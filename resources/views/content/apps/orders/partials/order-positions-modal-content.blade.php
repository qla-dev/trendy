@php
  $orderSummary = (array) ($orderSummary ?? []);
  $positions = (array) ($positions ?? []);
  $linkageToneClass = match ((string) ($orderSummary['linkage_tone'] ?? 'secondary')) {
      'danger' => 'badge-light-danger',
      'warning' => 'badge-light-warning',
      'success' => 'badge-light-success',
      default => 'badge-light-secondary',
  };
@endphp

<div class="order-linkage-modal-summary-grid">
  <div class="order-linkage-modal-summary-card">
    <span class="order-linkage-modal-summary-label">Narudzba</span>
    <span class="order-linkage-modal-summary-value">{{ $orderSummary['order_number'] ?? '-' }}</span>
  </div>
  <div class="order-linkage-modal-summary-card">
    <span class="order-linkage-modal-summary-label">Narucitelj</span>
    <span class="order-linkage-modal-summary-value">{{ $orderSummary['narucitelj'] ?? $orderSummary['customer'] ?? '-' }}</span>
  </div>
  <div class="order-linkage-modal-summary-card">
    <span class="order-linkage-modal-summary-label">Veza</span>
    <span class="order-linkage-modal-summary-value">
      <span class="badge {{ $linkageToneClass }} order-linkage-indicator">{{ $orderSummary['linkage_label'] ?? 'N/A' }}</span>
    </span>
  </div>
  <div class="order-linkage-modal-summary-card">
    <span class="order-linkage-modal-summary-label">Broj pozicija</span>
    <span class="order-linkage-modal-summary-value">{{ $orderSummary['position_count'] ?? count($positions) }}</span>
  </div>
</div>

<div class="order-linkage-modal-table-wrap">
  <div class="table-responsive">
    <table class="table order-linkage-modal-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>RN</th>
          <th>Pozicija</th>
          <th>Artikal</th>
          <th>Opis</th>
          <th>Napomena</th>
          <th class="text-end">Kolicina</th>
          <th>JM</th>
        </tr>
      </thead>
      <tbody>
        @forelse($positions as $position)
          <tr>
            <td>{{ $position['id'] ?? '-' }}</td>
            <td>{{ $position['rn_number'] ?? '-' }}</td>
            <td>{{ $position['pozicija'] ?? '-' }}</td>
            <td>{{ $position['sifra'] ?? '-' }}</td>
            <td>{{ $position['naziv'] ?? '-' }}</td>
            <td>{{ $position['napomena'] ?? '-' }}</td>
            <td class="text-end">{{ $position['kolicina'] ?? '-' }}</td>
            <td>{{ $position['mj'] ?? '-' }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="8" class="order-linkage-modal-empty">Za ovu narudzbu nisu pronadjene pozicije.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
