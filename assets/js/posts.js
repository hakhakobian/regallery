function reacg_get_posts(that, images_ids) {
  let wrapper = jQuery(that).find(".reacg-posts-wrapper");
  wrapper.find(".spinner").addClass("is-active");
  jQuery.ajax({
    url: reacg.ajax_url,
    type: "POST",
    data: {
      action: "reacg_get_posts",
      type: wrapper.data("type"),
      s: wrapper.find("#media-search-input").val(),
      orderby: wrapper.find("#media-attachment-order-by").val(),
      order: wrapper.find("#media-attachment-order").val(),
    },
    success: function (response) {
      wrapper.html(response);
      let typingTimer;
      const delay = 500;
      wrapper.find("#media-search-input").off("keyup input").on("keyup input", function () {
        clearTimeout(typingTimer); /* Clear previous timer.*/
        typingTimer = setTimeout(() => {
          reacg_get_posts(that, images_ids);
        }, delay);
      });

      wrapper.find("#media-attachment-order-by, #media-attachment-order").off("change").on("change", function () {
        reacg_get_posts(that, images_ids);
      });

      let lastSelected = null;
      wrapper.find(".attachment").off("click").on("click", function (e) {
        let selection = wp.media.frames.file_frame.state().get("selection");
        let itemId = jQuery(this).data("id");
        if ( jQuery(this).hasClass("selected") && !e.shiftKey ) {
          /* If the clicked item is already selected and no key is pressed, deselect it.*/
          jQuery(this).removeClass("selected");
          lastSelected = null;
          /* Remove deselected item form the selection.*/
          selection.each(function (item) {
            if ( item && item.get("id") === itemId ) {
              selection.remove(item);
            }
          });
          if ( !wrapper.find(".attachment.selected").length ) {
            jQuery(that).find(".media-button-select").attr("disabled", "disabled");
          }
          return;
        }

        if ( e.shiftKey && lastSelected ) {
          /* If Shift is pressed, select all items between lastSelected and current.*/
          let items = wrapper.find(".attachment");
          let start = items.index(lastSelected);
          let end = items.index(this);
          let min = Math.min(start, end);
          let max = Math.max(start, end);

          let selected_items = items.slice(min, max + 1).addClass("selected");
          /* Add all items data between lastSelected except current item.*/
          selected_items.each(function (index) {
            if ( jQuery(items[index]).data("id") !== itemId ) {
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
        else if ( e.ctrlKey || e.metaKey ) {
          /* If Ctrl/Cmd is pressed, toggle selection.*/
          jQuery(this).toggleClass("selected");
        }
        else {
          /* If no key is pressed, select only the clicked item.*/
          wrapper.find(".attachment").removeClass("selected");
          jQuery(this).addClass("selected");
        }

        lastSelected = this; // Update last selected item

        if ( wrapper.find(".attachment.selected").length ) {
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
    },
  });
}

function reacg_add_posts_tab(images_ids) {
  let post_types = reacg.allowed_post_types;

  for ( let key in post_types ) {
    const button = '<button type="button" data-type="' + post_types[key].type + '" role="tab" class="media-menu-item" id="menu-item-' + post_types[key].type + '" aria-selected="false" tabindex="-1">' + post_types[key].title + '</button>';
    jQuery(".media-router").append(button);
  }

  jQuery(document).off("click", ".media-menu-item").on("click", ".media-menu-item", function () {
    if ( post_types.some(item => jQuery(this).data("type") === item.type ) ) {
      /* Reset images selection on tab change.*/
      wp.media.frames.file_frame.state().get("selection").reset();
      let media_modal = jQuery(this).closest(".media-modal-content");
      media_modal.find(".media-menu-item").removeClass("active").attr({"aria-selected": false, "tabindex": -1});
      jQuery(this).addClass("active").attr("aria-selected", true).removeAttr("tabindex");
      media_modal.find(".media-frame-content").html('<div class="reacg-posts-wrapper attachments-browser" data-type="' + jQuery(this).data("type") + '"><span class="spinner is-active"></span></div>');

      reacg_get_posts(media_modal, images_ids);
    }
    else {
      /* Re-render the media frame content.*/
      wp.media.frame.content.render();
    }
  });
}