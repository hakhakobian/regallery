jQuery(document).ready(function () {
  /* Save options on saving the gallery.*/
  jQuery("#publish").on("click", function () {
    jQuery( ".MuiButton-root" ).trigger("click");
  });
  /* Bind an event to the add image button.*/
  jQuery(".reacg_item_new ").on("click", function (event) {
    reacg_media_uploader( event );
  });

  /* Make the image items sortable.*/
  jQuery( ".reacg_items" ).sortable( {
    items: ".reacg-sortable",
    update: function ( event, tr ) {
      let images_ids = [];
      jQuery( ".reacg_items > .reacg-sortable" ).each( function () {
        images_ids.push(jQuery( this ).data( 'id' ));
      } );
      reacg_set_image_ids( images_ids );
      /* Save images on reorder.*/
      reacg_save_images();
    }
  } );

  /* Bind a delete event to the every image item.*/
  jQuery(document).on("click", ".reacg_item .reacg-delete", function () {
    let item = jQuery(this).closest(".reacg_item");
    /* The image id to be deleted.*/
    let image_id = item.data("id");
    item.remove();

    let images_ids = reacg_get_image_ids(true);
    let index = images_ids.indexOf(image_id);
    images_ids.splice(index, 1);
    reacg_set_image_ids(images_ids);
    /* Save images on delete.*/
    reacg_save_images();
  });

  /* Bind an edit event to the every image item.*/
  jQuery(document).on("click", ".reacg_item .reacg-edit", function () {
    let item = jQuery(this).closest(".reacg_item");
    /* The image id to be edited.*/
    let image_id = item.data("id");
    let type = item.data("type");

    let media_uploader = wp.media( {
      title: reacg.edit,
      button: { text: reacg.update },
      multiple: false
    } );
    media_uploader.on('open', function() {
      let selection = media_uploader.state().get('selection');
      selection.add(wp.media.attachment(image_id));
    });
    media_uploader.open();
  });

  /* Bind an edit thumbnail event to the every video item.*/
  jQuery(document).on("click", ".reacg_item .reacg-edit-thumbnail", function () {
    let item = jQuery(this).closest(".reacg_item");
    /* The image id to be edited.*/
    let image_id = item.data("id");

    let media_uploader = wp.media( {
      title: reacg.edit_thumbnail,
      library: { type: 'image' },
      button: { text: reacg.update_thumbnail },
      multiple: false
    } );
    media_uploader.open();
    media_uploader.on( 'select', function () {
      let selected_image = media_uploader.state().get('selection').toJSON();
      if ( typeof selected_image[0] !== "undefined" ) {
        reacg_save_thumbnail(image_id, selected_image[0].id);
        /* Change the item thumbnail in item view.*/
        let sizes = selected_image[0].sizes;
        let thumbnail_url = reacg.no_image;
        if ( typeof sizes.thumbnail !== 'undefined' ) {
          thumbnail_url = sizes.thumbnail.url;
        }
        else if ( typeof sizes.full.url !== 'undefined' ) {
          thumbnail_url = sizes.full.url;
        }
        item.find(".reacg_item_image").css("background-image", "url('" + thumbnail_url + "')");
      }
      /* Save images on inserting from media library.*/
      reacg_save_images();

      media_uploader.close();
    } );
  });
});

/**
 * Get images IDs array.
 *
 * @param parsed
 * @returns {any|*[]}
 */
