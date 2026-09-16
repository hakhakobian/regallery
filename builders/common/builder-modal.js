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
    try {
      if (
        window.top &&
        window.top.document &&
        window.top.document.getElementById("reacg_settings")
      ) {
        return window.top.document;
      }
    } catch (e) {}

    try {
      if (
        window.parent &&
        window.parent.document &&
        window.parent.document.getElementById("reacg_settings")
      ) {
        return window.parent.document;
      }
    } catch (e) {}

    if (document.getElementById("reacg_settings")) {
      return document;
    }

    try {
      if (
        window.top &&
        window.top.document &&
        window.top.document.documentElement
      ) {
        return window.top.document;
      }
    } catch (e) {}

    try {
      if (
        window.parent &&
        window.parent.document &&
        window.parent.document.documentElement
      ) {
        return window.parent.document;
      }
    } catch (e) {}

    return document;
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
    const topWin = window.top || window;
    const topDoc = (topWin && topWin.document) || document;
    let doc = document;

    // 1. If current document already has .reacg-gallery or placeholder, it is already the canvas
    if (
      document.querySelector(".reacg-gallery") ||
      document.querySelector(".reacg-builder-placeholder")
    ) {
      ensureDashicons(document);
      return document;
    }

    // 2. Beaver Builder Responsive iFrame UI API
    try {
      const FLBuilder = topWin.FLBuilder || window.FLBuilder;
      if (
        FLBuilder &&
        FLBuilder.UIIFrame &&
        typeof FLBuilder.UIIFrame.getIFrameWindow === "function"
      ) {
        const bbWin = FLBuilder.UIIFrame.getIFrameWindow();
        if (bbWin && bbWin.document && bbWin.document.body) {
          doc = bbWin.document;
          ensureDashicons(doc);
          return doc;
        }
      }
    } catch (e) {}

    // 3. Known page builder preview iframes
    const iframeSelectors = [
      "#elementor-preview-iframe",
      "#bricks-builder-iframe",
      'iframe[name="editor-canvas"]',
      "iframe.fl-builder-ui-iframe",
      ".fl-builder-frame iframe",
      "iframe[src*='fl_builder']",
    ];

    for (let i = 0; i < iframeSelectors.length; i++) {
      try {
        const frame =
          topDoc.querySelector(iframeSelectors[i]) ||
          document.querySelector(iframeSelectors[i]);
        if (frame && frame.contentDocument && frame.contentDocument.body) {
          doc = frame.contentDocument;
          ensureDashicons(doc);
          return doc;
        }
      } catch (e) {}
    }

    // 4. Heuristic: Check any iframe for .reacg-gallery, .reacg-builder-placeholder, or builder modules
    try {
      const iframes = topDoc.querySelectorAll("iframe");
      for (let j = 0; j < iframes.length; j++) {
        try {
          const frameDoc = iframes[j].contentDocument;
          if (frameDoc && frameDoc.body) {
            if (
              frameDoc.querySelector(".reacg-gallery") ||
              frameDoc.querySelector(".reacg-builder-placeholder") ||
              frameDoc.querySelector(".fl-module") ||
              frameDoc.querySelector(".fl-builder-content")
            ) {
              doc = frameDoc;
              break;
            }
          }
        } catch (err) {}
      }
    } catch (e) {}

    ensureDashicons(doc);
    return doc;
  }

  /**
   * Find the document that owns a specific gallery instance. Some builders
   * keep their control UI and canvas in separate iframes; selecting a gallery
   * by its instance root avoids falling back to another copy of that gallery.
   *
   * @param {string} rootId
   * @param {Document} fallbackDoc
   * @returns {Document}
   */
  function getCanvasDocumentForRoot(rootId, fallbackDoc) {
    if (!rootId) {
      return fallbackDoc || getCanvasDocument();
    }

    const documents = [];
    const addDocument = function (candidate) {
      if (candidate && documents.indexOf(candidate) === -1) {
        documents.push(candidate);
      }
    };

    addDocument(fallbackDoc);
    addDocument(document);

    try {
      addDocument(window.top && window.top.document);
    } catch (e) {}

    try {
      const topWin = window.top || window;
      const FLBuilder = topWin.FLBuilder || window.FLBuilder;
      if (
        FLBuilder &&
        FLBuilder.UIIFrame &&
        typeof FLBuilder.UIIFrame.getIFrameWindow === "function"
      ) {
        const builderWin = FLBuilder.UIIFrame.getIFrameWindow();
        addDocument(builderWin && builderWin.document);
      }
    } catch (e) {}

    try {
      const topDoc = (window.top && window.top.document) || document;
      const frames = topDoc.querySelectorAll("iframe");
      for (let i = 0; i < frames.length; i++) {
        addDocument(frames[i].contentDocument);
      }
    } catch (e) {}

    for (let i = 0; i < documents.length; i++) {
      try {
        if (documents[i].getElementById(rootId)) {
          ensureDashicons(documents[i]);
          return documents[i];
        }
      } catch (e) {}
    }

    return fallbackDoc || getCanvasDocument();
  }

  /**
   * Bridge admin helper and dialog APIs bi-directionally between admin and canvas iframe window.
   *
   * @param {Document} canvasDoc
   */
  function bridgeAdminApis(canvasDoc) {
    const canvasWin = canvasDoc && canvasDoc.defaultView;
    const adminDoc = getAdminDocument();
    const adminWin = (adminDoc && adminDoc.defaultView) || window;

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

    const windows = [];
    const addWin = function (w) {
      try {
        if (w && windows.indexOf(w) === -1) {
          windows.push(w);
        }
      } catch (e) {}
    };
    addWin(window);
    addWin(adminWin);
    addWin(canvasWin);
    try {
      addWin(window.top);
    } catch (e) {}
    try {
      addWin(window.parent);
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
            const allWins = [];
            windows.forEach(function (w) {
              if (w && allWins.indexOf(w) === -1) allWins.push(w);
            });
            if (currentWin && allWins.indexOf(currentWin) === -1) {
              allWins.push(currentWin);
            }

            let callArgs = args;
            if (name === "reacg_open_free_trial_layout_dialog") {
              let first = args[0];
              if (typeof first === "string") {
                first = {
                  utm_medium: "free_trial_layout_" + first,
                  showFreeTrialForm: true,
                  buttonConfig: {
                    label: "START FREE TRIAL",
                    backgroundColor: "#8769ff",
                    width: "100%",
                    onClick: function () {},
                  },
                };
                callArgs = [first];
              } else if (
                first &&
                typeof first === "object" &&
                !first.buttonConfig
              ) {
                first.buttonConfig = {
                  label: "START FREE TRIAL",
                  backgroundColor: "#8769ff",
                  width: "100%",
                  onClick: function () {},
                };
                callArgs = [first];
              }
            }

            for (let i = 0; i < allWins.length; i++) {
              const w = allWins[i];
              if (
                w &&
                typeof w[name] === "function" &&
                !w[name].__reacgBridgeProxy
              ) {
                return w[name].apply(w, callArgs);
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
              const wins = [];
              windows.forEach(function (w) {
                if (w && wins.indexOf(w) === -1) wins.push(w);
              });
              if (dynWin && wins.indexOf(dynWin) === -1) {
                wins.push(dynWin);
              }

              for (let i = 0; i < wins.length; i++) {
                const w = wins[i];
                if (
                  w &&
                  typeof w[name] === "function" &&
                  !w[name].__reacgBridgeProxy
                ) {
                  clearInterval(timer);
                  return w[name].apply(w, callArgs);
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

        if (name === "reacg_open_free_trial_layout_dialog") {
          const rawImpl = implementation;
          implementation = function (configOrLayout) {
            let config = configOrLayout;
            if (typeof config === "string") {
              config = {
                utm_medium: "free_trial_layout_" + config,
                showFreeTrialForm: true,
                buttonConfig: {
                  label: "START FREE TRIAL",
                  backgroundColor: "#8769ff",
                  width: "100%",
                  onClick: function () {},
                },
              };
            } else if (!config || typeof config !== "object") {
              config = {
                utm_medium: "free_trial_layout",
                showFreeTrialForm: true,
                buttonConfig: {
                  label: "START FREE TRIAL",
                  backgroundColor: "#8769ff",
                  width: "100%",
                  onClick: function () {},
                },
              };
            } else if (!config.buttonConfig) {
              config.buttonConfig = {
                label: "START FREE TRIAL",
                backgroundColor: "#8769ff",
                width: "100%",
                onClick: function () {},
              };
            }
            let offerFn = null;
            for (let j = 0; j < windows.length; j++) {
              const w = windows[j];
              if (
                w &&
                typeof w.reacg_open_free_trial_offer_dialog === "function" &&
                !w.reacg_open_free_trial_offer_dialog.__reacgBridgeProxy
              ) {
                offerFn = w.reacg_open_free_trial_offer_dialog;
                break;
              }
            }
            if (offerFn) {
              return offerFn(config);
            }
            return rawImpl(config);
          };
        }

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

    if (canvasWin && canvasWin !== window) {
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
      if (!canvasWin.__reacgOnOptionsChangeBridged) {
        canvasWin.__reacgOnOptionsChangeBridged = true;
        const origCanvasOnOptionsChange =
          canvasWin.reacg_global.onOptionsChange;
        canvasWin.reacg_global.onOptionsChange = function (options, context) {
          if (
            typeof origCanvasOnOptionsChange === "function" &&
            origCanvasOnOptionsChange !== canvasWin.reacg_global.onOptionsChange
          ) {
            origCanvasOnOptionsChange(options, context);
          }
          if (
            window.reacg_global &&
            window.reacg_global !== canvasWin.reacg_global &&
            typeof window.reacg_global.onOptionsChange === "function"
          ) {
            window.reacg_global.onOptionsChange(options, context);
          }
        };
      }
    }
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
  let isHandlingOptionsChange = false;
  window.reacg_global.onOptionsChange = function (options, context) {
    if (isHandlingOptionsChange) return;
    isHandlingOptionsChange = true;
    try {
      if (
        typeof prevAdminOnOptionsChange === "function" &&
        prevAdminOnOptionsChange !== window.reacg_global.onOptionsChange
      ) {
        prevAdminOnOptionsChange(options, context);
      }

      const canvasDoc = getCanvasDocument();
      syncGalleryStyles(canvasDoc);

      latestOptions = options;
      // The floating panel edits one gallery at a time. React can emit an
      // options event from a previously mounted root during a builder redraw,
      // so the active modal gallery must take precedence over that stale event.
      const currentGalId =
        (window.ReacgBuilderModal &&
          window.ReacgBuilderModal.currentGalleryId) ||
        (context && context.galleryId);
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
    } finally {
      isHandlingOptionsChange = false;
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
        id.indexOf("bricks") === 0 ||
        id.indexOf("fl-") === 0
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

    const canvasWin = canvasDoc.defaultView;
    const prototypes = [];
    if (canvasWin && canvasWin.Document) {
      prototypes.push(canvasWin.Document.prototype);
    }
    if (canvasWin && canvasWin.HTMLDocument) {
      prototypes.push(canvasWin.HTMLDocument.prototype);
    }

    let nativeBodyGetter = null;
    for (let i = 0; i < prototypes.length; i++) {
      const descriptor = Object.getOwnPropertyDescriptor(prototypes[i], "body");
      if (descriptor && descriptor.get) {
        nativeBodyGetter = descriptor.get;
        break;
      }
    }
    if (!nativeBodyGetter) {
      return;
    }

    canvasDoc.__reacgOverlayPortal = true;
    // While builder settings are mounted, all React portals from the gallery
    // must use the parent document. Lazy chunks can create a dialog well after
    // the originating click, so a short time window is not reliable here.
    canvasWin.__reacgPortalToAdmin = true;
    canvasWin.__reacgOpenOverlayInAdmin = function () {
      canvasWin.__reacgPortalToAdmin = true;
    };

    Object.defineProperty(canvasDoc, "body", {
      configurable: true,
      enumerable: true,
      get: function () {
        if (canvasWin.__reacgPortalToAdmin && adminDoc.body) {
          return adminDoc.body;
        }
        return nativeBodyGetter.call(canvasDoc);
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
      className.indexOf("MuiMenu-root") !== -1 ||
      className.indexOf("reacg-settings-panel-tabs__menu") !== -1 ||
      !!(
        node.querySelector &&
        node.querySelector(".reacg-settings-panel-tabs__menu")
      )
    );
  }

  // Captured before React creates a portalled menu, so its first placement can
  // use the real trigger instead of Popper's temporary (0, 0) origin.
  let lastSettingsMenuTrigger = null;

  function getExpandedSettingsAnchor(adminDoc) {
    if (lastSettingsMenuTrigger && lastSettingsMenuTrigger.isConnected) {
      const triggerRect = lastSettingsMenuTrigger.getBoundingClientRect();
      if (triggerRect.width && triggerRect.height) {
        return lastSettingsMenuTrigger;
      }
    }
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

  function isMoreMenuTrigger(anchor) {
    if (!anchor) return false;
    return [
      anchor.getAttribute("aria-label"),
      anchor.getAttribute("title"),
      anchor.textContent,
    ]
      .join(" ")
      .toLowerCase()
      .indexOf("more") !== -1;
  }

  function repositionMuiTooltip(node, canvasDoc) {
    const adminDoc = getAdminDocument();
    const adminWin = adminDoc.defaultView;
    if (!node || !canvasDoc || !adminWin) return;

    const tooltip =
      node.getAttribute("role") === "tooltip"
        ? node
        : node.querySelector('[role="tooltip"], .MuiTooltip-tooltip');
    const tooltipId = tooltip && tooltip.id;
    if (!tooltipId) return;

    let anchor = null;
    try {
      anchor = canvasDoc.querySelector(
        '[aria-describedby~="' + CSS.escape(tooltipId) + '"]',
      );
    } catch (e) {}
    if (!anchor) return;

    let frameRect = { left: 0, top: 0 };
    try {
      const canvasWin = canvasDoc.defaultView;
      const frames = adminDoc.querySelectorAll("iframe");
      for (let i = 0; i < frames.length; i++) {
        if (frames[i].contentWindow === canvasWin) {
          frameRect = frames[i].getBoundingClientRect();
          break;
        }
      }
    } catch (e) {}

    const anchorRect = anchor.getBoundingClientRect();
    const placement = String(
      node.getAttribute("data-popper-placement") || "bottom",
    );
    const left = frameRect.left + anchorRect.left + anchorRect.width / 2;
    const top =
      frameRect.top +
      (placement.indexOf("top") === 0
        ? anchorRect.top - 8
        : anchorRect.bottom + 8);

    // Popper calculated its transform in iframe coordinates. Once portalled
    // to the admin document it must use parent-document coordinates instead.
    node.style.setProperty("position", "fixed", "important");
    node.style.setProperty("left", left + "px", "important");
    node.style.setProperty("top", top + "px", "important");
    node.style.setProperty("transform", "translateX(-50%)", "important");
    node.style.setProperty("z-index", "1000003", "important");
    node.style.setProperty("pointer-events", "none", "important");
  }

  function repositionMuiOverlay(node, canvasDoc) {
    if (!node || isMuiDialog(node) || isMuiTooltip(node)) return;
    const adminDoc = getAdminDocument();
    const canvasWin = canvasDoc.defaultView;
    const adminWin = adminDoc.defaultView;

    node.style.setProperty("z-index", "1000003", "important");
    // A portalled MUI menu is inserted at its fallback edge coordinate before
    // Popper and the builder bridge calculate its anchor. Hide that first
    // paint; the node is revealed only once a real position is applied.
    node.style.setProperty("visibility", "hidden", "important");

    const apply = function () {
      if (isMuiDialog(node)) return false;
      syncGalleryStyles(canvasDoc);
      try {
        if (canvasWin) canvasWin.dispatchEvent(new Event("resize"));
        if (adminWin) adminWin.dispatchEvent(new Event("resize"));
      } catch (e) {}

      const anchor = getExpandedSettingsAnchor(adminDoc);
      if (!anchor || !adminWin) {
        return false;
      }

      const positioned =
        node.querySelector("[data-popper-placement]") ||
        node.querySelector(".MuiPaper-root") ||
        node.firstElementChild;
      if (
        !positioned ||
        (positioned.className &&
          positioned.className.toString().indexOf("MuiBackdrop-root") !== -1)
      ) {
        return false;
      }
      if (isMuiDialog(positioned)) return false;

      const rect = anchor.getBoundingClientRect();
      const viewportWidth =
        adminWin.innerWidth || adminDoc.documentElement.clientWidth;
      let left = rect.left;
      if (left > viewportWidth - 8) {
        left = Math.max(8, rect.right - 8);
      }
      const top = rect.bottom + 4;

      node.style.setProperty("z-index", "1000003", "important");
      positioned.style.setProperty("z-index", "1000003", "important");
      positioned.style.setProperty("position", "fixed", "important");
      positioned.style.setProperty("top", top + "px", "important");
      positioned.style.setProperty("left", left + "px", "important");
      positioned.style.setProperty("transform", "none", "important");
      positioned.style.setProperty("margin", "0", "important");
      positioned.style.setProperty("right", "auto", "important");
      if (isMoreMenuTrigger(anchor)) {
        // Let More size itself to its labels rather than the narrow trigger.
        positioned.style.removeProperty("min-width");
      } else {
        // Layout selects need a roomier list than their field, but must not
        // force the field itself to resize while the menu is open.
        const selectRect = getOverlayWidthSource(anchor).getBoundingClientRect();
        const menuWidth = Math.max(Math.ceil(selectRect.width) + 32, 240);
        positioned.style.setProperty(
          "min-width",
          menuWidth + "px",
          "important",
        );
      }
      positioned.style.removeProperty("width");
      positioned.style.setProperty("box-sizing", "border-box", "important");
      node.style.setProperty("visibility", "visible", "important");
      return true;
    };

    apply();
    requestAnimationFrame(function () {
      apply();
      requestAnimationFrame(function () {
        apply();
        adminDoc.documentElement.classList.remove("reacg-settings-menu-opening");
      });
    });
    // Keep a safe fallback for menus that do not have an anchor (for example,
    // a builder's own context menu) so they never remain hidden.
    window.setTimeout(function () {
      if (node.isConnected && node.style.visibility === "hidden") {
        node.style.setProperty("visibility", "visible", "important");
      }
    }, 250);
  }

  function enableMuiPopoverFix(canvasDoc) {
    const adminDoc = getAdminDocument();
    if (!canvasDoc) {
      return;
    }
    if (canvasDoc.__reacgMuiPopoverFix && adminDoc.__reacgMuiPopoverFix) {
      return;
    }
    canvasDoc.__reacgMuiPopoverFix = true;
    adminDoc.__reacgMuiPopoverFix = true;

    const captureSettingsMenuTrigger = function (event) {
      const target = event.target;
      if (!target || !target.closest) return;
      const trigger = target.closest(
        "#reacg_settings button, #reacg_settings [role='button'], #reacg_settings [aria-haspopup]",
      );
      if (trigger) {
        lastSettingsMenuTrigger = trigger;
        adminDoc.documentElement.classList.add("reacg-settings-menu-opening");
        window.setTimeout(function () {
          adminDoc.documentElement.classList.remove(
            "reacg-settings-menu-opening",
          );
        }, 500);
      }
    };
    adminDoc.addEventListener("pointerdown", captureSettingsMenuTrigger, true);
    if (canvasDoc !== adminDoc) {
      canvasDoc.addEventListener(
        "pointerdown",
        captureSettingsMenuTrigger,
        true,
      );
    }

    const watchNode = function (node) {
      if (!node || node.nodeType !== 1) return;
      if (isMuiTooltip(node) || isMuiDialog(node)) {
        if (isMuiTooltip(node)) {
          repositionMuiTooltip(node, canvasDoc);
        }
        if (isMuiDialog(node)) {
          // React owns this portal in the canvas document. Moving the node to
          // the builder/admin document detaches it from React's reconciliation
          // tree, which leaves the Pro dialog visible but impossible to close.
          if (node.style) {
            node.style.setProperty("z-index", "10000000", "important");
            node.style.setProperty("position", "fixed", "important");
            node.style.setProperty("inset", "0", "important");
            node.style.setProperty("display", "block", "important");
          }
        }
        requestAnimationFrame(function () {
          if (isMuiTooltip(node)) {
            repositionMuiTooltip(node, canvasDoc);
          }
        });
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
          if (isMuiTooltip(node)) {
            repositionMuiTooltip(node, canvasDoc);
          }
          if (isMuiDialog(node)) {
            if (node.style) {
              node.style.setProperty("z-index", "10000000", "important");
              node.style.setProperty("position", "fixed", "important");
              node.style.setProperty("inset", "0", "important");
              node.style.setProperty("display", "block", "important");
            }
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

    if (canvasDoc.body) observeBody(canvasDoc.body);
    if (adminDoc.body) observeBody(adminDoc.body);
  }

  function enableBuilderProControls(canvasDoc) {
    const adminDoc = getAdminDocument();
    const docs = [canvasDoc];
    if (adminDoc && docs.indexOf(adminDoc) === -1) {
      docs.push(adminDoc);
    }

    const wins = [];
    const addWin = function (w) {
      try {
        if (w && wins.indexOf(w) === -1) wins.push(w);
      } catch (e) {}
    };
    addWin(window);
    if (adminDoc) addWin(adminDoc.defaultView);
    if (canvasDoc) addWin(canvasDoc.defaultView);
    try {
      addWin(window.top);
    } catch (e) {}
    try {
      addWin(window.parent);
    } catch (e) {}

    // Defensively wrap layout dialog on all windows to prevent missing buttonConfig crashes
    wins.forEach(function (w) {
      if (!w) return;
      const original = w.reacg_open_free_trial_layout_dialog;
      if (original && original.__reacgSafeWrapped) return;

      const safeWrapper = function (configOrLayout) {
        let config = configOrLayout;
        if (typeof config === "string") {
          config = {
            utm_medium: "free_trial_layout_" + config,
            showFreeTrialForm: true,
            buttonConfig: {
              label: "START FREE TRIAL",
              backgroundColor: "#8769ff",
              width: "100%",
              onClick: function () {},
            },
          };
        } else if (!config || typeof config !== "object") {
          config = {
            utm_medium: "free_trial_layout",
            showFreeTrialForm: true,
            buttonConfig: {
              label: "START FREE TRIAL",
              backgroundColor: "#8769ff",
              width: "100%",
              onClick: function () {},
            },
          };
        } else if (!config.buttonConfig) {
          config.buttonConfig = {
            label: "START FREE TRIAL",
            backgroundColor: "#8769ff",
            width: "100%",
            onClick: function () {},
          };
        }

        if (
          typeof w.reacg_open_free_trial_offer_dialog === "function" &&
          !w.reacg_open_free_trial_offer_dialog.__reacgBridgeProxy
        ) {
          return w.reacg_open_free_trial_offer_dialog(config);
        }
        if (
          typeof original === "function" &&
          original !== safeWrapper &&
          !original.__reacgBridgeProxy
        ) {
          return original.call(w, config);
        }
        if (typeof w.reacg_open_free_trial_offer_dialog === "function") {
          return w.reacg_open_free_trial_offer_dialog(config);
        }
      };
      safeWrapper.__reacgSafeWrapped = true;
      w.reacg_open_free_trial_layout_dialog = safeWrapper;
    });

    const triggerProOffer = function (utmMedium, layoutType) {
      try {
        const canvasWin = canvasDoc && canvasDoc.defaultView;
        if (
          canvasWin &&
          typeof canvasWin.__reacgOpenOverlayInAdmin === "function"
        ) {
          canvasWin.__reacgOpenOverlayInAdmin();
        }
      } catch (e) {}
      // Re-collect active windows
      const activeWins = [];
      const addActiveWin = function (w) {
        try {
          if (w && activeWins.indexOf(w) === -1) activeWins.push(w);
        } catch (e) {}
      };
      addActiveWin(window);
      if (adminDoc) addActiveWin(adminDoc.defaultView);
      if (canvasDoc) addActiveWin(canvasDoc.defaultView);
      try {
        addActiveWin(window.top);
      } catch (e) {}
      try {
        addActiveWin(window.parent);
      } catch (e) {}

      // Close open MUI dropdown menus (like "More" menu or select popover)
      try {
        docs.forEach(function (d) {
          if (!d) return;
          const popovers = d.querySelectorAll(
            ".MuiPopover-root, .MuiMenu-root",
          );
          popovers.forEach(function (popover) {
            const backdrop = popover.querySelector(".MuiBackdrop-root");
            if (backdrop) {
              backdrop.click();
            }
          });
        });
      } catch (e) {}

      const effectiveMedium =
        utmMedium ||
        (layoutType ? "free_trial_layout_" + layoutType : "builder");
      const config = {
        utm_medium: effectiveMedium,
        showFreeTrialForm: true,
        buttonConfig: {
          label: "START FREE TRIAL",
          backgroundColor: "#8769ff",
          width: "100%",
          onClick: function () {},
        },
      };

      // Prioritize free_trial_offer_dialog since it is completely crash-proof and has full layout
      const fnNames = [
        "reacg_open_free_trial_offer_dialog",
        "reacg_open_free_trial_layout_dialog",
        "reacg_open_premium_offer_dialog",
        "reacg_open_pro_layout_dialog",
      ];

      for (let f = 0; f < fnNames.length; f++) {
        const name = fnNames[f];
        for (let w = 0; w < activeWins.length; w++) {
          const win = activeWins[w];
          if (
            win &&
            typeof win[name] === "function" &&
            !win[name].__reacgBridgeProxy
          ) {
            try {
              win[name](config);
              return;
            } catch (err) {
              console.error("Error opening pro offer dialog:", err);
            }
          }
        }
      }

      // If not yet available without proxy, try triggering reacg-loadApp to request chunk 753
      try {
        let loadApp =
          (adminDoc && adminDoc.getElementById("reacg-loadApp")) ||
          document.getElementById("reacg-loadApp") ||
          (canvasDoc && canvasDoc.getElementById("reacg-loadApp"));
        if (loadApp) {
          loadApp.click();
        }
      } catch (e) {}

      // Retry every 80ms for up to 3.5 seconds
      let retryCount = 0;
      const retryTimer = setInterval(function () {
        retryCount++;
        for (let f = 0; f < fnNames.length; f++) {
          const name = fnNames[f];
          for (let w = 0; w < activeWins.length; w++) {
            const win = activeWins[w];
            if (
              win &&
              typeof win[name] === "function" &&
              !win[name].__reacgBridgeProxy
            ) {
              clearInterval(retryTimer);
              try {
                win[name](config);
              } catch (e) {}
              return;
            }
          }
        }
        if (retryCount > 40) {
          clearInterval(retryTimer);
          // Fallback to proxy
          for (let f = 0; f < fnNames.length; f++) {
            const name = fnNames[f];
            for (let w = 0; w < activeWins.length; w++) {
              const win = activeWins[w];
              if (win && typeof win[name] === "function") {
                try {
                  win[name](config);
                  return;
                } catch (e) {}
              }
            }
          }
        }
      }, 80);
    };

    const getProLayoutType = function (title) {
      const normalized = String(title || "").toLowerCase();
      const layouts = [
        ["coverflow", "depthflow"],
        ["justified", "smart rows"],
        ["cards", "spotlight"],
        ["scroller", "active drift"],
      ];
      for (let i = 0; i < layouts.length; i++) {
        if (
          normalized.indexOf(layouts[i][0]) !== -1 ||
          normalized.indexOf(layouts[i][1]) !== -1
        ) {
          return layouts[i][0];
        }
      }
      return "pro";
    };

    const bind = function (doc) {
      if (!doc || !doc.body) return;

      // Locked layout cards in gallery tab.
      doc
        .querySelectorAll(".type-option__locked, .type-option")
        .forEach(function (card) {
          const titleEl = card.querySelector(".type-option__title");
          const title = titleEl
            ? titleEl.textContent.trim().toLowerCase()
            : (card.textContent || "").trim().toLowerCase();
          const isProLayout =
            card.classList.contains("type-option__locked") ||
            !!card.querySelector(".type-option__pro-badge") ||
            getProLayoutType(title) !== "pro";
          if (!isProLayout) return;

          if (card.__reacgProCardBound) return;
          card.__reacgProCardBound = true;
          card.addEventListener(
            "click",
            function (event) {
              const val = getProLayoutType(title);
              event.preventDefault();
              event.stopPropagation();
              triggerProOffer("layout_" + val, val);
            },
            true,
          );
        });

      // 4. Pro badges / options in layout dropdown / select menu
      doc
        .querySelectorAll(
          ".type-panel-select__pro-badge, .type-panel-select__body, .MuiMenuItem-root, [role='option']",
        )
        .forEach(function (el) {
          const item = el.closest(".MuiMenuItem-root, [role='option']") || el;
          if (!item || item.__reacgProItemBound) return;
          const titleEl = item.querySelector(".type-panel-select__title");
          const title = titleEl
            ? titleEl.textContent.trim().toLowerCase()
            : (item.textContent || "").trim().toLowerCase();
          const isProLayout =
            getProLayoutType(title) !== "pro" ||
            !!item.querySelector(".type-panel-select__pro-badge");
          if (!isProLayout) return;

          item.__reacgProItemBound = true;
          item.classList.remove("Mui-disabled");
          item.removeAttribute("disabled");
          item.removeAttribute("aria-disabled");
          item.addEventListener(
            "click",
            function (event) {
              const val = getProLayoutType(title);
              event.preventDefault();
              event.stopPropagation();
              triggerProOffer("layout_" + val, val);
            },
            true,
          );
        });

      // 5. Direct pro badges anywhere in settings
      doc
        .querySelectorAll(
          ".type-panel-select__pro-badge, .type-option__pro-badge, .reacg-pro-badge",
        )
        .forEach(function (badge) {
          if (badge.__reacgBadgeBound) return;
          badge.__reacgBadgeBound = true;
          badge.style.pointerEvents = "auto";
          badge.style.cursor = "pointer";
          badge.addEventListener(
            "click",
            function (event) {
              event.preventDefault();
              event.stopPropagation();
              triggerProOffer("badge");
            },
            true,
          );
        });
    };

    // 6. Global delegated capture click interceptor for instant, reliable response
    const attachGlobalProClickInterceptor = function (doc) {
      if (!doc || doc.__reacgProGlobalClickAttached) return;
      doc.__reacgProGlobalClickAttached = true;

      doc.addEventListener(
        "click",
        function (event) {
          const target = event.target;
          if (!target) return;

          // Layout options in dropdown select menu or card grid.
          const proLayoutItem = target.closest(
            ".type-option__locked, .type-option, .MuiMenuItem-root, [role='option'], .type-panel-select__body",
          );
          if (proLayoutItem) {
            const isProBadge =
              proLayoutItem.querySelector(
                ".type-option__pro-badge, .type-panel-select__pro-badge",
              ) ||
              target.closest(
                ".type-option__pro-badge, .type-panel-select__pro-badge",
              );
            const isLocked = proLayoutItem.classList.contains(
              "type-option__locked",
            );
            const titleEl = proLayoutItem.querySelector(
              ".type-option__title, .type-panel-select__title",
            );
            const title = titleEl
              ? titleEl.textContent.trim().toLowerCase()
              : (proLayoutItem.textContent || "").trim().toLowerCase();
            const isProLayoutTitle =
              getProLayoutType(title) !== "pro";

            if (isProBadge || isLocked || isProLayoutTitle) {
              const val = getProLayoutType(title);
              event.preventDefault();
              event.stopPropagation();
              event.stopImmediatePropagation();
              triggerProOffer("layout_" + val, val);
              return;
            }
          }

          // Any direct pro badge click.
          const badge = target.closest(
            ".type-option__pro-badge, .type-panel-select__pro-badge, .reacg-pro-badge",
          );
          if (badge) {
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();
            triggerProOffer("badge");
            return;
          }
        },
        true,
      );
    };

    docs.forEach(function (doc) {
      if (!doc) return;
      bind(doc);
      attachGlobalProClickInterceptor(doc);
      if (!doc.__reacgTemplatePortalClickBound) {
        doc.__reacgTemplatePortalClickBound = true;
        doc.addEventListener(
          "click",
          function (event) {
            const target =
              event.target && event.target.closest
                ? event.target.closest("button, [role='button']")
                : null;
            const label = target
              ? (target.textContent || "").toLowerCase()
              : "";
            if (label.indexOf("template") === -1) {
              return;
            }
            try {
              const canvasWin = canvasDoc && canvasDoc.defaultView;
              if (
                canvasWin &&
                typeof canvasWin.__reacgOpenOverlayInAdmin === "function"
              ) {
                canvasWin.__reacgOpenOverlayInAdmin();
              }
            } catch (e) {}
          },
          true,
        );
      }
      if (!doc.body || doc.__reacgProControlsObserver) return;
      doc.__reacgProControlsObserver = new MutationObserver(function () {
        docs.forEach(bind);
      });
      doc.__reacgProControlsObserver.observe(doc.body, {
        childList: true,
        subtree: true,
      });
    });
  }

  /**
   * Let the canvas gallery app find sidebar targets that live in the admin document.
   *
   * @param {Document} canvasDoc
   */
  function enableSettingsPortal(canvasDoc) {
    const adminDoc = getAdminDocument();
    if (!canvasDoc) {
      return;
    }

    bridgeAdminApis(canvasDoc);
    enableGalleryOverlayPortal(canvasDoc);
    enableMuiPopoverFix(canvasDoc);
    enableBuilderProControls(canvasDoc);
    syncGalleryStyles(canvasDoc);

    // Gutenberg without the editor iframe uses one document for both canvas and
    // InspectorControls. Redirecting that document's own selector methods back
    // into itself recurses indefinitely as soon as the settings panel mounts.
    if (canvasDoc === adminDoc) {
      return;
    }

    const resolveTarget = function (selector) {
      const candidates = [
        adminDoc,
        (window.top && window.top.document) || null,
        (window.parent && window.parent.document) || null,
        document,
      ];
      for (let i = 0; i < candidates.length; i++) {
        const d = candidates[i];
        if (d && typeof d.querySelector === "function") {
          try {
            const target = d.querySelector(selector);
            if (target) {
              return target;
            }
          } catch (e) {}
        }
      }

      // If #reacg_settings not found anywhere yet, ensure DOM is built in adminDoc and retry
      if (selector === "#reacg_settings") {
        try {
          const modal =
            (window.top && window.top.ReacgBuilderModal) ||
            window.ReacgBuilderModal;
          if (modal && typeof modal.buildDom === "function") {
            modal.buildDom();
            for (let j = 0; j < candidates.length; j++) {
              const d2 = candidates[j];
              if (d2 && typeof d2.querySelector === "function") {
                const target2 = d2.querySelector(selector);
                if (target2) return target2;
              }
            }
          }
        } catch (e) {}
      }

      return null;
    };

    const patchDoc = function (targetDoc) {
      if (!targetDoc || targetDoc.__reacgSettingsPortal) {
        return;
      }
      targetDoc.__reacgSettingsPortal = true;

      const origQuerySelector = targetDoc.querySelector.bind(targetDoc);
      targetDoc.querySelector = function (selector) {
        if (SETTINGS_SELECTORS[selector]) {
          const target = resolveTarget(selector);
          if (target) {
            return target;
          }
        }
        return origQuerySelector(selector);
      };

      const origGetElementById = targetDoc.getElementById.bind(targetDoc);
      targetDoc.getElementById = function (id) {
        const selector = "#" + id;
        if (SETTINGS_SELECTORS[selector]) {
          const target = resolveTarget(selector);
          if (target) {
            return target;
          }
        }
        return origGetElementById(id);
      };
    };

    // The gallery React app runs in the canvas document. Keep this bridge scoped
    // to that document; patching the builder's admin document breaks unrelated
    // builder and WordPress selectors.
    patchDoc(canvasDoc);
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
    isEmbedded: false,
    currentGalleryId: 0,
    currentWidgetId: "",
    lastLoadedWidgetRootId: null,
    lastLoadedGalleryId: null,
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
      const adminDoc = getAdminDocument();
      ensureDashicons(document);
      ensureDashicons(adminDoc);
      if (
        window.top &&
        window.top.document &&
        window.top.document !== document &&
        window.top.document !== adminDoc
      ) {
        ensureDashicons(window.top.document);
      }

      const $existingBackdrop = $(adminDoc)
        .find("#reacg-builder-panel-backdrop")
        .add($("#reacg-builder-panel-backdrop"));
      const $existingPanel = $(adminDoc)
        .find("#reacg-builder-panel")
        .add($("#reacg-builder-panel"));

      if ($existingBackdrop.length && $existingPanel.length) {
        return;
      }

      // If one exists without the other, clean up before rebuilding
      if ($existingBackdrop.length && !$existingPanel.length) {
        $existingBackdrop.remove();
      } else if (!$existingBackdrop.length && $existingPanel.length) {
        $existingPanel.remove();
      }

      const data = this.getData();
      const pluginUrl = data.plugin_url || "";
      const iconUrl = pluginUrl + "/assets/images/icon.svg";

      // Ensure modal stylesheet is in adminDoc
      try {
        if (
          !adminDoc.getElementById("reacg-builder-modal-css") &&
          !adminDoc.querySelector('link[href*="builder-modal.css"]')
        ) {
          const cssLink = adminDoc.createElement("link");
          cssLink.rel = "stylesheet";
          cssLink.id = "reacg-builder-modal-css";
          cssLink.href =
            (pluginUrl || "") + "/builders/common/builder-modal.css";
          (adminDoc.head || adminDoc.documentElement).appendChild(cssLink);
        }
      } catch (e) {}

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
              <!-- Empty State when no gallery is selected (ID: 0) -->
              <div class="reacg-bp-empty-state reacg-hidden">
                <p class="reacg-bp-empty-desc">No gallery selected. Choose an existing gallery above or click <strong>+ New</strong> to create one.</p>
              </div>

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

      $(adminDoc.body || document.body).append(html);
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
        if (ReacgBuilderModal.isEmbedded) {
          return;
        }
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

      // Notify host builder / Elementor model in real time (only when user actively changes gallery, NOT when simply opening settings)
      if (!isOpening && typeof self.onSaveCallback === "function") {
        self.onSaveCallback(self.currentGalleryId);
      }

      if (self.currentGalleryId === 0) {
        // "Select gallery" (ID: 0) - no gallery data in DB, mounts empty gallery state like Gutenberg
        $(".reacg-bp-empty-state").removeClass("reacg-hidden");
        $(".reacg-bp-images-section").addClass("reacg-hidden");
        $(".reacg-bp-settings-section").addClass("reacg-hidden");
        $("#reacg-gallery-images").empty();
        self.mountReactSettingsInCanvas(0, isOpening);
        return;
      }

      $(".reacg-bp-empty-state").addClass("reacg-hidden");
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
      const self = this;
      let canvasDoc = getCanvasDocument();
      const requestedWidgetId = String(this.currentWidgetId || "")
        .trim()
        .replace(/[^a-zA-Z0-9_-]/g, "");
      if (requestedWidgetId) {
        canvasDoc = getCanvasDocumentForRoot(
          "reacg-root" + requestedWidgetId,
          canvasDoc,
        );
      }
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
        try {
          if (safeWidgetId) {
            galleryEl = canvasDoc.getElementById("reacg-root" + safeWidgetId);
            placeholder = canvasDoc.querySelector(
              '.reacg-builder-placeholder[data-widget-id="' +
                safeWidgetId +
                '"]',
            );
          }
        } catch (e) {}

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
            galleryEl = canvasDoc.getElementById("reacg-root" + safeWidgetId);
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

      const galleriesToUnmount = [];
      for (let i = 0; i < allGalleries.length; i++) {
        const gal = allGalleries[i];
        if (galleryEl && gal !== galleryEl) {
          if (gal.getAttribute("data-options-section") === "1") {
            gal.setAttribute("data-options-section", "0");
            gal.setAttribute("data-options-timestamp", String(Date.now()));
            galleriesToUnmount.push(gal);
          }
        }
      }

      const unmountOtherGalleries = function (app) {
        if (!app || !galleriesToUnmount.length) {
          return;
        }
        for (let i = 0; i < galleriesToUnmount.length; i++) {
          const gal = galleriesToUnmount[i];
          if (gal.id) {
            triggerLoadApp(app, gal.id);
          }
        }
      };

      if (galleryId === 0) {
        this.lastLoadedWidgetRootId = null;
        this.lastLoadedGalleryId = null;
        if (placeholder) {
          placeholder.classList.remove("reacg-hidden");
        }
        if (galleryEl) {
          galleryEl.classList.add("reacg-hidden");
          galleryEl.setAttribute("data-options-section", "0");
          galleryEl.setAttribute("data-gallery-id", "0");
          galleryEl.setAttribute("data-options-timestamp", String(Date.now()));
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
        const rootId =
          galleryEl.id || "reacg-root" + (this.currentWidgetId || galleryId);
        if (!galleryEl.id) {
          galleryEl.id = rootId;
        }
        const currentMountedId = galleryEl.getAttribute("data-gallery-id");
        const settingsContainer =
          adminDoc.getElementById("reacg_settings") ||
          document.getElementById("reacg_settings");

        // Check if gallery is already rendered with options enabled
        const isSameWidgetAndSameGallery =
          this.lastLoadedWidgetRootId === rootId &&
          String(this.lastLoadedGalleryId) === String(galleryId) &&
          String(currentMountedId) === String(galleryId) &&
          galleryEl.getAttribute("data-options-section") === "1";
        const hasSettingsMounted =
          settingsContainer && settingsContainer.children.length > 0;

        // If already rendered for this exact widget and settings are in place, do not reload or flash!
        if (isOpening && isSameWidgetAndSameGallery && hasSettingsMounted) {
          this.lastLoadedWidgetRootId = rootId;
          this.lastLoadedGalleryId = galleryId;
          galleryEl.classList.remove("reacg-hidden");
          return;
        }

        this.lastLoadedWidgetRootId = rootId;
        this.lastLoadedGalleryId = galleryId;

        galleryEl.classList.remove("reacg-hidden");
        galleryEl.setAttribute("data-options-section", "1");
        galleryEl.setAttribute("data-options-container", "#reacg_settings");
        galleryEl.setAttribute("data-gallery-id", galleryId);
        galleryEl.setAttribute("data-plugin-version", version);

        const timestamp = String(Date.now());
        galleryEl.setAttribute("data-options-timestamp", timestamp);
        if (String(currentMountedId) !== String(galleryId)) {
          galleryEl.setAttribute("data-gallery-timestamp", timestamp);
        }

        const attempt = function (remaining) {
          if (!loadApp) {
            loadApp = canvasDoc.getElementById("reacg-loadApp");
          }

          if (loadApp) {
            unmountOtherGalleries(loadApp);
            // Both gallery roots render into the one settings portal. Let the
            // previous root commit with controls disabled before mounting the
            // selected root, otherwise React can leave the first root owning
            // the portal after a Beaver partial render.
            setTimeout(
              function () {
                triggerLoadApp(loadApp, rootId);
                syncGalleryStyles(canvasDoc);
              },
              galleriesToUnmount.length ? 50 : 0,
            );
            return;
          }

          if (remaining > 0) {
            setTimeout(function () {
              attempt(remaining - 1);
            }, 250);
          }
        };

        attempt(20);
      } else if (galleryId > 0) {
        // If gallery element not yet in DOM, poll briefly until Elementor/builder renders it
        let retries = 10;
        const retryInterval = setInterval(function () {
          retries--;
          const retryGallery =
            (self.currentWidgetId &&
              canvasDoc.getElementById("reacg-root" + self.currentWidgetId)) ||
            (!self.currentWidgetId &&
              canvasDoc.querySelector(
                '.reacg-gallery[data-gallery-id="' + galleryId + '"]',
              ));
          if (retryGallery || retries <= 0) {
            clearInterval(retryInterval);
            if (retryGallery) {
              self.mountReactSettingsInCanvas(galleryId, false);
            }
          }
        }, 200);
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
      const galleryId = this.currentGalleryId || latestGalleryId;

      // Save against the active modal gallery, rather than clicking a React
      // button in the shared portal. That button can belong to a previously
      // mounted gallery after a builder partial render.
      if (latestOptions && galleryId) {
        this.saveOptionsDirect(galleryId, latestOptions);
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
    },

    mountEmbedded: function (container, options) {
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
      this.isEmbedded = true;

      let $panel = $("#reacg-builder-panel");
      let $backdrop = $("#reacg-builder-panel-backdrop");

      if (!$panel.length) {
        this.isInitialized = false;
        this.init();
        $panel = $("#reacg-builder-panel");
        $backdrop = $("#reacg-builder-panel-backdrop");
      }

      $backdrop.addClass("reacg-embedded-mode reacg-hidden");
      $panel
        .addClass("reacg-builder-panel-embedded")
        .removeClass("reacg-hidden");

      if (container) {
        const domContainer = container.jquery ? container.get(0) : container;
        if (domContainer && $panel.parent().get(0) !== domContainer) {
          $(domContainer).empty().append($panel);
        }
      }

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
      this.isOpen = true;

      this.loadGallery(targetGalleryId, true);
    },

    unmountEmbedded: function (widgetId) {
      this.flushAutoSave();
      this.isEmbedded = false;
      this.isOpen = false;

      const $panel = $("#reacg-builder-panel");
      const $backdrop = $("#reacg-builder-panel-backdrop");

      // Park #reacg-builder-panel safely inside $backdrop in document.body so it is never destroyed
      if (
        $backdrop.length &&
        $panel.length &&
        $panel.parent().get(0) !== $backdrop.get(0)
      ) {
        $backdrop.append($panel);
      }
      $backdrop.addClass("reacg-hidden").removeClass("reacg-embedded-mode");
      $panel.removeClass("reacg-builder-panel-embedded");
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

      const adminDoc = getAdminDocument();
      const $panel = $(adminDoc)
        .find("#reacg-builder-panel")
        .add($("#reacg-builder-panel"));
      const $backdrop = $(adminDoc)
        .find("#reacg-builder-panel-backdrop")
        .add($("#reacg-builder-panel-backdrop"));

      if (this.isEmbedded) {
        this.isEmbedded = false;
        $panel.removeClass("reacg-builder-panel-embedded");
        $backdrop.removeClass("reacg-embedded-mode");
        if ($panel.parent().get(0) !== $backdrop.get(0)) {
          $backdrop.append($panel);
        }
      }

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

      $backdrop.removeClass("reacg-hidden");
      this.isOpen = true;

      this.loadGallery(targetGalleryId, true);
    },

    close: function () {
      this.flushAutoSave();

      const adminDoc = getAdminDocument();
      if (!this.isEmbedded) {
        $(adminDoc)
          .find("#reacg-builder-panel-backdrop")
          .add($("#reacg-builder-panel-backdrop"))
          .addClass("reacg-hidden");
      }
      $(adminDoc)
        .find(".reacg-bp-create-form")
        .add($(".reacg-bp-create-form"))
        .addClass("reacg-hidden");
      this.isOpen = false;
      this.lastLoadedWidgetRootId = null;
      this.lastLoadedGalleryId = null;

      // Unmount options from canvas gallery so options never stay lingering in canvas
      if (this.currentWidgetId || this.currentGalleryId) {
        try {
          const rootId = this.currentWidgetId
            ? "reacg-root" +
              String(this.currentWidgetId).replace(/[^a-zA-Z0-9_-]/g, "")
            : "";
          const canvasDoc = getCanvasDocumentForRoot(
            rootId,
            getCanvasDocument(),
          );
          const activeGal =
            (this.currentWidgetId &&
              canvasDoc.getElementById("reacg-root" + this.currentWidgetId)) ||
            (this.currentGalleryId &&
              canvasDoc.querySelector(
                '.reacg-gallery[data-gallery-id="' +
                  this.currentGalleryId +
                  '"]',
              ));
          if (
            activeGal &&
            activeGal.getAttribute("data-options-section") === "1"
          ) {
            activeGal.setAttribute("data-options-section", "0");
            activeGal.setAttribute(
              "data-options-timestamp",
              String(Date.now()),
            );
            let loadApp = canvasDoc.getElementById("reacg-loadApp");
            if (loadApp && activeGal.id) {
              triggerLoadApp(loadApp, activeGal.id);
            }
          }
        } catch (e) {}
      }

      if (typeof this.onCloseCallback === "function") {
        this.onCloseCallback();
      }
    },
  };

  try {
    window.ReacgBuilderModal = ReacgBuilderModal;
    // The floating panel is owned by the top/admin document. Builder canvas
    // iframes also load this script, but must never replace that owner: their
    // callbacks would then be stored on a different object from the one whose
    // dropdown event listener receives the user's selection.
    if (window.top === window) {
      window.top.ReacgBuilderModal = ReacgBuilderModal;
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
    try {
      const canvasDoc = getCanvasDocument();
      if (canvasDoc) {
        enableSettingsPortal(canvasDoc);
      }
    } catch (e) {}
  });
})(jQuery);
