<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class KWB_Google_Calendar {
	const TOKEN_OPTION='kwb_google_tokens';
	const SECRET_OPTION='kwb_google_client_secret';
	const REGISTRY_OPTION='kwb_google_event_registry';
	const CRON_HOOK='kwb_google_rsvp_sync';
	const AUTH_URL='https://accounts.google.com/o/oauth2/v2/auth';
	const TOKEN_URL='https://oauth2.googleapis.com/token';
	const REVOKE_URL='https://oauth2.googleapis.com/revoke';
	const API_BASE='https://www.googleapis.com/calendar/v3';
	const SCOPE_EVENTS='https://www.googleapis.com/auth/calendar.events';
	const SCOPE_LIST='https://www.googleapis.com/auth/calendar.calendarlist.readonly';

	public static function init(){
		add_action('admin_post_kwb_google_connect',array(__CLASS__,'connect'));
		add_action('admin_post_kwb_google_callback',array(__CLASS__,'callback'));
		add_action('admin_post_kwb_google_disconnect',array(__CLASS__,'disconnect'));
		add_action('admin_post_kwb_google_test',array(__CLASS__,'test_connection'));
		add_action('admin_post_kwb_google_sync_now',array(__CLASS__,'sync_now'));
		add_action('woocommerce_order_status_processing',array(__CLASS__,'sync_order'),20);
		add_action('woocommerce_order_status_completed',array(__CLASS__,'sync_order'),20);
		add_action('woocommerce_order_status_cancelled',array(__CLASS__,'remove_order_events'),20);
		add_action('woocommerce_order_status_refunded',array(__CLASS__,'remove_order_events'),20);
		add_action('woocommerce_order_status_failed',array(__CLASS__,'remove_order_events'),20);
		add_filter('cron_schedules',array(__CLASS__,'cron_schedules'));
		add_action(self::CRON_HOOK,array(__CLASS__,'sync_rsvp'));
		add_action('update_option_'.KWB_Settings::OPTION,array(__CLASS__,'settings_updated'),10,2);
		self::ensure_cron();
	}

	public static function redirect_uri(){
		return admin_url('admin-post.php?action=kwb_google_callback');
	}

	private static function crypto_key(){
		return hash('sha256',wp_salt('auth').'|'.wp_salt('secure_auth').'|kwb-google',true);
	}
	private static function encrypt($value){
		if(''===(string)$value)return'';
		if(!function_exists('openssl_encrypt'))return'';
		$iv=random_bytes(12);$tag='';
		$cipher=openssl_encrypt((string)$value,'aes-256-gcm',self::crypto_key(),OPENSSL_RAW_DATA,$iv,$tag,'kwb-google');
		if(false===$cipher)return'';
		return base64_encode("KWB1".$iv.$tag.$cipher);
	}
	private static function decrypt($value){
		if(''===(string)$value||!function_exists('openssl_decrypt'))return'';
		$raw=base64_decode((string)$value,true);if(false===$raw||0!==strpos($raw,'KWB1')||strlen($raw)<32)return'';
		$iv=substr($raw,4,12);$tag=substr($raw,16,16);$cipher=substr($raw,32);
		$plain=openssl_decrypt($cipher,'aes-256-gcm',self::crypto_key(),OPENSSL_RAW_DATA,$iv,$tag,'kwb-google');
		return false===$plain?'':$plain;
	}
	private static function set_encrypted_option($name,$value){
		$enc=self::encrypt($value);if(''===$enc&&''!==(string)$value)return false;
		return update_option($name,$enc,false);
	}
	private static function get_encrypted_option($name){
		return self::decrypt((string)get_option($name,''));
	}

	public static function store_client_secret($secret){return self::set_encrypted_option(self::SECRET_OPTION,(string)$secret);}
	public static function client_secret(){
		if(defined('KWB_GOOGLE_CLIENT_SECRET')&&KWB_GOOGLE_CLIENT_SECRET)return(string)KWB_GOOGLE_CLIENT_SECRET;
		$secret=self::get_encrypted_option(self::SECRET_OPTION);
		if($secret)return$secret;
		// One-time migration from early v0.5 development builds.
		$legacy=(string)KWB_Settings::get('google_client_secret','');
		if($legacy){self::store_client_secret($legacy);$all=KWB_Settings::all();$all['google_client_secret']='';update_option(KWB_Settings::OPTION,$all,false);return$legacy;}
		return'';
	}
	public static function has_client_secret(){return''!==self::client_secret();}
	public static function client_id(){
		if(defined('KWB_GOOGLE_CLIENT_ID')&&KWB_GOOGLE_CLIENT_ID)return(string)KWB_GOOGLE_CLIENT_ID;
		return trim((string)KWB_Settings::get('google_client_id',''));
	}

	private static function save_tokens($tokens){
		return self::set_encrypted_option(self::TOKEN_OPTION,wp_json_encode($tokens));
	}
	private static function tokens(){
		$raw=self::get_encrypted_option(self::TOKEN_OPTION);if(!$raw)return array();
		$t=json_decode($raw,true);return is_array($t)?$t:array();
	}
	public static function connected(){return!empty(self::tokens()['refresh_token'])||!empty(self::tokens()['access_token']);}

	private static function admin_return($args=array()){
		return add_query_arg(array_merge(array('page'=>'kwb-settings'),$args),admin_url('admin.php'));
	}
	private static function guard($action){
		if(!current_user_can('manage_woocommerce'))wp_die('Δεν έχετε δικαίωμα για αυτή την ενέργεια.',403);
		check_admin_referer($action);
	}
	private static function error_return($code){
		wp_safe_redirect(self::admin_return(array('kwb_google'=>'error','kwb_google_code'=>sanitize_key($code))));exit;
	}

	public static function connect(){
		self::guard('kwb_google_connect');
		$id=self::client_id();$secret=self::client_secret();
		if(!$id||!$secret)self::error_return('missing_credentials');
		$state=bin2hex(random_bytes(32));set_transient('kwb_google_state_'.get_current_user_id(),hash('sha256',$state),10*MINUTE_IN_SECONDS);
		$url=add_query_arg(array(
			'client_id'=>$id,
			'redirect_uri'=>self::redirect_uri(),
			'response_type'=>'code',
			'scope'=>self::SCOPE_EVENTS.' '.self::SCOPE_LIST,
			'access_type'=>'offline',
			'include_granted_scopes'=>'true',
			'prompt'=>'consent select_account',
			'state'=>$state,
		),self::AUTH_URL);
		wp_redirect(esc_url_raw($url));exit;
	}

	public static function callback(){
		if(!current_user_can('manage_woocommerce'))wp_die('Δεν έχετε δικαίωμα για αυτή την ενέργεια.',403);
		// OAuth state protects this callback from CSRF; Google supplies the authorization code.
		$state=isset($_GET['state'])?sanitize_text_field(wp_unslash($_GET['state'])):'';
		$expected=get_transient('kwb_google_state_'.get_current_user_id());
		delete_transient('kwb_google_state_'.get_current_user_id());
		if(!$state||!$expected||!hash_equals((string)$expected,hash('sha256',$state)))self::error_return('state');
		if(!empty($_GET['error']))self::error_return(sanitize_key(wp_unslash($_GET['error'])));
		$code=isset($_GET['code'])?sanitize_text_field(wp_unslash($_GET['code'])):'';
		if(!$code)self::error_return('missing_code');
		$res=wp_remote_post(self::TOKEN_URL,array('timeout'=>20,'body'=>array(
			'client_id'=>self::client_id(),'client_secret'=>self::client_secret(),'code'=>$code,
			'grant_type'=>'authorization_code','redirect_uri'=>self::redirect_uri(),
		)));
		if(is_wp_error($res)||200!==wp_remote_retrieve_response_code($res))self::error_return('token_exchange');
		$new=json_decode(wp_remote_retrieve_body($res),true);if(!is_array($new)||empty($new['access_token']))self::error_return('token_response');
		$old=self::tokens();if(empty($new['refresh_token'])&&!empty($old['refresh_token']))$new['refresh_token']=$old['refresh_token'];
		$new['obtained_at']=time();
		if(!self::save_tokens($new))self::error_return('token_storage');
		delete_transient('kwb_google_calendars');
		wp_safe_redirect(self::admin_return(array('kwb_google'=>'connected')));exit;
	}

	public static function disconnect(){
		self::guard('kwb_google_disconnect');
		$t=self::tokens();$token=(string)($t['refresh_token']??$t['access_token']??'');
		if($token)wp_remote_post(self::REVOKE_URL,array('timeout'=>10,'body'=>array('token'=>$token)));
		delete_option(self::TOKEN_OPTION);delete_transient('kwb_google_calendars');
		wp_safe_redirect(self::admin_return(array('kwb_google'=>'disconnected')));exit;
	}

	private static function refresh_access_token(){
		$t=self::tokens();$refresh=(string)($t['refresh_token']??'');if(!$refresh)return'';
		$res=wp_remote_post(self::TOKEN_URL,array('timeout'=>20,'body'=>array(
			'client_id'=>self::client_id(),'client_secret'=>self::client_secret(),
			'refresh_token'=>$refresh,'grant_type'=>'refresh_token',
		)));
		if(is_wp_error($res)||200!==wp_remote_retrieve_response_code($res)){self::set_status('error','Δεν ήταν δυνατή η ανανέωση του Google access token.');return'';}
		$new=json_decode(wp_remote_retrieve_body($res),true);if(!is_array($new)||empty($new['access_token']))return'';
		$new['refresh_token']=$refresh;$new['obtained_at']=time();self::save_tokens($new);
		return(string)$new['access_token'];
	}
	private static function access_token(){
		$t=self::tokens();if(empty($t['access_token']))return self::refresh_access_token();
		$expires=max(60,absint($t['expires_in']??3600));$at=absint($t['obtained_at']??0);
		if(!$at||time()>=$at+$expires-120)return self::refresh_access_token();
		return(string)$t['access_token'];
	}

	private static function request($method,$path,$body=null,$retry=true){
		$token=self::access_token();if(!$token)return new WP_Error('kwb_google_not_connected','Google Calendar is not connected.');
		$args=array('method'=>$method,'timeout'=>20,'headers'=>array('Authorization'=>'Bearer '.$token,'Accept'=>'application/json'));
		if(null!==$body){$args['headers']['Content-Type']='application/json';$args['body']=wp_json_encode($body);}
		$res=wp_remote_request(self::API_BASE.$path,$args);
		if(!is_wp_error($res)&&401===wp_remote_retrieve_response_code($res)&&$retry){self::refresh_access_token();return self::request($method,$path,$body,false);}
		if(is_wp_error($res))return$res;
		$status=wp_remote_retrieve_response_code($res);$decoded=json_decode(wp_remote_retrieve_body($res),true);
		if($status<200||$status>=300){
			$msg=is_array($decoded)&&!empty($decoded['error']['message'])?$decoded['error']['message']:'Google Calendar API error '.$status;
			return new WP_Error('kwb_google_api',$msg,array('status'=>$status));
		}
		return is_array($decoded)?$decoded:array();
	}

	public static function calendars($force=false){
		if(!$force){$cached=get_transient('kwb_google_calendars');if(is_array($cached))return$cached;}
		$data=self::request('GET','/users/me/calendarList?maxResults=250');if(is_wp_error($data))return$data;
		$list=array();foreach((array)($data['items']??array())as$cal){if(empty($cal['id']))continue;$list[] = array(
			'id'=>(string)$cal['id'],'summary'=>(string)($cal['summary']??$cal['id']),'primary'=>!empty($cal['primary']),
			'accessRole'=>(string)($cal['accessRole']??''),
		);}
		set_transient('kwb_google_calendars',$list,10*MINUTE_IN_SECONDS);return$list;
	}

	public static function test_connection(){
		self::guard('kwb_google_test');
		if(!self::connected())self::error_return('not_connected');
		$calendar=(string)KWB_Settings::get('google_calendar_id','primary');$now=time()+DAY_IN_SECONDS;
		$body=array(
			'summary'=>'Workshop Bookings — connection test',
			'description'=>'Temporary event created automatically to verify write access. It will be deleted immediately.',
			'start'=>array('dateTime'=>wp_date(DATE_RFC3339,$now,wp_timezone())),
			'end'=>array('dateTime'=>wp_date(DATE_RFC3339,$now+5*MINUTE_IN_SECONDS,wp_timezone())),
		);
		$event=self::request('POST','/calendars/'.rawurlencode($calendar).'/events?sendUpdates=none',$body);
		if(is_wp_error($event)||empty($event['id']))self::error_return('test_create');
		self::request('DELETE','/calendars/'.rawurlencode($calendar).'/events/'.rawurlencode($event['id']).'?sendUpdates=none');
		self::set_status('ok','Η σύνδεση Google Calendar λειτουργεί κανονικά.');
		wp_safe_redirect(self::admin_return(array('kwb_google'=>'test_ok')));exit;
	}

	private static function render_template($template,$order,$item,$occ){
		$map=array(
			'{{workshop}}'=>$item->get_name(),'{{date}}'=>$occ['date'],'{{time}}'=>$occ['start'],
			'{{order_number}}'=>$order->get_order_number(),
		);
		return strtr((string)$template,$map);
	}
	private static function product_id($item){return$item->get_variation_id()?wp_get_post_parent_id($item->get_variation_id()):$item->get_product_id();}

	public static function sync_order($order_id){
		if(!KWB_Settings::get('google_enabled',0)||!self::connected())return;
		$order=wc_get_order($order_id);if(!$order||in_array($order->get_status(),array('cancelled','refunded','failed'),true))return;
		$calendar=(string)KWB_Settings::get('google_calendar_id','primary');$send=self::send_updates();
		foreach($order->get_items('line_item')as$item_id=>$item){
			$occurrences=KWB_Booking::item_occurrences($item);if(!$occurrences)continue;
			$stored=(array)$item->get_meta('_kwb_google_events',true);$changed=false;
			foreach($occurrences as$occ){
				if(!empty($stored[$occ['id']]['event_id']))continue;
				$body=array(
					'summary'=>self::render_template(KWB_Settings::get('calendar_title_template','{{workshop}}'),$order,$item,$occ),
					'description'=>self::render_template(KWB_Settings::get('calendar_description_template','Κράτηση #{{order_number}} — {{workshop}}'),$order,$item,$occ),
					'location'=>KWB_Booking::location(self::product_id($item)),
					'start'=>array('dateTime'=>$occ['start_dt']->format(DATE_RFC3339)),
					'end'=>array('dateTime'=>$occ['end_dt']->format(DATE_RFC3339)),
					'extendedProperties'=>array('private'=>array('kwb_order_id'=>(string)$order_id,'kwb_item_id'=>(string)$item_id,'kwb_occurrence_id'=>(string)$occ['id'])),
				);
				$alarm=absint(KWB_Settings::get('calendar_alarm_minutes',240));
				if($alarm>0)$body['reminders']=array('useDefault'=>false,'overrides'=>array(array('method'=>'popup','minutes'=>$alarm)));
				$email=$order->get_billing_email();
				if(KWB_Settings::get('google_create_attendees',1)&&is_email($email))$body['attendees']=array(array('email'=>$email));
				$event=self::request('POST','/calendars/'.rawurlencode($calendar).'/events?sendUpdates='.rawurlencode($send),$body);
				if(is_wp_error($event)){self::set_status('error',$event->get_error_message());continue;}
				if(empty($event['id']))continue;
				$stored[$occ['id']]=array('event_id'=>(string)$event['id'],'calendar_id'=>$calendar,'html_link'=>(string)($event['htmlLink']??''),'start'=>$occ['start_dt']->getTimestamp(),'email'=>(string)$email);
				self::registry_put((string)$event['id'],array('order_id'=>(int)$order_id,'item_id'=>(int)$item_id,'occurrence_id'=>(string)$occ['id'],'calendar_id'=>$calendar,'start'=>$occ['start_dt']->getTimestamp(),'email'=>(string)$email));
				$changed=true;
			}
			if($changed){$item->update_meta_data('_kwb_google_events',$stored);$item->save();}
		}
		self::set_status('ok','Τελευταία δημιουργία/ενημέρωση Google events ολοκληρώθηκε.');
	}

	public static function sync_now(){
		self::guard('kwb_google_sync_now');
		if(!self::connected())self::error_return('not_connected');
		$orders=wc_get_orders(array('status'=>array('wc-processing','wc-completed','wc-on-hold'),'limit'=>200,'return'=>'ids','orderby'=>'date','order'=>'DESC'));
		foreach($orders as$order_id)self::sync_order($order_id);
		self::sync_rsvp();
		self::set_status('ok','Χειροκίνητος συγχρονισμός Google Calendar ολοκληρώθηκε.');
		wp_safe_redirect(self::admin_return(array('kwb_google'=>'sync_ok')));exit;
	}

	public static function remove_order_events($order_id){
		$order=wc_get_order($order_id);if(!$order)return;
		foreach($order->get_items('line_item')as$item_id=>$item){
			$stored=(array)$item->get_meta('_kwb_google_events',true);
			foreach($stored as$occ_id=>$entry){
				if(empty($entry['event_id']))continue;$cal=(string)($entry['calendar_id']??KWB_Settings::get('google_calendar_id','primary'));
				if(self::connected())self::request('DELETE','/calendars/'.rawurlencode($cal).'/events/'.rawurlencode($entry['event_id']).'?sendUpdates='.rawurlencode(self::send_updates()));
				self::registry_remove((string)$entry['event_id']);
			}
			if($stored){$item->delete_meta_data('_kwb_google_events');$item->save();}
		}
	}

	private static function send_updates(){
		$v=(string)KWB_Settings::get('google_send_updates','all');
		if('externalonly'===$v)$v='externalOnly';
		return in_array($v,array('all','externalOnly','none'),true)?$v:'all';
	}

	private static function registry(){
		$r=get_option(self::REGISTRY_OPTION,array());return is_array($r)?$r:array();
	}
	private static function registry_put($event_id,$data){$r=self::registry();$r[$event_id]=$data;update_option(self::REGISTRY_OPTION,$r,false);}
	private static function registry_remove($event_id){$r=self::registry();unset($r[$event_id]);update_option(self::REGISTRY_OPTION,$r,false);}

	public static function cron_schedules($schedules){
		$minutes=max(5,min(1440,absint(KWB_Settings::get('google_sync_interval_minutes',15))));
		$schedules['kwb_google_sync']=array('interval'=>$minutes*MINUTE_IN_SECONDS,'display'=>'Workshop Bookings Google RSVP sync');
		return$schedules;
	}
	public static function ensure_cron(){
		if(!wp_next_scheduled(self::CRON_HOOK))wp_schedule_event(time()+5*MINUTE_IN_SECONDS,'kwb_google_sync',self::CRON_HOOK);
	}
	public static function settings_updated($old,$new){
		$old_i=absint(is_array($old)?($old['google_sync_interval_minutes']??15):15);$new_i=absint(is_array($new)?($new['google_sync_interval_minutes']??15):15);
		if($old_i!==$new_i){wp_clear_scheduled_hook(self::CRON_HOOK);self::ensure_cron();}
	}

	public static function sync_rsvp(){
		if(!KWB_Settings::get('google_enabled',0)||!KWB_Settings::get('google_sync_rsvp',1)||!self::connected())return;
		$r=self::registry();$now=time();$changed_registry=false;$processed=0;
		foreach($r as$event_id=>$entry){
			if(++$processed>250)break;
			$start=absint($entry['start']??0);
			if($start&&$start<$now-DAY_IN_SECONDS){unset($r[$event_id]);$changed_registry=true;continue;}
			$order=wc_get_order(absint($entry['order_id']??0));if(!$order||in_array($order->get_status(),array('cancelled','refunded','failed'),true)){unset($r[$event_id]);$changed_registry=true;continue;}
			$item=$order->get_item(absint($entry['item_id']??0));if(!$item){unset($r[$event_id]);$changed_registry=true;continue;}
			$cal=(string)($entry['calendar_id']??KWB_Settings::get('google_calendar_id','primary'));
			$event=self::request('GET','/calendars/'.rawurlencode($cal).'/events/'.rawurlencode($event_id));if(is_wp_error($event))continue;
			$email=strtolower((string)($entry['email']??$order->get_billing_email()));$status='';
			foreach((array)($event['attendees']??array())as$a){if(strtolower((string)($a['email']??''))===$email){$status=(string)($a['responseStatus']??'');break;}}
			if(!$status)continue;
			$occ=(string)($entry['occurrence_id']??'');$yes=(array)$item->get_meta('_kwb_confirmed_occurrences',true);$no=(array)$item->get_meta('_kwb_declined_occurrences',true);
			$yes=array_values(array_diff(array_map('strval',$yes),array($occ)));$no=array_values(array_diff(array_map('strval',$no),array($occ)));
			if('accepted'===$status)$yes[]=$occ;
			if('declined'===$status&&KWB_Settings::get('release_on_no',1))$no[]=$occ;
			$item->update_meta_data('_kwb_confirmed_occurrences',array_values(array_unique($yes)));$item->update_meta_data('_kwb_declined_occurrences',array_values(array_unique($no)));$item->save();
		}
		if($changed_registry)update_option(self::REGISTRY_OPTION,$r,false);
		self::set_status('ok','Google RSVP sync ολοκληρώθηκε.');
	}

	private static function set_status($status,$message){
		update_option('kwb_google_status',array('status'=>sanitize_key($status),'message'=>sanitize_text_field($message),'time'=>time()),false);
	}
	public static function status(){
		$s=get_option('kwb_google_status',array());return is_array($s)?$s:array();
	}
}