function reacg_get_image_ids(parsed) {
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
function reacg_set_image_ids(arr) {
  jQuery("#images_ids").val(JSON.stringify(arr));
}

/**
 * Disable images which are already added to the gallery.
 */
function reacg_check_images() {
  let images_ids = reacg_get_image_ids(true);
  jQuery("ul.attachments li").each(function () {
    if (jQuery.inArray(jQuery(this).data("id"), images_ids) !== -1) {
      jQuery(this).attr("title", "Already added").addClass("reacg-already-added");
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
function reacg_media_uploader( e ) {
  e.preventDefault();

  let media_uploader = wp.media.frames.file_frame = wp.media( {
    title: reacg.choose_images,
    button: { text: reacg.insert },
    multiple: true
  } );
  media_uploader.on('open', function () {
    setTimeout(function (){
      reacg_check_images();
    }, 500);
    jQuery("#menu-item-browse").on("click", function () {
      reacg_check_images();
    });
  });
  media_uploader.open();

  media_uploader.on( 'select', function () {
    /* Get images ids already added.*/
    let images_ids = reacg_get_image_ids(true);

    /* Get selected images.*/
    let selected_images = media_uploader.state().get( 'selection' ).toJSON();
    for ( let key in selected_images ) {
      let title = selected_images[key].title;
      let sizes = selected_images[key].sizes;
      let type = "image";
      let thumbnail_url = reacg.no_image;
      if ( selected_images[key].type === "video" && typeof selected_images[key].thumb.src !== 'undefined' ) {
        if ( selected_images[key].thumb.src.search("media/video.png") === -1 )  {
          thumbnail_url = selected_images[key].thumb.src;
        }
        type = "video";
      }
      else if ( typeof sizes.thumbnail !== 'undefined' ) {
        thumbnail_url = sizes.thumbnail.url;
      }
      else if ( typeof sizes.full.url !== 'undefined' ) {
        thumbnail_url = sizes.full.url;
      }

      let image_id = selected_images[key].id;

      /* Add an image to the gallery, if it doesn't already exist.*/
      if ( jQuery.inArray(image_id, images_ids) === -1 ) {
        /* Add selected image to the existing list of visual items.*/
        let clone = jQuery(".reacg-template").clone();
        if ( type === "video" ) {
          clone.find(".reacg-edit-thumbnail").removeClass("reacg-hidden");
        }
        clone.attr("data-id", image_id);
        clone.attr("data-type", type);
        clone.find(".reacg_item_image").css("background-image", "url('" + thumbnail_url + "')").attr("title", title);
        clone.removeClass("reacg-hidden reacg-template").addClass("reacg-sortable");
        clone.insertAfter(".reacg_item_new");
        /* Add selected image id to the existing list.*/
        images_ids.unshift(image_id);
      }
    }

    /* Update the images data.*/
    reacg_set_image_ids(images_ids);

    /* Save the images.*/
    reacg_save_images();

    media_uploader.close();
  } );
}

/**
 * Save the images IDs to the gallery.
 */
function reacg_save_images() {
  reacg_loading();

  jQuery.ajax({
    type: 'POST',
    url: jQuery(".reacg_items").data("ajax-url"),
    data: {
      'post_id': jQuery(".reacg_items").data("post-id"),
      'images_ids': reacg_get_image_ids(false)
    },
    complete: function (data) {
      reacg_loading();
      /* Trigger hidden button click to reload the preview.*/
      jQuery("#reacg-reloadData").trigger("click");

      /* Run autosave for newly added posts.*/
      if ( jQuery("#original_post_status").val() === "auto-draft" ) {
        jQuery("#publish").trigger("click");
      }
    }
  });
}

/**
 * Save the thumbnail for the given item.
 *
 * @param id
 * @param thumbnail_id
 */
function reacg_save_thumbnail(id, thumbnail_id) {
  reacg_loading();
  jQuery.ajax({
    type: 'POST',
    url: jQuery(".reacg_items").data("ajax-url"),
    data: {
      'id': id,
      'thumbnail_id': thumbnail_id
    },
    complete: function (data) {
      /* Trigger hidden button click to reload the preview.*/
      jQuery("#reacg-reloadData").trigger("click");
      reacg_loading();
    }
  });
}

/**
 * Add/remove loading.
 */
function reacg_loading() {
  jQuery("#publishing-action .spinner").toggleClass("is-active");
  jQuery("#publishing-action #publish").toggleClass("disabled");
}