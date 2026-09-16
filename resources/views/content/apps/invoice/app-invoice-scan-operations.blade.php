@extends('layouts/contentLayoutMaster')

@section('title', 'Operacije radnog naloga')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('vendors/css/extensions/sweetalert2.min.css') }}">
@endsection

@section('page-style')
<style>
  .scan-operations-page { min-height: calc(100vh - 14rem); min-height: calc(100svh - 14rem); padding: .25rem 0 calc(4rem + env(safe-area-inset-bottom, 0px)); }
  .scan-operations-shell { width: 100%; max-width: 900px; }
  .scan-operations-card { overflow: hidden; border-radius: .428rem; }
  .scan-operations-card-header { display: flex; justify-content: center; align-items: center; padding: 1rem 1.2rem; border-bottom: 1px solid rgba(34,41,47,.08); }
  .scan-operations-card-header strong { font-size: 1.05rem; text-align: center; }
  .scan-operations-list { padding: .75rem; display: grid; gap: .65rem; }
  .scan-operation-row { width: 100%; min-height: 78px; display: grid; grid-template-columns: 48px minmax(0,1fr) 28px; align-items: center; gap: 1rem; padding: .85rem 1rem; border-radius: 10px; text-align: left; border: 1px solid transparent; transition: .18s ease; font: inherit; -webkit-appearance: none; appearance: none; -webkit-tap-highlight-color: transparent; touch-action: manipulation; }
  .scan-operation-position { width: 42px; height: 42px; display: grid; place-items: center; border-radius: 9px; font-weight: 800; }
  .scan-operation-copy { min-width: 0; display: flex; flex-direction: column; gap: .25rem; }
  .scan-operation-code { font-size: .76rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
  .scan-operation-name { font-size: 1.05rem; font-weight: 700; }
  .scan-operation-finished-check { width: 22px; height: 22px; display: inline-grid; place-items: center; border: 2px solid #b9b7c1; border-radius: .25rem; color: transparent; }
  .scan-operation-finished-check::after { content: '✓'; font-size: .9rem; font-weight: 800; line-height: 1; }
  .scan-operation-finished-check.is-checked { color: #fff; background: #28c76f; border-color: #28c76f; }
  .scan-operation-row.is-disabled { color: #9a97a5; background: #f8f8f8; border-color: #ebe9f1; cursor: not-allowed; opacity: .64; }
  .scan-operation-row.is-disabled .scan-operation-position { background: #ebe9f1; }
  .scan-operation-row.is-enabled { color: #3b4253; background: rgba(40,199,111,.08); border-color: rgba(40,199,111,.42); cursor: pointer; box-shadow: 0 4px 15px rgba(40,199,111,.06); }
  .scan-operation-row.is-enabled .scan-operation-position { color: #111c17; background: #65dba0; }
  .scan-operation-row.is-enabled .scan-operation-code { color: #65dba0; }
  .scan-operation-row.is-enabled:hover { transform: translateY(-2px); border-color: #65dba0; box-shadow: 0 12px 30px rgba(40,199,111,.15); }
  .scan-operation-row.is-finished { color: #6e6b7b; background: rgba(40,199,111,.08); border-color: rgba(40,199,111,.2); opacity: .72; cursor: default; }
  .scan-operation-row.is-finished .scan-operation-position { color: #16824a; background: rgba(40,199,111,.18); }
  .scan-operation-row.is-saving { opacity: .7; cursor: wait; }
  .scan-operation-row.is-complete { background: rgba(40,199,111,.27); }
  .scan-operations-empty { padding: 3rem 1rem; text-align: center; color: #8790a6; }
  .scan-operations-empty i { font-size: 2rem; margin-bottom: .75rem; }
  body.dark-layout .scan-operations-card-header, body.semi-dark-layout .scan-operations-card-header { border-bottom-color: rgba(184,190,220,.15); }
  body.dark-layout .scan-operation-row.is-disabled, body.semi-dark-layout .scan-operation-row.is-disabled { color: #747d92; background: #202533; border-color: rgba(184,190,220,.1); }
  body.dark-layout .scan-operation-row.is-disabled .scan-operation-position, body.semi-dark-layout .scan-operation-row.is-disabled .scan-operation-position { background: #303747; }
  body.dark-layout .scan-operation-row.is-enabled, body.semi-dark-layout .scan-operation-row.is-enabled { color: #e8e9ed; background: rgba(40,199,111,.12); }
  body.dark-layout .scan-operation-row.is-finished, body.semi-dark-layout .scan-operation-row.is-finished { color: #aab3c6; }
  body.dark-layout .scan-operation-finished-check, body.semi-dark-layout .scan-operation-finished-check { border-color: #6d7486; }
  @media (max-width: 575.98px) {
    .scan-operations-page { padding: .75rem 0 calc(4.5rem + env(safe-area-inset-bottom, 0px)); }
    .scan-operation-row { grid-template-columns: 42px minmax(0,1fr) 26px; gap: .7rem; }
  }
</style>
@endsection

@section('content')
@php
  $workOrderNumber = trim((string) ($workOrder['broj_naloga'] ?? $workOrder['id'] ?? ''));
@endphp

<div class="scan-operations-page">
  <div class="scan-operations-shell">
    <div class="card scan-operations-card mb-0">
      <div class="scan-operations-card-header">
        <strong>{{ $workOrderNumber }}</strong>
      </div>

      <div class="scan-operations-list" role="list">
        @forelse($operations as $operation)
          @php
            $enabled = (bool) ($operation['checkpoint_enabled'] ?? false);
            $finished = (bool) ($operation['is_finished'] ?? false);
            $operationId = (string) ($operation['id'] ?? '');
          @endphp
          <button type="button"
            class="scan-operation-row {{ $finished ? 'is-finished' : ($enabled ? 'is-enabled' : 'is-disabled') }}"
            data-operation-id="{{ $operationId }}"
            data-operation-name="{{ $operation['naziv'] ?? $operation['operacija'] ?? 'Operacija' }}"
            {{ (!$enabled || $operationId === '') ? 'disabled' : '' }} role="listitem">
            <span class="scan-operation-position">{{ $operation['pozicija'] ?: $loop->iteration }}</span>
              <span class="scan-operation-copy">
                <span class="scan-operation-code">{{ $operation['operacija'] ?: '—' }}</span>
                <span class="scan-operation-name">{{ $operation['naziv'] ?: 'Operacija bez naziva' }}</span>
              </span>
              <span class="scan-operation-finished-check {{ $finished ? 'is-checked' : '' }}" aria-label="{{ $finished ? 'Operacija završena' : 'Operacija nije završena' }}"></span>
            </button>
        @empty
          <div class="scan-operations-empty"><i class="fa fa-list-alt"></i><h3>Nema operacija</h3><p>Na stavkama ovog radnog naloga nisu pronađene operacije.</p></div>
        @endforelse
      </div>
    </div>

  </div>
</div>

@include('content.new-components.nalog-scan')
@endsection

@section('footer-actions')
  <button class="btn btn-success d-flex justify-content-center align-items-center" data-bs-toggle="modal" data-bs-target="#qr-scanner-modal">
    <i class="fa fa-qrcode me-50"></i> Skeniraj radni nalog
  </button>
  <a class="btn btn-outline-primary" href="{{ $previewUrl }}">Detalji RN</a>
@endsection

@section('vendor-script')
<script src="{{ asset('vendors/js/extensions/sweetalert2.all.min.js') }}"></script>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  var checkpointUrl = @json($checkpointUrl);
  var csrfToken = @json(csrf_token());

  document.querySelectorAll('.scan-operation-row.is-enabled').forEach(function (button) {
    button.addEventListener('click', async function () {
      if (button.disabled) return;
      var operationName = button.getAttribute('data-operation-name') || 'operaciju';
      var result = await Swal.fire({
        title: 'Potvrdi kontrolnu tačku', text: 'Želite li označiti operaciju "' + operationName + '"?', icon: 'question',
        showCancelButton: true, confirmButtonText: 'Da, označi', cancelButtonText: 'Odustani', reverseButtons: true,
        customClass: { confirmButton: 'btn btn-success ms-1', cancelButton: 'btn btn-outline-secondary' }, buttonsStyling: false
      });
      if (!result.isConfirmed) return;

      button.disabled = true;
      button.classList.add('is-saving');
      try {
        var response = await fetch(checkpointUrl, {
          method: 'POST', credentials: 'same-origin',
          headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
          body: JSON.stringify({ operation_id: button.getAttribute('data-operation-id') })
        });
        var payload = await response.json().catch(function () { return {}; });
        if (!response.ok) throw new Error(payload.message || 'Kontrolna tačka nije sačuvana.');
        button.classList.remove('is-saving');
        button.classList.remove('is-enabled');
        button.classList.add('is-finished');
        button.querySelector('.scan-operation-finished-check').classList.add('is-checked');
        await Swal.fire({ title: 'Evidentirano', text: payload.message || 'Kontrolna tačka je sačuvana.', icon: 'success', confirmButtonText: 'U redu' });
      } catch (error) {
        button.disabled = false;
        button.classList.remove('is-saving');
        Swal.fire({ title: 'Greška', text: error.message || 'Kontrolna tačka nije sačuvana.', icon: 'error', confirmButtonText: 'U redu' });
      }
    });
  });
});
</script>
@endsection
