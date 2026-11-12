<?php
/**
 * Language Switcher block markup.
 *
 * Class names (.wpm-lang-list, .wpm-lang-item, .wpm-active, .wpm-lang-link,
 * .wpm-lang-current) are unchanged since 1.0 because themes style them.
 *
 * @package PixelLang_Multilingual
 *
 * @var array $attributes Block attributes.
 */

defined( 'ABSPATH' ) || exit;

$wpm_manager = WPM_Language_Manager::instance();
$wpm_urls    = WPM_Url_Handler::instance();
$wpm_current = $wpm_manager->get_current();
$wpm_style   = isset( $attributes['style'] ) && in_array( $attributes['style'], array( 'flags', 'text', 'dropdown' ), true ) ? $attributes['style'] : 'flags';
$wpm_hide    = ! empty( $attributes['hideCurrent'] );
$wpm_strict  = ! empty( $attributes['hideMissing'] );

// One entry per language: url '' means "no translation" when hideMissing is on.
$wpm_items = array();
foreach ( $wpm_manager->get_all() as $wpm_slug => $wpm_cfg ) {
	$wpm_is_current = $wpm_slug === $wpm_current;
	$wpm_url        = $wpm_is_current ? '' : $wpm_urls->get_language_url( $wpm_slug, ! $wpm_strict );
	if ( ( $wpm_is_current && $wpm_hide ) || ( ! $wpm_is_current && ! $wpm_url ) ) {
		continue;
	}
	$wpm_items[ $wpm_slug ] = array(
		'label'    => $wpm_cfg['label'],
		'hreflang' => $wpm_manager->get_hreflang( $wpm_slug ),
		'url'      => $wpm_url,
		'current'  => $wpm_is_current,
	);
}
if ( ! $wpm_items ) {
	return;
}

$wpm_wrapper = get_block_wrapper_attributes(
	array(
		'class'      => 'wpm-language-switcher wpm-style-' . $wpm_style,
		'aria-label' => __( 'Language', 'pixellang-multilingual' ),
	)
);
?>
<nav <?php echo $wpm_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by get_block_wrapper_attributes(). ?>>
	<?php if ( 'dropdown' === $wpm_style ) : ?>
		<?php $wpm_select_id = wp_unique_id( 'wpm-switcher-' ); ?>
		<label class="screen-reader-text" for="<?php echo esc_attr( $wpm_select_id ); ?>"><?php esc_html_e( 'Choose a language', 'pixellang-multilingual' ); ?></label>
		<select id="<?php echo esc_attr( $wpm_select_id ); ?>" data-wpm-switch>
			<?php foreach ( $wpm_items as $wpm_slug => $wpm_item ) : ?>
				<option value="<?php echo esc_url( $wpm_item['url'] ); ?>" lang="<?php echo esc_attr( $wpm_item['hreflang'] ); ?>" <?php selected( $wpm_item['current'] ); ?>><?php echo esc_html( $wpm_item['label'] ); ?></option>
			<?php endforeach; ?>
		</select>
	<?php else : ?>
		<ul class="wpm-lang-list">
			<?php
			foreach ( $wpm_items as $wpm_slug => $wpm_item ) :
				$wpm_text = 'text' === $wpm_style ? $wpm_item['label'] : $wpm_slug;
				// Codes get a full name, starting with the visible text (WCAG 2.5.3).
				$wpm_aria = 'text' === $wpm_style ? '' : ' aria-label="' . esc_attr( sprintf( '%1$s (%2$s)', $wpm_slug, $wpm_item['label'] ) ) . '"';
				?>
				<li class="wpm-lang-item<?php echo $wpm_item['current'] ? ' wpm-active' : ''; ?>">
					<?php if ( $wpm_item['current'] ) : ?>
						<span class="wpm-lang-current" aria-current="true" lang="<?php echo esc_attr( $wpm_item['hreflang'] ); ?>"<?php echo $wpm_aria; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?>><?php echo esc_html( $wpm_text ); ?></span>
					<?php else : ?>
						<a class="wpm-lang-link" href="<?php echo esc_url( $wpm_item['url'] ); ?>" hreflang="<?php echo esc_attr( $wpm_item['hreflang'] ); ?>" lang="<?php echo esc_attr( $wpm_item['hreflang'] ); ?>"<?php echo $wpm_aria; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?>><?php echo esc_html( $wpm_text ); ?></a>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</nav>
