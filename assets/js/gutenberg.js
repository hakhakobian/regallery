/**
 * 10Web plugins Gutenberg integration
 * version 2.0.6
 */
( function ( blocks, element ) {
  registerPluginBlock(blocks, element, 'aig/gallery', aig);
  function registerPluginBlock( blocks, element, pluginId, pluginData ) {
    var el = element.createElement;

    blocks.registerBlockType( pluginId, {
      title: pluginData.title,
      icon: el( 'img', {
        width: 20,
        height: 20,
        src: pluginData.iconSvgUrl
      } ),
      category: 'common',
      attributes: {
        shortcode: {
          type: 'string'
        },
        popupOpened: {
          type: 'boolean',
          value: true
        },
        notInitial: {
          type: 'boolean'
        },
        shortcode_id: {
          type: 'string'
        }
      },

      edit: function ( props ) {
        if ( !props.attributes.notInitial ) {
          props.setAttributes( {
            notInitial: true,
            popupOpened: true
          } );

          return el( 'p' );
        }

        if ( props.attributes.popupOpened ) {
          return showShortcodeList( props.attributes.shortcode );
        }
console.log(props.attributes.shortcode);
        if ( props.attributes.shortcode ) {
          return showShortcode();
        }
        else {
          return showShortcodePlaceholder();
        }
        function showShortcodeList( shortcode ) {
          props.setAttributes( { popupOpened: true } );
          var children = [];
          var shortcodeList = JSON.parse( pluginData.data );
          children.push( el( 'option', { value: '', dataId: 0 }, '-Select-' ) );
          shortcodeList.forEach( function ( optionItem ) {
            children.push(
              el( 'option', { value: optionItem.shortcode, dataId: optionItem.id }, optionItem.title )
            );
          } );

          return el( 'form', { onSubmit: chooseFromList }, el( 'div', {}, pluginData.titleSelect ), el( 'select', {
            value: shortcode,
            onChange: chooseFromList,
            class: 'tw-gb-select'
          }, children ) );
        }

        function showShortcodePlaceholder() {
          props.setAttributes( { popupOpened: false } );
          return el( 'p', {
            style: {
              'cursor': "pointer"
            },

            onClick: function () {
              props.setAttributes( { popupOpened: true } );
            }.bind( this )
          }, tw_obj_translate.nothing_selected );
        }

        function showShortcode() {
          return el( 'div', {
            'data-options-section': 1,
            'data-get-google-fonts': "http://localhost/wordpress/wp-json/aig/v1/google-fonts",
            'data-get-images-url': "http://localhost/wordpress/wp-json/aig/v1/gallery/6/images",
            'data-options-url': "http://localhost/wordpress/wp-json/aig/v1/options/6",
            class: "aig-gallery aig-preview",
            id: "root",
          } );
        }

        function chooseFromList( event, shortcode_id ) {
          var selected = event.target.querySelector( 'option:checked' );
          props.setAttributes( { shortcode: selected.value, shortcode_id: selected.dataId, popupOpened: false } );
          event.preventDefault();
        }
      },

      save: function ( props ) {
        return props.attributes.shortcode;
      }
    } );
  }

  function generateUniqueCbName( pluginId ) {
    return 'wdg_cb_' + pluginId;
  }
} )(
  window.wp.blocks,
  window.wp.element
);
