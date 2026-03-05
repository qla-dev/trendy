<div class="modal fade fine-adjust-bom-fixed-theme" id="fine-adjust-bom-modal" tabindex="-1" aria-labelledby="fine-adjust-bom-label" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen">
    <div class="modal-content fine-adjust-bom-content">
      <div class="modal-header fine-adjust-bom-header">
        <div>
          <h4 class="mb-25 text-white" id="fine-adjust-bom-label">Ručno prilagođavanje sastavnice</h4>
          <p class="mb-0 small fine-adjust-bom-subtitle">Ovaj prikaz dopušta administratoru ručno prilagođavanje svih stavki unutar nove privremene sastavnice prije dodavanje iste na radni nalog</p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zatvori"></button>
      </div>

      <div class="modal-body fine-adjust-bom-body">
        <div class="d-flex justify-content-end mb-1">
          <span class="badge bg-primary" id="fine-adjust-selected-count">Stavki: 0</span>
        </div>

        <div class="table-responsive fine-adjust-bom-table-wrap">
          <table class="table table-sm mb-0 align-middle" id="fine-adjust-bom-table">
            <thead>
              <tr>
                <th class="text-center">Alternat...</th>
                <th class="text-center">Pozicija</th>
                <th class="text-center">Artikal</th>
                <th class="text-center">Opis</th>
                <th class="text-center">Slika</th>
                <th class="text-center">Napo...</th>
                <th class="text-center">Planirano</th>
                <th class="text-center">Zaliha</th>
                <th class="text-center">MJ</th>
                <th class="text-center">Serija</th>
                <th class="text-center">nor.os.</th>
                <th class="text-center">Aktivno</th>
                <th class="text-center">Zavrs...</th>
                <th class="text-center">VA</th>
                <th class="text-center">Prim.klas</th>
                <th class="text-center">Sek.klas</th>
              </tr>
            </thead>
            <tbody id="fine-adjust-bom-body">
              <tr>
                <td colspan="16" class="text-center text-muted py-2">Nema odabranih stavki.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="modal-footer fine-adjust-bom-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
          <i class="fa fa-times me-50"></i> Odustani
        </button>
        <button type="button" class="btn btn-success" id="fine-adjust-save-btn">
          <i class="fa fa-check me-50"></i> Potvrdi i dodaj na RN
        </button>
      </div>
    </div>
  </div>
</div>

