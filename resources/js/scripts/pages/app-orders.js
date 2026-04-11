$(function () {
  'use strict';

  var tableElement = $('#order-linkage-table');
  var pageErrorElement = $('#order-linkage-page-error');
  var config = window.orderLinkageConfig || {};
  var dataUrl = (config.dataUrl || '').toString().trim();
  var positionsUrl = (config.positionsUrl || '').toString().trim();
  var workOrdersUrl = (config.workOrdersUrl || '').toString().trim();
  var workOrdersApiUrl = (config.workOrdersApiUrl || '').toString().trim();
  var modalElement = document.getElementById('order-linkage-modal');
  var modalTitleElement = document.getElementById('order-linkage-modal-label');
  var modalSubtitleElement = document.getElementById('order-linkage-modal-subtitle');
  var modalErrorElement = document.getElementById('order-linkage-modal-error');
  var modalLoadingElement = document.getElementById('order-linkage-modal-loading');
  var modalContentElement = document.getElementById('order-linkage-modal-content');
  var transferModalElement = document.getElementById('order-linkage-transfer-modal');
  var transferModalSubtitleElement = document.getElementById('order-linkage-transfer-modal-subtitle');
  var transferModalBodyElement = document.getElementById('order-linkage-transfer-modal-body');
  var filtersBody = $('#filters-body');
  var toggleFiltersBtn = $('#btn-toggle-filters');
  var deleteFiltersBtn = $('#btn-delete-filter');
  var activeFiltersContainer = $('#active-filters-container');
  var activeFiltersDivider = $('#active-filters-divider');
  var dataTable = null;
  var modalRequest = null;

  var filterLabels = {
    kupac: 'Kupac',
    primatelj: 'Primatelj',
    proizvod: 'Proizvod',
    plan_pocetak_od: 'Plan. pocetak od',
    plan_pocetak_do: 'Plan. pocetak do',
    plan_kraj_od: 'Plan. kraj od',
    plan_kraj_do: 'Plan. kraj do',
    datum_od: 'Datum od',
    datum_do: 'Datum do',
    vezni_dok: 'Vezni dok.',
    prioritet: 'Prioritet'
  };

  var filterInputIds = {
    kupac: 'filter-kupac',
    primatelj: 'filter-primatelj',
    proizvod: 'filter-proizvod',
    plan_pocetak_od: 'filter-plan-pocetak-od',
    plan_pocetak_do: 'filter-plan-pocetak-do',
    plan_kraj_od: 'filter-plan-kraj-od',
    plan_kraj_do: 'filter-plan-kraj-do',
    datum_od: 'filter-datum-od',
    datum_do: 'filter-datum-do',
    vezni_dok: 'filter-vezni-dok',
    prioritet: 'filter-prioritet'
  };

  var dateFilterKeys = [
    'plan_pocetak_od',
    'plan_pocetak_do',
    'plan_kraj_od',
    'plan_kraj_do',
    'datum_od',
    'datum_do'
  ];

  var sortableColumnMap = {
    0: 'narudzba',
    1: 'narucitelj',
    2: 'prijevoznik',
    3: 'datum',
    4: 'kolicina',
    5: 'broj_pozicija',
    6: 'broj_rn'
  };

  var bosnianDatePickerLocale = {
    firstDayOfWeek: 1,
    weekdays: {
      shorthand: ['Ned', 'Pon', 'Uto', 'Sri', 'Cet', 'Pet', 'Sub'],
      longhand: ['Nedjelja', 'Ponedjeljak', 'Utorak', 'Srijeda', 'Cetvrtak', 'Petak', 'Subota']
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

  function replaceFeather() {
    if (window.feather && typeof window.feather.replace === 'function') {
      window.feather.replace({ width: 14, height: 14 });
    }
  }

  function showPageError(message) {
    if (!pageErrorElement.length) {
      return;
    }

    pageErrorElement.text(message || 'Greska pri ucitavanju narudzbi.');
    pageErrorElement.removeClass('d-none');
  }

  function hidePageError() {
    if (!pageErrorElement.length) {
      return;
    }

    pageErrorElement.addClass('d-none').text('');
  }

  function formatDateForApi(value) {
    var normalizedValue = (value || '').toString().trim();
    var dotDateMatch;

    if (!normalizedValue) {
      return '';
    }

    if (/^\d{4}-\d{2}-\d{2}$/.test(normalizedValue)) {
      return normalizedValue;
    }

    dotDateMatch = normalizedValue.match(/^(\d{2})\.(\d{2})\.(\d{4})$/);
    if (dotDateMatch) {
      return dotDateMatch[3] + '-' + dotDateMatch[2] + '-' + dotDateMatch[1];
    }

    return normalizedValue;
  }

  function formatDateForDisplay(value) {
    var normalizedValue = (value || '').toString().trim();
    var isoDateMatch;

    if (!normalizedValue) {
      return '';
    }

    isoDateMatch = normalizedValue.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (isoDateMatch) {
      return isoDateMatch[3] + '.' + isoDateMatch[2] + '.' + isoDateMatch[1];
    }

    return normalizedValue;
  }

  function formatDate(value) {
    if (!value) {
      return '<span class="text-muted">-</span>';
    }

    return escapeHtml(formatDateForDisplay(value));
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

  function formatQuantityWithUnit(value, unit) {
    var formattedQuantity = formatQuantity(value);
    var normalizedUnit = (unit || '').toString().trim();

    if (formattedQuantity.indexOf('text-muted') !== -1) {
      return formattedQuantity;
    }

    if (!normalizedUnit) {
      return formattedQuantity;
    }

    return formattedQuantity + ' ' + escapeHtml(normalizedUnit);
  }

  function badgeToneClass(tone) {
    switch ((tone || '').toString().trim()) {
      case 'danger':
        return 'badge-light-danger';
      case 'warning':
        return 'badge-light-warning';
      case 'success':
        return 'badge-light-success';
      case 'info':
        return 'badge-light-info';
      case 'primary':
        return 'badge-light-primary';
      default:
        return 'badge-light-secondary';
    }
  }

  function statusToneFromBucket(bucket) {
    switch ((bucket || '').toString().trim()) {
      case 'otvoren':
        return 'success';
      case 'planiran':
        return 'primary';
      case 'rezerviran':
      case 'u_radu':
      case 'djelimicno_zakljucen':
        return 'warning';
      case 'zakljucen':
        return 'danger';
      default:
        return 'secondary';
    }
  }

  function getPriorityFilterDisplayValue(value) {
    switch ((value || '').toString().trim()) {
      case '1':
        return '1 - Visoki prioritet';
      case '5':
        return '5 - Uobicajeni prioritet';
      case '10':
        return '10 - Niski prioritet';
      case '15':
        return '15 - Uzorci';
      default:
        return value || '';
    }
  }

  function isDateFilterKey(filterKey) {
    return dateFilterKeys.indexOf(filterKey) !== -1;
  }

  function getFilters() {
    return {
      kupac: $('#filter-kupac').val() || '',
      primatelj: $('#filter-primatelj').val() || '',
      proizvod: $('#filter-proizvod').val() || '',
      plan_pocetak_od: formatDateForApi($('#filter-plan-pocetak-od').val() || ''),
      plan_pocetak_do: formatDateForApi($('#filter-plan-pocetak-do').val() || ''),
      plan_kraj_od: formatDateForApi($('#filter-plan-kraj-od').val() || ''),
      plan_kraj_do: formatDateForApi($('#filter-plan-kraj-do').val() || ''),
      datum_od: formatDateForApi($('#filter-datum-od').val() || ''),
      datum_do: formatDateForApi($('#filter-datum-do').val() || ''),
      vezni_dok: $('#filter-vezni-dok').val() || '',
      prioritet: $('#filter-prioritet').val() || ''
    };
  }

  function clearFilterInputByKey(filterKey) {
    var inputId = filterInputIds[filterKey];
    var inputElement;
    var flatpickrInstance;

    if (!inputId) {
      return;
    }

    inputElement = document.getElementById(inputId);
    if (!inputElement) {
      return;
    }

    flatpickrInstance = inputElement._flatpickr;
    if (flatpickrInstance) {
      flatpickrInstance.clear();
      return;
    }

    if (inputElement.tagName === 'SELECT') {
      inputElement.value = '';
    } else {
      inputElement.value = '';
    }
  }

  function initializeDateFilterPickers() {
    if (!window.flatpickr || typeof window.flatpickr !== 'function') {
      return;
    }

    $('.order-linkage-filter-date').each(function () {
      window.flatpickr(this, {
        dateFormat: 'd.m.Y',
        allowInput: true,
        locale: bosnianDatePickerLocale
      });
    });
  }

  function renderActiveFilters() {
    var filters = getFilters();
    var activeFilters = [];

    Object.keys(filters).forEach(function (key) {
      var rawValue = filters[key];
      var value = rawValue === null || rawValue === undefined ? '' : String(rawValue).trim();

      if (!value) {
        return;
      }

      activeFilters.push({
        key: key,
        label: filterLabels[key] || key,
        value: key === 'prioritet' ? getPriorityFilterDisplayValue(value) : (isDateFilterKey(key) ? formatDateForDisplay(value) : value)
      });
    });

    if (!activeFilters.length) {
      activeFiltersContainer.empty().addClass('d-none');
      activeFiltersDivider.addClass('d-none');
      return;
    }

    activeFiltersContainer
      .html(
        activeFilters
          .map(function (filter) {
            var safeKey = escapeHtml(filter.key);
            return (
              '<span class="order-linkage-active-filter-chip" data-filter-key="' +
              safeKey +
              '">' +
              '<span class="order-linkage-active-filter-label">' +
              escapeHtml(filter.label) +
              ':</span>' +
              '<span class="order-linkage-active-filter-value">' +
              escapeHtml(filter.value) +
              '</span>' +
              '<button type="button" class="order-linkage-active-filter-remove" data-filter-key="' +
              safeKey +
              '" aria-label="Ukloni filter">&times;</button>' +
              '</span>'
            );
          })
          .join('')
      )
      .removeClass('d-none');

    activeFiltersDivider.removeClass('d-none');
  }

  function setFiltersBodyVisibility(isVisible) {
    filtersBody.toggleClass('d-none', !isVisible);
    toggleFiltersBtn.html('<i data-feather="filter" class="me-50"></i> ' + (isVisible ? 'Sakrij filtere' : 'Pokazi filtere'));
    toggleFiltersBtn.attr('aria-expanded', isVisible ? 'true' : 'false');
    replaceFeather();
  }

  function buildActionsHtml() {
    return (
      '<div class="order-linkage-actions-group">' +
        '<button type="button" class="btn btn-sm btn-outline-primary order-linkage-action-btn order-linkage-positions-btn">' +
          '<i class="fa fa-list"></i>' +
          '<span>Pozicije</span>' +
        '</button>' +
        '<button type="button" class="btn btn-sm btn-outline-primary order-linkage-action-btn order-linkage-work-orders-btn">' +
          '<i class="fa fa-industry"></i>' +
          '<span>Veze</span>' +
        '</button>' +
      '</div>'
    );
  }

  function resolveRowDataFromTrigger(trigger) {
    var rowElement = $(trigger).closest('tr');

    if (!dataTable || !rowElement.length) {
      return null;
    }

    return dataTable.row(rowElement).data() || null;
  }

  function ensureTableBodyScrollContainer() {
    var existingScrollHost = tableElement.closest('.order-linkage-table-body-scroll');
    var scrollHost;

    if (existingScrollHost.length) {
      return existingScrollHost;
    }

    scrollHost = $('<div class="order-linkage-table-body-scroll"></div>');
    tableElement.before(scrollHost);
    scrollHost.append(tableElement);

    if (scrollHost.get(0)) {
      scrollHost.get(0).scrollLeft = 0;
    }

    return scrollHost;
  }

  function setModalState(options) {
    var loading = options && options.loading;
    var errorMessage = options && options.errorMessage ? String(options.errorMessage) : '';
    var html = options && options.html ? String(options.html) : '';

    if (modalLoadingElement) {
      modalLoadingElement.classList.toggle('d-none', !loading);
    }

    if (modalErrorElement) {
      modalErrorElement.classList.toggle('d-none', errorMessage === '');
      modalErrorElement.textContent = errorMessage;
    }

    if (modalContentElement) {
      modalContentElement.classList.toggle('d-none', loading || html === '');
      modalContentElement.innerHTML = html;
    }

    if (!loading && html) {
      replaceFeather();
    }
  }

  function openModal(title, subtitle) {
    var modalInstance;

    if (!modalElement || !window.bootstrap || !window.bootstrap.Modal) {
      return null;
    }

    if (modalTitleElement) {
      modalTitleElement.textContent = title || 'Detalji narudzbe';
    }

    if (modalSubtitleElement) {
      modalSubtitleElement.textContent = subtitle || '-';
    }

    setModalState({ loading: true, errorMessage: '', html: '' });
    modalInstance = window.bootstrap.Modal.getOrCreateInstance(modalElement);
    modalInstance.show();

    return modalInstance;
  }

  function buildWorkOrdersModalHtml(row, workOrders) {
    var records = Array.isArray(workOrders) ? workOrders : [];

    return (
      '<div class="order-linkage-modal-summary-grid">' +
        '<div class="order-linkage-modal-summary-card">' +
          '<span class="order-linkage-modal-summary-label">Narudzba</span>' +
          '<span class="order-linkage-modal-summary-value">' + escapeHtml((row && row.narudzba) || '-') + '</span>' +
        '</div>' +
        '<div class="order-linkage-modal-summary-card">' +
          '<span class="order-linkage-modal-summary-label">Narucitelj</span>' +
          '<span class="order-linkage-modal-summary-value">' + escapeHtml((row && (row.narucitelj || row.klijent)) || '-') + '</span>' +
        '</div>' +
        '<div class="order-linkage-modal-summary-card">' +
          '<span class="order-linkage-modal-summary-label">Broj RN</span>' +
          '<span class="order-linkage-modal-summary-value">' + escapeHtml((row && row.brojRN) || 0) + '</span>' +
        '</div>' +
        '<div class="order-linkage-modal-summary-card">' +
          '<span class="order-linkage-modal-summary-label">Kolicina</span>' +
          '<span class="order-linkage-modal-summary-value">' + formatQuantityWithUnit(row && row.totalKolicina, row && row.jedinica) + '</span>' +
        '</div>' +
      '</div>' +
      '<div class="order-linkage-modal-table-wrap">' +
        '<div class="table-responsive">' +
          '<table class="table order-linkage-modal-table">' +
            '<thead>' +
              '<tr>' +
                '<th>#</th>' +
                '<th>Status</th>' +
                '<th>Veza</th>' +
                '<th>Pozicije</th>' +
              '</tr>' +
            '</thead>' +
            '<tbody>' +
              (records.length
                ? records
                    .map(function (workOrder) {
                      var vezaToneClass = badgeToneClass((workOrder && workOrder.veza_tone) || 'secondary');
                      var statusToneClass = badgeToneClass((workOrder && workOrder.status_tone) || 'secondary');

                      return (
                        '<tr>' +
                          '<td>' + escapeHtml((workOrder && workOrder.id) || '-') + '</td>' +
                          '<td><span class="badge ' + statusToneClass + '">' + escapeHtml((workOrder && workOrder.status) || 'N/A') + '</span></td>' +
                          '<td><span class="badge ' + vezaToneClass + '">' + escapeHtml((workOrder && workOrder.veza) || 'Sumnjiva veza') + '</span></td>' +
                          '<td>' + escapeHtml((workOrder && workOrder.pozicije) || '-') + '</td>' +
                        '</tr>'
                      );
                    })
                    .join('')
                : '<tr><td colspan="4" class="order-linkage-modal-empty">Za ovu narudzbu nisu pronadjeni radni nalozi.</td></tr>') +
            '</tbody>' +
          '</table>' +
        '</div>' +
      '</div>'
    );
  }

  function buildTransferModalDetail(label, value) {
    var displayValue = (value || '').toString().trim() || '-';

    return (
      '<div class="order-linkage-transfer-detail">' +
        '<span class="order-linkage-transfer-detail-label">' + escapeHtml(label) + '</span>' +
        '<span class="order-linkage-transfer-detail-value">' + escapeHtml(displayValue) + '</span>' +
      '</div>'
    );
  }

  function buildTransferModalHtml(details) {
    return (
      '<div class="order-linkage-transfer-detail-grid">' +
        buildTransferModalDetail('Pozicija', details.position) +
        buildTransferModalDetail('Status', details.status) +
        buildTransferModalDetail('Dokument', details.document) +
        buildTransferModalDetail('Order item QID', details.orderItemQid) +
      '</div>'
    );
  }

  function openTransferModal(buttonElement) {
    var button = $(buttonElement);
    var details = {
      position: (button.data('position') || '').toString(),
      orderItemQid: (button.data('order-item-qid') || '').toString(),
      document: (button.data('transfer-document') || '').toString(),
      status: (button.data('transfer-status') || '').toString()
    };
    var showTransferModal;
    var activeModal;

    if (!transferModalElement || !window.bootstrap || !window.bootstrap.Modal) {
      return;
    }

    if (transferModalSubtitleElement) {
      transferModalSubtitleElement.textContent = 'Pozicija: ' + (details.position || '-');
    }

    if (transferModalBodyElement) {
      transferModalBodyElement.innerHTML = buildTransferModalHtml(details);
    }

    showTransferModal = function () {
      window.bootstrap.Modal.getOrCreateInstance(transferModalElement).show();
      replaceFeather();
    };

    if (modalElement && modalElement.classList.contains('show')) {
      activeModal = window.bootstrap.Modal.getInstance(modalElement) || window.bootstrap.Modal.getOrCreateInstance(modalElement);
      $(modalElement).one('hidden.bs.modal', showTransferModal);
      activeModal.hide();
      return;
    }

    showTransferModal();
  }

  function loadPositionsModalContent(row) {
    var modalInstance;

    if (!row || !positionsUrl) {
      return;
    }

    modalInstance = openModal('Pozicije narudzbe', 'Narudzba: ' + ((row && row.narudzba) || '-'));

    if (!modalInstance) {
      return;
    }

    if (modalRequest && typeof modalRequest.abort === 'function') {
      modalRequest.abort();
    }

    modalRequest = $.ajax({
      url: positionsUrl,
      method: 'GET',
      dataType: 'html',
      data: {
        order_number: row.narudzba || row.order_number || ''
      },
      success: function (html) {
        setModalState({
          loading: false,
          errorMessage: '',
          html: html || '<div class="order-linkage-modal-empty">Nema podataka za prikaz.</div>'
        });
      },
      error: function (xhr) {
        setModalState({
          loading: false,
          errorMessage: xhr && xhr.responseText ? $(xhr.responseText).text() || 'Greska pri ucitavanju detalja.' : 'Greska pri ucitavanju detalja.',
          html: ''
        });
      }
    });
  }

  function loadWorkOrdersModalContent(row) {
    var modalInstance;

    if (!row || !workOrdersUrl) {
      return;
    }

    modalInstance = openModal('Veze narudzbe', 'Narudzba: ' + ((row && row.narudzba) || '-'));

    if (!modalInstance) {
      return;
    }

    if (modalRequest && typeof modalRequest.abort === 'function') {
      modalRequest.abort();
    }

    modalRequest = $.ajax({
      url: workOrdersUrl,
      method: 'GET',
      dataType: 'html',
      data: {
        order_number: row.narudzba || row.order_number || ''
      },
      success: function (html) {
        setModalState({
          loading: false,
          errorMessage: '',
          html: html || '<div class="order-linkage-modal-empty">Nema podataka za prikaz.</div>'
        });
      },
      error: function (xhr) {
        setModalState({
          loading: false,
          errorMessage: xhr && xhr.responseText ? $(xhr.responseText).text() || 'Greska pri ucitavanju detalja.' : 'Greska pri ucitavanju detalja.',
          html: ''
        });
      }
    });
  }

  function initialiseTable() {
    dataTable = tableElement.DataTable({
      processing: true,
      serverSide: true,
      autoWidth: false,
      responsive: false,
      pageLength: 10,
      lengthMenu: [10, 25, 50],
      searchDelay: 350,
      order: [[3, 'desc']],
      ajax: function (requestData, callback) {
        var page = Math.floor(requestData.start / requestData.length) + 1;
        var firstOrder = requestData.order && requestData.order.length ? requestData.order[0] : null;
        var orderColumnIndex = firstOrder && firstOrder.column !== undefined ? parseInt(firstOrder.column, 10) : NaN;
        var sortBy = Number.isInteger(orderColumnIndex) ? sortableColumnMap[orderColumnIndex] || '' : '';
        var params = getFilters();

        params.page = page;
        params.limit = requestData.length || 10;
        params.draw = requestData.draw;
        params.search = requestData.search && requestData.search.value ? requestData.search.value : '';
        params.sort_by = sortBy;
        params.sort_dir = firstOrder && firstOrder.dir === 'asc' ? 'asc' : 'desc';

        if (!sortBy) {
          params.sort_dir = '';
        }

        hidePageError();

        $.ajax({
          url: dataUrl,
          method: 'GET',
          dataType: 'json',
          data: params,
          success: function (response) {
            hidePageError();
            callback({
              draw: requestData.draw,
              recordsTotal: response.meta && response.meta.total ? response.meta.total : 0,
              recordsFiltered: response.meta && response.meta.filtered_total ? response.meta.filtered_total : 0,
              data: response.data || []
            });
          },
          error: function (xhr) {
            var responseJson = xhr && xhr.responseJSON ? xhr.responseJSON : null;
            var message = responseJson && responseJson.message ? responseJson.message : 'Greska pri ucitavanju narudzbi.';

            showPageError(message);
            callback({
              draw: requestData.draw,
              recordsTotal: 0,
              recordsFiltered: 0,
              data: []
            });
          }
        });
      },
      columns: [
        { data: 'narudzba' },
        { data: 'narucitelj' },
        { data: 'prijevoznik' },
        { data: 'datum' },
        { data: 'totalKolicina' },
        { data: 'brojPozicija' },
        { data: 'brojRN' },
        { data: null, orderable: false, searchable: false }
      ],
      columnDefs: [
        {
          targets: 0,
          className: 'order-linkage-order-cell',
          render: function (data, type, row) {
            var orderNumber = data ? escapeHtml(data) : '<span class="text-muted">-</span>';
            var linkageLabel = row && row.linkage_label ? escapeHtml(row.linkage_label) : 'N/A';
            var toneClass = badgeToneClass(row && row.linkage_tone ? row.linkage_tone : 'secondary');

            if (type === 'sort' || type === 'type') {
              return row && row.narudzba ? row.narudzba : '';
            }

            return (
              '<div class="order-linkage-order-number">' +
                orderNumber +
              '</div>' +
              '<div class="mt-50"><span class="badge ' + toneClass + ' order-linkage-indicator">' + linkageLabel + '</span></div>'
            );
          }
        },
        {
          targets: 1,
          render: function (data, type) {
            if (type === 'sort' || type === 'type') {
              return data || '';
            }

            return data ? escapeHtml(data) : '<span class="text-muted">-</span>';
          }
        },
        {
          targets: 2,
          render: function (data, type) {
            if (type === 'sort' || type === 'type') {
              return data || '';
            }

            return data ? escapeHtml(data) : '<span class="text-muted">-</span>';
          }
        },
        {
          targets: 3,
          render: function (data, type) {
            return type === 'display' ? formatDate(data) : (data || '');
          }
        },
        {
          targets: 4,
          className: 'text-end order-linkage-quantity-cell',
          render: function (data, type, row) {
            if (type === 'sort' || type === 'type') {
              return data === null || data === undefined ? -1 : Number(data);
            }

            return formatQuantityWithUnit(data, row && row.jedinica ? row.jedinica : '');
          }
        },
        {
          targets: 5,
          className: 'text-center order-linkage-count-cell',
          render: function (data, type) {
            if (type === 'sort' || type === 'type') {
              return Number(data || 0);
            }

            return '<span class="badge badge-light-secondary">' + escapeHtml(data || 0) + '</span>';
          }
        },
        {
          targets: 6,
          className: 'text-center order-linkage-count-cell',
          render: function (data, type) {
            if (type === 'sort' || type === 'type') {
              return Number(data || 0);
            }

            return '<span class="badge badge-light-secondary">' + escapeHtml(data || 0) + '</span>';
          }
        },
        {
          targets: 7,
          className: 'text-end order-linkage-actions-cell',
          render: function (data, type, row) {
            if (type !== 'display') {
              return '';
            }

            return buildActionsHtml(row);
          }
        }
      ],
      dom:
        '<"row align-items-center"' +
          '<"col-sm-12 col-md-6"l>' +
          '<"col-sm-12 col-md-6"f>' +
        '>' +
        't' +
        '<"row align-items-center"' +
          '<"col-sm-12 col-md-5"i>' +
          '<"col-sm-12 col-md-7"p>' +
        '>',
      language: {
        search: 'Brza pretraga:',
        searchPlaceholder: 'Pretrazi...',
        lengthMenu: 'Prikazi _MENU_ narudzbi',
        info: 'Prikaz _START_ do _END_ od _TOTAL_ narudzbi',
        infoEmpty: 'Nema narudzbi za prikaz',
        infoFiltered: '(filtrirano od _MAX_ ukupno)',
        emptyTable: 'Nema narudzbi za prikaz.',
        zeroRecords: 'Nema rezultata za zadanu pretragu.',
        paginate: {
          first: 'Prva',
          last: 'Zadnja',
          next: 'Sljedeca',
          previous: 'Prethodna'
        }
      },
      initComplete: function () {
        ensureTableBodyScrollContainer();
        tableElement.closest('.card-datatable').removeClass('order-linkage-initial-loading');
        replaceFeather();
      },
      drawCallback: function () {
        ensureTableBodyScrollContainer();
        replaceFeather();
      }
    });

    tableElement.find('tbody').on('click', '.order-linkage-positions-btn', function (event) {
      var row = resolveRowDataFromTrigger(this);

      event.preventDefault();
      event.stopPropagation();

      if (!row) {
        return;
      }

      loadPositionsModalContent(row);
    });

    tableElement.find('tbody').on('click', '.order-linkage-work-orders-btn', function (event) {
      var row = resolveRowDataFromTrigger(this);

      event.preventDefault();
      event.stopPropagation();

      if (!row) {
        return;
      }

      loadWorkOrdersModalContent(row);
    });

  }

  function applyFilters() {
    renderActiveFilters();
    hidePageError();

    if (dataTable) {
      dataTable.ajax.reload();
    }
  }

  function releaseButtonState(buttonElement) {
    var $button = $(buttonElement);

    if (!$button.length) {
      return;
    }

    window.requestAnimationFrame(function () {
      $button.removeClass('active focus');
      $button.trigger('blur');
    });
  }

  initializeDateFilterPickers();
  renderActiveFilters();
  setFiltersBodyVisibility(false);
  initialiseTable();

  if (modalContentElement) {
    $(modalContentElement).on('click', '.order-linkage-modal-transfer-btn', function (event) {
      event.preventDefault();
      event.stopPropagation();
      openTransferModal(this);
    });
  }

  toggleFiltersBtn.on('click', function () {
    setFiltersBodyVisibility(filtersBody.hasClass('d-none'));
    releaseButtonState(this);
  });

  $('#btn-filter').on('click', function () {
    applyFilters();
  });

  $('#btn-delete-filter').on('click', function () {
    Object.keys(filterInputIds).forEach(function (filterKey) {
      clearFilterInputByKey(filterKey);
    });
    applyFilters();
    releaseButtonState(deleteFiltersBtn);
  });

  activeFiltersContainer.on('click', '.order-linkage-active-filter-remove', function (event) {
    event.preventDefault();
    clearFilterInputByKey($(this).data('filter-key'));
    applyFilters();
  });

  $('.order-linkage-filter-input').on('keypress', function (event) {
    if (event.which === 13) {
      applyFilters();
    }
  });
});
