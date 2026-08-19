(function (blocks, element, blockEditor) {
  let el = element.createElement;
  const {InspectorControls} = blockEditor;
  const { useEffect, useRef } = element;
  /* useBlockProps is required for apiVersion 3 in the iframed editor (WP 6.3+ / 7.1). */
  const useBlockProps = blockEditor.useBlockProps || function (extra) {
    return extra || {};
  };
  const all_images_id = -1;
  const parsed = JSON.parse(reacg_gutenberg.data);
  /* If it's an object, convert its values to array.*/
  const shortcodes = Array.isArray(parsed) ? parsed : Object.values(parsed);
  const existGalleries = shortcodes.length > 1;
  /* Canvas nodes keyed by clientId so sidebar events can reach the iframe block. */
  const blockNodes = {};
  const SETTINGS_SELECTORS = {
    "#reacg_settings": true,
    "#reacg-gallery-images": true
  };

  /**
   * Admin document where the block script and InspectorControls live.
   *
   * @returns {Document}
   */
  function getAdminDocument() {
    return document;
  }

  /**
   * Document that contains the block canvas (iframe in WP 7.1, admin doc before that).
   *
   * @param {Element|null} node
   * @returns {Document}
   */
  function getCanvasDocument(node) {
    return (node && node.ownerDocument) || getAdminDocument();
  }

  /**
   * Resolve the mounted block wrapper for a clientId.
   *
   * @param {string} clientId
   * @param {Element|null} hintNode
   * @returns {Element|null}
   */
  function getBlockNode(clientId, hintNode) {
    const stored = blockNodes[clientId];
    if (stored && stored.isConnected) {
      return stored;
    }

    if (hintNode) {
      if (hintNode.id === "reacg-gutenberg" + clientId) {
        return hintNode;
      }
      if (hintNode.closest) {
        const closest = hintNode.closest("#reacg-gutenberg" + clientId)
          || hintNode.closest(".reacg-gutenberg");
        if (closest) {
          return closest;
        }
      }
      const fromHint = getCanvasDocument(hintNode).getElementById(
        "reacg-gutenberg" + clientId
      );
      if (fromHint) {
        return fromHint;
      }
    }

    const fromAdmin = getAdminDocument().getElementById(
      "reacg-gutenberg" + clientId
    );
    if (fromAdmin) {
      return fromAdmin;
    }

    return stored || null;
  }

  /**
   * Gallery app in the iframe reads admin helpers off its own window.
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
      "reacg_reload_preview",
      "reacg_update_item_image_fit",
      "reacg_make_items_sortable"
    ];
    names.forEach(function (name) {
      if (typeof window[name] === "function") {
        canvasWin[name] = window[name];
      }
    });
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

    if (canvasDoc.__reacgSettingsPortal) {
      return;
    }

    canvasDoc.__reacgSettingsPortal = true;
    const origQuerySelector = canvasDoc.querySelector.bind(canvasDoc);
    canvasDoc.querySelector = function (selector) {
      const local = origQuerySelector(selector);
      if (local) {
        return local;
      }
      if (SETTINGS_SELECTORS[selector]) {
        try {
          return adminDoc.querySelector(selector);
        }
        catch (error) {
          return null;
        }
      }
      return null;
    };

    enableGalleryOverlayPortal(canvasDoc);
    enableMuiPopoverFix(canvasDoc);
  }

  /**
   * True when document.body is being read from the gallery app, not Gutenberg.
   *
   * @returns {boolean}
   */
  function isGalleryAppCaller() {
    try {
      const stack = new Error().stack || "";
      return stack.indexOf("wp-gallery") !== -1;
    }
    catch (error) {
      return false;
    }
  }

  /**
   * Native body accessor for a document, ignoring our instance getter.
   *
   * @param {Document} doc
   * @returns {function|null}
   */
  function getNativeBodyGetter(doc) {
    const win = doc.defaultView;
    const protos = [];
    if (win && win.Document) {
      protos.push(win.Document.prototype);
    }
    if (win && win.HTMLDocument) {
      protos.push(win.HTMLDocument.prototype);
    }
    protos.push(Document.prototype);
    for (let i = 0; i < protos.length; i++) {
      try {
        const desc = Object.getOwnPropertyDescriptor(protos[i], "body");
        if (desc && desc.get) {
          return desc.get;
        }
      }
      catch (error) {
        /* Ignore.*/
      }
    }
    return null;
  }

  /**
   * MUI Dialog/Modal portals to document.body. Use the admin body so Template
   * Library (and other overlays) render on the page, with React events attached
   * there. Gutenberg still sees the real iframe body.
   *
   * @param {Document} canvasDoc
   */
  function enableGalleryOverlayPortal(canvasDoc) {
    const adminDoc = getAdminDocument();
    if (!canvasDoc || canvasDoc === adminDoc || canvasDoc.__reacgOverlayPortal) {
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
      }
    });
  }

  /**
   * Read CSS text from a style node, including Emotion/MUI rules inserted via CSSOM.
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
    }
    catch (error) {
      /* Cross-origin or unreadable stylesheet.*/
    }
    return inline;
  }

  /**
   * Copy gallery CSS-in-JS from the canvas into the admin document for portaled settings.
   * Emotion inserts rules with insertRule(), so cloneNode() would copy empty tags.
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
      if (node.getAttribute && node.getAttribute("data-reacg-synced-clone") === "1") {
        return false;
      }
      const name = node.nodeName.toUpperCase();
      if (name === "LINK") {
        const rel = (node.getAttribute("rel") || "").toLowerCase();
        const href = node.getAttribute("href") || "";
        return rel.indexOf("stylesheet") !== -1 && (
          href.indexOf("wp-gallery") !== -1
          || href.indexOf("reacg") !== -1
          || href.indexOf("/assets/") !== -1
          || href.indexOf("fonts.googleapis") !== -1
        );
      }
      if (name !== "STYLE") {
        return false;
      }
      const id = node.id || "";
      if (id.indexOf("wp-") === 0 || id.indexOf("core-block") === 0) {
        return false;
      }
      if (
        node.getAttribute("data-emotion")
        || node.getAttribute("data-jss")
        || node.getAttribute("data-styled")
      ) {
        return true;
      }
      const text = getStyleSheetText(node);
      return text.indexOf("reacg-") !== -1
        || text.indexOf("Mui") !== -1
        || text.indexOf("notistack") !== -1
        || text.indexOf("yarl__") !== -1;
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
        targets.push({source: node, dest: dest});
        return;
      }

      const css = getStyleSheetText(node);
      if (!css && !node.getAttribute("data-emotion") && !node.getAttribute("data-jss")) {
        return;
      }
      if (!dest || !dest.isConnected) {
        dest = adminDoc.createElement("style");
        dest.setAttribute("data-reacg-synced-clone", "1");
        if (node.getAttribute("data-emotion")) {
          dest.setAttribute("data-emotion", node.getAttribute("data-emotion"));
        }
        adminDoc.head.appendChild(dest);
        targets.push({source: node, dest: dest});
      }
      if (css && dest.textContent !== css) {
        dest.textContent = css;
      }
    };

    const scan = function () {
      const roots = [canvasDoc.head, canvasDoc.body];
      for (let i = 0; i < roots.length; i++) {
        if (!roots[i]) {
          continue;
        }
        roots[i].querySelectorAll("style, link[rel='stylesheet']").forEach(upsertStyle);
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
      subtree: true
    });

    /* insertRule() does not mutate the DOM, so keep copying until lazy MUI chunks settle. */
    let ticks = 0;
    const interval = setInterval(function () {
      ticks++;
      scan();
      if (ticks > 60) {
        clearInterval(interval);
      }
    }, 250);
  }

  /**
   * MUI menus are portaled with the iframe's window metrics against a sidebar
   * anchor in the admin document, so the first open can land on the far left.
   *
   * @param {HTMLElement} node
   * @returns {boolean}
   */
  function isMuiTooltip(node) {
    if (!node || node.nodeType !== 1) {
      return false;
    }
    const className = node.className && node.className.toString
      ? node.className.toString()
      : "";
    return className.indexOf("MuiTooltip") !== -1
      || node.getAttribute("role") === "tooltip"
      || !!(node.querySelector && node.querySelector('[role="tooltip"], .MuiTooltip-tooltip'));
  }

  /**
   * Full-screen MUI dialogs (Template Library) must not be snapped like selects.
   *
   * @param {HTMLElement} node
   * @returns {boolean}
   */
  function isMuiDialog(node) {
    if (!node || node.nodeType !== 1) {
      return false;
    }
    const className = node.className && node.className.toString
      ? node.className.toString()
      : "";
    return className.indexOf("MuiDialog") !== -1
      || className.indexOf("reacg-templates-dialog") !== -1
      || node.getAttribute("role") === "dialog"
      || !!(node.querySelector && node.querySelector(
        ".MuiDialog-root, .MuiDialog-container, .reacg-templates-dialog"
      ));
  }

  /**
   * @param {HTMLElement} node
   * @returns {boolean}
   */
  function isMuiOverlay(node) {
    if (!node || node.nodeType !== 1 || isMuiTooltip(node) || isMuiDialog(node)) {
      return false;
    }
    const className = node.className && node.className.toString
      ? node.className.toString()
      : "";
    return className.indexOf("MuiModal-root") !== -1
      || className.indexOf("MuiPopover-root") !== -1
      || className.indexOf("MuiMenu-root") !== -1;
  }

  /**
   * Find the sidebar control that opened a MUI menu.
   *
   * @param {Document} adminDoc
   * @returns {Element|null}
   */
  function getExpandedSettingsAnchor(adminDoc) {
    const roots = [
      adminDoc.getElementById("reacg_settings"),
      adminDoc.querySelector(".reacg-gutenberg-settings")
    ];
    for (let i = 0; i < roots.length; i++) {
      if (!roots[i]) {
        continue;
      }
      const expanded = roots[i].querySelector('[aria-expanded="true"]');
      if (expanded) {
        return expanded;
      }
    }
    return null;
  }

  /**
   * Use the full MUI select field, not a nested aria-expanded child.
   *
   * @param {Element} anchor
   * @returns {Element}
   */
  function getOverlayWidthSource(anchor) {
    return anchor.closest(".MuiInputBase-root")
      || anchor.closest(".MuiFormControl-root")
      || anchor.closest(".MuiButton-root")
      || anchor;
  }

  /**
   * Recompute a MUI overlay against the admin viewport, then snap it to the anchor.
   *
   * @param {HTMLElement} node
   * @param {Document} canvasDoc
   */
  function repositionMuiOverlay(node, canvasDoc) {
    const adminDoc = getAdminDocument();
    const canvasWin = canvasDoc.defaultView;
    const adminWin = adminDoc.defaultView;

    const apply = function () {
      syncGalleryStyles(canvasDoc);
      try {
        if (canvasWin) {
          canvasWin.dispatchEvent(new Event("resize"));
        }
        if (adminWin) {
          adminWin.dispatchEvent(new Event("resize"));
        }
      }
      catch (error) {
        /* Ignore.*/
      }

      const anchor = getExpandedSettingsAnchor(adminDoc);
      if (!anchor || !adminWin) {
        return;
      }

      const positioned = node.querySelector("[data-popper-placement]")
        || node.querySelector(".MuiPaper-root")
        || node.firstElementChild;
      if (!positioned || positioned.className && positioned.className.toString().indexOf("MuiBackdrop-root") !== -1) {
        return;
      }

      const widthSource = getOverlayWidthSource(anchor);
      const rect = widthSource.getBoundingClientRect();
      const minWidth = Math.ceil(rect.width);
      const viewportWidth = adminWin.innerWidth || adminDoc.documentElement.clientWidth;
      let left = rect.left;
      if (left + minWidth > viewportWidth - 8) {
        left = Math.max(8, rect.right - minWidth);
      }
      const top = rect.bottom + 4;

      positioned.style.setProperty("position", "fixed", "important");
      positioned.style.setProperty("top", top + "px", "important");
      positioned.style.setProperty("left", left + "px", "important");
      positioned.style.setProperty("transform", "none", "important");
      positioned.style.setProperty("margin", "0", "important");
      positioned.style.setProperty("right", "auto", "important");
      /* First open shrink-wraps before MUI copies the select width. */
      positioned.style.setProperty("min-width", minWidth + "px", "important");
      positioned.style.setProperty("width", minWidth + "px", "important");
      positioned.style.setProperty("box-sizing", "border-box", "important");

      const list = node.querySelector('.MuiList-root, .MuiMenu-list, [role="listbox"]');
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

  /**
   * Watch iframe and admin bodies for MUI portals opened from the sidebar.
   *
   * @param {Document} canvasDoc
   */
  function enableMuiPopoverFix(canvasDoc) {
    const adminDoc = getAdminDocument();
    if (!canvasDoc || canvasDoc === adminDoc || canvasDoc.__reacgMuiPopoverFix) {
      return;
    }
    canvasDoc.__reacgMuiPopoverFix = true;

    const watchNode = function (node) {
      if (isMuiTooltip(node) || isMuiDialog(node)) {
        syncGalleryStyles(canvasDoc);
        return;
      }
      if (isMuiOverlay(node)) {
        repositionMuiOverlay(node, canvasDoc);
        return;
      }
      if (!node || node.nodeType !== 1) {
        return;
      }
      const attrObserver = new MutationObserver(function () {
        if (isMuiTooltip(node) || isMuiDialog(node)) {
          attrObserver.disconnect();
          syncGalleryStyles(canvasDoc);
          return;
        }
        if (isMuiOverlay(node)) {
          attrObserver.disconnect();
          repositionMuiOverlay(node, canvasDoc);
        }
      });
      attrObserver.observe(node, {attributes: true, attributeFilter: ["class"]});
      setTimeout(function () {
        attrObserver.disconnect();
      }, 1000);
    };

    const observeBody = function (target) {
      if (!target || target.__reacgMuiObserved) {
        return;
      }
      target.__reacgMuiObserved = true;
      const observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
          mutation.addedNodes.forEach(watchNode);
        });
      });
      observer.observe(target, {childList: true});
    };

    observeBody(canvasDoc.body);
    observeBody(adminDoc.body);
  }

  const inspectorBindTimers = {};

  /**
   * Current sidebar panel (Post vs Block).
   *
   * @returns {string|null}
   */
  function getActiveInspectorArea() {
    const iface = wp.data.select("core/interface");
    if (iface && typeof iface.getActiveComplementaryArea === "function") {
      return iface.getActiveComplementaryArea("core/edit-post")
        || iface.getActiveComplementaryArea("core/edit-site")
        || iface.getActiveComplementaryArea("core/edit-widgets");
    }
    const editPost = wp.data.select("core/edit-post");
    if (editPost && typeof editPost.getActiveGeneralSidebarName === "function") {
      return editPost.getActiveGeneralSidebarName();
    }
    return null;
  }

  /**
   * @param {string|null} area
   * @returns {boolean}
   */
  function isBlockInspectorArea(area) {
    return !!area && String(area).indexOf("block") !== -1;
  }

  /**
   * Rebind images + options after InspectorControls remounts (Post/Block tab).
   *
   * @param {string} clientId
   * @param {string|number} shortcodeId
   * @param {Object} props
   */
  function scheduleInspectorBind(clientId, shortcodeId, props) {
    if (inspectorBindTimers[clientId]) {
      clearTimeout(inspectorBindTimers[clientId]);
    }
    inspectorBindTimers[clientId] = setTimeout(function () {
      delete inspectorBindTimers[clientId];
      const adminDoc = getAdminDocument();
      const settings = adminDoc.getElementById("reacg_settings");
      const images = adminDoc.getElementById("reacg-gallery-images");
      if (!settings && !images) {
        return;
      }
      const needsImages = images && !images.innerHTML;
      const needsSettings = settings && !settings.childElementCount;
      if (!needsImages && !needsSettings) {
        return;
      }
      const baseCont = getBlockNode(clientId);
      if (!baseCont || !shortcodeId) {
        return;
      }
      images_cont(baseCont, shortcodeId, props, false, true);
    }, 50);
  }

  /**
   * Click the gallery mount button in the same document as the gallery root.
   *
   * @param {Element} loadApp
   * @param {string} rootId
   * @returns {boolean}
   */
  function triggerLoadApp(loadApp, rootId) {
    loadApp.setAttribute("data-id", rootId);

    try {
      if (typeof loadApp.onclick === "function") {
        loadApp.onclick.call(loadApp);
        return true;
      }
    }
    catch (error) {
      /* Ignore onclick errors.*/
    }

    try {
      loadApp.click();
      return true;
    }
    catch (error) {
      /* Ignore click errors.*/
    }

    const view = loadApp.ownerDocument && loadApp.ownerDocument.defaultView
      ? loadApp.ownerDocument.defaultView
      : window;
    loadApp.dispatchEvent(new MouseEvent("click", {
      bubbles: true,
      cancelable: true,
      view: view
    }));
    return true;
  }

  blocks.registerBlockType("reacg/gallery", {
    apiVersion: 3,
    title: reacg_gutenberg.title,
    description: reacg_gutenberg.description,
    icon: el('img', {
      width: 24,
      height: 24,
      src: reacg_gutenberg.icon
    }),
    category: 'common',
    // Disable support for Additional CSS Class(es) in the block sidebar.
    supports: {
      customClassName: false
    },
    example: {},
    keywords: [
      'gallery',
      'photo gallery',
      'image gallery',
      're gallery',
      'media',
    ],
    attributes: {
      shortcode: {
        type: "string",
        value: ""
      },
      shortcode_id: {
        type: "int",
        value: 0
      },
      hidePreview: {
        type: "boolean",
        value: false
      },
    },
    edit: function (props) {
      const blockRef = useRef(null);
      const latestProps = useRef(props);
      latestProps.current = props;
      const shortcode_id = props.attributes.shortcode_id;
      const showPlaceholder = !props.attributes.hidePreview && !props.isSelected;
      const onInspectorMount = useRef(function (node) {
        if (!node) {
          return;
        }
        const current = latestProps.current;
        if (!current.isSelected || !current.attributes.shortcode_id) {
          return;
        }
        scheduleInspectorBind(
          current.clientId,
          current.attributes.shortcode_id,
          current
        );
      });

      const setBlockRef = function (node) {
        if (node) {
          blockRef.current = node;
          blockNodes[props.clientId] = node;
          enableSettingsPortal(node.ownerDocument);
          syncGalleryStyles(node.ownerDocument);
        }
        else {
          if (blockNodes[props.clientId] === blockRef.current) {
            delete blockNodes[props.clientId];
          }
          blockRef.current = null;
        }
      };

      useEffect(() => {
        if (showPlaceholder || props.attributes.hidePreview) {
          return;
        }
        props.setAttributes({
          hidePreview: true,
        });
      }, [showPlaceholder]);

      useEffect(() => {
        if (!props.isSelected) {
          return;
        }

        if (typeof wp.data.select('core/edit-post') !== "undefined"
          && typeof wp.data.dispatch('core/edit-post').openGeneralSidebar !== 'undefined') {
          wp.data.dispatch('core/edit-post').openGeneralSidebar('edit-post/block');
        }

        if (!wp.data.select('core/edit-widgets')) {
          return;
        }

        // Force BLOCK tab
        wp.data.dispatch('core/interface').enableComplementaryArea(
          'core/edit-widgets',
          'edit-widgets/block'
        );
        setTimeout(() => {
          const tab = getAdminDocument().getElementById(
            'tabs-0-edit-widgets/block-inspector'
          );
          tab?.click();
        }, 50);
      }, [props.isSelected]);

      useEffect(() => {
        if (showPlaceholder) {
          return;
        }

        const baseCont = blockRef.current || getBlockNode(props.clientId);
        if (!baseCont) {
          return;
        }

        if (!shortcode_id) {
          const controls = baseCont.querySelector(".reacg-setup-controls");
          if (controls) {
            controls.classList.remove("reacg-hidden");
          }
          return;
        }

        images_cont(baseCont, shortcode_id, props, true, true);
      }, [shortcode_id, props.isSelected, showPlaceholder, props.clientId]);

      useEffect(() => {
        if (showPlaceholder || !shortcode_id || !props.isSelected) {
          return;
        }

        let lastIsBlock = isBlockInspectorArea(getActiveInspectorArea());
        const unsubscribe = wp.data.subscribe(function () {
          const isBlock = isBlockInspectorArea(getActiveInspectorArea());
          if (isBlock === lastIsBlock) {
            return;
          }
          lastIsBlock = isBlock;
          if (!isBlock) {
            return;
          }
          scheduleInspectorBind(props.clientId, shortcode_id, latestProps.current);
        });

        return unsubscribe;
      }, [shortcode_id, props.isSelected, showPlaceholder, props.clientId]);

      const blockProps = useBlockProps({
        className: "reacg-gutenberg" + (showPlaceholder ? " reacg-block-preview" : ""),
        id: "reacg-gutenberg" + props.clientId,
        ref: setBlockRef
      });

      // Display block preview only on the block hover.
      if (showPlaceholder) {
        return el("div", blockProps, el("img", {
          src: reacg_gutenberg.plugin_url + "/builders/gutenberg/images/preview.png",
          style: {height: "auto", width: "100%"}
        }));
      }

      const selected = wp.data.select('core/block-editor').getSelectedBlock();
      const selectedBlockShortcodeId = selected?.attributes?.shortcode_id;

      return regallery(props, selectedBlockShortcodeId, blockProps, onInspectorMount.current);
    },
    save: function (props) {
      return props.attributes.shortcode;
    }
  });

  /**
   * Gallery block.
   *
   * @param props
   * @param selectedBlockShortcodeId
   * @param blockProps
   * @param onInspectorMount
   * @returns {*}
   */
  function regallery(props, selectedBlockShortcodeId, blockProps, onInspectorMount) {
    const shortcode_id = typeof props.attributes.shortcode_id == "undefined" ? 0 : props.attributes.shortcode_id;

    const loader = el('div', {class: "reacg-spinner__wrapper reacg-hidden"}, el('span', {
      class: "spinner is-active",
    }));

    const setup_wizard = props.attributes.shortcode_id ? "" : el('div', {class: "reacg-setup-wizard"},
      el('img', {
        width: 50,
        height: 50,
        src: reacg_gutenberg.icon
      }),
      existGalleries ? el('p', {}, reacg_gutenberg.setup_wizard_description) : "",
      el("div",
        {
          class: "reacg-setup-controls"
        },
        create_gallery_button(props),
        existGalleries ? galleries_list(props) : ""
      ),
    );

    return el(
      "div",
      blockProps,
      loader,
      setup_wizard,
      gallery_container(shortcode_id, props),
      shortcode_id && props.isSelected ? el(InspectorControls, {},
        el("div",
          {
            class: "reacg-gutenberg-settings",
            ref: onInspectorMount
          },
          galleries_list(props),
          gallery_images_container(selectedBlockShortcodeId),
          gallery_settings_container(),
        )
      ) : "",
    );
  }

  function gallery_container(shortcode_id, props) {
    const timestamp = Date.now();

    return el('div', {
      'data-options-section': 0,
      'data-options-container': "#reacg_settings",
      'data-gallery-id': shortcode_id,
      'data-plugin-version': reacg_gutenberg.plugin_version,
      'data-gallery-timestamp': timestamp,
      'data-options-timestamp': timestamp,
      class: "reacg-wrapper reacg-gallery reacg-preview" + (shortcode_id ? "" : " reacg-hidden"),
      id: "reacg-root" + props.clientId,
    });
  }
  function gallery_settings_container() {
    return el("div", {
        id: "reacg_settings",
        class: "reacg-wrapper",
      }
    );
  }
  function gallery_images_container(shortcode_id) {
    return el("div", {
        class: (shortcode_id != all_images_id ? "" : "reacg-hidden"),
      },
      el('h2', {
        class: 'reacg-gallery-images-section-title'
      }, reacg_gutenberg.gallery_images_section_title),
      el("div", {
        id: "reacg-gallery-images",
      })
    );
  }

  function set_data(baseCont, shortcode_id, props, enableOptions) {
    const galleryCont = baseCont.querySelector(".reacg-gallery");
    if (galleryCont) {
      const timestamp = Date.now();
      galleryCont.classList.remove("reacg-hidden");
      galleryCont.setAttribute('data-options-section', enableOptions ? 1 : 0);
      galleryCont.setAttribute('data-options-container', "#reacg_settings");
      galleryCont.setAttribute('data-gallery-id', shortcode_id);
      galleryCont.setAttribute('data-plugin-version', reacg_gutenberg.plugin_version);
      galleryCont.setAttribute('data-gallery-timestamp', timestamp);
      galleryCont.setAttribute('data-options-timestamp', timestamp);
      galleryCont.setAttribute('id', "reacg-root" + props.clientId);
    }
  }

  function reload_gallery(props, hintNode) {
    const rootId = "reacg-root" + props.clientId;
    const attempt = function (remaining) {
      const baseCont = getBlockNode(props.clientId, hintNode);
      const canvasDoc = getCanvasDocument(baseCont);
      enableSettingsPortal(canvasDoc);
      syncGalleryStyles(canvasDoc);

      let loadApp = canvasDoc.getElementById("reacg-loadApp");
      if (!loadApp && canvasDoc !== getAdminDocument()) {
        loadApp = getAdminDocument().getElementById("reacg-loadApp");
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

  function images_cont(baseCont, shortcode_id, props, first_load, selectGallery) {
    fetch(reacg_gutenberg.ajax_url + '&action=reacg_get_images&id=' + shortcode_id)
      .then(response => response.json())
      .then(data => {
        const container = getAdminDocument().querySelector("#reacg-gallery-images");
        if (container && (!first_load || !container.innerHTML)) {
          if (shortcode_id != all_images_id) {
            container.classList.remove("reacg-hidden");
            container.innerHTML = data;
            if (typeof reacg_update_item_image_fit === "function") {
              container.querySelectorAll(".reacg_item_image img").forEach(reacg_update_item_image_fit);
            }
            /* Make the image items sortable.*/
            reacg_make_items_sortable(container);
          }
          else {
            container.classList.add("reacg-hidden");
            container.innerHTML = "";
          }
        }

        if (!selectGallery) {
          const list = baseCont.querySelector(".reacg-galleries-list");
          if (list) {
            list.classList.add("reacg-hidden");
          }
        }

        /* Options UI lives in InspectorControls (admin document), not in the canvas iframe. */
        const enableOptions = !!getAdminDocument().querySelector("#reacg_settings");
        set_data(baseCont, shortcode_id, props, enableOptions);
        reload_gallery(props, baseCont);

        const spinner = baseCont.querySelector(".reacg-spinner__wrapper");
        if (spinner) {
          spinner.classList.add("reacg-hidden");
        }
      })
      .catch(error => console.error("Error fetching data:", error));
  }

  function showPreview(shortcode_id, props, hintNode) {
    const baseCont = getBlockNode(props.clientId, hintNode);
    if (baseCont) {
      const spinner = baseCont.querySelector(".reacg-spinner__wrapper");
      if (spinner) {
        spinner.classList.remove("reacg-hidden");
      }
      if (shortcode_id === 0) {
        const titleInput = baseCont.querySelector(".reacg-gallery-title-input");
        fetch(reacg_gutenberg.ajax_url + '&action=reacg_save_gallery&gallery_title=' + (titleInput ? titleInput.value : ""))
          .then(response => response.json())
          .then(data => {
            shortcode_id = data;
            props.setAttributes({
              shortcode: '[REACG id="' + shortcode_id + '"]',
              shortcode_id: shortcode_id,
            });
            const controls = baseCont.querySelector(".reacg-setup-controls");
            if (controls) {
              controls.classList.add("reacg-hidden");
            }
            images_cont(baseCont, shortcode_id, props, false, false);
          })
          .catch(error => console.error("Error fetching data:", error));
      }
      else {
        images_cont(baseCont, shortcode_id, props, false, true);
      }
    }
  }

  function create_gallery_button(props) {
    const create_button = el('button', {
      class: "reacg-create-gallery button button-primary button-large",
      onClick: (event) => showPreview(0, props, event.target),
    }, reacg_gutenberg.create_button);
    const gallery_title_input = el('input', {
      type: "text",
      class: "reacg-gallery-title-input",
      name: "reacg_gallery_title",
      placeholder: reacg_gutenberg.gallery_title_placeholder,
    });
    return  el('div', '', gallery_title_input, create_button);
  }

  function galleries_list(props) {
    // Add shortcodes to the html elements.
    let shortcode_list = [];
    shortcodes.forEach(function (shortcode_data) {
      shortcode_list.push(
        el('option', {
          value: shortcode_data.id,
          "data-shortcode": shortcode_data.shortcode,
        }, shortcode_data.title)
      );
    });
    return el('select', {
        value: props.attributes.shortcode_id,
        onChange: (event) => itemSelect(event, props),
        class: 'reacg-galleries-list'
      }, shortcode_list);
  }

  /**
   * Bind an event on the item select.
   *
   * @param event
   * @param props
   */
  function itemSelect(event, props) {
    let selected = event.target.querySelector("option:checked");
    if (typeof props.attributes.shortcode_id == "undefined") {
      props.setAttributes({
        shortcode_id: 0,
      });
    }
    showPreview(selected.value, props, event.target);
    // Get selected item's data.
    props.setAttributes({
      shortcode: selected.dataset.shortcode,
      shortcode_id: selected.value,
    });
    event.preventDefault();
  }

  /**
   * Save gallery options from the sidebar or the canvas document.
   */
  function clickSaveSettingsButtons() {
    const docs = [getAdminDocument()];
    Object.keys(blockNodes).forEach(function (clientId) {
      const node = blockNodes[clientId];
      const canvasDoc = node && node.ownerDocument;
      if (canvasDoc && docs.indexOf(canvasDoc) === -1) {
        docs.push(canvasDoc);
      }
    });
    docs.forEach(function (doc) {
      const buttons = doc.querySelectorAll(".save-settings-button");
      for (let i = 0; i < buttons.length; i++) {
        buttons[i].click();
      }
    });
  }

  wp.domReady(function () {
    const { select, subscribe } = wp.data;

    let wasSaving = false;

    subscribe(function () {
      const blockEditorStore = select('core/block-editor');
      if (!blockEditorStore || typeof blockEditorStore.getBlocks !== 'function') {
        return;
      }

      const blocks = blockEditorStore.getBlocks();
      const hasGalleryBlock = blocks.some(
        block => block.name === 'reacg/gallery'
      );

      if (!hasGalleryBlock) {
        return; /* Exit early if block not present.*/
      }

      const editor = select('core/editor');
      if (!editor || typeof editor.isSavingPost !== 'function') {
        return;
      }

      const isSaving = editor.isSavingPost();
      const isAutosaving = typeof editor.isAutosavingPost === 'function'
        && editor.isAutosavingPost();

      if (isSaving && !isAutosaving && !wasSaving) {
        wasSaving = true;
        /* Save options on saving the gallery.*/
        clickSaveSettingsButtons();
      }

      if (!isSaving) {
        wasSaving = false;
      }
    });
  });
})(
  window.wp.blocks,
  window.wp.element,
  window.wp.blockEditor,
  window.wp.components
);
