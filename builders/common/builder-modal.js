/**
 * ReGallery Unified Floating Options Panel.
 *
 * A builder-agnostic panel hosted in the builder's top document. It offers
 * gallery selection/creation, the WordPress media section and the gallery
 * settings UI. The settings UI is the standalone settings app from the
 * gallery bundle (window.reacg_mount_settings); the preview gallery lives
 * wherever the builder renders it (usually a canvas iframe) and receives live
 * option changes from the settings app over postMessage. This script never
 * touches the canvas document.
 *
 * Host integration contract (see builders/<builder>/...):
 *   ReacgBuilderModal.open({galleryId, widgetId, onSave, onClose})
 *   ReacgBuilderModal.mountEmbedded(container, {galleryId, widgetId, onSave})
 *   ReacgBuilderModal.unmountEmbedded()
 *   ReacgBuilderModal.close()
 *   ReacgBuilderModal.flushAutoSave()  -> Promise
 */
(function ($) {
  "use strict";

  const AUTO_SAVE_DELAY = 600;
  const MOUNT_RETRY_DELAY = 100;
  const MOUNT_RETRIES = 50;

  /**
   * Localized data passed from PHP.
   *
   * @returns {Object}
   */
  function getData() {
    if (typeof reacg_builder_data !== "undefined") {
      return reacg_builder_data;
    }
    return {};
  }

  /**
   * Whether the gallery is in the list the builder was given (deleted
   * galleries are not). -1 is the dynamic "All images" gallery.
   *
   * @param {number} galleryId
   * @returns {boolean}
   */
  function galleryExists(galleryId) {
    if (galleryId === -1) {
      return true;
    }
    const galleries = getData().galleries || {};
    if (Array.isArray(galleries)) {
      return galleries.some(function (g) {
        return parseInt(g.id, 10) === galleryId;
      });
    }
    return Object.prototype.hasOwnProperty.call(galleries, String(galleryId));
  }

  /**
   * Translated UI string with fallback.
   *
   * @param {string} key
   * @param {string} fallback
   * @returns {string}
   */
  function text(key, fallback) {
    const data = getData();
    return data[key] ? String(data[key]) : fallback;
  }

  /**
   * Escape a string for safe insertion into HTML.
   *
   * @param {*} value
   * @returns {string}
   */
  function escapeHtml(value) {
    return String(value === null || value === undefined ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  /**
   * Resolve admin-ajax URL (already carrying the plugin nonce).
   *
   * @returns {string}
   */
  function getAjaxUrl() {
    const data = getData();
    if (data.ajax_url) {
      return data.ajax_url;
    }
    if (typeof reacg !== "undefined" && reacg.ajax_url) {
      return reacg.ajax_url;
    }
    if (typeof window.ajaxurl === "string") {
      return window.ajaxurl;
    }
    return "/wp-admin/admin-ajax.php";
  }

  /**
   * Programmatic access to the mounted settings app for a gallery.
   *
   * @param {number|string} galleryId
   * @returns {Object|null}
   */
  function getSettingsApi(galleryId) {
    const api = window.reacg_global && window.reacg_global.settings_api;
    return (api && api[String(galleryId)]) || null;
  }

  const ReacgBuilderModal = {
    isOpen: false,
    isEmbedded: false,
    isInitialized: false,
    currentGalleryId: 0,
    currentWidgetId: "",
    onSaveCallback: null,
    onCloseCallback: null,
    autoSaveTimer: null,
    pendingSave: false,
    _saveStatusTimeout: null,
    _mountRetryTimer: null,
    _lastLoadedImagesGalleryId: null,

    init: function () {
      const backdrop = document.getElementById("reacg-builder-panel-backdrop");
      const hasDom = !!(backdrop && backdrop.querySelector("#reacg_settings"));
      if (this.isInitialized && hasDom) {
        return;
      }
      if (this.isInitialized) {
        // The host removed or emptied the panel DOM after load (Divi 4 moves
        // the page DOM into its app frame at boot). Events are delegated on
        // the document, so only the markup and the drag handler are rebuilt.
        if (backdrop) {
          backdrop.remove();
        }
        this.buildDom();
        this.initDraggable();
        return;
      }
      this.buildDom();
      this.bindEvents();
      this.initDraggable();
      this.bindOptionsChange();
      this.isInitialized = true;
    },

    /* ------------------------------------------------------------------ */
    /* DOM                                                                 */
    /* ------------------------------------------------------------------ */

    buildDom: function () {
      if ($("#reacg-builder-panel-backdrop").length) {
        return;
      }

      const data = getData();
      const iconUrl = (data.plugin_url || "") + "/assets/images/icon.svg";
      const brand = escapeHtml(text("text_brand", "Re Gallery"));

      const html = `
        <div id="reacg-builder-panel-backdrop" class="reacg-builder-panel-backdrop reacg-hidden et_fb_ignore_iframe">
          <div id="reacg-builder-panel" class="reacg-builder-panel">
            <div class="reacg-bp-spinner-wrap reacg-hidden">
              <span class="spinner is-active"></span>
            </div>

            <div class="reacg-bp-header" title="${escapeHtml(text("text_drag_to_move", "Drag to move panel"))}">
              <div class="reacg-bp-brand">
                <span class="reacg-bp-drag-handle">
                  <span class="dashicons dashicons-move"></span>
                </span>
                <img src="${escapeHtml(iconUrl)}" alt="${brand}" />
                <span>${brand}</span>
              </div>
              <div class="reacg-bp-header-actions">
                <span class="reacg-bp-save-status reacg-hidden"></span>
                <span class="reacg-bp-close-btn" role="button" tabindex="0" aria-label="${escapeHtml(text("text_close", "Close"))}">
                  <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M19 6.41 17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"></path></svg>
                </span>
              </div>
            </div>

            <div class="reacg-bp-select-bar">
              <select id="reacg-bp-gallery-select" class="reacg-bp-gallery-select" aria-label="${escapeHtml(text("text_select_gallery", "Select gallery"))}"></select>
              <button type="button" class="reacg-bp-btn-new-gallery button button-secondary" title="${escapeHtml(text("text_create_new", "Create New Gallery"))}">${escapeHtml(text("text_new_gallery", "+ New"))}</button>
            </div>

            <div class="reacg-bp-create-form reacg-hidden">
              <input type="text" class="reacg-bp-new-title" placeholder="${escapeHtml(text("text_enter_title", "Gallery title..."))}" />
              <button type="button" class="reacg-bp-btn-create-submit button button-primary">${escapeHtml(text("text_create", "Create"))}</button>
              <button type="button" class="reacg-bp-btn-create-cancel button button-secondary">${escapeHtml(text("text_cancel", "Cancel"))}</button>
            </div>

            <div class="reacg-bp-body">
              <div class="reacg-bp-empty-state reacg-hidden">
                <p class="reacg-bp-empty-desc">${text("text_empty_state", "No gallery selected. Choose an existing gallery above or click <strong>+ New</strong> to create one.")}</p>
              </div>

              <div class="reacg-bp-images-section">
                <h3 class="reacg-gallery-images-section-title">${escapeHtml(text("text_media", "Media"))}</h3>
                <div id="reacg-gallery-images"></div>
              </div>

              <div class="reacg-bp-settings-section">
                <h3 class="reacg-gallery-settings-section-title">${escapeHtml(text("text_settings", "Settings"))}</h3>
                <div id="reacg_settings" class="reacg-wrapper reacg-settings-app"></div>
              </div>
            </div>
          </div>
        </div>
      `;

      $(document.body).append(html);
    },

    getSettingsRoot: function () {
      return document.getElementById("reacg_settings");
    },

    /* ------------------------------------------------------------------ */
    /* Dragging                                                            */
    /* ------------------------------------------------------------------ */

    initDraggable: function () {
      const $panel = $("#reacg-builder-panel");
      const $header = $panel.find(".reacg-bp-header");

      let isDragging = false;
      let startX = 0;
      let startY = 0;
      let initialLeft = 0;
      let initialTop = 0;

      const pointer = function (e) {
        const touch = e.originalEvent && e.originalEvent.touches && e.originalEvent.touches[0];
        return {
          x: touch ? touch.clientX : e.clientX,
          y: touch ? touch.clientY : e.clientY,
        };
      };

      $header.on("mousedown touchstart", function (e) {
        if (ReacgBuilderModal.isEmbedded) {
          return;
        }
        if ($(e.target).closest("button, input, select, a, .reacg-bp-close-btn").length) {
          return;
        }

        isDragging = true;
        $panel.addClass("reacg-is-dragging");

        const p = pointer(e);
        startX = p.x;
        startY = p.y;

        const rect = $panel.get(0).getBoundingClientRect();
        initialLeft = rect.left;
        initialTop = rect.top;

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

        const p = pointer(e);
        let newLeft = initialLeft + (p.x - startX);
        let newTop = initialTop + (p.y - startY);

        const panelWidth = $panel.outerWidth();
        const panelHeight = $panel.outerHeight();
        const maxLeft = Math.max(10, $(window).width() - panelWidth - 10);
        const maxTop = Math.max(10, $(window).height() - panelHeight - 10);

        newLeft = Math.min(Math.max(newLeft, 10), maxLeft);
        newTop = Math.min(Math.max(newTop, 10), maxTop);

        $panel.css({ left: newLeft + "px", top: newTop + "px" });
      });

      $(document).on("mouseup.reacgDrag touchend.reacgDrag", function () {
        if (isDragging) {
          isDragging = false;
          $panel.removeClass("reacg-is-dragging");
        }
      });
    },

    /* ------------------------------------------------------------------ */
    /* Events                                                              */
    /* ------------------------------------------------------------------ */

    bindEvents: function () {
      const self = this;

      $(document).on("click keydown", ".reacg-bp-close-btn", function (e) {
        if (e.type === "keydown" && e.key !== "Enter" && e.key !== " ") {
          return;
        }
        e.preventDefault();
        self.close();
      });

      // Visual feedback when media images are saved via AJAX (admin.js).
      $(document).ajaxSend(function (e, xhr, settings) {
        if (self.isOpen && settings && typeof settings.data === "string" && settings.data.indexOf("reacg_save_images") !== -1) {
          self.updateSaveStatus("saving");
        }
      });
      $(document).ajaxSuccess(function (e, xhr, settings) {
        if (self.isOpen && settings && typeof settings.data === "string" && settings.data.indexOf("reacg_save_images") !== -1) {
          self.updateSaveStatus("saved");
        }
      });

      $(document).on("change", "#reacg-bp-gallery-select", function () {
        const selectedId = parseInt($(this).val(), 10);
        if (!isNaN(selectedId) && selectedId !== self.currentGalleryId) {
          self.selectGallery(selectedId);
        }
      });

      $(document).on("click", ".reacg-bp-btn-new-gallery", function (e) {
        e.preventDefault();
        $(".reacg-bp-create-form").removeClass("reacg-hidden");
        $(".reacg-bp-new-title").val("").trigger("focus");
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

      window.addEventListener("reacg:images-updated", function (e) {
        if (
          self.isOpen &&
          e &&
          e.detail &&
          String(e.detail.galleryId) === String(self.currentGalleryId)
        ) {
          self._lastLoadedImagesGalleryId = null;
          self.loadImages(self.currentGalleryId);
          self.updateSaveStatus("saved");
        }
      });
    },

    /**
     * The settings app reports every option change through
     * window.reacg_global.onOptionsChange. Use it to drive auto-save.
     */
    bindOptionsChange: function () {
      const self = this;
      window.reacg_global = window.reacg_global || {};
      const previous = window.reacg_global.onOptionsChange;

      window.reacg_global.onOptionsChange = function (options, context) {
        if (typeof previous === "function") {
          try {
            previous(options, context);
          } catch (e) {}
        }

        if (!self.isOpen || !context || String(context.galleryId) !== String(self.currentGalleryId)) {
          return;
        }

        if (context.hasChanges) {
          self.scheduleAutoSave();
        } else if (self.pendingSave || self.autoSaveTimer) {
          // Options were saved (by the app's own Save button or by Reset).
          self.pendingSave = false;
          if (self.autoSaveTimer) {
            clearTimeout(self.autoSaveTimer);
            self.autoSaveTimer = null;
          }
          self.updateSaveStatus("saved");
        }
      };
    },

    /* ------------------------------------------------------------------ */
    /* Galleries                                                           */
    /* ------------------------------------------------------------------ */

    populateGalleriesDropdown: function (selectedId) {
      const $select = $("#reacg-bp-gallery-select");
      const galleries = getData().galleries || {};
      $select.empty();

      $select.append($("<option>", { value: 0, text: text("text_select_gallery", "Select gallery") }));

      const append = function (id, title) {
        if (parseInt(id, 10) === 0) {
          return;
        }
        $select.append($("<option>", { value: id, text: title || text("text_no_title", "(no title)") }));
      };

      if (Array.isArray(galleries)) {
        galleries.forEach(function (g) {
          append(g.id, g.title);
        });
      } else if (typeof galleries === "object") {
        Object.keys(galleries).forEach(function (id) {
          append(id, galleries[id]);
        });
      }

      const target = selectedId !== undefined && selectedId !== null ? selectedId : 0;
      $select.val($select.find('option[value="' + target + '"]').length ? target : 0);
    },

    handleCreateGallery: function () {
      const self = this;
      const title = $(".reacg-bp-new-title").val().trim() || text("text_no_title", "(no title)");
      const data = getData();

      $(".reacg-bp-spinner-wrap").removeClass("reacg-hidden");

      fetch(getAjaxUrl() + "&action=reacg_save_gallery&gallery_title=" + encodeURIComponent(title), {
        credentials: "same-origin",
      })
        .then(function (res) {
          return res.json();
        })
        .then(function (newId) {
          const galleryId = parseInt(newId, 10);
          $(".reacg-bp-spinner-wrap").addClass("reacg-hidden");
          if (!(galleryId > 0)) {
            window.alert(text("text_create_failed", "Unable to create gallery."));
            return;
          }

          if (Array.isArray(data.galleries)) {
            data.galleries.push({ id: galleryId, title: title });
          } else if (data.galleries && typeof data.galleries === "object") {
            data.galleries[galleryId] = title;
          }

          $("#reacg-bp-gallery-select").append($("<option>", { value: galleryId, text: title })).val(galleryId);
          $(".reacg-bp-create-form").addClass("reacg-hidden");
          self.selectGallery(galleryId);
        })
        .catch(function (err) {
          $(".reacg-bp-spinner-wrap").addClass("reacg-hidden");
          console.error("ReGallery: error creating gallery", err);
        });
    },

    /**
     * User picked another gallery for the current widget.
     *
     * @param {number} galleryId
     */
    selectGallery: function (galleryId) {
      const self = this;
      this.flushAutoSave().then(function () {
        self.loadGallery(galleryId);
        if (typeof self.onSaveCallback === "function") {
          self.onSaveCallback(self.currentGalleryId, self.currentWidgetId);
        }
      });
    },

    /**
     * Show a gallery in the panel: media section + settings app.
     *
     * @param {number} galleryId
     */
    loadGallery: function (galleryId) {
      this.currentGalleryId = parseInt(galleryId, 10) || 0;
      $("#reacg-bp-gallery-select").val(this.currentGalleryId);
      $(".reacg-bp-create-form").addClass("reacg-hidden");
      this.updateSaveStatus("");

      if (this.currentGalleryId <= 0) {
        // 0: nothing selected. -1: dynamic "All images" gallery (no manual media).
        $(".reacg-bp-empty-state").toggleClass("reacg-hidden", this.currentGalleryId !== 0);
        $(".reacg-bp-images-section").addClass("reacg-hidden");
        $(".reacg-bp-settings-section").toggleClass("reacg-hidden", this.currentGalleryId === 0);
        $("#reacg-gallery-images").empty();
        this._lastLoadedImagesGalleryId = null;
        if (this.currentGalleryId === 0) {
          this.unmountSettings();
        } else {
          this.mountSettings(this.currentGalleryId);
        }
        return;
      }

      $(".reacg-bp-empty-state").addClass("reacg-hidden");
      $(".reacg-bp-images-section").removeClass("reacg-hidden");
      $(".reacg-bp-settings-section").removeClass("reacg-hidden");

      this.mountSettings(this.currentGalleryId);
      this.loadImages(this.currentGalleryId);
    },

    loadImages: function (galleryId) {
      const self = this;
      const $container = $("#reacg-gallery-images");

      if (self._lastLoadedImagesGalleryId === galleryId && $container.children().length) {
        return;
      }

      $container.empty();
      $(".reacg-bp-spinner-wrap").removeClass("reacg-hidden");

      const ajaxUrl = getAjaxUrl();
      const data = getData();
      const separator = ajaxUrl.indexOf("?") === -1 ? "?" : "&";
      const nonce = ajaxUrl.indexOf("reacg_nonce") === -1 && data.nonce ? "&reacg_nonce=" + encodeURIComponent(data.nonce) : "";

      fetch(ajaxUrl + separator + "action=reacg_get_images&id=" + galleryId + nonce, { credentials: "same-origin" })
        .then(function (res) {
          return res.text();
        })
        .then(function (body) {
          if (self.currentGalleryId !== galleryId) {
            return; // User switched gallery while loading.
          }
          let html = body;
          try {
            const parsed = JSON.parse(body);
            if (typeof parsed === "string") {
              html = parsed;
            }
          } catch (e) {}

          $container.html(html);
          const raw = $container.get(0);
          if (typeof window.reacg_make_items_sortable === "function") {
            window.reacg_make_items_sortable(raw);
          }
          if (typeof window.reacg_update_item_image_fit === "function") {
            $container.find(".reacg_item_image img").each(function () {
              window.reacg_update_item_image_fit(this);
            });
          }
          self._lastLoadedImagesGalleryId = galleryId;
        })
        .catch(function (err) {
          console.error("ReGallery: error loading gallery images", err);
        })
        .then(function () {
          $(".reacg-bp-spinner-wrap").addClass("reacg-hidden");
        });
    },

    /* ------------------------------------------------------------------ */
    /* Settings app                                                        */
    /* ------------------------------------------------------------------ */

    /**
     * Mount the standalone settings app for a gallery into the panel.
     * Retries briefly while the gallery bundle is still loading.
     *
     * @param {number} galleryId
     */
    mountSettings: function (galleryId) {
      const self = this;
      const root = this.getSettingsRoot();
      if (!root) {
        return;
      }

      if (this._mountRetryTimer) {
        clearInterval(this._mountRetryTimer);
        this._mountRetryTimer = null;
      }

      const mounted = root.getAttribute("data-gallery-id");
      if (String(mounted) === String(galleryId) && root.hasChildNodes()) {
        return;
      }

      this.unmountSettings();
      root.setAttribute("data-gallery-id", String(galleryId));
      root.setAttribute("data-plugin-version", getData().version || "");
      root.setAttribute("data-options-timestamp", String(Date.now()));

      const attempt = function () {
        if (typeof window.reacg_mount_settings !== "function") {
          return false;
        }
        // The bundle auto-mounts `.reacg-settings-app` roots on load; do not
        // mount twice when it got there first.
        const alreadyMounted =
          root.hasChildNodes() && root.getAttribute("data-gallery-id") === String(galleryId);
        if (!alreadyMounted && self.currentGalleryId === galleryId && root.isConnected) {
          window.reacg_mount_settings(root);
        }
        return true;
      };

      if (attempt()) {
        return;
      }

      let retries = 0;
      this._mountRetryTimer = setInterval(function () {
        retries++;
        if (attempt() || retries >= MOUNT_RETRIES) {
          clearInterval(self._mountRetryTimer);
          self._mountRetryTimer = null;
          if (retries >= MOUNT_RETRIES) {
            console.error("ReGallery: gallery bundle did not load, settings cannot be mounted.");
          }
        }
      }, MOUNT_RETRY_DELAY);
    },

    unmountSettings: function () {
      const root = this.getSettingsRoot();
      if (this._mountRetryTimer) {
        clearInterval(this._mountRetryTimer);
        this._mountRetryTimer = null;
      }
      if (!root) {
        return;
      }
      if (typeof window.reacg_unmount_settings === "function") {
        window.reacg_unmount_settings(root);
      }
      root.removeAttribute("data-gallery-id");
      root.innerHTML = "";
    },

    /* ------------------------------------------------------------------ */
    /* Saving                                                              */
    /* ------------------------------------------------------------------ */

    updateSaveStatus: function (status) {
      const $status = $(".reacg-bp-save-status");
      if (!$status.length) return;

      if (this._saveStatusTimeout) {
        clearTimeout(this._saveStatusTimeout);
        this._saveStatusTimeout = null;
      }

      if (status === "saving") {
        $status
          .stop(true, true)
          .css("display", "")
          .removeClass("reacg-hidden reacg-status-saved")
          .addClass("reacg-status-saving")
          .html('<span class="spinner is-active" style="margin:0 4px 0 0;"></span>' + escapeHtml(text("text_saving", "Saving...")));
      } else if (status === "saved") {
        $status
          .stop(true, true)
          .css("display", "")
          .removeClass("reacg-hidden reacg-status-saving")
          .addClass("reacg-status-saved")
          .html('<span class="dashicons dashicons-yes" style="vertical-align:middle;"></span>' + escapeHtml(text("text_saved", "Saved")));

        this._saveStatusTimeout = setTimeout(function () {
          $status.fadeOut(300, function () {
            $(this).addClass("reacg-hidden").css("display", "");
          });
        }, 2000);
      } else {
        $status.stop(true, true).addClass("reacg-hidden").css("display", "").empty();
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
        self.saveSettings();
      }, AUTO_SAVE_DELAY);
    },

    /**
     * Save pending option changes through the settings app itself so its
     * state (hasChanges, saved snapshot) stays consistent.
     *
     * @returns {Promise<boolean>}
     */
    saveSettings: function () {
      const self = this;
      const api = getSettingsApi(this.currentGalleryId);
      self.pendingSave = false;

      if (!api || !api.hasChanges()) {
        return Promise.resolve(true);
      }

      self.updateSaveStatus("saving");
      return Promise.resolve(api.save({ silent: true })).then(
        function (ok) {
          self.updateSaveStatus(ok ? "saved" : "");
          return !!ok;
        },
        function (err) {
          console.error("ReGallery: auto-save failed", err);
          self.updateSaveStatus("");
          return false;
        }
      );
    },

    /**
     * @returns {Promise<boolean>}
     */
    flushAutoSave: function () {
      if (this.autoSaveTimer) {
        clearTimeout(this.autoSaveTimer);
        this.autoSaveTimer = null;
      }
      const api = getSettingsApi(this.currentGalleryId);
      if (this.pendingSave || (api && api.hasChanges())) {
        return this.saveSettings();
      }
      return Promise.resolve(true);
    },

    /* ------------------------------------------------------------------ */
    /* Open / close                                                        */
    /* ------------------------------------------------------------------ */

    _applyOptions: function (options) {
      options = options || {};
      this.currentWidgetId = options.widgetId !== undefined && options.widgetId !== null ? String(options.widgetId) : "";
      this.onSaveCallback = options.onSave || null;
      this.onCloseCallback = options.onClose || null;

      let galleryId = options.galleryId !== undefined ? parseInt(options.galleryId, 10) : 0;
      galleryId = isNaN(galleryId) ? 0 : galleryId;

      // A gallery that was deleted meanwhile: behave as "no gallery" and let
      // the builder drop the stale reference from its model.
      this._staleGallery = galleryId > 0 && !galleryExists(galleryId);
      return this._staleGallery ? 0 : galleryId;
    },

    _dropStaleGallery: function () {
      if (!this._staleGallery) {
        return;
      }
      this._staleGallery = false;
      if (typeof this.onSaveCallback === "function") {
        this.onSaveCallback(0, this.currentWidgetId);
      }
    },

    /**
     * Open as a floating panel.
     */
    open: function (options) {
      this.init();

      const $panel = $("#reacg-builder-panel");
      const $backdrop = $("#reacg-builder-panel-backdrop");

      if (this.isEmbedded) {
        this.isEmbedded = false;
        $panel.removeClass("reacg-builder-panel-embedded");
        $backdrop.removeClass("reacg-embedded-mode");
      }
      if ($panel.parent().get(0) !== $backdrop.get(0)) {
        $backdrop.append($panel);
      }

      const sameWidget = this.isOpen && options && String(options.widgetId || "") === this.currentWidgetId;
      const galleryId = this._applyOptions(options);

      this.populateGalleriesDropdown(galleryId);
      $backdrop.removeClass("reacg-hidden");
      this.isOpen = true;
      this._dropStaleGallery();

      // Re-opening for the same widget must not remount (and flash) the settings.
      if (sameWidget && galleryId === this.currentGalleryId) {
        return;
      }
      this.loadGallery(galleryId);
    },

    /**
     * Mount the panel inline inside a host container (e.g. Gutenberg sidebar).
     */
    mountEmbedded: function (container, options) {
      this.init();

      const $panel = $("#reacg-builder-panel");
      const $backdrop = $("#reacg-builder-panel-backdrop");
      const domContainer = container && container.jquery ? container.get(0) : container;

      this.isEmbedded = true;
      $backdrop.addClass("reacg-embedded-mode reacg-hidden");
      $panel.addClass("reacg-builder-panel-embedded").removeClass("reacg-hidden");

      if (domContainer && $panel.parent().get(0) !== domContainer) {
        $(domContainer).empty().append($panel);
      }

      const sameWidget = this.isOpen && options && String(options.widgetId || "") === this.currentWidgetId;
      const galleryId = this._applyOptions(options);
      this._dropStaleGallery();

      this.populateGalleriesDropdown(galleryId);
      this.isOpen = true;

      if (sameWidget && galleryId === this.currentGalleryId) {
        return;
      }
      this.loadGallery(galleryId);
    },

    /**
     * Detach the embedded panel and park it back in its hidden backdrop.
     */
    unmountEmbedded: function () {
      const self = this;
      this.flushAutoSave();
      this.isEmbedded = false;
      this.isOpen = false;

      const $panel = $("#reacg-builder-panel");
      const $backdrop = $("#reacg-builder-panel-backdrop");

      if ($backdrop.length && $panel.length && $panel.parent().get(0) !== $backdrop.get(0)) {
        $backdrop.append($panel);
      }
      $backdrop.addClass("reacg-hidden").removeClass("reacg-embedded-mode");
      $panel.removeClass("reacg-builder-panel-embedded");

      self.unmountSettings();
      self.currentWidgetId = "";
      self.currentGalleryId = 0;
      self._lastLoadedImagesGalleryId = null;
    },

    close: function () {
      if (!this.isOpen) {
        return;
      }
      this.flushAutoSave();

      if (!this.isEmbedded) {
        $("#reacg-builder-panel-backdrop").addClass("reacg-hidden");
      }
      $(".reacg-bp-create-form").addClass("reacg-hidden");
      this.isOpen = false;

      this.unmountSettings();
      this.currentWidgetId = "";
      this.currentGalleryId = 0;
      this._lastLoadedImagesGalleryId = null;
      $("#reacg-gallery-images").empty();

      const onClose = this.onCloseCallback;
      this.onCloseCallback = null;
      this.onSaveCallback = null;
      if (typeof onClose === "function") {
        onClose();
      }
    },
  };

  // The panel is owned by the top document. Canvas iframes may also load
  // this script (shared asset enqueues); they must defer to the top owner.
  try {
    if (window.top === window || !window.top.ReacgBuilderModal) {
      window.ReacgBuilderModal = ReacgBuilderModal;
    } else {
      window.ReacgBuilderModal = window.top.ReacgBuilderModal;
    }
  } catch (e) {
    window.ReacgBuilderModal = ReacgBuilderModal;
  }

  $(function () {
    if (window.ReacgBuilderModal === ReacgBuilderModal) {
      ReacgBuilderModal.init();
    }
  });
})(jQuery);
