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

  // datatable
  if (dtInvoiceTable.length) {
    var currentStatusFilter = null;
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
        { data: 'status' },
        { data: 'datum_kreiranja' },
        { data: 'klijent' },
        { data: 'vrednost' },
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
          width: '46px',
          type: 'num',
          render: function (data, type, full) {
            var nalogId = full['id'] || full['broj_naloga'];
            var numericSort = Number(nalogId);
            var sortValue = Number.isFinite(numericSort) ? numericSort : nalogId;

            return (
              '<span class="d-none">' +
              sortValue +
              '</span>' +
              '<a class="fw-bold" href="' +
              invoicePreview +
              '/' +
              full['id'] +
              '"> #' +
              nalogId +
              '</a>'
            );
          }
        },
        {
          targets: 2,
          width: '42px',
          orderable: false,
          render: function (data, type, full) {
            var status = full['status'],
              datumZavrsetka = full['datum_zavrsetka'],
              vrednost = full['vrednost'];
            var normalizedStatus = (status || '').toString().toLowerCase();
            var statusConfig = { class: 'bg-light-secondary', icon: 'help-circle' };

            if (normalizedStatus.includes('zavr') || normalizedStatus.includes('zaklj')) {
              statusConfig = { class: 'bg-light-success', icon: 'check-circle' };
            } else if (normalizedStatus.includes('u toku') || normalizedStatus.includes('u radu')) {
              statusConfig = { class: 'bg-light-warning', icon: 'clock' };
            } else if (
              normalizedStatus.includes('novo') ||
              normalizedStatus.includes('planiran') ||
              normalizedStatus.includes('otvoren')
            ) {
              statusConfig = { class: 'bg-light-primary', icon: 'plus-circle' };
            } else if (normalizedStatus.includes('otkaz')) {
              statusConfig = { class: 'bg-light-danger', icon: 'x-circle' };
            } else if (normalizedStatus.includes('nacrt')) {
              statusConfig = { class: 'bg-light-info', icon: 'edit' };
            }

            return (
              "<span data-bs-toggle='tooltip' data-bs-html='true' title='<span>" +
              status +
              '<br> <span class="fw-bold">Vrijednost:</span> ' +
              vrednost +
              ' ' +
              (full['valuta'] || 'RSD') +
              '<br> <span class="fw-bold">Datum zavrsetka:</span> ' +
              (datumZavrsetka || 'Nije dostupno') +
              "</span>'>" +
              '<div class="avatar avatar-status ' +
              statusConfig.class +
              '">' +
              '<span class="avatar-content">' +
              feather.icons[statusConfig.icon].toSvg({ class: 'avatar-icon' }) +
              '</span>' +
              '</div>' +
              '</span>'
            );
          }
        },
        {
          targets: 3,
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
          targets: 4,
          width: '73px',
          render: function (data, type, full) {
            var total = full['vrednost'];
            var currency = full['valuta'] || 'RSD';
            return '<span class="d-none">' + total + '</span>' + currency + ' ' + total;
          }
        },
        {
          targets: 5,
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
          targets: 6,
          width: '98px',
          render: function (data, type, full) {
            var status = full['status'];
            var badgeClass = 'badge-light-secondary';
            var textColor = '';
            var normalizedStatus = (status || '').toString().toLowerCase();

            if (normalizedStatus.includes('zavr') || normalizedStatus.includes('zaklj')) {
              badgeClass = 'badge-light-success';
              textColor = 'text-success';
            } else if (normalizedStatus.includes('u toku') || normalizedStatus.includes('u radu')) {
              badgeClass = 'badge-light-warning';
              textColor = 'text-warning';
            } else if (
              normalizedStatus.includes('novo') ||
              normalizedStatus.includes('planiran') ||
              normalizedStatus.includes('otvoren')
            ) {
              badgeClass = 'badge-light-primary';
              textColor = 'text-primary';
            } else if (normalizedStatus.includes('otkaz')) {
              badgeClass = 'badge-light-danger';
              textColor = 'text-danger';
            } else if (normalizedStatus.includes('nacrt')) {
              badgeClass = 'badge-light-info';
              textColor = 'text-info';
            }

            return (
              '<span class="badge rounded-pill ' +
              badgeClass +
              ' ' +
              textColor +
              '" text-capitalized> ' +
              status +
              ' </span>'
            );
          }
        },
        {
          targets: 7,
          width: '80px',
          render: function (data, type, full) {
            var priority = full['prioritet'];
            var badgeClass = 'badge-light-secondary';
            var textColor = '';
            var normalizedPriority = (priority || '').toString().toLowerCase();

            if (normalizedPriority === 'visok' || normalizedPriority === 'z' || normalizedPriority === 'high') {
              badgeClass = 'badge-light-danger';
              textColor = 'text-danger';
            } else if (normalizedPriority === 'srednji' || normalizedPriority === 's' || normalizedPriority === 'medium') {
              badgeClass = 'badge-light-warning';
              textColor = 'text-warning';
            } else if (normalizedPriority === 'nizak' || normalizedPriority === 'd' || normalizedPriority === 'low') {
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
          width: '80px',
          orderable: false,
          render: function (data, type, full) {
            return (
              '<div class="d-flex align-items-center col-actions">' +
              '<a class="me-25" href="' +
              invoicePreview +
              '/' +
              full['id'] +
              '" data-bs-toggle="tooltip" data-bs-placement="top" title="Pregled radnog naloga">' +
              feather.icons['eye'].toSvg({ class: 'font-medium-2 text-body' }) +
              '</a>' +
              '<div class="dropdown">' +
              '<a class="btn btn-sm btn-icon dropdown-toggle hide-arrow" data-bs-toggle="dropdown">' +
              feather.icons['more-vertical'].toSvg({ class: 'font-medium-2 text-body' }) +
              '</a>' +
              '<div class="dropdown-menu dropdown-menu-end">' +
              '<a href="#" class="dropdown-item">' +
              feather.icons['download'].toSvg({ class: 'font-small-4 me-50' }) +
              'Preuzmi</a>' +
              '<a href="' +
              invoiceEdit +
              '" class="dropdown-item">' +
              feather.icons['edit'].toSvg({ class: 'font-small-4 me-50' }) +
              'Uredi</a>' +
              '<a href="#" class="dropdown-item">' +
              feather.icons['trash'].toSvg({ class: 'font-small-4 me-50' }) +
              'Obrisi</a>' +
              '<a href="#" class="dropdown-item">' +
              feather.icons['copy'].toSvg({ class: 'font-small-4 me-50' }) +
              'Dupliciraj</a>' +
              '</div>' +
              '</div>' +
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
        sLengthMenu: 'Prikazi _MENU_',
        search: 'Brza pretraga',
        searchPlaceholder: 'Pretrazi...',
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
              return col.columnIndex !== 2
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

    $('#btn-add-filter').on('click', function () {
      alert('Funkcionalnost "Dodaj filter" ce biti implementirana.');
    });

    $('#btn-delete-filter').on('click', function () {
      $('.filter-input').val('');
      currentStatusFilter = null;
      $('.status-card').removeClass('status-card-active');
      applyFilters();
    });

    $('#btn-save-filter').on('click', function () {
      var filters = {
        kupac: $('#filter-kupac').val(),
        primatelj: $('#filter-primatelj').val(),
        proizvod: $('#filter-proizvod').val(),
        planPocetakOd: $('#filter-plan-pocetak-od').val(),
        planPocetakDo: $('#filter-plan-pocetak-do').val(),
        planKrajOd: $('#filter-plan-kraj-od').val(),
        planKrajDo: $('#filter-plan-kraj-do').val(),
        datumOd: $('#filter-datum-od').val(),
        datumDo: $('#filter-datum-do').val(),
        vezniDok: $('#filter-vezni-dok').val(),
        status: currentStatusFilter
      };

      localStorage.setItem('radniNaloziFilters', JSON.stringify(filters));
      alert('Filteri su sacuvani!');
    });

    var savedFilters = localStorage.getItem('radniNaloziFilters');
    if (savedFilters) {
      try {
        var filters = JSON.parse(savedFilters);
        $('#filter-kupac').val(filters.kupac || '');
        $('#filter-primatelj').val(filters.primatelj || '');
        $('#filter-proizvod').val(filters.proizvod || '');
        $('#filter-plan-pocetak-od').val(filters.planPocetakOd || '');
        $('#filter-plan-pocetak-do').val(filters.planPocetakDo || '');
        $('#filter-plan-kraj-od').val(filters.planKrajOd || '');
        $('#filter-plan-kraj-do').val(filters.planKrajDo || '');
        $('#filter-datum-od').val(filters.datumOd || '');
        $('#filter-datum-do').val(filters.datumDo || '');
        $('#filter-vezni-dok').val(filters.vezniDok || '');

        if (filters.status) {
          currentStatusFilter = filters.status;
          $('.status-card[data-status="' + filters.status + '"]').addClass('status-card-active');
        }

        applyFilters();
      } catch (e) {
        console.error('Error loading saved filters:', e);
      }
    }

    $('.filter-input').on('keypress', function (e) {
      if (e.which === 13) {
        applyFilters();
      }
    });
  }
});
