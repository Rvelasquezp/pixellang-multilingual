<?php
/**
 * Language suggestion banner.
 *
 * Instead of redirecting visitors by their browser language (which hides
 * pages from search engines and surprises people), the page stays as it is
 * and a small banner offers its translation: "This page is available in
 * your language. [English]".
 *
 * The server only prints the translations of the page, each with its text
 * already in its own language. The choice is made in the browser
 * (navigator.languages), so the HTML is the same for everyone and full page
 * caches keep working. The banner never shows again once the visitor closes
 * it, follows it, or uses a language switcher.
 *
 * @package PixelLang_Multilingual
 */

defined( 'ABSPATH' ) || exit;

class WPM_Language_Banner {

	/**
	 * Loaded translation files, by locale.
	 *
	 * @var array<string,MO|null>
	 */
	private static $mo = array();

	public static function init() {
		if ( is_admin() ) {
			return;
		}
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_footer', array( __CLASS__, 'render' ), 50 );
	}

	/**
	 * Whether the banner is on for this request.
	 *
	 * @return bool
	 */
	public static function is_active() {
		if ( 'banner' !== WPM_Settings::browser_mode() || is_feed() || is_embed() || is_customize_preview() || is_404() ) {
			return false;
		}
		return count( WPM_Language_Manager::instance()->get_all() ) > 1;
	}

	/**
	 * Translations of the current page that can be suggested.
	 *
	 * @return array<string,array> lang => hreflang, url, label, text, cta, close, region.
	 */
	public static function get_suggestions() {
		$manager = WPM_Language_Manager::instance();
		$current = $manager->get_current();
		$urls    = WPM_Url_Handler::instance();
		$out     = array();
		foreach ( $manager->get_all() as $slug => $cfg ) {
			if ( $slug === $current ) {
				continue;
			}
			$url = $urls->get_language_url( $slug, false );
			if ( ! $url ) {
				continue;
			}
			$locale       = $manager->get_locale( $slug );
			$out[ $slug ] = array(
				'hreflang' => $manager->get_hreflang( $slug ),
				'url'      => $url,
				'label'    => $cfg['label'],
				'text'     => self::translate( 'This page is available in your language.', $locale ),
				'close'    => self::translate( 'Close', $locale ),
				'region'   => self::translate( 'Language suggestion', $locale ),
			);
		}
		/**
		 * Filters the languages offered by the suggestion banner.
		 *
		 * @param array  $out     lang => hreflang, url, label, text, close, region.
		 * @param string $current Language of the page.
		 */
		return apply_filters( 'wpm_banner_suggestions', $out, $current );
	}

	/**
	 * A string of this plugin in another locale, without switching the
	 * locale of the page. Falls back to the language without region
	 * (fr_CA, then fr_FR, then any fr_*), then to English.
	 *
	 * @param string $text   English text.
	 * @param string $locale Locale.
	 * @return string
	 */
	public static function translate( $text, $locale ) {
		if ( ! $locale || 0 === strpos( $locale, 'en' ) ) {
			return $text;
		}
		if ( ! array_key_exists( $locale, self::$mo ) ) {
			self::$mo[ $locale ] = null;
			$lang       = strtok( $locale, '_' );
			$candidates = array( $locale, $lang . '_' . strtoupper( $lang ) );
			$candidates = array_merge( $candidates, (array) glob( WPM_DIR . 'languages/pixellang-multilingual-' . $lang . '_*.mo' ) );
			foreach ( $candidates as $candidate ) {
				$file = false !== strpos( $candidate, '/' ) ? $candidate : WPM_DIR . 'languages/pixellang-multilingual-' . $candidate . '.mo';
				if ( is_readable( $file ) ) {
					$mo = new MO();
					if ( $mo->import_from_file( $file ) ) {
						self::$mo[ $locale ] = $mo;
						break;
					}
				}
			}
		}
		return self::$mo[ $locale ] ? self::$mo[ $locale ]->translate( $text ) : $text;
	}

	/**
	 * Never called: lists the banner strings for the translation template,
	 * since translate() receives them as variables.
	 */
	private static function strings_for_translators() {
		__( 'This page is available in your language.', 'pixellang-multilingual' );
		__( 'Close', 'pixellang-multilingual' );
		__( 'Language suggestion', 'pixellang-multilingual' );
	}

	public static function enqueue() {
		if ( ! self::is_active() ) {
			return;
		}
		wp_enqueue_style( 'wpm-banner', WPM_URL . 'assets/css/banner.css', array(), WPM_VERSION );
		wp_enqueue_script(
			'wpm-banner',
			WPM_URL . 'assets/js/banner.js',
			array(),
			WPM_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	/**
	 * Hidden banner and its data; banner.js decides whether to show it.
	 */
	public static function render() {
		if ( ! self::is_active() ) {
			return;
		}
		$suggestions = self::get_suggestions();
		if ( ! $suggestions ) {
			return;
		}
		$data = array(
			'current'     => WPM_Language_Manager::instance()->get_current(),
			'active'      => WPM_Language_Manager::instance()->get_slugs(),
			'suggestions' => $suggestions,
		);
		?>
		<div class="wpm-banner" id="wpm-banner" role="region" hidden>
			<span class="wpm-banner__text"></span>
			<a class="wpm-banner__cta" href="#">
				<span class="wpm-banner__label"></span>
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
			</a>
			<button type="button" class="wpm-banner__close">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M18 6 6 18M6 6l12 12"/></svg>
			</button>
		</div>
		<script type="application/json" id="wpm-banner-data"><?php echo wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?></script>
		<?php
	}
}
