(function ($) {
 'use strict';
 $('.kwb-local-date').datepicker({dateFormat:'dd/mm/yy',firstDay:1,prevText:'Προηγούμενος',nextText:'Επόμενος',monthNames:['Ιανουάριος','Φεβρουάριος','Μάρτιος','Απρίλιος','Μάιος','Ιούνιος','Ιούλιος','Αύγουστος','Σεπτέμβριος','Οκτώβριος','Νοέμβριος','Δεκέμβριος'],dayNamesMin:['Κυ','Δε','Τρ','Τε','Πε','Πα','Σα']});
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
