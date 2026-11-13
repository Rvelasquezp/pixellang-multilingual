<?php
/**
 * String Translation: translations of texts that come from themes, plugins
 * and templates instead of posts.
 *
 * Storage: two tables.
 * - {prefix}wpm_strings: one row per string. Its identity (string_key) is a
 *   hash of text domain + context + original, so the same text used by two
 *   components or in two contexts stays two strings.
 * - {prefix}wpm_string_translations: one row per string and language.
 *
 * Resolution: WordPress gettext filters on the front end, for the language
 * resolved by WPM_Language_Manager (no second language system). A string
 * translated here wins over the component's own .mo translation; strings not
 * translated here keep whatever WordPress returned. Domains without any
 * translation return immediately, so the filters cost almost nothing.
 *
 * Literal strings (source "literal") are texts written directly in HTML.
 * They are replaced in the text nodes of the final page, never in
 * attributes, scripts or styles, and only when at least one is translated.
 *
 * @package PixelLang_Multilingual
 */

defined( 'ABSPATH' ) || exit;

class WPM_Strings {

	const LITERAL_DOMAIN = 'wpm-literal';
	const DOMAINS_OPTION = 'wpm_string_domains';
	const DISCOVERY      = 'wpm_string_discovery';
	const CACHE_GROUP    = 'wpm_strings';

	/**
	 * Domains that have at least one translation (autoloaded option).
	 *
	 * @var array|null
	 */
	private static $domains = null;

	/**
	 * Per-language maps loaded during the request.
	 *
	 * @var array
	 */
	private static $maps = array();

	/**
	 * Strings met while discovery mode is on, written at shutdown.
	 *
	 * @var array
	 */
	private static $discovered = array();

	/**
	 * Re-entrancy guard (our own code may call gettext).
	 *
	 * @var bool
	 */
	private static $resolving = false;

	/**
	 * Discovery state, computed once per request.
	 *
	 * @var bool
	 */
	private static $discovering = false;

	/**
	 * Capability needed to manage strings (filterable, e.g. for editors).
	 *
	 * @return string
	 */
	public static function capability() {
		return apply_filters( 'wpm_strings_capability', 'manage_options' );
	}

	/**
	 * Active languages without translation (the original language excluded).
	 *
	 * @param object $string String row with translations.
	 * @return string[]
	 */
	public static function missing_langs( $string ) {
		$missing = array();
		foreach ( WPM_Language_Manager::instance()->get_slugs() as $lang ) {
			if ( $lang !== $string->original_lang && empty( $string->translations[ $lang ] ) ) {
				$missing[] = $lang;
			}
		}
		return $missing;
	}

	public static function init() {
		add_action( 'init', array( __CLASS__, 'boot_front' ), 2 );
	}

	/**
	 * Hooks the resolution on front-end requests only (same rule as the locale).
	 */
	public static function boot_front() {
		if ( ! WPM_Language_Manager::instance()->is_front_request() ) {
			return;
		}
		self::$discovering = self::is_discovering();
		if ( self::get_domains() || self::$discovering ) {
			add_filter( 'gettext', array( __CLASS__, 'filter_gettext' ), 20, 3 );
			add_filter( 'gettext_with_context', array( __CLASS__, 'filter_gettext_with_context' ), 20, 4 );
			add_filter( 'ngettext', array( __CLASS__, 'filter_ngettext' ), 20, 5 );
			add_filter( 'ngettext_with_context', array( __CLASS__, 'filter_ngettext_with_context' ), 20, 6 );
		}
		if ( isset( self::get_domains()[ self::LITERAL_DOMAIN ] ) && ! wp_doing_ajax() ) {
			add_action( 'template_redirect', array( __CLASS__, 'start_literal_buffer' ), 0 );
		}
		if ( self::$discovering ) {
			add_action( 'shutdown', array( __CLASS__, 'flush_discovered' ) );
		}
	}

