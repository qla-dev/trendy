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
    .production-plan-table { min-width: 1500px; }
    .production-plan-table > :not(caption) > * > * { padding: .42rem .5rem; font-size: .8rem; white-space: nowrap; }
    .production-plan-table .editable-cell { cursor: pointer; }
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
    .production-plan-table-loading-overlay-content { display: inline-flex; flex-direction: column; align-items: center; gap: .65rem; text-align: center; }
    .production-plan-table-loading-spinner { width: 2rem; height: 2rem; border-width: .2em; color: #495b73; }
    .production-plan-table-loading-message { font-size: .95rem; font-weight: 600; color: #5e5873; letter-spacing: .01em; }
    #production-plan-export-modal button { cursor: pointer; }
    .dark-layout .production-plan-table-loading-overlay, .semi-dark-layout .production-plan-table-loading-overlay { background: rgba(20, 28, 48, .68); }
    .dark-layout .production-plan-table-loading-spinner, .semi-dark-layout .production-plan-table-loading-spinner { color: #d6dcec; }
    .dark-layout .production-plan-table-loading-message, .semi-dark-layout .production-plan-table-loading-message { color: #f4f5fb; }
    .production-plan-wrapper .card-datatable.table-responsive { overflow-x: hidden; }
    .production-plan-wrapper .card-datatable .dataTables_wrapper > .row:first-child,
    .production-plan-wrapper .card-datatable .dataTables_wrapper > .row:last-child { margin-right: 0; margin-left: 0; padding: 1rem; }
    .production-plan-wrapper .card-datatable .dataTables_wrapper > .row:first-child > [class*='col-'],
    .production-plan-wrapper .card-datatable .dataTables_wrapper > .row:last-child > [class*='col-'] { padding-right: 0; padding-left: 0; }
    .production-plan-wrapper .card-datatable .dataTables_wrapper > .row:nth-child(2) { margin-right: 0; margin-left: 0; }
    .production-plan-wrapper .card-datatable .dataTables_wrapper > .row:nth-child(2) > [class*='col-'] { padding-right: 0; padding-left: 0; }
    @media (min-width: 768px) {
      .production-plan-wrapper .card-datatable .dataTables_wrapper > .row:first-child { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
      .production-plan-wrapper .card-datatable .dataTables_wrapper > .row:first-child > [class*='col-']:first-child { flex: 1 1 auto; width: auto; max-width: none; }
      .production-plan-wrapper .card-datatable .dataTables_wrapper > .row:first-child > [class*='col-']:last-child { flex: 0 0 auto; width: auto; max-width: none; margin-left: auto; }
    }
    .plan-inline-editor { z-index: 2000; min-width: 240px; max-width: calc(100vw - 16px); padding: .65rem; background: #fff; border: 1px solid #d8d6de; border-radius: .35rem; box-shadow: 0 5px 18px rgba(34,41,47,.16); }
    .plan-inline-editor label { font-size: .75rem; margin-bottom: .35rem; }
    .plan-inline-editor textarea { min-height: 80px; }
  </style>
@endsection
@section('content')
<section id="rn-plan">
  <div class="content-header row"><div class="col-12 mb-2"><h2 class="mb-0">Plan proizvodnje — Radni nalozi</h2></div></div>
  <div class="card mb-2"><div class="card-header d-flex justify-content-between align-items-center"><h4 class="mb-0">Filter plana proizvodnje</h4><div class="d-flex align-items-center flex-wrap gap-2"><button class="btn btn-outline-primary btn-sm" id="btn-prikazi-filtere"><i data-feather="filter" class="me-50"></i>Prikaži filtere</button><button class="btn btn-outline-danger btn-sm" id="btn-obrisi-filter"><i data-feather="trash-2" class="me-50"></i>Obriši filter</button></div></div>
    <div class="card-body d-none" id="tijelo-filtera"><div class="row g-2">
      <div class="col-md-3"><label class="form-label" for="filter-prioritet">Prioritet</label><select class="form-select f" id="filter-prioritet" data-k="prioritet"><option value="">Svi prioriteti</option>@foreach (($planConfig['priorityOptions'] ?? []) as $priorityOption)<option value="{{ $priorityOption['code'] }}">{{ $priorityOption['label'] }}</option>@endforeach</select></div>
      <div class="col-md-3"><label class="form-label" for="plan-boja-redova">Boja redova</label><select class="form-select" id="plan-boja-redova"><option value="none">Bez boje</option><option value="basic">Osnovne</option><option value="all" selected>Sve</option></select></div>
      <div class="col-md-3"><x-filters.text label="RN" class="f" data-k="rn"/></div><div class="col-md-3"><x-filters.text label="Naručitelj" class="f" data-k="narucitelj"/></div><div class="col-md-3"><x-filters.text label="Proizvod" class="f" data-k="proizvod"/></div><div class="col-md-3"><label class="form-label">Status RN</label><input class="form-control f" data-k="status_rn"></div><div class="col-md-3"><x-filters.text label="Narudžba" class="f" data-k="narudzba"/></div><div class="col-md-3"><label class="form-label">Godina</label><input class="form-control f" data-k="year" value="{{ now()->year }}"></div><div class="col-md-3"><label class="form-label">Kalendarska sedmica</label><input class="form-control f" data-k="kw" placeholder="1–53"></div><div class="col-md-3"><x-filters.date label="Datum od" class="f" data-k="datum_od"/></div><div class="col-md-3"><x-filters.date label="Datum do" class="f" data-k="datum_do"/></div><div class="col-md-3 d-flex align-items-end"><button id="filter" class="btn btn-primary w-100"><i data-feather="filter" class="me-50"></i>Filter</button></div>
    </div></div>
  </div>
  <div class="card production-plan-wrapper"><div class="card-datatable table-responsive"><table class="table production-plan-table" id="plan-proizvodnje-tabela"><thead><tr><th>%</th><th>RN</th><th>Naručitelj</th><th>Prioritet</th><th>Datum</th><th>Narudžba</th><th>Br. poz.</th><th>Poč. termin</th><th>Kraj termin</th><th>Proizvod</th><th>Plan. kol.</th><th>Izr. kol.</th><th>Naziv</th><th>Napomena</th></tr></thead></table></div></div>
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
  <script>window.planProizvodnjeConfig=@json($planConfig);flatpickr('.shared-filter-date',{dateFormat:'Y-m-d',altInput:true,altFormat:'d.m.Y',allowInput:true,disableMobile:true});</script>
  <script src="{{ asset('js/scripts/pages/app-production-plan.js?v=115') }}"></script>
@endsection
