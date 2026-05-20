( function() {
	var registerBlockType = wp.blocks.registerBlockType;
	var el               = wp.element.createElement;

	registerBlockType( 'wp-multilingual/language-switcher', {
		edit: function() {
			return el(
				'div',
				{
					style: {
						display:      'inline-flex',
						alignItems:   'center',
						gap:          '6px',
						padding:      '6px 12px',
						background:   '#f0f6ff',
						border:       '1px dashed #2271b1',
						borderRadius: '4px',
						fontSize:     '13px',
						color:        '#2271b1'
					}
				},
				el( 'span', null, '🌐' ),
				el( 'span', null, 'Language Switcher' )
			);
		},
		save: function() {
			return null; // Server-side rendered via render.php
		}
	} );
} )();
