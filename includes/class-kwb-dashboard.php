<?php
if ( ! defined('ABSPATH') ) { exit; }

final class KWB_Dashboard {
 public static function t($en,$el) { return KWB_I18n::is_greek()?$el:$en; }
 public static function init() {
  add_action('admin_menu',array(__CLASS__,'menu'));
  add_action('admin_post_kwb_commercial',array(__CLASS__,'handle'));
  add_action('admin_enqueue_scripts',array(__CLASS__,'assets'));
  add_action('wp_enqueue_scripts',array(__CLASS__,'assets'));
  add_action('init',array(__CLASS__,'endpoint'));
  add_filter('woocommerce_account_menu_items',array(__CLASS__,'account_menu'));
  add_action('woocommerce_account_workshops_endpoint',array(__CLASS__,'account'));
  add_action('template_redirect',array(__CLASS__,'account_action'));
 }
 public static function endpoint() {
  add_rewrite_endpoint('workshops',EP_ROOT|EP_PAGES);
  if (get_option('kwb_account_endpoint_version')!=='1') {
   flush_rewrite_rules(false); update_option('kwb_account_endpoint_version','1',false);
  }
 }
 public static function assets($hook='') {
  if (is_admin() ? $hook!=='woocommerce_page_kwb-dashboard' : !is_account_page()) { return; }
  wp_enqueue_style('kwb-dashboard',plugins_url('../assets/kwb-dashboard.css',__FILE__),array(),KWB_VERSION);
  if (is_admin()) {
   wp_enqueue_media();
   wp_enqueue_script('kwb-dashboard',plugins_url('../assets/kwb-dashboard.js',__FILE__),array('jquery'),KWB_VERSION,true);
  }
 }
 public static function menu() {
  if (KWB_Commercial::can_manage()) {
   add_submenu_page('woocommerce',self::t('Workshop dashboard','Πίνακας εργαστηρίων'),self::t('Workshop dashboard','Πίνακας εργαστηρίων'),'manage_woocommerce','kwb-dashboard',array(__CLASS__,'render'));
  }
 }
 public static function url($tab='analytics') { return add_query_arg(array('page'=>'kwb-dashboard','tab'=>$tab),admin_url('admin.php')); }
 public static function input($name,$label,$value='',$type='text',$extra='') {
  echo '<label class="kwb-field"><span>'.esc_html($label).'</span><input name="'.esc_attr($name).'" type="'.esc_attr($type).'" value="'.esc_attr($value).'" '.$extra.'></label>';
 }
 public static function form($op,$label) {
  echo '<form class="kwb-form" method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
  wp_nonce_field('kwb_commercial','kwb_nonce');
  echo '<input type="hidden" name="action" value="kwb_commercial"><input type="hidden" name="op" value="'.esc_attr($op).'">';
  echo '<h2>'.esc_html($label).'</h2>';
 }
 public static function button($text) { echo '<button class="button button-primary" type="submit">'.esc_html($text).'</button></form>'; }
 public static function terms($policy) {
  $mode='any'===$policy['mode']?self::t('any workshop','οποιοδήποτε εργαστήριο'):('monthly'===$policy['mode']?self::t('monthly bookings','μηνιαίες κρατήσεις'):self::t('single bookings','μεμονωμένες κρατήσεις'));
  $months_label=1===(int)$policy['months']?self::t('month','μήνα'):self::t('months','μήνες');
  return sprintf(self::t('%s%% off %s; up to %d %s, one participant in one booking line, one use within %d days. Workshop: %s. Unused months do not carry forward.','Έκπτωση %s%% σε %s· έως %d %s, ένας συμμετέχων σε μία γραμμή κράτησης, μία χρήση εντός %d ημερών. Εργαστήριο: %s. Οι μήνες που δεν χρησιμοποιούνται δεν μεταφέρονται.'),$policy['percent'],$mode,$policy['months'],$months_label,$policy['expiry'],$policy['product']?get_the_title($policy['product']):self::t('all','όλα'));
 }
 public static function policy_fields($policy,$referral=false) {
  if ($referral) {
   echo '<label><input type="checkbox" name="enabled" value="1" '.checked($policy['enabled'],1,false).'> '.esc_html(self::t('Enable referrals','Ενεργοποίηση συστάσεων')).'</label>';
   self::input('friends',self::t('Friends who must complete a purchase','Φίλοι που πρέπει να ολοκληρώσουν αγορά'),$policy['friends'],'number','min="1" max="100" required');
  }
  self::input('percent',self::t('Discount (%) — 100 means free','Έκπτωση (%) — 100 σημαίνει δωρεάν'),$policy['percent'],'number','min="1" max="100" step="0.01" required');
  self::input('months',self::t('Maximum rewarded months (monthly bookings)','Μέγιστος αριθμός μηνών ανταμοιβής (μηνιαίες κρατήσεις)'),$policy['months'],'number','min="1" max="12" required');
  echo '<label class="kwb-field"><span>'.esc_html(self::t('Eligible purchases and reward','Επιλέξιμες αγορές και ανταμοιβή')).'</span><select name="mode">';
  foreach(array('monthly'=>self::t('Monthly','Μηνιαία'),'single'=>self::t('Single','Μεμονωμένη'),'any'=>self::t('Both','Και τα δύο')) as $value=>$label) { echo '<option value="'.esc_attr($value).'" '.selected($policy['mode'],$value,false).'>'.esc_html($label).'</option>'; }
  echo '</select></label>';
  self::input('product',self::t('Workshop product ID (0 = all)','ID προϊόντος εργαστηρίου (0 = όλα)'),$policy['product'],'number','min="0" required');
  self::input('expiry',self::t('Coupon validity (days)','Ισχύς κουπονιού (ημέρες)'),$policy['expiry'],'number','min="1" max="365" required');
 }
 public static function render() {
  if (!KWB_Commercial::can_manage()) { wp_die(esc_html(self::t('Access denied.','Δεν επιτρέπεται η πρόσβαση.')), '',array('response'=>403)); }
  $tab=sanitize_key($_GET['tab']??'analytics');
  echo '<div class="wrap kwb-dashboard"><header class="kwb-hero"><span>WORKSHOP BOOKINGS</span><h1>'.esc_html(self::t('Your workshops, together','Όλα τα εργαστήριά σας μαζί')).'</h1><p>'.esc_html(self::t('Bookings, customers and rewards.','Κρατήσεις, πελάτες και ανταμοιβές.')).'</p></header><nav class="kwb-tabs">';
  foreach(array('analytics'=>self::t('Overview','Επισκόπηση'),'customers'=>self::t('Customers & loyalty','Πελάτες & επιβράβευση'),'broadcast'=>self::t('Workshop updates','Ενημερώσεις εργαστηρίων'),'referrals'=>self::t('Referrals','Συστάσεις'),'deliveries'=>self::t('Delivery history','Ιστορικό αποστολών')) as $key=>$label) {
   echo '<a '.($key===$tab?'aria-current="page"':'').' href="'.esc_url(self::url($key)).'">'.esc_html($label).'</a>';
  }
  echo '</nav>';
  if (!empty($_GET['saved'])) { echo '<div class="notice notice-success"><p>'.esc_html(self::t('Saved. Queued messages are processed in the background.','Αποθηκεύτηκε. Τα μηνύματα στην ουρά αποστέλλονται στο παρασκήνιο.')).'</p></div>'; }
  if ('referrals'===$tab) {
   self::form('settings',self::t('Referral rewards','Ανταμοιβές συστάσεων'));
   self::policy_fields(KWB_Rewards::settings(),true);
   echo '<p>'.esc_html(self::t('Each new registered friend counts once after a completed, paid eligible booking. Refunded qualifying items stop counting. Each group of friends earns one coupon. Terms are fixed when the friend registers; changed settings apply to new registrations.','Κάθε νέος εγγεγραμμένος φίλος μετρά μία φορά μετά από ολοκληρωμένη, πληρωμένη επιλέξιμη κράτηση. Αγορές με επιστροφή δεν προσμετρώνται. Κάθε ομάδα φίλων δίνει ένα κουπόνι. Οι όροι οριστικοποιούνται κατά την εγγραφή του φίλου· οι αλλαγές ισχύουν για νέες εγγραφές.')).'</p>';
   self::button(self::t('Save terms','Αποθήκευση όρων'));
  } elseif ('customers'===$tab) {
   $days=max(1,min(3650,absint($_GET['days']??90)));
   echo '<section class="kwb-card"><h2>'.esc_html(self::t('Customers to reconnect with','Πελάτες για επανασύνδεση')).'</h2><form method="get"><input type="hidden" name="page" value="kwb-dashboard"><input type="hidden" name="tab" value="customers">';
   self::input('days',self::t('Days since last paid booking','Ημέρες από την τελευταία πληρωμένη κράτηση'),$days,'number','min="1" max="3650"');
   echo '<button class="button">'.esc_html(self::t('Filter','Φιλτράρισμα')).'</button></form>';
   $customers=KWB_Commercial::inactive($days);
   echo '<p>'.esc_html(count($customers).' '.self::t('customers','πελάτες')).'</p><div class="kwb-table"><table><thead><tr><th>'.esc_html(self::t('Customer','Πελάτης')).'</th><th>Email</th><th>'.esc_html(self::t('Last booking','Τελευταία κράτηση')).'</th></tr></thead><tbody>';
   foreach(array_slice($customers,0,100) as $row) { echo '<tr><td>'.esc_html($row['name']).'</td><td>'.esc_html($row['email']).'</td><td>'.esc_html(wp_date('d/m/Y',$row['last'])).'</td></tr>'; }
   echo '</tbody></table></div><p>'.esc_html(self::t('Preview: first 100. Export contains all matching customers. Marketing consent is not inferred from a purchase.','Προεπισκόπηση: οι πρώτοι 100. Η εξαγωγή περιλαμβάνει όλους τους αντίστοιχους πελάτες. Η αγορά δεν θεωρείται συγκατάθεση για ενημερωτικά δελτία.')).'</p>';
   self::form('export',self::t('Export list','Εξαγωγή λίστας'));
   echo '<input type="hidden" name="days" value="'.esc_attr($days).'">'; self::button(self::t('Download CSV','Λήψη CSV')); echo '</section>';
   self::form('preview',self::t('Reward your customers','Επιβράβευση πελατών'));
   echo '<input type="hidden" name="kind" value="loyalty">';
   self::input('email',self::t('Customer email (blank = all matching customers)','Email πελάτη (κενό = όλοι οι αντίστοιχοι πελάτες)'),'','email');
   self::input('bookings',self::t('Minimum paid booking lines','Ελάχιστος αριθμός πληρωμένων κρατήσεων'),1,'number','min="1" required');
   self::input('workshops',self::t('Minimum different workshops','Ελάχιστος αριθμός διαφορετικών εργαστηρίων'),1,'number','min="1" required');
   self::policy_fields(KWB_Rewards::settings());
   self::message_fields(); self::button(self::t('Review campaign','Έλεγχος αποστολής'));
  } elseif ('broadcast'===$tab) {
   self::form('preview',self::t('Cancellation notice','Ενημέρωση ακύρωσης'));
   echo '<input type="hidden" name="kind" value="broadcast">';
   self::input('product',self::t('Workshop product ID','ID προϊόντος εργαστηρίου'),'','number','min="1" required');
   self::input('date',self::t('Cancelled date (all sessions that day)','Ημερομηνία ακύρωσης (όλες οι συναντήσεις της ημέρας)'),'','date','required');
   echo '<p>'.esc_html(self::t('All booked customers for this workshop and date receive a separate email. Confirming also closes this date to new bookings and stops reminders. Existing orders and payments are preserved; manage refunds from the order screen.','Όλοι οι πελάτες με κράτηση για το εργαστήριο και την ημερομηνία λαμβάνουν ξεχωριστό email. Η επιβεβαίωση κλείνει την ημέρα για νέες κρατήσεις και σταματά τις υπενθυμίσεις. Οι παραγγελίες και οι πληρωμές διατηρούνται· οι επιστροφές γίνονται από την παραγγελία.')).'</p>';
   self::message_fields(); self::button(self::t('Review cancellation','Έλεγχος ακύρωσης'));
  } elseif ('deliveries'===$tab) { self::deliveries(); }
  elseif ('review'===$tab) { self::review(); }
  else { self::analytics(); }
  echo '</div>';
 }
 public static function message_fields() {
  self::input('subject',self::t('Email subject','Θέμα email'),'','text','maxlength="180" required');
  echo '<label class="kwb-field"><span>'.esc_html(self::t('Message','Μήνυμα')).'</span><textarea name="message" rows="5" maxlength="10000" required></textarea></label>';
  echo '<input id="kwb-image" type="hidden" name="image" value="0"><button type="button" class="button" id="kwb-select-image">'.esc_html(self::t('Choose image','Επιλογή εικόνας')).'</button><button type="button" class="button" id="kwb-remove-image">'.esc_html(self::t('Remove image','Αφαίρεση εικόνας')).'</button><div id="kwb-image-preview"></div>';
 }
 public static function analytics() {
  $from=sanitize_text_field($_GET['from']??wp_date('Y-m-01')); $to=sanitize_text_field($_GET['to']??wp_date('Y-m-d'));
  if (!KWB_Booking::date_ok($from) || !KWB_Booking::date_ok($to) || $from>$to) { $from=wp_date('Y-m-01');$to=wp_date('Y-m-d'); }
  echo '<section class="kwb-card"><h2>'.esc_html(self::t('Booking overview','Επισκόπηση κρατήσεων')).'</h2><form class="kwb-filters" method="get"><input type="hidden" name="page" value="kwb-dashboard">';
  self::input('from',self::t('From','Από'),$from,'date');self::input('to',self::t('To','Έως'),$to,'date');
  echo '<button class="button">'.esc_html(self::t('Update','Ενημέρωση')).'</button></form><p>'.esc_html(self::t('By order creation date. Counts are booking lines; revenue excludes tax and shipping, after discounts and item refunds. Fully refunded orders have zero revenue. Currencies are shown separately.','Με βάση την ημερομηνία παραγγελίας. Μετράμε γραμμές κράτησης· τα έσοδα δεν περιλαμβάνουν φόρους και μεταφορικά και αφαιρούν εκπτώσεις και επιστροφές ειδών. Οι πλήρως επιστραφείσες παραγγελίες έχουν μηδενικά έσοδα. Τα νομίσματα εμφανίζονται χωριστά.')).'</p><div class="kwb-table"><table><thead><tr>';
  foreach(array(self::t('Workshop','Εργαστήριο'),self::t('Monthly bookings','Μηνιαίες κρατήσεις'),self::t('Monthly revenue','Έσοδα μηνιαίων'),self::t('Single bookings','Μεμονωμένες κρατήσεις'),self::t('Single revenue','Έσοδα μεμονωμένων')) as $label) { echo '<th>'.esc_html($label).'</th>'; }
  echo '</tr></thead><tbody>';
  $rows=KWB_Commercial::report($from,$to);
  foreach($rows as $row) {
   echo '<tr><th>'.esc_html($row['name']).'</th><td>'.esc_html($row['monthly']).'</td><td>'.wp_kses_post(wc_price($row['monthly_revenue'],array('currency'=>$row['currency']))).'</td><td>'.esc_html($row['single']).'</td><td>'.wp_kses_post(wc_price($row['single_revenue'],array('currency'=>$row['currency']))).'</td></tr>';
  }
  if (!$rows) { echo '<tr><td colspan="5">'.esc_html(self::t('No bookings in this period.','Δεν υπάρχουν κρατήσεις σε αυτή την περίοδο.')).'</td></tr>'; }
  echo '</tbody></table></div></section>';
 }
 public static function request($name,$default='') { return isset($_POST[$name]) && is_scalar($_POST[$name])?sanitize_text_field(wp_unslash($_POST[$name])):$default; }
 public static function handle() {
  if (!KWB_Commercial::can_manage()) { wp_die('Forbidden','',array('response'=>403)); }
  check_admin_referer('kwb_commercial','kwb_nonce');
  $op=self::request('op'); $tab='deliveries';
  if ('settings'===$op) {
   $policy=KWB_Rewards::sanitize(wp_unslash($_POST)); self::check_policy($policy);
   update_option('kwb_referral_settings',$policy,false); $tab='referrals';
  } elseif ('export'===$op) {
   nocache_headers(); header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename="inactive-workshop-customers.csv"'); header('X-Content-Type-Options: nosniff');
   $out=fopen('php://output','w'); fwrite($out,"\xEF\xBB\xBF");
   fputcsv($out,array('Name','Email','Last booking','Paid booking lines','Different workshops','Marketing consent'));
   foreach(KWB_Commercial::inactive(max(1,min(3650,absint(self::request('days',90))))) as $row) {
    fputcsv($out,array_map(array(__CLASS__,'csv'),array($row['name'],$row['email'],wp_date('Y-m-d',$row['last']),$row['bookings'],count($row['workshops']),'not recorded')));
   }
   fclose($out); exit;
  } elseif ('preview'===$op) {
   $kind=self::request('kind'); if (!in_array($kind,array('loyalty','broadcast'),true)) { wp_die('Invalid campaign'); }
   $config=array('kind'=>$kind,'subject'=>substr(self::request('subject'),0,180),'message'=>sanitize_textarea_field(wp_unslash($_POST['message']??'')),'image'=>absint(self::request('image')));
   if (!$config['subject'] || !$config['message'] || strlen($config['message'])>30000) { wp_die(esc_html(self::t('Enter a subject and message.','Συμπληρώστε θέμα και μήνυμα.'))); }
   if ($config['image'] && (!wp_attachment_is_image($config['image']) || !current_user_can('edit_post',$config['image']))) { wp_die('Invalid image'); }
   if ('loyalty'===$kind) {
    $config['policy']=KWB_Rewards::sanitize(wp_unslash($_POST)); self::check_policy($config['policy']);
    $config['email']=strtolower(sanitize_email(self::request('email')));
    if (self::request('email') && !is_email($config['email'])) { wp_die('Invalid email'); }
    $config['bookings']=max(1,absint(self::request('bookings'))); $config['workshops']=max(1,absint(self::request('workshops')));
   } else {
    $config['product']=absint(self::request('product')); self::check_product($config['product']);
    $config['date']=self::request('date');
    if (!KWB_Booking::date_ok($config['date']) || $config['date']<wp_date('Y-m-d')) { wp_die(esc_html(self::t('Choose today or a future date.','Επιλέξτε σημερινή ή μελλοντική ημερομηνία.'))); }
   }
   $id=wp_generate_uuid4(); set_transient('kwb_preview_'.get_current_user_id().'_'.$id,$config,HOUR_IN_SECONDS);
   wp_safe_redirect(add_query_arg('preview',$id,self::url('review')));exit;
  } elseif ('confirm'===$op) {
   $id=sanitize_key(self::request('preview')); $config=get_transient('kwb_preview_'.get_current_user_id().'_'.$id);
   if (!$config) { wp_die(esc_html(self::t('Preview expired. Please review the message again.','Η προεπισκόπηση έληξε. Ελέγξτε ξανά το μήνυμα.'))); }
   if ('loyalty'===$config['kind']) { self::check_policy($config['policy']); }
   if ('broadcast'===$config['kind']) {
    self::check_product($config['product']);
    $lock='cancel_'.$config['product'];
    if (!KWB_Commercial::lock($lock)) { wp_die(esc_html(self::t('The workshop is being updated. Please try again.','Το εργαστήριο ενημερώνεται. Δοκιμάστε ξανά.'))); }
    try {
     $blackouts=KWB_Booking::blackouts($config['product']);$blackouts[$config['date']]=1;
     update_post_meta($config['product'],KWB_Booking::META_BLACKOUTS,implode("\n",array_keys($blackouts)));
    } finally { KWB_Commercial::unlock($lock); }
   }
   KWB_Campaigns::create($config,$id); delete_transient('kwb_preview_'.get_current_user_id().'_'.$id);
  } elseif ('retry'===$op) { KWB_Campaigns::retry(sanitize_key(self::request('campaign'))); }
  wp_safe_redirect(add_query_arg('saved',1,self::url($tab)));exit;
 }
 public static function csv($value) { return KWB_Commercial::csv_cell($value); }
 private static function check_policy($policy) {
  self::check_product($policy['product'],true);
  if (!$policy['product']) { return; }
  if ('any'!==$policy['mode'] && !KWB_Booking::mode($policy['product'],$policy['mode'])) {
   wp_die(esc_html(self::t('This workshop does not offer the selected booking mode.','Το εργαστήριο δεν προσφέρει τον επιλεγμένο τύπο κράτησης.')));
  }
  if ('single'!==$policy['mode'] && KWB_Booking::mode($policy['product'],'monthly') && $policy['months']>KWB_Booking::max_booking_months($policy['product'])) {
   wp_die(esc_html(self::t('The reward month allowance exceeds this workshop’s maximum booking months. Increase the product limit or reduce the reward.','Οι μήνες ανταμοιβής υπερβαίνουν το όριο μηνών κράτησης του εργαστηρίου. Αυξήστε το όριο του προϊόντος ή μειώστε την ανταμοιβή.')));
  }
 }
 private static function check_product($id,$allow_all=false) {
  if (!$id && $allow_all) { return; }
  if (!$id || !wc_get_product($id) || !KWB_Booking::enabled($id) || !current_user_can('edit_post',$id)) { wp_die(esc_html(self::t('Choose a workshop you can edit.','Επιλέξτε εργαστήριο που μπορείτε να επεξεργαστείτε.'))); }
 }
 public static function review() {
  $id=sanitize_key($_GET['preview']??''); $config=get_transient('kwb_preview_'.get_current_user_id().'_'.$id);
  if (!$config) { echo '<p>'.esc_html(self::t('Preview expired.','Η προεπισκόπηση έληξε.')).'</p>';return; }
  echo '<section class="kwb-card"><h2>'.esc_html($config['subject']).'</h2><p>'.nl2br(esc_html($config['message'])).'</p>';
  if ($config['image']) { echo wp_kses_post(wp_get_attachment_image($config['image'],'medium')); }
  if ('loyalty'===$config['kind']) {
   echo '<p>'.esc_html(self::terms($config['policy'])).'</p><p>'.esc_html(sprintf(self::t('Audience: %s. At least %d bookings across %d workshops.','Παραλήπτες: %s. Τουλάχιστον %d κρατήσεις σε %d εργαστήρια.'),$config['email']?:self::t('all matching customers','όλοι οι αντίστοιχοι πελάτες'),$config['bookings'],$config['workshops'])).'</p>';
  } else { echo '<p>'.esc_html(get_the_title($config['product']).' · '.$config['date']).'</p>'; }
  self::form('confirm',self::t('Confirm and queue','Επιβεβαίωση και προγραμματισμός'));
  echo '<input type="hidden" name="preview" value="'.esc_attr($id).'">';
  self::button(self::t('Confirm and send','Επιβεβαίωση και αποστολή'));echo '</section>';
 }
 public static function deliveries() {
  global $wpdb;
  $options=$wpdb->get_results("SELECT option_name,option_value FROM {$wpdb->options} WHERE option_name LIKE 'kwb_campaign_%' AND option_name NOT LIKE 'kwb_campaign_ready_%' ORDER BY option_id DESC LIMIT 30");
  echo '<section class="kwb-card"><h2>'.esc_html(self::t('Recent campaigns','Πρόσφατες αποστολές')).'</h2><p>'.esc_html(self::t('Sent means accepted by the mail service, not confirmed delivery. “Sending” may indicate an interrupted request; check your mail logs before sending again. Failed messages can be retried.','Απεσταλμένο σημαίνει αποδοχή από την υπηρεσία email, όχι επιβεβαίωση παράδοσης. Η ένδειξη «Αποστολή» μπορεί να σημαίνει διακοπή· ελέγξτε τα αρχεία email πριν ξαναστείλετε. Οι αποτυχημένες αποστολές επαναλαμβάνονται.')).'</p>';
  foreach($options as $option) {
   $config=maybe_unserialize($option->option_value); if (!is_array($config) || empty($config['subject'])) { continue; }
   $id=substr($option->option_name,strlen('kwb_campaign_'));
   echo '<article class="kwb-campaign"><h3>'.esc_html($config['subject']).'</h3><p>'.esc_html(wp_date('d/m/Y H:i',$config['created'])).'</p><div class="kwb-stats">';
   $states=$wpdb->get_results($wpdb->prepare("SELECT state,COUNT(*) AS total FROM {$wpdb->prefix}kwb_deliveries WHERE campaign=%s GROUP BY state",$id));
   $labels=array('pending'=>self::t('Queued','Στην ουρά'),'sending'=>self::t('Sending / unconfirmed','Αποστολή / ανεπιβεβαίωτο'),'sent'=>self::t('Sent','Απεσταλμένα'),'failed'=>self::t('Failed','Αποτυχημένα'));
   foreach($states as $state) { echo '<span>'.esc_html(($labels[$state->state]??$state->state).': '.$state->total).'</span>'; }
   if (!$states) { echo '<span>'.esc_html(get_option('kwb_campaign_ready_'.$id)?self::t('No matching recipients','Δεν βρέθηκαν παραλήπτες'):self::t('Preparing recipients','Προετοιμασία παραληπτών')).'</span>'; }
   echo '</div>';self::form('retry','');echo '<input type="hidden" name="campaign" value="'.esc_attr($id).'">';self::button(self::t('Retry failed / resume preparation','Επανάληψη αποτυχημένων / συνέχιση προετοιμασίας'));echo '</article>';
  }
  echo '</section>';
 }
 public static function account_menu($items) {
  $logout=$items['customer-logout']??null;unset($items['customer-logout']);
  $items['workshops']=self::t('My workshops','Τα εργαστήριά μου');
  if ($logout) { $items['customer-logout']=$logout; }return $items;
 }
 public static function account() {
  if (!is_user_logged_in()) { return; }
  nocache_headers(); $user=wp_get_current_user();
  echo '<div class="kwb-dashboard"><header class="kwb-hero"><h2>'.esc_html(self::t('My workshops','Τα εργαστήριά μου')).'</h2><p>'.esc_html(self::t('Your bookings, rewards and invitations.','Οι κρατήσεις, οι ανταμοιβές και οι προσκλήσεις σας.')).'</p></header>';
  $policy=KWB_Rewards::settings();
  if ($policy['enabled']) {
   echo '<section class="kwb-card"><h3>'.esc_html(self::t('Invite your friends','Προσκαλέστε φίλους')).'</h3><p>'.esc_html(sprintf(self::t('Qualifying friends required per reward: %d.','Επιλέξιμοι φίλοι ανά ανταμοιβή: %d.'),$policy['friends'])).'</p><p>'.esc_html(self::terms($policy)).'</p><label class="kwb-field"><span>'.esc_html(self::t('Your personal referral link','Ο προσωπικός σας σύνδεσμος')).'</span><input readonly type="url" value="'.esc_attr(KWB_Rewards::link($user->ID)).'" onclick="this.select()"></label><p>'.esc_html(sprintf(self::t('Qualifying friends under the current terms: %d','Επιλέξιμοι φίλοι με τους τρέχοντες όρους: %d'),count(KWB_Rewards::qualified($user->ID,$policy)))).'</p><p>'.esc_html(self::t('Your friend must open this link, register within 30 days and complete an eligible paid booking. Each new friend counts once.','Ο φίλος σας πρέπει να ανοίξει τον σύνδεσμο, να εγγραφεί εντός 30 ημερών και να ολοκληρώσει επιλέξιμη πληρωμένη κράτηση. Κάθε νέος φίλος μετρά μία φορά.')).'</p></section>';
  }
  echo '<section class="kwb-card"><h3>'.esc_html(self::t('My coupons','Τα κουπόνια μου')).'</h3>';
  $coupon_page=max(1,absint($_GET['coupon_page']??1));
  $coupons=get_posts(array('post_type'=>'shop_coupon','post_status'=>'publish','posts_per_page'=>20,'paged'=>$coupon_page,'meta_key'=>'_kwb_recipient','meta_value'=>strtolower($user->user_email)));
  if (!$coupons) { echo '<p>'.esc_html(self::t('Your rewards will appear here.','Οι ανταμοιβές σας θα εμφανιστούν εδώ.')).'</p>'; }
  foreach($coupons as $post) {
   $coupon=new WC_Coupon($post->ID);$expires=$coupon->get_date_expires();
   $active=$coupon->get_usage_count()<1 && (!$expires || $expires->getTimestamp()>time()) && KWB_Rewards::valid(true,$coupon);
   echo '<article class="kwb-coupon"><strong>'.esc_html($coupon->get_code()).'</strong><p>'.esc_html(self::terms($coupon->get_meta('_kwb_policy'))).'</p><p>'.esc_html(self::t('Expires: ','Λήγει: ').($expires?$expires->date_i18n('d/m/Y'):'—')).'</p>';
   if ($active) {
    echo '<form method="post">';wp_nonce_field('kwb_apply_coupon','kwb_coupon_nonce');echo '<input type="hidden" name="kwb_coupon" value="'.esc_attr($coupon->get_code()).'"><button type="submit" class="button">'.esc_html(self::t('Apply to my cart','Εφαρμογή στο καλάθι')).'</button></form>';
   } else { echo '<span>'.esc_html(self::t('Used, expired or no longer eligible','Χρησιμοποιήθηκε, έληξε ή δεν είναι πλέον επιλέξιμο')).'</span>'; }
   echo '</article>';
  }
  self::pagination('coupon_page',$coupon_page,count($coupons),20);
  echo '</section><section class="kwb-card"><h3>'.esc_html(self::t('My bookings','Οι κρατήσεις μου')).'</h3>';
  $page=max(1,absint($_GET['booking_page']??1));
  $orders=wc_get_orders(array('customer_id'=>$user->ID,'limit'=>10,'page'=>$page,'orderby'=>'date','order'=>'DESC','type'=>'shop_order'));
  $found=false;
  foreach($orders as $order) {
   foreach($order->get_items() as $item) {
    if (!KWB_Commercial::type($item)) { continue; }$found=true;
    echo '<article class="kwb-booking"><h4>'.esc_html($item->get_name()).'</h4><p>'.esc_html(wc_get_order_status_name($order->get_status()).' · '.self::t('Participants: ','Συμμετέχοντες: ').$item->get_quantity()).'</p><ul>';
    foreach(KWB_Booking::item_occurrences($item) as $occ) {
     $cancelled=isset(KWB_Booking::blackouts($item->get_product_id())[$occ['date']]);
     echo '<li>'.esc_html($occ['date'].' · '.$occ['start'].'–'.$occ['end']).' ';
     if ($cancelled) { echo '<strong>'.esc_html(self::t('Cancelled','Ακυρώθηκε')).'</strong>'; }
     elseif (KWB_Booking::occurrence_declined($item,$occ['id'])) {
      echo esc_html(self::t('Not attending','Δεν θα παρευρεθώ'));
      if ($order->is_paid() && $occ['start_dt']->getTimestamp()>time() && KWB_Booking::rsvp_enabled($item->get_product_id())) {
       echo ' · <a href="'.esc_url(KWB_RSVP::url($order->get_id(),$item->get_id(),$occ['id'],'yes')).'">'.esc_html(self::t('Attend if a place is available','Συμμετοχή αν υπάρχει διαθέσιμη θέση')).'</a>';
      }
     }
     elseif ($order->is_paid() && $occ['start_dt']->getTimestamp()>time() && KWB_Booking::rsvp_enabled($item->get_product_id())) {
      echo '<a href="'.esc_url(KWB_RSVP::url($order->get_id(),$item->get_id(),$occ['id'],'no')).'">'.esc_html(self::t('Decline attendance','Δήλωση απουσίας')).'</a>';
     }
     echo '</li>';
    }
    echo '</ul><a class="button" href="'.esc_url($order->get_view_order_url()).'">'.esc_html(self::t('Order details & calendar','Παραγγελία & ημερολόγιο')).'</a></article>';
   }
  }
  if (!$found) { echo '<p>'.esc_html(self::t('No workshop bookings on this page of orders.','Δεν υπάρχουν κρατήσεις εργαστηρίων σε αυτή τη σελίδα παραγγελιών.')).'</p>'; }
  self::pagination('booking_page',$page,count($orders),10);echo '</section></div>';
 }
 public static function pagination($key,$page,$count,$size) {
  echo '<nav class="kwb-pagination">';
  if ($page>1) { echo '<a href="'.esc_url(add_query_arg($key,$page-1)).'">'.esc_html(self::t('Previous','Προηγούμενη')).'</a>'; }
  if ($count===$size) { echo '<a href="'.esc_url(add_query_arg($key,$page+1)).'">'.esc_html(self::t('Next','Επόμενη')).'</a>'; }
  echo '</nav>';
 }
 public static function account_action() {
  if (!isset($_POST['kwb_coupon']) || !is_account_page()) { return; }
  if (!is_user_logged_in() || !isset($_POST['kwb_coupon_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['kwb_coupon_nonce'])),'kwb_apply_coupon')) { wp_die('Forbidden','',array('response'=>403)); }
  $coupon=new WC_Coupon(sanitize_text_field(wp_unslash($_POST['kwb_coupon'])));
  if (!$coupon->get_id() || strtolower(wp_get_current_user()->user_email)!==$coupon->get_meta('_kwb_recipient') || !KWB_Rewards::valid(true,$coupon)) { wp_die('Forbidden','',array('response'=>403)); }
  if (WC()->cart) { WC()->cart->apply_coupon($coupon->get_code()); }
  wp_safe_redirect(wc_get_cart_url());exit;
 }
}
