<?php
if(!defined('ABSPATH')){exit(1);}
add_filter('pre_wp_mail',static function(){return true;});
if(getenv('KWB_TEST_MODE')==='prepare'){
 $p=new WC_Product_Simple();$p->set_name('Attendance HTTP test');$p->set_regular_price(20);$pid=$p->save();
 $order=wc_create_order();$order->set_billing_email('attendance@example.org');$iid=$order->add_product($p,1);
 $date=wp_date('Y-m-d',time()+DAY_IN_SECONDS);$occ=KWB_Booking::occ_id($pid,$date,'12:00','13:00');
 $item=$order->get_item($iid);$item->add_meta_data('_kwb_occurrences',wp_json_encode(array(array('id'=>$occ,'date'=>$date,'start'=>'12:00','end'=>'13:00','capacity'=>2))));$item->save();
 $order->calculate_totals();$order->set_status('completed');$order->save();
 $settings=KWB_Settings::all();$settings['rsvp_enabled']=1;$settings['release_on_no']=1;update_option('kwb_settings',$settings);
 $fixture=array('order'=>$order->get_id(),'item'=>$iid,'occ'=>$occ,'url'=>KWB_RSVP::url($order->get_id(),$iid,$occ,'no'));
 update_option('kwb_http_fixture',$fixture,false);file_put_contents('/tmp/kwb-rsvp.json',wp_json_encode($fixture));return;
}
$f=get_option('kwb_http_fixture');$item=wc_get_order($f['order'])->get_item($f['item']);$declined=KWB_Booking::occurrence_declined($item,$f['occ']);
if((getenv('KWB_TEST_MODE')==='after-post')!==$declined){throw new RuntimeException('Attendance changed without confirmation or failed to save');}
echo "rsvp-http-ok\n";