	// ---------------------------------------------------------------------
	// Schema
	// ---------------------------------------------------------------------

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'wpm_strings';
	}

	public static function translations_table() {
		global $wpdb;
		return $wpdb->prefix . 'wpm_string_translations';
	}

	/**
	 * Creates or updates the tables (dbDelta never drops data).
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$strings = self::table();
		$trans   = self::translations_table();

		dbDelta(
			"CREATE TABLE {$strings} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				string_key char(32) NOT NULL,
				domain varchar(191) NOT NULL DEFAULT '',
				context varchar(191) NOT NULL DEFAULT '',
				original longtext NOT NULL,
				original_plural longtext NULL,
				original_lang varchar(20) NOT NULL DEFAULT 'en',
				source_type varchar(20) NOT NULL DEFAULT 'manual',
				source_name varchar(191) NOT NULL DEFAULT '',
				locations text NULL,
				created datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				modified datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (id),
				UNIQUE KEY string_key (string_key),
				KEY domain (domain),
				KEY source (source_type,source_name)
			) {$charset};"
		);
		dbDelta(
			"CREATE TABLE {$trans} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				string_id bigint(20) unsigned NOT NULL,
				lang varchar(20) NOT NULL,
				value longtext NOT NULL,
				value_plural longtext NULL,
				modified datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				modified_by bigint(20) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (id),
				UNIQUE KEY string_lang (string_id,lang),
				KEY lang (lang)
			) {$charset};"
		);
	}

	public static function tables_exist() {
		global $wpdb;
		return self::table() === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', self::table() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	// ---------------------------------------------------------------------
	// Registry
	// ---------------------------------------------------------------------

	/**
	 * Stable identity of a string.
	 *
	 * @param string $domain   Text domain.
	 * @param string $context  gettext context ('' when none).
	 * @param string $original Original text.
	 * @return string
	 */
	public static function key( $domain, $context, $original ) {
		return md5( $domain . "\x04" . $context . "\x04" . $original );
	}

	/**
	 * Registers a string, or refreshes it when it exists (locations merged).
	 *
	 * @param array $data original, domain, context, original_plural,
	 *                    source_type, source_name, location, original_lang.
	 * @return int|WP_Error String ID.
	 */
	public static function register( array $data ) {
		global $wpdb;
		$original = isset( $data['original'] ) ? (string) $data['original'] : '';
		if ( '' === trim( $original ) ) {
			return new WP_Error( 'wpm_empty_string', __( 'The original text is empty.', 'pixellang-multilingual' ) );
		}
		$domain  = isset( $data['domain'] ) ? substr( sanitize_text_field( $data['domain'] ), 0, 191 ) : '';
		$context = isset( $data['context'] ) ? substr( sanitize_text_field( $data['context'] ), 0, 191 ) : '';
		$type    = isset( $data['source_type'] ) && in_array( $data['source_type'], array( 'theme', 'plugin', 'core', 'manual', 'literal' ), true ) ? $data['source_type'] : 'manual';
		if ( 'literal' === $type ) {
			$domain  = self::LITERAL_DOMAIN;
			$context = '';
		}
		$key      = self::key( $domain, $context, $original );
		$now      = current_time( 'mysql' );
		$location = isset( $data['location'] ) ? (string) $data['location'] : '';
		$existing = $wpdb->get_row( $wpdb->prepare( 'SELECT id, locations FROM ' . self::table() . ' WHERE string_key = %s', $key ) ); // phpcs:ignore WordPress.DB

		if ( $existing ) {
			if ( $location ) {
				$locations = array_filter( (array) json_decode( (string) $existing->locations, true ) );
				if ( ! in_array( $location, $locations, true ) && count( $locations ) < 20 ) {
					$locations[] = $location;
					$wpdb->update( self::table(), array( 'locations' => wp_json_encode( array_values( $locations ) ) ), array( 'id' => $existing->id ) ); // phpcs:ignore WordPress.DB
				}
			}
			return (int) $existing->id;
		}

		$lang = isset( $data['original_lang'] ) ? sanitize_key( $data['original_lang'] ) : WPM_Settings::get( 'strings_source_lang' );
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array(
				'string_key'      => $key,
				'domain'          => $domain,
				'context'         => $context,
				'original'        => $original,
				'original_plural' => isset( $data['original_plural'] ) && '' !== $data['original_plural'] ? (string) $data['original_plural'] : null,
				'original_lang'   => $lang ? $lang : 'en',
				'source_type'     => $type,
				'source_name'     => isset( $data['source_name'] ) ? substr( sanitize_text_field( $data['source_name'] ), 0, 191 ) : '',
				'locations'       => $location ? wp_json_encode( array( $location ) ) : null,
				'created'         => $now,
				'modified'        => $now,
			)
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * @param int $id String ID.
	 * @return object|null Row with a `translations` array (lang => row).
	 */
	public static function get( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ) ); // phpcs:ignore WordPress.DB
		if ( $row ) {
			$row->translations = self::get_translations( array( (int) $id ) )[ (int) $id ] ?? array();
		}
		return $row;
	}

	/**
	 * Translations of several strings: string_id => lang => row.
	 *
	 * @param int[] $ids String IDs.
	 * @return array
	 */
	public static function get_translations( array $ids ) {
		global $wpdb;
		$ids = array_filter( array_map( 'absint', $ids ) );
		$out = array();
		if ( ! $ids ) {
			return $out;
		}
		$in   = implode( ',', $ids );
		$rows = $wpdb->get_results( 'SELECT string_id, lang, value, value_plural, modified FROM ' . self::translations_table() . " WHERE string_id IN ($in)" ); // phpcs:ignore WordPress.DB
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row->string_id ][ $row->lang ] = $row;
		}
		return $out;
	}

	/**
	 * Saves (or removes, with an empty value) one translation.
	 *
	 * HTML is allowed like in post content (wp_kses_post), because originals
	 * may contain markup and themes often echo __() without escaping.
	 *
	 * @param int    $id     String ID.
	 * @param string $lang   Language.
	 * @param string $value  Translation.
	 * @param string $plural Plural translation.
	 * @return bool|WP_Error
	 */
	public static function save_translation( $id, $lang, $value, $plural = '' ) {
		global $wpdb;
		$string = self::get( $id );
		if ( ! $string ) {
			return new WP_Error( 'wpm_unknown_string', __( 'Unknown string.', 'pixellang-multilingual' ) );
		}
		if ( ! WPM_Language_Manager::instance()->is_valid( $lang ) ) {
			return new WP_Error( 'wpm_invalid_lang', __( 'Unknown language.', 'pixellang-multilingual' ) );
		}
		$value  = trim( wp_kses_post( (string) $value ) );
		$plural = trim( wp_kses_post( (string) $plural ) );

		if ( '' === $value ) {
			$wpdb->delete( self::translations_table(), array( 'string_id' => $id, 'lang' => $lang ) ); // phpcs:ignore WordPress.DB
		} else {
			$wpdb->query( // phpcs:ignore WordPress.DB
				$wpdb->prepare(
					'INSERT INTO ' . self::translations_table() . ' (string_id, lang, value, value_plural, modified, modified_by)
					VALUES (%d, %s, %s, %s, %s, %d)
					ON DUPLICATE KEY UPDATE value = VALUES(value), value_plural = VALUES(value_plural), modified = VALUES(modified), modified_by = VALUES(modified_by)',
					$id,
					$lang,
					$value,
					$plural,
					current_time( 'mysql' ),
					get_current_user_id()
				)
			);
		}
		$wpdb->update( self::table(), array( 'modified' => current_time( 'mysql' ) ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB
		self::flush_cache();
		return true;
	}

	/**
	 * Deletes strings and their translations.
	 *
	 * @param int[] $ids String IDs.
	 * @return int Number of strings deleted.
	 */
	public static function delete( array $ids ) {
		global $wpdb;
		$ids = array_filter( array_map( 'absint', $ids ) );
		if ( ! $ids ) {
			return 0;
		}
		$in = implode( ',', $ids );
		$wpdb->query( 'DELETE FROM ' . self::translations_table() . " WHERE string_id IN ($in)" ); // phpcs:ignore WordPress.DB
		$done = (int) $wpdb->query( 'DELETE FROM ' . self::table() . " WHERE id IN ($in)" ); // phpcs:ignore WordPress.DB
		self::flush_cache();
		return $done;
	}

	/**
	 * Filtered, paginated list for the admin table.
	 *
	 * @param array $args search, source, domain, missing (lang), per_page, page, orderby, order.
	 * @return array{items: array, total: int}
	 */
	public static function query( array $args ) {
		global $wpdb;
		$s     = self::table();
		$t     = self::translations_table();
		$where = array( '1=1' );
		$vals  = array();

		if ( ! empty( $args['search'] ) ) {
			$like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[] = "(s.original LIKE %s OR s.id IN (SELECT string_id FROM {$t} WHERE value LIKE %s))";
			$vals[]  = $like;
			$vals[]  = $like;
		}
		if ( ! empty( $args['source'] ) && false !== strpos( $args['source'], ':' ) ) {
			list( $type, $name ) = explode( ':', $args['source'], 2 );
			$where[]             = 's.source_type = %s AND s.source_name = %s';
			$vals[]              = $type;
			$vals[]              = $name;
		}
		if ( isset( $args['domain'] ) && '' !== $args['domain'] ) {
			$where[] = 's.domain = %s';
			$vals[]  = $args['domain'];
		}
		if ( ! empty( $args['missing'] ) ) {
			$where[] = "s.original_lang <> %s AND NOT EXISTS (SELECT 1 FROM {$t} x WHERE x.string_id = s.id AND x.lang = %s)";
			$vals[]  = $args['missing'];
			$vals[]  = $args['missing'];
		}
		$sql_where = implode( ' AND ', $where );
		$orderby   = isset( $args['orderby'] ) && in_array( $args['orderby'], array( 'original', 'domain', 'modified' ), true ) ? $args['orderby'] : 'modified';
		$order     = isset( $args['order'] ) && 'asc' === strtolower( $args['order'] ) ? 'ASC' : 'DESC';
		$per_page  = max( 1, (int) ( $args['per_page'] ?? 30 ) );
		$offset    = max( 0, ( (int) ( $args['page'] ?? 1 ) - 1 ) * $per_page );

		// phpcs:disable WordPress.DB
		$count_sql = "SELECT COUNT(*) FROM {$s} s WHERE {$sql_where}";
		$total     = (int) ( $vals ? $wpdb->get_var( $wpdb->prepare( $count_sql, $vals ) ) : $wpdb->get_var( $count_sql ) );
		$list_sql  = "SELECT s.* FROM {$s} s WHERE {$sql_where} ORDER BY s.{$orderby} {$order}, s.id DESC LIMIT %d OFFSET %d";
		$items     = $wpdb->get_results( $wpdb->prepare( $list_sql, array_merge( $vals, array( $per_page, $offset ) ) ) );
		// phpcs:enable

		$translations = self::get_translations( wp_list_pluck( (array) $items, 'id' ) );
		foreach ( (array) $items as $item ) {
			$item->translations = $translations[ (int) $item->id ] ?? array();
		}
		return array(
			'items' => (array) $items,
			'total' => $total,
		);
	}

	/**
	 * Components that registered strings: "type:name" => label.
	 *
	 * @return array
	 */
	public static function sources() {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT source_type, source_name, COUNT(*) AS total FROM ' . self::table() . ' GROUP BY source_type, source_name ORDER BY source_type, source_name' ); // phpcs:ignore WordPress.DB
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$out[ $row->source_type . ':' . $row->source_name ] = array(
				'type'  => $row->source_type,
				'name'  => $row->source_name,
				'total' => (int) $row->total,
			);
		}
		return $out;
	}

	/**
	 * Text domains with their number of strings.
	 *
	 * @return array domain => total
	 */
	public static function domain_counts() {
		global $wpdb;
		return wp_list_pluck( (array) $wpdb->get_results( 'SELECT domain, COUNT(*) AS total FROM ' . self::table() . ' GROUP BY domain ORDER BY domain', OBJECT_K ), 'total' ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Totals for the screen header: all, and missing per language.
	 *
	 * @return array
	 */
	public static function stats() {
		global $wpdb;
		$s     = self::table();
		$t     = self::translations_table();
		$stats = array(
			'total'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$s}" ), // phpcs:ignore WordPress.DB
			'missing' => array(),
		);
		foreach ( WPM_Language_Manager::instance()->get_slugs() as $lang ) {
			$stats['missing'][ $lang ] = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB
				$wpdb->prepare( "SELECT COUNT(*) FROM {$s} s WHERE s.original_lang <> %s AND NOT EXISTS (SELECT 1 FROM {$t} x WHERE x.string_id = s.id AND x.lang = %s)", $lang, $lang ) // phpcs:ignore WordPress.DB
			);
		}
		return $stats;
	}

	// ---------------------------------------------------------------------
	// Resolution
	// ---------------------------------------------------------------------

	/**
	 * Domains that have translations (cheap pre-check for the filters).
	 *
	 * @return array domain => true
	 */
	public static function get_domains() {
		if ( null === self::$domains ) {
			$domains       = get_option( self::DOMAINS_OPTION, array() );
			self::$domains = is_array( $domains ) ? $domains : array();
		}
		return self::$domains;
	}

	/**
	 * Translations of one language: string_key => [ value, plural ].
	 *
	 * @param string $lang Language.
	 * @return array
	 */
	public static function get_map( $lang ) {
		if ( isset( self::$maps[ $lang ] ) ) {
			return self::$maps[ $lang ];
		}
		$cache_key = 'map_' . $lang;
		$map       = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false === $map ) {
			$map = get_transient( 'wpm_strings_map_' . $lang );
		}
		if ( ! is_array( $map ) ) {
			global $wpdb;
			$map  = array();
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB
				$wpdb->prepare( 'SELECT s.string_key, t.value, t.value_plural FROM ' . self::translations_table() . ' t INNER JOIN ' . self::table() . ' s ON s.id = t.string_id WHERE t.lang = %s', $lang ) // phpcs:ignore WordPress.DB
			);
			foreach ( (array) $rows as $row ) {
				$map[ $row->string_key ] = array( $row->value, (string) $row->value_plural );
			}
			set_transient( 'wpm_strings_map_' . $lang, $map, DAY_IN_SECONDS );
		}
		wp_cache_set( $cache_key, $map, self::CACHE_GROUP );
		self::$maps[ $lang ] = $map;
		return $map;
	}

	/**
	 * Clears caches and rebuilds the list of translated domains.
	 */
	public static function flush_cache() {
		global $wpdb;
		foreach ( WPM_Language_Manager::instance()->get_slugs() as $lang ) {
			delete_transient( 'wpm_strings_map_' . $lang );
			wp_cache_delete( 'map_' . $lang, self::CACHE_GROUP );
		}
		self::$maps = array();
		$domains    = $wpdb->get_col( 'SELECT DISTINCT s.domain FROM ' . self::table() . ' s INNER JOIN ' . self::translations_table() . ' t ON t.string_id = s.id' ); // phpcs:ignore WordPress.DB
		self::$domains = array_fill_keys( (array) $domains, true );
		update_option( self::DOMAINS_OPTION, self::$domains, true );
	}

	/**
	 * Translation for the current language, or null when none is stored.
	 *
	 * @param string   $text    Original.
	 * @param string   $context Context.
	 * @param string   $domain  Text domain.
	 * @param int|null $number  Count for plurals.
	 * @return string|null
	 */
	public static function lookup( $text, $context, $domain, $number = null ) {
		$lang = WPM_Language_Manager::instance()->get_current();
		$map  = self::get_map( $lang );
		$key  = self::key( $domain, $context, $text );
		if ( ! isset( $map[ $key ] ) ) {
			return null;
		}
		list( $single, $plural ) = $map[ $key ];
		if ( null !== $number && '' !== $plural && ! self::is_singular( $number, $lang ) ) {
			return $plural;
		}
		return $single;
	}

	/**
	 * Plural rule: French treats 0 and 1 as singular, English only 1.
	 *
	 * @param int    $number Count.
	 * @param string $lang   Language.
	 * @return bool
	 */
	private static function is_singular( $number, $lang ) {
		$locale = WPM_Language_Manager::instance()->get_locale( $lang );
		return 0 === strpos( (string) $locale, 'fr' ) ? $number <= 1 : 1 === (int) $number;
	}

	private static function resolve( $translation, $text, $context, $domain, $number = null, $plural = null ) {
		if ( self::$resolving ) {
			return $translation;
		}
		if ( self::$discovering ) {
			self::remember( $text, $context, $domain, $plural );
		}
		if ( ! isset( self::get_domains()[ $domain ] ) ) {
			return $translation;
		}
		self::$resolving = true;
		$found           = self::lookup( $text, $context, $domain, $number );
		self::$resolving = false;
		return null === $found ? $translation : $found;
	}

	public static function filter_gettext( $translation, $text, $domain ) {
		return self::resolve( $translation, $text, '', $domain );
	}

	public static function filter_gettext_with_context( $translation, $text, $context, $domain ) {
		return self::resolve( $translation, $text, $context, $domain );
	}

	public static function filter_ngettext( $translation, $single, $plural, $number, $domain ) {
		return self::resolve( $translation, $single, '', $domain, (int) $number, $plural );
	}

	public static function filter_ngettext_with_context( $translation, $single, $plural, $number, $context, $domain ) {
		return self::resolve( $translation, $single, $context, $domain, (int) $number, $plural );
	}

	// ---------------------------------------------------------------------
	// Literal strings (text written directly in HTML)
	// ---------------------------------------------------------------------

	public static function start_literal_buffer() {
		if ( is_feed() || is_admin() ) {
			return;
		}
		$lang = WPM_Language_Manager::instance()->get_current();
		if ( ! self::literal_pairs( $lang ) ) {
			return;
		}
		ob_start( array( __CLASS__, 'replace_literals' ) );
	}

	/**
	 * Original => translation pairs of literal strings for a language.
	 *
	 * @param string $lang Language.
	 * @return array
	 */
	public static function literal_pairs( $lang ) {
		global $wpdb;
		$cache = wp_cache_get( 'literal_' . $lang, self::CACHE_GROUP );
		if ( is_array( $cache ) ) {
			return $cache;
		}
		$pairs = array();
		$map   = self::get_map( $lang );
		$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT string_key, original FROM ' . self::table() . ' WHERE domain = %s', self::LITERAL_DOMAIN ) ); // phpcs:ignore WordPress.DB
		foreach ( (array) $rows as $row ) {
			if ( isset( $map[ $row->string_key ] ) ) {
				$pairs[ $row->original ] = $map[ $row->string_key ][0];
			}
		}
		// Longest first so a short text never breaks a longer one.
		uksort(
			$pairs,
			function ( $a, $b ) {
				return strlen( $b ) - strlen( $a );
			}
		);
		wp_cache_set( 'literal_' . $lang, $pairs, self::CACHE_GROUP );
		return $pairs;
	}

	/**
	 * Replaces literal strings in text nodes only. Scripts, styles,
	 * textareas, comments and attributes are left untouched.
	 *
	 * @param string $html Page HTML.
	 * @return string
	 */
	public static function replace_literals( $html ) {
		$pairs = self::literal_pairs( WPM_Language_Manager::instance()->get_current() );
		if ( ! $pairs || '' === $html ) {
			return $html;
		}
		$parts = preg_split( '#(<script\b.*?</script>|<style\b.*?</style>|<textarea\b.*?</textarea>|<!--.*?-->)#is', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( ! is_array( $parts ) ) {
			return $html;
		}
		foreach ( $parts as $i => $part ) {
			if ( 1 === $i % 2 ) {
				continue; // Captured protected block.
			}
			$parts[ $i ] = preg_replace_callback(
				'/>([^<>]+)</u',
				function ( $m ) use ( $pairs ) {
					$text = $m[1];
					foreach ( $pairs as $from => $to ) {
						if ( false !== strpos( $text, $from ) ) {
							$text = str_replace( $from, $to, $text );
						}
					}
					return '>' . $text . '<';
				},
				$part
			);
		}
		return implode( '', $parts );
	}

	// ---------------------------------------------------------------------
	// Discovery mode
	// ---------------------------------------------------------------------

	/**
	 * Discovery records strings used on pages browsed by an administrator.
	 *
	 * @return bool
	 */
	public static function is_discovering() {
		$until = (int) get_option( self::DISCOVERY, 0 );
		return $until > time() && is_user_logged_in() && current_user_can( 'manage_options' );
	}

	/**
	 * Turns discovery on for a while (minutes) or off (0).
	 *
	 * @param int $minutes Duration.
	 */
	public static function set_discovery( $minutes ) {
		if ( $minutes > 0 ) {
			update_option( self::DISCOVERY, time() + $minutes * MINUTE_IN_SECONDS, false );
		} else {
			delete_option( self::DISCOVERY );
		}
	}

	/**
	 * Domains never recorded: WordPress core and this plugin.
	 *
	 * @return string[]
	 */
	private static function ignored_domains() {
		return apply_filters( 'wpm_strings_ignored_domains', array( 'default', 'pixellang-multilingual', 'wp-multilingual' ) );
	}

	private static function remember( $text, $context, $domain, $plural ) {
		if ( '' === trim( (string) $text ) || in_array( $domain, self::ignored_domains(), true ) || count( self::$discovered ) > 2000 ) {
			return;
		}
		$key = self::key( $domain, $context, $text );
		if ( isset( self::$discovered[ $key ] ) ) {
			return;
		}
		self::$discovered[ $key ] = array(
			'original'        => $text,
			'original_plural' => (string) $plural,
			'context'         => $context,
			'domain'          => $domain,
		) + self::guess_source();
	}

	/**
	 * Component that called gettext, from a short backtrace (discovery only).
	 *
	 * @return array source_type, source_name, location.
	 */
	private static function guess_source() {
		$trace       = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
		$theme_root  = wp_normalize_path( get_theme_root() );
		$plugin_root = wp_normalize_path( WP_PLUGIN_DIR );
		foreach ( $trace as $frame ) {
			if ( empty( $frame['file'] ) ) {
				continue;
			}
			$file = wp_normalize_path( $frame['file'] );
			if ( 0 === strpos( $file, $theme_root ) ) {
				return self::source_from_path( 'theme', ltrim( substr( $file, strlen( $theme_root ) ), '/' ), (int) $frame['line'] );
			}
			if ( 0 === strpos( $file, $plugin_root ) && false === strpos( $file, '/' . basename( WPM_DIR ) . '/' ) ) {
				return self::source_from_path( 'plugin', ltrim( substr( $file, strlen( $plugin_root ) ), '/' ), (int) $frame['line'] );
			}
		}
		return array(
			'source_type' => 'core',
			'source_name' => 'wordpress',
			'location'    => '',
		);
	}

	/**
	 * Source of a file path relative to the themes or plugins directory.
	 * Locations are relative to the component, like the scanner's.
	 *
	 * @param string $type theme|plugin.
	 * @param string $rel  "slug/path/file.php".
	 * @param int    $line Line.
	 * @return array
	 */
	private static function source_from_path( $type, $rel, $line ) {
		$parts = explode( '/', $rel, 2 );
		return array(
			'source_type' => $type,
			'source_name' => $parts[0],
			'location'    => ( isset( $parts[1] ) ? $parts[1] : $rel ) . ':' . $line,
		);
	}

	/**
	 * Writes discovered strings (one query per new string, at shutdown).
	 */
	public static function flush_discovered() {
		if ( ! self::$discovered || ! self::tables_exist() ) {
			return;
		}
		self::$resolving = true;
		foreach ( self::$discovered as $data ) {
			self::register( $data );
		}
		self::$resolving  = false;
		self::$discovered = array();
	}
}
