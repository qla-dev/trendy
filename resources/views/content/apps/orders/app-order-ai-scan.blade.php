@extends('layouts/contentLayoutMaster')

@section('title', __('locale.Skeniraj narudzbu sa AI'))

@section('vendor-style')
  <link rel="stylesheet" href="{{ asset('vendors/css/extensions/sweetalert2.min.css') }}">
@endsection

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

    .order-ai-shell.is-initializing {
      position: relative;
      min-height: 420px;
    }

    .order-ai-shell.is-initializing > .row {
      opacity: 0;
      pointer-events: none;
    }

    .order-ai-initial-loader {
      position: absolute;
      inset: 0;
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 5;
      padding: 2rem;
    }

    .order-ai-shell.is-initializing .order-ai-initial-loader {
      display: flex;
    }

    .order-ai-initial-loader-card {
      display: grid;
      gap: 0.65rem;
      min-width: min(26rem, 100%);
      padding: 1.1rem 1.2rem;
      border-radius: 1rem;
      border: 1px solid rgba(22, 52, 77, 0.08);
      background: rgba(255, 255, 255, 0.94);
      box-shadow: 0 18px 32px rgba(16, 31, 48, 0.08);
      text-align: center;
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
      background: none;
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
      flex: 1 1 auto;
      width: 100%;
      height: 100%;
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

    .order-ai-upload-stage-row {
      align-items: stretch;
    }

    #order-ai-dropzone-shell,
    .order-ai-progress-shell {
      display: flex;
    }

    #order-ai-dropzone-shell > .card,
    .order-ai-progress-shell > .card {
      width: 100%;
    }

    #order-ai-dropzone-shell > .card,
    #order-ai-dropzone-shell > .card > .card-body,
    .order-ai-progress-shell > .card,
    .order-ai-progress-shell > .card > .card-body {
      height: 100%;
    }

    #order-ai-dropzone-shell > .card > .card-body {
      display: flex;
    }

    .order-ai-progress-shell {
      align-self: stretch;
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

    .order-ai-progress-shell {
      align-self: stretch;
    }

    .order-ai-progress-shell:not(.order-ai-progress-shell-wide) .order-ai-extract-live-grid {
      grid-auto-flow: column;
      grid-auto-columns: minmax(170px, 1fr);
      grid-template-columns: unset;
      grid-template-rows: repeat(2, minmax(0, 1fr));
      overflow-x: auto;
      padding-bottom: 0.15rem;
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
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      gap: 0.3rem;
    }

    .order-ai-progress-runtime {
      display: inline-flex;
      align-items: center;
      justify-content: flex-end;
      gap: 0.45rem;
      margin-top: 0.2rem;
      text-align: right;
      white-space: nowrap;
    }

    .order-ai-progress-runtime-label {
      color: var(--order-ai-subtle);
      font-size: 0.72rem;
      font-weight: 700;
    }

    .order-ai-progress-runtime-value {
      color: var(--order-ai-ink);
      font-size: 0.82rem;
      font-weight: 700;
      line-height: 1;
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
      width: 100%;
      border-radius: 999px;
      background: linear-gradient(90deg, #0e7a6b 0%, #1ca28f 100%);
      transform: scaleX(0);
      transform-origin: left center;
      will-change: transform;
      transition: transform 0.26s linear;
    }

    .order-ai-stage-list {
      display: grid;
      gap: 0.6rem;
    }

    .order-ai-stage {
      --order-ai-stage-accent: #0e7a6b;
      --order-ai-stage-accent-rgb: 14, 122, 107;
      position: relative;
      overflow: hidden;
      padding: 0.78rem 0.95rem;
      border-radius: 1rem;
      background: var(--order-ai-card-soft);
      border: 1px solid rgba(22, 52, 77, 0.08);
    }

    .order-ai-stage[data-stage="upload"],
    .order-ai-stage[data-stage="extract"],
    .order-ai-stage[data-stage="transfer"] {
      --order-ai-stage-accent: #0e7a6b;
      --order-ai-stage-accent-rgb: 14, 122, 107;
    }

    .order-ai-stage-fill {
      position: absolute;
      top: 0;
      left: 0;
      bottom: 0;
      width: 100%;
      background: linear-gradient(90deg, rgba(var(--order-ai-stage-accent-rgb), 0.24), rgba(var(--order-ai-stage-accent-rgb), 0.08));
      transform: scaleX(0);
      transform-origin: left center;
      will-change: transform;
      transition: transform 0.32s linear;
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
      display: flex;
      justify-content: flex-end;
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
      background: var(--order-ai-stage-accent);
      box-shadow: 0 0 0 0.25rem rgba(var(--order-ai-stage-accent-rgb), 0.14);
    }

    .order-ai-stage.is-active {
      background: rgba(var(--order-ai-stage-accent-rgb), 0.1);
      border-color: rgba(var(--order-ai-stage-accent-rgb), 0.24);
    }

    .order-ai-stage.is-done {
      background: rgba(var(--order-ai-stage-accent-rgb), 0.08);
      border-color: rgba(var(--order-ai-stage-accent-rgb), 0.2);
    }

    .order-ai-stage.is-active[data-stage="extract"] .order-ai-stage-fill {
      background: linear-gradient(90deg, rgba(var(--order-ai-stage-accent-rgb), 0.28), rgba(var(--order-ai-stage-accent-rgb), 0.18), rgba(var(--order-ai-stage-accent-rgb), 0.08));
      background-size: 200% 100%;
      animation: order-ai-flow 2s linear infinite;
    }

    .order-ai-stage.is-active[data-stage="upload"] .order-ai-stage-fill,
    .order-ai-stage.is-active[data-stage="transfer"] .order-ai-stage-fill {
      background: linear-gradient(90deg, rgba(var(--order-ai-stage-accent-rgb), 0.3), rgba(var(--order-ai-stage-accent-rgb), 0.14));
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

    .order-ai-extract-live {
      --order-ai-phase-accent: #e0585d;
      --order-ai-phase-accent-rgb: 224, 88, 93;
      display: grid;
      gap: 0.7rem;
      padding: 0;
    }

    #order-ai-extract-live-shell .order-ai-extract-live {
      background: transparent !important;
      border-color: transparent !important;
      box-shadow: none !important;
    }

    .order-ai-extract-live[data-phase-index="1"] {
      --order-ai-phase-accent: #ea8f1f;
      --order-ai-phase-accent-rgb: 234, 143, 31;
    }

    .order-ai-extract-live[data-phase-index="2"] {
      --order-ai-phase-accent: #d6bb25;
      --order-ai-phase-accent-rgb: 214, 187, 37;
    }

    .order-ai-extract-live[data-phase-index="3"] {
      --order-ai-phase-accent: #347cf7;
      --order-ai-phase-accent-rgb: 52, 124, 247;
    }

    .order-ai-extract-live[data-phase-index="4"] {
      --order-ai-phase-accent: #18a957;
      --order-ai-phase-accent-rgb: 24, 169, 87;
    }

    .order-ai-extract-live-header {
      display: flex;
      align-items: baseline;
      justify-content: space-between;
      gap: 0.65rem;
      flex-wrap: wrap;
    }

    .order-ai-extract-live-title {
      color: var(--order-ai-ink);
      font-size: 0.86rem;
      font-weight: 700;
    }

    .order-ai-extract-live-copy {
      display: grid;
      gap: 0.18rem;
    }

    .order-ai-extract-live-description {
      color: var(--order-ai-subtle);
      font-size: 0.78rem;
    }

    .order-ai-extract-live-meta {
      color: var(--order-ai-subtle);
      font-size: 0.78rem;
      font-weight: 600;
    }

    .order-ai-extract-global-progress {
      height: 0.55rem;
      border-radius: 999px;
      background: rgba(18, 52, 77, 0.08);
      overflow: hidden;
    }

    .order-ai-extract-global-progress-bar {
      display: block;
      width: 0;
      height: 100%;
      border-radius: inherit;
      background: linear-gradient(90deg, rgba(var(--order-ai-phase-accent-rgb), 0.98), rgba(var(--order-ai-phase-accent-rgb), 0.72));
      transition: width 0.22s linear;
    }

    .order-ai-extract-focus {
      display: grid;
      gap: 0.1rem;
    }

    .order-ai-extract-focus-label {
      color: var(--order-ai-subtle);
      font-size: 0.71rem;
      font-weight: 700;
      letter-spacing: 0.04em;
      text-transform: uppercase;
    }

    .order-ai-extract-focus-value {
      color: var(--order-ai-ink);
      font-size: 0.96rem;
      font-weight: 700;
      line-height: 1.2;
    }

    .order-ai-extract-live-grid {
      display: flex;
      flex-wrap: wrap;
      gap: 0.45rem;
    }

    .order-ai-extract-page-chip {
      --order-ai-phase-accent: #e0585d;
      --order-ai-phase-accent-rgb: 224, 88, 93;
      min-width: 2.5rem;
      height: 2.1rem;
      padding: 0 0.72rem;
      border-radius: 999px;
      border: 1px solid rgba(18, 52, 77, 0.1);
      background: rgba(255, 255, 255, 0.88);
      color: var(--order-ai-subtle);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.35rem;
      font-size: 0.8rem;
      font-weight: 700;
      line-height: 1;
      white-space: nowrap;
      transition: border-color 0.18s ease, background-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
    }

    .order-ai-extract-page-chip.is-tone-1 {
      --order-ai-phase-accent: #ea8f1f;
      --order-ai-phase-accent-rgb: 234, 143, 31;
    }

    .order-ai-extract-page-chip.is-tone-2 {
      --order-ai-phase-accent: #d6bb25;
      --order-ai-phase-accent-rgb: 214, 187, 37;
    }

    .order-ai-extract-page-chip.is-tone-3 {
      --order-ai-phase-accent: #347cf7;
      --order-ai-phase-accent-rgb: 52, 124, 247;
    }

    .order-ai-extract-page-chip.is-tone-4 {
      --order-ai-phase-accent: #18a957;
      --order-ai-phase-accent-rgb: 24, 169, 87;
    }

    .order-ai-extract-page-chip.is-pending {
      border-color: rgba(var(--order-ai-phase-accent-rgb), 0.18);
      background: rgba(var(--order-ai-phase-accent-rgb), 0.08);
      color: var(--order-ai-phase-accent);
    }

    .order-ai-extract-page-chip.is-done {
      border-color: rgba(var(--order-ai-phase-accent-rgb), 0.24);
      background: rgba(var(--order-ai-phase-accent-rgb), 0.12);
      color: var(--order-ai-phase-accent);
    }

    .order-ai-extract-page-chip.is-active {
      border-color: rgba(var(--order-ai-phase-accent-rgb), 0.28);
      background: linear-gradient(135deg, rgba(var(--order-ai-phase-accent-rgb), 0.18), rgba(255, 255, 255, 0.96));
      color: var(--order-ai-phase-accent);
      box-shadow: 0 10px 18px rgba(var(--order-ai-phase-accent-rgb), 0.16);
      animation: order-ai-page-pulse 1.25s ease-in-out infinite;
    }

    .order-ai-extract-page-chip.is-error {
      border-color: rgba(220, 38, 38, 0.22);
      background: rgba(220, 38, 38, 0.08);
      color: #8b1e1e;
    }

    .order-ai-extract-page-chip-state {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 1rem;
      height: 1rem;
      border-radius: 999px;
      background: rgba(18, 52, 77, 0.08);
      font-size: 0.74rem;
      line-height: 1;
      flex: 0 0 auto;
    }

    .order-ai-extract-page-chip.is-pending .order-ai-extract-page-chip-state {
      background: rgba(var(--order-ai-phase-accent-rgb), 0.1);
    }

    .order-ai-extract-page-chip.is-done .order-ai-extract-page-chip-state {
      background: rgba(var(--order-ai-phase-accent-rgb), 0.16);
    }

    .order-ai-extract-page-chip.is-active .order-ai-extract-page-chip-state {
      background: rgba(var(--order-ai-phase-accent-rgb), 0.12);
    }

    .order-ai-extract-page-chip.is-error .order-ai-extract-page-chip-state {
      background: rgba(220, 38, 38, 0.12);
    }

    @keyframes order-ai-page-pulse {
      0%,
      100% {
        transform: translateY(0);
        box-shadow: 0 10px 18px rgba(16, 31, 48, 0.08);
      }
      50% {
        transform: translateY(-1px);
        box-shadow: 0 14px 24px rgba(14, 122, 107, 0.16);
      }
    }

    .order-ai-extract-step {
      display: grid;
      gap: 0.45rem;
      padding: 0.78rem 0.72rem;
      min-height: 7.2rem;
      border-radius: 0.9rem;
      border: 1px solid rgba(22, 52, 77, 0.1);
      background: rgba(255, 255, 255, 0.84);
      transition: transform 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease;
    }

    .order-ai-extract-step-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.5rem;
    }

    .order-ai-extract-step-progress {
      height: 0.34rem;
      border-radius: 999px;
      background: rgba(18, 52, 77, 0.08);
      overflow: hidden;
    }

    .order-ai-extract-step-progress-bar {
      display: block;
      height: 100%;
      border-radius: inherit;
      background: linear-gradient(90deg, rgba(14, 122, 107, 0.92), rgba(24, 203, 183, 0.9));
      transition: width 0.22s linear;
    }

    .order-ai-extract-phase-list {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
    }

    .order-ai-extract-phase {
      --order-ai-phase-rgb: 224, 88, 93;
      --order-ai-phase-color: #e0585d;
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      padding: 0.42rem 0.68rem;
      border-radius: 999px;
      border: 1px solid rgba(var(--order-ai-phase-rgb), 0.18);
      background: rgba(var(--order-ai-phase-rgb), 0.08);
      color: var(--order-ai-phase-color);
      font-size: 0.72rem;
      font-weight: 700;
      line-height: 1.2;
      white-space: nowrap;
      transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
    }

    .order-ai-extract-phase.is-tone-1 {
      --order-ai-phase-rgb: 234, 143, 31;
      --order-ai-phase-color: #ea8f1f;
    }

    .order-ai-extract-phase.is-tone-2 {
      --order-ai-phase-rgb: 214, 187, 37;
      --order-ai-phase-color: #c89f17;
    }

    .order-ai-extract-phase.is-tone-3 {
      --order-ai-phase-rgb: 52, 124, 247;
      --order-ai-phase-color: #347cf7;
    }

    .order-ai-extract-phase.is-tone-4 {
      --order-ai-phase-rgb: 24, 169, 87;
      --order-ai-phase-color: #18a957;
    }

    .order-ai-extract-phase-name {
      font-weight: 700;
    }

    .order-ai-extract-phase-state {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 1.15rem;
      height: 1.15rem;
      padding: 0 0.22rem;
      border-radius: 999px;
      background: rgba(var(--order-ai-phase-rgb), 0.14);
      font-size: 0.7rem;
      white-space: nowrap;
    }

    .order-ai-extract-phase.is-done {
      border-color: rgba(var(--order-ai-phase-rgb), 0.28);
      background: rgba(var(--order-ai-phase-rgb), 0.14);
      box-shadow: 0 8px 16px rgba(var(--order-ai-phase-rgb), 0.12);
    }

    .order-ai-extract-phase.is-active {
      border-color: rgba(var(--order-ai-phase-rgb), 0.32);
      background: linear-gradient(135deg, rgba(var(--order-ai-phase-rgb), 0.18), rgba(255, 255, 255, 0.96));
      box-shadow: 0 10px 18px rgba(var(--order-ai-phase-rgb), 0.14);
      transform: translateY(-1px);
    }

    .order-ai-extract-phase.is-pending {
      opacity: 0.78;
    }

    .order-ai-extract-phase.is-error {
      --order-ai-phase-rgb: 220, 38, 38;
      --order-ai-phase-color: #b42318;
      border-color: rgba(220, 38, 38, 0.28);
      background: rgba(220, 38, 38, 0.08);
    }

    .order-ai-extract-step.is-done {
      border-color: rgba(18, 129, 74, 0.24);
      background: rgba(221, 246, 231, 0.9);
      color: #17683b;
    }

    .order-ai-extract-step.is-active {
      border-color: rgba(14, 122, 107, 0.24);
      background: linear-gradient(135deg, rgba(226, 255, 247, 0.96), rgba(233, 244, 255, 0.92));
      box-shadow: 0 12px 24px rgba(16, 31, 48, 0.08);
      transform: translateY(-1px);
    }

    .order-ai-extract-step.is-pending {
      color: var(--order-ai-subtle);
    }

    .order-ai-extract-step-index {
      font-size: 0.98rem;
      font-weight: 700;
      line-height: 1;
    }

    .order-ai-extract-step-label {
      font-size: 0.72rem;
      font-weight: 700;
      letter-spacing: 0.04em;
      text-transform: uppercase;
    }

    .order-ai-extract-step-status {
      font-size: 0.74rem;
      opacity: 0.88;
    }

    .order-ai-primary-action {
      min-width: 170px;
      min-height: 3.15rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0.8rem 1.2rem;
      border: 1px solid rgba(14, 122, 107, 0.16);
      border-radius: 0.9rem;
      background: rgba(14, 122, 107, 0.08);
      color: #166458;
      font-weight: 700;
      line-height: 1.15;
      white-space: nowrap;
      box-shadow: 0 12px 24px rgba(14, 122, 107, 0.08);
    }

    .order-ai-primary-action:hover,
    .order-ai-primary-action:focus {
      color: #12584d;
      background: rgba(14, 122, 107, 0.12);
      transform: translateY(-1px);
    }

    .order-ai-primary-action:disabled,
    .order-ai-primary-action:disabled:hover,
    .order-ai-primary-action:disabled:focus {
      background: rgba(133, 148, 163, 0.16);
      border-color: rgba(133, 148, 163, 0.18);
      color: #8da0b0;
      box-shadow: none;
      transform: none;
      cursor: not-allowed !important;
    }

    .order-ai-transfer-cta {
      min-width: 170px;
      min-height: 3.15rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0.8rem 1.35rem;
      border-radius: 0.9rem;
      font-weight: 700;
      line-height: 1.15;
      white-space: nowrap;
      background: linear-gradient(180deg, #4aa075 0%, #397f62 100%);
      border-color: #397f62;
      box-shadow: 0 14px 28px rgba(57, 127, 98, 0.18);
    }

    .order-ai-transfer-cta:hover,
    .order-ai-transfer-cta:focus {
      background: linear-gradient(180deg, #57aa7f 0%, #3e8768 100%);
      border-color: #3e8768;
    }

    .order-ai-transfer-cta.is-ready {
      animation: none;
    }

    .order-ai-transfer-cta.is-busy,
    .order-ai-transfer-cta.is-complete {
      opacity: 1;
      color: #ffffff;
    }

    .order-ai-transfer-cta.is-busy {
      background: linear-gradient(180deg, #4da17a 0%, #397f62 100%);
      border-color: #397f62;
    }

    .order-ai-transfer-cta.is-complete {
      background: linear-gradient(180deg, #0e7a6b 0%, #0b6156 100%);
      border-color: #0b6156;
    }

    .order-ai-transfer-cta:disabled:not(.is-busy):not(.is-complete) {
      background: linear-gradient(180deg, #dce3ea 0%, #cfd7df 100%);
      border-color: #cfd7df;
      color: #7f8d9b;
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

    .order-ai-bottom-actions {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      margin-bottom: -0.5rem;
      padding-top: 0.7rem;
      padding-bottom: 0.7rem;
      border-top: 1px solid rgba(22, 52, 77, 0.08);
    }

    .order-ai-bottom-actions-secondary {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      flex-wrap: wrap;
    }

    .order-ai-bottom-action-primary {
      margin-left: auto;
      flex: 0 0 auto;
    }

    .order-ai-secondary-action {
      min-height: 3.15rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0.8rem 1.35rem;
      border-radius: 0.9rem;
      border: 1px solid rgba(74, 160, 117, 0.24);
      background: transparent;
      color: var(--order-ai-ink);
      font-weight: 600;
      line-height: 1.15;
      white-space: nowrap;
    }

    .order-ai-secondary-action:hover,
    .order-ai-secondary-action:focus {
      color: var(--order-ai-ink);
      background: rgba(74, 160, 117, 0.08);
      border-color: rgba(74, 160, 117, 0.34);
    }

    #order-ai-lines-shell {
      margin-bottom: 0.5rem !important;
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
      grid-template-columns: repeat(8, minmax(0, 1fr));
      gap: 0.85rem;
    }

    .order-ai-fact {
      padding: 1rem;
      border-radius: 1rem;
      background: linear-gradient(180deg, var(--order-ai-card-surface) 0%, var(--order-ai-card-muted) 100%);
      border: 1px solid rgba(22, 52, 77, 0.08);
    }

    .order-ai-fact.is-match {
      border-color: rgba(22, 163, 74, 0.24);
      background: rgba(22, 163, 74, 0.08);
      color: #17683b;
    }

    .order-ai-fact.is-mismatch {
      border-color: rgba(220, 38, 38, 0.22);
      background: rgba(220, 38, 38, 0.08);
      color: #8b1e1e;
    }

    .order-ai-fact-meta {
      margin-top: 0.45rem;
      font-size: 0.76rem;
      font-weight: 600;
      opacity: 0.88;
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

    .order-ai-line-row.is-catalog-missing > td,
    .order-ai-line-row.is-catalog-created > td {
      transition: background-color 0.2s ease;
    }

    .order-ai-line-code-stack {
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      gap: 0.35rem;
    }

    .order-ai-line-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      padding: 0.2rem 0.55rem;
      border: 1px solid transparent;
      border-radius: 999px;
      font-size: 0.72rem;
      font-weight: 700;
      line-height: 1.15;
      letter-spacing: 0.02em;
      white-space: nowrap;
    }

    .order-ai-line-name {
      font-weight: 600;
    }

    .order-ai-line-note {
      margin-top: 0.35rem;
      font-size: 0.78rem;
      font-weight: 600;
      line-height: 1.45;
      opacity: 0.92;
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
      flex-wrap: nowrap;
      align-items: center;
      gap: 0.75rem;
    }

    .order-ai-total-check-edit input {
      min-width: 9rem;
      flex: 1 1 auto;
    }

    .order-ai-total-check-edit .btn,
    #order-ai-line-total-save-button {
      min-height: 2.875rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      line-height: 1;
      white-space: nowrap;
      text-align: center;
    }

    #order-ai-line-total-save-button {
      min-width: 5.8rem;
    }

    .order-ai-hero-story.has-hero-visual .order-ai-hero-story-inner {
      padding-right: 11.75rem;
    }

    .order-ai-hero-visual {
      position: absolute;
      right: 0.85rem;
      bottom: 0.25rem;
      z-index: 1;
      width: 10.75rem;
      height: 10.75rem;
      display: flex;
      align-items: flex-end;
      justify-content: center;
      pointer-events: none;
    }

    .order-ai-hero-lottie {
      display: block;
      width: 100%;
      height: 100%;
      position: relative;
      z-index: 1;
      opacity: 1;
      filter: drop-shadow(0 18px 26px rgba(16, 31, 48, 0.14));
    }

    .order-ai-alert {
      border-radius: 1rem;
      border: 0;
    }

    .order-ai-alert.is-preview-ready {
      color: #c45b12;
      background: rgba(249, 115, 22, 0.12);
      box-shadow: inset 0 0 0 1px rgba(249, 115, 22, 0.18);
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

    #order-ai-result-card,
    #order-ai-actions {
      scroll-margin-top: 6rem;
    }

    .order-ai-hidden {
      display: none !important;
    }

    @media (min-width: 992px) {
      #order-ai-dropzone-shell.order-ai-dropzone-shell-wide,
      .order-ai-progress-shell.order-ai-progress-shell-wide {
        flex: 0 0 100%;
        max-width: 100%;
      }
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

    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-initial-loader-card {
      background: rgba(255, 255, 255, 0.96);
      border-color: rgba(18, 52, 77, 0.08);
      color: var(--order-ai-ink);
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
      opacity: var(--order-ai-hero-illustration-opacity, 0.16);
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
      background: linear-gradient(180deg, #e2e8ef 0%, #d0d8e0 100%);
      border-color: #d0d8e0;
      color: #7f8d9b;
    }

    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-transfer-error-copy code,
    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-extract-live,
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

    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-extract-step {
      background: rgba(255, 255, 255, 0.92);
      border-color: rgba(18, 52, 77, 0.08);
    }

    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-saved-preview {
      background: linear-gradient(135deg, rgba(226, 255, 239, 0.75), rgba(255, 255, 255, 0.95));
      border-color: rgba(22, 163, 74, 0.16);
    }

    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-fact.is-match,
    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-line-total-trigger.is-match,
    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-total-check-banner.is-match {
      border-color: rgba(22, 163, 74, 0.28);
      background: rgba(22, 163, 74, 0.08);
      color: #17683b;
    }

    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-fact.is-mismatch,
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

    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-line-row.is-catalog-missing > td {
      background: rgba(255, 159, 67, 0.1);
    }

    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-line-row.is-catalog-created > td {
      background: rgba(22, 163, 74, 0.08);
    }

    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-line-badge.is-missing {
      background: rgba(255, 159, 67, 0.14);
      border-color: rgba(245, 158, 11, 0.28);
      color: #b45309;
    }

    html.light-layout:not(.dark-layout):not(.semi-dark-layout):not(.bordered-layout) .order-ai-line-badge.is-created {
      background: rgba(22, 163, 74, 0.12);
      border-color: rgba(22, 163, 74, 0.26);
      color: #17683b;
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

    html.dark-layout .order-ai-initial-loader-card,
    html.semi-dark-layout .order-ai-initial-loader-card {
      background: rgba(27, 37, 61, 0.96);
      border-color: rgba(126, 153, 194, 0.16);
      color: var(--order-ai-ink);
      box-shadow: 0 18px 36px rgba(2, 6, 23, 0.26);
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
      opacity: var(--order-ai-hero-illustration-opacity, 0.14);
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
      background: linear-gradient(135deg, rgba(var(--order-ai-stage-accent-rgb), 0.26), rgba(27, 37, 61, 0.96));
      border-color: rgba(var(--order-ai-stage-accent-rgb), 0.28);
    }

    html.dark-layout .order-ai-stage.is-done,
    html.semi-dark-layout .order-ai-stage.is-done {
      background: linear-gradient(135deg, rgba(var(--order-ai-stage-accent-rgb), 0.18), rgba(27, 37, 61, 0.96));
      border-color: rgba(var(--order-ai-stage-accent-rgb), 0.24);
    }

    html.dark-layout .order-ai-stage-bullet,
    html.semi-dark-layout .order-ai-stage-bullet {
      background: #62708a;
    }

    html.dark-layout .order-ai-transfer-cta:disabled:not(.is-busy):not(.is-complete),
    html.semi-dark-layout .order-ai-transfer-cta:disabled:not(.is-busy):not(.is-complete) {
      background: linear-gradient(180deg, #8390a4 0%, #728094 100%);
      border-color: #728094;
      color: #eef4ff;
    }

    html.dark-layout .order-ai-transfer-error-copy code,
    html.dark-layout .order-ai-extract-live,
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

    html.dark-layout .order-ai-extract-step,
    html.semi-dark-layout .order-ai-extract-step {
      background: rgba(30, 41, 64, 0.94);
      border-color: rgba(126, 153, 194, 0.16);
    }

    html.dark-layout .order-ai-secondary-action,
    html.semi-dark-layout .order-ai-secondary-action {
      border-color: rgba(108, 176, 140, 0.28);
      color: var(--order-ai-ink);
    }

    html.dark-layout .order-ai-secondary-action:hover,
    html.dark-layout .order-ai-secondary-action:focus,
    html.semi-dark-layout .order-ai-secondary-action:hover,
    html.semi-dark-layout .order-ai-secondary-action:focus {
      background: rgba(108, 176, 140, 0.12);
      border-color: rgba(108, 176, 140, 0.38);
      color: var(--order-ai-ink);
    }

    html.dark-layout .order-ai-primary-action,
    html.semi-dark-layout .order-ai-primary-action {
      background: rgba(24, 203, 183, 0.1);
      border-color: rgba(24, 203, 183, 0.18);
      color: #9ef1e0;
    }

    html.dark-layout .order-ai-primary-action:hover,
    html.dark-layout .order-ai-primary-action:focus,
    html.semi-dark-layout .order-ai-primary-action:hover,
    html.semi-dark-layout .order-ai-primary-action:focus {
      background: rgba(24, 203, 183, 0.16);
      color: #c7fff4;
    }

    html.dark-layout .order-ai-primary-action:disabled,
    html.dark-layout .order-ai-primary-action:disabled:hover,
    html.dark-layout .order-ai-primary-action:disabled:focus,
    html.semi-dark-layout .order-ai-primary-action:disabled,
    html.semi-dark-layout .order-ai-primary-action:disabled:hover,
    html.semi-dark-layout .order-ai-primary-action:disabled:focus {
      background: rgba(126, 153, 194, 0.12);
      border-color: rgba(126, 153, 194, 0.16);
      color: #8ea0bd;
    }

    html.dark-layout .order-ai-alert.is-preview-ready,
    html.semi-dark-layout .order-ai-alert.is-preview-ready {
      color: #ffd2a9;
      background: rgba(249, 115, 22, 0.18);
      box-shadow: inset 0 0 0 1px rgba(251, 146, 60, 0.22);
    }

    html.dark-layout .order-ai-fact.is-match,
    html.dark-layout .order-ai-line-total-trigger.is-match,
    html.dark-layout .order-ai-total-check-banner.is-match,
    html.semi-dark-layout .order-ai-fact.is-match,
    html.semi-dark-layout .order-ai-line-total-trigger.is-match,
    html.semi-dark-layout .order-ai-total-check-banner.is-match {
      border-color: rgba(49, 196, 109, 0.26);
      background: rgba(22, 163, 74, 0.16);
      color: #9de8b8;
    }

    html.dark-layout .order-ai-fact.is-mismatch,
    html.dark-layout .order-ai-line-total-trigger.is-mismatch,
    html.dark-layout .order-ai-total-check-banner.is-mismatch,
    html.semi-dark-layout .order-ai-fact.is-mismatch,
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

    html.dark-layout .order-ai-line-row.is-catalog-missing > td,
    html.semi-dark-layout .order-ai-line-row.is-catalog-missing > td {
      background: rgba(251, 146, 60, 0.14);
    }

    html.dark-layout .order-ai-line-row.is-catalog-created > td,
    html.semi-dark-layout .order-ai-line-row.is-catalog-created > td {
      background: rgba(34, 197, 94, 0.12);
    }

    html.dark-layout .order-ai-line-badge.is-missing,
    html.semi-dark-layout .order-ai-line-badge.is-missing {
      background: rgba(251, 146, 60, 0.18);
      border-color: rgba(251, 146, 60, 0.3);
      color: #ffd59c;
    }

    html.dark-layout .order-ai-line-badge.is-created,
    html.semi-dark-layout .order-ai-line-badge.is-created {
      background: rgba(34, 197, 94, 0.18);
      border-color: rgba(34, 197, 94, 0.28);
      color: #b8f7c9;
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
        background-size: var(--order-ai-hero-illustration-size, 19rem auto);
        background-position: var(--order-ai-hero-illustration-position, right 0.4rem top 1rem);
        opacity: var(--order-ai-hero-illustration-opacity, 0.13);
      }

      .order-ai-hero-story.has-hero-visual .order-ai-hero-story-inner {
        padding-right: 9.5rem;
      }

      .order-ai-hero-visual {
        width: 8.5rem;
        height: 8.5rem;
        right: 0.65rem;
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
        align-items: flex-start;
      }

      .order-ai-progress-runtime {
        justify-content: flex-start;
        text-align: left;
      }

      .order-ai-stage-side,
      .order-ai-transfer-cta,
      .order-ai-primary-action {
        width: 100%;
      }

      .order-ai-total-check-edit {
        flex-wrap: wrap;
      }

      .order-ai-bottom-actions {
        align-items: stretch;
        flex-direction: column;
        margin-bottom: 0;
      }

      .order-ai-bottom-actions-secondary,
      .order-ai-bottom-action-primary {
        width: 100%;
      }

      .order-ai-bottom-actions-secondary {
        display: grid;
        grid-template-columns: 1fr;
      }

      .order-ai-bottom-actions-secondary .btn,
      .order-ai-bottom-actions-secondary a,
      .order-ai-bottom-action-primary .btn {
        width: 100%;
      }

      .order-ai-hero-story.has-hero-visual .order-ai-hero-story-inner {
        padding-right: 0;
      }

      .order-ai-hero-visual {
        position: relative;
        right: auto;
        bottom: auto;
        margin: 0.9rem auto 0;
        width: 8rem;
        height: 8rem;
      }

      .order-ai-facts {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }

      .order-ai-extract-live-grid {
        grid-auto-flow: column;
        grid-auto-columns: minmax(170px, 1fr);
        grid-template-columns: unset;
        grid-template-rows: repeat(2, minmax(0, 1fr));
        overflow-x: auto;
        padding-bottom: 0.15rem;
      }

      .order-linkage-modal-summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }

    @media (min-width: 768px) and (max-width: 991.98px) {
      .order-ai-hero-grid {
        grid-template-columns: minmax(0, 1fr) minmax(188px, 12rem);
        gap: 0.8rem;
      }

      .order-ai-hero-copy .order-ai-chip {
        display: none;
      }

      .order-ai-hero-story {
        min-height: 206px;
        padding: 1rem 1.05rem;
      }

      .order-ai-hero-copy {
        max-width: none;
      }

      .order-ai-hero-copy .order-ai-title {
        margin-bottom: 0.6rem !important;
        line-height: 1.12;
      }

      .order-ai-hero-story.has-hero-visual .order-ai-hero-story-inner {
        padding-right: 8.85rem;
      }

      .order-ai-hero-visual {
        right: 0.2rem;
        width: 8.3rem;
        height: 8.3rem;
      }

      .order-ai-hero-aside {
        gap: 0.6rem;
        max-width: 12rem;
        justify-self: end;
      }

      .order-ai-stat {
        padding: 0.7rem 0.8rem 0.72rem 0.9rem;
        min-height: 4.4rem;
        border-radius: 0.92rem;
      }

      .order-ai-stat::after {
        width: 0.28rem;
      }

      .order-ai-stat-label {
        margin-bottom: 0.16rem;
        font-size: 0.62rem;
        letter-spacing: 0.06em;
      }

      .order-ai-stat-value {
        font-size: 0.84rem;
        line-height: 1.22;
      }

      .order-ai-extract-live {
        gap: 0.85rem;
      }

      .order-ai-extract-focus {
        gap: 0.18rem;
      }

      .order-ai-extract-live-row {
        gap: 0.42rem;
      }

      .order-ai-extract-page-chip {
        height: 2.2rem;
        padding: 0 0.4rem;
        border-radius: 0.88rem;
      }

      .order-ai-extract-phase-list {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.55rem;
        align-items: stretch;
      }

      .order-ai-extract-phase {
        width: 100%;
        justify-content: space-between;
        gap: 0.7rem;
        padding: 0.58rem 0.72rem;
        border-radius: 0.92rem;
        box-shadow: 0 8px 18px rgba(16, 31, 48, 0.06);
      }

      .order-ai-extract-phase:last-child:nth-child(odd) {
        grid-column: 1 / -1;
        justify-self: center;
        max-width: 18rem;
      }

      .order-ai-extract-phase-name {
        min-width: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
      }

      .order-ai-extract-phase-state {
        min-width: 3rem;
        height: 1.35rem;
        padding: 0 0.42rem;
        font-size: 0.68rem;
        flex: 0 0 auto;
      }

      .order-ai-facts {
        grid-template-columns: repeat(4, minmax(0, 1fr));
      }

      .order-ai-bottom-actions {
        flex-direction: row;
        align-items: center;
        flex-wrap: nowrap;
      }

      .order-ai-bottom-actions-secondary {
        width: auto;
        flex: 1 1 auto;
        min-width: 0;
        gap: 0.55rem;
      }

      .order-ai-bottom-action-primary {
        width: auto;
        margin-left: auto;
        flex: 0 0 auto;
      }

      .order-ai-secondary-action {
        min-height: 2.9rem;
        padding: 0.72rem 1rem;
        font-size: 0.9rem;
      }

      .order-ai-transfer-cta {
        min-width: 11.25rem;
        min-height: 2.9rem;
        padding: 0.72rem 1.15rem;
      }

      .order-ai-bottom-action-primary .btn {
        width: auto;
      }
    }

    @media (min-width: 768px) {
      .order-ai-bottom-actions {
        margin-bottom: -1rem;
      }
    }

    @media (max-width: 575.98px) {
      .order-ai-extract-live-grid {
        grid-template-rows: 1fr;
        grid-auto-columns: minmax(190px, 78vw);
      }

      .order-linkage-modal-summary-grid {
        grid-template-columns: 1fr;
      }
    }
</style>
<style>
    .order-ai-extract-live-header {
        justify-content: flex-start;
        align-items: center;
        margin-bottom: 0.65rem;
        width: 100%;
    }

    .order-ai-extract-live-copy {
        display: none !important;
    }

    .order-ai-extract-live-grid {
        display: grid !important;
        grid-template-columns: minmax(0, 1fr) !important;
        grid-auto-flow: row !important;
        grid-auto-columns: unset !important;
        grid-template-rows: none !important;
        gap: 0.5rem;
        align-content: start;
        justify-content: stretch;
        width: 100%;
        overflow: visible !important;
        padding-bottom: 0 !important;
    }

    .order-ai-extract-live-row {
        --order-ai-row-columns: 8;
        display: grid;
        grid-template-columns: repeat(var(--order-ai-row-columns), minmax(0, 1fr));
        gap: 0.5rem;
        width: 100%;
    }

    .order-ai-extract-page-chip {
        width: 100%;
        min-width: 0;
        max-width: none;
        height: 2.15rem;
        padding: 0 0.45rem;
        display: grid;
        grid-template-columns: 0.82rem 1fr;
        align-items: center;
        justify-items: center;
        gap: 0.2rem;
        box-sizing: border-box;
    }

    .order-ai-extract-page-chip-state {
        width: 0.82rem;
        min-width: 0.82rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.72rem;
        line-height: 1;
    }

    .order-ai-extract-page-chip-state.is-empty {
        visibility: hidden;
    }

    .order-ai-extract-page-chip-number {
        min-width: 0;
        text-align: center;
        font-variant-numeric: tabular-nums;
    }

    @media (max-width: 991.98px) {
        .order-ai-extract-live-grid {
            gap: 0.45rem;
        }

        .order-ai-extract-live-row {
            gap: 0.45rem;
        }
    }

    @media (max-width: 767.98px) {
        .order-ai-extract-live-grid {
            gap: 0.42rem;
        }

        .order-ai-extract-live-row {
            gap: 0.42rem;
        }
    }

    @media (max-width: 575.98px) {
        .order-ai-extract-live-grid {
            gap: 0.38rem;
        }

        .order-ai-extract-live-row {
            gap: 0.38rem;
        }

        .order-ai-extract-page-chip {
            height: 2.05rem;
            padding: 0 0.4rem;
        }
    }
</style>
@endsection

@section('content')
@php
  $heroRobotLottieAsset = null;
  $heroRobotLottieScriptAsset = null;
  $heroRobotLottiePath = resource_path('images/order-ai/hero-robot.lottie');

  if (is_file($heroRobotLottiePath) && is_readable($heroRobotLottiePath)) {
    $heroRobotLottieAsset = 'data:application/octet-stream;base64,' . base64_encode((string) file_get_contents($heroRobotLottiePath));
  }

  if (file_exists(public_path('vendors/js/order-ai/dotlottie/dotlottie-wc.js'))) {
    $heroRobotLottieScriptAsset = asset('vendors/js/order-ai/dotlottie/dotlottie-wc.js');
  }

@endphp
<section
  class="order-ai-shell {{ !empty($initialScanId) ? 'is-initializing' : '' }}"
  id="order-ai-app"
  data-upload-url="{{ route('app-order-ai-scan-upload') }}"
  data-transfer-url="{{ route('app-orders-store') }}"
  data-positions-url="{{ route('app-orders-positions') }}"
  data-status-template="{{ route('app-order-ai-scan-status', ['scan' => '__SCAN__']) }}"
  data-initial-scan-id="{{ (int) ($initialScanId ?? 0) }}"
  data-opened-from-history="{{ !empty($openedFromHistory) ? '1' : '0' }}"
  data-csrf="{{ csrf_token() }}"
>
    <div class="order-ai-initial-loader" id="order-ai-initial-loader">
      <div class="order-ai-initial-loader-card">
        <span class="spinner-border text-primary mx-auto" role="status" aria-hidden="true"></span>
        <strong>Učitavam odabrani sken...</strong>
        <span class="order-ai-subtle small">Rezultat će biti prikazan odmah nakon učitavanja podataka.</span>
      </div>
    </div>
  <div class="row">
    <div class="col-12">
      <div class="card order-ai-hero mb-2">
        <div class="card-body p-2 p-md-3">
          <div class="order-ai-hero-grid">
            <div class="order-ai-hero-story{{ (!empty($heroRobotLottieAsset) && !empty($heroRobotLottieScriptAsset)) ? ' has-hero-visual' : '' }}">
              <div class="order-ai-hero-story-inner">
                <div class="order-ai-hero-copy">
                  <span class="order-ai-chip mb-1">
                    <i class="fa fa-magic" aria-hidden="true"></i>
                    {{ __('locale.Skeniraj narudzbu sa AI') }}
                  </span>
                  <h2 class="mb-75 order-ai-title">{{ __('locale.Skeniraj narudzbu sa AI') }}</h2>
                  <p class="mb-0 order-ai-subtle" style="max-width:720px;">
                    Ubaci PDF, sliku ili izvoz dokumenta. Dokument se zadržava na istoj stranici, AI obrada se izvršava,
                    a upis u bazu se pokreće tek nakon ručne potvrde transfera.
                  </p>
                </div>
                @if(!empty($heroRobotLottieAsset) && !empty($heroRobotLottieScriptAsset))
                  <div class="order-ai-hero-visual" aria-hidden="true">
                    <dotlottie-wc class="order-ai-hero-lottie" src="{{ $heroRobotLottieAsset }}" autoplay loop></dotlottie-wc>
                  </div>
                @endif
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
                <span class="order-ai-stat-value">{{ $autoTransferEnabled ? 'Auto transfer uključen' : 'Ručni transfer aktivan' }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="row g-2 order-ai-upload-stage-row mb-2">
        <div class="col-lg-7 col-12" id="order-ai-dropzone-shell">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-2 p-md-3">
              <div class="order-ai-dropzone" id="order-ai-dropzone" tabindex="0" role="button" aria-label="Učitaj dokument za AI skeniranje">
                <input type="file" class="d-none" id="order-ai-file-input" accept=".pdf,.png,.jpg,.jpeg,.webp,.bmp,.tif,.tiff,.json,.txt,.csv,.xls,.xlsx,.doc,.docx">
                <div class="order-ai-dropzone-icon">
                  <i data-feather="upload-cloud"></i>
                </div>
                <h3 class="mb-75">Prevuci dokument ovdje</h3>
                <p class="order-ai-subtle mb-1">ili se klikom odabire fajl za AI obradu narudžbe</p>
                <small class="text-muted">PDF, slike i izvozi do 50 MB</small>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-5 col-12 order-ai-progress-shell" id="order-ai-progress-shell">
          <div class="card order-ai-progress-card" id="order-ai-progress-card">
            <div class="card-body p-2">
              <div class="order-ai-progress-head mb-1">
                <div class="order-ai-progress-copy">
                  <h4 class="mb-25">Status obrade</h4>
                  <p class="mb-0 order-ai-subtle" id="order-ai-progress-label">Čekam upload...</p>
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
                  <span class="order-ai-progress-runtime" id="order-ai-progress-runtime">
                    <span class="order-ai-progress-runtime-label">Proteklo vrijeme:</span>
                    <span class="order-ai-progress-runtime-value" id="order-ai-elapsed-time">0s</span>
                  </span>
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
                      <div class="small text-muted">Fajl se šalje u lokalni prihvat.</div>
                    </div>
                  </div>
                </div>
                <div class="order-ai-stage" data-stage="extract">
                  <div class="order-ai-stage-fill" data-stage-fill></div>
                  <div class="order-ai-stage-content">
                    <span class="order-ai-stage-bullet"></span>
                    <div class="order-ai-stage-main">
                      <strong>AI ekstrakcija</strong>
                      <div class="small text-muted">Dokument se čita i pretvara se u preglednu narudžbu za provjeru.</div>
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
                        Akcije su na dnu stranice. Nakon završetka obrade omogućava se upis u bazu.
                      </div>
                    </div>
                    <div class="order-ai-stage-side">
                      <button type="button" class="btn order-ai-primary-action" id="order-ai-primary-action-button" disabled>Poduzmi akciju</button>
                    </div>
                  </div>
                </div>
              </div>

              <div class="alert alert-warning order-ai-alert mt-2 mb-0 order-ai-hidden" id="order-ai-progress-warning"></div>
            </div>
          </div>
        </div>
      </div>

      <div class="row g-2 mb-2 order-ai-hidden" id="order-ai-extract-live-shell">
        <div class="col-12">
          <div class="card order-ai-progress-card">
            <div class="card-body p-2 p-md-3">
              <div class="order-ai-extract-live" id="order-ai-extract-live" data-phase-index="0">
                <div class="order-ai-extract-live-header">
                  <span class="order-ai-extract-live-meta" id="order-ai-extract-live-meta">Čekam dokument.</span>
                </div>
                <div class="order-ai-extract-global-progress">
                  <span class="order-ai-extract-global-progress-bar" id="order-ai-extract-live-progress-bar"></span>
                </div>
                <div class="order-ai-extract-focus">
                  <span class="order-ai-extract-focus-label">Tok obrade</span>
                  <strong class="order-ai-extract-focus-value" id="order-ai-extract-current-step">Priprema dokumenta</strong>
                </div>
                <div class="order-ai-extract-live-grid" id="order-ai-extract-live-grid"></div>
                <div class="order-ai-extract-phase-list" id="order-ai-extract-phase-list"></div>
              </div>
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
                  <p class="mb-0 order-ai-subtle" id="order-ai-result-caption">Nema obrađenog dokumenta.</p>
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

              <div class="order-ai-bottom-actions order-ai-hidden" id="order-ai-actions">
                <div class="order-ai-bottom-actions-secondary">
                  <a href="#" class="btn order-ai-secondary-action order-ai-hidden" id="order-ai-view-pdf-button" target="_blank" rel="noopener">Vidi PDF</a>
                  <button type="button" class="btn order-ai-secondary-action" id="order-ai-new-order-button">Nova narudžba</button>
                  <a href="{{ route('app-ai-token-history') }}" class="btn order-ai-secondary-action">Historija</a>
                  <a href="{{ route('app-orders') }}" class="btn order-ai-secondary-action">Moje narudžbe</a>
                  <button type="button" class="btn order-ai-secondary-action order-ai-hidden" id="order-ai-view-order-button">Vidi narudžbu</button>
                  <button type="button" class="btn order-ai-secondary-action order-ai-hidden" id="order-ai-view-positions-button">Pozicije</button>
                </div>
                <div class="order-ai-bottom-action-primary">
                  <button type="button" class="btn btn-success order-ai-transfer-cta" id="order-ai-transfer-button" disabled>Transfer u bazu</button>
                </div>
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
          <h5 class="modal-title mb-0" id="order-ai-order-modal-label">Pregled spremljene narudžbe</h5>
          <div class="small text-muted">Kratki pregled narudžbe nakon upisa u bazu</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zatvori"></button>
      </div>
      <div class="modal-body" id="order-ai-order-modal-body"></div>
      <div class="modal-footer">
        <a href="{{ route('app-orders') }}" class="btn btn-outline-primary">Upravljanje narudžbama</a>
        <button type="button" class="btn btn-success" id="order-ai-modal-new-order-button">Nova narudžba</button>
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
          <h5 class="modal-title mb-0" id="order-ai-positions-modal-label">Pozicije spremljene narudžbe</h5>
          <div class="small text-muted" id="order-ai-positions-modal-subtitle">Pregled pozicija upisanih u bazu</div>
        </div>
        <div class="d-flex align-items-center gap-1">
          <button type="button" class="btn btn-sm btn-outline-primary" id="order-ai-positions-refresh-button">Osvježi</button>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zatvori"></button>
        </div>
      </div>
      <div class="modal-body order-ai-positions-body">
        <div class="order-ai-positions-loading" id="order-ai-positions-loading">
          <span class="spinner-border text-primary" role="status" aria-hidden="true"></span>
          <div>Učitavam pozicije spremljene narudžbe...</div>
        </div>
        <div class="alert alert-danger mb-0 order-ai-hidden" id="order-ai-positions-error"></div>
        <div id="order-ai-positions-content" class="order-ai-hidden"></div>
      </div>
      <div class="modal-footer">
        <a href="{{ route('app-orders') }}" class="btn btn-outline-primary">Upravljanje narudžbama</a>
        <button type="button" class="btn btn-success" id="order-ai-positions-new-order-button">Nova narudžba</button>
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
          <div class="small text-muted">Prikaz razloga zbog kojeg Pantheon nije prihvatio narudžbu</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zatvori"></button>
      </div>
      <div class="modal-body">
        <div class="order-ai-transfer-error-copy" id="order-ai-transfer-error-modal-body"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-primary" id="order-ai-transfer-error-retry-button">Pokušaj ponovo</button>
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
          <div class="small text-muted" id="order-ai-line-total-modal-subtitle">Poredi skenirani total sa proračunom iz količine i jed. cijene.</div>
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
              <div class="order-ai-total-check-label">Preračunato iz jed. cijene i količine</div>
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

@section('vendor-script')
  <script src="{{ asset('vendors/js/extensions/sweetalert2.all.min.js') }}"></script>
@endsection

@section('page-script')
  @if(!empty($heroRobotLottieAsset) && !empty($heroRobotLottieScriptAsset))
    <script type="module" src="{{ $heroRobotLottieScriptAsset }}"></script>
  @endif
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
      const openedFromHistory = app.dataset.openedFromHistory === '1';
      const csrfToken = app.dataset.csrf;
      const initialLoader = document.getElementById('order-ai-initial-loader');
      const dropzoneShell = document.getElementById('order-ai-dropzone-shell');
      const progressShell = document.getElementById('order-ai-progress-shell');
      const dropzone = document.getElementById('order-ai-dropzone');
      const fileInput = document.getElementById('order-ai-file-input');
      const progressCard = document.getElementById('order-ai-progress-card');
      const progressLabel = document.getElementById('order-ai-progress-label');
      const progressPercent = document.getElementById('order-ai-progress-percent');
      const progressBar = document.getElementById('order-ai-progress-bar');
      const fileNameEl = document.getElementById('order-ai-file-name');
      const activityIndicator = document.getElementById('order-ai-activity-indicator');
      const activityText = document.getElementById('order-ai-activity-text');
      const extractLiveShell = document.getElementById('order-ai-extract-live-shell');
      const extractLive = document.getElementById('order-ai-extract-live');
      const extractLiveMeta = document.getElementById('order-ai-extract-live-meta');
      const extractLiveProgressBar = document.getElementById('order-ai-extract-live-progress-bar');
      const extractCurrentStep = document.getElementById('order-ai-extract-current-step');
      const extractLiveGrid = document.getElementById('order-ai-extract-live-grid');
      const extractPhaseList = document.getElementById('order-ai-extract-phase-list');
      const progressWarning = document.getElementById('order-ai-progress-warning');
      const elapsedTimeEl = document.getElementById('order-ai-elapsed-time');
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
      const primaryActionButton = document.getElementById('order-ai-primary-action-button');
      const transferHint = document.getElementById('order-ai-transfer-hint');
      const viewPdfButton = document.getElementById('order-ai-view-pdf-button');
      const ordersPageUrl = @json(route('app-orders'));
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

      if (actions && viewPdfButton) {
        const ordersLink = Array.from(actions.querySelectorAll('.order-ai-bottom-actions-secondary a.order-ai-secondary-action')).find(function (link) {
          return String(link.getAttribute('href') || '').trim() === ordersPageUrl;
        });

        if (ordersLink && ordersLink.parentNode) {
          ordersLink.insertAdjacentElement('afterend', viewPdfButton);
        }
      }

      let pollTimer = null;
      let currentScanId = null;
      let uploadProgress = 0;
      let latestStatusPayload = null;
      let isTransferBusy = false;
      let extractFillTimer = null;
      let extractVisualProgress = 0;
      let elapsedTimer = null;
      let activeLineTotalIndex = null;
      let openedFromExistingScan = Boolean(initialScanId);
      let hasAutoScrolledToExtraction = false;
      let hasAutoScrolledToResult = false;
      let lastRenderedStatus = '';
      let progressAnimationFrame = null;
      let pendingProgressState = null;
      let lastProgressPercent = null;
      let lastProgressLabel = '';
      let lastFactsSignature = '';
      let lastLinesSignature = '';
      let lastWarningsSignature = '';
      let lastExtractLiveSignature = '';
      let extractSimulationStartedAt = null;
      let extractSimulationPageCount = 1;
      let extractSimulationStatus = '';
      const stageFillState = {
        upload: null,
        extract: null,
        transfer: null,
      };
      const EXTRACTION_STEPS = [
        'Priprema dokumenta',
        'Prepoznavanje sadržaja',
        'Klasifikacija stavki',
        'Ekstrakcija podataka',
        'Provjera rezultata',
      ];
      const OVERALL_PHASE_COUNT = EXTRACTION_STEPS.length + 1;

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

      function setInitializingState(active) {
        app.classList.toggle('is-initializing', Boolean(active));
        if (initialLoader) {
          initialLoader.setAttribute('aria-hidden', active ? 'false' : 'true');
        }
      }

      function syncDropzoneVisibility(status) {
        const resolvedStatus = String(status || (latestStatusPayload && latestStatusPayload.status) || '').trim();
        const showProcessingState = openedFromExistingScan || ['uploading', 'uploaded', 'extracting', 'completed', 'ready_for_transfer', 'transferring', 'transferred', 'failed'].includes(resolvedStatus);
        const showDropzone = !showProcessingState;

        setVisible(dropzoneShell, showDropzone);
        dropzoneShell.classList.toggle('order-ai-dropzone-shell-wide', showDropzone);

        if (progressShell) {
          setVisible(progressShell, showProcessingState);
          progressShell.classList.toggle('order-ai-progress-shell-wide', showProcessingState);
        }
      }

      function toFiniteNumber(value, fallback) {
        const number = Number(value);

        return Number.isFinite(number) ? number : (fallback ?? 0);
      }

      function formatAmount(value) {
        return toFiniteNumber(value, 0).toFixed(2);
      }

      function formatWholeNumber(value) {
        return String(Math.max(0, Math.round(toFiniteNumber(value, 0))));
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

      function resolveLoadedScanLabel(fileName) {
        const name = String(fileName || '').trim();

        if (name === '') {
          return 'Sken dokumenta uspješno učitan.';
        }

        return /\.pdf$/i.test(name)
          ? `Sken PDF-a "${name}" uspješno učitan.`
          : `Sken dokumenta "${name}" uspješno učitan.`;
      }

      function showLoadedScanToast(fileName) {
        if (!window.Swal || typeof window.Swal.fire !== 'function') {
          return;
        }

        window.Swal.fire({
          toast: true,
          position: 'top-end',
          icon: 'success',
          title: resolveLoadedScanLabel(fileName),
          showConfirmButton: false,
          timer: 2200,
          timerProgressBar: true,
        });
      }

      function showTransferSuccessAlert(orderView) {
        if (!window.Swal || typeof window.Swal.fire !== 'function') {
          return;
        }

        const resolvedOrderView = String(orderView || '').trim();

        window.Swal.fire({
          icon: 'success',
          title: 'Transfer uspješan',
          text: resolvedOrderView !== ''
            ? `Narudžba je uspješno prenesena u bazu kao ${resolvedOrderView}.`
            : 'Narudžba je uspješno prenesena u bazu.',
          showConfirmButton: false,
          timer: 1000,
          timerProgressBar: true,
        });
      }

      function parseTimestamp(value) {
        const raw = String(value || '').trim();

        if (raw === '') {
          return null;
        }

        const timestamp = Date.parse(raw);

        return Number.isFinite(timestamp) ? timestamp : null;
      }

      function formatElapsedRuntime(totalSeconds) {
        const safeSeconds = Math.max(0, Math.round(totalSeconds || 0));
        const hours = Math.floor(safeSeconds / 3600);
        const minutes = Math.floor((safeSeconds % 3600) / 60);
        const seconds = safeSeconds % 60;

        if (hours > 0) {
          return `${hours}h ${minutes}m`;
        }

        if (minutes > 0) {
          return `${minutes}m ${seconds}s`;
        }

        return `${seconds}s`;
      }

      function stopElapsedTimer() {
        if (elapsedTimer) {
          clearInterval(elapsedTimer);
          elapsedTimer = null;
        }
      }

      function renderElapsedRuntime(data) {
        if (!elapsedTimeEl) {
          return;
        }

        const status = String(data && data.status || '').trim();
        const startedAt = parseTimestamp(data && data.started_at);
        const finishedAt = parseTimestamp(data && data.finished_at);
        const elapsedSeconds = Math.max(0, Math.round(toFiniteNumber(data && data.elapsed_seconds, 0)));
        const elapsedDisplay = String(data && data.elapsed_display || '').trim();
        const isFinal = ['completed', 'failed', 'ready_for_transfer', 'transferring', 'transferred'].includes(status) || Boolean(finishedAt);

        if (!startedAt && elapsedSeconds === 0) {
          stopElapsedTimer();
          elapsedTimeEl.textContent = '0s';
          return;
        }

        if (isFinal) {
          stopElapsedTimer();
          elapsedTimeEl.textContent = elapsedDisplay || formatElapsedRuntime(elapsedSeconds);
          return;
        }

        const syncElapsed = function () {
          const liveSeconds = startedAt ? Math.max(elapsedSeconds, Math.floor((Date.now() - startedAt) / 1000)) : elapsedSeconds;
          elapsedTimeEl.textContent = formatElapsedRuntime(liveSeconds);
        };

        syncElapsed();

        if (!elapsedTimer) {
          elapsedTimer = window.setInterval(syncElapsed, 1000);
        }
      }

      function scrollToNode(node, blockPosition) {
        if (!node) {
          return;
        }

        node.scrollIntoView({
          behavior: 'smooth',
          block: blockPosition || 'start',
        });
      }

      function scrollToResultSection() {
        scrollToNode(resultCard);
      }

      function scrollToExtractionSection() {
        scrollToNode(extractLiveShell);
      }

      function scrollToActionSection() {
        scrollToNode(actions, 'end');
      }

      function maybeAutoScrollToExtraction(status, previousStatus) {
        const extractionStatuses = ['uploaded', 'extracting'];
        const shouldAutoScroll = !openedFromExistingScan
          && !hasAutoScrolledToExtraction
          && extractionStatuses.includes(status)
          && !extractionStatuses.includes(previousStatus);

        if (shouldAutoScroll) {
          hasAutoScrolledToExtraction = true;
          window.setTimeout(scrollToExtractionSection, 220);
        }
      }

      function resolvePageCount(data) {
        const payload = data && data.result ? data.result : {};
        const payloadPageCount = toFiniteNumber(payload && payload.order && payload.order.page_count, 0);
        const statusPageCount = toFiniteNumber(data && data.page_count, payloadPageCount);

        return Math.max(0, Math.round(statusPageCount));
      }

      function resolveExtractRowSizes(model) {
        const totalPages = Math.max(1, Number(model && model.totalPages || 1));
        const isDesktop = window.matchMedia('(min-width: 992px)').matches;
        const isTablet = window.matchMedia('(min-width: 576px) and (max-width: 991.98px)').matches;
        const isComplete = String(model && model.status || '') === 'done';
        let rowCount = 1;

        if (isDesktop) {
          rowCount = isComplete || totalPages <= 8 ? 1 : 2;
        } else if (isTablet) {
          rowCount = Math.max(1, Math.ceil(totalPages / 6));
        } else {
          rowCount = Math.max(1, Math.ceil(totalPages / 4));
        }

        const baseSize = Math.floor(totalPages / rowCount);
        const extraItems = totalPages % rowCount;

        return Array.from({ length: rowCount }, function (_, index) {
          return baseSize + (index < extraItems ? 1 : 0);
        }).filter(function (size) {
          return size > 0;
        });
      }

      function renderExtractLive(data) {
        if (!extractLive || !extractLiveGrid || !extractLiveMeta) {
          return;
        }

        const status = String(data && data.status || '').trim();
        const visibleStatuses = ['uploaded', 'extracting', 'completed', 'ready_for_transfer', 'transferring', 'transferred'];

        if (!visibleStatuses.includes(status)) {
          extractLiveGrid.innerHTML = '';
          extractLiveMeta.textContent = 'Čekam dokument.';
          setVisible(extractLive, false);
          return;
        }

        const pageCount = Math.max(1, resolvePageCount(data) || 1);
        const isComplete = ['completed', 'ready_for_transfer', 'transferring', 'transferred'].includes(status);
        const normalizedProgress = isComplete
          ? 1
          : Math.max(0.08, Math.min(0.95, extractVisualProgress / 100));
        const completedPages = isComplete ? pageCount : Math.min(pageCount, Math.floor(normalizedProgress * pageCount));
        const activePage = isComplete ? 0 : Math.min(pageCount, completedPages + 1);
        const pageLabel = pageCount === 1 ? 'stranica' : 'stranice';

        extractLiveMeta.textContent = `${pageCount} ${pageLabel} · ${String(data && data.processing_step || 'AI obrada je u toku.').trim()}`;
        extractLiveGrid.innerHTML = Array.from({ length: pageCount }).map(function (_, index) {
          const pageNumber = index + 1;
          let stateClass = 'is-pending';
          let stateLabel = 'Čeka';

          if (isComplete || pageNumber <= completedPages) {
            stateClass = 'is-done';
            stateLabel = 'Obrađeno';
          } else if (pageNumber === activePage) {
            stateClass = 'is-active';
            stateLabel = 'U obradi';
          }

          return `
            <div class="order-ai-extract-step ${stateClass}">
              <span class="order-ai-extract-step-index">Str ${pageNumber}</span>
              <span class="order-ai-extract-step-label">Ekstrakcija</span>
              <span class="order-ai-extract-step-status">${stateLabel}</span>
            </div>
          `;
        }).join('');

        setVisible(extractLive, true);
      }

      function showPendingExtractionState(data) {
        const statusData = data && typeof data === 'object' ? data : {};

        latestStatusPayload = statusData;

        if (statusData.source_file_name) {
          fileNameEl.textContent = statusData.source_file_name;
        }

        syncDropzoneVisibility('extracting');
        setProgress(Math.max(18, toFiniteNumber(statusData.current_progress, 18)), 'AI obrada je pokrenuta. Dokument se analizira...');
        setStageState('extract', false);
        setStageFill('upload', 100);
        startExtractFillAnimation(18);
        updateActivityState({ status: 'extracting' });
        renderExtractLive(Object.assign({}, statusData, {
          status: 'extracting',
          processing_step: 'AI obrada je pokrenuta. Dokument se analizira...'
        }));
        renderElapsedRuntime(Object.assign({}, statusData, {
          status: 'extracting'
        }));
        setTransferButtonState({
          enabled: false,
          label: 'Transfer u bazu',
          hint: 'Dokument se čita i priprema se pregled narudžbe.'
        });
        setPrimaryActionButtonState({
          enabled: false,
          label: 'Poduzmi akciju'
        });
      }

      function setPrimaryActionButtonState(options) {
        const config = options || {};

        if (!primaryActionButton) {
          return;
        }

        primaryActionButton.disabled = !Boolean(config.enabled);
        primaryActionButton.textContent = config.label || 'Poduzmi akciju';
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

      function resolveDocumentDisplayedTotal(summary) {
        const subtotal = toFiniteNumber(summary && summary.subtotal, 0);
        const grandTotal = toFiniteNumber(summary && summary.grand_total, 0);

        return subtotal > 0 ? roundMoney(subtotal) : roundMoney(grandTotal);
      }

      function resolveDocumentTotalComparison(payload) {
        const result = payload || {};
        const summary = result.summary || {};
        const items = Array.isArray(result.items) ? result.items : [];
        const documentTotal = resolveDocumentDisplayedTotal(summary);
        const itemsTotal = roundMoney(items.reduce(function (carry, item) {
          return carry + resolveLineSourceTotal(item);
        }, 0));
        const difference = roundMoney(documentTotal - itemsTotal);
        const hasComparableValues = documentTotal > 0 || itemsTotal > 0;

        return {
          documentTotal: documentTotal,
          itemsTotal: itemsTotal,
          difference: difference,
          hasComparableValues: hasComparableValues,
          matches: hasComparableValues && Math.abs(difference) <= 0.01,
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
              drawing_reference: String(item.drawing_reference || '').trim(),
              material_hint: String(item.material_hint || '').trim(),
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
          return 'Transfer u bazu nije uspio, ali detaljan razlog nije vraćen.';
        }

        if (/rtHE_Order_tHE_SetSubj_21|anConsigneeQId/i.test(message)) {
          return 'Pantheon nije prihvatio naručitelja jer nije bio postavljen validan subject za anConsigneeQId.';
        }

        return message;
      }

      function resetMessages() {
        [progressWarning, warningsBox, errorBox, successBox].forEach((node) => {
          node.textContent = '';
          setVisible(node, false);
        });

        if (progressWarning) {
          progressWarning.classList.remove('is-preview-ready');
        }
      }

      function setProgressWarningMessage(message, options) {
        const config = options || {};
        const text = String(message || '').trim();

        if (!progressWarning) {
          return;
        }

        progressWarning.textContent = text;
        progressWarning.classList.toggle('is-preview-ready', Boolean(config.previewReady) && text !== '');
        setVisible(progressWarning, text !== '');
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
        return Math.round((Math.max(0, Math.min(100, rawPercent)) / 100) * (100 / OVERALL_PHASE_COUNT));
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
          transferred: 'Sačuvano u bazi',
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
          transferHint.textContent = config.hint || 'Dugme se aktivira kada AI završi ekstrakciju i pripremi payload.';
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
        const pantheon = statusData.pantheon_order || {};
        const totalComparison = resolveDocumentTotalComparison(payload);
        const amountMeta = totalComparison.hasComparableValues && !totalComparison.matches
          ? `Razlika: ${formatAmount(totalComparison.difference)}`
          : '';
        const factsMarkup = [
          { label: 'Kupac', value: order.customer_name || '-' },
          { label: 'Naručitelj', value: order.supplier_name || '-' },
          { label: 'Referenca', value: order.external_document_number || '-' },
          { label: 'Doc type', value: order.document_type || '-' },
          { label: 'Valuta', value: order.currency || '-' },
          {
            label: 'Iznos',
            value: formatAmount(totalComparison.documentTotal || 0),
            stateClass: totalComparison.hasComparableValues ? (totalComparison.matches ? 'is-match' : 'is-mismatch') : '',
            meta: amountMeta,
          },
          { label: 'AI krediti', value: formatWholeNumber(statusData.billed_tokens || 0) },
          { label: 'Pantheon ključ', value: pantheon.key || '-' }
        ];

        facts.innerHTML = factsMarkup.map((fact) => `
          <div class="order-ai-fact ${escapeHtml(fact.stateClass || '')}">
            <div class="text-muted small mb-50">${escapeHtml(fact.label)}</div>
            <div class="fw-bolder">${escapeHtml(fact.value)}</div>
            ${fact.meta ? `<div class="order-ai-fact-meta">${escapeHtml(fact.meta)}</div>` : ''}
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
              <span class="badge rounded-pill bg-light-success text-success px-1 py-75">Sačuvano u bazi</span>
              <h5 class="mb-50 mt-1">Narudžba ${escapeHtml(displayNumber)} je uspješno upisana u bazu.</h5>
              <p class="mb-0 text-muted">Otvoriš preview ili pozicije jednim klikom, pa odmah nastaviš na upravljanje narudžbama.</p>
            </div>
          </div>
          <div class="order-ai-saved-grid">
            <div class="order-ai-saved-item">
              <div class="text-muted small mb-50">Kupac</div>
              <div class="fw-bolder">${escapeHtml(order.customer_name || '-')}</div>
            </div>
            <div class="order-ai-saved-item">
              <div class="text-muted small mb-50">Naručitelj</div>
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
              <div class="text-muted small mb-50">Narudžba u bazi</div>
              <div class="fw-bolder">${escapeHtml(pantheon.view || pantheon.key || '-')}</div>
            </div>
            <div class="order-ai-modal-summary-card">
              <div class="text-muted small mb-50">Kupac</div>
              <div class="fw-bolder">${escapeHtml(order.customer_name || '-')}</div>
            </div>
            <div class="order-ai-modal-summary-card">
              <div class="text-muted small mb-50">Naručitelj</div>
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
                  <th>Šifra</th>
                  <th>Naziv</th>
                  <th>Količina</th>
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
              <div class="small text-muted mb-50">Tehnički detalj</div>
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
            subtitle: orderNumber ? `Narudžba ${orderNumber}` : 'Pregled pozicija upisanih u bazu',
            error: 'Ruta za pozicije trenutno nije dostupna.'
          });
          return;
        }

        if (!orderNumber) {
          setPositionsModalState({
            loading: false,
            showContent: false,
            error: 'Broj spremljene narudžbe nije dostupan.'
          });
          return;
        }

        setPositionsModalState({
          loading: true,
          showContent: false,
          html: '',
          error: '',
          subtitle: `Narudžba ${orderNumber}`
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
            subtitle: `Narudžba ${orderNumber}`
          });
          syncFeatherIcons();
        } catch (error) {
          setPositionsModalState({
            loading: false,
            showContent: false,
            subtitle: `Narudžba ${orderNumber}`,
            error: 'Pozicije trenutno nije moguće učitati. Pokušaj ponovo za nekoliko trenutaka.'
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
        resultCaption.textContent = 'Nema obrađenog dokumenta.';
        resultStatus.textContent = 'Spremno';
        setVisible(resultCard, false);
        setVisible(linesShell, false);
        setVisible(savedPreview, false);
        setVisible(actions, false);
        setVisible(transferFollowup, false);
        setVisible(viewOrderButton, false);
        resetMessages();
        setPositionsModalState({
          loading: false,
          showContent: false,
          html: '',
          error: '',
          subtitle: 'Pregled pozicija upisanih u bazu'
        });
        setProgress(0, 'Čekam upload...');
        setStageState('upload', false);
        setStageFill('upload', 0);
        setStageFill('transfer', 0);
        updateActivityState(null);
        setTransferButtonState({
          enabled: false,
          label: 'Transfer u bazu',
          hint: 'Dugme se aktivira kada AI završi ekstrakciju i pripremi payload.'
        });
        syncDropzoneVisibility('');
      }

      function startNewOrder() {
        openedFromExistingScan = false;
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

        syncDropzoneVisibility(data.status);
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
        setVisible(viewOrderButton, false);

        if (data.status === 'failed') {
          errorBox.textContent = data.error_message || 'AI obrada nije uspjela.';
          setVisible(errorBox, true);
          setVisible(transferFollowup, true);
          if (/(transfer|baza|pantheon)/i.test(String(data.processing_step || '')) || /(anConsigneeQId|SetSubj|Pantheon)/i.test(String(data.error_message || ''))) {
            showTransferErrorModal(data.error_message || 'Transfer u bazu nije uspio.', data.error_message || '');
          }
          setTransferButtonState({
            enabled: false,
            label: 'Transfer u bazu',
            hint: 'AI obrada nije uspjela. Učitaj novi dokument i pokušaj ponovo.'
          });
          return;
        }

        if (data.status === 'completed') {
          setTransferButtonState({
            enabled: Boolean(data.transfer_ready),
            label: 'Transfer u bazu',
            hint: data.transfer_ready
              ? 'AI payload je spreman. Pregledaj rezultat i klikni za upis u bazu.'
              : 'Transfer ostaje zaključan dok svi obavezni podaci nisu pripremljeni.'
          });

          if (!autoTransfer) {
            if (data.transfer_ready && data.transfer_preview_error) {
              setProgressWarningMessage('Priprema preview-a nije uspjela, ali i dalje možeš pokušati ručni transfer u bazu.');
            } else if (data.transfer_ready && data.transfer_preview_available) {
              setProgressWarningMessage('Preview payload je pripremljen. Pregledaj rezultat i pokreni transfer u bazu kada budeš spreman.', {
                previewReady: true
              });
            } else if (data.transfer_ready) {
              setProgressWarningMessage('Rezultat je spreman za upis u bazu. Pokreni transfer kada budeš spreman.');
            } else {
              setProgressWarningMessage('Ekstrakcija je završena. Pregledaj rezultat i dopuni podatke ako nešto nedostaje.');
            }
            setVisible(transferFollowup, true);
          }
          return;
        }

        if (data.status === 'ready_for_transfer' || data.status === 'transferring') {
          setTransferButtonState({
            enabled: false,
            busy: true,
            label: autoTransfer ? 'Auto transfer radi...' : 'Transfer u toku...',
            hint: autoTransfer
              ? 'AI trenutno samostalno šalje narudžbu prema bazi.'
              : 'Narudžba se upravo upisuje u bazu.'
          });
          return;
        }

        if (data.status === 'transferred') {
          const orderView = data.pantheon_order && data.pantheon_order.view ? data.pantheon_order.view : data.pantheon_order.key;
          successBox.textContent = orderView
            ? `Narudžba je uspješno spremljena u bazu kao ${orderView}.`
            : 'Narudžba je uspješno spremljena u bazu.';
          setVisible(successBox, true);
          renderSavedPreview(data);
          setVisible(actions, true);
          setVisible(transferFollowup, true);
          setVisible(viewOrderButton, true);
          setTransferButtonState({
            enabled: false,
            label: 'Prebačeno u bazu',
            hint: 'Narudžba je spremljena. Otvori preview, pregledaj pozicije ili započni novu narudžbu.',
            complete: true
          });
          return;
        }

        setTransferButtonState({
          enabled: false,
          label: 'Transfer u bazu',
          hint: 'AI trenutno čita dokument i priprema strukturu narudžbe.'
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
          setInitializingState(false);

          if (['completed', 'transferred', 'failed'].includes(data.status)) {
            stopPolling();
            return;
          }

          pollTimer = window.setTimeout(pollStatus, 1300);
        } catch (error) {
          setProgressWarningMessage('Status AI obrade trenutno nije dostupan.');
          updateActivityState(null);
          setInitializingState(false);
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
        openedFromExistingScan = false;
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
          setProgress(mapUploadProgressToOverall(uploadProgress), uploadProgress >= 100 ? 'Upload završen, pokrećem AI obradu...' : 'Dokument se učitava na server...');
          setStageState('upload', uploadProgress >= 100);
          setStageFill('upload', uploadProgress);
        });

        xhr.addEventListener('load', function () {
          if (xhr.status < 200 || xhr.status >= 300) {
            const response = xhr.response || {};
            setProgressWarningMessage(response.message || 'Upload nije uspio.');
            updateActivityState(null);
            return;
          }

          const response = xhr.response || {};
          currentScanId = response.scan_id;
          uploadProgress = 100;
          setProgress(18, 'Upload završen. Dokument čeka AI ekstrakciju.');
          setStageState('extract', false);
          setStageFill('upload', 100);
          startExtractFillAnimation(14);
          updateActivityState({ status: 'uploaded' });
          startPolling(response.scan_id);
        });

        xhr.addEventListener('error', function () {
          setProgressWarningMessage('Greška pri uploadu dokumenta.');
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
          hint: 'Narudžba se upravo upisuje u bazu.'
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

          const persistedStatusPayload = payload && payload.data && payload.data.scan_status && typeof payload.data.scan_status === 'object'
            ? payload.data.scan_status
            : null;

          if (!response.ok) {
            const failure = new Error((payload && (payload.reason || payload.message)) || 'Transfer u bazu nije uspio.');
            failure.technicalReason = payload && payload.technical_reason
              ? payload.technical_reason
              : (rawText || '');
            throw failure;
          }

          if (persistedStatusPayload) {
            latestStatusPayload = persistedStatusPayload;
          }

          if (latestStatusPayload) {
            latestStatusPayload.current_progress = 100;
            latestStatusPayload.status = 'transferred';
            latestStatusPayload.processing_step = 'Narudžba je ručno prebačena u bazu.';
            if (latestStatusPayload.result && Array.isArray(latestStatusPayload.result.items)) {
              latestStatusPayload.result.items = latestStatusPayload.result.items.map(function (item) {
                const isMissing = Boolean(item && item.catalog_item_missing);

                if (!isMissing) {
                  return item;
                }

                return Object.assign({}, item, {
                  catalog_item_exists: true,
                  catalog_item_missing: false,
                  catalog_item_created: true,
                  catalog_item_status: 'created',
                  catalog_item_notice: `Artikal ${String(item.product_code || '').trim()} je automatski kreiran tokom transfera.`,
                });
              });
            }
            if (Array.isArray(latestStatusPayload.warnings)) {
              latestStatusPayload.warnings = latestStatusPayload.warnings.map(function (warning) {
                const message = String(warning || '').trim();

                if (!message.includes('nije pronađen u bazi i biće automatski kreiran pri transferu')) {
                  return message;
                }

                return message.replace(
                  'nije pronađen u bazi i biće automatski kreiran pri transferu',
                  'nije postojao u bazi i automatski je kreiran tokom transfera'
                );
              });
            }
            latestStatusPayload.pantheon_order = {
              key: payload.data ? payload.data.pantheon_order_key : '',
              view: payload.data ? payload.data.pantheon_order_view : '',
              qid: payload.data ? payload.data.pantheon_order_qid : null,
            };
            latestStatusPayload.transfer_meta = payload.data || {};
            isTransferBusy = false;
            renderStatus(latestStatusPayload);
            showTransferSuccessAlert(latestStatusPayload.pantheon_order && latestStatusPayload.pantheon_order.view
              ? latestStatusPayload.pantheon_order.view
              : latestStatusPayload.pantheon_order && latestStatusPayload.pantheon_order.key
                ? latestStatusPayload.pantheon_order.key
                : '');
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
            label: 'Pokušaj ponovo',
            hint: 'Transfer nije uspio. Pregledaj grešku i pokušaj ponovo.'
          });
        }
      }

      function normalizeTransferFailureReason(reason) {
        const message = String(reason || '').trim();

        if (!message) {
          return 'Transfer u bazu nije uspio, ali detaljan razlog nije vraćen.';
        }

        if (/rtHE_Order_tHE_SetSubj_21|anConsigneeQId/i.test(message)) {
          return 'Pantheon nije prihvatio naručitelja jer nije bio postavljen validan subject za anConsigneeQId.';
        }

        return message;
      }

      function updateActivityState(data) {
        const status = String(data && data.status || '').trim();
        const extractionBusy = status === 'uploaded' || status === 'extracting';
        const transferBusy = status === 'transferring' || isTransferBusy;
        const visible = extractionBusy || transferBusy;

        if (activityText) {
          activityText.textContent = transferBusy ? 'Transfer u bazu je u toku...' : 'AI ekstrakcija radi...';
        }

        setVisible(activityIndicator, visible);
      }

      function resolveStatusLabel(status) {
        const map = {
          uploaded: 'Uploadovan',
          extracting: 'AI radi',
          completed: 'Spremno za pregled',
          ready_for_transfer: 'Spremno za transfer',
          transferring: 'Transfer u toku',
          transferred: 'Sačuvano u bazi',
          failed: 'Neuspjelo',
        };

        return map[status] || status || 'Spremno';
      }

      function setTransferButtonState(options) {
        const config = options || {};
        const busy = Boolean(config.busy);
        const complete = Boolean(config.complete);
        const enabled = Boolean(config.enabled) && !busy && !complete;
        const label = config.label || 'Transfer u bazu';

        if (!transferButton) {
          return;
        }

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
          transferHint.textContent = config.hint || 'Akcije su na dnu stranice. Nakon završetka obrade omogućava se upis u bazu.';
        }
      }

      function renderFacts(payload, statusData) {
        const order = payload.order || {};
        const pantheon = statusData.pantheon_order || {};
        const totalComparison = resolveDocumentTotalComparison(payload);
        const amountMeta = totalComparison.hasComparableValues && !totalComparison.matches
          ? `Razlika: ${formatAmount(totalComparison.difference)}`
          : '';
        const factsMarkup = [
          { label: 'Kupac', value: order.customer_name || '-' },
          { label: 'Naručilac', value: order.supplier_name || '-' },
          { label: 'Referenca', value: order.external_document_number || '-' },
          { label: 'Vrsta dokumenta', value: order.document_type || '-' },
          { label: 'Valuta', value: order.currency || '-' },
          {
            label: 'Iznos',
            value: formatAmount(totalComparison.documentTotal || 0),
            stateClass: totalComparison.hasComparableValues ? (totalComparison.matches ? 'is-match' : 'is-mismatch') : '',
            meta: amountMeta,
          },
          { label: 'AI krediti', value: formatWholeNumber(statusData.billed_tokens || 0) },
          { label: 'Pantheon ključ', value: pantheon.key || '-' },
        ];

        facts.innerHTML = factsMarkup.map((fact) => `
          <div class="order-ai-fact ${escapeHtml(fact.stateClass || '')}">
            <div class="text-muted small mb-50">${escapeHtml(fact.label)}</div>
            <div class="fw-bolder">${escapeHtml(fact.value)}</div>
            ${fact.meta ? `<div class="order-ai-fact-meta">${escapeHtml(fact.meta)}</div>` : ''}
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
                    <span class="order-ai-line-total-source">Skenirani total: ${escapeHtml(formatAmountWithCurrency(comparison.source, currency))}</span>
                    <span class="order-ai-line-total-diff">Razlika: ${escapeHtml(diffPrefix + formatAmountWithCurrency(comparison.difference, currency))}</span>
                  </span>
                </button>
              </td>
            </tr>
          `;
        }).join('');

        setVisible(linesShell, true);
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
              <span class="badge rounded-pill bg-light-success text-success px-1 py-75">Sačuvano u bazi</span>
              <h5 class="mb-50 mt-1">Narudžba ${escapeHtml(displayNumber)} je uspješno upisana u bazu.</h5>
              <p class="mb-0 text-muted">Pregled ili pozicije mogu se otvoriti jednim klikom, a zatim se može nastaviti na sljedeću narudžbu.</p>
            </div>
          </div>
          <div class="order-ai-saved-grid">
            <div class="order-ai-saved-item">
              <div class="text-muted small mb-50">Kupac</div>
              <div class="fw-bolder">${escapeHtml(order.customer_name || '-')}</div>
            </div>
            <div class="order-ai-saved-item">
              <div class="text-muted small mb-50">Naručilac</div>
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
              <div class="small text-muted mb-50">Tehnički detalj</div>
              <code>${escapeHtml(technical)}</code>
            </div>
          ` : ''}
        `;

        if (transferErrorModalElement && window.bootstrap && window.bootstrap.Modal) {
          window.bootstrap.Modal.getOrCreateInstance(transferErrorModalElement).show();
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
          lineTotalModalSubtitle.textContent = `${String(item.product_code || '-').trim()} - ${String(item.product_name || '-').trim()}`;
        }

        if (lineTotalStatusBox) {
          lineTotalStatusBox.className = `order-ai-total-check-banner ${comparison.matches ? 'is-match' : 'is-mismatch'}`;
          lineTotalStatusBox.innerHTML = comparison.matches
            ? '<strong>Provjera nije potrebna.</strong><div class="mt-50">Skenirani total se podudara sa preračunom iz količine i jed. cijene.</div>'
            : '<strong>Provjera je preporučena.</strong><div class="mt-50">Skenirani total odstupa od preračuna iz količine i jed. cijene.</div>';
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

      function resetInterface() {
        stopPolling();
        stopExtractFillAnimation(0);
        stopElapsedTimer();
        currentScanId = null;
        uploadProgress = 0;
        latestStatusPayload = null;
        isTransferBusy = false;
        activeLineTotalIndex = null;
        hasAutoScrolledToResult = false;
        lastRenderedStatus = '';
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
        if (extractLiveGrid) {
          extractLiveGrid.innerHTML = '';
        }
        if (extractLiveMeta) {
          extractLiveMeta.textContent = 'Čekam dokument.';
        }
        resultCaption.textContent = 'Nema obrađenog dokumenta.';
        resultStatus.textContent = 'Spremno';
        setVisible(resultCard, false);
        setVisible(linesShell, false);
        setVisible(savedPreview, false);
        setVisible(actions, false);
        syncSourcePdfButton(null, false);
        setVisible(viewOrderButton, false);
        setVisible(viewPositionsButton, false);
        setVisible(extractLive, false);
        resetMessages();
        setPositionsModalState({
          loading: false,
          showContent: false,
          html: '',
          error: '',
          subtitle: 'Pregled pozicija upisanih u bazu'
        });
        setProgress(0, 'Čekam upload...');
        setStageState('upload', false);
        setStageFill('upload', 0);
        setStageFill('transfer', 0);
        updateActivityState(null);
        renderElapsedRuntime(null);
        setTransferButtonState({
          enabled: false,
          label: 'Transfer u bazu',
          hint: 'Akcije su na dnu stranice. Nakon završetka obrade omogućava se upis u bazu.'
        });
        setPrimaryActionButtonState({
          enabled: false,
          label: 'Poduzmi akciju'
        });
        syncDropzoneVisibility('');
      }

      function handleUpload(file) {
        if (!file) {
          return;
        }

        stopPolling();
        stopElapsedTimer();
        closeSavedOrderModal();
        closePositionsModal();
        closeLineTotalModal();
        resetMessages();
        currentScanId = null;
        latestStatusPayload = null;
        uploadProgress = 0;
        isTransferBusy = false;
        openedFromExistingScan = false;
        hasAutoScrolledToResult = false;
        lastRenderedStatus = '';
        fileNameEl.textContent = file.name;
        setVisible(progressCard, true);
        setVisible(resultCard, false);
        setVisible(actions, false);
        setVisible(savedPreview, false);
        setVisible(viewOrderButton, false);
        setVisible(viewPositionsButton, false);
        setVisible(extractLive, false);
        facts.innerHTML = '';
        linesBody.innerHTML = '';
        savedPreview.innerHTML = '';
        orderModalBody.innerHTML = '';
        if (positionsModalContent) {
          positionsModalContent.innerHTML = '';
        }
        if (extractLiveGrid) {
          extractLiveGrid.innerHTML = '';
        }
        if (extractLiveMeta) {
          extractLiveMeta.textContent = 'Čekam dokument.';
        }
        setVisible(linesShell, false);
        setPositionsModalState({
          loading: false,
          showContent: false,
          html: '',
          error: '',
          subtitle: 'Pregled pozicija upisanih u bazu'
        });
        renderElapsedRuntime(null);
        setProgress(0, 'Priprema lokalnog prihvata fajla...');
        setStageState('upload', false);
        setStageFill('upload', 0);
        setStageFill('transfer', 0);
        stopExtractFillAnimation(0);
        updateActivityState(null);
        setTransferButtonState({
          enabled: false,
          label: 'Transfer u bazu',
          hint: 'Dokument se priprema za AI ekstrakciju.'
        });
        setPrimaryActionButtonState({
          enabled: false,
          label: 'Poduzmi akciju'
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
          setProgress(
            mapUploadProgressToOverall(uploadProgress),
            uploadProgress >= 100 ? 'Upload je završen. Pokreće se AI obrada...' : 'Dokument se učitava na server...'
          );
          setStageState('upload', uploadProgress >= 100);
          setStageFill('upload', uploadProgress);
        });

        xhr.addEventListener('load', function () {
          if (xhr.status < 200 || xhr.status >= 300) {
            const response = xhr.response || {};
            setProgressWarningMessage(response.message || 'Upload nije uspio.');
            updateActivityState(null);
            return;
          }

          const response = xhr.response || {};
          currentScanId = response.scan_id;
          uploadProgress = 100;
          showPendingExtractionState(response.data || {
            source_file_name: file.name,
            page_count: 1,
            current_progress: 18,
          });
          startPolling(response.scan_id);
        });

        xhr.addEventListener('error', function () {
          setProgressWarningMessage('Greska pri uploadu dokumenta.');
          updateActivityState(null);
        });

        xhr.send(formData);
      }

      function renderStatus(data) {
        const previousStatus = lastRenderedStatus;
        latestStatusPayload = data || {};
        const payload = latestStatusPayload.result || {};
        const autoTransfer = Boolean(latestStatusPayload.auto_transfer);
        const effectiveProgress = toFiniteNumber(latestStatusPayload.current_progress, 0);
        const status = String(latestStatusPayload.status || '').trim();
        const hasItems = Array.isArray(payload.items) && payload.items.length > 0;
        const order = payload.order || {};
        const hasOrderFacts = ['customer_name', 'supplier_name', 'external_document_number', 'document_type', 'currency'].some(function (key) {
          return String(order && order[key] || '').trim() !== '';
        });
        const showResultCard = hasItems
          || hasOrderFacts
          || ['completed', 'ready_for_transfer', 'transferring', 'transferred', 'failed'].includes(status);
        const stageName = detectStage(status, autoTransfer, latestStatusPayload.current_progress, latestStatusPayload.processing_step);
        const finalizeStage = (status === 'completed' && stageName === 'extract' && !autoTransfer)
          || (status === 'transferred' && stageName === 'transfer');
        const extractionProgressState = (status === 'uploaded' || status === 'extracting')
          ? buildExtractPhaseModel(latestStatusPayload)
          : null;

        syncDropzoneVisibility(status);
        setVisible(resultCard, showResultCard);
        setVisible(actions, showResultCard);
        resetMessages();

        if (latestStatusPayload.source_file_name) {
          fileNameEl.textContent = latestStatusPayload.source_file_name;
        }

        setProgress(
          extractionProgressState ? extractionProgressState.progressPercent : effectiveProgress,
          latestStatusPayload.processing_step || 'AI obrada je u toku.'
        );
        setStageState(stageName, finalizeStage);
        updateStageFills(latestStatusPayload);
        updateActivityState(latestStatusPayload);
        renderExtractLive(latestStatusPayload);
        renderElapsedRuntime(latestStatusPayload);
        renderFacts(payload, latestStatusPayload);
        renderLines(payload);
        renderWarnings(latestStatusPayload.warnings || []);

        resultCaption.textContent = latestStatusPayload.processing_step || 'Status nije dostupan.';
        resultStatus.textContent = resolveStatusLabel(status);
        setVisible(savedPreview, false);
        syncSourcePdfButton(latestStatusPayload, showResultCard);
        setVisible(viewOrderButton, false);
        setVisible(viewPositionsButton, false);

        setPrimaryActionButtonState({
          enabled: ['completed', 'ready_for_transfer', 'transferring', 'transferred', 'failed'].includes(status),
          label: 'Poduzmi akciju'
        });

        if (status === 'failed') {
          errorBox.textContent = latestStatusPayload.error_message || 'AI obrada nije uspjela.';
          setVisible(errorBox, true);
          setTransferButtonState({
            enabled: false,
            label: 'Transfer u bazu',
            hint: 'AI obrada nije uspjela. Učitaj novi dokument i pokušaj ponovo.'
          });

          if (/(transfer|baza|pantheon)/i.test(String(latestStatusPayload.processing_step || '')) || /(anConsigneeQId|SetSubj|Pantheon)/i.test(String(latestStatusPayload.error_message || ''))) {
            showTransferErrorModal(latestStatusPayload.error_message || 'Transfer u bazu nije uspio.', latestStatusPayload.error_message || '');
          }

          lastRenderedStatus = status;
          return;
        }

        if (status === 'completed') {
          setTransferButtonState({
            enabled: Boolean(latestStatusPayload.transfer_ready),
            label: 'Transfer u bazu',
            hint: latestStatusPayload.transfer_ready
              ? 'Rezultat je spreman. Nakon pregleda podataka može se pokrenuti upis u bazu.'
              : 'Transfer ostaje zaključan dok se svi obavezni podaci ne pripreme.'
          });

          if (!autoTransfer) {
            if (latestStatusPayload.transfer_ready && latestStatusPayload.transfer_preview_error) {
              setProgressWarningMessage('Priprema provjere za bazu nije uspjela, ali se i dalje može pokušati ručni transfer.');
            } else if (latestStatusPayload.transfer_ready && latestStatusPayload.transfer_preview_available) {
              setProgressWarningMessage('AI obrada je završena. Narudžba je spremna za provjeru i upis u bazu.', {
                previewReady: true
              });
            } else if (latestStatusPayload.transfer_ready) {
              setProgressWarningMessage('AI obrada je završena. Narudžba je spremna za provjeru i upis u bazu.');
            } else {
              setProgressWarningMessage('AI obrada je završena. Narudžba je spremna za provjeru i dopunu podataka.');
            }
          }
        } else if (status === 'ready_for_transfer' || status === 'transferring') {
          setTransferButtonState({
            enabled: false,
            busy: true,
            label: autoTransfer ? 'Auto transfer radi...' : 'Transfer u toku...',
            hint: autoTransfer
              ? 'AI trenutno samostalno šalje narudžbu prema bazi.'
              : 'Narudžba se upravo upisuje u bazu.'
          });
        } else if (status === 'transferred') {
          const orderView = latestStatusPayload.pantheon_order && latestStatusPayload.pantheon_order.view
            ? latestStatusPayload.pantheon_order.view
            : latestStatusPayload.pantheon_order && latestStatusPayload.pantheon_order.key
              ? latestStatusPayload.pantheon_order.key
              : '';

          successBox.textContent = orderView
            ? `Narudžba je uspješno spremljena u bazu kao ${orderView}.`
            : 'Narudžba je uspješno spremljena u bazu.';
          setVisible(successBox, true);
          renderSavedPreview(latestStatusPayload);
          setVisible(viewOrderButton, true);
          setVisible(viewPositionsButton, true);
          setTransferButtonState({
            enabled: false,
            label: 'Prebačeno u bazu',
            hint: 'Narudžba je spremljena. Pregled i pozicije mogu se otvoriti ili se može započeti nova narudžba.',
            complete: true
          });
        } else {
          setTransferButtonState({
            enabled: false,
            label: 'Transfer u bazu',
            hint: 'Dokument se čita i priprema se pregled narudžbe.'
          });
          setPrimaryActionButtonState({
            enabled: false,
            label: 'Poduzmi akciju'
          });
        }

        const completionStatuses = ['completed', 'ready_for_transfer', 'transferring', 'transferred'];
        const shouldAutoScroll = !openedFromExistingScan
          && !hasAutoScrolledToResult
          && completionStatuses.includes(status)
          && !completionStatuses.includes(previousStatus);

        if (shouldAutoScroll) {
          hasAutoScrolledToResult = true;
          window.setTimeout(scrollToResultSection, 220);
        }

        lastRenderedStatus = status;
      }

      function resetRenderStateCaches() {
        lastFactsSignature = '';
        lastLinesSignature = '';
        lastWarningsSignature = '';
        lastExtractLiveSignature = '';
        lastProgressPercent = null;
        lastProgressLabel = '';
        extractSimulationStartedAt = null;
        extractSimulationPageCount = 1;
        extractSimulationStatus = '';
        stageFillState.upload = null;
        stageFillState.extract = null;
        stageFillState.transfer = null;

        if (progressAnimationFrame) {
          window.cancelAnimationFrame(progressAnimationFrame);
          progressAnimationFrame = null;
        }

        pendingProgressState = null;
      }

      function setProgress(percent, label) {
        const safePercent = Math.max(0, Math.min(100, Math.round(percent)));
        const nextLabel = typeof label === 'string' ? label : null;

        pendingProgressState = {
          percent: safePercent,
          label: nextLabel,
        };

        if (progressAnimationFrame) {
          return;
        }

        progressAnimationFrame = window.requestAnimationFrame(function () {
          const nextState = pendingProgressState;

          progressAnimationFrame = null;
          pendingProgressState = null;

          if (!nextState) {
            return;
          }

          if (nextState.percent !== lastProgressPercent) {
            progressPercent.textContent = nextState.percent + '%';
            progressBar.style.transform = `scaleX(${nextState.percent / 100})`;
            lastProgressPercent = nextState.percent;
          }

          if (nextState.label !== null && nextState.label !== lastProgressLabel) {
            progressLabel.textContent = nextState.label;
            lastProgressLabel = nextState.label;
          }
        });
      }

      function setStageFill(stageName, percent) {
        const fillNode = stageFillNodes[stageName];
        const normalizedPercent = Math.round(Math.max(0, Math.min(100, percent)) * 10) / 10;

        if (!fillNode) {
          return;
        }

        if (stageFillState[stageName] === normalizedPercent) {
          return;
        }

        stageFillState[stageName] = normalizedPercent;
        fillNode.style.transform = `scaleX(${normalizedPercent / 100})`;
      }

      function resolveOverallProgress(data) {
        const status = String(data && data.status || '').trim();
        const uploadPhasePercent = 100 / OVERALL_PHASE_COUNT;
        const uploadOverall = Math.max(
          0,
          Math.min(
            uploadPhasePercent,
            Math.round(
              Math.max(
                mapUploadProgressToOverall(uploadProgress),
                toFiniteNumber(data && data.current_progress, mapUploadProgressToOverall(uploadProgress))
              )
            )
          )
        );

        if (!['uploaded', 'extracting', 'completed', 'ready_for_transfer', 'transferring', 'transferred', 'failed'].includes(status)) {
          return uploadOverall;
        }

        const extractModel = buildExtractPhaseModel(data || {});
        const completedOverallPhaseCount = extractModel.status === 'done'
          ? OVERALL_PHASE_COUNT
          : Math.max(1, Math.min(OVERALL_PHASE_COUNT - 1, 1 + extractModel.currentStepIndex));
        const extractOverall = Math.round((completedOverallPhaseCount / OVERALL_PHASE_COUNT) * 100);

        return Math.max(uploadOverall, Math.min(100, extractOverall));
      }

      function resolveExtractProgressRatio(data) {
        const status = String(data && data.status || '').trim();

        if (['completed', 'ready_for_transfer', 'transferring', 'transferred'].includes(status)) {
          return 1;
        }

        const numericProgress = Math.max(
          toFiniteNumber(data && data.current_progress, 0),
          toFiniteNumber(extractVisualProgress, 0)
        );

        return Math.max(0, Math.min(1, (numericProgress - 18) / 82));
      }

      function buildExtractPhaseModel(data) {
        const status = String(data && data.status || '').trim();
        const totalSteps = EXTRACTION_STEPS.length;
        const totalPages = Math.max(1, resolvePageCount(data) || 1);
        const terminalStatuses = ['completed', 'ready_for_transfer', 'transferring', 'transferred'];
        const isTerminal = terminalStatuses.includes(status);
        const backendProgress = data && data.extraction_progress && typeof data.extraction_progress === 'object'
          ? data.extraction_progress
          : null;
        let currentStepIndex = 0;
        let processedPages = 0;
        let currentPage = 1;
        let progressPercent = 0;
        let phaseProgressPercent = 0;
        let stateStatus = status === 'failed' ? 'error' : (isTerminal ? 'done' : 'active');

        if (backendProgress) {
          currentStepIndex = Math.max(0, Math.min(totalSteps - 1, Math.round(toFiniteNumber(backendProgress.currentStepIndex, 0))));
          processedPages = Math.max(0, Math.min(totalPages, Math.round(toFiniteNumber(backendProgress.processedPages, 0))));
          currentPage = Math.max(1, Math.min(totalPages, Math.round(toFiniteNumber(backendProgress.currentPage, processedPages + 1))));
          stateStatus = String(backendProgress.status || stateStatus).trim() || stateStatus;
          const rawOverallProgressPercent = Math.max(0, Math.min(100, toFiniteNumber(backendProgress.progressPercent, 0)));
          const hasBackendPageSignal = backendProgress.processedPages !== undefined || backendProgress.currentPage !== undefined;
          const pageProgressUnits = Math.max(
            processedPages,
            stateStatus === 'active' && currentPage > processedPages
              ? Math.min(totalPages, Math.max(0, currentPage - 1) + 0.35)
              : processedPages
          );
          const phaseProgressFromPages = totalPages > 0
            ? (pageProgressUnits / totalPages) * 100
            : 0;
          const phaseProgressFromOverall = (((rawOverallProgressPercent / 100) * totalSteps) - currentStepIndex) * 100;

          phaseProgressPercent = hasBackendPageSignal
            ? phaseProgressFromPages
            : phaseProgressFromOverall;

          if (isTerminal) {
            phaseProgressPercent = 100;
          } else if (stateStatus === 'error') {
            phaseProgressPercent = Math.max(0, Math.min(99, phaseProgressPercent));
          } else {
            phaseProgressPercent = Math.max(6, Math.min(100, phaseProgressPercent));
          }

          progressPercent = rawOverallProgressPercent > 0
            ? rawOverallProgressPercent
            : (((currentStepIndex) + (Math.max(0, Math.min(100, phaseProgressPercent)) / 100)) / totalSteps) * 100;
        } else {
          if (status === 'uploaded' || status === 'extracting') {
            if (!extractSimulationStartedAt || extractSimulationPageCount !== totalPages || !['uploaded', 'extracting'].includes(extractSimulationStatus)) {
              extractSimulationStartedAt = parseTimestamp(data && data.started_at) || Date.now();
              extractSimulationPageCount = totalPages;
            }

            extractSimulationStatus = status;

            const totalUnits = totalSteps * totalPages;
            const elapsedMs = Math.max(0, Date.now() - extractSimulationStartedAt);
            const durationMs = Math.max(10000, Math.min(65000, totalUnits * 420));
            const simulatedRatio = Math.min(0.985, elapsedMs / durationMs);
            const backendRatio = Math.max(0, Math.min(0.96, (toFiniteNumber(data && data.current_progress, 18) - 18) / 82));
            const effectiveRatio = Math.max(backendRatio, simulatedRatio);
            const completedUnitsFloat = Math.min((totalUnits - 0.001), Math.max(0, effectiveRatio * totalUnits));

            currentStepIndex = Math.max(0, Math.min(totalSteps - 1, Math.floor(completedUnitsFloat / totalPages)));

            const withinStepUnits = Math.max(0, completedUnitsFloat - (currentStepIndex * totalPages));
            processedPages = Math.max(0, Math.min(totalPages, Math.floor(withinStepUnits)));
            currentPage = Math.max(1, Math.min(totalPages, processedPages + 1));

            const withinStepRatio = Math.max(0.05, Math.min(0.99, withinStepUnits / totalPages));
            progressPercent = (((currentStepIndex) + withinStepRatio) / totalSteps) * 100;
            phaseProgressPercent = withinStepRatio * 100;
          } else if (isTerminal) {
            currentStepIndex = totalSteps - 1;
            processedPages = totalPages;
            currentPage = totalPages;
            progressPercent = 100;
            phaseProgressPercent = 100;
            extractSimulationStatus = status;
          } else if (status === 'failed') {
            currentStepIndex = Math.max(0, Math.min(totalSteps - 1, Math.floor(Math.max(0, toFiniteNumber(extractVisualProgress, 0)) / (100 / totalSteps))));
            processedPages = Math.max(0, Math.min(totalPages, Math.round((toFiniteNumber(extractVisualProgress, 0) / 100) * totalPages)));
            currentPage = Math.max(1, Math.min(totalPages, processedPages + 1));
            progressPercent = Math.max(0, Math.min(99, toFiniteNumber(extractVisualProgress, 0)));
            phaseProgressPercent = Math.max(0, Math.min(99, ((((progressPercent / 100) * totalSteps) - currentStepIndex) * 100)));
            extractSimulationStatus = status;
          } else {
            extractSimulationStartedAt = null;
            extractSimulationPageCount = totalPages;
            extractSimulationStatus = '';
          }
        }

        const currentStepLabel = EXTRACTION_STEPS[currentStepIndex] || EXTRACTION_STEPS[0];
        const pageStates = Array.from({ length: totalPages }).map(function (_, index) {
          const pageNumber = index + 1;
          let chipStatus = 'pending';
          let symbol = '';
          let toneIndex = Math.max(0, currentStepIndex - 1);

          if (stateStatus === 'error' && pageNumber === currentPage) {
            chipStatus = 'error';
            symbol = '!';
            toneIndex = currentStepIndex;
          } else if (isTerminal || pageNumber <= processedPages) {
            chipStatus = 'done';
            symbol = '✓';
            toneIndex = currentStepIndex;
          } else if (pageNumber === currentPage && stateStatus === 'active') {
            chipStatus = 'active';
            symbol = '⟳';
            toneIndex = currentStepIndex;
          } else if (currentStepIndex === 0) {
            toneIndex = 0;
          }

          return {
            pageNumber: pageNumber,
            chipStatus: chipStatus,
            symbol: symbol,
            toneIndex: Math.max(0, Math.min(totalSteps - 1, toneIndex)),
          };
        });

        return {
          currentStepIndex: currentStepIndex,
          totalSteps: totalSteps,
          currentStepLabel: currentStepLabel,
          currentPage: currentPage,
          totalPages: totalPages,
          processedPages: processedPages,
          status: stateStatus,
          progressPercent: Math.max(0, Math.min(100, progressPercent)),
          phaseProgressPercent: Math.max(0, Math.min(100, phaseProgressPercent)),
          pages: pageStates,
        };
      }

      function renderExtractLive(data) {
        if (!extractLive || !extractLiveGrid || !extractLiveMeta) {
          return;
        }

        const status = String(data && data.status || '').trim();
        const visibleStatuses = ['uploaded', 'extracting', 'completed', 'ready_for_transfer', 'transferring', 'transferred', 'failed'];

        if (!visibleStatuses.includes(status)) {
          if (lastExtractLiveSignature !== 'hidden') {
            extractLiveGrid.innerHTML = '';
            if (extractPhaseList) {
              extractPhaseList.innerHTML = '';
            }
            extractLiveMeta.textContent = 'Čekam dokument.';
            if (extractCurrentStep) {
              extractCurrentStep.textContent = 'Priprema dokumenta';
            }
            if (extractLiveProgressBar) {
              extractLiveProgressBar.style.width = '0%';
            }
            extractLive.dataset.phaseIndex = '0';
            lastExtractLiveSignature = 'hidden';
          }
          setVisible(extractLiveShell, false);
          return;
        }

        const model = buildExtractPhaseModel(data);
        const rowSizes = resolveExtractRowSizes(model);
        const phaseStates = EXTRACTION_STEPS.map(function (label, index) {
          let state = 'pending';

          if (model.status === 'done' || index < model.currentStepIndex) {
            state = 'done';
          } else if (index === model.currentStepIndex) {
            state = model.status === 'error' ? 'error' : 'active';
          }

          return {
            index: index,
            label: label,
            state: state,
          };
        });
        const renderSignature = JSON.stringify({
          model: model,
          rowSizes: rowSizes,
          phaseStates: phaseStates,
        });

        if (renderSignature === lastExtractLiveSignature) {
          setVisible(extractLiveShell, true);
          return;
        }

        lastExtractLiveSignature = renderSignature;
        extractLive.dataset.phaseIndex = String(model.currentStepIndex);

        if (extractCurrentStep) {
          extractCurrentStep.textContent = model.currentStepLabel;
        }

        if (extractLiveProgressBar) {
          extractLiveProgressBar.style.width = `${model.phaseProgressPercent}%`;
        }

        extractLiveMeta.textContent = `Korak ${model.currentStepIndex + 1}/${model.totalSteps} - ${model.currentStepLabel} - Stranica ${model.currentPage}/${model.totalPages}`;
        extractLiveGrid.classList.toggle('is-complete', model.status === 'done');
        let pageOffset = 0;
        extractLiveGrid.innerHTML = rowSizes.map(function (rowSize) {
          const rowPages = model.pages.slice(pageOffset, pageOffset + rowSize);

          pageOffset += rowSize;

          return `
            <div class="order-ai-extract-live-row" style="--order-ai-row-columns:${rowPages.length};">
              ${rowPages.map(function (page) {
                const symbol = page.symbol || '.';
                const symbolClass = page.symbol ? '' : ' is-empty';

                return `
                  <span class="order-ai-extract-page-chip is-tone-${page.toneIndex} is-${page.chipStatus}" title="Stranica ${page.pageNumber}">
                    <span class="order-ai-extract-page-chip-state${symbolClass}">${symbol}</span>
                    <span class="order-ai-extract-page-chip-number">${page.pageNumber}</span>
                  </span>
                `;
              }).join('')}
            </div>
          `;
        }).join('');

        if (extractPhaseList) {
          extractPhaseList.innerHTML = phaseStates.map(function (phase) {
            const stateLabel = phase.state === 'done'
              ? '✓'
              : phase.state === 'active'
                ? 'U toku'
                : phase.state === 'error'
                  ? 'Greška'
                  : 'Čeka';

            return `
              <div class="order-ai-extract-phase is-tone-${phase.index} is-${phase.state}">
                <span class="order-ai-extract-phase-name">${escapeHtml(phase.label)}</span>
                <span class="order-ai-extract-phase-state">${escapeHtml(stateLabel)}</span>
              </div>
            `;
          }).join('');
        }

        setVisible(extractLiveShell, true);
      }

      window.addEventListener('resize', function () {
        if (!extractLiveShell || extractLiveShell.classList.contains('order-ai-hidden') || !latestStatusPayload) {
          return;
        }

        renderExtractLive(latestStatusPayload);
      });

      function stopExtractFillAnimation(finalPercent) {
        if (extractFillTimer) {
          clearInterval(extractFillTimer);
          extractFillTimer = null;
        }

        extractVisualProgress = Math.max(0, Math.min(100, finalPercent));
      }

      function startExtractFillAnimation() {
        if (extractFillTimer) {
          return;
        }

        extractFillTimer = window.setInterval(function () {
          if (!latestStatusPayload) {
            return;
          }

          const status = String(latestStatusPayload.status || '').trim();

          if (!['uploaded', 'extracting'].includes(status)) {
            stopExtractFillAnimation(extractVisualProgress);
            return;
          }

          const model = buildExtractPhaseModel(latestStatusPayload);
          const liveProgressLabel = String(latestStatusPayload.processing_step || '').trim() || 'AI obrada je u toku.';
          extractVisualProgress = model.progressPercent;
          setProgress(resolveOverallProgress(latestStatusPayload), liveProgressLabel);
          setStageFill('extract', model.progressPercent);
          renderExtractLive(latestStatusPayload);
        }, 260);
      }

      function updateStageFills(data) {
        const status = String(data && data.status || '').trim();

        setStageFill('upload', uploadProgress);

        if (status === 'uploaded' || status === 'extracting') {
          const model = buildExtractPhaseModel(data || {});

          extractVisualProgress = model.progressPercent;
          setStageFill('extract', model.progressPercent);
          startExtractFillAnimation();
        } else if (status === 'completed' || status === 'ready_for_transfer' || status === 'transferring' || status === 'transferred') {
          stopExtractFillAnimation(100);
          setStageFill('extract', 100);
        } else if (status === 'failed') {
          stopExtractFillAnimation(extractVisualProgress);
          setStageFill('extract', Math.max(0, Math.min(100, extractVisualProgress || 0)));
        } else {
          stopExtractFillAnimation(0);
          setStageFill('extract', 0);
        }

        if (status === 'transferred') {
          setStageFill('transfer', 100);
        } else if (status === 'transferring' || isTransferBusy) {
          setStageFill('transfer', 72);
        } else {
          setStageFill('transfer', 0);
        }
      }

      function showPendingExtractionState(data) {
        const statusData = data && typeof data === 'object' ? data : {};
        const pendingExtractionPayload = Object.assign({}, statusData, {
          status: 'extracting',
          processing_step: 'AI obrada je pokrenuta. Dokument se analizira...'
        });

        latestStatusPayload = pendingExtractionPayload;
        extractSimulationStartedAt = parseTimestamp(statusData.started_at) || Date.now();
        extractSimulationPageCount = Math.max(1, resolvePageCount(statusData) || 1);
        extractSimulationStatus = 'extracting';

        if (statusData.source_file_name) {
          fileNameEl.textContent = statusData.source_file_name;
        }

        syncDropzoneVisibility('extracting');
        setProgress(resolveOverallProgress(pendingExtractionPayload), pendingExtractionPayload.processing_step);
        setStageState('extract', false);
        setStageFill('upload', 100);
        updateStageFills(pendingExtractionPayload);
        updateActivityState({ status: 'extracting' });
        renderExtractLive(pendingExtractionPayload);
        renderElapsedRuntime(pendingExtractionPayload);
        maybeAutoScrollToExtraction('extracting', lastRenderedStatus);
        setTransferButtonState({
          enabled: false,
          label: 'Transfer u bazu',
          hint: 'Dokument se čita i priprema se pregled narudžbe.'
        });
        setPrimaryActionButtonState({
          enabled: false,
          label: 'Poduzmi akciju'
        });
      }

      function renderFacts(payload, statusData) {
        const order = payload.order || {};
        const pantheon = statusData.pantheon_order || {};
        const totalComparison = resolveDocumentTotalComparison(payload);
        const amountMeta = totalComparison.hasComparableValues && !totalComparison.matches
          ? `Razlika: ${formatAmount(totalComparison.difference)}`
          : '';
        const factsMarkup = [
          { label: 'Kupac', value: order.customer_name || '-' },
          { label: 'Naručilac', value: order.supplier_name || '-' },
          { label: 'Referenca', value: order.external_document_number || '-' },
          { label: 'Vrsta dokumenta', value: order.document_type || '-' },
          { label: 'Valuta', value: order.currency || '-' },
          {
            label: 'Iznos',
            value: formatAmount(totalComparison.documentTotal || 0),
            stateClass: totalComparison.hasComparableValues ? (totalComparison.matches ? 'is-match' : 'is-mismatch') : '',
            meta: amountMeta,
          },
          { label: 'AI krediti', value: formatWholeNumber(statusData.billed_tokens || 0) },
          { label: 'Pantheon ključ', value: pantheon.key || '-' },
        ];
        const renderSignature = JSON.stringify(factsMarkup);

        if (renderSignature === lastFactsSignature) {
          return;
        }

        lastFactsSignature = renderSignature;
        facts.innerHTML = factsMarkup.map((fact) => `
          <div class="order-ai-fact ${escapeHtml(fact.stateClass || '')}">
            <div class="text-muted small mb-50">${escapeHtml(fact.label)}</div>
            <div class="fw-bolder">${escapeHtml(fact.value)}</div>
            ${fact.meta ? `<div class="order-ai-fact-meta">${escapeHtml(fact.meta)}</div>` : ''}
          </div>
        `).join('');
      }

      function renderLines(payload) {
        const items = Array.isArray(payload.items) ? payload.items : [];
        const currency = String((payload.order && payload.order.currency) || '').trim();
        const allowLineEdit = canEditLineTotals();

        if (!items.length) {
          if (lastLinesSignature !== 'empty') {
            linesBody.innerHTML = '';
            lastLinesSignature = 'empty';
          }
          setVisible(linesShell, false);
          return;
        }

        const renderSignature = JSON.stringify({
          currency: currency,
          editable: allowLineEdit,
          items: items.map(function (item) {
            const comparison = resolveLineComparison(item);

            return {
              line_number: item.line_number || '',
              product_code: item.product_code || '',
              product_name: item.product_name || '',
              quantity: formatAmount(item.quantity || 0),
              unit: item.unit || '-',
              unit_price: formatAmount(item.unit_price || 0),
              computed: comparison.computed,
              source: comparison.source,
              difference: comparison.difference,
              matches: comparison.matches,
              catalog_item_missing: Boolean(item.catalog_item_missing),
              catalog_item_created: Boolean(item.catalog_item_created),
              catalog_item_status: item.catalog_item_status || '',
              catalog_item_notice: item.catalog_item_notice || '',
              primary_classification: item.primary_classification || '',
            };
          }),
        });

        if (renderSignature === lastLinesSignature) {
          setVisible(linesShell, true);
          return;
        }

        lastLinesSignature = renderSignature;
        linesBody.innerHTML = items.map((item, index) => {
          const comparison = resolveLineComparison(item);
          const buttonClasses = comparison.matches ? 'is-match' : 'is-mismatch';
          const readOnlyClass = allowLineEdit ? '' : ' is-readonly';
          const diffPrefix = comparison.difference > 0 ? '+' : '';
          const catalogStatus = String(item.catalog_item_status || '').trim();
          const isCatalogMissing = Boolean(item.catalog_item_missing) || catalogStatus === 'missing';
          const isCatalogCreated = Boolean(item.catalog_item_created) || catalogStatus === 'created';
          const rowClass = isCatalogMissing
            ? ' class="order-ai-line-row is-catalog-missing"'
            : isCatalogCreated
              ? ' class="order-ai-line-row is-catalog-created"'
              : '';
          const catalogBadge = isCatalogMissing
            ? '<span class="order-ai-line-badge is-missing">Nije u bazi</span>'
            : isCatalogCreated
              ? '<span class="order-ai-line-badge is-created">Kreiran</span>'
              : '';
          const catalogMeta = [];

          if (item.catalog_item_notice) {
            catalogMeta.push(`<div class="order-ai-line-note">${escapeHtml(item.catalog_item_notice)}</div>`);
          }

          if (isCatalogMissing && item.primary_classification) {
            catalogMeta.push(`<div class="order-ai-line-note">Primarna klasifikacija: ${escapeHtml(item.primary_classification)}</div>`);
          }

          return `
            <tr${rowClass}>
              <td>${escapeHtml(item.line_number || '')}</td>
              <td>
                <div class="order-ai-line-code-stack">
                  <span>${escapeHtml(item.product_code || '-')}</span>
                  ${catalogBadge}
                </div>
              </td>
              <td class="order-ai-wrap">
                <div class="order-ai-line-name">${escapeHtml(item.product_name || '-')}</div>
                ${catalogMeta.join('')}
              </td>
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
                    <span class="order-ai-line-total-source">Skenirani total: ${escapeHtml(formatAmountWithCurrency(comparison.source, currency))}</span>
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
        const renderSignature = JSON.stringify(validWarnings);

        if (renderSignature === lastWarningsSignature && (validWarnings.length === 0 || warningsBox.innerHTML !== '')) {
          setVisible(warningsBox, validWarnings.length > 0);
          return;
        }

        lastWarningsSignature = renderSignature;
        warningsBox.innerHTML = validWarnings.map((warning) => `<div>${escapeHtml(warning)}</div>`).join('');
        setVisible(warningsBox, validWarnings.length > 0);
      }

      function syncSourcePdfButton(statusData, visible) {
        if (!viewPdfButton) {
          return;
        }

        const href = statusData && typeof statusData.source_document_view_url === 'string'
          ? statusData.source_document_view_url.trim()
          : '';

        viewPdfButton.href = href || '#';
        viewPdfButton.setAttribute('aria-disabled', href ? 'false' : 'true');
        viewPdfButton.tabIndex = href ? 0 : -1;
        setVisible(viewPdfButton, Boolean(visible) && href !== '');
      }

      function resetInterface() {
        stopPolling();
        stopExtractFillAnimation(0);
        stopElapsedTimer();
        currentScanId = null;
        uploadProgress = 0;
        latestStatusPayload = null;
        isTransferBusy = false;
        activeLineTotalIndex = null;
        hasAutoScrolledToExtraction = false;
        hasAutoScrolledToResult = false;
        lastRenderedStatus = '';
        resetRenderStateCaches();
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
        if (extractLiveGrid) {
          extractLiveGrid.innerHTML = '';
        }
        if (extractPhaseList) {
          extractPhaseList.innerHTML = '';
        }
        if (extractLiveMeta) {
          extractLiveMeta.textContent = 'Čekam dokument.';
        }
        if (extractLive) {
          extractLive.dataset.phaseIndex = '0';
        }
        resultCaption.textContent = 'Nema obrađenog dokumenta.';
        resultStatus.textContent = 'Spremno';
        setVisible(resultCard, false);
        setVisible(linesShell, false);
        setVisible(savedPreview, false);
        setVisible(actions, false);
        syncSourcePdfButton(null, false);
        setVisible(viewOrderButton, false);
        setVisible(viewPositionsButton, false);
        setVisible(extractLiveShell, false);
        resetMessages();
        setPositionsModalState({
          loading: false,
          showContent: false,
          html: '',
          error: '',
          subtitle: 'Pregled pozicija upisanih u bazu'
        });
        setProgress(0, 'Čekam upload...');
        setStageState('upload', false);
        setStageFill('upload', 0);
        setStageFill('transfer', 0);
        updateActivityState(null);
        renderElapsedRuntime(null);
        setTransferButtonState({
          enabled: false,
          label: 'Transfer u bazu',
          hint: 'Akcije su na dnu stranice. Nakon završetka obrade omogućava se upis u bazu.'
        });
        setPrimaryActionButtonState({
          enabled: false,
          label: 'Poduzmi akciju'
        });
        syncDropzoneVisibility('');
      }

      function handleUpload(file) {
        if (!file) {
          return;
        }

        stopPolling();
        stopElapsedTimer();
        closeSavedOrderModal();
        closePositionsModal();
        closeLineTotalModal();
        resetMessages();
        currentScanId = null;
        latestStatusPayload = null;
        uploadProgress = 0;
        isTransferBusy = false;
        openedFromExistingScan = false;
        hasAutoScrolledToExtraction = false;
        hasAutoScrolledToResult = false;
        lastRenderedStatus = '';
        resetRenderStateCaches();
        fileNameEl.textContent = file.name;
        setVisible(progressCard, true);
        setVisible(resultCard, false);
        setVisible(actions, false);
        setVisible(savedPreview, false);
        setVisible(viewOrderButton, false);
        setVisible(viewPositionsButton, false);
        setVisible(extractLiveShell, false);
        syncDropzoneVisibility('uploading');
        facts.innerHTML = '';
        linesBody.innerHTML = '';
        savedPreview.innerHTML = '';
        orderModalBody.innerHTML = '';
        if (positionsModalContent) {
          positionsModalContent.innerHTML = '';
        }
        if (extractLiveGrid) {
          extractLiveGrid.innerHTML = '';
        }
        if (extractPhaseList) {
          extractPhaseList.innerHTML = '';
        }
        if (extractLiveMeta) {
          extractLiveMeta.textContent = 'Čekam dokument.';
        }
        if (extractLive) {
          extractLive.dataset.phaseIndex = '0';
        }
        setVisible(linesShell, false);
        setPositionsModalState({
          loading: false,
          showContent: false,
          html: '',
          error: '',
          subtitle: 'Pregled pozicija upisanih u bazu'
        });
        renderElapsedRuntime(null);
        setProgress(0, 'Priprema lokalnog prihvata fajla...');
        setStageState('upload', false);
        setStageFill('upload', 0);
        setStageFill('transfer', 0);
        stopExtractFillAnimation(0);
        updateActivityState(null);
        setTransferButtonState({
          enabled: false,
          label: 'Transfer u bazu',
          hint: 'Dokument se priprema za AI ekstrakciju.'
        });
        setPrimaryActionButtonState({
          enabled: false,
          label: 'Poduzmi akciju'
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
          setProgress(
            mapUploadProgressToOverall(uploadProgress),
            uploadProgress >= 100 ? 'Upload je završen. Pokreće se AI obrada...' : 'Dokument se učitava na server...'
          );
          setStageState('upload', uploadProgress >= 100);
          setStageFill('upload', uploadProgress);
        });

        xhr.addEventListener('load', function () {
          if (xhr.status < 200 || xhr.status >= 300) {
            const response = xhr.response || {};
            setProgressWarningMessage(response.message || 'Upload nije uspio.');
            updateActivityState(null);
            return;
          }

          const response = xhr.response || {};
          currentScanId = response.scan_id;
          uploadProgress = 100;
          showPendingExtractionState(response.data || {
            source_file_name: file.name,
            page_count: 1,
            current_progress: 18,
          });
          startPolling(response.scan_id);
        });

        xhr.addEventListener('error', function () {
          setProgressWarningMessage('Greška pri uploadu dokumenta.');
          updateActivityState(null);
        });

        xhr.send(formData);
      }

      function renderStatus(data) {
        const previousStatus = lastRenderedStatus;
        latestStatusPayload = data || {};
        const payload = latestStatusPayload.result || {};
        const autoTransfer = Boolean(latestStatusPayload.auto_transfer);
        const status = String(latestStatusPayload.status || '').trim();
        const order = payload.order || {};
        const hasItems = Array.isArray(payload.items) && payload.items.length > 0;
        const hasOrderFacts = ['customer_name', 'supplier_name', 'external_document_number', 'document_type', 'currency'].some(function (key) {
          return String(order && order[key] || '').trim() !== '';
        });
        const showResultCard = hasItems
          || hasOrderFacts
          || ['completed', 'ready_for_transfer', 'transferring', 'transferred', 'failed'].includes(status);
        const stageName = detectStage(status, autoTransfer, latestStatusPayload.current_progress, latestStatusPayload.processing_step);
        const finalizeStage = (status === 'completed' && stageName === 'extract' && !autoTransfer)
          || (status === 'transferred' && stageName === 'transfer');

        syncDropzoneVisibility(status);
        setVisible(resultCard, showResultCard);
        setVisible(actions, showResultCard);

        if (progressWarning) {
          progressWarning.textContent = '';
          progressWarning.classList.remove('is-preview-ready');
          setVisible(progressWarning, false);
        }

        if (errorBox) {
          errorBox.textContent = '';
          setVisible(errorBox, false);
        }

        if (successBox) {
          successBox.textContent = '';
          setVisible(successBox, false);
        }

        if (latestStatusPayload.source_file_name) {
          fileNameEl.textContent = latestStatusPayload.source_file_name;
        }

        setProgress(resolveOverallProgress(latestStatusPayload), latestStatusPayload.processing_step || 'AI obrada je u toku.');
        setStageState(stageName, finalizeStage);
        updateStageFills(latestStatusPayload);
        updateActivityState(latestStatusPayload);
        renderExtractLive(latestStatusPayload);
        renderElapsedRuntime(latestStatusPayload);
        maybeAutoScrollToExtraction(status, previousStatus);

        if (showResultCard) {
          renderFacts(payload, latestStatusPayload);
          renderLines(payload);
        } else {
          setVisible(linesShell, false);
        }

        renderWarnings(latestStatusPayload.warnings || []);

        resultCaption.textContent = latestStatusPayload.processing_step || 'Status nije dostupan.';
        resultStatus.textContent = resolveStatusLabel(status);
        setVisible(savedPreview, false);
        syncSourcePdfButton(latestStatusPayload, showResultCard);
        setVisible(viewOrderButton, false);
        setVisible(viewPositionsButton, false);

        setPrimaryActionButtonState({
          enabled: ['completed', 'ready_for_transfer', 'transferring', 'transferred', 'failed'].includes(status),
          label: 'Poduzmi akciju'
        });

        if (status === 'failed') {
          errorBox.textContent = latestStatusPayload.error_message || 'AI obrada nije uspjela.';
          setVisible(errorBox, true);
          setTransferButtonState({
            enabled: false,
            label: 'Transfer u bazu',
            hint: 'AI obrada nije uspjela. Učitaj novi dokument i pokušaj ponovo.'
          });

          if (/(transfer|baza|pantheon)/i.test(String(latestStatusPayload.processing_step || '')) || /(anConsigneeQId|SetSubj|Pantheon)/i.test(String(latestStatusPayload.error_message || ''))) {
            showTransferErrorModal(latestStatusPayload.error_message || 'Transfer u bazu nije uspio.', latestStatusPayload.error_message || '');
          }

          lastRenderedStatus = status;
          return;
        }

        if (status === 'completed') {
          setTransferButtonState({
            enabled: Boolean(latestStatusPayload.transfer_ready),
            label: 'Transfer u bazu',
            hint: latestStatusPayload.transfer_ready
              ? 'Rezultat je spreman. Nakon pregleda podataka može se pokrenuti upis u bazu.'
              : 'Transfer ostaje zaključan dok se svi obavezni podaci ne pripreme.'
          });

          if (!autoTransfer) {
            if (latestStatusPayload.transfer_ready && latestStatusPayload.transfer_preview_error) {
              setProgressWarningMessage('Priprema provjere za bazu nije uspjela, ali se i dalje može pokušati ručni transfer.');
            } else if (latestStatusPayload.transfer_ready && latestStatusPayload.transfer_preview_available) {
              setProgressWarningMessage('Provjera za bazu je spremna. Nakon pregleda rezultata može se pokrenuti transfer.', {
                previewReady: true
              });
            } else if (latestStatusPayload.transfer_ready) {
              setProgressWarningMessage('Rezultat je spreman za upis u bazu. Pokreni transfer kada budeš spreman.');
            } else {
              setProgressWarningMessage('Ekstrakcija je završena. Pregledaj rezultat i dopuni podatke ako nešto nedostaje.');
            }
          }
        } else if (status === 'ready_for_transfer' || status === 'transferring') {
          setTransferButtonState({
            enabled: false,
            busy: true,
            label: autoTransfer ? 'Auto transfer radi...' : 'Transfer u toku...',
            hint: autoTransfer
              ? 'AI trenutno samostalno šalje narudžbu prema bazi.'
              : 'Narudžba se upravo upisuje u bazu.'
          });
        } else if (status === 'transferred') {
          const orderView = latestStatusPayload.pantheon_order && latestStatusPayload.pantheon_order.view
            ? latestStatusPayload.pantheon_order.view
            : latestStatusPayload.pantheon_order && latestStatusPayload.pantheon_order.key
              ? latestStatusPayload.pantheon_order.key
              : '';

          successBox.textContent = orderView
            ? `Narudžba je uspješno spremljena u bazu kao ${orderView}.`
            : 'Narudžba je uspješno spremljena u bazu.';
          setVisible(successBox, true);
          renderSavedPreview(latestStatusPayload);
          setVisible(viewOrderButton, true);
          setVisible(viewPositionsButton, true);
          setTransferButtonState({
            enabled: false,
            label: 'Prebačeno u bazu',
            hint: 'Narudžba je spremljena. Pregled i pozicije mogu se otvoriti ili se može započeti nova narudžba.',
            complete: true
          });
        } else {
          setTransferButtonState({
            enabled: false,
            label: 'Transfer u bazu',
            hint: 'Dokument se čita i priprema se pregled narudžbe.'
          });
          setPrimaryActionButtonState({
            enabled: false,
            label: 'Poduzmi akciju'
          });
        }

        const completionStatuses = ['completed', 'ready_for_transfer', 'transferring', 'transferred'];
        const shouldAutoScroll = !openedFromExistingScan
          && !hasAutoScrolledToResult
          && completionStatuses.includes(status)
          && !completionStatuses.includes(previousStatus);

        if (shouldAutoScroll) {
          hasAutoScrolledToResult = true;
          window.setTimeout(scrollToActionSection, 220);
        }

        lastRenderedStatus = status;
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
      if (primaryActionButton) {
        primaryActionButton.addEventListener('click', scrollToActionSection);
      }
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
        syncDropzoneVisibility(initialScanState && typeof initialScanState === 'object' ? initialScanState.status : '');

        if (initialScanState && typeof initialScanState === 'object') {
          if (initialScanState.source_file_name) {
            fileNameEl.textContent = initialScanState.source_file_name;
          }

          renderStatus(initialScanState);
          setInitializingState(false);

          if (openedFromHistory) {
            showLoadedScanToast(initialScanState.source_file_name || '');
          }

          if (!['completed', 'transferred', 'failed'].includes(String(initialScanState.status || ''))) {
            startPolling(initialScanId);
          }
        } else {
          startPolling(initialScanId);
        }
      } else {
        setInitializingState(false);
      }

      syncFeatherIcons();
    })();
</script>
@endsection
