( function ( blocks, element ) {
  let el = element.createElement;
  let pluginData = reacg;
  blocks.registerBlockType( "reacg/gallery", {
    title: pluginData.title,
    description: pluginData.description,
    icon: el( 'img', {
      width: 24,
      height: 24,
      src: pluginData.icon
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
      }
    },

    edit: function ( props ) {
      // Display block preview only on the block hover.
      if ( !props.attributes.hidePreview && !props.isSelected ) {
        return el( "div", {class: "reacg-block-preview"}, el( "img", {
          src: pluginData.plugin_url + "/builders/gutenberg/images/preview.png",
          style: { height: "auto", width: "100%" }
        }));
      }

      // Create the shortcodes list and the container for preview.
      let cont = el( "div", {}, shortcodeList(), showPreview());

      return cont;

      /**
       * Create the shortcodes list html element.
       *
       * @returns {*}
       */
      function shortcodeList() {
        let shortcodes = JSON.parse( pluginData.data );

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

        // Return the complete html list of items.
        return el( 'select', {
          value: props.attributes.shortcode_id,
          onChange: itemSelect,
          class: 'reacg-gbShortcodesList'
        }, shortcode_list );
      }

      /**
       * Bind an event on the item select.
       *
       * @param event
       */
      function itemSelect( event ) {
        let selected = event.target.querySelector( "option:checked" );
        // Get selected item's data.
        props.setAttributes( {
          shortcode: selected.dataset.shortcode,
          shortcode_id: selected.value,
        } );

        let cont = event.target.parentElement.querySelector(".reacg-gallery");
        if ( cont ) {
          cont.setAttribute('data-gallery-id', selected.value);
          cont.setAttribute('id', 'reacg-root' + selected.value);
        }
        let button = document.getElementById("reacg-loadApp");
        if ( button ) {
          button.setAttribute('data-id', 'reacg-root' + selected.value);
          button.click();
        }

        event.preventDefault();
      }

      /**
       *  Create the container for preview.
       *
       * @returns {*}
       */
      function showPreview() {
        props.setAttributes( {
          hidePreview: true,
        } );
        let shortcode_id = typeof props.attributes.shortcode_id == "undefined" ? 0 : props.attributes.shortcode_id;
        let cont = el( 'div', {
          'data-options-section': 1,
          'data-gallery-id': shortcode_id,
          class: "reacg-gallery reacg-preview",
          id: "reacg-root" + shortcode_id,
        } );
        if ( document.getElementsByClassName("reacg-gallery")
          && document.getElementsByClassName("reacg-gallery").length > 0
          && document.getElementsByClassName("reacg-gallery")[0].innerHTML === '' ) {
          let button = document.getElementById("reacg-loadApp");
          if ( button ) {
            button.setAttribute('data-id', 'reacg-root' + shortcode_id);
            button.click();
          }
        }

        return cont;
      }
    },

    save: function ( props ) {
      return props.attributes.shortcode;
    }
  } );
} )(
  window.wp.blocks,
  window.wp.element
);