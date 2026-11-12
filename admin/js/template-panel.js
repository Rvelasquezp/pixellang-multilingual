/**
 * Site Editor: language versions of a template part.
 */
/* global wp */
( function () {
	'use strict';
	if ( ! window.wp || ! wp.plugins || ! wp.element || ! wp.data || ! wp.components || ! wp.apiFetch ) {
		return;
	}
	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var useSelect = wp.data.useSelect;
	var __ = wp.i18n.__;
	var sprintf = wp.i18n.sprintf;
	var SelectControl = wp.components.SelectControl;
	var Button = wp.components.Button;
	var Notice = wp.components.Notice;
	var Spinner = wp.components.Spinner;
	var PluginDocumentSettingPanel = ( wp.editor && wp.editor.PluginDocumentSettingPanel ) || ( wp.editPost && wp.editPost.PluginDocumentSettingPanel );
	if ( ! PluginDocumentSettingPanel ) {
		return;
	}
	var data = window.wpmTemplateData || { languages: {}, template_map: {}, assignments: {} };

	/**
	 * Group of a part: its slug without a language suffix (footer-en → footer).
	 */
	function deriveGroup( slug ) {
		var base = slug;
		Object.keys( data.languages ).forEach( function ( lang ) {
			var suffix = '-' + lang;
			if ( base.slice( -suffix.length ) === suffix ) {
				base = base.slice( 0, -suffix.length );
			}
		} );
		return base;
	}

	function editUrl( slug ) {
		return data.editBase + '?p=' + encodeURIComponent( '/wp_template_part/' + data.theme + '//' + slug ) + '&canvas=edit';
	}

	function TemplateLanguagePanel() {
		var post = useSelect( function ( select ) {
			var editor = select( 'core/editor' );
			if ( ! editor ) {
				return {};
			}
			var current = editor.getCurrentPost() || {};
			return { type: editor.getCurrentPostType(), id: current.wp_id || 0, slug: current.slug || '' };
		}, [] );
		var mapState = useState( data.template_map || {} );
		var langState = useState( null );
		var statusState = useState( '' );
		var busyState = useState( '' );

		if ( post.type !== 'wp_template_part' ) {
			return null;
		}
		var panel = function () {
			return el.apply( null, [ PluginDocumentSettingPanel, { name: 'wpm-template-language-panel', title: __( 'Languages', 'pixellang-multilingual' ), initialOpen: true } ].concat( Array.prototype.slice.call( arguments ) ) );
		};
		if ( ! post.id ) {
			return panel( el( 'p', { className: 'components-base-control__help' }, __( 'Save this template part once, then assign its language.', 'pixellang-multilingual' ) ) );
		}

		var group = deriveGroup( post.slug );
		var saved = data.assignments[ post.id ] ? data.assignments[ post.id ].lang : '';
		var active = langState[ 0 ] !== null ? langState[ 0 ] : saved;
		var versions = mapState[ 0 ][ group ] || {};
		var status = statusState[ 0 ];

		var options = [ { label: __( 'Not assigned', 'pixellang-multilingual' ), value: '' } ];
		Object.keys( data.languages ).forEach( function ( slug ) {
			options.push( { label: data.languages[ slug ].label, value: slug } );
		} );

		function assign( lang ) {
			langState[ 1 ]( lang );
			if ( ! lang ) {
				return;
			}
			statusState[ 1 ]( 'saving' );
			wp.apiFetch( { path: '/wpm/v1/template-language', method: 'POST', data: { template_id: post.id, lang: lang, group: group } } )
				.then( function ( response ) {
					var next = Object.assign( {}, mapState[ 0 ] );
					next[ group ] = response.group;
					mapState[ 1 ]( next );
					statusState[ 1 ]( 'saved' );
				} )
				.catch( function () {
					statusState[ 1 ]( 'error' );
				} );
		}

		function createVersion( lang ) {
			busyState[ 1 ]( lang );
			wp.apiFetch( { path: '/wpm/v1/template-duplicate', method: 'POST', data: { template_id: post.id, lang: lang, group: group } } )
				.then( function ( response ) {
					window.location.href = response.edit_url;
				} )
				.catch( function () {
					busyState[ 1 ]( '' );
					statusState[ 1 ]( 'error' );
				} );
		}

		var rows = Object.keys( data.languages ).map( function ( slug ) {
			var info = versions[ slug ];
			var action;
			if ( slug === active ) {
				action = el( 'span', { className: 'wpm-tp-current' }, __( 'This version', 'pixellang-multilingual' ) );
			} else if ( info ) {
				action = el( Button, { variant: 'link', href: editUrl( info.slug ) }, __( 'Edit', 'pixellang-multilingual' ) );
			} else if ( busyState[ 0 ] === slug ) {
				action = el( Spinner );
			} else {
				action = el( Button, {
					variant: 'secondary',
					size: 'small',
					disabled: ! active,
					onClick: function () {
						createVersion( slug );
					},
				}, __( 'Create', 'pixellang-multilingual' ) );
			}
			return el( 'li', { key: slug, style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: '8px', padding: '6px 0', borderTop: '1px solid #e2e8f0', margin: 0 } },
				el( 'span', null, el( 'code', { style: { marginRight: '8px' } }, slug.toUpperCase() ), data.languages[ slug ].label ),
				action
			);
		} );

		var feedback = null;
		if ( status === 'saving' ) {
			feedback = el( 'p', null, el( Spinner ), __( 'Saving…', 'pixellang-multilingual' ) );
		} else if ( status === 'error' ) {
			feedback = el( Notice, { status: 'error', isDismissible: false }, __( 'The change could not be saved.', 'pixellang-multilingual' ) );
		}

		return panel(
			el( SelectControl, {
				label: __( 'This template part is in', 'pixellang-multilingual' ),
				value: active,
				options: options,
				onChange: assign,
				__nextHasNoMarginBottom: true,
				__next40pxDefaultSize: true,
			} ),
			/* translators: placeholders: language or content name. */
			el( 'p', { className: 'components-base-control__help' }, sprintf( __( 'Group: %s', 'pixellang-multilingual' ), group ) ),
			feedback,
			active ? null : el( 'p', { className: 'components-base-control__help' }, __( 'Assign a language to create the other versions.', 'pixellang-multilingual' ) ),
			el( 'ul', { style: { margin: '12px 0 0', padding: 0, listStyle: 'none' } }, rows )
		);
	}

	wp.plugins.registerPlugin( 'wpm-template-language', { render: TemplateLanguagePanel } );
}() );
