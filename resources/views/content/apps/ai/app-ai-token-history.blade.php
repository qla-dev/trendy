@extends('layouts/contentLayoutMaster')

@section('title', 'Historija AI skeniranja')

@php
  $summary = $tokenHistorySummary ?? [];
  $filters = $tokenHistoryFilters ?? [];
  $activityOptions = $tokenHistoryActivityOptions ?? [];
  $monthOptions = $tokenHistoryMonthOptions ?? [];
  $yearOptions = $tokenHistoryYearOptions ?? [];
  $perPage = (int) ($tokenHistoryPerPage ?? 10);
  $perPageOptions = $tokenHistoryPerPageOptions ?? [10, 25, 50, 100];
  $showUsdSpend = (bool) ($showAiTokenUsdSpend ?? false);
@endphp

@section('vendor-style')
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

  .ai-token-history-summary-card {
    height: 100%;
  }

  .ai-token-history-filter-actions {
    row-gap: 8px;
  }

  .ai-token-history-inline-filter-form {
    display: inline-flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
  }

  .ai-token-history-inline-filter-form .form-select-sm,
  .ai-token-history-inline-filter-form .btn-sm {
    min-height: 2.35rem;
    border-radius: 0.357rem;
  }

  .ai-token-history-inline-filter-form .form-select-sm {
    padding-top: 0.48rem;
    padding-bottom: 0.48rem;
  }

  .ai-token-history-inline-select-month {
    width: 12.25rem;
    min-width: 12.25rem;
  }

  .ai-token-history-inline-select-year {
    width: 12.25rem;
    min-width: 12.25rem;
  }

  .ai-token-history-filter-input,
  .ai-token-history-filter-date,
  .ai-token-history-filter-date.flatpickr-input,
  .ai-token-history-filter-date[readonly] {
    font-size: 14px;
  }

  .ai-token-history-filter-date,
  .ai-token-history-filter-date.flatpickr-input,
  .ai-token-history-filter-date[readonly] {
    cursor: pointer;
  }

  .ai-token-history-table-toolbar {
    row-gap: 0.75rem;
    margin-bottom: 1.5rem;
  }

  .ai-token-history-last-loaded {
    display: block;
    margin-top: 0.2rem;
  }

  .ai-token-history-length-form {
    display: inline-flex;
    align-items: center;
    gap: 0.65rem;
  }

  .ai-token-history-length-select {
    width: 84px;
  }

  .ai-token-history-table-wrap {
    overflow-x: auto;
    overflow-y: hidden;
    scrollbar-width: thin;
    scrollbar-color: var(--app-scroll-thumb-flat) var(--app-scroll-track);
    scrollbar-gutter: auto;
    padding-right: 0 !important;
    background: #ffffff;
  }

  .ai-token-history-table-wrap::-webkit-scrollbar {
    width: 8px;
    height: 8px;
  }

  .ai-token-history-table-wrap::-webkit-scrollbar-track {
    background: var(--app-scroll-track);
    border-radius: 999px;
  }

  .ai-token-history-table-wrap::-webkit-scrollbar-thumb {
    background: var(--app-scroll-thumb-flat);
    border-radius: 999px;
    border: 1px solid var(--app-scroll-thumb-border);
  }

  .ai-token-history-table-wrap::-webkit-scrollbar-thumb:hover {
    background: var(--app-scroll-thumb-flat-hover);
  }

  .ai-token-history-table {
    width: 100%;
    min-width: 1280px;
    margin-bottom: 0;
    margin-right: 0;
  }

  .ai-token-history-table thead th {
    background: #f8f8fa;
    background-color: #f8f8fa;
    white-space: nowrap;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.02em;
  }

  .ai-token-history-table tbody td {
    vertical-align: middle;
  }

  .ai-token-history-table.table tbody tr:hover > * {
    background-color: #f8f8fc;
  }

  .ai-token-history-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    padding: 0.36rem 0.72rem;
    font-size: 0.76rem;
    font-weight: 600;
    line-height: 1;
    white-space: nowrap;
    width: 13.5rem;
    min-width: 13.5rem;
    max-width: 13.5rem;
    text-align: center;
  }

  .ai-token-history-badge-primary {
    background: rgba(115, 103, 240, 0.12);
    color: #5e50ee;
  }

  .ai-token-history-badge-warning {
    background: rgba(255, 159, 67, 0.16);
    color: #ff8f1f;
  }

  .ai-token-history-badge-success {
    background: rgba(40, 199, 111, 0.16);
    color: #28c76f;
  }

  .ai-token-history-badge-info {
    background: rgba(0, 207, 232, 0.16);
    color: #00a8c2;
  }

  .ai-token-history-badge-danger {
    background: rgba(234, 84, 85, 0.14);
    color: #ea5455;
  }

  .ai-token-history-badge-secondary {
    background: rgba(130, 134, 139, 0.14);
    color: #6e6b7b;
  }

  .ai-token-history-file-name {
    max-width: 340px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-weight: 500;
  }

  .ai-token-history-amount {
    font-weight: 700;
    white-space: nowrap;
  }

  .ai-token-history-amount-success {
    color: #28c76f;
  }

  .ai-token-history-amount-danger {
    color: #ea5455;
  }

  .ai-token-history-amount-secondary {
    color: #6e6b7b;
  }

  .ai-token-history-action-cell {
    width: 1% !important;
    position: sticky !important;
    right: 0 !important;
    z-index: 10 !important;
    background: #ffffff !important;
    background-color: #ffffff !important;
    background-clip: border-box !important;
    opacity: 1 !important;
    isolation: isolate !important;
    box-shadow: none !important;
    border-left: 1px solid #ebe9f1 !important;
    white-space: nowrap;
  }

  .ai-token-history-table thead .ai-token-history-action-cell {
    z-index: 11 !important;
    background: #f8f8fa !important;
    background-color: #f8f8fa !important;
    box-shadow: none !important;
  }

  .ai-token-history-table.table tbody tr:hover > .ai-token-history-action-cell {
    background: #f8f8fc !important;
    background-color: #f8f8fc !important;
    box-shadow: none !important;
  }

  .ai-token-history-empty {
    padding: 3rem 1.5rem !important;
    text-align: center;
    color: #6e6b7b;
  }

  .ai-token-history-transfer-feedback {
    margin-bottom: 1rem;
  }

  @media (max-width: 767.98px) {
    .ai-token-history-badge {
      width: 11.75rem;
      min-width: 11.75rem;
      max-width: 11.75rem;
    }
  }

  .ai-token-history-pagination {
    row-gap: 0.75rem;
  }

  .ai-token-history-pagination-nav .pagination {
    margin-bottom: 0;
    gap: 0.35rem;
    align-items: center;
  }

  .ai-token-history-pagination-nav .page-item {
    margin: 0;
  }

  .ai-token-history-pagination-nav .page-link {
    min-width: 2rem;
    height: 2rem;
    padding: 0 0.4rem;
    border: 0;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    color: #6e6b7b;
    font-size: 0.92rem;
    box-shadow: none;
  }

  .ai-token-history-pagination-nav .page-item.active .page-link {
    background: #4b5d78;
    color: #fff;
  }

  .ai-token-history-pagination-nav .page-item.disabled .page-link {
    color: #b9b9c3;
    opacity: 1;
  }

  .ai-token-history-pagination-nav .page-item:not(.active):not(.disabled) .page-link:hover {
    background: rgba(75, 93, 120, 0.08);
    color: #4b5d78;
  }

  .ai-token-history-pagination-nav .page-item.ellipsis .page-link {
    background: transparent;
    color: #9e9eae;
    pointer-events: none;
  }

  .dark-layout .ai-token-history-table.table tbody tr:hover > *,
  .semi-dark-layout .ai-token-history-table.table tbody tr:hover > * {
    background-color: #36405a !important;
  }

  .dark-layout .ai-token-history-table thead th,
  .semi-dark-layout .ai-token-history-table thead th {
    background: #2f3854;
    background-color: #2f3854;
  }

  .dark-layout .ai-token-history-table-wrap,
  .semi-dark-layout .ai-token-history-table-wrap,
  body.dark-layout .ai-token-history-table-wrap,
  body.semi-dark-layout .ai-token-history-table-wrap {
    background: #283046;
  }

  body.dark-layout .ai-token-history-table .ai-token-history-action-cell,
  body.semi-dark-layout .ai-token-history-table .ai-token-history-action-cell,
  .dark-layout .ai-token-history-table .ai-token-history-action-cell,
  .semi-dark-layout .ai-token-history-table .ai-token-history-action-cell {
    background: #283046 !important;
    background-color: #283046 !important;
    box-shadow: none !important;
    border-left-color: rgba(184, 190, 220, 0.22) !important;
  }

  body.dark-layout .ai-token-history-table thead .ai-token-history-action-cell,
  body.semi-dark-layout .ai-token-history-table thead .ai-token-history-action-cell,
  .dark-layout .ai-token-history-table thead .ai-token-history-action-cell,
  .semi-dark-layout .ai-token-history-table thead .ai-token-history-action-cell {
    background: #2f3854 !important;
    background-color: #2f3854 !important;
    box-shadow: none !important;
  }

  body.dark-layout .ai-token-history-table.table tbody tr:hover > .ai-token-history-action-cell,
  body.semi-dark-layout .ai-token-history-table.table tbody tr:hover > .ai-token-history-action-cell,
  .dark-layout .ai-token-history-table.table tbody tr:hover > .ai-token-history-action-cell,
  .semi-dark-layout .ai-token-history-table.table tbody tr:hover > .ai-token-history-action-cell {
    background: #36405a !important;
    background-color: #36405a !important;
    box-shadow: none !important;
  }

  body.dark-layout .ai-token-history-wrapper .btn.btn-outline-primary,
  body.dark-layout .ai-token-history-wrapper .btn.btn-outline-primary:hover,
  body.dark-layout .ai-token-history-wrapper .btn.btn-outline-primary:focus,
  body.semi-dark-layout .ai-token-history-wrapper .btn.btn-outline-primary,
  body.semi-dark-layout .ai-token-history-wrapper .btn.btn-outline-primary:hover,
  body.semi-dark-layout .ai-token-history-wrapper .btn.btn-outline-primary:focus,
  .dark-layout .ai-token-history-wrapper .btn.btn-outline-primary,
  .dark-layout .ai-token-history-wrapper .btn.btn-outline-primary:hover,
  .dark-layout .ai-token-history-wrapper .btn.btn-outline-primary:focus,
  .semi-dark-layout .ai-token-history-wrapper .btn.btn-outline-primary,
  .semi-dark-layout .ai-token-history-wrapper .btn.btn-outline-primary:hover,
  .semi-dark-layout .ai-token-history-wrapper .btn.btn-outline-primary:focus {
    color: #fff !important;
    border-color: #fff !important;
    background-color: transparent !important;
  }

  body.dark-layout .ai-token-history-wrapper .btn.btn-outline-primary svg,
  body.semi-dark-layout .ai-token-history-wrapper .btn.btn-outline-primary svg,
  .dark-layout .ai-token-history-wrapper .btn.btn-outline-primary svg,
  .semi-dark-layout .ai-token-history-wrapper .btn.btn-outline-primary svg {
    stroke: currentColor !important;
  }

  .dark-layout .ai-token-history-badge-primary,
  .semi-dark-layout .ai-token-history-badge-primary {
    background: rgba(115, 103, 240, 0.22);
    color: #c9c4ff;
  }

  .dark-layout .ai-token-history-badge-warning,
  .semi-dark-layout .ai-token-history-badge-warning {
    background: rgba(255, 159, 67, 0.22);
    color: #ffd3a1;
  }

  .dark-layout .ai-token-history-badge-success,
  .semi-dark-layout .ai-token-history-badge-success {
    background: rgba(40, 199, 111, 0.22);
    color: #9ff0c1;
  }

  .dark-layout .ai-token-history-badge-info,
  .semi-dark-layout .ai-token-history-badge-info {
    background: rgba(0, 207, 232, 0.22);
    color: #9fefff;
  }

  .dark-layout .ai-token-history-badge-danger,
  .semi-dark-layout .ai-token-history-badge-danger {
    background: rgba(234, 84, 85, 0.2);
    color: #ffb2b2;
  }

  .dark-layout .ai-token-history-badge-secondary,
  .semi-dark-layout .ai-token-history-badge-secondary {
    background: rgba(130, 134, 139, 0.22);
    color: #d0d2d6;
  }

  .dark-layout .ai-token-history-pagination-nav .page-link,
  .semi-dark-layout .ai-token-history-pagination-nav .page-link {
    color: #b6b6c9;
  }

  .dark-layout .ai-token-history-pagination-nav .page-item.active .page-link,
  .semi-dark-layout .ai-token-history-pagination-nav .page-item.active .page-link {
    background: #65728a;
    color: #fff;
  }

  .dark-layout .ai-token-history-pagination-nav .page-item:not(.active):not(.disabled) .page-link:hover,
  .semi-dark-layout .ai-token-history-pagination-nav .page-item:not(.active):not(.disabled) .page-link:hover {
    background: rgba(101, 114, 138, 0.18);
    color: #fff;
  }

  @media (max-width: 767.98px) {
    .ai-token-history-inline-filter-form {
      width: 100%;
    }
  }
