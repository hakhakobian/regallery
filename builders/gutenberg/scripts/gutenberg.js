(function (blocks, element, blockEditor) {
  let el = element.createElement;
  const {InspectorControls} = blockEditor;
  const all_images_id = -1;
  const parsed = JSON.parse(reacg_gutenberg.data);
  /* If it's an object, convert its values to array.*/
  const shortcodes = Array.isArray(parsed) ? parsed : Object.values(parsed);
  const existGalleries = shortcodes.length > 1;
  blocks.registerBlockType("reacg/gallery", {
    title: reacg_gutenberg.title,
    description: reacg_gutenberg.description,
    icon: el('img', {
      width: 24,
      height: 24,
      src: reacg_gutenberg.icon
    }),
    category: 'common',
    // Disable support for Additional CSS Class(es) in the block sidebar.
    supports: {
      customClassName: false
    },
    example: {},
    attributes: {
      shortcode: {
        type: "string",
        value: ""
      },
      shortcode_id: {
        type: "int",
        value: 0
      },
      hidePreview: {
        type: "boolean",
        value: false
      },
    },
    edit: function (props) {
      // Display block preview only on the block hover.
      if (!props.attributes.hidePreview && !props.isSelected) {
        return block_preview();
      }

      // Auto-open block settings when the block becomes selected.
      if (props.isSelected) {
        wp.data.dispatch('core/edit-post').openGeneralSidebar('edit-post/block');
      }
      const selected = wp.data.select('core/block-editor').getSelectedBlock();
      const selectedBlockShortcodeId = selected?.attributes?.shortcode_id;

      return regallery(props, selectedBlockShortcodeId);
    },
    save: function (props) {
      return props.attributes.shortcode;
    }
  });

  /**
   * Display block preview.
   *
   * @returns {*}
   */
  function block_preview() {
    return el("div", {class: "reacg-block-preview"}, el("img", {
      src: reacg_gutenberg.plugin_url + "/builders/gutenberg/images/preview.png",
      style: {height: "auto", width: "100%"}
    }))
  }

  /**
   * Gallery block.
   *
   * @param props
   * @param selectedBlockShortcodeId
   * @returns {*}
   */
  function regallery(props, selectedBlockShortcodeId) {
    props.setAttributes({
      hidePreview: true,
    });

    const shortcode_id = typeof props.attributes.shortcode_id == "undefined" ? 0 : props.attributes.shortcode_id;

    if (!props.attributes.shortcode_id && document.querySelector(".reacg-setup-controls")) {
      /* In case of undo after creating gallery.*/
      document.querySelector(".reacg-setup-controls").classList.remove("reacg-hidden");
    }

    /* On page load.*/
    if (shortcode_id && props.isSelected) {
      // Show settings for the selected gallery.
      const baseCont = document.getElementById("reacg-gutenberg" + props.clientId);
      if (baseCont) {
        images_cont(baseCont, shortcode_id, props, true, true);
      }
    }

    const loader = el('div', {class: "reacg-spinner__wrapper reacg-hidden"}, el('span', {
      class: "spinner is-active",
    }));

    const setup_wizard = props.attributes.shortcode_id ? "" : el('div', {class: "reacg-setup-wizard"},
      el('img', {
        width: 50,
        height: 50,
        src: reacg_gutenberg.icon
      }),
      existGalleries ? el('p', {}, reacg_gutenberg.setup_wizard_description) : "",
      el("div",
        {
          class: "reacg-setup-controls"
        },
        create_gallery_button(props),
        existGalleries ? galleries_list(props) : ""
      ),
    );

    return el(
      "div",
      {
        class: "reacg-gutenberg",
        id: `reacg-gutenberg${props.clientId}`
      },
      loader,
      setup_wizard,
      gallery_container(shortcode_id, props),
      shortcode_id && props.isSelected ? el(InspectorControls, {},
        el("div",
          {
            class: "reacg-gutenberg-settings"
          },
          galleries_list(props),
          gallery_images_container(selectedBlockShortcodeId),
          gallery_settings_container(),
        )
      ) : "",
    );
  }

  function gallery_container(shortcode_id, props) {
    const timestamp = Date.now();

    return el('div', {
      'data-options-section': 0,
      'data-options-container': "#reacg_settings",
      'data-gallery-id': shortcode_id,
      'data-plugin-version': reacg_gutenberg.plugin_version,
      'data-gallery-timestamp': timestamp,
      'data-options-timestamp': timestamp,
      class: "reacg-wrapper reacg-gallery reacg-preview" + (shortcode_id ? "" : " reacg-hidden"),
      id: "reacg-root" + props.clientId,
    });
  }
  function gallery_settings_container() {
    return el("div", {
        id: "reacg_settings",
        class: "reacg-wrapper",
      }
    );
  }
  function gallery_images_container(shortcode_id) {
    return el("div", {
      id: "reacg-gallery-images",
      class: (shortcode_id != all_images_id ? "" : "reacg-hidden"),
    });
  }

  function set_data(baseCont, shortcode_id, props) {
    const galleryCont = baseCont.querySelector(".reacg-gallery");
    if (galleryCont) {
      const timestamp = Date.now();
      galleryCont.classList.remove("reacg-hidden");
      galleryCont.setAttribute('data-options-section', 1);
      galleryCont.setAttribute('data-options-container', "#reacg_settings");
      galleryCont.setAttribute('data-gallery-id', shortcode_id);
      galleryCont.setAttribute('data-plugin-version', reacg_gutenberg.plugin_version);
      galleryCont.setAttribute('data-gallery-timestamp', timestamp);
      galleryCont.setAttribute('data-options-timestamp', timestamp);
      galleryCont.setAttribute('id', "reacg-root" + props.clientId);
    }
  }

  function reload_gallery(props) {
    const button = document.getElementById("reacg-loadApp");
    if (button) {
      button.setAttribute('data-id', 'reacg-root' + props.clientId);
      button.click();
    }
  }

  function images_cont(baseCont, shortcode_id, props, first_load, selectGallery) {
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
            document.querySelector(".reacg-galleries-list").classList.add("reacg-hidden");
          }

          set_data(baseCont, shortcode_id, props);
          reload_gallery(props);
        }
        baseCont.querySelector(".reacg-spinner__wrapper").classList.add("reacg-hidden");
      })
      .catch(error => console.error("Error fetching data:", error));
  }

  function showPreview(shortcode_id, props) {
    const baseCont = document.querySelector("#reacg-gutenberg" + props.clientId);
    if (baseCont) {
      baseCont.querySelector(".reacg-spinner__wrapper").classList.remove("reacg-hidden");
      if (shortcode_id === 0) {
        fetch(reacg_gutenberg.ajax_url + '&action=reacg_save_gallery')
          .then(response => response.json())
          .then(data => {
            shortcode_id = data;
            props.setAttributes({
              shortcode: '[REACG id="' + shortcode_id + '"]',
              shortcode_id: shortcode_id,
            });
            baseCont.querySelector(".reacg-setup-controls").classList.add("reacg-hidden");
            images_cont(baseCont, shortcode_id, props, false, false);
          })
          .catch(error => console.error("Error fetching data:", error));
      }
      else {
        images_cont(baseCont, shortcode_id, props, false, true);
      }
    }
  }

  function create_gallery_button(props) {
    return  el('button', {
      class: "reacg-create-gallery",
      onClick: (event) => showPreview(0, props),
    }, reacg_gutenberg.create_button);
  }

  function galleries_list(props) {
    // Add shortcodes to the html elements.
    let shortcode_list = [];
    shortcodes.forEach(function (shortcode_data) {
      shortcode_list.push(
        el('option', {
          value: shortcode_data.id,
          "data-shortcode": shortcode_data.shortcode,
        }, shortcode_data.title)
      );
    });
    return el('select', {
        value: props.attributes.shortcode_id,
        onChange: (event) => itemSelect(event, props),
        class: 'reacg-galleries-list'
      }, shortcode_list);
  }

  /**
   * Bind an event on the item select.
   *
   * @param event
   * @param props
   */
  function itemSelect(event, props) {
    let selected = event.target.querySelector("option:checked");
    if (typeof props.attributes.shortcode_id == "undefined") {
      props.setAttributes({
        shortcode_id: 0,
      });
    }
    showPreview(selected.value, props);
    // Get selected item's data.
    props.setAttributes({
      shortcode: selected.dataset.shortcode,
      shortcode_id: selected.value,
    });
    event.preventDefault();
  }
})(
  window.wp.blocks,
  window.wp.element,
  window.wp.blockEditor,
  window.wp.components
);