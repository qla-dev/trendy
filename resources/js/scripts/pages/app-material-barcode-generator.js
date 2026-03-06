$(function () {
  'use strict';

  var tableElement = $('#material-barcode-table');
  var config = window.materialBarcodeGeneratorConfig || {};
  var dataUrl = (config.dataUrl || '').toString().trim();
  var stockAdjustUrl = (config.stockAdjustUrl || '').toString().trim();
  var canAdjustStock = !!config.canAdjustStock;
  var csrfToken = $('meta[name="csrf-token"]').attr('content') || '';
  var modalElement = document.getElementById('material-barcode-modal');
  var modalNameElement = document.getElementById('material-barcode-modal-name');
  var modalCodeElement = document.getElementById('material-barcode-modal-code');
  var modalErrorElement = document.getElementById('material-barcode-modal-error');
  var modalPreviewElement = document.getElementById('material-barcode-modal-preview');
  var downloadButton = document.getElementById('material-barcode-download-btn');
  var stockModalElement = document.getElementById('material-stock-modal');
  var stockModalSubtitleElement = document.getElementById('material-stock-modal-subtitle');
  var stockModalCodeElement = document.getElementById('material-stock-modal-code');
  var stockModalNameElement = document.getElementById('material-stock-modal-name');
  var stockModalUnitElement = document.getElementById('material-stock-modal-unit');
  var stockModalWarehouseElement = document.getElementById('material-stock-modal-warehouse');
  var stockModalCurrentElement = document.getElementById('material-stock-modal-current');
  var stockModalDeltaElement = document.getElementById('material-stock-modal-delta');
  var stockModalTargetInput = document.getElementById('material-stock-modal-target-input');
  var stockModalSaveButton = document.getElementById('material-stock-modal-save-btn');
  var stockModalErrorElement = document.getElementById('material-stock-modal-error');
  var stockModalSuccessElement = document.getElementById('material-stock-modal-success');
  var currentSvgMarkup = '';
  var currentMaterialCode = '';
  var activeStockMaterial = null;
  var stockRequestInFlight = false;
  var tableLoadingRequestCount = 0;
  var overlaySafetyTimer = null;
  var hasCompletedInitialTableLoad = false;
  var dataTable;
  var sortableColumnMap = canAdjustStock
    ? {
        0: 'material_code',
        1: 'material_name',
        2: 'material_um',
        3: 'material_warehouse',
        4: 'material_qty'
      }
    : {
        0: 'material_code',
        1: 'material_name',
        2: 'material_um',
        3: 'material_qty'
      };

  var code39Patterns = {
    '0': 'nnnwwnwnn',
    '1': 'wnnwnnnnw',
    '2': 'nnwwnnnnw',
    '3': 'wnwwnnnnn',
    '4': 'nnnwwnnnw',
    '5': 'wnnwwnnnn',
    '6': 'nnwwwnnnn',
    '7': 'nnnwnnwnw',
    '8': 'wnnwnnwnn',
    '9': 'nnwwnnwnn',
    A: 'wnnnnwnnw',
    B: 'nnwnnwnnw',
    C: 'wnwnnwnnn',
    D: 'nnnnwwnnw',
    E: 'wnnnwwnnn',
    F: 'nnwnwwnnn',
    G: 'nnnnnwwnw',
    H: 'wnnnnwwnn',
    I: 'nnwnnwwnn',
    J: 'nnnnwwwnn',
    K: 'wnnnnnnww',
    L: 'nnwnnnnww',
    M: 'wnwnnnnwn',
    N: 'nnnnwnnww',
    O: 'wnnnwnnwn',
    P: 'nnwnwnnwn',
    Q: 'nnnnnnwww',
    R: 'wnnnnnwwn',
    S: 'nnwnnnwwn',
    T: 'nnnnwnwwn',
    U: 'wwnnnnnnw',
    V: 'nwwnnnnnw',
    W: 'wwwnnnnnn',
    X: 'nwnnwnnnw',
    Y: 'wwnnwnnnn',
    Z: 'nwwnwnnnn',
    '-': 'nwnnnnwnw',
    '.': 'wwnnnnwnn',
    ' ': 'nwwnnnwnn',
    '$': 'nwnwnwnnn',
    '/': 'nwnwnnnwn',
    '+': 'nwnnnwnwn',
    '%': 'nnnwnwnwn',
    '*': 'nwnnwnwnn'
  };

  function escapeHtml(value) {
    return $('<div/>').text(value === null || value === undefined ? '' : String(value)).html();
  }

  function escapeXml(value) {
    return String(value === null || value === undefined ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&apos;');
  }

  function toFiniteNumber(value, fallbackValue) {
    var numericValue = Number(value);
    return Number.isFinite(numericValue) ? numericValue : fallbackValue;
  }

  function formatQuantity(value) {
    var numericValue = Number(value);

    if (!Number.isFinite(numericValue)) {
      return '0';
    }

    if (Math.abs(numericValue % 1) < 0.000001) {
      return numericValue.toLocaleString('hr-HR', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
      });
    }

    return numericValue.toLocaleString('hr-HR', {
      minimumFractionDigits: 0,
      maximumFractionDigits: 3
    });
  }

  function formatSignedQuantity(value) {
    var numericValue = Number(value);

    if (!Number.isFinite(numericValue)) {
      return '0';
    }

    if (numericValue > 0) {
      return '+' + formatQuantity(numericValue);
    }

    return formatQuantity(numericValue);
  }

  function formatInputQuantity(value) {
    var numericValue = Number(value);

    if (!Number.isFinite(numericValue)) {
      return '';
    }

    if (Math.abs(numericValue % 1) < 0.000001) {
      return String(Math.trunc(numericValue));
    }

    return numericValue.toFixed(3).replace(/\.?0+$/, '');
  }

  function normalizeBarcodeValue(value) {
    return (value || '').toString().trim().toUpperCase();
  }

  function sanitizeFileName(value) {
    var normalizedValue = (value || '').toString().trim().replace(/[^0-9A-Za-z\-_]+/g, '-');
    normalizedValue = normalizedValue.replace(/-+/g, '-').replace(/^-|-$/g, '');

    return normalizedValue || 'barcode-etiketa';
  }

  function resetModalError() {
    if (!modalErrorElement) {
      return;
    }

    modalErrorElement.classList.add('d-none');
    modalErrorElement.textContent = '';
  }

  function showModalError(message) {
    if (!modalErrorElement) {
      return;
    }

    modalErrorElement.textContent = message || 'Ne mogu generisati barcode etiketu.';
    modalErrorElement.classList.remove('d-none');
  }

  function setEmptyPreview(message) {
    if (!modalPreviewElement) {
      return;
    }

    modalPreviewElement.innerHTML = '<div class="material-barcode-modal-empty">' + escapeHtml(message || '') + '</div>';
  }

  function labelFontSize(label) {
    var length = (label || '').length;

    if (length > 56) {
      return 12;
    }

    if (length > 36) {
      return 13;
    }

    return 15;
  }

  function buildBarcodeSvg(barcodeValue, materialName) {
    var normalizedBarcode = normalizeBarcodeValue(barcodeValue);
    var encodedValue;
    var quietZone = 18;
    var narrow = 3;
    var wide = 7;
    var gap = 3;
    var topPadding = 34;
    var barHeight = 132;
    var bottomPadding = 36;
    var width = quietZone * 2;
    var x = quietZone;
    var rects = [];
    var label = (materialName || '').toString().trim();
    var codeLabel = normalizedBarcode;
    var labelSize = labelFontSize(label);

    if (!normalizedBarcode) {
      throw new Error('Materijal nema vrijednost za barcode.');
    }

    encodedValue = '*' + normalizedBarcode + '*';

    encodedValue.split('').forEach(function (character, index) {
      var pattern = code39Patterns[character];

      if (!pattern) {
        throw new Error('Barcode sadrzi znak koji nije podrzan za SVG etiketu: ' + character);
      }

      pattern.split('').forEach(function (unit) {
        width += unit === 'w' ? wide : narrow;
      });

      if (index < encodedValue.length - 1) {
        width += gap;
      }
    });

    encodedValue.split('').forEach(function (character, characterIndex) {
      var pattern = code39Patterns[character];

      pattern.split('').forEach(function (unit, patternIndex) {
        var segmentWidth = unit === 'w' ? wide : narrow;
        var isBar = patternIndex % 2 === 0;

        if (isBar) {
          rects.push(
            '<rect x="' + x + '" y="' + topPadding + '" width="' + segmentWidth + '" height="' + barHeight + '" fill="#111111" />'
          );
        }

        x += segmentWidth;
      });

      if (characterIndex < encodedValue.length - 1) {
        x += gap;
      }
    });

    return [
      '<?xml version="1.0" encoding="UTF-8"?>',
      '<svg xmlns="http://www.w3.org/2000/svg" width="' + width + '" height="' + (topPadding + barHeight + bottomPadding) + '" viewBox="0 0 ' + width + ' ' + (topPadding + barHeight + bottomPadding) + '" role="img" aria-label="' + escapeXml(codeLabel) + '">',
      '<rect width="100%" height="100%" fill="#ffffff" />',
      '<text x="' + width / 2 + '" y="18" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="' + labelSize + '" font-weight="600" fill="#1f2430">' + escapeXml(label) + '</text>',
      rects.join(''),
      '<text x="' + width / 2 + '" y="' + (topPadding + barHeight + 22) + '" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="16" letter-spacing="1.2" fill="#1f2430">' + escapeXml(codeLabel) + '</text>',
      '</svg>'
    ].join('');
  }

  function renderMaterialBarcode(materialRow) {
    var materialCode = materialRow && materialRow.barcode_value ? materialRow.barcode_value : '';
    var materialName = materialRow && materialRow.material_name ? materialRow.material_name : '';
    var svgMarkup;

    resetModalError();
    currentSvgMarkup = '';
    currentMaterialCode = '';

    if (modalNameElement) {
      modalNameElement.textContent = materialName || '-';
    }

    if (modalCodeElement) {
      modalCodeElement.textContent = materialCode ? 'Barcode / sifra: ' + materialCode : '-';
    }

    if (downloadButton) {
      downloadButton.disabled = true;
    }

    try {
      svgMarkup = buildBarcodeSvg(materialCode, materialName);
      currentSvgMarkup = svgMarkup;
      currentMaterialCode = materialCode;

      if (modalPreviewElement) {
        modalPreviewElement.innerHTML = svgMarkup;
      }

      if (downloadButton) {
        downloadButton.disabled = false;
      }
    } catch (error) {
      setEmptyPreview('Barcode etiketa nije dostupna za odabrani materijal.');
      showModalError(error && error.message ? error.message : 'Ne mogu generisati barcode etiketu.');
    }
  }

  function downloadCurrentSvg() {
    var blob;
    var objectUrl;
    var link;

    if (!currentSvgMarkup) {
      return;
    }

    blob = new Blob([currentSvgMarkup], { type: 'image/svg+xml;charset=utf-8' });
    objectUrl = window.URL.createObjectURL(blob);
    link = document.createElement('a');
    link.href = objectUrl;
    link.download = sanitizeFileName(currentMaterialCode) + '.svg';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.setTimeout(function () {
      window.URL.revokeObjectURL(objectUrl);
    }, 150);
  }

  function ensureTableLoadingOverlay() {
    var overlayHost = tableElement.closest('.card-datatable');
    var overlay;

    if (!overlayHost.length) {
      return null;
    }

    overlayHost.addClass('invoice-table-overlay-host');
    overlay = overlayHost.find('.invoice-table-loading-overlay').first();

    if (overlay.length) {
      return overlay;
    }

    overlayHost.append(
      '<div class="invoice-table-loading-overlay" aria-hidden="true">' +
        '<div class="invoice-table-loading-overlay-content">' +
          '<div class="spinner-border invoice-table-loading-spinner" role="status" aria-hidden="true"></div>' +
          '<div class="invoice-table-loading-message">Ucitavanje rezultata</div>' +
        '</div>' +
      '</div>'
    );

    return overlayHost.find('.invoice-table-loading-overlay').first();
  }

  function updateTableLoadingOverlayBounds() {
    var overlayHost = tableElement.closest('.card-datatable');
    var overlay = overlayHost.find('.invoice-table-loading-overlay').first();
    var hostElement = overlayHost.get(0);
    var tableNode = tableElement.get(0);
    var bodyElement = tableElement.find('tbody').get(0);
    var hostRect;
    var bodyRect;
    var spacerRowRect;
    var tableRect;
    var headerHeight;
    var top;
    var left;
    var width;
    var height;
    var minimumBodyHeight = 120;
    var spacerRowElement;
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
      spacerRowElement = bodyElement.querySelector('.material-barcode-loading-spacer-row');
      bodyRect = bodyElement.getBoundingClientRect();

      if (spacerRowElement) {
        spacerRowRect = spacerRowElement.getBoundingClientRect();

        if (
          Number.isFinite(spacerRowRect.width) &&
          spacerRowRect.width > 0 &&
          Number.isFinite(spacerRowRect.height) &&
          spacerRowRect.height > 0
        ) {
          top = spacerRowRect.top - hostRect.top;
          left = spacerRowRect.left - hostRect.left;
          width = spacerRowRect.width;
          height = spacerRowRect.height;
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

  function setInitialBottomControlsRowVisible(visible) {
    var wrapper = tableElement.closest('.dataTables_wrapper');
    var bottomRow;

    if (!wrapper.length) {
      return;
    }

    bottomRow = wrapper.children('.row').eq(2);
    if (!bottomRow.length) {
      return;
    }

    bottomRow.css('display', visible ? '' : 'none');
  }

  function setInitialTableLoadingState(active) {
    var overlayHost = tableElement.closest('.card-datatable');

    if (!overlayHost.length) {
      return;
    }

    if (active && hasCompletedInitialTableLoad) {
      return;
    }

    overlayHost.toggleClass('material-barcode-initial-loading', !!active);
  }

  function hideTableLoadingOverlay(force) {
    var overlayHost = tableElement.closest('.card-datatable');
    var overlay = overlayHost.find('.invoice-table-loading-overlay').first();

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

  function finishTableLoadingRequest() {
    tableLoadingRequestCount = Math.max(0, tableLoadingRequestCount - 1);

    if (tableLoadingRequestCount === 0) {
      hasCompletedInitialTableLoad = true;
      setInitialTableLoadingState(false);
      setInitialBottomControlsRowVisible(true);
      hideTableLoadingOverlay(true);
    }
  }

  function scheduleOverlaySafetyHide() {
    if (overlaySafetyTimer) {
      clearTimeout(overlaySafetyTimer);
    }

    overlaySafetyTimer = setTimeout(function () {
      if (tableLoadingRequestCount === 0) {
        hideTableLoadingOverlay(true);
      }
    }, 1000);
  }

  function updateStockModalDetails(materialRow) {
    var materialQty = toFiniteNumber(materialRow && materialRow.material_qty, 0);
    var materialWarehouse = materialRow && materialRow.material_warehouse ? materialRow.material_warehouse : '';

    if (stockModalSubtitleElement) {
      stockModalSubtitleElement.textContent = 'Materijal: ' + (materialRow && materialRow.material_name ? materialRow.material_name : '-');
    }

    if (stockModalCodeElement) {
      stockModalCodeElement.textContent = materialRow && materialRow.material_code ? materialRow.material_code : '-';
    }

    if (stockModalNameElement) {
      stockModalNameElement.textContent = materialRow && materialRow.material_name ? materialRow.material_name : '-';
    }

    if (stockModalUnitElement) {
      stockModalUnitElement.textContent = materialRow && materialRow.material_um ? materialRow.material_um : '-';
    }

    if (stockModalWarehouseElement) {
      stockModalWarehouseElement.textContent = materialWarehouse || '-';
    }

    if (stockModalCurrentElement) {
      stockModalCurrentElement.textContent = formatQuantity(materialQty);
    }
  }

  function clearStockMessages() {
    if (stockModalErrorElement) {
      stockModalErrorElement.classList.add('d-none');
      stockModalErrorElement.textContent = '';
    }

    if (stockModalSuccessElement) {
      stockModalSuccessElement.classList.add('d-none');
      stockModalSuccessElement.textContent = '';
    }
  }

  function setStockMessage(type, message) {
    clearStockMessages();

    if (type === 'success' && stockModalSuccessElement) {
      stockModalSuccessElement.textContent = message;
      stockModalSuccessElement.classList.remove('d-none');
      return;
    }

    if (type === 'error' && stockModalErrorElement) {
      stockModalErrorElement.textContent = message;
      stockModalErrorElement.classList.remove('d-none');
    }
  }

  function syncStockDeltaPreview() {
    var currentQty = toFiniteNumber(activeStockMaterial && activeStockMaterial.material_qty, 0);
    var nextQty = stockModalTargetInput ? Number(stockModalTargetInput.value) : NaN;
    var deltaValue = Number.isFinite(nextQty) ? nextQty - currentQty : 0;

    if (stockModalDeltaElement) {
      stockModalDeltaElement.textContent = formatSignedQuantity(deltaValue);
    }
  }

  function openStockModal(materialRow) {
    var modalInstance;

    if (!canAdjustStock || !stockModalElement || !window.bootstrap || !window.bootstrap.Modal || !materialRow) {
      return;
    }

    activeStockMaterial = $.extend({}, materialRow);
    clearStockMessages();
    updateStockModalDetails(activeStockMaterial);

    if (stockModalTargetInput) {
      stockModalTargetInput.value = formatInputQuantity(activeStockMaterial.material_qty);
    }

    syncStockDeltaPreview();
    modalInstance = window.bootstrap.Modal.getOrCreateInstance(stockModalElement);
    modalInstance.show();
  }

  function resolveRowData(rowElement) {
    var rowData = dataTable.row(rowElement).data();

    if (!rowData && rowElement.hasClass('child')) {
      rowData = dataTable.row(rowElement.prev()).data();
    }

    return rowData || null;
  }

  function resolveRowDataFromTrigger(triggerElement) {
    return resolveRowData($(triggerElement).closest('tr'));
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

  function submitStockAdjustment() {
    var targetValueRaw;
    var targetValue;
    var requestHeaders = {
      Accept: 'application/json'
    };

    if (!canAdjustStock || !stockAdjustUrl || !activeStockMaterial || stockRequestInFlight) {
      return;
    }

    targetValueRaw = stockModalTargetInput ? $.trim(stockModalTargetInput.value || '') : '';
    if (targetValueRaw === '') {
      setStockMessage('error', 'Unesite novu vrijednost zalihe.');
      return;
    }

    targetValue = Number(targetValueRaw);
    if (!Number.isFinite(targetValue)) {
      setStockMessage('error', 'Nova zaliha mora biti broj.');
      return;
    }

    if (csrfToken) {
      requestHeaders['X-CSRF-TOKEN'] = csrfToken;
    }

    stockRequestInFlight = true;
    clearStockMessages();

    if (stockModalSaveButton) {
      stockModalSaveButton.disabled = true;
    }

    $.ajax({
      url: stockAdjustUrl,
      method: 'POST',
      dataType: 'json',
      contentType: 'application/json; charset=UTF-8',
      headers: requestHeaders,
      data: JSON.stringify({
        adjust_mode: 'manual_materials_list',
        items: [
          {
            material_code: activeStockMaterial.material_code || '',
            warehouse: activeStockMaterial.material_warehouse || '',
            new_stock_value: targetValue
          }
        ]
      }),
      success: function (response) {
        var responseItems = response && response.data && Array.isArray(response.data.items) ? response.data.items : [];
        var updatedItem = responseItems.length ? responseItems[0] : null;

        activeStockMaterial.material_qty =
          updatedItem && updatedItem.new_stock_value !== undefined
            ? toFiniteNumber(updatedItem.new_stock_value, targetValue)
            : targetValue;

        if (updatedItem && updatedItem.warehouse !== undefined) {
          activeStockMaterial.material_warehouse = (updatedItem.warehouse || '').toString().trim();
        }

        updateStockModalDetails(activeStockMaterial);

        if (stockModalTargetInput) {
          stockModalTargetInput.value = formatInputQuantity(activeStockMaterial.material_qty);
        }

        syncStockDeltaPreview();
        setStockMessage('success', response && response.message ? response.message : 'Zaliha je uspjesno azurirana.');

        if (dataTable) {
          dataTable.ajax.reload(null, false);
        }
      },
      error: function (xhr) {
        setStockMessage('error', extractAjaxErrorMessage(xhr, 'Ne mogu azurirati zalihu.'));
      },
      complete: function () {
        stockRequestInFlight = false;

        if (stockModalSaveButton) {
          stockModalSaveButton.disabled = false;
        }
      }
    });
  }

  function runTableRequest(requestData, callback, requestPayload, skipBeginLoading) {
    if (!skipBeginLoading) {
      beginTableLoadingRequest();
    }

    $.ajax({
      url: dataUrl,
      method: 'GET',
      dataType: 'json',
      data: requestPayload,
      success: function (response) {
        callback({
          draw: requestData.draw,
          recordsTotal: response.recordsTotal || 0,
          recordsFiltered: response.recordsFiltered || 0,
          data: response.data || []
        });
      },
      error: function () {
        callback({
          draw: requestData.draw,
          recordsTotal: 0,
          recordsFiltered: 0,
          data: []
        });
      },
      complete: function () {
        finishTableLoadingRequest();
        scheduleOverlaySafetyHide();
      }
    });
  }

  if (!tableElement.length || !dataUrl) {
    return;
  }

  $(window).on('resize.materialBarcodeTableOverlay', function () {
    updateTableLoadingOverlayBounds();
  });

  dataTable = tableElement.DataTable({
    processing: false,
    serverSide: true,
    responsive: true,
    pageLength: 25,
    lengthMenu: [10, 25, 50, 100],
    searchDelay: 250,
    order: [[0, 'asc']],
    ajax: function (requestData, callback) {
      var firstOrder = requestData.order && requestData.order.length ? requestData.order[0] : null;
      var orderColumnIndex = firstOrder && firstOrder.column !== undefined ? parseInt(firstOrder.column, 10) : NaN;
      var sortBy = Number.isInteger(orderColumnIndex) ? sortableColumnMap[orderColumnIndex] || 'material_code' : 'material_code';
      var requestPayload = {
        draw: requestData.draw,
        start: requestData.start || 0,
        length: requestData.length || 25,
        sort_by: sortBy,
        sort_dir: firstOrder && firstOrder.dir === 'desc' ? 'desc' : 'asc',
        search: {
          value: requestData.search && requestData.search.value ? requestData.search.value : ''
        }
      };

      runTableRequest(requestData, callback, requestPayload, false);
    },
    columns: (function () {
      var columns = [
        { data: 'material_code' },
        { data: 'material_name' },
        { data: 'material_um' }
      ];

      if (canAdjustStock) {
        columns.push({ data: 'material_warehouse' });
      }

      columns.push({ data: 'material_qty' });

      if (canAdjustStock) {
        columns.push({
          data: null,
          orderable: false,
          searchable: false
        });
      }

      return columns;
    })(),
    columnDefs: (function () {
      var columnDefs = [
        {
          targets: 0,
          className: 'material-code-cell',
          render: function (data) {
            var value = (data || '').toString().trim();
            return value ? escapeHtml(value) : '<span class="text-muted">-</span>';
          }
        },
        {
          targets: 1,
          render: function (data) {
            var value = (data || '').toString().trim();
            return value ? escapeHtml(value) : '<span class="text-muted">-</span>';
          }
        },
        {
          targets: 2,
          className: 'material-unit-cell',
          render: function (data) {
            var value = (data || '').toString().trim();
            return value ? escapeHtml(value) : '<span class="text-muted">-</span>';
          }
        }
      ];
      var stockColumnIndex = canAdjustStock ? 4 : 3;

      if (canAdjustStock) {
        columnDefs.push({
          targets: 3,
          className: 'material-warehouse-cell',
          render: function (data) {
            var value = (data || '').toString().trim();
            return value ? escapeHtml(value) : '<span class="text-muted">-</span>';
          }
        });
      }

      columnDefs.push({
        targets: stockColumnIndex,
        className: 'text-end material-stock-cell',
        render: function (data) {
          return escapeHtml(formatQuantity(data));
        }
      });

      if (canAdjustStock) {
        columnDefs.push({
          targets: stockColumnIndex + 1,
          className: 'text-end material-actions-cell',
          render: function () {
            return (
              '<button type="button" class="btn btn-sm btn-outline-primary material-stock-adjust-btn">' +
                '<i class="fa fa-edit"></i>' +
                '<span>Prilagodi</span>' +
              '</button>'
            );
          }
        });
      }

      return columnDefs;
    })(),
    language: {
      search: 'Pretraga:',
      lengthMenu: 'Prikazi _MENU_ redova',
      info: 'Prikaz _START_ do _END_ od _TOTAL_ materijala',
      infoEmpty: 'Nema materijala za prikaz',
      infoFiltered: '(filtrirano od _MAX_ ukupno)',
      emptyTable: 'Nema materijala za prikaz.',
      zeroRecords: 'Nema rezultata za zadanu pretragu.',
      paginate: {
        first: 'Prva',
        last: 'Zadnja',
        next: 'Sljedeca',
        previous: 'Prethodna'
      }
    }
  });

  setInitialTableLoadingState(true);
  setInitialBottomControlsRowVisible(false);

  tableElement.find('tbody').on('click', '.material-stock-adjust-btn', function (event) {
    var rowData;

    if (!canAdjustStock) {
      return;
    }

    event.preventDefault();
    event.stopPropagation();
    rowData = resolveRowDataFromTrigger(this);

    if (!rowData) {
      return;
    }

    openStockModal(rowData);
  });

  tableElement.find('tbody').on('click', 'tr', function (event) {
    var rowElement = $(this);
    var rowData;
    var modalInstance;

    if ($(event.target).closest('.material-stock-adjust-btn').length) {
      return;
    }

    rowData = resolveRowData(rowElement);

    if (!rowData || !modalElement || !window.bootstrap || !window.bootstrap.Modal) {
      return;
    }

    renderMaterialBarcode(rowData);
    modalInstance = window.bootstrap.Modal.getOrCreateInstance(modalElement);
    modalInstance.show();
  });

  if (downloadButton) {
    downloadButton.addEventListener('click', function () {
      downloadCurrentSvg();
    });
  }

  if (stockModalTargetInput) {
    stockModalTargetInput.addEventListener('input', syncStockDeltaPreview);
    stockModalTargetInput.addEventListener('keydown', function (event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        submitStockAdjustment();
      }
    });
  }

  if (stockModalSaveButton) {
    stockModalSaveButton.addEventListener('click', function () {
      submitStockAdjustment();
    });
  }

  if (modalElement) {
    modalElement.addEventListener('hidden.bs.modal', function () {
      currentSvgMarkup = '';
      currentMaterialCode = '';
      resetModalError();

      if (modalNameElement) {
        modalNameElement.textContent = '-';
      }

      if (modalCodeElement) {
        modalCodeElement.textContent = '-';
      }

      if (downloadButton) {
        downloadButton.disabled = true;
      }

      setEmptyPreview('Kliknite materijal u tabeli za pregled barcode etikete.');
    });
  }

  if (stockModalElement) {
    stockModalElement.addEventListener('hidden.bs.modal', function () {
      activeStockMaterial = null;
      stockRequestInFlight = false;
      clearStockMessages();

      if (stockModalSubtitleElement) {
        stockModalSubtitleElement.textContent = '-';
      }

      if (stockModalCodeElement) {
        stockModalCodeElement.textContent = '-';
      }

      if (stockModalNameElement) {
        stockModalNameElement.textContent = '-';
      }

      if (stockModalUnitElement) {
        stockModalUnitElement.textContent = '-';
      }

      if (stockModalWarehouseElement) {
        stockModalWarehouseElement.textContent = '-';
      }

      if (stockModalCurrentElement) {
        stockModalCurrentElement.textContent = '0';
      }

      if (stockModalDeltaElement) {
        stockModalDeltaElement.textContent = '0';
      }

      if (stockModalTargetInput) {
        stockModalTargetInput.value = '';
      }

      if (stockModalSaveButton) {
        stockModalSaveButton.disabled = false;
      }
    });
  }
});
