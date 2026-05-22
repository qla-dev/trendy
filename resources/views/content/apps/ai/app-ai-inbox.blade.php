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
  }

  .ai-inbox-table thead th {
    font-size: 0.78rem;
    letter-spacing: 0.02em;
    text-transform: uppercase;
    color: #6e6b7b;
    white-space: nowrap;
  }

  .ai-inbox-table tbody td {
    vertical-align: middle;
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

  html.dark-layout .ai-inbox-table thead th,
  html.semi-dark-layout .ai-inbox-table thead th {
    color: #b4bfd9;
  }

  html.dark-layout .ai-inbox-file,
  html.semi-dark-layout .ai-inbox-file,
  html.dark-layout .ai-inbox-subject,
  html.semi-dark-layout .ai-inbox-subject {
    color: #d8def1;
  }
</style>
@endsection

@section('content')
<section class="ai-inbox-shell">
  <div class="content-header row">
    <div class="content-header-left col-12 mb-2">
      <div class="row breadcrumbs-top align-items-center">
        <div class="col-md-6 col-12">
          <h2 class="content-header-title float-start mb-0">AI Inbox</h2>
        </div>
        <div class="col-md-6 col-12">
          <div class="d-flex justify-content-md-end justify-content-start align-items-center flex-wrap ai-inbox-header-actions">
            <a href="{{ route('app-orders') }}" class="btn btn-outline-secondary btn-sm">
              <i data-feather="arrow-left" class="me-50"></i> Narudžbe
            </a>
            <form method="POST" action="{{ route('app-order-ai-inbox-refresh') }}">
              @csrf
              <button type="submit" class="btn btn-primary btn-sm">
                <i data-feather="refresh-cw" class="me-50"></i> Osvježi
              </button>
            </form>
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
      <div class="table-responsive">
        <table class="table ai-inbox-table mb-0">
          <thead>
            <tr>
              <th>Vrijeme prijema</th>
              <th>Pošiljalac</th>
              <th>Subject</th>
              <th>Naziv PDF-a</th>
              <th>AI status</th>
              <th>Transfer status</th>
              <th class="text-end">Akcija</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($aiInboxRows as $row)
              <tr>
                <td>{{ $row['received_at_display'] }}</td>
                <td>{{ $row['from'] }}</td>
                <td class="ai-inbox-subject">{{ $row['subject'] }}</td>
                <td class="ai-inbox-file">{{ $row['file_name'] }}</td>
                <td>
                  <span class="ai-inbox-badge ai-inbox-badge-{{ $row['ai_status_tone'] }}">
                    {{ $row['ai_status_label'] }}
                  </span>
                </td>
                <td>
                  <span class="ai-inbox-badge ai-inbox-badge-{{ $row['transfer_status_tone'] }}">
                    {{ $row['transfer_status_label'] }}
                  </span>
                </td>
                <td class="text-end">
                  <a href="{{ $row['edit_url'] }}" class="btn btn-outline-primary btn-sm">
                    <i data-feather="eye" class="me-50"></i> Pregledaj / Uredi
                  </a>
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
      <div class="card-footer d-flex justify-content-end">
        {{ $aiInboxRows->links() }}
      </div>
    @endif
  </div>
</section>
@endsection

