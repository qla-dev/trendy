<div class="modal fade" id="confirm-weight-modal" tabindex="-1" aria-labelledby="confirm-weight-modal-label" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div class="w-100">
          <h5 class="modal-title mb-25" id="confirm-weight-modal-label">
            Odabrano: <span id="scanned-material-name">0 komponenti</span>
          </h5>
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
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zatvori"></button>
      </div>
      <div class="modal-body">
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
  #confirm-weight-modal .modal-footer {
    padding: 1rem;
    border-top: 1px solid #dee2e6;
    gap: 1rem;
  }

  #confirm-weight-modal .modal-footer .btn {
    flex: 1;
  }

  #confirm-weight-modal .form-control-lg {
    font-size: 2rem;
    padding: 0.95rem 1.2rem;
    font-weight: 600;
    text-align: center;
  }

  #confirm-weight-modal .weight-unit-inline {
    min-width: 190px;
  }

  #confirm-weight-modal .weight-unit-inline .form-label {
    font-size: 0.84rem;
    color: #6e6b7b;
    font-weight: 600;
  }

  #confirm-weight-modal .weight-unit-inline .form-select {
    min-height: 52px;
    font-size: 1rem;
    font-weight: 600;
  }

  #confirm-weight-modal .confirm-material-details {
    margin-top: 0.75rem;
    padding: 0.85rem 1rem;
    border-radius: 0.75rem;
    background: rgba(40, 48, 70, 0.04);
    border: 1px solid rgba(115, 103, 240, 0.08);
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
    color: #6e6b7b;
    font-weight: 700;
  }

  #confirm-weight-modal .confirm-material-meta-item strong {
    font-size: 0.95rem;
    color: #283046;
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
    color: #0d7f49;
  }

  @media (max-width: 575.98px) {
    #confirm-weight-modal .confirm-material-meta-grid {
      grid-template-columns: minmax(0, 1fr);
    }
  }
</style>
