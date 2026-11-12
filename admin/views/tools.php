<?php
/**
 * Tools: maintenance tasks and the 1.0 migration report.
 *
 * @package PixelLang_Multilingual
 */

defined( 'ABSPATH' ) || exit;

$wpm_stats   = WPM_Translations::get_stats();
$wpm_report  = get_option( WPM_Migration::REPORT );
$wpm_reasons = WPM_Migration::reasons();
$wpm_default = WPM_Language_Manager::instance()->get_default();
?>
<div class="wpm-page-head">
	<div>
		<h2 class="wpm-page-head__title"><?php esc_html_e( 'Tools', 'pixellang-multilingual' ); ?></h2>
		<p class="wpm-page-head__text"><?php esc_html_e( 'Maintenance tasks. None of them deletes content.', 'pixellang-multilingual' ); ?></p>
	</div>
</div>

<div class="wpm-grid wpm-grid--tools">
	<form class="wpm-card wpm-tool" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
		data-wpm-confirm="<?php esc_attr_e( 'Assign the default language to every selected content that has no language?', 'pixellang-multilingual' ); ?>">
		<input type="hidden" name="action" value="wpm_tools" />
		<input type="hidden" name="task" value="assign_default" />
		<?php wp_nonce_field( 'wpm_tools' ); ?>
		<h3 class="wpm-card__title"><?php WPM_Icons::render( 'tag', 18 ); ?><?php esc_html_e( 'Assign the default language', 'pixellang-multilingual' ); ?></h3>
		<p class="wpm-card__intro">
			<?php
			/* translators: %s: default language name. */
			echo esc_html( sprintf( __( 'Content without language is set to %s. Useful right after installing the plugin on an existing site.', 'pixellang-multilingual' ), WPM_Language_Manager::instance()->get_label( $wpm_default ) ) );
			?>
		</p>
		<fieldset class="wpm-checks">
			<legend class="screen-reader-text"><?php esc_html_e( 'Content types', 'pixellang-multilingual' ); ?></legend>
			<?php
			$wpm_any = false;
			foreach ( WPM_Settings::get_post_types() as $wpm_type ) :
				$wpm_obj   = get_post_type_object( $wpm_type );
				$wpm_count = isset( $wpm_stats['unassigned'][ $wpm_type ] ) ? (int) $wpm_stats['unassigned'][ $wpm_type ] : 0;
				if ( ! $wpm_obj ) {
					continue;
				}
				$wpm_any = $wpm_any || $wpm_count;
				?>
				<label class="wpm-check">
					<input type="checkbox" name="post_types[]" value="<?php echo esc_attr( $wpm_type ); ?>" <?php checked( $wpm_count > 0 ); ?> <?php disabled( 0 === $wpm_count ); ?> />
					<span><?php echo esc_html( $wpm_obj->labels->name ); ?></span>
					<span class="wpm-badge<?php echo $wpm_count ? ' wpm-badge--warning' : ''; ?>"><?php echo esc_html( number_format_i18n( $wpm_count ) ); ?></span>
				</label>
			<?php endforeach; ?>
		</fieldset>
		<button type="submit" class="wpm-btn wpm-btn--primary" <?php disabled( ! $wpm_any ); ?>><?php esc_html_e( 'Assign', 'pixellang-multilingual' ); ?></button>
	</form>

	<form class="wpm-card wpm-tool" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="wpm_tools" />
		<input type="hidden" name="task" value="recount" />
		<?php wp_nonce_field( 'wpm_tools' ); ?>
		<h3 class="wpm-card__title"><?php WPM_Icons::render( 'refresh', 18 ); ?><?php esc_html_e( 'Recalculate statistics', 'pixellang-multilingual' ); ?></h3>
		<p class="wpm-card__intro"><?php esc_html_e( 'Counters are cached for one hour and refreshed when content changes. Recalculate them after an import or a direct database change.', 'pixellang-multilingual' ); ?></p>
		<button type="submit" class="wpm-btn"><?php esc_html_e( 'Recalculate', 'pixellang-multilingual' ); ?></button>
	</form>

	<?php if ( wp_is_block_theme() ) : ?>
		<div class="wpm-card wpm-tool">
			<h3 class="wpm-card__title"><?php WPM_Icons::render( 'layout', 18 ); ?><?php esc_html_e( 'Repair template parts', 'pixellang-multilingual' ); ?></h3>
			<p class="wpm-card__intro"><?php esc_html_e( 'Attaches the active theme to template parts that belong to no theme, so the Site Editor lists them again. Parts of other themes are not touched.', 'pixellang-multilingual' ); ?></p>
			<button type="button" class="wpm-btn" id="wpm-repair-templates"
				data-wpm-confirm="<?php esc_attr_e( 'Attach the active theme to template parts that have no theme?', 'pixellang-multilingual' ); ?>"><?php esc_html_e( 'Repair', 'pixellang-multilingual' ); ?></button>
		</div>
	<?php endif; ?>
