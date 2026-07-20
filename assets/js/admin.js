jQuery(document).ready(function () {
  reacg_track_unsaved_changes();

  const max_edit_count = 3;
  const max_minutes_spent = 1;
  if ( !localStorage.getItem("reacg-opened-contact-us-dialog") ) {
    setTimeout(function () {
      if ( jQuery(".reacg_items").data("edit-count") > max_edit_count ) {
        const reacg_open_need_help_dialog_exist = setInterval(function () {
          if ( typeof reacg_open_need_help_dialog !== "undefined" ) {
            reacg_open_need_help_dialog({onClose: () => localStorage.setItem("reacg-opened-contact-us-dialog", true)});
            clearInterval(reacg_open_need_help_dialog_exist);
          }
        }, 100);
      }
    }, 5 * 1000);
  }

  jQuery(document).on("click", ".reacg_help_icon", function () {
    reacg_open_need_help_dialog();
  });

  /* Check if user is pro.*/
  jQuery.ajax({
    type: "GET",
    url: reacg.core_rest_url_v2 + "user",
    contentType: "application/json",
    complete: function (response) {
      reacg_isPro(!!response.responseJSON);
    }
  });

  jQuery(document).on("click", ".reacg-license-activate-button", function () {
    const activate = jQuery(this).data("activate");
    const container = jQuery(this).closest("#gallery-license");
    const licenseKey = container.find(".reacg-license-key").val();
    const errorNoteCont = container.find(".reacg-error");
    
    if ( activate && !licenseKey ) {
      errorNoteCont.removeClass("hidden").html(reacg.enter_license_key);
      return;
    }

    const spinner = container.find(".spinner");
    spinner.addClass("is-active");

    const button = jQuery(this);
    button.attr("disabled", "desabled");

    jQuery.ajax({
      type: "POST",
      url: reacg.core_rest_url_v2 + "user",
      contentType: "application/json",
      data: JSON.stringify({
        licenseKey: licenseKey,
        action: activate ? "activate" : "deactivate",
      }),
      complete: function (response) {
        spinner.removeClass("is-active");
        button.removeAttr("disabled");
        if (response.status === 200 && response.success && response.responseJSON) {
          container.find(".reacg-success").html(response.responseJSON.message);
          reacg_isPro(!!activate);
        }
        else {
          errorNoteCont.removeClass("hidden").html(response.responseJSON.errors.message);
        }
      }
    });
  });

  if ( !localStorage.getItem("reacg-opened-contact-us-dialog") ) {
    setTimeout(function () {
      if ( typeof reacg_open_new_here_dialog !== "undefined" ) {
        reacg_open_new_here_dialog({onClose: () => localStorage.setItem("reacg-opened-contact-us-dialog", true)});
      }
    }, max_minutes_spent * 60 * 1000);
  }

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
    if ( reacg.allowed_post_types.hasOwnProperty(type)
      && String(image_id).includes("dynamic") ) {
      /* Reset additional data on dynamic gallery delete.*/
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
  jQuery(document).on("click", ".reacg_item .reacg-edit", function (e) {
    let item = jQuery(this).closest(".reacg_item");
    const galleryItemsContainer = item.closest(".reacg_items");
    /* The image id to be edited.*/
    let image_id = item.data("id");
    let type = item.data("type");
    
    if ( reacg.allowed_post_types.hasOwnProperty(type)
      && String(image_id).includes("dynamic") ) {
      /* If dynamic is edited.*/
      let media_uploader = wp.media({
        title: reacg.edit,
        button: {text: reacg.update},
        multiple: false
      });
      media_uploader.on('open', function () {
        reacg_add_posts_tab(media_uploader, type.replace("dynamic", ""), galleryItemsContainer.data("post-id"));
      });
      media_uploader.on('select', function () {
        const selected_images = media_uploader.state().get('selection').toJSON();
        galleryItemsContainer.find(".additional_data").val(JSON.stringify(selected_images[0].additional_data));
        reacg_save_images(galleryItemsContainer);
        media_uploader.remove();
      });
      media_uploader.on('close', function () {
        delete wp.Uploader.defaults.multipart_params.reacg;
        delete wp.Uploader.defaults.multipart_params.reacg_nonce;
        media_uploader.remove();
      });
      media_uploader.open();
    }
    else {
      if ( jQuery(this).attr('disabled') ) {
        /* To prevent multiple clicks.*/
        e.preventDefault();
        e.stopPropagation();
        return false;
      }

      jQuery(this).attr("disabled", true);
      const editButton = jQuery(this);
      const attachment = wp.media.attachment(image_id);
      attachment.fetch().then(function () {
        reacg_open_attachment_edit_modal(editButton, attachment);
      }).always(function () {
        editButton.removeAttr("disabled");
      });
      return false;
    }
  });

  /* Bind an edit thumbnail event to the every video item.*/
  jQuery(document).on("click", ".reacg_item .reacg-edit-thumbnail", function () {
    wp.Uploader.defaults.multipart_params = wp.Uploader.defaults.multipart_params || {};
    wp.Uploader.defaults.multipart_params.reacg = 'gallery';
    wp.Uploader.defaults.multipart_params.reacg_nonce = reacg.nonce;
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
    media_uploader.on('close', function () {
      delete wp.Uploader.defaults.multipart_params.reacg;
      delete wp.Uploader.defaults.multipart_params.reacg_nonce;
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
        item.find(".reacg_item_image img").attr("src", thumbnail_url);
      }

      media_uploader.remove();
    } );
  });

  reacg_add_ai_button_to_uploader();
});

