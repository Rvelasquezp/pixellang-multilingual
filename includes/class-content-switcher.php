<?php
/**
 * Swaps shared site elements for their version in the current language:
 * navigation blocks, classic menu locations, template parts (see
 * WPM_Template_Switcher) and forms (Contact Form 7, Gravity Forms).
 *
 * @package PixelLang_Multilingual
 */

defined( 'ABSPATH' ) || exit;

class WPM_Content_Switcher {

	private static $instance = null;

	private function __construct() {
		add_filter( 'render_block_core/navigation', array( $this, 'swap_navigation_block' ), 10, 2 );
		add_filter( 'render_block_gravityforms/form', array( $this, 'swap_gravity_form_block' ), 10, 2 );
		add_filter( 'shortcode_atts_wpcf7', array( $this, 'swap_cf7_form' ), 10, 1 );
		add_filter( 'shortcode_atts_gravityforms', array( $this, 'swap_gravity_form_shortcode' ), 10, 1 );
		add_filter( 'body_class', array( $this, 'add_body_language_class' ) );
		if ( ! is_admin() ) {
			add_filter( 'theme_mod_nav_menu_locations', array( $this, 'swap_menu_locations' ) );
		}
	}

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Navigation block: render the menu assigned to the current language.
	 *
	 * @param string $block_content Rendered block.
	 * @param array  $block         Parsed block.
	 * @return string
	 */
	public function swap_navigation_block( $block_content, $block ) {
		$ref = isset( $block['attrs']['ref'] ) ? (int) $block['attrs']['ref'] : 0;
		if ( ! $ref ) {
			return $block_content;
		}
		$menus = get_option( 'wpm_menus', array() );
		$menus = is_array( $menus ) ? array_map( 'intval', $menus ) : array();

		// Only swap navigations that are assigned to a language.
		if ( ! in_array( $ref, $menus, true ) ) {
			return $block_content;
		}
		$lang    = WPM_Language_Manager::instance()->get_current();
		$menu_id = isset( $menus[ $lang ] ) ? $menus[ $lang ] : 0;
		if ( ! $menu_id || $ref === $menu_id || 'wp_navigation' !== get_post_type( $menu_id ) ) {
			return $block_content;
		}

		// Same attributes (className, overlayMenu…), other menu.
		return render_block(
			array(
				'blockName'    => 'core/navigation',
				'attrs'        => array_merge( $block['attrs'], array( 'ref' => $menu_id ) ),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);
	}

	/**
	 * Classic themes: menu assigned to each location for the current language.
	 *
	 * @param array|false $locations location => menu term ID.
	 * @return array|false
	 */
	public function swap_menu_locations( $locations ) {
		$map  = WPM_Settings::get( 'menu_locations' );
		$lang = WPM_Language_Manager::instance()->get_current();
		if ( empty( $map[ $lang ] ) ) {
			return $locations;
		}
		return array_merge( is_array( $locations ) ? $locations : array(), $map[ $lang ] );
	}

	/**
	 * Form translated into the current language, if the form belongs to a group.
	 *
	 * @param string $provider 'cf7' or 'gf'.
	 * @param int    $form_id  Form ID.
	 * @return int Form to display (the same one when there is no translation).
	 */
	public function get_form_translation( $provider, $form_id ) {
		$lang = WPM_Language_Manager::instance()->get_current();
		foreach ( (array) WPM_Settings::get( 'form_groups' ) as $group ) {
			if ( $provider !== $group['provider'] || ! in_array( (int) $form_id, array_map( 'intval', $group['forms'] ), true ) ) {
				continue;
			}
			return isset( $group['forms'][ $lang ] ) ? (int) $group['forms'][ $lang ] : (int) $form_id;
		}
		return (int) $form_id;
	}

	/**
	 * Contact Form 7 (block and shortcode both go through shortcode_atts).
	 *
	 * @param array $atts Shortcode attributes.
	 * @return array
	 */
	public function swap_cf7_form( $atts ) {
		if ( is_admin() || empty( $atts['id'] ) ) {
			return $atts;
		}
		$id = trim( (string) $atts['id'] );
		if ( ! ctype_digit( $id ) && function_exists( 'wpcf7_get_contact_form_by_hash' ) ) {
			$form = wpcf7_get_contact_form_by_hash( $id );
			$id   = $form ? (string) $form->id() : '';
		}
		if ( ! $id ) {
			return $atts;
		}
		$target = $this->get_form_translation( 'cf7', (int) $id );
		if ( $target !== (int) $id ) {
			$atts['id']    = (string) $target;
			$atts['title'] = '';
		}
		return $atts;
	}

	/**
	 * Gravity Forms block.
	 *
	 * @param string $block_content Rendered block.
	 * @param array  $block         Parsed block.
	 * @return string
	 */
	public function swap_gravity_form_block( $block_content, $block ) {
		if ( ! function_exists( 'gravity_form' ) || ! class_exists( 'GFAPI' ) ) {
			return $block_content;
		}
		$form_id = isset( $block['attrs']['formId'] ) ? (int) $block['attrs']['formId'] : 0;
		$target  = $form_id ? $this->get_form_translation( 'gf', $form_id ) : 0;
		if ( ! $target || $target === $form_id || ! GFAPI::get_form( $target ) ) {
			return $block_content;
		}
		$attrs = $block['attrs'];
		return gravity_form(
			$target,
			! isset( $attrs['title'] ) || (bool) $attrs['title'],
			! isset( $attrs['description'] ) || (bool) $attrs['description'],
			false,
			null,
			! empty( $attrs['ajax'] ),
			0,
			false
		);
	}

	/**
	 * Gravity Forms shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return array
	 */
	public function swap_gravity_form_shortcode( $atts ) {
		if ( ! empty( $atts['id'] ) && ! is_admin() ) {
			$atts['id'] = $this->get_form_translation( 'gf', (int) $atts['id'] );
		}
		return $atts;
	}

	public function add_body_language_class( $classes ) {
		$lang      = WPM_Language_Manager::instance()->get_current();
		$classes[] = 'lang-' . sanitize_html_class( $lang );
		return $classes;
	}
}
