<div class="modal fade material-stock-modal" id="material-stock-modal" tabindex="-1" aria-labelledby="material-stock-modal-label" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0" id="material-stock-modal-label">Ručno prilagođavanje zalihe</h5>
          <div class="material-stock-modal-subtitle" id="material-stock-modal-subtitle">-</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zatvori"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-danger d-none" id="material-stock-modal-error"></div>
        <div class="alert alert-success d-none" id="material-stock-modal-success"></div>

        <div class="material-stock-meta-grid">
          <div class="material-stock-meta-item">
            <span class="material-stock-meta-label">Šifra</span>
            <span class="material-stock-meta-value" id="material-stock-modal-code">-</span>
          </div>
          <div class="material-stock-meta-item">
            <span class="material-stock-meta-label">Naziv</span>
            <span class="material-stock-meta-value" id="material-stock-modal-name">-</span>
          </div>
          <div class="material-stock-meta-item">
            <span class="material-stock-meta-label">MJ</span>
            <span class="material-stock-meta-value" id="material-stock-modal-unit">-</span>
          </div>
          <div class="material-stock-meta-item">
            <span class="material-stock-meta-label">Skladište</span>
            <span class="material-stock-meta-value" id="material-stock-modal-warehouse">-</span>
          </div>
          <div class="material-stock-meta-item">
            <span class="material-stock-meta-label">Trenutna zaliha</span>
            <span class="material-stock-meta-value" id="material-stock-modal-current">0</span>
          </div>
          <div class="material-stock-meta-item">
            <span class="material-stock-meta-label">Promjena</span>
            <span class="material-stock-meta-value" id="material-stock-modal-delta">0</span>
          </div>
        </div>

        <div class="material-stock-form-block">
          <label class="form-label" for="material-stock-modal-target-input">Nova zaliha</label>
          <input
            type="number"
            class="form-control"
            id="material-stock-modal-target-input"
            step="0.001"
            inputmode="decimal"
            autocomplete="off"
          >
          <div class="material-stock-form-help">
            Dozvoljene su i negativne vrijednosti.
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Zatvori</button>
        <button type="button" class="btn btn-primary" id="material-stock-modal-save-btn">
          <i class="fa fa-save me-50"></i> Sačuvaj zalihu
        </button>
      </div>
    </div>
  </div>
</div>
