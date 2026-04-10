@php
  $orderSummary = (array) ($orderSummary ?? []);
  $links = (array) ($links ?? []);
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
    <span class="order-linkage-modal-summary-label">Broj veza</span>
    <span class="order-linkage-modal-summary-value">{{ $orderSummary['link_count'] ?? count($links) }}</span>
  </div>
</div>

<div class="order-linkage-modal-table-wrap">
  <div class="table-responsive">
    <table class="table order-linkage-modal-table">
      <thead>
        <tr>
          <th>Dokument</th>
          <th>Datum</th>
          <th>Poz.</th>
          <th>Artikal</th>
          <th>Opis</th>
          <th>Tip</th>
          <th class="text-end">Naruceno</th>
          <th class="text-end">Izradjeno</th>
          <th class="text-end">Neizradjeno</th>
          <th>JM</th>
        </tr>
      </thead>
      <tbody>
        @forelse($links as $link)
          <tr>
            <td>{{ $link['dokument'] ?? '-' }}</td>
            <td>{{ $link['datum'] ?? '-' }}</td>
            <td>{{ $link['pozicija'] ?? '-' }}</td>
            <td>{{ $link['artikal'] ?? '-' }}</td>
            <td>{{ $link['opis'] ?? '-' }}</td>
            <td>{{ $link['tip'] ?? '-' }}</td>
            <td class="text-end">{{ $link['naruceno'] ?? '-' }}</td>
            <td class="text-end">{{ $link['izradjeno'] ?? '-' }}</td>
            <td class="text-end">{{ $link['neizradjeno'] ?? '-' }}</td>
            <td>{{ $link['jm'] ?? '-' }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="10" class="order-linkage-modal-empty">Za ovu narudzbu nisu pronadjene veze.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
