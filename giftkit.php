<?php
/**
 * Plugin Name: GiftKit for Gravity Forms
 * Plugin URI: https://github.com/mwender/giftkit
 * Description: A ready-made, multi-page donation form for Gravity Forms. One-time and monthly gifts, preset amounts, an optional processing fee and Stripe payments, set up in one step and managed from a single settings tab.
 * Version: 0.1.0
 * Author: Michael Wender
 * Author URI: https://mwender.com/
 * License: GPL2+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: giftkit
 * Requires at least: 6.5
 * Requires PHP: 8.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GIFTKIT_VERSION', '0.1.0' );
define( 'GIFTKIT_FILE', __FILE__ );
define( 'GIFTKIT_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Registers the add-on once Gravity Forms has loaded.
 */
function giftkit_load() {
	if ( ! method_exists( 'GFForms', 'include_addon_framework' ) ) {
		return;
	}

	GFForms::include_addon_framework();

	require_once GIFTKIT_DIR . 'includes/class-form-builder.php';
	require_once GIFTKIT_DIR . 'includes/class-addon.php';

	GFAddOn::register( 'GiftKit\\Addon' );
}
add_action( 'gform_loaded', 'giftkit_load', 5 );

/**
 * Returns the add-on instance.
 *
 * @return GiftKit\Addon|null
 */
function giftkit() {
	return class_exists( 'GiftKit\\Addon' ) ? GiftKit\Addon::get_instance() : null;
}
