<?php
/**
 * Multilingual XML sitemaps and SEO plugin compatibility.
 *
 * - WordPress sitemaps (wp-sitemap.xml): every translated post and term
 *   lists its alternates as <xhtml:link rel="alternate" hreflang>, the
 *   format Google documents for multilingual sitemaps. Core cannot output
 *   extra elements, so its renderer is replaced by a subclass that only adds
 *   them; everything else (stylesheet, index, providers) stays core.
 * - Yoast SEO and Rank Math disable core sitemaps and build their own: the
 *   same alternates are added through their entry and URL filters.
 * - Open Graph: og:locale follows the language of the page, and
 *   og:locale:alternate lists the translations, when Yoast SEO or Rank Math
 *   print the Open Graph tags.
 *
 * Every alternate list is the one used for the hreflang tags of the page
 * (published translations only, two languages at least, x-default on the
 * default language), so the sitemap and the page never disagree.
 *
 * @package PixelLang_Multilingual
 */

defined( 'ABSPATH' ) || exit;

class WPM_Sitemaps {

	const XHTML_NS = 'http://www.w3.org/1999/xhtml';

	public static function init() {
		// WordPress core sitemaps.
		add_action( 'wp_sitemaps_init', array( __CLASS__, 'use_renderer' ) );
		add_filter( 'wp_sitemaps_posts_entry', array( __CLASS__, 'core_post_entry' ), 10, 2 );
		add_filter( 'wp_sitemaps_taxonomies_entry', array( __CLASS__, 'core_term_entry' ), 10, 3 );

		// Yoast SEO.
		add_filter( 'wpseo_sitemap_entry', array( __CLASS__, 'seo_entry' ), 10, 3 );
		add_filter( 'wpseo_sitemap_url', array( __CLASS__, 'seo_url' ), 10, 2 );
		add_filter( 'wpseo_locale', array( __CLASS__, 'og_locale' ) );

		// Rank Math.
		add_filter( 'rank_math/sitemap/entry', array( __CLASS__, 'seo_entry' ), 10, 3 );
		add_filter( 'rank_math/sitemap/url', array( __CLASS__, 'seo_url' ), 10, 2 );
		add_filter( 'rank_math/opengraph/facebook/og_locale', array( __CLASS__, 'og_locale' ) );

		// Both plugins print og:locale; neither prints the alternates.
		add_action( 'wp_head', array( __CLASS__, 'og_locale_alternates' ), 3 );
	}

	/**
	 * Whether an SEO plugin that prints Open Graph tags is active.
	 *
	 * @return bool
	 */
	public static function seo_plugin_active() {
		return defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' );
	}

	/**
	 * Whether sitemaps list alternates (same switch as the hreflang tags).
	 *
	 * @return bool
	 */
	public static function enabled() {
		/**
		 * Filters whether sitemaps list the translations of each URL.
		 *
		 * @param bool $enabled Default: the hreflang setting.
		 */
		return (bool) apply_filters( 'wpm_sitemap_alternates', (bool) WPM_Settings::get( 'hreflang' ) );
	}

	// ---------------------------------------------------------------------
	// Alternates
	// ---------------------------------------------------------------------

	/**
	 * Alternates of a post: hreflang => URL, or array() when fewer than two
	 * published languages exist.
	 *
	 * @param WP_Post|int $post Post.
	 * @return array<string,string>
	 */
	public static function post_alternates( $post ) {
		$post = get_post( $post );
		if ( ! $post || ! WPM_Settings::is_translatable_post_type( $post->post_type ) ) {
			return array();
		}
		$urls = array();
		foreach ( WPM_Translations::get_post_translations( $post->ID ) as $lang => $id ) {
			if ( 'publish' === get_post_status( $id ) && ! post_password_required( $id ) ) {
				$urls[ $lang ] = get_permalink( $id );
			}
		}
		return self::finalize( $urls );
	}

