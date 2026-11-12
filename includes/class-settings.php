<?php
/**
 * Central access to plugin options, with defaults and sanitization.
 *
 * Legacy options are kept under their original names so existing sites and
 * the theme keep working: wpm_languages, wpm_post_types, wpm_menus,
 * wpm_forms, wpm_page_map and wpm_template_map. Settings introduced in 1.1
 * live in a single array option, wpm_options.
 *
 * @package PixelLang_Multilingual
 */

defined( 'ABSPATH' ) || exit;

class WPM_Settings {

	const OPTION = 'wpm_options';

	/**
	 * Request cache of the merged options.
	 *
	 * @var array|null
	 */
	private static $options = null;

	/**
	 * Default values. Defaults reproduce the 1.0 behavior whenever a setting
	 * changes how the front end works (browser detection on, query filtering off).
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'browser_detection' => true,
			'cookie_days'       => 30,
			'front_redirect'    => true,
			'filter_queries'    => false,
			'hreflang'          => true,
			'taxonomies'        => array(),
			'menu_locations'    => array(),
			'form_groups'       => array(),
		);
	}

	/**
	 * All options merged with defaults.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$options ) {
			$stored        = get_option( self::OPTION, array() );
			self::$options = wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );
		}
		return self::$options;
	}

	/**
	 * One option value.
	 *
	 * @param string $key Option key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Merges and saves a subset of options, after sanitization.
	 *
	 * @param array $values Raw values keyed by option key.
	 * @return array The stored options.
	 */
	public static function update( array $values ) {
		$current = self::all();
		foreach ( $values as $key => $value ) {
			if ( array_key_exists( $key, self::defaults() ) ) {
				$current[ $key ] = self::sanitize( $key, $value );
			}
		}
		update_option( self::OPTION, $current, true );
		self::$options = $current;
		return $current;
	}

	/**
	 * Sanitizes one option value.
	 *
	 * @param string $key   Option key.
	 * @param mixed  $value Raw value.
	 * @return mixed
	 */
	public static function sanitize( $key, $value ) {
		$langs = WPM_Language_Manager::instance()->get_slugs();

		switch ( $key ) {
			case 'browser_detection':
			case 'front_redirect':
			case 'filter_queries':
			case 'hreflang':
				return (bool) $value;

			case 'cookie_days':
				return min( 3650, max( 1, absint( $value ) ) );

			case 'taxonomies':
				$allowed = array_keys( self::get_translatable_taxonomies() );
				return array_values( array_intersect( array_map( 'sanitize_key', (array) $value ), $allowed ) );

			case 'menu_locations':
				$clean     = array();
				$locations = array_keys( get_registered_nav_menus() );
				foreach ( (array) $value as $lang => $map ) {
					$lang = sanitize_key( $lang );
					if ( ! in_array( $lang, $langs, true ) || ! is_array( $map ) ) {
						continue;
					}
					foreach ( $map as $location => $menu_id ) {
						$location = sanitize_key( $location );
						$menu_id  = absint( $menu_id );
						if ( $menu_id && in_array( $location, $locations, true ) && is_nav_menu( $menu_id ) ) {
							$clean[ $lang ][ $location ] = $menu_id;
						}
					}
				}
				return $clean;

			case 'form_groups':
				$clean = array();
				foreach ( (array) $value as $group ) {
					if ( ! is_array( $group ) || empty( $group['provider'] ) ) {
						continue;
					}
					$provider = sanitize_key( $group['provider'] );
					if ( ! in_array( $provider, array( 'cf7', 'gf' ), true ) ) {
						continue;
					}
					$forms = array();
					foreach ( (array) ( isset( $group['forms'] ) ? $group['forms'] : array() ) as $lang => $form_id ) {
						$lang    = sanitize_key( $lang );
						$form_id = absint( $form_id );
						if ( $form_id && in_array( $lang, $langs, true ) ) {
							$forms[ $lang ] = $form_id;
						}
					}
					if ( count( $forms ) >= 2 ) {
						$clean[] = array(
							'provider' => $provider,
							'forms'    => $forms,
						);
					}
				}
				return $clean;
		}
		return $value;
	}

	/**
	 * Post types enabled for translation (legacy option wpm_post_types).
	 *
	 * @return string[]
	 */
	public static function get_post_types() {
		$types = get_option( 'wpm_post_types', array( 'page', 'post' ) );
		return is_array( $types ) ? array_values( array_unique( array_map( 'sanitize_key', $types ) ) ) : array( 'page', 'post' );
	}

	/**
	 * Whether a post type is translatable.
	 *
	 * @param string $post_type Post type slug.
	 * @return bool
	 */
	public static function is_translatable_post_type( $post_type ) {
		return in_array( $post_type, self::get_post_types(), true );
	}

	/**
	 * Taxonomies enabled for translation.
	 *
	 * @return string[]
	 */
	public static function get_taxonomies() {
		return (array) self::get( 'taxonomies' );
	}

	/**
	 * Whether a taxonomy is translatable.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 * @return bool
	 */
	public static function is_translatable_taxonomy( $taxonomy ) {
		return in_array( $taxonomy, self::get_taxonomies(), true );
	}

	/**
	 * Post types that can be enabled for translation (internal WP types excluded).
	 *
	 * @return WP_Post_Type[]
	 */
	public static function get_translatable_post_types() {
		$excluded = array(
			'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset',
			'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part',
			'wp_global_styles', 'wp_navigation', 'wp_font_face', 'wp_font_family',
			'acf-field-group', 'acf-field', 'acf-post-type', 'acf-taxonomy', 'acf-ui-options-page',
			'wpcf7_contact_form',
		);
		$result = array();
		foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $slug => $obj ) {
			if ( ! in_array( $slug, $excluded, true ) ) {
				$result[ $slug ] = $obj;
			}
		}
		return $result;
	}

	/**
	 * Public taxonomies that can be enabled for translation.
	 *
	 * @return WP_Taxonomy[]
	 */
	public static function get_translatable_taxonomies() {
		$excluded = array( 'nav_menu', 'link_category', 'post_format', 'wp_theme', 'wp_template_part_area', 'wp_pattern_category' );
		$result   = array();
		foreach ( get_taxonomies( array( 'show_ui' => true ), 'objects' ) as $slug => $obj ) {
			if ( ! in_array( $slug, $excluded, true ) ) {
				$result[ $slug ] = $obj;
			}
		}
		return $result;
	}

	/**
	 * Clears the request cache (used by tests and after imports).
	 */
	public static function flush() {
		self::$options = null;
	}
}
