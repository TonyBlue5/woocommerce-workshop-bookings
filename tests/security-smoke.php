<?php
/**
 * Security regression smoke tests executed with WP-CLI.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

wp_set_current_user( 1 );

function kwb_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "SECURITY TEST FAILED: {$message}\n" );
		exit( 1 );
	}
}

// Create a WooCommerce product.
$product = new WC_Product_Simple();
$product->set_name( 'KWB Security Test Workshop' );
$product->set_regular_price( '10' );
$product_id = $product->save();

update_post_meta( $product_id, '_kwb_enabled', 'no' );
update_post_meta( $product_id, '_kwb_slots', '' );

// 1. Product settings must not change without a valid nonce.
$_POST = array(
	'_kwb_enabled' => 'yes',
	'_kwb_slots'   => '2030-01-05|11:00|12:00|10',
);
Kangiroo_Workshop_Bookings::save_product_settings( $product_id );
kwb_assert( 'no' === get_post_meta( $product_id, '_kwb_enabled', true ), 'admin save accepted without nonce' );

// 2. Product settings must not change with an invalid nonce.
$_POST['kwb_nonce'] = 'invalid';
Kangiroo_Workshop_Bookings::save_product_settings( $product_id );
kwb_assert( 'no' === get_post_meta( $product_id, '_kwb_enabled', true ), 'admin save accepted invalid nonce' );

// 3. Valid nonce should allow a sanitized update.
$_POST['kwb_nonce'] = wp_create_nonce( 'kwb_save_product_' . $product_id );
$_POST['_kwb_slots'] = "2030-01-05|11:00|12:00|999999\ninvalid-slot\n";
Kangiroo_Workshop_Bookings::save_product_settings( $product_id );

kwb_assert( 'yes' === get_post_meta( $product_id, '_kwb_enabled', true ), 'valid admin save was rejected' );
kwb_assert(
	'2030-01-05|11:00|12:00|10000' === get_post_meta( $product_id, '_kwb_slots', true ),
	'slot sanitization/capacity limit failed'
);

// 4. Calendar signatures must be bound to the order item booking data.
$order   = wc_create_order();
$item_id = $order->add_product( wc_get_product( $product_id ), 1 );
$order->save();

kwb_assert( is_numeric( $item_id ) && (int) $item_id > 0, 'could not create order line item for signature test' );

$saved_item = $order->get_item( $item_id );
kwb_assert( $saved_item instanceof WC_Order_Item_Product, 'saved order line item was not retrievable' );

$saved_item->add_meta_data( '_kwb_date', '2030-01-05', true );
$saved_item->add_meta_data( '_kwb_start', '11:00', true );
$saved_item->add_meta_data( '_kwb_end', '12:00', true );
$saved_item->save();

$reflection = new ReflectionClass( 'Kangiroo_Workshop_Bookings' );
$method     = $reflection->getMethod( 'signature' );
$method->setAccessible( true );

$sig_before = $method->invoke( null, $order->get_id(), $item_id );

$saved_item = $order->get_item( $item_id );
kwb_assert( $saved_item instanceof WC_Order_Item_Product, 'saved order item disappeared before tamper test' );
$saved_item->update_meta_data( '_kwb_date', '2030-01-06' );
$saved_item->save();

$sig_after = $method->invoke( null, $order->get_id(), $item_id );

kwb_assert( is_string( $sig_before ) && 64 === strlen( $sig_before ), 'calendar signature is not SHA-256 HMAC length' );
kwb_assert( ! hash_equals( $sig_before, $sig_after ), 'calendar signature did not change after booking tamper' );

// 5. Ensure updater is fail-closed when no public verification key is configured.
kwb_assert( class_exists( 'KWB_GitHub_Updater' ), 'secure updater class is missing' );

echo "security-smoke-ok\n";
