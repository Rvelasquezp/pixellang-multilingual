<?php
defined( 'ABSPATH' ) || exit;

class WPM_Admin {

	private static $instance = null;

	private function __construct() {
		add_action( 'admin_menu',            array( $this, 'register_menu' ) );
		add_action( 'admin_init',            array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register_menu() {
		add_menu_page(
			__( 'WP Multilingual', 'wp-multilingual' ),
			__( 'Multilingual', 'wp-multilingual' ),
			'manage_options',
			'wp-multilingual',
			array( $this, 'render_settings_page' ),
			'dashicons-translation',
			80
		);
	}

	public function register_settings() {
		register_setting( 'wpm_settings', 'wpm_languages',   array( 'sanitize_callback' => array( $this, 'sanitize_languages' ) ) );
		register_setting( 'wpm_settings', 'wpm_menus',       array( 'sanitize_callback' => array( $this, 'sanitize_id_map' ) ) );
		register_setting( 'wpm_settings', 'wpm_forms',       array( 'sanitize_callback' => array( $this, 'sanitize_id_map' ) ) );
		register_setting( 'wpm_settings', 'wpm_page_map',    array( 'sanitize_callback' => array( $this, 'sanitize_page_map' ) ) );
		register_setting( 'wpm_settings', 'wpm_post_types',  array( 'sanitize_callback' => array( $this, 'sanitize_post_types' ) ) );
	}

	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_wp-multilingual' !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'wpm-admin',
			WPM_URL . 'admin/css/admin.css',
			array(),
			WPM_VERSION
		);
		wp_enqueue_script(
			'wpm-admin',
			WPM_URL . 'admin/js/admin.js',
			array( 'jquery', 'wp-api-fetch' ),
			WPM_VERSION,
			true
		);
		wp_localize_script( 'wpm-admin', 'wpApiSettings', array(
			'root'  => esc_url_raw( rest_url() ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
		) );
	}

	public function render_settings_page() {
		require_once WPM_DIR . 'admin/views/settings-page.php';
	}

	public function sanitize_languages( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}
		$clean       = array();
		$has_default = false;
		foreach ( $input as $slug => $cfg ) {
			$slug = sanitize_key( $slug );
			if ( ! $slug ) {
				continue;
			}
			$clean[ $slug ] = array(
				'label'   => sanitize_text_field( isset( $cfg['label'] )  ? $cfg['label']  : $slug ),
				'locale'  => sanitize_text_field( isset( $cfg['locale'] ) ? $cfg['locale'] : '' ),
				'default' => ! empty( $cfg['default'] ),
			);
			if ( $clean[ $slug ]['default'] ) {
				$has_default = true;
			}
		}
		if ( ! $has_default && ! empty( $clean ) ) {
			$keys = array_keys( $clean );
			$clean[ $keys[0] ]['default'] = true;
		}
		return $clean;
	}

	public function sanitize_id_map( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}
		$clean = array();
		foreach ( $input as $slug => $id ) {
			$clean[ sanitize_key( $slug ) ] = absint( $id );
		}
		return $clean;
	}

	public function sanitize_page_map( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}
		$clean = array();
		foreach ( $input as $group_key => $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}
			$clean_group = array();
			foreach ( $group as $slug => $pid ) {
				$clean_group[ sanitize_key( $slug ) ] = absint( $pid );
			}
			$clean[ sanitize_key( $group_key ) ] = $clean_group;
		}
		return $clean;
	}

	public function sanitize_post_types( $input ) {
		if ( ! is_array( $input ) ) {
			return array( 'page', 'post' );
		}
		$allowed = array_keys( $this->get_translatable_post_types() );
		$clean   = array();
		foreach ( $input as $type ) {
			$type = sanitize_key( $type );
			if ( in_array( $type, $allowed, true ) ) {
				$clean[] = $type;
			}
		}
		// page and post are always included.
		foreach ( array( 'page', 'post' ) as $required ) {
			if ( ! in_array( $required, $clean, true ) ) {
				$clean[] = $required;
			}
		}
		return $clean;
	}

	/**
	 * Returns all public post types that can be enabled for translation.
	 * Excludes internal WP types.
	 */
	public static function get_translatable_post_types() {
		$excluded = array( 'attachment', 'revision', 'nav_menu_item', 'custom_css',
		                   'customize_changeset', 'oembed_cache', 'user_request',
		                   'wp_block', 'wp_template', 'wp_template_part',
		                   'wp_global_styles', 'wp_navigation', 'wp_font_face', 'wp_font_family' );

		$types  = get_post_types( array( 'show_ui' => true ), 'objects' );
		$result = array();
		foreach ( $types as $slug => $obj ) {
			if ( ! in_array( $slug, $excluded, true ) ) {
				$result[ $slug ] = $obj;
			}
		}
		return $result;
	}
}
