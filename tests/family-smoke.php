<?php
if (!defined('ABSPATH')) { exit(1); }
function kwbf_assert($ok,$message) { if(!$ok) { throw new RuntimeException($message); } }
add_filter('pre_wp_mail',static function(){return true;});
kwbf_assert(defined('KWB_EDITION'),'edition missing');
if ('lite'===KWB_EDITION) {
 kwbf_assert(!class_exists('KWB_GitHub_Updater')&&!class_exists('KWB_Rewards')&&!class_exists('KWB_Commercial')&&!class_exists('KWB_Messages'),'Lite loads commercial code');
 kwbf_assert(!has_action('template_redirect',array('KWB_RSVP','handle_response')),'Lite registers RSVP');
 echo "family-smoke-ok\n";return;
}
$policy=KWB_Rewards::sanitize(array('enabled'=>1,'friends'=>2,'mode'=>'monthly'));
update_option('kwb_referral_settings',$policy);
$owner=wp_insert_user(array('user_login'=>'family-owner','user_pass'=>wp_generate_password(),'user_email'=>'family-owner@example.org','role'=>'customer'));
$p=new WC_Product_Simple();$p->set_name('Guest referral workshop');$p->set_regular_price(40);$pid=$p->save();
function kwbf_cookie($owner,$expiry=null) {
 $payload=$owner.'|'.($expiry??(time()+DAY_IN_SECONDS));return $payload.'|'.hash_hmac('sha256',$payload,wp_salt('auth'));
}
function kwbf_order($pid,$email,$customer=0,$hook='woocommerce_checkout_create_order') {
 $order=wc_create_order(array('customer_id'=>$customer));$order->set_billing_email($email);
 $id=$order->add_product(wc_get_product($pid),1,array('subtotal'=>40,'total'=>40));
 $item=$order->get_item($id);$item->add_meta_data('_kwb_booking_type','monthly');$item->save();
 $order->calculate_totals();do_action($hook,$order);$order->save();return $order;
}
wp_set_current_user(0);
$_COOKIE['kwb_ref']=kwbf_cookie($owner);
$a=kwbf_order($pid,'guest-a@example.org');
kwbf_assert((int)$a->get_meta('_kwb_referrer')['id']===$owner,'classic checkout lost guest attribution');
unset($_COOKIE['kwb_ref']);
$a=wc_get_order($a->get_id());$a->update_status('completed');
kwbf_assert(count(KWB_Rewards::qualified($owner,$policy))===1,'asynchronous guest completion failed');
$_COOKIE['kwb_ref']=kwbf_cookie($owner);
$b=kwbf_order($pid,'guest-b@example.org',0,'woocommerce_store_api_checkout_update_order_meta');$b->update_status('completed');
kwbf_assert(count(KWB_Rewards::qualified($owner,$policy))===2,'second guest did not qualify independently');
for($i=0;$i<3;$i++){KWB_Rewards::qualify($b->get_id());}
$coupons=get_posts(array('post_type'=>'shop_coupon','meta_key'=>'_kwb_owner','meta_value'=>$owner,'numberposts'=>-1));
kwbf_assert(count($coupons)===1,'replayed guest completion issued duplicate coupon');
$again=kwbf_order($pid,'guest-a@example.org');$again->update_status('completed');
kwbf_assert(count(KWB_Rewards::qualified($owner,$policy))===2,'repeat billing email counted twice');
$friend=wp_insert_user(array('user_login'=>'family-guest-account','user_pass'=>wp_generate_password(),'user_email'=>'guest-a@example.org'));
add_user_meta($friend,'_kwb_referrer',array('id'=>$owner,'policy'=>$policy));
$registered=kwbf_order($pid,'guest-a@example.org',$friend);$registered->update_status('completed');
kwbf_assert(count(KWB_Rewards::qualified($owner,$policy))===2,'guest to registered conversion counted twice');
$alternate=kwbf_order($pid,'alternate-billing@example.org',$friend);$alternate->update_status('completed');
kwbf_assert(count(KWB_Rewards::qualified($owner,$policy))===2,'account converted from guest bypassed deduplication using alternate billing email');
$self=kwbf_order($pid,'family-owner@example.org');$self->update_status('completed');
kwbf_assert(!$self->get_meta('_kwb_referrer'),'self referral attributed');
$_COOKIE['kwb_ref']=kwbf_cookie($owner).'tampered';
$bad=kwbf_order($pid,'tampered@example.org');kwbf_assert(!$bad->get_meta('_kwb_referrer'),'tampered cookie attributed');
$_COOKIE['kwb_ref']=kwbf_cookie($owner,time()-1);
$expired=kwbf_order($pid,'expired@example.org');kwbf_assert(!$expired->get_meta('_kwb_referrer'),'expired cookie attributed');
$_COOKIE['kwb_ref']=array('invalid');
$array=kwbf_order($pid,'array@example.org');kwbf_assert(!$array->get_meta('_kwb_referrer'),'array cookie attributed');
$_COOKIE['kwb_ref']=kwbf_cookie($owner);
$changed=kwbf_order($pid,'before@example.org');$changed->set_billing_email('after@example.org');$changed->save();$changed->update_status('completed');
kwbf_assert(count(KWB_Rewards::qualified($owner,$policy))===2,'changed attribution email qualified');
$a->update_status('refunded');
kwbf_assert(count(KWB_Rewards::qualified($owner,$policy))===1,'refunded guest remained eligible');
$coupon=new WC_Coupon($coupons[0]->ID);wp_set_current_user($owner);
kwbf_assert(!KWB_Rewards::valid(true,$coupon),'guest refund did not revoke unredeemed reward');
// Sanitized HTML remains rich text while removing active content.
$clean=KWB_Messages::clean('<h2>Hello</h2><script>alert(1)</script><a href="javascript:alert(1)">Link</a><img src="https://example.org/x.png" onerror="alert(1)">');
kwbf_assert(strpos($clean,'<h2>')!==false && strpos($clean,'<script')===false && strpos($clean,'javascript:')===false && strpos($clean,'onerror=')===false,'template HTML sanitization failed');
kwbf_assert(strpos(KWB_Messages::body(array('html'=>true,'message'=>'<strong>Saved</strong>')),'<strong>Saved</strong>')!==false,'designer markup lost');
kwbf_assert(strpos(KWB_Messages::body(array('message'=>'<strong>Legacy</strong>')),'&lt;strong&gt;')!==false,'legacy plain-text message semantics changed');
$admin=get_users(array('role'=>'administrator','number'=>1))[0];wp_set_current_user($admin->ID);
$multi_policy=KWB_Rewards::sanitize(array('products'=>array($pid,$pid+999,$pid)));
kwbf_assert(count($multi_policy['products'])===2&&KWB_Rewards::allows_product($multi_policy,$pid)&&!KWB_Rewards::allows_product($multi_policy,$pid+1),'multi-workshop restrictions failed');
$one_policy=KWB_Rewards::sanitize(array('products'=>array($pid)));
$old_policy=KWB_Rewards::sanitize(array('product'=>$pid));
kwbf_assert(KWB_Rewards::policy_key($one_policy)===KWB_Rewards::policy_key($old_policy),'single-workshop UI changed historical policy identity');
$campaign=KWB_Campaigns::create(array('kind'=>'loyalty','emails'=>array('guest-a@example.org','guest-b@example.org','guest-b@example.org'),'email'=>'','bookings'=>1,'workshops'=>1,'policy'=>$policy,'subject'=>'Test','message'=>'Test','image'=>0),wp_generate_uuid4());
KWB_Campaigns::prepare($campaign);global $wpdb;
kwbf_assert((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}kwb_deliveries WHERE campaign=%s",$campaign))===2,'explicit multi-email audience was broadened or duplicated');
update_option('kwb_message_templates',array('demo'=>array('name'=>'Demo','subject'=>'Saved subject','message'=>'<h2>Saved body</h2>')));
$_GET=array('template'=>'demo');ob_start();KWB_Dashboard::message_fields();$html=ob_get_clean();
kwbf_assert(strpos($html,'Saved subject')!==false&&strpos($html,'Saved body')!==false,'saved template not loaded into composer');
// Mutation entry points must reject missing nonces and unauthorized roles before writing.
$die=static function(){return static function(){throw new RuntimeException('blocked');};};add_filter('wp_die_handler',$die,999);
$_POST=array('name'=>'Injected','subject'=>'Injected','message'=>'Injected');$_REQUEST=array();$blocked=false;
try { KWB_Messages::save(); } catch(RuntimeException $e) { $blocked='blocked'===$e->getMessage(); }
kwbf_assert($blocked&&count(KWB_Messages::templates())===1,'template save accepted missing nonce');
wp_set_current_user($owner);$blocked=false;
try { KWB_Messages::save(); } catch(RuntimeException $e) { $blocked='blocked'===$e->getMessage(); }
kwbf_assert($blocked,'customer reached template mutation');
remove_filter('wp_die_handler',$die,999);wp_set_current_user($admin->ID);$_POST=array();$_REQUEST=array();
// Avoid network requests during the modal check.
set_site_transient('kwb_github_release',array('version'=>'1.0.0','fetched_at'=>time()),MINUTE_IN_SECONDS);
$details=KWB_GitHub_Updater::details(false,'plugin_information',(object)array('slug'=>KWB_GitHub_Updater::SLUG));
foreach(array('description','installation','faq','screenshots','changelog','support') as $section){kwbf_assert(!empty($details->sections[$section]),'missing modal section '.$section);}
echo "family-smoke-ok\n";
