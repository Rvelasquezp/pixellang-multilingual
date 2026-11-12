<?php
/**
 * <link rel="alternate" hreflang> tags.
 *
 * Only real, published translations are listed, with their clean permalink.
 * Since 1.1 the URLs no longer carry ?set_lang= (a redirecting URL is not a
 * valid alternate for search engines) and pages without translations output
 * nothing instead of pointing every language to the home page.
 *
 * @package PixelLang_Multilingual
 */

defined( 'ABSPATH' ) || exit;

class WPM_Hreflang {

	private static $instance = null;

	private function __construct() {
		add_action( 'wp_head', array( $this, 'output_hreflang_tags' ), 2 );
	}

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Alternate URLs of the current page: hreflang => URL.
	 *
	 * @return array<string,string>
	 */
	public function get_alternates() {
		$manager = WPM_Language_Manager::instance();
		$object  = get_queried_object();
		$urls    = array();

		if ( $object instanceof WP_Post && is_singular() && WPM_Settings::is_translatable_post_type( $object->post_type ) ) {
			foreach ( WPM_Translations::get_post_translations( $object->ID ) as $lang => $id ) {
				if ( $id === $object->ID || 'publish' === get_post_status( $id ) ) {
					$urls[ $lang ] = get_permalink( $id );
				}
			}
		} elseif ( $object instanceof WP_Term && WPM_Settings::is_translatable_taxonomy( $object->taxonomy ) && ! is_paged() ) {
			foreach ( WPM_Translations::get_translations( 'term', $object->term_id ) as $lang => $id ) {
				$link = get_term_link( (int) $id, $object->taxonomy );
				if ( ! is_wp_error( $link ) ) {
					$urls[ $lang ] = $link;
				}
			}
		}

		$alternates = array();
		foreach ( $manager->get_slugs() as $lang ) {
			if ( ! empty( $urls[ $lang ] ) ) {
				$alternates[ $manager->get_hreflang( $lang ) ] = $urls[ $lang ];
			}
		}
		if ( count( $alternates ) < 2 ) {
			return array();
		}
		$default = $manager->get_default();
		if ( ! empty( $urls[ $default ] ) ) {
			$alternates['x-default'] = $urls[ $default ];
		}

		/**
		 * Filters the hreflang alternates of the current page.
		 *
		 * @param array $alternates hreflang code => URL.
		 */
		return apply_filters( 'wpm_hreflang_alternates', $alternates );
	}

	public function output_hreflang_tags() {
		if ( ! WPM_Settings::get( 'hreflang' ) || is_search() || is_404() ) {
			return;
		}
		$alternates = $this->get_alternates();
		if ( ! $alternates ) {
			return;
		}
		foreach ( $alternates as $hreflang => $url ) {
			printf( '<link rel="alternate" hreflang="%s" href="%s" />' . "\n", esc_attr( $hreflang ), esc_url( $url ) );
		}
	}
}
