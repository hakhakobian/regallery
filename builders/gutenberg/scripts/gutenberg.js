( function ( blocks, element ) {
  let el = element.createElement;
  const all_images_id = -1;
  blocks.registerBlockType( "reacg/gallery", {
    title: reacg_gutenberg.title,
    description: reacg_gutenberg.description,
    icon: el( 'img', {
      width: 24,
      height: 24,
      src: reacg_gutenberg.icon
    } ),
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
      enableOptions: {
        type: 'int',
        default: 1,
      }
    },

    edit: function ( props ) {
      // Display block preview only on the block hover.
      if ( !props.attributes.hidePreview && !props.isSelected ) {
        return block_preview();
      }

      // Create the gallery block.
      return regallery(props);
    },

    save: function ( props ) {
      return props.attributes.shortcode;
    }
  } );

  /**
   * Display block preview.
   *
   * @returns {*}
   */
  function block_preview() {
    return el( "div", {class: "reacg-block-preview"}, el( "img", {
      src: reacg_gutenberg.plugin_url + "/builders/gutenberg/images/preview.png",
      style: { height: "auto", width: "100%" }
    }))
  }

  /**
   * Gallery block.
   *
   * @param props
   * @returns {*}
   */
  function regallery(props) {
    props.setAttributes( {
      hidePreview: true,
    } );

    const parsed = JSON.parse(reacg_gutenberg.data);
    /* If it's an object, convert its values to array.*/
    const shortcodes = Array.isArray(parsed) ? parsed : Object.values(parsed);
    const shortcode_id = typeof props.attributes.shortcode_id == "undefined" ? 0 : props.attributes.shortcode_id;

    const create_button = props.attributes.shortcode_id ? "" : create_gallery(props);
    const galleries_list = props.attributes.shortcode_id || shortcodes.length > 1 ? shortcodesList(props) : "";
    const separator_cont = create_button && galleries_list ? separator() : "";

    if ( !props.attributes.shortcode_id && document.querySelector(".reacg-gutenberg-controls") ) {
      /* In case of undo after creating gallery.*/
      document.querySelector(".reacg-gutenberg-controls").classList.remove("reacg-hidden");
    }

    const images_cont = el("div", {id: "reacg-gallery-images", class: (shortcode_id && shortcode_id != all_images_id ? "" : "reacg-hidden")});
    const timestamp = Date.now();
    const preview = el('div', {
      'data-options-section': props.attributes.enableOptions,
      'data-gallery-id': shortcode_id,
      'data-plugin-version': reacg_gutenberg.plugin_version,
      'data-gallery-timestamp': timestamp,
      'data-options-timestamp': timestamp,
      class: "reacg-gallery reacg-preview" + (shortcode_id ? "" : " reacg-hidden"),
      id: "reacg-root" + shortcode_id,
    });
    const loader = el('div', {class: "reacg-spinner__wrapper reacg-hidden"}, el('span', {
      class: "spinner is-active",
    }));
    const instruction = props.attributes.shortcode_id ? "" : el('div', {class: "reacg-instruction"}, el( 'img', {
      width: 50,
      height: 50,
      src: reacg_gutenberg.icon
    } ), shortcodes.length > 1 ? el( 'p', {}, "Create new gallery or select the existing one." ) : "");

    const enableOptionsCont = shortcode_id ? el("div", { className: "reacg-enable-options__wrapper" },
        el("label", {}, "Enable options section:"),
          el("label", {},
            el("input", {
              type: "radio",
              name: "enableOptions",
              value: "1",
              checked: props.attributes.enableOptions == 1,
              onChange: (event) => onOptionsChange(event, props, shortcode_id),
            }),
            "Yes"
          ),
          el("label", {},
            el("input", {
              type: "radio",
              name: "enableOptions",
              value: "0",
              checked: props.attributes.enableOptions == 0,
              onChange: (event) => onOptionsChange(event, props, shortcode_id),
            }),
            "No"
          ),
      ) : "";

    return el(
      "div",
      {
        class: "reacg-gutenberg",
        id: `reacg-gutenberg${shortcode_id}`
      },
      loader,
      instruction,
      enableOptionsCont,
      el( "div",
        {
          class: "reacg-gutenberg-controls"
        },
        create_button,
        separator_cont,
        galleries_list
      ),
      images_cont,
      preview,
    );
  }

  function onOptionsChange(event, props, shortcode_id) {
    const checkedValue = event.target.value;
    const baseCont = document.getElementById('reacg-gutenberg' + shortcode_id);
    const galleryCont = baseCont.querySelector(".reacg-gallery");
    galleryCont.setAttribute('data-options-section', checkedValue);
    reload_gallery(shortcode_id);
    if ( shortcode_id != all_images_id ) {
      baseCont.querySelector("#reacg-gallery-images").classList.toggle("reacg-hidden", checkedValue != "1");
    }

    props.setAttributes({
      enableOptions: checkedValue,
    });
  }

  function separator() {
    return el(
      "span", {
        class: "reacg-separator"
      },
      "or",
    );
  }

  function create_gallery(props) {
    return el(
      "span",
      {
        class: "reacg-createButton"
      },
      el('button' , {
        onClick: (event) => showPreview(event, 0, props),
      }, 'Create new gallery'),
    );
  }

  function set_data(baseCont, shortcode_id, props) {
    if ( shortcode_id != all_images_id ) {
      baseCont.querySelector("#reacg-gallery-images").classList.toggle("reacg-hidden", props.attributes.enableOptions != "1");
    }
    const galleryCont = baseCont.querySelector(".reacg-gallery");
    if ( galleryCont ) {
      const timestamp = Date.now();
      galleryCont.classList.remove("reacg-hidden");
      galleryCont.setAttribute('data-options-section', props.attributes.enableOptions);
      galleryCont.setAttribute('data-gallery-id', shortcode_id);
      galleryCont.setAttribute('data-plugin-version', reacg_gutenberg.plugin_version);
      galleryCont.setAttribute('data-gallery-timestamp', timestamp);
      galleryCont.setAttribute('data-options-timestamp', timestamp);
      galleryCont.setAttribute('id', "reacg-root" + shortcode_id);
    }
  }

  function reload_gallery(shortcode_id) {
    const button = document.getElementById("reacg-loadApp");
    if ( button ) {
      button.setAttribute('data-id', 'reacg-root' + shortcode_id);
      button.click();
    }
  }

  function images_cont(baseCont, shortcode_id, props) {
    fetch(reacg_gutenberg.ajax_url + '&action=reacg_get_images&id=' + shortcode_id)
      .then(response => response.json())
      .then(data => {
        const container = baseCont.querySelector("#reacg-gallery-images");
        if ( container ) {
          if ( shortcode_id != all_images_id ) {
            container.classList.remove("reacg-hidden");
            container.innerHTML = data;
            /* Make the image items sortable.*/
            reacg_make_items_sortable(container);
          }
          else {
            container.classList.add("reacg-hidden");
            container.innerHTML = "";
          }
          set_data(baseCont, shortcode_id, props);
          reload_gallery(shortcode_id);
        }
        baseCont.querySelector(".reacg-spinner__wrapper").classList.add("reacg-hidden");
      })
      .catch(error => console.error("Error fetching data:", error));
  }

  function showPreview(event, shortcode_id, props) {
    const baseCont = event.target.closest(".reacg-gutenberg");

    if ( baseCont ) {
      baseCont.querySelector(".reacg-spinner__wrapper").classList.remove("reacg-hidden");
      if ( shortcode_id === 0 ) {
        fetch(reacg_gutenberg.ajax_url + '&action=reacg_save_gallery')
          .then(response => response.json())
          .then(data => {
            shortcode_id = data;
            props.setAttributes({
              shortcode: '[REACG id="' + shortcode_id + '"]',
              shortcode_id: shortcode_id,
            });
            baseCont.querySelector(".reacg-gutenberg-controls").classList.add("reacg-hidden");
            images_cont(baseCont, shortcode_id, props);
          })
          .catch(error => console.error("Error fetching data:", error));
      }
      else {
        images_cont(baseCont, shortcode_id, props);
      }
    }
  }

  function shortcodesList(props) {
    let parsed = JSON.parse(reacg_gutenberg.data);

    /* If it's an object, convert its values to array.*/
    let shortcodes = Array.isArray(parsed) ? parsed : Object.values(parsed);

    // Add shortcodes to the html elements.
    let shortcode_list = [];
    shortcodes.forEach( function ( shortcode_data ) {
      shortcode_list.push(
        el( 'option', {
          value: shortcode_data.id,
          "data-shortcode": shortcode_data.shortcode,
        }, shortcode_data.title )
      );
    } );

    if ( props.attributes.shortcode_id ) {
      // Show images container on page load for the selected gallery.
      const baseCont = document.getElementById(`reacg-gutenberg${props.attributes.shortcode_id}`);
      if ( baseCont && !baseCont.querySelector("#reacg-gallery-images").innerHTML) {
        images_cont(baseCont, props.attributes.shortcode_id, props);
      }
    }

    return el(
      "span",
      {
        class: "reacg-list"
      },
      el('select', {
        value: props.attributes.shortcode_id,
        onChange: (event) => itemSelect(event, props),
        class: 'reacg-gbShortcodesList'
      }, shortcode_list)
    );
  }

  /**
   * Bind an event on the item select.
   *
   * @param event
   * @param props
   */
  function itemSelect( event, props ) {
    let selected = event.target.querySelector( "option:checked" );
    // Get selected item's data.
    props.setAttributes( {
      shortcode: selected.dataset.shortcode,
      shortcode_id: selected.value,
    } );

    showPreview(event, selected.value, props);

    event.preventDefault();
  }
} )(
  window.wp.blocks,
  window.wp.element
);