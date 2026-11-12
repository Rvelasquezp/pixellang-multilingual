<?php
/**
 * Language and translation relations of posts and terms.
 *
 * Storage (since 1.1):
 * - `_wpm_language` (post meta / term meta): language code of the object.
 *   The name is unchanged since 1.0 because themes query it directly.
 * - `_wpm_translations` (post meta / term meta): the whole group, as
 *   array( lang => object_id ), stored on every member. Reading a relation
 *   therefore costs no extra query once the meta cache is primed, which
 *   WP_Query does for every listed post. Before 1.1 all groups lived in one
 *   autoloaded option that had to be scanned on every lookup.
 *
 * Invariants kept by link(): an object belongs to at most one group, a group
 * holds at most one object per language, and every member stores the same
 * group. Breaking these was the cause of the orphan groups found in 1.0 data.
 *
 * @package PixelLang_Multilingual
 */

defined( 'ABSPATH' ) || exit;

class WPM_Translations {

	const META_LANG  = '_wpm_language';
	const META_GROUP = '_wpm_translations';

	/**
	 * Hooks that keep relations consistent when content is deleted.
	 */
	public static function init() {
		add_action( 'before_delete_post', array( __CLASS__, 'on_delete_post' ) );
		add_action( 'pre_delete_term', array( __CLASS__, 'on_delete_term' ), 10, 1 );
		add_action( 'save_post', array( __CLASS__, 'on_save_post' ), 10, 2 );
		add_action( 'deleted_post', array( __CLASS__, 'flush_stats' ) );
	}

	/**
	 * Counters depend on post status: refresh them when a translatable post changes.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public static function on_save_post( $post_id, $post ) {
		if ( $post && WPM_Settings::is_translatable_post_type( $post->post_type ) ) {
			self::flush_stats();
		}
	}

	// ---------------------------------------------------------------------
	// Generic (post | term)
	// ---------------------------------------------------------------------

	/**
	 * Language of an object, or '' when unassigned or not an active language.
	 *
	 * @param string $type 'post' or 'term'.
	 * @param int    $id   Object ID.
	 * @return string
	 */
	public static function get_language( $type, $id ) {
		$lang = get_metadata( $type, (int) $id, self::META_LANG, true );
		return ( is_string( $lang ) && WPM_Language_Manager::instance()->is_valid( $lang ) ) ? $lang : '';
	}

	/**
	 * Assigns (or clears with '') the language of an object.
	 *
	 * Changing the language of an object that belongs to a group whose slot
	 * for the new language is taken detaches it from that group.
	 *
	 * @param string $type 'post' or 'term'.
	 * @param int    $id   Object ID.
	 * @param string $lang Language code or ''.
	 * @return bool
	 */
	public static function set_language( $type, $id, $lang ) {
		$id = (int) $id;
		if ( '' === $lang ) {
			self::unlink( $type, $id );
			delete_metadata( $type, $id, self::META_LANG );
			self::flush_stats();
			return true;
		}
		if ( ! WPM_Language_Manager::instance()->is_valid( $lang ) ) {
			return false;
		}
		$previous = self::get_language( $type, $id );
		if ( $previous !== $lang ) {
			$group = self::get_translations( $type, $id );
			unset( $group[ $previous ] );
			if ( isset( $group[ $lang ] ) && (int) $group[ $lang ] !== $id ) {
				self::unlink( $type, $id );
			} elseif ( count( $group ) ) {
				$group[ $lang ] = $id;
				update_metadata( $type, $id, self::META_LANG, $lang );
				self::write_group( $type, $group );
				self::flush_stats();
				return true;
			}
			update_metadata( $type, $id, self::META_LANG, $lang );
			self::flush_stats();
		}
		return true;
	}

