@extends('layouts/contentLayoutMaster')

@section('title', 'Lista narudzbi')

@php
  $orderLinkageConfig = [
      'dataUrl' => (string) ($ordersLinkageDataUrl ?? route('app-orders-data')),
      'positionsUrl' => (string) ($ordersLinkagePositionsUrl ?? route('app-orders-positions')),
      'workOrdersUrl' => (string) ($ordersLinkageWorkOrdersUrl ?? route('app-orders-work-orders')),
      'workOrdersApiUrl' => (string) ($ordersLinkageWorkOrdersApiUrl ?? route('app-orders-radni-nalozi')),
  ];
@endphp

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('vendors/css/tables/datatable/dataTables.bootstrap5.min.css') }}">
<link rel="stylesheet" href="{{ asset('vendors/css/tables/datatable/responsive.bootstrap5.min.css') }}">
<link rel="stylesheet" href="{{ asset('vendors/css/pickers/flatpickr/flatpickr.min.css') }}">
<link rel="stylesheet" href="{{ asset('vendors/css/extensions/sweetalert2.min.css') }}">
@endsection

@section('page-style')
<style>
  .content-header {
    margin-top: -6px;
    margin-bottom: 4px;
  }

  .content-header-title {
    margin-top: 5px;
  }

  .order-linkage-wrapper {
    --wo-table-scroll-track: var(--app-scroll-track);
    --wo-table-scroll-thumb: var(--app-scroll-thumb-flat);
    --wo-table-scroll-thumb-hover: var(--app-scroll-thumb-flat-hover);
    --wo-table-scroll-thumb-active: var(--app-scroll-thumb-flat-active);
    --wo-table-scroll-thumb-border: var(--app-scroll-thumb-border);
  }

  .order-linkage-filter-actions {
    row-gap: 8px;
  }

  .order-linkage-active-filters {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
  }

  .order-linkage-active-filters-divider {
    width: 1px;
    height: 28px;
    background-color: #d8d6de;
    margin-right: 2px;
  }

  .order-linkage-active-filter-chip {
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

  .order-linkage-active-filter-label {
    color: #6e6b7b;
    font-weight: 500;
  }

  .order-linkage-active-filter-value {
    color: #5e5873;
    font-weight: 600;
  }

  .order-linkage-active-filter-remove {
    border: 0;
    background: transparent;
    color: #ea5455;
    padding: 0;
    font-size: 14px;
    line-height: 1;
  }

  .order-linkage-filter-input {
    font-size: 14px;
  }

  .order-linkage-filter-date,
  .order-linkage-filter-date.flatpickr-input,
  .order-linkage-filter-date + .flatpickr-input,
  .order-linkage-filter-date[readonly],
  .flatpickr-input[readonly] {
    cursor: pointer;
  }

  .order-linkage-wrapper .card-datatable.table-responsive {
    overflow-x: hidden;
  }

  .order-linkage-wrapper .card-datatable .dataTables_wrapper > .row:first-child,
  .order-linkage-wrapper .card-datatable .dataTables_wrapper > .row:last-child {
    margin-left: 0;
    margin-right: 0;
    padding: 1rem 1rem 0.95rem;
  }

  .order-linkage-wrapper .card-datatable.order-linkage-initial-loading .dataTables_wrapper > .row:last-child {
    display: none;
  }

  .order-linkage-wrapper .card-datatable .dataTables_wrapper > .row:first-child > [class*='col-'],
  .order-linkage-wrapper .card-datatable .dataTables_wrapper > .row:last-child > [class*='col-'] {
    padding-left: 0;
    padding-right: 0;
  }

  .order-linkage-wrapper .card-datatable .dataTables_wrapper > .row:nth-child(2) {
    margin-left: 0;
    margin-right: 0;
  }

  .order-linkage-wrapper .card-datatable .dataTables_wrapper > .row:nth-child(2) > [class*='col-'] {
    padding-left: 0;
    padding-right: 0;
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
    scrollbar-color: var(--wo-table-scroll-thumb) var(--wo-table-scroll-track);
    scrollbar-gutter: stable;
  }

  .order-linkage-wrapper .card-datatable .dataTables_wrapper > .row:nth-child(2) > [class*='col-']::-webkit-scrollbar {
    width: 8px;
    height: 8px;
  }

  .order-linkage-wrapper .card-datatable .dataTables_wrapper > .row:nth-child(2) > [class*='col-']::-webkit-scrollbar-track {
    background: var(--wo-table-scroll-track);
    border-radius: 999px;
  }

  .order-linkage-wrapper .card-datatable .dataTables_wrapper > .row:nth-child(2) > [class*='col-']::-webkit-scrollbar-thumb {
    background: var(--wo-table-scroll-thumb);
    border-radius: 999px;
    border: 1px solid var(--wo-table-scroll-thumb-border);
  }

  .order-linkage-wrapper .card-datatable .dataTables_wrapper > .row:nth-child(2) > [class*='col-']::-webkit-scrollbar-thumb:hover {
    background: var(--wo-table-scroll-thumb-hover);
  }

  .order-linkage-wrapper .card-datatable .dataTables_wrapper > .row:nth-child(2) > [class*='col-']::-webkit-scrollbar-thumb:active {
    background: var(--wo-table-scroll-thumb-active);
  }

  .order-linkage-wrapper .card-datatable .dataTables_wrapper > .row:nth-child(2) > [class*='col-']::-webkit-scrollbar-corner {
    background: var(--wo-table-scroll-track);
  }

  .order-linkage-table-body-scroll {
    display: block;
    width: 100%;
    max-width: 100%;
    min-width: 0;
    box-sizing: border-box;
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
    scrollbar-color: var(--wo-table-scroll-thumb) var(--wo-table-scroll-track);
    scrollbar-gutter: stable;
  }

  .order-linkage-table-body-scroll::-webkit-scrollbar {
    width: 8px;
    height: 8px;
  }

  .order-linkage-table-body-scroll::-webkit-scrollbar-track {
    background: var(--wo-table-scroll-track);
    border-radius: 999px;
  }

  .order-linkage-table-body-scroll::-webkit-scrollbar-thumb {
    background: var(--wo-table-scroll-thumb);
    border-radius: 999px;
    border: 1px solid var(--wo-table-scroll-thumb-border);
  }

  .order-linkage-table-body-scroll::-webkit-scrollbar-thumb:hover {
    background: var(--wo-table-scroll-thumb-hover);
  }

  .order-linkage-table-body-scroll::-webkit-scrollbar-thumb:active {
    background: var(--wo-table-scroll-thumb-active);
  }

  .order-linkage-table-body-scroll::-webkit-scrollbar-corner {
    background: var(--wo-table-scroll-track);
  }

  .order-linkage-table {
    min-width: 1380px;
  }

  .order-linkage-table tbody tr {
    transition: background-color 0.2s ease;
  }

  .order-linkage-table.table tbody tr:hover > * {
    background-color: #f8f8fc;
  }

  body.dark-layout .order-linkage-table.table tbody tr:hover > *,
  body.semi-dark-layout .order-linkage-table.table tbody tr:hover > *,
  .dark-layout .order-linkage-table.table tbody tr:hover > *,
  .semi-dark-layout .order-linkage-table.table tbody tr:hover > * {
    background-color: #36405a !important;
  }

  .order-linkage-loading-spacer-row > td {
    height: 180px;
    padding: 0 !important;
    border-top: 0 !important;
    border-bottom: 0 !important;
    background: transparent !important;
  }

  .order-linkage-order-cell {
    min-width: 170px;
  }

  .order-linkage-order-number {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 700;
    letter-spacing: 0.01em;
  }

  .order-linkage-indicator {
    font-size: 0.74rem;
    font-weight: 600;
    letter-spacing: 0.01em;
  }

  .order-linkage-status-cell,
  .order-linkage-quantity-cell,
  .order-linkage-count-cell,
  .order-linkage-actions-cell {
    white-space: nowrap;
  }

  .order-linkage-actions-cell {
    width: 1%;
    position: sticky;
    right: 0;
    z-index: 2;
    background-color: #ffffff;
    box-shadow: none;
    border-left: 1px solid #ebe9f1;
  }

  .order-linkage-table thead .order-linkage-actions-cell {
    z-index: 3;
    background-color: #f8f8fa;
  }

  .order-linkage-table.table tbody tr:hover > .order-linkage-actions-cell {
    background-color: #f8f8fc;
  }

  body.dark-layout .order-linkage-table .order-linkage-actions-cell,
  body.semi-dark-layout .order-linkage-table .order-linkage-actions-cell,
  .dark-layout .order-linkage-table .order-linkage-actions-cell,
  .semi-dark-layout .order-linkage-table .order-linkage-actions-cell {
    background-color: #283046;
    border-left-color: rgba(184, 190, 220, 0.22);
  }

  body.dark-layout .order-linkage-table thead .order-linkage-actions-cell,
  body.semi-dark-layout .order-linkage-table thead .order-linkage-actions-cell,
  .dark-layout .order-linkage-table thead .order-linkage-actions-cell,
  .semi-dark-layout .order-linkage-table thead .order-linkage-actions-cell {
    background-color: #2f3854;
  }

  body.dark-layout .order-linkage-table.table tbody tr:hover > .order-linkage-actions-cell,
  body.semi-dark-layout .order-linkage-table.table tbody tr:hover > .order-linkage-actions-cell,
  .dark-layout .order-linkage-table.table tbody tr:hover > .order-linkage-actions-cell,
  .semi-dark-layout .order-linkage-table.table tbody tr:hover > .order-linkage-actions-cell {
    background-color: #36405a !important;
  }

  .order-linkage-actions-group {
    display: inline-flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.65rem;
    flex-wrap: nowrap;
  }

  .order-linkage-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    min-width: 98px;
    white-space: nowrap;
  }

  .order-linkage-action-btn.order-linkage-positions-btn {
    border-color: #1e88e5 !important;
    color: #1e88e5 !important;
    background-color: transparent !important;
  }

  .order-linkage-action-btn.order-linkage-positions-btn:hover {
    background-color: rgba(30, 136, 229, 0.1) !important;
  }

  .order-linkage-action-btn.order-linkage-work-orders-btn {
    border-color: #28c76f !important;
    color: #28c76f !important;
    background-color: transparent !important;
    justify-content: center;
    text-align: center;
  }

  .order-linkage-action-btn.order-linkage-work-orders-btn:hover {
    background-color: rgba(40, 199, 111, 0.1) !important;
  }

  .order-linkage-action-btn[disabled],
  .order-linkage-action-btn.disabled {
    opacity: 0.45;
    cursor: not-allowed;
    pointer-events: none;
  }

  .order-linkage-modal .modal-content {
    border-radius: 1rem;
  }

  .order-linkage-modal-subtitle {
    margin-top: 0.2rem;
    font-size: 0.9rem;
    color: #6e6b7b;
  }

  .order-linkage-modal-loading {
    min-height: 240px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 0.9rem;
    color: #6e6b7b;
  }

  .order-linkage-modal-summary-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.9rem;
    margin-bottom: 1rem;
  }

  .order-linkage-modal-summary-card {
    padding: 0.95rem 1rem;
    border-radius: 0.85rem;
    border: 1px solid rgba(71, 95, 123, 0.12);
    background: rgba(71, 95, 123, 0.04);
  }

  .order-linkage-modal-summary-label {
    display: block;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: #6e6b7b;
    margin-bottom: 0.35rem;
  }

  .order-linkage-modal-summary-value {
    display: block;
    font-size: 1rem;
    font-weight: 600;
    line-height: 1.35;
  }

  .order-linkage-modal-table-wrap {
    border: 1px solid rgba(71, 95, 123, 0.12);
    border-radius: 0.85rem;
    overflow: hidden;
  }

  .order-linkage-modal-table {
    margin-bottom: 0;
  }

  .order-linkage-modal-table thead th {
    white-space: nowrap;
  }

  .order-linkage-modal-empty {
    padding: 1rem;
    color: #6e6b7b;
    text-align: center;
  }

  @media (max-width: 991.98px) {
    .order-linkage-modal-summary-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }

  @media (max-width: 767.98px) {
    .order-linkage-filter-actions {
      align-items: flex-start !important;
    }

    .order-linkage-modal-summary-grid {
      grid-template-columns: minmax(0, 1fr);
    }
  }
