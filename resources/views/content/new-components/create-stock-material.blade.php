<div class="modal fade material-create-modal" id="material-create-modal" tabindex="-1" aria-labelledby="material-create-modal-label" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0" id="material-create-modal-label">Dodaj novi materijal</h5>
          <div class="material-stock-modal-subtitle" id="material-create-modal-subtitle">Kreiraj novi katalog materijal i početnu zalihu.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zatvori"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-danger d-none" id="material-create-modal-error"></div>
        <div class="alert alert-success d-none" id="material-create-modal-success"></div>

        <div class="material-create-form-grid">
          <div class="material-create-field-card">
            <label class="form-label" for="material-create-code-input">Šifra</label>
            <input type="text" class="form-control" id="material-create-code-input" maxlength="64" autocomplete="off">
          </div>
          <div class="material-create-field-card">
            <label class="form-label" for="material-create-name-input">Naziv</label>
            <input type="text" class="form-control" id="material-create-name-input" maxlength="255" autocomplete="off">
          </div>
          <div class="material-create-field-card">
            <label class="form-label" for="material-create-unit-input">MJ</label>
            <select class="form-select" id="material-create-unit-input">
              <option value="">Odaberite MJ</option>
              @foreach(($materialUnitOptions ?? []) as $unitCode)
                <option value="{{ $unitCode }}">{{ $unitCode }}</option>
              @endforeach
            </select>
          </div>
          <div class="material-create-field-card">
            <label class="form-label" for="material-create-warehouse-input">Skladište</label>
            <select class="form-select" id="material-create-warehouse-input">
              <option value="">Odaberite skladiste</option>
              @foreach(($stockWarehouseOptions ?? []) as $warehouseName)
                <option value="{{ $warehouseName }}">{{ $warehouseName }}</option>
              @endforeach
            </select>
          </div>
          <div class="material-create-field-card">
            <label class="form-label" for="material-create-stock-input">Početna zaliha</label>
            <input type="number" class="form-control" id="material-create-stock-input" step="0.001" inputmode="decimal" autocomplete="off">
          </div>
          <div class="material-create-field-card">
            <label class="form-label" for="material-create-set-input">Set</label>
            <select class="form-select" id="material-create-set-input">
              @foreach(($materialSetOptions ?? []) as $setCode)
                <option value="{{ $setCode }}" @selected((string) $setCode === (string) ($defaultMaterialSet ?? '011'))>{{ $setCode }}</option>
              @endforeach
            </select>
          </div>
        </div>

        <div class="material-create-help" id="material-create-help-text">
          Novi materijal ce biti upisan u katalog, a početna zaliha ce odmah biti evidentirana na odabranom skladistu.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Zatvori</button>
        <button type="button" class="btn btn-primary" id="material-create-modal-save-btn">
          <i class="fa fa-plus me-50"></i> Sačuvaj materijal
        </button>
      </div>
    </div>
  </div>
</div>
