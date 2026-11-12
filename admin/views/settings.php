<?php
/**
 * Settings: one tab, one form, one purpose.
 *
 * @package PixelLang_Multilingual
 */

defined( 'ABSPATH' ) || exit;

$wpm_tabs = array(
	'content'   => array( __( 'Content', 'pixellang-multilingual' ), 'files' ),
	'behavior'  => array( __( 'Visitors & SEO', 'pixellang-multilingual' ), 'globe' ),
	'menus'     => array( __( 'Menus', 'pixellang-multilingual' ), 'menu' ),
	'forms'     => array( __( 'Forms', 'pixellang-multilingual' ), 'form' ),
	'templates' => array( __( 'Template parts', 'pixellang-multilingual' ), 'layout' ),
);
if ( ! wp_is_block_theme() && ! WPM_Template_Switcher::get_map() ) {
	unset( $wpm_tabs['templates'] );
}
$wpm_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'content'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
if ( ! isset( $wpm_tabs[ $wpm_tab ] ) ) {
	$wpm_tab = 'content';
}
$wpm_manager   = WPM_Language_Manager::instance();
$wpm_languages = $wpm_manager->get_all();

/**
 * Opens a settings form for a section.
 *
 * @param string $section Section key.
 */
$wpm_form_open = function ( $section ) {
	printf( '<form class="wpm-card" method="post" action="%s" data-wpm-dirty-guard>', esc_url( admin_url( 'admin-post.php' ) ) );
	echo '<input type="hidden" name="action" value="wpm_save_settings" />';
	printf( '<input type="hidden" name="section" value="%s" />', esc_attr( $section ) );
	wp_nonce_field( 'wpm_save_settings' );
};
$wpm_form_close = function ( $label = '' ) {
	printf(
		'<div class="wpm-card__foot"><span></span><button type="submit" class="wpm-btn wpm-btn--primary">%s</button></div></form>',
		esc_html( $label ? $label : __( 'Save changes', 'pixellang-multilingual' ) )
	);
};

/**
 * Toggle switch row.
 *
 * @param string $name    Field name.
 * @param bool   $checked State.
 * @param string $label   Label.
 * @param string $help    Description.
 */
$wpm_toggle = function ( $name, $checked, $label, $help ) {
	$id = 'wpm-' . $name;
	?>
	<div class="wpm-setting">
		<div class="wpm-setting__text">
			<label class="wpm-setting__label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
			<p class="wpm-setting__help" id="<?php echo esc_attr( $id ); ?>-help"><?php echo esc_html( $help ); ?></p>
		</div>
		<span class="wpm-switch">
			<input type="checkbox" role="switch" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="1" aria-describedby="<?php echo esc_attr( $id ); ?>-help" <?php checked( $checked ); ?> />
			<span class="wpm-switch__track" aria-hidden="true"></span>
		</span>
	</div>
	<?php
};
?>
<div class="wpm-page-head">
	<div>
		<h2 class="wpm-page-head__title"><?php esc_html_e( 'Settings', 'pixellang-multilingual' ); ?></h2>
		<p class="wpm-page-head__text"><?php esc_html_e( 'Each tab saves on its own.', 'pixellang-multilingual' ); ?></p>
	</div>
</div>

