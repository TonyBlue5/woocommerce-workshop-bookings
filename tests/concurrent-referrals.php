<?php
if(!defined('ABSPATH')){exit(1);}
add_filter('pre_wp_mail',static function(){return true;});
$mode=getenv('KWB_TEST_MODE');
if('prepare'===$mode){
 $owner=wp_insert_user(array('user_login'=>'concurrent-owner','user_pass'=>wp_generate_password(),'user_email'=>'concurrent-owner@example.org'));
 $policy=KWB_Rewards::sanitize(array('enabled'=>1,'friends'=>1,'mode'=>'single'));
 $p=new WC_Product_Simple();$p->set_name('Concurrent referral');$p->set_regular_price(20);$pid=$p->save();
 $order=wc_create_order();$order->set_billing_email('concurrent-guest@example.org');
 $id=$order->add_product($p,1);$item=$order->get_item($id);$item->add_meta_data('_kwb_booking_type','single');$item->save();
 $order->update_meta_data('_kwb_referrer',array('id'=>$owner,'policy'=>$policy,'email'=>'concurrent-guest@example.org'));
 $order->calculate_totals();$order->save();update_option('kwb_test_concurrency',array('owner'=>$owner,'order'=>$order->get_id(),'policy'=>$policy),false);return;
}
$fixture=get_option('kwb_test_concurrency');
if('qualify'===$mode){$order=wc_get_order($fixture['order']);$order->update_status('completed');KWB_Rewards::qualify($order->get_id());return;}
if('assert'===$mode){
 $coupons=get_posts(array('post_type'=>'shop_coupon','meta_key'=>'_kwb_owner','meta_value'=>$fixture['owner'],'numberposts'=>-1));
 if(count(KWB_Rewards::qualified($fixture['owner'],$fixture['policy']))!==1||count($coupons)!==1){throw new RuntimeException('Concurrent completion created duplicate or missing rewards');}
 echo "concurrent-referrals-ok\n";
}
