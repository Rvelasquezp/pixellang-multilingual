<?php
/**
 * Active languages and the language of the current request.
 *
 * Public API used by themes (kept stable since 1.0):
 * WPM_Language_Manager::instance()->get_current(), get_default(), get_all(),
 * get( $slug ), is_valid( $slug ), get_slugs(), get_translated_page().
 *
 * @package PixelLang_Multilingual
 */

defined( 'ABSPATH' ) || exit;

class WPM_Language_Manager {

	private static $instance = null;

	/**
	 * Active languages, in display order: slug => { label, locale, default }.
	 *
	 * @var array
	 */
	private $languages = array();

	/**
	 * Language of the current request.
	 *
	 * @var string
	 */
	private $current_lang = '';

	private function __construct() {
		$this->languages = $this->normalize( get_option( 'wpm_languages', array() ) );
		add_filter( 'locale', array( $this, 'filter_locale' ) );
	}

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Makes sure every stored language has the expected keys.
	 *
	 * @param mixed $languages Raw option value.
	 * @return array
	 */
	private function normalize( $languages ) {
		if ( ! is_array( $languages ) ) {
			return array();
		}
		$clean = array();
		foreach ( $languages as $slug => $cfg ) {
			$slug = sanitize_key( $slug );
			if ( ! $slug || ! is_array( $cfg ) ) {
				continue;
			}
			$clean[ $slug ] = array(
				'label'   => isset( $cfg['label'] ) && '' !== $cfg['label'] ? (string) $cfg['label'] : strtoupper( $slug ),
				'locale'  => isset( $cfg['locale'] ) ? (string) $cfg['locale'] : '',
				'default' => ! empty( $cfg['default'] ),
			);
		}
		return $clean;
	}

	public function get_slugs() {
		return array_keys( $this->languages );
	}

	public function get_all() {
		return $this->languages;
	}

	public function get( $slug ) {
		return isset( $this->languages[ $slug ] ) ? $this->languages[ $slug ] : null;
	}

	/**
	 * Display name of a language (falls back to the uppercase code).
	 *
	 * @param string $slug Language code.
	 * @return string
	 */
	public function get_label( $slug ) {
		$cfg = $this->get( $slug );
		return $cfg ? $cfg['label'] : strtoupper( (string) $slug );
	}

	/**
	 * WordPress locale of a language (fr_FR, en_US…).
	 *
	 * @param string $slug Language code.
	 * @return string
	 */
	public function get_locale( $slug ) {
		$cfg = $this->get( $slug );
		return ( $cfg && $cfg['locale'] ) ? $cfg['locale'] : '';
	}

	/**
	 * BCP 47 code for hreflang and the html lang attribute (fr-CA, en-US…).
	 *
	 * @param string $slug Language code.
	 * @return string
	 */
	public function get_hreflang( $slug ) {
		$locale = $this->get_locale( $slug );
		return $locale ? str_replace( '_', '-', $locale ) : $slug;
	}

	public function get_default() {
		foreach ( $this->languages as $slug => $cfg ) {
			if ( ! empty( $cfg['default'] ) ) {
				return $slug;
			}
		}
		$keys = array_keys( $this->languages );
		return ! empty( $keys ) ? $keys[0] : 'fr';
	}

	public function is_valid( $slug ) {
		return is_string( $slug ) && isset( $this->languages[ $slug ] );
	}

	public function set_current( $slug ) {
		if ( $this->is_valid( $slug ) ) {
			$this->current_lang = $slug;
		}
	}

	/**
	 * Home pages follow the languages: the version in the default language is
	 * the WordPress front page (site root), and every other version lives at
	 * /{language code}/ (/fr/, /es/…). The posts page follows the default
	 * language too. Only published translations become the front page. Old
	 * addresses redirect to the new ones (WordPress keeps the old slug).
	 *
	 * @return array What changed: page_on_front / page_for_posts => [ old ID,
	 *               new ID ], slugs => [ post ID => new slug ].
	 */
	public function sync_front_page() {
		$changed = array();
		if ( 'page' !== get_option( 'show_on_front' ) ) {
			return $changed;
		}
		$changed = $this->sync_front_option();
		$front   = (int) get_option( 'page_on_front' );
		if ( $front ) {
			foreach ( WPM_Translations::get_post_translations( $front ) as $lang => $id ) {
				if ( (int) $id === $front || get_post_field( 'post_name', $id ) === $lang ) {
					continue;
				}
				$slug = wp_unique_post_slug( $lang, $id, get_post_status( $id ), 'page', 0 );
				if ( $slug === $lang ) {
					$this->set_page_slug( (int) $id, $lang );
					$changed['slugs'][ $id ] = $lang;
				}
			}
		}
		return $changed;
	}

