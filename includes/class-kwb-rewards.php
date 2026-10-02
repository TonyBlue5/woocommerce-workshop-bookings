<?php
if ( ! defined('ABSPATH') ) { exit; }

final class KWB_Rewards {
 public static function init() {
  add_action('template_redirect', array(__CLASS__,'capture'));
  add_action('user_register', array(__CLASS__,'register_friend'));
  add_action('woocommerce_order_status_completed', array(__CLASS__,'qualify'));
  add_filter('woocommerce_coupon_discount_types', array(__CLASS__,'types'));
  add_filter('woocommerce_product_coupon_types', static function($types) { $types[]='kwb_reward'; return $types; });
  add_filter('woocommerce_coupon_is_valid', array(__CLASS__,'valid'), 10, 3);
  add_filter('woocommerce_coupon_is_valid_for_product', array(__CLASS__,'valid_product'), 10, 4);
  add_filter('woocommerce_coupon_get_discount_amount', array(__CLASS__,'discount'), 10, 5);
 }
 public static function settings() {
  return wp_parse_args((array)get_option('kwb_referral_settings',array()),array('enabled'=>0,'friends'=>2,'percent'=>100,'months'=>1,'mode'=>'monthly','product'=>0,'expiry'=>90));
 }
 public static function sanitize($input) {
  return array('enabled'=>empty($input['enabled'])?0:1,
   'friends'=>max(1,min(100,absint($input['friends']??2))),
   'percent'=>max(1,min(100,(float)($input['percent']??100))),
   'months'=>max(1,min(12,absint($input['months']??1))),
   'mode'=>in_array($input['mode']??'',array('monthly','single','any'),true)?$input['mode']:'monthly',
   'product'=>absint($input['product']??0), 'expiry'=>max(1,min(365,absint($input['expiry']??90))));
 }
 public static function types($types) { $types['kwb_reward']=KWB_Dashboard::t('Workshop reward','Ανταμοιβή εργαστηρίου'); return $types; }
 public static function link($user_id) {
  $token=get_user_meta($user_id,'_kwb_referral_token',true);
  if (!$token) {
   add_user_meta($user_id,'_kwb_referral_token',wp_generate_password(32,false,false),true);
   $token=get_user_meta($user_id,'_kwb_referral_token',true);
  }
  return add_query_arg('kwb_ref',$token,home_url('/'));
 }
 public static function capture() {
  if (is_user_logged_in() || !self::settings()['enabled'] || empty($_GET['kwb_ref']) || !empty($_COOKIE['kwb_ref'])) { return; }
  $token=sanitize_text_field(wp_unslash($_GET['kwb_ref']));
  if (!preg_match('/^[a-zA-Z0-9]{32}$/',$token)) { return; }
  $users=get_users(array('meta_key'=>'_kwb_referral_token','meta_value'=>$token,'number'=>1,'fields'=>'ID'));
  if (!$users) { return; }
  $payload=(int)$users[0].'|'.(time()+30*DAY_IN_SECONDS);
  $value=$payload.'|'.hash_hmac('sha256',$payload,wp_salt('auth'));
  wc_setcookie('kwb_ref',$value,time()+30*DAY_IN_SECONDS,is_ssl(),true);
  nocache_headers();
 }
 public static function register_friend($user_id) {
  if (!self::settings()['enabled']) { return; }
  $parts=explode('|',sanitize_text_field(wp_unslash($_COOKIE['kwb_ref']??'')));
  if (count($parts)!==3 || (int)$parts[1]<time() || !hash_equals(hash_hmac('sha256',$parts[0].'|'.$parts[1],wp_salt('auth')),$parts[2])) { return; }
  $referrer=get_userdata(absint($parts[0])); $friend=get_userdata($user_id);
  if (!$friend || !$referrer || $referrer->ID===$friend->ID || strtolower($friend->user_email)===strtolower($referrer->user_email)) { return; }
  add_user_meta($user_id,'_kwb_referrer',array('id'=>$referrer->ID,'policy'=>self::settings()),true);
 }
 public static function eligible_order($order,$policy) {
  if (!$order || !$order->has_status('completed')) { return false; }
  foreach ($order->get_items() as $item) {
   $type=KWB_Commercial::type($item);
   if (!$type || ('any'!==$policy['mode'] && $type!==$policy['mode']) || ($policy['product'] && (int)$item->get_product_id()!==(int)$policy['product'])) { continue; }
   if (KWB_Commercial::net($order,$item)>0 && !$order->get_total_refunded_for_item($item->get_id()) && !$order->get_qty_refunded_for_item($item->get_id())) { return true; }
  }
  return false;
 }
 public static function qualify($order_id) {
  global $wpdb;
  $order=wc_get_order($order_id);
  if (!$order || !$order->get_customer_id()) { return; }
  $friend=$order->get_customer_id();
  $ref=(array)get_user_meta($friend,'_kwb_referrer',true);
  $owner=get_userdata(absint($ref['id']??0));
  if (!$owner || $owner->ID===$friend || !isset($ref['policy']) || !self::eligible_order($order,$ref['policy'])) { return; }
  if (strtolower($owner->user_email)===strtolower($order->get_billing_email()) || !is_email($order->get_billing_email())) { return; }
  $lock='reward_'.$owner->ID;
  if (!KWB_Commercial::lock($lock)) { throw new RuntimeException('Reward lock unavailable; retry order completion.'); }
  try {
   $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$wpdb->prefix}kwb_referrals (friend_id,email_hash,referrer_id,order_id,policy) VALUES (%d,%s,%d,%d,%s)", $friend,hash('sha256',strtolower($order->get_billing_email())),$owner->ID,$order_id,wp_json_encode($ref['policy'])));
   $existing=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}kwb_referrals WHERE friend_id=%d",$friend));
   if ($existing && (int)$existing->referrer_id===$owner->ID && !self::eligible_order(wc_get_order($existing->order_id),json_decode($existing->policy,true))) {
    // A new paid booking can replace a refunded qualifying purchase, but never count the friend twice.
    $wpdb->update($wpdb->prefix.'kwb_referrals',array('order_id'=>$order_id,'email_hash'=>hash('sha256',strtolower($order->get_billing_email()))),array('friend_id'=>$friend));
   }
   $rows=self::qualified($owner->ID,$ref['policy']);
   $tiers=(int)floor(count($rows)/$ref['policy']['friends']);
   for ($tier=1;$tier<=$tiers;$tier++) {
    $key='referral:'.$owner->ID.':'.self::policy_key($ref['policy']).':'.$tier;
    $coupon=self::coupon($key,$owner->user_email,$ref['policy'],$owner->ID,$tier*$ref['policy']['friends']);
    $coupon->update_meta_data('_kwb_milestone',$tier*$ref['policy']['friends']);
    $coupon->save();
   }
  } finally { KWB_Commercial::unlock($lock); }
 }
 public static function policy_key($policy) { return hash('sha256',wp_json_encode($policy)); }
 public static function qualified($owner,$policy) {
  global $wpdb;
  $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}kwb_referrals WHERE referrer_id=%d",$owner));
  return array_values(array_filter($rows,static function($row)use($policy){
   return self::policy_key(json_decode($row->policy,true))===self::policy_key($policy) && self::eligible_order(wc_get_order($row->order_id),$policy);
  }));
 }
 public static function coupon($key,$email,$policy,$owner=0,$milestone=0) {
  $code='kwb-'.substr(hash_hmac('sha256',$key,wp_salt('auth')),0,24);
  $existing=wc_get_coupon_id_by_code($code);
  if ($existing) { return new WC_Coupon($existing); }
  $coupon=new WC_Coupon();
  $coupon->set_code($code); $coupon->set_discount_type('kwb_reward');
  $coupon->set_amount($policy['percent']); $coupon->set_individual_use(true);
  $coupon->set_usage_limit(1); $coupon->set_usage_limit_per_user(1); $coupon->set_limit_usage_to_x_items(1);
  $coupon->set_email_restrictions(array(strtolower($email)));
  $coupon->set_date_expires(time()+$policy['expiry']*DAY_IN_SECONDS);
  $coupon->update_meta_data('_kwb_policy',$policy);
  $coupon->update_meta_data('_kwb_owner',absint($owner));
  $coupon->update_meta_data('_kwb_milestone',absint($milestone));
  $coupon->update_meta_data('_kwb_recipient',strtolower($email));
  $coupon->save();
  return $coupon;
 }
 public static function valid($valid,$coupon,$discounts=null) {
  if ('kwb_reward'!==$coupon->get_discount_type()) { return $valid; }
  $owner=absint($coupon->get_meta('_kwb_owner'));
  if ($owner && $owner!==get_current_user_id() && !(is_admin() && KWB_Commercial::can_manage())) { return false; }
  $milestone=absint($coupon->get_meta('_kwb_milestone'));
  if ($milestone && count(self::qualified($owner,$coupon->get_meta('_kwb_policy')))<$milestone) { return false; }
  return $valid;
 }
 public static function booking($values) {
  if ($values instanceof WC_Order_Item_Product) {
   $months=json_decode((string)$values->get_meta('_kwb_booking_months'),true);
   return array('type'=>KWB_Commercial::type($values),'months'=>$months?:array($values->get_meta('_kwb_booking_month')));
  }
  return is_array($values) ? ($values['kwb_booking']??array()) : array();
 }
 public static function valid_product($valid,$product,$coupon,$values) {
  if ('kwb_reward'!==$coupon->get_discount_type()) { return $valid; }
  $policy=$coupon->get_meta('_kwb_policy'); $booking=self::booking($values);
  $pid=$product->get_parent_id()?:$product->get_id();
  return $valid && !empty($booking['type']) && ('any'===$policy['mode'] || $booking['type']===$policy['mode']) && (!$policy['product'] || $pid===$policy['product']);
 }
 public static function discount($discount,$amount,$values,$single,$coupon) {
  if ('kwb_reward'!==$coupon->get_discount_type()) { return $discount; }
  $booking=self::booking($values); $policy=$coupon->get_meta('_kwb_policy');
  if (empty($booking['type'])) { return 0; }
  $factor=1;
  if ('monthly'===$booking['type']) {
   $months=max(1,count((array)($booking['months']??array())));
   $factor=min($months,$policy['months'])/$months;
  }
  return min($amount,max(0,$amount*$policy['percent']/100*$factor));
 }
}
