@php
  $currency = (string) ($invoice['currency'] ?? 'BAM');
  $invoiceType = (string) ($invoice['invoiceType'] ?? 'predracun');
  $items = is_array($invoice['items'] ?? null) ? $invoice['items'] : [];
  $printItemPages = $printItemPages ?: [[]];
  $notes = (string) ($invoice['notes'] ?? '');
  $noteLineCount = preg_match_all('/\r\n|\r|\n/', $notes) + 1;
  $hasMultiplePrintPages = count($printItemPages) > 1;
  $hasLongPrintTable = count($items) > 10;
  $hasExpandedPrintNotes = strlen($notes) > 260 || $noteLineCount > 4;
  $qlaLogoUrl = 'https://deklarant.ai/build/images/logo-qla.png';
  $formatMoney = static function ($amount) use ($currency): string {
      return $currency . ' ' . number_format((float) $amount, 2, '.', ',');
  };
  $formatQuantity = static function ($amount): string {
      $formatted = number_format(round((float) $amount, 4), 4, '.', '');

      return rtrim(rtrim($formatted, '0'), '.') ?: '0';
  };
  $itemQuantityTotal = array_reduce($items, static function (float $sum, array $item): float {
      return $sum + (float) ($item['hoursOrQty'] ?? 0);
  }, 0.0);
  $itemTotal = array_reduce($items, static function (float $sum, array $item): float {
      return $sum + (float) ($item['total'] ?? 0);
  }, 0.0);
  $formatDateForDisplay = static function ($value): string {
      if ($value instanceof \Illuminate\Support\Carbon) {
          return $value->format('d.m.Y');
      }

      $value = trim((string) $value);

      if ($value === '') {
          return '';
      }

      try {
          return \Illuminate\Support\Carbon::parse($value)->format('d.m.Y');
      } catch (\Throwable $exception) {
          return $value;
      }
  };
  $documentTypeLabel = $documentTitle ?? ($invoiceType === 'faktura' ? 'Faktura' : 'Predra&#269;un');
