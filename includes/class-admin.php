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
		register_setting( 'wpm_settings', 'wpm_languages', array( 'sanitize_callback' => array( $this, 'sanitize_languages' ) ) );
		register_setting( 'wpm_settings', 'wpm_menus',     array( 'sanitize_callback' => array( $this, 'sanitize_id_map' ) ) );
		register_setting( 'wpm_settings', 'wpm_forms',     array( 'sanitize_callback' => array( $this, 'sanitize_id_map' ) ) );
		register_setting( 'wpm_settings', 'wpm_page_map',  array( 'sanitize_callback' => array( $this, 'sanitize_page_map' ) ) );
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
			array( 'jquery' ),
			WPM_VERSION,
			true
		);
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
}
