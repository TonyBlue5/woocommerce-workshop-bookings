<?php
if (!defined('ABSPATH')) { exit; }

/** Shared controls keep presentation separate from persisted dates and IDs. */
final class KWB_UI {
 public static function t($en,$el) { return KWB_I18n::is_greek()?$el:$en; }
 public static function init() { add_action('wp_ajax_kwb_search_workshops',array(__CLASS__,'search')); }
 public static function assets() {
  wp_enqueue_script('kwb-controls',plugins_url('../assets/kwb-controls.js',__FILE__),array('jquery','jquery-ui-datepicker'),KWB_VERSION,true);
  wp_enqueue_style('kwb-controls',plugins_url('../assets/kwb-controls.css',__FILE__),array(),KWB_VERSION);
  wp_localize_script('kwb-controls','KWB_CONTROLS',array('greek'=>KWB_I18n::is_greek(),'months'=>KWB_I18n::t('months'),'invalid'=>self::t('Enter a valid date in DD/MM/YYYY format.','Συμπληρώστε έγκυρη ημερομηνία ΗΗ/ΜΜ/ΕΕΕΕ.')));
 }
 public static function date($date) { return KWB_Booking::date_ok($date)?substr($date,8,2).'/'.substr($date,5,2).'/'.substr($date,0,4):''; }
 public static function date_input($name,$value='',$extra='',$month=false) {
  $display=$month?(preg_match('/^\d{4}-\d{2}$/',$value)?substr($value,5,2).'/'.substr($value,0,4):''):self::date($value);
  echo '<span class="kwb-date-control" data-month="'.($month?'1':'0').'"><input type="text" class="kwb-date-display" value="'.esc_attr($display).'" placeholder="'.($month?'MM/YYYY':'DD/MM/YYYY').'" autocomplete="off" '.$extra.'><input type="hidden" class="kwb-date-iso" name="'.esc_attr($name).'" value="'.esc_attr($value).'"></span>';
 }
 public static function workshop_select($name,$ids=array(),$multiple=false,$all=false) {
  echo '<select class="wc-product-search" name="'.esc_attr($name).'" '.($multiple?'multiple="multiple"':'').' style="width:100%;min-width:240px" data-action="kwb_search_workshops" data-allow_clear="'.($all||$multiple?'true':'false').'" data-placeholder="'.esc_attr($all?self::t('All workshops','Όλα τα εργαστήρια'):self::t('Search workshops','Αναζήτηση εργαστηρίων')).'" '.(!$all&&!$multiple?'required':'').'>';
  if(!$multiple) { echo '<option value="'.($all?'0':'').'">'.esc_html($all?self::t('All workshops','Όλα τα εργαστήρια'):self::t('Select workshop','Επιλέξτε εργαστήριο')).'</option>'; }
  foreach((array)$ids as $id) { if($id && KWB_Booking::enabled($id) && current_user_can('edit_post',$id)) { echo '<option selected value="'.esc_attr($id).'">'.htmlspecialchars(wp_strip_all_tags(get_the_title($id)),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8',true).'</option>'; } }
  echo '</select>';
 }
 public static function search_results($term) {
  $query=new WP_Query(array('post_type'=>'product','post_status'=>array('publish','private','draft','pending'),'s'=>$term,'posts_per_page'=>30,'orderby'=>'title','order'=>'ASC','meta_key'=>KWB_Booking::META_ENABLED,'meta_value'=>'yes'));
  // WooCommerce's SelectWoo configuration returns markup unchanged.
  $results=array();foreach($query->posts as $post) { if(current_user_can('edit_post',$post->ID)) { $results[$post->ID]=esc_html(wp_strip_all_tags(get_the_title($post->ID))); } }
  return $results;
 }
 public static function search() {
  check_ajax_referer('search-products','security');
  if(!current_user_can('manage_woocommerce')) { wp_send_json_error(null,403); }
  $term=isset($_GET['term'])&&is_string($_GET['term'])?sanitize_text_field(wp_unslash($_GET['term'])):'';
  wp_send_json(self::search_results($term));
 }
}
