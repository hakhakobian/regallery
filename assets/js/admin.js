jQuery(document).ready(function () {
  // Bind an event to the add image button.
  jQuery(".aig_item_new ").on("click", function (event) {
    aig_media_uploader( event );
  });

  // Make the image items sortable.
  jQuery( ".aig_items" ).sortable( {
    items: ".aig-sortable",
    update: function ( event, tr ) {
      let images_ids = [];
      jQuery( ".aig_items > .aig-sortable" ).each( function () {
        images_ids.push(jQuery( this ).data( 'id' ));
      } );
      aig_set_image_ids( images_ids );
      // Save the images.
      aig_save_images();
    }
  } );

  // Bind a delete event to the every image item.
  jQuery(document).on("click", ".aig_item .aig-delete", function () {
    let item = jQuery(this).closest(".aig_item");
    // The image id to be deleted.
    let image_id = item.data("id");
    item.remove();

    let images_ids = aig_get_image_ids(true);
    let index = images_ids.indexOf(image_id);
    images_ids.splice(index, 1);
    aig_set_image_ids(images_ids);
    // Save the images.
    aig_save_images();
  });

  // Bind an edit event to the every image item.
  jQuery(document).on("click", ".aig_item .aig-edit", function () {
    let item = jQuery(this).closest(".aig_item");
    // The image id to be edited.
    let image_id = item.data("id");

    let media_uploader = wp.media( {
      title: aig.edit_image,
      library: { type: 'image' },
      button: { text: aig.update },
      multiple: false
    } );
    media_uploader.on('open', function() {
      let selection = media_uploader.state().get('selection');
      selection.add(wp.media.attachment(image_id));
    });
    media_uploader.open();
  });
});

/**
 * Get images IDs array.
 *
 * @param parsed
 * @returns {any|*[]}
 */
function aig_get_image_ids(parsed) {
  let data = jQuery("#images_ids").val();
  if ( parsed === true ) {
    return data !== "" ? JSON.parse(data) : [];
  }
  else {
    return data;
  }
}

/**
 * Update images IDs passing an array.
 *
 * @param arr
 */
function aig_set_image_ids(arr) {
  jQuery("#images_ids").val(JSON.stringify(arr));
}

/**
 * Disable images which are already added to the gallery.
 */
function aig_check_images() {
  let images_ids = aig_get_image_ids(true);
  jQuery("ul.attachments li").each(function () {
    if (jQuery.inArray(jQuery(this).data("id"), images_ids) !== -1) {
      jQuery(this).attr("title", "Already added").addClass("aig-already-added");
      jQuery(this).find(".thumbnail").on("click", function (e) {
        e.stopPropagation();
      });
    }
  });
}
/**
 * Open Media uploader and set the event to the Insert button.
 *
 * @param e
 * @param multiple
 */
function aig_media_uploader( e ) {
  e.preventDefault();

  let media_uploader = wp.media.frames.file_frame = wp.media( {
    title: aig.choose_images,
    library: { type: 'image' },
    button: { text: aig.insert },
    multiple: true
  } );
  media_uploader.on('open', function () {
    setTimeout(function (){
      aig_check_images();
    }, 500);
    jQuery("#menu-item-browse").on("click", function () {
      aig_check_images();
    });
  });
  media_uploader.open();

  media_uploader.on( 'select', function () {
    // Get images already added.
    //let images = jQuery("#images").val() != "" ? JSON.parse(jQuery("#images").val()) : [];
    // Get images ids already added.
    let images_ids = aig_get_image_ids(true);

    // Get selected images.
    let selected_images = media_uploader.state().get( 'selection' ).toJSON();
    for ( let key in selected_images ) {
      let title = selected_images[key].title;
      let thumbnail_url = selected_images[key].sizes.thumbnail.url;
      let image_id = selected_images[key].id;

      // Add an image to the gallery, if it doesn't already exist.
      if ( jQuery.inArray(image_id, images_ids) === -1 ) {
        // Add selected image to the existing list of visual items.
        let clone = jQuery(".aig-template").clone();
        clone.attr("data-id", image_id);
        clone.find(".aig_item_image").css("background-image", "url('" + thumbnail_url + "')").attr("title", title);
        clone.removeClass("aig-hidden aig-template").addClass("aig-sortable");
        clone.insertAfter(".aig_item_new");
        // Add selected image to the existing list.
        //images.push({id: image_id, title: title, url: thumbnail_url});
        // Add selected image id to the existing list.
        images_ids.unshift(image_id);
      }
    }

    // Update the images data.
    aig_set_image_ids(images_ids);
    //jQuery("#images").val(JSON.stringify(images));

    // Save the images.
    aig_save_images();

    media_uploader.close();
  } );
}

/**
 * Save the images IDs to the gallery.
 */
function aig_save_images() {
  aig_loading();

  jQuery.ajax({
    type: 'POST',
    url: jQuery(".aig_items").data("ajax-url"),
    data: {
      'post_id': jQuery(".aig_items").data("post-id"),
      'images_ids': aig_get_image_ids(false)
    },
    complete: function (data) {
      aig_loading();
      // Trigger hidden button click to reload the preview.
      jQuery("#reloadData").trigger("click");
    }
  });
}

/**
 * Add/remove loading.
 */
function aig_loading() {
  jQuery("#publishing-action .spinner").toggleClass("is-active");
  jQuery("#publishing-action #publish").toggleClass("disabled");
}