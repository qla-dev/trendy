@php
  $workOrderPreviewPathPattern = route('app-invoice-preview', ['id' => '__WORK_ORDER_ID__'], false);
  $workOrderScanLookupUrl = route('app-invoice-scan-lookup');
  $workOrderScanCreateUrl = route('app-invoice-scan-create');
@endphp

<!-- QR Scanner Modal for Work Order -->
<div class="modal fade" id="qr-scanner-modal" tabindex="-1" aria-labelledby="qr-scanner-modal-label" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="background: transparent; border: none;">
      <div class="modal-body p-0 text-center">
        <h4 class="text-white mb-2" id="qr-scanner-modal-label">Skeniraj QR code radnog naloga</h4>

        <div class="qr-scanner-container position-relative" style="max-width: 400px; margin: 0 auto;">
          <div id="qr-scanner-frame" class="qr-scanner-frame position-relative" style="width: 100%; padding-top: 100%; background: rgba(255, 255, 255, 0.1); border: 2px solid var(--bs-success, #28c76f); border-radius: 12px; overflow: hidden;">
            <div id="qr-scanner-region" class="position-absolute" style="inset: 0;"></div>

            <div class="qr-corner qr-corner-top-left" style="position: absolute; top: 0; left: 0; width: 40px; height: 40px; border-top: 3px solid var(--bs-success, #28c76f); border-left: 3px solid var(--bs-success, #28c76f);"></div>
            <div class="qr-corner qr-corner-top-right" style="position: absolute; top: 0; right: 0; width: 40px; height: 40px; border-top: 3px solid var(--bs-success, #28c76f); border-right: 3px solid var(--bs-success, #28c76f);"></div>
            <div class="qr-corner qr-corner-bottom-left" style="position: absolute; bottom: 0; left: 0; width: 40px; height: 40px; border-bottom: 3px solid var(--bs-success, #28c76f); border-left: 3px solid var(--bs-success, #28c76f);"></div>
            <div class="qr-corner qr-corner-bottom-right" style="position: absolute; bottom: 0; right: 0; width: 40px; height: 40px; border-bottom: 3px solid var(--bs-success, #28c76f); border-right: 3px solid var(--bs-success, #28c76f);"></div>

            <div class="qr-scan-line" style="position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, transparent, var(--bs-success, #28c76f), transparent); animation: scanLineNalog 2s linear infinite;"></div>

            <div class="qr-grid" style="position: absolute; inset: 0; background-image:
              linear-gradient(rgba(40, 199, 111, 0.1) 1px, transparent 1px),
              linear-gradient(90deg, rgba(40, 199, 111, 0.1) 1px, transparent 1px);
              background-size: 20px 20px; opacity: 0.3;"></div>
          </div>
        </div>

        <div class="wo-qr-controls-wrap" style="max-width: 400px; margin: 0 auto;">
          <div class="wo-qr-controls-panel mt-2 mb-1">
            <div class="wo-qr-controls-row">
              <div class="wo-qr-control-block">
                <span class="wo-qr-control-kicker">Prikaz</span>
                <div class="form-check form-switch mb-0 d-flex align-items-center">
                  <input class="form-check-input me-50" type="checkbox" id="qr-mirror-toggle">
                  <label class="form-check-label mb-0" for="qr-mirror-toggle">Mirror</label>
                </div>
              </div>
              <button type="button" class="btn btn-sm wo-qr-btn wo-qr-btn-subtle" id="qr-scanner-restart-btn">
                <i class="fa fa-refresh me-50"></i> Ponovo pokreni
              </button>
            </div>

            <div class="wo-qr-controls-row wo-qr-camera-controls-row">
              <span class="wo-qr-camera-icon" aria-hidden="true"><i class="fa fa-camera"></i></span>
              <label class="visually-hidden" for="qr-camera-select">Kamera</label>
              <div class="wo-qr-camera-row">
                <select class="form-select form-select-sm" id="qr-camera-select">
                  <option value="">Automatski odabir</option>
                </select>
                <button type="button" class="btn btn-sm wo-qr-btn wo-qr-btn-primary" id="qr-camera-apply-btn">Primijeni</button>
              </div>
            </div>
          </div>

          <div class="wo-qr-feedback-wrap">
            <div id="qr-scanner-status" class="small wo-qr-status">Dozvoli pristup kameri da skeniranje zapocne.</div>
            <div id="qr-scanner-error" class="small wo-qr-error text-danger d-none"></div>
          </div>
        </div>

        <button type="button" class="btn btn-secondary wo-scanner-close-fab" data-bs-dismiss="modal" aria-label="Zatvori">
          <i class="fa fa-times me-50"></i> Zatvori
        </button>
      </div>
    </div>
  </div>
</div>