	/**
	 * Alternates of a term.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy.
	 * @return array<string,string>
	 */
	public static function term_alternates( $term_id, $taxonomy ) {
		if ( ! WPM_Settings::is_translatable_taxonomy( $taxonomy ) ) {
			return array();
		}
		$urls = array();
		foreach ( WPM_Translations::get_translations( 'term', $term_id ) as $lang => $id ) {
			$link = get_term_link( (int) $id, $taxonomy );
			if ( ! is_wp_error( $link ) ) {
				$urls[ $lang ] = $link;
			}
		}
		return self::finalize( $urls );
	}

	/**
	 * lang => URL becomes hreflang => URL, ordered like the languages, with
	 * x-default. Same rules as WPM_Hreflang::get_alternates().
	 *
	 * @param array $urls lang => URL.
	 * @return array<string,string>
	 */
	private static function finalize( array $urls ) {
		$manager    = WPM_Language_Manager::instance();
		$alternates = array();
		foreach ( $manager->get_slugs() as $lang ) {
			if ( ! empty( $urls[ $lang ] ) ) {
				$alternates[ $manager->get_hreflang( $lang ) ] = $urls[ $lang ];
			}
		}
		if ( count( $alternates ) < 2 ) {
			return array();
		}
		$default = $manager->get_default();
		if ( ! empty( $urls[ $default ] ) ) {
			$alternates['x-default'] = $urls[ $default ];
		}
		/** This filter is documented in includes/class-hreflang.php */
		return apply_filters( 'wpm_hreflang_alternates', $alternates );
	}

	/**
	 * <xhtml:link> elements of a URL entry.
	 *
	 * @param array $alternates hreflang => URL.
	 * @param bool  $inline_ns  Declare the namespace on each element (for
	 *                          sitemaps whose <urlset> does not declare it).
	 * @return string
	 */
	public static function links_xml( array $alternates, $inline_ns = false ) {
		$out = '';
		foreach ( $alternates as $hreflang => $url ) {
			$out .= sprintf(
				"\t\t<xhtml:link%s rel=\"alternate\" hreflang=\"%s\" href=\"%s\" />\n",
				$inline_ns ? ' xmlns:xhtml="' . self::XHTML_NS . '"' : '',
				esc_attr( $hreflang ),
				esc_url( $url )
			);
		}
		return $out;
	}

	// ---------------------------------------------------------------------
	// WordPress core sitemaps
	// ---------------------------------------------------------------------

	/**
	 * @param WP_Sitemaps $sitemaps Sitemaps instance.
	 */
	public static function use_renderer( $sitemaps ) {
		if ( self::enabled() && class_exists( 'WP_Sitemaps_Renderer' ) && $sitemaps instanceof WP_Sitemaps ) {
			require_once WPM_DIR . 'includes/class-sitemaps-renderer.php';
			$sitemaps->renderer = new WPM_Sitemaps_Renderer();
		}
	}

	/**
	 * @param array   $entry Sitemap entry.
	 * @param WP_Post $post  Post.
	 * @return array
	 */
	public static function core_post_entry( $entry, $post ) {
		if ( self::enabled() ) {
			$alternates = self::post_alternates( $post );
			if ( $alternates ) {
				$entry['wpm_alternates'] = $alternates;
			}
		}
		return $entry;
	}

	/**
	 * @param array  $entry    Sitemap entry.
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy.
	 * @return array
	 */
	public static function core_term_entry( $entry, $term_id, $taxonomy ) {
		if ( self::enabled() ) {
			$alternates = self::term_alternates( $term_id, $taxonomy );
			if ( $alternates ) {
				$entry['wpm_alternates'] = $alternates;
			}
		}
		return $entry;
	}

	// ---------------------------------------------------------------------
	// Yoast SEO and Rank Math (same filter shapes)
	// ---------------------------------------------------------------------

