@php
  $productsEndpoint = (string) ($productsFetchUrl ?? '');
  $bomEndpoint = (string) ($bomFetchUrl ?? '');
  $allMaterialsEndpoint = (string) ($allMaterialsFetchUrl ?? '');
  $allOperationsEndpoint = (string) ($allOperationsFetchUrl ?? '');
  $barcodeMaterialLookupEndpoint = (string) ($barcodeMaterialLookupUrl ?? '');
  $plannedEndpoint = (string) ($plannedConsumptionStoreUrl ?? '');
  $defaultProduct = trim((string) ($defaultProductIdent ?? ''));
  $defaultProductLabel = trim((string) ($defaultProductLabel ?? ''));
  $currentUser = auth()->user();
  $isAdminUser = $currentUser
    ? (method_exists($currentUser, 'isAdmin')
        ? (bool) $currentUser->isAdmin()
        : strtolower((string) ($currentUser->role ?? '')) === 'admin')
    : false;
  $allowManualStockAdjust = $isAdminUser;
  $stockAdjustWarehouse = trim((string) (($workOrder['magacin'] ?? '') ?: ''));
  $scannerCompactRoleLayout = $currentUser
    ? strtolower((string) ($currentUser->role ?? '')) === 'user'
    : false;
  $scannerRequiresManualCameraStart = $currentUser
    ? (method_exists($currentUser, 'isAdmin')
        ? (bool) $currentUser->isAdmin()
        : strtolower((string) ($currentUser->role ?? '')) === 'admin')
    : false;
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
  data-all-materials-url="{{ $allMaterialsEndpoint }}"
  data-all-operations-url="{{ $allOperationsEndpoint }}"
  data-barcode-lookup-url="{{ $barcodeMaterialLookupEndpoint }}"
  data-save-url="{{ $plannedEndpoint }}"
  data-stock-adjust-url="{{ route('app-materials-stock-bulk-adjust') }}"
  data-stock-adjust-warehouse="{{ $stockAdjustWarehouse }}"
  data-default-product="{{ $defaultProduct }}"
  data-default-product-label="{{ $defaultProductLabel }}"
  data-csrf-token="{{ csrf_token() }}"
  data-require-manual-camera-start="{{ $scannerRequiresManualCameraStart ? '1' : '0' }}"
  data-allow-manual-stock-adjust="{{ $allowManualStockAdjust ? '1' : '0' }}"
  data-compact-layout="{{ $scannerCompactRoleLayout ? '1' : '0' }}"
