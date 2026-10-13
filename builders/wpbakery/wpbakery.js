/**
 * ReGallery WPBakery Page Builder integration script.
 *
 * Loaded in the editor document (backend editor, frontend editor parent) and
 * in the frontend editor canvas iframe. The editor side opens the shared
 * ReacgBuilderModal instead of the element's native edit form and writes
 * the chosen gallery back to the WPBakery model; the canvas side forwards clicks
 * on the preview and mounts re-rendered gallery roots.
 */
(function ($) {
  "use strict";

  const TAG = typeof reacg_wpbakery !== "undefined" && reacg_wpbakery.tag ? reacg_wpbakery.tag : "REACG_wpbakery_widget";

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

  /**
   * The model collection that owns the element being edited. The editor
   * document has it locally (also when WPBakery embeds its backend editor in
   * an iframe inside the block editor); the canvas iframe reads its parent's.
   */
  function getVc() {
    if (window.vc && window.vc.shortcodes) return window.vc;
    const topWin = getTopWindow();
    if (topWin.vc && topWin.vc.shortcodes) return topWin.vc;
    return null;
  }

  /**
   * Persist the gallery id on a WPBakery model. The frontend editor view
   * listens to param changes and re-renders the element; the backend editor
   * marks the content as changed.
   */
  function setModelGalleryId(modelId, galleryId) {
    const vc = getVc();
    const model = vc && modelId ? vc.shortcodes.get(modelId) : null;
    if (!model) return;
    const params = $.extend({}, model.get("params") || {}, { id: String(parseInt(galleryId, 10) || 0) });
    try {
      model.save({ params: params });
    } catch (e) {
      model.set("params", params);
    }
  }

  function openPanel(modelId, galleryId, onSave) {
    const modal = getModal();
    if (!modal) {
      console.error("ReacgBuilderModal is not loaded.");
      return;
    }
    modal.open({
      galleryId: parseInt(galleryId, 10) || 0,
      widgetId: String(modelId || ""),
      onSave: onSave,
    });
  }

  /* ---------------------------------------------------------------- */
  /* Editor document (backend editor / frontend editor parent)         */
  /* ---------------------------------------------------------------- */

  function bindEditor() {
    const vc = window.vc;
    if (!vc || !vc.events || !vc.shortcodes) {
      return false;
    }

    const isOurs = function (model) {
      return !!model && typeof model.get === "function" && model.get("shortcode") === TAG;
    };

    const openForModel = function (model) {
      const modelId = model.get("id");
      const params = model.get("params") || {};
      openPanel(modelId, params.id, function (savedGalleryId) {
        setModelGalleryId(modelId, savedGalleryId);
      });
    };

    // WPBakery renders its "Edit element" form when an element is inserted
    // and when its pencil icon is clicked. The gallery element has nothing to
    // edit there, so the form is replaced by the ReGallery panel instead of
    // being rendered.
    const PanelClass = vc.EditElementUIPanel || vc.EditElementPanelView;
    const proto = PanelClass && PanelClass.prototype;
    if (proto && typeof proto.render === "function" && !proto.__reacgRenderHooked) {
      proto.__reacgRenderHooked = true;
      const originalRender = proto.render;
      proto.render = function (model) {
        if (!isOurs(model)) {
          return originalRender.apply(this, arguments);
        }
        if (vc.active_panel && typeof vc.closeActivePanel === "function") {
          vc.closeActivePanel();
        }
        openForModel(model);
        return this;
      };
    }

    // Fallback for a form that was rendered anyway, and closing the panel
    // when another element's form opens.
    vc.events.on("editElementPanel:ready", function () {
      const view = vc.edit_element_block_view;
      const model = view && view.model;
      const modal = getModal();
      if (!isOurs(model)) {
        if (modal && modal.isOpen && !modal.isEmbedded) modal.close();
        return;
      }
      openForModel(model);
      if (typeof view.hide === "function") view.hide();
    });

    // Button inside the param field, in case the form is shown by a
    // third-party extension.
    $(document).on("click", ".reacg-vc-param__open", function (e) {
      e.preventDefault();
      const view = vc.edit_element_block_view;
      if (view && isOurs(view.model)) {
        openForModel(view.model);
        if (typeof view.hide === "function") view.hide();
      }
    });

    return true;
  }

  /* ---------------------------------------------------------------- */
  /* Frontend editor canvas iframe                                     */
  /* ---------------------------------------------------------------- */

  function mountPendingGalleries() {
    if (typeof window.reacg_mount_gallery !== "function") return;
    document.querySelectorAll("div.reacg-gallery").forEach(function (root) {
      if (root.hasChildNodes()) return;
      if (!root.id) root.id = "reacg-root" + root.getAttribute("data-gallery-id");
      window.reacg_mount_gallery(root);
    });
  }

  function bindCanvas() {
    const topWin = getTopWindow();
    if (topWin === window || !topWin.vc || !topWin.vc.shortcodes) {
      return false;
    }

    const modelIdFrom = function (element) {
      const wrapper = element.closest("[data-model-id]");
      return wrapper ? wrapper.getAttribute("data-model-id") : "";
    };

    const openFor = function (element, galleryId) {
      const modelId = modelIdFrom(element);
      if (!modelId) return;
      openPanel(modelId, galleryId, function (savedGalleryId) {
        setModelGalleryId(modelId, savedGalleryId);
      });
    };

    $(document).on("click", ".reacg-builder-open-modal-btn", function (e) {
      e.preventDefault();
      e.stopPropagation();
      openFor(this, 0);
    });

    window.addEventListener(
      "click",
      function (e) {
        const gallery = e.target && e.target.closest && e.target.closest(".reacg-gallery");
        if (!gallery) return;
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        openFor(gallery, gallery.getAttribute("data-gallery-id"));
      },
      true,
    );

    // WPBakery replaces the element markup after a param change; mount any
    // gallery root that arrives empty.
    let timer = null;
    const observer = new MutationObserver(function () {
      if (timer) clearTimeout(timer);
      timer = setTimeout(mountPendingGalleries, 100);
    });
    observer.observe(document.body, { childList: true, subtree: true });
    mountPendingGalleries();

    return true;
  }

  $(function () {
    if (!bindEditor()) {
      bindCanvas();
    }
  });
})(jQuery);
