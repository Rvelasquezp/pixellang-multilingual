<?php
/**
 * Translated URL slugs of custom post types and taxonomies.
 *
 * A post type registered with the rewrite slug "equipe" keeps it for the
 * default language; Settings > URLs gives it "team" for English, and so on.
 * Then:
 * - permalinks and archive links of English content use /team/…;
 * - rewrite rules for /team/… (single, archive and its pages) resolve to the
 *   same post type, so both bases work;
 * - a content opened under the base of another language is redirected (301)
 *   to its own URL, so search engines see one URL per page.
 *
 * Settings: slugs => [ post_type => [ type => [ lang => slug ] ],
 *                      taxonomy  => [ tax  => [ lang => slug ] ] ].
 * Rewrite rules are flushed when the setting changes.
 *
 * @package PixelLang_Multilingual
 */

defined( 'ABSPATH' ) || exit;

class WPM_Slugs {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_rules' ), 99 );
		add_action( 'init', array( __CLASS__, 'flush_if_needed' ), 100 );
		add_filter( 'post_type_link', array( __CLASS__, 'post_link' ), 10, 2 );
		add_filter( 'term_link', array( __CLASS__, 'term_link' ), 10, 3 );
		add_filter( 'post_type_archive_link', array( __CLASS__, 'archive_link' ), 10, 2 );
		add_action( 'template_redirect', array( __CLASS__, 'redirect_wrong_base' ), 1 );
		add_action( 'update_option_' . WPM_Settings::OPTION, array( __CLASS__, 'maybe_flush' ), 10, 2 );
	}

	// ---------------------------------------------------------------------
	// Objects and settings
	// ---------------------------------------------------------------------

	/**
	 * Translatable post types that have their own URL base.
	 *
	 * @return WP_Post_Type[]
	 */
	public static function post_types() {
		$out = array();
		foreach ( WPM_Settings::get_post_types() as $type ) {
			$obj = get_post_type_object( $type );
			if ( $obj && $obj->publicly_queryable && ! $obj->_builtin && is_array( $obj->rewrite ) && ! empty( $obj->rewrite['slug'] ) ) {
				$out[ $type ] = $obj;
			}
		}
		return $out;
	}

	/**
	 * Translatable taxonomies that have their own URL base.
	 *
	 * @return WP_Taxonomy[]
	 */
	public static function taxonomies() {
		$out = array();
		foreach ( WPM_Settings::get_taxonomies() as $tax ) {
			$obj = get_taxonomy( $tax );
			if ( $obj && $obj->publicly_queryable && is_array( $obj->rewrite ) && ! empty( $obj->rewrite['slug'] ) ) {
				$out[ $tax ] = $obj;
			}
		}
		return $out;
	}

	/**
	 * Registered (default language) base of a post type or taxonomy.
	 *
	 * @param string $kind 'post_type' or 'taxonomy'.
	 * @param string $name Name.
	 * @return string
	 */
	public static function base( $kind, $name ) {
		$obj = 'post_type' === $kind ? get_post_type_object( $name ) : get_taxonomy( $name );
		return ( $obj && is_array( $obj->rewrite ) && ! empty( $obj->rewrite['slug'] ) ) ? trim( $obj->rewrite['slug'], '/' ) : '';
	}

	/**
	 * Base of a post type or taxonomy in a language (the registered one when
	 * no translation is set).
	 *
	 * @param string $kind 'post_type' or 'taxonomy'.
	 * @param string $name Name.
	 * @param string $lang Language code.
	 * @return string
	 */
	public static function slug( $kind, $name, $lang ) {
		$all = (array) WPM_Settings::get( 'slugs' );
		if ( $lang && ! empty( $all[ $kind ][ $name ][ $lang ] ) ) {
			return $all[ $kind ][ $name ][ $lang ];
		}
		return self::base( $kind, $name );
	}

	/**
	 * Sanitizes the setting.
	 *
	 * @param mixed $value Raw value.
	 * @return array
	 */
	public static function sanitize( $value ) {
		$manager = WPM_Language_Manager::instance();
		$clean   = array(
			'post_type' => array(),
			'taxonomy'  => array(),
		);
		foreach ( array( 'post_type', 'taxonomy' ) as $kind ) {
			$value_kind = isset( $value[ $kind ] ) && is_array( $value[ $kind ] ) ? $value[ $kind ] : array();
			foreach ( $value_kind as $name => $langs ) {
				$name = sanitize_key( $name );
				$base = self::base( $kind, $name );
				if ( '' === $base || ! is_array( $langs ) ) {
					continue;
				}
				foreach ( $langs as $lang => $slug ) {
					$lang = sanitize_key( $lang );
					// Allows nested bases ("about/team"); each part is a slug.
					$slug = implode( '/', array_filter( array_map( 'sanitize_title', explode( '/', (string) $slug ) ) ) );
					if ( $manager->is_valid( $lang ) && $lang !== $manager->get_default() && '' !== $slug && $slug !== $base ) {
						$clean[ $kind ][ $name ][ $lang ] = $slug;
					}
				}
			}
		}
		return $clean;
	}

	// ---------------------------------------------------------------------
	// Rewrite rules
	// ---------------------------------------------------------------------

	/**
	 * Rules for every translated base.
	 */
	public static function add_rules() {
		$all = (array) WPM_Settings::get( 'slugs' );
		foreach ( self::post_types() as $type => $obj ) {
			$var = $obj->query_var ? $obj->query_var : $type;
			foreach ( isset( $all['post_type'][ $type ] ) ? (array) $all['post_type'][ $type ] : array() as $slug ) {
				$q = preg_quote( $slug, '#' );
				if ( $obj->has_archive ) {
					add_rewrite_rule( '^' . $q . '/page/?([0-9]{1,})/?$', 'index.php?post_type=' . $type . '&paged=$matches[1]', 'top' );
					add_rewrite_rule( '^' . $q . '/?$', 'index.php?post_type=' . $type, 'top' );
				}
				if ( $obj->hierarchical ) {
					add_rewrite_rule( '^' . $q . '/(.+?)/?$', 'index.php?' . $var . '=$matches[1]', 'top' );
				} else {
					add_rewrite_rule( '^' . $q . '/([^/]+)/?$', 'index.php?' . $var . '=$matches[1]', 'top' );
				}
			}
		}
		foreach ( self::taxonomies() as $tax => $obj ) {
			$var = $obj->query_var ? $obj->query_var : 'taxonomy=' . $tax . '&term';
			foreach ( isset( $all['taxonomy'][ $tax ] ) ? (array) $all['taxonomy'][ $tax ] : array() as $slug ) {
				$q = preg_quote( $slug, '#' );
				add_rewrite_rule( '^' . $q . '/([^/]+)/page/?([0-9]{1,})/?$', 'index.php?' . $var . '=$matches[1]&paged=$matches[2]', 'top' );
				add_rewrite_rule( '^' . $q . '/([^/]+)/?$', 'index.php?' . $var . '=$matches[1]', 'top' );
			}
		}
	}

	/**
	 * The slugs changed: rebuild the rewrite rules on the next request, once
	 * add_rules() has registered the new bases only.
	 *
	 * @param mixed $old Old options.
	 * @param mixed $new New options.
	 */
	public static function maybe_flush( $old, $new ) {
		$before = isset( $old['slugs'] ) ? $old['slugs'] : array();
		$after  = isset( $new['slugs'] ) ? $new['slugs'] : array();
		if ( $before !== $after ) {
			update_option( 'wpm_flush_rewrite', 1, true );
		}
	}

	/**
	 * Pending rebuild of the rewrite rules (see maybe_flush()).
	 */
	public static function flush_if_needed() {
		if ( get_option( 'wpm_flush_rewrite' ) ) {
			delete_option( 'wpm_flush_rewrite' );
			flush_rewrite_rules( false );
		}
	}

	// ---------------------------------------------------------------------
	// Links
	// ---------------------------------------------------------------------

	/**
	 * Replaces the first occurrence of /base/ by /slug/ in a URL path.
	 *
	 * @param string $url  URL.
	 * @param string $base Registered base.
	 * @param string $slug Language base.
	 * @return string
	 */
	private static function swap_base( $url, $base, $slug ) {
		if ( '' === $base || $base === $slug ) {
			return $url;
		}
		$home = trailingslashit( home_url() );
		if ( 0 !== strpos( $url, $home . $base . '/' ) && untrailingslashit( $url ) !== $home . $base ) {
			return $url;
		}
		return $home . $slug . substr( $url, strlen( $home . $base ) );
	}

	/**
	 * @param string  $link Permalink.
	 * @param WP_Post $post Post.
	 * @return string
	 */
	public static function post_link( $link, $post ) {
		if ( ! isset( self::post_types()[ $post->post_type ] ) ) {
			return $link;
		}
		$lang = WPM_Translations::get_post_language( $post->ID );
		return $lang ? self::swap_base( $link, self::base( 'post_type', $post->post_type ), self::slug( 'post_type', $post->post_type, $lang ) ) : $link;
	}

	/**
	 * @param string  $link     Term link.
	 * @param WP_Term $term     Term.
	 * @param string  $taxonomy Taxonomy.
	 * @return string
	 */
	public static function term_link( $link, $term, $taxonomy ) {
		if ( ! isset( self::taxonomies()[ $taxonomy ] ) ) {
			return $link;
		}
		$lang = WPM_Translations::get_language( 'term', $term->term_id );
		return $lang ? self::swap_base( $link, self::base( 'taxonomy', $taxonomy ), self::slug( 'taxonomy', $taxonomy, $lang ) ) : $link;
	}

	/**
	 * Archive link in the current language (front end).
	 *
	 * @param string $link      Archive link.
	 * @param string $post_type Post type.
	 * @return string
	 */
	public static function archive_link( $link, $post_type ) {
		$manager = WPM_Language_Manager::instance();
		if ( ! isset( self::post_types()[ $post_type ] ) || ! $manager->is_front_request() ) {
			return $link;
		}
		return self::swap_base( $link, self::base( 'post_type', $post_type ), self::slug( 'post_type', $post_type, $manager->get_current() ) );
	}

	/**
	 * Language whose base is the given URL segment, when only one has it.
	 *
	 * @param string $kind 'post_type' or 'taxonomy'.
	 * @param string $name Name.
	 * @param string $base First segment of the URL.
	 * @return string '' when no language or several share it.
	 */
	public static function language_of_base( $kind, $name, $base ) {
		$found = array();
		foreach ( WPM_Language_Manager::instance()->get_slugs() as $lang ) {
			if ( self::slug( $kind, $name, $lang ) === $base ) {
				$found[] = $lang;
			}
		}
		return 1 === count( $found ) ? $found[0] : '';
	}

	/**
	 * Archive URL of a content type in a language, or ''.
	 *
	 * @param string $post_type Post type.
	 * @param string $lang      Language code.
	 * @return string
	 */
	public static function archive_url( $post_type, $lang ) {
		$types = self::post_types();
		if ( ! isset( $types[ $post_type ] ) || ! $types[ $post_type ]->has_archive ) {
			return '';
		}
		remove_filter( 'post_type_archive_link', array( __CLASS__, 'archive_link' ), 10 );
		$link = get_post_type_archive_link( $post_type );
		add_filter( 'post_type_archive_link', array( __CLASS__, 'archive_link' ), 10, 2 );
		return $link ? self::swap_base( $link, self::base( 'post_type', $post_type ), self::slug( 'post_type', $post_type, $lang ) ) : '';
	}

	// ---------------------------------------------------------------------
	// One URL per content
	// ---------------------------------------------------------------------

	/**
	 * Content opened under the base of another language: 301 to its URL.
	 */
	public static function redirect_wrong_base() {
		if ( is_preview() || ! ( is_singular() || is_tax() || is_category() || is_tag() ) ) {
			return;
		}
		$object = get_queried_object();
		if ( $object instanceof WP_Post && isset( self::post_types()[ $object->post_type ] ) && 'publish' === $object->post_status ) {
			$target = get_permalink( $object );
		} elseif ( $object instanceof WP_Term && isset( self::taxonomies()[ $object->taxonomy ] ) ) {
			$target = get_term_link( $object );
		} else {
			return;
		}
		if ( ! $target || is_wp_error( $target ) ) {
			return;
		}
		$path   = trim( (string) wp_parse_url( $target, PHP_URL_PATH ), '/' );
		$asked  = trim( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ), '/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$asked  = preg_replace( '#/page/[0-9]+$#', '', $asked );
		$prefix = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
		if ( $prefix && 0 === strpos( $asked, $prefix . '/' ) ) {
			$asked = substr( $asked, strlen( $prefix ) + 1 );
			$path  = 0 === strpos( $path, $prefix . '/' ) ? substr( $path, strlen( $prefix ) + 1 ) : $path;
		}
		if ( $asked === $path || '' === $asked ) {
			return;
		}
		$first_asked  = strtok( $asked, '/' );
		$first_target = strtok( $path, '/' );
		// Only a different base is corrected; WordPress handles the rest.
		if ( $first_asked !== $first_target ) {
			$query = isset( $_SERVER['QUERY_STRING'] ) && '' !== $_SERVER['QUERY_STRING'] ? '?' . wp_unslash( $_SERVER['QUERY_STRING'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			wp_safe_redirect( $target . $query, 301, 'PixelLang Multilingual' );
			exit;
		}
	}
}
