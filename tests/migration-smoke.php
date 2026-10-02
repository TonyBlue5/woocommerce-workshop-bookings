<?php
if(!defined('ABSPATH')){exit(1);}
if(getenv('KWB_TEST_MODE')==='prepare'){
 $p=new WC_Product_Simple();$p->set_name('Edition migration');$p->set_regular_price(20);$pid=$p->save();
 update_post_meta($pid,'_kwb_enabled','yes');update_post_meta($pid,'_kwb_rsvp_override','off');
 $order=wc_create_order();$iid=$order->add_product($p,1);$item=$order->get_item($iid);
 $occ=array(array('id'=>'migration-occurrence','date'=>'2030-11-10','start'=>'10:00','end'=>'11:00','capacity'=>7));
 $item->add_meta_data('_kwb_occurrences',wp_json_encode($occ));$item->add_meta_data('_kwb_booking_type','monthly');$item->add_meta_data('_kwb_declined_occurrences',array('migration-occurrence'));$item->save();
 $order->set_status('completed');$order->save();
 $settings=KWB_Settings::all();$settings['release_on_no']=1;$settings['reminder_message_template']='Preserved message';update_option('kwb_settings',$settings);
 update_option('kwb_migration_fixture',array('pid'=>$pid,'oid'=>$order->get_id(),'iid'=>$iid,'occ'=>wp_json_encode($occ)),false);return;
}
$f=get_option('kwb_migration_fixture');$item=wc_get_order($f['oid'])->get_item($f['iid']);
if($item->get_meta('_kwb_occurrences')!==$f['occ']||!KWB_Booking::occurrence_declined($item,'migration-occurrence')||get_post_meta($f['pid'],'_kwb_rsvp_override',true)!=='off'||KWB_Settings::get('reminder_message_template')!=='Preserved message'){throw new RuntimeException('Edition switch changed existing data');}
if('lite'===KWB_EDITION){
 $settings=KWB_Settings::sanitize(array('reminder_enabled'=>1));
 if($settings['release_on_no']!==1||$settings['reminder_message_template']!=='Preserved message'){throw new RuntimeException('Lite settings erased preserved Pro settings');}
}
echo "migration-smoke-ok\n";
