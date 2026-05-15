<?php
/**
 * Plugin Name: WP Multilingual
 * Plugin URI:  https://github.com/ricardovelasquez/wp-multilingual
 * Description: Plugin multilingüe tipo WPML — gestión de idiomas, URLs, SEO hreflang y bloque language-switcher.
 * Version:     1.0.0
 * Author:      Ricardo Velasquez
 * License:     GPL-2.0-or-later
 * Text Domain: wp-multilingual
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'WPM_VERSION',  '1.0.0' );
define( 'WPM_DIR',      plugin_dir_path( __FILE__ ) );
define( 'WPM_URL',      plugin_dir_url( __FILE__ ) );
define( 'WPM_BASENAME', plugin_basename( __FILE__ ) );

require_once WPM_DIR . 'includes/languages-data.php';
require_once WPM_DIR . 'includes/class-language-manager.php';
require_once WPM_DIR . 'includes/class-url-handler.php';
require_once WPM_DIR . 'includes/class-content-switcher.php';
require_once WPM_DIR . 'includes/class-hreflang.php';
require_once WPM_DIR . 'includes/class-admin.php';
require_once WPM_DIR . 'includes/class-meta-box.php';
require_once WPM_DIR . 'includes/class-admin-columns.php';

function wpm() {
	static $instance = null;
	if ( null === $instance ) {
		$instance = new stdClass();
		$instance->languages = WPM_Language_Manager::instance();
		$instance->urls      = WPM_Url_Handler::instance();
		$instance->content   = WPM_Content_Switcher::instance();
		$instance->hreflang  = WPM_Hreflang::instance();
		$instance->admin     = WPM_Admin::instance();
		$instance->meta_box  = WPM_Meta_Box::instance();
		$instance->columns   = WPM_Admin_Columns::instance();
	}
	return $instance;
}

add_action( 'plugins_loaded', 'wpm', 5 );

add_action( 'init', 'wpm_register_blocks' );
function wpm_register_blocks() {
	register_block_type( WPM_DIR . 'blocks/language-switcher' );
}

register_activation_hook( __FILE__, 'wpm_activate' );
function wpm_activate() {
	if ( false === get_option( 'wpm_languages' ) ) {
		update_option( 'wpm_languages', array(
			'fr' => array( 'label' => 'Français', 'locale' => 'fr_FR', 'default' => true ),
			'en' => array( 'label' => 'English',  'locale' => 'en_US', 'default' => false ),
			'es' => array( 'label' => 'Español',  'locale' => 'es_ES', 'default' => false ),
		) );
	}
	if ( false === get_option( 'wpm_page_map' ) ) {
		update_option( 'wpm_page_map', array() );
	}
}

register_deactivation_hook( __FILE__, 'wpm_deactivate' );
function wpm_deactivate() {}
