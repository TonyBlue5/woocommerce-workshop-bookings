<?php
/** Disposable CI only: real browser checkout, then asynchronous payment completion. */
if(!defined('ABSPATH')) { exit(1); }
add_filter('pre_wp_mail',static function(){return true;});
if(getenv('KWB_TEST_MODE')==='prepare') {
 wp_mkdir_p(WPMU_PLUGIN_DIR);
 file_put_contents(WPMU_PLUGIN_DIR.'/kwb-ci-no-mail.php',"<?php add_filter('pre_wp_mail',static function(){return true;});");
 $owner=get_user_by('email','ankanaris@gmail.com');
 $product=new WC_Product_Simple();$product->set_name('Browser referral workshop');$product->set_regular_price(40);$product->set_virtual(true);$pid=$product->save();
 foreach(array('_kwb_enabled'=>'yes','_kwb_monthly_enabled'=>'yes','_kwb_monthly_price'=>'40','_kwb_horizon_months'=>3,'_kwb_weekly_schedule'=>'1|12:00|13:00|100') as $key=>$value){update_post_meta($pid,$key,$value);}
 $policy=KWB_Rewards::sanitize(array('enabled'=>1,'friends'=>2,'mode'=>'monthly','product'=>$pid));update_option('kwb_referral_settings',$policy);
 foreach(array('cart'=>'[woocommerce_cart]','checkout'=>'[woocommerce_checkout]') as $slug=>$content){$id=wp_insert_post(array('post_type'=>'page','post_status'=>'publish','post_title'=>'Browser '.$slug,'post_content'=>$content));update_option('woocommerce_'.$slug.'_page_id',$id);}
 update_option('woocommerce_enable_guest_checkout','yes');update_option('woocommerce_enable_signup_and_login_from_checkout','no');update_option('woocommerce_default_country','GB');update_option('woocommerce_default_customer_address','base');
 update_option('woocommerce_coming_soon','no');
 update_option('woocommerce_bacs_settings',array('enabled'=>'yes','title'=>'Bank transfer','description'=>'CI payment','instructions'=>''));
 $fixture=array('owner'=>$owner->ID,'product'=>$pid,'policy'=>$policy,'referral'=>KWB_Rewards::link($owner->ID),'product_url'=>get_permalink($pid),'checkout'=>wc_get_checkout_url());
 update_option('kwb_checkout_fixture',$fixture,false);file_put_contents(getenv('GITHUB_WORKSPACE').'/checkout-fixture.json',wp_json_encode($fixture));return;
}
$fixture=get_option('kwb_checkout_fixture');$orders=wc_get_orders(array('limit'=>-1,'type'=>'shop_order','billing_email'=>array('browser-a@example.org','browser-b@example.org')));
if(count($orders)!==3) { throw new RuntimeException('Expected three actual browser checkout orders, got '.count($orders)); }
foreach($orders as $order) {
 $ref=$order->get_meta('_kwb_referrer');
 if($order->get_customer_id()!==0 || (int)($ref['id']??0)!==(int)$fixture['owner'] || ($ref['email']??'')!==strtolower($order->get_billing_email())) { throw new RuntimeException('Actual guest checkout failed to persist signed attribution'); }
 $order->payment_complete();$order->update_status('completed');
}
if(count(KWB_Rewards::qualified($fixture['owner'],$fixture['policy']))!==2) { throw new RuntimeException('Actual browser checkout did not yield two unique referrals'); }
$coupons=get_posts(array('post_type'=>'shop_coupon','numberposts'=>-1,'meta_key'=>'_kwb_owner','meta_value'=>$fixture['owner']));$matching=0;
foreach($coupons as $post){$coupon=new WC_Coupon($post->ID);if(KWB_Rewards::policy_key($coupon->get_meta('_kwb_policy'))===KWB_Rewards::policy_key($fixture['policy'])){$matching++;}}
if($matching!==1){throw new RuntimeException('Actual checkout milestone coupon missing or duplicated');}
echo "browser-checkout-ok\n";
