/**
 * ReGallery Divi 5 module (Visual Builder, app window).
 *
 * Registers the module with Divi's module library. The settings field and the
 * canvas open the shared ReacgBuilderModal that lives in the top window; the
 * chosen gallery is written to the module attributes through Divi's edit-post
 * store, which re-renders the canvas preview.
 */
(function () {
  if (!window.divi || !window.divi.moduleLibrary || !window.divi.module || !window.vendor || !window.vendor.wp) {
    return;
  }

  const MODULE_NAME = 'regallery/divi-gallery-selector';

  const metadata = {
    name: MODULE_NAME,
    d4Shortcode: 'reacg_module',
    title: 'Re Gallery',
    titles: 'Galleries',
    moduleIcon: MODULE_NAME,
    category: 'module',
    attributes: {
      module: {
        type: 'object',
        selector: '{{selector}}',
        default: {},
        defaultPrintedStyle: {},
        settings: {
          advanced: { htmlAttributes: {} },
          decoration: {
            animation: {},
            sizing: {},
            spacing: {},
            transform: {},
            conditions: {},
            disabledOn: {},
            position: {},
            scroll: {},
            transition: {},
          },
        },
      },
      galleryId: { type: 'string', default: '0' },
      // Kept for layouts saved by older versions; the floating panel owns the options now.
      enableOptions: { type: 'string', default: 'off' },
    },
    customCssFields: {},
    settings: { design: 'auto', advanced: 'auto' },
  };

  const React = window.React;
  const { useEffect, useRef, useState } = window.vendor.wp.element;
  const { ModuleContainer, FieldContainer, GroupContainer, StyleContainer, CssStyle } = window.divi.module;
  const registerModule = window.divi.moduleLibrary.registerModule;
  const getAttrByMode = window.divi.moduleUtils.getAttrByMode;
  const diviData = window.RegalleryDiviData || window.ReacgDiviData || {};

  const PANEL_CLOSE_DELAY = 250;

  /* ---------------------------------------------------------------- */
  /* Helpers                                                            */
  /* ---------------------------------------------------------------- */

  function text(key, fallback) {
    return diviData[key] ? String(diviData[key]) : fallback;
  }

  function getTopWindow() {
    try {
      return window.top || window;
    } catch (error) {
      return window;
    }
  }

  function getPanel() {
    const topWin = getTopWindow();
    return topWin.ReacgBuilderModal || window.ReacgBuilderModal || null;
  }

  function parseGalleryId(value) {
    const id = parseInt(value, 10);
    return isNaN(id) ? 0 : id;
  }

  function galleryExists(galleryId) {
    if (galleryId === -1) {
      return true;
    }
    const galleries = diviData.galleries || {};
    return Object.prototype.hasOwnProperty.call(galleries, String(galleryId));
  }

  /**
   * Gallery id of the module; a deleted gallery counts as none.
   */
  function getGalleryId(attrs) {
    const galleryId = parseGalleryId(getAttrByMode(attrs && attrs.galleryId));
    return galleryId > 0 && !galleryExists(galleryId) ? 0 : galleryId;
  }

  function getGalleryTitle(galleryId) {
    const galleries = diviData.galleries || {};
    const title = galleries[String(galleryId)];
    return title ? String(title) : '';
  }

  function sanitizeId(value) {
    return String(value || '').replace(/[^a-zA-Z0-9_-]/g, '');
  }

  /**
   * Persist the gallery id on the module. The edit component re-renders and
   * reloads the preview on attribute change.
   */
  function setModuleGalleryId(moduleId, galleryId) {
    window.divi.data.dispatch('divi/edit-post').editModuleAttribute({
      id: moduleId,
      attrName: 'galleryId',
      value: { desktop: { value: String(parseGalleryId(galleryId)) } },
    });
  }

  function openPanel(moduleId, galleryId) {
    const panel = getPanel();
    if (!panel) {
      console.error('ReacgBuilderModal is not loaded in the Divi top window.');
      return;
    }
    panel.open({
      galleryId: parseGalleryId(galleryId),
      widgetId: String(moduleId),
      onSave: function (savedGalleryId) {
        setModuleGalleryId(moduleId, savedGalleryId);
      },
    });
  }

  function closePanelFor(moduleId) {
    const panel = getPanel();
    if (panel && panel.isOpen && !panel.isEmbedded && panel.currentWidgetId === String(moduleId)) {
      panel.close();
    }
  }

  /**
   * Mount a freshly rendered gallery root with the bundle loaded in this window.
   */
  function mountGalleryRoot(root) {
    if (!root || root.childElementCount > 0) {
      return true;
    }
    if (typeof window.reacg_mount_gallery === 'function') {
      window.reacg_mount_gallery(root);
      return true;
    }
    const loadApp = document.getElementById('reacg-loadApp');
    if (loadApp) {
      loadApp.setAttribute('data-id', root.id);
      loadApp.click();
      return true;
    }
    return false;
  }

  /* ---------------------------------------------------------------- */
  /* Settings field (rendered in the top window by Divi)                */
  /* ---------------------------------------------------------------- */

  const mountedFields = {};

  function GalleryPickerField(props) {
    const moduleId = props.moduleId;
    const galleryId = parseGalleryId(props.value);
    const galleryIdRef = useRef(galleryId);
    galleryIdRef.current = galleryId;

    // The settings modal opened for this module: show the panel with it, and
    // hide the panel again when the modal closes. Tab switches re-mount the
    // field, so the close is deferred and cancelled by a re-mount.
    useEffect(
      function () {
        mountedFields[moduleId] = true;
        openPanel(moduleId, galleryIdRef.current);
        return function () {
          delete mountedFields[moduleId];
          window.setTimeout(function () {
            if (!mountedFields[moduleId]) {
              closePanelFor(moduleId);
            }
          }, PANEL_CLOSE_DELAY);
        };
      },
      [moduleId],
    );

    const title = galleryId ? getGalleryTitle(galleryId) || '#' + galleryId : text('noGallery', 'No gallery selected');

    return React.createElement(
      'div',
      { className: 'reacg-divi-field' },
      React.createElement(
        'div',
        { className: 'reacg-divi-field__title' },
        text('selectedGallery', 'Selected gallery:') + ' ',
        React.createElement('strong', null, title),
      ),
      React.createElement(
        'button',
        {
          type: 'button',
          className: 'reacg-divi-field__open',
          onClick: function (event) {
            event.preventDefault();
            openPanel(moduleId, galleryIdRef.current);
          },
        },
        React.createElement(
          'svg',
          { 'aria-hidden': 'true', viewBox: '0 0 20 20', focusable: 'false' },
          React.createElement('path', {
            d: 'M16 4h1.96c.57 0 1.04.47 1.04 1.04v12.92c0 .57-.47 1.04-1.04 1.04H5.04C4.47 19 4 18.53 4 17.96V16H2.04C1.47 16 1 15.53 1 14.96V2.04C1 1.47 1.47 1 2.04 1h12.92c.57 0 1.04.47 1.04 1.04V4zM6 6v11h11V6H6zm1 9l2.5-3 1.5 2 2.5-4L15 15H7zM3 3v11h1V5.04C4 4.47 4.47 4 5.04 4H14V3H3z',
          }),
        ),
        text('selectOrCreate', 'Select or Create Gallery'),
      ),
      React.createElement('p', {
        className: 'reacg-divi-field__desc',
        dangerouslySetInnerHTML: { __html: text('fieldDescription', '') },
      }),
    );
  }

  function SettingsComponent(props) {
    const { attrs } = props;

    return React.createElement(
      GroupContainer,
      {
        attrs: attrs,
        id: 'reacgGallerySettings',
        title: text('settingsGroup', 'Gallery'),
      },
      React.createElement(
        FieldContainer,
        {
          attrs: attrs,
          attrName: 'galleryId',
          label: text('fieldLabel', 'Gallery'),
        },
        React.createElement(GalleryPickerField, null),
      ),
    );
  }

  /* ---------------------------------------------------------------- */
  /* Canvas (edit) component                                            */
  /* ---------------------------------------------------------------- */

  function StylesComponent(props) {
    const { attrs, elements, orderClass, mode, state, noStyleTag } = props;

    return React.createElement(
      StyleContainer,
      { mode: mode, state: state, noStyleTag: noStyleTag },
      elements.style({ attrName: 'module' }),
      React.createElement(CssStyle, {
        selector: orderClass,
        attr: attrs.css || {},
        orderClass: orderClass,
        cssFields: [],
      }),
    );
  }

  function Placeholder(props) {
    return React.createElement(
      'div',
      { className: 'reacg-builder-placeholder', 'data-widget-id': props.moduleId },
      React.createElement(
        'div',
        { className: 'reacg-builder-placeholder-icon' },
        React.createElement('img', { src: (diviData.plugin_url || '') + '/assets/images/icon.svg', alt: text('brand', 'Re Gallery') }),
      ),
      React.createElement('div', { className: 'reacg-builder-placeholder-title' }, text('brand', 'Re Gallery')),
      React.createElement(
        'p',
        { className: 'reacg-builder-placeholder-desc' },
        text('placeholderDesc', 'No gallery selected yet. Select an existing gallery or create a new one.'),
      ),
      React.createElement(
        'button',
        {
          type: 'button',
          className: 'reacg-builder-open-modal-btn button button-primary',
          onClick: function (event) {
            event.preventDefault();
            event.stopPropagation();
            openPanel(props.moduleId, 0);
          },
        },
        React.createElement(
          'svg',
          { className: 'reacg-builder-open-modal-btn__icon', 'aria-hidden': 'true', viewBox: '0 0 20 20', focusable: 'false' },
          React.createElement('path', {
            d: 'M16 4h1.96c.57 0 1.04.47 1.04 1.04v12.92c0 .57-.47 1.04-1.04 1.04H5.04C4.47 19 4 18.53 4 17.96V16H2.04C1.47 16 1 15.53 1 14.96V2.04C1 1.47 1.47 1 2.04 1h12.92c.57 0 1.04.47 1.04 1.04V4zM6 6v11h11V6H6zm1 9l2.5-3 1.5 2 2.5-4L15 15H7zM3 3v11h1V5.04C4 4.47 4.47 4 5.04 4H14V3H3z',
          }),
        ),
        text('selectOrCreate', 'Select or Create Gallery'),
      ),
    );
  }

  function EditComponent(props) {
    const { attrs, elements, id, name } = props;
    const galleryId = getGalleryId(attrs);
    const rootId = 'reacg-root' + sanitizeId(id);
    const previewState = useState({ status: 'idle', galleryId: 0, galleryTimestamp: '', optionsTimestamp: '', pluginVersion: '' });
    const preview = previewState[0];
    const setPreview = previewState[1];

    // Load the gallery data for the canvas preview whenever the gallery changes.
    useEffect(
      function () {
        if (!galleryId) {
          setPreview({ status: 'idle', galleryId: 0, galleryTimestamp: '', optionsTimestamp: '', pluginVersion: '' });
          return undefined;
        }

        const controller = new AbortController();
        const body = new URLSearchParams();
        body.append('action', 'reacg_divi_preview');
        body.append('nonce', diviData.nonce || '');
        body.append('gallery_id', String(galleryId));
        body.append('module_id', String(id || ''));

        setPreview(function (current) {
          return Object.assign({}, current, { status: 'loading' });
        });

        fetch(diviData.ajax_url || '', {
          method: 'POST',
          credentials: 'same-origin',
          cache: 'no-cache',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: body,
          signal: controller.signal,
        })
          .then(function (response) {
            return response.json();
          })
          .then(function (response) {
            if (!response || !response.success || !response.data) {
              throw new Error('preview failed');
            }
            window.reacg_data = window.reacg_data || {};
            window.reacg_data[String(galleryId)] = response.data.galleryData;
            setPreview({
              status: 'ready',
              galleryId: galleryId,
              galleryTimestamp: String(response.data.galleryTimestamp || Date.now()),
              optionsTimestamp: String(response.data.optionsTimestamp || Date.now()),
              pluginVersion: String(response.data.pluginVersion || ''),
            });
          })
          .catch(function () {
            if (!controller.signal.aborted) {
              setPreview({ status: 'error', galleryId: galleryId, galleryTimestamp: '', optionsTimestamp: '', pluginVersion: '' });
            }
          });

        return function () {
          controller.abort();
        };
      },
      [galleryId, id],
    );

    // Mount the root once the preview markup for the current gallery is in the DOM.
    useEffect(
      function () {
        if (preview.status !== 'ready' || !preview.galleryId) {
          return undefined;
        }
        let attempts = 0;
        let timer = null;
        const tryMount = function () {
          const root = document.getElementById(rootId);
          const ready = root && String(root.getAttribute('data-gallery-id')) === String(preview.galleryId);
          if (ready && mountGalleryRoot(root)) {
            return;
          }
          attempts += 1;
          if (attempts < 60) {
            timer = window.setTimeout(tryMount, attempts < 10 ? 100 : 250);
          }
        };
        tryMount();
        return function () {
          if (timer) {
            window.clearTimeout(timer);
          }
        };
      },
      [preview.status, preview.galleryId, rootId],
    );

    let inner;
    if (!galleryId) {
      inner = React.createElement(Placeholder, { moduleId: id });
    } else if (preview.status === 'error') {
      inner = React.createElement('div', { className: 'reacg-divi-error' }, text('errorText', 'Unable to load gallery preview. Please try again.'));
    } else if (preview.status !== 'ready' || preview.galleryId !== galleryId) {
      inner = React.createElement('div', { className: 'reacg-divi-loading' }, text('loadingText', 'Loading gallery preview...'));
    } else {
      inner = React.createElement('div', {
        key: rootId + ':' + preview.galleryId,
        id: rootId,
        className: 'reacg-wrapper reacg-gallery reacg-preview',
        'data-options-section': 0,
        'data-options-container': '#reacg_settings',
        'data-plugin-version': preview.pluginVersion,
        'data-gallery-timestamp': preview.galleryTimestamp,
        'data-options-timestamp': preview.optionsTimestamp,
        'data-widget-id': sanitizeId(id),
        'data-gallery-id': preview.galleryId,
        // Clicking the preview opens the panel instead of the gallery's own handlers.
        onClickCapture: function (event) {
          event.preventDefault();
          event.stopPropagation();
          openPanel(id, galleryId);
        },
      });
    }

    return React.createElement(
      ModuleContainer,
      {
        attrs: attrs,
        elements: elements,
        id: id,
        name: name,
        moduleClassName: 'reacg_divi_gallery',
        stylesComponent: StylesComponent,
      },
      React.createElement('div', { className: 'et_pb_module_inner' }, inner),
    );
  }

  /* ---------------------------------------------------------------- */
  /* Registration                                                       */
  /* ---------------------------------------------------------------- */

  const moduleDefinition = {
    metadata: metadata,
    renderers: {
      edit: EditComponent,
      styles: StylesComponent,
    },
    settings: {
      content: SettingsComponent,
    },
  };

  window.vendor.wp.hooks.addAction('divi.moduleLibrary.registerModuleLibraryStore.after', 'regallery', function () {
    registerModule(moduleDefinition.metadata, moduleDefinition);
  });

  window.vendor.wp.hooks.addFilter('divi.iconLibrary.icon.map', 'regallery', function (icons) {
    return Object.assign({}, icons, {
      [MODULE_NAME]: {
        name: MODULE_NAME,
        viewBox: '0 0 26 28',
        component: function () {
          return React.createElement(
            'svg',
            { xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 26 28' },
            React.createElement('image', {
              href: (diviData.plugin_url || '') + '/assets/images/icon.svg',
              x: '0',
              y: '0',
              width: '26',
              height: '28',
            }),
          );
        },
      },
    });
  });
})();
