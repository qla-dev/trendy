@extends('layouts/contentLayoutMaster')

@section('title', 'Lista Radnih Naloga')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('vendors/css/tables/datatable/dataTables.bootstrap5.min.css')}}">
<link rel="stylesheet" href="{{asset('vendors/css/tables/datatable/extensions/dataTables.checkboxes.css')}}">
<link rel="stylesheet" href="{{asset('vendors/css/tables/datatable/responsive.bootstrap5.min.css')}}">
@endsection

@section('page-style')
<link rel="stylesheet" href="{{asset('css/base/pages/app-invoice-list.css')}}">
<style>
  .content-header {
    margin-top: -6px;
    margin-bottom: 4px;
  }
  .content-header-title {margin-top: 5px;}
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
        <button type="button" class="btn btn-primary" id="btn-add-radni-nalog">
          <i data-feather="plus" class="me-50"></i> Dodaj radni nalog
        </button>
      </div>
    </div>
  </div>

  <!-- Status Cards Section -->
  <div class="row mb-2">
    <div class="col-12">
      <div class="d-flex flex-wrap gap-2 status-cards-wrapper">
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
        <div class="status-card" data-status="raspisan">
          <div class="status-card-body">
            <div class="status-label">Raspisan</div>
            <div class="status-count">{{ $statusStats['raspisan'] ?? 0 }}</div>
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
            <div class="status-label">Djelimično zak...</div>
            <div class="status-count">{{ $statusStats['djelimicno_zakljucen'] ?? 0 }}</div>
          </div>
        </div>
        <div class="status-card status-card-active" data-status="zakljucen">
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
      <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-add-filter">
          <i data-feather="plus" class="me-50"></i> Dodaj filter
        </button>
        <button type="button" class="btn btn-outline-danger btn-sm" id="btn-delete-filter">
          <i data-feather="trash-2" class="me-50"></i> Obriši filter
        </button>
        <button type="button" class="btn btn-outline-success btn-sm" id="btn-save-filter">
          <i data-feather="save" class="me-50"></i> Sačuvaj
        </button>
      </div>
    </div>
    <div class="card-body">
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
            <input type="date" class="form-control filter-input" id="filter-plan-pocetak-od">
          </div>
        </div>
      </div>
      <div class="row g-2 mb-2">
        <div class="col-md-3">
          <label class="form-label">Plan. početak do</label>
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i data-feather="calendar"></i></span>
            <input type="date" class="form-control filter-input" id="filter-plan-pocetak-do">
          </div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Plan. kraj od</label>
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i data-feather="calendar"></i></span>
            <input type="date" class="form-control filter-input" id="filter-plan-kraj-od">
          </div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Plan. kraj do</label>
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i data-feather="calendar"></i></span>
            <input type="date" class="form-control filter-input" id="filter-plan-kraj-do">
          </div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Datum od</label>
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i data-feather="calendar"></i></span>
            <input type="date" class="form-control filter-input" id="filter-datum-od">
          </div>
        </div>
      </div>
      <div class="row g-2 mb-2">
        <div class="col-md-3">
          <label class="form-label">RN datum do</label>
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i data-feather="calendar"></i></span>
            <input type="date" class="form-control filter-input" id="filter-datum-do">
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
            <th></th>
            <th>#</th>
            <th><i data-feather="trending-up"></i></th>
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
    flex-wrap: wrap;
    gap: 12px;
  }

  .status-card {
    flex: 1;
    min-width: 140px;
    max-width: 200px;
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
    border-color: #7367f0;
    background-color: #fff;
    box-shadow: 0 4px 24px 0 rgba(34, 41, 47, 0.1);
  }

  .status-card-active:hover {
    border-color: #7367f0;
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
    border-color: #7367f0;
  }

  .status-card[data-status="raspisan"]:hover {
    border-color: #7367f0;
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
    color: #7367f0;
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

  .filter-input {
    font-size: 14px;
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

  div.dataTables_wrapper div.dataTables_filter {
    text-align: right;
    float: right;
  }

  div.dataTables_wrapper div.dataTables_filter label {
    margin-top: 0;
    margin-bottom: 0;
    text-align: right;
    float: right;
  }

  div.dataTables_wrapper div.dataTables_filter input {
    text-align: left;
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
@endsection

@section('page-script')
<script src="{{asset('js/scripts/pages/app-invoice-list.js')}}"></script>
@endsection
