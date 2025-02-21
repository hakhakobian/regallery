jQuery(document).ready(function () {
  reacg_track_unsaved_changes();

  /* Save options on saving the gallery.*/
  jQuery(document).on("click", "#publish", function () {
    jQuery( ".save-settings-button" ).trigger("click");
  });
  /* Bind an event to the add image button.*/
  jQuery(document).on("click", ".reacg_item_new", function (event) {
    reacg_media_uploader( event, this );
  });

  /* Make the image items sortable.*/
  reacg_make_items_sortable(document);

  /* Bind a delete event to the every image item.*/
  jQuery(document).on("click", ".reacg_item .reacg-delete", function () {
    let item = jQuery(this).closest(".reacg_item");
    /* The image id to be deleted.*/
    let image_id = item.data("id");
    if ( item.data("type") === "video" ) {
      reacg_remove_thumbnail(image_id);
    }
    item.remove();

    let images_ids = reacg_get_image_ids(jQuery(this).closest(".reacg_items"), true);
    let index = images_ids.indexOf(image_id);
    images_ids.splice(index, 1);
    reacg_set_image_ids(jQuery(this).closest(".reacg_items"), images_ids);
    /* Save images on delete.*/
    reacg_save_images(jQuery(this).closest(".reacg_items"));
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
    media_uploader.on( 'select', function () {
      reacg_reload_preview();
      media_uploader.close();
    });
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

      media_uploader.close();
    } );
  });
});

/**
 * Make the image items sortable.
 */
function reacg_make_items_sortable(container) {
  jQuery(container).find(".reacg_items").sortable({
    items: ".reacg-sortable",
    update: function (event, tr) {
      let images_ids = [];
      jQuery(".reacg_items > .reacg-sortable").each(function () {
        images_ids.push(jQuery(this).data('id'));
      });
      reacg_set_image_ids(jQuery(this), images_ids);
      /* Save images on reorder.*/
      reacg_save_images(jQuery(this));
    }
  });
}

function reacg_track_unsaved_changes() {
  /* Track only the newly added not yet saved galleries.*/
  if ( document.getElementById('original_post_status').value === "auto-draft" ) {
    let isEmpty = false;
    const hiddenInput = document.getElementById('images_ids');

    if ( hiddenInput ) {
      /* Monitor changes.*/
      hiddenInput.addEventListener('added-images', function () {
        isEmpty = true;
      });
      /* Trigger warning if changes are unsaved.*/
      window.onbeforeunload = function () {
        if (isEmpty) {
          return "Changes you made may not be saved.";
        }
      };
      /* Reset isEmpty when the form is submitted.*/
      jQuery('form').on('submit', function () {
        isEmpty = false;
      });
    }
  }
}

/**
 * Get images IDs array.
 *
 * @param parsed
 * @returns {any|*[]}
 */
