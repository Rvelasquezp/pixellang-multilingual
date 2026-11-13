<?php
/**
 * Inline SVG icons for the admin (24×24 stroke icons, Lucide style).
 *
 * @package PixelLang_Multilingual
 */

defined( 'ABSPATH' ) || exit;

class WPM_Icons {

	/**
	 * PixelLang mark on a 20×20 grid: two speech bubbles, the front one
	 * carrying an "A". One filled path (even-odd) so WordPress can recolor
	 * the menu icon for every admin color scheme and state.
	 */
	const MARK = 'M4.5 10H3.5L1 12.5V3.5A2.5 2.5 0 0 1 3.5 1h7A2.5 2.5 0 0 1 13 3.5v1h-1.5v-1a1 1 0 0 0-1-1h-7a1 1 0 0 0-1 1v5h2ZM8.5 6h8A2.5 2.5 0 0 1 19 8.5v10L16.5 16h-8A2.5 2.5 0 0 1 6 13.5v-5A2.5 2.5 0 0 1 8.5 6Zm3 2h2l2.5 6h-1.75l-.5-1.5h-2.5l-.5 1.5H9Zm1 1.75-.75 1.75h1.5Z';

	/**
	 * The PixelLang mark as inline SVG.
	 *
	 * @param int    $size Size in pixels.
	 * @param string $fill Fill color.
	 * @return string
	 */
	public static function mark( $size = 20, $fill = 'currentColor' ) {
		return sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" class="wpm-icon" width="%1$d" height="%1$d" viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path fill="%2$s" fill-rule="evenodd" d="%3$s"/></svg>',
			(int) $size,
			esc_attr( $fill ),
			self::MARK
		);
	}

	/**
	 * Data URI for add_menu_page(). The black fill is replaced by WordPress
	 * (svg-painter) with the menu color of the active admin scheme.
	 *
	 * @return string
	 */
	public static function menu_icon() {
		return 'data:image/svg+xml;base64,' . base64_encode( self::mark( 20, 'black' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Menu icon data URI.
	}

	/**
	 * Inner SVG markup per icon.
	 *
	 * @var array<string,string>
	 */
	private static $paths = array(
		'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
		'globe'     => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18a14 14 0 0 1 0-18z"/>',
		'files'     => '<path d="M15 3H8a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V7z"/><path d="M15 3v4h4"/><path d="M3 8v11a2 2 0 0 0 2 2h9"/>',
		'settings'  => '<path d="M4 7h10"/><path d="M18 7h2"/><circle cx="16" cy="7" r="2"/><path d="M4 17h4"/><path d="M12 17h8"/><circle cx="10" cy="17" r="2"/>',
		'tools'     => '<path d="M14.7 6.3a4 4 0 0 0-5.4 5.2L3 17.8V21h3.2l6.3-6.3a4 4 0 0 0 5.2-5.4l-2.6 2.6-2.4-.5-.5-2.4z"/>',
		'check'     => '<path d="M20 6 9 17l-5-5"/>',
		'check-circle' => '<circle cx="12" cy="12" r="9"/><path d="m8.5 12 2.5 2.5 4.5-5"/>',
		'alert'     => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
		'info'      => '<circle cx="12" cy="12" r="9"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
		'x'         => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
		'plus'      => '<path d="M12 5v14"/><path d="M5 12h14"/>',
		'pencil'    => '<path d="M17 3a2.8 2.8 0 0 1 4 4L7.5 20.5 2 22l1.5-5.5z"/>',
		'external'  => '<path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
		'link'      => '<path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/>',
		'unlink'    => '<path d="m18.8 13.5 1.7-1.7a5 5 0 0 0-7-7l-1.7 1.7"/><path d="m5.2 10.5-1.7 1.7a5 5 0 0 0 7 7l1.7-1.7"/><path d="M8 2v3"/><path d="M2 8h3"/><path d="M16 22v-3"/><path d="M22 16h-3"/>',
		'search'    => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
		'menu'      => '<path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h10"/>',
		'layout'    => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M3 15h18"/>',
		'form'      => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8"/><path d="M8 12h8"/><path d="M8 16h4"/>',
		'refresh'   => '<path d="M21 12a9 9 0 0 1-15.4 6.4L3 16"/><path d="M3 21v-5h5"/><path d="M3 12a9 9 0 0 1 15.4-6.4L21 8"/><path d="M21 3v5h-5"/>',
		'trash'     => '<path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>',
		'grip'      => '<circle cx="9" cy="6" r="1"/><circle cx="15" cy="6" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="9" cy="18" r="1"/><circle cx="15" cy="18" r="1"/>',
		'up'        => '<path d="m18 15-6-6-6 6"/>',
		'down'      => '<path d="m6 9 6 6 6-6"/>',
		'star'      => '<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2L12 17.3 6.4 20.2l1.1-6.2L3 9.6l6.2-.9z"/>',
		'eye'       => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
		'arrow-right' => '<path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>',
		'tag'       => '<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><path d="M7.5 7.5h.01"/>',
		'type'      => '<path d="M4 7V5h16v2"/><path d="M9 19h6"/><path d="M12 5v14"/>',
		'shield'    => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
	);

	/**
	 * SVG markup of an icon (decorative: aria-hidden).
	 *
	 * @param string $name  Icon name.
	 * @param int    $size  Size in pixels.
	 * @param string $class Extra class.
	 * @return string
	 */
	public static function get( $name, $size = 18, $class = '' ) {
		if ( ! isset( self::$paths[ $name ] ) ) {
			return '';
		}
		return sprintf(
			'<svg class="wpm-icon %1$s" width="%2$d" height="%2$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%3$s</svg>',
			esc_attr( $class ),
			(int) $size,
			self::$paths[ $name ]
		);
	}

	/**
	 * Echoes an icon. The markup comes from the static list above.
	 *
	 * @param string $name Icon name.
	 * @param int    $size Size in pixels.
	 * @param string $class Extra class.
	 */
	public static function render( $name, $size = 18, $class = '' ) {
		echo self::get( $name, $size, $class ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG.
	}

	/**
	 * Allowed tags for wp_kses when an icon is part of a larger string.
	 *
	 * @return array
	 */
	public static function kses() {
		$common = array(
			'd' => true, 'cx' => true, 'cy' => true, 'r' => true, 'x' => true, 'y' => true,
			'width' => true, 'height' => true, 'rx' => true,
		);
		return array(
			'svg'    => array(
				'class' => true, 'width' => true, 'height' => true, 'viewbox' => true, 'fill' => true,
				'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true,
				'aria-hidden' => true, 'focusable' => true,
			),
			'path'   => $common,
			'circle' => $common,
			'rect'   => $common,
		);
	}
}