	/**
	 * Stores the alternates on the entry, read back by seo_url().
	 *
	 * @param array|false $url    Entry (loc, mod, images...), false to skip it.
	 * @param string      $type   'post', 'term' or 'user'.
	 * @param object      $object Post or term (WP_Post, WP_Term or a stdClass row).
	 * @return array|false
	 */
	public static function seo_entry( $url, $type, $object ) {
		if ( ! is_array( $url ) || ! self::enabled() ) {
			return $url;
		}
		// Yoast SEO passes rows of its own query (stdClass), not WP_Post.
		$alternates = array();
		if ( 'post' === $type && is_object( $object ) && ! empty( $object->ID ) ) {
			$alternates = self::post_alternates( (int) $object->ID );
		} elseif ( 'term' === $type && is_object( $object ) && ! empty( $object->term_id ) ) {
			$taxonomy   = ! empty( $object->taxonomy ) ? $object->taxonomy : (string) get_term_field( 'taxonomy', (int) $object->term_id );
			$alternates = self::term_alternates( (int) $object->term_id, $taxonomy );
		}
		if ( $alternates ) {
			$url['wpm_alternates'] = $alternates;
		}
		return $url;
	}

	/**
	 * Adds the <xhtml:link> elements before </url>. The namespace is declared
	 * on each element: both plugins' <urlset> may not declare it.
	 *
	 * @param string $output XML of one <url>.
	 * @param array  $url    Entry.
	 * @return string
	 */
	public static function seo_url( $output, $url ) {
		// Yoast SEO builds the front page entry without wpseo_sitemap_entry.
		if ( empty( $url['wpm_alternates'] ) && is_array( $url ) && ! empty( $url['loc'] ) && self::enabled()
			&& 'page' === get_option( 'show_on_front' ) && get_option( 'page_on_front' )
			&& untrailingslashit( $url['loc'] ) === untrailingslashit( home_url( '/' ) ) ) {
			$url['wpm_alternates'] = self::post_alternates( (int) get_option( 'page_on_front' ) );
		}
		if ( empty( $url['wpm_alternates'] ) || ! is_array( $url['wpm_alternates'] ) || false === strpos( $output, '</url>' ) ) {
			return $output;
		}
		$links = self::links_xml( $url['wpm_alternates'], true );
		$pos   = strrpos( $output, '</url>' );
		$head  = rtrim( substr( $output, 0, $pos ), "\t " );
		return $head . $links . "\t" . substr( $output, $pos );
	}

	// ---------------------------------------------------------------------
	// Open Graph
	// ---------------------------------------------------------------------

	/**
	 * og:locale of the language of the page.
	 *
	 * @param string $locale Locale computed by the SEO plugin.
	 * @return string
	 */
	public static function og_locale( $locale ) {
		$manager = WPM_Language_Manager::instance();
		$current = $manager->get_locale( $manager->get_current() );
		return $current ? $current : $locale;
	}

	/**
	 * og:locale:alternate for each published translation of the page.
	 */
	public static function og_locale_alternates() {
		if ( ! self::seo_plugin_active() || is_search() || is_404() ) {
			return;
		}
		$manager = WPM_Language_Manager::instance();
		$current = $manager->get_current();
		$object  = get_queried_object();
		$langs   = array();
		if ( $object instanceof WP_Post && is_singular() && WPM_Settings::is_translatable_post_type( $object->post_type ) ) {
			foreach ( WPM_Translations::get_post_translations( $object->ID ) as $lang => $id ) {
				if ( 'publish' === get_post_status( $id ) ) {
					$langs[] = $lang;
				}
			}
		} elseif ( $object instanceof WP_Term && WPM_Settings::is_translatable_taxonomy( $object->taxonomy ) ) {
			$langs = array_keys( WPM_Translations::get_translations( 'term', $object->term_id ) );
		}
		foreach ( $langs as $lang ) {
			$locale = $manager->get_locale( $lang );
			if ( $lang !== $current && $locale ) {
				printf( '<meta property="og:locale:alternate" content="%s" />' . "\n", esc_attr( $locale ) );
			}
		}
	}
}
