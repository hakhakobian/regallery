const all_images_id = -1;
function images_cont(baseCont, shortcode_id, widget_id, load_only_settings = false) {
  fetch(reacg.ajax_url + '&action=reacg_get_images&id=' + shortcode_id)
    .then(response => response.json())
    .then(data => {
      const container = baseCont.find("#reacg-gallery-images");
      if (container) {
        if (shortcode_id != all_images_id) {
          container.removeClass("reacg-hidden");
          container.html(data);
          /* Make the image items sortable.*/
          reacg_make_items_sortable(container);
        }
        else {
          container.addClass("reacg-hidden");
          container.html('');
        }

        reacg_reload_gallery(widget_id, shortcode_id, load_only_settings);
      }
      baseCont.find(".reacg-spinner__wrapper").addClass("reacg-hidden");
      baseCont.find(".reacg-elementor-options").removeClass("reacg-hidden");
    })
    .catch(error => console.error("Error fetching data:", error));
}

function showPreview(shortcode_id, widget_id = null, load_only_settings = false) {
  const baseCont = jQuery("#elementor-controls");
  if (baseCont) {
    baseCont.find(".reacg-elementor-options").addClass("reacg-hidden");
    if (shortcode_id === 0) {
      baseCont.find(".elementor-control-setup_wizard_html .reacg-spinner__wrapper").removeClass("reacg-hidden");
      baseCont.find(".reacg-setup-wizard").addClass("reacg-hidden");
      baseCont.find(".elementor-control-post_id").addClass("reacg-hidden");

      fetch(reacg.ajax_url + '&action=reacg_save_gallery')
        .then(response => response.json())
        .then(data => {
          shortcode_id = data;
          images_cont(baseCont, shortcode_id, widget_id, load_only_settings);
        })
        .catch(error => console.error("Error fetching data:", error));
    }
    else {
      baseCont.find(".elementor-control-gallery_options_html .reacg-spinner__wrapper").removeClass("reacg-hidden");
      images_cont(baseCont, shortcode_id, widget_id, load_only_settings);
    }
  }
}

function reacg_reload_gallery(id, shortcode_id, load_only_settings = false, initial_load = false) {
  let previewDocument;
  let previewWindow;
  const iframe = document.getElementById('elementor-preview-iframe');
  if ( iframe ) {
    previewDocument = iframe.contentDocument || iframe.contentWindow.document;
    previewWindow = iframe.contentWindow;
  }
  else {
    previewDocument = document;
    previewWindow = window;
  }

  const gallery = previewDocument.getElementById("reacg-root" + id);
  if ( gallery ) {
    gallery.setAttribute('data-options-section', initial_load ? 0 : 1);
    if ( shortcode_id ) {
      const fake_container = document.querySelector(".reacg-fake-container");
      if (fake_container) {
        fake_container.setAttribute('data-gallery-id', shortcode_id);
      }
      gallery.setAttribute('data-gallery-id', shortcode_id);
    }
  }

  if (load_only_settings) {
    previewWindow.postMessage(
      {type: "reacg-root" + id + "-show-controls", show: true},
      "*"
    );
    return;
  }

  const button = previewDocument.getElementById("reacg-loadApp");
  if ( button ) {
    button.setAttribute('data-id', 'reacg-root' + id);
    button.click();
  }
}

(function ($) {
  // Fired when a widget is selected and its panel is opened.
  elementor.channels.editor.on('section:activated', function (sectionName, editor) {
      if (!editor || !editor.model) {
        return;
      }
      const widgetName = editor.model.get('widgetType');
      if (widgetName !== 'reacg-elementor') {
        return;
      }
      const settingsModel = editor.model.get('settings');
      if (!settingsModel) return;
      const widgetId = editor.model.get('id');
      const shortcode_id = settingsModel.get('post_id');
      //reacg_reload_gallery(widgetId, shortcode_id);
      waitForControl('.reacg-create-gallery', function (btn) {
        btn.addEventListener('click', function () {
          window.showPreview(0, widgetId);
        });
        if (shortcode_id == 0) {
          const baseCont = jQuery("#elementor-controls");
          if (baseCont) {
            baseCont.find(".reacg-setup-wizard").removeClass("reacg-hidden");
            baseCont.find(".elementor-control-gallery_options_html .reacg-elementor-options").addClass("reacg-hidden");
          }
        }
      });
      waitForControl('#reacg-gallery-images', function (btn) {
        if (shortcode_id != 0) {
          showPreview(shortcode_id, widgetId, true);
        }
      });
      // Listen to galleries list change.
      settingsModel.on('change:post_id', function (settings) {
        showPreview(settings.get('post_id'), widgetId, true);
      });
  });
  function waitForControl(selector, callback) {
    const observer = new MutationObserver(() => {
      const el = document.querySelector(selector);
      if (el) {
        observer.disconnect();
        callback(el);
      }
    });

    observer.observe(document.body, {
      childList: true,
      subtree: true,
    });
  }

})(jQuery);