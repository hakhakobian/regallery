/**
 * ReGallery Gutenberg Integration Script
 *
 * Uses the shared ReacgBuilderModal UI embedded in Gutenberg InspectorControls.
 */
(function (blocks, element, blockEditor, components) {
  "use strict";

  const el = element.createElement;
  const { InspectorControls } = blockEditor;
  const { useEffect, useRef } = element;
  const { PanelBody } = components;

  /* useBlockProps is required for apiVersion 3 in the iframed editor (WP 6.3+ / 7.1). */
  const useBlockProps =
    blockEditor.useBlockProps ||
    function (extra) {
      return extra || {};
    };

  /* Canvas nodes keyed by clientId so sidebar/modal events can reach the iframe block. */
  const blockNodes = {};

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
        const closest =
          hintNode.closest("#reacg-gutenberg" + clientId) ||
          hintNode.closest(".reacg-gutenberg");
        if (closest) {
          return closest;
        }
      }
      const fromHint = getCanvasDocument(hintNode).getElementById(
        "reacg-gutenberg" + clientId,
      );
      if (fromHint) {
        return fromHint;
      }
    }

    const fromAdmin = getAdminDocument().getElementById(
      "reacg-gutenberg" + clientId,
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
    names.forEach(function (name) {
      // The gallery bundle exposes upgrade dialogs asynchronously. Copying a
      // function only when the block mounts leaves the canvas without a
      // handler when that bundle has not finished loading yet.
      if (
        typeof canvasWin[name] === "function" &&
        !canvasWin[name].__reacgGutenbergDialogProxy
      ) {
        return;
      }

      const proxy = function () {
        const args = arguments;
        const candidates = [window];

        try {
          if (window.parent && window.parent !== window) {
            candidates.push(window.parent);
          }
        } catch (error) {}
        try {
          if (window.top && window.top !== window.parent) {
            candidates.push(window.top);
          }
        } catch (error) {}

        const invoke = function () {
          for (let i = 0; i < candidates.length; i++) {
            const candidate = candidates[i];
            if (
              candidate &&
              typeof candidate[name] === "function" &&
              candidate[name] !== proxy &&
              !candidate[name].__reacgGutenbergDialogProxy
            ) {
              candidate[name].apply(candidate, args);
              return true;
            }
          }
          return false;
        };

        if (invoke()) {
          return;
        }

        // wp-gallery.js can finish loading after the block canvas is mounted.
        // Resolve the dialog implementation once it becomes available.
        let attempts = 0;
        const retry = window.setInterval(function () {
          attempts++;
          if (invoke() || attempts >= 40) {
            window.clearInterval(retry);
          }
        }, 100);
      };
      proxy.__reacgGutenbergDialogProxy = true;
      canvasWin[name] = proxy;
    });

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

    if (!canvasWin.__reacgOnOptionsChangeBridged) {
      canvasWin.__reacgOnOptionsChangeBridged = true;
      const origCanvasOnOptionsChange = canvasWin.reacg_global.onOptionsChange;
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
    } catch (error) {
      /* Ignore onclick errors. */
    }

    try {
      loadApp.click();
      return true;
    } catch (error) {
      /* Ignore click errors. */
    }

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

  /**
   * Reload gallery preview in canvas iframe or admin document.
   *
   * @param {Object} props
   * @param {Element|null} hintNode
   */
  function reload_gallery(props, hintNode) {
    const rootId = "reacg-root" + props.clientId;
    const attempt = function (remaining) {
      const baseCont = getBlockNode(props.clientId, hintNode);
      const canvasDoc = getCanvasDocument(baseCont);
      bridgeAdminApis(canvasDoc);

      let loadApp = canvasDoc.getElementById("reacg-loadApp");
      if (!loadApp && canvasDoc !== getAdminDocument()) {
        loadApp = getAdminDocument().getElementById("reacg-loadApp");
      }

      if (loadApp) {
        triggerLoadApp(loadApp, rootId);
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

  /**
   * Safe getter for ReacgBuilderModal across window hierarchies.
   *
   * @returns {Object|null}
   */
  function getBuilderModal() {
    try {
      if (window.top && window.top.ReacgBuilderModal) {
        return window.top.ReacgBuilderModal;
      }
    } catch (e) {}
    try {
      if (window.parent && window.parent.ReacgBuilderModal) {
        return window.parent.ReacgBuilderModal;
      }
    } catch (e) {}
    return window.ReacgBuilderModal || null;
  }

  /**
   * Helper to select block and open Gutenberg's block inspector sidebar.
   *
   * @param {Object} props
   */
  function focusBlockInSidebar(props) {
    if (window.wp && window.wp.data && props.clientId) {
      try {
        const blockEditor = window.wp.data.dispatch("core/block-editor");
        if (blockEditor && typeof blockEditor.selectBlock === "function") {
          blockEditor.selectBlock(props.clientId);
        }
        const editPost = window.wp.data.dispatch("core/edit-post");
        if (editPost && typeof editPost.openGeneralSidebar === "function") {
          editPost.openGeneralSidebar("edit-post/block");
        } else {
          const editSite = window.wp.data.dispatch("core/edit-site");
          if (editSite && typeof editSite.openGeneralSidebar === "function") {
            editSite.openGeneralSidebar("edit-site/block");
          }
        }
      } catch (e) {}
    }
  }

  /**
   * Helper to mount the embedded modal settings panel inside Gutenberg's InspectorControls.
   *
   * @param {Element} containerNode
   * @param {Object} props Current Gutenberg block props
   * @param {number} [retries=0] Retry count while waiting for ReacgBuilderModal to load
   */
  function mountEmbeddedSettings(containerNode, props, retries) {
    if (!containerNode) return;
    retries = typeof retries === "number" ? retries : 0;
    const modal = getBuilderModal();
    if (!modal || typeof modal.mountEmbedded !== "function") {
      if (retries < 30) {
        setTimeout(function () {
          mountEmbeddedSettings(containerNode, props, retries + 1);
        }, 100);
        return;
      }
      console.error("ReacgBuilderModal is not loaded.");
      return;
    }

    const widgetId = props.clientId;
    let currentGalleryId = parseInt(props.attributes.shortcode_id, 10) || 0;

    // Check if DOM already has a non-zero gallery ID rendered for this widget
    const baseCont = getBlockNode(widgetId);
    if (baseCont) {
      const galleryDom = baseCont.querySelector(".reacg-gallery");
      if (galleryDom) {
        const domGalleryId = parseInt(
          galleryDom.getAttribute("data-gallery-id"),
          10,
        );
        if (!isNaN(domGalleryId) && domGalleryId > 0) {
          currentGalleryId = domGalleryId;
        }
      }
    }

    modal.mountEmbedded(containerNode, {
      galleryId: currentGalleryId,
      widgetId: widgetId,
      onSave: function (savedGalleryId) {
        const idNum = parseInt(savedGalleryId, 10) || 0;
        const currentAttrId = parseInt(props.attributes.shortcode_id, 10) || 0;
        const node = getBlockNode(widgetId);
        const gallery = node ? node.querySelector(".reacg-gallery") : null;
        const alreadyRendered =
          gallery &&
          gallery.children.length > 0 &&
          (gallery.querySelector(".reacg-gallery-wrapper") ||
            gallery.querySelector("img") ||
            gallery.querySelector(".reacg_item") ||
            gallery.querySelector(".content-placeholder"));

        // If gallery ID has not changed and canvas is already rendered, do not update timestamps or reload
        if (idNum === currentAttrId && alreadyRendered) {
          return;
        }

        const newShortcode = idNum ? '[REACG id="' + idNum + '"]' : "";

        props.setAttributes({
          shortcode_id: idNum,
          shortcode: newShortcode,
        });

        // Also update via core block-editor store if available
        if (window.wp && window.wp.data && widgetId) {
          try {
            const blockEditorStore =
              window.wp.data.dispatch("core/block-editor");
            if (
              blockEditorStore &&
              typeof blockEditorStore.updateBlockAttributes === "function"
            ) {
              blockEditorStore.updateBlockAttributes(widgetId, {
                shortcode_id: idNum,
                shortcode: newShortcode,
              });
            }
          } catch (e) {}
        }

        // Live-update canvas placeholder & gallery container
        if (node) {
          const placeholder = node.querySelector(".reacg-builder-placeholder");
          const gallery = node.querySelector(".reacg-gallery");
          if (idNum === 0) {
            if (placeholder) {
              placeholder.classList.remove("reacg-hidden");
            }
            if (gallery) {
              gallery.classList.add("reacg-hidden");
              gallery.setAttribute("data-options-section", "0");
              gallery.setAttribute("data-gallery-id", "0");
            }
          } else {
            if (placeholder) {
              placeholder.classList.add("reacg-hidden");
            }
            if (gallery) {
              gallery.classList.remove("reacg-hidden");
              gallery.setAttribute("data-options-section", "1");
              gallery.setAttribute("data-options-container", "#reacg_settings");
              gallery.setAttribute("data-gallery-id", idNum);
              gallery.setAttribute(
                "data-plugin-version",
                reacg_gutenberg.plugin_version,
              );
              gallery.setAttribute("data-gallery-timestamp", Date.now());
              gallery.setAttribute("data-options-timestamp", Date.now());
            }
            reload_gallery(props, node);
          }
        }
      },
    });
  }

  /**
   * Render placeholder element matching Elementor and other visual page builders.
   *
   * @param {Object} props
   * @param {boolean} isHidden
   * @returns {Element}
   */
  function renderPlaceholder(props, isHidden) {
    return el(
      "div",
      {
        className:
          "reacg-builder-placeholder" + (isHidden ? " reacg-hidden" : ""),
        "data-widget-id": props.clientId,
        onClick: function (e) {
          e.preventDefault();
          focusBlockInSidebar(props);
        },
      },
      el(
        "div",
        { className: "reacg-builder-placeholder-icon" },
        el("img", {
          src: reacg_gutenberg.icon,
          alt: reacg_gutenberg.title || "ReGallery",
        }),
      ),
      el(
        "div",
        { className: "reacg-builder-placeholder-title" },
        reacg_gutenberg.title || "ReGallery",
      ),
      el(
        "p",
        { className: "reacg-builder-placeholder-desc" },
        reacg_gutenberg.text_no_gallery_selected ||
          "No gallery selected yet. Select an existing gallery or create a new one.",
      ),
      el(
        "button",
        {
          type: "button",
          className: "reacg-builder-open-modal-btn button button-primary",
          onClick: function (e) {
            e.preventDefault();
            e.stopPropagation();
            focusBlockInSidebar(props);
          },
        },
        el("span", { className: "dashicons dashicons-format-gallery" }),
        " " +
          (reacg_gutenberg.text_select_or_create || "Select or Create Gallery"),
      ),
    );
  }

  /**
   * Render gallery container for canvas live preview.
   *
   * @param {number} shortcodeId
   * @param {Object} props
   * @returns {Element}
   */
  function renderGalleryContainer(shortcodeId, props) {
    const hasGallery = shortcodeId !== 0;

    return el("div", {
      "data-options-section": hasGallery ? 1 : 0,
      "data-options-container": "#reacg_settings",
      "data-gallery-id": shortcodeId,
      "data-plugin-version": reacg_gutenberg.plugin_version,
      className:
        "reacg-wrapper reacg-gallery reacg-preview" +
        (hasGallery ? "" : " reacg-hidden"),
      id: "reacg-root" + props.clientId,
      onClick: function (e) {
        e.preventDefault();
        e.stopPropagation();
        focusBlockInSidebar(props);
      },
    });
  }

  /**
   * React component managing lifecycle of embedded builder modal in InspectorControls.
   *
   * @param {Object} componentProps
   * @returns {Element}
   */
  function EmbeddedSettingsPanel(componentProps) {
    const containerRef = useRef(null);
    const props = componentProps.props;

    useEffect(
      function () {
        const node = containerRef.current;
        if (node) {
          mountEmbeddedSettings(node, props);
        }

        return function () {
          const modal = getBuilderModal();
          if (
            modal &&
            typeof modal.unmountEmbedded === "function" &&
            // Only unmount if this block still owns the panel.
            // If another ReGallery block has already claimed it, skip — otherwise
            // we would park the freshly-mounted panel of the new block.
            modal.currentWidgetId === String(props.clientId)
          ) {
            modal.unmountEmbedded(props.clientId);
          }
        };
      },
      [props.clientId],
    );

    return el("div", {
      className: "reacg-gutenberg-settings-container",
      ref: containerRef,
    });
  }

  /**
   * Render sidebar InspectorControls panel mounting the embedded builder modal.
   *
   * @param {Object} props
   * @returns {Element}
   */
  function renderSidebarPanel(props) {
    return el(
      PanelBody,
      {
        // title: reacg_gutenberg.text_settings || "Gallery Settings",
        initialOpen: true,
        className: "reacg-gutenberg-panel-body",
      },
      el(EmbeddedSettingsPanel, { props: props }),
    );
  }

  // Register the ReGallery block
  blocks.registerBlockType("reacg/gallery", {
    apiVersion: 3,
    title: reacg_gutenberg.title,
    description: reacg_gutenberg.description,
    icon: el("img", {
      width: 24,
      height: 24,
      src: reacg_gutenberg.icon,
    }),
    category: "common",
    supports: {
      customClassName: false,
    },
    example: {
      attributes: {
        isExample: true,
      },
    },
    keywords: [
      "gallery",
      "photo gallery",
      "image gallery",
      "re gallery",
      "media",
    ],
    attributes: {
      shortcode: {
        type: "string",
        value: "",
      },
      shortcode_id: {
        type: "int",
        value: 0,
      },
      isExample: {
        type: "boolean",
        value: false,
      },
    },
    edit: function (props) {
      const blockRef = useRef(null);
      const shortcodeId = parseInt(props.attributes.shortcode_id, 10) || 0;
      const isExample = !!(props.attributes && props.attributes.isExample);

      const setBlockRef = function (node) {
        if (node) {
          blockRef.current = node;
          blockNodes[props.clientId] = node;
          bridgeAdminApis(node.ownerDocument);
        } else {
          if (blockNodes[props.clientId] === blockRef.current) {
            delete blockNodes[props.clientId];
          }
          blockRef.current = null;
        }
      };

      // Initial mount when gallery is first rendered or shortcode_id changes
      useEffect(
        function () {
          if (isExample || !shortcodeId) {
            return;
          }

          const baseCont = blockRef.current || getBlockNode(props.clientId);
          if (!baseCont) {
            return;
          }

          const gallery = baseCont.querySelector(".reacg-gallery");
          if (gallery) {
            gallery.classList.remove("reacg-hidden");
            gallery.setAttribute("data-options-section", "1");
            gallery.setAttribute("data-options-container", "#reacg_settings");
            gallery.setAttribute("data-gallery-id", shortcodeId);
            gallery.setAttribute(
              "data-plugin-version",
              reacg_gutenberg.plugin_version,
            );

            // If the gallery DOM already has rendered content for this exact shortcodeId, do not reload!
            const alreadyRendered =
              gallery.children.length > 0 &&
              (gallery.querySelector(".reacg-gallery-wrapper") ||
                gallery.querySelector("img") ||
                gallery.querySelector(".reacg_item") ||
                gallery.querySelector(".content-placeholder"));
            if (alreadyRendered) {
              return;
            }
          }

          reload_gallery(props, baseCont);
        },
        [shortcodeId, props.clientId, isExample],
      );

      // When block unselects or selects, toggle sidebar class and manage embedded modal.
      // Canvas gallery is never reloaded on select/unselect since nothing changed.
      useEffect(
        function () {
          const SIDEBAR_CLASS = "reacg-gutenberg-sidebar-active";
          const sidebar =
            document.querySelector(".interface-interface-skeleton__sidebar") ||
            document.querySelector(".edit-post-sidebar") ||
            document.querySelector('[role="complementary"]');

          /**
           * Returns true when a *different* ReGallery block is currently selected.
           * Used to avoid removing the sidebar class when React's effect order means
           * the deselecting block's effect runs after the selecting block's effect.
           */
          function anotherReacgBlockIsSelected() {
            try {
              const sel =
                window.wp &&
                window.wp.data &&
                window.wp.data.select("core/block-editor").getSelectedBlock();
              return (
                sel &&
                sel.name === "reacg/gallery" &&
                sel.clientId !== props.clientId
              );
            } catch (e) {
              return false;
            }
          }

          if (props.isSelected) {
            if (sidebar) sidebar.classList.add(SIDEBAR_CLASS);
          } else {
            // Only remove the class if no other ReGallery block has taken over.
            if (!anotherReacgBlockIsSelected()) {
              if (sidebar) sidebar.classList.remove(SIDEBAR_CLASS);
            }

            const modal = getBuilderModal();
            if (
              modal &&
              typeof modal.unmountEmbedded === "function" &&
              // Only unmount if this block still owns the panel.
              modal.currentWidgetId === String(props.clientId)
            ) {
              modal.unmountEmbedded(props.clientId);
            }
          }

          return function () {
            // On component teardown, only strip the class if no other ReGallery
            // block is selected (avoids stripping class mounted by the new block).
            if (!anotherReacgBlockIsSelected()) {
              if (sidebar) sidebar.classList.remove(SIDEBAR_CLASS);
            }
          };
        },
        [props.isSelected, props.clientId],
      );

      const blockProps = useBlockProps({
        className: "reacg-gutenberg",
        id: "reacg-gutenberg" + props.clientId,
        ref: setBlockRef,
      });

      // Display block preview only on the block inserter hover
      if (isExample) {
        return el(
          "div",
          blockProps,
          el("img", {
            src:
              reacg_gutenberg.plugin_url +
              "/builders/gutenberg/images/preview.png",
            style: { height: "auto", width: "100%" },
          }),
        );
      }

      const loader = el(
        "div",
        { className: "reacg-spinner__wrapper reacg-hidden" },
        el("span", { className: "spinner is-active" }),
      );

      return el(
        "div",
        blockProps,
        loader,
        renderPlaceholder(props, shortcodeId !== 0),
        renderGalleryContainer(shortcodeId, props),
        props.isSelected
          ? el(InspectorControls, {}, renderSidebarPanel(props))
          : null,
      );
    },
    save: function (props) {
      return props.attributes.shortcode;
    },
  });

  // Global capture click listener on document/window for canvas clicks
  if (window.jQuery) {
    window
      .jQuery(document)
      .on("click", ".reacg-builder-open-modal-btn", function (e) {
        e.preventDefault();
        const $placeholder = window
          .jQuery(this)
          .closest(".reacg-builder-placeholder");
        const widgetId =
          $placeholder.attr("data-widget-id") ||
          ($placeholder.closest(".reacg-gutenberg").attr("id") || "").replace(
            "reacg-gutenberg",
            "",
          );

        if (widgetId && window.wp && window.wp.data) {
          try {
            window.wp.data.dispatch("core/block-editor").selectBlock(widgetId);
            const editPost = window.wp.data.dispatch("core/edit-post");
            if (editPost && typeof editPost.openGeneralSidebar === "function") {
              editPost.openGeneralSidebar("edit-post/block");
            }
          } catch (err) {}
        }
      });
  }

  // Auto-close modal when switching away to edit a different block (matching Elementor behavior)
  wp.domReady(function () {
    const { select, subscribe } = wp.data;
    let lastSelectedBlockId = null;

    subscribe(function () {
      const blockEditorStore = select("core/block-editor");
      if (
        !blockEditorStore ||
        typeof blockEditorStore.getSelectedBlock !== "function"
      ) {
        return;
      }

      const selectedBlock = blockEditorStore.getSelectedBlock();
      const selectedBlockId = selectedBlock ? selectedBlock.clientId : null;

      if (selectedBlockId !== lastSelectedBlockId) {
        const prevBlockId = lastSelectedBlockId;
        lastSelectedBlockId = selectedBlockId;
        if (!selectedBlock || selectedBlock.name !== "reacg/gallery") {
          const modal = getBuilderModal();
          if (modal) {
            if (typeof modal.unmountEmbedded === "function") {
              modal.unmountEmbedded(prevBlockId);
            }
            if (modal.isOpen) {
              modal.close();
            }
          }
        }
      }
    });

    // Save gallery options when post is being saved in Gutenberg
    let wasSaving = false;
    subscribe(function () {
      const blockEditorStore = select("core/block-editor");
      if (
        !blockEditorStore ||
        typeof blockEditorStore.getBlocks !== "function"
      ) {
        return;
      }

      const blocks = blockEditorStore.getBlocks();
      const hasGalleryBlock = blocks.some(function (block) {
        return block.name === "reacg/gallery";
      });

      if (!hasGalleryBlock) {
        return;
      }

      const editor = select("core/editor");
      if (!editor || typeof editor.isSavingPost !== "function") {
        return;
      }

      const isSaving = editor.isSavingPost();
      const isAutosaving =
        typeof editor.isAutosavingPost === "function" &&
        editor.isAutosavingPost();

      if (isSaving && !isAutosaving && !wasSaving) {
        wasSaving = true;
        const modal = getBuilderModal();
        if (modal) {
          if (typeof modal.flushAutoSave === "function") {
            modal.flushAutoSave();
          }
          if (typeof modal.triggerReactSave === "function") {
            modal.triggerReactSave();
          }
        }
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
  window.wp.components,
);
