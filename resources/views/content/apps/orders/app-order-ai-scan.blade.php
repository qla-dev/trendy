@extends('layouts/contentLayoutMaster')

@section('title', __('locale.Skeniraj narudzbu sa AI'))

@section('page-style')
  <style>
    .order-ai-shell {
      --order-ai-ink: #16344d;
      --order-ai-accent: #0e7a6b;
      --order-ai-border: rgba(18, 52, 77, 0.12);
    }

    .order-ai-hero {
      position: relative;
      overflow: hidden;
      border: 0;
      border-radius: 1.25rem;
      background:
        radial-gradient(circle at top right, rgba(255, 207, 107, 0.28), transparent 32%),
        linear-gradient(135deg, #fdf7eb 0%, #fffefe 46%, #eef7f5 100%);
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

    .order-ai-chip {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      padding: 0.55rem 0.85rem;
      border-radius: 999px;
      background: rgba(22, 52, 77, 0.08);
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
        #ffffff;
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
      color: #607385;
    }

    .order-ai-progress-card,
    .order-ai-result-card {
      border-radius: 1.1rem;
      border: 1px solid var(--order-ai-border);
      box-shadow: 0 16px 32px rgba(16, 31, 48, 0.06);
    }

    .order-ai-progress-track {
      height: 0.9rem;
      border-radius: 999px;
      background: #eef3f7;
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
      display: flex;
      align-items: center;
      gap: 0.85rem;
      padding: 0.85rem 1rem;
      border-radius: 1rem;
      background: #fbfcfd;
      border: 1px solid rgba(22, 52, 77, 0.08);
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

    .order-ai-facts {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
      gap: 0.85rem;
    }

    .order-ai-fact {
      padding: 1rem;
      border-radius: 1rem;
      background: linear-gradient(180deg, #ffffff 0%, #fbfcff 100%);
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

    .order-ai-alert {
      border-radius: 1rem;
      border: 0;
    }

    .order-ai-hidden {
      display: none !important;
    }

    @media (max-width: 767.98px) {
      .order-ai-dropzone {
        min-height: 260px;
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
  data-status-template="{{ route('app-order-ai-scan-status', ['scan' => '__SCAN__']) }}"
  data-csrf="{{ csrf_token() }}"
>
  <div class="row">
    <div class="col-12">
      <div class="card order-ai-hero mb-2">
        <div class="card-body p-2 p-md-3">
          <div class="d-flex flex-column flex-lg-row justify-content-between gap-2">
            <div class="pe-lg-4">
              <span class="order-ai-chip mb-1">
                <i class="fa fa-magic" aria-hidden="true"></i>
                {{ $aiOrderLabel }}
              </span>
              <h2 class="mb-75" style="color:#16344d;">{{ $aiOrderLabel }}</h2>
              <p class="mb-0 order-ai-subtle" style="max-width:720px;">
                Ubaci PDF, sliku ili izvoz dokumenta. Fajl ostaje na istoj stranici, AI odradi ekstrakciju,
                a Pantheon preview payload ostaje lokalno dok korisnik rucno ne potvrdi transfer u Pantheon.
              </p>
            </div>
            <div class="d-flex flex-column justify-content-between">
              <div class="badge rounded-pill bg-light-primary text-primary px-1 py-75">Provider: {{ strtoupper($scanProvider) }}</div>
              <div class="badge rounded-pill bg-light-secondary text-secondary px-1 py-75">Model: {{ $scanModel }}</div>
              <div class="badge rounded-pill {{ $autoTransferEnabled ? 'bg-light-success text-success' : 'bg-light-warning text-warning' }} px-1 py-75">
                {{ $autoTransferEnabled ? 'Auto transfer ukljucen' : 'Pantheon create iskljucen' }}
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
              <div class="d-flex align-items-start justify-content-between gap-1 mb-1">
                <div>
                  <h4 class="mb-25">Status obrade</h4>
                  <p class="mb-0 order-ai-subtle" id="order-ai-progress-label">Cekam upload...</p>
                </div>
                <div class="text-end">
                  <div class="fw-bolder" id="order-ai-progress-percent">0%</div>
                  <small class="text-muted" id="order-ai-file-name"></small>
                </div>
              </div>
              <div class="order-ai-progress-track mb-2">
                <div class="order-ai-progress-bar" id="order-ai-progress-bar"></div>
              </div>

              <div class="order-ai-stage-list" id="order-ai-stage-list">
                <div class="order-ai-stage" data-stage="upload">
                  <span class="order-ai-stage-bullet"></span>
                  <div>
                    <strong>Upload</strong>
                    <div class="small text-muted">Fajl se salje na lokalni staging.</div>
                  </div>
                </div>
                <div class="order-ai-stage" data-stage="extract">
                  <span class="order-ai-stage-bullet"></span>
                  <div>
                    <strong>AI ekstrakcija</strong>
                    <div class="small text-muted">Prompt pretvara dokument u strukturirani payload.</div>
                  </div>
                </div>
                <div class="order-ai-stage" data-stage="transfer">
                  <span class="order-ai-stage-bullet"></span>
                  <div>
                    <strong>{{ $autoTransferEnabled ? 'Pantheon transfer' : 'Pantheon preview' }}</strong>
                    <div class="small text-muted">
                      {{ $autoTransferEnabled ? 'Novi order API ubacuje header i stavke u Pantheon.' : 'Pantheon payload se priprema i loguje lokalno bez kreiranja narudzbe.' }}
                    </div>
                  </div>
                </div>
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

              <div class="table-responsive mb-2 order-ai-hidden" id="order-ai-lines-shell">
                <table class="table table-sm order-ai-lines-table mb-0">
                  <thead>
                    <tr>
                      <th>#</th>
                      <th>Sifra</th>
                      <th class="order-ai-wrap">Naziv</th>
                      <th>Kolicina</th>
                      <th>JM</th>
                      <th>Cijena</th>
                    </tr>
                  </thead>
                  <tbody id="order-ai-lines-body"></tbody>
                </table>
              </div>

              <div class="d-flex flex-wrap gap-1 order-ai-hidden" id="order-ai-actions">
                <button type="button" class="btn btn-primary" id="order-ai-transfer-button">Transfer u bazu</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
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
      const statusTemplate = app.dataset.statusTemplate;
      const csrfToken = app.dataset.csrf;
      const dropzone = document.getElementById('order-ai-dropzone');
      const fileInput = document.getElementById('order-ai-file-input');
      const progressCard = document.getElementById('order-ai-progress-card');
      const progressLabel = document.getElementById('order-ai-progress-label');
      const progressPercent = document.getElementById('order-ai-progress-percent');
      const progressBar = document.getElementById('order-ai-progress-bar');
      const fileNameEl = document.getElementById('order-ai-file-name');
      const progressWarning = document.getElementById('order-ai-progress-warning');
      const resultCard = document.getElementById('order-ai-result-card');
      const resultCaption = document.getElementById('order-ai-result-caption');
      const resultStatus = document.getElementById('order-ai-result-status');
      const facts = document.getElementById('order-ai-facts');
      const warningsBox = document.getElementById('order-ai-warnings');
      const errorBox = document.getElementById('order-ai-error');
      const successBox = document.getElementById('order-ai-success');
      const linesShell = document.getElementById('order-ai-lines-shell');
      const linesBody = document.getElementById('order-ai-lines-body');
      const actions = document.getElementById('order-ai-actions');
      const transferButton = document.getElementById('order-ai-transfer-button');
      const stageNodes = Array.from(document.querySelectorAll('.order-ai-stage'));

      let pollTimer = null;
      let currentScanId = null;
      let uploadProgress = 0;
      let latestStatusPayload = null;

      function escapeHtml(value) {
        return String(value ?? '')
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#039;');
      }

      function setVisible(node, visible) {
        node.classList.toggle('order-ai-hidden', !visible);
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

      function detectStage(status, autoTransfer, progress, step) {
        if (status === 'transferred' || status === 'ready_for_transfer' || status === 'transferring') {
          return 'transfer';
        }
        if (status === 'completed') {
          return autoTransfer ? 'transfer' : 'extract';
        }
        if (status === 'failed' && /pantheon/i.test(String(step || ''))) {
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
          { label: 'Referenca', value: order.external_document_number || '-' },
          { label: 'Doc type', value: order.document_type || '-' },
          { label: 'Valuta', value: order.currency || '-' },
          { label: 'Iznos', value: Number(summary.grand_total || 0).toFixed(2) },
          { label: 'AI krediti', value: Number(statusData.credits_spent || 0).toFixed(2) },
          { label: 'Pantheon kljuc', value: pantheon.key || '-' },
          { label: 'Pantheon prikaz', value: pantheon.view || '-' }
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

        if (!items.length) {
          linesBody.innerHTML = '';
          setVisible(linesShell, false);
          return;
        }

        linesBody.innerHTML = items.map((item) => `
          <tr>
            <td>${escapeHtml(item.line_number || '')}</td>
            <td>${escapeHtml(item.product_code || '-')}</td>
            <td class="order-ai-wrap">${escapeHtml(item.product_name || '-')}</td>
            <td>${escapeHtml(Number(item.quantity || 0).toFixed(2))}</td>
            <td>${escapeHtml(item.unit || '-')}</td>
            <td>${escapeHtml(Number(item.unit_price || 0).toFixed(2))}</td>
          </tr>
        `).join('');

        setVisible(linesShell, true);
      }

      function renderWarnings(warnings) {
        const validWarnings = Array.isArray(warnings) ? warnings.filter(Boolean) : [];
        warningsBox.innerHTML = validWarnings.map((warning) => `<div>${escapeHtml(warning)}</div>`).join('');
        setVisible(warningsBox, validWarnings.length > 0);
      }

      function renderStatus(data) {
        latestStatusPayload = data;
        const payload = data.result || {};
        const autoTransfer = Boolean(data.auto_transfer);
        const effectiveProgress = Math.max(uploadProgress, Number(data.current_progress || 0));
        const stageName = detectStage(data.status, autoTransfer, data.current_progress, data.processing_step);
        const finalizeStage = (data.status === 'completed' && stageName === 'extract' && !autoTransfer)
          || (data.status === 'transferred' && stageName === 'transfer');

        setVisible(resultCard, true);
        resetMessages();
        setProgress(effectiveProgress, data.processing_step || 'AI obrada je u toku.');
        setStageState(stageName, finalizeStage);
        renderFacts(payload, data);
        renderLines(payload);
        renderWarnings(data.warnings || []);

        resultCaption.textContent = data.processing_step || 'Status nije dostupan.';
        resultStatus.textContent = data.status || 'nepoznato';
        setVisible(actions, false);

        if (data.status === 'failed') {
          errorBox.textContent = data.error_message || 'AI obrada nije uspjela.';
          setVisible(errorBox, true);
          return;
        }

        if (data.status === 'completed') {
          if (!autoTransfer) {
            if (data.transfer_ready && data.transfer_preview_error) {
              progressWarning.textContent = 'Pantheon preview priprema nije uspjela, ali i dalje mozes pokusati rucni transfer.';
            } else if (data.transfer_ready && data.transfer_preview_available) {
              progressWarning.textContent = 'Pantheon preview payload je upisan u laravel.log. Provjeri rezultat i pokreni rucni transfer kada budes spreman.';
            } else if (data.transfer_ready) {
              progressWarning.textContent = 'Rezultat je spreman za Pantheon. Pokreni rucni transfer kada budes spreman.';
            } else {
              progressWarning.textContent = 'Ekstrakcija je zavrsena. Pregledaj rezultat i dopuni podatke ako nesto nedostaje.';
            }
            setVisible(progressWarning, true);
            setVisible(actions, Boolean(data.transfer_ready));
          }
          return;
        }

        if (data.status === 'transferred') {
          const orderView = data.pantheon_order && data.pantheon_order.view ? data.pantheon_order.view : data.pantheon_order.key;
          successBox.textContent = orderView
            ? `Narudzba je prebacena u Pantheon kao ${orderView}.`
            : 'Narudzba je prebacena u Pantheon.';
          setVisible(successBox, true);
          setVisible(actions, false);
        }
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
          errorBox.textContent = 'Status AI obrade nije dostupan.';
          setVisible(errorBox, true);
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

        resetMessages();
        currentScanId = null;
        latestStatusPayload = null;
        uploadProgress = 0;
        fileNameEl.textContent = file.name;
        setVisible(progressCard, true);
        setVisible(resultCard, false);
        setVisible(actions, false);
        setProgress(0, 'Priprema lokalnog staging uploada...');
        setStageState('upload', false);

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
          setProgress(uploadProgress, uploadProgress >= 100 ? 'Upload zavrsen, pokrecem AI obradu...' : 'Dokument se ucitava na server...');
          setStageState('upload', uploadProgress >= 100);
        });

        xhr.addEventListener('load', function () {
          if (xhr.status < 200 || xhr.status >= 300) {
            const response = xhr.response || {};
            errorBox.textContent = response.message || 'Upload nije uspio.';
            setVisible(errorBox, true);
            return;
          }

          const response = xhr.response || {};
          currentScanId = response.scan_id;
          uploadProgress = 100;
          setProgress(100, 'Upload zavrsen. Dokument ceka AI ekstrakciju.');
          setStageState('extract', false);
          startPolling(response.scan_id);
        });

        xhr.addEventListener('error', function () {
          errorBox.textContent = 'Greska pri uploadu dokumenta.';
          setVisible(errorBox, true);
        });

        xhr.send(formData);
      }

      async function transferToPantheon() {
        if (!currentScanId) {
          return;
        }

        transferButton.disabled = true;
        transferButton.textContent = 'Transfer u toku...';
        resetMessages();

        try {
          const response = await fetch(transferUrl, {
            method: 'POST',
            headers: {
              'Accept': 'application/json',
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': csrfToken,
              'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ scan_id: currentScanId }),
          });

          const payload = await response.json();

          if (!response.ok) {
            throw new Error(payload.message || 'Pantheon transfer nije uspio.');
          }

          if (latestStatusPayload) {
            latestStatusPayload.status = 'transferred';
            latestStatusPayload.processing_step = 'Narudzba je rucno prebacena u Pantheon.';
            latestStatusPayload.pantheon_order = {
              key: payload.data ? payload.data.pantheon_order_key : '',
              view: payload.data ? payload.data.pantheon_order_view : '',
              qid: payload.data ? payload.data.pantheon_order_qid : null,
            };
            renderStatus(latestStatusPayload);
          }
        } catch (error) {
          errorBox.textContent = error.message || 'Pantheon transfer nije uspio.';
          setVisible(errorBox, true);
          setVisible(actions, true);
        } finally {
          transferButton.disabled = false;
        transferButton.textContent = 'Transfer u bazu';
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

      transferButton.addEventListener('click', transferToPantheon);

      setProgress(0, 'Cekam upload...');
      setStageState('upload', false);
    })();
  </script>
@endsection
