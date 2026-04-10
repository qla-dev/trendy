@php
  $orderSummary = (array) ($orderSummary ?? []);
  $items = (array) ($items ?? []);
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
    <span class="order-linkage-modal-summary-label">Narudzba</span>
    <span class="order-linkage-modal-summary-value">{{ $orderSummary['order_number'] ?? $orderSummary['narudzba'] ?? '-' }}</span>
  </div>
  <div class="order-linkage-modal-summary-card">
    <span class="order-linkage-modal-summary-label">Narucitelj</span>
    <span class="order-linkage-modal-summary-value">{{ $orderSummary['narucitelj'] ?? $orderSummary['customer'] ?? '-' }}</span>
  </div>
  <div class="order-linkage-modal-summary-card">
    <span class="order-linkage-modal-summary-label">Broj pozicija</span>
    <span class="order-linkage-modal-summary-value">{{ $orderSummary['position_count'] ?? count($items) }}</span>
  </div>
  <div class="order-linkage-modal-summary-card">
    <span class="order-linkage-modal-summary-label">Rok otpreme</span>
    <span class="order-linkage-modal-summary-value">{{ $orderSummary['due_date'] ?? '-' }}</span>
  </div>
</div>

<div class="order-linkage-modal-table-wrap">
  <div class="table-responsive">
    <table class="table order-linkage-modal-table">
      <thead>
        <tr>
          <th>Poz.</th>
          <th>Sifra</th>
          <th>Alt.</th>
          <th>Naziv</th>
          <th>JM</th>
          <th class="text-end">Kolicina</th>
          <th class="text-end">Cijena</th>
          <th class="text-end">R1 %</th>
          <th class="text-end">R2 %</th>
          <th class="text-end">SR %</th>
          <th class="text-end">Popust %</th>
          <th class="text-end">Vrijednost</th>
          <th>PDV</th>
          <th class="text-end">Za platiti</th>
          <th class="text-end">Otprem.</th>
          <th class="text-end">Paketa</th>
          <th class="text-end">Neto tez.</th>
          <th class="text-end">Bruto tez.</th>
          <th class="text-end">Volumen</th>
          <th>Rok otpreme</th>
          <th>Odjel</th>
          <th>Nos. tr.</th>
          <th class="text-end">Cijena s rab.</th>
        </tr>
      </thead>
      <tbody>
        @forelse($items as $item)
          <tr>
            <td>{{ $item['pozicija'] ?? '-' }}</td>
            <td>{{ $item['sifra'] ?? $item['artikal'] ?? '-' }}</td>
            <td>{{ $item['alt'] ?? '-' }}</td>
            <td>{{ $item['naziv'] ?? $item['opis'] ?? '-' }}</td>
            <td>{{ $item['jm'] ?? '-' }}</td>
            <td class="text-end">{{ $formatNumber($item['kolicina'] ?? null, 2) }}</td>
            <td class="text-end">{{ $formatNumber($item['cijena'] ?? null, 2) }}</td>
            <td class="text-end">{{ $formatNumber($item['r1'] ?? null, 2) }}</td>
            <td class="text-end">{{ $formatNumber($item['r2'] ?? null, 2) }}</td>
            <td class="text-end">{{ $formatNumber($item['sr'] ?? null, 2) }}</td>
            <td class="text-end">{{ $formatNumber($item['popust'] ?? null, 2) }}</td>
            <td class="text-end">{{ $formatNumber($item['vrijednost'] ?? null, 2) }}</td>
            <td>{{ trim((string) ($item['pdv'] ?? '')) !== '' ? $item['pdv'] : $formatNumber($item['pdv_stopa'] ?? null, 2) }}</td>
            <td class="text-end">{{ $formatNumber($item['za_platiti'] ?? null, 2) }}</td>
            <td class="text-end">{{ $formatNumber($item['otpremljeno'] ?? null, 2) }}</td>
            <td class="text-end">{{ $formatNumber($item['paketa'] ?? null, 2) }}</td>
            <td class="text-end">{{ $formatNumber($item['neto_tezina'] ?? null, 2) }}</td>
            <td class="text-end">{{ $formatNumber($item['bruto_tezina'] ?? null, 2) }}</td>
            <td class="text-end">{{ $formatNumber($item['volumen'] ?? null, 2) }}</td>
            <td>{{ $item['rok_otpreme'] ?? '-' }}</td>
            <td>{{ $item['odjel'] ?? '-' }}</td>
            <td>{{ $item['nos_tr'] ?? '-' }}</td>
            <td class="text-end">{{ $formatNumber($item['cijena_s_rabatom'] ?? null, 2) }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="23" class="order-linkage-modal-empty">Za ovu narudzbu nisu pronadjene pozicije.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
