<?php
/** Execute inside disposable WP/WooCommerce, against installed Lite and Pro packages. */
if(!defined('ABSPATH')) { exit(1); }
function kwbp_assert($ok,$message) { if(!$ok) { throw new RuntimeException($message); } }
add_filter('pre_wp_mail',static function(){return true;});
$greek=static function(){return 'el';};add_filter('locale',$greek);add_filter('determine_locale',$greek);
ob_start();KWB_UI::date_input('from','2026-10-03','required');$html=ob_get_clean();
kwbp_assert(strpos($html,'value="03/10/2026"')!==false&&strpos($html,'type="hidden"')!==false&&strpos($html,'name="from" value="2026-10-03"')!==false&&strpos($html,'type="date"')===false,'controlled Greek date is incorrect');
$p=new WC_Product_Simple();$p->set_name('Regression workshop');$p->set_regular_price(40);$pid=$p->save();update_post_meta($pid,'_kwb_enabled','yes');
update_post_meta($pid,'_kwb_weekly_schedule','4|12:00|13:00|10');
$item=new WC_Order_Item_Product();$item->set_product($p);
$fixture_month=((int)wp_date('Y')+2).'-10';$GLOBALS['kwbp_from']=$fixture_month.'-01';
$session=(new DateTimeImmutable($GLOBALS['kwbp_from']))->modify('first thursday of this month')->format('Y-m-d');
$occ=array('id'=>KWB_Booking::occ_id($pid,$session,'12:00','13:00'),'date'=>$session,'start'=>'12:00','end'=>'13:00','capacity'=>10);
$GLOBALS['kwbp_occ']=$occ;
KWB_Booking::order_meta($item,'',array('kwb_booking'=>array('type'=>'monthly','month'=>'2026-10','months'=>array('2026-10'),'occurrences'=>array($occ))),null);
$item->add_meta_data('_kwb_future_technical','secret-hash');$item->add_meta_data('_third_party_private','private-value');$item->save();
$formatted=$item->get_formatted_meta_data('',true);
foreach($formatted as $meta) { kwbp_assert(strpos($meta->key,'_')!==0,'internal metadata leaked'); }
$display=wc_display_item_meta($item,array('echo'=>false));
kwbp_assert(strpos($display,'_kwb_')===false&&strpos($display,'secret-hash')===false&&strpos($display,'2026-10')===false&&strpos($display,'Οκτώβριος')!==false,'customer/email metadata display leaked or not localized');
kwbp_assert($item->get_meta('_kwb_occurrences')!==''&&$item->get_meta('_kwb_future_technical')==='secret-hash','presentation deleted stored data');
kwbp_assert(in_array('_kwb_occurrences',apply_filters('woocommerce_hidden_order_itemmeta',array()),true),'admin edit metadata not hidden');
remove_filter('locale',$greek);remove_filter('determine_locale',$greek);
$display=wc_display_item_meta($item,array('echo'=>false));kwbp_assert(strpos($display,'Participation type')!==false&&strpos($display,'October')!==false,'historical Greek metadata did not switch to English');
if(KWB_EDITION==='lite') { echo "production-regression-ok\n";return; }
$admin=get_users(array('role'=>'administrator','number'=>1))[0];wp_set_current_user($admin->ID);
$regular=new WC_Product_Simple();$regular->set_name('Regression regular product');$regular->set_regular_price(5);$regular_id=$regular->save();
$results=KWB_UI::search_results('Regression');kwbp_assert(isset($results[$pid])&&!isset($results[$regular_id]),'selector includes non-workshop products');
$unsafe_title=static function($title,$id)use($pid){return (int)$id===(int)$pid?'<img src=x onerror=alert(1)>'.$title:$title;};
add_filter('the_title',$unsafe_title,10,2);$results=KWB_UI::search_results('Regression');
kwbp_assert(strpos($results[$pid],'<')===false&&strpos($results[$pid],'onerror')===false,'selector AJAX returned active title markup');
ob_start();KWB_UI::workshop_select('product',array($pid),false,true);$select=ob_get_clean();
kwbp_assert(strpos($select,'onerror')===false&&strpos($select,'data-allow_clear="true"')!==false,'selected label unsafe or all-workshops reset unavailable');
remove_filter('the_title',$unsafe_title,10);
wp_set_current_user(0);kwbp_assert(!KWB_UI::search_results('Regression'),'search disclosed products to guest');
$policy=KWB_Rewards::sanitize(array('enabled'=>1,'friends'=>2,'mode'=>'monthly'));update_option('kwb_referral_settings',$policy);
$owner=wp_insert_user(array('user_login'=>'regression-owner','user_pass'=>wp_generate_password(),'user_email'=>'regression-owner@example.org'));
parse_str((string)wp_parse_url(KWB_Rewards::link($owner),PHP_URL_QUERY),$query);
$_GET['kwb_ref']=$query['kwb_ref'];$_COOKIE['kwb_ref']='expired-or-invalid';
KWB_Rewards::capture();kwbp_assert((int)(KWB_Rewards::attribution()['id']??0)===$owner,'link capture did not replace invalid cookie');
unset($_GET['kwb_ref']);
function kwbp_cookie($owner) { $payload=$owner.'|'.(time()+DAY_IN_SECONDS);return $payload.'|'.hash_hmac('sha256',$payload,wp_salt('auth')); }
function kwbp_order($p,$email,$customer=0,$type='monthly',$qty=1,$date='') {
 $occ=$GLOBALS['kwbp_occ'];
 $date=$date?:$GLOBALS['kwbp_from'];
 $o=wc_create_order(array('customer_id'=>$customer));$o->set_billing_email($email);$o->set_date_created($date.' 12:00:00');
 $id=$o->add_product($p,$qty,array('subtotal'=>40*$qty,'total'=>40*$qty));$i=$o->get_item($id);$i->add_meta_data('_kwb_booking_type',$type);$i->add_meta_data('_kwb_occurrences',wp_json_encode(array($occ)));$i->save();
 $o->calculate_totals();do_action('woocommerce_checkout_create_order',$o);$o->save();return $o;
}
$_COOKIE['kwb_ref']=kwbp_cookie($owner);
$a=kwbp_order($p,'regression-a@example.org');$b=kwbp_order($p,'regression-b@example.org');unset($_COOKIE['kwb_ref']);
$a->update_status('completed');$b->update_status('completed');
kwbp_assert(count(KWB_Rewards::qualified($owner,$policy))===2,'two distinct guests failed');
$coupons=get_posts(array('post_type'=>'shop_coupon','numberposts'=>-1,'meta_key'=>'_kwb_owner','meta_value'=>$owner));kwbp_assert(count($coupons)===1,'milestone coupon missing');
$_COOKIE['kwb_ref']=kwbp_cookie($owner);$again=kwbp_order($p,'REGRESSION-A@example.org');$again->update_status('completed');kwbp_assert(count(KWB_Rewards::qualified($owner,$policy))===2,'same billing email counted twice');
$registered=wp_insert_user(array('user_login'=>'regression-existing','user_pass'=>wp_generate_password(),'user_email'=>'regression-existing@example.org'));
delete_user_meta($registered,'_kwb_referrer');wp_set_current_user($registered);
unset($_COOKIE['kwb_ref']);$_GET['kwb_ref']=$query['kwb_ref'];KWB_Rewards::capture();unset($_GET['kwb_ref']);
kwbp_assert((int)(KWB_Rewards::attribution()['id']??0)===$owner,'logged-in link click lost attribution');
$logged=kwbp_order($p,'regression-existing@example.org',$registered);$logged->update_status('completed');kwbp_assert(count(KWB_Rewards::qualified($owner,$policy))===3,'existing logged-in cookie referral failed');
wp_set_current_user(0);
$self=kwbp_order($p,'regression-owner@example.org');$self->update_status('completed');kwbp_assert(!$self->get_meta('_kwb_referrer'),'self-referral accepted');
$wrong=kwbp_order($p,'regression-wrong@example.org',0,'single');$wrong->update_status('completed');
$cancelled=kwbp_order($p,'regression-cancelled@example.org');$cancelled->update_status('cancelled');
$pending=kwbp_order($p,'regression-pending@example.org');
kwbp_assert(count(KWB_Rewards::qualified($owner,$policy))===3,'nonqualifying purchase counted');
$friend=wp_insert_user(array('user_login'=>'regression-later','user_pass'=>wp_generate_password(),'user_email'=>'regression-a@example.org'));
global $wpdb;
kwbp_assert((int)$wpdb->get_var($wpdb->prepare("SELECT friend_id FROM {$wpdb->prefix}kwb_guest_referrals WHERE email_hash=%s",hash('sha256','regression-a@example.org')))===$friend,'later account not linked to guest history');
$later=kwbp_order($p,'regression-a@example.org',$friend);$later->update_status('completed');kwbp_assert(count(KWB_Rewards::qualified($owner,$policy))===3,'guest registration double-counted');
$b->update_status('refunded');$logged->update_status('cancelled');kwbp_assert(count(KWB_Rewards::qualified($owner,$policy))===1,'refund/cancellation remained eligible');
wp_set_current_user($owner);kwbp_assert(!KWB_Rewards::valid(true,new WC_Coupon($coupons[0]->ID)),'invalid milestone coupon usable');
// Reconciliation replays stored evidence and issues no duplicate milestone.
KWB_Rewards::reconcile();kwbp_assert(count(get_posts(array('post_type'=>'shop_coupon','numberposts'=>-1,'meta_key'=>'_kwb_owner','meta_value'=>$owner)))===1,'reconciliation duplicated coupon');
unset($_COOKIE['kwb_ref']);
$q=new WC_Product_Simple();$q->set_name('Regression performance');$q->set_regular_price(40);$qid=$q->save();update_post_meta($qid,'_kwb_enabled','yes');
update_post_meta($qid,'_kwb_weekly_schedule','4|12:00|13:00|10');$occ['id']=KWB_Booking::occ_id($qid,$session,'12:00','13:00');
$GLOBALS['kwbp_occ']=$occ;
$sale=kwbp_order($q,'regression-sale@example.org',0,'monthly',2);$sale->update_status('completed');
$previous=(new DateTimeImmutable($GLOBALS['kwbp_from']))->modify('-31 days')->format('Y-m-d');
$prev=kwbp_order($q,'regression-prev@example.org',0,'single',1,$previous);$prev->update_status('completed');
$r=KWB_Commercial::performance($fixture_month.'-01',$fixture_month.'-31',$qid)[$qid.':'.get_woocommerce_currency()];
kwbp_assert($r['bookings']===1&&$r['participants']===2&&$r['revenue']===80.0&&$r['per_booking']===80.0&&$r['per_participant']===40.0,'performance revenue/quantity arithmetic incorrect');
$capacity=count(KWB_Booking::month_occurrences($qid,$fixture_month,false))*10;
kwbp_assert($r['trend']===100.0&&$r['capacity']===$capacity&&$r['occupied']===3&&abs($r['occupancy']-300/$capacity)<0.01,'previous period or session-based occupancy incorrect');
wp_set_current_user($admin->ID);$_GET=array('from'=>'2026-10-01','to'=>'2026-10-31','product'=>$qid);ob_start();KWB_Dashboard::analytics();$html=ob_get_clean();kwbp_assert(strpos($html,'kwb-performance')!==false&&strpos($html,'kwb_search_workshops')!==false&&strpos($html,'type="date"')===false,'analytics controls not integrated');
update_option('kwb_browser_product',$pid,false);
echo "production-regression-ok\n";
