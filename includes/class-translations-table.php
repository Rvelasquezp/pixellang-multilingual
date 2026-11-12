<?php
/**
 * Translations screen table: one row per source content, one column per
 * other language. Paginated server side, so it stays fast on large sites.
 *
 * @package PixelLang_Multilingual
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class WPM_Translations_Table extends WP_List_Table {

	/**
	 * Post type shown.
	 *
	 * @var string
	 */
	public $post_type = 'page';

	/**
	 * Language of the rows (source).
	 *
	 * @var string
	 */
	public $source = '';

	/**
	 * Only rows missing a translation in this language.
	 *
	 * @var string
	 */
	public $missing = '';

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'wpm-translation',
				'plural'   => 'wpm-translations',
				'ajax'     => false,
			)
		);
		$manager = WPM_Language_Manager::instance();
		$types   = WPM_Settings::get_post_types();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters.
		// Not "post_type": on admin.php that argument makes WordPress look for
		// the screen under the menu of that post type and refuse to load it.
		$type            = isset( $_GET['content_type'] ) ? sanitize_key( wp_unslash( $_GET['content_type'] ) ) : '';
		$this->post_type = in_array( $type, $types, true ) ? $type : ( in_array( 'page', $types, true ) ? 'page' : reset( $types ) );
		$source          = isset( $_GET['source'] ) ? sanitize_key( wp_unslash( $_GET['source'] ) ) : '';
		$this->source    = $manager->is_valid( $source ) ? $source : $manager->get_default();
		$missing         = isset( $_GET['missing'] ) ? sanitize_key( wp_unslash( $_GET['missing'] ) ) : '';
		$this->missing   = ( $manager->is_valid( $missing ) && $missing !== $this->source ) ? $missing : '';
		// phpcs:enable
	}

	public function get_columns() {
		$columns = array(
			'title'  => __( 'Content', 'pixellang-multilingual' ),
			'status' => __( 'Status', 'pixellang-multilingual' ),
		);
		foreach ( WPM_Language_Manager::instance()->get_all() as $slug => $cfg ) {
			if ( $slug !== $this->source ) {
				$columns[ 'lang_' . $slug ] = WPM_Admin::language_chip( $slug, true, false );
			}
		}
		return $columns;
	}

	protected function get_sortable_columns() {
		return array( 'title' => array( 'title', false ) );
	}

	protected function get_primary_column_name() {
		return 'title';
	}

	public function prepare_items() {
		$per_page = $this->get_items_per_page( 'wpm_translations_per_page', 20 );
		$meta     = array(
			'relation' => 'AND',
			array(
				'key'   => WPM_Translations::META_LANG,
				'value' => $this->source,
			),
		);
		if ( $this->missing ) {
			// Groups are serialized arrays keyed by language code: a missing
			// key means the row has no translation in that language.
			$meta[] = array(
				'relation' => 'OR',
				array(
					'key'     => WPM_Translations::META_GROUP,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => WPM_Translations::META_GROUP,
					'value'   => sprintf( 's:%d:"%s";', strlen( $this->missing ), $this->missing ),
					'compare' => 'NOT LIKE',
				),
			);
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$orderby = isset( $_GET['orderby'] ) && 'title' === $_GET['orderby'] ? 'title' : 'modified';
		$order   = isset( $_GET['order'] ) && 'asc' === strtolower( sanitize_key( wp_unslash( $_GET['order'] ) ) ) ? 'ASC' : ( 'title' === $orderby ? 'ASC' : 'DESC' );
		$search  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		// phpcs:enable

		$query = new WP_Query(
			array(
				'post_type'              => $this->post_type,
				'post_status'            => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page'         => $per_page,
				'paged'                  => $this->get_pagenum(),
				's'                      => $search,
				'orderby'                => $orderby,
				'order'                  => $order,
				'meta_query'             => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'update_post_term_cache' => false,
			)
		);
		$this->items = $query->posts;

		// Prime caches of every linked post in one query.
		$linked = array();
		foreach ( $this->items as $post ) {
			$linked = array_merge( $linked, array_values( WPM_Translations::get_post_translations( $post->ID ) ) );
		}
		if ( $linked ) {
			_prime_post_caches( array_unique( $linked ), false, true );
		}

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'title' );
		$this->set_pagination_args(
			array(
				'total_items' => (int) $query->found_posts,
				'per_page'    => $per_page,
			)
		);
	}

	public function no_items() {
		if ( $this->missing ) {
			esc_html_e( 'Nothing is missing here: every item has this translation.', 'pixellang-multilingual' );
		} else {
			esc_html_e( 'No content in this language yet.', 'pixellang-multilingual' );
		}
	}

	/**
	 * @param WP_Post $post Row post.
	 * @return string
	 */
	protected function column_title( $post ) {
		$title = _draft_or_post_title( $post );
		$edit  = get_edit_post_link( $post->ID );
		$out   = $edit && current_user_can( 'edit_post', $post->ID )
			? sprintf( '<a class="row-title" href="%s">%s</a>', esc_url( $edit ), esc_html( $title ) )
			: sprintf( '<strong>%s</strong>', esc_html( $title ) );
		$actions = array();
		if ( 'publish' === $post->post_status ) {
			$actions['view'] = sprintf( '<a href="%s">%s</a>', esc_url( get_permalink( $post ) ), esc_html__( 'View', 'pixellang-multilingual' ) );
		}
		return $out . $this->row_actions( $actions );
	}

	/**
	 * @param WP_Post $post Row post.
	 * @return string
	 */
	protected function column_status( $post ) {
		ob_start();
		WPM_Admin::status_badge( $post->post_status );
		return ob_get_clean();
	}

	/**
	 * Language columns.
	 *
	 * @param WP_Post $post        Row post.
	 * @param string  $column_name Column.
	 * @return string
	 */
	protected function column_default( $post, $column_name ) {
		if ( 0 !== strpos( $column_name, 'lang_' ) ) {
			return '';
		}
		$lang = substr( $column_name, 5 );
		$id   = WPM_Translations::get_post_translation( $post->ID, $lang );
		if ( $id && get_post( $id ) ) {
			$status = get_post_status( $id );
			return sprintf(
				'<div class="wpm-cell is-%1$s"><a class="wpm-cell__title" href="%2$s">%3$s</a><span class="wpm-cell__status">%4$s</span></div>',
				'publish' === $status ? 'done' : 'draft',
				esc_url( (string) get_edit_post_link( $id ) ),
				esc_html( _draft_or_post_title( $id ) ),
				esc_html( WPM_Admin::status_label( $status ) )
			);
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return '<span class="wpm-muted">–</span>';
		}
		return sprintf(
			'<a class="wpm-btn wpm-btn--small wpm-btn--ghost" href="%1$s" aria-label="%2$s">%3$s%4$s</a>',
			esc_url( WPM_Admin::create_translation_url( $post->ID, $lang ) ),
			/* translators: 1: language name, 2: content title. */
			esc_attr( sprintf( __( 'Create the %1$s translation of “%2$s”', 'pixellang-multilingual' ), WPM_Language_Manager::instance()->get_label( $lang ), _draft_or_post_title( $post ) ) ),
			WPM_Icons::get( 'plus', 14 ),
			esc_html__( 'Create', 'pixellang-multilingual' )
		);
	}
}
