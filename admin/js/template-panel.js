/* global wp, wpmTemplateData */
( function () {
	if ( ! wp || ! wp.plugins || ! wp.element || ! wp.data || ! wp.components || ! wp.apiFetch ) {
		return;
	}

	var registerPlugin = wp.plugins.registerPlugin;
	var el             = wp.element.createElement;
	var useState       = wp.element.useState;
	var useSelect      = wp.data.useSelect;
	var SelectControl  = wp.components.SelectControl;
	var Button         = wp.components.Button;
	var Spinner        = wp.components.Spinner;

	var PluginDocumentSettingPanel =
		( wp.editor   && wp.editor.PluginDocumentSettingPanel )  ||
		( wp.editPost && wp.editPost.PluginDocumentSettingPanel ) ||
		null;

	if ( ! PluginDocumentSettingPanel ) {
		return;
	}

	/**
	 * Derive the group name from a template slug.
	 * Strips known language suffixes: footer-en → footer, header-fr → header.
	 */
	function deriveGroup( slug, languages ) {
		var base = slug;
		Object.keys( languages ).forEach( function ( lang ) {
			var suffix = '-' + lang;
			if ( base.slice( -suffix.length ) === suffix ) {
				base = base.slice( 0, -suffix.length );
			}
		} );
		return base;
	}

	function WpmTemplatePanel() {
		var postType = useSelect( function ( select ) {
			var store = select( 'core/editor' );
			return store ? store.getCurrentPostType() : null;
		} );

		var currentPost = useSelect( function ( select ) {
			var store = select( 'core/editor' );
			return store ? store.getCurrentPost() : null;
		} );

		// All hooks must run before early returns.
		var statusState    = useState( '' );
		var status         = statusState[0];
		var setStatus      = statusState[1];

		var mapState       = useState( ( wpmTemplateData && wpmTemplateData.template_map ) ? wpmTemplateData.template_map : {} );
		var templateMap    = mapState[0];
		var setTemplateMap = mapState[1];

		var dupState       = useState( '' );
		var duplicating    = dupState[0];
		var setDuplicating = dupState[1];

		var langState      = useState( null ); // null = not yet initialised
		var selectedLang   = langState[0];
		var setSelectedLang = langState[1];

		if ( postType !== 'wp_template_part' ) {
			return null;
		}

		var numericId = ( currentPost && currentPost.wp_id ) ? currentPost.wp_id : 0;

		if ( ! numericId ) {
			return el(
				PluginDocumentSettingPanel,
				{ name: 'wpm-template-language-panel', title: '🌐 Template Languages', initialOpen: true },
				el( 'p', { style: { fontSize: '12px', color: '#757575', margin: 0 } },
					'Save this template part first, then assign a language.'
				)
			);
		}

		var languages = ( wpmTemplateData && wpmTemplateData.languages ) ? wpmTemplateData.languages : {};
		var theme     = ( wpmTemplateData && wpmTemplateData.theme )      ? wpmTemplateData.theme      : '';

		// Derive group from the template's own slug — no manual input needed.
		var postSlug = ( currentPost && currentPost.slug ) ? currentPost.slug : '';
		var group    = deriveGroup( postSlug, languages );

		// Initialise selectedLang from assignments on first render.
		var assignments   = ( wpmTemplateData && wpmTemplateData.assignments ) ? wpmTemplateData.assignments : {};
		var savedLang     = ( numericId && assignments[ numericId ] ) ? assignments[ numericId ].lang : '';
		var activeLang    = ( selectedLang !== null ) ? selectedLang : savedLang;

		var langOptions = [ { label: '— not assigned —', value: '' } ];
		Object.keys( languages ).forEach( function ( slug ) {
			langOptions.push( {
				label: languages[ slug ].flag + ' ' + languages[ slug ].label,
				value: slug,
			} );
		} );

		function saveAssignment( lang ) {
			if ( ! lang || ! group || ! numericId ) {
				return;
			}
			setStatus( 'saving' );
			wp.apiFetch( {
				path:   '/wpm/v1/template-language',
				method: 'POST',
				data:   { template_id: numericId, lang: lang, group: group },
			} ).then( function ( res ) {
				setStatus( 'saved' );
				var newMap = Object.assign( {}, templateMap );
				newMap[ group ] = res.group;
				setTemplateMap( newMap );
				setTimeout( function () { setStatus( '' ); }, 2500 );
			} ).catch( function () {
				setStatus( 'error' );
			} );
		}

		function createVersion( lang ) {
			setDuplicating( lang );
			wp.apiFetch( {
				path:   '/wpm/v1/template-duplicate',
				method: 'POST',
				data:   { template_id: numericId, lang: lang, group: group },
			} ).then( function ( res ) {
				setDuplicating( '' );
				var newMap = Object.assign( {}, templateMap );
				if ( ! newMap[ group ] ) {
					newMap[ group ] = {};
				}
				newMap[ group ][ lang ] = { id: res.template_id, slug: res.slug };
				setTemplateMap( newMap );
				window.location.href = res.edit_url;
			} ).catch( function () {
				setDuplicating( '' );
			} );
		}

		// Status row.
		var statusEl = null;
		if ( status === 'saving' ) {
			statusEl = el( 'p', { style: { fontSize: '12px', margin: '8px 0 0', display: 'flex', alignItems: 'center', gap: '6px', color: '#757575' } },
				el( Spinner, { style: { margin: 0 } } ), 'Saving…'
			);
		} else if ( status === 'saved' ) {
			statusEl = el( 'p', { style: { fontSize: '12px', margin: '8px 0 0', color: '#0a6e3b', fontWeight: '500' } }, '✅ Saved.' );
		} else if ( status === 'error' ) {
			statusEl = el( 'p', { style: { fontSize: '12px', margin: '8px 0 0', color: '#b32d2e' } }, '❌ Error saving.' );
		}

		// Build version rows — all languages.
		var groupVersions = templateMap[ group ] || {};
		var versionRows   = [];

		Object.keys( languages ).forEach( function ( slug ) {
			var lang      = languages[ slug ];
			var info      = groupVersions[ slug ];
			var isCurrent = slug === activeLang;
			var editUrl   = info
				? ( '/wp-admin/site-editor.php?p=' + encodeURIComponent( '/wp_template_part/' + theme + '//' + info.slug ) + '&canvas=edit' )
				: null;

			var actionEl;
			if ( isCurrent ) {
				actionEl = el( 'span', { style: { fontSize: '11px', color: '#0a6e3b', fontWeight: '600' } }, '← Current' );
			} else if ( info && editUrl ) {
				actionEl = el( Button, { variant: 'link', href: editUrl, style: { fontSize: '12px' } }, 'Edit →' );
			} else if ( duplicating === slug ) {
				actionEl = el( Spinner, { style: { margin: 0, width: '16px', height: '16px' } } );
			} else {
				actionEl = el( Button, {
					variant: 'primary',
					style:   { fontSize: '11px', padding: '2px 10px', height: 'auto' },
					onClick: function () { createVersion( slug ); },
				}, '+ Create ' + lang.label + ' version' );
			}

			versionRows.push(
				el( 'div', {
					key:   slug,
					style: {
						display:         'flex',
						alignItems:      'center',
						justifyContent:  'space-between',
						padding:         isCurrent ? '6px 12px' : '6px 0',
						margin:          isCurrent ? '0 -12px' : '0',
						borderTop:       '1px solid #f0f0f0',
						backgroundColor: isCurrent ? '#f0faf4' : 'transparent',
					},
				},
				el( 'span', { style: { fontSize: '13px' } }, lang.flag + ' ' + lang.label ),
				actionEl
				)
			);
		} );

		return el(
			PluginDocumentSettingPanel,
			{ name: 'wpm-template-language-panel', title: '🌐 Template Languages', initialOpen: true },

			el( SelectControl, {
				label:                   'This template is in:',
				value:                   activeLang,
				options:                 langOptions,
				onChange:                function ( val ) {
					setSelectedLang( val );
					saveAssignment( val );
				},
				__nextHasNoMarginBottom: true,
			} ),

			el( 'p', { style: { fontSize: '11px', color: '#999', margin: '4px 0 0' } },
				'Group: ' + group
			),

			statusEl,

			versionRows.length > 0
				? el( 'div', { style: { marginTop: '16px' } },
					el( 'p', { style: { fontSize: '11px', fontWeight: '600', textTransform: 'uppercase', letterSpacing: '0.05em', color: '#757575', margin: '0 0 4px' } },
						'All versions'
					),
					versionRows
				)
				: null
		);
	}

	registerPlugin( 'wpm-template-language', { render: WpmTemplatePanel } );
} )();
