@extends('layouts/contentLayoutMaster')

@section('title', 'Razduženi materijali')

@php
  $releasedMaterialsConfig = [
      'dataUrl' => (string) ($releasedMaterialsDataUrl ?? route('app-released-material-documents-data')),
      'deleteUrl' => (string) ($releasedMaterialsDeleteUrl ?? route('app-released-material-documents-destroy')),
      'canDeleteDocuments' => (bool) ($canDeleteReleasedMaterialDocuments ?? false),
      'documentType' => (string) ($documentType ?? '6400'),
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
  .released-material-documents-wrapper {
    --released-doc-scroll-track: var(--app-scroll-track);
    --released-doc-scroll-thumb: var(--app-scroll-thumb-flat);
    --released-doc-scroll-thumb-hover: var(--app-scroll-thumb-flat-hover);
    --released-doc-scroll-thumb-active: var(--app-scroll-thumb-flat-active);
    --released-doc-scroll-thumb-border: var(--app-scroll-thumb-border);
  }

  .released-material-documents-wrapper .content-header {
    margin-top: -6px;
    margin-bottom: 4px;
  }

  .released-doc-filter-actions {
    row-gap: 8px;
  }

  .released-doc-filter-input {
    font-size: 14px;
  }

  .released-doc-filter-date,
  .released-doc-filter-date.flatpickr-input,
  .released-doc-filter-date + .flatpickr-input,
  .released-doc-filter-date[readonly],
  .flatpickr-input[readonly] {
    cursor: pointer;
  }

  .released-doc-active-filters {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
  }

  .released-doc-active-filter-chip {
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

  .released-doc-active-filter-label {
    color: #6e6b7b;
    font-weight: 500;
  }

  .released-doc-active-filter-value {
    color: #5e5873;
    font-weight: 600;
  }

  .released-doc-table {
    min-width: {{ !empty($canDeleteReleasedMaterialDocuments) ? '1620px' : '1500px' }};
  }

  .released-doc-wrapper .card-datatable.table-responsive {
    overflow-x: hidden;
  }

  .released-doc-wrapper .card-datatable.released-doc-initial-loading .dataTables_wrapper > .row:last-child {
    display: none;
  }

  .released-doc-loading-overlay-host {
    position: relative;
  }

  .released-doc-loading-overlay {
    position: absolute;
    display: none;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.74);
    backdrop-filter: blur(1px);
    z-index: 9;
    pointer-events: none;
  }

  .released-doc-loading-overlay.is-visible {
    display: flex;
  }

  .released-doc-loading-overlay-content {
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.65rem;
    min-width: 220px;
    padding: 0.95rem 1.35rem;
    border-radius: 0.5rem;
    background: #fff;
    box-shadow: 0 6px 20px rgba(34, 41, 47, 0.16);
    text-align: center;
  }

  .released-doc-loading-spinner {
    width: 1.85rem;
    height: 1.85rem;
    border-width: 0.2em;
    color: #495b73;
  }

  .released-doc-loading-message {
    font-size: 0.95rem;
    font-weight: 600;
    color: #5e5873;
    letter-spacing: 0.01em;
  }

  .released-doc-wrapper .dataTables_wrapper > .row:first-child,
  .released-doc-wrapper .dataTables_wrapper > .row:last-child {
    margin-left: 0;
    margin-right: 0;
    padding: 1rem 1rem 0.95rem;
  }

  .released-doc-wrapper .dataTables_wrapper > .row:first-child > [class*='col-'],
  .released-doc-wrapper .dataTables_wrapper > .row:last-child > [class*='col-'] {
    padding-left: 0;
    padding-right: 0;
  }

  .released-doc-wrapper .dataTables_wrapper > .row:nth-child(2) {
    margin-left: 0;
    margin-right: 0;
  }

  .released-doc-wrapper .dataTables_wrapper > .row:nth-child(2) > [class*='col-'] {
    padding-left: 0;
    padding-right: 0;
    overflow: visible;
  }

  .released-doc-wrapper .released-doc-table-body-scroll {
    display: block;
    width: 100%;
    max-width: 100%;
    min-width: 0;
    box-sizing: border-box;
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
    scrollbar-color: var(--released-doc-scroll-thumb) var(--released-doc-scroll-track);
    scrollbar-gutter: stable;
  }

  .released-doc-wrapper .released-doc-table-body-cell {
    flex: 0 0 100%;
    width: 100%;
    max-width: 100%;
    min-width: 0;
    padding-left: 0 !important;
    padding-right: 0 !important;
    overflow: visible !important;
  }

  .released-doc-wrapper .released-doc-table-body-scroll::-webkit-scrollbar {
    width: 8px;
    height: 8px;
  }

  .released-doc-wrapper .released-doc-table-body-scroll::-webkit-scrollbar-track {
    background: var(--released-doc-scroll-track);
    border-radius: 999px;
  }

  .released-doc-wrapper .released-doc-table-body-scroll::-webkit-scrollbar-thumb {
    background: var(--released-doc-scroll-thumb);
    border-radius: 999px;
    border: 1px solid var(--released-doc-scroll-thumb-border);
  }

  .released-doc-wrapper .released-doc-table-body-scroll::-webkit-scrollbar-thumb:hover {
    background: var(--released-doc-scroll-thumb-hover);
  }

  .released-doc-wrapper .released-doc-table-body-scroll::-webkit-scrollbar-thumb:active {
    background: var(--released-doc-scroll-thumb-active);
  }

  .released-doc-table tbody tr {
    transition: background-color 0.2s ease;
  }

  .released-doc-table.table tbody tr:hover > * {
    background-color: #f8f8fc;
  }

  body.dark-layout .released-doc-table.table tbody tr:hover > *,
  body.semi-dark-layout .released-doc-table.table tbody tr:hover > *,
  .dark-layout .released-doc-table.table tbody tr:hover > *,
  .semi-dark-layout .released-doc-table.table tbody tr:hover > * {
    background-color: #36405a !important;
  }

  .released-doc-document-cell,
  .released-doc-work-order-cell,
  .released-doc-order-cell,
  .released-doc-position-cell,
  .released-doc-quantity-cell,
  .released-doc-price-cell,
  .released-doc-action-cell {
    white-space: nowrap;
  }

  .released-doc-document-number {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    font-weight: 700;
    letter-spacing: 0.01em;
  }

  .released-doc-note-cell,
  .released-doc-name-cell {
    min-width: 220px;
  }

  .released-doc-action-cell {
    width: 1% !important;
    position: sticky !important;
    right: 0 !important;
    z-index: 10 !important;
    text-align: center;
    background: #ffffff !important;
    background-color: #ffffff !important;
    background-clip: border-box !important;
    opacity: 1 !important;
    isolation: isolate !important;
    box-shadow: none !important;
    border-left: 1px solid #ebe9f1 !important;
  }

  .released-doc-table thead .released-doc-action-cell {
    z-index: 11 !important;
    background: #f8f8fa !important;
    background-color: #f8f8fa !important;
    box-shadow: none !important;
  }

  .released-doc-table.table tbody tr:hover > .released-doc-action-cell {
    background: #f8f8fc !important;
    background-color: #f8f8fc !important;
    box-shadow: none !important;
  }

  body.dark-layout .released-doc-table .released-doc-action-cell,
  body.semi-dark-layout .released-doc-table .released-doc-action-cell,
  .dark-layout .released-doc-table .released-doc-action-cell,
  .semi-dark-layout .released-doc-table .released-doc-action-cell {
    background: #283046 !important;
    background-color: #283046 !important;
    box-shadow: none !important;
    border-left-color: rgba(184, 190, 220, 0.22) !important;
  }

  body.dark-layout .released-doc-table thead .released-doc-action-cell,
  body.semi-dark-layout .released-doc-table thead .released-doc-action-cell,
  .dark-layout .released-doc-table thead .released-doc-action-cell,
  .semi-dark-layout .released-doc-table thead .released-doc-action-cell {
    background: #2f3854 !important;
    background-color: #2f3854 !important;
    box-shadow: none !important;
  }

  body.dark-layout .released-doc-table.table tbody tr:hover > .released-doc-action-cell,
  body.semi-dark-layout .released-doc-table.table tbody tr:hover > .released-doc-action-cell,
  .dark-layout .released-doc-table.table tbody tr:hover > .released-doc-action-cell,
  .semi-dark-layout .released-doc-table.table tbody tr:hover > .released-doc-action-cell {
    background: #36405a !important;
    background-color: #36405a !important;
    box-shadow: none !important;
  }

  .released-doc-actions-group {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
  }

  .released-doc-action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    min-width: 38px;
    height: 38px;
    padding: 0.45rem;
    white-space: nowrap;
  }

  .released-doc-action-btn svg,
  .released-doc-action-btn i {
    width: 16px;
    height: 16px;
  }

  .released-doc-loading-spacer-row > td {
    height: 220px;
    padding: 0 !important;
    border-top: 0 !important;
    border-bottom: 0 !important;
    background: transparent !important;
  }

  .released-doc-wrapper .dataTables_processing {
    display: none !important;
  }

  body.dark-layout .released-doc-loading-overlay,
  body.semi-dark-layout .released-doc-loading-overlay,
  .dark-layout .released-doc-loading-overlay,
  .semi-dark-layout .released-doc-loading-overlay {
    background: rgba(34, 41, 47, 0.58);
  }

  body.dark-layout .released-doc-loading-overlay-content,
  body.semi-dark-layout .released-doc-loading-overlay-content,
  .dark-layout .released-doc-loading-overlay-content,
  .semi-dark-layout .released-doc-loading-overlay-content {
    background: #283046;
    box-shadow: 0 10px 24px rgba(0, 0, 0, 0.22);
  }

  body.dark-layout .released-doc-loading-spinner,
  body.semi-dark-layout .released-doc-loading-spinner,
  .dark-layout .released-doc-loading-spinner,
  .semi-dark-layout .released-doc-loading-spinner {
    color: #d0d4f2;
  }

  body.dark-layout .released-doc-loading-message,
  body.semi-dark-layout .released-doc-loading-message,
  .dark-layout .released-doc-loading-message,
  .semi-dark-layout .released-doc-loading-message {
    color: #f0f0f0;
  }

  @media (max-width: 767.98px) {
    .released-doc-filter-actions {
      align-items: flex-start !important;
    }
  }
