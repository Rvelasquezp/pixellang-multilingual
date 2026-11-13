<?php
/**
 * Outdated translations.
 *
 * Every translatable post stores a hash of its own text (title, content,
 * excerpt) in `_wpm_content_hash`, refreshed when it is saved. A translation
 * stores the post it was translated from (`_wpm_source`) and the source hash
 * at the time it was last brought up to date (`_wpm_source_hash`). When the
 * two hashes differ, the original changed after the translation: the
 * translation is outdated until someone marks it up to date again.
 *
 * Only text changes count: saving without editing, changing the status, the
 * date or a custom field never marks translations outdated. Comparing hashes
 * costs one meta read per post, already cached by WP_Query.
 *
 * `_wpm_source_revision` keeps the latest revision of the source when the
 * translation was synced, so the editor can show exactly what changed with
 * the core revision compare screen.
 *
 * @package PixelLang_Multilingual
 */

defined( 'ABSPATH' ) || exit;

class WPM_Sync {

	const META_HASH     = '_wpm_content_hash';
	const META_SOURCE   = '_wpm_source';
	const META_SYNCED   = '_wpm_source_hash';
	const META_REVISION = '_wpm_source_revision';

	const CURRENT  = 'current';
	const OUTDATED = 'outdated';
	const NONE     = 'none';

	public static function init() {
		add_action( 'save_post', array( __CLASS__, 'on_save_post' ), 20, 2 );
		add_action( 'wpm_translation_created', array( __CLASS__, 'on_translation_created' ), 10, 2 );
		add_action( 'wpm_group_written', array( __CLASS__, 'on_group_written' ), 10, 2 );
		add_action( 'wpm_object_unlinked', array( __CLASS__, 'on_unlinked' ), 10, 2 );
		add_filter( 'wpm_translation_skip_meta', array( __CLASS__, 'skip_meta' ) );
	}

