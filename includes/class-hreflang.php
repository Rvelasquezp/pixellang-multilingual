<?php
defined( 'ABSPATH' ) || exit;

class WPM_Hreflang {

	private static $instance = null;

	private function __construct() {
		add_action( 'wp_head', array( $this, 'output_hreflang_tags' ) );
	}

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function output_hreflang_tags() {
		$manager   = WPM_Language_Manager::instance();
		$languages = $manager->get_all();
		$post_id   = get_queried_object_id();

		echo "\n<!-- WP Multilingual hreflang -->\n";

		foreach ( $languages as $slug => $cfg ) {
			$url = $this->get_url_for_lang( $slug, $post_id, $manager );
			if ( ! $url ) {
				continue;
			}
			$hreflang = $this->slug_to_hreflang( $slug, $cfg );
			printf(
				'<link rel="alternate" hreflang="%s" href="%s" />' . "\n",
				esc_attr( $hreflang ),
				esc_url( $url )
			);
		}

		$default_slug = $manager->get_default();
		$default_url  = $this->get_url_for_lang( $default_slug, $post_id, $manager );
		if ( $default_url ) {
			printf(
				'<link rel="alternate" hreflang="x-default" href="%s" />' . "\n",
				esc_url( $default_url )
			);
		}

		echo "<!-- /WP Multilingual hreflang -->\n";
	}

	private function get_url_for_lang( $slug, $post_id, $manager ) {
		if ( ! $post_id ) {
			return add_query_arg( 'set_lang', $slug, home_url( '/' ) );
		}

		$translated_id = $manager->get_translated_page( $post_id, $slug );
		if ( $translated_id ) {
			$url = get_permalink( $translated_id );
			return $url ? add_query_arg( 'set_lang', $slug, $url ) : '';
		}

		return add_query_arg( 'set_lang', $slug, home_url( '/' ) );
	}

	private function slug_to_hreflang( $slug, $cfg ) {
		if ( ! empty( $cfg['locale'] ) ) {
			return str_replace( '_', '-', $cfg['locale'] );
		}
		return $slug;
	}
}
