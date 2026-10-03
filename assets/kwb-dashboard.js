(function ($) {
 'use strict';
 $('.kwb-sort').on('click',function(){
  const th=this.closest('th'),table=th.closest('table'),index=th.cellIndex,ascending=th.getAttribute('aria-sort')!=='ascending';
  const rows=Array.from(table.tBodies[0].rows).filter(row=>row.cells.length===10);
  rows.sort((a,b)=>{const x=a.cells[index].dataset.sort??a.cells[index].textContent,y=b.cells[index].dataset.sort??b.cells[index].textContent;return (ascending?1:-1)*(!isNaN(Number(x))&&!isNaN(Number(y))?Number(x)-Number(y):x.localeCompare(y,undefined,{numeric:true}));});
  table.querySelectorAll('th').forEach(h=>h.setAttribute('aria-sort','none'));th.setAttribute('aria-sort',ascending?'ascending':'descending');
  rows.forEach(row=>table.tBodies[0].appendChild(row));
 });
 $('#kwb-select-image').on('click', function () {
  var frame = wp.media({title: this.textContent, library: {type: 'image'}, multiple: false});
  frame.on('select', function () {
   var image = frame.state().get('selection').first().toJSON();
   $('#kwb-image').val(image.id);
   $('#kwb-image-preview').empty().append($('<img>', {src: image.url, alt: image.alt || ''}));
  });
  frame.open();
 });
 $('#kwb-remove-image').on('click', function () { $('#kwb-image').val('0'); $('#kwb-image-preview').empty(); });
})(jQuery);
