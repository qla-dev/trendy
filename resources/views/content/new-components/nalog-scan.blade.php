@php
  $workOrderPreviewPathPattern = route('app-invoice-preview', ['id' => '__WORK_ORDER_ID__'], false);
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
    width: 2.2rem;
    height: 2.2rem;
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
  }

  #qr-scanner-modal .form-select {
    min-height: 2.3rem;
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
    min-height: 2.3rem;
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

  @media (max-width: 575.98px) {
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

    function applyBackdrop() {
      var backdrop = document.querySelector('.modal-backdrop');
      if (!backdrop) {
        return;
      }
      backdrop.classList.add('qr-scanner-backdrop');
      backdrop.style.backgroundColor = 'rgba(0, 0, 0, 0.985)';
      backdrop.style.opacity = '1';
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

      if (parts.length === 3) {
        var normalizedProductCode = rawProductCode.replace(/[^0-9A-Za-z]+/g, '').toUpperCase();
        if (!normalizedProductCode) {
          return null;
        }

        return normalizedOrderNumber + ';' + String(integerPosition) + ';' + normalizedProductCode;
      }

      return normalizedOrderNumber + ';' + String(integerPosition);
    }

    function parseWorkOrderIdFromQr(rawText) {
      var text = (rawText || '').trim();
      if (!text) {
        return { id: null, error: 'Prazan QR sadrzaj.' };
      }

      var orderLocator = parseOrderLocatorToken(text);
      if (orderLocator) {
        return { id: orderLocator, error: null };
      }

      if (/^https?:\/\//i.test(text)) {
        try {
          var parsedUrl = new URL(text);
          var idFromUrl = extractWorkOrderIdFromPath(parsedUrl.pathname);
          if (idFromUrl) {
            return { id: idFromUrl, error: null };
          }
          return { id: null, error: 'Neispravan QR format. Ocekivan je link na radni nalog.' };
        } catch (e) {
          return { id: null, error: 'QR ne sadrzi validan URL radnog naloga.' };
        }
      }

      var pathLike = text.startsWith('/') ? text : ('/' + text);
      var idFromPath = extractWorkOrderIdFromPath(pathLike);
      if (idFromPath) {
        return { id: idFromPath, error: null };
      }

      return { id: null, error: 'Neispravan QR format. Koristi brojNarudzbe;pozicija;sifraProizvoda ili link na radni nalog.' };
    }

    function toWorkOrderPreviewUrl(workOrderId) {
      var path = previewPathPattern.replace('__WORK_ORDER_ID__', encodeURIComponent(workOrderId));
      var targetUrl = new URL(path, window.location.origin);
      targetUrl.searchParams.set('scan', '1');
      return targetUrl.toString();
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
      setStatus('QR prepoznat. Otvaram radni nalog...', 'success');
      redirecting = true;

      var targetUrl = toWorkOrderPreviewUrl(parsed.id);
      stopScanner().finally(function () {
        window.location.assign(targetUrl);
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
      setTimeout(applyBackdrop, 40);
    });

    modal.addEventListener('shown.bs.modal', function () {
      redirecting = false;
      lastDecodedText = '';
      lastDecodedAt = 0;
      applyBackdrop();
      startScanner();
    });

    modal.addEventListener('hide.bs.modal', function () {
      stopScanner();
    });

    modal.addEventListener('hidden.bs.modal', function () {
      setStatus('Skener je zaustavljen.');
      clearError();
      var backdrop = document.querySelector('.modal-backdrop');
      if (backdrop) {
        backdrop.classList.remove('qr-scanner-backdrop');
      }
    });
  });
</script>
