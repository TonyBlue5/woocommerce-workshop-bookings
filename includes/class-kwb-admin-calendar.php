<?php
/** Staff calendar and phone bookings. All mutations share the online capacity lock. */
if (!defined('ABSPATH')) { exit; }
final class KWB_Admin_Calendar {
 public static function init() {
  add_action('admin_menu',array(__CLASS__,'menu'));
  add_action('admin_post_kwb_staff_booking',array(__CLASS__,'handle'));
 }
 public static function t($en,$el) { return KWB_Dashboard::t($en,$el); }
 public static function menu() {
  if(KWB_Commercial::can_manage()) { add_submenu_page('woocommerce',self::t('Workshop calendar','Ημερολόγιο εργαστηρίων'),self::t('Workshop calendar','Ημερολόγιο εργαστηρίων'),'manage_woocommerce','kwb-calendar',array(__CLASS__,'page')); }
 }
 public static function url($pid=0,$month='',$occ='') { return add_query_arg(array('page'=>'kwb-calendar','product'=>absint($pid),'month'=>$month?:wp_date('Y-m'),'occurrence'=>$occ),admin_url('admin.php')); }
 public static function allowed($pid) { return KWB_Commercial::can_manage() && current_user_can('edit_shop_orders') && current_user_can('edit_post',$pid) && KWB_Booking::enabled($pid); }
 public static function month($value) { return is_string($value)&&preg_match('/^\d{4}-\d{2}$/',$value)&&KWB_Booking::date_ok($value.'-01')?$value:wp_date('Y-m'); }
 public static function rows($pid,$month) {
  $rows=array();
  foreach(KWB_Commercial::orders(array('status'=>array('pending','checkout-draft','on-hold','processing','completed'))) as $order) {
   if($order->has_status(array('pending','checkout-draft'))&&!$order->get_meta('_kwb_capacity_hold')) { continue; }
   foreach($order->get_items() as $item) {
    $product=$item->get_variation_id()?wp_get_post_parent_id($item->get_variation_id()):$item->get_product_id();
    if((int)$product!==(int)$pid) { continue; }
    $qty=max(0,(int)$item->get_quantity()-absint($order->get_qty_refunded_for_item($item->get_id())));
    foreach(KWB_Booking::item_occurrences($item) as $occ) {
     if(substr($occ['date'],0,7)!==$month) { continue; }
     $released=KWB_Booking::staff_released($item,$occ['id'])||(KWB_Settings::get('release_on_no',1)&&KWB_Booking::occurrence_declined($item,$occ['id']));
     $rows[$occ['id']][]=array('order'=>$order,'item'=>$item,'occ'=>$occ,'qty'=>$qty,'released'=>$released);
    }
   }
  }
  return $rows;
 }
 public static function form($op,$pid,$month,$occ) {
  echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('kwb_staff_booking');
  foreach(array('action'=>'kwb_staff_booking','op'=>$op,'product'=>$pid,'month'=>$month,'occurrence'=>$occ) as $key=>$value) { echo '<input type="hidden" name="'.esc_attr($key).'" value="'.esc_attr($value).'">'; }
 }
 public static function page() {
  if(!KWB_Commercial::can_manage()) { wp_die('Forbidden','',array('response'=>403)); }
  $pid=absint($_GET['product']??0);$month=self::month($_GET['month']??'');$selected=sanitize_text_field(is_scalar($_GET['occurrence']??'')?$_GET['occurrence']:'');
  echo '<div class="wrap kwb-dashboard"><header class="kwb-hero"><span>WORKSHOP BOOKINGS PRO</span><h1>'.esc_html(self::t('Workshop calendar','Ημερολόγιο εργαστηρίων')).'</h1><p>'.esc_html(self::t('Online and phone bookings share the same places.','Οι online και οι τηλεφωνικές κρατήσεις μοιράζονται τις ίδιες θέσεις.')).'</p></header>';
  $notice=get_transient('kwb_staff_notice_'.get_current_user_id());
  if($notice){delete_transient('kwb_staff_notice_'.get_current_user_id());echo '<div class="notice notice-'.($notice['ok']?'success':'error').'"><p>'.esc_html($notice['text']).'</p></div>';}
  echo '<form class="kwb-filters" method="get"><input type="hidden" name="page" value="kwb-calendar"><label class="kwb-field"><span>'.esc_html(self::t('Workshop','Εργαστήριο')).'</span>';KWB_UI::workshop_select('product',array($pid));echo '</label>';KWB_Dashboard::input('month',self::t('Month','Μήνας'),$month,'month','required');echo '<button class="button">'.esc_html(self::t('Show availability','Προβολή διαθεσιμότητας')).'</button></form>';
  if(!$pid || !self::allowed($pid)){echo '<p>'.esc_html(self::t('Select a workshop you can manage.','Επιλέξτε εργαστήριο που μπορείτε να διαχειριστείτε.')).'</p></div>';return;}
  $sessions=KWB_Booking::month_occurrences($pid,$month,false);$rows=self::rows($pid,$month);$scheduled=$sessions;
  foreach($rows as $id=>$list){if(!isset($sessions[$id])){$sessions[$id]=$list[0]['occ'];}}
  $days=array();foreach($sessions as $id=>$occ){$days[$occ['date']][$id]=$occ;}
  $first=new DateTimeImmutable($month.'-01',wp_timezone());$previous=$first->modify('-1 month')->format('Y-m');$next=$first->modify('+1 month')->format('Y-m');
  echo '<p><a class="button" href="'.esc_url(self::url($pid,$previous)).'">← '.esc_html(KWB_Booking::month_label($previous)).'</a> <strong>'.esc_html(KWB_Booking::month_label($month)).'</strong> <a class="button" href="'.esc_url(self::url($pid,$next)).'">'.esc_html(KWB_Booking::month_label($next)).' →</a></p><div class="kwb-staff-calendar"><div class="kwb-staff-grid">';
  foreach(explode('|',self::t('Mon|Tue|Wed|Thu|Fri|Sat|Sun','Δευ|Τρι|Τετ|Πεμ|Παρ|Σαβ|Κυρ')) as $day){echo '<strong>'.esc_html($day).'</strong>';}
  for($i=1;$i<(int)$first->format('N');$i++){echo '<div></div>';}
  for($i=1;$i<=(int)$first->format('t');$i++){
   $date=$month.'-'.str_pad((string)$i,2,'0',STR_PAD_LEFT);echo '<section class="kwb-staff-day"><strong>'.esc_html($i).'</strong>';
   foreach($days[$date]??array() as $id=>$occ){$used=0;foreach($rows[$id]??array() as $row){if(!$row['released']){$used+=$row['qty'];}}
    $left=max(0,(int)$occ['capacity']-$used);$label=isset($scheduled[$id])?sprintf(self::t('%d / %d free','%d / %d ελεύθερες'),$left,$occ['capacity']):self::t('Unavailable','Μη διαθέσιμη');
    echo '<a '.($selected===$id?'aria-current="true"':'').' href="'.esc_url(self::url($pid,$month,$id)).'#kwb-session">'.esc_html($occ['start'].'–'.$occ['end']).'<br>'.esc_html($label).'</a>';
   }echo '</section>';
  }echo '</div></div>';
  if(isset($sessions[$selected])){self::session($pid,$month,$sessions[$selected],$rows[$selected]??array(),isset($scheduled[$selected]));}
  else{echo '<p>'.esc_html(self::t('Choose a session to see participants or add a phone booking.','Επιλέξτε συνάντηση για συμμετέχοντες ή νέα τηλεφωνική κράτηση.')).'</p>';}
  echo '<p>Powered by e-iT – Information Technology &amp; e-commerce</p></div>';
 }
 private static function session($pid,$month,$occ,$rows,$scheduled) {
  echo '<section id="kwb-session" class="kwb-card"><h2>'.esc_html(KWB_Booking::label($occ)).'</h2><div class="kwb-table"><table><thead><tr>';
  foreach(array(self::t('Customer','Πελάτης'),self::t('Contact','Επικοινωνία'),self::t('Places','Θέσεις'),self::t('Order','Παραγγελία'),self::t('Attendance','Συμμετοχή')) as $label){echo '<th>'.esc_html($label).'</th>';}
  echo '</tr></thead><tbody>';
  foreach($rows as $row){$order=$row['order'];echo '<tr><td>'.esc_html($order->get_formatted_billing_full_name()).'</td><td>'.esc_html($order->get_billing_email()).'<br>'.esc_html($order->get_billing_phone()).'</td><td>'.esc_html($row['qty']).'</td><td><a href="'.esc_url($order->get_edit_order_url()).'">#'.esc_html($order->get_order_number()).'</a><br>'.esc_html(wc_get_order_status_name($order->get_status())).'</td><td>';
   echo esc_html($row['released']?self::t('Released','Αποδεσμευμένη'):self::t('Reserved','Δεσμευμένη'));
   if($occ['start_dt']->getTimestamp()>time()&&$row['qty']>0){self::form($row['released']?'restore':'release',$pid,$month,$occ['id']);echo '<input type="hidden" name="order" value="'.esc_attr($order->get_id()).'"><input type="hidden" name="item" value="'.esc_attr($row['item']->get_id()).'"><label><input type="checkbox" name="confirm" value="1" required> '.esc_html(self::t('Confirm this session only','Επιβεβαίωση μόνο για αυτή τη συνάντηση')).'</label><br><button class="button">'.esc_html($row['released']?self::t('Reserve again','Εκ νέου δέσμευση'):self::t('Release places','Αποδέσμευση θέσεων')).'</button></form>';}
   echo '</td></tr>';
  }
  if(!$rows){echo '<tr><td colspan="5">'.esc_html(self::t('No bookings yet.','Δεν υπάρχουν κρατήσεις.')).'</td></tr>';}echo '</tbody></table></div><p>'.esc_html(self::t('Releasing a session does not cancel the order or refund money. Other monthly sessions remain reserved.','Η αποδέσμευση συνάντησης δεν ακυρώνει την παραγγελία ούτε επιστρέφει χρήματα. Οι υπόλοιπες μηνιαίες συναντήσεις παραμένουν δεσμευμένες.')).'</p></section>';
  if(!$scheduled||$occ['start_dt']->getTimestamp()<=time()){return;}
  $product=wc_get_product($pid);if(!$product||!$product->is_type('simple')){echo '<p>'.esc_html(self::t('Phone booking creation requires a simple workshop product.','Η δημιουργία τηλεφωνικής κράτησης απαιτεί απλό προϊόν εργαστηρίου.')).'</p>';return;}
  echo '<section class="kwb-card"><h2>'.esc_html(self::t('Add a phone booking','Προσθήκη τηλεφωνικής κράτησης')).'</h2>';self::form('create',$pid,$month,$occ['id']);echo '<input type="hidden" name="request_id" value="'.esc_attr(wp_generate_uuid4()).'">';
  KWB_Dashboard::input('name',self::t('Customer name','Ονοματεπώνυμο'),'','text','required maxlength="150"');KWB_Dashboard::input('email','Email','','email','maxlength="200"');KWB_Dashboard::input('phone',self::t('Phone','Τηλέφωνο'),'','tel','maxlength="50"');KWB_Dashboard::input('quantity',self::t('Participants','Συμμετέχοντες'),1,'number','min="1" max="10000" required');
  echo '<label class="kwb-field"><span>'.esc_html(self::t('Participation','Συμμετοχή')).'</span><select name="mode">';
  foreach(array('single'=>self::t('This session','Αυτή η συνάντηση'),'monthly'=>self::t('All remaining sessions this month','Όλες οι υπόλοιπες συναντήσεις του μήνα')) as $mode=>$label){if(KWB_Booking::mode($pid,$mode)){echo '<option value="'.esc_attr($mode).'">'.esc_html($label).'</option>';}}
  echo '</select></label><p>'.esc_html(self::t('Enter an email or phone number. The product price and store taxes apply. The order is created On hold, reserving the places without recording payment. Open the order to record payment or send a payment link.','Συμπληρώστε email ή τηλέφωνο. Ισχύουν η τιμή προϊόντος και οι φόροι καταστήματος. Η παραγγελία δημιουργείται Σε αναμονή, δεσμεύοντας θέσεις χωρίς καταχώριση πληρωμής. Ανοίξτε την παραγγελία για καταχώριση πληρωμής ή αποστολή συνδέσμου πληρωμής.')).'</p><button class="button button-primary">'.esc_html(self::t('Create reservation','Δημιουργία κράτησης')).'</button></form></section>';
 }
 public static function handle() {
  if(!KWB_Commercial::can_manage()){wp_die('Forbidden','',array('response'=>403));}check_admin_referer('kwb_staff_booking');
  $pid=absint(KWB_Dashboard::request('product'));$month=self::month(KWB_Dashboard::request('month'));$occ=KWB_Dashboard::request('occurrence');
  try{
   if('create'===KWB_Dashboard::request('op')){$id=self::create(wp_unslash($_POST));$text=sprintf(self::t('Reservation created. Order #%d is On hold.','Η κράτηση δημιουργήθηκε. Η παραγγελία #%d είναι Σε αναμονή.'),$id);}
   else{if('1'!==KWB_Dashboard::request('confirm')){throw new Exception(self::t('Confirm the attendance change.','Επιβεβαιώστε την αλλαγή συμμετοχής.'));}self::attendance(absint(KWB_Dashboard::request('order')),absint(KWB_Dashboard::request('item')),$pid,$occ,KWB_Dashboard::request('op'));$text=self::t('Attendance updated. Order totals and payments are unchanged.','Η συμμετοχή ενημερώθηκε. Τα ποσά και οι πληρωμές της παραγγελίας δεν άλλαξαν.');}
   $notice=array('ok'=>true,'text'=>$text);
  }catch(Exception $error){$notice=array('ok'=>false,'text'=>$error->getMessage());}
  finally{KWB_Booking::unlock_capacity();}
  set_transient('kwb_staff_notice_'.get_current_user_id(),$notice,MINUTE_IN_SECONDS);wp_safe_redirect(self::url($pid,$month,$occ).'#kwb-session');exit;
 }
 public static function create($input) {
  $pid=isset($input['product'])&&is_scalar($input['product'])?absint($input['product']):0;if(!self::allowed($pid)){throw new Exception(self::t('Access denied.','Δεν επιτρέπεται η πρόσβαση.'));}
  foreach(array('name','email','phone','mode','occurrence','month','request_id','quantity') as $key){if(isset($input[$key])&&!is_scalar($input[$key])){throw new Exception('Invalid input');}}
  $name=sanitize_text_field($input['name']??'');$email=trim($input['email']??'');$phone=wc_sanitize_phone_number($input['phone']??'');$qty=absint($input['quantity']??0);$mode=sanitize_key($input['mode']??'');$month=self::month($input['month']??'');$request=sanitize_key($input['request_id']??'');
  $product=wc_get_product($pid);
  if(!$product||!$product->is_type('simple')||!in_array($mode,array('single','monthly'),true)||!$name||(!$email&&!$phone)||($email&&!is_email($email))||strlen($name)>150||strlen($email)>200||strlen($phone)>50||$qty<1||$qty>10000||(KWB_Booking::max_qty($pid)&&$qty>KWB_Booking::max_qty($pid))||!KWB_Booking::mode($pid,$mode)||!preg_match('/^[a-f0-9-]{36}$/',$request)){throw new Exception(self::t('Check the customer, contact details, participation and quantity.','Ελέγξτε πελάτη, στοιχεία επικοινωνίας, συμμετοχή και ποσότητα.'));}
  if(!KWB_Booking::lock_capacity()){throw new Exception(self::t('Availability is being updated. Try again.','Η διαθεσιμότητα ενημερώνεται. Δοκιμάστε ξανά.'));}
  $request=hash('sha256',get_current_user_id().'|'.$request);
  $existing=wc_get_order(absint(get_transient('kwb_manual_'.$request)));
  if($existing){if($existing->has_status(array('failed','cancelled','refunded'))){throw new Exception(self::t('This request was already processed. Reload the calendar before making another booking.','Το αίτημα έχει ήδη καταχωριστεί. Ανανεώστε το ημερολόγιο πριν από νέα κράτηση.'));}return $existing->get_id();}
  $sessions=KWB_Booking::month_occurrences($pid,$month,true);$chosen=sanitize_text_field($input['occurrence']??'');
  if(!isset($sessions[$chosen])){throw new Exception(self::t('This session is unavailable.','Η συνάντηση δεν είναι διαθέσιμη.'));}
  if('single'===$mode){$sessions=array($chosen=>$sessions[$chosen]);}
  foreach($sessions as $session){if($qty>KWB_Booking::remaining($pid,$session)){throw new Exception(KWB_I18n::t('not_enough_places',array('slot'=>KWB_Booking::label($session))));}}
  $order=null;
  try{
   $order=wc_create_order(array('created_via'=>'kwb-admin'));if(is_wp_error($order)){throw new Exception($order->get_error_message());}
   $order->set_billing_first_name($name);$order->set_billing_email($email);$order->set_billing_phone($phone);$order->set_billing_country(WC()->countries->get_base_country());$order->set_billing_state(WC()->countries->get_base_state());
   set_transient('kwb_manual_'.$request,$order->get_id(),2*DAY_IN_SECONDS);
   $order->update_meta_data('_kwb_manual_request',$request);$order->update_meta_data('_kwb_staff_author',get_current_user_id());
   $product=wc_get_product($pid);$price=KWB_Booking::price($pid,$mode);$total=wc_get_price_excluding_tax($product,array('price'=>$price,'qty'=>$qty));$iid=$order->add_product($product,$qty,array('subtotal'=>$total,'total'=>$total));$item=$order->get_item($iid);
   $clean=array();foreach($sessions as $session){$clean[]=array_intersect_key($session,array_flip(array('id','date','start','end','capacity')));}
   KWB_Booking::order_meta($item,'',array('kwb_booking'=>array('type'=>$mode,'month'=>'monthly'===$mode?$month:'','months'=>'monthly'===$mode?array($month):array(),'occurrences'=>$clean)),$order);$item->save();
   KWB_Booking::reserve_order($order);$order->calculate_totals();$order->save();$order->update_status('on-hold',self::t('Telephone booking recorded by staff. Payment has not been recorded.','Τηλεφωνική κράτηση από το προσωπικό. Δεν έχει καταχωριστεί πληρωμή.'),true);return $order->get_id();
  }catch(Exception $error){if($order instanceof WC_Order){$order->update_status('failed',self::t('Manual booking could not be completed.','Η χειροκίνητη κράτηση δεν ολοκληρώθηκε.'));}throw $error;}
 }
 public static function attendance($oid,$iid,$pid,$occ,$op) {
  if(!self::allowed($pid)||!in_array($op,array('release','restore'),true)){throw new Exception(self::t('Access denied.','Δεν επιτρέπεται η πρόσβαση.'));}
  if(!KWB_Booking::lock_capacity()){throw new Exception(self::t('Try again.','Δοκιμάστε ξανά.'));}
  $order=wc_get_order($oid);$item=$order?$order->get_item($iid):false;$item_pid=$item?($item->get_variation_id()?wp_get_post_parent_id($item->get_variation_id()):$item->get_product_id()):0;
  if(!$item||(int)$item_pid!==$pid||!$order->has_status(array('pending','on-hold','processing','completed'))||($order->has_status('pending')&&!$order->get_meta('_kwb_capacity_hold'))){throw new Exception(self::t('This reservation is unavailable.','Η κράτηση δεν είναι διαθέσιμη.'));}
  $selected=null;foreach(KWB_Booking::item_occurrences($item) as $session){if($session['id']===$occ){$selected=$session;break;}}
  if(!$selected||$selected['start_dt']->getTimestamp()<=time()){throw new Exception(self::t('Select a future booked session.','Επιλέξτε μελλοντική συνάντηση με κράτηση.'));}
  $released=array_map('strval',(array)$item->get_meta('_kwb_staff_released_occurrences',true));$no=array_map('strval',(array)$item->get_meta('_kwb_declined_occurrences',true));$was_released=in_array($occ,$released,true)||(KWB_Settings::get('release_on_no',1)&&in_array($occ,$no,true));
  if('restore'===$op&&$was_released){
   $fresh=KWB_Booking::month_occurrences($pid,substr($selected['date'],0,7),true);$qty=max(0,(int)$item->get_quantity()-absint($order->get_qty_refunded_for_item($iid)));
   if(!isset($fresh[$occ])||$qty>KWB_Booking::remaining($pid,$fresh[$occ])){throw new Exception(KWB_I18n::t('seat_taken'));}
   $released=array_values(array_diff($released,array($occ)));$no=array_values(array_diff($no,array($occ)));$item->update_meta_data('_kwb_declined_occurrences',$no);
  }elseif('release'===$op){$released[]=$occ;$yes=(array)$item->get_meta('_kwb_confirmed_occurrences',true);$item->update_meta_data('_kwb_confirmed_occurrences',array_values(array_diff($yes,array($occ))));}
  $item->update_meta_data('_kwb_staff_released_occurrences',array_values(array_unique($released)));$item->save();
  $order->add_order_note(sprintf(self::t('Staff %s for %s. Other occurrences and payment totals unchanged.','Το προσωπικό έκανε %s για %s. Οι υπόλοιπες συναντήσεις και τα ποσά δεν άλλαξαν.'),'release'===$op?self::t('released places','αποδέσμευση θέσεων'):self::t('restored places','επαναδέσμευση θέσεων'),KWB_Booking::label($selected)),false,true);
 }
}
