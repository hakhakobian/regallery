(function ($) {
  "use strict";

  var state = {
    source: "",
    galleries: [],
    filtered: [],
    sort: {
      key: "title",
      dir: "asc",
    },
    page: 1,
    perPage: 20,
  };

  var $source = $("#reacg-migration-source");
  var $search = $("#reacg-migration-search");
  var $list = $("#reacg-migration-list");
  var $status = $("#reacg-migration-status");
  var $importBtn = $("#reacg-migration-import");
  var $results = $("#reacg-migration-results");
  var $importSpinner = $("#reacg-migration-import-spinner");
  var $progress = $("#reacg-migration-progress");
  var $progressText = $("#reacg-migration-progress-text");
  var $progressFill = $("#reacg-migration-progress-fill");
  var $progressBar = $(".reacg-migration-progress-bar");
  var $loadBtn = $("#reacg-migration-load");
  var $searchSubmitBtn = $("#reacg-migration-search-submit");
  var $pagesTop = $("#reacg-migration-pages-top");
  var $pagesBottom = $("#reacg-migration-pages-bottom");
  var $forceNewInput = $("#reacg-migration-force-new");
  var $alert = $("#reacg-migration-alert");
  var $alertMessage = $("#reacg-migration-alert-message");
  var $alertConfirm = $("#reacg-migration-alert-confirm");
  var $alertCancel = $("#reacg-migration-alert-cancel");
  var $alertClose = $("#reacg-migration-alert-close");

  function getPayload(extra) {
    var payload = {};
    payload[reacg_migration.nonce_key] = reacg_migration.nonce;
    return $.extend(payload, extra || {});
  }

  function escHtml(value) {
    return String(value || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function showStatus(type, message) {
    $status
      .removeClass("notice-success notice-error notice-warning")
      .addClass("notice-" + type)
      .html("<p>" + escHtml(message) + "</p>")
      .show();
  }

  function clearStatus() {
    $status
      .hide()
      .removeClass("notice-success notice-error notice-warning")
      .empty();
  }

  function setLoading(message) {
    showStatus("warning", message || reacg_migration.i18n.loading);
  }

  function setBusy(isBusy) {
    $source.prop("disabled", isBusy);
    $search.prop("disabled", isBusy);
    $searchSubmitBtn.prop("disabled", isBusy);
    $loadBtn.prop("disabled", isBusy);
    $importBtn.prop(
      "disabled",
      isBusy || $(".reacg-migration-item:checked").length === 0,
    );
    $("#reacg-migration-toggle-all").prop("disabled", isBusy);
    $(".reacg-migration-item").prop("disabled", isBusy);
  }

  function setImportLoading(isLoading) {
    if (isLoading) {
      $importSpinner.addClass("is-active").show();
      $importBtn.prop("disabled", true);
      return;
    }

    $importSpinner.removeClass("is-active").hide();
    updateImportState();
  }

  function showProgress(text, percent) {
    $progressText.text(text || "");
    if (typeof percent === "number") {
      var clamped = Math.max(0, Math.min(100, percent));
      $progressFill.css("width", clamped + "%");
      $progressBar.attr("aria-valuenow", String(Math.round(clamped)));
      $progressBar.show();
    } else {
      $progressFill.css("width", "0%");
      $progressBar.attr("aria-valuenow", "0");
      $progressBar.hide();
    }
    $progress.show();
  }

  function hideProgress() {
    $progress.hide();
    $progressText.text("");
    $progressFill.css("width", "0%");
    $progressBar.attr("aria-valuenow", "0");
    $progressBar.show();
  }

  function renderSources(items) {
    $source.empty();
    $source.append(
      '<option value="">' +
        escHtml(reacg_migration.i18n.all_sources) +
        "</option>",
    );

    items.forEach(function (source) {
      if (!source.available) {
        return;
      }
      $source.append(
        '<option value="' +
          escHtml(source.key) +
          '">' +
          escHtml(source.label) +
          "</option>",
      );
    });
  }

  function getStatusLabel(item) {
    if (item.migrated) {
      if (item.migrated_gallery_id) {
        var href = reacg_migration.edit_url + item.migrated_gallery_id;
        return (
          '<a href="' +
          escHtml(href) +
          '" target="_blank" rel="noopener noreferrer">' +
          escHtml(reacg_migration.i18n.status_migrated) +
          "</a>"
        );
      }
      return escHtml(reacg_migration.i18n.status_migrated);
    }

    return escHtml(reacg_migration.i18n.status_not_migrated);
  }

  function getComparableValue(item, key) {
    if (key === "image_count") {
      return Number(item.image_count || 0);
    }

    if (key === "source") {
      return (item.source_label || item.source || "").toLowerCase();
    }

    if (key === "status") {
      return item.migrated ? 1 : 0;
    }

    return (item.title || "").toLowerCase();
  }

  function getReplaceAction(item) {
    if (!item || !item.migrated) {
      return "&mdash;";
    }

    if (item.replaced) {
      return (
        '<span class="reacg-migration-replaced">' +
        escHtml(
          reacg_migration.i18n.replace_already_replaced ||
            reacg_migration.i18n.replace_replaced,
        ) +
        "</span>"
      );
    }

    return (
      '<a href="#" class="reacg-migration-replace-shortcode" data-source="' +
      escHtml(item.source || "") +
      '" data-source-gallery-id="' +
      escHtml(item.id || "") +
      '" data-migrated-gallery-id="' +
      escHtml(item.migrated_gallery_id || "0") +
      '">' +
      escHtml(reacg_migration.i18n.replace_shortcode) +
      "</a>"
    );
  }

  function sortFiltered() {
    var key = state.sort.key;
    var dir = state.sort.dir;

    state.filtered.sort(function (a, b) {
      var av = getComparableValue(a, key);
      var bv = getComparableValue(b, key);

      if (av < bv) {
        return dir === "asc" ? -1 : 1;
      }
      if (av > bv) {
        return dir === "asc" ? 1 : -1;
      }
      return 0;
    });
  }

  function applyFilters() {
    var source = state.source;
    var term = ($search.val() || "").toLowerCase().trim();

    state.filtered = state.galleries.filter(function (item) {
      if (source && item.source !== source) {
        return false;
      }

      if (term && (item.title || "").toLowerCase().indexOf(term) === -1) {
        return false;
      }

      return true;
    });

    sortFiltered();
    renderSortHeaders();

    var maxPage = Math.max(1, Math.ceil(state.filtered.length / state.perPage));
    state.page = Math.min(state.page, maxPage);
    renderGalleryRows();
    renderPagination();
  }

  function renderSortHeaders() {
    var activeKey = state.sort.key;
    var activeDir = state.sort.dir;
    var visualDir = activeDir === "asc" ? "desc" : "asc";

    $(".reacg-migration-table th[data-sort]").each(function () {
      var $th = $(this);
      var key = $th.data("sort");

      $th.removeClass("sorted sortable asc desc");
      if (key === activeKey) {
        $th.addClass("sorted " + visualDir);
        $th.attr("aria-sort", activeDir === "asc" ? "ascending" : "descending");
        return;
      }

      $th.addClass("sortable desc");
      $th.attr("aria-sort", "none");
    });
  }

  function renderGalleryRows() {
    $list.empty();

    if (!state.filtered.length) {
      $list.append(
        '<tr><td colspan="6">' +
          escHtml(reacg_migration.i18n.no_data) +
          "</td></tr>",
      );
      $importBtn.prop("disabled", true);
      $("#reacg-migration-toggle-all").prop("checked", false);
      return;
    }

    var start = (state.page - 1) * state.perPage;
    var end = start + state.perPage;
    var rows = state.filtered.slice(start, end);

    rows.forEach(function (gallery) {
      var checkboxId =
        "reacg-migration-item-" +
        escHtml(gallery.source) +
        "-" +
        escHtml(gallery.id);
      var row = [
        "<tr>",
        '<th class="check-column"><input type="checkbox" id="' +
          checkboxId +
          '" class="reacg-migration-item" data-source="' +
          escHtml(gallery.source) +
          '" data-migrated="' +
          (gallery.migrated ? "1" : "0") +
          '" value="' +
          escHtml(gallery.id) +
          '"></th>',
        '<td><a href="#" class="row-title reacg-migration-title-select" data-checkbox-id="' +
          checkboxId +
          '">' +
          escHtml(gallery.title || "") +
          "</a></td>",
        "<td>" +
          escHtml(gallery.source_label || gallery.source || "") +
          "</td>",
        "<td>" + escHtml(gallery.image_count || 0) + "</td>",
        "<td>" + getStatusLabel(gallery) + "</td>",
        "<td>" + getReplaceAction(gallery) + "</td>",
        "</tr>",
      ].join("");
      $list.append(row);
    });

    updateImportState();
  }

  function renderPagination() {
    var total = state.filtered.length;
    var maxPage = Math.max(1, Math.ceil(total / state.perPage));
    var current = state.page;
    var isFirstDisabled = current <= 1;
    var isLastDisabled = current >= maxPage;

    function getPaginationHtml(position) {
      var currentPageId = "reacg-migration-current-page-" + position;
      var pagingId = "reacg-migration-pages-" + position;

      function getNavControl(action, label, symbol, isDisabled, buttonClass) {
        if (isDisabled) {
          return (
            '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">' +
            symbol +
            "</span>"
          );
        }

        return (
          '<a class="' +
          buttonClass +
          ' button" href="#" data-page="' +
          action +
          '">' +
          '<span class="screen-reader-text">' +
          escHtml(label) +
          "</span>" +
          '<span aria-hidden="true">' +
          symbol +
          "</span>" +
          "</a>"
        );
      }

      return [
        '<span class="displaying-num">' +
          escHtml(total) +
          " " +
          escHtml(reacg_migration.i18n.items) +
          "</span>",
        '<span class="pagination-links">',
        getNavControl(
          "first",
          reacg_migration.i18n.first_page,
          "&laquo;",
          isFirstDisabled,
          "first-page",
        ),
        getNavControl(
          "prev",
          reacg_migration.i18n.prev,
          "&lsaquo;",
          isFirstDisabled,
          "prev-page",
        ),
        '<span class="paging-input">' +
          '<label for="' +
          escHtml(currentPageId) +
          '" class="screen-reader-text">' +
          escHtml(reacg_migration.i18n.current_page) +
          "</label>" +
          '<input class="current-page" id="' +
          escHtml(currentPageId) +
          '" type="text" name="paged" value="' +
          escHtml(current) +
          '" size="1" aria-describedby="' +
          escHtml(pagingId) +
          '" />' +
          '<span class="tablenav-paging-text"> ' +
          escHtml(reacg_migration.i18n.of) +
          ' <span class="total-pages">' +
          escHtml(maxPage) +
          "</span></span>" +
          "</span>",
        getNavControl(
          "next",
          reacg_migration.i18n.next,
          "&rsaquo;",
          isLastDisabled,
          "next-page",
        ),
        getNavControl(
          "last",
          reacg_migration.i18n.last_page,
          "&raquo;",
          isLastDisabled,
          "last-page",
        ),
        "</span>",
      ].join("");
    }

    $pagesTop.html(getPaginationHtml("top"));
    $pagesBottom.html(getPaginationHtml("bottom"));
  }

  function getSelectedGalleries() {
    return $(".reacg-migration-item:checked")
      .map(function () {
        var $item = $(this);

        return {
          id: $item.val(),
          source: $item.data("source"),
          migrated: String($item.attr("data-migrated")) === "1",
        };
      })
      .get();
  }

  function getForceNewValue(selected) {
    var hasMigratedSelection = selected.some(function (item) {
      return item.migrated;
    });

    if (!hasMigratedSelection) {
      return {
        selected: selected,
        forceNew: parseInt($forceNewInput.val(), 10) === 1 ? 1 : 0,
        showAlert: false,
      };
    }

    return {
      selected: selected,
      forceNew: 1,
      showAlert: true,
    };
  }

  function showForceNewAlert(onConfirm, onSkipMigrated, onClose) {
    $alertMessage.text(reacg_migration.i18n.force_new_alert || "");
    $alert.show().attr("aria-hidden", "false");
    $alertConfirm.trigger("focus");

    function hideAlert() {
      $alert.hide().attr("aria-hidden", "true");
      $alertConfirm.off("click.reacgMigrationAlert");
      $alertCancel.off("click.reacgMigrationAlert");
      $alertClose.off("click.reacgMigrationAlert");
      $alert.off("click.reacgMigrationAlert");
      $(document).off("keydown.reacgMigrationAlert");
    }

    $alertConfirm
      .off("click.reacgMigrationAlert")
      .on("click.reacgMigrationAlert", function () {
        hideAlert();
        if (typeof onConfirm === "function") {
          onConfirm();
        }
      });

    $alertCancel
      .off("click.reacgMigrationAlert")
      .on("click.reacgMigrationAlert", function () {
        hideAlert();
        if (typeof onSkipMigrated === "function") {
          onSkipMigrated();
        }
      });

    $alertClose
      .off("click.reacgMigrationAlert")
      .on("click.reacgMigrationAlert", function () {
        hideAlert();
        if (typeof onClose === "function") {
          onClose();
        }
      });

    $alert
      .off("click.reacgMigrationAlert")
      .on("click.reacgMigrationAlert", function (event) {
        if (!$(event.target).is(".reacg-migration-alert__backdrop")) {
          return;
        }

        hideAlert();
        if (typeof onClose === "function") {
          onClose();
        }
      });

    $(document)
      .off("keydown.reacgMigrationAlert")
      .on("keydown.reacgMigrationAlert", function (event) {
        if (event.key !== "Escape") {
          return;
        }

        hideAlert();
        if (typeof onClose === "function") {
          onClose();
        }
      });
  }

  function startImport(selected, forceNew) {
    setBusy(true);
    setImportLoading(true);
    setLoading(reacg_migration.i18n.importing);

    var total = selected.length;
    var processed = 0;
    var imported = 0;
    var failed = 0;
    var rows = [];

    function renderResultLine(item, isSuccess) {
      if (!isSuccess) {
        rows.push(
          "<li><strong>" +
            escHtml(item.source_gallery_id || "-") +
            "</strong>: " +
            escHtml(item.message || reacg_migration.i18n.error) +
            "</li>",
        );
        return;
      }

      var editLink = reacg_migration.edit_url + item.gallery_id;
      rows.push(
        "<li><strong>" +
          escHtml(item.source_gallery_id) +
          "</strong>: " +
          escHtml(item.message) +
          ' <a href="' +
          escHtml(editLink) +
          '" target="_blank" rel="noopener noreferrer">#' +
          escHtml(item.gallery_id) +
          "</a></li>",
      );
    }

    function finishImport() {
      $results.html(
        '<div class="notice notice-success inline"><p>' +
          escHtml(reacg_migration.i18n.import_done) +
          " " +
          "(Imported: " +
          imported +
          ", Failed: " +
          failed +
          ")" +
          "</p><ul>" +
          rows.join("") +
          "</ul></div>",
      );

      clearStatus();
      hideProgress();
      setBusy(false);
      setImportLoading(false);
      fetchSourcesAndGalleries();
    }

    function runNext() {
      if (processed >= total) {
        finishImport();
        return;
      }

      var current = selected[processed];
      var currentIndex = processed + 1;
      var percent = (processed / total) * 100;
      showProgress(
        reacg_migration.i18n.importing +
          " (" +
          currentIndex +
          "/" +
          total +
          ")",
        percent,
      );

      $.post(
        reacg_migration.ajax_url,
        getPayload({
          action: "reacg_migration_import",
          source: current.source,
          force_new: forceNew,
          gallery_ids: JSON.stringify([current.id]),
        }),
      )
        .done(function (response) {
          if (!response || !response.success) {
            failed += 1;
            renderResultLine(
              {
                source_gallery_id: current.id,
                message:
                  (response && response.data && response.data.message) ||
                  reacg_migration.i18n.error,
              },
              false,
            );
            return;
          }

          var item =
            (response.data &&
              response.data.results &&
              response.data.results[0]) ||
            null;
          if (!item || !item.success) {
            failed += 1;
            renderResultLine(
              item || {
                source_gallery_id: current.id,
                message: reacg_migration.i18n.error,
              },
              false,
            );
            return;
          }

          imported += 1;
          renderResultLine(item, true);
        })
        .fail(function () {
          failed += 1;
          renderResultLine(
            {
              source_gallery_id: current.id,
              message: reacg_migration.i18n.error,
            },
            false,
          );
        })
        .always(function () {
          processed += 1;
          var nextPercent = (processed / total) * 100;
          showProgress(
            reacg_migration.i18n.importing +
              " (" +
              Math.min(processed + 1, total) +
              "/" +
              total +
              ")",
            nextPercent,
          );
          runNext();
        });
    }

    showProgress(reacg_migration.i18n.importing + " (1/" + total + ")", 0);
    runNext();
  }

  function updateImportState() {
    var total = $(".reacg-migration-item").length;
    var selected = $(".reacg-migration-item:checked").length;
    $importBtn.prop("disabled", selected === 0);
    $("#reacg-migration-toggle-all").prop(
      "checked",
      total > 0 && selected === total,
    );
  }

  function fetchSourcesAndGalleries() {
    setBusy(true);
    setLoading(reacg_migration.i18n.loading_sources);
    showProgress(reacg_migration.i18n.loading_sources);

    $.post(
      reacg_migration.ajax_url,
      getPayload({ action: "reacg_migration_sources" }),
    )
      .done(function (response) {
        if (!response || !response.success) {
          showStatus(
            "error",
            (response && response.data && response.data.message) ||
              reacg_migration.i18n.error,
          );
          return;
        }

        renderSources(response.data.sources || []);
      })
      .fail(function () {
        showStatus("error", reacg_migration.i18n.error);
      })
      .always(function () {
        setLoading(reacg_migration.i18n.loading_galleries);
        showProgress(reacg_migration.i18n.loading_galleries);

        $.post(
          reacg_migration.ajax_url,
          getPayload({
            action: "reacg_migration_galleries",
            source: "",
            search: "",
            page: 1,
            per_page: 9999,
          }),
        )
          .done(function (response) {
            if (!response || !response.success) {
              showStatus(
                "error",
                (response && response.data && response.data.message) ||
                  reacg_migration.i18n.error,
              );
              return;
            }

            state.galleries = response.data.items || [];
            state.page = 1;
            clearStatus();
            applyFilters();
          })
          .fail(function () {
            showStatus("error", reacg_migration.i18n.error);
          })
          .always(function () {
            setBusy(false);
            hideProgress();
          });
      });
  }

  function importSelected() {
    var selected = getSelectedGalleries();

    if (!selected.length) {
      showStatus("warning", reacg_migration.i18n.select_galleries);
      return;
    }

    var importOptions = getForceNewValue(selected);

    if (importOptions.showAlert) {
      showForceNewAlert(
        function () {
          startImport(importOptions.selected, importOptions.forceNew);
        },
        function () {
          var notMigrated = importOptions.selected.filter(function (item) {
            return !item.migrated;
          });

          if (!notMigrated.length) {
            showStatus("warning", reacg_migration.i18n.select_not_migrated);
            return;
          }

          startImport(
            notMigrated,
            parseInt($forceNewInput.val(), 10) === 1 ? 1 : 0,
          );
        },
        function () {},
      );
      return;
    }

    startImport(importOptions.selected, importOptions.forceNew);
  }

  $(document).on("click", "#reacg-migration-load", function () {
    fetchSourcesAndGalleries();
  });

  $(document).on("click", "#reacg-migration-import", importSelected);

  $(document).on(
    "click",
    ".reacg-migration-replace-shortcode",
    function (event) {
      event.preventDefault();

      var $link = $(this);
      if ($link.hasClass("is-busy")) {
        return;
      }

      var source = String($link.data("source") || "");
      var sourceGalleryId = String($link.data("source-gallery-id") || "");
      var migratedGalleryId =
        parseInt($link.data("migrated-gallery-id"), 10) || 0;

      if (!source || !sourceGalleryId) {
        showStatus("error", reacg_migration.i18n.error);
        return;
      }

      $link.addClass("is-busy");
      setBusy(true);
      showProgress(reacg_migration.i18n.replacing_shortcode);

      $.post(
        reacg_migration.ajax_url,
        getPayload({
          action: "reacg_migration_replace_shortcode",
          source: source,
          source_gallery_id: sourceGalleryId,
          migrated_gallery_id: migratedGalleryId,
        }),
      )
        .done(function (response) {
          if (!response || !response.success) {
            showStatus(
              "error",
              (response && response.data && response.data.message) ||
                reacg_migration.i18n.error,
            );
            return;
          }

          var data = response.data || {};
          var replacedShortcodes = Number(data.replaced_shortcodes || 0);
          var updatedPosts = Number(data.updated_posts || 0);

          if (replacedShortcodes > 0) {
            showStatus(
              "success",
              reacg_migration.i18n.replace_done +
                " (" +
                replacedShortcodes +
                ", posts: " +
                updatedPosts +
                ")",
            );

            $link.replaceWith(
              '<span class="reacg-migration-replaced">' +
                escHtml(
                  reacg_migration.i18n.replace_replaced ||
                    reacg_migration.i18n.replace_done,
                ) +
                "</span>",
            );
          } else {
            showStatus("warning", reacg_migration.i18n.replace_none_found);
          }
        })
        .fail(function (xhr) {
          showStatus(
            "error",
            (xhr &&
              xhr.responseJSON &&
              xhr.responseJSON.data &&
              xhr.responseJSON.data.message) ||
              reacg_migration.i18n.error,
          );
        })
        .always(function () {
          $link.removeClass("is-busy");
          hideProgress();
          setBusy(false);
        });
    },
  );

  $(document).on("change", ".reacg-migration-item", updateImportState);

  $(document).on("click", ".reacg-migration-title-select", function (event) {
    event.preventDefault();

    var $checkbox = $(this).closest("tr").find(".reacg-migration-item").first();
    if (!$checkbox.length || $checkbox.prop("disabled")) {
      return;
    }

    $checkbox.prop("checked", !$checkbox.prop("checked"));
    updateImportState();
  });

  $(document).on("change", "#reacg-migration-toggle-all", function () {
    $(".reacg-migration-item").prop("checked", $(this).is(":checked"));
    updateImportState();
  });

  $(document).on("change", "#reacg-migration-source", function () {
    state.source = $(this).val();
    state.page = 1;
    applyFilters();
  });

  $(document).on("input", "#reacg-migration-search", function () {
    state.page = 1;
    applyFilters();
  });

  $(document).on("click", "#reacg-migration-search-submit", function () {
    state.page = 1;
    applyFilters();
  });

  $(document).on("keydown", "#reacg-migration-search", function (event) {
    if (event.key !== "Enter") {
      return;
    }

    event.preventDefault();
    state.page = 1;
    applyFilters();
  });

  $(document).on(
    "click",
    "#reacg-migration-pages-top a[data-page], #reacg-migration-pages-bottom a[data-page]",
    function (event) {
      event.preventDefault();

      var action = $(this).data("page");
      var maxPage = Math.max(
        1,
        Math.ceil(state.filtered.length / state.perPage),
      );

      if (action === "first") {
        state.page = 1;
      }
      if (action === "prev" && state.page > 1) {
        state.page -= 1;
      }
      if (action === "next" && state.page < maxPage) {
        state.page += 1;
      }
      if (action === "last") {
        state.page = maxPage;
      }

      renderGalleryRows();
      renderPagination();
    },
  );

  $(document).on(
    "keydown",
    "#reacg-migration-pages-top .current-page, #reacg-migration-pages-bottom .current-page",
    function (event) {
      if (event.key !== "Enter") {
        return;
      }

      event.preventDefault();
      var maxPage = Math.max(
        1,
        Math.ceil(state.filtered.length / state.perPage),
      );
      var nextPage = parseInt($(this).val(), 10);

      if (isNaN(nextPage)) {
        nextPage = state.page;
      }

      state.page = Math.max(1, Math.min(maxPage, nextPage));
      renderGalleryRows();
      renderPagination();
    },
  );

  $(document).on(
    "click",
    ".reacg-migration-table th[data-sort] a",
    function (event) {
      event.preventDefault();

      var key = $(this).closest("th").data("sort");
      if (!key) {
        return;
      }

      if (state.sort.key === key) {
        state.sort.dir = state.sort.dir === "asc" ? "desc" : "asc";
      } else {
        state.sort.key = key;
        state.sort.dir = "asc";
      }

      applyFilters();
    },
  );

  state.source = "";
  setImportLoading(false);
  renderSortHeaders();
  fetchSourcesAndGalleries();
})(jQuery);
