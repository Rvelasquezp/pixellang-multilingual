/**
 * String Translation screen: inline saving, deleting and the register form.
 */
/* global wp */
( function () {
	'use strict';

	var table = document.querySelector( '.wpm-strings-card' );
	var __ = wp.i18n.__;
	var sprintf = wp.i18n.sprintf;

	// Register form: show the fields of the chosen kind.
	var register = document.getElementById( 'wpm-register-string' );
	if ( register ) {
		var syncKind = function () {
			var kind = register.querySelector( 'input[name="kind"]:checked' ).value;
			register.querySelectorAll( '[data-kind]' ).forEach( function ( el ) {
				el.hidden = el.dataset.kind !== kind;
			} );
			register.querySelector( 'input[name="domain"]' ).required = kind === 'gettext';
		};
		register.addEventListener( 'change', syncKind );
		syncKind();
	}

	if ( ! table ) {
		return;
	}

	function rowStatus( row, message, isError ) {
		var status = row.querySelector( '.wpm-str__status' );
		status.textContent = message;
		status.classList.toggle( 'is-error', !! isError );
		if ( ! isError ) {
			window.setTimeout( function () {
				if ( status.textContent === message ) {
					status.textContent = '';
				}
			}, 4000 );
		}
	}

	function save( row ) {
		var id = row.dataset.string;
		var button = row.querySelector( '[data-save]' );
		var translations = {};
		row.querySelectorAll( '.wpm-tr__input' ).forEach( function ( field ) {
			var lang = field.dataset.lang;
			translations[ lang ] = translations[ lang ] || { value: '', plural: '' };
			translations[ lang ][ field.dataset.plural ? 'plural' : 'value' ] = field.value;
		} );
		button.disabled = true;
		button.classList.add( 'is-busy' );
		return wp.apiFetch( {
			path: '/wpm/v1/strings/' + id,
			method: 'POST',
			data: { translations: translations },
		} ).then( function ( response ) {
			button.disabled = false;
			button.classList.remove( 'is-busy' );
			row.querySelectorAll( '.wpm-tr' ).forEach( function ( cell ) {
				var lang = cell.querySelector( '.wpm-tr__input' ).dataset.lang;
				var done = response.translations && response.translations[ lang ];
				if ( ! cell.classList.contains( 'is-original' ) || done ) {
					cell.classList.toggle( 'is-done', !! done );
					cell.classList.toggle( 'is-missing', ! done && response.missing.indexOf( lang ) !== -1 );
				}
			} );
			row.classList.remove( 'is-dirty' );
			rowStatus( row, __( 'Saved', 'pixellang-multilingual' ) );
		} ).catch( function ( error ) {
			button.disabled = false;
			button.classList.remove( 'is-busy' );
			rowStatus( row, ( error && error.message ) || __( 'Not saved. Try again.', 'pixellang-multilingual' ), true );
		} );
	}

	table.addEventListener( 'input', function ( event ) {
		if ( event.target.classList.contains( 'wpm-tr__input' ) ) {
			event.target.closest( 'tr' ).classList.add( 'is-dirty' );
		}
	} );

	table.addEventListener( 'keydown', function ( event ) {
		// Ctrl/Cmd + Enter saves the row; Enter alone stays a new line.
		if ( event.target.classList.contains( 'wpm-tr__input' ) && event.key === 'Enter' && ( event.metaKey || event.ctrlKey ) ) {
			event.preventDefault();
			save( event.target.closest( 'tr' ) );
		}
	} );

	table.addEventListener( 'click', function ( event ) {
		var saveButton = event.target.closest( '[data-save]' );
		if ( saveButton ) {
			save( saveButton.closest( 'tr' ) );
			return;
		}
		var deleteButton = event.target.closest( '[data-delete]' );
		if ( deleteButton ) {
			var row = deleteButton.closest( 'tr' );
			var text = row.querySelector( '.wpm-str__text' ).textContent;
			/* translators: %s: original text. */
			if ( ! window.confirm( sprintf( __( 'Delete “%s” and its translations? The theme and plugin files are not affected.', 'pixellang-multilingual' ), text ) ) ) {
				return;
			}
			deleteButton.disabled = true;
			wp.apiFetch( { path: '/wpm/v1/strings/' + row.dataset.string, method: 'DELETE' } )
				.then( function () {
					row.remove();
				} )
				.catch( function ( error ) {
					deleteButton.disabled = false;
					rowStatus( row, ( error && error.message ) || __( 'Not deleted. Try again.', 'pixellang-multilingual' ), true );
				} );
		}
	} );

	// Unsaved rows: warn before leaving.
	window.addEventListener( 'beforeunload', function ( event ) {
		if ( table.querySelector( 'tr.is-dirty' ) ) {
			event.preventDefault();
			event.returnValue = '';
		}
	} );
}() );
