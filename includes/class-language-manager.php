<?php
defined( 'ABSPATH' ) || exit;

class WPM_Language_Manager {

	private static $instance = null;
	private $languages = array();
	private $current_lang = '';

	private function __construct() {
		$this->languages = get_option( 'wpm_languages', array() );
		add_filter( 'locale', array( $this, 'filter_locale' ) );
	}

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function get_slugs() {
		return array_keys( $this->languages );
	}

	public function get_all() {
		return $this->languages;
	}

	public function get( $slug ) {
		return isset( $this->languages[ $slug ] ) ? $this->languages[ $slug ] : null;
	}

	public function get_default() {
		foreach ( $this->languages as $slug => $cfg ) {
			if ( ! empty( $cfg['default'] ) ) {
				return $slug;
			}
		}
		$keys = array_keys( $this->languages );
		return ! empty( $keys ) ? $keys[0] : 'fr';
	}

	public function is_valid( $slug ) {
		return isset( $this->languages[ $slug ] );
	}

	public function set_current( $slug ) {
		if ( $this->is_valid( $slug ) ) {
			$this->current_lang = $slug;
		}
	}

	public function get_current() {
		return $this->current_lang ? $this->current_lang : $this->get_default();
	}

	public function filter_locale( $locale ) {
		$lang = $this->get_current();
		$cfg  = $this->get( $lang );
		return ( $cfg && ! empty( $cfg['locale'] ) ) ? $cfg['locale'] : $locale;
	}

	public function get_page_map() {
		return get_option( 'wpm_page_map', array() );
	}

	public function get_translated_page( $post_id, $lang ) {
		$map = $this->get_page_map();
		foreach ( $map as $group ) {
			if ( in_array( (int) $post_id, array_map( 'intval', $group ), true ) ) {
				return isset( $group[ $lang ] ) ? (int) $group[ $lang ] : null;
			}
		}
		return null;
	}

	public function save_languages( $languages ) {
		$this->languages = $languages;
		update_option( 'wpm_languages', $languages );
	}

	public function save_page_map( $map ) {
		update_option( 'wpm_page_map', $map );
	}
}
