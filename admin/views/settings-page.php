<?php
defined( 'ABSPATH' ) || exit;

$active_languages = get_option( 'wpm_languages', array() );
$all_languages    = wpm_get_available_languages();
$menus            = get_option( 'wpm_menus', array() );
$forms            = get_option( 'wpm_forms', array() );
$page_map         = get_option( 'wpm_page_map', array() );
$lang_slugs       = array_keys( $active_languages );

// Pass all languages to JS.
wp_localize_script( 'wpm-admin', 'wpmData', array(
	'available' => $all_languages,
	'active'    => array_keys( $active_languages ),
) );
?>
<div class="wrap wpm-settings">

	<div class="wpm-header">
		<span class="dashicons dashicons-translation"></span>
		<h1><?php esc_html_e( 'WP Multilingual Agence Pixel', 'wp-multilingual Agence Pixel' ); ?></h1>
	</div>

	<?php settings_errors( 'wpm_settings' ); ?>

	<form method="post" action="options.php" id="wpm-main-form">
		<?php settings_fields( 'wpm_settings' ); ?>

		<!-- =====================================================================
		     TAB NAV
		     ===================================================================== -->
		<nav class="wpm-tabs">
			<a href="#wpm-tab-languages" class="wpm-tab active"><?php esc_html_e( 'Languages', 'wp-multilingual' ); ?></a>
			<a href="#wpm-tab-menus"     class="wpm-tab"><?php esc_html_e( 'Menus', 'wp-multilingual' ); ?></a>
			<a href="#wpm-tab-forms"     class="wpm-tab"><?php esc_html_e( 'Gravity Forms', 'wp-multilingual' ); ?></a>
			<a href="#wpm-tab-pages"     class="wpm-tab"><?php esc_html_e( 'Page Map', 'wp-multilingual' ); ?></a>
		</nav>

		<!-- =====================================================================
		     TAB: LANGUAGES
		     ===================================================================== -->
		<div id="wpm-tab-languages" class="wpm-tab-panel">

			<div class="wpm-two-col">

				<!-- LEFT: Active languages -->
				<div class="wpm-col">
					<h2><?php esc_html_e( 'Active Languages', 'wp-multilingual' ); ?></h2>
					<p class="description"><?php esc_html_e( 'These languages are enabled on your site.', 'wp-multilingual' ); ?></p>

					<ul class="wpm-active-list" id="wpm-active-list">
					<?php foreach ( $active_languages as $slug => $cfg ) :
						$avail = isset( $all_languages[ $slug ] ) ? $all_languages[ $slug ] : array();
						$flag  = isset( $avail['flag'] ) ? $avail['flag'] : '🌐';
					?>
						<li class="wpm-active-item" data-slug="<?php echo esc_attr( $slug ); ?>">
							<span class="wpm-flag"><?php echo esc_html( $flag ); ?></span>
							<span class="wpm-lang-info">
								<strong><?php echo esc_html( $cfg['label'] ); ?></strong>
								<span class="wpm-locale"><?php echo esc_html( $cfg['locale'] ); ?></span>
							</span>
							<label class="wpm-default-label">
								<input type="radio" name="wpm_default_radio" value="<?php echo esc_attr( $slug ); ?>"
									<?php checked( ! empty( $cfg['default'] ) ); ?> />
								<?php esc_html_e( 'Default', 'wp-multilingual' ); ?>
							</label>
							<button type="button" class="wpm-remove-lang" data-slug="<?php echo esc_attr( $slug ); ?>" title="<?php esc_attr_e( 'Remove', 'wp-multilingual' ); ?>">✕</button>

							<!-- Hidden inputs -->
							<input type="hidden" name="wpm_languages[<?php echo esc_attr( $slug ); ?>][label]"   value="<?php echo esc_attr( $cfg['label'] ); ?>" />
							<input type="hidden" name="wpm_languages[<?php echo esc_attr( $slug ); ?>][locale]"  value="<?php echo esc_attr( $cfg['locale'] ); ?>" />
							<input type="hidden" name="wpm_languages[<?php echo esc_attr( $slug ); ?>][default]" value="<?php echo ! empty( $cfg['default'] ) ? '1' : '0'; ?>" class="wpm-default-hidden" />
						</li>
					<?php endforeach; ?>
					</ul>

					<?php if ( empty( $active_languages ) ) : ?>
						<p class="wpm-empty-notice"><?php esc_html_e( 'No languages added yet. Pick from the list →', 'wp-multilingual' ); ?></p>
					<?php endif; ?>
				</div>

				<!-- RIGHT: Language picker -->
				<div class="wpm-col">
					<h2><?php esc_html_e( 'Add a Language', 'wp-multilingual' ); ?></h2>
					<input type="search" id="wpm-lang-search" placeholder="<?php esc_attr_e( 'Search languages…', 'wp-multilingual' ); ?>" class="wpm-search" autocomplete="off" />

					<ul class="wpm-picker-list" id="wpm-picker-list">
					<?php foreach ( $all_languages as $slug => $info ) :
						$is_active = isset( $active_languages[ $slug ] );
					?>
						<li class="wpm-picker-item <?php echo $is_active ? 'wpm-picker-active' : ''; ?>"
							data-slug="<?php echo esc_attr( $slug ); ?>"
							data-label="<?php echo esc_attr( $info['label'] ); ?>"
							data-locale="<?php echo esc_attr( $info['locale'] ); ?>"
							data-flag="<?php echo esc_attr( $info['flag'] ); ?>"
							data-search="<?php echo esc_attr( strtolower( $info['english'] . ' ' . $info['label'] . ' ' . $slug ) ); ?>">
							<span class="wpm-flag"><?php echo esc_html( $info['flag'] ); ?></span>
							<span class="wpm-picker-name">
								<strong><?php echo esc_html( $info['label'] ); ?></strong>
								<small><?php echo esc_html( $info['english'] ); ?></small>
							</span>
							<?php if ( $is_active ) : ?>
								<span class="wpm-picker-badge"><?php esc_html_e( 'Active', 'wp-multilingual' ); ?></span>
							<?php else : ?>
								<button type="button" class="button button-small wpm-add-lang"><?php esc_html_e( '+ Add', 'wp-multilingual' ); ?></button>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
					</ul>
				</div>

			</div><!-- .wpm-two-col -->
		</div><!-- #wpm-tab-languages -->

		<!-- =====================================================================
		     TAB: MENUS
		     ===================================================================== -->
		<div id="wpm-tab-menus" class="wpm-tab-panel" style="display:none">
			<h2><?php esc_html_e( 'Navigation Menus per Language', 'wp-multilingual' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Enter the Navigation block post ID for each language (find it in the URL when editing the navigation in WP Admin → Appearance → Menus or in the editor).', 'wp-multilingual' ); ?>
			</p>
			<table class="widefat wpm-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Language', 'wp-multilingual' ); ?></th>
						<th><?php esc_html_e( 'Navigation post ID', 'wp-multilingual' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $active_languages as $slug => $cfg ) : ?>
					<tr>
						<td>
							<?php $avail = isset( $all_languages[ $slug ] ) ? $all_languages[ $slug ] : array(); ?>
							<?php echo isset( $avail['flag'] ) ? esc_html( $avail['flag'] ) : ''; ?>
							<?php echo esc_html( $cfg['label'] ); ?>
							<code><?php echo esc_html( $slug ); ?></code>
						</td>
						<td>
							<input type="number" name="wpm_menus[<?php echo esc_attr( $slug ); ?>]"
								value="<?php echo esc_attr( isset( $menus[ $slug ] ) ? $menus[ $slug ] : 0 ); ?>"
								class="small-text" min="0" />
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<!-- =====================================================================
		     TAB: GRAVITY FORMS
		     ===================================================================== -->
		<div id="wpm-tab-forms" class="wpm-tab-panel" style="display:none">
			<h2><?php esc_html_e( 'Gravity Forms per Language', 'wp-multilingual' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Enter the Gravity Form ID for each language. Leave 0 to skip.', 'wp-multilingual' ); ?>
			</p>
			<table class="widefat wpm-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Language', 'wp-multilingual' ); ?></th>
						<th><?php esc_html_e( 'Form ID', 'wp-multilingual' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $active_languages as $slug => $cfg ) : ?>
					<tr>
						<td>
							<?php $avail = isset( $all_languages[ $slug ] ) ? $all_languages[ $slug ] : array(); ?>
							<?php echo isset( $avail['flag'] ) ? esc_html( $avail['flag'] ) : ''; ?>
							<?php echo esc_html( $cfg['label'] ); ?>
							<code><?php echo esc_html( $slug ); ?></code>
						</td>
						<td>
							<input type="number" name="wpm_forms[<?php echo esc_attr( $slug ); ?>]"
								value="<?php echo esc_attr( isset( $forms[ $slug ] ) ? $forms[ $slug ] : 0 ); ?>"
								class="small-text" min="0" />
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<!-- =====================================================================
		     TAB: PAGE MAP
		     ===================================================================== -->
		<div id="wpm-tab-pages" class="wpm-tab-panel" style="display:none">
			<h2><?php esc_html_e( 'Page Translation Map', 'wp-multilingual' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Each row links the same page in different languages. Enter the post ID for each language version.', 'wp-multilingual' ); ?>
			</p>
			<table class="widefat wpm-table" id="wpm-page-map-table">
				<thead>
					<tr>
						<?php foreach ( $lang_slugs as $s ) :
							$avail = isset( $all_languages[ $s ] ) ? $all_languages[ $s ] : array();
						?>
							<th>
								<?php echo isset( $avail['flag'] ) ? esc_html( $avail['flag'] ) : ''; ?>
								<?php echo esc_html( strtoupper( $s ) ); ?>
							</th>
						<?php endforeach; ?>
						<th><?php esc_html_e( 'Remove', 'wp-multilingual' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $page_map as $group_key => $group ) : ?>
					<tr>
						<?php foreach ( $lang_slugs as $s ) : ?>
							<td>
								<input type="number"
									name="wpm_page_map[<?php echo esc_attr( $group_key ); ?>][<?php echo esc_attr( $s ); ?>]"
									value="<?php echo esc_attr( isset( $group[ $s ] ) ? $group[ $s ] : 0 ); ?>"
									class="small-text" min="0" />
							</td>
						<?php endforeach; ?>
						<td><button type="button" class="button wpm-remove-row">✕</button></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p>
				<button type="button" class="button" id="wpm-add-page-group">
					<?php esc_html_e( '+ Add Translation Group', 'wp-multilingual' ); ?>
				</button>
			</p>
			<p class="description">
				<span id="wpm-pagemap-slugs" style="display:none"><?php echo esc_attr( implode( ',', $lang_slugs ) ); ?></span>
			</p>
		</div>

		<div class="wpm-save-bar">
			<?php submit_button( __( 'Save Settings', 'wp-multilingual' ), 'primary large', 'submit', false ); ?>
		</div>

	</form>
</div><!-- .wrap -->
