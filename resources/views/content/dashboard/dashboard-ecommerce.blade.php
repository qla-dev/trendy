@extends('layouts/contentLayoutMaster')

@section('title', 'Kontrolna ploča')

@section('vendor-style')
  {{-- vendor css files --}}
  <link rel="stylesheet" href="{{ asset(mix('vendors/css/charts/apexcharts.css')) }}">
  <link rel="stylesheet" href="{{ asset(mix('vendors/css/extensions/toastr.min.css')) }}">
@endsection
@section('page-style')
  {{-- Page css files --}}
  <link rel="stylesheet" href="{{ asset(mix('css/base/pages/dashboard-ecommerce.css')) }}">
  <link rel="stylesheet" href="{{ asset(mix('css/base/plugins/charts/chart-apex.css')) }}">
  <link rel="stylesheet" href="{{ asset(mix('css/base/plugins/extensions/ext-component-toastr.css')) }}">
  <style>

    .dark-layout a:hover {
      color: unset!important;
    }
    .dashboard-workorders-card .table {
      margin-bottom: 0;
    }

    .dashboard-workorders-scroll {
      overflow-x: auto;
      overflow-y: hidden;
      scrollbar-width: thin;
      scrollbar-color: rgba(132, 142, 168, 0.72) rgba(228, 232, 241, 0.8);
      scrollbar-gutter: stable;
    }

    .dashboard-workorders-scroll::-webkit-scrollbar {
      height: 11px;
    }

    .dashboard-workorders-scroll::-webkit-scrollbar-track {
      background: rgba(228, 232, 241, 0.8);
      border-radius: 999px;
    }

    .dashboard-workorders-scroll::-webkit-scrollbar-thumb {
      background: linear-gradient(180deg, rgba(130, 141, 168, 0.95) 0%, rgba(108, 119, 147, 0.95) 100%);
      border-radius: 999px;
      border: 2px solid rgba(228, 232, 241, 0.95);
      min-width: 28px;
    }

    .dashboard-workorders-scroll::-webkit-scrollbar-thumb:hover {
      background: linear-gradient(180deg, rgba(113, 126, 156, 1) 0%, rgba(94, 108, 139, 1) 100%);
    }

    .dashboard-workorders-table thead th {
      background-color: #f8f8fc;
      border-bottom: 1px solid #ebe9f1;
      color: #6e6b7b;
      font-size: 1rem;
      font-weight: 700;
      letter-spacing: 0.02em;
      text-transform: uppercase;
      white-space: nowrap;
    }

    .dashboard-workorders-table > :not(caption) > * > * {
      box-shadow: none !important;
    }

    .dashboard-report-chart-shell {
      position: relative;
      min-height: 230px;
    }

    .dashboard-report-loader {
      position: absolute;
      inset: 0;
      z-index: 5;
      display: flex;
      align-items: center;
      justify-content: center;
      background: rgba(255, 255, 255, 0.78);
      backdrop-filter: blur(1px);
      transition: opacity 0.18s ease, visibility 0.18s ease;
    }

    .dashboard-report-loader.is-hidden {
      opacity: 0;
      visibility: hidden;
      pointer-events: none;
    }

    .dashboard-report-chart-shell #revenue-report-chart .apexcharts-tooltip {
      display: none !important;
    }

    .dashboard-report-hover-box {
      position: absolute;
      z-index: 7;
      min-width: 176px;
      max-width: 240px;
      padding: 0.55rem 0.65rem;
      border-radius: 10px;
      border: 1px solid #ebe9f1;
      background: rgba(255, 255, 255, 0.97);
      color: #5e5873;
      box-shadow: 0 10px 24px rgba(34, 41, 47, 0.22);
      pointer-events: none;
      transition: opacity 0.12s ease, visibility 0.12s ease;
    }

    .dashboard-report-hover-box.is-hidden {
      opacity: 0;
      visibility: hidden;
    }

    .dashboard-report-hover-title {
      font-size: 0.86rem;
      font-weight: 700;
      margin-bottom: 0.4rem;
      color: inherit;
    }

    .dashboard-report-hover-row {
      display: flex;
      align-items: center;
      gap: 0.35rem;
      font-size: 0.78rem;
      line-height: 1.35;
    }

    .dashboard-report-hover-row + .dashboard-report-hover-row {
      margin-top: 0.22rem;
    }

    .dashboard-report-hover-dot {
      width: 8px;
      height: 8px;
      border-radius: 999px;
      flex: 0 0 auto;
    }

    .dashboard-report-hover-dot-current {
      background: #ff9f43;
    }

    .dashboard-report-hover-dot-compare {
      background: #b9b9c3;
    }

    .dashboard-report-hover-label {
      color: #6e6b7b;
      font-weight: 600;
    }

    .dashboard-report-hover-value {
      margin-left: auto;
      color: inherit;
      font-weight: 700;
      text-align: right;
    }

    .dashboard-workorders-table > :not(:first-child) {
      border-top: 0 !important;
    }

    .dashboard-workorders-table tbody td {
      border-top: 1px solid #ebe9f1;
      vertical-align: middle;
      color: #5e5873;
      font-size: 1.1rem;
      font-weight: 500;
    }

    .dashboard-workorders-table tbody tr:first-child td {
      border-top: 0;
    }

    .dashboard-workorders-table.table-hover tbody tr:hover > * {
      background-color: #f8f8fc;
    }

    .dashboard-workorders-table tbody tr.dashboard-workorder-row {
      cursor: pointer;
    }

    .dashboard-workorder-id {
      color: #42526e;
      font-weight: 700;
      white-space: nowrap;
    }

    .dashboard-client-wrap {
      display: flex;
      align-items: center;
      gap: 0.8rem;
      min-width: 0;
    }

    .dashboard-product-name {
      font-weight: 500;
      color: #5e5873;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: 260px;
    }

    .dashboard-client-avatar {
      width: 2.1rem;
      height: 2.1rem;
      border-radius: 999px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 1rem;
      flex: 0 0 auto;
    }

    .dashboard-client-name {
      font-weight: 500;
      color: #5e5873;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .dashboard-status-badge {
      font-weight: 500;
    }

    .dashboard-status-default {
      background-color: rgba(110, 107, 123, 0.12) !important;
      color: #6e6b7b !important;
    }

    .dashboard-status-planiran {
      background-color: rgba(0, 207, 232, 0.12) !important;
      color: #00cfe8 !important;
    }

    .dashboard-status-otvoren {
      background-color: rgba(40, 199, 111, 0.12) !important;
      color: #28c76f !important;
    }

    .dashboard-status-rezerviran {
      background-color: rgba(255, 159, 67, 0.12) !important;
      color: #ff9f43 !important;
    }

    .dashboard-status-u-radu {
      background-color: rgba(255, 193, 7, 0.16) !important;
      color: #b38600 !important;
    }

    .dashboard-status-djelimicno {
      background-color: rgba(253, 126, 20, 0.12) !important;
      color: #fd7e14 !important;
    }

    .dashboard-status-zakljucen {
      background-color: rgba(234, 84, 85, 0.12) !important;
      color: #ea5455 !important;
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

    .dark-layout .dashboard-workorders-table thead th,
    .semi-dark-layout .dashboard-workorders-table thead th {
      background-color: #303a52;
      border-bottom-color: rgba(186, 191, 221, 0.18);
      color: #c7cbda;
    }

    .dark-layout .dashboard-workorders-table tbody td,
    .semi-dark-layout .dashboard-workorders-table tbody td {
      border-top-color: rgba(186, 191, 221, 0.16);
      color: #d7dbeb;
    }

    .dark-layout .dashboard-workorders-table,
    .semi-dark-layout .dashboard-workorders-table {
      --bs-table-bg: transparent;
      --bs-table-accent-bg: transparent;
      --bs-table-hover-bg: #36405a;
      --bs-table-hover-color: #ffffff;
    }

    .dark-layout .dashboard-workorders-scroll,
    .semi-dark-layout .dashboard-workorders-scroll {
      scrollbar-color: rgba(170, 182, 213, 0.85) rgba(50, 58, 82, 0.95);
    }

    .dark-layout .dashboard-workorders-scroll::-webkit-scrollbar-track,
    .semi-dark-layout .dashboard-workorders-scroll::-webkit-scrollbar-track {
      background: rgba(50, 58, 82, 0.95);
      border-radius: 999px;
    }

    .dark-layout .dashboard-workorders-scroll::-webkit-scrollbar-thumb,
    .semi-dark-layout .dashboard-workorders-scroll::-webkit-scrollbar-thumb {
      background: linear-gradient(180deg, rgba(178, 188, 217, 0.92) 0%, rgba(147, 160, 192, 0.92) 100%);
      border-radius: 999px;
      border: 2px solid rgba(50, 58, 82, 0.98);
    }

    .dark-layout .dashboard-workorders-scroll::-webkit-scrollbar-thumb:hover,
    .semi-dark-layout .dashboard-workorders-scroll::-webkit-scrollbar-thumb:hover {
      background: linear-gradient(180deg, rgba(195, 205, 232, 0.98) 0%, rgba(164, 177, 207, 0.98) 100%);
    }

    .dark-layout .dashboard-workorders-table > :not(caption) > * > *,
    .semi-dark-layout .dashboard-workorders-table > :not(caption) > * > * {
      box-shadow: none !important;
    }

    .dark-layout .dashboard-report-loader,
    .semi-dark-layout .dashboard-report-loader {
      background: rgba(40, 48, 70, 0.92);
      backdrop-filter: none;
    }

    .dark-layout .dashboard-report-hover-box,
    .semi-dark-layout .dashboard-report-hover-box {
      border-color: rgba(186, 191, 221, 0.24);
      background: rgba(40, 48, 70, 0.98);
      color: #d7dbeb;
      box-shadow: 0 10px 24px rgba(10, 14, 22, 0.45);
    }

    .dark-layout .dashboard-report-hover-label,
    .semi-dark-layout .dashboard-report-hover-label {
      color: #b4b7bd;
    }

    .dark-layout .dashboard-report-hover-dot-compare,
    .semi-dark-layout .dashboard-report-hover-dot-compare {
      background: #959cb2;
    }

    .dark-layout .dashboard-workorders-table.table-hover tbody tr:hover > *,
    .semi-dark-layout .dashboard-workorders-table.table-hover tbody tr:hover > * {
      background-color: #36405a !important;
      color: #fff !important;
    }

    .dark-layout .dashboard-workorder-id,
    .semi-dark-layout .dashboard-workorder-id {
      color: #dce2f2;
    }

    .dark-layout .dashboard-client-name,
    .semi-dark-layout .dashboard-client-name {
      color: #d7dbeb;
    }

    .dark-layout .dashboard-product-name,
    .semi-dark-layout .dashboard-product-name {
      color: #d7dbeb;
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

    .dark-layout #dashboard-report-year-toggle,
    .semi-dark-layout #dashboard-report-year-toggle {
      border-color: rgba(255, 255, 255, 0.8) !important;
      color: #ffffff !important;
    }

    .dark-layout #dashboard-report-year-toggle:hover,
    .dark-layout #dashboard-report-year-toggle:focus,
    .dark-layout #dashboard-report-year-toggle:active,
    .dark-layout #dashboard-report-year-toggle.show,
    .semi-dark-layout #dashboard-report-year-toggle:hover,
    .semi-dark-layout #dashboard-report-year-toggle:focus,
    .semi-dark-layout #dashboard-report-year-toggle:active,
    .semi-dark-layout #dashboard-report-year-toggle.show {
      border-color: #ffffff !important;
      color: #ffffff !important;
      background-color: rgba(255, 255, 255, 0.08) !important;
    }
  </style>
