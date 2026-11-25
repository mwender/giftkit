<?php
/**
 * Builds GiftKit donation forms and keeps them in step with their settings.
 *
 * A GiftKit form is ordinary Gravity Forms fields. The ones GiftKit manages
 * carry a `giftkitRole` property, so the settings, the Stripe feeds and the
 * front end find them by role rather than by field ID or CSS class.
 *
 * @package GiftKit
 */

namespace GiftKit;

use GFAPI;
use GFCommon;
use RGCurrency;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Form_Builder {

	/**
	 * Field property that holds a field's GiftKit role.
	 */
	const ROLE = 'giftkitRole';

	/**
	 * Gift Type choice values. Feeds and scripts compare against these, so
	 * the choice labels can be reworded freely.
	 */
	const ONE_TIME = 'one-time';
	const MONTHLY  = 'monthly';

	/**
	 * Default settings for a new form.
	 *
	 * @return array
	 */
	public static function default_settings() {
		return array(
			'presets'             => '25, 50, 100, 500, 1000, 1500, 2000',
			'allowOther'          => '1',
			'offerMonthly'        => '1',
			'minimumGift'         => '5',
			'feePercent'          => '3',
			'feeNote'             => __( 'Covering the card processing fee means more of your gift goes to our mission.', 'giftkit' ),
			/* translators: %s: site name */
			'subscriptionName'    => sprintf( __( 'Monthly donation to %s', 'giftkit' ), get_bloginfo( 'name' ) ),
			'confirmationMessage' => __( 'Thank you for your gift!', 'giftkit' ),
		);
	}

	/**
	 * The thank-you message a new form starts with, using its field IDs.
	 *
	 * @param array $form The form.
	 *
	 * @return string
	 */
	public static function default_confirmation( $form ) {
		$name  = self::field( $form, 'name' );
		$email = self::field( $form, 'email' );
		$total = self::field( $form, 'total' );
		if ( ! $name || ! $email || ! $total ) {
			return self::default_settings()['confirmationMessage'];
		}

		return sprintf(
			/* translators: 1: donor's first name, 2: gift total, 3: donor's email (all merge tags) */
			__( 'Thank you, %1$s! Your gift of %2$s was received. A receipt is on its way to %3$s.', 'giftkit' ),
			'{' . $name->label . ' (First):' . $name->id . '.3}',
			'{' . $total->label . ':' . $total->id . '}',
			'{' . $email->label . ':' . $email->id . '}'
		);
	}

	/**
	 * Creates a donation form and its Stripe feeds.
	 *
	 * @param string $title Form title.
	 *
	 * @return int|\WP_Error The new form ID.
	 */
	public static function create( $title ) {
		$settings = self::default_settings();

		$form_id = GFAPI::add_form( self::form_definition( $title, $settings ) );
		if ( is_wp_error( $form_id ) ) {
			return $form_id;
		}

		$form                            = GFAPI::get_form( $form_id );
		$settings['confirmationMessage'] = self::default_confirmation( $form );
		$form                            = self::apply_settings( $form, $settings );

		$feeds                      = self::create_feeds( $form, $settings );
		$form['giftkit']            = array_merge( $settings, $feeds, array( 'giftkitVersion' => GIFTKIT_VERSION ) );
		$form['giftkit']['presets'] = self::presets_to_string( self::parse_presets( $settings['presets'] ) );

		$result = GFAPI::update_form( $form );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $form_id;
	}

	/**
	 * Whether a form was made by GiftKit.
	 *
	 * @param array|null $form The form.
	 *
	 * @return bool
	 */
	public static function is_giftkit_form( $form ) {
		return is_array( $form ) && is_array( rgar( $form, 'giftkit' ) );
	}

	/**
	 * Finds the field that has a role.
	 *
	 * @param array  $form The form.
	 * @param string $role The role.
	 *
	 * @return \GF_Field|null
	 */
	public static function field( $form, $role ) {
		foreach ( ( rgar( $form, 'fields' ) ?: array() ) as $field ) {
			if ( rgobj( $field, self::ROLE ) === $role ) {
				return $field;
			}
		}

		return null;
	}

	/**
	 * Parses the presets setting into amounts.
	 *
	 * @param string $presets Amounts separated by commas, spaces or new lines.
	 *
	 * @return float[]
	 */
	public static function parse_presets( $presets ) {
		$amounts = array();
		foreach ( preg_split( '/[\s,]+/', (string) $presets, -1, PREG_SPLIT_NO_EMPTY ) as $part ) {
			$number = GFCommon::to_number( $part );
			if ( is_numeric( $number ) && $number > 0 && ! in_array( (float) $number, $amounts, true ) ) {
				$amounts[] = (float) $number;
			}
		}

		return $amounts;
	}

	/**
	 * Formats amounts back into the presets setting.
	 *
	 * @param float[] $amounts Amounts.
	 *
	 * @return string
	 */
	public static function presets_to_string( $amounts ) {
		return implode( ', ', array_map( array( __CLASS__, 'plain_number' ), $amounts ) );
	}

	/**
	 * Applies settings to a form's fields and confirmation.
	 *
	 * @param array $form     The form.
	 * @param array $settings GiftKit settings.
	 *
	 * @return array The updated form.
	 */
	public static function apply_settings( $form, $settings ) {
		$settings = wp_parse_args( $settings, self::default_settings() );
		$amount   = self::field( $form, 'amount' );
		$minimum  = max( 0, (float) GFCommon::to_number( $settings['minimumGift'] ) );
		$percent  = max( 0, (float) GFCommon::to_number( $settings['feePercent'] ) );
		$monthly  = '1' === (string) $settings['offerMonthly'];

		// Preset tiles.
		$presets = self::field( $form, 'presets' );
		if ( $presets ) {
			$choices = array();
			foreach ( self::parse_presets( $settings['presets'] ) as $value ) {
				$choices[] = array(
					'text'       => self::money( $value ),
					'value'      => self::plain_number( $value ),
					'isSelected' => false,
				);
			}
			if ( '1' === (string) $settings['allowOther'] ) {
				$choices[] = array(
					'text'       => __( 'Other', 'giftkit' ),
					'value'      => 'other',
					'isSelected' => false,
				);
			}
			$presets->choices = $choices;
		}

		// Minimum gift, enforced by Gravity Forms on the server.
		if ( $amount ) {
			$amount->rangeMin     = $minimum > 0 ? self::plain_number( $minimum ) : '';
			$amount->errorMessage = $minimum > 0
				/* translators: %s: minimum amount */
				? sprintf( __( 'Please enter a gift of at least %s.', 'giftkit' ), self::money( $minimum ) )
				: __( 'Please enter a gift amount.', 'giftkit' );
		}

		// Gift Type: hidden from donors when monthly giving is off.
		$frequency = self::field( $form, 'frequency' );
		if ( $frequency ) {
			$frequency->visibility = $monthly ? 'visible' : 'administrative';
		}

		// Processing fee: hidden entirely at 0%.
		$fee_choice = self::field( $form, 'fee' );
		$fee_line   = self::field( $form, 'fee-product' );
		if ( $fee_choice ) {
			/* translators: %s: fee percentage, e.g. 3% */
			$label                  = sprintf( __( 'Add %s to your gift to cover our transaction costs?', 'giftkit' ), self::plain_number( $percent ) . '%' );
			$fee_choice->choices    = array( array( 'text' => $label, 'value' => 'yes', 'isSelected' => false ) );
			$fee_choice->inputs     = array( array( 'id' => $fee_choice->id . '.1', 'label' => $label, 'name' => '' ) );
			$fee_choice->description = $settings['feeNote'];
			$fee_choice->visibility = $percent > 0 ? 'visible' : 'administrative';
		}
		if ( $fee_line && $amount ) {
			$fee_line->calculationFormula = sprintf( '{%s:%d} * %s', $amount->label, $amount->id, self::plain_number( $percent / 100 ) );
		}

		// Thank-you message, kept in the default confirmation.
		foreach ( ( rgar( $form, 'confirmations' ) ?: array() ) as $id => $confirmation ) {
			if ( rgar( $confirmation, 'isDefault' ) && 'message' === rgar( $confirmation, 'type' ) ) {
				$form['confirmations'][ $id ]['message'] = $settings['confirmationMessage'];
			}
		}

		return $form;
	}

	/**
	 * The default confirmation, if it shows a message.
	 *
	 * @param array $form The form.
	 *
	 * @return array|null
	 */
	public static function message_confirmation( $form ) {
		foreach ( ( rgar( $form, 'confirmations' ) ?: array() ) as $confirmation ) {
			if ( rgar( $confirmation, 'isDefault' ) ) {
				return 'message' === rgar( $confirmation, 'type' ) ? $confirmation : null;
			}
		}

		return null;
	}

	/**
	 * Creates the one-time and monthly Stripe feeds.
	 *
	 * @param array $form     The form.
	 * @param array $settings GiftKit settings.
	 *
	 * @return array Feed IDs, to store with the settings.
	 */
	public static function create_feeds( $form, $settings ) {
		if ( ! function_exists( 'gf_stripe' ) ) {
			return array();
		}

		$ids = array();
		foreach ( self::feed_definitions( $form, $settings ) as $key => $feed ) {
			$feed_id = GFAPI::add_feed( $form['id'], $feed['meta'], 'gravityformsstripe' );
			if ( is_wp_error( $feed_id ) ) {
				continue;
			}
			if ( ! $feed['active'] ) {
				GFAPI::update_feed_property( $feed_id, 'is_active', 0 );
			}
			$ids[ $key ] = $feed_id;
		}

		return $ids;
	}

	/**
	 * Brings the feeds in line with the settings.
	 *
	 * Only what the settings control is changed: the subscription name, the
	 * Gift Type conditions, and whether the monthly feed is active.
	 *
	 * @param array $form     The form.
	 * @param array $settings GiftKit settings, including the feed IDs.
	 */
	public static function sync_feeds( $form, $settings ) {
		if ( ! function_exists( 'gf_stripe' ) ) {
			return;
		}

		foreach ( self::feed_definitions( $form, $settings ) as $key => $definition ) {
			$feed_id = (int) rgar( $settings, $key );
			$feed    = $feed_id ? GFAPI::get_feed( $feed_id ) : null;
			if ( ! $feed || is_wp_error( $feed ) || (int) $feed['form_id'] !== (int) $form['id'] ) {
				continue;
			}

			$meta = $feed['meta'];
			foreach ( array( 'subscription_name', 'feed_condition_conditional_logic', 'feed_condition_conditional_logic_object' ) as $setting ) {
				if ( isset( $definition['meta'][ $setting ] ) ) {
					$meta[ $setting ] = $definition['meta'][ $setting ];
				}
			}

			// GFAPI::update_feed() only finds active feeds, so activate the
			// feed before writing its meta, then set the state it should have.
			GFAPI::update_feed_property( $feed_id, 'is_active', 1 );
			$result = GFAPI::update_feed( $feed_id, $meta, $form['id'] );
			GFAPI::update_feed_property( $feed_id, 'is_active', $definition['active'] ? 1 : 0 );

			if ( is_wp_error( $result ) && function_exists( 'gf_stripe' ) ) {
				gf_stripe()->log_error( __METHOD__ . "(): GiftKit couldn't update feed {$feed_id}: " . $result->get_error_message() );
			}
		}
	}

	/**
	 * The two feeds, keyed by the setting that stores each one's ID.
	 *
	 * @param array $form     The form.
	 * @param array $settings GiftKit settings.
	 *
	 * @return array
	 */
	private static function feed_definitions( $form, $settings ) {
		$settings  = wp_parse_args( $settings, self::default_settings() );
		$monthly   = '1' === (string) $settings['offerMonthly'];
		$frequency = self::field( $form, 'frequency' );
		$name      = self::field( $form, 'name' );
		$email     = self::field( $form, 'email' );
		$address   = self::field( $form, 'address' );
		$note      = self::field( $form, 'note' );
		$phone     = self::field( $form, 'phone' );
		$business  = self::field( $form, 'business-name' );

		$condition = function ( $value ) use ( $frequency, $monthly ) {
			// With monthly giving off, the Gift Type field isn't shown, so
			// the one-time feed must run unconditionally.
			if ( ! $frequency || ! $monthly ) {
				return array( 'feed_condition_conditional_logic' => '0' );
			}

			return array(
				'feed_condition_conditional_logic'        => '1',
				'feed_condition_conditional_logic_object' => array(
					'conditionalLogic' => array(
						'actionType' => 'show',
						'logicType'  => 'all',
						'rules'      => array(
							array(
								'fieldId'  => (string) $frequency->id,
								'operator' => 'is',
								'value'    => $value,
							),
						),
					),
				),
			);
		};

		$shared = array(
			'customerInformation_email' => $email ? (string) $email->id : '',
			'customerInformation_name'  => $name ? (string) $name->id : '',
			'metaData'                  => array(),
		);
		if ( $address ) {
			$parts = array( 'line1' => 1, 'line2' => 2, 'city' => 3, 'state' => 4, 'zip' => 5, 'country' => 6 );
			foreach ( $parts as $part => $input ) {
				$shared[ 'billingInformation_address_' . $part ] = $address->id . '.' . $input;
			}
		}
		foreach ( array( 'note' => $note, 'phone' => $phone, 'business_name' => $business ) as $key => $field ) {
			if ( $field ) {
				// The Stripe add-on names each metadata entry by custom_key.
				$shared['metaData'][] = array( 'key' => 'gf_custom', 'custom_key' => $key, 'value' => (string) $field->id );
			}
		}

		return array(
			'oneTimeFeedId' => array(
				'active' => true,
				'meta'   => array_merge(
					$shared,
					$condition( self::ONE_TIME ),
					array(
						'feedName'        => __( 'GiftKit: one-time gifts', 'giftkit' ),
						'transactionType' => 'product',
						'paymentAmount'   => 'form_total',
						'receipt_field'   => $email ? (string) $email->id : '',
					)
				),
			),
			'monthlyFeedId' => array(
				'active' => $monthly,
				'meta'   => array_merge(
					$shared,
					$condition( self::MONTHLY ),
					array(
						'feedName'            => __( 'GiftKit: monthly gifts', 'giftkit' ),
						'transactionType'     => 'subscription',
						'recurringAmount'     => 'form_total',
						'subscription_name'   => $settings['subscriptionName'],
						'billingCycle_length' => '1',
						'billingCycle_unit'   => 'month',
						'setupFee_enabled'    => '0',
						'trial_enabled'       => '0',
					)
				),
			),
		);
	}

	/**
	 * The form GiftKit creates, before settings are applied.
	 *
	 * Field IDs are assigned in order; nothing else depends on them.
	 *
	 * @param string $title    Form title.
	 * @param array  $settings GiftKit settings.
	 *
	 * @return array
	 */
	private static function form_definition( $title, $settings ) {
		$fields = array(
			// Page 1: the gift.
			array(
				'type'           => 'radio',
				'role'           => 'frequency',
				'label'          => __( 'Gift Type', 'giftkit' ),
				'isRequired'     => true,
				'labelPlacement' => 'hidden_label',
				'choices'        => array(
					array( 'text' => __( 'One Time Gift', 'giftkit' ), 'value' => self::ONE_TIME, 'isSelected' => true ),
					array( 'text' => __( 'Monthly Gift', 'giftkit' ), 'value' => self::MONTHLY, 'isSelected' => false ),
				),
			),
			array(
				'type'           => 'radio',
				'role'           => 'presets',
				'label'          => __( 'Choose an Amount', 'giftkit' ),
				'labelPlacement' => 'hidden_label',
				'choices'        => array(),
			),
			array(
				'type'        => 'textarea',
				'role'        => 'note',
				'label'       => __( 'Note', 'giftkit' ),
				'placeholder' => __( 'Notes, In Honor Of, In Memory Of, etc.', 'giftkit' ),
				'size'        => 'small',
			),
			array(
				'type'         => 'number',
				'role'         => 'amount',
				'label'        => __( 'Gift Amount', 'giftkit' ),
				'isRequired'   => true,
				'numberFormat' => 'decimal_dot',
				'placeholder'  => '0',
			),
			// Page 2: the donor.
			array( 'type' => 'page', 'role' => 'page-2' ),
			array(
				'type'  => 'section',
				'role'  => 'contact-heading',
				'label' => __( 'Contact Information', 'giftkit' ),
			),
			array(
				'type'       => 'name',
				'role'       => 'name',
				'label'      => __( 'Name', 'giftkit' ),
				'isRequired' => true,
				'nameFormat' => 'advanced',
				'inputs'     => array(
					array( 'id' => '.2', 'label' => __( 'Prefix', 'giftkit' ), 'isHidden' => true ),
					array( 'id' => '.3', 'label' => __( 'First Name', 'giftkit' ), 'customLabel' => __( 'First Name', 'giftkit' ) ),
					array( 'id' => '.4', 'label' => __( 'Middle', 'giftkit' ), 'isHidden' => true ),
					array( 'id' => '.6', 'label' => __( 'Last Name', 'giftkit' ), 'customLabel' => __( 'Last Name', 'giftkit' ) ),
					array( 'id' => '.8', 'label' => __( 'Suffix', 'giftkit' ), 'isHidden' => true ),
				),
			),
			array(
				'type'                 => 'email',
				'role'                 => 'email',
				'label'                => __( 'Email Address', 'giftkit' ),
				'isRequired'           => true,
				'layoutGridColumnSpan' => 6,
			),
			array(
				'type'                 => 'phone',
				'role'                 => 'phone',
				'label'                => __( 'Phone Number', 'giftkit' ),
				'isRequired'           => true,
				'phoneFormat'          => 'standard',
				'layoutGridColumnSpan' => 6,
			),
			array(
				'type'           => 'checkbox',
				'role'           => 'business',
				'label'          => __( 'Donate as a Business', 'giftkit' ),
				'labelPlacement' => 'hidden_label',
				'choices'        => array( array( 'text' => __( 'Donate as a Business', 'giftkit' ), 'value' => 'yes', 'isSelected' => false ) ),
				'inputs'         => array( array( 'id' => '.1', 'label' => __( 'Donate as a Business', 'giftkit' ) ) ),
			),
			array(
				'type'       => 'text',
				'role'       => 'business-name',
				'label'      => __( 'Business Name', 'giftkit' ),
				'isRequired' => true,
				'showIf'     => array( 'business', 'yes' ),
			),
			array(
				'type'    => 'checkbox',
				'role'    => 'updates',
				'label'   => __( "It's OK to send me updates via:", 'giftkit' ),
				'choices' => array(
					array( 'text' => __( 'Email', 'giftkit' ), 'value' => 'email', 'isSelected' => false ),
					array( 'text' => __( 'Text Messaging', 'giftkit' ), 'value' => 'text', 'isSelected' => false ),
				),
				'inputs'  => array(
					array( 'id' => '.1', 'label' => __( 'Email', 'giftkit' ) ),
					array( 'id' => '.2', 'label' => __( 'Text Messaging', 'giftkit' ) ),
				),
			),
			array(
				'type'  => 'section',
				'role'  => 'billing-heading',
				'label' => __( 'Billing Information', 'giftkit' ),
			),
			array(
				'type'           => 'address',
				'role'           => 'address',
				'label'          => __( 'Address', 'giftkit' ),
				'labelPlacement' => 'hidden_label',
				'isRequired'     => true,
				'addressType'    => 'international',
				'defaultCountry' => self::default_country(),
				'inputs'         => array(
					array( 'id' => '.1', 'label' => __( 'Address', 'giftkit' ), 'customLabel' => __( 'Address', 'giftkit' ) ),
					array( 'id' => '.2', 'label' => __( 'Address Line 2', 'giftkit' ) ),
					array( 'id' => '.3', 'label' => __( 'City', 'giftkit' ) ),
					array( 'id' => '.4', 'label' => __( 'State / Province / Region', 'giftkit' ), 'customLabel' => __( 'State', 'giftkit' ) ),
					array( 'id' => '.5', 'label' => __( 'ZIP / Postal Code', 'giftkit' ), 'customLabel' => __( 'Zip Code', 'giftkit' ) ),
					array( 'id' => '.6', 'label' => __( 'Country', 'giftkit' ) ),
				),
			),
			array(
				'type'    => 'html',
				'role'    => 'summary',
				'label'   => __( 'Gift Summary', 'giftkit' ),
				'content' => sprintf(
					/* translators: 1: amount, 2: "each month" or nothing, 3: edit link */
					'<p>' . __( 'You are giving %1$s%2$s. %3$s', 'giftkit' ) . '</p>',
					'<strong class="giftkit-summary-amount"></strong>',
					'<span class="giftkit-summary-frequency"></span>',
					'<a href="#" class="giftkit-edit-amount">' . __( '(edit amount)', 'giftkit' ) . '</a>'
				),
			),
			// Page 3: payment.
			array( 'type' => 'page', 'role' => 'page-3' ),
			array(
				'type'                         => 'stripe_creditcard',
				'role'                         => 'card',
				'label'                        => __( 'Payment Details', 'giftkit' ),
				'isRequired'                   => true,
				'enableMultiplePaymentMethods' => true,
				'inputs'                       => array(
					array( 'id' => '.1', 'label' => __( 'Card Details', 'giftkit' ) ),
					array( 'id' => '.4', 'label' => __( 'Card Type', 'giftkit' ) ),
					array( 'id' => '.5', 'label' => __( 'Cardholder Name', 'giftkit' ), 'isHidden' => true ),
				),
			),
			array(
				'type'           => 'checkbox',
				'role'           => 'fee',
				'label'          => __( 'Cover Transaction Costs', 'giftkit' ),
				'labelPlacement' => 'hidden_label',
				'choices'        => array(),
				'inputs'         => array(),
			),
			array(
				'type'                => 'product',
				'inputType'           => 'calculation',
				'role'                => 'gift-product',
				'label'               => __( 'Gift', 'giftkit' ),
				'enableCalculation'   => true,
				'calculationFormula'  => '',
				'calculationRounding' => '2',
				'disableQuantity'     => true,
				'inputs'              => array(
					array( 'id' => '.1', 'label' => __( 'Name', 'giftkit' ) ),
					array( 'id' => '.2', 'label' => __( 'Price', 'giftkit' ) ),
					array( 'id' => '.3', 'label' => __( 'Quantity', 'giftkit' ) ),
				),
			),
			array(
				'type'                => 'product',
				'inputType'           => 'calculation',
				'role'                => 'fee-product',
				'label'               => __( 'Transaction Fee', 'giftkit' ),
				'enableCalculation'   => true,
				'calculationFormula'  => '',
				'calculationRounding' => '2',
				'disableQuantity'     => true,
				'showIf'              => array( 'fee', 'yes' ),
				'inputs'              => array(
					array( 'id' => '.1', 'label' => __( 'Name', 'giftkit' ) ),
					array( 'id' => '.2', 'label' => __( 'Price', 'giftkit' ) ),
					array( 'id' => '.3', 'label' => __( 'Quantity', 'giftkit' ) ),
				),
			),
			array(
				'type'           => 'total',
				'role'           => 'total',
				'label'          => __( 'Total', 'giftkit' ),
				'labelPlacement' => 'hidden_label',
			),
		);

		// Assign IDs and page numbers, then resolve the references that need
		// them: input IDs, "show if" rules, and the gift formula.
		$ids  = array();
		$page = 1;
		foreach ( $fields as $index => &$field ) {
			if ( 'page' === $field['type'] ) {
				$page++;
				$field['nextButton']     = array( 'type' => 'text', 'text' => __( 'Continue', 'giftkit' ) );
				$field['previousButton'] = array( 'type' => 'text', 'text' => __( 'Back', 'giftkit' ) );
			}

			$id                    = $index + 1;
			$ids[ $field['role'] ] = $id;
			$field['id']           = $id;
			$field['formId']       = 0;
			$field['pageNumber']   = $page;
			$field[ self::ROLE ]   = $field['role'];
			unset( $field['role'] );

			foreach ( isset( $field['inputs'] ) ? $field['inputs'] : array() as $i => $input ) {
				$field['inputs'][ $i ] = array_merge( array( 'name' => '' ), $input, array( 'id' => $id . $input['id'] ) );
			}
		}
		unset( $field );

		foreach ( $fields as &$field ) {
			if ( isset( $field['showIf'] ) ) {
				list( $role, $value )      = $field['showIf'];
				$field['conditionalLogic'] = array(
					'actionType' => 'show',
					'logicType'  => 'all',
					'rules'      => array( array( 'fieldId' => (string) $ids[ $role ], 'operator' => 'is', 'value' => $value ) ),
				);
				unset( $field['showIf'] );
			}
			// The card field's "Link email field" setting, as the form editor
			// sets it. (Stripe Add-On 7.0 reads it only in the editor.)
			if ( 'card' === $field[ self::ROLE ] ) {
				$field['linkEmailFieldId'] = (string) $ids['email'];
			}
			if ( 'gift-product' === $field[ self::ROLE ] ) {
				$field['calculationFormula'] = sprintf( '{%s:%d}', __( 'Gift Amount', 'giftkit' ), $ids['amount'] );
			}
		}
		unset( $field );

		return array(
			'title'                => $title,
			'description'          => '',
			'labelPlacement'       => 'top_label',
			'descriptionPlacement' => 'below',
			'button'               => array( 'type' => 'text', 'text' => __( 'Donate', 'giftkit' ) ),
			'lastPageButton'       => array( 'type' => 'text', 'text' => __( 'Back', 'giftkit' ) ),
			'enableHoneypot'       => true,
			'pagination'           => array(
				'type'                                => 'steps',
				'pages'                               => array( __( 'Amount', 'giftkit' ), __( 'Contact', 'giftkit' ), __( 'Payment', 'giftkit' ) ),
				'display_progressbar_on_confirmation' => false,
			),
			'fields'               => $fields,
			'confirmations'        => array(
				'giftkit-confirmation' => array(
					'id'        => 'giftkit-confirmation',
					'name'      => __( 'Default Confirmation', 'giftkit' ),
					'isDefault' => true,
					'type'      => 'message',
					'message'   => $settings['confirmationMessage'],
				),
			),
			'notifications'        => array(
				'giftkit-admin' => array(
					'id'       => 'giftkit-admin',
					'name'     => __( 'Admin Notification', 'giftkit' ),
					'isActive' => true,
					'event'    => 'form_submission',
					'to'       => '{admin_email}',
					'toType'   => 'email',
					/* translators: merge tags for the total and donor name */
					'subject'  => sprintf( __( 'New gift: %1$s from %2$s', 'giftkit' ), '{Total:' . $ids['total'] . '}', '{Name:' . $ids['name'] . '}' ),
					'message'  => '{all_fields}',
					'from'     => '{admin_email}',
					'replyTo'  => '{Email Address:' . $ids['email'] . '}',
				),
			),
		);
	}

	/**
	 * The address field's default country, guessed from the site's currency.
	 *
	 * Only currencies used by a single country are mapped; others (EUR, for
	 * one) leave the choice to the donor. Site owners can change the default
	 * on the Address field.
	 *
	 * @return string A country name as Gravity Forms lists it, or ''.
	 */
	public static function default_country() {
		$countries = array(
			'AUD' => 'Australia',
			'BRL' => 'Brazil',
			'CAD' => 'Canada',
			'CHF' => 'Switzerland',
			'CZK' => 'Czechia',
			'DKK' => 'Denmark',
			'GBP' => 'United Kingdom',
			'HKD' => 'Hong Kong',
			'HUF' => 'Hungary',
			'ILS' => 'Israel',
			'JPY' => 'Japan',
			'MXN' => 'Mexico',
			'NOK' => 'Norway',
			'NZD' => 'New Zealand',
			'PHP' => 'Philippines',
			'PLN' => 'Poland',
			'SEK' => 'Sweden',
			'SGD' => 'Singapore',
			'THB' => 'Thailand',
			'TWD' => 'Taiwan',
			'USD' => 'United States',
			'ZAR' => 'South Africa',
		);

		/**
		 * Filters the default country for new GiftKit forms' address field.
		 *
		 * @param string $country  Country name, or '' for none.
		 * @param string $currency The site's Gravity Forms currency code.
		 */
		return apply_filters( 'giftkit_default_country', rgar( $countries, GFCommon::get_currency() ), GFCommon::get_currency() );
	}

	/**
	 * Formats an amount in the site's currency, without decimals when whole.
	 *
	 * @param float $amount Amount.
	 *
	 * @return string
	 */
	public static function money( $amount ) {
		$currency = RGCurrency::get_currency( GFCommon::get_currency() );
		if ( floor( $amount ) == $amount ) {
			$currency['decimals'] = 0;
		}

		return html_entity_decode( ( new RGCurrency( $currency ) )->to_money( $amount ) );
	}

	/**
	 * A number without trailing zeros, e.g. 3, 2.5, 0.03.
	 *
	 * @param float $number Number.
	 *
	 * @return string
	 */
	public static function plain_number( $number ) {
		return rtrim( rtrim( number_format( (float) $number, 4, '.', '' ), '0' ), '.' );
	}
}
