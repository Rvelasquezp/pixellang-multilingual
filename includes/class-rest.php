<?php
/**
 * REST routes used by the editor panels (namespace wpm/v1).
 *
 * @package PixelLang_Multilingual
 */

defined( 'ABSPATH' ) || exit;

class WPM_Rest {

	private static $instance = null;

	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register_routes() {
		register_rest_route(
			'wpm/v1',
			'/nav-language',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'save_nav_language' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_theme_options' );
				},
				'args'                => array(
					'nav_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'lang'   => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);

		register_rest_route(
			'wpm/v1',
			'/link',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'link_post' ),
				'permission_callback' => function ( $request ) {
					return current_user_can( 'edit_post', absint( $request->get_param( 'post_id' ) ) );
				},
				'args'                => array(
					'post_id'     => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'source_lang' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
					'lang'        => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
					'target'      => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			'wpm/v1',
			'/post-language',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'save_post_language' ),
				'permission_callback' => function ( $request ) {
					return current_user_can( 'edit_post', absint( $request->get_param( 'post_id' ) ) );
				},
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'lang'    => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);

		register_rest_route(
			'wpm/v1',
			'/search',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'search_posts' ),
				'permission_callback' => array( $this, 'can_search' ),
				'args'                => array(
					'post_type' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
					'lang'      => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
					'search'    => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'exclude'   => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Assigns a navigation menu (wp_navigation) to a language; '' unassigns it.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_nav_language( $request ) {
		$nav_id = (int) $request->get_param( 'nav_id' );
		$lang   = (string) $request->get_param( 'lang' );

		if ( 'wp_navigation' !== get_post_type( $nav_id ) ) {
			return new WP_Error( 'wpm_invalid_nav', __( 'This is not a navigation menu.', 'pixellang-multilingual' ), array( 'status' => 400 ) );
		}
		if ( $lang && ! WPM_Language_Manager::instance()->is_valid( $lang ) ) {
			return new WP_Error( 'wpm_invalid_lang', __( 'Unknown language.', 'pixellang-multilingual' ), array( 'status' => 400 ) );
		}
		$menus = self::assign_nav_language( $nav_id, $lang );
		return rest_ensure_response(
			array(
				'success' => true,
				'nav_id'  => $nav_id,
				'lang'    => $lang,
				'menus'   => $menus,
			)
		);
	}

	/**
	 * Updates wpm_menus: one navigation per language, one language per navigation.
	 *
	 * @param int    $nav_id Navigation post ID.
	 * @param string $lang   Language code or ''.
	 * @return array
	 */
	public static function assign_nav_language( $nav_id, $lang ) {
		$menus = get_option( 'wpm_menus', array() );
		$menus = is_array( $menus ) ? $menus : array();
		foreach ( $menus as $slug => $id ) {
			if ( (int) $id === (int) $nav_id || ! (int) $id ) {
				unset( $menus[ $slug ] );
			}
		}
		if ( $lang ) {
			$menus[ $lang ] = (int) $nav_id;
		}
		update_option( 'wpm_menus', $menus );
		return $menus;
	}

	/**
	 * Links (target > 0) or unlinks (target = 0) one translation of a post.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function link_post( $request ) {
		$post = get_post( (int) $request->get_param( 'post_id' ) );
		if ( ! $post || ! WPM_Settings::is_translatable_post_type( $post->post_type ) ) {
			return new WP_Error( 'wpm_invalid', __( 'This content cannot be translated.', 'pixellang-multilingual' ), array( 'status' => 400 ) );
		}
		$source_lang = (string) $request->get_param( 'source_lang' );
		$lang        = (string) $request->get_param( 'lang' );
		$manager     = WPM_Language_Manager::instance();
		if ( ! $manager->is_valid( $source_lang ) || ! $manager->is_valid( $lang ) || $lang === $source_lang ) {
			return new WP_Error( 'wpm_invalid_lang', __( 'Unknown language.', 'pixellang-multilingual' ), array( 'status' => 400 ) );
		}
		$requested = WPM_Translations::get_post_translations( $post->ID );
		unset( $requested[ WPM_Translations::get_post_language( $post->ID ) ] );
		$requested[ $lang ] = (int) $request->get_param( 'target' );

		$result = self::update_post_group( $post, $source_lang, $requested );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$out = array();
		foreach ( $result as $l => $id ) {
			$out[ $l ] = array(
				'id'     => $id,
				'title'  => html_entity_decode( get_the_title( $id ), ENT_QUOTES, get_bloginfo( 'charset' ) ),
				'status' => get_post_status( $id ),
				'edit'   => get_edit_post_link( $id, 'raw' ),
			);
		}
		return rest_ensure_response( array( 'group' => $out ) );
	}

	/**
	 * Changes the language of a post. When another member of its group
	 * already uses that language, the post leaves the group.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_post_language( $request ) {
		$post = get_post( (int) $request->get_param( 'post_id' ) );
		$lang = (string) $request->get_param( 'lang' );
		if ( ! $post || ! WPM_Settings::is_translatable_post_type( $post->post_type ) || ! WPM_Language_Manager::instance()->is_valid( $lang ) ) {
			return new WP_Error( 'wpm_invalid', __( 'Invalid request.', 'pixellang-multilingual' ), array( 'status' => 400 ) );
		}
		WPM_Translations::set_language( 'post', $post->ID, $lang );
		$out = array();
		foreach ( WPM_Translations::get_post_translations( $post->ID ) as $l => $id ) {
			$out[ $l ] = array(
				'id'     => $id,
				'title'  => html_entity_decode( get_the_title( $id ), ENT_QUOTES, get_bloginfo( 'charset' ) ),
				'status' => get_post_status( $id ),
				'edit'   => get_edit_post_link( $id, 'raw' ),
			);
		}
		return rest_ensure_response(
			array(
				'lang'  => $lang,
				'group' => $out,
			)
		);
	}

	/**
	 * Sets the language of a post and its translations in one go.
	 *
	 * Each requested translation must be of the same post type, editable by
	 * the current user and either without language or already in the slot's
	 * language. Invalid entries return an error instead of being dropped
	 * silently, so the editor can tell the user.
	 *
	 * @param WP_Post $post      Post.
	 * @param string  $lang      Language of the post.
	 * @param array   $requested lang => post ID (0 = no translation).
	 * @return array|WP_Error The stored group.
	 */
	public static function update_post_group( WP_Post $post, $lang, array $requested ) {
		$manager = WPM_Language_Manager::instance();
		$group   = array( $lang => $post->ID );
		foreach ( $requested as $t_lang => $t_id ) {
			$t_lang = sanitize_key( $t_lang );
			$t_id   = absint( $t_id );
			if ( ! $t_id || $t_lang === $lang || ! $manager->is_valid( $t_lang ) ) {
				continue;
			}
			$target = get_post( $t_id );
			if ( ! $target || $t_id === $post->ID || $target->post_type !== $post->post_type ) {
				return new WP_Error( 'wpm_invalid_target', __( 'Only content of the same type can be linked.', 'pixellang-multilingual' ), array( 'status' => 400 ) );
			}
			if ( ! current_user_can( 'edit_post', $t_id ) ) {
				return new WP_Error( 'wpm_forbidden_target', __( 'You are not allowed to edit this content.', 'pixellang-multilingual' ), array( 'status' => 403 ) );
			}
			$target_lang = WPM_Translations::get_post_language( $t_id );
			if ( $target_lang && $target_lang !== $t_lang ) {
				return new WP_Error(
					'wpm_language_mismatch',
					/* translators: %s: language name. */
					sprintf( __( 'This content is already in %s.', 'pixellang-multilingual' ), $manager->get_label( $target_lang ) ),
					array( 'status' => 400 )
				);
			}
			$group[ $t_lang ] = $t_id;
		}

		WPM_Translations::set_language( 'post', $post->ID, $lang );
		$current = WPM_Translations::get_post_translations( $post->ID );
		ksort( $current );
		ksort( $group );
		if ( $current !== $group ) {
			WPM_Translations::unlink( 'post', $post->ID );
			if ( count( $group ) > 1 ) {
				WPM_Translations::link( 'post', $group );
			}
		}
		return WPM_Translations::get_post_translations( $post->ID );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function can_search( $request ) {
		$type = get_post_type_object( sanitize_key( $request->get_param( 'post_type' ) ) );
		return $type && current_user_can( $type->cap->edit_posts );
	}

	/**
	 * Candidates to link as a translation: same post type, in the requested
	 * language or without language.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function search_posts( $request ) {
		$post_type = $request->get_param( 'post_type' );
		$lang      = $request->get_param( 'lang' );
		if ( ! WPM_Settings::is_translatable_post_type( $post_type ) || ! WPM_Language_Manager::instance()->is_valid( $lang ) ) {
			return new WP_Error( 'wpm_invalid', __( 'Invalid request.', 'pixellang-multilingual' ), array( 'status' => 400 ) );
		}
		$query = new WP_Query(
			array(
				'post_type'              => $post_type,
				'post_status'            => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				's'                      => $request->get_param( 'search' ),
				'post__not_in'           => array_filter( array( (int) $request->get_param( 'exclude' ) ) ),
				'posts_per_page'         => 20,
				'orderby'                => $request->get_param( 'search' ) ? 'relevance' : 'modified',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'meta_query'             => array( WPM_Query_Filter::language_clause( $lang ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			)
		);
		$items = array();
		foreach ( $query->posts as $post ) {
			if ( ! current_user_can( 'edit_post', $post->ID ) ) {
				continue;
			}
			$group   = WPM_Translations::get_post_translations( $post->ID );
			$items[] = array(
				'id'     => $post->ID,
				'title'  => html_entity_decode( get_the_title( $post ), ENT_QUOTES, get_bloginfo( 'charset' ) ),
				'status' => $post->post_status,
				'lang'   => WPM_Translations::get_post_language( $post->ID ),
				'linked' => count( $group ) > 1,
			);
		}
		return rest_ensure_response( $items );
	}
}
