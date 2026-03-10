@extends('layouts/contentLayoutMaster')

@section('title', 'Pregled zaliha')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('vendors/css/tables/datatable/dataTables.bootstrap5.min.css')}}">
<link rel="stylesheet" href="{{asset('vendors/css/tables/datatable/responsive.bootstrap5.min.css')}}">
<link rel="stylesheet" href="{{asset('vendors/css/extensions/sweetalert2.min.css')}}">
@endsection

@section('page-style')
@php
  $currentUser = auth()->user();
  $isAdminUser = $currentUser
      ? (method_exists($currentUser, 'isAdmin')
          ? (bool) $currentUser->isAdmin()
          : strtolower((string) ($currentUser->role ?? '')) === 'admin')
      : false;
  $canSeeMaterialWarehouse = $isAdminUser;
  $canAdjustMaterialStock = $isAdminUser;
  $canCreateMaterial = (bool) ($canCreateMaterial ?? false);
  $canDeleteMaterial = (bool) ($canDeleteMaterial ?? false);
  $canCopyMaterial = $canCreateMaterial;
  $canManageMaterialActions = $canAdjustMaterialStock || $canCopyMaterial || $canDeleteMaterial;
  $materialBarcodeGeneratorConfig = [
      'dataUrl' => $stockTableUrl ?? route('app-stock-data'),
      'stockAdjustUrl' => route('app-materials-stock-bulk-adjust'),
      'createUrl' => $stockCreateUrl ?? route('app-materials-store'),
      'deleteUrl' => $stockDeleteUrl ?? route('app-materials-destroy'),
      'canSeeWarehouse' => $canSeeMaterialWarehouse,
      'canAdjustStock' => $canAdjustMaterialStock,
      'canCreateMaterial' => $canCreateMaterial,
      'canCopyMaterial' => $canCopyMaterial,
      'canDeleteMaterial' => $canDeleteMaterial,
      'autoOpenCreateMaterial' => (bool) ($shouldAutoOpenCreateMaterial ?? false),
      'defaultMaterialSet' => (string) ($defaultMaterialSet ?? '011'),
  ];
