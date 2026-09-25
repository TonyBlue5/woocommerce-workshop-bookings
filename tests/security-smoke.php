<?php
if ( ! defined( 'ABSPATH' ) ) { exit(1); }
wp_set_current_user(1);
function kwb_assert($ok,$msg){if(!$ok){fwrite(STDERR,"SECURITY TEST FAILED: {$msg}\n");exit(1);}}

$p=new WC_Product_Simple();$p->set_name('Recurring Booking CI');$p->set_regular_price('1');$id=$p->save();
update_post_meta($id,'_kwb_enabled','yes');
update_post_meta($id,'_kwb_monthly_enabled','yes');
update_post_meta($id,'_kwb_monthly_price','40');
update_post_meta($id,'_kwb_single_enabled','yes');
update_post_meta($id,'_kwb_single_price','14');
update_post_meta($id,'_kwb_weekly_schedule',"2|19:15|20:00|2\n6|11:00|11:45|2");
update_post_meta($id,'_kwb_blackouts','2030-10-05');
update_post_meta($id,'_kwb_horizon_months','12');

$wc_product = wc_get_product($id);
kwb_assert( KWB_Booking::purchasable(false,$wc_product), 'booking product should be purchasable without base WooCommerce price' );
$price_html = KWB_Booking::price_html('', $wc_product);
kwb_assert( false !== strpos($price_html,'40') && false !== strpos($price_html,'14'), 'booking price HTML does not expose monthly and single prices' );

$os=KWB_Booking::month_occurrences($id,'2030-10',false);
kwb_assert(8===count($os),'monthly occurrence generation/blackout handling failed');
kwb_assert(40.0===KWB_Booking::price($id,'monthly'),'monthly price failed');
kwb_assert(14.0===KWB_Booking::price($id,'single'),'single price failed');

$first=reset($os);
$order=wc_create_order();
$item_id=$order->add_product(wc_get_product($id),1);
$item=$order->get_item($item_id);
$clean=array();
foreach($os as$o)$clean[]=array('id'=>$o['id'],'date'=>$o['date'],'start'=>$o['start'],'end'=>$o['end'],'capacity'=>$o['capacity']);
$item->add_meta_data('_kwb_booking_type','monthly',true);
$item->add_meta_data('_kwb_booking_month','2030-10',true);
$item->add_meta_data('_kwb_occurrences',wp_json_encode($clean),true);
$item->save();
$order->set_status('processing');$order->save();
kwb_assert(1===KWB_Booking::remaining($id,$first),'monthly booking did not reduce shared single-session capacity');

$before=get_post_meta($id,'_kwb_weekly_schedule',true);
$_POST=array('_kwb_weekly_schedule'=>'7|09:00|10:00|99');
KWB_Booking::save($id);
kwb_assert($before===get_post_meta($id,'_kwb_weekly_schedule',true),'admin save accepted without nonce');

kwb_assert(class_exists('KWB_GitHub_Updater'),'secure updater missing');
kwb_assert(class_exists('KWB_RSVP'),'RSVP engine missing');

// A declined occurrence must immediately free its seat.
$item = $order->get_item($item_id);
$item->update_meta_data('_kwb_declined_occurrences',array((string)$first['id']));
$item->save();
kwb_assert(2===KWB_Booking::remaining($id,$first),'declined occurrence did not release shared capacity');
echo "security-smoke-ok\n";