</style>
@endsection

@section('content')
<section class="order-linkage-wrapper">
  <div class="content-header row">
    <div class="content-header-left col-12 mb-2">
      <div class="row breadcrumbs-top">
        <div class="col-12">
          <h2 class="content-header-title float-start mb-0">Narud&#382;be</h2>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-2">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h4 class="mb-0">Filter narud&#382;bi</h4>
      <div class="d-flex align-items-center flex-wrap gap-2 order-linkage-filter-actions">
        <div id="active-filters-container" class="order-linkage-active-filters d-none"></div>
        <div id="active-filters-divider" class="order-linkage-active-filters-divider d-none"></div>
        <button type="button" class="btn btn-outline-primary btn-sm" id="btn-toggle-filters" aria-expanded="true">
          <i data-feather="filter" class="me-50"></i> Sakrij filtere
        </button>
        <button type="button" class="btn btn-outline-danger btn-sm" id="btn-delete-filter">
          <i data-feather="trash-2" class="me-50"></i> Obri&#353;i filter
        </button>
      </div>
    </div>
    <div class="card-body" id="filters-body">
      <div class="row g-2 mb-2">
        <div class="col-md-3">
          <label class="form-label">Kupac</label>
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i data-feather="search"></i></span>
            <input type="text" class="form-control order-linkage-filter-input" id="filter-kupac" placeholder="Kupac">
          </div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Primatelj</label>
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i data-feather="search"></i></span>
            <input type="text" class="form-control order-linkage-filter-input" id="filter-primatelj" placeholder="Primatelj">
          </div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Proizvod</label>
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i data-feather="search"></i></span>
            <input type="text" class="form-control order-linkage-filter-input" id="filter-proizvod" placeholder="Proizvod">
          </div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Plan. po&#269;etak od</label>
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i data-feather="calendar"></i></span>
            <input type="text" class="form-control order-linkage-filter-input order-linkage-filter-date" id="filter-plan-pocetak-od" placeholder="dd.mm.yyyy" autocomplete="off">
          </div>
        </div>
      </div>
      <div class="row g-2 mb-2">
        <div class="col-md-3">
          <label class="form-label">Plan. po&#269;etak do</label>
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i data-feather="calendar"></i></span>
            <input type="text" class="form-control order-linkage-filter-input order-linkage-filter-date" id="filter-plan-pocetak-do" placeholder="dd.mm.yyyy" autocomplete="off">
          </div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Plan. kraj od</label>
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i data-feather="calendar"></i></span>
            <input type="text" class="form-control order-linkage-filter-input order-linkage-filter-date" id="filter-plan-kraj-od" placeholder="dd.mm.yyyy" autocomplete="off">
          </div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Plan. kraj do</label>
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i data-feather="calendar"></i></span>
            <input type="text" class="form-control order-linkage-filter-input order-linkage-filter-date" id="filter-plan-kraj-do" placeholder="dd.mm.yyyy" autocomplete="off">
          </div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Datum od</label>
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i data-feather="calendar"></i></span>
            <input type="text" class="form-control order-linkage-filter-input order-linkage-filter-date" id="filter-datum-od" placeholder="dd.mm.yyyy" autocomplete="off">
          </div>
        </div>
      </div>
      <div class="row g-2 mb-2">
        <div class="col-md-3">
          <label class="form-label">Datum do</label>
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i data-feather="calendar"></i></span>
            <input type="text" class="form-control order-linkage-filter-input order-linkage-filter-date" id="filter-datum-do" placeholder="dd.mm.yyyy" autocomplete="off">
          </div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Vezni dok.</label>
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i data-feather="search"></i></span>
            <input type="text" class="form-control order-linkage-filter-input" id="filter-vezni-dok" placeholder="Vezni dok.">
          </div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Prioritet</label>
          <select class="form-select order-linkage-filter-input" id="filter-prioritet">
            <option value="">Svi prioriteti</option>
            <option value="1">1 - Visoki prioritet</option>
            <option value="5">5 - Uobi&#269;ajeni prioritet</option>
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

  <div class="card">
    <div class="alert alert-danger mb-0 d-none" id="order-linkage-page-error"></div>
    <div class="card-datatable table-responsive order-linkage-initial-loading">
      <table class="table order-linkage-table" id="order-linkage-table">
        <thead>
          <tr>
            <th>Narud&#382;ba</th>
            <th>Naru&#269;itelj</th>
            <th>Prijevoznik</th>
            <th>Datum</th>
            <th>Koli&#269;ina</th>
            <th>Broj pozicija</th>
            <th>Broj RN</th>
            <th class="order-linkage-actions-cell">Akcija</th>
          </tr>
        </thead>
        <tbody>
          <tr class="order-linkage-loading-spacer-row" aria-hidden="true">
            <td colspan="8"></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</section>

