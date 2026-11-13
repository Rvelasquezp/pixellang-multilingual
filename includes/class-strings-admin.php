<?php
/**
 * String Translation screen: actions, REST routes and the strings table.
 *
 * @package PixelLang_Multilingual
 */

defined( 'ABSPATH' ) || exit;

class WPM_Strings_Admin {

	private static $instance = null;

	/**
	 * Capability to manage strings (filterable for editors).
	 */
	public static function capability() {
		return WPM_Strings::capability();
	}

	private function __construct() {
		add_action( 'admin_post_wpm_strings', array( $this, 'handle_action' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function enqueue() {
		if ( 'wpm-strings' !== WPM_Admin::current_screen() ) {
			return;
		}
		wp_enqueue_script( 'wpm-strings', WPM_URL . 'admin/js/strings.js', array( 'wp-api-fetch', 'wp-i18n' ), WPM_VERSION, true );
		wp_set_script_translations( 'wpm-strings', 'pixellang-multilingual', WPM_DIR . 'languages' );
	}

	// ---------------------------------------------------------------------
	// admin-post actions
	// ---------------------------------------------------------------------

	private function back( $notice, $args = array() ) {
		$keep = array();
		foreach ( array( 's', 'source', 'domain', 'missing' ) as $k ) {
			if ( ! empty( $_POST[ 'keep_' . $k ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked in handle_action().
				$keep[ $k ] = sanitize_text_field( wp_unslash( $_POST[ 'keep_' . $k ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			}
		}
		wp_safe_redirect( WPM_Admin::url( 'wpm-strings', array_merge( $keep, $args, array( 'wpm_notice' => $notice ) ) ) );
		exit;
	}

	public function handle_action() {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'pixellang-multilingual' ), 403 );
		}
		check_admin_referer( 'wpm_strings' );
		$task = isset( $_POST['task'] ) ? sanitize_key( wp_unslash( $_POST['task'] ) ) : '';

		switch ( $task ) {
			case 'register':
				$kind     = isset( $_POST['kind'] ) && 'literal' === $_POST['kind'] ? 'literal' : 'gettext';
				$original = isset( $_POST['original'] ) ? trim( wp_kses_post( wp_unslash( $_POST['original'] ) ) ) : '';
				$domain   = isset( $_POST['domain'] ) ? sanitize_text_field( wp_unslash( $_POST['domain'] ) ) : '';
				if ( '' === $original || ( 'gettext' === $kind && '' === $domain ) ) {
					$this->back( 'string_invalid' );
				}
				global $wpdb;
				$key    = WPM_Strings::key( 'literal' === $kind ? WPM_Strings::LITERAL_DOMAIN : $domain, 'literal' === $kind ? '' : sanitize_text_field( wp_unslash( $_POST['context'] ?? '' ) ), $original );
				$exists = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . WPM_Strings::table() . ' WHERE string_key = %s', $key ) ); // phpcs:ignore WordPress.DB
				$id     = WPM_Strings::register(
					array(
						'original'      => $original,
						'domain'        => $domain,
						'context'       => isset( $_POST['context'] ) ? sanitize_text_field( wp_unslash( $_POST['context'] ) ) : '',
						'source_type'   => 'literal' === $kind ? 'literal' : 'manual',
						'source_name'   => isset( $_POST['source_name'] ) ? sanitize_text_field( wp_unslash( $_POST['source_name'] ) ) : '',
						'original_lang' => isset( $_POST['original_lang'] ) ? sanitize_key( wp_unslash( $_POST['original_lang'] ) ) : '',
					)
				);
				if ( is_wp_error( $id ) ) {
					$this->back( 'string_invalid' );
				}
				$this->back( $exists ? 'string_exists' : 'string_added', array( 's' => wp_strip_all_tags( $original ) ) );
				break;

			case 'scan':
				$component = isset( $_POST['component'] ) ? sanitize_text_field( wp_unslash( $_POST['component'] ) ) : '';
				$result    = WPM_String_Scanner::scan( $component );
				if ( is_wp_error( $result ) ) {
					$this->back( 'scan_failed' );
				}
				$this->back(
					'scanned',
					array(
						'found' => $result['found'],
						'new'   => $result['new'],
						'files' => $result['files'],
					)
				);
				break;

			case 'discovery':
				$on = ! empty( $_POST['enable'] );
				WPM_Strings::set_discovery( $on ? 30 : 0 );
				$this->back( $on ? 'discovery_on' : 'discovery_off' );
				break;

			case 'delete':
				$ids  = isset( $_POST['string'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['string'] ) ) : array();
				$done = WPM_Strings::delete( $ids );
				$this->back( 'strings_deleted', array( 'count' => $done ) );
				break;

			case 'source_lang':
				WPM_Settings::update( array( 'strings_source_lang' => isset( $_POST['strings_source_lang'] ) ? wp_unslash( $_POST['strings_source_lang'] ) : 'en' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by WPM_Settings.
				$this->back( 'saved' );
				break;
		}
		wp_die( esc_html__( 'Unknown task.', 'pixellang-multilingual' ), 400 );
	}
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Strings table with inline translation fields.
 */
class WPM_Strings_Table extends WP_List_Table {

	public $filters = array();

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'wpm-string',
				'plural'   => 'wpm-strings',
				'ajax'     => false,
			)
		);
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters.
		$this->filters = array(
			'search'  => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			'source'  => isset( $_GET['source'] ) ? sanitize_text_field( wp_unslash( $_GET['source'] ) ) : '',
			'domain'  => isset( $_GET['domain'] ) ? sanitize_text_field( wp_unslash( $_GET['domain'] ) ) : '',
			'missing' => isset( $_GET['missing'] ) && WPM_Language_Manager::instance()->is_valid( sanitize_key( wp_unslash( $_GET['missing'] ) ) ) ? sanitize_key( wp_unslash( $_GET['missing'] ) ) : '',
			'orderby' => isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : '',
			'order'   => isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : '',
		);
		// phpcs:enable
	}

	public function get_columns() {
		$columns = array(
			'cb'       => '<input type="checkbox" />',
			'original' => __( 'Original text', 'pixellang-multilingual' ),
		);
		foreach ( WPM_Language_Manager::instance()->get_slugs() as $lang ) {
			$columns[ 'lang_' . $lang ] = WPM_Admin::language_chip( $lang, true, false );
		}
		$columns['actions'] = '<span class="screen-reader-text">' . esc_html__( 'Actions', 'pixellang-multilingual' ) . '</span>';
		return $columns;
	}

	protected function get_sortable_columns() {
		return array( 'original' => array( 'original', false ) );
	}

	protected function get_bulk_actions() {
		return array( 'delete' => __( 'Delete', 'pixellang-multilingual' ) );
	}

	public function prepare_items() {
		$per_page = $this->get_items_per_page( 'wpm_strings_per_page', 25 );
		$result   = WPM_Strings::query( array_merge( $this->filters, array( 'per_page' => $per_page, 'page' => $this->get_pagenum() ) ) );
		$this->items           = $result['items'];
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'original' );
		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $per_page,
			)
		);
	}

