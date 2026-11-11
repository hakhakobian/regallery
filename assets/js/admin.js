jQuery(document).ready(function () {
  reacg_track_unsaved_changes();

  const max_edit_count = 3;
  const max_minutes_spent = 1;
  if (!localStorage.getItem("reacg-opened-contact-us-dialog")) {
    setTimeout(function () {
      if (jQuery(".reacg_items").data("edit-count") > max_edit_count) {
        const reacg_open_need_help_dialog_exist = setInterval(function () {
          if (typeof reacg_open_need_help_dialog !== "undefined") {
            reacg_open_need_help_dialog({
              onClose: () =>
                localStorage.setItem("reacg-opened-contact-us-dialog", true),
            });
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
    },
  });

  jQuery(document).on("click", ".reacg-license-activate-button", function () {
    const activate = jQuery(this).data("activate");
    const container = jQuery(this).closest("#gallery-license");
    const licenseKey = container.find(".reacg-license-key").val();
    const errorNoteCont = container.find(".reacg-error");

    if (activate && !licenseKey) {
      errorNoteCont.removeClass("hidden").html(reacg.enter_license_key);
      return;
    }

    const spinner = container.find(".spinner");
    spinner.addClass("is-active");

    const button = jQuery(this);
    button.attr("disabled", "disabled");

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
        if (
          response.status === 200 &&
          response.success &&
          response.responseJSON
        ) {
          container.find(".reacg-success").html(response.responseJSON.message);
          reacg_isPro(!!activate);
        } else {
          errorNoteCont
            .removeClass("hidden")
            .html(response.responseJSON.errors.message);
        }
      },
    });
  });

  if (!localStorage.getItem("reacg-opened-contact-us-dialog")) {
    setTimeout(
      function () {
        if (typeof reacg_open_new_here_dialog !== "undefined") {
          reacg_open_new_here_dialog({
            onClose: () =>
              localStorage.setItem("reacg-opened-contact-us-dialog", true),
          });
        }
      },
      max_minutes_spent * 60 * 1000,
    );
  }

  /* Save options on saving the gallery.*/
  reacg_bind_gallery_save_options();
  /* Bind an event to the add image button.*/
  jQuery(document).on("click", ".reacg_item_new", function (event) {
    reacg_media_uploader(event, this);
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
    if (
      reacg.allowed_post_types.hasOwnProperty(type) &&
      String(image_id).includes("dynamic")
    ) {
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
    reacg_update_bulk_toolbar(galleryItemsContainer);
  });

  /* Item selection events */
  jQuery(document).on("click mousedown", ".reacg-item-select", function (e) {
    e.stopPropagation();
  });

  jQuery(document).on("change", ".reacg-item-checkbox", function (e) {
    e.stopPropagation();
    const item = jQuery(this).closest(".reacg_item");
    if (this.checked) {
      item.addClass("reacg-item--selected");
    } else {
      item.removeClass("reacg-item--selected");
    }
    reacg_update_bulk_toolbar(
      item.closest(
        ".reacg_items, #gallery-images, #reacg-gallery-images, .inside",
      ),
    );
  });

  /* Select all checkbox event */
  jQuery(document).on("change", ".reacg-select-all-checkbox", function () {
    const isChecked = this.checked;
    const container = jQuery(this).closest(
      "#gallery-images, #reacg-gallery-images, .inside, body",
    );
    const items = container.find(
      ".reacg_items .reacg_item:not(.reacg_item_new):not(.reacg-template)",
    );
    items.each(function () {
      const item = jQuery(this);
      if (reacg_is_bulk_selectable_item(item)) {
        item.find(".reacg-item-checkbox").prop("checked", isChecked);
        if (isChecked) {
          item.addClass("reacg-item--selected");
        } else {
          item.removeClass("reacg-item--selected");
        }
      }
    });
    reacg_update_bulk_toolbar(container);
  });

  /* Bulk Edit button event */
  jQuery(document).on("click", ".reacg-bulk-edit-btn", function (e) {
    e.preventDefault();
    const btn = jQuery(this);
    if (btn.attr("disabled")) return;

    const root = btn.closest(
      "#gallery-images, #reacg-gallery-images, .inside, body",
    );
    const selectedItems = root
      .find(
        ".reacg_items .reacg_item.reacg-item--selected:not(.reacg_item_new):not(.reacg-template)",
      )
      .filter(function () {
        return reacg_is_bulk_selectable_item(this);
      });
    if (!selectedItems.length) return;

    btn.attr("disabled", true);
    const imageIds = [];
    selectedItems.each(function () {
      const id = jQuery(this).data("id");
      if (id) {
        imageIds.push(id);
      }
    });

    if (!imageIds.length) {
      btn.removeAttr("disabled");
      return;
    }

    const attachments = [];
    const fetchPromises = [];
    imageIds.forEach(function (id) {
      const att = wp.media.attachment(id);
      attachments.push(att);
      if (typeof att.fetch === "function") {
        fetchPromises.push(att.fetch());
      }
    });

    jQuery.when.apply(jQuery, fetchPromises).always(function () {
      btn.removeAttr("disabled");
      reacg_open_bulk_attachment_edit_modal(
        btn,
        attachments,
        selectedItems,
        root.find(".reacg_items"),
      );
    });
  });

  reacg_update_bulk_toolbar();

  /* Bind an edit event to the every image item.*/
  jQuery(document).on("click", ".reacg_item .reacg-edit", function (e) {
    let item = jQuery(this).closest(".reacg_item");
    const galleryItemsContainer = item.closest(".reacg_items");
    /* The image id to be edited.*/
    let image_id = item.data("id");
    let type = item.data("type");

    if (
      reacg.allowed_post_types.hasOwnProperty(type) &&
      String(image_id).includes("dynamic")
    ) {
      /* If dynamic is edited.*/
      let media_uploader = wp.media({
        title: reacg.edit,
        button: { text: reacg.update },
        multiple: false,
      });
      media_uploader.on("open", function () {
        reacg_add_posts_tab(
          media_uploader,
          type.replace("dynamic", ""),
          galleryItemsContainer.data("post-id"),
        );
      });
      media_uploader.on("select", function () {
        const selected_images = media_uploader
          .state()
          .get("selection")
          .toJSON();
        galleryItemsContainer
          .find(".additional_data")
          .val(JSON.stringify(selected_images[0].additional_data));
        reacg_save_images(galleryItemsContainer);
        media_uploader.remove();
      });
      media_uploader.on("close", function () {
        delete wp.Uploader.defaults.multipart_params.reacg;
        delete wp.Uploader.defaults.multipart_params.reacg_nonce;
        media_uploader.remove();
      });
      media_uploader.open();
    } else {
      if (jQuery(this).attr("disabled")) {
        /* To prevent multiple clicks.*/
        e.preventDefault();
        e.stopPropagation();
        return false;
      }

      jQuery(this).attr("disabled", true);
      const editButton = jQuery(this);
      const attachment = wp.media.attachment(image_id);
      attachment
        .fetch()
        .then(function () {
          reacg_open_attachment_edit_modal(editButton, attachment, item);
        })
        .always(function () {
          editButton.removeAttr("disabled");
        });
      return false;
    }
  });

  /* Bind an edit thumbnail event to the every video item.*/
  jQuery(document).on(
    "click",
    ".reacg_item .reacg-edit-thumbnail",
    function () {
      wp.Uploader.defaults.multipart_params =
        wp.Uploader.defaults.multipart_params || {};
      wp.Uploader.defaults.multipart_params.reacg = "gallery";
      wp.Uploader.defaults.multipart_params.reacg_nonce = reacg.nonce;
      let item = jQuery(this).closest(".reacg_item");
      const galleryItemsContainer = item.closest(".reacg_items");
      /* The image id to be edited.*/
      let image_id = item.data("id");

      let media_uploader = wp.media({
        title: reacg.edit_thumbnail,
        library: { type: "image" },
        button: { text: reacg.update_thumbnail },
        multiple: false,
      });
      media_uploader.on("close", function () {
        delete wp.Uploader.defaults.multipart_params.reacg;
        delete wp.Uploader.defaults.multipart_params.reacg_nonce;
        media_uploader.remove();
      });
      media_uploader.open();
      media_uploader.on("select", function () {
        let selected_image = media_uploader.state().get("selection").toJSON();
        if (typeof selected_image[0] !== "undefined") {
          reacg_save_thumbnail(
            galleryItemsContainer,
            image_id,
            selected_image[0].id,
          );
          /* Change the item thumbnail in item view.*/
          let sizes = selected_image[0].sizes;
          let thumbnail_url = reacg.no_image;
          if (typeof sizes.thumbnail !== "undefined") {
            thumbnail_url = sizes.thumbnail.url;
          } else if (typeof sizes.full.url !== "undefined") {
            thumbnail_url = sizes.full.url;
          }
          item
            .find(".reacg_item_image img")
            .attr("src", thumbnail_url)
            .each(function () {
              reacg_update_item_image_fit(this);
            });
        }

        media_uploader.remove();
      });
    },
  );

  jQuery(".reacg_item_image img").each(function () {
    reacg_update_item_image_fit(this);
  });

  reacg_add_ai_button_to_uploader();
});

function reacg_update_item_image_fit(image) {
  const img = jQuery(image);
  const container = img.closest(".reacg_item_image");

  if (!container.length) {
    return;
  }

  const update = function () {
    const imageElement = img[0];
    const isSmallImage =
      imageElement.naturalWidth > 0 &&
      imageElement.naturalHeight > 0 &&
      imageElement.naturalWidth < container.innerWidth() &&
      imageElement.naturalHeight < container.innerHeight();

    img.toggleClass("reacg_item_image_img_natural", isSmallImage);
  };

  img.off("load.reacgImageFit").on("load.reacgImageFit", update);

  if (image.complete && image.naturalWidth) {
    update();
  }
}

function reacg_is_user_pro() {
  try {
    return Boolean(JSON.parse(localStorage.getItem("reacg-pro")));
  } catch (e) {
    return false;
  }
}

window.addEventListener("reacg:pro-activated", function () {
  localStorage.setItem("reacg-pro", true);
  jQuery(".reacg-pro-badge").addClass("hidden");
  jQuery(".reacg-image-converter").each(function () {
    const wrap = jQuery(this);
    reacg_update_converter_disabled_notice(
      wrap,
      wrap.data("currentFormat") || "",
    );
  });
});

function reacg_isPro(isPro) {
  localStorage.setItem("reacg-pro", isPro);
  jQuery(".reacg-pro-badge").toggleClass("hidden", Boolean(isPro));
  if (isPro) {
    jQuery("#reacg-metabox-widget-why-upgrade").remove();
    jQuery("#gallery-custom-css").find(".postbox-header h2 svg").remove();
    window.dispatchEvent(new Event("reacg:pro-activated"));
  }
  jQuery(".reacg-pro-not-active").toggle(!isPro);
  jQuery(".reacg-pro-active").toggle(isPro);
  jQuery("textarea[name=custom_css]").on("input", function () {
    let text = jQuery(this).val();
    if (!isPro && text.length > 100) {
      jQuery(this).val(text.substring(0, 100));
      reacg_open_free_trial_offer_dialog({ utm_medium: "custom_css" });
    }
  });
}

/**
 * Make the image items sortable.
 *
 * @param container
 */
function reacg_make_items_sortable(container) {
  jQuery(container)
    .find(".reacg_items")
    .sortable({
      items: ".reacg-sortable",
      tolerance: "pointer",
      placeholder: "ui-sortable-placeholder reacg_item",
      start: function (event, ui) {
        const itemW = ui.item.outerWidth() || 65;
        const itemH = ui.item.outerHeight() || 65;
        ui.placeholder.css({
          width: itemW + "px",
          height: itemH + "px",
        });
        ui.helper.css({
          width: itemW + "px",
          height: itemH + "px",
          maxWidth: itemW + "px",
          maxHeight: itemH + "px",
        });
      },
      update: function (event, tr) {
        let images_ids = [];
        jQuery(this)
          .find("> .reacg-sortable")
          .each(function () {
            images_ids.push(jQuery(this).data("id"));
          });
        const galleryItemsContainer = jQuery(this);
        reacg_set_image_ids(galleryItemsContainer, images_ids);
        /* Save images on reorder.*/
        reacg_save_images(galleryItemsContainer);
      },
    });
}

function reacg_track_unsaved_changes() {
  /* Track only the newly added not yet saved galleries (not for builders).*/
  if (
    jQuery("#post_type").val() === "reacg" &&
    jQuery("#original_post_status").val() === "auto-draft"
  ) {
    let isEmpty = false;
    const hiddenInput = document.getElementById("images_ids");

    if (hiddenInput) {
      /* Monitor changes (capture phase on document survives .reacg_items replacement).*/
      document.addEventListener(
        "added-images",
        function (e) {
          if (e.target && e.target.id === "images_ids") {
            isEmpty = true;
          }
        },
        true,
      );
      /* Trigger warning if changes are unsaved.*/
      window.onbeforeunload = function () {
        if (isEmpty) {
          return "Changes you made may not be saved.";
        }
      };
      /* Reset isEmpty when the form is submitted.*/
      jQuery("form").on("submit", function () {
        isEmpty = false;
      });
    }
  }
}

/**
 * Save React gallery options before submitting the gallery post form.
 */
function reacg_bind_gallery_save_options() {
  const postForm = document.getElementById("post");
  if (!postForm || jQuery("#post_type").val() !== "reacg") {
    return;
  }

  const galleryId =
    jQuery("#post_ID").val() ||
    jQuery(".reacg-gallery[data-options-section='1']").attr(
      "data-gallery-id",
    ) ||
    jQuery(".reacg_items").data("post-id");

  if (!galleryId) {
    return;
  }

  let optionsLoaded = false;
  let isSavingOptions = false;
  let allowFormSubmit = false;
  let isSubmittingAfterOptionsSave = false;
  let resetSubmittingTimer = null;

  window.reacg_global = window.reacg_global || {};
  const prevOnOptionsChange = window.reacg_global.onOptionsChange;
  window.reacg_global.onOptionsChange = function (options, context) {
    if (typeof prevOnOptionsChange === "function") {
      try {
        prevOnOptionsChange(options, context);
      } catch (e) {}
    }
    if (context && String(context.galleryId) === String(galleryId)) {
      optionsLoaded = true;
    }
  };

  window.addEventListener(
    "beforeunload",
    function (e) {
      if (!isSubmittingAfterOptionsSave) {
        return;
      }
      const api = getSettingsApi();
      if (!api || typeof api.hasChanges !== "function" || !api.hasChanges()) {
        e.stopImmediatePropagation();
      }
    },
    true,
  );

  function getSettingsApi() {
    return (
      (window.reacg_global &&
        window.reacg_global.settings_api &&
        window.reacg_global.settings_api[String(galleryId)]) ||
      null
    );
  }

  function isOptionsReady(api) {
    if (optionsLoaded) {
      return true;
    }
    if (!api || typeof api.getOptions !== "function") {
      return false;
    }
    const currentOptions = api.getOptions();
    return Boolean(
      currentOptions &&
      Object.prototype.hasOwnProperty.call(currentOptions, "template_id"),
    );
  }

  function needsOptionsSave() {
    const api = getSettingsApi();
    if (!api || typeof api.save !== "function") {
      return false;
    }
    if (typeof api.hasChanges === "function" && api.hasChanges()) {
      return true;
    }
    return (
      jQuery("#original_post_status").val() === "auto-draft" &&
      isOptionsReady(api)
    );
  }

  function isButtonDisabled(button) {
    return Boolean(
      button &&
      (button.disabled ||
        button.classList.contains("disabled") ||
        button.classList.contains("button-disabled") ||
        button.classList.contains("button-primary-disabled")),
    );
  }

  function setButtonLoading(button, loading) {
    const isDraftSave = button && button.id === "save-post";
    const spinner = isDraftSave
      ? jQuery(
          "#save-action .spinner, #minor-publishing-actions .spinner",
        ).first()
      : jQuery("#publishing-action .spinner");
    const targetButton =
      button && (button.id === "publish" || button.id === "save-post")
        ? jQuery(button)
        : jQuery("#publishing-action #publish");

    spinner.toggleClass("is-active", loading);
    targetButton.toggleClass("disabled", loading);
  }

  function submitPostForm(submitter) {
    isSubmittingAfterOptionsSave = true;
    if (resetSubmittingTimer) {
      clearTimeout(resetSubmittingTimer);
    }
    resetSubmittingTimer = setTimeout(function () {
      isSubmittingAfterOptionsSave = false;
    }, 2000);

    allowFormSubmit = true;
    try {
      if (submitter && typeof submitter.click === "function") {
        submitter.click();
      } else if (typeof postForm.requestSubmit === "function") {
        postForm.requestSubmit();
      } else {
        postForm.submit();
      }
    } finally {
      allowFormSubmit = false;
    }
  }

  function saveOptionsThenSubmit(submitter) {
    isSavingOptions = true;
    setButtonLoading(submitter, true);

    /* Wait a tick so any focused React input blur update commits first.*/
    setTimeout(function () {
      const api = getSettingsApi();
      const savePromise =
        api && typeof api.save === "function"
          ? Promise.resolve(api.save({ silent: true }))
          : Promise.resolve(true);

      savePromise
        .then(function (ok) {
          if (!ok) {
            isSavingOptions = false;
            setButtonLoading(submitter, false);
            return;
          }

          /* Allow React to commit hasChanges=false and clean up its beforeunload listener.*/
          setTimeout(function () {
            isSavingOptions = false;
            setButtonLoading(submitter, false);
            submitPostForm(submitter);
          }, 50);
        })
        .catch(function () {
          isSavingOptions = false;
          setButtonLoading(submitter, false);
        });
    }, 0);
  }

  postForm.addEventListener(
    "click",
    function (e) {
      const button =
        e.target && typeof e.target.closest === "function"
          ? e.target.closest("#publish, #save-post")
          : null;
      if (!button || allowFormSubmit) {
        return;
      }
      if (isSavingOptions) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        return;
      }
      if (isButtonDisabled(button) || !needsOptionsSave()) {
        return;
      }

      e.preventDefault();
      e.stopPropagation();
      e.stopImmediatePropagation();
      saveOptionsThenSubmit(button);
    },
    true,
  );

  postForm.addEventListener(
    "submit",
    function (e) {
      if (allowFormSubmit || jQuery("#wp-preview").val() === "dopreview") {
        return;
      }
      if (isSavingOptions) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        return;
      }
      if (!needsOptionsSave()) {
        return;
      }

      const submitter =
        e.submitter ||
        document.getElementById("publish") ||
        document.getElementById("save-post");
      if (
        isButtonDisabled(submitter) ||
        isButtonDisabled(document.getElementById("publish"))
      ) {
        e.preventDefault();
        return;
      }

      e.preventDefault();
      e.stopPropagation();
      e.stopImmediatePropagation();
      saveOptionsThenSubmit(submitter);
    },
    true,
  );
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
  if (parsed === true) {
    return data !== "" ? JSON.parse(data) : [];
  } else {
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
  if (
    jQuery("#post_type").val() === "reacg" &&
    jQuery("#original_post_status").val() === "auto-draft"
  ) {
    const event = new Event("added-images");
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
    if (jQuery.inArray(jQuery(this).data("id"), images_ids) !== -1) {
      jQuery(this)
        .attr("title", "Already added")
        .addClass("reacg-already-added");
      jQuery(this)
        .find(".thumbnail")
        .on("click", function (e) {
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
  let container = jQuery(".media-frame-content .attachments:visible");
  if (container.length > 0) {
    /* Create a MutationObserver to watch for changes in the container.*/
    let observer = new MutationObserver(function (mutationsList) {
      /* Check if the .attachment container is added to the DOM.*/
      let attachmentContainer = container.find(".attachment");
      if (attachmentContainer.length > 0) {
        /* Attach load event listener to its images.*/
        attachmentContainer
          .find("img")
          .one("load", function () {
            /* Disable the image if it is already inserted into the gallery.*/
            if (
              jQuery.inArray(
                jQuery(this).closest("li").data("id"),
                images_ids,
              ) !== -1
            ) {
              jQuery(this)
                .closest("li")
                .attr("title", "Already added")
                .addClass("reacg-already-added");
              jQuery(this)
                .closest("li")
                .find(".thumbnail")
                .on("click", function (e) {
                  e.stopPropagation();
                });
            }
          })
          .each(function () {
            if (this.complete) {
              jQuery(this).trigger("load");
            }
          });
        /* Stop observing mutations once the container is found.*/
        observer.disconnect();
      }
    });
    /* Start observing mutations in the container.*/
    observer.observe(container[0], { childList: true, subtree: true });
  }
}

/**
 * Open Media uploader and set the event to the Insert button.
 *
 * @param e
 * @param that
 */
function reacg_media_uploader(e, that) {
  e.preventDefault();
  wp.Uploader.defaults.multipart_params =
    wp.Uploader.defaults.multipart_params || {};
  wp.Uploader.defaults.multipart_params.reacg = "gallery";
  wp.Uploader.defaults.multipart_params.reacg_nonce = reacg.nonce;
  const galleryItemsContainer = jQuery(that).closest(".reacg_items");

  let media_uploader = (wp.media.frames.file_frame = wp.media({
    title: reacg.choose_images,
    button: { text: reacg.insert },
    multiple: "add",
  }));

  /* Disable the images which are already added to the gallery.*/
  media_uploader.on("open", function () {
    /* Get the added images.*/
    let images_ids = reacg_get_image_ids(galleryItemsContainer, true);

    reacg_add_posts(
      media_uploader,
      images_ids,
      galleryItemsContainer.data("post-id"),
    );

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

  media_uploader.on("select", function () {
    /* Get images ids already added.*/
    let images_ids = reacg_get_image_ids(galleryItemsContainer, true);
    let coverPromises = [];

    /* Get selected images.*/
    let selected_images = media_uploader.state().get("selection").toJSON();
    for (let key in selected_images) {
      let title = selected_images[key].title;
      let sizes = selected_images[key].sizes;
      let type = selected_images[key].type;
      let thumbnail_url = reacg.no_image;
      if (
        selected_images[key].type === "video" &&
        typeof selected_images[key].thumb.src !== "undefined"
      ) {
        /* If there is thumbnail for the video and it is not a default image (video.png/video.svg)*/
        if (selected_images[key].thumb.src.search("media/video.") === -1) {
          thumbnail_url = selected_images[key].thumb.src;
        }
      } else if (sizes) {
        if (typeof sizes.thumbnail !== "undefined") {
          thumbnail_url = sizes.thumbnail.url;
        } else if (sizes.full && typeof sizes.full.url !== "undefined") {
          thumbnail_url = sizes.full.url;
        }
      }

      let image_id = selected_images[key].id;

      /* Add an image to the gallery, if it doesn't already exist.*/
      if (jQuery.inArray(image_id, images_ids) === -1) {
        /* Add selected image to the existing list of visual items.*/
        let clone = galleryItemsContainer.find(".reacg-template").clone();
        if (type === "video") {
          clone.find(".reacg-edit-thumbnail").removeClass("reacg-hidden");
          clone
            .find(".reacg-cover")
            .removeClass("reacg-hidden")
            .addClass("dashicons dashicons-controls-play");
        } else if (reacg.allowed_post_types.hasOwnProperty(type)) {
          /* Any of the allowed post types.*/
          clone.find(".reacg-item-select").addClass("reacg-hidden");
          if (String(image_id).includes("dynamic")) {
            thumbnail_url = "";
            galleryItemsContainer
              .find(".additional_data")
              .val(JSON.stringify(selected_images[key].additional_data));
          } else {
            clone.find(".reacg-edit").addClass("reacg-hidden");
          }
          clone
            .find(".reacg-cover")
            .removeClass("reacg-hidden")
            .addClass("dashicons " + reacg.allowed_post_types[type]["class"]);
        }
        clone.attr("data-id", image_id);
        clone.attr("data-type", type);
        clone.find(".reacg_item_image").attr("title", title);
        if (thumbnail_url) {
          if (!clone.find(".reacg_item_image img").length) {
            clone.find(".reacg_item_image").prepend('<img alt="" />');
          }
          clone
            .find(".reacg_item_image img")
            .attr("src", thumbnail_url)
            .each(function () {
              reacg_update_item_image_fit(this);
            });
        }
        clone
          .removeClass("reacg-hidden reacg-template")
          .addClass("reacg-sortable");
        clone.insertAfter(galleryItemsContainer.find(".reacg_item_new"));
        if (
          type === "video" &&
          reacg_should_generate_video_cover(selected_images[key], thumbnail_url)
        ) {
          coverPromises.push(
            reacg_generate_video_cover(
              selected_images[key],
              clone,
              galleryItemsContainer,
            ),
          );
        }
        /* Add selected image id to the existing list.*/
        images_ids.unshift(image_id);
      }
    }

    /* Update the images data.*/
    reacg_set_image_ids(galleryItemsContainer, images_ids);

    /* Save the images.*/
    reacg_save_images(galleryItemsContainer);
    reacg_update_bulk_toolbar(galleryItemsContainer);

    if (coverPromises.length) {
      Promise.all(coverPromises).then(function (results) {
        if (
          results.some(function (result) {
            return !!result;
          })
        ) {
          reacg_reload_preview();
        }
      });
    }

    media_uploader.remove();
  });
}

function reacg_should_generate_video_cover(selectedImage, thumbnailUrl) {
  if (
    !selectedImage ||
    selectedImage.type !== "video" ||
    !selectedImage.id ||
    !selectedImage.url
  ) {
    return false;
  }

  if (
    selectedImage.thumb &&
    typeof selectedImage.thumb.src !== "undefined" &&
    selectedImage.thumb.src.search("media/video.") === -1
  ) {
    return false;
  }

  return thumbnailUrl === reacg.no_image;
}

function reacg_generate_video_cover(
  selectedImage,
  item,
  galleryItemsContainer,
) {
  return reacg_capture_video_frame(selectedImage.url)
    .then(function (blob) {
      return reacg_upload_video_cover(
        selectedImage.id,
        blob,
        selectedImage.filename ||
          selectedImage.title ||
          "video-" + selectedImage.id,
      );
    })
    .then(function (response) {
      if (!response || !response.success || !response.data) {
        return false;
      }

      if (response.data.thumbnail_url) {
        item
          .find(".reacg_item_image img")
          .attr("src", response.data.thumbnail_url)
          .each(function () {
            reacg_update_item_image_fit(this);
          });
      }

      return true;
    })
    .catch(function (error) {
      console.warn("Video cover generation failed.", error);
      return false;
    });
}

function reacg_capture_video_frame(videoUrl) {
  return new Promise(function (resolve, reject) {
    const video = document.createElement("video");
    let settled = false;

    function cleanup() {
      video.pause();
      video.removeAttribute("src");
      video.load();
      video.remove();
    }

    function finish(callback, value) {
      if (settled) {
        return;
      }
      settled = true;
      cleanup();
      callback(value);
    }

    function drawFrame() {
      if (!video.videoWidth || !video.videoHeight) {
        finish(reject, new Error("Video frame is not available."));
        return;
      }

      const canvas = document.createElement("canvas");
      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;
      const context = canvas.getContext("2d");
      if (!context) {
        finish(reject, new Error("Canvas context is not available."));
        return;
      }

      context.drawImage(video, 0, 0, canvas.width, canvas.height);
      canvas.toBlob(
        function (blob) {
          if (!blob) {
            finish(reject, new Error("Failed to create image blob."));
            return;
          }

          finish(resolve, blob);
        },
        "image/jpeg",
        0.92,
      );
    }

    video.preload = "auto";
    video.muted = true;
    video.playsInline = true;
    video.style.position = "fixed";
    video.style.left = "-9999px";
    video.style.top = "-9999px";

    video.addEventListener(
      "error",
      function () {
        finish(reject, new Error("Video element failed to load."));
      },
      { once: true },
    );

    video.addEventListener(
      "loadeddata",
      function () {
        window.requestAnimationFrame(drawFrame);
      },
      { once: true },
    );

    document.body.appendChild(video);
    video.src = videoUrl;
    video.load();
  });
}

function reacg_upload_video_cover(videoId, blob, filename) {
  const formData = new FormData();
  const safeName =
    String(filename || "video-" + videoId).replace(/\.[^.]+$/, "") +
    "-cover.jpg";
  formData.append("action", "reacg_auto_upload_video_cover");
  formData.append("video_id", videoId);
  formData.append("filename", safeName);
  formData.append("cover", blob, safeName);

  return jQuery.ajax({
    type: "POST",
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
      action: "reacg_save_images",
      post_id: galleryItemsContainer.data("post-id"),
      images_ids: reacg_get_image_ids(galleryItemsContainer, false),
      additional_data: galleryItemsContainer.find(".additional_data").val(),
      gallery_timestamp:
        Date.now() /* Update the gallery timestamp on images save to prevent data from being read from the cache.*/,
    },
    complete: function (data) {
      reacg_toggle_loading();

      reacg_reload_preview();

      /* Highlight templates list for newly added and not yet saved posts.*/
      if (jQuery("#original_post_status").val() === "auto-draft") {
        const event = new Event("highlight-template-select");
        window.dispatchEvent(event);
      }
    },
  });
}

/**
 * Init select2 with the ReGallery dropdown class.
 *
 * The `dropdownCssClass` option needs the full select2 build
 * (select2/compat/dropdownCss). Page builders such as Beaver Builder load a
 * standard build that throws on it, so the class is added to the dropdown
 * element directly. Errors never propagate, so the caller can still bind its
 * modal handlers.
 *
 * @param {jQuery} $ jQuery instance that owns select2.
 * @param {jQuery|Element} select
 * @param {Object} options
 */
function reacg_init_select2($, select, options) {
  try {
    const $select = $(select);
    $select.select2(options);
    $select.on("select2:open", function () {
      const instance = $select.data("select2");
      const $dropdown =
        instance && instance.dropdown && instance.dropdown.$dropdown
          ? instance.dropdown.$dropdown
          : $select.closest(".reacg-modal").find(".select2-dropdown");
      if ($dropdown && $dropdown.length) {
        $dropdown.addClass("reacg-select2-dropdown");
      }
    });
  } catch (e) {
    console.error(e);
  }
}

/**
 * Documents that may contain the gallery preview (admin page and Gutenberg canvas iframe).
 *
 * @returns {Document[]}
 */
function reacg_get_preview_documents() {
  const documents = [];
  const add = function (doc) {
    if (doc && documents.indexOf(doc) === -1) {
      documents.push(doc);
    }
  };
  add(document);

  // Gutenberg canvas iframe
  try {
    const canvas = document.querySelector('iframe[name="editor-canvas"]');
    if (canvas && canvas.contentDocument) {
      add(canvas.contentDocument);
    }
  } catch (error) {
    /* Cross-origin iframe; ignore.*/
  }

  // Elementor preview iframe
  try {
    const elCanvas = document.querySelector("#elementor-preview-iframe");
    if (elCanvas && elCanvas.contentDocument) {
      add(elCanvas.contentDocument);
    }
  } catch (error) {
    /* Cross-origin iframe; ignore.*/
  }

  // Bricks preview iframe
  try {
    const bricksCanvas =
      document.querySelector("#bricks-builder-iframe") ||
      (window.top && window.top.document
        ? window.top.document.querySelector("#bricks-builder-iframe")
        : null);
    if (bricksCanvas && bricksCanvas.contentDocument) {
      add(bricksCanvas.contentDocument);
    }
  } catch (error) {
    /* Cross-origin iframe; ignore.*/
  }

  // Divi app frames (Divi 4: #et-fb-app-frame, Divi 5: #et-vb-app-frame)
  try {
    const diviFrame =
      document.querySelector("#et-fb-app-frame, #et-vb-app-frame") ||
      (window.top && window.top.document
        ? window.top.document.querySelector(
            "#et-fb-app-frame, #et-vb-app-frame",
          )
        : null);
    if (diviFrame && diviFrame.contentDocument) {
      add(diviFrame.contentDocument);
    }
  } catch (error) {
    /* Cross-origin iframe; ignore.*/
  }

  // Other editor preview iframes
  try {
    const iframes = document.querySelectorAll("iframe");
    iframes.forEach(function (frame) {
      try {
        if (
          frame.contentDocument &&
          (frame.id.indexOf("preview") !== -1 ||
            frame.name.indexOf("preview") !== -1 ||
            frame.className.indexOf("preview") !== -1)
        ) {
          add(frame.contentDocument);
        }
      } catch (e) {}
    });
  } catch (error) {}

  return documents;
}

/**
 * Query the first matching node in the admin document or editor canvas.
 *
 * @param {string} selector
 * @param {boolean} all
 * @returns {Element|Element[]|null}
 */
function reacg_query_preview_docs(selector, all) {
  const documents = reacg_get_preview_documents();
  if (all) {
    const results = [];
    documents.forEach(function (doc) {
      doc.querySelectorAll(selector).forEach(function (el) {
        results.push(el);
      });
    });
    return results;
  }
  for (let i = 0; i < documents.length; i++) {
    const el = documents[i].querySelector(selector);
    if (el) {
      return el;
    }
  }
  return null;
}

/**
 * Trigger hidden button click to reload the preview across all preview documents.
 */
function reacg_reload_preview() {
  const now = Date.now();
  const documents = reacg_get_preview_documents();

  documents.forEach(function (doc) {
    if (!doc) return;
    const win = doc.defaultView || window;

    // 1. Clear cached in-memory reacg_data in canvas window so it doesn't serve stale images
    if (win.reacg_data) {
      Object.keys(win.reacg_data).forEach(function (key) {
        delete win.reacg_data[key];
      });
    }

    // 2. Update data-gallery-timestamp on all gallery elements in this document
    const previews = doc.querySelectorAll(
      ".reacg-preview, .reacg-gallery, [id^='reacg-root']",
    );
    previews.forEach(function (p) {
      p.setAttribute("data-gallery-timestamp", now);
    });

    // 3. Trigger reloadData if present in this document (preserves unsaved React options)
    const reloadButtons = doc.querySelectorAll("#reacg-reloadData");
    let reloaded = false;
    reloadButtons.forEach(function (btn) {
      try {
        btn.click();
        reloaded = true;
      } catch (e) {}
    });

    // 4. Fallback: Trigger loadApp only if reloadData was not found
    if (!reloaded) {
      const loadApp = doc.getElementById("reacg-loadApp");
      if (loadApp) {
        previews.forEach(function (p) {
          if (p.id) {
            try {
              loadApp.setAttribute("data-id", p.id);
              loadApp.click();
            } catch (e) {}
          }
        });
      }
    }
  });

  if (window.reacg_data) {
    Object.keys(window.reacg_data).forEach(function (key) {
      delete window.reacg_data[key];
    });
  }
}

/**
 * Refresh the gallery Media metabox and live preview after external media import.
 *
 * @param {number|string} galleryId
 * @param {Array<number|string>} [imagesIds]
 */
function reacg_refresh_gallery_images(galleryId, imagesIds) {
  const metaboxItems = jQuery("#gallery-images .reacg_items").filter(
    function () {
      return String(jQuery(this).data("post-id")) === String(galleryId);
    },
  );

  if (metaboxItems.length) {
    if (Array.isArray(imagesIds)) {
      const hiddenImagesInput = metaboxItems.find(".images_ids");
      hiddenImagesInput.val(JSON.stringify(imagesIds));
      metaboxItems.find(".additional_data").val("");
      if (hiddenImagesInput[0]) {
        hiddenImagesInput[0].dispatchEvent(new Event("added-images"));
      }
    }

    const ajaxUrl =
      metaboxItems.data("ajax-url") ||
      (typeof reacg !== "undefined" && reacg.ajax_url ? reacg.ajax_url : "");

    if (ajaxUrl) {
      const separator = ajaxUrl.indexOf("?") === -1 ? "?" : "&";
      reacg_toggle_loading();
      fetch(
        ajaxUrl +
          separator +
          "action=reacg_get_images&id=" +
          encodeURIComponent(galleryId),
        { credentials: "same-origin" },
      )
        .then(function (res) {
          return res.text();
        })
        .then(function (body) {
          let html = body;
          try {
            const parsed = JSON.parse(body);
            if (typeof parsed === "string") {
              html = parsed;
            }
          } catch (e) {}

          if (html) {
            const inside = metaboxItems.closest(".inside");
            const hasNewToolbar =
              jQuery("<div>" + html + "</div>").find(".reacg-media-toolbar")
                .length > 0;
            if (hasNewToolbar) {
              metaboxItems.siblings(".reacg-media-toolbar").remove();
            }
            metaboxItems.replaceWith(html);
            const scope = inside.length ? inside[0] : document;
            reacg_make_items_sortable(scope);
            jQuery(scope)
              .find(".reacg_item_image img")
              .each(function () {
                reacg_update_item_image_fit(this);
              });
            reacg_update_bulk_toolbar(scope);
          }
        })
        .catch(function (error) {
          console.error("Failed to refresh gallery media items:", error);
        })
        .finally(function () {
          reacg_toggle_loading();
        });
    }

    if (jQuery("#original_post_status").val() === "auto-draft") {
      window.dispatchEvent(new Event("highlight-template-select"));
    }
  }

  reacg_reload_preview();
}

window.reacg_refresh_gallery_images = reacg_refresh_gallery_images;
window.addEventListener("reacg:images-updated", function (e) {
  if (e && e.detail && e.detail.galleryId) {
    reacg_refresh_gallery_images(e.detail.galleryId, e.detail.imagesIds);
  }
});

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
      action: "reacg_save_thumbnail",
      id: id,
      thumbnail_id: thumbnail_id,
    },
    complete: function (data) {
      reacg_toggle_loading();

      reacg_reload_preview();
    },
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
      action: "reacg_delete_thumbnail",
      id: id,
    },
    complete: function (data) {
      reacg_toggle_loading();
    },
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
  return (
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">' +
    '<path fill="#FFFFFF" d="m19.026,12v6c0,.552-.448,1-1,1s-1-.448-1-1v-6c0-.552.448-1,1-1s1,.448,1,1Zm-7.42-5.283l3.071,11.029c.175.63-.298,1.254-.953,1.254-.443,0-.831-.294-.952-.72l-.643-2.28h-5.206l-.643,2.28c-.12.426-.509.72-.952.72h0c-.654,0-1.128-.624-.953-1.254l3.091-11.108c.141-.608.541-1.12,1.098-1.405.568-.292,1.22-.31,1.839-.05.587.246,1.037.817,1.204,1.535Zm-.041,7.283l-1.929-6.835c-.029-.114-.191-.114-.219,0l-1.929,6.835h4.077Zm11.462-4c-.552,0-1,.448-1,1v8c0,1.654-1.346,3-3,3H5.026c-1.654,0-3-1.346-3-3V5c0-1.654,1.346-3,3-3h8c.552,0,1-.448,1-1S13.578,0,13.026,0H5.026C2.269,0,.026,2.243.026,5v14c0,2.757,2.243,5,5,5h14c2.757,0,5-2.243,5-5v-8c0-.552-.448-1-1-1Zm-6.85-4.82l1.868.787.745,1.865c.161.404.552.668.987.668s.825-.265.987-.668l.741-1.854,1.854-.741c.404-.161.668-.552.668-.987s-.265-.825-.668-.987l-1.854-.741-.741-1.854C20.601.265,20.21,0,19.776,0s-.825.265-.987.668l-.737,1.843-1.84.697c-.406.154-.678.54-.686.974-.008.435.25.83.65.999Z"/>' +
    "</svg>"
  );
}

function reacg_info_icon() {
  return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12,0A12,12,0,1,0,24,12,12.013,12.013,0,0,0,12,0Zm0,21a9,9,0,1,1,9-9A9.011,9.011,0,0,1,12,21Z"/><path d="M11.545,9.545h-.3A1.577,1.577,0,0,0,9.64,10.938,1.5,1.5,0,0,0,11,12.532v4.65a1.5,1.5,0,0,0,3,0V12A2.455,2.455,0,0,0,11.545,9.545Z"/><path d="M11.83,8.466A1.716,1.716,0,1,0,10.114,6.75,1.715,1.715,0,0,0,11.83,8.466Z"/></svg>';
}

function reacg_pro_icon() {
  return '<svg width="38px" height="22px" viewBox="0 0 153 88" xmlns="http://www.w3.org/2000/svg"><path d="M0 0 C0.96301025 -0.00571014 1.92602051 -0.01142029 2.91821289 -0.01730347 C3.9681694 -0.01842133 5.01812592 -0.01953918 6.09989929 -0.02069092 C7.7664003 -0.02813828 7.7664003 -0.02813828 9.46656799 -0.03573608 C13.14472573 -0.05035013 16.82284033 -0.05715834 20.50102234 -0.06268311 C23.05686443 -0.068435 25.61270652 -0.07419273 28.16854858 -0.07995605 C32.84420199 -0.0891209 37.51984246 -0.0954222 42.19550258 -0.09845281 C49.06655064 -0.10293151 55.93739957 -0.12047407 62.8083871 -0.1494534 C68.76923192 -0.17371706 74.7300274 -0.18139917 80.69092369 -0.18312836 C83.22224703 -0.1861544 85.75356953 -0.19418128 88.28486061 -0.20731354 C91.82752669 -0.2243814 95.3697087 -0.22249169 98.91239929 -0.21600342 C99.95628311 -0.2252182 101.00016693 -0.23443298 102.07568359 -0.243927 C110.4889872 -0.19569598 116.51764793 1.76531262 123.20024109 7.14044189 C127.39491321 12.62404291 129.29930016 17.23715251 129.37724304 24.14923096 C129.3964959 25.641633 129.3964959 25.641633 129.4161377 27.16418457 C129.42352463 28.23400574 129.43091156 29.3038269 129.43852234 30.40606689 C129.44659409 31.51343201 129.45466583 32.62079712 129.46298218 33.76171875 C129.47680719 36.1052291 129.48753642 38.44875945 129.4954071 40.79229736 C129.51260968 44.36316393 129.55651041 47.93310206 129.60063171 51.50372314 C129.61076011 53.78366427 129.61927575 56.06361325 129.62602234 58.34356689 C129.64353043 59.40529114 129.66103851 60.46701538 129.67907715 61.56091309 C129.6558828 69.54887152 128.06137832 74.82754616 122.82524109 80.89044189 C117.11483975 85.78507161 112.88955662 88.25419529 105.4072113 88.28088379 C104.44564621 88.28659393 103.48408112 88.29230408 102.49337769 88.29818726 C100.92079948 88.29986404 100.92079948 88.29986404 99.31645203 88.30157471 C97.65236046 88.30902206 97.65236046 88.30902206 95.95465088 88.31661987 C92.28129965 88.3312351 88.60799162 88.33804267 84.93461609 88.34356689 C82.38116962 88.34931911 79.82772317 88.35507684 77.27427673 88.36083984 C72.60229181 88.37000522 67.93031984 88.3763061 63.2583282 88.3793366 C56.39578004 88.3838132 49.53343147 88.40134549 42.67094398 88.43033719 C36.71544198 88.45461814 30.75998931 88.46228383 24.80443573 88.46401215 C22.27672998 88.46703607 19.74902502 88.4750558 17.22135162 88.48819733 C13.68128424 88.5052877 10.1417004 88.50336987 6.60160828 88.49688721 C5.56131973 88.50610199 4.52103119 88.51531677 3.44921875 88.52481079 C-4.73695785 88.47794814 -11.77427292 87.16592789 -17.79975891 81.14044189 C-21.52396907 75.92865771 -23.90495514 71.66698684 -23.97676086 65.15338135 C-23.9895961 64.13835037 -24.00243134 63.1233194 -24.01565552 62.07752991 C-24.02304245 60.98067368 -24.03042938 59.88381744 -24.03804016 58.75372314 C-24.04611191 57.62274704 -24.05418365 56.49177094 -24.0625 55.32652283 C-24.07633902 52.9299574 -24.08706199 50.53337227 -24.09492493 48.13677979 C-24.11210985 44.48462157 -24.15599599 40.83337509 -24.20014954 37.18145752 C-24.21027911 34.85138604 -24.21879438 32.52130688 -24.22554016 30.19122314 C-24.24304825 29.10448807 -24.26055634 28.01775299 -24.27859497 26.89808655 C-24.25568463 18.82587802 -22.7173398 13.51869344 -17.42475891 7.39044189 C-11.71116305 2.49307401 -7.48604418 0.02666017 0 0 Z " fill="#F5F2FF" transform="translate(23.799758911132813,-0.14044189453125)"></path><path d="M0 0 C3.68615206 2.21937072 5.74805019 4.36011501 7.5 8.375 C8.64966184 15.32771681 7.54721796 21.07085687 4.1328125 27.20703125 C0.8573381 31.55604631 -3.42862989 33.6845433 -8.5 35.375 C-16.14747114 35.94883622 -22.09789337 36.04342219 -28.1875 30.96875 C-31.52710966 27.69098496 -32.91012473 25.15387749 -33.0625 20.4375 C-32.55128788 13.25877663 -31.01166737 8.42930882 -25.625 3.5 C-18.45536973 -2.54498238 -8.6526023 -3.8961061 0 0 Z " fill="#896CFF" transform="translate(125.5,27.625)"></path><path d="M0 0 C3.70818297 -0.08756305 7.41609763 -0.14058015 11.125 -0.1875 C12.17171875 -0.21263672 13.2184375 -0.23777344 14.296875 -0.26367188 C19.96055425 -0.31744098 24.12542151 -0.25792535 29 3 C30 4 30 4 30.3125 7.75 C30.27178656 10.99544251 30.01242062 11.9795947 28.25 14.875 C26.20332225 16.80797343 24.60156414 17.97795695 22 19 C23.32 19.99 24.64 20.98 26 22 C25.47694567 26.40860079 24.87251184 30.63744078 24 35 C19.71 35 15.42 35 11 35 C12 26.42857143 12 26.42857143 13 23 C11.02 23 9.04 23 7 23 C6.79503906 23.69867188 6.59007813 24.39734375 6.37890625 25.1171875 C6.10949219 26.02726562 5.84007813 26.93734375 5.5625 27.875 C5.29566406 28.77992187 5.02882813 29.68484375 4.75390625 30.6171875 C4 33 4 33 3 35 C-0.96 35 -4.92 35 -9 35 C-8.27347664 28.63170882 -6.70010013 22.56410745 -5.0625 16.375 C-4.79759766 15.35148437 -4.53269531 14.32796875 -4.25976562 13.2734375 C-3.06413217 8.71638295 -1.82098461 4.35293175 0 0 Z " fill="#896BFF" transform="translate(62,27)"></path><path d="M0 0 C3.3956697 -0.08745946 6.79104773 -0.14054444 10.1875 -0.1875 C11.14720703 -0.21263672 12.10691406 -0.23777344 13.09570312 -0.26367188 C21.84048429 -0.35444815 21.84048429 -0.35444815 26 3 C26.598125 3.391875 27.19625 3.78375 27.8125 4.1875 C29.83132873 7.26887017 29.57723999 10.50338535 29 14 C26.80597572 18.75371928 24.02862635 21.18964541 19.37109375 23.3203125 C14.91354324 24.59809469 10.60216322 24.23010816 6 24 C5.34 27.63 4.68 31.26 4 35 C-0.29 35 -4.58 35 -9 35 C-8.29824602 30.78947612 -7.51539199 26.92246822 -6.40625 22.83203125 C-6.09816406 21.69185547 -5.79007813 20.55167969 -5.47265625 19.37695312 C-5.15167969 18.20068359 -4.83070312 17.02441406 -4.5 15.8125 C-4.17902344 14.62462891 -3.85804687 13.43675781 -3.52734375 12.21289062 C-1.15788849 3.47366546 -1.15788849 3.47366546 0 0 Z " fill="#8A6CFF" transform="translate(27,27)"></path><path d="M0 0 C2.875 0.75 2.875 0.75 3.875 1.75 C3.82165817 7.84430385 2.38984116 13.23669438 -0.125 18.75 C-3.125 20.75 -3.125 20.75 -5.75 20.4375 C-8.125 19.75 -8.125 19.75 -9.125 18.75 C-9.82749396 11.57645595 -8.88015315 6.81713084 -4.8125 0.8125 C-3.125 -0.25 -3.125 -0.25 0 0 Z " fill="#F0EBFF" transform="translate(116.125,34.25)"></path><path d="M0 0 C1.98 0 3.96 0 6 0 C6.125 2.375 6.125 2.375 6 5 C4 7 4 7 0.875 7.125 C-0.548125 7.063125 -0.548125 7.063125 -2 7 C-1.125 2.25 -1.125 2.25 0 0 Z " fill="#EFEAFF" transform="translate(73,35)"></path><path d="M0 0 C1.65 0.33 3.3 0.66 5 1 C4.67 2.98 4.34 4.96 4 7 C2.02 7.33 0.04 7.66 -2 8 C-1.34 5.36 -0.68 2.72 0 0 Z " fill="#EFEAFF" transform="translate(38,35)"></path></svg>';
}

function reacg_escape_html(value) {
  const stringValue = value == null ? "" : String(value);
  const htmlEntityMap = {
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#39;",
  };

  return stringValue.replace(/[&<>"']/g, function (character) {
    return htmlEntityMap[character];
  });
}

function reacg_get_attachment_field_value(attachment, fieldName) {
  const value = attachment.get(fieldName);
  if (value && typeof value === "object") {
    return value.raw || value.rendered || "";
  }

  return value || "";
}

function reacg_get_attachment_edit_link(attachmentId) {
  const id = parseInt(attachmentId, 10) || 0;
  if (!id) {
    return "#";
  }

  if (typeof ajaxurl === "string" && ajaxurl.length) {
    return (
      ajaxurl.replace("admin-ajax.php", "post.php") +
      "?post=" +
      id +
      "&action=edit&image-editor"
    );
  }

  return "/wp-admin/post.php?post=" + id + "&action=edit&image-editor";
}

function reacg_crop_position_picker() {
  const positions = [
    ["left top", reacg.crop_position_left_top],
    ["center top", reacg.crop_position_center_top],
    ["right top", reacg.crop_position_right_top],
    ["left center", reacg.crop_position_left_center],
    ["center center", reacg.crop_position_center_center],
    ["right center", reacg.crop_position_right_center],
    ["left bottom", reacg.crop_position_left_bottom],
    ["center bottom", reacg.crop_position_center_bottom],
    ["right bottom", reacg.crop_position_right_bottom],
  ];

  return (
    '<div class="reacg-attachment-modal__field reacg-crop-position-picker" data-setting="crop_position">' +
    '<div class="reacg-crop-position-picker__label-row"><label id="reacg-attachment-crop-position-label">' +
    reacg_escape_html(reacg.attachment_crop_position) +
    "</label>" +
    '<button type="button" class="reacg-crop-position-picker__help dashicons dashicons-info-outline" aria-label="' +
    reacg_escape_html(reacg.attachment_crop_position_description) +
    '" data-tooltip="' +
    reacg_escape_html(reacg.attachment_crop_position_description) +
    '"><span class="screen-reader-text">' +
    reacg_escape_html(reacg.attachment_crop_position_description) +
    "</span></button></div>" +
    '<div class="reacg-crop-position-picker__grid" role="radiogroup" aria-labelledby="reacg-attachment-crop-position-label">' +
    positions
      .map(function (position) {
        const value = position[0];
        const label = position[1];
        return (
          '<label class="reacg-crop-position-picker__option reacg-crop-position-picker__option--' +
          value.replace(" ", "-") +
          '" title="' +
          reacg_escape_html(label) +
          '">' +
          '<input type="radio" name="reacg-attachment-crop-position" value="' +
          value +
          '" />' +
          '<span aria-hidden="true"></span>' +
          '<span class="screen-reader-text">' +
          reacg_escape_html(label) +
          "</span>" +
          "</label>"
        );
      })
      .join("") +
    "</div>" +
    "</div>"
  );
}

function reacg_is_image_attachment(attachment) {
  if (!attachment || typeof attachment.get !== "function") {
    return false;
  }
  const attachmentType = String(
    reacg_get_attachment_field_value(attachment, "type") || "",
  ).toLowerCase();
  const mimeType = String(
    reacg_get_attachment_field_value(attachment, "mime") || "",
  ).toLowerCase();
  if (attachmentType === "video" || mimeType.indexOf("video/") === 0) {
    return false;
  }
  return attachmentType === "image" || mimeType.indexOf("image/") === 0;
}

function reacg_is_video_attachment(attachment) {
  if (!attachment || typeof attachment.get !== "function") {
    return false;
  }
  const attachmentType = String(
    reacg_get_attachment_field_value(attachment, "type") || "",
  ).toLowerCase();
  const mimeType = String(
    reacg_get_attachment_field_value(attachment, "mime") || "",
  ).toLowerCase();
  if (attachmentType === "video" || mimeType.indexOf("video/") === 0) {
    return true;
  }
  const url = String(reacg_get_attachment_field_value(attachment, "url") || "");
  return /\.(mp4|m4v|webm|ogv|mov|mkv)(\?.*)?$/i.test(url);
}

function reacg_resolve_attachment_ai_image_url(att, selectedItems) {
  const isVideo = reacg_is_video_attachment(att);
  const sizes = att && typeof att.get === "function" ? att.get("sizes") : null;
  const attachmentUrl = reacg_get_attachment_field_value(att, "url") || "";

  if (!isVideo) {
    const previewSize =
      sizes && sizes.thumbnail
        ? sizes.thumbnail
        : sizes && sizes.full
          ? sizes.full
          : null;
    const previewUrl =
      previewSize && previewSize.url ? previewSize.url : attachmentUrl;
    return Promise.resolve(previewUrl);
  }

  const attId =
    att && typeof att.get === "function" ? String(att.get("id")) : "";

  // 1. Try video_cover_url if exposed on attachment model.
  const coverUrl =
    att && typeof att.get === "function" ? att.get("video_cover_url") : null;
  if (
    coverUrl &&
    typeof coverUrl === "string" &&
    coverUrl.length &&
    coverUrl !== reacg.no_image
  ) {
    return Promise.resolve(coverUrl);
  }

  // 2. Try gallery item element from selectedItems or the DOM.
  let item =
    selectedItems && selectedItems.length
      ? selectedItems.filter('[data-id="' + attId + '"]')
      : jQuery();
  if (!item.length && attId) {
    item = jQuery('.reacg_items .reacg_item[data-id="' + attId + '"]');
  }
  if (item.length) {
    const domImgSrc = item.find(".reacg_item_image img").attr("src");
    if (
      domImgSrc &&
      domImgSrc.length &&
      domImgSrc !== reacg.no_image &&
      domImgSrc.indexOf("video.png") === -1 &&
      domImgSrc.indexOf("video.svg") === -1
    ) {
      return Promise.resolve(domImgSrc);
    }
  }

  // 3. Try sizes or thumb/image object on wp.media attachment.
  if (sizes && sizes.thumbnail && sizes.thumbnail.url) {
    return Promise.resolve(sizes.thumbnail.url);
  }
  if (sizes && sizes.full && sizes.full.url) {
    return Promise.resolve(sizes.full.url);
  }
  const thumbObj =
    att && typeof att.get === "function"
      ? att.get("thumb") || att.get("image")
      : null;
  if (
    thumbObj &&
    thumbObj.src &&
    thumbObj.src !== reacg.no_image &&
    thumbObj.src.indexOf("video.png") === -1 &&
    thumbObj.src.indexOf("video.svg") === -1
  ) {
    return Promise.resolve(thumbObj.src);
  }

  // 4. If video has no cover yet, capture frame and upload cover.
  if (
    attachmentUrl &&
    typeof reacg_capture_video_frame === "function" &&
    typeof reacg_upload_video_cover === "function"
  ) {
    return reacg_capture_video_frame(attachmentUrl)
      .then(function (blob) {
        return reacg_upload_video_cover(
          att.get("id"),
          blob,
          att.get("filename") || att.get("title") || "video-" + att.get("id"),
        );
      })
      .then(function (uploadRes) {
        if (
          uploadRes &&
          uploadRes.success &&
          uploadRes.data &&
          uploadRes.data.thumbnail_url
        ) {
          const generatedThumbUrl = uploadRes.data.thumbnail_url;
          if (item.length) {
            item
              .find(".reacg_item_image img")
              .attr("src", generatedThumbUrl)
              .each(function () {
                reacg_update_item_image_fit(this);
              });
          }
          if (typeof att.set === "function") {
            att.set("video_cover_url", generatedThumbUrl);
          }
          return generatedThumbUrl;
        }
        return attachmentUrl;
      })
      .catch(function () {
        return attachmentUrl;
      });
  }

  return Promise.resolve(attachmentUrl);
}

function reacg_is_convertible_attachment(attachment) {
  if (!attachment || typeof attachment.get !== "function") {
    return false;
  }
  return (
    reacg_is_image_attachment(attachment) ||
    reacg_is_video_attachment(attachment)
  );
}

function reacg_get_attachment_format(attachment, item) {
  if (!attachment || typeof attachment.get !== "function") {
    return "";
  }
  if (reacg_is_video_attachment(attachment)) {
    const coverFormat = attachment.get("video_cover_format");
    if (coverFormat && typeof coverFormat === "string") {
      return coverFormat.toLowerCase() === "jpeg"
        ? "jpg"
        : coverFormat.toLowerCase();
    }
    const coverMime = attachment.get("video_cover_mime");
    if (
      coverMime &&
      typeof coverMime === "string" &&
      coverMime.indexOf("image/") === 0
    ) {
      const sub = coverMime.slice(6).toLowerCase();
      return sub === "jpeg" ? "jpg" : sub;
    }
    const coverUrl = attachment.get("video_cover_url");
    if (coverUrl && typeof coverUrl === "string") {
      const match = coverUrl.split("?")[0].match(/\.([a-z0-9]+)$/i);
      if (match && match[1]) {
        const ext = match[1].toLowerCase();
        return ext === "jpeg" ? "jpg" : ext;
      }
    }
    const attId = String(attachment.get("id"));
    const domImg =
      (item && item.length
        ? item.find(".reacg_item_image img").attr("src")
        : "") ||
      jQuery(
        '.reacg_items .reacg_item[data-id="' +
          attId +
          '"] .reacg_item_image img',
      ).attr("src");
    if (domImg && typeof domImg === "string" && domImg !== reacg.no_image) {
      const match = domImg.split("?")[0].match(/\.([a-z0-9]+)$/i);
      if (match && match[1]) {
        const ext = match[1].toLowerCase();
        return ext === "jpeg" ? "jpg" : ext;
      }
    }
    const thumbObj = attachment.get("thumb") || attachment.get("image");
    if (
      thumbObj &&
      thumbObj.src &&
      thumbObj.src.indexOf("video.png") === -1 &&
      thumbObj.src.indexOf("video.svg") === -1 &&
      thumbObj.src !== reacg.no_image
    ) {
      const match = thumbObj.src.split("?")[0].match(/\.([a-z0-9]+)$/i);
      if (match && match[1]) {
        const ext = match[1].toLowerCase();
        return ext === "jpeg" ? "jpg" : ext;
      }
    }
    return "";
  }

  const mimeType = String(
    reacg_get_attachment_field_value(attachment, "mime") || "",
  ).toLowerCase();
  if (mimeType.indexOf("image/") === 0) {
    const sub = mimeType.slice(6).toLowerCase();
    return sub === "jpeg" ? "jpg" : sub;
  }
  const filename = String(
    reacg_get_attachment_field_value(attachment, "filename") ||
      reacg_get_attachment_field_value(attachment, "url") ||
      "",
  );
  const match = filename.split("?")[0].match(/\.([a-z0-9]+)$/i);
  if (match && match[1]) {
    const ext = match[1].toLowerCase();
    return ext === "jpeg" ? "jpg" : ext;
  }
  return "";
}

function reacg_get_convertible_formats() {
  const configured =
    reacg &&
    reacg.convertible_formats &&
    typeof reacg.convertible_formats === "object"
      ? reacg.convertible_formats
      : null;

  return {
    webp: {
      label: (configured && configured.webp && configured.webp.label) || "WebP",
      mime:
        (configured && configured.webp && configured.webp.mime) || "image/webp",
      supported:
        configured &&
        configured.webp &&
        typeof configured.webp.supported !== "undefined"
          ? Boolean(configured.webp.supported)
          : true,
      reason:
        (configured && configured.webp && configured.webp.reason) ||
        "WebP conversion is disabled because your server's PHP image editor (Imagick or GD) does not have WebP support enabled.",
      how_to_enable:
        (configured && configured.webp && configured.webp.how_to_enable) ||
        'Enable the PHP "imagick" extension or compile the "gd" extension with WebP support in your php.ini / hosting control panel.',
    },
    avif: {
      label: (configured && configured.avif && configured.avif.label) || "AVIF",
      mime:
        (configured && configured.avif && configured.avif.mime) || "image/avif",
      supported:
        configured &&
        configured.avif &&
        typeof configured.avif.supported !== "undefined"
          ? Boolean(configured.avif.supported)
          : true,
      reason:
        (configured && configured.avif && configured.avif.reason) ||
        "AVIF conversion is disabled because your server's PHP image editor (Imagick or GD) does not have AVIF support enabled.",
      how_to_enable:
        (configured && configured.avif && configured.avif.how_to_enable) ||
        'Enable the PHP "imagick" extension (with libavif) or PHP 8.1+ "gd" extension with AVIF support in your php.ini / hosting control panel.',
    },
  };
}

function reacg_image_converter_picker() {
  const label = reacg.convert_format_label || "Convert format";
  const description =
    reacg.convert_format_description ||
    "Convert image, video cover and their generated sizes to a modern, lighter format.";
  const buttonText = reacg.convert_button || "Convert";
  const deleteOriginalText =
    reacg.delete_original_files || "Delete old format files";
  const isPro = reacg_is_user_pro();

  return (
    '<div class="reacg-attachment-modal__field reacg-image-converter" data-setting="convert_format">' +
    '<div class="reacg-crop-position-picker__label-row">' +
    '<label for="reacg-convert-format-select">' +
    reacg_escape_html(label) +
    "</label>" +
    '<button type="button" class="reacg-crop-position-picker__help dashicons dashicons-info-outline" aria-label="' +
    reacg_escape_html(description) +
    '" data-tooltip="' +
    reacg_escape_html(description) +
    '"><span class="screen-reader-text">' +
    reacg_escape_html(description) +
    "</span></button>" +
    '<span class="reacg-pro-badge' +
    (isPro ? " hidden" : "") +
    '">' +
    reacg_pro_icon() +
    "</span></div>" +
    '<div class="reacg-image-converter__controls">' +
    '<select id="reacg-convert-format-select" class="reacg-convert-format-select"></select>' +
    '<button type="button" class="button button-secondary reacg-convert-format-btn">' +
    reacg_escape_html(buttonText) +
    "</button>" +
    '<span class="spinner reacg-convert-spinner reacg-float-none"></span>' +
    "</div>" +
    '<label class="reacg-convert-delete-original">' +
    '<input type="checkbox" class="reacg-convert-delete-original-checkbox" />' +
    "<span>" +
    reacg_escape_html(deleteOriginalText) +
    "</span>" +
    "</label>" +
    '<div class="reacg-convert-disabled-reason hidden" role="note"></div>' +
    '<p class="reacg-convert-status hidden"></p>' +
    "</div>"
  );
}

function reacg_update_converter_disabled_notice(converterWrap, currentFormat) {
  if (!converterWrap || !converterWrap.length) {
    return;
  }
  const select = converterWrap.find(".reacg-convert-format-select");
  const convertBtn = converterWrap.find(".reacg-convert-format-btn");
  const deleteCheckbox = converterWrap.find(
    ".reacg-convert-delete-original-checkbox",
  );
  const deleteLabel = converterWrap.find(".reacg-convert-delete-original");
  const reasonEl = converterWrap.find(".reacg-convert-disabled-reason");

  const selectedKey = String(select.val() || "").toLowerCase();
  const formats = reacg_get_convertible_formats();
  const info = formats[selectedKey];
  const isCurrent = Boolean(currentFormat && currentFormat === selectedKey);
  const isSupported = Boolean(info && info.supported);
  const canConvert = Boolean(selectedKey && info && isSupported && !isCurrent);
  const isPro = reacg_is_user_pro();

  if (isPro) {
    convertBtn
      .prop("disabled", !canConvert)
      .toggleClass("disabled", !canConvert)
      .attr("aria-disabled", !canConvert ? "true" : "false");
  } else {
    convertBtn
      .prop("disabled", false)
      .toggleClass("disabled", !canConvert)
      .attr("aria-disabled", !canConvert ? "true" : "false");
  }
  deleteCheckbox.prop("disabled", !canConvert);
  deleteLabel.toggleClass("is-disabled", !canConvert);

  if (!reasonEl.length) {
    return;
  }

  reasonEl
    .empty()
    .removeClass(
      "reacg-convert-disabled-reason--warning reacg-convert-disabled-reason--info",
    );

  if (info && !isSupported) {
    const howToLabel = reacg.how_to_enable_label || "How to enable:";
    const reasonParagraph = jQuery(
      '<p class="reacg-convert-disabled-reason__text"></p>',
    ).text(info.reason);
    reasonEl.append(reasonParagraph);

    if (info.how_to_enable) {
      const howToParagraph = jQuery(
        '<p class="reacg-convert-disabled-reason__howto"></p>',
      );
      howToParagraph.append(jQuery("<strong></strong>").text(howToLabel + " "));
      howToParagraph.append(document.createTextNode(info.how_to_enable));
      reasonEl.append(howToParagraph);
    }

    reasonEl
      .addClass("reacg-convert-disabled-reason--warning")
      .removeClass("hidden");
  } else if (info && isCurrent) {
    const isVideo = Boolean(converterWrap.data("isVideo"));
    const alreadyMsg = (
      isVideo
        ? reacg.video_cover_already_in_format ||
          "Video cover is already in %s format."
        : reacg.already_in_format || "Already in %s format."
    ).replace("%s", info.label || selectedKey.toUpperCase());
    reasonEl
      .append(
        jQuery('<p class="reacg-convert-disabled-reason__text"></p>').text(
          alreadyMsg,
        ),
      )
      .addClass("reacg-convert-disabled-reason--info")
      .removeClass("hidden");
  } else {
    reasonEl.addClass("hidden");
  }
}

function reacg_sync_converter_select(converterWrap, currentFormat, isVideo) {
  if (!converterWrap || !converterWrap.length) {
    return;
  }
  converterWrap.data("currentFormat", currentFormat || "");
  if (typeof isVideo !== "undefined") {
    converterWrap.data("isVideo", Boolean(isVideo));
  }

  const select = converterWrap.find(".reacg-convert-format-select");
  const formats = reacg_get_convertible_formats();
  const formatKeys = ["webp", "avif"];
  const currentSuffix = reacg.current_format_suffix || "current";

  const previousVal = select.val();
  select.empty();

  let firstSelectable = "";
  formatKeys.forEach(function (key) {
    const info = formats[key];
    if (!info) return;
    const isCurrent = Boolean(currentFormat && currentFormat === key);
    const isSupported = Boolean(info.supported);
    let text = info.label;
    if (isCurrent) {
      text += " (" + currentSuffix + ")";
    }

    const opt = jQuery("<option></option>")
      .attr("value", key)
      .attr("data-supported", isSupported ? "1" : "0")
      .attr("data-current", isCurrent ? "1" : "0")
      .text(text);
    if (!isCurrent && isSupported && !firstSelectable) {
      firstSelectable = key;
    }
    select.append(opt);
  });

  const prevInfo = previousVal ? formats[previousVal] : null;
  const prevSelectable = Boolean(
    prevInfo && prevInfo.supported && previousVal !== currentFormat,
  );

  if (previousVal && (prevSelectable || !firstSelectable)) {
    select.val(previousVal);
  } else if (firstSelectable) {
    select.val(firstSelectable);
  } else {
    select.val(formatKeys[0]);
  }

  select.prop("disabled", false);
  reacg_update_converter_disabled_notice(converterWrap, currentFormat);
}

function reacg_format_bytes(bytes) {
  const num = Number(bytes) || 0;
  if (num <= 0) return "0 B";
  if (num < 1024) return num + " B";
  if (num < 1024 * 1024) return Math.round(num / 1024) + " KB";
  return (num / (1024 * 1024)).toFixed(2) + " MB";
}

function reacg_build_conversion_size_note(
  oldBytes,
  newBytes,
  oldHuman,
  newHuman,
) {
  const oldSize = Number(oldBytes) || 0;
  const newSize = Number(newBytes) || 0;
  if (oldSize <= 0 || newSize <= 0) {
    return "";
  }
  const fromLabel = oldHuman || reacg_format_bytes(oldSize);
  const toLabel = newHuman || reacg_format_bytes(newSize);
  const diffPct = Math.round(((newSize - oldSize) / oldSize) * 100);
  const pctStr =
    diffPct < 0
      ? ", " + diffPct + "%"
      : diffPct > 0
        ? ", +" + diffPct + "%"
        : "";
  return " (" + fromLabel + " → " + toLabel + pctStr + ")";
}

function reacg_bind_single_image_converter(modal, attachment, item) {
  const converterWrap = modal.find(".reacg-image-converter");
  if (!converterWrap.length) {
    return;
  }

  const select = converterWrap.find(".reacg-convert-format-select");
  const convertBtn = converterWrap.find(".reacg-convert-format-btn");
  const deleteCheckbox = converterWrap.find(
    ".reacg-convert-delete-original-checkbox",
  );
  const spinner = converterWrap.find(".reacg-convert-spinner");
  const statusEl = converterWrap.find(".reacg-convert-status");

  reacg_sync_converter_select(
    converterWrap,
    reacg_get_attachment_format(attachment, item),
    reacg_is_video_attachment(attachment),
  );

  select.on("change", function () {
    reacg_update_converter_disabled_notice(
      converterWrap,
      converterWrap.data("currentFormat") || "",
    );
  });

  converterWrap.find(".reacg-pro-badge").on("click", function (e) {
    e.preventDefault();
    if (typeof reacg_open_free_trial_offer_dialog === "function") {
      reacg_open_free_trial_offer_dialog({ utm_medium: "convert_image" });
    }
  });

  convertBtn.on("click", function (e) {
    e.preventDefault();
    if (!reacg_is_user_pro()) {
      if (typeof reacg_open_free_trial_offer_dialog === "function") {
        reacg_open_free_trial_offer_dialog({ utm_medium: "convert_image" });
      }
      return;
    }
    if (convertBtn.prop("disabled") || convertBtn.hasClass("disabled")) {
      return;
    }

    const targetFormat = String(select.val() || "").toLowerCase();
    if (!targetFormat) {
      return;
    }

    const deleteOriginal = deleteCheckbox.is(":checked") ? 1 : 0;

    statusEl
      .addClass("hidden")
      .removeClass("reacg-convert-status--success reacg-convert-status--error")
      .empty();
    convertBtn.prop("disabled", true);
    select.prop("disabled", true);
    deleteCheckbox.prop("disabled", true);
    spinner.addClass("is-active");

    jQuery
      .ajax({
        url: reacg.ajax_url,
        type: "POST",
        data: {
          action: "reacg_convert_image",
          [reacg.nonce]: reacg.nonce,
          attachment_id: attachment.get("id"),
          target_format: targetFormat,
          delete_original: deleteOriginal,
        },
      })
      .done(function (res) {
        if (!res || !res.success || !res.data) {
          const errMsg =
            res && res.data && res.data.message
              ? res.data.message
              : reacg.convert_failed || "Failed to convert image.";
          statusEl
            .removeClass("hidden")
            .addClass("reacg-convert-status--error")
            .text(errMsg);
          reacg_sync_converter_select(
            converterWrap,
            reacg_get_attachment_format(attachment, item),
            isVideo,
          );
          return;
        }

        const isVideo = reacg_is_video_attachment(attachment);
        const data = res.data;
        if (data.filename && !isVideo) {
          modal
            .find(".reacg-attachment-modal__filename-val")
            .text(data.filename);
        }
        if (data.filesizeHumanReadable && !isVideo) {
          modal
            .find(".reacg-attachment-modal__filesize-val")
            .text(data.filesizeHumanReadable);
        }
        const previewSrc = data.thumbnail_url || data.url;
        if (previewSrc) {
          if (!isVideo) {
            modal
              .find(".reacg-attachment-modal__preview img")
              .attr("src", previewSrc);
          } else {
            modal
              .find(".reacg-attachment-modal__preview video")
              .attr("poster", previewSrc);
          }
          if (item && item.length) {
            item
              .find(".reacg_item_image img")
              .attr("src", previewSrc)
              .each(function () {
                reacg_update_item_image_fit(this);
              });
          }
        }
        if (previewSrc) {
          modal.find("#reacg-attachment-url").val(previewSrc);
        } else if (data.url && !isVideo) {
          modal.find("#reacg-attachment-url").val(data.url);
        }

        const baseMsg =
          data.message ||
          (isVideo
            ? reacg.video_cover_converted_successfully ||
              "Video cover converted to %s."
            : reacg.converted_successfully || "Converted to %s."
          ).replace("%s", String(data.format || targetFormat).toUpperCase());
        const sizeNote = data.skipped
          ? ""
          : reacg_build_conversion_size_note(
              data.old_filesize,
              data.filesize,
              data.old_filesizeHumanReadable,
              data.filesizeHumanReadable,
            );

        statusEl
          .removeClass("hidden")
          .addClass("reacg-convert-status--success")
          .text(baseMsg + sizeNote);

        attachment.fetch().always(function () {
          if (isVideo && previewSrc) {
            attachment.set("video_cover_url", previewSrc);
            attachment.set("video_cover_format", targetFormat);
            attachment.set(
              "video_cover_mime",
              targetFormat === "webp" ? "image/webp" : "image/avif",
            );
          }
          reacg_sync_converter_select(converterWrap, targetFormat, isVideo);
          reacg_reload_preview();
        });
      })
      .fail(function (xhr) {
        const errMsg =
          xhr &&
          xhr.responseJSON &&
          xhr.responseJSON.data &&
          xhr.responseJSON.data.message
            ? xhr.responseJSON.data.message
            : reacg.convert_failed || "Failed to convert image.";
        statusEl
          .removeClass("hidden")
          .addClass("reacg-convert-status--error")
          .text(errMsg);
        reacg_sync_converter_select(
          converterWrap,
          reacg_get_attachment_format(attachment, item),
          reacg_is_video_attachment(attachment),
        );
      })
      .always(function () {
        spinner.removeClass("is-active");
      });
  });
}

function reacg_get_bulk_shared_image_format(attachments) {
  const convertibleAtts = (attachments || []).filter(
    reacg_is_convertible_attachment,
  );
  if (!convertibleAtts.length) {
    return "";
  }
  const formats = convertibleAtts
    .map(function (att) {
      return reacg_get_attachment_format(att);
    })
    .filter(Boolean);
  const first = formats[0];
  if (
    first &&
    formats.length === convertibleAtts.length &&
    formats.every(function (fmt) {
      return fmt === first;
    })
  ) {
    return first;
  }
  return "";
}

function reacg_bind_bulk_image_converter(modal, attachments, selectedItems) {
  const converterWrap = modal.find(".reacg-image-converter");
  if (!converterWrap.length) {
    return;
  }

  const select = converterWrap.find(".reacg-convert-format-select");
  const convertBtn = converterWrap.find(".reacg-convert-format-btn");
  const deleteCheckbox = converterWrap.find(
    ".reacg-convert-delete-original-checkbox",
  );
  const spinner = converterWrap.find(".reacg-convert-spinner");
  const statusEl = converterWrap.find(".reacg-convert-status");
  const saveBtn = modal.find(".reacg-modal-button-save");

  reacg_sync_converter_select(
    converterWrap,
    reacg_get_bulk_shared_image_format(attachments),
  );

  select.on("change", function () {
    reacg_update_converter_disabled_notice(
      converterWrap,
      converterWrap.data("currentFormat") || "",
    );
  });

  converterWrap.find(".reacg-pro-badge").on("click", function (e) {
    e.preventDefault();
    if (typeof reacg_open_free_trial_offer_dialog === "function") {
      reacg_open_free_trial_offer_dialog({ utm_medium: "convert_image" });
    }
  });

  convertBtn.on("click", function (e) {
    e.preventDefault();
    if (!reacg_is_user_pro()) {
      if (typeof reacg_open_free_trial_offer_dialog === "function") {
        reacg_open_free_trial_offer_dialog({ utm_medium: "convert_image" });
      }
      return;
    }
    if (convertBtn.prop("disabled") || convertBtn.hasClass("disabled")) {
      return;
    }

    const targetFormat = String(select.val() || "").toLowerCase();
    if (!targetFormat) {
      return;
    }

    const activeSingleId = modal.data("activeSingleId");
    const targetAttachments = activeSingleId
      ? attachments.filter(function (att) {
          return String(att.get("id")) === String(activeSingleId);
        })
      : attachments;

    const convertibleAttachments = (targetAttachments || []).filter(
      reacg_is_convertible_attachment,
    );
    const toConvert = convertibleAttachments.filter(function (att) {
      return reacg_get_attachment_format(att) !== targetFormat;
    });

    if (!toConvert.length) {
      const isSingleVideo = Boolean(
        activeSingleId &&
        targetAttachments.length &&
        reacg_is_video_attachment(targetAttachments[0]),
      );
      const alreadyMsg = (
        isSingleVideo
          ? reacg.video_cover_already_in_format ||
            "Video cover is already in %s format."
          : reacg.bulk_all_already_in_format ||
            "All selected images are already in %s format."
      ).replace("%s", targetFormat.toUpperCase());
      statusEl
        .removeClass("hidden reacg-convert-status--error")
        .addClass("reacg-convert-status--success")
        .text(alreadyMsg);
      reacg_sync_converter_select(converterWrap, targetFormat, isSingleVideo);
      return;
    }

    const deleteOriginal = deleteCheckbox.is(":checked") ? 1 : 0;
    const total = toConvert.length;
    let index = 0;
    let convertedCount = 0;
    let totalOldBytes = 0;
    let totalNewBytes = 0;
    let firstError = "";

    convertBtn.prop("disabled", true);
    select.prop("disabled", true);
    deleteCheckbox.prop("disabled", true);
    saveBtn.attr("disabled", true);
    spinner.addClass("is-active");

    statusEl
      .removeClass(
        "hidden reacg-convert-status--success reacg-convert-status--error",
      )
      .text((reacg.converting || "Converting...") + " 1/" + total);

    const processNext = function () {
      if (index >= total) {
        const refetches = toConvert.map(function (att) {
          return att.fetch();
        });

        jQuery.when.apply(jQuery, refetches).always(function () {
          spinner.removeClass("is-active");
          saveBtn.removeAttr("disabled");

          const isSingleVideo = Boolean(
            activeSingleId &&
            targetAttachments.length &&
            reacg_is_video_attachment(targetAttachments[0]),
          );

          reacg_sync_converter_select(
            converterWrap,
            activeSingleId && targetAttachments.length
              ? reacg_get_attachment_format(targetAttachments[0])
              : reacg_get_bulk_shared_image_format(attachments),
            isSingleVideo,
          );

          if (convertedCount > 0) {
            reacg_reload_preview();
            let summaryText = "";
            if (isSingleVideo) {
              summaryText = (
                reacg.video_cover_converted_successfully ||
                "Video cover converted to %s."
              ).replace("%s", targetFormat.toUpperCase());
            } else if (activeSingleId && targetAttachments.length) {
              summaryText = (
                reacg.converted_successfully || "Converted to %s."
              ).replace("%s", targetFormat.toUpperCase());
            } else {
              const summaryTemplate =
                reacg.bulk_converted_summary ||
                "Converted %1$s of %2$s images to %3$s.";
              summaryText = summaryTemplate
                .replace("%1$s", String(convertedCount))
                .replace("%2$s", String(total))
                .replace("%3$s", targetFormat.toUpperCase());
            }
            const sizeNote = reacg_build_conversion_size_note(
              totalOldBytes,
              totalNewBytes,
              "",
              "",
            );
            statusEl
              .removeClass("hidden reacg-convert-status--error")
              .addClass("reacg-convert-status--success")
              .text(summaryText + sizeNote);
          } else {
            statusEl
              .removeClass("hidden reacg-convert-status--success")
              .addClass("reacg-convert-status--error")
              .text(
                firstError ||
                  reacg.convert_failed ||
                  "Failed to convert image.",
              );
          }
        });
        return;
      }

      const currentAtt = toConvert[index];
      const attId = currentAtt.get("id");
      statusEl.text(
        (reacg.converting || "Converting...") + " " + (index + 1) + "/" + total,
      );
      index++;

      jQuery
        .ajax({
          url: reacg.ajax_url,
          type: "POST",
          data: {
            action: "reacg_convert_image",
            [reacg.nonce]: reacg.nonce,
            attachment_id: attId,
            target_format: targetFormat,
            delete_original: deleteOriginal,
          },
        })
        .done(function (res) {
          if (res && res.success && res.data) {
            convertedCount++;
            const data = res.data;
            totalOldBytes += Number(data.old_filesize) || 0;
            totalNewBytes += Number(data.filesize) || 0;

            const previewSrc = data.thumbnail_url || data.url;
            if (previewSrc) {
              const thumbItem = modal.find(
                '.reacg-bulk-thumb-item[data-id="' + attId + '"]',
              );
              thumbItem.find("img").attr("src", previewSrc);
              thumbItem.find("video").attr("poster", previewSrc);
              if (selectedItems && selectedItems.length) {
                selectedItems
                  .filter('[data-id="' + attId + '"]')
                  .find(".reacg_item_image img")
                  .attr("src", previewSrc)
                  .each(function () {
                    reacg_update_item_image_fit(this);
                  });
              }
              if (currentAtt && typeof currentAtt.set === "function") {
                if (reacg_is_video_attachment(currentAtt)) {
                  currentAtt.set("video_cover_url", previewSrc);
                  currentAtt.set("video_cover_format", targetFormat);
                  currentAtt.set(
                    "video_cover_mime",
                    targetFormat === "webp" ? "image/webp" : "image/avif",
                  );
                }
              }
            }
          } else if (!firstError && res && res.data && res.data.message) {
            firstError = res.data.message;
          }
        })
        .fail(function (xhr) {
          if (
            !firstError &&
            xhr &&
            xhr.responseJSON &&
            xhr.responseJSON.data &&
            xhr.responseJSON.data.message
          ) {
            firstError = xhr.responseJSON.data.message;
          }
        })
        .always(processNext);
    };

    processNext();
  });
}

function reacg_attachment_edit_modal(attachment, item) {
  const sizes = attachment.get("sizes");
  const attachmentType = reacg_get_attachment_field_value(attachment, "type");
  const mimeType = reacg_get_attachment_field_value(attachment, "mime");
  const isVideo =
    attachmentType === "video" ||
    (typeof mimeType === "string" && mimeType.indexOf("video/") === 0);
  const attachmentUrl = reacg_get_attachment_field_value(attachment, "url");
  const previewSize =
    sizes && sizes.thumbnail
      ? sizes.thumbnail
      : sizes && sizes.full
        ? sizes.full
        : null;
  const previewUrl =
    previewSize && previewSize.url
      ? previewSize.url
      : attachmentUrl || reacg.no_image;
  const previewMedia =
    isVideo && attachmentUrl
      ? '<video controls preload="metadata" src="' +
        reacg_escape_html(attachmentUrl) +
        '"></video>'
      : '<img src="' + reacg_escape_html(previewUrl) + '" alt="" />';
  const filename = reacg_get_attachment_field_value(attachment, "filename");
  const dimensions =
    reacg_get_attachment_field_value(attachment, "width") &&
    reacg_get_attachment_field_value(attachment, "height")
      ? reacg_get_attachment_field_value(attachment, "width") +
        " x " +
        reacg_get_attachment_field_value(attachment, "height")
      : "";
  const fileSize =
    reacg_get_attachment_field_value(attachment, "filesizeHumanReadable") || "";
  const editLink = reacg_get_attachment_edit_link(attachment.get("id"));

  return jQuery(
    "" +
      '<div class="reacg-modal reacg-attachment-modal" style="display:none;">' +
      '<div class="reacg-modal-wrapper">' +
      '<div class="reacg-modal-content">' +
      '<div class="reacg-modal__header">' +
      "<h1>" +
      reacg.edit +
      "</h1>" +
      '<span class="reacg-modal-close"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M19 6.41 17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"></path></svg></span>' +
      "</div>" +
      '<div class="reacg-modal__body">' +
      '<div class="reacg-modal__layout">' +
      '<div class="reacg-modal__layout-content">' +
      '<div class="reacg-attachment-modal__preview">' +
      previewMedia +
      "</div>" +
      '<div class="reacg-attachment-modal__details">' +
      (filename
        ? '<p class="reacg-attachment-modal__filename"><strong>' +
          reacg_escape_html(reacg.attachment_filename) +
          ':</strong> <span class="reacg-attachment-modal__filename-val">' +
          reacg_escape_html(filename) +
          "</span></p>"
        : "") +
      (dimensions
        ? "<p><strong>" +
          reacg_escape_html(reacg.attachment_dimensions) +
          ":</strong> " +
          reacg_escape_html(dimensions) +
          "px</p>"
        : "") +
      (fileSize
        ? '<p class="reacg-attachment-modal__filesize"><strong>' +
          reacg_escape_html(reacg.attachment_file_size) +
          ':</strong> <span class="reacg-attachment-modal__filesize-val">' +
          reacg_escape_html(fileSize) +
          "</span></p>"
        : "") +
      '<p class="reacg-attachment-modal__edit-link"><a href="' +
      reacg_escape_html(editLink) +
      '" target="_blank" rel="noopener noreferrer">' +
      reacg.edit +
      "</a></p>" +
      "</div>" +
      reacg_crop_position_picker() +
      reacg_image_converter_picker() +
      "</div>" +
      '<div class="reacg-modal__layout-content">' +
      '<div class="reacg-attachment-modal__field" data-setting="title"><label for="reacg-attachment-title">' +
      reacg_escape_html(reacg.attachment_title) +
      '</label><input type="text" id="reacg-attachment-title" class="reacg-attachment-modal__input" value="' +
      reacg_escape_html(reacg_get_attachment_field_value(attachment, "title")) +
      '" /></div>' +
      '<div class="reacg-attachment-modal__field" data-setting="caption"><label for="reacg-attachment-caption">' +
      reacg_escape_html(reacg.attachment_caption) +
      '</label><textarea id="reacg-attachment-caption" rows="3">' +
      reacg_escape_html(
        reacg_get_attachment_field_value(attachment, "caption"),
      ) +
      "</textarea></div>" +
      '<div class="reacg-attachment-modal__field" data-setting="alt"><label for="reacg-attachment-alt">' +
      reacg_escape_html(reacg.attachment_alt_text) +
      '</label><input type="text" id="reacg-attachment-alt" class="reacg-attachment-modal__input" value="' +
      reacg_escape_html(reacg_get_attachment_field_value(attachment, "alt")) +
      '" /></div>' +
      '<div class="reacg-attachment-modal__field" data-setting="description"><label for="reacg-attachment-description">' +
      reacg_escape_html(reacg.attachment_description) +
      '</label><textarea id="reacg-attachment-description" rows="4">' +
      reacg_escape_html(
        reacg_get_attachment_field_value(attachment, "description"),
      ) +
      "</textarea></div>" +
      '<div class="reacg-attachment-modal__field" data-setting="action_url"><label for="reacg-attachment-action-url">' +
      reacg_escape_html(reacg.attachment_action_url) +
      '</label><input type="url" id="reacg-attachment-action-url" class="reacg-attachment-modal__input" value="' +
      reacg_escape_html(
        reacg_get_attachment_field_value(attachment, "action_url"),
      ) +
      '" placeholder="https://example.com" /></div>' +
      '<div class="reacg-attachment-modal__field" data-setting="categories"><label for="reacg-attachment-categories">' +
      reacg_escape_html(reacg.attachment_categories || "Categories") +
      '</label><select id="reacg-attachment-categories" class="reacg_searchable_select reacg-attachment-categories" multiple="multiple" style="width: 100%;">' +
      "</select></div>" +
      '<div class="reacg-attachment-modal__field" data-setting="exif"><label for="reacg-attachment-exif">' +
      reacg_escape_html(reacg.attachment_metadata_exif) +
      '</label><textarea id="reacg-attachment-exif" rows="5">' +
      reacg_escape_html(reacg_get_attachment_field_value(attachment, "exif")) +
      "</textarea></div>" +
      '<div class="reacg-attachment-modal__field reacg-hidden" data-setting="url"><label for="reacg-attachment-url">' +
      reacg_escape_html(reacg.attachment_url) +
      '</label><input type="hidden" id="reacg-attachment-url" class="reacg-attachment-modal__input" value="' +
      reacg_escape_html(
        isVideo
          ? item &&
            item.length &&
            item.find(".reacg_item_image img").attr("src") &&
            item.find(".reacg_item_image img").attr("src") !== reacg.no_image
            ? item.find(".reacg_item_image img").attr("src")
            : (attachment && typeof attachment.get === "function"
                ? attachment.get("video_cover_url")
                : null) ||
              (item && item.length
                ? item.find(".reacg_item_image img").attr("src")
                : "") ||
              reacg_get_attachment_field_value(attachment, "url")
          : reacg_get_attachment_field_value(attachment, "url"),
      ) +
      '" /></div>' +
      "</div>" +
      "</div>" +
      '<div><p class="reacg-modal-error-note hidden"></p></div>' +
      "</div>" +
      '<div class="reacg-modal__footer"><div class="reacg-modal-buttons-wrapper"><span class="spinner"></span><button class="reacg-modal-button-save button button-primary button-large">' +
      reacg.update +
      "</button></div></div>" +
      "</div>" +
      "</div>" +
      "</div>",
  );
}

function reacg_get_top_document() {
  try {
    if (window.top && window.top.document && window.top.document.body) {
      return window.top.document;
    }
  } catch (e) {}
  try {
    if (
      window.parent &&
      window.parent.document &&
      window.parent.document.body
    ) {
      return window.parent.document;
    }
  } catch (e) {}
  return document;
}

function reacg_open_attachment_edit_modal(button, attachment, item) {
  const modal = reacg_attachment_edit_modal(attachment, item);
  const targetDoc = reacg_get_top_document();
  const targetWin = targetDoc.defaultView || window;
  const $ = targetWin.jQuery || jQuery;
  $(targetDoc.body).append(modal);
  modal.css("display", "flex").show();
  modal
    .find(
      'input[name="reacg-attachment-crop-position"][value="' +
        (reacg_get_attachment_field_value(attachment, "crop_position") ||
          "center center") +
        '"]',
    )
    .prop("checked", true);
  reacg_bind_single_image_converter(modal, attachment, item);
  reacg_add_ai_button_to(modal.find(".reacg-modal__layout-content"));

  const categoriesSelect = modal.find("#reacg-attachment-categories");
  const rawAssigned = attachment.get("categories") || [];
  const assignedIds = [];

  rawAssigned.forEach(function (cat) {
    if (!cat) return;
    const catId =
      typeof cat === "object" ? String(cat.id || cat.name) : String(cat);
    const catName = typeof cat === "object" ? cat.name : String(cat);
    assignedIds.push(catId);
    categoriesSelect.append(new Option(catName, catId, true, true));
  });

  if ($.fn.select2) {
    reacg_init_select2($, categoriesSelect, {
      tags: true,
      placeholder:
        reacg.attachment_categories_placeholder ||
        "Search or add categories...",
      dropdownParent: modal,
    });
  }

  jQuery.ajax({
    url: reacg.ajax_url,
    type: "POST",
    data: {
      action: "reacg_get_categories",
      [reacg.nonce]: reacg.nonce,
    },
    success: function (res) {
      if (res.success && Array.isArray(res.data)) {
        res.data.forEach(function (cat) {
          const catId = String(cat.id);
          if (!categoriesSelect.find('option[value="' + catId + '"]').length) {
            categoriesSelect.append(new Option(cat.name, catId, false, false));
          }
        });
        if ($.fn.select2) {
          $(categoriesSelect).trigger("change");
        }
      }
    },
  });

  const closeModal = function () {
    modal.remove();
  };

  modal.find(".reacg-modal-close").on("click", closeModal);
  modal.on("click", function (e) {
    if (e.target === this) {
      closeModal();
    }
  });
  modal.find(".reacg-modal-wrapper").on("click", function (e) {
    e.stopPropagation();
  });

  modal.find(".reacg-modal-button-save").on("click", function () {
    const saveButton = jQuery(this);
    const spinner = saveButton
      .closest(".reacg-modal-buttons-wrapper")
      .find("> .spinner")
      .first();
    const errorNoteCont = modal.find(".reacg-modal-error-note");
    const attachmentId = attachment.get("id");
    const coreChanges = {
      title: modal.find("#reacg-attachment-title").val(),
      caption: modal.find("#reacg-attachment-caption").val(),
      alt: modal.find("#reacg-attachment-alt").val(),
      description: modal.find("#reacg-attachment-description").val(),
    };
    const currentCategoryVals = categoriesSelect.val() || [];
    const normInitialCats = assignedIds.slice().sort().join(",");
    const normCurrentCats = currentCategoryVals.slice().sort().join(",");
    const hasCategoryChanges = normInitialCats !== normCurrentCats;

    const compatChanges = {
      attachments: {},
    };
    compatChanges.attachments[attachmentId] = {
      action_url: modal.find("#reacg-attachment-action-url").val(),
      exif: modal.find("#reacg-attachment-exif").val(),
      crop_position: modal
        .find('input[name="reacg-attachment-crop-position"]:checked')
        .val(),
      regallery_categories: currentCategoryVals.length
        ? currentCategoryVals
        : "",
    };

    const normalizeValue = function (value) {
      return value == null ? "" : String(value);
    };
    const hasCoreChanges =
      normalizeValue(coreChanges.title) !==
        normalizeValue(reacg_get_attachment_field_value(attachment, "title")) ||
      normalizeValue(coreChanges.caption) !==
        normalizeValue(
          reacg_get_attachment_field_value(attachment, "caption"),
        ) ||
      normalizeValue(coreChanges.alt) !==
        normalizeValue(reacg_get_attachment_field_value(attachment, "alt")) ||
      normalizeValue(coreChanges.description) !==
        normalizeValue(
          reacg_get_attachment_field_value(attachment, "description"),
        );
    const hasCompatChanges =
      hasCategoryChanges ||
      normalizeValue(compatChanges.attachments[attachmentId].action_url) !==
        normalizeValue(
          reacg_get_attachment_field_value(attachment, "action_url"),
        ) ||
      normalizeValue(compatChanges.attachments[attachmentId].exif) !==
        normalizeValue(reacg_get_attachment_field_value(attachment, "exif")) ||
      normalizeValue(compatChanges.attachments[attachmentId].crop_position) !==
        normalizeValue(
          reacg_get_attachment_field_value(attachment, "crop_position") ||
            "center center",
        );

    if (!hasCoreChanges && !hasCompatChanges) {
      closeModal();
      button.removeAttr("disabled");
      return;
    }

    errorNoteCont.addClass("hidden").empty();
    saveButton.attr("disabled", true);
    spinner.addClass("is-active");

    const saveRequests = [];
    if (hasCoreChanges) {
      saveRequests.push(attachment.save(coreChanges));
    }
    if (hasCompatChanges) {
      saveRequests.push(attachment.saveCompat(compatChanges));
    }

    const saveRequest =
      saveRequests.length === 1
        ? saveRequests[0]
        : jQuery.when.apply(jQuery, saveRequests);

    saveRequest
      .done(function () {
        if (item && item.length) {
          const newTitle = modal.find("#reacg-attachment-title").val();
          item.find(".reacg_item_image").attr("title", newTitle);
        }
        attachment.fetch().always(function () {
          reacg_reload_preview();
          closeModal();
        });
      })
      .fail(function (response) {
        const message =
          response &&
          response.responseJSON &&
          response.responseJSON.data &&
          response.responseJSON.data.message
            ? response.responseJSON.data.message
            : reacg.save_failed;
        errorNoteCont.removeClass("hidden").html(message);
      })
      .always(function () {
        spinner.removeClass("is-active");
        saveButton.removeAttr("disabled");
        button.removeAttr("disabled");
      });
  });

  return modal;
}

function reacg_ai_button() {
  return jQuery(
    '<button class="reacg-ai-button" title="' +
      reacg.generate +
      '">' +
      reacg_ai_icon() +
      "</button>",
  );
}

function reacg_is_bulk_selectable_item(item) {
  const $item = jQuery(item);
  const type = String($item.data("type") || "");
  const id = String($item.data("id") || "");
  if (
    type.includes("dynamic") ||
    id.includes("dynamic") ||
    (reacg.allowed_post_types && reacg.allowed_post_types.hasOwnProperty(type))
  ) {
    return false;
  }
  return !$item.find(".reacg-item-select").hasClass("reacg-hidden");
}

function reacg_update_bulk_toolbar(container) {
  const root =
    container && container.length
      ? jQuery(container).closest(
          "#gallery-images, #reacg-gallery-images, .inside, body",
        )
      : jQuery(document);
  const items = root
    .find(".reacg_items .reacg_item:not(.reacg_item_new):not(.reacg-template)")
    .filter(function () {
      return reacg_is_bulk_selectable_item(this);
    });
  const selected = items.filter(".reacg-item--selected");
  const toolbar = root.find(".reacg-media-toolbar");
  const selectAll = toolbar.find(".reacg-select-all-checkbox");
  const countBadge = toolbar.find(".reacg-selected-count");
  const bulkBtn = toolbar.find(".reacg-bulk-edit-btn");

  selectAll.prop("disabled", items.length === 0);

  if (selected.length > 0) {
    bulkBtn.removeAttr("disabled");
    const countTemplate = reacg.selected_items || "%s items selected";
    const countText = countTemplate.replace("%s", selected.length);
    countBadge
      .attr("title", countText)
      .empty()
      .append(
        jQuery('<span class="reacg-selected-count__full"></span>').text(
          countText,
        ),
        jQuery('<span class="reacg-selected-count__short"></span>').text(
          selected.length,
        ),
      )
      .show();
  } else {
    bulkBtn.attr("disabled", "disabled");
    countBadge.hide();
  }

  if (items.length > 0 && selected.length === items.length) {
    selectAll.prop("checked", true).prop("indeterminate", false);
  } else if (selected.length > 0) {
    selectAll.prop("checked", false).prop("indeterminate", true);
  } else {
    selectAll.prop("checked", false).prop("indeterminate", false);
  }
}

function reacg_clear_bulk_selection(containerOrItems) {
  const root =
    containerOrItems && containerOrItems.length
      ? containerOrItems.closest(
          "#gallery-images, #reacg-gallery-images, .inside, body",
        )
      : jQuery("#gallery-images, #reacg-gallery-images, .inside, body").first();

  const items = root.find(
    ".reacg_items .reacg_item:not(.reacg_item_new):not(.reacg-template)",
  );

  items.each(function () {
    const item = jQuery(this);
    item.find(".reacg-item-checkbox").prop("checked", false);
    item.removeClass("reacg-item--selected");
  });

  reacg_update_bulk_toolbar(root);
}
window.reacg_clear_bulk_selection = reacg_clear_bulk_selection;

function reacg_run_bulk_generate_in_chunks(
  imagesData,
  state,
  chunkSize,
  onProgress,
) {
  const images = imagesData && typeof imagesData === "object" ? imagesData : {};
  const imageEntries = Object.entries(images);
  const deferred = jQuery.Deferred();
  const responses = [];
  const errors = [];
  const notifyProgress =
    typeof onProgress === "function" ? onProgress : function () {};

  const readPayloadCode = function (payload) {
    const source = payload || {};

    if (source.responseJSON) {
      if (source.responseJSON.code) {
        return String(source.responseJSON.code);
      }
      if (source.responseJSON.data && source.responseJSON.data.code) {
        return String(source.responseJSON.data.code);
      }
      if (
        source.responseJSON.error_data &&
        typeof source.responseJSON.error_data === "object"
      ) {
        const errorDataKeys = Object.keys(source.responseJSON.error_data);
        if (errorDataKeys.length) {
          return String(errorDataKeys[0]);
        }
      }
      if (
        source.responseJSON.errors &&
        typeof source.responseJSON.errors === "object"
      ) {
        const errorKeys = Object.keys(source.responseJSON.errors);
        if (errorKeys.length) {
          return String(errorKeys[0]);
        }
      }
    }

    if (source.responseText && typeof source.responseText === "string") {
      try {
        const parsed = JSON.parse(source.responseText);
        if (parsed && parsed.code) {
          return String(parsed.code);
        }
        if (parsed && parsed.data && parsed.data.code) {
          return String(parsed.data.code);
        }
        if (
          parsed &&
          parsed.error_data &&
          typeof parsed.error_data === "object"
        ) {
          const errorDataKeys = Object.keys(parsed.error_data);
          if (errorDataKeys.length) {
            return String(errorDataKeys[0]);
          }
        }
        if (parsed && parsed.errors && typeof parsed.errors === "object") {
          const errorKeys = Object.keys(parsed.errors);
          if (errorKeys.length) {
            return String(errorKeys[0]);
          }
        }
      } catch (ignored) {}
    }

    return "";
  };

  const isNothingToGeneratePayload = function (payload) {
    return readPayloadCode(payload) === "nothing_to_generate";
  };

  if (!imageEntries.length) {
    notifyProgress(0, 0);
    deferred.resolve({
      responses: [],
      errors: [],
      images: images,
      closeOnStatus200: false,
    });
    return deferred.promise();
  }

  notifyProgress(0, imageEntries.length);

  const normalizeGeneratedResponse = function (response) {
    let responseObject = response;
    if (typeof responseObject === "string") {
      try {
        responseObject = JSON.parse(responseObject);
      } catch (e) {
        responseObject = {};
      }
    }

    if (responseObject && responseObject.responseJSON) {
      responseObject = responseObject.responseJSON;
    }

    const source =
      responseObject && typeof responseObject === "object"
        ? responseObject.data && typeof responseObject.data === "object"
          ? responseObject.data
          : responseObject
        : {};

    const normalized = {};
    ["title", "alt", "caption", "description"].forEach(function (fieldName) {
      if (Object.prototype.hasOwnProperty.call(source, fieldName)) {
        normalized[fieldName] =
          source[fieldName] == null ? "" : String(source[fieldName]);
      }
    });

    return normalized;
  };

  const applyGeneratedDataToImage = function (
    imageId,
    imageData,
    generatedFields,
  ) {
    const baseImage =
      imageData && typeof imageData === "object" ? imageData : {};
    const nextImage = jQuery.extend({}, baseImage);
    let changed = false;

    ["title", "alt", "caption", "description"].forEach(function (fieldName) {
      if (!Object.prototype.hasOwnProperty.call(generatedFields, fieldName)) {
        return;
      }

      nextImage[fieldName] = generatedFields[fieldName];
      changed = true;
    });

    if (changed) {
      images[imageId] = nextImage;
    }

    return nextImage;
  };

  const saveGeneratedDataToAttachment = function (
    imageId,
    previousImageData,
    generatedFields,
  ) {
    const deferredSave = jQuery.Deferred();
    const normalizedImageId = parseInt(imageId, 10);
    const saveData = {};
    const normalizeValue = function (value) {
      return value == null ? "" : String(value);
    };

    ["title", "alt", "caption", "description"].forEach(function (fieldName) {
      if (!Object.prototype.hasOwnProperty.call(generatedFields, fieldName)) {
        return;
      }

      const previousValue = normalizeValue(
        previousImageData && previousImageData[fieldName],
      );
      const nextValue = normalizeValue(generatedFields[fieldName]);
      if (previousValue !== nextValue) {
        saveData[fieldName] = nextValue;
      }
    });

    if (!Object.keys(saveData).length) {
      deferredSave.resolve();
      return deferredSave.promise();
    }

    if (!normalizedImageId) {
      deferredSave.resolve();
      return deferredSave.promise();
    }

    if (!wp || !wp.media || typeof wp.media.attachment !== "function") {
      deferredSave.resolve();
      return deferredSave.promise();
    }

    const attachment = wp.media.attachment(normalizedImageId);
    if (!attachment || typeof attachment.save !== "function") {
      deferredSave.resolve();
      return deferredSave.promise();
    }

    const persist = function () {
      attachment
        .save(saveData)
        .done(function (response) {
          deferredSave.resolve(response);
        })
        .fail(function (xhr) {
          deferredSave.reject(xhr);
        });
    };

    if (typeof attachment.fetch === "function") {
      attachment
        .fetch()
        .done(function () {
          persist();
        })
        .fail(function (xhr) {
          deferredSave.reject(xhr);
        });
    } else {
      persist();
    }

    return deferredSave.promise();
  };

  const runChunk = function (index) {
    if (index >= imageEntries.length) {
      notifyProgress(imageEntries.length, imageEntries.length);
      if (errors.length) {
        deferred.reject({
          responses: responses,
          errors: errors,
          images: images,
        });
        return;
      }

      const successCount = responses.filter(function (entry) {
        return parseInt(entry.statusCode, 10) === 200;
      }).length;
      const closeOnStatus200 = successCount > 0;
      deferred.resolve({
        responses: responses,
        errors: [],
        images: images,
        closeOnStatus200: closeOnStatus200,
        successCount: successCount,
      });
      return;
    }

    const imageEntry = imageEntries[index];
    const imageId = imageEntry[0];
    const imageData = imageEntry[1];
    const singleImageData =
      imageData && typeof imageData === "object" ? imageData : {};

    notifyProgress(index + 1, imageEntries.length);

    let finalImageUrl = singleImageData.url || "";
    if (
      !finalImageUrl ||
      /\.(mp4|m4v|webm|ogv|mov|mkv)(\?.*)?$/i.test(finalImageUrl)
    ) {
      const domImg = jQuery(
        '.reacg_items .reacg_item[data-id="' +
          imageId +
          '"] .reacg_item_image img',
      ).attr("src");
      if (
        domImg &&
        domImg !== reacg.no_image &&
        domImg.indexOf("video.png") === -1 &&
        domImg.indexOf("video.svg") === -1
      ) {
        finalImageUrl = domImg;
      } else if (
        typeof wp !== "undefined" &&
        wp.media &&
        typeof wp.media.attachment === "function"
      ) {
        const attModel = wp.media.attachment(imageId);
        if (
          attModel &&
          typeof attModel.get === "function" &&
          attModel.get("video_cover_url")
        ) {
          finalImageUrl = attModel.get("video_cover_url");
        }
      }
    }

    jQuery
      .ajax({
        type: "GET",
        url: reacg.core_rest_url_v2 + "ai",
        contentType: "application/json",
        data: {
          image_id: imageId,
          image_url: finalImageUrl,
          title: singleImageData.title || "",
          alt: singleImageData.alt || "",
          caption: singleImageData.caption || "",
          description: singleImageData.description || "",
          website_language: reacg.website_language || "",
          user_language:
            reacg.user_language || document.documentElement.lang || "",
          fields: JSON.stringify(state.fields),
          overwrite_mode: state.overwriteMode,
          action: "get_content",
        },
      })
      .done(function (response, textStatus, xhr) {
        const statusCode =
          xhr && typeof xhr.status !== "undefined"
            ? parseInt(xhr.status, 10)
            : 0;
        const previousImageData = jQuery.extend({}, singleImageData);
        const generatedFields = normalizeGeneratedResponse(response);
        const updatedImageData = applyGeneratedDataToImage(
          imageId,
          singleImageData,
          generatedFields,
        );

        saveGeneratedDataToAttachment(
          imageId,
          previousImageData,
          generatedFields,
        )
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
            errors.push({
              index: index,
              imageId: imageId,
              stage: "save",
              xhr: xhr,
            });
          })
          .always(function () {
            if (errors.length) {
              deferred.reject({
                responses: responses,
                errors: errors,
                images: images,
              });
              return;
            }

            runChunk(index + 1);
          });
      })
      .fail(function (xhr) {
        if (isNothingToGeneratePayload(xhr)) {
          responses.push({
            index: index,
            imageId: imageId,
            statusCode:
              xhr && typeof xhr.status !== "undefined"
                ? parseInt(xhr.status, 10)
                : 400,
            response: xhr,
            skipped: true,
            image: singleImageData,
          });
          runChunk(index + 1);
          return;
        }

        errors.push({
          index: index,
          imageId: imageId,
          stage: "generate",
          xhr: xhr,
        });
        deferred.reject({
          responses: responses,
          errors: errors,
          images: images,
        });
      });
  };

  runChunk(0);

  return deferred.promise();
}

function reacg_bulk_single_details_html(att) {
  const filename = reacg_get_attachment_field_value(att, "filename");
  const dimensions =
    reacg_get_attachment_field_value(att, "width") &&
    reacg_get_attachment_field_value(att, "height")
      ? reacg_get_attachment_field_value(att, "width") +
        " x " +
        reacg_get_attachment_field_value(att, "height")
      : "";
  const fileSize =
    reacg_get_attachment_field_value(att, "filesizeHumanReadable") || "";
  const editLink = reacg_get_attachment_edit_link(att.get("id"));

  return (
    (filename
      ? '<p class="reacg-attachment-modal__filename"><strong>' +
        reacg_escape_html(reacg.attachment_filename || "File name") +
        ':</strong> <span class="reacg-attachment-modal__filename-val">' +
        reacg_escape_html(filename) +
        "</span></p>"
      : "") +
    (dimensions
      ? "<p><strong>" +
        reacg_escape_html(reacg.attachment_dimensions || "Dimensions") +
        ":</strong> " +
        reacg_escape_html(dimensions) +
        "px</p>"
      : "") +
    (fileSize
      ? '<p class="reacg-attachment-modal__filesize"><strong>' +
        reacg_escape_html(reacg.attachment_file_size || "File size") +
        ':</strong> <span class="reacg-attachment-modal__filesize-val">' +
        reacg_escape_html(fileSize) +
        "</span></p>"
      : "") +
    '<p class="reacg-attachment-modal__edit-link"><a href="' +
    reacg_escape_html(editLink) +
    '" target="_blank" rel="noopener noreferrer">' +
    reacg_escape_html(reacg.edit || "Edit") +
    "</a></p>"
  );
}

function reacg_bulk_attachment_edit_modal(attachments, selectedItems) {
  const count = attachments.length;
  const hasConvertibleImages = attachments.some(
    reacg_is_convertible_attachment,
  );
  let thumbnailsHtml = "";
  attachments.forEach(function (att) {
    const sizes = att.get("sizes");
    const attachmentUrl = reacg_get_attachment_field_value(att, "url");
    const previewSize =
      sizes && sizes.thumbnail
        ? sizes.thumbnail
        : sizes && sizes.full
          ? sizes.full
          : null;
    const previewUrl =
      previewSize && previewSize.url
        ? previewSize.url
        : attachmentUrl || reacg.no_image;
    const isVideo = reacg_get_attachment_field_value(att, "type") === "video";
    const title = reacg_get_attachment_field_value(att, "title") || "";

    thumbnailsHtml +=
      '<div class="reacg-bulk-thumb-item" data-id="' +
      reacg_escape_html(att.get("id")) +
      '" title="' +
      reacg_escape_html(title) +
      '">';
    if (isVideo && attachmentUrl) {
      thumbnailsHtml +=
        '<video src="' + reacg_escape_html(attachmentUrl) + '"></video>';
    } else {
      thumbnailsHtml +=
        '<img src="' + reacg_escape_html(previewUrl) + '" alt="" />';
    }
    thumbnailsHtml += "</div>";
  });

  const allSelectedLabel = reacg.bulk_all_selected
    ? reacg.bulk_all_selected.replace("%s", count)
    : "Selected (" + count + ")";

  return jQuery(
    "" +
      '<div class="reacg-modal reacg-attachment-modal reacg-bulk-attachment-modal" style="display:none;">' +
      '<div class="reacg-modal-wrapper">' +
      '<div class="reacg-modal-content">' +
      '<div class="reacg-modal__header">' +
      '<h1 class="reacg-modal-title">' +
      reacg_escape_html(reacg.bulk_edit || "Bulk Edit") +
      "</h1>" +
      '<span class="reacg-modal-close"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M19 6.41 17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"></path></svg></span>' +
      "</div>" +
      '<div class="reacg-modal__body">' +
      '<div class="reacg-modal__layout">' +
      // LEFT COLUMN
      '<div class="reacg-modal__layout-content">' +
      '<div class="reacg-bulk-items-selector">' +
      '<div class="reacg-bulk-selection-bar">' +
      '<button type="button" class="button button-secondary reacg-bulk-select-all-btn is-active">' +
      '<span class="dashicons dashicons-images-alt2"></span>' +
      '<span class="reacg-bulk-select-all-btn__label">' +
      reacg_escape_html(allSelectedLabel) +
      "</span>" +
      "</button>" +
      "</div>" +
      '<div class="reacg-bulk-edit-thumbnails">' +
      thumbnailsHtml +
      "</div>" +
      "</div>" +
      '<div class="reacg-attachment-modal__details hidden">' +
      '<div class="reacg-bulk-single-details"></div>' +
      "</div>" +
      reacg_crop_position_picker() +
      reacg_image_converter_picker() +
      "</div>" +
      // RIGHT COLUMN
      '<div class="reacg-modal__layout-content">' +
      // Fields
      '<div class="reacg-attachment-modal__field" data-setting="title">' +
      reacg_bulk_field_header_html(
        "reacg-attachment-title",
        reacg.attachment_title,
      ) +
      '<input type="text" id="reacg-attachment-title" class="reacg-attachment-modal__input" /></div>' +
      '<div class="reacg-attachment-modal__field" data-setting="caption">' +
      reacg_bulk_field_header_html(
        "reacg-attachment-caption",
        reacg.attachment_caption,
      ) +
      '<textarea id="reacg-attachment-caption" rows="3"></textarea></div>' +
      '<div class="reacg-attachment-modal__field" data-setting="alt">' +
      reacg_bulk_field_header_html(
        "reacg-attachment-alt",
        reacg.attachment_alt_text,
      ) +
      '<input type="text" id="reacg-attachment-alt" class="reacg-attachment-modal__input" /></div>' +
      '<div class="reacg-attachment-modal__field" data-setting="description">' +
      reacg_bulk_field_header_html(
        "reacg-attachment-description",
        reacg.attachment_description,
      ) +
      '<textarea id="reacg-attachment-description" rows="4"></textarea></div>' +
      '<div class="reacg-attachment-modal__field" data-setting="action_url">' +
      reacg_bulk_field_header_html(
        "reacg-attachment-action-url",
        reacg.attachment_action_url,
      ) +
      '<input type="url" id="reacg-attachment-action-url" class="reacg-attachment-modal__input" /></div>' +
      '<div class="reacg-attachment-modal__field" data-setting="categories">' +
      '<div class="reacg-bulk-field-header">' +
      '<div class="reacg-bulk-field-header__left">' +
      '<label for="reacg-attachment-categories">' +
      reacg_escape_html(reacg.attachment_categories || "Categories") +
      "</label>" +
      "</div>" +
      '<div class="reacg-bulk-field-header__right">' +
      '<div class="reacg-bulk-cat-modes" role="tablist">' +
      '<button type="button" class="reacg-bulk-cat-mode is-active" data-mode="add">' +
      reacg_escape_html(reacg.categories_mode_add || "Add") +
      "</button>" +
      '<button type="button" class="reacg-bulk-cat-mode" data-mode="replace">' +
      reacg_escape_html(reacg.categories_mode_replace || "Replace") +
      "</button>" +
      '<button type="button" class="reacg-bulk-cat-mode" data-mode="remove">' +
      reacg_escape_html(reacg.categories_mode_remove || "Remove") +
      "</button>" +
      "</div>" +
      "</div>" +
      "</div>" +
      '<select id="reacg-attachment-categories" class="reacg_searchable_select reacg-attachment-categories" multiple="multiple" style="width: 100%;"></select></div>' +
      '<div class="reacg-attachment-modal__field" data-setting="exif">' +
      reacg_bulk_field_header_html(
        "reacg-attachment-exif",
        reacg.attachment_metadata_exif,
      ) +
      '<textarea id="reacg-attachment-exif" rows="5"></textarea></div>' +
      "</div>" +
      "</div>" +
      '<div><p class="reacg-modal-error-note hidden"></p></div>' +
      "</div>" +
      '<div class="reacg-modal__footer"><div class="reacg-modal-buttons-wrapper">' +
      '<span class="reacg-bulk-progress-text hidden"></span>' +
      '<span class="spinner"></span>' +
      '<button class="reacg-modal-button-save button button-primary button-large">' +
      reacg_escape_html(reacg.update) +
      "</button></div></div>" +
      "</div>" +
      "</div>" +
      "</div>",
  );
}

function reacg_bulk_field_header_html(inputId, labelText) {
  return (
    '<div class="reacg-bulk-field-header">' +
    '<div class="reacg-bulk-field-header__left">' +
    '<label for="' +
    reacg_escape_html(inputId) +
    '">' +
    reacg_escape_html(labelText) +
    "</label>" +
    "</div>" +
    '<div class="reacg-bulk-field-header__right">' +
    '<label class="reacg-bulk-only-empty-label hidden">' +
    '<input type="checkbox" class="reacg-bulk-only-empty-checkbox" />' +
    "<span>" +
    reacg_escape_html(reacg.only_empty_fields || "Only empty fields") +
    "</span>" +
    "</label>" +
    '<button type="button" class="reacg-bulk-field-clear hidden">' +
    reacg_escape_html(reacg.clear_field || "Clear") +
    "</button>" +
    "</div>" +
    "</div>"
  );
}

function reacg_analyze_bulk_field(attachments, fieldName) {
  const values = attachments.map(function (att) {
    const raw = reacg_get_attachment_field_value(att, fieldName);
    return raw == null ? "" : String(raw).trim();
  });
  const nonEmptyCount = values.filter(function (v) {
    return v !== "";
  }).length;
  const allEmpty = nonEmptyCount === 0;
  const allSame =
    values.length > 0 &&
    values.every(function (v) {
      return v === values[0];
    });
  const sharedValue = allSame ? values[0] : "";
  const hasMixed = !allSame;

  return {
    values: values,
    nonEmptyCount: nonEmptyCount,
    allEmpty: allEmpty,
    allSame: allSame,
    sharedValue: sharedValue,
    hasMixed: hasMixed,
  };
}

function reacg_get_attachment_categories(att) {
  const raw =
    (att && typeof att.get === "function" ? att.get("categories") : null) || [];
  const list = [];
  if (Array.isArray(raw)) {
    raw.forEach(function (cat) {
      if (!cat) return;
      const id =
        typeof cat === "object"
          ? String(cat.id || cat.name || "")
          : String(cat);
      const name =
        typeof cat === "object"
          ? String(cat.name || cat.id || "")
          : String(cat);
      if (id) {
        list.push({ id: id, name: name });
      }
    });
  }
  return list;
}

function reacg_analyze_bulk_categories(attachments) {
  const countsById = {};
  const namesById = {};
  const total = attachments.length;

  attachments.forEach(function (att) {
    const seenOnAtt = {};
    reacg_get_attachment_categories(att).forEach(function (cat) {
      if (seenOnAtt[cat.id]) return;
      seenOnAtt[cat.id] = true;
      countsById[cat.id] = (countsById[cat.id] || 0) + 1;
      namesById[cat.id] = cat.name;
    });
  });

  const anyIds = Object.keys(countsById);
  const sharedIds = anyIds.filter(function (id) {
    return total > 0 && countsById[id] === total;
  });

  return {
    countsById: countsById,
    namesById: namesById,
    anyIds: anyIds,
    sharedIds: sharedIds,
    hasMixed: anyIds.length !== sharedIds.length,
  };
}

function reacg_sync_bulk_text_fields(modal, attachments) {
  const textFields = [
    { name: "title", selector: "#reacg-attachment-title" },
    { name: "caption", selector: "#reacg-attachment-caption" },
    { name: "alt", selector: "#reacg-attachment-alt" },
    { name: "description", selector: "#reacg-attachment-description" },
    { name: "action_url", selector: "#reacg-attachment-action-url" },
    { name: "exif", selector: "#reacg-attachment-exif" },
  ];

  textFields.forEach(function (fieldCfg) {
    const fieldWrap = modal.find('[data-setting="' + fieldCfg.name + '"]');
    const input = fieldWrap.find(fieldCfg.selector);
    const clearBtn = fieldWrap.find(".reacg-bulk-field-clear");
    const onlyEmptyLabel = fieldWrap.find(".reacg-bulk-only-empty-label");
    const onlyEmptyCheckbox = fieldWrap.find(".reacg-bulk-only-empty-checkbox");
    if (!fieldWrap.length || !input.length) return;

    const state = reacg_analyze_bulk_field(attachments, fieldCfg.name);
    const defaultPlaceholder = state.hasMixed
      ? reacg.bulk_multiple_values || "— Multiple Values —"
      : fieldCfg.name === "action_url"
        ? "https://example.com"
        : reacg.bulk_empty || "— Empty —";

    fieldWrap.data("bulkFieldState", state);
    fieldWrap.data("defaultPlaceholder", defaultPlaceholder);
    fieldWrap.attr("data-bulk-clear", "0");

    onlyEmptyCheckbox.prop("checked", false);
    onlyEmptyLabel.addClass("hidden");

    input
      .prop("disabled", false)
      .removeClass("reacg-bulk-input--cleared")
      .attr("placeholder", defaultPlaceholder)
      .val(state.allSame ? state.sharedValue : "");

    clearBtn
      .removeClass("is-undo")
      .text(reacg.clear_field || "Clear")
      .toggleClass("hidden", state.nonEmptyCount === 0);

    input.off("input.reacgBulkField").on("input.reacgBulkField", function () {
      const currentState = fieldWrap.data("bulkFieldState") || {};
      const isCleared = fieldWrap.attr("data-bulk-clear") === "1";
      const hasTyped = String(input.val() || "").trim() !== "";
      const showOnlyEmpty =
        !isCleared &&
        currentState.hasMixed &&
        currentState.nonEmptyCount > 0 &&
        hasTyped;
      onlyEmptyLabel.toggleClass("hidden", !showOnlyEmpty);
    });

    clearBtn
      .off("click.reacgBulkField")
      .on("click.reacgBulkField", function (e) {
        e.preventDefault();
        const isCurrentlyCleared = fieldWrap.attr("data-bulk-clear") === "1";
        const currentState = fieldWrap.data("bulkFieldState") || {};
        const defPh = fieldWrap.data("defaultPlaceholder") || "";

        if (!isCurrentlyCleared) {
          fieldWrap.attr("data-bulk-clear", "1");
          input
            .val("")
            .prop("disabled", true)
            .addClass("reacg-bulk-input--cleared")
            .attr(
              "placeholder",
              reacg.bulk_will_be_cleared || "— Will be cleared —",
            );
          clearBtn.addClass("is-undo").text(reacg.undo || "Undo");
          onlyEmptyLabel.addClass("hidden");
        } else {
          fieldWrap.attr("data-bulk-clear", "0");
          input
            .prop("disabled", false)
            .removeClass("reacg-bulk-input--cleared")
            .attr("placeholder", defPh)
            .val(currentState.allSame ? currentState.sharedValue : "");
          clearBtn.removeClass("is-undo").text(reacg.clear_field || "Clear");
        }
      });
  });
}

function reacg_resolve_bulk_categories(rawSelected) {
  const deferred = jQuery.Deferred();
  const list = Array.isArray(rawSelected) ? rawSelected : [];
  if (!list.length) {
    deferred.resolve([]);
    return deferred.promise();
  }

  const resolvedIds = [];
  const createQueue = [];

  list.forEach(function (val) {
    const strVal = String(val || "").trim();
    if (!strVal) return;
    if (/^\d+$/.test(strVal)) {
      resolvedIds.push(strVal);
    } else {
      createQueue.push(strVal);
    }
  });

  if (!createQueue.length) {
    deferred.resolve(resolvedIds);
    return deferred.promise();
  }

  let idx = 0;
  const processNext = function () {
    if (idx >= createQueue.length) {
      deferred.resolve(resolvedIds);
      return;
    }
    const name = createQueue[idx++];
    jQuery
      .ajax({
        url: reacg.ajax_url,
        type: "POST",
        data: {
          action: "reacg_create_category",
          [reacg.nonce]: reacg.nonce,
          name: name,
        },
      })
      .done(function (res) {
        if (res && res.success && res.data && res.data.id) {
          resolvedIds.push(String(res.data.id));
        } else {
          resolvedIds.push(name);
        }
      })
      .fail(function () {
        resolvedIds.push(name);
      })
      .always(processNext);
  };

  processNext();
  return deferred.promise();
}

function reacg_save_single_attachment_in_bulk(
  modal,
  attachment,
  item,
  categoriesSelect,
  assignedCategoryIds,
) {
  const deferred = jQuery.Deferred();
  if (!attachment || typeof attachment.get !== "function") {
    deferred.resolve({ changed: false });
    return deferred.promise();
  }

  const attachmentId = attachment.get("id");
  const coreChanges = {
    title: modal.find("#reacg-attachment-title").val(),
    caption: modal.find("#reacg-attachment-caption").val(),
    alt: modal.find("#reacg-attachment-alt").val(),
    description: modal.find("#reacg-attachment-description").val(),
  };

  const currentCategoryVals =
    categoriesSelect && categoriesSelect.length
      ? categoriesSelect.val() || []
      : [];
  const normInitialCats = (assignedCategoryIds || []).slice().sort().join(",");
  const normCurrentCats = currentCategoryVals.slice().sort().join(",");
  const hasCategoryChanges = normInitialCats !== normCurrentCats;

  const cropPos = modal
    .find('input[name="reacg-attachment-crop-position"]:checked')
    .val();

  const compatChanges = {
    attachments: {},
  };
  compatChanges.attachments[attachmentId] = {
    action_url: modal.find("#reacg-attachment-action-url").val(),
    exif: modal.find("#reacg-attachment-exif").val(),
    crop_position: cropPos || "center center",
    regallery_categories: currentCategoryVals.length ? currentCategoryVals : "",
  };

  const normalizeValue = function (value) {
    return value == null ? "" : String(value);
  };
  const hasCoreChanges =
    normalizeValue(coreChanges.title) !==
      normalizeValue(reacg_get_attachment_field_value(attachment, "title")) ||
    normalizeValue(coreChanges.caption) !==
      normalizeValue(reacg_get_attachment_field_value(attachment, "caption")) ||
    normalizeValue(coreChanges.alt) !==
      normalizeValue(reacg_get_attachment_field_value(attachment, "alt")) ||
    normalizeValue(coreChanges.description) !==
      normalizeValue(
        reacg_get_attachment_field_value(attachment, "description"),
      );

  const hasCompatChanges =
    hasCategoryChanges ||
    normalizeValue(compatChanges.attachments[attachmentId].action_url) !==
      normalizeValue(
        reacg_get_attachment_field_value(attachment, "action_url"),
      ) ||
    normalizeValue(compatChanges.attachments[attachmentId].exif) !==
      normalizeValue(reacg_get_attachment_field_value(attachment, "exif")) ||
    normalizeValue(compatChanges.attachments[attachmentId].crop_position) !==
      normalizeValue(
        reacg_get_attachment_field_value(attachment, "crop_position") ||
          "center center",
      );

  if (!hasCoreChanges && !hasCompatChanges) {
    deferred.resolve({ changed: false });
    return deferred.promise();
  }

  const spinner = modal.find(".reacg-modal__footer .spinner");
  const progressText = modal.find(".reacg-bulk-progress-text");
  const saveBtn = modal.find(".reacg-modal-button-save");
  const errorNote = modal.find(".reacg-modal-error-note");

  errorNote.addClass("hidden").empty();
  saveBtn.attr("disabled", true);
  spinner.addClass("is-active");
  progressText.removeClass("hidden").text(reacg.saving || "Saving...");

  const saveRequests = [];
  if (hasCoreChanges) {
    saveRequests.push(attachment.save(coreChanges));
  }
  if (hasCompatChanges) {
    saveRequests.push(attachment.saveCompat(compatChanges));
  }

  const saveRequest =
    saveRequests.length === 1
      ? saveRequests[0]
      : jQuery.when.apply(jQuery, saveRequests);

  saveRequest
    .done(function () {
      if (item && item.length) {
        const newTitle = modal.find("#reacg-attachment-title").val();
        item.find(".reacg_item_image").attr("title", newTitle);
      }
      const thumb = modal.find(
        '.reacg-bulk-thumb-item[data-id="' + attachmentId + '"]',
      );
      if (thumb.length) {
        const newTitle = modal.find("#reacg-attachment-title").val();
        if (newTitle) {
          thumb.attr("title", newTitle);
        }
        thumb.addClass("is-saved");
      }
      attachment.fetch().always(function () {
        progressText.text(reacg.image_updated || "Image updated successfully.");
        setTimeout(function () {
          progressText.addClass("hidden").empty();
        }, 2200);
        deferred.resolve({ changed: true });
      });
    })
    .fail(function (response) {
      const message =
        response &&
        response.responseJSON &&
        response.responseJSON.data &&
        response.responseJSON.data.message
          ? response.responseJSON.data.message
          : reacg.save_failed || "Unable to save image data.";
      errorNote.removeClass("hidden").html(message);
      progressText.addClass("hidden").empty();
      deferred.reject(message);
    })
    .always(function () {
      spinner.removeClass("is-active");
      saveBtn.removeAttr("disabled");
    });

  return deferred.promise();
}

function reacg_save_bulk_attachment_changes(
  modal,
  attachments,
  selectedItems,
  cropPositionChanged,
) {
  const fieldDefs = [
    { name: "title", selector: "#reacg-attachment-title", isCore: true },
    { name: "caption", selector: "#reacg-attachment-caption", isCore: true },
    { name: "alt", selector: "#reacg-attachment-alt", isCore: true },
    {
      name: "description",
      selector: "#reacg-attachment-description",
      isCore: true,
    },
    {
      name: "action_url",
      selector: "#reacg-attachment-action-url",
      isCore: false,
    },
    { name: "exif", selector: "#reacg-attachment-exif", isCore: false },
  ];

  const fieldOperations = {};
  let hasFieldOperation = false;

  fieldDefs.forEach(function (def) {
    const fieldWrap = modal.find('[data-setting="' + def.name + '"]');
    const input = fieldWrap.find(def.selector);
    const state = fieldWrap.data("bulkFieldState") || {};
    const isCleared = fieldWrap.attr("data-bulk-clear") === "1";
    const onlyEmpty = fieldWrap
      .find(".reacg-bulk-only-empty-checkbox")
      .is(":checked");
    const rawVal = input.val() == null ? "" : String(input.val()).trim();

    let op = { action: "none", value: "", isCore: def.isCore };
    if (isCleared) {
      op = { action: "clear", value: "", isCore: def.isCore };
      hasFieldOperation = true;
    } else if (state.allSame && state.nonEmptyCount > 0) {
      if (rawVal !== state.sharedValue) {
        op = { action: "replace", value: rawVal, isCore: def.isCore };
        hasFieldOperation = true;
      }
    } else if (rawVal !== "") {
      op = {
        action: onlyEmpty ? "only_empty" : "replace",
        value: rawVal,
        isCore: def.isCore,
      };
      hasFieldOperation = true;
    }

    fieldOperations[def.name] = op;
  });

  const categoriesSelect = modal.find("#reacg-attachment-categories");
  const rawCategories = categoriesSelect.val() || [];
  const catState = modal.data("bulkCatState") || {
    mode: "add",
    replaceTouched: false,
  };
  const catMode = catState.mode || "add";
  const hasCategoryOperation =
    (catMode === "add" && rawCategories.length > 0) ||
    (catMode === "remove" && rawCategories.length > 0) ||
    (catMode === "replace" && catState.replaceTouched);

  const newCropPosition = modal
    .find('input[name="reacg-attachment-crop-position"]:checked')
    .val();
  const hasCropOperation = Boolean(cropPositionChanged && newCropPosition);

  if (!hasFieldOperation && !hasCategoryOperation && !hasCropOperation) {
    modal.remove();
    return;
  }

  const spinner = modal.find(".reacg-modal__footer .spinner");
  const progressText = modal.find(".reacg-bulk-progress-text");
  const saveBtn = modal.find(".reacg-modal-button-save");
  const errorNote = modal.find(".reacg-modal-error-note");

  errorNote.addClass("hidden").empty();
  spinner.addClass("is-active");
  saveBtn.attr("disabled", true);
  progressText
    .removeClass("hidden")
    .text(reacg.updating_items || "Updating items...");

  const categoryResolvePromise =
    hasCategoryOperation && (catMode === "add" || catMode === "replace")
      ? reacg_resolve_bulk_categories(rawCategories)
      : jQuery.Deferred().resolve(rawCategories).promise();

  categoryResolvePromise.then(function (resolvedCategoryIds) {
    const savePromises = [];
    const titleUpdatedAttachments = [];

    attachments.forEach(function (att) {
      const attId = att.get("id");
      const coreChanges = {};
      const compatChanges = { attachments: {} };
      compatChanges.attachments[attId] = {};

      let hasCore = false;
      let hasCompat = false;

      fieldDefs.forEach(function (def) {
        const op = fieldOperations[def.name];
        if (!op || op.action === "none") return;

        const currentVal = String(
          reacg_get_attachment_field_value(att, def.name) || "",
        ).trim();
        let shouldApply = false;
        let targetVal = op.value;

        if (op.action === "clear") {
          shouldApply = currentVal !== "";
          targetVal = "";
        } else if (op.action === "only_empty") {
          shouldApply = currentVal === "";
        } else if (op.action === "replace") {
          shouldApply = currentVal !== targetVal;
        }

        if (shouldApply) {
          if (def.isCore) {
            coreChanges[def.name] = targetVal;
            hasCore = true;
            if (def.name === "title") {
              titleUpdatedAttachments.push(att);
            }
          } else {
            compatChanges.attachments[attId][def.name] = targetVal;
            hasCompat = true;
          }
        }
      });

      if (hasCropOperation) {
        const currentCrop =
          reacg_get_attachment_field_value(att, "crop_position") ||
          "center center";
        if (currentCrop !== newCropPosition) {
          compatChanges.attachments[attId].crop_position = newCropPosition;
          hasCompat = true;
        }
      }

      if (hasCategoryOperation) {
        const existingIds = reacg_get_attachment_categories(att).map(
          function (c) {
            return String(c.id);
          },
        );
        let nextIds = existingIds.slice();

        if (catMode === "add") {
          const merged = existingIds.slice();
          resolvedCategoryIds.forEach(function (id) {
            const sId = String(id);
            if (merged.indexOf(sId) === -1) {
              merged.push(sId);
            }
          });
          nextIds = merged;
        } else if (catMode === "replace") {
          nextIds = resolvedCategoryIds.map(String);
        } else if (catMode === "remove") {
          const removeSet = {};
          resolvedCategoryIds.forEach(function (id) {
            removeSet[String(id)] = true;
          });
          nextIds = existingIds.filter(function (id) {
            return !removeSet[String(id)];
          });
        }

        const normExisting = existingIds.slice().sort().join(",");
        const normNext = nextIds.slice().sort().join(",");
        if (normExisting !== normNext) {
          compatChanges.attachments[attId].regallery_categories = nextIds.length
            ? nextIds
            : "";
          hasCompat = true;
        }
      }

      if (hasCore) {
        savePromises.push(att.save(coreChanges));
      }
      if (hasCompat) {
        savePromises.push(att.saveCompat(compatChanges));
      }
    });

    if (!savePromises.length) {
      spinner.removeClass("is-active");
      saveBtn.removeAttr("disabled");
      progressText.addClass("hidden");
      reacg_clear_bulk_selection(selectedItems);
      modal.remove();
      return;
    }

    jQuery.when
      .apply(jQuery, savePromises)
      .done(function () {
        const refetches = attachments.map(function (att) {
          return att.fetch();
        });
        jQuery.when.apply(jQuery, refetches).always(function () {
          if (titleUpdatedAttachments.length) {
            attachments.forEach(function (att) {
              const attId = att.get("id");
              const updatedTitle = reacg_get_attachment_field_value(
                att,
                "title",
              );
              selectedItems
                .filter('[data-id="' + attId + '"]')
                .find(".reacg_item_image")
                .attr("title", updatedTitle);
            });
          }
          reacg_clear_bulk_selection(selectedItems);
          reacg_reload_preview();
          modal.remove();
        });
      })
      .fail(function (err) {
        const message =
          err &&
          err.responseJSON &&
          err.responseJSON.data &&
          err.responseJSON.data.message
            ? err.responseJSON.data.message
            : reacg.save_failed || "Unable to save image data.";
        errorNote.removeClass("hidden").html(message);
      })
      .always(function () {
        spinner.removeClass("is-active");
        saveBtn.removeAttr("disabled");
        progressText.addClass("hidden");
      });
  });
}

function reacg_add_bulk_ai_buttons_to(
  container,
  attachments,
  selectedItems,
  modal,
) {
  const fields = [
    { name: "title", label: reacg.attachment_title },
    { name: "caption", label: reacg.attachment_caption },
    { name: "alt", label: reacg.attachment_alt_text },
    { name: "description", label: reacg.attachment_description },
  ];

  fields.forEach(function (field) {
    const fieldWrap = container.find('[data-setting="' + field.name + '"]');
    if (!fieldWrap.length || fieldWrap.find(".reacg-ai-button").length) return;

    const button = reacg_ai_button();
    const spinner = '<span class="spinner reacg-float-none"></span>';
    fieldWrap.find("label").first().after(button, spinner);

    button.on("click", function (e) {
      e.preventDefault();
      const spinnerCont = fieldWrap.find(".spinner");
      spinnerCont.addClass("is-active");
      button.attr("disabled", true);

      jQuery.ajax({
        type: "GET",
        url: reacg.core_rest_url_v2 + "ai",
        contentType: "application/json",
        data: { action: "check" },
        complete: function (res) {
          if (res.status === 200) {
            const activeSingleId = modal.data("activeSingleId");
            const targetAttachments = activeSingleId
              ? attachments.filter(function (att) {
                  return String(att.get("id")) === String(activeSingleId);
                })
              : attachments;

            const urlPromises = targetAttachments.map(function (att) {
              return reacg_resolve_attachment_ai_image_url(att, selectedItems);
            });

            Promise.all(urlPromises)
              .then(function (resolvedUrls) {
                spinnerCont.removeClass("is-active");
                button.removeAttr("disabled");

                const imagesData = {};
                targetAttachments.forEach(function (att, idx) {
                  const id = String(att.get("id"));
                  imagesData[id] = {
                    url: resolvedUrls[idx] || "",
                    title: activeSingleId
                      ? modal.find("#reacg-attachment-title").val() || ""
                      : reacg_get_attachment_field_value(att, "title") || "",
                    caption: activeSingleId
                      ? modal.find("#reacg-attachment-caption").val() || ""
                      : reacg_get_attachment_field_value(att, "caption") || "",
                    alt: activeSingleId
                      ? modal.find("#reacg-attachment-alt").val() || ""
                      : reacg_get_attachment_field_value(att, "alt") || "",
                    description: activeSingleId
                      ? modal.find("#reacg-attachment-description").val() || ""
                      : reacg_get_attachment_field_value(att, "description") ||
                        "",
                  };
                });

                reacg_open_ai_generate_content_modal(imagesData, {
                  targetField: field.name,
                  onSuccess: function () {
                    const refetches = targetAttachments.map(function (att) {
                      return att.fetch();
                    });
                    jQuery.when.apply(jQuery, refetches).always(function () {
                      targetAttachments.forEach(function (att) {
                        const attId = att.get("id");
                        const newTitle = reacg_get_attachment_field_value(
                          att,
                          "title",
                        );
                        if (newTitle) {
                          selectedItems
                            .filter('[data-id="' + attId + '"]')
                            .find(".reacg_item_image")
                            .attr("title", newTitle);
                          modal
                            .find(
                              '.reacg-bulk-thumb-item[data-id="' + attId + '"]',
                            )
                            .attr("title", newTitle);
                        }
                      });
                      if (activeSingleId && targetAttachments.length) {
                        ["title", "alt", "caption", "description"].forEach(
                          function (fname) {
                            modal
                              .find("#reacg-attachment-" + fname)
                              .val(
                                reacg_get_attachment_field_value(
                                  targetAttachments[0],
                                  fname,
                                ),
                              );
                          },
                        );
                        modal.data("bulkSnapshotDirty", true);
                      } else {
                        reacg_sync_bulk_text_fields(modal, attachments);
                      }
                      reacg_reload_preview();
                    });
                  },
                });
              })
              .catch(function () {
                spinnerCont.removeClass("is-active");
                button.removeAttr("disabled");
              });
          } else {
            spinnerCont.removeClass("is-active");
            button.removeAttr("disabled");
            const errMsg =
              res && res.responseJSON && res.responseJSON.errors
                ? res.responseJSON.errors.message
                : "AI generation is not available.";
            const errorNote = modal.find(".reacg-modal-error-note");
            errorNote.removeClass("hidden").html(errMsg);
            if (String(errMsg).indexOf("reacg-upgrade-link") !== -1) {
              errorNote
                .find(".reacg-upgrade-link")
                .off("click")
                .on("click", function (ev) {
                  ev.preventDefault();
                  reacg_open_free_trial_offer_dialog({
                    utm_medium: "ai_generate",
                  });
                });
            }
          }
        },
      });
    });
  });
}

function reacg_open_bulk_attachment_edit_modal(
  button,
  attachments,
  selectedItems,
  galleryItemsContainer,
) {
  const targetDoc = reacg_get_top_document();
  const targetWin = targetDoc.defaultView || window;
  const $ = targetWin.jQuery || jQuery;

  const modal = reacg_bulk_attachment_edit_modal(attachments, selectedItems);
  $(targetDoc.body).append(modal);
  modal.css("display", "flex").show();

  const textFields = [
    { name: "title", selector: "#reacg-attachment-title" },
    { name: "caption", selector: "#reacg-attachment-caption" },
    { name: "alt", selector: "#reacg-attachment-alt" },
    { name: "description", selector: "#reacg-attachment-description" },
    { name: "action_url", selector: "#reacg-attachment-action-url" },
    { name: "exif", selector: "#reacg-attachment-exif" },
  ];

  let activeAttachment = null;
  let activeItem = null;
  let activeAssignedCategoryIds = [];
  let bulkSnapshot = null;
  let cropPositionChanged = false;
  let isRenderingCatSelect = false;

  const categoriesSelect = modal.find("#reacg-attachment-categories");
  const catAnalysis = reacg_analyze_bulk_categories(attachments);
  const allCategoriesMap = jQuery.extend({}, catAnalysis.namesById);
  const catState = {
    mode: "add",
    replaceTouched: false,
    selections: {
      add: [],
      replace: catAnalysis.sharedIds.slice(),
      remove: [],
    },
  };
  modal.data("bulkCatState", catState);

  const captureBulkSnapshot = function () {
    const snapshot = {
      fields: {},
      cropPositionChanged: cropPositionChanged,
      cropValue: modal
        .find('input[name="reacg-attachment-crop-position"]:checked')
        .val(),
    };
    textFields.forEach(function (f) {
      const fieldWrap = modal.find('[data-setting="' + f.name + '"]');
      const input = fieldWrap.find(f.selector);
      snapshot.fields[f.name] = {
        val: input.val(),
        isCleared: fieldWrap.attr("data-bulk-clear") === "1",
        onlyEmpty: fieldWrap
          .find(".reacg-bulk-only-empty-checkbox")
          .is(":checked"),
        onlyEmptyVisible: !fieldWrap
          .find(".reacg-bulk-only-empty-label")
          .hasClass("hidden"),
      };
    });
    return snapshot;
  };

  const restoreBulkSnapshot = function (snapshot) {
    if (!snapshot) {
      reacg_sync_bulk_text_fields(modal, attachments);
      return;
    }
    textFields.forEach(function (f) {
      const fieldWrap = modal.find('[data-setting="' + f.name + '"]');
      const input = fieldWrap.find(f.selector);
      const clearBtn = fieldWrap.find(".reacg-bulk-field-clear");
      const onlyEmptyLabel = fieldWrap.find(".reacg-bulk-only-empty-label");
      const onlyEmptyCheckbox = fieldWrap.find(
        ".reacg-bulk-only-empty-checkbox",
      );
      const state = reacg_analyze_bulk_field(attachments, f.name);
      const saved = snapshot.fields[f.name];

      fieldWrap.data("bulkFieldState", state);
      const defaultPlaceholder = state.hasMixed
        ? reacg.bulk_multiple_values || "— Multiple Values —"
        : f.name === "action_url"
          ? "https://example.com"
          : reacg.bulk_empty || "— Empty —";
      fieldWrap.data("defaultPlaceholder", defaultPlaceholder);

      if (saved && saved.isCleared) {
        fieldWrap.attr("data-bulk-clear", "1");
        input
          .val("")
          .prop("disabled", true)
          .addClass("reacg-bulk-input--cleared")
          .attr(
            "placeholder",
            reacg.bulk_will_be_cleared || "— Will be cleared —",
          );
        clearBtn.addClass("is-undo").text(reacg.undo || "Undo");
        onlyEmptyLabel.addClass("hidden");
      } else {
        fieldWrap.attr("data-bulk-clear", "0");
        input
          .prop("disabled", false)
          .removeClass("reacg-bulk-input--cleared")
          .attr("placeholder", defaultPlaceholder)
          .val(saved ? saved.val : state.allSame ? state.sharedValue : "");
        clearBtn.removeClass("is-undo").text(reacg.clear_field || "Clear");
        clearBtn.toggleClass("hidden", state.nonEmptyCount === 0);
        onlyEmptyCheckbox.prop("checked", Boolean(saved && saved.onlyEmpty));
        onlyEmptyLabel.toggleClass(
          "hidden",
          !(saved && saved.onlyEmptyVisible),
        );
      }
    });

    if (snapshot.cropValue) {
      modal
        .find(
          'input[name="reacg-attachment-crop-position"][value="' +
            snapshot.cropValue +
            '"]',
        )
        .prop("checked", true);
    } else {
      modal
        .find('input[name="reacg-attachment-crop-position"]')
        .prop("checked", false);
    }
    cropPositionChanged = Boolean(snapshot.cropPositionChanged);
  };

  const switchToAllSelected = function () {
    const savePromise = activeAttachment
      ? reacg_save_single_attachment_in_bulk(
          modal,
          activeAttachment,
          activeItem,
          categoriesSelect,
          activeAssignedCategoryIds,
        )
      : jQuery.Deferred().resolve().promise();

    savePromise.always(function () {
      activeAttachment = null;
      activeItem = null;
      activeAssignedCategoryIds = [];
      modal.data("activeSingleId", null);

      modal.find(".reacg-bulk-thumb-item").removeClass("is-active");
      modal.find(".reacg-bulk-select-all-btn").addClass("is-active");
      modal.removeClass("reacg-modal--single-active");

      modal
        .find(".reacg-modal__header h1, .reacg-modal-title")
        .text(reacg.bulk_edit || "Bulk Edit");

      modal.find(".reacg-attachment-modal__details").addClass("hidden");
      modal.find(".reacg-bulk-single-details").empty();

      modal.find(".reacg-bulk-field-header__right").removeClass("hidden");
      modal.find(".reacg-bulk-cat-modes").removeClass("hidden");

      if (modal.data("bulkSnapshotDirty")) {
        modal.data("bulkSnapshotDirty", false);
        bulkSnapshot = null;
        reacg_sync_bulk_text_fields(modal, attachments);
      } else {
        restoreBulkSnapshot(bulkSnapshot);
      }
      renderCategoriesSelectForMode(catState.mode);

      modal.find(".reacg-image-converter").each(function () {
        reacg_sync_converter_select(
          jQuery(this),
          reacg_get_bulk_shared_image_format(attachments),
          false,
        );
      });

      modal.find(".reacg-modal-button-save").text(reacg.update || "Update");
    });
  };

  const switchToSingleAttachment = function (att, item) {
    try {
      const attId =
        att && typeof att.get === "function"
          ? att.get("id")
          : att
            ? att.id
            : null;
      if (!attId) return;

      if (
        activeAttachment &&
        String(
          activeAttachment.get
            ? activeAttachment.get("id")
            : activeAttachment.id,
        ) === String(attId)
      ) {
        switchToAllSelected();
        return;
      }

      if (!activeAttachment) {
        bulkSnapshot = captureBulkSnapshot();
      }

      const savePrevPromise = activeAttachment
        ? reacg_save_single_attachment_in_bulk(
            modal,
            activeAttachment,
            activeItem,
            categoriesSelect,
            activeAssignedCategoryIds,
          )
        : jQuery.Deferred().resolve().promise();

      savePrevPromise.always(function () {
        activeAttachment = att;
        activeItem = item;
        modal.data("activeSingleId", attId);

        modal.find(".reacg-bulk-select-all-btn").removeClass("is-active");
        modal.find(".reacg-bulk-thumb-item").removeClass("is-active");
        modal
          .find('.reacg-bulk-thumb-item[data-id="' + attId + '"]')
          .addClass("is-active");
        modal.addClass("reacg-modal--single-active");

        modal
          .find(".reacg-modal__header h1, .reacg-modal-title")
          .text(reacg.edit || "Edit");

        modal.find(".reacg-attachment-modal__details").removeClass("hidden");
        modal
          .find(".reacg-bulk-single-details")
          .html(reacg_bulk_single_details_html(att));

        const attCrop =
          reacg_get_attachment_field_value(att, "crop_position") ||
          "center center";
        modal
          .find(
            'input[name="reacg-attachment-crop-position"][value="' +
              attCrop +
              '"]',
          )
          .prop("checked", true);

        modal.find(".reacg-bulk-field-header__right").addClass("hidden");
        modal.find(".reacg-bulk-cat-modes").addClass("hidden");

        textFields.forEach(function (f) {
          const fieldWrap = modal.find('[data-setting="' + f.name + '"]');
          const input = fieldWrap.find(f.selector);
          input
            .prop("disabled", false)
            .removeClass("reacg-bulk-input--cleared")
            .attr(
              "placeholder",
              f.name === "action_url" ? "https://example.com" : "",
            )
            .val(reacg_get_attachment_field_value(att, f.name));
        });

        isRenderingCatSelect = true;
        try {
          if (
            $.fn.select2 &&
            $(categoriesSelect).hasClass("select2-hidden-accessible")
          ) {
            $(categoriesSelect).select2("destroy");
          }
        } catch (err) {}
        categoriesSelect.empty();

        const rawAssigned =
          att && typeof att.get === "function"
            ? att.get("categories") || []
            : [];
        activeAssignedCategoryIds = [];
        const assignedSet = {};

        rawAssigned.forEach(function (cat) {
          if (!cat) return;
          const catId =
            typeof cat === "object" ? String(cat.id || cat.name) : String(cat);
          const catName = typeof cat === "object" ? cat.name : String(cat);
          activeAssignedCategoryIds.push(catId);
          assignedSet[catId] = true;
        });

        Object.keys(allCategoriesMap).forEach(function (id) {
          const strId = String(id);
          const name = allCategoriesMap[strId] || strId;
          const isSelected = Boolean(assignedSet[strId]);
          categoriesSelect.append(
            new Option(name, strId, isSelected, isSelected),
          );
        });

        rawAssigned.forEach(function (cat) {
          if (!cat) return;
          const catId =
            typeof cat === "object" ? String(cat.id || cat.name) : String(cat);
          const catName = typeof cat === "object" ? cat.name : String(cat);
          if (!categoriesSelect.find('option[value="' + catId + '"]').length) {
            categoriesSelect.append(new Option(catName, catId, true, true));
          }
        });

        if ($.fn.select2) {
          try {
            reacg_init_select2($, categoriesSelect, {
              tags: true,
              placeholder:
                reacg.attachment_categories_placeholder ||
                "Search or add categories...",
              dropdownParent: modal,
            });
          } catch (e) {}
        }
        isRenderingCatSelect = false;

        modal
          .find(".reacg-modal-button-save")
          .text(reacg.update_item || "Update Item");

        const isSingleVideo = reacg_is_video_attachment(att);
        modal.find(".reacg-image-converter").each(function () {
          reacg_sync_converter_select(
            jQuery(this),
            reacg_get_attachment_format(att, item),
            isSingleVideo,
          );
        });
      });
    } catch (err) {
      console.error("reacg: error switching to single attachment", err);
    }
  };

  reacg_sync_bulk_text_fields(modal, attachments);

  const cropPositions = attachments.map(function (att) {
    return (
      reacg_get_attachment_field_value(att, "crop_position") || "center center"
    );
  });
  const allSameCrop =
    cropPositions.length > 0 &&
    cropPositions.every(function (pos) {
      return pos === cropPositions[0];
    });
  if (allSameCrop) {
    modal
      .find(
        'input[name="reacg-attachment-crop-position"][value="' +
          cropPositions[0] +
          '"]',
      )
      .prop("checked", true);
  }

  modal
    .find('input[name="reacg-attachment-crop-position"]')
    .on("change", function () {
      cropPositionChanged = true;
    });

  reacg_bind_bulk_image_converter(modal, attachments, selectedItems);

  reacg_add_bulk_ai_buttons_to(
    modal.find(".reacg-modal__layout-content").eq(1),
    attachments,
    selectedItems,
    modal,
  );

  if (catAnalysis.anyIds.length === 0) {
    modal
      .find('.reacg-bulk-cat-mode[data-mode="remove"]')
      .prop("disabled", true);
  }

  const renderCategoriesSelectForMode = function (mode) {
    isRenderingCatSelect = true;
    const selectedVals = (catState.selections[mode] || []).map(String);
    const selectedSet = {};
    selectedVals.forEach(function (v) {
      selectedSet[v] = true;
    });

    if (
      $.fn.select2 &&
      $(categoriesSelect).hasClass("select2-hidden-accessible")
    ) {
      $(categoriesSelect).select2("destroy");
    }

    categoriesSelect.empty();

    const optionIds =
      mode === "remove"
        ? catAnalysis.anyIds.slice()
        : Object.keys(allCategoriesMap);

    optionIds.forEach(function (id) {
      const strId = String(id);
      const name = allCategoriesMap[strId] || strId;
      const isSelected = Boolean(selectedSet[strId]);
      categoriesSelect.append(new Option(name, strId, isSelected, isSelected));
    });

    if (mode !== "remove") {
      selectedVals.forEach(function (val) {
        if (!categoriesSelect.find('option[value="' + val + '"]').length) {
          categoriesSelect.append(new Option(val, val, true, true));
        }
      });
    }

    let placeholder =
      reacg.attachment_categories_placeholder || "Search or add categories...";
    if (mode === "remove") {
      placeholder =
        reacg.bulk_categories_remove_placeholder ||
        "Select categories to remove...";
    } else if (
      mode === "replace" &&
      catAnalysis.hasMixed &&
      selectedVals.length === 0
    ) {
      placeholder = reacg.bulk_multiple_values || "— Multiple Values —";
    }

    if ($.fn.select2) {
      reacg_init_select2($, categoriesSelect, {
        tags: mode !== "remove",
        placeholder: placeholder,
        dropdownParent: modal,
      });
    }
    isRenderingCatSelect = false;
  };

  renderCategoriesSelectForMode("add");

  $(categoriesSelect).on("change.reacgBulkCats", function () {
    if (isRenderingCatSelect) return;
    if (activeAttachment) return;
    const vals = $(categoriesSelect).val() || [];
    catState.selections[catState.mode] = vals;
    vals.forEach(function (v) {
      const strV = String(v);
      if (!allCategoriesMap[strV]) {
        const optText = categoriesSelect
          .find('option[value="' + strV + '"]')
          .text();
        allCategoriesMap[strV] = optText || strV;
      }
    });
    if (catState.mode === "replace") {
      catState.replaceTouched = true;
    }
  });

  modal.find(".reacg-bulk-cat-mode").on("click", function (e) {
    e.preventDefault();
    const btn = jQuery(this);
    if (btn.prop("disabled")) return;
    const newMode = String(btn.data("mode") || "add");
    if (newMode === catState.mode) return;

    catState.selections[catState.mode] = $(categoriesSelect).val() || [];
    catState.mode = newMode;
    if (newMode === "replace") {
      catState.replaceTouched = true;
    }

    modal.find(".reacg-bulk-cat-mode").removeClass("is-active");
    btn.addClass("is-active");
    renderCategoriesSelectForMode(newMode);
  });

  jQuery.ajax({
    url: reacg.ajax_url,
    type: "POST",
    data: {
      action: "reacg_get_categories",
      [reacg.nonce]: reacg.nonce,
    },
    success: function (res) {
      if (res.success && Array.isArray(res.data)) {
        res.data.forEach(function (cat) {
          const catId = String(cat.id);
          allCategoriesMap[catId] = cat.name;
        });
        if (!activeAttachment) {
          catState.selections[catState.mode] = $(categoriesSelect).val() || [];
          renderCategoriesSelectForMode(catState.mode);
        }
      }
    },
  });

  const handleThumbClick = function (e) {
    if (e) {
      e.preventDefault();
      e.stopPropagation();
    }
    const $item = jQuery(this).closest(".reacg-bulk-thumb-item");
    const id = String($item.attr("data-id") || $item.data("id") || "");
    if (!id) return;

    const att = attachments.find(function (a) {
      const aId =
        a && typeof a.get === "function" ? a.get("id") : a ? a.id : null;
      return String(aId) === id;
    });
    const itm = selectedItems.filter(function () {
      const itmId = jQuery(this).attr("data-id") || jQuery(this).data("id");
      return String(itmId) === id;
    });
    if (att) {
      switchToSingleAttachment(att, itm);
    }
  };

  modal.find(".reacg-bulk-select-all-btn").on("click", function (e) {
    e.preventDefault();
    switchToAllSelected();
  });

  modal
    .find(".reacg-bulk-edit-thumbnails")
    .on("click", ".reacg-bulk-thumb-item", handleThumbClick);
  modal.find(".reacg-bulk-thumb-item").on("click", handleThumbClick);

  try {
    const rawThumbContainer = modal.find(".reacg-bulk-edit-thumbnails")[0];
    if (rawThumbContainer) {
      rawThumbContainer.addEventListener("click", function (ev) {
        const thumbEl = ev.target.closest(".reacg-bulk-thumb-item");
        if (thumbEl) {
          handleThumbClick.call(thumbEl, ev);
        }
      });
    }
    const rawAllBtn = modal.find(".reacg-bulk-select-all-btn")[0];
    if (rawAllBtn) {
      rawAllBtn.addEventListener("click", function (ev) {
        ev.preventDefault();
        switchToAllSelected();
      });
    }
  } catch (err) {}

  const closeModal = function () {
    if (activeAttachment) {
      reacg_save_single_attachment_in_bulk(
        modal,
        activeAttachment,
        activeItem,
        categoriesSelect,
        activeAssignedCategoryIds,
      ).always(function () {
        modal.remove();
        reacg_reload_preview();
      });
    } else {
      modal.remove();
    }
  };

  modal.find(".reacg-modal-close").on("click", closeModal);
  modal.on("click", function (e) {
    if (e.target === this) {
      closeModal();
    }
  });

  modal.find(".reacg-modal-button-save").on("click", function () {
    if (activeAttachment) {
      reacg_save_single_attachment_in_bulk(
        modal,
        activeAttachment,
        activeItem,
        categoriesSelect,
        activeAssignedCategoryIds,
      );
    } else {
      reacg_save_bulk_attachment_changes(
        modal,
        attachments,
        selectedItems,
        cropPositionChanged,
      );
    }
  });

  return modal;
}

function reacg_open_ai_generate_content_modal(images, options) {
  const imagesData = images && typeof images === "object" ? images : {};
  const chunkObject = function (source, size) {
    const entries = Object.entries(source || {});
    const chunks = [];

    for (let i = 0; i < entries.length; i += size) {
      chunks.push(Object.fromEntries(entries.slice(i, i + size)));
    }

    return chunks;
  };

  const runBulkGenerateInChunks = function (state, chunkSize, onProgress) {
    return reacg_run_bulk_generate_in_chunks(
      imagesData,
      state,
      chunkSize,
      onProgress,
    );
  };

  const settings = jQuery.extend(
    true,
    {
      chunkSize: 5,
      targetField: null,
      fields: {
        title: true,
        alt: false,
        caption: false,
        description: false,
      },
      overwriteMode: "empty",
      onGenerate: null,
      onSuccess: null,
      onClose: null,
    },
    options || {},
  );

  const checkedAttr = function (value) {
    return value ? ' checked="checked"' : "";
  };
  const selectedOverwrite =
    settings.overwriteMode === "replace" ? "replace" : "empty";

  const fieldsSectionHtml = settings.targetField
    ? ""
    : "<h3>" +
      reacg_escape_html(reacg.choose_what_to_generate) +
      "</h3>" +
      '<div class="reacg-ai-generate-modal__fields">' +
      '<label><input type="checkbox" name="title" value="1"' +
      checkedAttr(settings.fields.title) +
      ' /><span class="reacg-ai-generate-modal__field-name">' +
      reacg_escape_html(reacg.attachment_title) +
      "</span></label>" +
      '<label><input type="checkbox" name="caption" value="1"' +
      checkedAttr(settings.fields.caption) +
      ' /><span class="reacg-ai-generate-modal__field-name">' +
      reacg_escape_html(reacg.attachment_caption) +
      "</span></label>" +
      '<label><input type="checkbox" name="alt" value="1"' +
      checkedAttr(settings.fields.alt) +
      ' /><span class="reacg-ai-generate-modal__field-name">' +
      reacg_escape_html(reacg.attachment_alt_text) +
      "</span></label>" +
      '<label><input type="checkbox" name="description" value="1"' +
      checkedAttr(settings.fields.description) +
      ' /><span class="reacg-ai-generate-modal__field-name">' +
      reacg_escape_html(reacg.attachment_description) +
      "</span></label>" +
      "</div>";

  const modal = jQuery(
    "" +
      '<div class="reacg-modal reacg-ai-generate-modal" style="display:none;">' +
      '<div class="reacg-modal-wrapper">' +
      '<div class="reacg-modal-content">' +
      '<div class="reacg-modal__header">' +
      "<h1>" +
      reacg_escape_html(reacg.ai_generate_content) +
      "</h1>" +
      '<span class="reacg-modal-close"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M19 6.41 17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"></path></svg></span>' +
      "</div>" +
      '<div class="reacg-modal__body">' +
      '<div class="reacg-modal__layout-content">' +
      '<div><p class="reacg-modal-note">' +
      reacg_info_icon() +
      reacg_escape_html(reacg.content_generated_based_on_image) +
      "</p></div>" +
      fieldsSectionHtml +
      "<h3>" +
      reacg_escape_html(reacg.overwrite_existing) +
      "</h3>" +
      '<div class="reacg-ai-generate-modal__overwrite">' +
      '<label><input type="radio" name="reacg-overwrite" value="empty"' +
      checkedAttr(selectedOverwrite === "empty") +
      " /><span>" +
      reacg_escape_html(reacg.only_empty_fields) +
      "</span></label>" +
      '<label><input type="radio" name="reacg-overwrite" value="replace"' +
      checkedAttr(selectedOverwrite === "replace") +
      " /><span>" +
      reacg_escape_html(reacg.replace_existing) +
      "</span></label>" +
      "</div>" +
      '<p class="reacg-ai-generate-modal__notice hidden"></p>' +
      "</div>" +
      "</div>" +
      '<div class="reacg-modal__footer">' +
      '<div class="reacg-modal-buttons-wrapper">' +
      '<span class="reacg-ai-generate-modal__progress hidden"></span>' +
      '<span class="spinner"></span>' +
      '<button class="button button-primary button-large reacg-modal-button-generate">' +
      reacg_ai_icon() +
      reacg_escape_html(reacg.generate) +
      "</button>" +
      "</div>" +
      "</div>" +
      "</div>" +
      "</div>" +
      "</div>",
  );

  const getState = function () {
    const fields = settings.targetField
      ? { [settings.targetField]: true }
      : {
          title: modal.find('input[name="title"]').is(":checked"),
          alt: modal.find('input[name="alt"]').is(":checked"),
          caption: modal.find('input[name="caption"]').is(":checked"),
          description: modal.find('input[name="description"]').is(":checked"),
        };

    return {
      fields: fields,
      overwriteMode:
        modal.find('input[name="reacg-overwrite"]:checked').val() || "empty",
    };
  };

  const closeModal = function () {
    if (typeof settings.onClose === "function") {
      settings.onClose();
    }
    modal.remove();
  };

  const targetDoc = reacg_get_top_document();
  const targetWin = targetDoc.defaultView || window;
  (targetWin.jQuery || jQuery)(targetDoc.body).append(modal);
  modal.css("display", "flex").show();

  modal.find(".reacg-modal-close").on("click", closeModal);
  modal.on("click", function (e) {
    if (e.target === this) {
      closeModal();
    }
  });
  modal.find(".reacg-modal-wrapper").on("click", function (e) {
    e.stopPropagation();
  });

  modal.find(".reacg-modal-button-generate").on("click", function (e) {
    e.preventDefault();
    const button = jQuery(this);
    const spinner = modal.find(".reacg-modal__footer .spinner");
    const progressNote = modal.find(".reacg-ai-generate-modal__progress");
    const statusNote = modal.find(".reacg-ai-generate-modal__notice");
    const state = getState();
    const errorText = reacg_escape_html(reacg.no_fields_to_generate);
    let shouldCloseAfterSuccess = false;

    const updateProgress = function (current, total) {
      if (!total) {
        progressNote.addClass("hidden").text("");
        return;
      }

      progressNote
        .text(reacg.processing + " " + current + "/" + total)
        .removeClass("hidden");
    };

    const setStatus = function (type, message) {
      const statusMessage = message == null ? "" : String(message);
      const upgradeLinkMarkup = "reacg-upgrade-link";
      const hasUpgradeLink = statusMessage.indexOf(upgradeLinkMarkup) !== -1;

      statusNote
        .removeClass(
          "hidden reacg-ai-generate-modal__notice--success reacg-ai-generate-modal__notice--error reacg-ai-generate-modal__notice--animate",
        )
        .addClass(
          type === "success"
            ? "reacg-ai-generate-modal__notice--success"
            : "reacg-ai-generate-modal__notice--error",
        );

      if (hasUpgradeLink) {
        statusNote.html(statusMessage);
        statusNote
          .find(".reacg-upgrade-link")
          .off("click")
          .on("click", function (event) {
            event.preventDefault();
            reacg_open_free_trial_offer_dialog({ utm_medium: "ai" });
          });
      } else {
        statusNote.text(statusMessage);
      }

      if (statusNote.length) {
        const statusNode = statusNote.get(0);
        /* Force reflow, then add class on next frame to ensure animation visibly plays. */
        void statusNode.offsetWidth;
        window.requestAnimationFrame(function () {
          statusNote.addClass("reacg-ai-generate-modal__notice--animate");
        });
      }

      const wrapper = modal.find(".reacg-modal-wrapper").first();
      if (wrapper.length && statusNote.length) {
        const wrapperNode = wrapper.get(0);
        const statusNode = statusNote.get(0);
        const wrapperTop = wrapper.offset().top;
        const statusTop = statusNote.offset().top;
        const targetScrollTop =
          wrapper.scrollTop() + (statusTop - wrapperTop) - 24;

        if (wrapperNode && typeof wrapperNode.scrollTo === "function") {
          wrapperNode.scrollTo({
            top: Math.max(0, targetScrollTop),
            behavior: "smooth",
          });
        } else {
          wrapper
            .stop(true)
            .animate({ scrollTop: Math.max(0, targetScrollTop) }, 250);
        }
      } else if (
        statusNote.length &&
        statusNote.get(0) &&
        typeof statusNote.get(0).scrollIntoView === "function"
      ) {
        statusNote
          .get(0)
          .scrollIntoView({ behavior: "smooth", block: "center" });
      }
    };

    const readErrorMessageFromResponse = function (payload, fallbackMessage) {
      const fallback = fallbackMessage || errorText;
      const source = payload || {};

      if (source.responseJSON) {
        if (source.responseJSON.data && source.responseJSON.data.message) {
          return source.responseJSON.data.message;
        }
        if (source.responseJSON.errors && source.responseJSON.errors.message) {
          return source.responseJSON.errors.message;
        }
        if (source.responseJSON.message) {
          return source.responseJSON.message;
        }
      }

      if (source.data && source.data.message) {
        return source.data.message;
      }
      if (source.errors && source.errors.message) {
        return source.errors.message;
      }
      if (source.message) {
        return source.message;
      }

      if (source.responseText && typeof source.responseText === "string") {
        try {
          const parsed = JSON.parse(source.responseText);
          if (parsed && parsed.data && parsed.data.message) {
            return parsed.data.message;
          }
          if (parsed && parsed.errors && parsed.errors.message) {
            return parsed.errors.message;
          }
          if (parsed && parsed.message) {
            return parsed.message;
          }
        } catch (ignored) {}
      }

      return fallback;
    };

    statusNote
      .addClass("hidden")
      .removeClass(
        "reacg-ai-generate-modal__notice--success reacg-ai-generate-modal__notice--error",
      )
      .text("");

    const defaultGenerateHandler = function (currentState) {
      const parsedChunkSize = parseInt(settings.chunkSize, 10);
      const chunkSize = parsedChunkSize > 0 ? parsedChunkSize : 5;
      return runBulkGenerateInChunks(currentState, chunkSize, updateProgress);
    };

    let result =
      typeof settings.onGenerate === "function"
        ? settings.onGenerate(state, modal)
        : defaultGenerateHandler(state);

    if (result === false) {
      return;
    }

    if (!result || typeof result.then !== "function") {
      result = defaultGenerateHandler(state);
    }

    if (result && typeof result.then === "function") {
      button.attr("disabled", true);
      spinner.addClass("is-active");
      result.done(function (resultData) {
        const canClose = !!(resultData && resultData.closeOnStatus200);
        if (canClose) {
          shouldCloseAfterSuccess = true;
          setStatus(
            "success",
            reacg_escape_html(reacg.content_generated_successfully),
          );
          if (typeof settings.onSuccess === "function") {
            settings.onSuccess(resultData);
          }
          reacg_reload_preview();
          window.setTimeout(function () {
            closeModal();
          }, 3000);
          return;
        }
        const firstResponse =
          resultData && resultData.responses && resultData.responses.length
            ? resultData.responses[0].response
            : null;
        const responseErrorMessage = readErrorMessageFromResponse(
          firstResponse,
          errorText,
        );
        setStatus("error", responseErrorMessage);
      });
      result.fail(function (errorData) {
        const firstError =
          errorData && errorData.errors && errorData.errors.length
            ? errorData.errors[0]
            : null;
        const xhr = firstError && firstError.xhr ? firstError.xhr : null;
        let message = readErrorMessageFromResponse(
          xhr || firstError,
          errorText,
        );

        const successCount =
          errorData && errorData.responses && errorData.responses.length
            ? errorData.responses.length
            : 0;
        const hasUpgradeLink =
          String(message || "").indexOf("reacg-upgrade-link") !== -1;
        if (hasUpgradeLink && successCount > 0) {
          message =
            "Generated content for " +
            successCount +
            " image" +
            (successCount === 1 ? "" : "s") +
            ". " +
            message;
        }

        setStatus("error", message);
      });
      result.always(function () {
        progressNote.addClass("hidden").text("");
        spinner.removeClass("is-active");
        button.removeAttr("disabled");
      });
      return;
    }
  });

  return modal;
}

function reacg_modal(field) {
  return jQuery(
    "" +
      '<div class="reacg-modal" style="display:none;">' +
      '<div class="reacg-modal-wrapper">' +
      '<div class="reacg-modal-content">' +
      '<div class="reacg-modal__header">' +
      "<h1>" +
      field.title +
      "</h1>" +
      '<span class="reacg-modal-close"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M19 6.41 17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"></path></svg></span>' +
      "</div>" +
      '<div class="reacg-modal__body">' +
      '<div class="reacg-modal__layout-content">' +
      '<div><p class="reacg-modal-note">' +
      reacg_info_icon() +
      field.notice +
      "</p></div>" +
      "<div>" +
      '<label for="reacg-modal-notes">' +
      reacg.ai_popup_additional_notes_label +
      ":</label>" +
      '<textarea class="reacg-modal-notes" id="reacg-modal-notes" rows="2" placeholder="' +
      reacg.ai_popup_additional_notes_placeholder +
      '"></textarea>' +
      "</div>" +
      "<div>" +
      '<label for="reacg-modal-generated-text">' +
      field.label +
      ":</label>" +
      '<textarea class="reacg-modal-generated-text" id="reacg-modal-reacg-modal-generated-text" rows="5" disabled="disabled"></textarea>' +
      "</div>" +
      '<p class="reacg-modal-error-note hidden"></p>' +
      "</div>" +
      "</div>" +
      '<div class="reacg-modal__footer">' +
      '<div class="reacg-modal-buttons-wrapper">' +
      '<span class="spinner"></span>' +
      '<button class="reacg-modal-button-generate button button-primary button-large">' +
      reacg_ai_icon() +
      reacg.generate +
      "</button>" +
      '<button class="reacg-modal-button-proceed button button-secondary button-large" disabled="disabled">' +
      reacg.proceed +
      "</button>" +
      "</div>" +
      "</div>" +
      "</div>" +
      "</div>" +
      "</div>",
  );
}

/**
 * Insert AI button to the given field.
 *
 * @param that
 * @param field
 */
function reacg_add_ai_button(that, field) {
  if (
    !that.find('[data-setting="' + field.name + '"] .reacg-ai-button').length
  ) {
    /* Create an AI button.*/
    const button = reacg_ai_button();
    const spinner = '<span class="spinner reacg-float-none"></span>';
    const url_cont = that.find('[data-setting="url"]');
    that
      .find('[data-setting="' + field.name + '"] label')
      .after(button, spinner);
    const spinnerCont = that.find(
      '[data-setting="' + field.name + '"] .spinner',
    );

    /* Show tooltip for the first field.*/
    if (field.name === "title") {
      if (!localStorage.getItem("reacg-highlight-ai-alt-generation")) {
        reacg_show_tooltip(
          jQuery(".media-frame-content"),
          '[data-setting="' + field.name + '"] .reacg-ai-button',
          jQuery(".media-frame-content").find(".media-sidebar"),
          reacg.ai_highlight,
        );
        localStorage.setItem("reacg-highlight-ai-alt-generation", true);
      }
    }

    button.on("click", function () {
      spinnerCont.addClass("is-active");

      jQuery.ajax({
        type: "GET",
        url: reacg.core_rest_url_v2 + "ai",
        contentType: "application/json",
        data: {
          action: "check",
        },
        complete: function (response) {
          if (response.status === 200) {
            /* Create modal if not exist and open.*/
            const targetDoc = reacg_get_top_document();
            const targetWin = targetDoc.defaultView || window;
            const targetBody = (targetWin.jQuery || jQuery)(targetDoc.body);
            if (
              !targetBody.find(".reacg-modal:not(.reacg-attachment-modal)")
                .length
            ) {
              const modal = reacg_modal(field);
              targetBody.append(modal);
              const modalSpinnerCont = modal.find(
                ".reacg-modal-buttons-wrapper .spinner",
              );
              const generatedText = modal.find(".reacg-modal-generated-text");
              const generateButton = modal.find(".reacg-modal-button-generate");
              const proceedButton = modal.find(".reacg-modal-button-proceed");
              const errorNoteCont = modal.find(".reacg-modal-error-note");
              modal
                .find(".reacg-modal-close, .reacg-modal")
                .on("click", function () {
                  modal.remove();
                });
              modal.on("click", function () {
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
                    image_url: url_cont.find("input").val(),
                    notes: modal.find(".reacg-modal-notes").val(),
                    website_language: reacg.website_language || "",
                    user_language:
                      reacg.user_language ||
                      document.documentElement.lang ||
                      "",
                    action: field.action,
                  },
                  complete: function (response) {
                    modalSpinnerCont.removeClass("is-active");
                    generatedText.removeAttr("disabled");
                    generateButton.removeAttr("disabled");
                    if (
                      response.status === 200 &&
                      response.success &&
                      response.responseJSON
                    ) {
                      generatedText
                        .removeAttr("disabled")
                        .val(response.responseJSON);
                      proceedButton.removeAttr("disabled");
                      generateButton.html(reacg_ai_icon() + reacg.regenerate);
                    } else {
                      const errorMessage =
                        response &&
                        response.responseJSON &&
                        response.responseJSON.errors
                          ? response.responseJSON.errors.message
                          : "";
                      const hasUpgradeLink =
                        String(errorMessage || "").indexOf(
                          "reacg-upgrade-link",
                        ) !== -1;

                      errorNoteCont
                        .removeClass("hidden")
                        .html(errorMessage || "");
                      if (hasUpgradeLink) {
                        errorNoteCont
                          .find(".reacg-upgrade-link")
                          .off("click")
                          .on("click", function (event) {
                            event.preventDefault();
                            reacg_open_free_trial_offer_dialog({
                              utm_medium: "ai_generate",
                            });
                          });
                      }

                      const wrapper = modal
                        .find(".reacg-modal-wrapper")
                        .first();
                      if (wrapper.length && errorNoteCont.length) {
                        const wrapperNode = wrapper.get(0);
                        const wrapperTop = wrapper.offset().top;
                        const errorTop = errorNoteCont.offset().top;
                        const targetScrollTop =
                          wrapper.scrollTop() + (errorTop - wrapperTop) - 24;

                        if (
                          wrapperNode &&
                          typeof wrapperNode.scrollTo === "function"
                        ) {
                          wrapperNode.scrollTo({
                            top: Math.max(0, targetScrollTop),
                            behavior: "smooth",
                          });
                        } else {
                          wrapper
                            .stop(true)
                            .animate(
                              { scrollTop: Math.max(0, targetScrollTop) },
                              250,
                            );
                        }
                      } else if (
                        errorNoteCont.length &&
                        errorNoteCont.get(0) &&
                        typeof errorNoteCont.get(0).scrollIntoView ===
                          "function"
                      ) {
                        errorNoteCont.get(0).scrollIntoView({
                          behavior: "smooth",
                          block: "center",
                        });
                      }
                    }
                  },
                });
              });
              proceedButton.on("click", function () {
                jQuery(that)
                  .find('[data-setting="' + field.name + '"]')
                  .find("textarea, input")
                  .first()
                  .val(generatedText.val());
                modal.remove();
              });
            }
            jQuery(".reacg-modal:not(.reacg-attachment-modal)")
              .css("display", "flex")
              .show();
          }
          spinnerCont.removeClass("is-active");
        },
      });
    });
  }
}

