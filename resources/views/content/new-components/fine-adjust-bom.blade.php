<div class="modal fade" id="fine-adjust-bom-modal" tabindex="-1" aria-labelledby="fine-adjust-bom-label" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen">
    <div class="modal-content fine-adjust-bom-content">
      <div class="modal-header fine-adjust-bom-header">
        <div>
          <h4 class="mb-25" id="fine-adjust-bom-label">Fine adjust BOM</h4>
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
                <th class="text-center">Kolicina</th>
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
                <td colspan="15" class="text-center text-muted py-2">Nema odabranih stavki.</td>
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
  #fine-adjust-bom-modal .fine-adjust-bom-content {
    background: linear-gradient(180deg, #0a1020 0%, #0a1324 70%, #091421 100%);
    color: #dfe8ff;
  }

  #fine-adjust-bom-modal .fine-adjust-bom-header {
    border-bottom: 1px solid rgba(95, 127, 194, 0.35);
    background: rgba(6, 12, 22, 0.92);
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

  #fine-adjust-bom-modal table tbody td {
    border-top-color: rgba(101, 129, 186, 0.22);
    color: #dfe8ff;
    min-width: 120px;
  }

  #fine-adjust-bom-modal .fine-adjust-input {
    min-width: 110px;
    background: rgba(9, 18, 36, 0.86);
    color: #e8f0ff;
    border-color: rgba(123, 153, 210, 0.35);
  }

  #fine-adjust-bom-modal .fine-adjust-input:focus {
    border-color: rgba(74, 179, 148, 0.72);
    box-shadow: 0 0 0 0.12rem rgba(74, 179, 148, 0.18);
  }
</style>
