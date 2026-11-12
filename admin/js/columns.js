/**
 * Quick Edit: pre-select the language of the edited row.
 */
/* global inlineEditPost */
( function () {
	'use strict';
	if ( typeof inlineEditPost === 'undefined' ) {
		return;
	}
	var original = inlineEditPost.edit;
	inlineEditPost.edit = function ( id ) {
		original.apply( this, arguments );
		var postId = typeof id === 'object' ? this.getId( id ) : id;
		var source = document.querySelector( '#post-' + postId + ' .wpm-qe-lang' );
		var select = document.querySelector( '#edit-' + postId + ' select[name="wpm_quick_lang"]' );
		if ( source && select ) {
			select.value = source.textContent.trim();
		}
	};
}() );
