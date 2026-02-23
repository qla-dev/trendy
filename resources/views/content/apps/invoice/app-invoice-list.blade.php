@extends('layouts/contentLayoutMaster')

@section('title', 'Lista Radnih Naloga')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('vendors/css/tables/datatable/dataTables.bootstrap5.min.css')}}">
<link rel="stylesheet" href="{{asset('vendors/css/tables/datatable/extensions/dataTables.checkboxes.css')}}">
<link rel="stylesheet" href="{{asset('vendors/css/tables/datatable/responsive.bootstrap5.min.css')}}">
<link rel="stylesheet" href="{{asset('vendors/css/pickers/flatpickr/flatpickr.min.css')}}">
@endsection

@section('page-style')
<link rel="stylesheet" href="{{asset('css/base/pages/app-invoice-list.css')}}">
<link rel="stylesheet" href="{{asset('css/base/plugins/forms/pickers/form-flat-pickr.css')}}">
<style>
  .content-header {
    margin-top: -6px;
    margin-bottom: 4px;
  }
  .content-header-title {
    margin-top: 5px;
  }
</style>
@endsection

@section('content')
<section class="invoice-list-wrapper">
  <!-- Main Title -->
  <div class="content-header row">
    <div class="content-header-left col-md-9 col-12 mb-2">
      <div class="row breadcrumbs-top">
        <div class="col-12">
          <h2 class="content-header-title float-start mb-0">Radni nalozi</h2>
        </div>
      </div>
    </div>
    <div class="content-header-right text-md-end col-md-3 col-12 d-md-block d-none">
      <div class="mb-1 breadcrumb-right">
        <button type="button" class="btn btn-primary" id="btn-add">
          <i data-feather="plus" class="me-50"></i> Dodaj radni nalog
        </button>
      </div>
    </div>
  </div>

  <!-- Status Cards Section -->
  <div class="row mb-2">
    <div class="col-12">
      <div class="d-flex status-cards-wrapper">
        <div class="status-card" data-status="svi">
          <div class="status-card-body">
            <div class="status-label">Svi</div>
            <div class="status-count">{{ $statusStats['svi'] ?? 0 }}</div>
          </div>
        </div>
        <div class="status-card" data-status="planiran">
          <div class="status-card-body">
            <div class="status-label">Planiran</div>
            <div class="status-count">{{ $statusStats['planiran'] ?? 0 }}</div>
          </div>
        </div>
        <div class="status-card" data-status="otvoren">
          <div class="status-card-body">
            <div class="status-label">Otvoren</div>
            <div class="status-count">{{ $statusStats['otvoren'] ?? 0 }}</div>
          </div>
        </div>
        <div class="status-card" data-status="rezerviran">
          <div class="status-card-body">
            <div class="status-label">Rezerviran</div>
            <div class="status-count">{{ $statusStats['rezerviran'] ?? 0 }}</div>
          </div>
        </div>
        <div class="status-card" data-status="u_radu">
          <div class="status-card-body">
            <div class="status-label">U radu</div>
            <div class="status-count">{{ $statusStats['u_radu'] ?? 0 }}</div>
          </div>
        </div>
        <div class="status-card" data-status="djelimicno_zakljucen">
          <div class="status-card-body">
            <div class="status-label">Djelimično zaključen</div>
            <div class="status-count">{{ $statusStats['djelimicno_zakljucen'] ?? 0 }}</div>
          </div>
        </div>
        <div class="status-card" data-status="zakljucen">
          <div class="status-card-body">
            <div class="status-label">Zaključen</div>
            <div class="status-count">{{ $statusStats['zakljucen'] ?? 0 }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Filtering Section -->
  <div class="card mb-2">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h4 class="mb-0">Filter radnih naloga</h4>
      <div class="d-flex align-items-center flex-wrap gap-2 filter-header-actions">
        <div id="active-filters-container" class="active-filters-container d-none"></div>
        <div id="active-filters-divider" class="active-filters-divider d-none"></div>
        <button type="button" class="btn btn-outline-primary btn-sm" id="btn-toggle-filters" aria-expanded="true">
          <i data-feather="filter" class="me-50"></i> Sakrij filtere
        </button>
        <button type="button" class="btn btn-outline-danger btn-sm" id="btn-delete-filter">
          <i data-feather="trash-2" class="me-50"></i> Obriši filter
        </button>
      </div>
    </div>
    <div class="card-body" id="filters-body">
      <div class="row g-2 mb-2">
        <div class="col-md-3">
          <label class="form-label">Kupac</label>
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i data-feather="search"></i></span>
            <input type="text" class="form-control filter-input" id="filter-kupac" placeholder="Kupac">
          </div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Primatelj</label>
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i data-feather="search"></i></span>
            <input type="text" class="form-control filter-input" id="filter-primatelj" placeholder="Primatelj">
          </div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Proizvod</label>
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i data-feather="search"></i></span>
            <input type="text" class="form-control filter-input" id="filter-proizvod" placeholder="Proizvod">
          </div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Plan. početak od</label>
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i data-feather="calendar"></i></span>
            <input type="text" class="form-control filter-input filter-date-input" id="filter-plan-pocetak-od" placeholder="dd.mm.yyyy" autocomplete="off">
          </div>
        </div>
      </div>
      <div class="row g-2 mb-2">
        <div class="col-md-3">
          <label class="form-label">Plan. početak do</label>
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i data-feather="calendar"></i></span>
            <input type="text" class="form-control filter-input filter-date-input" id="filter-plan-pocetak-do" placeholder="dd.mm.yyyy" autocomplete="off">
          </div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Plan. kraj od</label>
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i data-feather="calendar"></i></span>
            <input type="text" class="form-control filter-input filter-date-input" id="filter-plan-kraj-od" placeholder="dd.mm.yyyy" autocomplete="off">
          </div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Plan. kraj do</label>
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i data-feather="calendar"></i></span>
            <input type="text" class="form-control filter-input filter-date-input" id="filter-plan-kraj-do" placeholder="dd.mm.yyyy" autocomplete="off">
          </div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Datum od</label>
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i data-feather="calendar"></i></span>
            <input type="text" class="form-control filter-input filter-date-input" id="filter-datum-od" placeholder="dd.mm.yyyy" autocomplete="off">
          </div>
        </div>
      </div>
      <div class="row g-2 mb-2">
        <div class="col-md-3">
          <label class="form-label">RN datum do</label>
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i data-feather="calendar"></i></span>
            <input type="text" class="form-control filter-input filter-date-input" id="filter-datum-do" placeholder="dd.mm.yyyy" autocomplete="off">
          </div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Vezni dok.</label>
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i data-feather="search"></i></span>
            <input type="text" class="form-control filter-input" id="filter-vezni-dok" placeholder="Vezni dok.">
          </div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Prioritet</label>
          <select class="form-select filter-input" id="filter-prioritet">
            <option value="">Svi prioriteti</option>
            <option value="1">1 - Visoki prioritet</option>
            <option value="5">5 - Uobičajeni prioritet</option>
            <option value="10">10 - Niski prioritet</option>
            <option value="15">15 - Uzorci</option>
          </select>
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <button type="button" class="btn btn-primary w-100" id="btn-filter">
            <i data-feather="filter" class="me-50"></i> Filter
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Table Section -->
  <div class="card">
    @if(isset($error))
      <div class="alert alert-danger">
        {{ $error }}
      </div>
    @endif
    <div class="card-datatable table-responsive">
      <table class="invoice-list-table table">
        <thead>
          <tr>
            <th>#</th>
            <th>Naziv proizvoda</th>
            <th>Klijent</th>
            <th>Ukupno</th>
            <th class="text-truncate">Datum Kreiranja</th>
            <th>Status</th>
            <th>Prioritet</th>
            <th class="cell-fit">Akcije</th>
          </tr>
        </thead>
      </table>
    </div>
  </div>
</section>

<!-- Pass data to JavaScript -->
<script>
  window.radniNaloziData = @json($radniNalozi ?? []);
  window.statusStats = @json($statusStats ?? []);
</script>

<!-- Status Cards Styles -->
<style>
  .status-cards-wrapper {
    display: flex;
    flex-wrap: nowrap;
    gap: 14px;
    width: 100%;
    align-items: stretch;
    margin: 0;
    padding: 0;
  }

  .status-card {
    flex: 1 1 0;
    min-width: 0;
    max-width: none;
    background: #fff;
    border: 1px solid #ebe9f1;
    border-radius: 5px;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: none;
  }

  .status-card:hover {
    background-color: #f8f8f8;
  }

  /* Keep original border colors on hover */
  .status-card[data-status="svi"]:hover,
  .status-card[data-status="planiran"]:hover,
  .status-card[data-status="otvoren"]:hover,
  .status-card[data-status="rezerviran"]:hover,
  .status-card[data-status="raspisan"]:hover,
  .status-card[data-status="u_radu"]:hover,
  .status-card[data-status="djelimicno_zakljucen"]:hover,
  .status-card[data-status="zakljucen"]:hover {
    border-color: inherit;
  }

  .status-card-active {
    border-color: #495B73;
    background-color: #fff;
    box-shadow: 0 4px 24px 0 rgba(34, 41, 47, 0.1);
  }

  .status-card-active:hover {
    border-color: #495B73;
    background-color: #f8f8f8;
    box-shadow: 0 4px 24px 0 rgba(34, 41, 47, 0.15);
  }

  /* Specific colors for each status card */
  .status-card[data-status="svi"] {
    border-color: #6e6b7b;
  }

  .status-card[data-status="svi"]:hover {
    border-color: #6e6b7b;
  }

  .status-card[data-status="planiran"] {
    border-color: #00cfe8;
  }

  .status-card[data-status="planiran"]:hover {
    border-color: #00cfe8;
  }

  .status-card[data-status="otvoren"] {
    border-color: #28c76f;
  }

  .status-card[data-status="otvoren"]:hover {
    border-color: #28c76f;
  }

  .status-card[data-status="rezerviran"] {
    border-color: #ff9f43;
  }

  .status-card[data-status="rezerviran"]:hover {
    border-color: #ff9f43;
  }

  .status-card[data-status="raspisan"] {
    border-color: #495B73;
  }

  .status-card[data-status="raspisan"]:hover {
    border-color: #495B73;
  }

  .status-card[data-status="u_radu"] {
    border-color: #ffc107;
  }

  .status-card[data-status="u_radu"]:hover {
    border-color: #ffc107;
  }

  .status-card[data-status="djelimicno_zakljucen"] {
    border-color: #fd7e14;
  }

  .status-card[data-status="djelimicno_zakljucen"]:hover {
    border-color: #fd7e14;
  }

  .status-card[data-status="zakljucen"] {
    border-color: #ea5455;
  }

  .status-card[data-status="zakljucen"]:hover {
    border-color: #ea5455;
  }

  .status-card-body {
    padding: 10px 15px;
    text-align: center;
  }

  .status-label {
    font-size: 13px;
    font-weight: 500;
    margin-bottom: 5px;
    color: #6e6b7b;
    white-space: nowrap;
  }

  /* Status label colors matching border colors */
  .status-card[data-status="svi"] .status-label {
    color: #6e6b7b;
  }

  .status-card[data-status="planiran"] .status-label {
    color: #00cfe8;
  }

  .status-card[data-status="otvoren"] .status-label {
    color: #28c76f;
  }

  .status-card[data-status="rezerviran"] .status-label {
    color: #ff9f43;
  }

  .status-card[data-status="raspisan"] .status-label {
    color: #495B73;
  }

  .status-card[data-status="u_radu"] .status-label {
    color: #ffc107;
  }

  .status-card[data-status="djelimicno_zakljucen"] .status-label {
    color: #fd7e14;
  }

  .status-card[data-status="zakljucen"] .status-label {
    color: #ea5455;
  }

  .status-count {
    font-size: 1.5rem;
    font-weight: 700;
    color: #5e5873;
    line-height: 1;
  }

  .status-badge {
    font-weight: 500;
  }

  .status-badge-default {
    background-color: rgba(110, 107, 123, 0.12) !important;
    color: #6e6b7b !important;
  }

  .status-badge-planiran {
    background-color: rgba(0, 207, 232, 0.12) !important;
    color: #00cfe8 !important;
  }

  .status-badge-otvoren {
    background-color: rgba(40, 199, 111, 0.12) !important;
    color: #28c76f !important;
  }

  .status-badge-rezerviran {
    background-color: rgba(255, 159, 67, 0.12) !important;
    color: #ff9f43 !important;
  }

  .status-badge-raspisan {
    background-color: rgba(73, 91, 115, 0.12) !important;
    color: #495B73 !important;
  }

  .status-badge-u-radu {
    background-color: rgba(255, 193, 7, 0.16) !important;
    color: #b38600 !important;
  }

  .status-badge-djelimicno-zakljucen {
    background-color: rgba(253, 126, 20, 0.12) !important;
    color: #fd7e14 !important;
  }

  .status-badge-zakljucen {
    background-color: rgba(234, 84, 85, 0.12) !important;
    color: #ea5455 !important;
  }

  .avatar.avatar-status.status-avatar-default {
    background-color: rgba(110, 107, 123, 0.12);
    color: #6e6b7b;
  }

  .avatar.avatar-status.status-avatar-planiran {
    background-color: rgba(0, 207, 232, 0.12);
    color: #00cfe8;
  }

  .avatar.avatar-status.status-avatar-otvoren {
    background-color: rgba(40, 199, 111, 0.12);
    color: #28c76f;
  }

  .avatar.avatar-status.status-avatar-rezerviran {
    background-color: rgba(255, 159, 67, 0.12);
    color: #ff9f43;
  }

  .avatar.avatar-status.status-avatar-raspisan {
    background-color: rgba(73, 91, 115, 0.12);
    color: #495B73;
  }

  .avatar.avatar-status.status-avatar-u-radu {
    background-color: rgba(255, 193, 7, 0.16);
    color: #b38600;
  }

  .avatar.avatar-status.status-avatar-djelimicno-zakljucen {
    background-color: rgba(253, 126, 20, 0.12);
    color: #fd7e14;
  }

  .avatar.avatar-status.status-avatar-zakljucen {
    background-color: rgba(234, 84, 85, 0.12);
    color: #ea5455;
  }

  .filter-input {
    font-size: 14px;
  }

  .filter-date-input,
  .filter-date-input.flatpickr-input,
  .filter-date-input + .flatpickr-input,
  .filter-date-input[readonly],
  .flatpickr-input[readonly] {
    cursor: pointer;
  }

  .filter-header-actions {
    row-gap: 8px;
  }

  .active-filters-container {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
  }

  .active-filters-divider {
    width: 1px;
    height: 28px;
    background-color: #d8d6de;
    margin-right: 2px;
  }

  .active-filter-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border: 1px solid #d8d6de;
    border-radius: 999px;
    background-color: #f8f8f8;
    font-size: 12px;
    line-height: 1;
  }

  .active-filter-chip-label {
    color: #6e6b7b;
    font-weight: 500;
  }

  .active-filter-chip-value {
    color: #5e5873;
    font-weight: 600;
  }

  .active-filter-remove {
    border: 0;
    background: transparent;
    color: #ea5455;
    cursor: pointer;
    font-size: 14px;
    line-height: 1;
    padding: 0;
  }

  #btn-toggle-filters:focus,
  #btn-toggle-filters:active,
  #btn-delete-filter:focus,
  #btn-delete-filter:active {
    box-shadow: none !important;
  }

  #btn-toggle-filters:not(:hover):not(:active),
  #btn-delete-filter:not(:hover):not(:active) {
    background-color: transparent;
  }

  .filter-input::placeholder {
    padding-left: 8px;
  }

  .input-group-text {
    background: #f8f8f8;
    border-right: none;
  }

  .content-header-right {
    display: flex;
    justify-content: flex-end;
    align-items: center;
  }

  .content-header-right .breadcrumb-right {
    width: 100%;
    display: flex;
    justify-content: flex-end;
  }

  .invoice-list-table tbody tr {
    cursor: pointer;
    transition: background-color 0.2s ease, color 0.2s ease;
  }

  .invoice-list-table tbody tr td a,
  .invoice-list-table tbody tr td button,
  .invoice-list-table tbody tr td .wo-eye-action {
    cursor: pointer;
  }

  .invoice-list-table.table tbody tr:hover > * {
    background-color: #f8f8fc;
  }

  .invoice-table-overlay-host {
    position: relative;
  }

  .invoice-table-loading-overlay {
    position: absolute;
    display: none;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.74);
    backdrop-filter: blur(1px);
    z-index: 9;
    pointer-events: all;
  }

  .invoice-table-loading-overlay.is-visible {
    display: flex;
  }

  .invoice-table-loading-overlay-content {
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.65rem;
    text-align: center;
  }

  .invoice-table-loading-spinner {
    width: 2rem;
    height: 2rem;
    border-width: 0.2em;
    color: #495b73;
  }

  .invoice-table-loading-message {
    font-size: 0.95rem;
    font-weight: 600;
    color: #5e5873;
    letter-spacing: 0.01em;
  }

  .wo-eye-action {
    width: 32px;
    height: 32px;
    border: 1px solid #96a0b5;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #6e6b7b;
    background-color: transparent;
    transition: all 0.2s ease;
  }

  .wo-eye-action svg {
    width: 16px;
    height: 16px;
  }

  .wo-eye-action svg * {
    stroke: currentColor;
  }

  .wo-eye-action:hover,
  .wo-eye-action:focus {
    color: #42526e;
    border-color: #42526e;
    background-color: rgba(66, 82, 110, 0.08);
  }

  .wo-eye-action:focus-visible {
    outline: none;
    box-shadow: 0 0 0 0.2rem rgba(66, 82, 110, 0.18);
  }

  div.dataTables_wrapper div.dataTables_filter {
    text-align: right;
    float: right;
  }

  div.dataTables_wrapper div.dataTables_filter label {
    margin-top: 0;
    margin-bottom: 0;
    text-align: right;
    float: right;
    display: inline-flex;
    align-items: center;
  }

  div.dataTables_wrapper div.dataTables_filter input {
    text-align: left;
  }

  .invoice-search-label-wrap {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    margin-right: 0.5rem;
    color: #6e6b7b;
    font-weight: 500;
    line-height: 1;
    white-space: nowrap;
  }

  .invoice-search-header-spinner {
    width: 0.9rem;
    height: 0.9rem;
    border-width: 0.15em;
    color: #495b73;
    display: none;
  }

  .invoice-search-header-spinner.is-visible {
    display: inline-block;
  }

  div.dataTables_wrapper div.dataTables_length select,
  div.dataTables_wrapper .dataTables_length .form-select {
    cursor: pointer;
  }

  .dark-layout .status-card .status-count {
    color: #fff;
  }

  .dark-layout .invoice-search-label-wrap,
  .semi-dark-layout .invoice-search-label-wrap {
    color: #d6dcec;
  }

  .dark-layout .invoice-search-header-spinner,
  .semi-dark-layout .invoice-search-header-spinner {
    color: #d6dcec;
  }

  .dark-layout .invoice-list-table tbody td:nth-child(1) a,
  .dark-layout .invoice-list-table tbody td:nth-child(1) a:visited,
  .dark-layout .invoice-list-table tbody td:nth-child(1) a:hover,
  .dark-layout .invoice-list-table tbody td:nth-child(1) a:focus {
    color: #fff !important;
  }

  .dark-layout .invoice-list-table.table tbody tr:hover > *,
  .semi-dark-layout .invoice-list-table.table tbody tr:hover > * {
    background-color: #36405a !important;
    color: #fff !important;
  }

  .dark-layout .invoice-table-loading-overlay,
  .semi-dark-layout .invoice-table-loading-overlay {
    background: rgba(20, 28, 48, 0.68);
  }

  .dark-layout .invoice-table-loading-spinner,
  .semi-dark-layout .invoice-table-loading-spinner {
    color: #d6dcec;
  }

  .dark-layout .invoice-table-loading-message,
  .semi-dark-layout .invoice-table-loading-message {
    color: #f4f5fb;
  }

  .dark-layout #btn-toggle-filters,
  .dark-layout #btn-toggle-filters:hover,
  .dark-layout #btn-toggle-filters:focus {
    color: #fff !important;
    border-color: #fff !important;
  }

  .dark-layout .active-filters-divider {
    background-color: rgba(255, 255, 255, 0.4);
  }

  .dark-layout .active-filter-chip {
    background-color: rgba(255, 255, 255, 0.08);
    border-color: rgba(255, 255, 255, 0.24);
  }

  .dark-layout .active-filter-chip-label,
  .dark-layout .active-filter-chip-value {
    color: #fff;
  }

  .dark-layout .wo-eye-action,
  .semi-dark-layout .wo-eye-action {
    color: #d6dcec;
    border-color: rgba(214, 220, 236, 0.45);
    background-color: transparent;
  }

  .dark-layout .wo-eye-action:hover,
  .dark-layout .wo-eye-action:focus,
  .semi-dark-layout .wo-eye-action:hover,
  .semi-dark-layout .wo-eye-action:focus {
    color: #fff;
    border-color: rgba(255, 255, 255, 0.7);
    background-color: rgba(255, 255, 255, 0.08);
  }
</style>
@endsection

@section('vendor-script')
<script src="{{asset('vendors/js/extensions/moment.min.js')}}"></script>
<script src="{{asset('vendors/js/tables/datatable/jquery.dataTables.min.js')}}"></script>
<script src="{{asset('vendors/js/tables/datatable/datatables.buttons.min.js')}}"></script>
<script src="{{asset('vendors/js/tables/datatable/dataTables.bootstrap5.min.js')}}"></script>
<script src="{{asset('vendors/js/tables/datatable/datatables.checkboxes.min.js')}}"></script>
<script src="{{asset('vendors/js/tables/datatable/dataTables.responsive.min.js')}}"></script>
<script src="{{asset('vendors/js/tables/datatable/responsive.bootstrap5.js')}}"></script>
<script src="{{asset('vendors/js/pickers/flatpickr/flatpickr.min.js')}}"></script>
@endsection

@section('page-script')
<script src="{{asset('js/scripts/pages/app-invoice-list.js')}}"></script>
@endsection
