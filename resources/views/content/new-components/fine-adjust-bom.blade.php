<div class="modal fade fine-adjust-bom-fixed-theme" id="fine-adjust-bom-modal" tabindex="-1" aria-labelledby="fine-adjust-bom-label" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen">
    <div class="modal-content fine-adjust-bom-content">
      <div class="modal-header fine-adjust-bom-header">
        <button type="button" class="btn btn-secondary fine-adjust-close-fab" data-bs-dismiss="modal" aria-label="Zatvori">
          <i class="fa fa-times me-50"></i> Zatvori
        </button>
        <div class="fine-adjust-bom-header-copy">
          <h4 class="mb-25 text-white" id="fine-adjust-bom-label">Ručno prilagođavanje sastavnice</h4>
          <p class="mb-0 small fine-adjust-bom-subtitle">Ovaj prikaz dopušta administratoru ručno prilagođavanje svih stavki unutar nove privremene sastavnice prije dodavanje iste na radni nalog</p>
        </div>
      </div>

      <div class="modal-body fine-adjust-bom-body">
        <div class="d-flex justify-content-end mb-1">
          <span class="badge bg-primary" id="fine-adjust-selected-count">Stavki: 0</span>
        </div>

        <div class="table-responsive fine-adjust-bom-table-wrap">
          <table class="table table-sm mb-0 align-middle" id="fine-adjust-bom-table">
            <thead>
              <tr>
                <th class="text-center">Alternativno</th>
                <th class="text-center">Pozicija</th>
                <th class="text-center">Artikal</th>
                <th class="text-center">Opis</th>
                <th class="text-center">Slika</th>
                <th class="text-center">Visina</th>
                <th class="text-center">Širina</th>
                <th class="text-center">Debljina</th>
                <th class="text-center">Napomena</th>
                <th class="text-center">Planirano</th>
                <th class="text-center">Zaliha</th>
                <th class="text-center">MJ</th>
              </tr>
            </thead>
            <tbody id="fine-adjust-bom-body">
              <tr>
                <td colspan="12" class="text-center text-muted py-2">Nema odabranih stavki.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="modal-footer fine-adjust-bom-footer">
        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">
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
    border-bottom: 1px solid rgba(196, 204, 220, 0.18);
    background: rgba(6, 12, 22, 0.92);
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
  }

  #fine-adjust-bom-modal .fine-adjust-bom-header-copy {
    margin-left: auto;
    text-align: right;
    max-width: calc(100% - 170px);
  }

  #fine-adjust-bom-modal .fine-adjust-close-fab {
    position: static;
    border-radius: 10px !important;
    padding: 0.55rem 0.95rem;
    border: 1px solid rgba(205, 215, 238, 0.42);
    background-color: rgba(10, 14, 22, 0.82);
    color: #eef3ff;
    box-shadow: 0 8px 20px rgba(6, 8, 14, 0.45);
    backdrop-filter: blur(2px);
    height: 42px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    white-space: nowrap;
  }

  #fine-adjust-bom-modal .fine-adjust-close-fab:hover,
  #fine-adjust-bom-modal .fine-adjust-close-fab:focus {
    border-color: rgba(223, 231, 248, 0.7);
    background-color: rgba(18, 24, 37, 0.94);
    color: #ffffff;
  }

  @media (max-width: 767.98px) {
    #fine-adjust-bom-modal .fine-adjust-bom-header {
      flex-wrap: wrap;
    }

    #fine-adjust-bom-modal .fine-adjust-bom-header-copy {
      max-width: 100%;
      width: 100%;
      text-align: right;
    }
  }

  #fine-adjust-bom-modal .fine-adjust-bom-body {
    padding-top: 1rem;
  }

  #fine-adjust-bom-modal .fine-adjust-bom-footer {
    border-top: 1px solid rgba(196, 204, 220, 0.18) !important;
  }

  #fine-adjust-bom-modal .fine-adjust-bom-table-wrap {
    border: 1px solid rgba(196, 204, 220, 0.18);
    border-radius: 10px;
    overflow: auto;
    max-height: calc(100vh - 220px);
    background: rgba(10, 17, 30, 0.76);
    scrollbar-width: thin;
    scrollbar-color: var(--wo-scroll-thumb) var(--wo-scroll-track);
    scrollbar-gutter: stable;
  }

  #fine-adjust-bom-modal .fine-adjust-bom-table-wrap > .table {
    margin-bottom: 0;
    border-collapse: separate;
    border-spacing: 0;
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
    border-bottom: 1px solid rgba(204, 213, 229, 0.2);
    white-space: nowrap;
  }

  #fine-adjust-bom-modal .table > :not(caption) > * > * {
    border-color: rgba(186, 194, 210, 0.16) !important;
  }

  #fine-adjust-bom-modal table tbody tr {
    height: 54px;
    min-height: 54px;
    max-height: 54px;
  }

  #fine-adjust-bom-modal table tbody td {
    color: #dfe8ff;
    min-width: 120px;
    min-height: 54px;
    height: 54px;
    max-height: 54px;
    vertical-align: middle;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
  }

  #fine-adjust-bom-modal table tbody td .fine-adjust-note-stack,
  #fine-adjust-bom-modal table tbody td .fine-adjust-input,
  #fine-adjust-bom-modal table tbody td .fine-adjust-readonly {
    height: 54px;
    min-height: 54px;
    max-height: 54px;
  }

  #fine-adjust-bom-modal table tbody td[colspan] {
    height: auto;
    max-height: none;
    white-space: normal;
  }

  #fine-adjust-bom-modal .fine-adjust-note-cell {
    min-width: 480px;
    max-width: 640px;
    width: 100%;
  }

  #fine-adjust-bom-modal .fine-adjust-note-stack {
    display: flex;
    align-items: stretch;
    gap: 0;
    min-width: 300px;
    min-height: 72px;
    height: 72px;
    border: 1px solid rgba(123, 153, 210, 0.35);
    border-radius: 0.375rem;
    overflow: hidden;
    background: rgba(9, 18, 36, 0.86);
  }

  #fine-adjust-bom-modal .fine-adjust-note-stack.is-material-hidden {
    background: rgba(9, 18, 36, 0.86);
  }
  #fine-adjust-bom-modal .fine-adjust-note-preview,
  #fine-adjust-bom-modal .fine-adjust-note-input {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    width: 100%;
    height: 100%;
    white-space: normal;
    padding: 0.35rem 0.55rem;
    box-sizing: border-box;
  }

  #fine-adjust-bom-modal .fine-adjust-note-input {
    border: none;
    background: transparent;
    color: #dfe8ff;
    min-height: 100%;
    padding: 0;
  }

  #fine-adjust-bom-modal .fine-adjust-note-stack.is-material-hidden .fine-adjust-note-input {
    flex: 1 1 auto;
    width: 100%;
    padding: 0.55rem 0.75rem;
    background: rgba(9, 18, 36, 0.32);
  }
  #fine-adjust-bom-modal .fine-adjust-material-toggle {
    display: inline-flex;
    align-items: center;
    gap: 0.7rem;
    flex-wrap: nowrap;
    width: 210px;
    min-height: 100%;
    padding: 0.35rem 0.65rem;
    border-right: none;
    background: rgba(15, 23, 41, 0.48);
    box-shadow: none;
  }

  #fine-adjust-bom-modal .fine-adjust-material-toggle.is-aluminum .fine-adjust-material-label.is-aluminum,
  #fine-adjust-bom-modal .fine-adjust-material-toggle.is-steel .fine-adjust-material-label.is-steel {
    color: inherit;
  }

  #fine-adjust-bom-modal .fine-adjust-note-preview {
    flex: 1;
    min-height: 72px;
    height: 72px;
    padding: 0.55rem 0.75rem;
    border-radius: 0;
    border: none;
    background: rgba(9, 18, 36, 0.32);
    color: #dfe8ff;
    font-size: 0.82rem;
    line-height: 1.35;
    white-space: normal;
    word-break: break-word;
    box-shadow: none;
    min-width: 220px;
  }
    flex: 1;
    min-height: 72px;
    height: 72px;
    padding: 0.55rem 0.75rem;
    border-radius: 0;
    border: none;
    background: rgba(9, 18, 36, 0.60);
    color: #dfe8ff;
    font-size: 0.82rem;
    line-height: 1.35;
    white-space: normal;
    word-break: break-word;
    box-shadow: none;
    min-width: 220px;
  }

  #fine-adjust-bom-modal .fine-adjust-material-toggle {
    background: rgba(14, 23, 43, 0.84);
    box-shadow: inset 0 0 0 1px rgba(108, 129, 173, 0.28);
  }

  #fine-adjust-bom-modal .fine-adjust-material-toggle {
    display: inline-flex;
    align-items: center;
    gap: 0.7rem;
    flex-wrap: nowrap;
    width: fit-content;
    max-width: 100%;
  }

  #fine-adjust-bom-modal .fine-adjust-material-label {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.76rem;
    font-weight: 700;
    letter-spacing: 0.01em;
    color: rgba(191, 208, 244, 0.58);
    transition: color 0.18s ease, opacity 0.18s ease;
    white-space: nowrap;
  }

  #fine-adjust-bom-modal .fine-adjust-material-label i {
    font-size: 0.8rem;
  }

  #fine-adjust-bom-modal .fine-adjust-material-toggle.is-aluminum .fine-adjust-material-label.is-aluminum {
    color: #7fe5ff;
  }

  #fine-adjust-bom-modal .fine-adjust-material-toggle.is-steel .fine-adjust-material-label.is-steel {
    color: #ffd27e;
  }

  #fine-adjust-bom-modal .fine-adjust-material-switch {
    position: relative;
    display: inline-flex;
    align-items: center;
    flex: 0 0 auto;
    width: 58px;
    height: 32px;
    padding: 0;
    border: 1px solid rgba(129, 154, 203, 0.34);
    border-radius: 999px;
    background: linear-gradient(135deg, rgba(69, 171, 206, 0.35), rgba(38, 63, 112, 0.62));
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.03);
    cursor: pointer;
    transition: border-color 0.18s ease, background 0.18s ease, box-shadow 0.18s ease;
  }

  #fine-adjust-bom-modal .fine-adjust-material-switch:hover,
  #fine-adjust-bom-modal .fine-adjust-material-switch:focus {
    border-color: rgba(169, 193, 236, 0.58);
    box-shadow: 0 0 0 0.12rem rgba(92, 225, 194, 0.14);
  }

  #fine-adjust-bom-modal .fine-adjust-material-toggle.is-steel .fine-adjust-material-switch {
    background: linear-gradient(135deg, rgba(182, 132, 58, 0.38), rgba(70, 80, 106, 0.62));
  }

  #fine-adjust-bom-modal .fine-adjust-material-switch-thumb {
    position: absolute;
    top: 3px;
    left: 3px;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: linear-gradient(135deg, #c2fbff, #79dfff);
    box-shadow: 0 6px 14px rgba(9, 17, 32, 0.28);
    transition: transform 0.2s ease, background 0.2s ease;
  }

  #fine-adjust-bom-modal .fine-adjust-material-toggle.is-steel .fine-adjust-material-switch-thumb {
    transform: translateX(26px);
    background: linear-gradient(135deg, #ffe4a7, #ffc970);
  }

  #fine-adjust-bom-modal .fine-adjust-input {
    min-width: 110px;
    height: 72px;
    background: rgba(9, 18, 36, 0.86);
    color: #e8f0ff;
    border-color: rgba(123, 153, 210, 0.35);
    border-radius: 0.375rem;
    line-height: 1.4;
    padding: 0.5rem 0.75rem;
  }

  #fine-adjust-bom-modal .fine-adjust-note-stack {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    min-width: 300px;
    min-height: 72px;
  }

  #fine-adjust-bom-modal .fine-adjust-note-preview {
    flex: 1;
    min-height: 72px;
    height: 72px;
    padding: 0.55rem 0.75rem;
    border-radius: 0;
    border: none;
    background: rgba(9, 18, 36, 0.32);
    color: #dfe8ff;
    font-size: 0.82rem;
    line-height: 1.35;
    white-space: normal;
    word-break: break-word;
    box-shadow: none;
    min-width: 220px;
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
    height: 72px;
    min-height: 72px;
    max-height: 72px;
    background: rgba(13, 22, 39, 0.66);
    color: #b6c7eb;
    border-color: rgba(98, 120, 162, 0.32);
    border-radius: 0.375rem;
    pointer-events: none;
  }

  #fine-adjust-bom-modal .fine-adjust-plan-cell {
    min-width: 230px;
  }

  #fine-adjust-bom-modal .fine-adjust-plan-stack {
    position: relative;
    min-width: 190px;
    min-height: 54px;
    height: 54px;
    max-height: 54px;
    overflow: hidden;
    border-radius: 0.375rem;
  }

  #fine-adjust-bom-modal .fine-adjust-plan-input {
    position: relative;
    z-index: 1;
    min-height: 54px;
    height: 54px;
    max-height: 54px;
    padding-top: 0;
    padding-bottom: 0;
    line-height: 54px;
    transition: padding 0.2s ease, line-height 0.2s ease;
  }

  #fine-adjust-bom-modal .fine-adjust-plan-stack.has-hint .fine-adjust-plan-input {
    padding-top: 0.35rem;
    padding-bottom: 1.2rem;
    line-height: 1.4;
  }

  #fine-adjust-bom-modal .fine-adjust-plan-hint {
    position: absolute;
    left: 0.75rem;
    right: 0.75rem;
    bottom: 0.3rem;
    min-height: 0;
    padding: 0;
    color: rgba(191, 208, 244, 0.72);
    font-size: 0.6rem;
    line-height: 1.1;
    text-align: left;
    white-space: normal;
    word-break: break-word;
    pointer-events: none;
    z-index: 2;
    opacity: 0;
    transform: translateY(6px);
    transition: opacity 0.2s ease, transform 0.2s ease;
  }

  #fine-adjust-bom-modal .fine-adjust-plan-stack.has-hint .fine-adjust-plan-hint {
    opacity: 1;
    transform: translateY(0);
  }

  #fine-adjust-bom-modal .fine-adjust-note-stack.is-locked {
    border-color: rgba(255, 201, 112, 0.78);
    box-shadow: inset 0 0 0 1px rgba(255, 201, 112, 0.22);
  }

  #fine-adjust-bom-modal .fine-adjust-note-stack.is-locked.is-aluminum {
    border-color: rgba(127, 229, 255, 0.82);
    box-shadow: inset 0 0 0 1px rgba(127, 229, 255, 0.24);
  }

  #fine-adjust-bom-modal .fine-adjust-note-input.is-locked,
  #fine-adjust-bom-modal .fine-adjust-plan-input.is-locked {
    background: rgba(34, 28, 18, 0.72);
    border-color: rgba(255, 201, 112, 0.78);
    color: #ffe4af;
    box-shadow: inset 0 0 0 1px rgba(255, 201, 112, 0.18);
  }

  #fine-adjust-bom-modal .fine-adjust-note-stack.is-locked.is-aluminum .fine-adjust-note-input.is-locked,
  #fine-adjust-bom-modal .fine-adjust-plan-stack.is-locked.is-aluminum .fine-adjust-plan-input.is-locked {
    background: rgba(18, 34, 50, 0.76);
    border-color: rgba(127, 229, 255, 0.82);
    color: #a9f0ff;
    box-shadow: inset 0 0 0 1px rgba(127, 229, 255, 0.2);
  }

  #fine-adjust-bom-modal .fine-adjust-note-input.is-locked[readonly],
  #fine-adjust-bom-modal .fine-adjust-plan-input.is-locked[readonly] {
    cursor: not-allowed;
  }

  #fine-adjust-bom-modal .fine-adjust-plan-hint.is-locked {
    color: #ffd27e;
  }

  #fine-adjust-bom-modal .fine-adjust-plan-stack.is-locked.is-aluminum .fine-adjust-plan-hint.is-locked {
    color: #7fe5ff;
  }

  #fine-adjust-bom-modal .fine-adjust-input:focus {
    border-color: rgba(74, 179, 148, 0.72);
    box-shadow: 0 0 0 0.12rem rgba(74, 179, 148, 0.18);
  }
</style>
