@php
  $productsEndpoint = (string) ($productsFetchUrl ?? '');
  $bomEndpoint = (string) ($bomFetchUrl ?? '');
  $plannedEndpoint = (string) ($plannedConsumptionStoreUrl ?? '');
  $defaultProduct = trim((string) ($defaultProductIdent ?? ''));
@endphp

<div
  class="modal fade"
  id="sirovina-scanner-modal"
  tabindex="-1"
  aria-labelledby="sirovina-scanner-modal-label"
  aria-hidden="true"
  data-bs-backdrop="static"
  data-bs-keyboard="false"
  data-products-url="{{ $productsEndpoint }}"
  data-bom-url="{{ $bomEndpoint }}"
  data-save-url="{{ $plannedEndpoint }}"
  data-default-product="{{ $defaultProduct }}"
  data-csrf-token="{{ csrf_token() }}"
>
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content wo-bom-modal-content">
      <div class="modal-body p-0">
        <div class="wo-bom-modal-shell">
          <div class="wo-bom-modal-header">
            <h4 class="mb-0 text-white" id="sirovina-scanner-modal-label">
              Planirana potrošnja za RN <span id="sirovina-rn-number">-</span>
            </h4>
            <p class="mb-0 wo-bom-modal-subtitle">Izaberite proizvod, zatim stavke sastavnice i unesite količinu.</p>
          </div>

          <div class="row g-4 wo-bom-split-row">
            <div class="col-12 col-lg-6">
              <div class="wo-bom-card wo-bom-dummy-qr-card">
                <h5 class="text-white mb-1">Skenirajte QR kod radnog naloga</h5>

                <div class="qr-scanner-container position-relative wo-bom-dummy-qr-wrap">
                  <div id="sirovina-qr-scanner-frame" class="qr-scanner-frame position-relative">
                    <div id="sirovina-qr-scanner-region" class="position-absolute" style="inset: 0;"></div>

                    <div class="qr-corner qr-corner-top-left"></div>
                    <div class="qr-corner qr-corner-top-right"></div>
                    <div class="qr-corner qr-corner-bottom-left"></div>
                    <div class="qr-corner qr-corner-bottom-right"></div>

                    <div class="qr-scan-line"></div>

                    <div class="qr-grid"></div>
                  </div>
                </div>

                <div class="wo-qr-controls-wrap wo-bom-dummy-qr-controls">
                  <div class="wo-qr-controls-panel mt-2 mb-1">
                    <div class="wo-qr-controls-row">
                      <div class="wo-qr-control-block">
                        <span class="wo-qr-control-kicker">Prikaz</span>
                        <div class="form-check form-switch mb-0 d-flex align-items-center">
                          <input class="form-check-input me-50" type="checkbox" id="sirovina-qr-mirror-toggle">
                          <label class="form-check-label mb-0" for="sirovina-qr-mirror-toggle">Mirror</label>
                        </div>
                      </div>
                        <button type="button" class="btn btn-sm wo-qr-btn wo-qr-btn-subtle">
                          <i class="fa fa-refresh me-50"></i> Ponovo pokrenite
                        </button>
                    </div>

                    <div class="wo-qr-controls-row wo-qr-camera-controls-row">
                      <span class="wo-qr-camera-icon" aria-hidden="true"><i class="fa fa-camera"></i></span>
                      <label class="visually-hidden" for="sirovina-qr-camera-select">Kamera</label>
                      <div class="wo-qr-camera-row">
                        <select class="form-select form-select-sm" id="sirovina-qr-camera-select">
                          <option value="">Automatski odabir</option>
                        </select>
                        <button type="button" class="btn btn-sm wo-qr-btn wo-qr-btn-primary">Primijenite</button>
                      </div>
                    </div>
                  </div>

                  <div class="wo-qr-feedback-wrap">
                    <div class="small wo-qr-status">Dummy prikaz skenera za dodavanje sirovine.</div>
                    <div class="small wo-qr-error text-danger d-none"></div>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-12 col-lg-6 wo-bom-right-col">
              <div class="wo-bom-card wo-bom-main-card">
                <div class="wo-bom-head-block">
                  <div class="wo-bom-grid">
                    <div class="wo-bom-field">
                      <label class="form-label wo-bom-section-title mb-50" for="bom-product-select">Proizvod</label>
                      <select class="form-select form-select-sm" id="bom-product-select">
                        <option value="">Izaberite proizvod</option>
                      </select>
                    </div>
                  </div>
                  <p id="bom-status" class="small mb-0 text-white-50">Sastavnica se automatski učitava nakon odabira proizvoda.</p>
                  <p id="bom-error" class="small mb-0 text-danger d-none"></p>
                </div>
                <div class="wo-bom-table-section">
                  <h6 class="wo-bom-section-title mb-0">Sastavnice proizvoda</h6>

                  <div class="table-responsive wo-bom-table-wrap">
                    <table class="table table-sm mb-0 wo-bom-table">
                      <thead>
                        <tr>
                          <th class="text-center" style="width: 46px;">#</th>
                          <th style="width: 80px;">Poz</th>
                          <th style="width: 200px;">Komponenta</th>
                          <th>Opis</th>
                          <th style="width: 130px;" class="text-end">anGrossQty</th>
                          <th style="width: 120px;" class="text-center">Tip</th>
                        </tr>
                      </thead>
                      <tbody id="bom-components-body">
                        <tr>
                          <td colspan="6" class="text-center text-white-50 py-2">Sastavnica nije učitana.</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>

                <div class="wo-bom-summary">
                  <span>Odabrano: <strong id="bom-selected-count">0</strong></span>
                  <span>Ukupno: <strong id="bom-total-count">0</strong></span>
                </div>

                <div class="wo-bom-field wo-bom-description-last">
                  <label class="form-label wo-bom-section-title mb-50" for="bom-description-input">Opis (opciono)</label>
                  <textarea
                    class="form-control"
                    id="bom-description-input"
                    rows="3"
                    maxlength="500"
                    placeholder="Unesite opis koji želite sačuvati uz planiranu potrošnju"
                  ></textarea>
                </div>
              </div>

              <div class="wo-bom-modal-footer">
                <button type="button" class="btn btn-secondary wo-scanner-close-fab" data-bs-dismiss="modal" aria-label="Zatvori">
                  <i class="fa fa-times me-50"></i> Zatvori
                </button>
                <button type="button" class="btn btn-success" id="bom-open-quantity-btn" disabled>
                  <i class="fa fa-check me-50"></i> Nastavite na unos količine
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
  .sirovina-scanner-backdrop {
    background-color: rgba(0, 0, 0, 0.95) !important;
    opacity: 1 !important;
    backdrop-filter: blur(2px);
  }

  #sirovina-scanner-modal .wo-bom-modal-content {
    border: none;
    background: transparent;
    box-shadow: none;
  }

  #sirovina-scanner-modal {
    --qr-accent: rgba(74, 179, 148, 0.78);
    --qr-accent-soft: rgba(74, 179, 148, 0.24);
    --qr-border-muted: rgba(189, 199, 221, 0.32);
    --qr-text-soft: #cfd7ee;
    --qr-text-main: #e8edf9;
  }

  #sirovina-scanner-modal .modal-dialog {
    max-width: 1400px;
    margin: 1.75rem auto !important;
  }

  #sirovina-scanner-modal .wo-bom-modal-shell {
    border-radius: 0;
    border: none;
    background: transparent;
    padding: 0 1rem;
    max-width: 1320px;
    margin: 0 auto;
  }

  #sirovina-scanner-modal .wo-bom-modal-header {
    margin: 0 auto 1.4rem;
    max-width: 1180px;
    width: 100%;
    text-align: center;
  }

  #sirovina-scanner-modal .wo-bom-split-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    column-gap: 3.5rem;
    row-gap: 1.6rem;
    width: 100%;
    max-width: 1180px;
    margin: 0 auto !important;
  }

  #sirovina-scanner-modal .wo-bom-split-row > [class*='col-'] {
    display: flex;
    flex-direction: column;
    width: 100%;
    max-width: none;
    padding: 0 !important;
  }

  #sirovina-scanner-modal .wo-bom-modal-subtitle {
    margin-top: 0.35rem;
    color: #aebbd8;
    font-size: 0.88rem;
  }

  #sirovina-scanner-modal .wo-bom-card {
    border: none;
    border-radius: 0;
    background: transparent;
    padding: 0;
  }

  #sirovina-scanner-modal .wo-bom-dummy-qr-card,
  #sirovina-scanner-modal .wo-bom-main-card {
    flex: 1 1 auto;
    width: 100%;
  }

  #sirovina-scanner-modal .wo-bom-dummy-qr-card > h5 {
    text-align: center;
    margin-bottom: 0.9rem !important;
  }

  #sirovina-scanner-modal .wo-bom-main-card {
    display: flex;
    flex-direction: column;
    gap: 1.35rem;
  }

  #sirovina-scanner-modal .wo-bom-main-card .wo-bom-grid,
  #sirovina-scanner-modal .wo-bom-main-card .wo-bom-head-block,
  #sirovina-scanner-modal .wo-bom-main-card .wo-bom-table-section,
  #sirovina-scanner-modal .wo-bom-main-card .wo-bom-section-title,
  #sirovina-scanner-modal .wo-bom-main-card .wo-bom-table-wrap,
  #sirovina-scanner-modal .wo-bom-main-card .wo-bom-summary,
  #sirovina-scanner-modal .wo-bom-modal-footer {
    width: 100%;
  }

  #sirovina-scanner-modal .wo-bom-head-block {
    display: flex;
    flex-direction: column;
    gap: 0.42rem;
  }

  #sirovina-scanner-modal .wo-bom-section-title {
    color: #bad0ff;
    font-size: 0.82rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    font-weight: 600;
  }

  #sirovina-scanner-modal .wo-bom-table-section {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
  }

  #sirovina-scanner-modal .wo-bom-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.7rem;
  }

  #sirovina-scanner-modal .wo-bom-dummy-qr-wrap {
    max-width: 400px;
    margin: 0 auto;
  }

  #sirovina-scanner-modal #sirovina-qr-scanner-frame {
    width: 100%;
    padding-top: 100%;
    border-radius: 12px;
    border: 1px solid var(--qr-accent-soft);
    background: rgba(255, 255, 255, 0.045);
    overflow: hidden;
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.03);
  }

  @keyframes scanLineSirovina {
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

  @keyframes cornerPulseSirovina {
    0%, 100% {
      opacity: 1;
      transform: scale(1);
    }
    50% {
      opacity: 0.7;
      transform: scale(1.1);
    }
  }

  #sirovina-scanner-modal #sirovina-qr-scanner-frame .qr-corner {
    position: absolute;
    width: 34px;
    height: 34px;
    border-color: var(--qr-accent);
    animation: cornerPulseSirovina 2s ease-in-out infinite;
    opacity: 0.85;
    z-index: 3;
  }

  #sirovina-scanner-modal #sirovina-qr-scanner-frame .qr-corner-top-left {
    top: 0;
    left: 0;
    border-top: 2px solid var(--qr-accent);
    border-left: 2px solid var(--qr-accent);
  }

  #sirovina-scanner-modal #sirovina-qr-scanner-frame .qr-corner-top-right {
    top: 0;
    right: 0;
    border-top: 2px solid var(--qr-accent);
    border-right: 2px solid var(--qr-accent);
    animation-delay: 0.5s;
  }

  #sirovina-scanner-modal #sirovina-qr-scanner-frame .qr-corner-bottom-left {
    bottom: 0;
    left: 0;
    border-bottom: 2px solid var(--qr-accent);
    border-left: 2px solid var(--qr-accent);
    animation-delay: 1s;
  }

  #sirovina-scanner-modal #sirovina-qr-scanner-frame .qr-corner-bottom-right {
    bottom: 0;
    right: 0;
    border-bottom: 2px solid var(--qr-accent);
    border-right: 2px solid var(--qr-accent);
    animation-delay: 1.5s;
  }

  #sirovina-scanner-modal #sirovina-qr-scanner-frame .qr-scan-line {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 1.5px;
    background: linear-gradient(90deg, transparent, var(--qr-accent), transparent);
    animation: scanLineSirovina 2s linear infinite;
    opacity: 0.7;
    z-index: 2;
  }

  @keyframes gridMoveSirovina {
    0% {
      background-position: 0 0;
    }
    100% {
      background-position: 20px 20px;
    }
  }

  #sirovina-scanner-modal #sirovina-qr-scanner-frame .qr-grid {
    position: absolute;
    inset: 0;
    background-image:
      linear-gradient(rgba(74, 179, 148, 0.08) 1px, transparent 1px),
      linear-gradient(90deg, rgba(74, 179, 148, 0.08) 1px, transparent 1px);
    background-size: 20px 20px;
    opacity: 0.22;
    animation: gridMoveSirovina 3s linear infinite;
  }

  #sirovina-scanner-modal .wo-bom-dummy-qr-controls {
    max-width: 400px;
    margin: 0 auto;
    text-align: left;
  }

  #sirovina-scanner-modal .wo-qr-controls-panel {
    border: 1px solid rgba(170, 183, 212, 0.24);
    border-radius: 10px;
    background: rgba(14, 19, 30, 0.68);
    padding: 0.72rem;
  }

  #sirovina-scanner-modal .wo-qr-controls-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
  }

  #sirovina-scanner-modal .wo-qr-controls-row + .wo-qr-controls-row {
    margin-top: 0.7rem;
    padding-top: 0.7rem;
    border-top: 1px solid rgba(170, 183, 212, 0.18);
  }

  #sirovina-scanner-modal .wo-qr-control-block {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.36rem;
  }

  #sirovina-scanner-modal .wo-qr-control-kicker {
    font-size: 0.72rem;
    line-height: 1;
    text-transform: uppercase;
    letter-spacing: 0.045em;
    color: #98a8ce;
    font-weight: 600;
  }

  #sirovina-scanner-modal .form-check-label {
    color: var(--qr-text-soft) !important;
    font-size: 0.95rem;
    font-weight: 500;
    cursor: pointer;
  }

  #sirovina-scanner-modal .wo-qr-camera-row {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    flex: 1 1 auto;
  }

  #sirovina-scanner-modal .wo-qr-camera-icon {
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

  #sirovina-scanner-modal .wo-qr-camera-row .form-select {
    flex: 1 1 auto;
  }

  #sirovina-scanner-modal .wo-qr-btn {
    min-height: 2.3rem;
    border-radius: 8px;
    padding: 0.42rem 0.85rem;
    font-weight: 600;
    letter-spacing: 0.01em;
    box-shadow: none !important;
  }

  #sirovina-scanner-modal .wo-qr-btn-subtle {
    border: 1px solid rgba(177, 189, 216, 0.34);
    background-color: rgba(255, 255, 255, 0.035);
    color: #dde5fa;
  }

  #sirovina-scanner-modal .wo-qr-btn-subtle:hover,
  #sirovina-scanner-modal .wo-qr-btn-subtle:focus {
    border-color: rgba(205, 215, 238, 0.55);
    background-color: rgba(255, 255, 255, 0.1);
    color: #ffffff;
  }

  #sirovina-scanner-modal .wo-qr-btn-primary {
    border: 1px solid rgba(74, 179, 148, 0.5);
    background-color: rgba(74, 179, 148, 0.12);
    color: #dbfff2;
    min-width: 7.4rem;
  }

  #sirovina-scanner-modal .wo-qr-btn-primary:hover,
  #sirovina-scanner-modal .wo-qr-btn-primary:focus {
    border-color: rgba(74, 179, 148, 0.75);
    background-color: rgba(74, 179, 148, 0.2);
    color: #ffffff;
  }

  #sirovina-scanner-modal .wo-qr-status {
    padding-left: 0;
    line-height: 1.25rem;
    color: #bfc9e5 !important;
    text-align: center;
    margin: 0;
  }

  #sirovina-scanner-modal .wo-qr-error {
    margin: 0;
    text-align: center;
    line-height: 1.25rem;
  }

  #sirovina-scanner-modal .wo-qr-feedback-wrap {
    min-height: 3rem;
    margin: 0.5rem 0 0.75rem;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    gap: 0.2rem;
  }

  #sirovina-scanner-modal .wo-bom-description-last {
    margin-top: 0;
  }

  #sirovina-scanner-modal .wo-bom-description-last textarea {
    min-height: 92px;
    resize: vertical;
  }

  #sirovina-scanner-modal .wo-bom-table-wrap {
    margin-top: 0;
    border: 1px solid rgba(177, 189, 216, 0.22);
    border-radius: 8px;
    overflow: hidden;
    max-height: 330px;
    width: 100%;
  }

  #sirovina-scanner-modal .wo-bom-table {
    color: #e8edfb;
  }

  #sirovina-scanner-modal .wo-bom-table thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: rgba(23, 30, 48, 0.96);
    color: #bad0ff;
    font-size: 0.76rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    border-bottom-color: rgba(170, 183, 214, 0.28);
  }

  #sirovina-scanner-modal .wo-bom-table tbody td {
    border-top-color: rgba(170, 183, 214, 0.14);
    vertical-align: middle;
  }

  #sirovina-scanner-modal .wo-bom-table tbody tr:hover td {
    background: rgba(255, 255, 255, 0.04);
  }

  #sirovina-scanner-modal .wo-bom-summary {
    margin-top: 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.86rem;
    color: #d4def8;
  }

  #sirovina-scanner-modal .wo-bom-modal-footer {
    margin-top: 0.95rem;
    display: flex;
    justify-content: flex-end;
    gap: 0.85rem;
  }

  #sirovina-scanner-modal .wo-scanner-close-fab {
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

  #sirovina-scanner-modal .wo-scanner-close-fab:hover,
  #sirovina-scanner-modal .wo-scanner-close-fab:focus {
    border-color: rgba(223, 231, 248, 0.7);
    background-color: rgba(18, 24, 37, 0.94);
    color: #ffffff;
  }

  #sirovina-scanner-modal .form-control,
  #sirovina-scanner-modal .form-select {
    background-color: rgba(16, 22, 35, 0.85);
    color: #e8edfb;
    border-color: rgba(168, 179, 204, 0.34);
  }

  #sirovina-scanner-modal .form-control::placeholder {
    color: #9fb0d6;
  }

  #sirovina-scanner-modal .form-control:focus,
  #sirovina-scanner-modal .form-select:focus {
    border-color: rgba(74, 179, 148, 0.64);
    box-shadow: 0 0 0 0.12rem rgba(74, 179, 148, 0.15);
  }

  #sirovina-scanner-modal .form-check-input {
    border-color: rgba(177, 189, 216, 0.35);
    background-color: rgba(16, 22, 35, 0.85);
    cursor: pointer;
  }

  #sirovina-scanner-modal .form-check-input:checked {
    background-color: #28c76f;
    border-color: #28c76f;
  }

  #sirovina-scanner-modal .modal-body {
    background-color: unset!important;
  }

  @media (max-width: 991.98px) {
    #sirovina-scanner-modal .wo-bom-split-row {
      grid-template-columns: 1fr;
      column-gap: 0;
      max-width: none;
    }

    #sirovina-scanner-modal .wo-bom-right-col {
      display: none !important;
    }
  }

  @media (max-width: 767.98px) {
    #sirovina-scanner-modal .wo-scanner-close-fab {
      top: max(0.75rem, env(safe-area-inset-top));
      left: max(0.75rem, env(safe-area-inset-left));
      padding: 0.5rem 0.82rem;
    }

    #sirovina-scanner-modal .wo-bom-modal-shell {
      padding: 0;
    }

    #sirovina-scanner-modal .wo-bom-modal-header {
      max-width: none;
    }

    #sirovina-scanner-modal .wo-bom-split-row {
      grid-template-columns: 1fr;
      column-gap: 0;
      row-gap: 1rem;
      max-width: none;
    }

    #sirovina-scanner-modal .wo-bom-grid {
      grid-template-columns: 1fr;
    }

    #sirovina-scanner-modal .wo-bom-modal-footer {
      flex-direction: column;
      align-items: stretch;
    }

    #sirovina-scanner-modal .wo-qr-controls-row {
      flex-direction: column;
      align-items: stretch;
    }

    #sirovina-scanner-modal .wo-qr-camera-row {
      flex-direction: column;
      align-items: stretch;
    }
  }
