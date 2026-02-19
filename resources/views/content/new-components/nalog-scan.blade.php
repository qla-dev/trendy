@php
  $workOrderPreviewPathPattern = route('app-invoice-preview', ['id' => '__WORK_ORDER_ID__'], false);
@endphp

<!-- Work Order QR Scanner Modal -->
<div class="modal fade" id="qr-scanner-modal" tabindex="-1" aria-labelledby="qr-scanner-modal-label" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content wo-qr-modal-content">
      <div class="modal-body p-1 p-md-2">
        <div class="d-flex align-items-center justify-content-between mb-1">
          <h4 class="text-white mb-0" id="qr-scanner-modal-label">Skeniraj QR kod radnog naloga</h4>
          <button type="button" class="btn btn-sm btn-outline-light" data-bs-dismiss="modal" aria-label="Zatvori">
            <i class="fa fa-times me-50"></i>Zatvori
          </button>
        </div>

        <div class="wo-qr-region-wrap mb-1">
          <div id="qr-scanner-region" class="wo-qr-region"></div>
          <div class="wo-qr-corner wo-qr-corner-tl"></div>
          <div class="wo-qr-corner wo-qr-corner-tr"></div>
          <div class="wo-qr-corner wo-qr-corner-bl"></div>
          <div class="wo-qr-corner wo-qr-corner-br"></div>
          <div class="wo-qr-scan-line"></div>
        </div>

        <div class="d-flex align-items-center justify-content-between flex-wrap gap-75 mb-1">
          <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" id="qr-mirror-toggle">
            <label class="form-check-label text-white-50" for="qr-mirror-toggle">Preslikaj prikaz (mirror)</label>
          </div>
          <button type="button" class="btn btn-sm btn-outline-light" id="qr-scanner-restart-btn">
            <i class="fa fa-refresh me-50"></i>Ponovo pokreni
          </button>
        </div>

        <div class="row g-75 mb-1">
          <div class="col-12 col-md-8">
            <label class="form-label text-white-50 mb-50" for="qr-camera-select">Kamera</label>
            <select class="form-select form-select-sm" id="qr-camera-select">
              <option value="">Automatski odabir</option>
            </select>
          </div>
          <div class="col-12 col-md-4 d-grid">
            <button type="button" class="btn btn-sm btn-outline-light mt-md-175" id="qr-camera-apply-btn">Primijeni</button>
          </div>
        </div>

        <div id="qr-scanner-status" class="small text-white-50">Dozvoli pristup kameri da skeniranje započne.</div>
        <div id="qr-scanner-error" class="small text-danger mt-50 d-none"></div>
      </div>
    </div>
  </div>
</div>

<style>
  .qr-scanner-backdrop {
    background-color: rgba(0, 0, 0, 0.95) !important;
    opacity: 1 !important;
  }

  #qr-scanner-modal .modal-dialog {
    max-width: 520px;
  }

  #qr-scanner-modal .wo-qr-modal-content {
    background: rgba(11, 14, 20, 0.95);
    border: 1px solid rgba(255, 255, 255, 0.14);
    box-shadow: 0 20px 48px rgba(0, 0, 0, 0.45);
    border-radius: 12px;
  }

  #qr-scanner-modal .wo-qr-region-wrap {
    position: relative;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    overflow: hidden;
    background: rgba(255, 255, 255, 0.06);
    aspect-ratio: 1 / 1;
  }

  #qr-scanner-modal .wo-qr-region {
    width: 100%;
    height: 100%;
  }

  #qr-scanner-modal .wo-qr-region > div {
    border: 0 !important;
  }

  #qr-scanner-modal .wo-qr-region.is-mirrored video,
  #qr-scanner-modal .wo-qr-region.is-mirrored canvas {
    transform: scaleX(-1);
  }

  #qr-scanner-modal .wo-qr-corner {
    position: absolute;
    width: 34px;
    height: 34px;
    border-color: #00cfe8;
    border-style: solid;
    border-width: 0;
    z-index: 4;
    animation: woQrCornerPulse 2.2s ease-in-out infinite;
  }

  #qr-scanner-modal .wo-qr-corner-tl {
    top: 0;
    left: 0;
    border-top-width: 3px;
    border-left-width: 3px;
  }

  #qr-scanner-modal .wo-qr-corner-tr {
    top: 0;
    right: 0;
    border-top-width: 3px;
    border-right-width: 3px;
    animation-delay: .4s;
  }

  #qr-scanner-modal .wo-qr-corner-bl {
    bottom: 0;
    left: 0;
    border-bottom-width: 3px;
    border-left-width: 3px;
    animation-delay: .8s;
  }

  #qr-scanner-modal .wo-qr-corner-br {
    bottom: 0;
    right: 0;
    border-bottom-width: 3px;
    border-right-width: 3px;
    animation-delay: 1.2s;
  }

  #qr-scanner-modal .wo-qr-scan-line {
    position: absolute;
    left: 0;
    right: 0;
    top: 0;
    height: 2px;
    background: linear-gradient(90deg, transparent 0%, #00cfe8 50%, transparent 100%);
    z-index: 3;
    animation: woQrScanLine 2.1s linear infinite;
  }

  @keyframes woQrCornerPulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.55; }
  }

  @keyframes woQrScanLine {
    0% { top: 0; opacity: 1; }
    100% { top: calc(100% - 2px); opacity: .2; }
  }
</style>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('qr-scanner-modal');
    if (!modal) {
      return;
    }

    var region = document.getElementById('qr-scanner-region');
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

    function applyMirrorState() {
      region.classList.toggle('is-mirrored', !!mirrorToggle.checked);
    }

    function extractWorkOrderIdFromPath(pathname) {
      var match = pathname.match(/\/(?:app\/)?invoice\/preview\/([^/?#]+)/i);
      return match ? decodeURIComponent(match[1]) : null;
    }

    function parseWorkOrderIdFromQr(rawText) {
      var text = (rawText || '').trim();
      if (!text) {
        return { id: null, error: 'Prazan QR sadržaj.' };
      }

      if (/^https?:\/\//i.test(text)) {
        try {
          var parsedUrl = new URL(text);
          var idFromUrl = extractWorkOrderIdFromPath(parsedUrl.pathname);
          if (idFromUrl) {
            return { id: idFromUrl, error: null };
          }
          return { id: null, error: 'Neispravan QR format. Očekivan je link na radni nalog.' };
        } catch (e) {
          return { id: null, error: 'QR ne sadrži validan URL radnog naloga.' };
        }
      }

      var pathLike = text.startsWith('/') ? text : ('/' + text);
      var idFromPath = extractWorkOrderIdFromPath(pathLike);
      if (idFromPath) {
        return { id: idFromPath, error: null };
      }

      return { id: null, error: 'Neispravan QR format. Skeniraj QR radnog naloga.' };
    }

    function toWorkOrderPreviewUrl(workOrderId) {
      var path = previewPathPattern.replace('__WORK_ORDER_ID__', encodeURIComponent(workOrderId));
      return new URL(path, window.location.origin).toString();
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
        showError('QR biblioteka nije dostupna. Osvjezi stranicu i pokusaj ponovo.');
        return;
      }

      clearError();
      setStatus('Pokrecem kameru...');

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

    modal.addEventListener('show.bs.modal', function () {
      var backdrop = document.querySelector('.modal-backdrop');
      if (backdrop) {
        backdrop.classList.add('qr-scanner-backdrop');
      }
    });

    modal.addEventListener('shown.bs.modal', function () {
      redirecting = false;
      lastDecodedText = '';
      lastDecodedAt = 0;
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

