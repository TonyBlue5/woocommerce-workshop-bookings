(function ($) {
 'use strict';
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
