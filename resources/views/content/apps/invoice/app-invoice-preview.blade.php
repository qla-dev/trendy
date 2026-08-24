@extends('layouts/contentLayoutMaster')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('vendors/css/pickers/flatpickr/flatpickr.min.css')}}">
<link rel="stylesheet" href="{{asset('vendors/css/extensions/sweetalert2.min.css')}}">
<link rel="stylesheet" href="{{asset('vendors/css/forms/select/select2.min.css')}}">
@endsection
@section('page-style')
<link rel="stylesheet" href="{{asset('css/base/plugins/forms/pickers/form-flat-pickr.css')}}">
<link rel="stylesheet" href="{{asset('css/base/pages/app-invoice.css')}}">
<style>
  #qr-scanner-modal .modal-body {
    background-color: unset !important;
  }
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
    font-weight: 400;
  }
  .invoice-preview .invoice-title .invoice-key {
    font-weight: 700;
  }
  .invoice-preview .invoice-title .invoice-order-number {
    margin-left: 0;
    font-size: 0.85em;
    font-weight: 700;
    color: #6e6b7b;
  }
  .invoice-preview .invoice-title .invoice-title-stack {
    display: inline-flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.5rem;
  }
  .invoice-preview .invoice-title .invoice-order-number .invoice-number {
    margin-left: 0.45rem;
    font-weight: 400;
  }
  body.dark-layout .invoice-preview .invoice-title .invoice-order-number,
  body.semi-dark-layout .invoice-preview .invoice-title .invoice-order-number,
  .dark-layout .invoice-preview .invoice-title .invoice-order-number,
  .semi-dark-layout .invoice-preview .invoice-title .invoice-order-number {
    color: #b4bdd3;
  }
  .invoice-preview .invoice-date-wrapper, .invoice-edit .invoice-date-wrapper, .invoice-add .invoice-date-wrapper {
    justify-content: flex-end;
    margin-bottom: 0.45rem !important;
  }
  .invoice-preview .invoice-date-wrapper:last-child {
    margin-bottom: 0 !important;
  }
  .invoice-preview .invoice-date-wrapper .invoice-date-title, .invoice-edit .invoice-date-wrapper .invoice-date-title, .invoice-add .invoice-date-wrapper .invoice-date-title {
    width: unset;
    font-weight: 700;
  }
  .invoice-preview .invoice-date-wrapper .invoice-date, .invoice-edit .invoice-date-wrapper .invoice-date, .invoice-add .invoice-date-wrapper .invoice-date {
    font-weight: 400;
  }
  .invoice-preview-wrapper .logo-wrapper .invoice-logo {
    color: #42526e !important;
  }
  .invoice-preview-wrapper .logo-wrapper .wo-brand-logo {
    border-radius: 999px;
  }
  .invoice-actions .btn {
    height: 40px;
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
  .wo-mobile-top-actions {
    display: none;
  }
  .invoice-preview-wrapper {
    --wo-divider-color: #ebe9f1;
    --wo-table-scroll-track: var(--app-scroll-track);
    --wo-table-scroll-thumb: var(--app-scroll-thumb-flat);
    --wo-table-scroll-thumb-hover: var(--app-scroll-thumb-flat-hover);
    --wo-table-scroll-thumb-active: var(--app-scroll-thumb-flat-active);
    --wo-table-scroll-thumb-border: var(--app-scroll-thumb-border);
  }
  body.dark-layout .invoice-preview-wrapper,
  body.semi-dark-layout .invoice-preview-wrapper,
  .dark-layout .invoice-preview-wrapper,
  .semi-dark-layout .invoice-preview-wrapper {
    --wo-divider-color: rgba(184, 190, 220, 0.22);
    --wo-table-scroll-track: var(--app-scroll-track);
    --wo-table-scroll-thumb: var(--app-scroll-thumb-flat);
    --wo-table-scroll-thumb-hover: var(--app-scroll-thumb-flat-hover);
    --wo-table-scroll-thumb-active: var(--app-scroll-thumb-flat-active);
    --wo-table-scroll-thumb-border: var(--app-scroll-thumb-border);
  }
  .invoice-preview-wrapper hr.invoice-spacing {
    border-top-color: var(--wo-divider-color) !important;
  }
  .invoice-preview-wrapper .invoice-actions.invoice-actions-scrolled .card {
    transform: translateY(-2px);
    box-shadow: 0 10px 24px rgba(34, 41, 47, 0.12);
  }
  .invoice-actions-divider {
    border-top: 1px solid var(--wo-divider-color) !important;
    margin: 0.25rem 0 0.75rem;
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
  .wo-other-options-toggle {
    display: none !important;
  }
  .invoice-preview-wrapper .wo-other-options-collapse.collapse:not(.show) {
    display: block;
  }
  .wo-other-options-chevron {
    transition: transform 0.2s ease;
  }
  .wo-other-options-toggle[aria-expanded="true"] .wo-other-options-chevron {
    transform: rotate(180deg);
  }
  .wo-close-table .form-control {
    min-width: 180px;
  }
  .wo-close-clock-fields {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    min-width: 145px;
  }
  .wo-close-clock-fields .form-control {
    min-width: 0;
    width: 58px;
    text-align: center;
  }
  .wo-close-worker-suggestions {
    position: fixed;
    z-index: 1085;
    max-height: 210px;
    overflow-y: auto;
    background: var(--bs-body-bg, #fff);
    border: 1px solid #d8d6de;
    border-radius: 0.375rem;
    box-shadow: 0 0.25rem 1rem rgba(34, 41, 47, 0.16);
  }
  .wo-close-code-suggestions {
    position: fixed;
    z-index: 1085;
    max-height: 210px;
    overflow-y: auto;
    background: var(--bs-body-bg, #fff);
    border: 1px solid #d8d6de;
    border-radius: 0.375rem;
    box-shadow: 0 0.25rem 1rem rgba(34, 41, 47, 0.16);
  }
  .wo-close-worker-suggestion {
    display: block;
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 0;
    background: transparent;
    text-align: left;
  }
  .wo-close-worker-suggestion:hover,
  .wo-close-worker-suggestion:focus {
    background: rgba(115, 103, 240, 0.08);
  }
  .wo-close-worker-suggestion.is-active {
    background: rgba(115, 103, 240, 0.16);
  }
  .wo-close-code-suggestion.is-active {
    background: rgba(115, 103, 240, 0.16);
  }
  .wo-close-add-material-row-btn,
  .wo-close-add-material-row-btn:disabled,
  .wo-close-add-material-row-btn:hover,
  .wo-close-add-material-row-btn:focus,
  .wo-close-add-material-row-btn:active,
  .wo-close-add-material-row-btn.active {
    background-color: #fff !important;
    opacity: 1 !important;
    pointer-events: auto !important;
  }
  .wo-close-code-suggestion {
    display: block;
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 0;
    background: transparent;
    text-align: left;
  }
  .wo-close-code-suggestion:hover,
  .wo-close-code-suggestion:focus {
    background: rgba(115, 103, 240, 0.08);
  }
  .wo-close-action-buttons {
    display: inline-flex;
    gap: 0.45rem;
  }
  .wo-close-copy-row-btn,
  .wo-close-clear-row-btn,
  .wo-close-delete-row-btn,
  .wo-close-add-material-row-btn,
  .wo-close-material-clear-row-btn,
  .wo-close-material-delete-row-btn {
    display: inline-flex;
    width: 2.25rem;
    height: 2.25rem;
    align-items: center;
    justify-content: center;
    padding: 0;
  }
  .wo-close-copy-row-btn {
    appearance: none;
    -webkit-appearance: none;
    border: 1px solid #7367f0 !important;
    border-radius: 0.358rem;
    cursor: pointer;
    font: inherit;
    font-size: 0.875rem;
    line-height: 1;
    transition: color 0.15s ease, background-color 0.15s ease, border-color 0.15s ease;
  }
  .wo-close-copy-row-btn:focus,
  .wo-close-clear-row-btn:focus,
  .wo-close-delete-row-btn:focus {
    box-shadow: none;
  }
  .wo-close-copy-row-btn,
  .wo-close-copy-row-btn:focus,
  .wo-close-copy-row-btn:active,
  .wo-close-copy-row-btn.active {
    color: #7367f0 !important;
    background-color: #fff !important;
    border-color: #7367f0 !important;
    box-shadow: none !important;
  }
  .wo-close-copy-row-btn:hover:not(:active) {
    color: #fff !important;
    background-color: #7367f0 !important;
    border-color: #7367f0 !important;
  }
  .wo-close-operation-error {
    display: none;
    font-size: 0.78rem;
  }
  .wo-close-operation-row.is-invalid .wo-close-operation-error {
    display: block;
  }
  .wo-close-break-error {
    display: none;
    font-size: 0.78rem;
  }
  .wo-close-break-error.is-visible {
    display: block;
  }
  .wo-close-clock-break,
  .wo-close-clock-break:focus,
  .wo-close-time.wo-close-time-error,
  .wo-close-time.wo-close-time-error:focus {
    border-color: #ea5455 !important;
    background-image: none !important;
    box-shadow: 0 0 0 0.2rem rgba(234, 84, 85, 0.15) !important;
  }
  .wo-material-quantity-controls {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    min-width: 150px;
  }
  .wo-material-quantity-input {
    min-width: 0;
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
    flex-wrap: nowrap;
    gap: 0.5rem;
    margin-bottom: 0.85rem;
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
  }
  .wo-meta-chip-row > * {
    flex: 0 0 auto;
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
  .wo-header-shell {
    display: flex;
    flex-direction: column;
    gap: 0.95rem;
  }
  .wo-header-brand-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    width: 100%;
  }
  .invoice-preview-wrapper .wo-header-brand-row .logo-wrapper {
    margin-bottom: 0;
  }
  .invoice-preview-wrapper .wo-header-brand-row .wo-header-qr-block {
    margin-left: auto;
  }
  .wo-header-details-row {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 1.5rem;
    flex-wrap: wrap;
  }
  .wo-header-company-block {
    flex: 1 1 320px;
    min-width: 0;
  }
  .wo-header-right-column {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    margin-left: auto;
    text-align: right;
    flex: 0 1 auto;
  }
  .wo-header-main-row {
    display: flex;
    align-items: flex-start;
    justify-content: flex-end;
    gap: 0.75rem;
  }
  .wo-header-meta {
    text-align: right;
  }
  .wo-header-meta .invoice-title {
    text-align: right;
  }
  .wo-header-meta .invoice-title > span {
    display: inline-flex;
    flex-wrap: nowrap;
    white-space: nowrap;
    word-break: keep-all;
  }
  .wo-header-meta .invoice-title .invoice-title-stack > span:first-child {
    font-size: 1.85rem;
    line-height: 1.05;
  }
  .wo-header-meta .invoice-date-wrapper {
    justify-content: flex-end;
  }
  .wo-header-meta .invoice-date-title {
    min-width: 8rem;
    text-align: right;
    white-space: nowrap;
  }
  .wo-header-meta .invoice-date {
    white-space: nowrap;
  }
  .wo-header-qr-block {
    display: flex;
    align-items: flex-start;
    justify-content: flex-end;
  }
  .wo-preview-qr-image {
    width: 120px;
    height: 120px;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 8px;
    background: #fff;
  }
  .wo-sidebar-qr-block {
    display: none;
    justify-content: center;
    padding-bottom: 1.5rem;
  }
  .wo-sidebar-qr-divider {
    display: none;
    margin: 0 0 1.5rem;
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
  .wo-section-shell {
    border: 1px solid #ebe9f1;
    border-radius: 10px;
    background: linear-gradient(180deg, rgba(245, 247, 250, 0.6) 0%, rgba(255, 255, 255, 1) 100%);
    padding: 1rem;
    margin-top: 0.25rem;
    margin-bottom: 1rem;
  }
  .wo-progress-shell {
    border: 1px solid #ebe9f1;
    border-radius: 10px;
    background: transparent;
    padding: 0.85rem 1rem;
    margin-top: 0.25rem;
    margin-bottom: 0.85rem;
  }
  .invoice-preview-wrapper .wo-sastavnica-table-wrap {
    position: relative;
    scrollbar-width: thin;
    scrollbar-color: var(--wo-table-scroll-thumb) var(--wo-table-scroll-track);
    scrollbar-gutter: stable;
  }
  .invoice-preview-wrapper .wo-sastavnica-table-wrap > .table {
    margin-bottom: 0;
    border-collapse: separate;
    border-spacing: 0;
  }
  .invoice-preview-wrapper .wo-sastavnica-table-wrap::-webkit-scrollbar {
    width: 8px;
    height: 8px;
  }
  .invoice-preview-wrapper .wo-sastavnica-table-wrap::-webkit-scrollbar-track {
    background: var(--wo-table-scroll-track);
    border-radius: 999px;
  }
  .invoice-preview-wrapper .wo-sastavnica-table-wrap::-webkit-scrollbar-thumb {
    background: var(--wo-table-scroll-thumb);
    border-radius: 999px;
    border: 1px solid var(--wo-table-scroll-thumb-border);
  }
  .invoice-preview-wrapper .wo-sastavnica-table-wrap::-webkit-scrollbar-thumb:hover {
    background: var(--wo-table-scroll-thumb-hover);
  }
  .invoice-preview-wrapper .wo-sastavnica-table-wrap::-webkit-scrollbar-thumb:active {
    background: var(--wo-table-scroll-thumb-active);
  }
  .invoice-preview-wrapper .wo-sastavnica-table-wrap::-webkit-scrollbar-corner {
    background: var(--wo-table-scroll-track);
  }
  .invoice-preview-wrapper #sastavnica-table .wo-sastavnica-action-col {
    position: sticky;
    right: 0;
    z-index: 2;
    background-color: #ffffff;
    box-shadow: none;
    border-left: 1px solid var(--wo-divider-color);
  }
  .invoice-preview-wrapper #sastavnica-table thead .wo-sastavnica-action-col {
    z-index: 3;
    background-color: #f8f8fa;
  }
  body.dark-layout .invoice-preview-wrapper #sastavnica-table .wo-sastavnica-action-col,
  body.semi-dark-layout .invoice-preview-wrapper #sastavnica-table .wo-sastavnica-action-col,
  .dark-layout .invoice-preview-wrapper #sastavnica-table .wo-sastavnica-action-col,
  .semi-dark-layout .invoice-preview-wrapper #sastavnica-table .wo-sastavnica-action-col {
    background-color: #283046;
  }
  body.dark-layout .invoice-preview-wrapper #sastavnica-table thead .wo-sastavnica-action-col,
  body.semi-dark-layout .invoice-preview-wrapper #sastavnica-table thead .wo-sastavnica-action-col,
  .dark-layout .invoice-preview-wrapper #sastavnica-table thead .wo-sastavnica-action-col,
  .semi-dark-layout .invoice-preview-wrapper #sastavnica-table thead .wo-sastavnica-action-col {
    background-color: #2f3854;
  }
  .wo-progress-shell .wo-progress-head {
    align-items: baseline;
    font-size: 0.95rem;
    font-weight: 600;
    color: #5e5873;
    margin-bottom: 0.45rem;
  }
  .wo-progress-shell .wo-progress-head span:last-child {
    font-size: 1.1rem;
  }
  .wo-progress-shell .wo-progress {
    height: 9px;
    background-color: #e9edf3;
  }
  .wo-product-hero {
    border: 1px solid #ebe9f1;
    border-radius: 10px;
    background: linear-gradient(180deg, rgba(115, 103, 240, 0.08) 0%, rgba(255, 255, 255, 1) 100%);
    padding: 0.85rem 1rem;
    margin-bottom: 0.85rem;
  }
  .wo-product-hero-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    width: 100%;
  }
  .wo-product-hero-main {
    flex: 1 1 auto;
    min-width: 0;
  }
  .wo-product-kicker {
    display: block;
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #6e6b7b;
    margin-bottom: 0.3rem;
    font-weight: 600;
  }
  .wo-product-title {
    display: block;
    font-size: 1.15rem;
    line-height: 1.3;
    font-weight: 700;
    color: #5e5873;
  }
  .wo-product-code-accent {
    margin-top: 0.55rem;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    border-radius: 9px;
    padding: 0.36rem 0.72rem 0.36rem 0.56rem;
    background: linear-gradient(90deg, rgba(40, 199, 111, 0.2) 0%, rgba(40, 199, 111, 0.06) 68%, rgba(40, 199, 111, 0) 100%);
    box-shadow: inset 0 0 0 1px rgba(40, 199, 111, 0.34);
  }
  .wo-product-code-accent::before {
    content: '';
    width: 0.45rem;
    height: 0.45rem;
    border-radius: 999px;
    background: #28c76f;
    box-shadow: 0 0 0 4px rgba(40, 199, 111, 0.2);
    flex: 0 0 auto;
  }
  .wo-product-code-label {
    font-size: 0.64rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    font-weight: 700;
    color: #1b8f4c;
    line-height: 1;
  }
  .wo-product-code-value {
    font-size: 0.82rem;
    font-weight: 800;
    letter-spacing: 0.02em;
    line-height: 1;
    color: #28c76f;
  }
  .wo-product-qty {
    margin-left: auto;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    justify-content: flex-start;
    text-align: right;
    flex: 0 0 auto;
    white-space: nowrap;
  }
  .wo-product-qty-label {
    font-size: 0.64rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-weight: 700;
    color: #6e6b7b;
    line-height: 1;
    margin-bottom: 0.3rem;
  }
  .wo-product-qty-metric {
    display: inline-flex;
    align-items: baseline;
    justify-content: flex-end;
    gap: 0.35rem;
  }
  .wo-product-qty-value {
    font-size: 1.35rem;
    line-height: 1;
    font-weight: 800;
    color: #5e5873;
    letter-spacing: 0.01em;
  }
  .wo-product-qty-unit {
    font-size: 0.82rem;
    font-weight: 700;
    color: #6e6b7b;
    letter-spacing: 0.04em;
  }
  .wo-chip-shell {
    border: 1px solid #ebe9f1;
    border-radius: 10px;
    background: #fff;
    padding: 0.7rem 0.85rem;
    margin-bottom: 0.9rem;
    padding-left: 0!important;
  
  }
  .wo-kpi-shell {
    border: 1px solid #ebe9f1;
    border-radius: 10px;
    background: #fff;
    padding: 0.9rem 1rem 1rem;
    margin-bottom: 1rem;
  }
  .wo-kpi-shell .wo-kpi-grid {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
  .wo-subtle-tabs-pane {
    border: 1px solid #ebe9f1;
    border-top: 0;
    border-radius: 0 0 10px 10px;
    padding: 0.85rem 1rem;
    background: #fff;
    padding-top: 0!important;
  }
  .wo-timeline-pane .timeline {
    margin-bottom: 0;
  }
  .wo-note-pane {
    padding-top: 1rem;
    padding-bottom: 1rem;
  }
  .wo-note-readonly {
    min-height: 220px;
    border: 1px solid var(--wo-divider-color);
    border-radius: 10px;
    background: linear-gradient(180deg, rgba(245, 247, 250, 0.9) 0%, #fff 100%);
    padding: 1rem 1.1rem;
    color: #5e5873;
    font-size: 0.95rem;
    line-height: 1.65;
    white-space: pre-wrap;
    word-break: break-word;
  }
  .wo-note-empty {
    border: 1px dashed var(--wo-divider-color);
    border-radius: 10px;
    padding: 1rem 1.1rem;
    color: #6e6b7b;
    font-size: 0.95rem;
    background: rgba(245, 247, 250, 0.55);
  }
  .wo-timeline-pane .timeline .timeline-item .timeline-event {
    min-height: 3rem;
    padding-bottom: 0.75rem;
  }
  .wo-timeline-pane .timeline .timeline-item .timeline-event h6 {
    margin-bottom: 0;
    font-size: 0.9rem;
  }
  .wo-timeline-pane .timeline .timeline-item .timeline-event .timeline-event-time {
    font-weight: 600;
  }
  .wo-links-pane {
    padding-top: 1rem;
    padding-bottom: 1rem;
  }
  .wo-links-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.75rem;
  }
  .wo-link-card {
    position: relative;
    border: 1px solid var(--wo-divider-color);
    border-radius: 10px;
    background: linear-gradient(180deg, rgba(245, 247, 250, 0.8) 0%, #fff 100%);
    padding: 0.72rem 0.8rem 0.78rem;
    overflow: hidden;
  }
  .wo-link-card::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: #7367f0;
  }
  .wo-link-head {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    margin-bottom: 0.45rem;
  }
  .wo-link-icon {
    width: 26px;
    height: 26px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(115, 103, 240, 0.14);
    color: #7367f0;
    font-size: 0.8rem;
    flex: 0 0 auto;
  }
  .wo-link-label {
    font-size: 0.86rem;
    color: #6e6b7b;
    font-weight: 600;
    line-height: 1.2;
  }
  .wo-link-value {
    font-size: 1.05rem;
    color: #5e5873;
    font-weight: 700;
    line-height: 1.25;
    word-break: break-word;
    letter-spacing: 0.01em;
  }
  .wo-link-value.wo-link-empty {
    color: #b9b7c4;
    font-weight: 600;
  }
  .wo-link-tone-primary::before { background: #7367f0; }
  .wo-link-tone-primary .wo-link-icon { background: rgba(115, 103, 240, 0.14); color: #7367f0; }
  .wo-link-tone-info::before { background: #00cfe8; }
  .wo-link-tone-info .wo-link-icon { background: rgba(0, 207, 232, 0.14); color: #00cfe8; }
  .wo-link-tone-success::before { background: #28c76f; }
  .wo-link-tone-success .wo-link-icon { background: rgba(40, 199, 111, 0.14); color: #28c76f; }
  .wo-link-tone-warning::before { background: #ff9f43; }
  .wo-link-tone-warning .wo-link-icon { background: rgba(255, 159, 67, 0.14); color: #ff9f43; }
  .wo-link-tone-danger::before { background: #ea5455; }
  .wo-link-tone-danger .wo-link-icon { background: rgba(234, 84, 85, 0.14); color: #ea5455; }
  .wo-link-tone-secondary::before { background: #82868b; }
  .wo-link-tone-secondary .wo-link-icon { background: rgba(130, 134, 139, 0.16); color: #82868b; }
  .wo-links-empty {
    border: 1px solid var(--wo-divider-color);
    border-radius: 10px;
    padding: 0.95rem 1rem;
    color: #6e6b7b;
    font-size: 0.92rem;
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
    border: 1px solid var(--wo-divider-color);
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
    position: relative;
  }
  .wo-progress-bar {
    height: 100%;
    width: 0;
    border-radius: 999px;
    background: linear-gradient(90deg, #00cfe8 0%, #28c76f 100%);
    transition: width 1.25s cubic-bezier(0.22, 1, 0.36, 1), filter 0.2s ease;
    position: relative;
    will-change: width;
  }
  .wo-progress-bar::after {
    content: '';
    position: absolute;
    top: 0;
    bottom: 0;
    width: 34%;
    min-width: 36px;
    background: linear-gradient(90deg, transparent 0%, rgba(255, 255, 255, 0.65) 50%, transparent 100%);
    transform: translateX(-150%);
    opacity: 0;
    pointer-events: none;
  }
  .wo-progress.wo-progress-charging .wo-progress-bar {
    filter: brightness(1.04) saturate(1.15);
  }
  .wo-progress.wo-progress-live .wo-progress-bar::after {
    opacity: 0.8;
    animation: wo-progress-sheen 2.8s linear infinite;
  }
  @keyframes wo-progress-sheen {
    0% {
      transform: translateX(-150%);
    }
    100% {
      transform: translateX(320%);
    }
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
    padding: 0.3rem 0.65rem;
    border: 1px solid rgba(110, 107, 123, 0.35);
    background-color: rgba(110, 107, 123, 0.08);
    font-size: 0.78rem;
    line-height: 1;
    color: #5e5873;
    white-space: nowrap;
  }
  .wo-flag-dot {
    width: 7px;
    height: 7px;
    border-radius: 999px;
    background: #6e6b7b;
  }
  .wo-flag-success .wo-flag-dot { background: #28c76f; }
  .wo-flag-danger .wo-flag-dot { background: #ea5455; }
  .wo-flag-secondary .wo-flag-dot { background: #6e6b7b; }
  .wo-flag-info .wo-flag-dot { background: #00cfe8; }

  body:not(.dark-layout):not(.semi-dark-layout) .invoice-preview-wrapper .nav.nav-tabs .nav-link:not(.active):not([aria-selected='true']) {
    color: #9aa0b5;
  }
  body:not(.dark-layout):not(.semi-dark-layout) .invoice-preview-wrapper .nav.nav-tabs .nav-link:hover:not(.active):not([aria-selected='true']) {
    color: #8b92aa;
  }

  body.dark-layout .invoice-preview-wrapper .invoice-actions.invoice-actions-scrolled .card {
    box-shadow: 0 10px 24px rgba(0, 0, 0, 0.35);
  }
  body.dark-layout .invoice-actions-divider {
    border-top-color: var(--wo-divider-color);
  }
  body.dark-layout .invoice-preview-wrapper .logo-wrapper .invoice-logo,
  body.semi-dark-layout .invoice-preview-wrapper .logo-wrapper .invoice-logo,
  .dark-layout .invoice-preview-wrapper .logo-wrapper .invoice-logo,
  .semi-dark-layout .invoice-preview-wrapper .logo-wrapper .invoice-logo {
    color: #eaf0ff !important;
  }
  body.dark-layout .invoice-preview-wrapper .invoice-actions .btn.btn-outline-primary,
  body.dark-layout .invoice-preview-wrapper .invoice-actions .btn.btn-outline-secondary,
  body.semi-dark-layout .invoice-preview-wrapper .invoice-actions .btn.btn-outline-primary,
  body.semi-dark-layout .invoice-preview-wrapper .invoice-actions .btn.btn-outline-secondary,
  .dark-layout .invoice-preview-wrapper .invoice-actions .btn.btn-outline-primary,
  .dark-layout .invoice-preview-wrapper .invoice-actions .btn.btn-outline-secondary,
  .semi-dark-layout .invoice-preview-wrapper .invoice-actions .btn.btn-outline-primary,
  .semi-dark-layout .invoice-preview-wrapper .invoice-actions .btn.btn-outline-secondary {
    border-color: rgba(255, 255, 255, 0.9) !important;
    color: #ffffff !important;
    background-color: transparent !important;
  }
  body.dark-layout .invoice-preview-wrapper .invoice-actions .btn.btn-outline-primary i,
  body.dark-layout .invoice-preview-wrapper .invoice-actions .btn.btn-outline-secondary i,
  body.semi-dark-layout .invoice-preview-wrapper .invoice-actions .btn.btn-outline-primary i,
  body.semi-dark-layout .invoice-preview-wrapper .invoice-actions .btn.btn-outline-secondary i,
  .dark-layout .invoice-preview-wrapper .invoice-actions .btn.btn-outline-primary i,
  .dark-layout .invoice-preview-wrapper .invoice-actions .btn.btn-outline-secondary i,
  .semi-dark-layout .invoice-preview-wrapper .invoice-actions .btn.btn-outline-primary i,
  .semi-dark-layout .invoice-preview-wrapper .invoice-actions .btn.btn-outline-secondary i {
    color: #ffffff !important;
  }
  body.dark-layout .invoice-preview-wrapper .invoice-actions .btn.btn-outline-primary:hover,
  body.dark-layout .invoice-preview-wrapper .invoice-actions .btn.btn-outline-primary:focus,
  body.dark-layout .invoice-preview-wrapper .invoice-actions .btn.btn-outline-secondary:hover,
  body.dark-layout .invoice-preview-wrapper .invoice-actions .btn.btn-outline-secondary:focus,
  body.semi-dark-layout .invoice-preview-wrapper .invoice-actions .btn.btn-outline-primary:hover,
  body.semi-dark-layout .invoice-preview-wrapper .invoice-actions .btn.btn-outline-primary:focus,
  body.semi-dark-layout .invoice-preview-wrapper .invoice-actions .btn.btn-outline-secondary:hover,
  body.semi-dark-layout .invoice-preview-wrapper .invoice-actions .btn.btn-outline-secondary:focus,
  .dark-layout .invoice-preview-wrapper .invoice-actions .btn.btn-outline-primary:hover,
  .dark-layout .invoice-preview-wrapper .invoice-actions .btn.btn-outline-primary:focus,
  .dark-layout .invoice-preview-wrapper .invoice-actions .btn.btn-outline-secondary:hover,
  .dark-layout .invoice-preview-wrapper .invoice-actions .btn.btn-outline-secondary:focus,
  .semi-dark-layout .invoice-preview-wrapper .invoice-actions .btn.btn-outline-primary:hover,
  .semi-dark-layout .invoice-preview-wrapper .invoice-actions .btn.btn-outline-primary:focus,
  .semi-dark-layout .invoice-preview-wrapper .invoice-actions .btn.btn-outline-secondary:hover,
  .semi-dark-layout .invoice-preview-wrapper .invoice-actions .btn.btn-outline-secondary:focus {
    background-color: rgba(255, 255, 255, 0.06) !important;
    color: #ffffff !important;
    border-color: #ffffff !important;
  }
  body.dark-layout .invoice-preview-wrapper .logo-wrapper .wo-brand-logo,
  body.semi-dark-layout .invoice-preview-wrapper .logo-wrapper .wo-brand-logo,
  .dark-layout .invoice-preview-wrapper .logo-wrapper .wo-brand-logo,
  .semi-dark-layout .invoice-preview-wrapper .logo-wrapper .wo-brand-logo {
    box-shadow: none !important;
    filter: brightness(1.1) contrast(1.05);
  }
  body.dark-layout .wo-product-hero {
    border-color: #444e6e;
    background: linear-gradient(180deg, rgba(115, 103, 240, 0.26) 0%, rgba(40, 48, 70, 0.96) 100%);
  }
  body.dark-layout .wo-product-kicker {
    color: #c1c5d8;
  }
  body.dark-layout .wo-product-title {
    color: #f1f3f9;
  }
  body.dark-layout .wo-product-code-accent {
    background: linear-gradient(90deg, rgba(40, 199, 111, 0.28) 0%, rgba(40, 199, 111, 0.09) 68%, rgba(40, 199, 111, 0) 100%);
    box-shadow: inset 0 0 0 1px rgba(40, 199, 111, 0.4);
  }
  body.dark-layout .wo-product-code-label {
    color: #91eeb8;
  }
  body.dark-layout .wo-product-code-value {
    color: #28c76f;
    text-shadow: 0 0 10px rgba(40, 199, 111, 0.18);
  }
  body.dark-layout .wo-product-qty-label,
  body.semi-dark-layout .wo-product-qty-label,
  .dark-layout .invoice-preview-wrapper .wo-product-qty-label,
  .semi-dark-layout .invoice-preview-wrapper .wo-product-qty-label {
    color: #c1c5d8;
  }
  body.dark-layout .wo-product-qty-value,
  body.semi-dark-layout .wo-product-qty-value,
  .dark-layout .invoice-preview-wrapper .wo-product-qty-value,
  .semi-dark-layout .invoice-preview-wrapper .wo-product-qty-value {
    color: #f1f3f9;
  }
  body.dark-layout .wo-product-qty-unit,
  body.semi-dark-layout .wo-product-qty-unit,
  .dark-layout .invoice-preview-wrapper .wo-product-qty-unit,
  .semi-dark-layout .invoice-preview-wrapper .wo-product-qty-unit {
    color: #c1c5d8;
  }
  body.dark-layout .wo-note-readonly,
  body.semi-dark-layout .wo-note-readonly,
  .dark-layout .invoice-preview-wrapper .wo-note-readonly,
  .semi-dark-layout .invoice-preview-wrapper .wo-note-readonly {
    background: rgba(255, 255, 255, 0.04);
    border-color: #444e6e;
    color: #f1f3f9;
  }
  body.dark-layout .wo-note-empty,
  body.semi-dark-layout .wo-note-empty,
  .dark-layout .invoice-preview-wrapper .wo-note-empty,
  .semi-dark-layout .invoice-preview-wrapper .wo-note-empty {
    background: rgba(255, 255, 255, 0.03);
    border-color: #444e6e;
    color: #c1c5d8;
  }
  body.dark-layout .wo-progress-shell {
    background: transparent;
    border-color: #444e6e;
  }
  body.dark-layout .wo-progress-shell .wo-progress-head {
    color: #e1e4ef;
  }
  body.dark-layout .wo-progress-shell .wo-progress-head span:first-child,
  body.semi-dark-layout .wo-progress-shell .wo-progress-head span:first-child,
  .dark-layout .invoice-preview-wrapper .wo-progress-shell .wo-progress-head span:first-child,
  .semi-dark-layout .invoice-preview-wrapper .wo-progress-shell .wo-progress-head span:first-child {
    color: #ffffff !important;
  }
  body.dark-layout .wo-progress-shell .wo-progress-head span:last-child,
  body.semi-dark-layout .wo-progress-shell .wo-progress-head span:last-child,
  .dark-layout .invoice-preview-wrapper .wo-progress-shell .wo-progress-head span:last-child,
  .semi-dark-layout .invoice-preview-wrapper .wo-progress-shell .wo-progress-head span:last-child {
    color: #ffffff !important;
  }
  body.dark-layout .wo-progress-shell .wo-progress {
    background-color: #3a4461;
  }
  body.dark-layout .wo-chip-shell {
    background: #1f2940;
    border-color: #444e6e;
  }
  body.dark-layout .wo-meta-chip {
    border-color: #4a5373;
    background-color: #283454;
  }
  body.dark-layout .wo-meta-chip-label {
    color: #bdc2d6;
  }
  body.dark-layout .wo-meta-chip-value {
    color: #eef1fa;
  }
  body.dark-layout .wo-flag-pill {
    border-color: #4a5373;
    background: #283454;
    color: #d7dced;
  }
  body.dark-layout .wo-subtle-tabs-pane {
    border-color: #444e6e;
    background: #1f2940;
  }
  body.dark-layout .wo-kpi-shell {
    border-color: #445071;
    background: #252f49;
  }
  body.dark-layout .wo-kpi-item {
    border-color: var(--wo-divider-color);
    background-color: rgba(115, 103, 240, 0.12);
  }
  body.dark-layout .wo-kpi-label {
    color: #b9bfd4;
  }
  body.dark-layout .wo-kpi-value {
    color: #edf1fa;
  }
  body.dark-layout .wo-kpi-unit {
    color: #c3c9db;
  }
  body.dark-layout .wo-timeline-pane .timeline .timeline-item {
    border-left-color: #4b5678;
  }
  body.dark-layout .wo-timeline-pane .timeline .timeline-item .timeline-event h6 {
    color: #e7ebf7;
  }
  body.dark-layout .wo-timeline-pane .timeline .timeline-item .timeline-event .timeline-event-time {
    color: #c6ccde;
  }
  body.dark-layout .wo-link-card {
    border-color: #465274;
    background: linear-gradient(180deg, rgba(48, 61, 92, 0.95) 0%, rgba(34, 44, 69, 0.98) 100%);
  }
  body.dark-layout .wo-link-label {
    color: #c7cce0;
  }
  body.dark-layout .wo-link-value {
    color: #f2f5fb;
  }
  body.dark-layout .wo-link-value.wo-link-empty {
    color: #8f96b0;
  }
  body.dark-layout .wo-links-empty {
    border-color: #4a5474;
    background: #283454;
    color: #c7cce0;
  }
  body.dark-layout .wo-meta-row {
    border-bottom-color: rgba(184, 190, 220, 0.22);
  }
  body.dark-layout .wo-meta-key {
    color: #c2c8db;
  }
  body.dark-layout .wo-meta-value {
    color: #eef1fa;
  }
  body.dark-layout .nav-tabs .nav-link {
    color: #c0c6da;
  }
  body.dark-layout .nav-tabs .nav-link.active {
    color: #f2f5fb;
  }

  /* Force transparent surfaces in dark variants to avoid white panels */
  body.dark-layout .invoice-preview-wrapper .tab-content,
  body.dark-layout .invoice-preview-wrapper .tab-pane,
  body.dark-layout .invoice-preview-wrapper .wo-product-hero,
  body.dark-layout .invoice-preview-wrapper .wo-progress-shell,
  body.dark-layout .invoice-preview-wrapper .wo-chip-shell,
  body.dark-layout .invoice-preview-wrapper .wo-subtle-tabs-pane,
  body.dark-layout .invoice-preview-wrapper .wo-kpi-shell,
  body.dark-layout .invoice-preview-wrapper .wo-kpi-item,
  body.dark-layout .invoice-preview-wrapper .wo-meta-chip,
  body.dark-layout .invoice-preview-wrapper .wo-link-card,
  body.dark-layout .invoice-preview-wrapper .wo-links-empty,
  body.semi-dark-layout .invoice-preview-wrapper .tab-content,
  body.semi-dark-layout .invoice-preview-wrapper .tab-pane,
  body.semi-dark-layout .invoice-preview-wrapper .wo-product-hero,
  body.semi-dark-layout .invoice-preview-wrapper .wo-progress-shell,
  body.semi-dark-layout .invoice-preview-wrapper .wo-chip-shell,
  body.semi-dark-layout .invoice-preview-wrapper .wo-subtle-tabs-pane,
  body.semi-dark-layout .invoice-preview-wrapper .wo-kpi-shell,
  body.semi-dark-layout .invoice-preview-wrapper .wo-kpi-item,
  body.semi-dark-layout .invoice-preview-wrapper .wo-meta-chip,
  body.semi-dark-layout .invoice-preview-wrapper .wo-link-card,
  body.semi-dark-layout .invoice-preview-wrapper .wo-links-empty {
    background: transparent !important;
    background-color: transparent !important;
  }

  body.semi-dark-layout .invoice-preview-wrapper .invoice-actions-divider,
  body.semi-dark-layout .invoice-preview-wrapper .wo-meta-row {
    border-color: var(--wo-divider-color) !important;
  }
  body.semi-dark-layout .invoice-preview-wrapper .wo-progress-shell,
  body.semi-dark-layout .invoice-preview-wrapper .wo-chip-shell,
  body.semi-dark-layout .invoice-preview-wrapper .wo-subtle-tabs-pane,
  body.semi-dark-layout .invoice-preview-wrapper .wo-kpi-shell,
  body.semi-dark-layout .invoice-preview-wrapper .wo-link-card,
  body.semi-dark-layout .invoice-preview-wrapper .wo-links-empty,
  body.semi-dark-layout .invoice-preview-wrapper .wo-meta-chip,
  body.semi-dark-layout .invoice-preview-wrapper .wo-flag-pill {
    border-color: #465274 !important;
  }
  body.semi-dark-layout .invoice-preview-wrapper .wo-product-kicker,
  body.semi-dark-layout .invoice-preview-wrapper .wo-product-sub,
  body.semi-dark-layout .invoice-preview-wrapper .wo-kpi-label,
  body.semi-dark-layout .invoice-preview-wrapper .wo-meta-key,
  body.semi-dark-layout .invoice-preview-wrapper .wo-link-label,
  body.semi-dark-layout .invoice-preview-wrapper .timeline-event-time,
  body.semi-dark-layout .invoice-preview-wrapper .wo-meta-chip-label {
    color: #c4cade !important;
  }
  body.semi-dark-layout .invoice-preview-wrapper .wo-product-title,
  body.semi-dark-layout .invoice-preview-wrapper .wo-kpi-value,
  body.semi-dark-layout .invoice-preview-wrapper .wo-meta-value,
  body.semi-dark-layout .invoice-preview-wrapper .wo-link-value,
  body.semi-dark-layout .invoice-preview-wrapper .wo-meta-chip-value,
  body.semi-dark-layout .invoice-preview-wrapper .wo-flag-pill,
  body.semi-dark-layout .invoice-preview-wrapper .timeline-event h6 {
    color: #eef2fb !important;
  }

  /* Generic dark selectors in case dark class is not on body */
  .dark-layout .invoice-preview-wrapper .tab-content,
  .dark-layout .invoice-preview-wrapper .tab-pane,
  .dark-layout .invoice-preview-wrapper .wo-product-hero,
  .dark-layout .invoice-preview-wrapper .wo-progress-shell,
  .dark-layout .invoice-preview-wrapper .wo-chip-shell,
  .dark-layout .invoice-preview-wrapper .wo-subtle-tabs-pane,
  .dark-layout .invoice-preview-wrapper .wo-kpi-shell,
  .dark-layout .invoice-preview-wrapper .wo-kpi-item,
  .dark-layout .invoice-preview-wrapper .wo-meta-chip,
  .dark-layout .invoice-preview-wrapper .wo-link-card,
  .dark-layout .invoice-preview-wrapper .wo-links-empty,
  .semi-dark-layout .invoice-preview-wrapper .tab-content,
  .semi-dark-layout .invoice-preview-wrapper .tab-pane,
  .semi-dark-layout .invoice-preview-wrapper .wo-product-hero,
  .semi-dark-layout .invoice-preview-wrapper .wo-progress-shell,
  .semi-dark-layout .invoice-preview-wrapper .wo-chip-shell,
  .semi-dark-layout .invoice-preview-wrapper .wo-subtle-tabs-pane,
  .semi-dark-layout .invoice-preview-wrapper .wo-kpi-shell,
  .semi-dark-layout .invoice-preview-wrapper .wo-kpi-item,
  .semi-dark-layout .invoice-preview-wrapper .wo-meta-chip,
  .semi-dark-layout .invoice-preview-wrapper .wo-link-card,
  .semi-dark-layout .invoice-preview-wrapper .wo-links-empty {
    background: transparent !important;
    background-image: none !important;
    background-color: transparent !important;
  }
  .dark-layout .invoice-preview-wrapper .wo-product-kicker,
  .dark-layout .invoice-preview-wrapper .wo-product-sub,
  .dark-layout .invoice-preview-wrapper .wo-kpi-label,
  .dark-layout .invoice-preview-wrapper .wo-meta-key,
  .dark-layout .invoice-preview-wrapper .wo-link-label,
  .dark-layout .invoice-preview-wrapper .timeline-event-time,
  .dark-layout .invoice-preview-wrapper .wo-meta-chip-label,
  .semi-dark-layout .invoice-preview-wrapper .wo-product-kicker,
  .semi-dark-layout .invoice-preview-wrapper .wo-product-sub,
  .semi-dark-layout .invoice-preview-wrapper .wo-kpi-label,
  .semi-dark-layout .invoice-preview-wrapper .wo-meta-key,
  .semi-dark-layout .invoice-preview-wrapper .wo-link-label,
  .semi-dark-layout .invoice-preview-wrapper .timeline-event-time,
  .semi-dark-layout .invoice-preview-wrapper .wo-meta-chip-label {
    color: #c4cade !important;
  }
  .dark-layout .invoice-preview-wrapper .wo-product-title,
  .dark-layout .invoice-preview-wrapper .wo-kpi-value,
  .dark-layout .invoice-preview-wrapper .wo-meta-value,
  .dark-layout .invoice-preview-wrapper .wo-link-value,
  .dark-layout .invoice-preview-wrapper .wo-meta-chip-value,
  .dark-layout .invoice-preview-wrapper .wo-flag-pill,
  .dark-layout .invoice-preview-wrapper .timeline-event h6,
  .semi-dark-layout .invoice-preview-wrapper .wo-product-title,
  .semi-dark-layout .invoice-preview-wrapper .wo-kpi-value,
  .semi-dark-layout .invoice-preview-wrapper .wo-meta-value,
  .semi-dark-layout .invoice-preview-wrapper .wo-link-value,
  .semi-dark-layout .invoice-preview-wrapper .wo-meta-chip-value,
  .semi-dark-layout .invoice-preview-wrapper .wo-flag-pill,
  .semi-dark-layout .invoice-preview-wrapper .timeline-event h6 {
    color: #eef2fb !important;
  }
  body.dark-layout .invoice-preview-wrapper .wo-product-code-label,
  body.semi-dark-layout .invoice-preview-wrapper .wo-product-code-label,
  .dark-layout .invoice-preview-wrapper .wo-product-code-label,
  .semi-dark-layout .invoice-preview-wrapper .wo-product-code-label {
    color: #91eeb8 !important;
  }
  body.dark-layout .invoice-preview-wrapper .wo-product-code-value,
  body.semi-dark-layout .invoice-preview-wrapper .wo-product-code-value,
  .dark-layout .invoice-preview-wrapper .wo-product-code-value,
  .semi-dark-layout .invoice-preview-wrapper .wo-product-code-value {
    color: #28c76f !important;
    text-shadow: 0 0 10px rgba(40, 199, 111, 0.18);
  }
  .swal2-popup.wo-swal-dark {
    background: #283046 !important;
    color: #d0d2d6 !important;
  }
  .swal2-popup.wo-swal-dark .swal2-title,
  .swal2-popup.wo-swal-dark .swal2-html-container,
  .swal2-popup.wo-swal-dark .swal2-content {
    color: #d0d2d6 !important;
  }
  .swal2-popup.wo-scan-swal-popup {
    overflow: hidden;
    --wo-scan-progress-duration: 3000ms;
  }
  .swal2-popup .wo-scan-swal-progress {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    height: 4px;
    background: rgba(0, 0, 0, 0.12);
    pointer-events: none;
  }
  .swal2-popup .wo-scan-swal-progress-fill {
    width: 0;
    height: 100%;
    background: #28c76f;
    transition: width var(--wo-scan-progress-duration) linear;
  }
  .swal2-popup.wo-swal-dark .wo-scan-swal-progress {
    background: rgba(255, 255, 255, 0.12);
  }
  .swal2-popup.wo-swal-dark .wo-scan-swal-progress-fill {
    background: #28c76f;
  }
  .dark-layout .invoice-preview-wrapper .nav.nav-tabs .nav-link,
  .semi-dark-layout .invoice-preview-wrapper .nav.nav-tabs .nav-link,
  body.dark-layout .invoice-preview-wrapper .nav.nav-tabs .nav-link,
  body.semi-dark-layout .invoice-preview-wrapper .nav.nav-tabs .nav-link {
    color: #9aa2be !important;
  }
  .dark-layout .invoice-preview-wrapper .nav.nav-tabs .nav-link:hover:not(.active),
  .semi-dark-layout .invoice-preview-wrapper .nav.nav-tabs .nav-link:hover:not(.active),
  body.dark-layout .invoice-preview-wrapper .nav.nav-tabs .nav-link:hover:not(.active),
  body.semi-dark-layout .invoice-preview-wrapper .nav.nav-tabs .nav-link:hover:not(.active) {
    color: #b3bbd4 !important;
  }
  .dark-layout .invoice-preview-wrapper .nav.nav-tabs .nav-link.active,
  .semi-dark-layout .invoice-preview-wrapper .nav.nav-tabs .nav-link.active,
  body.dark-layout .invoice-preview-wrapper .nav.nav-tabs .nav-link.active,
  body.semi-dark-layout .invoice-preview-wrapper .nav.nav-tabs .nav-link.active,
  .dark-layout .invoice-preview-wrapper .nav.nav-tabs .nav-link[aria-selected='true'],
  .semi-dark-layout .invoice-preview-wrapper .nav.nav-tabs .nav-link[aria-selected='true'],
  body.dark-layout .invoice-preview-wrapper .nav.nav-tabs .nav-link[aria-selected='true'],
  body.semi-dark-layout .invoice-preview-wrapper .nav.nav-tabs .nav-link[aria-selected='true'] {
    color: #ffffff !important;
  }

  /* Restore visible hero gradient in dark variants */
  body.dark-layout .invoice-preview-wrapper .wo-product-hero,
  body.semi-dark-layout .invoice-preview-wrapper .wo-product-hero,
  .dark-layout .invoice-preview-wrapper .wo-product-hero,
  .semi-dark-layout .invoice-preview-wrapper .wo-product-hero {
    background-image: linear-gradient(180deg, rgba(112, 134, 166, 0.24) 0%, rgba(74, 90, 114, 0.14) 56%, rgba(17, 19, 25, 0) 100%) !important;
    background-color: transparent !important;
  }

  /* No outer borders on new section wrappers */
  .invoice-preview-wrapper .wo-product-hero,
  .invoice-preview-wrapper .wo-chip-shell,
  .invoice-preview-wrapper .wo-kpi-shell,
  .invoice-preview-wrapper .wo-subtle-tabs-pane {
    border: 0 !important;
    box-shadow: none !important;
  }
  .invoice-preview-wrapper .wo-progress-shell {
    border: 1px solid var(--wo-divider-color) !important;
    box-shadow: none !important;
  }

  @media (max-width: 1200px) {
    .wo-meta-grid {
      grid-template-columns: 1fr;
    }
    .wo-kpi-shell .wo-kpi-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .wo-links-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }
  @media (min-width: 1200px) {
    .wo-preview-qr-image {
      width: 108px;
      height: 108px;
    }
  }
  @media (min-width: 768px) and (max-width: 1199.98px) {
    .invoice-actions .btn {
      font-size: 0.7rem;
    }
    .invoice-preview-wrapper .logo-wrapper .invoice-logo {
      font-size: 1.25rem;
    }
    .wo-header-details-row {
      flex-direction: row;
      flex-wrap: nowrap;
      gap: 0.9rem;
    }
    .wo-header-company-block {
      flex: 1 1 auto;
      min-width: 0;
    }
    .wo-header-right-column {
      flex: 0 0 auto;
    }
    .wo-header-meta .invoice-date-title {
      min-width: 7rem;
    }
    .wo-header-qr-block {
      display: none;
    }
    .wo-sidebar-qr-block {
      display: flex;
    }
    .wo-sidebar-qr-divider {
      display: block;
    }
  }
  @media (max-width: 767.98px) {
    .invoice-preview-wrapper .invoice-actions {
      position: static;
      top: auto;
    }
    .wo-header-details-row {
      gap: 0.75rem;
    }
    .wo-header-right-column {
      width: 100%;
    }
    .wo-header-main-row {
      justify-content: flex-end;
    }
    .wo-header-chip-stack {
      min-width: 0;
      width: 100%;
    }
    .wo-product-hero-row {
      flex-direction: column;
      align-items: stretch;
    }
    .wo-product-qty {
      width: 100%;
      align-items: flex-end;
    }
    .wo-kpi-shell .wo-kpi-grid {
      grid-template-columns: 1fr;
    }
    .wo-links-grid {
      grid-template-columns: 1fr;
    }
  }
  /* Compact portrait tuning for 480x774-class screens */
  @media (max-width: 480px) {
    .invoice-preview-wrapper {
      padding-bottom: calc(2.4rem + env(safe-area-inset-bottom));
    }
    .invoice-preview .invoice-padding {
      padding-left: 1rem;
      padding-right: 1rem;
    }
    .invoice-preview .table th:first-child,
    .invoice-preview .table td:first-child {
      padding-left: 1rem;
    }
    .wo-mobile-top-actions {
      display: block;
      position: fixed;
      left: max(0.75rem, env(safe-area-inset-left));
      right: max(0.75rem, env(safe-area-inset-right));
      bottom: max(0.75rem, env(safe-area-inset-bottom));
      z-index: 1035;
      margin-bottom: 0;
    }
    .wo-mobile-top-actions .card {
      margin: 0;
      border: 1px solid rgba(113, 130, 163, 0.18);
      background: rgba(255, 255, 255, 0.94);
      box-shadow: 0 12px 28px rgba(34, 41, 47, 0.16);
      backdrop-filter: blur(10px);
    }
    .wo-mobile-top-actions .card-body {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 0.55rem;
      padding: 0.55rem;
    }
    .wo-mobile-top-actions .btn {
      width: 100%;
      height: 44px;
      margin-bottom: 0 !important;
      padding: 0.5rem 0.3rem;
      font-size: 0.92rem;
      font-weight: 600;
      line-height: 1.12;
      white-space: nowrap;
    }
    .wo-mobile-top-actions .btn i {
      font-size: 1.05rem !important;
    }
    body.vertical-overlay-menu .sidenav-overlay.show {
      z-index: 1036 !important;
    }
    body.vertical-overlay-menu .main-menu,
    body.vertical-overlay-menu .main-menu.menu-fixed,
    body.vertical-overlay-menu.menu-open .main-menu,
    body.vertical-overlay-menu.menu-open .main-menu.menu-fixed {
      z-index: 1037 !important;
    }
    .scroll-top {
      display: none !important;
    }
    .invoice-preview-wrapper .wo-preview-actions-col {
      order: 0;
    }
    .invoice-preview-wrapper .wo-preview-main-col {
      order: 0;
    }
    .invoice-preview-wrapper .invoice-actions {
      position: static;
      top: auto;
    }
    .invoice-preview-wrapper .invoice-actions .wo-action-primary-pair,
    .invoice-preview-wrapper .invoice-actions .wo-action-primary-divider {
      display: none !important;
    }
    .invoice-preview-wrapper .invoice-actions .card {
      margin-bottom: 0.75rem;
    }
    .invoice-preview-wrapper .wo-sidebar-qr-block,
    .invoice-preview-wrapper .wo-sidebar-qr-divider {
      display: none !important;
    }
    .wo-header-brand-row {
      align-items: center;
      gap: 0.95rem;
    }
    .invoice-preview-wrapper .wo-header-brand-row .logo-wrapper {
      flex: 0 1 54%;
      min-width: 0;
      margin-bottom: 0;
    }
    .invoice-preview-wrapper .logo-wrapper .wo-brand-logo {
      width: 62px;
      height: 62px;
      min-width: 62px;
      min-height: 62px;
      flex: 0 0 62px;
      object-fit: contain;
      object-position: center;
    }
    .invoice-preview-wrapper .logo-wrapper .invoice-logo {
      margin-left: 0.82rem;
      font-size: 1.62rem;
      line-height: 1.02;
      white-space: nowrap;
    }
    .wo-preview-qr-image {
      width: 96px;
      height: 96px;
      padding: 6px;
    }
    .wo-header-details-row {
      flex-wrap: nowrap;
      align-items: flex-start;
      gap: 0.8rem;
    }
    .wo-header-company-block {
      flex: 0 1 43%;
      font-size: 0.73rem;
      line-height: 1.32;
      word-break: break-word;
    }
    .wo-header-company-block .card-text {
      font-size: inherit;
      line-height: inherit;
      margin-bottom: 0.2rem !important;
    }
    .wo-header-company-block .card-text:first-child {
      font-size: 0.82rem;
      font-weight: 600;
      margin-bottom: 0.32rem !important;
    }
    .wo-header-right-column {
      flex: 0 1 57%;
      width: auto;
      min-width: 0;
    }
    .wo-header-main-row {
      width: 100%;
      justify-content: flex-end;
    }
    .wo-header-meta {
      width: 100%;
    }
    .wo-header-meta .invoice-title {
      margin-top: 0;
      margin-bottom: 0.42rem !important;
    }
    .wo-header-meta .invoice-title .invoice-title-stack {
      gap: 0.4rem;
      align-items: flex-end;
      width: 100%;
    }
    .wo-header-meta .invoice-title .invoice-title-stack > span:first-child {
      font-size: 1.22rem;
      line-height: 1.03;
    }
    .invoice-preview .invoice-title .invoice-order-number {
      font-size: 0.86rem;
      line-height: 1.12;
    }
    .wo-header-meta .invoice-date-title {
      min-width: 5.35rem;
      font-size: 0.74rem;
    }
    .wo-header-meta .invoice-date {
      font-size: 0.74rem;
      margin-left: 0.3rem;
      white-space: nowrap;
    }
    .invoice-preview .invoice-date-wrapper {
      margin-bottom: 0rem !important;
    }
    .wo-contact-row {
      --bs-gutter-x: 0;
      display: flex;
      flex-wrap: nowrap;
      gap: 0.7rem;
      margin-left: 0;
      margin-right: 0;
    }
    .wo-contact-col {
      flex: 1 1 0;
      max-width: calc(50% - 0.35rem);
      min-width: 0;
    }
    .wo-contact-col h6 {
      font-size: 0.78rem;
      line-height: 1.28;
      word-break: break-word;
    }
    .wo-product-hero {
      padding: 0.8rem 0.9rem;
    }
    .wo-product-hero-row {
      flex-direction: row;
      align-items: center;
      gap: 0.7rem;
    }
    .wo-product-kicker {
      font-size: 0.62rem;
      margin-bottom: 0.22rem;
    }
    .wo-product-title {
      font-size: 1.06rem;
      line-height: 1.18;
    }
    .wo-product-code-accent {
      margin-top: 0.38rem;
      gap: 0.32rem;
      padding: 0.26rem 0.52rem 0.26rem 0.42rem;
    }
    .wo-product-code-accent::before {
      width: 0.36rem;
      height: 0.36rem;
      box-shadow: 0 0 0 3px rgba(40, 199, 111, 0.2);
    }
    .wo-product-code-label {
      font-size: 0.56rem;
    }
    .wo-product-code-value {
      font-size: 0.72rem;
    }
    .wo-product-qty {
      width: auto;
      margin-left: auto;
      align-items: flex-end;
    }
    .wo-product-qty-label {
      font-size: 0.6rem;
      margin-bottom: 0.18rem;
    }
    .wo-product-qty-value {
      font-size: 1.16rem;
    }
    .wo-product-qty-unit {
      font-size: 0.74rem;
    }
    .invoice-preview-wrapper .nav-align-top > .nav.nav-tabs {
      flex-wrap: nowrap;
      overflow-x: auto;
      overflow-y: hidden;
      padding-bottom: 0.2rem;
      -webkit-overflow-scrolling: touch;
      scrollbar-width: thin;
      scrollbar-color: var(--wo-table-scroll-thumb) var(--wo-table-scroll-track);
    }
    .invoice-preview-wrapper .nav-align-top > .nav.nav-tabs::-webkit-scrollbar {
      height: 6px;
    }
    .invoice-preview-wrapper .nav-align-top > .nav.nav-tabs::-webkit-scrollbar-track {
      background: var(--wo-table-scroll-track);
      border-radius: 999px;
    }
    .invoice-preview-wrapper .nav-align-top > .nav.nav-tabs::-webkit-scrollbar-thumb {
      background: var(--wo-table-scroll-thumb);
      border-radius: 999px;
      border: 1px solid var(--wo-table-scroll-thumb-border);
    }
    .invoice-preview-wrapper .nav-align-top > .nav.nav-tabs .nav-item {
      flex: 0 0 auto;
    }
    .invoice-preview-wrapper .nav-align-top > .nav.nav-tabs .nav-link {
      white-space: nowrap;
      font-size: 1.04rem;
      padding-left: 0.95rem;
      padding-right: 0.95rem;
    }
    .invoice-preview-wrapper .nav-align-top > .nav.nav-tabs .nav-link i {
      font-size: 1.04rem;
    }
  }
  @media (max-width: 767.98px) {
    .wo-other-options-toggle {
      display: flex !important;
      margin-bottom: 0 !important;
      font-weight: 600;
    }
    .invoice-preview-wrapper .wo-other-options-collapse.collapse:not(.show) {
      display: none;
    }
    .wo-other-options-shell .invoice-actions-divider {
      display: none;
    }
    .wo-other-options-collapse {
      padding-top: 0.75rem;
    }
  }
</style>
@endsection

@php
  $workOrder = is_array($workOrder ?? null) ? $workOrder : [];
  $hasLoadedWorkOrder = !empty($workOrder);
  $showPreviewQr = $hasLoadedWorkOrder;
  $invoiceNumber = $invoiceNumber ?? '';
  $invoiceNumberDisplay = $invoiceNumber;
  if (is_string($invoiceNumberDisplay) && $invoiceNumberDisplay !== '') {
    $invoiceDigits = preg_replace('/\D+/', '', $invoiceNumberDisplay);
    if (is_string($invoiceDigits) && strlen($invoiceDigits) === 13) {
      $invoiceNumberDisplay =
        substr($invoiceDigits, 0, 2) . '-' .
        substr($invoiceDigits, 2, 4) . '-' .
        substr($invoiceDigits, 6);
    }
  }
  $displayValue = static function ($value): string {
    if ($value === null) {
      return '-';
    }

    if (is_string($value)) {
      $trimmedValue = trim($value);
      return $trimmedValue !== '' ? $trimmedValue : '-';
    }

    return trim((string) $value) !== '' ? (string) $value : '-';
  };
  $displayNumber = static function ($value, int $precision = 3): string {
    if ($value === null || $value === '') {
      return '-';
    }

    if (!is_numeric((string) $value)) {
      $trimmedValue = trim((string) $value);
      return $trimmedValue !== '' ? $trimmedValue : '-';
    }

    $formatted = number_format((float) $value, $precision, '.', '');
    $formatted = rtrim(rtrim($formatted, '0'), '.');

    if ($formatted === '-0') {
      return '0';
    }

    return str_replace('.', ',', $formatted);
  };

  $invoiceNumberDisplay = $displayValue($invoiceNumberDisplay);
  $orderNumberDisplay = $workOrder['broj_narudzbe'] ?? '';
  if (
    is_string($orderNumberDisplay) &&
    $orderNumberDisplay !== ''
  ) {
    $orderDigits = preg_replace('/\D+/', '', $orderNumberDisplay);
    if (is_string($orderDigits) && strlen($orderDigits) === 13) {
      $orderNumberDisplay =
        substr($orderDigits, 0, 2) . '-' .
        substr($orderDigits, 2, 4) . '-' .
        substr($orderDigits, 6);
    }
  }
  $orderNumberDisplay = $displayValue($orderNumberDisplay);
  $orderPositionDisplay = $displayValue($workOrder['broj_pozicije_narudzbe'] ?? null);
  $hasOrderNumber = $orderNumberDisplay !== '-';
  $hasOrderPosition = $orderPositionDisplay !== '-';
  $orderNumberWithPositionDisplay = $orderNumberDisplay;
  if ($hasOrderNumber && $hasOrderPosition) {
    $orderNumberWithPositionDisplay .= ';' . $orderPositionDisplay;
  }
  $issueDate = $displayValue($issueDate ?? null);
  $plannedStartDate = $displayValue($plannedStartDate ?? null);
  $dueDate = $displayValue($dueDate ?? null);

  $senderRaw = $sender ?? [];
  $sender = [
    'name' => $displayValue($senderRaw['name'] ?? null),
    'address' => $displayValue($senderRaw['address'] ?? null),
    'phone' => $displayValue($senderRaw['phone'] ?? null),
    'email' => $displayValue($senderRaw['email'] ?? null),
  ];

  $recipientRaw = $recipient ?? [];
  $recipient = [
    'name' => $displayValue($recipientRaw['name'] ?? null),
    'address' => $displayValue($recipientRaw['address'] ?? null),
    'phone' => $displayValue($recipientRaw['phone'] ?? null),
    'email' => $displayValue($recipientRaw['email'] ?? null),
  ];
  $workOrderMeta = $workOrderMeta ?? [];
  $currentUser = auth()->user();
  $isBasicUserRole = $currentUser && strtolower((string) ($currentUser->role ?? '')) === 'user';
  $isAdminUser = $currentUser
    ? (method_exists($currentUser, 'isAdmin')
        ? (bool) $currentUser->isAdmin()
        : strtolower((string) ($currentUser->role ?? '')) === 'admin')
    : false;
  $showSastavnicaActions = !$isBasicUserRole;
  $sastavnicaEmptyColspan = $showSastavnicaActions ? 16 : 15;
  $workOrderMetaHighlights = $workOrderMeta['highlights'] ?? [];
  $workOrderMetaKpis = $workOrderMeta['kpis'] ?? [];
  $workOrderMetaTimeline = $workOrderMeta['timeline'] ?? [];
  $workOrderMetaTraceability = $workOrderMeta['traceability'] ?? [];
  $workOrderMetaFlags = $workOrderMeta['flags'] ?? [];
  $workOrderMetaProgress = $workOrderMeta['progress'] ?? ['label' => 'Realizacija', 'percent' => 0, 'display' => '0 %'];
  $workOrderMetaProgressPercent = max(0, min(100, (float) ($workOrderMetaProgress['percent'] ?? 0)));
  $workOrderNote = trim((string) ($workOrder['napomena_rn'] ?? ''));
  $normalizedWorkOrderNote = preg_replace("/\r\n?/", "\n", $workOrderNote);
  $workOrderNote = is_string($normalizedWorkOrderNote) ? $normalizedWorkOrderNote : $workOrderNote;
  $workOrderRouteId = trim((string) ($workOrder['id'] ?? $invoiceNumber ?? ''));
  if ($workOrderRouteId === '') {
    $workOrderRouteId = trim((string) ($invoiceNumber ?? ''));
  }
  $statusUpdateUrl = $hasLoadedWorkOrder ? route('app-invoice-update-status', ['id' => $workOrderRouteId]) : '';
  $priorityUpdateUrl = $hasLoadedWorkOrder ? route('app-invoice-update-priority', ['id' => $workOrderRouteId]) : '';
  $protectionOptionsUrl = $hasLoadedWorkOrder ? route('app-invoice-protection-options', ['id' => $workOrderRouteId]) : '';
  $protectionUpdateUrl = $hasLoadedWorkOrder ? route('app-invoice-protection-update', ['id' => $workOrderRouteId]) : '';
  $protectionOptionStoreUrl = $isAdminUser ? route('app-invoice-protection-options-store') : '';
  $closeWorkOrderUrl = $hasLoadedWorkOrder ? route('app-invoice-close', ['id' => $workOrderRouteId]) : '';
  $pantheonWorkersUrl = $hasLoadedWorkOrder ? route('app-invoice-pantheon-workers', ['id' => $workOrderRouteId]) : '';
  $normalizedClosingStatus = mb_strtolower(trim((string) ($workOrder['status'] ?? '')));
  $isPartiallyClosedWorkOrder = $hasLoadedWorkOrder && (
    str_contains($normalizedClosingStatus, 'djelomi')
    || str_contains($normalizedClosingStatus, 'djelimic')
    || $normalizedClosingStatus === 'r'
  );
  $isClosedWorkOrder = $hasLoadedWorkOrder && (
    str_contains($normalizedClosingStatus, 'zatvoren')
    || (!$isPartiallyClosedWorkOrder && str_contains($normalizedClosingStatus, 'zaklju'))
    || (!$isPartiallyClosedWorkOrder && str_contains($normalizedClosingStatus, 'zavr'))
    || $normalizedClosingStatus === 'z'
  );
  $productsFetchUrl = $hasLoadedWorkOrder ? route('app-invoice-products', ['id' => $workOrderRouteId]) : '';
  $bomFetchUrl = $hasLoadedWorkOrder ? route('app-invoice-bom', ['id' => $workOrderRouteId]) : '';
  $bomDestroyUrl = ($hasLoadedWorkOrder && $isAdminUser)
    ? route('app-invoice-bom-destroy', ['id' => $workOrderRouteId])
    : '';
  $allMaterialsFetchUrl = $hasLoadedWorkOrder ? route('app-invoice-all-materials', ['id' => $workOrderRouteId]) : '';
  $allOperationsFetchUrl = $hasLoadedWorkOrder ? route('app-invoice-all-operations', ['id' => $workOrderRouteId]) : '';
  $barcodeMaterialLookupUrl = $hasLoadedWorkOrder ? route('app-invoice-barcode-material', ['id' => $workOrderRouteId]) : '';
  $plannedConsumptionStoreUrl = $hasLoadedWorkOrder ? route('app-invoice-planned-consumption', ['id' => $workOrderRouteId]) : '';
  $plannedConsumptionUpdateUrl = $hasLoadedWorkOrder ? route('app-invoice-planned-consumption-update', ['id' => $workOrderRouteId]) : '';
  $plannedConsumptionRemoveUrl = $hasLoadedWorkOrder ? route('app-invoice-planned-consumption-remove', ['id' => $workOrderRouteId]) : '';
  $destroyWorkOrderUrl = ($hasLoadedWorkOrder && $isAdminUser)
    ? route('app-invoice-destroy', ['id' => $workOrderRouteId])
    : '';
  $canPrepareMaterial = $hasLoadedWorkOrder
    && $productsFetchUrl !== ''
    && $bomFetchUrl !== ''
    && $allMaterialsFetchUrl !== ''
    && $allOperationsFetchUrl !== ''
    && $barcodeMaterialLookupUrl !== ''
    && $plannedConsumptionStoreUrl !== '';
  $pageTitle = 'eNalog.app';
  if ($hasLoadedWorkOrder) {
    $titleIdentifier = $invoiceNumberDisplay !== '-' ? $invoiceNumberDisplay : $displayValue($workOrderRouteId);
    $pageTitle = 'Radni nalog - ' . $titleIdentifier;
  }
  $normalizeOrderLocatorQrNumber = static function ($value): string {
    $normalized = preg_replace('/[^A-Z0-9]+/', '', strtoupper(trim((string) $value)));
    if (!is_string($normalized)) {
      return '';
    }
    return $normalized;
  };
  $normalizeOrderLocatorQrPosition = static function ($value): string {
    $raw = trim((string) $value);
    if ($raw === '') {
      return '';
    }
    $normalized = str_replace(',', '.', $raw);
    if (!is_numeric($normalized)) {
      return '';
    }
    $position = (float) $normalized;
    $integerPosition = (int) round($position);
    if (abs($position - $integerPosition) > 0.000001) {
      return '';
    }
    return (string) $integerPosition;
  };
  $escapeOrderLocatorQrProductCode = static function ($value): string {
    $productCode = trim((string) $value);
    $productCode = preg_replace('/[\r\n\t]+/', ' ', $productCode);
    if (!is_string($productCode)) {
      return '';
    }
    $productCode = trim($productCode);
    $productComparable = preg_replace('/[^A-Za-z0-9]+/', '', $productCode);
    if (!is_string($productComparable) || $productComparable === '') {
      return '';
    }
    return str_replace(';', '%3B', str_replace('%', '%25', $productCode));
  };
  $composeOrderLocatorQrTarget = static function ($orderNumberRaw, $orderPositionRaw, $productCodeRaw) use (
    $normalizeOrderLocatorQrNumber,
    $normalizeOrderLocatorQrPosition,
    $escapeOrderLocatorQrProductCode
  ): ?string {
    $orderNumberPayload = $normalizeOrderLocatorQrNumber($orderNumberRaw);
    $orderPositionPayload = $normalizeOrderLocatorQrPosition($orderPositionRaw);
    if ($orderNumberPayload === '' || $orderPositionPayload === '') {
      return null;
    }

    $productCodePayload = $escapeOrderLocatorQrProductCode($productCodeRaw);
    if ($productCodePayload !== '') {
      return $orderNumberPayload . ';' . $orderPositionPayload . ';' . $productCodePayload;
    }

    return $orderNumberPayload . ';' . $orderPositionPayload;
  };
  $orderNumberQrRaw = $workOrder['broj_narudzbe'] ?? $workOrder['order_number'] ?? $workOrder['acLnkKey'] ?? '';
  $orderPositionQrRaw = $workOrder['broj_pozicije_narudzbe'] ?? $workOrder['order_position'] ?? $workOrder['anLnkNo'] ?? '';
  $productCodeQrRaw = $workOrder['sifra'] ?? $workOrder['sifra_proizvoda'] ?? $workOrder['product_code'] ?? $workOrder['acIdent'] ?? '';
  $previewQrTarget = request()->getSchemeAndHttpHost() . route('app-invoice-preview', ['id' => $workOrderRouteId], false);
  $orderLocatorQrTarget = $composeOrderLocatorQrTarget($orderNumberQrRaw, $orderPositionQrRaw, $productCodeQrRaw);
  if ($orderLocatorQrTarget !== null) {
    $previewQrTarget = $orderLocatorQrTarget;
  }
  $previewQrImage = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=0&data=' . urlencode($previewQrTarget);
  $workOrderHeaderHighlights = [];
  $workOrderMetaHighlightChips = [];
  $workOrderProductName = '';
  $workOrderProductCode = '';
  foreach ($workOrderMetaHighlights as $metaChip) {
    $metaLabelNormalized = \Illuminate\Support\Str::of((string) ($metaChip['label'] ?? ''))
      ->ascii()
      ->lower()
      ->trim()
      ->value();
    if (in_array($metaLabelNormalized, ['status', 'prioritet'], true)) {
      $workOrderHeaderHighlights[] = $metaChip;
      continue;
    }
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
  $workOrderProductName = $displayValue($workOrderProductName);
  $workOrderProductCode = $displayValue($workOrderProductCode);
  $workOrderQuantityDisplay = $displayNumber($workOrder['kolicina'] ?? null);
  $workOrderQuantityUnit = $displayValue($workOrder['mj'] ?? null);
  $statusDisplayLabel = $displayValue($workOrder['status'] ?? null);
  $priorityDisplayLabel = $displayValue($workOrder['prioritet'] ?? null);
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

@section('title', $pageTitle)

@section('content')
<section class="invoice-preview-wrapper">
  <div class="wo-mobile-top-actions">
    <div class="card">
      <div class="card-body">
        <button class="btn btn-success w-100 mb-75 d-flex justify-content-center align-items-center" data-bs-toggle="modal" data-bs-target="#qr-scanner-modal">
          <i class="fa fa-qrcode me-50" style="font-size: 20px;"></i> Skeniraj radni nalog
        </button>
        <button class="btn btn-primary w-100 d-flex justify-content-center align-items-center" data-bs-toggle="modal" data-bs-target="#sirovina-scanner-modal" @if (!$canPrepareMaterial) disabled aria-disabled="true" title="Skeniraj radni nalog prvo" @endif>
          <i class="fa fa-barcode me-50" style="font-size: 20px;"></i> Pripremi materijal
        </button>
      </div>
    </div>
  </div>
  <div class="row invoice-preview">
    <!-- Invoice -->
    <div class="col-xl-9 col-md-8 col-12 wo-preview-main-col">
      <div class="card invoice-preview-card">
        <div class="card-body invoice-padding pb-0">
          <!-- Header starts -->
          <div class="wo-header-shell invoice-spacing mt-0">
            <div class="wo-header-brand-row">
              <div class="logo-wrapper">
                <img src="{{ asset('/images/logo/TrendyCNC.png') }}" alt="Trendy d.o.o." width="50" height="auto" class="wo-brand-logo">
                <h3 class="text-primary invoice-logo">eNalog.app</h3>
              </div>
              @if($showPreviewQr)
                <div class="wo-header-qr-block">
                  <img src="{{ $previewQrImage }}" alt="QR Code" class="wo-preview-qr-image">
                </div>
              @endif
            </div>
            <div class="wo-header-details-row">
              <div class="wo-header-company-block">
                <p class="card-text mb-25">Trendy d.o.o.</p>
                <p class="card-text mb-25">Bratstvo 11, 72290</p>
                <p class="card-text mb-25">Novi Travnik, BiH</p>
                <p class="card-text mb-0">+387 30 525 252</p>
                <p class="card-text mb-0">info@trendy.ba</p>
              </div>
              <div class="wo-header-right-column">
                <div class="wo-header-main-row">
                  <div class="wo-header-meta">
                    <h4 class="invoice-title">
                      <span class="invoice-title-stack">
                        <span><span class="invoice-key">RN</span><span class="invoice-number">{{ $invoiceNumberDisplay }}</span></span>
                        @if($hasOrderNumber)
                          <span class="invoice-order-number">Narudžba:<span class="invoice-number">{{ $orderNumberWithPositionDisplay }}</span></span>
                        @endif
                      </span>
                    </h4>
                    <div class="invoice-date-wrapper">
                      <p class="invoice-date-title">Datum izdavanja:</p>
                      <p class="invoice-date">{{ $issueDate }}</p>
                    </div>
                    <div class="invoice-date-wrapper">
                      <p class="invoice-date-title">Planirani start:</p>
                      <p class="invoice-date">{{ $plannedStartDate }}</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <!-- Header ends -->
        </div>

        <hr class="invoice-spacing" />

        <!-- Address and Contact starts -->
        <div class="card-body invoice-padding pt-0 pb-0">
          <div class="row invoice-spacing wo-contact-row">
            <div class="col-xl-4 col-md-6 p-0 wo-contact-col">
              <h6 class="mb-2">Pošiljatelj:</h6>
              @if($sender['name'])
                <h6 class="mb-25">{{ $sender['name'] }}</h6>
              @endif
             
            </div>
            <div class="col-xl-4 d-none d-xl-block"></div>
            <div class="col-xl-4 col-md-6 p-0 wo-contact-col">
              <h6 class="mb-2">Primatelj:</h6>
              @if($recipient['name'])
                <h6 class="mb-25">{{ $recipient['name'] }}</h6>
              @endif
              
            </div>
          </div>
        </div>
        <!-- Address and Contact ends -->

        <hr class="invoice-spacing" />

        @if($workOrderProductName !== '' || $workOrderProductCode !== '')
          <div class="card-body invoice-padding pt-2 pb-0">
            <div class="wo-product-hero">
              <div class="wo-product-hero-row">
                <div class="wo-product-hero-main">
                  <span class="wo-product-kicker">Naziv proizvoda</span>
                  <span class="wo-product-title">{{ $displayValue($workOrderProductName) }}</span>
                  @if($workOrderProductCode !== '')
                    <span class="wo-product-code-accent" aria-label="Šifra proizvoda">
                      <span class="wo-product-code-label">Šifra proizvoda</span>
                      <span class="wo-product-code-value">{{ $displayValue($workOrderProductCode) }}</span>
                    </span>
                  @endif
                </div>
                @if($workOrderQuantityDisplay !== '-')
                  <div class="wo-product-qty" aria-label="Količina proizvoda">
                    <span class="wo-product-qty-label">Količina</span>
                    <span class="wo-product-qty-metric">
                      <span class="wo-product-qty-value">{{ $workOrderQuantityDisplay }}</span>
                      @if($workOrderQuantityUnit !== '-')
                        <span class="wo-product-qty-unit">{{ $workOrderQuantityUnit }}</span>
                      @endif
                    </span>
                  </div>
                @endif
              </div>
            </div>
          </div>
        @endif

        <div class="card-body invoice-padding pt-0 pb-0">
          <div class="wo-progress-shell">
            <div class="wo-progress-head">
              <span>{{ $displayValue($workOrderMetaProgress['label'] ?? null) }}</span>
              <span>{{ $displayValue($workOrderMetaProgress['display'] ?? null) }}</span>
            </div>
            <div class="wo-progress">
              <div class="wo-progress-bar" data-target="{{ $workOrderMetaProgressPercent }}" style="width: 0%;"></div>
            </div>
          </div>
        </div>

        <!-- Work Order Metadata starts -->
        <div class="card-body invoice-padding pt-0 pb-0">
          @if(!empty($workOrderMetaHighlightChips) || !empty($workOrderMetaFlags))
            <div class="wo-chip-shell">
              <div class="wo-meta-chip-row mb-0">
                @foreach($workOrderMetaHighlightChips as $metaChip)
                  <div class="wo-meta-chip wo-chip-{{ $metaChip['tone'] ?? 'secondary' }}">
                    <span class="wo-meta-chip-label">{{ $displayValue($metaChip['label'] ?? null) }}</span>
                    <span class="wo-meta-chip-value">{{ $displayValue($metaChip['value'] ?? null) }}</span>
                  </div>
                @endforeach
                @foreach($workOrderMetaFlags as $metaFlag)
                  <span class="wo-flag-pill wo-flag-{{ $metaFlag['tone'] ?? 'secondary' }}">
                    <span class="wo-flag-dot"></span>
                    <span>{{ $displayValue($metaFlag['label'] ?? null) }}: <strong>{{ $displayValue($metaFlag['value'] ?? null) }}</strong></span>
                  </span>
                @endforeach
              </div>
            </div>
          @endif
        </div>
        <!-- Work Order Metadata ends -->

        <hr class="invoice-spacing mb-0" />

        <!-- Invoice Description starts -->
        <div class="nav-align-top">
          <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item">
              <button type="button" class="nav-link active" role="tab" data-bs-toggle="tab" data-bs-target="#tab-sastavnica" aria-controls="tab-sastavnica" aria-selected="true">
                <i class="fa fa-list me-50"></i> Sastavnica
              </button>
            </li>
            <li class="nav-item"><button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#tab-zastita" aria-controls="tab-zastita" aria-selected="false"><i class="fa fa-shield me-50"></i> Zaštita</button></li>
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
            <li class="nav-item">
              <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#tab-napomena" aria-controls="tab-napomena" aria-selected="false">
                <i class="fa fa-sticky-note-o me-50"></i> Napomena
              </button>
            </li>
            <li class="nav-item">
              <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#tab-kpi" aria-controls="tab-kpi" aria-selected="false">
                <i class="fa fa-line-chart me-50"></i> KPI
              </button>
            </li>
            <li class="nav-item">
              <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#tab-timeline" aria-controls="tab-timeline" aria-selected="false">
                <i class="fa fa-clock-o me-50"></i> Timeline
              </button>
            </li>
            <li class="nav-item">
              <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#tab-poveznice" aria-controls="tab-poveznice" aria-selected="false">
                <i class="fa fa-link me-50"></i> Poveznice
              </button>
            </li>
          </ul>
          <div class="tab-content">
            <!-- Sastavnica Tab -->
            <div class="tab-pane fade show active" id="tab-sastavnica" role="tabpanel">
              <div class="table-responsive wo-sastavnica-table-wrap">
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
                      @if($showSastavnicaActions)
                        <th class="py-1 text-center wo-sastavnica-action-col">Akcija</th>
                      @endif
                    </tr>
                  </thead>
                  <tbody>
                    @forelse(($workOrderItems ?? []) as $item)
                      <tr>
                        <td class="py-1">{{ $displayValue($item['alternativa'] ?? null) }}</td>
                        <td class="py-1">{{ $displayValue($item['pozicija'] ?? null) }}</td>
                        <td class="py-1">{{ $displayValue($item['artikal'] ?? null) }}</td>
                        <td class="py-1">{{ $displayValue($item['opis'] ?? null) }}</td>
                        <td class="py-1 text-center"><span class="text-muted">-</span></td>
                        @php
                          $itemNote = trim((string) ($item['napomena'] ?? ''));
                          $itemNoteDisplay = $itemNote !== '' ? (Illuminate\Support\Str::limit($itemNote, 20, '..')) : '-';
                        @endphp
                        <td class="py-1" title="{{ $itemNote }}">{{ $itemNoteDisplay }}</td>
                        <td class="py-1">{{ $displayValue($item['kolicina'] ?? null) }}</td>
                        <td class="py-1">{{ $displayValue($item['mj'] ?? null) }}</td>
                        <td class="py-1">{{ $displayValue($item['serija'] ?? null) }}</td>
                        <td class="py-1">{{ $displayValue($item['normativna_osnova'] ?? null) }}</td>
                        <td class="py-1">{{ $displayValue($item['aktivno'] ?? null) }}</td>
                        <td class="py-1">{{ $displayValue($item['zavrseno'] ?? null) }}</td>
                        <td class="py-1">{{ $displayValue($item['va'] ?? null) }}</td>
                        <td class="py-1">{{ $displayValue($item['prim_klas'] ?? null) }}</td>
                        <td class="py-1">{{ $displayValue($item['sek_klas'] ?? null) }}</td>
                        @if($showSastavnicaActions)
                          <td class="py-1 text-center wo-sastavnica-action-col">
                            <div class="d-inline-flex align-items-center gap-50">
                              <button
                                type="button"
                                class="btn btn-sm btn-outline-primary wo-edit-sastavnica-btn"
                                data-item-id="{{ trim((string) ($item['qid'] ?? '')) }}"
                                data-item-no="{{ trim((string) ($item['no'] ?? '')) }}"
                                data-item-position="{{ trim((string) ($item['pozicija'] ?? '')) }}"
                                data-item-code="{{ trim((string) ($item['artikal'] ?? '')) }}"
                                data-item-description="{{ trim((string) ($item['opis'] ?? '')) }}"
                                data-item-note="{{ trim((string) ($item['napomena'] ?? '')) }}"
                                data-item-quantity="{{ trim((string) ($item['kolicina'] ?? '')) }}"
                                data-item-unit="{{ trim((string) ($item['mj'] ?? '')) }}"
                                title="Uredi stavku"
                              >
                                <i class="fa fa-pencil"></i>
                              </button>
                              @if((bool) ($item['can_remove'] ?? false))
                                <button
                                  type="button"
                                  class="btn btn-sm btn-outline-danger wo-remove-sastavnica-btn"
                                  data-item-id="{{ $displayValue($item['qid'] ?? null) }}"
                                  data-item-no="{{ $displayValue($item['no'] ?? null) }}"
                                  title="Ukloni iz radnog naloga"
                                >
                                  <i class="fa fa-trash"></i>
                                </button>
                              @endif
                            </div>
                          </td>
                        @endif
                      </tr>
                    @empty
                      <tr>
                        <td colspan="{{ $sastavnicaEmptyColspan }}" class="text-center text-muted py-2">Nema stavki za ovaj radni nalog.</td>
                      </tr>
                    @endforelse
                  </tbody>
                </table>
              </div>
            </div>
            <!-- Materijali Tab -->
            <div class="tab-pane fade" id="tab-zastita" role="tabpanel"><div class="table-responsive wo-sastavnica-table-wrap"><table class="table" id="wo-protection-table"><thead><tr><th class="py-1 text-center">Kod</th><th class="py-1 text-center">Naziv</th><th class="py-1 text-center">Rok (sedmice)</th><th class="py-1 text-center">Promijeni zaštitu</th><th class="py-1 text-center">Akcije</th></tr></thead><tbody><tr><td class="py-1 wo-protection-code">{{ $displayValue($workOrder['povrsinska_zastita'] ?? null) }}</td><td class="py-1 wo-protection-name">-</td><td class="py-1 wo-protection-weeks">-</td><td class="py-1"><select class="form-select form-select-sm wo-protection-tab-select"><option value="">Učitavanje…</option></select></td><td class="py-1 text-center"><button type="button" class="btn btn-icon btn-flat-danger wo-protection-remove" title="Ukloni zaštitu"><i class="fa fa-trash"></i></button></td></tr></tbody></table></div></div>
            <div class="tab-pane fade" id="tab-materijali" role="tabpanel">
              <div class="table-responsive wo-sastavnica-table-wrap">
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
                        <td class="py-1">{{ $displayValue($item['pozicija'] ?? null) }}</td>
                        <td class="py-1">{{ $displayValue($item['materijal'] ?? null) }}</td>
                        <td class="py-1">{{ $displayValue($item['naziv'] ?? null) }}</td>
                        @php
                          $materialItemId = trim((string) ($item['item_qid'] ?? ''));
                          $materialItemNo = trim((string) ($item['item_no'] ?? $item['pozicija'] ?? ''));
                          $canEditMaterialQuantity = $materialItemId !== '' || $materialItemNo !== '';
                        @endphp
                        <td class="py-1">
                          <div class="wo-material-quantity-controls">
                            <input
                              class="form-control form-control-sm wo-material-quantity-input"
                              type="text"
                              inputmode="decimal"
                              autocomplete="off"
                              value="{{ $displayValue($item['kolicina'] ?? null) === '-' ? '' : ($item['kolicina'] ?? '') }}"
                              data-item-id="{{ $materialItemId }}"
                              data-item-no="{{ $materialItemNo }}"
                              data-saved-quantity="{{ $item['kolicina'] ?? '' }}"
                              aria-label="Količina materijala {{ $displayValue($item['materijal'] ?? null) }}"
                              @if(!$canEditMaterialQuantity) disabled title="Stavku nije moguće identifikovati za ažuriranje." @endif
                            >
                            <button
                              type="button"
                              class="btn btn-outline-primary btn-sm wo-material-save-quantity-btn"
                              title="Sačuvaj količinu"
                              aria-label="Sačuvaj količinu"
                              @if(!$canEditMaterialQuantity) disabled @endif
                            >
                              <i class="fa fa-check" aria-hidden="true"></i>
                            </button>
                          </div>
                        </td>
                        <td class="py-1">{{ $displayValue($item['napomena'] ?? null) }}</td>
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
              <div class="table-responsive wo-sastavnica-table-wrap">
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
                        <td class="py-1">{{ $displayValue($operation['alternativa'] ?? null) }}</td>
                        <td class="py-1">{{ $displayValue($operation['pozicija'] ?? null) }}</td>
                        <td class="py-1">{{ $displayValue($operation['operacija'] ?? null) }}</td>
                        <td class="py-1">{{ $displayValue($operation['naziv'] ?? null) }}</td>
                        @php
                          $operationNote = trim((string) ($operation['napomena'] ?? ''));
                          $operationNoteDisplay = $operationNote !== '' ? Illuminate\Support\Str::limit($operationNote, 20, '..') : '-';
                        @endphp
                        <td class="py-1" title="{{ $operationNote }}">{{ $operationNoteDisplay }}</td>
                        <td class="py-1">{{ $displayValue($operation['mj'] ?? null) }}</td>
                        <td class="py-1">{{ $displayValue($operation['mj_vrij'] ?? null) }}</td>
                        <td class="py-1">{{ $displayValue($operation['normativna_osnova'] ?? null) }}</td>
                        <td class="py-1">{{ $displayValue($operation['va'] ?? null) }}</td>
                        <td class="py-1">{{ $displayValue($operation['prim_klas'] ?? null) }}</td>
                        <td class="py-1">{{ $displayValue($operation['sek_klas'] ?? null) }}</td>
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
            <!-- Napomena Tab -->
            <div class="tab-pane fade" id="tab-napomena" role="tabpanel">
              <div class="wo-subtle-tabs-pane wo-note-pane">
                @if($workOrderNote !== '')
                  <div class="wo-note-readonly" role="textbox" aria-readonly="true">{{ $workOrderNote }}</div>
                @else
                  <div class="wo-note-empty">Nema napomene za ovaj radni nalog.</div>
                @endif
              </div>
            </div>
            <!-- KPI Tab -->
            <div class="tab-pane fade" id="tab-kpi" role="tabpanel">
              <div class="wo-subtle-tabs-pane">
                <div class="wo-kpi-shell mb-0 pt-0">
                  <div class="wo-kpi-grid">
                    @foreach($workOrderMetaKpis as $kpi)
                      <div class="wo-kpi-item">
                        <span class="wo-kpi-label">{{ $displayValue($kpi['label'] ?? null) }}</span>
                        <span class="wo-kpi-value">
                          {{ $displayValue($kpi['value'] ?? null) }}
                          @if(!empty($kpi['unit']))
                            <span class="wo-kpi-unit">{{ $kpi['unit'] }}</span>
                          @endif
                        </span>
                      </div>
                    @endforeach
                  </div>
                </div>
              </div>
            </div>
            <!-- Timeline Tab -->
            <div class="tab-pane fade" id="tab-timeline" role="tabpanel">
              <div class="wo-subtle-tabs-pane wo-timeline-pane">
                <ul class="timeline ms-50">
                  @forelse($workOrderMetaTimeline as $metaRow)
                    @php
                      $timelineLabel = strtolower((string) ($metaRow['label'] ?? ''));
                      $timelineTone = 'primary';
                      if (str_contains($timelineLabel, 'datum naloga') || str_contains($timelineLabel, 'unosa')) {
                        $timelineTone = 'info';
                      } elseif (str_contains($timelineLabel, 'start')) {
                        $timelineTone = 'success';
                      } elseif (str_contains($timelineLabel, 'kraj')) {
                        $timelineTone = 'warning';
                      } elseif (str_contains($timelineLabel, 'zavrsetak')) {
                        $timelineTone = 'danger';
                      } elseif (str_contains($timelineLabel, 'izmjene')) {
                        $timelineTone = 'secondary';
                      }
                    @endphp
                    <li class="timeline-item">
                      <span class="timeline-point timeline-point-{{ $timelineTone }} timeline-point-indicator"></span>
                      <div class="timeline-event">
                        <div class="d-flex justify-content-between flex-sm-row flex-column mb-sm-0 mb-25">
                          <h6>{{ $displayValue($metaRow['label'] ?? null) }}</h6>
                          <span class="timeline-event-time">{{ $displayValue($metaRow['value'] ?? null) }}</span>
                        </div>
                      </div>
                    </li>
                  @empty
                    <li class="timeline-item">
                      <span class="timeline-point timeline-point-secondary timeline-point-indicator"></span>
                      <div class="timeline-event">
                        <div class="text-muted">Nema podataka za timeline.</div>
                      </div>
                    </li>
                  @endforelse
                </ul>
              </div>
            </div>
            <!-- Poveznice Tab -->
            <div class="tab-pane fade" id="tab-poveznice" role="tabpanel">
              <div class="wo-subtle-tabs-pane wo-links-pane">
                <div class="wo-links-grid">
                  @forelse($workOrderMetaTraceability as $metaRow)
                    @php
                      $linkLabel = $displayValue($metaRow['label'] ?? null);
                      $linkValue = $displayValue($metaRow['value'] ?? null);
                      $normalizedLabel = strtolower($linkLabel);
                      $linkTone = 'primary';
                      $linkIcon = 'fa-link';

                      if (str_contains($normalizedLabel, 'vezni')) {
                        $linkTone = 'info';
                        $linkIcon = 'fa-random';
                      } elseif (str_contains($normalizedLabel, 'parent') || str_contains($normalizedLabel, 'nadred')) {
                        $linkTone = 'warning';
                        $linkIcon = 'fa-sitemap';
                      } elseif (str_contains($normalizedLabel, 'qid')) {
                        $linkTone = 'warning';
                        $linkIcon = 'fa-barcode';
                      } elseif (str_contains($normalizedLabel, 'user') || str_contains($normalizedLabel, 'korisnik')) {
                        $linkTone = 'success';
                        $linkIcon = 'fa-user';
                      } elseif (str_contains($normalizedLabel, 'cost') || str_contains($normalizedLabel, 'trosk')) {
                        $linkTone = 'danger';
                        $linkIcon = 'fa-money';
                      } elseif (str_contains($normalizedLabel, 'izvor') || str_contains($normalizedLabel, 'crop') || str_contains($normalizedLabel, 'kroj')) {
                        $linkTone = 'secondary';
                        $linkIcon = 'fa-cube';
                      } elseif (str_contains($normalizedLabel, 'rn')) {
                        $linkTone = 'primary';
                        $linkIcon = 'fa-key';
                      }
                    @endphp
                    <article class="wo-link-card wo-link-tone-{{ $linkTone }}">
                      <div class="wo-link-head">
                        <span class="wo-link-icon"><i class="fa {{ $linkIcon }}"></i></span>
                        <span class="wo-link-label">{{ $linkLabel }}</span>
                      </div>
                      <div class="wo-link-value{{ $linkValue === '-' ? ' wo-link-empty' : '' }}">{{ $linkValue }}</div>
                    </article>
                  @empty
                    <div class="wo-links-empty">Nema podataka o poveznicama.</div>
                  @endforelse
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- Invoice Description ends -->
      </div>
    </div>
    <!-- /Invoice -->

    <!-- Invoice Actions -->
    <div class="col-xl-3 col-md-4 col-12 invoice-actions mt-md-0 mt-2 wo-preview-actions-col">
      <div class="card">
        <div class="card-body">
          @if($showPreviewQr)
            <div class="wo-sidebar-qr-block">
              <img src="{{ $previewQrImage }}" alt="QR Code" class="wo-preview-qr-image">
            </div>
            <div class="invoice-actions-divider wo-sidebar-qr-divider"></div>
          @endif
          <div class="wo-action-primary-pair">
            <button class="btn btn-success w-100 mb-75 d-flex justify-content-center align-items-center" data-bs-toggle="modal" data-bs-target="#qr-scanner-modal">
              <i class="fa fa-qrcode me-50" style="font-size: 20px;"></i> Skeniraj radni nalog
            </button>
            <button class="btn btn-primary w-100 mb-75 d-flex justify-content-center align-items-center" data-bs-toggle="modal" data-bs-target="#sirovina-scanner-modal" @if (!$canPrepareMaterial) disabled aria-disabled="true" title="Skeniraj radni nalog prvo" @endif>
              <i class="fa fa-barcode me-50" style="font-size: 20px;"></i> Pripremi materijal
            </button>
          </div>
          <div class="invoice-actions-divider wo-action-primary-divider"></div>
          <div class="wo-other-options-shell">
            <div class="invoice-actions-divider"></div>
            <button
              class="btn btn-outline-secondary w-100 mb-75 d-flex justify-content-center align-items-center wo-other-options-toggle collapsed"
              type="button"
              data-bs-toggle="collapse"
              data-bs-target="#wo-other-options-collapse"
              aria-expanded="false"
              aria-controls="wo-other-options-collapse"
            >
              <i class="fa fa-chevron-down me-50 wo-other-options-chevron" style="font-size: 12px;"></i> Ostale opcije
            </button>
            <div class="collapse wo-other-options-collapse" id="wo-other-options-collapse">
          <button
            id="wo-close-order-btn"
            class="btn btn-outline-success w-100 mb-75 d-flex justify-content-center align-items-center"
            type="button"
            data-bs-toggle="modal"
            data-bs-target="#close-work-order-modal"
            @if (!$hasLoadedWorkOrder || $closeWorkOrderUrl === '' || $isClosedWorkOrder) disabled aria-disabled="true" title="{{ $isClosedWorkOrder ? 'Radni nalog je zaključen' : 'Skeniraj radni nalog prvo' }}" @endif
          >
            <i class="fa fa-check-circle me-50"></i>
            <span class="wo-close-order-label">{{ $isClosedWorkOrder ? 'Nalog zaključen' : ($isPartiallyClosedWorkOrder ? 'Nastavi zatvaranje' : 'Zatvori nalog') }}</span>
          </button>
          <button id="wo-status-trigger-btn" class="btn w-100 mb-75 d-flex justify-content-center align-items-center wo-side-meta-btn wo-side-meta-btn-{{ $statusToneClass }}" data-bs-toggle="modal" data-bs-target="#change-status-modal" @if (!$hasLoadedWorkOrder) disabled aria-disabled="true" title="Skeniraj radni nalog prvo" @endif>
            <i class="fa fa-circle-notch me-50"></i> Status: <span id="wo-status-label" class="ms-25">{{ $statusDisplayLabel }}</span>
          </button>
          <button id="wo-priority-trigger-btn" class="btn w-100 mb-75 d-flex justify-content-center align-items-center wo-side-meta-btn wo-side-meta-btn-{{ $priorityToneClass }}" data-bs-toggle="modal" data-bs-target="#change-priority-modal" @if (!$hasLoadedWorkOrder) disabled aria-disabled="true" title="Skeniraj radni nalog prvo" @endif>
            <span id="wo-priority-label">{{ $priorityDisplayLabel === '-' ? 'Prioritet -' : $priorityDisplayLabel }}</span>
          </button>
          @if($isAdminUser)
            <button
              id="wo-delete-order-btn"
              class="btn btn-danger w-100 mb-75 d-flex justify-content-center align-items-center"
              type="button"
              data-delete-url="{{ $destroyWorkOrderUrl }}"
              @if (!$hasLoadedWorkOrder || $destroyWorkOrderUrl === '') disabled aria-disabled="true" title="Skeniraj radni nalog prvo" @endif
            >
              <i class="fa fa-trash me-50" style="margin-top: 1px;"></i> Izbriši nalog
            </button>
          @endif
          @if($isAdminUser)
            <button
              id="wo-protection-trigger-btn"
              class="btn btn-outline-secondary w-100 mb-75 d-flex justify-content-center align-items-center"
              type="button"
              @if (!$hasLoadedWorkOrder || $protectionOptionsUrl === '') disabled aria-disabled="true" title="Skeniraj radni nalog prvo" @endif
            >
              <i class="fa fa-shield me-50" style="margin-top: 1px;"></i> Dodaj zaštitu
            </button>
            <a class="btn btn-outline-primary w-100 mb-75 d-flex justify-content-center align-items-center" href="{{ route('app-stock', ['open' => 'create-material']) }}">
              <i class="fa fa-cube me-50" style="margin-top: 2px;"></i> Dodaj materijal
            </a>
          @else
            <button class="btn btn-outline-secondary w-100 mb-75 d-flex justify-content-center align-items-center" type="button" @if (!$hasLoadedWorkOrder) disabled @endif id="wo-protection-trigger-btn">
              <i class="fa fa-shield me-50" style="margin-top: 1px;"></i> Dodaj zaštitu
            </button>
            <button class="btn btn-outline-primary w-100 mb-75 d-flex justify-content-center align-items-center" type="button" onclick="alert('Uskoro')">
              <i class="fa fa-cube me-50" style="margin-top: 2px;"></i> Dodaj materijal
            </button>
          @endif
          <button class="btn btn-outline-secondary w-100 mb-75 d-flex justify-content-center align-items-center" type="button" onclick="alert('Uskoro')">
            <i class="fa fa-cog me-50" style="margin-top: 2px;"></i> Dodaj operaciju
          </button>
          <button class="btn btn-outline-secondary w-100 mb-75 d-flex justify-content-center align-items-center" data-bs-toggle="modal" data-bs-target="#send-invoice-sidebar">
            <i class="fa fa-paper-plane me-50" style="margin-top: 2px; font-size: 12px;"></i> Pošalji
          </button>
          <a
            class="btn btn-outline-secondary w-100 d-flex justify-content-center align-items-center"
            href="{{ route('app-invoice-print', ['id' => $workOrderRouteId]) }}"
            target="_blank"
          >
            <i class="fa fa-print me-50"></i> Isprintaj
          </a>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- /Invoice Actions -->
  </div>
</section>

<div class="modal fade" id="close-work-order-modal" tabindex="-1" aria-labelledby="close-work-order-modal-label" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="close-work-order-modal-label">Zatvori nalog {{ $invoiceNumberDisplay }}</h5>
          <small class="text-muted">Vrijeme se unosi u minutama za jednu proizvedenu jedinicu.</small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zatvori"></button>
      </div>
      <div class="modal-body">
        <ul class="nav nav-tabs" role="tablist">
          <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#close-work-order-operations" type="button" role="tab">Operacije</button>
          </li>
          <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#close-work-order-materials" type="button" role="tab">Materijali</button>
          </li>
          <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#close-work-order-receipts" type="button" role="tab">Prijem</button>
          </li>
        </ul>
        <div class="tab-content pt-1">
          <div class="tab-pane fade show active" id="close-work-order-operations" role="tabpanel">
            <div class="table-responsive">
              <table class="table align-middle wo-close-table">
                <thead>
                  <tr>
                    <th>Pozicija</th>
                    <th>Operacija</th>
                    <th>Naziv</th>
                    <th>Radnik</th>
                    <th>Početak izrade</th>
                    <th>Kraj izrade</th>
                    <th>Trajanje (min/jedinica)</th>
                    <th>Zastoj (min)</th>
                    <th class="text-center">Akcije</th>
                  </tr>
                </thead>
                <tbody>
                  @php
                    $closeOperations = $closingWorkOrderOperations ?? $workOrderRegOperations ?? [];
                    $closeWorkOrderHasNoOperations = count($closeOperations) === 0;
                    if ($closeWorkOrderHasNoOperations) $closeOperations = array_fill(0, 6, []);
                  @endphp
                  @foreach($closeOperations as $operation)
                    <tr class="wo-close-operation-row" data-item-qid="{{ (int) ($operation['id'] ?? 0) }}" data-operation-code="{{ mb_strtoupper(trim((string) ($operation['operacija'] ?? ''))) }}">
                      <td>{{ $displayValue($operation['pozicija'] ?? $loop->iteration) }}</td>
                      <td class="position-relative"><input class="form-control wo-close-operation-code text-uppercase" type="text" maxlength="64" autocomplete="off" value="{{ $operation['operacija'] ?? '' }}" placeholder="npr. OP30" aria-label="Šifra operacije" aria-autocomplete="list"><div class="wo-close-code-suggestions d-none" role="listbox"></div></td>
                      <td><input class="form-control wo-close-operation-name" type="text" value="{{ $operation['naziv'] ?? '' }}" readonly aria-label="Naziv operacije"></td>
                      <td class="position-relative">
                        <input class="form-control wo-close-worker-search" type="text" autocomplete="off" placeholder="Upišite ime radnika" aria-label="Radnik" aria-autocomplete="list">
                        <input class="wo-close-worker" type="hidden" value="">
                        <div class="wo-close-worker-suggestions d-none" role="listbox"></div>
                        <div class="text-danger wo-close-operation-error">Odaberite Pantheon radnika.</div>
                      </td>
                      <td>
                        <div class="wo-close-clock-fields" aria-label="Početak izrade">
                          <input class="form-control wo-close-start-hour" type="text" inputmode="numeric" maxlength="2" placeholder="HH" autocomplete="off" aria-label="Sat početka izrade">
                          <span aria-hidden="true">:</span>
                          <input class="form-control wo-close-start-minute" type="text" inputmode="numeric" maxlength="2" placeholder="MM" autocomplete="off" aria-label="Minuta početka izrade">
                          <input class="wo-close-start-time" type="hidden" value="">
                        </div>
                        <div class="text-danger wo-close-break-error wo-close-start-break-error">Ovo je vrijeme tokom pauze.</div>
                      </td>
                      <td>
                        <div class="wo-close-clock-fields" aria-label="Kraj izrade">
                          <input class="form-control wo-close-end-hour" type="text" inputmode="numeric" maxlength="2" placeholder="HH" autocomplete="off" aria-label="Sat kraja izrade">
                          <span aria-hidden="true">:</span>
                          <input class="form-control wo-close-end-minute" type="text" inputmode="numeric" maxlength="2" placeholder="MM" autocomplete="off" aria-label="Minuta kraja izrade">
                          <input class="wo-close-end-time" type="hidden" value="">
                        </div>
                        <div class="text-danger wo-close-break-error wo-close-end-break-error">Ovo je vrijeme tokom pauze.</div>
                      </td>
                      <td>
                        <input class="form-control wo-close-time" type="text" inputmode="decimal" placeholder="Minute" autocomplete="off" aria-label="Trajanje u minutama po jedinici">
                        <div class="text-danger wo-close-operation-error">Unesite oba vremena ili nenegativan broj minuta. Završno vrijeme ne može biti prije početnog.</div>
                      </td>
                      <td><input class="form-control wo-close-downtime" type="text" inputmode="decimal" placeholder="Opcionalno" autocomplete="off" aria-label="Zastoj u minutama"></td>
                      <td class="text-center">
                        <div class="wo-close-action-buttons">
                          <button type="button" class="wo-close-copy-row-btn" title="Kopiraj red" aria-label="Kopiraj red"><i class="fa fa-copy" aria-hidden="true"></i></button>
                          <button type="button" class="btn btn-outline-secondary btn-sm wo-close-clear-row-btn" title="Očisti red" aria-label="Očisti red"><i class="fa fa-eraser" aria-hidden="true"></i></button>
                          <button type="button" class="btn btn-outline-danger btn-sm wo-close-delete-row-btn" title="Izbriši red" aria-label="Izbriši red"><i class="fa fa-trash" aria-hidden="true"></i></button>
                        </div>
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          </div>
          <div class="tab-pane fade" id="close-work-order-materials" role="tabpanel">
            <div class="table-responsive">
              <table class="table align-middle" id="close-work-order-materials-table">
                <thead><tr><th class="text-nowrap" style="width:1%">Pozicija</th><th style="width:23%">Materijal</th><th style="width:30%">Naziv</th><th style="width:19%">Količina</th><th class="text-nowrap" style="width:1%">MJ</th><th class="text-nowrap">Skladište sirovina</th><th class="text-center text-nowrap" style="width:1%">Akcije</th></tr></thead>
                <tbody>
                  @php
                    $closeMaterials = $workOrderItemResources ?? [];
                    if ($closeWorkOrderHasNoOperations) {
                      $closeMaterials = array_merge($closeMaterials, array_fill(0, 6, []));
                    } elseif (count($closeMaterials) === 0) {
                      $closeMaterials = array_fill(0, 6, []);
                    }
                  @endphp
                  @foreach($closeMaterials as $material)
                    <tr data-existing-material="{{ trim((string) ($material['materijal'] ?? '')) !== '' ? '1' : '0' }}">
                      <td class="text-nowrap">{{ $displayValue($material['pozicija'] ?? $loop->iteration) }}</td>
                      <td class="position-relative"><input class="form-control wo-close-material-code text-uppercase" type="text" maxlength="64" autocomplete="off" value="{{ $material['materijal'] ?? '' }}" placeholder="Šifra materijala" aria-label="Šifra materijala" aria-autocomplete="list"><div class="wo-close-code-suggestions d-none" role="listbox"></div></td>
                      <td><input class="form-control wo-close-material-name" type="text" value="{{ $material['naziv'] ?? '' }}" readonly aria-label="Naziv materijala"></td>
                      @php
                        $closeMaterialItemId = trim((string) ($material['item_qid'] ?? ''));
                        $closeMaterialItemNo = trim((string) ($material['item_no'] ?? $material['pozicija'] ?? ''));
                        $canEditCloseMaterialQuantity = $closeMaterialItemId !== '' || $closeMaterialItemNo !== '';
                      @endphp
                      <td>
                        <div class="wo-material-quantity-controls">
                          <input
                            class="form-control wo-close-material-quantity"
                            type="text"
                            inputmode="decimal"
                            autocomplete="off"
                            value="{{ $displayValue($material['kolicina'] ?? null) === '-' ? '' : ($material['kolicina'] ?? '') }}"
                            data-item-id="{{ $closeMaterialItemId }}"
                            data-item-no="{{ $closeMaterialItemNo }}"
                            data-saved-quantity="{{ $material['kolicina'] ?? '' }}"
                            aria-label="Količina materijala {{ $displayValue($material['materijal'] ?? null) }}"
                            @if(false && !$canEditCloseMaterialQuantity) disabled title="Stavku nije moguće identifikovati za ažuriranje." @endif
                          >
                        </div>
                      </td>
                      <td class="text-nowrap wo-close-material-unit">{{ $displayValue($material['mj'] ?? null) }}</td>
                      <td class="text-nowrap wo-close-material-raw-stock">{{ $displayValue($material['raw_material_stock_qty'] ?? null) }}</td>
                      <td class="text-center text-nowrap">
                        <button type="button" class="btn btn-outline-primary btn-sm wo-close-add-material-row-btn" style="background:#fff !important;background-color:#fff !important" title="Novi materijal" aria-label="Novi materijal"><i class="fa fa-plus"></i></button>
                        <button type="button" class="btn btn-outline-secondary btn-sm wo-close-material-clear-row-btn" title="Očisti red" aria-label="Očisti red"><i class="fa fa-eraser"></i></button>
                        <button type="button" class="btn btn-outline-danger btn-sm wo-close-material-delete-row-btn" title="Izbriši red" aria-label="Izbriši red"><i class="fa fa-trash"></i></button>
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          </div>
          <div class="tab-pane fade" id="close-work-order-receipts" role="tabpanel">
            @php
              $closingReceiptCode = $workOrder['sifra'] ?? $workOrder['sifra_proizvoda'] ?? $workOrder['product_code'] ?? $workOrder['acIdent'] ?? '';
              $closingReceiptName = $workOrder['naziv'] ?? $workOrder['naziv_proizvoda'] ?? $workOrder['product_name'] ?? '';
              $closingReceiptQuantity = $workOrder['kolicina'] ?? '';
            @endphp
            <p class="text-muted mb-1">Rasporedite proizvedenu količinu između veleprodajnog skladišta i skladišta škarta. Ukupan prijem mora biti jednak količini radnog naloga.</p>
            <div class="table-responsive">
              <table class="table align-middle" id="close-work-order-receipts-table">
                <thead><tr><th>Odredište</th><th>Artikal</th><th>Naziv</th><th>Količina</th><th>MJ</th><th></th></tr></thead>
                <tbody>
                  <tr data-receipt-target="vp">
                    <td>Veleprodajno skladište</td>
                    <td>{{ $displayValue($closingReceiptCode) }}</td>
                    <td>{{ $displayValue($closingReceiptName) }}</td>
                    <td><input class="form-control form-control-sm wo-close-receipt-quantity" type="text" inputmode="decimal" value="{{ $closingReceiptQuantity }}" autocomplete="off"></td>
                    <td>{{ $displayValue($workOrder['mj'] ?? null) }}</td>
                    <td></td>
                  </tr>
                </tbody>
              </table>
            </div>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="wo-close-add-scrap-receipt-btn"><i class="fa fa-plus me-50"></i>Dodaj prijem škarta</button>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Odustani</button>
        <button type="button" class="btn btn-success" id="wo-close-submit-btn" disabled>
          <i class="fa fa-check-circle me-50"></i> Zatvori nalog
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Send Invoice Sidebar -->
<div class="modal modal-slide-in fade" id="send-invoice-sidebar" aria-hidden="true">
  <div class="modal-dialog sidebar-lg">
    <div class="modal-content p-0">
      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zatvori">×</button>
      <div class="modal-header mb-1">
        <h5 class="modal-title">
          <span class="align-middle">Pošalji fakturu (prijedlog implementacije)</span>
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
<script src="{{asset('vendors/js/extensions/sweetalert2.all.min.js')}}"></script>
<script src="{{asset('vendors/js/forms/select/select2.full.min.js')}}"></script>
@endsection

@section('page-script')
<script src="{{asset('js/scripts/pages/app-invoice.js')}}"></script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    var sidebar = document.querySelector('.invoice-preview-wrapper .invoice-actions');
    var progressBars = document.querySelectorAll('.wo-progress-bar[data-target]');
    var scanLookupNotice = @json(session('scan_lookup_notice') ?? ($scanLookupNotice ?? null));
    var mutationConfig = {
      statusUrl: @json($statusUpdateUrl),
      priorityUrl: @json($priorityUpdateUrl),
      protectionOptionsUrl: @json($protectionOptionsUrl),
      protectionUpdateUrl: @json($protectionUpdateUrl),
      protectionOptionStoreUrl: @json($protectionOptionStoreUrl),
      closeUrl: @json($closeWorkOrderUrl),
      workersUrl: @json($pantheonWorkersUrl),
      operationsUrl: @json($allOperationsFetchUrl),
      materialsUrl: @json($allMaterialsFetchUrl),
      plannedConsumptionUpdateUrl: @json($plannedConsumptionUpdateUrl),
      plannedConsumptionRemoveUrl: @json($plannedConsumptionRemoveUrl),
      csrfToken: @json(csrf_token())
    };
    var toneClasses = ['primary', 'secondary', 'success', 'warning', 'danger', 'info'];
    var statusSaveButton = document.getElementById('wo-status-save-btn');
    var prioritySaveButton = document.getElementById('wo-priority-save-btn');
    var statusSelect = document.getElementById('wo-status-select');
    var prioritySelect = document.getElementById('wo-priority-select');
    var statusLabel = document.getElementById('wo-status-label');
    var priorityLabel = document.getElementById('wo-priority-label');
    var statusTriggerButton = document.getElementById('wo-status-trigger-btn');
    var priorityTriggerButton = document.getElementById('wo-priority-trigger-btn');
    var protectionTriggerButton = document.getElementById('wo-protection-trigger-btn');
    var closeWorkOrderButton = document.getElementById('wo-close-order-btn');
    var closeWorkOrderSubmit = document.getElementById('wo-close-submit-btn');
    var closeWorkOrderModal = document.getElementById('close-work-order-modal');
    var pantheonWorkerSearchCache = Object.create(null);
    var closingCatalogSearchCache = {
      operations: Object.create(null),
      materials: Object.create(null)
    };
    var workOrderBreaks = [
      [600, 630], [720, 735], [885, 915],
      [1080, 1110], [1200, 1215], [1365, 1380]
    ];
    var deleteWorkOrderButton = document.getElementById('wo-delete-order-btn');
    var statusModalElement = document.getElementById('change-status-modal');
    var priorityModalElement = document.getElementById('change-priority-modal');
    var editSastavnicaModalElement = document.getElementById('edit-sastavnica-item-modal');
    var sastavnicaTable = document.getElementById('sastavnica-table');
    var materijaliTable = document.getElementById('materijali-table');
    var closeWorkOrderMaterialsTable = document.getElementById('close-work-order-materials-table');
    var operacijaTable = document.getElementById('operacija-table');
    var editSastavnicaError = document.getElementById('edit-sastavnica-item-error');
    var editSastavnicaCodeInput = document.getElementById('edit-sastavnica-item-code');
    var editSastavnicaPositionInput = document.getElementById('edit-sastavnica-item-position');
    var editSastavnicaDescriptionInput = document.getElementById('edit-sastavnica-item-description');
    var editSastavnicaQuantityInput = document.getElementById('edit-sastavnica-item-quantity');
    var editSastavnicaUnitInput = document.getElementById('edit-sastavnica-item-unit');
    var editSastavnicaNoteInput = document.getElementById('edit-sastavnica-item-note');
    var editSastavnicaSaveButton = document.getElementById('edit-sastavnica-item-save-btn');
    var activeSastavnicaEditContext = null;

    var onScroll = function () {
      sidebar.classList.toggle('invoice-actions-scrolled', window.scrollY > 80);
    };

    if (sidebar) {
      onScroll();
      window.addEventListener('scroll', onScroll, { passive: true });
    }

    progressBars.forEach(function (bar, index) {
      var target = parseFloat(bar.getAttribute('data-target') || '0');
      var track = bar.closest('.wo-progress');

      if (!Number.isFinite(target)) {
        target = 0;
      }

      target = Math.max(0, Math.min(100, target));
      bar.style.width = '0%';

      if (track) {
        track.classList.remove('wo-progress-live');
        track.classList.add('wo-progress-charging');
      }

      var finalize = function () {
        if (!track) {
          return;
        }

        track.classList.remove('wo-progress-charging');
        track.classList.add('wo-progress-live');
      };

      bar.addEventListener('transitionend', function (event) {
        if (event.propertyName === 'width') {
          finalize();
        }
      }, { once: true });

      window.requestAnimationFrame(function () {
        window.requestAnimationFrame(function () {
          bar.style.width = target + '%';
        });
      });

      if (target === 0) {
        window.setTimeout(finalize, 520 + (index * 70));
      }
    });

    function swalWithTheme(options) {
      var resolvedOptions = Object.assign({}, options || {});
      var htmlElement = document.documentElement;
      var bodyElement = document.body;
      var htmlDataLayout = (htmlElement.getAttribute('data-layout') || '').toLowerCase();
      var isDarkTheme =
        htmlElement.classList.contains('dark-layout') ||
        htmlElement.classList.contains('semi-dark-layout') ||
        bodyElement.classList.contains('dark-layout') ||
        bodyElement.classList.contains('semi-dark-layout') ||
        htmlDataLayout.indexOf('dark-layout') !== -1 ||
        htmlDataLayout.indexOf('semi-dark-layout') !== -1;

      if (!isDarkTheme) {
        return resolvedOptions;
      }

      resolvedOptions.background = resolvedOptions.background || '#283046';
      resolvedOptions.color = resolvedOptions.color || '#d0d2d6';
      resolvedOptions.customClass = Object.assign({}, resolvedOptions.customClass || {});
      resolvedOptions.customClass.popup = (
        (resolvedOptions.customClass.popup || '') + ' wo-swal-dark'
      ).trim();
      var originalDidOpen = resolvedOptions.didOpen;
      resolvedOptions.didOpen = function (popup) {
        if (popup) {
          popup.style.background = '#283046';
          popup.style.color = '#d0d2d6';
        }

        if (typeof originalDidOpen === 'function') {
          originalDidOpen(popup);
        }
      };

      return resolvedOptions;
    }

    window.woSwalWithTheme = swalWithTheme;

    if (scanLookupNotice && window.Swal && typeof window.Swal.fire === 'function') {
      var scanLookupTimerMs = 3000;
      Swal.fire(swalWithTheme({
        icon: String(scanLookupNotice.icon || 'info'),
        title: String(scanLookupNotice.title || ''),
        text: String(scanLookupNotice.text || ''),
        timer: scanLookupTimerMs,
        timerProgressBar: false,
        showConfirmButton: false,
        customClass: {
          popup: 'wo-scan-swal-popup'
        },
        didOpen: function (popup) {
          if (!popup) {
            return;
          }

          popup.style.setProperty('--wo-scan-progress-duration', String(scanLookupTimerMs) + 'ms');

          var progress = document.createElement('div');
          progress.className = 'wo-scan-swal-progress';
          var fill = document.createElement('div');
          fill.className = 'wo-scan-swal-progress-fill';
          progress.appendChild(fill);
          popup.appendChild(progress);

          window.requestAnimationFrame(function () {
            window.requestAnimationFrame(function () {
              fill.style.width = '100%';
            });
          });
        }
      }));
    }

    function setActionButtonLoading(button, isLoading) {
      if (!button) {
        return;
      }

      if (isLoading) {
        if (!button.dataset.defaultHtml) {
          button.dataset.defaultHtml = button.innerHTML;
        }

        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-50" role="status" aria-hidden="true"></span> Obrada';
        return;
      }

      button.disabled = false;
      button.innerHTML = button.dataset.defaultHtml || button.dataset.defaultLabel || 'Sačuvaj';
    }

    function extractErrorMessage(payload, fallbackMessage) {
      if (payload && typeof payload.message === 'string' && payload.message.trim() !== '') {
        return payload.message.trim();
      }

      if (payload && payload.errors && typeof payload.errors === 'object') {
        var firstKey = Object.keys(payload.errors)[0];
        var firstError = firstKey && Array.isArray(payload.errors[firstKey]) ? payload.errors[firstKey][0] : '';

        if (typeof firstError === 'string' && firstError.trim() !== '') {
          return firstError.trim();
        }
      }

      return fallbackMessage;
    }

    function requestMutation(url, payload, fallbackMessage) {
      return fetch(url, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': mutationConfig.csrfToken,
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(payload || {})
      }).then(function (response) {
        return response.json().catch(function () {
          return {};
        }).then(function (body) {
          if (!response.ok) {
            throw new Error(extractErrorMessage(body, fallbackMessage));
          }

          return body;
        });
      });
    }

    function requestDelete(url, fallbackMessage) {
      return fetch(url, {
        method: 'DELETE',
        headers: {
          'Accept': 'application/json',
          'X-CSRF-TOKEN': mutationConfig.csrfToken,
          'X-Requested-With': 'XMLHttpRequest'
        }
      }).then(function (response) {
        return response.json().catch(function () {
          return {};
        }).then(function (body) {
          if (!response.ok) {
            throw new Error(extractErrorMessage(body, fallbackMessage));
          }

          return body;
        });
      });
    }

    function escapeProtectionHtml(value) {
      return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
        return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[character];
      });
    }

    function loadProtectionOptions() {
      return fetch(mutationConfig.protectionOptionsUrl, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
      }).then(function (response) {
        return response.json().then(function (body) {
          if (!response.ok) throw new Error(extractErrorMessage(body, 'Učitavanje zaštite nije uspjelo.'));
          return body.data || {};
        });
      });
    }

    function openNewProtectionDialog() {
      return Swal.fire(swalWithTheme({
        title: 'Dodaj novu zaštitu',
        html: '<input id="wo-new-protection-code" class="swal2-input" placeholder="Kod, npr. Lakiranje RAL 9005">'
          + '<input id="wo-new-protection-name" class="swal2-input" placeholder="Naziv">'
          + '<input id="wo-new-protection-weeks" class="swal2-input" type="number" min="1" max="52" step="1" value="3" placeholder="Broj sedmica">',
        showCancelButton: true,
        confirmButtonText: 'Dodaj',
        cancelButtonText: 'Otkaži',
        showLoaderOnConfirm: true,
        preConfirm: function () {
          var popup = Swal.getPopup();
          var code = popup.querySelector('#wo-new-protection-code').value.trim();
          var name = popup.querySelector('#wo-new-protection-name').value.trim();
          var weeks = Number(popup.querySelector('#wo-new-protection-weeks').value);
          if (!code || !name || !Number.isInteger(weeks) || weeks < 1 || weeks > 52) {
            Swal.showValidationMessage('Unesite kod, naziv i cijeli broj sedmica od 1 do 52.');
            return false;
          }
          return requestMutation(mutationConfig.protectionOptionStoreUrl, { code: code, name: name, weeks: weeks }, 'Dodavanje zaštite nije uspjelo.')
            .catch(function (error) { Swal.showValidationMessage(error.message); });
        }
      }));
    }

    function openProtectionDialog() {
      if (!mutationConfig.protectionOptionsUrl || !mutationConfig.protectionUpdateUrl) return;
      loadProtectionOptions().then(function (data) {
        var selected = String(data.selected || '');
        var options = Array.isArray(data.options) ? data.options : [];
        var optionsHtml = '<option value="">Bez zaštite / nije odabrano</option>' + options.map(function (option) {
          var value = String(option.value == null ? option.code || '' : option.value);
          var label = String(option.label || option.code || '');
          var description = String(option.description || '');
          return '<option value="' + escapeProtectionHtml(value) + '"' + (String(option.code || '') === selected || value === selected ? ' selected' : '') + '>'
            + escapeProtectionHtml(label + (description ? ' — ' + description : '') + ' (' + String(option.weeks || 3) + ' sedm.)') + '</option>';
        }).join('');
        return Swal.fire(swalWithTheme({
          title: 'Dodaj zaštitu',
          html: '<select id="wo-protection-select" class="swal2-select" style="width:80%">' + optionsHtml + '</select>',
          showCancelButton: true,
          showDenyButton: Boolean(mutationConfig.protectionOptionStoreUrl),
          confirmButtonText: 'Sačuvaj',
          denyButtonText: 'Dodaj novu',
          cancelButtonText: 'Otkaži',
          showLoaderOnConfirm: true,
          preConfirm: function () {
            return requestMutation(mutationConfig.protectionUpdateUrl, {
              protection_type: Swal.getPopup().querySelector('#wo-protection-select').value
            }, 'Ažuriranje zaštite nije uspjelo.').catch(function (error) { Swal.showValidationMessage(error.message); });
          }
        }));
      }).then(function (result) {
        if (result && result.isDenied) return openNewProtectionDialog().then(function (createResult) {
          if (createResult && createResult.isConfirmed) return openProtectionDialog();
        });
        if (result && result.isConfirmed) {
          Swal.fire(swalWithTheme({ icon: 'success', title: 'Sačuvano', timer: 1100, showConfirmButton: false }))
            .then(function () { window.location.reload(); });
        }
      }).catch(function (error) {
        Swal.fire(swalWithTheme({ icon: 'error', title: 'Greška', text: error.message || 'Učitavanje zaštite nije uspjelo.' }));
      });
    }

    function validateWorkOrderClosing(showErrors) {
      var rows = Array.prototype.slice.call(document.querySelectorAll('.wo-close-operation-row[data-item-qid]'));
      var allValid = rows.length > 0;
      var numericPattern = /^(?:0|[1-9]\d*)(?:[.,]\d+)?$/;

      rows.forEach(function (row) {
        var worker = row.querySelector('.wo-close-worker');
        var time = row.querySelector('.wo-close-time');
        var downtime = row.querySelector('.wo-close-downtime');
        var timeValue = time ? String(time.value || '').trim() : '';
        var downtimeValue = downtime ? String(downtime.value || '').trim() : '';
        var startValue = readClockFieldValue(row, 'start');
        var endValue = readClockFieldValue(row, 'end');
        var hasClockInput = startValue !== '' || endValue !== '';
        var timeValid = !hasClockInput
          ? (timeValue === '' || numericPattern.test(timeValue))
          : (startValue !== null && endValue !== null && startValue !== '' && endValue !== '' && operationTimeRangeIsValid(row));
        var downtimeValid = downtimeValue === '' || numericPattern.test(downtimeValue);
        if (timeValid && downtimeValid && hasClockInput) {
          var grossMinutes = (clockMinutes(endValue) - clockMinutes(startValue)) - breakOverlapMinutes(clockMinutes(startValue), clockMinutes(endValue));
          downtimeValid = Number(downtimeValue.replace(',', '.') || 0) <= grossMinutes;
        }

        allValid = allValid && timeValid && downtimeValid;
        if (showErrors) {
          row.classList.toggle('is-invalid', !timeValid || !downtimeValid);
          if (time) {
            time.classList.toggle('wo-close-time-error', !timeValid);
          }
        } else if (timeValid && downtimeValid) {
          row.classList.remove('is-invalid');
          if (time) {
            time.classList.remove('wo-close-time-error');
          }
        }
      });

      if (closeWorkOrderSubmit && !closeWorkOrderSubmit.dataset.processing) {
        closeWorkOrderSubmit.disabled = !allValid;
      }

      return allValid;
    }

    function clockMinutes(value) {
      var match = /^(?:[01]\d|2[0-3]):[0-5]\d$/.exec(String(value || '').trim());
      if (!match) {
        return null;
      }

      var parts = match[0].split(':');
      return (Number(parts[0]) * 60) + Number(parts[1]);
    }

    function formatClockMinutes(minutes) {
      var hours = Math.floor(minutes / 60);
      var remainder = minutes % 60;
      return String(hours).padStart(2, '0') + ':' + String(remainder).padStart(2, '0');
    }

    function breakOverlapMinutes(operationStart, operationEnd) {
      var overlap = 0;

      for (var index = 0; index < workOrderBreaks.length; index++) {
        var workBreak = workOrderBreaks[index];
        overlap += Math.max(0, Math.min(operationEnd, workBreak[1]) - Math.max(operationStart, workBreak[0]));
      }

      return overlap;
    }

    function normalizeManualDuration(input) {
      var rawValue = String((input || {}).value || '').trim().replace(',', '.');
      if (!/^(?:0|[1-9]\d*)(?:\.\d+)?$/.test(rawValue)) {
        return;
      }
    }

    function clockFieldInputs(row, boundary) {
      return {
        hour: row && row.querySelector('.wo-close-' + boundary + '-hour'),
        minute: row && row.querySelector('.wo-close-' + boundary + '-minute'),
        hidden: row && row.querySelector('.wo-close-' + boundary + '-time')
      };
    }

    function readClockFieldValue(row, boundary) {
      var fields = clockFieldInputs(row, boundary);
      if (!fields.hour || !fields.minute || !fields.hidden) {
        return null;
      }

      var hour = String(fields.hour.value || '').trim();
      var minute = String(fields.minute.value || '').trim();
      if (hour === '' && minute === '') {
        fields.hidden.value = '';
        return '';
      }

      // Do not treat a first typed digit as a complete value. Formatting it
      // while the user is still typing was resetting values such as 25.
      if (!/^\d{2}$/.test(hour) || !/^\d{2}$/.test(minute)) {
        fields.hidden.value = '';
        return null;
      }

      var hours = Number(hour);
      var minutes = Number(minute);
      if (hours > 23 || minutes > 59) {
        fields.hidden.value = '';
        return null;
      }

      var value = formatClockMinutes((hours * 60) + minutes);
      fields.hidden.value = value;
      return value;
    }

    function setClockFieldValue(row, boundary, value) {
      var fields = clockFieldInputs(row, boundary);
      var minutes = clockMinutes(value);
      if (!fields.hour || !fields.minute || !fields.hidden) {
        return;
      }

      if (minutes === null) {
        fields.hour.value = '';
        fields.minute.value = '';
        fields.hidden.value = '';
        return;
      }

      var normalized = formatClockMinutes(minutes);
      var parts = normalized.split(':');
      fields.hour.value = parts[0];
      fields.minute.value = parts[1];
      fields.hidden.value = normalized;
    }

    function normalizeClockFieldsOnBlur(row, boundary) {
      var fields = clockFieldInputs(row, boundary);
      if (!fields.hour || !fields.minute) {
        return;
      }

      [
        [fields.hour, 23],
        [fields.minute, 59]
      ].forEach(function (entry) {
        var input = entry[0];
        var maximum = entry[1];
        var rawValue = String(input.value || '').trim();
        if (/^\d{1,2}$/.test(rawValue)) {
          input.value = String(Math.min(Number(rawValue), maximum)).padStart(2, '0');
        }
      });
    }

    function operationTimeRangeIsValid(row) {
      var startValue = readClockFieldValue(row, 'start');
      var endValue = readClockFieldValue(row, 'end');

      if (startValue === '' && endValue === '') {
        return true;
      }

      if (startValue === null || endValue === null || startValue === '' || endValue === '') {
        return false;
      }

      var startMinutes = clockMinutes(startValue);
      var endMinutes = clockMinutes(endValue);

      return startMinutes !== null && endMinutes !== null && endMinutes >= startMinutes;
    }

    function syncOperationTime(row) {
      var time = row.querySelector('.wo-close-time');
      var downtime = row.querySelector('.wo-close-downtime');

      if (!time) {
        return;
      }

      var startValue = readClockFieldValue(row, 'start');
      var endValue = readClockFieldValue(row, 'end');

      if (startValue !== null && endValue !== null && startValue !== '' && endValue !== '') {
        var startMinutes = clockMinutes(startValue);
        var endMinutes = clockMinutes(endValue);
        time.readOnly = true;
        time.classList.toggle('wo-close-time-error', startMinutes === null || endMinutes === null || endMinutes < startMinutes);
        var grossMinutes = (endMinutes - startMinutes) - breakOverlapMinutes(startMinutes, endMinutes);
        var downtimeMinutes = Number(String((downtime || {}).value || '').trim().replace(',', '.') || 0);
        var netMinutes = grossMinutes - downtimeMinutes;
        time.classList.toggle('wo-close-time-error', startMinutes === null || endMinutes === null || endMinutes < startMinutes || !Number.isFinite(downtimeMinutes) || netMinutes < 0);
        time.value = startMinutes !== null && endMinutes !== null && endMinutes >= startMinutes && Number.isFinite(downtimeMinutes) && netMinutes >= 0
          ? String(netMinutes)
          : '';
        return;
      }

      time.readOnly = false;
      time.classList.toggle('wo-close-time-error', startValue !== '' || endValue !== '');
    }

    function hideWorkerSuggestions(row) {
      var suggestions = row && row.querySelector('.wo-close-worker-suggestions');
      var input = row && row.querySelector('.wo-close-worker-search');
      if (suggestions) {
        suggestions.replaceChildren();
        suggestions.classList.add('d-none');
      }
      if (input) {
        input.dataset.workerSuggestionIndex = '-1';
      }
    }

    function showWorkerSuggestions(row, workers) {
      var suggestions = row && row.querySelector('.wo-close-worker-suggestions');
      if (!suggestions) {
        return;
      }

      suggestions.replaceChildren();
      (workers || []).forEach(function (worker) {
        var workerId = Number(worker && worker.id || 0);
        if (!Number.isFinite(workerId) || workerId < 1) {
          return;
        }

        var option = document.createElement('button');
        option.type = 'button';
        option.className = 'wo-close-worker-suggestion';
        option.setAttribute('role', 'option');
        option.dataset.workerId = String(workerId);
        option.dataset.workerText = String(worker.text || worker.worker || '');
        option.textContent = option.dataset.workerText;
        suggestions.appendChild(option);
      });

      if (!suggestions.childElementCount || !closeWorkOrderModal || !closeWorkOrderModal.classList.contains('show')) {
        suggestions.classList.add('d-none');
        return;
      }

      suggestions.classList.remove('d-none');
      var input = row.querySelector('.wo-close-worker-search');
      if (input) {
        input.dataset.workerSuggestionIndex = '-1';
      }
      positionWorkerSuggestions(row);
    }

    function positionWorkerSuggestions(row) {
      var input = row && row.querySelector('.wo-close-worker-search');
      var suggestions = row && row.querySelector('.wo-close-worker-suggestions');
      if (!input || !suggestions || suggestions.classList.contains('d-none') || !closeWorkOrderModal) {
        return;
      }

      var inputRect = input.getBoundingClientRect();
      var modalRect = closeWorkOrderModal.getBoundingClientRect();
      var width = Math.max(inputRect.width, 180);
      suggestions.style.width = Math.round(width) + 'px';
      suggestions.style.left = Math.round(inputRect.left) + 'px';

      var height = Math.min(suggestions.offsetHeight || 210, 210);
      var top = inputRect.bottom + 4;
      if (top + height > modalRect.bottom - 8) {
        top = Math.max(modalRect.top + 8, inputRect.top - height - 4);
      }
      suggestions.style.top = Math.round(top) + 'px';
    }

    function selectWorkerSuggestion(suggestion) {
      var suggestionRow = suggestion && suggestion.closest ? suggestion.closest('.wo-close-operation-row') : null;
      if (!suggestionRow) {
        return;
      }

      var search = suggestionRow.querySelector('.wo-close-worker-search');
      var worker = suggestionRow.querySelector('.wo-close-worker');
      if (search) search.value = suggestion.dataset.workerText || '';
      if (worker) worker.value = suggestion.dataset.workerId || '';
      hideWorkerSuggestions(suggestionRow);
      validateWorkOrderClosing(false);
    }

    function workerSuggestionOptions(row) {
      var suggestions = row && row.querySelector('.wo-close-worker-suggestions');
      if (!suggestions || suggestions.classList.contains('d-none')) {
        return [];
      }

      return Array.prototype.slice.call(suggestions.querySelectorAll('.wo-close-worker-suggestion'));
    }

    function highlightWorkerSuggestion(input, direction) {
      var row = input && input.closest ? input.closest('.wo-close-operation-row') : null;
      var options = workerSuggestionOptions(row);
      if (!row || !options.length) {
        return false;
      }

      var currentIndex = Number(input.dataset.workerSuggestionIndex || -1);
      var nextIndex = direction > 0
        ? Math.min(options.length - 1, currentIndex + 1)
        : Math.max(0, currentIndex < 0 ? options.length - 1 : currentIndex - 1);
      input.dataset.workerSuggestionIndex = String(nextIndex);
      options.forEach(function (option, index) {
        option.classList.toggle('is-active', index === nextIndex);
      });
      options[nextIndex].scrollIntoView({ block: 'nearest' });
      return true;
    }

    function selectedWorkerSuggestion(input) {
      var row = input && input.closest ? input.closest('.wo-close-operation-row') : null;
      var options = workerSuggestionOptions(row);
      var selectedIndex = Number(input && input.dataset.workerSuggestionIndex || -1);
      return selectedIndex >= 0 && selectedIndex < options.length ? options[selectedIndex] : null;
    }

    function closingFocusableFields(scope) {
      var root = scope || closeWorkOrderModal;
      if (!root) {
        return [];
      }

      return Array.prototype.slice.call(root.querySelectorAll(
        '.wo-close-operation-code, .wo-close-worker-search, .wo-close-start-hour, .wo-close-start-minute, .wo-close-end-hour, .wo-close-end-minute, .wo-close-time, .wo-close-downtime, .wo-close-copy-row-btn, .wo-close-clear-row-btn, .wo-close-delete-row-btn'
      )).filter(function (element) {
        return !element.disabled && element.offsetParent !== null;
      });
    }

    function focusClosingField(field) {
      if (!field) {
        return;
      }

      field.focus();
      if (field.tagName === 'INPUT' && field.type !== 'time' && typeof field.select === 'function') {
        field.select();
      }
    }

    function moveClosingFieldFocus(current, key) {
      var currentRow = current && current.closest ? current.closest('.wo-close-operation-row') : null;
      if (!currentRow || !closeWorkOrderModal) {
        return;
      }

      var rowFields = closingFocusableFields(currentRow);
      var columnIndex = rowFields.indexOf(current);
      if (columnIndex < 0) {
        return;
      }

      if (key === 'ArrowLeft' || key === 'ArrowRight') {
        focusClosingField(rowFields[columnIndex + (key === 'ArrowRight' ? 1 : -1)]);
        return;
      }

      var rows = Array.prototype.slice.call(closeWorkOrderModal.querySelectorAll('.wo-close-operation-row[data-item-qid]'));
      var rowIndex = rows.indexOf(currentRow);
      var targetRow = rows[rowIndex + (key === 'ArrowDown' ? 1 : -1)];
      if (!targetRow) {
        return;
      }

      // Up/down preserves the active column while moving to an adjacent row.
      focusClosingField(closingFocusableFields(targetRow)[columnIndex]);
    }

    function moveToNextClosingFieldInRow(current) {
      var row = current && current.closest ? current.closest('.wo-close-operation-row') : null;
      if (!row) {
        return;
      }

      var fields = closingFocusableFields(row);
      focusClosingField(fields[fields.indexOf(current) + 1]);
    }

    function resetCopyButtonVisualState(button) {
      if (!button) {
        return;
      }

      button.classList.remove('active');
      button.removeAttribute('aria-pressed');
      ['color', 'background-color', 'border-color', 'box-shadow'].forEach(function (property) {
        button.style.removeProperty(property);
      });
    }

    function resetCloseOperationActionButtons(row) {
      row.querySelectorAll('.wo-close-copy-row-btn').forEach(function (button) {
        resetCopyButtonVisualState(button);
      });
      row.querySelectorAll('.wo-close-clear-row-btn, .wo-close-delete-row-btn').forEach(function (button) {
        button.classList.remove('active');
        button.removeAttribute('aria-pressed');
        button.style.backgroundColor = '';
        button.style.color = '';
      });
    }

    function cloneCloseOperationRow(sourceRow, clearValues) {
      var copiedRow = sourceRow.cloneNode(true);
      copiedRow.classList.remove('is-invalid');
      resetCloseOperationActionButtons(copiedRow);
      hideWorkerSuggestions(copiedRow);

      if (clearValues) {
        var workerSearch = copiedRow.querySelector('.wo-close-worker-search');
        var worker = copiedRow.querySelector('.wo-close-worker');
        var time = copiedRow.querySelector('.wo-close-time');
        var downtime = copiedRow.querySelector('.wo-close-downtime');
        if (workerSearch) workerSearch.value = '';
        if (worker) worker.value = '';
        setClockFieldValue(copiedRow, 'start', '');
        setClockFieldValue(copiedRow, 'end', '');
        if (time) {
          time.value = '';
          time.readOnly = false;
        }
        if (downtime) downtime.value = '';
      }

      return copiedRow;
    }

    function nextClosingRowPosition(table) {
      if (!table) return 1;
      var positions = Array.prototype.slice.call(table.querySelectorAll('tbody tr > td:first-child'))
        .map(function (cell) { return Number(String(cell.textContent || '').trim()); })
        .filter(function (position) { return Number.isFinite(position) && position > 0; });
      return (positions.length ? Math.max.apply(null, positions) : 0) + 1;
    }

    function refreshCloseMaterialAddButtons() {
      if (!closeWorkOrderMaterialsTable) return;
      var rows = Array.prototype.slice.call(closeWorkOrderMaterialsTable.querySelectorAll('tbody tr'));
      rows.forEach(function (row) {
        var button = row.querySelector('.wo-close-add-material-row-btn');
        if (!button) return;
        button.disabled = false;
        button.removeAttribute('disabled');
        button.classList.remove('active');
        button.removeAttribute('aria-pressed');
        button.style.setProperty('background', '#fff', 'important');
        button.style.setProperty('background-color', '#fff', 'important');
        button.style.setProperty('color', '#7367f0', 'important');
        button.style.setProperty('opacity', '1', 'important');
      });
    }

    function createCloseMaterialRow(sourceRow) {
      var copiedRow = sourceRow.cloneNode(true);
      copiedRow.classList.remove('is-invalid');
      copiedRow.setAttribute('data-existing-material', '0');
      copiedRow.querySelectorAll('.wo-close-code-suggestions').forEach(function (suggestions) {
        suggestions.replaceChildren();
        suggestions.classList.add('d-none');
      });
      copiedRow.querySelectorAll('input').forEach(function (input) {
        input.classList.remove('is-invalid');
        input.removeAttribute('data-item-id');
        input.removeAttribute('data-item-no');
        input.removeAttribute('data-saved-quantity');
      });
      copiedRow.querySelectorAll('.wo-close-add-material-row-btn, .wo-close-material-clear-row-btn, .wo-close-material-delete-row-btn').forEach(function (button) {
        button.disabled = false;
        button.removeAttribute('disabled');
        button.classList.remove('active');
        button.removeAttribute('aria-pressed');
        if (button.classList.contains('wo-close-add-material-row-btn')) {
          button.style.setProperty('background', '#fff', 'important');
          button.style.setProperty('background-color', '#fff', 'important');
          button.style.setProperty('color', '#7367f0', 'important');
          button.style.setProperty('opacity', '1', 'important');
        }
      });
      if (copiedRow.children[0]) copiedRow.children[0].textContent = String(nextClosingRowPosition(closeWorkOrderMaterialsTable));
      var copiedUnitCell = copiedRow.querySelector('.wo-close-material-unit');
      var copiedRawStockCell = copiedRow.querySelector('.wo-close-material-raw-stock');
      if (copiedUnitCell) copiedUnitCell.textContent = '';
      if (copiedRawStockCell) copiedRawStockCell.textContent = '';
      return copiedRow;
    }

    function ensureOp30Rows() {
      if (!closeWorkOrderModal) {
        return;
      }

      var rows = Array.prototype.slice.call(closeWorkOrderModal.querySelectorAll('.wo-close-operation-row[data-operation-code="OP30"]'));
      if (!rows.length) {
        return;
      }

      var lastRow = rows[rows.length - 1];
      while (rows.length < 5) {
        var copiedRow = cloneCloseOperationRow(rows[0], true);
        lastRow.parentNode.insertBefore(copiedRow, lastRow.nextSibling);
        rows.push(copiedRow);
        lastRow = copiedRow;
      }
    }

    function searchPantheonWorkers(input) {
      var row = input && input.closest ? input.closest('.wo-close-operation-row') : null;
      var query = String((input || {}).value || '').trim();
      if (!row || query.length < 1 || !mutationConfig.workersUrl) {
        hideWorkerSuggestions(row);
        return;
      }

      var cacheKey = query.toLocaleLowerCase();
      var searchToken = String(Number(input.dataset.workerSearchToken || 0) + 1);
      input.dataset.workerSearchToken = searchToken;

      if (Object.prototype.hasOwnProperty.call(pantheonWorkerSearchCache, cacheKey)) {
        showWorkerSuggestions(row, pantheonWorkerSearchCache[cacheKey]);
        return;
      }

      var separator = mutationConfig.workersUrl.indexOf('?') === -1 ? '?' : '&';
      var url = mutationConfig.workersUrl + separator + 'q=' + encodeURIComponent(query);

      fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (response) {
          return response.ok ? response.json() : { data: [] };
        })
        .then(function (payload) {
          var workers = payload && Array.isArray(payload.data) ? payload.data : [];
          pantheonWorkerSearchCache[cacheKey] = workers;
          if (input.dataset.workerSearchToken !== searchToken || String(input.value || '').trim() !== query) {
            return;
          }
          showWorkerSuggestions(row, workers);
        })
        .catch(function () {
          hideWorkerSuggestions(row);
        });
    }

    function closingCatalogKind(input) {
      return input && input.classList.contains('wo-close-operation-code') ? 'operations' : 'materials';
    }

    function closingCatalogUrl(kind) {
      return kind === 'operations' ? mutationConfig.operationsUrl : mutationConfig.materialsUrl;
    }

    function closingCatalogFields(input) {
      var row = input && input.closest ? input.closest('tr') : null;
      if (!row) {
        return {};
      }

      var kind = closingCatalogKind(input);
      return {
        row: row,
        kind: kind,
        input: input,
        name: row.querySelector(kind === 'operations' ? '.wo-close-operation-name' : '.wo-close-material-name'),
        suggestions: row.querySelector('.wo-close-code-suggestions')
      };
    }

    function closingCatalogValue(item, key) {
      var value = (item || {})[key];
      return value === undefined || value === null ? '' : String(value).trim();
    }

    function closingCatalogItemCode(item) {
      return closingCatalogValue(item, 'acIdentChild');
    }

    function closingCatalogItemName(item) {
      return closingCatalogValue(item, 'acDescr');
    }

    function hideClosingCatalogSuggestions(input) {
      var fields = closingCatalogFields(input);
      if (fields.suggestions) {
        fields.suggestions.replaceChildren();
        fields.suggestions.classList.add('d-none');
      }
      if (input) input.dataset.catalogSuggestionIndex = '-1';
    }

    function positionClosingCatalogSuggestions(suggestions) {
      if (!suggestions || suggestions.classList.contains('d-none') || !closeWorkOrderModal) {
        return;
      }

      var input = suggestions.parentNode && suggestions.parentNode.querySelector('input');
      if (!input) {
        return;
      }

      var inputRect = input.getBoundingClientRect();
      var modalRect = closeWorkOrderModal.getBoundingClientRect();
      var height = Math.min(suggestions.offsetHeight || 210, 210);
      var top = inputRect.bottom + 4;
      if (top + height > modalRect.bottom - 8) {
        top = Math.max(modalRect.top + 8, inputRect.top - height - 4);
      }

      suggestions.style.width = Math.round(Math.max(inputRect.width, 180)) + 'px';
      suggestions.style.left = Math.round(inputRect.left) + 'px';
      suggestions.style.top = Math.round(top) + 'px';
    }

    function applyClosingCatalogItem(input, item) {
      var fields = closingCatalogFields(input);
      var code = closingCatalogItemCode(item);
      if (!fields.row || code === '') {
        return;
      }

      input.value = code;
      if (fields.name) {
        fields.name.value = closingCatalogItemName(item);
      }
      if (fields.kind === 'materials') {
        var unitCell = fields.row.querySelector('.wo-close-material-unit');
        var rawStockCell = fields.row.querySelector('.wo-close-material-raw-stock');
        if (unitCell) {
          unitCell.textContent = closingCatalogValue(item, 'acUM').toUpperCase();
        }
        if (rawStockCell) {
          rawStockCell.textContent = closingCatalogValue(item, 'raw_material_stock_qty');
        }
      }
      if (fields.kind === 'operations') {
        fields.row.setAttribute('data-operation-code', code.toUpperCase());
      }
      hideClosingCatalogSuggestions(input);
      validateWorkOrderClosing(false);
    }

    function showClosingCatalogSuggestions(input, items) {
      var fields = closingCatalogFields(input);
      if (!fields.suggestions) {
        return;
      }

      fields.suggestions.replaceChildren();
      (items || []).forEach(function (item) {
        var code = closingCatalogItemCode(item);
        if (code === '') {
          return;
        }

        var option = document.createElement('button');
        option.type = 'button';
        option.className = 'wo-close-code-suggestion';
        option.setAttribute('role', 'option');
        option.dataset.catalogCode = code;
        option.dataset.catalogName = closingCatalogItemName(item);
        option.dataset.catalogUnit = closingCatalogValue(item, 'acUM');
        option.dataset.catalogRawStock = closingCatalogValue(item, 'raw_material_stock_qty');
        option.textContent = code + (option.dataset.catalogName ? ' — ' + option.dataset.catalogName : '');
        fields.suggestions.appendChild(option);
      });

      if (!fields.suggestions.childElementCount || !closeWorkOrderModal || !closeWorkOrderModal.classList.contains('show')) {
        fields.suggestions.classList.add('d-none');
        return;
      }

      fields.suggestions.classList.remove('d-none');
      input.dataset.catalogSuggestionIndex = '-1';
      positionClosingCatalogSuggestions(fields.suggestions);
    }

    function useClosingCatalogResults(input, items, query) {
      if (String(input.value || '').trim() !== query) {
        return;
      }

      var exact = (items || []).find(function (item) {
        return closingCatalogItemCode(item).toUpperCase() === query.toUpperCase();
      });
      if (exact) {
        applyClosingCatalogItem(input, exact);
        return;
      }

      showClosingCatalogSuggestions(input, items);
    }

    function searchClosingCatalog(input) {
      var fields = closingCatalogFields(input);
      var query = String((input || {}).value || '').trim();
      var endpoint = closingCatalogUrl(fields.kind);
      if (!fields.row || query.length < 1 || !endpoint) {
        hideClosingCatalogSuggestions(input);
        return;
      }

      if (fields.name) {
        fields.name.value = '';
      }
      if (fields.kind === 'operations') {
        fields.row.setAttribute('data-operation-code', query.toUpperCase());
      }

      var cacheKey = query.toUpperCase();
      input.dataset.catalogSearchToken = String(Number(input.dataset.catalogSearchToken || 0) + 1);
      var searchToken = input.dataset.catalogSearchToken;
      if (Object.prototype.hasOwnProperty.call(closingCatalogSearchCache[fields.kind], cacheKey)) {
        useClosingCatalogResults(input, closingCatalogSearchCache[fields.kind][cacheKey], query);
        return;
      }

      var separator = endpoint.indexOf('?') === -1 ? '?' : '&';
      fetch(endpoint + separator + 'q=' + encodeURIComponent(query) + '&limit=15', {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
      })
        .then(function (response) {
          return response.ok ? response.json() : { data: [] };
        })
        .then(function (payload) {
          var items = payload && Array.isArray(payload.data) ? payload.data : [];
          closingCatalogSearchCache[fields.kind][cacheKey] = items;
          if (input.dataset.catalogSearchToken !== searchToken) {
            return;
          }
          useClosingCatalogResults(input, items, query);
        })
        .catch(function () {
          hideClosingCatalogSuggestions(input);
        });
    }

    function selectClosingCatalogSuggestion(suggestion) {
      var input = suggestion && suggestion.parentNode && suggestion.parentNode.parentNode
        ? suggestion.parentNode.parentNode.querySelector('.wo-close-operation-code, .wo-close-material-code')
        : null;
      if (!input) {
        return;
      }

      applyClosingCatalogItem(input, {
        acIdentChild: suggestion.dataset.catalogCode || '',
        acDescr: suggestion.dataset.catalogName || '',
        acUM: suggestion.dataset.catalogUnit || '',
        raw_material_stock_qty: suggestion.dataset.catalogRawStock || ''
      });
    }

    function highlightClosingCatalogSuggestion(input, direction) {
      var fields = closingCatalogFields(input);
      var options = fields.suggestions && !fields.suggestions.classList.contains('d-none')
        ? Array.prototype.slice.call(fields.suggestions.querySelectorAll('.wo-close-code-suggestion')) : [];
      if (!options.length) return false;
      var current = Number(input.dataset.catalogSuggestionIndex || -1);
      var next = direction > 0 ? Math.min(options.length - 1, current + 1) : Math.max(0, current < 0 ? options.length - 1 : current - 1);
      input.dataset.catalogSuggestionIndex = String(next);
      options.forEach(function (option, index) { option.classList.toggle('is-active', index === next); });
      options[next].scrollIntoView({ block: 'nearest' });
      return true;
    }

    function selectedClosingCatalogSuggestion(input) {
      var fields = closingCatalogFields(input);
      var index = Number(input.dataset.catalogSuggestionIndex || -1);
      var options = fields.suggestions ? fields.suggestions.querySelectorAll('.wo-close-code-suggestion') : [];
      return index >= 0 && options[index] ? options[index] : null;
    }

    function closingPayload() {
      return {
        operations: Array.prototype.slice.call(document.querySelectorAll('.wo-close-operation-row[data-item-qid]')).map(function (row) {
          syncOperationTime(row);
          var workerId = Number((row.querySelector('.wo-close-worker') || {}).value || 0);
          var itemQid = Number(row.getAttribute('data-item-qid') || 0);
          return {
            item_qid: itemQid > 0 ? itemQid : null,
            code: String((row.querySelector('.wo-close-operation-code') || {}).value || '').trim().toUpperCase(),
            worker_id: workerId > 0 ? workerId : null,
            time: String((row.querySelector('.wo-close-time') || {}).value || '').trim().replace(',', '.'),
            downtime: String((row.querySelector('.wo-close-downtime') || {}).value || '').trim().replace(',', '.'),
            start_time: String((row.querySelector('.wo-close-start-time') || {}).value || '').trim(),
            end_time: String((row.querySelector('.wo-close-end-time') || {}).value || '').trim()
          };
        }).filter(function (operation) {
          // A selected code without worker, duration, or time range is just a
          // placeholder. Do not submit it or let it block complete rows.
          return [operation.worker_id, operation.time, operation.downtime, operation.start_time, operation.end_time]
            .some(function (value) { return String(value || '').trim() !== ''; });
        }),
        materials: Array.prototype.slice.call(document.querySelectorAll('#close-work-order-materials-table tbody tr')).map(function (row) {
          var quantityInput = row.querySelector('.wo-close-material-quantity') || {};
          return {
            code: String((row.querySelector('.wo-close-material-code') || {}).value || '').trim().toUpperCase(),
            quantity: String(quantityInput.value || '').trim().replace(',', '.'),
            item_qid: Number(quantityInput.dataset.itemId || 0),
            is_new: row.getAttribute('data-existing-material') === '0'
          };
        }).filter(function (material) {
          return material.code !== '' || material.quantity !== '';
        }),
        receipts: Array.prototype.slice.call(document.querySelectorAll('#close-work-order-receipts-table tbody tr')).map(function (row) {
          return {
            target: String(row.getAttribute('data-receipt-target') || '').trim(),
            quantity: String((row.querySelector('.wo-close-receipt-quantity') || {}).value || '').trim().replace(',', '.')
          };
        })
      };
    }

    function addScrapReceiptRow() {
      var table = document.getElementById('close-work-order-receipts-table');
      var addButton = document.getElementById('wo-close-add-scrap-receipt-btn');
      if (!table || table.querySelector('tr[data-receipt-target="scrap"]')) return;
      var vpRow = table.querySelector('tr[data-receipt-target="vp"]');
      if (!vpRow) return;
      var row = vpRow.cloneNode(true);
      row.setAttribute('data-receipt-target', 'scrap');
      row.children[0].textContent = 'Skladište škarta';
      var quantity = row.querySelector('.wo-close-receipt-quantity');
      if (quantity) quantity.value = '';
      row.children[row.children.length - 1].innerHTML = '<button type="button" class="btn btn-outline-danger btn-sm wo-close-remove-scrap-receipt-btn" title="Ukloni"><i class="fa fa-trash"></i></button>';
      table.querySelector('tbody').appendChild(row);
      if (addButton) addButton.disabled = true;
    }

    function isClosingOperationRowEmpty(row) {
      return [
        (row.querySelector('.wo-close-worker-search') || {}).value,
        (row.querySelector('.wo-close-worker') || {}).value,
        (row.querySelector('.wo-close-time') || {}).value,
        (row.querySelector('.wo-close-downtime') || {}).value,
        (row.querySelector('.wo-close-start-hour') || {}).value,
        (row.querySelector('.wo-close-start-minute') || {}).value,
        (row.querySelector('.wo-close-end-hour') || {}).value,
        (row.querySelector('.wo-close-end-minute') || {}).value
      ].every(function (value) {
        return String(value || '').trim() === '';
      });
    }

    function missingClosingFields() {
      return Array.prototype.slice.call(document.querySelectorAll('.wo-close-operation-row[data-item-qid]'))
        .map(function (row) {
          syncOperationTime(row);
          var operationCode = String(row.getAttribute('data-operation-code') || '').trim().toUpperCase();
          if (isClosingOperationRowEmpty(row)) {
            return null;
          }

          var missing = [];
          if (Number((row.querySelector('.wo-close-worker') || {}).value || 0) < 1) {
            missing.push('radnik');
          }
          if (String((row.querySelector('.wo-close-time') || {}).value || '').trim() === '') {
            missing.push('trajanje');
          }

          if (!missing.length) {
            return null;
          }

          var position = String((row.children[0] || {}).textContent || '').trim();
          return {
            label: (operationCode || 'Operacija') + (position ? ' (pozicija ' + position + ')' : ''),
            fields: missing
          };
        })
        .filter(function (row) { return row !== null; });
    }

    function escapeClosingHtml(value) {
      return String(value === null || typeof value === 'undefined' ? '' : value)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function closingResultHtml(data) {
      var documents = data && Array.isArray(data.documents) ? data.documents : [];
      var notices = data && Array.isArray(data.notices) ? data.notices : [];
      if (!documents.length && !notices.length) {
        return '';
      }

      var rows = documents.map(function (document) {
        var lines = [
          '<strong>' + escapeClosingHtml(document.document_number || '') + '</strong> (' + escapeClosingHtml(document.document_type || '') + ')'
        ];
        if (document.item_code) lines.push('Artikal: ' + escapeClosingHtml(document.item_code));
        if (document.work_order_code) lines.push('Radni nalog: ' + escapeClosingHtml(document.work_order_code));
        if (document.quantity) lines.push('Količina: ' + escapeClosingHtml(document.quantity));
        if (document.price_per_unit) lines.push('Cijena po jedinici: ' + escapeClosingHtml(document.price_per_unit));
        if (document.total_price) lines.push('Ukupna cijena: ' + escapeClosingHtml(document.total_price));
        if (document.operation_cost) lines.push('Trošak operacija: ' + escapeClosingHtml(document.operation_cost));
        if (document.material_cost) lines.push('Trošak materijala: ' + escapeClosingHtml(document.material_cost));
        return '<div class="text-start py-2 border-top">' + lines.join('<br>') + '</div>';
      }).join('');

      var noticesHtml = notices.length
        ? '<div class="alert alert-warning text-start mb-2" role="alert">' + notices.map(escapeClosingHtml).join('<br>') + '</div>'
        : '';

      return '<div class="text-start">'
        + noticesHtml
        + '<div class="fw-bold pb-2">Kreirani radni dokumenti</div>'
        + rows
        + '</div>';
    }

    if (closeWorkOrderModal) {
      closeWorkOrderModal.addEventListener('shown.bs.modal', function () {
        ensureOp30Rows();
        closeWorkOrderModal.querySelectorAll('.wo-close-operation-row').forEach(function (row) {
          resetCloseOperationActionButtons(row);
          syncOperationTime(row);
        });
        refreshCloseMaterialAddButtons();
        validateWorkOrderClosing(false);
      });

      closeWorkOrderModal.addEventListener('input', function (event) {
        var target = event.target;
        if (target && target.matches('.wo-close-operation-code, .wo-close-material-code')) {
          searchClosingCatalog(target);
        }
        var row = target && target.closest ? target.closest('.wo-close-operation-row') : null;
        if (!row) {
          return;
        }

        if (target.classList.contains('wo-close-worker-search')) {
          var worker = row.querySelector('.wo-close-worker');
          if (worker) worker.value = '';
          searchPantheonWorkers(target);
        }

        if (target.matches('.wo-close-start-hour, .wo-close-start-minute, .wo-close-end-hour, .wo-close-end-minute, .wo-close-downtime')) {
          syncOperationTime(row);
        }

        if (target.classList.contains('wo-close-time')) {
          normalizeManualDuration(target);
        }

        if (target.classList.contains('wo-close-time') || target.classList.contains('wo-close-downtime') || target.matches('.wo-close-start-hour, .wo-close-start-minute, .wo-close-end-hour, .wo-close-end-minute') || target.classList.contains('wo-close-worker-search')) {
          validateWorkOrderClosing(false);
        }
      });

      closeWorkOrderModal.addEventListener('click', function (event) {
        if (event.target.closest('#wo-close-add-scrap-receipt-btn')) {
          addScrapReceiptRow();
          return;
        }
        if (event.target.closest('.wo-close-remove-scrap-receipt-btn')) {
          var row = event.target.closest('tr[data-receipt-target="scrap"]');
          if (row) row.remove();
          var addButton = document.getElementById('wo-close-add-scrap-receipt-btn');
          if (addButton) addButton.disabled = false;
        }
        var addMaterial = event.target.closest('.wo-close-add-material-row-btn');
        if (addMaterial && closeWorkOrderMaterialsTable) {
          var sourceMaterialRow = addMaterial.closest('tr');
          var newMaterialRow = createCloseMaterialRow(sourceMaterialRow);
          sourceMaterialRow.parentNode.insertBefore(newMaterialRow, sourceMaterialRow.nextSibling);
          refreshCloseMaterialAddButtons();
          var codeInput = newMaterialRow.querySelector('.wo-close-material-code');
          if (codeInput) codeInput.focus();
          return;
        }
        var clearMaterial = event.target.closest('.wo-close-material-clear-row-btn');
        if (clearMaterial) {
          var materialRow = clearMaterial.closest('tr');
          materialRow.querySelectorAll('.wo-close-material-code, .wo-close-material-name, .wo-close-material-quantity').forEach(function (input) { input.value = ''; });
          var materialUnitCell = materialRow.querySelector('.wo-close-material-unit');
          var materialRawStockCell = materialRow.querySelector('.wo-close-material-raw-stock');
          if (materialUnitCell) materialUnitCell.textContent = '';
          if (materialRawStockCell) materialRawStockCell.textContent = '';
          return;
        }
        var deleteMaterial = event.target.closest('.wo-close-material-delete-row-btn');
        if (deleteMaterial) {
          var deleteRow = deleteMaterial.closest('tr');
          var rows = closeWorkOrderMaterialsTable ? closeWorkOrderMaterialsTable.querySelectorAll('tbody tr') : [];
          if (rows.length > 1) deleteRow.remove(); else deleteRow.querySelector('.wo-close-material-code').value = '';
          refreshCloseMaterialAddButtons();
          validateWorkOrderClosing(false);
        }
      });

      closeWorkOrderModal.addEventListener('keydown', function (event) {
        if (event.ctrlKey || event.altKey || event.metaKey) {
          return;
        }

        var field = event.target;
        if (!field || !field.matches('.wo-close-operation-code, .wo-close-material-code, .wo-close-worker-search, .wo-close-start-hour, .wo-close-start-minute, .wo-close-end-hour, .wo-close-end-minute, .wo-close-time, .wo-close-downtime, .wo-close-copy-row-btn, .wo-close-clear-row-btn, .wo-close-delete-row-btn')) {
          return;
        }

        if (field.matches('.wo-close-operation-code, .wo-close-material-code')) {
          if ((event.key === 'ArrowDown' || event.key === 'ArrowUp') && highlightClosingCatalogSuggestion(field, event.key === 'ArrowDown' ? 1 : -1)) { event.preventDefault(); return; }
          if (event.key === 'Enter') {
            var catalogOption = selectedClosingCatalogSuggestion(field);
            if (catalogOption) { event.preventDefault(); selectClosingCatalogSuggestion(catalogOption); }
            return;
          }
        }

        if (field.classList.contains('wo-close-worker-search')) {
          if ((event.key === 'ArrowDown' || event.key === 'ArrowUp') && highlightWorkerSuggestion(field, event.key === 'ArrowDown' ? 1 : -1)) {
            event.preventDefault();
            return;
          }

          if (event.key === 'Enter') {
            var suggestion = selectedWorkerSuggestion(field);
            event.preventDefault();
            if (suggestion) {
              selectWorkerSuggestion(suggestion);
            } else {
              moveToNextClosingFieldInRow(field);
            }
            return;
          }
        }

        if (event.key === 'Enter' && field.matches('.wo-close-worker-search, .wo-close-start-hour, .wo-close-start-minute, .wo-close-end-hour, .wo-close-end-minute, .wo-close-time, .wo-close-downtime')) {
          event.preventDefault();
          moveToNextClosingFieldInRow(field);
          return;
        }

        if (!['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key)) {
          return;
        }

        event.preventDefault();
        moveClosingFieldFocus(field, event.key);
      });

      closeWorkOrderModal.addEventListener('click', function (event) {
        var target = event.target;
        var catalogSuggestion = target && target.closest ? target.closest('.wo-close-code-suggestion') : null;
        if (catalogSuggestion) {
          event.preventDefault();
          selectClosingCatalogSuggestion(catalogSuggestion);
          return;
        }
        var suggestion = target && target.closest ? target.closest('.wo-close-worker-suggestion') : null;
        if (suggestion) {
          selectWorkerSuggestion(suggestion);
          return;
        }

        var copyButton = target && target.closest ? target.closest('.wo-close-copy-row-btn') : null;
        if (copyButton) {
          event.preventDefault();
          var sourceRow = copyButton.closest('.wo-close-operation-row');
          resetCopyButtonVisualState(copyButton);
          var copiedRow = cloneCloseOperationRow(sourceRow, false);
          if (copiedRow.children[0]) copiedRow.children[0].textContent = String(nextClosingRowPosition(sourceRow.closest('table')));
          sourceRow.parentNode.insertBefore(copiedRow, sourceRow.nextSibling);
          syncOperationTime(copiedRow);
          validateWorkOrderClosing(false);
          window.setTimeout(function () {
            resetCopyButtonVisualState(copyButton);
            copyButton.blur();
          }, 0);
          return;
        }

        var clearButton = target && target.closest ? target.closest('.wo-close-clear-row-btn') : null;
        if (clearButton) {
          event.preventDefault();
          var rowToClear = clearButton.closest('.wo-close-operation-row');
          var clearedWorkerSearch = rowToClear.querySelector('.wo-close-worker-search');
          var clearedWorker = rowToClear.querySelector('.wo-close-worker');
          var clearedTime = rowToClear.querySelector('.wo-close-time');
          var clearedDowntime = rowToClear.querySelector('.wo-close-downtime');
          if (clearedWorkerSearch) clearedWorkerSearch.value = '';
          if (clearedWorker) clearedWorker.value = '';
          if (clearedTime) {
            clearedTime.value = '';
            clearedTime.readOnly = false;
            clearedTime.classList.remove('wo-close-time-error');
          }
          if (clearedDowntime) clearedDowntime.value = '';
          setClockFieldValue(rowToClear, 'start', '');
          setClockFieldValue(rowToClear, 'end', '');
          hideWorkerSuggestions(rowToClear);
          rowToClear.classList.remove('is-invalid');
          resetCloseOperationActionButtons(rowToClear);
          validateWorkOrderClosing(false);
          clearButton.blur();
          return;
        }

        var deleteButton = target && target.closest ? target.closest('.wo-close-delete-row-btn') : null;
        if (deleteButton) {
          event.preventDefault();
          var rowToDelete = deleteButton.closest('.wo-close-operation-row');
          var itemQid = rowToDelete.getAttribute('data-item-qid');
          var matchingRows = closeWorkOrderModal.querySelectorAll('.wo-close-operation-row[data-item-qid="' + itemQid + '"]');
          if (matchingRows.length > 1) {
            rowToDelete.remove();
          } else {
            rowToDelete.classList.add('is-invalid');
          }
          validateWorkOrderClosing(false);
          deleteButton.classList.remove('active');
          window.setTimeout(function () { deleteButton.blur(); }, 0);
        }
      });

      closeWorkOrderModal.addEventListener('mousedown', function (event) {
        var catalogSuggestion = event.target && event.target.closest ? event.target.closest('.wo-close-code-suggestion') : null;
        if (catalogSuggestion) {
          event.preventDefault();
          selectClosingCatalogSuggestion(catalogSuggestion);
          return;
        }
        var suggestion = event.target && event.target.closest ? event.target.closest('.wo-close-worker-suggestion') : null;
        if (suggestion) {
          // Choose on pointer down, before the textbox blur can hide the popup.
          event.preventDefault();
          selectWorkerSuggestion(suggestion);
          return;
        }

        var actionButton = event.target && event.target.closest
          ? event.target.closest('.wo-close-copy-row-btn, .wo-close-clear-row-btn, .wo-close-delete-row-btn, .wo-close-add-material-row-btn, .wo-close-material-clear-row-btn, .wo-close-material-delete-row-btn')
          : null;
        if (actionButton) {
          // Do not let Bootstrap keep an action button in a focused/active state.
          event.preventDefault();
          if (actionButton.classList.contains('wo-close-copy-row-btn')) {
            resetCopyButtonVisualState(actionButton);
          } else {
            actionButton.classList.remove('active');
          }
          actionButton.blur();
        }
      });

      closeWorkOrderModal.addEventListener('scroll', function () {
        closeWorkOrderModal.querySelectorAll('.wo-close-worker-suggestions:not(.d-none)').forEach(function (suggestions) {
          positionWorkerSuggestions(suggestions.closest('.wo-close-operation-row'));
        });
        closeWorkOrderModal.querySelectorAll('.wo-close-code-suggestions:not(.d-none)').forEach(function (suggestions) {
          positionClosingCatalogSuggestions(suggestions);
        });
      }, true);

      closeWorkOrderModal.addEventListener('focusout', function (event) {
        var input = event.target;
        if (!input) {
          return;
        }
        if (input.matches('.wo-close-operation-code, .wo-close-material-code')) {
          window.setTimeout(function () { hideClosingCatalogSuggestions(input); }, 150);
          return;
        }
        var row = input.closest('.wo-close-operation-row');
        if (input.classList.contains('wo-close-worker-search')) {
          window.setTimeout(function () { hideWorkerSuggestions(row); }, 150);
          return;
        }
        if (input.matches('.wo-close-start-hour, .wo-close-start-minute, .wo-close-end-hour, .wo-close-end-minute')) {
          var boundary = input.matches('.wo-close-start-hour, .wo-close-start-minute') ? 'start' : 'end';
          normalizeClockFieldsOnBlur(row, boundary);
          syncOperationTime(row);
          validateWorkOrderClosing(false);
        }
      });
    }

    if (closeWorkOrderSubmit) {
      closeWorkOrderSubmit.addEventListener('click', function () {
        if (!validateWorkOrderClosing(true) || !mutationConfig.closeUrl) {
          return;
        }

        if (!closeWorkOrderSubmit.dataset.confirmedIncompleteFields) {
          var missing = missingClosingFields();
          if (missing.length) {
            var missingRowsHtml = missing.map(function (row) {
              return '<li><strong>' + escapeClosingHtml(row.label) + '</strong>: ' + escapeClosingHtml(row.fields.join(', ')) + '</li>';
            }).join('');
            Swal.fire(swalWithTheme({
              icon: 'warning',
              title: 'Nedostaju obavezna polja',
              html: '<p class="text-start mb-50">Sljedeće stavke nisu u potpunosti popunjene:</p><ul class="text-start">' + missingRowsHtml + '</ul><p class="text-start mb-0">Želite li ipak nastaviti zatvaranje radnog naloga?</p>',
              showCancelButton: true,
              confirmButtonText: 'Nastavi zatvaranje',
              cancelButtonText: 'Vrati se',
              customClass: { confirmButton: 'btn btn-warning me-1', cancelButton: 'btn btn-outline-secondary' },
              buttonsStyling: false
            })).then(function (result) {
              if (result.isConfirmed) {
                closeWorkOrderSubmit.dataset.confirmedIncompleteFields = '1';
                closeWorkOrderSubmit.click();
              }
            });
            return;
          }
        }
        delete closeWorkOrderSubmit.dataset.confirmedIncompleteFields;

        // Closing-modal material rows are document-only; never save them to the BOM.
        if (false && !closeWorkOrderSubmit.dataset.materialsSavedForClose) {
          setActionButtonLoading(closeWorkOrderSubmit, true);
          savePendingClosingMaterialQuantities()
            .then(function () {
              setActionButtonLoading(closeWorkOrderSubmit, false);
              closeWorkOrderSubmit.dataset.materialsSavedForClose = '1';
              closeWorkOrderSubmit.click();
            })
            .catch(function (error) {
              setActionButtonLoading(closeWorkOrderSubmit, false);
              Swal.fire(swalWithTheme({
                icon: 'error',
                title: 'Količina materijala nije sačuvana',
                text: error && error.message ? error.message : 'Pokušajte ponovo.'
              }));
            });
          return;
        }
        delete closeWorkOrderSubmit.dataset.materialsSavedForClose;

        closeWorkOrderSubmit.dataset.processing = '1';
        setActionButtonLoading(closeWorkOrderSubmit, true);
        if (closeWorkOrderButton) {
          closeWorkOrderButton.disabled = true;
          closeWorkOrderButton.setAttribute('aria-disabled', 'true');
        }

        requestMutation(mutationConfig.closeUrl, closingPayload(), 'Zatvaranje radnog naloga nije uspjelo.')
          .then(function (response) {
            var data = response && response.data ? response.data : {};
            var partialClose = String(data.status || '').toLocaleLowerCase().indexOf('djelomi') !== -1;
            hideModal(closeWorkOrderModal);

            if (closeWorkOrderButton) {
              var label = closeWorkOrderButton.querySelector('.wo-close-order-label');
              if (label) label.textContent = partialClose ? 'Nastavi zatvaranje' : 'Nalog zaključen';
              closeWorkOrderButton.title = partialClose ? 'Radni nalog je djelomično zaključen' : 'Radni nalog je zaključen';
              closeWorkOrderButton.disabled = !partialClose;
              closeWorkOrderButton.setAttribute('aria-disabled', partialClose ? 'false' : 'true');
            }
            if (partialClose && closeWorkOrderSubmit) {
              delete closeWorkOrderSubmit.dataset.processing;
              setActionButtonLoading(closeWorkOrderSubmit, false);
              validateWorkOrderClosing(false);
            }
            if (statusLabel) statusLabel.textContent = partialClose ? 'Djelomično zaključen' : 'Zaključen';
            updateSideButtonTone(statusTriggerButton, partialClose ? 'warning' : 'success');

            return Swal.fire(swalWithTheme({
              icon: 'success',
              title: partialClose ? (response.message || data.message || 'Radni nalog je djelomično zaključen') : 'Radni nalog zaključen',
              html: closingResultHtml(data),
              confirmButtonText: 'U redu',
              customClass: { confirmButton: 'btn btn-success' },
              buttonsStyling: false
            }));
          })
          .catch(function (error) {
            delete closeWorkOrderSubmit.dataset.processing;
            setActionButtonLoading(closeWorkOrderSubmit, false);
            validateWorkOrderClosing(false);
            if (closeWorkOrderButton) {
              closeWorkOrderButton.disabled = false;
              closeWorkOrderButton.removeAttribute('aria-disabled');
            }
            Swal.fire(swalWithTheme({
              icon: 'error',
              title: 'Zatvaranje nije uspjelo',
              text: error && error.message ? error.message : 'Sve promjene su poništene.'
            }));
          });
      });
    }

    function updateSideButtonTone(button, resolvedTone) {
      if (!button) {
        return;
      }

      toneClasses.forEach(function (toneClass) {
        button.classList.remove('wo-side-meta-btn-' + toneClass);
      });
      button.classList.add('wo-side-meta-btn-' + resolvedTone);
    }

    function resolveStatusToneClass(statusValue) {
      var normalized = (statusValue || '').toString().trim().toLowerCase();

      if (normalized.indexOf('otvoren') !== -1) {
        return 'success';
      }

      if (normalized.indexOf('u radu') !== -1 || normalized.indexOf('u toku') !== -1) {
        return 'warning';
      }

      if (normalized.indexOf('planiran') !== -1 || normalized.indexOf('novo') !== -1) {
        return 'primary';
      }

      if (normalized.indexOf('rezerv') !== -1) {
        return 'info';
      }

      if (normalized.indexOf('djelimic') !== -1 || normalized.indexOf('djelomi') !== -1) {
        return 'warning';
      }

      if (normalized.indexOf('zavr') !== -1 || normalized.indexOf('zaklj') !== -1) {
        return 'danger';
      }

      return 'secondary';
    }

    function resolvePriorityToneClass(priorityValue) {
      var normalized = (priorityValue || '').toString().trim();
      var codeMatch = normalized.match(/^\s*(\d+)/);
      var code = codeMatch ? parseInt(codeMatch[1], 10) : 0;

      if (code === 1) {
        return 'danger';
      }

      if (code === 5) {
        return 'warning';
      }

      if (code >= 10) {
        return 'info';
      }

      return 'secondary';
    }

    function hideModal(modalElement) {
      if (!modalElement || !window.bootstrap || !window.bootstrap.Modal) {
        return;
      }

      window.bootstrap.Modal.getOrCreateInstance(modalElement).hide();
    }

    function ensureSastavnicaEmptyState() {
      if (!sastavnicaTable) {
        return;
      }

      var body = sastavnicaTable.querySelector('tbody');
      if (!body) {
        return;
      }

      var rows = body.querySelectorAll('tr');
      if (rows.length > 0) {
        return;
      }

      var emptyRow = document.createElement('tr');
      emptyRow.innerHTML = '<td colspan="{{ $sastavnicaEmptyColspan }}" class="text-center text-muted py-2">Nema stavki za ovaj radni nalog.</td>';
      body.appendChild(emptyRow);
    }

    function ensureTabTableEmptyState(tableElement, colspan, message) {
      if (!tableElement) {
        return;
      }

      var body = tableElement.querySelector('tbody');
      if (!body) {
        return;
      }

      var rows = body.querySelectorAll('tr');
      if (rows.length > 0) {
        return;
      }

      var emptyRow = document.createElement('tr');
      emptyRow.innerHTML = '<td colspan="' + String(colspan) + '" class="text-center text-muted py-2">' + String(message || '') + '</td>';
      body.appendChild(emptyRow);
    }

    function showModal(modalElement) {
      if (!modalElement || !window.bootstrap || !window.bootstrap.Modal) {
        return;
      }

      window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
    }

    function clearEditSastavnicaError() {
      if (!editSastavnicaError) {
        return;
      }

      editSastavnicaError.textContent = '';
      editSastavnicaError.classList.add('d-none');
    }

    function setEditSastavnicaError(message) {
      if (!editSastavnicaError) {
        return;
      }

      editSastavnicaError.textContent = String(message || 'Ažuriranje stavke nije uspjelo.');
      editSastavnicaError.classList.remove('d-none');
    }

    function displaySastavnicaCellValue(value) {
      var normalizedValue = value === null || typeof value === 'undefined'
        ? ''
        : String(value).trim();

      return normalizedValue === '' ? '-' : normalizedValue;
    }

    function formatSastavnicaQuantity(value) {
      var normalizedValue = value === null || typeof value === 'undefined'
        ? ''
        : String(value).trim();

      if (!normalizedValue) {
        return '-';
      }

      var parsedValue = Number(normalizedValue.replace(',', '.'));
      if (!Number.isFinite(parsedValue)) {
        return displaySastavnicaCellValue(value);
      }

      var formattedValue = parsedValue.toFixed(6).replace(/0+$/, '').replace(/\.$/, '');
      return formattedValue === '-0' ? '0' : formattedValue;
    }

    function truncateSastavnicaNote(value) {
      var normalizedValue = value === null || typeof value === 'undefined'
        ? ''
        : String(value).trim();

      if (normalizedValue.length <= 20) {
        return normalizedValue || '-';
      }

      return normalizedValue.slice(0, 18) + '..';
    }

    function materialQuantityValuesMatch(left, right) {
      var leftValue = Number(String(left || '').trim().replace(',', '.'));
      var rightValue = Number(String(right || '').trim().replace(',', '.'));

      return Number.isFinite(leftValue) && Number.isFinite(rightValue) && Math.abs(leftValue - rightValue) < 0.000001;
    }

    function syncMaterialQuantityInputs(itemId, itemNo, savedQuantity) {
      Array.prototype.slice.call(document.querySelectorAll('.wo-material-quantity-input')).forEach(function (candidate) {
        var candidateItemId = String(candidate.getAttribute('data-item-id') || '').trim();
        var candidateItemNo = String(candidate.getAttribute('data-item-no') || '').trim();
        var sameItem = itemId !== '' ? candidateItemId === itemId : candidateItemNo === itemNo;

        if (sameItem) {
          candidate.value = formatSastavnicaQuantity(savedQuantity);
          candidate.dataset.savedQuantity = String(savedQuantity);
          candidate.classList.remove('is-invalid');
        }
      });
    }

    function saveMaterialQuantity(button, suppressError) {
      var row = button && button.closest ? button.closest('tr') : null;
      var input = row && row.querySelector('.wo-material-quantity-input');
      if (!button || !input || !mutationConfig.plannedConsumptionUpdateUrl) {
        return suppressError ? Promise.reject(new Error('Material quantity update is unavailable.')) : Promise.resolve(null);
      }

      var itemId = String(input.getAttribute('data-item-id') || '').trim();
      var itemNo = String(input.getAttribute('data-item-no') || '').trim();
      var quantity = Number(String(input.value || '').trim().replace(',', '.'));
      if ((!itemId && !itemNo) || !Number.isFinite(quantity) || quantity < 0) {
        input.classList.add('is-invalid');
        return suppressError ? Promise.reject(new Error('Material quantity must be a non-negative number.')) : Promise.resolve(null);
      }

      input.classList.remove('is-invalid');
      setActionButtonLoading(button, true);

      return requestMutation(mutationConfig.plannedConsumptionUpdateUrl, {
        item_id: itemId ? Number(itemId) : null,
        item_no: itemNo ? Number(itemNo) : null,
        kolicina: quantity
      }, 'Ažuriranje količine materijala nije uspjelo.')
        .then(function (response) {
          var item = response && response.data && response.data.item ? response.data.item : {};
          var savedQuantity = item.kolicina !== undefined && item.kolicina !== null
            ? item.kolicina
            : quantity;
          syncMaterialQuantityInputs(itemId, itemNo, savedQuantity);
          setActionButtonLoading(button, false);
          return savedQuantity;
        })
        .catch(function (error) {
          setActionButtonLoading(button, false);
          input.classList.add('is-invalid');
          if (suppressError) {
            throw error;
          }
          Swal.fire(swalWithTheme({
            icon: 'error',
            title: 'Količina nije sačuvana',
            text: error && error.message ? error.message : 'Pokušajte ponovo.'
          }));
        });
    }

    function savePendingClosingMaterialQuantities() {
      if (!closeWorkOrderMaterialsTable) {
        return Promise.resolve();
      }

      var buttons = Array.prototype.slice.call(closeWorkOrderMaterialsTable.querySelectorAll('.wo-material-save-quantity-btn'))
        .filter(function (button) {
          var input = button.closest('tr').querySelector('.wo-material-quantity-input');
          return input && !input.disabled && !materialQuantityValuesMatch(input.value, input.dataset.savedQuantity);
        });

      return Promise.all(buttons.map(function (button) {
        return saveMaterialQuantity(button, true);
      }));
    }

    function bindMaterialQuantityTable(table) {
      if (!table) {
        return;
      }

      table.addEventListener('click', function (event) {
        var button = event.target && event.target.closest
          ? event.target.closest('.wo-material-save-quantity-btn')
          : null;
        if (button) {
          saveMaterialQuantity(button);
        }
      });

      table.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter' || !event.target.classList.contains('wo-material-quantity-input')) {
          return;
        }
        event.preventDefault();
        var row = event.target.closest('tr');
        saveMaterialQuantity(row && row.querySelector('.wo-material-save-quantity-btn'));
      });
    }

    bindMaterialQuantityTable(materijaliTable);
    bindMaterialQuantityTable(closeWorkOrderMaterialsTable);

    function openEditSastavnicaModal(button) {
      if (!button || !editSastavnicaModalElement) {
        return;
      }

      var row = button.closest('tr');
      if (!row) {
        return;
      }

      activeSastavnicaEditContext = {
        button: button,
        row: row,
        itemId: String(button.getAttribute('data-item-id') || '').trim(),
        itemNo: String(button.getAttribute('data-item-no') || '').trim()
      };

      clearEditSastavnicaError();

      if (editSastavnicaCodeInput) {
        editSastavnicaCodeInput.value = String(button.getAttribute('data-item-code') || '').trim();
      }

      if (editSastavnicaPositionInput) {
        editSastavnicaPositionInput.value = String(button.getAttribute('data-item-position') || '').trim();
      }

      if (editSastavnicaDescriptionInput) {
        editSastavnicaDescriptionInput.value = String(button.getAttribute('data-item-description') || '').trim();
      }

      if (editSastavnicaQuantityInput) {
        editSastavnicaQuantityInput.value = String(button.getAttribute('data-item-quantity') || '').trim();
      }

      if (editSastavnicaUnitInput) {
        editSastavnicaUnitInput.value = String(button.getAttribute('data-item-unit') || '').trim().toUpperCase();
      }

      if (editSastavnicaNoteInput) {
        editSastavnicaNoteInput.value = String(button.getAttribute('data-item-note') || '').trim();
      }

      showModal(editSastavnicaModalElement);
    }

    function applyUpdatedSastavnicaRow(row, triggerButton, item) {
      if (!row || !item) {
        return;
      }

      var cells = row.children || [];
      var description = String(item.opis || '').trim();
      var note = String(item.napomena || '').trim();
      var quantity = formatSastavnicaQuantity(item.kolicina);
      var unit = String(item.mj || '').trim().toUpperCase();

      if (cells.length > 3) {
        cells[3].textContent = displaySastavnicaCellValue(description);
      }

      if (cells.length > 5) {
        cells[5].textContent = truncateSastavnicaNote(note);
        cells[5].setAttribute('title', note);
      }

      if (cells.length > 6) {
        cells[6].textContent = quantity;
      }

      if (cells.length > 7) {
        cells[7].textContent = displaySastavnicaCellValue(unit);
      }

      if (triggerButton) {
        triggerButton.setAttribute('data-item-description', description);
        triggerButton.setAttribute('data-item-note', note);
        triggerButton.setAttribute('data-item-quantity', quantity === '-' ? '' : quantity);
        triggerButton.setAttribute('data-item-unit', unit);
      }
    }

    function saveEditedSastavnicaItem() {
      if (!activeSastavnicaEditContext) {
        return;
      }

      if (!mutationConfig.plannedConsumptionUpdateUrl) {
        setEditSastavnicaError('Endpoint za ažuriranje nije dostupan.');
        return;
      }

      var itemIdRaw = String(activeSastavnicaEditContext.itemId || '').trim();
      var itemNoRaw = String(activeSastavnicaEditContext.itemNo || '').trim();
      var parsedQuantity = Number(String(editSastavnicaQuantityInput && editSastavnicaQuantityInput.value ? editSastavnicaQuantityInput.value : '').trim().replace(',', '.'));

      if (!itemIdRaw && !itemNoRaw) {
        setEditSastavnicaError('Stavku nije moguće identifikovati za ažuriranje.');
        return;
      }

      if (!Number.isFinite(parsedQuantity) || parsedQuantity < 0) {
        setEditSastavnicaError('Količina mora biti broj veći ili jednak nuli.');
        return;
      }

      clearEditSastavnicaError();
      setActionButtonLoading(editSastavnicaSaveButton, true);

      requestMutation(mutationConfig.plannedConsumptionUpdateUrl, {
        item_id: itemIdRaw ? Number(itemIdRaw) : null,
        item_no: itemNoRaw ? Number(itemNoRaw) : null,
        opis: editSastavnicaDescriptionInput ? String(editSastavnicaDescriptionInput.value || '').trim() : '',
        napomena: editSastavnicaNoteInput ? String(editSastavnicaNoteInput.value || '').trim() : '',
        kolicina: parsedQuantity,
      }, 'Ažuriranje stavke nije uspjelo.')
        .then(function (response) {
          var item = response && response.data && response.data.item
            ? response.data.item
            : {
                opis: editSastavnicaDescriptionInput ? String(editSastavnicaDescriptionInput.value || '').trim() : '',
                napomena: editSastavnicaNoteInput ? String(editSastavnicaNoteInput.value || '').trim() : '',
                kolicina: parsedQuantity,
                mj: editSastavnicaUnitInput ? String(editSastavnicaUnitInput.value || '').trim().toUpperCase() : ''
              };

           applyUpdatedSastavnicaRow(activeSastavnicaEditContext.row, activeSastavnicaEditContext.button, item);
           hideModal(editSastavnicaModalElement);

           var stockAdjustments = response && response.data && response.data.stock_adjustments
             ? response.data.stock_adjustments
             : [];
           var stockAdjusted = Array.isArray(stockAdjustments) && stockAdjustments.length > 0;
           var stockInfoText = '';

           if (stockAdjusted) {
             var firstAdjustment = stockAdjustments[0] || {};
             var stockBefore = formatSastavnicaQuantity(firstAdjustment.current_stock_value);
             var stockAfter = formatSastavnicaQuantity(firstAdjustment.new_stock_value);

             if (stockBefore !== '-' && stockAfter !== '-') {
               stockInfoText = '\nZaliha: ' + stockBefore + ' -> ' + stockAfter;
             }
           }

           Swal.fire(swalWithTheme({
             icon: 'success',
             title: 'Stavka ažurirana',
             text: stockAdjusted
               ? ('Stavka sastavnice i skladište uspješno ažurirani.' + stockInfoText)
               : (response && response.message ? response.message : 'Stavka sastavnice je uspješno ažurirana.')
           }));
         })
        .catch(function (error) {
          setEditSastavnicaError(error && error.message ? error.message : 'Ažuriranje stavke nije uspjelo.');
        })
        .finally(function () {
          setActionButtonLoading(editSastavnicaSaveButton, false);
        });
    }

    function removeLinkedRowsFromTabs(positionValue, componentCodeValue) {
      var positionText = String(positionValue || '').trim();
      var componentCode = String(componentCodeValue || '').trim().toUpperCase();

      var removeInTable = function (tableElement, positionColumnIndex, codeColumnIndex) {
        if (!tableElement) {
          return;
        }

        var body = tableElement.querySelector('tbody');
        if (!body) {
          return;
        }

        var rows = Array.prototype.slice.call(body.querySelectorAll('tr'));
        rows.forEach(function (candidateRow) {
          var cells = candidateRow.children || [];
          if (!cells.length || candidateRow.querySelector('td[colspan]')) {
            return;
          }

          var candidatePosition = cells.length > positionColumnIndex
            ? String(cells[positionColumnIndex].textContent || '').trim()
            : '';
          var candidateCode = cells.length > codeColumnIndex
            ? String(cells[codeColumnIndex].textContent || '').trim().toUpperCase()
            : '';

          if (candidatePosition === positionText && candidateCode === componentCode) {
            candidateRow.remove();
          }
        });
      };

      removeInTable(materijaliTable, 0, 1);
      removeInTable(operacijaTable, 1, 2);

      ensureTabTableEmptyState(materijaliTable, 5, 'Nema stavki za ovaj radni nalog.');
      ensureTabTableEmptyState(operacijaTable, 11, 'Nema operacija za ovaj radni nalog.');
    }

    if (statusSaveButton && statusSelect) {
      statusSaveButton.addEventListener('click', function () {
        var selectedStatus = (statusSelect.value || '').toString().trim();

        if (!selectedStatus) {
          return;
        }

        if (!mutationConfig.statusUrl) {
          Swal.fire(swalWithTheme({
            icon: 'error',
            title: 'Nedostaje ruta',
            text: 'Status endpoint nije dostupan.'
          }));
          return;
        }

        hideModal(statusModalElement);

        Swal.fire(swalWithTheme({
          title: 'Potvrda promjene statusa',
          text: 'Novi status će biti: ' + selectedStatus,
          icon: 'question',
          showCancelButton: true,
          confirmButtonText: 'Sačuvaj',
          cancelButtonText: 'Otkaži',
          customClass: {
            confirmButton: 'btn btn-primary',
            cancelButton: 'btn btn-danger ms-1'
          },
          buttonsStyling: false
        })).then(function (result) {
          if (!result.isConfirmed) {
            return;
          }

          setActionButtonLoading(statusSaveButton, true);

          requestMutation(mutationConfig.statusUrl, { status: selectedStatus }, 'Ažuriranje statusa nije uspjelo.')
            .then(function (response) {
              var resolvedStatus = response && response.data && response.data.status ? response.data.status : selectedStatus;

              if (statusLabel) {
                statusLabel.textContent = resolvedStatus;
              }

              updateSideButtonTone(statusTriggerButton, resolveStatusToneClass(resolvedStatus));
              hideModal(statusModalElement);

              Swal.fire(swalWithTheme({
                icon: 'success',
                title: 'Status ažuriran',
                text: response && response.message ? response.message : 'Status je uspješno ažuriran.'
              }));
            })
            .catch(function (error) {
              Swal.fire(swalWithTheme({
                icon: 'error',
                title: 'Greška',
                text: error && error.message ? error.message : 'Ažuriranje statusa nije uspjelo.'
              }));
            })
            .finally(function () {
              setActionButtonLoading(statusSaveButton, false);
            });
        });
      });
    }

    if (prioritySaveButton && prioritySelect) {
      prioritySaveButton.addEventListener('click', function () {
        var selectedPriority = (prioritySelect.value || '').toString().trim();

        if (!selectedPriority) {
          return;
        }

        if (!mutationConfig.priorityUrl) {
          Swal.fire(swalWithTheme({
            icon: 'error',
            title: 'Nedostaje ruta',
            text: 'Prioritet endpoint nije dostupan.'
          }));
          return;
        }

        hideModal(priorityModalElement);

        Swal.fire(swalWithTheme({
          title: 'Potvrda promjene prioriteta',
          text: 'Novi prioritet će biti: ' + selectedPriority,
          icon: 'question',
          showCancelButton: true,
          confirmButtonText: 'Sačuvaj',
          cancelButtonText: 'Otkaži',
          customClass: {
            confirmButton: 'btn btn-primary',
            cancelButton: 'btn btn-danger ms-1'
          },
          buttonsStyling: false
        })).then(function (result) {
          if (!result.isConfirmed) {
            return;
          }

          setActionButtonLoading(prioritySaveButton, true);

          requestMutation(mutationConfig.priorityUrl, { priority: selectedPriority }, 'Ažuriranje prioriteta nije uspjelo.')
            .then(function (response) {
              var resolvedPriority = response && response.data && response.data.priority ? response.data.priority : selectedPriority;

              if (priorityLabel) {
                priorityLabel.textContent = resolvedPriority;
              }

              updateSideButtonTone(priorityTriggerButton, resolvePriorityToneClass(resolvedPriority));
              hideModal(priorityModalElement);

              Swal.fire(swalWithTheme({
                icon: 'success',
                title: 'Prioritet ažuriran',
                text: response && response.message ? response.message : 'Prioritet je uspješno ažuriran.'
              }));
            })
            .catch(function (error) {
              Swal.fire(swalWithTheme({
                icon: 'error',
                title: 'Greška',
                text: error && error.message ? error.message : 'Ažuriranje prioriteta nije uspjelo.'
              }));
            })
            .finally(function () {
              setActionButtonLoading(prioritySaveButton, false);
            });
        });
      });
    }

    if (deleteWorkOrderButton && window.Swal && typeof window.Swal.fire === 'function') {
      deleteWorkOrderButton.addEventListener('click', function () {
        var deleteUrl = String(deleteWorkOrderButton.getAttribute('data-delete-url') || '').trim();

        if (!deleteUrl) {
          return;
        }

        Swal.fire(swalWithTheme({
          title: 'Izbrisati nalog?',
          text: 'Ova akcija je trajna i obrisat će radni nalog iz sistema.',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Izbriši',
          cancelButtonText: 'Otkaži',
          customClass: {
            confirmButton: 'btn btn-danger',
            cancelButton: 'btn btn-outline-danger ms-1'
          },
          buttonsStyling: false,
          showLoaderOnConfirm: true,
          preConfirm: function () {
            return requestDelete(deleteUrl, 'Brisanje naloga nije uspjelo.')
              .catch(function (error) {
                Swal.showValidationMessage(
                  error && error.message ? error.message : 'Brisanje naloga nije uspjelo.'
                );
              });
          },
          allowOutsideClick: function () {
            return !Swal.isLoading();
          }
        })).then(function (result) {
          if (!result.isConfirmed) {
            return;
          }

          return Swal.fire(swalWithTheme({
            icon: 'success',
            title: 'Nalog je izbrisan',
            timer: 900,
            showConfirmButton: false
          })).then(function () {
            window.location.href = @json(url('/app/invoice/preview'));
          });
        }).catch(function (error) {
          Swal.fire(swalWithTheme({
            icon: 'error',
            title: 'Brisanje nije uspjelo',
            text: error && error.message ? error.message : 'Brisanje naloga nije uspjelo.'
          }));
        });
      });
    }

    function openCompactProtectionModal() {
      var modalElement = document.getElementById('work-order-protection-modal');
      if (!modalElement) return openProtectionDialog();
      var results = modalElement.querySelector('#wo-protection-modal-results'), search = modalElement.querySelector('#wo-protection-modal-search'), selectedLabel = modalElement.querySelector('#wo-protection-modal-selected');
      var picker = modalElement.querySelector('#wo-protection-picker'), createForm = modalElement.querySelector('#wo-protection-create-form');
      if (picker) picker.classList.remove('d-none'); if (createForm) createForm.classList.add('d-none'); modalElement.querySelector('.modal-title').textContent = 'Dodaj zaštitu';
      var selected = '', options = [], modal = bootstrap.Modal.getOrCreateInstance(modalElement);
      function render() { var q = (search.value || '').toLowerCase().trim(), shown = options.filter(function(o){return ((o.code||'')+' '+(o.label||'')+' '+(o.description||'')).toLowerCase().indexOf(q) !== -1;}).sort(function(a,b){return String(a.label||a.code||'').localeCompare(String(b.label||b.code||''));}); results.innerHTML = shown.map(function(o){var active=String(o.value||o.code)===selected;return '<button type="button" class="list-group-item wo-protection-choice '+(active?'is-selected':'')+'" data-value="'+escapeProtectionHtml(o.value||o.code)+'"><strong>'+escapeProtectionHtml(o.label||o.code)+'</strong><small>'+(o.description?escapeProtectionHtml(o.description)+' · ':'')+escapeProtectionHtml(o.weeks)+' sedm.</small></button>';}).join(''); modalElement.querySelector('#wo-protection-modal-empty').classList.toggle('d-none', shown.length>0); results.querySelectorAll('.wo-protection-choice').forEach(function(button){button.onclick=function(){selected=button.dataset.value;var item=options.find(function(o){return String(o.value||o.code)===selected;});selectedLabel.textContent=item?(item.label||item.code)+' · '+item.weeks+' sedm.':'Bez zaštite';render();};}); }
      loadProtectionOptions().then(function(data){ selected=String(data.selected||''); options=[{value:'',code:'',label:'Bez zaštite',weeks:2}].concat(data.options||[]); search.value=''; render(); modal.show(); });
      search.oninput=render;
      modalElement.querySelector('#wo-protection-modal-save').onclick=function(){var form=modalElement.querySelector('#wo-protection-create-form');if(form&&!form.classList.contains('d-none')){var payload={code:modalElement.querySelector('#wo-protection-new-code').value.trim(),name:modalElement.querySelector('#wo-protection-new-name').value.trim(),weeks:Number(modalElement.querySelector('#wo-protection-new-weeks').value),note:modalElement.querySelector('#wo-protection-new-note').value.trim()},err=modalElement.querySelector('#wo-protection-new-error');requestMutation(mutationConfig.protectionOptionStoreUrl,payload,'Dodavanje zaštite nije uspjelo.').then(function(){modal.hide();Swal.fire(swalWithTheme({icon:'success',title:'Zaštita je dodana',timer:1200,showConfirmButton:false})).then(openCompactProtectionModal);}).catch(function(e){err.textContent=e.message;err.classList.remove('d-none');Swal.fire(swalWithTheme({icon:'error',title:'Dodavanje nije uspjelo',text:e.message}));});return;}requestMutation(mutationConfig.protectionUpdateUrl,{protection_type:selected},'Ažuriranje zaštite nije uspjelo.').then(function(response){modal.hide();return Swal.fire(swalWithTheme({icon:'success',title:'Zaštita je sačuvana',text:response&&response.message?response.message:'Početak radnog naloga je ažuriran.',timer:1500,showConfirmButton:false}));}).then(function(){window.location.reload();}).catch(function(e){Swal.fire(swalWithTheme({icon:'error',title:'Spremanje nije uspjelo',text:e.message||'Pokušajte ponovo.'}));});};
      var add=modalElement.querySelector('#wo-protection-modal-add'); if(add) add.onclick=function(){modalElement.querySelector('#wo-protection-picker').classList.add('d-none');modalElement.querySelector('#wo-protection-create-form').classList.remove('d-none');modalElement.querySelector('.modal-title').textContent='Dodaj novu zaštitu';};
    }
    var protectionTab = document.getElementById('tab-zastita');
    function loadProtectionTab() { if (!protectionTab || !mutationConfig.protectionOptionsUrl) return; loadProtectionOptions().then(function(data){var select=protectionTab.querySelector('.wo-protection-tab-select'), code=protectionTab.querySelector('.wo-protection-code'), name=protectionTab.querySelector('.wo-protection-name'), weeks=protectionTab.querySelector('.wo-protection-weeks'), selected=String(data.selected||''), options=data.options||[];select.innerHTML='<option value="">Bez zaštite</option>'+options.map(function(o){var value=String(o.value||o.code), isSelected=String(o.code||'')===selected||value===selected;return '<option value="'+escapeProtectionHtml(value)+'" '+(isSelected?'selected':'')+'>'+escapeProtectionHtml(o.label||o.code)+'</option>';}).join('');var current=options.find(function(o){return String(o.code||'')===selected||String(o.value||o.code)===selected;});code.textContent=current?(current.label||current.code):'-';name.textContent=current?(current.description||'-'):'-';weeks.textContent=current?(current.weeks+' sedm.'):'-';select.onchange=function(){requestMutation(mutationConfig.protectionUpdateUrl,{protection_type:select.value},'Ažuriranje zaštite nije uspjelo.').then(function(r){return Swal.fire(swalWithTheme({icon:'success',title:'Zaštita je sačuvana',text:r.message||'',timer:1300,showConfirmButton:false}));}).then(function(){window.location.reload();}).catch(function(e){Swal.fire(swalWithTheme({icon:'error',title:'Spremanje nije uspjelo',text:e.message}));});};var remove=protectionTab.querySelector('.wo-protection-remove');remove.disabled=!current;remove.onclick=function(){requestMutation(mutationConfig.protectionUpdateUrl,{protection_type:''},'Uklanjanje zaštite nije uspjelo.').then(function(r){return Swal.fire(swalWithTheme({icon:'success',title:'Zaštita je uklonjena',text:r.message||'',timer:1300,showConfirmButton:false}));}).then(function(){window.location.reload();}).catch(function(e){Swal.fire(swalWithTheme({icon:'error',title:'Uklanjanje nije uspjelo',text:e.message}));});};}); }
    document.querySelector('[data-bs-target="#tab-zastita"]')?.addEventListener('shown.bs.tab', loadProtectionTab);
    if (protectionTriggerButton) protectionTriggerButton.addEventListener('click', openCompactProtectionModal);

    if (editSastavnicaModalElement) {
      editSastavnicaModalElement.addEventListener('hidden.bs.modal', function () {
        clearEditSastavnicaError();
        activeSastavnicaEditContext = null;
      });
    }

    if (editSastavnicaSaveButton) {
      editSastavnicaSaveButton.addEventListener('click', function () {
        saveEditedSastavnicaItem();
      });
    }

    if (sastavnicaTable) {
      sastavnicaTable.addEventListener('click', function (event) {
        var editButton = event.target.closest('.wo-edit-sastavnica-btn');
        if (editButton) {
          openEditSastavnicaModal(editButton);
          return;
          Swal.fire(swalWithTheme({
            icon: 'info',
            title: 'Uskoro',
            text: 'Uređivanje stavke biće dostupno uskoro.'
          }));
          return;
        }

        var removeButton = event.target.closest('.wo-remove-sastavnica-btn');

        if (!removeButton) {
          return;
        }

        if (!mutationConfig.plannedConsumptionRemoveUrl) {
          Swal.fire(swalWithTheme({
            icon: 'error',
            title: 'Nedostaje ruta',
            text: 'Endpoint za brisanje nije dostupan.'
          }));
          return;
        }

        var row = removeButton.closest('tr');
        if (!row) {
          return;
        }

        var itemIdRaw = (removeButton.getAttribute('data-item-id') || '').trim();
        var itemNoRaw = (removeButton.getAttribute('data-item-no') || '').trim();
        var itemId = itemIdRaw && itemIdRaw !== '-' ? Number(itemIdRaw) : null;
        var itemNo = itemNoRaw && itemNoRaw !== '-' ? Number(itemNoRaw) : null;

        if (itemId === null && itemNo === null) {
          Swal.fire(swalWithTheme({
            icon: 'warning',
            title: 'Nedostaje identifikator',
            text: 'Stavku nije moguće identifikovati za brisanje.'
          }));
          return;
        }

        var componentCode = '';
        var componentCell = row.children.length > 2 ? row.children[2] : null;
        if (componentCell) {
          componentCode = (componentCell.textContent || '').trim();
        }

        var positionText = '';
        var positionCell = row.children.length > 1 ? row.children[1] : null;
        if (positionCell) {
          positionText = (positionCell.textContent || '').trim();
        }

        Swal.fire(swalWithTheme({
          title: 'Ukloniti stavku?',
          text: componentCode ? ('Stavka: ' + componentCode) : 'Ova stavka će biti uklonjena iz radnog naloga.',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Ukloni',
          cancelButtonText: 'Otkaži',
          customClass: {
            confirmButton: 'btn btn-danger',
            cancelButton: 'btn btn-outline-danger ms-1'
          },
          buttonsStyling: false
        })).then(function (result) {
          if (!result.isConfirmed) {
            return;
          }

          removeButton.disabled = true;
          removeButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';

          requestMutation(mutationConfig.plannedConsumptionRemoveUrl, {
            item_id: itemId,
            item_no: itemNo
          }, 'Brisanje stavke nije uspjelo.')
            .then(function (response) {
              row.remove();
              ensureSastavnicaEmptyState();
              removeLinkedRowsFromTabs(positionText, componentCode);

              Swal.fire(swalWithTheme({
                icon: 'success',
                title: 'Stavka obrisana',
                text: response && response.message ? response.message : 'Stavka je uspješno obrisana.'
              }));
            })
            .catch(function (error) {
              Swal.fire(swalWithTheme({
                icon: 'error',
                title: 'Greška',
                text: error && error.message ? error.message : 'Brisanje stavke nije uspjelo.'
              }));
            })
            .finally(function () {
              if (!document.body.contains(removeButton)) {
                return;
              }

              removeButton.disabled = false;
              removeButton.innerHTML = '<i class="fa fa-trash"></i>';
            });
        });
      });
    }
  });
</script>
{{-- Include QR Scanner Modals --}}
@include('content.new-components.change-status-modal', ['currentStatus' => $statusDisplayLabel])
@include('content.new-components.change-priority-modal', ['currentPriority' => $priorityDisplayLabel])
@include('content.new-components.edit-sastavnica-item-modal')
@include('content.new-components.work-order-protection-modal')
@include('content.new-components.nalog-scan')
@include('content.new-components.sirovina-scan', [
  'productsFetchUrl' => $productsFetchUrl,
  'bomFetchUrl' => $bomFetchUrl,
  'bomDestroyUrl' => $bomDestroyUrl,
  'allMaterialsFetchUrl' => $allMaterialsFetchUrl,
  'allOperationsFetchUrl' => $allOperationsFetchUrl,
  'barcodeMaterialLookupUrl' => $barcodeMaterialLookupUrl,
  'plannedConsumptionStoreUrl' => $plannedConsumptionStoreUrl,
  'defaultProductIdent' => trim((string) ($workOrder['sifra'] ?? '')),
  'defaultProductLabel' => trim((string) ($workOrder['sifra'] ?? '')) . (
    trim((string) ($workOrder['naziv'] ?? '')) !== '' ? ' - ' . trim((string) ($workOrder['naziv'] ?? '')) : ''
  ),
  'defaultWorkOrderQuantity' => $workOrder['kolicina'] ?? null,
])
@include('content.new-components.fine-adjust-bom')
@include('content.new-components.confirm-weight')
@endsection