</style>
@endsection

@section('content')
<section
  class="ai-token-history-wrapper"
  id="ai-token-history-app"
  data-status-poll-url="{{ route('app-ai-token-history-statuses') }}"
  data-retry-url-template="{{ route('app-ai-token-history-retry', ['scan' => '__SCAN_ID__']) }}"
  data-transfer-url="{{ route('app-orders-store') }}"
  data-csrf="{{ csrf_token() }}"
  data-last-loaded-display="{{ $tokenHistoryLastLoadedAtDisplay ?? now()->format('d.m.Y H:i:s') }}">
  <div class="content-header row">
    <div class="content-header-left col-12 mb-2">
      <div class="row breadcrumbs-top">
        <div class="col-12">
          <h2 class="content-header-title float-start mb-0">Historija AI skeniranja</h2>
        </div>
      </div>
    </div>
  </div>

  <div class="row match-height mb-2">
    <div class="col-xl col-md-6 col-12">
      <div class="card ai-token-history-summary-card">
        <div class="card-body d-flex align-items-center">
          <div class="avatar bg-light-primary me-2">
            <div class="avatar-content">
              <i data-feather="file-text" class="font-medium-3"></i>
            </div>
          </div>
          <div>
            <h4 class="fw-bolder mb-0">{{ $summary['documents_total_display'] ?? '0' }}</h4>
            <p class="card-text font-small-3 mb-0">Obra&#273;eni dokumenti</p>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl col-md-6 col-12">
      <div class="card ai-token-history-summary-card">
        <div class="card-body d-flex align-items-center">
          <div class="avatar bg-light-warning me-2">
            <div class="avatar-content">
              <i data-feather="activity" class="font-medium-3"></i>
            </div>
          </div>
          <div>
            <h4 class="fw-bolder mb-0">{{ $summary['charged_tokens_display'] ?? '0' }}</h4>
            <p class="card-text font-small-3 mb-0">Tokeni</p>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl col-md-6 col-12">
      <div class="card ai-token-history-summary-card">
        <div class="card-body d-flex align-items-center">
          <div class="avatar bg-light-success me-2">
            <div class="avatar-content">
              <i data-feather="check-circle" class="font-medium-3"></i>
            </div>
          </div>
          <div>
            <h4 class="fw-bolder mb-0">{{ $summary['successful_total_display'] ?? '0' }}</h4>
            <p class="card-text font-small-3 mb-0">Uspje&#353;an AI scan</p>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl col-md-6 col-12">
      <div class="card ai-token-history-summary-card">
        <div class="card-body d-flex align-items-center">
          <div class="avatar bg-light-danger me-2">
            <div class="avatar-content">
              <i data-feather="x-circle" class="font-medium-3"></i>
            </div>
          </div>
          <div>
            <h4 class="fw-bolder mb-0">{{ $summary['failed_total_display'] ?? '0' }}</h4>
            <p class="card-text font-small-3 mb-0">Neuspje&#353;an AI scan</p>
          </div>
        </div>
      </div>
    </div>

    @if ($showUsdSpend)
      <div class="col-xl col-md-6 col-12">
        <div class="card ai-token-history-summary-card">
          <div class="card-body d-flex align-items-center">
            <div class="avatar bg-light-success me-2">
              <div class="avatar-content">
                <i data-feather="shield" class="font-medium-3"></i>
              </div>
            </div>
            <div>
              <h4 class="fw-bolder mb-0">{{ $summary['usd_spent_display'] ?? '$0.00000' }}</h4>
              <p class="card-text font-small-3 mb-0">Potro&#353;nja ($)</p>
            </div>
          </div>
        </div>
      </div>
    @endif
  </div>

  <div class="card mb-2">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h4 class="mb-0">Filter historije</h4>
      <div class="d-flex align-items-center flex-wrap gap-2 ai-token-history-filter-actions">
        <form method="GET" action="{{ route('app-ai-token-history') }}" class="ai-token-history-inline-filter-form">
          <input type="hidden" name="per_page" value="{{ $perPage }}">
          <input type="hidden" name="date_from" value="{{ $filters['date_from_display'] ?? '' }}">
          <input type="hidden" name="date_to" value="{{ $filters['date_to_display'] ?? '' }}">
          <input type="hidden" name="activity" value="{{ $filters['activity'] ?? 'all' }}">
          <input type="hidden" name="file_name" value="{{ $filters['file_name'] ?? '' }}">

          <select
            name="month"
            class="form-select form-select-sm ai-token-history-filter-input ai-token-history-inline-select-month"
            aria-label="Mjesec"
            title="Mjesec">
            @foreach ($monthOptions as $value => $label)
              <option value="{{ $value }}" @selected((int) ($filters['month'] ?? now()->month) === (int) $value)>{{ $label }}</option>
            @endforeach
          </select>

          <select
            name="year"
            class="form-select form-select-sm ai-token-history-filter-input ai-token-history-inline-select-year"
            aria-label="Godina"
            title="Godina">
            @foreach ($yearOptions as $value)
              <option value="{{ $value }}" @selected((int) ($filters['year'] ?? now()->year) === (int) $value)>{{ $value }}</option>
            @endforeach
          </select>

          <button type="submit" class="btn btn-primary btn-sm">
            <i data-feather="filter" class="me-50"></i> Primijeni
          </button>
        </form>

        <button type="button" class="btn btn-outline-primary btn-sm" id="btn-toggle-filters" aria-expanded="false">
          <i data-feather="filter" class="me-50"></i> Poka&#382;i filtere
        </button>
        <a href="{{ route('app-ai-token-history') }}" class="btn btn-outline-danger btn-sm">
          <i data-feather="trash-2" class="me-50"></i> Obri&#353;i filter
        </a>
      </div>
    </div>
    <div class="card-body d-none" id="filters-body">
      <form method="GET" action="{{ route('app-ai-token-history') }}">
        <input type="hidden" name="per_page" value="{{ $perPage }}">
        <input type="hidden" name="month" value="{{ (int) ($filters['month'] ?? now()->month) }}">
        <input type="hidden" name="year" value="{{ (int) ($filters['year'] ?? now()->year) }}">

        <div class="row g-2">
          <div class="col-md-3">
            <label class="form-label">Datum od</label>
            <div class="input-group input-group-merge">
              <span class="input-group-text"><i data-feather="calendar"></i></span>
              <input
                type="text"
                name="date_from"
                class="form-control ai-token-history-filter-input ai-token-history-filter-date"
                placeholder="dd.mm.yyyy"
                autocomplete="off"
                value="{{ $filters['date_from_display'] ?? '' }}">
            </div>
          </div>

          <div class="col-md-3">
            <label class="form-label">Datum do</label>
            <div class="input-group input-group-merge">
              <span class="input-group-text"><i data-feather="calendar"></i></span>
              <input
                type="text"
                name="date_to"
                class="form-control ai-token-history-filter-input ai-token-history-filter-date"
                placeholder="dd.mm.yyyy"
                autocomplete="off"
                value="{{ $filters['date_to_display'] ?? '' }}">
            </div>
          </div>

          <div class="col-md-3">
            <label class="form-label">Aktivnost</label>
            <select name="activity" class="form-select ai-token-history-filter-input">
              @foreach ($activityOptions as $value => $label)
                <option value="{{ $value }}" @selected(($filters['activity'] ?? 'all') === $value)>{{ $label }}</option>
              @endforeach
            </select>
          </div>

          <div class="col-md-3">
            <label class="form-label">Naziv fajla</label>
            <div class="input-group input-group-merge">
              <span class="input-group-text"><i data-feather="search"></i></span>
              <input
                type="text"
                name="file_name"
                class="form-control ai-token-history-filter-input"
                placeholder="Pretra&#382;i fajl"
                value="{{ $filters['file_name'] ?? '' }}">
            </div>
          </div>
        </div>

        <div class="row g-2 mt-1">
          <div class="col-md-3">
            <button type="submit" class="btn btn-primary w-100">
              <i data-feather="filter" class="me-50"></i> Filter
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-body pb-0">
      <div class="alert alert-dismissible d-none ai-token-history-transfer-feedback" id="ai-token-history-transfer-feedback" role="alert"></div>
      <div class="row align-items-center ai-token-history-table-toolbar">
        <div class="col-md-6 col-12">
          <form method="GET" action="{{ route('app-ai-token-history') }}" class="ai-token-history-length-form">
            @foreach (request()->except(['per_page', 'page']) as $name => $value)
              <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            <label for="per-page" class="mb-0">Prika&#382;i</label>
            <select id="per-page" name="per_page" class="form-select ai-token-history-length-select" onchange="this.form.submit()">
              @foreach ($perPageOptions as $option)
                <option value="{{ $option }}" @selected($perPage === (int) $option)>{{ $option }}</option>
              @endforeach
            </select>
          </form>
        </div>
        <div class="col-md-6 col-12 text-md-end">
          <span class="text-muted small">{{ number_format($tokenHistoryRows->total(), 0, ',', '.') }} zapisa</span>
          <span class="text-muted small ai-token-history-last-loaded" id="ai-token-history-last-loaded">
            Zadnji put u&#269;itano: <span>{{ $tokenHistoryLastLoadedAtDisplay ?? now()->format('d.m.Y H:i:s') }}</span>
          </span>
        </div>
      </div>
    </div>

    <div class="table-responsive ai-token-history-table-wrap">
      <table class="table ai-token-history-table">
        <thead>
          <tr>
            <th>Datum i vrijeme</th>
            <th>Modul</th>
            <th>Aktivnost</th>
            <th>Status</th>
            <th>Iznos</th>
            <th>Fajl</th>
            <th>Broj stranica</th>
            <th>Tokeni</th>
            @if ($showUsdSpend)
              <th>Potro&#353;nja ($)</th>
            @endif
            <th class="text-end ai-token-history-action-cell">Akcija</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($tokenHistoryRows as $row)
            <tr data-scan-id="{{ $row['id'] }}">
              <td>{{ $row['event_time_display'] }}</td>
              <td>
                <span class="ai-token-history-badge ai-token-history-badge-primary">{{ $row['usage_label'] }}</span>
              </td>
              <td>
                <span class="ai-token-history-badge ai-token-history-badge-warning">{{ $row['activity_label'] }}</span>
              </td>
              <td data-history-status-cell>
                <span class="ai-token-history-badge ai-token-history-badge-{{ $row['status_tone'] }}">{{ $row['status_label'] }}</span>
              </td>
              <td>
                <span
                  class="ai-token-history-amount ai-token-history-amount-{{ $row['amount_tone'] }}"
                  data-bs-toggle="tooltip"
                  data-bs-placement="top"
                  title="{{ $row['amount_title'] }}">
                  {{ $row['amount_display'] }}
                </span>
              </td>
              <td>
                <div class="ai-token-history-file-name">{{ $row['file_name'] }}</div>
              </td>
              <td>{{ $row['page_count_display'] }}</td>
              <td>{{ $row['billed_tokens_display'] }}</td>
              @if ($showUsdSpend)
                <td>{{ $row['usage_cost_usd_display'] }}</td>
              @endif
              <td class="text-end ai-token-history-action-cell">
                <div class="app-table-action-group">
                  @if (!empty($row['download_source_url']))
                    <a
                      href="{{ $row['download_source_url'] }}"
                      class="btn btn-sm app-table-action-btn app-table-action-btn--primary"
                      data-bs-toggle="tooltip"
                      data-bs-placement="top"
                      title="Preuzmi PDF"
                      aria-label="Preuzmi PDF">
                      <i data-feather="download"></i>
                    </a>
                  @endif
                  <span data-history-retry-host>
                    @if ($row['retry_enabled'])
                      <button
                        type="button"
                        class="btn btn-sm app-table-action-btn app-table-action-btn--warning"
                        data-history-retry-button
                        data-scan-id="{{ $row['id'] }}"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        title="{{ $row['retry_tooltip'] }}"
                        aria-label="{{ $row['retry_tooltip'] }}">
                        <i data-feather="{{ $row['retry_icon'] }}"></i>
                      </button>
                    @else
                      <span
                        class="app-table-action-tooltip"
                        tabindex="0"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        title="{{ $row['retry_tooltip'] }}"
                        aria-label="{{ $row['retry_tooltip'] }}">
                        <button
                          type="button"
                          class="btn btn-sm app-table-action-btn app-table-action-btn--warning"
                          disabled>
                          <i data-feather="{{ $row['retry_icon'] }}"></i>
                        </button>
                      </span>
                    @endif
                  </span>
                  <span data-history-transfer-host>
                    @if ($row['transfer_enabled'])
                      <button
                        type="button"
                        class="btn btn-sm app-table-action-btn app-table-action-btn--success"
                        data-history-transfer-button
                        data-scan-id="{{ $row['id'] }}"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        title="{{ $row['transfer_tooltip'] }}"
                        aria-label="{{ $row['transfer_tooltip'] }}">
                        <i data-feather="{{ $row['transfer_icon'] }}"></i>
                      </button>
                    @else
                      <span
                        class="app-table-action-tooltip"
                        tabindex="0"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        title="{{ $row['transfer_tooltip'] }}"
                        aria-label="{{ $row['transfer_tooltip'] }}">
                        <button
                          type="button"
                          class="btn btn-sm app-table-action-btn app-table-action-btn--success"
                          disabled>
                          <i data-feather="{{ $row['transfer_icon'] }}"></i>
                        </button>
                      </span>
                    @endif
                  </span>
                  <a
                    href="{{ $row['open_scan_url'] }}"
                    class="btn btn-sm app-table-action-btn app-table-action-btn--info"
                    data-history-open-scan-link
                    data-bs-toggle="tooltip"
                    data-bs-placement="top"
                    title="Otvori scan"
                    aria-label="Otvori scan">
                    <i data-feather="eye"></i>
                  </a>
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="{{ $showUsdSpend ? 10 : 9 }}" class="ai-token-history-empty">
                Nema AI token historije za odabrane filtere.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if ($tokenHistoryRows->hasPages())
      <div class="card-footer">
        <div class="d-flex justify-content-between align-items-center flex-wrap ai-token-history-pagination">
          <div class="text-muted small">
            Prikazano {{ number_format($tokenHistoryRows->firstItem() ?? 0, 0, ',', '.') }} - {{ number_format($tokenHistoryRows->lastItem() ?? 0, 0, ',', '.') }}
            od {{ number_format($tokenHistoryRows->total(), 0, ',', '.') }} zapisa
          </div>
          <div class="ai-token-history-pagination-nav">
            {{ $tokenHistoryRows->onEachSide(2)->links('vendor.pagination.ai-token-history') }}
          </div>
        </div>
      </div>
    @endif
  </div>
