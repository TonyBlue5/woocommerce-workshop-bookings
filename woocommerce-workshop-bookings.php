<?php
/**
 * Plugin Name: Workshop Bookings for WooCommerce by e-iT
 * Plugin URI: https://github.com/TonyBlue5/woocommerce-workshop-bookings
 * Description: API-free WooCommerce workshop bookings with visual availability, monthly or single participation, reminders, RSVP, shared capacity and calendar exports.
 * Version: 0.6.2
 * Author: e-iT
 * Requires Plugins: woocommerce
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * WC tested up to: 11.1
 * Text Domain: woocommerce-workshop-bookings
 * License: GPL-2.0-or-later
 * Update URI: https://github.com/TonyBlue5/woocommerce-workshop-bookings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KWB_VERSION', '0.6.2' );
define( 'KWB_PLUGIN_FILE', __FILE__ );
define( 'KWB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				__FILE__,
				true
			);
		}
	}
);

require_once KWB_PLUGIN_DIR . 'includes/class-kwb-settings.php';
require_once KWB_PLUGIN_DIR . 'includes/class-kwb-booking.php';
require_once KWB_PLUGIN_DIR . 'includes/class-kwb-rsvp.php';
require_once KWB_PLUGIN_DIR . 'includes/class-kwb-updater.php';

KWB_GitHub_Updater::init();

add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function () {
					if ( ! current_user_can( 'activate_plugins' ) ) {
						return;
					}
					echo '<div class="notice notice-error"><p><strong>Workshop Bookings for WooCommerce:</strong> ';
					esc_html_e( 'Απαιτείται ενεργό WooCommerce.', 'woocommerce-workshop-bookings' );
					echo '</p></div>';
				}
			);
			return;
		}

		KWB_Settings::init();
		KWB_Booking::init();
		KWB_RSVP::init();
	}
);