@endphp
<style>
  .content-header {
    margin-top: -6px;
    margin-bottom: 4px;
  }

  .content-header-title {
    margin-top: 5px;
  }

  .material-header-actions {
    display: inline-flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.75rem;
    flex-wrap: nowrap;
    width: 100%;
  }

  .material-warehouse-filter {
    min-width: 260px;
    flex: 1 1 260px;
  }

  .material-barcode-generator-wrapper {
    --wo-table-scroll-track: var(--app-scroll-track);
    --wo-table-scroll-thumb: var(--app-scroll-thumb-flat);
    --wo-table-scroll-thumb-hover: var(--app-scroll-thumb-flat-hover);
    --wo-table-scroll-thumb-active: var(--app-scroll-thumb-flat-active);
    --wo-table-scroll-thumb-border: var(--app-scroll-thumb-border);
  }

  body.dark-layout .material-barcode-generator-wrapper,
  body.semi-dark-layout .material-barcode-generator-wrapper,
  .dark-layout .material-barcode-generator-wrapper,
  .semi-dark-layout .material-barcode-generator-wrapper {
    --wo-table-scroll-track: var(--app-scroll-track);
    --wo-table-scroll-thumb: var(--app-scroll-thumb-flat);
    --wo-table-scroll-thumb-hover: var(--app-scroll-thumb-flat-hover);
    --wo-table-scroll-thumb-active: var(--app-scroll-thumb-flat-active);
    --wo-table-scroll-thumb-border: var(--app-scroll-thumb-border);
  }

  .material-barcode-table tbody tr {
    cursor: pointer;
    transition: background-color 0.2s ease;
  }

  .material-barcode-table.table tbody tr:hover > * {
    background-color: #f8f8fc;
  }

  .material-barcode-generator-wrapper .material-barcode-table {
    min-width: {{ $canManageMaterialActions ? '1140px' : ($canSeeMaterialWarehouse ? '860px' : '760px') }};
  }

  .material-barcode-table tbody .material-barcode-loading-spacer-row > td {
    height: 180px;
    padding: 0 !important;
    border-top: 0 !important;
    border-bottom: 0 !important;
    background: transparent !important;
  }

  .material-barcode-generator-wrapper .card-datatable.table-responsive {
    overflow-x: visible;
  }

  .material-barcode-generator-wrapper .card-datatable .dataTables_wrapper > .row:first-child,
  .material-barcode-generator-wrapper .card-datatable .dataTables_wrapper > .row:last-child {
    margin-left: 0;
    margin-right: 0;
    padding: 1rem 1rem 0.95rem;
  }

  .material-barcode-generator-wrapper .card-datatable.material-barcode-initial-loading .dataTables_wrapper > .row:last-child {
    display: none;
  }

  .material-barcode-generator-wrapper .card-datatable .dataTables_wrapper > .row:first-child > [class*='col-'],
  .material-barcode-generator-wrapper .card-datatable .dataTables_wrapper > .row:last-child > [class*='col-'] {
    padding-left: 0;
    padding-right: 0;
  }

  .material-barcode-generator-wrapper .card-datatable .dataTables_wrapper > .row:nth-child(2) {
    margin-left: 0;
    margin-right: 0;
  }

  .material-barcode-generator-wrapper .card-datatable .dataTables_wrapper > .row:nth-child(2) > [class*='col-'] {
    padding-left: 0;
    padding-right: 0;
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
    scrollbar-color: var(--wo-table-scroll-thumb) var(--wo-table-scroll-track);
    scrollbar-gutter: stable;
  }

  .material-barcode-generator-wrapper .card-datatable .dataTables_wrapper > .row:nth-child(2) > [class*='col-']::-webkit-scrollbar {
    width: 8px;
    height: 8px;
  }

  .material-barcode-generator-wrapper .card-datatable .dataTables_wrapper > .row:nth-child(2) > [class*='col-']::-webkit-scrollbar-track {
    background: var(--wo-table-scroll-track);
    border-radius: 999px;
  }

  .material-barcode-generator-wrapper .card-datatable .dataTables_wrapper > .row:nth-child(2) > [class*='col-']::-webkit-scrollbar-thumb {
    background: var(--wo-table-scroll-thumb);
    border-radius: 999px;
    border: 1px solid var(--wo-table-scroll-thumb-border);
  }

  .material-barcode-generator-wrapper .card-datatable .dataTables_wrapper > .row:nth-child(2) > [class*='col-']::-webkit-scrollbar-thumb:hover {
    background: var(--wo-table-scroll-thumb-hover);
  }

  .material-barcode-generator-wrapper .card-datatable .dataTables_wrapper > .row:nth-child(2) > [class*='col-']::-webkit-scrollbar-thumb:active {
    background: var(--wo-table-scroll-thumb-active);
  }

  .material-barcode-generator-wrapper .card-datatable .dataTables_wrapper > .row:nth-child(2) > [class*='col-']::-webkit-scrollbar-corner {
    background: var(--wo-table-scroll-track);
  }

  .material-barcode-generator-modal {
    --barcode-modal-preview-bg: #ffffff;
    --barcode-modal-preview-border: rgba(71, 95, 123, 0.12);
    --barcode-modal-text-muted: #6e6b7b;
  }

  body.dark-layout .material-barcode-generator-modal,
  body.semi-dark-layout .material-barcode-generator-modal,
  .dark-layout .material-barcode-generator-modal,
  .semi-dark-layout .material-barcode-generator-modal {
    --barcode-modal-preview-border: rgba(214, 220, 236, 0.14);
    --barcode-modal-text-muted: #b4bdd3;
  }

  body.dark-layout .material-barcode-table.table tbody tr:hover > *,
  body.semi-dark-layout .material-barcode-table.table tbody tr:hover > *,
  .dark-layout .material-barcode-table.table tbody tr:hover > *,
  .semi-dark-layout .material-barcode-table.table tbody tr:hover > * {
    background-color: #36405a !important;
  }

  .material-barcode-modal-name {
    font-size: 1.15rem;
    font-weight: 700;
    margin-bottom: 0.35rem;
  }

  .material-barcode-modal-code {
    font-size: 0.92rem;
    color: var(--barcode-modal-text-muted);
    margin-bottom: 1rem;
  }

  .material-barcode-modal-preview {
    min-height: 260px;
    padding: 1rem;
    border-radius: 0.85rem;
    border: 1px solid var(--barcode-modal-preview-border);
    background: var(--barcode-modal-preview-bg);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
  }

  .material-barcode-modal-preview svg {
    width: 100%;
    height: auto;
    max-width: 760px;
    display: block;
  }

  .material-barcode-modal-empty {
    text-align: center;
    color: var(--barcode-modal-text-muted);
    font-size: 0.95rem;
    font-weight: 500;
  }

  .material-barcode-table .material-code-cell {
    font-weight: 700;
    letter-spacing: 0.01em;
  }

  .material-barcode-table .material-stock-cell,
  .material-barcode-table .material-unit-cell,
  .material-barcode-table .material-warehouse-cell,
  .material-barcode-table .material-actions-cell {
    white-space: nowrap;
  }

  .material-barcode-table .material-actions-cell {
    width: 1%;
  }

  .material-stock-adjust-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
  }

  .material-actions-group {
    display: inline-flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.3rem;
    flex-wrap: nowrap;
  }

  .material-action-btn {
    min-width: 85px;
  }

  .material-action-btn.material-barcode-preview-btn {
    border-color: #1e88e5 !important;
    color: #1e88e5 !important;
    background-color: transparent !important;
  }

  .material-action-btn.material-barcode-preview-btn:hover {
    background-color: rgba(30, 136, 229, 0.1) !important;
  }

  .material-action-btn.material-stock-adjust-btn {
    border-color: #28c76f !important;
    color: #28c76f !important;
    background-color: transparent !important;
  }

  .material-action-btn.material-stock-adjust-btn:hover {
    background-color: rgba(40, 199, 111, 0.1) !important;
  }

  .material-action-btn.material-copy-btn {
    border-color: #6e6b7b !important;
    color: #6e6b7b !important;
    background-color: transparent !important;
  }

  .material-action-btn.material-copy-btn:hover {
    background-color: rgba(110, 107, 123, 0.1) !important;
  }

  .material-action-btn.material-delete-btn {
    border-color: #ea5455 !important;
    color: #ea5455 !important;
    background-color: transparent !important;
  }

  .material-action-btn.material-delete-btn:hover {
    background-color: rgba(234, 84, 85, 0.1) !important;
  }

  .material-actions-group {
    gap: 0.65rem;
  }

  .material-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    white-space: nowrap;
  }

  .material-stock-modal .modal-content {
    border-radius: 1rem;
  }

  .material-stock-surface-card,
  .material-create-field-card {
    padding: 0.95rem 1rem;
    border-radius: 0.85rem;
    border: 1px solid rgba(71, 95, 123, 0.12);
    background: rgba(71, 95, 123, 0.04);
  }

  .material-stock-modal-subtitle {
    margin-top: 0.2rem;
    font-size: 0.9rem;
    color: #6e6b7b;
  }

  .material-stock-meta-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.9rem;
    margin-bottom: 1rem;
  }

  .material-stock-meta-item {
    padding: 0;
    border: 0;
    background: transparent;
  }

  .material-stock-meta-label {
    display: block;
    margin-bottom: 0.3rem;
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #6e6b7b;
  }

  .material-stock-meta-value {
    display: block;
    font-size: 1rem;
    font-weight: 700;
    line-height: 1.35;
    word-break: break-word;
  }

  .material-stock-form-block {
    border-top: 1px solid rgba(71, 95, 123, 0.12);
    padding-top: 1rem;
  }

  .material-create-modal .modal-content {
    border-radius: 1rem;
  }

  .material-create-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.9rem;
  }

  .material-create-field-card .form-label {
    margin-bottom: 0.45rem;
    display: block;
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #6e6b7b;
  }

  .material-create-field-card .form-control {
    background-color: transparent;
  }

  .material-create-field-card .form-select {
    background-color: transparent;
    background-repeat: no-repeat;
    background-position: right 0.9rem center;
    background-size: 16px 12px;
    cursor: pointer;
  }

  .material-create-field-card .form-select option {
    cursor: pointer;
  }

  .material-create-help {
    margin-top: 0.9rem;
    font-size: 0.88rem;
    color: #6e6b7b;
  }

  .material-stock-form-help {
    margin-top: 0.45rem;
    font-size: 0.88rem;
    color: #6e6b7b;
  }

  .material-barcode-generator-wrapper .card-datatable {
    position: relative;
    scrollbar-width: thin;
    scrollbar-color: var(--wo-table-scroll-thumb) var(--wo-table-scroll-track);
    scrollbar-gutter: stable;
  }

  .material-barcode-generator-wrapper .card-datatable::-webkit-scrollbar {
    width: 8px;
    height: 8px;
  }

  .material-barcode-generator-wrapper .card-datatable::-webkit-scrollbar-track {
    background: var(--wo-table-scroll-track);
    border-radius: 999px;
  }

  .material-barcode-generator-wrapper .card-datatable::-webkit-scrollbar-thumb {
    background: var(--wo-table-scroll-thumb);
    border-radius: 999px;
    border: 1px solid var(--wo-table-scroll-thumb-border);
  }

  .material-barcode-generator-wrapper .card-datatable::-webkit-scrollbar-thumb:hover {
    background: var(--wo-table-scroll-thumb-hover);
  }

  .material-barcode-generator-wrapper .card-datatable::-webkit-scrollbar-thumb:active {
    background: var(--wo-table-scroll-thumb-active);
  }

  .material-barcode-generator-wrapper .card-datatable::-webkit-scrollbar-corner {
    background: var(--wo-table-scroll-track);
  }

  .material-barcode-generator-wrapper .invoice-table-overlay-host {
    position: relative;
  }

  .material-barcode-generator-wrapper .invoice-table-loading-overlay {
    position: absolute;
    display: none;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.74);
    backdrop-filter: blur(1px);
    z-index: 9;
    pointer-events: all;
  }

  .material-barcode-generator-wrapper .invoice-table-loading-overlay.is-visible {
    display: flex;
  }

  .material-barcode-generator-wrapper .invoice-table-loading-overlay-content {
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.65rem;
    text-align: center;
  }

  .material-barcode-generator-wrapper .invoice-table-loading-spinner {
    width: 2rem;
    height: 2rem;
    border-width: 0.2em;
    color: #495b73;
  }

  .material-barcode-generator-wrapper .invoice-table-loading-message {
    font-size: 0.95rem;
    font-weight: 600;
    color: #5e5873;
    letter-spacing: 0.01em;
  }

  body.dark-layout .material-barcode-generator-wrapper .invoice-table-loading-overlay,
  body.semi-dark-layout .material-barcode-generator-wrapper .invoice-table-loading-overlay,
  .dark-layout .material-barcode-generator-wrapper .invoice-table-loading-overlay,
  .semi-dark-layout .material-barcode-generator-wrapper .invoice-table-loading-overlay {
    background: rgba(20, 28, 48, 0.68);
  }

  body.dark-layout .material-barcode-generator-wrapper .invoice-table-loading-spinner,
  body.semi-dark-layout .material-barcode-generator-wrapper .invoice-table-loading-spinner,
  .dark-layout .material-barcode-generator-wrapper .invoice-table-loading-spinner,
  .semi-dark-layout .material-barcode-generator-wrapper .invoice-table-loading-spinner {
    color: #d6dcec;
  }

  body.dark-layout .material-barcode-generator-wrapper .invoice-table-loading-message,
  body.semi-dark-layout .material-barcode-generator-wrapper .invoice-table-loading-message,
  .dark-layout .material-barcode-generator-wrapper .invoice-table-loading-message,
  .semi-dark-layout .material-barcode-generator-wrapper .invoice-table-loading-message {
    color: #f4f5fb;
  }

  body.dark-layout .material-stock-modal-subtitle,
  body.semi-dark-layout .material-stock-modal-subtitle,
  .dark-layout .material-stock-modal-subtitle,
  .semi-dark-layout .material-stock-modal-subtitle,
  body.dark-layout .material-stock-meta-label,
  body.semi-dark-layout .material-stock-meta-label,
  .dark-layout .material-stock-meta-label,
  .semi-dark-layout .material-stock-meta-label,
  body.dark-layout .material-stock-form-help,
  body.semi-dark-layout .material-stock-form-help,
  .dark-layout .material-stock-form-help,
  .semi-dark-layout .material-stock-form-help {
    color: #b4bdd3;
  }

  body.dark-layout .material-stock-meta-item,
  body.semi-dark-layout .material-stock-meta-item,
  .dark-layout .material-stock-meta-item,
  .semi-dark-layout .material-stock-meta-item {
    background: transparent;
  }

  body.dark-layout .material-stock-form-block,
  body.semi-dark-layout .material-stock-form-block,
  .dark-layout .material-stock-form-block,
  .semi-dark-layout .material-stock-form-block {
    border-top-color: rgba(214, 220, 236, 0.14);
  }

  body.dark-layout .material-stock-surface-card,
  body.semi-dark-layout .material-stock-surface-card,
  .dark-layout .material-stock-surface-card,
  .semi-dark-layout .material-stock-surface-card,
  body.dark-layout .material-create-field-card,
  body.semi-dark-layout .material-create-field-card,
  .dark-layout .material-create-field-card,
  .semi-dark-layout .material-create-field-card {
    border-color: rgba(214, 220, 236, 0.12);
    background: rgba(214, 220, 236, 0.05);
  }

  body.dark-layout .material-create-field-card .form-label,
  body.semi-dark-layout .material-create-field-card .form-label,
  .dark-layout .material-create-field-card .form-label,
  .semi-dark-layout .material-create-field-card .form-label,
  body.dark-layout .material-create-help,
  body.semi-dark-layout .material-create-help,
  .dark-layout .material-create-help,
  .semi-dark-layout .material-create-help {
    color: #b4bdd3;
  }

  @media (max-width: 767.98px) {
    .material-header-actions {
      width: 100%;
      justify-content: flex-start;
    }

    .material-warehouse-filter {
      min-width: 0;
      width: 100%;
    }

    .material-barcode-generator-wrapper .card-datatable .dataTables_wrapper > .row:first-child,
    .material-barcode-generator-wrapper .card-datatable .dataTables_wrapper > .row:last-child {
      padding: 0.85rem 0.85rem 0.8rem;
    }

    .material-barcode-modal-preview {
      min-height: 210px;
      padding: 0.8rem;
    }

    .material-stock-meta-grid {
      grid-template-columns: 1fr;
    }

    .material-create-form-grid {
      grid-template-columns: 1fr;
    }
  }
