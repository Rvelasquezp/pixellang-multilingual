<?php
/**
 * Language features of the content lists (edit.php):
 * Language and Translations columns, language filter, bulk "Set language"
 * action and Quick Edit field. Navigation menus get a Language column.
 *
 * @package PixelLang_Multilingual
 */

defined( 'ABSPATH' ) || exit;

class WPM_Admin_Columns {

	private static $instance = null;

	private function __construct() {
		add_action( 'admin_init', array( $this, 'register_column_hooks' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'restrict_manage_posts', array( $this, 'render_language_filter' ), 10, 1 );
		add_action( 'pre_get_posts', array( $this, 'filter_list_by_language' ) );
		add_action( 'save_post', array( $this, 'save_quick_edit' ), 10, 2 );
		add_action( 'quick_edit_custom_box', array( $this, 'render_quick_edit_box' ), 10, 2 );
		add_action( 'admin_notices', array( $this, 'bulk_notice' ) );
	}

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * manage_{$post_type}_posts_columns is the hook for every post type
	 * (1.0 used manage_{$type}s_columns, which only exists for page and post).
	 */
	public function register_column_hooks() {
		foreach ( WPM_Settings::get_post_types() as $type ) {
			add_filter( "manage_{$type}_posts_columns", array( $this, 'add_columns' ) );
			add_action( "manage_{$type}_posts_custom_column", array( $this, 'render_column' ), 10, 2 );
			add_filter( "bulk_actions-edit-{$type}", array( $this, 'register_bulk_actions' ) );
			add_filter( "handle_bulk_actions-edit-{$type}", array( $this, 'handle_bulk_actions' ), 10, 3 );
		}
		add_filter( 'manage_wp_navigation_posts_columns', array( $this, 'add_nav_column' ) );
		add_action( 'manage_wp_navigation_posts_custom_column', array( $this, 'render_nav_column' ), 10, 2 );
	}

	private function is_list_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen && 'edit' === $screen->base && ( WPM_Settings::is_translatable_post_type( $screen->post_type ) || 'wp_navigation' === $screen->post_type );
	}

	public function enqueue_assets( $hook ) {
		if ( 'edit.php' !== $hook || ! $this->is_list_screen() ) {
			return;
		}
		wp_enqueue_style( 'wpm-columns', WPM_URL . 'admin/css/columns.css', array(), WPM_VERSION );
		wp_enqueue_script( 'wpm-columns', WPM_URL . 'admin/js/columns.js', array( 'inline-edit-post' ), WPM_VERSION, true );
	}

	// ---------------------------------------------------------------------
	// Columns
	// ---------------------------------------------------------------------