/**
 * Add AI buttons to the specified fields.
 */
function reacg_add_ai_button_to_uploader() {
  wp.media.view.Attachment.Details.prototype.render = _.wrap(
    wp.media.view.Attachment.Details.prototype.render,
    function (render) {
      render.apply(this, _.rest(arguments));

      reacg_add_ai_button_to(this.$el);

      return this;
    },
  );
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
    },
  };

  for (let i in add_button_to) {
    reacg_add_ai_button(el, add_button_to[i]);
  }
}

function reacg_show_tooltip(parent, selectorOrEl, containerToBeScrolled, text) {
  const el =
    typeof selectorOrEl === "string" ? parent.find(selectorOrEl) : selectorOrEl;
  if (!el.length) {
    /* Retry if element is not yet in DOM.*/
    setTimeout(
      () =>
        reacg_show_tooltip(parent, selectorOrEl, containerToBeScrolled, text),
      200,
    );

    return;
  }

  if (!containerToBeScrolled.length) {
    return;
  }
  const containerTop = containerToBeScrolled.offset().top;
  const targetTop = el.offset().top;
  const scrollPosition =
    containerToBeScrolled.scrollTop() + (targetTop - containerTop);
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

  const tooltip = jQuery(
    '<div role="tooltip" class="reacg-tooltip">' +
      "<div>" +
      '<div class="reacg-tooltip-wrapper">' +
      '<span class="reacg-tooltip-icon-wrapper">' +
      reacg_tooltip_icon() +
      "</span>" +
      text +
      "</div>" +
      '<span class="reacg-tooltip-arrow" data-popper-placement="left"></span>' +
      "</div>" +
      "</div>",
  );

  const targetDoc = reacg_get_top_document();
  const targetWin = targetDoc.defaultView || window;
  (targetWin.jQuery || jQuery)(targetDoc.body).append(tooltip);

  const offset = element.offset();
  const elementHeight = element.outerHeight();
  const tooltipHeight = tooltip.outerHeight();

  tooltip.css({
    left: offset.left - tooltip.outerWidth(),
    top: offset.top - tooltipHeight / 2 + elementHeight / 2,
  });

  const removeTooltip = () => {
    jQuery(".reacg-tooltip").remove();
    jQuery("*").off("scroll.reacgTooltip");
    jQuery(window).off(
      "click.reacgTooltip keydown.reacgTooltip resize.reacgTooltip",
    );
  };

  setTimeout(() => {
    /* Remove on ANY scroll (including divs).*/
    jQuery("*").on("scroll.reacgTooltip", removeTooltip);
    /* Also remove on common user interactions.*/
    jQuery(window).on(
      "click.reacgTooltip keydown.reacgTooltip resize.reacgTooltip",
      removeTooltip,
    );
  }, 200); /* Small delay to ensure tooltip is in DOM.*/
}