<style>
  #fine-adjust-bom-modal {
    --wo-scroll-track: rgba(12, 18, 30, 0.92);
    --wo-scroll-thumb: rgba(138, 148, 169, 0.86);
    --wo-scroll-thumb-hover: rgba(160, 170, 190, 0.92);
    --wo-scroll-thumb-active: rgba(176, 186, 206, 0.95);
    --wo-scroll-thumb-border: rgba(11, 17, 29, 0.9);
  }

  #fine-adjust-bom-modal .fine-adjust-bom-subtitle {
    color: rgba(194, 208, 238, 0.72);
  }

  #fine-adjust-bom-modal .fine-adjust-bom-content {
    background: linear-gradient(180deg, #0a1020 0%, #0a1324 70%, #091421 100%);
    color: #dfe8ff;
  }

  #fine-adjust-bom-modal.fine-adjust-bom-fixed-theme .modal-content {
    background: linear-gradient(180deg, #0a1020 0%, #0a1324 70%, #091421 100%) !important;
    color: #dfe8ff !important;
  }

  #fine-adjust-bom-modal.fine-adjust-bom-fixed-theme .modal-body,
  #fine-adjust-bom-modal.fine-adjust-bom-fixed-theme .modal-footer {
    background: transparent !important;
  }

  #fine-adjust-bom-modal .fine-adjust-bom-header {
    border-bottom: 1px solid rgba(196, 204, 220, 0.28);
    background: rgba(6, 12, 22, 0.92);
  }

  #fine-adjust-bom-modal .btn-close {
    background-image: none;
    background-color: rgba(232, 238, 248, 0.18) !important;
    opacity: 1;
    border: 1px solid rgba(206, 214, 230, 0.42);
    border-radius: 10px;
    width: 2.15rem;
    height: 2.15rem;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #ffffff !important;
  }

  #fine-adjust-bom-modal .btn-close::before {
    content: "\00d7";
    color: #ffffff;
    font-size: 1.45rem;
    line-height: 1;
    font-weight: 500;
  }

  #fine-adjust-bom-modal .btn-close:hover,
  #fine-adjust-bom-modal .btn-close:focus {
    background-color: rgba(232, 238, 248, 0.28) !important;
    border-color: rgba(222, 229, 242, 0.62);
    box-shadow: 0 0 0 0.14rem rgba(198, 206, 222, 0.24);
  }

  #fine-adjust-bom-modal .fine-adjust-bom-body {
    padding-top: 1rem;
  }

  #fine-adjust-bom-modal .fine-adjust-bom-footer {
    border-top: 1px solid rgba(196, 204, 220, 0.28) !important;
  }

  #fine-adjust-bom-modal .fine-adjust-bom-table-wrap {
    border: 1px solid rgba(196, 204, 220, 0.28);
    border-radius: 10px;
    overflow: auto;
    max-height: calc(100vh - 220px);
    background: rgba(10, 17, 30, 0.76);
    scrollbar-width: thin;
    scrollbar-color: var(--wo-scroll-thumb) var(--wo-scroll-track);
    scrollbar-gutter: stable;
  }

  #fine-adjust-bom-modal .fine-adjust-bom-table-wrap::-webkit-scrollbar {
    width: 8px;
    height: 8px;
  }

  #fine-adjust-bom-modal .fine-adjust-bom-table-wrap::-webkit-scrollbar-track {
    background: var(--wo-scroll-track);
    border-radius: 999px;
  }

  #fine-adjust-bom-modal .fine-adjust-bom-table-wrap::-webkit-scrollbar-thumb {
    background: var(--wo-scroll-thumb);
    border-radius: 999px;
    border: 1px solid var(--wo-scroll-thumb-border);
  }

  #fine-adjust-bom-modal .fine-adjust-bom-table-wrap::-webkit-scrollbar-thumb:hover {
    background: var(--wo-scroll-thumb-hover);
  }

  #fine-adjust-bom-modal .fine-adjust-bom-table-wrap::-webkit-scrollbar-thumb:active {
    background: var(--wo-scroll-thumb-active);
  }

  #fine-adjust-bom-modal .fine-adjust-bom-table-wrap::-webkit-scrollbar-corner {
    background: var(--wo-scroll-track);
  }

  #fine-adjust-bom-modal table thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: rgba(20, 31, 56, 0.95);
    color: #c9dbff;
    text-transform: uppercase;
    font-size: 0.74rem;
    letter-spacing: 0.03em;
    border-bottom: 1px solid rgba(204, 213, 229, 0.32);
    white-space: nowrap;
  }

  #fine-adjust-bom-modal .table > :not(caption) > * > * {
    border-color: rgba(186, 194, 210, 0.26) !important;
  }

  #fine-adjust-bom-modal table tbody td {
    color: #dfe8ff;
    min-width: 120px;
    min-height: 68px;
    height: 68px;
    max-height: 68px;
    vertical-align: middle;
  }

  #fine-adjust-bom-modal table tbody td[colspan] {
    height: auto;
    max-height: none;
  }

  #fine-adjust-bom-modal .fine-adjust-input {
    min-width: 110px;
    height: 36px;
    background: rgba(9, 18, 36, 0.86);
    color: #e8f0ff;
    border-color: rgba(123, 153, 210, 0.35);
  }

  #fine-adjust-bom-modal .fine-adjust-select {
    cursor: pointer;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    padding-right: 2rem;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%23BFD0F4' d='M1.41 0.59L6 5.17 10.59 0.59 12 2l-6 6-6-6z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.65rem center;
    background-size: 12px 8px;
  }

  #fine-adjust-bom-modal .fine-adjust-readonly {
    min-width: 110px;
    height: 36px;
    background: rgba(13, 22, 39, 0.66);
    color: #b6c7eb;
    border-color: rgba(98, 120, 162, 0.32);
    pointer-events: none;
  }

  #fine-adjust-bom-modal .fine-adjust-input:focus {
    border-color: rgba(74, 179, 148, 0.72);
    box-shadow: 0 0 0 0.12rem rgba(74, 179, 148, 0.18);
  }
</style>
