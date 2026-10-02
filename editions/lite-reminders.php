<?php
/** Basic reminders; no attendance response endpoint or commercial features. */
if (!defined('ABSPATH')) { exit; }
final class KWB_RSVP {
 const CRON_HOOK='kwb_send_attendance_reminder';
 public static function init() {
  add_action(self::CRON_HOOK,array(__CLASS__,'send_reminder'),10,3);
  add_action('woocommerce_order_status_processing',array(__CLASS__,'schedule_order'));
  add_action('woocommerce_order_status_completed',array(__CLASS__,'schedule_order'));
 }
 public static function schedule_order($id) {
  $order=wc_get_order($id);if (!$order || !$order->is_paid()) { return; }
  foreach($order->get_items() as $item_id=>$item) {
   foreach(KWB_Booking::item_occurrences($item) as $occ) {
    $run=$occ['start_dt']->getTimestamp()-KWB_Booking::reminder_minutes($item->get_product_id())*MINUTE_IN_SECONDS;
    $args=array((int)$id,(int)$item_id,(string)$occ['id']);
    if($run>time()+60 && !wp_next_scheduled(self::CRON_HOOK,$args)) { wp_schedule_single_event($run,self::CRON_HOOK,$args); }
   }
  }
 }
 public static function send_reminder($id,$item_id,$occurrence) {
  $order=wc_get_order($id);if(!$order || !$order->is_paid() || !KWB_Settings::get('reminder_enabled',1) || !KWB_Settings::get('email_reminder_enabled',1)) { return; }
  $item=$order->get_item($item_id);if(!$item || !is_email($order->get_billing_email())) { return; }
  foreach(KWB_Booking::item_occurrences($item) as $occ) {
   if($occ['id']!==$occurrence || $occ['start_dt']->getTimestamp()<=time() || isset(KWB_Booking::blackouts($item->get_product_id())[$occ['date']]) || KWB_Booking::occurrence_declined($item,$occurrence)) { continue; }
   $values=array('{{workshop}}'=>$item->get_name(),'{{date}}'=>wp_date('d/m/Y',$occ['start_dt']->getTimestamp()),'{{time}}'=>$occ['start'],'{{order_number}}'=>$order->get_order_number());
   wp_mail($order->get_billing_email(),strtr(KWB_I18n::t('reminder_subject_default'),$values),strtr(KWB_I18n::t('reminder_message_default'),$values));
  }
 }
}
