<?php
/**
 * Site title and tagline per language.
 *
 * Settings > General keeps the texts of the default language. For the other
 * languages, Settings > Site identity stores a title and a tagline; on the
 * front end, get_option( 'blogname' | 'blogdescription' ) returns them, so
 * every consumer follows: the <title> of the pages, the Site Title and
 * Site Tagline blocks, feeds, Yoast SEO and Rank Math. An empty field falls
 * back to the default language. The admin always shows the real options.
 *
 * @package PixelLang_Multilingual
 */

defined( 'ABSPATH' ) || exit;

class WPM_Site_Texts {

	const FIELDS = array( 'blogname', 'blogdescription' );

	public static function init() {
		foreach ( self::FIELDS as $field ) {
			add_filter( 'option_' . $field, array( __CLASS__, 'filter_option' ), 10, 2 );
		}
	}

	/**
	 * Texts of a language: field => text ('' when not translated).
	 *
	 * @param string $lang Language code.
	 * @return array
	 */
	public static function get( $lang ) {
		$all = (array) WPM_Settings::get( 'site_texts' );
		$out = array();
		foreach ( self::FIELDS as $field ) {
			$out[ $field ] = isset( $all[ $lang ][ $field ] ) ? (string) $all[ $lang ][ $field ] : '';
		}
		return $out;
	}

	/**
	 * Front end: title or tagline of the current language.
	 *
	 * @param mixed  $value  Stored value (default language).
	 * @param string $option Option name.
	 * @return mixed
	 */
	public static function filter_option( $value, $option ) {
		$manager = WPM_Language_Manager::instance();
		if ( ! $manager->is_front_request() ) {
			return $value;
		}
		$lang = $manager->get_current();
		if ( $lang === $manager->get_default() ) {
			return $value;
		}
		$texts = self::get( $lang );
		return '' !== $texts[ $option ] ? $texts[ $option ] : $value;
	}

	/**
	 * Sanitizes the setting: lang => [ blogname, blogdescription ].
	 *
	 * @param mixed $value Raw value.
	 * @return array
	 */
	public static function sanitize( $value ) {
		$manager = WPM_Language_Manager::instance();
		$clean   = array();
		foreach ( (array) $value as $lang => $fields ) {
			$lang = sanitize_key( $lang );
			if ( ! $manager->is_valid( $lang ) || $lang === $manager->get_default() || ! is_array( $fields ) ) {
				continue;
			}
			foreach ( self::FIELDS as $field ) {
				$text = isset( $fields[ $field ] ) ? sanitize_text_field( $fields[ $field ] ) : '';
				if ( '' !== $text ) {
					$clean[ $lang ][ $field ] = $text;
				}
			}
		}
		return $clean;
	}
}
