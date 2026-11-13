<?php
/**
 * Admin screens: Dashboard, Languages, Translations, Settings, Tools.
 *
 * Each screen section posts to its own admin-post.php action with its own
 * nonce, so saving one section never touches the options of another (1.0
 * used a single options.php form that rewrote every option at once).
 *
 * @package PixelLang_Multilingual
 */

defined( 'ABSPATH' ) || exit;

class WPM_Admin {

	private static $instance = null;

	const CAP = 'manage_options';

	/**
	 * Hook suffixes of the plugin screens.
	 *
	 * @var string[]
	 */
	private $hooks = array();

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_wpm_save_languages', array( $this, 'handle_save_languages' ) );
		add_action( 'admin_post_wpm_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_wpm_create_translation', array( $this, 'handle_create_translation' ) );
		add_action( 'admin_post_wpm_tools', array( $this, 'handle_tools' ) );
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
		add_action( 'admin_notices', array( $this, 'editor_notice' ) );
	}

	/**
	 * Confirmation shown in the editor after a translation draft was created.
	 */
	public function editor_notice() {
		global $pagenow;
		if ( 'post.php' !== $pagenow || empty( $_GET['wpm_notice'] ) || 'created' !== $_GET['wpm_notice'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html__( 'Translation draft created from the original content. Translate it, then publish.', 'pixellang-multilingual' )
		);
	}

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Screens: slug => [ menu label, capability, view file, icon ].
	 *
	 * @return array
	 */
	public static function screens() {
		return array(
			'wp-multilingual'  => array( __( 'Dashboard', 'pixellang-multilingual' ), 'edit_others_posts', 'overview', 'dashboard' ),
			'wpm-languages'    => array( __( 'Languages', 'pixellang-multilingual' ), self::CAP, 'languages', 'globe' ),
			'wpm-translations' => array( __( 'Translations', 'pixellang-multilingual' ), 'edit_others_posts', 'translations', 'files' ),
			'wpm-strings'      => array( __( 'String Translation', 'pixellang-multilingual' ), WPM_Strings_Admin::capability(), 'strings', 'type' ),
			'wpm-settings'     => array( __( 'Settings', 'pixellang-multilingual' ), self::CAP, 'settings', 'settings' ),
			'wpm-tools'        => array( __( 'Tools', 'pixellang-multilingual' ), self::CAP, 'tools', 'tools' ),
		);
	}

	public function register_menu() {
		$icon = WPM_Icons::menu_icon();
		$this->hooks[] = add_menu_page(
			__( 'PixelLang Multilingual', 'pixellang-multilingual' ),
			__( 'PixelLang', 'pixellang-multilingual' ),
			'edit_others_posts',
			'wp-multilingual',
			array( $this, 'render_screen' ),
			$icon,
			80
		);
		foreach ( self::screens() as $slug => $screen ) {
			$this->hooks[] = add_submenu_page( 'wp-multilingual', $screen[0] . ' ‹ PixelLang Multilingual', $screen[0], $screen[1], $slug, array( $this, 'render_screen' ) );
		}
	}

	/**
	 * Current plugin screen slug, or ''.
	 *
	 * @return string
	 */
	public static function current_screen() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( self::screens()[ $page ] ) ? $page : '';
	}

	public function body_class( $classes ) {
		return self::current_screen() ? $classes . ' wpm-admin-screen' : $classes;
	}

	public function enqueue_assets( $hook ) {
		if ( ! in_array( $hook, $this->hooks, true ) ) {
			return;
		}
		wp_enqueue_style( 'wpm-admin', WPM_URL . 'admin/css/admin.css', array(), WPM_VERSION );
		wp_enqueue_script( 'wpm-admin', WPM_URL . 'admin/js/admin.js', array( 'wp-api-fetch', 'wp-i18n' ), WPM_VERSION, true );
		wp_set_script_translations( 'wpm-admin', 'pixellang-multilingual', WPM_DIR . 'languages' );
		// Live previews of the switcher designs (Settings > Language switcher).
		wp_enqueue_style( 'wp-multilingual-language-switcher-style' );
		wp_enqueue_script( 'wp-multilingual-language-switcher-view-script' );
	}

	public function render_screen() {
		$slug    = self::current_screen();
		$screens = self::screens();
		if ( ! $slug || ! current_user_can( $screens[ $slug ][1] ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'pixellang-multilingual' ) );
		}
		$view = $screens[ $slug ][2];
		include WPM_DIR . 'admin/views/layout-header.php';
		include WPM_DIR . 'admin/views/' . $view . '.php';
		include WPM_DIR . 'admin/views/layout-footer.php';
	}

	// ---------------------------------------------------------------------
	// Reusable UI pieces
	// ---------------------------------------------------------------------

	/**
	 * Language chip: code badge, optionally with the language name.
	 *
	 * @param string $slug       Language code.
	 * @param bool   $with_label Show the name next to the code.
	 * @param bool   $echo       Echo (true) or return.
	 * @return string
	 */
	public static function language_chip( $slug, $with_label = false, $echo = true ) {
		$label = WPM_Language_Manager::instance()->get_label( $slug );
		$html  = sprintf(
			'<span class="wpm-chip"><span class="wpm-chip__code"%s>%s</span>%s</span>',
			$with_label ? ' aria-hidden="true"' : ' title="' . esc_attr( $label ) . '"',
			esc_html( strtoupper( $slug ) ),
			$with_label ? '<span class="wpm-chip__label">' . esc_html( $label ) . '</span>' : '<span class="screen-reader-text">' . esc_html( $label ) . '</span>'
		);
		if ( $echo ) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
		}
		return $html;
	}

	/**
	 * Translated label of a post status.
	 *
	 * @param string $status Post status.
	 * @return string
	 */
	public static function status_label( $status ) {
		$labels = array(
			'publish' => __( 'Published', 'pixellang-multilingual' ),
			'draft'   => __( 'Draft', 'pixellang-multilingual' ),
			'pending' => __( 'Pending', 'pixellang-multilingual' ),
			'future'  => __( 'Scheduled', 'pixellang-multilingual' ),
			'private' => __( 'Private', 'pixellang-multilingual' ),
			'trash'   => __( 'Trash', 'pixellang-multilingual' ),
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : (string) $status;
	}

	/**
	 * Status badge of a post.
	 *
	 * @param string $status Post status.
	 */
	public static function status_badge( $status ) {
		$tone = 'publish' === $status ? 'success' : ( 'trash' === $status ? 'danger' : 'neutral' );
		printf( '<span class="wpm-badge wpm-badge--%s">%s</span>', esc_attr( $tone ), esc_html( self::status_label( $status ) ) );
	}

	/**
	 * Notice block (success, warning, error, info).
	 *
	 * @param string $type    Tone.
	 * @param string $message Message (plain text).
	 * @param string $action  Optional HTML of an action link (already escaped).
	 */
	public static function notice( $type, $message, $action = '' ) {
		$icons = array(
			'success' => 'check-circle',
			'warning' => 'alert',
			'error'   => 'alert',
			'info'    => 'info',
		);
		printf(
			'<div class="wpm-notice wpm-notice--%1$s" role="%2$s">%3$s<p>%4$s</p>%5$s</div>',
			esc_attr( $type ),
			'error' === $type ? 'alert' : 'status',
			WPM_Icons::get( isset( $icons[ $type ] ) ? $icons[ $type ] : 'info', 20 ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG.
			esc_html( $message ),
			wp_kses_post( $action )
		);
	}

	/**
	 * Empty state with an optional call to action.
	 *
	 * @param string $icon    Icon name.
	 * @param string $title   Title.
	 * @param string $text    Explanation.
	 * @param string $url     Action URL.
	 * @param string $label   Action label.
	 */
	public static function empty_state( $icon, $title, $text, $url = '', $label = '' ) {
		echo '<div class="wpm-empty">';
		WPM_Icons::render( $icon, 28, 'wpm-empty__icon' );
		printf( '<h3 class="wpm-empty__title">%s</h3><p class="wpm-empty__text">%s</p>', esc_html( $title ), esc_html( $text ) );
		if ( $url ) {
			printf( '<a class="wpm-btn wpm-btn--primary" href="%s">%s</a>', esc_url( $url ), esc_html( $label ) );
		}
		echo '</div>';
	}

	/**
	 * URL of a plugin screen.
	 *
	 * @param string $slug Screen slug.
	 * @param array  $args Extra query args.
	 * @return string
	 */
	public static function url( $slug, $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => $slug ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Nonce-protected URL that creates a translation draft.
	 *
	 * @param int    $post_id Source post.
	 * @param string $lang    Target language.
	 * @return string
	 */
	public static function create_translation_url( $post_id, $lang ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'wpm_create_translation',
					'post'   => (int) $post_id,
					'lang'   => $lang,
				),
				admin_url( 'admin-post.php' )
			),
			'wpm_create_translation_' . (int) $post_id . '_' . $lang
		);
	}

	/**
	 * Redirects back to a screen with a notice code.
	 *
	 * @param string $slug   Screen slug.
	 * @param string $notice Notice code.
	 * @param array  $args   Extra args.
	 */
	private function back( $slug, $notice, $args = array() ) {
		wp_safe_redirect( self::url( $slug, array_merge( $args, array( 'wpm_notice' => $notice ) ) ) );
		exit;
	}

	/**
	 * Messages for notice codes passed in the URL.
	 *
	 * @return array code => [ type, message ]
	 */
	public static function notices() {
		return array(
			'saved'          => array( 'success', __( 'Changes saved.', 'pixellang-multilingual' ) ),
			'languages'      => array( 'success', __( 'Languages saved.', 'pixellang-multilingual' ) ),
			'no_language'    => array( 'error', __( 'Keep at least one language.', 'pixellang-multilingual' ) ),
			'stats'          => array( 'success', __( 'Statistics recalculated.', 'pixellang-multilingual' ) ),
			'assigned'       => array( 'success', __( 'Default language assigned to content that had none.', 'pixellang-multilingual' ) ),
			'created'        => array( 'success', __( 'Translation draft created.', 'pixellang-multilingual' ) ),
			'create_failed'  => array( 'error', __( 'The translation could not be created.', 'pixellang-multilingual' ) ),
			'string_added'   => array( 'success', __( 'String registered. Add its translations below.', 'pixellang-multilingual' ) ),
			'string_exists'  => array( 'info', __( 'This string was already registered: it is shown below.', 'pixellang-multilingual' ) ),
			'string_invalid' => array( 'error', __( 'Enter the original text, and a text domain for a theme or plugin string.', 'pixellang-multilingual' ) ),
			'scanned'        => array( 'success', __( 'Scan finished.', 'pixellang-multilingual' ) ),
			'scan_failed'    => array( 'error', __( 'This theme or plugin could not be scanned.', 'pixellang-multilingual' ) ),
			'discovery_on'   => array( 'success', __( 'Discovery is on for 30 minutes. Browse your site while logged in: the strings used on each page are registered.', 'pixellang-multilingual' ) ),
			'discovery_off'  => array( 'success', __( 'Discovery is off.', 'pixellang-multilingual' ) ),
			'strings_deleted' => array( 'success', __( 'Selected strings deleted.', 'pixellang-multilingual' ) ),
		);
	}

	// ---------------------------------------------------------------------
	// Handlers
	// ---------------------------------------------------------------------

	private function verify( $action ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'pixellang-multilingual' ), 403 );
		}
		check_admin_referer( $action );
	}

	/**
	 * Languages screen: order, names, locales, default.
	 */
	public function handle_save_languages() {
		$this->verify( 'wpm_save_languages' );

		$catalog = wpm_get_available_languages();
		$posted  = isset( $_POST['languages'] ) && is_array( $_POST['languages'] ) ? wp_unslash( $_POST['languages'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized per field below.
		$default = isset( $_POST['default_language'] ) ? sanitize_key( wp_unslash( $_POST['default_language'] ) ) : '';

		$clean = array();
		foreach ( $posted as $slug => $cfg ) {
			$slug = sanitize_key( $slug );
			if ( ! preg_match( '/^[a-z]{2,3}(-[a-z0-9]{2,8})?$/', $slug ) || ! is_array( $cfg ) ) {
				continue;
			}
			$label  = isset( $cfg['label'] ) ? sanitize_text_field( $cfg['label'] ) : '';
			$locale = isset( $cfg['locale'] ) ? sanitize_text_field( $cfg['locale'] ) : '';
			if ( ! preg_match( '/^[a-z]{2,3}(_[A-Z]{2})?(_[a-z0-9]+)?$/', $locale ) ) {
				$locale = isset( $catalog[ $slug ] ) ? $catalog[ $slug ]['locale'] : '';
			}
			$clean[ $slug ] = array(
				'label'   => '' !== $label ? $label : ( isset( $catalog[ $slug ] ) ? $catalog[ $slug ]['label'] : strtoupper( $slug ) ),
				'locale'  => $locale,
				'default' => $slug === $default,
			);
		}

		if ( ! $clean ) {
			$this->back( 'wpm-languages', 'no_language' );
		}
		if ( ! isset( $clean[ $default ] ) ) {
			$first                      = array_key_first( $clean );
			$clean[ $first ]['default'] = true;
		}

		WPM_Language_Manager::instance()->save_languages( $clean );
		WPM_Translations::flush_stats();
		$this->back( 'wpm-languages', 'languages' );
	}

	/**
	 * Settings screen: one section per form.
	 */
	public function handle_save_settings() {
		$this->verify( 'wpm_save_settings' );
		$section = isset( $_POST['section'] ) ? sanitize_key( wp_unslash( $_POST['section'] ) ) : '';
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by WPM_Settings::sanitize().
		switch ( $section ) {
			case 'content':
				$types   = isset( $_POST['post_types'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['post_types'] ) ) : array();
				$allowed = array_keys( WPM_Settings::get_translatable_post_types() );
				$types   = array_values( array_unique( array_merge( array( 'page', 'post' ), array_intersect( $types, $allowed ) ) ) );
				update_option( 'wpm_post_types', $types );
				WPM_Settings::update( array( 'taxonomies' => isset( $_POST['taxonomies'] ) ? (array) wp_unslash( $_POST['taxonomies'] ) : array() ) );
				WPM_Translations::flush_stats();
				break;

			case 'behavior':
				WPM_Settings::update(
					array(
						'browser_mode'      => isset( $_POST['browser_mode'] ) ? sanitize_key( wp_unslash( $_POST['browser_mode'] ) ) : 'banner',
						'browser_detection' => isset( $_POST['browser_mode'] ) && 'redirect' === $_POST['browser_mode'],
						'front_redirect'    => ! empty( $_POST['front_redirect'] ),
						'filter_queries'    => ! empty( $_POST['filter_queries'] ),
						'hreflang'          => ! empty( $_POST['hreflang'] ),
						'cookie_days'       => isset( $_POST['cookie_days'] ) ? absint( $_POST['cookie_days'] ) : 30,
					)
				);
				break;

			case 'switcher':
				WPM_Settings::update(
					array(
						'switcher_placement'         => isset( $_POST['switcher_placement'] ) ? sanitize_key( wp_unslash( $_POST['switcher_placement'] ) ) : 'none',
						'switcher_menus'             => isset( $_POST['switcher_menus'] ) ? (array) wp_unslash( $_POST['switcher_menus'] ) : array(),
						'switcher_locations'         => isset( $_POST['switcher_locations'] ) ? (array) wp_unslash( $_POST['switcher_locations'] ) : array(),
						'switcher_display'           => isset( $_POST['switcher_display'] ) ? sanitize_key( wp_unslash( $_POST['switcher_display'] ) ) : 'code',
						'switcher_hide_current'      => ! empty( $_POST['switcher_hide_current'] ),
						'switcher_floating_position' => isset( $_POST['switcher_floating_position'] ) ? sanitize_key( wp_unslash( $_POST['switcher_floating_position'] ) ) : 'right',
						'switcher_floating_theme'    => isset( $_POST['switcher_floating_theme'] ) ? sanitize_key( wp_unslash( $_POST['switcher_floating_theme'] ) ) : 'light',
					)
				);
				break;

			case 'menus':
				$navs = isset( $_POST['nav_menus'] ) ? (array) wp_unslash( $_POST['nav_menus'] ) : array();
				$menu = array();
				foreach ( $navs as $lang => $nav_id ) {
					$lang   = sanitize_key( $lang );
					$nav_id = absint( $nav_id );
					if ( $nav_id && WPM_Language_Manager::instance()->is_valid( $lang ) && 'wp_navigation' === get_post_type( $nav_id ) && ! in_array( $nav_id, $menu, true ) ) {
						$menu[ $lang ] = $nav_id;
					}
				}
				update_option( 'wpm_menus', $menu );
				WPM_Settings::update( array( 'menu_locations' => isset( $_POST['menu_locations'] ) ? (array) wp_unslash( $_POST['menu_locations'] ) : array() ) );
				break;

			case 'forms':
				$groups = isset( $_POST['form_groups'] ) ? array_values( (array) wp_unslash( $_POST['form_groups'] ) ) : array();
				WPM_Settings::update( array( 'form_groups' => $groups ) );
				break;

			default:
				wp_die( esc_html__( 'Unknown settings section.', 'pixellang-multilingual' ), 400 );
		}
		// phpcs:enable
		$this->back( 'wpm-settings', 'saved', array( 'tab' => $section ) );
	}

	/**
	 * Creates a translation draft, then opens it in the editor.
	 */
	public function handle_create_translation() {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checked below.
		$lang    = isset( $_GET['lang'] ) ? sanitize_key( wp_unslash( $_GET['lang'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		check_admin_referer( 'wpm_create_translation_' . $post_id . '_' . $lang );

		$post = get_post( $post_id );
		$type = $post ? get_post_type_object( $post->post_type ) : null;
		if ( ! $post || ! $type || ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( $type->cap->create_posts ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to translate this content.', 'pixellang-multilingual' ), 403 );
		}

		$result = WPM_Translations::create_post_translation( $post_id, $lang );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), esc_html__( 'Translation', 'pixellang-multilingual' ), array( 'back_link' => true ) );
		}
		wp_safe_redirect( add_query_arg( 'wpm_notice', 'created', get_edit_post_link( $result, 'raw' ) ) );
		exit;
	}

	/**
	 * Tools screen actions.
	 */
	public function handle_tools() {
		$this->verify( 'wpm_tools' );
		$task = isset( $_POST['task'] ) ? sanitize_key( wp_unslash( $_POST['task'] ) ) : '';

		if ( 'recount' === $task ) {
			WPM_Translations::flush_stats();
			$this->back( 'wpm-tools', 'stats' );
		}

		if ( 'assign_default' === $task ) {
			$types = isset( $_POST['post_types'] ) ? array_intersect( array_map( 'sanitize_key', (array) wp_unslash( $_POST['post_types'] ) ), WPM_Settings::get_post_types() ) : array();
			$lang  = WPM_Language_Manager::instance()->get_default();
			if ( $types ) {
				$ids = get_posts(
					array(
						'post_type'        => array_values( $types ),
						'post_status'      => array( 'publish', 'draft', 'pending', 'future', 'private' ),
						'posts_per_page'   => -1,
						'fields'           => 'ids',
						'suppress_filters' => true,
						'no_found_rows'    => true,
						'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
							array(
								'key'     => WPM_Translations::META_LANG,
								'compare' => 'NOT EXISTS',
							),
						),
					)
				);
				foreach ( $ids as $id ) {
					if ( current_user_can( 'edit_post', $id ) ) {
						update_post_meta( $id, WPM_Translations::META_LANG, $lang );
					}
				}
				WPM_Translations::flush_stats();
			}
			$this->back( 'wpm-tools', 'assigned' );
		}

		wp_die( esc_html__( 'Unknown task.', 'pixellang-multilingual' ), 400 );
	}
}
