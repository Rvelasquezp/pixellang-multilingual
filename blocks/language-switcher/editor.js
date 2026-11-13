/**
 * Language Switcher block (dynamic, rendered by WPM_Switcher in PHP).
 *
 * Settings: a visual picker of the four designs, then the options that apply
 * to the chosen design. Colors, typography, spacing and border come from the
 * block supports (Styles tab). Blocks saved before 1.4 only have "style":
 * the effective design is derived from it, like in PHP.
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

	// "style" was the design before 1.4 (a string); it is now the block
	// supports object (colors, typography), so only a string means a design.
	function legacy( a ) {
		return typeof a.style === 'string' ? a.style : '';
	}

	function effective( a ) {
		var old = legacy( a );
		var design = a.design || ( old === 'dropdown' ? 'select' : 'minimal' );
		var display = a.display || ( old === 'text' || design === 'select' ? 'name' : ( design === 'dropdown' ? 'both' : 'code' ) );
		return { design: design, display: display };
	}

	var OWN = [ 'design', 'display', 'separator', 'size', 'showIcon', 'menuAlign', 'menuTheme', 'hideCurrent', 'hideMissing' ];

	// Attributes sent to the server preview: the block's own, never the
	// supports (their styles are applied to the editor wrapper).
	function previewAttributes( a ) {
		var out = {};
		OWN.forEach( function ( key ) {
			if ( a[ key ] !== undefined ) {
				out[ key ] = a[ key ];
			}
		} );
		var e = effective( a );
		out.design = e.design;
		out.display = e.display;
		return out;
	}

	var DESIGNS = [
		{ value: 'minimal', label: __( 'Minimal', 'pixellang-multilingual' ) },
		{ value: 'segmented', label: __( 'Segmented', 'pixellang-multilingual' ) },
		{ value: 'dropdown', label: __( 'Dropdown', 'pixellang-multilingual' ) },
		{ value: 'select', label: __( 'Select', 'pixellang-multilingual' ) },
	];

	// Small schematic of each design for the picker (decorative).
	function mock( value ) {
		if ( value === 'minimal' ) {
			return el( 'span', { className: 'wpm-pick__mock wpm-pick__mock--minimal' }, el( 'b', null, 'FR' ), el( 'i', null, '/' ), el( 'span', null, 'EN' ) );
		}
		if ( value === 'segmented' ) {
			return el( 'span', { className: 'wpm-pick__mock wpm-pick__mock--segmented' }, el( 'b', null, 'FR' ), el( 'span', null, 'EN' ) );
		}
		if ( value === 'dropdown' ) {
			return el( 'span', { className: 'wpm-pick__mock wpm-pick__mock--dropdown' }, el( 'span', null, 'FR' ), el( 'i', null, '' ), el( 'em', null ) );
		}
		return el( 'span', { className: 'wpm-pick__mock wpm-pick__mock--select' }, el( 'span', null, 'Français' ), el( 'i', null, '' ) );
	}

	function DesignPicker( props ) {
		return el(
			'div',
			{ className: 'wpm-pick', role: 'group', 'aria-label': __( 'Design', 'pixellang-multilingual' ) },
			DESIGNS.map( function ( d ) {
				var active = d.value === props.value;
				return el(
					'button',
					{
						key: d.value,
						type: 'button',
						className: 'wpm-pick__item' + ( active ? ' is-active' : '' ),
						'aria-pressed': active,
						onClick: function () {
							props.onChange( d.value );
						},
					},
					mock( d.value ),
					el( 'span', { className: 'wpm-pick__label' }, d.label )
				);
			} )
		);
	}

	wp.blocks.registerBlockType( 'wp-multilingual/language-switcher', {
		edit: function ( props ) {
			var a = props.attributes;
			var set = props.setAttributes;
			var e = effective( a );
			var common = { __nextHasNoMarginBottom: true, __next40pxDefaultSize: true };

			function control( Comp, options ) {
				return el( Comp, Object.assign( {}, common, options ) );
			}

			var designPanel = el(
				PanelBody,
				{ title: __( 'Design', 'pixellang-multilingual' ), initialOpen: true },
				el( DesignPicker, {
					value: e.design,
					onChange: function ( value ) {
						var next = { design: value };
						// The 1.0 string gives way to the block supports object.
						if ( legacy( a ) ) {
							next.style = undefined;
						}
						// A sensible text for each design, kept if already chosen.
						if ( ! a.display ) {
							next.display = value === 'select' ? 'name' : ( value === 'dropdown' ? 'both' : 'code' );
						}
						set( next );
					},
				} ),
				control( SelectControl, {
					label: __( 'Show languages as', 'pixellang-multilingual' ),
					value: e.display,
					options: [
						{ label: __( 'Codes (EN)', 'pixellang-multilingual' ), value: 'code' },
						{ label: __( 'Names (English)', 'pixellang-multilingual' ), value: 'name' },
						{ label: __( 'Names and codes', 'pixellang-multilingual' ), value: 'both' },
					],
					onChange: function ( value ) {
						set( { display: value } );
					},
				} ),
				e.design === 'minimal' && control( SelectControl, {
					label: __( 'Separator', 'pixellang-multilingual' ),
					value: a.separator,
					options: [
						{ label: __( 'None', 'pixellang-multilingual' ), value: 'none' },
						{ label: __( 'Slash (FR / EN)', 'pixellang-multilingual' ), value: 'slash' },
						{ label: __( 'Bar (FR | EN)', 'pixellang-multilingual' ), value: 'pipe' },
						{ label: __( 'Dot (FR · EN)', 'pixellang-multilingual' ), value: 'dot' },
					],
					onChange: function ( value ) {
						set( { separator: value } );
					},
				} ),
				control( SelectControl, {
					label: __( 'Size', 'pixellang-multilingual' ),
					help: __( 'Or set a font size in the Styles tab.', 'pixellang-multilingual' ),
					value: a.size,
					options: [
						{ label: __( 'Inherit from the theme', 'pixellang-multilingual' ), value: '' },
						{ label: __( 'Small', 'pixellang-multilingual' ), value: 'small' },
						{ label: __( 'Medium', 'pixellang-multilingual' ), value: 'medium' },
						{ label: __( 'Large', 'pixellang-multilingual' ), value: 'large' },
					],
					onChange: function ( value ) {
						set( { size: value } );
					},
				} ),
				control( ToggleControl, {
					label: __( 'Show a globe icon', 'pixellang-multilingual' ),
					checked: !! a.showIcon,
					onChange: function ( value ) {
						set( { showIcon: value } );
					},
				} )
			);

			var dropdownPanel = e.design === 'dropdown' && el(
				PanelBody,
				{ title: __( 'Dropdown panel', 'pixellang-multilingual' ), initialOpen: true },
				control( SelectControl, {
					label: __( 'Open toward', 'pixellang-multilingual' ),
					help: __( 'The panel opens upward by itself when there is no room below.', 'pixellang-multilingual' ),
					value: a.menuAlign,
					options: [
						{ label: __( 'The left (switcher on the right of the page)', 'pixellang-multilingual' ), value: 'right' },
						{ label: __( 'The right (switcher on the left of the page)', 'pixellang-multilingual' ), value: 'left' },
					],
					onChange: function ( value ) {
						set( { menuAlign: value } );
					},
				} ),
				control( SelectControl, {
					label: __( 'Panel appearance', 'pixellang-multilingual' ),
					value: a.menuTheme,
					options: [
						{ label: __( 'Light', 'pixellang-multilingual' ), value: 'light' },
						{ label: __( 'Dark', 'pixellang-multilingual' ), value: 'dark' },
					],
					onChange: function ( value ) {
						set( { menuTheme: value } );
					},
				} )
			);

			var behaviorPanel = el(
				PanelBody,
				{ title: __( 'Languages', 'pixellang-multilingual' ), initialOpen: false },
				control( ToggleControl, {
					label: __( 'Hide the current language', 'pixellang-multilingual' ),
					help: e.design === 'dropdown' ? __( 'It stays on the button.', 'pixellang-multilingual' ) : undefined,
					checked: !! a.hideCurrent,
					onChange: function ( value ) {
						set( { hideCurrent: value } );
					},
				} ),
				control( ToggleControl, {
					label: __( 'Hide languages without translation', 'pixellang-multilingual' ),
					help: __( 'Otherwise they link to the home page of that language.', 'pixellang-multilingual' ),
					checked: !! a.hideMissing,
					onChange: function ( value ) {
						set( { hideMissing: value } );
					},
				} )
			);

			return el(
				'div',
				useBlockProps( { className: 'wpm-switcher-editor' } ),
				el( InspectorControls, null, designPanel, dropdownPanel, behaviorPanel ),
				el(
					Disabled,
					null,
					el( ServerSideRender, {
						block: 'wp-multilingual/language-switcher',
						attributes: previewAttributes( a ),
						EmptyResponsePlaceholder: function () {
							return el( 'p', { className: 'wpm-switcher-empty' }, __( 'Language switcher: add a second language to see it.', 'pixellang-multilingual' ) );
						},
					} )
				)
			);
		},
		save: function () {
			return null;
		},
	} );
}( window.wp ) );