	public function no_items() {
		esc_html_e( 'No string matches. Scan your theme, turn on discovery or register a string manually.', 'pixellang-multilingual' );
	}

	protected function column_cb( $item ) {
		return sprintf(
			'<label class="screen-reader-text" for="wpm-string-%1$d">%2$s</label><input type="checkbox" id="wpm-string-%1$d" name="string[]" value="%1$d" form="wpm-strings-bulk" />',
			(int) $item->id,
			esc_html__( 'Select this string', 'pixellang-multilingual' )
		);
	}

	protected function column_original( $item ) {
		$types = array(
			'theme'   => __( 'Theme', 'pixellang-multilingual' ),
			'plugin'  => __( 'Plugin', 'pixellang-multilingual' ),
			'core'    => 'WordPress',
			'manual'  => __( 'Manual', 'pixellang-multilingual' ),
			'literal' => __( 'HTML text', 'pixellang-multilingual' ),
		);
		$locations = array_filter( (array) json_decode( (string) $item->locations, true ) );
		$meta      = array();
		$meta[]    = sprintf( '<span class="wpm-tag wpm-tag--%1$s">%2$s%3$s</span>', esc_attr( $item->source_type ), esc_html( $types[ $item->source_type ] ?? $item->source_type ), $item->source_name ? ' · ' . esc_html( $item->source_name ) : '' );
		if ( WPM_Strings::LITERAL_DOMAIN !== $item->domain ) {
			$meta[] = sprintf( '<span class="wpm-tag" title="%1$s"><code>%2$s</code></span>', esc_attr__( 'Text domain', 'pixellang-multilingual' ), esc_html( $item->domain ) );
		}
		if ( '' !== $item->context ) {
			$meta[] = sprintf( '<span class="wpm-tag" title="%1$s">%2$s</span>', esc_attr__( 'Context', 'pixellang-multilingual' ), esc_html( $item->context ) );
		}
		$out = sprintf(
			'<div class="wpm-str"><p class="wpm-str__text" lang="%1$s">%2$s</p>%3$s<div class="wpm-str__meta">%4$s</div>',
			esc_attr( WPM_Language_Manager::instance()->get_hreflang( $item->original_lang ) ),
			esc_html( $item->original ),
			$item->original_plural ? '<p class="wpm-str__plural"><span>' . esc_html__( 'Plural', 'pixellang-multilingual' ) . '</span> ' . esc_html( $item->original_plural ) . '</p>' : '',
			implode( '', $meta )
		);
		if ( $locations ) {
			$out .= sprintf(
				'<details class="wpm-str__where"><summary>%1$s</summary><ul>%2$s</ul></details>',
				/* translators: %d: number of places. */
				esc_html( sprintf( _n( 'Used in %d place', 'Used in %d places', count( $locations ), 'pixellang-multilingual' ), count( $locations ) ) ),
				implode( '', array_map( function ( $l ) {
					return '<li><code>' . esc_html( $l ) . '</code></li>';
				}, $locations ) )
			);
		}
		return $out . '</div>';
	}

