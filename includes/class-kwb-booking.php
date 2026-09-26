<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class KWB_Booking {
	const META_ENABLED='_kwb_enabled';
	const META_MONTHLY='_kwb_monthly_enabled';
	const META_MONTHLY_PRICE='_kwb_monthly_price';
	const META_SINGLE='_kwb_single_enabled';
	const META_SINGLE_PRICE='_kwb_single_price';
	const META_SCHEDULE='_kwb_weekly_schedule';
	const META_BLACKOUTS='_kwb_blackouts';
	const META_HORIZON='_kwb_horizon_months';
	const META_MAX_MONTHS='_kwb_max_booking_months';
	const META_REMINDER_OVERRIDE='_kwb_reminder_minutes_override';
	const META_RSVP_OVERRIDE='_kwb_rsvp_override';
	const META_BOOKING_CUTOFF='_kwb_booking_cutoff_minutes';
	const META_MAX_QTY='_kwb_max_qty';
	const META_LOCATION='_kwb_location_override';
	const META_CAL_DESCRIPTION='_kwb_calendar_description';

	public static function init(){
		add_filter('woocommerce_product_data_tabs',array(__CLASS__,'tab'));
		add_action('woocommerce_product_data_panels',array(__CLASS__,'panel'));
		add_action('admin_enqueue_scripts',array(__CLASS__,'admin_assets'));
		add_action('wp_enqueue_scripts',array(__CLASS__,'frontend_assets'));
		add_action('woocommerce_process_product_meta',array(__CLASS__,'save'));
		add_filter('woocommerce_is_purchasable',array(__CLASS__,'purchasable'),20,2);
		add_filter('woocommerce_get_price_html',array(__CLASS__,'price_html'),20,2);
		add_action('woocommerce_before_add_to_cart_button',array(__CLASS__,'fields'));
		add_filter('woocommerce_add_to_cart_validation',array(__CLASS__,'validate'),10,6);
		add_filter('woocommerce_add_cart_item_data',array(__CLASS__,'cart_data'),10,3);
		add_filter('woocommerce_get_item_data',array(__CLASS__,'display_cart_data'),10,2);
		add_action('woocommerce_before_calculate_totals',array(__CLASS__,'prices'),20);
		add_action('woocommerce_checkout_create_order_line_item',array(__CLASS__,'order_meta'),10,4);
		add_action('woocommerce_check_cart_items',array(__CLASS__,'cart_capacity'));
		add_action('woocommerce_email_after_order_table',array(__CLASS__,'email_links'),20,4);
		add_action('woocommerce_order_details_after_order_table',array(__CLASS__,'order_links'),20);
		add_action('template_redirect',array(__CLASS__,'ics_download'));
	}

	public static function admin_assets($hook){
		if(!in_array($hook,array('post.php','post-new.php'),true))return;
		$screen=get_current_screen();if(!$screen||'product'!==$screen->post_type)return;
		wp_enqueue_script('kwb-admin',plugins_url('../assets/kwb-admin.js',__FILE__),array(),KWB_VERSION,true);
		wp_localize_script('kwb-admin','KWB_ADMIN_I18N',array('days'=>KWB_I18n::t('days'),'capacity'=>KWB_I18n::t('capacity'),'remove'=>KWB_I18n::t('remove')));
		wp_enqueue_style('kwb-admin',plugins_url('../assets/kwb-admin.css',__FILE__),array(),KWB_VERSION);
	}

	public static function frontend_assets(){
		if(!is_product())return;
		wp_enqueue_script('kwb-calendar',plugins_url('../assets/kwb-calendar.js',__FILE__),array(),KWB_VERSION,true);
		wp_localize_script('kwb-calendar','KWB_CAL_I18N',array(
			'months'=>KWB_I18n::t('months'),'days'=>KWB_I18n::t('day_short'),'no_dates'=>KWB_I18n::t('no_dates'),
			'choose_month'=>KWB_I18n::t('choose_month'),'choose_date'=>KWB_I18n::t('choose_date'),'full'=>KWB_I18n::t('full'),
			'available_place'=>KWB_I18n::t('available_place'),'available_places'=>KWB_I18n::t('available_places'),'choose'=>KWB_I18n::t('choose'),
			'unavailable_month'=>KWB_I18n::t('unavailable_month'),'selected_date'=>KWB_I18n::t('selected_date'),'place'=>KWB_I18n::t('place'),'places'=>KWB_I18n::t('places'),
			'previous_month'=>KWB_I18n::t('previous_month'),'next_month'=>KWB_I18n::t('next_month'),'greek'=>KWB_I18n::is_greek(),
			'selected_month'=>KWB_I18n::t('selected_month'),'participation_count_singular'=>KWB_I18n::t('participation_count_singular'),'participation_count_plural'=>KWB_I18n::t('participation_count_plural'),
			'selected_months'=>KWB_I18n::t('selected_months'),'monthly_date_blocked'=>KWB_I18n::t('monthly_date_blocked'),
			'max_months_hint'=>KWB_I18n::t('max_months_hint'),'max_months_reached'=>KWB_I18n::t('max_months_reached'),
			'month_singular'=>KWB_I18n::t('month_singular'),'month_plural'=>KWB_I18n::t('month_plural'),'remove_month'=>KWB_I18n::t('remove_month'),
		));
		wp_enqueue_style('kwb-calendar',plugins_url('../assets/kwb-calendar.css',__FILE__),array(),KWB_VERSION);
	}

	public static function tab($tabs){
		$tabs['kwb_booking']=array('label'=>KWB_I18n::t('booking_tab'),'target'=>'kwb_booking_product_data','class'=>array('show_if_simple','show_if_variable'),'priority'=>80);
		return $tabs;
	}

	public static function panel(){
		global $post;if(!$post)return;
		wp_nonce_field('kwb_save_'.$post->ID,'kwb_nonce');
		$g=function($k,$d='')use($post){$v=get_post_meta($post->ID,$k,true);return ''===$v?$d:$v;};
		$schedule=self::schedule($post->ID);$black=array_keys(self::blackouts($post->ID));
		?>
		<div id="kwb_booking_product_data" class="panel woocommerce_options_panel hidden"><div class="options_group">
		<?php
		woocommerce_wp_checkbox(array('id'=>self::META_ENABLED,'label'=>KWB_I18n::t('enable_booking'),'value'=>$g(self::META_ENABLED)));
		woocommerce_wp_checkbox(array('id'=>self::META_MONTHLY,'label'=>KWB_I18n::t('monthly_participation'),'description'=>KWB_I18n::t('monthly_reserves_all'),'value'=>$g(self::META_MONTHLY)));
		woocommerce_wp_text_input(array('id'=>self::META_MONTHLY_PRICE,'label'=>KWB_I18n::t('monthly_price'),'value'=>$g(self::META_MONTHLY_PRICE),'type'=>'number','custom_attributes'=>array('min'=>'0','step'=>'0.01')));
		woocommerce_wp_checkbox(array('id'=>self::META_SINGLE,'label'=>KWB_I18n::t('single_participation'),'value'=>$g(self::META_SINGLE)));
		woocommerce_wp_text_input(array('id'=>self::META_SINGLE_PRICE,'label'=>KWB_I18n::t('single_price'),'value'=>$g(self::META_SINGLE_PRICE),'type'=>'number','custom_attributes'=>array('min'=>'0','step'=>'0.01')));
		woocommerce_wp_text_input(array('id'=>self::META_HORIZON,'label'=>KWB_I18n::t('horizon_months'),'value'=>$g(self::META_HORIZON,KWB_Settings::get('default_horizon_months',3)),'type'=>'number','custom_attributes'=>array('min'=>'1','max'=>'12','step'=>'1')));
		woocommerce_wp_text_input(array('id'=>self::META_MAX_MONTHS,'label'=>KWB_I18n::t('max_booking_months'),'description'=>KWB_I18n::t('max_booking_months_help'),'value'=>$g(self::META_MAX_MONTHS,1),'type'=>'number','custom_attributes'=>array('min'=>'1','max'=>'12','step'=>'1')));
		woocommerce_wp_text_input(array('id'=>self::META_REMINDER_OVERRIDE,'label'=>KWB_I18n::t('reminder_override'),'description'=>KWB_I18n::t('blank_general'),'value'=>$g(self::META_REMINDER_OVERRIDE),'type'=>'number','custom_attributes'=>array('min'=>'5','max'=>'10080','step'=>'1')));
		woocommerce_wp_select(array('id'=>self::META_RSVP_OVERRIDE,'label'=>KWB_I18n::t('rsvp_workshop'),'value'=>$g(self::META_RSVP_OVERRIDE,'inherit'),'options'=>array('inherit'=>KWB_I18n::t('inherit_global'),'on'=>KWB_I18n::t('enabled'),'off'=>KWB_I18n::t('disabled'))));
		woocommerce_wp_text_input(array('id'=>self::META_BOOKING_CUTOFF,'label'=>KWB_I18n::t('booking_cutoff'),'description'=>KWB_I18n::t('cutoff_help'),'value'=>$g(self::META_BOOKING_CUTOFF),'type'=>'number','custom_attributes'=>array('min'=>'0','max'=>'10080','step'=>'1')));
		woocommerce_wp_text_input(array('id'=>self::META_MAX_QTY,'label'=>KWB_I18n::t('max_participations'),'description'=>KWB_I18n::t('max_participations_help'),'value'=>$g(self::META_MAX_QTY),'type'=>'number','custom_attributes'=>array('min'=>'1','max'=>'100','step'=>'1')));
		woocommerce_wp_text_input(array('id'=>self::META_LOCATION,'label'=>KWB_I18n::t('location_override'),'description'=>KWB_I18n::t('blank_global_location'),'value'=>$g(self::META_LOCATION)));
		woocommerce_wp_textarea_input(array('id'=>self::META_CAL_DESCRIPTION,'label'=>KWB_I18n::t('calendar_description'),'description'=>KWB_I18n::t('calendar_description_help'),'value'=>$g(self::META_CAL_DESCRIPTION),'rows'=>4));
		?>
		<div class="kwb-admin-block">
		<h4><?php echo esc_html(KWB_I18n::t('weekly_schedule'));?></h4>
		<div id="kwb-schedule-rows">
		<?php foreach($schedule as$r): self::schedule_row($r); endforeach; if(!$schedule)self::schedule_row(array('weekday'=>1,'start'=>'09:00','end'=>'10:00','capacity'=>absint(KWB_Settings::get('default_capacity',10))));?>
		</div>
		<button type="button" class="button" id="kwb-add-schedule"><?php echo esc_html(KWB_I18n::t('add_time'));?></button>
		<textarea id="<?php echo esc_attr(self::META_SCHEDULE);?>" name="<?php echo esc_attr(self::META_SCHEDULE);?>" hidden><?php echo esc_textarea($g(self::META_SCHEDULE));?></textarea>
		</div>
		<div class="kwb-admin-block">
		<h4><?php echo esc_html(KWB_I18n::t('blackout_dates'));?></h4>
		<div id="kwb-blackout-rows">
		<?php foreach($black as$d): self::blackout_row($d); endforeach; if(!$black)self::blackout_row('');?>
		</div>
		<button type="button" class="button" id="kwb-add-blackout"><?php echo esc_html(KWB_I18n::t('add_date'));?></button>
		<textarea id="<?php echo esc_attr(self::META_BLACKOUTS);?>" name="<?php echo esc_attr(self::META_BLACKOUTS);?>" hidden><?php echo esc_textarea($g(self::META_BLACKOUTS));?></textarea>
		</div>
		</div></div>
		<?php
	}
	private static function schedule_row($r){$day_names=KWB_I18n::t('days');$days=array();foreach($day_names as$i=>$name)$days[$i+1]=$name;?>
		<div class="kwb-schedule-row"><select class="kwb-day"><?php foreach($days as$k=>$v):?><option value="<?php echo esc_attr($k);?>" <?php selected((int)$r['weekday'],$k);?>><?php echo esc_html($v);?></option><?php endforeach;?></select><input class="kwb-start" type="time" value="<?php echo esc_attr($r['start']);?>"><input class="kwb-end" type="time" value="<?php echo esc_attr($r['end']);?>"><input class="kwb-capacity" type="number" min="1" max="10000" value="<?php echo esc_attr($r['capacity']);?>" placeholder="<?php echo esc_attr(KWB_I18n::t('capacity'));?>"><button type="button" class="button-link-delete kwb-remove-row"><?php echo esc_html(KWB_I18n::t('remove'));?></button></div>
	<?php }
	private static function blackout_row($date){?><div class="kwb-blackout-row"><input class="kwb-blackout-date" type="date" value="<?php echo esc_attr($date);?>"><button type="button" class="button-link-delete kwb-remove-row"><?php echo esc_html(KWB_I18n::t('remove'));?></button></div><?php }


	public static function save($id){
		if(!current_user_can('edit_post',$id))return;
		$n=isset($_POST['kwb_nonce'])?sanitize_text_field(wp_unslash($_POST['kwb_nonce'])):'';
		if(!$n||!wp_verify_nonce($n,'kwb_save_'.$id))return;
		foreach(array(self::META_ENABLED,self::META_MONTHLY,self::META_SINGLE) as $k)update_post_meta($id,$k,isset($_POST[$k])?'yes':'no');
		foreach(array(self::META_MONTHLY_PRICE,self::META_SINGLE_PRICE) as $k){$v=isset($_POST[$k])?wc_format_decimal(wp_unslash($_POST[$k])):'';update_post_meta($id,$k,$v);}
		$h=isset($_POST[self::META_HORIZON])?absint($_POST[self::META_HORIZON]):3;$h=max(1,min(12,$h));update_post_meta($id,self::META_HORIZON,$h);
		$mm=isset($_POST[self::META_MAX_MONTHS])?absint($_POST[self::META_MAX_MONTHS]):1;update_post_meta($id,self::META_MAX_MONTHS,max(1,min($h,$mm)));
		foreach(array(self::META_REMINDER_OVERRIDE,self::META_BOOKING_CUTOFF,self::META_MAX_QTY)as$k){
			$v=isset($_POST[$k])?trim((string)wp_unslash($_POST[$k])):'';update_post_meta($id,$k,''===$v?'':absint($v));
		}
		$rsvp=isset($_POST[self::META_RSVP_OVERRIDE])?sanitize_key(wp_unslash($_POST[self::META_RSVP_OVERRIDE])):'inherit';
		update_post_meta($id,self::META_RSVP_OVERRIDE,in_array($rsvp,array('inherit','on','off'),true)?$rsvp:'inherit');
		$loc=isset($_POST[self::META_LOCATION])?sanitize_text_field(wp_unslash($_POST[self::META_LOCATION])):'';update_post_meta($id,self::META_LOCATION,$loc);
		$cal_desc=isset($_POST[self::META_CAL_DESCRIPTION])?sanitize_textarea_field(wp_unslash($_POST[self::META_CAL_DESCRIPTION])):'';update_post_meta($id,self::META_CAL_DESCRIPTION,$cal_desc);
		$raw=isset($_POST[self::META_SCHEDULE])?sanitize_textarea_field(wp_unslash($_POST[self::META_SCHEDULE])):'';
		$out=array();
		foreach(array_slice(preg_split('/\r\n|\r|\n/',$raw),0,50) as $line){
			$p=array_map('trim',explode('|',$line));if(4!==count($p))continue;
			$d=absint($p[0]);$s=$p[1];$e=$p[2];$c=max(1,min(10000,absint($p[3])));
			if($d<1||$d>7||!self::time_ok($s)||!self::time_ok($e)||$e<=$s)continue;
			$out[]=sprintf('%d|%s|%s|%d',$d,$s,$e,$c);
		}
		update_post_meta($id,self::META_SCHEDULE,implode("\n",array_unique($out)));
		$raw=isset($_POST[self::META_BLACKOUTS])?sanitize_textarea_field(wp_unslash($_POST[self::META_BLACKOUTS])):'';
		$out=array();foreach(array_slice(preg_split('/\r\n|\r|\n/',$raw),0,250) as $d){$d=trim($d);if(self::date_ok($d))$out[]=$d;}
		update_post_meta($id,self::META_BLACKOUTS,implode("\n",array_unique($out)));
	}

	public static function date_ok($d){$x=DateTime::createFromFormat('Y-m-d',(string)$d);return $x&&$x->format('Y-m-d')===$d;}
	public static function time_ok($t){return(bool)preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',(string)$t);}
	public static function enabled($id){return'yes'===get_post_meta($id,self::META_ENABLED,true);}
	public static function mode($id,$m){$k='monthly'===$m?self::META_MONTHLY:self::META_SINGLE;$pk='monthly'===$m?self::META_MONTHLY_PRICE:self::META_SINGLE_PRICE;return'yes'===get_post_meta($id,$k,true)&&''!==(string)get_post_meta($id,$pk,true);}
	public static function price($id,$m){$k='monthly'===$m?self::META_MONTHLY_PRICE:self::META_SINGLE_PRICE;return(float)wc_format_decimal(get_post_meta($id,$k,true));}

	public static function reminder_minutes($id){$v=get_post_meta($id,self::META_REMINDER_OVERRIDE,true);return ''===$v?absint(KWB_Settings::get('reminder_lead_minutes',240)):max(5,absint($v));}
	public static function rsvp_enabled($id){$v=(string)get_post_meta($id,self::META_RSVP_OVERRIDE,true);if('on'===$v)return true;if('off'===$v)return false;return(bool)KWB_Settings::get('rsvp_enabled',1);}
	public static function booking_cutoff_minutes($id){return absint(get_post_meta($id,self::META_BOOKING_CUTOFF,true));}
	public static function max_qty($id){return absint(get_post_meta($id,self::META_MAX_QTY,true));}
	public static function max_booking_months($id){
		$h=max(1,min(12,absint(get_post_meta($id,self::META_HORIZON,true)?:KWB_Settings::get('default_horizon_months',3))));
		$v=absint(get_post_meta($id,self::META_MAX_MONTHS,true)?:1);
		return max(1,min($h,$v));
	}
	public static function location($id){
		$v=trim((string)get_post_meta($id,self::META_LOCATION,true));if($v)return$v;
		$v=trim((string)KWB_Settings::get('calendar_location',''));if($v)return$v;
		$parts=array_filter(array(
			trim((string)get_option('woocommerce_store_address','')),
			trim((string)get_option('woocommerce_store_address_2','')),
			trim((string)get_option('woocommerce_store_city','')),
			trim((string)get_option('woocommerce_store_postcode','')),
		));
		$country_setting=(string)get_option('woocommerce_default_country','');$country='';$state='';
		if($country_setting){$bits=explode(':',$country_setting,2);$country=$bits[0]??'';$state=$bits[1]??'';}
		if(function_exists('WC')&&WC()->countries){
			if($state&&!empty(WC()->countries->states[$country][$state]))$parts[]=WC()->countries->states[$country][$state];
			if($country&&!empty(WC()->countries->countries[$country]))$parts[]=WC()->countries->countries[$country];
		}
		return $parts?implode(', ',array_unique($parts)):get_bloginfo('name');
	}
	private static function product_calendar_description($id){
		$custom=trim((string)get_post_meta($id,self::META_CAL_DESCRIPTION,true));if($custom)return$custom;
		$product=wc_get_product($id);if(!$product)return'';
		$short=html_entity_decode(wp_strip_all_tags((string)$product->get_short_description()),ENT_QUOTES,get_bloginfo('charset'));
		$short=preg_replace('/\s+/u',' ',trim($short));
		return (string)$short;
	}

	public static function purchasable($purchasable,$product){
		if(!$product instanceof WC_Product)return$purchasable;
		$id=$product->get_parent_id()?:$product->get_id();
		if(!self::enabled($id))return$purchasable;
		return self::mode($id,'monthly')||self::mode($id,'single');
	}

	public static function price_html($html,$product){
		if(!$product instanceof WC_Product)return$html;
		$id=$product->get_parent_id()?:$product->get_id();
		if(!self::enabled($id))return$html;
		$parts=array();
		if(self::mode($id,'monthly'))$parts[]=wp_strip_all_tags(wc_price(self::price($id,'monthly'))).'/'.KWB_I18n::t('per_month');
		if(self::mode($id,'single'))$parts[]=wp_strip_all_tags(wc_price(self::price($id,'single'))).' ' .KWB_I18n::t('single_lower');
		if(!$parts)return$html;
		return '<span class="price kwb-price">'.esc_html(implode(' '.KWB_I18n::t('or').' ',$parts)).'</span>';
	}

	public static function schedule($id){
		$out=array();$raw=(string)get_post_meta($id,self::META_SCHEDULE,true);
		foreach(preg_split('/\r\n|\r|\n/',$raw) as $line){$p=array_map('trim',explode('|',$line));if(4!==count($p))continue;$d=absint($p[0]);if($d<1||$d>7||!self::time_ok($p[1])||!self::time_ok($p[2]))continue;$out[]=array('weekday'=>$d,'start'=>$p[1],'end'=>$p[2],'capacity'=>max(1,min(10000,absint($p[3]))));}
		return$out;
	}
	public static function blackouts($id){$o=array();foreach(preg_split('/\r\n|\r|\n/',(string)get_post_meta($id,self::META_BLACKOUTS,true))as$d){$d=trim($d);if(self::date_ok($d))$o[$d]=1;}return$o;}
	public static function occ_id($id,$d,$s,$e){return hash('sha256',absint($id).'|'.$d.'|'.$s.'|'.$e);}

	public static function month_occurrences($id,$month,$future=true){
		if(!preg_match('/^\d{4}-\d{2}$/',(string)$month))return array();
		$tz=wp_timezone();$first=DateTimeImmutable::createFromFormat('!Y-m-d',$month.'-01',$tz);if(!$first)return array();
		$last=$first->modify('last day of this month');$now=new DateTimeImmutable('now',$tz);$sched=self::schedule($id);$black=self::blackouts($id);$out=array();
		for($day=$first;$day<=$last;$day=$day->modify('+1 day')){
			$date=$day->format('Y-m-d');if(isset($black[$date]))continue;$wd=(int)$day->format('N');
			foreach($sched as$r){if($wd!==(int)$r['weekday'])continue;$st=DateTimeImmutable::createFromFormat('Y-m-d H:i',$date.' '.$r['start'],$tz);$en=DateTimeImmutable::createFromFormat('Y-m-d H:i',$date.' '.$r['end'],$tz);if(!$st||!$en||($future&&$st<=$now))continue;$oid=self::occ_id($id,$date,$r['start'],$r['end']);$out[$oid]=array('id'=>$oid,'date'=>$date,'start'=>$r['start'],'end'=>$r['end'],'capacity'=>(int)$r['capacity'],'start_dt'=>$st,'end_dt'=>$en);}
		}
		uasort($out,static function($a,$b){return$a['start_dt']<=>$b['start_dt'];});return$out;
	}
	public static function future_occurrences($id){$h=max(1,min(12,absint(get_post_meta($id,self::META_HORIZON,true)?:KWB_Settings::get('default_horizon_months',3))));$tz=wp_timezone();$c=new DateTimeImmutable('first day of this month 00:00:00',$tz);$out=array();for($i=0;$i<$h;$i++)$out+=self::month_occurrences($id,$c->modify('+'.$i.' month')->format('Y-m'),true);return$out;}
	public static function month_options($id){$h=max(1,min(12,absint(get_post_meta($id,self::META_HORIZON,true)?:KWB_Settings::get('default_horizon_months',3))));$tz=wp_timezone();$c=new DateTimeImmutable('first day of this month 00:00:00',$tz);$out=array();for($i=0;$i<$h;$i++){$m=$c->modify('+'.$i.' month')->format('Y-m');$os=self::month_occurrences($id,$m,true);if(!$os)continue;$left=null;foreach($os as$o){$v=self::remaining($id,$o);$left=null===$left?$v:min($left,$v);}if($left<1)continue;$md=DateTimeImmutable::createFromFormat('!Y-m',$m,$tz);$out[$m]=array('label'=>wp_date('F Y',$md->getTimestamp(),$tz),'count'=>count($os),'remaining'=>$left,'occurrences'=>$os);}return$out;}
	public static function hydrate($o){$tz=wp_timezone();$o['start_dt']=DateTimeImmutable::createFromFormat('Y-m-d H:i',$o['date'].' '.$o['start'],$tz);$o['end_dt']=DateTimeImmutable::createFromFormat('Y-m-d H:i',$o['date'].' '.$o['end'],$tz);return$o;}
	public static function label($o){return wp_date('l d/m/Y',$o['start_dt']->getTimestamp(),wp_timezone()).' '.$o['start'].'–'.$o['end'];}
	public static function month_label($month){
		if(!preg_match('/^\d{4}-\d{2}$/',(string)$month))return(string)$month;
		$dt=DateTimeImmutable::createFromFormat('!Y-m',(string)$month,wp_timezone());
		return$dt?wp_date('F Y',$dt->getTimestamp(),wp_timezone()):(string)$month;
	}

	public static function fields(){
		global$product;if(!$product||!self::enabled($product->get_id()))return;$id=$product->get_id();$m=self::mode($id,'monthly');$s=self::mode($id,'single');if(!$m&&!$s)return;
		$months=$m?self::month_options($id):array();$occ=($m||$s)?self::future_occurrences($id):array();$def=$m?'monthly':'single';wp_nonce_field('kwb_cart_'.$id,'kwb_cart_nonce');
		$calendar=array('months'=>array(),'occurrences'=>array(),'maxMonths'=>self::max_booking_months($id));
		foreach($months as$k=>$v){$calendar['months'][$k]=array('label'=>$v['label'],'remaining'=>$v['remaining'],'count'=>$v['count']);}
		foreach($occ as$o){$calendar['occurrences'][$o['date']][]=array('id'=>$o['id'],'date'=>$o['date'],'start'=>$o['start'],'end'=>$o['end'],'remaining'=>self::remaining($id,$o));}
		?>
		<div class="kwb-booking-fields" data-calendar="<?php echo esc_attr(wp_json_encode($calendar));?>">
		<?php if($m&&$s):?><label for="kwb_booking_type"><strong><?php echo esc_html(KWB_I18n::t('participation_type'));?></strong></label><select name="kwb_booking_type" id="kwb_booking_type"><option value="monthly"><?php echo esc_html(KWB_I18n::t('monthly_participation').' — '.wp_strip_all_tags(wc_price(self::price($id,'monthly'))));?></option><option value="single"><?php echo esc_html(KWB_I18n::t('single_participation').' — '.wp_strip_all_tags(wc_price(self::price($id,'single'))));?></option></select><?php else:?><input type="hidden" name="kwb_booking_type" id="kwb_booking_type" value="<?php echo esc_attr($def);?>"><?php endif;?>
		<div class="kwb-calendar-heading"><strong id="kwb-calendar-label"><?php echo esc_html('monthly'===$def?KWB_I18n::t('choose_month'):KWB_I18n::t('choose_date'));?></strong></div>
		<input type="hidden" name="kwb_month" id="kwb_month" value="">
		<input type="hidden" name="kwb_months" id="kwb_months" value="">
		<input type="hidden" name="kwb_occurrence" id="kwb_occurrence" value="">
		<div class="kwb-calendar-nav"><button type="button" class="kwb-prev" aria-label="<?php echo esc_attr(KWB_I18n::t('previous_month'));?>">‹</button><span class="kwb-current-month"></span><button type="button" class="kwb-next" aria-label="<?php echo esc_attr(KWB_I18n::t('next_month'));?>">›</button></div>
		<div class="kwb-calendar-grid"></div>
		<div class="kwb-calendar-help"><?php echo esc_html(KWB_I18n::t('calendar_help'));?></div>
		<div class="kwb-month-action">
			<div class="kwb-month-select-wrap"><button type="button" class="button kwb-select-month"><?php echo esc_html(KWB_I18n::t('choose_this_month'));?></button></div>
			<div class="kwb-selection-summary" aria-live="polite"></div>
			<div class="kwb-month-limit" aria-live="polite"></div>
		</div>
		<small><?php echo esc_html(KWB_I18n::t('quantity_participants'));?></small>
		</div>
		<?php
	}


	private static function nonce($id){$n=isset($_POST['kwb_cart_nonce'])?sanitize_text_field(wp_unslash($_POST['kwb_cart_nonce'])):'';return$n&&wp_verify_nonce($n,'kwb_cart_'.$id);}
	private static function request($id){
		$type=isset($_POST['kwb_booking_type'])?sanitize_key(wp_unslash($_POST['kwb_booking_type'])):'';
		if(!self::mode($id,$type))return new WP_Error('kwb_type',KWB_I18n::t('type_unavailable'));
		if('monthly'===$type){
			$raw=isset($_POST['kwb_months'])?sanitize_text_field(wp_unslash($_POST['kwb_months'])):'';
			$months=array_values(array_unique(array_filter(array_map('trim',explode(',',$raw)),static function($m){return(bool)preg_match('/^\d{4}-\d{2}$/',$m);})));
			if(!$months){
				$legacy=isset($_POST['kwb_month'])?sanitize_text_field(wp_unslash($_POST['kwb_month'])):'';
				if(preg_match('/^\d{4}-\d{2}$/',$legacy))$months=array($legacy);
			}
			$max=self::max_booking_months($id);
			if(!$months)return new WP_Error('kwb_month',KWB_I18n::t('select_available_month'));
			if(count($months)>$max)return new WP_Error('kwb_month_limit',KWB_I18n::t('max_months_reached',array('max'=>$max,'month_word'=>1===$max?KWB_I18n::t('month_singular'):KWB_I18n::t('month_plural'))));
			sort($months,SORT_STRING);
			$opts=self::month_options($id);$occurrences=array();$seen=array();
			foreach($months as$m){
				if(empty($opts[$m]['occurrences']))return new WP_Error('kwb_month',KWB_I18n::t('select_available_month'));
				foreach($opts[$m]['occurrences']as$o){
					if(isset($seen[$o['id']]))continue;$seen[$o['id']]=1;$occurrences[]=$o;
				}
			}
			return array('type'=>'monthly','month'=>$months[0],'months'=>$months,'price'=>self::price($id,'monthly')*count($months),'occurrences'=>$occurrences);
		}
		$oid=isset($_POST['kwb_occurrence'])?sanitize_text_field(wp_unslash($_POST['kwb_occurrence'])):'';
		$all=self::future_occurrences($id);
		if(empty($all[$oid]))return new WP_Error('kwb_occ',KWB_I18n::t('select_available_date'));
		return array('type'=>'single','month'=>'','months'=>array(),'price'=>self::price($id,'single'),'occurrences'=>array($all[$oid]));
	}

	public static function validate($passed,$product_id,$qty,$variation_id=0,$vars=array(),$data=array()){
		$id=$variation_id?wp_get_post_parent_id($variation_id):$product_id;if(!self::enabled($id))return$passed;if(!self::nonce($id)){wc_add_notice(KWB_I18n::t('form_expired'),'error');return false;}$b=self::request($id);if(is_wp_error($b)){wc_add_notice($b->get_error_message(),'error');return false;}$q=max(1,absint($qty));$max=self::max_qty($id);if($max&&$q>$max){wc_add_notice(KWB_I18n::t('max_limit',array('max'=>$max)),'error');return false;}$cut=self::booking_cutoff_minutes($id);foreach($b['occurrences']as$o){if($cut&&$o['start_dt']->getTimestamp()-time()<($cut*MINUTE_IN_SECONDS)){wc_add_notice(KWB_I18n::t('bookings_closed',array('slot'=>self::label($o))),'error');return false;}if($q>self::remaining($id,$o)){wc_add_notice(KWB_I18n::t('not_enough_places',array('slot'=>self::label($o))),'error');return false;}}return$passed;
	}
	public static function cart_data($data,$product_id,$variation_id){$id=$variation_id?wp_get_post_parent_id($variation_id):$product_id;if(!self::enabled($id)||!self::nonce($id))return$data;$b=self::request($id);if(is_wp_error($b))return$data;$os=array();foreach($b['occurrences']as$o)$os[]=array('id'=>$o['id'],'date'=>$o['date'],'start'=>$o['start'],'end'=>$o['end'],'capacity'=>(int)$o['capacity']);$data['kwb_booking']=array('type'=>$b['type'],'month'=>$b['month'],'months'=>$b['months']??array(),'price'=>(float)$b['price'],'occurrences'=>$os);$data['kwb_unique']=md5(wp_json_encode($data['kwb_booking']).'|'.microtime(true));return$data;}
	public static function prices($cart){if(is_admin()&&!defined('DOING_AJAX'))return;foreach($cart->get_cart()as$item)if(isset($item['kwb_booking']['price']))$item['data']->set_price((float)$item['kwb_booking']['price']);}
	public static function display_cart_data($rows,$item){
		if(empty($item['kwb_booking']))return$rows;$b=$item['kwb_booking'];
		$rows[]=array('key'=>KWB_I18n::t('participation'),'value'=>'monthly'===$b['type']?KWB_I18n::t('monthly'):KWB_I18n::t('single'));
		if('monthly'===$b['type']){
			$months=!empty($b['months'])?(array)$b['months']:array($b['month']);
			$labels=array_map(array(__CLASS__,'month_label'),$months);$rows[]=array('key'=>count($months)>1?KWB_I18n::t('months_label'):KWB_I18n::t('month'),'value'=>esc_html(implode(', ',$labels)));
			$rows[]=array('key'=>KWB_I18n::t('sessions'),'value'=>esc_html((string)count($b['occurrences'])));
		}else{$rows[]=array('key'=>KWB_I18n::t('date'),'value'=>esc_html(self::label(self::hydrate($b['occurrences'][0]))));}
		return$rows;
	}
	public static function order_meta($item,$key,$values,$order){
		if(empty($values['kwb_booking']))return;$b=$values['kwb_booking'];
		$item->add_meta_data('_kwb_booking_type',$b['type'],true);
		$item->add_meta_data('_kwb_booking_month',$b['month'],true);
		$item->add_meta_data('_kwb_booking_months',wp_json_encode($b['months']??array()),true);
		$item->add_meta_data('_kwb_occurrences',wp_json_encode($b['occurrences']),true);
		$item->add_meta_data(KWB_I18n::t('participation_type_meta'),'monthly'===$b['type']?KWB_I18n::t('monthly'):KWB_I18n::t('single'),true);
		if('monthly'===$b['type']){
			$months=!empty($b['months'])?(array)$b['months']:array($b['month']);
			$labels=array_map(array(__CLASS__,'month_label'),$months);$item->add_meta_data(count($months)>1?KWB_I18n::t('months_label'):KWB_I18n::t('month'),implode(', ',$labels),true);
			$item->add_meta_data(KWB_I18n::t('sessions'),count($b['occurrences']),true);
		}else{
			$o=self::hydrate($b['occurrences'][0]);
			$item->add_meta_data(KWB_I18n::t('workshop_date'),wp_date('d/m/Y',$o['start_dt']->getTimestamp(),wp_timezone()),true);
			$item->add_meta_data(KWB_I18n::t('workshop_time'),$o['start'].'–'.$o['end'],true);
		}
	}
	public static function cart_capacity(){if(!WC()->cart)return;$req=array();foreach(WC()->cart->get_cart()as$item){if(empty($item['kwb_booking']['occurrences']))continue;$id=$item['variation_id']?wp_get_post_parent_id($item['variation_id']):$item['product_id'];$q=max(1,absint($item['quantity']));foreach($item['kwb_booking']['occurrences']as$o){$k=$id.'|'.$o['id'];if(!isset($req[$k]))$req[$k]=array('id'=>$id,'o'=>self::hydrate($o),'q'=>0);$req[$k]['q']+=$q;}}foreach($req as$r)if($r['q']>self::remaining($r['id'],$r['o']))wc_add_notice(KWB_I18n::t('availability_changed',array('slot'=>self::label($r['o']))),'error');}

	public static function remaining($id,$o){return max(0,absint($o['capacity'])-self::booked($id,$o['id']));}
	public static function occurrence_declined($item,$oid){$d=(array)$item->get_meta('_kwb_declined_occurrences',true);return in_array((string)$oid,array_map('strval',$d),true);}
	public static function booked($id,$oid){$orders=wc_get_orders(array('status'=>array('wc-processing','wc-completed','wc-on-hold'),'limit'=>-1,'return'=>'ids'));$n=0;foreach($orders as$orid){$order=wc_get_order($orid);if(!$order)continue;foreach($order->get_items('line_item')as$item){$pid=$item->get_variation_id()?wp_get_post_parent_id($item->get_variation_id()):$item->get_product_id();if((int)$pid!==(int)$id||(KWB_Settings::get('release_on_no',1)&&self::occurrence_declined($item,$oid)))continue;$list=json_decode((string)$item->get_meta('_kwb_occurrences',true),true);if(!is_array($list))continue;foreach($list as$o)if(isset($o['id'])&&hash_equals((string)$oid,(string)$o['id'])){$n+=absint($item->get_quantity());break;}}}return$n;}

	public static function item_occurrences($item){$list=json_decode((string)$item->get_meta('_kwb_occurrences',true),true);$out=array();if(is_array($list))foreach($list as$o)if(!empty($o['id'])&&self::date_ok($o['date']??'')&&self::time_ok($o['start']??'')&&self::time_ok($o['end']??''))$out[]=self::hydrate($o);return$out;}

	private static function calendar_template($template,$item,$o,$order){
		return strtr((string)$template,array(
			'{{workshop}}'=>$item->get_name(),
			'{{date}}'=>wp_date('d/m/Y',$o['start_dt']->getTimestamp(),wp_timezone()),
			'{{time}}'=>$o['start'],
			'{{order_number}}'=>$order->get_order_number(),
		));
	}
	private static function calendar_description($item,$o,$order,$product_id){
		$parts=array();
		$about=self::product_calendar_description($product_id);if($about)$parts[]=$about;
		$base=self::calendar_template(KWB_Settings::get('calendar_description_template',KWB_I18n::t('calendar_description_default')),$item,$o,$order);if($base)$parts[]=$base;
		$type=(string)$item->get_meta('_kwb_booking_type',true);
		$parts[]=KWB_I18n::t('type').': '.('monthly'===$type?KWB_I18n::t('monthly'):KWB_I18n::t('single'));
		$parts[]=KWB_I18n::t('date_label').': '.wp_date('d/m/Y',$o['start_dt']->getTimestamp(),wp_timezone());
		$parts[]=KWB_I18n::t('time_label').': '.$o['start'].'–'.$o['end'];
		$product_url=get_permalink($product_id);if($product_url)$parts[]=KWB_I18n::t('workshop_info').': '.$product_url;
		return implode("\n\n",array_filter($parts));
	}
	public static function email_links($order,$admin,$plain,$email){if($admin||!$order instanceof WC_Order||in_array($order->get_status(),array('failed','cancelled','refunded'),true))return;self::calendar_links($order,$plain);}
	public static function order_links($order){if($order instanceof WC_Order)self::calendar_links($order,false);}
	private static function calendar_links($order,$plain=false){
		$google=(bool)KWB_Settings::get('calendar_google_link_enabled',1);$ics=(bool)KWB_Settings::get('calendar_ics_enabled',1);if(!$google&&!$ics)return;
		foreach($order->get_items('line_item')as$iid=>$item){
			$os=self::item_occurrences($item);if(!$os)continue;$pid=$item->get_variation_id()?wp_get_post_parent_id($item->get_variation_id()):$item->get_product_id();
			$count=count($os);
			if($plain){
				echo "\n".esc_html($item->get_name())."\n";
				echo KWB_I18n::t('calendar_plain_intro')."\n";
				if($google){
					if($count>1)echo KWB_I18n::t('google_each_date')."\n";
					foreach($os as$o)echo 'Google Calendar — '.wp_date('d/m/Y',$o['start_dt']->getTimestamp(),wp_timezone()).' '.$o['start'].'–'.$o['end'].': '.esc_url_raw(self::google($item,$o,$order,$pid))."\n";
				}
				if($ics)echo 'Apple / Outlook / iCalendar: '.esc_url_raw(self::ics_url($order->get_id(),$iid))."\n";
				continue;
			}

			$card='font-family:Roboto,Segoe UI,Arial,sans-serif;margin:22px 0;padding:18px;border:1px solid #e1e5e9;border-radius:10px;background:#fafbfc;color:#202a33';
			$button='display:inline-block;margin:7px 7px 0 0;padding:10px 14px;border-radius:7px;text-decoration:none;font-weight:600;line-height:1.25';
			echo '<div style="'.esc_attr($card).'">';
			echo '<div style="font-size:16px;font-weight:700;margin-bottom:7px">'.esc_html($item->get_name()).'</div>';
			echo '<div style="font-size:14px;line-height:1.55;margin-bottom:10px">📅 '.esc_html(KWB_I18n::t('calendar_intro')).'</div>';
			if($google){
				if($count>1)echo '<div style="font-size:13px;color:#5f6b76;margin:5px 0">'.esc_html(KWB_I18n::t('google_each_date_html')).'</div>';
				foreach($os as$o){
					$label='Google Calendar — '.wp_date('d/m/Y',$o['start_dt']->getTimestamp(),wp_timezone()).' '.$o['start'];
					echo '<a href="'.esc_url(self::google($item,$o,$order,$pid)).'" target="_blank" rel="noopener" style="'.esc_attr($button.';background:#fff;color:#1a73e8;border:1px solid #d7dce1').'">📅 '.esc_html($label).'</a>';
				}
			}
			if($ics){
				$ics_label='Apple / Outlook / iCalendar — '.KWB_I18n::t('add_to_calendar');
				echo '<a href="'.esc_url(self::ics_url($order->get_id(),$iid)).'" style="'.esc_attr($button.';background:#202a33;color:#fff;border:1px solid #202a33').'">📆 '.esc_html($ics_label).'</a>';
			}
			echo '</div>';
		}
	}
	private static function google($item,$o,$order,$product_id){
		$utc=new DateTimeZone('UTC');
		$title=self::calendar_template(KWB_Settings::get('calendar_title_template','{{workshop}}'),$item,$o,$order);
		$details=self::calendar_description($item,$o,$order,$product_id);
		return add_query_arg(array(
			'action'=>'TEMPLATE',
			'text'=>$title,
			'dates'=>$o['start_dt']->setTimezone($utc)->format('Ymd\THis\Z').'/'.$o['end_dt']->setTimezone($utc)->format('Ymd\THis\Z'),
			'details'=>$details,
			'location'=>self::location($product_id),
			'ctz'=>wp_timezone_string(),
		),'https://calendar.google.com/calendar/render');
	}
	private static function sig($oid,$iid){$order=wc_get_order(absint($oid));$item=$order?$order->get_item(absint($iid)):false;$ctx=absint($oid).'|'.absint($iid);if($order&&$item)$ctx.='|'.$order->get_order_key().'|'.hash('sha256',(string)$item->get_meta('_kwb_occurrences',true));return hash_hmac('sha256',$ctx,wp_salt('auth'));}
	private static function ics_url($oid,$iid){return add_query_arg(array('kwb_ics'=>1,'order_id'=>absint($oid),'item_id'=>absint($iid),'sig'=>self::sig($oid,$iid)),home_url('/'));}
	public static function ics_download(){// phpcs:disable WordPress.Security.NonceVerification.Recommended -- HMAC-authenticated read-only endpoint.
		if(empty($_GET['kwb_ics']))return;$oid=isset($_GET['order_id'])?absint($_GET['order_id']):0;$iid=isset($_GET['item_id'])?absint($_GET['item_id']):0;$sig=isset($_GET['sig'])?sanitize_text_field(wp_unslash($_GET['sig'])):'';if(!$oid||!$iid||!hash_equals(self::sig($oid,$iid),$sig)){status_header(403);exit;}$order=wc_get_order($oid);$item=$order?$order->get_item($iid):false;if(!$item){status_header(404);exit;}$os=self::item_occurrences($item);if(!$os){status_header(404);exit;}$utc=new DateTimeZone('UTC');$host=(string)wp_parse_url(home_url(),PHP_URL_HOST);$pid=$item->get_variation_id()?wp_get_post_parent_id($item->get_variation_id()):$item->get_product_id();$ics=array('BEGIN:VCALENDAR','VERSION:2.0','PRODID:-//e-iT//Workshop Bookings for WooCommerce//EN','CALSCALE:GREGORIAN','METHOD:PUBLISH');foreach($os as$i=>$o){$ics[]='BEGIN:VEVENT';$ics[]='UID:'.self::esc(sprintf('kwb-%d-%d-%d@%s',$oid,$iid,$i,$host));$ics[]='DTSTAMP:'.gmdate('Ymd\THis\Z');$ics[]='DTSTART:'.$o['start_dt']->setTimezone($utc)->format('Ymd\THis\Z');$ics[]='DTEND:'.$o['end_dt']->setTimezone($utc)->format('Ymd\THis\Z');$ics[]='SUMMARY:'.self::esc(self::calendar_template(KWB_Settings::get('calendar_title_template','{{workshop}}'),$item,$o,$order));$ics[]='DESCRIPTION:'.self::esc(self::calendar_description($item,$o,$order,$pid));$ics[]='LOCATION:'.self::esc(self::location($pid));$alarm=absint(KWB_Settings::get('calendar_alarm_minutes',240));if($alarm>0){$ics[]='BEGIN:VALARM';$ics[]='TRIGGER:-PT'.$alarm.'M';$ics[]='ACTION:DISPLAY';$ics[]='DESCRIPTION:'.self::esc(KWB_I18n::t('calendar_reminder'));$ics[]='END:VALARM';}$ics[]='END:VEVENT';}$ics[]='END:VCALENDAR';nocache_headers();header('X-Content-Type-Options: nosniff');header('Content-Type: text/calendar; charset=utf-8');header('Content-Disposition: attachment; filename="workshop-booking-'.$oid.'-'.$iid.'.ics"');// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo implode("\r\n",$ics)."\r\n";// phpcs:enable WordPress.Security.NonceVerification.Recommended
		exit;}
	private static function esc($v){return str_replace(array('\\',';',',',"\r\n","\r","\n"),array('\\\\','\\;','\\,','\\n','\\n','\\n'),(string)$v);}
}
