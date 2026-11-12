<?php
/**
 * Optional: limit front-end listings (blog, archives, search) to the current
 * language. Off by default because themes may already filter themselves
 * (the PixelLang theme filters its team listings with _wpm_language).
 *
 * Content without a language stays visible so nothing disappears on sites
 * where languages were never assigned.
 *
 * @package PixelLang_Multilingual
 */

defined( 'ABSPATH' ) || exit;

class WPM_Query_Filter {

	private static $instance = null;

	private function __construct() {
		if ( ! is_admin() ) {
			add_action( 'pre_get_posts', array( $this, 'filter_main_query' ) );
		}
	}

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * @param WP_Query $query Query.
	 */
	public function filter_main_query( $query ) {
		if ( ! $query->is_main_query() || $query->is_singular() || ! WPM_Settings::get( 'filter_queries' ) ) {
			return;
		}
		if ( ! ( $query->is_home() || $query->is_archive() || $query->is_search() ) ) {
			return;
		}
		$types = $query->get( 'post_type' );
		if ( empty( $types ) || 'any' === $types ) {
			$types = $query->is_search() ? WPM_Settings::get_post_types() : array( 'post' );
		}
		$translatable = array_filter( (array) $types, array( 'WPM_Settings', 'is_translatable_post_type' ) );
		if ( ! $translatable ) {
			return;
		}
		$meta_query   = (array) $query->get( 'meta_query' );
		$meta_query[] = self::language_clause( WPM_Language_Manager::instance()->get_current() );
		$query->set( 'meta_query', $meta_query );
	}

	/**
	 * meta_query clause: the language, or no language at all.
	 *
	 * @param string $lang Language code.
	 * @return array
	 */
	public static function language_clause( $lang ) {
		return array(
			'relation' => 'OR',
			array(
				'key'   => WPM_Translations::META_LANG,
				'value' => $lang,
			),
			array(
				'key'     => WPM_Translations::META_LANG,
				'compare' => 'NOT EXISTS',
			),
		);
	}
}
