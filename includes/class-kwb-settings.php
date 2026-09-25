<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class KWB_Settings {
	const OPTION='kwb_settings';

	public static function defaults(){
		return array(
			'reminder_enabled'=>1,
			'reminder_lead_minutes'=>240,
			'email_reminder_enabled'=>1,
			'reminder_subject_template'=>KWB_I18n::t('reminder_subject_default'),
			'reminder_message_template'=>KWB_I18n::t('reminder_message_default'),
			'rsvp_enabled'=>1,
			'release_on_no'=>1,
			'rsvp_cutoff_minutes'=>30,
			'rsvp_yes_label'=>KWB_I18n::t('yes_default'),
			'rsvp_no_label'=>KWB_I18n::t('no_default'),
			'calendar_google_link_enabled'=>1,
			'calendar_ics_enabled'=>1,
			'calendar_alarm_minutes'=>240,
			'calendar_title_template'=>'{{workshop}}',
			'calendar_description_template'=>KWB_I18n::t('calendar_description_default'),
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
	<div class="wrap"><h1><?php echo esc_html(KWB_I18n::t('settings_title'));?></h1>
	<form method="post" action="options.php"><?php settings_fields('kwb_settings_group');?>

	<h2><?php echo esc_html(KWB_I18n::t('reminders_rsvp'));?></h2><table class="form-table"><tbody>
	<tr><th><?php echo esc_html(KWB_I18n::t('reminder'));?></th><td><?php self::checkbox('reminder_enabled',KWB_I18n::t('active_reminder'));?><br><?php self::number('reminder_lead_minutes',KWB_I18n::t('send'),'5','10080',KWB_I18n::t('minutes_before'));?></td></tr>
	<tr><th><?php echo esc_html(KWB_I18n::t('email_reminder'));?></th><td><?php self::checkbox('email_reminder_enabled',KWB_I18n::t('send_email_reminder'));?></td></tr>
	<tr><th><?php echo esc_html(KWB_I18n::t('email_subject'));?></th><td><input class="large-text" name="<?php echo esc_attr(self::OPTION.'[reminder_subject_template]');?>" value="<?php echo esc_attr($s['reminder_subject_template']);?>"><p class="description"><?php echo esc_html(KWB_I18n::t('placeholders'));?>: {{workshop}}, {{date}}, {{time}}, {{order_number}}</p></td></tr>
	<tr><th><?php echo esc_html(KWB_I18n::t('email_message'));?></th><td><textarea class="large-text" rows="4" name="<?php echo esc_attr(self::OPTION.'[reminder_message_template]');?>"><?php echo esc_textarea($s['reminder_message_template']);?></textarea></td></tr>
	<tr><th>RSVP</th><td><?php self::checkbox('rsvp_enabled',KWB_I18n::t('ask_yes_no'));?><br><?php self::checkbox('release_on_no',KWB_I18n::t('release_on_no'));?><br><?php self::number('rsvp_cutoff_minutes',KWB_I18n::t('last_change'),'0','1440',KWB_I18n::t('before_start'));?></td></tr>
	<tr><th><?php echo esc_html(KWB_I18n::t('rsvp_button_text'));?></th><td><input class="regular-text" name="<?php echo esc_attr(self::OPTION.'[rsvp_yes_label]');?>" value="<?php echo esc_attr($s['rsvp_yes_label']);?>" placeholder="<?php echo esc_attr(KWB_I18n::t('yes_default'));?>"> <input class="regular-text" name="<?php echo esc_attr(self::OPTION.'[rsvp_no_label]');?>" value="<?php echo esc_attr($s['rsvp_no_label']);?>" placeholder="<?php echo esc_attr(KWB_I18n::t('no_default'));?>"></td></tr>
	</tbody></table>

	<h2><?php echo esc_html(KWB_I18n::t('calendars_no_api'));?></h2>
	<p><?php echo esc_html(KWB_I18n::t('calendar_no_api_desc'));?></p>
	<table class="form-table"><tbody>
	<tr><th>Google Calendar</th><td><?php self::checkbox('calendar_google_link_enabled',KWB_I18n::t('show_google'));?></td></tr>
	<tr><th>Apple / Outlook / iCalendar</th><td><?php self::checkbox('calendar_ics_enabled',KWB_I18n::t('show_ics'));?></td></tr>
	<tr><th><?php echo esc_html(KWB_I18n::t('ics_reminder'));?></th><td><?php self::number('calendar_alarm_minutes',KWB_I18n::t('reminder'),'0','10080',KWB_I18n::t('minutes_before'));?><p class="description"><?php echo esc_html(KWB_I18n::t('ics_alarm_help'));?></p></td></tr>
	<tr><th><?php echo esc_html(KWB_I18n::t('event_title'));?></th><td><input class="regular-text" name="<?php echo esc_attr(self::OPTION.'[calendar_title_template]');?>" value="<?php echo esc_attr($s['calendar_title_template']);?>"><p class="description">Placeholders: {{workshop}}, {{date}}, {{time}}, {{order_number}}</p></td></tr>
	<tr><th><?php echo esc_html(KWB_I18n::t('event_description'));?></th><td><textarea class="large-text" rows="3" name="<?php echo esc_attr(self::OPTION.'[calendar_description_template]');?>"><?php echo esc_textarea($s['calendar_description_template']);?></textarea></td></tr>
	<tr><th><?php echo esc_html(KWB_I18n::t('default_location'));?></th><td><input class="regular-text" name="<?php echo esc_attr(self::OPTION.'[calendar_location]');?>" value="<?php echo esc_attr($s['calendar_location']);?>" placeholder="<?php echo esc_attr(get_bloginfo('name'));?>"></td></tr>
	</tbody></table>

	<h2><?php echo esc_html(KWB_I18n::t('booking_defaults'));?></h2><table class="form-table"><tbody>
	<tr><th><?php echo esc_html(KWB_I18n::t('default_months'));?></th><td><?php self::number('default_horizon_months',KWB_I18n::t('show'),'1','12',KWB_I18n::t('months_ahead'));?></td></tr>
	<tr><th><?php echo esc_html(KWB_I18n::t('default_capacity'));?></th><td><?php self::number('default_capacity',KWB_I18n::t('capacity'),'1','10000',KWB_I18n::t('places_per_session'));?></td></tr>
	</tbody></table>

	<?php submit_button(KWB_I18n::t('save_settings'));?></form></div><?php
	}
}
