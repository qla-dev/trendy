@extends('layouts/contentLayoutMaster')

@section('title', 'Dozvoljeni pošiljaoci')

@php
  $stats = $whitelistStats ?? [];
  $entries = collect($whitelistEntries ?? [])->values();
@endphp

@section('vendor-style')
  <link rel="stylesheet" href="{{ asset(mix('vendors/css/tables/datatable/dataTables.bootstrap5.min.css')) }}">
  <link rel="stylesheet" href="{{ asset(mix('vendors/css/tables/datatable/responsive.bootstrap5.min.css')) }}">
  <link rel="stylesheet" href="{{ asset(mix('vendors/css/tables/datatable/buttons.bootstrap5.min.css')) }}">
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

    .whitelist-feedback {
      margin-bottom: 1.25rem;
    }

    .whitelist-notes-cell {
      max-width: 320px;
      white-space: normal;
    }

    .whitelist-status-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 108px;
      padding: 0.35rem 0.7rem;
      border-radius: 999px;
      font-weight: 600;
      font-size: 0.78rem;
      line-height: 1;
      white-space: nowrap;
    }

    .whitelist-status-badge--success {
      background: rgba(40, 199, 111, 0.16);
      color: #28c76f;
    }

    .whitelist-status-badge--secondary {
      background: rgba(130, 134, 139, 0.14);
      color: #6e6b7b;
    }

    .whitelist-list-table.table tbody tr:hover > * {
      background-color: #f8f8fc;
    }

    .whitelist-action-cell {
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

    .whitelist-list-table thead .whitelist-action-cell {
      z-index: 11 !important;
      background: #f8f8fa !important;
      background-color: #f8f8fa !important;
      box-shadow: none !important;
    }

    .whitelist-list-table.table tbody tr:hover > .whitelist-action-cell {
      background: #f8f8fc !important;
      background-color: #f8f8fc !important;
      box-shadow: none !important;
    }

    .whitelist-modal-feedback {
      margin-bottom: 1rem;
    }

    .whitelist-modal-copy {
      color: #6e6b7b;
      line-height: 1.55;
    }

    .dark-layout .whitelist-list-table.table tbody tr:hover > *,
    .semi-dark-layout .whitelist-list-table.table tbody tr:hover > * {
      background-color: #36405a !important;
    }

    .dark-layout .whitelist-list-table .whitelist-action-cell,
    .semi-dark-layout .whitelist-list-table .whitelist-action-cell,
    body.dark-layout .whitelist-list-table .whitelist-action-cell,
    body.semi-dark-layout .whitelist-list-table .whitelist-action-cell {
      background: #283046 !important;
      background-color: #283046 !important;
      border-left-color: rgba(184, 190, 220, 0.22) !important;
    }

    .dark-layout .whitelist-list-table thead .whitelist-action-cell,
    .semi-dark-layout .whitelist-list-table thead .whitelist-action-cell,
    body.dark-layout .whitelist-list-table thead .whitelist-action-cell,
    body.semi-dark-layout .whitelist-list-table thead .whitelist-action-cell {
      background: #2f3854 !important;
      background-color: #2f3854 !important;
    }

    .dark-layout .whitelist-list-table.table tbody tr:hover > .whitelist-action-cell,
    .semi-dark-layout .whitelist-list-table.table tbody tr:hover > .whitelist-action-cell,
    body.dark-layout .whitelist-list-table.table tbody tr:hover > .whitelist-action-cell,
    body.semi-dark-layout .whitelist-list-table.table tbody tr:hover > .whitelist-action-cell {
      background: #36405a !important;
      background-color: #36405a !important;
    }
  </style>
@endsection

@section('content')
<section class="app-ai-whitelist">
  <div id="whitelistAsyncFeedback" class="alert d-none whitelist-feedback" role="alert"></div>

  <div class="row">
    <div class="col-lg-6 col-sm-6">
      <div class="card">
        <div class="card-body d-flex align-items-center justify-content-between">
          <div>
            <h3 class="fw-bolder mb-75" data-whitelist-stat="total">{{ number_format((int) ($stats['total'] ?? 0), 0, ',', '.') }}</h3>
            <span>Ukupno unosa</span>
          </div>
          <div class="avatar bg-light-primary p-50">
            <span class="avatar-content">
              <i data-feather="mail" class="font-medium-4"></i>
            </span>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-6 col-sm-6">
      <div class="card">
        <div class="card-body d-flex align-items-center justify-content-between">
          <div>
            <h3 class="fw-bolder mb-75" data-whitelist-stat="active">{{ number_format((int) ($stats['active'] ?? 0), 0, ',', '.') }}</h3>
            <span>Aktivni pošiljaoci</span>
          </div>
          <div class="avatar bg-light-success p-50">
            <span class="avatar-content">
              <i data-feather="check-circle" class="font-medium-4"></i>
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header border-bottom d-flex justify-content-between align-items-center flex-wrap gap-1">
      <div>
        <h4 class="card-title mb-0">Dozvoljeni pošiljaoci</h4>
        <small class="text-muted">Izmjene se prikazuju odmah nakon sačuvanih promjena.</small>
      </div>

      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addWhitelistEntryModal">
        <i data-feather="plus" class="me-50"></i> Novi Pošiljalac
      </button>
    </div>

    <div class="card-datatable table-responsive pt-0">
      <table class="whitelist-list-table table">
        <thead class="table-light">
          <tr>
            <th>Naziv</th>
            <th>Email</th>
            <th>Status</th>
            <th>Zadnje primljen mail</th>
            <th>Napomena</th>
            <th>Kreirano</th>
            <th class="text-end whitelist-action-cell">Akcije</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</section>

<div class="modal fade" id="addWhitelistEntryModal" tabindex="-1" aria-labelledby="addWhitelistEntryModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addWhitelistEntryModalLabel">Novi pošiljalac</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zatvori"></button>
      </div>
      <form id="addWhitelistEntryForm" action="{{ route('app-ai-whitelist-store') }}" method="POST" autocomplete="off">
        @csrf
        <div class="modal-body">
          <div class="alert alert-danger d-none whitelist-modal-feedback" data-form-feedback role="alert"></div>

          <div class="row">
            <div class="col-md-6 mb-1">
              <label class="form-label" for="whitelist-name">Naziv / kompanija</label>
              <input
                type="text"
                class="form-control @error('name') is-invalid @enderror"
                id="whitelist-name"
                name="name"
                value="{{ old('name') }}">
              @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
            <div class="col-md-6 mb-1">
              <label class="form-label" for="whitelist-email">Email</label>
              <input
                type="email"
                class="form-control @error('email') is-invalid @enderror"
                id="whitelist-email"
                name="email"
                value="{{ old('email') }}"
                required>
              @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>

          <div class="row">
            <div class="col-md-12 mb-1">
              <label class="form-label" for="whitelist-notes">Napomena</label>
              <textarea
                class="form-control @error('notes') is-invalid @enderror"
                id="whitelist-notes"
                name="notes"
                rows="4">{{ old('notes') }}</textarea>
              @error('notes')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>

          <div class="form-check form-switch mt-1">
            <input type="hidden" name="is_active" value="0">
            <input
              class="form-check-input"
              type="checkbox"
              id="whitelist-is-active"
              name="is_active"
              value="1"
              @checked(old('is_active', '1') === '1')>
            <label class="form-check-label" for="whitelist-is-active">Aktivan pošiljalac</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Odustani</button>
          <button type="submit" class="btn btn-primary">Sačuvaj</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="editWhitelistEntryModal" tabindex="-1" aria-labelledby="editWhitelistEntryModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editWhitelistEntryModalLabel">Uredi pošiljaoca</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zatvori"></button>
      </div>
      <form id="editWhitelistEntryForm" method="POST" autocomplete="off">
        @csrf
        @method('PUT')
        <div class="modal-body">
          <div class="alert alert-danger d-none whitelist-modal-feedback" data-form-feedback role="alert"></div>

          <div class="row">
            <div class="col-md-6 mb-1">
              <label class="form-label" for="edit-whitelist-name">Naziv / kompanija</label>
              <input
                type="text"
                class="form-control @error('name') is-invalid @enderror"
                id="edit-whitelist-name"
                name="name"
                value="">
              @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
            <div class="col-md-6 mb-1">
              <label class="form-label" for="edit-whitelist-email">Email</label>
              <input
                type="email"
                class="form-control @error('email') is-invalid @enderror"
                id="edit-whitelist-email"
                name="email"
                value=""
                required>
              @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>

          <div class="row">
            <div class="col-md-12 mb-1">
              <label class="form-label" for="edit-whitelist-notes">Napomena</label>
              <textarea
                class="form-control @error('notes') is-invalid @enderror"
                id="edit-whitelist-notes"
                name="notes"
                rows="4"></textarea>
              @error('notes')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>

          <div class="form-check form-switch mt-1">
            <input type="hidden" name="is_active" value="0">
            <input
              class="form-check-input"
              type="checkbox"
              id="edit-whitelist-is-active"
              name="is_active"
              value="1">
            <label class="form-check-label" for="edit-whitelist-is-active">Aktivan pošiljalac</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Odustani</button>
          <button type="submit" class="btn btn-primary">Sačuvaj izmjene</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="toggleWhitelistEntryModal" tabindex="-1" aria-labelledby="toggleWhitelistEntryModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="toggleWhitelistEntryModalLabel">Promjena statusa</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zatvori"></button>
      </div>
      <form id="toggleWhitelistEntryModalForm" method="POST" autocomplete="off">
        @csrf
        @method('PATCH')
        <div class="modal-body">
          <div class="alert alert-danger d-none whitelist-modal-feedback" data-form-feedback role="alert"></div>
          <p class="mb-0 whitelist-modal-copy" id="toggleWhitelistEntryMessage"></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Odustani</button>
          <button type="submit" class="btn btn-warning" id="toggleWhitelistEntrySubmitButton">Sačuvaj</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="deleteWhitelistEntryModal" tabindex="-1" aria-labelledby="deleteWhitelistEntryModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="deleteWhitelistEntryModalLabel">Obriši pošiljaoca</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zatvori"></button>
      </div>
      <form id="deleteWhitelistEntryModalForm" method="POST" autocomplete="off">
        @csrf
        @method('DELETE')
        <div class="modal-body">
          <div class="alert alert-danger d-none whitelist-modal-feedback" data-form-feedback role="alert"></div>
          <p class="mb-0 whitelist-modal-copy" id="deleteWhitelistEntryMessage"></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Odustani</button>
          <button type="submit" class="btn btn-danger" id="deleteWhitelistEntrySubmitButton">Obriši</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@section('vendor-script')
  <script src="{{ asset(mix('vendors/js/tables/datatable/jquery.dataTables.min.js')) }}"></script>
  <script src="{{ asset(mix('vendors/js/tables/datatable/dataTables.bootstrap5.min.js')) }}"></script>
  <script src="{{ asset(mix('vendors/js/tables/datatable/dataTables.responsive.min.js')) }}"></script>
  <script src="{{ asset(mix('vendors/js/tables/datatable/responsive.bootstrap5.js')) }}"></script>
  <script src="{{ asset(mix('vendors/js/tables/datatable/datatables.buttons.min.js')) }}"></script>
  <script src="{{ asset(mix('vendors/js/tables/datatable/buttons.bootstrap5.min.js')) }}"></script>
@endsection

@section('page-script')
  <script>
    const whitelistBaseUrl = '{{ url("app/ai-assistant/whitelist") }}';
    const whitelistCsrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    let whitelistEntriesState = {{ \Illuminate\Support\Js::from($entries->all()) }};
    let whitelistStatsState = {{ \Illuminate\Support\Js::from($stats) }};
    let whitelistTableInstance = null;
    let toggleWhitelistEntryId = null;
    let deleteWhitelistEntryId = null;

    function replaceFeatherIcons() {
      if (window.feather && typeof window.feather.replace === 'function') {
        window.feather.replace();
      }
    }

    function initWhitelistTooltips(scope) {
      const root = scope || document;

      if (window.bootstrap && window.bootstrap.Tooltip) {
        root.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (element) {
          const instance = window.bootstrap.Tooltip.getInstance(element);

          if (instance) {
            instance.dispose();
          }

          new window.bootstrap.Tooltip(element);
        });
      }
    }

    function escapeHtml(value) {
      return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    function formatWhitelistNumber(value) {
      return new Intl.NumberFormat('bs-BA').format(Number(value || 0));
    }

    function findWhitelistEntry(entryId) {
      const normalizedId = Number(entryId || 0);

      return whitelistEntriesState.find(function (entry) {
        return Number(entry.id || 0) === normalizedId;
      }) || null;
    }

    function updateWhitelistStats(stats) {
      const totalElement = document.querySelector('[data-whitelist-stat="total"]');
      const activeElement = document.querySelector('[data-whitelist-stat="active"]');

      if (totalElement) {
        totalElement.textContent = formatWhitelistNumber(stats.total || 0);
      }

      if (activeElement) {
        activeElement.textContent = formatWhitelistNumber(stats.active || 0);
      }
    }

    function showWhitelistFeedback(message, tone) {
      const feedback = document.getElementById('whitelistAsyncFeedback');

      if (!feedback) {
        return;
      }

      if (!message) {
        feedback.classList.add('d-none');
        feedback.textContent = '';
        feedback.classList.remove('alert-success', 'alert-danger', 'alert-warning', 'alert-info');
        return;
      }

      feedback.textContent = message;
      feedback.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning', 'alert-info');
      feedback.classList.add('alert-' + (tone || 'success'));
    }

    function clearWhitelistFormErrors(form) {
      if (!form) {
        return;
      }

      const feedback = form.querySelector('[data-form-feedback]');

      if (feedback) {
        feedback.classList.add('d-none');
        feedback.textContent = '';
      }

      form.querySelectorAll('.is-invalid').forEach(function (element) {
        element.classList.remove('is-invalid');
      });

      form.querySelectorAll('[data-generated-invalid-feedback="true"]').forEach(function (element) {
        element.remove();
      });
    }

    function setWhitelistFormError(form, fieldName, message) {
      const field = form.querySelector('[name="' + fieldName + '"]');

      if (!field) {
        return;
      }

      field.classList.add('is-invalid');

      let feedback = field.parentElement ? field.parentElement.querySelector('[data-generated-invalid-feedback="true"]') : null;

      if (!feedback) {
        feedback = document.createElement('div');
        feedback.className = 'invalid-feedback';
        feedback.dataset.generatedInvalidFeedback = 'true';
        field.insertAdjacentElement('afterend', feedback);
      }

      feedback.textContent = message;
    }

    function showWhitelistFormFeedback(form, message) {
      const feedback = form ? form.querySelector('[data-form-feedback]') : null;

      if (!feedback) {
        return;
      }

      feedback.textContent = message;
      feedback.classList.remove('d-none');
    }

    function applyWhitelistValidationErrors(form, errors, fallbackMessage) {
      clearWhitelistFormErrors(form);

      if (errors && typeof errors === 'object') {
        Object.keys(errors).forEach(function (fieldName) {
          const fieldErrors = Array.isArray(errors[fieldName]) ? errors[fieldName] : [errors[fieldName]];
          const firstError = fieldErrors.find(Boolean);

          if (firstError) {
            setWhitelistFormError(form, fieldName, firstError);
          }
        });
      }

      showWhitelistFormFeedback(form, fallbackMessage || 'Provjerite unesene podatke i pokušajte ponovo.');
    }

    function setWhitelistSubmitState(button, isSubmitting, loadingLabel) {
      if (!button) {
        return;
      }

      button.disabled = isSubmitting;

      if (isSubmitting) {
        button.dataset.originalLabel = button.innerHTML;
        button.innerHTML =
          '<span class="spinner-border spinner-border-sm me-50" role="status" aria-hidden="true"></span>' +
          escapeHtml(loadingLabel || 'Sačuvaj');
        return;
      }

      if (button.dataset.originalLabel) {
        button.innerHTML = button.dataset.originalLabel;
      }
    }

    function renderWhitelistStatusBadge(entry) {
      return (
        '<span class="whitelist-status-badge whitelist-status-badge--' +
        escapeHtml(entry.status_tone || 'secondary') +
        '">' +
        escapeHtml(entry.status_label || '-') +
        '</span>'
      );
    }

    function renderWhitelistActions(entry) {
      if (entry.is_read_only) {
        return (
          '<div class="app-table-action-group">' +
          '<span class="app-table-action-tooltip" data-bs-toggle="tooltip" data-bs-placement="top" title="Unos iz konfiguracije">' +
          '<button type="button" class="btn btn-sm app-table-action-btn app-table-action-btn--primary" disabled aria-label="Unos iz konfiguracije">' +
          feather.icons['lock'].toSvg({ class: 'font-small-4' }) +
          '</button>' +
          '</span>' +
          '</div>'
        );
      }

      const toggleTitle = entry.is_active ? 'Deaktiviraj pošiljaoca' : 'Aktiviraj pošiljaoca';
      const toggleIcon = entry.is_active ? 'pause-circle' : 'play-circle';
      const toggleClass = entry.is_active ? 'app-table-action-btn--warning' : 'app-table-action-btn--success';

      return (
        '<div class="app-table-action-group">' +
        '<button type="button" class="btn btn-sm app-table-action-btn app-table-action-btn--accent" data-whitelist-action="edit" data-entry-id="' +
        escapeHtml(entry.id) +
        '" data-bs-toggle="tooltip" data-bs-placement="top" title="Uredi pošiljaoca" aria-label="Uredi pošiljaoca">' +
        feather.icons['edit-2'].toSvg({ class: 'font-small-4' }) +
        '</button>' +
        '<button type="button" class="btn btn-sm app-table-action-btn ' +
        toggleClass +
        '" data-whitelist-action="toggle" data-entry-id="' +
        escapeHtml(entry.id) +
        '" data-bs-toggle="tooltip" data-bs-placement="top" title="' +
        escapeHtml(toggleTitle) +
        '" aria-label="' +
        escapeHtml(toggleTitle) +
        '">' +
        feather.icons[toggleIcon].toSvg({ class: 'font-small-4' }) +
        '</button>' +
        '<button type="button" class="btn btn-sm app-table-action-btn app-table-action-btn--danger" data-whitelist-action="delete" data-entry-id="' +
        escapeHtml(entry.id) +
        '" data-bs-toggle="tooltip" data-bs-placement="top" title="Obriši pošiljaoca" aria-label="Obriši pošiljaoca">' +
        feather.icons['trash-2'].toSvg({ class: 'font-small-4' }) +
        '</button>' +
        '</div>'
      );
    }

    function buildWhitelistTableRows() {
      return whitelistEntriesState.map(function (entry) {
        return [
          escapeHtml(entry.name || '-'),
          escapeHtml(entry.email || '-'),
          renderWhitelistStatusBadge(entry),
          escapeHtml(entry.last_received_mail_display || '-'),
          escapeHtml(entry.notes_display || '-'),
          escapeHtml(entry.created_at_display || '-'),
          renderWhitelistActions(entry),
        ];
      });
    }

    function refreshWhitelistTable() {
      const table = $('.whitelist-list-table');

      if (!table.length) {
        return;
      }

      const rows = buildWhitelistTableRows();

      if (whitelistTableInstance) {
        whitelistTableInstance.clear();
        whitelistTableInstance.rows.add(rows);
        whitelistTableInstance.draw(false);
        return;
      }

      whitelistTableInstance = table.DataTable({
        data: rows,
        order: [[1, 'asc']],
        columnDefs: [
          {
            targets: 4,
            className: 'whitelist-notes-cell',
          },
          {
            targets: -1,
            orderable: false,
            className: 'text-end whitelist-action-cell',
          },
        ],
        dom:
          '<"d-flex justify-content-between align-items-center header-actions mx-2 row mt-75"' +
          '<"col-sm-12 col-lg-4 d-flex justify-content-center justify-content-lg-start" l>' +
          '<"col-sm-12 col-lg-8 ps-xl-75 ps-0"<"dt-action-buttons d-flex align-items-center justify-content-center justify-content-lg-end flex-lg-nowrap flex-wrap"<"me-1"f>>>' +
          '>t' +
          '<"d-flex justify-content-between mx-2 row"' +
          '<"col-sm-12 col-md-6"i>' +
          '<"col-sm-12 col-md-6"p>' +
          '>',
        oLanguage: {
          sDecimal: '',
          sEmptyTable: 'Nema pošiljalaca u tabeli',
          sInfo: 'Prikazano _START_ do _END_ od _TOTAL_ unosa',
          sInfoEmpty: 'Prikazano 0 do 0 od 0 unosa',
          sInfoFiltered: '(filtrirano od _MAX_ ukupno unosa)',
          sInfoPostFix: '',
          sThousands: ',',
          sLengthMenu: 'Prikaži _MENU_',
          sLoadingRecords: 'Učitavanje...',
          sProcessing: 'Obrađuje se...',
          sSearch: 'Pretraži:',
          sSearchPlaceholder: 'Email ili naziv...',
          sZeroRecords: 'Nisu pronađeni odgovarajući zapisi',
          oPaginate: {
            sFirst: 'Prva',
            sLast: 'Poslednja',
            sNext: 'Sljedeća',
            sPrevious: 'Prethodna'
          },
          oAria: {
            sSortAscending: ': aktiviraj za rastuće sortiranje kolone',
            sSortDescending: ': aktiviraj za opadajuće sortiranje kolone'
          }
        },
        responsive: false,
        language: {
          paginate: {
            previous: '&nbsp;',
            next: '&nbsp;'
          }
        },
        drawCallback: function () {
          replaceFeatherIcons();
          initWhitelistTooltips(document);
        },
        initComplete: function () {
          replaceFeatherIcons();
          initWhitelistTooltips(document);
        }
      });
    }

    function applyWhitelistResponsePayload(payload) {
      if (Array.isArray(payload.entries)) {
        whitelistEntriesState = payload.entries;
      }

      if (payload.stats && typeof payload.stats === 'object') {
        whitelistStatsState = payload.stats;
      }

      updateWhitelistStats(whitelistStatsState);
      refreshWhitelistTable();
    }

    async function sendWhitelistRequest(url, method, payload) {
      const requestHeaders = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': whitelistCsrfToken,
      };

      if (payload !== undefined) {
        requestHeaders['Content-Type'] = 'application/json';
      }

      const response = await fetch(url, {
        method: method,
        headers: requestHeaders,
        body: payload !== undefined ? JSON.stringify(payload) : undefined,
      });

      const responseData = await response.json().catch(function () {
        return {};
      });

      if (!response.ok) {
        const error = new Error(responseData.message || 'Došlo je do greške.');
        error.status = response.status;
        error.payload = responseData;
        throw error;
      }

      return responseData;
    }

    function collectWhitelistFormPayload(form) {
      const isActiveCheckbox = form.querySelector('input[type="checkbox"][name="is_active"]');

      return {
        name: form.querySelector('[name="name"]')?.value || '',
        email: form.querySelector('[name="email"]')?.value || '',
        notes: form.querySelector('[name="notes"]')?.value || '',
        is_active: isActiveCheckbox && isActiveCheckbox.checked ? 1 : 0,
      };
    }

    function resetAddWhitelistForm() {
      const form = document.getElementById('addWhitelistEntryForm');

      if (!form) {
        return;
      }

      form.reset();
      clearWhitelistFormErrors(form);

      const activeField = document.getElementById('whitelist-is-active');

      if (activeField) {
        activeField.checked = true;
      }
    }

    function openEditWhitelistEntry(entryOrId) {
      const entry = typeof entryOrId === 'object' && entryOrId !== null && entryOrId.id
        ? entryOrId
        : findWhitelistEntry(entryOrId);

      if (!entry || !entry.id) {
        return;
      }

      const form = document.getElementById('editWhitelistEntryForm');

      if (!form) {
        return;
      }

      clearWhitelistFormErrors(form);
      form.action = whitelistBaseUrl + '/' + entry.id;
      document.getElementById('edit-whitelist-name').value = entry.name_raw || '';
      document.getElementById('edit-whitelist-email').value = entry.email || '';
      document.getElementById('edit-whitelist-notes').value = entry.notes_raw || '';
      document.getElementById('edit-whitelist-is-active').checked = entry.is_active === true || entry.is_active === 1 || entry.is_active === '1';

      $('#editWhitelistEntryModal').modal('show');
    }

    function openToggleWhitelistEntryModal(entryId) {
      const entry = findWhitelistEntry(entryId);

      if (!entry || !entry.id) {
        return;
      }

      toggleWhitelistEntryId = Number(entry.id);

      const title = entry.is_active ? 'Deaktiviraj pošiljaoca' : 'Aktiviraj pošiljaoca';
      const button = document.getElementById('toggleWhitelistEntrySubmitButton');
      const message = document.getElementById('toggleWhitelistEntryMessage');

      document.getElementById('toggleWhitelistEntryModalLabel').textContent = title;
      message.textContent = entry.is_active
        ? 'Da li ste sigurni da želite deaktivirati pošiljaoca ' + (entry.email || '-') + '?'
        : 'Da li ste sigurni da želite aktivirati pošiljaoca ' + (entry.email || '-') + '?';

      button.textContent = entry.is_active ? 'Deaktiviraj' : 'Aktiviraj';
      button.classList.remove('btn-warning', 'btn-success');
      button.classList.add(entry.is_active ? 'btn-warning' : 'btn-success');

      clearWhitelistFormErrors(document.getElementById('toggleWhitelistEntryModalForm'));
      $('#toggleWhitelistEntryModal').modal('show');
    }

    function openDeleteWhitelistEntryModal(entryId) {
      const entry = findWhitelistEntry(entryId);

      if (!entry || !entry.id) {
        return;
      }

      deleteWhitelistEntryId = Number(entry.id);
      document.getElementById('deleteWhitelistEntryMessage').textContent =
        'Da li ste sigurni da želite obrisati pošiljaoca ' + (entry.email || '-') + '? Ova akcija se ne može poništiti.';

      clearWhitelistFormErrors(document.getElementById('deleteWhitelistEntryModalForm'));
      $('#deleteWhitelistEntryModal').modal('show');
    }

    $(function () {
      const addForm = document.getElementById('addWhitelistEntryForm');
      const editForm = document.getElementById('editWhitelistEntryForm');
      const toggleForm = document.getElementById('toggleWhitelistEntryModalForm');
      const deleteForm = document.getElementById('deleteWhitelistEntryModalForm');
      const tableElement = document.querySelector('.whitelist-list-table');

      updateWhitelistStats(whitelistStatsState);
      refreshWhitelistTable();

      if (tableElement) {
        tableElement.addEventListener('click', function (event) {
          const actionButton = event.target.closest('[data-whitelist-action]');

          if (!actionButton) {
            return;
          }

          const entryId = Number(actionButton.dataset.entryId || 0);
          const action = actionButton.dataset.whitelistAction;

          if (action === 'edit') {
            openEditWhitelistEntry(entryId);
            return;
          }

          if (action === 'toggle') {
            openToggleWhitelistEntryModal(entryId);
            return;
          }

          if (action === 'delete') {
            openDeleteWhitelistEntryModal(entryId);
          }
        });
      }

      if (addForm) {
        addForm.addEventListener('submit', async function (event) {
          event.preventDefault();
          clearWhitelistFormErrors(addForm);
          const submitButton = addForm.querySelector('button[type="submit"]');

          setWhitelistSubmitState(submitButton, true, 'Sačuvaj');

          try {
            const response = await sendWhitelistRequest(addForm.action, 'POST', collectWhitelistFormPayload(addForm));
            applyWhitelistResponsePayload(response);
            showWhitelistFeedback(response.message, 'success');
            $('#addWhitelistEntryModal').modal('hide');
            resetAddWhitelistForm();
          } catch (error) {
            if (error.status === 422) {
              applyWhitelistValidationErrors(addForm, error.payload?.errors, error.payload?.message);
            } else {
              showWhitelistFormFeedback(addForm, error.message || 'Došlo je do greške prilikom čuvanja pošiljaoca.');
            }
          } finally {
            setWhitelistSubmitState(submitButton, false);
          }
        });
      }

      if (editForm) {
        editForm.addEventListener('submit', async function (event) {
          event.preventDefault();
          clearWhitelistFormErrors(editForm);
          const submitButton = editForm.querySelector('button[type="submit"]');

          setWhitelistSubmitState(submitButton, true, 'Sačuvaj izmjene');

          try {
            const response = await sendWhitelistRequest(editForm.action, 'PUT', collectWhitelistFormPayload(editForm));
            applyWhitelistResponsePayload(response);
            showWhitelistFeedback(response.message, 'success');
            $('#editWhitelistEntryModal').modal('hide');
          } catch (error) {
            if (error.status === 422) {
              applyWhitelistValidationErrors(editForm, error.payload?.errors, error.payload?.message);
            } else {
              showWhitelistFormFeedback(editForm, error.message || 'Došlo je do greške prilikom ažuriranja pošiljaoca.');
            }
          } finally {
            setWhitelistSubmitState(submitButton, false);
          }
        });
      }

      if (toggleForm) {
        toggleForm.addEventListener('submit', async function (event) {
          event.preventDefault();

          if (!toggleWhitelistEntryId) {
            return;
          }

          clearWhitelistFormErrors(toggleForm);
          const submitButton = document.getElementById('toggleWhitelistEntrySubmitButton');

          setWhitelistSubmitState(submitButton, true, submitButton.textContent.trim() || 'Sačuvaj');

          try {
            const response = await sendWhitelistRequest(whitelistBaseUrl + '/' + toggleWhitelistEntryId + '/toggle', 'PATCH');
            applyWhitelistResponsePayload(response);
            showWhitelistFeedback(response.message, 'success');
            $('#toggleWhitelistEntryModal').modal('hide');
            toggleWhitelistEntryId = null;
          } catch (error) {
            showWhitelistFormFeedback(toggleForm, error.message || 'Došlo je do greške prilikom promjene statusa.');
          } finally {
            setWhitelistSubmitState(submitButton, false);
          }
        });
      }

      if (deleteForm) {
        deleteForm.addEventListener('submit', async function (event) {
          event.preventDefault();

          if (!deleteWhitelistEntryId) {
            return;
          }

          clearWhitelistFormErrors(deleteForm);
          const submitButton = document.getElementById('deleteWhitelistEntrySubmitButton');

          setWhitelistSubmitState(submitButton, true, 'Obriši');

          try {
            const response = await sendWhitelistRequest(whitelistBaseUrl + '/' + deleteWhitelistEntryId, 'DELETE');
            applyWhitelistResponsePayload(response);
            showWhitelistFeedback(response.message, 'success');
            $('#deleteWhitelistEntryModal').modal('hide');
            deleteWhitelistEntryId = null;
          } catch (error) {
            showWhitelistFormFeedback(deleteForm, error.message || 'Došlo je do greške prilikom brisanja pošiljaoca.');
          } finally {
            setWhitelistSubmitState(submitButton, false);
          }
        });
      }

      $('#addWhitelistEntryModal').on('hidden.bs.modal', function () {
        if (!$('.modal.show').length) {
          clearWhitelistFormErrors(addForm);
        }
      });

      $('#editWhitelistEntryModal').on('hidden.bs.modal', function () {
        clearWhitelistFormErrors(editForm);
      });

      $('#toggleWhitelistEntryModal').on('hidden.bs.modal', function () {
        toggleWhitelistEntryId = null;
        clearWhitelistFormErrors(toggleForm);
      });

      $('#deleteWhitelistEntryModal').on('hidden.bs.modal', function () {
        deleteWhitelistEntryId = null;
        clearWhitelistFormErrors(deleteForm);
      });

      @if (session('openWhitelistEditModal'))
        openEditWhitelistEntry({{ \Illuminate\Support\Js::from([
          'id' => session('openWhitelistEditModalId'),
          'name_raw' => old('name', ''),
          'email' => old('email', ''),
          'notes_raw' => old('notes', ''),
          'is_active' => old('is_active', '1') === '1',
        ]) }});
      @elseif ($errors->any() || session('openWhitelistModal'))
        $('#addWhitelistEntryModal').modal('show');
      @endif

      replaceFeatherIcons();
      initWhitelistTooltips(document);
    });
  </script>
@endsection
