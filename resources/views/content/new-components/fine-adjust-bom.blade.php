<div class="modal fade" id="fine-adjust-bom-modal" tabindex="-1" aria-labelledby="fine-adjust-bom-label" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen">
    <div class="modal-content fine-adjust-bom-content">
      <div class="modal-header fine-adjust-bom-header">
        <div>
          <h4 class="mb-25 text-white" id="fine-adjust-bom-label">Ručno prilagođavanje sastavnice</h4>
          <p class="mb-0 small text-muted">Prilagodite stavke prije dodavanja na radni nalog.</p>
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

      <div class="modal-footer">
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
    --wo-scroll-track: rgba(216, 223, 236, 0.94);
    --wo-scroll-thumb: rgba(128, 139, 164, 0.86);
    --wo-scroll-thumb-hover: rgba(106, 118, 145, 0.9);
    --wo-scroll-thumb-active: rgba(92, 104, 132, 0.94);
    --wo-scroll-thumb-border: rgba(246, 248, 253, 0.88);
  }

  body.dark-layout #fine-adjust-bom-modal,
  body.semi-dark-layout #fine-adjust-bom-modal,
  .dark-layout #fine-adjust-bom-modal,
  .semi-dark-layout #fine-adjust-bom-modal {
    --wo-scroll-track: rgba(10, 16, 28, 0.92);
    --wo-scroll-thumb: rgba(120, 136, 170, 0.86);
    --wo-scroll-thumb-hover: rgba(149, 164, 194, 0.92);
    --wo-scroll-thumb-active: rgba(162, 176, 206, 0.95);
    --wo-scroll-thumb-border: rgba(12, 19, 33, 0.9);
  }

  #fine-adjust-bom-modal .fine-adjust-bom-content {
    background: linear-gradient(180deg, #0a1020 0%, #0a1324 70%, #091421 100%);
    color: #dfe8ff;
  }

  #fine-adjust-bom-modal .fine-adjust-bom-header {
    border-bottom: 1px solid rgba(95, 127, 194, 0.35);
    background: rgba(6, 12, 22, 0.92);
  }

  #fine-adjust-bom-modal .btn-close {
    filter: invert(1) grayscale(100%);
    opacity: 0.88;
    border: 1px solid rgba(122, 141, 178, 0.42);
    border-radius: 10px;
    padding: 0.7rem;
  }

  #fine-adjust-bom-modal .btn-close:hover,
  #fine-adjust-bom-modal .btn-close:focus {
    opacity: 1;
    background-color: rgba(22, 32, 52, 0.95);
    border-color: rgba(155, 173, 206, 0.62);
    box-shadow: 0 0 0 0.14rem rgba(98, 124, 176, 0.28);
  }

  #fine-adjust-bom-modal .fine-adjust-bom-body {
    padding-top: 1rem;
  }

  #fine-adjust-bom-modal .fine-adjust-bom-table-wrap {
    border: 1px solid rgba(95, 127, 194, 0.35);
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
    border-bottom: 1px solid rgba(118, 144, 199, 0.4);
    white-space: nowrap;
  }

  #fine-adjust-bom-modal .table > :not(caption) > * > * {
    border-color: rgba(70, 89, 123, 0.52) !important;
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
