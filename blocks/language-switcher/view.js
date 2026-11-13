/**
 * Language Switcher on the front end.
 *
 * - Select design: go to the chosen language.
 * - Dropdown design (disclosure pattern): the button toggles the list of
 *   links; Escape closes and returns focus to the button; Arrow keys,
 *   Home and End move between links; a click outside or leaving the
 *   switcher with Tab closes it. The panel opens upward when there is no
 *   room below. Without this script, the list opens on focus (CSS).
 */
( function () {
	'use strict';

	document.addEventListener( 'change', function ( event ) {
		var select = event.target;
		if ( select.matches && select.matches( '.wpm-language-switcher select[data-wpm-switch]' ) && select.value ) {
			window.location.assign( select.value );
		}
	} );

	function links( dd ) {
		return Array.prototype.slice.call( dd.querySelectorAll( '.wpm-dd__menu a' ) );
	}

	function close( dd, focusButton ) {
		if ( ! dd.classList.contains( 'is-open' ) ) {
			return;
		}
		dd.classList.remove( 'is-open' );
		dd.querySelector( '.wpm-dd__toggle' ).setAttribute( 'aria-expanded', 'false' );
		if ( focusButton ) {
			dd.querySelector( '.wpm-dd__toggle' ).focus();
		}
	}

	function open( dd, focusFirst ) {
		document.querySelectorAll( '.wpm-dd.is-open' ).forEach( function ( other ) {
			if ( other !== dd ) {
				close( other, false );
			}
		} );
		var toggle = dd.querySelector( '.wpm-dd__toggle' );
		var menu = dd.querySelector( '.wpm-dd__menu' );
		dd.classList.add( 'is-open' );
		toggle.setAttribute( 'aria-expanded', 'true' );
		// Open upward when the panel would overflow the bottom of the screen.
		if ( ! dd.closest( '.wpm-floating' ) ) {
			dd.classList.remove( 'is-up' );
			var rect = toggle.getBoundingClientRect();
			var below = window.innerHeight - rect.bottom;
			if ( below < menu.offsetHeight + 16 && rect.top > below ) {
				dd.classList.add( 'is-up' );
			}
		}
		if ( focusFirst ) {
			var first = links( dd )[ 0 ];
			if ( first ) {
				first.focus();
			}
		}
	}

	function init( dd ) {
		if ( dd.classList.contains( 'is-ready' ) ) {
			return;
		}
		dd.classList.add( 'is-ready' );
		var toggle = dd.querySelector( '.wpm-dd__toggle' );

		toggle.addEventListener( 'click', function () {
			if ( dd.classList.contains( 'is-open' ) ) {
				close( dd, false );
			} else {
				open( dd, false );
			}
		} );

		dd.addEventListener( 'keydown', function ( event ) {
			var items = links( dd );
			var index = items.indexOf( document.activeElement );
			if ( event.key === 'Escape' && dd.classList.contains( 'is-open' ) ) {
				event.preventDefault();
				close( dd, true );
			} else if ( event.key === 'ArrowDown' ) {
				event.preventDefault();
				if ( ! dd.classList.contains( 'is-open' ) ) {
					open( dd, true );
				} else if ( items.length ) {
					items[ ( index + 1 ) % items.length ].focus();
				}
			} else if ( event.key === 'ArrowUp' && dd.classList.contains( 'is-open' ) && items.length ) {
				event.preventDefault();
				items[ index <= 0 ? items.length - 1 : index - 1 ].focus();
			} else if ( ( event.key === 'Home' || event.key === 'End' ) && index !== -1 ) {
				event.preventDefault();
				items[ event.key === 'Home' ? 0 : items.length - 1 ].focus();
			}
		} );

		dd.addEventListener( 'focusout', function ( event ) {
			if ( event.relatedTarget && ! dd.contains( event.relatedTarget ) ) {
				close( dd, false );
			}
		} );
	}

	document.addEventListener( 'click', function ( event ) {
		document.querySelectorAll( '.wpm-dd.is-open' ).forEach( function ( dd ) {
			if ( ! dd.contains( event.target ) ) {
				close( dd, false );
			}
		} );
	} );

	function initAll() {
		document.querySelectorAll( '[data-wpm-dropdown]' ).forEach( init );
	}
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initAll );
	} else {
		initAll();
	}
}() );
