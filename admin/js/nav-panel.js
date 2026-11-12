/**
 * Site Editor: language of a Navigation menu (wp_navigation).
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
	var Notice = wp.components.Notice;
	var Spinner = wp.components.Spinner;
	var PluginDocumentSettingPanel = ( wp.editor && wp.editor.PluginDocumentSettingPanel ) || ( wp.editPost && wp.editPost.PluginDocumentSettingPanel );
	if ( ! PluginDocumentSettingPanel ) {
		return;
	}
	var data = window.wpmNavData || { languages: {}, menus: {} };

	function langOf( menus, id ) {
		var found = '';
		Object.keys( menus ).forEach( function ( slug ) {
			if ( String( menus[ slug ] ) === String( id ) ) {
				found = slug;
			}
		} );
		return found;
	}

	function NavLanguagePanel() {
		var post = useSelect( function ( select ) {
			var editor = select( 'core/editor' );
			return editor ? { type: editor.getCurrentPostType(), id: editor.getCurrentPostId() } : {};
		}, [] );
		var menusState = useState( data.menus || {} );
		var statusState = useState( '' );
		var menus = menusState[ 0 ];
		var status = statusState[ 0 ];

		if ( post.type !== 'wp_navigation' ) {
			return null;
		}
		var current = langOf( menus, post.id );
		var options = [ { label: __( 'Not assigned', 'pixellang-multilingual' ), value: '' } ];
		Object.keys( data.languages ).forEach( function ( slug ) {
			var taken = menus[ slug ] && String( menus[ slug ] ) !== String( post.id );
			options.push( {
				/* translators: placeholders: number. */
				label: data.languages[ slug ].label + ( taken ? ' · ' + sprintf( __( 'used by #%d', 'pixellang-multilingual' ), menus[ slug ] ) : '' ),
				value: slug,
			} );
		} );

		function onChange( value ) {
			statusState[ 1 ]( 'saving' );
			wp.apiFetch( { path: '/wpm/v1/nav-language', method: 'POST', data: { nav_id: post.id, lang: value } } )
				.then( function ( response ) {
					menusState[ 1 ]( response.menus || {} );
					statusState[ 1 ]( 'saved' );
				} )
				.catch( function () {
					statusState[ 1 ]( 'error' );
				} );
		}

		var feedback = null;
		if ( status === 'saving' ) {
			feedback = el( 'p', { className: 'wpm-panel-status' }, el( Spinner ), __( 'Saving…', 'pixellang-multilingual' ) );
		} else if ( status === 'error' ) {
			feedback = el( Notice, { status: 'error', isDismissible: false }, __( 'The language could not be saved.', 'pixellang-multilingual' ) );
		} else if ( current ) {
			feedback = el( 'p', { className: 'components-base-control__help' },
				/* translators: placeholders: language or content name. */
				sprintf( __( 'Navigation blocks showing a menu of another language display this one for %s visitors.', 'pixellang-multilingual' ), data.languages[ current ] ? data.languages[ current ].label : current ) );
		}

		return el(
			PluginDocumentSettingPanel,
			{ name: 'wpm-nav-language-panel', title: __( 'Language', 'pixellang-multilingual' ), initialOpen: true },
			el( SelectControl, {
				label: __( 'This menu is for', 'pixellang-multilingual' ),
				value: current,
				options: options,
				onChange: onChange,
				disabled: status === 'saving',
				__nextHasNoMarginBottom: true,
				__next40pxDefaultSize: true,
			} ),
			feedback
		);
	}

	wp.plugins.registerPlugin( 'wpm-nav-language', { render: NavLanguagePanel } );
}() );