</style>
@endsection

@section('content')
<section class="material-barcode-generator-wrapper">
  <div class="content-header row">
    <div class="content-header-left col-md-3 col-lg-6 col-12 mb-2">
      <div class="row breadcrumbs-top">
        <div class="col-12">
          <h2 class="content-header-title float-start mb-0">Pregled zaliha</h2>
        </div>
      </div>
    </div>
    <div class="content-header-right text-md-end col-md-9 col-lg-6 col-12">
      @if(!empty($stockWarehouseOptions) || $canCreateMaterial)
        <div class="mb-1 breadcrumb-right">
          <div class="material-header-actions">
            @if(!empty($stockWarehouseOptions))
              <div class="material-warehouse-filter">
                <select class="form-select" id="material-warehouse-filter">
                  <option value="">Filtriraj po skladistu</option>
                  @foreach(($stockWarehouseOptions ?? []) as $warehouseName)
                    <option value="{{ $warehouseName }}">{{ $warehouseName }}</option>
                  @endforeach
                </select>
              </div>
            @endif
            @if($canCreateMaterial)
              <button type="button" class="btn btn-primary" id="material-create-open-btn">
                <i data-feather="plus" class="me-50"></i> Dodaj novi materijal
              </button>
            @endif
          </div>
        </div>
      @endif
    </div>
  </div>

  <div class="card">
    @if(isset($error))
      <div class="alert alert-danger mb-0">
        {{ $error }}
      </div>
    @endif

    <div class="card-datatable table-responsive material-barcode-initial-loading">
      <table class="table material-barcode-table" id="material-barcode-table">
        <thead>
          <tr>
            <th>Šifra</th>
            <th>Naziv</th>
            <th>MJ</th>
            @if($canSeeMaterialWarehouse)
              <th>Skladište</th>
            @endif
            <th>Zaliha</th>
            @if($canManageMaterialActions)
              <th>Akcija</th>
            @endif
          </tr>
        </thead>
        <tbody>
          <tr class="material-barcode-loading-spacer-row" aria-hidden="true">
            <td colspan="{{ $canManageMaterialActions ? 6 : ($canSeeMaterialWarehouse ? 5 : 4) }}"></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</section>