@endphp
<!doctype html>
<html lang="bs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $documentTypeLabel }} {{ $invoice['fiscalNumber'] ?? '' }}</title>
  <style>
    :root {
      --invoice-gray-950: #030712;
      --invoice-gray-900: #111827;
      --invoice-gray-800: #1f2937;
      --invoice-gray-700: #374151;
      --invoice-gray-650: #4b5563;
      --invoice-gray-500: #6b7280;
      --invoice-gray-405: #9ca3af;
      --invoice-gray-400: #9ca3af;
      --invoice-gray-200: #e5e7eb;
      --invoice-gray-100: #f3f4f6;
      --invoice-gray-50: #f9fafb;
      --invoice-blue-700: #1d4ed8;
      --invoice-blue-600: #2563eb;
      --invoice-blue-500: #3b82f6;
      --invoice-blue-50: #eff6ff;
      --invoice-red-500: #ef4444;
      --invoice-emerald-600: #059669;
    }

    * {
      box-sizing: border-box;
    }

    html,
    body {
      min-height: 100%;
      margin: 0;
      background: var(--invoice-gray-50);
      color: var(--invoice-gray-800);
      font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      -webkit-font-smoothing: antialiased;
    }

    button,
    select {
      font: inherit;
    }

    .invoice-screen {
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      background: var(--invoice-gray-50);
    }

    .invoice-toolbar {
      position: sticky;
      top: 0;
      z-index: 20;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      padding: 0.9rem 1.25rem;
      background: rgba(255, 255, 255, 0.94);
      border-bottom: 1px solid var(--invoice-gray-200);
      backdrop-filter: blur(14px);
    }

    .toolbar-left,
    .toolbar-right,
    .document-type-switch {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      flex-wrap: wrap;
    }

    .toolbar-title {
      display: flex;
      flex-direction: column;
      gap: 0.1rem;
      min-width: 12rem;
    }

    .toolbar-title strong {
      color: var(--invoice-gray-900);
      font-size: 0.95rem;
    }

    .toolbar-title span {
      color: var(--invoice-gray-500);
      font-size: 0.72rem;
    }

    .toolbar-button,
    .type-button,
    .payment-select {
      min-height: 2.15rem;
      border: 1px solid var(--invoice-gray-200);
      border-radius: 0.65rem;
      background: #fff;
      color: var(--invoice-gray-650);
      padding: 0.45rem 0.75rem;
      font-size: 0.78rem;
      font-weight: 700;
      cursor: pointer;
      text-decoration: none;
      transition: border-color 0.16s ease, background 0.16s ease, color 0.16s ease;
    }

    .toolbar-button:hover,
    .type-button:hover {
      border-color: #cbd5e1;
      background: var(--invoice-gray-50);
      color: var(--invoice-gray-900);
    }

    .type-button.is-active,
    .toolbar-button.is-primary {
      border-color: var(--invoice-blue-600);
      background: var(--invoice-blue-600);
      color: #fff;
    }

    .toolbar-right {
      justify-content: flex-end;
      flex-wrap: wrap;
    }

    .toolbar-right .invoice-pdf-button {
      margin-left: auto;
    }

    .payment-select {
      cursor: default;
      color: var(--invoice-gray-700);
      font-family: "JetBrains Mono", ui-monospace, SFMono-Regular, monospace;
      font-size: 0.7rem;
    }

    .invoice-layout {
      flex: 1;
      width: 100%;
      max-width: none;
      margin: 0 auto;
      padding: 1rem;
      display: flex;
      gap: 1.5rem;
    }

    .invoice-paper-shell {
      flex: 1;
      display: flex;
      justify-content: center;
      align-items: flex-start;
      min-width: 0;
      overflow-x: auto;
    }

    .printable-area {
      position: relative;
      flex: 0 0 210mm;
      width: 210mm;
      max-width: 210mm;
      min-height: 297mm;
      overflow: visible;
      background: #fff;
      color: var(--invoice-gray-900);
      padding: 12mm;
      border: 1px solid rgba(229, 231, 235, 0.75);
      border-radius: 0.75rem;
      box-shadow: 0 18px 52px rgba(15, 23, 42, 0.08);
    }

    .invoice-main {
      position: relative;
      z-index: 1;
    }

    .invoice-header {
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      align-items: flex-start;
      justify-content: space-between;
      gap: 1.5rem;
      margin-bottom: 2.5rem;
    }

    .invoice-brand {
      display: flex;
      flex-direction: column;
      align-items: flex-start;
    }

    .invoice-brand-logo {
      height: 2.75rem;
      width: auto;
      object-fit: contain;
      display: block;
      user-select: none;
    }

    .invoice-brand-fallback {
      display: none;
      color: var(--invoice-gray-900);
      font-size: 1.8rem;
      font-weight: 800;
      letter-spacing: 0;
    }

    .invoice-brand-tagline {
      margin: 0.45rem 0 0;
      color: var(--invoice-gray-500);
      font-family: "Space Grotesk", Inter, sans-serif;
      font-size: 0.63rem;
      line-height: 1.05;
      font-weight: 700;
      letter-spacing: 0.24em;
      text-transform: uppercase;
    }

    .invoice-brand-tagline span {
      display: block;
    }

    .invoice-title-block {
      justify-self: end;
      min-width: 42mm;
      text-align: right;
    }

    .invoice-title {
      margin: 0;
      color: var(--invoice-gray-900);
      font-size: 2rem;
      line-height: 1.1;
      font-weight: 800;
      letter-spacing: 0;
    }

    .invoice-number-line {
      margin-top: 0.25rem;
      display: flex;
      align-items: center;
      justify-content: flex-end;
      color: var(--invoice-gray-500);
      font-size: 0.7rem;
      font-weight: 700;
      letter-spacing: 0.04em;
    }

    .invoice-number-line strong {
      color: var(--invoice-gray-700);
      margin-left: 0.25rem;
      font-family: "JetBrains Mono", ui-monospace, SFMono-Regular, monospace;
      font-weight: 600;
    }

    .invoice-party-grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 2rem;
      margin-bottom: 2rem;
      padding-bottom: 2rem;
      border-bottom: 1px solid rgba(243, 244, 246, 0.95);
      font-size: 0.76rem;
    }

    .section-label {
      margin: 0 0 0.55rem;
      color: var(--invoice-gray-400);
      font-size: 0.63rem;
      line-height: 1.2;
      font-weight: 800;
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }

    .party-lines,
    .payment-detail-grid {
      display: grid;
      gap: 0.3rem;
    }

    .party-name {
      color: var(--invoice-gray-800);
      font-weight: 800;
    }

    .party-muted,
    .payment-detail-grid dt {
      color: var(--invoice-gray-500);
    }

    .party-mono,
    .payment-detail-grid dd {
      color: var(--invoice-gray-650);
      font-family: "JetBrains Mono", ui-monospace, SFMono-Regular, monospace;
      font-size: 0.68rem;
    }

    .payment-detail-grid {
      grid-template-columns: auto 1fr;
      column-gap: 0.85rem;
      row-gap: 0.48rem;
      margin: 0;
    }

    .payment-detail-grid dt,
    .payment-detail-grid dd {
      margin: 0;
    }

    .payment-detail-grid dd {
      color: var(--invoice-gray-700);
      font-weight: 700;
      font-family: Inter, ui-sans-serif, system-ui, sans-serif;
    }

    .invoice-meta {
      display: flex;
      align-items: flex-start;
      justify-content: flex-end;
      gap: 1rem;
      margin-bottom: 2rem;
    }

    .meta-grid {
      grid-template-columns: minmax(8.75rem, auto) minmax(10rem, 1fr);
      column-gap: 1.5rem;
      row-gap: 0.75rem;
      font-size: 0.76rem;
      display: none;
    }

    .meta-label {
      color: var(--invoice-gray-405);
    }

    .meta-value {
      color: var(--invoice-gray-700);
      font-weight: 700;
    }

    .amount-due-box {
      min-width: 15rem;
      padding: 1.1rem 1.5rem;
      border: 1px solid rgba(243, 244, 246, 0.75);
      border-radius: 0.75rem;
      background: var(--invoice-gray-50);
      text-align: right;
      margin-left: auto;
    }

    .amount-due-box .amount-label {
      color: var(--invoice-gray-400);
      font-size: 0.63rem;
      font-weight: 800;
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }

    .amount-due-box .amount-value {
      margin-top: 0.3rem;
      color: var(--invoice-gray-900);
      font-size: 1.5rem;
      line-height: 1.1;
      font-weight: 800;
      letter-spacing: 0;
    }

    .invoice-items-table {
      margin-bottom: 2rem;
      overflow: hidden;
      font-size: 0.76rem;
      user-select: text;
    }

    .invoice-items-table table,
    table.invoice-items-table {
      width: 100%;
      border-collapse: collapse;
      text-align: left;
    }

    .invoice-items-table thead tr {
      border-bottom: 1px solid rgba(31, 41, 55, 0.82);
      color: var(--invoice-gray-400);
      font-size: 0.63rem;
      font-weight: 800;
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }

    .invoice-items-table th,
    .invoice-items-table td {
      padding-top: 0.72rem;
      padding-bottom: 0.72rem;
      vertical-align: top;
    }

    .invoice-items-table tbody tr {
      border-bottom: 1px solid var(--invoice-gray-100);
    }

    .invoice-items-table tfoot tr {
      border-top: 2px solid var(--invoice-gray-700);
      color: var(--invoice-gray-900);
      font-weight: 800;
    }

    .invoice-items-table tfoot td {
      padding-top: 0.82rem;
      padding-bottom: 0.82rem;
    }

    .invoice-items-total-label {
      padding-right: 1rem;
      text-align: right;
      text-transform: uppercase;
    }

    .item-description {
      padding-left: 1rem;
      padding-right: 1rem;
      color: var(--invoice-gray-800);
      font-weight: 700;
      line-height: 1.45;
      text-align: center;
      white-space: pre-wrap;
      overflow-wrap: anywhere;
    }

    .item-date-time {
      width: 7.5rem;
      padding-right: 1rem;
      color: var(--invoice-gray-700);
      font-family: "JetBrains Mono", ui-monospace, SFMono-Regular, monospace;
      white-space: nowrap;
    }

    .item-center {
      width: 6rem;
      text-align: center;
      color: var(--invoice-gray-700);
      font-family: "JetBrains Mono", ui-monospace, SFMono-Regular, monospace;
    }

    .item-right {
      width: 7rem;
      text-align: right;
      color: var(--invoice-gray-700);
      font-family: "JetBrains Mono", ui-monospace, SFMono-Regular, monospace;
    }

    .item-total {
      width: 7rem;
      padding-right: 0.25rem;
      text-align: right;
      color: var(--invoice-gray-900);
      font-family: "JetBrains Mono", ui-monospace, SFMono-Regular, monospace;
      font-weight: 700;
    }

    .invoice-items-table thead th {
      color: var(--invoice-gray-700);
    }

    .item-description-heading {
      text-align: center;
    }

    .invoice-empty-row {
      color: var(--invoice-gray-500);
      font-weight: 500;
    }

    .invoice-footer {
      margin-top: 2rem;
      padding-top: 1.5rem;
      border-top: 1px solid rgba(243, 244, 246, 0.95);
    }

    .invoice-footer-grid {
      display: grid;
      grid-template-columns: minmax(0, 1fr) 20rem;
      align-items: flex-start;
      gap: 2rem;
    }

    .invoice-notes {
      width: 100%;
      max-width: none;
    }

    .invoice-print-notes,
    .invoice-conditions {
      color: var(--invoice-gray-500);
      font-size: 0.7rem;
      line-height: 1.55;
      white-space: pre-wrap;
      overflow-wrap: anywhere;
    }

    .invoice-conditions-block {
      margin-top: 0.75rem;
    }

    .invoice-totals {
      width: 20rem;
      color: var(--invoice-gray-600);
      font-size: 0.76rem;
    }

    .total-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      margin-bottom: 0.55rem;
    }

    .total-row span:last-child {
      color: var(--invoice-gray-700);
      font-family: "JetBrains Mono", ui-monospace, SFMono-Regular, monospace;
      font-weight: 700;
      text-align: right;
    }

    .total-row.discount {
      color: var(--invoice-red-500);
    }

    .total-row.advance {
      color: var(--invoice-emerald-600);
    }

    .total-divider {
      border-top: 1px solid var(--invoice-gray-100);
      margin: 0.7rem 0;
    }

    .total-row.grand {
      margin-bottom: 0;
      color: var(--invoice-gray-950);
      font-size: 1rem;
      font-weight: 800;
    }

    .total-row.grand span:last-child {
      color: var(--invoice-gray-900);
      font-family: Inter, ui-sans-serif, system-ui, sans-serif;
      font-size: 1rem;
      letter-spacing: 0;
    }

    .invoice-approval {
      position: relative;
      margin-top: 1.25rem;
      margin-left: auto;
      width: 15rem;
      height: 8.5rem;
      pointer-events: none;
      user-select: none;
      break-inside: avoid-page;
      page-break-inside: avoid;
    }

    .invoice-signature-line {
      position: absolute;
      right: 0.55rem;
      bottom: 1.45rem;
      width: 10.9rem;
      border-top: 1px solid rgba(120, 131, 148, 0.4);
    }

    .invoice-signature,
    .invoice-stamp {
      position: absolute;
      display: block;
      background: transparent;
      mix-blend-mode: multiply;
      transform-origin: center;
    }

    .invoice-signature {
      right: 0;
      bottom: 0;
      width: 14rem;
      opacity: 1;
      filter: grayscale(1) contrast(1.38) sepia(1) saturate(12) hue-rotate(188deg) brightness(0.6);
    }

    .invoice-stamp {
      right: 2.35rem;
      bottom: 1.15rem;
      width: 8.25rem;
      opacity: 0.58;
      mix-blend-mode: normal;
      transform: rotate(-14deg);
      filter: brightness(0) saturate(100%) invert(30%) sepia(84%) saturate(1680%) hue-rotate(192deg) brightness(96%) contrast(92%);
    }

    .print-only,
    .print-invoice-items-pages {
      display: none;
    }

    @media (max-width: 900px) {
      .invoice-toolbar {
        flex-wrap: wrap;
      }

      .invoice-totals {
        width: 100%;
      }
    }

    @media print {
      @page {
        size: A4 portrait;
        margin: 0;
      }

      html,
      body {
        width: 210mm !important;
        min-height: 297mm !important;
        background: white !important;
        color: black !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: visible !important;
      }

      .invoice-screen,
      .invoice-layout,
      .invoice-paper-shell {
        display: block !important;
        width: 210mm !important;
        max-width: 210mm !important;
        min-width: 0 !important;
        min-height: 0 !important;
        height: auto !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: visible !important;
      }

      .no-print {
        display: none !important;
      }

      .screen-only {
        display: none !important;
      }

      .print-only {
        display: inline !important;
      }

      .printable-area {
        position: relative !important;
        display: flex !important;
        flex-direction: column !important;
        width: 210mm !important;
        max-width: 210mm !important;
        margin: 0 !important;
        min-height: 297mm !important;
        padding: 10mm 10mm 6mm !important;
        overflow: visible !important;
        box-shadow: none !important;
        border: none !important;
        border-radius: 0 !important;
        background: white !important;
        color-adjust: exact;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
        break-before: auto !important;
        page-break-before: auto !important;
      }

      .invoice-main {
        flex: 0 0 auto !important;
      }

      .invoice-meta {
        display: none !important;
      }

      .amount-due-box {
        width: 58mm !important;
        min-width: 58mm !important;
        margin-left: auto !important;
      }

      .invoice-header {
        display: grid !important;
        grid-template-columns: minmax(0, 1fr) auto !important;
        align-items: flex-start !important;
      }

      .invoice-title-block {
        justify-self: end !important;
        min-width: 42mm !important;
        text-align: right !important;
      }

      .invoice-number-line {
        justify-content: flex-end !important;
      }

      .invoice-footer {
        position: static !important;
        left: auto !important;
        right: auto !important;
        bottom: auto !important;
        margin-top: 8mm !important;
      }

      .invoice-print-notes {
        display: block !important;
        white-space: pre-wrap !important;
        overflow: visible !important;
        word-break: break-word !important;
      }

      .invoice-header,
      .invoice-party-grid,
      .invoice-meta,
      .invoice-footer,
      .invoice-item-print-page {
        break-inside: avoid-page;
        page-break-inside: avoid;
      }

      .screen-invoice-items-table {
        display: none !important;
      }

      .print-invoice-items-pages {
        display: block !important;
      }

      .invoice-item-print-page {
        margin-bottom: 8mm;
      }

      .has-long-print-table .invoice-item-print-page {
        break-inside: auto !important;
        page-break-inside: auto !important;
      }

      .invoice-item-print-page + .invoice-item-print-page {
        break-before: page;
        page-break-before: always;
      }

      .invoice-items-table thead {
        display: table-header-group;
      }

      .invoice-item-print-page .invoice-items-table tfoot {
        display: table-row-group;
      }

      .invoice-item-print-page .invoice-items-table {
        width: 100% !important;
        table-layout: fixed !important;
        border-collapse: collapse !important;
      }

      .invoice-item-print-page .item-date-time {
        width: 32mm !important;
      }

      .invoice-item-print-page .item-center {
        width: 20mm !important;
      }

      .invoice-item-print-page .item-right {
        width: 31mm !important;
      }

      .invoice-item-print-page .item-total {
        width: 29mm !important;
      }

      .invoice-item-print-page .item-description,
      .invoice-item-print-page .item-description-heading {
        width: auto !important;
      }

      .invoice-items-table tr {
        break-inside: avoid;
        page-break-inside: avoid;
      }

      .invoice-party-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
      }

      .invoice-print-payment-details {
        display: block !important;
      }

      .invoice-approval {
        width: 54mm;
        height: 18mm;
        margin-top: 1mm;
      }

      .invoice-signature-line {
        position: absolute;
        right: 2mm;
        bottom: 1mm;
        width: 38mm;
        border-top: 0.35mm solid rgba(120, 131, 148, 0.45);
      }

      .invoice-signature {
        width: 50mm;
      }

      .invoice-stamp {
        right: 9mm;
        bottom: 0;
        width: 27mm;
      }

      .force-print-bg {
        background-color: #f3f4f6 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
      }
    }
  </style>