@endsection

@section('content')
<!-- Dashboard Ecommerce Starts -->
@php
  $dashboardCurrentYear = now()->year;
  $dashboardPreviousYears = [];
  for ($year = $dashboardCurrentYear - 1; $year >= 2022; $year--) {
    $dashboardPreviousYears[] = $year;
  }
  $dashboardDefaultCompareYear = $dashboardPreviousYears[0] ?? 2022;
@endphp
<section
  id="dashboard-ecommerce"
  data-work-orders-calendar-url="{{ route('api.work-orders.calendar') }}"
  data-work-orders-yearly-summary-url="{{ route('api.work-orders.yearly-summary') }}"
  data-current-year="{{ $dashboardCurrentYear }}"
  data-default-compare-year="{{ $dashboardDefaultCompareYear }}"
>
  <div class="row match-height">
    <!-- Medal Card -->
      <!-- Developer Meetup Card -->
  <div class="col-lg-4 col-md-6 col-12">
  <div class="card card-developer-meetup">
    <div class="meetup-img-wrapper rounded-top text-center">
      <img src="{{ asset('images/illustration/email.svg') }}" alt="CNC proizvodnja" height="170" />
    </div>

    <div class="card-body">
      <div class="meetup-header d-flex align-items-center">
        <div class="meetup-day">
          <h6 class="mb-0">ČET</h6>
          <h3 class="mb-0">24</h3>
        </div>
        <div class="my-auto">
          <h4 class="card-title mb-25">
            GROB-WERKE 
          </h4>
          <p class="card-text mb-0">
            Sastanak o saradnji vezanoj za CNC obradu metala – kapaciteti i rokovi.
          </p>
        </div>
      </div>

      <!-- Datum i vrijeme -->
      <div class="mt-0">
        <div class="avatar float-start bg-light-primary rounded me-1">
          <div class="avatar-content">
            <i data-feather="calendar" class="avatar-icon font-medium-3"></i>
          </div>
        </div>
        <div class="more-info">
          <h6 class="mb-0">Četvrtak, 24. decembar 2025.</h6>
          <small>09:00 – 10:30</small>
        </div>
      </div>

      <!-- Lokacija + šta mogu očekivati -->
      <div class="mt-2">
        <div class="avatar float-start bg-light-primary rounded me-1">
          <div class="avatar-content">
            <i data-feather="map-pin" class="avatar-icon font-medium-3"></i>
          </div>
        </div>
        <div class="more-info">
          <h6 class="mb-0">Online sastanak (Teams / Zoom)</h6>
          <small>
            Predstavljanje mašina (CNC glodanje i tokarenje), tipičnih serija, tolerancija,
            površinske obrade.
          </small>
        </div>
      </div>

      <!-- Učesnici: s kim imaju sastanak -->
      <div class="avatar-group mt-1">
        <div
          data-bs-toggle="tooltip"
          data-popup="tooltip-custom"
          data-bs-placement="bottom"
          title="Direktor proizvodnje – Trendy d.o.o."
          class="avatar pull-up"
        >
          <img src="{{ asset('images/portrait/small/avatar-s-9.jpg') }}" alt="Avatar" width="33" height="33" />
        </div>
        <div
          data-bs-toggle="tooltip"
          data-popup="tooltip-custom"
          data-bs-placement="bottom"
          title="Tehnički inženjer – Trendy d.o.o."
          class="avatar pull-up"
        >
          <img src="{{ asset('images/portrait/small/avatar-s-6.jpg') }}" alt="Avatar" width="33" height="33" />
        </div>
        <div
          data-bs-toggle="tooltip"
          data-popup="tooltip-custom"
          data-bs-placement="bottom"
          title="Predstavnik nabavke vašeg preduzeća"
          class="avatar pull-up"
        >
          <img src="{{ asset('images/portrait/small/avatar-s-8.jpg') }}" alt="Avatar" width="33" height="33" />
        </div>
        <h6 class="align-self-center cursor-pointer ms-50 mb-0">+ još učesnika po potrebi</h6>
      </div>
    </div>
  </div>
