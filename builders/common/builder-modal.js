/**
 * ReGallery Unified Floating Options Panel
 *
 * Draggable floating sidebar panel (Gutenberg-style) providing live preview synchronization across page builders.
 */
(function ($) {
  "use strict";

  const SETTINGS_SELECTORS = {
    "#reacg_settings": true,
    "#reacg-gallery-images": true,
  };

  /**
   * Resolve top admin document where sidebar options live.
   *
   * @returns {Document}
   */
  function getAdminDocument() {
    return document;
  }

  /**
   * Setup click interceptor on canvas document to prevent lightbox/links and open modal.
   *
   * @param {Document} canvasDoc
   */
  function setupCanvasClickInterceptor(canvasDoc) {
    if (!canvasDoc || canvasDoc._reacgClickInterceptorAttached) {
      return;
    }
    canvasDoc._reacgClickInterceptorAttached = true;

    canvasDoc.addEventListener(
      "click",
      function (e) {
        const topWin = window.top || window.parent;
        const isEditorActive = Boolean(
          topWin &&
          (topWin.elementor ||
            topWin.bricksData ||
            topWin.bricks ||
            (topWin.document &&
              topWin.document.getElementById("bricks-builder-iframe")) ||
            topWin.ReacgBuilderModal),
        );
        if (!isEditorActive) {
          return;
        }

        if (
          topWin.elementor &&
          typeof topWin.elementor.isEditMode === "function" &&
          !topWin.elementor.isEditMode()
        ) {
          return;
        }
        if (
          canvasDoc.body &&
          canvasDoc.body.classList.contains("elementor-editor-preview")
        ) {
          return;
        }

        const gallery =
          e.target && e.target.closest && e.target.closest(".reacg-gallery");
        const placeholderBtn =
          e.target &&
          e.target.closest &&
          e.target.closest(".reacg-builder-open-modal-btn");
        if (!gallery && !placeholderBtn) {
          return;
        }

        // Intercept: Prevent inner lightbox popup and link navigation
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();

        const targetEl = gallery || placeholderBtn;
        const widgetWrapper =
          targetEl.closest(".elementor-element") ||
          targetEl.closest(".brxe-reacg") ||
          targetEl.closest("[data-bricks-element-id]") ||
          targetEl.closest("[data-script-id]");

        const rawWidgetId = widgetWrapper
          ? widgetWrapper.getAttribute("data-id") ||
            widgetWrapper.getAttribute("data-bricks-element-id") ||
            widgetWrapper.getAttribute("data-script-id") ||
            (widgetWrapper.id ? widgetWrapper.id.replace("brxe-", "") : "")
          : gallery && gallery.id && gallery.id.indexOf("reacg-root") === 0
            ? gallery.id.replace("reacg-root", "")
            : "";

        const widgetId = (rawWidgetId || "")
          .trim()
          .replace(/[^a-zA-Z0-9_-]/g, "");

        let galleryId = gallery
          ? gallery.getAttribute("data-gallery-id") || 0
          : 0;
        if (!galleryId && widgetWrapper) {
          const innerGal = widgetWrapper.querySelector(".reacg-gallery");
          if (innerGal) {
            galleryId = innerGal.getAttribute("data-gallery-id") || 0;
          }
        }

        const modal = topWin.ReacgBuilderModal || window.ReacgBuilderModal;
        if (modal) {
          modal.open({
            galleryId: parseInt(galleryId, 10) || 0,
            widgetId: widgetId || "",
            onSave: function (savedGalleryId) {
              // Elementor save
              if (topWin.elementor && widgetId) {
                const container =
                  typeof topWin.elementor.getContainer === "function"
                    ? topWin.elementor.getContainer(widgetId)
                    : null;
                let model = container ? container.model : null;
                if (
                  !model &&
                  topWin.elementor.elements &&
                  typeof topWin.findElementorModelDeep === "function"
                ) {
                  model = topWin.findElementorModelDeep(
                    widgetId,
                    topWin.elementor.elements,
                  );
                }
                if (typeof topWin.setPostIdOnTarget === "function") {
                  topWin.setPostIdOnTarget(
                    model,
                    container || (model && model.container),
                    savedGalleryId,
                  );
                }
              }

              // Bricks save
              if (typeof topWin.setBricksGalleryId === "function") {
                topWin.setBricksGalleryId(widgetId, savedGalleryId);
              } else if (
                topWin.bricksData &&
                Array.isArray(topWin.bricksData.elements)
              ) {
                const el = topWin.bricksData.elements.find(function (item) {
                  return String(item.id) === String(widgetId);
                });
                if (el) {
                  if (!el.settings) el.settings = {};
                  el.settings.gallery_id = parseInt(savedGalleryId, 10);
                }
              }
            },
          });
        }
      },
      true,
    );
  }

  /**
   * Ensure dashicons stylesheet is loaded in the target document.
   * Required for builders like Bricks where dashicons are not loaded by default.
   *
   * @param {Document} targetDoc
   */
  function ensureDashicons(targetDoc) {
    if (!targetDoc) {
      return;
    }
    try {
      if (
        targetDoc.getElementById("dashicons-css") ||
        targetDoc.querySelector('link[href*="dashicons"]')
      ) {
        return;
      }

      // Try to get resolved dashicons URL from current document
      let dashiconsUrl = "";
      const localLink = document.querySelector('link[href*="dashicons"]');
      if (localLink && localLink.href) {
        dashiconsUrl = localLink.href;
      } else if (
        typeof reacg_builder_data !== "undefined" &&
        reacg_builder_data.dashicons_url
      ) {
        dashiconsUrl = reacg_builder_data.dashicons_url;
      }

      if (!dashiconsUrl) {
        const pluginUrl =
          typeof reacg_builder_data !== "undefined" &&
          reacg_builder_data.plugin_url
            ? reacg_builder_data.plugin_url
            : "";
        if (pluginUrl && pluginUrl.indexOf("/wp-content/") !== -1) {
          dashiconsUrl =
            pluginUrl.split("/wp-content/")[0] +
            "/wp-includes/css/dashicons.min.css";
        } else {
          dashiconsUrl = "/wp-includes/css/dashicons.min.css";
        }
      }

      const link = targetDoc.createElement("link");
      link.rel = "stylesheet";
      link.id = "dashicons-css";
      link.href = dashiconsUrl;
      link.media = "all";
      (targetDoc.head || targetDoc.documentElement).appendChild(link);
    } catch (e) {}
  }

  /**
   * Resolve active canvas document (Elementor iframe, Bricks iframe, Gutenberg iframe, or main document).
   *
   * @returns {Document}
   */
  function getCanvasDocument() {
    let doc = document;
    try {
      const elIframe = document.querySelector("#elementor-preview-iframe");
      if (
        elIframe &&
        elIframe.contentDocument &&
        elIframe.contentDocument.body
      ) {
        doc = elIframe.contentDocument;
      }
    } catch (e) {}

    try {
      const bricksIframe = document.querySelector("#bricks-builder-iframe");
      if (
        bricksIframe &&
        bricksIframe.contentDocument &&
        bricksIframe.contentDocument.body
      ) {
        doc = bricksIframe.contentDocument;
      }
    } catch (e) {}

    try {
      const gIframe = document.querySelector('iframe[name="editor-canvas"]');
      if (gIframe && gIframe.contentDocument && gIframe.contentDocument.body) {
        doc = gIframe.contentDocument;
      }
    } catch (e) {}

    setupCanvasClickInterceptor(doc);
    ensureDashicons(doc);
    return doc;
  }

  /**
   * Bridge admin helper and dialog APIs bi-directionally between admin and canvas iframe window.
   *
   * @param {Document} canvasDoc
   */
  function bridgeAdminApis(canvasDoc) {
    const canvasWin = canvasDoc && canvasDoc.defaultView;
    if (!canvasWin || canvasWin === window) {
      return;
    }
    const names = [
      "reacg_open_ai_generate_content_modal",
      "reacg_open_free_trial_offer_dialog",
      "reacg_open_free_trial_layout_dialog",
      "reacg_open_premium_offer_dialog",
      "reacg_open_pro_layout_dialog",
      "reacg_open_need_help_dialog",
      "reacg_open_new_here_dialog",
      "reacg_open_error_dialog",
      "reacg_open_special_offer_dialog",
      "reacg_open_attachment_edit_modal",
      "reacg_reload_preview",
      "reacg_update_item_image_fit",
      "reacg_make_items_sortable",
    ];

    const windows = [window, canvasWin];
    try {
      if (
        window.top &&
        window.top !== window &&
        windows.indexOf(window.top) === -1
      ) {
        windows.push(window.top);
      }
    } catch (e) {}

    names.forEach(function (name) {
      windows.forEach(function (targetWin) {
        if (!targetWin) return;
        const existing = targetWin[name];
        if (typeof existing !== "function" || existing.__reacgBridgeProxy) {
          const proxy = function () {
            const args = arguments;
            const currentDoc = getCanvasDocument();
            const currentWin = currentDoc ? currentDoc.defaultView : null;
            if (currentDoc) {
              syncGalleryStyles(currentDoc);
            }
            const allWins = [window, canvasWin];
            if (currentWin && allWins.indexOf(currentWin) === -1) {
              allWins.push(currentWin);
            }
            try {
              if (
                window.top &&
                window.top !== window &&
                allWins.indexOf(window.top) === -1
              ) {
                allWins.push(window.top);
              }
            } catch (e) {}

            for (let i = 0; i < allWins.length; i++) {
              const w = allWins[i];
              if (
                w &&
                typeof w[name] === "function" &&
                !w[name].__reacgBridgeProxy
              ) {
                return w[name].apply(w, args);
              }
            }
            let retries = 0;
            const timer = setInterval(function () {
              retries++;
              const dynDoc = getCanvasDocument();
              const dynWin = dynDoc ? dynDoc.defaultView : null;
              if (dynDoc) {
                syncGalleryStyles(dynDoc);
              }
              const wins = [window, canvasWin];
              if (dynWin && wins.indexOf(dynWin) === -1) {
                wins.push(dynWin);
              }
              try {
                if (
                  window.top &&
                  window.top !== window &&
                  wins.indexOf(window.top) === -1
                ) {
                  wins.push(window.top);
                }
              } catch (e) {}

              for (let i = 0; i < wins.length; i++) {
                const w = wins[i];
                if (
                  w &&
                  typeof w[name] === "function" &&
                  !w[name].__reacgBridgeProxy
                ) {
                  clearInterval(timer);
                  return w[name].apply(w, args);
                }
              }
              if (retries > 40) {
                clearInterval(timer);
              }
            }, 100);
          };
          proxy.__reacgBridgeProxy = true;
          targetWin[name] = proxy;
        }
      });
    });

    let bridgeRetries = 0;
    const syncDialogBridges = function () {
      names.forEach(function (name) {
        let implementation = null;
        for (let i = 0; i < windows.length; i++) {
          const candidate = windows[i] && windows[i][name];
          if (
            typeof candidate === "function" &&
            !candidate.__reacgBridgeProxy
          ) {
            implementation = candidate;
            break;
          }
        }
        if (!implementation) return;

        windows.forEach(function (targetWin) {
          if (
            targetWin &&
            targetWin[name] &&
            targetWin[name].__reacgBridgeProxy
          ) {
            targetWin[name] = implementation;
          }
        });
      });
    };
    syncDialogBridges();
    const bridgeTimer = setInterval(function () {
      bridgeRetries++;
      syncDialogBridges();
      if (bridgeRetries > 40) {
        clearInterval(bridgeTimer);
      }
    }, 100);

    canvasWin.reacg_global = canvasWin.reacg_global || {};
    if (window.reacg_global) {
      Object.keys(window.reacg_global).forEach(function (k) {
        if (
          k !== "onOptionsChange" &&
          canvasWin.reacg_global[k] === undefined
        ) {
          canvasWin.reacg_global[k] = window.reacg_global[k];
        }
      });
    }

    if (!canvasWin.__reacgProActivatedBridged) {
      canvasWin.__reacgProActivatedBridged = true;
      try {
        canvasWin.addEventListener("reacg:pro-activated", function () {
          window.dispatchEvent(new Event("reacg:pro-activated"));
        });
      } catch (e) {}
    }

    // Bridge onOptionsChange so layout changes in canvas notify admin window
    const origCanvasOnOptionsChange = canvasWin.reacg_global.onOptionsChange;
    canvasWin.reacg_global.onOptionsChange = function (options, context) {
      if (typeof origCanvasOnOptionsChange === "function") {
        origCanvasOnOptionsChange(options, context);
      }
      if (
        window.reacg_global &&
        typeof window.reacg_global.onOptionsChange === "function"
      ) {
        window.reacg_global.onOptionsChange(options, context);
      }
    };
  }

  let latestOptions = null;
  let latestGalleryId = null;

  /**
   * Update in-memory reacg_data options cache across all window contexts.
   *
   * @param {number|string} galleryId
   * @param {object} opts
   */
  function updateReacgDataOptions(galleryId, opts) {
    if (!galleryId || !opts) return;
    const id = parseInt(galleryId, 10);
    if (!id || id <= 0) return;

    const targets = [];
    if (typeof window !== "undefined") targets.push(window);
    try {
      if (window.top && window.top !== window) targets.push(window.top);
    } catch (e) {}

    try {
      const canvasDoc = getCanvasDocument();
      if (
        canvasDoc &&
        canvasDoc.defaultView &&
        targets.indexOf(canvasDoc.defaultView) === -1
      ) {
        targets.push(canvasDoc.defaultView);
      }
    } catch (e) {}

    targets.forEach(function (win) {
      try {
        if (!win.reacg_data) {
          win.reacg_data = {};
        }
        if (!win.reacg_data[id]) {
          win.reacg_data[id] = {};
        }
        win.reacg_data[id].options = $.extend(
          true,
          {},
          win.reacg_data[id].options || {},
          opts,
        );
      } catch (e) {}
    });
  }

  window.reacg_global = window.reacg_global || {};
  const prevAdminOnOptionsChange = window.reacg_global.onOptionsChange;
  window.reacg_global.onOptionsChange = function (options, context) {
    if (typeof prevAdminOnOptionsChange === "function") {
      prevAdminOnOptionsChange(options, context);
    }

    const canvasDoc = getCanvasDocument();
    syncGalleryStyles(canvasDoc);

    latestOptions = options;
    const currentGalId =
      (context && context.galleryId) ||
      (window.ReacgBuilderModal && window.ReacgBuilderModal.currentGalleryId);
    if (currentGalId) {
      latestGalleryId = currentGalId;
      updateReacgDataOptions(currentGalId, options);
    }

    if (context && context.hasChanges === false) {
      if (
        window.ReacgBuilderModal &&
        typeof window.ReacgBuilderModal.updateSaveStatus === "function"
      ) {
        window.ReacgBuilderModal.updateSaveStatus("saved");
      }
      if (canvasDoc && currentGalId) {
        const galleryEl =
          canvasDoc.querySelector(
            '.reacg-gallery[data-gallery-id="' + currentGalId + '"]',
          ) || canvasDoc.querySelector(".reacg-gallery");
        if (galleryEl) {
          galleryEl.setAttribute("data-options-timestamp", Date.now());
        }
      }
    } else if (context && context.hasChanges === true) {
      if (
        window.ReacgBuilderModal &&
        typeof window.ReacgBuilderModal.scheduleAutoSave === "function"
      ) {
        window.ReacgBuilderModal.scheduleAutoSave();
      }
    }
  };

  /**
   * Read CSS text from a style node including Emotion CSSOM rules.
   *
   * @param {HTMLStyleElement} node
   * @returns {string}
   */
  function getStyleSheetText(node) {
    const inline = node && node.textContent ? node.textContent : "";
    try {
      const sheet = node && node.sheet;
      const rules = sheet && (sheet.cssRules || sheet.rules);
      if (rules && rules.length) {
        let css = "";
        for (let i = 0; i < rules.length; i++) {
          css += rules[i].cssText + "\n";
        }
        if (css) {
          return css;
        }
      }
    } catch (e) {}
    return inline;
  }

  /**
   * Sync CSS-in-JS styles from canvas document into admin document.
   *
   * @param {Document} canvasDoc
   */
  function syncGalleryStyles(canvasDoc) {
    const adminDoc = getAdminDocument();
    if (!canvasDoc || !canvasDoc.head || canvasDoc === adminDoc) {
      return;
    }

    if (!canvasDoc.__reacgStyleSyncTargets) {
      canvasDoc.__reacgStyleSyncTargets = [];
    }

    const isGalleryStyleNode = function (node) {
      if (!node || !node.nodeName) {
        return false;
      }
      if (
        node.getAttribute &&
        node.getAttribute("data-reacg-synced-clone") === "1"
      ) {
        return false;
      }
      const name = node.nodeName.toUpperCase();
      if (name === "LINK") {
        const rel = (node.getAttribute("rel") || "").toLowerCase();
        const href = node.getAttribute("href") || "";
        return (
          rel.indexOf("stylesheet") !== -1 &&
          (href.indexOf("wp-gallery") !== -1 ||
            href.indexOf("reacg") !== -1 ||
            href.indexOf("/assets/") !== -1 ||
            href.indexOf("fonts.googleapis") !== -1)
        );
      }
      if (name !== "STYLE") {
        return false;
      }
      const id = node.id || "";
      if (
        id.indexOf("wp-") === 0 ||
        id.indexOf("core-block") === 0 ||
        id.indexOf("elementor") === 0 ||
        id.indexOf("bricks") === 0
      ) {
        return false;
      }
      if (
        node.getAttribute("data-emotion") ||
        node.getAttribute("data-jss") ||
        node.getAttribute("data-styled")
      ) {
        return true;
      }
      const text = getStyleSheetText(node);
      return (
        text.indexOf("reacg-") !== -1 ||
        text.indexOf("Mui") !== -1 ||
        text.indexOf("notistack") !== -1 ||
        text.indexOf("yarl__") !== -1 ||
        text.indexOf("alert") !== -1 ||
        text.indexOf("trial") !== -1
      );
    };

    const upsertStyle = function (node) {
      if (!isGalleryStyleNode(node)) {
        return;
      }

      const targets = canvasDoc.__reacgStyleSyncTargets;
      let dest = null;
      for (let i = 0; i < targets.length; i++) {
        if (targets[i].source === node) {
          dest = targets[i].dest;
          break;
        }
      }

      if (node.nodeName.toUpperCase() === "LINK") {
        if (dest && dest.isConnected) {
          return;
        }
        dest = node.cloneNode(true);
        dest.setAttribute("data-reacg-synced-clone", "1");
        adminDoc.head.appendChild(dest);
        targets.push({ source: node, dest: dest });
        return;
      }

      const css = getStyleSheetText(node);
      if (
        !css &&
        !node.getAttribute("data-emotion") &&
        !node.getAttribute("data-jss")
      ) {
        return;
      }
      if (!dest || !dest.isConnected) {
        dest = adminDoc.createElement("style");
        dest.setAttribute("data-reacg-synced-clone", "1");
        if (node.getAttribute("data-emotion")) {
          dest.setAttribute("data-emotion", node.getAttribute("data-emotion"));
        }
        adminDoc.head.appendChild(dest);
        targets.push({ source: node, dest: dest });
      }
      if (css && dest.textContent !== css) {
        dest.textContent = css;
      }
    };

    const scan = function () {
      const roots = [canvasDoc.head, canvasDoc.body];
      for (let i = 0; i < roots.length; i++) {
        if (!roots[i]) continue;
        roots[i]
          .querySelectorAll("style, link[rel='stylesheet']")
          .forEach(upsertStyle);
      }
    };

    scan();

    if (canvasDoc.__reacgStyleSync) {
      return;
    }
    canvasDoc.__reacgStyleSync = true;

    const observer = new MutationObserver(function () {
      scan();
    });
    observer.observe(canvasDoc.documentElement, {
      childList: true,
      subtree: true,
    });

    let ticks = 0;
    const interval = setInterval(function () {
      ticks++;
      scan();
      if (ticks > 40) {
        clearInterval(interval);
      }
    }, 250);
  }

  /**
   * Native body accessor for a document.
   *
   * @param {Document} doc
   * @returns {function|null}
   */
  function getNativeBodyGetter(doc) {
    const win = doc.defaultView;
    const protos = [];
    if (win && win.Document) protos.push(win.Document.prototype);
    if (win && win.HTMLDocument) protos.push(win.HTMLDocument.prototype);
    protos.push(Document.prototype);
    for (let i = 0; i < protos.length; i++) {
      try {
        const desc = Object.getOwnPropertyDescriptor(protos[i], "body");
        if (desc && desc.get) return desc.get;
      } catch (e) {}
    }
    return null;
  }

  function isGalleryAppCaller() {
    try {
      const stack = new Error().stack || "";
      const isGalleryCaller =
        stack.indexOf("wp-gallery") !== -1 ||
        stack.indexOf("react") !== -1 ||
        stack.indexOf("Portal") !== -1 ||
        stack.indexOf("Dialog") !== -1 ||
        stack.indexOf("Modal") !== -1 ||
        stack.indexOf("Popover") !== -1 ||
        stack.indexOf("Mui") !== -1 ||
        stack.indexOf("chunk") !== -1 ||
        stack.indexOf("trial") !== -1 ||
        stack.indexOf("alert") !== -1;
      if (
        (stack.indexOf("elementor") !== -1 || stack.indexOf("bricks") !== -1) &&
        !isGalleryCaller
      ) {
        return false;
      }
      return isGalleryCaller;
    } catch (e) {
      return false;
    }
  }

  /**
   * Portal MUI dialogs / modals to admin document body.
   *
   * @param {Document} canvasDoc
   */
  function enableGalleryOverlayPortal(canvasDoc) {
    const adminDoc = getAdminDocument();
    if (
      !canvasDoc ||
      canvasDoc === adminDoc ||
      canvasDoc.__reacgOverlayPortal
    ) {
      return;
    }

    const nativeBodyGet = getNativeBodyGetter(canvasDoc);
    if (!nativeBodyGet) {
      return;
    }

    canvasDoc.__reacgOverlayPortal = true;
    Object.defineProperty(canvasDoc, "body", {
      configurable: true,
      enumerable: true,
      get: function () {
        if (isGalleryAppCaller()) {
          return adminDoc.body;
        }
        return nativeBodyGet.call(canvasDoc);
      },
    });
  }

  function isMuiTooltip(node) {
    if (!node || node.nodeType !== 1) return false;
    const className =
      node.className && node.className.toString
        ? node.className.toString()
        : "";
    return (
      className.indexOf("MuiTooltip") !== -1 ||
      node.getAttribute("role") === "tooltip" ||
      !!(
        node.querySelector &&
        node.querySelector('[role="tooltip"], .MuiTooltip-tooltip')
      )
    );
  }

  function isMuiDialog(node) {
    if (!node || node.nodeType !== 1) return false;
    const className =
      node.className && node.className.toString
        ? node.className.toString()
        : "";
    return (
      className.indexOf("MuiDialog") !== -1 ||
      className.indexOf("reacg-templates-dialog") !== -1 ||
      className.indexOf("alert") !== -1 ||
      className.indexOf("trial") !== -1 ||
      node.getAttribute("role") === "dialog" ||
      !!(
        node.querySelector &&
        node.querySelector(
          ".MuiDialog-root, .MuiDialog-container, .MuiDialog-paper, .reacg-templates-dialog, .alert, .trial-success-state, [role='dialog']",
        )
      )
    );
  }

  function isMuiOverlay(node) {
    if (!node || node.nodeType !== 1 || isMuiTooltip(node) || isMuiDialog(node))
      return false;
    const className =
      node.className && node.className.toString
        ? node.className.toString()
        : "";
    return (
      className.indexOf("MuiPopover-root") !== -1 ||
      className.indexOf("MuiMenu-root") !== -1
    );
  }

  function getExpandedSettingsAnchor(adminDoc) {
    const roots = [
      adminDoc.getElementById("reacg_settings"),
      adminDoc.querySelector(".reacg-builder-panel"),
    ];
    for (let i = 0; i < roots.length; i++) {
      if (!roots[i]) continue;
      const expanded = roots[i].querySelector('[aria-expanded="true"]');
      if (expanded) return expanded;
    }
    return null;
  }

  function getOverlayWidthSource(anchor) {
    return (
      anchor.closest(".MuiInputBase-root") ||
      anchor.closest(".MuiFormControl-root") ||
      anchor.closest(".MuiButton-root") ||
      anchor
    );
  }

  function repositionMuiOverlay(node, canvasDoc) {
    if (!node || isMuiDialog(node) || isMuiTooltip(node)) return;
    const adminDoc = getAdminDocument();
    const canvasWin = canvasDoc.defaultView;
    const adminWin = adminDoc.defaultView;

    node.style.setProperty("z-index", "1000000", "important");

    const apply = function () {
      if (isMuiDialog(node)) return;
      syncGalleryStyles(canvasDoc);
      try {
        if (canvasWin) canvasWin.dispatchEvent(new Event("resize"));
        if (adminWin) adminWin.dispatchEvent(new Event("resize"));
      } catch (e) {}

      const anchor = getExpandedSettingsAnchor(adminDoc);
      if (!anchor || !adminWin) return;

      const positioned =
        node.querySelector("[data-popper-placement]") ||
        node.querySelector(".MuiPaper-root") ||
        node.firstElementChild;
      if (
        !positioned ||
        (positioned.className &&
          positioned.className.toString().indexOf("MuiBackdrop-root") !== -1)
      )
        return;
      if (isMuiDialog(positioned)) return;

      const widthSource = getOverlayWidthSource(anchor);
      const rect = widthSource.getBoundingClientRect();
      const minWidth = Math.ceil(rect.width);
      const viewportWidth =
        adminWin.innerWidth || adminDoc.documentElement.clientWidth;
      let left = rect.left;
      if (left + minWidth > viewportWidth - 8) {
        left = Math.max(8, rect.right - minWidth);
      }
      const top = rect.bottom + 4;

      node.style.setProperty("z-index", "1000000", "important");
      positioned.style.setProperty("z-index", "1000000", "important");
      positioned.style.setProperty("position", "fixed", "important");
      positioned.style.setProperty("top", top + "px", "important");
      positioned.style.setProperty("left", left + "px", "important");
      positioned.style.setProperty("transform", "none", "important");
      positioned.style.setProperty("margin", "0", "important");
      positioned.style.setProperty("right", "auto", "important");
      positioned.style.setProperty("min-width", minWidth + "px", "important");
      positioned.style.setProperty("width", minWidth + "px", "important");
      positioned.style.setProperty("box-sizing", "border-box", "important");

      const list = node.querySelector(
        '.MuiList-root, .MuiMenu-list, [role="listbox"]',
      );
      if (list) {
        list.style.setProperty("min-width", minWidth + "px", "important");
      }
    };

    apply();
    requestAnimationFrame(function () {
      apply();
      requestAnimationFrame(apply);
    });
    setTimeout(apply, 50);
    setTimeout(apply, 150);
  }

  function enableMuiPopoverFix(canvasDoc) {
    const adminDoc = getAdminDocument();
    if (
      !canvasDoc ||
      canvasDoc === adminDoc ||
      canvasDoc.__reacgMuiPopoverFix
    ) {
      return;
    }
    canvasDoc.__reacgMuiPopoverFix = true;

    const watchNode = function (node) {
      if (!node || node.nodeType !== 1) return;
      if (isMuiTooltip(node) || isMuiDialog(node)) {
        if (isMuiDialog(node) && node.style) {
          node.style.setProperty("z-index", "10000000", "important");
        }
        syncGalleryStyles(canvasDoc);
        return;
      }
      if (isMuiOverlay(node)) {
        repositionMuiOverlay(node, canvasDoc);
        return;
      }

      const attrObserver = new MutationObserver(function () {
        if (isMuiTooltip(node) || isMuiDialog(node)) {
          attrObserver.disconnect();
          if (isMuiDialog(node) && node.style) {
            node.style.setProperty("z-index", "10000000", "important");
          }
          syncGalleryStyles(canvasDoc);
          return;
        }
        if (isMuiOverlay(node)) {
          attrObserver.disconnect();
          repositionMuiOverlay(node, canvasDoc);
        }
      });
      attrObserver.observe(node, {
        attributes: true,
        attributeFilter: ["class"],
      });
      setTimeout(function () {
        attrObserver.disconnect();
      }, 1000);
    };

    const observeBody = function (target) {
      if (!target || target.__reacgMuiObserved) return;
      target.__reacgMuiObserved = true;
      const observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
          mutation.addedNodes.forEach(watchNode);
        });
      });
      observer.observe(target, { childList: true });
    };

    observeBody(canvasDoc.body);
    observeBody(adminDoc.body);
  }

  function enableBuilderProControls(canvasDoc) {
    const adminDoc = getAdminDocument();
    const docs = [canvasDoc, adminDoc];
    const bind = function (doc) {
      if (!doc || !doc.body) return;
      doc
        .querySelectorAll(".reacg-settings-panel-tabs__tab")
        .forEach(function (button) {
          if (button.textContent.trim() !== "Lightbox") return;
          button.classList.remove("Mui-disabled");
          button.removeAttribute("disabled");
          if (button.__reacgProControlBound) return;
          button.__reacgProControlBound = true;
          button.addEventListener(
            "click",
            function (event) {
              event.preventDefault();
              event.stopPropagation();
              const win = doc.defaultView || window;
              const topWin = win.top || win;
              const openDialog =
                topWin.reacg_open_premium_offer_dialog ||
                win.reacg_open_premium_offer_dialog;
              if (typeof openDialog === "function") {
                openDialog({ utm_medium: "builder" });
              }
            },
            true,
          );
        });
    };

    docs.forEach(bind);
    if (canvasDoc && !canvasDoc.__reacgProControlsObserver) {
      canvasDoc.__reacgProControlsObserver = new MutationObserver(function () {
        docs.forEach(bind);
      });
      canvasDoc.__reacgProControlsObserver.observe(canvasDoc.body, {
        childList: true,
        subtree: true,
      });
    }
  }

  /**
   * Let the canvas gallery app find sidebar targets that live in the admin document.
   *
   * @param {Document} canvasDoc
   */
  function enableSettingsPortal(canvasDoc) {
    const adminDoc = getAdminDocument();
    if (!canvasDoc || canvasDoc === adminDoc) {
      return;
    }

    bridgeAdminApis(canvasDoc);
    enableGalleryOverlayPortal(canvasDoc);
    enableMuiPopoverFix(canvasDoc);
    enableBuilderProControls(canvasDoc);
    syncGalleryStyles(canvasDoc);

    if (canvasDoc.__reacgSettingsPortal) {
      return;
    }

    canvasDoc.__reacgSettingsPortal = true;
    const origQuerySelector = canvasDoc.querySelector.bind(canvasDoc);
    canvasDoc.querySelector = function (selector) {
      if (SETTINGS_SELECTORS[selector]) {
        try {
          const adminTarget = adminDoc.querySelector(selector);
          if (adminTarget) {
            return adminTarget;
          }
        } catch (error) {}
      }
      return origQuerySelector(selector);
    };
  }

  /**
   * Click the gallery mount button in the same document as the gallery root.
   *
   * @param {Element} loadApp
   * @param {string} rootId
   * @returns {boolean}
   */
  function triggerLoadApp(loadApp, rootId) {
    if (!loadApp) return false;
    loadApp.setAttribute("data-id", rootId);

    try {
      if (typeof loadApp.onclick === "function") {
        loadApp.onclick.call(loadApp);
        return true;
      }
    } catch (error) {}

    try {
      loadApp.click();
      return true;
    } catch (error) {}

    const view =
      loadApp.ownerDocument && loadApp.ownerDocument.defaultView
        ? loadApp.ownerDocument.defaultView
        : window;
    loadApp.dispatchEvent(
      new MouseEvent("click", {
        bubbles: true,
        cancelable: true,
        view: view,
      }),
    );
    return true;
  }

  const ReacgBuilderModal = {
    isOpen: false,
    currentGalleryId: 0,
    currentWidgetId: "",
    onSaveCallback: null,
    onCloseCallback: null,
    isInitialized: false,
    autoSaveTimer: null,
    pendingSave: false,
    _saveStatusTimeout: null,

    init: function () {
      if (this.isInitialized) {
        return;
      }
      this.buildDom();
      this.bindEvents();
      this.initDraggable();
      this.isInitialized = true;
    },

    getData: function () {
      if (typeof reacg_builder_data !== "undefined") {
        return reacg_builder_data;
      }
      if (typeof reacg !== "undefined") {
        return reacg;
      }
      return {};
    },

    buildDom: function () {
      ensureDashicons(document);
      if (
        window.top &&
        window.top.document &&
        window.top.document !== document
      ) {
        ensureDashicons(window.top.document);
      }

      if ($("#reacg-builder-panel-backdrop").length) {
        return;
      }

      const data = this.getData();
      const pluginUrl = data.plugin_url || "";
      const iconUrl = pluginUrl + "/assets/images/icon.svg";

      const html = `
        <div id="reacg-builder-panel-backdrop" class="reacg-builder-panel-backdrop reacg-hidden">
          <div id="reacg-builder-panel" class="reacg-builder-panel">
            <!-- Full Panel Loading Spinner Overlay -->
            <div class="reacg-bp-spinner-wrap reacg-hidden">
              <span class="spinner is-active"></span>
            </div>

            <!-- Header (Drag Handle) -->
            <div class="reacg-bp-header" title="Drag to move panel">
              <div class="reacg-bp-brand">
                <span class="reacg-bp-drag-handle">
                  <span class="dashicons dashicons-move"></span>
                </span>
                <img src="${iconUrl}" alt="Re Gallery" />
                <span>Re Gallery</span>
              </div>
              <div class="reacg-bp-header-actions">
                <span class="reacg-bp-save-status reacg-hidden"></span>
                <span class="reacg-bp-close-btn" aria-label="Close">
                  <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M19 6.41 17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"></path></svg>
                </span>
              </div>
            </div>

            <!-- Gallery Select Dropdown Bar -->
            <div class="reacg-bp-select-bar">
              <select id="reacg-bp-gallery-select" class="reacg-bp-gallery-select"></select>
              <button type="button" class="reacg-bp-btn-new-gallery button button-secondary" title="Create New Gallery">+ New</button>
            </div>

            <!-- Create Gallery Form -->
            <div class="reacg-bp-create-form reacg-hidden">
              <input type="text" class="reacg-bp-new-title" placeholder="Gallery title..." />
              <button type="button" class="reacg-bp-btn-create-submit button button-primary">Create</button>
              <button type="button" class="reacg-bp-btn-create-cancel button button-secondary">Cancel</button>
            </div>

            <!-- Body -->
            <div class="reacg-bp-body">
              <!-- Media (Gallery Images) Section -->
              <div class="reacg-bp-images-section">
                <h3 class="reacg-gallery-images-section-title">Media</h3>
                <div id="reacg-gallery-images"></div>
              </div>

              <!-- React Settings Section -->
              <div class="reacg-bp-settings-section">
                <h3 class="reacg-gallery-settings-section-title">Settings</h3>
                <div id="reacg_settings" class="reacg-wrapper"></div>
              </div>
            </div>
          </div>
        </div>
      `;

      $("body").append(html);
    },

    /**
     * Make the floating panel draggable anywhere on screen.
     */
    initDraggable: function () {
      const $panel = $("#reacg-builder-panel");
      const $header = $panel.find(".reacg-bp-header");

      let isDragging = false;
      let startX = 0;
      let startY = 0;
      let initialLeft = 0;
      let initialTop = 0;

      $header.on("mousedown touchstart", function (e) {
        // Don't drag if clicking buttons or actions inside header
        if ($(e.target).closest("button, input, select, a").length) {
          return;
        }

        isDragging = true;
        $panel.addClass("reacg-is-dragging");

        const clientX =
          e.type === "touchstart"
            ? e.originalEvent.touches[0].clientX
            : e.clientX;
        const clientY =
          e.type === "touchstart"
            ? e.originalEvent.touches[0].clientY
            : e.clientY;

        startX = clientX;
        startY = clientY;

        const rect = $panel.get(0).getBoundingClientRect();
        initialLeft = rect.left;
        initialTop = rect.top;

        // Switch from right-based to left/top-based positioning
        $panel.css({
          left: initialLeft + "px",
          top: initialTop + "px",
          right: "auto",
          bottom: "auto",
        });

        e.preventDefault();
      });

      $(document).on("mousemove.reacgDrag touchmove.reacgDrag", function (e) {
        if (!isDragging) return;

        const clientX =
          e.type === "touchmove"
            ? e.originalEvent.touches[0].clientX
            : e.clientX;
        const clientY =
          e.type === "touchmove"
            ? e.originalEvent.touches[0].clientY
            : e.clientY;

        const deltaX = clientX - startX;
        const deltaY = clientY - startY;

        let newLeft = initialLeft + deltaX;
        let newTop = initialTop + deltaY;

        // Viewport bounds clamping
        const panelWidth = $panel.outerWidth();
        const panelHeight = $panel.outerHeight();
        const winWidth = $(window).width();
        const winHeight = $(window).height();

        const minLeft = 10;
        const maxLeft = Math.max(10, winWidth - panelWidth - 10);
        const minTop = 10;
        const maxTop = Math.max(10, winHeight - panelHeight - 10);

        newLeft = Math.min(Math.max(newLeft, minLeft), maxLeft);
        newTop = Math.min(Math.max(newTop, minTop), maxTop);

        $panel.css({
          left: newLeft + "px",
          top: newTop + "px",
        });
      });

      $(document).on("mouseup.reacgDrag touchend.reacgDrag", function () {
        if (isDragging) {
          isDragging = false;
          $panel.removeClass("reacg-is-dragging");
        }
      });
    },

    bindEvents: function () {
      const self = this;

      // Close button
      $(document).on("click", ".reacg-bp-close-btn", function (e) {
        e.preventDefault();
        self.close();
      });

      // Visual feedback when media images are saved via AJAX
      $(document).ajaxSend(function (e, xhr, settings) {
        if (
          settings &&
          typeof settings.data === "string" &&
          settings.data.indexOf("reacg_save_images") !== -1
        ) {
          self.updateSaveStatus("saving");
        }
      });
      $(document).ajaxSuccess(function (e, xhr, settings) {
        if (
          settings &&
          typeof settings.data === "string" &&
          settings.data.indexOf("reacg_save_images") !== -1
        ) {
          self.updateSaveStatus("saved");
        }
      });

      // Gallery dropdown change
      $(document).on("change", "#reacg-bp-gallery-select", function () {
        const selectedId = parseInt($(this).val(), 10);
        if (!isNaN(selectedId) && selectedId !== self.currentGalleryId) {
          self.loadGallery(selectedId);
        }
      });

      // New Gallery button
      $(document).on("click", ".reacg-bp-btn-new-gallery", function (e) {
        e.preventDefault();
        $(".reacg-bp-create-form").removeClass("reacg-hidden");
        $(".reacg-bp-new-title").val("").focus();
      });

      $(document).on("click", ".reacg-bp-btn-create-cancel", function (e) {
        e.preventDefault();
        $(".reacg-bp-create-form").addClass("reacg-hidden");
      });

      $(document).on("click", ".reacg-bp-btn-create-submit", function (e) {
        e.preventDefault();
        self.handleCreateGallery();
      });

      $(document).on("keydown", ".reacg-bp-new-title", function (e) {
        if (e.key === "Enter") {
          e.preventDefault();
          self.handleCreateGallery();
        }
      });

      // Instantly sync styles when Browse Templates or settings buttons are clicked
      $(document).on(
        "click",
        '#reacg_settings button, #reacg_settings [role="button"], #reacg_settings .MuiButton-root',
        function () {
          const canvasDoc = getCanvasDocument();
          syncGalleryStyles(canvasDoc);
        },
      );
    },

    populateGalleriesDropdown: function (selectedId) {
      const $select = $("#reacg-bp-gallery-select");
      $select.empty();

      const data = this.getData();
      const galleries = data.galleries || {};

      $select.append(
        '<option value="0">' +
          (data.text_select_gallery || "Select gallery") +
          "</option>",
      );

      if (Array.isArray(galleries)) {
        galleries.forEach(function (g) {
          if (parseInt(g.id, 10) === 0) {
            return;
          }
          $select.append(
            `<option value="${g.id}">${g.title || "(no title)"}</option>`,
          );
        });
      } else if (typeof galleries === "object") {
        Object.keys(galleries).forEach(function (id) {
          if (parseInt(id, 10) === 0) {
            return;
          }
          $select.append(`<option value="${id}">${galleries[id]}</option>`);
        });
      }

      const targetVal =
        typeof selectedId !== "undefined" && selectedId !== null
          ? selectedId
          : 0;
      if ($select.find(`option[value="${targetVal}"]`).length) {
        $select.val(targetVal);
      } else {
        $select.val(0);
      }
    },

    handleCreateGallery: function () {
      const self = this;
      const title = $(".reacg-bp-new-title").val().trim() || "(no title)";
      const data = this.getData();
      const ajaxUrl =
        data.ajax_url || (typeof reacg !== "undefined" ? reacg.ajax_url : "");

      if (!ajaxUrl) return;

      $(".reacg-bp-spinner-wrap").removeClass("reacg-hidden");

      fetch(
        ajaxUrl +
          "&action=reacg_save_gallery&gallery_title=" +
          encodeURIComponent(title),
      )
        .then(function (res) {
          return res.json();
        })
        .then(function (newId) {
          const galleryId = parseInt(newId, 10);
          if (galleryId > 0) {
            if (data.galleries && typeof data.galleries === "object") {
              if (Array.isArray(data.galleries)) {
                data.galleries.push({
                  id: galleryId,
                  title: title,
                  shortcode: '[REACG id="' + galleryId + '"]',
                });
              } else {
                data.galleries[galleryId] = title;
              }
            }

            const $select = $("#reacg-bp-gallery-select");
            $select.append(`<option value="${galleryId}">${title}</option>`);
            $select.val(galleryId);

            $(".reacg-bp-create-form").addClass("reacg-hidden");
            self.loadGallery(galleryId);
          } else {
            $(".reacg-bp-spinner-wrap").addClass("reacg-hidden");
            alert("Unable to create gallery.");
          }
        })
        .catch(function (err) {
          $(".reacg-bp-spinner-wrap").addClass("reacg-hidden");
          console.error("Error creating gallery:", err);
        });
    },

    loadGallery: function (galleryId, isOpening) {
      const self = this;
      self.flushAutoSave();
      self.currentGalleryId = parseInt(galleryId, 10);
      if (isNaN(self.currentGalleryId)) {
        self.currentGalleryId = 0;
      }

      $("#reacg-bp-gallery-select").val(self.currentGalleryId);

      $(".reacg-bp-create-form").addClass("reacg-hidden");

      // Notify host builder / Elementor model in real time
      if (typeof self.onSaveCallback === "function") {
        self.onSaveCallback(self.currentGalleryId);
      }

      if (self.currentGalleryId === 0) {
        // "Select gallery" (ID: 0) - no gallery data in DB, mounts empty gallery state like Gutenberg
        $(".reacg-bp-images-section").addClass("reacg-hidden");
        $(".reacg-bp-settings-section").addClass("reacg-hidden");
        $("#reacg-gallery-images").empty();
        self.mountReactSettingsInCanvas(0, isOpening);
        return;
      }

      $(".reacg-bp-settings-section").removeClass("reacg-hidden");

      if (self.currentGalleryId === -1) {
        // "All Images" state (dynamic media, hide manual images section like Gutenberg)
        $(".reacg-bp-images-section").addClass("reacg-hidden");
        $("#reacg-gallery-images").empty();
        self.mountReactSettingsInCanvas(-1, isOpening);
        return;
      }

      // Specific gallery (id > 0)
      $(".reacg-bp-images-section").removeClass("reacg-hidden");

      const isSameImagesLoaded =
        self._lastLoadedImagesGalleryId === self.currentGalleryId &&
        $("#reacg-gallery-images").children().length > 0;

      // Immediately mount React settings in canvas & panel so settings are instant!
      self.mountReactSettingsInCanvas(self.currentGalleryId, isOpening);

      if (isOpening && isSameImagesLoaded) {
        $(".reacg-bp-spinner-wrap").addClass("reacg-hidden");
        return;
      }

      $(".reacg-bp-spinner-wrap").removeClass("reacg-hidden");

      // Clear stale images from previous gallery immediately so old data is never shown
      $("#reacg-gallery-images").empty();

      const data = this.getData();
      let ajaxUrl =
        data.ajax_url || (typeof reacg !== "undefined" ? reacg.ajax_url : "");
      if (!ajaxUrl && typeof window.ajaxurl !== "undefined") {
        ajaxUrl = window.ajaxurl;
      }
      if (!ajaxUrl) {
        ajaxUrl =
          adminDoc.defaultView && adminDoc.defaultView.ajaxurl
            ? adminDoc.defaultView.ajaxurl
            : "/wp-admin/admin-ajax.php";
      }

      const separator = ajaxUrl.indexOf("?") === -1 ? "?" : "&";
      const nonceParam =
        ajaxUrl.indexOf("reacg_nonce") === -1 && data.nonce
          ? "&reacg_nonce=" + encodeURIComponent(data.nonce)
          : "";
      const fetchUrl =
        ajaxUrl +
        separator +
        "action=reacg_get_images&id=" +
        self.currentGalleryId +
        nonceParam;

      // 1. Fetch images HTML into #reacg-gallery-images
      fetch(fetchUrl)
        .then(function (res) {
          return res.text();
        })
        .then(function (text) {
          let html = text;
          try {
            const parsed = JSON.parse(text);
            if (typeof parsed === "string") {
              html = parsed;
            }
          } catch (e) {}

          const $container = $("#reacg-gallery-images");
          $container.html(html);

          const rawCont = $container.get(0);
          if (typeof reacg_make_items_sortable === "function") {
            reacg_make_items_sortable(rawCont);
          }
          if (typeof reacg_update_item_image_fit === "function") {
            $container.find(".reacg_item_image img").each(function () {
              reacg_update_item_image_fit(this);
            });
          }

          self._lastLoadedImagesGalleryId = self.currentGalleryId;
          $(".reacg-bp-spinner-wrap").addClass("reacg-hidden");
        })
        .catch(function (err) {
          $(".reacg-bp-spinner-wrap").addClass("reacg-hidden");
          console.error("Error loading gallery images:", err);
        });
    },

    mountReactSettingsInCanvas: function (galleryId, isOpening) {
      const canvasDoc = getCanvasDocument();
      const adminDoc = getAdminDocument();
      enableSettingsPortal(canvasDoc);
      syncGalleryStyles(canvasDoc);

      const data = this.getData();
      const version =
        data.version || (typeof reacg !== "undefined" ? reacg.version : "1.0");

      // Find specific placeholder and gallery container in canvas for this widget instance
      let placeholder = null;
      let galleryEl = null;

      if (this.currentWidgetId) {
        const safeWidgetId = String(this.currentWidgetId)
          .trim()
          .replace(/[^a-zA-Z0-9_-]/g, "");
        let widgetWrapper = null;
        try {
          if (safeWidgetId) {
            widgetWrapper =
              canvasDoc.querySelector(".elementor-element-" + safeWidgetId) ||
              canvasDoc.querySelector('[data-id="' + safeWidgetId + '"]') ||
              canvasDoc.querySelector(
                '.brxe-reacg[data-script-id="' + safeWidgetId + '"]',
              ) ||
              canvasDoc.querySelector(
                '.brxe-reacg[data-bricks-element-id="' + safeWidgetId + '"]',
              ) ||
              canvasDoc.querySelector("#brxe-" + safeWidgetId) ||
              canvasDoc.querySelector(
                '[data-bricks-element-id="' + safeWidgetId + '"]',
              );
          }
        } catch (e) {}

        if (widgetWrapper) {
          placeholder = widgetWrapper.classList.contains(
            "reacg-builder-placeholder",
          )
            ? widgetWrapper
            : widgetWrapper.querySelector(".reacg-builder-placeholder");
          galleryEl = widgetWrapper.querySelector(".reacg-gallery");
        }

        if (!placeholder && safeWidgetId) {
          try {
            placeholder = canvasDoc.querySelector(
              '.reacg-builder-placeholder[data-widget-id="' +
                safeWidgetId +
                '"]',
            );
          } catch (e) {}
        }

        if (!galleryEl && safeWidgetId) {
          try {
            galleryEl =
              canvasDoc.getElementById("reacg-root" + safeWidgetId) ||
              (widgetWrapper
                ? widgetWrapper.querySelector(".reacg-gallery")
                : null);
          } catch (e) {}
        }
      } else {
        placeholder = canvasDoc.querySelector(".reacg-builder-placeholder");
        galleryEl =
          canvasDoc.querySelector(
            '.reacg-gallery[data-gallery-id="' + galleryId + '"]',
          ) ||
          canvasDoc.querySelector("#reacg-root" + galleryId) ||
          canvasDoc.querySelector(".reacg-gallery");
      }

      // Ensure all other galleries on the canvas have options disabled so settings only connect to this active gallery
      const allGalleries = canvasDoc.querySelectorAll(".reacg-gallery");
      let loadApp = canvasDoc.getElementById("reacg-loadApp");
      if (!loadApp && canvasDoc !== adminDoc) {
        loadApp = adminDoc.getElementById("reacg-loadApp");
      }

      for (let i = 0; i < allGalleries.length; i++) {
        const gal = allGalleries[i];
        if (galleryEl && gal !== galleryEl) {
          if (gal.getAttribute("data-options-section") === "1") {
            gal.setAttribute("data-options-section", "0");
            if (loadApp && gal.id) {
              triggerLoadApp(loadApp, gal.id);
            }
          }
        }
      }

      if (galleryId === 0) {
        if (placeholder) {
          placeholder.classList.remove("reacg-hidden");
        }
        if (galleryEl) {
          galleryEl.classList.add("reacg-hidden");
          galleryEl.setAttribute("data-options-section", "0");
          galleryEl.setAttribute("data-gallery-id", "0");
          if (loadApp && galleryEl.id) {
            triggerLoadApp(loadApp, galleryEl.id);
          }
        }
        return;
      }

      if (placeholder) {
        placeholder.classList.add("reacg-hidden");
      }

      if (galleryEl) {
        const rootId = galleryEl.id;
        const currentMountedId = galleryEl.getAttribute("data-gallery-id");
        const settingsContainer =
          adminDoc.getElementById("reacg_settings") ||
          document.getElementById("reacg_settings");

        // Check if gallery is already rendered with options enabled
        const isSameWidgetAndSameGallery =
          (this.lastLoadedWidgetRootId === rootId ||
            !this.lastLoadedWidgetRootId) &&
          String(currentMountedId) === String(galleryId) &&
          galleryEl.getAttribute("data-options-section") === "1";
        const hasSettingsMounted =
          settingsContainer && settingsContainer.children.length > 0;

        // If already rendered for this exact widget and settings are in place, do not reload or flash!
        if (isOpening && isSameWidgetAndSameGallery && hasSettingsMounted) {
          this.lastLoadedWidgetRootId = rootId;
          galleryEl.classList.remove("reacg-hidden");
          return;
        }

        this.lastLoadedWidgetRootId = rootId;

        galleryEl.classList.remove("reacg-hidden");
        galleryEl.setAttribute("data-options-section", "1");
        galleryEl.setAttribute("data-options-container", "#reacg_settings");
        galleryEl.setAttribute("data-gallery-id", galleryId);
        galleryEl.setAttribute("data-plugin-version", version);

        // Only update timestamps if switching gallery or not yet mounted
        if (
          String(currentMountedId) !== String(galleryId) ||
          !hasSettingsMounted
        ) {
          const timestamp = Date.now();
          galleryEl.setAttribute("data-gallery-timestamp", timestamp);
          galleryEl.setAttribute("data-options-timestamp", timestamp);
        }

        const attempt = function (remaining) {
          if (!loadApp) {
            loadApp =
              canvasDoc.getElementById("reacg-loadApp") ||
              (canvasDoc !== adminDoc
                ? adminDoc.getElementById("reacg-loadApp")
                : null);
          }

          if (loadApp) {
            triggerLoadApp(loadApp, rootId);
            syncGalleryStyles(canvasDoc);
            return;
          }

          if (remaining > 0) {
            setTimeout(function () {
              attempt(remaining - 1);
            }, 250);
          }
        };

        attempt(20);
      }
    },

    updateSaveStatus: function (status) {
      const $status = $(".reacg-bp-save-status");
      if (!$status.length) return;

      if (status === "saving") {
        if (this._saveStatusTimeout) {
          clearTimeout(this._saveStatusTimeout);
          this._saveStatusTimeout = null;
        }
        const savingText = this.getData().text_saving || "Saving...";
        $status
          .removeClass("reacg-hidden reacg-status-saved")
          .addClass("reacg-status-saving")
          .html(
            '<span class="spinner is-active" style="margin:0 4px 0 0;"></span>' +
              savingText,
          );
      } else if (status === "saved") {
        const savedText = this.getData().text_saved || "Saved";
        $status
          .removeClass("reacg-status-saving")
          .addClass("reacg-status-saved")
          .html(
            '<span class="dashicons dashicons-yes" style="vertical-align:middle;"></span>' +
              savedText,
          );

        if (this._saveStatusTimeout) {
          clearTimeout(this._saveStatusTimeout);
        }
        this._saveStatusTimeout = setTimeout(function () {
          $status.fadeOut(300, function () {
            $(this).addClass("reacg-hidden").css("display", "");
          });
        }, 2000);
      } else {
        $status.addClass("reacg-hidden").empty();
      }
    },

    scheduleAutoSave: function () {
      const self = this;
      self.pendingSave = true;
      self.updateSaveStatus("saving");

      if (self.autoSaveTimer) {
        clearTimeout(self.autoSaveTimer);
      }

      self.autoSaveTimer = setTimeout(function () {
        self.autoSaveTimer = null;
        self.triggerReactSave();
      }, 600);
    },

    flushAutoSave: function () {
      if (this.autoSaveTimer) {
        clearTimeout(this.autoSaveTimer);
        this.autoSaveTimer = null;
      }
      if (this.pendingSave) {
        this.triggerReactSave();
      }
    },

    triggerReactSave: function () {
      this.pendingSave = false;
      const adminDoc = getAdminDocument();
      const canvasDoc = getCanvasDocument();
      const settingsEl =
        adminDoc.getElementById("reacg_settings") ||
        document.getElementById("reacg_settings") ||
        (canvasDoc ? canvasDoc.getElementById("reacg_settings") : null);

      if (settingsEl) {
        const reactSave = settingsEl.querySelector(".save-settings-button");
        if (reactSave) {
          reactSave.click();
          return true;
        }
      }

      // Fallback: direct REST POST if React button wasn't found
      if (latestOptions && latestGalleryId) {
        this.saveOptionsDirect(latestGalleryId, latestOptions);
        return true;
      }

      return false;
    },

    saveOptionsDirect: function (galleryId, options) {
      const self = this;
      const data = self.getData();
      let restUrl = "";
      if (window.wpApiSettings && window.wpApiSettings.root) {
        restUrl =
          window.wpApiSettings.root + "regallery/v1/options/" + galleryId;
      } else {
        restUrl = "/wp-json/regallery/v1/options/" + galleryId;
      }
      const nonce =
        data.rest_nonce ||
        (window.wpApiSettings && window.wpApiSettings.nonce) ||
        "";

      fetch(restUrl, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": nonce,
        },
        body: JSON.stringify(options),
      })
        .then(function (res) {
          return res.json();
        })
        .then(function () {
          self.updateSaveStatus("saved");
        })
        .catch(function (err) {
          console.error("ReGallery auto-save error:", err);
        });
    },

    saveSettings: function () {
      this.flushAutoSave();

      const adminDoc = getAdminDocument();
      const canvasDoc = getCanvasDocument();
      const imagesEl =
        adminDoc.getElementById("reacg-gallery-images") ||
        document.getElementById("reacg-gallery-images") ||
        (canvasDoc ? canvasDoc.getElementById("reacg-gallery-images") : null);
      if (imagesEl) {
        const $mediaContainer = $(imagesEl).find(".reacg_items");
        const saveImagesFn =
          typeof reacg_save_images === "function"
            ? reacg_save_images
            : adminDoc.defaultView &&
                typeof adminDoc.defaultView.reacg_save_images === "function"
              ? adminDoc.defaultView.reacg_save_images
              : window.top && typeof window.top.reacg_save_images === "function"
                ? window.top.reacg_save_images
                : null;

        if ($mediaContainer.length && typeof saveImagesFn === "function") {
          saveImagesFn($mediaContainer);
        }
      }

      if (typeof this.onSaveCallback === "function" && this.currentGalleryId) {
        this.onSaveCallback(this.currentGalleryId);
      }
    },

    open: function (options) {
      options = options || {};
      ensureDashicons(document);
      if (
        window.top &&
        window.top.document &&
        window.top.document !== document
      ) {
        ensureDashicons(window.top.document);
      }
      this.init();

      this.currentWidgetId =
        options.widgetId !== undefined && options.widgetId !== null
          ? String(options.widgetId)
          : "";
      this.onSaveCallback = options.onSave || null;
      this.onCloseCallback = options.onClose || null;

      let targetGalleryId =
        typeof options.galleryId !== "undefined"
          ? parseInt(options.galleryId, 10)
          : 0;
      if (isNaN(targetGalleryId)) {
        targetGalleryId = 0;
      }

      this.populateGalleriesDropdown(targetGalleryId);

      $("#reacg-builder-panel-backdrop").removeClass("reacg-hidden");
      this.isOpen = true;

      this.loadGallery(targetGalleryId, true);
    },

    close: function () {
      this.flushAutoSave();

      $("#reacg-builder-panel-backdrop").addClass("reacg-hidden");
      $(".reacg-bp-create-form").addClass("reacg-hidden");
      this.isOpen = false;

      if (typeof this.onSaveCallback === "function" && this.currentGalleryId) {
        this.onSaveCallback(this.currentGalleryId);
      }

      if (typeof this.onCloseCallback === "function") {
        this.onCloseCallback();
      }
    },
  };

  try {
    if (window.top && window.top !== window) {
      if (window.top.ReacgBuilderModal) {
        window.ReacgBuilderModal = window.top.ReacgBuilderModal;
      } else {
        window.top.ReacgBuilderModal = ReacgBuilderModal;
        window.ReacgBuilderModal = ReacgBuilderModal;
      }
    } else {
      window.ReacgBuilderModal = ReacgBuilderModal;
    }
  } catch (e) {
    window.ReacgBuilderModal = ReacgBuilderModal;
  }

  $(document).ready(function () {
    ensureDashicons(document);
    if (window.top && window.top.document && window.top.document !== document) {
      ensureDashicons(window.top.document);
    }
    ReacgBuilderModal.init();
  });
})(jQuery);
