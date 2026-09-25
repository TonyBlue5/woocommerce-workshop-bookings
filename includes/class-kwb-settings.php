<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class KWB_Settings {
	const OPTION='kwb_settings';

	public static function defaults(){
		return array(
			'reminder_enabled'=>1,
			'reminder_lead_minutes'=>240,
			'email_reminder_enabled'=>1,
			'reminder_subject_template'=>'Υπενθύμιση: {{workshop}} — {{date}} στις {{time}}',
			'reminder_message_template'=>'Υπενθύμιση για το εργαστήριο {{workshop}} στις {{date}} {{time}}. Θα μπορέσετε τελικά να έρθετε;',
			'rsvp_enabled'=>1,
			'release_on_no'=>1,
			'rsvp_cutoff_minutes'=>30,
			'rsvp_yes_label'=>'ΝΑΙ, θα έρθουμε',
			'rsvp_no_label'=>'ΟΧΙ, δεν θα έρθουμε',
			'calendar_google_link_enabled'=>1,
			'calendar_ics_enabled'=>1,
			'calendar_alarm_minutes'=>240,
			'calendar_title_template'=>'{{workshop}}',
			'calendar_description_template'=>'Κράτηση #{{order_number}} — {{workshop}}',
			'calendar_location'=>'',
			'default_horizon_months'=>3,
			'default_capacity'=>10,
		);
	}
	public static function all(){return wp_parse_args((array)get_option(self::OPTION,array()),self::defaults());}
	public static function get($key,$default=null){$all=self::all();return array_key_exists($key,$all)?$all[$key]:$default;}

	public static function init(){
		add_action('admin_menu',array(__CLASS__,'menu'));
		add_action('admin_init',array(__CLASS__,'register'));
		add_action('admin_init',array(__CLASS__,'cleanup_legacy_google_oauth'));
	}

	public static function cleanup_legacy_google_oauth(){
		if(get_option('kwb_api_free_migration_done'))return;
		delete_option('kwb_google_tokens');
		delete_option('kwb_google_client_secret');
		delete_option('kwb_google_event_registry');
		delete_option('kwb_google_status');
		wp_clear_scheduled_hook('kwb_google_rsvp_sync');
		update_option('kwb_api_free_migration_done',1,false);
	}

	public static function menu(){
		add_submenu_page('woocommerce','Workshop Bookings','Workshop Bookings','manage_woocommerce','kwb-settings',array(__CLASS__,'page'));
	}

	public static function register(){
		register_setting('kwb_settings_group',self::OPTION,array(__CLASS__,'sanitize'));
	}

	public static function sanitize($input){
		$out=self::defaults();$input=is_array($input)?$input:array();
		foreach(array('reminder_enabled','email_reminder_enabled','rsvp_enabled','release_on_no','calendar_google_link_enabled','calendar_ics_enabled')as$k)$out[$k]=empty($input[$k])?0:1;
		foreach(array(
			'reminder_lead_minutes'=>array(5,10080,240),
			'rsvp_cutoff_minutes'=>array(0,1440,30),
			'calendar_alarm_minutes'=>array(0,10080,240),
			'default_horizon_months'=>array(1,12,3),
			'default_capacity'=>array(1,10000,10),
		)as$k=>$cfg){$v=isset($input[$k])?absint($input[$k]):$cfg[2];$out[$k]=max($cfg[0],min($cfg[1],$v));}
		foreach(array('calendar_title_template','rsvp_yes_label','rsvp_no_label','reminder_subject_template','calendar_location')as$k)$out[$k]=sanitize_text_field((string)($input[$k]??$out[$k]));
		foreach(array('calendar_description_template','reminder_message_template')as$k)$out[$k]=sanitize_textarea_field((string)($input[$k]??$out[$k]));
		return$out;
	}

	private static function checkbox($name,$label,$desc=''){
		$v=(int)self::get($name);echo '<label><input type="checkbox" name="'.esc_attr(self::OPTION.'['.$name.']').'" value="1" '.checked($v,1,false).'> '.esc_html($label).'</label>';if($desc)echo '<p class="description">'.esc_html($desc).'</p>';
	}
	private static function number($name,$label,$min,$max,$suffix=''){
		$v=absint(self::get($name));echo '<label>'.esc_html($label).' <input type="number" name="'.esc_attr(self::OPTION.'['.$name.']').'" value="'.esc_attr($v).'" min="'.esc_attr($min).'" max="'.esc_attr($max).'" step="1"> '.esc_html($suffix).'</label>';
	}

	public static function page(){
		if(!current_user_can('manage_woocommerce'))return;$s=self::all();?>
	<div class="wrap"><h1>Workshop Bookings — Ρυθμίσεις</h1>
	<form method="post" action="options.php"><?php settings_fields('kwb_settings_group');?>

	<h2>Υπενθυμίσεις & RSVP</h2><table class="form-table"><tbody>
	<tr><th>Υπενθύμιση</th><td><?php self::checkbox('reminder_enabled','Ενεργή υπενθύμιση');?><br><?php self::number('reminder_lead_minutes','Αποστολή','5','10080','λεπτά πριν');?></td></tr>
	<tr><th>Email reminder</th><td><?php self::checkbox('email_reminder_enabled','Αποστολή email υπενθύμισης');?></td></tr>
	<tr><th>Θέμα email</th><td><input class="large-text" name="<?php echo esc_attr(self::OPTION.'[reminder_subject_template]');?>" value="<?php echo esc_attr($s['reminder_subject_template']);?>"><p class="description">Placeholders: {{workshop}}, {{date}}, {{time}}, {{order_number}}</p></td></tr>
	<tr><th>Κείμενο email</th><td><textarea class="large-text" rows="4" name="<?php echo esc_attr(self::OPTION.'[reminder_message_template]');?>"><?php echo esc_textarea($s['reminder_message_template']);?></textarea></td></tr>
	<tr><th>RSVP</th><td><?php self::checkbox('rsvp_enabled','Να ζητά ΝΑΙ / ΟΧΙ από τον πελάτη');?><br><?php self::checkbox('release_on_no','Με ΟΧΙ να ελευθερώνεται αμέσως η θέση');?><br><?php self::number('rsvp_cutoff_minutes','Τελευταία αλλαγή απάντησης','0','1440','λεπτά πριν την έναρξη');?></td></tr>
	<tr><th>Κείμενα κουμπιών RSVP</th><td><input class="regular-text" name="<?php echo esc_attr(self::OPTION.'[rsvp_yes_label]');?>" value="<?php echo esc_attr($s['rsvp_yes_label']);?>" placeholder="ΝΑΙ, θα έρθουμε"> <input class="regular-text" name="<?php echo esc_attr(self::OPTION.'[rsvp_no_label]');?>" value="<?php echo esc_attr($s['rsvp_no_label']);?>" placeholder="ΟΧΙ, δεν θα έρθουμε"></td></tr>
	</tbody></table>

	<h2>Ημερολόγια — χωρίς API</h2>
	<p>Οι πελάτες προσθέτουν την κράτησή τους στο προσωπικό τους ημερολόγιο χωρίς Google Cloud, OAuth ή API credentials.</p>
	<table class="form-table"><tbody>
	<tr><th>Google Calendar</th><td><?php self::checkbox('calendar_google_link_enabled','Εμφάνιση “Add to Google Calendar”');?></td></tr>
	<tr><th>Apple / Outlook / iCalendar</th><td><?php self::checkbox('calendar_ics_enabled','Εμφάνιση λήψης .ics');?></td></tr>
	<tr><th>Reminder μέσα στο .ics</th><td><?php self::number('calendar_alarm_minutes','Υπενθύμιση','0','10080','λεπτά πριν');?><p class="description">0 = χωρίς VALARM. Η εφαρμογή ημερολογίου του πελάτη αποφασίζει πώς θα εμφανίσει την ειδοποίηση.</p></td></tr>
	<tr><th>Τίτλος event</th><td><input class="regular-text" name="<?php echo esc_attr(self::OPTION.'[calendar_title_template]');?>" value="<?php echo esc_attr($s['calendar_title_template']);?>"><p class="description">Placeholders: {{workshop}}, {{date}}, {{time}}, {{order_number}}</p></td></tr>
	<tr><th>Περιγραφή event</th><td><textarea class="large-text" rows="3" name="<?php echo esc_attr(self::OPTION.'[calendar_description_template]');?>"><?php echo esc_textarea($s['calendar_description_template']);?></textarea></td></tr>
	<tr><th>Προεπιλεγμένη τοποθεσία</th><td><input class="regular-text" name="<?php echo esc_attr(self::OPTION.'[calendar_location]');?>" value="<?php echo esc_attr($s['calendar_location']);?>" placeholder="<?php echo esc_attr(get_bloginfo('name'));?>"></td></tr>
	</tbody></table>

	<h2>Booking defaults</h2><table class="form-table"><tbody>
	<tr><th>Προεπιλεγμένοι μήνες</th><td><?php self::number('default_horizon_months','Εμφάνιση','1','12','μηνών μπροστά');?></td></tr>
	<tr><th>Προεπιλεγμένη χωρητικότητα</th><td><?php self::number('default_capacity','Θέσεις','1','10000','ανά συνάντηση');?></td></tr>
	</tbody></table>

	<?php submit_button('Αποθήκευση ρυθμίσεων');?></form></div><?php
	}
}
