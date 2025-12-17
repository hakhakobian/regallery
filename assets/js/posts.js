function reacg_get_posts(media_uploader, that, select_type, images_ids, gallery_id) {
  let wrapper = jQuery(that).find(".reacg-posts-wrapper");
  wrapper.find(".spinner").addClass("is-active");
  wrapper.find(".reacg_select_type").addClass("hidden");
  jQuery.ajax({
    url: reacg.ajax_url,
    type: "POST",
    data: {
      action: "reacg_get_posts",
      gallery_id: gallery_id,
      select_type: select_type,
      type: wrapper.data("type"),
      s: wrapper.find("#media-search-input").val(),
      orderby: wrapper.find("#media-attachment-order-by").val(),
      order: wrapper.find("#media-attachment-order").val(),
    },
    success: function (response) {
      wrapper.html(response);
      wrapper.find(".spinner").removeClass("is-active");
      wrapper.find(".reacg_select_type").removeClass("hidden");
      if ( select_type === "" ) {
        /* Bind events to the buttons.*/
        wrapper.find(".reacg_select_type").off("click").on("click", function () {
          reacg_get_posts(media_uploader, that, jQuery(this).data("select-type"), images_ids, gallery_id);
        });
      }
      else if ( select_type === "dynamic" ) {
        wrapper.find("input[name='reacg_count_option']").off("change").on("change", function () {
          if (jQuery('#reacg_count_custom').is(':checked')) {
            jQuery('#reacg_count').removeClass('reacg-invisible').val(6);
          }
          else {
            jQuery('#reacg_count').addClass('reacg-invisible').val(0);
          }
        });

        let selection = media_uploader.state().get('selection');
        /* Reset images selection on tab change.*/
        selection.reset();
        selection.add([
          new wp.media.model.Attachment({
            id: wrapper.data("type") + "dynamic",
            edit: true,
            title: reacg.allowed_post_types[wrapper.data("type")]['title'],
            sizes: {},
            type: wrapper.data("type") + "dynamic",
            additional_data: {
              relation: wrapper.find("#reacg_relation").val(),
              taxonomies: wrapper.find("#reacg_taxanomies").val(),
              exclude: wrapper.find("#reacg_exclude").val(),
              exclude_without_image: wrapper.find("#reacg_exclude_without_image").is(':checked'),
              count: wrapper.find("#reacg_count").val(),
            }
          }),
        ]);
        if ( wrapper.find(".reacg_searchable_select").length ) {
          wrapper.find(".reacg_searchable_select").select2({});
        }
        wrapper.find(".reacg_change_listener").on("change", function () {
          selection.reset();
          selection.add([
            new wp.media.model.Attachment({
              id: wrapper.data("type") + "dynamic",
              title: reacg.allowed_post_types[wrapper.data("type")]['title'],
              sizes: {},
              type: wrapper.data("type") + "dynamic",
              additional_data: {
                relation: wrapper.find("#reacg_relation").val(),
                taxonomies: wrapper.find("#reacg_taxanomies").val(),
                exclude: wrapper.find("#reacg_exclude").val(),
                exclude_without_image: wrapper.find("#reacg_exclude_without_image").is(':checked'),
                count: wrapper.find("#reacg_count").val(),
              }
            }),
          ]);
        });
      }
      else if ( select_type === "manual" ) {
        let typingTimer;
        const delay = 500;
        wrapper.find("#media-search-input").off("keyup input").on("keyup input", function () {
          clearTimeout(typingTimer); /* Clear previous timer.*/
          typingTimer = setTimeout(() => {
            reacg_get_posts(media_uploader, that, select_type, images_ids, gallery_id);
          }, delay);
        });
        wrapper.find("#media-attachment-order-by, #media-attachment-order").off("change").on("change", function () {
          reacg_get_posts(media_uploader, that, select_type, images_ids, gallery_id);
        });
        let lastSelected = null;
        wrapper.find(".attachment").off("click").on("click", function (e) {
          let selection = media_uploader.state().get('selection');
          let itemId = jQuery(this).data("id");
          if (jQuery(this).hasClass("selected") && !e.shiftKey) {
            /* If the clicked item is already selected and no key is pressed, deselect it.*/
            jQuery(this).removeClass("selected");
            lastSelected = null;
            /* Remove deselected item form the selection.*/
            selection.each(function (item) {
              if (item && item.get("id") === itemId) {
                selection.remove(item);
              }
            });
            if (!wrapper.find(".attachment.selected").length) {
              jQuery(that).find(".media-button-select").attr("disabled", "disabled");
            }
            return;
          }
          if (e.shiftKey && lastSelected) {
            /* If Shift is pressed, select all items between lastSelected and current.*/
            let items = wrapper.find(".attachment");
            let start = items.index(lastSelected);
            let end = items.index(this);
            let min = Math.min(start, end);
            let max = Math.max(start, end);
            let selected_items = items.slice(min, max + 1).addClass("selected");
            /* Add all items data between lastSelected except current item.*/
            selected_items.each(function (index) {
              if (jQuery(items[index]).data("id") !== itemId) {
                selection.add([
                  new wp.media.model.Attachment({
                    id: jQuery(items[index]).data("id"),
                    title: jQuery(items[index]).data("title"),
                    sizes: {thumbnail: {url: jQuery(items[index]).data("thumbnail")}},
                    type: wrapper.data("type"),
                  }),
                ]);
              }
            });
          }
          else if (e.ctrlKey || e.metaKey) {
            /* If Ctrl/Cmd is pressed, toggle selection.*/
            jQuery(this).toggleClass("selected");
          }
          else {
            /* If no key is pressed, select the clicked item.*/
            jQuery(this).addClass("selected");
          }
          lastSelected = this; /* Update last selected item.*/
          if (wrapper.find(".attachment.selected").length) {
            jQuery(that).find(".media-button-select").removeAttr("disabled");
          }
          /* Add selected item data.*/
          selection.add([
            new wp.media.model.Attachment({
              id: itemId,
              title: jQuery(this).data("title"),
              sizes: {thumbnail: {url: jQuery(this).data("thumbnail")}},
              type: wrapper.data("type"),
            }),
          ]);
        });
        reacg_check_images(images_ids);
      }
    },
  });
}

