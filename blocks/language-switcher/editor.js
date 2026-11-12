/**
 * Language Switcher block (dynamic, rendered by render.php).
 */
( function ( wp ) {
	'use strict';
	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var ToggleControl = wp.components.ToggleControl;
	var Disabled = wp.components.Disabled;
	var ServerSideRender = wp.serverSideRender;

	wp.blocks.registerBlockType( 'wp-multilingual/language-switcher', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;

			return el(
				'div',
				useBlockProps(),
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Display', 'pixellang-multilingual' ), initialOpen: true },
						el( SelectControl, {
							label: __( 'Style', 'pixellang-multilingual' ),
							value: attributes.style,
							options: [
								{ label: __( 'Codes (FR · EN)', 'pixellang-multilingual' ), value: 'flags' },
								{ label: __( 'Language names', 'pixellang-multilingual' ), value: 'text' },
								{ label: __( 'Dropdown', 'pixellang-multilingual' ), value: 'dropdown' },
							],
							onChange: function ( value ) {
								setAttributes( { style: value } );
							},
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize: true,
						} ),
						el( ToggleControl, {
							label: __( 'Hide the current language', 'pixellang-multilingual' ),
							checked: !! attributes.hideCurrent,
							onChange: function ( value ) {
								setAttributes( { hideCurrent: value } );
							},
							__nextHasNoMarginBottom: true,
						} ),
						el( ToggleControl, {
							label: __( 'Hide languages without translation', 'pixellang-multilingual' ),
							help: __( 'Otherwise they link to the home page of that language.', 'pixellang-multilingual' ),
							checked: !! attributes.hideMissing,
							onChange: function ( value ) {
								setAttributes( { hideMissing: value } );
							},
							__nextHasNoMarginBottom: true,
						} )
					)
				),
				el(
					Disabled,
					null,
					el( ServerSideRender, {
						block: 'wp-multilingual/language-switcher',
						attributes: attributes,
						skipBlockSupportAttributes: true,
					} )
				)
			);
		},
		save: function () {
			return null;
		},
	} );
}( window.wp ) );
