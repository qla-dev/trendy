$(function () {
  'use strict';
  var config = window.planProizvodnjeConfig || {};
  var csrf = $('meta[name="csrf-token"]').attr('content');
  var tableElement = $('#plan-proizvodnje-tabela');
  var keys = ['progress', 'rn', 'narucitelj', 'prioritet', 'datum', 'narudzba', 'pozicija', 'pocetak', 'kraj', 'proizvod', 'plan_kol', 'izr_kol', 'naziv', 'nositelj_troska', 'napomena'];
  var requestErrorShown = false;
  var priorityFilter = $('#filter-prioritet');
  var rowColourFilter = $('#plan-boja-redova');

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
  function showSuccess(field) {
    var message = 'Polje „' + field + '“ je uspješno uređeno.';
    if (window.Swal && typeof window.Swal.fire === 'function') {
      window.Swal.fire({ icon: 'success', title: 'Uspješno uređeno', text: message, confirmButtonText: 'U redu', confirmButtonClass: 'btn btn-primary', buttonsStyling: false });
      return;
    }
    window.alert(message);
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
  var weekDatesAuto = false;
  var weekInput = $('.f[data-k="kw"]');
  var yearInput = $('.f[data-k="year"]');
  var dateInputs = $('.f[data-k="datum_od"], .f[data-k="datum_do"]');
  function isoWeekDates(year, week) {
    var start = new Date(Date.UTC(year, 0, 4));
    start.setUTCDate(start.getUTCDate() - ((start.getUTCDay() + 6) % 7) + (week - 1) * 7);
    var thursday = new Date(start);
    thursday.setUTCDate(thursday.getUTCDate() + 3);
    if (thursday.getUTCFullYear() !== year) return null;
    var end = new Date(start);
    end.setUTCDate(end.getUTCDate() + 6);
    return [start.toISOString().slice(0, 10), end.toISOString().slice(0, 10)];
  }
  function syncWeekDates() {
    var rawWeek = String(weekInput.val() || '').trim();
    var year = Number(yearInput.val()) || new Date().getFullYear();
    var dates = rawWeek === '' ? ['', ''] : (/^\d{1,2}$/.test(rawWeek) && Number(rawWeek) >= 1 && Number(rawWeek) <= 53 ? isoWeekDates(year, Number(rawWeek)) : null);
    if (!dates) return;
    dateInputs.each(function () {
      var value = dates[$(this).data('k') === 'datum_od' ? 0 : 1];
      if (this._flatpickr) {
        if (value) this._flatpickr.setDate(value, false, 'Y-m-d');
        else this._flatpickr.clear(false);
      } else $(this).val(value);
    });
    weekDatesAuto = rawWeek !== '';
  }
  weekInput.on('input change', syncWeekDates);
  yearInput.on('input change', function () { if (weekInput.val()) syncWeekDates(); });
  dateInputs.on('input change', function () { weekDatesAuto = false; }).each(function () {
    if (this._flatpickr) {
      this._flatpickr.config.onChange.push(function () { weekDatesAuto = false; });
      $(this._flatpickr.altInput).on('input change', function () { weekDatesAuto = false; });
    }
  });
  function filters() { var values = {}; $('.f').each(function () { values[$(this).data('k')] = $(this).val(); }); values.week_dates_auto = weekDatesAuto ? 1 : 0; return values; }
  function editable(key) { return config.canEdit && key !== 'rn' && key !== 'progress' && key !== 'prioritet'; }
  function closeEditor() {
    $('.plan-inline-editor .select2-hidden-accessible').each(function () { $(this).select2('destroy'); });
    $('.plan-inline-editor').remove();
  }
  function rowColour(data) {
    var mode = rowColourFilter.val() || 'all';
    if (data && data.is_previous_week_open_order) return mode === 'none' ? '' : 'red';
    var colour = String((data && data.priority_row_color) || '').toLowerCase();
    if (mode === 'none') return '';
    if (mode === 'basic' && ['green', 'yellow', 'teal', 'grey'].indexOf(colour) === -1) return '';
    return colour;
  }
  function applyRowColour(row, data) {
    var colours = 'red yellow orange purple teal green grey'.split(' ');
    var $row = $(row);
    colours.forEach(function (colour) { $row.removeClass('production-plan-row--' + colour); });
    var colour = rowColour(data);
    if (colour) $row.addClass('production-plan-row--' + colour);
  }

  // DataTables' technical alerts are replaced with friendly Bosnian messages.
  $.fn.dataTable.ext.errMode = 'none';
  var loadingOverlay = $('#production-plan-loading-overlay');
  function setPlanLoading(loading) {
    loadingOverlay.toggleClass('is-visible', loading).attr('aria-hidden', String(!loading));
    tableElement.attr('aria-busy', String(loading));
  }
  // The overlay belongs to the Ajax request, not DataTables' internal processing
  // state. With scrolling enabled, that state can remain true after rows draw.
  // Register before initialization so the first request shows the overlay too.
  tableElement.on('preXhr.dt', function () { setPlanLoading(true); });
  tableElement.on('xhr.dt error.dt draw.dt init.dt', function () { setPlanLoading(false); });
  var table = tableElement.DataTable({
    serverSide: true, processing: true, scrollX: true, pageLength: 25, order: [[7, 'desc']],
    ajax: {
      url: config.dataUrl,
      data: function (data) { data.filter = filters(); data.sort = keys[data.order[0] ? data.order[0].column : 4]; data.dir = data.order[0] ? data.order[0].dir : 'desc'; },
      error: function (xhr) { setPlanLoading(false); showRequestError(xhr); }
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
    }),
    createdRow: function (row, data) { applyRowColour(row, data); }
  });
  tableElement.on('xhr.dt', function () { requestErrorShown = false; });
  var exportModalElement = document.getElementById('production-plan-export-modal');
  function toggleExportModal(show) {
    if (window.bootstrap && window.bootstrap.Modal) {
      var modal = window.bootstrap.Modal.getOrCreateInstance(exportModalElement);
      modal[show ? 'show' : 'hide']();
    } else {
      $(exportModalElement).modal(show ? 'show' : 'hide');
    }
  }
  function updateExportScope() {
    var filtered = $('#production-plan-export-filtered').is(':checked');
    var summary = [];
    $('.f').each(function () {
      var value = $(this).val();
      if (value == null || value === '') return;
      var label = $(this).closest('[class*="col-"]').find('label').first().text().trim() || $(this).data('k');
      if ($(this).is('select')) value = $(this).find('option:selected').text();
      summary.push(escapeHtml(label) + ': ' + escapeHtml(value));
    });
    $('#production-plan-active-filters').toggle(filtered).html(summary.length ? summary.join('<br>') : 'Nema aktivnih filtera.');
  }
  $('#btn-izvoz-plana').on('click', function () {
    updateExportScope();
    toggleExportModal(true);
  });
  $('input[name="production-plan-export-scope"]').on('change', updateExportScope);
  $('#btn-potvrdi-izvoz-plana').on('click', function () {
    if (!config.exportUrl) {
      showError('Izvoz nije dostupan', 'Adresa za izvoz nije podešena.');
      return;
    }
    var order = table.order()[0] || [7, 'desc'];
    var parameters = {
      scope: $('input[name="production-plan-export-scope"]:checked').val() || 'filtered',
      filter: filters(),
      sort: keys[order[0]],
      dir: order[1],
      include_colours: $('#production-plan-export-colours').is(':checked') ? 1 : 0,
      include_filter_summary: $('#production-plan-export-filter-summary').is(':checked') ? 1 : 0
    };
    var link = document.createElement('a');
    link.href = config.exportUrl + (config.exportUrl.indexOf('?') === -1 ? '?' : '&') + $.param(parameters);
    document.body.appendChild(link);
    link.click();
    link.remove();
    toggleExportModal(false);
  });
  tableElement.on('error.dt', function (event, settings, techNote) {
    if (techNote === 4) showError('Prikaz podataka nije uspio', 'Primljeni podaci nisu u očekivanom formatu. Obavijestite administratora ako se problem ponovi.');
    else showRequestError();
  });
  $('#filter').on('click', function () { table.ajax.reload(); });
  rowColourFilter.on('change', function () {
    table.rows({ page: 'current' }).every(function () { applyRowColour(this.node(), this.data()); });
  });
  $('#btn-prikazi-filtere').on('click', function () { $('#tijelo-filtera').toggleClass('d-none'); });
  $('#btn-obrisi-filter').on('click', function () { $('.f').each(function () { if (this._flatpickr) this._flatpickr.clear(); else if ($(this).is('select')) $(this).val('').trigger('change'); else $(this).val(''); }); $('.f[data-k="year"]').val(new Date().getFullYear()); table.ajax.reload(); });
  tableElement.find('tbody').on('click', 'td', function () {
    var cell = table.cell(this), row = table.row($(this).closest('tr')).data(), key = keys[cell.index().column];
    if (!row || !editable(key)) return;
    closeEditor();
    var rect = this.getBoundingClientRect(), multiline = key === 'napomena';
    var editor = $('<div class="plan-inline-editor" data-id="' + escapeHtml(row.id) + '" data-field="' + key + '"><label>Uredi polje</label>' + (multiline ? '<textarea class="form-control"></textarea>' : '<input class="form-control" value="' + escapeHtml(row[key]) + '">') + '<div class="mt-50"><button class="btn btn-primary btn-sm save">Sačuvaj</button><button class="btn btn-outline-secondary btn-sm cancel ms-50">Odustani</button></div></div>').appendTo('body');
    var width = Math.min(300, window.innerWidth - 16);
    editor.css({ position: 'fixed', zIndex: 2000, left: Math.min(rect.left, window.innerWidth - width - 8), top: rect.bottom + 4, width: width });
    editor.find('input,textarea').focus();
    if (key === 'nositelj_troska') {
      var select = $('<select class="form-select"><option value="">Bez nositelja troška</option></select>');
      if (row[key]) select.append(new Option(row[key], row[key], true, true));
      editor.find('input').replaceWith(select);
      var save = editor.find('.save').prop('disabled', true);
      $.getJSON(String(config.costDriverOptionsUrl).replace('__RN__', encodeURIComponent(row.id)))
        .done(function (response) {
          if (!editor.closest('body').length) return;
          (response.data.options || []).forEach(function (option) {
            if (option.code === row[key]) return;
            select.append(new Option(option.label + (option.description ? ' — ' + option.description : ''), option.code));
          });
          select.select2({ dropdownParent: editor, width: '100%', placeholder: 'Pretraži nositelj troška', minimumResultsForSearch: 0 });
          save.prop('disabled', false);
          select.select2('open');
        })
        .fail(function () { showError('Prijedlozi nisu dostupni', 'Učitavanje nositelja troška nije uspjelo. Pokušajte ponovo.'); });
    }
  });
  $('body').on('click', '.plan-inline-editor .cancel', closeEditor).on('click', '.plan-inline-editor .save', function () {
    var editor = $(this).closest('.plan-inline-editor');
    $.post(String(config.fieldUrl).replace('__RN__', editor.data('id')), { _token: csrf, field: editor.data('field'), value: editor.find('input,textarea,select').val() })
      .done(function () {
        var field = editor.data('field');
        var fieldLabel = tableElement.find('thead th').eq(keys.indexOf(field)).text().trim() || field;
        closeEditor();
        table.ajax.reload(null, false);
        showSuccess(fieldLabel);
      })
      .fail(function (xhr) { var message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Izmjena nije sačuvana. Pokušajte ponovo.'; showError('Spremanje nije uspjelo', message); });
  });
  $(document).on('keydown', function (event) { if (event.key === 'Escape') closeEditor(); });
});
