<?php
/**
 * Language versions of template parts (header, footer…) for block themes.
 *
 * - Site Editor panel to assign a language and create a version.
 * - Front-end swap of core/template-part blocks by language.
 *
 * Map (option wpm_template_map, unchanged since 1.0):
 * group => lang => { id, slug }. The group is the slug of the part without
 * its language suffix (footer, footer-en → footer).
 *
 * @package PixelLang_Multilingual
 */

defined( 'ABSPATH' ) || exit;

class WPM_Template_Switcher {

	private static $instance   = null;
	private static $swapping   = array();
	private static $slug_index = null;

	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_panel' ) );
		add_filter( 'render_block_core/template-part', array( $this, 'swap_template_part_block' ), 10, 2 );
	}

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * @return array
	 */
	public static function get_map() {
		$map = get_option( 'wpm_template_map', array() );
		return is_array( $map ) ? $map : array();
	}

	private static function save_map( array $map ) {
		update_option( 'wpm_template_map', $map );
		self::$slug_index = null;
	}

	// ---------------------------------------------------------------------
	// REST
	// ---------------------------------------------------------------------

	public function register_rest_routes() {
		$can = function () {
			return current_user_can( 'edit_theme_options' );
		};
		$lang_arg = array(
			'required'          => true,
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => function ( $value ) {
				return WPM_Language_Manager::instance()->is_valid( sanitize_key( $value ) );
			},
		);
		$id_arg = array(
			'required'          => true,
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
		);
		$group_arg = array(
			'required'          => true,
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_key',
		);

		register_rest_route(
			'wpm/v1',
			'/template-language',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_save_language' ),
				'permission_callback' => $can,
				'args'                => array(
					'template_id' => $id_arg,
					'group'       => $group_arg,
					'lang'        => $lang_arg,
				),
			)
		);
		register_rest_route(
			'wpm/v1',
			'/template-repair',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_repair' ),
				'permission_callback' => $can,
			)
		);
		register_rest_route(
			'wpm/v1',
			'/template-group-remove',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_remove_group' ),
				'permission_callback' => $can,
				'args'                => array( 'group' => $group_arg ),
			)
		);
		register_rest_route(
			'wpm/v1',
			'/template-duplicate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_duplicate' ),
				'permission_callback' => $can,
				'args'                => array(
					'template_id' => $id_arg,
					'lang'        => $lang_arg,
					'group'       => $group_arg,
				),
			)
		);
	}

	/**
	 * Adds the missing wp_theme term to template parts of the active theme
	 * listed in the map, and to parts without any theme term. Parts that
	 * belong to another theme are never touched.
	 *
	 * @return WP_REST_Response
	 */
	public function rest_repair() {
		$theme = get_stylesheet();
		$fixed = array();
		$ids   = get_posts(
			array(
				'post_type'        => 'wp_template_part',
				'post_status'      => 'any',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
				'no_found_rows'    => true,
			)
		);
		foreach ( $ids as $pid ) {
			$terms = wp_get_object_terms( $pid, 'wp_theme', array( 'fields' => 'slugs' ) );
			if ( ! is_wp_error( $terms ) && empty( $terms ) ) {
				wp_set_object_terms( $pid, array( $theme ), 'wp_theme' );
				$fixed[] = get_post_field( 'post_name', $pid );
			}
		}
		return rest_ensure_response(
			array(
				'success' => true,
				'fixed'   => $fixed,
			)
		);
	}

	public function rest_remove_group( $request ) {
		$group = $request->get_param( 'group' );
		$map   = self::get_map();
		if ( ! isset( $map[ $group ] ) ) {
			return new WP_Error( 'wpm_unknown_group', __( 'Unknown template group.', 'pixellang-multilingual' ), array( 'status' => 404 ) );
		}
		unset( $map[ $group ] );
		self::save_map( $map );
		return rest_ensure_response( array( 'success' => true ) );
	}

	public function rest_save_language( $request ) {
		$template_id = (int) $request->get_param( 'template_id' );
		$group       = $request->get_param( 'group' );
		$lang        = $request->get_param( 'lang' );

		$post = get_post( $template_id );
		if ( ! $post || 'wp_template_part' !== $post->post_type ) {
			return new WP_Error( 'wpm_invalid_template', __( 'Invalid template part.', 'pixellang-multilingual' ), array( 'status' => 400 ) );
		}

		$map = self::get_map();
		foreach ( $map as $g => $langs ) {
			foreach ( (array) $langs as $l => $info ) {
				if ( isset( $info['id'] ) && (int) $info['id'] === $template_id ) {
					unset( $map[ $g ][ $l ] );
				}
			}
			if ( empty( $map[ $g ] ) ) {
				unset( $map[ $g ] );
			}
		}
		$map[ $group ][ $lang ] = array(
			'id'   => $template_id,
			'slug' => $post->post_name,
		);
		self::save_map( $map );

		return rest_ensure_response(
			array(
				'success' => true,
				'group'   => $map[ $group ],
			)
		);
	}

	public function rest_duplicate( $request ) {
		$template_id = (int) $request->get_param( 'template_id' );
		$lang        = $request->get_param( 'lang' );
		$group       = $request->get_param( 'group' );

		$source = get_post( $template_id );
		if ( ! $source || 'wp_template_part' !== $source->post_type ) {
			return new WP_Error( 'wpm_invalid_template', __( 'Invalid template part.', 'pixellang-multilingual' ), array( 'status' => 400 ) );
		}

		$map = self::get_map();
		if ( ! empty( $map[ $group ][ $lang ]['id'] ) ) {
			$existing = get_post( $map[ $group ][ $lang ]['id'] );
			if ( $existing ) {
				return rest_ensure_response(
					array(
						'success'     => true,
						'template_id' => $existing->ID,
						'slug'        => $existing->post_name,
						'edit_url'    => self::get_edit_url( $existing->post_name ),
						'existing'    => true,
					)
				);
			}
		}

		$new_slug = $source->post_name . '-' . $lang;
		$taken    = get_posts(
			array(
				'post_type'        => 'wp_template_part',
				'name'             => $new_slug,
				'post_status'      => 'any',
				'numberposts'      => 1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);

		if ( $taken ) {
			$new_post = get_post( $taken[0] );
			$themes   = wp_get_object_terms( $new_post->ID, 'wp_theme', array( 'fields' => 'slugs' ) );
			if ( ! is_wp_error( $themes ) && empty( $themes ) ) {
				wp_set_object_terms( $new_post->ID, array( get_stylesheet() ), 'wp_theme' );
			}
		} else {
			$new_id = wp_insert_post(
				wp_slash(
					array(
						'post_type'    => 'wp_template_part',
						'post_name'    => $new_slug,
						/* translators: 1: template part title, 2: language code. */
						'post_title'   => sprintf( _x( '%1$s (%2$s)', 'template part title with language', 'pixellang-multilingual' ), $source->post_title, strtoupper( $lang ) ),
						'post_content' => $source->post_content,
						'post_status'  => 'publish',
						'post_author'  => get_current_user_id(),
					)
				),
				true
			);
			if ( is_wp_error( $new_id ) ) {
				return $new_id;
			}
			$area = wp_get_object_terms( $source->ID, 'wp_template_part_area', array( 'fields' => 'slugs' ) );
			if ( ! is_wp_error( $area ) && $area ) {
				wp_set_object_terms( $new_id, $area, 'wp_template_part_area' );
			}
			// The Site Editor only lists parts that carry the theme term.
			$theme_terms = wp_get_object_terms( $source->ID, 'wp_theme', array( 'fields' => 'slugs' ) );
			wp_set_object_terms( $new_id, ( ! is_wp_error( $theme_terms ) && $theme_terms ) ? $theme_terms : array( get_stylesheet() ), 'wp_theme' );
			$new_post = get_post( $new_id );
		}

		$map[ $group ][ $lang ] = array(
			'id'   => $new_post->ID,
			'slug' => $new_post->post_name,
		);
		self::save_map( $map );

		return rest_ensure_response(
			array(
				'success'     => true,
				'template_id' => $new_post->ID,
				'slug'        => $new_post->post_name,
				'edit_url'    => self::get_edit_url( $new_post->post_name ),
				'existing'    => ! empty( $taken ),
			)
		);
	}

	/**
	 * Site Editor URL of a template part of the active theme.
	 *
	 * @param string $slug Template part slug.
	 * @return string
	 */
	public static function get_edit_url( $slug ) {
		$path = '/wp_template_part/' . get_stylesheet() . '//' . $slug;
		return admin_url( 'site-editor.php?p=' . rawurlencode( $path ) . '&canvas=edit' );
	}

	// ---------------------------------------------------------------------
	// Editor panel
	// ---------------------------------------------------------------------

	public function enqueue_panel() {
		$screen = get_current_screen();
		if ( ! $screen || ! $screen->is_block_editor() || ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}
		// Template parts are only edited in the Site Editor.
		if ( 'site-editor' !== $screen->base && 'site-editor' !== $screen->id ) {
			return;
		}

		$manager   = WPM_Language_Manager::instance();
		$map       = self::get_map();
		$lang_data = array();
		foreach ( $manager->get_all() as $slug => $cfg ) {
			$lang_data[ $slug ] = array( 'label' => $cfg['label'] );
		}

		$assignments  = array();
		$map_verified = array();
		foreach ( $map as $group => $langs ) {
			foreach ( (array) $langs as $lang => $info ) {
				if ( ! empty( $info['id'] ) && get_post( $info['id'] ) ) {
					$map_verified[ $group ][ $lang ] = $info;
					$assignments[ $info['id'] ]      = array(
						'group' => $group,
						'lang'  => $lang,
					);
				}
			}
		}

		wp_enqueue_script(
			'wpm-template-panel',
			WPM_URL . 'admin/js/template-panel.js',
			array( 'wp-plugins', 'wp-editor', 'wp-element', 'wp-data', 'wp-components', 'wp-api-fetch', 'wp-i18n' ),
			WPM_VERSION,
			true
		);
		wp_set_script_translations( 'wpm-template-panel', 'pixellang-multilingual', WPM_DIR . 'languages' );
		wp_localize_script(
			'wpm-template-panel',
			'wpmTemplateData',
			array(
				'languages'    => $lang_data,
				'template_map' => (object) $map_verified,
				'assignments'  => (object) $assignments,
				'theme'        => get_stylesheet(),
				'editBase'     => admin_url( 'site-editor.php' ),
			)
		);
	}

	// ---------------------------------------------------------------------
	// Front end
	// ---------------------------------------------------------------------

	/**
	 * Renders the version of a template part for the current language.
	 *
	 * @param string $block_content Rendered block.
	 * @param array  $block         Parsed block.
	 * @return string
	 */
	public function swap_template_part_block( $block_content, $block ) {
		if ( is_admin() ) {
			return $block_content;
		}
		$slug = isset( $block['attrs']['slug'] ) ? $block['attrs']['slug'] : '';
		if ( ! $slug || isset( self::$swapping[ $slug ] ) ) {
			return $block_content;
		}
		$index = $this->get_slug_index();
		if ( ! isset( $index[ $slug ] ) ) {
			return $block_content;
		}
		$lang  = WPM_Language_Manager::instance()->get_current();
		$langs = $index[ $slug ]['langs'];
		if ( empty( $langs[ $lang ]['slug'] ) || $langs[ $lang ]['slug'] === $slug ) {
			return $block_content;
		}

		self::$swapping[ $slug ] = true;
		$result                  = render_block(
			array(
				'blockName'    => 'core/template-part',
				'attrs'        => array_merge( $block['attrs'], array( 'slug' => $langs[ $lang ]['slug'] ) ),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);
		unset( self::$swapping[ $slug ] );
		return $result;
	}

	/**
	 * slug => { group, langs }, built once per request.
	 *
	 * @return array
	 */
	private function get_slug_index() {
		if ( null !== self::$slug_index ) {
			return self::$slug_index;
		}
		$index = array();
		foreach ( self::get_map() as $group => $langs ) {
			foreach ( (array) $langs as $info ) {
				if ( ! empty( $info['slug'] ) ) {
					$index[ $info['slug'] ] = array(
						'group' => $group,
						'langs' => $langs,
					);
				}
			}
		}
		self::$slug_index = $index;
		return $index;
	}
}
