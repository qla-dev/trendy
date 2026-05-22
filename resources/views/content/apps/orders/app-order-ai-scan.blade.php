@extends('layouts/contentLayoutMaster')

@section('title', __('locale.Skeniraj narudzbu sa AI'))

@section('page-style')
  <style>
    .order-ai-shell {
      --order-ai-ink: #16344d;
      --order-ai-subtle: #607385;
      --order-ai-accent: #0e7a6b;
      --order-ai-border: rgba(18, 52, 77, 0.12);
      --order-ai-brand-a: #18cbb7;
      --order-ai-brand-b: #347cf7;
      --order-ai-brand-c: #31c46d;
      --order-ai-card-surface: #ffffff;
      --order-ai-card-soft: #fbfcfd;
      --order-ai-card-muted: #f8fafc;
      --order-ai-card-strong: #eef3f7;
      --order-ai-chip-bg: rgba(22, 52, 77, 0.08);
      --order-ai-panel-shadow: 0 16px 32px rgba(16, 31, 48, 0.06);
    }

    .order-ai-hero {
      position: relative;
      overflow: hidden;
      border: 0;
      border-radius: 1.25rem;
      background:
        radial-gradient(circle at top right, rgba(255, 207, 107, 0.3), transparent 32%),
        linear-gradient(135deg, #fdf7eb 0%, #fffefe 42%, #eef8f7 100%);
      box-shadow: 0 20px 42px rgba(16, 31, 48, 0.08);
    }

    .order-ai-hero::after {
      content: "";
      position: absolute;
      inset: auto -6rem -5rem auto;
      width: 14rem;
      height: 14rem;
      border-radius: 999px;
      background: rgba(14, 122, 107, 0.08);
      filter: blur(12px);
    }

    .order-ai-hero-grid {
      display: grid;
      grid-template-columns: minmax(0, 1.12fr) minmax(320px, 0.88fr);
      gap: 1rem;
      align-items: stretch;
    }

    .order-ai-hero-story {
      position: relative;
      overflow: hidden;
      min-height: 194px;
      padding: 1.15rem 1.35rem;
      border-radius: 1.15rem;
      border: 1px solid rgba(18, 52, 77, 0.08);
      background:
        linear-gradient(135deg, rgba(255, 255, 255, 0.96), rgba(241, 250, 247, 0.88)),
        linear-gradient(135deg, rgba(24, 203, 183, 0.08), rgba(52, 124, 247, 0.05));
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7);
    }

    .order-ai-hero-story::before {
      content: "";
      position: absolute;
      inset: 0;
      background-image:
        url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 520 240'%3E%3Cdefs%3E%3ClinearGradient id='g' x1='0%25' y1='0%25' x2='100%25' y2='100%25'%3E%3Cstop offset='0%25' stop-color='%2318cbb7'/%3E%3Cstop offset='55%25' stop-color='%23347cf7'/%3E%3Cstop offset='100%25' stop-color='%2331c46d'/%3E%3C/linearGradient%3E%3C/defs%3E%3Cg fill='none' stroke='url(%23g)' stroke-width='3.2' stroke-linecap='round' stroke-linejoin='round' opacity='.95'%3E%3Cpath d='M134 28h84a36 36 0 0 1 36 36v44a36 36 0 0 1-36 36h-84a36 36 0 0 1-36-36V64a36 36 0 0 1 36-36Z'/%3E%3Cpath d='M176 8v20'/%3E%3Cpath d='M132 144l-16 32'/%3E%3Cpath d='M220 144l16 32'/%3E%3Cpath d='M110 176h132'/%3E%3Ccircle cx='148' cy='84' r='14'/%3E%3Ccircle cx='204' cy='84' r='14'/%3E%3Cpath d='M148 116c14 10 42 10 56 0'/%3E%3Cpath d='M98 76H66'/%3E%3Cpath d='M286 76h-32'/%3E%3Cpath d='M98 106H72'/%3E%3Cpath d='M280 106h-26'/%3E%3Cpath d='M112 48L84 28'/%3E%3Cpath d='M240 48l28-20'/%3E%3Cpath d='M254 62h88'/%3E%3Cpath d='M254 102h72'/%3E%3Cpath d='M326 102l20-18'/%3E%3Cpath d='M58 146l40-22'/%3E%3Cpath d='M60 146H24'/%3E%3Ccircle cx='354' cy='62' r='10'/%3E%3Ccircle cx='356' cy='102' r='10'/%3E%3Ccircle cx='8' cy='146' r='8'/%3E%3Cpath d='M300 160h88'/%3E%3Cpath d='M388 160l26 18'/%3E%3Ccircle cx='428' cy='188' r='11'/%3E%3Cpath d='M236 182h64'/%3E%3Cpath d='M56 188h88'/%3E%3Cpath d='M144 188l22-18'/%3E%3Ccircle cx='46' cy='188' r='10'/%3E%3C/g%3E%3Cg fill='url(%23g)' opacity='.18'%3E%3Ccircle cx='148' cy='84' r='6'/%3E%3Ccircle cx='204' cy='84' r='6'/%3E%3Ccircle cx='354' cy='62' r='5'/%3E%3Ccircle cx='356' cy='102' r='5'/%3E%3C/g%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: left 1.2rem center;
      background-size: 22rem auto;
      opacity: 0.16;
      pointer-events: none;
    }

    .order-ai-hero-story::after {
      content: "";
      position: absolute;
      inset: auto auto -2rem -2rem;
      width: 14rem;
      height: 14rem;
      border-radius: 999px;
      background: radial-gradient(circle, rgba(24, 203, 183, 0.2), rgba(52, 124, 247, 0.04) 68%, transparent 72%);
      filter: blur(8px);
      pointer-events: none;
    }

    .order-ai-hero-story-inner {
      position: relative;
      z-index: 1;
      display: flex;
      align-items: center;
      min-height: 100%;
    }

    .order-ai-hero-copy {
      max-width: 28rem;
      padding-left: 0.15rem;
    }

    .order-ai-hero-aside {
      display: grid;
      gap: 0.75rem;
      grid-template-rows: repeat(3, minmax(0, 1fr));
      min-height: 100%;
    }

    .order-ai-stat {
      position: relative;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      justify-content: center;
      padding: 0.85rem 1rem;
      border-radius: 1rem;
      border: 1px solid rgba(18, 52, 77, 0.08);
      background: rgba(255, 255, 255, 0.8);
      box-shadow: 0 14px 30px rgba(16, 31, 48, 0.06);
    }

    .order-ai-stat::after {
      content: "";
      position: absolute;
      inset: 0 auto 0 0;
      width: 0.35rem;
      background: linear-gradient(180deg, var(--order-ai-brand-a), var(--order-ai-brand-b));
      opacity: 0.7;
    }

    .order-ai-stat--model {
      background: linear-gradient(135deg, rgba(24, 203, 183, 0.14), rgba(52, 124, 247, 0.08));
      border-color: rgba(24, 203, 183, 0.18);
    }

    .order-ai-stat-label {
      display: block;
      margin-bottom: 0.2rem;
      color: var(--order-ai-subtle);
      font-size: 0.72rem;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }

    .order-ai-stat-value {
      display: block;
      color: var(--order-ai-ink);
      font-size: 0.96rem;
      font-weight: 700;
      line-height: 1.25;
    }

    .order-ai-chip {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      padding: 0.55rem 0.85rem;
      border-radius: 999px;
      background: var(--order-ai-chip-bg);
      color: var(--order-ai-ink);
      font-size: 0.9rem;
      font-weight: 700;
      letter-spacing: 0.04em;
      text-transform: uppercase;
    }

    .order-ai-dropzone {
      position: relative;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      min-height: 330px;
      padding: 2rem;
      border: 2px dashed rgba(14, 122, 107, 0.32);
      border-radius: 1.25rem;
      background:
        linear-gradient(180deg, rgba(14, 122, 107, 0.06), rgba(14, 122, 107, 0.02)),
        var(--order-ai-card-surface);
      text-align: center;
      transition: border-color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
      cursor: pointer;
    }

    .order-ai-dropzone.is-dragover {
      border-color: var(--order-ai-accent);
      transform: translateY(-2px);
      box-shadow: 0 18px 32px rgba(14, 122, 107, 0.12);
    }

    .order-ai-dropzone-icon {
      width: 5rem;
      height: 5rem;
      border-radius: 1.4rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 1.15rem;
      background: linear-gradient(135deg, rgba(14, 122, 107, 0.16), rgba(255, 207, 107, 0.18));
      color: var(--order-ai-accent);
    }

    .order-ai-dropzone-icon svg {
      width: 2.25rem;
      height: 2.25rem;
    }

    .order-ai-subtle {
      color: var(--order-ai-subtle);
    }

    .order-ai-title,
    .order-ai-shell h3,
    .order-ai-shell h4,
    .order-ai-shell h5 {
      color: var(--order-ai-ink);
    }

    .order-ai-progress-card,
    .order-ai-result-card {
      background: var(--order-ai-card-surface);
      border-radius: 1.1rem;
      border: 1px solid var(--order-ai-border);
      box-shadow: var(--order-ai-panel-shadow);
    }

    .order-ai-progress-head {
      display: flex;
      align-items: flex-start;
      gap: 0.9rem;
      flex-wrap: nowrap;
    }

    .order-ai-progress-copy {
      flex: 1 1 auto;
      min-width: 0;
    }

    .order-ai-progress-status {
      flex: 0 0 auto;
      align-self: center;
    }

    .order-ai-progress-meta {
      flex: 0 0 auto;
      min-width: 5rem;
      text-align: right;
    }

    .order-ai-activity {
      display: inline-flex;
      align-items: center;
      gap: 0.55rem;
      padding: 0.55rem 0.8rem;
      border-radius: 999px;
      background: rgba(14, 122, 107, 0.1);
      color: var(--order-ai-accent);
      font-size: 0.85rem;
      font-weight: 700;
    }

    .order-ai-activity .spinner-border {
      width: 0.95rem;
      height: 0.95rem;
      border-width: 0.14em;
    }

    .order-ai-progress-track {
      height: 0.9rem;
      border-radius: 999px;
      background: var(--order-ai-card-strong);
      overflow: hidden;
    }

    .order-ai-progress-bar {
      height: 100%;
      width: 0;
      border-radius: 999px;
      background: linear-gradient(90deg, #0e7a6b 0%, #1ca28f 100%);
      transition: width 0.25s ease;
    }

    .order-ai-stage-list {
      display: grid;
      gap: 0.75rem;
    }

    .order-ai-stage {
      position: relative;
      overflow: hidden;
      padding: 0.85rem 1rem;
      border-radius: 1rem;
      background: var(--order-ai-card-soft);
      border: 1px solid rgba(22, 52, 77, 0.08);
    }

    .order-ai-stage-fill {
      position: absolute;
      top: 0;
      left: 0;
      bottom: 0;
      width: 0;
      background: linear-gradient(90deg, rgba(49, 196, 109, 0.18), rgba(14, 122, 107, 0.08));
      transition: width 0.35s ease;
      pointer-events: none;
    }

    .order-ai-stage-content {
      position: relative;
      z-index: 1;
      display: flex;
      align-items: flex-start;
      gap: 0.85rem;
      width: 100%;
    }

    .order-ai-stage-main {
      flex: 1 1 auto;
      min-width: 0;
    }

    .order-ai-stage-side {
      flex: 0 0 auto;
      align-self: center;
      margin-left: auto;
    }

    .order-ai-stage-bullet {
      width: 0.8rem;
      height: 0.8rem;
      border-radius: 999px;
      background: #c5d0da;
      flex: 0 0 auto;
    }

    .order-ai-stage.is-active .order-ai-stage-bullet,
    .order-ai-stage.is-done .order-ai-stage-bullet {
      background: var(--order-ai-accent);
      box-shadow: 0 0 0 0.25rem rgba(14, 122, 107, 0.12);
    }

    .order-ai-stage.is-active {
      background: rgba(14, 122, 107, 0.08);
      border-color: rgba(14, 122, 107, 0.2);
    }

    .order-ai-stage.is-done {
      background: rgba(22, 163, 74, 0.08);
      border-color: rgba(22, 163, 74, 0.18);
    }

    .order-ai-stage.is-active[data-stage="extract"] .order-ai-stage-fill {
      background: linear-gradient(90deg, rgba(49, 196, 109, 0.24), rgba(24, 203, 183, 0.18), rgba(52, 124, 247, 0.08));
      background-size: 200% 100%;
      animation: order-ai-flow 2s linear infinite;
    }

    .order-ai-stage-note-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      margin-top: 0.45rem;
      padding: 0.35rem 0.55rem;
      border-radius: 999px;
      background: rgba(49, 196, 109, 0.12);
      color: #12814a;
      font-size: 0.78rem;
      font-weight: 700;
    }

    .order-ai-transfer-cta {
      min-width: 170px;
      border-radius: 0.9rem;
      font-weight: 700;
      box-shadow: 0 14px 28px rgba(22, 163, 74, 0.22);
    }

    .order-ai-transfer-cta.is-ready {
      animation: order-ai-pulse 2.2s ease-in-out infinite;
    }

    .order-ai-transfer-cta.is-busy,
    .order-ai-transfer-cta.is-complete {
      opacity: 1;
      color: #ffffff;
    }

    .order-ai-transfer-cta.is-busy {
      background: linear-gradient(180deg, #16a34a 0%, #12813d 100%);
      border-color: #12813d;
    }

    .order-ai-transfer-cta.is-complete {
      background: linear-gradient(180deg, #0e7a6b 0%, #0b6156 100%);
      border-color: #0b6156;
    }

    .order-ai-transfer-cta:disabled:not(.is-busy):not(.is-complete) {
      background: linear-gradient(180deg, #62707f 0%, #495464 100%);
      border-color: #495464;
      color: #eef4f7;
      opacity: 1;
      box-shadow: none;
      cursor: not-allowed !important;
    }

    .order-ai-transfer-cta:disabled,
    .order-ai-transfer-cta:disabled:hover,
    .order-ai-transfer-cta:disabled:focus {
      box-shadow: none;
      cursor: not-allowed !important;
      transform: none;
    }

    .order-ai-transfer-followup {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 0.85rem;
      margin-top: 1rem;
    }

    .order-ai-transfer-error-copy {
      display: grid;
      gap: 0.9rem;
    }

    .order-ai-transfer-error-copy code {
      white-space: pre-wrap;
      word-break: break-word;
      display: block;
      padding: 0.85rem 0.95rem;
      border-radius: 0.85rem;
      background: var(--order-ai-card-muted);
      color: #284257;
      font-size: 0.84rem;
    }

    .order-ai-facts {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
      gap: 0.85rem;
    }

    .order-ai-fact {
      padding: 1rem;
      border-radius: 1rem;
      background: linear-gradient(180deg, var(--order-ai-card-surface) 0%, var(--order-ai-card-muted) 100%);
      border: 1px solid rgba(22, 52, 77, 0.08);
    }

    .order-ai-lines-table td,
    .order-ai-lines-table th {
      white-space: nowrap;
      vertical-align: middle;
    }

    .order-ai-lines-table td.order-ai-wrap,
    .order-ai-lines-table th.order-ai-wrap {
      white-space: normal;
    }

    .order-ai-line-total-trigger {
      width: 100%;
      border: 1px solid rgba(22, 52, 77, 0.12);
      border-radius: 0.9rem;
      background: var(--order-ai-card-muted);
      padding: 0.7rem 0.8rem;
      text-align: left;
      transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
    }

    .order-ai-line-total-trigger:hover,
    .order-ai-line-total-trigger:focus {
      transform: translateY(-1px);
      box-shadow: 0 10px 20px rgba(16, 31, 48, 0.08);
    }

    .order-ai-line-total-trigger.is-match {
      border-color: rgba(22, 163, 74, 0.28);
      background: rgba(22, 163, 74, 0.08);
      color: #17683b;
    }

    .order-ai-line-total-trigger.is-mismatch {
      border-color: rgba(220, 38, 38, 0.25);
      background: rgba(220, 38, 38, 0.08);
      color: #8b1e1e;
    }

    .order-ai-line-total-trigger.is-readonly {
      cursor: default;
    }

    .order-ai-line-total-trigger.is-readonly:hover,
    .order-ai-line-total-trigger.is-readonly:focus {
      transform: none;
      box-shadow: none;
    }

    .order-ai-line-total-meta {
      display: grid;
      gap: 0.15rem;
    }

    .order-ai-line-total-computed {
      font-weight: 700;
    }

    .order-ai-line-total-source {
      font-size: 0.8rem;
      opacity: 0.84;
    }

    .order-ai-line-total-diff {
      font-size: 0.76rem;
      font-weight: 700;
      letter-spacing: 0.01em;
    }

    .order-ai-total-check-copy {
      display: grid;
      gap: 1rem;
    }

    .order-ai-total-check-banner {
      border-radius: 1rem;
      padding: 0.95rem 1rem;
      border: 1px solid rgba(22, 52, 77, 0.12);
      background: var(--order-ai-card-muted);
    }

    .order-ai-total-check-banner.is-match {
      border-color: rgba(22, 163, 74, 0.24);
      background: rgba(22, 163, 74, 0.08);
      color: #17683b;
    }

    .order-ai-total-check-banner.is-mismatch {
      border-color: rgba(220, 38, 38, 0.22);
      background: rgba(220, 38, 38, 0.08);
      color: #8b1e1e;
    }

    .order-ai-total-check-grid {
      display: grid;
      gap: 0.75rem;
    }

    .order-ai-total-check-row {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
      padding-bottom: 0.75rem;
      border-bottom: 1px solid rgba(22, 52, 77, 0.08);
    }

    .order-ai-total-check-row:last-child {
      padding-bottom: 0;
      border-bottom: 0;
    }

    .order-ai-total-check-label {
      color: var(--order-ai-subtle);
      font-size: 0.84rem;
    }

    .order-ai-total-check-value {
      font-weight: 700;
      color: var(--order-ai-ink);
    }

    .order-ai-total-check-edit {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 0.75rem;
    }

    .order-ai-total-check-edit input {
      min-width: 9rem;
    }

    .order-ai-alert {
      border-radius: 1rem;
      border: 0;
    }

    .order-ai-saved-preview {
      padding: 1rem 1.1rem;
      border-radius: 1rem;
      border: 1px solid rgba(22, 163, 74, 0.16);
      background: linear-gradient(135deg, rgba(226, 255, 239, 0.75), rgba(255, 255, 255, 0.95));
    }

    .order-ai-saved-preview-header {
      display: flex;
      flex-wrap: wrap;
      align-items: flex-start;
      justify-content: space-between;
      gap: 0.85rem;
      margin-bottom: 0.95rem;
    }

    .order-ai-saved-grid,
    .order-ai-modal-summary {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
      gap: 0.75rem;
    }

    .order-ai-saved-item,
    .order-ai-modal-summary-card {
      padding: 0.85rem 0.9rem;
      border-radius: 0.9rem;
      border: 1px solid rgba(18, 52, 77, 0.08);
      background: rgba(255, 255, 255, 0.92);
    }

    .order-ai-modal-summary-card {
      background: linear-gradient(180deg, var(--order-ai-card-surface) 0%, var(--order-ai-card-muted) 100%);
    }

    .order-ai-modal-table-wrap {
      border-radius: 1rem;
      border: 1px solid rgba(18, 52, 77, 0.08);
      background: var(--order-ai-card-surface);
      overflow: auto;
      max-height: 360px;
    }

    .order-ai-positions-body {
      min-height: 320px;
    }

    .order-ai-positions-loading {
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 240px;
      flex-direction: column;
      gap: 0.9rem;
      color: var(--order-ai-subtle);
      text-align: center;
    }

    .order-linkage-modal-summary-grid {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 0.9rem;
      margin-bottom: 1rem;
    }

    .order-linkage-modal-summary-card {
      padding: 0.95rem 1rem;
      border-radius: 0.85rem;
      border: 1px solid rgba(71, 95, 123, 0.12);
      background: rgba(71, 95, 123, 0.04);
    }

    .order-linkage-modal-summary-label {
      display: block;
      font-size: 0.78rem;
      text-transform: uppercase;
      letter-spacing: 0.03em;
      color: var(--order-ai-subtle);
      margin-bottom: 0.35rem;
    }

    .order-linkage-modal-summary-value {
      display: block;
      font-size: 1rem;
      font-weight: 600;
      line-height: 1.35;
    }

    .order-linkage-modal-table-wrap {
      border: 1px solid rgba(71, 95, 123, 0.12);
      border-radius: 0.85rem;
      overflow: hidden;
    }

    .order-linkage-modal-table {
      margin-bottom: 0;
    }

    .order-linkage-modal-table thead th {
      white-space: nowrap;
    }

    .order-linkage-modal-transfer-cell {
      position: sticky !important;
      right: 0 !important;
      z-index: 20 !important;
      width: 1% !important;
      white-space: nowrap;
      text-align: center;
      background: #ffffff !important;
      background-color: #ffffff !important;
      background-clip: border-box !important;
      opacity: 1 !important;
      isolation: isolate !important;
      border-left: 1px solid #ebe9f1 !important;
      box-shadow: none !important;
    }

    .order-linkage-modal-table thead .order-linkage-modal-transfer-cell {
      z-index: 21 !important;
      background: #f8f8fa !important;
      background-color: #f8f8fa !important;
    }

    .order-linkage-modal-table.table tbody tr:hover > .order-linkage-modal-transfer-cell {
      background: #f8f8fc !important;
      background-color: #f8f8fc !important;
    }

    .order-linkage-modal-empty {
      padding: 1rem;
      color: #6e6b7b;
      text-align: center;
    }

    #order-ai-positions-modal .order-linkage-modal-transfer-cell {
      display: none !important;
    }

    .order-ai-hidden {
      display: none !important;
    }

    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-shell {
      --order-ai-ink: #16344d;
      --order-ai-subtle: #607385;
      --order-ai-border: rgba(18, 52, 77, 0.12);
      --order-ai-card-surface: #ffffff;
      --order-ai-card-soft: #fbfcfd;
      --order-ai-card-muted: #f8fafc;
      --order-ai-card-strong: #eef3f7;
      --order-ai-chip-bg: rgba(22, 52, 77, 0.08);
      --order-ai-panel-shadow: 0 16px 32px rgba(16, 31, 48, 0.06);
    }

    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-shell .card:not(.order-ai-hero):not(.order-ai-progress-card):not(.order-ai-result-card) {
      background: #ffffff;
      border-color: rgba(18, 52, 77, 0.06);
      box-shadow: 0 16px 32px rgba(16, 31, 48, 0.06);
    }

    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-hero {
      background:
        radial-gradient(circle at top right, rgba(255, 207, 107, 0.3), transparent 32%),
        linear-gradient(135deg, #fdf7eb 0%, #fffefe 42%, #eef8f7 100%);
      box-shadow: 0 20px 42px rgba(16, 31, 48, 0.08);
    }

    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-hero::after {
      background: rgba(14, 122, 107, 0.08);
    }

    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-hero-story {
      border-color: rgba(18, 52, 77, 0.08);
      background:
        linear-gradient(135deg, rgba(255, 255, 255, 0.96), rgba(241, 250, 247, 0.88)),
        linear-gradient(135deg, rgba(24, 203, 183, 0.08), rgba(52, 124, 247, 0.05));
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7);
    }

    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-hero-story::before {
      opacity: 0.16;
      filter: none;
    }

    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-hero-story::after {
      background: radial-gradient(circle, rgba(24, 203, 183, 0.2), rgba(52, 124, 247, 0.04) 68%, transparent 72%);
    }

    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-stat {
      background: rgba(255, 255, 255, 0.8);
      border-color: rgba(18, 52, 77, 0.08);
      box-shadow: 0 14px 30px rgba(16, 31, 48, 0.06);
    }

    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-stat--model {
      background: linear-gradient(135deg, rgba(24, 203, 183, 0.14), rgba(52, 124, 247, 0.08));
      border-color: rgba(24, 203, 183, 0.18);
    }

    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-dropzone {
      border-color: rgba(14, 122, 107, 0.32);
      background:
        linear-gradient(180deg, rgba(14, 122, 107, 0.06), rgba(14, 122, 107, 0.02)),
        var(--order-ai-card-surface);
      box-shadow: none;
      color: var(--order-ai-ink);
    }

    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-dropzone h3 {
      color: var(--order-ai-ink);
    }

    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-dropzone.is-dragover {
      box-shadow: 0 18px 32px rgba(14, 122, 107, 0.12);
    }

    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-dropzone-icon {
      background: linear-gradient(135deg, rgba(14, 122, 107, 0.16), rgba(255, 207, 107, 0.18));
      color: var(--order-ai-accent);
    }

    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-progress-card,
    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-result-card {
      background: var(--order-ai-card-surface);
    }

    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-progress-track {
      background: var(--order-ai-card-strong);
    }

    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-activity {
      background: rgba(14, 122, 107, 0.1);
      color: var(--order-ai-accent);
    }

    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-stage {
      background: var(--order-ai-card-soft);
      border-color: rgba(22, 52, 77, 0.08);
    }

    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-stage strong {
      color: var(--order-ai-ink);
    }

    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-stage.is-active {
      background: rgba(14, 122, 107, 0.08);
      border-color: rgba(14, 122, 107, 0.2);
    }

    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-stage.is-done {
      background: rgba(22, 163, 74, 0.08);
      border-color: rgba(22, 163, 74, 0.18);
    }

    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-stage-bullet {
      background: #c5d0da;
    }

    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-transfer-cta:disabled:not(.is-busy):not(.is-complete) {
      background: linear-gradient(180deg, #778492 0%, #5d6977 100%);
      border-color: #5d6977;
      color: #f4f7fa;
    }

    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-transfer-error-copy code,
    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-fact,
    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-line-total-trigger,
    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-total-check-banner,
    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-saved-item,
    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-modal-summary-card,
    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-modal-table-wrap,
    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-linkage-modal-summary-card,
    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-linkage-modal-table-wrap {
      background: var(--order-ai-card-surface);
      border-color: rgba(18, 52, 77, 0.08);
      color: var(--order-ai-ink);
    }

    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-transfer-error-copy code {
      background: var(--order-ai-card-muted);
      color: #284257;
    }

    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-saved-preview {
      background: linear-gradient(135deg, rgba(226, 255, 239, 0.75), rgba(255, 255, 255, 0.95));
      border-color: rgba(22, 163, 74, 0.16);
    }

    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-line-total-trigger.is-match,
    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-total-check-banner.is-match {
      border-color: rgba(22, 163, 74, 0.28);
      background: rgba(22, 163, 74, 0.08);
      color: #17683b;
    }

    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-line-total-trigger.is-mismatch,
    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-total-check-banner.is-mismatch {
      border-color: rgba(220, 38, 38, 0.25);
      background: rgba(220, 38, 38, 0.08);
      color: #8b1e1e;
    }

    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-shell .table,
    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-shell .table td,
    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-shell .table th {
      color: var(--order-ai-ink);
      border-color: rgba(18, 52, 77, 0.08);
    }

    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-shell .table thead th {
      color: #607385;
      background: rgba(238, 243, 247, 0.8);
    }

    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-shell .text-muted {
      color: var(--order-ai-subtle) !important;
    }

    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) #order-ai-result-status {
      background: rgba(115, 103, 240, 0.12) !important;
      color: #7367f0 !important;
      border: 1px solid rgba(115, 103, 240, 0.18);
    }

    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-linkage-modal-transfer-cell {
      background: #ffffff !important;
      background-color: #ffffff !important;
      border-left-color: #ebe9f1 !important;
    }

    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-linkage-modal-table thead .order-linkage-modal-transfer-cell {
      background: #f8f8fa !important;
      background-color: #f8f8fa !important;
    }

    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-linkage-modal-table.table tbody tr:hover > .order-linkage-modal-transfer-cell {
      background: #f8f8fc !important;
      background-color: #f8f8fc !important;
    }

    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-shell .modal-content {
      background: #ffffff;
    }

    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-shell .modal-header,
    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-shell .modal-footer {
      border-color: rgba(18, 52, 77, 0.08);
    }

    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-shell .btn-close {
      filter: none;
    }

    html.dark-layout .order-ai-shell,
    html.semi-dark-layout .order-ai-shell {
      --order-ai-ink: #e7efff;
      --order-ai-subtle: #99abca;
      --order-ai-border: rgba(126, 153, 194, 0.18);
      --order-ai-card-surface: #222d45;
      --order-ai-card-soft: #1c2640;
      --order-ai-card-muted: #27334e;
      --order-ai-card-strong: #34425f;
      --order-ai-chip-bg: rgba(92, 114, 151, 0.28);
      --order-ai-panel-shadow: 0 18px 36px rgba(2, 6, 23, 0.28);
    }

    html.dark-layout .order-ai-shell .card:not(.order-ai-hero):not(.order-ai-progress-card):not(.order-ai-result-card),
    html.semi-dark-layout .order-ai-shell .card:not(.order-ai-hero):not(.order-ai-progress-card):not(.order-ai-result-card) {
      background: linear-gradient(180deg, rgba(34, 45, 69, 0.98), rgba(29, 39, 63, 0.98));
      border-color: rgba(126, 153, 194, 0.14);
      box-shadow: 0 18px 36px rgba(2, 6, 23, 0.24);
    }

    html.dark-layout .order-ai-hero,
    html.semi-dark-layout .order-ai-hero {
      background:
        radial-gradient(circle at top right, rgba(255, 207, 107, 0.1), transparent 30%),
        linear-gradient(135deg, #202a40 0%, #182237 46%, #132033 100%);
      box-shadow: 0 22px 44px rgba(2, 6, 23, 0.3);
    }

    html.dark-layout .order-ai-hero::after,
    html.semi-dark-layout .order-ai-hero::after {
      background: rgba(24, 203, 183, 0.14);
    }

    html.dark-layout .order-ai-hero-story,
    html.semi-dark-layout .order-ai-hero-story {
      border-color: rgba(126, 153, 194, 0.16);
      background:
        linear-gradient(135deg, rgba(31, 42, 68, 0.96), rgba(22, 32, 52, 0.9)),
        linear-gradient(135deg, rgba(24, 203, 183, 0.08), rgba(52, 124, 247, 0.08));
      box-shadow: inset 0 1px 0 rgba(226, 232, 240, 0.05);
    }

    html.dark-layout .order-ai-hero-story::before,
    html.semi-dark-layout .order-ai-hero-story::before {
      opacity: 0.14;
      filter: saturate(1.12);
    }

    html.dark-layout .order-ai-hero-story::after,
    html.semi-dark-layout .order-ai-hero-story::after {
      background: radial-gradient(circle, rgba(24, 203, 183, 0.18), rgba(52, 124, 247, 0.07) 68%, transparent 72%);
    }

    html.dark-layout .order-ai-stat,
    html.semi-dark-layout .order-ai-stat {
      background: linear-gradient(135deg, rgba(34, 45, 69, 0.95), rgba(25, 35, 55, 0.94));
      border-color: rgba(126, 153, 194, 0.18);
      box-shadow: 0 16px 34px rgba(2, 6, 23, 0.24);
    }

    html.dark-layout .order-ai-stat--model,
    html.semi-dark-layout .order-ai-stat--model {
      background: linear-gradient(135deg, rgba(24, 203, 183, 0.16), rgba(52, 124, 247, 0.12));
      border-color: rgba(24, 203, 183, 0.22);
    }

    html.dark-layout .order-ai-dropzone,
    html.semi-dark-layout .order-ai-dropzone {
      border-color: rgba(94, 176, 171, 0.46);
      background:
        linear-gradient(180deg, rgba(24, 203, 183, 0.08), rgba(14, 122, 107, 0.03)),
        linear-gradient(135deg, rgba(34, 45, 69, 0.98), rgba(27, 37, 61, 0.96));
      box-shadow: inset 0 1px 0 rgba(226, 232, 240, 0.04);
      color: var(--order-ai-ink);
    }

    html.dark-layout .order-ai-dropzone h3,
    html.semi-dark-layout .order-ai-dropzone h3 {
      color: var(--order-ai-ink);
    }

    html.dark-layout .order-ai-dropzone.is-dragover,
    html.semi-dark-layout .order-ai-dropzone.is-dragover {
      box-shadow:
        0 18px 32px rgba(2, 6, 23, 0.28),
        0 0 0 1px rgba(24, 203, 183, 0.16) inset;
    }

    html.dark-layout .order-ai-dropzone-icon,
    html.semi-dark-layout .order-ai-dropzone-icon {
      background: linear-gradient(135deg, rgba(24, 203, 183, 0.22), rgba(52, 124, 247, 0.16));
      color: #86f3df;
    }

    html.dark-layout .order-ai-progress-card,
    html.dark-layout .order-ai-result-card,
    html.semi-dark-layout .order-ai-progress-card,
    html.semi-dark-layout .order-ai-result-card {
      background: linear-gradient(180deg, rgba(34, 45, 69, 0.98), rgba(29, 39, 63, 0.98));
    }

    html.dark-layout .order-ai-progress-track,
    html.semi-dark-layout .order-ai-progress-track {
      background: rgba(126, 153, 194, 0.18);
    }

    html.dark-layout .order-ai-activity,
    html.semi-dark-layout .order-ai-activity {
      background: rgba(24, 203, 183, 0.12);
      color: #8ef0dc;
    }

    html.dark-layout .order-ai-stage,
    html.semi-dark-layout .order-ai-stage {
      background: rgba(25, 36, 58, 0.94);
      border-color: rgba(126, 153, 194, 0.12);
    }

    html.dark-layout .order-ai-stage strong,
    html.semi-dark-layout .order-ai-stage strong {
      color: var(--order-ai-ink);
    }

    html.dark-layout .order-ai-stage.is-active,
    html.semi-dark-layout .order-ai-stage.is-active {
      background: linear-gradient(135deg, rgba(14, 122, 107, 0.22), rgba(27, 37, 61, 0.96));
      border-color: rgba(24, 203, 183, 0.24);
    }

    html.dark-layout .order-ai-stage.is-done,
    html.semi-dark-layout .order-ai-stage.is-done {
      background: linear-gradient(135deg, rgba(22, 163, 74, 0.16), rgba(27, 37, 61, 0.96));
      border-color: rgba(49, 196, 109, 0.24);
    }

    html.dark-layout .order-ai-stage-bullet,
    html.semi-dark-layout .order-ai-stage-bullet {
      background: #62708a;
    }

    html.dark-layout .order-ai-transfer-cta:disabled:not(.is-busy):not(.is-complete),
    html.semi-dark-layout .order-ai-transfer-cta:disabled:not(.is-busy):not(.is-complete) {
      background: linear-gradient(180deg, #556277 0%, #424d61 100%);
      border-color: #4a576d;
      color: #dce6f5;
    }

    html.dark-layout .order-ai-transfer-error-copy code,
    html.dark-layout .order-ai-fact,
    html.dark-layout .order-ai-line-total-trigger,
    html.dark-layout .order-ai-total-check-banner,
    html.dark-layout .order-ai-saved-item,
    html.dark-layout .order-ai-modal-summary-card,
    html.dark-layout .order-ai-modal-table-wrap,
    html.dark-layout .order-linkage-modal-summary-card,
    html.dark-layout .order-linkage-modal-table-wrap,
    html.semi-dark-layout .order-ai-transfer-error-copy code,
    html.semi-dark-layout .order-ai-fact,
    html.semi-dark-layout .order-ai-line-total-trigger,
    html.semi-dark-layout .order-ai-total-check-banner,
    html.semi-dark-layout .order-ai-saved-item,
    html.semi-dark-layout .order-ai-modal-summary-card,
    html.semi-dark-layout .order-ai-modal-table-wrap,
    html.semi-dark-layout .order-linkage-modal-summary-card,
    html.semi-dark-layout .order-linkage-modal-table-wrap {
      background: rgba(27, 37, 61, 0.94);
      border-color: rgba(126, 153, 194, 0.16);
      color: var(--order-ai-ink);
    }

    html.dark-layout .order-ai-saved-preview,
    html.semi-dark-layout .order-ai-saved-preview {
      background: linear-gradient(135deg, rgba(14, 122, 107, 0.18), rgba(27, 37, 61, 0.98));
      border-color: rgba(24, 203, 183, 0.22);
    }

    html.dark-layout .order-ai-line-total-trigger.is-match,
    html.dark-layout .order-ai-total-check-banner.is-match,
    html.semi-dark-layout .order-ai-line-total-trigger.is-match,
    html.semi-dark-layout .order-ai-total-check-banner.is-match {
      border-color: rgba(49, 196, 109, 0.26);
      background: rgba(22, 163, 74, 0.16);
      color: #9de8b8;
    }

    html.dark-layout .order-ai-line-total-trigger.is-mismatch,
    html.dark-layout .order-ai-total-check-banner.is-mismatch,
    html.semi-dark-layout .order-ai-line-total-trigger.is-mismatch,
    html.semi-dark-layout .order-ai-total-check-banner.is-mismatch {
      border-color: rgba(248, 113, 113, 0.28);
      background: rgba(220, 38, 38, 0.16);
      color: #ffb7b7;
    }

    html.dark-layout .order-ai-shell .table,
    html.dark-layout .order-ai-shell .table td,
    html.dark-layout .order-ai-shell .table th,
    html.semi-dark-layout .order-ai-shell .table,
    html.semi-dark-layout .order-ai-shell .table td,
    html.semi-dark-layout .order-ai-shell .table th {
      color: var(--order-ai-ink);
      border-color: rgba(126, 153, 194, 0.12);
    }

    html.dark-layout .order-ai-shell .table thead th,
    html.semi-dark-layout .order-ai-shell .table thead th {
      color: #adc0dc;
      background: rgba(51, 65, 94, 0.36);
    }

    html.dark-layout .order-ai-shell .text-muted,
    html.semi-dark-layout .order-ai-shell .text-muted {
      color: var(--order-ai-subtle) !important;
    }

    html.dark-layout #order-ai-result-status,
    html.semi-dark-layout #order-ai-result-status {
      background: rgba(24, 203, 183, 0.14) !important;
      color: #92eedb !important;
      border: 1px solid rgba(24, 203, 183, 0.22);
    }

    html.dark-layout .order-linkage-modal-transfer-cell,
    html.semi-dark-layout .order-linkage-modal-transfer-cell {
      background: #222d45 !important;
      background-color: #222d45 !important;
      border-left-color: rgba(126, 153, 194, 0.16) !important;
    }

    html.dark-layout .order-linkage-modal-table thead .order-linkage-modal-transfer-cell,
    html.semi-dark-layout .order-linkage-modal-table thead .order-linkage-modal-transfer-cell {
      background: #2c3955 !important;
      background-color: #2c3955 !important;
    }

    html.dark-layout .order-linkage-modal-table.table tbody tr:hover > .order-linkage-modal-transfer-cell,
    html.semi-dark-layout .order-linkage-modal-table.table tbody tr:hover > .order-linkage-modal-transfer-cell {
      background: #28344f !important;
      background-color: #28344f !important;
    }

    html.dark-layout .order-ai-shell .modal-content,
    html.semi-dark-layout .order-ai-shell .modal-content {
      background: #1f2940;
    }

    html.dark-layout .order-ai-shell .modal-header,
    html.dark-layout .order-ai-shell .modal-footer,
    html.semi-dark-layout .order-ai-shell .modal-header,
    html.semi-dark-layout .order-ai-shell .modal-footer {
      border-color: rgba(126, 153, 194, 0.16);
    }

    html.dark-layout .order-ai-shell .btn-close,
    html.semi-dark-layout .order-ai-shell .btn-close {
      filter: invert(1) grayscale(100%) brightness(200%);
    }

    @keyframes order-ai-flow {
      0% {
        background-position: 200% 0;
      }
      100% {
        background-position: 0 0;
      }
    }

    @keyframes order-ai-pulse {
      0%,
      100% {
        transform: translateY(0);
        box-shadow: 0 14px 28px rgba(22, 163, 74, 0.22);
      }
      50% {
        transform: translateY(-1px);
        box-shadow: 0 18px 34px rgba(22, 163, 74, 0.3);
      }
    }

    @media (max-width: 767.98px) {
      .order-ai-hero-grid {
        grid-template-columns: 1fr;
      }

      .order-ai-hero-story {
        min-height: 200px;
        padding: 1.2rem;
      }

      .order-ai-hero-story::before {
        background-size: 16rem auto;
        background-position: left 0.4rem top 1rem;
        opacity: 0.13;
      }

      .order-ai-hero-story-inner {
        display: block;
      }

      .order-ai-dropzone {
        min-height: 260px;
      }

      .order-ai-stage-content {
        flex-wrap: wrap;
      }

      .order-ai-progress-head {
        flex-wrap: wrap;
      }

      .order-ai-progress-status,
      .order-ai-progress-meta {
        width: 100%;
      }

      .order-ai-progress-meta {
        text-align: left;
      }

      .order-ai-stage-side,
      .order-ai-transfer-cta {
        width: 100%;
      }

      .order-ai-transfer-followup {
        grid-template-columns: 1fr;
      }

      .order-linkage-modal-summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }

    @media (max-width: 575.98px) {
      .order-linkage-modal-summary-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
@endsection

@section('content')
@php($aiOrderLabel = __('locale.Skeniraj narudzbu sa AI'))
<section
  class="order-ai-shell"
  id="order-ai-app"
  data-upload-url="{{ route('app-order-ai-scan-upload') }}"
  data-transfer-url="{{ route('app-orders-store') }}"
  data-positions-url="{{ route('app-orders-positions') }}"
  data-status-template="{{ route('app-order-ai-scan-status', ['scan' => '__SCAN__']) }}"
  data-initial-scan-id="{{ (int) ($initialScanId ?? 0) }}"
  data-csrf="{{ csrf_token() }}"
>
  <div class="row">
    <div class="col-12">
      <div class="card order-ai-hero mb-2">
        <div class="card-body p-2 p-md-3">
          <div class="order-ai-hero-grid">
            <div class="order-ai-hero-story">
              <div class="order-ai-hero-story-inner">
                <div class="order-ai-hero-copy">
                  <span class="order-ai-chip mb-1">
                    <i class="fa fa-magic" aria-hidden="true"></i>
                    {{ $aiOrderLabel }}
                  </span>
                  <h2 class="mb-75 order-ai-title">{{ $aiOrderLabel }}</h2>
                  <p class="mb-0 order-ai-subtle" style="max-width:720px;">
                    Ubaci PDF, sliku ili izvoz dokumenta. Dokument ostaje na istoj stranici, AI odradi ekstrakciju,
                    a upis u bazu ostaje pod tvojom kontrolom dok rucno ne potvrdis transfer.
                  </p>
                </div>
              </div>
            </div>

            <div class="order-ai-hero-aside">
              <div class="order-ai-stat">
                <span class="order-ai-stat-label">Provider</span>
                <span class="order-ai-stat-value">qla.dev</span>
              </div>

              <div class="order-ai-stat order-ai-stat--model">
                <span class="order-ai-stat-label">Model</span>
                <span class="order-ai-stat-value">TrendyGPT 1.0</span>
              </div>

              <div class="order-ai-stat">
                <span class="order-ai-stat-label">Status transfera</span>
                <span class="order-ai-stat-value">{{ $autoTransferEnabled ? 'Auto transfer ukljucen' : 'Rucni transfer aktivan' }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="row g-2 align-items-stretch mb-2">
        <div class="col-lg-7 col-12">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-2 p-md-3">
              <div class="order-ai-dropzone" id="order-ai-dropzone" tabindex="0" role="button" aria-label="Ucitaj dokument za AI skeniranje">
                <input type="file" class="d-none" id="order-ai-file-input" accept=".pdf,.png,.jpg,.jpeg,.webp,.bmp,.tif,.tiff,.json,.txt,.csv,.xls,.xlsx,.doc,.docx">
                <div class="order-ai-dropzone-icon">
                  <i data-feather="upload-cloud"></i>
                </div>
                <h3 class="mb-75">Prevuci dokument ovdje</h3>
                <p class="order-ai-subtle mb-1">ili klikni da odaberes fajl za AI obradu narudzbe</p>
                <small class="text-muted">PDF, slike i izvozi do 50 MB</small>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-5 col-12">
          <div class="card order-ai-progress-card h-100" id="order-ai-progress-card">
            <div class="card-body p-2">
              <div class="order-ai-progress-head mb-1">
                <div class="order-ai-progress-copy">
                  <h4 class="mb-25">Status obrade</h4>
                  <p class="mb-0 order-ai-subtle" id="order-ai-progress-label">Cekam upload...</p>
                </div>
                <div class="order-ai-progress-status">
                  <span class="order-ai-activity order-ai-hidden" id="order-ai-activity-indicator">
                    <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                    <span id="order-ai-activity-text">AI obrada u toku...</span>
                  </span>
                </div>
                <div class="order-ai-progress-meta">
                  <div class="fw-bolder" id="order-ai-progress-percent">0%</div>
                  <small class="text-muted" id="order-ai-file-name"></small>
                </div>
              </div>
              <div class="order-ai-progress-track mb-2">
                <div class="order-ai-progress-bar" id="order-ai-progress-bar"></div>
              </div>

              <div class="order-ai-stage-list" id="order-ai-stage-list">
                <div class="order-ai-stage" data-stage="upload">
                  <div class="order-ai-stage-fill" data-stage-fill></div>
                  <div class="order-ai-stage-content">
                    <span class="order-ai-stage-bullet"></span>
                    <div class="order-ai-stage-main">
                      <strong>Upload</strong>
                      <div class="small text-muted">Fajl se salje na lokalni staging.</div>
                    </div>
                  </div>
                </div>
                <div class="order-ai-stage" data-stage="extract">
                  <div class="order-ai-stage-fill" data-stage-fill></div>
                  <div class="order-ai-stage-content">
                    <span class="order-ai-stage-bullet"></span>
                    <div class="order-ai-stage-main">
                      <strong>AI ekstrakcija</strong>
                      <div class="small text-muted">Prompt pretvara dokument u strukturirani payload.</div>
                    </div>
                  </div>
                </div>
                <div class="order-ai-stage" data-stage="transfer">
                  <div class="order-ai-stage-fill" data-stage-fill></div>
                  <div class="order-ai-stage-content">
                    <span class="order-ai-stage-bullet"></span>
                    <div class="order-ai-stage-main">
                      <strong>Transfer u bazu</strong>
                      <div class="small text-muted" id="order-ai-transfer-hint">
                        Dugme se aktivira kada AI zavrsi ekstrakciju i pripremi payload.
                      </div>
                    </div>
                    <div class="order-ai-stage-side">
                      <button type="button" class="btn btn-success order-ai-transfer-cta" id="order-ai-transfer-button" disabled>Transfer u bazu</button>
                    </div>
                  </div>
                </div>
              </div>

              <div class="order-ai-transfer-followup order-ai-hidden" id="order-ai-transfer-followup">
                <button type="button" class="btn btn-outline-primary" id="order-ai-view-order-button">Vidi narudzbu</button>
                <button type="button" class="btn btn-outline-success" id="order-ai-new-order-button">Nova narudzba</button>
              </div>

              <div class="alert alert-warning order-ai-alert mt-2 mb-0 order-ai-hidden" id="order-ai-progress-warning"></div>
            </div>
          </div>
        </div>
      </div>

      <div class="row g-2">
        <div class="col-12">
          <div class="card order-ai-result-card order-ai-hidden" id="order-ai-result-card">
            <div class="card-body p-2">
              <div class="d-flex flex-wrap align-items-start justify-content-between gap-1 mb-2">
                <div>
                  <h4 class="mb-25">Rezultat AI skena</h4>
                  <p class="mb-0 order-ai-subtle" id="order-ai-result-caption">Nema obradjenog dokumenta.</p>
                </div>
                <span class="badge rounded-pill bg-light-primary text-primary" id="order-ai-result-status">Spremno</span>
              </div>

              <div class="order-ai-facts mb-2" id="order-ai-facts"></div>

              <div class="alert alert-info order-ai-alert mb-2 order-ai-hidden" id="order-ai-warnings"></div>
              <div class="alert alert-danger order-ai-alert mb-2 order-ai-hidden" id="order-ai-error"></div>
              <div class="alert alert-success order-ai-alert mb-2 order-ai-hidden" id="order-ai-success"></div>
              <div class="order-ai-saved-preview mb-2 order-ai-hidden" id="order-ai-saved-preview"></div>

              <div class="table-responsive mb-2 order-ai-hidden" id="order-ai-lines-shell">
                <table class="table table-sm order-ai-lines-table mb-0">
                  <thead>
                    <tr>
                      <th>#</th>
                      <th>Sifra</th>
                      <th class="order-ai-wrap">Naziv</th>
                      <th>Kolicina</th>
                      <th>JM</th>
                      <th>Jed. cijena</th>
                      <th class="order-ai-wrap">Total provjera</th>
                    </tr>
                  </thead>
                  <tbody id="order-ai-lines-body"></tbody>
                </table>
              </div>

              <div class="d-flex flex-wrap gap-1 order-ai-hidden" id="order-ai-actions">
                <button type="button" class="btn btn-primary" id="order-ai-view-positions-button">Pozicije</button>
                <a href="{{ route('app-orders') }}" class="btn btn-outline-secondary">Upravljanje narudzbama</a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<div class="modal fade" id="order-ai-order-modal" tabindex="-1" aria-labelledby="order-ai-order-modal-label" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0" id="order-ai-order-modal-label">Pregled spremljene narudzbe</h5>
          <div class="small text-muted">Kratki pregled narudzbe nakon upisa u bazu</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zatvori"></button>
      </div>
      <div class="modal-body" id="order-ai-order-modal-body"></div>
      <div class="modal-footer">
        <a href="{{ route('app-orders') }}" class="btn btn-outline-primary">Upravljanje narudzbama</a>
        <button type="button" class="btn btn-success" id="order-ai-modal-new-order-button">Nova narudzba</button>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Zatvori</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="order-ai-positions-modal" tabindex="-1" aria-labelledby="order-ai-positions-modal-label" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0" id="order-ai-positions-modal-label">Pozicije spremljene narudzbe</h5>
          <div class="small text-muted" id="order-ai-positions-modal-subtitle">Pregled pozicija upisanih u bazu</div>
        </div>
        <div class="d-flex align-items-center gap-1">
          <button type="button" class="btn btn-sm btn-outline-primary" id="order-ai-positions-refresh-button">Osvjezi</button>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zatvori"></button>
        </div>
      </div>
      <div class="modal-body order-ai-positions-body">
        <div class="order-ai-positions-loading" id="order-ai-positions-loading">
          <span class="spinner-border text-primary" role="status" aria-hidden="true"></span>
          <div>Ucitam pozicije spremljene narudzbe...</div>
        </div>
        <div class="alert alert-danger mb-0 order-ai-hidden" id="order-ai-positions-error"></div>
        <div id="order-ai-positions-content" class="order-ai-hidden"></div>
      </div>
      <div class="modal-footer">
        <a href="{{ route('app-orders') }}" class="btn btn-outline-primary">Upravljanje narudzbama</a>
        <button type="button" class="btn btn-success" id="order-ai-positions-new-order-button">Nova narudzba</button>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Zatvori</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="order-ai-transfer-error-modal" tabindex="-1" aria-labelledby="order-ai-transfer-error-modal-label" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0" id="order-ai-transfer-error-modal-label">Transfer u bazu nije uspio</h5>
          <div class="small text-muted">Prikaz razloga zbog kojeg Pantheon nije prihvatio narudzbu</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zatvori"></button>
      </div>
      <div class="modal-body">
        <div class="order-ai-transfer-error-copy" id="order-ai-transfer-error-modal-body"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-primary" id="order-ai-transfer-error-retry-button">Pokusaj ponovo</button>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Zatvori</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="order-ai-line-total-modal" tabindex="-1" aria-labelledby="order-ai-line-total-modal-label" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0" id="order-ai-line-total-modal-label">Provjera totala stavke</h5>
          <div class="small text-muted" id="order-ai-line-total-modal-subtitle">Poredi skenirani total sa proracunom iz kolicine i jed. cijene.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zatvori"></button>
      </div>
      <div class="modal-body">
        <div class="order-ai-total-check-copy">
          <div class="order-ai-total-check-banner" id="order-ai-line-total-status-box"></div>
          <div class="order-ai-total-check-grid">
            <div class="order-ai-total-check-row">
              <div>
                <div class="order-ai-total-check-label">Skenirani total stavke</div>
                <div class="order-ai-total-check-value" id="order-ai-line-total-source-value">0,00</div>
              </div>
              <div class="order-ai-total-check-edit">
                <input type="number" step="0.01" min="0" class="form-control" id="order-ai-line-total-input">
                <button type="button" class="btn btn-outline-primary" id="order-ai-line-total-save-button">Uredi</button>
              </div>
            </div>
            <div class="order-ai-total-check-row">
              <div class="order-ai-total-check-label">Preracunato iz jed. cijene i kolicine</div>
              <div class="order-ai-total-check-value" id="order-ai-line-total-computed-value">0,00</div>
            </div>
            <div class="order-ai-total-check-row">
              <div class="order-ai-total-check-label">Razlika</div>
              <div class="order-ai-total-check-value" id="order-ai-line-total-difference-value">0,00</div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Zatvori</button>
      </div>
    </div>
  </div>
</div>
@endsection

@section('page-script')
  <script>
    (function () {
      const app = document.getElementById('order-ai-app');
      if (!app) {
        return;
      }

      const uploadUrl = app.dataset.uploadUrl;
      const transferUrl = app.dataset.transferUrl;
      const positionsUrl = app.dataset.positionsUrl || '';
      const statusTemplate = app.dataset.statusTemplate;
      const initialScanId = Number(app.dataset.initialScanId || 0) || null;
      const initialScanState = @json($initialScanState ?? null);
      const csrfToken = app.dataset.csrf;
      const dropzone = document.getElementById('order-ai-dropzone');
      const fileInput = document.getElementById('order-ai-file-input');
      const progressCard = document.getElementById('order-ai-progress-card');
      const progressLabel = document.getElementById('order-ai-progress-label');
      const progressPercent = document.getElementById('order-ai-progress-percent');
      const progressBar = document.getElementById('order-ai-progress-bar');
      const fileNameEl = document.getElementById('order-ai-file-name');
      const activityIndicator = document.getElementById('order-ai-activity-indicator');
      const activityText = document.getElementById('order-ai-activity-text');
      const extractLive = document.getElementById('order-ai-extract-live');
      const progressWarning = document.getElementById('order-ai-progress-warning');
      const resultCard = document.getElementById('order-ai-result-card');
      const resultCaption = document.getElementById('order-ai-result-caption');
      const resultStatus = document.getElementById('order-ai-result-status');
      const facts = document.getElementById('order-ai-facts');
      const warningsBox = document.getElementById('order-ai-warnings');
      const errorBox = document.getElementById('order-ai-error');
      const successBox = document.getElementById('order-ai-success');
      const savedPreview = document.getElementById('order-ai-saved-preview');
      const linesShell = document.getElementById('order-ai-lines-shell');
      const linesBody = document.getElementById('order-ai-lines-body');
      const actions = document.getElementById('order-ai-actions');
      const transferButton = document.getElementById('order-ai-transfer-button');
      const transferHint = document.getElementById('order-ai-transfer-hint');
      const transferFollowup = document.getElementById('order-ai-transfer-followup');
      const viewPositionsButton = document.getElementById('order-ai-view-positions-button');
      const viewOrderButton = document.getElementById('order-ai-view-order-button');
      const newOrderButton = document.getElementById('order-ai-new-order-button');
      const orderModalElement = document.getElementById('order-ai-order-modal');
      const orderModalBody = document.getElementById('order-ai-order-modal-body');
      const orderModalNewOrderButton = document.getElementById('order-ai-modal-new-order-button');
      const positionsModalElement = document.getElementById('order-ai-positions-modal');
      const positionsModalSubtitle = document.getElementById('order-ai-positions-modal-subtitle');
      const positionsModalLoading = document.getElementById('order-ai-positions-loading');
      const positionsModalError = document.getElementById('order-ai-positions-error');
      const positionsModalContent = document.getElementById('order-ai-positions-content');
      const positionsModalRefreshButton = document.getElementById('order-ai-positions-refresh-button');
      const positionsModalNewOrderButton = document.getElementById('order-ai-positions-new-order-button');
      const transferErrorModalElement = document.getElementById('order-ai-transfer-error-modal');
      const transferErrorModalBody = document.getElementById('order-ai-transfer-error-modal-body');
      const transferErrorRetryButton = document.getElementById('order-ai-transfer-error-retry-button');
      const lineTotalModalElement = document.getElementById('order-ai-line-total-modal');
      const lineTotalModalSubtitle = document.getElementById('order-ai-line-total-modal-subtitle');
      const lineTotalStatusBox = document.getElementById('order-ai-line-total-status-box');
      const lineTotalSourceValue = document.getElementById('order-ai-line-total-source-value');
      const lineTotalComputedValue = document.getElementById('order-ai-line-total-computed-value');
      const lineTotalDifferenceValue = document.getElementById('order-ai-line-total-difference-value');
      const lineTotalInput = document.getElementById('order-ai-line-total-input');
      const lineTotalSaveButton = document.getElementById('order-ai-line-total-save-button');
      const stageNodes = Array.from(document.querySelectorAll('.order-ai-stage'));
      const stageFillNodes = stageNodes.reduce(function (carry, node) {
        if (node && node.dataset && node.dataset.stage) {
          carry[node.dataset.stage] = node.querySelector('[data-stage-fill]');
        }

        return carry;
      }, {});

      let pollTimer = null;
      let currentScanId = null;
      let uploadProgress = 0;
      let latestStatusPayload = null;
      let isTransferBusy = false;
      let extractFillTimer = null;
      let extractVisualProgress = 0;
      let activeLineTotalIndex = null;

      function escapeHtml(value) {
        return String(value ?? '')
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#039;');
      }

      function setVisible(node, visible) {
        if (!node) {
          return;
        }

        node.classList.toggle('order-ai-hidden', !visible);
      }

      function toFiniteNumber(value, fallback) {
        const number = Number(value);

        return Number.isFinite(number) ? number : (fallback ?? 0);
      }

      function formatAmount(value) {
        return toFiniteNumber(value, 0).toFixed(2);
      }

      function formatAmountWithCurrency(value, currency) {
        const suffix = String(currency || '').trim();

        return suffix ? `${formatAmount(value)} ${suffix}` : formatAmount(value);
      }

      function roundMoney(value) {
        return Math.round(toFiniteNumber(value, 0) * 100) / 100;
      }

      function parseAmountInput(value) {
        const normalized = String(value ?? '').replace(',', '.').trim();
        const parsed = Number(normalized);

        return Number.isFinite(parsed) ? roundMoney(Math.max(0, parsed)) : 0;
      }

      function canEditLineTotals() {
        return Boolean(
          latestStatusPayload
          && latestStatusPayload.status === 'completed'
          && latestStatusPayload.transfer_ready
        );
      }

      function resolveLineComputedTotal(item) {
        const quantity = toFiniteNumber(item && item.quantity, 0);
        const unitPrice = toFiniteNumber(item && item.unit_price, 0);

        return roundMoney(quantity * unitPrice);
      }

      function resolveLineSourceTotal(item) {
        const explicitTotal = toFiniteNumber(item && item.line_total, 0);

        return explicitTotal > 0 ? roundMoney(explicitTotal) : resolveLineComputedTotal(item);
      }

      function resolveLineComparison(item) {
        const computed = resolveLineComputedTotal(item);
        const source = resolveLineSourceTotal(item);
        const difference = roundMoney(source - computed);
        const absoluteDifference = Math.abs(difference);

        return {
          computed: computed,
          source: source,
          difference: difference,
          matches: absoluteDifference <= 0.01,
        };
      }

      function recalculateSummaryFromItems(payload) {
        const result = payload || {};
        const items = Array.isArray(result.items) ? result.items : [];
        const currentSummary = result.summary || {};

        const nextSummary = items.reduce(function (carry, item) {
          const baseValue = resolveLineSourceTotal(item);
          const vatRate = toFiniteNumber(item && item.vat_rate, 0);
          const vatValue = roundMoney(baseValue * (vatRate / 100));

          carry.subtotal += baseValue;
          carry.vat_total += vatValue;
          carry.grand_total += baseValue + vatValue;

          return carry;
        }, {
          subtotal: 0,
          vat_total: 0,
          grand_total: 0,
        });

        result.summary = {
          subtotal: roundMoney(nextSummary.subtotal || currentSummary.subtotal || 0),
          vat_total: roundMoney(nextSummary.vat_total || currentSummary.vat_total || 0),
          grand_total: roundMoney(nextSummary.grand_total || currentSummary.grand_total || 0),
        };

        return result;
      }

      function buildTransferPayloadFromState() {
        const payload = latestStatusPayload && latestStatusPayload.result ? latestStatusPayload.result : null;

        if (!payload) {
          return null;
        }

        recalculateSummaryFromItems(payload);

        const order = payload.order || {};
        const summary = payload.summary || {};
        const items = Array.isArray(payload.items) ? payload.items : [];

        return {
          order: {
            customer_name: String(order.customer_name || '').trim(),
            supplier_name: String(order.supplier_name || '').trim(),
            receiver_name: String(order.receiver_name || '').trim(),
            contact_name: String(order.contact_name || '').trim(),
            external_document_number: String(order.external_document_number || '').trim(),
            document_type: String(order.document_type || '').trim(),
            currency: String(order.currency || '').trim(),
            delivery_deadline: String(order.delivery_deadline || '').trim(),
            note: String(order.note || '').trim(),
            way_of_sale: String(order.way_of_sale || '').trim(),
            confidence: toFiniteNumber(order.confidence, 0),
            warnings: Array.isArray(order.warnings) ? order.warnings.map(function (warning) {
              return String(warning || '').trim();
            }).filter(Boolean) : [],
          },
          items: items.map(function (item, index) {
            return {
              line_number: Math.round(toFiniteNumber(item.line_number, index + 1)),
              product_code: String(item.product_code || '').trim(),
              product_name: String(item.product_name || '').trim(),
              quantity: toFiniteNumber(item.quantity, 0),
              unit: String(item.unit || '').trim(),
              unit_price: toFiniteNumber(item.unit_price, 0),
              line_total: resolveLineSourceTotal(item),
              vat_rate: toFiniteNumber(item.vat_rate, 0),
              vat_code: String(item.vat_code || '').trim(),
              discount_percent: toFiniteNumber(item.discount_percent, 0),
              priority: String(item.priority || '').trim(),
              note: String(item.note || '').trim(),
            };
          }),
          summary: {
            subtotal: toFiniteNumber(summary.subtotal, 0),
            vat_total: toFiniteNumber(summary.vat_total, 0),
            grand_total: toFiniteNumber(summary.grand_total, 0),
          },
        };
      }

      function syncFeatherIcons() {
        if (window.feather && typeof window.feather.replace === 'function') {
          window.feather.replace();
        }
      }

      function normalizeTransferFailureReason(reason) {
        const message = String(reason || '').trim();

        if (!message) {
          return 'Transfer u bazu nije uspio, ali detaljan razlog nije vracen.';
        }

        if (/rtHE_Order_tHE_SetSubj_21|anConsigneeQId/i.test(message)) {
          return 'Pantheon nije prihvatio narucitelja jer nije bio postavljen validan subject za anConsigneeQId.';
        }

        return message;
      }

      function resetMessages() {
        [progressWarning, warningsBox, errorBox, successBox].forEach((node) => {
          node.textContent = '';
          setVisible(node, false);
        });
      }

      function setProgress(percent, label) {
        const safePercent = Math.max(0, Math.min(100, Math.round(percent)));
        progressPercent.textContent = safePercent + '%';
        progressBar.style.width = safePercent + '%';
        if (label) {
          progressLabel.textContent = label;
        }
      }

      function mapUploadProgressToOverall(rawPercent) {
        return Math.round((Math.max(0, Math.min(100, rawPercent)) / 100) * 18);
      }

      function setStageState(stageName, finalize) {
        const order = ['upload', 'extract', 'transfer'];
        const activeIndex = order.indexOf(stageName);

        stageNodes.forEach((node, index) => {
          node.classList.remove('is-active', 'is-done');
          if (activeIndex === -1) {
            return;
          }
          if (index < activeIndex || (finalize && index === activeIndex)) {
            node.classList.add('is-done');
          } else if (index === activeIndex) {
            node.classList.add('is-active');
          }
        });
      }

      function setStageFill(stageName, percent) {
        const fillNode = stageFillNodes[stageName];

        if (!fillNode) {
          return;
        }

        fillNode.style.width = Math.max(0, Math.min(100, percent)) + '%';
      }

      function stopExtractFillAnimation(finalPercent) {
        if (extractFillTimer) {
          clearInterval(extractFillTimer);
          extractFillTimer = null;
        }

        extractVisualProgress = Math.max(0, Math.min(100, finalPercent));
        setStageFill('extract', extractVisualProgress);
      }

      function startExtractFillAnimation(seedPercent) {
        if (extractFillTimer) {
          return;
        }

        extractVisualProgress = Math.max(extractVisualProgress, Math.max(10, Math.min(88, seedPercent || 12)));
        setStageFill('extract', extractVisualProgress);

        extractFillTimer = window.setInterval(function () {
          if (extractVisualProgress >= 88) {
            return;
          }

          if (extractVisualProgress < 45) {
            extractVisualProgress += 6;
          } else if (extractVisualProgress < 72) {
            extractVisualProgress += 3;
          } else {
            extractVisualProgress += 1.2;
          }

          extractVisualProgress = Math.min(88, extractVisualProgress);
          setStageFill('extract', extractVisualProgress);
        }, 260);
      }

      function updateStageFills(data) {
        const status = (data && data.status) || '';
        const progress = toFiniteNumber(data && data.current_progress, 0);
        const transferReady = Boolean(data && data.transfer_ready);

        setStageFill('upload', uploadProgress);

        if (status === 'uploaded' || status === 'extracting') {
          startExtractFillAnimation(Math.max(16, Math.min(52, progress)));
        } else if (status === 'completed' || status === 'ready_for_transfer' || status === 'transferring' || status === 'transferred') {
          stopExtractFillAnimation(100);
        } else if (status === 'failed' && progress >= 25) {
          stopExtractFillAnimation(Math.max(extractVisualProgress, 72));
        } else if (!status) {
          stopExtractFillAnimation(0);
        }

        if (status === 'transferred') {
          setStageFill('transfer', 100);
        } else if (status === 'transferring' || isTransferBusy) {
          setStageFill('transfer', 72);
        } else if (status === 'completed' && transferReady) {
          setStageFill('transfer', 26);
        } else if (status === 'completed') {
          setStageFill('transfer', 8);
        } else {
          setStageFill('transfer', 0);
        }
      }

      function updateActivityState(data) {
        const status = (data && data.status) || '';
        const extractionBusy = status === 'uploaded' || status === 'extracting';
        const transferBusy = status === 'transferring' || isTransferBusy;
        const visible = extractionBusy || transferBusy;

        if (activityText) {
          activityText.textContent = transferBusy ? 'Transfer u bazu je u toku...' : 'AI ekstrakcija radi...';
        }

        setVisible(activityIndicator, visible);
        setVisible(extractLive, extractionBusy);
      }

      function resolveStatusLabel(status) {
        const map = {
          uploaded: 'Uploadovan',
          extracting: 'AI radi',
          completed: 'Spremno za pregled',
          ready_for_transfer: 'Spremno za transfer',
          transferring: 'Transfer u toku',
          transferred: 'Sacuvano u bazi',
          failed: 'Neuspjelo'
        };

        return map[status] || status || 'Spremno';
      }

      function setTransferButtonState(options) {
        const config = options || {};
        const busy = Boolean(config.busy);
        const complete = Boolean(config.complete);
        const enabled = Boolean(config.enabled) && !busy && !complete;
        const label = config.label || 'Transfer u bazu';

        transferButton.disabled = !enabled;
        transferButton.classList.toggle('is-ready', enabled);
        transferButton.classList.toggle('is-busy', busy);
        transferButton.classList.toggle('is-complete', complete);

        if (busy) {
          transferButton.innerHTML = '<span class="spinner-border spinner-border-sm me-50" role="status" aria-hidden="true"></span> ' + escapeHtml(label);
        } else {
          transferButton.textContent = label;
        }

        if (transferHint) {
          transferHint.textContent = config.hint || 'Dugme se aktivira kada AI zavrsi ekstrakciju i pripremi payload.';
        }
      }

      function detectStage(status, autoTransfer, progress, step) {
        if (status === 'transferred' || status === 'ready_for_transfer' || status === 'transferring') {
          return 'transfer';
        }
        if (status === 'completed') {
          return autoTransfer ? 'transfer' : 'extract';
        }
        if (status === 'failed' && /(pantheon|baza)/i.test(String(step || ''))) {
          return 'transfer';
        }
        if (status === 'failed' && Number(progress || 0) >= 25) {
          return 'extract';
        }
        if (status === 'uploaded' || status === 'extracting') {
          return 'extract';
        }
        return 'upload';
      }

      function renderFacts(payload, statusData) {
        const order = payload.order || {};
        const summary = payload.summary || {};
        const pantheon = statusData.pantheon_order || {};
        const factsMarkup = [
          { label: 'Kupac', value: order.customer_name || '-' },
          { label: 'Narucitelj', value: order.supplier_name || '-' },
          { label: 'Referenca', value: order.external_document_number || '-' },
          { label: 'Doc type', value: order.document_type || '-' },
          { label: 'Valuta', value: order.currency || '-' },
          { label: 'Iznos', value: formatAmount(summary.grand_total || 0) },
          { label: 'AI krediti', value: formatAmount(statusData.credits_spent || 0) },
          { label: 'Pantheon kljuc', value: pantheon.key || '-' }
        ];

        facts.innerHTML = factsMarkup.map((fact) => `
          <div class="order-ai-fact">
            <div class="text-muted small mb-50">${escapeHtml(fact.label)}</div>
            <div class="fw-bolder">${escapeHtml(fact.value)}</div>
          </div>
        `).join('');
      }

      function renderLines(payload) {
        const items = Array.isArray(payload.items) ? payload.items : [];
        const currency = String((payload.order && payload.order.currency) || '').trim();
        const allowLineEdit = canEditLineTotals();

        if (!items.length) {
          linesBody.innerHTML = '';
          setVisible(linesShell, false);
          return;
        }

        linesBody.innerHTML = items.map((item, index) => {
          const comparison = resolveLineComparison(item);
          const buttonClasses = comparison.matches ? 'is-match' : 'is-mismatch';
          const readOnlyClass = allowLineEdit ? '' : ' is-readonly';
          const diffPrefix = comparison.difference > 0 ? '+' : '';

          return `
            <tr>
              <td>${escapeHtml(item.line_number || '')}</td>
              <td>${escapeHtml(item.product_code || '-')}</td>
              <td class="order-ai-wrap">${escapeHtml(item.product_name || '-')}</td>
              <td>${escapeHtml(formatAmount(item.quantity || 0))}</td>
              <td>${escapeHtml(item.unit || '-')}</td>
              <td>${escapeHtml(formatAmount(item.unit_price || 0))}</td>
              <td class="order-ai-wrap">
                <button
                  type="button"
                  class="order-ai-line-total-trigger ${buttonClasses}${readOnlyClass}"
                  data-line-total-index="${index}"
                  ${allowLineEdit ? '' : 'tabindex="-1"'}
                >
                  <span class="order-ai-line-total-meta">
                    <span class="order-ai-line-total-computed">${escapeHtml(formatAmount(item.unit_price || 0))} x ${escapeHtml(formatAmount(item.quantity || 0))} = ${escapeHtml(formatAmountWithCurrency(comparison.computed, currency))}</span>
                    <span class="order-ai-line-total-source">AI total: ${escapeHtml(formatAmountWithCurrency(comparison.source, currency))}</span>
                    <span class="order-ai-line-total-diff">Razlika: ${escapeHtml(diffPrefix + formatAmountWithCurrency(comparison.difference, currency))}</span>
                  </span>
                </button>
              </td>
            </tr>
          `;
        }).join('');

        setVisible(linesShell, true);
      }

      function renderWarnings(warnings) {
        const validWarnings = Array.isArray(warnings) ? warnings.filter(Boolean) : [];
        warningsBox.innerHTML = validWarnings.map((warning) => `<div>${escapeHtml(warning)}</div>`).join('');
        setVisible(warningsBox, validWarnings.length > 0);
      }

      function renderSavedPreview(data) {
        const payload = (data && data.result) || {};
        const order = payload.order || {};
        const summary = payload.summary || {};
        const pantheon = (data && data.pantheon_order) || {};
        const items = Array.isArray(payload.items) ? payload.items : [];
        const itemCount = toFiniteNumber(data && data.transfer_meta && data.transfer_meta.item_count, items.length);
        const displayNumber = pantheon.view || pantheon.key || '-';

        savedPreview.innerHTML = `
          <div class="order-ai-saved-preview-header">
            <div>
              <span class="badge rounded-pill bg-light-success text-success px-1 py-75">Sacuvano u bazi</span>
              <h5 class="mb-50 mt-1">Narudzba ${escapeHtml(displayNumber)} je uspjesno upisana u bazu.</h5>
              <p class="mb-0 text-muted">Otvoris preview ili pozicije jednim klikom, pa odmah nastavis na upravljanje narudzbama.</p>
            </div>
          </div>
          <div class="order-ai-saved-grid">
            <div class="order-ai-saved-item">
              <div class="text-muted small mb-50">Kupac</div>
              <div class="fw-bolder">${escapeHtml(order.customer_name || '-')}</div>
            </div>
            <div class="order-ai-saved-item">
              <div class="text-muted small mb-50">Narucitelj</div>
              <div class="fw-bolder">${escapeHtml(order.supplier_name || '-')}</div>
            </div>
            <div class="order-ai-saved-item">
              <div class="text-muted small mb-50">Pozicije</div>
              <div class="fw-bolder">${escapeHtml(itemCount)}</div>
            </div>
            <div class="order-ai-saved-item">
              <div class="text-muted small mb-50">Ukupan iznos</div>
              <div class="fw-bolder">${escapeHtml(formatAmount(summary.grand_total || 0))}</div>
            </div>
          </div>
        `;

        setVisible(savedPreview, true);
      }

      function buildOrderModalHtml(data) {
        const payload = (data && data.result) || {};
        const order = payload.order || {};
        const summary = payload.summary || {};
        const pantheon = (data && data.pantheon_order) || {};
        const items = Array.isArray(payload.items) ? payload.items : [];
        const modalRows = items.length ? items.map(function (item) {
          return `
            <tr>
              <td>${escapeHtml(item.line_number || '')}</td>
              <td>${escapeHtml(item.product_code || '-')}</td>
              <td class="order-ai-wrap">${escapeHtml(item.product_name || '-')}</td>
              <td>${escapeHtml(formatAmount(item.quantity || 0))}</td>
              <td>${escapeHtml(item.unit || '-')}</td>
            </tr>
          `;
        }).join('') : '<tr><td colspan="5" class="text-center text-muted py-2">Nema pozicija za prikaz.</td></tr>';

        return `
          <div class="order-ai-modal-summary mb-2">
            <div class="order-ai-modal-summary-card">
              <div class="text-muted small mb-50">Narudzba u bazi</div>
              <div class="fw-bolder">${escapeHtml(pantheon.view || pantheon.key || '-')}</div>
            </div>
            <div class="order-ai-modal-summary-card">
              <div class="text-muted small mb-50">Kupac</div>
              <div class="fw-bolder">${escapeHtml(order.customer_name || '-')}</div>
            </div>
            <div class="order-ai-modal-summary-card">
              <div class="text-muted small mb-50">Narucitelj</div>
              <div class="fw-bolder">${escapeHtml(order.supplier_name || '-')}</div>
            </div>
            <div class="order-ai-modal-summary-card">
              <div class="text-muted small mb-50">Ukupno</div>
              <div class="fw-bolder">${escapeHtml(formatAmount(summary.grand_total || 0))} ${escapeHtml(order.currency || '')}</div>
            </div>
          </div>
          ${order.note ? `<div class="alert alert-light border mb-2"><strong>Napomena:</strong> ${escapeHtml(order.note)}</div>` : ''}
          <div class="order-ai-modal-table-wrap">
            <table class="table table-sm mb-0">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Sifra</th>
                  <th>Naziv</th>
                  <th>Kolicina</th>
                  <th>JM</th>
                </tr>
              </thead>
              <tbody>${modalRows}</tbody>
            </table>
          </div>
        `;
      }

      function openSavedOrderModal() {
        if (!latestStatusPayload || latestStatusPayload.status !== 'transferred') {
          return;
        }

        if (orderModalBody) {
          orderModalBody.innerHTML = buildOrderModalHtml(latestStatusPayload);
        }

        if (orderModalElement && window.bootstrap && window.bootstrap.Modal) {
          window.bootstrap.Modal.getOrCreateInstance(orderModalElement).show();
        }
      }

      function resolveTransferredOrderNumber() {
        const pantheon = latestStatusPayload && latestStatusPayload.pantheon_order
          ? latestStatusPayload.pantheon_order
          : {};

        return String(pantheon.view || pantheon.key || '').trim();
      }

      function closeSavedOrderModal() {
        if (!orderModalElement || !window.bootstrap || !window.bootstrap.Modal) {
          return;
        }

        const modalInstance = window.bootstrap.Modal.getInstance(orderModalElement);

        if (modalInstance) {
          modalInstance.hide();
        }
      }

      function openPositionsModal() {
        const orderNumber = resolveTransferredOrderNumber();

        if (!orderNumber) {
          return;
        }

        if (positionsModalElement && window.bootstrap && window.bootstrap.Modal) {
          window.bootstrap.Modal.getOrCreateInstance(positionsModalElement).show();
        }

        loadPositionsModalContent(orderNumber);
      }

      function closePositionsModal() {
        if (!positionsModalElement || !window.bootstrap || !window.bootstrap.Modal) {
          return;
        }

        const modalInstance = window.bootstrap.Modal.getInstance(positionsModalElement);

        if (modalInstance) {
          modalInstance.hide();
        }
      }

      function showTransferErrorModal(reason, technicalReason) {
        if (!transferErrorModalBody) {
          return;
        }

        const friendlyReason = normalizeTransferFailureReason(reason);
        const technical = String(technicalReason || '').trim();

        transferErrorModalBody.innerHTML = `
          <div class="alert alert-danger mb-0">
            <strong>Razlog:</strong> ${escapeHtml(friendlyReason)}
          </div>
          ${technical && technical !== friendlyReason ? `
            <div>
              <div class="small text-muted mb-50">Tehnicki detalj</div>
              <code>${escapeHtml(technical)}</code>
            </div>
          ` : ''}
        `;

        if (transferErrorModalElement && window.bootstrap && window.bootstrap.Modal) {
          window.bootstrap.Modal.getOrCreateInstance(transferErrorModalElement).show();
        }
      }

      function closeTransferErrorModal() {
        if (!transferErrorModalElement || !window.bootstrap || !window.bootstrap.Modal) {
          return;
        }

        const modalInstance = window.bootstrap.Modal.getInstance(transferErrorModalElement);

        if (modalInstance) {
          modalInstance.hide();
        }
      }

      function openLineTotalModal(index) {
        if (!lineTotalModalElement || !window.bootstrap || !window.bootstrap.Modal) {
          return;
        }

        const payload = latestStatusPayload && latestStatusPayload.result ? latestStatusPayload.result : null;
        const items = payload && Array.isArray(payload.items) ? payload.items : [];
        const item = items[index];

        if (!item) {
          return;
        }

        const order = payload.order || {};
        const comparison = resolveLineComparison(item);
        const currency = String(order.currency || '').trim();

        activeLineTotalIndex = index;

        if (lineTotalModalSubtitle) {
          lineTotalModalSubtitle.textContent = `${String(item.product_code || '-').trim()} · ${String(item.product_name || '-').trim()}`;
        }

        if (lineTotalStatusBox) {
          lineTotalStatusBox.className = `order-ai-total-check-banner ${comparison.matches ? 'is-match' : 'is-mismatch'}`;
          lineTotalStatusBox.innerHTML = comparison.matches
            ? '<strong>Provjera nije potrebna.</strong><div class="mt-50">Skenirani total se podudara sa proracunom iz kolicine i jed. cijene.</div>'
            : '<strong>Provjera je preporucena.</strong><div class="mt-50">Skenirani total odstupa od proracuna iz kolicine i jed. cijene.</div>';
        }

        if (lineTotalSourceValue) {
          lineTotalSourceValue.textContent = formatAmountWithCurrency(comparison.source, currency);
        }

        if (lineTotalComputedValue) {
          lineTotalComputedValue.textContent = formatAmountWithCurrency(comparison.computed, currency);
        }

        if (lineTotalDifferenceValue) {
          lineTotalDifferenceValue.textContent = formatAmountWithCurrency(comparison.difference, currency);
        }

        if (lineTotalInput) {
          lineTotalInput.value = formatAmount(comparison.source);
          lineTotalInput.disabled = !canEditLineTotals();
        }

        if (lineTotalSaveButton) {
          lineTotalSaveButton.disabled = !canEditLineTotals();
        }

        window.bootstrap.Modal.getOrCreateInstance(lineTotalModalElement).show();
      }

      function closeLineTotalModal() {
        if (!lineTotalModalElement || !window.bootstrap || !window.bootstrap.Modal) {
          return;
        }

        activeLineTotalIndex = null;
        const modalInstance = window.bootstrap.Modal.getInstance(lineTotalModalElement);

        if (modalInstance) {
          modalInstance.hide();
        }
      }

      function saveLineTotalEdit() {
        if (!canEditLineTotals() || activeLineTotalIndex === null || !lineTotalInput) {
          return;
        }

        const payload = latestStatusPayload && latestStatusPayload.result ? latestStatusPayload.result : null;
        const items = payload && Array.isArray(payload.items) ? payload.items : [];
        const item = items[activeLineTotalIndex];

        if (!item) {
          return;
        }

        item.line_total = parseAmountInput(lineTotalInput.value);
        recalculateSummaryFromItems(payload);
        closeLineTotalModal();
        renderStatus(latestStatusPayload);
      }

      function setPositionsModalState(options) {
        const config = options || {};

        if (positionsModalSubtitle) {
          positionsModalSubtitle.textContent = config.subtitle || 'Pregled pozicija upisanih u bazu';
        }

        if (typeof config.html === 'string' && positionsModalContent) {
          positionsModalContent.innerHTML = config.html;
        }

        if (positionsModalError) {
          positionsModalError.textContent = config.error || '';
        }

        setVisible(positionsModalLoading, Boolean(config.loading));
        setVisible(positionsModalError, Boolean(config.error));
        setVisible(positionsModalContent, Boolean(config.showContent));

        if (positionsModalRefreshButton) {
          positionsModalRefreshButton.disabled = Boolean(config.loading) || resolveTransferredOrderNumber() === '';
        }
      }

      async function loadPositionsModalContent(orderNumber) {
        if (!positionsUrl) {
          setPositionsModalState({
            loading: false,
            showContent: false,
            subtitle: orderNumber ? `Narudzba ${orderNumber}` : 'Pregled pozicija upisanih u bazu',
            error: 'Ruta za pozicije trenutno nije dostupna.'
          });
          return;
        }

        if (!orderNumber) {
          setPositionsModalState({
            loading: false,
            showContent: false,
            error: 'Broj spremljene narudzbe nije dostupan.'
          });
          return;
        }

        setPositionsModalState({
          loading: true,
          showContent: false,
          html: '',
          error: '',
          subtitle: `Narudzba ${orderNumber}`
        });

        try {
          const query = new URLSearchParams({ order_number: orderNumber });
          const response = await fetch(`${positionsUrl}?${query.toString()}`, {
            headers: {
              'Accept': 'text/html',
              'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
          });
          const html = await response.text();

          setPositionsModalState({
            loading: false,
            showContent: true,
            html: html,
            error: '',
            subtitle: `Narudzba ${orderNumber}`
          });
          syncFeatherIcons();
        } catch (error) {
          setPositionsModalState({
            loading: false,
            showContent: false,
            subtitle: `Narudzba ${orderNumber}`,
            error: 'Pozicije trenutno nije moguce ucitati. Pokusaj ponovo za nekoliko trenutaka.'
          });
        }
      }

      function resetInterface() {
        stopPolling();
        stopExtractFillAnimation(0);
        currentScanId = null;
        uploadProgress = 0;
        latestStatusPayload = null;
        isTransferBusy = false;
        activeLineTotalIndex = null;
        fileNameEl.textContent = '';
        fileInput.value = '';
        facts.innerHTML = '';
        linesBody.innerHTML = '';
        savedPreview.innerHTML = '';
        orderModalBody.innerHTML = '';
        if (positionsModalContent) {
          positionsModalContent.innerHTML = '';
        }
        if (transferErrorModalBody) {
          transferErrorModalBody.innerHTML = '';
        }
        if (lineTotalInput) {
          lineTotalInput.value = '';
        }
        resultCaption.textContent = 'Nema obradjenog dokumenta.';
        resultStatus.textContent = 'Spremno';
        setVisible(resultCard, false);
        setVisible(linesShell, false);
        setVisible(savedPreview, false);
        setVisible(actions, false);
        setVisible(transferFollowup, false);
        resetMessages();
        setPositionsModalState({
          loading: false,
          showContent: false,
          html: '',
          error: '',
          subtitle: 'Pregled pozicija upisanih u bazu'
        });
        setProgress(0, 'Cekam upload...');
        setStageState('upload', false);
        setStageFill('upload', 0);
        setStageFill('transfer', 0);
        updateActivityState(null);
        setTransferButtonState({
          enabled: false,
          label: 'Transfer u bazu',
          hint: 'Dugme se aktivira kada AI zavrsi ekstrakciju i pripremi payload.'
        });
      }

      function startNewOrder() {
        closeSavedOrderModal();
        closePositionsModal();
        closeTransferErrorModal();
        closeLineTotalModal();
        resetInterface();
      }

      function renderStatus(data) {
        latestStatusPayload = data;
        const payload = data.result || {};
        const autoTransfer = Boolean(data.auto_transfer);
        const effectiveProgress = toFiniteNumber(data.current_progress, 0);
        const stageName = detectStage(data.status, autoTransfer, data.current_progress, data.processing_step);
        const finalizeStage = (data.status === 'completed' && stageName === 'extract' && !autoTransfer)
          || (data.status === 'transferred' && stageName === 'transfer');

        setVisible(resultCard, true);
        resetMessages();
        if (data.source_file_name) {
          fileNameEl.textContent = data.source_file_name;
        }
        setProgress(effectiveProgress, data.processing_step || 'AI obrada je u toku.');
        setStageState(stageName, finalizeStage);
        updateStageFills(data);
        updateActivityState(data);
        renderFacts(payload, data);
        renderLines(payload);
        renderWarnings(data.warnings || []);

        resultCaption.textContent = data.processing_step || 'Status nije dostupan.';
        resultStatus.textContent = resolveStatusLabel(data.status);
        setVisible(savedPreview, false);
        setVisible(actions, false);
        setVisible(transferFollowup, false);

        if (data.status === 'failed') {
          errorBox.textContent = data.error_message || 'AI obrada nije uspjela.';
          setVisible(errorBox, true);
          if (/(transfer|baza|pantheon)/i.test(String(data.processing_step || '')) || /(anConsigneeQId|SetSubj|Pantheon)/i.test(String(data.error_message || ''))) {
            showTransferErrorModal(data.error_message || 'Transfer u bazu nije uspio.', data.error_message || '');
          }
          setTransferButtonState({
            enabled: false,
            label: 'Transfer u bazu',
            hint: 'AI obrada nije uspjela. Ucitaj novi dokument i pokusaj ponovo.'
          });
          return;
        }

        if (data.status === 'completed') {
          setTransferButtonState({
            enabled: Boolean(data.transfer_ready),
            label: 'Transfer u bazu',
            hint: data.transfer_ready
              ? 'AI payload je spreman. Pregledaj rezultat i klikni za upis u bazu.'
              : 'Transfer ostaje zakljucan dok svi obavezni podaci nisu pripremljeni.'
          });

          if (!autoTransfer) {
            if (data.transfer_ready && data.transfer_preview_error) {
              progressWarning.textContent = 'Priprema preview-a nije uspjela, ali i dalje mozes pokusati rucni transfer u bazu.';
            } else if (data.transfer_ready && data.transfer_preview_available) {
              progressWarning.textContent = 'Preview payload je pripremljen. Pregledaj rezultat i pokreni transfer u bazu kada budes spreman.';
            } else if (data.transfer_ready) {
              progressWarning.textContent = 'Rezultat je spreman za upis u bazu. Pokreni transfer kada budes spreman.';
            } else {
              progressWarning.textContent = 'Ekstrakcija je zavrsena. Pregledaj rezultat i dopuni podatke ako nesto nedostaje.';
            }
            setVisible(progressWarning, true);
          }
          return;
        }

        if (data.status === 'ready_for_transfer' || data.status === 'transferring') {
          setTransferButtonState({
            enabled: false,
            busy: true,
            label: autoTransfer ? 'Auto transfer radi...' : 'Transfer u toku...',
            hint: autoTransfer
              ? 'AI trenutno samostalno salje narudzbu prema bazi.'
              : 'Narudzba se upravo upisuje u bazu.'
          });
          return;
        }

        if (data.status === 'transferred') {
          const orderView = data.pantheon_order && data.pantheon_order.view ? data.pantheon_order.view : data.pantheon_order.key;
          successBox.textContent = orderView
            ? `Narudzba je uspjesno spremljena u bazu kao ${orderView}.`
            : 'Narudzba je uspjesno spremljena u bazu.';
          setVisible(successBox, true);
          renderSavedPreview(data);
          setVisible(actions, true);
          setVisible(transferFollowup, true);
          setTransferButtonState({
            enabled: false,
            label: 'Prebaceno u bazu',
            hint: 'Narudzba je spremljena. Otvori preview, pregledaj pozicije ili zapocni novu narudzbu.',
            complete: true
          });
          return;
        }

        setTransferButtonState({
          enabled: false,
          label: 'Transfer u bazu',
          hint: 'AI trenutno cita dokument i priprema strukturu narudzbe.'
        });
      }

      function stopPolling() {
        if (pollTimer) {
          clearTimeout(pollTimer);
          pollTimer = null;
        }
      }

      async function pollStatus() {
        if (!currentScanId) {
          return;
        }

        try {
          const response = await fetch(statusTemplate.replace('__SCAN__', currentScanId), {
            headers: {
              'Accept': 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
          });

          const payload = await response.json();
          const data = payload.data || {};
          renderStatus(data);

          if (['completed', 'transferred', 'failed'].includes(data.status)) {
            stopPolling();
            return;
          }

          pollTimer = window.setTimeout(pollStatus, 1300);
        } catch (error) {
          progressWarning.textContent = 'Status AI obrade trenutno nije dostupan.';
          setVisible(progressWarning, true);
          updateActivityState(null);
          stopPolling();
        }
      }

      function startPolling(scanId) {
        currentScanId = scanId;
        stopPolling();
        pollTimer = window.setTimeout(pollStatus, 900);
      }

      function handleUpload(file) {
        if (!file) {
          return;
        }

        stopPolling();
        closeSavedOrderModal();
        closePositionsModal();
        closeLineTotalModal();
        resetMessages();
        currentScanId = null;
        latestStatusPayload = null;
        uploadProgress = 0;
        isTransferBusy = false;
        fileNameEl.textContent = file.name;
        setVisible(progressCard, true);
        setVisible(resultCard, false);
        setVisible(actions, false);
        setVisible(transferFollowup, false);
        setVisible(savedPreview, false);
        facts.innerHTML = '';
        linesBody.innerHTML = '';
        savedPreview.innerHTML = '';
        orderModalBody.innerHTML = '';
        if (positionsModalContent) {
          positionsModalContent.innerHTML = '';
        }
        setVisible(linesShell, false);
        setPositionsModalState({
          loading: false,
          showContent: false,
          html: '',
          error: '',
          subtitle: 'Pregled pozicija upisanih u bazu'
        });
        setProgress(0, 'Priprema lokalnog staging uploada...');
        setStageState('upload', false);
        setStageFill('upload', 0);
        setStageFill('transfer', 0);
        stopExtractFillAnimation(0);
        updateActivityState(null);
        setTransferButtonState({
          enabled: false,
          label: 'Transfer u bazu',
          hint: 'AI priprema dokument za ekstrakciju.'
        });

        const formData = new FormData();
        formData.append('file', file);

        const xhr = new XMLHttpRequest();
        xhr.open('POST', uploadUrl, true);
        xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.responseType = 'json';

        xhr.upload.addEventListener('progress', function (event) {
          if (!event.lengthComputable) {
            return;
          }

          uploadProgress = Math.round((event.loaded / event.total) * 100);
          setProgress(mapUploadProgressToOverall(uploadProgress), uploadProgress >= 100 ? 'Upload zavrsen, pokrecem AI obradu...' : 'Dokument se ucitava na server...');
          setStageState('upload', uploadProgress >= 100);
          setStageFill('upload', uploadProgress);
        });

        xhr.addEventListener('load', function () {
          if (xhr.status < 200 || xhr.status >= 300) {
            const response = xhr.response || {};
            progressWarning.textContent = response.message || 'Upload nije uspio.';
            setVisible(progressWarning, true);
            updateActivityState(null);
            return;
          }

          const response = xhr.response || {};
          currentScanId = response.scan_id;
          uploadProgress = 100;
          setProgress(18, 'Upload zavrsen. Dokument ceka AI ekstrakciju.');
          setStageState('extract', false);
          setStageFill('upload', 100);
          startExtractFillAnimation(14);
          updateActivityState({ status: 'uploaded' });
          startPolling(response.scan_id);
        });

        xhr.addEventListener('error', function () {
          progressWarning.textContent = 'Greska pri uploadu dokumenta.';
          setVisible(progressWarning, true);
          updateActivityState(null);
        });

        xhr.send(formData);
      }

      async function transferToPantheon() {
        if (!currentScanId || isTransferBusy) {
          return;
        }

        if (!latestStatusPayload || latestStatusPayload.status !== 'completed' || !latestStatusPayload.transfer_ready) {
          return;
        }

        isTransferBusy = true;
        resetMessages();
        setStageState('transfer', false);
        updateStageFills({ status: 'transferring', transfer_ready: true });
        updateActivityState({ status: 'transferring' });
        setTransferButtonState({
          enabled: false,
          busy: true,
          label: 'Transfer u toku...',
          hint: 'Narudzba se upravo upisuje u bazu.'
        });

        try {
          const transferPayload = buildTransferPayloadFromState();
          const response = await fetch(transferUrl, {
            method: 'POST',
            headers: {
              'Accept': 'application/json',
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': csrfToken,
              'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
              scan_id: currentScanId,
              payload: transferPayload,
            }),
          });
          const rawText = await response.text();
          let payload = null;

          try {
            payload = rawText ? JSON.parse(rawText) : {};
          } catch (error) {
            payload = null;
          }

          if (!response.ok) {
            const failure = new Error((payload && (payload.reason || payload.message)) || 'Transfer u bazu nije uspio.');
            failure.technicalReason = payload && payload.technical_reason
              ? payload.technical_reason
              : (rawText || '');
            throw failure;
          }

          if (latestStatusPayload) {
            latestStatusPayload.current_progress = 100;
            latestStatusPayload.status = 'transferred';
            latestStatusPayload.processing_step = 'Narudzba je rucno prebacena u bazu.';
            latestStatusPayload.pantheon_order = {
              key: payload.data ? payload.data.pantheon_order_key : '',
              view: payload.data ? payload.data.pantheon_order_view : '',
              qid: payload.data ? payload.data.pantheon_order_qid : null,
            };
            latestStatusPayload.transfer_meta = payload.data || {};
            isTransferBusy = false;
            renderStatus(latestStatusPayload);
          }
        } catch (error) {
          isTransferBusy = false;
          errorBox.textContent = error.message || 'Transfer u bazu nije uspio.';
          setVisible(errorBox, true);
          showTransferErrorModal(error.message || 'Transfer u bazu nije uspio.', error.technicalReason || '');
          updateActivityState(latestStatusPayload);
          updateStageFills(latestStatusPayload);
          setTransferButtonState({
            enabled: true,
            label: 'Pokusaj ponovo',
            hint: 'Transfer nije uspio. Pregledaj gresku i pokusaj ponovo.'
          });
        }
      }

      dropzone.addEventListener('click', function () {
        fileInput.click();
      });

      dropzone.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          fileInput.click();
        }
      });

      fileInput.addEventListener('change', function (event) {
        const file = event.target.files && event.target.files[0] ? event.target.files[0] : null;
        handleUpload(file);
      });

      ['dragenter', 'dragover'].forEach((eventName) => {
        dropzone.addEventListener(eventName, function (event) {
          event.preventDefault();
          event.stopPropagation();
          dropzone.classList.add('is-dragover');
        });
      });

      ['dragleave', 'drop'].forEach((eventName) => {
        dropzone.addEventListener(eventName, function (event) {
          event.preventDefault();
          event.stopPropagation();
          dropzone.classList.remove('is-dragover');
        });
      });

      dropzone.addEventListener('drop', function (event) {
        const file = event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files[0]
          ? event.dataTransfer.files[0]
          : null;
        handleUpload(file);
      });

      linesBody.addEventListener('click', function (event) {
        const trigger = event.target.closest('[data-line-total-index]');

        if (!trigger) {
          return;
        }

        const index = Number(trigger.dataset.lineTotalIndex);

        if (!Number.isInteger(index)) {
          return;
        }

        openLineTotalModal(index);
      });

      transferButton.addEventListener('click', transferToPantheon);
      viewPositionsButton.addEventListener('click', openPositionsModal);
      viewOrderButton.addEventListener('click', openSavedOrderModal);
      newOrderButton.addEventListener('click', startNewOrder);
      orderModalNewOrderButton.addEventListener('click', startNewOrder);
      positionsModalRefreshButton.addEventListener('click', function () {
        const orderNumber = resolveTransferredOrderNumber();

        if (!orderNumber) {
          return;
        }

        loadPositionsModalContent(orderNumber);
      });
      positionsModalNewOrderButton.addEventListener('click', startNewOrder);
      transferErrorRetryButton.addEventListener('click', function () {
        closeTransferErrorModal();
        transferToPantheon();
      });
      lineTotalSaveButton.addEventListener('click', saveLineTotalEdit);
      lineTotalInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
          event.preventDefault();
          saveLineTotalEdit();
        }
      });

      resetInterface();

      if (initialScanId) {
        currentScanId = initialScanId;
        setVisible(progressCard, true);

        if (initialScanState && typeof initialScanState === 'object') {
          if (initialScanState.source_file_name) {
            fileNameEl.textContent = initialScanState.source_file_name;
          }

          renderStatus(initialScanState);

          if (!['completed', 'transferred', 'failed'].includes(String(initialScanState.status || ''))) {
            startPolling(initialScanId);
          }
        } else {
          startPolling(initialScanId);
        }
      }

      syncFeatherIcons();
    })();
  </script>
@endsection
