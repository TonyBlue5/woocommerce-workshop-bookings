<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class KWB_GitHub_Updater {
	const VERSION='0.4.0';
	const API='https://api.github.com/repos/TonyBlue5/woocommerce-workshop-bookings/releases/latest';
	const SLUG='kangiroo-workshop-bookings';
	const PUBLIC_KEY="-----BEGIN PUBLIC KEY-----\nMIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEA0r9E6m6osaldFxI1ALSi\nTaUaT5q0WtaA1tyjKwdNAyvkZKuZ2gppLEW8184AX+qg5BYp7YX/65nx1gYdHord\nXHv1YD+al4A6AcTZB6OV7omma/m0XauuyJWIW3jNyGyYkBDEdX3sfAlLG18VCBnv\n2FQQa4OcjXYSh03neJUHofXTL7N2G9YHqAr3BQdY3xTKAB5f/KdSGsAgNjJ+h9Sk\n7zB7E2LhcMBfKCnbkydbHmApoaiYq75OVizCFfbWkO3L0PuUpknLv8mo30Lw1y5a\noAUMbBjKmSri1L6Kwi7gq00ahJQ1cgLQhysXb5j9l7rgYu2jwnRneJ7K0qA3cgZc\ntLOQ8Bs2J/4pMuTpxIoqyHDS/GX/7+HPP86tNHCzEU2ckYSXkwzU7pyxgAFYO0Ul\nbQxBlBX74CqUWhDamRM+SbtPE3f0ZD+UJHwJhCbsSB9d4++QOL9ZVAcadpoCSGsO\nhRUTbOk+nue0VOihhqMw3u2vxn73qVhhesLz9BlqSheg/pWNk1wN6EBZWheC/xGR\n9J1r1aD5HgCIhR52RTbExZ4ANMZO2ZAFfGh8RynZiEx311mH2X0UPBxpdm8mqDsS\nC933cyvkeKiiqmnTMU9QF1HX07A+xyh3eGJiQNGmXL3GRrPS6O52tfla9wj0y77d\n6uBSKpmDU/gAfp1MiX3mz6cCAwEAAQ==\n-----END PUBLIC KEY-----";

	public static function init(){
		add_filter('pre_set_site_transient_update_plugins',array(__CLASS__,'updates'));
		add_filter('plugins_api',array(__CLASS__,'details'),20,3);
		add_filter('upgrader_pre_download',array(__CLASS__,'verify_download'),10,4);
	}
	private static function trusted($url,$name){$p=wp_parse_url($url);if(empty($p['scheme'])||'https'!==strtolower($p['scheme'])||empty($p['host'])||'github.com'!==strtolower($p['host']))return false;$path=(string)($p['path']??'');$prefix='/TonyBlue5/woocommerce-workshop-bookings/releases/download/';return 0===strpos($path,$prefix)&&'/'.$name===substr($path,-strlen('/'.$name));}
	private static function release(){
		$c=get_site_transient('kwb_github_release');if(is_array($c))return$c;
		$r=wp_remote_get(self::API,array('timeout'=>12,'redirection'=>0,'headers'=>array('Accept'=>'application/vnd.github+json','User-Agent'=>'Kangiroo-Workshop-Bookings/'.self::VERSION)));
		if(is_wp_error($r)||200!==wp_remote_retrieve_response_code($r)){set_site_transient('kwb_github_release',array(),HOUR_IN_SECONDS);return array();}
		$j=json_decode(wp_remote_retrieve_body($r),true);if(!is_array($j))return array();$v=ltrim((string)($j['tag_name']??''),'vV');if(!preg_match('/^\d+\.\d+\.\d+$/',$v))return array();
		$a=array();foreach((array)($j['assets']??array())as$x){$n=sanitize_file_name((string)($x['name']??''));$u=esc_url_raw((string)($x['browser_download_url']??''));if($n&&$u)$a[$n]=$u;}
		$z='kangiroo-workshop-bookings.zip';$h=$z.'.sha256';$s=$z.'.sig';
		if(empty($a[$z])||empty($a[$h])||empty($a[$s])||!self::trusted($a[$z],$z)||!self::trusted($a[$h],$h)||!self::trusted($a[$s],$s))return array();
		$hr=wp_remote_get($a[$h],array('timeout'=>10,'redirection'=>5,'headers'=>array('User-Agent'=>'Kangiroo-Workshop-Bookings/'.self::VERSION)));if(is_wp_error($hr)||200!==wp_remote_retrieve_response_code($hr)||!preg_match('/\b([a-f0-9]{64})\b/i',wp_remote_retrieve_body($hr),$m))return array();
		$d=array('version'=>$v,'package'=>$a[$z],'sha256'=>strtolower($m[1]),'signature'=>$a[$s],'url'=>esc_url_raw((string)($j['html_url']??'')),'body'=>wp_kses_post((string)($j['body']??'')));set_site_transient('kwb_github_release',$d,12*HOUR_IN_SECONDS);return$d;
	}
	public static function updates($t){if(!is_object($t)||empty($t->checked))return$t;$r=self::release();$p=plugin_basename(KWB_PLUGIN_FILE);if($r&&version_compare(self::VERSION,$r['version'],'<'))$t->response[$p]=(object)array('slug'=>self::SLUG,'plugin'=>$p,'new_version'=>$r['version'],'url'=>$r['url'],'package'=>$r['package'],'requires'=>'6.4','requires_php'=>'7.4');else unset($t->response[$p]);return$t;}
	public static function details($res,$action,$args){if('plugin_information'!==$action||empty($args->slug)||self::SLUG!==$args->slug)return$res;$r=self::release();if(!$r)return$res;return(object)array('name'=>'Kangiroo Workshop Bookings for WooCommerce','slug'=>self::SLUG,'version'=>$r['version'],'author'=>'e-iT','homepage'=>$r['url'],'download_link'=>$r['package'],'requires'=>'6.4','requires_php'=>'7.4','sections'=>array('description'=>'Workshop booking layer for WooCommerce.','changelog'=>$r['body']?:'Security and compatibility maintenance release.'));}
	public static function verify_download($reply,$package,$upgrader,$hook_extra){
		$r=self::release();if(!$r||$package!==$r['package'])return$reply;if(!function_exists('openssl_verify'))return new WP_Error('kwb_crypto_unavailable','Update blocked because signature verification is unavailable.');
		require_once ABSPATH.'wp-admin/includes/file.php';$tmp=download_url($package,300);if(is_wp_error($tmp))return$tmp;$actual=strtolower(hash_file('sha256',$tmp));if(!hash_equals($r['sha256'],$actual)){@unlink($tmp);return new WP_Error('kwb_bad_checksum','Update blocked: SHA-256 verification failed.');}
		$sr=wp_remote_get($r['signature'],array('timeout'=>15,'redirection'=>5,'headers'=>array('User-Agent'=>'Kangiroo-Workshop-Bookings/'.self::VERSION)));if(is_wp_error($sr)||200!==wp_remote_retrieve_response_code($sr)){@unlink($tmp);return new WP_Error('kwb_signature_missing','Update blocked: digital signature unavailable.');}
		$sig=wp_remote_retrieve_body($sr);$contents=file_get_contents($tmp);$ok=is_string($sig)&&''!==$sig&&false!==$contents&&1===openssl_verify($contents,$sig,self::PUBLIC_KEY,OPENSSL_ALGO_SHA256);unset($contents);if(!$ok){@unlink($tmp);return new WP_Error('kwb_bad_signature','Update blocked: publisher signature verification failed.');}return$tmp;
	}
}
