<?php
/**
 * WP-CLI: `wp pixellang status`, a summary of the multilingual site.
 *
 * (`wp pixellang license` is registered by WPM_License.)
 *
 * @package PixelLang_Multilingual
 */

defined( 'ABSPATH' ) || exit;

class WPM_CLI {

	public static function init() {
		WP_CLI::add_command( 'pixellang status', array( __CLASS__, 'status' ) );
	}

	/**
	 * Missing translations: content of the default language without a
	 * translation in a language, per post type.
	 *
	 * @param string $type Post type.
	 * @param string $lang Language code.
	 * @return int
	 */
	public static function count_missing( $type, $lang ) {
		$default = WPM_Language_Manager::instance()->get_default();
		$query   = new WP_Query(
			array(
				'post_type'              => $type,
				'post_status'            => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'suppress_filters'       => true,
				'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'AND',
					array(
						'key'   => WPM_Translations::META_LANG,
						'value' => $default,
					),
					array(
						'relation' => 'OR',
						array(
							'key'     => WPM_Translations::META_GROUP,
							'compare' => 'NOT EXISTS',
						),
						array(
							'key'     => WPM_Translations::META_GROUP,
							'value'   => sprintf( 's:%d:"%s";', strlen( $lang ), $lang ),
							'compare' => 'NOT LIKE',
						),
					),
				),
			)
		);
		return (int) $query->found_posts;
	}

	/**
	 * Summary data (also used by --format=json).
	 *
	 * @return array
	 */
	public static function data() {
		$manager = WPM_Language_Manager::instance();
		$default = $manager->get_default();
		WPM_Translations::flush_stats();
		$stats   = WPM_Translations::get_stats();
		$license = WPM_License::status();

		$languages = array();
		foreach ( $manager->get_all() as $slug => $cfg ) {
			$languages[] = array(
				'code'    => $slug,
				'name'    => $cfg['label'],
				'locale'  => $cfg['locale'],
				'default' => $slug === $default,
				'home'    => WPM_Url_Handler::instance()->get_home_url( $slug ),
			);
		}

		$content = array();
		$totals  = array(
			'missing'  => 0,
			'outdated' => 0,
			'no_lang'  => 0,
		);
		foreach ( WPM_Settings::get_post_types() as $type ) {
			if ( ! post_type_exists( $type ) ) {
				continue;
			}
			$row = array( 'type' => $type );
			foreach ( $manager->get_slugs() as $slug ) {
				$row[ $slug ] = isset( $stats['by_lang'][ $slug ][ $type ] ) ? (int) $stats['by_lang'][ $slug ][ $type ] : 0;
			}
			$row['no_lang'] = isset( $stats['unassigned'][ $type ] ) ? (int) $stats['unassigned'][ $type ] : 0;
			$missing        = 0;
			$outdated       = 0;
			foreach ( $manager->get_slugs() as $slug ) {
				if ( $slug === $default ) {
					continue;
				}
				$missing  += self::count_missing( $type, $slug );
				$outdated += isset( $stats['outdated'][ $slug ][ $type ] ) ? (int) $stats['outdated'][ $slug ][ $type ] : 0;
			}
			$row['missing']   = $missing;
			$row['outdated']  = $outdated;
			$totals['missing']  += $missing;
			$totals['outdated'] += $outdated;
			$totals['no_lang']  += $row['no_lang'];
			$content[]        = $row;
		}

		return array(
			'version'   => WPM_VERSION,
			'site'      => home_url( '/' ),
			'license'   => array(
				'state'   => $license['state'],
				'label'   => WPM_License::label( $license['state'] ),
				'client'  => isset( $license['payload']['c'] ) ? $license['payload']['c'] : '',
				'expires' => ! empty( $license['payload']['e'] ) ? $license['payload']['e'] : '',
			),
			'languages' => $languages,
			'content'   => $content,
			'totals'    => $totals,
			'settings'  => array(
				'switcher'        => WPM_Settings::get( 'switcher_placement' ),
				'browser'         => WPM_Settings::browser_mode(),
				'hreflang'        => (bool) WPM_Settings::get( 'hreflang' ),
				'front_redirect'  => (bool) WPM_Settings::get( 'front_redirect' ),
			),
		);
	}

	/**
	 * Shows a summary of the multilingual site: license, languages, content
	 * per language, missing and outdated translations, main settings.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table (default) or json.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp pixellang status
	 *     wp pixellang status --format=json
	 *
	 * @param array $args       Arguments.
	 * @param array $assoc_args Options.
	 */
	public static function status( $args, $assoc_args ) {
		$data = self::data();
		if ( isset( $assoc_args['format'] ) && 'json' === $assoc_args['format'] ) {
			WP_CLI::line( (string) wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
			return;
		}

		WP_CLI::line( WP_CLI::colorize( '%BPixelLang Multilingual ' . $data['version'] . '%n  ' . $data['site'] ) );
		WP_CLI::line( '' );

		$license = $data['license'];
		$color   = in_array( $license['state'], array( 'valid', 'development' ), true ) ? '%G' : ( 'expiring' === $license['state'] ? '%Y' : '%R' );
		$detail  = trim( ( $license['client'] ? $license['client'] : '' ) . ( $license['expires'] ? ', expires ' . $license['expires'] : '' ), ', ' );
		WP_CLI::line( WP_CLI::colorize( 'License:   ' . $color . $license['label'] . '%n' . ( $detail ? ' (' . $detail . ')' : '' ) ) );
		WP_CLI::line( '' );

		WP_CLI::line( WP_CLI::colorize( '%BLanguages%n' ) );
		$rows = array();
		foreach ( $data['languages'] as $lang ) {
			$rows[] = array(
				'code'    => $lang['code'] . ( $lang['default'] ? ' (default)' : '' ),
				'name'    => $lang['name'],
				'locale'  => $lang['locale'],
				'home'    => $lang['home'],
			);
		}
		WP_CLI\Utils\format_items( 'table', $rows, array( 'code', 'name', 'locale', 'home' ) );
		WP_CLI::line( '' );

		WP_CLI::line( WP_CLI::colorize( '%BContent%n (missing = default-language content without a translation; outdated = original changed since)' ) );
		$fields = array_merge( array( 'type' ), wp_list_pluck( $data['languages'], 'code' ), array( 'no_lang', 'missing', 'outdated' ) );
		WP_CLI\Utils\format_items( 'table', $data['content'], $fields );
		WP_CLI::line( '' );

		$t = $data['totals'];
		WP_CLI::line( WP_CLI::colorize( 'Missing translations:  ' . ( $t['missing'] ? '%Y' : '%G' ) . $t['missing'] . '%n' ) );
		WP_CLI::line( WP_CLI::colorize( 'Outdated translations: ' . ( $t['outdated'] ? '%Y' : '%G' ) . $t['outdated'] . '%n' ) );
		WP_CLI::line( WP_CLI::colorize( 'Without a language:    ' . ( $t['no_lang'] ? '%Y' : '%G' ) . $t['no_lang'] . '%n' ) );
		WP_CLI::line( '' );

		$s = $data['settings'];
		WP_CLI::line( sprintf( 'Switcher: %s | Browser language: %s | hreflang: %s | Home redirect: %s', $s['switcher'], $s['browser'], $s['hreflang'] ? 'on' : 'off', $s['front_redirect'] ? 'on' : 'off' ) );
	}
}
