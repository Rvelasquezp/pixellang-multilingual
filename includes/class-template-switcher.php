<?php
defined( 'ABSPATH' ) || exit;

/**
 * Handles template part translation in the Site Editor.
 * - REST endpoints to save language assignments and duplicate template parts.
 * - Gutenberg panel JS enqueue for wp_template_part editor.
 * - Frontend swap of core/template-part blocks by language.
 */
class WPM_Template_Switcher {

	private static $instance  = null;
	private static $swapping  = array();
	private static $slug_index = null;

	private function __construct() {
		add_action( 'rest_api_init',            array( $this, 'register_rest_routes' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_panel' ) );
		add_filter( 'render_block',             array( $this, 'swap_template_part_block' ), 10, 2 );
	}

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	// -------------------------------------------------------------------------
	// REST routes
	// -------------------------------------------------------------------------

	public function register_rest_routes() {
		register_rest_route( 'wpm/v1', '/template-language', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'rest_save_language' ),
			'permission_callback' => function() {
				return current_user_can( 'edit_theme_options' );
			},
			'args' => array(
				'template_id' => array( 'required' => true, 'sanitize_callback' => 'absint' ),
				'group'       => array( 'required' => true, 'sanitize_callback' => 'sanitize_key' ),
				'lang'        => array( 'required' => true, 'sanitize_callback' => 'sanitize_key' ),
			),
		) );

		register_rest_route( 'wpm/v1', '/template-repair', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'rest_repair' ),
			'permission_callback' => function() {
				return current_user_can( 'edit_theme_options' );
			},
		) );

		register_rest_route( 'wpm/v1', '/template-group-remove', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'rest_remove_group' ),
			'permission_callback' => function() {
				return current_user_can( 'edit_theme_options' );
			},
			'args' => array(
				'group' => array( 'required' => true, 'sanitize_callback' => 'sanitize_key' ),
			),
		) );

		register_rest_route( 'wpm/v1', '/template-duplicate', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'rest_duplicate' ),
			'permission_callback' => function() {
				return current_user_can( 'edit_theme_options' );
			},
			'args' => array(
				'template_id' => array( 'required' => true, 'sanitize_callback' => 'absint' ),
				'lang'        => array( 'required' => true, 'sanitize_callback' => 'sanitize_key' ),
				'group'       => array( 'required' => true, 'sanitize_callback' => 'sanitize_key' ),
			),
		) );
	}

	public function rest_repair( $request ) {
		$theme  = get_stylesheet();
		$map    = get_option( 'wpm_template_map', array() );
		$fixed  = array();

		foreach ( $map as $group => $langs ) {
			foreach ( $langs as $lang => $info ) {
				if ( empty( $info['id'] ) ) continue;
				$post = get_post( $info['id'] );
				if ( ! $post ) continue;

				$terms = wp_get_object_terms( $post->ID, 'wp_theme', array( 'fields' => 'slugs' ) );
				if ( is_wp_error( $terms ) || empty( $terms ) ) {
					wp_set_object_terms( $post->ID, array( $theme ), 'wp_theme' );
					$fixed[] = $post->post_name;
				}
			}
		}

		// Also fix all wp_template_part posts that belong to this theme but have no wp_theme term.
		$all_parts = get_posts( array(
			'post_type'      => 'wp_template_part',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) );
		foreach ( $all_parts as $pid ) {
			$terms = wp_get_object_terms( $pid, 'wp_theme', array( 'fields' => 'slugs' ) );
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				wp_set_object_terms( $pid, array( $theme ), 'wp_theme' );
				$post = get_post( $pid );
				$fixed[] = $post->post_name . ' (auto)';
			}
		}

		return rest_ensure_response( array( 'success' => true, 'fixed' => $fixed ) );
	}

	public function rest_remove_group( $request ) {
		$group = $request->get_param( 'group' );
		$map   = get_option( 'wpm_template_map', array() );
		unset( $map[ $group ] );
		update_option( 'wpm_template_map', $map );
		self::$slug_index = null;
		return rest_ensure_response( array( 'success' => true ) );
	}

	public function rest_save_language( $request ) {
		$template_id = $request->get_param( 'template_id' );
		$group       = $request->get_param( 'group' );
		$lang        = $request->get_param( 'lang' );

		if ( ! WPM_Language_Manager::instance()->is_valid( $lang ) ) {
			return new WP_Error( 'invalid_lang', 'Invalid language.', array( 'status' => 400 ) );
		}

		$post = get_post( $template_id );
		if ( ! $post || 'wp_template_part' !== $post->post_type ) {
			return new WP_Error( 'invalid_template', 'Invalid template part.', array( 'status' => 400 ) );
		}

		$map = get_option( 'wpm_template_map', array() );

		// Remove this template_id from any existing assignment.
		foreach ( $map as $g => $langs ) {
			foreach ( $langs as $l => $info ) {
				if ( isset( $info['id'] ) && (int) $info['id'] === $template_id ) {
					unset( $map[ $g ][ $l ] );
				}
			}
			if ( empty( $map[ $g ] ) ) {
				unset( $map[ $g ] );
			}
		}

		if ( ! isset( $map[ $group ] ) ) {
			$map[ $group ] = array();
		}
		$map[ $group ][ $lang ] = array(
			'id'   => $template_id,
			'slug' => $post->post_name,
		);

		update_option( 'wpm_template_map', $map );
		self::$slug_index = null;

		return rest_ensure_response( array(
			'success' => true,
			'group'   => $map[ $group ],
		) );
	}

	public function rest_duplicate( $request ) {
		$template_id = $request->get_param( 'template_id' );
		$lang        = $request->get_param( 'lang' );
		$group       = $request->get_param( 'group' );

		if ( ! WPM_Language_Manager::instance()->is_valid( $lang ) ) {
			return new WP_Error( 'invalid_lang', 'Invalid language.', array( 'status' => 400 ) );
		}

		$source = get_post( $template_id );
		if ( ! $source || 'wp_template_part' !== $source->post_type ) {
			return new WP_Error( 'invalid_template', 'Invalid template part.', array( 'status' => 400 ) );
		}

		$map = get_option( 'wpm_template_map', array() );

		// Return existing version if already created.
		if ( ! empty( $map[ $group ][ $lang ]['id'] ) ) {
			$existing = get_post( $map[ $group ][ $lang ]['id'] );
			if ( $existing ) {
				return rest_ensure_response( array(
					'success'     => true,
					'template_id' => $existing->ID,
					'slug'        => $existing->post_name,
					'edit_url'    => $this->get_edit_url( $existing->post_name ),
					'existing'    => true,
				) );
			}
		}

		// Build a unique slug.
		$new_slug = $source->post_name . '-' . $lang;
		$taken    = get_posts( array(
			'post_type'      => 'wp_template_part',
			'name'           => $new_slug,
			'post_status'    => 'any',
			'numberposts'    => 1,
			'fields'         => 'ids',
		) );

		if ( $taken ) {
			$new_post = get_post( $taken[0] );
			// Ensure wp_theme taxonomy is set on existing posts (may be missing from older tests).
			$existing_themes = wp_get_object_terms( $new_post->ID, 'wp_theme', array( 'fields' => 'slugs' ) );
			if ( is_wp_error( $existing_themes ) || empty( $existing_themes ) ) {
				wp_set_object_terms( $new_post->ID, array( get_stylesheet() ), 'wp_theme' );
			}
		} else {
			$new_id = wp_insert_post( array(
				'post_type'    => 'wp_template_part',
				'post_name'    => $new_slug,
				'post_title'   => $source->post_title . ' (' . strtoupper( $lang ) . ')',
				'post_content' => $source->post_content,
				'post_status'  => 'publish',
				'post_author'  => get_current_user_id(),
			) );

			if ( is_wp_error( $new_id ) ) {
				return $new_id;
			}

			// Copy template part area taxonomy (header/footer/uncategorized).
			$area_terms = wp_get_object_terms( $source->ID, 'wp_template_part_area', array( 'fields' => 'slugs' ) );
			if ( ! is_wp_error( $area_terms ) && ! empty( $area_terms ) ) {
				wp_set_object_terms( $new_id, $area_terms, 'wp_template_part_area' );
			}

			// Copy wp_theme taxonomy — required for the Site Editor to recognize the part.
			$theme_terms = wp_get_object_terms( $source->ID, 'wp_theme', array( 'fields' => 'slugs' ) );
			if ( ! is_wp_error( $theme_terms ) && ! empty( $theme_terms ) ) {
				wp_set_object_terms( $new_id, $theme_terms, 'wp_theme' );
			} else {
				wp_set_object_terms( $new_id, array( get_stylesheet() ), 'wp_theme' );
			}

			$new_post = get_post( $new_id );
		}

		// Save to map.
		if ( ! isset( $map[ $group ] ) ) {
			$map[ $group ] = array();
		}
		$map[ $group ][ $lang ] = array(
			'id'   => $new_post->ID,
			'slug' => $new_post->post_name,
		);
		update_option( 'wpm_template_map', $map );
		self::$slug_index = null;

		return rest_ensure_response( array(
			'success'     => true,
			'template_id' => $new_post->ID,
			'slug'        => $new_post->post_name,
			'edit_url'    => $this->get_edit_url( $new_post->post_name ),
			'existing'    => ! empty( $taken ),
		) );
	}

	private function get_edit_url( $slug ) {
		$theme = get_stylesheet();
		$path  = '/wp_template_part/' . $theme . '//' . $slug;
		return admin_url( 'site-editor.php?p=' . rawurlencode( $path ) . '&canvas=edit' );
	}

	// -------------------------------------------------------------------------
	// Enqueue panel JS in block editor
	// -------------------------------------------------------------------------

	public function enqueue_panel() {
		$screen = get_current_screen();
		if ( ! $screen || ! $screen->is_block_editor() ) {
			return;
		}

		$manager   = WPM_Language_Manager::instance();
		$languages = $manager->get_all();
		$map       = get_option( 'wpm_template_map', array() );
		$all_data  = wpm_get_available_languages();

		// Build per-language display data.
		$lang_data = array();
		foreach ( $languages as $slug => $cfg ) {
			$lang_data[ $slug ] = array(
				'label' => $cfg['label'],
				'flag'  => isset( $all_data[ $slug ]['flag'] ) ? $all_data[ $slug ]['flag'] : '🌐',
			);
		}

		// Build reverse lookup: template_id -> { group, lang }.
		$assignments = array();
		foreach ( $map as $group => $langs ) {
			foreach ( $langs as $lang => $info ) {
				if ( ! empty( $info['id'] ) ) {
					$assignments[ $info['id'] ] = array(
						'group' => $group,
						'lang'  => $lang,
					);
				}
			}
		}

		// Current template part ID (if editing one in Site Editor).
		$current_id = 0;
		if ( isset( $GLOBALS['post'] ) && $GLOBALS['post'] ) {
			if ( 'wp_template_part' === $GLOBALS['post']->post_type ) {
				$current_id = (int) $GLOBALS['post']->ID;
			}
		} elseif ( isset( $_GET['postId'] ) ) {
			// Site Editor passes postId as "theme//slug" — resolve to DB post.
			$raw   = urldecode( sanitize_text_field( wp_unslash( $_GET['postId'] ) ) );
			$parts = explode( '//', $raw );
			$slug  = isset( $parts[1] ) ? sanitize_key( $parts[1] ) : '';
			if ( $slug ) {
				$found = get_posts( array(
					'post_type'   => 'wp_template_part',
					'name'        => $slug,
					'post_status' => 'any',
					'numberposts' => 1,
					'fields'      => 'ids',
				) );
				$current_id = $found ? (int) $found[0] : 0;
			}
		}

		wp_enqueue_script(
			'wpm-template-panel',
			WPM_URL . 'admin/js/template-panel.js',
			array( 'wp-plugins', 'wp-editor', 'wp-element', 'wp-data', 'wp-components', 'wp-api-fetch' ),
			WPM_VERSION,
			true
		);

		// Verify which template parts in the map actually exist in DB.
		$map_verified = array();
		foreach ( $map as $grp => $langs ) {
			$map_verified[ $grp ] = array();
			foreach ( $langs as $l => $info ) {
				if ( ! empty( $info['id'] ) && get_post( $info['id'] ) ) {
					$map_verified[ $grp ][ $l ] = $info;
				}
			}
		}

		wp_localize_script( 'wpm-template-panel', 'wpmTemplateData', array(
			'languages'    => $lang_data,
			'template_map' => $map_verified,
			'assignments'  => $assignments,
			'current_id'   => $current_id,
			'theme'        => get_stylesheet(),
		) );
	}

	// -------------------------------------------------------------------------
	// Frontend swap
	// -------------------------------------------------------------------------

	public function swap_template_part_block( $block_content, $block ) {
		if ( empty( $block['blockName'] ) || 'core/template-part' !== $block['blockName'] ) {
			return $block_content;
		}

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

		$lang        = WPM_Language_Manager::instance()->get_current();
		$group_langs = $index[ $slug ]['langs'];

		if ( ! isset( $group_langs[ $lang ] ) ) {
			return $block_content;
		}

		$target_slug = $group_langs[ $lang ]['slug'];
		if ( $target_slug === $slug ) {
			return $block_content;
		}

		self::$swapping[ $slug ] = true;
		$swapped = array(
			'blockName'    => 'core/template-part',
			'attrs'        => array_merge( $block['attrs'], array( 'slug' => $target_slug ) ),
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		);
		$result = render_block( $swapped );
		unset( self::$swapping[ $slug ] );

		return $result;
	}

	/**
	 * Builds a reverse index: slug -> { group, langs } — computed once per request.
	 */
	private function get_slug_index() {
		if ( null !== self::$slug_index ) {
			return self::$slug_index;
		}

		$map   = get_option( 'wpm_template_map', array() );
		$index = array();

		foreach ( $map as $group => $langs ) {
			foreach ( $langs as $lang => $info ) {
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
