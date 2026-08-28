$(function () {
  'use strict';
  var config = window.planProizvodnjeConfig || {};
  var debounceTimer;
  var tableElement = $('#plan-proizvodnje-tabela');
  var tableLoadingRequestCount = 0;
  function resolveTableOverlayHost() { return tableElement.closest('.card-datatable'); }
  function ensureTableLoadingOverlay() {
    var overlayHost = resolveTableOverlayHost();
    var overlay;
    if (!overlayHost.length) return null;
    overlayHost.addClass('production-plan-table-overlay-host');
    overlay = overlayHost.find('.production-plan-table-loading-overlay').first();
    if (overlay.length) return overlay;
    overlayHost.append(
      '<div class="production-plan-table-loading-overlay" aria-hidden="true">' +
        '<div class="production-plan-table-loading-overlay-content">' +
          '<div class="spinner-border production-plan-table-loading-spinner" role="status" aria-hidden="true"></div>' +
          '<div class="production-plan-table-loading-message">Učitavanje rezultata</div>' +
        '</div>' +
      '</div>'
    );
    return overlayHost.find('.production-plan-table-loading-overlay').first();
  }
  function updateTableLoadingOverlayBounds() {
    var overlayHost = resolveTableOverlayHost();
    var overlay = overlayHost.find('.production-plan-table-loading-overlay').first();
    if (!overlay.length) return;
    overlay.css({ top: '0px', left: '0px', width: '100vw', height: '100vh' });
  }
  function showTableLoadingOverlay() {
    var overlay = ensureTableLoadingOverlay();
    if (!overlay || !overlay.length) return;
    updateTableLoadingOverlayBounds();
    overlay.addClass('is-visible').attr('aria-hidden', 'false');
    window.requestAnimationFrame(updateTableLoadingOverlayBounds);
    window.setTimeout(updateTableLoadingOverlayBounds, 0);
  }
  function hideTableLoadingOverlay(force) {
    var overlay = resolveTableOverlayHost().find('.production-plan-table-loading-overlay').first();
    if (!force && tableLoadingRequestCount > 0) return;
    overlay.removeClass('is-visible').attr('aria-hidden', 'true');
  }
  function beginTableLoadingRequest() { tableLoadingRequestCount += 1; showTableLoadingOverlay(); }
  function finishTableLoadingRequest() {
    tableLoadingRequestCount = Math.max(0, tableLoadingRequestCount - 1);
    if (tableLoadingRequestCount === 0) hideTableLoadingOverlay(true);
  }
  function apiDate(value) { var m = (value || '').match(/^(\d{2})\.(\d{2})\.(\d{4})$/); return m ? m[3] + '-' + m[2] + '-' + m[1] : (value || ''); }
  function filters() { var result = {}; $('[data-filter]').each(function () { result[$(this).data('filter')] = apiDate($(this).val()); }); return result; }
  function esc(value) { return $('<div>').text(value == null ? '' : value).html(); }
  function number(value) { var n = Number(value); return Number.isFinite(n) ? n.toLocaleString('bs-BA', { maximumFractionDigits: 3 }) : esc(value); }
  function date(value) { if (!value) return ''; var m = String(value).match(/^(\d{4})-(\d{2})-(\d{2})/); return m ? m[3] + '.' + m[2] + '.' + m[1] : esc(value); }
  tableElement.on('preXhr.dt', beginTableLoadingRequest);
  tableElement.on('xhr.dt error.dt', function () { window.setTimeout(finishTableLoadingRequest, 0); });
  tableElement.on('draw.dt', function () { updateTableLoadingOverlayBounds(); });
  $(window).on('resize.productionPlanLoader', updateTableLoadingOverlayBounds);
  var table = tableElement.DataTable({
    processing: false, serverSide: true, pageLength: 25, scrollX: true, order: [[8, 'desc']],
    ajax: { url: config.dataUrl, data: function (d) { d.filter = filters(); d.brza_pretraga = (d.search && d.search.value) ? d.search.value : $('#plan-proizvodnje-tabela_filter input').val(); d.sort = ['broj_narudzbenice','kupac','narudzbenica_kupca','broj_pozicije','sifra_artikla','naziv','naruceno','izradeno','datum_isporuke','datum_narudzbe','dobavljac','status_dobavljaca','status_rn','faza_izrade'][d.order[0] ? d.order[0].column : 8]; d.dir = d.order[0] && d.order[0].dir; } },
    language: { processing: 'Učitavanje...', search: 'Pretraga:', lengthMenu: 'Prikaži _MENU_ redova', info: 'Prikazano _START_–_END_ od _TOTAL_ redova', infoEmpty: 'Nema podataka', zeroRecords: 'Nema pronađenih redova', paginate: { next: 'Sljedeća', previous: 'Prethodna' } },
    columns: [
      { data: 'broj_narudzbenice', className: 'document-cell' }, { data: 'kupac' }, { data: 'narudzbenica_kupca' }, { data: 'broj_pozicije' }, { data: 'sifra_artikla' }, { data: 'naziv' },
      { data: 'naruceno', className: 'number-cell', render: number }, { data: 'izradeno', className: 'number-cell', render: number }, { data: 'datum_isporuke', render: date }, { data: 'datum_narudzbe', render: date }, { data: 'dobavljac' }, { data: 'status_dobavljaca', className: 'document-cell' }, { data: 'status_rn' }, { data: 'faza_izrade' }
    ]
  });
  $('.plan-date').each(function () { flatpickr(this, { dateFormat: 'Y-m-d', altInput: true, altFormat: 'd.m.Y', disableMobile: true }); });
  $('#btn-filter').on('click', function () { table.ajax.reload(); });
  $('[data-filter]').on('keydown', function (event) { if (event.key === 'Enter') { event.preventDefault(); table.ajax.reload(); } });
  $('#btn-prikazi-filtere').on('click', function () { var body = $('#tijelo-filtera'); var isVisible = body.is(':visible'); if (isVisible) { body.stop(true, true).slideUp(160); } else { body.removeClass('d-none').hide().stop(true, true).slideDown(160); } $(this).attr('aria-expanded', !isVisible).html('<i data-feather="filter" class="me-50"></i> ' + (isVisible ? 'Prikaži filtere' : 'Sakrij filtere')); if (window.feather) feather.replace(); });
  $('#btn-obrisi-filter').on('click', function () { $('[data-filter]').each(function () { if (this._flatpickr) this._flatpickr.clear(); else $(this).val(''); }); table.ajax.reload(); });
  var exportModalElement = document.getElementById('modal-izvoz');
  exportModalElement.addEventListener('hide.bs.modal', function () { if (document.activeElement && exportModalElement.contains(document.activeElement)) document.activeElement.blur(); });
  exportModalElement.addEventListener('hidden.bs.modal', function () { document.getElementById('btn-izvoz').focus(); });
  $('#btn-izvoz').on('click', function () { bootstrap.Modal.getOrCreateInstance(exportModalElement).show(); });
  $('input[name="opseg-izvoza"]').on('change', function () { $('#raspon-redova').toggleClass('d-none', this.value !== 'redovi'); });
  $('#potvrdi-izvoz').on('click', function () { var params = $.extend({}, filters(), { opseg: $('input[name="opseg-izvoza"]:checked').val(), primijeni_filter: $('#primijeni-trenutni-filter').is(':checked') ? 1 : 0, pocetni_red: $('#pocetni-red').val(), zavrsni_red: $('#zavrsni-red').val() }); window.location.href = config.exportUrl + '?' + $.param({ filter: params, opseg: params.opseg, primijeni_filter: params.primijeni_filter, pocetni_red: params.pocetni_red, zavrsni_red: params.zavrsni_red }); });
  if (window.feather) feather.replace();
});
