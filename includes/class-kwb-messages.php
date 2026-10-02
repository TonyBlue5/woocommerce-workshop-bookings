<?php
/** Reusable native WordPress message templates, available in Pro only. */
if (!defined('ABSPATH')) { exit; }
final class KWB_Messages {
 public static function init() {
  add_action('admin_menu',array(__CLASS__,'menu'));
  add_action('admin_post_kwb_message_template',array(__CLASS__,'save'));
 }
 public static function menu() {
  if (KWB_Commercial::can_manage()) { add_submenu_page('woocommerce',KWB_Dashboard::t('Message designer','Σχεδιαστής μηνυμάτων'),KWB_Dashboard::t('Message designer','Σχεδιαστής μηνυμάτων'),'manage_woocommerce','kwb-messages',array(__CLASS__,'page')); }
 }
 public static function clean($value) { return is_string($value)?wp_kses_post($value):''; }
 public static function templates() { return (array)get_option('kwb_message_templates',array()); }
 public static function body($config) {
  return !empty($config['html'])?wpautop(self::clean($config['message'])):'<p>'.nl2br(esc_html($config['message'])).'</p>';
 }
 public static function page() {
  if (!KWB_Commercial::can_manage()) { wp_die('Forbidden','',array('response'=>403)); }
  $templates=self::templates();$key=sanitize_key(is_scalar($_GET['template']??'')?$_GET['template']:'');$entry=$templates[$key]??array();
  echo '<div class="wrap"><h1>'.esc_html(KWB_Dashboard::t('Message designer','Σχεδιαστής μηνυμάτων')).'</h1><p>Powered by e-iT – Information Technology &amp; e-commerce</p><ul>';
  foreach($templates as $id=>$row) { echo '<li><a href="'.esc_url(add_query_arg(array('page'=>'kwb-messages','template'=>$id),admin_url('admin.php'))).'">'.esc_html($row['name']).'</a></li>'; }
  echo '</ul><p><a class="button" href="'.esc_url(admin_url('admin.php?page=kwb-messages')).'">'.esc_html(KWB_Dashboard::t('New template','Νέο πρότυπο')).'</a></p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
  wp_nonce_field('kwb_message_template');
  echo '<input type="hidden" name="action" value="kwb_message_template"><input type="hidden" name="template" value="'.esc_attr($key).'">';
  KWB_Dashboard::input('name',KWB_Dashboard::t('Template name','Όνομα προτύπου'),$entry['name']??'','text','required maxlength="100"');
  KWB_Dashboard::input('subject',KWB_Dashboard::t('Subject','Θέμα'),$entry['subject']??'','text','required maxlength="180"');
  wp_editor($entry['message']??'','kwb_template_body',array('textarea_name'=>'message','media_buttons'=>current_user_can('upload_files'),'textarea_rows'=>14));
  submit_button(KWB_Dashboard::t('Save template','Αποθήκευση προτύπου'));
  if ($key) { echo '<button class="button" name="delete" value="1">'.esc_html(KWB_Dashboard::t('Delete template','Διαγραφή προτύπου')).'</button>'; }
  echo '</form></div>';
 }
 public static function save() {
  if (!KWB_Commercial::can_manage()) { wp_die('Forbidden','',array('response'=>403)); }
  check_admin_referer('kwb_message_template');
  $key=sanitize_key(KWB_Dashboard::request('template'));$templates=self::templates();
  if (!empty($_POST['delete'])) { unset($templates[$key]); }
  else {
   $name=substr(KWB_Dashboard::request('name'),0,100);$subject=substr(KWB_Dashboard::request('subject'),0,180);
   $body=self::clean(wp_unslash($_POST['message']??''));
   if (!$name || !$subject || !$body || strlen($body)>30000 || (!$key && count($templates)>=50)) { wp_die('Invalid template'); }
   $key=$key?:wp_generate_uuid4();$templates[$key]=array('name'=>$name,'subject'=>$subject,'message'=>$body);
  }
  update_option('kwb_message_templates',$templates,false);
  wp_safe_redirect(admin_url('admin.php?page=kwb-messages'));exit;
 }
}
