@php
  $currentStatus = $currentStatus ?? '';
@endphp

<div class="modal fade" id="change-status-modal" tabindex="-1" aria-labelledby="change-status-modal-label" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="change-status-modal-label">Promjena statusa</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zatvori"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted mb-1">Odaberite novi status radnog naloga.</p>
        <select class="form-select" id="wo-status-select">
          @php
            $statusOptions = [
              'Planiran',
              'Otvoren',
              'Rezerviran',
              'Raspisan',
              'U radu',
              'Djelimično završen',
              'Završen',
            ];
          @endphp
          @foreach($statusOptions as $statusOption)
            <option value="{{ $statusOption }}" {{ strcasecmp($currentStatus, $statusOption) === 0 ? 'selected' : '' }}>
              {{ $statusOption }}
            </option>
          @endforeach
        </select>
        <small class="text-muted d-block mt-75">Demo modal (frontend only). Backend update trenutno nije aktiviran.</small>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Otkaži</button>
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Sačuvaj</button>
      </div>
    </div>
  </div>
</div>
