/**
 * The landing page's sample form: the same arithmetic and wording as
 * GiftKit's own form, without the payment step.
 */
( function () {
	'use strict';

	var form = document.querySelector( '[data-demo]' );
	if ( ! form ) {
		return;
	}

	var total = form.querySelector( '[data-demo-total]' );
	var note = form.querySelector( '[data-demo-note]' );
	var unit = form.querySelector( '[data-demo-unit]' );

	function money( value ) {
		var whole = Math.round( value * 100 ) % 100 === 0;
		return '$' + value.toLocaleString( 'en-US', { minimumFractionDigits: whole ? 0 : 2, maximumFractionDigits: 2 } );
	}

	function update() {
		var amount = parseFloat( form.elements.amount.value ) || 0;
		var fee = form.elements.fee.checked ? Math.round( amount * 3 ) / 100 : 0;
		var monthly = form.elements.frequency.value === 'monthly';

		total.value = money( amount + fee );
		total.textContent = total.value;
		note.textContent = monthly ? 'Monthly gift, charged every month' : 'One-time gift';
		unit.textContent = monthly ? 'USD / month' : 'USD';
	}

	form.addEventListener( 'change', update );
	form.addEventListener( 'submit', function ( event ) {
		event.preventDefault();
	} );
	update();
} )();