	public function add_columns( $columns ) {
		if ( ! WPM_Language_Manager::instance()->get_all() ) {
			return $columns;
		}
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['wpm_language']     = __( 'Language', 'pixellang-multilingual' );
				$new['wpm_translations'] = __( 'Translations', 'pixellang-multilingual' );
			}
		}
		return $new;
	}

	public function render_column( $column, $post_id ) {
		if ( 'wpm_language' === $column ) {
			$lang = WPM_Translations::get_post_language( $post_id );
			// Read by columns.js to fill the Quick Edit select.
			printf( '<span class="wpm-qe-lang" hidden>%s</span>', esc_html( $lang ) );
			if ( $lang ) {
				WPM_Admin::language_chip( $lang, true );
			} else {
				printf( '<span class="wpm-col-unset">%s</span>', esc_html__( 'Not set', 'pixellang-multilingual' ) );
			}
			return;
		}
		if ( 'wpm_translations' !== $column ) {
			return;
		}

		$lang = WPM_Translations::get_post_language( $post_id );
		if ( ! $lang ) {
			echo '<span class="wpm-col-unset" aria-hidden="true">–</span><span class="screen-reader-text">' . esc_html__( 'Assign a language first', 'pixellang-multilingual' ) . '</span>';
			return;
		}
		$group   = WPM_Translations::get_post_translations( $post_id );
		$manager = WPM_Language_Manager::instance();
		echo '<span class="wpm-trans">';
		foreach ( $manager->get_all() as $slug => $cfg ) {
			if ( $slug === $lang ) {
				continue;
			}
			$linked = isset( $group[ $slug ] ) ? (int) $group[ $slug ] : 0;
			if ( $linked ) {
				$status = get_post_status( $linked );
				$title  = sprintf(
					/* translators: 1: language, 2: title, 3: status. */
					__( '%1$s: %2$s (%3$s). Edit', 'pixellang-multilingual' ),
					$cfg['label'],
					get_the_title( $linked ),
					WPM_Admin::status_label( $status )
				);
				printf(
					'<a class="wpm-trans__item %1$s" href="%2$s" title="%3$s" aria-label="%3$s">%4$s</a>',
					'publish' === $status ? 'is-published' : 'is-draft',
					esc_url( (string) get_edit_post_link( $linked ) ),
					esc_attr( $title ),
					esc_html( strtoupper( $slug ) )
				);
			} elseif ( current_user_can( 'edit_post', $post_id ) ) {
				/* translators: %s: language name. */
				$label = sprintf( __( 'Create the %s translation', 'pixellang-multilingual' ), $cfg['label'] );
				printf(
					'<a class="wpm-trans__item is-missing" href="%1$s" title="%2$s" aria-label="%2$s">%3$s</a>',
					esc_url( WPM_Admin::create_translation_url( $post_id, $slug ) ),
					esc_attr( $label ),
					esc_html( strtoupper( $slug ) ) . WPM_Icons::get( 'plus', 10 ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG.
				);
			}
		}
		echo '</span>';
	}

	public function add_nav_column( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['wpm_language'] = __( 'Language', 'pixellang-multilingual' );
			}
		}
		return $new;
	}

	public function render_nav_column( $column, $post_id ) {
		if ( 'wpm_language' !== $column ) {
			return;
		}
		$menus = get_option( 'wpm_menus', array() );
		$lang  = is_array( $menus ) ? array_search( (int) $post_id, array_map( 'intval', $menus ), true ) : false;
		if ( $lang && WPM_Language_Manager::instance()->is_valid( $lang ) ) {
			WPM_Admin::language_chip( $lang, true );
		} else {
			printf( '<span class="wpm-col-unset">%s</span>', esc_html__( 'Not assigned', 'pixellang-multilingual' ) );
		}
	}

	// ---------------------------------------------------------------------
	// Language filter
	// ---------------------------------------------------------------------

	public function render_language_filter( $post_type ) {
		if ( ! WPM_Settings::is_translatable_post_type( $post_type ) ) {
			return;
		}
		$current = isset( $_GET['wpm_lang'] ) ? sanitize_key( wp_unslash( $_GET['wpm_lang'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<label for="wpm-filter-lang" class="screen-reader-text"><?php esc_html_e( 'Filter by language', 'pixellang-multilingual' ); ?></label>
		<select name="wpm_lang" id="wpm-filter-lang">
			<option value=""><?php esc_html_e( 'All languages', 'pixellang-multilingual' ); ?></option>
			<?php foreach ( WPM_Language_Manager::instance()->get_all() as $slug => $cfg ) : ?>
				<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $current, $slug ); ?>><?php echo esc_html( $cfg['label'] ); ?></option>
			<?php endforeach; ?>
			<option value="none" <?php selected( $current, 'none' ); ?>><?php esc_html_e( 'No language', 'pixellang-multilingual' ); ?></option>
		</select>
		<?php
	}

	/**
	 * @param WP_Query $query Query.
	 */
	public function filter_list_by_language( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || empty( $_GET['wpm_lang'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		global $pagenow;
		if ( 'edit.php' !== $pagenow || ! WPM_Settings::is_translatable_post_type( (string) $query->get( 'post_type' ) ) ) {
			return;
		}
		$lang       = sanitize_key( wp_unslash( $_GET['wpm_lang'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$meta_query = (array) $query->get( 'meta_query' );
		if ( 'none' === $lang ) {
			$meta_query[] = array(
				'key'     => WPM_Translations::META_LANG,
				'compare' => 'NOT EXISTS',
			);
		} elseif ( WPM_Language_Manager::instance()->is_valid( $lang ) ) {
			$meta_query[] = array(
				'key'   => WPM_Translations::META_LANG,
				'value' => $lang,
			);
		}
		$query->set( 'meta_query', $meta_query );
	}

	// ---------------------------------------------------------------------
	// Bulk action
	// ---------------------------------------------------------------------

	public function register_bulk_actions( $actions ) {
		foreach ( WPM_Language_Manager::instance()->get_all() as $slug => $cfg ) {
			/* translators: %s: language name. */
			$actions[ 'wpm_set_lang_' . $slug ] = sprintf( __( 'Set language: %s', 'pixellang-multilingual' ), $cfg['label'] );
		}
		return $actions;
	}

	/**
	 * Assigns a language to the selected posts. Posts that already have a
	 * translation in that language keep their group but leave it.
	 *
	 * @param string $redirect Redirect URL.
	 * @param string $action   Action name.
	 * @param int[]  $ids      Selected post IDs.
	 * @return string
	 */
	public function handle_bulk_actions( $redirect, $action, $ids ) {
		if ( 0 !== strpos( $action, 'wpm_set_lang_' ) ) {
			return $redirect;
		}
		$lang = substr( $action, strlen( 'wpm_set_lang_' ) );
		if ( ! WPM_Language_Manager::instance()->is_valid( $lang ) ) {
			return $redirect;
		}
		$done = 0;
		foreach ( array_map( 'absint', (array) $ids ) as $id ) {
			if ( current_user_can( 'edit_post', $id ) ) {
				WPM_Translations::set_language( 'post', $id, $lang );
				++$done;
			}
		}
		return add_query_arg(
			array(
				'wpm_bulk_lang' => $lang,
				'wpm_bulk_done' => $done,
			),
			remove_query_arg( array( 'wpm_bulk_lang', 'wpm_bulk_done' ), $redirect )
		);
	}

	public function bulk_notice() {
		if ( empty( $_GET['wpm_bulk_lang'] ) || ! isset( $_GET['wpm_bulk_done'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$lang = sanitize_key( wp_unslash( $_GET['wpm_bulk_lang'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$done = absint( $_GET['wpm_bulk_done'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: 1: number of items, 2: language name. */
					_n( '%1$d item set to %2$s.', '%1$d items set to %2$s.', $done, 'pixellang-multilingual' ),
					$done,
					WPM_Language_Manager::instance()->get_label( $lang )
				)
			)
		);
	}

	// ---------------------------------------------------------------------
	// Quick Edit
	// ---------------------------------------------------------------------

	public function render_quick_edit_box( $column_name, $post_type ) {
		if ( 'wpm_language' !== $column_name || ! WPM_Settings::is_translatable_post_type( $post_type ) ) {
			return;
		}
		wp_nonce_field( 'wpm_quick_edit_nonce', 'wpm_qe_nonce' );
		?>
		<fieldset class="inline-edit-col-left wpm-qe">
			<div class="inline-edit-col">
				<label>
					<span class="title"><?php esc_html_e( 'Language', 'pixellang-multilingual' ); ?></span>
					<select name="wpm_quick_lang">
						<option value=""><?php esc_html_e( 'Not set', 'pixellang-multilingual' ); ?></option>
						<?php foreach ( WPM_Language_Manager::instance()->get_all() as $slug => $cfg ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $cfg['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</div>
		</fieldset>
		<?php
	}

	public function save_quick_edit( $post_id, $post ) {
		if ( ! isset( $_POST['wpm_qe_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpm_qe_nonce'] ) ), 'wpm_quick_edit_nonce' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) || ! WPM_Settings::is_translatable_post_type( $post->post_type ) ) {
			return;
		}
		$lang = isset( $_POST['wpm_quick_lang'] ) ? sanitize_key( wp_unslash( $_POST['wpm_quick_lang'] ) ) : '';
		WPM_Translations::set_language( 'post', $post_id, WPM_Language_Manager::instance()->is_valid( $lang ) ? $lang : '' );
	}
}