<style>
  /* Stronger backdrop for work order scanner */
  .qr-scanner-backdrop {
    background-color: rgba(0, 0, 0, 0.992) !important;
    opacity: 1 !important;
    backdrop-filter: blur(3px);
  }

  #qr-scanner-modal {
    --qr-accent: rgba(74, 179, 148, 0.78);
    --qr-accent-soft: rgba(74, 179, 148, 0.24);
    --qr-border-muted: rgba(189, 199, 221, 0.32);
    --qr-surface-dark: rgba(18, 22, 33, 0.82);
    --qr-text-soft: #cfd7ee;
    --qr-text-main: #e8edf9;
    --wo-qr-control-height: 2.55rem;
  }

  #qr-scanner-modal .qr-scanner-frame {
    border: 1px solid var(--qr-accent-soft) !important;
    background: rgba(255, 255, 255, 0.045) !important;
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.03);
  }

  @keyframes scanLineNalog {
    0% {
      top: 0;
      opacity: 1;
    }
    50% {
      opacity: 0.8;
    }
    100% {
      top: 100%;
      opacity: 0;
    }
  }

  @keyframes cornerPulseNalog {
    0%, 100% {
      opacity: 1;
      transform: scale(1);
    }
    50% {
      opacity: 0.7;
      transform: scale(1.1);
    }
  }

  #qr-scanner-modal .qr-corner {
    width: 34px !important;
    height: 34px !important;
    border-color: var(--qr-accent) !important;
    animation: cornerPulseNalog 2s ease-in-out infinite;
    opacity: 0.85;
  }

  #qr-scanner-modal .qr-corner-top-right {
    border-top-width: 2px !important;
    border-right-width: 2px !important;
    animation-delay: 0.5s;
  }

  #qr-scanner-modal .qr-corner-bottom-left {
    border-bottom-width: 2px !important;
    border-left-width: 2px !important;
    animation-delay: 1s;
  }

  #qr-scanner-modal .qr-corner-bottom-right {
    border-bottom-width: 2px !important;
    border-right-width: 2px !important;
    animation-delay: 1.5s;
  }

  #qr-scanner-modal .qr-corner-top-left {
    border-top-width: 2px !important;
    border-left-width: 2px !important;
  }

  #qr-scanner-modal .qr-scan-line {
    height: 1.5px !important;
    background: linear-gradient(90deg, transparent, var(--qr-accent), transparent) !important;
    opacity: 0.7;
  }

  @keyframes gridMoveNalog {
    0% {
      background-position: 0 0;
    }
    100% {
      background-position: 20px 20px;
    }
  }

  #qr-scanner-modal .qr-grid {
    background-image:
      linear-gradient(rgba(74, 179, 148, 0.08) 1px, transparent 1px),
      linear-gradient(90deg, rgba(74, 179, 148, 0.08) 1px, transparent 1px) !important;
    opacity: 0.22 !important;
    animation: gridMoveNalog 3s linear infinite;
  }

  #qr-scanner-modal .modal-dialog {
    background: transparent;
  }

  #qr-scanner-modal .modal-content {
    background: transparent;
    box-shadow: none;
    border: none;
  }

  #qr-scanner-modal .form-label {
    color: var(--qr-text-soft) !important;
  }

  #qr-scanner-modal .wo-qr-controls-wrap {
    text-align: left;
  }

  #qr-scanner-modal .wo-qr-controls-panel {
    border: 1px solid rgba(170, 183, 212, 0.24);
    border-radius: 10px;
    background: rgba(14, 19, 30, 0.68);
    padding: 0.72rem;
  }

  #qr-scanner-modal .wo-qr-controls-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
  }

  #qr-scanner-modal .wo-qr-controls-row + .wo-qr-controls-row {
    margin-top: 0.7rem;
    padding-top: 0.7rem;
    border-top: 1px solid rgba(170, 183, 212, 0.18);
  }

  #qr-scanner-modal .wo-qr-camera-controls-row {
    align-items: center;
  }

  #qr-scanner-modal .wo-qr-control-block {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.36rem;
  }

  #qr-scanner-modal .wo-qr-control-kicker {
    font-size: 0.72rem;
    line-height: 1;
    text-transform: uppercase;
    letter-spacing: 0.045em;
    color: #98a8ce;
    font-weight: 600;
  }

  #qr-scanner-modal .form-check-label {
    color: var(--qr-text-soft) !important;
    font-size: 0.95rem;
    font-weight: 500;
    cursor: pointer;
  }

  #qr-scanner-modal .form-check-input {
    width: 2.35em;
    height: 1.3em;
    margin-top: 0;
    background-color: rgba(255, 255, 255, 0.11);
    border-color: rgba(168, 179, 204, 0.38);
    box-shadow: none !important;
    cursor: pointer;
  }

  #qr-scanner-modal .form-check-input:focus {
    border-color: rgba(74, 179, 148, 0.58);
    box-shadow: 0 0 0 0.12rem rgba(74, 179, 148, 0.16) !important;
  }

  #qr-scanner-modal .form-check-input:checked {
    background-color: rgba(74, 179, 148, 0.35);
    border-color: rgba(74, 179, 148, 0.66);
  }

  #qr-scanner-modal .wo-qr-camera-row {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    flex: 1 1 auto;
  }

  #qr-scanner-modal .wo-qr-camera-icon {
    width: var(--wo-qr-control-height);
    height: var(--wo-qr-control-height);
    border-radius: 8px;
    border: 1px solid rgba(177, 189, 216, 0.26);
    background: rgba(255, 255, 255, 0.035);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #aeb9d8;
    flex: 0 0 auto;
  }

  #qr-scanner-modal .wo-qr-camera-row .form-select {
    flex: 1 1 auto;
    height: var(--wo-qr-control-height);
    min-height: var(--wo-qr-control-height);
  }

  #qr-scanner-modal .form-select {
    height: var(--wo-qr-control-height);
    min-height: var(--wo-qr-control-height);
    background-color: rgba(16, 22, 35, 0.85);
    color: var(--qr-text-main);
    border-color: rgba(168, 179, 204, 0.34);
    font-weight: 500;
    box-shadow: none !important;
    cursor: pointer;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23aeb8d5' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
  }

  #qr-scanner-modal .form-select:focus {
    border-color: rgba(74, 179, 148, 0.54);
    box-shadow: 0 0 0 0.12rem rgba(74, 179, 148, 0.13) !important;
  }

  #qr-scanner-modal .form-select option {
    background: #121826;
    color: #e5ebfb;
  }

  #qr-scanner-modal .wo-qr-btn {
    height: var(--wo-qr-control-height);
    min-height: var(--wo-qr-control-height);
    border-radius: 8px;
    padding: 0.42rem 0.85rem;
    font-weight: 600;
    letter-spacing: 0.01em;
    box-shadow: none !important;
  }

  #qr-scanner-modal .wo-qr-btn-subtle {
    border: 1px solid rgba(177, 189, 216, 0.34);
    background-color: rgba(255, 255, 255, 0.035);
    color: #dde5fa;
  }

  #qr-scanner-modal .wo-qr-btn-subtle:hover,
  #qr-scanner-modal .wo-qr-btn-subtle:focus {
    border-color: rgba(205, 215, 238, 0.55);
    background-color: rgba(255, 255, 255, 0.1);
    color: #ffffff;
  }

  #qr-scanner-modal .wo-qr-btn-primary {
    border: 1px solid rgba(74, 179, 148, 0.5);
    background-color: rgba(74, 179, 148, 0.12);
    color: #dbfff2;
    min-width: 7.4rem;
  }

  #qr-scanner-modal .wo-qr-btn-primary:hover,
  #qr-scanner-modal .wo-qr-btn-primary:focus {
    border-color: rgba(74, 179, 148, 0.75);
    background-color: rgba(74, 179, 148, 0.2);
    color: #ffffff;
  }

  #qr-scanner-modal .wo-qr-status {
    padding-left: 0;
    line-height: 1.25rem;
    color: #bfc9e5 !important;
    text-align: center;
    margin: 0;
  }

  #qr-scanner-modal .wo-qr-error {
    margin: 0;
    text-align: center;
    line-height: 1.25rem;
  }

  #qr-scanner-modal .wo-qr-feedback-wrap {
    min-height: 3rem;
    margin: 0.5rem 0 0.75rem;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    gap: 0.2rem;
  }

  #qr-scanner-modal .wo-scanner-close-fab {
    position: fixed;
    top: max(1rem, env(safe-area-inset-top));
    left: max(1rem, env(safe-area-inset-left));
    z-index: 1085;
    border-radius: 10px;
    padding: 0.55rem 0.95rem;
    border: 1px solid rgba(205, 215, 238, 0.42);
    background-color: rgba(10, 14, 22, 0.82);
    color: #eef3ff;
    box-shadow: 0 8px 20px rgba(6, 8, 14, 0.45);
    backdrop-filter: blur(2px);
  }

  #qr-scanner-modal .wo-scanner-close-fab:hover,
  #qr-scanner-modal .wo-scanner-close-fab:focus {
    border-color: rgba(223, 231, 248, 0.7);
    background-color: rgba(18, 24, 37, 0.94);
    color: #ffffff;
  }

  #qr-scanner-modal #qr-scanner-region > div {
    border: 0 !important;
  }

  #qr-scanner-frame.qr-mirror-on video,
  #qr-scanner-frame.qr-mirror-on canvas {
    transform: scaleX(-1);
  }

  .wo-scan-create-rn-swal .wo-scan-create-head {
    margin-bottom: 1rem;
  }

  .wo-scan-create-rn-swal .wo-scan-create-copy {
    font-size: 1rem;
    font-weight: 600;
    color: inherit;
  }

  .wo-scan-create-rn-swal .wo-scan-create-next-number {
    margin-top: 0.35rem;
    font-size: clamp(1.5rem, 4vw, 2.1rem);
    line-height: 1.05;
    font-weight: 700;
    letter-spacing: 0.02em;
  }

  .wo-scan-create-rn-swal .wo-scan-create-doc-card {
    margin: 0 0 1rem;
  }

  .wo-scan-create-rn-swal .wo-scan-create-doc-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.8rem;
    margin-bottom: 0.55rem;
  }

  .wo-scan-create-rn-swal .wo-scan-create-doc-label {
    font-size: 0.74rem;
    line-height: 1;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-weight: 700;
    color: #8f9ab4;
  }

  .wo-scan-create-rn-swal .wo-scan-create-doc-switch {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.32rem;
    border-radius: 999px;
    border: 1px solid rgba(148, 163, 184, 0.24);
    background: rgba(15, 23, 42, 0.06);
  }

  .wo-scan-create-rn-swal .wo-scan-create-doc-option {
    flex: 1 1 50%;
    min-width: 0;
    border: 0;
    border-radius: 999px;
    padding: 0.8rem 1rem;
    background: transparent;
    color: inherit;
    font-size: 0.98rem;
    font-weight: 700;
    line-height: 1;
    transition: background-color 0.18s ease, color 0.18s ease, box-shadow 0.18s ease, opacity 0.18s ease;
  }

  .wo-scan-create-rn-swal .wo-scan-create-doc-option:hover,
  .wo-scan-create-rn-swal .wo-scan-create-doc-option:focus {
    opacity: 0.9;
  }

  .wo-scan-create-rn-swal .wo-scan-create-doc-option.is-active {
    background: linear-gradient(135deg, rgba(76, 189, 158, 0.26), rgba(53, 141, 118, 0.22));
    color: #f4fffb;
    box-shadow: inset 0 0 0 1px rgba(111, 255, 214, 0.18);
  }

  .wo-scan-create-rn-swal .wo-scan-create-last {
    color: #8f9ab4;
    text-align: right;
    white-space: nowrap;
  }

  .wo-scan-create-loading-swal .wo-scan-create-loading-title {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.2rem;
    text-align: center;
  }

  .wo-scan-create-loading-swal .wo-scan-create-loading-title-main {
    font-size: 1.05rem;
    line-height: 1.2;
    font-weight: 700;
  }

  .wo-scan-create-loading-swal .wo-scan-create-loading-title-number {
    display: inline-block;
    font-size: clamp(1.65rem, 4vw, 2.15rem);
    line-height: 1.08;
    font-weight: 800;
    white-space: nowrap;
  }

  .wo-scan-create-loading-swal .wo-scan-create-loading-copy {
    margin-top: 0.9rem;
    text-align: center;
  }

  body.dark-layout .wo-scan-create-rn-swal .wo-scan-create-doc-switch,
  body.semi-dark-layout .wo-scan-create-rn-swal .wo-scan-create-doc-switch {
    border-color: rgba(170, 183, 212, 0.24);
    background: rgba(12, 17, 28, 0.8);
  }

  body.dark-layout .wo-scan-create-rn-swal .wo-scan-create-doc-option,
  body.semi-dark-layout .wo-scan-create-rn-swal .wo-scan-create-doc-option {
    color: #dfe7fb;
  }

  body.dark-layout .wo-scan-create-rn-swal .wo-scan-create-doc-option.is-active,
  body.semi-dark-layout .wo-scan-create-rn-swal .wo-scan-create-doc-option.is-active {
    background: linear-gradient(135deg, rgba(74, 179, 148, 0.34), rgba(33, 98, 82, 0.52));
    color: #f4fffb;
  }

  body.dark-layout .wo-scan-create-rn-swal .wo-scan-create-doc-label,
  body.semi-dark-layout .wo-scan-create-rn-swal .wo-scan-create-doc-label,
  body.dark-layout .wo-scan-create-rn-swal .wo-scan-create-last,
  body.semi-dark-layout .wo-scan-create-rn-swal .wo-scan-create-last {
    color: #9fb0d6;
  }

  @media (max-width: 575.98px) {
    .wo-scan-create-rn-swal .wo-scan-create-doc-head {
      flex-direction: column;
      align-items: flex-start;
    }

    .wo-scan-create-rn-swal .wo-scan-create-last {
      text-align: left;
      white-space: normal;
    }

    #qr-scanner-modal .wo-scanner-close-fab {
      top: max(0.75rem, env(safe-area-inset-top));
      left: max(0.75rem, env(safe-area-inset-left));
      padding: 0.5rem 0.82rem;
    }

    #qr-scanner-modal .wo-qr-controls-row {
      flex-direction: column;
      align-items: stretch;
    }

    #qr-scanner-modal .wo-qr-camera-controls-row {
      flex-direction: row;
      align-items: center;
    }

    #qr-scanner-modal .wo-qr-camera-row {
      flex-direction: column;
      align-items: stretch;
    }

    #qr-scanner-modal .wo-qr-btn-primary {
      width: 100%;
      min-width: 0;
    }
  }
