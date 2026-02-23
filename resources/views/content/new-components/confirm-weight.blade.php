<div class="modal fade" id="confirm-weight-modal" tabindex="-1" aria-labelledby="confirm-weight-modal-label" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="confirm-weight-modal-label">
          Odabrano: <span id="scanned-material-name">0 komponenti</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zatvori"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted mb-1">Unesi faktor količine za planiranu potrošnju.</p>
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
          <i class="fa fa-check me-2"></i> Sačuvaj planiranu potrošnju
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
</style>
