@php
  $orderSummary = (array) ($orderSummary ?? []);
  $links = (array) ($links ?? []);
  $formatNumber = static function ($value, int $precision = 2): string {
      if ($value === null || $value === '') {
          return '-';
      }

      if (!is_numeric($value)) {
          return (string) $value;
      }

      return number_format((float) $value, $precision, ',', '.');
  };
@endphp

<div class="order-linkage-modal-summary-grid">
  <div class="order-linkage-modal-summary-card">
    <span class="order-linkage-modal-summary-label">Narudžba</span>
    <span class="order-linkage-modal-summary-value">{{ $orderSummary['narudzba'] ?? $orderSummary['order_number'] ?? '-' }}</span>
  </div>
  <div class="order-linkage-modal-summary-card">
    <span class="order-linkage-modal-summary-label">Naručitelj</span>
    <span class="order-linkage-modal-summary-value">{{ $orderSummary['narucitelj'] ?? $orderSummary['klijent'] ?? $orderSummary['customer'] ?? '-' }}</span>
  </div>
  <div class="order-linkage-modal-summary-card">
    <span class="order-linkage-modal-summary-label">Broj veza</span>
    <span class="order-linkage-modal-summary-value">{{ $orderSummary['link_count'] ?? count($links) }}</span>
  </div>
  <div class="order-linkage-modal-summary-card">
    <span class="order-linkage-modal-summary-label">Tip</span>
    <span class="order-linkage-modal-summary-value">Veze RN</span>
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
          <th>Artikl</th>
          <th class="text-end">Neizrađeno</th>
          <th class="text-end">Izrađeno</th>
          <th class="text-end">Naručeno</th>
          <th>Poč.Ter.</th>
          <th>Rok.izr</th>
          <th>St.</th>
        </tr>
      </thead>
      <tbody>
        @forelse($links as $link)
          <tr>
            <td>{{ $link['dokument'] ?? '-' }}</td>
            <td>{{ $link['datum'] ?? '-' }}</td>
            <td>{{ $link['pozicija'] ?? '-' }}</td>
            <td>{{ $link['artikal'] ?? '-' }}</td>
            <td class="text-end">{{ $formatNumber($link['neizradjeno'] ?? null, 2) }}</td>
            <td class="text-end">{{ $formatNumber($link['izradjeno'] ?? null, 2) }}</td>
            <td class="text-end">{{ $formatNumber($link['naruceno'] ?? null, 2) }}</td>
            <td>{{ $link['poc_ter'] ?? '-' }}</td>
            <td>{{ $link['rok_izr'] ?? '-' }}</td>
            <td>{{ $link['status'] ?? '-' }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="10" class="order-linkage-modal-empty">Za ovu narudžbu nisu pronađene veze.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
