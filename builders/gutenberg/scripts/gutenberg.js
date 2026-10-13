/**
 * ReGallery Gutenberg Integration Script
 *
 * The block renders a preview gallery in the editor canvas (an iframe in
 * recent WordPress versions). Its settings are the shared ReacgBuilderModal
 * panel embedded in InspectorControls, which mounts the standalone settings
 * app in the editor document. Live option changes reach the canvas preview
 * over postMessage; this script never bridges APIs between documents.
 */
(function (blocks, element, blockEditor, components) {
  "use strict";

  const el = element.createElement;
  const { InspectorControls } = blockEditor;
  const { useEffect, useRef } = element;
  const { PanelBody } = components;

  /* useBlockProps is required for apiVersion 3 in the iframed editor (WP 6.3+). */
  const useBlockProps =
    blockEditor.useBlockProps ||
    function (extra) {
      return extra || {};
    };

  /* Canvas nodes keyed by clientId so sidebar events can reach the iframe block. */
  const blockNodes = {};

  /**
   * Whether the gallery still exists (the block may reference a deleted one).
   */
  function galleryExists(galleryId) {
    if (galleryId === -1) {
      return true;
    }
    let list = [];
    try {
      list = typeof reacg_gutenberg.data === "string" ? JSON.parse(reacg_gutenberg.data) : reacg_gutenberg.data || [];
    } catch (e) {
      list = [];
    }
    return (Array.isArray(list) ? list : Object.values(list)).some(function (g) {
      return parseInt(g.id, 10) === galleryId;
    });
  }

  function getBlockNode(clientId) {
    const stored = blockNodes[clientId];
    if (stored && stored.isConnected) {
      return stored;
    }
    return document.getElementById("reacg-gutenberg" + clientId) || stored || null;
  }

  function getBuilderModal() {
    return window.ReacgBuilderModal || null;
  }

  /**
   * Click the gallery mount button in the same document as the gallery root.
   */
  function triggerLoadApp(loadApp, rootId) {
    loadApp.setAttribute("data-id", rootId);
    try {
      loadApp.click();
    } catch (error) {
      const view = loadApp.ownerDocument && loadApp.ownerDocument.defaultView ? loadApp.ownerDocument.defaultView : window;
      loadApp.dispatchEvent(new MouseEvent("click", { bubbles: true, cancelable: true, view: view }));
    }
  }

  /**
   * (Re)mount the preview gallery of a block in the canvas document.
   */
  function reload_gallery(clientId) {
    const rootId = "reacg-root" + clientId;
    const attempt = function (remaining) {
      const node = getBlockNode(clientId);
      const canvasDoc = (node && node.ownerDocument) || document;
      const canvasWin = canvasDoc.defaultView;
      const root = canvasDoc.getElementById(rootId);

      if (root && canvasWin && typeof canvasWin.reacg_mount_gallery === "function") {
        canvasWin.reacg_mount_gallery(root);
        return;
      }

      const loadApp = canvasDoc.getElementById("reacg-loadApp");
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
   * Select the block and open the block inspector sidebar.
   */
  function focusBlockInSidebar(clientId) {
    if (!window.wp || !window.wp.data || !clientId) {
      return;
    }
    try {
      window.wp.data.dispatch("core/block-editor").selectBlock(clientId);
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

  /**
   * Update the canvas markup for a new gallery id and remount the preview.
   */
  function applyGalleryToCanvas(clientId, galleryId) {
    const node = getBlockNode(clientId);
    if (!node) {
      return;
    }
    const placeholder = node.querySelector(".reacg-builder-placeholder");
    const gallery = node.querySelector(".reacg-gallery");

    if (!galleryId) {
      if (placeholder) placeholder.classList.remove("reacg-hidden");
      if (gallery) {
        gallery.classList.add("reacg-hidden");
        gallery.setAttribute("data-gallery-id", "0");
      }
      return;
    }

    if (placeholder) placeholder.classList.add("reacg-hidden");
    if (gallery) {
      gallery.classList.remove("reacg-hidden");
      gallery.setAttribute("data-gallery-id", String(galleryId));
      gallery.setAttribute("data-options-section", "0");
      gallery.setAttribute("data-plugin-version", reacg_gutenberg.plugin_version);
      gallery.setAttribute("data-gallery-timestamp", String(Date.now()));
      gallery.setAttribute("data-options-timestamp", String(Date.now()));
    }
    reload_gallery(clientId);
  }

  /**
   * Mount the shared panel inside InspectorControls for a block.
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

    const clientId = props.clientId;
    const galleryId = parseInt(props.attributes.shortcode_id, 10) || 0;

    modal.mountEmbedded(containerNode, {
      galleryId: galleryId,
      widgetId: clientId,
      onSave: function (savedGalleryId) {
        const idNum = parseInt(savedGalleryId, 10) || 0;
        const currentAttrId = parseInt(props.attributes.shortcode_id, 10) || 0;
        if (idNum === currentAttrId) {
          return;
        }
        props.setAttributes({
          shortcode_id: idNum,
          shortcode: idNum ? '[REACG id="' + idNum + '"]' : "",
        });
        applyGalleryToCanvas(clientId, idNum);
      },
    });
  }

  function renderPlaceholder(props, isHidden) {
    const focus = function (e) {
      e.preventDefault();
      e.stopPropagation();
      focusBlockInSidebar(props.clientId);
    };
    return el(
      "div",
      {
        className: "reacg-builder-placeholder" + (isHidden ? " reacg-hidden" : ""),
        "data-widget-id": props.clientId,
        onClick: focus,
      },
      el("div", { className: "reacg-builder-placeholder-icon" }, el("img", { src: reacg_gutenberg.icon, alt: reacg_gutenberg.title || "ReGallery" })),
      el("div", { className: "reacg-builder-placeholder-title" }, reacg_gutenberg.title || "ReGallery"),
      el("p", { className: "reacg-builder-placeholder-desc" }, reacg_gutenberg.text_no_gallery_selected || "No gallery selected yet. Select an existing gallery or create a new one."),
      el(
        "button",
        { type: "button", className: "reacg-builder-open-modal-btn button button-primary", onClick: focus },
        el(
          "svg",
          { className: "reacg-builder-open-modal-btn__icon", "aria-hidden": "true", viewBox: "0 0 20 20", focusable: "false" },
          el("path", { d: "M16 4h1.96c.57 0 1.04.47 1.04 1.04v12.92c0 .57-.47 1.04-1.04 1.04H5.04C4.47 19 4 18.53 4 17.96V16H2.04C1.47 16 1 15.53 1 14.96V2.04C1 1.47 1.47 1 2.04 1h12.92c.57 0 1.04.47 1.04 1.04V4zM6 6v11h11V6H6zm1 9l2.5-3 1.5 2 2.5-4L15 15H7zM3 3v11h1V5.04C4 4.47 4.47 4 5.04 4H14V3H3z" }),
        ),
        reacg_gutenberg.text_select_or_create || "Select or Create Gallery",
      ),
    );
  }

  /**
   * Preview container. Controls are never rendered in the canvas
   * (data-options-section is always 0); the sidebar hosts the settings app.
   */
  function renderGalleryContainer(shortcodeId, props) {
    const hasGallery = shortcodeId !== 0;
    return el("div", {
      "data-options-section": 0,
      "data-gallery-id": shortcodeId,
      "data-plugin-version": reacg_gutenberg.plugin_version,
      className: "reacg-wrapper reacg-gallery reacg-preview" + (hasGallery ? "" : " reacg-hidden"),
      id: "reacg-root" + props.clientId,
      onClick: function (e) {
        e.preventDefault();
        e.stopPropagation();
        focusBlockInSidebar(props.clientId);
      },
    });
  }

  function EmbeddedSettingsPanel(componentProps) {
    const containerRef = useRef(null);
    const props = componentProps.props;

    useEffect(
      function () {
        if (containerRef.current) {
          mountEmbeddedSettings(containerRef.current, props);
        }
        return function () {
          const modal = getBuilderModal();
          // Only unmount if this block still owns the panel; another ReGallery
          // block may already have claimed it.
          if (modal && typeof modal.unmountEmbedded === "function" && modal.currentWidgetId === String(props.clientId)) {
            modal.unmountEmbedded();
          }
        };
      },
      [props.clientId],
    );

    return el("div", { className: "reacg-gutenberg-settings-container", ref: containerRef });
  }

  function renderSidebarPanel(props) {
    return el(PanelBody, { initialOpen: true, className: "reacg-gutenberg-panel-body" }, el(EmbeddedSettingsPanel, { props: props }));
  }

  blocks.registerBlockType("reacg/gallery", {
    apiVersion: 3,
    title: reacg_gutenberg.title,
    description: reacg_gutenberg.description,
    icon: el("img", { width: 24, height: 24, src: reacg_gutenberg.icon }),
    category: "common",
    supports: { customClassName: false },
    example: { attributes: { isExample: true } },
    keywords: ["gallery", "photo gallery", "image gallery", "re gallery", "media"],
    attributes: {
      shortcode: { type: "string", value: "" },
      shortcode_id: { type: "int", value: 0 },
      isExample: { type: "boolean", value: false },
    },
    edit: function (props) {
      const blockRef = useRef(null);
      const storedId = parseInt(props.attributes.shortcode_id, 10) || 0;
      const isStale = storedId > 0 && !galleryExists(storedId);
      const shortcodeId = isStale ? 0 : storedId;
      const isExample = !!(props.attributes && props.attributes.isExample);

      // The gallery was deleted: drop the reference so the block becomes a
      // placeholder and the page saves without it.
      useEffect(
        function () {
          if (isStale && !isExample) {
            props.setAttributes({ shortcode_id: 0, shortcode: "" });
          }
        },
        [isStale, isExample],
      );

      const setBlockRef = function (node) {
        if (node) {
          blockRef.current = node;
          blockNodes[props.clientId] = node;
        } else {
          if (blockNodes[props.clientId] === blockRef.current) {
            delete blockNodes[props.clientId];
          }
          blockRef.current = null;
        }
      };

      // Mount the preview when the block appears or its gallery changes.
      useEffect(
        function () {
          if (isExample || !shortcodeId) {
            return;
          }
          const node = blockRef.current || getBlockNode(props.clientId);
          const gallery = node ? node.querySelector(".reacg-gallery") : null;
          if (gallery) {
            gallery.classList.remove("reacg-hidden");
            gallery.setAttribute("data-options-section", "0");
            gallery.setAttribute("data-gallery-id", String(shortcodeId));
            gallery.setAttribute("data-plugin-version", reacg_gutenberg.plugin_version);
            if (gallery.children.length > 0) {
              return; // Already rendered for this gallery.
            }
          }
          reload_gallery(props.clientId);
        },
        [shortcodeId, props.clientId, isExample],
      );

      // Hide the block card while a ReGallery block is selected; the panel has its own header.
      useEffect(
        function () {
          const SIDEBAR_CLASS = "reacg-gutenberg-sidebar-active";
          const sidebar =
            document.querySelector(".interface-interface-skeleton__sidebar") ||
            document.querySelector(".edit-post-sidebar") ||
            document.querySelector('[role="complementary"]');

          function anotherReacgBlockIsSelected() {
            try {
              const sel = window.wp.data.select("core/block-editor").getSelectedBlock();
              return sel && sel.name === "reacg/gallery" && sel.clientId !== props.clientId;
            } catch (e) {
              return false;
            }
          }

          if (props.isSelected) {
            if (sidebar) sidebar.classList.add(SIDEBAR_CLASS);
          } else if (!anotherReacgBlockIsSelected()) {
            if (sidebar) sidebar.classList.remove(SIDEBAR_CLASS);
          }

          return function () {
            if (!anotherReacgBlockIsSelected() && sidebar) {
              sidebar.classList.remove(SIDEBAR_CLASS);
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

      if (isExample) {
        return el(
          "div",
          blockProps,
          el("img", { src: reacg_gutenberg.plugin_url + "/builders/gutenberg/images/preview.png", style: { height: "auto", width: "100%" } }),
        );
      }

      return el(
        "div",
        blockProps,
        renderPlaceholder(props, shortcodeId !== 0),
        renderGalleryContainer(shortcodeId, props),
        props.isSelected ? el(InspectorControls, {}, renderSidebarPanel(props)) : null,
      );
    },
    save: function (props) {
      return props.attributes.shortcode;
    },
  });

  wp.domReady(function () {
    const { select, subscribe } = wp.data;

    // Release the panel when selection leaves ReGallery blocks.
    let lastSelectedBlockId = null;
    subscribe(function () {
      const store = select("core/block-editor");
      if (!store || typeof store.getSelectedBlock !== "function") {
        return;
      }
      const selectedBlock = store.getSelectedBlock();
      const selectedId = selectedBlock ? selectedBlock.clientId : null;
      if (selectedId === lastSelectedBlockId) {
        return;
      }
      lastSelectedBlockId = selectedId;

      if (!selectedBlock || selectedBlock.name !== "reacg/gallery") {
        const modal = getBuilderModal();
        if (modal && modal.isOpen && modal.isEmbedded) {
          modal.unmountEmbedded();
        }
      }
    });

    // Flush unsaved gallery options when the post is saved.
    let wasSaving = false;
    subscribe(function () {
      const editor = select("core/editor");
      if (!editor || typeof editor.isSavingPost !== "function") {
        return;
      }
      const isSaving = editor.isSavingPost();
      const isAutosaving = typeof editor.isAutosavingPost === "function" && editor.isAutosavingPost();

      if (isSaving && !isAutosaving && !wasSaving) {
        wasSaving = true;
        const modal = getBuilderModal();
        if (modal && modal.isOpen && typeof modal.flushAutoSave === "function") {
          modal.flushAutoSave();
        }
      }
      if (!isSaving) {
        wasSaving = false;
      }
    });
  });
})(window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components);
