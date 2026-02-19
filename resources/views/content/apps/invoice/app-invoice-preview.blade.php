@extends('layouts/contentLayoutMaster')

@section('title', 'Pregled radnog naloga')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('vendors/css/pickers/flatpickr/flatpickr.min.css')}}">
@endsection
@section('page-style')
<link rel="stylesheet" href="{{asset('css/base/plugins/forms/pickers/form-flat-pickr.css')}}">
<link rel="stylesheet" href="{{asset('css/base/pages/app-invoice.css')}}">
<style>
  .nav-tabs {
    margin-bottom: 0 !important;
  }
  .image-placeholder:hover {
    transform: scale(1.08);
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
  }
  .invoice-preview .invoice-title, .invoice-edit .invoice-title, .invoice-add .invoice-title {
    margin-bottom: 0.5rem !important;
  }
  .invoice-preview .invoice-title .invoice-number {
    margin-left: 0.5rem;
  }
  .invoice-preview .invoice-date-wrapper, .invoice-edit .invoice-date-wrapper, .invoice-add .invoice-date-wrapper {
    justify-content: flex-end;
    margin-bottom: 0.5rem !important;
  }
  .invoice-preview .invoice-date-wrapper:last-child {
    margin-bottom: 0 !important;
  }
  .invoice-preview .invoice-date-wrapper .invoice-date-title, .invoice-edit .invoice-date-wrapper .invoice-date-title, .invoice-add .invoice-date-wrapper .invoice-date-title {
    width: unset;
  }
  .invoice-actions .btn {
    color: #5e5873;
  }
  .invoice-actions .btn i {
    color: inherit;
  }
  .invoice-preview-wrapper .invoice-actions {
    position: sticky;
    top: 5rem;
    align-self: flex-start;
    z-index: 5;
  }
  .invoice-preview-wrapper .invoice-actions .card {
    transition: transform 0.22s ease, box-shadow 0.22s ease;
  }
  .invoice-preview-wrapper .invoice-actions.invoice-actions-scrolled .card {
    transform: translateY(-2px);
    box-shadow: 0 10px 24px rgba(34, 41, 47, 0.12);
  }
  .invoice-actions-divider {
    border-top: 1px dashed #d8d6de;
    margin: 0.25rem 0 0.75rem;
  }
  body:not(.dark-layout) .invoice-actions-divider {
    border-top: 2px dashed #b6b3c1;
  }
  .wo-side-meta-btn {
    border-width: 1px;
    background-color: #fff;
    font-weight: 600;
    transition: all 0.2s ease;
  }
  .wo-side-meta-btn.wo-side-meta-btn-success { border-color: #28c76f; color: #28c76f; background-color: rgba(40, 199, 111, 0.08); }
  .wo-side-meta-btn.wo-side-meta-btn-warning { border-color: #ff9f43; color: #ff9f43; background-color: rgba(255, 159, 67, 0.1); }
  .wo-side-meta-btn.wo-side-meta-btn-danger { border-color: #ea5455; color: #ea5455; background-color: rgba(234, 84, 85, 0.08); }
  .wo-side-meta-btn.wo-side-meta-btn-info { border-color: #00cfe8; color: #00cfe8; background-color: rgba(0, 207, 232, 0.08); }
  .wo-side-meta-btn.wo-side-meta-btn-primary { border-color: #7367f0; color: #7367f0; background-color: rgba(115, 103, 240, 0.08); }
  .wo-side-meta-btn.wo-side-meta-btn-secondary { border-color: #6e6b7b; color: #6e6b7b; background-color: rgba(110, 107, 123, 0.08); }
  .wo-side-meta-btn:hover {
    filter: brightness(0.96);
  }
  .wo-meta-shell {
    border: 1px solid #ebe9f1;
    border-radius: 10px;
    background: linear-gradient(180deg, rgba(245, 247, 250, 0.6) 0%, rgba(255, 255, 255, 1) 100%);
    padding: 1rem;
    margin-top: 0.25rem;
    margin-bottom: 1rem;
  }
  .wo-meta-chip-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 0.85rem;
  }
  .wo-meta-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    border-radius: 999px;
    padding: 0.3rem 0.65rem;
    border: 1px solid #ebe9f1;
    background-color: #fff;
    font-size: 0.78rem;
    line-height: 1;
    white-space: nowrap;
  }
  .wo-meta-chip-label {
    color: #6e6b7b;
    font-weight: 500;
  }
  .wo-meta-chip-value {
    color: #5e5873;
    font-weight: 600;
  }
  .wo-chip-success { border-color: rgba(40, 199, 111, 0.45); background-color: rgba(40, 199, 111, 0.1); }
  .wo-chip-danger { border-color: rgba(234, 84, 85, 0.45); background-color: rgba(234, 84, 85, 0.1); }
  .wo-chip-warning { border-color: rgba(255, 159, 67, 0.45); background-color: rgba(255, 159, 67, 0.1); }
  .wo-chip-info { border-color: rgba(0, 207, 232, 0.45); background-color: rgba(0, 207, 232, 0.1); }
  .wo-chip-primary { border-color: rgba(115, 103, 240, 0.45); background-color: rgba(115, 103, 240, 0.1); }
  .wo-chip-secondary,
  .wo-chip-slate,
  .wo-chip-orange { border-color: rgba(110, 107, 123, 0.35); background-color: rgba(110, 107, 123, 0.08); }
  .wo-header-right-column {
    display: flex;
    flex-direction: column;
    align-items: stretch;
  }
  .wo-header-main-row {
    display: flex;
    align-items: center;
    justify-content: flex-end;
  }
  .wo-header-qr-block {
    display: flex;
    align-items: flex-end;
  }
  .wo-header-chip-stack {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    align-items: stretch;
    width: 100%;
    min-width: 210px;
    margin-top: 0.55rem;
  }
  .wo-header-chip-stack .wo-meta-chip {
    justify-content: space-between;
    width: 100%;
    background-color: rgba(115, 103, 240, 0.06);
  }

  .wo-meta-grid {
    display: grid;
    grid-template-columns: 1.2fr 1fr 1fr;
    gap: 0.75rem;
  }
  .wo-meta-card {
    border: 1px solid #ebe9f1;
    border-radius: 8px;
    background-color: #fff;
    padding: 0.85rem;
  }
  .wo-meta-card-title {
    font-size: 0.92rem;
    font-weight: 600;
    margin-bottom: 0.7rem;
    color: #5e5873;
  }
  .wo-kpi-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.6rem 0.75rem;
  }
  .wo-kpi-item {
    border: 1px dashed #ebe9f1;
    border-radius: 7px;
    padding: 0.5rem 0.55rem;
    background-color: rgba(115, 103, 240, 0.03);
  }
  .wo-kpi-label {
    display: block;
    font-size: 0.7rem;
    color: #6e6b7b;
    margin-bottom: 0.2rem;
  }
  .wo-kpi-value {
    display: flex;
    align-items: baseline;
    gap: 0.22rem;
    color: #5e5873;
    font-size: 0.94rem;
    font-weight: 600;
  }
  .wo-kpi-unit {
    color: #6e6b7b;
    font-size: 0.72rem;
    font-weight: 500;
  }
  .wo-progress-wrap {
    margin-top: 0.75rem;
    border-top: 1px solid #ebe9f1;
    padding-top: 0.6rem;
  }
  .wo-progress-head {
    display: flex;
    justify-content: space-between;
    gap: 0.5rem;
    font-size: 0.75rem;
    color: #6e6b7b;
    margin-bottom: 0.35rem;
  }
  .wo-progress {
    height: 6px;
    width: 100%;
    border-radius: 999px;
    background-color: #f1f1f5;
    overflow: hidden;
  }
  .wo-progress-bar {
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, #00cfe8 0%, #28c76f 100%);
  }
  .wo-meta-list {
    display: grid;
    gap: 0.42rem;
  }
  .wo-meta-row {
    display: flex;
    justify-content: space-between;
    gap: 0.65rem;
    border-bottom: 1px dashed #ebe9f1;
    padding-bottom: 0.3rem;
  }
  .wo-meta-row:last-child {
    border-bottom: 0;
    padding-bottom: 0;
  }
  .wo-meta-key {
    color: #6e6b7b;
    font-size: 0.74rem;
  }
  .wo-meta-value {
    color: #5e5873;
    font-size: 0.78rem;
    text-align: right;
    font-weight: 600;
    word-break: break-word;
  }
  .wo-meta-flag-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.45rem;
    margin-top: 0.75rem;
  }
  .wo-flag-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    border-radius: 999px;
    padding: 0.3rem 0.6rem;
    border: 1px solid #ebe9f1;
    background: #fff;
    font-size: 0.72rem;
    color: #5e5873;
  }
  .wo-flag-dot {
    width: 7px;
    height: 7px;
    border-radius: 999px;
    background: #6e6b7b;
  }
  .wo-flag-success .wo-flag-dot { background: #28c76f; }
  .wo-flag-secondary .wo-flag-dot { background: #6e6b7b; }
  .wo-flag-info .wo-flag-dot { background: #00cfe8; }

  @media (max-width: 1200px) {
    .wo-meta-grid {
      grid-template-columns: 1fr;
    }
  }
  @media (max-width: 767.98px) {
    .invoice-preview-wrapper .invoice-actions {
      position: static;
      top: auto;
    }
    .wo-header-right-column {
      width: 100%;
    }
    .wo-header-main-row {
      justify-content: flex-start;
    }
    .wo-header-chip-stack {
      min-width: 0;
      width: 100%;
    }
  }
</style>
@endsection

@php
  $invoiceNumber = $invoiceNumber ?? '';
  $issueDate = $issueDate ?? '';
  $dueDate = $dueDate ?? '';
  $sender = $sender ?? ['name' => '', 'address' => '', 'phone' => '', 'email' => ''];
  $recipient = $recipient ?? ['name' => '', 'address' => '', 'phone' => '', 'email' => ''];
  $workOrderMeta = $workOrderMeta ?? [];
  $workOrderMetaHighlights = $workOrderMeta['highlights'] ?? [];
  $workOrderMetaKpis = $workOrderMeta['kpis'] ?? [];
  $workOrderMetaTimeline = $workOrderMeta['timeline'] ?? [];
  $workOrderMetaTraceability = $workOrderMeta['traceability'] ?? [];
  $workOrderMetaFlags = $workOrderMeta['flags'] ?? [];
  $workOrderMetaProgress = $workOrderMeta['progress'] ?? ['label' => 'Realizacija', 'percent' => 0, 'display' => '0 %'];
  $workOrderMetaProgressPercent = max(0, min(100, (float) ($workOrderMetaProgress['percent'] ?? 0)));
  $workOrderHeaderHighlights = [];
  $workOrderMetaHighlightChips = [];
  foreach ($workOrderMetaHighlights as $metaChip) {
    $metaLabel = strtolower(trim((string) ($metaChip['label'] ?? '')));
    if (in_array($metaLabel, ['status', 'prioritet'], true)) {
      $workOrderHeaderHighlights[] = $metaChip;
      continue;
    }
    $workOrderMetaHighlightChips[] = $metaChip;
  }
  $statusDisplayLabel = trim((string) ($workOrder['status'] ?? 'N/A'));
  $priorityDisplayLabel = trim((string) ($workOrder['prioritet'] ?? 'N/A'));
  $statusToneClass = 'secondary';
  $priorityToneClass = 'secondary';
  $normalizedStatusLabel = strtolower($statusDisplayLabel);
  if (str_contains($normalizedStatusLabel, 'otvoren')) {
    $statusToneClass = 'success';
  } elseif (str_contains($normalizedStatusLabel, 'u radu') || str_contains($normalizedStatusLabel, 'u toku')) {
    $statusToneClass = 'warning';
  } elseif (str_contains($normalizedStatusLabel, 'planiran') || str_contains($normalizedStatusLabel, 'novo')) {
    $statusToneClass = 'primary';
  } elseif (str_contains($normalizedStatusLabel, 'rezerv')) {
    $statusToneClass = 'info';
  } elseif (str_contains($normalizedStatusLabel, 'zavr') || str_contains($normalizedStatusLabel, 'zaklj')) {
    $statusToneClass = 'danger';
  } elseif (str_contains($normalizedStatusLabel, 'djelimic')) {
    $statusToneClass = 'warning';
  }
  if (preg_match('/^\s*(\d+)/', $priorityDisplayLabel, $priorityMatches) === 1) {
    $priorityCode = (int) ($priorityMatches[1] ?? 0);
    if ($priorityCode === 1) {
      $priorityToneClass = 'danger';
    } elseif ($priorityCode === 5) {
      $priorityToneClass = 'warning';
    } elseif ($priorityCode >= 10) {
      $priorityToneClass = 'info';
    }
  }
@endphp

@section('content')
<section class="invoice-preview-wrapper">
  <div class="row invoice-preview">
    <!-- Invoice -->
    <div class="col-xl-9 col-md-8 col-12">
      <div class="card invoice-preview-card">
        <div class="card-body invoice-padding pb-0">
          <!-- Header starts -->
          <div class="d-flex justify-content-between flex-md-row flex-column invoice-spacing mt-0">
            <div>
              <div class="logo-wrapper">
                <img src="{{ asset('/images/logo/TrendyCNC.png') }}" alt="Trendy d.o.o." width="50" height="auto">
                <h3 class="text-primary invoice-logo">eNalog.app</h3>
              </div>
              <p class="card-text mb-25">Trendy d.o.o.</p>
              <p class="card-text mb-25">Bratstvo 11, 72290, Novi Travnik, BiH</p>
              <p class="card-text mb-0">+387 30 525 252</p>
              <p class="card-text mb-0">info@trendy.ba</p>
            </div>
            <div class="mt-md-0 mt-2 d-flex align-items-center justify-content-end">
              <div class="me-3">
                <h4 class="invoice-title">
                  <span>RN<span class="invoice-number">{{ $invoiceNumber }}</span></span>
                </h4>
                <div class="invoice-date-wrapper">
                  <p class="invoice-date-title">Datum izdavanja:</p>
                  <p class="invoice-date">{{ $issueDate }}</p>
                </div>
                <div class="invoice-date-wrapper">
                  <p class="invoice-date-title">Datum dospijeća:</p>
                  <p class="invoice-date">{{ $dueDate }}</p>
                </div>
              </div>
              <div class="wo-header-qr-block">
                <img src="https://upload.wikimedia.org/wikipedia/commons/d/d0/QR_code_for_mobile_English_Wikipedia.svg" alt="QR Code" style="width: 120px; height: 120px; border: 1px solid #dee2e6; border-radius: 4px; padding: 8px; background: white;">
              </div>
            </div>
          </div>
          <!-- Header ends -->
        </div>

        <hr class="invoice-spacing" />

        <!-- Address and Contact starts -->
        <div class="card-body invoice-padding pt-0">
          <div class="row invoice-spacing">
            <div class="col-xl-4 col-md-6 p-0">
              <h6 class="mb-2">Pošiljatelj:</h6>
              @if($sender['name'])
                <h6 class="mb-25">{{ $sender['name'] }}</h6>
              @endif
              @if($sender['address'])
                <p class="card-text mb-25">{{ $sender['address'] }}</p>
              @endif
              @if($sender['phone'])
                <p class="card-text mb-25">{{ $sender['phone'] }}</p>
              @endif
              @if($sender['email'])
                <p class="card-text mb-0">{{ $sender['email'] }}</p>
              @endif
            </div>
            <div class="col-xl-4 d-none d-xl-block"></div>
            <div class="col-xl-4 col-md-6 p-0">
              <h6 class="mb-2">Primatelj:</h6>
              @if($recipient['name'])
                <h6 class="mb-25">{{ $recipient['name'] }}</h6>
              @endif
              @if($recipient['address'])
                <p class="card-text mb-25">{{ $recipient['address'] }}</p>
              @endif
              @if($recipient['phone'])
                <p class="card-text mb-25">{{ $recipient['phone'] }}</p>
              @endif
              @if($recipient['email'])
                <p class="card-text mb-0">{{ $recipient['email'] }}</p>
              @endif
            </div>
          </div>
        </div>
        <!-- Address and Contact ends -->

        <!-- Work Order Metadata starts -->
        <div class="card-body invoice-padding pt-0">
          <div class="wo-meta-shell">
            @if(!empty($workOrderMetaHighlightChips))
              <div class="wo-meta-chip-row">
                @foreach($workOrderMetaHighlightChips as $metaChip)
                  <div class="wo-meta-chip wo-chip-{{ $metaChip['tone'] ?? 'secondary' }}">
                    <span class="wo-meta-chip-label">{{ $metaChip['label'] ?? '' }}</span>
                    <span class="wo-meta-chip-value">{{ $metaChip['value'] ?? '-' }}</span>
                  </div>
                @endforeach
              </div>
            @endif

            <div class="wo-meta-grid">
              <div class="wo-meta-card">
                <div class="wo-meta-card-title">Operativni KPI</div>
                <div class="wo-kpi-grid">
                  @foreach($workOrderMetaKpis as $kpi)
                    <div class="wo-kpi-item">
                      <span class="wo-kpi-label">{{ $kpi['label'] ?? '' }}</span>
                      <span class="wo-kpi-value">
                        {{ $kpi['value'] ?? '-' }}
                        @if(!empty($kpi['unit']))
                          <span class="wo-kpi-unit">{{ $kpi['unit'] }}</span>
                        @endif
                      </span>
                    </div>
                  @endforeach
                </div>
                <div class="wo-progress-wrap">
                  <div class="wo-progress-head">
                    <span>{{ $workOrderMetaProgress['label'] ?? 'Realizacija' }}</span>
                    <span>{{ $workOrderMetaProgress['display'] ?? '0 %' }}</span>
                  </div>
                  <div class="wo-progress">
                    <div class="wo-progress-bar" style="width: {{ $workOrderMetaProgressPercent }}%;"></div>
                  </div>
                </div>
              </div>

              <div class="wo-meta-card">
                <div class="wo-meta-card-title">Vremenski tok</div>
                <div class="wo-meta-list">
                  @foreach($workOrderMetaTimeline as $metaRow)
                    <div class="wo-meta-row">
                      <span class="wo-meta-key">{{ $metaRow['label'] ?? '' }}</span>
                      <span class="wo-meta-value">{{ $metaRow['value'] ?? '-' }}</span>
                    </div>
                  @endforeach
                </div>
              </div>

              <div class="wo-meta-card">
                <div class="wo-meta-card-title">Traceability i poveznice</div>
                <div class="wo-meta-list">
                  @foreach($workOrderMetaTraceability as $metaRow)
                    <div class="wo-meta-row">
                      <span class="wo-meta-key">{{ $metaRow['label'] ?? '' }}</span>
                      <span class="wo-meta-value">{{ $metaRow['value'] ?? '-' }}</span>
                    </div>
                  @endforeach
                </div>
              </div>
            </div>

            @if(!empty($workOrderMetaFlags))
              <div class="wo-meta-flag-row">
                @foreach($workOrderMetaFlags as $metaFlag)
                  <span class="wo-flag-pill wo-flag-{{ $metaFlag['tone'] ?? 'secondary' }}">
                    <span class="wo-flag-dot"></span>
                    <span>{{ $metaFlag['label'] ?? '' }}: <strong>{{ $metaFlag['value'] ?? '-' }}</strong></span>
                  </span>
                @endforeach
              </div>
            @endif
          </div>
        </div>
        <!-- Work Order Metadata ends -->

        <!-- Invoice Description starts -->
        <div class="nav-align-top">
          <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item">
              <button type="button" class="nav-link active" role="tab" data-bs-toggle="tab" data-bs-target="#tab-sastavnica" aria-controls="tab-sastavnica" aria-selected="true">
                <i class="fa fa-list me-50"></i> Sastavnica
              </button>
            </li>
            <li class="nav-item">
              <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#tab-materijali" aria-controls="tab-materijali" aria-selected="false">
                <i class="fa fa-cube me-50"></i> Materijali
              </button>
            </li>
            <li class="nav-item">
              <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#tab-operacija" aria-controls="tab-operacija" aria-selected="false">
                <i class="fa fa-cog me-50"></i> Operacija
              </button>
            </li>
          </ul>
          <div class="tab-content">
            <!-- Sastavnica Tab -->
            <div class="tab-pane fade show active" id="tab-sastavnica" role="tabpanel">
              <div class="table-responsive">
                <table class="table" id="sastavnica-table">
                  <thead>
                    <tr>
                      <th class="py-1 text-center">Alternat...</th>
                      <th class="py-1 text-center">Pozicija</th>
                      <th class="py-1 text-center">Artikal</th>
                      <th class="py-1 text-center">Opis</th>
                      <th class="py-1 text-center">Slika</th>
                      <th class="py-1 text-center">Napo...</th>
                      <th class="py-1 text-center">Količina</th>
                      <th class="py-1 text-center">MJ</th>
                      <th class="py-1 text-center">Serija</th>
                      <th class="py-1 text-center">nor.os.</th>
                      <th class="py-1 text-center">Aktivno</th>
                      <th class="py-1 text-center">Završ...</th>
                      <th class="py-1 text-center">VA</th>
                      <th class="py-1 text-center">Prim.klas</th>
                      <th class="py-1 text-center">Sek.klas</th>
                    </tr>
                  </thead>
                  <tbody>
                    @forelse(($workOrderItems ?? []) as $item)
                      <tr>
                        <td class="py-1">{{ $item['alternativa'] ?? '' }}</td>
                        <td class="py-1">{{ $item['pozicija'] ?? '' }}</td>
                        <td class="py-1">{{ $item['artikal'] ?? '' }}</td>
                        <td class="py-1">{{ $item['opis'] ?? '' }}</td>
                        <td class="py-1 text-center"><span class="text-muted">-</span></td>
                        <td class="py-1">{{ $item['napomena'] ?? '' }}</td>
                        <td class="py-1">{{ $item['kolicina'] ?? '' }}</td>
                        <td class="py-1">{{ $item['mj'] ?? '' }}</td>
                        <td class="py-1">{{ $item['serija'] ?? '' }}</td>
                        <td class="py-1">{{ $item['normativna_osnova'] ?? '' }}</td>
                        <td class="py-1">{{ $item['aktivno'] ?? '' }}</td>
                        <td class="py-1">{{ $item['zavrseno'] ?? '' }}</td>
                        <td class="py-1">{{ $item['va'] ?? '' }}</td>
                        <td class="py-1">{{ $item['prim_klas'] ?? '' }}</td>
                        <td class="py-1">{{ $item['sek_klas'] ?? '' }}</td>
                      </tr>
                    @empty
                      <tr>
                        <td colspan="15" class="text-center text-muted py-2">Nema stavki za ovaj radni nalog.</td>
                      </tr>
                    @endforelse
                  </tbody>
                </table>
              </div>
            </div>
            <!-- Materijali Tab -->
            <div class="tab-pane fade" id="tab-materijali" role="tabpanel">
              <div class="table-responsive">
                <table class="table" id="materijali-table">
                  <thead>
                    <tr>
                      <th class="py-1 text-center">Pozicija</th>
                      <th class="py-1 text-center">Materijal</th>
                      <th class="py-1 text-center">Naziv</th>
                      <th class="py-1 text-center">Količina</th>
                      <th class="py-1 text-center">Napomena</th>
                    </tr>
                  </thead>
                  <tbody>
                    @forelse(($workOrderItemResources ?? []) as $item)
                      <tr>
                        <td class="py-1">{{ $item['pozicija'] ?? '' }}</td>
                        <td class="py-1">{{ $item['materijal'] ?? '' }}</td>
                        <td class="py-1">{{ $item['naziv'] ?? '' }}</td>
                        <td class="py-1">{{ $item['kolicina'] ?? '' }}</td>
                        <td class="py-1">{{ $item['napomena'] ?? '' }}</td>
                      </tr>
                    @empty
                      <tr>
                        <td colspan="5" class="text-center text-muted py-2">Nema stavki za ovaj radni nalog.</td>
                      </tr>
                    @endforelse
                  </tbody>
                </table>
              </div>
            </div>
            <!-- Operacija Tab -->
            <div class="tab-pane fade" id="tab-operacija" role="tabpanel">
              <div class="table-responsive">
                <table class="table" id="operacija-table">
                  <thead>
                    <tr>
                      <th class="py-1 text-center">Alternativa</th>
                      <th class="py-1 text-center">Pozicija</th>
                      <th class="py-1 text-center">Operacija</th>
                      <th class="py-1 text-center">Naziv</th>
                      <th class="py-1 text-center">Napo...</th>
                      <th class="py-1 text-center">MJ</th>
                      <th class="py-1 text-center">MJ/vrij.</th>
                      <th class="py-1 text-center">nor.os.</th>
                      <th class="py-1 text-center">VA</th>
                      <th class="py-1 text-center">Prim.klas.</th>
                      <th class="py-1 text-center">Sek.klas.</th>
                    </tr>
                  </thead>
                  <tbody>
                    @forelse(($workOrderRegOperations ?? []) as $operation)
                      <tr>
                        <td class="py-1">{{ $operation['alternativa'] ?? '' }}</td>
                        <td class="py-1">{{ $operation['pozicija'] ?? '' }}</td>
                        <td class="py-1">{{ $operation['operacija'] ?? '' }}</td>
                        <td class="py-1">{{ $operation['naziv'] ?? '' }}</td>
                        <td class="py-1">{{ $operation['napomena'] ?? '' }}</td>
                        <td class="py-1">{{ $operation['mj'] ?? '' }}</td>
                        <td class="py-1">{{ $operation['mj_vrij'] ?? '' }}</td>
                        <td class="py-1">{{ $operation['normativna_osnova'] ?? '' }}</td>
                        <td class="py-1">{{ $operation['va'] ?? '' }}</td>
                        <td class="py-1">{{ $operation['prim_klas'] ?? '' }}</td>
                        <td class="py-1">{{ $operation['sek_klas'] ?? '' }}</td>
                      </tr>
                    @empty
                      <tr>
                        <td colspan="11" class="text-center text-muted py-2">Nema operacija za ovaj radni nalog.</td>
                      </tr>
                    @endforelse
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
        <!-- Invoice Description ends -->
      </div>
    </div>
    <!-- /Invoice -->

    <!-- Invoice Actions -->
    <div class="col-xl-3 col-md-4 col-12 invoice-actions mt-md-0 mt-2">
      <div class="card">
        <div class="card-body">
          <button class="btn btn-primary w-100 mb-75 d-flex justify-content-center align-items-center" data-bs-toggle="modal" data-bs-target="#qr-scanner-modal">
            <i class="fa fa-qrcode me-50" style="font-size: 20px;"></i> Skeniraj radni nalog
          </button>
          <button class="btn btn-success w-100 mb-75 d-flex justify-content-center align-items-center" data-bs-toggle="modal" data-bs-target="#sirovina-scanner-modal">
            <i class="fa fa-qrcode me-50" style="font-size: 20px;"></i> Dodaj sirovinu
          </button>
          <div class="invoice-actions-divider"></div>
          <button class="btn w-100 mb-75 d-flex justify-content-center align-items-center wo-side-meta-btn wo-side-meta-btn-{{ $statusToneClass }}" data-bs-toggle="modal" data-bs-target="#change-status-modal">
            <i class="fa fa-circle-notch me-50"></i> Status: {{ $statusDisplayLabel !== '' ? $statusDisplayLabel : 'N/A' }}
          </button>
          <button class="btn w-100 mb-75 d-flex justify-content-center align-items-center wo-side-meta-btn wo-side-meta-btn-{{ $priorityToneClass }}" data-bs-toggle="modal" data-bs-target="#change-priority-modal">
            {{ $priorityDisplayLabel !== '' ? $priorityDisplayLabel : 'N/A' }}
          </button>
          <div class="invoice-actions-divider"></div>
          <button class="btn btn-outline-primary w-100 mb-75 d-flex justify-content-center align-items-center" type="button" onclick="alert('Uskoro')">
            <i class="fa fa-cube me-50" style="font-size: 20px;"></i> Dodaj materijal
          </button>
          <button class="btn btn-outline-secondary w-100 mb-75 d-flex justify-content-center align-items-center" type="button" onclick="alert('Uskoro')">
            <i class="fa fa-cog me-50" style="font-size: 20px;"></i> Dodaj operaciju
          </button>
          <button class="btn btn-outline-secondary w-100 mb-75 d-flex justify-content-center align-items-center" data-bs-toggle="modal" data-bs-target="#send-invoice-sidebar">
            <i class="fa fa-paper-plane me-50"></i> Pošalji
          </button>
          <a class="btn btn-outline-secondary w-100 d-flex justify-content-center align-items-center" href="{{url('app/invoice/print')}}" target="_blank">
            <i class="fa fa-print me-50"></i> Isprintaj
          </a>
        </div>
      </div>
    </div>
    <!-- /Invoice Actions -->
  </div>
</section>

<!-- Send Invoice Sidebar -->
<div class="modal modal-slide-in fade" id="send-invoice-sidebar" aria-hidden="true">
  <div class="modal-dialog sidebar-lg">
    <div class="modal-content p-0">
      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zatvori">×</button>
      <div class="modal-header mb-1">
        <h5 class="modal-title">
          <span class="align-middle">Pošalji fakturu</span>
        </h5>
      </div>
      <div class="modal-body flex-grow-1">
        <form>
          <div class="mb-1">
            <label for="invoice-from" class="form-label">Od</label>
            <input
              type="text"
              class="form-control"
              id="invoice-from"
              value="shelbyComapny@email.com"
              placeholder="company@email.com"
            />
          </div>
          <div class="mb-1">
            <label for="invoice-to" class="form-label">Za</label>
            <input
              type="text"
              class="form-control"
              id="invoice-to"
              value="qConsolidated@email.com"
              placeholder="company@email.com"
            />
          </div>
          <div class="mb-1">
            <label for="invoice-subject" class="form-label">Predmet</label>
            <input
              type="text"
              class="form-control"
              id="invoice-subject"
              value="Faktura za Trendy d.o.o."
              placeholder="Faktura u vezi robe"
            />
          </div>
          <div class="mb-1">
            <label for="invoice-message" class="form-label">Poruka</label>
            <textarea
              class="form-control"
              name="invoice-message"
              id="invoice-message"
              cols="3"
              rows="11"
              placeholder="Poruka..."
            >
Poštovani,

Hvala vam na poslovanju, uvijek je zadovoljstvo raditi sa vama!

Generirali smo novu fakturu u iznosu od 95.59 KM

Cijenili bismo plaćanje ove fakture do 05/11/2019</textarea
            >
          </div>
          <div class="mb-1">
            <span class="badge badge-light-primary">
              <i data-feather="link" class="me-25"></i>
              <span class="align-middle">Faktura priložena</span>
            </span>
          </div>
          <div class="mb-1 d-flex flex-wrap mt-2">
            <button type="button" class="btn btn-primary me-1" data-bs-dismiss="modal">Pošalji</button>
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Otkaži</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<!-- /Send Invoice Sidebar -->

<!-- Add Payment Sidebar -->
<div class="modal modal-slide-in fade" id="add-payment-sidebar" aria-hidden="true">
  <div class="modal-dialog sidebar-lg">
    <div class="modal-content p-0">
      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zatvori">×</button>
      <div class="modal-header mb-1">
        <h5 class="modal-title">
          <span class="align-middle">Dodaj plaćanje</span>
        </h5>
      </div>
      <div class="modal-body flex-grow-1">
        <form>
          <div class="mb-1">
            <input id="balance" class="form-control" type="text" value="Stanje fakture: 5000.00 KM" disabled />
          </div>
          <div class="mb-1">
            <label class="form-label" for="amount">Iznos plaćanja</label>
            <input id="amount" class="form-control" type="number" placeholder="1000 KM" />
          </div>
          <div class="mb-1">
            <label class="form-label" for="payment-date">Datum plaćanja</label>
            <input id="payment-date" class="form-control date-picker" type="text" />
          </div>
          <div class="mb-1">
            <label class="form-label" for="payment-method">Način plaćanja</label>
            <select class="form-select" id="payment-method">
              <option value="" selected disabled>Odaberi način plaćanja</option>
              <option value="Cash">Gotovina</option>
              <option value="Bank Transfer">Bankovni transfer</option>
              <option value="Debit">Debitna kartica</option>
              <option value="Credit">Kreditna kartica</option>
              <option value="Paypal">Paypal</option>
            </select>
          </div>
          <div class="mb-1">
            <label class="form-label" for="payment-note">Interna napomena o plaćanju</label>
            <textarea class="form-control" id="payment-note" rows="5" placeholder="Interna napomena o plaćanju"></textarea>
          </div>
          <div class="d-flex flex-wrap mb-0">
            <button type="button" class="btn btn-primary me-1" data-bs-dismiss="modal">Pošalji</button>
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Otkaži</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<!-- /Add Payment Sidebar -->
@endsection

@section('vendor-script')
<script src="{{asset('vendors/js/forms/repeater/jquery.repeater.min.js')}}"></script>
<script src="{{asset('vendors/js/pickers/flatpickr/flatpickr.min.js')}}"></script>
@endsection

@section('page-script')
<script src="{{asset('js/scripts/pages/app-invoice.js')}}"></script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    var sidebar = document.querySelector('.invoice-preview-wrapper .invoice-actions');
    if (!sidebar) {
      return;
    }

    var onScroll = function () {
      sidebar.classList.toggle('invoice-actions-scrolled', window.scrollY > 80);
    };

    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
  });
</script>
{{-- Include QR Scanner Modals --}}
@include('content.new-components.change-status-modal', ['currentStatus' => $statusDisplayLabel])
@include('content.new-components.change-priority-modal', ['currentPriority' => $priorityDisplayLabel])
@include('content.new-components.nalog-scan')
@include('content.new-components.sirovina-scan')
@include('content.new-components.confirm-weight')
@endsection

