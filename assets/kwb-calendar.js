(function(){
  function ready(fn){document.readyState!=='loading'?fn():document.addEventListener('DOMContentLoaded',fn);}
  const monthsGR=['Ιανουάριος','Φεβρουάριος','Μάρτιος','Απρίλιος','Μάιος','Ιούνιος','Ιούλιος','Αύγουστος','Σεπτέμβριος','Οκτώβριος','Νοέμβριος','Δεκέμβριος'];
  const days=['Δε','Τρ','Τε','Πε','Πα','Σα','Κυ'];
  ready(function(){
    document.querySelectorAll('.kwb-booking-fields').forEach(init);
  });
  function init(root){
    let data={months:{},occurrences:{}};
    try{data=JSON.parse(root.dataset.calendar||'{}');}catch(e){}
    const type=root.querySelector('#kwb_booking_type');
    const monthInput=root.querySelector('#kwb_month');
    const occInput=root.querySelector('#kwb_occurrence');
    const grid=root.querySelector('.kwb-calendar-grid');
    const title=root.querySelector('.kwb-current-month');
    const label=root.querySelector('#kwb-calendar-label');
    const prev=root.querySelector('.kwb-prev');
    const next=root.querySelector('.kwb-next');
    const monthBtn=root.querySelector('.kwb-select-month');
    const monthWrap=root.querySelector('.kwb-month-select-wrap');
    const summary=root.querySelector('.kwb-selection-summary');
    let keys=[...new Set([...Object.keys(data.months||{}),...Object.keys(data.occurrences||{}).map(d=>d.slice(0,7))])].sort();
    if(!keys.length){grid.innerHTML='<p class="kwb-empty">Δεν υπάρχουν διαθέσιμες ημερομηνίες αυτή τη στιγμή.</p>';return;}
    let idx=0;
    const current=(new Date()).toISOString().slice(0,7);
    const ci=keys.indexOf(current);if(ci>=0)idx=ci;
    function mode(){return type?type.value:'monthly';}
    function draw(){
      const key=keys[idx],p=key.split('-'),y=+p[0],m=+p[1]-1;
      title.textContent=monthsGR[m]+' '+y;
      label.textContent=mode()==='monthly'?'Επιλέξτε μήνα':'Επιλέξτε ημερομηνία';
      monthWrap.style.display=mode()==='monthly'?'':'none';
      prev.disabled=idx===0;next.disabled=idx===keys.length-1;
      grid.innerHTML='';
      days.forEach(d=>{const h=document.createElement('div');h.className='kwb-cal-dayname';h.textContent=d;grid.appendChild(h);});
      const first=new Date(Date.UTC(y,m,1)), last=new Date(Date.UTC(y,m+1,0));
      let offset=(first.getUTCDay()+6)%7;
      for(let i=0;i<offset;i++){const blank=document.createElement('div');blank.className='kwb-cal-blank';grid.appendChild(blank);}
      for(let d=1;d<=last.getUTCDate();d++){
        const date=key+'-'+String(d).padStart(2,'0');
        const os=(data.occurrences&&data.occurrences[date])||[];
        const cell=document.createElement('button');cell.type='button';cell.className='kwb-cal-cell';
        const n=document.createElement('span');n.className='kwb-cal-number';n.textContent=d;cell.appendChild(n);
        if(!os.length){cell.disabled=true;cell.classList.add('is-disabled');}
        else{
          const available=os.filter(o=>+o.remaining>0);
          const seats=document.createElement('span');seats.className='kwb-cal-seats';
          if(!available.length){cell.disabled=true;cell.classList.add('is-full');seats.textContent='Πλήρες';}
          else{
            let min=Math.min.apply(null,available.map(o=>+o.remaining));
            seats.textContent=min+' '+(min===1?'θέση':'θέσεις');
            cell.classList.add('is-available');
            cell.addEventListener('click',()=>selectDate(date,available,cell));
          }
          cell.appendChild(seats);
        }
        grid.appendChild(cell);
      }
      if(mode()==='monthly'){
        const info=data.months&&data.months[key];
        monthBtn.disabled=!info||+info.remaining<1;
        monthBtn.textContent=info?'Επιλογή '+(info.label||title.textContent)+' — '+info.remaining+' θέσεις':'Μη διαθέσιμος μήνας';
      }
    }
    function clearSelected(){root.querySelectorAll('.kwb-cal-cell.is-selected').forEach(x=>x.classList.remove('is-selected'));root.querySelectorAll('.kwb-slot-choices').forEach(x=>x.remove());}
    function selectDate(date,os,cell){
      if(mode()==='monthly')return;
      clearSelected();cell.classList.add('is-selected');monthInput.value='';
      if(os.length===1){occInput.value=os[0].id;summary.textContent='Επιλέχθηκε '+date.split('-').reverse().join('/')+' '+os[0].start+'–'+os[0].end;return;}
      const box=document.createElement('div');box.className='kwb-slot-choices';
      os.forEach(o=>{const b=document.createElement('button');b.type='button';b.textContent=o.start+'–'+o.end+' — '+o.remaining+' θέσεις';b.addEventListener('click',()=>{occInput.value=o.id;box.querySelectorAll('button').forEach(x=>x.classList.remove('selected'));b.classList.add('selected');summary.textContent='Επιλέχθηκε '+date.split('-').reverse().join('/')+' '+o.start+'–'+o.end;});box.appendChild(b);});
      cell.insertAdjacentElement('afterend',box);
    }
    monthBtn.addEventListener('click',()=>{const key=keys[idx],info=data.months&&data.months[key];if(!info||+info.remaining<1)return;monthInput.value=key;occInput.value='';clearSelected();root.querySelectorAll('.kwb-cal-cell.is-available').forEach(x=>x.classList.add('is-month-selected'));summary.textContent='Επιλέχθηκε '+(info.label||key)+' — '+info.count+' συναντήσεις';});
    if(type)type.addEventListener('change',()=>{monthInput.value='';occInput.value='';summary.textContent='';clearSelected();draw();});
    prev.addEventListener('click',()=>{if(idx>0){idx--;monthInput.value='';occInput.value='';summary.textContent='';draw();}});
    next.addEventListener('click',()=>{if(idx<keys.length-1){idx++;monthInput.value='';occInput.value='';summary.textContent='';draw();}});
    draw();
  }
})();