<?php
/**
 * Plugin Name: Workshop Bookings Pro for WooCommerce by e-iT
 * Plugin URI: https://github.com/TonyBlue5/woocommerce-workshop-bookings
 * Description: API-free WooCommerce workshop bookings with visual availability, monthly or single participation, reminders, RSVP, shared capacity and calendar exports.
 * Version: 1.0.1
 * Author URI: https://it-e.gr
 * Author: e-iT
 * Requires Plugins: woocommerce
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * WC tested up to: 11.1
 * Text Domain: workshop-bookings-pro-for-woocommerce
 * License: GPL-2.0-or-later
 * Update URI: https://github.com/TonyBlue5/woocommerce-workshop-bookings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// A single edition owns the existing booking hooks and metadata.
if (defined('KWB_VERSION')) {
 add_action('admin_notices',static function(){ if(current_user_can('activate_plugins')) { echo '<div class="notice notice-warning"><p>Workshop Bookings: activate only one edition. Deactivate the other edition first.</p></div>'; } });
 return;
}
$kwb_legacy='woocommerce-workshop-bookings/woocommerce-workshop-bookings.php';
if (plugin_basename(__FILE__)!==$kwb_legacy && (in_array($kwb_legacy,(array)get_option('active_plugins',array()),true) || isset(get_site_option('active_sitewide_plugins',array())[$kwb_legacy]))) {
 add_action('admin_notices',static function(){ if(current_user_can('activate_plugins')) { echo '<div class="notice notice-warning"><p>Workshop Bookings: deactivate the legacy plugin before activating this edition. Your bookings are preserved.</p></div>'; } });
 return;
}
unset($kwb_legacy);
define('KWB_EDITION','pro');
define( 'KWB_VERSION', '1.0.1' );
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

require_once KWB_PLUGIN_DIR . 'includes/class-kwb-i18n.php';
require_once KWB_PLUGIN_DIR . 'includes/class-kwb-settings.php';
require_once KWB_PLUGIN_DIR . 'includes/class-kwb-ui.php';
require_once KWB_PLUGIN_DIR . 'includes/class-kwb-booking.php';
require_once KWB_PLUGIN_DIR . 'includes/class-kwb-rsvp.php';
require_once KWB_PLUGIN_DIR . 'includes/class-kwb-updater.php';

require_once KWB_PLUGIN_DIR . 'includes/class-kwb-commercial.php';
require_once KWB_PLUGIN_DIR . 'includes/class-kwb-rewards.php';
require_once KWB_PLUGIN_DIR . 'includes/class-kwb-campaigns.php';
require_once KWB_PLUGIN_DIR . 'includes/class-kwb-dashboard.php';
require_once KWB_PLUGIN_DIR . 'includes/class-kwb-messages.php';
require_once KWB_PLUGIN_DIR . 'includes/class-kwb-admin-calendar.php';

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
					echo esc_html( KWB_I18n::t( 'requires_woocommerce' ) );
					echo '</p></div>';
				}
			);
			return;
		}

		KWB_Settings::init();
		KWB_Booking::init();
        KWB_UI::init();
		KWB_RSVP::init();
		KWB_Commercial::init();
		KWB_Messages::init();
		KWB_Admin_Calendar::init();
	}
);
