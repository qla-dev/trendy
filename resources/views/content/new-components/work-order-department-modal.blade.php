<div class="modal fade" id="work-order-department-modal" tabindex="-1" aria-labelledby="work-order-department-modal-label" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered wo-department-modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="work-order-department-modal-label">Dodaj odjel</h5>
          <small class="text-muted">Odjel se prepisuje na dokumente nastale iz ovog RN-a.</small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zatvori"></button>
      </div>
      <div class="modal-body pt-1">
        <div class="input-group mb-1">
          <span class="input-group-text"><i class="fa fa-search"></i></span>
          <input id="wo-department-modal-search" class="form-control" autocomplete="off" placeholder="Pretraži Pantheon odjele">
        </div>
        <div id="wo-department-modal-results" class="list-group mb-1"></div>
        <div id="wo-department-modal-empty" class="text-muted small d-none">Nema odgovarajućih odjela.</div>
      </div>
      <div class="modal-footer">
        <span id="wo-department-modal-selected" class="text-muted small me-auto">Bez odjela</span>
        <button id="wo-department-modal-clear" type="button" class="btn btn-outline-danger">Ukloni odjel</button>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Otkaži</button>
        <button id="wo-department-modal-save" type="button" class="btn btn-primary">Sačuvaj</button>
      </div>
    </div>
  </div>
</div>

<style>
  #work-order-department-modal .wo-department-modal-dialog {
    width: 510px;
    max-width: calc(100vw - 2rem);
  }

  #work-order-department-modal .modal-content {
    height: 650px;
    max-height: calc(100vh - 2rem);
  }

  #work-order-department-modal .modal-body {
    overflow: hidden;
  }

  #work-order-department-modal #wo-department-modal-results {
    height: 430px;
    overflow-y: auto;
  }

  #work-order-department-modal .wo-department-choice {
    min-height: 43px;
    display: flex;
    align-items: center;
  }
</style>
