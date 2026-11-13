<?php
/**
 * String Translation: texts of themes, plugins and templates.
 *
 * @package PixelLang_Multilingual
 */

defined( 'ABSPATH' ) || exit;

if ( ! WPM_Strings::tables_exist() ) {
	WPM_Admin::notice( 'error', __( 'The String Translation tables are missing. Deactivate and reactivate the plugin to create them.', 'pixellang-multilingual' ) );
	return;
}

$wpm_manager    = WPM_Language_Manager::instance();
$wpm_table      = new WPM_Strings_Table();
$wpm_table->prepare_items();
$wpm_stats      = WPM_Strings::stats();
$wpm_sources    = WPM_Strings::sources();
$wpm_domains    = WPM_Strings::domain_counts();
$wpm_components = WPM_String_Scanner::components();
$wpm_until      = (int) get_option( WPM_Strings::DISCOVERY, 0 );
$wpm_discover   = $wpm_until > time();
$wpm_source     = WPM_Settings::get( 'strings_source_lang' );
$wpm_type_names = array(
	'theme'   => __( 'Theme', 'pixellang-multilingual' ),
	'plugin'  => __( 'Plugin', 'pixellang-multilingual' ),
	'core'    => 'WordPress',
	'manual'  => __( 'Manual', 'pixellang-multilingual' ),
	'literal' => __( 'HTML text', 'pixellang-multilingual' ),
);

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display only.
if ( isset( $_GET['wpm_notice'] ) && 'scanned' === $_GET['wpm_notice'] ) {
	WPM_Admin::notice(
		'info',
		sprintf(
			/* translators: 1: files read, 2: strings found, 3: new strings. */
			__( '%1$d files read, %2$d strings found, %3$d new.', 'pixellang-multilingual' ),
			isset( $_GET['files'] ) ? absint( $_GET['files'] ) : 0,
			isset( $_GET['found'] ) ? absint( $_GET['found'] ) : 0,
			isset( $_GET['new'] ) ? absint( $_GET['new'] ) : 0
		)
	);
}
// phpcs:enable

$wpm_hidden_filters = function () use ( $wpm_table ) {
	foreach ( array( 's' => 'search', 'source' => 'source', 'domain' => 'domain', 'missing' => 'missing' ) as $wpm_k => $wpm_f ) {
		if ( '' !== $wpm_table->filters[ $wpm_f ] ) {
			printf( '<input type="hidden" name="keep_%1$s" value="%2$s" />', esc_attr( $wpm_k ), esc_attr( $wpm_table->filters[ $wpm_f ] ) );
		}
	}
};
?>
<div class="wpm-page-head">
	<div>
		<h2 class="wpm-page-head__title"><?php esc_html_e( 'String Translation', 'pixellang-multilingual' ); ?></h2>
		<p class="wpm-page-head__text"><?php esc_html_e( 'Texts of your theme, plugins and templates, translated without editing their files. A translation entered here is shown automatically in the matching language.', 'pixellang-multilingual' ); ?></p>
	</div>
	<div class="wpm-kpis">
		<span class="wpm-kpi"><strong><?php echo esc_html( number_format_i18n( $wpm_stats['total'] ) ); ?></strong> <?php esc_html_e( 'strings', 'pixellang-multilingual' ); ?></span>
		<?php foreach ( $wpm_stats['missing'] as $wpm_lang => $wpm_missing ) : ?>
			<a class="wpm-kpi<?php echo $wpm_missing ? ' is-warning' : ' is-ok'; ?>" href="<?php echo esc_url( WPM_Admin::url( 'wpm-strings', array( 'missing' => $wpm_lang ) ) ); ?>">
				<?php WPM_Admin::language_chip( $wpm_lang ); ?>
				<strong><?php echo esc_html( number_format_i18n( $wpm_missing ) ); ?></strong> <?php esc_html_e( 'to translate', 'pixellang-multilingual' ); ?>
			</a>
		<?php endforeach; ?>
	</div>
</div>

