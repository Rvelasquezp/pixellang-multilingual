/* global wp, wpmNavData */
( function () {
	if ( ! wp || ! wp.plugins || ! wp.element || ! wp.data || ! wp.components || ! wp.apiFetch ) {
		return;
	}

	var registerPlugin = wp.plugins.registerPlugin;
	var el             = wp.element.createElement;
	var useState       = wp.element.useState;
	var useSelect      = wp.data.useSelect;
	var SelectControl  = wp.components.SelectControl;
	var Spinner        = wp.components.Spinner;

	var PluginDocumentSettingPanel =
		( wp.editor   && wp.editor.PluginDocumentSettingPanel )  ||
		( wp.editPost && wp.editPost.PluginDocumentSettingPanel ) ||
		null;

	if ( ! PluginDocumentSettingPanel ) {
		console.warn( '[WP Multilingual] PluginDocumentSettingPanel not found.' );
		return;
	}

	function WpmNavPanel() {
		// All hooks unconditionally at the top.
		var postType = useSelect( function ( select ) {
			var store = select( 'core/editor' );
			return store ? store.getCurrentPostType() : null;
		} );

		var postId = useSelect( function ( select ) {
			var store = select( 'core/editor' );
			return store ? store.getCurrentPostId() : null;
		} );

		var saving = useSelect( function ( select ) {
			var store = select( 'core/editor' );
			return store ? store.isSavingPost() : false;
		} );

		var status  = useState( '' );       // '' | 'saving' | 'saved' | 'error'
		var setStatus = status[ 1 ];
		var statusVal = status[ 0 ];

		// Determine initial language from wpmNavData (set by PHP at page load).
		var initialLang = ( wpmNavData && wpmNavData.current_lang ) ? wpmNavData.current_lang : '';
		var selected    = useState( initialLang );
		var setSelected = selected[ 1 ];
		var selectedVal = selected[ 0 ];

		if ( postType !== 'wp_navigation' ) {
			return null;
		}

		var options = [ { label: '— not assigned —', value: '' } ];
		if ( wpmNavData && wpmNavData.languages ) {
			Object.keys( wpmNavData.languages ).forEach( function ( slug ) {
				var lang  = wpmNavData.languages[ slug ];
				var taken = lang.taken_by && String( lang.taken_by ) !== String( postId );
				options.push( {
					label:    lang.flag + ' ' + lang.label + ( taken ? ' (' + lang.taken_title + ')' : '' ),
					value:    slug,
					disabled: taken,
				} );
			} );
		}

		function onChange( value ) {
			setSelected( value );
			setStatus( 'saving' );

			wp.apiFetch( {
				path:   '/wpm/v1/nav-language',
				method: 'POST',
				data:   { nav_id: postId, lang: value },
			} ).then( function () {
				setStatus( 'saved' );
				setTimeout( function () { setStatus( '' ); }, 2500 );
			} ).catch( function ( err ) {
				console.error( '[WP Multilingual] Failed to save nav language:', err );
				setStatus( 'error' );
			} );
		}

		var statusEl = null;
		if ( statusVal === 'saving' ) {
			statusEl = el( 'p', { style: { fontSize: '12px', margin: '8px 0 0', display: 'flex', alignItems: 'center', gap: '6px' } },
				el( Spinner, { style: { margin: 0 } } ), 'Saving…'
			);
		} else if ( statusVal === 'saved' ) {
			statusEl = el( 'p', { style: { color: '#0a6e3b', fontSize: '12px', margin: '8px 0 0', fontWeight: '500' } },
				'✅ Saved.'
			);
		} else if ( statusVal === 'error' ) {
			statusEl = el( 'p', { style: { color: '#b32d2e', fontSize: '12px', margin: '8px 0 0' } },
				'❌ Error saving. Check console.'
			);
		} else if ( selectedVal ) {
			var langLabel = wpmNavData.languages[ selectedVal ]
				? wpmNavData.languages[ selectedVal ].flag + ' ' + wpmNavData.languages[ selectedVal ].label
				: selectedVal;
			statusEl = el( 'p', { style: { color: '#0a6e3b', fontSize: '12px', margin: '8px 0 0', fontWeight: '500' } },
				'✅ ' + langLabel + ' menu.'
			);
		} else {
			statusEl = el( 'p', { style: { color: '#757575', fontSize: '12px', margin: '8px 0 0' } },
				'Select a language to assign this menu.'
			);
		}

		return el(
			PluginDocumentSettingPanel,
			{ name: 'wpm-nav-language-panel', title: '🌐 Menu Language', initialOpen: true },
			el( SelectControl, {
				label:                   'This menu is for:',
				value:                   selectedVal,
				options:                 options,
				onChange:                onChange,
				__nextHasNoMarginBottom: true,
			} ),
			statusEl
		);
	}

	registerPlugin( 'wpm-nav-language', { render: WpmNavPanel } );
} )();
