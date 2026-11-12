<?php
/**
 * Languages: active languages (order, name, locale, default) and catalog.
 *
 * @package PixelLang_Multilingual
 */

defined( 'ABSPATH' ) || exit;

$wpm_manager = WPM_Language_Manager::instance();
$wpm_active  = $wpm_manager->get_all();
$wpm_catalog = wpm_get_available_languages();
$wpm_default = $wpm_manager->get_default();
$wpm_stats   = WPM_Translations::get_stats();

/**
 * One editable row of the active list.
 *
 * @param string $slug    Code.
 * @param array  $cfg     Language settings.
 * @param bool   $default Default language.
 * @param int    $count   Items in this language.
 */
$wpm_row = function ( $slug, $cfg, $default, $count ) {
	$base = 'languages[' . $slug . ']';
	?>
	<li class="wpm-lang-row" data-slug="<?php echo esc_attr( $slug ); ?>" data-count="<?php echo esc_attr( $count ); ?>">
		<span class="wpm-lang-row__grip" aria-hidden="true"><?php WPM_Icons::render( 'grip', 16 ); ?></span>
		<span class="wpm-chip"><span class="wpm-chip__code"><?php echo esc_html( strtoupper( $slug ) ); ?></span></span>
		<div class="wpm-lang-row__fields">
			<label class="wpm-field wpm-field--inline">
				<span class="wpm-field__label"><?php esc_html_e( 'Name', 'pixellang-multilingual' ); ?></span>
				<input type="text" class="wpm-input" name="<?php echo esc_attr( $base ); ?>[label]" value="<?php echo esc_attr( $cfg['label'] ); ?>" required />
			</label>
			<label class="wpm-field wpm-field--inline wpm-field--locale">
				<span class="wpm-field__label"><?php esc_html_e( 'Locale', 'pixellang-multilingual' ); ?></span>
				<input type="text" class="wpm-input wpm-input--mono" name="<?php echo esc_attr( $base ); ?>[locale]" value="<?php echo esc_attr( $cfg['locale'] ); ?>" pattern="[a-z]{2,3}(_[A-Z]{2})?(_[a-z0-9]+)?" spellcheck="false" />
			</label>
		</div>
		<label class="wpm-radio-pill">
			<input type="radio" name="default_language" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $default ); ?> />
			<span><?php esc_html_e( 'Default', 'pixellang-multilingual' ); ?></span>
		</label>
		<div class="wpm-lang-row__actions">
			<button type="button" class="wpm-icon-btn" data-move="up" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: language name. */ __( 'Move %s up', 'pixellang-multilingual' ), $cfg['label'] ) ); ?>"><?php WPM_Icons::render( 'up', 16 ); ?></button>
			<button type="button" class="wpm-icon-btn" data-move="down" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: language name. */ __( 'Move %s down', 'pixellang-multilingual' ), $cfg['label'] ) ); ?>"><?php WPM_Icons::render( 'down', 16 ); ?></button>
			<button type="button" class="wpm-icon-btn wpm-icon-btn--danger" data-remove aria-label="<?php echo esc_attr( sprintf( /* translators: %s: language name. */ __( 'Remove %s', 'pixellang-multilingual' ), $cfg['label'] ) ); ?>"><?php WPM_Icons::render( 'trash', 16 ); ?></button>
		</div>
	</li>
	<?php
};
?>
<div class="wpm-page-head">
	<div>
		<h2 class="wpm-page-head__title"><?php esc_html_e( 'Languages', 'pixellang-multilingual' ); ?></h2>
		<p class="wpm-page-head__text"><?php esc_html_e( 'The order below is the order of the language switcher. The default language is used for visitors without a preference and for the x-default hreflang.', 'pixellang-multilingual' ); ?></p>
	</div>
</div>

