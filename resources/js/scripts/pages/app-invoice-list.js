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
      plan_pocetak_od: $('#filter-plan-pocetak-od').val() || '',
      plan_pocetak_do: $('#filter-plan-pocetak-do').val() || '',
      plan_kraj_od: $('#filter-plan-kraj-od').val() || '',
      plan_kraj_do: $('#filter-plan-kraj-do').val() || '',
      datum_od: $('#filter-datum-od').val() || '',
      datum_do: $('#filter-datum-do').val() || '',
      vezni_dok: $('#filter-vezni-dok').val() || ''
    };
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
    var moneyColumnIndex = 3;
    var moneyColumnVisible = null;
    updateStatusCards(window.statusStats || {});

    var dtInvoice = dtInvoiceTable.DataTable({
      processing: true,
      serverSide: true,
      pageLength: 10,
      lengthMenu: [10, 25, 50],
      autoWidth: false,
      ajax: function (requestData, callback) {
        var page = Math.floor(requestData.start / requestData.length) + 1;
        var params = getFilters(currentStatusFilter);

        params.page = page;
        params.limit = requestData.length || 10;
        params.draw = requestData.draw;
        params.search = requestData.search && requestData.search.value ? requestData.search.value : '';

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
          }
        });
      },
      columns: [
        { data: 'responsive_id' },
        { data: 'id' },
        { data: 'klijent' },
        { data: 'vrednost' },
        { data: 'datum_kreiranja' },
        { data: 'status' },
        { data: 'prioritet' },
        { data: '' }
      ],
      columnDefs: [
        {
          className: 'control',
          responsivePriority: 2,
          targets: 0,
          orderable: false
        },
        {
          targets: 1,
          className: 'text-nowrap',
          width: '140px',
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
          targets: 2,
          responsivePriority: 4,
          width: '270px',
          render: function (data, type, full) {
            var name = full['klijent'] || 'N/A',
              dodeljenKorisnik = full['dodeljen_korisnik'] || '';
            var stateNum = Math.floor(Math.random() * 6),
              states = ['success', 'danger', 'warning', 'info', 'primary', 'secondary'],
              state = states[stateNum],
              initials = name.match(/\b\w/g) || [];
            initials = ((initials.shift() || '') + (initials.pop() || '')).toUpperCase();

            var output = '<div class="avatar-content">' + initials + '</div>';
            var colorClass = ' bg-light-' + state + ' ';

            return (
              '<div class="d-flex justify-content-left align-items-center">' +
              '<div class="avatar-wrapper">' +
              '<div class="avatar' +
              colorClass +
              'me-50">' +
              output +
              '</div>' +
              '</div>' +
              '<div class="d-flex flex-column">' +
              '<h6 class="user-name text-truncate mb-0">' +
              name +
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
          targets: 3,
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
          targets: 4,
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
          targets: 5,
          width: '98px',
          render: function (data, type, full) {
            var status = full['status'];
            var statusConfig = resolveStatusAppearance(status);

            return (
              '<span class="badge rounded-pill status-badge ' +
              statusConfig.badgeClass +
              '" text-capitalized> ' +
              status +
              ' </span>'
            );
          }
        },
        {
          targets: 6,
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
        },
        {
          targets: -1,
          title: 'Akcije',
          width: '48px',
          orderable: false,
          render: function (data, type, full) {
            return (
              '<div class="d-flex align-items-center justify-content-center col-actions">' +
              '<a href="' +
              invoicePreview +
              '/' +
              full['id'] +
              '" data-bs-toggle="tooltip" data-bs-placement="top" title="Pregled radnog naloga">' +
              feather.icons['eye'].toSvg({ class: 'font-medium-2 text-body' }) +
              '</a>' +
              '</div>'
            );
          }
        }
      ],
      ordering: false,
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
        search: 'Brza pretraga',
        searchPlaceholder: 'Pretraži...',
        info: 'Prikazano _START_ do _END_ od _TOTAL_ unosa',
        infoEmpty: 'Prikazano 0 do 0 od 0 unosa',
        infoFiltered: '(filtrirano od _MAX_ ukupnih unosa)',
        paginate: {
          previous: '&nbsp;',
          next: '&nbsp;'
        }
      },
      buttons: [],
      responsive: {
        details: {
          display: $.fn.dataTable.Responsive.display.modal({
            header: function (row) {
              var data = row.data();
              return 'Detalji od ' + data['klijent'];
            }
          }),
          type: 'column',
          renderer: function (api, rowIdx, columns) {
            var data = $.map(columns, function (col) {
              return col.columnIndex !== 0 && col.title !== 'Akcije'
                ? '<tr data-dt-row="' +
                    col.rowIdx +
                    '" data-dt-column="' +
                    col.columnIndex +
                    '">' +
                    '<td>' +
                    col.title +
                    ':</td> ' +
                    '<td>' +
                    col.data +
                    '</td>' +
                    '</tr>'
                : '';
            }).join('');
            return data ? $('<table class="table"/>').append('<tbody>' + data + '</tbody>') : false;
          }
        }
      },
      initComplete: function () {
        $(document).find('[data-bs-toggle="tooltip"]').tooltip();
        if (typeof feather !== 'undefined') {
          feather.replace();
        }
      },
      drawCallback: function () {
        $(document).find('[data-bs-toggle="tooltip"]').tooltip();
        if (typeof feather !== 'undefined') {
          feather.replace();
        }
      }
    });

    function applyFilters() {
      dtInvoice.ajax.reload();
    }

    var filtersBody = $('#filters-body');
    var toggleFiltersBtn = $('#btn-toggle-filters');

    function setFiltersBodyVisibility(isVisible) {
      filtersBody.toggleClass('d-none', !isVisible);
      toggleFiltersBtn.html(
        '<i data-feather="filter" class="me-50"></i> ' + (isVisible ? 'Sakrij filtere' : 'Pokaži filtere')
      );
      toggleFiltersBtn.attr('aria-expanded', isVisible ? 'true' : 'false');
      localStorage.setItem('radniNaloziFiltersBodyVisible', isVisible ? '1' : '0');
      if (typeof feather !== 'undefined') {
        feather.replace();
      }
    }

    var savedFiltersBodyVisibility = localStorage.getItem('radniNaloziFiltersBodyVisible');
    if (savedFiltersBodyVisibility === null) {
      setFiltersBodyVisibility(true);
    } else {
      setFiltersBodyVisibility(savedFiltersBodyVisibility === '1');
    }

    toggleFiltersBtn.on('click', function () {
      setFiltersBodyVisibility(filtersBody.hasClass('d-none'));
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
      $('.filter-input').val('');
      currentStatusFilter = null;
      $('.status-card').removeClass('status-card-active');
      applyFilters();
    });

    $('.filter-input').on('keypress', function (e) {
      if (e.which === 13) {
        applyFilters();
      }
    });
  }
});
