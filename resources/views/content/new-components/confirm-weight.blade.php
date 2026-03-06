<div class="modal fade" id="confirm-weight-modal" tabindex="-1" aria-labelledby="confirm-weight-modal-label" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div class="confirm-weight-header-copy">
          <h5 class="modal-title mb-0" id="confirm-weight-modal-label">Potvrda količine</h5>
          <p class="mb-0" id="confirm-weight-help-text">Unesi faktor kolicine za planiranu potrosnju.</p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zatvori"></button>
      </div>
      <div class="modal-body">
        <div class="confirm-weight-selection-name" id="scanned-material-name">0 komponenti</div>
        <div id="confirm-material-details-wrap" class="confirm-material-details d-none">
          <div class="confirm-material-meta-grid">
            <div class="confirm-material-meta-item">
              <span class="confirm-material-meta-label">Sifra</span>
              <strong id="confirm-material-code">-</strong>
            </div>
            <div class="confirm-material-meta-item">
              <span class="confirm-material-meta-label">MJ</span>
              <strong id="confirm-material-unit">-</strong>
            </div>
            <div class="confirm-material-meta-item">
              <span class="confirm-material-meta-label">Trenutno na RN</span>
              <strong id="confirm-material-current-qty">0</strong>
            </div>
            <div class="confirm-material-meta-item">
              <span class="confirm-material-meta-label">Zaliha</span>
              <strong id="confirm-material-stock-qty">0</strong>
            </div>
          </div>
          <div id="confirm-material-action-indicator" class="confirm-material-action-indicator">
            Dodat ce novu stavku na radni nalog.
          </div>
        </div>
        <div class="d-flex align-items-center justify-content-center gap-3 py-2">
          <div style="width: 45%;">
            <input
              type="number"
              class="form-control form-control-lg"
              id="weight-input"
              placeholder="1"
              step="0.01"
              min="0.01"
              value="1"
            >
          </div>
          <div class="weight-unit-inline">
            <label for="weight-unit-select" class="form-label mb-50">MJ</label>
            <select class="form-select" id="weight-unit-select">
              <option value="AUTO" selected>Auto (iz sastavnice)</option>
              <option value="KG">KG</option>
              <option value="RDS">RDS</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer d-flex">
        <button type="button" class="btn btn-outline-secondary flex-fill" data-bs-dismiss="modal">
          <i class="fa fa-times me-2"></i> Odustani
        </button>
        <button type="button" class="btn btn-primary flex-fill" id="confirm-add-sirovina-btn">
          <i class="fa fa-check me-2"></i> Sacuvaj planiranu potrosnju
        </button>
      </div>
    </div>
  </div>
</div>

