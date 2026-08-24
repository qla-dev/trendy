@extends('layouts/contentLayoutMaster')

@section('title', 'Površinske zaštite')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('vendors/css/tables/datatable/dataTables.bootstrap5.min.css') }}">
<link rel="stylesheet" href="{{ asset('vendors/css/tables/datatable/responsive.bootstrap5.min.css') }}">
<link rel="stylesheet" href="{{ asset('vendors/css/extensions/sweetalert2.min.css') }}">
@endsection

@section('page-style')
<style>
  .content-header { margin-top: -6px; margin-bottom: 4px; }
  .content-header-title { margin-top: 5px; }
  .protection-catalogue-wrapper { --protection-scroll-track: var(--app-scroll-track); --protection-scroll-thumb: var(--app-scroll-thumb-flat); --protection-scroll-thumb-hover: var(--app-scroll-thumb-flat-hover); --protection-scroll-thumb-border: var(--app-scroll-thumb-border); }
  .protection-catalogue-wrapper .protection-table { min-width: {{ $canManageProtections ? '860px' : '720px' }}; }
  .protection-catalogue-wrapper .card-datatable.table-responsive { overflow-x: visible; }
  .protection-catalogue-wrapper .card-datatable .dataTables_wrapper > .row:first-child, .protection-catalogue-wrapper .card-datatable .dataTables_wrapper > .row:last-child { margin-left: 0; margin-right: 0; padding: 1rem 1rem .95rem; }
  .protection-catalogue-wrapper .card-datatable .dataTables_wrapper > .row:first-child > [class*='col-'], .protection-catalogue-wrapper .card-datatable .dataTables_wrapper > .row:last-child > [class*='col-'] { padding-left: 0; padding-right: 0; }
  .protection-catalogue-wrapper .card-datatable .dataTables_wrapper > .row:nth-child(2) { margin-left: 0; margin-right: 0; }
  .protection-catalogue-wrapper .card-datatable .dataTables_wrapper > .row:nth-child(2) > [class*='col-'] { padding-left: 0; padding-right: 0; overflow-x: auto; overflow-y: hidden; -webkit-overflow-scrolling: touch; scrollbar-width: thin; scrollbar-color: var(--protection-scroll-thumb) var(--protection-scroll-track); scrollbar-gutter: stable; }
  .protection-catalogue-wrapper .card-datatable .dataTables_wrapper > .row:nth-child(2) > [class*='col-']::-webkit-scrollbar { width: 8px; height: 8px; }
  .protection-catalogue-wrapper .card-datatable .dataTables_wrapper > .row:nth-child(2) > [class*='col-']::-webkit-scrollbar-track { background: var(--protection-scroll-track); border-radius: 999px; }
  .protection-catalogue-wrapper .card-datatable .dataTables_wrapper > .row:nth-child(2) > [class*='col-']::-webkit-scrollbar-thumb { background: var(--protection-scroll-thumb); border: 1px solid var(--protection-scroll-thumb-border); border-radius: 999px; }
  .protection-table tbody tr { cursor: pointer; transition: background-color .2s ease; }
  .protection-table.table tbody tr:hover > * { background-color: #f8f8fc; }
  .protection-code-cell, .protection-weeks-cell, .protection-actions-cell { white-space: nowrap; }
  .protection-code-cell { font-weight: 700; letter-spacing: .01em; }
  .protection-actions-cell { width: 1% !important; position: sticky !important; right: 0 !important; z-index: 10 !important; background: #fff !important; border-left: 1px solid #ebe9f1 !important; }
  .protection-table thead .protection-actions-cell { z-index: 11 !important; background: #f8f8fa !important; }
  .protection-table.table tbody tr:hover > .protection-actions-cell { background: #f8f8fc !important; }
  body.dark-layout .protection-table.table tbody tr:hover > *, body.semi-dark-layout .protection-table.table tbody tr:hover > * { background-color: #36405a !important; }
  body.dark-layout .protection-actions-cell, body.semi-dark-layout .protection-actions-cell { background: #283046 !important; border-left-color: rgba(184,190,220,.22) !important; }
  body.dark-layout .protection-table thead .protection-actions-cell, body.semi-dark-layout .protection-table thead .protection-actions-cell { background: #2f3854 !important; }
  body.dark-layout .protection-table.table tbody tr:hover > .protection-actions-cell, body.semi-dark-layout .protection-table.table tbody tr:hover > .protection-actions-cell { background: #36405a !important; }
  @media (min-width: 768px) { .protection-catalogue-wrapper .card-datatable .dataTables_wrapper > .row:first-child { display: flex; align-items: flex-start; justify-content: space-between; column-gap: 1rem; row-gap: .75rem; } .protection-catalogue-wrapper .card-datatable .dataTables_wrapper > .row:first-child > [class*='col-']:first-child { flex: 1 1 auto; width: auto; max-width: none; } .protection-catalogue-wrapper .card-datatable .dataTables_wrapper > .row:first-child > [class*='col-']:last-child { flex: 0 0 auto; width: auto; max-width: none; margin-left: auto; } }
  @media (max-width: 767.98px) { .protection-catalogue-wrapper .card-datatable .dataTables_wrapper > .row:first-child, .protection-catalogue-wrapper .card-datatable .dataTables_wrapper > .row:last-child { padding: .85rem .85rem .8rem; } }
</style>
@endsection

@section('content')
<section class="protection-catalogue-wrapper">
  <div class="content-header row">
    <div class="content-header-left col-md-3 col-lg-6 col-12 mb-2"><div class="row breadcrumbs-top"><div class="col-12"><h2 class="content-header-title float-start mb-0">Površinske zaštite</h2></div></div></div>
    @if($canManageProtections)<div class="content-header-right text-md-end col-md-9 col-lg-6 col-12"><div class="mb-1 breadcrumb-right"><button type="button" id="protection-add-btn" class="btn btn-primary"><i data-feather="plus" class="me-50"></i> Dodaj novu zaštitu</button></div></div>@endif
  </div>
  <div class="card"><div class="card-datatable table-responsive"><table class="table protection-table" id="protection-table"><thead><tr><th>Šifra</th><th>Naziv</th><th>Rok izrade</th><th>Napomena</th>@if($canManageProtections)<th class="protection-actions-cell">Akcija</th>@endif</tr></thead><tbody></tbody></table></div></div>
</section>

@if($canManageProtections)
<div class="modal fade" id="protection-catalogue-modal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Dodaj zaštitu</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="mb-1"><label class="form-label">Kod</label><input id="catalogue-protection-code" class="form-control" maxlength="16"></div><div class="mb-1"><label class="form-label">Naziv</label><input id="catalogue-protection-name" class="form-control" maxlength="100"></div><div><label class="form-label">Rok izrade (sedmice)</label><input id="catalogue-protection-weeks" class="form-control" type="number" min="1" max="52" value="3"></div><div id="catalogue-protection-error" class="text-danger small mt-1 d-none"></div></div><div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Otkaži</button><button id="catalogue-protection-save" class="btn btn-primary">Sačuvaj</button></div></div></div></div>
<div class="modal fade" id="edit-protection-modal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Uredi zaštitu</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div id="edit-protection-error" class="alert alert-danger d-none"></div><div class="mb-1"><label class="form-label">Kod</label><input id="edit-protection-code" class="form-control" readonly></div><div class="mb-1"><label class="form-label">Naziv</label><input id="edit-protection-name" class="form-control" maxlength="100"></div><div class="mb-1"><label class="form-label">Rok izrade (sedmice)</label><input id="edit-protection-weeks" type="number" min="1" max="52" class="form-control"></div><div><label class="form-label">Napomena</label><textarea id="edit-protection-note" rows="4" maxlength="4000" class="form-control"></textarea></div></div><div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Otkaži</button><button id="edit-protection-save" class="btn btn-primary">Sačuvaj</button></div></div></div></div>
@endif
@endsection

@section('vendor-script')
<script src="{{ asset('vendors/js/tables/datatable/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('vendors/js/tables/datatable/dataTables.bootstrap5.min.js') }}"></script>
<script src="{{ asset('vendors/js/tables/datatable/dataTables.responsive.min.js') }}"></script>
<script src="{{ asset('vendors/js/tables/datatable/responsive.bootstrap5.js') }}"></script>
<script src="{{ asset('vendors/js/extensions/sweetalert2.all.min.js') }}"></script>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  var url = @json($catalogueUrl), storeUrl = @json($storeUrl), updateTpl = @json($updateUrlTemplate), destroyTpl = @json($destroyUrlTemplate), canManage = @json($canManageProtections), token = @json(csrf_token()), rows = [];
  var tableElement = $('#protection-table'), table;
  function esc(value) { var d = document.createElement('div'); d.textContent = value == null ? '' : String(value); return d.innerHTML; }
  function textOrDash(value) { value = value == null ? '' : String(value).trim(); return value ? esc(value) : '<span class="text-muted">-</span>'; }
  function refreshTable(resetPaging) { return fetch(url, { headers: { Accept: 'application/json' } }).then(function (response) { if (!response.ok) throw new Error('Učitavanje zaštita nije uspjelo.'); return response.json(); }).then(function (payload) { rows = payload.data || []; table.clear().rows.add(rows).draw(resetPaging === false ? false : true); }); }
  table = tableElement.DataTable({ responsive: false, pageLength: 25, lengthMenu: [10, 25, 50, 100], searchDelay: 250, order: [[0, 'asc']], data: rows, columns: [{ data: 'code', className: 'protection-code-cell', render: textOrDash }, { data: null, render: function (data, type, row) { return textOrDash(row.description || row.label); } }, { data: 'weeks', className: 'protection-weeks-cell', render: function (data) { return '<span class="badge bg-light-primary text-primary">' + esc(data) + ' sedm.</span>'; } }, { data: 'note', render: textOrDash }].concat(canManage ? [{ data: null, orderable: false, searchable: false, className: 'text-end protection-actions-cell', render: function () { return '<div class="d-inline-flex gap-50"><button type="button" class="btn btn-sm app-table-action-btn app-table-action-btn--primary edit-protection" data-bs-toggle="tooltip" data-bs-placement="top" title="Uredi" aria-label="Uredi"><i class="fa fa-pencil"></i></button><button type="button" class="btn btn-sm app-table-action-btn app-table-action-btn--danger delete-protection" data-bs-toggle="tooltip" data-bs-placement="top" title="Obriši" aria-label="Obriši"><i class="fa fa-trash"></i></button></div>'; } }] : []), language: { search: 'Pretraga:', lengthMenu: 'Prikaži _MENU_ zaštita', info: 'Prikaz _START_ do _END_ od _TOTAL_ zaštita', infoEmpty: 'Nema zaštita za prikaz', infoFiltered: '(filtrirano od _MAX_ ukupno)', emptyTable: 'Nema zaštita za prikaz.', zeroRecords: 'Nema rezultata za zadanu pretragu.', paginate: { first: 'Prva', last: 'Zadnja', next: 'Sljedeća', previous: 'Prethodna' } }, drawCallback: function () { if (window.feather) window.feather.replace(); } });
  refreshTable();
  if (!canManage) return;
  var modalEl = document.getElementById('protection-catalogue-modal'), modal = new bootstrap.Modal(modalEl), error = document.getElementById('catalogue-protection-error');
  document.getElementById('protection-add-btn').onclick = function () { error.classList.add('d-none'); document.getElementById('catalogue-protection-code').value = ''; document.getElementById('catalogue-protection-name').value = ''; document.getElementById('catalogue-protection-weeks').value = 3; modal.show(); };
  document.getElementById('catalogue-protection-save').onclick = function () { var payload = { code: document.getElementById('catalogue-protection-code').value.trim(), name: document.getElementById('catalogue-protection-name').value.trim(), weeks: Number(document.getElementById('catalogue-protection-weeks').value) }; fetch(storeUrl, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token }, body: JSON.stringify(payload) }).then(function (response) { return response.json().then(function (data) { if (!response.ok) throw new Error(data.message || 'Spremanje nije uspjelo.'); return data; }); }).then(function () { modal.hide(); return refreshTable(false); }).catch(function (exception) { error.textContent = exception.message; error.classList.remove('d-none'); }); };
  var editModalEl = document.getElementById('edit-protection-modal'), editModal = new bootstrap.Modal(editModalEl), editing = null;
  function openEditModal(item) { var editError = document.getElementById('edit-protection-error'); editing = item; document.getElementById('edit-protection-code').value = item.code; document.getElementById('edit-protection-name').value = item.description || item.label || ''; document.getElementById('edit-protection-weeks').value = item.weeks; document.getElementById('edit-protection-note').value = item.note || ''; if (editError) editError.classList.add('d-none'); editModal.show(); }
  tableElement.on('click', 'tbody tr', function (event) { var item = table.row(this).data(); if (!item) return; if (event.target.closest('.delete-protection')) { if (!confirm('Obrisati ' + item.code + '?')) return; fetch(destroyTpl.replace('__CODE__', encodeURIComponent(item.code)), { method: 'DELETE', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token } }).then(function (response) { return response.json().then(function (data) { if (!response.ok) throw new Error(data.message); return data; }); }).then(function () { return refreshTable(false); }).catch(function (exception) { alert(exception.message); }); return; } openEditModal(item); });
  document.getElementById('edit-protection-save').onclick = function () { if (!editing) return; var payload = { name: document.getElementById('edit-protection-name').value.trim(), weeks: Number(document.getElementById('edit-protection-weeks').value), note: document.getElementById('edit-protection-note').value.trim() }; fetch(updateTpl.replace('__CODE__', encodeURIComponent(editing.code)), { method: 'PUT', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token }, body: JSON.stringify(payload) }).then(function (response) { return response.json().then(function (data) { if (!response.ok) throw new Error(data.message); return data; }); }).then(function () { editModal.hide(); return refreshTable(false); }).then(function () { if (window.Swal && typeof window.Swal.fire === 'function') window.Swal.fire({ icon: 'success', title: 'Zaštita je ažurirana', text: 'Izmjene su uspješno sačuvane.', timer: 1600, showConfirmButton: false }); }).catch(function (exception) { var editError = document.getElementById('edit-protection-error'); if (editError) { editError.textContent = exception.message; editError.classList.remove('d-none'); } if (window.Swal && typeof window.Swal.fire === 'function') window.Swal.fire({ icon: 'error', title: 'Spremanje nije uspjelo', text: exception.message }); }); };
});
</script>
@endsection
