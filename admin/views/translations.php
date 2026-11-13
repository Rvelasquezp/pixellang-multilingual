<?php
/**
 * Translations: status of every content in every language.
 *
 * @package PixelLang_Multilingual
 */

defined( 'ABSPATH' ) || exit;

require_once WPM_DIR . 'includes/class-translations-table.php';

$wpm_manager = WPM_Language_Manager::instance();
if ( count( $wpm_manager->get_all() ) < 2 ) {
	WPM_Admin::empty_state(
		'globe',
		__( 'Add a second language', 'pixellang-multilingual' ),
		__( 'Translations appear here once at least two languages are active.', 'pixellang-multilingual' ),
		WPM_Admin::url( 'wpm-languages' ),
		__( 'Manage languages', 'pixellang-multilingual' )
	);
	return;
}

$wpm_table = new WPM_Translations_Table();
$wpm_table->prepare_items();
$wpm_stats = WPM_Translations::get_stats();
$wpm_none  = isset( $wpm_stats['unassigned'][ $wpm_table->post_type ] ) ? (int) $wpm_stats['unassigned'][ $wpm_table->post_type ] : 0;
?>
<div class="wpm-page-head">
	<div>
		<h2 class="wpm-page-head__title"><?php esc_html_e( 'Translations', 'pixellang-multilingual' ); ?></h2>
		<p class="wpm-page-head__text"><?php esc_html_e( 'Each row is a content in the source language. Create a missing translation in one click: a draft copy opens in the editor, ready to translate.', 'pixellang-multilingual' ); ?></p>
	</div>
</div>

<nav class="wpm-segmented" aria-label="<?php esc_attr_e( 'Content type', 'pixellang-multilingual' ); ?>">
	<?php
	foreach ( WPM_Settings::get_post_types() as $wpm_type ) :
		$wpm_obj = get_post_type_object( $wpm_type );
		if ( ! $wpm_obj ) {
			continue;
		}
		$wpm_current = $wpm_type === $wpm_table->post_type;
		?>
		<a class="wpm-segmented__item<?php echo $wpm_current ? ' is-current' : ''; ?>" href="<?php echo esc_url( WPM_Admin::url( 'wpm-translations', array( 'content_type' => $wpm_type, 'source' => $wpm_table->source ) ) ); ?>"<?php echo $wpm_current ? ' aria-current="page"' : ''; ?>>
			<?php echo esc_html( $wpm_obj->labels->name ); ?>
		</a>
	<?php endforeach; ?>
</nav>

<?php
if ( $wpm_none ) {
	WPM_Admin::notice(
		'warning',
		/* translators: %s: number of items. */
		sprintf( _n( '%s item of this type has no language and is not listed.', '%s items of this type have no language and are not listed.', $wpm_none, 'pixellang-multilingual' ), number_format_i18n( $wpm_none ) ),
		sprintf( '<a class="wpm-link" href="%s">%s</a>', esc_url( admin_url( 'edit.php?post_type=' . $wpm_table->post_type . '&wpm_lang=none' ) ), esc_html__( 'Assign languages', 'pixellang-multilingual' ) )
	);
}
?>

<form method="get" class="wpm-card wpm-card--flush wpm-table-card">
	<input type="hidden" name="page" value="wpm-translations" />
	<input type="hidden" name="content_type" value="<?php echo esc_attr( $wpm_table->post_type ); ?>" />
	<div class="wpm-filters">
		<label class="wpm-field wpm-field--inline">
			<span class="wpm-field__label"><?php esc_html_e( 'Source language', 'pixellang-multilingual' ); ?></span>
			<select name="source" class="wpm-select" data-wpm-autosubmit>
				<?php foreach ( $wpm_manager->get_all() as $wpm_slug => $wpm_cfg ) : ?>
					<option value="<?php echo esc_attr( $wpm_slug ); ?>" <?php selected( $wpm_table->source, $wpm_slug ); ?>><?php echo esc_html( $wpm_cfg['label'] ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label class="wpm-field wpm-field--inline">
			<span class="wpm-field__label"><?php esc_html_e( 'Show', 'pixellang-multilingual' ); ?></span>
			<select name="missing" class="wpm-select" data-wpm-autosubmit>
				<option value=""><?php esc_html_e( 'All content', 'pixellang-multilingual' ); ?></option>
				<option value="outdated" <?php selected( $wpm_table->outdated ); ?>><?php esc_html_e( 'Translations that need an update', 'pixellang-multilingual' ); ?></option>
				<?php
				foreach ( $wpm_manager->get_all() as $wpm_slug => $wpm_cfg ) :
					if ( $wpm_slug === $wpm_table->source ) {
						continue;
					}
					?>
					<option value="<?php echo esc_attr( $wpm_slug ); ?>" <?php selected( $wpm_table->missing, $wpm_slug ); ?>>
						<?php
						/* translators: %s: language name. */
						echo esc_html( sprintf( __( 'Missing in %s', 'pixellang-multilingual' ), $wpm_cfg['label'] ) );
						?>
					</option>
				<?php endforeach; ?>
			</select>
		</label>
		<noscript><button type="submit" class="wpm-btn"><?php esc_html_e( 'Filter', 'pixellang-multilingual' ); ?></button></noscript>
		<?php $wpm_table->search_box( __( 'Search', 'pixellang-multilingual' ), 'wpm-translations' ); ?>
	</div>
	<?php $wpm_table->display(); ?>
</form>
