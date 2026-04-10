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
    <span class="order-linkage-modal-summary-label">Narudžba</span>
    <span class="order-linkage-modal-summary-value">{{ $orderSummary['order_number'] ?? '-' }}</span>
  </div>
  <div class="order-linkage-modal-summary-card">
    <span class="order-linkage-modal-summary-label">Kupac</span>
    <span class="order-linkage-modal-summary-value">{{ $orderSummary['customer'] ?? '-' }}</span>
  </div>
  <div class="order-linkage-modal-summary-card">
    <span class="order-linkage-modal-summary-label">Veza</span>
    <span class="order-linkage-modal-summary-value">
      <span class="badge {{ $linkageToneClass }} order-linkage-indicator">{{ $orderSummary['linkage_label'] ?? 'N/A' }}</span>
    </span>
  </div>
  <div class="order-linkage-modal-summary-card">
    <span class="order-linkage-modal-summary-label">Broj pozicija</span>
    <span class="order-linkage-modal-summary-value">{{ $orderSummary['position_count'] ?? 0 }}</span>
  </div>
</div>

<div class="order-linkage-modal-table-wrap">
  <div class="table-responsive">
    <table class="table order-linkage-modal-table">
      <thead>
        <tr>
          <th>Pozicija</th>
          <th>Šifra</th>
          <th>Naziv</th>
          <th class="text-end">Količina</th>
          <th>JM</th>
          <th>Datum / rok</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        @forelse($positions as $position)
          <tr>
            <td>{{ $position['pozicija'] ?? '-' }}</td>
            <td>{{ $position['sifra'] ?? '-' }}</td>
            <td>{{ $position['naziv'] ?? '-' }}</td>
            <td class="text-end">{{ $position['kolicina'] ?? '-' }}</td>
            <td>{{ $position['mj'] ?? '-' }}</td>
            <td>{{ $position['datum'] ?? '-' }}</td>
            <td>{{ $position['status'] ?? 'N/A' }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="7" class="order-linkage-modal-empty">Za ovu narudžbu nisu pronađene pozicije.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