</div>

<section class="wpm-card" aria-labelledby="wpm-migration-title">
	<div class="wpm-card__head">
		<h3 class="wpm-card__title" id="wpm-migration-title"><?php WPM_Icons::render( 'shield', 18 ); ?><?php esc_html_e( 'Migration from version 1.0', 'pixellang-multilingual' ); ?></h3>
	</div>
	<?php if ( ! is_array( $wpm_report ) ) : ?>
		<p class="wpm-card__intro"><?php esc_html_e( 'Nothing was migrated: this site started with version 1.1 or later.', 'pixellang-multilingual' ); ?></p>
	<?php else : ?>
		<p class="wpm-card__intro">
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: date, 2: number of source groups, 3: number of migrated groups. */
					__( 'Migrated on %1$s: %2$d groups of the old page map were read and %3$d translation groups were created. The old map (option wpm_page_map) is kept unchanged as a backup.', 'pixellang-multilingual' ),
					mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $wpm_report['date'] ),
					(int) $wpm_report['source'],
					(int) $wpm_report['groups']
				)
			);
			?>
		</p>
		<?php if ( $wpm_report['skipped'] ) : ?>
			<details class="wpm-disclosure">
				<summary>
					<?php
					/* translators: %d: number of entries. */
					echo esc_html( sprintf( _n( '%d entry ignored', '%d entries ignored', count( $wpm_report['skipped'] ), 'pixellang-multilingual' ), count( $wpm_report['skipped'] ) ) );
					?>
				</summary>
				<div class="wpm-table-wrap">
					<table class="wpm-table">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Old group', 'pixellang-multilingual' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Language', 'pixellang-multilingual' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Content', 'pixellang-multilingual' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Reason', 'pixellang-multilingual' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $wpm_report['skipped'] as $wpm_row ) : ?>
								<tr>
									<td><code><?php echo esc_html( $wpm_row['group'] ); ?></code></td>
									<td><?php echo esc_html( strtoupper( $wpm_row['lang'] ) ); ?></td>
									<td>
										<?php if ( get_post( $wpm_row['post'] ) ) : ?>
											<a href="<?php echo esc_url( (string) get_edit_post_link( $wpm_row['post'] ) ); ?>"><?php echo esc_html( _draft_or_post_title( $wpm_row['post'] ) ); ?></a>
										<?php else : ?>
											#<?php echo esc_html( $wpm_row['post'] ); ?>
										<?php endif; ?>
									</td>
									<td><?php echo esc_html( isset( $wpm_reasons[ $wpm_row['reason'] ] ) ? $wpm_reasons[ $wpm_row['reason'] ] : $wpm_row['reason'] ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</details>
		<?php endif; ?>
	<?php endif; ?>
</section>

<section class="wpm-card" aria-labelledby="wpm-system-title">
	<h3 class="wpm-card__title" id="wpm-system-title"><?php WPM_Icons::render( 'info', 18 ); ?><?php esc_html_e( 'System', 'pixellang-multilingual' ); ?></h3>
	<dl class="wpm-dl">
		<dt><?php esc_html_e( 'Plugin version', 'pixellang-multilingual' ); ?></dt><dd><?php echo esc_html( WPM_VERSION ); ?></dd>
		<dt><?php esc_html_e( 'Data version', 'pixellang-multilingual' ); ?></dt><dd><?php echo esc_html( (string) get_option( WPM_Migration::OPTION, 1 ) ); ?></dd>
		<dt>WordPress</dt><dd><?php echo esc_html( get_bloginfo( 'version' ) ); ?></dd>
		<dt>PHP</dt><dd><?php echo esc_html( PHP_VERSION ); ?></dd>
		<dt><?php esc_html_e( 'Theme', 'pixellang-multilingual' ); ?></dt><dd><?php echo esc_html( wp_get_theme()->get( 'Name' ) . ( wp_is_block_theme() ? ' · ' . __( 'block theme', 'pixellang-multilingual' ) : ' · ' . __( 'classic theme', 'pixellang-multilingual' ) ) ); ?></dd>
		<dt><?php esc_html_e( 'Permalinks', 'pixellang-multilingual' ); ?></dt><dd><code><?php echo esc_html( get_option( 'permalink_structure' ) ? get_option( 'permalink_structure' ) : __( 'plain', 'pixellang-multilingual' ) ); ?></code></dd>
	</dl>
</section>
