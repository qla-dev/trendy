@php
  $currentStatus = (string) ($currentStatus ?? '');
  $normalizeStatus = static function (string $value): string {
    $normalizedValue = strtolower(trim(\Illuminate\Support\Str::ascii($value)));
    $normalizedValue = preg_replace('/\s+/', ' ', $normalizedValue);

    return trim((string) $normalizedValue);
  };
  $normalizedCurrentStatus = $normalizeStatus($currentStatus);
  $statusAliases = [
    'novo' => 'planiran',
    'u toku' => 'u radu',
    'djelimicno zavrseno' => 'djelimicno zavrsen',
    'djelimično završeno' => 'djelimicno zavrsen',
    'zavrseno' => 'zavrsen',
    'završeno' => 'zavrsen',
  ];

  if (array_key_exists($normalizedCurrentStatus, $statusAliases)) {
    $normalizedCurrentStatus = (string) $statusAliases[$normalizedCurrentStatus];
  }
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
            @php
              $normalizedOptionStatus = $normalizeStatus($statusOption);
            @endphp
            <option value="{{ $statusOption }}" {{ $normalizedCurrentStatus === $normalizedOptionStatus ? 'selected' : '' }}>
              {{ $statusOption }}
            </option>
          @endforeach
        </select>
        <small class="text-muted d-block mt-75">Odabir će biti sačuvan nakon potvrde.</small>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Otkaži</button>
        <button type="button" class="btn btn-primary" id="wo-status-save-btn" data-default-label="Sačuvaj">Sačuvaj</button>
      </div>
    </div>
  </div>
</div>
