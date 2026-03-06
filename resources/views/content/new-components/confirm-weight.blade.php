<div class="modal fade" id="confirm-weight-modal" tabindex="-1" aria-labelledby="confirm-weight-modal-label" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title mb-0" id="confirm-weight-modal-label">
          Odabrano: <span id="scanned-material-name">0 komponenti</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zatvori"></button>
      </div>
      <div class="modal-body">
        <div id="confirm-material-details-wrap" class="confirm-material-details d-none">
          <div class="confirm-material-meta-grid">
            <div class="confirm-material-meta-item">
              <span class="confirm-material-meta-label">Sifra</span>
              <strong id="confirm-material-code">-</strong>
            </div>
            <div class="confirm-material-meta-item">
              <span class="confirm-material-meta-label">Naziv</span>
              <strong id="confirm-material-title">-</strong>
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
        <p class="text-muted mb-1" id="confirm-weight-help-text">Unesi faktor kolicine za planiranu potrosnju.</p>
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
  #confirm-weight-modal .modal-content {
    background: #1d2740;
    border: 1px solid rgba(173, 190, 222, 0.12);
    box-shadow: 0 22px 60px rgba(2, 6, 18, 0.45);
    color: #eef4ff;
  }

  #confirm-weight-modal .modal-header {
    border-bottom: 1px solid rgba(173, 190, 222, 0.1);
    padding: 1rem 1.25rem 0.9rem;
    align-items: flex-start;
  }

  #confirm-weight-modal .modal-body {
    padding: 1rem 1.25rem 1.1rem;
  }

  #confirm-weight-modal .modal-footer {
    padding: 1rem;
    border-top: 1px solid rgba(173, 190, 222, 0.1);
    gap: 1rem;
  }

  #confirm-weight-modal .modal-footer .btn {
    flex: 1;
  }

  #confirm-weight-modal .modal-title,
  #confirm-weight-modal #scanned-material-name {
    color: #f7fbff;
  }

  #confirm-weight-modal .btn-close {
    filter: invert(1) grayscale(1);
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
    color: #f7fbff;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(173, 190, 222, 0.16);
  }

  #confirm-weight-modal .weight-unit-inline {
    min-width: 190px;
  }

  #confirm-weight-modal .form-control,
  #confirm-weight-modal .form-select {
    color: #f7fbff;
    background-color: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(173, 190, 222, 0.16);
    box-shadow: none;
  }

  #confirm-weight-modal .form-control::placeholder {
    color: rgba(230, 238, 252, 0.42);
  }

  #confirm-weight-modal .form-control:focus,
  #confirm-weight-modal .form-select:focus {
    color: #ffffff;
    background-color: rgba(255, 255, 255, 0.05);
    border-color: rgba(134, 206, 195, 0.4);
    box-shadow: 0 0 0 0.18rem rgba(110, 201, 185, 0.12);
  }

  #confirm-weight-modal .form-select option {
    color: #eef4ff;
    background: #1d2740;
  }

  #confirm-weight-modal .weight-unit-inline .form-label {
    font-size: 0.84rem;
    color: #c9d6f0;
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
    background: rgba(255, 255, 255, 0.025);
    border: 1px solid rgba(173, 190, 222, 0.08);
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
    color: #9fb2d6;
    font-weight: 700;
  }

  #confirm-weight-modal .confirm-material-meta-item strong {
    font-size: 0.95rem;
    color: #f4f8ff;
    word-break: break-word;
  }

  #confirm-weight-modal .confirm-material-action-indicator {
    margin-top: 0.85rem;
    padding: 0.65rem 0.8rem;
    border-radius: 0.6rem;
    font-size: 0.92rem;
    font-weight: 600;
    background: rgba(255, 159, 67, 0.12);
    color: #9f5e00;
  }

  #confirm-weight-modal .confirm-material-action-indicator.is-update {
    background: rgba(40, 199, 111, 0.14);
    color: #74f0af;
  }

  #confirm-weight-modal #confirm-weight-help-text {
    color: #d8e2f6 !important;
  }

  #confirm-weight-modal .btn-outline-secondary {
    color: #eef4ff;
    border-color: rgba(173, 190, 222, 0.18);
    background: transparent;
  }

  #confirm-weight-modal .btn-outline-secondary:hover,
  #confirm-weight-modal .btn-outline-secondary:focus {
    color: #ffffff;
    border-color: rgba(173, 190, 222, 0.26);
    background: rgba(255, 255, 255, 0.05);
  }

  #confirm-weight-modal .btn-primary {
    border-color: rgba(143, 167, 205, 0.18);
    background: #566989;
  }

  #confirm-weight-modal .btn-primary:hover,
  #confirm-weight-modal .btn-primary:focus {
    border-color: rgba(143, 167, 205, 0.24);
    background: #65799c;
  }

  @media (max-width: 575.98px) {
    #confirm-weight-modal .confirm-material-meta-grid {
      grid-template-columns: minmax(0, 1fr);
    }
  }
</style>
