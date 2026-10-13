/**
 * ReGallery Elementor Integration Script
 *
 * Loaded in both the editor document and the preview iframe. The editor side
 * opens the shared ReacgBuilderModal when the widget is edited and writes the
 * chosen gallery back to the widget model through Elementor's command API so
 * history (undo/redo) and re-rendering behave like any other control change.
 * The preview side only forwards clicks on the gallery/placeholder.
 */
(function ($) {
  "use strict";

  const WIDGET_TYPE = "reacg-elementor";

  function getTopWindow() {
    try {
      return window.top || window;
    } catch (e) {
      return window;
    }
  }

  function getModal() {
    const topWin = getTopWindow();
    return topWin.ReacgBuilderModal || window.ReacgBuilderModal || null;
  }

  function getElementor() {
    const topWin = getTopWindow();
    return topWin.elementor || window.elementor || null;
  }

  function getContainer(widgetId) {
    const elementor = getElementor();
    if (!elementor || !widgetId) {
      return null;
    }
    try {
      if (typeof elementor.getContainer === "function") {
        return elementor.getContainer(widgetId) || null;
      }
    } catch (e) {}
    return null;
  }

  function getPostId(container) {
    if (!container || !container.settings) {
      return 0;
    }
    try {
      return parseInt(container.settings.get("post_id"), 10) || 0;
    } catch (e) {
      return 0;
    }
  }

  /**
   * Persist the gallery id on the widget. The command API records history and
   * triggers the widget re-render that mounts the new preview.
   */
  function setPostId(container, galleryId) {
    if (!container) {
      return;
    }
    const value = String(parseInt(galleryId, 10) || 0);
    const topWin = getTopWindow();
    const $e = topWin.$e || window.$e;

    if ($e && typeof $e.run === "function") {
      try {
        $e.run("document/elements/settings", {
          container: container,
          settings: { post_id: value },
          options: { external: true },
        });
        return;
      } catch (e) {
        console.error("ReGallery: failed to update Elementor settings", e);
      }
    }

    // Fallback for very old Elementor versions without the commands API.
    if (container.settings && typeof container.settings.set === "function") {
      container.settings.set("post_id", value);
    }
    const elementor = getElementor();
    if (elementor && elementor.saver && typeof elementor.saver.setFlagEditorChange === "function") {
      elementor.saver.setFlagEditorChange(true);
    }
  }

  /**
   * Open the panel for a widget container.
   */
  function openPanel(container) {
    const modal = getModal();
    if (!modal || !container) {
      if (!modal) console.error("ReacgBuilderModal is not loaded.");
      return;
    }
    const widgetId = container.id;
    modal.open({
      galleryId: getPostId(container),
      widgetId: widgetId,
      onSave: function (savedGalleryId) {
        setPostId(getContainer(widgetId) || container, savedGalleryId);
      },
    });
  }

  /* ---------------------------------------------------------------- */
  /* Editor document                                                   */
  /* ---------------------------------------------------------------- */

  if (window.elementor && window.elementor.hooks) {
    // Open the panel whenever the widget's settings are opened (drop, click, navigator).
    window.elementor.hooks.addAction("panel/open_editor/widget/" + WIDGET_TYPE, function (panel, model, view) {
      const container = (view && view.container) || (model && model.id ? getContainer(model.id) : null);
      if (!container) {
        return;
      }
      setTimeout(function () {
        openPanel(container);
      }, 50);
    });

    // Close it when a different widget is edited.
    window.elementor.hooks.addAction("panel/open_editor/widget", function (panel, model) {
      if (model && model.get && model.get("widgetType") !== WIDGET_TYPE) {
        const modal = getModal();
        if (modal && modal.isOpen) {
          modal.close();
        }
      }
    });

    if (window.elementor.channels && window.elementor.channels.editor) {
      window.elementor.channels.editor.on("reacg:open_gallery_modal", function (view) {
        if (view && view.container) {
          openPanel(view.container);
        }
      });
    }
  }

  /* ---------------------------------------------------------------- */
  /* Preview document (and editor document, for completeness)          */
  /* ---------------------------------------------------------------- */

  function widgetIdFromElement(element) {
    const wrapper = element.closest(".elementor-element");
    if (wrapper && wrapper.getAttribute("data-id")) {
      return wrapper.getAttribute("data-id");
    }
    const placeholder = element.closest(".reacg-builder-placeholder");
    if (placeholder && placeholder.getAttribute("data-widget-id")) {
      return placeholder.getAttribute("data-widget-id");
    }
    const gallery = element.closest(".reacg-gallery");
    if (gallery && gallery.getAttribute("data-widget-id")) {
      return gallery.getAttribute("data-widget-id");
    }
    return "";
  }

  function isEditing() {
    const elementor = getElementor();
    if (!elementor) {
      return false;
    }
    if (typeof elementor.isEditMode === "function" && !elementor.isEditMode()) {
      return false;
    }
    return !(document.body && document.body.classList.contains("elementor-editor-preview"));
  }

  // Placeholder button.
  $(document).on("click", ".reacg-builder-open-modal-btn", function (e) {
    e.preventDefault();
    e.stopPropagation();
    const widgetId = widgetIdFromElement(this);
    const container = getContainer(widgetId);
    if (container) {
      openPanel(container);
    }
  });

  // Clicks on the preview gallery: block lightbox/links, open the panel.
  window.addEventListener(
    "click",
    function (e) {
      if (!isEditing()) {
        return;
      }
      const gallery = e.target && e.target.closest && e.target.closest(".reacg-gallery");
      if (!gallery) {
        return;
      }
      e.preventDefault();
      e.stopPropagation();
      e.stopImmediatePropagation();

      const container = getContainer(widgetIdFromElement(gallery));
      if (container) {
        openPanel(container);
      }
    },
    true,
  );
})(jQuery);
