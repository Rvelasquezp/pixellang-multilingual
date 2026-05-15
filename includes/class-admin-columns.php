<?php
defined( 'ABSPATH' ) || exit;

/**
 * Adds Language and Translations columns to the Pages (and Posts) list table.
 */
class WPM_Admin_Columns {

	private static $instance = null;

	private function __construct() {
		foreach ( array( 'page', 'post' ) as $type ) {
			add_filter( "manage_{$type}s_columns",              array( $this, 'add_columns' ) );
			add_action( "manage_{$type}s_custom_column",        array( $this, 'render_column' ), 10, 2 );
		}
		add_action( 'admin_head', array( $this, 'column_styles' ) );
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
					$new_url = admin_url( 'post-new.php?post_type=page' );
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
		if ( ! $screen || ! in_array( $screen->id, array( 'edit-page', 'edit-post' ), true ) ) {
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
		</style>
		<?php
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
