document.addEventListener('DOMContentLoaded', function(){
  const fields = document.querySelectorAll('.tec-color-field');
  fields.forEach(function(f){
    if (typeof jQuery !== 'undefined' && jQuery.fn.wpColorPicker) {
      jQuery(f).wpColorPicker();
    }
  });
});
