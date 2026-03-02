@extends('layouts/fullLayoutMaster')

@php
  $workOrder = $workOrder ?? [];
  $invoiceNumber = (string) ($invoiceNumber ?? '');
  $invoiceNumberDisplay = $invoiceNumber !== '' ? $invoiceNumber : 'N/A';
  $issueDate = (string) ($issueDate ?? '');
  $plannedStartDate = (string) ($plannedStartDate ?? '');
  $dueDate = (string) ($dueDate ?? '');
  $sender = $sender ?? ['name' => '', 'address' => '', 'phone' => '', 'email' => ''];
  $recipient = $recipient ?? ['name' => '', 'address' => '', 'phone' => '', 'email' => ''];

  $compositions = $workOrderItems ?? (is_object($workOrder) ? ($workOrder->compositions ?? []) : []);
  $materials = $workOrderItemResources ?? (is_object($workOrder) ? ($workOrder->materials ?? []) : []);
  $operations = $workOrderRegOperations ?? (is_object($workOrder) ? ($workOrder->operations ?? []) : []);

  $workOrderMeta = $workOrderMeta ?? [];
  $workOrderMetaHighlights = $workOrderMeta['highlights'] ?? [];
  $workOrderMetaKpis = $workOrderMeta['kpis'] ?? [];
  $workOrderMetaTimeline = $workOrderMeta['timeline'] ?? [];
  $workOrderMetaTraceability = $workOrderMeta['traceability'] ?? [];
  $workOrderMetaFlags = $workOrderMeta['flags'] ?? [];
  $workOrderMetaProgress = $workOrderMeta['progress'] ?? ['label' => 'Realizacija', 'percent' => 0, 'display' => '0 %'];
  $workOrderMetaProgressPercent = max(0, min(100, (float) ($workOrderMetaProgress['percent'] ?? 0)));

  $workOrderProductName = '';
  $workOrderProductCode = '';
  $workOrderMetaHighlightChips = [];
  foreach ($workOrderMetaHighlights as $metaChip) {
    $metaLabelNormalized = \Illuminate\Support\Str::of((string) ($metaChip['label'] ?? ''))
      ->ascii()
      ->lower()
      ->trim()
      ->value();
    if ($metaLabelNormalized === 'naziv proizvoda') {
      $workOrderProductName = trim((string) ($metaChip['value'] ?? ''));
      continue;
    }
    if ($metaLabelNormalized === 'sifra proizvoda') {
      $workOrderProductCode = trim((string) ($metaChip['value'] ?? ''));
      continue;
    }
    $workOrderMetaHighlightChips[] = $metaChip;
  }

  $workOrderRouteId = trim((string) ($workOrder['id'] ?? $invoiceNumber ?? ''));
  if ($workOrderRouteId === '') {
    $workOrderRouteId = trim((string) ($invoiceNumber ?? ''));
  }
  $orderNumberQrRaw = trim((string) ($workOrder['broj_narudzbe'] ?? ''));
  $orderNumberQrPayload = preg_replace('/\D+/', '', $orderNumberQrRaw);
  if (!is_string($orderNumberQrPayload) || $orderNumberQrPayload === '') {
    $orderNumberQrPayload = preg_replace('/[^A-Za-z0-9]+/', '', $orderNumberQrRaw);
  }
  $orderPositionQrRaw = trim((string) ($workOrder['broj_pozicije_narudzbe'] ?? ''));
  $orderPositionQrPayload = '';
  if (is_numeric(str_replace(',', '.', $orderPositionQrRaw))) {
    $orderPositionQrPayload = (string) ((int) round((float) str_replace(',', '.', $orderPositionQrRaw)));
  }
  $productCodeQrRaw = trim((string) ($workOrder['sifra'] ?? ''));
  $productCodeQrPayload = preg_replace('/[^A-Za-z0-9]+/', '', $productCodeQrRaw);
  if (is_string($productCodeQrPayload)) {
    $productCodeQrPayload = strtoupper($productCodeQrPayload);
  } else {
    $productCodeQrPayload = '';
  }
  $previewQrTarget = request()->getSchemeAndHttpHost() . route('app-invoice-preview', ['id' => $workOrderRouteId], false);
  if (
    is_string($orderNumberQrPayload) &&
    $orderNumberQrPayload !== '' &&
    $orderPositionQrPayload !== '' &&
    $productCodeQrPayload !== ''
  ) {
    $previewQrTarget = $orderNumberQrPayload . ';' . $orderPositionQrPayload . ';' . $productCodeQrPayload;
  } elseif (
    is_string($orderNumberQrPayload) &&
    $orderNumberQrPayload !== '' &&
    $orderPositionQrPayload !== ''
  ) {
    $previewQrTarget = $orderNumberQrPayload . ';' . $orderPositionQrPayload;
  }
  $previewQrImage = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=0&data=' . urlencode($previewQrTarget);
