<?php
/**
 * Language and translations of terms, for taxonomies enabled in Settings.
 *
 * @package PixelLang_Multilingual
 */

defined( 'ABSPATH' ) || exit;

class WPM_Term_Language {

	private static $instance = null;

	private function __construct() {
		add_action( 'admin_init', array( $this, 'register_hooks' ) );
	}

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register_hooks() {
		if ( ! WPM_Language_Manager::instance()->get_all() ) {
			return;
		}
		foreach ( WPM_Settings::get_taxonomies() as $taxonomy ) {
			add_action( "{$taxonomy}_add_form_fields", array( $this, 'render_add_fields' ) );
			add_action( "{$taxonomy}_edit_form_fields", array( $this, 'render_edit_fields' ), 10, 2 );
			add_action( "created_{$taxonomy}", array( $this, 'save_term' ), 10, 1 );
			add_action( "edited_{$taxonomy}", array( $this, 'save_term' ), 10, 1 );
			add_filter( "manage_edit-{$taxonomy}_columns", array( $this, 'add_column' ) );
			add_filter( "manage_{$taxonomy}_custom_column", array( $this, 'render_column' ), 10, 3 );
		}
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function enqueue_assets( $hook ) {
		if ( ! in_array( $hook, array( 'edit-tags.php', 'term.php' ), true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( $screen && WPM_Settings::is_translatable_taxonomy( $screen->taxonomy ) ) {
			wp_enqueue_style( 'wpm-columns', WPM_URL . 'admin/css/columns.css', array(), WPM_VERSION );
		}
	}

	private function language_select( $current, $id = 'wpm-term-lang' ) {
		?>
		<select name="wpm_term_lang" id="<?php echo esc_attr( $id ); ?>">
			<?php foreach ( WPM_Language_Manager::instance()->get_all() as $slug => $cfg ) : ?>
				<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $current, $slug ); ?>><?php echo esc_html( $cfg['label'] ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	public function render_add_fields( $taxonomy ) {
		wp_nonce_field( 'wpm_term_nonce', 'wpm_term_nonce' );
		?>
		<div class="form-field term-wpm-lang-wrap">
			<label for="wpm-term-lang"><?php esc_html_e( 'Language', 'pixellang-multilingual' ); ?></label>
			<?php $this->language_select( WPM_Language_Manager::instance()->get_default() ); ?>
			<p><?php esc_html_e( 'Link translations after creating the term, from its edit screen.', 'pixellang-multilingual' ); ?></p>
		</div>
		<?php
	}

	/**
	 * @param WP_Term $term     Term.
	 * @param string  $taxonomy Taxonomy.
	 */
	public function render_edit_fields( $term, $taxonomy ) {
		$manager = WPM_Language_Manager::instance();
		$lang    = WPM_Translations::get_language( 'term', $term->term_id );
		$group   = WPM_Translations::get_translations( 'term', $term->term_id );
		wp_nonce_field( 'wpm_term_nonce', 'wpm_term_nonce' );
		?>
		<tr class="form-field term-wpm-lang-wrap">
			<th scope="row"><label for="wpm-term-lang"><?php esc_html_e( 'Language', 'pixellang-multilingual' ); ?></label></th>
			<td>
				<?php $this->language_select( $lang ? $lang : $manager->get_default() ); ?>
				<?php if ( ! $lang ) : ?>
					<p class="description"><?php esc_html_e( 'No language assigned yet. Saving will assign the selected one.', 'pixellang-multilingual' ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
		foreach ( $manager->get_all() as $slug => $cfg ) :
			if ( $slug === $lang ) {
				continue;
			}
			$linked     = isset( $group[ $slug ] ) ? (int) $group[ $slug ] : 0;
			$candidates = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'exclude'    => array( $term->term_id ),
					'number'     => 300,
					'orderby'    => 'name',
					'meta_query' => array( WPM_Query_Filter::language_clause( $slug ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				)
			);
			$field_id   = 'wpm-term-trans-' . $slug;
			?>
			<tr class="form-field term-wpm-trans-wrap">
				<th scope="row">
					<label for="<?php echo esc_attr( $field_id ); ?>">
						<?php
						/* translators: %s: language name. */
						echo esc_html( sprintf( __( 'Translation (%s)', 'pixellang-multilingual' ), $cfg['label'] ) );
						?>
					</label>
				</th>
				<td>
					<select name="wpm_term_translations[<?php echo esc_attr( $slug ); ?>]" id="<?php echo esc_attr( $field_id ); ?>">
						<option value=""><?php esc_html_e( 'No translation', 'pixellang-multilingual' ); ?></option>
						<?php if ( ! is_wp_error( $candidates ) ) : ?>
							<?php foreach ( $candidates as $candidate ) : ?>
								<option value="<?php echo esc_attr( $candidate->term_id ); ?>" <?php selected( $linked, $candidate->term_id ); ?>><?php echo esc_html( $candidate->name ); ?></option>
							<?php endforeach; ?>
						<?php endif; ?>
					</select>
				</td>
			</tr>
			<?php
		endforeach;
	}

	/**
	 * @param int $term_id Term ID.
	 */
	public function save_term( $term_id ) {
		if ( ! isset( $_POST['wpm_term_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpm_term_nonce'] ) ), 'wpm_term_nonce' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_term', $term_id ) ) {
			return;
		}
		$term    = get_term( $term_id );
		$manager = WPM_Language_Manager::instance();
		$lang    = isset( $_POST['wpm_term_lang'] ) ? sanitize_key( wp_unslash( $_POST['wpm_term_lang'] ) ) : '';
		if ( ! $term || is_wp_error( $term ) || ! $manager->is_valid( $lang ) ) {
			return;
		}
		WPM_Translations::set_language( 'term', $term_id, $lang );

		if ( ! isset( $_POST['wpm_term_translations'] ) ) {
			return; // Add form: no translation fields.
		}
		$group = array( $lang => (int) $term_id );
		foreach ( (array) wp_unslash( $_POST['wpm_term_translations'] ) as $t_lang => $t_id ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below.
			$t_lang = sanitize_key( $t_lang );
			$t_id   = absint( $t_id );
			if ( ! $t_id || $t_lang === $lang || ! $manager->is_valid( $t_lang ) ) {
				continue;
			}
			$target = get_term( $t_id );
			if ( ! $target || is_wp_error( $target ) || $target->taxonomy !== $term->taxonomy || ! current_user_can( 'edit_term', $t_id ) ) {
				continue;
			}
			$target_lang = WPM_Translations::get_language( 'term', $t_id );
			if ( $target_lang && $target_lang !== $t_lang ) {
				continue;
			}
			$group[ $t_lang ] = $t_id;
		}
		$current = WPM_Translations::get_translations( 'term', $term_id );
		ksort( $current );
		ksort( $group );
		if ( $current !== $group ) {
			WPM_Translations::unlink( 'term', $term_id );
			if ( count( $group ) > 1 ) {
				WPM_Translations::link( 'term', $group );
			}
		}
	}

	public function add_column( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'name' === $key ) {
				$new['wpm_language'] = __( 'Language', 'pixellang-multilingual' );
			}
		}
		return $new;
	}

	public function render_column( $content, $column, $term_id ) {
		if ( 'wpm_language' !== $column ) {
			return $content;
		}
		$lang = WPM_Translations::get_language( 'term', $term_id );
		if ( ! $lang ) {
			return '<span class="wpm-col-unset">' . esc_html__( 'Not set', 'pixellang-multilingual' ) . '</span>';
		}
		$out   = WPM_Admin::language_chip( $lang, false, false );
		$group = WPM_Translations::get_translations( 'term', $term_id );
		unset( $group[ $lang ] );
		if ( $group ) {
			$out .= ' <span class="wpm-trans">';
			foreach ( $group as $slug => $id ) {
				$out .= sprintf(
					'<a class="wpm-trans__item is-published" href="%s" title="%s">%s</a>',
					esc_url( (string) get_edit_term_link( $id ) ),
					esc_attr( WPM_Language_Manager::instance()->get_label( $slug ) ),
					esc_html( strtoupper( $slug ) )
				);
			}
			$out .= '</span>';
		}
		return $out;
	}
}