</section>
@endsection

@section('vendor-script')
<script src="{{ asset('vendors/js/pickers/flatpickr/flatpickr.min.js') }}"></script>
<script src="{{ asset('vendors/js/extensions/sweetalert2.all.min.js') }}"></script>
@endsection

@section('page-script')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const app = document.getElementById('ai-token-history-app');
    const filterBody = document.getElementById('filters-body');
    const toggleButton = document.getElementById('btn-toggle-filters');
    const lastLoadedEl = document.getElementById('ai-token-history-last-loaded');
    const transferFeedback = document.getElementById('ai-token-history-transfer-feedback');
    const pollUrl = app ? (app.dataset.statusPollUrl || '') : '';
    const retryUrlTemplate = app ? (app.dataset.retryUrlTemplate || '') : '';
    const transferUrl = app ? (app.dataset.transferUrl || '') : '';
    const csrfToken = app ? (app.dataset.csrf || '') : '';
    let pollTimer = null;
    let feedbackTimer = null;
    const pendingRetryScanIds = new Set();

    function syncFeatherIcons() {
      if (window.feather && typeof window.feather.replace === 'function') {
        window.feather.replace();
      }
    }

    function initTooltips(scope) {
      const root = scope || document;

      if (window.bootstrap && window.bootstrap.Tooltip) {
        root.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (element) {
          const instance = window.bootstrap.Tooltip.getInstance(element);

          if (instance) {
            instance.dispose();
          }

          new window.bootstrap.Tooltip(element);
        });

        return;
      }

      if (window.jQuery && window.jQuery.fn && typeof window.jQuery.fn.tooltip === 'function') {
        window.jQuery(root).find('[data-bs-toggle="tooltip"]').tooltip();
      }
    }

    function escapeHtml(value) {
      return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    function normalizeBosnianText(value) {
      return String(value == null ? '' : value)
        .split('\u00c4\u015a').join('\u010c')
        .split('\u00c4\u2020').join('\u0106')
        .split('\u00c4\u0164').join('\u010d')
        .split('\u00c4\u2021').join('\u0107')
        .split('\u00c4\u2018').join('\u0111')
        .split('\u0139\u02c7').join('\u0161')
        .split('\u0139\u00a0').join('\u0160')
        .split('\u0139\u013e').join('\u017e')
        .split('\u0139\u02dd').join('\u017d');
    }

    function normalizeVisibleBosnianText(scope) {
      const root = scope || app || document;

      if (!root || !root.querySelectorAll) {
        return;
      }

      root.querySelectorAll('*').forEach(function (element) {
        if (element.children.length === 0) {
          element.textContent = normalizeBosnianText(element.textContent);
        }

        if (element.hasAttribute('title')) {
          element.setAttribute('title', normalizeBosnianText(element.getAttribute('title')));
        }

        if (element.hasAttribute('aria-label')) {
          element.setAttribute('aria-label', normalizeBosnianText(element.getAttribute('aria-label')));
        }
      });
    }

    function showTransferFeedback(message, tone) {
      if (!transferFeedback) {
        return;
      }

      transferFeedback.className = 'alert alert-dismissible ai-token-history-transfer-feedback';
      transferFeedback.classList.add(tone === 'success' ? 'alert-success' : 'alert-danger');
      transferFeedback.textContent = normalizeBosnianText(message || '');
      transferFeedback.classList.remove('d-none');

      if (feedbackTimer) {
        window.clearTimeout(feedbackTimer);
      }

      feedbackTimer = window.setTimeout(function () {
        transferFeedback.classList.add('d-none');
      }, 4500);
    }

    if (toggleButton && filterBody) {
      const syncToggleState = function () {
        const isHidden = filterBody.classList.contains('d-none');
        toggleButton.setAttribute('aria-expanded', isHidden ? 'false' : 'true');
        toggleButton.innerHTML = isHidden
          ? '<i data-feather="filter" class="me-50"></i> Poka\u017ei filtere'
          : '<i data-feather="filter" class="me-50"></i> Sakrij filtere';

        syncFeatherIcons();
      };

      syncToggleState();

      toggleButton.addEventListener('click', function () {
        filterBody.classList.toggle('d-none');
        syncToggleState();
      });
    }

    if (typeof flatpickr === 'function') {
      document.querySelectorAll('.ai-token-history-filter-date').forEach(function (element) {
        flatpickr(element, {
          dateFormat: 'd.m.Y',
          allowInput: true
        });
      });
    }

    function collectIds() {
      if (!app) {
        return [];
      }

      return Array.from(app.querySelectorAll('tbody tr[data-scan-id]'))
        .map(function (row) {
          return String(row.dataset.scanId || '').trim();
        })
        .filter(Boolean);
    }

    function updateLastLoaded(displayValue) {
      if (!lastLoadedEl) {
        return;
      }

      const target = lastLoadedEl.querySelector('span');

      if (target) {
        target.textContent = displayValue || (app ? app.dataset.lastLoadedDisplay || '' : '');
      }
    }

    function renderStatusBadge(label, tone) {
      return '<span class="ai-token-history-badge ai-token-history-badge-' + String(tone || 'secondary') + '">' + escapeHtml(normalizeBosnianText(label || '-')) + '</span>';
    }

    function renderRetryButton(rowPayload) {
      const icon = escapeHtml(rowPayload && rowPayload.retry_icon ? rowPayload.retry_icon : 'refresh-cw');
      const tooltip = escapeHtml(normalizeBosnianText(rowPayload && rowPayload.retry_tooltip ? rowPayload.retry_tooltip : 'Ponovi AI scan'));
      const enabled = Boolean(rowPayload && rowPayload.retry_enabled);
      const scanId = escapeHtml(rowPayload && rowPayload.id ? rowPayload.id : '');

      if (enabled) {
        return '' +
          '<button type="button" class="btn btn-sm app-table-action-btn app-table-action-btn--warning" ' +
            'data-history-retry-button data-scan-id="' + scanId + '" ' +
            'data-bs-toggle="tooltip" data-bs-placement="top" title="' + tooltip + '" aria-label="' + tooltip + '">' +
            '<i data-feather="' + icon + '"></i>' +
          '</button>';
      }

      return '' +
        '<span class="app-table-action-tooltip" tabindex="0" data-bs-toggle="tooltip" data-bs-placement="top" title="' + tooltip + '" aria-label="' + tooltip + '">' +
          '<button type="button" class="btn btn-sm app-table-action-btn app-table-action-btn--warning" disabled>' +
            '<i data-feather="' + icon + '"></i>' +
          '</button>' +
        '</span>';
    }

    function renderTransferButton(rowPayload) {
      const icon = escapeHtml(rowPayload && rowPayload.transfer_icon ? rowPayload.transfer_icon : 'arrow-right');
      const tooltip = escapeHtml(normalizeBosnianText(rowPayload && rowPayload.transfer_tooltip ? rowPayload.transfer_tooltip : 'Transfer u bazu'));
      const enabled = Boolean(rowPayload && rowPayload.transfer_enabled);
      const scanId = escapeHtml(rowPayload && rowPayload.id ? rowPayload.id : '');

      if (enabled) {
        return '' +
          '<button type="button" class="btn btn-sm app-table-action-btn app-table-action-btn--success" ' +
            'data-history-transfer-button data-scan-id="' + scanId + '" ' +
            'data-bs-toggle="tooltip" data-bs-placement="top" title="' + tooltip + '" aria-label="' + tooltip + '">' +
            '<i data-feather="' + icon + '"></i>' +
          '</button>';
      }

      return '' +
        '<span class="app-table-action-tooltip" tabindex="0" data-bs-toggle="tooltip" data-bs-placement="top" title="' + tooltip + '" aria-label="' + tooltip + '">' +
          '<button type="button" class="btn btn-sm app-table-action-btn app-table-action-btn--success" disabled>' +
            '<i data-feather="' + icon + '"></i>' +
          '</button>' +
        '</span>';
    }

    function applyRowPayload(row, rowPayload) {
      if (!row || !rowPayload) {
        return;
      }

      const statusCell = row.querySelector('[data-history-status-cell]');
      const retryHost = row.querySelector('[data-history-retry-host]');
      const transferHost = row.querySelector('[data-history-transfer-host]');
      const openScanLink = row.querySelector('[data-history-open-scan-link]');

      if (statusCell) {
        statusCell.innerHTML = renderStatusBadge(rowPayload.status_label, rowPayload.status_tone);
      }

      if (retryHost) {
        retryHost.innerHTML = renderRetryButton(rowPayload);
      }

      if (transferHost) {
        transferHost.innerHTML = renderTransferButton(rowPayload);
      }

      if (openScanLink && rowPayload.open_scan_url) {
        openScanLink.href = rowPayload.open_scan_url;
      }
    }

    function getOpenScanUrl(row) {
      const openScanLink = row ? row.querySelector('[data-history-open-scan-link]') : null;

      return openScanLink ? String(openScanLink.getAttribute('href') || '').trim() : '';
    }

    async function showRetryFinishedModal(row) {
      const openScanUrl = getOpenScanUrl(row);

      if (!openScanUrl) {
        showTransferFeedback('AI scan je zavr\u0161en.', 'success');
        return;
      }

      if (!window.Swal || typeof window.Swal.fire !== 'function') {
        if (window.confirm('AI scan je zavr\u0161en. Da li \u017eeli\u0161 otvoriti scan?')) {
          window.location.assign(openScanUrl);
        }

        return;
      }

      const result = await window.Swal.fire({
        icon: 'success',
        title: 'AI scan je zavr\u0161en',
        text: 'Mo\u017ee\u0161 zatvoriti prozor ili odmah otvoriti scan.',
        showCancelButton: true,
        confirmButtonText: 'Otvori scan',
        cancelButtonText: 'Zatvori',
        reverseButtons: true,
        focusCancel: true,
      });

      if (result && result.isConfirmed) {
        window.location.assign(openScanUrl);
      }
    }

    function buildRetryUrl(scanId) {
      const resolvedId = String(scanId || '').trim();

      if (!retryUrlTemplate || resolvedId === '') {
        return '';
      }

      return retryUrlTemplate.replace('__SCAN_ID__', encodeURIComponent(resolvedId));
    }

    function scheduleNextPoll() {
      pollTimer = window.setTimeout(pollStatuses, 5000);
    }

    async function pollStatuses() {
      const ids = collectIds();

      if (!app || !pollUrl || !ids.length) {
        scheduleNextPoll();
        return;
      }

      if (document.hidden) {
        scheduleNextPoll();
        return;
      }

      try {
        const query = new URLSearchParams();
        ids.forEach(function (id) {
          query.append('ids[]', id);
        });

        const response = await fetch(pollUrl + '?' + query.toString(), {
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          credentials: 'same-origin',
        });

        if (!response.ok) {
          throw new Error('Polling nije uspio.');
        }

        const payload = await response.json();
        const rows = payload.rows || {};
        const completedRetryRows = [];

        app.querySelectorAll('tbody tr[data-scan-id]').forEach(function (row) {
          const scanId = String(row.dataset.scanId || '');
          const rowPayload = rows[scanId];

          if (!rowPayload) {
            return;
          }

          applyRowPayload(row, rowPayload);

          if (!pendingRetryScanIds.has(scanId)) {
            return;
          }

          if (rowPayload.status_outcome === 'success') {
            pendingRetryScanIds.delete(scanId);
            completedRetryRows.push(row);
            return;
          }

          if (rowPayload.status_outcome === 'failed') {
            pendingRetryScanIds.delete(scanId);
            showTransferFeedback(rowPayload.status_error_message || 'Ponovljeni AI scan nije uspio.', 'danger');
          }
        });

        updateLastLoaded(payload.last_loaded_at_display || '');
        syncFeatherIcons();
        normalizeVisibleBosnianText(app);
        initTooltips(app);

        for (const completedRetryRow of completedRetryRows) {
          await showRetryFinishedModal(completedRetryRow);
        }
      } catch (error) {
      } finally {
        scheduleNextPoll();
      }
    }

    async function confirmRetry() {
      if (!window.Swal || typeof window.Swal.fire !== 'function') {
        return window.confirm('Da li \u017eeli\u0161 ponovo pokrenuti AI skeniranje ovog dokumenta?');
      }

      const result = await window.Swal.fire({
        icon: 'question',
        title: 'Ponoviti AI scan?',
        text: 'Da li \u017eeli\u0161 ponovo pokrenuti AI skeniranje ovog dokumenta?',
        showCancelButton: true,
        confirmButtonText: 'Da, pokreni ponovo',
        cancelButtonText: 'Odustani',
        reverseButtons: true,
        focusCancel: true,
      });

      return Boolean(result && result.isConfirmed);
    }

    async function handleRetry(button) {
      const row = button ? button.closest('tr[data-scan-id]') : null;
      const scanId = row ? row.dataset.scanId || '' : '';
      const retryUrl = buildRetryUrl(scanId);

      if (!button || !row || !scanId || !retryUrl) {
        return;
      }

      const confirmed = await confirmRetry();

      if (!confirmed) {
        return;
      }

      const originalMarkup = button.innerHTML;
      let appliedRowPayload = false;
      button.disabled = true;
      button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';

      try {
        const response = await fetch(retryUrl, {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
          },
          credentials: 'same-origin',
        });

        const payload = await response.json().catch(function () {
          return {};
        });
        const rowPayload = payload && payload.data ? payload.data : null;

        if (rowPayload) {
          applyRowPayload(row, rowPayload);
          appliedRowPayload = true;
        }

        syncFeatherIcons();
        normalizeVisibleBosnianText(row);
        initTooltips(row);

        if (!response.ok) {
          throw new Error(payload && payload.message ? payload.message : 'Ponovno AI skeniranje nije uspjelo.');
        }

        if (rowPayload && rowPayload.status_outcome === 'success') {
          pendingRetryScanIds.delete(scanId);
          updateLastLoaded(payload && payload.last_loaded_at_display ? payload.last_loaded_at_display : new Date().toLocaleString('bs-BA'));
          await showRetryFinishedModal(row);
          return;
        }

        pendingRetryScanIds.add(scanId);
        showTransferFeedback(payload && payload.message ? payload.message : 'AI skeniranje je ponovo pokrenuto.', 'success');
        updateLastLoaded(payload && payload.last_loaded_at_display ? payload.last_loaded_at_display : new Date().toLocaleString('bs-BA'));
      } catch (error) {
        pendingRetryScanIds.delete(scanId);

        if (!appliedRowPayload) {
          button.disabled = false;
          button.innerHTML = originalMarkup;
        }

        syncFeatherIcons();
        normalizeVisibleBosnianText(row);
        initTooltips(row);
        showTransferFeedback(error && error.message ? error.message : 'Ponovno AI skeniranje nije uspjelo.', 'danger');
      }
    }

    async function handleTransfer(button) {
      const row = button ? button.closest('tr[data-scan-id]') : null;
      const scanId = row ? row.dataset.scanId || '' : '';

      if (!button || !row || !scanId || !transferUrl) {
        return;
      }

      button.disabled = true;
      button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';

      try {
        const response = await fetch(transferUrl, {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
          },
          credentials: 'same-origin',
          body: JSON.stringify({
            scan_id: Number(scanId),
          }),
        });

        const payload = await response.json().catch(function () {
          return {};
        });

        if (!response.ok) {
          throw new Error(payload && payload.message ? payload.message : 'Transfer u bazu nije uspio.');
        }

        applyRowPayload(row, {
          id: scanId,
          status_outcome: 'success',
          status_label: 'Uspješan transfer',
          status_tone: 'success',
          retry_enabled: false,
          retry_icon: 'refresh-cw',
          retry_tooltip: 'AI scan je uspješno završen.',
          transfer_enabled: false,
          transfer_completed: true,
          transfer_icon: 'check',
          transfer_tooltip: 'Narudžba je već prebačena u bazu.',
        });

        showTransferFeedback(payload && payload.message ? payload.message : 'Narudžba je uspješno prebačena u bazu.', 'success');
        updateLastLoaded(new Date().toLocaleString('bs-BA'));
        syncFeatherIcons();
        normalizeVisibleBosnianText(row);
        initTooltips(row);
      } catch (error) {
        button.disabled = false;
        button.innerHTML = '<i data-feather="arrow-right"></i>';
        syncFeatherIcons();
        normalizeVisibleBosnianText(row);
        initTooltips(row);
        showTransferFeedback(error && error.message ? error.message : 'Transfer u bazu nije uspio.', 'danger');
      }
    }

    if (app) {
      app.addEventListener('click', function (event) {
        const retryButton = event.target.closest('[data-history-retry-button]');
        const button = event.target.closest('[data-history-transfer-button]');

        if (retryButton) {
          event.preventDefault();
          handleRetry(retryButton);
          return;
        }

        if (!button) {
          return;
        }

        event.preventDefault();
        handleTransfer(button);
      });
    }

    updateLastLoaded(app ? app.dataset.lastLoadedDisplay || '' : '');
    syncFeatherIcons();
    normalizeVisibleBosnianText(app || document);
    initTooltips(app || document);
    scheduleNextPoll();

    window.addEventListener('beforeunload', function () {
      if (pollTimer) {
        clearTimeout(pollTimer);
      }
    });
  });
</script>
@endsection
