@php
  $orderSummary = (array) ($orderSummary ?? []);
  $workOrders = (array) ($workOrders ?? []);
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
    <span class="order-linkage-modal-summary-value">{{ $orderSummary['narudzba'] ?? $orderSummary['order_number'] ?? '-' }}</span>
  </div>
  <div class="order-linkage-modal-summary-card">
    <span class="order-linkage-modal-summary-label">Naručitelj</span>
    <span class="order-linkage-modal-summary-value">{{ $orderSummary['narucitelj'] ?? $orderSummary['klijent'] ?? $orderSummary['customer'] ?? '-' }}</span>
  </div>
  <div class="order-linkage-modal-summary-card">
    <span class="order-linkage-modal-summary-label">Veza</span>
    <span class="order-linkage-modal-summary-value">
      <span class="badge {{ $linkageToneClass }} order-linkage-indicator">{{ $orderSummary['linkage_label'] ?? 'N/A' }}</span>
    </span>
  </div>
  <div class="order-linkage-modal-summary-card">
    <span class="order-linkage-modal-summary-label">Broj RN</span>
    <span class="order-linkage-modal-summary-value">{{ $orderSummary['brojRN'] ?? $orderSummary['work_order_count'] ?? 0 }}</span>
  </div>
</div>

<div class="order-linkage-modal-table-wrap">
  <div class="table-responsive">
    <table class="table order-linkage-modal-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Status</th>
          <th>Veza</th>
          <th>Pozicije</th>
        </tr>
      </thead>
      <tbody>
        @forelse($workOrders as $workOrder)
          <tr>
            <td>{{ $workOrder['id'] ?? $workOrder['rn_number'] ?? '-' }}</td>
            <td>{{ $workOrder['status'] ?? 'N/A' }}</td>
            <td>
              @php
                $workOrderLinkToneClass = match ((string) ($workOrder['veza_tone'] ?? 'secondary')) {
                    'danger' => 'badge-light-danger',
                    'warning' => 'badge-light-warning',
                    'success' => 'badge-light-success',
                    'info' => 'badge-light-info',
                    'primary' => 'badge-light-primary',
                    default => 'badge-light-secondary',
                };
              @endphp
              <span class="badge {{ $workOrderLinkToneClass }}">{{ $workOrder['veza'] ?? 'Sumnjiva veza' }}</span>
            </td>
            <td>{{ $workOrder['pozicije'] ?? '-' }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="4" class="order-linkage-modal-empty">Za ovu narudzbu nisu pronadjeni radni nalozi.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
