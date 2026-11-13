<?php
/**
 * Screen shown instead of the plugin screens without a valid license.
 *
 * @package PixelLang_Multilingual
 */

defined( 'ABSPATH' ) || exit;

WPM_Admin::empty_state(
	'shield',
	__( 'A license is needed to change these settings', 'pixellang-multilingual' ),
	WPM_License::message(),
	current_user_can( 'manage_options' ) ? WPM_Admin::url( 'wpm-license' ) : '',
	__( 'Add the license key', 'pixellang-multilingual' )
);