	protected function column_default( $item, $column_name ) {
		if ( 0 !== strpos( $column_name, 'lang_' ) ) {
			return '';
		}
		$lang  = substr( $column_name, 5 );
		$label = WPM_Language_Manager::instance()->get_label( $lang );
		$row   = $item->translations[ $lang ] ?? null;
		$value = $row ? $row->value : '';
		$id    = 'wpm-tr-' . (int) $item->id . '-' . $lang;
		$state = $row ? 'is-done' : ( $lang === $item->original_lang ? 'is-original' : 'is-missing' );

		$html  = sprintf( '<div class="wpm-tr %s">', esc_attr( $state ) );
		$html .= sprintf(
			'<label class="screen-reader-text" for="%1$s">%2$s</label>',
			esc_attr( $id ),
			/* translators: %s: language name. */
			esc_html( sprintf( __( '%s translation', 'pixellang-multilingual' ), $label ) )
		);
		$html .= sprintf(
			'<textarea id="%1$s" class="wpm-tr__input" rows="2" data-lang="%2$s" placeholder="%3$s">%4$s</textarea>',
			esc_attr( $id ),
			esc_attr( $lang ),
			esc_attr( $lang === $item->original_lang ? __( 'Original language: leave empty to keep the original', 'pixellang-multilingual' ) : __( 'Not translated yet', 'pixellang-multilingual' ) ),
			esc_textarea( $value )
		);
		if ( $item->original_plural ) {
			$html .= sprintf(
				'<textarea class="wpm-tr__input wpm-tr__input--plural" rows="1" data-lang="%1$s" data-plural="1" aria-label="%2$s" placeholder="%3$s">%4$s</textarea>',
				esc_attr( $lang ),
				/* translators: %s: language name. */
				esc_attr( sprintf( __( '%s plural translation', 'pixellang-multilingual' ), $label ) ),
				esc_attr__( 'Plural', 'pixellang-multilingual' ),
				esc_textarea( $row ? (string) $row->value_plural : '' )
			);
		}
		return $html . '</div>';
	}

	protected function column_actions( $item ) {
		return sprintf(
			'<div class="wpm-str__actions"><button type="button" class="wpm-btn wpm-btn--small wpm-btn--primary" data-save="%1$d">%2$s</button><button type="button" class="wpm-icon-btn wpm-icon-btn--danger" data-delete="%1$d" aria-label="%3$s">%4$s</button><span class="wpm-str__status" aria-live="polite"></span></div>',
			(int) $item->id,
			esc_html__( 'Save', 'pixellang-multilingual' ),
			esc_attr( sprintf( /* translators: %s: string. */ __( 'Delete the string “%s”', 'pixellang-multilingual' ), wp_html_excerpt( $item->original, 40, '…' ) ) ),
			WPM_Icons::get( 'trash', 16 )
		);
	}

	public function single_row( $item ) {
		printf( '<tr data-string="%d">', (int) $item->id );
		$this->single_row_columns( $item );
		echo '</tr>';
	}

	/**
	 * Bulk actions submit a separate form (the filters form is GET).
	 *
	 * @param string $which top|bottom.
	 */
	protected function bulk_actions( $which = '' ) {
		if ( 'top' !== $which ) {
			return;
		}
		printf(
			'<button type="submit" class="button" form="wpm-strings-bulk" data-wpm-bulk-delete>%s</button>',
			esc_html__( 'Delete selected', 'pixellang-multilingual' )
		);
	}
}
