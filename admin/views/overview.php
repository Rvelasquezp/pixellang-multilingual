<?php
/**
 * Dashboard: language coverage and site health.
 *
 * @package PixelLang_Multilingual
 */

defined( 'ABSPATH' ) || exit;

$wpm_manager   = WPM_Language_Manager::instance();
$wpm_languages = $wpm_manager->get_all();
$wpm_default   = $wpm_manager->get_default();
$wpm_stats     = WPM_Translations::get_stats();
$wpm_types     = WPM_Settings::get_post_types();

$wpm_sum = function ( $rows ) {
	return array_sum( array_map( 'intval', (array) $rows ) );
};

if ( ! $wpm_languages ) {
	WPM_Admin::empty_state(
		'globe',
		__( 'Start by adding your languages', 'pixellang-multilingual' ),
		__( 'Choose the languages of your site and the default one. Everything else builds on them.', 'pixellang-multilingual' ),
		WPM_Admin::url( 'wpm-languages' ),
		__( 'Add languages', 'pixellang-multilingual' )
	);
	return;
}

$wpm_default_total = isset( $wpm_stats['by_lang'][ $wpm_default ] ) ? $wpm_sum( $wpm_stats['by_lang'][ $wpm_default ] ) : 0;
$wpm_unassigned    = $wpm_sum( $wpm_stats['unassigned'] );
?>
<div class="wpm-page-head">
	<div>
		<h2 class="wpm-page-head__title"><?php esc_html_e( 'Dashboard', 'pixellang-multilingual' ); ?></h2>
		<p class="wpm-page-head__text"><?php esc_html_e( 'How complete each language is, and what needs your attention.', 'pixellang-multilingual' ); ?></p>
	</div>
	<a class="wpm-btn wpm-btn--primary" href="<?php echo esc_url( WPM_Admin::url( 'wpm-translations' ) ); ?>">
		<?php WPM_Icons::render( 'files', 16 ); ?><?php esc_html_e( 'Translate content', 'pixellang-multilingual' ); ?>
	</a>
</div>

<section class="wpm-grid wpm-grid--langs" aria-label="<?php esc_attr_e( 'Languages', 'pixellang-multilingual' ); ?>">
	<?php
	foreach ( $wpm_languages as $wpm_slug => $wpm_cfg ) :
		$wpm_count = isset( $wpm_stats['by_lang'][ $wpm_slug ] ) ? $wpm_sum( $wpm_stats['by_lang'][ $wpm_slug ] ) : 0;
		$wpm_is_default = $wpm_slug === $wpm_default;
		// Coverage: share of default-language content that has this translation.
		$wpm_linked = isset( $wpm_stats['translated'][ $wpm_slug ] ) ? $wpm_sum( $wpm_stats['translated'][ $wpm_slug ] ) : 0;
		$wpm_pct    = $wpm_default_total ? min( 100, (int) round( $wpm_linked / $wpm_default_total * 100 ) ) : 0;
		?>
		<article class="wpm-card wpm-lang-card">
			<header class="wpm-lang-card__head">
				<?php WPM_Admin::language_chip( $wpm_slug ); ?>
				<div>
					<h3 class="wpm-lang-card__name"><?php echo esc_html( $wpm_cfg['label'] ); ?></h3>
					<p class="wpm-lang-card__locale"><?php echo esc_html( $wpm_manager->get_hreflang( $wpm_slug ) ); ?></p>
				</div>
				<?php if ( $wpm_is_default ) : ?>
					<span class="wpm-badge wpm-badge--primary"><?php esc_html_e( 'Default', 'pixellang-multilingual' ); ?></span>
				<?php endif; ?>
			</header>
			<p class="wpm-lang-card__stat">
				<strong><?php echo esc_html( number_format_i18n( $wpm_count ) ); ?></strong>
				<span><?php echo esc_html( _n( 'item', 'items', $wpm_count, 'pixellang-multilingual' ) ); ?></span>
			</p>
			<?php if ( $wpm_is_default ) : ?>
				<p class="wpm-lang-card__meta"><?php esc_html_e( 'Reference language for coverage.', 'pixellang-multilingual' ); ?></p>
			<?php else : ?>
				<div class="wpm-meter" role="meter" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( $wpm_pct ); ?>"
					aria-label="<?php echo esc_attr( sprintf( /* translators: %s: language name. */ __( '%s coverage', 'pixellang-multilingual' ), $wpm_cfg['label'] ) ); ?>">
					<span class="wpm-meter__bar" style="width: <?php echo esc_attr( $wpm_pct ); ?>%"></span>
				</div>
				<p class="wpm-lang-card__meta">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: percentage, 2: default language name. */
							__( '%1$d%% of %2$s content translated', 'pixellang-multilingual' ),
							$wpm_pct,
							$wpm_manager->get_label( $wpm_default )
						)
					);
					?>
				</p>
			<?php endif; ?>
			<a class="wpm-link" href="<?php echo esc_url( WPM_Admin::url( 'wpm-translations', $wpm_is_default ? array() : array( 'missing' => $wpm_slug ) ) ); ?>">
				<?php $wpm_is_default ? esc_html_e( 'View content', 'pixellang-multilingual' ) : esc_html_e( 'View missing translations', 'pixellang-multilingual' ); ?>
				<?php WPM_Icons::render( 'arrow-right', 14 ); ?>
			</a>
		</article>
	<?php endforeach; ?>
