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
    else if ( reacg.allowed_post_types.hasOwnProperty(type)
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
      media_uploader.on("close", function () {
        media_uploader.remove();
      });
      media_uploader.open();
    }
    else {
      /* If image or video edited.*/
      if ( jQuery(this).attr('disabled') ) {
        /* To prevent multiple clicks.*/
        e.preventDefault();
        e.stopPropagation();
        return false;
      }

      jQuery(this).attr("disabled", true);
      const that = this;
      /* Fetch the attachment.*/
      const attachment = wp.media.attachment(image_id);
      attachment.fetch().then(function () {
        jQuery(that).removeAttr("disabled");
        /* Create a custom Attachments collection containing only the given image.*/
        const attachments = new wp.media.model.Attachments([attachment], {
          query: false
        });
        /* Create a custom state with that one image.*/
        const LibraryState = wp.media.controller.Library.extend({
          defaults: _.defaults({
            id: 'custom-library',
            title: reacg.edit,
            toolbar: 'select',
            filterable: false,
            multiple: false,
            library: attachments
          }, wp.media.controller.Library.prototype.defaults)
        });
        /* Create the media frame with custom state.*/
        const media_uploader = wp.media({
          frame: 'select',
          button: {text: reacg.update},
          state: 'custom-library',
          states: [
            new LibraryState()
          ]
        });
        media_uploader.on('open', function () {
          /* Change the active tab to the Media Library.*/
          jQuery('#menu-item-browse').trigger('click');

          let selection = media_uploader.state().get('selection');
          selection.add(wp.media.attachment(image_id));
        });
        media_uploader.on('select', function () {
          reacg_reload_preview();
          media_uploader.remove();
        });
        media_uploader.on('close', function () {
          media_uploader.remove();
        });
        media_uploader.open();
      });
    }
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

  reacg_add_ai_button_to_uploader();
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


function reacg_ai_icon() {
  return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">' +
    '<path fill="#FFFFFF" d="m19.026,12v6c0,.552-.448,1-1,1s-1-.448-1-1v-6c0-.552.448-1,1-1s1,.448,1,1Zm-7.42-5.283l3.071,11.029c.175.63-.298,1.254-.953,1.254-.443,0-.831-.294-.952-.72l-.643-2.28h-5.206l-.643,2.28c-.12.426-.509.72-.952.72h0c-.654,0-1.128-.624-.953-1.254l3.091-11.108c.141-.608.541-1.12,1.098-1.405.568-.292,1.22-.31,1.839-.05.587.246,1.037.817,1.204,1.535Zm-.041,7.283l-1.929-6.835c-.029-.114-.191-.114-.219,0l-1.929,6.835h4.077Zm11.462-4c-.552,0-1,.448-1,1v8c0,1.654-1.346,3-3,3H5.026c-1.654,0-3-1.346-3-3V5c0-1.654,1.346-3,3-3h8c.552,0,1-.448,1-1S13.578,0,13.026,0H5.026C2.269,0,.026,2.243.026,5v14c0,2.757,2.243,5,5,5h14c2.757,0,5-2.243,5-5v-8c0-.552-.448-1-1-1Zm-6.85-4.82l1.868.787.745,1.865c.161.404.552.668.987.668s.825-.265.987-.668l.741-1.854,1.854-.741c.404-.161.668-.552.668-.987s-.265-.825-.668-.987l-1.854-.741-.741-1.854C20.601.265,20.21,0,19.776,0s-.825.265-.987.668l-.737,1.843-1.84.697c-.406.154-.678.54-.686.974-.008.435.25.83.65.999Z"/>' +
    '</svg>';
}

function reacg_info_icon() {
  return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12,0A12,12,0,1,0,24,12,12.013,12.013,0,0,0,12,0Zm0,21a9,9,0,1,1,9-9A9.011,9.011,0,0,1,12,21Z"/><path d="M11.545,9.545h-.3A1.577,1.577,0,0,0,9.64,10.938,1.5,1.5,0,0,0,11,12.532v4.65a1.5,1.5,0,0,0,3,0V12A2.455,2.455,0,0,0,11.545,9.545Z"/><path d="M11.83,8.466A1.716,1.716,0,1,0,10.114,6.75,1.715,1.715,0,0,0,11.83,8.466Z"/></svg>';
}

function reacg_ai_button() {
  return jQuery('<button class="reacg-ai-button" title="' + reacg.generate + '">' + reacg_ai_icon() + '</button>');
}

