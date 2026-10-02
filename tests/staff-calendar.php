<?php
if(!defined('ABSPATH')){exit(1);}
function kwbs_assert($ok,$message){if(!$ok){throw new RuntimeException($message);}}
function kwbs_create($input){try{return KWB_Admin_Calendar::create($input);}finally{KWB_Booking::unlock_capacity();}}
function kwbs_attend($oid,$iid,$pid,$occ,$op){try{KWB_Admin_Calendar::attendance($oid,$iid,$pid,$occ,$op);}finally{KWB_Booking::unlock_capacity();}}
add_filter('pre_wp_mail',static function(){return true;});
$admin=get_user_by('login','admin');wp_set_current_user($admin->ID);
$manager=wp_insert_user(array('user_login'=>'calendar-manager','user_pass'=>wp_generate_password(),'role'=>'shop_manager'));
$p=new WC_Product_Simple();$p->set_name('Telephone workshop');$p->set_regular_price(20);$p->set_virtual(true);$pid=$p->save();
foreach(array('_kwb_enabled'=>'yes','_kwb_single_enabled'=>'yes','_kwb_single_price'=>'20','_kwb_monthly_enabled'=>'yes','_kwb_monthly_price'=>'120') as $key=>$value){update_post_meta($pid,$key,$value);}
$schedule=array();for($i=1;$i<=7;$i++){$schedule[]=$i.'|12:00|13:00|2';}update_post_meta($pid,'_kwb_weekly_schedule',implode("\n",$schedule));
$month=(new DateTimeImmutable('first day of next month',wp_timezone()))->format('Y-m');$sessions=array_values(KWB_Booking::month_occurrences($pid,$month,true));$occ=$sessions[0]['id'];$other=$sessions[1]['id'];
$input=array('product'=>$pid,'name'=>'Phone customer','email'=>'phone@example.org','phone'=>'2101234567','quantity'=>1,'mode'=>'monthly','month'=>$month,'occurrence'=>$occ,'request_id'=>wp_generate_uuid4());
wp_set_current_user($manager);kwbs_assert(KWB_Admin_Calendar::allowed($pid),'Shop manager cannot manage workshops');
$oid=kwbs_create($input);$order=wc_get_order($oid);$items=$order->get_items();$item=reset($items);$iid=$item->get_id();
kwbs_assert($order->has_status('on-hold')&&!$order->is_paid(),'Phone order incorrectly recorded payment');
kwbs_assert((float)$order->get_total()===120.0,'Phone monthly price changed');
kwbs_assert(count(KWB_Booking::item_occurrences($item))===count($sessions),'Monthly phone booking lost occurrences');
kwbs_assert(kwbs_create($input)===$oid&&KWB_Booking::booked($pid,$occ)===1,'Duplicate submission created another booking');
$settings=KWB_Settings::all();$settings['release_on_no']=0;update_option('kwb_settings',$settings);
kwbs_attend($oid,$iid,$pid,$occ,'release');
kwbs_assert(KWB_Booking::booked($pid,$occ)===0&&KWB_Booking::booked($pid,$other)===1,'Staff release depends on RSVP setting or releases other monthly sessions');
$single=$input;$single['mode']='single';$single['quantity']=2;$single['request_id']=wp_generate_uuid4();$second=kwbs_create($single);
$blocked=false;try{kwbs_attend($oid,$iid,$pid,$occ,'restore');}catch(Exception $e){$blocked=true;}kwbs_assert($blocked&&KWB_Booking::booked($pid,$occ)===2,'Restoring oversold a full session');
$third=$single;$third['request_id']=wp_generate_uuid4();$blocked=false;try{kwbs_create($third);}catch(Exception $e){$blocked=true;}kwbs_assert($blocked,'Phone booking oversold a full session');
wc_get_order($second)->update_status('cancelled');kwbs_assert(KWB_Booking::booked($pid,$occ)===0,'Cancelling phone order did not return places');
kwbs_attend($oid,$iid,$pid,$occ,'restore');kwbs_assert(KWB_Booking::booked($pid,$occ)===1,'Staff could not restore available place');
kwbs_attend($oid,$iid,$pid,$occ,'release');$order=wc_get_order($oid);KWB_Booking::reserve_order($order);$order->save();KWB_Booking::unlock_capacity();
kwbs_assert(KWB_Booking::booked($pid,$occ)===0,'Payment reservation restored staff-released place');
$order->update_status('cancelled');kwbs_assert(KWB_Booking::booked($pid,$other)===0,'Cancelling monthly order did not free every occurrence');
wp_set_current_user(0);$blocked=false;try{kwbs_create($third);}catch(Exception $e){$blocked=true;}kwbs_assert($blocked,'Anonymous user created staff reservation');
$customer=wp_insert_user(array('user_login'=>'calendar-customer','user_pass'=>wp_generate_password(),'role'=>'customer'));wp_set_current_user($customer);
$blocked=false;try{kwbs_attend($oid,$iid,$pid,$occ,'restore');}catch(Exception $e){$blocked=true;}kwbs_assert($blocked,'Customer changed staff attendance');
wp_set_current_user($manager);$_POST=array('op'=>'create');$_REQUEST=$_POST;
$die=static function(){return static function(){throw new RuntimeException('blocked');};};add_filter('wp_die_handler',$die,999);
$blocked=false;try{KWB_Admin_Calendar::handle();}catch(RuntimeException $e){$blocked=true;}kwbs_assert($blocked,'Staff endpoint accepted missing nonce');
remove_filter('wp_die_handler',$die,999);$_POST=array();$_REQUEST=array();wp_set_current_user($admin->ID);
$single['request_id']=wp_generate_uuid4();$single['quantity']=1;kwbs_create($single);
$_GET=array('product'=>$pid,'month'=>$month,'occurrence'=>$occ);ob_start();KWB_Admin_Calendar::page();$html=ob_get_clean();
kwbs_assert(strpos($html,'kwb-staff-grid')!==false&&strpos($html,'Phone customer')!==false,'Calendar did not render participant details');
if(getenv('GITHUB_WORKSPACE')){wp_mkdir_p(getenv('GITHUB_WORKSPACE').'/ui-preview');file_put_contents(getenv('GITHUB_WORKSPACE').'/ui-preview/calendar.html','<!doctype html><html><meta charset="utf-8"><meta name="viewport" content="width=device-width"><style>'.file_get_contents(KWB_PLUGIN_DIR.'assets/kwb-dashboard.css').'</style>'.$html.'</html>');}
$_GET=array();echo "staff-calendar-ok\n";
