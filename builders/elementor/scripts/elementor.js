/**
 * ReGallery Elementor Integration Script
 */
(function ($) {
  'use strict';

  /**
   * Resolve Elementor target model and container from any view, model, or global context.
   * Uses modern container.settings API to prevent elementSettingsModel deprecation warnings.
   *
   * @param {Object} viewOrModel
   * @param {Object} container
   * @returns {{settingsModel: Object|null, container: Object|null}}
   */
  /**
   * Recursively search Elementor collection for a model by ID.
   *
   * @param {string} id
   * @param {Object} collection
   * @returns {Object|null}
   */
  function findElementorModelDeep(id, collection) {
    if (!collection || !id) return null;
    const items = collection.models || (Array.isArray(collection) ? collection : []);
    for (let i = 0; i < items.length; i++) {
      const el = items[i];
      if (!el) continue;
      const elId = el.id || (el.get && el.get('id'));
      if (String(elId) === String(id)) {
        return el;
      }
      const children = (el.get && el.get('elements')) || el.elements;
      if (children) {
        const found = findElementorModelDeep(id, children);
        if (found) return found;
      }
    }
    return null;
  }

  /**
   * Resolve Elementor target model and container from any view, model, or global context.
   * Uses modern container.settings API to prevent elementSettingsModel deprecation warnings.
   *
   * @param {Object} viewOrModel
   * @param {Object} container
   * @returns {{settingsModel: Object|null, container: Object|null}}
   */
  function resolveElementorTarget(viewOrModel, container) {
    let settingsModel = null;
    let widgetContainer = container || null;

    if (viewOrModel) {
      if (viewOrModel.container) {
        widgetContainer = viewOrModel.container;
        if (widgetContainer.settings) {
          settingsModel = widgetContainer.settings;
        }
      }

      if (!settingsModel) {
        if (viewOrModel.settings) {
          settingsModel = viewOrModel.settings;
        } else if (typeof viewOrModel.getSetting === 'function') {
          settingsModel = viewOrModel;
        } else if (viewOrModel.model) {
          if (viewOrModel.model.container && viewOrModel.model.container.settings) {
            settingsModel = viewOrModel.model.container.settings;
            widgetContainer = widgetContainer || viewOrModel.model.container;
          } else if (typeof viewOrModel.model.getSetting === 'function') {
            settingsModel = viewOrModel.model;
          } else {
            settingsModel = viewOrModel.model;
          }
        } else {
          settingsModel = viewOrModel;
        }
      }
    }

    if ((!settingsModel || !widgetContainer) && window.elementor) {
      const widgetId = (viewOrModel && (viewOrModel.id || (viewOrModel.get && viewOrModel.get('id')))) || '';
      if (widgetId && typeof window.elementor.getContainer === 'function') {
        const foundContainer = window.elementor.getContainer(widgetId);
        if (foundContainer) {
          widgetContainer = widgetContainer || foundContainer;
          settingsModel = settingsModel || foundContainer.settings || foundContainer.model;
        }
      }
    }

    if (!settingsModel && window.elementor) {
      if (window.elementor.selection) {
        const selected = window.elementor.selection.getElements();
        if (selected && selected.length) {
          const el = selected[0];
          widgetContainer = widgetContainer || el.container;
          settingsModel = (el.container && el.container.settings) ? el.container.settings : el.model;
        }
      }
      if (!settingsModel && window.elementor.panel && window.elementor.panel.currentView) {
        const currentView = window.elementor.panel.currentView;
        widgetContainer = widgetContainer || currentView.container;
        settingsModel = (currentView.container && currentView.container.settings) ? currentView.container.settings : currentView.model;
      }
    }

    return {
      settingsModel: settingsModel,
      container: widgetContainer
    };
  }

  /**
   * Extract post_id value safely from Elementor model/target.
   *
   * @param {Object} target
   * @returns {number}
   */
  function getPostIdFromTarget(target) {
    if (!target) {
      return 0;
    }
    if (typeof target.getSetting === 'function') {
      return parseInt(target.getSetting('post_id'), 10) || 0;
    }
    if (typeof target.get === 'function') {
      const val = target.get('post_id');
      if (val !== undefined && val !== null && val !== '') {
        return parseInt(val, 10) || 0;
      }
      const settings = target.get('settings');
      if (settings && typeof settings.get === 'function') {
        return parseInt(settings.get('post_id'), 10) || 0;
      }
    }
    if (target.attributes && target.attributes.post_id !== undefined) {
      return parseInt(target.attributes.post_id, 10) || 0;
    }
    return 0;
  }

  /**
   * Set post_id on Elementor target silently to prevent re-render.
   *
   * @param {Object} target
   * @param {Object} container
   * @param {number|string} savedGalleryId
   */
  function setPostIdOnTarget(target, container, savedGalleryId) {
    const idStr = String(savedGalleryId);

    if (container && container.settings) {
      if (typeof container.settings.set === 'function') {
        container.settings.set('post_id', idStr, { silent: true });
      }
      if (container.settings.attributes) {
        container.settings.attributes.post_id = idStr;
      }
    }

    if (target) {
      if (typeof target.set === 'function') {
        target.set('post_id', idStr, { silent: true });
      }
      if (target.attributes) {
        target.attributes.post_id = idStr;
      }
      const settings = target.get && target.get('settings');
      if (settings && typeof settings.set === 'function') {
        settings.set('post_id', idStr, { silent: true });
      }
      if (settings && settings.attributes) {
        settings.attributes.post_id = idStr;
      }
    }

    if (window.elementor && window.elementor.saver && typeof window.elementor.saver.setFlagEditorChange === 'function') {
      window.elementor.saver.setFlagEditorChange(true);
    }
  }

  /**
   * Helper to open the ReGallery modal and sync selection back to Elementor model.
   *
   * @param {Object} viewOrModel Current Elementor view or model
   * @param {Object} container Current Elementor widget container
   */
  function openReGalleryModal(viewOrModel, container) {
    const resolved = resolveElementorTarget(viewOrModel, container);
    const target = resolved.settingsModel;
    const widgetContainer = resolved.container;

    let currentPostId = getPostIdFromTarget(target);
    const widgetId = (widgetContainer && widgetContainer.id) ||
      (viewOrModel && (viewOrModel.id || (viewOrModel.model && viewOrModel.model.id))) ||
      (target && target.id) || '';

    // If DOM already has a non-zero gallery ID rendered for this widget, prioritize it over default 0
    if (widgetId && currentPostId === 0) {
      const canvasDoc = (window.elementor && window.elementor.$preview && window.elementor.$preview[0] && window.elementor.$preview[0].contentDocument) || document;
      const galleryDom = canvasDoc.getElementById('reacg-root' + widgetId) || canvasDoc.querySelector('.elementor-element-' + widgetId + ' .reacg-gallery');
      if (galleryDom) {
        const domGalleryId = parseInt(galleryDom.getAttribute('data-gallery-id'), 10);
        if (!isNaN(domGalleryId) && domGalleryId > 0) {
          currentPostId = domGalleryId;
        }
      }
    }

    const modal = window.top.ReacgBuilderModal || window.ReacgBuilderModal;
    if (!modal) {
      console.error('ReacgBuilderModal is not loaded.');
      return;
    }

    // If modal is already open for this exact widget and gallery, do not re-open/reset
    if (modal.isOpen && modal.currentWidgetId === widgetId && (currentPostId === 0 || modal.currentGalleryId === currentPostId)) {
      return;
    }

    modal.open({
      galleryId: currentPostId,
      widgetId: widgetId,
      onSave: function (savedGalleryId) {
        setPostIdOnTarget(target, widgetContainer, savedGalleryId);
      }
    });
  }

  // Hook into Elementor editor channel for button control event
  if (window.elementor) {
    window.elementor.channels.editor.on('reacg:open_gallery_modal', function (view) {
      openReGalleryModal(view);
    });

    // Auto-open modal whenever ReGallery widget settings are opened (click, drop, navigator selection)
    window.elementor.hooks.addAction('panel/open_editor/widget/reacg-elementor', function (panel, model, view) {
      if (!model) {
        return;
      }
      setTimeout(function () {
        openReGalleryModal(model, view ? view.container : null);
      }, 50);
    });

    // Auto-close modal when switching away to edit a different Elementor widget
    window.elementor.hooks.addAction('panel/open_editor/widget', function (panel, model, view) {
      if (model && model.get && model.get('widgetType') !== 'reacg-elementor') {
        const modal = window.top.ReacgBuilderModal || window.ReacgBuilderModal;
        if (modal && modal.isOpen) {
          modal.close();
        }
      }
    });
  }

  // Hook into placeholder clicks in the editor window
  $(document).on('click', '.reacg-builder-open-modal-btn', function (e) {
    e.preventDefault();
    const $placeholder = $(this).closest('.reacg-builder-placeholder');
    const widgetId = $placeholder.attr('data-widget-id') || $placeholder.closest('.elementor-element').attr('data-id');

    let targetModel = null;
    let targetContainer = null;

    if (window.elementor) {
      targetContainer = typeof window.elementor.getContainer === 'function' ? window.elementor.getContainer(widgetId) : null;
      if (targetContainer) {
        targetModel = targetContainer.model || targetContainer;
      } else if (window.elementor.elements) {
        targetModel = findElementorModelDeep(widgetId, window.elementor.elements);
        targetContainer = targetModel ? targetModel.container : null;
      }
    }

    openReGalleryModal(targetModel, targetContainer);
  });

  // Global capture click listener on window/document in Elementor editor
  // Intercepts clicks on .reacg-gallery at the absolute top of the event pipeline before React synthetic events
  window.addEventListener('click', function (e) {
    const topWin = window.top || window.parent;
    const isEditorActive = Boolean(topWin && (topWin.elementor || topWin.ReacgBuilderModal));
    if (!isEditorActive) {
      return;
    }

    if (topWin.elementor && typeof topWin.elementor.isEditMode === 'function' && !topWin.elementor.isEditMode()) {
      return;
    }
    if (document.body && document.body.classList.contains('elementor-editor-preview')) {
      return;
    }

    const gallery = e.target && e.target.closest && e.target.closest('.reacg-gallery');
    if (!gallery) {
      return;
    }

    // Stop lightbox, modal popups, and links inside React gallery
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();

    const widgetWrapper = gallery.closest('.elementor-element');
    const widgetId = widgetWrapper
      ? (widgetWrapper.getAttribute('data-id') || '')
      : (gallery.id && gallery.id.indexOf('reacg-root') === 0 ? gallery.id.replace('reacg-root', '') : '');

    let currentPostId = parseInt(gallery.getAttribute('data-gallery-id'), 10) || 0;

    const modal = topWin.ReacgBuilderModal || window.ReacgBuilderModal;
    if (modal) {
      modal.open({
        galleryId: currentPostId,
        widgetId: widgetId,
        onSave: function (savedGalleryId) {
          if (topWin.elementor && widgetId) {
            const container = typeof topWin.elementor.getContainer === 'function' ? topWin.elementor.getContainer(widgetId) : null;
            let model = container ? container.model : null;
            if (!model && topWin.elementor.elements) {
              model = findElementorModelDeep(widgetId, topWin.elementor.elements);
            }
            setPostIdOnTarget(model, container || (model && model.container), savedGalleryId);
          }
        }
      });
    }
  }, true);

})(jQuery);