function reacg_isPro(isPro) {
  if (isPro) {
    jQuery("#reacg-metabox-widget-why-upgrade").remove();
    jQuery("#gallery-custom-css").find(".postbox-header h2 svg").remove();
    window.dispatchEvent(new Event('reacg:pro-activated'));
  }
  jQuery(".reacg-pro-not-active").toggle(!isPro);
  jQuery(".reacg-pro-active").toggle(isPro);
  jQuery("textarea[name=custom_css]").on("input", function () {
    let text = jQuery(this).val();
    if ( !isPro && text.length > 100) {
      jQuery(this).val(text.substring(0, 100));
      reacg_open_free_trial_offer_dialog({utm_medium: 'custom_css'});
    }
  });
  localStorage.setItem("reacg-pro", isPro);
}

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
  /* Select the container for images inside the media modal for the current uploader.*/
  let container = jQuery('.media-frame-content .attachments:visible');
  if ( container.length > 0 ) {
    /* Create a MutationObserver to watch for changes in the container.*/
    let observer = new MutationObserver(function (mutationsList) {
      /* Check if the .attachment container is added to the DOM.*/
      let attachmentContainer = container.find('.attachment');
      if ( attachmentContainer.length > 0 ) {
        /* Attach load event listener to its images.*/
        attachmentContainer.find('img').one('load', function () {
          /* Disable the image if it is already inserted into the gallery.*/
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
        /* Stop observing mutations once the container is found.*/
        observer.disconnect();
      }
    });
    /* Start observing mutations in the container.*/
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
  wp.Uploader.defaults.multipart_params = wp.Uploader.defaults.multipart_params || {};
  wp.Uploader.defaults.multipart_params.reacg = 'gallery';
  wp.Uploader.defaults.multipart_params.reacg_nonce = reacg.nonce;
  const galleryItemsContainer = jQuery(that).closest(".reacg_items");

  let media_uploader = wp.media.frames.file_frame = wp.media( {
    title: reacg.choose_images,
    button: { text: reacg.insert },
    multiple: 'add'
  } );

  /* Disable the images which are already added to the gallery.*/
  media_uploader.on('open', function () {
    /* Get the added images.*/
    let images_ids = reacg_get_image_ids(galleryItemsContainer, true);

    reacg_add_posts(media_uploader, images_ids, galleryItemsContainer.data("post-id"));

    /* On clicking Media library tab inside the uploader.*/
    jQuery(document).on("click", ".media-menu-item", function () {
      /* When images are already loaded (e.g. opening after closing the uploader).*/
      reacg_check_images(images_ids);
      /* When images are not loaded (e.g. opening first time).*/
      reacg_check_image(images_ids);
    });

    /* On clicking load more button in the uploader.*/
    jQuery(document).on("click", ".load-more-wrapper .load-more", function () {
      reacg_check_image(images_ids);
    });

    /* On opening Media library tab when images are not loaded.*/
    reacg_check_image(images_ids);
  });

  media_uploader.on("close", function () {
    delete wp.Uploader.defaults.multipart_params.reacg;
    delete wp.Uploader.defaults.multipart_params.reacg_nonce;
    media_uploader.remove();
  });

  media_uploader.open();

  media_uploader.on( 'select', function () {
    /* Get images ids already added.*/
    let images_ids = reacg_get_image_ids(galleryItemsContainer, true);
    let coverPromises = [];

    /* Get selected images.*/
    let selected_images = media_uploader.state().get( 'selection' ).toJSON();
    for ( let key in selected_images ) {
      let title = selected_images[key].title;
      let sizes = selected_images[key].sizes;
      let type = selected_images[key].type;
      let thumbnail_url = reacg.no_image;
      if ( selected_images[key].type === "video" && typeof selected_images[key].thumb.src !== 'undefined' ) {
        /* If there is thumbnail for the video and it is not a default image (video.png/video.svg)*/
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
        clone.find(".reacg_item_image").attr("title", title);
        if ( thumbnail_url ) {
          if ( !clone.find(".reacg_item_image img").length ) {
            clone.find(".reacg_item_image").prepend('<img alt="" />');
          }
          clone.find(".reacg_item_image img").attr("src", thumbnail_url);
        }
        clone.removeClass("reacg-hidden reacg-template").addClass("reacg-sortable");
        clone.insertAfter(galleryItemsContainer.find(".reacg_item_new"));
        if ( type === "video" && reacg_should_generate_video_cover(selected_images[key], thumbnail_url) ) {
          coverPromises.push(reacg_generate_video_cover(selected_images[key], clone, galleryItemsContainer));
        }
        /* Add selected image id to the existing list.*/
        images_ids.unshift(image_id);
      }
    }

    /* Update the images data.*/
    reacg_set_image_ids(galleryItemsContainer, images_ids);

    /* Save the images.*/
    reacg_save_images(galleryItemsContainer);

    if ( coverPromises.length ) {
      Promise.all(coverPromises).then(function (results) {
        if ( results.some(function (result) { return !!result; }) ) {
          reacg_reload_preview();
        }
      });
    }

    media_uploader.remove();
  } );
}

function reacg_should_generate_video_cover(selectedImage, thumbnailUrl) {
  if ( !selectedImage || selectedImage.type !== "video" || !selectedImage.id || !selectedImage.url ) {
    return false;
  }

  if ( selectedImage.thumb && typeof selectedImage.thumb.src !== 'undefined'
    && selectedImage.thumb.src.search("media/video.") === -1 ) {
    return false;
  }

  return thumbnailUrl === reacg.no_image;
}

function reacg_generate_video_cover(selectedImage, item, galleryItemsContainer) {
  return reacg_capture_video_frame(selectedImage.url)
    .then(function (blob) {
      return reacg_upload_video_cover(selectedImage.id, blob, selectedImage.filename || selectedImage.title || ("video-" + selectedImage.id));
    })
    .then(function (response) {
      if ( !response || !response.success || !response.data ) {
        return false;
      }

      if ( response.data.thumbnail_url ) {
        item.find(".reacg_item_image img").attr("src", response.data.thumbnail_url);
      }

      return true;
    })
    .catch(function (error) {
      console.warn('Video cover generation failed.', error);
      return false;
    });
}

function reacg_capture_video_frame(videoUrl) {
  return new Promise(function (resolve, reject) {
    const video = document.createElement('video');
    let settled = false;

    function cleanup() {
      video.pause();
      video.removeAttribute('src');
      video.load();
      video.remove();
    }

    function finish(callback, value) {
      if ( settled ) {
        return;
      }
      settled = true;
      cleanup();
      callback(value);
    }

    function drawFrame() {
      if ( !video.videoWidth || !video.videoHeight ) {
        finish(reject, new Error('Video frame is not available.'));
        return;
      }

      const canvas = document.createElement('canvas');
      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;
      const context = canvas.getContext('2d');
      if ( !context ) {
        finish(reject, new Error('Canvas context is not available.'));
        return;
      }

      context.drawImage(video, 0, 0, canvas.width, canvas.height);
      canvas.toBlob(function (blob) {
        if ( !blob ) {
          finish(reject, new Error('Failed to create image blob.'));
          return;
        }

        finish(resolve, blob);
      }, 'image/jpeg', 0.92);
    }

    video.preload = 'auto';
    video.muted = true;
    video.playsInline = true;
    video.style.position = 'fixed';
    video.style.left = '-9999px';
    video.style.top = '-9999px';

    video.addEventListener('error', function () {
      finish(reject, new Error('Video element failed to load.'));
    }, { once: true });

    video.addEventListener('loadeddata', function () {
      window.requestAnimationFrame(drawFrame);
    }, { once: true });

    document.body.appendChild(video);
    video.src = videoUrl;
    video.load();
  });
}

function reacg_upload_video_cover(videoId, blob, filename) {
  const formData = new FormData();
  const safeName = String(filename || ("video-" + videoId)).replace(/\.[^.]+$/, '') + '-cover.jpg';
  formData.append('action', 'reacg_auto_upload_video_cover');
  formData.append('video_id', videoId);
  formData.append('filename', safeName);
  formData.append('cover', blob, safeName);

  return jQuery.ajax({
    type: 'POST',
    url: reacg.ajax_url,
    data: formData,
    processData: false,
    contentType: false,
  });
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


function reacg_ai_icon() {
  return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">' +
    '<path fill="#FFFFFF" d="m19.026,12v6c0,.552-.448,1-1,1s-1-.448-1-1v-6c0-.552.448-1,1-1s1,.448,1,1Zm-7.42-5.283l3.071,11.029c.175.63-.298,1.254-.953,1.254-.443,0-.831-.294-.952-.72l-.643-2.28h-5.206l-.643,2.28c-.12.426-.509.72-.952.72h0c-.654,0-1.128-.624-.953-1.254l3.091-11.108c.141-.608.541-1.12,1.098-1.405.568-.292,1.22-.31,1.839-.05.587.246,1.037.817,1.204,1.535Zm-.041,7.283l-1.929-6.835c-.029-.114-.191-.114-.219,0l-1.929,6.835h4.077Zm11.462-4c-.552,0-1,.448-1,1v8c0,1.654-1.346,3-3,3H5.026c-1.654,0-3-1.346-3-3V5c0-1.654,1.346-3,3-3h8c.552,0,1-.448,1-1S13.578,0,13.026,0H5.026C2.269,0,.026,2.243.026,5v14c0,2.757,2.243,5,5,5h14c2.757,0,5-2.243,5-5v-8c0-.552-.448-1-1-1Zm-6.85-4.82l1.868.787.745,1.865c.161.404.552.668.987.668s.825-.265.987-.668l.741-1.854,1.854-.741c.404-.161.668-.552.668-.987s-.265-.825-.668-.987l-1.854-.741-.741-1.854C20.601.265,20.21,0,19.776,0s-.825.265-.987.668l-.737,1.843-1.84.697c-.406.154-.678.54-.686.974-.008.435.25.83.65.999Z"/>' +
    '</svg>';
}

function reacg_info_icon() {
  return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12,0A12,12,0,1,0,24,12,12.013,12.013,0,0,0,12,0Zm0,21a9,9,0,1,1,9-9A9.011,9.011,0,0,1,12,21Z"/><path d="M11.545,9.545h-.3A1.577,1.577,0,0,0,9.64,10.938,1.5,1.5,0,0,0,11,12.532v4.65a1.5,1.5,0,0,0,3,0V12A2.455,2.455,0,0,0,11.545,9.545Z"/><path d="M11.83,8.466A1.716,1.716,0,1,0,10.114,6.75,1.715,1.715,0,0,0,11.83,8.466Z"/></svg>';
}

function reacg_escape_html(value) {
  const stringValue = value == null ? '' : String(value);
  const htmlEntityMap = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;'
  };

  return stringValue.replace(/[&<>"']/g, function (character) {
    return htmlEntityMap[character];
  });
}

function reacg_get_attachment_field_value(attachment, fieldName) {
  const value = attachment.get(fieldName);
  if ( value && typeof value === 'object' ) {
    return value.raw || value.rendered || '';
  }

  return value || '';
}

function reacg_get_attachment_edit_link(attachmentId) {
  const id = parseInt(attachmentId, 10) || 0;
  if ( !id ) {
    return '#';
  }

  if ( typeof ajaxurl === 'string' && ajaxurl.length ) {
    return ajaxurl.replace('admin-ajax.php', 'post.php') + '?post=' + id + '&action=edit&image-editor';
  }

  return '/wp-admin/post.php?post=' + id + '&action=edit&image-editor';
}

function reacg_attachment_edit_modal(attachment) {
  const sizes = attachment.get('sizes');
  const attachmentType = reacg_get_attachment_field_value(attachment, 'type');
  const mimeType = reacg_get_attachment_field_value(attachment, 'mime');
  const isVideo = attachmentType === 'video' || (typeof mimeType === 'string' && mimeType.indexOf('video/') === 0);
  const attachmentUrl = reacg_get_attachment_field_value(attachment, 'url');
  const previewSize = sizes && sizes.thumbnail ? sizes.thumbnail : sizes && sizes.full ? sizes.full : null;
  const previewUrl = previewSize && previewSize.url ? previewSize.url : attachmentUrl || reacg.no_image;
  const previewMedia = isVideo && attachmentUrl
    ? '<video controls preload="metadata" src="' + reacg_escape_html(attachmentUrl) + '"></video>'
    : '<img src="' + reacg_escape_html(previewUrl) + '" alt="" />';
  const filename = reacg_get_attachment_field_value(attachment, 'filename');
  const dimensions = reacg_get_attachment_field_value(attachment, 'width') && reacg_get_attachment_field_value(attachment, 'height')
    ? reacg_get_attachment_field_value(attachment, 'width') + ' x ' + reacg_get_attachment_field_value(attachment, 'height')
    : '';
  const fileSize = reacg_get_attachment_field_value(attachment, 'filesizeHumanReadable') || '';
  const editLink = reacg_get_attachment_edit_link(attachment.get('id'));

  return jQuery('' +
    '<div class="reacg-modal reacg-attachment-modal" style="display:none;">' +
      '<div class="reacg-modal-wrapper">' +
        '<div class="reacg-modal-content">' +
          '<div class="reacg-modal__header">' +
            '<h1>' + reacg.edit + '</h1>' +
            '<span class="reacg-modal-close"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M19 6.41 17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"></path></svg></span>' +
          '</div>' +
          '<div class="reacg-modal__body">' +
            '<div class="reacg-modal__layout">' +
              '<div class="reacg-modal__layout-content">' +
                '<div class="reacg-attachment-modal__preview">' + previewMedia + '</div>' +
                '<div class="reacg-attachment-modal__details">' +
                  (filename ? '<p><strong>' + reacg_escape_html(reacg.attachment_filename) + ':</strong> ' + reacg_escape_html(filename) + '</p>' : '') +
                  (dimensions ? '<p><strong>' + reacg_escape_html(reacg.attachment_dimensions) + ':</strong> ' + reacg_escape_html(dimensions) + 'px</p>' : '') +
                  (fileSize ? '<p><strong>' + reacg_escape_html(reacg.attachment_file_size) + ':</strong> ' + reacg_escape_html(fileSize) + '</p>' : '') +
                  '<p class="reacg-attachment-modal__edit-link"><a href="' + reacg_escape_html(editLink) + '" target="_blank" rel="noopener noreferrer">' + reacg.edit + '</a></p>' +
                '</div>' +
              '</div>' +
              '<div class="reacg-modal__layout-content">' +
                '<div class="reacg-attachment-modal__field" data-setting="title"><label for="reacg-attachment-title">' + reacg_escape_html(reacg.attachment_title) + '</label><input type="text" id="reacg-attachment-title" class="reacg-attachment-modal__input" value="' + reacg_escape_html(reacg_get_attachment_field_value(attachment, 'title')) + '" /></div>' +
                '<div class="reacg-attachment-modal__field" data-setting="caption"><label for="reacg-attachment-caption">' + reacg_escape_html(reacg.attachment_caption) + '</label><textarea id="reacg-attachment-caption" rows="3">' + reacg_escape_html(reacg_get_attachment_field_value(attachment, 'caption')) + '</textarea></div>' +
                '<div class="reacg-attachment-modal__field" data-setting="alt"><label for="reacg-attachment-alt">' + reacg_escape_html(reacg.attachment_alt_text) + '</label><input type="text" id="reacg-attachment-alt" class="reacg-attachment-modal__input" value="' + reacg_escape_html(reacg_get_attachment_field_value(attachment, 'alt')) + '" /></div>' +
                '<div class="reacg-attachment-modal__field" data-setting="description"><label for="reacg-attachment-description">' + reacg_escape_html(reacg.attachment_description) + '</label><textarea id="reacg-attachment-description" rows="4">' + reacg_escape_html(reacg_get_attachment_field_value(attachment, 'description')) + '</textarea></div>' +
                '<div class="reacg-attachment-modal__field" data-setting="action_url"><label for="reacg-attachment-action-url">' + reacg_escape_html(reacg.attachment_action_url) + '</label><input type="url" id="reacg-attachment-action-url" class="reacg-attachment-modal__input" value="' + reacg_escape_html(reacg_get_attachment_field_value(attachment, 'action_url')) + '" placeholder="https://example.com" /></div>' +
                '<div class="reacg-attachment-modal__field" data-setting="exif"><label for="reacg-attachment-exif">' + reacg_escape_html(reacg.attachment_metadata_exif) + '</label><textarea id="reacg-attachment-exif" rows="5">' + reacg_escape_html(reacg_get_attachment_field_value(attachment, 'exif')) + '</textarea></div>' +
                '<div class="reacg-attachment-modal__field reacg-hidden" data-setting="url"><label for="reacg-attachment-url">' + reacg_escape_html(reacg.attachment_url) + '</label><input type="hidden" id="reacg-attachment-url" class="reacg-attachment-modal__input" value="' + reacg_escape_html(reacg_get_attachment_field_value(attachment, 'url')) + '" /></div>' +
              '</div>' +
            '</div>' +
            '<div><p class="reacg-modal-error-note hidden"></p></div>' +
          '</div>' +
          '<div class="reacg-modal__footer"><div class="reacg-modal-buttons-wrapper"><span class="spinner"></span><button class="reacg-modal-button-save button button-primary button-large">' + reacg.update + '</button></div></div>' +
        '</div>' +
      '</div>' +
    '</div>');
}

function reacg_open_attachment_edit_modal(button, attachment) {
  const modal = reacg_attachment_edit_modal(attachment);
  jQuery('body').append(modal);
  modal.css('display', 'flex').show();
  reacg_add_ai_button_to(jQuery('.reacg-modal__layout-content'));

  const closeModal = function () {
    modal.remove();
  };

  modal.find('.reacg-modal-close').on('click', closeModal);
  modal.on('click', function (e) {
    if ( e.target === this ) {
      closeModal();
    }
  });
  modal.find('.reacg-modal-wrapper').on('click', function (e) {
    e.stopPropagation();
  });

  modal.find('.reacg-modal-button-save').on('click', function () {
    const saveButton = jQuery(this);
    const spinner = saveButton.closest('.reacg-modal-buttons-wrapper').find('> .spinner').first();
    const errorNoteCont = modal.find('.reacg-modal-error-note');
    const attachmentId = attachment.get('id');
    const coreChanges = {
      title: modal.find('#reacg-attachment-title').val(),
      caption: modal.find('#reacg-attachment-caption').val(),
      alt: modal.find('#reacg-attachment-alt').val(),
      description: modal.find('#reacg-attachment-description').val(),
    };
    const compatChanges = {
      attachments: {}
    };
    compatChanges.attachments[attachmentId] = {
      action_url: modal.find('#reacg-attachment-action-url').val(),
      exif: modal.find('#reacg-attachment-exif').val(),
    };

    const normalizeValue = function (value) {
      return value == null ? '' : String(value);
    };
    const hasCoreChanges = normalizeValue(coreChanges.title) !== normalizeValue(reacg_get_attachment_field_value(attachment, 'title'))
      || normalizeValue(coreChanges.caption) !== normalizeValue(reacg_get_attachment_field_value(attachment, 'caption'))
      || normalizeValue(coreChanges.alt) !== normalizeValue(reacg_get_attachment_field_value(attachment, 'alt'))
      || normalizeValue(coreChanges.description) !== normalizeValue(reacg_get_attachment_field_value(attachment, 'description'));
    const hasCompatChanges = normalizeValue(compatChanges.attachments[attachmentId].action_url) !== normalizeValue(reacg_get_attachment_field_value(attachment, 'action_url'))
      || normalizeValue(compatChanges.attachments[attachmentId].exif) !== normalizeValue(reacg_get_attachment_field_value(attachment, 'exif'));

    if ( !hasCoreChanges && !hasCompatChanges ) {
      closeModal();
      button.removeAttr('disabled');
      return;
    }

    errorNoteCont.addClass('hidden').empty();
    saveButton.attr('disabled', true);
    spinner.addClass('is-active');

    const saveRequests = [];
    if ( hasCoreChanges ) {
      saveRequests.push(attachment.save(coreChanges));
    }
    if ( hasCompatChanges ) {
      saveRequests.push(attachment.saveCompat(compatChanges));
    }

    const saveRequest = saveRequests.length === 1
      ? saveRequests[0]
      : jQuery.when.apply(jQuery, saveRequests);

    saveRequest.done(function () {
      reacg_reload_preview();
      closeModal();
    }).fail(function (response) {
      const message = response && response.responseJSON && response.responseJSON.data && response.responseJSON.data.message
        ? response.responseJSON.data.message
        : reacg.save_failed;
      errorNoteCont.removeClass('hidden').html(message);
    }).always(function () {
      spinner.removeClass('is-active');
      saveButton.removeAttr('disabled');
      button.removeAttr('disabled');
    });
  });

  return modal;
}

function reacg_ai_button() {
  return jQuery('<button class="reacg-ai-button" title="' + reacg.generate + '">' + reacg_ai_icon() + '</button>');
}

function reacg_open_ai_generate_content_modal(images, options) {
  const imagesData = images && typeof images === 'object' ? images : {};
  const chunkObject = function (source, size) {
    const entries = Object.entries(source || {});
    const chunks = [];

    for ( let i = 0; i < entries.length; i += size ) {
      chunks.push(Object.fromEntries(entries.slice(i, i + size)));
    }

    return chunks;
  };

  const runBulkGenerateInChunks = function (state, chunkSize, onProgress) {
    const imageEntries = Object.entries(imagesData);
    const deferred = jQuery.Deferred();
    const responses = [];
    const errors = [];
    const notifyProgress = typeof onProgress === 'function' ? onProgress : function () {};

    const readPayloadCode = function (payload) {
      const source = payload || {};

      if ( source.responseJSON ) {
        if ( source.responseJSON.code ) {
          return String(source.responseJSON.code);
        }
        if ( source.responseJSON.data && source.responseJSON.data.code ) {
          return String(source.responseJSON.data.code);
        }
        if ( source.responseJSON.error_data && typeof source.responseJSON.error_data === 'object' ) {
          const errorDataKeys = Object.keys(source.responseJSON.error_data);
          if ( errorDataKeys.length ) {
            return String(errorDataKeys[0]);
          }
        }
        if ( source.responseJSON.errors && typeof source.responseJSON.errors === 'object' ) {
          const errorKeys = Object.keys(source.responseJSON.errors);
          if ( errorKeys.length ) {
            return String(errorKeys[0]);
          }
        }
      }

      if ( source.responseText && typeof source.responseText === 'string' ) {
        try {
          const parsed = JSON.parse(source.responseText);
          if ( parsed && parsed.code ) {
            return String(parsed.code);
          }
          if ( parsed && parsed.data && parsed.data.code ) {
            return String(parsed.data.code);
          }
          if ( parsed && parsed.error_data && typeof parsed.error_data === 'object' ) {
            const errorDataKeys = Object.keys(parsed.error_data);
            if ( errorDataKeys.length ) {
              return String(errorDataKeys[0]);
            }
          }
          if ( parsed && parsed.errors && typeof parsed.errors === 'object' ) {
            const errorKeys = Object.keys(parsed.errors);
            if ( errorKeys.length ) {
              return String(errorKeys[0]);
            }
          }
        }
        catch (ignored) {}
      }

      return '';
    };

    const isNothingToGeneratePayload = function (payload) {
      return readPayloadCode(payload) === 'nothing_to_generate';
    };

    if ( !imageEntries.length ) {
      notifyProgress(0, 0);
      deferred.resolve({ responses: [], errors: [], images: imagesData, closeOnStatus200: false });
      return deferred.promise();
    }

    notifyProgress(0, imageEntries.length);

    const normalizeGeneratedResponse = function (response) {
      let responseObject = response;
      if ( typeof responseObject === 'string' ) {
        try {
          responseObject = JSON.parse(responseObject);
        }
        catch (e) {
          responseObject = {};
        }
      }

      if ( responseObject && responseObject.responseJSON ) {
        responseObject = responseObject.responseJSON;
      }

      const source = responseObject && typeof responseObject === 'object'
        ? (responseObject.data && typeof responseObject.data === 'object' ? responseObject.data : responseObject)
        : {};

      const normalized = {};
      ['title', 'alt', 'caption', 'description'].forEach(function (fieldName) {
        if ( Object.prototype.hasOwnProperty.call(source, fieldName) ) {
          normalized[fieldName] = source[fieldName] == null ? '' : String(source[fieldName]);
        }
      });

      return normalized;
    };

    const applyGeneratedDataToImage = function (imageId, imageData, generatedFields) {
      const baseImage = imageData && typeof imageData === 'object' ? imageData : {};
      const nextImage = jQuery.extend({}, baseImage);
      let changed = false;

      ['title', 'alt', 'caption', 'description'].forEach(function (fieldName) {
        if ( !Object.prototype.hasOwnProperty.call(generatedFields, fieldName) ) {
          return;
        }

        nextImage[fieldName] = generatedFields[fieldName];
        changed = true;
      });

      if ( changed ) {
        imagesData[imageId] = nextImage;
      }

      return nextImage;
    };

    const saveGeneratedDataToAttachment = function (imageId, previousImageData, generatedFields) {
      const deferredSave = jQuery.Deferred();
      const normalizedImageId = parseInt(imageId, 10);
      const saveData = {};
      const normalizeValue = function (value) {
        return value == null ? '' : String(value);
      };

      ['title', 'alt', 'caption', 'description'].forEach(function (fieldName) {
        if ( !Object.prototype.hasOwnProperty.call(generatedFields, fieldName) ) {
          return;
        }

        const previousValue = normalizeValue(previousImageData && previousImageData[fieldName]);
        const nextValue = normalizeValue(generatedFields[fieldName]);
        if ( previousValue !== nextValue ) {
          saveData[fieldName] = nextValue;
        }
      });

      if ( !Object.keys(saveData).length ) {
        deferredSave.resolve();
        return deferredSave.promise();
      }

      if ( !normalizedImageId ) {
        deferredSave.resolve();
        return deferredSave.promise();
      }

      if ( !wp || !wp.media || typeof wp.media.attachment !== 'function' ) {
        deferredSave.resolve();
        return deferredSave.promise();
      }

      const attachment = wp.media.attachment(normalizedImageId);
      if ( !attachment || typeof attachment.save !== 'function' ) {
        deferredSave.resolve();
        return deferredSave.promise();
      }

      const persist = function () {
        attachment.save(saveData)
          .done(function (response) {
            deferredSave.resolve(response);
          })
          .fail(function (xhr) {
            deferredSave.reject(xhr);
          });
      };

      if ( typeof attachment.fetch === 'function' ) {
        attachment.fetch()
          .done(function () {
            persist();
          })
          .fail(function (xhr) {
            deferredSave.reject(xhr);
          });
      }
      else {
        persist();
      }

      return deferredSave.promise();
    };

    const runChunk = function (index) {
      if ( index >= imageEntries.length ) {
        notifyProgress(imageEntries.length, imageEntries.length);
        if ( errors.length ) {
          deferred.reject({ responses: responses, errors: errors, images: imagesData });
          return;
        }

        const successCount = responses.filter(function (entry) {
          return parseInt(entry.statusCode, 10) === 200;
        }).length;
        const closeOnStatus200 = successCount > 0;
        deferred.resolve({
          responses: responses,
          errors: [],
          images: imagesData,
          closeOnStatus200: closeOnStatus200,
          successCount: successCount,
        });
        return;
      }

      const imageEntry = imageEntries[index];
      const imageId = imageEntry[0];
      const imageData = imageEntry[1];
      const singleImageData = imageData && typeof imageData === 'object' ? imageData : {};

      notifyProgress(index + 1, imageEntries.length);

      jQuery.ajax({
        type: 'GET',
        url: reacg.core_rest_url_v2 + 'ai',
        contentType: 'application/json',
        data: {
          image_id: imageId,
          image_url: singleImageData.url || '',
          title: singleImageData.title || '',
          alt: singleImageData.alt || '',
          caption: singleImageData.caption || '',
          description: singleImageData.description || '',
          website_language: reacg.website_language || '',
          user_language: reacg.user_language || document.documentElement.lang || '',
          fields: JSON.stringify(state.fields),
          overwrite_mode: state.overwriteMode,
          action: 'get_content',
        },
      }).done(function (response, textStatus, xhr) {
        const statusCode = xhr && typeof xhr.status !== 'undefined' ? parseInt(xhr.status, 10) : 0;
        const previousImageData = jQuery.extend({}, singleImageData);
        const generatedFields = normalizeGeneratedResponse(response);
        const updatedImageData = applyGeneratedDataToImage(imageId, singleImageData, generatedFields);

        saveGeneratedDataToAttachment(imageId, previousImageData, generatedFields)
          .done(function (saveResponse) {
            responses.push({
              index: index,
              imageId: imageId,
              statusCode: statusCode,
              response: response,
              saveResponse: saveResponse,
              generated: generatedFields,
              image: updatedImageData,
            });
          })
          .fail(function (xhr) {
            errors.push({ index: index, imageId: imageId, stage: 'save', xhr: xhr });
          })
          .always(function () {
            if ( errors.length ) {
              deferred.reject({ responses: responses, errors: errors, images: imagesData });
              return;
            }

            runChunk(index + 1);
          });
      }).fail(function (xhr) {
        if ( isNothingToGeneratePayload(xhr) ) {
          responses.push({
            index: index,
            imageId: imageId,
            statusCode: xhr && typeof xhr.status !== 'undefined' ? parseInt(xhr.status, 10) : 400,
            response: xhr,
            skipped: true,
            image: singleImageData,
          });
          runChunk(index + 1);
          return;
        }

        errors.push({ index: index, imageId: imageId, stage: 'generate', xhr: xhr });
        deferred.reject({ responses: responses, errors: errors, images: imagesData });
      });
    };

    runChunk(0);

    return deferred.promise();
  };

  const settings = jQuery.extend(true, {
    chunkSize: 5,
    fields: {
      title: true,
      alt: false,
      caption: false,
      description: false,
    },
    overwriteMode: 'empty',
    onGenerate: null,
  }, options || {});

  const checkedAttr = function (value) {
    return value ? ' checked="checked"' : '';
  };
  const selectedOverwrite = settings.overwriteMode === 'replace' ? 'replace' : 'empty';

  const modal = jQuery('' +
    '<div class="reacg-modal reacg-ai-generate-modal" style="display:none;">' +
      '<div class="reacg-modal-wrapper">' +
        '<div class="reacg-modal-content">' +
          '<div class="reacg-modal__header">' +
            '<h1>' + reacg_escape_html(reacg.ai_generate_content) + '</h1>' +
            '<span class="reacg-modal-close"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M19 6.41 17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"></path></svg></span>' +
          '</div>' +
          '<div class="reacg-modal__body">' +
            '<div class="reacg-modal__layout-content">' +
              '<div><p class="reacg-modal-note">' + reacg_info_icon() + reacg_escape_html(reacg.content_generated_based_on_image) + '</p></div>' +
              '<h3>' + reacg_escape_html(reacg.choose_what_to_generate) + '</h3>' +
              '<div class="reacg-ai-generate-modal__fields">' +
                '<label><input type="checkbox" name="title" value="1"' + checkedAttr(settings.fields.title) + ' /><span class="reacg-ai-generate-modal__field-name">' + reacg_escape_html(reacg.attachment_title) + '</span></label>' +
                '<label><input type="checkbox" name="caption" value="1"' + checkedAttr(settings.fields.caption) + ' /><span class="reacg-ai-generate-modal__field-name">' + reacg_escape_html(reacg.attachment_caption) + '</span></label>' +
                '<label><input type="checkbox" name="alt" value="1"' + checkedAttr(settings.fields.alt) + ' /><span class="reacg-ai-generate-modal__field-name">' + reacg_escape_html(reacg.attachment_alt_text) + '</span></label>' +
                '<label><input type="checkbox" name="description" value="1"' + checkedAttr(settings.fields.description) + ' /><span class="reacg-ai-generate-modal__field-name">' + reacg_escape_html(reacg.attachment_description) + '</span></label>' +
              '</div>' +
              '<h3>' + reacg_escape_html(reacg.overwrite_existing) + '</h3>' +
              '<div class="reacg-ai-generate-modal__overwrite">' +
                '<label><input type="radio" name="reacg-overwrite" value="empty"' + checkedAttr(selectedOverwrite === 'empty') + ' /><span>' + reacg_escape_html(reacg.only_empty_fields) + '</span></label>' +
                '<label><input type="radio" name="reacg-overwrite" value="replace"' + checkedAttr(selectedOverwrite === 'replace') + ' /><span>' + reacg_escape_html(reacg.replace_existing) + '</span></label>' +
              '</div>' +
              '<p class="reacg-ai-generate-modal__notice hidden"></p>' +
            '</div>' +
          '</div>' +
          '<div class="reacg-modal__footer">' +
            '<div class="reacg-modal-buttons-wrapper">' +
              '<span class="reacg-ai-generate-modal__progress hidden"></span>' +
              '<span class="spinner"></span>' +
              '<button class="button button-primary button-large reacg-modal-button-generate">' + reacg_ai_icon() + reacg_escape_html(reacg.generate) + '</button>' +
            '</div>' +
          '</div>' +
        '</div>' +
      '</div>' +
    '</div>');

  const getState = function () {
    return {
      fields: {
        title: modal.find('input[name="title"]').is(':checked'),
        alt: modal.find('input[name="alt"]').is(':checked'),
        caption: modal.find('input[name="caption"]').is(':checked'),
        description: modal.find('input[name="description"]').is(':checked'),
      },
      overwriteMode: modal.find('input[name="reacg-overwrite"]:checked').val() || 'empty',
    };
  };

  const closeModal = function () {
    modal.remove();
  };

  jQuery('body').append(modal);
  modal.css('display', 'flex').show();

  modal.find('.reacg-modal-close').on('click', closeModal);
  modal.on('click', function (e) {
    if ( e.target === this ) {
      closeModal();
    }
  });
  modal.find('.reacg-modal-wrapper').on('click', function (e) {
    e.stopPropagation();
  });

  modal.find('.reacg-modal-button-generate').on('click', function (e) {
    e.preventDefault();
    const button = jQuery(this);
    const spinner = modal.find('.reacg-modal__footer .spinner');
    const progressNote = modal.find('.reacg-ai-generate-modal__progress');
    const statusNote = modal.find('.reacg-ai-generate-modal__notice');
    const state = getState();
    const errorText = reacg_escape_html(reacg.no_fields_to_generate);
    let shouldCloseAfterSuccess = false;

    const updateProgress = function (current, total) {
      if ( !total ) {
        progressNote.addClass('hidden').text('');
        return;
      }

      progressNote.text(reacg.processing + ' ' + current + '/' + total).removeClass('hidden');
    };

    const setStatus = function (type, message) {
      const statusMessage = message == null ? '' : String(message);
      const upgradeLinkMarkup = 'reacg-upgrade-link';
      const hasUpgradeLink = statusMessage.indexOf(upgradeLinkMarkup) !== -1;

      statusNote
        .removeClass('hidden reacg-ai-generate-modal__notice--success reacg-ai-generate-modal__notice--error reacg-ai-generate-modal__notice--animate')
        .addClass(type === 'success' ? 'reacg-ai-generate-modal__notice--success' : 'reacg-ai-generate-modal__notice--error');

      if ( hasUpgradeLink ) {
        statusNote.html(statusMessage);
        statusNote.find('.reacg-upgrade-link').off('click').on('click', function (event) {
          event.preventDefault();
          reacg_open_free_trial_offer_dialog({utm_medium: 'ai'});
        });
      }
      else {
        statusNote.text(statusMessage);
      }

      if ( statusNote.length ) {
        const statusNode = statusNote.get(0);
        /* Force reflow, then add class on next frame to ensure animation visibly plays. */
        void statusNode.offsetWidth;
        window.requestAnimationFrame(function () {
          statusNote.addClass('reacg-ai-generate-modal__notice--animate');
        });
      }

      const wrapper = modal.find('.reacg-modal-wrapper').first();
      if ( wrapper.length && statusNote.length ) {
        const wrapperNode = wrapper.get(0);
        const statusNode = statusNote.get(0);
        const wrapperTop = wrapper.offset().top;
        const statusTop = statusNote.offset().top;
        const targetScrollTop = wrapper.scrollTop() + (statusTop - wrapperTop) - 24;

        if ( wrapperNode && typeof wrapperNode.scrollTo === 'function' ) {
          wrapperNode.scrollTo({
            top: Math.max(0, targetScrollTop),
            behavior: 'smooth',
          });
        }
        else {
          wrapper.stop(true).animate({ scrollTop: Math.max(0, targetScrollTop) }, 250);
        }
      }
      else if ( statusNote.length && statusNote.get(0) && typeof statusNote.get(0).scrollIntoView === 'function' ) {
        statusNote.get(0).scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    };

    const readErrorMessageFromResponse = function (payload, fallbackMessage) {
      const fallback = fallbackMessage || errorText;
      const source = payload || {};

      if ( source.responseJSON ) {
        if ( source.responseJSON.data && source.responseJSON.data.message ) {
          return source.responseJSON.data.message;
        }
        if ( source.responseJSON.errors && source.responseJSON.errors.message ) {
          return source.responseJSON.errors.message;
        }
        if ( source.responseJSON.message ) {
          return source.responseJSON.message;
        }
      }

      if ( source.data && source.data.message ) {
        return source.data.message;
      }
      if ( source.errors && source.errors.message ) {
        return source.errors.message;
      }
      if ( source.message ) {
        return source.message;
      }

      if ( source.responseText && typeof source.responseText === 'string' ) {
        try {
          const parsed = JSON.parse(source.responseText);
          if ( parsed && parsed.data && parsed.data.message ) {
            return parsed.data.message;
          }
          if ( parsed && parsed.errors && parsed.errors.message ) {
            return parsed.errors.message;
          }
          if ( parsed && parsed.message ) {
            return parsed.message;
          }
        }
        catch (ignored) {}
      }

      return fallback;
    };

    statusNote
      .addClass('hidden')
      .removeClass('reacg-ai-generate-modal__notice--success reacg-ai-generate-modal__notice--error')
      .text('');

    const defaultGenerateHandler = function (currentState) {
      const parsedChunkSize = parseInt(settings.chunkSize, 10);
      const chunkSize = parsedChunkSize > 0 ? parsedChunkSize : 5;
      return runBulkGenerateInChunks(currentState, chunkSize, updateProgress);
    };

    let result = typeof settings.onGenerate === 'function'
      ? settings.onGenerate(state, modal)
      : defaultGenerateHandler(state);

    if ( result === false ) {
      return;
    }

    if ( !result || typeof result.then !== 'function' ) {
      result = defaultGenerateHandler(state);
    }

    if ( result && typeof result.then === 'function' ) {
      button.attr('disabled', true);
      spinner.addClass('is-active');
      result.done(function (resultData) {
        const canClose = !!(resultData && resultData.closeOnStatus200);
        if ( canClose ) {
          shouldCloseAfterSuccess = true;
          setStatus('success', reacg_escape_html(reacg.content_generated_successfully));
          reacg_reload_preview();
          window.setTimeout(function () {
            closeModal();
          }, 3000);
          return;
        }
        const firstResponse = resultData && resultData.responses && resultData.responses.length
          ? resultData.responses[0].response
          : null;
        const responseErrorMessage = readErrorMessageFromResponse(firstResponse, errorText);
        setStatus('error', responseErrorMessage);
      });
      result.fail(function (errorData) {
        const firstError = errorData && errorData.errors && errorData.errors.length ? errorData.errors[0] : null;
        const xhr = firstError && firstError.xhr ? firstError.xhr : null;
        let message = readErrorMessageFromResponse(xhr || firstError, errorText);

        const successCount = errorData && errorData.responses && errorData.responses.length
          ? errorData.responses.length
          : 0;
        const hasUpgradeLink = String(message || '').indexOf('reacg-upgrade-link') !== -1;
        if ( hasUpgradeLink && successCount > 0 ) {
          message = 'Generated content for ' + successCount + ' image' + (successCount === 1 ? '' : 's') + '. ' + message;
        }

        setStatus('error', message);
      });
      result.always(function () {
        progressNote.addClass('hidden').text('');
        spinner.removeClass('is-active');
        button.removeAttr('disabled');
      });
      return;
    }
  });

  return modal;
}

function reacg_modal(field) {
  return jQuery('' +
    '<div class="reacg-modal" style="display:none;">' +
      '<div class="reacg-modal-wrapper">' +
        '<div class="reacg-modal-content">' +
          '<div class="reacg-modal__header">' +
            '<h1>' + field.title + '</h1>' +
            '<span class="reacg-modal-close"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M19 6.41 17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"></path></svg></span>' +
          '</div>' +
          '<div class="reacg-modal__body">' +
            '<div class="reacg-modal__layout-content">' +
              '<div><p class="reacg-modal-note">' + reacg_info_icon() + field.notice + '</p></div>' +
              '<div>' +
                '<label for="reacg-modal-notes">' + reacg.ai_popup_additional_notes_label + ':</label>' +
                '<textarea class="reacg-modal-notes" id="reacg-modal-notes" rows="2" placeholder="' + reacg.ai_popup_additional_notes_placeholder + '"></textarea>' +
              '</div>' +
              '<div>' +
                '<label for="reacg-modal-generated-text">' + field.label + ':</label>' +
                '<textarea class="reacg-modal-generated-text" id="reacg-modal-reacg-modal-generated-text" rows="5" disabled="disabled"></textarea>' +
              '</div>' +
              '<p class="reacg-modal-error-note hidden"></p>' +
            '</div>' +
          '</div>' +
          '<div class="reacg-modal__footer">' +
            '<div class="reacg-modal-buttons-wrapper">' +
              '<span class="spinner"></span>' +
              '<button class="reacg-modal-button-generate button button-primary button-large">' + reacg_ai_icon() + reacg.generate + '</button>' +
              '<button class="reacg-modal-button-proceed button button-secondary button-large" disabled="disabled">' + reacg.proceed + '</button>' +
            '</div>' +
          '</div>' +
        '</div>' +
      '</div>' +
    '</div>');
}

/**
 * Insert AI button to the given field.
 *
 * @param that
 * @param field
 */
function reacg_add_ai_button(that, field) {
  if ( !that.find('[data-setting="' + field.name + '"] .reacg-ai-button').length ) {
    /* Create an AI button.*/
    const button = reacg_ai_button();
    const spinner = '<span class="spinner reacg-float-none"></span>';
    const url_cont = that.find('[data-setting="url"]');
    that.find('[data-setting="' + field.name + '"] label').after(button, spinner);
    const spinnerCont = that.find('[data-setting="' + field.name + '"] .spinner');

    /* Show tooltip for the first field.*/
    if ( field.name === "title" ) {
      if ( !localStorage.getItem("reacg-highlight-ai-alt-generation") ) {
        reacg_show_tooltip(jQuery(".media-frame-content"), '[data-setting="' + field.name + '"] .reacg-ai-button', jQuery(".media-frame-content").find(".media-sidebar"), reacg.ai_highlight);
        localStorage.setItem("reacg-highlight-ai-alt-generation", true);
      }
    }

    button.on('click', function() {
      spinnerCont.addClass("is-active");

      jQuery.ajax({
        type: "GET",
        url: reacg.core_rest_url_v2 + "ai",
        contentType: "application/json",
        data: {
          "action": "check",
        },
        complete: function (response) {
          if (response.status === 200) {
            /* Create modal if not exist and open.*/
            if (!jQuery("body").find(".reacg-modal:not(.reacg-attachment-modal)").length) {
              const modal = reacg_modal(field);
              jQuery("body").append(modal);
              const modalSpinnerCont = modal.find(".reacg-modal-buttons-wrapper .spinner");
              const generatedText = modal.find(".reacg-modal-generated-text");
              const generateButton = modal.find(".reacg-modal-button-generate");
              const proceedButton = modal.find(".reacg-modal-button-proceed");
              const errorNoteCont = modal.find(".reacg-modal-error-note");
              modal.find(".reacg-modal-close, .reacg-modal").on('click', function () {
                modal.remove();
              });
              modal.on('click', function () {
                modal.remove();
              });
              modal.find(".reacg-modal-wrapper").on("click", function (e) {
                e.preventDefault();
                e.stopPropagation();
                return false;
              });
              generateButton.on("click", function () {
                errorNoteCont.addClass("hidden");
                modalSpinnerCont.addClass("is-active");
                generatedText.attr("disabled", "disabled");
                generateButton.attr("disabled", "disabled");
                /* Perform AJAX request to generate AI text.*/
                jQuery.ajax({
                  type: "GET",
                  url: reacg.core_rest_url_v2 + "ai",
                  contentType: "application/json",
                  data: {
                    "image_url": url_cont.find("input").val(),
                    "notes": modal.find(".reacg-modal-notes").val(),
                    "website_language": reacg.website_language || '',
                    "user_language": reacg.user_language || document.documentElement.lang || '',
                    "action": field.action,
                  },
                  complete: function (response) {
                    modalSpinnerCont.removeClass("is-active");
                    generatedText.removeAttr("disabled");
                    generateButton.removeAttr("disabled");
                    if (response.status === 200 && response.success && response.responseJSON) {
                      generatedText.removeAttr("disabled").val(response.responseJSON);
                      proceedButton.removeAttr("disabled");
                      generateButton.html(reacg_ai_icon() + reacg.regenerate);
                    }
                    else {
                      const errorMessage = response && response.responseJSON && response.responseJSON.errors
                        ? response.responseJSON.errors.message
                        : '';
                      const hasUpgradeLink = String(errorMessage || '').indexOf('reacg-upgrade-link') !== -1;

                      errorNoteCont.removeClass("hidden").html(errorMessage || '');
                      if ( hasUpgradeLink ) {
                        errorNoteCont.find('.reacg-upgrade-link').off('click').on('click', function (event) {
                          event.preventDefault();
                          reacg_open_free_trial_offer_dialog({utm_medium: 'ai_generate'});
                        });
                      }

                      const wrapper = modal.find('.reacg-modal-wrapper').first();
                      if ( wrapper.length && errorNoteCont.length ) {
                        const wrapperNode = wrapper.get(0);
                        const wrapperTop = wrapper.offset().top;
                        const errorTop = errorNoteCont.offset().top;
                        const targetScrollTop = wrapper.scrollTop() + (errorTop - wrapperTop) - 24;

                        if ( wrapperNode && typeof wrapperNode.scrollTo === 'function' ) {
                          wrapperNode.scrollTo({
                            top: Math.max(0, targetScrollTop),
                            behavior: 'smooth',
                          });
                        }
                        else {
                          wrapper.stop(true).animate({ scrollTop: Math.max(0, targetScrollTop) }, 250);
                        }
                      }
                      else if ( errorNoteCont.length && errorNoteCont.get(0) && typeof errorNoteCont.get(0).scrollIntoView === 'function' ) {
                        errorNoteCont.get(0).scrollIntoView({ behavior: 'smooth', block: 'center' });
                      }
                    }
                  }
                });
              });
              proceedButton.on("click", function () {
                jQuery(that).find('[data-setting="' + field.name + '"]').find('textarea, input').first().val(generatedText.val());
                modal.remove();
              });
            }
            jQuery(".reacg-modal:not(.reacg-attachment-modal)").css("display", "flex").show();
          }
          spinnerCont.removeClass("is-active");
        }
      });
    });
  }
}

/**
 * Add AI buttons to the specified fields.
 */
function reacg_add_ai_button_to_uploader() {
  wp.media.view.Attachment.Details.prototype.render = _.wrap(wp.media.view.Attachment.Details.prototype.render, function(render) {
    render.apply(this, _.rest(arguments));

    reacg_add_ai_button_to(this.$el);

    return this;
  });
}

function reacg_add_ai_button_to(el) {
  const add_button_to = {
    alt: {
      name: "alt",
      action: "get_alt",
      title: reacg.ai_popup_alt_heading,
      notice: reacg.ai_popup_alt_notice,
      label: reacg.ai_popup_alt_field_label,
    },
    description: {
      name: "description",
      action: "get_description",
      title: reacg.ai_popup_description_heading,
      notice: reacg.ai_popup_description_notice,
      label: reacg.ai_popup_description_field_label,
    },
    title: {
      name: "title",
      action: "get_title",
      title: reacg.ai_popup_title_heading,
      notice: reacg.ai_popup_title_notice,
      label: reacg.ai_popup_title_field_label,
    },
    caption: {
      name: "caption",
      action: "get_caption",
      title: reacg.ai_popup_caption_heading,
      notice: reacg.ai_popup_caption_notice,
      label: reacg.ai_popup_caption_field_label,
    }
  }

  for ( let i in add_button_to ) {
    reacg_add_ai_button(el, add_button_to[i]);
  }
}

function reacg_show_tooltip(parent, selectorOrEl, containerToBeScrolled, text) {
  const el = typeof selectorOrEl === 'string' ? parent.find(selectorOrEl) : selectorOrEl;
  if ( !el.length ) {
    /* Retry if element is not yet in DOM.*/
    setTimeout(() => reacg_show_tooltip(parent, selectorOrEl, containerToBeScrolled, text), 200);

    return;
  }

  if ( !containerToBeScrolled.length ) {
    return;
  }
  const containerTop = containerToBeScrolled.offset().top;
  const targetTop = el.offset().top;
  const scrollPosition = containerToBeScrolled.scrollTop() + (targetTop - containerTop);
  containerToBeScrolled.animate({ scrollTop: scrollPosition }, 300, () => {
    /* After scroll completes, show tooltip.*/
    reacg_tooltip(el, text);
  });
}

function reacg_tooltip_icon() {
  return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" font-size="30px" width="1em" height="1em" fill="yellow"><path d="m11.864,4.001c-4.184.069-7.709,3.526-7.858,7.705-.088,2.428.914,4.733,2.75,6.326.791.687,1.244,1.743,1.244,2.968,0,1.654,1.346,3,3,3h2c1.654,0,3-1.346,3-3v-.375c0-.966.455-1.898,1.282-2.626,1.728-1.518,2.718-3.704,2.718-5.999,0-2.161-.849-4.187-2.39-5.703-1.541-1.516-3.583-2.345-5.746-2.296Zm2.136,16.999c0,.552-.448,1-1,1h-2c-.552,0-1-.448-1-1.069,0-.316-.031-.626-.077-.931h4.118c-.025.206-.041.415-.041.625v.375Zm1.962-4.503c-.511.449-.923.957-1.24,1.503h-1.722v-4.184c1.161-.414,2-1.514,2-2.816,0-.553-.447-1-1-1s-1,.447-1,1-.448,1-1,1-1-.448-1-1-.447-1-1-1-1,.447-1,1c0,1.302.839,2.402,2,2.816v4.184h-1.746c-.31-.558-.707-1.06-1.188-1.478-1.376-1.195-2.128-2.924-2.062-4.744.112-3.134,2.756-5.726,5.894-5.777.034,0,.067,0,.102,0,1.586,0,3.077.609,4.208,1.723,1.156,1.137,1.793,2.656,1.793,4.277,0,1.72-.743,3.358-2.038,4.497Zm.823-14.023l1.235-2.01c.288-.472.904-.619,1.375-.328.471.289.618.904.328,1.375l-1.235,2.01c-.188.308-.517.477-.853.477-.179,0-.359-.048-.522-.148-.471-.289-.618-.904-.328-1.375Zm6.628,4.148l-1.933.872c-.133.061-.273.089-.41.089-.382,0-.745-.219-.912-.589-.228-.503-.004-1.096.5-1.322l1.933-.872c.506-.229,1.096-.003,1.322.5.228.503.004,1.096-.5,1.322ZM4.194,1.51c-.289-.471-.141-1.087.33-1.375.473-.288,1.087-.14,1.375.33l1.232,2.011c.289.471.141,1.087-.33,1.375-.163.1-.344.147-.521.147-.337,0-.665-.17-.854-.478l-1.232-2.011Zm-.483,5.551c-.171.359-.529.568-.902.568-.145,0-.292-.031-.431-.099l-1.798-.861c-.498-.238-.709-.835-.47-1.333.237-.499.837-.712,1.333-.47l1.798.861c.498.238.709.835.47,1.333Z"></path></svg>';
}
function reacg_tooltip(element, text) {
  /* Remove existing tooltip if any.*/
  jQuery(".reacg-tooltip").remove();

  const tooltip = jQuery('<div role="tooltip" class="reacg-tooltip">' +
    '<div>' +
    '<div class="reacg-tooltip-wrapper">' +
    '<span class="reacg-tooltip-icon-wrapper">' + reacg_tooltip_icon() + '</span>' +
    text +
    '</div>' +
    '<span class="reacg-tooltip-arrow" data-popper-placement="left"></span>' +
    '</div>' +
    '</div>');

  jQuery("body").append(tooltip);

  const offset = element.offset();
  const elementHeight = element.outerHeight();
  const tooltipHeight = tooltip.outerHeight();

  tooltip.css({
    left: offset.left - tooltip.outerWidth(),
    top: offset.top - tooltipHeight / 2 + elementHeight / 2
  });

  const removeTooltip = () => {
    jQuery(".reacg-tooltip").remove();
    jQuery("*").off("scroll.reacgTooltip");
    jQuery(window).off("click.reacgTooltip keydown.reacgTooltip resize.reacgTooltip");
  };

  setTimeout(() => {
    /* Remove on ANY scroll (including divs).*/
    jQuery("*").on("scroll.reacgTooltip", removeTooltip);
    /* Also remove on common user interactions.*/
    jQuery(window).on("click.reacgTooltip keydown.reacgTooltip resize.reacgTooltip", removeTooltip);
  }, 200); /* Small delay to ensure tooltip is in DOM.*/
}