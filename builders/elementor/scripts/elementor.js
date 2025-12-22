const all_images_id = -1;
const reacg_list_element = ".elementor-control-post_id";
function images_cont(baseCont, shortcode_id, widget_id, first_load, selectGallery) {
  fetch(reacg_gutenberg.ajax_url + '&action=reacg_get_images&id=' + shortcode_id)
    .then(response => response.json())
    .then(data => {
      const container = document.querySelector("#reacg-gallery-images");
      if (container && (!first_load || !container.innerHTML)) {
        if (shortcode_id != all_images_id) {
          container.classList.remove("reacg-hidden");
          container.innerHTML = data;
          /* Make the image items sortable.*/
          reacg_make_items_sortable(container);
        }
        else {
          container.classList.add("reacg-hidden");
          container.innerHTML = "";
        }
        if (!selectGallery) {
          document.querySelector(reacg_list_element).classList.add("reacg-hidden");
        }

        reacg_reload_gallery(widget_id, {gallery_id: shortcode_id});
      }
      baseCont.querySelector(".reacg-spinner__wrapper").classList.add("reacg-hidden");
    })
    .catch(error => console.error("Error fetching data:", error));
}

function showPreview(shortcode_id, widget_id = null) {
  const baseCont = document.querySelector("#elementor-controls");
  if (baseCont) {
    baseCont.querySelector(".reacg-spinner__wrapper").classList.remove("reacg-hidden");
    if (shortcode_id === 0) {
      fetch(reacg_gutenberg.ajax_url + '&action=reacg_save_gallery')
        .then(response => response.json())
        .then(data => {
          shortcode_id = data;
          baseCont.querySelector(".reacg-setup-wizard").classList.add("reacg-hidden");
          images_cont(baseCont, shortcode_id, widget_id, false, false);
        })
        .catch(error => console.error("Error fetching data:", error));
    }
    else {
      images_cont(baseCont, shortcode_id, widget_id, false, true);
    }
  }
}

function reacg_reload_gallery(id, data = {}, initial = false, ) {
  let innerDoc;
  const iframe = document.getElementById('elementor-preview-iframe');
  if ( iframe ) {
    innerDoc = iframe.contentDocument || iframe.contentWindow.document;
  }
  else {
    innerDoc = document;
  }
  const gallery = innerDoc.getElementById("reacg-root" + id);
  if ( gallery ) {
    gallery.setAttribute('data-options-section', initial ? 0 : 1);
    const fake_container = document.querySelector(".reacg-fake-container");
    if (fake_container) {
      fake_container.setAttribute('data-gallery-id', data.gallery_id);
    }
    if ( data.gallery_id ) {
      gallery.setAttribute('data-gallery-id', data.gallery_id);
    }
  }
  const button = innerDoc.getElementById("reacg-loadApp");
  if ( button ) {
    button.setAttribute('data-id', 'reacg-root' + id);
    button.click();
  }
}

(function ($) {
  'use strict';
  // Fired when a widget is selected and its panel is opened.
  elementor.channels.editor.on('section:activated', function (sectionName, editor) {
    if (!editor || !editor.model) {
      return;
    }

    const widgetName = editor.model.get('widgetType');
    if (widgetName !== 'reacg-elementor') {
      return;
    }
    const widgetId = editor.model.get('id');

    if (typeof reacg_reload_gallery === 'function') {
      reacg_reload_gallery(widgetId);
    }

    waitForControl('.reacg-create-gallery', function (btn) {
      btn.addEventListener('click', function () {
        window.showPreview(0, widgetId);
      });
    });
    //waitForRawHtmlChange('#reacg-gallery-images', function (el, mutations) {
    //  console.log('Settings RAW_HTML changed');
    //  reacg_reload_gallery(widgetId);
    //});

    const settingsModel = editor.model.get('settings');

    if (!settingsModel) return;

    // Listen to galleries list change.
    settingsModel.on('change:post_id', function (settings) {
      const shortcode_id = settings.get('post_id');
      showPreview(shortcode_id, widgetId);
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
  function waitForRawHtmlChange(selector, callback) {
    const target = document.querySelector(selector);

    if (!target) {
      // Wait until it exists first
      waitForControl(selector, () => {
        waitForRawHtmlChange(selector, callback);
      });
      return;
    }

    const observer = new MutationObserver((mutations) => {
      callback(target, mutations);
    });

    observer.observe(target, {
      childList: true,
      subtree: true,
      characterData: true,
    });
  }

})(jQuery);