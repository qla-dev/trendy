$(function () {
  'use strict';

  var config = window.releasedMaterialsConfig || {};
  var dataUrl = (config.dataUrl || '').toString().trim();
  var deleteUrl = (config.deleteUrl || '').toString().trim();
  var canDeleteDocuments = !!config.canDeleteDocuments;
  var csrfToken = $('meta[name="csrf-token"]').attr('content') || '';
  var tableElement = $('#released-doc-table');
  var pageErrorElement = $('#released-doc-page-error');
  var filtersBody = $('#released-doc-filters-body');
  var toggleFiltersButton = $('#released-doc-toggle-filters');
  var clearFiltersButton = $('#released-doc-clear-filters');
  var applyFiltersButton = $('#released-doc-apply-filters');
  var activeFiltersContainer = $('#released-doc-active-filters');
  var dataTable = null;
  var deleteRequestInFlight = false;
  var tableLoadingRequestCount = 0;
  var hasCompletedInitialTableLoad = false;

  var filterLabels = {
    dokument: 'Dokument',
    predracun: 'RN',
    narudzba: 'Narud\u017eba',
    sifra: '\u0160ifra',
    naziv: 'Naziv',
    datum_od: 'Datum od',
    datum_do: 'Datum do',
    napomena: 'Napomena'
  };

  var filterInputIds = {
    dokument: 'filter-dokument',
    predracun: 'filter-predracun',
    narudzba: 'filter-narudzba',
    sifra: 'filter-sifra',
    naziv: 'filter-naziv',
    datum_od: 'filter-datum-od',
    datum_do: 'filter-datum-do',
    napomena: 'filter-napomena'
  };

  var bosnianDatePickerLocale = {
    firstDayOfWeek: 1,
    weekdays: {
      shorthand: ['Ned', 'Pon', 'Uto', 'Sri', '\u010cet', 'Pet', 'Sub'],
      longhand: ['Nedjelja', 'Ponedjeljak', 'Utorak', 'Srijeda', '\u010cetvrtak', 'Petak', 'Subota']
    },
    months: {
      shorthand: ['Jan', 'Feb', 'Mar', 'Apr', 'Maj', 'Jun', 'Jul', 'Avg', 'Sep', 'Okt', 'Nov', 'Dec'],
      longhand: [
        'Januar',
        'Februar',
        'Mart',
        'April',
        'Maj',
        'Juni',
        'Juli',
        'August',
        'Septembar',
        'Oktobar',
        'Novembar',
        'Decembar'
      ]
    }
  };

  function escapeHtml(value) {
    return $('<div/>').text(value === null || value === undefined ? '' : String(value)).html();
  }

  function extractAjaxErrorMessage(xhr, fallbackMessage) {
    var responseJson = xhr && xhr.responseJSON ? xhr.responseJSON : null;
    var errors = responseJson && responseJson.errors ? responseJson.errors : null;
    var firstErrorKey;

    if (responseJson && responseJson.message) {
      return responseJson.message;
    }

    if (errors) {
      firstErrorKey = Object.keys(errors)[0];

      if (firstErrorKey && errors[firstErrorKey] && errors[firstErrorKey][0]) {
        return errors[firstErrorKey][0];
      }
    }

    return fallbackMessage;
  }

  function replaceFeather() {
    if (window.feather && typeof window.feather.replace === 'function') {
      window.feather.replace({ width: 14, height: 14 });
    }
  }

  function showPageError(message) {
    if (!pageErrorElement.length) {
      return;
    }

    pageErrorElement.text(message || 'Gre\u0161ka pri u\u010ditavanju razdu\u017eenih materijala.');
    pageErrorElement.removeClass('d-none');
  }

  function hidePageError() {
    if (!pageErrorElement.length) {
      return;
    }

    pageErrorElement.addClass('d-none').text('');
  }

  function placeholder(value) {
    var normalizedValue = (value || '').toString().trim();

    return normalizedValue === '' ? '<span class="text-muted">-</span>' : escapeHtml(normalizedValue);
  }

  function formatQuantity(value) {
    var numericValue = Number(value);

    if (!Number.isFinite(numericValue)) {
      return '<span class="text-muted">-</span>';
    }

    return escapeHtml(
      numericValue.toLocaleString('hr-HR', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 3
      })
    );
  }

  function formatMoney(value, displayValue) {
    if (displayValue) {
      return escapeHtml(displayValue);
    }

    if (value === null || value === undefined || value === '') {
      return '<span class="text-muted">-</span>';
    }

    var numericValue = Number(value);

    if (!Number.isFinite(numericValue)) {
      return '<span class="text-muted">-</span>';
    }

    return escapeHtml(
      numericValue.toLocaleString('hr-HR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      }) + ' KM'
    );
  }

  function resolveRowDataFromTrigger(trigger) {
    var rowNode = $(trigger).closest('tr');

    if (!dataTable || !rowNode.length) {
      return null;
    }

    if (rowNode.hasClass('child')) {
      rowNode = rowNode.prev();
    }

    return dataTable.row(rowNode).data() || null;
  }

  function buildDeleteDocumentHtml(documentRow) {
    var documentNumber = escapeHtml(documentRow && documentRow.document_number ? documentRow.document_number : '-');
    var workOrderNumber = escapeHtml(documentRow && documentRow.predracun ? documentRow.predracun : '-');
    var orderNumber = escapeHtml(documentRow && documentRow.narudzba ? documentRow.narudzba : '-');

    return (
      '<div class="small lh-lg text-start d-inline-block">' +
        '<div><span class="fw-bolder">Dokument:</span> ' + documentNumber + '</div>' +
        '<div><span class="fw-bolder">RN:</span> ' + workOrderNumber + '</div>' +
        '<div><span class="fw-bolder">Narudzba:</span> ' + orderNumber + '</div>' +
        '<div class="mt-50 text-danger">Ova akcija je trajna i obrisat \u0107e dokument iz sistema.</div>' +
      '</div>'
    );
  }

  function requestDeleteDocument(documentRow) {
    var requestHeaders = {
      Accept: 'application/json'
    };
    var documentKey;

    if (!canDeleteDocuments || !deleteUrl || !documentRow || deleteRequestInFlight) {
      return Promise.reject(new Error('Brisanje dokumenta trenutno nije dostupno.'));
    }

    documentKey = (documentRow.document_key || '').toString().trim();

    if (!documentKey) {
      return Promise.reject(new Error('Dokument nema validan klju\u010d za brisanje.'));
    }

    if (csrfToken) {
      requestHeaders['X-CSRF-TOKEN'] = csrfToken;
    }

    return new Promise(function (resolve, reject) {
      deleteRequestInFlight = true;

      $.ajax({
        url: deleteUrl,
        method: 'DELETE',
        dataType: 'json',
        contentType: 'application/json; charset=UTF-8',
        headers: requestHeaders,
        data: JSON.stringify({
          document_key: documentKey
        }),
        success: function (response) {
          resolve(response || {});
        },
        error: function (xhr) {
          reject(new Error(extractAjaxErrorMessage(xhr, 'Ne mogu obrisati dokument.')));
        },
        complete: function () {
          deleteRequestInFlight = false;
        }
      });
    });
  }

  function confirmDeleteDocument(documentRow) {
    var displayNumber;
    var fallbackMessage;

    if (!canDeleteDocuments || !documentRow) {
      return;
    }

    displayNumber = (documentRow.document_number || '').toString().trim() || '-';
    fallbackMessage = 'Obrisati dokument ' + displayNumber + '?';

    if (!window.Swal || typeof window.Swal.fire !== 'function') {
      if (!window.confirm(fallbackMessage)) {
        return;
      }

      requestDeleteDocument(documentRow)
        .then(function () {
          if (dataTable) {
            dataTable.ajax.reload(null, false);
          }
        })
        .catch(function (error) {
          window.alert(error && error.message ? error.message : 'Brisanje dokumenta nije uspjelo.');
        });
      return;
    }

    window.Swal.fire({
      title: 'Izbrisati dokument?',
      html: buildDeleteDocumentHtml(documentRow),
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Izbri\u0161i',
      cancelButtonText: 'Otka\u017ei',
      customClass: {
        confirmButton: 'btn btn-danger',
        cancelButton: 'btn btn-outline-danger ms-1'
      },
      buttonsStyling: false,
      showLoaderOnConfirm: true,
      preConfirm: function () {
        return requestDeleteDocument(documentRow).catch(function (error) {
          window.Swal.showValidationMessage(
            error && error.message ? error.message : 'Brisanje dokumenta nije uspjelo.'
          );
        });
      },
      allowOutsideClick: function () {
        return !window.Swal.isLoading();
      }
    }).then(function (result) {
      if (!result.isConfirmed) {
        return;
      }

      return window.Swal.fire({
        icon: 'success',
        title: 'Dokument je izbrisan',
        timer: 900,
        showConfirmButton: false
      }).then(function () {
        if (dataTable) {
          dataTable.ajax.reload(null, false);
        }
      });
    }).catch(function (error) {
      window.Swal.fire({
        icon: 'error',
        title: 'Brisanje nije uspjelo',
        text: error && error.message ? error.message : 'Brisanje dokumenta nije uspjelo.',
        confirmButtonText: 'Zatvori',
        customClass: {
          confirmButton: 'btn btn-danger'
        },
        buttonsStyling: false
      });
    });
  }

  function collectFilters() {
    var filters = {};

    Object.keys(filterInputIds).forEach(function (key) {
      var input = document.getElementById(filterInputIds[key]);
      filters[key] = input ? String(input.value || '').trim() : '';
    });

    return filters;
  }

  function updateActiveFilters() {
    var filters = collectFilters();
    var html = '';
    var hasFilters = false;

    Object.keys(filters).forEach(function (key) {
      if (!filters[key]) {
        return;
      }

      hasFilters = true;
      html +=
        '<span class="released-doc-active-filter-chip">' +
        '<span class="released-doc-active-filter-label">' +
        escapeHtml(filterLabels[key] || key) +
        '</span>' +
        '<span class="released-doc-active-filter-value">' +
        escapeHtml(filters[key]) +
        '</span>' +
        '</span>';
    });

    if (!activeFiltersContainer.length) {
      return;
    }

    if (!hasFilters) {
      activeFiltersContainer.addClass('d-none').empty();
      return;
    }

    activeFiltersContainer.html(html).removeClass('d-none');
  }

  function reloadTable() {
    updateActiveFilters();
    hidePageError();

    if (dataTable) {
      dataTable.ajax.reload();
    }
  }

  function clearFilters() {
    Object.keys(filterInputIds).forEach(function (key) {
      var input = document.getElementById(filterInputIds[key]);

      if (input) {
        input.value = '';
      }
    });

    reloadTable();
  }

  function initializeDatePickers() {
    if (typeof window.flatpickr !== 'function') {
      return;
    }

    $('.released-doc-filter-date').flatpickr({
      altInput: false,
      allowInput: true,
      dateFormat: 'd.m.Y',
      locale: bosnianDatePickerLocale
    });
  }

  function ensureTableBodyScrollContainer() {
    var wrapper = tableElement.closest('.dataTables_wrapper');
    var bodyCell;
    var scrollHost;

    if (!wrapper.length) {
      return tableElement.parent();
    }

    bodyCell = wrapper.children('.row').eq(1).children("[class*='col-']").first();

    if (!bodyCell.length) {
      return tableElement.parent();
    }

    bodyCell.addClass('released-doc-table-body-cell');
    scrollHost = bodyCell.children('.released-doc-table-body-scroll').first();

    if (scrollHost.length) {
      return scrollHost;
    }

    scrollHost = $('<div class="released-doc-table-body-scroll"></div>');
    tableElement.before(scrollHost);
    scrollHost.append(tableElement);

    if (scrollHost.length && scrollHost.get(0)) {
      scrollHost.get(0).scrollLeft = 0;
    }

    return scrollHost;
  }

  function resolveTableOverlayHost() {
    ensureTableBodyScrollContainer();
    return tableElement.closest('.card-datatable');
  }

  function setInitialTableLoadingState(active) {
    var overlayHost = resolveTableOverlayHost();

    if (!overlayHost.length) {
      return;
    }

    if (active && hasCompletedInitialTableLoad) {
      return;
    }

    overlayHost.toggleClass('released-doc-initial-loading', !!active);
  }

  function ensureTableLoadingOverlay() {
    var overlayHost = resolveTableOverlayHost();
    var overlay;

    if (!overlayHost.length) {
      return null;
    }

    overlayHost.addClass('released-doc-loading-overlay-host');
    overlay = overlayHost.find('.released-doc-loading-overlay').first();

    if (overlay.length) {
      return overlay;
    }

    overlayHost.append(
      '<div class="released-doc-loading-overlay" aria-hidden="true">' +
        '<div class="released-doc-loading-overlay-content">' +
          '<div class="spinner-border released-doc-loading-spinner" role="status" aria-hidden="true"></div>' +
          '<div class="released-doc-loading-message">U\u010ditavanje podataka</div>' +
        '</div>' +
      '</div>'
    );

    return overlayHost.find('.released-doc-loading-overlay').first();
  }

  function updateTableLoadingOverlayBounds() {
    var overlayHost = resolveTableOverlayHost();
    var overlay = overlayHost.find('.released-doc-loading-overlay').first();
    var hostElement = overlayHost.get(0);
    var tableNode = tableElement.get(0);
    var scrollHost = ensureTableBodyScrollContainer();
    var bodyElement = scrollHost && scrollHost.length ? scrollHost.get(0) : tableNode;
    var spacerRowElement;
    var hostRect;
    var tableRect;
    var bodyRect;
    var spacerRect;
    var headerHeight;
    var top;
    var left;
    var width;
    var height;
    var minimumBodyHeight = 120;
    var spacerRowApplied = false;

    if (!overlay.length || !hostElement || !tableNode) {
      return;
    }

    hostRect = hostElement.getBoundingClientRect();
    tableRect = tableNode.getBoundingClientRect();
    headerHeight = tableElement.find('thead').outerHeight() || 0;
    top = Math.max(0, tableRect.top - hostRect.top + headerHeight);
    left = Math.max(0, tableRect.left - hostRect.left);
    width = Math.max(0, tableRect.width);
    height = Math.max(minimumBodyHeight, tableRect.height - headerHeight);

    if (bodyElement) {
      spacerRowElement = bodyElement.querySelector('.released-doc-loading-spacer-row');
      bodyRect = bodyElement.getBoundingClientRect();

      if (spacerRowElement) {
        spacerRect = spacerRowElement.getBoundingClientRect();

        if (
          Number.isFinite(spacerRect.width) &&
          spacerRect.width > 0 &&
          Number.isFinite(spacerRect.height) &&
          spacerRect.height > 0
        ) {
          top = spacerRect.top - hostRect.top;
          left = spacerRect.left - hostRect.left;
          width = spacerRect.width;
          height = spacerRect.height;
          spacerRowApplied = true;
        }
      }

      if (
        !spacerRowApplied &&
        Number.isFinite(bodyRect.width) &&
        bodyRect.width > 0 &&
        Number.isFinite(bodyRect.height) &&
        bodyRect.height > 0
      ) {
        top = bodyRect.top - hostRect.top;
        left = bodyRect.left - hostRect.left;
        width = bodyRect.width;
        height = bodyRect.height;
      }
    }

    if (hostElement && (hostElement.clientWidth || hostRect.width)) {
      left = 0;
      width = hostElement.clientWidth || hostRect.width;
    }

    overlay.css({
      top: Math.max(0, top) + 'px',
      left: Math.max(0, left) + 'px',
      width: Math.max(0, width) + 'px',
      height: Math.max(minimumBodyHeight, height) + 'px'
    });
  }

  function showTableLoadingOverlay() {
    var overlay = ensureTableLoadingOverlay();

    if (!overlay || !overlay.length) {
      return;
    }

    updateTableLoadingOverlayBounds();
    overlay.addClass('is-visible').attr('aria-hidden', 'false');
    window.requestAnimationFrame(updateTableLoadingOverlayBounds);
    window.setTimeout(updateTableLoadingOverlayBounds, 0);
  }

  function hideTableLoadingOverlay(force) {
    var overlayHost = resolveTableOverlayHost();
    var overlay = overlayHost.find('.released-doc-loading-overlay').first();

    if (!overlay.length) {
      return;
    }

    if (!force && tableLoadingRequestCount > 0) {
      return;
    }

    overlay.removeClass('is-visible').attr('aria-hidden', 'true');
  }

  function beginTableLoadingRequest() {
    tableLoadingRequestCount += 1;
    showTableLoadingOverlay();
  }

  function finishTableLoadingRequest(force) {
    if (force) {
      tableLoadingRequestCount = 0;
    } else {
      tableLoadingRequestCount = Math.max(0, tableLoadingRequestCount - 1);
    }

    if (tableLoadingRequestCount === 0) {
      hasCompletedInitialTableLoad = true;
      setInitialTableLoadingState(false);
      hideTableLoadingOverlay(true);
    }
  }

  function setFiltersBodyVisibility(isVisible) {
    filtersBody.toggleClass('d-none', !isVisible);
    toggleFiltersButton.attr('aria-expanded', isVisible ? 'true' : 'false');
    toggleFiltersButton.html(
      '<i data-feather="filter" class="me-50"></i> ' + (isVisible ? 'Sakrij filtere' : 'Prika\u017ei filtere')
    );
    replaceFeather();
  }

  function runReleasedDocumentsRequest(requestData, callback) {
    beginTableLoadingRequest();
    hidePageError();

    $.ajax({
      url: dataUrl,
      method: 'GET',
      dataType: 'json',
      data: $.extend({}, requestData, collectFilters()),
      success: function (response) {
        var rows = response && Array.isArray(response.data) ? response.data : [];

        hidePageError();

        if (response && response.message && !rows.length) {
          showPageError(response.message);
        }

        callback({
          draw: requestData.draw,
          recordsTotal: response && response.recordsTotal ? response.recordsTotal : 0,
          recordsFiltered: response && response.recordsFiltered ? response.recordsFiltered : 0,
          data: rows
        });
      },
      error: function (xhr) {
        var message = 'Gre\u0161ka pri u\u010ditavanju razdu\u017eenih materijala.';

        if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
          message = xhr.responseJSON.message;
        }

        showPageError(message);
        callback({
          draw: requestData.draw,
          recordsTotal: 0,
          recordsFiltered: 0,
          data: []
        });
      },
      complete: function () {
        finishTableLoadingRequest(true);
        window.setTimeout(updateTableLoadingOverlayBounds, 0);
      }
    });
  }

  function initializeDataTable() {
    var columns = [
      {
        data: 'document_number',
        className: 'released-doc-document-cell',
        render: function (value, type) {
          var number = (value || '').toString().trim();

          if (type !== 'display') {
            return number;
          }

          return (
            '<span class="released-doc-document-number">' +
            escapeHtml(number || '-') +
            '</span>'
          );
        }
      },
      {
        data: 'document_date',
        className: 'released-doc-date-cell',
        render: function (value, type, row) {
          return type === 'display' ? placeholder(row.document_date_display || value) : value || '';
        }
      },
      {
        data: 'predracun',
        className: 'released-doc-work-order-cell',
        render: function (value) {
          return placeholder(value);
        }
      },
      {
        data: 'narudzba',
        className: 'released-doc-order-cell',
        render: function (value) {
          return placeholder(value);
        }
      },
      {
        data: 'pozicija',
        className: 'released-doc-position-cell text-end',
        render: function (value, type) {
          return type === 'display' ? placeholder(value) : value || 0;
        }
      },
      {
        data: 'sifra',
        className: 'released-doc-code-cell',
        render: function (value) {
          return placeholder(value);
        }
      },
      {
        data: 'naziv',
        className: 'released-doc-name-cell',
        render: function (value) {
          return placeholder(value);
        }
      },
      {
        data: 'kolicina',
        className: 'released-doc-quantity-cell text-end',
        render: function (value, type) {
          return type === 'display' ? formatQuantity(value) : value || 0;
        }
      },
      {
        data: 'jm',
        className: 'released-doc-unit-cell',
        render: function (value) {
          return placeholder(value);
        }
      },
      {
        data: 'cijena',
        className: 'released-doc-price-cell text-end',
        render: function (value, type, row) {
          return type === 'display' ? formatMoney(value, row.cijena_display) : value || 0;
        }
      },
      {
        data: 'napomena',
        className: 'released-doc-note-cell',
        orderable: false,
        render: function (value) {
          return placeholder(value);
        }
      }
    ];

    if (!tableElement.length || !dataUrl) {
      showPageError('Endpoint za dokumente nije dostupan.');
      return;
    }

    if (canDeleteDocuments) {
      columns.push({
        data: null,
        className: 'released-doc-action-cell text-end',
        orderable: false,
        searchable: false,
        render: function () {
          return (
            '<div class="released-doc-actions-group">' +
              '<button type="button" class="btn btn-sm btn-outline-danger released-doc-action-btn released-doc-delete-btn" title="Izbri\u0161i dokument" aria-label="Izbri\u0161i dokument">' +
                '<i data-feather="trash-2"></i>' +
              '</button>' +
            '</div>'
          );
        }
      });
    }

    setInitialTableLoadingState(true);

    tableElement.off('.releasedDocs');
    tableElement.on('draw.dt.releasedDocs', function () {
      updateTableLoadingOverlayBounds();
    });

    $(window).off('.releasedDocs');
    $(window).on('resize.releasedDocs orientationchange.releasedDocs', function () {
      updateTableLoadingOverlayBounds();
    });

    dataTable = tableElement.DataTable({
      processing: false,
      serverSide: true,
      searching: true,
      searchDelay: 500,
      pageLength: 10,
      lengthMenu: [
        [10, 25, 50, 100],
        [10, 25, 50, 100]
      ],
      order: [[0, 'desc']],
      ajax: function (requestData, callback) {
        runReleasedDocumentsRequest(requestData, callback);
      },
      columns: columns,
      language: {
        search: 'Brza pretraga:',
        searchPlaceholder: 'Dokument, RN, narud\u017eba, \u0161ifra ili naziv',
        lengthMenu: 'Prika\u017ei _MENU_ zapisa',
        info: 'Prikaz _START_ do _END_ od _TOTAL_ zapisa',
        infoEmpty: 'Nema zapisa',
        infoFiltered: '(filtrirano od _MAX_ ukupno)',
        zeroRecords: 'Nema prona\u0111enih dokumenata.',
        emptyTable: 'Nema dokumenata za prikaz.',
        processing: 'U\u010ditavanje...',
        paginate: {
          first: 'Prva',
          last: 'Zadnja',
          next: 'Sljede\u0107a',
          previous: 'Prethodna'
        }
      },
      drawCallback: function () {
        updateTableLoadingOverlayBounds();
        replaceFeather();
      },
      initComplete: function () {
        updateActiveFilters();
        updateTableLoadingOverlayBounds();
        replaceFeather();
      }
    });

    tableElement.find('tbody').on('click', '.released-doc-delete-btn', function (event) {
      var rowData;

      if (!canDeleteDocuments) {
        return;
      }

      event.preventDefault();
      event.stopPropagation();
      rowData = resolveRowDataFromTrigger(this);

      if (!rowData) {
        return;
      }

      confirmDeleteDocument(rowData);
    });
  }

  initializeDatePickers();
  setFiltersBodyVisibility(false);
  initializeDataTable();

  applyFiltersButton.on('click', function () {
    reloadTable();
  });

  clearFiltersButton.on('click', function () {
    clearFilters();
  });

  toggleFiltersButton.on('click', function () {
    setFiltersBodyVisibility(filtersBody.hasClass('d-none'));
  });

  $('.released-doc-filter-input').on('keydown', function (event) {
    if (event.key === 'Enter') {
      event.preventDefault();
      reloadTable();
    }
  });

  replaceFeather();
});