function reacg_get_image_ids(gallery_items, parsed) {
  let data = gallery_items.find(".images_ids").val();
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
 * @param gallery_items
 * @param arr
 */
function reacg_set_image_ids(gallery_items, arr) {
  const images_ids = gallery_items.find(".images_ids");
  images_ids.val(JSON.stringify(arr));

  /* Dispatch event for newly added and not yet saved posts.*/
  if (jQuery('#original_post_status').val() === "auto-draft") {
    const event = new Event('added-images');
    images_ids[0].dispatchEvent(event); // Use [0] to get the raw DOM element
  }
}

/**
 * Disable the images which are already added to the gallery.
 *
 * @param images_ids
 */
function reacg_check_images(images_ids) {
  jQuery("ul.attachments li").each(function () {
    if ( jQuery.inArray(jQuery(this).data("id"), images_ids) !== -1 ) {
      jQuery(this).attr("title", "Already added").addClass("reacg-already-added");
      jQuery(this).find(".thumbnail").on("click", function (e) {
        e.stopPropagation();
      });
    }
  });
}

/**
 * Disable the image which is already added to the gallery.
 *
 * @param images_ids
 */
function reacg_check_image(images_ids) {
  // Select the container for images inside the media modal for the current uploader.
  let container = jQuery('.media-frame-content .attachments:visible');
  if ( container.length > 0 ) {
    // Create a MutationObserver to watch for changes in the container.
    let observer = new MutationObserver(function (mutationsList) {
      // Check if the .attachment container is added to the DOM.
      let attachmentContainer = container.find('.attachment');
      if ( attachmentContainer.length > 0 ) {
        // Attach load event listener to its images.
        attachmentContainer.find('img').one('load', function () {
          // Disable the image if it is already inserted into the gallery.
          if ( jQuery.inArray(jQuery(this).closest("li").data("id"), images_ids) !== -1 ) {
            jQuery(this).closest("li").attr("title", "Already added").addClass("reacg-already-added");
            jQuery(this).closest("li").find(".thumbnail").on("click", function (e) {
              e.stopPropagation();
            });
          }
        }).each(function () {
          if ( this.complete ) {
            jQuery(this).trigger('load');
          }
        });
        // Stop observing mutations once the container is found.
        observer.disconnect();
      }
    });
    // Start observing mutations in the container
    observer.observe(container[0], {childList: true, subtree: true});
  }
}

/**
 * Open Media uploader and set the event to the Insert button.
 *
 * @param e
 */
function reacg_media_uploader( e, that ) {
  e.preventDefault();

  const gallery_items = jQuery(that).closest(".reacg_items");

  let media_uploader = wp.media.frames.file_frame = wp.media( {
    title: reacg.choose_images,
    button: { text: reacg.insert },
    multiple: true
  } );

  // Disable the images which are already added to the gallery.
  media_uploader.on('open', function () {
    // Get the added images.
    let images_ids = reacg_get_image_ids(gallery_items, true);

    // On clicking Media library tab inside the uploader.
    jQuery(document).on("click", "#menu-item-browse", function () {
      // When images are already loaded (e.g. opening after closing the uploader).
      reacg_check_images(images_ids);
      // When images are not loaded (e.g. opening first time).
      reacg_check_image(images_ids);
    });

    // On clicking load more button in the uploader.
    jQuery(document).on("click", ".load-more-wrapper .load-more", function () {
      reacg_check_image(images_ids);
    });

    // On opening Media library tab when images are not loaded.
    reacg_check_image(images_ids);
  });
  media_uploader.open();

  media_uploader.on( 'select', function () {
    /* Get images ids already added.*/
    let images_ids = reacg_get_image_ids(gallery_items, true);

    /* Get selected images.*/
    let selected_images = media_uploader.state().get( 'selection' ).toJSON();
    for ( let key in selected_images ) {
      let title = selected_images[key].title;
      let sizes = selected_images[key].sizes;
      let type = "image";
      let thumbnail_url = reacg.no_image;
      if ( selected_images[key].type === "video" && typeof selected_images[key].thumb.src !== 'undefined' ) {
        // If there is thumbnail for the video and it is not a default image (video.png/video.svg)
        if ( selected_images[key].thumb.src.search("media/video.") === -1 )  {
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
        let clone = gallery_items.find(".reacg-template").clone();
        if ( type === "video" ) {
          clone.find(".reacg-edit-thumbnail").removeClass("reacg-hidden");
          clone.find(".reacg-cover").removeClass("reacg-hidden");
        }
        clone.attr("data-id", image_id);
        clone.attr("data-type", type);
        clone.find(".reacg_item_image").css("background-image", "url('" + thumbnail_url + "')").attr("title", title);
        clone.removeClass("reacg-hidden reacg-template").addClass("reacg-sortable");
        clone.insertAfter(gallery_items.find(".reacg_item_new"));
        /* Add selected image id to the existing list.*/
        images_ids.unshift(image_id);
      }
    }

    /* Update the images data.*/
    reacg_set_image_ids(gallery_items, images_ids);

    /* Save the images.*/
    reacg_save_images(gallery_items);

    media_uploader.close();
  } );
}

/**
 * Save the images IDs to the gallery.
 */
function reacg_save_images(gallery_items) {
  reacg_toggle_loading();
  jQuery.ajax({
    type: "POST",
    url: gallery_items.data("ajax-url"),
    data: {
      "action": "reacg_save_images",
      "post_id": gallery_items.data("post-id"),
      "images_ids": reacg_get_image_ids(gallery_items, false),
      "gallery_timestamp": Date.now() /* Update the gallery timestamp on images save to prevent data from being read from the cache.*/
    },
    complete: function (data) {
      reacg_toggle_loading();

      reacg_reload_preview();

      /* Highlight templates list for newly added and not yet saved posts.*/
      if ( jQuery("#original_post_status").val() === "auto-draft" ) {
        const event = new Event('highlight-template-select');
        window.dispatchEvent(event);
      }
    }
  });
}

/* Trigger hidden button click to reload the preview.*/
function reacg_reload_preview() {
  /* Update the gallery timestamp before the preview reload to prevent data from being read from the cache.*/
  document.querySelector(".reacg-preview").setAttribute("data-gallery-timestamp", Date.now());
  let containers = document.querySelectorAll("#reacg-reloadData");
  if (containers.length > 1) {
    for (let i = 0; i < containers.length - 1; i++) {
      containers[i].remove(); // Remove all except the last one
    }
  }
  jQuery("#reacg-reloadData").trigger("click");
}

/**
 * Save the thumbnail for the given item.
 *
 * @param id
 * @param thumbnail_id
 */
function reacg_save_thumbnail(id, thumbnail_id) {
  reacg_toggle_loading();
  jQuery.ajax({
    type: "POST",
    url: jQuery(".reacg_items").data("ajax-url"),
    data: {
      "action": "reacg_save_thumbnail",
      "id": id,
      "thumbnail_id": thumbnail_id
    },
    complete: function (data) {
      reacg_toggle_loading();

      reacg_reload_preview();
    }
  });
}

/**
 * Remove the item thumbnail.
 *
 * @param id
 */
function reacg_remove_thumbnail(id) {
  reacg_toggle_loading();
  jQuery.ajax({
    type: "POST",
    url: jQuery(".reacg_items").data("ajax-url"),
    data: {
      "action": "reacg_delete_thumbnail",
      "id": id,
    },
    complete: function (data) {
      reacg_toggle_loading();
    }
  });
}

/**
 * Add/remove loading.
 */
function reacg_toggle_loading() {
  jQuery("#publishing-action .spinner").toggleClass("is-active");
  jQuery("#publishing-action #publish").toggleClass("disabled");
}