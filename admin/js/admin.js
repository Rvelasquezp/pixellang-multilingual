/**
 * PixelLang Multilingual admin screens.
 */
/* global wp */
( function () {
	'use strict';

	var app = document.querySelector( '.wpm-app' );
	if ( ! app ) {
		return;
	}
	var __ = wp.i18n.__;
	var sprintf = wp.i18n.sprintf;

	// ---------------------------------------------------------------------
	// Toasts (aria-live region, auto-dismiss after 5 s).
	// ---------------------------------------------------------------------
	var region = app.querySelector( '.wpm-toast-region' );

	function toast( message, type ) {
		if ( ! region ) {
			return;
		}
		var item = document.createElement( 'div' );
		item.className = 'wpm-toast' + ( type === 'error' ? ' wpm-toast--error' : '' );
		item.setAttribute( 'role', type === 'error' ? 'alert' : 'status' );
		item.textContent = message;
		region.appendChild( item );
		window.setTimeout( function () {
			item.classList.add( 'is-leaving' );
			window.setTimeout( function () {
				item.remove();
			}, 250 );
		}, 5000 );
	}

	function escapeHtml( value ) {
		return String( value ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[ c ];
		} );
	}

	// ---------------------------------------------------------------------
	// Generic behaviors.
	// ---------------------------------------------------------------------

	// Warn before leaving a form with unsaved changes.
	app.querySelectorAll( '[data-wpm-dirty-guard]' ).forEach( function ( form ) {
		var dirty = false;
		var mark = function () {
			dirty = true;
		};
		form.addEventListener( 'input', mark );
		form.addEventListener( 'change', mark );
		form.addEventListener( 'wpm:changed', mark );
		form.addEventListener( 'submit', function () {
			dirty = false;
		} );
		window.addEventListener( 'beforeunload', function ( event ) {
			if ( dirty ) {
				event.preventDefault();
				event.returnValue = '';
			}
		} );
	} );

	// Confirmation on sensitive forms.
	app.querySelectorAll( 'form[data-wpm-confirm]' ).forEach( function ( form ) {
		form.addEventListener( 'submit', function ( event ) {
			if ( ! window.confirm( form.dataset.wpmConfirm ) ) {
				event.preventDefault();
			}
		} );
	} );

	// Busy state on submit buttons.
	app.addEventListener( 'submit', function ( event ) {
		if ( event.defaultPrevented ) {
			return;
		}
		var button = event.target.querySelector( 'button[type="submit"]' );
		if ( button ) {
			button.classList.add( 'is-busy' );
			button.setAttribute( 'aria-busy', 'true' );
		}
	} );

	// Filters that apply on change.
	app.querySelectorAll( '[data-wpm-autosubmit]' ).forEach( function ( select ) {
		select.addEventListener( 'change', function () {
			select.form.submit();
		} );
	} );

	// ---------------------------------------------------------------------
	// Languages screen.
	// ---------------------------------------------------------------------
	var list = document.getElementById( 'wpm-active-list' );
	var form = document.getElementById( 'wpm-languages-form' );

	if ( list && form ) {
		var template = document.getElementById( 'wpm-lang-row-template' );
		var catalog = document.getElementById( 'wpm-catalog' );
		var search = document.getElementById( 'wpm-lang-search' );
		var emptyActive = document.getElementById( 'wpm-active-empty' );
		var emptyCatalog = document.getElementById( 'wpm-catalog-empty' );
		var counter = form.querySelector( '[data-wpm-count]' );

		var changed = function () {
			form.dispatchEvent( new CustomEvent( 'wpm:changed' ) );
		};

		var refresh = function () {
			var rows = list.querySelectorAll( '.wpm-lang-row' );
			emptyActive.hidden = rows.length > 0;
			counter.textContent = rows.length;
			rows.forEach( function ( row, index ) {
				row.querySelector( '[data-move="up"]' ).disabled = index === 0;
				row.querySelector( '[data-move="down"]' ).disabled = index === rows.length - 1;
			} );
			// There must always be a default language.
			if ( rows.length && ! list.querySelector( 'input[name="default_language"]:checked' ) ) {
				rows[ 0 ].querySelector( 'input[name="default_language"]' ).checked = true;
				/* translators: placeholders: language or content name. */
				toast( sprintf( __( '%s is now the default language.', 'pixellang-multilingual' ), rows[ 0 ].querySelector( 'input[type="text"]' ).value ) );
			}
			if ( catalog ) {
				catalog.querySelectorAll( '.wpm-catalog__item' ).forEach( function ( item ) {
					item.toggleAttribute( 'data-active', !! list.querySelector( '.wpm-lang-row[data-slug="' + item.dataset.slug + '"]' ) );
				} );
			}
		};

		var addLanguage = function ( slug, label, locale ) {
			if ( list.querySelector( '.wpm-lang-row[data-slug="' + slug + '"]' ) ) {
				/* translators: placeholders: language or content name. */
				toast( sprintf( __( '%s is already active.', 'pixellang-multilingual' ), label ) );
				return;
			}
			var html = template.innerHTML
				.split( '__slug__' ).join( escapeHtml( slug ) )
				.split( '__SLUG__' ).join( escapeHtml( slug.toUpperCase() ) )
				.split( '__label__' ).join( escapeHtml( label ) )
				.split( '__locale__' ).join( escapeHtml( locale ) );
			var holder = document.createElement( 'ul' );
			holder.innerHTML = html.trim();
			var row = holder.firstElementChild;
			row.classList.add( 'is-new' );
			list.appendChild( row );
			refresh();
			changed();
			/* translators: placeholders: language or content name. */
			toast( sprintf( __( '%s added. Save to apply.', 'pixellang-multilingual' ), label ) );
			row.querySelector( 'input[type="text"]' ).focus();
		};

		list.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( 'button' );
			if ( ! button ) {
				return;
			}
			var row = button.closest( '.wpm-lang-row' );
			var name = row.querySelector( 'input[type="text"]' ).value || row.dataset.slug;

			if ( button.hasAttribute( 'data-remove' ) ) {
				var count = parseInt( row.dataset.count, 10 ) || 0;
				/* translators: placeholders: language or content name, number. */
				if ( count && ! window.confirm( sprintf( __( '%1$s has %2$d items. They keep their language and links, but stop showing as a language of the site until you add it again. Remove it?', 'pixellang-multilingual' ), name, count ) ) ) {
					return;
				}
				var next = row.nextElementSibling || row.previousElementSibling;
				row.remove();
				refresh();
				changed();
				/* translators: placeholders: language or content name. */
				toast( sprintf( __( '%s removed. Save to apply.', 'pixellang-multilingual' ), name ) );
				if ( next ) {
					next.querySelector( '[data-remove]' ).focus();
				} else if ( search ) {
					search.focus();
				}
				return;
			}

			var direction = button.dataset.move;
			if ( direction === 'up' && row.previousElementSibling ) {
				list.insertBefore( row, row.previousElementSibling );
			} else if ( direction === 'down' && row.nextElementSibling ) {
				list.insertBefore( row.nextElementSibling, row );
			} else {
				return;
			}
			refresh();
			changed();
			var again = row.querySelector( '[data-move="' + direction + '"]' );
			( again.disabled ? row.querySelector( '[data-move="' + ( direction === 'up' ? 'down' : 'up' ) + '"]' ) : again ).focus();
		} );

		// Drag and drop with the grip (mouse); buttons cover keyboard users.
		var dragged = null;
		list.addEventListener( 'pointerdown', function ( event ) {
			var grip = event.target.closest( '.wpm-lang-row__grip' );
			if ( grip ) {
				grip.closest( '.wpm-lang-row' ).setAttribute( 'draggable', 'true' );
			}
		} );
		list.addEventListener( 'dragstart', function ( event ) {
			dragged = event.target.closest( '.wpm-lang-row' );
			if ( dragged ) {
				dragged.classList.add( 'is-dragging' );
				event.dataTransfer.effectAllowed = 'move';
			}
		} );
		list.addEventListener( 'dragover', function ( event ) {
			if ( ! dragged ) {
				return;
			}
			event.preventDefault();
			var over = event.target.closest( '.wpm-lang-row' );
			if ( over && over !== dragged ) {
				var box = over.getBoundingClientRect();
				list.insertBefore( dragged, event.clientY > box.top + box.height / 2 ? over.nextElementSibling : over );
			}
		} );
		list.addEventListener( 'dragend', function () {
			if ( dragged ) {
				dragged.classList.remove( 'is-dragging' );
				dragged.removeAttribute( 'draggable' );
				dragged = null;
				refresh();
				changed();
			}
		} );

		if ( catalog ) {
			catalog.addEventListener( 'click', function ( event ) {
				var add = event.target.closest( '[data-add]' );
				if ( add ) {
					var item = add.closest( '.wpm-catalog__item' );
					addLanguage( item.dataset.slug, item.dataset.label, item.dataset.locale );
				}
			} );
		}

		if ( search ) {
			search.addEventListener( 'input', function () {
				var query = search.value.toLowerCase().trim();
				var visible = 0;
				catalog.querySelectorAll( '.wpm-catalog__item' ).forEach( function ( item ) {
					var match = ! query || item.dataset.search.indexOf( query ) !== -1;
					item.hidden = ! match;
					visible += match ? 1 : 0;
				} );
				emptyCatalog.hidden = visible > 0;
			} );
		}

		var custom = document.getElementById( 'wpm-custom-lang' );
		if ( custom ) {
			custom.querySelector( '[data-add-custom]' ).addEventListener( 'click', function () {
				var slug = custom.querySelector( '[data-custom="slug"]' ).value.trim().toLowerCase();
				var label = custom.querySelector( '[data-custom="label"]' ).value.trim();
				var locale = custom.querySelector( '[data-custom="locale"]' ).value.trim();
				var error = custom.querySelector( '[data-custom-error]' );
				var message = '';
				if ( ! /^[a-z]{2,3}(-[a-z0-9]{2,8})?$/.test( slug ) ) {
					message = __( 'The code must be 2 or 3 lowercase letters, optionally followed by a dash and a region (pt-br).', 'pixellang-multilingual' );
				} else if ( ! label ) {
					message = __( 'Give the language a name.', 'pixellang-multilingual' );
				} else if ( locale && ! /^[a-z]{2,3}(_[A-Z]{2})?(_[a-z0-9]+)?$/.test( locale ) ) {
					message = __( 'The locale looks like fr_CA or pt_BR.', 'pixellang-multilingual' );
				}
				error.textContent = message;
				error.hidden = ! message;
				if ( message ) {
					return;
				}
				addLanguage( slug, label, locale );
				custom.querySelectorAll( 'input' ).forEach( function ( input ) {
					input.value = '';
				} );
			} );
		}

		refresh();
	}

	// ---------------------------------------------------------------------
	// Settings > Forms: form groups.
	// ---------------------------------------------------------------------
	var groups = document.getElementById( 'wpm-form-groups' );
	if ( groups ) {
		var groupTemplate = document.getElementById( 'wpm-form-group-template' );
		var groupsEmpty = document.getElementById( 'wpm-form-groups-empty' );
		var tbody = groups.querySelector( 'tbody' );
		var nextIndex = tbody.querySelectorAll( 'tr' ).length + 1000;

		var syncProvider = function ( row ) {
			var provider = row.querySelector( '[data-provider]' ).value;
			row.querySelectorAll( '[data-for-provider]' ).forEach( function ( select ) {
				var active = select.dataset.forProvider === provider;
				select.hidden = ! active;
				select.disabled = ! active;
			} );
		};
		var syncEmpty = function () {
			groupsEmpty.hidden = tbody.querySelectorAll( 'tr' ).length > 0;
		};

		document.getElementById( 'wpm-add-form-group' ).addEventListener( 'click', function () {
			var holder = document.createElement( 'tbody' );
			holder.innerHTML = groupTemplate.innerHTML.split( '__index__' ).join( String( nextIndex++ ) ).trim();
			var row = holder.firstElementChild;
			tbody.appendChild( row );
			syncProvider( row );
			syncEmpty();
			row.querySelector( 'select' ).focus();
			groups.closest( 'form' ).dispatchEvent( new CustomEvent( 'wpm:changed' ) );
		} );
		groups.addEventListener( 'change', function ( event ) {
			if ( event.target.matches( '[data-provider]' ) ) {
				syncProvider( event.target.closest( 'tr' ) );
			}
		} );
		groups.addEventListener( 'click', function ( event ) {
			var remove = event.target.closest( '[data-remove-row]' );
			if ( remove ) {
				remove.closest( 'tr' ).remove();
				syncEmpty();
				groups.closest( 'form' ).dispatchEvent( new CustomEvent( 'wpm:changed' ) );
				toast( __( 'Form group removed. Save to apply.', 'pixellang-multilingual' ) );
			}
		} );
	}

	// ---------------------------------------------------------------------
	// Template parts (REST).
	// ---------------------------------------------------------------------
	app.addEventListener( 'click', function ( event ) {
		var remove = event.target.closest( '[data-remove-template-group]' );
		if ( remove ) {
			var group = remove.dataset.removeTemplateGroup;
			/* translators: placeholders: language or content name. */
			if ( ! window.confirm( sprintf( __( 'Stop translating “%s”? The template parts themselves are kept.', 'pixellang-multilingual' ), group ) ) ) {
				return;
			}
			remove.disabled = true;
			wp.apiFetch( { path: '/wpm/v1/template-group-remove', method: 'POST', data: { group: group } } )
				.then( function () {
					remove.closest( 'tr' ).remove();
					/* translators: placeholders: language or content name. */
					toast( sprintf( __( '“%s” is no longer translated.', 'pixellang-multilingual' ), group ) );
				} )
				.catch( function ( error ) {
					remove.disabled = false;
					toast( ( error && error.message ) || __( 'The change could not be saved.', 'pixellang-multilingual' ), 'error' );
				} );
		}

		var repair = event.target.closest( '#wpm-repair-templates' );
		if ( repair ) {
			if ( ! window.confirm( repair.dataset.wpmConfirm ) ) {
				return;
			}
			repair.classList.add( 'is-busy' );
			repair.disabled = true;
			wp.apiFetch( { path: '/wpm/v1/template-repair', method: 'POST' } )
				.then( function ( response ) {
					repair.classList.remove( 'is-busy' );
					repair.disabled = false;
					var fixed = ( response && response.fixed ) || [];
					var message = __( 'Nothing to repair: every template part has a theme.', 'pixellang-multilingual' );
					if ( fixed.length ) {
						/* translators: %s: comma-separated template part slugs. */
						message = sprintf( __( 'Repaired: %s', 'pixellang-multilingual' ), fixed.join( ', ' ) );
					}
					toast( message );
				} )
				.catch( function ( error ) {
					repair.classList.remove( 'is-busy' );
					repair.disabled = false;
					toast( ( error && error.message ) || __( 'The repair failed.', 'pixellang-multilingual' ), 'error' );
				} );
		}
	} );
}() );
