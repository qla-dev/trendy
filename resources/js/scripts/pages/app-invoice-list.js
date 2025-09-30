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
    invoiceEdit = 'app-invoice-edit.html';

  if ($('body').attr('data-framework') === 'laravel') {
    assetPath = $('body').attr('data-asset-path');
    invoicePreview = assetPath + 'app/invoice/preview';
    invoiceAdd = assetPath + 'app/invoice/add';
    invoiceEdit = assetPath + 'app/invoice/edit';
  }

  // datatable
  if (dtInvoiceTable.length) {
    var dtInvoice = dtInvoiceTable.DataTable({
      data: window.radniNaloziData || [], // Use data from PawsController
      autoWidth: false,
      columns: [
        // columns according to PAWS data structure
        { data: 'responsive_id' },
        { data: 'id' }, // PAWS acKey
        { data: 'status' }, // PAWS status
        { data: 'datum_kreiranja' }, // PAWS date
        { data: 'klijent' }, // PAWS client
        { data: 'vrednost' }, // PAWS value
        { data: 'status' }, // PAWS status again
        { data: 'prioritet' }, // PAWS priority
        { data: '' }
      ],
      columnDefs: [
        {
          // For Responsive
          className: 'control',
          responsivePriority: 2,
          targets: 0
        },
        {
          // Radni Nalog ID
          targets: 1,
          width: '46px',
          render: function (data, type, full, meta) {
            var $nalogId = full['id'] || full['broj_naloga'];
            // Creates full output for row
            var $rowOutput = '<a class="fw-bold" href="' + invoicePreview + '/' + full['id'] + '"> #' + $nalogId + '</a>';
            return $rowOutput;
          }
        },
        {
          // Radni Nalog Status
          targets: 2,
          width: '42px',
          render: function (data, type, full, meta) {
            var $status = full['status'],
              $datumZavrsetka = full['datum_zavrsetka'],
              $vrednost = full['vrednost'],
              roleObj = {
                'Završeno': { class: 'bg-light-success', icon: 'check-circle' },
                'U toku': { class: 'bg-light-warning', icon: 'clock' },
                'Novo': { class: 'bg-light-primary', icon: 'plus-circle' },
                'Otkažano': { class: 'bg-light-danger', icon: 'x-circle' },
                'Draft': { class: 'bg-light-info', icon: 'edit' }
              };
            
            var statusConfig = roleObj[$status] || { class: 'bg-light-secondary', icon: 'help-circle' };
            
            return (
              "<span data-bs-toggle='tooltip' data-bs-html='true' title='<span>" +
              $status +
              '<br> <span class="fw-bold">Vrednost:</span> ' +
              $vrednost + ' ' + (full['valuta'] || 'RSD') +
              '<br> <span class="fw-bold">Datum završetka:</span> ' +
              ($datumZavrsetka || 'N/A') +
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
          // Client name and Service
          targets: 3,
          responsivePriority: 4,
          width: '270px',
          render: function (data, type, full, meta) {
            var $name = full['klijent'],
              $dodeljenKorisnik = full['dodeljen_korisnik'],
              $magacin = full['magacin'],
              stateNum = Math.floor(Math.random() * 6),
              states = ['success', 'danger', 'warning', 'info', 'primary', 'secondary'],
              $state = states[stateNum],
              $initials = $name.match(/\b\w/g) || [];
            $initials = (($initials.shift() || '') + ($initials.pop() || '')).toUpperCase();
            
            // For Avatar badge
            var $output = '<div class="avatar-content">' + $initials + '</div>';
            var colorClass = ' bg-light-' + $state + ' ';

            var $rowOutput =
              '<div class="d-flex justify-content-left align-items-center">' +
              '<div class="avatar-wrapper">' +
              '<div class="avatar' +
              colorClass +
              'me-50">' +
              $output +
              '</div>' +
              '</div>' +
              '<div class="d-flex flex-column">' +
              '<h6 class="user-name text-truncate mb-0">' +
              $name +
              '</h6>' +
              '<small class="text-truncate text-muted">' +
              $dodeljenKorisnik +
              '</small>' +
              '</div>' +
              '</div>';
            return $rowOutput;
          }
        },
        {
          // Total Amount
          targets: 4,
          width: '73px',
          render: function (data, type, full, meta) {
            var $total = full['vrednost'];
            var $currency = full['valuta'] || 'RSD';
            return '<span class="d-none">' + $total + '</span>' + $currency + ' ' + $total;
          }
        },
        {
          // Created Date
          targets: 5,
          width: '130px',
          render: function (data, type, full, meta) {
            var $createdDate = new Date(full['datum_kreiranja']);
            // Creates full output for row
            var $rowOutput =
              '<span class="d-none">' +
              moment($createdDate).format('YYYYMMDD') +
              '</span>' +
              moment($createdDate).format('DD MMM YYYY');
            return $rowOutput;
          }
        },
        {
          // Status Badge
          targets: 6,
          width: '98px',
          render: function (data, type, full, meta) {
            var $status = full['status'];
            var $badge_class = 'badge-light-secondary';
            
            if ($status === 'Završeno') {
              $badge_class = 'badge-light-success';
            } else if ($status === 'U toku') {
              $badge_class = 'badge-light-warning';
            } else if ($status === 'Novo') {
              $badge_class = 'badge-light-primary';
            } else if ($status === 'Otkažano') {
              $badge_class = 'badge-light-danger';
            }
            
            return '<span class="badge rounded-pill ' + $badge_class + '" text-capitalized> ' + $status + ' </span>';
          }
        },
        {
          // Priority
          targets: 7,
          width: '80px',
          render: function (data, type, full, meta) {
            var $priority = full['prioritet'];
            var $badge_class = 'badge-light-secondary';
            
            if ($priority === 'Visok') {
              $badge_class = 'badge-light-danger';
            } else if ($priority === 'Srednji') {
              $badge_class = 'badge-light-warning';
            } else if ($priority === 'Nizak') {
              $badge_class = 'badge-light-info';
            }
            
            return '<span class="badge rounded-pill ' + $badge_class + '" text-capitalized> ' + $priority + ' </span>';
          }
        },
        {
          // Actions
          targets: -1,
          title: 'Actions',
          width: '80px',
          orderable: false,
          render: function (data, type, full, meta) {
            return (
              '<div class="d-flex align-items-center col-actions">' +
              '<a class="me-1" href="#" data-bs-toggle="tooltip" data-bs-placement="top" title="Send Mail">' +
              feather.icons['send'].toSvg({ class: 'font-medium-2 text-body' }) +
              '</a>' +
              '<a class="me-25" href="' +
              invoicePreview +
              '" data-bs-toggle="tooltip" data-bs-placement="top" title="Preview Invoice">' +
              feather.icons['eye'].toSvg({ class: 'font-medium-2 text-body' }) +
              '</a>' +
              '<div class="dropdown">' +
              '<a class="btn btn-sm btn-icon dropdown-toggle hide-arrow" data-bs-toggle="dropdown">' +
              feather.icons['more-vertical'].toSvg({ class: 'font-medium-2 text-body' }) +
              '</a>' +
              '<div class="dropdown-menu dropdown-menu-end">' +
              '<a href="#" class="dropdown-item">' +
              feather.icons['download'].toSvg({ class: 'font-small-4 me-50' }) +
              'Download</a>' +
              '<a href="' +
              invoiceEdit +
              '" class="dropdown-item">' +
              feather.icons['edit'].toSvg({ class: 'font-small-4 me-50' }) +
              'Edit</a>' +
              '<a href="#" class="dropdown-item">' +
              feather.icons['trash'].toSvg({ class: 'font-small-4 me-50' }) +
              'Delete</a>' +
              '<a href="#" class="dropdown-item">' +
              feather.icons['copy'].toSvg({ class: 'font-small-4 me-50' }) +
              'Duplicate</a>' +
              '</div>' +
              '</div>' +
              '</div>'
            );
          }
        }
      ],
      order: [[1, 'desc']],
      dom:
        '<"row d-flex justify-content-between align-items-center m-1"' +
        '<"col-lg-6 d-flex align-items-center"l<"dt-action-buttons text-xl-end text-lg-start text-lg-end text-start "B>>' +
        '<"col-lg-6 d-flex align-items-center justify-content-lg-end flex-lg-nowrap flex-wrap pe-lg-1 p-0"f<"invoice_status ms-sm-2">>' +
        '>t' +
        '<"d-flex justify-content-between mx-2 row"' +
        '<"col-sm-12 col-md-6"i>' +
        '<"col-sm-12 col-md-6"p>' +
        '>',
      language: {
        sLengthMenu: 'Show _MENU_',
        search: 'Search',
        searchPlaceholder: 'Search Invoice',
        paginate: {
          // remove previous & next text from pagination
          previous: '&nbsp;',
          next: '&nbsp;'
        }
      },
      // Buttons with Dropdown
      buttons: [
        {
          text: 'Add Record',
          className: 'btn btn-primary btn-add-record ms-2',
          action: function (e, dt, button, config) {
            window.location = invoiceAdd;
          }
        }
      ],
      // For responsive popup
      responsive: {
        details: {
          display: $.fn.dataTable.Responsive.display.modal({
            header: function (row) {
              var data = row.data();
              return 'Details of ' + data['client_name'];
            }
          }),
          type: 'column',
          renderer: function (api, rowIdx, columns) {
            var data = $.map(columns, function (col, i) {
              return col.columnIndex !== 2 // ? Do not show row in modal popup if title is blank (for check box)
                ? '<tr data-dt-row="' +
                    col.rowIdx +
                    '" data-dt-column="' +
                    col.columnIndex +
                    '">' +
                    '<td>' +
                    col.title +
                    ':' +
                    '</td> ' +
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
        // Adding role filter once table initialized
        this.api()
          .columns(7)
          .every(function () {
            var column = this;
            var select = $(
              '<select id="UserRole" class="form-select ms-50 text-capitalize"><option value=""> Select Status </option></select>'
            )
              .appendTo('.invoice_status')
              .on('change', function () {
                var val = $.fn.dataTable.util.escapeRegex($(this).val());
                column.search(val ? '^' + val + '$' : '', true, false).draw();
              });

            column
              .data()
              .unique()
              .sort()
              .each(function (d, j) {
                select.append('<option value="' + d + '" class="text-capitalize">' + d + '</option>');
              });
          });
      },
      drawCallback: function () {
        $(document).find('[data-bs-toggle="tooltip"]').tooltip();
      }
    });
  }
});
