(function ($) {
 'use strict';
 const config=window.KWB_CONTROLS||{};
 function parse(value,month) {
  const m=(month?/^(\d{2})\/(\d{4})$/:/^(\d{2})\/(\d{2})\/(\d{4})$/).exec(value);
  if(!m)return '';
  const day=month?1:+m[1],mon=+(month?m[1]:m[2]),year=+(month?m[2]:m[3]);
  const date=new Date(0);date.setFullYear(year,mon-1,day);
  return year>=1000&&date.getFullYear()===year&&date.getMonth()===mon-1&&date.getDate()===day?(month?m[2]+'-'+m[1]:m[3]+'-'+m[2]+'-'+m[1]):'';
 }
 function sync(input) {
  const wrap=input.closest('.kwb-date-control'),month=wrap.dataset.month==='1',iso=parse(input.value,month);
  input.setCustomValidity(input.value&&!iso?(config.invalid||'Enter DD/MM/YYYY'):'');
  wrap.querySelector('.kwb-date-iso').value=iso;
 }
 function init(root) {
  $(root).find('.kwb-date-display').each(function(){
   if(this.dataset.initialized)return;this.dataset.initialized='1';
   const input=this;sync(input);
   $(input).on('input change',()=>sync(input));
   if(input.closest('.kwb-date-control').dataset.month==='1')return;
   const options={dateFormat:'dd/mm/yy',firstDay:1,onSelect:function(){sync(input);$(input).trigger('change');}};
   if(config.greek)Object.assign(options,{monthNames:config.months,dayNamesMin:['Κυ','Δε','Τρ','Τε','Πε','Πα','Σα'],prevText:'Προηγούμενος',nextText:'Επόμενος'});
   $(input).datepicker(options);
  });
 }
 window.KWBDateControls={init: init,parse:parse};
 $(function(){init(document);});
})(jQuery);
