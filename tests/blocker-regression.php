<?php
/** Real WooCommerce storage, admin templates, emails and reward lifecycle. No outgoing mail. */
if(!defined('ABSPATH')) { exit(1); }
function kwbb_assert($ok,$message) { if(!$ok) { throw new RuntimeException($message); } }
add_filter('pre_wp_mail',static function(){return true;});
$greek=static function(){return 'el';};add_filter('locale',$greek);add_filter('determine_locale',$greek);
$product=new WC_Product_Simple();$product->set_name('Blocker workshop');$product->set_regular_price(40);$product->set_virtual(true);$pid=$product->save();update_post_meta($pid,'_kwb_enabled','yes');
$order=wc_create_order();$iid=$order->add_product($product,1);$item=$order->get_item($iid);
KWB_Booking::order_meta($item,'',array('kwb_booking'=>array('type'=>'single','month'=>'','occurrences'=>array(array('id'=>str_repeat('a',64),'date'=>'2030-10-08','start'=>'17:00','end'=>'17:45','capacity'=>10)))),null);
$item->add_meta_data('_kwb_unknown_future_meta','technical-secret');$item->save();$item_id=$iid;
ob_start();include WC_ABSPATH.'includes/admin/meta-boxes/views/html-order-item-meta.php';$admin_html=ob_get_clean();
kwbb_assert(strpos($admin_html,'08/10/2030')!==false,'actual admin template lost friendly date');
foreach(array('_kwb_','technical-secret','2030-10-08',str_repeat('a',64)) as $secret) { kwbb_assert(strpos($admin_html,$secret)===false,'actual admin view/editor leaks '.$secret); }
$order->calculate_totals();$order->save();
foreach(array(false,true) as $plain) {
 $email=wc_get_email_order_items($order,array('plain_text'=>$plain,'sent_to_admin'=>false));
 kwbb_assert(strpos($email,'08/10/2030')!==false&&strpos($email,'technical-secret')===false&&strpos($email,'_kwb_')===false,'email template leaks internal data or ISO date');
}
$item->delete_meta_data('_kwb_booking_type');$item->save();
kwbb_assert(strpos(wc_display_item_meta($item,array('echo'=>false)),'technical-secret')===false,'incomplete historical item leaks internal metadata');
kwbb_assert($item->get_meta('_kwb_unknown_future_meta')==='technical-secret','hiding changed stored data');
if(KWB_EDITION==='lite') { echo "blocker-regression-ok\n";return; }
$admin=get_users(array('role'=>'administrator','number'=>1))[0];wp_set_current_user($admin->ID);
foreach(array('analytics','customers','broadcast','referrals','deliveries') as $tab) {
 $_GET=array('tab'=>$tab);ob_start();KWB_Dashboard::render();$html=ob_get_clean();
 kwbb_assert(!preg_match('/<input[^>]+type=["\'](?:date|month)["\']/i',$html),'native browser date in '.$tab);
 kwbb_assert(!preg_match('/<input(?=[^>]+name=["\'](?:product|products\[\]|product_id)["\'])(?![^>]+type=["\']hidden["\'])[^>]*>/i',$html),'raw workshop input in '.$tab);
}
// The exact reported account, two different guests, one milestone, repeated email.
$policy=KWB_Rewards::sanitize(array('enabled'=>1,'friends'=>2,'mode'=>'monthly'));update_option('kwb_referral_settings',$policy);
$owner=wp_insert_user(array('user_login'=>'blocker-owner','user_pass'=>wp_generate_password(),'user_email'=>'ankanaris@gmail.com','role'=>'customer'));
function kwbb_click($owner) {
 unset($_COOKIE['kwb_ref']);if(WC()->session) { WC()->session->set('kwb_ref',null); }
 parse_str((string)wp_parse_url(KWB_Rewards::link($owner),PHP_URL_QUERY),$query);$_GET['kwb_ref']=$query['kwb_ref'];KWB_Rewards::capture();unset($_GET['kwb_ref']);
}
function kwbb_order($product,$email,$customer=0) {
 $order=wc_create_order(array('customer_id'=>$customer));$order->set_billing_email($email);
 $id=$order->add_product($product,1);$item=$order->get_item($id);$item->add_meta_data('_kwb_booking_type','monthly');$item->save();$order->calculate_totals();
 do_action('woocommerce_checkout_create_order',$order);$order->save();return $order;
}
kwbb_click($owner);$a=kwbb_order($product,'blocker-a@example.org');$b=kwbb_order($product,'blocker-b@example.org');
kwbb_assert((int)$a->get_meta('_kwb_referrer')['id']===$owner&&$a->get_meta('_kwb_referrer')['token'],'attribution not stored before completion');
unset($_COOKIE['kwb_ref']);if(WC()->session) { WC()->session->set('kwb_ref',null); }
$a->update_status('completed');$b->update_status('completed');
kwbb_assert(count(KWB_Rewards::qualified($owner,$policy))===2,'ankanaris two guest emails did not count');
$coupons=get_posts(array('post_type'=>'shop_coupon','numberposts'=>-1,'meta_key'=>'_kwb_owner','meta_value'=>$owner));
kwbb_assert(count($coupons)===1,'ankanaris configured coupon missing');$coupon=new WC_Coupon($coupons[0]->ID);
wp_set_current_user($owner);ob_start();KWB_Dashboard::account();$dashboard=ob_get_clean();
kwbb_assert(strpos($dashboard,'Επιλέξιμοι φίλοι με τους τρέχοντες όρους: 2')!==false&&strpos($dashboard,$coupon->get_code())!==false,'customer dashboard lost count or coupon');
kwbb_assert($coupon->get_amount()==$policy['percent']&&$coupon->get_email_restrictions()===array('ankanaris@gmail.com'),'coupon terms/recipient incorrect');
wp_set_current_user(0);kwbb_click($owner);$repeat=kwbb_order($product,'BLOCKER-A@example.org');$repeat->update_status('completed');
kwbb_assert(count(KWB_Rewards::qualified($owner,$policy))===2,'repeat email counted twice');
$a->update_status('refunded');
kwbb_assert(count(KWB_Rewards::qualified($owner,$policy))===2,'valid repeat purchase lost after first order refund');
$repeat->update_status('cancelled');kwbb_assert(count(KWB_Rewards::qualified($owner,$policy))===1,'cancelled/refunded purchases counted');
wp_set_current_user($owner);kwbb_assert(!KWB_Rewards::valid(true,$coupon),'invalidated milestone still usable');wp_set_current_user(0);
// A draft checkout can update billing details before creation/payment finishes.
kwbb_click($owner);$draft=kwbb_order($product,'draft-before@example.org');$draft->set_billing_email('draft-final@example.org');
do_action('woocommerce_store_api_checkout_update_order_meta',$draft);$draft->save();$draft->update_status('completed');
kwbb_assert(count(KWB_Rewards::qualified($owner,$policy))===2&&$draft->get_meta('_kwb_referrer')['email']==='draft-final@example.org','Store API draft billing changes lost attribution');
$friend=wp_insert_user(array('user_login'=>'blocker-later-account','user_pass'=>wp_generate_password(),'user_email'=>'blocker-b@example.org'));
global $wpdb;
kwbb_assert((int)$wpdb->get_var($wpdb->prepare("SELECT friend_id FROM {$wpdb->prefix}kwb_referral_orders WHERE order_id=%d",$b->get_id()))===$friend,'guest history not linked');
$registered=kwbb_order($product,'blocker-b@example.org',$friend);$registered->update_status('completed');
kwbb_assert(count(KWB_Rewards::qualified($owner,$policy))===2,'later account double counted');
$self=kwbb_order($product,'ANKANARIS@gmail.com');$self->update_status('completed');kwbb_assert(!$self->get_meta('_kwb_referrer'),'self referral accepted');
// Separate policies and referrers must not collide on friend_id/email primary keys.
$policy2=$policy;$policy2['percent']=50;update_option('kwb_referral_settings',$policy2);kwbb_click($owner);
$other_policy=kwbb_order($product,'blocker-b@example.org');$other_policy->update_status('completed');
kwbb_assert(count(KWB_Rewards::qualified($owner,$policy2))===1&&count(KWB_Rewards::qualified($owner,$policy))===2,'policy identity collision');
$owner2=wp_insert_user(array('user_login'=>'blocker-owner-two','user_pass'=>wp_generate_password(),'user_email'=>'blocker-owner-two@example.org'));kwbb_click($owner2);
$other_owner=kwbb_order($product,'blocker-b@example.org');$other_owner->update_status('completed');
kwbb_assert(count(KWB_Rewards::qualified($owner2,$policy2))===1,'referrer identity collision');
// Replay must neither lose history nor duplicate coupons.
KWB_Rewards::reconcile();KWB_Rewards::reconcile();
kwbb_assert(count(get_posts(array('post_type'=>'shop_coupon','numberposts'=>-1,'meta_key'=>'_kwb_owner','meta_value'=>$owner)))===1,'replay duplicated coupon');
// Simulate an old release: migrate both tables without changing bookings/coupons.
$legacy_order=$b->get_id();$row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}kwb_referral_orders WHERE order_id=%d",$legacy_order),ARRAY_A);
unset($row['policy_key']);$wpdb->insert($wpdb->prefix.'kwb_guest_referrals',$row);$wpdb->delete($wpdb->prefix.'kwb_referral_orders',array('order_id'=>$legacy_order));
update_option('kwb_commercial_schema','2');KWB_Commercial::install();KWB_Commercial::install();
kwbb_assert(get_option('kwb_commercial_schema')==='3'&&count(KWB_Rewards::qualified($owner,$policy))===2,'restartable legacy migration lost history');
kwbb_assert((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}kwb_guest_referrals WHERE order_id=%d",$legacy_order))===1,'migration removed legacy evidence');
unset($_COOKIE['kwb_ref']);if(WC()->session) { WC()->session->set('kwb_ref',null); }wp_set_current_user(0);
echo "blocker-regression-ok\n";
