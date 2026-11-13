<?php
/**
 * "Language & translations" box in the post editor (block and classic),
 * and the language panel of navigation menus in the Site Editor.
 *
 * @package PixelLang_Multilingual
 */

defined( 'ABSPATH' ) || exit;

class WPM_Meta_Box {

	private static $instance = null;

	private function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_meta_box' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_nav_panel' ) );
	}

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function add_meta_box() {
		foreach ( WPM_Settings::get_post_types() as $screen ) {
			add_meta_box(
				'wpm-translations',
				__( 'Language & translations', 'pixellang-multilingual' ),
				array( $this, 'render_meta_box' ),
				$screen,
				'side',
				'high'
			);
		}
	}

	/**
	 * @param WP_Post $post Post being edited.
	 */
	public function render_meta_box( $post ) {
		$manager   = WPM_Language_Manager::instance();
		$languages = $manager->get_all();

		if ( empty( $languages ) ) {
			printf(
				'<p class="wpm-mb-empty">%s <a href="%s">%s</a></p>',
				esc_html__( 'No language is active yet.', 'pixellang-multilingual' ),
				esc_url( admin_url( 'admin.php?page=wpm-languages' ) ),
				esc_html__( 'Add languages', 'pixellang-multilingual' )
			);
			return;
		}

		wp_nonce_field( 'wpm_meta_box_nonce', 'wpm_meta_box_nonce' );

		$current_lang = WPM_Translations::get_post_language( $post->ID );
		$is_new       = ! $current_lang;
		if ( ! $current_lang ) {
			$current_lang = $manager->get_default();
		}
		$group    = WPM_Translations::get_post_translations( $post->ID );
		$is_saved = 'auto-draft' !== $post->post_status;
		$sync     = WPM_Sync::get_status( $post->ID );
		$source   = WPM_Sync::get_source( $post->ID );
		$compare  = WPM_Sync::OUTDATED === $sync ? WPM_Sync::get_compare_url( $post->ID ) : '';
		?>
		<div class="wpm-mb" data-post="<?php echo esc_attr( $post->ID ); ?>" data-post-type="<?php echo esc_attr( $post->post_type ); ?>"
			data-sync="<?php echo esc_attr( $sync ); ?>" data-compare="<?php echo esc_url( $compare ); ?>">
			<?php if ( $source ) : ?>
				<?php $this->render_sync( $sync, $source, $compare ); ?>
			<?php endif; ?>
			<div class="wpm-mb__field">
				<label class="wpm-mb__label" for="wpm-page-lang"><?php esc_html_e( 'Language of this content', 'pixellang-multilingual' ); ?></label>
				<select name="wpm_page_lang" id="wpm-page-lang" class="wpm-mb__select">
					<?php foreach ( $languages as $slug => $cfg ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $current_lang, $slug ); ?>><?php echo esc_html( $cfg['label'] . ' (' . strtoupper( $slug ) . ')' ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php if ( $is_new && $is_saved ) : ?>
					<p class="wpm-mb__hint wpm-mb__hint--warning"><?php esc_html_e( 'No language assigned yet. Saving will assign the selected one.', 'pixellang-multilingual' ); ?></p>
				<?php endif; ?>
			</div>

			<p class="wpm-mb__heading"><?php esc_html_e( 'Translations', 'pixellang-multilingual' ); ?></p>
			<ul class="wpm-mb__list">
				<?php
				foreach ( $languages as $slug => $cfg ) :
					$linked_id = ( isset( $group[ $slug ] ) && $group[ $slug ] !== $post->ID ) ? (int) $group[ $slug ] : 0;
					$hidden    = $slug === $current_lang;
					?>
					<li class="wpm-mb__row<?php echo $linked_id ? ' is-linked' : ''; ?>" data-lang="<?php echo esc_attr( $slug ); ?>" data-label="<?php echo esc_attr( $cfg['label'] ); ?>"<?php echo $hidden ? ' hidden' : ''; ?>>
						<span class="wpm-chip" title="<?php echo esc_attr( $cfg['label'] ); ?>"><?php echo esc_html( strtoupper( $slug ) ); ?></span>
						<input type="hidden" class="wpm-mb__value" name="wpm_translations[<?php echo esc_attr( $slug ); ?>]" value="<?php echo esc_attr( $linked_id ? $linked_id : '' ); ?>"<?php disabled( $hidden ); ?> />

						<div class="wpm-mb__linked"<?php echo $linked_id ? '' : ' hidden'; ?>>
							<?php if ( $linked_id ) : ?>
								<a class="wpm-mb__title" href="<?php echo esc_url( (string) get_edit_post_link( $linked_id ) ); ?>"><?php echo esc_html( get_the_title( $linked_id ) ? get_the_title( $linked_id ) : __( '(no title)', 'pixellang-multilingual' ) ); ?></a>
								<?php WPM_Admin::status_badge( get_post_status( $linked_id ) ); ?>
								<?php if ( WPM_Sync::is_outdated( $linked_id ) ) : ?>
									<span class="wpm-badge wpm-badge--warning wpm-mb__outdated"><?php esc_html_e( 'Needs update', 'pixellang-multilingual' ); ?></span>
								<?php endif; ?>
							<?php else : ?>
								<span class="wpm-mb__title"></span>
							<?php endif; ?>
							<button type="button" class="wpm-icon-btn wpm-mb__unlink" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: language name. */ __( 'Unlink the %s translation', 'pixellang-multilingual' ), $cfg['label'] ) ); ?>">
								<?php WPM_Icons::render( 'unlink', 16 ); ?>
							</button>
						</div>

						<div class="wpm-mb__empty"<?php echo $linked_id ? ' hidden' : ''; ?>>
							<?php if ( $is_saved ) : ?>
								<a class="button button-small wpm-mb__create" href="<?php echo esc_url( WPM_Admin::create_translation_url( $post->ID, $slug ) ); ?>">
									<?php WPM_Icons::render( 'plus', 14 ); ?><?php esc_html_e( 'Create', 'pixellang-multilingual' ); ?>
								</a>
							<?php endif; ?>
							<div class="wpm-combobox">
								<input type="search" class="wpm-combobox__input" role="combobox" aria-expanded="false" aria-autocomplete="list" autocomplete="off"
									aria-controls="wpm-results-<?php echo esc_attr( $slug ); ?>"
									aria-label="<?php echo esc_attr( sprintf( /* translators: %s: language name. */ __( 'Link an existing %s content', 'pixellang-multilingual' ), $cfg['label'] ) ); ?>"
									placeholder="<?php esc_attr_e( 'Link existing…', 'pixellang-multilingual' ); ?>" />
								<ul class="wpm-combobox__list" id="wpm-results-<?php echo esc_attr( $slug ); ?>" role="listbox" hidden></ul>
							</div>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php if ( ! $is_saved ) : ?>
				<p class="wpm-mb__hint"><?php esc_html_e( 'Save a draft to create translations from this content.', 'pixellang-multilingual' ); ?></p>
			<?php endif; ?>
			<p class="wpm-mb__live screen-reader-text" aria-live="polite"></p>
		</div>
		<?php
	}

	/**
	 * Sync state of a translation: up to date, or outdated with the actions
	 * that resolve it.
	 *
	 * @param string $sync    WPM_Sync status.
	 * @param int    $source  Source post ID.
	 * @param string $compare Revision compare URL or ''.
	 */
	private function render_sync( $sync, $source, $compare ) {
		$source_lang  = WPM_Translations::get_post_language( $source );
		$source_label = $source_lang ? WPM_Language_Manager::instance()->get_label( $source_lang ) : '';
		$source_edit  = current_user_can( 'edit_post', $source ) ? get_edit_post_link( $source ) : '';
		if ( WPM_Sync::OUTDATED !== $sync ) {
			?>
			<p class="wpm-mb__sync is-current">
				<?php WPM_Icons::render( 'check-circle', 16 ); ?>
				<span>
					<?php
					/* translators: %s: language name of the original. */
					echo esc_html( sprintf( __( 'Up to date with the %s original.', 'pixellang-multilingual' ), $source_label ) );
					?>
				</span>
			</p>
			<?php
			return;
		}
		?>
		<div class="wpm-mb__sync is-outdated">
			<p class="wpm-mb__sync-title"><?php WPM_Icons::render( 'alert', 16 ); ?><strong><?php esc_html_e( 'Needs update', 'pixellang-multilingual' ); ?></strong></p>
			<p class="wpm-mb__sync-text">
				<?php
				/* translators: %s: language name of the original. */
				echo esc_html( sprintf( __( 'The %s original changed after this translation was last updated.', 'pixellang-multilingual' ), $source_label ) );
				?>
			</p>
			<div class="wpm-mb__sync-actions">
				<?php if ( $compare ) : ?>
					<a class="wpm-mb__sync-link" href="<?php echo esc_url( $compare ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'See what changed', 'pixellang-multilingual' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'pixellang-multilingual' ); ?></span></a>
				<?php elseif ( $source_edit ) : ?>
					<a class="wpm-mb__sync-link" href="<?php echo esc_url( $source_edit ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open the original', 'pixellang-multilingual' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'pixellang-multilingual' ); ?></span></a>
				<?php endif; ?>
				<button type="button" class="button button-small wpm-mb__mark-synced"><?php WPM_Icons::render( 'check', 14 ); ?><?php esc_html_e( 'Mark as up to date', 'pixellang-multilingual' ); ?></button>
			</div>
		</div>
		<?php
	}

	/**
	 * Saves language and links. Every linked post is checked: same post type,
	 * editable by the user, and not already in another language.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public function save_meta_box( $post_id, $post ) {
		if ( ! isset( $_POST['wpm_meta_box_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpm_meta_box_nonce'] ) ), 'wpm_meta_box_nonce' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) || ! WPM_Settings::is_translatable_post_type( $post->post_type ) ) {
			return;
		}
		$lang = isset( $_POST['wpm_page_lang'] ) ? sanitize_key( wp_unslash( $_POST['wpm_page_lang'] ) ) : '';
		if ( ! WPM_Language_Manager::instance()->is_valid( $lang ) ) {
			return;
		}
		$requested = array();
		$posted    = isset( $_POST['wpm_translations'] ) ? (array) wp_unslash( $_POST['wpm_translations'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in update_post_group().
		foreach ( $posted as $t_lang => $t_id ) {
			$requested[ sanitize_key( $t_lang ) ] = absint( $t_id );
		}
		$result = WPM_Rest::update_post_group( $post, $lang, $requested );
		if ( is_wp_error( $result ) ) {
			// Keep the language even when a link is rejected.
			WPM_Translations::set_language( 'post', $post_id, $lang );
		}
	}

	public function enqueue_assets( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! WPM_Settings::is_translatable_post_type( $screen->post_type ) ) {
			return;
		}
		wp_enqueue_style( 'wpm-meta-box', WPM_URL . 'admin/css/meta-box.css', array(), WPM_VERSION );
		wp_enqueue_script( 'wpm-meta-box', WPM_URL . 'admin/js/meta-box.js', array( 'wp-api-fetch', 'wp-i18n', 'wp-url', 'wp-data', 'wp-notices' ), WPM_VERSION, true );
		wp_set_script_translations( 'wpm-meta-box', 'pixellang-multilingual', WPM_DIR . 'languages' );
		wp_localize_script(
			'wpm-meta-box',
			'wpmMetaBox',
			array(
				'upToDate' => __( 'Up to date with the original.', 'pixellang-multilingual' ),
				'statuses' => array(
					'publish' => __( 'Published', 'pixellang-multilingual' ),
					'draft'   => __( 'Draft', 'pixellang-multilingual' ),
					'pending' => __( 'Pending', 'pixellang-multilingual' ),
					'future'  => __( 'Scheduled', 'pixellang-multilingual' ),
					'private' => __( 'Private', 'pixellang-multilingual' ),
				),
			)
		);
	}

	/**
	 * Language panel for navigation menus in the Site Editor.
	 */
	public function enqueue_nav_panel() {
		$screen = get_current_screen();
		if ( ! $screen || ! $screen->is_block_editor() || ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}
		if ( 'site-editor' !== $screen->base && 'site-editor' !== $screen->id ) {
			return;
		}
		$menus     = get_option( 'wpm_menus', array() );
		$menus     = is_array( $menus ) ? $menus : array();
		$lang_data = array();
		foreach ( WPM_Language_Manager::instance()->get_all() as $slug => $cfg ) {
			$taken_id           = isset( $menus[ $slug ] ) ? (int) $menus[ $slug ] : 0;
			$lang_data[ $slug ] = array(
				'label'       => $cfg['label'],
				'taken_by'    => $taken_id,
				'taken_title' => $taken_id ? get_the_title( $taken_id ) : '',
			);
		}
		wp_enqueue_script(
			'wpm-nav-panel',
			WPM_URL . 'admin/js/nav-panel.js',
			array( 'wp-plugins', 'wp-editor', 'wp-element', 'wp-data', 'wp-components', 'wp-api-fetch', 'wp-i18n' ),
			WPM_VERSION,
			true
		);
		wp_set_script_translations( 'wpm-nav-panel', 'pixellang-multilingual', WPM_DIR . 'languages' );
		wp_localize_script(
			'wpm-nav-panel',
			'wpmNavData',
			array(
				'languages' => $lang_data,
				'menus'     => (object) array_map( 'intval', $menus ),
			)
		);
	}
}
