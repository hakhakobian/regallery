/**
 * ReGallery Beaver Builder Integration Script
 */

function reacg_loadApp() {
  const topWin = window.top || window;
  const docs = [document];
  try {
    if (topWin.document && docs.indexOf(topWin.document) === -1) {
      docs.push(topWin.document);
    }
  } catch (e) {}

  try {
    const FLBuilder = topWin.FLBuilder || window.FLBuilder;
    if (
      FLBuilder &&
      FLBuilder.UIIFrame &&
      typeof FLBuilder.UIIFrame.getIFrameWindow === "function"
    ) {
      const bbWin = FLBuilder.UIIFrame.getIFrameWindow();
      if (bbWin && bbWin.document && docs.indexOf(bbWin.document) === -1) {
        docs.push(bbWin.document);
      }
    }
  } catch (e) {}

  try {
    const iframes = (topWin.document || document).querySelectorAll("iframe");
    for (let i = 0; i < iframes.length; i++) {
      try {
        const ifDoc = iframes[i].contentDocument;
        if (ifDoc && docs.indexOf(ifDoc) === -1) {
          docs.push(ifDoc);
        }
      } catch (err) {}
    }
  } catch (e) {}

  const mountDocumentGalleries = function (doc) {
    const reacgLoadApp = doc.getElementById("reacg-loadApp");
    if (!reacgLoadApp) {
      return false;
    }

    doc.querySelectorAll("div.reacg-gallery").forEach(function (div) {
      const rootId =
        div.id || "reacg-root" + div.getAttribute("data-gallery-id");
      if (!rootId) return;

      reacgLoadApp.setAttribute("data-id", rootId);
      try {
        reacgLoadApp.click();
      } catch (e) {}
    });
    return true;
  };

  // A Beaver partial render can finish before wp-gallery.js has added its
  // hidden mount button to the canvas iframe. Retry locally: a button from
  // the parent document cannot mount a gallery element in the iframe.
  let needsRetry = false;
  docs.forEach(function (doc) {
    if (doc.querySelector("div.reacg-gallery") && !mountDocumentGalleries(doc)) {
      needsRetry = true;
    }
  });

  if (needsRetry && !window.__reacgBeaverLoadRetry) {
    let attempts = 0;
    window.__reacgBeaverLoadRetry = window.setInterval(function () {
      attempts += 1;
      let stillWaiting = false;
      docs.forEach(function (doc) {
        if (doc.querySelector("div.reacg-gallery") && !mountDocumentGalleries(doc)) {
          stillWaiting = true;
        }
      });

      if (!stillWaiting || attempts >= 20) {
        window.clearInterval(window.__reacgBeaverLoadRetry);
        window.__reacgBeaverLoadRetry = null;
      }
    }, 150);
  }
}

