//jQuery(document).ready(function () {
//  debugger;
//
//  jQuery(".wpb_REACG").each(function () {
//    var $el = jQuery(this);
//    var modelId = $el.attr('data-model-id');
//
//    if (modelId && vc && vc.shortcodes) {
//      var shortcode = vc.shortcodes.get(modelId);
//      if (shortcode) {
//        var params = shortcode.get('params');
//        var galleryId = params.id || 'Not selected';
//        var enable_options = params.enable_options || false;
//        $el.find(".reacg-gallery").attr("id", "reacg-root" + galleryId);
//        $el.find(".reacg-gallery").data("options-section", enable_options);
//        var reacgLoadApp = document.getElementById("reacg-loadApp");
//        if (reacgLoadApp) {
//          reacgLoadApp.setAttribute("data-id", "reacg-root" + galleryId);
//          reacgLoadApp.click();
//        }
//      }
//    }
//  });
//});
//jQuery(document).on('vc_backend_ready', function() {
//  debugger;
//  vc.shortcodes.all.each(function(model) {
//    var $el = jQuery('[data-model-id="' + model.id + '"]');
//    if ($el.length) {
//      updateGalleryPreview($el);
//    }
//  });
//});

// When new shortcode is added dynamically
//jQuery(document).on('change', '[data-model-id]', function() {
//  loaded = 0;
//  updateGalleryPreview(jQuery(this));
//});
let loaded = 0;
const interval = setInterval(() => {
  if (loaded === 1) return clearInterval(interval);
  updateAllGalleryPreview();
}, 1000);

function updateGalleryPreview($el) {
  var modelId = $el.attr('data-model-id');

  if (modelId && vc && vc.shortcodes) {
    var shortcode = vc.shortcodes.get(modelId);
    if (shortcode) {
      debugger;
      var params = shortcode.get('params');
      var galleryId = params.id || 'Not selected';
      var enable_options = params.enable_options == "true" ? 1 : 0;
      $el.find(".reacg-gallery").attr("id", "reacg-root" + galleryId);
      $el.find(".reacg-gallery").attr("data-gallery-id", galleryId);
      $el.find(".reacg-gallery").attr("data-options-section", enable_options);
      var reacgLoadApp = document.getElementById("reacg-loadApp");
      if (reacgLoadApp) {
        loaded = 1;
        reacgLoadApp.setAttribute("data-id", "reacg-root" + galleryId);
        reacgLoadApp.click();
      }
    }
  }
}
// Optional: Trigger when select changes
jQuery(document).on('click', '.vc_ui-button.vc_ui-button-action[data-vc-ui-element="button-save"]', function () {
  loaded = 0;
  updateAllGalleryPreview();
});

function updateAllGalleryPreview() {
  jQuery(".wpb_REACG").each(function () {
    updateGalleryPreview(jQuery(this));
  });
}