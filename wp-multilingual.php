<?php
/**
 * Plugin Name:       PixelLang Multilingual
 * Plugin URI:        https://github.com/Rvelasquezp/pixellang-multilingual
 * Description:       Multilingual WordPress without lock-in: languages, linked translations of posts, pages, custom post types and terms, language-aware menus, template parts and forms, hreflang, multilingual sitemaps, outdated translation tracking, a language suggestion banner, and a language switcher block with design options that can also be added automatically to menus or float on the page.
 * Version:           1.6.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Agence Pixel
 * Author URI:        https://agencepixel.ca
 * License:           GPL-2.0-or-later
 * Text Domain:       pixellang-multilingual
 * Domain Path:       /languages
 *
 * @package PixelLang_Multilingual
 */

defined( 'ABSPATH' ) || exit;

define( 'WPM_VERSION', '1.6.0' );
define( 'WPM_FILE', __FILE__ );
define( 'WPM_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPM_URL', plugin_dir_url( __FILE__ ) );
define( 'WPM_BASENAME', plugin_basename( __FILE__ ) );

require_once WPM_DIR . 'includes/languages-data.php';
require_once WPM_DIR . 'includes/class-settings.php';
require_once WPM_DIR . 'includes/class-language-manager.php';
require_once WPM_DIR . 'includes/class-translations.php';
require_once WPM_DIR . 'includes/class-migration.php';
require_once WPM_DIR . 'includes/class-url-handler.php';
require_once WPM_DIR . 'includes/class-content-switcher.php';
require_once WPM_DIR . 'includes/class-query-filter.php';
require_once WPM_DIR . 'includes/class-hreflang.php';
require_once WPM_DIR . 'includes/class-compat.php';
require_once WPM_DIR . 'includes/class-template-switcher.php';
require_once WPM_DIR . 'includes/class-rest.php';
require_once WPM_DIR . 'includes/class-strings.php';
require_once WPM_DIR . 'includes/class-sync.php';
require_once WPM_DIR . 'includes/class-switcher.php';
require_once WPM_DIR . 'includes/class-license.php';
require_once WPM_DIR . 'includes/class-site-texts.php';
require_once WPM_DIR . 'includes/class-media.php';
require_once WPM_DIR . 'includes/class-slugs.php';
require_once WPM_DIR . 'includes/class-sitemaps.php';
require_once WPM_DIR . 'includes/class-language-banner.php';

if ( is_admin() ) {
	require_once WPM_DIR . 'includes/class-icons.php';
	require_once WPM_DIR . 'includes/class-admin.php';
	require_once WPM_DIR . 'includes/class-meta-box.php';
	require_once WPM_DIR . 'includes/class-admin-columns.php';
	require_once WPM_DIR . 'includes/class-term-language.php';
	require_once WPM_DIR . 'includes/class-string-scanner.php';
	require_once WPM_DIR . 'includes/class-strings-admin.php';
}

/**
 * Plugin services. The 1.0 property names are kept for code that reads them.
 *
 * @return stdClass
 */
function wpm() {
	static $instance = null;
	if ( null === $instance ) {
		$instance            = new stdClass();
		$instance->languages = WPM_Language_Manager::instance();
		$instance->urls      = WPM_Url_Handler::instance();
		$instance->content   = WPM_Content_Switcher::instance();
		$instance->queries   = WPM_Query_Filter::instance();
		$instance->hreflang  = WPM_Hreflang::instance();
		$instance->templates = WPM_Template_Switcher::instance();
		$instance->rest      = WPM_Rest::instance();
		WPM_Translations::init();
		WPM_Strings::init();
		WPM_Compat::init();
		WPM_Sync::init();
		WPM_Sitemaps::init();
		WPM_Language_Banner::init();
		WPM_Switcher::init();
		WPM_License::init();
		WPM_Site_Texts::init();
		WPM_Media::init();
		WPM_Slugs::init();

		if ( is_admin() ) {
			$instance->admin    = WPM_Admin::instance();
			$instance->meta_box = WPM_Meta_Box::instance();
			$instance->columns  = WPM_Admin_Columns::instance();
			$instance->terms    = WPM_Term_Language::instance();
			$instance->strings  = WPM_Strings_Admin::instance();
		}
	}
	return $instance;
}
add_action( 'plugins_loaded', 'wpm', 5 );

add_action( 'init', array( 'WPM_Migration', 'maybe_upgrade' ), 0 );

add_action( 'init', 'wpm_load_textdomain', 0 );
function wpm_load_textdomain() {
	load_plugin_textdomain( 'pixellang-multilingual', false, dirname( WPM_BASENAME ) . '/languages' );
}

add_action( 'init', 'wpm_register_blocks' );
function wpm_register_blocks() {
	register_block_type( WPM_DIR . 'blocks/language-switcher' );
	if ( function_exists( 'wp_set_script_translations' ) ) {
		wp_set_script_translations( 'wp-multilingual-language-switcher-editor-script', 'pixellang-multilingual', WPM_DIR . 'languages' );
	}
}

add_filter( 'plugin_action_links_' . WPM_BASENAME, 'wpm_action_links' );
function wpm_action_links( $links ) {
	array_unshift( $links, sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'admin.php?page=wp-multilingual' ) ), esc_html__( 'Dashboard', 'pixellang-multilingual' ) ) );
	return $links;
}

register_activation_hook( __FILE__, 'wpm_activate' );
function wpm_activate() {
	if ( false === get_option( 'wpm_languages' ) ) {
		update_option(
			'wpm_languages',
			array(
				'fr' => array( 'label' => 'Français', 'locale' => 'fr_FR', 'default' => true ),
				'en' => array( 'label' => 'English', 'locale' => 'en_US', 'default' => false ),
			)
		);
		// A fresh install has no 1.0 data to migrate.
		update_option( 'wpm_db_version', WPM_Migration::DB_VERSION );
	}
	WPM_Strings::install();
	if ( false === get_option( WPM_Settings::OPTION ) ) {
		// New sites suggest translations instead of redirecting by browser language.
		update_option( WPM_Settings::OPTION, array( 'browser_mode' => 'banner' ), true );
	}
	if ( false === get_option( 'wpm_post_types' ) ) {
		update_option( 'wpm_post_types', array( 'page', 'post' ) );
	}
	if ( false === get_option( 'wpm_template_map' ) ) {
		update_option( 'wpm_template_map', array() );
	}
}

register_deactivation_hook( __FILE__, 'wpm_deactivate' );
function wpm_deactivate() {
	delete_transient( 'wpm_stats' );
}
