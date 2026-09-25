<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class KWB_Settings {
	const OPTION='kwb_settings';

	public static function defaults(){
		return array(
			'reminder_enabled'=>1,
			'reminder_lead_minutes'=>240,
			'email_reminder_enabled'=>1,
			'rsvp_enabled'=>1,
			'release_on_no'=>1,
			'rsvp_cutoff_minutes'=>30,
			'calendar_alarm_minutes'=>240,
			'default_horizon_months'=>3,
			'default_capacity'=>10,
			'calendar_title_template'=>'{{workshop}}',
			'calendar_description_template'=>'Κράτηση #{{order_number}} — {{workshop}}',
			'calendar_location'=>'',
			'google_enabled'=>0,
			'google_client_id'=>'',
			'google_client_secret'=>'',
			'google_calendar_id'=>'primary',
			'google_send_updates'=>'all',
			'google_create_attendees'=>1,
			'google_sync_rsvp'=>1,
			'google_sync_interval_minutes'=>15,
		);
	}
	public static function all(){return wp_parse_args((array)get_option(self::OPTION,array()),self::defaults());}
	public static function get($key,$default=null){$all=self::all();return array_key_exists($key,$all)?$all[$key]:$default;}

	public static function init(){
		add_action('admin_menu',array(__CLASS__,'menu'));
		add_action('admin_init',array(__CLASS__,'register'));
	}

	public static function menu(){
		add_submenu_page('woocommerce','Workshop Bookings','Workshop Bookings','manage_woocommerce','kwb-settings',array(__CLASS__,'page'));
	}

	public static function register(){
		register_setting('kwb_settings_group',self::OPTION,array(__CLASS__,'sanitize'));
	}

	public static function sanitize($input){
		$old=self::all();$out=self::defaults();$input=is_array($input)?$input:array();
		foreach(array('reminder_enabled','email_reminder_enabled','rsvp_enabled','release_on_no','google_enabled','google_create_attendees','google_sync_rsvp')as$k)$out[$k]=empty($input[$k])?0:1;
		foreach(array(
			'reminder_lead_minutes'=>array(5,10080,240),
			'rsvp_cutoff_minutes'=>array(0,1440,30),
			'calendar_alarm_minutes'=>array(0,10080,240),
			'default_horizon_months'=>array(1,12,3),
			'default_capacity'=>array(1,10000,10),
			'google_sync_interval_minutes'=>array(5,1440,15),
		)as$k=>$cfg){$v=isset($input[$k])?absint($input[$k]):$cfg[2];$out[$k]=max($cfg[0],min($cfg[1],$v));}
		$out['calendar_title_template']=sanitize_text_field((string)($input['calendar_title_template']??'{{workshop}}'));
		$out['calendar_description_template']=sanitize_textarea_field((string)($input['calendar_description_template']??''));
		$out['calendar_location']=sanitize_text_field((string)($input['calendar_location']??''));
		$out['google_client_id']=sanitize_text_field((string)($input['google_client_id']??''));
		$new_secret=trim((string)($input['google_client_secret']??''));
		if(''!==$new_secret&&class_exists('KWB_Google_Calendar'))KWB_Google_Calendar::store_client_secret($new_secret);
		$out['google_client_secret']='';
		$out['google_calendar_id']=sanitize_text_field((string)($input['google_calendar_id']??'primary'));
		$send=sanitize_key((string)($input['google_send_updates']??'all'));$out['google_send_updates']=in_array($send,array('all','externalonly','none'),true)?$send:'all';
		return$out;
	}

	private static function checkbox($name,$label,$desc=''){
		$v=(int)self::get($name);echo '<label><input type="checkbox" name="'.esc_attr(self::OPTION.'['.$name.']').'" value="1" '.checked($v,1,false).'> '.esc_html($label).'</label>';if($desc)echo '<p class="description">'.esc_html($desc).'</p>';
	}
	private static function number($name,$label,$min,$max,$suffix=''){
		$v=absint(self::get($name));echo '<label>'.esc_html($label).' <input type="number" name="'.esc_attr(self::OPTION.'['.$name.']').'" value="'.esc_attr($v).'" min="'.esc_attr($min).'" max="'.esc_attr($max).'" step="1"> '.esc_html($suffix).'</label>';
	}
	private static function google_message(){
		$key=isset($_GET['kwb_google'])?sanitize_key(wp_unslash($_GET['kwb_google'])):'';
		$code=isset($_GET['kwb_google_code'])?sanitize_key(wp_unslash($_GET['kwb_google_code'])):'';
		if(!$key)return;
		$messages=array(
			'connected'=>array('success','Ο Google λογαριασμός συνδέθηκε. Επιλέξτε ημερολόγιο και αποθηκεύστε τις ρυθμίσεις.'),
			'disconnected'=>array('warning','Ο Google λογαριασμός αποσυνδέθηκε.'),
			'test_ok'=>array('success','Η σύνδεση δοκιμάστηκε επιτυχώς: δημιουργήθηκε και διαγράφηκε προσωρινό test event.'),
		);
		if(isset($messages[$key])){$m=$messages[$key];echo '<div class="notice notice-'.esc_attr($m[0]).' is-dismissible"><p>'.esc_html($m[1]).'</p></div>';return;}
		if('error'===$key){$errors=array(
			'missing_credentials'=>'Λείπει Client ID ή Client Secret.',
			'state'=>'Η OAuth επιστροφή δεν πέρασε τον έλεγχο ασφαλείας. Ξεκινήστε ξανά τη σύνδεση.',
			'access_denied'=>'Η πρόσβαση ακυρώθηκε από τον Google λογαριασμό.',
			'missing_code'=>'Η Google δεν επέστρεψε authorization code.',
			'token_exchange'=>'Απέτυχε η ανταλλαγή του authorization code με tokens.',
			'token_response'=>'Η απάντηση OAuth της Google δεν ήταν έγκυρη.',
			'token_storage'=>'Δεν ήταν δυνατή η ασφαλής αποθήκευση των Google tokens.',
			'not_connected'=>'Δεν υπάρχει ενεργή σύνδεση Google.',
			'test_create'=>'Η Google σύνδεση υπάρχει, αλλά δεν μπόρεσε να δημιουργηθεί test event στο επιλεγμένο ημερολόγιο.',
		);echo '<div class="notice notice-error"><p>'.esc_html($errors[$code]??('Google Calendar error: '.$code)).'</p></div>';}
	}

	public static function page(){
		if(!current_user_can('manage_woocommerce'))return;$s=self::all();self::google_message();
		$connected=class_exists('KWB_Google_Calendar')&&KWB_Google_Calendar::connected();
		$redirect=class_exists('KWB_Google_Calendar')?KWB_Google_Calendar::redirect_uri():'';
		$calendars=$connected?KWB_Google_Calendar::calendars():array();
		$status=$connected?KWB_Google_Calendar::status():array();
		$connect_url=wp_nonce_url(admin_url('admin-post.php?action=kwb_google_connect'),'kwb_google_connect');
		$disconnect_url=wp_nonce_url(admin_url('admin-post.php?action=kwb_google_disconnect'),'kwb_google_disconnect');
		$test_url=wp_nonce_url(admin_url('admin-post.php?action=kwb_google_test'),'kwb_google_test');
		?>
	<div class="wrap"><h1>Workshop Bookings — Ρυθμίσεις</h1><form method="post" action="options.php"><?php settings_fields('kwb_settings_group');?>
	<h2>Υπενθυμίσεις & RSVP</h2><table class="form-table"><tbody>
	<tr><th>Υπενθύμιση πριν το εργαστήριο</th><td><?php self::checkbox('reminder_enabled','Ενεργή υπενθύμιση');?><br><?php self::number('reminder_lead_minutes','Αποστολή','5','10080','λεπτά πριν');?></td></tr>
	<tr><th>Email reminder</th><td><?php self::checkbox('email_reminder_enabled','Αποστολή email υπενθύμισης');?></td></tr>
	<tr><th>RSVP</th><td><?php self::checkbox('rsvp_enabled','Να ζητά ΝΑΙ / ΟΧΙ από τον πελάτη');?><br><?php self::checkbox('release_on_no','Με ΟΧΙ να ελευθερώνεται αμέσως η θέση');?><br><?php self::number('rsvp_cutoff_minutes','Τελευταία αλλαγή απάντησης','0','1440','λεπτά πριν την έναρξη');?></td></tr>
	</tbody></table>
	<h2>Ημερολόγια</h2><table class="form-table"><tbody>
	<tr><th>Calendar reminder</th><td><?php self::number('calendar_alarm_minutes','Υπενθύμιση μέσα στο calendar','0','10080','λεπτά πριν');?><p class="description">0 = χωρίς calendar alarm.</p></td></tr>
	<tr><th>Τίτλος event</th><td><input class="regular-text" name="<?php echo esc_attr(self::OPTION.'[calendar_title_template]');?>" value="<?php echo esc_attr($s['calendar_title_template']);?>"><p class="description">Placeholders: {{workshop}}, {{date}}, {{time}}, {{order_number}}</p></td></tr>
	<tr><th>Περιγραφή event</th><td><textarea class="large-text" rows="3" name="<?php echo esc_attr(self::OPTION.'[calendar_description_template]');?>"><?php echo esc_textarea($s['calendar_description_template']);?></textarea></td></tr>
	<tr><th>Τοποθεσία</th><td><input class="regular-text" name="<?php echo esc_attr(self::OPTION.'[calendar_location]');?>" value="<?php echo esc_attr($s['calendar_location']);?>" placeholder="<?php echo esc_attr(get_bloginfo('name'));?>"></td></tr>
	</tbody></table>
	<h2>Booking defaults</h2><table class="form-table"><tbody>
	<tr><th>Προεπιλεγμένοι μήνες</th><td><?php self::number('default_horizon_months','Εμφάνιση','1','12','μηνών μπροστά');?></td></tr>
	<tr><th>Προεπιλεγμένη χωρητικότητα</th><td><?php self::number('default_capacity','Θέσεις','1','10000','ανά συνάντηση');?></td></tr>
	</tbody></table>

	<h2>Google Calendar — Connection Wizard</h2>
	<div style="max-width:900px;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:18px 22px;margin:10px 0 20px">
		<p><strong>Βήμα 1 — Google Cloud OAuth</strong></p>
		<p>Στο OAuth Client τύπου <strong>Web application</strong> προσθέστε ακριβώς αυτό το Authorized Redirect URI:</p>
		<p><code id="kwb-google-redirect" style="display:inline-block;padding:8px 10px;user-select:all"><?php echo esc_html($redirect);?></code></p>
		<p class="description">Ενεργοποιήστε το Google Calendar API στο ίδιο Google Cloud project.</p>

		<p><strong>Βήμα 2 — Credentials</strong></p>
		<table class="form-table"><tbody>
		<tr><th>Client ID</th><td><input class="large-text" name="<?php echo esc_attr(self::OPTION.'[google_client_id]');?>" value="<?php echo esc_attr($s['google_client_id']);?>" autocomplete="off"></td></tr>
		<tr><th>Client Secret</th><td><input type="password" class="regular-text" autocomplete="new-password" name="<?php echo esc_attr(self::OPTION.'[google_client_secret]');?>" value="" placeholder="<?php echo KWB_Google_Calendar::has_client_secret()?'Αποθηκευμένο κρυπτογραφημένα — αφήστε κενό για να μη αλλάξει':'Εισάγετε Client Secret';?>"><p class="description">Το secret και τα OAuth tokens αποθηκεύονται κρυπτογραφημένα με κλειδί που παράγεται από τα WordPress salts.</p></td></tr>
		</tbody></table>
		<p><strong>Αποθηκεύστε πρώτα τις ρυθμίσεις</strong> και μετά πατήστε σύνδεση.</p>

		<p><strong>Βήμα 3 — Σύνδεση</strong></p>
		<?php if($connected):?>
		<p><span style="display:inline-block;background:#edfaef;color:#176b2c;border:1px solid #8ccf9a;border-radius:20px;padding:5px 10px;font-weight:600">● Google Connected</span></p>
		<p><a class="button button-secondary" href="<?php echo esc_url($test_url);?>">Δοκιμή σύνδεσης</a> <a class="button" href="<?php echo esc_url($disconnect_url);?>" onclick="return confirm('Αποσύνδεση Google Calendar;')">Αποσύνδεση</a></p>
		<?php else:?>
		<p><a class="button button-primary" href="<?php echo esc_url($connect_url);?>">Σύνδεση με Google Calendar</a></p>
		<?php endif;?>

		<p><strong>Βήμα 4 — Επιλογή ημερολογίου</strong></p>
		<?php if(is_wp_error($calendars)):?>
		<p style="color:#b32d2e"><?php echo esc_html($calendars->get_error_message());?></p>
		<input class="regular-text" name="<?php echo esc_attr(self::OPTION.'[google_calendar_id]');?>" value="<?php echo esc_attr($s['google_calendar_id']);?>">
		<?php elseif($connected&&$calendars):?>
		<select name="<?php echo esc_attr(self::OPTION.'[google_calendar_id]');?>">
		<?php foreach($calendars as$cal): if(!in_array($cal['accessRole'],array('owner','writer'),true))continue;?>
		<option value="<?php echo esc_attr($cal['id']);?>" <?php selected($s['google_calendar_id'],$cal['id']);?>><?php echo esc_html($cal['summary'].($cal['primary']?' — Primary':''));?></option>
		<?php endforeach;?>
		</select>
		<?php else:?>
		<input class="regular-text" name="<?php echo esc_attr(self::OPTION.'[google_calendar_id]');?>" value="<?php echo esc_attr($s['google_calendar_id']);?>" placeholder="primary">
		<?php endif;?>

		<p><strong>Συμπεριφορά Google Calendar</strong></p>
		<p><?php self::checkbox('google_enabled','Δημιουργία Google events για πληρωμένες κρατήσεις');?></p>
		<p><?php self::checkbox('google_create_attendees','Προσθήκη email πελάτη ως attendee για Yes / No / Maybe');?></p>
		<p><?php self::checkbox('google_sync_rsvp','Συγχρονισμός Google RSVP με τη διαθεσιμότητα');?> <?php self::number('google_sync_interval_minutes','κάθε','5','1440','λεπτά');?></p>
		<p><label>Αποστολή Google invitations <select name="<?php echo esc_attr(self::OPTION.'[google_send_updates]');?>"><option value="all" <?php selected($s['google_send_updates'],'all');?>>Σε όλους</option><option value="externalonly" <?php selected($s['google_send_updates'],'externalonly');?>>Μόνο εξωτερικούς</option><option value="none" <?php selected($s['google_send_updates'],'none');?>>Καμία</option></select></label></p>
		<?php if(!empty($status['time'])):?><p class="description">Τελευταία κατάσταση: <?php echo esc_html(wp_date('d/m/Y H:i',(int)$status['time']));?> — <?php echo esc_html($status['message']??'');?></p><?php endif;?>
	</div>

	<?php submit_button('Αποθήκευση ρυθμίσεων');?></form></div><?php }
}
