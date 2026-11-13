<?php
/**
 * Data upgrades between plugin versions. Never deletes stored data.
 *
 * @package PixelLang_Multilingual
 */

defined( 'ABSPATH' ) || exit;

class WPM_Migration {

	const DB_VERSION = 3;
	const OPTION     = 'wpm_db_version';
	const REPORT     = 'wpm_migration_report';

	/**
	 * Runs pending upgrades once. Cheap when up to date (autoloaded option).
	 */
	public static function maybe_upgrade() {
		$version = (int) get_option( self::OPTION, 1 );
		if ( $version >= self::DB_VERSION ) {
			return;
		}
		// Simple lock so two concurrent requests do not run the upgrade twice.
		if ( get_transient( 'wpm_upgrading' ) ) {
			return;
		}
		set_transient( 'wpm_upgrading', 1, MINUTE_IN_SECONDS );

		if ( $version < 2 ) {
			self::upgrade_to_2();
		}
		if ( $version < 3 ) {
			// 1.2: String Translation tables (new tables, nothing changed).
			WPM_Strings::install();
		}

		update_option( self::OPTION, self::DB_VERSION, true );
		delete_transient( 'wpm_upgrading' );
	}

	/**
	 * 1.0 -> 1.1: translation groups move from the wpm_page_map option to
	 * post meta, and the global Gravity Forms map becomes a form group.
	 *
	 * The page map is read in its stored order and the first group that
	 * contains a post wins, exactly like 1.0's get_translated_page(). Empty
	 * slots, missing posts, groups of one, posts whose language disagrees with
	 * their slot and posts of another post type are skipped and reported.
	 * The wpm_page_map option itself is left untouched as a backup.
	 */
	public static function upgrade_to_2() {
		$report = array(
			'date'     => current_time( 'mysql' ),
			'groups'   => 0,
			'skipped'  => array(),
			'source'   => 0,
		);

		$map = get_option( 'wpm_page_map', array() );
		$map = is_array( $map ) ? $map : array();
		$report['source'] = count( $map );

		$manager  = WPM_Language_Manager::instance();
		$assigned = array();

		foreach ( $map as $key => $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}
			$clean = array();
			$type  = '';
			foreach ( $group as $lang => $post_id ) {
				$post_id = absint( $post_id );
				$lang    = sanitize_key( $lang );
				if ( ! $post_id ) {
					continue;
				}
				$reason = '';
				$post   = get_post( $post_id );
				if ( ! $post ) {
					$reason = 'missing';
				} elseif ( ! $manager->is_valid( $lang ) ) {
					$reason = 'inactive_language';
				} elseif ( isset( $assigned[ $post_id ] ) ) {
					$reason = 'already_linked';
				} elseif ( $type && $post->post_type !== $type ) {
					$reason = 'other_post_type';
				} else {
					$meta_lang = get_post_meta( $post_id, WPM_Translations::META_LANG, true );
					if ( $meta_lang && $meta_lang !== $lang ) {
						$reason = 'language_mismatch';
					}
				}
				if ( $reason ) {
					$report['skipped'][] = array(
						'group'  => (string) $key,
						'lang'   => $lang,
						'post'   => $post_id,
						'reason' => $reason,
					);
					continue;
				}
				$type           = $post->post_type;
				$clean[ $lang ] = $post_id;
			}

			if ( count( $clean ) < 2 ) {
				continue;
			}
			foreach ( $clean as $post_id ) {
				$assigned[ $post_id ] = true;
			}
			WPM_Translations::link( 'post', $clean );
			++$report['groups'];
		}

		// Gravity Forms: 1.0 swapped every form for the one of the language.
		$forms = get_option( 'wpm_forms', array() );
		if ( is_array( $forms ) && count( array_filter( array_map( 'absint', $forms ) ) ) >= 2 ) {
			$groups = WPM_Settings::get( 'form_groups' );
			if ( ! $groups ) {
				WPM_Settings::update(
					array(
						'form_groups' => array(
							array(
								'provider' => 'gf',
								'forms'    => $forms,
							),
						),
					)
				);
			}
		}

		update_option( self::REPORT, $report, false );
	}

	/**
	 * Human readable reasons for the Tools screen.
	 *
	 * @return array<string,string>
	 */
	public static function reasons() {
		return array(
			'missing'           => __( 'Content no longer exists', 'pixellang-multilingual' ),
			'inactive_language' => __( 'Language is not active', 'pixellang-multilingual' ),
			'already_linked'    => __( 'Already linked in an earlier group', 'pixellang-multilingual' ),
			'other_post_type'   => __( 'Different content type than the group', 'pixellang-multilingual' ),
			'language_mismatch' => __( 'Language of the content differs from its slot', 'pixellang-multilingual' ),
		);
	}
}
