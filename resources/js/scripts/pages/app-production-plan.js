$(function () {
  'use strict';
  var config = window.planProizvodnjeConfig || {};
  var csrf = $('meta[name="csrf-token"]').attr('content');
  var tableElement = $('#plan-proizvodnje-tabela');
  var keys = ['progress', 'rn', 'narucitelj', 'prioritet', 'datum', 'narudzba', 'pozicija', 'pocetak', 'kraj', 'proizvod', 'plan_kol', 'izr_kol', 'naziv', 'napomena'];
  var requestErrorShown = false;
  var priorityFilter = $('#filter-prioritet');

  if (priorityFilter.length && $.fn.select2) {
    priorityFilter.select2({
      width: '100%',
      placeholder: 'Svi prioriteti',
      allowClear: true,
      minimumResultsForSearch: 0
    });
  }

  function showError(title, message) {
    if (window.Swal && typeof window.Swal.fire === 'function') {
      window.Swal.fire({ icon: 'error', title: title, text: message, confirmButtonText: 'U redu', confirmButtonClass: 'btn btn-primary', buttonsStyling: false });
      return;
    }
    window.alert(title + '\n\n' + message);
  }
  function showRequestError(xhr) {
    if (requestErrorShown) return;
    requestErrorShown = true;
    var message = 'Podaci plana proizvodnje trenutno nisu dostupni. Pokušajte ponovo za nekoliko trenutaka.';
    if (xhr && xhr.status === 403) message = 'Nemate dozvolu za pregled plana proizvodnje.';
    showError('Učitavanje plana nije uspjelo', message);
  }
  function escapeHtml(value) { return $('<div>').text(value == null ? '' : value).html(); }
  function formatNumber(value) { var number = Number(value); return Number.isFinite(number) ? number.toLocaleString('bs-BA', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : ''; }
  function formatDate(value) { var match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})/); return match ? match[3] + '.' + match[2] + '.' + match[1] : ''; }
  function filters() { var values = {}; $('.f').each(function () { values[$(this).data('k')] = $(this).val(); }); return values; }
  function editable(key) { return config.canEdit && key !== 'rn' && key !== 'progress' && key !== 'prioritet'; }
  function closeEditor() { $('.plan-inline-editor').remove(); }

  // DataTables' technical alerts are replaced with friendly Bosnian messages.
  $.fn.dataTable.ext.errMode = 'none';
  var table = tableElement.DataTable({
    serverSide: true, processing: true, scrollX: true, pageLength: 25, order: [[4, 'desc']],
    ajax: {
      url: config.dataUrl,
      data: function (data) { data.filter = filters(); data.sort = keys[data.order[0] ? data.order[0].column : 4]; data.dir = data.order[0] ? data.order[0].dir : 'desc'; },
      error: function (xhr) { showRequestError(xhr); }
    },
    language: { processing: 'Učitavanje...', search: 'Pretraga:', lengthMenu: 'Prikaži _MENU_ redova', info: 'Prikaz _START_ do _END_ od _TOTAL_ radnih naloga', infoEmpty: 'Nema podataka', zeroRecords: 'Nema pronađenih radnih naloga', paginate: { next: 'Sljedeća', previous: 'Prethodna' } },
    columns: keys.map(function (key) {
      return { data: key, defaultContent: '', orderable: key !== 'progress', render: function (value) {
        var output;
        if (key === 'progress') output = '<b>' + escapeHtml(value) + '%</b>';
        else if (key === 'plan_kol' || key === 'izr_kol') output = formatNumber(value);
        else if (['datum', 'pocetak', 'kraj'].indexOf(key) >= 0) output = formatDate(value);
        else output = escapeHtml(value);
        return editable(key) ? '<span class="editable-cell">' + output + '</span>' : output;
      }};
    })
  });
  tableElement.on('xhr.dt', function () { requestErrorShown = false; });
  tableElement.on('error.dt', function (event, settings, techNote) {
    if (techNote === 4) showError('Prikaz podataka nije uspio', 'Primljeni podaci nisu u očekivanom formatu. Obavijestite administratora ako se problem ponovi.');
    else showRequestError();
  });
  $('#filter').on('click', function () { table.ajax.reload(); });
  $('#btn-prikazi-filtere').on('click', function () { $('#tijelo-filtera').toggleClass('d-none'); });
  $('#btn-obrisi-filter').on('click', function () { $('.f').each(function () { if (this._flatpickr) this._flatpickr.clear(); else if ($(this).is('select')) $(this).val('').trigger('change'); else $(this).val(''); }); $('.f[data-k="year"]').val(new Date().getFullYear()); table.ajax.reload(); });
  tableElement.find('tbody').on('click', 'td', function () {
    var cell = table.cell(this), row = table.row($(this).closest('tr')).data(), key = keys[cell.index().column];
    if (!row || !editable(key)) return;
    closeEditor();
    var rect = this.getBoundingClientRect(), multiline = key === 'napomena';
    var editor = $('<div class="plan-inline-editor" data-id="' + escapeHtml(row.id) + '" data-field="' + key + '"><label>Uredi polje</label>' + (multiline ? '<textarea class="form-control">' + escapeHtml(row[key]) + '</textarea>' : '<input class="form-control" value="' + escapeHtml(row[key]) + '">') + '<div class="mt-50"><button class="btn btn-primary btn-sm save">Sačuvaj</button><button class="btn btn-outline-secondary btn-sm cancel ms-50">Odustani</button></div></div>').appendTo('body');
    var width = Math.min(300, window.innerWidth - 16);
    editor.css({ position: 'fixed', zIndex: 2000, left: Math.min(rect.left, window.innerWidth - width - 8), top: rect.bottom + 4, width: width });
    editor.find('input,textarea').focus();
  });
  $('body').on('click', '.plan-inline-editor .cancel', closeEditor).on('click', '.plan-inline-editor .save', function () {
    var editor = $(this).closest('.plan-inline-editor');
    $.post(String(config.fieldUrl).replace('__RN__', editor.data('id')), { _token: csrf, field: editor.data('field'), value: editor.find('input,textarea').val() })
      .done(function () { closeEditor(); table.ajax.reload(null, false); })
      .fail(function (xhr) { var message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Izmjena nije sačuvana. Pokušajte ponovo.'; showError('Spremanje nije uspjelo', message); });
  });
  $(document).on('keydown', function (event) { if (event.key === 'Escape') closeEditor(); });
});