<div class="wpm-grid wpm-grid--tools">
	<form class="wpm-card wpm-tool" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="wpm_strings" />
		<input type="hidden" name="task" value="scan" />
		<?php wp_nonce_field( 'wpm_strings' ); ?>
		<h3 class="wpm-card__title"><?php WPM_Icons::render( 'search', 18 ); ?><?php esc_html_e( 'Scan a theme or plugin', 'pixellang-multilingual' ); ?></h3>
		<p class="wpm-card__intro"><?php esc_html_e( 'Reads its PHP and JavaScript files and registers the texts passed to __(), _e(), esc_html__(), _x(), _n() and similar functions. Files are never modified.', 'pixellang-multilingual' ); ?></p>
		<label class="wpm-field">
			<span class="wpm-field__label"><?php esc_html_e( 'Theme or plugin', 'pixellang-multilingual' ); ?></span>
			<select class="wpm-select" name="component">
				<?php foreach ( $wpm_components as $wpm_key => $wpm_component ) : ?>
					<option value="<?php echo esc_attr( $wpm_key ); ?>"><?php echo esc_html( $wpm_component['label'] ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<button type="submit" class="wpm-btn wpm-btn--primary"><?php esc_html_e( 'Scan', 'pixellang-multilingual' ); ?></button>
	</form>

	<form class="wpm-card wpm-tool" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="wpm_strings" />
		<input type="hidden" name="task" value="discovery" />
		<input type="hidden" name="enable" value="<?php echo $wpm_discover ? '0' : '1'; ?>" />
		<?php wp_nonce_field( 'wpm_strings' ); ?>
		<h3 class="wpm-card__title"><?php WPM_Icons::render( 'eye', 18 ); ?><?php esc_html_e( 'Discovery mode', 'pixellang-multilingual' ); ?></h3>
		<p class="wpm-card__intro"><?php esc_html_e( 'While it is on, every translatable text actually displayed on the pages you visit (logged in as an administrator) is registered, with the file that printed it. Visitors are not affected.', 'pixellang-multilingual' ); ?></p>
		<?php if ( $wpm_discover ) : ?>
			<p class="wpm-status-line is-on">
				<span class="wpm-dot" aria-hidden="true"></span>
				<?php
				/* translators: %s: time. */
				echo esc_html( sprintf( __( 'On until %s', 'pixellang-multilingual' ), wp_date( get_option( 'time_format' ), $wpm_until ) ) );
				?>
				<a class="wpm-link" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Browse the site', 'pixellang-multilingual' ); ?><?php WPM_Icons::render( 'external', 14 ); ?></a>
			</p>
			<button type="submit" class="wpm-btn"><?php esc_html_e( 'Turn off', 'pixellang-multilingual' ); ?></button>
		<?php else : ?>
			<p class="wpm-status-line"><span class="wpm-dot" aria-hidden="true"></span><?php esc_html_e( 'Off', 'pixellang-multilingual' ); ?></p>
			<button type="submit" class="wpm-btn wpm-btn--primary"><?php esc_html_e( 'Turn on for 30 minutes', 'pixellang-multilingual' ); ?></button>
		<?php endif; ?>
	</form>

	<form class="wpm-card wpm-tool" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="wpm-register-string">
		<input type="hidden" name="action" value="wpm_strings" />
		<input type="hidden" name="task" value="register" />
		<?php wp_nonce_field( 'wpm_strings' ); ?>
		<h3 class="wpm-card__title"><?php WPM_Icons::render( 'plus', 18 ); ?><?php esc_html_e( 'Register a string', 'pixellang-multilingual' ); ?></h3>
		<fieldset class="wpm-choice">
			<legend class="wpm-field__label"><?php esc_html_e( 'Where does the text come from?', 'pixellang-multilingual' ); ?></legend>
			<label><input type="radio" name="kind" value="gettext" checked /> <?php esc_html_e( 'A translation function: __( "Text", "domain" )', 'pixellang-multilingual' ); ?></label>
			<label><input type="radio" name="kind" value="literal" /> <?php esc_html_e( 'Text written directly in HTML', 'pixellang-multilingual' ); ?></label>
		</fieldset>
		<label class="wpm-field">
			<span class="wpm-field__label"><?php esc_html_e( 'Original text (exact)', 'pixellang-multilingual' ); ?></span>
			<textarea class="wpm-input wpm-textarea" name="original" rows="2" required></textarea>
		</label>
		<div class="wpm-field-row" data-kind="gettext">
			<label class="wpm-field">
				<span class="wpm-field__label"><?php esc_html_e( 'Text domain', 'pixellang-multilingual' ); ?></span>
				<input class="wpm-input wpm-input--mono" name="domain" list="wpm-domain-list" spellcheck="false" />
			</label>
			<label class="wpm-field">
				<span class="wpm-field__label"><?php esc_html_e( 'Context (optional)', 'pixellang-multilingual' ); ?></span>
				<input class="wpm-input" name="context" />
			</label>
		</div>
		<datalist id="wpm-domain-list">
			<?php foreach ( array_keys( $wpm_domains ) as $wpm_d ) : ?>
				<option value="<?php echo esc_attr( $wpm_d ); ?>"></option>
			<?php endforeach; ?>
		</datalist>
		<label class="wpm-field">
			<span class="wpm-field__label"><?php esc_html_e( 'Language of the original', 'pixellang-multilingual' ); ?></span>
			<select class="wpm-select" name="original_lang">
				<?php foreach ( $wpm_manager->get_all() as $wpm_slug => $wpm_cfg ) : ?>
					<option value="<?php echo esc_attr( $wpm_slug ); ?>" <?php selected( $wpm_source, $wpm_slug ); ?>><?php echo esc_html( $wpm_cfg['label'] ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<p class="wpm-help" data-kind="literal" hidden><?php WPM_Icons::render( 'info', 16 ); ?><?php esc_html_e( 'HTML text is replaced in the visible text of the page only (never in links, attributes or scripts). It must match the page exactly. Texts created by JavaScript cannot be translated this way.', 'pixellang-multilingual' ); ?></p>
		<button type="submit" class="wpm-btn wpm-btn--primary"><?php esc_html_e( 'Register', 'pixellang-multilingual' ); ?></button>
	</form>
</div>

<form method="get" class="wpm-card wpm-card--flush wpm-table-card wpm-strings-card">
	<input type="hidden" name="page" value="wpm-strings" />
	<div class="wpm-filters">
		<label class="wpm-field wpm-field--inline">
			<span class="wpm-field__label"><?php esc_html_e( 'Origin', 'pixellang-multilingual' ); ?></span>
			<select name="source" class="wpm-select" data-wpm-autosubmit>
				<option value=""><?php esc_html_e( 'All', 'pixellang-multilingual' ); ?></option>
				<?php foreach ( $wpm_sources as $wpm_key => $wpm_src ) : ?>
					<option value="<?php echo esc_attr( $wpm_key ); ?>" <?php selected( $wpm_table->filters['source'], $wpm_key ); ?>>
						<?php echo esc_html( ( $wpm_type_names[ $wpm_src['type'] ] ?? $wpm_src['type'] ) . ( $wpm_src['name'] ? ' · ' . $wpm_src['name'] : '' ) . ' (' . $wpm_src['total'] . ')' ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</label>
		<label class="wpm-field wpm-field--inline">
			<span class="wpm-field__label"><?php esc_html_e( 'Text domain', 'pixellang-multilingual' ); ?></span>
			<select name="domain" class="wpm-select" data-wpm-autosubmit>
				<option value=""><?php esc_html_e( 'All', 'pixellang-multilingual' ); ?></option>
				<?php foreach ( $wpm_domains as $wpm_d => $wpm_total ) : ?>
					<option value="<?php echo esc_attr( $wpm_d ); ?>" <?php selected( $wpm_table->filters['domain'], $wpm_d ); ?>><?php echo esc_html( ( WPM_Strings::LITERAL_DOMAIN === $wpm_d ? __( 'HTML text', 'pixellang-multilingual' ) : $wpm_d ) . ' (' . $wpm_total . ')' ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label class="wpm-field wpm-field--inline">
			<span class="wpm-field__label"><?php esc_html_e( 'Show', 'pixellang-multilingual' ); ?></span>
			<select name="missing" class="wpm-select" data-wpm-autosubmit>
				<option value=""><?php esc_html_e( 'All strings', 'pixellang-multilingual' ); ?></option>
				<?php foreach ( $wpm_manager->get_all() as $wpm_slug => $wpm_cfg ) : ?>
					<option value="<?php echo esc_attr( $wpm_slug ); ?>" <?php selected( $wpm_table->filters['missing'], $wpm_slug ); ?>>
						<?php
						/* translators: %s: language name. */
						echo esc_html( sprintf( __( 'Not translated in %s', 'pixellang-multilingual' ), $wpm_cfg['label'] ) );
						?>
					</option>
				<?php endforeach; ?>
			</select>
		</label>
		<noscript><button type="submit" class="wpm-btn"><?php esc_html_e( 'Filter', 'pixellang-multilingual' ); ?></button></noscript>
		<?php $wpm_table->search_box( __( 'Search', 'pixellang-multilingual' ), 'wpm-strings' ); ?>
	</div>
	<?php $wpm_table->display(); ?>
</form>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="wpm-strings-bulk"
	data-wpm-confirm="<?php esc_attr_e( 'Delete the selected strings and their translations? The theme and plugin files are not affected.', 'pixellang-multilingual' ); ?>">
	<input type="hidden" name="action" value="wpm_strings" />
	<input type="hidden" name="task" value="delete" />
	<?php wp_nonce_field( 'wpm_strings' ); ?>
	<?php $wpm_hidden_filters(); ?>
</form>

<form class="wpm-inline-setting" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="wpm_strings" />
	<input type="hidden" name="task" value="source_lang" />
	<?php wp_nonce_field( 'wpm_strings' ); ?>
	<label for="wpm-source-lang"><?php esc_html_e( 'Language of texts found in code', 'pixellang-multilingual' ); ?></label>
	<select class="wpm-select" id="wpm-source-lang" name="strings_source_lang">
		<?php foreach ( $wpm_manager->get_all() as $wpm_slug => $wpm_cfg ) : ?>
			<option value="<?php echo esc_attr( $wpm_slug ); ?>" <?php selected( $wpm_source, $wpm_slug ); ?>><?php echo esc_html( $wpm_cfg['label'] ); ?></option>
		<?php endforeach; ?>
	</select>
	<button type="submit" class="wpm-btn wpm-btn--small"><?php esc_html_e( 'Save', 'pixellang-multilingual' ); ?></button>
</form>
