const fs=require('fs');
const f=JSON.parse(fs.readFileSync('/tmp/kwb-rsvp.json','utf8'));
(async()=>{
const r=await fetch(f.url);const html=await r.text();
if(r.status!==200||!html.includes('kwb_rsvp_confirm')&&!html.includes('_wpnonce'))throw Error('Attendance confirmation page missing');
if(process.argv[2]==='get')return;
const form=new URLSearchParams();for(const m of html.matchAll(/<input[^>]*name="([^"]+)"[^>]*value="([^"]*)"/g))form.set(m[1],m[2].replaceAll('&amp;','&'));
const target=new URL('/',f.url);const noNonce=new URLSearchParams(form);noNonce.delete('_wpnonce');
let result=await fetch(target,{method:'POST',body:noNonce});if(result.status!==403)throw Error('Missing nonce accepted');
const forged=new URLSearchParams(form);forged.set('token','bad');result=await fetch(target,{method:'POST',body:forged});if(result.status!==403)throw Error('Forged signature accepted');
result=await fetch(target,{method:'POST',body:form});if(result.status!==200)throw Error('Confirmed attendance failed: '+result.status);
console.log('rsvp-post-ok');
})().catch(e=>{console.error(e);process.exitCode=1;});