<div class="modal fade material-barcode-generator-modal" id="material-barcode-modal" tabindex="-1" aria-labelledby="material-barcode-modal-label" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0" id="material-barcode-modal-label">Barcode etiketa materijala</h5>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zatvori"></button>
      </div>
      <div class="modal-body">
        <div class="material-barcode-modal-name" id="material-barcode-modal-name">-</div>
        <div class="material-barcode-modal-code" id="material-barcode-modal-code">-</div>
        <div class="alert alert-danger d-none" id="material-barcode-modal-error"></div>
        <div class="material-barcode-modal-preview" id="material-barcode-modal-preview">
          <div class="material-barcode-modal-empty">Kliknite materijal u tabeli za pregled barcode etikete.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Zatvori</button>
        <button type="button" class="btn btn-primary" id="material-barcode-download-btn" disabled>
          <i class="fa fa-download me-50"></i> Preuzmi SVG
        </button>
      </div>
    </div>
  </div>
</div>
@if($canAdjustMaterialStock)
  @include('content.new-components.confirm-stock')
@endif
@if($canCreateMaterial)
  @include('content.new-components.create-stock-material')
@endif
@endsection

@section('vendor-script')
<script src="{{asset('vendors/js/tables/datatable/jquery.dataTables.min.js')}}"></script>
<script src="{{asset('vendors/js/tables/datatable/dataTables.bootstrap5.min.js')}}"></script>
<script src="{{asset('vendors/js/tables/datatable/dataTables.responsive.min.js')}}"></script>
<script src="{{asset('vendors/js/tables/datatable/responsive.bootstrap5.js')}}"></script>
<script src="{{asset('vendors/js/extensions/sweetalert2.all.min.js')}}"></script>
@endsection

@section('page-script')
<script>
  window.materialBarcodeGeneratorConfig = @json($materialBarcodeGeneratorConfig);
</script>
<script src="{{asset('js/scripts/pages/app-material.js?v=10')}}"></script>
@endsection
