<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class KWB_RSVP {
	const CRON_HOOK='kwb_send_attendance_reminder';

	public static function init(){
		add_action(self::CRON_HOOK,array(__CLASS__,'send_reminder'),10,3);
		add_action('template_redirect',array(__CLASS__,'handle_response'));
		add_action('woocommerce_order_status_processing',array(__CLASS__,'schedule_order'));
		add_action('woocommerce_order_status_completed',array(__CLASS__,'schedule_order'));
		add_action('woocommerce_order_status_cancelled',array(__CLASS__,'clear_order'));
		add_action('woocommerce_order_status_refunded',array(__CLASS__,'clear_order'));
	}

	public static function schedule_order($order_id){
		$order=wc_get_order($order_id);if(!$order)return;
		foreach($order->get_items('line_item') as $item_id=>$item){
			foreach(KWB_Booking::item_occurrences($item) as $occ){
				$lead=max(5,absint(KWB_Settings::get('reminder_lead_minutes',240)));$run=$occ['start_dt']->getTimestamp()-($lead*MINUTE_IN_SECONDS);
				if($run<=time()+60)continue;
				$args=array((int)$order_id,(int)$item_id,(string)$occ['id']);
				if(!wp_next_scheduled(self::CRON_HOOK,$args))wp_schedule_single_event($run,self::CRON_HOOK,$args);
			}
		}
	}

	public static function clear_order($order_id){
		$order=wc_get_order($order_id);if(!$order)return;
		foreach($order->get_items('line_item') as $item_id=>$item){
			foreach(KWB_Booking::item_occurrences($item) as $occ){
				$args=array((int)$order_id,(int)$item_id,(string)$occ['id']);
				$ts=wp_next_scheduled(self::CRON_HOOK,$args);if($ts)wp_unschedule_event($ts,self::CRON_HOOK,$args);
			}
		}
	}

	private static function token($order_id,$item_id,$occurrence_id,$answer){
		$order=wc_get_order(absint($order_id));if(!$order)return'';
		$ctx=absint($order_id).'|'.absint($item_id).'|'.sanitize_text_field($occurrence_id).'|'.sanitize_key($answer).'|'.$order->get_order_key();
		return hash_hmac('sha256',$ctx,wp_salt('secure_auth'));
	}

	public static function url($order_id,$item_id,$occurrence_id,$answer){
		return add_query_arg(array(
			'kwb_rsvp'=>1,
			'order_id'=>absint($order_id),
			'item_id'=>absint($item_id),
			'occurrence'=>rawurlencode((string)$occurrence_id),
			'answer'=>sanitize_key($answer),
			'token'=>self::token($order_id,$item_id,$occurrence_id,$answer),
		),home_url('/'));
	}

	public static function send_reminder($order_id,$item_id,$occurrence_id){
		$order=wc_get_order($order_id);if(!$order||in_array($order->get_status(),array('cancelled','refunded','failed'),true))return;
		$item=$order->get_item($item_id);if(!$item)return;
		$occ=null;foreach(KWB_Booking::item_occurrences($item) as $o)if(hash_equals((string)$occurrence_id,(string)$o['id'])){$occ=$o;break;}if(!$occ)return;
		if(!KWB_Settings::get('reminder_enabled',1)||KWB_Booking::occurrence_declined($item,$occurrence_id))return;
		$to=$order->get_billing_email();if(!KWB_Settings::get('email_reminder_enabled',1)||!is_email($to))return;
		$yes=self::url($order_id,$item_id,$occurrence_id,'yes');$no=self::url($order_id,$item_id,$occurrence_id,'no');
		$subject=sprintf('Υπενθύμιση: %s σήμερα στις %s',$item->get_name(),$occ['start']);
		$message='<p>Υπενθύμιση για το εργαστήριο <strong>'.esc_html($item->get_name()).'</strong> σήμερα στις <strong>'.esc_html($occ['start']).'</strong>.</p>';
		$message.='<p>Θα μπορέσετε τελικά να έρθετε;</p>';
		$message.='<p><a href="'.esc_url($yes).'" style="display:inline-block;padding:10px 18px;background:#2271b1;color:#fff;text-decoration:none;border-radius:4px">ΝΑΙ, θα έρθουμε</a> ';
		$message.='<a href="'.esc_url($no).'" style="display:inline-block;padding:10px 18px;background:#b32d2e;color:#fff;text-decoration:none;border-radius:4px">ΟΧΙ, δεν θα έρθουμε</a></p>';
		add_filter('wp_mail_content_type',array(__CLASS__,'html_mail'));
		wp_mail($to,$subject,$message);
		remove_filter('wp_mail_content_type',array(__CLASS__,'html_mail'));
	}
	public static function html_mail(){return'text/html';}

	public static function handle_response(){
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- HMAC-authenticated response endpoint.
		if(empty($_GET['kwb_rsvp']))return;
		$order_id=isset($_GET['order_id'])?absint($_GET['order_id']):0;
		$item_id=isset($_GET['item_id'])?absint($_GET['item_id']):0;
		$occ=isset($_GET['occurrence'])?sanitize_text_field(wp_unslash($_GET['occurrence'])):'';
		$answer=isset($_GET['answer'])?sanitize_key(wp_unslash($_GET['answer'])):'';
		$token=isset($_GET['token'])?sanitize_text_field(wp_unslash($_GET['token'])):'';
		if(!KWB_Settings::get('rsvp_enabled',1)||!$order_id||!$item_id||!in_array($answer,array('yes','no'),true)||!hash_equals(self::token($order_id,$item_id,$occ,$answer),$token)){status_header(403);exit;}
		$order=wc_get_order($order_id);$item=$order?$order->get_item($item_id):false;if(!$item){status_header(404);exit;}
		$valid=false;foreach(KWB_Booking::item_occurrences($item)as$o)if(hash_equals((string)$o['id'],(string)$occ)){$valid=true;break;}if(!$valid){status_header(404);exit;}
		$yes=(array)$item->get_meta('_kwb_confirmed_occurrences',true);$no=(array)$item->get_meta('_kwb_declined_occurrences',true);
		$yes=array_values(array_diff($yes,array($occ)));$no=array_values(array_diff($no,array($occ)));
		if('yes'===$answer)$yes[]=$occ;else if(KWB_Settings::get('release_on_no',1))$no[]=$occ;
		$item->update_meta_data('_kwb_confirmed_occurrences',array_values(array_unique($yes)));
		$item->update_meta_data('_kwb_declined_occurrences',array_values(array_unique($no)));
		$item->save();
		nocache_headers();status_header(200);
		echo '<!doctype html><html><meta charset="utf-8"><title>Kangiroo</title><body style="font-family:Arial,sans-serif;text-align:center;padding:50px">';
		echo '<h1>'.('yes'===$answer?'Ευχαριστούμε! Σας περιμένουμε.':'Η θέση σας ελευθερώθηκε. Ευχαριστούμε που μας ενημερώσατε.').'</h1>';
		echo '</body></html>';// phpcs:enable WordPress.Security.NonceVerification.Recommended
		exit;
	}
}
