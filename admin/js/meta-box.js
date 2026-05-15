/* global jQuery */
jQuery( function ( $ ) {

	// When the page-language selector changes, reload the page
	// so the translation dropdowns reflect the new language context.
	$( '#wpm-page-lang' ).on( 'change', function () {
		var msg = 'Changing the language will reload the page. Unsaved changes will be lost. Continue?';
		if ( window.confirm( msg ) ) {
			$( '#wpm-main-form, #post' ).first().find( '[name="wpm_page_lang"]' ).val( $( this ).val() );
			// Add a flag so PHP knows to just re-render, not full save.
			var form = $( this ).closest( 'form' );
			$( '<input type="hidden" name="wpm_lang_preview" value="1" />' ).appendTo( form );
			form.submit();
		} else {
			// Revert select to original value.
			$( this ).val( $( this ).data( 'original' ) || $( this ).find( 'option:first' ).val() );
		}
	} ).each( function () {
		$( this ).data( 'original', $( this ).val() );
	} );

	// Unlink a translation (revert linked block back to a dropdown).
	$( document ).on( 'click', '.wpm-mb-unlink', function () {
		var lang    = $( this ).data( 'lang' );
		var $row    = $( this ).closest( '.wpm-mb-row' );
		var $linked = $row.find( '.wpm-mb-linked' );
		var $hidden = $row.find( 'input.wpm-trans-input[data-lang="' + lang + '"]' );

		// Clear hidden input and replace UI with empty select.
		$hidden.remove();
		$linked.replaceWith(
			'<select name="wpm_translations[' + esc( lang ) + ']" class="wpm-mb-select wpm-trans-select" data-lang="' + esc( lang ) + '">' +
				'<option value="">— not set —</option>' +
			'</select>'
		);
	} );

	function esc( str ) {
		return String( str ).replace( /[&<>"]/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ c ];
		} );
	}
} );
