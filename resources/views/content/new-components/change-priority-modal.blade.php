@php
  $currentPriority = $currentPriority ?? '';
@endphp

<div class="modal fade" id="change-priority-modal" tabindex="-1" aria-labelledby="change-priority-modal-label" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="change-priority-modal-label">Promjena prioriteta</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zatvori"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted mb-1">Odaberite novi prioritet radnog naloga.</p>
        <select class="form-select" id="wo-priority-select">
          @php
            $priorityOptions = [
              '1 - Visoki prioritet',
              '5 - Uobičajeni prioritet',
              '10 - Niski prioritet',
              '15 - Uzorci',
            ];
          @endphp
          @foreach($priorityOptions as $priorityOption)
            <option value="{{ $priorityOption }}" {{ strcasecmp($currentPriority, $priorityOption) === 0 ? 'selected' : '' }}>
              {{ $priorityOption }}
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
