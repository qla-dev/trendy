@php
  $priorityOptions = $priorityOptions ?? app(\App\Services\WorkOrder\DeliveryPriorityOptions::class)->all();
  $currentPriority = (string) ($currentPriority ?? '');
  $currentPriorityCode = null;

  if (preg_match('/^\s*(\d+)/', $currentPriority, $currentPriorityMatches) === 1) {
    $currentPriorityCode = (int) ($currentPriorityMatches[1] ?? 0);
  }
@endphp

<div class="modal fade" id="change-priority-modal" tabindex="-1" aria-labelledby="change-priority-modal-label" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="change-priority-modal-label">Promjena prioriteta</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zatvori"></button>
      </div>
      <div class="modal-body">
        <p class="text mb-1">Odaberite novi prioritet radnog naloga.</p>
        <select class="form-select" id="wo-priority-select" style="cursor: pointer;">
          @foreach(($priorityOptions ?? []) as $priorityOption)
            @php
              $priorityOptionCode = (int) ($priorityOption['code'] ?? 0);
              $priorityOptionLabel = (string) ($priorityOption['label'] ?? '');
              $isPrioritySelected = $currentPriorityCode !== null
                ? $currentPriorityCode === $priorityOptionCode
                : strcasecmp($currentPriority, $priorityOptionLabel) === 0;
            @endphp
            <option value="{{ $priorityOptionLabel }}" {{ $isPrioritySelected ? 'selected' : '' }} style="cursor: pointer;">
              {{ $priorityOptionLabel }}
            </option>
          @endforeach
        </select>
        <small class="d-block mt-75">Odabir će biti sačuvan nakon potvrde.</small>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Otkaži</button>
        <button type="button" class="btn btn-primary" id="wo-priority-save-btn" data-default-label="Sačuvaj">Sačuvaj</button>
      </div>
    </div>
  </div>
</div>
