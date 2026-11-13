/**
 * PixelLang Multilingual: language suggestion banner.
 *
 * Shows the translation of the page in the first browser language that the
 * site offers, unless the page is already in that language or the visitor
 * already answered (closed the banner, followed it, or used a switcher).
 */
( function () {
	'use strict';

	var banner = document.getElementById( 'wpm-banner' );
	var source = document.getElementById( 'wpm-banner-data' );
	if ( ! banner || ! source ) {
		return;
	}
	var KEY = 'wpm_banner';
	var data;
	try {
		data = JSON.parse( source.textContent );
	} catch ( e ) {
		return;
	}

	function answered() {
		try {
			return !! window.localStorage.getItem( KEY );
		} catch ( e ) {
			return false;
		}
	}
	function remember( value ) {
		try {
			window.localStorage.setItem( KEY, value );
		} catch ( e ) {}
	}

	// A language picked with a switcher is an answer too.
	document.addEventListener( 'click', function ( event ) {
		if ( event.target.closest && event.target.closest( '.wpm-language-switcher a' ) ) {
			remember( 'switcher' );
		}
	} );
	document.addEventListener( 'change', function ( event ) {
		if ( event.target.matches && event.target.matches( '.wpm-language-switcher select' ) ) {
			remember( 'switcher' );
		}
	} );

	if ( answered() ) {
		return;
	}

	// First browser language the site offers decides; a language the site
	// has but this page lacks means there is nothing better to offer.
	var wanted = window.navigator.languages && window.navigator.languages.length
		? window.navigator.languages
		: [ window.navigator.language || '' ];
	var pick = null;
	for ( var i = 0; i < wanted.length; i++ ) {
		var code = String( wanted[ i ] ).toLowerCase().split( '-' )[ 0 ];
		if ( code === data.current ) {
			return;
		}
		if ( data.suggestions && data.suggestions[ code ] ) {
			pick = data.suggestions[ code ];
			break;
		}
		if ( data.active && data.active.indexOf( code ) !== -1 ) {
			return;
		}
	}
	if ( ! pick ) {
		return;
	}

	var cta = banner.querySelector( '.wpm-banner__cta' );
	var close = banner.querySelector( '.wpm-banner__close' );
	banner.lang = pick.hreflang;
	banner.setAttribute( 'aria-label', pick.region );
	banner.querySelector( '.wpm-banner__text' ).textContent = pick.text;
	banner.querySelector( '.wpm-banner__label' ).textContent = pick.label;
	cta.href = pick.url;
	cta.hreflang = pick.hreflang;
	close.setAttribute( 'aria-label', pick.close );
	close.title = pick.close;

	function hide() {
		banner.classList.remove( 'is-visible' );
		window.setTimeout( function () {
			banner.hidden = true;
		}, 250 );
	}

	cta.addEventListener( 'click', function () {
		remember( 'followed' );
	} );
	close.addEventListener( 'click', function () {
		remember( 'closed' );
		hide();
	} );
	banner.addEventListener( 'keydown', function ( event ) {
		if ( event.key === 'Escape' ) {
			remember( 'closed' );
			hide();
		}
	} );

	banner.hidden = false;
	// Read layout once so the transition starts from the hidden state.
	void banner.offsetWidth;
	banner.classList.add( 'is-visible' );
}() );