</style>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    var modalEl = document.getElementById('sirovina-scanner-modal');
    if (!modalEl) {
      return;
    }

    var productsUrl = modalEl.getAttribute('data-products-url') || '';
    var bomUrl = modalEl.getAttribute('data-bom-url') || '';
    var saveUrl = modalEl.getAttribute('data-save-url') || '';
    var csrfToken = modalEl.getAttribute('data-csrf-token') || '';
    var defaultProduct = modalEl.getAttribute('data-default-product') || '';

    var rnNumberEl = document.getElementById('sirovina-rn-number');
    var statusEl = document.getElementById('bom-status');
    var errorEl = document.getElementById('bom-error');
    var productSelect = document.getElementById('bom-product-select');
    var descriptionInput = document.getElementById('bom-description-input');
    var componentsBody = document.getElementById('bom-components-body');
    var selectedCountEl = document.getElementById('bom-selected-count');
    var totalCountEl = document.getElementById('bom-total-count');
    var openQuantityBtn = document.getElementById('bom-open-quantity-btn');

    var confirmModalEl = document.getElementById('confirm-weight-modal');
    var confirmLabelEl = document.getElementById('scanned-material-name');
    var quantityInput = document.getElementById('weight-input');
    var quantityUnitSelect = document.getElementById('weight-unit-select');
    var confirmSaveBtn = document.getElementById('confirm-add-sirovina-btn');

    var state = {
      initialized: false,
      products: [],
      bomRows: [],
      selectedKeys: new Set(),
      loadingProducts: false,
      loadingBom: false,
      saving: false
    };

    function setStatus(text, tone) {
      if (!statusEl) {
        return;
      }

      statusEl.textContent = text;
      statusEl.classList.remove('text-success', 'text-warning', 'text-danger', 'text-white-50');

      if (tone === 'success') {
        statusEl.classList.add('text-success');
      } else if (tone === 'warning') {
        statusEl.classList.add('text-warning');
      } else if (tone === 'danger') {
        statusEl.classList.add('text-danger');
      } else {
        statusEl.classList.add('text-white-50');
      }
    }

    function showError(text) {
      if (!errorEl) {
        return;
      }

      if (!text) {
        errorEl.textContent = '';
        errorEl.classList.add('d-none');
        return;
      }

      errorEl.textContent = text;
      errorEl.classList.remove('d-none');
    }

    function notify(icon, title, text) {
      if (window.Swal && typeof window.Swal.fire === 'function') {
        window.Swal.fire({
          icon: icon,
          title: title,
          text: text
        });
        return;
      }

      window.alert(title + ': ' + text);
    }

    function applyBackdrop() {
      var backdrop = document.querySelector('.modal-backdrop');
      if (!backdrop) {
        return;
      }

      backdrop.classList.add('sirovina-scanner-backdrop');
      backdrop.style.backgroundColor = 'rgba(0, 0, 0, 0.95)';
      backdrop.style.opacity = '1';
    }

    function parseResponse(response) {
      return response.json().catch(function () {
        return {};
      }).then(function (payload) {
        if (!response.ok) {
          var message = payload && payload.message ? payload.message : 'Zahtjev nije uspio.';
          throw new Error(message);
        }

        return payload || {};
      });
    }

    function buildUrl(url, params) {
      var fullUrl = new URL(url, window.location.origin);

      Object.keys(params || {}).forEach(function (key) {
        var value = params[key];

        if (value === null || value === undefined || value === '') {
          return;
        }

        fullUrl.searchParams.set(key, value);
      });

      return fullUrl.toString();
    }

    function bomKey(lineNo, componentId) {
      return String(lineNo || 0) + '|' + String(componentId || '').trim().toLowerCase();
    }

    function selectedRows() {
      return state.bomRows.filter(function (row) {
        return state.selectedKeys.has(bomKey(row.anNo, row.acIdentChild));
      }).map(function (row) {
        return {
          acIdentChild: row.acIdentChild,
          anNo: row.anNo
        };
      });
    }

    function updateSelectionSummary() {
      var selectedCount = state.selectedKeys.size;
      var totalCount = state.bomRows.length;

      if (selectedCountEl) {
        selectedCountEl.textContent = String(selectedCount);
      }

      if (totalCountEl) {
        totalCountEl.textContent = String(totalCount);
      }

      if (openQuantityBtn) {
        openQuantityBtn.disabled = selectedCount === 0;
      }
    }

    function renderProducts() {
      if (!productSelect) {
        return;
      }

      var previousValue = productSelect.value || '';
      productSelect.innerHTML = '<option value="">Izaberite proizvod</option>';

      state.products.forEach(function (product) {
        var option = document.createElement('option');
        option.value = product.acIdent || '';
        option.textContent = product.acIdentTrimmed || product.acIdent || '';
        productSelect.appendChild(option);
      });

      if (previousValue && state.products.some(function (row) { return (row.acIdent || '') === previousValue; })) {
        productSelect.value = previousValue;
        return;
      }

      if (defaultProduct && state.products.some(function (row) { return (row.acIdent || '') === defaultProduct; })) {
        productSelect.value = defaultProduct;
        return;
      }

      if (state.products.length > 0) {
        productSelect.value = state.products[0].acIdent || '';
      }
    }

    function renderBomRows() {
      if (!componentsBody) {
        return;
      }

      if (!Array.isArray(state.bomRows) || state.bomRows.length === 0) {
        componentsBody.innerHTML = '<tr><td colspan="6" class="text-center text-white-50 py-2">Nema stavki sastavnice za odabrani proizvod.</td></tr>';
        updateSelectionSummary();
        return;
      }

      var html = state.bomRows.map(function (row, index) {
        var key = bomKey(row.anNo, row.acIdentChild);
        var checked = state.selectedKeys.has(key) ? 'checked' : '';
        var lineNo = row.anNo || 0;
        var componentId = row.acIdentChild || '';
        var descr = row.acDescr || '-';
        var baseQty = Number(row.anGrossQty || 0);
        var operationType = row.acOperationType || '';

        return '' +
          '<tr>' +
            '<td class="text-center">' +
              '<input type="checkbox" class="form-check-input bom-component-checkbox" ' +
              'data-key="' + key.replace(/"/g, '&quot;') + '" ' +
              'data-no="' + String(lineNo).replace(/"/g, '&quot;') + '" ' +
              'data-ident="' + componentId.replace(/"/g, '&quot;') + '" ' +
              checked + '>' +
            '</td>' +
            '<td>' + lineNo + '</td>' +
            '<td class="fw-semibold">' + componentId + '</td>' +
            '<td>' + descr + '</td>' +
            '<td class="text-end">' + baseQty.toFixed(4).replace(/0+$/, '').replace(/\.$/, '') + '</td>' +
            '<td class="text-center">' + operationType + '</td>' +
          '</tr>';
      }).join('');

      componentsBody.innerHTML = html;
      updateSelectionSummary();
    }

    function loadProducts() {
      if (!productsUrl || state.loadingProducts) {
        return Promise.resolve();
      }

      state.loadingProducts = true;
      showError('');
      setStatus('Učitavam proizvode...');

      var url = buildUrl(productsUrl, {});

      return fetch(url, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
        .then(parseResponse)
        .then(function (payload) {
          state.products = Array.isArray(payload.data) ? payload.data.slice(0, 100) : [];
          renderProducts();

          if (state.products.length === 0) {
            setStatus('Nema proizvoda za odabrani kriterij.', 'warning');
            return;
          }

          setStatus('Proizvodi učitani.', 'success');
        })
        .catch(function (error) {
          state.products = [];
          renderProducts();
          setStatus('Ne mogu učitati proizvode.', 'danger');
          showError(error && error.message ? error.message : 'Greška pri učitavanju proizvoda.');
        })
        .finally(function () {
          state.loadingProducts = false;
        });
    }

    function loadBomForSelectedProduct() {
      var productId = (productSelect && productSelect.value) ? productSelect.value : '';

      if (!productId) {
        setStatus('Izaberite proizvod prije učitavanja sastavnice.', 'warning');
        return Promise.resolve();
      }

      if (!bomUrl || state.loadingBom) {
        return Promise.resolve();
      }

      state.loadingBom = true;
      showError('');
      setStatus('Učitavam sastavnicu...');

      return fetch(buildUrl(bomUrl, { product_id: productId }), {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
        .then(parseResponse)
        .then(function (payload) {
          state.bomRows = Array.isArray(payload.data) ? payload.data.slice(0, 100) : [];
          state.selectedKeys.clear();
          renderBomRows();

          if (state.bomRows.length === 0) {
            setStatus('Sastavnica nije pronađena za odabrani proizvod.', 'warning');
            return;
          }

          setStatus('Sastavnica učitana. Označite komponente koje želite planirati.', 'success');
        })
        .catch(function (error) {
          state.bomRows = [];
          state.selectedKeys.clear();
          renderBomRows();
          setStatus('Ne mogu učitati sastavnicu.', 'danger');
          showError(error && error.message ? error.message : 'Greška pri učitavanju sastavnice.');
        })
        .finally(function () {
          state.loadingBom = false;
        });
    }

    function openQuantityModal() {
      var selected = selectedRows();

      if (selected.length === 0) {
        setStatus('Izaberite barem jednu komponentu.', 'warning');
        return;
      }

      if (!confirmModalEl || !window.bootstrap || !window.bootstrap.Modal) {
        notify('error', 'Modal nije dostupan', 'Ne mogu otvoriti unos količine.');
        return;
      }

      if (confirmLabelEl) {
        confirmLabelEl.textContent = selected.length + ' komponenti';
      }

      if (quantityInput && (!quantityInput.value || Number(quantityInput.value) <= 0)) {
        quantityInput.value = '1';
      }

      var confirmModal = window.bootstrap.Modal.getOrCreateInstance(confirmModalEl);
      confirmModal.show();

      if (quantityInput) {
        window.setTimeout(function () {
          quantityInput.focus();
          quantityInput.select();
        }, 120);
      }
    }

    function savePlannedConsumption() {
      if (state.saving) {
        return;
      }

      var selected = selectedRows();
      var productId = (productSelect && productSelect.value) ? productSelect.value : '';
      var quantity = quantityInput ? Number(quantityInput.value || 0) : 0;
      var quantityUnit = quantityUnitSelect ? String(quantityUnitSelect.value || 'AUTO').toUpperCase() : 'AUTO';
      var description = descriptionInput ? String(descriptionInput.value || '').trim() : '';

      if (!saveUrl) {
        notify('error', 'Nedostaje endpoint', 'Snimanje planirane potrošnje nije dostupno.');
        return;
      }

      if (!productId) {
        notify('warning', 'Nedostaje proizvod', 'Izaberite proizvod prije snimanja.');
        return;
      }

      if (selected.length === 0) {
        notify('warning', 'Nema komponenti', 'Izaberite barem jednu komponentu.');
        return;
      }

      if (!Number.isFinite(quantity) || quantity <= 0) {
        notify('warning', 'Neispravna količina', 'Unesite količinu veću od 0.');
        return;
      }

      state.saving = true;
      if (confirmSaveBtn) {
        confirmSaveBtn.disabled = true;
        confirmSaveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-50" role="status" aria-hidden="true"></span> Snimam';
      }

      fetch(saveUrl, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
          product_id: productId,
          quantity: quantity,
          quantity_unit: quantityUnit,
          description: description,
          components: selected
        })
      })
        .then(parseResponse)
        .then(function (payload) {
          var savedCount = payload && payload.data ? Number(payload.data.saved_count || 0) : 0;
          var message = payload && payload.message ? payload.message : 'Planirana potrošnja je sačuvana.';

          notify('success', 'Uspješno', message + ' (Stavki: ' + savedCount + ')');
          setStatus('Planirana potrošnja je sačuvana.', 'success');

          if (confirmModalEl && window.bootstrap && window.bootstrap.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(confirmModalEl).hide();
          }

          window.setTimeout(function () {
            window.location.reload();
          }, 150);
        })
        .catch(function (error) {
          notify('error', 'Greška', error && error.message ? error.message : 'Snimanje nije uspjelo.');
        })
        .finally(function () {
          state.saving = false;
          if (confirmSaveBtn) {
            confirmSaveBtn.disabled = false;
            confirmSaveBtn.innerHTML = '<i class="fa fa-check me-2"></i> Sačuvaj planiranu potrošnju';
          }
        });
    }

    if (componentsBody) {
      componentsBody.addEventListener('change', function (event) {
        var target = event.target;

        if (!target || !target.classList.contains('bom-component-checkbox')) {
          return;
        }

        var key = target.getAttribute('data-key') || '';

        if (!key) {
          return;
        }

        if (target.checked) {
          state.selectedKeys.add(key);
        } else {
          state.selectedKeys.delete(key);
        }

        updateSelectionSummary();
      });
    }

    if (productSelect) {
      productSelect.addEventListener('change', function () {
        loadBomForSelectedProduct();
      });
    }

    if (openQuantityBtn) {
      openQuantityBtn.addEventListener('click', function () {
        openQuantityModal();
      });
    }

    if (confirmSaveBtn) {
      confirmSaveBtn.addEventListener('click', function () {
        savePlannedConsumption();
      });
    }

    var backdropObserver = new MutationObserver(function () {
      if (modalEl.classList.contains('show')) {
        applyBackdrop();
      }
    });

    backdropObserver.observe(document.body, {
      childList: true,
      subtree: true
    });

    modalEl.addEventListener('show.bs.modal', function () {
      var invoiceNumberNode = document.querySelector('.invoice-number');
      if (rnNumberEl) {
        rnNumberEl.textContent = invoiceNumberNode ? invoiceNumberNode.textContent.trim() : '-';
      }

      window.setTimeout(applyBackdrop, 40);
    });

    modalEl.addEventListener('shown.bs.modal', function () {
      applyBackdrop();

      if (!productsUrl || !bomUrl || !saveUrl) {
        setStatus('Skenirajte i učitajte validan radni nalog prije planiranja potrošnje.', 'warning');
        showError('Nedostaju API rute za planiranu potrošnju.');
        return;
      }

      if (state.initialized) {
        if (productSelect && productSelect.value) {
          loadBomForSelectedProduct();
        }
        return;
      }

      state.initialized = true;
      loadProducts().then(function () {
        if (productSelect && productSelect.value) {
          loadBomForSelectedProduct();
        }
      });
    });

    modalEl.addEventListener('hidden.bs.modal', function () {
      showError('');
      var backdrop = document.querySelector('.modal-backdrop');
      if (backdrop) {
        backdrop.classList.remove('sirovina-scanner-backdrop');
      }
    });
  });
</script>
