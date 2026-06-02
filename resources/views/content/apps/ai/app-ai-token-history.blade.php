@extends('layouts/contentLayoutMaster')

@section('title', 'AI tokeni')

@php
  $summary = $tokenHistorySummary ?? [];
  $filters = $tokenHistoryFilters ?? [];
  $activityOptions = $tokenHistoryActivityOptions ?? [];
  $monthOptions = $tokenHistoryMonthOptions ?? [];
  $yearOptions = $tokenHistoryYearOptions ?? [];
  $perPage = (int) ($tokenHistoryPerPage ?? 10);
  $perPageOptions = $tokenHistoryPerPageOptions ?? [10, 25, 50, 100];
  $showTokenUsage = (bool) ($showAiTokenUsage ?? false);
@endphp

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('vendors/css/pickers/flatpickr/flatpickr.min.css') }}">
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
    min-width: 1120px;
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
    border-radius: 999px;
    padding: 0.36rem 0.72rem;
    font-size: 0.76rem;
    font-weight: 600;
    line-height: 1;
    white-space: nowrap;
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
  data-last-loaded-display="{{ $tokenHistoryLastLoadedAtDisplay ?? now()->format('d.m.Y H:i:s') }}">
  <div class="content-header row">
    <div class="content-header-left col-12 mb-2">
      <div class="row breadcrumbs-top">
        <div class="col-12">
          <h2 class="content-header-title float-start mb-0">AI tokeni</h2>
        </div>
      </div>
    </div>
  </div>

  <div class="row match-height mb-2">
    <div class="col-xl-4 col-md-6 col-12">
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

    @if ($showTokenUsage)
      <div class="col-xl-4 col-md-6 col-12">
        <div class="card ai-token-history-summary-card">
          <div class="card-body d-flex align-items-center">
            <div class="avatar bg-light-warning me-2">
              <div class="avatar-content">
                <i data-feather="activity" class="font-medium-3"></i>
              </div>
            </div>
            <div>
              <h4 class="fw-bolder mb-0">{{ $summary['charged_tokens_display'] ?? '0' }}</h4>
              <p class="card-text font-small-3 mb-0">Napla&#263;eni tokeni</p>
            </div>
          </div>
        </div>
      </div>

      <div class="col-xl-4 col-md-6 col-12">
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
            <th>Fajl</th>
            <th>Broj stranica</th>
            @if ($showTokenUsage)
              <th>Napla&#263;eni tokeni</th>
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
                <div class="ai-token-history-file-name" title="{{ $row['file_name'] }}">{{ $row['file_name'] }}</div>
              </td>
              <td>{{ $row['page_count_display'] }}</td>
              @if ($showTokenUsage)
                <td>{{ $row['billed_tokens_display'] }}</td>
                <td>{{ $row['usage_cost_usd_display'] }}</td>
              @endif
              <td class="text-end ai-token-history-action-cell">
                <a href="{{ $row['open_scan_url'] }}" class="btn btn-outline-primary btn-sm">
                  <i data-feather="eye" class="me-50"></i> Otvori scan
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="{{ $showTokenUsage ? 9 : 7 }}" class="ai-token-history-empty">
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
@endsection

@section('page-script')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const app = document.getElementById('ai-token-history-app');
    const filterBody = document.getElementById('filters-body');
    const toggleButton = document.getElementById('btn-toggle-filters');
    const lastLoadedEl = document.getElementById('ai-token-history-last-loaded');
    const pollUrl = app ? (app.dataset.statusPollUrl || '') : '';
    let pollTimer = null;

    if (toggleButton && filterBody) {
      const syncToggleState = function () {
        const isHidden = filterBody.classList.contains('d-none');
        toggleButton.setAttribute('aria-expanded', isHidden ? 'false' : 'true');
        toggleButton.innerHTML = isHidden
          ? '<i data-feather="filter" class="me-50"></i> Pokaži filtere'
          : '<i data-feather="filter" class="me-50"></i> Sakrij filtere';

        if (typeof feather !== 'undefined') {
          feather.replace();
        }
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
      return '<span class="ai-token-history-badge ai-token-history-badge-' + String(tone || 'secondary') + '">' + String(label || '-') + '</span>';
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

        app.querySelectorAll('tbody tr[data-scan-id]').forEach(function (row) {
          const rowPayload = rows[String(row.dataset.scanId || '')];

          if (!rowPayload) {
            return;
          }

          const statusCell = row.querySelector('[data-history-status-cell]');

          if (statusCell) {
            statusCell.innerHTML = renderStatusBadge(rowPayload.status_label, rowPayload.status_tone);
          }
        });

        updateLastLoaded(payload.last_loaded_at_display || '');
      } catch (error) {
      } finally {
        scheduleNextPoll();
      }
    }

    updateLastLoaded(app ? app.dataset.lastLoadedDisplay || '' : '');
    scheduleNextPoll();

    window.addEventListener('beforeunload', function () {
      if (pollTimer) {
        clearTimeout(pollTimer);
      }
    });
  });
</script>
@endsection