function reacg_add_posts_tab(media_uploader, type, gallery_id) {
  for ( let post_type in reacg.allowed_post_types ) {
    if ( !String(post_type).includes("dynamic") ) {
      const button = '<button type="button" data-type="' + post_type + '" role="tab" class="media-menu-item" id="menu-item-' + post_type + '" aria-selected="false" tabindex="-1">' + reacg.allowed_post_types[post_type]['title'] + '</button>';
      jQuery(".media-router").append(button);
    }
  }

  if ( type ) {
    const button = jQuery(".media-router").find("#menu-item-" + type);
    const media_modal = button.closest(".media-modal-content");
    media_modal.find(".media-menu-item").removeClass("active").attr({"aria-selected": false, "tabindex": -1, disabled: "disabled"});
    button.addClass("active").attr("aria-selected", true).removeAttr("tabindex");
    media_modal.find(".media-frame-content").html('<div class="reacg-posts-wrapper attachments-browser" data-type="' + button.data("type") + '"><span class="spinner is-active"></span></div>');

    reacg_get_posts(media_uploader, media_modal, "dynamic", "", gallery_id);
  }
}

function reacg_add_posts(media_uploader, images_ids, gallery_id) {
  reacg_add_posts_tab(media_uploader, "", gallery_id);

  jQuery(document).off("click", ".media-menu-item").on("click", ".media-menu-item", function () {
    if ( reacg.allowed_post_types.hasOwnProperty(jQuery(this).data("type")) ) {
      /* Reset images selection on tab change.*/
      media_uploader.state().get('selection').reset();
      let media_modal = jQuery(this).closest(".media-modal-content");
      media_modal.find(".media-menu-item").removeClass("active").attr({"aria-selected": false, "tabindex": -1});
      jQuery(this).addClass("active").attr("aria-selected", true).removeAttr("tabindex");
      media_modal.find(".media-frame-content").html('<div class="reacg-posts-wrapper attachments-browser" data-type="' + jQuery(this).data("type") + '"><span class="spinner is-active"></span></div>');

      if ( jQuery(this).data("type").includes("product") ) {
        jQuery.ajax({
          type: "GET",
          url: "https://regallery.team/core/wp-json/reacgcore/v2/postTypes",
          contentType: "application/json",
          complete: function (response) {
            if (response.status === 204) {
              /* If trying to get Pro post type with free account.*/
              reacg_open_premium_offer_dialog({utm_medium: 'woo_products'});
              media_modal.find(".media-frame-content .spinner").removeClass("is-active");
            }
            else if (response.status === 200) {
              reacg_get_posts(media_uploader, media_modal, "", images_ids, gallery_id);
            }
          }
        });
      }
      else {
        reacg_get_posts(media_uploader, media_modal, "", images_ids, gallery_id);
      }
    }
    else {
      /* Re-render the media frame content.*/
      wp.media.frame.content.render();
    }
  });
}