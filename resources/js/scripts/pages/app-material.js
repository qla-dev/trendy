$(function () {
  'use strict';

  var tableElement = $('#material-barcode-table');
  var config = window.materialBarcodeGeneratorConfig || {};
  var dataUrl = (config.dataUrl || '').toString().trim();
  var stockAdjustUrl = (config.stockAdjustUrl || '').toString().trim();
  var createUrl = (config.createUrl || '').toString().trim();
  var deleteUrl = (config.deleteUrl || '').toString().trim();
  var canSeeWarehouse = !!config.canSeeWarehouse;
  var canAdjustStock = !!config.canAdjustStock;
  var canCreateMaterial = !!config.canCreateMaterial;
  var canCopyMaterial = !!config.canCopyMaterial;
  var canDeleteMaterial = !!config.canDeleteMaterial;
  var canManageMaterialActions = canAdjustStock || canCopyMaterial || canDeleteMaterial;
  var autoOpenCreateMaterial = !!config.autoOpenCreateMaterial;
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
  var createModalElement = document.getElementById('material-create-modal');
  var createModalTitleElement = document.getElementById('material-create-modal-label');
  var createModalSubtitleElement = document.getElementById('material-create-modal-subtitle');
  var createModalHelpElement = document.getElementById('material-create-help-text');
  var createModalOpenButton = document.getElementById('material-create-open-btn');
  var warehouseFilterInput = document.getElementById('material-warehouse-filter');
  var bulkDownloadButton = null;
  var createModalCodeInput = document.getElementById('material-create-code-input');
  var createModalNameInput = document.getElementById('material-create-name-input');
  var createModalUnitInput = document.getElementById('material-create-unit-input');
  var createModalWarehouseInput = document.getElementById('material-create-warehouse-input');
  var createModalStockInput = document.getElementById('material-create-stock-input');
  var createModalSetInput = document.getElementById('material-create-set-input');
  var createModalSaveButton = document.getElementById('material-create-modal-save-btn');
  var createModalErrorElement = document.getElementById('material-create-modal-error');
  var createModalSuccessElement = document.getElementById('material-create-modal-success');
  var currentSvgMarkup = '';
  var currentMaterialCode = '';
  var activeStockMaterial = null;
  var activeCopyMaterial = null;
  var stockRequestInFlight = false;
  var createRequestInFlight = false;
  var deleteRequestInFlight = false;
  var bulkDownloadInFlight = false;
  var tableLoadingRequestCount = 0;
  var overlaySafetyTimer = null;
  var hasCompletedInitialTableLoad = false;
  var lastTableRecordsFiltered = 0;
  var dataTable;
  var bulkDownloadButtonDefaultHtml = '<i class="fa fa-download me-50"></i> Preuzmi sve etikete';
  var defaultCreateModalTitle = createModalTitleElement ? createModalTitleElement.textContent : 'Dodaj novi materijal';
  var defaultCreateModalSubtitle = createModalSubtitleElement
    ? createModalSubtitleElement.textContent
    : 'Kreiraj novi katalog materijal i početnu zalihu.';
  var defaultCreateModalHelp = createModalHelpElement
    ? createModalHelpElement.textContent
    : 'Novi materijal će biti upisan u katalog, a početna zaliha će odmah biti evidentirana na odabranom skladištu.';
  var defaultCreateModalSaveHtml = createModalSaveButton ? createModalSaveButton.innerHTML : '';
  var sortableColumnMap = canSeeWarehouse
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

  function ensureSelectOption(selectElement, optionValue, emptyLabel) {
    var normalizedValue;
    var optionElement;

    if (!selectElement) {
      return;
    }

    normalizedValue = (optionValue || '').toString().trim();

    if (!normalizedValue) {
      selectElement.value = '';
      return;
    }

    optionElement = Array.prototype.find.call(selectElement.options || [], function (optionNode) {
      return ((optionNode && optionNode.value) || '').toString().trim() === normalizedValue;
    });

    if (!optionElement) {
      optionElement = document.createElement('option');
      optionElement.value = normalizedValue;
      optionElement.textContent = normalizedValue || emptyLabel || '-';
      selectElement.appendChild(optionElement);
    }

    selectElement.value = normalizedValue;
  }

  function getWarehouseFilterValue() {
    return warehouseFilterInput ? $.trim(warehouseFilterInput.value || '') : '';
  }

  function resolveSortMeta(orderData) {
    var firstOrder = orderData && orderData.length ? orderData[0] : null;
    var orderColumnIndex = NaN;
    var sortDir = 'asc';

    if (firstOrder) {
      if (firstOrder.column !== undefined) {
        orderColumnIndex = parseInt(firstOrder.column, 10);
        sortDir = firstOrder.dir === 'desc' ? 'desc' : 'asc';
      } else {
        orderColumnIndex = parseInt(firstOrder[0], 10);
        sortDir = firstOrder[1] === 'desc' ? 'desc' : 'asc';
      }
    }

    return {
      sortBy: Number.isInteger(orderColumnIndex) ? sortableColumnMap[orderColumnIndex] || 'material_code' : 'material_code',
      sortDir: sortDir
    };
  }

  function buildTableRequestPayload(requestData, overrides) {
    var overrideValues = overrides || {};
    var sortMeta = overrideValues.sortMeta || resolveSortMeta(requestData && requestData.order ? requestData.order : []);
    var startValue = overrideValues.start !== undefined ? overrideValues.start : requestData && requestData.start ? requestData.start : 0;
    var lengthValue = overrideValues.length !== undefined ? overrideValues.length : requestData && requestData.length ? requestData.length : 25;
    var searchValue = overrideValues.searchValue !== undefined
      ? overrideValues.searchValue
      : requestData && requestData.search && requestData.search.value
        ? requestData.search.value
        : '';
    var warehouseValue = overrideValues.warehouse !== undefined ? overrideValues.warehouse : getWarehouseFilterValue();

    return {
      draw: requestData && requestData.draw ? requestData.draw : 0,
      start: startValue,
      length: lengthValue,
      sort_by: sortMeta.sortBy,
      sort_dir: sortMeta.sortDir,
      warehouse: warehouseValue,
      search: {
        value: searchValue
      }
    };
  }

  function createBulkDownloadButton() {
    var button = document.createElement('button');

    button.type = 'button';
    button.id = 'material-bulk-download-btn';
    button.className = 'btn material-action-btn material-barcode-preview-btn material-bulk-download-btn d-none';
    button.disabled = true;
    button.innerHTML = bulkDownloadButtonDefaultHtml;
    button.addEventListener('click', function () {
      downloadAllFilteredLabels();
    });

    return button;
  }

  function ensureBulkDownloadButton() {
    var wrapper = tableElement.closest('.dataTables_wrapper');
    var firstRow;
    var leftColumn;
    var lengthContainer;
    var inlineControls;

    if (!wrapper.length) {
      return null;
    }

    if (!bulkDownloadButton) {
      bulkDownloadButton = createBulkDownloadButton();
    }

    firstRow = wrapper.children('.row').first();
    leftColumn = firstRow.children('[class*="col-"]').first();

    if (!leftColumn.length) {
      return bulkDownloadButton;
    }

    lengthContainer = leftColumn.find('.dataTables_length').first();

    if (!lengthContainer.length) {
      return bulkDownloadButton;
    }

    inlineControls = leftColumn.find('.material-table-inline-controls').first();

    if (!inlineControls.length) {
      inlineControls = $('<div class="material-table-inline-controls"></div>');
      lengthContainer.before(inlineControls);
    }

    if (!inlineControls.find('.dataTables_length').length) {
      inlineControls.append(lengthContainer);
    }

    if (!inlineControls.find('#material-bulk-download-btn').length) {
      inlineControls.append(bulkDownloadButton);
    }

    return bulkDownloadButton;
  }

  function setBulkDownloadButtonLoadingState(active) {
    var button = ensureBulkDownloadButton();

    if (!button) {
      return;
    }

    if (active) {
      button.disabled = true;
      button.innerHTML = '<span class="spinner-border spinner-border-sm me-50" role="status" aria-hidden="true"></span> Priprema ZIP-a';
      return;
    }

    button.innerHTML = bulkDownloadButtonDefaultHtml;
  }

  function updateBulkDownloadButtonState() {
    var button = ensureBulkDownloadButton();
    var hasWarehouseFilter = !!getWarehouseFilterValue();

    if (!button) {
      return;
    }

    button.classList.toggle('d-none', !hasWarehouseFilter);
    button.disabled = bulkDownloadInFlight || !hasWarehouseFilter || lastTableRecordsFiltered < 1;
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
      modalCodeElement.textContent = materialCode ? 'Barcode / šifra: ' + materialCode : '-';
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

  function triggerBlobDownload(blob, fileName) {
    var objectUrl;
    var link;

    if (!blob) {
      return;
    }

    objectUrl = window.URL.createObjectURL(blob);
    link = document.createElement('a');
    link.href = objectUrl;
    link.download = fileName;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    window.setTimeout(function () {
      window.URL.revokeObjectURL(objectUrl);
    }, 150);
  }

  function downloadCurrentSvg() {
    var blob;

    if (!currentSvgMarkup) {
      return;
    }

    blob = new Blob([currentSvgMarkup], { type: 'image/svg+xml;charset=utf-8' });
    triggerBlobDownload(blob, sanitizeFileName(currentMaterialCode) + '.svg');
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
          '<div class="invoice-table-loading-message">Učitavanje rezultata</div>' +
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

  function showBulkDownloadAlert(icon, title, text) {
    if (window.Swal && typeof window.Swal.fire === 'function') {
      window.Swal.fire({
        icon: icon,
        title: title,
        text: text,
        confirmButtonText: 'U redu',
        customClass: {
          confirmButton: icon === 'error' ? 'btn btn-danger' : icon === 'warning' ? 'btn btn-warning' : 'btn btn-primary'
        },
        buttonsStyling: false
      });
      return;
    }

    window.alert(text);
  }

  function fetchMaterialBatch(requestPayload) {
    return new Promise(function (resolve, reject) {
      $.ajax({
        url: dataUrl,
        method: 'GET',
        dataType: 'json',
        data: requestPayload,
        success: function (response) {
          resolve(response || {});
        },
        error: function (xhr) {
          reject(new Error(extractAjaxErrorMessage(xhr, 'Ne mogu učitati filtrirane materijale za etikete.')));
        }
      });
    });
  }

  function fetchAllFilteredMaterials(snapshot) {
    var collectedRows = [];
    var expectedTotal = snapshot && snapshot.expectedTotal ? snapshot.expectedTotal : 0;
    var batchSize = 100;

    function loadBatch(offset) {
      return fetchMaterialBatch(
        buildTableRequestPayload(
          {
            draw: 0,
            start: offset,
            length: batchSize,
            search: {
              value: snapshot.searchValue
            },
            order: [[snapshot.orderColumnIndex, snapshot.sortDir]]
          },
          {
            start: offset,
            length: batchSize,
            searchValue: snapshot.searchValue,
            warehouse: snapshot.warehouse,
            sortMeta: {
              sortBy: snapshot.sortBy,
              sortDir: snapshot.sortDir
            }
          }
        )
      ).then(function (response) {
        var rows = Array.isArray(response.data) ? response.data : [];
        var responseTotal = Number(response.recordsFiltered);

        if (expectedTotal < 1) {
          expectedTotal = Number.isFinite(responseTotal) && responseTotal > 0 ? responseTotal : rows.length;
        }

        collectedRows = collectedRows.concat(rows);

        if (!rows.length || collectedRows.length >= expectedTotal) {
          return collectedRows.slice(0, expectedTotal || collectedRows.length);
        }

        return loadBatch(offset + rows.length);
      });
    }

    return loadBatch(0);
  }

  function createZipFileName(warehouseName) {
    return 'etikete-' + sanitizeFileName(warehouseName || 'skladiste') + '.zip';
  }

  function resolveUniqueZipEntryName(baseName, usedNames) {
    var normalizedBaseName = sanitizeFileName(baseName || 'barcode-etiketa');
    var candidate = normalizedBaseName;
    var suffix = 2;

    while (usedNames[candidate]) {
      candidate = normalizedBaseName + '-' + suffix;
      suffix += 1;
    }

    usedNames[candidate] = true;

    return candidate;
  }

  function buildLabelsZip(materialRows) {
    var zip;
    var usedNames = {};
    var addedCount = 0;
    var skippedCount = 0;

    if (!window.JSZip) {
      return Promise.reject(new Error('ZIP biblioteka nije dostupna na stranici.'));
    }

    if (!Array.isArray(materialRows) || !materialRows.length) {
      return Promise.reject(new Error('Nema materijala za preuzimanje etiketa.'));
    }

    zip = new window.JSZip();

    materialRows.forEach(function (row) {
      var materialCode = row && row.material_code ? row.material_code : row && row.barcode_value ? row.barcode_value : '';
      var materialName = row && row.material_name ? row.material_name : '';
      var svgMarkup;
      var zipEntryName;

      try {
        svgMarkup = buildBarcodeSvg(materialCode, materialName);
        zipEntryName = resolveUniqueZipEntryName(materialCode || materialName, usedNames);
        zip.file(zipEntryName + '.svg', svgMarkup);
        addedCount += 1;
      } catch (error) {
        skippedCount += 1;
      }
    });

    if (!addedCount) {
      return Promise.reject(new Error('Nijedna etiketa nije mogla biti generisana za odabrano skladište.'));
    }

    return zip.generateAsync({ type: 'blob' }).then(function (blob) {
      return {
        blob: blob,
        addedCount: addedCount,
        skippedCount: skippedCount
      };
    });
  }

  function downloadAllFilteredLabels() {
    var warehouseValue = getWarehouseFilterValue();
    var currentOrder = dataTable && typeof dataTable.order === 'function' ? dataTable.order() : [];
    var sortMeta = resolveSortMeta(currentOrder);
    var snapshot;

    if (bulkDownloadInFlight) {
      return;
    }

    if (!warehouseValue) {
      showBulkDownloadAlert('warning', 'Odaberite skladište', 'Bulk preuzimanje etiketa je dostupno tek nakon filtera po skladištu.');
      return;
    }

    if (lastTableRecordsFiltered < 1) {
      showBulkDownloadAlert('warning', 'Nema rezultata', 'Za odabrano skladište trenutno nema materijala za preuzimanje etiketa.');
      return;
    }

    snapshot = {
      warehouse: warehouseValue,
      searchValue: dataTable && typeof dataTable.search === 'function' ? dataTable.search() : '',
      sortBy: sortMeta.sortBy,
      sortDir: sortMeta.sortDir,
      orderColumnIndex: currentOrder && currentOrder.length ? parseInt(currentOrder[0][0], 10) : 0,
      expectedTotal: lastTableRecordsFiltered
    };

    bulkDownloadInFlight = true;
    setBulkDownloadButtonLoadingState(true);
    updateBulkDownloadButtonState();

    fetchAllFilteredMaterials(snapshot)
      .then(function (materialRows) {
        return buildLabelsZip(materialRows);
      })
      .then(function (result) {
        var resultMessage = 'Preuzeto ' + result.addedCount + ' etiketa za skladište "' + snapshot.warehouse + '".';

        triggerBlobDownload(result.blob, createZipFileName(snapshot.warehouse));

        if (result.skippedCount > 0) {
          resultMessage += ' Preskoceno: ' + result.skippedCount + '.';
          showBulkDownloadAlert('warning', 'ZIP je pripremljen', resultMessage);
          return;
        }

        showBulkDownloadAlert('success', 'ZIP je pripremljen', resultMessage);
      }, function (error) {
        showBulkDownloadAlert('error', 'Preuzimanje nije uspjelo', error && error.message ? error.message : 'Ne mogu pripremiti ZIP sa etiketama.');
      })
      .then(function () {
        bulkDownloadInFlight = false;
        setBulkDownloadButtonLoadingState(false);
        updateBulkDownloadButtonState();
      });
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
        setStockMessage('success', response && response.message ? response.message : 'Zaliha je uspješno ažurirana.');

        if (dataTable) {
          dataTable.ajax.reload(null, false);
        }
      },
      error: function (xhr) {
        setStockMessage('error', extractAjaxErrorMessage(xhr, 'Ne mogu ažurirati zalihu.'));
      },
      complete: function () {
        stockRequestInFlight = false;

        if (stockModalSaveButton) {
          stockModalSaveButton.disabled = false;
        }
      }
    });
  }

  function clearCreateMessages() {
    if (createModalErrorElement) {
      createModalErrorElement.classList.add('d-none');
      createModalErrorElement.textContent = '';
    }

    if (createModalSuccessElement) {
      createModalSuccessElement.classList.add('d-none');
      createModalSuccessElement.textContent = '';
    }
  }

  function setCreateMessage(type, message) {
    clearCreateMessages();

    if (type === 'success' && createModalSuccessElement) {
      createModalSuccessElement.textContent = message;
      createModalSuccessElement.classList.remove('d-none');
      return;
    }

    if (type === 'error' && createModalErrorElement) {
      createModalErrorElement.textContent = message;
      createModalErrorElement.classList.remove('d-none');
    }
  }

  function setCreateModalMode(mode, materialRow) {
    var isCopyMode = mode === 'copy' && materialRow;

    if (createModalTitleElement) {
      createModalTitleElement.textContent = isCopyMode ? 'Kopiraj materijal' : defaultCreateModalTitle;
    }

    if (createModalSubtitleElement) {
      createModalSubtitleElement.textContent = isCopyMode
        ? 'Prefill podaci su učitani. Promijenite šifru prije sačuvanja kopije.'
        : defaultCreateModalSubtitle;
    }

    if (createModalHelpElement) {
      createModalHelpElement.textContent = isCopyMode
        ? 'Kopija koristi podatke odabranog materijala i po potrebi novu početnu zalihu.'
        : defaultCreateModalHelp;
    }

    if (createModalSaveButton) {
      createModalSaveButton.innerHTML = isCopyMode
        ? '<i class="fa fa-copy me-50"></i> Sačuvaj kopiju'
        : defaultCreateModalSaveHtml;
    }
  }

  function resetCreateMaterialForm(materialRow) {
    var rowData = materialRow || null;

    clearCreateMessages();
    activeCopyMaterial = rowData;
    setCreateModalMode(rowData ? 'copy' : 'create', rowData);

    if (createModalCodeInput) {
      createModalCodeInput.value = rowData ? (rowData.material_code || '') : '';
    }

    if (createModalNameInput) {
      createModalNameInput.value = rowData ? (rowData.material_name || '') : '';
    }

    if (createModalUnitInput) {
      ensureSelectOption(createModalUnitInput, rowData ? (rowData.material_um || '') : '', 'MJ');
    }

    if (createModalWarehouseInput) {
      ensureSelectOption(createModalWarehouseInput, rowData ? (rowData.material_warehouse || '') : '', 'Skladište');
    }

    if (createModalStockInput) {
      createModalStockInput.value = rowData ? formatInputQuantity(rowData.material_qty) : '0';
    }

    if (createModalSetInput) {
      createModalSetInput.value = rowData && rowData.material_set
        ? rowData.material_set
        : (config.defaultMaterialSet || '011');
    }

    if (createModalSaveButton) {
      createModalSaveButton.disabled = false;
    }
  }

  function openCreateMaterialModal(materialRow) {
    var modalInstance;

    if (!canCreateMaterial || !createModalElement || !window.bootstrap || !window.bootstrap.Modal) {
      return;
    }

    resetCreateMaterialForm(materialRow || null);
    modalInstance = window.bootstrap.Modal.getOrCreateInstance(createModalElement);
    modalInstance.show();

    if (materialRow && createModalCodeInput && typeof createModalCodeInput.focus === 'function') {
      window.setTimeout(function () {
        createModalCodeInput.focus();

        if (typeof createModalCodeInput.select === 'function') {
          createModalCodeInput.select();
        }
      }, 120);
    }
  }

  function submitCreateMaterial() {
    var requestHeaders = {
      Accept: 'application/json'
    };
    var payload;
    var startingStock;
    var modalInstance;

    if (!canCreateMaterial || !createUrl || !createModalElement || createRequestInFlight) {
      return;
    }

    payload = {
      material_code: createModalCodeInput ? $.trim(createModalCodeInput.value || '') : '',
      material_name: createModalNameInput ? $.trim(createModalNameInput.value || '') : '',
      material_um: createModalUnitInput ? $.trim(createModalUnitInput.value || '').toUpperCase() : '',
      material_warehouse: createModalWarehouseInput ? $.trim(createModalWarehouseInput.value || '') : '',
      material_set: createModalSetInput ? $.trim(createModalSetInput.value || '') : (config.defaultMaterialSet || '011'),
      material_qty: createModalStockInput ? $.trim(createModalStockInput.value || '0') : '0'
    };

    if (!payload.material_code || !payload.material_name || !payload.material_um || !payload.material_warehouse) {
      var errorText = 'Šifra, naziv, MJ i skladište su obavezni.';
      if (window.Swal && typeof window.Swal.fire === 'function') {
        window.Swal.fire({
          icon: 'error',
          title: 'Neispravan unos',
          text: errorText,
        });
      }
      return;
    }

    startingStock = Number(payload.material_qty);
    if (!Number.isFinite(startingStock)) {
      setCreateMessage('error', 'Početna zaliha mora biti broj.');
      return;
    }

    payload.material_qty = startingStock;

    if (csrfToken) {
      requestHeaders['X-CSRF-TOKEN'] = csrfToken;
    }

    createRequestInFlight = true;
    clearCreateMessages();

    if (createModalSaveButton) {
      createModalSaveButton.disabled = true;
    }

    function translateBackendMessage(message) {
      var normalized = (message || '').toString().toLowerCase();

      if (normalized.indexOf('sifra') !== -1 && normalized.indexOf('postoji') !== -1) {
        return 'Šifra već postoji.';
      }

      if (normalized.indexOf('already exists') !== -1) {
        return 'Uneseni materijal već postoji.';
      }

      if (normalized.indexOf('required') !== -1) {
        return 'Obavezno polje nije popunjeno.';
      }

      return message || 'Greška pri dodavanju materijala.';
    }

    $.ajax({
      url: createUrl,
      method: 'POST',
      dataType: 'json',
      contentType: 'application/json; charset=UTF-8',
      headers: requestHeaders,
      data: JSON.stringify(payload),
      success: function (response) {
        var successMessage = response && response.message ? response.message : 'Materijal je uspješno dodan.';
        setCreateMessage('success', successMessage);
        if (window.Swal && typeof window.Swal.fire === 'function') {
          window.Swal.fire({
            icon: 'success',
            title: 'Uspjeh',
            text: successMessage,
            timer: 1200,
            showConfirmButton: false,
          });
        }

        if (dataTable) {
          dataTable.ajax.reload(null, false);
        }

        modalInstance = window.bootstrap.Modal.getOrCreateInstance(createModalElement);
        window.setTimeout(function () {
          modalInstance.hide();
        }, 600);
      },
      error: function (xhr) {
        var message = translateBackendMessage(extractAjaxErrorMessage(xhr, 'Ne mogu dodati novi materijal.'));
        setCreateMessage('error', message);

        if (window.Swal && typeof window.Swal.fire === 'function') {
          window.Swal.fire({
            icon: 'error',
            title: 'Greška',
            text: message,
          });
        }
      },
      complete: function () {
        createRequestInFlight = false;

        if (createModalSaveButton) {
          createModalSaveButton.disabled = false;
        }
      }
    });
  }

  function buildMaterialDeleteHtml(materialRow) {
    var materialCode = escapeHtml(materialRow && materialRow.material_code ? materialRow.material_code : '-');
    var materialName = escapeHtml(materialRow && materialRow.material_name ? materialRow.material_name : '-');

    return (
      '<div class="small lh-lg text-start d-inline-block">' +
        '<div><span class="fw-bolder">Šifra:</span> ' + materialCode + '</div>' +
        '<div><span class="fw-bolder">Naziv:</span> ' + materialName + '</div>' +
      '</div>'
    );
  }

  function deleteMaterial(materialRow) {
    var requestHeaders = {
      Accept: 'application/json'
    };
    var materialCode;

    if (!canDeleteMaterial || !deleteUrl || !materialRow || deleteRequestInFlight) {
      return;
    }

    materialCode = (materialRow.material_code || '').toString().trim();
    if (!materialCode) {
      return;
    }

    if (csrfToken) {
      requestHeaders['X-CSRF-TOKEN'] = csrfToken;
    }

    deleteRequestInFlight = true;

    $.ajax({
      url: deleteUrl,
      method: 'DELETE',
      dataType: 'json',
      contentType: 'application/json; charset=UTF-8',
      headers: requestHeaders,
      data: JSON.stringify({
        material_code: materialCode
      }),
      success: function (response) {
        if (window.Swal && typeof window.Swal.fire === 'function') {
          window.Swal.fire({
            icon: 'success',
            title: 'Materijal obrisan',
            text: response && response.message ? response.message : 'Materijal je uspješno obrisan.',
            confirmButtonText: 'U redu',
            customClass: {
              confirmButton: 'btn btn-success'
            },
            buttonsStyling: false
          });
        }

        if (dataTable) {
          dataTable.ajax.reload(null, false);
        }
      },
      error: function (xhr) {
        var errorMessage = extractAjaxErrorMessage(xhr, 'Ne mogu obrisati materijal.');

        if (window.Swal && typeof window.Swal.fire === 'function') {
          window.Swal.fire({
            icon: 'error',
            title: 'Brisanje nije uspjelo',
            text: errorMessage,
            confirmButtonText: 'Zatvori',
            customClass: {
              confirmButton: 'btn btn-danger'
            },
            buttonsStyling: false
          });
          return;
        }

        window.alert(errorMessage);
      },
      complete: function () {
        deleteRequestInFlight = false;
      }
    });
  }

  function confirmDeleteMaterial(materialRow) {
    var fallbackMessage;

    if (!canDeleteMaterial || !materialRow) {
      return;
    }

    fallbackMessage = 'Obrisati materijal ' + ((materialRow.material_code || '').toString().trim() || '-') + '?';

    if (!window.Swal || typeof window.Swal.fire !== 'function') {
      if (window.confirm(fallbackMessage)) {
        deleteMaterial(materialRow);
      }
      return;
    }

    window.Swal.fire({
      icon: 'warning',
      title: 'Obrisati materijal?',
      html: buildMaterialDeleteHtml(materialRow),
      showCancelButton: true,
      confirmButtonText: 'Izbriši',
      cancelButtonText: 'Otkaži',
      customClass: {
        confirmButton: 'btn btn-danger me-1',
        cancelButton: 'btn btn-outline-danger'
      },
      buttonsStyling: false
    }).then(function (result) {
      if (result.isConfirmed) {
        deleteMaterial(materialRow);
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
        lastTableRecordsFiltered = Number(response && response.recordsFiltered);

        if (!Number.isFinite(lastTableRecordsFiltered) || lastTableRecordsFiltered < 0) {
          lastTableRecordsFiltered = Array.isArray(response && response.data) ? response.data.length : 0;
        }

        updateBulkDownloadButtonState();

        callback({
          draw: requestData.draw,
          recordsTotal: response.recordsTotal || 0,
          recordsFiltered: response.recordsFiltered || 0,
          data: response.data || []
        });
      },
      error: function () {
        lastTableRecordsFiltered = 0;
        updateBulkDownloadButtonState();

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
    initComplete: function () {
      ensureBulkDownloadButton();
      updateBulkDownloadButtonState();
    },
    drawCallback: function () {
      ensureBulkDownloadButton();
      updateBulkDownloadButtonState();
    },
    ajax: function (requestData, callback) {
      var requestPayload = buildTableRequestPayload(requestData);

      runTableRequest(requestData, callback, requestPayload, false);
    },
    columns: (function () {
      var columns = [
        { data: 'material_code' },
        { data: 'material_name' },
        { data: 'material_um' }
      ];

      if (canSeeWarehouse) {
        columns.push({ data: 'material_warehouse' });
      }

      columns.push({ data: 'material_qty' });

      if (canManageMaterialActions) {
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
      var stockColumnIndex = canSeeWarehouse ? 4 : 3;

      if (canSeeWarehouse) {
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

      if (canManageMaterialActions) {
        columnDefs.push({
          targets: stockColumnIndex + 1,
          className: 'text-end material-actions-cell',
          render: function (data, type, row) {
            var actionsHtml = '<div class="material-actions-group">';

            actionsHtml +=
              '<button type="button" class="btn btn-sm btn-outline-primary material-action-btn material-barcode-preview-btn">' +
                '<i class="fa fa-barcode"></i>' +
                '<span>Barcode</span>' +
              '</button>';

            if (canAdjustStock) {
              actionsHtml +=
                '<button type="button" class="btn btn-sm btn-outline-primary material-action-btn material-stock-adjust-btn">' +
                  '<i class="fa fa-database"></i>' +
                  '<span>Skladište</span>' +
                '</button>';
            }

            if (canCopyMaterial) {
              actionsHtml +=
                '<button type="button" class="btn btn-sm btn-outline-secondary material-action-btn material-copy-btn">' +
                  '<i class="fa fa-copy"></i>' +
                  '<span>Kopiraj</span>' +
                '</button>';
            }

            if (canDeleteMaterial) {
              actionsHtml +=
                '<button type="button" class="btn btn-sm btn-outline-danger material-action-btn material-delete-btn" data-material-code="' + escapeHtml(row && row.material_code ? row.material_code : '') + '">' +
                  '<i class="fa fa-trash"></i>' +
                  '<span>Izbriši</span>' +
                '</button>';
            }

            actionsHtml += '</div>';

            return actionsHtml;
          }
        });
      }

      return columnDefs;
    })(),
    language: {
      search: 'Pretraga:',
      lengthMenu: 'Prikaži _MENU_ materijala',
      info: 'Prikaz _START_ do _END_ od _TOTAL_ materijala',
      infoEmpty: 'Nema materijala za prikaz',
      infoFiltered: '(filtrirano od _MAX_ ukupno)',
      emptyTable: 'Nema materijala za prikaz.',
      zeroRecords: 'Nema rezultata za zadanu pretragu.',
      paginate: {
        first: 'Prva',
        last: 'Zadnja',
        next: 'Sljedeća',
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

  tableElement.find('tbody').on('click', '.material-barcode-preview-btn', function (event) {
    var rowData;
    var modalInstance;

    event.preventDefault();
    event.stopPropagation();
    rowData = resolveRowDataFromTrigger(this);

    if (!rowData || !modalElement || !window.bootstrap || !window.bootstrap.Modal) {
      return;
    }

    renderMaterialBarcode(rowData);
    modalInstance = window.bootstrap.Modal.getOrCreateInstance(modalElement);
    modalInstance.show();
  });

  tableElement.find('tbody').on('click', '.material-copy-btn', function (event) {
    var rowData;

    if (!canCopyMaterial) {
      return;
    }

    event.preventDefault();
    event.stopPropagation();
    rowData = resolveRowDataFromTrigger(this);

    if (!rowData) {
      return;
    }

    openCreateMaterialModal(rowData);
  });

  tableElement.find('tbody').on('click', '.material-delete-btn', function (event) {
    var rowData;

    if (!canDeleteMaterial) {
      return;
    }

    event.preventDefault();
    event.stopPropagation();
    rowData = resolveRowDataFromTrigger(this);

    if (!rowData) {
      return;
    }

    confirmDeleteMaterial(rowData);
  });

  tableElement.find('tbody').on('click', 'tr', function (event) {
    var rowElement = $(this);
    var rowData;
    var modalInstance;

    if ($(event.target).closest('.material-stock-adjust-btn, .material-barcode-preview-btn, .material-copy-btn, .material-delete-btn').length) {
      return;
    }

    rowData = resolveRowData(rowElement);

    if (!rowData) {
      return;
    }

    if (canAdjustStock && stockModalElement && window.bootstrap && window.bootstrap.Modal) {
      openStockModal(rowData);
      return;
    }

    if (!modalElement || !window.bootstrap || !window.bootstrap.Modal) {
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

  if (createModalOpenButton) {
    createModalOpenButton.addEventListener('click', function () {
      openCreateMaterialModal(null);
    });
  }

  if (warehouseFilterInput) {
    warehouseFilterInput.addEventListener('change', function () {
      lastTableRecordsFiltered = 0;
      updateBulkDownloadButtonState();

      if (dataTable) {
        dataTable.ajax.reload(null, true);
      }
    });
  }

  if (createModalSaveButton) {
    createModalSaveButton.addEventListener('click', function () {
      submitCreateMaterial();
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

  if (createModalElement) {
    createModalElement.addEventListener('hidden.bs.modal', function () {
      createRequestInFlight = false;
      resetCreateMaterialForm();
    });
  }

  if (autoOpenCreateMaterial) {
    openCreateMaterialModal(null);

    if (window.history && typeof window.history.replaceState === 'function') {
      window.history.replaceState({}, document.title, window.location.pathname);
    }
  }
});
