<?php
/** Run with wp eval-file against an isolated WordPress/WooCommerce installation. */
if (!defined('ABSPATH')) { exit(1); }
function kwbc_assert($value,$message) { if (!$value) { throw new RuntimeException($message); } }
add_filter('pre_wp_mail',static function(){return true;});
$admin=get_users(array('role'=>'administrator','number'=>1))[0];
wp_set_current_user($admin->ID);
kwbc_assert(KWB_Commercial::can_manage(),'administrator denied');
$manager=wp_insert_user(array('user_login'=>'kwb-manager','user_pass'=>wp_generate_password(),'role'=>'shop_manager','user_email'=>'manager@example.org'));
wp_set_current_user($manager);kwbc_assert(KWB_Commercial::can_manage(),'shop manager denied');
$editor=wp_insert_user(array('user_login'=>'kwb-editor','user_pass'=>wp_generate_password(),'role'=>'editor','user_email'=>'editor@example.org'));
get_user_by('id',$editor)->add_cap('manage_woocommerce');
wp_set_current_user($editor);kwbc_assert(!KWB_Commercial::can_manage(),'custom manager capability must not bypass role gate');
$owner=wp_insert_user(array('user_login'=>'kwb-owner','user_pass'=>wp_generate_password(),'role'=>'customer','user_email'=>'owner@example.org'));
wp_set_current_user($owner);kwbc_assert(!KWB_Commercial::can_manage(),'customer accessed dashboard');
wp_set_current_user(0);kwbc_assert(!KWB_Commercial::can_manage(),'guest accessed dashboard');
wp_set_current_user($admin->ID);

$product=new WC_Product_Simple();$product->set_name('Commercial workshop');$product->set_regular_price(40);$product->set_virtual(true);$pid=$product->save();
update_post_meta($pid,'_kwb_enabled','yes');
update_post_meta($pid,'_kwb_monthly_enabled','yes');update_post_meta($pid,'_kwb_monthly_price','40');
$policy=KWB_Rewards::sanitize(array('enabled'=>1,'friends'=>2,'percent'=>100,'months'=>1,'mode'=>'monthly','product'=>$pid,'expiry'=>90));
update_option('kwb_referral_settings',$policy);
function kwbc_order($pid,$customer,$type,$total=80) {
 $order=wc_create_order(array('customer_id'=>$customer));
 $user=get_userdata($customer);$order->set_billing_email($user->user_email);
 $id=$order->add_product(wc_get_product($pid),1,array('subtotal'=>$total,'total'=>$total));
 $item=$order->get_item($id);$item->add_meta_data('_kwb_booking_type',$type);
 $item->add_meta_data('_kwb_booking_months',wp_json_encode(array('2030-10','2030-11')));
 $item->add_meta_data('_kwb_occurrences',wp_json_encode(array(array('id'=>KWB_Booking::occ_id($pid,'2030-10-08','12:00','13:00'),'date'=>'2030-10-08','start'=>'12:00','end'=>'13:00','capacity'=>10))));$item->save();
 $order->calculate_totals();$order->set_status('completed');$order->save();return $order;
}
$friends=array();$orders=array();
for($i=1;$i<=2;$i++) {
 $friend=wp_insert_user(array('user_login'=>'kwb-friend-'.$i,'user_pass'=>wp_generate_password(),'role'=>'customer','user_email'=>'friend'.$i.'@example.org'));
 $friends[]=$friend;add_user_meta($friend,'_kwb_referrer',array('id'=>$owner,'policy'=>$policy));
 $orders[]=kwbc_order($pid,$friend,'monthly');
}
kwbc_assert(count(KWB_Rewards::qualified($owner,$policy))===2,'two completed friends must qualify');
KWB_Rewards::qualify($orders[0]->get_id());
$coupons=get_posts(array('post_type'=>'shop_coupon','meta_key'=>'_kwb_owner','meta_value'=>$owner,'numberposts'=>-1));
kwbc_assert(count($coupons)===1,'duplicate completion issued a second reward');
$coupon=new WC_Coupon($coupons[0]->ID);
wp_set_current_user($owner);kwbc_assert(KWB_Rewards::valid(true,$coupon),'owner cannot use earned coupon');
wp_set_current_user($friends[0]);kwbc_assert(!KWB_Rewards::valid(true,$coupon),'another customer can use reward');

// Refunded qualifying items revoke eligibility immediately without mutating booking metadata.
$order=$orders[0];$items=$order->get_items();$item=reset($items);$metadata=$item->get_meta('_kwb_occurrences');
$refund=wc_create_refund(array('order_id'=>$order->get_id(),'amount'=>10,'line_items'=>array($item->get_id()=>array('qty'=>0,'refund_total'=>10,'refund_tax'=>array()))));
kwbc_assert(!is_wp_error($refund),'refund setup failed');
wp_set_current_user($owner);kwbc_assert(!KWB_Rewards::valid(true,$coupon),'partial refund did not revoke qualifying reward');
kwbc_assert(wc_get_order($order->get_id())->get_item($item->get_id())->get_meta('_kwb_occurrences')===$metadata,'refund changed booking metadata');
$report=KWB_Commercial::report();$row=$report[$pid.':'.get_woocommerce_currency()];
kwbc_assert($row['monthly']===2 && abs($row['monthly_revenue']-150)<0.01,'net revenue after partial refund incorrect');

