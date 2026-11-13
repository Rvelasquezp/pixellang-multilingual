<?php
/**
 * Integration tests, run inside WordPress with WP-CLI:
 *
 *   wp eval-file wp-content/plugins/pixellang-multilingual/tests/integration.php --exec='define("WP_ADMIN", true);'
 *
 * Everything runs in one database transaction that is rolled back at the
 * end: no post, meta or option written by the tests survives.
 *
 * @package PixelLang_Multilingual
 */

// phpcs:disable

if ( ! defined( 'ABSPATH' ) ) {
	exit( "Run with wp eval-file.\n" );
}

require_once ABSPATH . 'wp-admin/includes/admin.php';

global $wpdb, $wpm_pass, $wpm_fail;
$wpm_pass = 0;
$wpm_fail = array();

function wpm_assert( $condition, $label ) {
	global $wpm_pass, $wpm_fail;
	if ( $condition ) {
		++$wpm_pass;
	} else {
		$wpm_fail[] = $label;
		echo "  FAIL: $label\n";
	}
}

function wpm_post( $title, $lang = '', $type = 'page', $status = 'publish' ) {
	$id = wp_insert_post( array( 'post_title' => $title, 'post_type' => $type, 'post_status' => $status, 'post_content' => '<p>' . $title . '</p>' ) );
	if ( $lang ) {
		update_post_meta( $id, '_wpm_language', $lang );
	}
	return $id;
}

/**
 * Simulates a front-end request for a URL path and resolves its language.
 */
function wpm_visit( $query_args, $cookie = '' ) {
	$_COOKIE = $cookie ? array( 'wpm_lang' => $cookie ) : array();
	$GLOBALS['wp_query']     = new WP_Query( $query_args );
	$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
	$manager                 = WPM_Language_Manager::instance();
	$manager->set_current( $cookie ? $cookie : $manager->get_default() );
	return $GLOBALS['wp_query'];
}

$wpdb->query( 'START TRANSACTION' );
wp_set_current_user( 1 );

// Isolate settings for the run.
$langs = array(
	'fr' => array( 'label' => 'Français', 'locale' => 'fr_FR', 'default' => true ),
	'en' => array( 'label' => 'English', 'locale' => 'en_US', 'default' => false ),
	'es' => array( 'label' => 'Español', 'locale' => 'es_ES', 'default' => false ),
);
WPM_Language_Manager::instance()->save_languages( $langs );
update_option( 'wpm_post_types', array( 'page', 'post' ) );