<style>
  #confirm-weight-modal {
    --confirm-modal-bg: #ffffff;
    --confirm-surface-bg: #f8f9fc;
    --confirm-surface-border: rgba(71, 95, 123, 0.12);
    --confirm-border: rgba(71, 95, 123, 0.14);
    --confirm-text: #4b4b4b;
    --confirm-text-strong: #2f3349;
    --confirm-text-muted: #6e6b7b;
    --confirm-input-bg: #ffffff;
    --confirm-input-border: rgba(71, 95, 123, 0.18);
    --confirm-input-placeholder: rgba(75, 75, 75, 0.4);
    --confirm-focus-border: rgba(115, 103, 240, 0.4);
    --confirm-focus-ring: rgba(115, 103, 240, 0.14);
    --confirm-indicator-bg: rgba(255, 159, 67, 0.14);
    --confirm-indicator-text: #8e5b13;
    --confirm-indicator-update-bg: rgba(40, 199, 111, 0.14);
    --confirm-indicator-update-text: #0f7d4a;
    --confirm-shadow: 0 22px 60px rgba(34, 41, 47, 0.16);
    --confirm-close-filter: none;
  }

  body.dark-layout #confirm-weight-modal,
  body.semi-dark-layout #confirm-weight-modal,
  .dark-layout #confirm-weight-modal,
  .semi-dark-layout #confirm-weight-modal {
    --confirm-modal-bg: #283046;
    --confirm-surface-bg: rgba(255, 255, 255, 0.035);
    --confirm-surface-border: rgba(173, 190, 222, 0.08);
    --confirm-border: rgba(173, 190, 222, 0.12);
    --confirm-text: #d0d2d6;
    --confirm-text-strong: #f7fbff;
    --confirm-text-muted: #b4bdd3;
    --confirm-input-bg: rgba(255, 255, 255, 0.04);
    --confirm-input-border: rgba(173, 190, 222, 0.16);
    --confirm-input-placeholder: rgba(230, 238, 252, 0.42);
    --confirm-focus-border: rgba(134, 206, 195, 0.4);
    --confirm-focus-ring: rgba(110, 201, 185, 0.12);
    --confirm-indicator-bg: rgba(255, 159, 67, 0.12);
    --confirm-indicator-text: #f4b96b;
    --confirm-indicator-update-bg: rgba(40, 199, 111, 0.14);
    --confirm-indicator-update-text: #74f0af;
    --confirm-shadow: 0 22px 60px rgba(2, 6, 18, 0.45);
    --confirm-close-filter: invert(1) grayscale(1);
  }

  #confirm-weight-modal .modal-content {
    background: var(--confirm-modal-bg);
    border: 1px solid var(--confirm-border);
    box-shadow: var(--confirm-shadow);
    color: var(--confirm-text);
  }

  #confirm-weight-modal .modal-header {
    border-bottom: 1px solid var(--confirm-border);
    padding: 1rem 1.25rem 0.9rem;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.85rem;
  }

  #confirm-weight-modal .modal-body {
    padding: 1rem 1.25rem 1.1rem;
  }

  #confirm-weight-modal .modal-footer {
    padding: 1rem;
    border-top: 1px solid var(--confirm-border);
    gap: 1rem;
  }

  #confirm-weight-modal .modal-footer .btn {
    flex: 1;
  }

  #confirm-weight-modal .modal-title,
  #confirm-weight-modal .confirm-weight-selection-name {
    color: var(--confirm-text-strong);
  }

  #confirm-weight-modal .confirm-weight-header-copy {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    min-width: 0;
  }

  #confirm-weight-modal .confirm-weight-selection-name {
    margin-bottom: 0.85rem;
    font-size: 1.28rem;
    line-height: 1.3;
    font-weight: 700;
    word-break: break-word;
  }

  #confirm-weight-modal .btn-close {
    filter: var(--confirm-close-filter);
    opacity: 0.7;
  }

  #confirm-weight-modal .btn-close:hover,
  #confirm-weight-modal .btn-close:focus {
    opacity: 1;
  }

  #confirm-weight-modal .form-control-lg {
    font-size: 2rem;
    padding: 0.95rem 1.2rem;
    font-weight: 600;
    text-align: center;
    color: var(--confirm-text-strong);
    background: var(--confirm-input-bg);
    border: 1px solid var(--confirm-input-border);
  }

  #confirm-weight-modal .weight-unit-inline {
    min-width: 190px;
  }

  #confirm-weight-modal .form-control,
  #confirm-weight-modal .form-select {
    color: var(--confirm-text-strong);
    background-color: var(--confirm-input-bg);
    border: 1px solid var(--confirm-input-border);
    box-shadow: none;
  }

  #confirm-weight-modal .form-control::placeholder {
    color: var(--confirm-input-placeholder);
  }

  #confirm-weight-modal .form-control:focus,
  #confirm-weight-modal .form-select:focus {
    color: var(--confirm-text-strong);
    background-color: var(--confirm-input-bg);
    border-color: var(--confirm-focus-border);
    box-shadow: 0 0 0 0.18rem var(--confirm-focus-ring);
  }

  #confirm-weight-modal .form-select option {
    color: var(--confirm-text);
    background: var(--confirm-modal-bg);
  }

  #confirm-weight-modal .weight-unit-inline .form-label {
    font-size: 0.84rem;
    color: var(--confirm-text-muted);
    font-weight: 600;
  }

  #confirm-weight-modal .weight-unit-inline .form-select {
    min-height: 52px;
    font-size: 1rem;
    font-weight: 600;
  }

  #confirm-weight-modal .confirm-material-details {
    margin: 0 0 1rem;
    padding: 0.85rem 1rem;
    border-radius: 0.75rem;
    background: var(--confirm-surface-bg);
    border: 1px solid var(--confirm-surface-border);
  }

  #confirm-weight-modal .confirm-material-meta-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.65rem 1rem;
  }

  #confirm-weight-modal .confirm-material-meta-item {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
  }

  #confirm-weight-modal .confirm-material-meta-label {
    font-size: 0.72rem;
    line-height: 1;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--confirm-text-muted);
    font-weight: 700;
  }

  #confirm-weight-modal .confirm-material-meta-item strong {
    font-size: 0.95rem;
    color: var(--confirm-text-strong);
    word-break: break-word;
  }

  #confirm-weight-modal .confirm-material-action-indicator {
    margin-top: 0.85rem;
    padding: 0.65rem 0.8rem;
    border-radius: 0.6rem;
    font-size: 0.92rem;
    font-weight: 600;
    background: var(--confirm-indicator-bg);
    color: var(--confirm-indicator-text);
  }

  #confirm-weight-modal .confirm-material-action-indicator.is-update {
    background: var(--confirm-indicator-update-bg);
    color: var(--confirm-indicator-update-text);
  }

  #confirm-weight-modal #confirm-weight-help-text {
    color: var(--confirm-text-muted) !important;
    font-size: 0.95rem;
    line-height: 1.45;
  }

  @media (max-width: 575.98px) {
    #confirm-weight-modal .modal-header {
      gap: 0.65rem;
    }

    #confirm-weight-modal .confirm-weight-selection-name {
      font-size: 1.08rem;
    }

    #confirm-weight-modal .confirm-material-meta-grid {
      grid-template-columns: minmax(0, 1fr);
    }
  }
</style>