(function ($) {
  "use strict";

  /**
   * Return Beaver Builder's unique module node ID for an element in its canvas.
   * Beaver uses a `fl-node-{id}` class in rendered layouts; `data-node` is only
   * present in some editor views, so both forms must be supported.
   *
   * @param {jQuery|Element} element
   * @return {string}
   */
  function getBeaverNodeId(element) {
    const $element = element && element.jquery ? element : $(element);
    const $module = $element.closest(".fl-module, [data-node], [class*='fl-node-']");
    const dataNode = String($module.attr("data-node") || "").trim();

    if (dataNode) {
      return dataNode;
    }

    const classes = String($module.attr("class") || "");
    const match = classes.match(/(?:^|\s)fl-node-([A-Za-z0-9_-]+)(?=\s|$)/);
    return match ? match[1] : "";
  }

  /**
   * Helper to update Beaver Builder module setting when modal saves.
   *
   * @param {string} nodeId
   * @param {number|string} galleryId
   */
  function setBeaverBuilderGalleryId(nodeId, galleryId, onComplete) {
    const intGalleryId = parseInt(galleryId, 10) || 0;
    const strNodeId = String(nodeId || "").trim();
    const topWin = window.top || window;
    const topDoc = topWin.document || document;

    // Kept as a non-invasive runtime trace while the editor is open. This is
    // useful if another builder script dispatches an unexpected second save.
    topWin.__reacgBeaverLastSave = {
      nodeId: strNodeId,
      galleryId: intGalleryId,
      frameUrl: String(window.location.href || ""),
      time: Date.now(),
    };

    // Save directly to the known Beaver node. Reading or submitting whichever
    // settings form is visible is unsafe: Beaver keeps previous module forms
    // in its lightbox DOM, and that is how a second gallery could update the
    // first module.
    const FLBuilder = window.FLBuilder || topWin.FLBuilder;
    const builderWin =
      window.FL &&
      window.FL.Builder &&
      window.FL.Builder.data &&
      typeof window.FL.Builder.data.getLayoutActions === "function"
        ? window
        : topWin;
    const layoutActions =
      builderWin.FL &&
      builderWin.FL.Builder &&
      builderWin.FL.Builder.data &&
      typeof builderWin.FL.Builder.data.getLayoutActions === "function"
        ? builderWin.FL.Builder.data.getLayoutActions()
        : null;
    const settingsConfig =
      builderWin.FLBuilderSettingsConfig || window.FLBuilderSettingsConfig;

    if (layoutActions && typeof layoutActions.updateNodeSettings === "function" && strNodeId) {
      // Beaver persists the page from its layout store, not from the rendered
      // HTML. Updating the store is therefore essential: a direct ajax call
      // appears to work until Beaver saves the page and restores its old node.
      const currentSettings =
        settingsConfig && settingsConfig.nodes && settingsConfig.nodes[strNodeId]
          ? settingsConfig.nodes[strNodeId]
          : {};
      const settings = Object.assign({}, currentSettings, {
        gallery_id: intGalleryId,
      });

      if (settingsConfig && settingsConfig.nodes) {
        settingsConfig.nodes[strNodeId] = settings;
      }

      if (typeof FLBuilder._showNodeLoading === "function") {
        FLBuilder._showNodeLoading(strNodeId);
      }

      layoutActions.updateNodeSettings(strNodeId, settings, function (response) {
        // Use Beaver's own completion handler so its Redux state, canvas, and
        // saved layout all receive the response for this exact node.
        if (typeof FLBuilder._saveSettingsComplete === "function") {
          FLBuilder._saveSettingsComplete(true, response);
        } else if (typeof FLBuilder._updateLayout === "function") {
          FLBuilder._updateLayout();
        }
        if (typeof onComplete === "function") {
          onComplete();
        }
      });
    } else if (FLBuilder && typeof FLBuilder.ajax === "function" && strNodeId) {
      // Compatibility fallback for older Beaver versions without the layout
      // actions API. Current Beaver versions always use the branch above.
      FLBuilder.ajax(
        {
          action: "save_settings",
          node_id: strNodeId,
          settings: { gallery_id: intGalleryId },
        },
        function (response) {
          const data =
            typeof FLBuilder._jsonParse === "function"
              ? FLBuilder._jsonParse(response)
              : response;
          if (data && data.layout && typeof FLBuilder._renderLayout === "function") {
            FLBuilder._renderLayout(data.layout);
          } else if (typeof FLBuilder._updateLayout === "function") {
            FLBuilder._updateLayout();
          }
          if (typeof onComplete === "function") {
            onComplete();
          }
        }
      );
    }

    // Update DOM attributes on canvas module container across all docs.
    try {
      const targetDocs = [topDoc, document];
      if (
        FLBuilder &&
        FLBuilder.UIIFrame &&
        typeof FLBuilder.UIIFrame.getIFrameWindow === "function"
      ) {
        const bbWin = FLBuilder.UIIFrame.getIFrameWindow();
        if (
          bbWin &&
          bbWin.document &&
          targetDocs.indexOf(bbWin.document) === -1
        ) {
          targetDocs.push(bbWin.document);
        }
      }
      targetDocs.forEach(function (d) {
        const $module = $(
          ".fl-node-" + strNodeId + ', [data-node="' + strNodeId + '"]',
          d
        );
        if ($module.length) {
          $module
            .find(".reacg-gallery")
            .attr("data-gallery-id", intGalleryId);
          $module
            .find(".reacg-builder-placeholder")
            .attr("data-gallery-id", intGalleryId);
        }
      });
    } catch (e) {}
  }

  // Expose helper globally on current and top window
  window.setBeaverBuilderGalleryId = setBeaverBuilderGalleryId;
  try {
    if (window.top && window.top !== window) {
      window.top.setBeaverBuilderGalleryId = setBeaverBuilderGalleryId;
    }
  } catch (e) {}

  /**
   * Helper to open ReGallery modal for a Beaver Builder module.
   *
   * @param {string} nodeId
   * @param {number|string} galleryId
   */
  function openReGalleryModal(nodeId, galleryId) {
    const topWin = window.top || window;
    const modal = topWin.ReacgBuilderModal || window.ReacgBuilderModal;
    if (!modal) {
      console.error("ReacgBuilderModal is not loaded.");
      return;
    }

    if (typeof modal.init === "function") {
      modal.init();
    }

    const currentPostId = parseInt(galleryId, 10) || 0;
    const safeWidgetId = String(nodeId || "").trim();

    // If modal is already open for this exact widget and gallery, do not re-open/reset
    if (
      modal.isOpen &&
      modal.currentWidgetId === safeWidgetId &&
      (currentPostId === 0 || modal.currentGalleryId === currentPostId)
    ) {
      return;
    }

    modal.open({
      galleryId: currentPostId,
      widgetId: safeWidgetId,
      onSave: function (savedGalleryId) {
        // Never retain a module ID from an earlier modal opening. The modal
        // itself is shared by every Beaver module, so resolve its active
        // widget at the instant the gallery is selected.
        const activeNodeId = String(modal.currentWidgetId || safeWidgetId).trim();
        let targetWindow = window;

        // The modal lives in the parent document while Beaver's node store
        // lives in its UI iframe. Execute the saved helper in the frame which
        // actually contains this exact module.
        try {
          const topWin = window.top || window;
          const frames = topWin.frames || [];
          for (let i = 0; i < frames.length; i++) {
            try {
              const candidate = frames[i];
              if (
                candidate.document.querySelector(
                  ".fl-node-" + activeNodeId + ', [data-node="' + activeNodeId + '"]',
                ) &&
                typeof candidate.setBeaverBuilderGalleryId === "function"
              ) {
                targetWindow = candidate;
                break;
              }
            } catch (e) {}
          }
        } catch (e) {}

        const refreshModalSettings = function () {
          // loadGallery mounts settings before this asynchronous Beaver node
          // update finishes. Mount once more from the returned module markup,
          // which now includes the newly selected gallery's data.
          if (
            modal.currentWidgetId === activeNodeId &&
            parseInt(modal.currentGalleryId, 10) === parseInt(savedGalleryId, 10)
          ) {
            window.setTimeout(function () {
              modal.mountReactSettingsInCanvas(savedGalleryId, false);
            }, 0);
          }
        };

        if (
          targetWindow !== window &&
          typeof targetWindow.setBeaverBuilderGalleryId === "function"
        ) {
          targetWindow.setBeaverBuilderGalleryId(
            activeNodeId,
            savedGalleryId,
            refreshModalSettings,
          );
        } else {
          setBeaverBuilderGalleryId(
            activeNodeId,
            savedGalleryId,
            refreshModalSettings,
          );
        }
      },
    });
  }

  // Hook into placeholder clicks in the editor window
  $(document).on("click", ".reacg-builder-open-modal-btn", function (e) {
    e.preventDefault();
    e.stopPropagation();

    const $placeholder = $(this).closest(".reacg-builder-placeholder");
    const $module = $placeholder.closest(".fl-module, [data-node]");
    const nodeId =
      $placeholder.attr("data-widget-id") ||
      getBeaverNodeId($module) ||
      "";
    const galleryId =
      parseInt(
        $module.find(".reacg-gallery").attr("data-gallery-id") ||
          $placeholder.attr("data-gallery-id") ||
          0,
        10
      ) || 0;

    openReGalleryModal(nodeId, galleryId);
  });

  // Global click interceptor inside Beaver Builder canvas for galleries
  window.addEventListener(
    "click",
    function (e) {
      const topWin = window.top || window;
      const isFLBuilderActive = Boolean(
        topWin.FLBuilder ||
          window.FLBuilder ||
          (document.body &&
            document.body.classList.contains("fl-builder-edit"))
      );
      if (!isFLBuilderActive) {
        return;
      }

      const gallery =
        e.target && e.target.closest && e.target.closest(".reacg-gallery");
      if (!gallery) {
        return;
      }

      // Stop lightbox, modal popups, and links inside React gallery
      e.preventDefault();
      e.stopPropagation();
      e.stopImmediatePropagation();

      const nodeId =
        String(gallery.getAttribute("data-widget-id") || "").trim() ||
        getBeaverNodeId(gallery) ||
        (gallery.id && gallery.id.indexOf("reacg-root") === 0
          ? gallery.id.replace("reacg-root", "")
          : "");
      const currentPostId =
        parseInt(gallery.getAttribute("data-gallery-id"), 10) || 0;

      openReGalleryModal(nodeId, currentPostId);
    },
    true
  );

  /**
   * Bind Beaver Builder FLBuilder JS hooks when FLBuilder is available.
   */
  function bindFLBuilderHooks() {
    const topWin = window.top || window;
    const FLBuilder = topWin.FLBuilder || window.FLBuilder;

    if (!FLBuilder || typeof FLBuilder.addHook !== "function") {
      return;
    }

    if (FLBuilder._reacgHooksBound) {
      return;
    }
    FLBuilder._reacgHooksBound = true;

    // Auto-open ReGallery modal when ReGallery module settings lightbox is opened
    FLBuilder.addHook("didShowLightbox", function () {
      const topDoc = topWin.document || document;
      const previewNodeId = String(
        (FLBuilder.preview && FLBuilder.preview.nodeId) || "",
      ).trim();
      let $form = $(
        "form.fl-builder-settings:visible, form.fl-builder-module-settings:visible",
        topDoc,
      ).last();
      if (previewNodeId) {
        const $targetForm = $(
          '.fl-builder-settings[data-node="' + previewNodeId + '"]',
          topDoc,
        ).last();
        if ($targetForm.length) {
          $form = $targetForm;
        }
      }
      const $input = $form.find('input[name="gallery_id"]');

      if ($input.length) {
        const nodeId =
          previewNodeId ||
          $form.attr("data-node") ||
          $form.find('input[name="node_id"]').val() ||
          "";
        const nodeSettings =
          typeof FLBuilderSettingsConfig !== "undefined" &&
          FLBuilderSettingsConfig.nodes &&
          FLBuilderSettingsConfig.nodes[nodeId]
            ? FLBuilderSettingsConfig.nodes[nodeId]
            : null;
        const galleryId = parseInt(
          nodeSettings && nodeSettings.gallery_id !== undefined
            ? nodeSettings.gallery_id
            : $input.val(),
          10,
        ) || 0;
        setTimeout(function () {
          openReGalleryModal(nodeId, galleryId);
        }, 50);
      } else {
        // If a different module settings lightbox is opened, auto-close the ReGallery modal
        const modal = topWin.ReacgBuilderModal || window.ReacgBuilderModal;
        if (modal && modal.isOpen) {
          modal.close();
        }
      }
    });

    // Auto-close modal when Beaver Builder settings lightbox is closed
    FLBuilder.addHook("didHideLightbox", function () {
      const modal = topWin.ReacgBuilderModal || window.ReacgBuilderModal;
      if (modal && modal.isOpen) {
        modal.close();
      }
    });

    // Re-initialize React galleries when Beaver Builder re-renders layout or completes AJAX
    FLBuilder.addHook("didRenderLayout", function () {
      reacg_loadApp();
    });

    FLBuilder.addHook("didRenderLayoutComplete", function () {
      reacg_loadApp();
    });

    FLBuilder.addHook("didCompleteAJAX", function () {
      reacg_loadApp();
    });
  }

  // Attempt to bind FLBuilder hooks immediately and on DOM ready / intervals
  bindFLBuilderHooks();
  $(function () {
    bindFLBuilderHooks();
    reacg_loadApp();
    const topWin = window.top || window;
    const modal = topWin.ReacgBuilderModal || window.ReacgBuilderModal;
    if (modal && typeof modal.init === "function") {
      modal.init();
    }
    setTimeout(bindFLBuilderHooks, 500);
    setTimeout(bindFLBuilderHooks, 1500);
    setTimeout(reacg_loadApp, 300);
    setTimeout(reacg_loadApp, 1000);
  });
})(jQuery);