try {
	echo "Translations: link, unlink, invariants\n";
	$fr = wpm_post( 'Accueil test', 'fr' );
	$en = wpm_post( 'Home test', 'en' );
	$es = wpm_post( 'Inicio test', 'es' );
	WPM_Translations::link( 'post', array( 'fr' => $fr, 'en' => $en ) );
	wpm_assert( WPM_Translations::get_post_translation( $fr, 'en' ) === $en, 'fr -> en' );
	wpm_assert( WPM_Translations::get_post_translation( $en, 'fr' ) === $fr, 'en -> fr (symmetric)' );
	WPM_Translations::link( 'post', array( 'fr' => $fr, 'en' => $en, 'es' => $es ) );
	wpm_assert( count( WPM_Translations::get_post_translations( $es ) ) === 3, 'group of 3 stored on every member' );

	// Moving en to another group must clean the old one.
	$fr2 = wpm_post( 'Autre', 'fr' );
	WPM_Translations::link( 'post', array( 'fr' => $fr2, 'en' => $en ) );
	wpm_assert( 0 === WPM_Translations::get_post_translation( $fr, 'en' ), 'old group no longer points to the moved post' );
	wpm_assert( $es === WPM_Translations::get_post_translation( $fr, 'es' ), 'old group keeps its other members' );
	wpm_assert( $fr2 === WPM_Translations::get_post_translation( $en, 'fr' ), 'moved post points to its new group' );

	WPM_Translations::unlink( 'post', $es );
	wpm_assert( array( 'es' => $es ) === WPM_Translations::get_post_translations( $es ), 'unlinked post is alone' );
	wpm_assert( 0 === WPM_Translations::get_post_translation( $fr, 'es' ), 'group without the unlinked post' );
	wpm_assert( '' === (string) get_post_meta( $fr, '_wpm_translations', true ), 'group of one is not stored' );

	echo "Translations: language changes\n";
	WPM_Translations::link( 'post', array( 'fr' => $fr, 'es' => $es ) );
	WPM_Translations::set_language( 'post', $es, 'fr' ); // Slot fr is taken by $fr.
	wpm_assert( 'fr' === WPM_Translations::get_post_language( $es ), 'language changed' );
	wpm_assert( 0 === WPM_Translations::get_post_translation( $fr, 'es' ), 'conflicting post leaves the group' );
	WPM_Translations::link( 'post', array( 'fr' => $fr, 'es' => $es ) );
	wpm_assert( 'es' === WPM_Translations::get_post_language( $es ), 'link() stamps the slot language' );
	WPM_Translations::set_language( 'post', $es, 'en' ); // Free slot: stays linked.
	wpm_assert( $es === WPM_Translations::get_post_translation( $fr, 'en' ), 'moving to a free slot keeps the link' );
	WPM_Translations::set_language( 'post', $es, '' );
	wpm_assert( '' === WPM_Translations::get_post_language( $es ) && 0 === WPM_Translations::get_post_translation( $fr, 'en' ), 'clearing the language unlinks' );

	echo "Deleting a post cleans its group\n";
	$a = wpm_post( 'A', 'fr' );
	$b = wpm_post( 'B', 'en' );
	$c = wpm_post( 'C', 'es' );
	WPM_Translations::link( 'post', array( 'fr' => $a, 'en' => $b, 'es' => $c ) );
	wp_delete_post( $b, true );
	wpm_assert( array( 'fr' => $a, 'es' => $c ) === WPM_Translations::get_post_translations( $a ), 'deleted member removed from the group' );

	echo "REST: update_post_group validation\n";
	$p   = wpm_post( 'Page FR', 'fr' );
	$art = wpm_post( 'Article EN', 'en', 'post' );
	$r   = WPM_Rest::update_post_group( get_post( $p ), 'fr', array( 'en' => $art ) );
	wpm_assert( is_wp_error( $r ) && 'wpm_invalid_target' === $r->get_error_code(), 'other post type rejected (the 1.0 page/post mix-up)' );
	$es_page = wpm_post( 'Página', 'es' );
	$r       = WPM_Rest::update_post_group( get_post( $p ), 'fr', array( 'en' => $es_page ) );
	wpm_assert( is_wp_error( $r ) && 'wpm_language_mismatch' === $r->get_error_code(), 'post of another language rejected' );
	$none = wpm_post( 'Sans langue' );
	$r    = WPM_Rest::update_post_group( get_post( $p ), 'fr', array( 'en' => $none ) );
	wpm_assert( ! is_wp_error( $r ) && 'en' === WPM_Translations::get_post_language( $none ), 'post without language gets the slot language' );

	echo "REST routes\n";
	$req = new WP_REST_Request( 'POST', '/wpm/v1/link' );
	$req->set_body_params( array( 'post_id' => $p, 'source_lang' => 'fr', 'lang' => 'en', 'target' => 0 ) );
	$res = rest_do_request( $req );
	wpm_assert( 200 === $res->get_status() && ! isset( $res->get_data()['group']['en'] ), 'POST /link with target 0 unlinks' );
	$req = new WP_REST_Request( 'GET', '/wpm/v1/search' );
	$req->set_query_params( array( 'post_type' => 'page', 'lang' => 'en', 'search' => 'Sans langue' ) );
	$res = rest_do_request( $req );
	wpm_assert( 200 === $res->get_status() && in_array( $none, wp_list_pluck( $res->get_data(), 'id' ), true ), 'GET /search finds candidates' );
	$req = new WP_REST_Request( 'POST', '/wpm/v1/nav-language' );
	$req->set_body_params( array( 'nav_id' => $p, 'lang' => 'en' ) );
	wpm_assert( 400 === rest_do_request( $req )->get_status(), 'nav-language refuses a non-navigation post' );
	wp_set_current_user( 0 );
	$req = new WP_REST_Request( 'POST', '/wpm/v1/link' );
	$req->set_body_params( array( 'post_id' => $p, 'source_lang' => 'fr', 'lang' => 'en', 'target' => $none ) );
	wpm_assert( in_array( rest_do_request( $req )->get_status(), array( 401, 403 ), true ), 'anonymous users cannot link' );
	wp_set_current_user( 1 );

	echo "Create a translation draft\n";
	$cat = wp_insert_term( 'Catégorie test', 'category' );
	$src = wpm_post( 'Source FR', 'fr', 'post' );
	update_post_meta( $src, 'custom_field', array( 'a' => 1 ) );
	update_post_meta( $src, '_edit_lock', 'x' );
	wp_set_object_terms( $src, array( (int) $cat['term_id'] ), 'category' );
	$new = WPM_Translations::create_post_translation( $src, 'en' );
	wpm_assert( is_int( $new ) && 'draft' === get_post_status( $new ), 'draft created' );
	wpm_assert( 'en' === WPM_Translations::get_post_language( $new ) && $new === WPM_Translations::get_post_translation( $src, 'en' ), 'linked to the source' );
	wpm_assert( array( 'a' => 1 ) === get_post_meta( $new, 'custom_field', true ), 'custom fields copied' );
	wpm_assert( '' === get_post_meta( $new, '_edit_lock', true ), 'internal meta not copied' );
	wpm_assert( has_term( (int) $cat['term_id'], 'category', $new ), 'terms copied' );
	wpm_assert( $new === WPM_Translations::create_post_translation( $src, 'en' ), 'second call returns the existing translation' );

	echo "Migration from 1.0 page map\n";
	$m1 = wpm_post( 'M1', 'fr' );
	$m2 = wpm_post( 'M2', 'en' );
	$m3 = wpm_post( 'M3 article', 'en', 'post' );
	$m4 = wpm_post( 'M4', 'es' );
	foreach ( array( $m1, $m2, $m3, $m4 ) as $id ) {
		delete_post_meta( $id, '_wpm_translations' );
	}
	update_option(
		'wpm_page_map',
		array(
			'group_a' => array( 'fr' => $m1, 'en' => $m2 ),
			'group_b' => array( 'en' => $m2 ),                // Duplicate singleton.
			'group_c' => array( 'fr' => 0, 'en' => $m3 ),     // Empty slot + singleton.
			'group_d' => array( 'fr' => $m1, 'es' => $m4 ),   // m1 already linked.
			'group_e' => array( 'en' => $m4, 'fr' => 999999 ), // Language mismatch, missing post.
		)
	);
	delete_option( WPM_Migration::REPORT );
	WPM_Migration::upgrade_to_2();
	$report = get_option( WPM_Migration::REPORT );
	wpm_assert( 1 === $report['groups'], 'one valid group migrated' );
	wpm_assert( $m2 === WPM_Translations::get_post_translation( $m1, 'en' ), 'first group wins, like 1.0' );
	wpm_assert( 0 === WPM_Translations::get_post_translation( $m1, 'es' ), 'later duplicate ignored' );
	$reasons = wp_list_pluck( $report['skipped'], 'reason' );
	wpm_assert( in_array( 'already_linked', $reasons, true ) && in_array( 'missing', $reasons, true ) && in_array( 'language_mismatch', $reasons, true ), 'skipped entries reported with reasons' );
	wpm_assert( 5 === count( get_option( 'wpm_page_map' ) ), 'legacy option untouched' );

	echo "Settings sanitization\n";
	wpm_assert( 3650 === WPM_Settings::sanitize( 'cookie_days', 99999 ) && 1 === WPM_Settings::sanitize( 'cookie_days', 0 ), 'cookie days bounded' );
	$groups = WPM_Settings::sanitize( 'form_groups', array( array( 'provider' => 'cf7', 'forms' => array( 'fr' => 5, 'en' => 6, 'xx' => 7 ) ), array( 'provider' => 'cf7', 'forms' => array( 'fr' => 5 ) ), array( 'provider' => 'woo', 'forms' => array( 'fr' => 1, 'en' => 2 ) ) ) );
	wpm_assert( 1 === count( $groups ) && array( 'fr' => 5, 'en' => 6 ) === $groups[0]['forms'], 'form groups: unknown language, single form and unknown provider dropped' );
	wpm_assert( array() === WPM_Settings::sanitize( 'menu_locations', array( 'fr' => array( 'nope' => 3 ) ) ), 'unknown menu location dropped' );
	wpm_assert( array() === WPM_Settings::sanitize( 'taxonomies', array( 'wp_theme', 'nav_menu' ) ), 'internal taxonomies refused' );

	echo "Forms: Contact Form 7 swap\n";
	WPM_Settings::update( array( 'form_groups' => array( array( 'provider' => 'cf7', 'forms' => array( 'fr' => 11, 'en' => 22 ) ) ) ) );
	WPM_Language_Manager::instance()->set_current( 'en' );
	// swap_cf7_form() is disabled in the admin (form previews): test the resolution it relies on.
	wpm_assert( 22 === WPM_Content_Switcher::instance()->get_form_translation( 'cf7', 11 ), 'CF7 form resolved to the English one' );
	wpm_assert( 22 === WPM_Content_Switcher::instance()->get_form_translation( 'cf7', 22 ), 'form already in the language stays' );
	wpm_assert( 33 === WPM_Content_Switcher::instance()->get_form_translation( 'cf7', 33 ), 'forms outside a group are untouched' );
	wpm_assert( 11 === WPM_Content_Switcher::instance()->get_form_translation( 'gf', 11 ), 'groups are per plugin' );
	wpm_assert( has_filter( 'shortcode_atts_wpcf7' ), 'CF7 hook registered' );

	echo "Front end: URL language, hreflang, switcher\n";
	$hf = wpm_post( 'Hreflang FR', 'fr' );
	$he = wpm_post( 'Hreflang EN', 'en' );
	$hd = wpm_post( 'Brouillon ES', 'es', 'page', 'draft' );
	WPM_Translations::link( 'post', array( 'fr' => $hf, 'en' => $he, 'es' => $hd ) );
	WPM_Settings::update( array( 'hreflang' => true ) );
	wpm_visit( array( 'page_id' => $he ), 'fr' );
	wpm_assert( 'en' === WPM_Url_Handler::instance()->get_queried_language(), 'the page decides the language, not the cookie' );
	$alt = WPM_Hreflang::instance()->get_alternates();
	wpm_assert( isset( $alt['fr-FR'], $alt['en-US'], $alt['x-default'] ) && ! isset( $alt['es-ES'] ), 'hreflang lists published translations only' );
	wpm_assert( false === strpos( implode( ' ', $alt ), 'set_lang' ), 'hreflang URLs have no redirect parameter' );
	wpm_assert( get_permalink( $hf ) === WPM_Url_Handler::instance()->get_language_url( 'fr' ), 'switcher links straight to the translation' );
	wpm_assert( '' === WPM_Url_Handler::instance()->get_language_url( 'es', false ), 'draft translation is not linked' );
	WPM_Language_Manager::instance()->set_current( 'en' );
	$html = render_block( array( 'blockName' => 'wp-multilingual/language-switcher', 'attrs' => array( 'hideMissing' => true ), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ) );
	wpm_assert( false !== strpos( $html, 'hreflang="fr-FR"' ) && false === strpos( $html, 'hreflang="es-ES"' ), 'hideMissing hides languages without translation' );
	wpm_assert( false !== strpos( $html, 'aria-current="true"' ), 'current language marked' );
	$html = render_block( array( 'blockName' => 'wp-multilingual/language-switcher', 'attrs' => array( 'style' => 'dropdown', 'hideCurrent' => true ), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ) );
	wpm_assert( false !== strpos( $html, '<select' ) && false === strpos( $html, '>English<' ), 'dropdown without the current language' );

	echo "Compatibility filters\n";
	WPM_Language_Manager::instance()->set_current( 'en' );
	wpm_assert( 'en' === apply_filters( 'wpml_current_language', null ), 'wpml_current_language' );
	wpm_assert( 'fr' === apply_filters( 'wpml_default_language', null ), 'wpml_default_language' );
	wpm_assert( $hf === apply_filters( 'wpml_object_id', $he, 'page', false, 'fr' ), 'wpml_object_id' );
	wpm_assert( $he === wpm_get_post_translation( $hf, 'en' ), 'wpm_get_post_translation()' );

	echo "Admin list: language filter\n";
	global $pagenow;
	$pagenow       = 'edit.php';
	$_GET['wpm_lang'] = 'none';
	$q             = new WP_Query();
	$GLOBALS['wp_the_query'] = $q;
	$q->query( array( 'post_type' => 'page', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) );
	wpm_assert( in_array( $hd, $q->posts, true ) === false && ! array_diff( $q->posts, array_filter( $q->posts, function ( $id ) {
		return '' === WPM_Translations::get_post_language( $id );
	} ) ), '"No language" filter only lists content without language' );
	unset( $_GET['wpm_lang'] );

	echo "Admin handlers (one form per section)\n";
	$catch = function ( $location ) {
		throw new RuntimeException( 'redirect:' . $location );
	};
	add_filter( 'wp_redirect', $catch );
	$run = function ( $method, $post, $nonce ) {
		$_POST = $_REQUEST = array_merge( $post, array( '_wpnonce' => wp_create_nonce( $nonce ) ) );
		try {
			WPM_Admin::instance()->$method();
		} catch ( RuntimeException $e ) {
			return $e->getMessage();
		}
		return '';
	};
	$before_menus = get_option( 'wpm_menus' );
	$r = $run( 'handle_save_settings', array( 'section' => 'behavior', 'cookie_days' => '90', 'hreflang' => '1' ), 'wpm_save_settings' );
	WPM_Settings::flush();
	wpm_assert( false !== strpos( $r, 'wpm_notice=saved' ) && 90 === WPM_Settings::get( 'cookie_days' ) && ! WPM_Settings::get( 'browser_detection' ) && WPM_Settings::get( 'hreflang' ), 'behavior section saved' );
	wpm_assert( $before_menus === get_option( 'wpm_menus' ), 'saving one section leaves the others untouched' );
	$run( 'handle_save_settings', array( 'section' => 'content', 'post_types' => array( 'page', 'attachment', 'nope' ), 'taxonomies' => array( 'category' ) ), 'wpm_save_settings' );
	WPM_Settings::flush();
	wpm_assert( array( 'page', 'post' ) === get_option( 'wpm_post_types' ), 'post types: page and post forced, invalid types dropped' );
	wpm_assert( array( 'category' ) === WPM_Settings::get_taxonomies(), 'taxonomies saved' );
	$run( 'handle_save_settings', array( 'section' => 'menus', 'nav_menus' => array( 'fr' => $hf, 'en' => 4 ) ), 'wpm_save_settings' );
	$menus = get_option( 'wpm_menus' );
	wpm_assert( ! isset( $menus['fr'] ) && ( 'wp_navigation' !== get_post_type( 4 ) || 4 === $menus['en'] ), 'menus: only navigation posts accepted' );
	$r = $run( 'handle_save_languages', array( 'languages' => array( 'en' => array( 'label' => 'English <b>', 'locale' => 'en_CA' ), 'BAD KEY' => array( 'label' => 'x' ), 'fr' => array( 'label' => '', 'locale' => 'not a locale' ) ), 'default_language' => 'en' ), 'wpm_save_languages' );
	$saved = get_option( 'wpm_languages' );
	wpm_assert( array( 'en', 'fr' ) === array_keys( $saved ), 'languages: order kept, invalid code dropped' );
	wpm_assert( 'English' === $saved['en']['label'] && 'en_CA' === $saved['en']['locale'] && $saved['en']['default'], 'languages: label sanitized, locale and default saved' );
	wpm_assert( 'Français' === $saved['fr']['label'] && 'fr_FR' === $saved['fr']['locale'], 'languages: empty name and bad locale fall back to the catalog' );
	$r = $run( 'handle_save_languages', array( 'languages' => array() ), 'wpm_save_languages' );
	wpm_assert( false !== strpos( $r, 'no_language' ) && 2 === count( get_option( 'wpm_languages' ) ), 'languages: refusing to save an empty list' );
	$_POST = $_REQUEST = array( 'section' => 'behavior', '_wpnonce' => 'bad' );
	$died  = false;
	add_filter( 'wp_die_handler', function () use ( &$died ) {
		return function () use ( &$died ) {
			$died = true;
			throw new RuntimeException( 'died' );
		};
	} );
	try {
		WPM_Admin::instance()->handle_save_settings();
	} catch ( RuntimeException $e ) {
	}
	wpm_assert( $died, 'invalid nonce rejected' );
	remove_filter( 'wp_redirect', $catch );
	$_POST = $_REQUEST = array();
	WPM_Language_Manager::instance()->save_languages( $langs );

	echo "String Translation: registry\n";
	$a1 = WPM_Strings::register( array( 'original' => 'Test string', 'domain' => 'wpm-test', 'source_type' => 'theme', 'source_name' => 't', 'location' => 'a.php:1' ) );
	$a2 = WPM_Strings::register( array( 'original' => 'Test string', 'domain' => 'wpm-test', 'source_type' => 'theme', 'source_name' => 't', 'location' => 'b.php:9' ) );
	$b1 = WPM_Strings::register( array( 'original' => 'Test string', 'domain' => 'wpm-test', 'context' => 'button' ) );
	$c1 = WPM_Strings::register( array( 'original' => 'Test string', 'domain' => 'other-domain' ) );
	wpm_assert( $a1 === $a2 && is_int( $a1 ), 'same domain + context + text = same string' );
	wpm_assert( 2 === count( json_decode( WPM_Strings::get( $a1 )->locations, true ) ), 'locations merged' );
	wpm_assert( $b1 !== $a1 && $c1 !== $a1 && $c1 !== $b1, 'other context or domain = other string' );
	wpm_assert( is_wp_error( WPM_Strings::register( array( 'original' => '  ' ) ) ), 'empty original refused' );

	echo "String Translation: resolution\n";
	WPM_Strings::save_translation( $a1, 'fr', 'Chaîne de test' );
	WPM_Strings::save_translation( $b1, 'fr', 'Bouton test' );
	wpm_assert( isset( WPM_Strings::get_domains()['wpm-test'] ) && ! isset( WPM_Strings::get_domains()['other-domain'] ), 'only translated domains are hooked' );
	add_filter( 'gettext', array( 'WPM_Strings', 'filter_gettext' ), 20, 3 );
	add_filter( 'gettext_with_context', array( 'WPM_Strings', 'filter_gettext_with_context' ), 20, 4 );
	add_filter( 'ngettext', array( 'WPM_Strings', 'filter_ngettext' ), 20, 5 );
	WPM_Language_Manager::instance()->set_current( 'fr' );
	wpm_assert( 'Chaîne de test' === __( 'Test string', 'wpm-test' ), '__() translated in FR' );
	wpm_assert( 'Chaîne de test' === esc_html__( 'Test string', 'wpm-test' ), 'esc_html__() translated' );
	ob_start();
	esc_html_e( 'Test string', 'wpm-test' );
	wpm_assert( 'Chaîne de test' === ob_get_clean(), 'esc_html_e() translated' );
	wpm_assert( 'Bouton test' === _x( 'Test string', 'button', 'wpm-test' ), '_x() uses its context' );
	wpm_assert( 'Test string' === __( 'Test string', 'other-domain' ), 'same text in another domain untouched' );
	WPM_Language_Manager::instance()->set_current( 'en' );
	wpm_assert( 'Test string' === __( 'Test string', 'wpm-test' ), 'EN shows the original' );
	WPM_Language_Manager::instance()->set_current( 'fr' );
	$n1 = WPM_Strings::register( array( 'original' => '%d item', 'original_plural' => '%d items', 'domain' => 'wpm-test' ) );
	WPM_Strings::save_translation( $n1, 'fr', '%d élément', '%d éléments' );
	wpm_assert( '%d élément' === _n( '%d item', '%d items', 1, 'wpm-test' ) && '%d éléments' === _n( '%d item', '%d items', 3, 'wpm-test' ) && '%d élément' === _n( '%d item', '%d items', 0, 'wpm-test' ), 'plurals follow the French rule' );
	WPM_Strings::save_translation( $a1, 'fr', '<script>alert(1)</script><strong>Chaîne</strong>' );
	wpm_assert( '<strong>Chaîne</strong>' === WPM_Strings::lookup( 'Test string', '', 'wpm-test' ) || 'alert(1)<strong>Chaîne</strong>' === WPM_Strings::lookup( 'Test string', '', 'wpm-test' ), 'translations are sanitized (no script tags)' );
	WPM_Strings::save_translation( $a1, 'fr', '' );
	wpm_assert( 'Test string' === __( 'Test string', 'wpm-test' ), 'empty value removes the translation' );
	remove_filter( 'gettext', array( 'WPM_Strings', 'filter_gettext' ), 20 );
	remove_filter( 'gettext_with_context', array( 'WPM_Strings', 'filter_gettext_with_context' ), 20 );
	remove_filter( 'ngettext', array( 'WPM_Strings', 'filter_ngettext' ), 20 );

	echo "String Translation: literal HTML text\n";
	$l1 = WPM_Strings::register( array( 'original' => 'Hello there', 'source_type' => 'literal' ) );
	WPM_Strings::save_translation( $l1, 'fr', 'Bonjour à vous' );
	$html = '<p title="Hello there">Hello there</p><script>var a="Hello there";</script><a href="/x?Hello there">Hello there!</a><!-- Hello there -->';
	$out  = WPM_Strings::replace_literals( $html );
	wpm_assert( '<p title="Hello there">Bonjour à vous</p><script>var a="Hello there";</script><a href="/x?Hello there">Bonjour à vous!</a><!-- Hello there -->' === $out, 'literal text replaced in text nodes only' );

	echo "String Translation: scanner\n";
	$code  = '<?php echo __( \'Scan A\', \'dom\' ); esc_html_e( "Scan B", \'dom\' ); _x( \'Scan C\', \'ctx\', \'dom\' ); _n( \'%d one\', \'%d many\', $n, \'dom\' ); __( $var, \'dom\' ); $o->__( \'No\', \'dom\' ); __( \'Core\' ); __( \'Core2\', \'default\' ); __( \'It\\\'s\', \'dom\' );';
	$found = WPM_String_Scanner::parse_php( $code );
	$texts = wp_list_pluck( $found, 'original' );
	wpm_assert( array( 'Scan A', 'Scan B', 'Scan C', '%d one', "It's" ) === $texts, 'PHP scan: literals kept, variables, methods and core skipped' );
	wpm_assert( 'ctx' === $found[2]['context'] && '%d many' === $found[3]['original_plural'], 'PHP scan: context and plural read' );
	$js = WPM_String_Scanner::parse_js( "const a = __( 'Js A', 'dom' ); wp.i18n._x( \"Js B\", 'ctx', 'dom' ); obj.__( 'No', 'dom' );" );
	wpm_assert( array( 'Js A', 'Js B' ) === wp_list_pluck( $js, 'original' ), 'JS scan' );

	echo "String Translation: permissions and deletion\n";
	wp_set_current_user( 0 );
	$req = new WP_REST_Request( 'POST', '/wpm/v1/strings/' . $b1 );
	$req->set_body_params( array( 'translations' => array( 'fr' => array( 'value' => 'hack' ) ) ) );
	wpm_assert( in_array( rest_do_request( $req )->get_status(), array( 401, 403 ), true ), 'anonymous users cannot edit strings' );
	wp_set_current_user( 1 );
	wpm_assert( 200 === rest_do_request( $req )->get_status() && 'hack' === WPM_Strings::get( $b1 )->translations['fr']->value, 'administrators can edit through REST' );
	wpm_assert( 4 === WPM_Strings::delete( array( $a1, $b1, $c1, $n1 ) ) && null === WPM_Strings::get( $b1 ), 'strings deleted with their translations' );

	echo "String Translation: performance\n";
	add_filter( 'gettext', array( 'WPM_Strings', 'filter_gettext' ), 20, 3 );
	$t0 = microtime( true );
	for ( $i = 0; $i < 20000; $i++ ) {
		__( 'Some untranslated text', 'some-plugin' );
	}
	$untranslated = ( microtime( true ) - $t0 ) * 1000;
	remove_filter( 'gettext', array( 'WPM_Strings', 'filter_gettext' ), 20 );
	$t0 = microtime( true );
	for ( $i = 0; $i < 20000; $i++ ) {
		__( 'Some untranslated text', 'some-plugin' );
	}
	$baseline = ( microtime( true ) - $t0 ) * 1000;
	printf( "  20 000 gettext calls: %.1f ms with String Translation, %.1f ms without\n", $untranslated, $baseline );
	wpm_assert( $untranslated - $baseline < 40, 'overhead under 2 microseconds per call' );

	echo "Locale stays the user's in the admin\n";
	WPM_Language_Manager::instance()->set_current( 'en' );
	wpm_assert( 'xx_XX' === WPM_Language_Manager::instance()->filter_locale( 'xx_XX' ), 'admin locale not changed by the visitor language' );
} catch ( Throwable $e ) {
	$wpm_fail[] = 'Exception: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
	echo '  EXCEPTION: ', $e->getMessage(), ' @ ', $e->getFile(), ':', $e->getLine(), "\n";
}

$wpdb->query( 'ROLLBACK' );
wp_cache_flush();

printf( "\n%d passed, %d failed\n", $wpm_pass, count( $wpm_fail ) );
if ( $wpm_fail ) {
	exit( 1 );
}
