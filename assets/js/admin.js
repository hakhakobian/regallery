jQuery(document).ready(function () {
  reacg_track_unsaved_changes();

  /* Save options on saving the gallery.*/
  jQuery("#publish").on("click", function () {
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
    const galleryItemsContainer = item.closest(".reacg_items");
    /* The image id to be deleted.*/
    let image_id = item.data("id");
    let type = item.data("type");
    if ( type === "video" ) {
      reacg_remove_thumbnail(galleryItemsContainer, image_id);
    }
    else if ( reacg.allowed_post_types.hasOwnProperty(type) && String(image_id).includes("dynamic") ) {
      galleryItemsContainer.find(".additional_data").val("");
    }
    item.remove();
    let images_ids = reacg_get_image_ids(galleryItemsContainer, true);
    let index = images_ids.indexOf(image_id);
    images_ids.splice(index, 1);
    reacg_set_image_ids(galleryItemsContainer, images_ids);
    /* Save images on delete.*/
    reacg_save_images(galleryItemsContainer);
  });
  /* Bind an edit event to the every image item.*/
  jQuery(document).on("click", ".reacg_item .reacg-edit", function () {
    let item = jQuery(this).closest(".reacg_item");
    const galleryItemsContainer = item.closest(".reacg_items");
    /* The image id to be edited.*/
    let image_id = item.data("id");
    let type = item.data("type");

    let media_uploader = wp.media( {
      title: reacg.edit,
      button: { text: reacg.update },
      multiple: false
    } );
    media_uploader.on('open', function() {
      if ( reacg.allowed_post_types.hasOwnProperty(type) ) {
        reacg_add_posts_tab(media_uploader, type.replace("dynamic", ""), galleryItemsContainer.data("post-id"));
      }
      else {
        let selection = media_uploader.state().get('selection');
        selection.add(wp.media.attachment(image_id));
      }
    });
    media_uploader.on("close", function () {
      media_uploader.remove();
    });
    media_uploader.open();
    media_uploader.on( 'select', function () {
      if ( reacg.allowed_post_types.hasOwnProperty(type) ) {
        let selected_images = media_uploader.state().get( 'selection' ).toJSON();
        galleryItemsContainer.find(".additional_data").val(JSON.stringify(selected_images[0].additional_data));
        reacg_save_images(galleryItemsContainer);
      }
      else {
        reacg_reload_preview();
      }
      media_uploader.remove();
    });

  });

  /* Bind an edit thumbnail event to the every video item.*/
  jQuery(document).on("click", ".reacg_item .reacg-edit-thumbnail", function () {
    let item = jQuery(this).closest(".reacg_item");
    const galleryItemsContainer = item.closest(".reacg_items");
    /* The image id to be edited.*/
    let image_id = item.data("id");

    let media_uploader = wp.media( {
      title: reacg.edit_thumbnail,
      library: { type: 'image' },
      button: { text: reacg.update_thumbnail },
      multiple: false
    } );
    media_uploader.on("close", function () {
      media_uploader.remove();
    });
    media_uploader.open();
    media_uploader.on( 'select', function () {
      let selected_image = media_uploader.state().get('selection').toJSON();
      if ( typeof selected_image[0] !== "undefined" ) {
        reacg_save_thumbnail(galleryItemsContainer, image_id, selected_image[0].id);
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

      media_uploader.remove();
    } );
  });
});

/**
 * Make the image items sortable.
 *
 * @param container
 */
function reacg_make_items_sortable(container) {
  jQuery(container).find(".reacg_items").sortable({
    items: ".reacg-sortable",
    update: function (event, tr) {
      let images_ids = [];
      jQuery(this).find("> .reacg-sortable").each(function () {
        images_ids.push(jQuery(this).data('id'));
      });
      const galleryItemsContainer = jQuery(this);
      reacg_set_image_ids(galleryItemsContainer, images_ids);
      /* Save images on reorder.*/
      reacg_save_images(galleryItemsContainer);
    }
  });
}

function reacg_track_unsaved_changes() {
  /* Track only the newly added not yet saved galleries (not for builders).*/
  if ( jQuery("#post_type").val() === "reacg"
    && jQuery("#original_post_status").val() === "auto-draft" ) {
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
 * @param galleryItemsContainer
 * @param parsed
 * @returns {any|*[]}
 */
function reacg_get_image_ids(galleryItemsContainer, parsed) {
  let data = galleryItemsContainer.find(".images_ids").val();
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
 * @param galleryItemsContainer
 * @param arr
 */
function reacg_set_image_ids(galleryItemsContainer, arr) {
  const images_ids = galleryItemsContainer.find(".images_ids");
  images_ids.val(JSON.stringify(arr));
  /* Dispatch event for newly added and not yet saved posts (not for builders).*/
  if ( jQuery("#post_type").val() === "reacg"
    && jQuery("#original_post_status").val() === "auto-draft" ) {
    const event = new Event('added-images');
    /* Use [0] to get the raw DOM element.*/
    images_ids[0].dispatchEvent(event);
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
 * @param that
 */
function reacg_media_uploader( e, that ) {
  e.preventDefault();

  const galleryItemsContainer = jQuery(that).closest(".reacg_items");

  let media_uploader = wp.media.frames.file_frame = wp.media( {
    title: reacg.choose_images,
    button: { text: reacg.insert },
    multiple: true
  } );

  // Disable the images which are already added to the gallery.
  media_uploader.on('open', function () {
    // Get the added images.
    let images_ids = reacg_get_image_ids(galleryItemsContainer, true);
    reacg_add_posts(media_uploader, images_ids, galleryItemsContainer.data("post-id"));

    // On clicking Media library tab inside the uploader.
    jQuery(document).on("click", ".media-menu-item", function () {
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

  media_uploader.on("close", function () {
    media_uploader.remove();
  });

  media_uploader.open();

  media_uploader.on( 'select', function () {
    /* Get images ids already added.*/
    let images_ids = reacg_get_image_ids(galleryItemsContainer, true);

    /* Get selected images.*/
    let selected_images = media_uploader.state().get( 'selection' ).toJSON();
    for ( let key in selected_images ) {
      let title = selected_images[key].title;
      let sizes = selected_images[key].sizes;
      let type = selected_images[key].type;
      let thumbnail_url = reacg.no_image;
      if ( selected_images[key].type === "video" && typeof selected_images[key].thumb.src !== 'undefined' ) {
        // If there is thumbnail for the video and it is not a default image (video.png/video.svg)
        if ( selected_images[key].thumb.src.search("media/video.") === -1 )  {
          thumbnail_url = selected_images[key].thumb.src;
        }
      }
      else if ( sizes ) {
        if (typeof sizes.thumbnail !== 'undefined') {
          thumbnail_url = sizes.thumbnail.url;
        }
        else if (sizes.full && typeof sizes.full.url !== 'undefined') {
          thumbnail_url = sizes.full.url;
        }
      }

      let image_id = selected_images[key].id;

      /* Add an image to the gallery, if it doesn't already exist.*/
      if ( jQuery.inArray(image_id, images_ids) === -1 ) {
        /* Add selected image to the existing list of visual items.*/
        let clone = galleryItemsContainer.find(".reacg-template").clone();
        if ( type === "video" ) {
          clone.find(".reacg-edit-thumbnail").removeClass("reacg-hidden");
          clone.find(".reacg-cover").removeClass("reacg-hidden").addClass("dashicons dashicons-controls-play");
        }
        else if ( reacg.allowed_post_types.hasOwnProperty(type) ) {
          /* Any of the allowed post types.*/
          if ( String(image_id).includes("dynamic") ) {
            thumbnail_url = "";
            galleryItemsContainer.find(".additional_data").val(JSON.stringify(selected_images[key].additional_data));
          }
          else {
            clone.find(".reacg-edit").addClass("reacg-hidden");
          }
          clone.find(".reacg-cover").removeClass("reacg-hidden").addClass("dashicons " + reacg.allowed_post_types[type]['class']);
        }
        clone.attr("data-id", image_id);
        clone.attr("data-type", type);
        clone.find(".reacg_item_image").css("background-image", "url('" + thumbnail_url + "')").attr("title", title);
        clone.removeClass("reacg-hidden reacg-template").addClass("reacg-sortable");
        clone.insertAfter(galleryItemsContainer.find(".reacg_item_new"));
        /* Add selected image id to the existing list.*/
        images_ids.unshift(image_id);
      }
    }

    /* Update the images data.*/
    reacg_set_image_ids(galleryItemsContainer, images_ids);

    /* Save the images.*/
    reacg_save_images(galleryItemsContainer);

    media_uploader.remove();
  } );
}

/**
 * Save the images IDs to the gallery.
 *
 * @param galleryItemsContainer
 */
function reacg_save_images(galleryItemsContainer) {
  reacg_toggle_loading();
  jQuery.ajax({
    type: "POST",
    url: galleryItemsContainer.data("ajax-url"),
    data: {
      "action": "reacg_save_images",
      "post_id": galleryItemsContainer.data("post-id"),
      "images_ids": reacg_get_image_ids(galleryItemsContainer, false),
      "additional_data": galleryItemsContainer.find(".additional_data").val(),
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

/**
 * Trigger hidden button click to reload the preview.
 */
function reacg_reload_preview() {
  /* Update the gallery timestamp before the preview reload to prevent data from being read from the cache.*/
  document.querySelector(".reacg-preview").setAttribute("data-gallery-timestamp", Date.now());

  /* Remove all containers with the same ID except the last one. */
  let containers = document.querySelectorAll("#reacg-reloadData");
  if ( containers.length > 1 ) {
    for (let i = 0; i < containers.length - 1; i++) {
      containers[i].remove()
    }
  }

  jQuery("#reacg-reloadData").trigger("click");
}

/**
 * Save the thumbnail for the given item.
 *
 * @param galleryItemsContainer
 * @param id
 * @param thumbnail_id
 */
function reacg_save_thumbnail(galleryItemsContainer, id, thumbnail_id) {
  reacg_toggle_loading();
  jQuery.ajax({
    type: "POST",
    url: galleryItemsContainer.data("ajax-url"),
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
 * @param galleryItemsContainer
 * @param id
 */
function reacg_remove_thumbnail(galleryItemsContainer, id) {
  reacg_toggle_loading();
  jQuery.ajax({
    type: "POST",
    url: galleryItemsContainer.data("ajax-url"),
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