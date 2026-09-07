/**
 * ReGallery Bricks Builder Integration Script
 */
function reacg_loadApp() {
  const reacgLoadApp = document.getElementById("reacg-loadApp");
  if (reacgLoadApp) {
    document.querySelectorAll("div.reacg-gallery").forEach((div) => {
      const rootId =
        div.id || "reacg-root" + div.getAttribute("data-gallery-id");
      if (!rootId) return;

      reacgLoadApp.setAttribute("data-id", rootId);
      reacgLoadApp.click();
    });
  }
}

(function ($) {
  "use strict";

  /**
   * Helper to update Bricks element setting when modal saves.
   *
   * @param {string} elementId
   * @param {number|string} galleryId
   */
  function setBricksGalleryId(elementId, galleryId) {
    const intGalleryId = parseInt(galleryId, 10) || 0;
    const strElementId = String(elementId || "").trim();
    if (!strElementId) {
      return;
    }

    const topWin = window.top || window;
    const topDoc = topWin.document || document;

    // 1. Update Bricks Vue reactive state on the main top window
    try {
      const brxBody = topDoc.querySelector(".brx-body");
      const vueApp = brxBody && brxBody.__vue_app__;
      const globalProps =
        vueApp && vueApp.config && vueApp.config.globalProperties;
      const state = globalProps && globalProps.$_state;

      // Update in reactive $_dynamicElements
      if (
        globalProps &&
        globalProps.$_dynamicElements &&
        Array.isArray(globalProps.$_dynamicElements.value)
      ) {
        const el = globalProps.$_dynamicElements.value.find(function (item) {
          return String(item.id) === strElementId;
        });
        if (el) {
          if (!el.settings) el.settings = {};
          el.settings.gallery_id = intGalleryId;
        }
      }

      // Update in state.content
      if (state && Array.isArray(state.content)) {
        const el = state.content.find(function (item) {
          return String(item.id) === strElementId;
        });
        if (el) {
          if (!el.settings) el.settings = {};
          el.settings.gallery_id = intGalleryId;
        }
      }

      // Update activeElement if currently selected in panel
      if (
        state &&
        state.activeElement &&
        String(state.activeElement.id) === strElementId
      ) {
        if (!state.activeElement.settings) state.activeElement.settings = {};
        state.activeElement.settings.gallery_id = intGalleryId;
      }

      // Update activeComponent if inside a component
      if (
        state &&
        state.activeComponent &&
        Array.isArray(state.activeComponent.elements)
      ) {
        const el = state.activeComponent.elements.find(function (item) {
          return String(item.id) === strElementId;
        });
        if (el) {
          if (!el.settings) el.settings = {};
          el.settings.gallery_id = intGalleryId;
        }
      }

      // Mark content as unsaved so Bricks Save button activates and includes content in save payload
      if (state && Array.isArray(state.unsavedChanges)) {
        if (!state.unsavedChanges.includes("content")) {
          state.unsavedChanges.push("content");
        }
      }

      // Trigger Bricks element re-render if available
      if (state && Array.isArray(state.rerenderElementIds)) {
        if (!state.rerenderElementIds.includes(strElementId)) {
          state.rerenderElementIds.push(strElementId);
        }
      }
    } catch (e) {}

    // 2. Also update Vue reactive elements inside canvas iframe if present
    try {
      let canvasDoc = null;
      const iframe = topDoc.querySelector("#bricks-builder-iframe");
      if (iframe && iframe.contentDocument) {
        canvasDoc = iframe.contentDocument;
      } else if (document !== topDoc) {
        canvasDoc = document;
      }

      if (canvasDoc) {
        const canvasBody = canvasDoc.querySelector(".brx-body");
        const canvasVue = canvasBody && canvasBody.__vue_app__;
        const canvasProps =
          canvasVue && canvasVue.config && canvasVue.config.globalProperties;
        if (
          canvasProps &&
          canvasProps.$_dynamicElements &&
          Array.isArray(canvasProps.$_dynamicElements.value)
        ) {
          const el = canvasProps.$_dynamicElements.value.find(function (item) {
            return String(item.id) === strElementId;
          });
          if (el) {
            if (!el.settings) el.settings = {};
            el.settings.gallery_id = intGalleryId;
          }
        }
      }
    } catch (e) {}

    // 3. Update panel control input if open in #bricks-panel
    try {
      const panel = topDoc.querySelector("#bricks-panel");
      if (panel) {
        const input = panel.querySelector(
          '[data-controlkey="gallery_id"] input, input[name="gallery_id"], .bricks-control-gallery_id input',
        );
        if (input) {
          input.value = intGalleryId;
          input.dispatchEvent(new Event("input", { bubbles: true }));
          input.dispatchEvent(new Event("change", { bubbles: true }));
        }
      }
    } catch (e) {}

    // 4. Update Bricks fallback data model
    try {
      if (topWin.bricksData && Array.isArray(topWin.bricksData.elements)) {
        const el = topWin.bricksData.elements.find(function (item) {
          return String(item.id) === strElementId;
        });
        if (el) {
          if (!el.settings) el.settings = {};
          el.settings.gallery_id = intGalleryId;
        }
      }
    } catch (e) {}
  }

  // Expose helper globally on current and top window
  window.setBricksGalleryId = setBricksGalleryId;
  try {
    if (window.top && window.top !== window) {
      window.top.setBricksGalleryId = setBricksGalleryId;
    }
  } catch (e) {}

  // Open modal handler for Bricks placeholder button
  $(document).on("click", ".reacg-builder-open-modal-btn", function (e) {
    e.preventDefault();
    e.stopPropagation();

    const $placeholder = $(this).closest(".reacg-builder-placeholder");
    const $wrapper = $placeholder.closest(
      ".brxe-reacg, [data-bricks-element-id]",
    );
    const rawWidgetId =
      $placeholder.attr("data-widget-id") ||
      ($wrapper.length
        ? $wrapper.attr("data-bricks-element-id") ||
          $wrapper.attr("data-script-id") ||
          ($wrapper.attr("id") ? $wrapper.attr("id").replace("brxe-", "") : "")
        : "");
    const widgetId = (rawWidgetId || "").trim().replace(/[^a-zA-Z0-9_-]/g, "");
    const galleryId =
      parseInt(
        $wrapper.find(".reacg-gallery").attr("data-gallery-id") ||
          $placeholder.attr("data-gallery-id") ||
          0,
        10,
      ) || 0;

    const topWin = window.top || window;
    const modal = topWin.ReacgBuilderModal || window.ReacgBuilderModal;
    if (modal) {
      modal.open({
        galleryId: galleryId,
        widgetId: widgetId,
        onSave: function (savedGalleryId) {
          setBricksGalleryId(widgetId, savedGalleryId);
        },
      });
    }
  });

  // Global click interceptor inside Bricks preview/canvas
  window.addEventListener(
    "click",
    function (e) {
      const topWin = window.top || window;
      const isBricks = Boolean(
        topWin.bricksData ||
        topWin.bricks ||
        (topWin.document &&
          topWin.document.getElementById("bricks-builder-iframe")),
      );
      if (!isBricks) {
        return;
      }

      const gallery =
        e.target && e.target.closest && e.target.closest(".reacg-gallery");
      if (!gallery) {
        return;
      }

      e.preventDefault();
      e.stopPropagation();
      e.stopImmediatePropagation();

      const widgetWrapper =
        gallery.closest(".brxe-reacg") ||
        gallery.closest("[data-bricks-element-id]") ||
        gallery.closest("[data-script-id]");
      const rawWidgetId = widgetWrapper
        ? widgetWrapper.getAttribute("data-bricks-element-id") ||
          widgetWrapper.getAttribute("data-script-id") ||
          (widgetWrapper.id ? widgetWrapper.id.replace("brxe-", "") : "")
        : gallery.id && gallery.id.indexOf("reacg-root") === 0
          ? gallery.id.replace("reacg-root", "")
          : "";
      const widgetId = (rawWidgetId || "")
        .trim()
        .replace(/[^a-zA-Z0-9_-]/g, "");

      const currentPostId =
        parseInt(gallery.getAttribute("data-gallery-id"), 10) || 0;

      const modal = topWin.ReacgBuilderModal || window.ReacgBuilderModal;
      if (modal) {
        modal.open({
          galleryId: currentPostId,
          widgetId: widgetId,
          onSave: function (savedGalleryId) {
            setBricksGalleryId(widgetId, savedGalleryId);
          },
        });
      }
    },
    true,
  );
})(jQuery);