</style>
@endsection

@section('content')
<section class="released-material-documents-wrapper">
  <div class="content-header row">
    <div class="content-header-left col-12 mb-2">
        <div class="row breadcrumbs-top">
        <div class="col-12">
          <h2 class="content-header-title float-start mb-0">Razduženi materijali</h2>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-2">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <h4 class="mb-0">Filter dokumenata</h4>
        <small class="text-muted">Dokumenti tipa {{ $releasedMaterialsConfig['documentType'] }} - RN Rasknjiženje materijala</small>
      </div>
      <div class="d-flex align-items-center flex-wrap gap-2 released-doc-filter-actions">
        <div id="released-doc-active-filters" class="released-doc-active-filters d-none"></div>
        <button type="button" class="btn btn-outline-primary btn-sm" id="released-doc-toggle-filters" aria-expanded="false">
          <i data-feather="filter" class="me-50"></i> Prikaži filtere
        </button>
        <button type="button" class="btn btn-outline-danger btn-sm" id="released-doc-clear-filters">
          <i data-feather="trash-2" class="me-50"></i> Obriši filter
        </button>
      </div>
    </div>
    <div class="card-body d-none" id="released-doc-filters-body">
      <div class="row g-2 mb-2">
        <div class="col-md-3">
          <label class="form-label">Dokument</label>
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i data-feather="file-text"></i></span>
            <input type="text" class="form-control released-doc-filter-input" id="filter-dokument" placeholder="26-6400-...">
          </div>
        </div>
        <div class="col-md-3">
          <label class="form-label">RN</label>
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i data-feather="search"></i></span>
            <input type="text" class="form-control released-doc-filter-input" id="filter-predracun" placeholder="26-6000-0001687">
          </div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Narudžba</label>
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i data-feather="briefcase"></i></span>
            <input type="text" class="form-control released-doc-filter-input" id="filter-narudzba" placeholder="25-0110-0003084">
          </div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Šifra</label>
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i data-feather="hash"></i></span>
            <input type="text" class="form-control released-doc-filter-input" id="filter-sifra" placeholder="Šifra materijala">
          </div>
        </div>
      </div>
      <div class="row g-2 mb-2">
        <div class="col-md-3">
          <label class="form-label">Naziv</label>
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i data-feather="search"></i></span>
            <input type="text" class="form-control released-doc-filter-input" id="filter-naziv" placeholder="Naziv materijala">
          </div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Datum od</label>
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i data-feather="calendar"></i></span>
            <input type="text" class="form-control released-doc-filter-input released-doc-filter-date" id="filter-datum-od" placeholder="dd.mm.yyyy" autocomplete="off">
          </div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Datum do</label>
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i data-feather="calendar"></i></span>
            <input type="text" class="form-control released-doc-filter-input released-doc-filter-date" id="filter-datum-do" placeholder="dd.mm.yyyy" autocomplete="off">
          </div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Napomena</label>
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i data-feather="message-square"></i></span>
            <input type="text" class="form-control released-doc-filter-input" id="filter-napomena" placeholder="Napomena">
          </div>
        </div>
      </div>
      <div class="row g-2">
        <div class="col-md-3 ms-auto d-flex align-items-end">
          <button type="button" class="btn btn-primary w-100" id="released-doc-apply-filters">
            <i data-feather="filter" class="me-50"></i> Filter
          </button>
        </div>
      </div>
    </div>
  </div>

  <div class="card released-doc-wrapper">
    <div class="alert alert-danger mb-0 d-none" id="released-doc-page-error"></div>
    <div class="card-datatable table-responsive released-doc-initial-loading">
      <table class="table released-doc-table" id="released-doc-table">
        <thead>
          <tr>
            <th>Dokument</th>
            <th>Datum</th>
            <th>RN</th>
            <th>Narudžba</th>
            <th>Pozicija</th>
            <th>Šifra</th>
            <th>Naziv</th>
            <th>Količina</th>
            <th>JM</th>
            <th>Cijena</th>
            <th>Napomena</th>
            @if(!empty($canDeleteReleasedMaterialDocuments))
              <th class="released-doc-action-cell">Akcija</th>
            @endif
          </tr>
        </thead>
        <tbody>
          <tr class="released-doc-loading-spacer-row" aria-hidden="true">
            <td colspan="{{ !empty($canDeleteReleasedMaterialDocuments) ? 12 : 11 }}"></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</section>
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
  window.releasedMaterialsConfig = @json($releasedMaterialsConfig);
</script>
<script src="{{ asset('js/scripts/pages/app-released-material-documents.js?v=6') }}"></script>
@endsection
