/**
 * Dropdown style: go to the selected language.
 */
( function () {
	'use strict';
	document.addEventListener( 'change', function ( event ) {
		var select = event.target;
		if ( select.matches && select.matches( '.wpm-language-switcher select[data-wpm-switch]' ) && select.value ) {
			window.location.assign( select.value );
		}
	} );
}() );
