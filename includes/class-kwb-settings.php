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
		$out['google_client_secret']=''===$new_secret?($old['google_client_secret']??''):sanitize_text_field($new_secret);
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
	public static function page(){if(!current_user_can('manage_woocommerce'))return;$s=self::all();?>
	<div class="wrap"><h1>Workshop Bookings — Ρυθμίσεις</h1><form method="post" action="options.php"><?php settings_fields('kwb_settings_group');?>
	<h2>Υπενθυμίσεις & RSVP</h2><table class="form-table"><tbody>
	<tr><th>Υπενθύμιση πριν το εργαστήριο</th><td><?php self::checkbox('reminder_enabled','Ενεργή υπενθύμιση');?><br><?php self::number('reminder_lead_minutes','Αποστολή','5','10080','λεπτά πριν');?></td></tr>
	<tr><th>Email reminder</th><td><?php self::checkbox('email_reminder_enabled','Αποστολή email υπενθύμισης');?></td></tr>
	<tr><th>RSVP</th><td><?php self::checkbox('rsvp_enabled','Να ζητά ΝΑΙ / ΟΧΙ από τον γονιό');?><br><?php self::checkbox('release_on_no','Με ΟΧΙ να ελευθερώνεται αμέσως η θέση');?><br><?php self::number('rsvp_cutoff_minutes','Τελευταία αλλαγή απάντησης','0','1440','λεπτά πριν την έναρξη');?></td></tr>
	</tbody></table>
	<h2>Ημερολόγια</h2><table class="form-table"><tbody>
	<tr><th>Calendar reminder</th><td><?php self::number('calendar_alarm_minutes','Υπενθύμιση μέσα στο calendar','0','10080','λεπτά πριν');?><p class="description">0 = χωρίς alarm στο .ics.</p></td></tr>
	<tr><th>Τίτλος event</th><td><input class="regular-text" name="<?php echo esc_attr(self::OPTION.'[calendar_title_template]');?>" value="<?php echo esc_attr($s['calendar_title_template']);?>"><p class="description">Placeholders: {{workshop}}, {{date}}, {{time}}, {{order_number}}</p></td></tr>
	<tr><th>Περιγραφή event</th><td><textarea class="large-text" rows="3" name="<?php echo esc_attr(self::OPTION.'[calendar_description_template]');?>"><?php echo esc_textarea($s['calendar_description_template']);?></textarea></td></tr>
	<tr><th>Τοποθεσία</th><td><input class="regular-text" name="<?php echo esc_attr(self::OPTION.'[calendar_location]');?>" value="<?php echo esc_attr($s['calendar_location']);?>" placeholder="<?php echo esc_attr(get_bloginfo('name'));?>"></td></tr>
	</tbody></table>
	<h2>Booking defaults</h2><table class="form-table"><tbody>
	<tr><th>Προεπιλεγμένοι μήνες</th><td><?php self::number('default_horizon_months','Εμφάνιση','1','12','μηνών μπροστά');?></td></tr>
	<tr><th>Προεπιλεγμένη χωρητικότητα</th><td><?php self::number('default_capacity','Θέσεις','1','10000','ανά συνάντηση');?></td></tr>
	</tbody></table>
	<h2>Google Calendar</h2><table class="form-table"><tbody>
	<tr><th>Google integration</th><td><?php self::checkbox('google_enabled','Ενεργοποίηση Google Calendar integration');?></td></tr>
	<tr><th>Client ID</th><td><input class="large-text" name="<?php echo esc_attr(self::OPTION.'[google_client_id]');?>" value="<?php echo esc_attr($s['google_client_id']);?>"></td></tr>
	<tr><th>Client Secret</th><td><input type="password" class="regular-text" autocomplete="new-password" name="<?php echo esc_attr(self::OPTION.'[google_client_secret]');?>" value="" placeholder="<?php echo $s['google_client_secret']?'Αποθηκευμένο — αφήστε κενό για να μη αλλάξει':'Εισάγετε Client Secret';?>"></td></tr>
	<tr><th>Calendar ID</th><td><input class="regular-text" name="<?php echo esc_attr(self::OPTION.'[google_calendar_id]');?>" value="<?php echo esc_attr($s['google_calendar_id']);?>"><p class="description">Μετά το OAuth wizard θα γίνει dropdown με τα calendars του λογαριασμού.</p></td></tr>
	<tr><th>Προσκλήσεις</th><td><?php self::checkbox('google_create_attendees','Ο γονιός να προστίθεται ως attendee για Yes / No / Maybe');?></td></tr>
	<tr><th>RSVP sync</th><td><?php self::checkbox('google_sync_rsvp','Συγχρονισμός Google RSVP με διαθεσιμότητα');?><br><?php self::number('google_sync_interval_minutes','Έλεγχος αλλαγών κάθε','5','1440','λεπτά');?></td></tr>
	<tr><th>Google notifications</th><td><select name="<?php echo esc_attr(self::OPTION.'[google_send_updates]');?>"><option value="all" <?php selected($s['google_send_updates'],'all');?>>Σε όλους</option><option value="externalonly" <?php selected($s['google_send_updates'],'externalonly');?>>Μόνο εξωτερικούς</option><option value="none" <?php selected($s['google_send_updates'],'none');?>>Καμία</option></select></td></tr>
	</tbody></table>
	<?php submit_button('Αποθήκευση ρυθμίσεων');?></form></div><?php }
}
