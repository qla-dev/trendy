<div class="modal fade" id="edit-sastavnica-item-modal" tabindex="-1" aria-labelledby="edit-sastavnica-item-modal-label" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="edit-sastavnica-item-modal-label">Uredi stavku sastavnice</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zatvori"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-danger d-none" id="edit-sastavnica-item-error" role="alert"></div>

        <div class="row g-1">
          <div class="col-md-6">
            <label class="form-label" for="edit-sastavnica-item-code">Artikal</label>
            <input type="text" class="form-control" id="edit-sastavnica-item-code" readonly>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="edit-sastavnica-item-position">Pozicija</label>
            <input type="text" class="form-control" id="edit-sastavnica-item-position" readonly>
          </div>
          <div class="col-12">
            <label class="form-label" for="edit-sastavnica-item-description">Opis</label>
            <input type="text" class="form-control" id="edit-sastavnica-item-description" maxlength="80">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="edit-sastavnica-item-quantity">Količina</label>
            <input type="number" class="form-control" id="edit-sastavnica-item-quantity" min="0" step="0.001">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="edit-sastavnica-item-unit">MJ</label>
            <input type="text" class="form-control text-uppercase" id="edit-sastavnica-item-unit" maxlength="3" readonly disabled>
          </div>
          <div class="col-12">
            <label class="form-label" for="edit-sastavnica-item-note">Napomena</label>
            <textarea class="form-control" id="edit-sastavnica-item-note" rows="4" maxlength="4000"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Otkaži</button>
        <button type="button" class="btn btn-primary" id="edit-sastavnica-item-save-btn" data-default-label="Sačuvaj">Sačuvaj</button>
      </div>
    </div>
  </div>
</div>
