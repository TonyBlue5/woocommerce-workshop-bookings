(function(){
  function ready(fn){document.readyState!=='loading'?fn():document.addEventListener('DOMContentLoaded',fn);}
  const i18n=window.KWB_CAL_I18N||{};
  const months=i18n.months||['January','February','March','April','May','June','July','August','September','October','November','December'];
  const days=i18n.days||['Mo','Tu','We','Th','Fr','Sa','Su'];

  ready(function(){
    document.querySelectorAll('.kwb-booking-fields').forEach(init);
  });

  function init(root){
    let data={months:{},occurrences:{},maxMonths:1};
    try{data=JSON.parse(root.dataset.calendar||'{}');}catch(e){}

    const type=root.querySelector('#kwb_booking_type');
    const monthInput=root.querySelector('#kwb_month');
    const monthsInput=root.querySelector('#kwb_months');
    const occInput=root.querySelector('#kwb_occurrence');
    const grid=root.querySelector('.kwb-calendar-grid');
    const title=root.querySelector('.kwb-current-month');
    const label=root.querySelector('#kwb-calendar-label');
    const prev=root.querySelector('.kwb-prev');
    const next=root.querySelector('.kwb-next');
    const monthBtn=root.querySelector('.kwb-select-month');
    const monthWrap=root.querySelector('.kwb-month-select-wrap');
    const summary=root.querySelector('.kwb-selection-summary');
    const monthLimit=root.querySelector('.kwb-month-limit');
    const maxMonths=Math.max(1,parseInt(data.maxMonths||1,10));
    const selectedMonths=new Set();

    let keys=[...new Set([
      ...Object.keys(data.months||{}),
      ...Object.keys(data.occurrences||{}).map(d=>d.slice(0,7))
    ])].sort();

    if(!keys.length){
      grid.innerHTML='<p class="kwb-empty">'+(i18n.no_dates||'There are no available dates at the moment.')+'</p>';
      return;
    }

    let idx=0;
    const current=(new Date()).toISOString().slice(0,7);
    const ci=keys.indexOf(current);
    if(ci>=0)idx=ci;

    function mode(){return type?type.value:'monthly';}
    function monthName(key){
      const p=key.split('-'),m=+p[1]-1;
      return months[m]+' '+p[0];
    }
    function formatDate(date){return date.split('-').reverse().join('/');}
    function monthWord(n){return n===1?(i18n.month_singular||'month'):(i18n.month_plural||'months');}
    function participationWord(n){return n===1?(i18n.participation_count_singular||'participation'):(i18n.participation_count_plural||'participations');}
    function setMessage(text,warning){
      summary.textContent=text||'';
      summary.classList.toggle('is-warning',!!warning);
    }
    function updateHiddenMonths(){
      const list=[...selectedMonths].sort();
      if(monthsInput)monthsInput.value=list.join(',');
      if(monthInput)monthInput.value=list[0]||'';
    }
    function totalParticipations(){
      return [...selectedMonths].reduce((total,key)=>{
        const info=data.months&&data.months[key];
        return total+(info?parseInt(info.count||0,10):0);
      },0);
    }
    function updateMonthSummary(){
      const list=[...selectedMonths].sort();
      if(!list.length){setMessage('',false);return;}
      const labels=list.map(k=>(data.months[k]&&data.months[k].label)||monthName(k));
      const total=totalParticipations();
      let template;
      if(list.length===1){
        template=i18n.selected_month||'Selected {{month}} with a total of {{count}} {{participation_word}} per participant.';
        setMessage(template.replace('{{month}}',labels[0]).replace('{{count}}',total).replace('{{participation_word}}',participationWord(total)),false);
      }else{
        template=i18n.selected_months||'Selected {{months_count}} months: {{months}} — {{count}} {{participation_word}} per participant in total.';
        setMessage(template.replace('{{months_count}}',list.length).replace('{{months}}',labels.join(', ')).replace('{{count}}',total).replace('{{participation_word}}',participationWord(total)),false);
      }
    }
    function updateMonthLimit(){
      if(!monthLimit)return;
      const template=i18n.max_months_hint||'You can select up to {{max}} {{month_word}}.';
      monthLimit.textContent=template.replace('{{max}}',maxMonths).replace('{{month_word}}',monthWord(maxMonths));
    }

    function draw(){
      const key=keys[idx],p=key.split('-'),y=+p[0],m=+p[1]-1;
      root.classList.toggle('is-monthly-mode',mode()==='monthly');
      title.textContent=months[m]+' '+y;
      label.textContent=mode()==='monthly'?(i18n.choose_month||'Choose month'):(i18n.choose_date||'Choose date');
      monthWrap.style.display=mode()==='monthly'?'':'none';
      if(monthLimit)monthLimit.style.display=mode()==='monthly'?'':'none';
      prev.disabled=idx===0;
      next.disabled=idx===keys.length-1;
      grid.innerHTML='';

      days.forEach(d=>{
        const h=document.createElement('div');
        h.className='kwb-cal-dayname';
        h.textContent=d;
        grid.appendChild(h);
      });

      const first=new Date(Date.UTC(y,m,1));
      const last=new Date(Date.UTC(y,m+1,0));
      const offset=(first.getUTCDay()+6)%7;

      for(let i=0;i<offset;i++){
        const blank=document.createElement('div');
        blank.className='kwb-cal-blank';
        grid.appendChild(blank);
      }

      for(let d=1;d<=last.getUTCDate();d++){
        const date=key+'-'+String(d).padStart(2,'0');
        const os=(data.occurrences&&data.occurrences[date])||[];
        const cell=document.createElement('button');
        cell.type='button';
        cell.className='kwb-cal-cell';
        cell.setAttribute('aria-label',formatDate(date));

        const n=document.createElement('span');
        n.className='kwb-cal-number';
        n.textContent=d;
        cell.appendChild(n);

        if(!os.length){
          cell.disabled=true;
          cell.classList.add('is-disabled');
        }else{
          const available=os.filter(o=>+o.remaining>0);
          const times=document.createElement('span');
          times.className='kwb-cal-time';
          times.textContent=os.map(o=>o.start+(o.end?'–'+o.end:'')).join(' / ');
          cell.appendChild(times);

          const seats=document.createElement('span');
          seats.className='kwb-cal-seats';

          if(!available.length){
            cell.disabled=true;
            cell.classList.add('is-full');
            seats.textContent=i18n.full||'Full';
          }else{
            const min=Math.min.apply(null,available.map(o=>+o.remaining));
            const shortSeats=min+' '+(min===1?(i18n.place||'place'):(i18n.places||'places'));
            seats.textContent=shortSeats;
            cell.classList.add('is-available');
            if(mode()==='monthly'&&selectedMonths.has(key))cell.classList.add('is-month-selected');
            cell.setAttribute('aria-label',formatDate(date)+', '+times.textContent+', '+min+' '+(min===1?(i18n.available_place||'available place'):(i18n.available_places||'available places')));
            cell.addEventListener('click',()=>selectDate(date,available,cell));
          }
          cell.appendChild(seats);
        }
        grid.appendChild(cell);
      }

      if(mode()==='monthly'){
        const info=data.months&&data.months[key];
        const selected=selectedMonths.has(key);
        monthBtn.disabled=!info||+info.remaining<1;
        monthBtn.classList.toggle('is-selected',selected);
        if(info){
          monthBtn.textContent=(selected?(i18n.remove_month||'Remove'):(i18n.choose||'Choose'))+' '+(info.label||monthName(key));
        }else{
          monthBtn.textContent=i18n.unavailable_month||'Month unavailable';
        }
      }
    }

    function clearDateSelection(){
      root.querySelectorAll('.kwb-cal-cell.is-selected').forEach(x=>x.classList.remove('is-selected'));
      root.querySelectorAll('.kwb-slot-choices').forEach(x=>x.remove());
    }

    function selectDate(date,os,cell){
      if(mode()==='monthly'){
        setMessage(i18n.monthly_date_blocked||'You cannot select individual dates with monthly participation. Please use the button below to select the month you want to book.',true);
        return;
      }

      clearDateSelection();
      cell.classList.add('is-selected');
      if(monthInput)monthInput.value='';
      if(monthsInput)monthsInput.value='';

      if(os.length===1){
        occInput.value=os[0].id;
        setMessage((i18n.selected_date||'Selected date {{date}}, {{time}}.').replace('{{date}}',formatDate(date)).replace('{{time}}',os[0].start+'–'+os[0].end),false);
        return;
      }

      const box=document.createElement('div');
      box.className='kwb-slot-choices';
      os.forEach(o=>{
        const b=document.createElement('button');
        b.type='button';
        b.textContent=o.start+'–'+o.end+' — '+o.remaining+' '+(+o.remaining===1?(i18n.place||'place'):(i18n.places||'places'));
        b.addEventListener('click',()=>{
          occInput.value=o.id;
          box.querySelectorAll('button').forEach(x=>x.classList.remove('selected'));
          b.classList.add('selected');
          setMessage((i18n.selected_date||'Selected date {{date}}, {{time}}.').replace('{{date}}',formatDate(date)).replace('{{time}}',o.start+'–'+o.end),false);
        });
        box.appendChild(b);
      });
      cell.insertAdjacentElement('afterend',box);
    }

    monthBtn.addEventListener('click',()=>{
      const key=keys[idx],info=data.months&&data.months[key];
      if(!info||+info.remaining<1)return;

      if(selectedMonths.has(key)){
        selectedMonths.delete(key);
      }else{
        if(maxMonths===1){
          selectedMonths.clear();
        }else if(selectedMonths.size>=maxMonths){
          const template=i18n.max_months_reached||'You can select up to {{max}} {{month_word}} in this booking.';
          setMessage(template.replace('{{max}}',maxMonths).replace('{{month_word}}',monthWord(maxMonths)),true);
          return;
        }
        selectedMonths.add(key);
      }

      occInput.value='';
      updateHiddenMonths();
      updateMonthSummary();
      draw();
    });

    if(type)type.addEventListener('change',()=>{
      if(monthInput)monthInput.value='';
      if(monthsInput)monthsInput.value='';
      occInput.value='';
      selectedMonths.clear();
      setMessage('',false);
      clearDateSelection();
      draw();
    });

    prev.addEventListener('click',()=>{
      if(idx>0){idx--;clearDateSelection();draw();}
    });
    next.addEventListener('click',()=>{
      if(idx<keys.length-1){idx++;clearDateSelection();draw();}
    });

    updateMonthLimit();
    draw();
  }
})();