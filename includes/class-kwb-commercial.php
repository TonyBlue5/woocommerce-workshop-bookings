<?php
/** Shared commercial data access. Existing booking metadata remains authoritative. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class KWB_Commercial {
 public static function init() {
  self::install();
  KWB_Rewards::init();
  KWB_Campaigns::init();
  KWB_Dashboard::init();
  add_action('woocommerce_check_cart_items',array(__CLASS__,'check_cancelled_cart'));
 }

 public static function check_cancelled_cart() {
  if (!WC()->cart) { return; }
  foreach (WC()->cart->get_cart() as $item) {
   $blackouts=KWB_Booking::blackouts($item['product_id']);
   foreach (($item['kwb_booking']['occurrences']??array()) as $occ) {
    if (isset($blackouts[$occ['date']])) {
     wc_add_notice(KWB_Dashboard::t('A session in your cart has been cancelled. Remove the booking and select available dates.','Μια συνάντηση στο καλάθι σας ακυρώθηκε. Αφαιρέστε την κράτηση και επιλέξτε διαθέσιμες ημερομηνίες.'),'error');
     break;
    }
   }
  }
 }

 public static function install() {
  if ( '3' === get_option('kwb_commercial_schema') ) { return; }
  global $wpdb;
  require_once ABSPATH . 'wp-admin/includes/upgrade.php';
  $charset = $wpdb->get_charset_collate();
  // Keep every attributed purchase. Eligibility is deduplicated by billing email,
  // referrer and policy when read, never by a guest's zero customer ID.
  dbDelta("CREATE TABLE {$wpdb->prefix}kwb_referral_orders (
   order_id bigint(20) unsigned NOT NULL,
   friend_id bigint(20) unsigned NOT NULL DEFAULT 0,
   email_hash varchar(64) NOT NULL,
   referrer_id bigint(20) unsigned NOT NULL,
   policy_key varchar(64) NOT NULL,
   policy longtext NOT NULL,
   PRIMARY KEY  (order_id),
   KEY referral_policy (referrer_id,policy_key),
   KEY email_hash (email_hash)
  ) $charset;");
  dbDelta("CREATE TABLE {$wpdb->prefix}kwb_guest_referrals (
   email_hash varchar(64) NOT NULL,
   friend_id bigint(20) unsigned NOT NULL DEFAULT 0,
   referrer_id bigint(20) unsigned NOT NULL,
   order_id bigint(20) unsigned NOT NULL,
   policy longtext NOT NULL,
   PRIMARY KEY  (email_hash),
   KEY referrer_id (referrer_id)
  ) $charset;");
  dbDelta("CREATE TABLE {$wpdb->prefix}kwb_referrals (
   friend_id bigint(20) unsigned NOT NULL,
   email_hash varchar(64) NOT NULL,
   referrer_id bigint(20) unsigned NOT NULL,
   order_id bigint(20) unsigned NOT NULL,
   policy longtext NOT NULL,
   PRIMARY KEY  (friend_id),
   UNIQUE KEY email_hash (email_hash),
   KEY referrer_id (referrer_id)
  ) $charset;");
  dbDelta("CREATE TABLE {$wpdb->prefix}kwb_deliveries (
   id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
   campaign varchar(36) NOT NULL,
   email varchar(200) NOT NULL,
   state varchar(20) NOT NULL DEFAULT 'pending',
   PRIMARY KEY  (id),
   UNIQUE KEY recipient (campaign,email),
   KEY state (state)
  ) $charset;");
  if ( $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}kwb_referrals'") && $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}kwb_deliveries'") && $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}kwb_guest_referrals'") ) {
   // Additive and restartable: retain both legacy tables and their original rows.
   foreach(array('kwb_referrals','kwb_guest_referrals') as $legacy) {
    $copied=$wpdb->query("INSERT IGNORE INTO {$wpdb->prefix}kwb_referral_orders (order_id,friend_id,email_hash,referrer_id,policy_key,policy) SELECT order_id,friend_id,email_hash,referrer_id,SHA2(policy,256),policy FROM {$wpdb->prefix}{$legacy}");
    if(false===$copied) { return; }
   }
   update_option('kwb_commercial_schema', '3', false);
   delete_option('kwb_referral_reconcile_page');
  }
 }

 public static function can_manage() {
  return current_user_can('manage_woocommerce') && (bool) array_intersect(array('administrator','shop_manager'), (array) wp_get_current_user()->roles);
 }

 /** Iterate bounded pages through WooCommerce's storage-independent API. */
 public static function orders($args = array()) {
  $args = wp_parse_args($args, array('status'=>array('processing','completed','refunded'), 'type'=>'shop_order'));
  $args['limit'] = 100;
  $args['orderby'] = 'ID';
  $args['order'] = 'ASC';
  for ($page = 1; ; $page++) {
   $args['page'] = $page;
   $orders = wc_get_orders($args);
   foreach ($orders as $order) { yield $order; }
   if (count($orders) < 100) { break; }
  }
 }

 public static function type($item) {
  $type = $item->get_meta('_kwb_booking_type', true);
  return in_array($type, array('monthly','single'), true) ? $type : '';
 }

 public static function net($order, $item) {
  if ($order->has_status('refunded')) { return 0.0; }
  return max(0, (float) $item->get_total() - (float) $order->get_total_refunded_for_item($item->get_id()));
 }

 public static function report($from = '', $to = '') {
  $args = array();
  if ($from && $to) { $args['date_created'] = $from . '...' . $to; }
  $rows = array();
  foreach (self::orders($args) as $order) {
   foreach ($order->get_items() as $item) {
    $type = self::type($item);
    if (!$type) { continue; }
    $key = $item->get_product_id() . ':' . $order->get_currency();
    if (!isset($rows[$key])) {
     $rows[$key] = array('name'=>$item->get_name(), 'currency'=>$order->get_currency(), 'monthly'=>0, 'single'=>0, 'monthly_revenue'=>0.0, 'single_revenue'=>0.0);
    }
    // Counts are booking lines (not seats); refunded orders remain visible with zero net revenue.
    $rows[$key][$type]++;
    $rows[$key][$type . '_revenue'] += self::net($order, $item);
   }
  }
  return $rows;
 }

 /** Sales use order dates; occupancy uses session dates, regardless of purchase date. */
 public static function performance($from,$to,$product=0) {
  $start=new DateTimeImmutable($from,wp_timezone());$end=new DateTimeImmutable($to,wp_timezone());
  $days=(int)$start->diff($end)->days+1;
  if($end<$start || $days>366) { return array(); }
  $previous=$start->modify('-'.$days.' days')->format('Y-m-d');
  $rows=array();$slots=array();$products=array();
  $seed=static function($pid,$currency,$name) use (&$rows) {
   $key=$pid.':'.$currency;
   if(!isset($rows[$key])) { $rows[$key]=array('product'=>$pid,'name'=>$name,'currency'=>$currency,'bookings'=>0,'monthly'=>0,'single'=>0,'participants'=>0,'revenue'=>0.0,'previous_revenue'=>0.0,'previous_bookings'=>0); }
   return $key;
  };
  $ids=get_posts(array('post_type'=>'product','post_status'=>array('publish','private'),'numberposts'=>-1,'fields'=>'ids','meta_key'=>KWB_Booking::META_ENABLED,'meta_value'=>'yes'));
  foreach($ids as $pid) { if(!$product || (int)$pid===(int)$product) { $products[$pid]=true;$seed($pid,get_woocommerce_currency(),get_the_title($pid)); } }
  foreach(self::orders(array('status'=>array('processing','completed','refunded'))) as $order) {
   $created=$order->get_date_created();if(!$created) { continue; }$date=wp_date('Y-m-d',$created->getTimestamp(),wp_timezone());
   foreach($order->get_items() as $item) {
    $type=self::type($item);$pid=$item->get_product_id();
    if(!$type || ($product && (int)$pid!==(int)$product)) { continue; }
    $products[$pid]=true;
    $qty=$order->has_status('refunded')?0:max(0,(int)$item->get_quantity()-absint($order->get_qty_refunded_for_item($item->get_id())));
    if($date>=$previous && $date<=$to) {
     $key=$seed($pid,$order->get_currency(),$item->get_name());$net=self::net($order,$item);
     if($date>=$from) { $rows[$key]['bookings']++;$rows[$key][$type]++;$rows[$key]['participants']+=$qty;$rows[$key]['revenue']+=$net; }
     else { $rows[$key]['previous_bookings']++;$rows[$key]['previous_revenue']+=$net; }
    }
    foreach(KWB_Booking::item_occurrences($item) as $occ) {
     if($occ['date']<$from || $occ['date']>$to || isset(KWB_Booking::blackouts($pid)[$occ['date']])) { continue; }
     if(!isset($slots[$pid][$occ['id']])) { $slots[$pid][$occ['id']]=array('capacity'=>0,'used'=>0); }
     $slots[$pid][$occ['id']]['capacity']=max($slots[$pid][$occ['id']]['capacity'],(int)($occ['capacity']??0));
     if(!KWB_Booking::staff_released($item,$occ['id']) && !(KWB_Settings::get('release_on_no',1)&&KWB_Booking::occurrence_declined($item,$occ['id']))) { $slots[$pid][$occ['id']]['used']+=$qty; }
    }
   }
  }
  foreach($products as $pid=>$unused) {
   for($month=$start->modify('first day of this month');$month<=$end;$month=$month->modify('+1 month')) {
    foreach(KWB_Booking::month_occurrences($pid,$month->format('Y-m'),false) as $occ) {
     if($occ['date']<$from || $occ['date']>$to) { continue; }
     if(!isset($slots[$pid][$occ['id']])) { $slots[$pid][$occ['id']]=array('capacity'=>(int)$occ['capacity'],'used'=>0); }
    }
   }
  }
  foreach($rows as &$row) {
   $capacity=0;$used=0;foreach($slots[$row['product']]??array() as $slot) { $capacity+=$slot['capacity'];$used+=$slot['used']; }
   $row['capacity']=$capacity;$row['occupied']=$used;$row['occupancy']=$capacity?100*$used/$capacity:null;
   $row['per_booking']=$row['bookings']?$row['revenue']/$row['bookings']:null;
   $row['per_participant']=$row['participants']?$row['revenue']/$row['participants']:null;
   $row['trend']=$row['previous_revenue']>0?100*($row['revenue']-$row['previous_revenue'])/$row['previous_revenue']:null;
  }unset($row);
  uasort($rows,static function($a,$b){return strcmp($a['currency'],$b['currency'])?:($b['revenue']<=>$a['revenue']);});
  return $rows;
 }

 public static function customers() {
  $rows = array();
  foreach (self::orders(array('status'=>array('processing','completed'))) as $order) {
   $email = strtolower(sanitize_email($order->get_billing_email()));
   if (!is_email($email)) { continue; }
   foreach ($order->get_items() as $item) {
    if (!self::type($item) || self::net($order,$item) <= 0) { continue; }
    if (!isset($rows[$email])) {
     $rows[$email] = array('email'=>$email, 'name'=>$order->get_formatted_billing_full_name(), 'last'=>0, 'bookings'=>0, 'workshops'=>array());
    }
    $rows[$email]['last'] = max($rows[$email]['last'], $order->get_date_created()->getTimestamp());
    $rows[$email]['bookings']++;
    $rows[$email]['workshops'][$item->get_product_id()] = true;
   }
  }
  return $rows;
 }

 public static function inactive($days) {
  $cutoff = time() - max(1,absint($days)) * DAY_IN_SECONDS;
  return array_filter(self::customers(), static function($row) use ($cutoff) { return $row['last'] < $cutoff; });
 }

 public static function csv_cell($value) {
  $value = (string) $value;
  return preg_match('/^[\s]*[=+@\-\t\r\n]/u', $value) ? "'" . $value : $value;
 }

 /** Database-owned locks are released even if the request terminates. */
 public static function lock($name) {
  global $wpdb;
  return '1' === (string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', 'kwb_' . md5($wpdb->prefix . $name)));
 }
 public static function unlock($name) {
  global $wpdb;
  $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', 'kwb_' . md5($wpdb->prefix . $name)));
 }
}