function reacg_modal(field) {
  return jQuery('' +
    '<div class="reacg-modal" style="display:none;">' +
    '<div class="reacg-modal-wrapper">' +
    '<span class="reacg-modal-close"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M19 6.41 17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"></path></svg></span>' +
    '<div class="reacg-modal-content">' +
    '<h1>' + field.title + '</h1>' +
    '<div>' +
    '<p class="reacg-modal-note">' + reacg_info_icon() + reacg.ai_popup_note + '</p>' +
    '</div>' +
    '<div>' +
    '<label for="reacg-modal-notes">' + reacg.ai_popup_additional_notes_label + ':</label>' +
    '<textarea class="reacg-modal-notes" id="reacg-modal-notes" rows="2" placeholder="' + reacg.ai_popup_additional_notes_placeholder + '"></textarea>' +
    '</div>' +
    '<div>' +
    '<label for="reacg-modal-generated-text">' + field.label + ':</label>' +
    '<textarea class="reacg-modal-generated-text" id="reacg-modal-reacg-modal-generated-text" rows="5" disabled="disabled"></textarea>' +
    '</div>' +
    '<div class="reacg-modal-buttons-wrapper">' +
    '<span class="spinner"></span>' +
    '<button class="reacg-modal-button-generate button button-primary button-large">' + reacg_ai_icon() + reacg.generate + '</button>' +
    '<button class="reacg-modal-button-proceed button button-secondary button-large" disabled="disabled">' + reacg.proceed + '</button>' +
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
    const title_cont = that.find('[data-setting="title"]');

    that.find('[data-setting="' + field.name + '"] label').after(button, spinner);
    const spinnerCont = that.find('[data-setting="' + field.name + '"] spinner');

    button.on('click', function() {
      spinnerCont.addClass("is-active");
      /* Add notification if title is empty.*/
      const title = title_cont.find("input").val();
      if ( !title ) {
        spinnerCont.removeClass("is-active");
        title_cont.next(".description.required").remove();
        title_cont.after("<span class='description required'>Title is required. Make sure it accurately describes the image.</span>");
        title_cont.get(0).scrollIntoView({ behavior: 'smooth', block: 'center' });
        title_cont.find("input").focus();

        return;
      }

      /* Create modal if not exist and open.*/
      if ( !jQuery(this).closest(".media-modal").find(".reacg-modal").length ) {
        const modal = reacg_modal(field);
        jQuery(this).closest(".media-modal-content").after(modal);

        const modalSpinnerCont = modal.find(".reacg-modal-buttons-wrapper .spinner");
        const generatedText = modal.find(".reacg-modal-generated-text");
        const generateButton = modal.find(".reacg-modal-button-generate");
        const proceedButton = modal.find(".reacg-modal-button-proceed");

        modal.find(".reacg-modal-close, .reacg-modal").on('click', function() {
          modal.remove();
        });
        modal.on('click', function() {
          modal.remove();
        });
        modal.find(".reacg-modal-wrapper").on("click", function (e) {
          e.preventDefault();
          e.stopPropagation();
          return false;
        });
        generateButton.on("click", function () {
          modalSpinnerCont.addClass("is-active");
          generatedText.attr("disabled", "disabled");
          generateButton.attr("disabled", "disabled");
          /* Perform AJAX request to generate AI text.*/
          jQuery.ajax({
            type: "GET",
            url: "https://regallery.team/core/wp-json/reacgcore/v2/ai",
            //url: "http://localhost/wordpress/wp-json/reacgcore/v2/ai",
            contentType: "application/json",
            data: {
              "title": title,
              "notes": modal.find(".reacg-modal-notes").val(),
              "action": field.action,
            },
            complete: function (response) {
              modalSpinnerCont.removeClass("is-active");
              generatedText.removeAttr("disabled");
              generateButton.removeAttr("disabled");
              if ( response.status === 204 ) {

              }
              else if ( response.success && response.responseJSON ) {
                generatedText.removeAttr("disabled").val(response.responseJSON);
                proceedButton.removeAttr("disabled");
                generateButton.html(reacg_ai_icon() + reacg.regenerate);
              }
              else {
                alert('Error generating description.');
              }
            }
          });
        });
        proceedButton.on("click", function () {
          jQuery(that).find('[data-setting="' + field.name + '"] textarea').val(generatedText.val());
          modal.remove();
        });
      }
      jQuery(".reacg-modal").css("display", "flex").show();
      spinnerCont.removeClass("is-active");
    });
  }
}

/**
 * Add AI buttons to the specified fields.
 */
function reacg_add_ai_button_to_uploader() {
  wp.media.view.Attachment.Details.prototype.render = _.wrap(wp.media.view.Attachment.Details.prototype.render, function(render) {
    render.apply(this, _.rest(arguments));

    const title_cont = this.$el.find('[data-setting="title"]');
    /* Remove required notice on filling.*/
    title_cont.find("input").on("keyup", function() {
      if ( jQuery(this).val() !== "" ) {
        title_cont.next(".description.required").remove();
      }
    });

    const add_button_to = {
      alt: {
        name: "alt",
        action: "get_alt",
        title: reacg.ai_popup_alt_title,
        label: reacg.ai_popup_alt_field_label,
      },
      description: {
        name: "description",
        action: "get_description",
        title: reacg.ai_popup_description_title,
        label: reacg.ai_popup_description_field_label,
      }
    }

    for ( let i in add_button_to ) {
      reacg_add_ai_button(this.$el, add_button_to[i]);
    }

    return this;
  });
}