(function(){
  function ready(fn){document.readyState!=='loading'?fn():document.addEventListener('DOMContentLoaded',fn);}
  ready(function(){
    const panel=document.getElementById('kwb_booking_product_data');if(!panel)return;
    const sched=document.getElementById('kwb-schedule-rows'),black=document.getElementById('kwb-blackout-rows');
    const schedHidden=document.getElementById('_kwb_weekly_schedule'),blackHidden=document.getElementById('_kwb_blackouts');
    const i18n=window.KWB_ADMIN_I18N||{};const dayOptions=i18n.days||['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
    function scheduleRow(){
      const r=document.createElement('div');r.className='kwb-schedule-row';
      const s=document.createElement('select');s.className='kwb-day';dayOptions.forEach((x,i)=>{const o=document.createElement('option');o.value=i+1;o.textContent=x;s.appendChild(o);});
      r.appendChild(s);
      [['time','kwb-start','09:00'],['time','kwb-end','10:00'],['number','kwb-capacity','10']].forEach(a=>{const i=document.createElement('input');i.type=a[0];i.className=a[1];i.value=a[2];if(a[0]==='number'){i.min=1;i.max=10000;i.placeholder=i18n.capacity||'Seats';}r.appendChild(i);});
      const b=document.createElement('button');b.type='button';b.className='button-link-delete kwb-remove-row';b.textContent=i18n.remove||'Remove';r.appendChild(b);return r;
    }
    function blackoutRow(){const r=document.createElement('div');r.className='kwb-blackout-row';const i=document.createElement('input');i.type='text';i.className='kwb-date-display';i.placeholder='DD/MM/YYYY';const wrap=document.createElement('span');wrap.className='kwb-date-control';wrap.dataset.month='0';wrap.appendChild(i);const hidden=document.createElement('input');hidden.type='hidden';hidden.className='kwb-date-iso';wrap.appendChild(hidden);r.appendChild(wrap);const b=document.createElement('button');b.type='button';b.className='button-link-delete kwb-remove-row';b.textContent=i18n.remove||'Remove';r.appendChild(b);return r;}
    function sync(){
      const lines=[];sched.querySelectorAll('.kwb-schedule-row').forEach(r=>{const d=r.querySelector('.kwb-day').value,s=r.querySelector('.kwb-start').value,e=r.querySelector('.kwb-end').value,c=r.querySelector('.kwb-capacity').value;if(d&&s&&e&&c)lines.push([d,s,e,c].join('|'));});schedHidden.value=lines.join('\n');
      const dates=[];black.querySelectorAll('.kwb-date-iso').forEach(i=>{if(i.value)dates.push(i.value);});blackHidden.value=dates.join('\n');
    }
    document.getElementById('kwb-add-schedule').addEventListener('click',()=>{sched.appendChild(scheduleRow());sync();});
    document.getElementById('kwb-add-blackout').addEventListener('click',()=>{black.appendChild(blackoutRow());window.KWBDateControls.init(black);sync();});
    panel.addEventListener('click',e=>{if(e.target.classList.contains('kwb-remove-row')){e.preventDefault();e.target.parentElement.remove();sync();}});
    panel.addEventListener('change',sync);panel.addEventListener('input',sync);
    const form=document.getElementById('post');if(form)form.addEventListener('submit',sync);
    sync();
  });
})();