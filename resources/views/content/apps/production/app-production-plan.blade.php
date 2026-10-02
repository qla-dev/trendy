@extends('layouts/contentLayoutMaster')
@section('title', 'Plan proizvodnje')
@section('vendor-style')
  <link rel="stylesheet" href="{{ asset(mix('vendors/css/forms/select/select2.min.css')) }}">
  <link rel="stylesheet" href="{{ asset('vendors/css/tables/datatable/dataTables.bootstrap5.min.css') }}">
  <link rel="stylesheet" href="{{ asset('vendors/css/pickers/flatpickr/flatpickr.min.css') }}">
  <link rel="stylesheet" href="{{ asset('vendors/css/extensions/sweetalert2.min.css') }}">
@endsection
@section('page-style')
  <style>
    .production-plan-table .plan-expand-cell { width: 28px; padding: .25rem; }
    .plan-expand-button { display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border: 0; border-radius: .35rem; background: transparent; color: inherit; }
    .plan-expand-button:hover { background: rgba(94, 88, 115, .12); }
    .plan-expand-button:focus-visible { outline: 2px solid currentColor; outline-offset: 2px; }
    .plan-expand-button i { transition: transform .15s ease; font-size: .7rem; }
    .plan-expand-button[aria-expanded='true'] i { transform: rotate(90deg); }
    .production-plan-table .plan-detail-row > td { padding: 0; background: #f8f8f8; border-bottom: 1px solid #d8d6de; }
    .plan-expanded-details { box-sizing: border-box; width: min(100%, var(--production-plan-visible-width, 80vw)); padding: .75rem 1rem; white-space: normal; }
    .plan-expanded-details h6 { font-size: .75rem; margin-bottom: .5rem; }
    .plan-detail-scroll { max-width: 100%; overflow-x: auto; }
    .plan-operations-flow { display: flex; flex-wrap: nowrap; list-style: none; padding: 0; margin: 0; width: max-content; }
    .plan-operations-flow li { display: flex; align-items: center; flex: 0 0 auto; }
    .plan-operations-flow li:not(:last-child)::after { content: ''; width: 2rem; height: 1px; margin: 0 .75rem; background: #d8d6de; }
    .plan-operation-circle { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 auto; width: 26px; height: 26px; border: 2px solid currentColor; border-radius: 50%; margin-right: .5rem; }
    .plan-operations-flow strong { font-size: .8rem; }
    .plan-operations-flow small { display: block; font-size: .7rem; }
    .dark-layout .production-plan-table .plan-detail-row > td, .semi-dark-layout .production-plan-table .plan-detail-row > td { background: #283046; border-color: #3b4253; }
    @media (prefers-reduced-motion: reduce) { .plan-expand-button i { transition: none; } }
    .production-plan-table { width: max-content !important; min-width: 100%; }
    .production-plan-table > :not(caption) > * > * { padding: .42rem .5rem; font-size: .8rem; white-space: nowrap; }
    .production-plan-table .editable-cell { cursor: pointer; }
    .production-plan-table .production-plan-note-preview { display: inline-block; max-width: 25rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; vertical-align: bottom; }
    .production-plan-table tr.production-plan-row--red > td { background-color: #ffd6dc !important; color: #7a0014; }
    .production-plan-table tr.production-plan-row--yellow > td { background-color: #fff0a3 !important; color: #5f4500; }
    .production-plan-table tr.production-plan-row--orange > td { background-color: #ffd1aa !important; color: #792b00; }
    .production-plan-table tr.production-plan-row--purple > td { background-color: #e8d4ff !important; color: #4f167f; }
    .production-plan-table tr.production-plan-row--teal > td { background-color: #bcefe5 !important; color: #005c4c; }
    .production-plan-table tr.production-plan-row--green > td { background-color: #c5f1d2 !important; color: #075e2a; }
    .production-plan-table tr.production-plan-row--grey > td { background-color: #dde2e8 !important; color: #39424e; }
    .production-plan-table tr[class*='production-plan-row--'] > td:first-child { box-shadow: inset 4px 0 0 currentColor; }
    .production-plan-table-overlay-host { position: relative; isolation: isolate; }
    .production-plan-table-loading-overlay { position: absolute; inset: 0; display: none; align-items: center; justify-content: center; min-height: 220px; background: rgba(255, 255, 255, .74); backdrop-filter: blur(1px); z-index: 30; pointer-events: none; }
    .production-plan-table-loading-overlay.is-visible { display: flex; }
    .production-plan-wrapper .dataTables_processing { display: none !important; }
    .production-plan-table td.dataTables_empty { padding: 0 !important; text-align: left !important; }
    .production-plan-empty-message { position: sticky; left: 0; display: block; box-sizing: border-box; width: var(--production-plan-visible-width, 100%); padding: .42rem .5rem; text-align: center; }
    .production-plan-table-loading-overlay-content { display: inline-flex; flex-direction: column; align-items: center; gap: .65rem; text-align: center; }
    .production-plan-table-loading-spinner { width: 2rem; height: 2rem; border-width: .2em; color: #495b73; }
    .production-plan-table-loading-message { font-size: .95rem; font-weight: 600; color: #5e5873; letter-spacing: .01em; }
    #btn-izvoz-plana { color: #5e5873; border-color: currentColor; cursor: pointer; }
    #btn-izvoz-plana:hover, #btn-izvoz-plana:focus-visible { background-color: rgba(94, 88, 115, .12); }
    .dark-layout #btn-izvoz-plana { color: #d0d2d6; }
    #production-plan-export-modal button,
    #production-plan-export-modal .form-check-input:not(:disabled),
    #production-plan-export-modal .form-check-input:not(:disabled) + .form-check-label { cursor: pointer; }
    #tijelo-kolona-plana button,
    #tijelo-kolona-plana .form-check-input:not(:disabled),
    #tijelo-kolona-plana .form-check-input:not(:disabled) + .form-check-label { cursor: pointer; }
    .production-plan-column-options { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: .75rem 1rem; }
    .production-plan-column-options .form-check { margin: 0; }
    .dark-layout .production-plan-table-loading-overlay, .semi-dark-layout .production-plan-table-loading-overlay { background: rgba(20, 28, 48, .68); }
    .dark-layout .production-plan-table-loading-spinner, .semi-dark-layout .production-plan-table-loading-spinner { color: #d6dcec; }
    .dark-layout .production-plan-table-loading-message, .semi-dark-layout .production-plan-table-loading-message { color: #f4f5fb; }
    .production-plan-wrapper .card-datatable.table-responsive { overflow: visible; }
    .production-plan-wrapper .card-datatable .dataTables_wrapper > .row:first-child,
    .production-plan-wrapper .card-datatable .dataTables_wrapper > .row:last-child { margin-right: 0; margin-left: 0; padding: 1rem; }
    .production-plan-wrapper .card-datatable .dataTables_wrapper > .row:first-child > [class*='col-'],
    .production-plan-wrapper .card-datatable .dataTables_wrapper > .row:last-child > [class*='col-'] { padding-right: 0; padding-left: 0; }
    .production-plan-wrapper .card-datatable .dataTables_wrapper > .row:nth-child(2) { margin-right: 0; margin-left: 0; overflow-x: auto; }
    .production-plan-wrapper .card-datatable .dataTables_wrapper > .row:nth-child(2) > [class*='col-'] { min-width: max-content; padding-right: 0; padding-left: 0; }
    @media (min-width: 768px) {
      .production-plan-wrapper .card-datatable .dataTables_wrapper > .row:first-child { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
      .production-plan-wrapper .card-datatable .dataTables_wrapper > .row:first-child > [class*='col-']:first-child { flex: 1 1 auto; width: auto; max-width: none; }
      .production-plan-wrapper .card-datatable .dataTables_wrapper > .row:first-child > [class*='col-']:last-child { flex: 0 0 auto; width: auto; max-width: none; margin-left: auto; }
    }
    body.production-plan-fullscreen { overflow: hidden; }
    .production-plan-fullscreen-toolbar { display: none; }
    #production-plan-list.is-fullscreen { position: fixed; inset: 0; z-index: 1040; margin: 0; border-radius: 0; overflow: auto; background: #fff; }
    .dark-layout #production-plan-list.is-fullscreen { background: #283046; }
    #production-plan-list.is-fullscreen .dataTables_wrapper > .row:first-child,
    #production-plan-list.is-fullscreen .dataTables_wrapper > .row:last-child { display: none; }
    .plan-inline-editor { z-index: 2000; min-width: 240px; max-width: calc(100vw - 16px); padding: .65rem; background: #fff; border: 1px solid #d8d6de; border-radius: .35rem; box-shadow: 0 5px 18px rgba(34,41,47,.16); }
    .plan-inline-editor label { font-size: .75rem; margin-bottom: .35rem; }
    .plan-inline-editor textarea { min-height: 80px; }
    .plan-multiselect { position: relative; }
    .plan-multiselect-menu { position: absolute; z-index: 1050; top: 100%; left: 0; width: 100%; min-width: 230px; padding: .65rem; background: #fff; border: 1px solid #d8d6de; border-radius: .35rem; box-shadow: 0 5px 18px rgba(34,41,47,.16); }
    .plan-multiselect-options { max-height: 220px; overflow-y: auto; }
    .plan-multiselect .form-check { padding-top: .25rem; padding-bottom: .25rem; }
    .plan-multiselect .form-check-input, .plan-multiselect .form-check-label { cursor: pointer; }
    .dark-layout .plan-multiselect-menu { background: #283046; border-color: #3b4253; }
    .plan-customer-dates { max-width: 100%; }
  </style>
@endsection
@section('content')
<section id="rn-plan">
  <div class="content-header row"><div class="col-12 mb-2 d-flex justify-content-between align-items-center flex-wrap gap-1"><h2 class="mb-0">Plan proizvodnje — Radni nalozi</h2><div class="d-flex flex-wrap gap-1"><button type="button" class="btn btn-outline-secondary" id="btn-fullscreen-plana" aria-controls="production-plan-list" aria-expanded="false"><i data-feather="maximize" class="me-50"></i>Prikaz preko cijelog ekrana</button><button type="button" class="btn" id="btn-izvoz-plana"><i data-feather="download" class="me-50"></i>Izvoz u Excel</button></div></div></div>
  <div class="card mb-2"><div class="card-header d-flex justify-content-between align-items-center"><h4 class="mb-0">Filter plana proizvodnje</h4><div class="d-flex align-items-center flex-wrap gap-2"><button type="button" class="btn btn-outline-primary btn-sm" id="btn-kolone-plana" aria-controls="tijelo-kolona-plana" aria-expanded="false"><i data-feather="columns" class="me-50"></i>Filter kolona</button><button type="button" class="btn btn-outline-primary btn-sm" id="btn-prikazi-filtere" aria-controls="tijelo-filtera" aria-expanded="false"><i data-feather="filter" class="me-50"></i>Prikaži filtere</button><button class="btn btn-outline-danger btn-sm" id="btn-obrisi-filter"><i data-feather="trash-2" class="me-50"></i>Obriši filter</button></div></div>
    <div class="card-body d-none" id="tijelo-filtera"><div class="row g-2">
      <div class="col-md-3"><label class="form-label" for="filter-prioritet">Prioritet</label><div class="plan-multiselect" data-k="prioritet" data-all-label="Svi prioriteti" data-none-label="Nijedan prioritet"><button type="button" class="form-select text-start plan-multiselect-toggle" id="filter-prioritet" aria-expanded="false" aria-controls="filter-prioritet-options">Svi prioriteti</button><div class="plan-multiselect-menu d-none" id="filter-prioritet-options"><div class="form-check border-bottom mb-50"><input class="form-check-input plan-multiselect-all" type="checkbox" id="filter-prioritet-all" checked><label class="form-check-label" for="filter-prioritet-all">Odaberi sve</label></div><div class="plan-multiselect-options">@foreach (($planConfig['priorityOptions'] ?? []) as $priorityOption)<div class="form-check"><input class="form-check-input plan-multiselect-option" type="checkbox" id="filter-prioritet-{{ $loop->index }}" value="{{ $priorityOption['code'] }}" checked><label class="form-check-label" for="filter-prioritet-{{ $loop->index }}">{{ $priorityOption['label'] }}</label></div>@endforeach</div></div></div></div>
      <div class="col-md-3"><label class="form-label" for="plan-boja-redova">Boja redova</label><select class="form-select" id="plan-boja-redova"><option value="none">Bez boje</option><option value="basic">Osnovne</option><option value="all" selected>Sve</option></select></div>
      <div class="col-md-3"><x-filters.text label="RN" class="f" data-k="rn"/></div><div class="col-md-3"><x-filters.text label="Proizvod" class="f" data-k="proizvod"/></div>
    </div><div class="row g-2 mt-0">
      <div class="col-md-3"><label class="form-label" for="filter-status-rn">Status RN</label><div class="plan-multiselect" data-k="status_rn" data-label="Status RN" data-all-label="Svi statusi" data-none-label="Nijedan status"><button type="button" class="form-select text-start plan-multiselect-toggle" id="filter-status-rn" aria-expanded="false" aria-controls="filter-status-rn-options">Nezaključeni</button><div class="plan-multiselect-menu d-none" id="filter-status-rn-options"><div class="form-check border-bottom mb-50"><input class="form-check-input plan-multiselect-all" type="checkbox" id="filter-status-rn-all"><label class="form-check-label" for="filter-status-rn-all">Odaberi sve</label></div><div class="plan-multiselect-options">@foreach (($planConfig['statusOptions'] ?? []) as $statusOption)@php($isDefaultStatus = !in_array(strtoupper((string) $statusOption['code']), ['F', 'I', 'Z'], true))<div class="form-check"><input class="form-check-input plan-multiselect-option" type="checkbox" id="filter-status-rn-{{ $loop->index }}" value="{{ $statusOption['code'] }}" data-default-selected="{{ $isDefaultStatus ? '1' : '0' }}" {{ $isDefaultStatus ? 'checked' : '' }}><label class="form-check-label" for="filter-status-rn-{{ $loop->index }}">{{ $statusOption['label'] }}</label></div>@endforeach</div></div></div></div><div class="col-md-3"><x-filters.text label="Narudžba" class="f" data-k="narudzba"/></div><div class="col-md-3"><label class="form-label">Godina</label><input class="form-control f" data-k="year" value="{{ now()->year }}"></div><div class="col-md-3"><label class="form-label">Kalendarska sedmica</label><input class="form-control f" data-k="kw" placeholder="1–53"></div>
    </div><div class="row g-2 mt-0">
      <div class="col-md-3"><x-filters.date label="Početni termin od" class="f" data-k="datum_od"/></div><div class="col-md-3"><x-filters.date label="Početni termin do" class="f" data-k="datum_do"/></div><div class="col-md-3"><x-filters.date label="Datum isporuke od" class="f" data-k="isporuka_od"/></div><div class="col-md-3"><x-filters.date label="Datum isporuke do" class="f" data-k="isporuka_do"/></div>
    </div><div class="row g-2 mt-0" role="group" aria-label="TRENDY GERMANY filteri">
        <div class="col-md-3"><label class="form-label" for="filter-trendy_germany-customers">Trendy naručitelji</label><div class="plan-multiselect" data-k="trendy_germany_customers" data-label="Trendy Germany" data-all-label="Svi: Trendy Germany" data-none-label="Nijedan naručitelj"><button type="button" class="form-select text-start plan-multiselect-toggle" id="filter-trendy_germany-customers" aria-expanded="false" aria-controls="filter-trendy_germany-customer-options">Svi: Trendy Germany</button><div class="plan-multiselect-menu d-none" id="filter-trendy_germany-customer-options"><div class="form-check border-bottom mb-50"><input class="form-check-input plan-multiselect-all" type="checkbox" id="filter-trendy_germany-customers-all" checked><label class="form-check-label" for="filter-trendy_germany-customers-all">Odaberi sve</label></div><div class="plan-multiselect-options">@foreach ([['code' => 'germany', 'label' => 'TRENDY GERMANY'], ['code' => 'germany_gmbh', 'label' => 'TRENDY GERMANY GMBH']] as $customerOption)<div class="form-check"><input class="form-check-input plan-multiselect-option" type="checkbox" id="filter-trendy_germany-customer-{{ $loop->index }}" value="{{ $customerOption['code'] }}" checked><label class="form-check-label" for="filter-trendy_germany-customer-{{ $loop->index }}">{{ $customerOption['label'] }}</label></div>@endforeach</div></div></div></div>
        <div class="col-md-3"><x-filters.date label="Datum isporuke od" class="f" data-k="trendy_germany_date_from" aria-label="Trendy naručitelji datum isporuke od"/></div>
        <div class="col-md-3"><x-filters.date label="Datum isporuke do" class="f" data-k="trendy_germany_date_to" aria-label="Trendy naručitelji datum isporuke do"/></div>
        <div class="col-md-3"><label class="form-label" for="filter-trendy-germany-number">Broj</label><div class="plan-multiselect" data-k="trendy_germany_numbers" data-label="Trendy Germany broj" data-all-label="Svi brojevi" data-none-label="Nijedan broj"><button type="button" class="form-select text-start plan-multiselect-toggle" id="filter-trendy-germany-number" aria-label="Odaberite Trendy Germany brojeve" aria-expanded="false" aria-controls="filter-trendy-germany-number-options">Svi brojevi</button><div class="plan-multiselect-menu d-none" id="filter-trendy-germany-number-options"><div class="form-check border-bottom mb-50"><input class="form-check-input plan-multiselect-all" type="checkbox" id="filter-trendy-germany-number-all" checked><label class="form-check-label" for="filter-trendy-germany-number-all">Odaberi sve</label></div><div class="plan-multiselect-options">@foreach (($planConfig['trendyGermanyNumberOptions'] ?? []) as $numberOption)<div class="form-check"><input class="form-check-input plan-multiselect-option" type="checkbox" id="filter-trendy-germany-number-{{ $loop->index }}" value="{{ $numberOption['code'] }}" checked><label class="form-check-label" for="filter-trendy-germany-number-{{ $loop->index }}">{{ $numberOption['label'] }}</label></div>@endforeach</div></div></div></div>
      </div><div class="row g-2 mt-0" role="group" aria-label="GROB-WERKE filteri">
        <div class="col-md-3"><label class="form-label" for="filter-grob-customers">Ostali naručitelji</label><div class="plan-multiselect" data-k="grob_customers" data-label="Ostali naručitelji" data-all-label="Svi: Ostali naručitelji" data-none-label="Nijedan naručitelj"><button type="button" class="form-select text-start plan-multiselect-toggle" id="filter-grob-customers" aria-expanded="false" aria-controls="filter-grob-customer-options">Svi: Ostali naručitelji</button><div class="plan-multiselect-menu d-none" id="filter-grob-customer-options"><div class="form-check border-bottom mb-50"><input class="form-check-input plan-multiselect-all" type="checkbox" id="filter-grob-customers-all" checked><label class="form-check-label" for="filter-grob-customers-all">Odaberi sve</label></div><div class="plan-multiselect-options">@foreach (($planConfig['otherCustomerOptions'] ?? []) as $customerOption)<div class="form-check"><input class="form-check-input plan-multiselect-option" type="checkbox" id="filter-grob-customer-{{ $loop->index }}" value="{{ $customerOption['code'] }}" checked><label class="form-check-label" for="filter-grob-customer-{{ $loop->index }}">{{ $customerOption['label'] }}</label></div>@endforeach</div></div></div></div>
        <div class="col-md-3"><x-filters.date label="Datum isporuke od" class="f" data-k="grob_date_from" aria-label="Ostali naručitelji datum isporuke od"/></div>
        <div class="col-md-3"><x-filters.date label="Datum isporuke do" class="f" data-k="grob_date_to" aria-label="Ostali naručitelji datum isporuke do"/></div>
        <div class="col-md-3 d-flex align-items-end"><button id="filter" class="btn btn-primary w-100"><i data-feather="filter" class="me-50"></i>Primijeni filtere</button></div>
      </div></div>
    <div class="card-body border-top d-none" id="tijelo-kolona-plana">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-1 mb-1"><h5 class="mb-0">Prikazane kolone</h5><button type="button" class="btn btn-outline-secondary btn-sm" id="btn-sve-kolone-plana">Prikaži sve</button></div>
      <div id="production-plan-column-options" class="production-plan-column-options"></div>
    </div>
  </div>
  <div class="card production-plan-wrapper production-plan-table-overlay-host" id="production-plan-list" tabindex="-1">
    <div class="production-plan-fullscreen-toolbar"><button type="button" class="btn btn-outline-secondary btn-sm" id="btn-exit-fullscreen-plana"><i data-feather="minimize" class="me-50"></i>Vrati prikaz</button></div>
    <div id="production-plan-loading-overlay" class="production-plan-table-loading-overlay is-visible" role="status" aria-live="polite" aria-hidden="false">
      <div class="production-plan-table-loading-overlay-content"><span class="spinner-border production-plan-table-loading-spinner" aria-hidden="true"></span><span class="production-plan-table-loading-message">Učitavanje plana proizvodnje...</span></div>
    </div>
    <div class="card-datatable table-responsive"><table class="table production-plan-table" id="plan-proizvodnje-tabela" aria-busy="true"><thead><tr><th aria-label="Detalji"></th><th>%</th><th>RN</th><th>Naručitelj</th><th>Prioritet</th><th>Status RN</th><th>Datum</th><th>Narudžba</th><th>Br. narudžbe kupca</th><th>Br. poz.</th><th>Poč. termin</th><th>Kraj termin</th><th>Datum isporuke</th><th>Proizvod</th><th>Plan. kol.</th><th>Izr. kol.</th><th>Naziv</th><th>Nositelj troška</th><th>Napomena</th></tr></thead></table></div>
  </div>
</section>

<div class="modal fade" id="production-plan-export-modal" tabindex="-1" aria-labelledby="production-plan-export-modal-title" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-transparent">
        <h5 class="modal-title" id="production-plan-export-modal-title"><i data-feather="download" class="me-50"></i>Izvoz plana proizvodnje</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zatvori"></button>
      </div>
      <div class="modal-body pt-0">
        <div class="mb-2"><label class="form-label d-block mb-50">Obuhvat izvoza</label>
          <div class="form-check mb-75"><input class="form-check-input" type="radio" name="production-plan-export-scope" id="production-plan-export-filtered" value="filtered" checked><label class="form-check-label" for="production-plan-export-filtered">Izvezi s trenutnim filterima</label></div>
          <div id="production-plan-active-filters" class="border rounded p-75 bg-light mb-1 small"></div>
          <div class="form-check"><input class="form-check-input" type="radio" name="production-plan-export-scope" id="production-plan-export-all" value="all"><label class="form-check-label" for="production-plan-export-all">Izvezi kompletan plan (sve godine)</label></div>
        </div>
        <hr>
        <div class="form-check form-switch mb-75"><input class="form-check-input" type="checkbox" id="production-plan-export-colours" checked><label class="form-check-label" for="production-plan-export-colours">Uključi boje prioriteta</label></div>
        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="production-plan-export-filter-summary" checked><label class="form-check-label" for="production-plan-export-filter-summary">Uključi sažetak filtera u dokument</label></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Odustani</button><button type="button" class="btn btn-primary" id="btn-potvrdi-izvoz-plana"><i data-feather="download" class="me-50"></i>Preuzmi Excel</button></div>
    </div>
  </div>
</div>
@endsection
@section('vendor-script')
  <script src="{{ asset(mix('vendors/js/forms/select/select2.full.min.js')) }}"></script><script src="{{ asset('vendors/js/tables/datatable/jquery.dataTables.min.js') }}"></script><script src="{{ asset('vendors/js/tables/datatable/dataTables.bootstrap5.min.js') }}"></script><script src="{{ asset('vendors/js/pickers/flatpickr/flatpickr.min.js') }}"></script><script src="{{ asset('vendors/js/extensions/sweetalert2.all.min.js') }}"></script>
@endsection
@section('page-script')
  <script>
    window.planProizvodnjeConfig = @json($planConfig);
    flatpickr('.shared-filter-date', {
      dateFormat: 'Y-m-d',
      altInput: true,
      altFormat: 'd.m.Y',
      allowInput: true,
      disableMobile: true,
      locale: {
        firstDayOfWeek: 1,
        weekdays: {
          shorthand: ['Ned', 'Pon', 'Uto', 'Sri', '\u010cet', 'Pet', 'Sub'],
          longhand: ['Nedjelja', 'Ponedjeljak', 'Utorak', 'Srijeda', '\u010cetvrtak', 'Petak', 'Subota']
        },
        months: {
          shorthand: ['Jan', 'Feb', 'Mar', 'Apr', 'Maj', 'Jun', 'Jul', 'Avg', 'Sep', 'Okt', 'Nov', 'Dec'],
          longhand: ['Januar', 'Februar', 'Mart', 'April', 'Maj', 'Juni', 'Juli', 'August', 'Septembar', 'Oktobar', 'Novembar', 'Decembar']
        }
      }
    });
  </script>
  <script src="{{ asset('js/scripts/pages/app-production-plan.js?v=144') }}"></script>
@endsection
