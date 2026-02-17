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
      data: window.radniNaloziData || [],
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
          type: 'num',
          render: function (data, type, full, meta) {
            var $nalogId = full['id'] || full['broj_naloga'];
            var numericSort = Number($nalogId);
            var sortValue = Number.isFinite(numericSort) ? numericSort : $nalogId;
            // Creates full output for row, include hidden sort helper
            var $rowOutput =
              '<span class="d-none">' + sortValue + '</span>' +
              '<a class="fw-bold" href="' + invoicePreview + '/' + full['id'] + '"> #' + $nalogId + '</a>';
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
              $vrednost = full['vrednost'];
            var normalizedStatus = ($status || '').toString().toLowerCase();
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
              $status +
              '<br> <span class="fw-bold">Vrijednost:</span> ' +
              $vrednost + ' ' + (full['valuta'] || 'RSD') +
              '<br> <span class="fw-bold">Datum završetka:</span> ' +
              ($datumZavrsetka || 'Nije dostupno') +
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
            var $text_color = '';
            var normalizedStatus = ($status || '').toString().toLowerCase();

            if (normalizedStatus.includes('zavr') || normalizedStatus.includes('zaklj')) {
              $badge_class = 'badge-light-success';
              $text_color = 'text-success';
            } else if (normalizedStatus.includes('u toku') || normalizedStatus.includes('u radu')) {
              $badge_class = 'badge-light-warning';
              $text_color = 'text-warning';
            } else if (
              normalizedStatus.includes('novo') ||
              normalizedStatus.includes('planiran') ||
              normalizedStatus.includes('otvoren')
            ) {
              $badge_class = 'badge-light-primary';
              $text_color = 'text-primary';
            } else if (normalizedStatus.includes('otkaz')) {
              $badge_class = 'badge-light-danger';
              $text_color = 'text-danger';
            } else if (normalizedStatus.includes('nacrt')) {
              $badge_class = 'badge-light-info';
              $text_color = 'text-info';
            }

            return '<span class="badge rounded-pill ' + $badge_class + ' ' + $text_color + '" text-capitalized> ' + $status + ' </span>';
          }
        },
        {
          // Priority
          targets: 7,
          width: '80px',
          render: function (data, type, full, meta) {
            var $priority = full['prioritet'];
            var $badge_class = 'badge-light-secondary';
            var $text_color = '';
            var normalizedPriority = ($priority || '').toString().toLowerCase();

            if (normalizedPriority === 'visok' || normalizedPriority === 'z' || normalizedPriority === 'high') {
              $badge_class = 'badge-light-danger';
              $text_color = 'text-danger';
            } else if (normalizedPriority === 'srednji' || normalizedPriority === 's' || normalizedPriority === 'medium') {
              $badge_class = 'badge-light-warning';
              $text_color = 'text-warning';
            } else if (normalizedPriority === 'nizak' || normalizedPriority === 'd' || normalizedPriority === 'low') {
              $badge_class = 'badge-light-info';
              $text_color = 'text-info';
            }
            
            return '<span class="badge rounded-pill ' + $badge_class + ' ' + $text_color + '" text-capitalized> ' + $priority + ' </span>';
          }
        },
        {
          // Actions
          targets: -1,
          title: 'Akcije',
          width: '80px',
          orderable: false,
          render: function (data, type, full, meta) {
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
              'Obriši</a>' +
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
      order: [[1, 'asc']],
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
          // remove previous & next text from pagination
          previous: '&nbsp;',
          next: '&nbsp;'
        }
      },
      // Buttons with Dropdown
      buttons: [],
      // For responsive popup
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
        // Initialize feather icons
        if (typeof feather !== 'undefined') {
          feather.replace();
        }
      },
      drawCallback: function () {
        $(document).find('[data-bs-toggle="tooltip"]').tooltip();
        // Initialize feather icons
        if (typeof feather !== 'undefined') {
          feather.replace();
        }
      }
    });

    // Handle "Dodaj radni nalog" button click
    $('#btn-add').on('click', function() {
      window.location = invoiceAdd;
    });

    // Filtering functionality
    var currentStatusFilter = null;
    
    // Status card click handler
    $('.status-card').on('click', function() {
      $('.status-card').removeClass('status-card-active');
      $(this).addClass('status-card-active');
      
      var status = $(this).data('status');
      currentStatusFilter = status;
      
      applyFilters();
    });

    // Store the custom filter function reference
    var customFilterFunction = null;

    // Apply filters function
    function applyFilters() {
      // Remove previous custom filter if it exists
      if (customFilterFunction !== null) {
        var idx = $.fn.dataTable.ext.search.indexOf(customFilterFunction);
        if (idx !== -1) {
          $.fn.dataTable.ext.search.splice(idx, 1);
        }
      }

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
        vezniDok: $('#filter-vezni-dok').val()
      };

      // Custom filtering function
      customFilterFunction = function(settings, data, dataIndex) {
          var row = dtInvoice.row(dataIndex).data();
          if (!row) return false;

          // Status filter
          if (currentStatusFilter && currentStatusFilter !== 'svi') {
            var rowStatus = (row['status'] || '').toLowerCase();
            var statusMatch = false;
            
            switch(currentStatusFilter) {
              case 'planiran':
                statusMatch = rowStatus.includes('planiran') || rowStatus.includes('novo');
                break;
              case 'otvoren':
                statusMatch = rowStatus.includes('otvoren') || rowStatus.includes('novo');
                break;
              case 'rezerviran':
                statusMatch = rowStatus.includes('rezerviran');
                break;
              case 'raspisan':
                statusMatch = rowStatus.includes('raspisan');
                break;
              case 'u_radu':
                statusMatch = rowStatus.includes('u toku') || rowStatus.includes('u radu');
                break;
              case 'djelimicno_zakljucen':
                statusMatch = rowStatus.includes('djelimicno') || rowStatus.includes('djelomicno');
                break;
              case 'zakljucen':
                statusMatch = rowStatus.includes('zavr') || rowStatus.includes('zaklj');
                break;
            }
            
            if (!statusMatch) return false;
          }

          // Text filters
          if (filters.kupac) {
            var klijent = (row['klijent'] || '').toLowerCase();
            if (!klijent.includes(filters.kupac.toLowerCase())) return false;
          }

          if (filters.primatelj) {
            var primatelj = (row['klijent'] || row['dodeljen_korisnik'] || '').toLowerCase();
            if (!primatelj.includes(filters.primatelj.toLowerCase())) return false;
          }

          if (filters.proizvod) {
            var proizvod = (row['opis'] || '').toLowerCase();
            if (!proizvod.includes(filters.proizvod.toLowerCase())) return false;
          }

          if (filters.vezniDok) {
            var vezniDok = (row['broj_naloga'] || '').toLowerCase();
            if (!vezniDok.includes(filters.vezniDok.toLowerCase())) return false;
          }

          // Date filters
          if (filters.datumOd) {
            var datumKreiranja = row['datum_kreiranja'] || '';
            if (datumKreiranja && datumKreiranja < filters.datumOd) return false;
          }

          if (filters.datumDo) {
            var datumKreiranja = row['datum_kreiranja'] || '';
            if (datumKreiranja && datumKreiranja > filters.datumDo) return false;
          }

          return true;
        };

      // Push the new filter function
      $.fn.dataTable.ext.search.push(customFilterFunction);

      dtInvoice.draw();
    }

    // Filter button click
    $('#btn-filter').on('click', function() {
      applyFilters();
    });

    // Add filter button (placeholder - can be extended)
    $('#btn-add-filter').on('click', function() {
      // Can add dynamic filter rows here in the future
      alert('Funkcionalnost "Dodaj filter" će biti implementirana.');
    });

    // Clear filters button
    $('#btn-delete-filter').on('click', function() {
      $('.filter-input').val('');
      currentStatusFilter = null;
      $('.status-card').removeClass('status-card-active');
      
      // Remove custom filter
      if (customFilterFunction !== null) {
        var idx = $.fn.dataTable.ext.search.indexOf(customFilterFunction);
        if (idx !== -1) {
          $.fn.dataTable.ext.search.splice(idx, 1);
        }
        customFilterFunction = null;
      }
      
      dtInvoice.draw();
    });

    // Save filter (placeholder - can be extended to save to localStorage)
    $('#btn-save-filter').on('click', function() {
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
      alert('Filteri su sačuvani!');
    });

    // Load saved filters (on page load)
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
      } catch(e) {
        console.error('Error loading saved filters:', e);
      }
    }

    // Enter key to apply filter
    $('.filter-input').on('keypress', function(e) {
      if (e.which === 13) {
        $('#btn-filter').click();
      }
    });
  }
});