>
  <div class="modal-dialog modal-dialog-centered modal-xl mt-0">
    <div class="modal-content wo-bom-modal-content">
      <div class="modal-header wo-bom-modal-header wo-bom-content-header">
        <div class="w-100 text-center">
          <h4 class="mb-0 text-white" id="sirovina-scanner-modal-label">
            Planiraj novu potrošnju za RN <span id="sirovina-rn-number">-</span>
          </h4>
          <p class="mb-0 wo-bom-modal-subtitle">Izaberite materijal i operacije za privremenu sastavnicu</p>
        </div>
      </div>
      <div class="modal-body p-0">
        <div class="wo-bom-modal-shell">
          <div class="row g-4 align-items-stretch">
            <div class="col-12 col-lg-4 wo-bom-scanner-col">
              <div class="wo-bom-card wo-bom-dummy-qr-card">
                <h5 class="text-white mb-1">Skenirajte BARCODE artikla</h5>

                <div class="qr-scanner-container position-relative wo-bom-dummy-qr-wrap">
                  <div id="sirovina-qr-scanner-frame" class="qr-scanner-frame position-relative">
                    <div id="sirovina-qr-scanner-region" class="position-absolute" style="inset: 0;"></div>

                    <div class="qr-corner qr-corner-top-left"></div>
                    <div class="qr-corner qr-corner-top-right"></div>
                    <div class="qr-corner qr-corner-bottom-left"></div>
                    <div class="qr-corner qr-corner-bottom-right"></div>
                    <div class="qr-barcode-window"></div>
                    <div class="qr-barcode-window-label">Barcode može biti bilo gdje u okviru</div>

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
                        <button type="button" class="btn btn-sm wo-qr-btn wo-qr-btn-subtle" id="sirovina-qr-scanner-restart-btn">
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
                        <button type="button" class="btn btn-sm wo-qr-btn wo-qr-btn-primary" id="sirovina-qr-camera-apply-btn">Primijenite</button>
                      </div>
                    </div>

                    <div class="wo-qr-controls-row wo-qr-enhance-row">
                      <div class="wo-qr-zoom-control" id="sirovina-qr-zoom-wrap">
                        <label class="wo-qr-control-kicker mb-0" for="sirovina-qr-zoom-range">
                          Zoom <span id="sirovina-qr-zoom-value">1.0x</span>
                        </label>
                        <input
                          type="range"
                          class="form-range wo-qr-range"
                          id="sirovina-qr-zoom-range"
                          min="1"
                          max="1"
                          step="0.1"
                          value="1"
                          disabled
                        >
                      </div>
                      <button type="button" class="btn btn-sm wo-qr-btn wo-qr-btn-subtle" id="sirovina-qr-torch-btn" disabled>
                        <i class="fa fa-lightbulb-o me-50"></i> Uključite svjetlo
                      </button>
                    </div>

                  </div>

                  <div class="wo-qr-feedback-wrap">
                    <div class="small wo-qr-status" id="sirovina-qr-scanner-status">Dozvoli pristup kameri za barcode skeniranje.</div>
                    <div class="small wo-qr-error text-danger d-none" id="sirovina-qr-scanner-error"></div>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-12 col-lg-4 d-flex wo-bom-ipad-hidden-col">
              <div class="wo-bom-card wo-bom-quick-card h-100 w-100 d-flex flex-column">
                <div class="wo-bom-field wo-bom-quick-last wo-bom-quick-persistent d-flex flex-column flex-grow-1">
                  <div class="wo-bom-quick-head">
                    <label class="form-label wo-bom-section-title mb-50">Privremena sastavnica</label>
                    <span class="wo-bom-quick-selected">Odabrano: <strong id="bom-selected-count">0</strong></span>
                  </div>
                  <div class="table-responsive wo-bom-quick-table-wrap">
                    <table class="table table-sm mb-0 wo-bom-table wo-bom-quick-table">
                      <thead>
                        <tr>
                          <th style="width: 80px;">Poz</th>
                          <th style="width: 200px;">Komponenta</th>
                          <th>Opis</th>
                          <th style="width: 120px;" class="text-end">Zaliha</th>
                          <th style="width: 120px;" class="text-center">Tip</th>
                        </tr>
                      </thead>
                      <tbody id="bom-quick-components-body">
                        <tr class="wo-bom-empty-row">
                          <td colspan="5" class="text-center text-white-50 py-2">Nema odabranih komponenti.</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-12 col-lg-4 wo-bom-right-col d-flex">
              <div class="wo-bom-card wo-bom-main-card h-100 w-100 d-flex flex-column">
                <div class="wo-bom-mode-panel wo-panel-active" id="bom-mode-product-panel" data-mode-panel="product">
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
                  <div class="wo-bom-table-head mt-1">
                    <h6 class="wo-bom-section-title mb-0">Sastavnice proizvoda</h6>
                    <span class="wo-bom-table-found">Pronađeno: <strong id="bom-total-count">0</strong></span>
                  </div>

                  <div class="table-responsive wo-bom-table-wrap">
                    <table class="table table-sm mb-0 wo-bom-table">
                      <thead>
                        <tr>
                          <th class="text-center" style="width: 46px;">#</th>
                          <th style="width: 80px;">Poz</th>
                          <th style="width: 200px;">Komponenta</th>
                          <th>Opis</th>
                          <th style="width: 130px;" class="text-end">Planirano</th>
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

                  <div class="wo-bom-loading-overlay d-none" id="bom-loading-overlay" aria-hidden="true">
                    <div class="wo-bom-loading-inner">
                      <span class="spinner-border spinner-border-sm text-secondary" role="status" aria-hidden="true"></span>
                      <span>Učitavanje</span>
                    </div>
                  </div>
                </div>

                </div>

                <div class="wo-bom-mode-panel d-none" id="bom-mode-all-panel" data-mode-panel="all" aria-hidden="true">
                  <div class="wo-bom-field">
                    <label class="form-label wo-bom-section-title mb-50" for="bom-all-search-input">Pretraga</label>
                    <input type="text" class="form-control form-control-sm" id="bom-all-search-input" placeholder="Unesite šifru ili naziv">
                  </div>

                  <div class="wo-bom-table-section mt-1">
                    <div class="wo-bom-table-head">
                      <h6 class="wo-bom-section-title mb-0" id="bom-all-title">Sve stavke - Materijali</h6>
                      <span class="wo-bom-table-found">Pronađeno: <strong id="bom-all-total-count">0</strong></span>
                    </div>
                    <div class="wo-bom-loading-more-note d-none" id="bom-all-loading-more-note" aria-live="polite">
                      U&#269;itavanje jo&#353; rezultata...
                    </div>

                    <div class="table-responsive wo-bom-table-wrap wo-bom-all-table-wrap" id="bom-all-table-wrap">
                      <table class="table table-sm mb-0 wo-bom-table">
                        <thead id="bom-all-items-head">
                          <tr>
                            <th class="text-center" style="width: 46px;">#</th>
                            <th style="width: 70px;">Poz</th>
                            <th style="width: 210px;">Sifra</th>
                            <th>Opis</th>
                            <th style="width: 120px;" class="text-end">Zaliha</th>
                            <th style="width: 100px;" class="text-center">MJ</th>
                            <th style="width: 90px;" class="text-center">Tip</th>
                          </tr>
                        </thead>
                        <tbody id="bom-all-items-body">
                          <tr>
                            <td colspan="7" class="text-center text-white-50 py-2">Ucitajte stavke iz odabranog prikaza.</td>
                          </tr>
                        </tbody>
                      </table>
                    </div>

                    <div class="wo-bom-loading-overlay d-none" id="bom-all-loading-overlay" aria-hidden="true">
                      <div class="wo-bom-loading-inner">
                        <span class="spinner-border spinner-border-sm text-secondary" role="status" aria-hidden="true"></span>
                        <span>Učitavanje</span>
                      </div>
                    </div>
                  </div>
                </div>

              </div>

              <div class="wo-bom-bottom-mode-switch" id="bom-mode-switcher" role="tablist" aria-label="Rezim prikaza">
                <button type="button" class="wo-bom-bottom-mode-btn is-active" data-mode="product" aria-selected="true">Pretraga po proizvodu</button>
                <button type="button" class="wo-bom-bottom-mode-btn" data-mode="materials" aria-selected="false">Svi materijali</button>
                <button type="button" class="wo-bom-bottom-mode-btn" data-mode="operations" aria-selected="false">Sve operacije</button>
              </div>
            </div>
          </div>

          <div class="wo-bom-modal-footer">
            <button type="button" class="btn btn-secondary wo-scanner-close-fab" data-bs-dismiss="modal" aria-label="Zatvori">
              <i class="fa fa-times me-50"></i> Zatvori
            </button>
            <button type="button" class="btn btn-success wo-scanner-open-fab" id="bom-open-quantity-btn" disabled>
              <i class="fa fa-check me-50"></i> Nastavite
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<style>

  
  @media (min-width: 576px) and (max-width: 1180px) {
    #sirovina-scanner-modal .modal-dialog {
        max-width: 95%;
        margin: 1.75rem auto;
    }
}

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
    --qr-accent: rgba(168, 176, 190, 0.78);
    --qr-accent-soft: rgba(168, 176, 190, 0.24);
    --qr-border-muted: rgba(189, 199, 221, 0.32);
    --qr-text-soft: #cfd7ee;
    --qr-text-main: #e8edf9;
    --bom-control-height: calc(1.5em + 0.572rem + 2px);
    --wo-qr-control-height: 2.55rem;
    --wo-scroll-track: rgba(12, 18, 30, 0.92);
    --wo-scroll-thumb: rgba(138, 148, 169, 0.86);
    --wo-scroll-thumb-hover: rgba(160, 170, 190, 0.92);
    --wo-scroll-thumb-active: rgba(176, 186, 206, 0.95);
    --wo-scroll-thumb-border: rgba(11, 17, 29, 0.9);
  }

  body.dark-layout #sirovina-scanner-modal,
  body.semi-dark-layout #sirovina-scanner-modal,
  .dark-layout #sirovina-scanner-modal,
  .semi-dark-layout #sirovina-scanner-modal {
    --wo-scroll-track: rgba(12, 18, 30, 0.92);
    --wo-scroll-thumb: rgba(138, 148, 169, 0.86);
    --wo-scroll-thumb-hover: rgba(160, 170, 190, 0.92);
    --wo-scroll-thumb-active: rgba(176, 186, 206, 0.95);
    --wo-scroll-thumb-border: rgba(11, 17, 29, 0.9);
  }

  #sirovina-scanner-modal .wo-bom-modal-shell {
    border-radius: 0;
    border: none;
    background: transparent;
    padding: 0;
  }

  #sirovina-scanner-modal .wo-bom-modal-header {
    margin: 0 0 1.4rem;
    text-align: center;
  }

  #sirovina-scanner-modal .wo-bom-content-header {
    border-bottom: 0;
    justify-content: center;
    background: transparent;
    padding: 0.95rem 1rem 0.45rem;
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
  #sirovina-scanner-modal .wo-bom-main-card,
  #sirovina-scanner-modal .wo-bom-quick-card {
    flex: 1 1 auto;
    width: 100%;
    min-height: 0;
  }

  #sirovina-scanner-modal .wo-bom-dummy-qr-card > h5 {
    text-align: center;
    margin-bottom: 0.9rem !important;
  }

  #sirovina-scanner-modal .wo-bom-main-card {
    display: flex;
    flex-direction: column;
    gap: 0;
    min-height: 0;
    overflow: hidden;
  }

  #sirovina-scanner-modal .wo-bom-quick-card {
    min-height: 0;
    overflow: hidden;
  }

  #sirovina-scanner-modal .wo-bom-mode-panel {
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    min-height: 0;
    padding-top: 0;
    padding-bottom: 0.35rem;
    overflow: hidden;
    width: 100%;
    transition: opacity 0.24s ease, transform 0.24s ease;
    transform: translateX(0) scale(1);
    opacity: 1;
  }

  #sirovina-scanner-modal .wo-bom-mode-panel.wo-panel-enter {
    opacity: 0;
    transform: translateX(14px) scale(0.99);
  }

  #sirovina-scanner-modal .wo-bom-mode-panel.wo-panel-exit {
    opacity: 0;
    transform: translateX(-14px) scale(0.99);
  }

  #sirovina-scanner-modal .wo-bom-segment-switch {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.3rem;
    border-radius: 999px;
    border: 1px solid rgba(177, 189, 216, 0.24);
    background: rgba(10, 16, 28, 0.72);
    backdrop-filter: blur(5px);
  }

  #sirovina-scanner-modal .wo-bom-segment-btn {
    border: 0;
    border-radius: 999px;
    padding: 0.35rem 0.9rem;
    font-size: 0.78rem;
    color: #d3ddf5;
    background: transparent;
    transition: all 0.2s ease;
  }

  #sirovina-scanner-modal .wo-bom-segment-btn.is-active {
    color: #ffffff;
    background: linear-gradient(135deg, rgba(122, 132, 148, 0.98), rgba(92, 102, 118, 0.98));
    box-shadow: 0 8px 18px rgba(6, 8, 14, 0.34);
  }

  #sirovina-scanner-modal .wo-bom-main-card .wo-bom-grid,
  #sirovina-scanner-modal .wo-bom-main-card .wo-bom-head-block,
  #sirovina-scanner-modal .wo-bom-main-card .wo-bom-table-section,
  #sirovina-scanner-modal .wo-bom-main-card .wo-bom-section-title,
  #sirovina-scanner-modal .wo-bom-main-card .wo-bom-table-head,
  #sirovina-scanner-modal .wo-bom-main-card .wo-bom-table-wrap,
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
    flex: 1 1 auto;
    min-height: 0;
    gap: 0.45rem;
    position: relative;
  }

  #sirovina-scanner-modal .wo-bom-table-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
  }

  #sirovina-scanner-modal .wo-bom-table-found {
    font-size: 0.86rem;
    color: #d4def8;
    white-space: nowrap;
  }

  #sirovina-scanner-modal .wo-bom-loading-more-note {
    margin: -0.1rem 0 0.25rem;
    font-size: 0.78rem;
    color: #b4c0dd;
    text-align: right;
  }

  #sirovina-scanner-modal .wo-bom-loading-overlay {
    position: absolute;
    inset: 0;
    z-index: 6;
    border-radius: 10px;
    background: rgba(5, 10, 18, 0.42);
    backdrop-filter: blur(3px);
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: auto;
  }

  #sirovina-scanner-modal .wo-bom-loading-inner {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.55rem;
    padding: 0.55rem 0.85rem;
    border-radius: 999px;
    color: #e8edfb;
    background: rgba(10, 16, 27, 0.86);
    border: 1px solid rgba(168, 176, 190, 0.38);
    font-size: 0.84rem;
    font-weight: 500;
    letter-spacing: 0.01em;
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
    height: 280px;
    padding-top: 0;
    border-radius: 12px;
    border: 1px solid var(--qr-accent-soft);
    background: rgba(255, 255, 255, 0.045);
    overflow: hidden;
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.03);
  }

  #sirovina-scanner-modal #sirovina-qr-scanner-region > div {
    border: 0 !important;
  }

  #sirovina-scanner-modal #sirovina-qr-scanner-frame.qr-mirror-on video,
  #sirovina-scanner-modal #sirovina-qr-scanner-frame.qr-mirror-on canvas {
    transform: scaleX(-1);
  }

  #sirovina-scanner-modal #sirovina-qr-scanner-frame .qr-barcode-window {
    position: absolute;
    inset: 16px;
    border: 2px solid rgba(92, 225, 194, 0.95);
    border-radius: 12px;
    background:
      linear-gradient(180deg, rgba(92, 225, 194, 0.08), rgba(92, 225, 194, 0.01)),
      rgba(10, 16, 27, 0.06);
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.08);
    z-index: 2;
    pointer-events: none;
  }

  #sirovina-scanner-modal #sirovina-qr-scanner-frame .qr-barcode-window-label {
    position: absolute;
    left: 50%;
    bottom: 26px;
    transform: translateX(-50%);
    padding: 0.18rem 0.55rem;
    border-radius: 999px;
    background: rgba(8, 11, 20, 0.7);
    border: 1px solid rgba(140, 230, 208, 0.26);
    color: #dff8f1;
    font-size: 0.72rem;
    font-weight: 600;
    letter-spacing: 0.02em;
    white-space: nowrap;
    z-index: 3;
    pointer-events: none;
  }

  @keyframes scanLineSirovina {
    0% {
      top: 12%;
      opacity: 1;
    }
    50% {
      opacity: 0.8;
    }
    100% {
      top: 88%;
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
    top: 50%;
    left: 50%;
    width: calc(100% - 44px);
    height: 2px;
    transform: translate(-50%, -50%);
    background: linear-gradient(90deg, transparent, var(--qr-accent), transparent);
    animation: scanLineSirovina 2s linear infinite;
    opacity: 0.82;
    z-index: 3;
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
      linear-gradient(rgba(172, 180, 194, 0.08) 1px, transparent 1px),
      linear-gradient(90deg, rgba(172, 180, 194, 0.08) 1px, transparent 1px);
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

  #sirovina-scanner-modal .wo-qr-enhance-row {
    align-items: flex-end;
  }

  #sirovina-scanner-modal .wo-qr-zoom-control {
    min-width: 0;
    flex: 1 1 auto;
    display: flex;
    flex-direction: column;
    gap: 0.28rem;
  }

  #sirovina-scanner-modal .wo-qr-range {
    margin: 0;
  }

  #sirovina-scanner-modal .wo-qr-range::-webkit-slider-thumb {
    background: #5ce1c2;
  }

  #sirovina-scanner-modal .wo-qr-range::-moz-range-thumb {
    background: #5ce1c2;
    border: 0;
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

  #sirovina-scanner-modal .wo-qr-camera-row .form-select {
    flex: 1 1 auto;
    height: var(--wo-qr-control-height);
    min-height: var(--wo-qr-control-height);
  }

  #sirovina-scanner-modal .wo-qr-btn {
    height: var(--wo-qr-control-height);
    min-height: var(--wo-qr-control-height);
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

  #sirovina-scanner-modal .wo-bom-quick-last {
    margin-top: 0;
  }

  #sirovina-scanner-modal .wo-bom-quick-persistent {
    position: relative;
    padding-top: 0;
    padding-bottom: 0.65rem;
    margin-bottom: 0;
    min-height: 0;
  }

  #sirovina-scanner-modal .wo-bom-mode-panel .wo-bom-field > .wo-bom-section-title {
    display: block;
    margin-top: 0 !important;
    margin-bottom: 0.35rem !important;
    line-height: 1.2;
  }

  #sirovina-scanner-modal .wo-bom-quick-persistent::after {
    display: none;
  }

  #sirovina-scanner-modal .wo-bom-quick-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.35rem;
  }

  #sirovina-scanner-modal .wo-bom-quick-head .wo-bom-section-title {
    margin-bottom: 0 !important;
  }

  #sirovina-scanner-modal .wo-bom-quick-selected {
    font-size: 0.86rem;
    color: #d4def8;
    white-space: nowrap;
  }

  #sirovina-scanner-modal .wo-bom-quick-table-wrap {
    margin-top: 0;
    border: 1px solid rgba(177, 189, 216, 0.22);
    border-radius: 8px;
    overflow-x: auto;
    overflow-y: auto;
    flex: 1 1 auto;
    min-height: 0;
    height: auto;
    max-height: none;
    width: 100%;
  }

  #sirovina-scanner-modal .wo-bom-main-card .wo-bom-table-wrap {
    margin-top: 0;
    border: 1px solid rgba(177, 189, 216, 0.22);
    border-radius: 8px;
    overflow-x: auto;
    overflow-y: auto;
    flex: 1 1 auto;
    min-height: 0;
    height: auto;
    max-height: none;
    width: 100%;
  }

  #sirovina-scanner-modal .wo-bom-table {
    color: #e8edfb;
    margin-bottom: 0;
    border-collapse: separate;
    border-spacing: 0;
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

  #sirovina-scanner-modal .wo-bom-table.table > :not(caption) > * > * {
    border-color: rgba(56, 66, 88, 0.62) !important;
  }

  #sirovina-scanner-modal .wo-bom-table-wrap .wo-bom-table tbody td,
  #sirovina-scanner-modal .wo-bom-quick-table-wrap .wo-bom-table tbody td {
    vertical-align: middle;
    min-height: 68px;
    height: 68px;
    max-height: 68px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  #sirovina-scanner-modal .wo-bom-table-wrap .wo-bom-table tbody tr:hover td,
  #sirovina-scanner-modal .wo-bom-quick-table-wrap .wo-bom-table tbody tr:hover td {
    background: rgba(255, 255, 255, 0.04);
  }

  #sirovina-scanner-modal .wo-bom-table tbody tr.wo-bom-empty-row td {
    border-top: 0 !important;
  }

  #sirovina-scanner-modal .wo-bom-quick-table tbody tr.wo-bom-quick-operation-row td {
    background: rgba(62, 132, 86, 0.18);
  }

  #sirovina-scanner-modal .wo-bom-quick-table tbody tr.wo-bom-quick-operation-row:hover td {
    background: rgba(62, 132, 86, 0.24);
  }

  #sirovina-scanner-modal .wo-zaliha-loading {
    display: inline-flex;
    align-items: center;
    justify-content: flex-end;
    min-width: 1.1rem;
  }

  #sirovina-scanner-modal .wo-zaliha-loading .spinner-border {
    width: 0.9rem;
    height: 0.9rem;
    border-width: 0.14em;
    color: rgba(214, 224, 246, 0.9);
    vertical-align: middle;
  }

  #sirovina-scanner-modal .wo-opis-cell {
    white-space: normal !important;
    text-overflow: clip !important;
    overflow: hidden;
  }

  #sirovina-scanner-modal .wo-opis-two-line {
    display: flex;
    flex-direction: column;
    line-height: 1.15;
    min-height: 2.3em;
    max-height: 2.3em;
    overflow: hidden;
  }

  #sirovina-scanner-modal .wo-opis-two-line.is-double {
    justify-content: space-between;
  }

  #sirovina-scanner-modal .wo-opis-two-line.is-single {
    justify-content: center;
  }

  #sirovina-scanner-modal .wo-opis-two-line > span {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  #sirovina-scanner-modal .wo-bom-modal-footer {
    margin-top: 0;
    position: fixed;
    top: max(1rem, env(safe-area-inset-top));
    left: max(1rem, env(safe-area-inset-left));
    z-index: 1085;
    display: flex;
    justify-content: flex-start;
    align-items: center;
    gap: 0.55rem;
    flex-wrap: wrap;
  }

  #sirovina-scanner-modal .wo-scanner-close-fab {
    position: static;
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

  #sirovina-scanner-modal .wo-scanner-close-fab,
  #sirovina-scanner-modal .wo-scanner-open-fab {
    height: 42px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
  }

  #sirovina-scanner-modal .wo-scanner-open-fab {
    position: static;
    border-radius: 10px;
    padding: 0.55rem 1.35rem 0.55rem 0.95rem;
    box-shadow: 0 8px 20px rgba(6, 8, 14, 0.45);
  }

  #sirovina-scanner-modal .wo-bom-bottom-mode-switch {
    position: fixed;
    left: 50%;
    bottom: max(0.9rem, env(safe-area-inset-bottom));
    transform: translateX(-50%);
    z-index: 1085;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem;
    border-radius: 999px;
    border: 1px solid rgba(179, 192, 220, 0.3);
    background: rgba(10, 16, 28, 0.78);
    backdrop-filter: blur(6px);
    box-shadow: 0 12px 30px rgba(4, 7, 13, 0.45);
  }

  #sirovina-scanner-modal .wo-bom-bottom-mode-btn {
    border: 0;
    border-radius: 999px;
    padding: 0.42rem 0.82rem;
    min-width: 130px;
    font-size: 0.78rem;
    color: #d3ddf5;
    background: transparent;
    transition: all 0.22s ease;
  }

  #sirovina-scanner-modal .wo-bom-bottom-mode-btn.is-active {
    color: #ffffff;
    background: linear-gradient(135deg, rgba(122, 132, 148, 0.98), rgba(92, 102, 118, 0.98));
  }

  #sirovina-scanner-modal .wo-bom-all-table-wrap {
    height: auto;
    max-height: none;
  }

  #sirovina-scanner-modal .form-control,
  #sirovina-scanner-modal .form-select {
    background-color: rgba(16, 22, 35, 0.85);
    color: #e8edfb;
    border-color: rgba(168, 179, 204, 0.34);
  }

  #sirovina-scanner-modal .form-select {
    height: var(--wo-qr-control-height);
    min-height: var(--wo-qr-control-height);
  }

  #sirovina-scanner-modal .form-control::placeholder {
    color: #9fb0d6;
  }

  #sirovina-scanner-modal .form-control:focus,
  #sirovina-scanner-modal .form-select:focus {
    border-color: rgba(168, 176, 190, 0.64);
    box-shadow: 0 0 0 0.12rem rgba(168, 176, 190, 0.15);
  }

  #sirovina-scanner-modal .select2-container {
    width: 100% !important;
  }

  #sirovina-scanner-modal .select2-container--default .select2-selection--single {
    height: var(--bom-control-height);
    background-color: rgba(16, 22, 35, 0.85);
    border-color: rgba(168, 179, 204, 0.34);
    color: #e8edfb;
    border-radius: 0.357rem;
  }

  #sirovina-scanner-modal #bom-all-search-input {
    height: 38px !important;
    min-height: 38px !important;
    max-height: 38px !important;
    line-height: 1.5 !important;
    padding-top: 0.375rem !important;
    padding-bottom: 0.375rem !important;
  }

  #sirovina-scanner-modal .select2-container--default .select2-selection--single .select2-selection__rendered {
    color: #e8edfb;
    line-height: calc(var(--bom-control-height) - 2px);
    padding-left: 0.75rem;
    padding-right: 2rem;
  }

  #sirovina-scanner-modal .select2-container--default .select2-selection--single .select2-selection__placeholder {
    color: #9fb0d6;
  }

  #sirovina-scanner-modal .select2-container--default .select2-selection--single .select2-selection__arrow {
    height: var(--bom-control-height);
  }

  #sirovina-scanner-modal .select2-container--classic .select2-selection--single .select2-selection__arrow b,
  #sirovina-scanner-modal .select2-container--default .select2-selection--single .select2-selection__arrow b {
    margin-top: -4px;
  }

  #sirovina-scanner-modal .select2-container--default.select2-container--focus .select2-selection--single,
  #sirovina-scanner-modal .select2-container--open .select2-selection--single {
    border-color: rgba(168, 176, 190, 0.64);
    box-shadow: 0 0 0 0.12rem rgba(168, 176, 190, 0.15);
  }

  #sirovina-scanner-modal .select2-dropdown {
    background-color: rgba(10, 15, 26, 0.98);
    border-color: rgba(168, 176, 190, 0.35);
  }

  #sirovina-scanner-modal .select2-search--dropdown .select2-search__field {
    background-color: rgba(16, 22, 35, 0.9);
    color: #e8edfb;
    border-color: rgba(168, 179, 204, 0.34);
  }

  #sirovina-scanner-modal .select2-results__option {
    color: #dce5fb;
  }

  #sirovina-scanner-modal .select2-results__option[aria-selected=true],
  #sirovina-scanner-modal .select2-results__option[aria-selected=false]:hover,
  #sirovina-scanner-modal .select2-results__option[aria-selected=true]:hover {
    color: #ffffff !important;
  }

  #sirovina-scanner-modal .select2-results__option--highlighted[aria-selected] {
    background-color: rgba(168, 176, 190, 0.25);
    color: #ffffff !important;
  }

  #sirovina-scanner-modal .select2-results__options {
    scrollbar-width: thin;
    scrollbar-color: var(--wo-scroll-thumb) var(--wo-scroll-track);
  }

  #sirovina-scanner-modal .select2-results__options::-webkit-scrollbar {
    width: 6px;
  }

  #sirovina-scanner-modal .select2-results__options::-webkit-scrollbar-track {
    background: var(--wo-scroll-track);
    border-radius: 999px;
  }

  #sirovina-scanner-modal .select2-results__options::-webkit-scrollbar-thumb {
    background: var(--wo-scroll-thumb);
    border-radius: 999px;
    border: 1px solid var(--wo-scroll-thumb-border);
  }

  #sirovina-scanner-modal .select2-results__options::-webkit-scrollbar-thumb:hover {
    background: var(--wo-scroll-thumb-hover);
  }

  #sirovina-scanner-modal .wo-bom-table-wrap,
  #sirovina-scanner-modal .wo-bom-quick-table-wrap {
    scrollbar-width: thin;
    scrollbar-color: var(--wo-scroll-thumb) var(--wo-scroll-track);
  }

  #sirovina-scanner-modal .wo-bom-table-wrap::-webkit-scrollbar,
  #sirovina-scanner-modal .wo-bom-quick-table-wrap::-webkit-scrollbar {
    width: 6px;
    height: 6px;
  }

  #sirovina-scanner-modal .wo-bom-table-wrap::-webkit-scrollbar-track,
  #sirovina-scanner-modal .wo-bom-quick-table-wrap::-webkit-scrollbar-track {
    background: var(--wo-scroll-track);
    border-radius: 999px;
  }

  #sirovina-scanner-modal .wo-bom-table-wrap::-webkit-scrollbar-thumb,
  #sirovina-scanner-modal .wo-bom-quick-table-wrap::-webkit-scrollbar-thumb {
    background: var(--wo-scroll-thumb);
    border-radius: 999px;
    border: 1px solid var(--wo-scroll-thumb-border);
  }

  #sirovina-scanner-modal .wo-bom-table-wrap::-webkit-scrollbar-thumb:hover,
  #sirovina-scanner-modal .wo-bom-quick-table-wrap::-webkit-scrollbar-thumb:hover {
    background: var(--wo-scroll-thumb-hover);
  }

  #sirovina-scanner-modal .wo-bom-table-wrap::-webkit-scrollbar-thumb:active,
  #sirovina-scanner-modal .wo-bom-quick-table-wrap::-webkit-scrollbar-thumb:active {
    background: var(--wo-scroll-thumb-active);
  }

  #sirovina-scanner-modal .wo-bom-table-wrap::-webkit-scrollbar-corner,
  #sirovina-scanner-modal .wo-bom-quick-table-wrap::-webkit-scrollbar-corner {
    background: var(--wo-scroll-track);
  }

  #sirovina-scanner-modal .form-check-input {
    border-color: rgba(177, 189, 216, 0.35);
    background-color: rgba(16, 22, 35, 0.85);
    cursor: pointer;
  }

  #sirovina-scanner-modal .form-check-label,
  #sirovina-scanner-modal .wo-bom-segment-btn,
  #sirovina-scanner-modal .wo-bom-bottom-mode-btn {
    cursor: pointer;
  }

  #sirovina-scanner-modal .wo-bom-table-wrap tbody tr:not(.wo-bom-empty-row),
  #sirovina-scanner-modal .wo-bom-all-table-wrap tbody tr:not(.wo-bom-empty-row),
  #sirovina-scanner-modal .wo-bom-table-wrap tbody tr:not(.wo-bom-empty-row) > td,
  #sirovina-scanner-modal .wo-bom-all-table-wrap tbody tr:not(.wo-bom-empty-row) > td,
  #sirovina-scanner-modal .bom-component-checkbox,
  #sirovina-scanner-modal .bom-all-item-checkbox {
    cursor: pointer;
  }

  #sirovina-scanner-modal .form-check-input:checked {
    background-color: #7a8698;
    border-color: #7a8698;
  }

  #sirovina-scanner-modal .modal-body {
    background-color: unset!important;
  }

  @media (min-width: 768px) and (max-width: 1180px) and (orientation: portrait) {
    #sirovina-scanner-modal .wo-bom-scanner-col {
      flex: 0 0 100%;
      max-width: 100%;
    }

    #sirovina-scanner-modal .wo-bom-ipad-hidden-col,
    #sirovina-scanner-modal .wo-bom-right-col {
      display: none !important;
    }

    #sirovina-scanner-modal .wo-scanner-open-fab {
      display: none !important;
    }
  }

  #sirovina-scanner-modal[data-compact-layout="1"] .wo-bom-scanner-col {
    flex: 0 0 100%;
    max-width: 100%;
  }

  #sirovina-scanner-modal[data-compact-layout="1"] .wo-bom-ipad-hidden-col,
  #sirovina-scanner-modal[data-compact-layout="1"] .wo-bom-right-col,
  #sirovina-scanner-modal[data-compact-layout="1"] .wo-scanner-open-fab {
    display: none !important;
  }

  @media (max-width: 991.98px) {
    #sirovina-scanner-modal .wo-bom-bottom-mode-switch {
      display: none !important;
    }
  }

  @media (max-width: 767.98px) {
    #sirovina-scanner-modal #sirovina-qr-scanner-frame {
      height: 220px;
    }

    #sirovina-scanner-modal .wo-bom-modal-footer {
      top: max(0.75rem, env(safe-area-inset-top));
      left: max(0.75rem, env(safe-area-inset-left));
      gap: 0.45rem;
    }

    #sirovina-scanner-modal .wo-scanner-close-fab {
      padding: 0.5rem 0.82rem;
    }

    #sirovina-scanner-modal .wo-scanner-open-fab {
      padding: 0.5rem 1.15rem 0.5rem 0.82rem;
    }

    #sirovina-scanner-modal .wo-bom-bottom-mode-btn {
      min-width: 108px;
      padding: 0.38rem 0.62rem;
    }

    #sirovina-scanner-modal .wo-bom-modal-shell {
      padding: 0;
    }

    #sirovina-scanner-modal .wo-bom-grid {
      grid-template-columns: 1fr;
    }

    #sirovina-scanner-modal .wo-bom-modal-footer {
      flex-direction: row;
      align-items: center;
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
    var allMaterialsUrl = modalEl.getAttribute('data-all-materials-url') || '';
    var allOperationsUrl = modalEl.getAttribute('data-all-operations-url') || '';
    var barcodeLookupUrl = modalEl.getAttribute('data-barcode-lookup-url') || '';
    var saveUrl = modalEl.getAttribute('data-save-url') || '';
    var stockAdjustUrl = modalEl.getAttribute('data-stock-adjust-url') || '';
    var stockAdjustWarehouse = modalEl.getAttribute('data-stock-adjust-warehouse') || '';
    var csrfToken = modalEl.getAttribute('data-csrf-token') || '';
    var defaultProduct = modalEl.getAttribute('data-default-product') || '';
    var defaultProductLabel = modalEl.getAttribute('data-default-product-label') || '';
    var requireManualCameraStart = modalEl.getAttribute('data-require-manual-camera-start') === '1';
    var allowManualStockAdjust = modalEl.getAttribute('data-allow-manual-stock-adjust') === '1';

    function isTabletViewport() {
      return window.matchMedia('(min-width: 768px) and (max-width: 1180px)').matches;
    }

    function requiresManualCameraStartForCurrentView() {
      return requireManualCameraStart && !isTabletViewport();
    }

    var rnNumberEl = document.getElementById('sirovina-rn-number');
    var statusEl = document.getElementById('bom-status');
    var errorEl = document.getElementById('bom-error');
    var productSelect = document.getElementById('bom-product-select');
    var componentsBody = document.getElementById('bom-components-body');
    var quickComponentsBody = document.getElementById('bom-quick-components-body');
    var bomLoadingOverlay = document.getElementById('bom-loading-overlay');
    var selectedCountEl = document.getElementById('bom-selected-count');
    var totalCountEl = document.getElementById('bom-total-count');
    var openQuantityBtn = document.getElementById('bom-open-quantity-btn');
    var modeSwitcherEl = document.getElementById('bom-mode-switcher');
    var modeButtons = modeSwitcherEl ? modeSwitcherEl.querySelectorAll('[data-mode]') : [];
    var productModePanel = document.getElementById('bom-mode-product-panel');
    var allModePanel = document.getElementById('bom-mode-all-panel');
    var allSearchInput = document.getElementById('bom-all-search-input');
    var allItemsTitleEl = document.getElementById('bom-all-title');
    var allItemsHeadEl = document.getElementById('bom-all-items-head');
    var allItemsBodyEl = document.getElementById('bom-all-items-body');
    var allItemsTotalEl = document.getElementById('bom-all-total-count');
    var allLoadingMoreNoteEl = document.getElementById('bom-all-loading-more-note');
    var allLoadingOverlay = document.getElementById('bom-all-loading-overlay');
    var allTableWrapEl = document.getElementById('bom-all-table-wrap');
    var scannerCardEl = modalEl.querySelector('.wo-bom-dummy-qr-card');
    var quickCardEl = modalEl.querySelector('.wo-bom-quick-card');
    var rightMainCardEl = modalEl.querySelector('.wo-bom-main-card');
    var scannerFrameEl = document.getElementById('sirovina-qr-scanner-frame');
    var scannerStatusEl = document.getElementById('sirovina-qr-scanner-status');
    var scannerErrorEl = document.getElementById('sirovina-qr-scanner-error');
    var scannerMirrorToggle = document.getElementById('sirovina-qr-mirror-toggle');
    var scannerRestartBtn = document.getElementById('sirovina-qr-scanner-restart-btn');
    var scannerCameraSelect = document.getElementById('sirovina-qr-camera-select');
    var scannerCameraApplyBtn = document.getElementById('sirovina-qr-camera-apply-btn');
    var scannerZoomWrap = document.getElementById('sirovina-qr-zoom-wrap');
    var scannerZoomRange = document.getElementById('sirovina-qr-zoom-range');
    var scannerZoomValueEl = document.getElementById('sirovina-qr-zoom-value');
    var scannerTorchBtn = document.getElementById('sirovina-qr-torch-btn');

    var confirmModalEl = document.getElementById('confirm-weight-modal');
    var confirmLabelEl = document.getElementById('scanned-material-name');
    var confirmMaterialCodeWrapEl = document.getElementById('confirm-material-code-wrap');
    var confirmDetailsWrapEl = document.getElementById('confirm-material-details-wrap');
    var confirmHelpTextEl = document.getElementById('confirm-weight-help-text');
    var confirmMaterialCodeEl = document.getElementById('confirm-material-code');
    var confirmMaterialSetEl = document.getElementById('confirm-material-set');
    var confirmMaterialTitleEl = document.getElementById('confirm-material-title');
    var confirmMaterialUnitEl = document.getElementById('confirm-material-unit');
    var confirmMaterialQidEl = document.getElementById('confirm-material-qid');
    var confirmMaterialCurrentQtyEl = document.getElementById('confirm-material-current-qty');
    var confirmMaterialRnLineEl = document.getElementById('confirm-material-rn-line');
    var confirmMaterialStockQtyEl = document.getElementById('confirm-material-stock-qty');
    var confirmMaterialDeadlineEl = document.getElementById('confirm-material-deadline');
    var confirmMaterialUpdatedAtEl = document.getElementById('confirm-material-updated-at');
    var confirmMaterialActionEl = document.getElementById('confirm-material-action-indicator');
    var quantityInput = document.getElementById('weight-input');
    var quantityUnitSelect = document.getElementById('weight-unit-select');
    var confirmScreenValueEl = document.getElementById('confirm-weight-screen-value');
    var confirmScreenUnitEl = document.getElementById('confirm-weight-screen-unit');
    var confirmUnitSwitchEl = document.getElementById('confirm-weight-unit-switch');
    var confirmUnitButtons = confirmUnitSwitchEl ? confirmUnitSwitchEl.querySelectorAll('.confirm-weight-unit-btn') : [];
    var confirmKeypadEl = document.getElementById('confirm-weight-keypad');
    var confirmBackspaceBtn = document.getElementById('confirm-weight-backspace-btn');
    var confirmSaveBtn = document.getElementById('confirm-add-sirovina-btn');
    var fineAdjustModalEl = document.getElementById('fine-adjust-bom-modal');
    var fineAdjustBodyEl = document.getElementById('fine-adjust-bom-body');
    var fineAdjustCountEl = document.getElementById('fine-adjust-selected-count');
    var fineAdjustSaveBtn = document.getElementById('fine-adjust-save-btn');
    var select2PageSize = 10;
    var layoutSyncRaf = null;
    var confirmSaveIdleHtml = confirmSaveBtn
      ? confirmSaveBtn.innerHTML
      : '<i class="fa fa-check me-2"></i> Sačuvaj planiranu potršnju';
    var barcodeScanner = null;
    var barcodeScannerRunning = false;
    var barcodeScannerBusy = false;
    var barcodeManualStartUnlocked = false;
    var barcodeTrackCapabilities = null;
    var barcodeTrackSettings = null;
    var barcodeTorchEnabled = false;
    var barcodeZoomApplyTimer = null;
    var barcodeCameras = [];
    var lastDecodedBarcode = '';
    var lastDecodedBarcodeAt = 0;
    var barcodeDuplicateWindowMs = 1200;
    var barcodeScanConfig = {
      fps: 10,
      qrbox: function (viewfinderWidth, viewfinderHeight) {
        var maxWidth = Math.max(220, Math.floor(viewfinderWidth - 24));
        var width = Math.min(maxWidth, Math.max(240, Math.floor(viewfinderWidth * 0.94)));
        var maxHeight = Math.max(160, Math.floor(viewfinderHeight - 24));
        var height = Math.min(maxHeight, Math.max(180, Math.floor(viewfinderHeight * 0.84)));
        return { width: width, height: height };
      },
      aspectRatio: 1,
      experimentalFeatures: {
        useBarCodeDetectorIfSupported: true
      }
    };

    var state = {
      initialized: false,
      products: [],
      bomRows: [],
      selectedKeys: new Set(),
      selectedKeysByProduct: new Map(),
      quickSelections: new Map(),
      loadingProducts: false,
      loadingBom: false,
      bomRequestSeq: 0,
      select2EventsBound: false,
      proceedSource: 'manual',
      fineAdjustRows: [],
      saving: false,
      activeMode: 'product',
      allType: 'materials',
      allRows: [],
      allLoading: false,
      allRequestSeq: 0,
      allSearchDebounce: null,
      prefillOperationsSeq: 0,
      allTotalByType: {
        materials: 0,
        operations: 0
      },
      allOffsetByType: {
        materials: 0,
        operations: 0
      },
      allHasMoreByType: {
        materials: true,
        operations: false
      },
      allLoadingMoreByType: {
        materials: false,
        operations: false
      },
      selectedAllKeysByType: {
        materials: new Set(),
        operations: new Set()
      },
      materialStockByIdent: new Map(),
      materialStockPendingByIdent: new Map(),
      confirmSelectionRows: [],
      confirmContext: null
    };

    function normalizeQuantityInputValue(rawValue) {
      var value = String(rawValue == null ? '' : rawValue)
        .replace(/,/g, '.')
        .replace(/[^0-9.]/g, '');

      if (value === '') {
        return '';
      }

      var dotSeen = false;
      var cleaned = '';

      for (var index = 0; index < value.length; index++) {
        var char = value.charAt(index);

        if (char === '.') {
          if (dotSeen) {
            continue;
          }

          dotSeen = true;
          cleaned += char;
          continue;
        }

        cleaned += char;
      }

      if (cleaned.charAt(0) === '.') {
        cleaned = '0' + cleaned;
      }

      var parts = cleaned.split('.');
      var integerPart = (parts[0] || '0').replace(/^0+(?=\d)/, '');

      if (parts.length > 1) {
        return integerPart + '.' + parts[1];
      }

      return integerPart;
    }

    function formatQuantityScreenValue(rawValue) {
      var normalized = normalizeQuantityInputValue(rawValue);
      return normalized || '0';
    }

    function resolveConfirmScreenUnitLabel() {
      var selectedUnit = quantityUnitSelect ? String(quantityUnitSelect.value || 'AUTO').trim().toUpperCase() : 'AUTO';
      var sourceUnit = state && state.confirmContext && state.confirmContext.material
        ? String(state.confirmContext.material.material_um || '').trim().toUpperCase()
        : '';

      if (selectedUnit === 'AUTO' && sourceUnit) {
        return 'AUTO / ' + sourceUnit;
      }

      return selectedUnit;
    }

    function syncConfirmUnitButtons() {
      var activeUnit = quantityUnitSelect ? String(quantityUnitSelect.value || 'AUTO').trim().toUpperCase() : 'AUTO';

      if (confirmUnitButtons && confirmUnitButtons.length) {
        Array.prototype.forEach.call(confirmUnitButtons, function (button) {
          var buttonUnit = String(button.getAttribute('data-unit') || '').trim().toUpperCase();
          button.classList.toggle('is-active', buttonUnit === activeUnit);
        });
      }

      if (confirmScreenUnitEl) {
        confirmScreenUnitEl.textContent = resolveConfirmScreenUnitLabel();
      }
    }

    function updateConfirmQuantityScreen() {
      if (confirmScreenValueEl) {
        confirmScreenValueEl.textContent = formatQuantityScreenValue(quantityInput ? quantityInput.value : '');
      }

      syncConfirmUnitButtons();
    }

    function setQuantityInputValue(nextValue) {
      var normalized = (nextValue === '' || nextValue == null)
        ? ''
        : normalizeQuantityInputValue(nextValue);

      if (quantityInput) {
        quantityInput.value = normalized;
      }

      updateConfirmQuantityScreen();
    }

    function setQuantityUnitValue(nextUnit) {
      var allowedUnits = ['AUTO', 'KG', 'MJ', 'RDS'];
      var normalized = String(nextUnit || 'AUTO').trim().toUpperCase();

      if (allowedUnits.indexOf(normalized) === -1) {
        normalized = 'AUTO';
      }

      if (quantityUnitSelect) {
        quantityUnitSelect.value = normalized;
      }

      syncConfirmUnitButtons();
    }

    function appendQuantityInputToken(token) {
      var current = quantityInput ? String(quantityInput.value || '') : '';
      var next = current;

      if (token === '.') {
        if (current.indexOf('.') === -1) {
          next = current ? current + '.' : '0.';
        }
      } else if (token === '00') {
        if (!current) {
          next = '0';
        } else if (current !== '0') {
          next = current + '00';
        }
      } else if (/^\d+$/.test(token)) {
        if (!current || current === '0') {
          next = token === '0' ? '0' : token;
        } else {
          next = current + token;
        }
      }

      setQuantityInputValue(next);
    }

    function backspaceQuantityInputValue() {
      var current = quantityInput ? String(quantityInput.value || '') : '';

      if (!current) {
        return;
      }

      setQuantityInputValue(current.slice(0, -1));
    }

    function clearQuantityInputValue() {
      setQuantityInputValue('');
    }

    function setStatus(text, tone) {
      if (!statusEl) {
        return;
      }

      if (!text) {
        statusEl.textContent = '';
        statusEl.classList.add('d-none');
        return;
      }

      statusEl.classList.remove('d-none');
      statusEl.textContent = text;
      statusEl.classList.remove('text-success', 'text-secondary', 'text-warning', 'text-danger', 'text-white-50');

      if (tone === 'success') {
        statusEl.classList.add('text-secondary');
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
        var options = {
          icon: icon,
          title: title,
          text: text
        };

        if (typeof window.woSwalWithTheme === 'function') {
          options = window.woSwalWithTheme(options);
        }

        window.Swal.fire(options);
        return;
      }

      window.alert(title + ': ' + text);
    }

    function shouldIgnoreSelectableRowClick(target) {
      if (!target || typeof target.closest !== 'function') {
        return true;
      }

      return Boolean(target.closest('input, button, a, select, textarea, label'));
    }

    function bindSelectableRowClick(bodyElement, checkboxSelector) {
      if (!bodyElement) {
        return;
      }

      bodyElement.addEventListener('click', function (event) {
        var target = event.target;
        var row;
        var checkbox;

        if (shouldIgnoreSelectableRowClick(target)) {
          return;
        }

        row = target.closest('tr');
        if (!row || row.querySelector('td[colspan]')) {
          return;
        }

        checkbox = row.querySelector(checkboxSelector);
        if (!checkbox || checkbox.disabled) {
          return;
        }

        checkbox.click();
      });
    }

    function setScannerStatus(text, tone) {
      if (!scannerStatusEl) {
        return;
      }

      scannerStatusEl.textContent = text || '';
      scannerStatusEl.classList.remove('text-white-50', 'text-success', 'text-danger', 'text-warning');

      if (tone === 'success') {
        scannerStatusEl.classList.add('text-success');
      } else if (tone === 'danger') {
        scannerStatusEl.classList.add('text-danger');
      } else if (tone === 'warning') {
        scannerStatusEl.classList.add('text-warning');
      } else {
        scannerStatusEl.classList.add('text-white-50');
      }
    }

    function showScannerError(text) {
      if (!scannerErrorEl) {
        return;
      }

      scannerErrorEl.textContent = text || '';
      scannerErrorEl.classList.toggle('d-none', !text);
    }

    function clearScannerError() {
      showScannerError('');
    }

    function applyScannerMirrorState() {
      if (!scannerFrameEl || !scannerMirrorToggle) {
        return;
      }

      scannerFrameEl.classList.toggle('qr-mirror-on', !!scannerMirrorToggle.checked);
    }

    function clampScannerNumber(value, min, max) {
      var numericValue = Number(value);
      if (!Number.isFinite(numericValue)) {
        return min;
      }

      return Math.min(Math.max(numericValue, min), max);
    }

    function normalizeScannerCapabilityModes(value) {
      if (Array.isArray(value)) {
        return value.map(function (item) {
          return String(item || '').trim().toLowerCase();
        }).filter(function (item) {
          return item !== '';
        });
      }

      var singleValue = String(value || '').trim().toLowerCase();
      return singleValue ? [singleValue] : [];
    }

    function scannerCapabilityRange(value) {
      if (!value || typeof value !== 'object') {
        return null;
      }

      var min = Number(value.min);
      var max = Number(value.max);
      var step = Number(value.step);

      if (!Number.isFinite(min) || !Number.isFinite(max) || max <= min) {
        return null;
      }

      return {
        min: min,
        max: max,
        step: Number.isFinite(step) && step > 0 ? step : 0.1
      };
    }

    function scannerBooleanCapabilityEnabled(value) {
      if (Array.isArray(value)) {
        return value.some(function (item) {
          return item === true || String(item || '').toLowerCase() === 'true';
        });
      }

      return value === true || String(value || '').toLowerCase() === 'true';
    }

    function scannerSourceCandidateKey(candidate) {
      if (typeof candidate === 'string') {
        return 'id:' + candidate;
      }

      try {
        return JSON.stringify(candidate);
      } catch (error) {
        return String(candidate);
      }
    }

    function formatScannerZoomValue(value) {
      var numericValue = Number(value);
      if (!Number.isFinite(numericValue) || numericValue <= 0) {
        return '1.0x';
      }

      return numericValue.toFixed(1) + 'x';
    }

    function updateScannerZoomLabel(value) {
      if (!scannerZoomValueEl) {
        return;
      }

      scannerZoomValueEl.textContent = formatScannerZoomValue(value);
    }

    function resetScannerEnhancementControls() {
      barcodeTrackCapabilities = null;
      barcodeTrackSettings = null;
      barcodeTorchEnabled = false;

      if (barcodeZoomApplyTimer) {
        window.clearTimeout(barcodeZoomApplyTimer);
        barcodeZoomApplyTimer = null;
      }

      if (scannerZoomRange) {
        scannerZoomRange.disabled = true;
        scannerZoomRange.min = '1';
        scannerZoomRange.max = '1';
        scannerZoomRange.step = '0.1';
        scannerZoomRange.value = '1';
      }

      updateScannerZoomLabel(1);

      if (scannerTorchBtn) {
        scannerTorchBtn.disabled = true;
        scannerTorchBtn.classList.remove('wo-qr-btn-primary');
        scannerTorchBtn.classList.add('wo-qr-btn-subtle');
        scannerTorchBtn.innerHTML = '<i class="fa fa-lightbulb-o me-50"></i> Uključite svjetlo';
      }
    }

    function supportedBarcodeFormats() {
      if (typeof Html5QrcodeSupportedFormats === 'undefined') {
        return null;
      }

      return [
        Html5QrcodeSupportedFormats.CODE_128,
        Html5QrcodeSupportedFormats.CODE_39,
        Html5QrcodeSupportedFormats.CODE_93,
        Html5QrcodeSupportedFormats.CODABAR,
        Html5QrcodeSupportedFormats.ITF,
        Html5QrcodeSupportedFormats.EAN_13,
        Html5QrcodeSupportedFormats.EAN_8,
        Html5QrcodeSupportedFormats.UPC_A,
        Html5QrcodeSupportedFormats.UPC_E
      ].filter(function (value) {
        return value !== undefined && value !== null;
      });
    }

    function renderScannerCameraOptions() {
      if (!scannerCameraSelect) {
        return;
      }

      var selected = scannerCameraSelect.value;
      scannerCameraSelect.innerHTML = '<option value="">Automatski odabir</option>';
      barcodeCameras.forEach(function (camera) {
        var option = document.createElement('option');
        option.value = camera.id;
        option.textContent = camera.label || ('Kamera ' + camera.id);
        scannerCameraSelect.appendChild(option);
      });

      if (selected) {
        scannerCameraSelect.value = selected;
      }
    }

    function refreshScannerCameras() {
      if (typeof Html5Qrcode === 'undefined') {
        return Promise.resolve([]);
      }

      return Html5Qrcode.getCameras()
        .then(function (cameras) {
          barcodeCameras = Array.isArray(cameras) ? cameras : [];
          renderScannerCameraOptions();
          return barcodeCameras;
        })
        .catch(function () {
          barcodeCameras = [];
          renderScannerCameraOptions();
          return [];
        });
    }

    function scannerSourceCandidates() {
      var selected = scannerCameraSelect ? String(scannerCameraSelect.value || '').trim() : '';
      var candidates = [];

      if (selected) {
        candidates.push(selected);
      } else {
        barcodeCameras.forEach(function (camera) {
          var label = String(camera && camera.label ? camera.label : '').toLowerCase();

          if (label.indexOf('back') > -1 || label.indexOf('rear') > -1 || label.indexOf('environment') > -1) {
            candidates.push(camera.id);
          }
        });

        candidates.push({ facingMode: { exact: 'environment' } });
        candidates.push({ facingMode: 'environment' });

        barcodeCameras.forEach(function (camera) {
          candidates.push(camera.id);
        });

        candidates.push({ facingMode: 'user' });
      }

      var seen = {};
      return candidates.filter(function (candidate) {
        var key = scannerSourceCandidateKey(candidate);
        if (seen[key]) {
          return false;
        }

        seen[key] = true;
        return true;
      });
    }

    function syncScannerEnhancementControls() {
      var zoomRange = scannerCapabilityRange(barcodeTrackCapabilities && barcodeTrackCapabilities.zoom);
      var zoomSetting = Number(barcodeTrackSettings && barcodeTrackSettings.zoom);

      if (scannerZoomRange) {
        if (zoomRange) {
          scannerZoomRange.disabled = !barcodeScannerRunning;
          scannerZoomRange.min = String(zoomRange.min);
          scannerZoomRange.max = String(zoomRange.max);
          scannerZoomRange.step = String(zoomRange.step);
          scannerZoomRange.value = String(
            clampScannerNumber(
              Number.isFinite(zoomSetting) ? zoomSetting : zoomRange.min,
              zoomRange.min,
              zoomRange.max
            )
          );
          updateScannerZoomLabel(scannerZoomRange.value);
        } else {
          scannerZoomRange.disabled = true;
          scannerZoomRange.value = '1';
          updateScannerZoomLabel(1);
        }
      }

      var torchSupported = scannerBooleanCapabilityEnabled(barcodeTrackCapabilities && barcodeTrackCapabilities.torch);
      if (scannerTorchBtn) {
        scannerTorchBtn.disabled = !torchSupported || !barcodeScannerRunning;

        if (torchSupported) {
          scannerTorchBtn.classList.toggle('wo-qr-btn-primary', barcodeTorchEnabled);
          scannerTorchBtn.classList.toggle('wo-qr-btn-subtle', !barcodeTorchEnabled);
          scannerTorchBtn.innerHTML = barcodeTorchEnabled
            ? '<i class="fa fa-lightbulb-o me-50"></i> Ugasi svjetlo'
            : '<i class="fa fa-lightbulb-o me-50"></i> Uključite svjetlo';
        } else {
          scannerTorchBtn.classList.remove('wo-qr-btn-primary');
          scannerTorchBtn.classList.add('wo-qr-btn-subtle');
          scannerTorchBtn.innerHTML = '<i class="fa fa-lightbulb-o me-50"></i> Uključite svjetlo';
        }
      }
    }

    function captureScannerTrackState() {
      if (!barcodeScannerRunning || !barcodeScanner) {
        resetScannerEnhancementControls();
        return;
      }

      try {
        barcodeTrackCapabilities = typeof barcodeScanner.getRunningTrackCapabilities === 'function'
          ? (barcodeScanner.getRunningTrackCapabilities() || null)
          : null;
      } catch (error) {
        barcodeTrackCapabilities = null;
      }

      try {
        barcodeTrackSettings = typeof barcodeScanner.getRunningTrackSettings === 'function'
          ? (barcodeScanner.getRunningTrackSettings() || null)
          : null;
      } catch (error) {
        barcodeTrackSettings = null;
      }

      if (barcodeTrackSettings && typeof barcodeTrackSettings.torch !== 'undefined') {
        barcodeTorchEnabled = barcodeTrackSettings.torch === true;
      }

      syncScannerEnhancementControls();
    }

    function applyScannerVideoConstraints(constraints) {
      if (!barcodeScanner || !barcodeScannerRunning || typeof barcodeScanner.applyVideoConstraints !== 'function') {
        return Promise.resolve(false);
      }

      return barcodeScanner.applyVideoConstraints(constraints)
        .then(function () {
          captureScannerTrackState();
          return true;
        })
        .catch(function () {
          captureScannerTrackState();
          return false;
        });
    }

    function applyScannerZoomValue(value) {
      var zoomRange = scannerCapabilityRange(barcodeTrackCapabilities && barcodeTrackCapabilities.zoom);
      if (!zoomRange) {
        return Promise.resolve(false);
      }

      var zoomValue = clampScannerNumber(value, zoomRange.min, zoomRange.max);
      updateScannerZoomLabel(zoomValue);

      return applyScannerVideoConstraints({
        advanced: [{ zoom: zoomValue }]
      });
    }

    function toggleScannerTorch() {
      var torchSupported = scannerBooleanCapabilityEnabled(barcodeTrackCapabilities && barcodeTrackCapabilities.torch);
      if (!torchSupported) {
        return Promise.resolve(false);
      }

      var nextState = !barcodeTorchEnabled;
      return applyScannerVideoConstraints({
        advanced: [{ torch: nextState }]
      }).then(function (applied) {
        if (applied) {
          barcodeTorchEnabled = nextState;
          syncScannerEnhancementControls();
        }

        return applied;
      });
    }

    function applyScannerAutoEnhancements() {
      captureScannerTrackState();

      if (!barcodeScannerRunning) {
        return Promise.resolve(false);
      }

      var capabilities = barcodeTrackCapabilities || {};
      var constraintsQueue = [];
      var focusModes = normalizeScannerCapabilityModes(capabilities.focusMode);
      var exposureModes = normalizeScannerCapabilityModes(capabilities.exposureMode);
      var whiteBalanceModes = normalizeScannerCapabilityModes(capabilities.whiteBalanceMode);
      var zoomRange = scannerCapabilityRange(capabilities.zoom);

      if (focusModes.indexOf('continuous') > -1) {
        constraintsQueue.push({ advanced: [{ focusMode: 'continuous' }] });
      } else if (focusModes.indexOf('single-shot') > -1) {
        constraintsQueue.push({ advanced: [{ focusMode: 'single-shot' }] });
      }

      if (exposureModes.indexOf('continuous') > -1) {
        constraintsQueue.push({ advanced: [{ exposureMode: 'continuous' }] });
      }

      if (whiteBalanceModes.indexOf('continuous') > -1) {
        constraintsQueue.push({ advanced: [{ whiteBalanceMode: 'continuous' }] });
      }

      if (zoomRange) {
        var preferredZoom = clampScannerNumber(
          zoomRange.min + Math.min((zoomRange.max - zoomRange.min) * 0.22, 1.25),
          zoomRange.min,
          zoomRange.max
        );
        constraintsQueue.push({ advanced: [{ zoom: preferredZoom }] });
      }

      if (constraintsQueue.length === 0) {
        syncScannerEnhancementControls();
        return Promise.resolve(false);
      }

      return constraintsQueue.reduce(function (promiseChain, nextConstraints) {
        return promiseChain.then(function () {
          return applyScannerVideoConstraints(nextConstraints);
        });
      }, Promise.resolve(false));
    }

    function normalizeBarcodeText(value) {
      return String(value || '').trim();
    }

    function lookupMaterialByBarcode(barcodeText) {
      var resolvedBarcode = normalizeBarcodeText(barcodeText);

      if (!barcodeLookupUrl) {
        return Promise.reject(new Error('Barcode lookup ruta nije dostupna.'));
      }

      if (!resolvedBarcode) {
        return Promise.reject(new Error('Skenirani barcode je prazan.'));
      }

      return fetch(buildUrl(barcodeLookupUrl, {
        barcode: resolvedBarcode
      }), {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
        .then(parseResponse)
        .then(function (payload) {
          return payload && payload.data ? payload.data : null;
        });
    }

    function buildBarcodeSelectionRow(materialPayload) {
      return {
        anNo: Number(materialPayload && materialPayload.existing_item && materialPayload.existing_item.no
          ? materialPayload.existing_item.no
          : (materialPayload && materialPayload.material_qid ? materialPayload.material_qid : 0)),
        acIdentChild: String(materialPayload && materialPayload.material_code ? materialPayload.material_code : '').trim(),
        acDescr: String(materialPayload && materialPayload.material_name ? materialPayload.material_name : '').trim(),
        acUM: String(materialPayload && materialPayload.material_um ? materialPayload.material_um : 'AUTO').trim() || 'AUTO',
        acUMSource: String(materialPayload && materialPayload.material_um ? materialPayload.material_um : '').trim(),
        acOperationType: 'M',
        anGrossQty: Number(materialPayload && materialPayload.stock_qty ? materialPayload.stock_qty : 0)
      };
    }

    function handleResolvedBarcodeMaterial(materialPayload) {
      var selectedRow = buildBarcodeSelectionRow(materialPayload);

      if (!selectedRow.acIdentChild) {
        throw new Error('Materijal za skenirani barcode nije pronađen');
      }

      setScannerStatus('Materijal pronađen. Otvara se modal za potvrdu težine...', 'success');
      clearScannerError();
      markProceedSource('barcode');
      openQuantityModal([selectedRow], {
        mode: 'barcode',
        action: materialPayload && materialPayload.action === 'update' ? 'update' : 'insert',
        material: materialPayload || {}
      });
    }

    function handleBarcodeScanSuccess(decodedText) {
      if (barcodeScannerBusy) {
        return;
      }

      var normalizedBarcode = normalizeBarcodeText(decodedText);
      if (!normalizedBarcode) {
        return;
      }

      var now = Date.now();
      if (normalizedBarcode === lastDecodedBarcode && (now - lastDecodedBarcodeAt) < barcodeDuplicateWindowMs) {
        return;
      }

      lastDecodedBarcode = normalizedBarcode;
      lastDecodedBarcodeAt = now;
      barcodeScannerBusy = true;
      clearScannerError();
      setScannerStatus('Barcode prepoznat. Provjeravam materijal...', 'success');

      lookupMaterialByBarcode(normalizedBarcode)
        .then(function (materialPayload) {
          return stopBarcodeScanner()
            .catch(function () {
              return null;
            })
            .then(function () {
              handleResolvedBarcodeMaterial(materialPayload || {});
            });
        })
        .catch(function (error) {
          setScannerStatus('Postavi barcode bilo gdje u okviru i zadrži fokus na etiketi', 'warning');
          showScannerError(error && error.message ? error.message : 'Barcode materijal nije pronađen');
        })
        .finally(function () {
          barcodeScannerBusy = false;
        });
    }

    function startBarcodeScanner() {
      if (barcodeScannerRunning || modalEl.classList.contains('show') === false) {
        return Promise.resolve();
      }

      if (typeof Html5Qrcode === 'undefined') {
        setScannerStatus('Skener nije spreman.', 'danger');
        showScannerError('Barcode biblioteka nije dostupna. Osvjezi stranicu i pokusaj ponovo.');
        return Promise.resolve();
      }

      clearScannerError();
      resetScannerEnhancementControls();
      setScannerStatus('Kamera se pokreće...');

      if (!barcodeScanner) {
        barcodeScanner = new Html5Qrcode('sirovina-qr-scanner-region');
      }

      return refreshScannerCameras()
        .then(function () {
          var scanConfig = Object.assign({}, barcodeScanConfig);
          var formats = supportedBarcodeFormats();
          if (formats && formats.length > 0) {
            scanConfig.formatsToSupport = formats;
          }

          var candidates = scannerSourceCandidates();
          var startChain = Promise.reject(new Error('Ne mogu pokrenuti kameru.'));

          candidates.forEach(function (candidate) {
            startChain = startChain.catch(function () {
              return barcodeScanner.start(candidate, scanConfig, handleBarcodeScanSuccess, function () {});
            });
          });

          return startChain.then(function () {
            barcodeScannerRunning = true;
            applyScannerMirrorState();
            captureScannerTrackState();
            return applyScannerAutoEnhancements().then(function () {
              setScannerStatus('Postavi barcode bilo gdje u okviru i drži etiketu 10-20 cm od kamere.');
            });
          });
        })
        .catch(function (error) {
          barcodeScannerRunning = false;
          resetScannerEnhancementControls();
          setScannerStatus('Skener nije pokrenut.', 'danger');
          console.error(error);
          showScannerError(error && error.message ? error.message : 'Ne mogu pokrenuti kameru za barcode skeniranje.');
        });
    }

    function requestBarcodeScannerStart(unlockForAdmin) {
      if (requiresManualCameraStartForCurrentView() && unlockForAdmin === true) {
        barcodeManualStartUnlocked = true;
      }

      if (requiresManualCameraStartForCurrentView() && !barcodeManualStartUnlocked) {
        clearScannerError();
        setScannerStatus('Admin mora kliknuti Primijenite da pokrene kameru.', 'warning');
        return refreshScannerCameras().then(function () {
          return null;
        });
      }

      return startBarcodeScanner();
    }

    function stopBarcodeScanner() {
      if (!barcodeScanner || !barcodeScannerRunning) {
        resetScannerEnhancementControls();
        return Promise.resolve();
      }

      return barcodeScanner.stop()
        .catch(function () {
          return null;
        })
        .then(function () {
          barcodeScannerRunning = false;
          resetScannerEnhancementControls();
        });
    }

    function restartBarcodeScanner(unlockForAdmin) {
      if (requiresManualCameraStartForCurrentView() && unlockForAdmin === true) {
        barcodeManualStartUnlocked = true;
      }

      if (requiresManualCameraStartForCurrentView() && !barcodeManualStartUnlocked) {
        clearScannerError();
        setScannerStatus('Admin mora kliknuti Primijenite da pokrene kameru.', 'warning');
        return refreshScannerCameras().then(function () {
          return null;
        });
      }

      return stopBarcodeScanner().then(function () {
        return startBarcodeScanner();
      });
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
        backdrop.classList.add('sirovina-scanner-backdrop');
        backdrop.style.backgroundColor = 'rgba(0, 0, 0, 0.95)';
        backdrop.style.opacity = '1';
      });
    }

    function clearBackdrop() {
      activeBackdropNodes().forEach(function (backdrop) {
        backdrop.classList.remove('sirovina-scanner-backdrop');
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

    function requestStockAdjustments(items, adjustMode) {
      if (!stockAdjustUrl) {
        return Promise.reject(new Error('API za azuriranje zalihe nije dostupna.'));
      }

      return fetch(stockAdjustUrl, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
          adjust_mode: adjustMode || 'api',
          warehouse: stockAdjustWarehouse,
          items: Array.isArray(items) ? items : []
        })
      }).then(parseResponse);
    }

    function applyLocalStockValue(materialCode, newStockValue) {
      var key = stockLookupKey(materialCode);
      var parsedValue = Number(newStockValue);

      if (!key) {
        return;
      }

      if (!Number.isFinite(parsedValue)) {
        parsedValue = 0;
      }

      state.materialStockByIdent.set(key, parsedValue);

      if (Array.isArray(state.allRows)) {
        state.allRows.forEach(function (row) {
          if (stockLookupKey(row && row.acIdentChild ? row.acIdentChild : '') !== key) {
            return;
          }

          row.anGrossQty = parsedValue;
        });
      }

      if (state.confirmContext && state.confirmContext.material && stockLookupKey(state.confirmContext.material.material_code || '') === key) {
        state.confirmContext.material.stock_qty = parsedValue;

        if (confirmMaterialStockQtyEl) {
          confirmMaterialStockQtyEl.textContent = formatQuantity(parsedValue);
        }
      }

      refreshQuickSelectionStocks(true);

      if (state.activeMode === 'materials') {
        renderAllRows();
      }
    }

    function findBomRowDataByKey(rowKey, fallbackLineNo, fallbackComponentId) {
      var matchedRow = Array.isArray(state.bomRows)
        ? state.bomRows.find(function (row) {
            return bomKey(row.anNo, row.acIdentChild) === rowKey;
          })
        : null;

      if (matchedRow) {
        return matchedRow;
      }

      if (!fallbackComponentId) {
        return null;
      }

      return {
        anNo: fallbackLineNo,
        acIdentChild: fallbackComponentId,
        acDescr: '-',
        anGrossQty: resolveKnownStock(fallbackComponentId),
        acOperationType: '',
        acUM: 'AUTO'
      };
    }

    function findAllRowDataByKey(rowType, rowKey, fallbackLineNo, fallbackComponentId) {
      var matchedRow = Array.isArray(state.allRows)
        ? state.allRows.find(function (row) {
            return allItemKey(rowType, row && row.acIdentChild ? row.acIdentChild : '') === rowKey;
          })
        : null;

      if (matchedRow) {
        return matchedRow;
      }

      if (!fallbackComponentId) {
        return null;
      }

      return {
        anNo: fallbackLineNo,
        acIdentChild: fallbackComponentId,
        acDescr: '-',
        anGrossQty: resolveKnownStock(fallbackComponentId),
        acOperationType: rowType === 'operations' ? 'O' : 'M',
        acUM: 'AUTO'
      };
    }

    function openManualStockAdjustPrompt(rowData) {
      var materialCode = String(rowData && rowData.acIdentChild ? rowData.acIdentChild : '').trim();
      var materialName = String(rowData && rowData.acDescr ? rowData.acDescr : '').trim();

      if (!allowManualStockAdjust || !materialCode) {
        return;
      }

      ensureKnownStockForComponent(materialCode)
        .then(function (currentStockValue) {
          var currentStock = Number(currentStockValue);
          if (!Number.isFinite(currentStock)) {
            currentStock = 0;
          }

          var promptLabel = 'Nova zaliha za ' + materialCode;
          if (materialName) {
            promptLabel += ' - ' + materialName;
          }
          promptLabel += '\nTrenutna zaliha: ' + formatQuantity(currentStock);

          var inputValue = window.prompt(promptLabel, formatQuantity(currentStock));
          if (inputValue === null) {
            return;
          }

          var parsedNewStock = Number(String(inputValue).trim().replace(',', '.'));
          if (!Number.isFinite(parsedNewStock)) {
            notify('warning', 'Neispravan unos', 'Unesite ispravnu brojcanu vrijednost zalihe.');
            return;
          }

          requestStockAdjustments([
            {
              material_code: materialCode,
              value: currentStock - parsedNewStock,
              new_stock_value: parsedNewStock
            }
          ], 'manual_scanner')
            .then(function (payload) {
              var responseItems = payload && payload.data && Array.isArray(payload.data.items)
                ? payload.data.items
                : [];
              var responseItem = responseItems.length > 0 ? responseItems[0] : null;
              var resolvedNewStock = responseItem && Number.isFinite(Number(responseItem.new_stock_value))
                ? Number(responseItem.new_stock_value)
                : parsedNewStock;

              applyLocalStockValue(materialCode, resolvedNewStock);
              setStatus('Zaliha materijala je rucno azurirana.', 'success');
              notify('success', 'Zaliha azurirana', materialCode + ': ' + formatQuantity(resolvedNewStock));
            })
            .catch(function (error) {
              notify('error', 'Greska', error && error.message ? error.message : 'Azuriranje zalihe nije uspjelo.');
            });
        })
        .catch(function () {
          notify('error', 'Greska', 'Ne mogu ucitati trenutnu zalihu za odabrani materijal.');
        });
    }

    function bindManualStockAdjust(bodyElement, rowResolver) {
      if (!allowManualStockAdjust || !bodyElement || typeof rowResolver !== 'function') {
        return;
      }

      bodyElement.addEventListener('click', function (event) {
        if (!event.ctrlKey) {
          return;
        }

        var row = event.target && typeof event.target.closest === 'function'
          ? event.target.closest('tr')
          : null;
        if (!row || row.querySelector('td[colspan]')) {
          return;
        }

        var rowData = rowResolver(row);
        if (!rowData || isOperationType(rowData.acOperationType || '')) {
          return;
        }

        event.preventDefault();
        event.stopPropagation();
        if (typeof event.stopImmediatePropagation === 'function') {
          event.stopImmediatePropagation();
        }

        openManualStockAdjustPrompt(rowData);
      }, true);
    }

    function bomKey(lineNo, componentId) {
      return String(lineNo || 0) + '|' + String(componentId || '').trim().toLowerCase();
    }

    function currentProductId() {
      return productSelect && productSelect.value ? String(productSelect.value).trim() : '';
    }

    function quickSelectionKey(productId, lineNo, componentId) {
      var resolvedProductId = String(productId || '').trim();
      return resolvedProductId + '::' + bomKey(lineNo, componentId);
    }

    function getStoredSelectionForProduct(productId) {
      var resolvedProductId = String(productId || '').trim();
      if (!resolvedProductId || !state.selectedKeysByProduct.has(resolvedProductId)) {
        return new Set();
      }

      var snapshot = state.selectedKeysByProduct.get(resolvedProductId);
      return snapshot instanceof Set ? new Set(snapshot) : new Set();
    }

    function storeSelectionForProduct(productId, selectedKeySet) {
      var resolvedProductId = String(productId || '').trim();
      if (!resolvedProductId) {
        return;
      }

      state.selectedKeysByProduct.set(resolvedProductId, new Set(selectedKeySet || []));
    }

    function syncQuickSelectionRow(productId, row, isSelected) {
      var resolvedProductId = String(productId || '').trim();
      if (!resolvedProductId || !row) {
        return;
      }

      var rowKey = quickSelectionKey(resolvedProductId, row.anNo, row.acIdentChild);
      if (!isSelected) {
        state.quickSelections.delete(rowKey);
        return;
      }

      state.quickSelections.set(rowKey, {
        productId: resolvedProductId,
        anNo: row.anNo || 0,
        acIdentChild: row.acIdentChild || '',
        acDescr: row.acDescr || '-',
        napomena: row.napomena || '',
        anGrossQty: Number(row.anGrossQty || 0),
        stockQty: resolveKnownStock(row.acIdentChild || ''),
        acOperationType: row.acOperationType || '',
        acUM: row.acUM || 'AUTO'
      });
    }

    function allItemKey(type, componentId) {
      var resolvedType = type === 'operations' ? 'operations' : 'materials';
      var resolvedComponentId = String(componentId || '').trim().toLowerCase();
      return resolvedType + '|' + resolvedComponentId;
    }

    function stockLookupKey(componentId) {
      return String(componentId || '').trim().toLowerCase();
    }

    function resolveKnownStock(componentId) {
      var key = stockLookupKey(componentId);
      if (!key || !state.materialStockByIdent.has(key)) {
        return 0;
      }

      var parsed = Number(state.materialStockByIdent.get(key));
      return Number.isFinite(parsed) ? parsed : 0;
    }

    function refreshQuickSelectionStocks(forceRender) {
      if (!state.quickSelections || state.quickSelections.size === 0) {
        return;
      }

      var hasChanges = false;
      state.quickSelections.forEach(function (row) {
        if (!row) {
          return;
        }

        var componentId = String(row.acIdentChild || '').trim();
        if (!componentId) {
          return;
        }

        var knownStock = resolveKnownStock(componentId);
        var currentStock = Number(row.stockQty);
        if (!Number.isFinite(currentStock)) {
          currentStock = 0;
        }

        if (knownStock !== currentStock) {
          row.stockQty = knownStock;
          hasChanges = true;
        }
      });

      if (!hasChanges && !forceRender) {
        return;
      }

      renderQuickBomRows();
      if (fineAdjustModalEl && fineAdjustModalEl.classList.contains('show')) {
        state.fineAdjustRows = buildFineAdjustRowsFromSelection();
        renderFineAdjustRows();
      }
    }

    function ensureKnownStockForComponent(componentId) {
      var ident = String(componentId || '').trim();
      var key = stockLookupKey(ident);
      if (!ident || !key) {
        return Promise.resolve(0);
      }

      if (state.materialStockByIdent.has(key)) {
        return Promise.resolve(resolveKnownStock(ident));
      }

      if (!allMaterialsUrl) {
        return Promise.resolve(0);
      }

      if (state.materialStockPendingByIdent.has(key)) {
        return state.materialStockPendingByIdent.get(key);
      }

      var url = buildUrl(allMaterialsUrl, {
        q: ident,
        limit: 100,
        offset: 0
      });

      var request = fetch(url, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
        .then(parseResponse)
        .then(function (payload) {
          var rows = Array.isArray(payload && payload.data) ? payload.data : [];
          var match = rows.find(function (row) {
            return stockLookupKey(row && row.acIdentChild ? row.acIdentChild : '') === key;
          });
          var stockValue = Number(match && match.anGrossQty ? match.anGrossQty : 0);
          if (!Number.isFinite(stockValue)) {
            stockValue = 0;
          }

          state.materialStockByIdent.set(key, stockValue);
          refreshQuickSelectionStocks();
          return stockValue;
        })
        .catch(function () {
          state.materialStockByIdent.set(key, 0);
          refreshQuickSelectionStocks();
          return 0;
        })
        .finally(function () {
          state.materialStockPendingByIdent.delete(key);
          refreshQuickSelectionStocks(true);
        });

      state.materialStockPendingByIdent.set(key, request);
      refreshQuickSelectionStocks(true);
      return request;
    }

    function selectedAllSet(type) {
      return type === 'operations'
        ? state.selectedAllKeysByType.operations
        : state.selectedAllKeysByType.materials;
    }

    function syncAllSelectionRow(type, row, isSelected) {
      if (!row) {
        return;
      }

      var resolvedType = type === 'operations' ? 'operations' : 'materials';
      var componentId = String(row.acIdentChild || '').trim();
      if (!componentId) {
        return;
      }

      var key = 'all::' + allItemKey(resolvedType, componentId);
      if (!isSelected) {
        state.quickSelections.delete(key);
        return;
      }

      var stockQtyValue = resolvedType === 'materials'
        ? Number(row.anGrossQty || 0)
        : resolveKnownStock(componentId);
      if (!Number.isFinite(stockQtyValue)) {
        stockQtyValue = 0;
      }

      state.quickSelections.set(key, {
        productId: 'all:' + resolvedType,
        anNo: Number(row.anNo || 0),
        acIdentChild: componentId,
        acDescr: row.acDescr || '-',
        napomena: row.napomena || '',
        anGrossQty: Number(row.anGrossQty || 0),
        stockQty: stockQtyValue,
        acOperationType: row.acOperationType || (resolvedType === 'operations' ? 'O' : 'M'),
        acUM: row.acUM || 'AUTO'
      });
    }

    function applyStoredSelectionToCurrentRows(productId) {
      var stored = getStoredSelectionForProduct(productId);
      var available = new Set(
        (state.bomRows || []).map(function (row) {
          return bomKey(row.anNo, row.acIdentChild);
        })
      );

      var filtered = new Set();
      stored.forEach(function (key) {
        if (available.has(key)) {
          filtered.add(key);
        }
      });

      state.selectedKeys = filtered;
      storeSelectionForProduct(productId, filtered);
    }

    function selectedRows() {
      return Array.from(state.quickSelections.values()).map(function (row) {
        return {
          acIdentChild: row.acIdentChild,
          anNo: row.anNo,
          acDescr: row.acDescr || '',
          napomena: row.napomena || '',
          acUM: row.acUM || 'AUTO',
          acUMSource: row.acUM || '',
          acOperationType: row.acOperationType || ''
        };
      });
    }

    function selectedDetailedRows() {
      return Array.from(state.quickSelections.values());
    }

    function isOperationType(value) {
      var normalized = String(value || '').trim().toUpperCase();
      return normalized === 'O' || normalized === 'OP' || normalized === 'OPR';
    }

    function renderQuickBomRows() {
      if (!quickComponentsBody) {
        return;
      }

      var rows = selectedDetailedRows();
      if (!Array.isArray(rows) || rows.length === 0) {
        quickComponentsBody.innerHTML = '<tr class="wo-bom-empty-row"><td colspan="5" class="text-center text-white-50 py-2">Nema odabranih komponenti.</td></tr>';
        return;
      }

      var html = rows.map(function (row) {
        var lineNo = row.anNo || 0;
        var componentId = row.acIdentChild || '';
        var descr = row.acDescr || '-';
        var isStockLoading = state.materialStockPendingByIdent.has(stockLookupKey(componentId));
        var stockQty = Number(row.stockQty);
        if (!Number.isFinite(stockQty)) {
          stockQty = resolveKnownStock(componentId);
          if (!Number.isFinite(stockQty)) {
            stockQty = 0;
          }
          row.stockQty = stockQty;
        }
        var operationType = row.acOperationType || '';
        var rowClass = isOperationType(operationType) ? ' class="wo-bom-quick-operation-row"' : '';

        return '' +
          '<tr' + rowClass + '>' +
            '<td>' + lineNo + '</td>' +
            '<td class="fw-semibold">' + componentId + '</td>' +
            '<td class="wo-opis-cell">' + formatOpisCell(descr) + '</td>' +
            '<td class="text-end">' + (isStockLoading
              ? '<span class="wo-zaliha-loading" aria-label="Učitavanje zalihe"><span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span></span>'
              : formatQuantity(stockQty)
            ) + '</td>' +
            '<td class="text-center">' + operationType + '</td>' +
          '</tr>';
      }).join('');

      quickComponentsBody.innerHTML = html;
    }

    function updateSelectionSummary() {
      var selectedCount = state.quickSelections.size;
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

      renderQuickBomRows();
    }

    function setBomLoading(isLoading) {
      if (!bomLoadingOverlay) {
        return;
      }

      bomLoadingOverlay.classList.toggle('d-none', !isLoading);
      bomLoadingOverlay.setAttribute('aria-hidden', isLoading ? 'false' : 'true');
    }

    function setAllLoading(isLoading) {
      state.allLoading = Boolean(isLoading);

      if (!allLoadingOverlay) {
        return;
      }

      allLoadingOverlay.classList.toggle('d-none', !isLoading);
      allLoadingOverlay.setAttribute('aria-hidden', isLoading ? 'false' : 'true');
    }

    function setAllLoadingMoreNotice(isVisible) {
      if (!allLoadingMoreNoteEl) {
        return;
      }

      allLoadingMoreNoteEl.classList.toggle('d-none', !isVisible);
    }

    function formatQuantity(value) {
      var parsed = Number(value || 0);
      if (!Number.isFinite(parsed)) {
        return '0';
      }

      return parsed.toFixed(4).replace(/0+$/, '').replace(/\.$/, '');
    }

    function parseDecimalValue(value, fallbackValue) {
      var normalized = String(value === null || value === undefined ? '' : value).trim().replace(',', '.');
      var parsed = Number(normalized);
      if (!Number.isFinite(parsed)) {
        return Number(fallbackValue || 0);
      }

      return parsed;
    }

    function clampPlaniranoToZaliha(planiranoValue, zalihaValue) {
      var planirano = parseDecimalValue(planiranoValue, 0);
      if (!Number.isFinite(planirano) || planirano < 0) {
        planirano = 0;
      }

      return planirano;
    }

    function hasFineAdjustCompleteDimensions(row) {
      var dim1 = parseDecimalValue(row && row.dim1 ? row.dim1 : 0, 0);
      var dim2 = parseDecimalValue(row && row.dim2 ? row.dim2 : 0, 0);
      var dim3 = parseDecimalValue(row && row.dim3 ? row.dim3 : 0, 0);

      return dim1 > 0 && dim2 > 0 && dim3 > 0;
    }

    function formatFineAdjustDimensionValue(value) {
      return formatQuantity(parseDecimalValue(value, 0));
    }

    function buildFineAdjustRawNote(row) {
      if (!hasFineAdjustCompleteDimensions(row)) {
        return '';
      }

      return [
        formatFineAdjustDimensionValue(row.dim1),
        formatFineAdjustDimensionValue(row.dim2),
        formatFineAdjustDimensionValue(row.dim3)
      ].join('x');
    }

    function allTypeLabel() {
      return state.allType === 'operations' ? 'Operacije' : 'Materijali';
    }

    function allFoundCountValue(rows) {
      if (state.allType === 'materials') {
        return String(Math.max(0, Number(state.allTotalByType.materials || 0)));
      }

      var list = Array.isArray(rows) ? rows : [];
      return String(list.length);
    }

    function allEndpointByType(type) {
      return type === 'operations' ? allOperationsUrl : allMaterialsUrl;
    }

    function resetAllPaging(type) {
      var resolvedType = type === 'operations' ? 'operations' : 'materials';
      state.allOffsetByType[resolvedType] = 0;
      state.allHasMoreByType[resolvedType] = resolvedType === 'materials';
      state.allLoadingMoreByType[resolvedType] = false;
    }

    function canLoadMore(type) {
      return type === 'materials' && Boolean(state.allHasMoreByType.materials);
    }

    function syncAllSearchInputHeight() {
      return;
    }

    function syncRightColumnHeightToScanner() {
      if (!scannerCardEl) {
        return;
      }

      if (window.matchMedia('(max-width: 991.98px)').matches) {
        if (quickCardEl) {
          quickCardEl.style.height = '';
          quickCardEl.style.maxHeight = '';
        }
        if (rightMainCardEl) {
          rightMainCardEl.style.height = '';
          rightMainCardEl.style.maxHeight = '';
        }
        return;
      }

      var scannerHeight = Math.round(scannerCardEl.getBoundingClientRect().height);
      if (!Number.isFinite(scannerHeight) || scannerHeight <= 0) {
        return;
      }

      if (quickCardEl) {
        quickCardEl.style.height = scannerHeight + 'px';
        quickCardEl.style.maxHeight = scannerHeight + 'px';
      }

      if (rightMainCardEl) {
        rightMainCardEl.style.height = scannerHeight + 'px';
        rightMainCardEl.style.maxHeight = scannerHeight + 'px';
      }
    }

    function scheduleLayoutSync() {
      if (layoutSyncRaf) {
        window.cancelAnimationFrame(layoutSyncRaf);
      }

      layoutSyncRaf = window.requestAnimationFrame(function () {
        layoutSyncRaf = null;
        syncRightColumnHeightToScanner();
      });
    }

    function resolveMode(mode) {
      if (mode === 'operations') {
        return 'operations';
      }

      if (mode === 'materials') {
        return 'materials';
      }

      return 'product';
    }

    function isAllMode(mode) {
      return mode === 'materials' || mode === 'operations';
    }

    function updateModeButtons() {
      if (!modeButtons || modeButtons.length === 0) {
        return;
      }

      Array.prototype.forEach.call(modeButtons, function (button) {
        var mode = String(button.getAttribute('data-mode') || '').trim();
        var isActive = mode === state.activeMode;
        button.classList.toggle('is-active', isActive);
        button.setAttribute('aria-selected', isActive ? 'true' : 'false');
      });
    }

    function showModePanel(nextMode) {
      var normalizedMode = resolveMode(nextMode);
      var nextIsAll = isAllMode(normalizedMode);
      var previousMode = state.activeMode;
      var previousIsAll = isAllMode(previousMode);
      var fromPanel = previousIsAll ? allModePanel : productModePanel;
      var toPanel = nextIsAll ? allModePanel : productModePanel;
      var switchedPanel = fromPanel && toPanel && fromPanel !== toPanel;

      state.activeMode = normalizedMode;
      state.allType = normalizedMode === 'operations' ? 'operations' : 'materials';
      updateModeButtons();

      if (!fromPanel || !toPanel) {
        if (nextIsAll) {
          syncAllSearchInputHeight();
          loadAllRows({ reset: true });
        }
        scheduleLayoutSync();
        return;
      }

      if (!switchedPanel) {
        if (nextIsAll) {
          syncAllSearchInputHeight();
          renderAllRows();
          loadAllRows({ reset: true });
        }
        scheduleLayoutSync();
        return;
      }

      fromPanel.classList.add('d-none');
      fromPanel.classList.remove('wo-panel-enter', 'wo-panel-exit');
      fromPanel.setAttribute('aria-hidden', 'true');

      toPanel.classList.remove('d-none', 'wo-panel-enter', 'wo-panel-exit');
      toPanel.setAttribute('aria-hidden', 'false');

      if (nextIsAll) {
        syncAllSearchInputHeight();
        renderAllRows();
        loadAllRows({ reset: true });
      }

      scheduleLayoutSync();
    }

    function renderAllRows() {
      if (!allItemsBodyEl) {
        return;
      }

      var rows = Array.isArray(state.allRows) ? state.allRows : [];
      var selectedSet = selectedAllSet(state.allType);

      if (allItemsTitleEl) {
        allItemsTitleEl.textContent = 'Sve stavke - ' + allTypeLabel();
      }

      if (allItemsHeadEl) {
        allItemsHeadEl.innerHTML = '' +
          '<tr>' +
            '<th class="text-center" style="width: 46px;">#</th>' +
            '<th style="width: 70px;">Poz</th>' +
            '<th style="width: 210px;">Sifra</th>' +
            '<th>Opis</th>' +
            '<th style="width: 120px;" class="text-end">Zaliha</th>' +
            '<th style="width: 100px;" class="text-center">MJ</th>' +
            '<th style="width: 90px;" class="text-center">Tip</th>' +
          '</tr>';
      }

      if (!Array.isArray(rows) || rows.length === 0) {
        allItemsBodyEl.innerHTML = '<tr><td colspan="7" class="text-center text-white-50 py-2">Nema stavki za odabrani filter.</td></tr>';
        if (allItemsTotalEl) {
          allItemsTotalEl.textContent = allFoundCountValue(rows);
        }
        return;
      }

      var html = rows.map(function (row, index) {
        var lineNo = Number(row && row.anNo ? row.anNo : (index + 1));
        var componentIdRaw = row && row.acIdentChild ? row.acIdentChild : '-';
        var code = escapeHtml(componentIdRaw);
        var description = row && row.acDescr ? row.acDescr : '-';
        var quantity = formatQuantity(row && row.anGrossQty ? row.anGrossQty : 0);
        var unit = escapeHtml(row && row.acUM ? row.acUM : '-');
        var typeValue = row && row.acOperationType ? row.acOperationType : (state.allType === 'operations' ? 'O' : 'M');
        var type = escapeHtml(typeValue);
        var key = allItemKey(state.allType, componentIdRaw);
        var checked = selectedSet.has(key) ? 'checked' : '';

        return '' +
          '<tr>' +
            '<td class="text-center">' +
              '<input type="checkbox" class="form-check-input bom-all-item-checkbox" ' +
              'data-key="' + escapeHtml(key) + '" ' +
              'data-type="' + escapeHtml(state.allType) + '" ' +
              'data-no="' + escapeHtml(lineNo) + '" ' +
              'data-ident="' + code + '" ' +
              checked + '>' +
            '</td>' +
            '<td>' + lineNo + '</td>' +
            '<td class="fw-semibold">' + code + '</td>' +
            '<td class="wo-opis-cell">' + formatOpisCell(description) + '</td>' +
            '<td class="text-end">' + quantity + '</td>' +
            '<td class="text-center">' + unit + '</td>' +
            '<td class="text-center">' + type + '</td>' +
          '</tr>';
      }).join('');

      allItemsBodyEl.innerHTML = html;
      if (allItemsTotalEl) {
        allItemsTotalEl.textContent = allFoundCountValue(rows);
      }
      scheduleLayoutSync();
    }

    function loadAllRows(options) {
      var settings = options || {};
      var append = Boolean(settings.append);
      var reset = settings.reset !== false;
      var resolvedType = state.allType === 'operations' ? 'operations' : 'materials';
      var endpoint = allEndpointByType(resolvedType);

      if (!endpoint) {
        state.allRows = [];
        resetAllPaging(resolvedType);
        renderAllRows();
        return Promise.resolve();
      }

      if (append) {
        if (resolvedType !== 'materials') {
          return Promise.resolve();
        }
        if (state.allLoadingMoreByType.materials || !canLoadMore('materials')) {
          return Promise.resolve();
        }
        state.allLoadingMoreByType.materials = true;
        setAllLoadingMoreNotice(true);
      } else {
        setAllLoadingMoreNotice(false);
        if (reset) {
          resetAllPaging(resolvedType);
          state.allRows = [];
          renderAllRows();
          if (allTableWrapEl) {
            allTableWrapEl.scrollTop = 0;
          }
        }
        setAllLoading(true);
      }

      state.allRequestSeq += 1;
      var requestSeq = state.allRequestSeq;
      var search = allSearchInput ? String(allSearchInput.value || '').trim() : '';
      var currentOffset = append ? (state.allOffsetByType[resolvedType] || 0) : 0;
      var url = buildUrl(endpoint, {
        q: search,
        limit: 100,
        offset: currentOffset
      });

      return fetch(url, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
        .then(parseResponse)
        .then(function (payload) {
          if (requestSeq !== state.allRequestSeq || resolvedType !== state.allType) {
            return;
          }

          var totalAllRaw = payload && payload.meta ? payload.meta.total_all : null;
          var totalAllParsed = Number(totalAllRaw);
          if (resolvedType === 'materials' && Number.isFinite(totalAllParsed) && totalAllParsed >= 0) {
            state.allTotalByType.materials = Math.floor(totalAllParsed);
          }

          var incomingRows = Array.isArray(payload && payload.data) ? payload.data.slice(0, 100) : [];
          if (resolvedType === 'materials') {
            incomingRows.forEach(function (row) {
              var ident = String(row && row.acIdentChild ? row.acIdentChild : '').trim();
              if (!ident) {
                return;
              }

              var stockValue = Number(row && row.anGrossQty ? row.anGrossQty : 0);
              if (!Number.isFinite(stockValue)) {
                stockValue = 0;
              }

              state.materialStockByIdent.set(stockLookupKey(ident), stockValue);
            });
            refreshQuickSelectionStocks();
          }

          if (append) {
            state.allRows = (state.allRows || []).concat(incomingRows);
          } else {
            state.allRows = incomingRows;
          }

          state.allOffsetByType[resolvedType] = currentOffset + incomingRows.length;
          state.allHasMoreByType[resolvedType] = resolvedType === 'materials' && incomingRows.length >= 100;
          renderAllRows();
        })
        .catch(function () {
          if (requestSeq !== state.allRequestSeq || resolvedType !== state.allType) {
            return;
          }

          if (!append) {
            state.allRows = [];
            resetAllPaging(resolvedType);
            renderAllRows();
          }
        })
        .finally(function () {
          if (requestSeq !== state.allRequestSeq) {
            return;
          }

          if (append) {
            state.allLoadingMoreByType.materials = false;
            setAllLoadingMoreNotice(false);
            return;
          }

          setAllLoadingMoreNotice(false);
          setAllLoading(false);
        });
    }

    function maybeLoadMoreAllRows() {
      if (!allTableWrapEl || state.activeMode !== 'materials' || state.allType !== 'materials') {
        return;
      }

      if (state.allLoading || state.allLoadingMoreByType.materials || !canLoadMore('materials')) {
        return;
      }

      var remaining = allTableWrapEl.scrollHeight - allTableWrapEl.scrollTop - allTableWrapEl.clientHeight;
      if (remaining > 48) {
        return;
      }

      loadAllRows({
        append: true,
        reset: false
      });
    }

    function scheduleAllRowsLoad() {
      if (state.allSearchDebounce) {
        window.clearTimeout(state.allSearchDebounce);
      }

      state.allSearchDebounce = window.setTimeout(function () {
        loadAllRows({
          reset: true
        });
      }, 200);
    }

    function resetSelectionsForOpen() {
      state.selectedKeys.clear();
      state.selectedKeysByProduct.clear();
      state.quickSelections.clear();
      state.selectedAllKeysByType.materials.clear();
      state.selectedAllKeysByType.operations.clear();
    }

    function prefillOperationsForOpen() {
      state.prefillOperationsSeq += 1;
      var requestSeq = state.prefillOperationsSeq;
      var operationsSet = selectedAllSet('operations');
      var prefillOperationCodes = new Set(['op10', 'op30']);
      operationsSet.clear();

      if (!allOperationsUrl) {
        updateSelectionSummary();
        return Promise.resolve();
      }

      var url = buildUrl(allOperationsUrl, {
        q: '',
        limit: 100,
        offset: 0
      });

      return fetch(url, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
        .then(parseResponse)
        .then(function (payload) {
          if (requestSeq !== state.prefillOperationsSeq) {
            return;
          }

          var rows = Array.isArray(payload && payload.data) ? payload.data.slice(0, 100) : [];
          rows.forEach(function (row, index) {
            var componentId = String(row && row.acIdentChild ? row.acIdentChild : '').trim();
            if (!componentId) {
              return;
            }
            if (!prefillOperationCodes.has(componentId.toLowerCase())) {
              return;
            }

            var lineNo = Number(row && row.anNo ? row.anNo : (index + 1));
            var quantity = Number(row && row.anGrossQty ? row.anGrossQty : 0);
            if (!Number.isFinite(quantity)) {
              quantity = 0;
            }

            var normalizedRow = {
              anNo: Number.isFinite(lineNo) ? lineNo : (index + 1),
              acIdentChild: componentId,
              acDescr: row && row.acDescr ? row.acDescr : '-',
              anGrossQty: quantity,
              acOperationType: 'O',
              acUM: row && row.acUM ? row.acUM : 'AUTO'
            };

            operationsSet.add(allItemKey('operations', componentId));
            syncAllSelectionRow('operations', normalizedRow, true);
          });
        })
        .catch(function () {
          if (requestSeq !== state.prefillOperationsSeq) {
            return;
          }
        })
        .finally(function () {
          if (requestSeq !== state.prefillOperationsSeq) {
            return;
          }

          updateSelectionSummary();
          if (state.activeMode === 'operations') {
            renderAllRows();
          }
        });
    }

    function markProceedSource(source) {
      state.proceedSource = source === 'barcode' ? 'barcode' : 'manual';
    }

    function escapeHtml(value) {
      return String(value === null || value === undefined ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    }

    function formatOpisCell(value) {
      var text = String(value === null || value === undefined ? '' : value).replace(/\s+/g, ' ').trim();
      if (!text) {
        text = '-';
      }

      var maxCharsPerLine = 20;

      function pickChunk(input, maxChars, addEllipsisIfTrimmed) {
        var source = String(input || '');
        if (!source) {
          return {
            chunk: '',
            rest: '',
            trimmed: false
          };
        }

        if (source.length <= maxChars) {
          return {
            chunk: source,
            rest: '',
            trimmed: false
          };
        }

        var breakAt = source.lastIndexOf(' ', maxChars);
        if (breakAt <= 0) {
          breakAt = maxChars;
        }

        var chunk = source.slice(0, breakAt).trimEnd();
        var rest = source.slice(breakAt).trimStart();
        var trimmed = rest.length > 0;

        if (addEllipsisIfTrimmed && trimmed) {
          chunk = chunk + '...';
          rest = '';
        }

        return {
          chunk: chunk,
          rest: rest,
          trimmed: trimmed
        };
      }

      var first = pickChunk(text, maxCharsPerLine, false);
      var second = pickChunk(first.rest, maxCharsPerLine, true);
      var firstLine = first.chunk;
      var secondLine = second.chunk;
      var hasSecondLine = secondLine.length > 0;
      var modeClass = hasSecondLine ? 'is-double' : 'is-single';

      if (hasSecondLine) {
        return '' +
          '<span class="wo-opis-two-line ' + modeClass + '" title="' + escapeHtml(text) + '">' +
            '<span>' + escapeHtml(firstLine) + '</span>' +
            '<span>' + escapeHtml(secondLine) + '</span>' +
          '</span>';
      }

      return '' +
        '<span class="wo-opis-two-line ' + modeClass + '" title="' + escapeHtml(text) + '">' +
          '<span>' + escapeHtml(firstLine) + '</span>' +
        '</span>';
    }

    function inferFineAdjustMaterialMode(row) {
      var sourceText = [
        row && row.artikal,
        row && row.opis,
        row && row.napomena,
        row && row.acIdentChild,
        row && row.acDescr
      ].join(' ').toLowerCase();

      if (/(^|[^a-z])alu([^a-z]|$)|alumin/.test(sourceText)) {
        return 'aluminum';
      }

      return 'steel';
    }

    function parseFineAdjustGeometryText(source) {
      var normalized = String(source === null || source === undefined ? '' : source)
        .replace(/,/g, '.')
        .replace(/\s+/g, ' ')
        .trim();

      if (!normalized || normalized === '-') {
        return null;
      }

      var fiMatch = normalized.match(/(?:fi|ø|φ)\s*(\d+(?:\.\d+)?)\s*[x×]\s*(\d+(?:\.\d+)?)/i);
      if (fiMatch) {
        return {
          type: 'round',
          diameter: parseDecimalValue(fiMatch[1], 0),
          length: parseDecimalValue(fiMatch[2], 0),
          label: 'Fi ' + fiMatch[1] + ' x ' + fiMatch[2] + ' mm'
        };
      }

      var rectangularMatch = normalized.match(/(\d+(?:\.\d+)?)\s*[x×]\s*(\d+(?:\.\d+)?)\s*[x×]\s*(\d+(?:\.\d+)?)/i);
      if (rectangularMatch) {
        return {
          type: 'rectangular',
          thickness: parseDecimalValue(rectangularMatch[1], 0),
          width: parseDecimalValue(rectangularMatch[2], 0),
          length: parseDecimalValue(rectangularMatch[3], 0),
          label: rectangularMatch[1] + ' x ' + rectangularMatch[2] + ' x ' + rectangularMatch[3] + ' mm'
        };
      }

      return null;
    }

    function resolveFineAdjustGeometry(row) {
      if (row && row.dim1 && row.dim2 && row.dim3) {
        var d1 = parseDecimalValue(row.dim1, 0);
        var d2 = parseDecimalValue(row.dim2, 0);
        var d3 = parseDecimalValue(row.dim3, 0);

        if (d1 > 0 && d2 > 0 && d3 > 0) {
          return {
            type: 'rectangular',
            thickness: d1,
            width: d2,
            length: d3,
            label: d1 + ' x ' + d2 + ' x ' + d3 + ' mm'
          };
        }
      }

      if (row && row.dim1 && row.dim2 && !row.dim3) {
        var dia = parseDecimalValue(row.dim1, 0);
        var len = parseDecimalValue(row.dim2, 0);

        if (dia > 0 && len > 0) {
          return {
            type: 'round',
            diameter: dia,
            length: len,
            label: 'Fi ' + dia + ' x ' + len + ' mm'
          };
        }
      }

      var candidates = [
        row && row.opis,
        row && row.artikal,
        row && row.acDescr,
        row && row.acIdentChild,
        row && row.napomena
      ];

      for (var index = 0; index < candidates.length; index += 1) {
        var geometry = parseFineAdjustGeometryText(candidates[index]);
        if (geometry) {
          return geometry;
        }
      }

      return null;
    }

    function calculateFineAdjustBgSummary(row) {
      var mode = row && row.materialMode === 'aluminum' ? 'aluminum' : 'steel';
      var modeLabel = mode === 'aluminum' ? 'Alumijum' : '\u010Celik';
      var density = mode === 'aluminum' ? 2800 : 7800;
      var quantityFactor = Math.max(0, parseDecimalValue(row && row.planirano ? row.planirano : 0, 0));
      var geometry = resolveFineAdjustGeometry(row);
      var unitWeight = 0;
      var totalWeight = 0;

      if (!geometry) {
        return {
          mode: mode,
          density: density,
          geometry: null,
          unitWeight: 0,
          totalWeight: 0,
          note: 'Unesite visinu, širinu i dužinu i planiranu količinu za automatski izračun težine.'
        };
      }

      if (geometry.type === 'round') {
        unitWeight = Math.PI * Math.pow(geometry.diameter / 2, 2) * geometry.length * density / 1000000000;
      } else {
        unitWeight = geometry.thickness * geometry.width * geometry.length * density / 1000000000;
      }

      if (!Number.isFinite(unitWeight) || unitWeight < 0) {
        unitWeight = 0;
      }

      totalWeight = unitWeight * quantityFactor;

      return {
        mode: mode,
        density: density,
        geometry: geometry,
        unitWeight: unitWeight,
        totalWeight: totalWeight,
        note: modeLabel +
          ' BG: ' + formatQuantity(totalWeight) + ' kg' +
          ' | 1 kom: ' + formatQuantity(unitWeight) + ' kg' +
          ' | Planirano: ' + formatQuantity(quantityFactor) +
          ' | ' + geometry.label
      };
    }

    function syncFineAdjustComputedRow(row) {
      if (!row || typeof row !== 'object') {
        return row;
      }

      row.materialMode = row.materialMode === 'aluminum' ? 'aluminum' : inferFineAdjustMaterialMode(row);
      row.bgSummary = calculateFineAdjustBgSummary(row);

      var computedNote = row.bgSummary && row.bgSummary.note ? String(row.bgSummary.note) : '';
      var currentNote = String(row.napomena || '').trim();

      if (!currentNote || currentNote === row._computedNapomena || currentNote === '' ) {
        currentNote = computedNote;
      }

      row._computedNapomena = computedNote;
      row.napomena = currentNote;

      return row;
    }

    function syncFineAdjustRowUi(rowIndex) {
      var row = Array.isArray(state.fineAdjustRows) ? state.fineAdjustRows[rowIndex] : null;
      var rowEl = fineAdjustBodyEl ? fineAdjustBodyEl.querySelector('tr[data-row="' + rowIndex + '"]') : null;

      if (!row || !rowEl) {
        return;
      }

      syncFineAdjustComputedRow(row);

      var noteInputEl = rowEl.querySelector('.fine-adjust-input[data-field="napomena"]');
      var toggleWrapEl = rowEl.querySelector('.fine-adjust-material-toggle');
      var mode = row.materialMode === 'aluminum' ? 'aluminum' : 'steel';

      if (noteInputEl) {
        var hint = 'Unesite visinu, širinu i dužinu i planiranu količinu za automatski izračun težine.';
        var computed = row.bgSummary && row.bgSummary.note ? String(row.bgSummary.note) : '';

        if (!row.napomena || row.napomena === row._computedNapomena) {
          noteInputEl.value = (row.napomena || computed || '').trim();
        } else {
          noteInputEl.value = row.napomena;
        }

        noteInputEl.placeholder = hint;
        noteInputEl.title = noteInputEl.value || hint;
      }

      var dim1InputEl = rowEl.querySelector('.fine-adjust-input[data-field="dim1"]');
      var dim2InputEl = rowEl.querySelector('.fine-adjust-input[data-field="dim2"]');
      var dim3InputEl = rowEl.querySelector('.fine-adjust-input[data-field="dim3"]');

      if (dim1InputEl) {
        dim1InputEl.value = String(row.dim1 || '');
      }
      if (dim2InputEl) {
        dim2InputEl.value = String(row.dim2 || '');
      }
      if (dim3InputEl) {
        dim3InputEl.value = String(row.dim3 || '');
      }

      if (toggleWrapEl) {
        toggleWrapEl.classList.toggle('is-aluminum', mode === 'aluminum');
        toggleWrapEl.classList.toggle('is-steel', mode === 'steel');
      }
    }

    function calculateFineAdjustBgSummary(row) {
      var mode = row && row.materialMode === 'aluminum' ? 'aluminum' : 'steel';
      var modeLabel = mode === 'aluminum' ? 'Alumijum' : '\u010Celik';
      var density = mode === 'aluminum' ? 2800 : 7800;
      var geometry = resolveFineAdjustGeometry(row);
      var rawNote = buildFineAdjustRawNote(row);
      var isLocked = hasFineAdjustCompleteDimensions(row) && geometry && geometry.type === 'rectangular';
      var unitWeight = 0;
      var calculationText = '';

      if (!isLocked) {
        return {
          mode: mode,
          density: density,
          geometry: geometry,
          unitWeight: 0,
          totalWeight: 0,
          note: rawNote,
          calculatedPlanirano: '',
          calculationText: calculationText,
          isLocked: false
        };
      }

      unitWeight = geometry.thickness * geometry.width * geometry.length * density / 1000000000;

      if (!Number.isFinite(unitWeight) || unitWeight < 0) {
        unitWeight = 0;
      }

      calculationText =
        formatQuantity(geometry.thickness) +
        ' x ' + formatQuantity(geometry.width) +
        ' x ' + formatQuantity(geometry.length) +
        ' x ' + density +
        ' / 1e9 = ' + formatQuantity(unitWeight) + ' kg';

      return {
        mode: mode,
        density: density,
        geometry: geometry,
        unitWeight: unitWeight,
        totalWeight: unitWeight,
        note: rawNote,
        calculatedPlanirano: formatQuantity(unitWeight),
        calculationText: calculationText,
        isLocked: true
      };
    }

    function syncFineAdjustComputedRow(row) {
      if (!row || typeof row !== 'object') {
        return row;
      }

      row.materialMode = row.materialMode === 'aluminum' ? 'aluminum' : inferFineAdjustMaterialMode(row);
      var previousAutoNote = String(row._computedNapomena || '').trim();
      var previousAutoPlanirano = String(row._computedPlanirano || '').trim();
      var previousLockState = row._fineAdjustLocked === true;
      row.bgSummary = calculateFineAdjustBgSummary(row);

      var computedNote = row.bgSummary && row.bgSummary.note ? String(row.bgSummary.note).trim() : '';
      var computedPlanirano = row.bgSummary && row.bgSummary.calculatedPlanirano
        ? String(row.bgSummary.calculatedPlanirano).trim()
        : '';
      var isLocked = Boolean(row.bgSummary && row.bgSummary.isLocked);
      var currentNote = String(row.napomena || '').trim();
      var currentPlanirano = String(row.planirano || '').trim();

      if (isLocked) {
        currentNote = computedNote;
        currentPlanirano = computedPlanirano;

        if (!row.mj || String(row.mj).trim().toUpperCase() === 'AUTO') {
          row.mj = 'KG';
        }
      } else {
        if (previousLockState && currentNote === previousAutoNote) {
          currentNote = '';
        }

        if (previousLockState && currentPlanirano === previousAutoPlanirano) {
          currentPlanirano = '';
        }
      }

      if (!isLocked && currentPlanirano === '') {
        currentPlanirano = '0';
      }

      row._computedNapomena = computedNote;
      row._computedPlanirano = computedPlanirano;
      row._fineAdjustLocked = isLocked;
      row.napomena = currentNote;
      row.planirano = currentPlanirano;

      return row;
    }

    function syncFineAdjustRowUi(rowIndex) {
      var row = Array.isArray(state.fineAdjustRows) ? state.fineAdjustRows[rowIndex] : null;
      var rowEl = fineAdjustBodyEl ? fineAdjustBodyEl.querySelector('tr[data-row="' + rowIndex + '"]') : null;

      if (!row || !rowEl) {
        return;
      }

      syncFineAdjustComputedRow(row);

      var noteInputEl = rowEl.querySelector('.fine-adjust-input[data-field="napomena"]');
      var noteStackEl = rowEl.querySelector('.fine-adjust-note-stack');
      var planInputEl = rowEl.querySelector('.fine-adjust-input[data-field="planirano"]');
      var planStackEl = rowEl.querySelector('.fine-adjust-plan-stack');
      var planHintEl = rowEl.querySelector('.fine-adjust-plan-hint');
      var toggleWrapEl = rowEl.querySelector('.fine-adjust-material-toggle');
      var mode = row.materialMode === 'aluminum' ? 'aluminum' : 'steel';
      var isLocked = row._fineAdjustLocked === true;
      var showMaterialToggle = hasFineAdjustCompleteDimensions(row);
      var calculationText = row.bgSummary && row.bgSummary.calculationText
        ? String(row.bgSummary.calculationText)
        : '';

      if (noteInputEl) {
        if (!isLocked) {
          var liveNoteValue = String(noteInputEl.value || '').trim();
          if (liveNoteValue !== '' && liveNoteValue !== String(row.napomena || '').trim()) {
            row.napomena = liveNoteValue;
          }
        }

        noteInputEl.value = String(row.napomena || '').trim();
        noteInputEl.placeholder = isLocked ? '' : 'Unesite napomenu';
        noteInputEl.title = noteInputEl.value || noteInputEl.placeholder;
        noteInputEl.readOnly = isLocked;
        noteInputEl.classList.toggle('is-locked', isLocked);
      }

      if (noteStackEl) {
        noteStackEl.classList.toggle('is-locked', isLocked);
        noteStackEl.classList.toggle('is-material-hidden', !showMaterialToggle);
        noteStackEl.classList.toggle('is-aluminum', mode === 'aluminum');
        noteStackEl.classList.toggle('is-steel', mode === 'steel');
      }

      var dim1InputEl = rowEl.querySelector('.fine-adjust-input[data-field="dim1"]');
      var dim2InputEl = rowEl.querySelector('.fine-adjust-input[data-field="dim2"]');
      var dim3InputEl = rowEl.querySelector('.fine-adjust-input[data-field="dim3"]');

      if (dim1InputEl) {
        dim1InputEl.value = String(row.dim1 || '');
      }
      if (dim2InputEl) {
        dim2InputEl.value = String(row.dim2 || '');
      }
      if (dim3InputEl) {
        dim3InputEl.value = String(row.dim3 || '');
      }

      if (planInputEl) {
        planInputEl.value = String(row.planirano || '').trim();
        planInputEl.placeholder = '';
        planInputEl.title = calculationText;
        planInputEl.readOnly = isLocked;
        planInputEl.classList.toggle('is-locked', isLocked);
      }

      if (planStackEl) {
        planStackEl.classList.toggle('has-hint', calculationText !== '');
        planStackEl.classList.toggle('is-locked', isLocked);
        planStackEl.classList.toggle('is-aluminum', mode === 'aluminum');
        planStackEl.classList.toggle('is-steel', mode === 'steel');
      }

      if (planHintEl) {
        planHintEl.textContent = calculationText;
        planHintEl.title = calculationText;
        planHintEl.classList.toggle('is-locked', isLocked);
      }

      if (toggleWrapEl) {
        toggleWrapEl.classList.toggle('d-none', !showMaterialToggle);
        toggleWrapEl.classList.toggle('is-aluminum', mode === 'aluminum');
        toggleWrapEl.classList.toggle('is-steel', mode === 'steel');
      }
    }

    function buildFineAdjustRowsFromSelection() {
      return selectedDetailedRows()
        .map(function (row, index) {
          var planiranoQty = '';
          var zalihaQty = Number(row.stockQty);
          if (!Number.isFinite(zalihaQty)) {
            zalihaQty = resolveKnownStock(row.acIdentChild || '');
          }
          if (!Number.isFinite(zalihaQty)) {
            zalihaQty = 0;
          }

          var parsedGeometry = resolveFineAdjustGeometry(row);
          var dim1 = '';
          var dim2 = '';
          var dim3 = '';

          if (parsedGeometry && parsedGeometry.type === 'rectangular') {
            dim1 = parsedGeometry.thickness ? String(parsedGeometry.thickness) : '';
            dim2 = parsedGeometry.width ? String(parsedGeometry.width) : '';
            dim3 = parsedGeometry.length ? String(parsedGeometry.length) : '';
          } else if (parsedGeometry && parsedGeometry.type === 'round') {
            dim1 = parsedGeometry.diameter ? String(parsedGeometry.diameter) : '';
            dim2 = parsedGeometry.length ? String(parsedGeometry.length) : '';
            dim3 = '';
          }

          return {
            alternativa: '0',
            pozicija: String(index + 1),
            artikal: String(row.acIdentChild || '').trim(),
            opis: String(row.acDescr || '').trim(),
            dim1: dim1,
            dim2: dim2,
            dim3: dim3,
            acOperationType: String(row.acOperationType || '').trim(),
            acUMSource: String(row.acUM || '').trim(),
            slika: '-',
            napomena: String(row.napomena || '').trim(),
            planirano: String(planiranoQty),
            zaliha: String(zalihaQty),
            mj: String(row.acUM || 'AUTO').trim() || 'AUTO',
            materialMode: inferFineAdjustMaterialMode(row)
          };
        })
        .map(function (row) {
          return syncFineAdjustComputedRow(row);
        });
    }

    function renderFineAdjustRows() {
      if (!fineAdjustBodyEl) {
        return;
      }

      if (!Array.isArray(state.fineAdjustRows) || state.fineAdjustRows.length === 0) {
        fineAdjustBodyEl.innerHTML = '<tr><td colspan="12" class="text-center text-muted py-2">Nema odabranih stavki.</td></tr>';
        if (fineAdjustCountEl) {
          fineAdjustCountEl.textContent = 'Stavki: 0';
        }
        return;
      }

      var fields = [
        'alternativa', 'pozicija', 'artikal', 'opis', 'slika', 'dim1', 'dim2', 'dim3', 'napomena', 'planirano', 'zaliha', 'mj'
      ];

      var html = state.fineAdjustRows.map(function (row, rowIndex) {
        var cells = fields.map(function (field) {
          if (field === 'napomena') {
            syncFineAdjustComputedRow(row);
            var noteValue = escapeHtml(row[field] || '');
            var mode = row.materialMode === 'aluminum' ? 'aluminum' : 'steel';

            return '' +
              '<td class="fine-adjust-note-cell">' +
                '<div class="fine-adjust-note-stack">' +
                  '<div class="fine-adjust-material-toggle ' + (mode === 'aluminum' ? 'is-aluminum' : 'is-steel') + '">' +
                    '<span class="fine-adjust-material-label is-aluminum"><i class="fa fa-cubes"></i><span>Alumijum</span></span>' +
                    '<button type="button" class="fine-adjust-material-switch" data-row="' + rowIndex + '" aria-label="Prebaci materijal izmedu Alumijuma i \u010Celika">' +
                      '<span class="fine-adjust-material-switch-thumb"></span>' +
                    '</button>' +
                    '<span class="fine-adjust-material-label is-steel"><i class="fa fa-industry"></i><span>\u010Celik</span></span>' +
                  '</div>' +
                  '<input type="text" class="form-control form-control-sm fine-adjust-input fine-adjust-note-input" data-row="' + rowIndex + '" data-field="napomena" value="' + noteValue + '" placeholder="Unesite visinu, širinu i dužinu i planiranu količinu" />' +
                '</div>' +
              '</td>';
          }

          if (field === 'mj') {
            var rawMjValue = String(row[field] || '').trim().toUpperCase();
            var mjValue = (rawMjValue === 'AUTO' || rawMjValue === 'KG' || rawMjValue === 'MJ') ? rawMjValue : 'AUTO';
            return '' +
              '<td><select class="form-select form-select-sm fine-adjust-input fine-adjust-select" data-row="' + rowIndex + '" data-field="mj">' +
                '<option value="AUTO"' + (mjValue === 'AUTO' ? ' selected' : '') + '>AUTO</option>' +
                '<option value="KG"' + (mjValue === 'KG' ? ' selected' : '') + '>KG</option>' +
                '<option value="MJ"' + (mjValue === 'MJ' ? ' selected' : '') + '>MJ</option>' +
              '</select></td>';
          }

          if (field === 'zaliha') {
            var readonlyValue = escapeHtml(row[field] || '0');
            return '<td><input type="text" class="form-control form-control-sm fine-adjust-readonly" value="' + readonlyValue + '" readonly tabindex="-1"></td>';
          }

          if (field === 'dim1' || field === 'dim2' || field === 'dim3') {
            var dimensionValue = escapeHtml(row[field] || '');
            return '<td><input type="number" class="form-control form-control-sm fine-adjust-input" ' +
              'data-row="' + rowIndex + '" data-field="' + field + '" value="' + dimensionValue + '" step="0.01" min="0"></td>';
          }

          var value = escapeHtml(row[field] || '');
          var inputType = (field === 'planirano' || field === 'pozicija' || field === 'alternativa')
            ? 'number'
            : 'text';
          var stepAttr = inputType === 'number' ? ' step="0.0001"' : '';
          var minAttr = field === 'planirano' ? ' min="0"' : '';

          return '<td><input type="' + inputType + '" class="form-control form-control-sm fine-adjust-input" ' +
            'data-row="' + rowIndex + '" data-field="' + field + '" value="' + value + '"' + stepAttr + minAttr + '></td>';
        }).join('');

        return '<tr data-row="' + rowIndex + '">' + cells + '</tr>';
      }).join('');

      fineAdjustBodyEl.innerHTML = html;

      if (fineAdjustCountEl) {
        fineAdjustCountEl.textContent = 'Stavki: ' + state.fineAdjustRows.length;
      }
    }

    function collectFineAdjustRowsFromDom() {
      if (!fineAdjustBodyEl) {
        return [];
      }

      var rows = [];
      var rowElements = fineAdjustBodyEl.querySelectorAll('tr[data-row]');

      rowElements.forEach(function (rowEl) {
        var rowIndex = Number(rowEl.getAttribute('data-row') || 0);
        var existingRow = Array.isArray(state.fineAdjustRows) ? state.fineAdjustRows[rowIndex] : null;
        var rowData = existingRow && typeof existingRow === 'object'
          ? Object.assign({}, existingRow)
          : {};
        var inputs = rowEl.querySelectorAll('.fine-adjust-input[data-field]');

        inputs.forEach(function (inputEl) {
          var field = String(inputEl.getAttribute('data-field') || '').trim();
          if (!field) {
            return;
          }

          rowData[field] = String(inputEl.value || '').trim();
        });

        rowData.planirano = String(
          clampPlaniranoToZaliha(
            rowData.planirano,
            rowData.zaliha
          )
        );

        rows.push(rowData);
      });

      return rows;
    }

    function renderFineAdjustRows() {
      if (!fineAdjustBodyEl) {
        return;
      }

      if (!Array.isArray(state.fineAdjustRows) || state.fineAdjustRows.length === 0) {
        fineAdjustBodyEl.innerHTML = '<tr><td colspan="12" class="text-center text-muted py-2">Nema odabranih stavki.</td></tr>';
        if (fineAdjustCountEl) {
          fineAdjustCountEl.textContent = 'Stavki: 0';
        }
        return;
      }

      var fields = [
        'alternativa', 'pozicija', 'artikal', 'opis', 'slika', 'dim1', 'dim2', 'dim3', 'napomena', 'planirano', 'zaliha', 'mj'
      ];

      var html = state.fineAdjustRows.map(function (row, rowIndex) {
        syncFineAdjustComputedRow(row);

        var cells = fields.map(function (field) {
          if (field === 'napomena') {
            var noteValue = escapeHtml(row[field] || '');
            var mode = row.materialMode === 'aluminum' ? 'aluminum' : 'steel';
            var noteLocked = row._fineAdjustLocked === true;
            var showMaterialToggle = hasFineAdjustCompleteDimensions(row);

            return '' +
              '<td class="fine-adjust-note-cell">' +
                '<div class="fine-adjust-note-stack' + (noteLocked ? ' is-locked' : '') + (showMaterialToggle ? '' : ' is-material-hidden') + '">' +
                  '<div class="fine-adjust-material-toggle ' + (mode === 'aluminum' ? 'is-aluminum' : 'is-steel') + (showMaterialToggle ? '' : ' d-none') + '">' +
                    '<span class="fine-adjust-material-label is-aluminum"><i class="fa fa-cubes"></i><span>Alumijum</span></span>' +
                    '<button type="button" class="fine-adjust-material-switch" data-row="' + rowIndex + '" aria-label="Prebaci materijal izmedu Alumijuma i \u010Celika">' +
                      '<span class="fine-adjust-material-switch-thumb"></span>' +
                    '</button>' +
                    '<span class="fine-adjust-material-label is-steel"><i class="fa fa-industry"></i><span>\u010Celik</span></span>' +
                  '</div>' +
                  '<input type="text" class="form-control form-control-sm fine-adjust-input fine-adjust-note-input' + (noteLocked ? ' is-locked' : '') + '" data-row="' + rowIndex + '" data-field="napomena" value="' + noteValue + '" placeholder="' + (noteLocked ? '' : 'Unesite napomenu') + '"' + (noteLocked ? ' readonly' : '') + ' />' +
                '</div>' +
              '</td>';
          }

          if (field === 'mj') {
            var rawMjValue = String(row[field] || '').trim().toUpperCase();
            var mjValue = (rawMjValue === 'AUTO' || rawMjValue === 'KG' || rawMjValue === 'MJ') ? rawMjValue : 'AUTO';
            return '' +
              '<td><select class="form-select form-select-sm fine-adjust-input fine-adjust-select" data-row="' + rowIndex + '" data-field="mj">' +
                '<option value="AUTO"' + (mjValue === 'AUTO' ? ' selected' : '') + '>AUTO</option>' +
                '<option value="KG"' + (mjValue === 'KG' ? ' selected' : '') + '>KG</option>' +
                '<option value="MJ"' + (mjValue === 'MJ' ? ' selected' : '') + '>MJ</option>' +
              '</select></td>';
          }

          if (field === 'zaliha') {
            var readonlyValue = escapeHtml(row[field] || '0');
            return '<td><input type="text" class="form-control form-control-sm fine-adjust-readonly" value="' + readonlyValue + '" readonly tabindex="-1"></td>';
          }

          if (field === 'dim1' || field === 'dim2' || field === 'dim3') {
            var dimensionValue = escapeHtml(row[field] || '');
            return '<td><input type="text" inputmode="decimal" autocomplete="off" spellcheck="false" class="form-control form-control-sm fine-adjust-input" ' +
              'data-row="' + rowIndex + '" data-field="' + field + '" value="' + dimensionValue + '"></td>';
          }

          if (field === 'planirano') {
            var planValue = escapeHtml(row[field] || '0');
            var planLocked = row._fineAdjustLocked === true;
            var planHint = escapeHtml(
              row && row.bgSummary && row.bgSummary.calculationText
                ? String(row.bgSummary.calculationText)
                : ''
            );

            return '' +
              '<td class="fine-adjust-plan-cell">' +
                '<div class="fine-adjust-plan-stack' + (planLocked ? ' is-locked' : '') + (planHint ? ' has-hint' : '') + '">' +
                  '<input type="text" inputmode="decimal" autocomplete="off" spellcheck="false" class="form-control form-control-sm fine-adjust-input fine-adjust-plan-input' + (planLocked ? ' is-locked' : '') + '" ' +
                    'data-row="' + rowIndex + '" data-field="planirano" value="' + planValue + '" placeholder="" title="' + planHint + '"' + (planLocked ? ' readonly' : '') + '>' +
                  '<div class="fine-adjust-plan-hint' + (planLocked ? ' is-locked' : '') + '" title="' + planHint + '">' + planHint + '</div>' +
                '</div>' +
              '</td>';
          }

          var value = escapeHtml(row[field] || '');
          var numericInputAttrs = (field === 'pozicija' || field === 'alternativa')
            ? ' type="text" inputmode="decimal" autocomplete="off" spellcheck="false"'
            : ' type="text"';

          return '<td><input' + numericInputAttrs + ' class="form-control form-control-sm fine-adjust-input" ' +
            'data-row="' + rowIndex + '" data-field="' + field + '" value="' + value + '"></td>';
        }).join('');

        return '<tr data-row="' + rowIndex + '">' + cells + '</tr>';
      }).join('');

      fineAdjustBodyEl.innerHTML = html;

      state.fineAdjustRows.forEach(function (unusedRow, rowIndex) {
        syncFineAdjustRowUi(rowIndex);
      });

      if (fineAdjustCountEl) {
        fineAdjustCountEl.textContent = 'Stavki: ' + state.fineAdjustRows.length;
      }
    }

    function collectFineAdjustRowsFromDom() {
      if (!fineAdjustBodyEl) {
        return [];
      }

      var rows = [];
      var rowElements = fineAdjustBodyEl.querySelectorAll('tr[data-row]');

      rowElements.forEach(function (rowEl) {
        var rowIndex = Number(rowEl.getAttribute('data-row') || 0);
        var existingRow = Array.isArray(state.fineAdjustRows) ? state.fineAdjustRows[rowIndex] : null;
        var rowData = existingRow && typeof existingRow === 'object'
          ? Object.assign({}, existingRow)
          : {};
        var inputs = rowEl.querySelectorAll('.fine-adjust-input[data-field]');

        inputs.forEach(function (inputEl) {
          var field = String(inputEl.getAttribute('data-field') || '').trim();
          if (!field) {
            return;
          }

          rowData[field] = String(inputEl.value || '').trim();
        });

        syncFineAdjustComputedRow(rowData);

        if (String(rowData.planirano || '').trim() !== '') {
          rowData.planirano = String(
            clampPlaniranoToZaliha(
              rowData.planirano,
              rowData.zaliha
            )
          );
        } else {
          rowData.planirano = '';
        }

        rows.push(rowData);
      });

      return rows;
    }

    function hasSelect2() {
      return Boolean(window.jQuery && window.jQuery.fn && typeof window.jQuery.fn.select2 === 'function');
    }

    function normalizeProductOption(product) {
      var id = String((product && (product.acIdent || product.acIdentTrimmed)) || '').trim();
      var name = String((product && product.acName) || '').trim();
      var text = String((product && product.label) || '').trim();

      if (!id) {
        return null;
      }

      if (!text && name) {
        text = id + ' - ' + name;
      }

      if (!text) {
        text = String((product && (product.acIdentTrimmed || product.acIdent)) || '').trim();
      }

      return {
        id: id,
        text: text || id
      };
    }

    function ensureProductOptionSelected(productId, productText) {
      if (!productSelect) {
        return;
      }

      var normalizedId = String(productId || '').trim();
      if (!normalizedId) {
        return;
      }

      var hasOption = false;
      for (var index = 0; index < productSelect.options.length; index += 1) {
        if (String(productSelect.options[index].value || '').trim() === normalizedId) {
          hasOption = true;
          break;
        }
      }

      if (!hasOption) {
        var option = document.createElement('option');
        option.value = normalizedId;
        option.textContent = String(productText || normalizedId).trim() || normalizedId;
        productSelect.appendChild(option);
      }

      productSelect.value = normalizedId;

      if (hasSelect2()) {
        window.jQuery(productSelect).trigger('change.select2');
      }
    }

    function initProductSelect2() {
      if (!productSelect || !productsUrl || !hasSelect2()) {
        return;
      }

      var $select = window.jQuery(productSelect);
      if ($select.data('select2')) {
        syncAllSearchInputHeight();
        return;
      }

      $select.select2({
        width: '100%',
        placeholder: 'Pretrazite proizvod',
        allowClear: true,
        dropdownParent: window.jQuery(modalEl),
        minimumInputLength: 0,
        ajax: {
          url: productsUrl,
          dataType: 'json',
          delay: 200,
          data: function (params) {
            return {
              q: String((params && params.term) || '').trim(),
              selected: String((productSelect && productSelect.value) || '').trim()
            };
          },
          processResults: function (payload, params) {
            params.page = params.page || 1;

            var rows = Array.isArray(payload && payload.data) ? payload.data : [];
            var selectedValue = String((productSelect && productSelect.value) || '').trim();
            var selectedText = '';
            var options = productSelect ? productSelect.options : null;

            if (selectedValue && options && productSelect.selectedIndex > -1) {
              selectedText = String(options[productSelect.selectedIndex].text || '').trim();
            }

            var items = rows
              .map(normalizeProductOption)
              .filter(function (item) {
                return item !== null;
              });

            if (selectedValue) {
              var selectedIndex = items.findIndex(function (item) {
                return item.id === selectedValue;
              });

              if (selectedIndex > -1) {
                var selectedItem = items.splice(selectedIndex, 1)[0];
                selectedItem.selected = true;
                items.unshift(selectedItem);
              } else {
                items.unshift({
                  id: selectedValue,
                  text: selectedText || selectedValue,
                  selected: true
                });
              }
            }

            var start = (params.page - 1) * select2PageSize;
            var end = start + select2PageSize;

            return {
              results: items.slice(start, end),
              pagination: { more: end < items.length }
            };
          },
          cache: false
        },
        language: {
          searching: function () {
            return 'Pretražujem';
          },
          loadingMore: function () {
            return 'Učitavanje još rezultata...';
          },
          noResults: function () {
            return 'Nema rezultata';
          }
        }
      });

      if (!state.select2EventsBound) {
        $select.on('select2:select', function () {
          markProceedSource('manual');
          loadBomForSelectedProduct();
          syncAllSearchInputHeight();
        });

        $select.on('select2:clear', function () {
          markProceedSource('manual');
          state.bomRows = [];
          state.selectedKeys.clear();
          renderBomRows();
          setBomLoading(false);
          setStatus('Izaberite proizvod iz liste za učitavanje sastavnice', 'warning');
          syncAllSearchInputHeight();
        });

        state.select2EventsBound = true;
      }

      window.requestAnimationFrame(syncAllSearchInputHeight);
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
        option.textContent = product.label || product.acIdentTrimmed || product.acIdent || '';
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

      if (hasSelect2()) {
        window.jQuery(productSelect).trigger('change.select2');
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
            '<td class="wo-opis-cell">' + formatOpisCell(descr) + '</td>' +
            '<td class="text-end">' + baseQty.toFixed(4).replace(/0+$/, '').replace(/\.$/, '') + '</td>' +
            '<td class="text-center">' + operationType + '</td>' +
          '</tr>';
      }).join('');

      componentsBody.innerHTML = html;
      updateSelectionSummary();
      scheduleLayoutSync();
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
        state.bomRows = [];
        state.selectedKeys.clear();
        renderBomRows();
        setBomLoading(false);
        setStatus('Izaberite proizvod prije učitavanja sastavnice.', 'warning');
        return Promise.resolve();
      }

      if (!bomUrl) {
        setBomLoading(false);
        return Promise.resolve();
      }

      state.bomRequestSeq += 1;
      var requestSeq = state.bomRequestSeq;
      state.loadingBom = true;
      showError('');
      setBomLoading(true);
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
          if (requestSeq !== state.bomRequestSeq) {
            return;
          }

          state.bomRows = Array.isArray(payload.data) ? payload.data.slice(0, 100) : [];
          applyStoredSelectionToCurrentRows(productId);
          renderBomRows();

          if (state.bomRows.length === 0) {
            setStatus('Sastavnica nije pronađena za odabrani proizvod.', 'warning');
            return;
          }

          setStatus('', null);
        })
        .catch(function (error) {
          if (requestSeq !== state.bomRequestSeq) {
            return;
          }

          state.bomRows = [];
          state.selectedKeys.clear();
          renderBomRows();
          setStatus('Ne mogu učitati sastavnicu.', 'danger');
          showError(error && error.message ? error.message : 'Greška pri učitavanju sastavnice.');
        })
        .finally(function () {
          if (requestSeq === state.bomRequestSeq) {
            state.loadingBom = false;
            setBomLoading(false);
          }
        });
    }

    function savePlannedConsumptionRequest(selectedComponents, quantity, quantityUnit, description, saveMode, triggerStatusTransition) {
      var productId = (productSelect && productSelect.value) ? productSelect.value : '';
      var resolvedDescription = typeof description === 'string' ? String(description).trim() : '';
      var resolvedSaveMode = saveMode === 'barcode' ? 'barcode' : 'manual';
      var resolvedTriggerStatusTransition = triggerStatusTransition !== false;

      if (!saveUrl) {
        notify('error', 'Nedostaje endpoint', 'Snimanje planirane potrosnje nije dostupno.');
        return null;
      }

      if (!productId) {
        notify('warning', 'Nedostaje proizvod', 'Izaberite proizvod prije snimanja.');
        return null;
      }

      if (!Array.isArray(selectedComponents) || selectedComponents.length === 0) {
        notify('warning', 'Nema komponenti', 'Izaberite barem jednu komponentu.');
        return null;
      }

      if (!Number.isFinite(quantity) || quantity <= 0) {
        notify('warning', 'Neispravna kolicina', 'Unesite količinu vecću od 0');
        return null;
      }

      return fetch(saveUrl, {
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
          save_mode: resolvedSaveMode,
          trigger_status_transition: resolvedTriggerStatusTransition,
          description: resolvedDescription,
          components: selectedComponents
        })
      }).then(parseResponse);
    }

    function saveFineAdjustedConsumption() {
      var editedRows = collectFineAdjustRowsFromDom();
      state.fineAdjustRows = editedRows.slice();

      var unique = new Map();
      editedRows.forEach(function (row) {
        var componentId = String((row && row.artikal) || '').trim();
        var lineNo = Number((row && row.pozicija) || 0);
        var opis = String((row && row.opis) || '').trim();
        var unitValue = String((row && row.mj) || '').trim().toUpperCase();
        var sourceUnitValue = String((row && row.acUMSource) || '').trim().toUpperCase();
        var operationTypeValue = String((row && row.acOperationType) || '').trim().toUpperCase();
        var planiranoRawValue = String((row && row.planirano) || '').trim();
        var planiranoValue = parseDecimalValue(planiranoRawValue, 0);
        var zalihaValue = parseDecimalValue((row && row.zaliha) || 0, 0);

        if (!componentId || !Number.isFinite(lineNo)) {
          return;
        }

        var key = bomKey(lineNo, componentId);
        var normalizedOperationType = (operationTypeValue === 'M' || operationTypeValue === 'O')
          ? operationTypeValue
          : '';

        var normalizedSourceUnit = sourceUnitValue === 'AUTO'
          ? ''
          : sourceUnitValue.slice(0, 3);

        var normalizedUnit = (unitValue === 'KG' || unitValue === 'MJ')
          ? unitValue
          : normalizedSourceUnit;

        unique.set(key, {
          acIdentChild: componentId,
          anNo: lineNo,
          acDescr: opis,
          dim1: String((row && row.dim1) || '').trim(),
          dim2: String((row && row.dim2) || '').trim(),
          dim3: String((row && row.dim3) || '').trim(),
          acUM: normalizedUnit,
          acUMSource: normalizedSourceUnit,
          acOperationType: normalizedOperationType,
          anPlanQty: planiranoRawValue === ''
            ? null
            : clampPlaniranoToZaliha(planiranoValue, zalihaValue),
          napomena: String((row && row.napomena) || '').trim()
        });
      });

      var selected = Array.from(unique.values());
      var quantity = 1;
      var unitSet = new Set(
        editedRows.map(function (row) {
          var value = String((row && row.mj) || '').trim().toUpperCase();
          return (value === 'AUTO' || value === 'KG' || value === 'MJ') ? value : 'AUTO';
        })
      );
      var quantityUnit = unitSet.size === 1 ? Array.from(unitSet)[0] : 'AUTO';
      var description = '';

      submitPlannedConsumption(
        selected,
        quantity,
        quantityUnit,
        description,
        fineAdjustSaveBtn,
        '<i class="fa fa-check me-50"></i> Potvrdi i dodaj na RN'
      );
    }

    function saveFineAdjustedConsumption() {
      var editedRows = collectFineAdjustRowsFromDom();
      state.fineAdjustRows = editedRows.slice();

      var unique = new Map();
      editedRows.forEach(function (row) {
        var componentId = String((row && row.artikal) || '').trim();
        var lineNo = Number((row && row.pozicija) || 0);
        var opis = String((row && row.opis) || '').trim();
        var unitValue = String((row && row.mj) || '').trim().toUpperCase();
        var sourceUnitValue = String((row && row.acUMSource) || '').trim().toUpperCase();
        var operationTypeValue = String((row && row.acOperationType) || '').trim().toUpperCase();
        var planiranoRawValue = String((row && row.planirano) || '').trim();
        var zalihaValue = parseDecimalValue((row && row.zaliha) || 0, 0);

        if (!componentId || !Number.isFinite(lineNo)) {
          return;
        }

        var key = bomKey(lineNo, componentId);
        var normalizedOperationType = (operationTypeValue === 'M' || operationTypeValue === 'O')
          ? operationTypeValue
          : '';

        var normalizedSourceUnit = sourceUnitValue === 'AUTO'
          ? ''
          : sourceUnitValue.slice(0, 3);

        var normalizedUnit = (unitValue === 'KG' || unitValue === 'MJ')
          ? unitValue
          : normalizedSourceUnit;

        unique.set(key, {
          acIdentChild: componentId,
          anNo: lineNo,
          acDescr: opis,
          dim1: String((row && row.dim1) || '').trim(),
          dim2: String((row && row.dim2) || '').trim(),
          dim3: String((row && row.dim3) || '').trim(),
          acUM: normalizedUnit,
          acUMSource: normalizedSourceUnit,
          acOperationType: normalizedOperationType,
          anPlanQty: planiranoRawValue === ''
            ? null
            : clampPlaniranoToZaliha(planiranoRawValue, zalihaValue),
          napomena: String((row && row.napomena) || '').trim()
        });
      });

      var selected = Array.from(unique.values());
      var quantity = 1;
      var unitSet = new Set(
        editedRows.map(function (row) {
          var value = String((row && row.mj) || '').trim().toUpperCase();
          return (value === 'AUTO' || value === 'KG' || value === 'MJ') ? value : 'AUTO';
        })
      );
      var quantityUnit = unitSet.size === 1 ? Array.from(unitSet)[0] : 'AUTO';
      var description = '';

      submitPlannedConsumption(
        selected,
        quantity,
        quantityUnit,
        description,
        fineAdjustSaveBtn,
        '<i class="fa fa-check me-50"></i> Potvrdi i dodaj na RN'
      );
    }

    function saveFineAdjustedConsumption() {
      var editedRows = collectFineAdjustRowsFromDom();
      state.fineAdjustRows = editedRows.slice();

      var unique = new Map();
      editedRows.forEach(function (row) {
        var componentId = String((row && row.artikal) || '').trim();
        var lineNo = Number((row && row.pozicija) || 0);
        var opis = String((row && row.opis) || '').trim();
        var unitValue = String((row && row.mj) || '').trim().toUpperCase();
        var sourceUnitValue = String((row && row.acUMSource) || '').trim().toUpperCase();
        var operationTypeValue = String((row && row.acOperationType) || '').trim().toUpperCase();
        var planiranoRawValue = String((row && row.planirano) || '').trim();
        var zalihaValue = parseDecimalValue((row && row.zaliha) || 0, 0);

        if (!componentId || !Number.isFinite(lineNo)) {
          return;
        }

        var key = bomKey(lineNo, componentId);
        var normalizedOperationType = (operationTypeValue === 'M' || operationTypeValue === 'O')
          ? operationTypeValue
          : '';

        var normalizedSourceUnit = sourceUnitValue === 'AUTO'
          ? ''
          : sourceUnitValue.slice(0, 3);

        var normalizedUnit = (unitValue === 'KG' || unitValue === 'MJ')
          ? unitValue
          : normalizedSourceUnit;

        unique.set(key, {
          acIdentChild: componentId,
          anNo: lineNo,
          acDescr: opis,
          dim1: String((row && row.dim1) || '').trim(),
          dim2: String((row && row.dim2) || '').trim(),
          dim3: String((row && row.dim3) || '').trim(),
          acUM: normalizedUnit,
          acUMSource: normalizedSourceUnit,
          acOperationType: normalizedOperationType,
          anPlanQty: planiranoRawValue === ''
            ? null
            : clampPlaniranoToZaliha(planiranoRawValue, zalihaValue),
          napomena: String((row && row.napomena) || '').trim()
        });
      });

      var selected = Array.from(unique.values());
      var quantity = 1;
      var unitSet = new Set(
        editedRows.map(function (row) {
          var value = String((row && row.mj) || '').trim().toUpperCase();
          return (value === 'AUTO' || value === 'KG' || value === 'MJ') ? value : 'AUTO';
        })
      );
      var quantityUnit = unitSet.size === 1 ? Array.from(unitSet)[0] : 'AUTO';
      var description = '';

      submitPlannedConsumption(
        selected,
        quantity,
        quantityUnit,
        description,
        fineAdjustSaveBtn,
        '<i class="fa fa-check me-50"></i> Potvrdi i dodaj na RN'
      );
    }

    function resetConfirmContext() {
      state.confirmSelectionRows = [];
      state.confirmContext = null;
      state.proceedSource = 'manual';

      if (confirmDetailsWrapEl) {
        confirmDetailsWrapEl.classList.add('d-none');
      }

      if (confirmMaterialActionEl) {
        confirmMaterialActionEl.classList.remove('is-update');
        confirmMaterialActionEl.textContent = 'Dodat ce novu stavku na radni nalog.';
      }

      if (confirmHelpTextEl) {
        confirmHelpTextEl.textContent = 'Unesi faktor kolicine za planiranu potrosnju.';
      }

      if (confirmSaveBtn) {
        confirmSaveBtn.innerHTML = confirmSaveIdleHtml;
      }
    }

    function currentConfirmRows() {
      if (Array.isArray(state.confirmSelectionRows) && state.confirmSelectionRows.length > 0) {
        return state.confirmSelectionRows.map(function (row) {
          return Object.assign({}, row);
        });
      }

      return selectedRows();
    }

    function currentConfirmMode() {
      return state.confirmContext && state.confirmContext.mode === 'barcode' ? 'barcode' : 'manual';
    }

    function resolveConfirmButtonHtml(context) {
      if (!context || context.mode !== 'barcode') {
        return confirmSaveIdleHtml;
      }

      if (context.action === 'update') {
        return '<i class="fa fa-refresh me-2"></i> Ažuriraj postojecu stavku';
      }

      return '<i class="fa fa-plus me-2"></i> Dodaj materijal na RN';
    }

    function resolveBarcodeQuantityUnit(materialPayload) {
      var unit = String(materialPayload && materialPayload.material_um ? materialPayload.material_um : '').trim().toUpperCase();

      if (unit === 'KG' || unit === 'RDS' || unit === 'MJ') {
        return unit;
      }

      return 'AUTO';
    }

    function applyConfirmContext(selectedRowsInput, context) {
      var selected = Array.isArray(selectedRowsInput) ? selectedRowsInput.map(function (row) {
        return Object.assign({}, row);
      }) : [];
      var resolvedContext = context && typeof context === 'object'
        ? Object.assign({}, context)
        : { mode: 'manual' };

      state.confirmSelectionRows = selected;
      state.confirmContext = resolvedContext;

      if (confirmLabelEl) {
        if (resolvedContext.mode === 'barcode') {
          confirmLabelEl.textContent = String(
            (resolvedContext.material && resolvedContext.material.material_name)
              || (selected[0] && (selected[0].acDescr || selected[0].acIdentChild))
              || 'Skenirani materijal'
          );
        } else {
          confirmLabelEl.textContent = selected.length + ' komponenti';
        }
      }

      if (resolvedContext.mode !== 'barcode') {
        if (confirmDetailsWrapEl) {
          confirmDetailsWrapEl.classList.add('d-none');
        }
        if (confirmHelpTextEl) {
          confirmHelpTextEl.textContent = 'Unesi faktor kolicine za planiranu potrosnju.';
        }
        if (confirmSaveBtn) {
          confirmSaveBtn.innerHTML = confirmSaveIdleHtml;
        }
        return;
      }

      var material = resolvedContext.material || {};
      var existingItem = material.existing_item || null;
      var action = resolvedContext.action === 'update' ? 'update' : 'insert';

      if (confirmDetailsWrapEl) {
        confirmDetailsWrapEl.classList.remove('d-none');
      }

      if (confirmMaterialCodeEl) {
        confirmMaterialCodeEl.textContent = String(material.material_code || '-');
      }
      if (confirmMaterialTitleEl) {
        confirmMaterialTitleEl.textContent = String(material.material_name || '-');
      }
      if (confirmMaterialUnitEl) {
        confirmMaterialUnitEl.textContent = String(material.material_um || '-');
      }
      if (confirmMaterialCurrentQtyEl) {
        confirmMaterialCurrentQtyEl.textContent = formatQuantity(existingItem && existingItem.qty ? existingItem.qty : 0);
      }
      if (confirmMaterialStockQtyEl) {
        confirmMaterialStockQtyEl.textContent = formatQuantity(material.stock_qty || 0);
      }
      if (confirmMaterialActionEl) {
        confirmMaterialActionEl.classList.toggle('is-update', action === 'update');
        confirmMaterialActionEl.textContent = action === 'update'
          ? 'Postojeća stavka na RN ce biti ažurirana novom tezinom.'
          : 'Materijal ne postoji na RN i bit će dodan kao nova stavka sastavnice.';
      }
      if (confirmHelpTextEl) {
        confirmHelpTextEl.textContent = action === 'update'
          ? 'Prilagodite količinu i mjernu jedinicu koja se dopisuje na postojeći materijal RN.'
          : 'Unesite količinu i mjernu jedinicu skeniranog materijala koji se dodaje na RN.';
      }
      if (quantityUnitSelect) {
        quantityUnitSelect.value = resolveBarcodeQuantityUnit(material);
      }
      if (confirmSaveBtn) {
        confirmSaveBtn.innerHTML = resolveConfirmButtonHtml(resolvedContext);
      }
    }

    function openQuantityModal(preselectedRows, context) {
      var selected = Array.isArray(preselectedRows) ? preselectedRows : selectedRows();

      if (selected.length === 0) {
        setStatus('Izaberite barem jednu komponentu.', 'warning');
        return;
      }

      if (!confirmModalEl || !window.bootstrap || !window.bootstrap.Modal) {
        notify('error', 'Modal nije dostupan', 'Ne mogu otvoriti unos kolicine.');
        return;
      }

      applyConfirmContext(selected, context);

      if (quantityInput) {
        if (context && context.mode === 'barcode') {
          quantityInput.value = '1';
        } else if (!quantityInput.value || Number(quantityInput.value) <= 0) {
          quantityInput.value = '1';
        }
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

    function openFineAdjustModal(preselectedRows) {
      var selected = Array.isArray(preselectedRows) ? preselectedRows : selectedRows();

      if (selected.length === 0) {
        setStatus('Izaberite barem jednu komponentu.', 'warning');
        return;
      }

      if (!fineAdjustModalEl || !window.bootstrap || !window.bootstrap.Modal) {
        notify('error', 'Modal nije dostupan', 'Ne mogu otvoriti fine adjust BOM.');
        return;
      }

      state.fineAdjustRows = buildFineAdjustRowsFromSelection();
      renderFineAdjustRows();

      var fineAdjustModal = window.bootstrap.Modal.getOrCreateInstance(fineAdjustModalEl);
      fineAdjustModal.show();
    }

    function openProceedFlow(forcedSource) {
      var selected = selectedRows();
      if (selected.length === 0) {
        setStatus('Izaberite barem jednu komponentu.', 'warning');
        return;
      }

      var source = forcedSource || state.proceedSource;
      if (source === 'barcode' && state.confirmContext && state.confirmContext.mode === 'barcode') {
        openQuantityModal(selected, state.confirmContext || { mode: 'barcode', action: 'insert' });
        return;
      }

      openFineAdjustModal(selected);
    }

    function hideModalIfOpen(modalNode) {
      if (!modalNode || !window.bootstrap || !window.bootstrap.Modal) {
        return;
      }

      var instance = window.bootstrap.Modal.getInstance(modalNode);
      if (instance) {
        instance.hide();
      }
    }

    function handleSaveSuccess(payload) {
      var savedCount = payload && payload.data ? Number(payload.data.saved_count || 0) : 0;
      var stockAdjustments = payload && payload.data && Array.isArray(payload.data.stock_adjustments)
        ? payload.data.stock_adjustments
        : [];
      var message = payload && payload.message ? payload.message : 'Planirana potrošnja je uspješno sačuvana.';

      stockAdjustments.forEach(function (item) {
        if (!item) {
          return;
        }

        applyLocalStockValue(
          item.material_code || item.code || item.acIdent || '',
          item.new_stock_value
        );
      });

      var itemsLine = 'Broj stavki: ' + savedCount;
      var successHtml = (
        '<span style="display:block;">' + escapeHtml(message) + '</span>' +
        '<span style="display:block;margin-top:0.45rem;">' + escapeHtml(itemsLine) + '</span>'
      );

      if (window.Swal && typeof window.Swal.fire === 'function') {
        var successOptions = {
          icon: 'success',
          title: 'Uspješno',
          html: successHtml
        };

        if (typeof window.woSwalWithTheme === 'function') {
          successOptions = window.woSwalWithTheme(successOptions);
        }

        window.Swal.fire(successOptions);
      } else {
        notify('success', 'Uspješno', message + ' ' + itemsLine);
      }
      setStatus('Planirana potrošnja je uspješno sačuvana.', 'success');
      resetConfirmContext();
      hideModalIfOpen(confirmModalEl);
      hideModalIfOpen(fineAdjustModalEl);

      window.setTimeout(function () {
        window.location.reload();
      }, 150);
    }

    function submitPlannedConsumption(selectedComponents, quantity, quantityUnit, description, buttonEl, idleHtml) {
      if (state.saving) {
        return;
      }

      var triggerStatusTransition = !!(confirmModalEl && confirmModalEl.classList.contains('show'));

      var request = savePlannedConsumptionRequest(
        selectedComponents,
        quantity,
        quantityUnit,
        description,
        currentConfirmMode(),
        triggerStatusTransition
      );
      if (!request || typeof request.then !== 'function') {
        return;
      }

      state.saving = true;
      if (buttonEl) {
        buttonEl.disabled = true;
        buttonEl.innerHTML = '<span class="spinner-border spinner-border-sm me-50" role="status" aria-hidden="true"></span> Snimam';
      }

      request
        .then(function (payload) {
          handleSaveSuccess(payload);
        })
        .catch(function (error) {
          notify('error', 'Greska', error && error.message ? error.message : 'Snimanje nije uspjelo.');
        })
        .finally(function () {
          state.saving = false;
          if (buttonEl) {
            buttonEl.disabled = false;
            buttonEl.innerHTML = idleHtml;
          }
        });
    }

    function savePlannedConsumption() {
      var selected = currentConfirmRows();
      var quantity = quantityInput ? Number(quantityInput.value || 0) : 0;
      var quantityUnit = quantityUnitSelect ? String(quantityUnitSelect.value || 'AUTO').toUpperCase() : 'AUTO';

      submitPlannedConsumption(
        selected,
        quantity,
        quantityUnit,
        '',
        confirmSaveBtn,
        resolveConfirmButtonHtml(state.confirmContext)
      );
    }

    function saveFineAdjustedConsumption() {
      var editedRows = collectFineAdjustRowsFromDom();
      state.fineAdjustRows = editedRows.slice();

      var unique = new Map();
      editedRows.forEach(function (row) {
        var componentId = String((row && row.artikal) || '').trim();
        var lineNo = Number((row && row.pozicija) || 0);
        var opis = String((row && row.opis) || '').trim();
        var unitValue = String((row && row.mj) || '').trim().toUpperCase();
        var sourceUnitValue = String((row && row.acUMSource) || '').trim().toUpperCase();
        var operationTypeValue = String((row && row.acOperationType) || '').trim().toUpperCase();
        var planiranoValue = parseDecimalValue((row && row.planirano) || 0, 0);
        var zalihaValue = parseDecimalValue((row && row.zaliha) || 0, 0);

        if (!componentId || !Number.isFinite(lineNo)) {
          return;
        }

        var key = bomKey(lineNo, componentId);
        var normalizedOperationType = (operationTypeValue === 'M' || operationTypeValue === 'O')
          ? operationTypeValue
          : '';

        var normalizedSourceUnit = sourceUnitValue === 'AUTO'
          ? ''
          : sourceUnitValue.slice(0, 3);

        var normalizedUnit = (unitValue === 'KG' || unitValue === 'MJ')
          ? unitValue
          : normalizedSourceUnit;

        var normalizedPlanirano = clampPlaniranoToZaliha(planiranoValue, zalihaValue);
        var placeholderNote = 'Unesite visinu, širinu i dužinu i planiranu količinu za automatski izračun težine.';
        var napomenaValue = String((row && row.napomena) || '').trim();

        if (napomenaValue === placeholderNote) {
          napomenaValue = '0';
        }

        if (napomenaValue === '' && row && row.bgSummary && row.bgSummary.note === placeholderNote) {
          napomenaValue = '0';
        }

        unique.set(key, {
          acIdentChild: componentId,
          anNo: lineNo,
          acDescr: opis,
          dim1: String((row && row.dim1) || '').trim(),
          dim2: String((row && row.dim2) || '').trim(),
          dim3: String((row && row.dim3) || '').trim(),
          acUM: normalizedUnit,
          acUMSource: normalizedSourceUnit,
          acOperationType: normalizedOperationType,
          anPlanQty: normalizedPlanirano,
          napomena: napomenaValue
        });
      });

      var selected = Array.from(unique.values());
      var quantity = 1;
      var unitSet = new Set(
        editedRows.map(function (row) {
          var value = String((row && row.mj) || '').trim().toUpperCase();
          return (value === 'AUTO' || value === 'KG' || value === 'MJ') ? value : 'AUTO';
        })
      );
      var quantityUnit = unitSet.size === 1 ? Array.from(unitSet)[0] : 'AUTO';
      var description = '';

      submitPlannedConsumption(
        selected,
        quantity,
        quantityUnit,
        description,
        fineAdjustSaveBtn,
        '<i class="fa fa-check me-50"></i> Potvrdi i dodaj na RN'
      );
    }

    function resetConfirmContext() {
      state.confirmSelectionRows = [];
      state.confirmContext = null;
      state.proceedSource = 'manual';

      if (confirmDetailsWrapEl) {
        confirmDetailsWrapEl.classList.add('d-none');
      }

      if (confirmMaterialCodeWrapEl) {
        confirmMaterialCodeWrapEl.classList.add('d-none');
      }

      if (confirmMaterialTitleEl) {
        confirmMaterialTitleEl.textContent = '-';
      }

      if (confirmMaterialSetEl) {
        confirmMaterialSetEl.textContent = '-';
      }

      if (confirmMaterialQidEl) {
        confirmMaterialQidEl.textContent = '-';
      }

      if (confirmMaterialRnLineEl) {
        confirmMaterialRnLineEl.textContent = '-';
      }

      if (confirmMaterialDeadlineEl) {
        confirmMaterialDeadlineEl.textContent = '-';
      }

      if (confirmMaterialUpdatedAtEl) {
        confirmMaterialUpdatedAtEl.textContent = '-';
      }

      if (confirmMaterialActionEl) {
        confirmMaterialActionEl.classList.add('d-none');
        confirmMaterialActionEl.classList.remove('is-update');
        confirmMaterialActionEl.textContent = 'Dodat ce novu stavku na radni nalog.';
      }

      if (confirmHelpTextEl) {
        confirmHelpTextEl.textContent = 'Unesi faktor kolicine za planiranu potrosnju.';
      }

      setQuantityInputValue('1');
      setQuantityUnitValue('AUTO');

      if (confirmSaveBtn) {
        confirmSaveBtn.innerHTML = confirmSaveIdleHtml;
      }
    }

    function resolveConfirmButtonHtml(context) {
      var subtitle = 'Sačuvaj';

      if (context && context.mode === 'barcode') {
        subtitle = context.action === 'update' ? 'Ažuriraj' : 'Dodaj';
      }

      return '<span class="confirm-weight-ok-label">OK</span><span class="confirm-weight-ok-subtitle">' + subtitle + '</span>';
    }

    function applyConfirmContext(selectedRowsInput, context) {
      var selected = Array.isArray(selectedRowsInput) ? selectedRowsInput.map(function (row) {
        return Object.assign({}, row);
      }) : [];
      var resolvedContext = context && typeof context === 'object'
        ? Object.assign({}, context)
        : { mode: 'manual' };

      state.confirmSelectionRows = selected;
      state.confirmContext = resolvedContext;

      if (confirmLabelEl) {
        if (resolvedContext.mode === 'barcode') {
          confirmLabelEl.textContent = String(
            (resolvedContext.material && resolvedContext.material.material_name)
              || (selected[0] && (selected[0].acDescr || selected[0].acIdentChild))
              || 'Skenirani materijal'
          );
        } else {
          confirmLabelEl.textContent = selected.length + ' komponenti';
        }
      }

      if (resolvedContext.mode !== 'barcode') {
        if (confirmDetailsWrapEl) {
          confirmDetailsWrapEl.classList.add('d-none');
        }

        if (confirmMaterialCodeWrapEl) {
          confirmMaterialCodeWrapEl.classList.add('d-none');
        }

        if (confirmMaterialActionEl) {
          confirmMaterialActionEl.classList.add('d-none');
        }

        if (confirmHelpTextEl) {
          confirmHelpTextEl.textContent = 'Unesi faktor kolicine za planiranu potrosnju.';
        }

        setQuantityUnitValue('AUTO');

        if (confirmSaveBtn) {
          confirmSaveBtn.innerHTML = confirmSaveIdleHtml;
        }

        updateConfirmQuantityScreen();
        return;
      }

      var material = resolvedContext.material || {};
      var existingItem = material.existing_item || null;
      var action = resolvedContext.action === 'update' ? 'update' : 'insert';

      if (confirmDetailsWrapEl) {
        confirmDetailsWrapEl.classList.remove('d-none');
      }

      if (confirmMaterialCodeWrapEl) {
        confirmMaterialCodeWrapEl.classList.remove('d-none');
      }

      if (confirmMaterialCodeEl) {
        confirmMaterialCodeEl.textContent = String(material.material_code || '-');
      }

      if (confirmMaterialSetEl) {
        confirmMaterialSetEl.textContent = String(material.material_set || '-');
      }

      if (confirmMaterialTitleEl) {
        confirmMaterialTitleEl.textContent = String(material.material_name || '-');
      }

      if (confirmMaterialUnitEl) {
        confirmMaterialUnitEl.textContent = String(material.material_um || '-');
      }

      if (confirmMaterialQidEl) {
        confirmMaterialQidEl.textContent = String(material.material_qid || '-');
      }

      if (confirmMaterialCurrentQtyEl) {
        confirmMaterialCurrentQtyEl.textContent = formatQuantity(existingItem && existingItem.qty ? existingItem.qty : 0);
      }

      if (confirmMaterialRnLineEl) {
        confirmMaterialRnLineEl.textContent = existingItem && existingItem.no ? String(existingItem.no) : '-';
      }

      if (confirmMaterialStockQtyEl) {
        confirmMaterialStockQtyEl.textContent = formatQuantity(material.stock_qty || 0);
      }

      if (confirmMaterialDeadlineEl) {
        confirmMaterialDeadlineEl.textContent = String(material.material_delivery_deadline_display || '-');
      }

      if (confirmMaterialUpdatedAtEl) {
        confirmMaterialUpdatedAtEl.textContent = String(material.material_changed_display || '-');
      }

      if (confirmMaterialActionEl) {
        confirmMaterialActionEl.classList.remove('d-none');
        confirmMaterialActionEl.classList.toggle('is-update', action === 'update');
        confirmMaterialActionEl.textContent = action === 'update'
          ? 'Postojeća stavka na RN biće ažurirana novom težinom.'
          : 'Materijal ne postoji na RN i biće dodan kao nova stavka sastavnice';
      }

      if (confirmHelpTextEl) {
        confirmHelpTextEl.textContent = action === 'update'
          ? 'Prilagodite količinu i mjernu jedinicu koja se dopisuje na postojeci materijal RN'
          : 'Unesite količinu i mjernu jedinicu skeniranog materijala koji se dodaje na RN';
      }

      setQuantityUnitValue(resolveBarcodeQuantityUnit(material));

      if (confirmSaveBtn) {
        confirmSaveBtn.innerHTML = resolveConfirmButtonHtml(resolvedContext);
      }

      updateConfirmQuantityScreen();
    }

    function openQuantityModal(preselectedRows, context) {
      var selected = Array.isArray(preselectedRows) ? preselectedRows : selectedRows();

      if (selected.length === 0) {
        setStatus('Izaberite barem jednu komponentu.', 'warning');
        return;
      }

      if (!confirmModalEl || !window.bootstrap || !window.bootstrap.Modal) {
        notify('error', 'Modal nije dostupan', 'Ne mogu otvoriti unos kolicine.');
        return;
      }

      applyConfirmContext(selected, context);
      setQuantityInputValue('1');

      var confirmModal = window.bootstrap.Modal.getOrCreateInstance(confirmModalEl);
      confirmModal.show();
    }

    function savePlannedConsumption() {
      var selected = currentConfirmRows();
      var rawQuantityValue = quantityInput ? normalizeQuantityInputValue(quantityInput.value || '') : '';
      var quantity = rawQuantityValue ? Number(rawQuantityValue) : 0;
      var quantityUnit = quantityUnitSelect ? String(quantityUnitSelect.value || 'AUTO').toUpperCase() : 'AUTO';

      if (!(quantity > 0)) {
        notify('warning', 'Nedostaje količina', 'Unesite količinu preko tastature na ekranu.');
        return;
      }

      submitPlannedConsumption(
        selected,
        quantity,
        quantityUnit,
        '',
        confirmSaveBtn,
        resolveConfirmButtonHtml(state.confirmContext)
      );
    }

    if (confirmKeypadEl) {
      confirmKeypadEl.addEventListener('click', function (event) {
        var target = event.target ? event.target.closest('[data-keypad-value], [data-keypad-action]') : null;

        if (!target) {
          return;
        }

        var action = String(target.getAttribute('data-keypad-action') || '').trim().toLowerCase();
        var value = String(target.getAttribute('data-keypad-value') || '');

        if (action === 'clear') {
          clearQuantityInputValue();
          return;
        }

        if (value !== '') {
          appendQuantityInputToken(value);
        }
      });
    }

    if (confirmBackspaceBtn) {
      confirmBackspaceBtn.addEventListener('click', function () {
        backspaceQuantityInputValue();
      });
    }

    if (confirmUnitSwitchEl) {
      confirmUnitSwitchEl.addEventListener('click', function (event) {
        var button = event.target ? event.target.closest('.confirm-weight-unit-btn') : null;

        if (!button) {
          return;
        }

        setQuantityUnitValue(button.getAttribute('data-unit') || 'AUTO');
      });
    }

    if (quantityUnitSelect) {
      quantityUnitSelect.addEventListener('change', function () {
        syncConfirmUnitButtons();
      });
    }

    if (quantityInput) {
      ['keydown', 'keypress', 'paste', 'beforeinput', 'mousedown', 'mouseup', 'touchstart', 'focus'].forEach(function (eventName) {
        quantityInput.addEventListener(eventName, function (event) {
          if (eventName === 'focus' && typeof quantityInput.blur === 'function') {
            quantityInput.blur();
          }

          if (event && typeof event.preventDefault === 'function') {
            event.preventDefault();
          }
        });
      });
    }

    updateConfirmQuantityScreen();

    if (componentsBody) {
      bindManualStockAdjust(componentsBody, function (row) {
        var checkbox = row ? row.querySelector('.bom-component-checkbox') : null;
        if (!checkbox) {
          return null;
        }

        return findBomRowDataByKey(
          String(checkbox.getAttribute('data-key') || '').trim(),
          Number(checkbox.getAttribute('data-no') || 0),
          String(checkbox.getAttribute('data-ident') || '').trim()
        );
      });

      bindSelectableRowClick(componentsBody, '.bom-component-checkbox');

      componentsBody.addEventListener('change', function (event) {
        var target = event.target;

        if (!target || !target.classList.contains('bom-component-checkbox')) {
          return;
        }

        var key = target.getAttribute('data-key') || '';
        var lineNo = Number(target.getAttribute('data-no') || 0);
        var componentId = String(target.getAttribute('data-ident') || '').trim();
        var productId = currentProductId();

        if (!key) {
          return;
        }

        if (target.checked) {
          state.selectedKeys.add(key);
        } else {
          state.selectedKeys.delete(key);
        }

        var rowData = state.bomRows.find(function (row) {
          return bomKey(row.anNo, row.acIdentChild) === key;
        });

        if (!rowData && componentId) {
          rowData = {
            anNo: lineNo,
            acIdentChild: componentId,
            acDescr: '-',
            anGrossQty: 0,
            acOperationType: '',
            acUM: 'AUTO'
          };
        }

        syncQuickSelectionRow(productId, rowData, target.checked);
        storeSelectionForProduct(productId, state.selectedKeys);

        if (target.checked && componentId) {
          ensureKnownStockForComponent(componentId);
        }

        markProceedSource('manual');
        updateSelectionSummary();
      });
    }

    if (allItemsBodyEl) {
      bindManualStockAdjust(allItemsBodyEl, function (row) {
        var checkbox = row ? row.querySelector('.bom-all-item-checkbox') : null;
        var rowType = checkbox && String(checkbox.getAttribute('data-type') || '').trim() === 'operations'
          ? 'operations'
          : 'materials';

        if (!checkbox) {
          return null;
        }

        return findAllRowDataByKey(
          rowType,
          String(checkbox.getAttribute('data-key') || '').trim(),
          Number(checkbox.getAttribute('data-no') || 0),
          String(checkbox.getAttribute('data-ident') || '').trim()
        );
      });

      bindSelectableRowClick(allItemsBodyEl, '.bom-all-item-checkbox');

      allItemsBodyEl.addEventListener('change', function (event) {
        var target = event.target;

        if (!target || !target.classList.contains('bom-all-item-checkbox')) {
          return;
        }

        var rowKey = String(target.getAttribute('data-key') || '').trim();
        var rowType = String(target.getAttribute('data-type') || '').trim() === 'operations' ? 'operations' : 'materials';
        var componentId = String(target.getAttribute('data-ident') || '').trim();
        var lineNo = Number(target.getAttribute('data-no') || 0);
        var selectedSet = selectedAllSet(rowType);

        if (!rowKey || !componentId) {
          return;
        }

        if (target.checked) {
          selectedSet.add(rowKey);
        } else {
          selectedSet.delete(rowKey);
        }

        var rowData = state.allRows.find(function (row) {
          return allItemKey(rowType, row && row.acIdentChild ? row.acIdentChild : '') === rowKey;
        });

        if (!rowData) {
          rowData = {
            anNo: lineNo,
            acIdentChild: componentId,
            acDescr: '-',
            anGrossQty: 0,
            acOperationType: rowType === 'operations' ? 'O' : 'M',
            acUM: 'AUTO'
          };
        }

        syncAllSelectionRow(rowType, rowData, target.checked);
        markProceedSource('manual');
        updateSelectionSummary();
      });
    }

    function saveFineAdjustedConsumption() {
      var editedRows = collectFineAdjustRowsFromDom();
      state.fineAdjustRows = editedRows.slice();

      var unique = new Map();
      editedRows.forEach(function (row) {
        var componentId = String((row && row.artikal) || '').trim();
        var lineNo = Number((row && row.pozicija) || 0);
        var opis = String((row && row.opis) || '').trim();
        var unitValue = String((row && row.mj) || '').trim().toUpperCase();
        var sourceUnitValue = String((row && row.acUMSource) || '').trim().toUpperCase();
        var operationTypeValue = String((row && row.acOperationType) || '').trim().toUpperCase();
        var planiranoRawValue = String((row && row.planirano) || '').trim();
        var zalihaValue = parseDecimalValue((row && row.zaliha) || 0, 0);

        if (!componentId || !Number.isFinite(lineNo)) {
          return;
        }

        var key = bomKey(lineNo, componentId);
        var normalizedOperationType = (operationTypeValue === 'M' || operationTypeValue === 'O')
          ? operationTypeValue
          : '';

        var normalizedSourceUnit = sourceUnitValue === 'AUTO'
          ? ''
          : sourceUnitValue.slice(0, 3);

        var normalizedUnit = (unitValue === 'KG' || unitValue === 'MJ')
          ? unitValue
          : normalizedSourceUnit;

        unique.set(key, {
          acIdentChild: componentId,
          anNo: lineNo,
          acDescr: opis,
          dim1: String((row && row.dim1) || '').trim(),
          dim2: String((row && row.dim2) || '').trim(),
          dim3: String((row && row.dim3) || '').trim(),
          acUM: normalizedUnit,
          acUMSource: normalizedSourceUnit,
          acOperationType: normalizedOperationType,
          anPlanQty: planiranoRawValue === ''
            ? null
            : clampPlaniranoToZaliha(planiranoRawValue, zalihaValue),
          napomena: String((row && row.napomena) || '').trim()
        });
      });

      var selected = Array.from(unique.values());
      var quantity = 1;
      var unitSet = new Set(
        editedRows.map(function (row) {
          var value = String((row && row.mj) || '').trim().toUpperCase();
          return (value === 'AUTO' || value === 'KG' || value === 'MJ') ? value : 'AUTO';
        })
      );
      var quantityUnit = unitSet.size === 1 ? Array.from(unitSet)[0] : 'AUTO';
      var description = '';

      submitPlannedConsumption(
        selected,
        quantity,
        quantityUnit,
        description,
        fineAdjustSaveBtn,
        '<i class="fa fa-check me-50"></i> Potvrdi i dodaj na RN'
      );
    }

    if (productSelect && !hasSelect2()) {
      productSelect.addEventListener('change', function () {
        markProceedSource('manual');
        loadBomForSelectedProduct();
      });
    }

    if (openQuantityBtn) {
      openQuantityBtn.addEventListener('click', function () {
        openProceedFlow();
      });
    }

    if (confirmSaveBtn) {
      confirmSaveBtn.addEventListener('click', function () {
        savePlannedConsumption();
      });
    }

    if (fineAdjustSaveBtn) {
      fineAdjustSaveBtn.addEventListener('click', function () {
        saveFineAdjustedConsumption();
      });
    }

    if (fineAdjustBodyEl) {
      var fineAdjustNavigationFields = [
        'alternativa', 'pozicija', 'artikal', 'opis', 'dim1', 'dim2', 'dim3', 'napomena', 'planirano', 'mj'
      ];
      var fineAdjustNumericFields = [
        'alternativa', 'pozicija', 'dim1', 'dim2', 'dim3', 'planirano'
      ];

      var isFineAdjustNumericField = function (field) {
        return fineAdjustNumericFields.indexOf(field) !== -1;
      };

      var isFineAdjustNavigableInput = function (element) {
        return !!(
          element &&
          element.classList &&
          element.classList.contains('fine-adjust-input') &&
          !element.disabled &&
          !element.readOnly
        );
      };

      var focusFineAdjustNavigableInput = function (element) {
        if (!isFineAdjustNavigableInput(element)) {
          return false;
        }

        element.focus();

        if (element.tagName === 'INPUT' && typeof element.select === 'function') {
          try {
            element.select();
          } catch (error) {
          }
        }

        return true;
      };

      var findFineAdjustNavigableInput = function (rowIndex, field) {
        if (!Number.isFinite(rowIndex) || rowIndex < 0 || !field) {
          return null;
        }

        var selector = '.fine-adjust-input[data-row="' + rowIndex + '"][data-field="' + field + '"]';
        var element = fineAdjustBodyEl.querySelector(selector);

        return isFineAdjustNavigableInput(element) ? element : null;
      };

      var findFineAdjustHorizontalTarget = function (rowIndex, field, direction) {
        var fieldIndex = fineAdjustNavigationFields.indexOf(field);
        if (fieldIndex === -1) {
          return null;
        }

        var nextIndex = fieldIndex + direction;
        while (nextIndex >= 0 && nextIndex < fineAdjustNavigationFields.length) {
          var target = findFineAdjustNavigableInput(rowIndex, fineAdjustNavigationFields[nextIndex]);
          if (target) {
            return target;
          }

          nextIndex += direction;
        }

        return null;
      };

      var readFineAdjustSelection = function (element) {
        if (!element || element.tagName !== 'INPUT') {
          return null;
        }

        try {
          if (typeof element.selectionStart === 'number' && typeof element.selectionEnd === 'number') {
            return {
              start: element.selectionStart,
              end: element.selectionEnd
            };
          }
        } catch (error) {
        }

        return null;
      };

      var shouldNavigateFineAdjustHorizontally = function (element, key) {
        if (!element) {
          return false;
        }

        if (element.tagName === 'SELECT') {
          return true;
        }

        var value = String(element.value || '');
        var selection = readFineAdjustSelection(element);
        if (value === '') {
          return true;
        }

        if (!selection || selection.start !== selection.end) {
          return false;
        }

        if (key === 'ArrowRight') {
          return selection.end >= value.length;
        }

        if (key === 'ArrowLeft') {
          return selection.start <= 0;
        }

        return false;
      };

      fineAdjustBodyEl.addEventListener('keydown', function (event) {
        var target = event.target;
        if (!isFineAdjustNavigableInput(target)) {
          return;
        }

        var key = String(event.key || '');
        if (key !== 'ArrowLeft' && key !== 'ArrowRight' && key !== 'ArrowUp' && key !== 'ArrowDown') {
          return;
        }

        var field = String(target.getAttribute('data-field') || '').trim();
        var rowIndex = Number(target.getAttribute('data-row') || -1);

        if (key === 'ArrowUp' || key === 'ArrowDown') {
          event.preventDefault();

          if (!field || !Number.isFinite(rowIndex) || rowIndex < 0) {
            return;
          }

          var verticalTarget = findFineAdjustNavigableInput(
            rowIndex + (key === 'ArrowUp' ? -1 : 1),
            field
          );

          if (!verticalTarget) {
            return;
          }

          focusFineAdjustNavigableInput(verticalTarget);
          return;
        }

        if (event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) {
          return;
        }

        if (!field || !Number.isFinite(rowIndex) || rowIndex < 0) {
          return;
        }

        if (!shouldNavigateFineAdjustHorizontally(target, key)) {
          return;
        }

        var horizontalTarget = findFineAdjustHorizontalTarget(
          rowIndex,
          field,
          key === 'ArrowLeft' ? -1 : 1
        );

        if (!horizontalTarget) {
          return;
        }

        event.preventDefault();
        focusFineAdjustNavigableInput(horizontalTarget);
      });

      fineAdjustBodyEl.addEventListener('input', function (event) {
        var target = event.target;
        if (!target || !target.classList || !target.classList.contains('fine-adjust-input')) {
          return;
        }

        var field = String(target.getAttribute('data-field') || '').trim();
        var rowIndex = Number(target.getAttribute('data-row') || -1);
        if (!Number.isFinite(rowIndex) || rowIndex < 0) {
          return;
        }

        if (isFineAdjustNumericField(field) && target.tagName === 'INPUT') {
          var normalizedNumericValue = normalizeQuantityInputValue(target.value);
          if (normalizedNumericValue !== String(target.value || '')) {
            target.value = normalizedNumericValue;
          }
        }

        var row = Array.isArray(state.fineAdjustRows) ? state.fineAdjustRows[rowIndex] : null;
        if (!row) {
          return;
        }

        if (field === 'planirano') {
          if (row._fineAdjustLocked === true) {
            syncFineAdjustRowUi(rowIndex);
            return;
          }

          var zalihaValue = row ? row.zaliha : 0;
          var clamped = clampPlaniranoToZaliha(target.value, zalihaValue);
          var current = parseDecimalValue(target.value, 0);

          if (clamped !== current) {
            target.value = formatQuantity(clamped);
          }

          row.planirano = String(target.value || '').trim();
          syncFineAdjustRowUi(rowIndex);
          return;
        }

        if (field === 'napomena') {
          row.napomena = String(target.value || '').trim();
          return;
        }

        if (field === 'artikal' || field === 'opis' || field === 'dim1' || field === 'dim2' || field === 'dim3') {
          row[field] = String(target.value || '').trim();
          syncFineAdjustRowUi(rowIndex);
          return;
        }

        if (field === 'mj') {
          row.mj = String(target.value || '').trim().toUpperCase();
          syncFineAdjustRowUi(rowIndex);
          return;
        }
      });

      fineAdjustBodyEl.addEventListener('click', function (event) {
        var toggleButton = event.target && event.target.closest
          ? event.target.closest('.fine-adjust-material-switch')
          : null;
        if (!toggleButton) {
          return;
        }

        event.preventDefault();

        var rowIndex = Number(toggleButton.getAttribute('data-row') || -1);
        if (!Number.isFinite(rowIndex) || rowIndex < 0) {
          return;
        }

        var row = Array.isArray(state.fineAdjustRows) ? state.fineAdjustRows[rowIndex] : null;
        if (!row) {
          return;
        }

        row.materialMode = row.materialMode === 'aluminum' ? 'steel' : 'aluminum';
        syncFineAdjustRowUi(rowIndex);
      });
    }

    if (modeButtons && modeButtons.length > 0) {
      Array.prototype.forEach.call(modeButtons, function (button) {
        button.addEventListener('click', function () {
          var mode = String(button.getAttribute('data-mode') || '').trim();
          showModePanel(mode);
        });
      });
    }

    if (allSearchInput) {
      allSearchInput.addEventListener('input', function () {
        scheduleAllRowsLoad();
      });
    }

    if (allTableWrapEl) {
      allTableWrapEl.addEventListener('scroll', function () {
        maybeLoadMoreAllRows();
      });
    }

    if (scannerMirrorToggle) {
      scannerMirrorToggle.addEventListener('change', function () {
        applyScannerMirrorState();
      });
    }

    if (scannerRestartBtn) {
      scannerRestartBtn.addEventListener('click', function () {
        restartBarcodeScanner(false);
      });
    }

    if (scannerCameraApplyBtn) {
      scannerCameraApplyBtn.addEventListener('click', function () {
        restartBarcodeScanner(true);
      });
    }

    if (scannerZoomRange) {
      scannerZoomRange.addEventListener('input', function () {
        updateScannerZoomLabel(scannerZoomRange.value);

        if (barcodeZoomApplyTimer) {
          window.clearTimeout(barcodeZoomApplyTimer);
        }

        barcodeZoomApplyTimer = window.setTimeout(function () {
          barcodeZoomApplyTimer = null;
          applyScannerZoomValue(scannerZoomRange.value);
        }, 140);
      });

      scannerZoomRange.addEventListener('change', function () {
        applyScannerZoomValue(scannerZoomRange.value);
      });
    }

    if (scannerTorchBtn) {
      scannerTorchBtn.addEventListener('click', function () {
        toggleScannerTorch();
      });
    }

    window.addEventListener('resize', function () {
      if (modalEl.classList.contains('show')) {
        scheduleLayoutSync();
      }
    });

    window.sirovinaScannerProceedFromBarcode = function () {
      markProceedSource('barcode');
      openProceedFlow('barcode');
    };

    window.addEventListener('sirovina:barcode-proceed', function () {
      markProceedSource('barcode');
      openProceedFlow('barcode');
    });

    if (confirmModalEl) {
      confirmModalEl.addEventListener('show.bs.modal', function () {
        stopBarcodeScanner();
      });

      confirmModalEl.addEventListener('hidden.bs.modal', function () {
        var modalStillVisible = modalEl.classList.contains('show');
        var shouldResume = modalStillVisible && !state.saving;

        if (!shouldResume) {
          markProceedSource('manual');
          resetConfirmContext();
          return;
        }

        markProceedSource('manual');
        resetConfirmContext();
        window.setTimeout(function () {
          requestBarcodeScannerStart(false);
        }, 120);
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
      markProceedSource('manual');
      state.activeMode = 'product';
      state.allType = 'materials';
      state.allRows = [];
      setAllLoadingMoreNotice(false);
      state.allTotalByType.materials = 0;
      resetSelectionsForOpen();
      resetConfirmContext();
      resetAllPaging('materials');
      resetAllPaging('operations');
      lastDecodedBarcode = '';
      lastDecodedBarcodeAt = 0;
      barcodeScannerBusy = false;
      barcodeManualStartUnlocked = false;
      clearScannerError();
      setScannerStatus(
        requiresManualCameraStartForCurrentView()
          ? 'Još uvijek nije dostupno.'
          : 'Dozvoli pristup kameri i pokreni skeniranje barcodova.' ,
      );
      applyScannerMirrorState();

      if (productModePanel) {
        productModePanel.classList.remove('d-none', 'wo-panel-exit', 'wo-panel-enter');
        productModePanel.setAttribute('aria-hidden', 'false');
      }

      if (allModePanel) {
        allModePanel.classList.add('d-none');
        allModePanel.classList.remove('wo-panel-exit', 'wo-panel-enter');
        allModePanel.setAttribute('aria-hidden', 'true');
      }

      if (allSearchInput) {
        allSearchInput.value = '';
      }

      if (allTableWrapEl) {
        allTableWrapEl.scrollTop = 0;
      }

      updateModeButtons();
      renderAllRows();
      updateSelectionSummary();
      prefillOperationsForOpen();
      scheduleLayoutSync();

      var invoiceNumberNode = document.querySelector('.invoice-number');
      if (rnNumberEl) {
        rnNumberEl.textContent = invoiceNumberNode ? invoiceNumberNode.textContent.trim() : '-';
      }

      queueBackdropSync();
    });

    modalEl.addEventListener('shown.bs.modal', function () {
      queueBackdropSync();
      syncAllSearchInputHeight();
      scheduleLayoutSync();

      if (requiresManualCameraStartForCurrentView()) {
        refreshScannerCameras();
        setScannerStatus('Još uvijek nije dostupno', 'warning');
      } else {
        requestBarcodeScannerStart(false);
      }

      if (!barcodeLookupUrl) {
        setScannerStatus('Barcode lookup nije dostupan.', 'warning');
        showScannerError('Ruta za provjeru barcode materijala nije konfigurirana.');
      }

      if (!productsUrl || !bomUrl || !saveUrl) {
        setStatus('Skenirajte i učitajte validan radni nalog prije planiranja potrošnje.', 'warning');
        showError('Nedostaju API rute za planiranu potrošnju.');
        return;
      }

      if (!state.initialized) {
        state.initialized = true;
        initProductSelect2();
        ensureProductOptionSelected(defaultProduct, defaultProductLabel || defaultProduct);
      }

      if (!hasSelect2()) {
        loadProducts().then(function () {
          syncAllSearchInputHeight();
          if (productSelect && productSelect.value) {
            loadBomForSelectedProduct();
            return;
          }

          state.bomRows = [];
          state.selectedKeys.clear();
          renderBomRows();
          setBomLoading(false);
          setStatus('Izaberite proizvod iz liste za učitavanje sastavnice', 'warning');
        });
        return;
      }

      if (productSelect && productSelect.value) {
        syncAllSearchInputHeight();
        loadBomForSelectedProduct();
        return;
      }

      state.bomRows = [];
      state.selectedKeys.clear();
      renderBomRows();
      setBomLoading(false);
      setStatus('Izaberite proizvod iz liste za učitavanje sastavnice', 'warning');
    });

    modalEl.addEventListener('hidden.bs.modal', function () {
      state.prefillOperationsSeq += 1;
      resetConfirmContext();
      barcodeManualStartUnlocked = false;
      stopBarcodeScanner();
      clearScannerError();
      setScannerStatus('Skener je zaustavljen.');
      showError('');
      setBomLoading(false);
      setAllLoading(false);
      if (quickCardEl) {
        quickCardEl.style.height = '';
        quickCardEl.style.maxHeight = '';
      }
      if (rightMainCardEl) {
        rightMainCardEl.style.height = '';
        rightMainCardEl.style.maxHeight = '';
      }

      if (state.allSearchDebounce) {
        window.clearTimeout(state.allSearchDebounce);
        state.allSearchDebounce = null;
      }

      clearBackdrop();
    });
  });
</script>

