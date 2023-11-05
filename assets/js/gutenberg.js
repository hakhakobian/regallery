( function ( blocks, element ) {
  let el = element.createElement;
  let pluginData = aig;
  blocks.registerBlockType( "aig/gallery", {
    title: pluginData.title,
    icon: el( 'img', {
      width: 20,
      height: 20,
      src: pluginData.icon
    } ),
    category: 'common',
    attributes: {
      shortcode: {
        type: "string",
        value: ""
      },
      shortcode_id: {
        type: "int",
        value: 0
      }
    },

    edit: function ( props ) {
      // Create the shortcodes list and the container for preview.
      let cont = el( "div", {}, shortcodeList(), showPreview());
      console.log(props.attributes.shortcode_id);

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
          class: 'aig-gbShortcodesList'
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

        let cont = event.target.parentElement.querySelector(".aig-gallery");
        cont.setAttribute('data-get-gallery-url', "http://localhost/wordpress/wp-json/aig/v1/gallery/" + selected.value);
        cont.setAttribute('data-get-images-url', "http://localhost/wordpress/wp-json/aig/v1/gallery/" + selected.value + "/images");
        cont.setAttribute('data-options-url', "http://localhost/wordpress/wp-json/aig/v1/options/" + selected.value);
        cont.setAttribute('id', 'root' + selected.value);
        document.getElementById('loadApp').setAttribute('data-id', 'root' + selected.value);
        document.getElementById('loadApp').click();

        event.preventDefault();
      }

      /**
       *  Create the container for preview.
       *
       * @returns {*}
       */
      function showPreview() {
        let shortcode_id = props.attributes.shortcode_id;
        let cont = el( 'div', {
          'data-options-section': 1,
          'data-get-google-fonts': "http://localhost/wordpress/wp-json/aig/v1/google-fonts",
          'data-get-gallery-url': "http://localhost/wordpress/wp-json/aig/v1/gallery/" + shortcode_id,
          'data-get-images-url': "http://localhost/wordpress/wp-json/aig/v1/gallery/" + shortcode_id + "/images",
          'data-options-url': "http://localhost/wordpress/wp-json/aig/v1/options/" + shortcode_id,
          class: "aig-gallery aig-preview",
          id: "root" + shortcode_id,
        } );

        //document.getElementById('loadApp').setAttribute('data-id', 'root' + props.attributes.shortcode_id);
        //document.getElementById('loadApp').click();

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