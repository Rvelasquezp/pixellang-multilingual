<?php
/**
 * Works out the language of each request and builds language URLs.
 *
 * Order of precedence:
 * 1. ?set_lang=xx (explicit switch): stores the choice, redirects to the
 *    translation of the page the visitor came from.
 * 2. The content itself: a post or term that has a language shows in that
 *    language, whatever the cookie says. Every translated URL is therefore
 *    canonical and safe for page caches and search engines.
 * 3. The wpm_lang cookie (last language seen or chosen).
 * 4. The browser Accept-Language header (optional, "redirect" mode; the
 *    "banner" mode only suggests the translation, see WPM_Language_Banner).
 * 5. The default language.
 *
 * @package PixelLang_Multilingual
 */

defined( 'ABSPATH' ) || exit;

class WPM_Url_Handler {

	private static $instance = null;

	const COOKIE_NAME = 'wpm_lang';
	const COOKIE_DAYS = 30;

	private function __construct() {
		add_action( 'init', array( $this, 'resolve_language' ), 1 );
		add_action( 'wp', array( $this, 'resolve_content_language' ), 1 );
		add_action( 'template_redirect', array( $this, 'redirect_old_slug' ), 5 );
	}

	/**
	 * 404 on the old address of a home page moved to /{language}/: 301 to it.
	 */
	public function redirect_old_slug() {
		if ( ! is_404() ) {
			return;
		}
		$segment = $this->request_base();
		$path    = trim( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ), '/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( '' === $segment || false !== strpos( trim( substr( $path, strpos( $path, $segment ) ), '/' ), '/' ) ) {
			return;
		}
		$ids = get_posts(
			array(
				'post_type'        => 'page',
				'post_status'      => 'publish',
				'meta_key'         => '_wpm_old_slug', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'       => sanitize_title( $segment ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'           => 'ids',
				'posts_per_page'   => 1,
				'no_found_rows'    => true,
				'suppress_filters' => true,
			)
		);
		if ( $ids ) {
			wp_safe_redirect( get_permalink( $ids[0] ), 301, 'PixelLang Multilingual' );
			exit;
		}
	}

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Early resolution (before the query): switch request, cookie, browser.
	 */
	public function resolve_language() {
		$manager = WPM_Language_Manager::instance();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Public, idempotent language switch.
		if ( isset( $_GET['set_lang'] ) && ! is_admin() ) {
			$slug = sanitize_key( wp_unslash( $_GET['set_lang'] ) );
			if ( $manager->is_valid( $slug ) ) {
				$this->set_cookie( $slug );
				$manager->set_current( $slug );
				wp_safe_redirect( $this->get_redirect_url( $slug ), 302, 'PixelLang Multilingual' );
				exit;
			}
		}
		// phpcs:enable

		$slug = $this->get_cookie_language();
		if ( $slug ) {
			$manager->set_current( $slug );
			return;
		}

		if ( 'redirect' === WPM_Settings::browser_mode() && ! is_admin() ) {
			$slug = $this->detect_browser_language();
			if ( $slug ) {
				$this->set_cookie( $slug );
				$manager->set_current( $slug );
				return;
			}
		}

		$manager->set_current( $manager->get_default() );
	}

	/**
	 * Late resolution (query known): the content decides the language.
	 */
	public function resolve_content_language() {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || is_feed() ) {
			return;
		}
		$manager = WPM_Language_Manager::instance();
		$visitor = $manager->get_current();
		$content = $this->get_queried_language();

		// Static front page: send visitors to the front page of their language.
		// Only while browsing the site (logo, menu): an address typed in the
		// browser or a link from another site shows the front page asked for.
		if ( $content && $content !== $visitor && is_front_page() && ! is_paged() && WPM_Settings::get( 'front_redirect' ) && $this->visitor_has_preference() && $this->is_internal_navigation() ) {
			$translation = WPM_Translations::get_public_post_translation( get_queried_object_id(), $visitor );
			if ( $translation ) {
				$url = get_permalink( $translation );
				if ( $url ) {
					wp_safe_redirect( $url, 302, 'PixelLang Multilingual' );
					exit;
				}
			}
		}

		if ( $content && $content !== $visitor ) {
			$this->switch_to( $content );
		}

		// Archives have no language: a translated URL base gives one.
		if ( ! $content && is_post_type_archive() && class_exists( 'WPM_Slugs' ) ) {
			$type = get_query_var( 'post_type' );
			$type = is_array( $type ) ? reset( $type ) : $type;
			$lang = WPM_Slugs::language_of_base( 'post_type', (string) $type, $this->request_base() );
			if ( $lang && $lang !== $visitor ) {
				$this->switch_to( $lang );
			}
		}
	}

	/**
	 * First segment of the requested path (without the home path).
	 *
	 * @return string
	 */
	public function request_base() {
		$path   = trim( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ), '/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$prefix = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
		if ( $prefix && 0 === strpos( $path, $prefix . '/' ) ) {
			$path = substr( $path, strlen( $prefix ) + 1 );
		}
		return (string) strtok( $path, '/' );
	}

	/**
	 * Language of the queried post or term, or ''.
	 *
	 * @return string
	 */
	public function get_queried_language() {
		$object = get_queried_object();
		if ( $object instanceof WP_Post && WPM_Settings::is_translatable_post_type( $object->post_type ) ) {
			return WPM_Translations::get_post_language( $object->ID );
		}
		if ( $object instanceof WP_Term && WPM_Settings::is_translatable_taxonomy( $object->taxonomy ) ) {
			return WPM_Translations::get_language( 'term', $object->term_id );
		}
		return '';
	}

	/**
	 * Makes a language current for the rest of the request.
	 *
	 * @param string $slug Language code.
	 */
	private function switch_to( $slug ) {
		$manager = WPM_Language_Manager::instance();
		$before  = determine_locale();

		// Text domains (WordPress core included, which gives <html lang>) were
		// loaded with the previous locale: reload them. switch_to_locale() must
		// run while the previous language is still current, otherwise it sees
		// the new locale through the 'locale' filter and does nothing.
		$locale = $manager->get_locale( $slug );
		if ( $locale && $locale !== $before && function_exists( 'switch_to_locale' ) ) {
			switch_to_locale( $locale );
		}
		$manager->set_current( $slug );
		$this->set_cookie( $slug );
	}

	/**
	 * Whether the request comes from a page of this site (Referer header).
	 *
	 * @return bool
	 */
	private function is_internal_navigation() {
		$referer = isset( $_SERVER['HTTP_REFERER'] ) ? (string) wp_unslash( $_SERVER['HTTP_REFERER'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$host    = $referer ? strtolower( (string) wp_parse_url( $referer, PHP_URL_HOST ) ) : '';
		return '' !== $host && strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) === $host;
	}

	/**
	 * Whether the visitor expressed a language (cookie or a detected browser language).
	 *
	 * @return bool
	 */
	private function visitor_has_preference() {
		return '' !== $this->get_cookie_language();
	}

	/**
	 * Valid language stored in the cookie, or ''.
	 *
	 * @return string
	 */
	private function get_cookie_language() {
		if ( empty( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return '';
		}
		$slug = sanitize_key( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );
		return WPM_Language_Manager::instance()->is_valid( $slug ) ? $slug : '';
	}

	/**
	 * Stores the language in a first-party cookie (no-op once headers are sent).
	 *
	 * @param string $slug Language code.
	 */
	private function set_cookie( $slug ) {
		$_COOKIE[ self::COOKIE_NAME ] = $slug;
		if ( headers_sent() ) {
			return;
		}
		$days = (int) WPM_Settings::get( 'cookie_days' );
		setcookie(
			self::COOKIE_NAME,
			$slug,
			array(
				'expires'  => time() + ( ( $days ? $days : self::COOKIE_DAYS ) * DAY_IN_SECONDS ),
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	/**
	 * First active language found in Accept-Language, by descending quality.
	 *
	 * @return string
	 */
	private function detect_browser_language() {
		$header = isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) : '';
		if ( ! $header ) {
			return '';
		}
		$candidates = array();
		foreach ( explode( ',', $header ) as $index => $part ) {
			$pieces = explode( ';', trim( $part ) );
			$code   = strtolower( substr( trim( $pieces[0] ), 0, 2 ) );
			$q      = 1.0;
			if ( isset( $pieces[1] ) && 0 === strpos( trim( $pieces[1] ), 'q=' ) ) {
				$q = (float) substr( trim( $pieces[1] ), 2 );
			}
			// Keep header order for equal weights.
			$candidates[] = array( $code, $q, $index );
		}
		usort(
			$candidates,
			function ( $a, $b ) {
				return $b[1] === $a[1] ? $a[2] - $b[2] : ( $b[1] > $a[1] ? 1 : -1 );
			}
		);
		$manager = WPM_Language_Manager::instance();
		foreach ( $candidates as $candidate ) {
			if ( $candidate[1] > 0 && $manager->is_valid( $candidate[0] ) ) {
				return $candidate[0];
			}
		}
		return '';
	}

	/**
	 * Destination of a ?set_lang= switch: the translation of the referring
	 * page, else the front page of the language, else the home page.
	 *
	 * @param string $slug Target language.
	 * @return string
	 */
	private function get_redirect_url( $slug ) {
		$referer = wp_get_referer();
		if ( $referer && wp_validate_redirect( $referer, false ) ) {
			$post_id = url_to_postid( $referer );
			if ( $post_id ) {
				$translated = WPM_Translations::get_public_post_translation( $post_id, $slug );
				if ( $translated ) {
					return get_permalink( $translated );
				}
			}
		}
		return $this->get_home_url( $slug );
	}

	/**
	 * Front page of a language (its translation when the front page is a page).
	 *
	 * @param string $slug Language code.
	 * @return string
	 */
	public function get_home_url( $slug ) {
		$front = (int) get_option( 'page_on_front' );
		if ( 'page' === get_option( 'show_on_front' ) && $front ) {
			$translated = WPM_Translations::get_public_post_translation( $front, $slug );
			if ( $translated ) {
				return get_permalink( $translated );
			}
		}
		return home_url( '/' );
	}

	/**
	 * URL of the current page in another language.
	 *
	 * Links straight to the translation when there is one (crawlable, no
	 * redirect). Without a translation, falls back to ?set_lang= on the home
	 * page of that language so the visitor's choice is still stored.
	 *
	 * @param string $slug     Language code.
	 * @param bool   $fallback Return a fallback URL when no translation exists.
	 * @return string Raw URL (escape on output), or '' when none and no fallback.
	 */
	public function get_language_url( $slug, $fallback = true ) {
		$object = get_queried_object();

		if ( $object instanceof WP_Post && WPM_Settings::is_translatable_post_type( $object->post_type ) ) {
			$translated = WPM_Translations::get_public_post_translation( $object->ID, $slug );
			if ( $translated ) {
				return $this->get_post_link( $translated, $slug );
			}
		} elseif ( $object instanceof WP_Term && WPM_Settings::is_translatable_taxonomy( $object->taxonomy ) ) {
			$translated = WPM_Translations::get_translation( 'term', $object->term_id, $slug );
			if ( $translated ) {
				$link = get_term_link( $translated, $object->taxonomy );
				if ( ! is_wp_error( $link ) ) {
					return $link;
				}
			}
		}

		// Archive of a content type: the archive in that language.
		if ( is_post_type_archive() && class_exists( 'WPM_Slugs' ) ) {
			$type = get_query_var( 'post_type' );
			$type = is_array( $type ) ? reset( $type ) : $type;
			$url  = WPM_Slugs::archive_url( (string) $type, $slug );
			if ( $url ) {
				return add_query_arg( 'set_lang', $slug, $url );
			}
		}

		if ( ! $fallback ) {
			return '';
		}
		return add_query_arg( 'set_lang', $slug, $this->get_home_url( $slug ) );
	}

	/**
	 * Permalink of a post for a language link.
	 *
	 * The site front page keeps ?set_lang= because, with the front page
	 * redirect, a visitor whose cookie holds another language would otherwise
	 * be sent back to the front page of that language.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $slug    Language of the post.
	 * @return string
	 */
	public function get_post_link( $post_id, $slug ) {
		$url = get_permalink( $post_id );
		if ( $url && (int) get_option( 'page_on_front' ) === (int) $post_id && 'page' === get_option( 'show_on_front' ) ) {
			$url = add_query_arg( 'set_lang', $slug, $url );
		}
		return $url ? $url : '';
	}

	/**
	 * Language switch URL. Kept for 1.0 compatibility (returns an escaped URL).
	 *
	 * @param string $slug     Language code.
	 * @param string $base_url Optional base URL.
	 * @return string
	 */
	public function switch_url( $slug, $base_url = '' ) {
		if ( $base_url ) {
			return esc_url( add_query_arg( 'set_lang', $slug, $base_url ) );
		}
		return esc_url( $this->get_language_url( $slug ) );
	}
}
