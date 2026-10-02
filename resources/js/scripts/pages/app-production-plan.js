$(function () {
  'use strict';
  var config = window.planProizvodnjeConfig || {};
  var csrf = $('meta[name="csrf-token"]').attr('content');
  var tableElement = $('#plan-proizvodnje-tabela');
  var keys = ['progress', 'rn', 'narucitelj', 'prioritet', 'status_rn', 'datum', 'narudzba', 'broj_narudzbe_kupca', 'pozicija', 'pocetak', 'kraj', 'datum_isporuke', 'proizvod', 'plan_kol', 'izr_kol', 'naziv', 'nositelj_troska', 'napomena'];
  var columnLabels = tableElement.find('thead th').map(function () { return $(this).text().trim(); }).get();
  var columnStorageKey = 'production-plan-visible-columns-v3';
  var savedColumns = null;
  try {
    var storedColumns = JSON.parse(window.localStorage.getItem(columnStorageKey));
    if (!Array.isArray(storedColumns)) {
      storedColumns = JSON.parse(window.localStorage.getItem('production-plan-visible-columns-v2'));
      if (!Array.isArray(storedColumns)) storedColumns = JSON.parse(window.localStorage.getItem('production-plan-visible-columns'));
      if (Array.isArray(storedColumns)) {
        storedColumns = storedColumns.filter(function (key) { return key !== 'izr_kol'; });
        if (storedColumns.indexOf('datum_isporuke') === -1) storedColumns.push('datum_isporuke');
        window.localStorage.setItem(columnStorageKey, JSON.stringify(storedColumns));
      }
    }
    if (Array.isArray(storedColumns) && storedColumns.some(function (key) { return keys.indexOf(key) !== -1; })) savedColumns = storedColumns;
  } catch (error) { /* Browsers may block local storage. The picker still works for this visit. */ }
  var requestErrorShown = false;
  var rowColourFilter = $('#plan-boja-redova');
  var multiSelects = $('.plan-multiselect');
  function selectedValues(picker) {
    return picker.find('.plan-multiselect-option:checked').map(function () { return this.value; }).get();
  }
  function syncMultiSelect(picker) {
    var options = picker.find('.plan-multiselect-option');
    var selected = selectedValues(picker);
    var all = picker.find('.plan-multiselect-all')[0];
    var defaultStatusOptions = picker.find('.plan-multiselect-option[data-default-selected="1"]');
    var isDefaultStatusSelection = picker.data('k') === 'status_rn'
      && selected.length === defaultStatusOptions.length
      && defaultStatusOptions.filter(':checked').length === selected.length;
    all.checked = selected.length === options.length;
    all.indeterminate = selected.length > 0 && selected.length < options.length;
    picker.find('.plan-multiselect-toggle').text(selected.length === options.length ? picker.data('all-label') : isDefaultStatusSelection ? 'Nezaključeni' : selected.length ? selected.length + ' odabrano' : picker.data('none-label'));
  }
  multiSelects.each(function () { syncMultiSelect($(this)); });
  multiSelects.on('click', '.plan-multiselect-toggle', function () {
    var picker = $(this).closest('.plan-multiselect');
    var opening = picker.find('.plan-multiselect-menu').hasClass('d-none');
    multiSelects.not(picker).find('.plan-multiselect-menu').addClass('d-none');
    multiSelects.not(picker).find('.plan-multiselect-toggle').attr('aria-expanded', 'false');
    picker.find('.plan-multiselect-menu').toggleClass('d-none', !opening);
    $(this).attr('aria-expanded', String(opening));
    if (opening) picker.find('.plan-multiselect-search').trigger('focus');
  });
  multiSelects.on('change', '.plan-multiselect-all', function () {
    var picker = $(this).closest('.plan-multiselect');
    picker.find('.plan-multiselect-option').prop('checked', this.checked);
    syncMultiSelect(picker);
  }).on('change', '.plan-multiselect-option', function () {
    syncMultiSelect($(this).closest('.plan-multiselect'));
  }).on('input', '.plan-multiselect-search', function () {
    var picker = $(this).closest('.plan-multiselect');
    var term = $(this).val().trim().toLocaleLowerCase();
    var visible = 0;
    picker.find('.plan-multiselect-options .form-check').each(function () {
      var match = $(this).find('label').text().toLocaleLowerCase().indexOf(term) !== -1;
      $(this).toggleClass('d-none', !match);
      if (match) visible++;
    });
    picker.find('.plan-multiselect-empty').toggleClass('d-none', visible !== 0);
  });
  $(document).on('click', function (event) {
    multiSelects.each(function () {
      if (this.contains(event.target)) return;
      $(this).find('.plan-multiselect-menu').addClass('d-none');
      $(this).find('.plan-multiselect-toggle').attr('aria-expanded', 'false');
    });
  }).on('keydown', function (event) {
    if (event.key === 'Escape') multiSelects.find('.plan-multiselect-menu').addClass('d-none').end().find('.plan-multiselect-toggle').attr('aria-expanded', 'false');
  });

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
  function formatQuantity(value) { var number = Number(value); return Number.isFinite(number) ? number.toLocaleString('bs-BA', { minimumFractionDigits: 0, maximumFractionDigits: 4 }) : ''; }
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
  function filters() {
    var values = {};
    $('.f').each(function () { values[$(this).data('k')] = $(this).val(); });
    multiSelects.each(function () {
      var picker = $(this), selected = selectedValues(picker), total = picker.find('.plan-multiselect-option').length;
      values[picker.data('k')] = selected.length === total ? '' : selected.length ? selected : '__none__';
    });
    values.week_dates_auto = weekDatesAuto ? 1 : 0;
    return values;
  }
  function editable(key) { return config.canEdit && ['rn', 'progress', 'prioritet', 'status_rn', 'broj_narudzbe_kupca', 'datum_isporuke'].indexOf(key) === -1; }
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
  function sizeEmptyMessage() {
    var viewport = tableElement.closest('.dataTables_wrapper').children('.row').eq(1)[0];
    if (viewport) viewport.style.setProperty('--production-plan-visible-width', viewport.clientWidth + 'px');
  }
  // The overlay belongs to the Ajax request, not DataTables' internal processing
  // state. With scrolling enabled, that state can remain true after rows draw.
  // Register before initialization so the first request shows the overlay too.
  tableElement.on('preXhr.dt', function () { setPlanLoading(true); });
  tableElement.on('xhr.dt error.dt draw.dt init.dt', function () { setPlanLoading(false); });
  tableElement.on('draw.dt', sizeEmptyMessage);
  $(window).on('resize', sizeEmptyMessage);
  var table = tableElement.DataTable({
    serverSide: true, processing: true, scrollX: false, pageLength: 25, order: [[9, 'desc']],
    ajax: {
      url: config.dataUrl,
      data: function (data) { data.filter = filters(); data.sort = keys[data.order[0] ? data.order[0].column : 4]; data.dir = data.order[0] ? data.order[0].dir : 'desc'; },
      error: function (xhr) { setPlanLoading(false); showRequestError(xhr); }
    },
    language: { processing: 'Učitavanje...', search: 'Pretraga:', lengthMenu: 'Prikaži _MENU_ redova', info: 'Prikaz _START_ do _END_ od _TOTAL_ radnih naloga', infoEmpty: 'Nema podataka', emptyTable: '<span class="production-plan-empty-message">Nema radnih naloga</span>', zeroRecords: '<span class="production-plan-empty-message">Nema pronađenih radnih naloga</span>', paginate: { next: 'Sljedeća', previous: 'Prethodna' } },
    columns: keys.map(function (key) {
      return { data: key, defaultContent: '', visible: savedColumns ? savedColumns.indexOf(key) !== -1 : ['izr_kol', 'status_rn'].indexOf(key) === -1, orderable: key !== 'progress', render: function (value) {
        var output;
        if (key === 'progress') output = '<b>' + escapeHtml(value) + '%</b>';
        else if (key === 'plan_kol' || key === 'izr_kol') output = formatQuantity(value);
        else if (['datum', 'pocetak', 'kraj', 'datum_isporuke'].indexOf(key) >= 0) output = formatDate(value);
        else if (key === 'napomena') output = '<span class="production-plan-note-preview" title="' + escapeHtml(value).replace(/"/g, '&quot;') + '">' + escapeHtml(value) + '</span>';
        else output = escapeHtml(value);
        return editable(key) ? '<span class="editable-cell">' + output + '</span>' : output;
      }};
    }),
    createdRow: function (row, data) { applyRowColour(row, data); }
  });
  tableElement.on('xhr.dt', function () { requestErrorShown = false; });
  var columnOptions = $('#production-plan-column-options');
  keys.forEach(function (key, index) {
    var option = $('<div class="form-check"><input class="form-check-input production-plan-column-toggle" type="checkbox"><label class="form-check-label"></label></div>');
    var input = option.find('input').attr({ id: 'production-plan-column-' + index, 'data-column-index': index }).prop('checked', table.column(index).visible());
    option.find('label').attr('for', input.attr('id')).text(columnLabels[index]);
    columnOptions.append(option);
  });
  function saveColumnSelection() {
    var selected = columnOptions.find('input:checked').map(function () { return keys[Number(this.dataset.columnIndex)]; }).get();
    try { window.localStorage.setItem(columnStorageKey, JSON.stringify(selected)); } catch (error) { /* Storage is optional. */ }
  }
  function columnCells(index) {
    return [table.column(index).header()].concat(table.column(index).nodes().toArray());
  }
  function columnPositions() {
    var positions = new Map();
    keys.forEach(function (key, index) {
      if (!table.column(index).visible()) return;
      columnCells(index).forEach(function (cell) {
        if (cell && cell.isConnected) positions.set(cell, cell.getBoundingClientRect().left);
      });
    });
    return positions;
  }
  function changeVisibleColumns() {
    var changes = keys.map(function (key, index) {
      return { index: index, visible: columnOptions.find('[data-column-index="' + index + '"]').prop('checked') };
    }).filter(function (change) { return table.column(change.index).visible() !== change.visible; });
    if (!changes.length) return Promise.resolve();
    var canAnimate = Element.prototype.animate && (!window.matchMedia || !window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    var exits = [];
    if (canAnimate) changes.filter(function (change) { return !change.visible; }).forEach(function (change) {
      columnCells(change.index).forEach(function (cell) {
        if (cell && cell.isConnected) exits.push(cell.animate([{ opacity: 1 }, { opacity: 0 }], { duration: 110, easing: 'ease-in' }).finished.catch(function () {}));
      });
    });
    return Promise.all(exits).then(function () {
      var previousPositions = canAnimate ? columnPositions() : null;
      changes.forEach(function (change) { table.column(change.index).visible(change.visible, false); });
      table.columns.adjust();
      if (!canAnimate) return;
      var animations = [];
      keys.forEach(function (key, index) {
        if (!table.column(index).visible()) return;
        columnCells(index).forEach(function (cell) {
          if (!cell || !cell.isConnected) return;
          if (previousPositions.has(cell)) {
            var shift = previousPositions.get(cell) - cell.getBoundingClientRect().left;
            if (Math.abs(shift) > 1) animations.push(cell.animate([{ transform: 'translateX(' + shift + 'px)' }, { transform: 'translateX(0)' }], { duration: 230, easing: 'ease-out' }).finished.catch(function () {}));
          } else {
            animations.push(cell.animate([{ opacity: 0, transform: 'scaleX(.75)' }, { opacity: 1, transform: 'scaleX(1)' }], { duration: 230, easing: 'ease-out' }).finished.catch(function () {}));
          }
        });
      });
      return Promise.all(animations);
    });
  }
  var columnChangeQueue = Promise.resolve();
  function scheduleColumnChange() {
    saveColumnSelection();
    columnChangeQueue = columnChangeQueue.then(changeVisibleColumns).catch(function () {
      keys.forEach(function (key, index) {
        table.column(index).visible(columnOptions.find('[data-column-index="' + index + '"]').prop('checked'), false);
      });
      table.columns.adjust();
    });
  }
  columnOptions.on('change', 'input', function () {
    if (!columnOptions.find('input:checked').length) {
      $(this).prop('checked', true);
      return;
    }
    scheduleColumnChange();
  });
  $('#btn-sve-kolone-plana').on('click', function () {
    columnOptions.find('input').prop('checked', true);
    scheduleColumnChange();
  });
  $('#btn-kolone-plana, #btn-prikazi-filtere').on('click', function () {
    var columnsClicked = this.id === 'btn-kolone-plana';
    var panel = $(columnsClicked ? '#tijelo-kolona-plana' : '#tijelo-filtera');
    var otherPanel = $(columnsClicked ? '#tijelo-filtera' : '#tijelo-kolona-plana');
    var otherButton = $(columnsClicked ? '#btn-prikazi-filtere' : '#btn-kolone-plana');
    var opening = panel.hasClass('d-none');
    otherPanel.addClass('d-none');
    otherButton.attr('aria-expanded', 'false');
    panel.toggleClass('d-none', !opening);
    $(this).attr('aria-expanded', String(opening));
  });
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
      var customerRow = $(this).closest('.plan-customer-dates-row');
      if (customerRow.length) {
        var customer = customerRow.find('span.fw-bold').first().text().trim();
        var dateLabel = $(this).closest('.shared-filter-field').find('label').first().text().trim();
        summary.push(escapeHtml(customer + ' ' + dateLabel) + ': ' + escapeHtml(formatDate(value) || value));
        return;
      }
      var label = $(this).closest('[class*="col-"]').find('label').first().text().trim() || $(this).data('k');
      if ($(this).is('select')) value = $(this).find('option:selected').text();
      summary.push(escapeHtml(label) + ': ' + escapeHtml(value));
    });
    multiSelects.each(function () {
      var picker = $(this), selected = picker.find('.plan-multiselect-option:checked');
      if (selected.length === picker.find('.plan-multiselect-option').length) return;
      var label = picker.data('label') || picker.closest('[class*="col-"]').find('label').first().text().trim() || picker.data('k');
      var names = selected.map(function () { return $(this).next('label').text(); }).get();
      summary.push(escapeHtml(label) + ': ' + escapeHtml(names.length ? names.join(', ') : 'Nijedan'));
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
    var order = table.order()[0] || [9, 'desc'];
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
  var planList = $('#production-plan-list');
  var fullscreenButton = $('#btn-fullscreen-plana');
  var fullscreenPlaceholder = null;
  var ownsBrowserFullscreen = false;
  function resizePlanList() {
    window.requestAnimationFrame(function () { table.columns.adjust(); sizeEmptyMessage(); });
  }
  function restorePlanList() {
    if (!fullscreenPlaceholder) return;
    closeEditor();
    planList.removeClass('is-fullscreen').insertBefore(fullscreenPlaceholder);
    fullscreenPlaceholder.remove();
    fullscreenPlaceholder = null;
    $('body').removeClass('production-plan-fullscreen');
    fullscreenButton.attr('aria-expanded', 'false').trigger('focus');
    resizePlanList();
  }
  function exitPlanFullscreen() {
    restorePlanList();
    if (ownsBrowserFullscreen && document.fullscreenElement === document.documentElement) {
      document.exitFullscreen().catch(function () { /* The list is already restored. */ });
    }
    ownsBrowserFullscreen = false;
  }
  fullscreenButton.on('click', function () {
    if (fullscreenPlaceholder) return;
    closeEditor();
    fullscreenPlaceholder = $('<div hidden></div>').insertBefore(planList);
    planList.appendTo('body').addClass('is-fullscreen');
    $('body').addClass('production-plan-fullscreen');
    fullscreenButton.attr('aria-expanded', 'true');
    planList.trigger('focus');
    resizePlanList();
    // Fullscreen the document so inline editors and dialogs remain available.
    if (!document.fullscreenElement && document.documentElement.requestFullscreen) {
      ownsBrowserFullscreen = true;
      try {
        document.documentElement.requestFullscreen().catch(function () { ownsBrowserFullscreen = false; });
      } catch (error) { ownsBrowserFullscreen = false; }
    }
  });
  $(document).on('fullscreenchange', function () {
    if (ownsBrowserFullscreen && !document.fullscreenElement) {
      ownsBrowserFullscreen = false;
      restorePlanList();
    }
    resizePlanList();
  }).on('keydown.productionPlanFullscreen', function (event) {
    if (event.key === 'Escape' && fullscreenPlaceholder) exitPlanFullscreen();
  });
  $('#filter').on('click', function () { table.ajax.reload(); });
  rowColourFilter.on('change', function () {
    table.rows({ page: 'current' }).every(function () { applyRowColour(this.node(), this.data()); });
  });
  $('#btn-obrisi-filter').on('click', function () { $('.f').each(function () { if (this._flatpickr) this._flatpickr.clear(); else if ($(this).is('select')) $(this).val('').trigger('change'); else $(this).val(''); }); multiSelects.each(function () { var picker = $(this); picker.find('.plan-multiselect-option').each(function () { $(this).prop('checked', picker.data('k') === 'status_rn' ? $(this).data('default-selected') === 1 : true); }); picker.find('.plan-multiselect-search').val('').trigger('input'); syncMultiSelect(picker); }); weekDatesAuto = false; $('.f[data-k="year"]').val(new Date().getFullYear()); table.ajax.reload(); });
  tableElement.find('tbody').on('click', 'td', function () {
    var cell = table.cell(this), row = table.row($(this).closest('tr')).data(), key = keys[cell.index().column];
    if (!row || !editable(key)) return;
    closeEditor();
    var rect = this.getBoundingClientRect(), multiline = key === 'napomena';
    var editor = $('<div class="plan-inline-editor" data-id="' + escapeHtml(row.id) + '" data-field="' + key + '"><label>Uredi polje</label>' + (multiline ? '<textarea class="form-control"></textarea>' : '<input class="form-control">') + '<div class="mt-50"><button class="btn btn-primary btn-sm save">Sačuvaj</button><button class="btn btn-outline-secondary btn-sm cancel ms-50">Odustani</button></div></div>').appendTo('body');
    editor.find('input,textarea').val(row[key] == null ? '' : row[key]);
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
