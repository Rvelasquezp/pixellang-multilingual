<?php
defined( 'ABSPATH' ) || exit;

/**
 * Adds Language and Translations columns to the Pages (and Posts) list table.
 */
class WPM_Admin_Columns {

	private static $instance = null;

	private function __construct() {
		add_action( 'admin_init', array( $this, 'register_column_hooks' ) );
		add_action( 'admin_head', array( $this, 'column_styles' ) );
	}

	public function register_column_hooks() {
		$post_types = get_option( 'wpm_post_types', array( 'page', 'post' ) );
		foreach ( $post_types as $type ) {
			add_filter( "manage_{$type}s_columns",       array( $this, 'add_columns' ) );
			add_action( "manage_{$type}s_custom_column", array( $this, 'render_column' ), 10, 2 );
		}
		add_action( 'quick_edit_custom_box', array( $this, 'render_quick_edit_box' ), 10, 2 );
		add_action( 'save_post',             array( $this, 'save_quick_edit' ), 10, 2 );
	}

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	// -------------------------------------------------------------------------
	// Register columns
	// -------------------------------------------------------------------------

	public function add_columns( $columns ) {
		// Insert after 'title'.
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['wpm_language']     = __( 'Language', 'wp-multilingual' );
				$new['wpm_translations'] = __( 'Translations', 'wp-multilingual' );
			}
		}
		return $new;
	}

	// -------------------------------------------------------------------------
	// Render column content
	// -------------------------------------------------------------------------

	public function render_column( $column, $post_id ) {
		$post_type = get_post_type( $post_id );
		if ( 'wpm_language' !== $column && 'wpm_translations' !== $column ) {
			return;
		}

		$manager   = WPM_Language_Manager::instance();
		$languages = $manager->get_all();
		$all       = wpm_get_available_languages();

		if ( empty( $languages ) ) {
			echo '<span style="color:#aaa;">—</span>';
			return;
		}

		$lang = get_post_meta( $post_id, '_wpm_language', true );

		// ---- Language column ----
		if ( 'wpm_language' === $column ) {
			// Hidden span used by Quick Edit JS to pre-populate the select.
			echo '<span class="wpm-qe-lang hidden">' . esc_html( $lang ) . '</span>';

			if ( ! $lang || ! isset( $languages[ $lang ] ) ) {
				echo '<span class="wpm-col-unset">' . esc_html__( 'Not set', 'wp-multilingual' ) . '</span>';
				return;
			}
			$flag  = isset( $all[ $lang ]['flag'] ) ? $all[ $lang ]['flag'] : '🌐';
			$label = $languages[ $lang ]['label'];
			echo '<span class="wpm-col-lang">'
				. esc_html( $flag ) . ' '
				. esc_html( $label )
				. '</span>';
			return;
		}

		// ---- Translations column ----
		if ( 'wpm_translations' === $column ) {
			$page_map = $manager->get_page_map();
			$group    = $this->find_group( $post_id, $page_map );

			if ( ! $lang ) {
				echo '<span style="color:#aaa;">—</span>';
				return;
			}

			$parts = array();
			foreach ( $languages as $slug => $cfg ) {
				if ( $slug === $lang ) {
					continue; // Skip current page's own language.
				}

				$flag        = isset( $all[ $slug ]['flag'] ) ? $all[ $slug ]['flag'] : '🌐';
				$linked_id   = isset( $group[ $slug ] ) ? (int) $group[ $slug ] : 0;

				if ( $linked_id ) {
					$title    = get_the_title( $linked_id );
					$edit_url = get_edit_post_link( $linked_id );
					$parts[]  = '<span class="wpm-trans-item">'
						. '<span class="wpm-trans-flag">' . esc_html( $flag ) . '</span>'
						. '<a href="' . esc_url( $edit_url ) . '" class="wpm-trans-title">'
						. esc_html( $title )
						. '</a>'
						. '<a href="' . esc_url( $edit_url ) . '" class="wpm-trans-edit button button-small">'
						. esc_html__( 'Edit', 'wp-multilingual' )
						. '</a>'
						. '</span>';
				} else {
					$new_url = admin_url( 'post-new.php?post_type=' . urlencode( $post_type ) );
					$parts[] = '<span class="wpm-trans-item wpm-trans-missing">'
						. '<span class="wpm-trans-flag">' . esc_html( $flag ) . '</span>'
						. '<span class="wpm-trans-none">' . esc_html( $cfg['label'] ) . '</span>'
						. '<a href="' . esc_url( $new_url ) . '" class="wpm-trans-add button button-small">'
						. esc_html__( '+ Create', 'wp-multilingual' )
						. '</a>'
						. '</span>';
				}
			}

			if ( empty( $parts ) ) {
				echo '<span style="color:#aaa;">—</span>';
			} else {
				echo implode( '', $parts ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
		}
	}

	// -------------------------------------------------------------------------
	// Inline styles
	// -------------------------------------------------------------------------

	public function column_styles() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}
		$post_types    = get_option( 'wpm_post_types', array( 'page', 'post' ) );
		$screen_ids    = array_map( function( $t ) { return 'edit-' . $t; }, $post_types );
		if ( ! in_array( $screen->id, $screen_ids, true ) ) {
			return;
		}
		?>
		<style>
		.column-wpm_language     { width: 100px; }
		.column-wpm_translations { width: 260px; }

		.wpm-col-lang   { font-size: 13px; }
		.wpm-col-unset  { color: #aaa; font-style: italic; font-size: 12px; }

		.wpm-trans-item {
			display: flex;
			align-items: center;
			gap: 5px;
			margin-bottom: 4px;
			flex-wrap: nowrap;
		}
		.wpm-trans-flag  { font-size: 16px; flex-shrink: 0; }
		.wpm-trans-title {
			flex: 1;
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
			max-width: 110px;
			font-size: 12px;
		}
		.wpm-trans-edit,
		.wpm-trans-add {
			flex-shrink: 0;
			font-size: 11px !important;
			padding: 1px 6px !important;
			height: auto !important;
			line-height: 1.6 !important;
		}
		.wpm-trans-add  { color: #2271b1 !important; }
		.wpm-trans-none { color: #aaa; font-size: 12px; flex: 1; }
		.wpm-trans-missing .wpm-trans-none { font-style: italic; }
		.wpm-qe-fieldset { margin-top: 8px; }
		.wpm-qe-fieldset .title { min-width: 100px; }
		</style>
		<script>
		jQuery(function($) {
			if (typeof inlineEditPost === 'undefined') return;
			var _edit = inlineEditPost.edit;
			inlineEditPost.edit = function(id) {
				_edit.apply(this, arguments);
				var postId = (typeof id === 'object') ? this.getId(id) : id;
				var lang   = $('#post-' + postId + ' .wpm-qe-lang').text().trim();
				$('select[name="wpm_quick_lang"]').val(lang);
			};
		});
		</script>
		<?php
	}

	// -------------------------------------------------------------------------
	// Quick Edit
	// -------------------------------------------------------------------------

	public function render_quick_edit_box( $column_name, $post_type ) {
		if ( 'wpm_language' !== $column_name ) {
			return;
		}
		$post_types = get_option( 'wpm_post_types', array( 'page', 'post' ) );
		if ( ! in_array( $post_type, $post_types, true ) ) {
			return;
		}

		$manager   = WPM_Language_Manager::instance();
		$languages = $manager->get_all();
		$all       = wpm_get_available_languages();

		if ( empty( $languages ) ) {
			return;
		}
		?>
		<fieldset class="inline-edit-col-left wpm-qe-fieldset">
			<div class="inline-edit-col">
				<label>
					<span class="title"><?php esc_html_e( 'Language', 'wp-multilingual' ); ?></span>
					<select name="wpm_quick_lang" id="wpm-qe-lang">
						<option value=""><?php esc_html_e( '— not set —', 'wp-multilingual' ); ?></option>
						<?php foreach ( $languages as $slug => $cfg ) :
							$flag = isset( $all[ $slug ]['flag'] ) ? $all[ $slug ]['flag'] : '🌐';
						?>
							<option value="<?php echo esc_attr( $slug ); ?>">
								<?php echo esc_html( $flag . ' ' . $cfg['label'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>
			</div>
		</fieldset>
		<?php
		wp_nonce_field( 'wpm_quick_edit_nonce', 'wpm_qe_nonce' );
	}

	public function save_quick_edit( $post_id, $post ) {
		if ( ! isset( $_POST['wpm_qe_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( $_POST['wpm_qe_nonce'], 'wpm_quick_edit_nonce' ) ) {
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

		$lang = isset( $_POST['wpm_quick_lang'] ) ? sanitize_key( $_POST['wpm_quick_lang'] ) : '';
		if ( $lang ) {
			update_post_meta( $post_id, '_wpm_language', $lang );
		} else {
			delete_post_meta( $post_id, '_wpm_language' );
		}
	}

	// -------------------------------------------------------------------------
	// Helper
	// -------------------------------------------------------------------------

	private function find_group( $post_id, $page_map ) {
		foreach ( $page_map as $group ) {
			if ( in_array( (int) $post_id, array_map( 'intval', $group ), true ) ) {
				return $group;
			}
		}
		return array();
	}
}