	/**
	 * Hash of the translatable text of a post.
	 *
	 * @param WP_Post|int $post Post.
	 * @return string
	 */
	public static function compute_hash( $post ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return '';
		}
		$text = trim( $post->post_title ) . "\n" . trim( $post->post_content ) . "\n" . trim( $post->post_excerpt );
		/**
		 * Filters the text whose changes mark translations outdated.
		 *
		 * @param string  $text Title, content and excerpt.
		 * @param WP_Post $post Post.
		 */
		return md5( (string) apply_filters( 'wpm_sync_text', $text, $post ) );
	}

	/**
	 * Stored hash of a post, computed and stored on first use.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function get_hash( $post_id ) {
		$hash = get_post_meta( $post_id, self::META_HASH, true );
		if ( ! $hash ) {
			$hash = self::compute_hash( $post_id );
			if ( $hash ) {
				update_post_meta( $post_id, self::META_HASH, $hash );
			}
		}
		return (string) $hash;
	}

	/**
	 * Refreshes the hash when the text of a translatable post changes.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public static function on_save_post( $post_id, $post ) {
		if ( ! $post || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || 'auto-draft' === $post->post_status ) {
			return;
		}
		if ( ! WPM_Settings::is_translatable_post_type( $post->post_type ) ) {
			return;
		}
		$hash = self::compute_hash( $post );
		if ( get_post_meta( $post_id, self::META_HASH, true ) !== $hash ) {
			update_post_meta( $post_id, self::META_HASH, $hash );
			WPM_Translations::flush_stats();
		}
	}

	/**
	 * Source of a translation, or 0 when the post is an original.
	 *
	 * The stored source only counts while it is still in the same group.
	 *
	 * @param int $post_id Post ID.
	 * @return int
	 */
	public static function get_source( $post_id ) {
		$source = (int) get_post_meta( $post_id, self::META_SOURCE, true );
		if ( ! $source || $source === (int) $post_id ) {
			return 0;
		}
		return in_array( $source, WPM_Translations::get_post_translations( $post_id ), true ) ? $source : 0;
	}

	/**
	 * Sync status of a post: current, outdated, or none (an original).
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function get_status( $post_id ) {
		$source = self::get_source( $post_id );
		if ( ! $source ) {
			return self::NONE;
		}
		$synced = (string) get_post_meta( $post_id, self::META_SYNCED, true );
		if ( '' === $synced ) {
			return self::CURRENT; // Linked before 1.3 and not migrated yet: assume current.
		}
		return hash_equals( self::get_hash( $source ), $synced ) ? self::CURRENT : self::OUTDATED;
	}

	/**
	 * Whether a post is an outdated translation.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function is_outdated( $post_id ) {
		return self::OUTDATED === self::get_status( $post_id );
	}

	/**
	 * Marks a translation as up to date with its source.
	 *
	 * @param int $post_id Translation ID.
	 * @return bool False when the post has no source.
	 */
	public static function mark_synced( $post_id ) {
		$source = self::get_source( $post_id );
		if ( ! $source ) {
			return false;
		}
		update_post_meta( $post_id, self::META_SYNCED, self::get_hash( $source ) );
		$revision = self::latest_revision( $source );
		if ( $revision ) {
			update_post_meta( $post_id, self::META_REVISION, $revision );
		} else {
			delete_post_meta( $post_id, self::META_REVISION );
		}
		WPM_Translations::flush_stats();
		return true;
	}

	/**
	 * Latest revision ID of a post, or 0.
	 *
	 * @param int $post_id Post ID.
	 * @return int
	 */
	private static function latest_revision( $post_id ) {
		if ( ! wp_revisions_enabled( get_post( $post_id ) ) ) {
			return 0;
		}
		$revisions = wp_get_post_revisions(
			$post_id,
			array(
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'DESC',
			)
		);
		return $revisions ? (int) reset( $revisions ) : 0;
	}

	/**
	 * Revision compare URL between the source as translated and the source now.
	 *
	 * @param int $post_id Translation ID.
	 * @return string '' when revisions are not available.
	 */
	public static function get_compare_url( $post_id ) {
		$source = self::get_source( $post_id );
		$from   = (int) get_post_meta( $post_id, self::META_REVISION, true );
		$to     = $source ? self::latest_revision( $source ) : 0;
		if ( ! $from || ! $to || $from === $to || ! get_post( $from ) || ! current_user_can( 'edit_post', $source ) ) {
			return '';
		}
		return add_query_arg(
			array(
				'from' => $from,
				'to'   => $to,
			),
			admin_url( 'revision.php' )
		);
	}

	/**
	 * A new translation draft is up to date with its source.
	 *
	 * @param int $new_id    Translation ID.
	 * @param int $source_id Source ID.
	 */
	public static function on_translation_created( $new_id, $source_id ) {
		update_post_meta( $new_id, self::META_SOURCE, (int) $source_id );
		self::mark_synced( $new_id );
	}

	/**
	 * Keeps the source of every member of a group consistent.
	 *
	 * The source is the post most members already point to; otherwise the
	 * member in the default language; otherwise the oldest member. Members
	 * that get a new source start up to date: linking existing content means
	 * someone considers it a translation of the other.
	 *
	 * @param string $type  'post' or 'term'.
	 * @param array  $group lang => id.
	 */
	public static function on_group_written( $type, array $group ) {
		if ( 'post' !== $type ) {
			return;
		}
		$ids = array_map( 'intval', array_values( $group ) );
		if ( count( $ids ) < 2 ) {
			foreach ( $ids as $id ) {
				self::clear( $id );
			}
			return;
		}
		$source = self::pick_source( $group );
		foreach ( $ids as $id ) {
			if ( $id === $source ) {
				self::clear( $id );
				continue;
			}
			$stored = (int) get_post_meta( $id, self::META_SOURCE, true );
			if ( $stored !== $source || '' === (string) get_post_meta( $id, self::META_SYNCED, true ) ) {
				update_post_meta( $id, self::META_SOURCE, $source );
				self::mark_synced( $id );
			}
		}
	}

	/**
	 * Source of a group (see on_group_written()).
	 *
	 * @param array $group lang => id.
	 * @return int
	 */
	public static function pick_source( array $group ) {
		$ids   = array_map( 'intval', array_values( $group ) );
		$votes = array();
		foreach ( $ids as $id ) {
			$stored = (int) get_post_meta( $id, self::META_SOURCE, true );
			if ( $stored && $stored !== $id && in_array( $stored, $ids, true ) ) {
				$votes[ $stored ] = ( isset( $votes[ $stored ] ) ? $votes[ $stored ] : 0 ) + 1;
			}
		}
		if ( $votes ) {
			arsort( $votes );
			return (int) key( $votes );
		}
		$default = WPM_Language_Manager::instance()->get_default();
		if ( isset( $group[ $default ] ) ) {
			return (int) $group[ $default ];
		}
		return min( $ids );
	}

	/**
	 * A post that leaves its group is no longer a translation.
	 *
	 * @param string $type 'post' or 'term'.
	 * @param int    $id   Object ID.
	 */
	public static function on_unlinked( $type, $id ) {
		if ( 'post' === $type ) {
			self::clear( (int) $id );
		}
	}

	/**
	 * Removes the translation markers of a post (its own hash stays).
	 *
	 * @param int $post_id Post ID.
	 */
	private static function clear( $post_id ) {
		delete_post_meta( $post_id, self::META_SOURCE );
		delete_post_meta( $post_id, self::META_SYNCED );
		delete_post_meta( $post_id, self::META_REVISION );
	}

	/**
	 * Sync markers are never copied to a new translation.
	 *
	 * @param string[] $skip Meta keys.
	 * @return string[]
	 */
	public static function skip_meta( $skip ) {
		return array_merge( (array) $skip, array( self::META_HASH, self::META_SOURCE, self::META_SYNCED, self::META_REVISION ) );
	}

	/**
	 * Outdated translations, in one query.
	 *
	 * @param array $args {
	 *     @type string $post_type Limit to a post type.
	 *     @type string $lang      Limit to translations in a language.
	 * }
	 * @return array[] Rows with keys id, source, lang, post_type.
	 */
	public static function get_outdated( array $args = array() ) {
		global $wpdb;
		$types = isset( $args['post_type'] ) ? array( $args['post_type'] ) : WPM_Settings::get_post_types();
		$types = array_values( array_filter( $types, array( 'WPM_Settings', 'is_translatable_post_type' ) ) );
		if ( ! $types ) {
			return array();
		}
		$in     = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$params = array_merge( array( self::META_SOURCE, self::META_SYNCED, self::META_HASH, WPM_Translations::META_LANG ), $types );
		$where  = '';
		if ( ! empty( $args['lang'] ) ) {
			$where    = ' AND l.meta_value = %s';
			$params[] = $args['lang'];
		}
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID AS id, s.meta_value AS source, l.meta_value AS lang, p.post_type
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = %s
				INNER JOIN {$wpdb->postmeta} y ON y.post_id = p.ID AND y.meta_key = %s
				INNER JOIN {$wpdb->postmeta} h ON h.post_id = s.meta_value AND h.meta_key = %s
				INNER JOIN {$wpdb->postmeta} l ON l.post_id = p.ID AND l.meta_key = %s
				INNER JOIN {$wpdb->posts} src ON src.ID = s.meta_value AND src.post_status IN ('publish','draft','pending','future','private')
				WHERE p.post_type IN ($in) AND p.post_status IN ('publish','draft','pending','future','private')
				AND h.meta_value <> y.meta_value{$where}",
				$params
			),
			ARRAY_A
		);
		// phpcs:enable
		$manager = WPM_Language_Manager::instance();
		$out     = array();
		foreach ( (array) $rows as $row ) {
			// The stored source must still be in the group of the translation.
			if ( $manager->is_valid( $row['lang'] ) && self::get_source( (int) $row['id'] ) === (int) $row['source'] ) {
				$out[] = array(
					'id'        => (int) $row['id'],
					'source'    => (int) $row['source'],
					'lang'      => $row['lang'],
					'post_type' => $row['post_type'],
				);
			}
		}
		return $out;
	}

	/**
	 * 1.2 -> 1.3: every existing group gets a source, and its translations
	 * start up to date (nothing tells which ones were outdated before).
	 *
	 * @return int Number of groups processed.
	 */
	public static function migrate() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ids  = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s", WPM_Translations::META_GROUP ) );
		$done = array();
		$n    = 0;
		foreach ( array_map( 'intval', $ids ) as $id ) {
			if ( isset( $done[ $id ] ) ) {
				continue;
			}
			$group = WPM_Translations::get_post_translations( $id );
			foreach ( $group as $member ) {
				$done[ $member ] = true;
			}
			if ( count( $group ) >= 2 ) {
				self::on_group_written( 'post', $group );
				++$n;
			}
		}
		WPM_Translations::flush_stats();
		return $n;
	}
}