@endphp

@section('title', 'Radni nalog ' . $invoiceNumberDisplay)

@section('page-style')
<link rel="stylesheet" href="{{ asset(mix('css/base/pages/app-invoice-print.css')) }}">
<style>
  .invoice-print {
    color: #5e5873;
    font-size: 14px;
  }
  .print-divider {
    border-top: 1px solid #ebe9f1;
    margin: 1.25rem 0;
  }
  .print-header-logo {
    border-radius: 999px;
  }
  .print-company-info p,
  .print-header-right p {
    margin-bottom: 0.2rem;
  }
  .print-header-right {
    min-width: 300px;
  }
  .print-qr {
    width: 112px;
    height: 112px;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 6px;
    background: #fff;
  }
  .meta-body {
    margin-bottom: 1.25rem;
  }
  .meta-product {
    border: 1px solid #ebe9f1;
    border-radius: 10px;
    background: linear-gradient(180deg, rgba(115, 103, 240, 0.08) 0%, rgba(255, 255, 255, 1) 100%);
    padding: 0.8rem 1rem;
    margin-bottom: 0.8rem;
  }
  .meta-product-kicker {
    display: block;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #6e6b7b;
    margin-bottom: 0.2rem;
    font-weight: 600;
  }
  .meta-product-title {
    display: block;
    font-size: 26px;
    line-height: 1.3;
    font-weight: 700;
    color: #5e5873;
  }
  .meta-product-sub {
    display: block;
    margin-top: 0.2rem;
    color: #6e6b7b;
    font-weight: 600;
  }
  .meta-progress-shell {
    border: 1px solid #ebe9f1;
    border-radius: 10px;
    padding: 0.8rem 1rem;
    margin-bottom: 0.8rem;
  }
  .meta-progress-head {
    display: flex;
    justify-content: space-between;
    font-weight: 700;
    color: #5e5873;
    margin-bottom: 0.45rem;
  }
  .meta-progress {
    width: 100%;
    height: 9px;
    background: #e9edf3;
    border-radius: 999px;
    overflow: hidden;
  }
  .meta-progress-bar {
    height: 100%;
    width: 0;
    background: linear-gradient(90deg, #00cfe8 0%, #28c76f 100%);
    border-radius: 999px;
  }
  .meta-chip-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-bottom: 0;
  }
  .meta-chip,
  .meta-flag {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    border: 1px solid #d8d6de;
    border-radius: 999px;
    background: #fff;
    padding: 0.22rem 0.6rem;
    font-size: 12px;
    line-height: 1;
  }
  .meta-chip-label {
    color: #6e6b7b;
    font-weight: 500;
  }
  .meta-chip-value,
  .meta-flag-value {
    color: #5e5873;
    font-weight: 700;
  }
  .meta-flag-dot {
    width: 6px;
    height: 6px;
    border-radius: 999px;
    background: #6e6b7b;
  }
  .print-section {
    margin-top: 1rem;
    page-break-inside: avoid;
  }
  .print-section-title {
    font-size: 16px;
    font-weight: 700;
    margin-bottom: 0.55rem;
    color: #4b4668;
    page-break-after: avoid;
  }
  .print-table {
    width: 100%;
    border-collapse: collapse;
    border: 1px solid #ebe9f1;
  }
  .print-table thead th {
    background: #f8f8fc;
    border-bottom: 1px solid #d8d6de;
    font-size: 12px;
    letter-spacing: 0.02em;
    text-transform: uppercase;
    color: #6e6b7b;
    padding: 0.5rem;
    vertical-align: middle;
  }
  .print-table tbody td {
    border-top: 1px solid #ebe9f1;
    font-size: 12px;
    color: #5e5873;
    padding: 0.45rem 0.5rem;
    vertical-align: top;
  }
  .print-empty {
    color: #6e6b7b;
    text-align: center;
    font-style: italic;
  }
  .print-text-end {
    text-align: right;
  }
  @media print {
    .invoice-print {
      padding: 0 !important;
    }
    .table-responsive {
      overflow: visible !important;
    }
    .print-section,
    .print-section-title,
    .print-table,
    .print-table thead,
    .print-table tbody,
    .print-table tr,
    .print-table td,
    .print-table th {
      page-break-inside: avoid;
    }
  }
</style>
@endsection

