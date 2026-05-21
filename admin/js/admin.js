/* global jQuery, wpmData */
jQuery( function ( $ ) {

	// =========================================================================
	// TABS
	// =========================================================================
	$( '.wpm-tab' ).on( 'click', function ( e ) {
		e.preventDefault();
		var target = $( this ).attr( 'href' );
		$( '.wpm-tab' ).removeClass( 'active' );
		$( this ).addClass( 'active' );
		$( '.wpm-tab-panel' ).hide();
		$( target ).show();
	} );

	// =========================================================================
	// LANGUAGE PICKER — search filter
	// =========================================================================
	$( '#wpm-lang-search' ).on( 'input', function () {
		var q = $( this ).val().toLowerCase().trim();
		$( '.wpm-picker-item' ).each( function () {
			var search = $( this ).data( 'search' ) || '';
			$( this ).toggleClass( 'wpm-hidden', q !== '' && search.indexOf( q ) === -1 );
		} );
	} );

	// =========================================================================
	// LANGUAGE PICKER — add a language
	// =========================================================================
	$( document ).on( 'click', '.wpm-add-lang', function () {
		var $item  = $( this ).closest( '.wpm-picker-item' );
		var slug   = $item.data( 'slug' );
		var label  = $item.data( 'label' );
		var locale = $item.data( 'locale' );
		var flag   = $item.data( 'flag' );

		// Mark picker item as active.
		$item.find( '.wpm-add-lang' ).replaceWith( '<span class="wpm-picker-badge">Active</span>' );
		$item.addClass( 'wpm-picker-active' );

		// Append to active list.
		var isFirst = $( '#wpm-active-list li' ).length === 0;
		var html = buildActiveItem( slug, label, locale, flag, isFirst );
		$( '.wpm-empty-notice' ).hide();
		$( '#wpm-active-list' ).append( html );
	} );

	function buildActiveItem( slug, label, locale, flag, isDefault ) {
		return '<li class="wpm-active-item" data-slug="' + slug + '">' +
			'<span class="wpm-flag">' + flag + '</span>' +
			'<span class="wpm-lang-info">' +
				'<strong>' + escHtml( label ) + '</strong>' +
				'<span class="wpm-locale">' + escHtml( locale ) + '</span>' +
			'</span>' +
			'<label class="wpm-default-label">' +
				'<input type="radio" name="wpm_default_radio" value="' + escHtml( slug ) + '"' + ( isDefault ? ' checked' : '' ) + ' /> Default' +
			'</label>' +
			'<button type="button" class="wpm-remove-lang" data-slug="' + escHtml( slug ) + '" title="Remove">✕</button>' +
			'<input type="hidden" name="wpm_languages[' + escHtml( slug ) + '][label]"   value="' + escHtml( label )  + '" />' +
			'<input type="hidden" name="wpm_languages[' + escHtml( slug ) + '][locale]"  value="' + escHtml( locale ) + '" />' +
			'<input type="hidden" name="wpm_languages[' + escHtml( slug ) + '][default]" value="' + ( isDefault ? '1' : '0' ) + '" class="wpm-default-hidden" />' +
		'</li>';
	}

	// =========================================================================
	// ACTIVE LIST — remove a language
	// =========================================================================
	$( document ).on( 'click', '.wpm-remove-lang', function () {
		var slug  = $( this ).data( 'slug' );
		var $item = $( this ).closest( '.wpm-active-item' );
		var wasDefault = $item.find( 'input[name="wpm_default_radio"]' ).is( ':checked' );

		$item.remove();

		// Restore picker button.
		$( '.wpm-picker-item[data-slug="' + slug + '"]' )
			.removeClass( 'wpm-picker-active' )
			.find( '.wpm-picker-badge' )
			.replaceWith( '<button type="button" class="button button-small wpm-add-lang">+ Add</button>' );

		// If removed item was default, assign default to first remaining.
		if ( wasDefault ) {
			var $first = $( '#wpm-active-list li' ).first();
			if ( $first.length ) {
				$first.find( 'input[name="wpm_default_radio"]' ).prop( 'checked', true );
				$first.find( '.wpm-default-hidden' ).val( '1' );
			}
		}

		if ( $( '#wpm-active-list li' ).length === 0 ) {
			$( '.wpm-empty-notice' ).show();
		}
	} );

	// =========================================================================
	// ACTIVE LIST — default radio sync
	// =========================================================================
	$( document ).on( 'change', 'input[name="wpm_default_radio"]', function () {
		// Reset all hidden default values.
		$( '.wpm-default-hidden' ).val( '0' );
		// Set the selected one.
		$( this ).closest( '.wpm-active-item' ).find( '.wpm-default-hidden' ).val( '1' );
	} );

	// =========================================================================
	// PAGE MAP — add row
	// =========================================================================
	$( '#wpm-add-page-group' ).on( 'click', function () {
		var key     = 'group_' + Date.now();
		var slugsEl = document.getElementById( 'wpm-pagemap-slugs' );
		var slugs   = slugsEl ? slugsEl.textContent.split( ',' ) : [];
		if ( ! slugs.length || slugs[0] === '' ) return;

		var cells = '';
		for ( var i = 0; i < slugs.length; i++ ) {
			var s = slugs[ i ].trim();
			cells += '<td><input type="number" name="wpm_page_map[' + key + '][' + s + ']" value="0" class="small-text" min="0" /></td>';
		}
		cells += '<td><button type="button" class="button wpm-remove-row">✕</button></td>';
		$( '#wpm-page-map-table tbody' ).append( '<tr>' + cells + '</tr>' );
	} );

	// =========================================================================
	// PAGE MAP — remove row
	// =========================================================================
	$( document ).on( 'click', '.wpm-remove-row', function () {
		$( this ).closest( 'tr' ).remove();
	} );

	// =========================================================================
	// TEMPLATES — repair (add missing wp_theme taxonomy)
	// =========================================================================
	$( '#wpm-repair-templates' ).on( 'click', function () {
		var $btn    = $( this );
		var $status = $( '#wpm-repair-status' );
		$btn.prop( 'disabled', true ).text( '🔧 Repairing…' );
		$status.text( '' );

		wp.apiFetch( {
			path:   '/wpm/v1/template-repair',
			method: 'POST',
		} ).then( function ( res ) {
			$btn.prop( 'disabled', false ).text( '🔧 Repair Template Parts' );
			if ( res.fixed && res.fixed.length ) {
				$status.css( 'color', '#0a6e3b' ).text( '✅ Fixed: ' + res.fixed.join( ', ' ) );
			} else {
				$status.css( 'color', '#0a6e3b' ).text( '✅ All good — nothing to repair.' );
			}
		} ).catch( function () {
			$btn.prop( 'disabled', false ).text( '🔧 Repair Template Parts' );
			$status.css( 'color', '#b32d2e' ).text( '❌ Error.' );
		} );
	} );

	// =========================================================================
	// TEMPLATES — remove group via AJAX
	// =========================================================================
	$( document ).on( 'click', '.wpm-remove-template-group', function () {
		var $btn  = $( this );
		var group = $btn.data( 'group' );
		if ( ! group ) return;
		if ( ! confirm( 'Remove template group "' + group + '"? The template parts themselves will not be deleted.' ) ) return;

		$btn.prop( 'disabled', true ).text( '…' );

		wp.apiFetch( {
			path:   '/wpm/v1/template-group-remove',
			method: 'POST',
			data:   { group: group },
		} ).then( function () {
			$btn.closest( 'tr' ).fadeOut( 300, function () { $( this ).remove(); } );
		} ).catch( function () {
			$btn.prop( 'disabled', false ).text( '✕' );
			alert( 'Error removing group.' );
		} );
	} );

	// =========================================================================
	// Utility
	// =========================================================================
	function escHtml( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}
} );
