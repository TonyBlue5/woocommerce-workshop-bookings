<?php
if ( ! defined('ABSPATH') ) { exit; }

/** Private recipient queue. No customer addresses are exposed in bulk email headers. */
final class KWB_Campaigns {
 public static function init() {
  add_action('kwb_campaign_prepare',array(__CLASS__,'prepare'),10,1);
  add_action('kwb_campaign_send',array(__CLASS__,'send'),10,1);
 }
 public static function schedule($hook,$id) {
  if (function_exists('as_enqueue_async_action')) { as_enqueue_async_action($hook,array($id),'kwb-commercial',true); }
  elseif (!wp_next_scheduled($hook,array($id))) { wp_schedule_single_event(time()+10,$hook,array($id)); }
 }
 public static function create($config,$request_id) {
  if (!KWB_Commercial::can_manage()) { throw new RuntimeException('Forbidden'); }
  $id=sanitize_key($request_id);
  if (!preg_match('/^[a-f0-9-]{36}$/',$id)) { throw new InvalidArgumentException('Invalid request'); }
  // A resubmitted form cannot create a second campaign.
  $config['created']=time(); $config['author']=get_current_user_id(); $config['locale']=get_locale();
  add_option('kwb_campaign_'.$id,$config,'',false);
  self::schedule('kwb_campaign_prepare',$id);
  return $id;
 }
 public static function prepare($id) {
  global $wpdb;
  $config=get_option('kwb_campaign_'.$id);
  if (!$config || get_option('kwb_campaign_ready_'.$id)) { return; }
  if (!KWB_Commercial::lock('campaign_'.$id)) { throw new RuntimeException('Campaign is busy'); }
  try {
   if (get_option('kwb_campaign_ready_'.$id)) { return; }
   $recipients=array();
   if ('loyalty'===$config['kind']) {
    foreach (KWB_Commercial::customers() as $email=>$row) {
     if ($row['bookings'] >= $config['bookings'] && count($row['workshops']) >= $config['workshops'] && (!$config['email'] || $config['email']===$email)) { $recipients[$email]=true; }
    }
   } else {
    foreach (KWB_Commercial::orders(array('status'=>array('processing','completed','on-hold'))) as $order) {
     foreach ($order->get_items() as $item) {
      if ((int)$item->get_product_id()!==$config['product']) { continue; }
      foreach (KWB_Booking::item_occurrences($item) as $occ) {
       if ($occ['date']===$config['date']) { $recipients[strtolower($order->get_billing_email())]=true; }
      }
     }
    }
   }
   foreach (array_keys($recipients) as $email) {
    if (is_email($email)) {
     $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$wpdb->prefix}kwb_deliveries (campaign,email,state) VALUES (%s,%s,'pending')",$id,$email));
    }
   }
   update_option('kwb_campaign_ready_'.$id,1,false);
   self::schedule('kwb_campaign_send',$id);
  } finally { KWB_Commercial::unlock('campaign_'.$id); }
 }
 public static function send($id) {
  global $wpdb;
  $config=get_option('kwb_campaign_'.$id);
  if (!$config || !get_option('kwb_campaign_ready_'.$id)) { return; }
  $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}kwb_deliveries WHERE campaign=%s AND state='pending' ORDER BY id LIMIT 25",$id));
  switch_to_locale($config['locale']);
  try {
   foreach ($rows as $row) {
    $claimed=$wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}kwb_deliveries SET state='sending' WHERE id=%d AND state='pending'",$row->id));
    if (!$claimed) { continue; }
    try {
     $message='<p>'.nl2br(esc_html($config['message'])).'</p>';
     if (!empty($config['image'])) {
      $url=wp_get_attachment_image_url($config['image'],'large');
      if ($url) { $message.='<p><img src="'.esc_url($url).'" alt="" style="max-width:100%;height:auto"></p>'; }
     }
     if ('loyalty'===$config['kind']) {
      $coupon=KWB_Rewards::coupon('campaign:'.$id.':'.$row->email,$row->email,$config['policy']);
      $message.='<p><strong>'.esc_html($coupon->get_code()).'</strong></p><p>'.esc_html(KWB_Dashboard::terms($config['policy'])).'</p>';
     }
     $ok=wp_mail($row->email,$config['subject'],$message,array('Content-Type: text/html; charset=UTF-8'));
     $wpdb->update($wpdb->prefix.'kwb_deliveries',array('state'=>$ok?'sent':'failed'),array('id'=>$row->id));
    } catch (Throwable $error) {
     $wpdb->update($wpdb->prefix.'kwb_deliveries',array('state'=>'failed'),array('id'=>$row->id));
    }
   }
  } finally { restore_previous_locale(); }
  if ($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}kwb_deliveries WHERE campaign=%s AND state='pending'",$id))) {
   // Schedule a future action: the current unique action is still running.
   if (function_exists('as_schedule_single_action')) { as_schedule_single_action(time()+10,'kwb_campaign_send',array($id),'kwb-commercial'); }
   else { wp_schedule_single_event(time()+10,'kwb_campaign_send',array($id)); }
  }
 }
 public static function retry($id) {
  if (!KWB_Commercial::can_manage()) { return; }
  global $wpdb;
  $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}kwb_deliveries SET state='pending' WHERE campaign=%s AND state='failed'",$id));
  self::schedule('kwb_campaign_prepare',$id);
  self::schedule('kwb_campaign_send',$id);
 }
}