	/**
	 * Changes only the slug of a page (top level). wp_update_post() would save
	 * the whole content again, and filters of the site may rewrite it (block
	 * attributes re-encoded), which would mark its translations outdated.
	 * The old address keeps redirecting (_wp_old_slug).
	 *
	 * @param int    $id   Page ID.
	 * @param string $slug New slug.
	 */
	private function set_page_slug( $id, $slug ) {
		global $wpdb;
		$before = get_post( $id );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			$wpdb->posts,
			array(
				'post_name'   => $slug,
				'post_parent' => 0,
			),
			array( 'ID' => $id )
		);
		clean_post_cache( $id );
		// WordPress keeps old slugs of posts only, not of pages: keep it here
		// so the old address redirects (WPM_Url_Handler::redirect_old_slug()).
		if ( $before && $before->post_name && $before->post_name !== $slug ) {
			$old = (array) get_post_meta( $id, '_wpm_old_slug' );
			if ( ! in_array( $before->post_name, $old, true ) ) {
				add_post_meta( $id, '_wpm_old_slug', $before->post_name );
			}
		}
	}

	/**
	 * page_on_front and page_for_posts: version in the default language.
	 *
	 * @return array option => [ old ID, new ID ].
	 */
	private function sync_front_option() {
		$changed = array();
		$default = $this->get_default();
		foreach ( array( 'page_on_front', 'page_for_posts' ) as $option ) {
			$current = (int) get_option( $option );
			if ( ! $current || WPM_Translations::get_post_language( $current ) === $default ) {
				continue;
			}
			$target = WPM_Translations::get_post_translation( $current, $default );
			if ( $target && 'publish' === get_post_status( $target ) ) {
				update_option( $option, $target );
				$changed[ $option ] = array( $current, $target );
			}
		}
		return $changed;
	}

	public function get_current() {
		return $this->current_lang ? $this->current_lang : $this->get_default();
	}

	/**
	 * Applies the visitor language to the locale on the front end only.
	 *
	 * The admin keeps the user's own language: before 1.1 the front-end
	 * cookie also switched the wp-admin interface.
	 *
	 * @param string $locale Locale computed by WordPress.
	 * @return string
	 */
	public function filter_locale( $locale ) {
		if ( ! $this->is_front_request() ) {
			return $locale;
		}
		$target = $this->get_locale( $this->get_current() );
		return $target ? $target : $locale;
	}

	/**
	 * Whether the visitor language drives this request: front end and
	 * front-end AJAX calls, never the admin screens or REST. Also used by
	 * String Translation.
	 *
	 * @return bool
	 */
	public function is_front_request() {
		if ( wp_doing_ajax() ) {
			return true;
		}
		if ( is_admin() ) {
			return false;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( function_exists( 'rest_get_url_prefix' ) && false !== strpos( $uri, '/' . rest_get_url_prefix() . '/' ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Legacy translation map (option wpm_page_map). Since 1.1 relations live
	 * in post meta; this option is kept untouched as a backup.
	 *
	 * @deprecated 1.1.0 Use WPM_Translations.
	 * @return array
	 */
	public function get_page_map() {
		$map = get_option( 'wpm_page_map', array() );
		return is_array( $map ) ? $map : array();
	}

	/**
	 * ID of the translation of a post in a language, or null.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $lang    Language code.
	 * @return int|null
	 */
	public function get_translated_page( $post_id, $lang ) {
		$id = WPM_Translations::get_post_translation( $post_id, $lang );
		return $id ? $id : null;
	}

	/**
	 * Saves the active languages (already sanitized).
	 *
	 * @param array $languages slug => { label, locale, default }.
	 */
	public function save_languages( $languages ) {
		$this->languages = $this->normalize( $languages );
		update_option( 'wpm_languages', $this->languages );
	}

	/**
	 * @deprecated 1.1.0 Relations are stored in post meta.
	 * @param array $map Legacy map.
	 */
	public function save_page_map( $map ) {
		update_option( 'wpm_page_map', $map );
	}
}