<div class="wpm-settings-layout">
	<nav class="wpm-subnav" aria-label="<?php esc_attr_e( 'Settings sections', 'pixellang-multilingual' ); ?>">
		<?php foreach ( $wpm_tabs as $wpm_key => $wpm_t ) : ?>
			<a class="wpm-subnav__link<?php echo $wpm_key === $wpm_tab ? ' is-current' : ''; ?>" href="<?php echo esc_url( WPM_Admin::url( 'wpm-settings', array( 'tab' => $wpm_key ) ) ); ?>"<?php echo $wpm_key === $wpm_tab ? ' aria-current="page"' : ''; ?>>
				<?php WPM_Icons::render( $wpm_t[1], 16 ); ?><span><?php echo esc_html( $wpm_t[0] ); ?></span>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="wpm-settings-panel">
	<?php if ( 'content' === $wpm_tab ) : ?>
		<?php
		$wpm_form_open( 'content' );
		$wpm_enabled = WPM_Settings::get_post_types();
		?>
		<div class="wpm-card__head">
			<h3 class="wpm-card__title"><?php esc_html_e( 'Translatable content types', 'pixellang-multilingual' ); ?></h3>
		</div>
		<p class="wpm-card__intro"><?php esc_html_e( 'Enabled types get a language, the Translations box in the editor and the language columns. Pages and posts are always enabled.', 'pixellang-multilingual' ); ?></p>
		<fieldset class="wpm-option-grid">
			<legend class="screen-reader-text"><?php esc_html_e( 'Content types', 'pixellang-multilingual' ); ?></legend>
			<?php
			foreach ( WPM_Settings::get_translatable_post_types() as $wpm_slug => $wpm_obj ) :
				$wpm_core = in_array( $wpm_slug, array( 'page', 'post' ), true );
				?>
				<label class="wpm-option<?php echo $wpm_core ? ' is-locked' : ''; ?>">
					<input type="checkbox" name="post_types[]" value="<?php echo esc_attr( $wpm_slug ); ?>" <?php checked( $wpm_core || in_array( $wpm_slug, $wpm_enabled, true ) ); ?> <?php disabled( $wpm_core ); ?> />
					<span class="wpm-option__body">
						<strong><?php echo esc_html( $wpm_obj->labels->name ); ?></strong>
						<code><?php echo esc_html( $wpm_slug ); ?></code>
					</span>
					<?php if ( $wpm_core ) : ?>
						<span class="wpm-badge"><?php esc_html_e( 'Always on', 'pixellang-multilingual' ); ?></span>
					<?php endif; ?>
				</label>
			<?php endforeach; ?>
		</fieldset>

		<div class="wpm-card__head wpm-card__head--section">
			<h3 class="wpm-card__title"><?php esc_html_e( 'Translatable taxonomies', 'pixellang-multilingual' ); ?></h3>
		</div>
		<p class="wpm-card__intro"><?php esc_html_e( 'Terms of enabled taxonomies get a language and can be linked to their translations. When a translation is created, its terms are replaced by their translations.', 'pixellang-multilingual' ); ?></p>
		<fieldset class="wpm-option-grid">
			<legend class="screen-reader-text"><?php esc_html_e( 'Taxonomies', 'pixellang-multilingual' ); ?></legend>
			<?php foreach ( WPM_Settings::get_translatable_taxonomies() as $wpm_slug => $wpm_obj ) : ?>
				<label class="wpm-option">
					<input type="checkbox" name="taxonomies[]" value="<?php echo esc_attr( $wpm_slug ); ?>" <?php checked( WPM_Settings::is_translatable_taxonomy( $wpm_slug ) ); ?> />
					<span class="wpm-option__body">
						<strong><?php echo esc_html( $wpm_obj->labels->name ); ?></strong>
						<code><?php echo esc_html( $wpm_slug ); ?></code>
					</span>
				</label>
			<?php endforeach; ?>
		</fieldset>
		<?php $wpm_form_close(); ?>

	<?php elseif ( 'behavior' === $wpm_tab ) : ?>
		<?php $wpm_form_open( 'behavior' ); ?>
		<div class="wpm-card__head">
			<h3 class="wpm-card__title"><?php esc_html_e( 'Visitor language', 'pixellang-multilingual' ); ?></h3>
		</div>
		<p class="wpm-card__intro"><?php esc_html_e( 'A translated page always shows in its own language. These options decide the language of pages that have none (home, archives, search).', 'pixellang-multilingual' ); ?></p>
		<?php
		$wpm_toggle( 'browser_detection', WPM_Settings::get( 'browser_detection' ), __( 'Detect the browser language', 'pixellang-multilingual' ), __( 'On a first visit, use the first browser language that is active on the site.', 'pixellang-multilingual' ) );
		$wpm_toggle( 'front_redirect', WPM_Settings::get( 'front_redirect' ), __( 'Send visitors to the home page of their language', 'pixellang-multilingual' ), __( 'A visitor who chose English and opens the French home page is redirected to the English home page, if it exists.', 'pixellang-multilingual' ) );
		$wpm_toggle( 'filter_queries', WPM_Settings::get( 'filter_queries' ), __( 'Filter listings by language', 'pixellang-multilingual' ), __( 'Blog, archives and search only list content in the current language (and content without language). Leave off if your theme already filters its listings.', 'pixellang-multilingual' ) );
		?>
		<div class="wpm-setting">
			<div class="wpm-setting__text">
				<label class="wpm-setting__label" for="wpm-cookie-days"><?php esc_html_e( 'Remember the language for', 'pixellang-multilingual' ); ?></label>
				<p class="wpm-setting__help" id="wpm-cookie-days-help"><?php esc_html_e( 'Lifetime of the wpm_lang cookie, in days (1 to 3650).', 'pixellang-multilingual' ); ?></p>
			</div>
			<span class="wpm-input-suffix">
				<input type="number" class="wpm-input wpm-input--short" id="wpm-cookie-days" name="cookie_days" min="1" max="3650" value="<?php echo esc_attr( WPM_Settings::get( 'cookie_days' ) ); ?>" aria-describedby="wpm-cookie-days-help" />
				<span><?php esc_html_e( 'days', 'pixellang-multilingual' ); ?></span>
			</span>
		</div>

		<div class="wpm-card__head wpm-card__head--section">
			<h3 class="wpm-card__title"><?php esc_html_e( 'Search engines', 'pixellang-multilingual' ); ?></h3>
		</div>
		<?php $wpm_toggle( 'hreflang', WPM_Settings::get( 'hreflang' ), __( 'Output hreflang tags', 'pixellang-multilingual' ), __( 'Tells search engines which page is the translation of which, with x-default on the default language. Turn off only if an SEO plugin already outputs them.', 'pixellang-multilingual' ) ); ?>
		<?php $wpm_form_close(); ?>

	<?php elseif ( 'menus' === $wpm_tab ) : ?>
		<?php
		$wpm_form_open( 'menus' );
		$wpm_menus = get_option( 'wpm_menus', array() );
		$wpm_navs  = get_posts(
			array(
				'post_type'      => 'wp_navigation',
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		$wpm_locations = get_registered_nav_menus();
		?>
		<div class="wpm-card__head">
			<h3 class="wpm-card__title"><?php esc_html_e( 'Navigation blocks', 'pixellang-multilingual' ); ?></h3>
		</div>
		<p class="wpm-card__intro"><?php esc_html_e( 'A Navigation block that shows one of these menus displays the menu of the visitor language instead. You can also assign a language from the Site Editor, in the menu settings.', 'pixellang-multilingual' ); ?></p>
		<?php if ( $wpm_navs ) : ?>
			<div class="wpm-form-rows">
				<?php foreach ( $wpm_languages as $wpm_slug => $wpm_cfg ) : ?>
					<label class="wpm-form-row">
						<span class="wpm-form-row__label"><?php WPM_Admin::language_chip( $wpm_slug, true ); ?></span>
						<select class="wpm-select" name="nav_menus[<?php echo esc_attr( $wpm_slug ); ?>]">
							<option value=""><?php esc_html_e( 'No menu', 'pixellang-multilingual' ); ?></option>
							<?php foreach ( $wpm_navs as $wpm_nav ) : ?>
								<option value="<?php echo esc_attr( $wpm_nav->ID ); ?>" <?php selected( isset( $wpm_menus[ $wpm_slug ] ) ? (int) $wpm_menus[ $wpm_slug ] : 0, $wpm_nav->ID ); ?>>
									<?php echo esc_html( ( $wpm_nav->post_title ? $wpm_nav->post_title : __( '(no title)', 'pixellang-multilingual' ) ) . ' · #' . $wpm_nav->ID ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</label>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<p class="wpm-empty-inline"><?php esc_html_e( 'This site has no Navigation menu yet.', 'pixellang-multilingual' ); ?></p>
		<?php endif; ?>

		<div class="wpm-card__head wpm-card__head--section">
			<h3 class="wpm-card__title"><?php esc_html_e( 'Classic menu locations', 'pixellang-multilingual' ); ?></h3>
		</div>
		<?php if ( $wpm_locations ) : ?>
			<p class="wpm-card__intro"><?php esc_html_e( 'For classic themes: the menu shown in each location, per language. Leave empty to keep the menu set in Appearance > Menus.', 'pixellang-multilingual' ); ?></p>
			<?php
			$wpm_classic = wp_get_nav_menus();
			$wpm_map     = WPM_Settings::get( 'menu_locations' );
			?>
			<div class="wpm-table-wrap">
				<table class="wpm-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Location', 'pixellang-multilingual' ); ?></th>
							<?php foreach ( array_keys( $wpm_languages ) as $wpm_slug ) : ?>
								<th scope="col"><?php WPM_Admin::language_chip( $wpm_slug ); ?></th>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $wpm_locations as $wpm_loc => $wpm_loc_label ) : ?>
							<tr>
								<th scope="row"><?php echo esc_html( $wpm_loc_label ); ?></th>
								<?php foreach ( $wpm_languages as $wpm_slug => $wpm_cfg ) : ?>
									<td>
										<select class="wpm-select" name="menu_locations[<?php echo esc_attr( $wpm_slug ); ?>][<?php echo esc_attr( $wpm_loc ); ?>]" aria-label="<?php echo esc_attr( $wpm_loc_label . ' · ' . $wpm_cfg['label'] ); ?>">
											<option value=""><?php esc_html_e( 'Default', 'pixellang-multilingual' ); ?></option>
											<?php foreach ( $wpm_classic as $wpm_menu ) : ?>
												<option value="<?php echo esc_attr( $wpm_menu->term_id ); ?>" <?php selected( isset( $wpm_map[ $wpm_slug ][ $wpm_loc ] ) ? (int) $wpm_map[ $wpm_slug ][ $wpm_loc ] : 0, $wpm_menu->term_id ); ?>><?php echo esc_html( $wpm_menu->name ); ?></option>
											<?php endforeach; ?>
										</select>
									</td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php else : ?>
			<p class="wpm-empty-inline"><?php esc_html_e( 'The active theme registers no classic menu location: menus are handled by Navigation blocks above.', 'pixellang-multilingual' ); ?></p>
		<?php endif; ?>
		<?php $wpm_form_close(); ?>

	<?php elseif ( 'forms' === $wpm_tab ) : ?>
		<?php
		$wpm_providers = array();
		if ( defined( 'WPCF7_VERSION' ) ) {
			$wpm_providers['cf7'] = array(
				'label' => 'Contact Form 7',
				'forms' => wp_list_pluck( get_posts( array( 'post_type' => 'wpcf7_contact_form', 'posts_per_page' => 200, 'orderby' => 'title', 'order' => 'ASC', 'no_found_rows' => true ) ), 'post_title', 'ID' ),
			);
		}
		if ( class_exists( 'GFAPI' ) ) {
			$wpm_gf = array();
			foreach ( (array) GFAPI::get_forms() as $wpm_f ) {
				$wpm_gf[ (int) $wpm_f['id'] ] = $wpm_f['title'];
			}
			$wpm_providers['gf'] = array(
				'label' => 'Gravity Forms',
				'forms' => $wpm_gf,
			);
		}
		$wpm_groups = (array) WPM_Settings::get( 'form_groups' );
		$wpm_form_open( 'forms' );
		?>
		<div class="wpm-card__head">
			<h3 class="wpm-card__title"><?php esc_html_e( 'Form translations', 'pixellang-multilingual' ); ?></h3>
		</div>
		<p class="wpm-card__intro"><?php esc_html_e( 'Link the versions of a form: wherever one of them is inserted, visitors see the version of their language.', 'pixellang-multilingual' ); ?></p>
		<?php if ( ! $wpm_providers ) : ?>
			<?php
			WPM_Admin::empty_state( 'form', __( 'No supported form plugin', 'pixellang-multilingual' ), __( 'Activate Contact Form 7 or Gravity Forms to translate forms.', 'pixellang-multilingual' ) );
			foreach ( $wpm_groups as $wpm_i => $wpm_group ) {
				// Keep groups of an inactive plugin when saving.
				foreach ( $wpm_group['forms'] as $wpm_l => $wpm_id ) {
					printf( '<input type="hidden" name="form_groups[%1$d][forms][%2$s]" value="%3$d" />', (int) $wpm_i, esc_attr( $wpm_l ), (int) $wpm_id );
				}
				printf( '<input type="hidden" name="form_groups[%1$d][provider]" value="%2$s" />', (int) $wpm_i, esc_attr( $wpm_group['provider'] ) );
			}
			?>
		<?php else : ?>
			<div class="wpm-table-wrap">
				<table class="wpm-table wpm-form-groups" id="wpm-form-groups">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Plugin', 'pixellang-multilingual' ); ?></th>
							<?php foreach ( array_keys( $wpm_languages ) as $wpm_slug ) : ?>
								<th scope="col"><?php WPM_Admin::language_chip( $wpm_slug ); ?></th>
							<?php endforeach; ?>
							<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'pixellang-multilingual' ); ?></span></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$wpm_render_group = function ( $index, $group ) use ( $wpm_providers, $wpm_languages ) {
							?>
							<tr data-row>
								<td>
									<select class="wpm-select" name="form_groups[<?php echo esc_attr( $index ); ?>][provider]" data-provider aria-label="<?php esc_attr_e( 'Form plugin', 'pixellang-multilingual' ); ?>">
										<?php foreach ( $wpm_providers as $key => $provider ) : ?>
											<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $group['provider'], $key ); ?>><?php echo esc_html( $provider['label'] ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
								<?php foreach ( $wpm_languages as $slug => $cfg ) : ?>
									<td>
										<?php foreach ( $wpm_providers as $key => $provider ) : ?>
											<select class="wpm-select" name="form_groups[<?php echo esc_attr( $index ); ?>][forms][<?php echo esc_attr( $slug ); ?>]" data-for-provider="<?php echo esc_attr( $key ); ?>"
												aria-label="<?php echo esc_attr( $provider['label'] . ' · ' . $cfg['label'] ); ?>"<?php echo $group['provider'] === $key ? '' : ' hidden disabled'; ?>>
												<option value=""><?php esc_html_e( 'None', 'pixellang-multilingual' ); ?></option>
												<?php foreach ( $provider['forms'] as $id => $title ) : ?>
													<option value="<?php echo esc_attr( $id ); ?>" <?php selected( isset( $group['forms'][ $slug ] ) ? (int) $group['forms'][ $slug ] : 0, (int) $id ); ?>><?php echo esc_html( $title . ' · #' . $id ); ?></option>
												<?php endforeach; ?>
											</select>
										<?php endforeach; ?>
									</td>
								<?php endforeach; ?>
								<td class="is-actions">
									<button type="button" class="wpm-icon-btn wpm-icon-btn--danger" data-remove-row aria-label="<?php esc_attr_e( 'Remove this form group', 'pixellang-multilingual' ); ?>"><?php WPM_Icons::render( 'trash', 16 ); ?></button>
								</td>
							</tr>
							<?php
						};
						foreach ( $wpm_groups as $wpm_i => $wpm_group ) {
							if ( isset( $wpm_providers[ $wpm_group['provider'] ] ) ) {
								$wpm_render_group( $wpm_i, $wpm_group );
							}
						}
						?>
					</tbody>
				</table>
			</div>
			<template id="wpm-form-group-template">
				<?php
				$wpm_render_group(
					'__index__',
					array(
						'provider' => array_key_first( $wpm_providers ),
						'forms'    => array(),
					)
				);
				?>
			</template>
			<p class="wpm-empty-inline" id="wpm-form-groups-empty"<?php echo $wpm_groups ? ' hidden' : ''; ?>><?php esc_html_e( 'No form is linked yet.', 'pixellang-multilingual' ); ?></p>
			<p><button type="button" class="wpm-btn" id="wpm-add-form-group"><?php WPM_Icons::render( 'plus', 14 ); ?><?php esc_html_e( 'Link a form', 'pixellang-multilingual' ); ?></button></p>
		<?php endif; ?>
		<?php $wpm_form_close(); ?>

	<?php elseif ( 'templates' === $wpm_tab ) : ?>
		<?php $wpm_map = WPM_Template_Switcher::get_map(); ?>
		<section class="wpm-card">
			<div class="wpm-card__head">
				<h3 class="wpm-card__title"><?php esc_html_e( 'Template parts per language', 'pixellang-multilingual' ); ?></h3>
				<a class="wpm-btn wpm-btn--small" href="<?php echo esc_url( admin_url( 'site-editor.php?p=%2Fpattern&postType=wp_template_part' ) ); ?>"><?php WPM_Icons::render( 'external', 14 ); ?><?php esc_html_e( 'Open the Site Editor', 'pixellang-multilingual' ); ?></a>
			</div>
			<p class="wpm-card__intro"><?php esc_html_e( 'Header, footer and other parts can have one version per language. Open a part in the Site Editor: the Languages panel of its settings assigns its language and creates the other versions.', 'pixellang-multilingual' ); ?></p>
			<?php if ( ! $wpm_map ) : ?>
				<?php WPM_Admin::empty_state( 'layout', __( 'No template part has language versions yet', 'pixellang-multilingual' ), __( 'Without versions, every language shares the same header and footer.', 'pixellang-multilingual' ) ); ?>
			<?php else : ?>
				<div class="wpm-table-wrap">
					<table class="wpm-table">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Part', 'pixellang-multilingual' ); ?></th>
								<?php foreach ( array_keys( $wpm_languages ) as $wpm_slug ) : ?>
									<th scope="col"><?php WPM_Admin::language_chip( $wpm_slug ); ?></th>
								<?php endforeach; ?>
								<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'pixellang-multilingual' ); ?></span></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $wpm_map as $wpm_group => $wpm_langs ) : ?>
								<tr data-row>
									<th scope="row"><code><?php echo esc_html( $wpm_group ); ?></code></th>
									<?php foreach ( array_keys( $wpm_languages ) as $wpm_slug ) : ?>
										<td>
											<?php if ( ! empty( $wpm_langs[ $wpm_slug ]['slug'] ) ) : ?>
												<a class="wpm-cell-link" href="<?php echo esc_url( WPM_Template_Switcher::get_edit_url( $wpm_langs[ $wpm_slug ]['slug'] ) ); ?>">
													<code><?php echo esc_html( $wpm_langs[ $wpm_slug ]['slug'] ); ?></code><?php WPM_Icons::render( 'pencil', 14 ); ?>
												</a>
											<?php else : ?>
												<span class="wpm-badge wpm-badge--warning"><?php esc_html_e( 'Missing', 'pixellang-multilingual' ); ?></span>
											<?php endif; ?>
										</td>
									<?php endforeach; ?>
									<td class="is-actions">
										<button type="button" class="wpm-icon-btn wpm-icon-btn--danger" data-remove-template-group="<?php echo esc_attr( $wpm_group ); ?>"
											aria-label="<?php echo esc_attr( sprintf( /* translators: %s: template group. */ __( 'Stop translating %s', 'pixellang-multilingual' ), $wpm_group ) ); ?>"><?php WPM_Icons::render( 'unlink', 16 ); ?></button>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<p class="wpm-help"><?php WPM_Icons::render( 'info', 16 ); ?><?php esc_html_e( 'Unlinking a part only removes its language versions from this list: the template parts themselves are kept.', 'pixellang-multilingual' ); ?></p>
			<?php endif; ?>
		</section>
	<?php endif; ?>
	</div>
</div>
