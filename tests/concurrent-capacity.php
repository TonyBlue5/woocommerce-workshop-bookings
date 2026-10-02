<?php
if(!defined('ABSPATH')){exit(1);}
add_filter('pre_wp_mail',static function(){return true;});
$mode=getenv('KWB_TEST_MODE');
if('prepare'===$mode){
 $p=new WC_Product_Simple();$p->set_name('Last workshop place');$p->set_regular_price(20);$pid=$p->save();
 update_post_meta($pid,'_kwb_enabled','yes');$schedule=array();for($i=1;$i<=7;$i++){$schedule[]=$i.'|12:00|13:00|1';}update_post_meta($pid,'_kwb_weekly_schedule',implode("\n",$schedule));
 $date=wp_date('Y-m-d',time()+2*DAY_IN_SECONDS);$occ=array('id'=>KWB_Booking::occ_id($pid,$date,'12:00','13:00'),'date'=>$date,'start'=>'12:00','end'=>'13:00','capacity'=>1);$orders=array();
 for($i=0;$i<2;$i++){
  $o=wc_create_order();$iid=$o->add_product($p,1);$item=$o->get_item($iid);$item->add_meta_data('_kwb_occurrences',wp_json_encode(array($occ)));$item->add_meta_data('_kwb_booking_type','single');$item->save();$o->save();$orders[]=$o->get_id();
 }
 update_option('kwb_capacity_fixture',array('pid'=>$pid,'occ'=>$occ,'orders'=>$orders),false);return;
}
$f=get_option('kwb_capacity_fixture');
if('reserve'===$mode){
 $index=(int)getenv('KWB_ORDER_INDEX');$order=wc_get_order($f['orders'][$index]);
 try{do_action($index?'woocommerce_store_api_checkout_update_order_meta':'woocommerce_checkout_create_order',$order);$order->save();usleep(500000);}
 catch(Exception $e){$order->update_meta_data('_kwb_test_rejected',1);$order->save();}
 finally{KWB_Booking::unlock_capacity();}return;
}
if('assert'===$mode){
 $reserved=array();$rejected=array();foreach($f['orders'] as $id){$o=wc_get_order($id);if($o->get_meta('_kwb_capacity_hold'))$reserved[]=$o;if($o->get_meta('_kwb_test_rejected'))$rejected[]=$o;}
 if(count($reserved)!==1||count($rejected)!==1||KWB_Booking::booked($f['pid'],$f['occ']['id'])!==1){throw new RuntimeException('Concurrent checkout oversold or failed to reserve the last place');}
 $reserved[0]->update_status('cancelled');
 if(KWB_Booking::booked($f['pid'],$f['occ']['id'])!==0){throw new RuntimeException('Cancellation did not free the pending reservation');}
 KWB_Booking::reserve_order($rejected[0]);$rejected[0]->save();KWB_Booking::unlock_capacity();
 $blocked=false;try{KWB_Booking::reserve_payment($reserved[0]->get_id());}catch(Exception $e){$blocked=true;}finally{KWB_Booking::unlock_capacity();}
 if(!$blocked||KWB_Booking::booked($f['pid'],$f['occ']['id'])!==1){throw new RuntimeException('Late payment oversold a reallocated place');}
 echo "concurrent-capacity-ok\n";
}
