<?php
/**
 * License key: PixelLang Multilingual is for Agence Pixel clients only.
 *
 * A key is issued by Pixel with a private tool kept outside the plugin
 * (~/.pixellang/license-key.php on Pixel's computer) and signed with Pixel's
 * private Ed25519 key, which never leaves Pixel. The plugin only holds the
 * public key, so it can check a key offline (no license server) but cannot
 * create one. Format:
 *
 *   PXL1.<base64url JSON payload>.<base64url signature of the payload>
 *
 * Payload: c (client name), d (domains: "client.com" also covers
 * www.client.com; "*.client.com" covers its subdomains), e (expiry date
 * Y-m-d, or null for no expiry), i (issue date), n (key id).
 *
 * Without a valid key the public site keeps working (a client's site is
 * never broken by a license): the plugin screens are locked, except the
 * License screen, and the admin shows a notice. Development hosts
 * (localhost, *.local, *.test, *.localhost) need no key.
 *
 * This is a deterrent, not DRM: the plugin is GPL PHP code.
 *
 * @package PixelLang_Multilingual
 */

defined( 'ABSPATH' ) || exit;

class WPM_License {

	const OPTION     = 'wpm_license_key';
	const PREFIX     = 'PXL1';
	const PUBLIC_KEY = 'ql5GJ8P7InmmwV9QlxFBDppsSjnd1DXB+xVmyFokmjo=';
	const WARN_DAYS  = 30;

	/**
	 * Request cache of the status.
	 *
	 * @var array|null
	 */
	private static $status = null;