	/**
	 * Translation group of an object: lang => id, the object included.
	 *
	 * @param string $type 'post' or 'term'.
	 * @param int    $id   Object ID.
	 * @return array<string,int>
	 */
	public static function get_translations( $type, $id ) {
		$id    = (int) $id;
		$lang  = self::get_language( $type, $id );
		$group = get_metadata( $type, $id, self::META_GROUP, true );
		$clean = array();
		if ( is_array( $group ) ) {
			$manager = WPM_Language_Manager::instance();
			foreach ( $group as $l => $member ) {
				if ( $manager->is_valid( $l ) && absint( $member ) ) {
					$clean[ $l ] = (int) $member;
				}
			}
		}
		// The object's own language always points to itself.
		if ( $lang ) {
			if ( isset( $clean[ $lang ] ) && $clean[ $lang ] !== $id ) {
				return array( $lang => $id ); // Inconsistent group: ignore it.
			}
			$clean[ $lang ] = $id;
		}
		return $clean;
	}

	/**
	 * ID of the translation of an object in a language, or 0.
	 *
	 * @param string $type 'post' or 'term'.
	 * @param int    $id   Object ID.
	 * @param string $lang Language code.
	 * @return int
	 */
	public static function get_translation( $type, $id, $lang ) {
		$group = self::get_translations( $type, $id );
		return isset( $group[ $lang ] ) ? (int) $group[ $lang ] : 0;
	}

	/**
	 * Links objects as translations of each other.
	 *
	 * Members are removed from any group they belonged to, and each previous
	 * group is rewritten without them. Callers validate permissions and types.
	 *
	 * @param string $type  'post' or 'term'.
	 * @param array  $group lang => id.
	 * @return array The stored group.
	 */
	public static function link( $type, array $group ) {
		$manager = WPM_Language_Manager::instance();
		$clean   = array();
		foreach ( $group as $lang => $id ) {
			$id = absint( $id );
			if ( $id && $manager->is_valid( $lang ) && ! in_array( $id, $clean, true ) ) {
				$clean[ $lang ] = $id;
			}
		}

		// Detach every member from its previous group.
		foreach ( $clean as $id ) {
			$old = self::get_translations( $type, $id );
			$old = array_diff( $old, $clean );
			if ( $old ) {
				self::write_group( $type, $old );
			}
		}

		foreach ( $clean as $lang => $id ) {
			if ( self::get_language( $type, $id ) !== $lang ) {
				update_metadata( $type, $id, self::META_LANG, $lang );
			}
		}
		self::write_group( $type, $clean );
		self::flush_stats();
		return $clean;
	}

	/**
	 * Removes an object from its group (the other members stay linked).
	 *
	 * @param string $type 'post' or 'term'.
	 * @param int    $id   Object ID.
	 */
	public static function unlink( $type, $id ) {
		$id    = (int) $id;
		$group = self::get_translations( $type, $id );
		delete_metadata( $type, $id, self::META_GROUP );
		$rest = array_diff( $group, array( $id ) );
		if ( $rest ) {
			self::write_group( $type, $rest );
		}
		self::flush_stats();
	}

	/**
	 * Writes a group on all its members; a group of one is not a relation.
	 *
	 * @param string $type  'post' or 'term'.
	 * @param array  $group lang => id.
	 */
	private static function write_group( $type, array $group ) {
		if ( count( $group ) < 2 ) {
			foreach ( $group as $id ) {
				delete_metadata( $type, (int) $id, self::META_GROUP );
			}
			return;
		}
		foreach ( $group as $id ) {
			update_metadata( $type, (int) $id, self::META_GROUP, $group );
		}
	}

	// ---------------------------------------------------------------------
	// Posts
	// ---------------------------------------------------------------------

	public static function get_post_language( $post_id ) {
		return self::get_language( 'post', $post_id );
	}

	public static function get_post_translations( $post_id ) {
		return self::get_translations( 'post', $post_id );
	}

	public static function get_post_translation( $post_id, $lang ) {
		return self::get_translation( 'post', $post_id, $lang );
	}

	/**
	 * Translation that a visitor may see: published (or readable private).
	 *
	 * @param int    $post_id Post ID.
	 * @param string $lang    Language code.
	 * @return int
	 */
	public static function get_public_post_translation( $post_id, $lang ) {
		$id = self::get_post_translation( $post_id, $lang );
		if ( ! $id ) {
			return 0;
		}
		$status = get_post_status( $id );
		if ( 'publish' === $status || ( 'private' === $status && current_user_can( 'read_post', $id ) ) ) {
			return $id;
		}
		return 0;
	}