// Custom coupons apply to one participant only, proportionally across selected months.
wp_set_current_user($admin->ID);
$loyalty=KWB_Rewards::coupon('test-loyalty','owner@example.org',$policy);
$values=array('kwb_booking'=>array('type'=>'monthly','months'=>array('2030-10','2030-11')));
kwbc_assert(KWB_Rewards::discount(0,80,$values,true,$loyalty)===40.0,'one free month of two must discount half');
kwbc_assert(KWB_Rewards::discount(0,80,array(),true,$loyalty)===0,'non-booking got reward');
kwbc_assert(!KWB_Rewards::valid_product(true,$product,$loyalty,array('kwb_booking'=>array('type'=>'single'))),'monthly coupon applied to single');
kwbc_assert($loyalty->get_usage_limit()===1 && $loyalty->get_limit_usage_to_x_items()===1,'coupon usage caps missing');

// Full WooCommerce cart discount engine, including quantities and mixed contents.
wp_set_current_user($owner);
WC()->session=new WC_Session_Handler();WC()->session->init();
WC()->customer=new WC_Customer($owner);WC()->customer->set_billing_email('owner@example.org');
WC()->cart=new WC_Cart();
remove_filter('woocommerce_add_to_cart_validation',array('KWB_Booking','validate'),10);
$cart_product=wc_get_product($pid);$cart_product->set_price(80);
$key=WC()->cart->add_to_cart($pid,3,0,array(),$values);
kwbc_assert((bool)$key,'test booking could not be added to the cart: '.wp_json_encode(wc_get_notices()));
$cart=WC()->cart->get_cart();$cart[$key]['data']->set_price(80);WC()->cart->set_cart_contents($cart);
kwbc_assert(WC()->cart->apply_coupon($loyalty->get_code()),'reward coupon rejected by cart');
WC()->cart->calculate_totals();
kwbc_assert(abs(WC()->cart->get_discount_total()-40)<0.01,'reward must cover one month for one participant, not all three');

// Recipient deduplication, retry idempotency and one email per customer.
wp_set_current_user($admin->ID);$sent=array();
add_filter('pre_wp_mail',static function($return,$args)use(&$sent){$sent[]=$args;return true;},20,2);
$config=array('kind'=>'broadcast','product'=>$pid,'date'=>'2030-10-08','subject'=>'Cancelled','message'=>'<script>untrusted</script>','image'=>0);
$campaign=KWB_Campaigns::create($config,wp_generate_uuid4());
KWB_Campaigns::prepare($campaign);KWB_Campaigns::prepare($campaign);
KWB_Campaigns::send($campaign);KWB_Campaigns::send($campaign);
kwbc_assert(count($sent)===2,'broadcast duplicated or missed recipients');
kwbc_assert(strpos($sent[0]['message'],'<script>')===false,'broadcast failed to escape message');
kwbc_assert(strpos($sent[0]['to'],',')===false,'broadcast exposed recipient list');
kwbc_assert(KWB_Commercial::csv_cell('=HYPERLINK("x")')[0]==="'",'CSV formula injection');
kwbc_assert(KWB_Commercial::csv_cell("\t=1+1")[0]==="'",'CSV whitespace formula injection');
kwbc_assert(KWB_Commercial::csv_cell('Normal')==='Normal','CSV corrupted normal text');

// Failed mail can be retried without issuing another coupon.
$fail_mail=true;
$mail_failure=static function($return)use(&$fail_mail){return $fail_mail?false:$return;};
add_filter('pre_wp_mail',$mail_failure,30);
$loyalty_config=array('kind'=>'loyalty','policy'=>$policy,'email'=>'friend1@example.org','bookings'=>1,'workshops'=>1,'subject'=>'Thank you','message'=>'Your reward','image'=>0);
$loyalty_campaign=KWB_Campaigns::create($loyalty_config,wp_generate_uuid4());KWB_Campaigns::prepare($loyalty_campaign);KWB_Campaigns::send($loyalty_campaign);
$before_coupons=get_posts(array('post_type'=>'shop_coupon','meta_key'=>'_kwb_recipient','meta_value'=>'friend1@example.org','numberposts'=>-1));
$fail_mail=false;KWB_Campaigns::retry($loyalty_campaign);KWB_Campaigns::send($loyalty_campaign);
$after_coupons=get_posts(array('post_type'=>'shop_coupon','meta_key'=>'_kwb_recipient','meta_value'=>'friend1@example.org','numberposts'=>-1));
kwbc_assert(count($before_coupons)===1 && count($after_coupons)===1,'mail retry created duplicate loyalty coupons');
global $wpdb;
kwbc_assert($wpdb->get_var($wpdb->prepare("SELECT state FROM {$wpdb->prefix}kwb_deliveries WHERE campaign=%s",$loyalty_campaign))==='sent','failed mail did not recover');
remove_filter('pre_wp_mail',$mail_failure,30);

