/*=========================================================================================
    File Name: app-invoice-list.js
    Description: app-invoice-list Javascripts
    ----------------------------------------------------------------------------------------
    Item Name: Vuexy  - Vuejs, HTML & Laravel Admin Dashboard Template
   Version: 1.0
    Author: PIXINVENT
    Author URL: http://www.themeforest.net/user/pixinvent
==========================================================================================*/

$(function () {
  'use strict';

  var dtInvoiceTable = $('.invoice-list-table'),
    assetPath = '../../../app-assets/',
    invoicePreview = 'app-invoice-preview.html',
    invoiceAdd = 'app-invoice-add.html',
    invoiceEdit = 'app-invoice-edit.html',
    workOrdersApi = 'api/work-orders';

  if ($('body').attr('data-framework') === 'laravel') {
    assetPath = $('body').attr('data-asset-path');
    invoicePreview = assetPath + 'app/invoice/preview';
    invoiceAdd = assetPath + 'app/invoice/add';
    invoiceEdit = assetPath + 'app/invoice/edit';
    workOrdersApi = assetPath + 'api/work-orders';
  }

  function getFilters(currentStatusFilter) {
    return {
      status: currentStatusFilter || '',
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

  var filterLabels = {
    status: 'Status',
    kupac: 'Kupac',
    primatelj: 'Primatelj',
    proizvod: 'Proizvod',
    plan_pocetak_od: 'Plan. pocetak od',
    plan_pocetak_do: 'Plan. pocetak do',
    plan_kraj_od: 'Plan. kraj od',
    plan_kraj_do: 'Plan. kraj do',
    datum_od: 'Datum od',
    datum_do: 'RN datum do',
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
  var bosnianDatePickerLocale = {
    firstDayOfWeek: 1,
    weekdays: {
      shorthand: ['Ned', 'Pon', 'Uto', 'Sri', 'Čet', 'Pet', 'Sub'],
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

  function isDateFilterKey(filterKey) {
    return dateFilterKeys.indexOf(filterKey) !== -1;
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

    inputElement.value = '';
  }

  function initializeDateFilterPickers() {
    if (typeof flatpickr === 'undefined') {
      return;
    }

    dateFilterKeys.forEach(function (filterKey) {
      var inputId = filterInputIds[filterKey];
      var inputElement = inputId ? document.getElementById(inputId) : null;
      var initialValue;
      var pickerInstance;

      if (!inputElement || inputElement._flatpickr) {
        return;
      }

      initialValue = formatDateForApi(inputElement.value);
      pickerInstance = flatpickr(inputElement, {
        dateFormat: 'Y-m-d',
        altInput: true,
        altFormat: 'd.m.Y',
        locale: bosnianDatePickerLocale,
        disableMobile: true,
        allowInput: false
      });

      inputElement.style.cursor = 'pointer';
      if (pickerInstance.altInput) {
        pickerInstance.altInput.style.cursor = 'pointer';
      }

      if (initialValue) {
        pickerInstance.setDate(initialValue, false, 'Y-m-d');
      }
    });
  }

  function getStatusFilterDisplayValue(statusKey) {
    var normalizedStatusKey = (statusKey || '').toString().trim();
    var statusLabel = $('.status-card[data-status="' + normalizedStatusKey + '"] .status-label').first().text().trim();

    if (statusLabel) {
      return statusLabel;
    }

    return normalizedStatusKey.replace(/_/g, ' ');
  }

  function getPriorityFilterDisplayValue(priorityValue) {
    var normalizedPriorityValue = (priorityValue || '').toString().trim();
    var priorityLabel = $('#filter-prioritet option[value="' + normalizedPriorityValue + '"]')
      .first()
      .text()
      .trim();

    return priorityLabel || normalizedPriorityValue;
  }

  function escapeHtml(value) {
    return $('<div/>').text(value === null || value === undefined ? '' : String(value)).html();
  }

  function updateStatusCards(statusStats) {
    Object.keys(statusStats || {}).forEach(function (key) {
      $('.status-card[data-status="' + key + '"] .status-count').text(statusStats[key] || 0);
    });
  }

  function formatWorkOrderNumber(value) {
    var rawValue = (value || '').toString().trim();

    if (!rawValue) {
      return 'N/A';
    }

    if (rawValue.indexOf('-') !== -1) {
      return rawValue;
    }

    var digits = rawValue.replace(/\D/g, '');

    if (digits.length === 13) {
      return digits.slice(0, 2) + '-' + digits.slice(2, 7) + '-' + digits.slice(7);
    }

    return rawValue;
  }

  function trimProductName(value, maxLength) {
    var normalizedValue = (value || '').toString().trim();
    var limit = Number(maxLength) || 5;

    if (!normalizedValue) {
      return 'N/A';
    }

    if (normalizedValue.length <= limit) {
      return normalizedValue;
    }

    return normalizedValue.slice(0, limit) + '..';
  }

  function hashClientName(clientName) {
    var normalizedName = (clientName || '').toString().trim().toLowerCase();
    var hash = 0;
    var i;

    if (!normalizedName) {
      return 0;
    }

    for (i = 0; i < normalizedName.length; i++) {
      hash = (hash << 5) - hash + normalizedName.charCodeAt(i);
      hash |= 0;
    }

    return hash >>> 0;
  }

  function resolveClientAvatarColors(clientName) {
    var hash = hashClientName(clientName);
    var hue = hash % 360;
    var saturation = 62 + ((hash >> 8) % 24); // 62-85
    var textLightness = 30 + ((hash >> 13) % 12); // 30-41
    var bgLightness = 48 + ((hash >> 18) % 14); // 48-61

    return {
      text: 'hsl(' + hue + ', ' + Math.min(95, saturation + 8) + '%, ' + textLightness + '%)',
      bg: 'hsla(' + hue + ', ' + saturation + '%, ' + bgLightness + '%, 0.18)',
      border: 'hsla(' + hue + ', ' + saturation + '%, ' + bgLightness + '%, 0.35)'
    };
  }

  function normalizeStatusValue(value) {
    return (value || '')
      .toString()
      .trim()
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '');
  }

  function resolveStatusAppearance(status) {
    var normalizedStatus = normalizeStatusValue(status);

    if (normalizedStatus.includes('otkaz')) {
      return {
        icon: 'x-circle',
        avatarClass: 'status-avatar-zakljucen',
        badgeClass: 'status-badge-zakljucen'
      };
    }

    if (normalizedStatus.includes('djelimic')) {
      return {
        icon: 'pause-circle',
        avatarClass: 'status-avatar-djelimicno-zakljucen',
        badgeClass: 'status-badge-djelimicno-zakljucen'
      };
    }

    if (normalizedStatus.includes('zaklj') || normalizedStatus.includes('zavr')) {
      return {
        icon: 'check-circle',
        avatarClass: 'status-avatar-zakljucen',
        badgeClass: 'status-badge-zakljucen'
      };
    }

    if (normalizedStatus.includes('u toku') || normalizedStatus.includes('u radu')) {
      return {
        icon: 'clock',
        avatarClass: 'status-avatar-u-radu',
        badgeClass: 'status-badge-u-radu'
      };
    }

    if (normalizedStatus.includes('rezerv')) {
      return {
        icon: 'archive',
        avatarClass: 'status-avatar-rezerviran',
        badgeClass: 'status-badge-rezerviran'
      };
    }

    if (normalizedStatus.includes('otvoren')) {
      return {
        icon: 'unlock',
        avatarClass: 'status-avatar-otvoren',
        badgeClass: 'status-badge-otvoren'
      };
    }

    if (normalizedStatus.includes('raspis')) {
      return {
        icon: 'edit-3',
        avatarClass: 'status-avatar-raspisan',
        badgeClass: 'status-badge-raspisan'
      };
    }

    if (normalizedStatus.includes('nacrt')) {
      return {
        icon: 'edit',
        avatarClass: 'status-avatar-raspisan',
        badgeClass: 'status-badge-raspisan'
      };
    }

    if (normalizedStatus.includes('novo') || normalizedStatus.includes('planiran')) {
      return {
        icon: 'plus-circle',
        avatarClass: 'status-avatar-planiran',
        badgeClass: 'status-badge-planiran'
      };
    }

    return {
      icon: 'help-circle',
      avatarClass: 'status-avatar-default',
      badgeClass: 'status-badge-default'
    };
  }

  // datatable
  if (dtInvoiceTable.length) {
    var currentStatusFilter = null;
    var moneyColumnIndex = 5;
    var moneyColumnVisible = null;
    var tableLoadingRequestCount = 0;
    var searchDebounceTimer = null;
    var searchOverlaySafetyTimer = null;
    var sortableColumnMap = {
      0: 'id',
      4: 'klijent',
      7: 'status',
      8: 'prioritet'
    };
    var quickSearchIdleLabel = 'Brza pretraga po nazivu, šifri, klijentu itd..';
    var quickSearchLoadingLabel = 'Filterisanje';
    updateStatusCards(window.statusStats || {});

    function ensureQuickSearchHeaderLabel() {
      var tableWrapper = dtInvoiceTable.closest('.dataTables_wrapper');
      var searchLabel;
      var searchInput;

      if (!tableWrapper.length) {
        tableWrapper = dtInvoiceTable.closest('.card-datatable').find('.dataTables_wrapper').first();
      }

      searchLabel = tableWrapper.find('div.dataTables_filter label').first();
      searchInput = searchLabel.find('input').first();

      if (!searchLabel.length || !searchInput.length) {
        return null;
      }

      if (!searchLabel.find('.invoice-search-label-wrap').length) {
        searchInput.detach();
        searchLabel.empty().append(
          '<span class="invoice-search-label-wrap">' +
            '<span class="spinner-border spinner-border-sm invoice-search-header-spinner" role="status" aria-hidden="true"></span>' +
            '<span class="invoice-search-header-label">' + quickSearchIdleLabel + '</span>' +
          '</span>'
        );
        searchLabel.append(searchInput);
      }

      return searchLabel;
    }

    function setQuickSearchHeaderLoading(isLoading) {
      var searchLabel = ensureQuickSearchHeaderLabel();
      var isBusy = !!isLoading;

      if (!searchLabel || !searchLabel.length) {
        return;
      }

      searchLabel.toggleClass('is-filtering', isBusy);
      searchLabel.find('.invoice-search-header-label').text(isBusy ? quickSearchLoadingLabel : quickSearchIdleLabel);
      searchLabel
        .find('.invoice-search-header-spinner')
        .toggleClass('is-visible', isBusy)
        .attr('aria-hidden', isBusy ? 'false' : 'true');
    }

    function ensureTableLoadingOverlay() {
      var overlayHost = dtInvoiceTable.closest('.card-datatable');

      if (!overlayHost.length) {
        return null;
      }

      overlayHost.addClass('invoice-table-overlay-host');

      var overlay = overlayHost.find('.invoice-table-loading-overlay').first();

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
      var overlayHost = dtInvoiceTable.closest('.card-datatable');
      var overlay = overlayHost.find('.invoice-table-loading-overlay').first();
      var hostElement = overlayHost.get(0);
      var bodyElement = dtInvoiceTable.find('tbody').get(0);
      var top;
      var left;
      var width;
      var height;
      var hostRect;
      var bodyRect;
      var tableRect;
      var headerHeight;

      if (!overlay.length || !hostElement || !bodyElement) {
        return;
      }

      hostRect = hostElement.getBoundingClientRect();
      bodyRect = bodyElement.getBoundingClientRect();

      top = bodyRect.top - hostRect.top;
      left = bodyRect.left - hostRect.left;
      width = bodyRect.width;
      height = bodyRect.height;

      if (!Number.isFinite(width) || width <= 0 || !Number.isFinite(height) || height <= 0) {
        tableRect = dtInvoiceTable.get(0).getBoundingClientRect();
        headerHeight = dtInvoiceTable.find('thead').outerHeight() || 0;
        top = Math.max(0, tableRect.top - hostRect.top + headerHeight);
        left = Math.max(0, tableRect.left - hostRect.left);
        width = Math.max(0, tableRect.width);
        height = Math.max(120, tableRect.height - headerHeight);
      }

      overlay.css({
        top: Math.max(0, top) + 'px',
        left: Math.max(0, left) + 'px',
        width: Math.max(0, width) + 'px',
        height: Math.max(120, height) + 'px'
      });
    }

    function showTableLoadingOverlay() {
      var overlay = ensureTableLoadingOverlay();

      if (!overlay || !overlay.length) {
        return;
      }

      updateTableLoadingOverlayBounds();
      overlay.addClass('is-visible').attr('aria-hidden', 'false');
      setQuickSearchHeaderLoading(true);
    }

    function hideTableLoadingOverlay(force) {
      var overlayHost = dtInvoiceTable.closest('.card-datatable');
      var overlay = overlayHost.find('.invoice-table-loading-overlay').first();

      if (!overlay.length) {
        return;
      }

      if (!force && tableLoadingRequestCount > 0) {
        return;
      }

      overlay.removeClass('is-visible').attr('aria-hidden', 'true');
      setQuickSearchHeaderLoading(false);
    }

    function beginTableLoadingRequest() {
      tableLoadingRequestCount += 1;
      showTableLoadingOverlay();
    }

    function finishTableLoadingRequest() {
      tableLoadingRequestCount = Math.max(0, tableLoadingRequestCount - 1);

      if (tableLoadingRequestCount === 0) {
        hideTableLoadingOverlay(true);
      }
    }

    function scheduleSearchOverlaySafetyHide() {
      if (searchOverlaySafetyTimer) {
        clearTimeout(searchOverlaySafetyTimer);
      }

      searchOverlaySafetyTimer = setTimeout(function () {
        if (tableLoadingRequestCount === 0) {
          hideTableLoadingOverlay(true);
        }
      }, 1000);
    }

    $(window).on('resize.invoiceTableOverlay', function () {
      updateTableLoadingOverlayBounds();
    });

    var dtInvoice = dtInvoiceTable.DataTable({
      processing: true,
      serverSide: true,
      pageLength: 10,
      lengthMenu: [10, 25, 50],
      autoWidth: false,
      ajax: function (requestData, callback) {
        beginTableLoadingRequest();

        var page = Math.floor(requestData.start / requestData.length) + 1;
        var firstOrder = requestData.order && requestData.order.length ? requestData.order[0] : null;
        var orderColumnIndex = firstOrder && firstOrder.column !== undefined ? parseInt(firstOrder.column, 10) : NaN;
        var sortBy = Number.isInteger(orderColumnIndex) ? sortableColumnMap[orderColumnIndex] || '' : '';
        var params = getFilters(currentStatusFilter);

        params.page = page;
        params.limit = requestData.length || 10;
        params.draw = requestData.draw;
        params.search = requestData.search && requestData.search.value ? requestData.search.value : '';
        params.sort_by = sortBy;
        params.sort_dir = firstOrder && firstOrder.dir === 'asc' ? 'asc' : 'desc';

        if (!sortBy) {
          params.sort_dir = '';
        }

        $.ajax({
          url: workOrdersApi,
          method: 'GET',
          dataType: 'json',
          data: params,
          success: function (response) {
            updateStatusCards(response.statusStats || {});
            var showMoneyColumn = !!(response.meta && response.meta.has_money_values);

            if (moneyColumnVisible !== showMoneyColumn && dtInvoice && typeof dtInvoice.column === 'function') {
              dtInvoice.column(moneyColumnIndex).visible(showMoneyColumn, false);
              moneyColumnVisible = showMoneyColumn;
            }

            callback({
              draw: requestData.draw,
              recordsTotal: response.meta && response.meta.total ? response.meta.total : 0,
              recordsFiltered: response.meta && response.meta.filtered_total ? response.meta.filtered_total : 0,
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
          }
        });
      },
      columns: [
        { data: 'id' },
        { data: 'broj_narudzbe' },
        { data: 'naziv' },
        { data: 'sifra' },
        { data: 'klijent' },
        { data: 'vrednost' },
        { data: 'datum_kreiranja' },
        { data: 'status' },
        { data: 'prioritet' }
      ],
      columnDefs: [
        {
          targets: [1, 2, 3, 5, 6],
          orderable: false
        },
        {
          targets: 0,
          className: 'text-nowrap',
          width: '140px',
          orderSequence: ['desc', 'asc'],
          type: 'num',
          render: function (data, type, full) {
            var routeId = full['id'] || full['broj_naloga'];
            var displayNumber = formatWorkOrderNumber(full['broj_naloga_prikaz'] || full['broj_naloga'] || routeId);
            var numericSort = Number(routeId);
            var sortValue = Number.isFinite(numericSort) ? numericSort : routeId;

            return (
              '<span class="d-none">' +
              sortValue +
              '</span>' +
              '<a class="fw-bold text-nowrap" href="' +
              invoicePreview +
              '/' +
              routeId +
              '">' +
              displayNumber +
              '</a>'
            );
          }
        },
        {
          targets: 1,
          width: '150px',
          render: function (data, type, full) {
            var orderNumber = (full['broj_narudzbe'] || full['narudzba_kljuc'] || '').toString().trim();
            var displayNumber = formatWorkOrderNumber(orderNumber);

            if (!orderNumber) {
              return '<span class="text-muted">-</span>';
            }

            return '<span class="text-nowrap">' + escapeHtml(displayNumber) + '</span>';
          }
        },
        {
          targets: 2,
          width: '250px',
          render: function (data, type, full) {
            var productName = (full['naziv'] || 'Radni nalog').toString().trim();
            var displayName = trimProductName(productName, 10);
            var safeName = escapeHtml(productName || 'N/A');
            var safeDisplayName = escapeHtml(displayName);

            return (
              '<span class="text-truncate d-inline-block w-100" title="' +
              safeName +
              '">' +
              safeDisplayName +
              '</span>'
            );
          }
        },
        {
          targets: 3,
          width: '130px',
          render: function (data, type, full) {
            var productCode = (full['sifra'] || '').toString().trim();

            if (!productCode) {
              return '<span class="text-muted">-</span>';
            }

            return '<span class="text-nowrap">' + escapeHtml(productCode) + '</span>';
          }
        },
        {
          targets: 4,
          width: '270px',
          render: function (data, type, full) {
            var name = full['klijent'] || 'N/A',
              dodeljenKorisnik = full['dodeljen_korisnik'] || '';
            var displayName = trimProductName(name, 10);
            var safeName = escapeHtml(name);
            var safeDisplayName = escapeHtml(displayName);
            var avatarColors = resolveClientAvatarColors(name),
              initials = name.match(/\b\w/g) || [];
            initials = ((initials.shift() || '') + (initials.pop() || '')).toUpperCase();

            var output = '<div class="avatar-content">' + initials + '</div>';
            var avatarStyle =
              ' style="background-color: ' +
              avatarColors.bg +
              '; color: ' +
              avatarColors.text +
              '; border: 1px solid ' +
              avatarColors.border +
              ';"';

            return (
              '<div class="d-flex justify-content-left align-items-center">' +
              '<div class="avatar-wrapper">' +
              '<div class="avatar me-50"' +
              avatarStyle +
              '>' +
              output +
              '</div>' +
              '</div>' +
              '<div class="d-flex flex-column">' +
              '<h6 class="user-name text-truncate mb-0" title="' +
              safeName +
              '">' +
              safeDisplayName +
              '</h6>' +
              '<small class="text-truncate text-muted">' +
              dodeljenKorisnik +
              '</small>' +
              '</div>' +
              '</div>'
            );
          }
        },
        {
          targets: 5,
          width: '73px',
          render: function (data, type, full) {
            var total = full['vrednost'];
            var numericTotal = Number(total);
            var hasValue = total !== null && total !== '' && Number.isFinite(numericTotal);

            if (!hasValue) {
              return '<span class="d-none">0</span>-';
            }

            var currency = (full['valuta'] || '').toString().trim();
            var formatted = currency ? currency + ' ' + numericTotal : numericTotal;

            return '<span class="d-none">' + numericTotal + '</span>' + formatted;
          }
        },
        {
          targets: 6,
          width: '130px',
          render: function (data, type, full) {
            var createdDate = new Date(full['datum_kreiranja']);
            return (
              '<span class="d-none">' +
              moment(createdDate).format('YYYYMMDD') +
              '</span>' +
              moment(createdDate).format('DD MMM YYYY')
            );
          }
        },
        {
          targets: 7,
          className: 'text-center align-middle',
          width: '98px',
          render: function (data, type, full) {
            var status = full['status'];
            var statusConfig = resolveStatusAppearance(status);

            return (
              '<div class="d-flex justify-content-center">' +
              '<span class="badge rounded-pill status-badge ' +
              statusConfig.badgeClass +
              '" text-capitalized> ' +
              status +
              ' </span>' +
              '</div>'
            );
          }
        },
        {
          targets: 8,
          width: '80px',
          render: function (data, type, full) {
            var priority = full['prioritet'];
            var badgeClass = 'badge-light-secondary';
            var textColor = '';
            var normalizedPriority = (priority || '').toString().toLowerCase();
            var parsedPriorityCode = parseInt(normalizedPriority.split('-')[0], 10);

            if (
              parsedPriorityCode === 1 ||
              normalizedPriority.includes('visok') ||
              normalizedPriority === 'z' ||
              normalizedPriority === 'high'
            ) {
              badgeClass = 'badge-light-danger';
              textColor = 'text-danger';
            } else if (
              parsedPriorityCode === 5 ||
              normalizedPriority.includes('uobičajen') ||
              normalizedPriority.includes('uobicajen') ||
              normalizedPriority === 'srednji' ||
              normalizedPriority === 's' ||
              normalizedPriority === 'medium'
            ) {
              badgeClass = 'badge-light-warning';
              textColor = 'text-warning';
            } else if (
              parsedPriorityCode === 10 ||
              parsedPriorityCode === 15 ||
              normalizedPriority.includes('nizak') ||
              normalizedPriority.includes('uzor') ||
              normalizedPriority === 'd' ||
              normalizedPriority === 'low'
            ) {
              badgeClass = 'badge-light-info';
              textColor = 'text-info';
            }

            return (
              '<span class="badge rounded-pill ' +
              badgeClass +
              ' ' +
              textColor +
              '" text-capitalized> ' +
              priority +
              ' </span>'
            );
          }
        }
      ],
      ordering: true,
      order: [[0, 'desc']],
      orderMulti: false,
      dom:
        '<"row d-flex justify-content-between align-items-center m-1"' +
        '<"col-lg-6 d-flex align-items-center"l<"dt-action-buttons text-xl-end text-lg-start text-lg-end text-start "B>>' +
        '<"col-lg-6 d-flex align-items-center justify-content-lg-end flex-lg-nowrap flex-wrap pe-lg-1 p-0"f>' +
        '>t' +
        '<"d-flex justify-content-between mx-2 row"' +
        '<"col-sm-12 col-md-6"i>' +
        '<"col-sm-12 col-md-6"p>' +
        '>',
      language: {
        sLengthMenu: 'Prikaži _MENU_',
        search: 'Brza pretraga po nazivu, šifri, klijentu itd..',
        searchPlaceholder: 'Pretraži...',
        info: 'Prikazano _START_ do _END_ od _TOTAL_ naloga',
        infoEmpty: 'Prikazano 0 do 0 od 0 naloga',
        infoFiltered: '(filtrirano od _MAX_ ukupnih naloga)',
        zeroRecords: 'Nema rezultata za odabrani filter',
        emptyTable: 'Nema podataka u tabeli',
        paginate: {
          previous: '&nbsp;',
          next: '&nbsp;'
        }
      },
      buttons: [],
      responsive: false,
      initComplete: function () {
        var tableApi = this.api();
        var searchInput = $(tableApi.table().container()).find('div.dataTables_filter input');

        ensureQuickSearchHeaderLabel();
        setQuickSearchHeaderLoading(false);

        searchInput.off('.DT');

        searchInput.on('input.invoice-search', function () {
          var searchValue = ($(this).val() || '').toString();

          showTableLoadingOverlay();
          scheduleSearchOverlaySafetyHide();

          if (searchDebounceTimer) {
            clearTimeout(searchDebounceTimer);
          }

          searchDebounceTimer = setTimeout(function () {
            if (tableApi.search() === searchValue) {
              if (tableLoadingRequestCount === 0) {
                hideTableLoadingOverlay(true);
              }

              return;
            }

            tableApi.search(searchValue).draw();
          }, 320);
        });

        searchInput.on('keydown.invoice-search', function (event) {
          var searchValue;

          if (event.key !== 'Enter') {
            return;
          }

          event.preventDefault();
          searchValue = ($(this).val() || '').toString();

          if (searchDebounceTimer) {
            clearTimeout(searchDebounceTimer);
          }

          showTableLoadingOverlay();

          if (tableApi.search() === searchValue) {
            if (tableLoadingRequestCount === 0) {
              hideTableLoadingOverlay(true);
            }

            return;
          }

          tableApi.search(searchValue).draw();
        });

        $(document).find('[data-bs-toggle="tooltip"]').tooltip();
        if (typeof feather !== 'undefined') {
          feather.replace();
        }
      },
      drawCallback: function () {
        ensureQuickSearchHeaderLabel();
        updateTableLoadingOverlayBounds();
        hideTableLoadingOverlay();

        $(document).find('[data-bs-toggle="tooltip"]').tooltip();
        if (typeof feather !== 'undefined') {
          feather.replace();
        }
      }
    });

    dtInvoiceTable.find('tbody').on('click', 'tr', function (event) {
      var $target = $(event.target);

      if (
        $(this).hasClass('child') ||
        $(this).find('td.dataTables_empty').length ||
        $target.closest('a, button, input, textarea, select, label, .dropdown, .dropdown-menu').length
      ) {
        return;
      }

      var rowData = dtInvoice.row(this).data();
      if (!rowData || !rowData.id) {
        return;
      }

      window.location.href = invoicePreview + '/' + rowData.id;
    });

    var filtersBody = $('#filters-body');
    var toggleFiltersBtn = $('#btn-toggle-filters');
    var deleteFiltersBtn = $('#btn-delete-filter');
    var activeFiltersContainer = $('#active-filters-container');
    var activeFiltersDivider = $('#active-filters-divider');

    function releaseButtonState($button) {
      if (!$button || !$button.length) {
        return;
      }

      window.requestAnimationFrame(function () {
        $button.removeClass('active focus');
        $button.trigger('blur');
      });
    }

    function renderActiveFilters() {
      var filters = getFilters(currentStatusFilter);
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
          value:
            key === 'status'
              ? getStatusFilterDisplayValue(value)
              : key === 'prioritet'
                ? getPriorityFilterDisplayValue(value)
                : isDateFilterKey(key)
                  ? formatDateForDisplay(value)
                  : value
        });
      });

      if (!activeFilters.length) {
        activeFiltersContainer.empty().addClass('d-none');
        activeFiltersDivider.addClass('d-none');
        return;
      }

      var activeFiltersHtml = activeFilters
        .map(function (filter) {
          var safeKey = escapeHtml(filter.key);
          return (
            '<span class="active-filter-chip" data-filter-key="' +
            safeKey +
            '">' +
            '<span class="active-filter-chip-label">' +
            escapeHtml(filter.label) +
            ':</span>' +
            '<span class="active-filter-chip-value">' +
            escapeHtml(filter.value) +
            '</span>' +
            '<button type="button" class="active-filter-remove" data-filter-key="' +
            safeKey +
            '" aria-label="Ukloni filter">&times;</button>' +
            '</span>'
          );
        })
        .join('');

      activeFiltersContainer.html(activeFiltersHtml).removeClass('d-none');
      activeFiltersDivider.removeClass('d-none');
    }

    function clearSingleFilter(filterKey) {
      if (filterKey === 'status') {
        currentStatusFilter = null;
        $('.status-card').removeClass('status-card-active');
        $('.status-card[data-status="svi"]').addClass('status-card-active');
        return;
      }

      if (filterInputIds[filterKey]) {
        clearFilterInputByKey(filterKey);
      }
    }

    function applyFilters() {
      if (searchDebounceTimer) {
        clearTimeout(searchDebounceTimer);
      }

      renderActiveFilters();
      showTableLoadingOverlay();
      dtInvoice.ajax.reload();
    }

    function setFiltersBodyVisibility(isVisible) {
      filtersBody.toggleClass('d-none', !isVisible);
      toggleFiltersBtn.html(
        '<i data-feather="filter" class="me-50"></i> ' + (isVisible ? 'Sakrij filtere' : 'Pokaži filtere')
      );
      toggleFiltersBtn.attr('aria-expanded', isVisible ? 'true' : 'false');
      if (typeof feather !== 'undefined') {
        feather.replace();
      }
    }

    setFiltersBodyVisibility(false);

    initializeDateFilterPickers();
    renderActiveFilters();

    toggleFiltersBtn.on('click', function () {
      setFiltersBodyVisibility(filtersBody.hasClass('d-none'));
      releaseButtonState($(this));
    });

    $('#btn-add').on('click', function () {
      window.location = invoiceAdd;
    });

    $('.status-card').on('click', function () {
      $('.status-card').removeClass('status-card-active');
      $(this).addClass('status-card-active');

      var status = $(this).data('status');
      currentStatusFilter = status && status !== 'svi' ? status : null;
      applyFilters();
    });

    $('#btn-filter').on('click', function () {
      applyFilters();
    });

    $('#btn-delete-filter').on('click', function () {
      Object.keys(filterInputIds).forEach(function (filterKey) {
        clearFilterInputByKey(filterKey);
      });
      currentStatusFilter = null;
      $('.status-card').removeClass('status-card-active');
      $('.status-card[data-status="svi"]').addClass('status-card-active');
      applyFilters();
      releaseButtonState(deleteFiltersBtn);
    });

    activeFiltersContainer.on('click', '.active-filter-remove', function (e) {
      e.preventDefault();
      clearSingleFilter($(this).data('filter-key'));
      applyFilters();
    });

    $('.filter-input').on('keypress', function (e) {
      if (e.which === 13) {
        applyFilters();
      }
    });
  }
});