	public static function init() {
		if ( is_admin() ) {
			add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
			add_action( 'admin_post_wpm_license', array( __CLASS__, 'handle_form' ) );
		}
		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( 'WP_CLI' ) ) {
			WP_CLI::add_command( 'pixellang license', array( __CLASS__, 'cli' ) );
		}
	}

	// ---------------------------------------------------------------------
	// Keys
	// ---------------------------------------------------------------------

	/**
	 * Public key used to check signatures (filterable for tests).
	 *
	 * @return string Raw 32 bytes.
	 */
	private static function public_key() {
		/**
		 * Filters the base64 Ed25519 public key that checks license keys.
		 *
		 * @param string $key Base64 public key.
		 */
		$key = base64_decode( (string) apply_filters( 'wpm_license_public_key', self::PUBLIC_KEY ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		return ( false !== $key && 32 === strlen( $key ) ) ? $key : '';
	}

	/**
	 * URL-safe base64 decoding.
	 *
	 * @param string $data Data.
	 * @return string|false
	 */
	private static function b64url_decode( $data ) {
		$data = strtr( $data, '-_', '+/' );
		$pad  = strlen( $data ) % 4;
		if ( $pad ) {
			$data .= str_repeat( '=', 4 - $pad );
		}
		return base64_decode( $data, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
	}

	/**
	 * Payload of a key whose signature is valid, or null.
	 *
	 * @param string $key License key.
	 * @return array|null
	 */
	public static function decode( $key ) {
		$key   = preg_replace( '/\s+/', '', (string) $key );
		$parts = explode( '.', $key );
		if ( 3 !== count( $parts ) || self::PREFIX !== $parts[0] || ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
			return null;
		}
		$json   = self::b64url_decode( $parts[1] );
		$sig    = self::b64url_decode( $parts[2] );
		$public = self::public_key();
		if ( false === $json || false === $sig || 64 !== strlen( $sig ) || ! $public ) {
			return null;
		}
		try {
			if ( ! sodium_crypto_sign_verify_detached( $sig, $parts[0] . '.' . $parts[1], $public ) ) {
				return null;
			}
		} catch ( Exception $e ) {
			return null;
		}
		$payload = json_decode( $json, true );
		if ( ! is_array( $payload ) || empty( $payload['d'] ) || ! is_array( $payload['d'] ) ) {
			return null;
		}
		return $payload;
	}

	// ---------------------------------------------------------------------
	// Domains
	// ---------------------------------------------------------------------

	/**
	 * Host of the site, lowercase, without "www.".
	 *
	 * @return string
	 */
	public static function site_host() {
		$host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		return preg_replace( '/^www\./', '', $host );
	}

	/**
	 * Whether a host is a development environment (no key needed).
	 *
	 * @param string $host Host.
	 * @return bool
	 */
	public static function is_dev_host( $host ) {
		$dev = in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true )
			|| (bool) preg_match( '/\.(local|test|localhost)$/', $host );
		/**
		 * Filters whether the site runs on a development host.
		 *
		 * @param bool   $dev  Whether no key is needed.
		 * @param string $host Site host.
		 */
		return (bool) apply_filters( 'wpm_license_is_dev_host', $dev, $host );
	}

	/**
	 * Whether a host is covered by a list of licensed domains.
	 *
	 * @param string $host    Host (without www.).
	 * @param array  $domains Domains of the key.
	 * @return bool
	 */
	public static function host_matches( $host, array $domains ) {
		foreach ( $domains as $domain ) {
			$domain = preg_replace( '/^www\./', '', strtolower( trim( (string) $domain ) ) );
			if ( '' === $domain ) {
				continue;
			}
			if ( 0 === strpos( $domain, '*.' ) ) {
				$base = substr( $domain, 2 );
				if ( $host === $base || substr( $host, -strlen( '.' . $base ) ) === '.' . $base ) {
					return true;
				}
			} elseif ( $host === $domain ) {
				return true;
			}
		}
		return false;
	}

	// ---------------------------------------------------------------------
	// Status
	// ---------------------------------------------------------------------

	/**
	 * License status of this site.
	 *
	 * @return array{state: string, payload: array|null, days: int|null}
	 *   state: valid, expiring, development, missing, invalid, domain, expired.
	 */
	public static function status() {
		if ( null !== self::$status ) {
			return self::$status;
		}
		$key     = (string) get_option( self::OPTION, '' );
		$payload = $key ? self::decode( $key ) : null;
		$host    = self::site_host();
		$days    = null;

		if ( $payload && ! empty( $payload['e'] ) ) {
			$expiry = strtotime( $payload['e'] . ' 23:59:59 UTC' );
			$days   = $expiry ? (int) floor( ( $expiry - time() ) / DAY_IN_SECONDS ) : null;
		}

		if ( $payload && ! self::host_matches( $host, $payload['d'] ) ) {
			$state = self::is_dev_host( $host ) ? 'development' : 'domain';
		} elseif ( $payload && null !== $days && $days < 0 ) {
			$state = self::is_dev_host( $host ) ? 'development' : 'expired';
		} elseif ( $payload ) {
			$state = ( null !== $days && $days <= self::WARN_DAYS ) ? 'expiring' : 'valid';
		} elseif ( self::is_dev_host( $host ) ) {
			$state = 'development';
		} else {
			$state = $key ? 'invalid' : 'missing';
		}

		self::$status = array(
			'state'   => $state,
			'payload' => $payload,
			'days'    => $days,
		);
		return self::$status;
	}

	/**
	 * Whether the plugin screens are unlocked.
	 *
	 * @return bool
	 */
	public static function is_active() {
		return in_array( self::status()['state'], array( 'valid', 'expiring', 'development' ), true );
	}

	/**
	 * Clears the request cache (after saving a key, in tests).
	 */
	public static function flush() {
		self::$status = null;
	}

	/**
	 * Stores a key after checking it. '' removes the key.
	 *
	 * @param string $key License key.
	 * @return true|WP_Error
	 */
	public static function save( $key ) {
		$key = preg_replace( '/\s+/', '', (string) $key );
		if ( '' === $key ) {
			delete_option( self::OPTION );
			self::flush();
			return true;
		}
		if ( ! self::decode( $key ) ) {
			return new WP_Error( 'wpm_license_invalid', __( 'This license key is not valid. Copy it again exactly as Agence Pixel sent it.', 'pixellang-multilingual' ) );
		}
		update_option( self::OPTION, $key, false );
		self::flush();
		return true;
	}

	/**
	 * Human readable state.
	 *
	 * @param string $state State.
	 * @return string
	 */
	public static function label( $state ) {
		$labels = array(
			'valid'       => __( 'Active', 'pixellang-multilingual' ),
			'expiring'    => __( 'Expires soon', 'pixellang-multilingual' ),
			'development' => __( 'Development site', 'pixellang-multilingual' ),
			'missing'     => __( 'No license', 'pixellang-multilingual' ),
			'invalid'     => __( 'Invalid key', 'pixellang-multilingual' ),
			'domain'      => __( 'Other domain', 'pixellang-multilingual' ),
			'expired'     => __( 'Expired', 'pixellang-multilingual' ),
		);
		return isset( $labels[ $state ] ) ? $labels[ $state ] : $state;
	}

	/**
	 * Explanation of the state, for notices and the License screen.
	 *
	 * @return string
	 */
	public static function message() {
		$s = self::status();
		switch ( $s['state'] ) {
			case 'missing':
				return __( 'PixelLang Multilingual has no license key. The site keeps working in all its languages, but the plugin settings are locked until a key from Agence Pixel is added.', 'pixellang-multilingual' );
			case 'invalid':
				return __( 'The PixelLang Multilingual license key is not valid. The site keeps working, but the plugin settings are locked.', 'pixellang-multilingual' );
			case 'domain':
				/* translators: %s: site domain. */
				return sprintf( __( 'The PixelLang Multilingual license key is not issued for %s. The site keeps working, but the plugin settings are locked. Ask Agence Pixel for a key for this domain.', 'pixellang-multilingual' ), self::site_host() );
			case 'expired':
				return __( 'The PixelLang Multilingual license has expired. The site keeps working, but the plugin settings are locked until the license is renewed with Agence Pixel.', 'pixellang-multilingual' );
			case 'expiring':
				/* translators: %d: number of days. */
				return sprintf( _n( 'The PixelLang Multilingual license expires in %d day. Contact Agence Pixel to renew it.', 'The PixelLang Multilingual license expires in %d days. Contact Agence Pixel to renew it.', max( 0, (int) $s['days'] ), 'pixellang-multilingual' ), max( 0, (int) $s['days'] ) );
			case 'development':
				return __( 'No license key is needed on a development site (.local, .test, localhost). The key of the live site is checked on its own domain.', 'pixellang-multilingual' );
		}
		return __( 'PixelLang Multilingual is licensed for this site.', 'pixellang-multilingual' );
	}

	// ---------------------------------------------------------------------
	// Admin
	// ---------------------------------------------------------------------

	/**
	 * Notice in the whole admin when the license needs attention.
	 */
	public static function admin_notice() {
		if ( ! current_user_can( 'manage_options' ) || 'wpm-license' === WPM_Admin::current_screen() ) {
			return;
		}
		$state = self::status()['state'];
		if ( ! in_array( $state, array( 'missing', 'invalid', 'domain', 'expired', 'expiring' ), true ) ) {
			return;
		}
		printf(
			'<div class="notice notice-%1$s"><p><strong>PixelLang Multilingual.</strong> %2$s <a href="%3$s">%4$s</a></p></div>',
			'expiring' === $state ? 'warning' : 'error',
			esc_html( self::message() ),
			esc_url( WPM_Admin::url( 'wpm-license' ) ),
			esc_html__( 'Manage the license', 'pixellang-multilingual' )
		);
	}

	/**
	 * License screen form.
	 */
	public static function handle_form() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'pixellang-multilingual' ), 403 );
		}
		check_admin_referer( 'wpm_license' );
		$remove = ! empty( $_POST['remove'] );
		$key    = $remove ? '' : ( isset( $_POST['license_key'] ) ? sanitize_textarea_field( wp_unslash( $_POST['license_key'] ) ) : '' );
		$result = self::save( $key );
		$notice = is_wp_error( $result ) ? 'license_invalid' : ( $remove ? 'license_removed' : 'license_saved' );
		wp_safe_redirect( WPM_Admin::url( 'wpm-license', array( 'wpm_notice' => $notice ) ) );
		exit;
	}

	// ---------------------------------------------------------------------
	// WP-CLI
	// ---------------------------------------------------------------------

	/**
	 * Manages the PixelLang Multilingual license.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : status, activate or remove.
	 *
	 * [<key>]
	 * : License key (activate).
	 *
	 * ## EXAMPLES
	 *
	 *     wp pixellang license status
	 *     wp pixellang license activate PXL1.xxx.yyy
	 *
	 * @param array $args Arguments.
	 */
	public static function cli( $args ) {
		$action = isset( $args[0] ) ? $args[0] : 'status';
		if ( 'activate' === $action || 'remove' === $action ) {
			$result = self::save( 'remove' === $action ? '' : ( isset( $args[1] ) ? $args[1] : '' ) );
			if ( is_wp_error( $result ) ) {
				WP_CLI::error( $result->get_error_message() );
			}
		}
		$s = self::status();
		WP_CLI::log( sprintf( '%s: %s', self::label( $s['state'] ), self::message() ) );
		if ( $s['payload'] ) {
			WP_CLI::log( sprintf( 'Client: %s | Domains: %s | Expires: %s', isset( $s['payload']['c'] ) ? $s['payload']['c'] : '', implode( ', ', $s['payload']['d'] ), ! empty( $s['payload']['e'] ) ? $s['payload']['e'] : 'never' ) );
		}
		if ( 'status' !== $action ) {
			WP_CLI::success( 'remove' === $action ? 'License removed.' : 'License activated.' );
		}
	}
}
