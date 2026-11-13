/**
 * "Language & translations" box: language change without reload, unlink,
 * and a keyboard-accessible search to link existing content.
 */
/* global wp */
( function () {
	'use strict';

	var box = document.querySelector( '.wpm-mb' );
	if ( ! box || ! window.wp || ! wp.apiFetch ) {
		return;
	}
	var __ = wp.i18n.__;
	var sprintf = wp.i18n.sprintf;
	var statuses = ( window.wpmMetaBox && window.wpmMetaBox.statuses ) || {};
	var postId = parseInt( box.dataset.post, 10 ) || 0;
	var postType = box.dataset.postType;
	var live = box.querySelector( '.wpm-mb__live' );
	var langSelect = box.querySelector( '#wpm-page-lang' );

	// Confirmation after "Create": the block editor does not show classic admin notices.
	if ( window.location.search.indexOf( 'wpm_notice=created' ) !== -1 && wp.data && wp.data.dispatch( 'core/notices' ) ) {
		wp.data.dispatch( 'core/notices' ).createSuccessNotice(
			__( 'Translation draft created from the original content. Translate it, then publish.', 'pixellang-multilingual' ),
			{ id: 'wpm-translation-created' }
		);
	}

	// ---------------------------------------------------------------------
	// Outdated translation: notice in the block editor and "Mark as up to date".
	// ---------------------------------------------------------------------
	var notices = wp.data && wp.data.dispatch( 'core/notices' );

	function markSynced() {
		var button = box.querySelector( '.wpm-mb__mark-synced' );
		if ( button ) {
			button.disabled = true;
		}
		return wp.apiFetch( {
			path: '/wpm/v1/mark-synced',
			method: 'POST',
			data: { post_id: postId },
		} ).then( function () {
			var block = box.querySelector( '.wpm-mb__sync' );
			if ( block ) {
				var done = document.createElement( 'p' );
				done.className = 'wpm-mb__sync is-current';
				done.textContent = ( window.wpmMetaBox && window.wpmMetaBox.upToDate ) || '';
				block.replaceWith( done );
			}
			box.dataset.sync = 'current';
			if ( notices ) {
				notices.removeNotice( 'wpm-outdated' );
			}
			announce( __( 'Translation marked as up to date.', 'pixellang-multilingual' ) );
		} ).catch( function ( error ) {
			if ( button ) {
				button.disabled = false;
			}
			var message = ( error && error.message ) || __( 'The translation could not be marked as up to date.', 'pixellang-multilingual' );
			showError( box.querySelector( '.wpm-mb__sync' ) || box, message );
			announce( message );
		} );
	}

	if ( box.dataset.sync === 'outdated' && notices && document.body.classList.contains( 'block-editor-page' ) ) {
		var actions = [];
		if ( box.dataset.compare ) {
			actions.push( { label: __( 'See what changed', 'pixellang-multilingual' ), url: box.dataset.compare } );
		}
		actions.push( { label: __( 'Mark as up to date', 'pixellang-multilingual' ), onClick: markSynced } );
		notices.createWarningNotice(
			__( 'The original changed after this translation was last updated.', 'pixellang-multilingual' ),
			{ id: 'wpm-outdated', isDismissible: true, actions: actions }
		);
	}

	function announce( message ) {
		live.textContent = '';
		window.setTimeout( function () {
			live.textContent = message;
		}, 50 );
	}

	// The row of the content's own language is hidden and not submitted.
	function syncLanguage() {
		var lang = langSelect.value;
		box.querySelectorAll( '.wpm-mb__row' ).forEach( function ( row ) {
			var own = row.dataset.lang === lang;
			row.hidden = own;
			row.querySelector( '.wpm-mb__value' ).disabled = own;
		} );
	}
	var savedLang = langSelect.value;
	langSelect.addEventListener( 'change', function () {
		// The new language is held by a linked translation: this content
		// will leave the group, so ask first.
		var target = box.querySelector( '.wpm-mb__row[data-lang="' + langSelect.value + '"]' );
		if ( target && target.querySelector( '.wpm-mb__value' ).value ) {
			var ok = window.confirm( sprintf(
				/* translators: %s: language name. */
				__( 'This content already has a %s translation. Changing its language will unlink it from all its translations. Continue?', 'pixellang-multilingual' ),
				target.dataset.label
			) );
			if ( ! ok ) {
				langSelect.value = savedLang;
				return;
			}
		}
		syncLanguage();
		var lang = langSelect.value;
		var label = langSelect.options[ langSelect.selectedIndex ].text;
		langSelect.disabled = true;
		wp.apiFetch( {
			path: '/wpm/v1/post-language',
			method: 'POST',
			data: { post_id: postId, lang: lang },
		} ).then( function ( response ) {
			langSelect.disabled = false;
			var group = ( response && response.group ) || {};
			box.querySelectorAll( '.wpm-mb__row' ).forEach( function ( row ) {
				var item = group[ row.dataset.lang ];
				if ( row.dataset.lang !== lang ) {
					renderRow( row, item && item.id !== postId ? item : null );
				}
			} );
			savedLang = lang;
			var warning = box.querySelector( '.wpm-mb__hint--warning' );
			if ( warning ) {
				warning.remove();
			}
			/* translators: placeholders: language or content name. */
			announce( sprintf( __( 'Language saved: %s.', 'pixellang-multilingual' ), label ) );
		} ).catch( function ( error ) {
			// Back to the stored language so the box matches the server.
			langSelect.disabled = false;
			langSelect.value = savedLang;
			syncLanguage();
			var message = ( error && error.message ) || __( 'The language could not be saved.', 'pixellang-multilingual' );
			showError( box.querySelector( '.wpm-mb__field' ), message );
			announce( message );
			langSelect.focus();
		} );
	} );

	function renderRow( row, item ) {
		var linked = row.querySelector( '.wpm-mb__linked' );
		var empty = row.querySelector( '.wpm-mb__empty' );
		var value = row.querySelector( '.wpm-mb__value' );

		if ( item ) {
			value.value = item.id;
			var title = document.createElement( item.edit ? 'a' : 'span' );
			title.className = 'wpm-mb__title';
			title.textContent = item.title || __( '(no title)', 'pixellang-multilingual' );
			if ( item.edit ) {
				title.href = item.edit;
			}
			linked.querySelector( '.wpm-mb__title' ).replaceWith( title );
			var oldBadge = linked.querySelector( '.wpm-badge' );
			if ( oldBadge ) {
				oldBadge.remove();
			}
			var badge = document.createElement( 'span' );
			badge.className = 'wpm-badge wpm-badge--' + ( item.status === 'publish' ? 'success' : 'neutral' );
			badge.textContent = statuses[ item.status ] || item.status;
			title.after( badge );
			var oldOutdated = linked.querySelector( '.wpm-mb__outdated' );
			if ( oldOutdated ) {
				oldOutdated.remove();
			}
			if ( item.outdated ) {
				var outdated = document.createElement( 'span' );
				outdated.className = 'wpm-badge wpm-badge--warning wpm-mb__outdated';
				outdated.textContent = __( 'Needs update', 'pixellang-multilingual' );
				badge.after( outdated );
			}
		} else {
			value.value = '';
		}
		linked.hidden = ! item;
		empty.hidden = !! item;
		row.classList.toggle( 'is-linked', !! item );
	}

	// Saves one link immediately (classic meta boxes do not make the block
	// editor "dirty", so waiting for Update would lose the change).
	function saveLink( row, target, label ) {
		row.classList.add( 'is-busy' );
		return wp.apiFetch( {
			path: '/wpm/v1/link',
			method: 'POST',
			data: {
				post_id: postId,
				source_lang: langSelect.value,
				lang: row.dataset.lang,
				target: target,
			},
		} ).then( function ( response ) {
			row.classList.remove( 'is-busy' );
			var group = ( response && response.group ) || {};
			box.querySelectorAll( '.wpm-mb__row' ).forEach( function ( other ) {
				var item = group[ other.dataset.lang ];
				if ( other.dataset.lang !== langSelect.value ) {
					renderRow( other, item && item.id !== postId ? item : null );
				}
			} );
			if ( target ) {
				/* translators: placeholders: language or content name, language or content name. */
				announce( sprintf( __( '%1$s linked as the %2$s translation.', 'pixellang-multilingual' ), label, row.dataset.label ) );
				row.querySelector( '.wpm-mb__unlink' ).focus();
			} else {
				/* translators: placeholders: language or content name. */
				announce( sprintf( __( '%s translation unlinked.', 'pixellang-multilingual' ), row.dataset.label ) );
				row.querySelector( '.wpm-combobox__input' ).focus();
			}
		} ).catch( function ( error ) {
			row.classList.remove( 'is-busy' );
			var message = ( error && error.message ) || __( 'The link could not be saved.', 'pixellang-multilingual' );
			showError( row, message );
			announce( message );
		} );
	}

	function showError( row, message ) {
		var note = row.querySelector( '.wpm-mb__error' );
		if ( ! note ) {
			note = document.createElement( 'p' );
			note.className = 'wpm-mb__error';
			row.appendChild( note );
		}
		note.textContent = message;
		window.setTimeout( function () {
			note.remove();
		}, 6000 );
	}

	box.addEventListener( 'click', function ( event ) {
		if ( event.target.closest( '.wpm-mb__mark-synced' ) ) {
			markSynced();
			return;
		}
		var unlink = event.target.closest( '.wpm-mb__unlink' );
		if ( unlink ) {
			saveLink( unlink.closest( '.wpm-mb__row' ), 0, '' );
		}
	} );

	// ---------------------------------------------------------------------
	// Combobox (WAI-ARIA 1.2 pattern, list autocomplete).
	// ---------------------------------------------------------------------
	box.querySelectorAll( '.wpm-combobox' ).forEach( function ( combo ) {
		var input = combo.querySelector( '.wpm-combobox__input' );
		var list = combo.querySelector( '.wpm-combobox__list' );
		var row = combo.closest( '.wpm-mb__row' );
		var items = [];
		var active = -1;
		var timer = null;
		var request = 0;

		function close() {
			list.hidden = true;
			input.setAttribute( 'aria-expanded', 'false' );
			input.removeAttribute( 'aria-activedescendant' );
			active = -1;
		}

		function highlight( index ) {
			var options = list.querySelectorAll( '[role="option"]' );
			options.forEach( function ( option, i ) {
				option.setAttribute( 'aria-selected', i === index ? 'true' : 'false' );
			} );
			active = index;
			if ( options[ index ] ) {
				input.setAttribute( 'aria-activedescendant', options[ index ].id );
				options[ index ].scrollIntoView( { block: 'nearest' } );
			}
		}

		function render( message ) {
			list.innerHTML = '';
			if ( message ) {
				var li = document.createElement( 'li' );
				li.className = 'wpm-combobox__message';
				li.textContent = message;
				list.appendChild( li );
			}
			items.forEach( function ( item, i ) {
				var option = document.createElement( 'li' );
				option.id = 'wpm-opt-' + row.dataset.lang + '-' + item.id;
				option.setAttribute( 'role', 'option' );
				option.setAttribute( 'aria-selected', 'false' );
				option.className = 'wpm-combobox__option';
				var name = document.createElement( 'span' );
				name.className = 'wpm-combobox__name';
				name.textContent = item.title || __( '(no title)', 'pixellang-multilingual' );
				option.appendChild( name );
				var meta = document.createElement( 'span' );
				meta.className = 'wpm-combobox__meta';
				var details = [ statuses[ item.status ] || item.status ];
				if ( ! item.lang ) {
					details.push( __( 'no language', 'pixellang-multilingual' ) );
				}
				if ( item.linked ) {
					details.push( __( 'already linked', 'pixellang-multilingual' ) );
				}
				meta.textContent = details.join( ' · ' );
				option.appendChild( meta );
				option.addEventListener( 'mousedown', function ( event ) {
					event.preventDefault();
					choose( i );
				} );
				list.appendChild( option );
			} );
			list.hidden = false;
			input.setAttribute( 'aria-expanded', 'true' );
		}

		function choose( index ) {
			var item = items[ index ];
			if ( ! item ) {
				return;
			}
			close();
			input.value = '';
			saveLink( row, item.id, item.title );
		}

		function search() {
			var current = ++request;
			combo.classList.add( 'is-loading' );
			wp.apiFetch( {
				path: wp.url.addQueryArgs( '/wpm/v1/search', {
					post_type: postType,
					lang: row.dataset.lang,
					search: input.value.trim(),
					exclude: postId,
				} ),
			} ).then( function ( results ) {
				if ( current !== request ) {
					return;
				}
				combo.classList.remove( 'is-loading' );
				items = results || [];
				render( items.length ? '' : __( 'No matching content.', 'pixellang-multilingual' ) );
				/* translators: placeholders: number. */
				announce( sprintf( __( '%d results', 'pixellang-multilingual' ), items.length ) );
			} ).catch( function () {
				combo.classList.remove( 'is-loading' );
				items = [];
				render( __( 'Search failed. Try again.', 'pixellang-multilingual' ) );
			} );
		}

		input.addEventListener( 'input', function () {
			window.clearTimeout( timer );
			timer = window.setTimeout( search, 250 );
		} );
		input.addEventListener( 'focus', function () {
			if ( ! items.length ) {
				search();
			} else {
				render( '' );
			}
		} );
		input.addEventListener( 'blur', function () {
			window.setTimeout( close, 120 );
		} );
		input.addEventListener( 'keydown', function ( event ) {
			if ( event.key === 'ArrowDown' ) {
				event.preventDefault();
				if ( list.hidden ) {
					search();
					return;
				}
				highlight( Math.min( active + 1, items.length - 1 ) );
			} else if ( event.key === 'ArrowUp' ) {
				event.preventDefault();
				highlight( Math.max( active - 1, 0 ) );
			} else if ( event.key === 'Enter' ) {
				// Never submit the post form from this field.
				event.preventDefault();
				if ( active >= 0 ) {
					choose( active );
				}
			} else if ( event.key === 'Escape' ) {
				close();
			}
		} );
	} );
}() );