</section>

<div class="wpm-columns">
	<section class="wpm-card" aria-labelledby="wpm-health-title">
		<h3 class="wpm-card__title" id="wpm-health-title"><?php esc_html_e( 'Site health', 'pixellang-multilingual' ); ?></h3>
		<ul class="wpm-checklist">
			<?php
			$wpm_checks = array();

			$wpm_checks[] = array(
				$wpm_unassigned ? 'warning' : 'ok',
				$wpm_unassigned
					/* translators: %s: number of items. */
					? sprintf( _n( '%s item has no language', '%s items have no language', $wpm_unassigned, 'pixellang-multilingual' ), number_format_i18n( $wpm_unassigned ) )
					: __( 'All content has a language', 'pixellang-multilingual' ),
				$wpm_unassigned ? array( WPM_Admin::url( 'wpm-tools' ), __( 'Assign the default language', 'pixellang-multilingual' ) ) : null,
			);

			$wpm_menus    = get_option( 'wpm_menus', array() );
			$wpm_menus    = is_array( $wpm_menus ) ? array_filter( $wpm_menus ) : array();
			$wpm_has_navs = (bool) get_posts(
				array(
					'post_type'      => 'wp_navigation',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);
			if ( $wpm_has_navs ) {
				$wpm_missing  = array_diff( array_keys( $wpm_languages ), array_keys( $wpm_menus ) );
				$wpm_checks[] = array(
					$wpm_missing ? 'warning' : 'ok',
					$wpm_missing
						/* translators: %s: comma-separated language codes. */
						? sprintf( __( 'No navigation menu for: %s', 'pixellang-multilingual' ), strtoupper( implode( ', ', $wpm_missing ) ) )
						: __( 'Each language has its navigation menu', 'pixellang-multilingual' ),
					$wpm_missing ? array( WPM_Admin::url( 'wpm-settings', array( 'tab' => 'menus' ) ), __( 'Assign menus', 'pixellang-multilingual' ) ) : null,
				);
			}

			$wpm_checks[] = array(
				WPM_Settings::get( 'hreflang' ) ? 'ok' : 'warning',
				WPM_Settings::get( 'hreflang' ) ? __( 'hreflang tags are output for translated pages', 'pixellang-multilingual' ) : __( 'hreflang tags are turned off', 'pixellang-multilingual' ),
				WPM_Settings::get( 'hreflang' ) ? null : array( WPM_Admin::url( 'wpm-settings', array( 'tab' => 'behavior' ) ), __( 'Turn on', 'pixellang-multilingual' ) ),
			);

			$wpm_templates = WPM_Template_Switcher::get_map();
			if ( wp_is_block_theme() ) {
				$wpm_checks[] = array(
					$wpm_templates ? 'ok' : 'info',
					$wpm_templates
						/* translators: %d: number of template groups. */
						? sprintf( _n( '%d template part has language versions', '%d template parts have language versions', count( $wpm_templates ), 'pixellang-multilingual' ), count( $wpm_templates ) )
						: __( 'No template part has language versions yet', 'pixellang-multilingual' ),
					array( WPM_Admin::url( 'wpm-settings', array( 'tab' => 'templates' ) ), __( 'Manage', 'pixellang-multilingual' ) ),
				);
			}

			$wpm_report = get_option( WPM_Migration::REPORT );
			if ( is_array( $wpm_report ) ) {
				$wpm_checks[] = array(
					'info',
					/* translators: 1: number of groups, 2: number of ignored entries. */
					sprintf( __( 'Version 1.0 links migrated: %1$d groups, %2$d entries ignored', 'pixellang-multilingual' ), (int) $wpm_report['groups'], count( $wpm_report['skipped'] ) ),
					array( WPM_Admin::url( 'wpm-tools' ), __( 'Details', 'pixellang-multilingual' ) ),
				);
			}

			$wpm_icons = array(
				'ok'      => 'check-circle',
				'warning' => 'alert',
				'info'    => 'info',
			);
			foreach ( $wpm_checks as $wpm_check ) :
				?>
				<li class="wpm-checklist__item is-<?php echo esc_attr( $wpm_check[0] ); ?>">
					<?php WPM_Icons::render( $wpm_icons[ $wpm_check[0] ], 18, 'wpm-checklist__icon' ); ?>
					<span class="wpm-checklist__text"><?php echo esc_html( $wpm_check[1] ); ?></span>
					<?php if ( $wpm_check[2] ) : ?>
						<a class="wpm-link" href="<?php echo esc_url( $wpm_check[2][0] ); ?>"><?php echo esc_html( $wpm_check[2][1] ); ?></a>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>

	<section class="wpm-card" aria-labelledby="wpm-types-title">
		<h3 class="wpm-card__title" id="wpm-types-title"><?php esc_html_e( 'Content by type', 'pixellang-multilingual' ); ?></h3>
		<div class="wpm-table-wrap">
			<table class="wpm-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Type', 'pixellang-multilingual' ); ?></th>
						<?php foreach ( array_keys( $wpm_languages ) as $wpm_slug ) : ?>
							<th scope="col" class="is-num"><?php WPM_Admin::language_chip( $wpm_slug ); ?></th>
						<?php endforeach; ?>
						<th scope="col" class="is-num"><?php esc_html_e( 'None', 'pixellang-multilingual' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $wpm_types as $wpm_type ) :
						$wpm_obj = get_post_type_object( $wpm_type );
						if ( ! $wpm_obj ) {
							continue;
						}
						$wpm_none = isset( $wpm_stats['unassigned'][ $wpm_type ] ) ? (int) $wpm_stats['unassigned'][ $wpm_type ] : 0;
						?>
						<tr>
							<th scope="row"><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . $wpm_type ) ); ?>"><?php echo esc_html( $wpm_obj->labels->name ); ?></a></th>
							<?php foreach ( array_keys( $wpm_languages ) as $wpm_slug ) : ?>
								<td class="is-num"><?php echo esc_html( number_format_i18n( isset( $wpm_stats['by_lang'][ $wpm_slug ][ $wpm_type ] ) ? $wpm_stats['by_lang'][ $wpm_slug ][ $wpm_type ] : 0 ) ); ?></td>
							<?php endforeach; ?>
							<td class="is-num">
								<?php if ( $wpm_none ) : ?>
									<a class="wpm-badge wpm-badge--warning" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . $wpm_type . '&wpm_lang=none' ) ); ?>"><?php echo esc_html( number_format_i18n( $wpm_none ) ); ?></a>
								<?php else : ?>
									<span class="wpm-muted">0</span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</section>
</div>
