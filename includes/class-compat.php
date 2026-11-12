<?php
/**
 * Public helper functions and WPML-compatible filters.
 *
 * Themes and plugins written for WPML often call apply_filters(
 * 'wpml_current_language', null ) (the PixelLang theme does, for its
 * Contact Form 7 select). The filters below answer those calls when WPML
 * itself is not active, so that code works without changes.
 *
 * @package PixelLang_Multilingual
 */

defined( 'ABSPATH' ) || exit;

class WPM_Compat {

	public static function init() {
		if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
			return; // Never answer for a real WPML.
		}
		add_filter( 'wpml_current_language', array( __CLASS__, 'current_language' ) );
		add_filter( 'wpml_default_language', array( __CLASS__, 'default_language' ) );
		add_filter( 'wpml_active_languages', array( __CLASS__, 'active_languages' ), 10, 2 );
		add_filter( 'wpml_object_id', array( __CLASS__, 'object_id' ), 10, 4 );
		add_filter( 'wpml_element_language_code', array( __CLASS__, 'element_language' ), 10, 2 );
	}

	public static function current_language() {
		return WPM_Language_Manager::instance()->get_current();
	}

	public static function default_language() {
		return WPM_Language_Manager::instance()->get_default();
	}

	/**
	 * Same shape as WPML's wpml_active_languages.
	 *
	 * @return array
	 */
	public static function active_languages() {
		$manager = WPM_Language_Manager::instance();
		$current = $manager->get_current();
		$out     = array();
		foreach ( $manager->get_all() as $slug => $cfg ) {
			$out[ $slug ] = array(
				'code'             => $slug,
				'native_name'      => $cfg['label'],
				'translated_name'  => $cfg['label'],
				'default_locale'   => $cfg['locale'],
				'active'           => $slug === $current ? 1 : 0,
				'url'              => is_admin() ? home_url( '/' ) : WPM_Url_Handler::instance()->get_language_url( $slug ),
				'language_code'    => $slug,
			);
		}
		return $out;
	}

	/**
	 * wpml_object_id: translation of a post or term.
	 *
	 * @param int         $id              Object ID.
	 * @param string      $type            Post type or taxonomy.
	 * @param bool        $return_original Return $id when there is no translation.
	 * @param string|null $lang            Language (current by default).
	 * @return int|null
	 */
	public static function object_id( $id, $type = 'post', $return_original = false, $lang = null ) {
		$lang = $lang ? $lang : WPM_Language_Manager::instance()->get_current();
		$kind = taxonomy_exists( (string) $type ) ? 'term' : 'post';
		$tid  = WPM_Translations::get_translation( $kind, (int) $id, $lang );
		if ( $tid ) {
			return $tid;
		}
		return $return_original ? $id : null;
	}

	/**
	 * wpml_element_language_code.
	 *
	 * @param mixed $language Default value.
	 * @param array $args     element_id, element_type.
	 * @return string|null
	 */
	public static function element_language( $language, $args ) {
		if ( empty( $args['element_id'] ) ) {
			return $language;
		}
		$kind = ( ! empty( $args['element_type'] ) && taxonomy_exists( $args['element_type'] ) ) ? 'term' : 'post';
		$lang = WPM_Translations::get_language( $kind, (int) $args['element_id'] );
		return $lang ? $lang : $language;
	}
}

/**
 * Current language code.
 *
 * @return string
 */
function wpm_current_language() {
	return WPM_Language_Manager::instance()->get_current();
}

/**
 * Translation of a post in a language (current language by default).
 *
 * @param int         $post_id Post ID.
 * @param string|null $lang    Language code.
 * @return int 0 when there is none.
 */
function wpm_get_post_translation( $post_id, $lang = null ) {
	return WPM_Translations::get_post_translation( $post_id, $lang ? $lang : wpm_current_language() );
}

/**
 * URL of the current page in a language.
 *
 * @param string $lang Language code.
 * @return string
 */
function wpm_get_language_url( $lang ) {
	return WPM_Url_Handler::instance()->get_language_url( $lang );
}
