/**
 * GiftKit's admin pages: the "New Donation Form" page and a form's
 * Donation tab.
 */
( function () {
	'use strict';

	// Show the page title only while "Also create a page" is checked.
	var toggle = document.getElementById( 'giftkit-create-page' );
	var pageTitle = document.querySelector( '.giftkit-create__page-title' );
	if ( toggle && pageTitle ) {
		var sync = function () {
			pageTitle.hidden = ! toggle.checked;
		};
		toggle.addEventListener( 'change', sync );
		sync();
	}

	// The accent color: WordPress's color picker, which can be left empty.
	if ( window.jQuery && window.jQuery.fn.wpColorPicker ) {
		window.jQuery( 'input.giftkit-color' ).wpColorPicker();
	}

	// Copy the embed shortcode.
	document.querySelectorAll( '.giftkit-copy' ).forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			var input = button.parentNode.querySelector( 'input' );
			var label = button.textContent;
			var done = function () {
				button.textContent = button.getAttribute( 'data-copied' ) || label;
				setTimeout( function () {
					button.textContent = label;
				}, 2000 );
			};
			input.select();
			if ( navigator.clipboard && window.isSecureContext ) {
				navigator.clipboard.writeText( input.value ).then( done );
			} else {
				document.execCommand( 'copy' );
				done();
			}
		} );
	} );
} )();
