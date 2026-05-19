<?php
defined( 'ABSPATH' ) || exit;

/**
 * Meta box shown in the page/post editor.
 * Lets the user assign a language to the page and link its translations.
 */
class WPM_Meta_Box {

	private static $instance = null;

	private function __construct() {
		add_action( 'rest_api_init',    array( $this, 'register_rest_route' ) );
		add_action( 'add_meta_boxes',   array( $this, 'add_meta_box' ) );
		add_action( 'save_post',        array( $this, 'save_meta_box' ), 10, 2 );
		add_action( 'admin_enqueue_scripts',      array( $this, 'enqueue_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_nav_panel' ) );
	}

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	// -------------------------------------------------------------------------
	// Custom REST endpoint — bypasses wp_navigation meta restrictions
	// -------------------------------------------------------------------------

	public function register_rest_route() {
		register_rest_route( 'wpm/v1', '/nav-language', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'rest_save_nav_language' ),
			'permission_callback' => function() {
				return current_user_can( 'edit_posts' );
			},
			'args' => array(
				'nav_id' => array(
					'required'          => true,
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
				),
				'lang' => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_key',
				),
			),
		) );
	}

	public function rest_save_nav_language( $request ) {
		$nav_id = (int) $request->get_param( 'nav_id' );
		$lang   = sanitize_key( $request->get_param( 'lang' ) );

		$manager = WPM_Language_Manager::instance();
		if ( $lang && ! $manager->is_valid( $lang ) ) {
			return new WP_Error( 'invalid_lang', 'Invalid language slug.', array( 'status' => 400 ) );
		}

		$menus = get_option( 'wpm_menus', array() );

		// Clear any existing slot for this nav ID.
		foreach ( $menus as $slug => $id ) {
			if ( (int) $id === $nav_id ) {
				unset( $menus[ $slug ] );
			}
		}

		if ( $lang ) {
			$menus[ $lang ] = $nav_id;
		}

		update_option( 'wpm_menus', $menus );

		return rest_ensure_response( array(
			'success' => true,
			'nav_id'  => $nav_id,
			'lang'    => $lang,
			'menus'   => $menus,
		) );
	}

	// -------------------------------------------------------------------------
	// Enqueue Gutenberg panel for Site Editor (wp_navigation)
	// -------------------------------------------------------------------------

	public function enqueue_nav_panel() {
		$screen = get_current_screen();

		// Only load in block editor contexts.
		if ( ! $screen || ! $screen->is_block_editor() ) {
			return;
		}

		$manager   = WPM_Language_Manager::instance();
		$languages = $manager->get_all();
		$menus     = get_option( 'wpm_menus', array() );
		$all       = wpm_get_available_languages();

		// Build language data for JS.
		$lang_data  = array();
		foreach ( $languages as $slug => $cfg ) {
			$taken_id = isset( $menus[ $slug ] ) ? (int) $menus[ $slug ] : 0;
			$lang_data[ $slug ] = array(
				'label'       => $cfg['label'],
				'flag'        => isset( $all[ $slug ]['flag'] ) ? $all[ $slug ]['flag'] : '🌐',
				'taken_by'    => $taken_id,
				'taken_title' => $taken_id ? get_the_title( $taken_id ) : '',
			);
		}

		// Current post ID: try $GLOBALS['post'], then URL param ?postId= (site editor), then parse ?p=.
		$current_id = 0;
		if ( isset( $GLOBALS['post'] ) && $GLOBALS['post'] ) {
			$current_id = (int) $GLOBALS['post']->ID;
		} elseif ( isset( $_GET['postId'] ) ) {
			$current_id = absint( $_GET['postId'] );
		} elseif ( isset( $_GET['p'] ) ) {
			// Site editor passes p=%2Fwp_navigation%2F4 — extract the trailing number.
			$p = urldecode( $_GET['p'] );
			if ( preg_match( '/(\d+)$/', $p, $m ) ) {
				$current_id = (int) $m[1];
			}
		}

		// Detect which language is currently assigned to this nav ID.
		$current_lang = '';
		foreach ( $menus as $slug => $nav_id ) {
			if ( $current_id && (int) $nav_id === $current_id ) {
				$current_lang = $slug;
				break;
			}
		}

		wp_enqueue_script(
			'wpm-nav-panel',
			WPM_URL . 'admin/js/nav-panel.js',
			array( 'wp-plugins', 'wp-editor', 'wp-element', 'wp-data', 'wp-components', 'wp-api-fetch' ),
			WPM_VERSION,
			true
		);

		wp_localize_script( 'wpm-nav-panel', 'wpmNavData', array(
			'languages'    => $lang_data,
			'current_id'   => $current_id,
			'current_lang' => $current_lang,
		) );
	}

	// -------------------------------------------------------------------------
	// Register meta box
	// -------------------------------------------------------------------------

	public function add_meta_box() {
		// All enabled post types — language + translation links.
		$post_types = get_option( 'wpm_post_types', array( 'page', 'post' ) );
		foreach ( $post_types as $screen ) {
			add_meta_box(
				'wpm-translations',
				__( '🌐 Language & Translations', 'wp-multilingual' ),
				array( $this, 'render_meta_box' ),
				$screen,
				'side',
				'high'
			);
		}

		// Navigation menus — language selector only.
		add_meta_box(
			'wpm-nav-language',
			__( '🌐 Menu Language', 'wp-multilingual' ),
			array( $this, 'render_nav_meta_box' ),
			'wp_navigation',
			'side',
			'high'
		);
	}

	// -------------------------------------------------------------------------
	// Render
	// -------------------------------------------------------------------------

	public function render_meta_box( $post ) {
		wp_nonce_field( 'wpm_meta_box_nonce', 'wpm_meta_box_nonce' );

		$manager   = WPM_Language_Manager::instance();
		$languages = $manager->get_all();

		if ( empty( $languages ) ) {
			echo '<p>' . esc_html__( 'No languages configured yet. Go to Multilingual → Languages.', 'wp-multilingual' ) . '</p>';
			return;
		}

		$current_lang = get_post_meta( $post->ID, '_wpm_language', true );
		if ( ! $current_lang ) {
			$current_lang = $manager->get_default();
		}

		$page_map  = $manager->get_page_map();
		$group     = $this->find_group( $post->ID, $page_map );

		?>
		<div class="wpm-mb">

			<!-- Language of this page -->
			<div class="wpm-mb-row wpm-mb-lang-row">
				<label class="wpm-mb-label"><?php esc_html_e( 'This page is in:', 'wp-multilingual' ); ?></label>
				<select name="wpm_page_lang" id="wpm-page-lang" class="wpm-mb-select">
					<?php foreach ( $languages as $slug => $cfg ) :
						$all  = wpm_get_available_languages();
						$flag = isset( $all[ $slug ]['flag'] ) ? $all[ $slug ]['flag'] : '🌐';
					?>
						<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $current_lang, $slug ); ?>>
							<?php echo esc_html( $flag . ' ' . $cfg['label'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<!-- Translation links for other languages -->
			<div class="wpm-mb-divider"></div>
			<p class="wpm-mb-section-title"><?php esc_html_e( 'Translations', 'wp-multilingual' ); ?></p>

			<?php foreach ( $languages as $slug => $cfg ) :
				if ( $slug === $current_lang ) continue;

				$all       = wpm_get_available_languages();
				$flag      = isset( $all[ $slug ]['flag'] ) ? $all[ $slug ]['flag'] : '🌐';
				$linked_id = isset( $group[ $slug ] ) ? (int) $group[ $slug ] : 0;

				// Get all pages that are set to this language (or unassigned).
				$candidates = $this->get_pages_for_language( $slug, $post->ID );
			?>
				<div class="wpm-mb-row">
					<label class="wpm-mb-flag-label">
						<span class="wpm-mb-flag"><?php echo esc_html( $flag ); ?></span>
						<?php echo esc_html( $cfg['label'] ); ?>
					</label>

					<?php if ( $linked_id ) :
						$linked_title = get_the_title( $linked_id );
					?>
						<div class="wpm-mb-linked">
							<span class="wpm-mb-linked-title" title="ID: <?php echo esc_attr( $linked_id ); ?>">
								<?php echo esc_html( $linked_title ); ?>
							</span>
							<a href="<?php echo esc_url( get_edit_post_link( $linked_id ) ); ?>" class="wpm-mb-edit-link" title="<?php esc_attr_e( 'Edit translation', 'wp-multilingual' ); ?>">✏️</a>
							<button type="button" class="wpm-mb-unlink" data-lang="<?php echo esc_attr( $slug ); ?>" title="<?php esc_attr_e( 'Unlink', 'wp-multilingual' ); ?>">✕</button>
						</div>
						<input type="hidden" name="wpm_translations[<?php echo esc_attr( $slug ); ?>]" value="<?php echo esc_attr( $linked_id ); ?>" class="wpm-trans-input" data-lang="<?php echo esc_attr( $slug ); ?>" />
					<?php else : ?>
						<select name="wpm_translations[<?php echo esc_attr( $slug ); ?>]"
								class="wpm-mb-select wpm-trans-select"
								data-lang="<?php echo esc_attr( $slug ); ?>">
							<option value=""><?php esc_html_e( '— not set —', 'wp-multilingual' ); ?></option>
							<?php foreach ( $candidates as $p ) : ?>
								<option value="<?php echo esc_attr( $p->ID ); ?>">
									<?php echo esc_html( $p->post_title ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>

		</div><!-- .wpm-mb -->
		<?php
	}

	// -------------------------------------------------------------------------
	// Render — Navigation
	// -------------------------------------------------------------------------

	public function render_nav_meta_box( $post ) {
		wp_nonce_field( 'wpm_nav_nonce', 'wpm_nav_nonce' );

		$manager   = WPM_Language_Manager::instance();
		$languages = $manager->get_all();

		if ( empty( $languages ) ) {
			echo '<p>' . esc_html__( 'No languages configured yet. Go to Multilingual → Languages.', 'wp-multilingual' ) . '</p>';
			return;
		}

		$menus        = get_option( 'wpm_menus', array() );
		$current_lang = '';

		// Detect which language currently points to this nav ID.
		foreach ( $menus as $slug => $nav_id ) {
			if ( (int) $nav_id === $post->ID ) {
				$current_lang = $slug;
				break;
			}
		}

		$all = wpm_get_available_languages();
		?>
		<div class="wpm-mb">
			<div class="wpm-mb-row wpm-mb-lang-row">
				<label class="wpm-mb-label"><?php esc_html_e( 'This menu is for:', 'wp-multilingual' ); ?></label>
				<select name="wpm_nav_lang" id="wpm-nav-lang" class="wpm-mb-select">
					<option value=""><?php esc_html_e( '— not assigned —', 'wp-multilingual' ); ?></option>
					<?php foreach ( $languages as $slug => $cfg ) :
						$flag = isset( $all[ $slug ]['flag'] ) ? $all[ $slug ]['flag'] : '🌐';
						// Disable if this slug already points to a different nav.
						$taken    = isset( $menus[ $slug ] ) && (int) $menus[ $slug ] !== $post->ID;
						$taken_id = $taken ? (int) $menus[ $slug ] : 0;
					?>
						<option value="<?php echo esc_attr( $slug ); ?>"
							<?php selected( $current_lang, $slug ); ?>
							<?php disabled( $taken ); ?>>
							<?php echo esc_html( $flag . ' ' . $cfg['label'] ); ?>
							<?php if ( $taken ) : ?>
								<?php echo esc_html( sprintf( __( '(used by "%s")', 'wp-multilingual' ), get_the_title( $taken_id ) ) ); ?>
							<?php endif; ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<?php if ( $current_lang ) : ?>
				<p class="wpm-mb-status wpm-mb-status-ok">
					✅ <?php echo esc_html( sprintf( __( 'Assigned as the %s menu.', 'wp-multilingual' ), $languages[ $current_lang ]['label'] ) ); ?>
				</p>
			<?php else : ?>
				<p class="wpm-mb-status">
					<?php esc_html_e( 'Select a language to assign this menu.', 'wp-multilingual' ); ?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Save
	// -------------------------------------------------------------------------

	public function save_meta_box( $post_id, $post ) {
		// Security checks.
		if ( ! isset( $_POST['wpm_meta_box_nonce'] ) && ! isset( $_POST['wpm_nav_nonce'] ) ) {
			return;
		}

		// Navigation save.
		if ( 'wp_navigation' === $post->post_type ) {
			$this->save_nav_meta_box( $post_id );
			return;
		}

		if ( ! isset( $_POST['wpm_meta_box_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( $_POST['wpm_meta_box_nonce'], 'wpm_meta_box_nonce' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$post_types = get_option( 'wpm_post_types', array( 'page', 'post' ) );
		if ( ! in_array( $post->post_type, $post_types, true ) ) {
			return;
		}

		$lang = isset( $_POST['wpm_page_lang'] ) ? sanitize_key( $_POST['wpm_page_lang'] ) : '';
		if ( $lang ) {
			update_post_meta( $post_id, '_wpm_language', $lang );
		}

		// Build the translation group.
		$translations = isset( $_POST['wpm_translations'] ) ? (array) $_POST['wpm_translations'] : array();
		$group        = array( $lang => $post_id );

		foreach ( $translations as $t_lang => $t_id ) {
			$t_lang = sanitize_key( $t_lang );
			$t_id   = absint( $t_id );
			if ( $t_lang && $t_id ) {
				$group[ $t_lang ] = $t_id;
				// Also stamp the language on the linked page.
				update_post_meta( $t_id, '_wpm_language', $t_lang );
			}
		}

		// Update page map.
		$page_map  = get_option( 'wpm_page_map', array() );
		$group_key = null;

		foreach ( $page_map as $key => $g ) {
			if ( in_array( $post_id, array_map( 'intval', $g ), true ) ) {
				$group_key = $key;
				break;
			}
		}

		if ( $group_key ) {
			$page_map[ $group_key ] = $group;
		} else {
			$page_map[ 'group_' . $post_id ] = $group;
		}

		update_option( 'wpm_page_map', $page_map );
	}

	private function save_nav_meta_box( $post_id ) {
		if ( ! isset( $_POST['wpm_nav_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( $_POST['wpm_nav_nonce'], 'wpm_nav_nonce' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$lang  = isset( $_POST['wpm_nav_lang'] ) ? sanitize_key( $_POST['wpm_nav_lang'] ) : '';
		$menus = get_option( 'wpm_menus', array() );

		// Remove this post ID from any existing language slot.
		foreach ( $menus as $slug => $nav_id ) {
			if ( (int) $nav_id === $post_id ) {
				unset( $menus[ $slug ] );
			}
		}

		// Assign to new language slot.
		if ( $lang ) {
			$menus[ $lang ] = $post_id;
		}

		update_option( 'wpm_menus', $menus );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Find the translation group that contains $post_id.
	 */
	private function find_group( $post_id, $page_map ) {
		foreach ( $page_map as $group ) {
			if ( in_array( (int) $post_id, array_map( 'intval', $group ), true ) ) {
				return $group;
			}
		}
		return array();
	}

	/**
	 * Get published pages/posts that are assigned to $lang (or not yet assigned).
	 * Excludes the current page being edited.
	 */
	private function get_pages_for_language( $lang, $exclude_id ) {
		$post_types = get_option( 'wpm_post_types', array( 'page', 'post' ) );

		// Posts explicitly set to this language.
		$assigned = get_posts( array(
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'exclude'        => array( $exclude_id ),
			'meta_query'     => array(
				array(
					'key'   => '_wpm_language',
					'value' => $lang,
				),
			),
			'orderby' => 'title',
			'order'   => 'ASC',
		) );

		// Posts with no language set yet.
		$unassigned = get_posts( array(
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'exclude'        => array( $exclude_id ),
			'meta_query'     => array(
				array(
					'key'     => '_wpm_language',
					'compare' => 'NOT EXISTS',
				),
			),
			'orderby' => 'title',
			'order'   => 'ASC',
		) );

		return array_merge( $assigned, $unassigned );
	}

	// -------------------------------------------------------------------------
	// Assets
	// -------------------------------------------------------------------------

	public function enqueue_assets( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		wp_enqueue_style(
			'wpm-meta-box',
			WPM_URL . 'admin/css/meta-box.css',
			array(),
			WPM_VERSION
		);
		wp_enqueue_script(
			'wpm-meta-box',
			WPM_URL . 'admin/js/meta-box.js',
			array( 'jquery' ),
			WPM_VERSION,
			true
		);
	}
}