// Single revenue stays separate, including a different order currency.
$other_currency='USD'===get_woocommerce_currency()?'EUR':'USD';
$single_order=kwbc_order($pid,$owner,'single',14);$single_order->set_currency($other_currency);$single_order->save();
$report=KWB_Commercial::report();kwbc_assert($report[$pid.':'.$other_currency]['single']===1 && abs($report[$pid.':'.$other_currency]['single_revenue']-14)<0.01 && isset($report[$pid.':'.get_woocommerce_currency()]),'single revenue/currency separation failed');
$single_order->set_date_created(time()-100*DAY_IN_SECONDS);$single_order->save();
kwbc_assert(isset(KWB_Commercial::inactive(90)['owner@example.org']),'inactive customer missing');
$recent_order=kwbc_order($pid,$owner,'single',14);
kwbc_assert(!isset(KWB_Commercial::inactive(90)['owner@example.org']),'recent customer incorrectly exported as inactive');

// Real POST handler rejects missing nonce before changing settings.
$die_handler=static function(){return static function(){throw new RuntimeException('expected-wp-die');};};
add_filter('wp_die_handler',$die_handler,999);
$_POST=array('op'=>'settings','friends'=>99);$_REQUEST=array();$blocked=false;
try { KWB_Dashboard::handle(); } catch (RuntimeException $e) { $blocked='expected-wp-die'===$e->getMessage(); }
kwbc_assert($blocked && KWB_Rewards::settings()['friends']===2,'admin POST without nonce changed settings');
wp_set_current_user($editor);$blocked=false;
try { KWB_Dashboard::handle(); } catch (RuntimeException $e) { $blocked='expected-wp-die'===$e->getMessage(); }
kwbc_assert($blocked,'unauthorized role reached admin POST action');
remove_filter('wp_die_handler',$die_handler,999);$_POST=array();wp_set_current_user($admin->ID);

// Signed registration attribution rejects tampering and accepts an unexpired signature.
$payload=$owner.'|'.(time()+DAY_IN_SECONDS);
$_COOKIE['kwb_ref']=$payload.'|invalid';
$unsigned=wp_insert_user(array('user_login'=>'unsigned-friend','user_pass'=>wp_generate_password(),'user_email'=>'unsigned@example.org'));
kwbc_assert(!get_user_meta($unsigned,'_kwb_referrer',true),'tampered attribution accepted');
$_COOKIE['kwb_ref']=$payload.'|'.hash_hmac('sha256',$payload,wp_salt('auth'));
$signed=wp_insert_user(array('user_login'=>'signed-friend','user_pass'=>wp_generate_password(),'user_email'=>'signed@example.org'));
kwbc_assert((int)get_user_meta($signed,'_kwb_referrer',true)['id']===$owner,'signed registration lost attribution');
unset($_COOKIE['kwb_ref']);

// Cancelling a date invalidates an already populated cart without deleting the order's occurrences.
$cart=WC()->cart->get_cart();$cart[$key]['kwb_booking']['occurrences']=array(array('date'=>'2030-10-08'));WC()->cart->set_cart_contents($cart);
update_post_meta($pid,'_kwb_blackouts','2030-10-08');wc_clear_notices();KWB_Commercial::check_cancelled_cart();
kwbc_assert(wc_notice_count('error')>0,'cancelled date still accepted in cart');
wc_clear_notices();
$before_mail=count($sent);$paid_items=$orders[1]->get_items();$paid_item=reset($paid_items);$occ=KWB_Booking::item_occurrences($paid_item)[0];
KWB_RSVP::send_reminder($orders[1]->get_id(),$paid_item->get_id(),$occ['id']);
kwbc_assert(count($sent)===$before_mail,'cancelled session reminder was sent');

// Customer output does not reveal another customer's bookings or coupon.
wp_set_current_user($friends[1]);ob_start();KWB_Dashboard::account();$html=ob_get_clean();
kwbc_assert(strpos($html,$coupon->get_code())===false,'account leaked another customer coupon');
wp_set_current_user($admin->ID);ob_start();KWB_Dashboard::render();$english=ob_get_clean();
add_filter('locale',static function(){return 'el';});add_filter('determine_locale',static function(){return 'el';});
ob_start();KWB_Dashboard::render();$greek=ob_get_clean();
kwbc_assert(strpos($english,'Workshop performance')!==false && strpos($greek,'Απόδοση εργαστηρίων')!==false,'bilingual admin rendering failed');
if (getenv('GITHUB_WORKSPACE')) {
 $dir=getenv('GITHUB_WORKSPACE').'/ui-preview';wp_mkdir_p($dir);
 $style='<style>'.file_get_contents(KWB_PLUGIN_DIR.'assets/kwb-dashboard.css').'</style>';
 foreach(array('admin-en'=>$english,'admin-el'=>$greek,'account-en'=>$html) as $name=>$markup) {
  file_put_contents($dir.'/'.$name.'.html','<!doctype html><html lang="'.(strpos($name,'-el')!==false?'el':'en').'"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'.$style.'<body style="margin:20px;background:#f3f5f4">'.$markup.'</body></html>');
 }
}
echo "commercial-smoke-ok\n";
