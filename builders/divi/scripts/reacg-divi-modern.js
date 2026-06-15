(function () {
  const metadata = {
    name: 'regallery/divi-gallery-selector',
    d4Shortcode: 'reacg_module',
    title: 'Re Gallery',
    titles: 'Galleries',
    moduleIcon: 'regallery/divi-gallery-selector',
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
      enableOptions: { type: 'string', default: 'off' },
    },
    customCssFields: {},
    settings: { design: 'auto', advanced: 'auto' },
  };

  const { useEffect, useLayoutEffect, useState, useRef } = window.vendor.wp.element;
  const { ModuleContainer, FieldContainer, GroupContainer, StyleContainer } = window.divi.module;
  const { CssStyle } = window.divi.module;
  const registerModule = window.divi.moduleLibrary.registerModule;
  const getAttrByMode = window.divi.moduleUtils.getAttrByMode;
  const { Select, ToggleContainer } = window.divi.fieldLibrary;
  const diviData =
    window.ReacgDiviData ||
    window.RegalleryDiviData ||
    window.RegalleryDivi ||
    window.regalleryDiviData ||
    {};

  function getSettings() {
    const galleries = diviData.galleries || diviData.galleryOptions || {};

    return {
      galleries: Object.fromEntries(
        Object.entries(galleries).map(function (entry) {
          const id = entry[0];
          const label = entry[1];

          return [
            id,
            typeof label === 'string'
              ? { label: label }
              : label && typeof label === 'object' && label.label
                ? label
                : { label: String(label || '') },
          ];
        })
      ),
      settingsGroup: {
        label: (diviData.settingsGroup && diviData.settingsGroup.label) || 'Gallery Settings',
      },
      plugin_url: diviData.plugin_url || '',
      galleryId: {
        placeholder: (diviData.galleryId && diviData.galleryId.placeholder) || 'Select gallery',
        label: (diviData.galleryId && diviData.galleryId.label) || 'Select Gallery',
      },
      enableOptions: {
        label: (diviData.enableOptions && diviData.enableOptions.label) || 'Enable options section',
      },
      ajax_url: diviData.ajax_url || diviData.ajaxUrl || '',
      nonce: diviData.nonce || '',
      loading_text: diviData.loading_text || 'Loading gallery preview...',
      empty_text: diviData.empty_text || 'Select a gallery to preview it.',
      error_text: diviData.error_text || 'Unable to load gallery preview. Please try again.',
    };
  }

  function sanitizeModuleId(moduleId) {
    return String(moduleId || '').replace(/[^a-zA-Z0-9_-]/g, '');
  }

  function getRootId(moduleId, serverRootId) {
    if (serverRootId) {
      return serverRootId;
    }

    return 'reacg-root' + sanitizeModuleId(moduleId);
  }

  function createMountController() {
    return {
      active: true,
      timeouts: [],
      lastTriggerByRootId: {},
    };
  }

  function deactivateMountController(controller) {
    if (!controller) {
      return;
    }

    controller.active = false;

    controller.timeouts.forEach(function (timeoutId) {
      window.clearTimeout(timeoutId);
    });

    controller.timeouts.length = 0;
  }

  function scheduleMountRetry(controller, callback, delay) {
    if (!controller.active) {
      return;
    }

    const timeoutId = window.setTimeout(callback, delay);

    controller.timeouts.push(timeoutId);
  }

  function getDocumentContexts() {
    const documents = [];
    const windows = [window];

    try {
      if (window.parent && window.parent !== window) {
        windows.push(window.parent);
      }
    } catch (error) {
      // Cross-origin parent; ignore.
    }

    try {
      if (window.top && window.top !== window) {
        windows.push(window.top);
      }
    } catch (error) {
      // Cross-origin top; ignore.
    }

    windows.forEach(function (win) {
      try {
        if (win && win.document && documents.indexOf(win.document) === -1) {
          documents.push(win.document);
        }
      } catch (error) {
        // Ignore inaccessible documents.
      }
    });

    return documents;
  }

  function findElementById(elementId) {
    const documents = getDocumentContexts();

    for (let index = 0; index < documents.length; index++) {
      const element = documents[index].getElementById(elementId);

      if (element) {
        return element;
      }
    }

    return null;
  }

  function findLoadAppButton(root) {
    const documents = root && root.ownerDocument ? [root.ownerDocument] : [];

    getDocumentContexts().forEach(function (doc) {
      if (documents.indexOf(doc) === -1) {
        documents.push(doc);
      }
    });

    for (let index = 0; index < documents.length; index++) {
      const loadApp = documents[index].getElementById('reacg-loadApp');

      if (loadApp) {
        return loadApp;
      }
    }

    return null;
  }

  function triggerLoadAppButton(loadApp, rootId) {
    loadApp.setAttribute('data-id', rootId);

    try {
      if (typeof loadApp.onclick === 'function') {
        loadApp.onclick.call(loadApp);
        return true;
      }
    } catch (error) {
      // Ignore onclick errors.
    }

    try {
      loadApp.click();
      return true;
    } catch (error) {
      // Ignore click errors.
    }

    const view =
      loadApp.ownerDocument && loadApp.ownerDocument.defaultView
        ? loadApp.ownerDocument.defaultView
        : window;

    loadApp.dispatchEvent(
      new MouseEvent('click', {
        bubbles: true,
        cancelable: true,
        view: view,
      })
    );

    return true;
  }

  function mountGalleryElement(root) {
    if (!root || !root.id) {
      return false;
    }

    const loadApp = findLoadAppButton(root);

    if (!loadApp) {
      return false;
    }

    return triggerLoadAppButton(loadApp, root.id);
  }

  function hasMountedGalleryContent(root) {
    return !!(
      root &&
      (root.childElementCount > 0 || String(root.textContent || '').trim() !== '')
    );
  }

  function shouldTriggerMount(controller, rootId) {
    const now = Date.now();
    const lastTrigger = controller.lastTriggerByRootId[rootId] || 0;

    if (now - lastTrigger < 400) {
      return false;
    }

    controller.lastTriggerByRootId[rootId] = now;
    return true;
  }

  function mountGalleryPreview(rootId, expectedGalleryId, controller, attempt, onSuccess) {
    if (!controller.active) {
      return;
    }

    const currentAttempt = attempt || 0;
    const maxAttempts = 80;
    const root = findElementById(rootId);

    if (root) {
      if (String(root.getAttribute('data-gallery-id')) !== String(expectedGalleryId)) {
        if (currentAttempt < maxAttempts) {
          scheduleMountRetry(controller, function () {
            mountGalleryPreview(rootId, expectedGalleryId, controller, currentAttempt + 1, onSuccess);
          }, currentAttempt < 15 ? 100 : 150);
        }

        return;
      }

      if (hasMountedGalleryContent(root)) {
        if (typeof onSuccess === 'function') {
          onSuccess();
        }

        return;
      }

      if (shouldTriggerMount(controller, rootId)) {
        mountGalleryElement(root);
      }
    }

    if (currentAttempt >= maxAttempts) {
      return;
    }

    scheduleMountRetry(controller, function () {
      mountGalleryPreview(rootId, expectedGalleryId, controller, currentAttempt + 1, onSuccess);
    }, currentAttempt < 20 ? 100 : 250);
  }

  function scheduleGalleryPreviewMount(rootId, expectedGalleryId, controller, onSuccess) {
    window.requestAnimationFrame(function () {
      window.requestAnimationFrame(function () {
        mountGalleryPreview(rootId, expectedGalleryId, controller, 0, onSuccess);
      });
    });
  }

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
      })
    );
  }

  function EditComponent(props) {
    const { attrs, elements, id, name } = props;
    const settings = getSettings();
    const galleryId = getAttrByMode(attrs.galleryId);
    const enableOptions = getAttrByMode(attrs.enableOptions);
    const stableRootId = getRootId(id, '');
    const previewState = useState({
      loading: false,
      error: false,
      rootId: stableRootId,
      galleryId: '',
      galleryTimestamp: '',
      optionsTimestamp: '',
      pluginVersion: '',
    });
    const preview = previewState[0];
    const setPreview = previewState[1];
    const mountControllerRef = useRef(null);
    const lastMountedRef = useRef('');

    useEffect(function () {
      if (!galleryId || galleryId === '0') {
        deactivateMountController(mountControllerRef.current);
        mountControllerRef.current = null;
        lastMountedRef.current = '';
        setPreview({
          loading: false,
          error: false,
          rootId: stableRootId,
          galleryId: '',
          galleryTimestamp: '',
          optionsTimestamp: '',
          pluginVersion: '',
        });
        return;
      }

      if (!settings.ajax_url || !settings.nonce) {
        setPreview({
          loading: false,
          error: true,
          rootId: stableRootId,
          galleryId: String(galleryId),
          galleryTimestamp: '',
          optionsTimestamp: '',
          pluginVersion: '',
        });
        return;
      }

      deactivateMountController(mountControllerRef.current);
      mountControllerRef.current = null;
      lastMountedRef.current = '';

      const controller = new AbortController();
      const formData = new FormData();

      formData.append('nonce', settings.nonce);
      formData.append('action', 'reacg_divi_preview');
      formData.append('gallery_id', String(galleryId));
      formData.append('module_id', String(id || ''));
      formData.append('enable_options', String(enableOptions));

      setPreview(function (currentPreview) {
        return {
          loading: true,
          error: false,
          rootId: stableRootId,
          galleryId: currentPreview.galleryId || '',
          galleryTimestamp: currentPreview.galleryTimestamp || '',
          optionsTimestamp: currentPreview.optionsTimestamp || '',
          pluginVersion: currentPreview.pluginVersion || '',
        };
      });

      fetch(settings.ajax_url, {
        method: 'POST',
        cache: 'no-cache',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'Cache-Control': 'no-cache',
        },
        body: new URLSearchParams(formData),
        signal: controller.signal,
      })
        .then(function (response) {
          return response.json();
        })
        .then(function (response) {
          if (response && response.success && response.data) {
            try {
              window.reacg_data = window.reacg_data || {};
              window.reacg_data[String(response.data.galleryId || galleryId)] = response.data.galleryData;
            } catch (error) {
              // Ignore preview data assignment errors.
            }

            const rootId = getRootId(id, response.data.rootId);
            const now = String(Date.now());

            setPreview({
              loading: false,
              error: false,
              rootId: rootId,
              galleryId: String(response.data.galleryId || galleryId),
              galleryTimestamp: String(response.data.galleryTimestamp || now),
              optionsTimestamp: String(response.data.optionsTimestamp || now),
              pluginVersion: String(response.data.pluginVersion || ''),
            });
            return;
          }

          setPreview({
            loading: false,
            error: true,
            rootId: stableRootId,
            galleryId: String(galleryId),
            galleryTimestamp: '',
            optionsTimestamp: '',
            pluginVersion: '',
          });
        })
        .catch(function () {
          if (!controller.signal.aborted) {
            setPreview({
              loading: false,
              error: true,
              rootId: stableRootId,
              galleryId: String(galleryId),
              galleryTimestamp: '',
              optionsTimestamp: '',
              pluginVersion: '',
            });
          }
        });

      return function () {
        controller.abort();
      };
    }, [galleryId, enableOptions, id]);

    useLayoutEffect(function () {
      if (preview.loading || preview.error || !preview.rootId || !preview.galleryId) {
        return;
      }

      const mountKey =
        preview.rootId + ':' + preview.galleryId + ':' + String(enableOptions);

      if (lastMountedRef.current === mountKey) {
        return;
      }

      deactivateMountController(mountControllerRef.current);

      const controller = createMountController();

      mountControllerRef.current = controller;
      scheduleGalleryPreviewMount(preview.rootId, preview.galleryId, controller, function () {
        lastMountedRef.current = mountKey;
      });

      return function () {
        deactivateMountController(controller);

        if (mountControllerRef.current === controller) {
          mountControllerRef.current = null;
        }
      };
    }, [preview.loading, preview.error, preview.rootId, preview.galleryId, enableOptions]);

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
      React.createElement(
        'div',
        { className: 'et_pb_module_inner' },
        !galleryId || galleryId === '0'
          ? React.createElement('div', { className: 'reacg-divi-empty' }, settings.empty_text)
          : React.createElement(
              'div',
              { className: 'reacg-divi-preview-wrap' },
              preview.loading
                ? React.createElement('div', { className: 'reacg-divi-loading' }, settings.loading_text)
                : null,
              preview.error
                ? React.createElement('div', { className: 'reacg-divi-error' }, settings.error_text)
                : null,
              React.createElement('div', {
                key:
                  (preview.rootId || stableRootId) +
                  ':' +
                  (preview.galleryId || '') +
                  ':' +
                  String(enableOptions),
                id: preview.rootId || stableRootId,
                className: 'reacg-wrapper reacg-gallery reacg-preview',
                'data-options-section': enableOptions === 'on' ? 1 : 0,
                'data-options-container': '#reacg_settings',
                'data-plugin-version': preview.pluginVersion,
                'data-gallery-timestamp': preview.galleryTimestamp,
                'data-options-timestamp': preview.optionsTimestamp,
                'data-gallery-id': preview.galleryId,
              })
            )
      )
    );
  }

  function SettingsComponent(props) {
    const { attrs } = props;
    const settings = getSettings();

    return React.createElement(
      GroupContainer,
      {
        attrs: attrs,
        id: 'reacgGallerySettings',
        title: settings.settingsGroup.label,
      },
      React.createElement(
        FieldContainer,
        {
          attrs: attrs,
          attrName: 'galleryId',
          label: settings.galleryId.label,
        },
        React.createElement(Select, {
          options: settings.galleries,
          emptyLabel: settings.galleryId.placeholder,
        })
      ),
      React.createElement(
        FieldContainer,
        {
          attrs: attrs,
          attrName: 'enableOptions',
          label: settings.enableOptions.label,
        },
        React.createElement(ToggleContainer, { attrName: 'enableOptions' })
      )
    );
  }

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

  window.vendor.wp.hooks.addAction(
    'divi.moduleLibrary.registerModuleLibraryStore.after',
    'regallery',
    function () {
      registerModule(moduleDefinition.metadata, moduleDefinition);
    }
  );

  window.vendor.wp.hooks.addFilter('divi.iconLibrary.icon.map', 'regallery', function (icons) {
    return Object.assign({}, icons, {
      'regallery/divi-gallery-selector': {
        name: 'regallery/divi-gallery-selector',
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
            })
          );
        },
      },
    });
  });
})();
