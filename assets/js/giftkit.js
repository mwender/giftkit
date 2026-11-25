/**
 * GiftKit donation form behaviour.
 *
 * Runs on forms with the `giftkit-form` class. GiftKit gives each field it
 * manages a `giftkit-<role>` class (see includes/class-addon.php):
 *
 *   .giftkit-frequency  Gift Type radio (values "one-time" / "monthly")
 *   .giftkit-presets    preset amount buttons, with an "other" choice
 *   .giftkit-amount     the gift amount
 *   .giftkit-summary    the page-2 summary line and its "edit amount" link
 *   .giftkit-total      the page-3 total
 *
 * A preset fills the amount; typing an amount selects the matching preset,
 * or Other.
 */
( function ( $ ) {
	'use strict';

	var strings = window.giftkit_strings || {};

	// Each form's latest total, from Gravity Forms, and the function that
	// refreshes its summary.
	var totals = {};
	var summaries = {};
	if ( window.gform && typeof window.gform.addFilter === 'function' ) {
		window.gform.addFilter( 'gform_product_total', function ( total, formId ) {
			totals[ formId ] = total;
			if ( summaries[ formId ] ) {
				summaries[ formId ]();
			}
			return total;
		}, 50 );
	}

	function formatAmount( value ) {
		var number = parseFloat( value );
		if ( isNaN( number ) ) {
			number = 0;
		}
		var money = typeof window.gformFormatMoney === 'function' ? window.gformFormatMoney( number, true ) : String( number );
		// Whole amounts read better without ".00".
		return number % 1 === 0 ? money.replace( /[.,]00(?=\D*$)/, '' ) : money;
	}

	function init( formId ) {
		var $form = $( '#gform_' + formId );
		if ( ! $form.hasClass( 'giftkit-form' ) ) {
			return;
		}

		var $amount = $form.find( '.giftkit-amount input' );
		var $presets = $form.find( '.giftkit-presets input[type=radio]' );
		var $frequency = $form.find( '.giftkit-frequency input[type=radio]' );
		var $total = $form.find( '.giftkit-total' );
		var code = strings.currencyCode || '';

		$form.find( '.giftkit-amount .ginput_container' )
			.attr( 'data-giftkit-symbol', strings.currencySymbol || '' )
			.attr( 'data-giftkit-currency', code );

		function isMonthly() {
			return $frequency.filter( ':checked' ).val() === 'monthly';
		}

		// GF recalculates totals on `change`; `input` keeps the summary live.
		function setAmount( value ) {
			$amount.val( value ).trigger( 'input' ).trigger( 'change' );
		}

		function syncPresetToAmount() {
			var value = parseFloat( $amount.val() );
			if ( isNaN( value ) ) {
				if ( $presets.filter( ':checked' ).val() !== 'other' ) {
					$presets.prop( 'checked', false );
				}
				return;
			}
			var $match = $presets.filter( function () {
				return this.value !== 'other' && parseFloat( this.value ) === value;
			} );
			( $match.length ? $match : $presets.filter( '[value=other]' ) ).prop( 'checked', true );
		}

		function updateSummary() {
			var monthly = isMonthly();

			// The total includes the processing fee when the donor added it.
			var total = typeof totals[ formId ] === 'number' && totals[ formId ] > 0 ? totals[ formId ] : $amount.val();
			$form.find( '.giftkit-summary-amount' ).text( formatAmount( total ) );
			$form.find( '.giftkit-summary-frequency' ).text( monthly ? ( strings.eachMonth || '' ) : '' );

			// Page 3: say above the total whether it's charged once or monthly.
			var $note = $total.find( '.giftkit-frequency-note' );
			if ( ! $note.length ) {
				$note = $( '<p class="giftkit-frequency-note"></p>' ).prependTo( $total );
			}
			$note.text( monthly ? strings.monthly : strings.oneTime );
			$total.find( '.ginput_container_total' ).attr(
				'data-giftkit-currency',
				monthly && strings.perMonth ? strings.perMonth.replace( '%s', code ) : code
			);
		}

		$presets.off( '.giftkit' ).on( 'change.giftkit', function () {
			if ( this.value === 'other' ) {
				setAmount( '' );
				$amount.trigger( 'focus' );
			} else {
				setAmount( this.value );
			}
		} );

		$amount.off( '.giftkit' ).on( 'input.giftkit', function () {
			syncPresetToAmount();
			updateSummary();
		} );

		$frequency.off( '.giftkit' ).on( 'change.giftkit', updateSummary );

		// "(edit amount)" goes back to page 1, as GF's own Back button does.
		$form.find( '.giftkit-edit-amount' ).off( '.giftkit' ).on( 'click.giftkit', function ( event ) {
			event.preventDefault();
			$( '#gform_target_page_number_' + formId ).val( '1' );
			$form.trigger( 'submit', [ true ] );
		} );

		summaries[ formId ] = updateSummary;
		syncPresetToAmount();
		updateSummary();
	}

	$( document ).on( 'gform_post_render', function ( event, formId ) {
		init( formId );
	} );
} )( jQuery );