<div class="wpm-columns wpm-columns--wide-left">
	<form class="wpm-card" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="wpm-languages-form" data-wpm-dirty-guard>
		<input type="hidden" name="action" value="wpm_save_languages" />
		<?php wp_nonce_field( 'wpm_save_languages' ); ?>
		<div class="wpm-card__head">
			<h3 class="wpm-card__title"><?php esc_html_e( 'Active languages', 'pixellang-multilingual' ); ?></h3>
			<span class="wpm-badge" data-wpm-count><?php echo esc_html( count( $wpm_active ) ); ?></span>
		</div>

		<ol class="wpm-lang-list" id="wpm-active-list">
			<?php
			foreach ( $wpm_active as $wpm_slug => $wpm_cfg ) {
				$wpm_count = isset( $wpm_stats['by_lang'][ $wpm_slug ] ) ? array_sum( $wpm_stats['by_lang'][ $wpm_slug ] ) : 0;
				$wpm_row( $wpm_slug, $wpm_cfg, $wpm_slug === $wpm_default, $wpm_count );
			}
			?>
		</ol>
		<p class="wpm-empty-inline" id="wpm-active-empty"<?php echo $wpm_active ? ' hidden' : ''; ?>><?php esc_html_e( 'No language yet. Add one from the catalog.', 'pixellang-multilingual' ); ?></p>

		<template id="wpm-lang-row-template">
			<?php
			$wpm_row(
				'__slug__',
				array(
					'label'  => '__label__',
					'locale' => '__locale__',
				),
				false,
				0
			);
			?>
		</template>

		<div class="wpm-card__foot">
			<p class="wpm-help"><?php WPM_Icons::render( 'info', 16 ); ?><?php esc_html_e( 'Removing a language keeps its content and links: they come back if you add the language again.', 'pixellang-multilingual' ); ?></p>
			<button type="submit" class="wpm-btn wpm-btn--primary"><?php esc_html_e( 'Save languages', 'pixellang-multilingual' ); ?></button>
		</div>
	</form>

	<section class="wpm-card" aria-labelledby="wpm-catalog-title">
		<div class="wpm-card__head">
			<h3 class="wpm-card__title" id="wpm-catalog-title"><?php esc_html_e( 'Add a language', 'pixellang-multilingual' ); ?></h3>
		</div>
		<div class="wpm-search">
			<?php WPM_Icons::render( 'search', 16, 'wpm-search__icon' ); ?>
			<label for="wpm-lang-search" class="screen-reader-text"><?php esc_html_e( 'Search languages', 'pixellang-multilingual' ); ?></label>
			<input type="search" id="wpm-lang-search" class="wpm-input" placeholder="<?php esc_attr_e( 'Search by name or code…', 'pixellang-multilingual' ); ?>" autocomplete="off" />
		</div>
		<ul class="wpm-catalog" id="wpm-catalog">
			<?php foreach ( $wpm_catalog as $wpm_slug => $wpm_info ) : ?>
				<li class="wpm-catalog__item" data-slug="<?php echo esc_attr( $wpm_slug ); ?>" data-label="<?php echo esc_attr( $wpm_info['label'] ); ?>" data-locale="<?php echo esc_attr( $wpm_info['locale'] ); ?>"
					data-search="<?php echo esc_attr( strtolower( $wpm_info['english'] . ' ' . $wpm_info['label'] . ' ' . $wpm_slug . ' ' . $wpm_info['locale'] ) ); ?>"<?php echo isset( $wpm_active[ $wpm_slug ] ) ? ' data-active' : ''; ?>>
					<span class="wpm-chip"><span class="wpm-chip__code"><?php echo esc_html( strtoupper( $wpm_slug ) ); ?></span></span>
					<span class="wpm-catalog__name">
						<strong><?php echo esc_html( $wpm_info['label'] ); ?></strong>
						<small><?php echo esc_html( $wpm_info['english'] . ' · ' . $wpm_info['locale'] ); ?></small>
					</span>
					<button type="button" class="wpm-btn wpm-btn--small" data-add><?php WPM_Icons::render( 'plus', 14 ); ?><?php esc_html_e( 'Add', 'pixellang-multilingual' ); ?></button>
					<span class="wpm-badge wpm-badge--success" data-added><?php esc_html_e( 'Active', 'pixellang-multilingual' ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
		<p class="wpm-empty-inline" id="wpm-catalog-empty" hidden><?php esc_html_e( 'No language matches. Add it as a custom language below.', 'pixellang-multilingual' ); ?></p>

		<details class="wpm-disclosure">
			<summary><?php esc_html_e( 'Custom language', 'pixellang-multilingual' ); ?></summary>
			<div class="wpm-custom-lang" id="wpm-custom-lang">
				<label class="wpm-field">
					<span class="wpm-field__label"><?php esc_html_e( 'Code', 'pixellang-multilingual' ); ?></span>
					<input type="text" class="wpm-input wpm-input--mono" data-custom="slug" placeholder="fr-ca" pattern="[a-z]{2,3}(-[a-z0-9]{2,8})?" spellcheck="false" />
					<span class="wpm-field__hint"><?php esc_html_e( 'Lowercase, as used in URLs: fr, en, pt-br.', 'pixellang-multilingual' ); ?></span>
				</label>
				<label class="wpm-field">
					<span class="wpm-field__label"><?php esc_html_e( 'Name', 'pixellang-multilingual' ); ?></span>
					<input type="text" class="wpm-input" data-custom="label" placeholder="Français (Canada)" />
				</label>
				<label class="wpm-field">
					<span class="wpm-field__label"><?php esc_html_e( 'WordPress locale', 'pixellang-multilingual' ); ?></span>
					<input type="text" class="wpm-input wpm-input--mono" data-custom="locale" placeholder="fr_CA" spellcheck="false" />
				</label>
				<p class="wpm-field__error" data-custom-error hidden></p>
				<button type="button" class="wpm-btn" data-add-custom><?php WPM_Icons::render( 'plus', 14 ); ?><?php esc_html_e( 'Add custom language', 'pixellang-multilingual' ); ?></button>
			</div>
		</details>
	</section>
</div>
