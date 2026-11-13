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
	'switcher'  => array( __( 'Language switcher', 'pixellang-multilingual' ), 'link' ),
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
		$wpm_mode  = WPM_Settings::browser_mode();
		$wpm_modes = array(
			'banner'   => array( __( 'Suggest the translation', 'pixellang-multilingual' ), __( 'Visitors stay on the page they opened. A small banner, written in their language, offers the translation. Search engines and page caches see the same page for everyone.', 'pixellang-multilingual' ), true ),
			'redirect' => array( __( 'Choose the language automatically', 'pixellang-multilingual' ), __( 'On a first visit, pages without a language (home, archives) show in the first browser language active on the site.', 'pixellang-multilingual' ), false ),
			'off'      => array( __( 'Ignore the browser language', 'pixellang-multilingual' ), __( 'The default language is used until the visitor picks one with the language switcher.', 'pixellang-multilingual' ), false ),
		);
		?>
		<fieldset class="wpm-setting wpm-setting--stack">
			<legend class="wpm-setting__label"><?php esc_html_e( 'Browser language', 'pixellang-multilingual' ); ?></legend>
			<p class="wpm-setting__help"><?php esc_html_e( 'What happens when the browser of a visitor prefers another language than the page.', 'pixellang-multilingual' ); ?></p>
			<div class="wpm-option-grid wpm-option-grid--modes">
				<?php foreach ( $wpm_modes as $wpm_key => $wpm_m ) : ?>
					<label class="wpm-option wpm-option--radio">
						<input type="radio" name="browser_mode" value="<?php echo esc_attr( $wpm_key ); ?>" <?php checked( $wpm_mode, $wpm_key ); ?> />
						<span class="wpm-option__body">
							<span class="wpm-option__title">
								<strong><?php echo esc_html( $wpm_m[0] ); ?></strong>
								<?php if ( $wpm_m[2] ) : ?>
									<span class="wpm-badge wpm-badge--success"><?php esc_html_e( 'Recommended', 'pixellang-multilingual' ); ?></span>
								<?php endif; ?>
							</span>
							<span class="wpm-option__help"><?php echo esc_html( $wpm_m[1] ); ?></span>
						</span>
					</label>
				<?php endforeach; ?>
			</div>
		</fieldset>
		<?php
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
		<?php $wpm_toggle( 'hreflang', WPM_Settings::get( 'hreflang' ), __( 'Output hreflang tags', 'pixellang-multilingual' ), __( 'Tells search engines which page is the translation of which, in the page head and in the XML sitemaps (WordPress, Yoast SEO, Rank Math), with x-default on the default language. Turn off only if another plugin already outputs them.', 'pixellang-multilingual' ) ); ?>
		<p class="wpm-help"><?php WPM_Icons::render( 'info', 16 ); ?><span>
			<?php
			printf(
				/* translators: %s: sitemap URL. */
				esc_html__( 'Sitemap: %s', 'pixellang-multilingual' ),
				'<a href="' . esc_url( defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' ) ? home_url( '/sitemap_index.xml' ) : home_url( '/wp-sitemap.xml' ) ) . '" target="_blank" rel="noopener">' . esc_html( defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' ) ? home_url( '/sitemap_index.xml' ) : home_url( '/wp-sitemap.xml' ) ) . '</a>'
			);
			?>
		</span></p>
		<?php $wpm_form_close(); ?>

	<?php elseif ( 'switcher' === $wpm_tab ) : ?>
		<?php
		$wpm_form_open( 'switcher' );
		$wpm_place     = (string) WPM_Settings::get( 'switcher_placement' );
		$wpm_chosen    = array_map( 'intval', (array) WPM_Settings::get( 'switcher_menus' ) );
		$wpm_locs_on   = (array) WPM_Settings::get( 'switcher_locations' );
		$wpm_versions  = get_option( 'wpm_menus', array() );
		$wpm_versions  = is_array( $wpm_versions ) ? array_map( 'intval', $wpm_versions ) : array();
		$wpm_nav_posts = get_posts(
			array(
				'post_type'      => 'wp_navigation',
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		$wpm_places = array(
			'menu'     => array( __( 'In a menu', 'pixellang-multilingual' ), __( 'The languages become the last items of the menus you choose and take their look, on desktop and in the mobile menu. Works with any theme.', 'pixellang-multilingual' ), true ),
			'floating' => array( __( 'Floating', 'pixellang-multilingual' ), __( 'A compact dropdown fixed in a bottom corner of every page. Useful when the menu has no room left.', 'pixellang-multilingual' ), false ),
			'none'     => array( __( 'Block only', 'pixellang-multilingual' ), __( 'Nothing is added automatically. Insert the Language Switcher block where you want it (header, footer) and choose its design.', 'pixellang-multilingual' ), false ),
		);
		?>
		<div class="wpm-card__head">
			<h3 class="wpm-card__title"><?php esc_html_e( 'Where the language switcher appears', 'pixellang-multilingual' ); ?></h3>
		</div>
		<p class="wpm-card__intro"><?php esc_html_e( 'Like WPML, the plugin can add the switcher for you. The Language Switcher block stays available in the editor whatever you choose here.', 'pixellang-multilingual' ); ?></p>
		<fieldset class="wpm-option-grid wpm-option-grid--modes">
			<legend class="screen-reader-text"><?php esc_html_e( 'Placement', 'pixellang-multilingual' ); ?></legend>
			<?php foreach ( $wpm_places as $wpm_key => $wpm_p ) : ?>
				<label class="wpm-option wpm-option--radio wpm-option--place">
					<input type="radio" name="switcher_placement" value="<?php echo esc_attr( $wpm_key ); ?>" <?php checked( $wpm_place, $wpm_key ); ?> />
					<span class="wpm-option__body">
						<span class="wpm-place-mock wpm-place-mock--<?php echo esc_attr( $wpm_key ); ?>" aria-hidden="true">
							<span class="wpm-place-mock__bar"><i></i><b></b><b></b><b></b><?php echo 'menu' === $wpm_key ? '<em>EN</em>' : ''; ?></span>
							<?php if ( 'floating' === $wpm_key ) : ?><span class="wpm-place-mock__float">EN</span><?php endif; ?>
							<?php if ( 'none' === $wpm_key ) : ?><span class="wpm-place-mock__block">FR · EN</span><?php endif; ?>
						</span>
						<span class="wpm-option__title">
							<strong><?php echo esc_html( $wpm_p[0] ); ?></strong>
							<?php if ( $wpm_p[2] ) : ?>
								<span class="wpm-badge wpm-badge--success"><?php esc_html_e( 'Recommended', 'pixellang-multilingual' ); ?></span>
							<?php endif; ?>
						</span>
						<span class="wpm-option__help"><?php echo esc_html( $wpm_p[1] ); ?></span>
					</span>
				</label>
			<?php endforeach; ?>
		</fieldset>

		<div class="wpm-switcher-section" data-place="menu">
			<div class="wpm-card__head wpm-card__head--section">
				<h3 class="wpm-card__title"><?php esc_html_e( 'In a menu', 'pixellang-multilingual' ); ?></h3>
			</div>
			<?php if ( $wpm_nav_posts ) : ?>
				<fieldset class="wpm-option-grid">
					<legend class="wpm-setting__label"><?php esc_html_e( 'Navigation menus', 'pixellang-multilingual' ); ?></legend>
					<?php
					foreach ( $wpm_nav_posts as $wpm_nav ) :
						$wpm_nav_lang = array_search( (int) $wpm_nav->ID, $wpm_versions, true );
						?>
						<label class="wpm-option">
							<input type="checkbox" name="switcher_menus[]" value="<?php echo esc_attr( $wpm_nav->ID ); ?>" <?php checked( in_array( (int) $wpm_nav->ID, $wpm_chosen, true ) ); ?> />
							<span class="wpm-option__body">
								<strong><?php echo esc_html( $wpm_nav->post_title ? $wpm_nav->post_title : __( '(no title)', 'pixellang-multilingual' ) ); ?></strong>
								<code>#<?php echo esc_html( $wpm_nav->ID ); ?></code>
							</span>
							<?php
							if ( $wpm_nav_lang ) {
								WPM_Admin::language_chip( $wpm_nav_lang );
							}
							?>
						</label>
					<?php endforeach; ?>
				</fieldset>
				<p class="wpm-help"><?php WPM_Icons::render( 'info', 16 ); ?><span><?php esc_html_e( 'A menu that has language versions (Menus tab) gets the switcher in all its versions.', 'pixellang-multilingual' ); ?></span></p>
			<?php endif; ?>
			<?php if ( get_registered_nav_menus() ) : ?>
				<fieldset class="wpm-option-grid">
					<legend class="wpm-setting__label"><?php esc_html_e( 'Classic menu locations', 'pixellang-multilingual' ); ?></legend>
					<?php foreach ( get_registered_nav_menus() as $wpm_loc => $wpm_loc_label ) : ?>
						<label class="wpm-option">
							<input type="checkbox" name="switcher_locations[]" value="<?php echo esc_attr( $wpm_loc ); ?>" <?php checked( in_array( $wpm_loc, $wpm_locs_on, true ) ); ?> />
							<span class="wpm-option__body"><strong><?php echo esc_html( $wpm_loc_label ); ?></strong><code><?php echo esc_html( $wpm_loc ); ?></code></span>
						</label>
					<?php endforeach; ?>
				</fieldset>
			<?php endif; ?>
			<?php if ( ! $wpm_nav_posts && ! get_registered_nav_menus() ) : ?>
				<p class="wpm-empty-inline"><?php esc_html_e( 'This site has no menu yet.', 'pixellang-multilingual' ); ?></p>
			<?php endif; ?>
			<div class="wpm-setting">
				<div class="wpm-setting__text">
					<label class="wpm-setting__label" for="wpm-switcher-display"><?php esc_html_e( 'Show languages as', 'pixellang-multilingual' ); ?></label>
					<p class="wpm-setting__help" id="wpm-switcher-display-help"><?php esc_html_e( 'Codes keep the menu compact; names are clearer for visitors.', 'pixellang-multilingual' ); ?></p>
				</div>
				<select class="wpm-select" id="wpm-switcher-display" name="switcher_display" aria-describedby="wpm-switcher-display-help">
					<option value="code" <?php selected( WPM_Settings::get( 'switcher_display' ), 'code' ); ?>><?php esc_html_e( 'Codes (EN)', 'pixellang-multilingual' ); ?></option>
					<option value="name" <?php selected( WPM_Settings::get( 'switcher_display' ), 'name' ); ?>><?php esc_html_e( 'Names (English)', 'pixellang-multilingual' ); ?></option>
				</select>
			</div>
			<?php $wpm_toggle( 'switcher_hide_current', (bool) WPM_Settings::get( 'switcher_hide_current' ), __( 'Hide the current language', 'pixellang-multilingual' ), __( 'Show only the other languages, as most bilingual sites do.', 'pixellang-multilingual' ) ); ?>
		</div>

		<div class="wpm-switcher-section" data-place="floating">
			<div class="wpm-card__head wpm-card__head--section">
				<h3 class="wpm-card__title"><?php esc_html_e( 'Floating', 'pixellang-multilingual' ); ?></h3>
			</div>
			<div class="wpm-setting">
				<div class="wpm-setting__text">
					<label class="wpm-setting__label" for="wpm-switcher-position"><?php esc_html_e( 'Position', 'pixellang-multilingual' ); ?></label>
					<p class="wpm-setting__help"><?php esc_html_e( 'The panel opens upward, toward the page.', 'pixellang-multilingual' ); ?></p>
				</div>
				<select class="wpm-select" id="wpm-switcher-position" name="switcher_floating_position">
					<option value="right" <?php selected( WPM_Settings::get( 'switcher_floating_position' ), 'right' ); ?>><?php esc_html_e( 'Bottom right', 'pixellang-multilingual' ); ?></option>
					<option value="left" <?php selected( WPM_Settings::get( 'switcher_floating_position' ), 'left' ); ?>><?php esc_html_e( 'Bottom left', 'pixellang-multilingual' ); ?></option>
				</select>
			</div>
			<div class="wpm-setting">
				<div class="wpm-setting__text">
					<label class="wpm-setting__label" for="wpm-switcher-theme"><?php esc_html_e( 'Appearance', 'pixellang-multilingual' ); ?></label>
					<p class="wpm-setting__help"><?php esc_html_e( 'Pick the one that stands out from your pages.', 'pixellang-multilingual' ); ?></p>
				</div>
				<select class="wpm-select" id="wpm-switcher-theme" name="switcher_floating_theme">
					<option value="light" <?php selected( WPM_Settings::get( 'switcher_floating_theme' ), 'light' ); ?>><?php esc_html_e( 'Light', 'pixellang-multilingual' ); ?></option>
					<option value="dark" <?php selected( WPM_Settings::get( 'switcher_floating_theme' ), 'dark' ); ?>><?php esc_html_e( 'Dark', 'pixellang-multilingual' ); ?></option>
				</select>
			</div>
		</div>

		<div class="wpm-card__head wpm-card__head--section">
			<h3 class="wpm-card__title"><?php esc_html_e( 'Designs of the Language Switcher block', 'pixellang-multilingual' ); ?></h3>
		</div>
		<p class="wpm-card__intro"><?php esc_html_e( 'Choose them in the block settings, with colors, typography, border and spacing. Preview with the languages of this site:', 'pixellang-multilingual' ); ?></p>
		<div class="wpm-design-previews">
			<?php
			$wpm_previews = array(
				'minimal'   => array( __( 'Minimal', 'pixellang-multilingual' ), array( 'design' => 'minimal', 'display' => 'code', 'separator' => 'slash' ) ),
				'segmented' => array( __( 'Segmented', 'pixellang-multilingual' ), array( 'design' => 'segmented', 'display' => 'code' ) ),
				'dropdown'  => array( __( 'Dropdown', 'pixellang-multilingual' ), array( 'design' => 'dropdown', 'display' => 'both', 'showIcon' => true, 'menuAlign' => 'left' ) ),
				'select'    => array( __( 'Select', 'pixellang-multilingual' ), array( 'design' => 'select', 'display' => 'name' ) ),
			);
			foreach ( $wpm_previews as $wpm_key => $wpm_prev ) :
				?>
				<figure class="wpm-design-preview">
					<div class="wpm-design-preview__stage"><?php echo WPM_Switcher::render( $wpm_prev[1] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPM_Switcher. ?></div>
					<figcaption><?php echo esc_html( $wpm_prev[0] ); ?></figcaption>
				</figure>
			<?php endforeach; ?>
		</div>
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
