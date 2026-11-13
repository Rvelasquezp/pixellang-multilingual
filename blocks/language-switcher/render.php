<?php
/**
 * Language Switcher block markup (see WPM_Switcher for the designs).
 *
 * Class names (.wpm-lang-list, .wpm-lang-item, .wpm-active, .wpm-lang-link,
 * .wpm-lang-current) are unchanged since 1.0 because themes style them.
 *
 * @package PixelLang_Multilingual
 *
 * @var array    $attributes Block attributes.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

// Before 1.4 the design was a "style" string attribute, a name that block
// supports reserve for colors and typography: read it from the saved markup.
$wpm_attrs = (array) $attributes;
unset( $wpm_attrs['style'] );
if ( isset( $block ) && $block instanceof WP_Block && isset( $block->parsed_block['attrs']['style'] ) && is_string( $block->parsed_block['attrs']['style'] ) ) {
	$wpm_attrs['style'] = $block->parsed_block['attrs']['style'];
}

echo WPM_Switcher::render( $wpm_attrs, '', true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPM_Switcher.