<div class="modal fade order-linkage-modal" id="order-linkage-modal" tabindex="-1" aria-labelledby="order-linkage-modal-label" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0" id="order-linkage-modal-label">Detalji narud&#382;be</h5>
          <div class="order-linkage-modal-subtitle" id="order-linkage-modal-subtitle">-</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zatvori"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-danger d-none" id="order-linkage-modal-error"></div>
        <div class="order-linkage-modal-loading" id="order-linkage-modal-loading">
          <span class="spinner-border text-primary" role="status" aria-hidden="true"></span>
          <span>Ucitavanje detalja narudzbe...</span>
        </div>
        <div id="order-linkage-modal-content" class="d-none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Zatvori</button>
      </div>
    </div>
  </div>
</div>
@endsection

@section('vendor-script')
<script src="{{ asset('vendors/js/tables/datatable/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('vendors/js/tables/datatable/dataTables.bootstrap5.min.js') }}"></script>
<script src="{{ asset('vendors/js/tables/datatable/dataTables.responsive.min.js') }}"></script>
<script src="{{ asset('vendors/js/tables/datatable/responsive.bootstrap5.js') }}"></script>
<script src="{{ asset('vendors/js/pickers/flatpickr/flatpickr.min.js') }}"></script>
<script src="{{ asset('vendors/js/extensions/sweetalert2.all.min.js') }}"></script>
@endsection

@section('page-script')
<script>
  window.orderLinkageConfig = @json($orderLinkageConfig);
</script>
<script src="{{ asset('js/scripts/pages/app-orders.js?v=2') }}"></script>
@endsection
