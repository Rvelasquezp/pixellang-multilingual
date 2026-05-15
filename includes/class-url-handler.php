<?php
defined( 'ABSPATH' ) || exit;

class WPM_Url_Handler {

	private static $instance = null;
	const COOKIE_NAME = 'wpm_lang';
	const COOKIE_DAYS = 30;

	private function __construct() {
		add_action( 'init', array( $this, 'resolve_language' ), 1 );
	}

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function resolve_language() {
		$manager = WPM_Language_Manager::instance();

		// 1. ?set_lang= switch request.
		// phpcs:disable WordPress.Security.NonceVerification
		if ( isset( $_GET['set_lang'] ) ) {
			$slug = sanitize_key( $_GET['set_lang'] );
			if ( $manager->is_valid( $slug ) ) {
				$this->set_cookie( $slug );
				$manager->set_current( $slug );
				$redirect = $this->get_redirect_url( $slug );
				wp_safe_redirect( $redirect );
				exit;
			}
		}
		// phpcs:enable

		// 2. Cookie.
		if ( isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			$slug = sanitize_key( $_COOKIE[ self::COOKIE_NAME ] );
			if ( $manager->is_valid( $slug ) ) {
				$manager->set_current( $slug );
				return;
			}
		}

		// 3. Browser Accept-Language header.
		$slug = $this->detect_browser_language();
		if ( $slug ) {
			$this->set_cookie( $slug );
			$manager->set_current( $slug );
			return;
		}

		// 4. Default language.
		$manager->set_current( $manager->get_default() );
	}

	private function set_cookie( $slug ) {
		$expires = time() + ( self::COOKIE_DAYS * DAY_IN_SECONDS );
		setcookie( self::COOKIE_NAME, $slug, $expires, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN ? COOKIE_DOMAIN : '', is_ssl(), true );
		$_COOKIE[ self::COOKIE_NAME ] = $slug;
	}

	private function detect_browser_language() {
		$manager = WPM_Language_Manager::instance();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$header = isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '';
		if ( ! $header ) {
			return '';
		}
		$parts = explode( ',', $header );
		foreach ( $parts as $part ) {
			$pieces = explode( ';', $part );
			$lang   = trim( $pieces[0] );
			$slug   = strtolower( substr( $lang, 0, 2 ) );
			if ( $manager->is_valid( $slug ) ) {
				return $slug;
			}
		}
		return '';
	}

	private function get_redirect_url( $slug ) {
		$referer = wp_get_referer();
		if ( ! $referer ) {
			return home_url( '/' );
		}
		$post_id = url_to_postid( $referer );
		if ( $post_id ) {
			$translated = WPM_Language_Manager::instance()->get_translated_page( $post_id, $slug );
			if ( $translated ) {
				$url = get_permalink( $translated );
				if ( $url ) {
					return $url;
				}
			}
		}
		return home_url( '/' );
	}

	public function switch_url( $slug, $base_url = '' ) {
		$base = $base_url ? $base_url : ( is_singular() ? get_permalink() : home_url( '/' ) );
		return esc_url( add_query_arg( 'set_lang', $slug, $base ) );
	}
}
