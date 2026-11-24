<?php
/**
 * The GiftKit add-on: the Donation settings tab, the "New Donation Form"
 * entry points, and the front-end assets.
 *
 * @package GiftKit
 */

namespace GiftKit;

use GFAddOn;
use GFAPI;
use GFCommon;
use GFFormsModel;
use RGCurrency;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Addon extends GFAddOn {

	protected $_version                  = GIFTKIT_VERSION;
	protected $_min_gravityforms_version = '2.9';
	protected $_slug                     = 'giftkit';
	protected $_full_path                = GIFTKIT_FILE;
	protected $_title                    = 'GiftKit for Gravity Forms';
	protected $_short_title              = 'GiftKit';

	protected $_capabilities_form_settings = 'gravityforms_edit_forms';

	/**
	 * @var Addon|null
	 */
	private static $_instance = null;

	/**
	 * Returns the add-on instance.
	 *
	 * @return Addon
	 */
	public static function get_instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Sets the plugin path from the actual folder, so the add-on works
	 * whatever the folder is called (e.g. a localdev- checkout).
	 */
	public function __construct() {
		$this->_path = plugin_basename( GIFTKIT_FILE );
		parent::__construct();
	}

	/**
	 * GiftKit's payments run through the Stripe add-on.
	 *
	 * @return array
	 */
	public function minimum_requirements() {
		return array(
			'add-ons' => array(
				'gravityformsstripe' => array( 'name' => 'Gravity Forms Stripe Add-On' ),
			),
		);
	}

	/**
	 * Front-end hooks.
	 */
	public function init() {
		parent::init();
		add_filter( 'gform_pre_render', array( $this, 'add_form_class' ) );
		add_filter( 'gform_field_css_class', array( $this, 'add_field_class' ), 10, 3 );
	}

	/**
	 * Admin hooks.
	 */
	public function init_admin() {
		parent::init_admin();
		add_filter( 'gform_addon_navigation', array( $this, 'add_menu_item' ) );
		add_action( 'admin_post_' . Admin_Page::SLUG, array( Admin_Page::class, 'handle_create' ) );
		add_action( 'admin_footer', array( $this, 'print_forms_list_button' ) );
	}

	/*
	 * Front end
	 */

	/**
	 * Marks GiftKit forms with the `giftkit-form` class, which scopes the styles.
	 *
	 * @param array $form The form.
	 *
	 * @return array
	 */
	public function add_form_class( $form ) {
		if ( Form_Builder::is_giftkit_form( $form ) ) {
			$form['cssClass'] = trim( rgar( $form, 'cssClass' ) . ' giftkit-form' );
		}

		return $form;
	}

	/**
	 * Gives each GiftKit field a `giftkit-<role>` class for the styles and script.
	 *
	 * @param string    $classes Field classes.
	 * @param \GF_Field $field   The field.
	 * @param array     $form    The form.
	 *
	 * @return string
	 */
	public function add_field_class( $classes, $field, $form ) {
		$role = rgobj( $field, Form_Builder::ROLE );
		if ( $role && Form_Builder::is_giftkit_form( $form ) ) {
			$classes .= ' giftkit-' . sanitize_html_class( $role );
		}

		return $classes;
	}

	/**
	 * Enqueue condition: the form is a GiftKit form.
	 *
	 * @param array $form    The form.
	 * @param bool  $is_ajax Whether the form uses AJAX.
	 *
	 * @return bool
	 */
	public function form_needs_assets( $form, $is_ajax ) {
		return Form_Builder::is_giftkit_form( $form );
	}

	/**
	 * @return array
	 */
	public function scripts() {
		$scripts = array(
			array(
				'handle'    => 'giftkit',
				'src'       => $this->get_base_url() . '/assets/js/giftkit.js',
				'version'   => $this->_version,
				'deps'      => array( 'jquery', 'gform_gravityforms' ),
				'in_footer' => true,
				'strings'   => $this->script_strings(),
				'enqueue'   => array( array( $this, 'form_needs_assets' ) ),
			),
			array(
				'handle'    => 'giftkit-admin',
				'src'       => $this->get_base_url() . '/assets/js/admin.js',
				'version'   => $this->_version,
				'in_footer' => true,
				'enqueue'   => array( array( 'query' => 'page=' . Admin_Page::SLUG ) ),
			),
		);

		return array_merge( parent::scripts(), $scripts );
	}

	/**
	 * @return array
	 */
	public function styles() {
		$styles = array(
			array(
				'handle'  => 'giftkit',
				'src'     => $this->get_base_url() . '/assets/css/giftkit.css',
				'version' => $this->_version,
				'enqueue' => array( array( $this, 'form_needs_assets' ) ),
			),
			array(
				'handle'  => 'giftkit-admin',
				'src'     => $this->get_base_url() . '/assets/css/admin.css',
				'version' => $this->_version,
				'enqueue' => array( array( 'query' => 'page=' . Admin_Page::SLUG ) ),
			),
		);

		return array_merge( parent::styles(), $styles );
	}

	/**
	 * Text and currency details for the script.
	 *
	 * @return array
	 */
	private function script_strings() {
		$code     = GFCommon::get_currency();
		$currency = RGCurrency::get_currency( $code );
		$symbol   = rgar( $currency, 'symbol_left' ) ? $currency['symbol_left'] : rgar( $currency, 'symbol_right' );

		return array(
			'currencyCode'   => $code,
			'currencySymbol' => html_entity_decode( (string) $symbol ),
			'oneTime'        => __( 'One-time gift', 'giftkit' ),
			'monthly'        => __( 'Monthly gift, charged every month', 'giftkit' ),
			'eachMonth'      => __( ' each month', 'giftkit' ),
			/* translators: %s: currency code, e.g. USD */
			'perMonth'       => __( '%s / month', 'giftkit' ),
		);
	}

	/*
	 * Creating a donation form
	 */

	/**
	 * Adds "New Donation Form" to the Forms menu.
	 *
	 * @param array $menus Add-on menu items.
	 *
	 * @return array
	 */
	public function add_menu_item( $menus ) {
		$menus[] = array(
			'name'       => Admin_Page::SLUG,
			'label'      => __( 'New Donation Form', 'giftkit' ),
			'callback'   => array( Admin_Page::class, 'render' ),
			'permission' => 'gravityforms_create_form',
		);

		return $menus;
	}

	/**
	 * Puts a "New Donation Form" button beside "Add New" on the forms list.
	 *
	 * Gravity Forms has no hook for that spot, so a short script adds it. If
	 * the markup ever changes, the button just doesn't appear; the Forms menu
	 * item still works.
	 */
	public function print_forms_list_button() {
		if ( 'gf_edit_forms' !== rgget( 'page' ) || rgget( 'id' ) || ! GFCommon::current_user_can_any( 'gravityforms_create_form' ) ) {
			return;
		}

		$script = sprintf(
			'(function () {
				var addNew = document.querySelector( ".form-list-head .gform-add-new-form" );
				if ( ! addNew ) { return; }
				var link = document.createElement( "a" );
				link.href = %s;
				link.className = "button primary add-new-h2 giftkit-new-form";
				link.style.marginLeft = "8px";
				link.textContent = %s;
				addNew.insertAdjacentElement( "afterend", link );
			})();',
			wp_json_encode( Admin_Page::url() ),
			wp_json_encode( __( 'New Donation Form', 'giftkit' ) )
		);

		wp_print_inline_script_tag( $script );
	}

	/**
	 * URL of a form's Donation settings tab.
	 *
	 * @param int   $form_id Form ID.
	 * @param array $args    Extra query arguments.
	 *
	 * @return string
	 */
	public static function settings_url( $form_id, $args = array() ) {
		return add_query_arg(
			array_merge(
				array(
					'page'    => 'gf_edit_forms',
					'view'    => 'settings',
					'subview' => 'giftkit',
					'id'      => absint( $form_id ),
				),
				$args
			),
			admin_url( 'admin.php' )
		);
	}

	/*
	 * The Donation settings tab
	 */

	/**
	 * Shows the tab on GiftKit forms only, and calls it "Donation".
	 *
	 * @param array $tabs    Settings tabs.
	 * @param int   $form_id Form ID.
	 *
	 * @return array
	 */
	public function add_form_settings_menu( $tabs, $form_id ) {
		if ( ! Form_Builder::is_giftkit_form( GFFormsModel::get_form_meta( $form_id ) ) ) {
			return $tabs;
		}

		$tabs                                 = parent::add_form_settings_menu( $tabs, $form_id );
		$tabs[ array_key_last( $tabs ) ]['label'] = __( 'Donation', 'giftkit' );

		return $tabs;
	}

	/**
	 * @return string
	 */
	public function get_menu_icon() {
		return 'dashicons-heart';
	}

	/**
	 * Current settings, with the thank-you message read from the confirmation.
	 *
	 * @param array $form The form.
	 *
	 * @return array
	 */
	public function get_form_settings( $form ) {
		$settings = parent::get_form_settings( $form );
		$settings = wp_parse_args( is_array( $settings ) ? $settings : array(), Form_Builder::default_settings() );

		$confirmation = Form_Builder::message_confirmation( $form );
		if ( $confirmation ) {
			$settings['confirmationMessage'] = rgar( $confirmation, 'message' );
		}

		return $settings;
	}

	/**
	 * Saves the settings, then applies them to the form and its feeds.
	 *
	 * @param array $form     The form.
	 * @param array $settings Submitted settings.
	 *
	 * @return bool
	 */
	public function save_form_settings( $form, $settings ) {
		if ( isset( $settings['presets'] ) ) {
			$settings['presets'] = Form_Builder::presets_to_string( Form_Builder::parse_presets( $settings['presets'] ) );
		}

		if ( ! parent::save_form_settings( $form, $settings ) ) {
			return false;
		}

		$form     = GFAPI::get_form( $form['id'] );
		$settings = $form['giftkit'];
		$form     = Form_Builder::apply_settings( $form, $settings );

		$result = GFAPI::update_form( $form );
		Form_Builder::sync_feeds( $form, $settings );

		return ! is_wp_error( $result );
	}

	/**
	 * The fields on the Donation tab.
	 *
	 * @param array $form The form.
	 *
	 * @return array
	 */
	public function form_settings_fields( $form ) {
		if ( ! Form_Builder::is_giftkit_form( $form ) ) {
			return array(
				array(
					'title'       => esc_html__( 'Donation', 'giftkit' ),
					'description' => esc_html__( 'This form wasn\'t created by GiftKit, so it has no donation settings.', 'giftkit' ),
					'fields'      => array(),
				),
			);
		}

		$gift = array(
			'title'  => esc_html__( 'Donation', 'giftkit' ),
			'fields' => array(
				array(
					'name'                => 'presets',
					'label'               => esc_html__( 'Preset amounts', 'giftkit' ),
					'type'                => 'text',
					'class'               => 'large',
					'required'            => true,
					'description'         => esc_html__( 'Separate amounts with commas. They appear as buttons in this order.', 'giftkit' ),
					'validation_callback' => array( $this, 'validate_presets' ),
				),
				array(
					'name'  => 'allowOther',
					'label' => esc_html__( 'Let donors enter another amount', 'giftkit' ),
					'type'  => 'toggle',
				),
				array(
					'name'                => 'minimumGift',
					'label'               => esc_html__( 'Minimum gift', 'giftkit' ),
					'type'                => 'text',
					'input_type'          => 'number',
					'class'               => 'small',
					'description'         => esc_html__( 'Gifts below this are refused. A minimum also discourages bots that test stolen cards with tiny amounts.', 'giftkit' ),
					'validation_callback' => array( $this, 'validate_non_negative' ),
				),
				array(
					'name'  => 'offerMonthly',
					'label' => esc_html__( 'Offer monthly gifts', 'giftkit' ),
					'type'  => 'toggle',
				),
				array(
					'name'        => 'subscriptionName',
					'label'       => esc_html__( 'Monthly gift name', 'giftkit' ),
					'type'        => 'text',
					'class'       => 'large',
					'description' => esc_html__( 'Shown to donors at checkout and in your Stripe dashboard.', 'giftkit' ),
					'dependency'  => array( 'field' => 'offerMonthly', 'values' => array( '1' ) ),
				),
			),
		);

		$fee = array(
			'title'  => esc_html__( 'Processing fee', 'giftkit' ),
			'fields' => array(
				array(
					'name'                => 'feePercent',
					'label'               => esc_html__( 'Fee percentage', 'giftkit' ),
					'type'                => 'text',
					'input_type'          => 'number',
					'class'               => 'small',
					'append'              => '%',
					'description'         => esc_html__( 'Donors can choose to add this to their gift. Set it to 0 to hide the option.', 'giftkit' ),
					'validation_callback' => array( $this, 'validate_non_negative' ),
				),
				array(
					'name'        => 'feeNote',
					'label'       => esc_html__( 'Note below the fee option', 'giftkit' ),
					'type'        => 'textarea',
					'class'       => 'medium',
					'description' => esc_html__( 'A short line explaining why covering the fee helps.', 'giftkit' ),
				),
			),
		);

		if ( Form_Builder::message_confirmation( $form ) ) {
			$thanks_field = array(
				'name'        => 'confirmationMessage',
				'label'       => esc_html__( 'Thank-you message', 'giftkit' ),
				'type'        => 'textarea',
				'class'       => 'large merge-tag-support mt-position-right mt-hide_all_fields',
				'description' => esc_html__( 'Shown after a gift is made. This is the form\'s default confirmation, so it also appears under Confirmations.', 'giftkit' ),
			);
		} else {
			$thanks_field = array(
				'name'  => 'confirmationNotice',
				'label' => esc_html__( 'Thank-you message', 'giftkit' ),
				'type'  => 'html',
				'html'  => sprintf(
					/* translators: %s: link to the Confirmations settings */
					esc_html__( 'The default confirmation sends donors to a page or URL instead of showing a message. Change it under %s.', 'giftkit' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=gf_edit_forms&view=settings&subview=confirmation&id=' . absint( $form['id'] ) ) ) . '">' . esc_html__( 'Confirmations', 'giftkit' ) . '</a>'
				),
			);
		}

		$thanks = array(
			'title'  => esc_html__( 'After the gift', 'giftkit' ),
			'fields' => array( $thanks_field ),
		);

		if ( rgget( 'giftkit_created' ) ) {
			$gift['description'] = sprintf(
				/* translators: %s: shortcode */
				esc_html__( 'Your donation form is ready. Adjust the settings below, then add it to a page with %s.', 'giftkit' ),
				'<code>[gravityform id="' . absint( $form['id'] ) . '" title="false" ajax="true"]</code>'
			);
		}

		return array( $gift, $fee, $thanks );
	}

	/**
	 * Presets must include at least one positive amount.
	 *
	 * @param \Gravity_Forms\Gravity_Forms\Settings\Fields\Base $field The setting.
	 * @param string                                             $value Its value.
	 */
	public function validate_presets( $field, $value ) {
		if ( ! Form_Builder::parse_presets( $value ) ) {
			$field->set_error( esc_html__( 'Enter at least one amount, such as 25, 50, 100.', 'giftkit' ) );
		}
	}

	/**
	 * The value must be a number of zero or more.
	 *
	 * @param \Gravity_Forms\Gravity_Forms\Settings\Fields\Base $field The setting.
	 * @param string                                             $value Its value.
	 */
	public function validate_non_negative( $field, $value ) {
		if ( '' !== (string) $value && ( ! is_numeric( $value ) || (float) $value < 0 ) ) {
			$field->set_error( esc_html__( 'Enter a number of 0 or more.', 'giftkit' ) );
		}
	}
}
