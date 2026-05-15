<?php
defined( 'ABSPATH' ) || exit;

class WPM_Content_Switcher {

	private static $instance = null;

	private function __construct() {
		add_filter( 'render_block', array( $this, 'swap_navigation_block' ), 10, 2 );
		add_filter( 'render_block', array( $this, 'swap_gravity_form_block' ), 10, 2 );
		add_filter( 'body_class',   array( $this, 'add_body_language_class' ) );
	}

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function swap_navigation_block( $block_content, $block ) {
		if ( empty( $block['blockName'] ) || 'core/navigation' !== $block['blockName'] ) {
			return $block_content;
		}

		$ref     = isset( $block['attrs']['ref'] ) ? (int) $block['attrs']['ref'] : 0;
		$menus   = get_option( 'wpm_menus', array() );
		$lang    = WPM_Language_Manager::instance()->get_current();
		$menu_id = isset( $menus[ $lang ] ) ? (int) $menus[ $lang ] : 0;

		if ( ! $menu_id || ! $ref || (int) $ref === $menu_id ) {
			return $block_content;
		}

		$nav_post = get_post( $menu_id );
		if ( ! $nav_post ) {
			return $block_content;
		}

		$parsed = parse_blocks( $nav_post->post_content );
		$output = '';
		foreach ( $parsed as $inner ) {
			$output .= render_block( $inner );
		}
		return $output;
	}

	public function swap_gravity_form_block( $block_content, $block ) {
		if ( empty( $block['blockName'] ) || 'gravityforms/form' !== $block['blockName'] ) {
			return $block_content;
		}

		if ( ! class_exists( 'GFAPI' ) ) {
			return $block_content;
		}

		$forms   = get_option( 'wpm_forms', array() );
		$lang    = WPM_Language_Manager::instance()->get_current();
		$form_id = isset( $forms[ $lang ] ) ? (int) $forms[ $lang ] : 0;

		if ( ! $form_id ) {
			return $block_content;
		}

		$original_id = isset( $block['attrs']['formId'] ) ? (int) $block['attrs']['formId'] : 0;
		if ( $original_id === $form_id ) {
			return $block_content;
		}

		$form = GFAPI::get_form( $form_id );
		if ( ! $form ) {
			return $block_content;
		}

		ob_start();
		gravity_form( $form_id, true, true, false, null, true );
		return ob_get_clean();
	}

	public function add_body_language_class( $classes ) {
		$lang      = WPM_Language_Manager::instance()->get_current();
		$classes[] = 'lang-' . sanitize_html_class( $lang );
		return $classes;
	}
}
