@extends('layouts/contentLayoutMaster')

@section('title', 'AI Inbox')

@section('page-style')
<style>
  .ai-inbox-shell .content-header {
    margin-top: -6px;
    margin-bottom: 4px;
  }

  .ai-inbox-shell .content-header-title {
    margin-top: 5px;
  }

  .ai-inbox-header-actions {
    gap: 0.75rem;
    justify-content: flex-end;
    flex-wrap: nowrap;
  }

  .ai-inbox-header-actions form {
    order: 2;
    margin-bottom: 0;
  }

  .ai-inbox-header-actions .ai-inbox-last-loaded {
    order: 1;
  }

  @media (max-width: 767.98px) {
    .ai-inbox-header-actions {
      justify-content: flex-start;
      flex-wrap: wrap;
    }
  }

  .ai-inbox-last-loaded {
    display: inline-flex;
    align-items: center;
    color: #6e6b7b;
    font-size: 0.82rem;
    line-height: 1.2;
    white-space: nowrap;
  }

  .ai-inbox-table-wrap {
    overflow-x: auto;
    overflow-y: hidden;
    scrollbar-width: thin;
    scrollbar-color: var(--app-scroll-thumb-flat) var(--app-scroll-track);
    scrollbar-gutter: auto;
    padding-right: 0 !important;
    background: #ffffff;
  }

  .ai-inbox-table-wrap::-webkit-scrollbar {
    width: 8px;
    height: 8px;
  }

  .ai-inbox-table-wrap::-webkit-scrollbar-track {
    background: var(--app-scroll-track);
    border-radius: 999px;
  }

  .ai-inbox-table-wrap::-webkit-scrollbar-thumb {
    background: var(--app-scroll-thumb-flat);
    border-radius: 999px;
    border: 1px solid var(--app-scroll-thumb-border);
  }

  .ai-inbox-table-wrap::-webkit-scrollbar-thumb:hover {
    background: var(--app-scroll-thumb-flat-hover);
  }

  .ai-inbox-table {
    width: 100%;
    min-width: 1080px;
    margin-right: 0;
  }

  .ai-inbox-table thead,
  .ai-inbox-table thead tr,
  .ai-inbox-table thead th {
    background: #f8f8fa;
    background-color: #f8f8fa;
    font-size: 0.78rem;
    letter-spacing: 0.02em;
    text-transform: uppercase;
    color: #6e6b7b;
    white-space: nowrap;
    border-bottom: 0 !important;
  }

  .ai-inbox-table > :not(:first-child) {
    border-top: 0 !important;
  }

  .ai-inbox-table tbody tr:first-child > * {
    border-top: 0 !important;
  }

  .ai-inbox-table tbody td {
    vertical-align: middle;
  }

  .ai-inbox-table.table tbody tr:hover > * {
    background-color: #f8f8fc;
  }

  .ai-inbox-subject {
    min-width: 18rem;
    color: #5e5873;
  }

  .ai-inbox-file {
    min-width: 13rem;
    font-weight: 600;
    color: #4b4b68;
  }

  .ai-inbox-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.35rem 0.6rem;
    border-radius: 999px;
    font-size: 0.76rem;
    font-weight: 600;
    line-height: 1;
    white-space: nowrap;
  }

  .ai-inbox-badge-secondary {
    background: rgba(130, 134, 139, 0.14);
    color: #6c757d;
  }

  .ai-inbox-badge-primary {
    background: rgba(115, 103, 240, 0.12);
    color: #7367f0;
  }

  .ai-inbox-badge-info {
    background: rgba(0, 207, 232, 0.14);
    color: #00a8bf;
  }

  .ai-inbox-badge-success {
    background: rgba(40, 199, 111, 0.14);
    color: #28c76f;
  }

  .ai-inbox-badge-warning {
    background: rgba(255, 159, 67, 0.14);
    color: #ff9f43;
  }

  .ai-inbox-badge-danger {
    background: rgba(234, 84, 85, 0.14);
    color: #ea5455;
  }

  .ai-inbox-pagination {
    row-gap: 0.75rem;
  }

  .ai-inbox-pagination-nav .pagination {
    margin-bottom: 0;
    gap: 0.35rem;
    align-items: center;
  }

  .ai-inbox-pagination-nav .page-item {
    margin: 0;
  }

  .ai-inbox-pagination-nav .page-link {
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

  .ai-inbox-pagination-nav .page-item.active .page-link {
    background: #4b5d78;
    color: #fff;
  }

  .ai-inbox-pagination-nav .page-item.disabled .page-link {
    color: #b9b9c3;
    opacity: 1;
  }

  .ai-inbox-pagination-nav .page-item:not(.active):not(.disabled) .page-link:hover {
    background: rgba(75, 93, 120, 0.08);
    color: #4b5d78;
  }

  .ai-inbox-pagination-nav .page-item.ellipsis .page-link {
    background: transparent;
    color: #9e9eae;
    pointer-events: none;
  }

  .ai-inbox-action-cell {
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

  .ai-inbox-table thead .ai-inbox-action-cell {
    z-index: 11 !important;
    background: #f8f8fa !important;
    background-color: #f8f8fa !important;
    box-shadow: none !important;
  }

  .ai-inbox-table.table tbody tr:hover > .ai-inbox-action-cell {
    background: #f8f8fc !important;
    background-color: #f8f8fc !important;
    box-shadow: none !important;
  }

  html.dark-layout .ai-inbox-table thead,
  html.dark-layout .ai-inbox-table thead tr,
  html.dark-layout .ai-inbox-table thead th,
  html.semi-dark-layout .ai-inbox-table thead,
  html.semi-dark-layout .ai-inbox-table thead tr,
  html.semi-dark-layout .ai-inbox-table thead th {
    background: #2f3854;
    background-color: #2f3854;
    color: #b4bfd9;
  }

  html.dark-layout .ai-inbox-table-wrap,
  html.semi-dark-layout .ai-inbox-table-wrap,
  body.dark-layout .ai-inbox-table-wrap,
  body.semi-dark-layout .ai-inbox-table-wrap {
    background: #283046;
  }

  html.dark-layout .ai-inbox-file,
  html.semi-dark-layout .ai-inbox-file,
  html.dark-layout .ai-inbox-subject,
  html.semi-dark-layout .ai-inbox-subject {
    color: #d8def1;
  }

  html.dark-layout .ai-inbox-table.table tbody tr:hover > *,
  html.semi-dark-layout .ai-inbox-table.table tbody tr:hover > * {
    background-color: #36405a !important;
  }

  body.dark-layout .ai-inbox-table .ai-inbox-action-cell,
  body.semi-dark-layout .ai-inbox-table .ai-inbox-action-cell,
  html.dark-layout .ai-inbox-table .ai-inbox-action-cell,
  html.semi-dark-layout .ai-inbox-table .ai-inbox-action-cell,
  .dark-layout .ai-inbox-table .ai-inbox-action-cell,
  .semi-dark-layout .ai-inbox-table .ai-inbox-action-cell {
    background: #283046 !important;
    background-color: #283046 !important;
    box-shadow: none !important;
    border-left-color: rgba(184, 190, 220, 0.22) !important;
  }

  body.dark-layout .ai-inbox-table thead .ai-inbox-action-cell,
  body.semi-dark-layout .ai-inbox-table thead .ai-inbox-action-cell,
  html.dark-layout .ai-inbox-table thead .ai-inbox-action-cell,
  html.semi-dark-layout .ai-inbox-table thead .ai-inbox-action-cell,
  .dark-layout .ai-inbox-table thead .ai-inbox-action-cell,
  .semi-dark-layout .ai-inbox-table thead .ai-inbox-action-cell {
    background: #2f3854 !important;
    background-color: #2f3854 !important;
    box-shadow: none !important;
  }

  body.dark-layout .ai-inbox-table.table tbody tr:hover > .ai-inbox-action-cell,
  body.semi-dark-layout .ai-inbox-table.table tbody tr:hover > .ai-inbox-action-cell,
  html.dark-layout .ai-inbox-table.table tbody tr:hover > .ai-inbox-action-cell,
  html.semi-dark-layout .ai-inbox-table.table tbody tr:hover > .ai-inbox-action-cell,
  .dark-layout .ai-inbox-table.table tbody tr:hover > .ai-inbox-action-cell,
  .semi-dark-layout .ai-inbox-table.table tbody tr:hover > .ai-inbox-action-cell {
    background: #36405a !important;
    background-color: #36405a !important;
    box-shadow: none !important;
  }

  .dark-layout .ai-inbox-pagination-nav .page-link,
  .semi-dark-layout .ai-inbox-pagination-nav .page-link {
    color: #d8def1;
  }

  .dark-layout .ai-inbox-pagination-nav .page-item.active .page-link,
  .semi-dark-layout .ai-inbox-pagination-nav .page-item.active .page-link {
    background: #00cfe8;
    color: #283046;
  }

  .dark-layout .ai-inbox-pagination-nav .page-item:not(.active):not(.disabled) .page-link:hover,
  .semi-dark-layout .ai-inbox-pagination-nav .page-item:not(.active):not(.disabled) .page-link:hover {
    background: rgba(0, 207, 232, 0.12);
    color: #00cfe8;
  }

  body.dark-layout .ai-inbox-shell .btn.btn-outline-primary,
  body.dark-layout .ai-inbox-shell .btn.btn-outline-primary:hover,
  body.dark-layout .ai-inbox-shell .btn.btn-outline-primary:focus,
  body.semi-dark-layout .ai-inbox-shell .btn.btn-outline-primary,
  body.semi-dark-layout .ai-inbox-shell .btn.btn-outline-primary:hover,
  body.semi-dark-layout .ai-inbox-shell .btn.btn-outline-primary:focus,
  .dark-layout .ai-inbox-shell .btn.btn-outline-primary,
  .dark-layout .ai-inbox-shell .btn.btn-outline-primary:hover,
  .dark-layout .ai-inbox-shell .btn.btn-outline-primary:focus,
  .semi-dark-layout .ai-inbox-shell .btn.btn-outline-primary,
  .semi-dark-layout .ai-inbox-shell .btn.btn-outline-primary:hover,
  .semi-dark-layout .ai-inbox-shell .btn.btn-outline-primary:focus {
    color: #fff !important;
    border-color: #fff !important;
    background-color: transparent !important;
  }

  body.dark-layout .ai-inbox-shell .btn.btn-outline-primary svg,
  body.semi-dark-layout .ai-inbox-shell .btn.btn-outline-primary svg,
  .dark-layout .ai-inbox-shell .btn.btn-outline-primary svg,
  .semi-dark-layout .ai-inbox-shell .btn.btn-outline-primary svg {
    stroke: currentColor !important;
  }
</style>
@endsection

@section('content')
<section
  class="ai-inbox-shell"
  id="ai-inbox-app"
  data-status-poll-url="{{ route('app-order-ai-inbox-statuses') }}"
  data-last-loaded-display="{{ $aiInboxLastLoadedAtDisplay ?? now()->format('d.m.Y H:i:s') }}">
  <div class="content-header row">
    <div class="content-header-left col-12 mb-2">
      <div class="row breadcrumbs-top align-items-center">
        <div class="col-md-6 col-12">
          <h2 class="content-header-title float-start mb-0">AI Inbox</h2>
        </div>
        <div class="col-md-6 col-12">
          <div class="d-flex justify-content-md-end justify-content-start align-items-center flex-wrap ai-inbox-header-actions">
            <form method="POST" action="{{ route('app-order-ai-inbox-refresh') }}">
              @csrf
              <button type="submit" class="btn btn-outline-primary btn-sm">
                <i data-feather="refresh-cw" class="me-50"></i> Osvježi
              </button>
            </form>
            <div class="ai-inbox-last-loaded" id="ai-inbox-last-loaded">
              Zadnji put u&#269;itano: <span class="ms-25">{{ $aiInboxLastLoadedAtDisplay ?? now()->format('d.m.Y H:i:s') }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  @if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  @if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive ai-inbox-table-wrap">
        <table class="table ai-inbox-table mb-0">
          <thead>
            <tr>
              <th>Vrijeme prijema</th>
              <th>Pošiljalac</th>
              <th>Subject</th>
              <th>Naziv PDF-a</th>
              <th>AI status</th>
              <th>Transfer status</th>
              <th class="text-end ai-inbox-action-cell">Akcija</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($aiInboxRows as $row)
              <tr data-scan-id="{{ $row['id'] }}">
                <td>{{ $row['received_at_display'] }}</td>
                <td>{{ $row['from'] }}</td>
                <td class="ai-inbox-subject">{{ $row['subject'] }}</td>
                <td class="ai-inbox-file">{{ $row['file_name'] }}</td>
                <td data-ai-status-cell>
                  <span class="ai-inbox-badge ai-inbox-badge-{{ $row['ai_status_tone'] }}">
                    {{ $row['ai_status_label'] }}
                  </span>
                </td>
                <td data-transfer-status-cell>
                  <span class="ai-inbox-badge ai-inbox-badge-{{ $row['transfer_status_tone'] }}">
                    {{ $row['transfer_status_label'] }}
                  </span>
                </td>
                <td class="text-end ai-inbox-action-cell">
                  <div class="app-table-action-group">
                    <a
                      href="{{ $row['edit_url'] }}"
                      class="btn btn-sm app-table-action-btn app-table-action-btn--info"
                      data-bs-toggle="tooltip"
                      data-bs-placement="top"
                      title="Pregled"
                      aria-label="Pregled">
                      <i data-feather="eye"></i>
                    </a>
                  </div>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="7" class="text-center py-3 text-muted">Još nema importovanih mail narudžbi.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
    @if ($aiInboxRows instanceof \Illuminate\Pagination\LengthAwarePaginator && $aiInboxRows->hasPages())
      <div class="card-footer">
        <div class="d-flex justify-content-between align-items-center flex-wrap ai-inbox-pagination">
          <div class="text-muted small">
            Prikazano {{ number_format($aiInboxRows->firstItem() ?? 0, 0, ',', '.') }} - {{ number_format($aiInboxRows->lastItem() ?? 0, 0, ',', '.') }}
            od {{ number_format($aiInboxRows->total(), 0, ',', '.') }} zapisa
          </div>
          <div class="ai-inbox-pagination-nav">
            {{ $aiInboxRows->onEachSide(2)->links('vendor.pagination.ai-token-history', ['ariaLabel' => 'AI inbox pagination']) }}
          </div>
        </div>
      </div>
    @endif
  </div>
</section>
@endsection

@section('page-script')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const app = document.getElementById('ai-inbox-app');

    if (!app) {
      return;
    }

    const pollUrl = app.dataset.statusPollUrl || '';
    const lastLoadedEl = document.getElementById('ai-inbox-last-loaded');
    let pollTimer = null;

    function collectIds() {
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
        target.textContent = displayValue || app.dataset.lastLoadedDisplay || '';
      }
    }

    function renderBadge(label, tone) {
      return '<span class="ai-inbox-badge ai-inbox-badge-' + String(tone || 'secondary') + '">' + String(label || '-') + '</span>';
    }

    function scheduleNextPoll() {
      pollTimer = window.setTimeout(pollStatuses, 5000);
    }

    async function pollStatuses() {
      const ids = collectIds();

      if (!pollUrl || !ids.length) {
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

          const aiStatusCell = row.querySelector('[data-ai-status-cell]');
          const transferStatusCell = row.querySelector('[data-transfer-status-cell]');

          if (aiStatusCell) {
            aiStatusCell.innerHTML = renderBadge(rowPayload.ai_status_label, rowPayload.ai_status_tone);
          }

          if (transferStatusCell) {
            transferStatusCell.innerHTML = renderBadge(rowPayload.transfer_status_label, rowPayload.transfer_status_tone);
          }
        });

        updateLastLoaded(payload.last_loaded_at_display || '');
      } catch (error) {
      } finally {
        scheduleNextPoll();
      }
    }

    updateLastLoaded(app.dataset.lastLoadedDisplay || '');
    scheduleNextPoll();

    window.addEventListener('beforeunload', function () {
      if (pollTimer) {
        clearTimeout(pollTimer);
      }
    });
  });
</script>
@endsection