@section('content')
<div class="invoice-print p-4">
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-2">
    <div>
      <div class="d-flex align-items-center mb-1">
        <img src="{{ asset('/images/logo/TrendyCNC.png') }}" alt="Trendy logo" width="60" class="print-header-logo me-1">
        <h2 class="mb-0">eNalog.app</h2>
      </div>
      <div class="print-company-info">
        <p class="text-muted">Trendy d.o.o.</p>
        <p class="text-muted">Bratstvo 11, 72290, Novi Travnik, BiH</p>
        <p class="text-muted">+387 30 525 252</p>
        <p class="text-muted">info@trendy.ba</p>
      </div>
    </div>
    <div class="d-flex align-items-start gap-2">
      <div class="text-end print-header-right">
        <h3 class="fw-bold mb-50">RN {{ $invoiceNumberDisplay }}</h3>
        <p>Datum izdavanja: {{ $issueDate !== '' ? $issueDate : '-' }}</p>
        <p>Planirani start: {{ $plannedStartDate !== '' ? $plannedStartDate : '-' }}</p>
        <p>Datum dospijeca: {{ $dueDate !== '' ? $dueDate : '-' }}</p>
      </div>
      <img src="{{ $previewQrImage }}" alt="QR code" class="print-qr">
    </div>
  </div>

  <hr class="print-divider">

  <div class="row">
    <div class="col-6">
      <h6 class="mb-50">Pošiljatelj</h6>
      <p class="mb-25">{{ $sender['name'] ?: '-' }}</p>
    </div>
    <div class="col-6">
      <h6 class="mb-50">Primatelj</h6>
      <p class="mb-25">{{ $recipient['name'] ?: '-' }}</p>

    </div>
  </div>

  <hr class="print-divider">

  <div class="meta-body">
    @if($workOrderProductName !== '' || $workOrderProductCode !== '')
      <div class="meta-product">
        <span class="meta-product-kicker">Naziv proizvoda</span>
        <span class="meta-product-title">{{ $workOrderProductName !== '' ? $workOrderProductName : '-' }}</span>
        @if($workOrderProductCode !== '')
          <span class="meta-product-sub">Sifra proizvoda: {{ $workOrderProductCode }}</span>
        @endif
      </div>
    @endif

    <div class="meta-progress-shell">
      <div class="meta-progress-head">
        <span>{{ $workOrderMetaProgress['label'] ?? 'Realizacija po kolicini' }}</span>
        <span>{{ $workOrderMetaProgress['display'] ?? '0 %' }}</span>
      </div>
      <div class="meta-progress">
        <div class="meta-progress-bar" style="width: {{ $workOrderMetaProgressPercent }}%;"></div>
      </div>
    </div>

    @if(!empty($workOrderMetaHighlightChips) || !empty($workOrderMetaFlags))
      <div class="meta-chip-row">
        @foreach($workOrderMetaHighlightChips as $metaChip)
          <span class="meta-chip">
            <span class="meta-chip-label">{{ $metaChip['label'] ?? '' }}</span>
            <span class="meta-chip-value">{{ $metaChip['value'] ?? '-' }}</span>
          </span>
        @endforeach
        @foreach($workOrderMetaFlags as $metaFlag)
          <span class="meta-flag">
            <span class="meta-flag-dot"></span>
            <span class="meta-chip-label">{{ $metaFlag['label'] ?? '' }}:</span>
            <span class="meta-flag-value">{{ $metaFlag['value'] ?? '-' }}</span>
          </span>
        @endforeach
      </div>
    @endif
  </div>

  <section class="print-section">
    <h5 class="print-section-title">Sastavnica</h5>
    <div class="table-responsive">
      <table class="print-table">
        <thead>
          <tr>
            <th>Alternativa</th>
            <th>Pozicija</th>
            <th>Artikal</th>
            <th>Opis</th>
            <th>Slika</th>
            <th>Napomena</th>
            <th>Kolicina</th>
            <th>MJ</th>
            <th>Serija</th>
            <th>Normativna osnova</th>
            <th>Aktivno</th>
            <th>Zavrseno</th>
            <th>VA</th>
            <th>Prim klas</th>
            <th>Sek klas</th>
          </tr>
        </thead>
        <tbody>
          @forelse($compositions as $item)
            <tr>
              <td>{{ $item['alternativa'] ?? '-' }}</td>
              <td>{{ $item['pozicija'] ?? '-' }}</td>
              <td>{{ $item['artikal'] ?? '-' }}</td>
              <td>{{ $item['opis'] ?? '-' }}</td>
              <td>-</td>
              <td>{{ $item['napomena'] ?? '-' }}</td>
              <td>{{ $item['kolicina'] ?? '-' }}</td>
              <td>{{ $item['mj'] ?? '-' }}</td>
              <td>{{ $item['serija'] ?? '-' }}</td>
              <td>{{ $item['normativna_osnova'] ?? '-' }}</td>
              <td>{{ $item['aktivno'] ?? '-' }}</td>
              <td>{{ $item['zavrseno'] ?? '-' }}</td>
              <td>{{ $item['va'] ?? '-' }}</td>
              <td>{{ $item['prim_klas'] ?? '-' }}</td>
              <td>{{ $item['sek_klas'] ?? '-' }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="15" class="print-empty">Nema stavki za ovaj radni nalog.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>

  <section class="print-section">
    <h5 class="print-section-title">Materijal</h5>
    <div class="table-responsive">
      <table class="print-table">
        <thead>
          <tr>
            <th>Pozicija</th>
            <th>Materijal</th>
            <th>Naziv</th>
            <th>Kolicina</th>
            <th>Napomena</th>
          </tr>
        </thead>
        <tbody>
          @forelse($materials as $item)
            <tr>
              <td>{{ $item['pozicija'] ?? '-' }}</td>
              <td>{{ $item['materijal'] ?? '-' }}</td>
              <td>{{ $item['naziv'] ?? '-' }}</td>
              <td>{{ $item['kolicina'] ?? '-' }}</td>
              <td>{{ $item['napomena'] ?? '-' }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="print-empty">Nema materijala za ovaj radni nalog.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>

  <section class="print-section">
    <h5 class="print-section-title">Operacija</h5>
    <div class="table-responsive">
      <table class="print-table">
        <thead>
          <tr>
            <th>Alternativa</th>
            <th>Pozicija</th>
            <th>Operacija</th>
            <th>Naziv</th>
            <th>Napomena</th>
            <th>MJ</th>
            <th>MJ/Vrij</th>
            <th>Normativna osnova</th>
            <th>VA</th>
            <th>Prim klas</th>
            <th>Sek klas</th>
          </tr>
        </thead>
        <tbody>
          @forelse($operations as $operation)
            <tr>
              <td>{{ $operation['alternativa'] ?? '-' }}</td>
              <td>{{ $operation['pozicija'] ?? '-' }}</td>
              <td>{{ $operation['operacija'] ?? '-' }}</td>
              <td>{{ $operation['naziv'] ?? '-' }}</td>
              <td>{{ $operation['napomena'] ?? '-' }}</td>
              <td>{{ $operation['mj'] ?? '-' }}</td>
              <td>{{ $operation['mj_vrij'] ?? '-' }}</td>
              <td>{{ $operation['normativna_osnova'] ?? '-' }}</td>
              <td>{{ $operation['va'] ?? '-' }}</td>
              <td>{{ $operation['prim_klas'] ?? '-' }}</td>
              <td>{{ $operation['sek_klas'] ?? '-' }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="11" class="print-empty">Nema operacija za ovaj radni nalog.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>

  <section class="print-section">
    <h5 class="print-section-title">Operativni KPI</h5>
    <div class="table-responsive">
      <table class="print-table">
        <thead>
          <tr>
            <th>Parametar</th>
            <th>Vrijednost</th>
            <th>Jedinica</th>
          </tr>
        </thead>
        <tbody>
          @forelse($workOrderMetaKpis as $kpi)
            <tr>
              <td>{{ $kpi['label'] ?? '-' }}</td>
              <td class="print-text-end">{{ $kpi['value'] ?? '-' }}</td>
              <td>{{ $kpi['unit'] ?? '-' }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="3" class="print-empty">Nema KPI podataka za ovaj radni nalog.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>

  <section class="print-section">
    <h5 class="print-section-title">Timeline</h5>
    <div class="table-responsive">
      <table class="print-table">
        <thead>
          <tr>
            <th>Dogadjaj</th>
            <th>Vrijeme</th>
          </tr>
        </thead>
        <tbody>
          @forelse($workOrderMetaTimeline as $metaRow)
            <tr>
              <td>{{ $metaRow['label'] ?? '-' }}</td>
              <td>{{ $metaRow['value'] ?? '-' }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="2" class="print-empty">Nema timeline podataka za ovaj radni nalog.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>

  <section class="print-section">
    <h5 class="print-section-title">Poveznice</h5>
    <div class="table-responsive">
      <table class="print-table">
        <thead>
          <tr>
            <th>Polje</th>
            <th>Vrijednost</th>
          </tr>
        </thead>
        <tbody>
          @forelse($workOrderMetaTraceability as $metaRow)
            <tr>
              <td>{{ $metaRow['label'] ?? '-' }}</td>
              <td>{{ $metaRow['value'] ?? '-' }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="2" class="print-empty">Nema podataka o poveznicama.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
</div>
@endsection

@section('page-script')
<script src="{{ asset('js/scripts/pages/app-invoice-print.js') }}"></script>
@endsection
