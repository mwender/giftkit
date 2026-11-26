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
	 * GiftKit's website.
	 */
	const SITE_URL = 'https://giftkit.org/';

	/**
	 * Where GiftKit's documentation lives (the website built from docs/).
	 */
	const DOCS_URL = 'https://giftkit.org/docs/';

	/**
	 * The admin-post action that updates every form with changes waiting.
	 */
	const UPDATE_ACTION = 'giftkit_update_forms';

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
		add_filter( 'gform_replace_merge_tags', array( $this, 'replace_manage_gift_tag' ), 10, 7 );
		add_filter( 'gform_custom_merge_tags', array( $this, 'add_manage_gift_tag' ), 10, 4 );
		add_filter( 'gform_get_form_filter', array( $this, 'add_credit' ), 10, 2 );
		add_filter( 'gform_stripe_charge_description', array( $this, 'one_time_description' ), 10, 5 );
	}

	/**
	 * Admin hooks.
	 */
	public function init_admin() {
		parent::init_admin();
		add_filter( 'gform_addon_navigation', array( $this, 'add_menu_item' ) );
		add_action( 'admin_post_' . Admin_Page::SLUG, array( Admin_Page::class, 'handle_create' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_forms_list_button' ) );
		add_action( 'admin_notices', array( $this, 'forms_list_notice' ) );
		add_action( 'admin_post_' . self::UPDATE_ACTION, array( $this, 'handle_update_forms' ) );
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
		if ( ! Form_Builder::is_giftkit_form( $form ) ) {
			return $form;
		}

		$form['cssClass'] = trim( rgar( $form, 'cssClass' ) . ' giftkit-form' );

		// The Donation tab's accent color becomes the form's Gravity Forms
		// primary color, unless the embed (shortcode or block) set its own.
		// false means the embed turned Gravity Forms' styles off; leave it.
		$accent = (string) rgars( $form, 'giftkit/accentColor' );
		if ( '' !== $accent && false !== rgar( $form, 'styles' ) ) {
			$styles = is_array( rgar( $form, 'styles' ) ) ? $form['styles'] : array();
			if ( empty( $styles['buttonPrimaryBackgroundColor'] ) ) {
				$styles['buttonPrimaryBackgroundColor'] = $accent;
				$form['styles']                         = $styles;
			}
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
	 * Fills in {giftkit_manage_gift_link}: a link to the Stripe customer
	 * portal, with the donor's email filled in, for monthly gifts only.
	 *
	 * @param string $text       Text containing merge tags.
	 * @param array  $form       The form.
	 * @param array  $entry      The entry.
	 * @param bool   $url_encode Whether to URL-encode values.
	 * @param bool   $esc_html   Whether to escape HTML.
	 * @param bool   $nl2br      Whether to convert new lines.
	 * @param string $format     "html" or "text".
	 *
	 * @return string
	 */
	public function replace_manage_gift_tag( $text, $form, $entry, $url_encode, $esc_html, $nl2br, $format ) {
		if ( false === strpos( (string) $text, Form_Builder::MANAGE_GIFT_TAG ) ) {
			return $text;
		}

		$link = self::manage_gift_link( $form, $entry, $format );

		// In HTML the link is its own paragraph, so take the blank lines before
		// the tag with it (GF may already have turned them into <br>s). With
		// no link, that leaves nothing behind.
		if ( '' === $link || 'text' !== $format ) {
			$text = preg_replace( '/(?:\s|<br\s*\/?>)*' . preg_quote( Form_Builder::MANAGE_GIFT_TAG, '/' ) . '/i', Form_Builder::MANAGE_GIFT_TAG, $text );
		}

		return str_replace( Form_Builder::MANAGE_GIFT_TAG, $link, $text );
	}

	/**
	 * The manage-gift link for an entry, or '' when it doesn't apply.
	 *
	 * @param array  $form   The form.
	 * @param array  $entry  The entry.
	 * @param string $format "html" or "text".
	 *
	 * @return string
	 */
	public static function manage_gift_link( $form, $entry, $format = 'html' ) {
		$portal    = (string) rgars( $form, 'giftkit/portalUrl' );
		$frequency = Form_Builder::field( $form, 'frequency' );
		if ( '' === $portal || ! $frequency || ! is_array( $entry ) || Form_Builder::MONTHLY !== rgar( $entry, (string) $frequency->id ) ) {
			return '';
		}

		$email = Form_Builder::field( $form, 'email' );
		$email = $email ? (string) rgar( $entry, (string) $email->id ) : '';
		if ( is_email( $email ) ) {
			$portal = add_query_arg( 'prefilled_email', rawurlencode( $email ), $portal );
		}

		if ( 'text' === $format ) {
			/* translators: %s: link to the Stripe customer portal */
			return sprintf( __( 'To update your card or cancel your monthly gift at any time, visit %s', 'giftkit' ), esc_url_raw( $portal ) );
		}

		$link = sprintf(
			/* translators: 1: link to the Stripe customer portal, 2: its text */
			__( 'To update your card or cancel your monthly gift at any time, <a href="%1$s">%2$s</a>.', 'giftkit' ),
			esc_url( $portal ),
			esc_html__( 'manage your monthly gift', 'giftkit' )
		);

		/**
		 * Filters the "manage your monthly gift" paragraph shown to monthly donors.
		 *
		 * @param string $html   The paragraph.
		 * @param string $portal The portal URL, with the donor's email.
		 * @param array  $form   The form.
		 * @param array  $entry  The entry.
		 */
		return apply_filters( 'giftkit_manage_gift_link_html', '<p class="giftkit-manage-gift">' . wp_kses( $link, array( 'a' => array( 'href' => array() ) ) ) . '</p>', $portal, $form, $entry );
	}

	/**
	 * Describes a one-time gift in Stripe, in place of the Stripe Add-On's
	 * "Entry ID: …, Products: …", e.g. "Donation to The Maker City: $25.00
	 * gift + $0.75 to cover fees (entry 6244)".
	 *
	 * @param string $description     The Stripe Add-On's description.
	 * @param array  $strings         Its parts.
	 * @param array  $entry           The entry.
	 * @param array  $submission_data Payment data, including line items.
	 * @param array  $feed            The Stripe feed.
	 *
	 * @return string
	 */
	public function one_time_description( $description, $strings, $entry, $submission_data, $feed ) {
		if ( 'product' !== rgars( $feed, 'meta/transactionType' ) ) {
			return $description;
		}

		$form = GFAPI::get_form( rgar( $entry, 'form_id' ) ?: rgar( $feed, 'form_id' ) );
		if ( ! Form_Builder::is_giftkit_form( $form ) ) {
			return $description;
		}

		$amounts = array( 'gift-product' => 0, 'fee-product' => 0 );
		foreach ( $amounts as $role => $amount ) {
			$field = Form_Builder::field( $form, $role );
			foreach ( (array) rgar( $submission_data, 'line_items' ) as $item ) {
				if ( $field && (int) rgar( $item, 'id' ) === (int) $field->id ) {
					$amounts[ $role ] += (float) rgar( $item, 'unit_price' ) * max( 1, (int) rgar( $item, 'quantity' ) );
				}
			}
		}

		$currency = rgar( $entry, 'currency' ) ?: GFCommon::get_currency();
		$name     = (string) rgars( $form, 'giftkit/oneTimeName' );
		if ( '' === $name ) {
			$name = Form_Builder::default_settings()['oneTimeName'];
		}

		$text = $amounts['fee-product'] > 0
			/* translators: 1: one-time gift name, 2: gift amount, 3: fee amount */
			? sprintf( __( '%1$s: %2$s gift + %3$s to cover fees', 'giftkit' ), $name, GFCommon::to_money( $amounts['gift-product'], $currency ), GFCommon::to_money( $amounts['fee-product'], $currency ) )
			/* translators: 1: one-time gift name, 2: gift amount */
			: sprintf( __( '%1$s: %2$s gift', 'giftkit' ), $name, GFCommon::to_money( $amounts['gift-product'], $currency ) );

		if ( rgar( $entry, 'id' ) ) {
			/* translators: %d: Gravity Forms entry ID */
			$text .= ' ' . sprintf( __( '(entry %d)', 'giftkit' ), $entry['id'] );
		}

		return html_entity_decode( $text, ENT_QUOTES );
	}

	/**
	 * Adds a "Powered by GiftKit" link under the form, only when the site
	 * owner has turned it on.
	 *
	 * @param string $html The form's markup.
	 * @param array  $form The form.
	 *
	 * @return string
	 */
	public function add_credit( $html, $form ) {
		if ( ! Form_Builder::is_giftkit_form( $form ) || '1' !== (string) rgars( $form, 'giftkit/showCredit' ) ) {
			return $html;
		}

		// A small version of the GiftKit mark and wordmark.
		$lockup = sprintf(
			'<img class="giftkit-credit__mark" src="%1$s" alt="" width="18" height="18"><span class="giftkit-credit__name">GiftKit</span>',
			esc_url( plugins_url( 'assets/images/giftkit-mark.svg', GIFTKIT_FILE ) )
		);

		return $html . sprintf(
			'<p class="giftkit-credit"><a href="%1$s" target="_blank" rel="noopener">%2$s</a></p>',
			esc_url( self::SITE_URL ),
			'<span class="giftkit-credit__label">' . esc_html__( 'Powered by', 'giftkit' ) . '</span>' . $lockup
		);
	}

	/**
	 * Lists {giftkit_manage_gift_link} in the merge tag menu on GiftKit forms.
	 *
	 * @param array $tags    Custom merge tags.
	 * @param int   $form_id Form ID.
	 *
	 * @return array
	 */
	public function add_manage_gift_tag( $tags, $form_id ) {
		if ( Form_Builder::is_giftkit_form( GFFormsModel::get_form_meta( $form_id ) ) ) {
			$tags[] = array(
				'label' => __( 'Manage monthly gift link', 'giftkit' ),
				'tag'   => Form_Builder::MANAGE_GIFT_TAG,
			);
		}

		return $tags;
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
				'deps'      => array( 'jquery', 'wp-color-picker' ),
				'in_footer' => true,
				'enqueue'   => self::admin_asset_pages(),
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
				'deps'    => array( 'wp-color-picker' ),
				'enqueue' => self::admin_asset_pages(),
			),
		);

		return array_merge( parent::styles(), $styles );
	}

	/**
	 * Where GiftKit's admin script and styles load: the New Donation Form
	 * page, and a form's Donation tab.
	 *
	 * @return array Enqueue conditions.
	 */
	private static function admin_asset_pages() {
		return array(
			array( 'query' => 'page=' . Admin_Page::SLUG ),
			array( 'query' => 'page=gf_edit_forms&view=settings&subview=giftkit' ),
		);
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
			/* translators: %s: total including the processing fee, e.g. $103.00 */
			'feeTotal'        => __( 'Total with the fee: %s', 'giftkit' ),
			/* translators: %s: monthly total including the processing fee, e.g. $103.00 */
			'feeTotalMonthly' => __( 'Total with the fee: %s a month', 'giftkit' ),
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
	public function enqueue_forms_list_button() {
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

		wp_register_script( 'giftkit-forms-list', false, array(), $this->_version, true );
		wp_enqueue_script( 'giftkit-forms-list' );
		wp_add_inline_script( 'giftkit-forms-list', $script );
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

		// The toggle reflects the notification itself, which can also be
		// switched off under Notifications.
		$donor = rgars( $form, 'notifications/' . Form_Builder::DONOR_NOTIFICATION );
		if ( $donor ) {
			$settings['donorEmail'] = rgar( $donor, 'isActive' ) ? '1' : '0';
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

		if ( isset( $settings['accentColor'] ) ) {
			$settings['accentColor'] = (string) sanitize_hex_color( trim( $settings['accentColor'] ) );
		}

		// With a portal link set, make sure the thank-you message shows it.
		if ( ! empty( $settings['portalUrl'] ) && isset( $settings['confirmationMessage'] ) && false === strpos( $settings['confirmationMessage'], Form_Builder::MANAGE_GIFT_TAG ) ) {
			$settings['confirmationMessage'] = rtrim( $settings['confirmationMessage'] ) . "\n\n" . Form_Builder::MANAGE_GIFT_TAG;
		}

		if ( ! parent::save_form_settings( $form, $settings ) ) {
			return false;
		}

		$form = GFAPI::get_form( $form['id'] );

		return Form_Builder::update( $form, $form['giftkit'] );
	}

	/**
	 * What saving a form's Donation tab would change, from the form as
	 * stored now.
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return string[]
	 */
	public function pending_changes( $form_id ) {
		$form = GFAPI::get_form( $form_id );
		if ( ! Form_Builder::is_giftkit_form( $form ) ) {
			return array();
		}

		return Form_Builder::pending_changes( $form, $this->get_form_settings( $form ) );
	}

	/**
	 * The list of changes, and what GiftKit does and doesn't touch.
	 *
	 * @param string[] $changes What would change.
	 *
	 * @return string HTML.
	 */
	private static function changes_html( $changes ) {
		$items = '';
		foreach ( $changes as $change ) {
			$items .= '<li style="list-style:disc;">' . esc_html( $change ) . '</li>';
		}

		return '<ul class="giftkit-changes" style="list-style:disc;margin:0.25em 0 0.75em 1.5em;">' . $items . '</ul>';
	}

	/**
	 * The notice at the top of the Donation tab when saving would change the
	 * form, worked out as the page is drawn so it reflects a save just made.
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return array A settings section.
	 */
	private function updates_section( $form_id ) {
		return array(
			'title'      => esc_html__( 'Saving will update this form', 'giftkit' ),
			'class'      => 'giftkit-updates',
			'dependency' => function () use ( $form_id ) {
				return (bool) $this->pending_changes( $form_id );
			},
			'fields'     => array(
				array(
					'name' => 'giftkitUpdates',
					'type' => 'html',
					'html' => function () use ( $form_id ) {
						return '<p>' . esc_html__( 'Some parts of this form that GiftKit manages don\'t match what it would set up now, usually because GiftKit was updated or something was changed in the form editor. Save Settings will:', 'giftkit' ) . '</p>'
							. self::changes_html( $this->pending_changes( $form_id ) )
							. '<p>' . esc_html__( 'Changes made to these parts in the form editor will be replaced. Fields you\'ve added yourself are never touched.', 'giftkit' ) . '</p>';
					},
				),
			),
		);
	}

	/*
	 * Updating forms from the forms list
	 */

	/**
	 * GiftKit forms with changes waiting.
	 *
	 * @return array Form title and changes, keyed by form ID.
	 */
	private function forms_with_changes() {
		$forms = array();
		foreach ( GFAPI::get_forms( null ) as $form ) {
			if ( ! Form_Builder::is_giftkit_form( $form ) ) {
				continue;
			}
			$changes = Form_Builder::pending_changes( $form, $this->get_form_settings( $form ) );
			if ( $changes ) {
				$forms[ (int) $form['id'] ] = array(
					'title'   => $form['title'],
					'changes' => $changes,
				);
			}
		}

		return $forms;
	}

	/**
	 * On the forms list, lists the GiftKit forms with changes waiting, with
	 * a button that updates them all.
	 *
	 * Gravity Forms hides notices on its pages unless they have the
	 * `gf-notice` class.
	 */
	public function forms_list_notice() {
		if ( 'gf_edit_forms' !== rgget( 'page' ) || rgget( 'id' ) || ! GFCommon::current_user_can_any( 'gravityforms_edit_forms' ) ) {
			return;
		}

		$updated = absint( rgget( 'giftkit_updated' ) );
		if ( $updated ) {
			printf(
				'<div class="notice notice-success is-dismissible gf-notice"><p>%s</p></div>',
				/* translators: %d: number of forms */
				esc_html( sprintf( _n( 'GiftKit updated %d donation form.', 'GiftKit updated %d donation forms.', $updated, 'giftkit' ), $updated ) )
			);
		}

		$forms = $this->forms_with_changes();
		if ( ! $forms ) {
			return;
		}

		$count   = count( $forms );
		$confirm = __( 'Update these forms? Changes made in the form editor to the parts listed will be replaced.', 'giftkit' );
		?>
		<div class="notice notice-warning gf-notice giftkit-forms-notice">
			<p>
				<strong>
					<?php
					/* translators: %d: number of forms */
					echo esc_html( sprintf( _n( '%d donation form has updates waiting.', '%d donation forms have updates waiting.', $count, 'giftkit' ), $count ) );
					?>
				</strong>
				<?php esc_html_e( 'Some parts of these forms that GiftKit manages don\'t match what it would set up now, usually because GiftKit was updated or something was changed in the form editor. Update all makes these changes, as saving each form\'s Donation tab would:', 'giftkit' ); ?>
			</p>
			<ul style="margin-left:1em;">
				<?php foreach ( $forms as $form_id => $form ) : ?>
					<li><a href="<?php echo esc_url( self::settings_url( $form_id ) ); ?>"><?php echo esc_html( $form['title'] ); ?></a><?php echo self::changes_html( $form['changes'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in changes_html(). ?></li>
				<?php endforeach; ?>
			</ul>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return window.confirm(<?php echo esc_attr( wp_json_encode( $confirm ) ); ?>);">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::UPDATE_ACTION ); ?>">
				<?php wp_nonce_field( self::UPDATE_ACTION ); ?>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Update all', 'giftkit' ); ?></button></p>
			</form>
		</div>
		<?php
	}

	/**
	 * Updates GiftKit forms with changes waiting, as saving each form's
	 * Donation tab would: all of them, or the one in `form_id`. Returns to
	 * the page the request came from.
	 */
	public function handle_update_forms() {
		if ( ! GFCommon::current_user_can_any( 'gravityforms_edit_forms' ) ) {
			wp_die( esc_html__( 'You are not allowed to edit forms.', 'giftkit' ), 403 );
		}
		check_admin_referer( self::UPDATE_ACTION );

		$only    = absint( rgpost( 'form_id' ) );
		$updated = 0;
		foreach ( array_keys( $this->forms_with_changes() ) as $form_id ) {
			if ( $only && $only !== $form_id ) {
				continue;
			}
			$form = GFAPI::get_form( $form_id );
			if ( Form_Builder::update( $form, $this->get_form_settings( $form ) ) ) {
				++$updated;
			}
		}

		$back = wp_get_referer() ?: add_query_arg( 'page', 'gf_edit_forms', admin_url( 'admin.php' ) );
		$back = remove_query_arg( array( 'giftkit_updated', 'created' ), $back );
		wp_safe_redirect( add_query_arg( 'giftkit_updated', $updated, $back ) );
		exit;
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
					'name'        => 'oneTimeName',
					'label'       => esc_html__( 'One-time gift name', 'giftkit' ),
					'type'        => 'text',
					'class'       => 'large',
					'description' => esc_html__( 'Shown in your Stripe dashboard for one-time gifts, with the gift, any processing fee and the entry number.', 'giftkit' ),
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
				),
				array(
					'name'                => 'portalUrl',
					'label'               => esc_html__( 'Manage-your-gift link', 'giftkit' ),
					'type'                => 'text',
					'input_type'          => 'url',
					'class'               => 'large',
					'placeholder'         => 'https://billing.stripe.com/p/login/…',
					'description'         => sprintf(
						/* translators: %s: link to the setup instructions */
						esc_html__( 'Your Stripe customer portal login link. Monthly donors see it in the thank-you message, with their email filled in, so they can update their card or cancel without contacting you. %s', 'giftkit' ),
						'<a href="' . esc_url( self::DOCS_URL . 'stripe-customer-portal/' ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'How to get this link', 'giftkit' ) . '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'giftkit' ) . '</span></a>'
					),
					'validation_callback' => array( $this, 'validate_url' ),
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

		$has_donor_email = (bool) rgars( $form, 'notifications/' . Form_Builder::DONOR_NOTIFICATION );
		$donor_field     = array(
			'name'        => 'donorEmail',
			'label'       => esc_html__( 'Email donors a thank-you', 'giftkit' ),
			'type'        => 'toggle',
			'description' => $has_donor_email
				? sprintf(
					/* translators: %s: link to the notification */
					esc_html__( 'Sends each donor a thank-you with a summary of their gift. Monthly donors also get the manage-your-gift link. %s', 'giftkit' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=gf_edit_forms&view=settings&subview=notification&id=' . absint( $form['id'] ) . '&nid=' . Form_Builder::DONOR_NOTIFICATION ) ) . '">' . esc_html__( 'Edit the email', 'giftkit' ) . '</a>'
				)
				: esc_html__( 'Sends each donor a thank-you with a summary of their gift. This form doesn\'t have it yet: leave this on and click Save Settings to add it.', 'giftkit' ),
		);

		$thanks = array(
			'title'  => esc_html__( 'After the gift', 'giftkit' ),
			'fields' => array( $thanks_field, $donor_field ),
		);

		if ( rgget( 'giftkit_created' ) ) {
			$gift['description'] = esc_html__( 'Your donation form is ready. Adjust the settings below.', 'giftkit' );
		}

		$embed = array(
			'title'  => esc_html__( 'Add the form to a page', 'giftkit' ),
			'fields' => array(
				array(
					'name' => 'embedShortcode',
					'type' => 'html',
					'html' => sprintf(
						'<p>%1$s</p><span class="giftkit-embed giftkit-embed--settings"><input type="text" readonly value="%2$s" aria-label="%3$s"><button type="button" class="button giftkit-copy" data-copied="%4$s">%5$s</button></span>',
						esc_html__( 'Paste this shortcode into any page, or use the Gravity Forms block and choose this form.', 'giftkit' ),
						esc_attr( Admin_Page::shortcode( $form['id'] ) ),
						esc_attr__( 'Embed shortcode', 'giftkit' ),
						esc_attr__( 'Copied', 'giftkit' ),
						esc_html__( 'Copy', 'giftkit' )
					),
				),
			),
		);

		$appearance = array(
			'title'  => esc_html__( 'Appearance', 'giftkit' ),
			'fields' => array(
				array(
					'name'                => 'accentColor',
					'label'               => esc_html__( 'Button and accent color', 'giftkit' ),
					'type'                => 'text',
					'class'               => 'giftkit-color',
					'description'         => esc_html__( 'Used for buttons, the chosen amount and the gift type toggle. Leave it empty to use Gravity Forms\' default. A color set on a block or shortcode takes priority.', 'giftkit' ),
					'validation_callback' => array( $this, 'validate_color' ),
				),
			),
		);

		$credit = array(
			'title'  => esc_html__( 'Credit', 'giftkit' ),
			'fields' => array(
				array(
					'name'        => 'showCredit',
					'label'       => esc_html__( 'Show "Powered by GiftKit" below the form', 'giftkit' ),
					'type'        => 'toggle',
					'description' => esc_html__( 'Adds a small link to giftkit.org under the form. It\'s off unless you turn it on, and the form works the same either way.', 'giftkit' ),
				),
			),
		);

		return array( $this->updates_section( $form['id'] ), $embed, $gift, $fee, $appearance, $thanks, $credit );
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
	 * The value must be empty or a hex color such as #0a79b8.
	 *
	 * @param \Gravity_Forms\Gravity_Forms\Settings\Fields\Base $field The setting.
	 * @param string                                             $value Its value.
	 */
	public function validate_color( $field, $value ) {
		if ( '' !== trim( (string) $value ) && ! sanitize_hex_color( trim( $value ) ) ) {
			$field->set_error( esc_html__( 'Enter a color as a hex code, such as #0a79b8, or leave it empty.', 'giftkit' ) );
		}
	}

	/**
	 * The value must be empty or an https URL.
	 *
	 * @param \Gravity_Forms\Gravity_Forms\Settings\Fields\Base $field The setting.
	 * @param string                                             $value Its value.
	 */
	public function validate_url( $field, $value ) {
		if ( '' !== trim( (string) $value ) && ( 'https' !== wp_parse_url( $value, PHP_URL_SCHEME ) || ! wp_http_validate_url( $value ) ) ) {
			$field->set_error( esc_html__( 'Enter a full link starting with https://, as Stripe shows it.', 'giftkit' ) );
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