	public static function on_delete_post( $post_id ) {
		if ( get_post_meta( $post_id, self::META_GROUP, true ) ) {
			self::unlink( 'post', $post_id );
		}
	}

	public static function on_delete_term( $term_id ) {
		if ( get_term_meta( $term_id, self::META_GROUP, true ) ) {
			self::unlink( 'term', $term_id );
		}
	}

	/**
	 * Creates a draft copy of a post in another language and links it.
	 *
	 * Copies content, excerpt, template, featured image, custom fields
	 * (except internal ones) and terms. Terms of translatable taxonomies are
	 * replaced by their translation when it exists. The parent becomes the
	 * translation of the source parent when there is one.
	 *
	 * @param int    $source_id Source post ID.
	 * @param string $lang      Target language.
	 * @return int|WP_Error New post ID, or the existing translation ID.
	 */
	public static function create_post_translation( $source_id, $lang ) {
		$source = get_post( $source_id );
		if ( ! $source || ! WPM_Settings::is_translatable_post_type( $source->post_type ) ) {
			return new WP_Error( 'wpm_invalid_source', __( 'This content cannot be translated.', 'pixellang-multilingual' ) );
		}
		if ( ! WPM_Language_Manager::instance()->is_valid( $lang ) ) {
			return new WP_Error( 'wpm_invalid_lang', __( 'Unknown language.', 'pixellang-multilingual' ) );
		}

		$source_lang = self::get_post_language( $source->ID );
		if ( ! $source_lang ) {
			$source_lang = WPM_Language_Manager::instance()->get_default();
			self::set_language( 'post', $source->ID, $source_lang );
		}
		if ( $source_lang === $lang ) {
			return new WP_Error( 'wpm_same_lang', __( 'The content is already in this language.', 'pixellang-multilingual' ) );
		}

		$existing = self::get_post_translation( $source->ID, $lang );
		if ( $existing && get_post( $existing ) ) {
			return $existing;
		}

		$parent = 0;
		if ( $source->post_parent ) {
			$parent = self::get_post_translation( $source->post_parent, $lang );
		}

		$new_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'      => $source->post_type,
					'post_status'    => 'draft',
					'post_title'     => $source->post_title,
					'post_content'   => $source->post_content,
					'post_excerpt'   => $source->post_excerpt,
					'post_name'      => $source->post_name ? $source->post_name . '-' . $lang : '',
					'post_parent'    => $parent,
					'menu_order'     => $source->menu_order,
					'comment_status' => $source->comment_status,
					'ping_status'    => $source->ping_status,
					'post_password'  => $source->post_password,
					'post_author'    => get_current_user_id(),
				)
			),
			true
		);
		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}

		self::copy_post_meta( $source->ID, $new_id );
		self::copy_post_terms( $source, $new_id, $lang );

		$group          = self::get_post_translations( $source->ID );
		$group[ $lang ] = $new_id;
		self::link( 'post', $group );

		/**
		 * Fires after a translation draft has been created.
		 *
		 * @param int    $new_id    New post ID.
		 * @param int    $source_id Source post ID.
		 * @param string $lang      Language of the new post.
		 */
		do_action( 'wpm_translation_created', $new_id, $source->ID, $lang );
		return $new_id;
	}

	/**
	 * Copies custom fields, skipping internal and plugin-managed keys.
	 *
	 * @param int $from Source post ID.
	 * @param int $to   Target post ID.
	 */
	private static function copy_post_meta( $from, $to ) {
		$skip = array( '_edit_lock', '_edit_last', '_wp_old_slug', '_wp_old_date', '_wp_trash_meta_status', '_wp_trash_meta_time', self::META_LANG, self::META_GROUP );
		/**
		 * Filters the meta keys not copied to a new translation.
		 *
		 * @param string[] $skip Meta keys.
		 */
		$skip = apply_filters( 'wpm_translation_skip_meta', $skip );

		foreach ( get_post_meta( $from ) as $key => $values ) {
			if ( in_array( $key, $skip, true ) ) {
				continue;
			}
			foreach ( $values as $value ) {
				add_post_meta( $to, $key, wp_slash( maybe_unserialize( $value ) ) );
			}
		}
	}

	/**
	 * Copies terms, using term translations when available.
	 *
	 * @param WP_Post $source Source post.
	 * @param int     $to     Target post ID.
	 * @param string  $lang   Target language.
	 */
	private static function copy_post_terms( $source, $to, $lang ) {
		foreach ( get_object_taxonomies( $source->post_type ) as $taxonomy ) {
			$terms = wp_get_object_terms( $source->ID, $taxonomy, array( 'fields' => 'ids' ) );
			if ( is_wp_error( $terms ) || ! $terms ) {
				continue;
			}
			if ( WPM_Settings::is_translatable_taxonomy( $taxonomy ) ) {
				$terms = array_map(
					function ( $term_id ) use ( $lang ) {
						$translated = WPM_Translations::get_translation( 'term', $term_id, $lang );
						return $translated ? $translated : $term_id;
					},
					$terms
				);
			}
			wp_set_object_terms( $to, array_map( 'intval', $terms ), $taxonomy );
		}
	}

	// ---------------------------------------------------------------------
	// Stats (overview screen)
	// ---------------------------------------------------------------------

	/**
	 * Counts per post type and language, cached until content changes.
	 *
	 * @return array{by_lang: array, unassigned: array, translated: array, totals: array}
	 */
	public static function get_stats() {
		$stats = get_transient( 'wpm_stats' );
		if ( is_array( $stats ) ) {
			return $stats;
		}
		global $wpdb;
		$types = WPM_Settings::get_post_types();
		$stats = array(
			'by_lang'    => array(),
			'unassigned' => array(),
			'translated' => array(),
			'totals'     => array(),
		);
		if ( ! $types ) {
			return $stats;
		}
		$in       = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$statuses = "'publish','draft','pending','future','private'";

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.post_type, m.meta_value AS lang, COUNT(*) AS total
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s
				WHERE p.post_type IN ($in) AND p.post_status IN ($statuses)
				GROUP BY p.post_type, m.meta_value",
				array_merge( array( self::META_LANG ), $types )
			)
		);
		$linked = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.post_type, l.meta_value AS lang, COUNT(*) AS total
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} l ON l.post_id = p.ID AND l.meta_key = %s
				INNER JOIN {$wpdb->postmeta} g ON g.post_id = p.ID AND g.meta_key = %s
				WHERE p.post_type IN ($in) AND p.post_status IN ($statuses)
				GROUP BY p.post_type, l.meta_value",
				array_merge( array( self::META_LANG, self::META_GROUP ), $types )
			)
		);
		// phpcs:enable

		$manager = WPM_Language_Manager::instance();
		foreach ( (array) $rows as $row ) {
			$count = (int) $row->total;
			$stats['totals'][ $row->post_type ] = ( isset( $stats['totals'][ $row->post_type ] ) ? $stats['totals'][ $row->post_type ] : 0 ) + $count;
			if ( $row->lang && $manager->is_valid( $row->lang ) ) {
				$stats['by_lang'][ $row->lang ][ $row->post_type ] = $count;
			} else {
				$stats['unassigned'][ $row->post_type ] = ( isset( $stats['unassigned'][ $row->post_type ] ) ? $stats['unassigned'][ $row->post_type ] : 0 ) + $count;
			}
		}
		foreach ( (array) $linked as $row ) {
			if ( $manager->is_valid( $row->lang ) ) {
				$stats['translated'][ $row->lang ][ $row->post_type ] = (int) $row->total;
			}
		}

		set_transient( 'wpm_stats', $stats, HOUR_IN_SECONDS );
		return $stats;
	}

	/**
	 * Invalidates the overview counters.
	 */
	public static function flush_stats() {
		delete_transient( 'wpm_stats' );
	}
}