</head>
<body>
  <div id="app-root-container" class="invoice-screen" data-invoice='@json($invoice)'>
    <div class="invoice-toolbar no-print">
      <div class="toolbar-left">
        <div class="toolbar-title">
          <strong>{{ $documentTypeLabel }} {{ $invoice['fiscalNumber'] ?? '' }}</strong>
          <span>{{ $invoice['buyer']['name'] ?? '' }}</span>
        </div>
      </div>
      <div class="toolbar-right">
        <button type="button" class="toolbar-button is-primary invoice-pdf-button" id="invoice-print-button">
          Preuzmi PDF
        </button>
      </div>
    </div>

    <div class="invoice-layout">
      <div class="invoice-paper-shell">
        <div
          id="invoice-sheet-print"
          class="printable-area {{ $hasMultiplePrintPages ? 'has-multiple-print-pages' : '' }} {{ $hasLongPrintTable ? 'has-long-print-table' : '' }} {{ $hasExpandedPrintNotes ? 'has-expanded-print-notes' : '' }}"
        >
          <div class="invoice-main">
            <div class="invoice-header">
              <div class="invoice-brand">
                <img
                  src="{{ $qlaLogoUrl }}"
                  alt="qla.dev"
                  class="invoice-brand-logo"
                  referrerpolicy="no-referrer"
                  onerror="this.style.display='none';this.nextElementSibling.style.display='block';"
                >
                <div class="invoice-brand-fallback">qla.dev</div>
                <p class="invoice-brand-tagline">
                  <span>Developing</span>
                  <span>the next</span>
                  <span>generation</span>
                  <span>of tech</span>
                </p>
              </div>

              <div class="invoice-title-block">
                <h1 class="invoice-title">{{ $documentTypeLabel }}</h1>
                <div class="invoice-number-line">
                  <span>BF:</span>
                  <strong>#{{ $invoice['fiscalNumber'] ?? '' }}</strong>
                </div>
              </div>
            </div>

            <div class="invoice-party-grid">
              <div>
                <h4 class="section-label">Pru&#382;alac Usluga</h4>
                <div class="party-lines">
                  <div class="party-name">{{ $invoice['seller']['name'] ?? '' }}</div>
                  <div class="party-muted">{{ $invoice['seller']['address'] ?? '' }}</div>
                  <div class="party-mono">ID: {{ $invoice['seller']['idNumber'] ?? '' }}</div>
                  <div class="party-mono">Ra&#269;un: {{ $invoice['seller']['bankAccount'] ?? '' }}</div>
                </div>
              </div>

              <div>
                <h4 class="section-label">Primalac / Upla&#263;uje</h4>
                <div class="party-lines">
                  <div class="party-name">{{ $invoice['buyer']['name'] ?? '' }}</div>
                  <div class="party-muted">
                    {{ $invoice['buyer']['address'] ?? '' }}, {{ $invoice['buyer']['zipCode'] ?? '' }} {{ $invoice['buyer']['city'] ?? '' }}
                  </div>
                  <div class="party-mono">ID / IBK: {{ $invoice['buyer']['ibk'] ?? '' }}</div>
                </div>
              </div>

              <div class="invoice-print-payment-details">
                <h4 class="section-label">Datum / Pla&#263;anje</h4>
                <dl class="payment-detail-grid">
                  <dt>Datum fakture:</dt>
                  <dd>{{ $formatDateForDisplay($invoice['createdAt'] ?? '') }}</dd>
                  <dt>Datum isporuke:</dt>
                  <dd>{{ $formatDateForDisplay($invoice['deliveryDate'] ?? '') }}</dd>
                  <dt>Datum pla&#263;anja:</dt>
                  <dd>{{ $formatDateForDisplay($invoice['dueDate'] ?? '') }}</dd>
                  <dt>Rabat (popust%):</dt>
                  <dd>{{ number_format((float) ($invoice['discountPercent'] ?? 0), 0, ',', '.') }}%</dd>
                </dl>
              </div>
            </div>

            <div class="invoice-meta">
              <div class="meta-grid">
                <div class="meta-label">Datum fakture:</div>
                <div class="meta-value">{{ $formatDateForDisplay($invoice['createdAt'] ?? '') }}</div>
                <div class="meta-label">Datum isporuke:</div>
                <div class="meta-value">{{ $formatDateForDisplay($invoice['deliveryDate'] ?? '') }}</div>
                <div class="meta-label">Datum pla&#263;anja:</div>
                <div class="meta-value">{{ $formatDateForDisplay($invoice['dueDate'] ?? '') }}</div>
                <div class="meta-label">Rabat (popust%):</div>
                <div class="meta-value">{{ number_format((float) ($invoice['discountPercent'] ?? 0), 0, ',', '.') }}%</div>
              </div>

              <div class="amount-due-box force-print-bg">
                <div class="amount-label">Za platiti:</div>
                <div class="amount-value">{{ $formatMoney($totals['total'] ?? 0) }}</div>
              </div>
            </div>

            <div id="invoice-items-table" class="invoice-items-table screen-invoice-items-table">
              <table>
                <thead>
                  <tr>
                    <th class="item-date-time">Datum i vrijeme</th>
                    <th class="item-description-heading">Stavke</th>
                    <th class="item-center">Koli&#269;ina</th>
                    <th class="item-right">Jed. cijena ({{ $currency }})</th>
                    <th class="item-total">Total ({{ $currency }})</th>
                  </tr>
                </thead>
                <tbody>
                  @forelse ($items as $item)
                    <tr>
                      <td class="item-date-time">{{ $item['dateTime'] ?? '-' }}</td>
                      <td class="item-description">{{ $item['description'] ?? '' }}</td>
                      <td class="item-center">{{ $formatQuantity($item['hoursOrQty'] ?? 0) }}</td>
                      <td class="item-right">{{ $formatMoney($item['price'] ?? 0) }}</td>
                      <td class="item-total">{{ $formatMoney($item['total'] ?? 0) }}</td>
                    </tr>
                  @empty
                    <tr>
                      <td colspan="5" class="invoice-empty-row">Nema AI History zapisa za ovaj obra&#269;unski period.</td>
                    </tr>
                  @endforelse
                </tbody>
                @if (count($items) > 0)
                  <tfoot>
                    <tr>
                      <td colspan="2" class="invoice-items-total-label">Ukupno ({{ count($items) }} {{ count($items) === 1 ? 'stavka' : 'stavki' }})</td>
                      <td class="item-center">{{ $formatQuantity($itemQuantityTotal) }}</td>
                      <td class="item-right">-</td>
                      <td class="item-total">{{ $formatMoney($itemTotal) }}</td>
                    </tr>
                  </tfoot>
                @endif
              </table>
            </div>

            <div class="print-invoice-items-pages">
              @foreach ($printItemPages as $pageItems)
                <div class="invoice-item-print-page">
                  <table class="invoice-items-table">
                    <thead>
                      <tr>
                        <th class="item-date-time">Datum i vrijeme</th>
                        <th class="item-description-heading">Stavke</th>
                        <th class="item-center">Koli&#269;ina</th>
                        <th class="item-right">Jed. cijena ({{ $currency }})</th>
                        <th class="item-total">Total ({{ $currency }})</th>
                      </tr>
                    </thead>
                    <tbody>
                      @forelse ($pageItems as $item)
                        <tr>
                          <td class="item-date-time">{{ $item['dateTime'] ?? '-' }}</td>
                          <td class="item-description">{{ $item['description'] ?? '' }}</td>
                          <td class="item-center">{{ $formatQuantity($item['hoursOrQty'] ?? 0) }}</td>
                          <td class="item-right">{{ $formatMoney($item['price'] ?? 0) }}</td>
                          <td class="item-total">{{ $formatMoney($item['total'] ?? 0) }}</td>
                        </tr>
                      @empty
                        <tr>
                          <td colspan="5" class="invoice-empty-row">Nema AI History zapisa za ovaj obra&#269;unski period.</td>
                        </tr>
                      @endforelse
                    </tbody>
                    @if ($loop->last && count($pageItems) > 0)
                      <tfoot>
                        <tr>
                          <td colspan="2" class="invoice-items-total-label">Ukupno ({{ count($items) }} {{ count($items) === 1 ? 'stavka' : 'stavki' }})</td>
                          <td class="item-center">{{ $formatQuantity($itemQuantityTotal) }}</td>
                          <td class="item-right">-</td>
                          <td class="item-total">{{ $formatMoney($itemTotal) }}</td>
                        </tr>
                      </tfoot>
                    @endif
                  </table>
                </div>
              @endforeach
            </div>
          </div>

          <div class="invoice-footer">
            <div class="invoice-footer-grid">
              <div class="invoice-notes">
                <h4 class="section-label">Napomena:</h4>
                <div class="invoice-print-notes">{{ $invoice['notes'] ?? '' }}</div>
                <div class="invoice-conditions-block">
                  <h4 class="section-label">Uslovi pla&#263;anja:</h4>
                  <div class="invoice-conditions">{{ $invoice['conditions'] ?? '' }}</div>
                </div>
              </div>

              <div class="invoice-totals">
                <div class="total-row">
                  <span>Total:</span>
                  <span>{{ $formatMoney($totals['subtotal'] ?? 0) }}</span>
                </div>
                @if (($totals['discount'] ?? 0) > 0)
                  <div class="total-row discount">
                    <span>Popust ({{ number_format((float) ($invoice['discountPercent'] ?? 0), 0, ',', '.') }}%):</span>
                    <span>- {{ $formatMoney($totals['discount'] ?? 0) }}</span>
                  </div>
                @endif
                <div class="total-row">
                  <span>Porez:</span>
                  <span>{{ $formatMoney($totals['vat'] ?? 0) }}</span>
                </div>
                @if ($invoiceType === 'avansna')
                  <div class="total-row advance">
                    <span>Avansna uplata:</span>
                    <span>- {{ $formatMoney($invoice['advancePaidAmount'] ?? 0) }}</span>
                  </div>
                @endif
                <div class="total-divider"></div>
                <div class="total-row grand">
                  <span>Ukupno za platiti:</span>
                  <span>{{ $formatMoney($totals['total'] ?? 0) }}</span>
                </div>

                <div class="invoice-approval" aria-hidden="true">
                  <div class="invoice-signature-line"></div>
                  <img src="{{ asset('images/ai-payment/signature-clean.png') }}" alt="" class="invoice-signature">
                  <img src="{{ asset('images/ai-payment/stamp-clean.png') }}" alt="" class="invoice-stamp">
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    const invoice = @json($invoice);

    function sanitizeFilenamePart(value) {
      return String(value || '')
        .replace(/"/g, '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-zA-Z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 80);
    }

    function getPrintFilename() {
      const documentType =
        invoice.invoiceType === 'faktura'
          ? 'Racun'
          : invoice.invoiceType === 'avansna'
            ? 'Avansna-faktura'
            : 'Predracun';
      const fiscalNumber = sanitizeFilenamePart(invoice.fiscalNumber || invoice.number || 'bez-broja');
      const customerName = sanitizeFilenamePart((invoice.buyer && invoice.buyer.name) || 'kupac');

      return `${documentType}_${fiscalNumber}_qla-dev_${customerName}`;
    }

    function handlePrint() {
      const previousTitle = document.title;
      document.title = getPrintFilename();

      const restoreTitle = () => {
        document.title = previousTitle;
        window.removeEventListener('afterprint', restoreTitle);
      };

      window.addEventListener('afterprint', restoreTitle);
      window.print();
    }

    document.getElementById('invoice-print-button')?.addEventListener('click', handlePrint);
    document.getElementById('invoice-back-button')?.addEventListener('click', function () {
      if (window.history.length > 1) {
        window.history.back();
        return;
      }

      window.close();
    });
  </script>
</body>
</html>