</div>

    <!--/ Developer Meetup Card -->
    <!--/ Medal Card -->

    <!-- Statistics Card -->
    <div class="col-xl-8 col-md-6 col-12">
      <div class="card card-statistics">
     
        <div class="card-body statistics-body">
          <div class="row">
            <div class="col-xl-3 col-sm-6 col-12 mb-2 mb-xl-0">
              <div class="d-flex flex-row">
                <div class="avatar bg-light-warning me-2">
                  <div class="avatar-content">
                    <i data-feather="clipboard" class="avatar-icon"></i>
                  </div>
                </div>
                <div class="my-auto">
                  <h4 class="fw-bolder mb-0">{{ number_format((int) ($dashboardStats['work_orders_total'] ?? 0), 0, ',', '.') }}</h4>
                  <p class="card-text font-small-3 mb-0">Radni nalozi</p>
                </div>
              </div>
            </div>
            <div class="col-xl-3 col-sm-6 col-12 mb-2 mb-xl-0">
              <div class="d-flex flex-row">
                <div class="avatar bg-light-info me-2">
                  <div class="avatar-content">
                    <i data-feather="user" class="avatar-icon"></i>
                  </div>
                </div>
                <div class="my-auto">
                  <h4 class="fw-bolder mb-0">{{ number_format((int) ($dashboardStats['customers_total'] ?? 0), 0, ',', '.') }}</h4>
                  <p class="card-text font-small-3 mb-0">Kupci</p>
                </div>
              </div>
            </div>
            <div class="col-xl-3 col-sm-6 col-12 mb-2 mb-sm-0">
              <div class="d-flex flex-row">
                <div class="avatar bg-light-danger me-2">
                  <div class="avatar-content">
                    <i data-feather="box" class="avatar-icon"></i>
                  </div>
                </div>
                <div class="my-auto">
                  <h4 class="fw-bolder mb-0">{{ number_format((int) ($dashboardStats['products_total'] ?? 0), 0, ',', '.') }}</h4>
                  <p class="card-text font-small-3 mb-0">Proizvodi</p>
                </div>
              </div>
            </div>
            <div class="col-xl-3 col-sm-6 col-12">
              <div class="d-flex flex-row">
                <div class="avatar bg-light-success me-2">
                  <div class="avatar-content">
                    <i data-feather="dollar-sign" class="avatar-icon"></i>
                  </div>
                </div>
                <div class="my-auto">
                  <h4 class="fw-bolder mb-0">9745 KM</h4>
                  <p class="card-text font-small-3 mb-0">Prihodi</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
          <!-- Revenue Report Card -->

      <div class="card card-revenue-budget">
        <div class="row mx-0">
          <div class="col-md-8 col-12 revenue-report-wrapper">
            <div class="d-sm-flex justify-content-between align-items-center mb-3">
              <h4 class="card-title mb-50 mb-sm-0">Izvještaj o radnim nalozima</h4>
              <div class="d-flex align-items-center">
                <div class="d-flex align-items-center me-2">
                  <span class="bullet bullet-warning font-small-3 me-50 cursor-pointer"></span>
                  <span id="revenue-current-label">{{ $dashboardCurrentYear }}</span>
                </div>
                <div class="d-flex align-items-center ms-75">
                  <span class="bullet bullet-secondary font-small-3 me-50 cursor-pointer"></span>
                  <span id="revenue-compare-label">{{ $dashboardDefaultCompareYear }}</span>
                </div>
              </div>
            </div>
            <div class="dashboard-report-chart-shell">
              <div id="dashboard-report-loader" class="dashboard-report-loader">
                <div class="spinner-border text-primary" role="status">
                  <span class="visually-hidden">Učitavanje...</span>
                </div>
              </div>
              <div id="revenue-report-chart"></div>
              <div id="dashboard-report-hover-box" class="dashboard-report-hover-box is-hidden" aria-hidden="true"></div>
            </div>
          </div>
          <div class="col-md-4 col-12 budget-wrapper pb-2 pb-md-0">
            <div class="btn-group">
              <button
                type="button"
                id="dashboard-report-year-toggle"
                class="btn btn-outline-primary btn-sm dropdown-toggle budget-dropdown"
                data-bs-toggle="dropdown"
                aria-haspopup="true"
                aria-expanded="false"
              >
                {{ $dashboardDefaultCompareYear }}
              </button>
              <div class="dropdown-menu" id="dashboard-report-year-menu">
                @forelse ($dashboardPreviousYears as $yearOption)
                  <a class="dropdown-item{{ $yearOption === $dashboardDefaultCompareYear ? ' active' : '' }}" href="#" data-year="{{ $yearOption }}">
                    {{ $yearOption }}
                  </a>
                @empty
                  <span class="dropdown-item disabled">Nema godina</span>
                @endforelse
              </div>
            </div>
            <p class="text-center text-muted mb-50" id="work-orders-total-subtitle">Tekuća godina</p>
            <h2 class="mb-25" id="work-orders-total-primary">0 naloga</h2>
            <div class="d-flex justify-content-center">
              <span class="fw-bolder me-25" id="work-orders-total-compare-label">Poređenje:</span>
              <span id="work-orders-total-compare">0 naloga</span>
            </div>
            <div class="d-flex justify-content-center mb-1">
              <span class="badge rounded-pill badge-light-primary" id="work-orders-delta">0</span>
            </div>
            <div id="budget-chart"></div>
          </div>
        </div>
      </div>
   
    </div>
    <!--/ Statistics Card -->
  </div>

  <div class="row match-height">
    <div class="col-lg-4 col-12">
      <div class="row match-height">
        <!-- Bar Chart - Orders -->
        <div class="col-lg-6 col-md-3 col-6">
          <div class="card">
            <div class="card-body pb-50">
              <h6>Narudžbe</h6>
              <h2 class="fw-bolder mb-1">2,76k</h2>
              <div id="statistics-order-chart"></div>
            </div>
          </div>
        </div>
        <!--/ Bar Chart - Orders -->

        <!-- Line Chart - Profit -->
        <div class="col-lg-6 col-md-3 col-6">
          <div class="card card-tiny-line-stats">
            <div class="card-body pb-50">
              <h6>Dobit</h6>
              <h2 class="fw-bolder mb-1">6,24k</h2>
              <div id="statistics-profit-chart"></div>
            </div>
          </div>
        </div>
        <!--/ Line Chart - Profit -->

        <!-- Earnings Card -->
        <div class="col-lg-12 col-md-6 col-12">
          <div class="card earnings-card">
            <div class="card-body">
              <div class="row">
                <div class="col-6">
                  <h4 class="card-title mb-1">Zarada</h4>
                  <div class="font-small-2">Ovaj mjesec</div>
                  <h5 class="mb-1">4055,56 KM</h5>
                  <p class="card-text text-muted font-small-2">
                    <span class="fw-bolder">68.2%</span><span> više zarade nego prošlog mjeseca.</span>
                  </p>
                </div>
                <div class="col-6">
                  <div id="earnings-chart"></div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!--/ Earnings Card -->
      </div>
    </div>

    <div class="col-lg-8 col-12">
      <div class="card card-company-table dashboard-workorders-card">
        <div class="card-body p-0">
          <div class="table-responsive dashboard-workorders-scroll">
            <table class="table table-hover borderless mb-0 dashboard-workorders-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Naziv proizvoda</th>
                  <th>Klijent</th>
                  <th>Datum kreiranja</th>
                  <th>Status</th>
                  <th class="text-center">Akcije</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($latestOrders as $order)
                  @php
                    $routeId = (string) ($order->id ?? '');
                    $rawNumber = trim((string) ($order->work_order_number ?? $routeId));
                    $digits = preg_replace('/\D+/', '', $rawNumber);
                    $displayNumber = $rawNumber;
                    if (is_string($digits) && strlen($digits) === 13) {
                      $displayNumber = substr($digits, 0, 2) . '-' . substr($digits, 2, 5) . '-' . substr($digits, 7);
                    }

                    $clientName = trim((string) ($order->client_name ?? 'N/A'));
                    $productName = trim((string) ($order->product_name ?? 'Radni nalog'));
                    $productDisplayName = $productName;
                    if ($productDisplayName !== '' && mb_strlen($productDisplayName) > 10) {
                      $productDisplayName = mb_substr($productDisplayName, 0, 10) . '..';
                    }
                    $words = preg_split('/\s+/', $clientName) ?: [];
                    $firstInitial = $words[0] ?? '';
                    $lastInitial = $words[count($words) - 1] ?? '';
                    $initials = strtoupper(substr($firstInitial, 0, 1) . substr($lastInitial, 0, 1));
                    if ($initials === '') {
                      $initials = 'NA';
                    }

                    $hash = sprintf('%u', crc32(mb_strtolower($clientName)));
                    $hue = ((int) $hash) % 360;
                    $avatarText = 'hsl(' . $hue . ', 82%, 30%)';
                    $avatarBg = 'hsla(' . $hue . ', 72%, 52%, 0.18)';
                    $avatarBorder = 'hsla(' . $hue . ', 72%, 52%, 0.35)';

                    $statusText = trim((string) ($order->status ?? 'N/A'));
                    $normalizedStatus = mb_strtolower($statusText);
                    $statusClass = 'dashboard-status-default';
                    $previewUrl = url('app/invoice/preview/' . $routeId);
                    if (str_contains($normalizedStatus, 'otvoren')) {
                      $statusClass = 'dashboard-status-otvoren';
                    } elseif (str_contains($normalizedStatus, 'u radu') || str_contains($normalizedStatus, 'u toku')) {
                      $statusClass = 'dashboard-status-u-radu';
                    } elseif (str_contains($normalizedStatus, 'rezerv')) {
                      $statusClass = 'dashboard-status-rezerviran';
                    } elseif (str_contains($normalizedStatus, 'djelimic')) {
                      $statusClass = 'dashboard-status-djelimicno';
                    } elseif (str_contains($normalizedStatus, 'zaklj') || str_contains($normalizedStatus, 'zavr') || str_contains($normalizedStatus, 'otkaz')) {
                      $statusClass = 'dashboard-status-zakljucen';
                    } elseif (str_contains($normalizedStatus, 'planiran') || str_contains($normalizedStatus, 'novo') || str_contains($normalizedStatus, 'raspis') || str_contains($normalizedStatus, 'nacrt')) {
                      $statusClass = 'dashboard-status-planiran';
                    }
                  @endphp
                  <tr class="dashboard-workorder-row" data-preview-url="{{ $previewUrl }}">
                    <td class="text-nowrap">
                      <span class="dashboard-workorder-id">
                        {{ $displayNumber !== '' ? $displayNumber : 'N/A' }}
                      </span>
                    </td>
                    <td>
                      <span class="dashboard-product-name" title="{{ $productName !== '' ? $productName : 'N/A' }}">
                        {{ $productDisplayName !== '' ? $productDisplayName : 'N/A' }}
                      </span>
                    </td>
                    <td>
                      <div class="dashboard-client-wrap">
                        <span class="dashboard-client-avatar" style="background-color: {{ $avatarBg }}; color: {{ $avatarText }}; border: 1px solid {{ $avatarBorder }};">
                          {{ $initials }}
                        </span>
                        <span class="dashboard-client-name">{{ $clientName !== '' ? $clientName : 'N/A' }}</span>
                      </div>
                    </td>
                    <td>{{ optional($order->planned_start)->format('d M Y') ?? 'N/A' }}</td>
                    <td>
                      <span class="badge rounded-pill dashboard-status-badge {{ $statusClass }}">
                        {{ $statusText !== '' ? $statusText : 'N/A' }}
                      </span>
                    </td>
                    <td class="text-center">
                      <a href="{{ $previewUrl }}" class="wo-eye-action" data-bs-toggle="tooltip" data-bs-placement="top" title="Pregled radnog naloga">
                        <i data-feather="eye"></i>
                      </a>
                    </td>
                  </tr>
                @endforeach
                @if ($latestOrders->isEmpty())
                  <tr>
                    <td colspan="6" class="text-center text-muted">Nema dostupnih naloga.</td>
                  </tr>
                @endif
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    <!--/ Revenue Report Card -->
  </div>


</section>
<!-- Dashboard Ecommerce ends -->
@endsection

@section('vendor-script')
  {{-- vendor files --}}
  <script src="{{ asset(mix('vendors/js/charts/apexcharts.min.js')) }}"></script>
  <script src="{{ asset(mix('vendors/js/extensions/toastr.min.js')) }}"></script>
@endsection
@section('page-script')
  {{-- Page js files --}}
  <script src="{{ asset(mix('js/scripts/pages/dashboard-ecommerce.js')) }}"></script>
@endsection