</style>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('qr-scanner-modal');
    if (!modal) {
      return;
    }

    var frameEl = document.getElementById('qr-scanner-frame');
    var statusEl = document.getElementById('qr-scanner-status');
    var errorEl = document.getElementById('qr-scanner-error');
    var mirrorToggle = document.getElementById('qr-mirror-toggle');
    var restartBtn = document.getElementById('qr-scanner-restart-btn');
    var cameraSelect = document.getElementById('qr-camera-select');
    var cameraApplyBtn = document.getElementById('qr-camera-apply-btn');
    var previewPathPattern = @json($workOrderPreviewPathPattern);
    var scanLookupUrl = @json($workOrderScanLookupUrl);
    var scanCreateUrl = @json($workOrderScanCreateUrl);
    var csrfToken = @json(csrf_token());

    var scanner = null;
    var scannerRunning = false;
    var redirecting = false;
    var cameras = [];
    var lastDecodedText = '';
    var lastDecodedAt = 0;
    var duplicateWindowMs = 900;

    var scanConfig = {
      fps: 10,
      qrbox: function (viewfinderWidth, viewfinderHeight) {
        var edge = Math.max(180, Math.floor(Math.min(viewfinderWidth, viewfinderHeight) * 0.72));
        return { width: edge, height: edge };
      },
      aspectRatio: 1
    };

    function setStatus(text, tone) {
      statusEl.textContent = text;
      statusEl.classList.remove('text-white-50', 'text-success', 'text-danger', 'text-warning');
      if (tone === 'success') {
        statusEl.classList.add('text-success');
      } else if (tone === 'danger') {
        statusEl.classList.add('text-danger');
      } else if (tone === 'warning') {
        statusEl.classList.add('text-warning');
      } else {
        statusEl.classList.add('text-white-50');
      }
    }

    function showError(text) {
      errorEl.textContent = text;
      errorEl.classList.remove('d-none');
    }

    function clearError() {
      errorEl.textContent = '';
      errorEl.classList.add('d-none');
    }

    function activeBackdropNodes() {
      var backdrops = Array.prototype.slice.call(document.querySelectorAll('.modal-backdrop'));
      if (backdrops.length === 0) {
        return [];
      }

      var shownBackdrops = backdrops.filter(function (node) {
        return node.classList.contains('show');
      });

      return shownBackdrops.length > 0 ? shownBackdrops : backdrops;
    }

    function applyBackdrop() {
      activeBackdropNodes().forEach(function (backdrop) {
        backdrop.classList.add('qr-scanner-backdrop');
        backdrop.style.backgroundColor = 'rgba(0, 0, 0, 0.985)';
        backdrop.style.opacity = '1';
      });
    }

    function clearBackdrop() {
      activeBackdropNodes().forEach(function (backdrop) {
        backdrop.classList.remove('qr-scanner-backdrop');
        backdrop.style.backgroundColor = '';
        backdrop.style.opacity = '';
      });
    }

    function queueBackdropSync() {
      window.requestAnimationFrame(function () {
        applyBackdrop();
      });
      window.setTimeout(applyBackdrop, 0);
      window.setTimeout(applyBackdrop, 40);
      window.setTimeout(applyBackdrop, 120);
    }

    function applyMirrorState() {
      frameEl.classList.toggle('qr-mirror-on', !!mirrorToggle.checked);
    }

    function extractWorkOrderIdFromPath(pathname) {
      var match = pathname.match(/\/(?:app\/)?invoice\/preview\/([^/?#]+)/i);
      return match ? decodeURIComponent(match[1]) : null;
    }

    function parseOrderLocatorToken(text) {
      var parts = (text || '').split(';').map(function (part) {
        return (part || '').trim();
      });
      if (parts.length < 2 || parts.length > 3) {
        return null;
      }

      var rawOrderNumber = parts[0] || '';
      var rawOrderPosition = parts[1] || '';
      var rawProductCode = parts.length === 3 ? (parts[2] || '') : '';

      if (!rawOrderNumber || !rawOrderPosition) {
        return null;
      }

      var normalizedOrderNumber = rawOrderNumber.replace(/[^0-9A-Za-z]+/g, '').toUpperCase();
      if (!normalizedOrderNumber) {
        return null;
      }

      var numericPosition = Number(rawOrderPosition.replace(',', '.'));
      if (!Number.isFinite(numericPosition)) {
        return null;
      }

      var integerPosition = Math.round(numericPosition);
      if (Math.abs(numericPosition - integerPosition) > 0.000001) {
        return null;
      }

      var parsedToken = {
        identifier: rawOrderNumber + ';' + String(integerPosition),
        details: {
          broj_narudzbe: rawOrderNumber,
          poz: String(integerPosition),
          sifra: ''
        }
      };

      if (parts.length === 3) {
        var normalizedProductCode = rawProductCode.replace(/[^0-9A-Za-z]+/g, '').toUpperCase();
        if (!normalizedProductCode) {
          return null;
        }

        parsedToken.identifier = rawOrderNumber + ';' + String(integerPosition) + ';' + rawProductCode;
        parsedToken.details.sifra = rawProductCode;
        return parsedToken;
      }

      return parsedToken;
    }

    function parseWorkOrderIdFromQr(rawText) {
      var text = (rawText || '').trim();
      if (!text) {
        return { id: null, error: 'Prazan QR sadrzaj.' };
      }

      var orderLocator = parseOrderLocatorToken(text);
      if (orderLocator) {
        return { id: orderLocator.identifier, error: null, scanMeta: orderLocator.details || null };
      }

      if (/^https?:\/\//i.test(text)) {
        try {
          var parsedUrl = new URL(text);
          var idFromUrl = extractWorkOrderIdFromPath(parsedUrl.pathname);
          if (idFromUrl) {
            return { id: idFromUrl, error: null, scanMeta: null };
          }
          return { id: null, error: 'Neispravan QR format. Ocekivan je link na radni nalog.' };
        } catch (e) {
          return { id: null, error: 'QR ne sadrži validan URL radnog naloga.' };
        }
      }

      var pathLike = text.startsWith('/') ? text : ('/' + text);
      var idFromPath = extractWorkOrderIdFromPath(pathLike);
      if (idFromPath) {
        return { id: idFromPath, error: null, scanMeta: null };
      }

      return { id: null, error: 'Neispravan QR format. Koristi brojNarudzbe;pozicija;sifraProizvoda ili link na radni nalog.' };
    }

    function toWorkOrderPreviewUrl(workOrderId) {
      var path = previewPathPattern.replace('__WORK_ORDER_ID__', encodeURIComponent(workOrderId));
      var targetUrl = new URL(path, window.location.origin);
      targetUrl.searchParams.set('scan', '1');
      return targetUrl.toString();
    }

    function swalWithProjectTheme(options) {
      if (typeof window.woSwalWithTheme === 'function') {
        return window.woSwalWithTheme(options || {});
      }

      return options || {};
    }

    function escapeHtml(value) {
      return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

  function buildScanDetailRowsHtml(context) {
    var details = [];
    var brojNarudzbe = context && context.broj_narudzbe ? String(context.broj_narudzbe) : '';
    var poz = context && context.poz ? String(context.poz) : '';
    var sifra = context && context.sifra ? String(context.sifra) : '';
    var naziv = context && context.naziv ? String(context.naziv) : '';

      if (brojNarudzbe) {
        details.push('<div><span class="fw-bolder">Broj narudžbe:</span> ' + escapeHtml(brojNarudzbe) + '</div>');
      }
      if (poz) {
        details.push('<div><span class="fw-bolder">Poz:</span> ' + escapeHtml(poz) + '</div>');
      }
      if (sifra) {
        details.push('<div><span class="fw-bolder">Šifra:</span> ' + escapeHtml(sifra) + '</div>');
      }
      if (naziv) {
        details.push('<div><span class="fw-bolder">Naziv:</span> ' + escapeHtml(naziv) + '</div>');
      }

      return details.join('');
    }

    function buildScanSummaryHtml(context, extraHtml) {
      return [
        '<div class="text-start mt-1">',
        extraHtml || '',
        '<div class="small lh-lg">',
        buildScanDetailRowsHtml(context),
        '</div>',
        '</div>'
      ].join('');
    }

    function normalizeCreateDocTypePayload(payload) {
      var rawOptions = payload && payload.doc_type_options && typeof payload.doc_type_options === 'object'
        ? payload.doc_type_options
        : {};
      var resolvedOptions = {};

      ['6000', '6001'].forEach(function (docType) {
        var option = rawOptions[docType] && typeof rawOptions[docType] === 'object' ? rawOptions[docType] : {};

        resolvedOptions[docType] = {
          value: docType,
          label: option.label || docType,
          next_work_order: option.next_work_order && typeof option.next_work_order === 'object'
            ? option.next_work_order
            : {},
          last_work_order: option.last_work_order && typeof option.last_work_order === 'object'
            ? option.last_work_order
            : {}
        };
      });

      return {
        selected: rawOptions[payload && payload.doc_type ? String(payload.doc_type) : '']
          ? String(payload.doc_type)
          : '6000',
        options: resolvedOptions
      };
    }

    function getCreateDocTypeOption(docTypeState, docType) {
      var state = docTypeState && typeof docTypeState === 'object' ? docTypeState : { options: {} };
      var options = state.options && typeof state.options === 'object' ? state.options : {};

      return options[docType] || options['6000'] || {
        value: '6000',
        label: '6000',
        next_work_order: {},
        last_work_order: {}
      };
    }

    function buildCreateWorkOrderPromptHtml(order, docTypeState) {
      var selectedOption = getCreateDocTypeOption(docTypeState, docTypeState.selected);
      var nextWorkOrder = selectedOption.next_work_order || {};
      var lastWorkOrder = selectedOption.last_work_order || {};

      return [
        '<div class="text-start mt-1">',
        '<div class="wo-scan-create-head">',
        '<div class="wo-scan-create-copy">Da li želite kreirati RN broj</div>',
        '<div class="wo-scan-create-next-number" id="wo-scan-create-next-number">' + escapeHtml(nextWorkOrder.number || '') + '?</div>',
        '</div>',
        '<div class="wo-scan-create-doc-card">',
        '<div class="wo-scan-create-doc-head">',
        '<div class="wo-scan-create-doc-label">Vrsta dokumenta</div>',
        '<div class="wo-scan-create-last small" id="wo-scan-create-last"><span class="fw-bolder">Zadnji RN:</span> ' + escapeHtml(lastWorkOrder.number || '-') + '</div>',
        '</div>',
        '<div class="wo-scan-create-doc-switch" role="tablist" aria-label="Vrsta dokumenta">',
        Object.keys(docTypeState.options || {}).map(function (docType) {
          var option = docTypeState.options[docType] || {};
          var activeClass = docType === docTypeState.selected ? ' is-active' : '';

          return '<button type="button" class="wo-scan-create-doc-option' + activeClass + '" data-doc-type="' + escapeHtml(docType) + '" aria-pressed="' + (docType === docTypeState.selected ? 'true' : 'false') + '">' + escapeHtml(option.label || docType) + '</button>';
        }).join(''),
        '</div>',
        '</div>',
        '<div class="small lh-lg">',
        buildScanDetailRowsHtml(order),
        '</div>',
        '</div>'
      ].join('');
    }

    function syncCreateWorkOrderPrompt(popup, docTypeState, docType) {
      var selectedOption = getCreateDocTypeOption(docTypeState, docType);
      var nextWorkOrder = selectedOption.next_work_order || {};
      var lastWorkOrder = selectedOption.last_work_order || {};
      var nextNumberEl = popup ? popup.querySelector('#wo-scan-create-next-number') : null;
      var lastWorkOrderEl = popup ? popup.querySelector('#wo-scan-create-last') : null;

      Array.prototype.forEach.call(
        popup ? popup.querySelectorAll('.wo-scan-create-doc-option') : [],
        function (button) {
          var isActive = button.getAttribute('data-doc-type') === docType;
          button.classList.toggle('is-active', isActive);
          button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        }
      );

      if (nextNumberEl) {
        nextNumberEl.textContent = nextWorkOrder.number || '';
      }

      if (lastWorkOrderEl) {
        lastWorkOrderEl.innerHTML = '<span class="fw-bolder">Zadnji RN:</span> ' + escapeHtml(lastWorkOrder.number || '-');
      }
    }

    function bindCreateDocTypeSwitch(popup, docTypeState, onChange) {
      syncCreateWorkOrderPrompt(popup, docTypeState, docTypeState.selected);

      Array.prototype.forEach.call(
        popup ? popup.querySelectorAll('.wo-scan-create-doc-option') : [],
        function (button) {
          button.addEventListener('click', function () {
            var nextDocType = button.getAttribute('data-doc-type') || '';

            if (!docTypeState.options || !docTypeState.options[nextDocType]) {
              return;
            }

            docTypeState.selected = nextDocType;
            syncCreateWorkOrderPrompt(popup, docTypeState, nextDocType);

            if (typeof onChange === 'function') {
              onChange(nextDocType, docTypeState.options[nextDocType]);
            }
          });
        }
      );
    }

    function mergeScanContext(primaryContext, fallbackContext) {
      var primary = primaryContext && typeof primaryContext === 'object' ? primaryContext : {};
      var fallback = fallbackContext && typeof fallbackContext === 'object' ? fallbackContext : {};

      return {
        broj_narudzbe: primary.broj_narudzbe || fallback.broj_narudzbe || '',
        poz: primary.poz || fallback.poz || '',
        sifra: primary.sifra || fallback.sifra || '',
        naziv: primary.naziv || fallback.naziv || ''
      };
    }

    async function requestJson(url, options) {
      var response = await fetch(url, options || {});
      var data = null;

      try {
        data = await response.json();
      } catch (e) {
        data = null;
      }

      if (!response.ok) {
        var errorMessage = data && data.message ? data.message : 'Neuspjesan odgovor servera.';

        if (data && data.debug && Array.isArray(data.debug.messages) && data.debug.messages.length > 0) {
          errorMessage += ' [' + data.debug.messages[0] + ']';
        }

        var error = new Error(errorMessage);
        error.response = data;
        throw error;
      }

      return data || {};
    }

    async function lookupScannedWorkOrder(identifier) {
      var lookupTarget = new URL(scanLookupUrl, window.location.origin);
      lookupTarget.searchParams.set('identifier', identifier);

      return requestJson(lookupTarget.toString(), {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
      });
    }

    async function createWorkOrderFromScan(identifier, docType) {
      return requestJson(scanCreateUrl, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin',
        body: JSON.stringify({
          identifier: identifier,
          doc_type: docType || '6000'
        })
      });
    }

    async function resumeScannerAfterPrompt() {
      redirecting = false;
      lastDecodedText = '';
      lastDecodedAt = 0;
      clearError();
      setStatus('Usmjeri kameru prema QR kodu radnog naloga.');

      if (!modal.classList.contains('show')) {
        return;
      }

      await startScanner();
    }

    async function showScanError(message) {
      if (window.Swal && typeof window.Swal.fire === 'function') {
        await Swal.fire(swalWithProjectTheme({
          title: 'Skeniranje nije uspjelo',
          text: message || 'Ne mogu obraditi skenirani QR kod.',
          icon: 'error',
          confirmButtonText: 'Ponovno skeniranje',
          customClass: {
            confirmButton: 'btn btn-danger'
          },
          buttonsStyling: false
        }));
      }

      await resumeScannerAfterPrompt();
      showError(message || 'Ne mogu obraditi skenirani QR kod.');
    }

    async function promptForExistingWorkOrder(payload, scanMeta) {
      var workOrder = payload && payload.work_order ? payload.work_order : {};
      var previewUrl = workOrder.preview_url || toWorkOrderPreviewUrl(workOrder.id || '');
      var scanSummary = mergeScanContext(scanMeta, workOrder);
      var result = await Swal.fire(swalWithProjectTheme({
        title: 'RN pronadjen',
        html: buildScanSummaryHtml(workOrder, '<p class="mb-1">Da li želite otvoriti RN broj <strong>' + escapeHtml(workOrder.number || '') + '</strong>?</p>'),
        icon: 'question',
        showCancelButton: true,
        reverseButtons: true,
        confirmButtonText: 'Otvori RN',
        cancelButtonText: 'Ponovno skeniranje',
        customClass: {
          confirmButton: 'btn btn-success ms-1',
          cancelButton: 'btn btn-danger'
        },
        buttonsStyling: false
      }));

      if (result.isConfirmed) {
        setStatus('Otvaram radni nalog...', 'success');
        window.location.assign(previewUrl);
        return;
      }

      await resumeScannerAfterPrompt();
    }

    async function promptToCreateWorkOrderLegacy(identifier, payload, scanMeta) {
      var order = payload && payload.order ? payload.order : {};
      var nextWorkOrder = payload && payload.next_work_order ? payload.next_work_order : {};
      var lastWorkOrder = payload && payload.last_work_order ? payload.last_work_order : {};
      var scanSummary = mergeScanContext(scanMeta, order);
      var extraHtml = '<h2 class="mb-1">Da li želite kreirati RN broj <br><strong>' + escapeHtml(nextWorkOrder.number || '') + '?</strong></h2>';


      var result = await Swal.fire(swalWithProjectTheme({
        title: 'RN nije pronađen',
        html: buildScanSummaryHtml(order, extraHtml),
        icon: 'question',
        showCancelButton: true,
        reverseButtons: true,
        confirmButtonText: 'Kreiraj RN',
        cancelButtonText: 'Ponovno skeniranje',
        customClass: {
          confirmButton: 'btn btn-success ms-1',
          cancelButton: 'btn btn-danger'
        },
        buttonsStyling: false
      }));

      if (!result.isConfirmed) {
        await resumeScannerAfterPrompt();
        return;
      }

      Swal.fire(swalWithProjectTheme({
        html: [
          '<div class="wo-scan-create-loading-title">',
          '<div class="wo-scan-create-loading-title-main">Kreiram</div>',
          '<div class="wo-scan-create-loading-title-number">RN ' + escapeHtml(nextWorkOrder.number || '') + '</div>',
          '</div>',
          '<div class="wo-scan-create-loading-copy">Prepisujem podatke sa narudzbe i otvaram radni nalog...</div>'
        ].join(''),
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        customClass: {
          popup: 'wo-scan-create-loading-swal'
        },
        didOpen: function () {
          Swal.showLoading();
          var loader = Swal.getLoader();
          if (loader) {
            loader.style.borderColor = 'rgba(40, 199, 111, 0.18)';
            loader.style.borderTopColor = '#28c76f';
            loader.style.borderRightColor = '#28c76f';
          }
        }
      }));

      try {
        var createResponse = await createWorkOrderFromScan(identifier);
        var createdWorkOrder = createResponse && createResponse.data && createResponse.data.work_order
          ? createResponse.data.work_order
          : {};
        var previewUrl = createdWorkOrder.preview_url || toWorkOrderPreviewUrl(createdWorkOrder.id || '');

        setStatus('Radni nalog je spreman. Otvaram...', 'success');
        window.location.assign(previewUrl);
      } catch (error) {
        if (window.Swal && typeof window.Swal.close === 'function') {
          Swal.close();
        }

        await showScanError(error && error.message ? error.message : 'Ne mogu kreirati radni nalog iz skena.');
      }
    }

    async function promptToCreateWorkOrder(identifier, payload, scanMeta) {
      var order = payload && payload.order ? payload.order : {};
      var docTypeState = normalizeCreateDocTypePayload(payload);
      var selectedDocType = docTypeState.selected;

      var result = await Swal.fire(swalWithProjectTheme({
        title: 'RN nije pronađen',
        html: buildCreateWorkOrderPromptHtml(order, docTypeState),
        icon: 'question',
        showCancelButton: true,
        reverseButtons: true,
        confirmButtonText: 'Kreiraj RN',
        cancelButtonText: 'Ponovno skeniranje',
        customClass: {
          popup: 'wo-scan-create-rn-swal',
          confirmButton: 'btn btn-success ms-1',
          cancelButton: 'btn btn-danger'
        },
        buttonsStyling: false,
        didOpen: function (popup) {
          bindCreateDocTypeSwitch(popup, docTypeState, function (nextDocType) {
            selectedDocType = nextDocType;
          });
        }
      }));

      if (!result.isConfirmed) {
        await resumeScannerAfterPrompt();
        return;
      }

      var selectedDocTypeOption = getCreateDocTypeOption(docTypeState, selectedDocType);
      var selectedNextWorkOrder = selectedDocTypeOption.next_work_order || {};

      Swal.fire(swalWithProjectTheme({
        html: [
          '<div class="wo-scan-create-loading-title">',
          '<div class="wo-scan-create-loading-title-main">Kreiram</div>',
          '<div class="wo-scan-create-loading-title-number">RN ' + escapeHtml(selectedNextWorkOrder.number || '') + '</div>',
          '</div>',
          '<div class="wo-scan-create-loading-copy">Prepisujem podatke sa narudžbe i otvaram radni nalog...</div>'
        ].join(''),
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        customClass: {
          popup: 'wo-scan-create-loading-swal'
        },
        didOpen: function () {
          Swal.showLoading();
          var loader = Swal.getLoader();
          if (loader) {
            loader.style.borderColor = 'rgba(40, 199, 111, 0.18)';
            loader.style.borderTopColor = '#28c76f';
            loader.style.borderRightColor = '#28c76f';
          }
        }
      }));

      try {
        var createResponse = await createWorkOrderFromScan(identifier, selectedDocType);
        var createdWorkOrder = createResponse && createResponse.data && createResponse.data.work_order
          ? createResponse.data.work_order
          : {};
        var previewUrl = createdWorkOrder.preview_url || toWorkOrderPreviewUrl(createdWorkOrder.id || '');

        setStatus('Radni nalog je spreman. Otvaram...', 'success');
        window.location.assign(previewUrl);
      } catch (error) {
        if (window.Swal && typeof window.Swal.close === 'function') {
          Swal.close();
        }

        await showScanError(error && error.message ? error.message : 'Ne mogu kreirati radni nalog iz skena.');
      }
    }

    async function handleScanLookup(identifier, lookupResponse, scanMeta) {
      var payload = lookupResponse && lookupResponse.data ? lookupResponse.data : {};
      var existingWorkOrder = payload && payload.work_order ? payload.work_order : {};
      var existingPreviewUrl = existingWorkOrder.preview_url || toWorkOrderPreviewUrl(existingWorkOrder.id || '');

      if (payload.status === 'existing' && existingPreviewUrl) {
        setStatus('Otvaram radni nalog...', 'success');
        window.location.assign(existingPreviewUrl);
        return;
      }

      if (!window.Swal || typeof window.Swal.fire !== 'function') {
        throw new Error('SweetAlert nije dostupan za potvrdu skeniranog naloga.');
      }

      if (payload.status === 'create_available') {
        await promptToCreateWorkOrder(identifier, payload, scanMeta);
        return;
      }

      throw new Error(payload.message || 'Skenirani QR nije moguce obraditi.');
    }

    function renderCameraOptions() {
      var selected = cameraSelect.value;
      cameraSelect.innerHTML = '<option value="">Automatski odabir</option>';
      cameras.forEach(function (camera) {
        var option = document.createElement('option');
        option.value = camera.id;
        option.textContent = camera.label || ('Kamera ' + camera.id);
        cameraSelect.appendChild(option);
      });
      if (selected) {
        cameraSelect.value = selected;
      }
    }

    async function refreshCameras() {
      if (typeof Html5Qrcode === 'undefined') {
        return;
      }
      try {
        cameras = await Html5Qrcode.getCameras();
        renderCameraOptions();
      } catch (e) {
        cameras = [];
        renderCameraOptions();
      }
    }

    function sourceCandidates() {
      var selected = cameraSelect.value;
      var candidates = [];

      if (selected) {
        candidates.push(selected);
      } else {
        candidates.push({ facingMode: { exact: 'environment' } });
        candidates.push({ facingMode: 'environment' });
        candidates.push({ facingMode: 'user' });
      }

      cameras.forEach(function (camera) {
        candidates.push(camera.id);
      });

      return candidates.filter(function (candidate, index, arr) {
        return arr.indexOf(candidate) === index;
      });
    }

    function onScanSuccess(decodedText) {
      if (redirecting) {
        return;
      }

      var now = Date.now();
      if (decodedText === lastDecodedText && (now - lastDecodedAt) < duplicateWindowMs) {
        return;
      }

      lastDecodedText = decodedText;
      lastDecodedAt = now;

      var parsed = parseWorkOrderIdFromQr(decodedText);
      if (!parsed.id) {
        setStatus('Usmjeri kameru prema QR kodu radnog naloga.');
        showError(parsed.error || 'Neispravan QR format.');
        return;
      }

      clearError();
      setStatus('QR prepoznat. Provjeravam radni nalog i narudžbu...', 'success');
      redirecting = true;
      stopScanner()
        .then(function () {
          return lookupScannedWorkOrder(parsed.id);
        })
        .then(function (lookupResponse) {
          return handleScanLookup(parsed.id, lookupResponse);
        })
        .catch(function (error) {
          console.error(error);
          showScanError(error && error.message ? error.message : 'Ne mogu obraditi skenirani QR kod.');
        });
    }

    async function startScanner() {
      if (scannerRunning || redirecting) {
        return;
      }

      if (typeof Html5Qrcode === 'undefined') {
        setStatus('Skener nije spreman.');
        showError('QR biblioteka nije dostupna. Osvježi stranicu i pokušaj ponovo.');
        return;
      }

      clearError();
      setStatus('Pokrećem kameru...');

      if (!scanner) {
        scanner = new Html5Qrcode('qr-scanner-region');
      }

      await refreshCameras();

      var candidates = sourceCandidates();
      var started = false;
      var lastError = null;

      for (var i = 0; i < candidates.length; i += 1) {
        try {
          await scanner.start(candidates[i], scanConfig, onScanSuccess, function () {});
          started = true;
          break;
        } catch (err) {
          lastError = err;
        }
      }

      if (!started) {
        setStatus('Skener nije pokrenut.');
        showError('Ne mogu pokrenuti kameru. Provjeri dozvole preglednika i HTTPS/localhost.');
        if (lastError) {
          console.error(lastError);
        }
        return;
      }

      scannerRunning = true;
      applyMirrorState();
      setStatus('Usmjeri kameru prema QR kodu radnog naloga.');
    }

    async function stopScanner() {
      if (!scanner || !scannerRunning) {
        return;
      }

      try {
        await scanner.stop();
      } catch (e) {
        console.error(e);
      }

      scannerRunning = false;
    }

    async function restartScanner() {
      await stopScanner();
      await startScanner();
    }

    mirrorToggle.addEventListener('change', applyMirrorState);
    restartBtn.addEventListener('click', function () {
      restartScanner();
    });
    cameraApplyBtn.addEventListener('click', function () {
      restartScanner();
    });

    var observer = new MutationObserver(function () {
      if (modal.classList.contains('show')) {
        applyBackdrop();
      }
    });

    observer.observe(document.body, {
      childList: true,
      subtree: true
    });

    modal.addEventListener('show.bs.modal', function () {
      queueBackdropSync();
    });

    modal.addEventListener('shown.bs.modal', function () {
      redirecting = false;
      lastDecodedText = '';
      lastDecodedAt = 0;
      queueBackdropSync();
      startScanner();
    });

    modal.addEventListener('hide.bs.modal', function () {
      stopScanner();
    });

    modal.addEventListener('hidden.bs.modal', function () {
      setStatus('Skener je zaustavljen.');
      clearError();
      clearBackdrop();
    });
  });
</script>
