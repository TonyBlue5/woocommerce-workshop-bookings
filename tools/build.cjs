// Deterministic, dependency-free edition packaging. Run with Node.js 18+.
const fs=require('fs'),path=require('path'),crypto=require('crypto');
const root=path.resolve(__dirname,'..'),out=path.resolve(process.argv[2]||path.join(root,'build'));
fs.mkdirSync(out,{recursive:true});
function crc32(buf){let crc=0xffffffff;for(const b of buf){crc^=b;for(let k=0;k<8;k++)crc=(crc>>>1)^((crc&1)?0xedb88320:0);}return (crc^0xffffffff)>>>0;}
function zip(files){let offset=0;const body=[],dir=[];for(const [name,data] of Object.entries(files).sort(([a],[b])=>a.localeCompare(b))){const n=Buffer.from(name),b=Buffer.isBuffer(data)?data:Buffer.from(data),crc=crc32(b),h=Buffer.alloc(30);h.writeUInt32LE(0x04034b50);h.writeUInt16LE(20,4);h.writeUInt16LE(0x800,6);h.writeUInt16LE(33,12);h.writeUInt32LE(crc,14);h.writeUInt32LE(b.length,18);h.writeUInt32LE(b.length,22);h.writeUInt16LE(n.length,26);body.push(h,n,b);const c=Buffer.alloc(46);c.writeUInt32LE(0x02014b50);c.writeUInt16LE(20,4);c.writeUInt16LE(20,6);c.writeUInt16LE(0x800,8);c.writeUInt16LE(33,14);c.writeUInt32LE(crc,16);c.writeUInt32LE(b.length,20);c.writeUInt32LE(b.length,24);c.writeUInt16LE(n.length,28);c.writeUInt32LE(offset,42);dir.push(c,n);offset+=h.length+n.length+b.length;}const d=Buffer.concat(dir),e=Buffer.alloc(22);e.writeUInt32LE(0x06054b50);e.writeUInt16LE(Object.keys(files).length,8);e.writeUInt16LE(Object.keys(files).length,10);e.writeUInt32LE(d.length,12);e.writeUInt32LE(offset,16);return Buffer.concat([...body,d,e]);}
const read=f=>fs.readFileSync(path.join(root,f),'utf8').replace(/\r\n/g,'\n');
function replace(s,from,to){if(!s.includes(from))throw Error('Missing build marker: '+from);return s.replace(from,to);}
const packages={};
for(const edition of ['lite','pro']){
 const slug=edition==='lite'?'workshop-bookings-for-woocommerce':'workshop-bookings-pro-for-woocommerce';
 const files={};let main=read('woocommerce-workshop-bookings.php');
 if(edition==='lite'){
  main=main.replace('Workshop Bookings Pro for WooCommerce by e-iT','Workshop Bookings Lite for WooCommerce').replace("define('KWB_EDITION','pro')","define('KWB_EDITION','lite')").replace(/^ \* Update URI:.*\n/m,'').replace('workshop-bookings-pro-for-woocommerce',slug);
  main=main.replace(/^require_once.*class-kwb-(updater|commercial|rewards|campaigns|dashboard|messages|admin-calendar)\.php.*\n/gm,'').replace(/^KWB_GitHub_Updater::init\(\);\n/m,'').replace(/^\s*KWB_(Commercial|Messages|Admin_Calendar)::init\(\);\n/gm,'');
 }
 files[slug+'/'+slug+'.php']=main;
 const common=['i18n','settings','booking','rsvp'];
 for(const name of edition==='lite'?common:[...common,'updater','commercial','rewards','campaigns','dashboard','messages','admin-calendar']){
  let content=read('includes/class-kwb-'+name+'.php');
  if(edition==='lite'){
   if(name==='rsvp') content=read('editions/lite-reminders.php');
   if(name==='settings') {
    // Omit Pro controls, while retaining existing settings needed to honor historical attendance data.
    content=content.split('\n').filter(line=>!line.includes('<tr><th>RSVP')&&!line.includes("<tr><th><?php echo esc_html(KWB_I18n::t('rsvp_button_text'))")&&!line.includes("<tr><th><?php echo esc_html(KWB_I18n::t('email_subject'))")&&!line.includes("<tr><th><?php echo esc_html(KWB_I18n::t('email_message'))")).join('\n');
    content=replace(content,'$out=self::defaults();$input=is_array($input)?$input:array();','$out=self::defaults();$input=is_array($input)?$input:array();$legacy=self::all();');
    content=replace(content,'return$out;',"foreach(array('rsvp_enabled','release_on_no','rsvp_cutoff_minutes','rsvp_yes_label','rsvp_no_label','reminder_subject_template','reminder_message_template') as $key) { $out[$key]=$legacy[$key]; } return$out;");
   }
   if(name==='booking') content=content.split('\n').filter(line=>!line.includes("woocommerce_wp_select(array('id'=>self::META_RSVP_OVERRIDE")&&!line.includes('update_post_meta($id,self::META_RSVP_OVERRIDE')).join('\n');
  }
  files[slug+'/includes/class-kwb-'+name+'.php']=content;
 }
 for(const name of fs.readdirSync(path.join(root,'assets'))){if(edition==='lite'&&name.startsWith('kwb-dashboard'))continue;files[slug+'/assets/'+name]=read('assets/'+name);}
 files[slug+'/readme.txt']=read(edition==='lite'?'editions/lite-readme.txt':'readme.txt');files[slug+'/LICENSE']=read('LICENSE');
 if(edition==='lite'&&Object.values(files).some(v=>/KWB_GitHub_Updater|api\.github\.com|class KWB_Rewards|class KWB_Commercial|class KWB_Messages|class KWB_Admin_Calendar/.test(v.toString())))throw Error('Commercial code leaked into Lite');
 const data=zip(files);fs.writeFileSync(path.join(out,slug+'.zip'),data);packages[slug+'.zip']=data;
 fs.writeFileSync(path.join(out,slug+'.zip.sha256'),crypto.createHash('sha256').update(data).digest('hex')+'  '+slug+'.zip\n');
 // Clean staging is unnecessary: write only manifest-owned source files, and ZIP from the in-memory manifest.
 for(const [name,value]of Object.entries(files)){const target=path.join(out,'staging',name);fs.mkdirSync(path.dirname(target),{recursive:true});fs.writeFileSync(target,value);}
 console.log(`${edition}: ${Object.keys(files).length} files, ${data.length} bytes`);
}
const commercial={'plugin/workshop-bookings-pro-for-woocommerce.zip':packages['workshop-bookings-pro-for-woocommerce.zip'],'documentation/index.html':read('marketplace/documentation.html'),'marketplace/listing-copy.html':read('marketplace/listing-copy.html'),'marketplace/assets-and-access.txt':read('marketplace/assets-and-access.txt'),'LICENSE.txt':read('LICENSE'),'CHANGELOG.txt':read('CHANGELOG.md')};
fs.writeFileSync(path.join(out,'workshop-bookings-pro-codecanyon-package.zip'),zip(commercial));
