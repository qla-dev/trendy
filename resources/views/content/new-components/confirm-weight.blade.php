<div
  class="modal fade"
  id="confirm-weight-modal"
  tabindex="-1"
  aria-labelledby="confirm-weight-modal-label"
  aria-hidden="true"
  data-bs-backdrop="static"
  data-bs-keyboard="false"
>
  <div class="modal-dialog modal-dialog-centered confirm-weight-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <div class="confirm-weight-header-copy">
          <span class="confirm-weight-kicker">Potvrda količine materijala</span>
          <p class="mb-0" id="confirm-weight-help-text">Unesi faktor količine za planiranu potrošnju.</p>
        </div>
        <button type="button" class="confirm-weight-close-btn" data-bs-dismiss="modal" aria-label="Zatvori">
          <span aria-hidden="true">X</span>
        </button>
      </div>

      <div class="modal-body">
        <div class="confirm-weight-layout">
          <section class="confirm-weight-info-panel">
            <div id="confirm-material-action-indicator" class="confirm-material-action-indicator d-none">
              Dodat će novu stavku na radni nalog.
            </div>

            <div class="confirm-weight-selection-name" id="scanned-material-name">0 komponenti</div>

            <div id="confirm-material-code-wrap" class="confirm-material-code-card d-none">
              <span class="confirm-material-meta-icon" aria-hidden="true"><i class="fa fa-tag"></i></span>
              <div class="confirm-material-meta-copy">
                <span class="confirm-material-meta-label">Šifra</span>
                <strong id="confirm-material-code">-</strong>
              </div>
            </div>

            <div id="confirm-material-details-wrap" class="confirm-material-details d-none">
              <div class="confirm-material-meta-grid">
                <div class="confirm-material-meta-item">
                  <span class="confirm-material-meta-icon" aria-hidden="true"><i class="fa fa-cubes"></i></span>
                  <div class="confirm-material-meta-copy">
                    <span class="confirm-material-meta-label">Set</span>
                    <strong id="confirm-material-set">-</strong>
                  </div>
                </div>
                <div class="confirm-material-meta-item">
                  <span class="confirm-material-meta-icon" aria-hidden="true"><i class="fa fa-balance-scale"></i></span>
                  <div class="confirm-material-meta-copy">
                    <span class="confirm-material-meta-label">MJ</span>
                    <strong id="confirm-material-unit">-</strong>
                  </div>
                </div>
                <div class="confirm-material-meta-item">
                  <span class="confirm-material-meta-icon" aria-hidden="true"><i class="fa fa-barcode"></i></span>
                  <div class="confirm-material-meta-copy">
                    <span class="confirm-material-meta-label">QID</span>
                    <strong id="confirm-material-qid">-</strong>
                  </div>
                </div>
                <div class="confirm-material-meta-item">
                  <span class="confirm-material-meta-icon" aria-hidden="true"><i class="fa fa-cube"></i></span>
                  <div class="confirm-material-meta-copy">
                    <span class="confirm-material-meta-label">Trenutno na RN</span>
                    <strong id="confirm-material-current-qty">0</strong>
                  </div>
                </div>
                <div class="confirm-material-meta-item">
                  <span class="confirm-material-meta-icon" aria-hidden="true"><i class="fa fa-list-ol"></i></span>
                  <div class="confirm-material-meta-copy">
                    <span class="confirm-material-meta-label">Pozicija RN</span>
                    <strong id="confirm-material-rn-line">-</strong>
                  </div>
                </div>
                <div class="confirm-material-meta-item">
                  <span class="confirm-material-meta-icon" aria-hidden="true"><i class="fa fa-archive"></i></span>
                  <div class="confirm-material-meta-copy">
                    <span class="confirm-material-meta-label">Zaliha</span>
                    <strong id="confirm-material-stock-qty">0</strong>
                  </div>
                </div>
                <div class="confirm-material-meta-item">
                  <span class="confirm-material-meta-icon" aria-hidden="true"><i class="fa fa-truck"></i></span>
                  <div class="confirm-material-meta-copy">
                    <span class="confirm-material-meta-label">Rok isporuke</span>
                    <strong id="confirm-material-deadline">-</strong>
                  </div>
                </div>
                <div class="confirm-material-meta-item">
                  <span class="confirm-material-meta-icon" aria-hidden="true"><i class="fa fa-clock-o"></i></span>
                  <div class="confirm-material-meta-copy">
                    <span class="confirm-material-meta-label">Ažurirano</span>
                    <strong id="confirm-material-updated-at">-</strong>
                  </div>
                </div>
              </div>

            </div>
          </section>

          <section class="confirm-weight-console">
            <div class="confirm-weight-screen-shell">
              <div class="confirm-weight-screen-head">
                <span class="confirm-weight-screen-caption">Količina</span>

                <div class="confirm-weight-unit-switch" id="confirm-weight-unit-switch" aria-label="Mjernica jedinica">
                  <button type="button" class="confirm-weight-unit-btn is-active" data-unit="AUTO">AUTO</button>
                  <button type="button" class="confirm-weight-unit-btn" data-unit="KG">KG</button>
                  <button type="button" class="confirm-weight-unit-btn" data-unit="MJ">MJ</button>
                  <button type="button" class="confirm-weight-unit-btn" data-unit="RDS">RDS</button>
                </div>
              </div>

              <div class="confirm-weight-screen">
                <div class="confirm-weight-screen-readout">
                  <span class="confirm-weight-screen-value" id="confirm-weight-screen-value">1</span>
                  <span class="confirm-weight-screen-unit" id="confirm-weight-screen-unit">AUTO</span>
                </div>

                <button
                  type="button"
                  class="confirm-weight-screen-delete"
                  id="confirm-weight-backspace-btn"
                  data-keypad-action="backspace"
                  aria-label="Brisi zadnji unos"
                >
                  <span aria-hidden="true">&#9003;</span>
                </button>
              </div>

              <input
                type="text"
                id="weight-input"
                value="1"
                inputmode="none"
                autocomplete="off"
                readonly
                tabindex="-1"
                class="d-none"
                aria-hidden="true"
              >

              <select class="d-none" id="weight-unit-select" tabindex="-1" aria-hidden="true">
                <option value="AUTO" selected>Auto (iz sastavnice)</option>
                <option value="KG">KG</option>
                <option value="MJ">MJ</option>
                <option value="RDS">RDS</option>
              </select>
            </div>

            <div class="confirm-weight-keypad" id="confirm-weight-keypad">
              <button type="button" class="confirm-weight-key confirm-weight-key--digit" data-keypad-value="1">1</button>
              <button type="button" class="confirm-weight-key confirm-weight-key--digit" data-keypad-value="2">2</button>
              <button type="button" class="confirm-weight-key confirm-weight-key--digit" data-keypad-value="3">3</button>
              <button type="button" class="confirm-weight-key confirm-weight-key--clear" id="confirm-weight-clear-btn" data-keypad-action="clear">Poništi</button>

              <button type="button" class="confirm-weight-key confirm-weight-key--digit" data-keypad-value="4">4</button>
              <button type="button" class="confirm-weight-key confirm-weight-key--digit" data-keypad-value="5">5</button>
              <button type="button" class="confirm-weight-key confirm-weight-key--digit" data-keypad-value="6">6</button>
              <button type="button" class="confirm-weight-key confirm-weight-key--digit" data-keypad-value="7">7</button>
              <button type="button" class="confirm-weight-key confirm-weight-key--digit" data-keypad-value="8">8</button>
              <button type="button" class="confirm-weight-key confirm-weight-key--digit" data-keypad-value="9">9</button>
              <button type="button" class="confirm-weight-key confirm-weight-key--ok" id="confirm-add-sirovina-btn">
                <span class="confirm-weight-ok-label">OK</span>
                <span class="confirm-weight-ok-subtitle">Sačuvaj</span>
              </button>

              <button type="button" class="confirm-weight-key confirm-weight-key--digit" data-keypad-value="0">0</button>
              <button type="button" class="confirm-weight-key confirm-weight-key--digit" data-keypad-value="00">00</button>
              <button type="button" class="confirm-weight-key confirm-weight-key--digit" data-keypad-value=".">.</button>
            </div>
          </section>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
  #confirm-weight-modal {
    --confirm-meta-row-gap: clamp(0.7rem, 1.15vh, 1rem);
    --confirm-shell-bg:
      radial-gradient(circle at top left, rgba(76, 108, 168, 0.2), transparent 32%),
      radial-gradient(circle at top right, rgba(92, 225, 194, 0.14), transparent 24%),
      linear-gradient(180deg, rgba(8, 13, 23, 0.99) 0%, rgba(12, 20, 34, 0.98) 100%);
    --confirm-shell-border: rgba(177, 189, 216, 0.22);
    --confirm-shell-shadow:
      0 28px 70px rgba(2, 6, 18, 0.64),
      0 0 0 1px rgba(92, 225, 194, 0.08);
    --confirm-panel-bg: rgba(10, 16, 28, 0.76);
    --confirm-panel-border: rgba(177, 189, 216, 0.16);
    --confirm-text: #d7e2fa;
    --confirm-text-muted: #9fb2d4;
    --confirm-text-strong: #f3f7ff;
    --confirm-screen-bg:
      linear-gradient(180deg, rgba(9, 19, 32, 0.98), rgba(6, 12, 24, 0.96)),
      linear-gradient(90deg, rgba(92, 225, 194, 0.05), transparent 42%);
    --confirm-screen-border: rgba(92, 225, 194, 0.42);
    --confirm-screen-text: #8cf2d7;
    --confirm-screen-unit-text: #dcf8f0;
    --confirm-unit-bg: rgba(255, 255, 255, 0.05);
    --confirm-unit-border: rgba(177, 189, 216, 0.16);
    --confirm-unit-active-bg: linear-gradient(135deg, rgba(92, 225, 194, 0.24), rgba(64, 175, 232, 0.22));
    --confirm-unit-active-border: rgba(92, 225, 194, 0.5);
    --confirm-key-bg: linear-gradient(180deg, rgba(15, 22, 36, 0.98), rgba(10, 16, 28, 0.98));
    --confirm-key-border: rgba(177, 189, 216, 0.16);
    --confirm-key-hover: linear-gradient(180deg, rgba(20, 30, 48, 1), rgba(12, 20, 34, 1));
    --confirm-key-text: #e9f2ff;
    --confirm-key-shadow:
      0 14px 24px rgba(3, 8, 18, 0.34),
      inset 0 1px 0 rgba(255, 255, 255, 0.04);
    --confirm-clear-bg: linear-gradient(180deg, rgba(228, 88, 66, 0.98), rgba(179, 44, 29, 0.98));
    --confirm-clear-border: rgba(255, 191, 183, 0.16);
    --confirm-ok-bg: linear-gradient(180deg, rgba(47, 192, 146, 0.98), rgba(14, 127, 104, 0.98));
    --confirm-ok-border: rgba(163, 255, 223, 0.2);
    --confirm-ok-text: #ebfff7;
    --confirm-delete-bg: linear-gradient(180deg, rgba(228, 88, 66, 0.98), rgba(179, 44, 29, 0.98));
    --confirm-delete-border: rgba(255, 191, 183, 0.16);
    --confirm-indicator-bg: rgba(255, 159, 67, 0.12);
    --confirm-indicator-text: #ffc47d;
    --confirm-indicator-update-bg: rgba(92, 225, 194, 0.12);
    --confirm-indicator-update-text: #9ef7df;
  }

  #confirm-weight-modal .modal-dialog {
    max-width: min(980px, calc(100vw - 2rem));
    margin: 1rem auto;
  }

  #confirm-weight-modal .modal-content {
    border-radius: 1.8rem;
    border: 1px solid var(--confirm-shell-border);
    background: var(--confirm-shell-bg);
    color: var(--confirm-text);
    box-shadow: var(--confirm-shell-shadow);
    overflow: hidden;
    position: relative;
    backdrop-filter: blur(14px);
  }

  #confirm-weight-modal .modal-content::before {
    content: '';
    position: absolute;
    inset: 0;
    background:
      linear-gradient(rgba(172, 180, 194, 0.035) 1px, transparent 1px),
      linear-gradient(90deg, rgba(172, 180, 194, 0.035) 1px, transparent 1px);
    background-size: 26px 26px;
    opacity: 0.22;
    pointer-events: none;
  }

  #confirm-weight-modal .modal-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    padding: 1.1rem 1.25rem 0.85rem;
    border-bottom: 1px solid rgba(177, 189, 216, 0.12);
    background: rgba(7, 12, 22, 0.48);
    position: relative;
    z-index: 1;
  }

  #confirm-weight-modal .modal-body {
    padding: 1.05rem 1.25rem 1.25rem;
    position: relative;
    z-index: 1;
  }

  #confirm-weight-modal .confirm-weight-header-copy {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.28rem;
  }

  #confirm-weight-modal .confirm-weight-kicker {
    font-size: 2rem;
    font-weight: 800;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: #9ef7df;
  }

  #confirm-weight-modal .modal-title {
    font-size: clamp(1.45rem, 1.1rem + 0.95vw, 2.1rem);
    font-weight: 800;
    color: var(--confirm-text-strong);
  }

  #confirm-weight-modal #confirm-weight-help-text {
    color: var(--confirm-text-muted);
    font-size: 1.05rem;
    line-height: 1.45;
  }

  #confirm-weight-modal .confirm-weight-close-btn {
    width: 4.4rem;
    height: 4.4rem;
    border: 0;
    border-radius: 0.95rem;
    background: var(--confirm-delete-bg);
    color: #ffffff;
    box-shadow:
      0 14px 28px rgba(78, 12, 6, 0.36),
      inset 0 0 0 1px var(--confirm-delete-border);
    transition: transform 0.18s ease, opacity 0.18s ease;
    flex: 0 0 auto;
  }

  #confirm-weight-modal .confirm-weight-close-btn span {
    font-size: 2rem;
    line-height: 1;
    font-weight: 300;
  }

  #confirm-weight-modal .confirm-weight-close-btn:hover,
  #confirm-weight-modal .confirm-weight-close-btn:focus {
    opacity: 0.92;
    transform: translateY(-1px);
  }

  #confirm-weight-modal .confirm-weight-layout {
    display: grid;
    grid-template-columns: minmax(260px, 0.92fr) minmax(0, 1.38fr);
    gap: 1rem;
    align-items: stretch;
  }

  #confirm-weight-modal .confirm-weight-info-panel,
  #confirm-weight-modal .confirm-weight-console {
    min-width: 0;
  }

  #confirm-weight-modal .confirm-weight-info-panel {
    display: flex;
    flex-direction: column;
    gap: clamp(0.85rem, 1.1vh, 1.15rem);
    min-height: 100%;
  }

  #confirm-weight-modal .confirm-weight-selection-name {
    display: flex;
    align-items: center;
    padding: 1rem 1.05rem;
    border-radius: 1rem;
    border: 1px solid var(--confirm-panel-border);
    background: var(--confirm-panel-bg);
    color: var(--confirm-text-strong);
    font-size: clamp(1.45rem, 1.15rem + 0.9vw, 2rem);
    line-height: 1.16;
    font-weight: 800;
    word-break: break-word;
    min-height: 5.8rem;
    backdrop-filter: blur(10px);
    box-shadow:
      inset 0 1px 0 rgba(255, 255, 255, 0.03),
      0 18px 34px rgba(2, 6, 18, 0.22);
  }

  #confirm-weight-modal .confirm-material-details {
    display: flex;
    flex: 1 1 auto;
    min-height: 0;
    padding: 1rem 1.05rem;
    border-radius: 1rem;
    border: 1px solid var(--confirm-panel-border);
    background: var(--confirm-panel-bg);
    backdrop-filter: blur(10px);
    box-shadow:
      inset 0 1px 0 rgba(255, 255, 255, 0.03),
      0 18px 34px rgba(2, 6, 18, 0.22);
  }

  #confirm-weight-modal .confirm-material-code-card {
    padding: 1rem 1.05rem;
    border-radius: 1rem;
    border: 1px solid var(--confirm-panel-border);
    background: var(--confirm-panel-bg);
    backdrop-filter: blur(10px);
    box-shadow:
      inset 0 1px 0 rgba(255, 255, 255, 0.03),
      0 18px 34px rgba(2, 6, 18, 0.22);
    display: flex;
    align-items: center;
    gap: 0.85rem;
  }

  #confirm-weight-modal .confirm-material-meta-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    row-gap: var(--confirm-meta-row-gap);
    column-gap: 1rem;
    align-content: space-between;
    flex: 1 1 auto;
    min-height: 100%;
  }

  #confirm-weight-modal .confirm-material-meta-item {
    min-width: 0;
    display: flex;
    align-items: flex-start;
    gap: 0.7rem;
  }

  #confirm-weight-modal .confirm-material-meta-icon {
    width: 2.6rem;
    height: 2.6rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 2.6rem;
    border-radius: 0.85rem;
    border: 1px solid rgba(177, 189, 216, 0.14);
    background: rgba(255, 255, 255, 0.04);
    color: #8cf2d7;
    font-size: 1.16rem;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.03);
  }

  #confirm-weight-modal .confirm-material-meta-copy {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.34rem;
  }

  #confirm-weight-modal .confirm-material-meta-label {
    font-size: 0.82rem;
    line-height: 1;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--confirm-text-muted);
    font-weight: 800;
  }

  #confirm-weight-modal .confirm-material-meta-item strong {
    color: var(--confirm-text-strong);
    font-size: 1.28rem;
    word-break: break-word;
  }

  #confirm-weight-modal .confirm-material-action-indicator {
    padding: 0.8rem 0.9rem;
    border-radius: 0.85rem;
    background: var(--confirm-indicator-bg);
    color: var(--confirm-indicator-text);
    font-size: 1.08rem;
    font-weight: 700;
    line-height: 1.42;
  }

  #confirm-weight-modal .confirm-material-action-indicator.is-update {
    background: var(--confirm-indicator-update-bg);
    color: var(--confirm-indicator-update-text);
  }

  #confirm-weight-modal .confirm-weight-console {
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    gap: 0.95rem;
  }

  #confirm-weight-modal .confirm-weight-screen-shell {
    padding: 0.95rem;
    border-radius: 1.1rem;
    border: 1px solid rgba(177, 189, 216, 0.18);
    background: rgba(10, 16, 28, 0.76);
    box-shadow:
      inset 0 1px 0 rgba(255, 255, 255, 0.03),
      0 18px 34px rgba(2, 6, 18, 0.24);
    backdrop-filter: blur(10px);
  }

  #confirm-weight-modal .confirm-weight-screen-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.8rem;
    margin-bottom: 0.85rem;
  }

  #confirm-weight-modal .confirm-weight-screen-caption {
    font-size: 0.8rem;
    font-weight: 800;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: #7fd7c6;
  }

  #confirm-weight-modal .confirm-weight-unit-switch {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 0.45rem;
  }

  #confirm-weight-modal .confirm-weight-unit-btn {
    min-width: 4.2rem;
    padding: 0.55rem 0.75rem;
    border-radius: 0.8rem;
    border: 1px solid var(--confirm-unit-border);
    background: var(--confirm-unit-bg);
    color: #d7e2fa;
    font-size: 0.88rem;
    font-weight: 800;
    letter-spacing: 0.05em;
    transition: transform 0.16s ease, border-color 0.16s ease, background-color 0.16s ease, box-shadow 0.16s ease;
  }

  #confirm-weight-modal .confirm-weight-unit-btn:hover,
  #confirm-weight-modal .confirm-weight-unit-btn:focus {
    transform: translateY(-1px);
    background: rgba(255, 255, 255, 0.08);
  }

  #confirm-weight-modal .confirm-weight-unit-btn.is-active {
    background: var(--confirm-unit-active-bg);
    border-color: var(--confirm-unit-active-border);
    color: #edfffb;
    box-shadow:
      inset 0 -1px 0 rgba(255, 255, 255, 0.06),
      0 12px 22px rgba(7, 18, 34, 0.24);
  }

  #confirm-weight-modal .confirm-weight-screen {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 0.8rem;
    align-items: stretch;
  }

  #confirm-weight-modal .confirm-weight-screen-readout {
    min-width: 0;
    min-height: 8.3rem;
    padding: 1rem 1.2rem;
    border-radius: 1rem;
    border: 1px solid var(--confirm-screen-border);
    background: var(--confirm-screen-bg);
    box-shadow:
      inset 0 0 0 1px rgba(92, 225, 194, 0.12),
      inset 0 0 24px rgba(92, 225, 194, 0.05),
      0 14px 28px rgba(2, 6, 18, 0.26);
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 1rem;
    position: relative;
    overflow: hidden;
  }

  #confirm-weight-modal .confirm-weight-screen-readout::before {
    content: '';
    position: absolute;
    inset: 0;
    background:
      linear-gradient(rgba(92, 225, 194, 0.04) 1px, transparent 1px),
      linear-gradient(90deg, rgba(92, 225, 194, 0.04) 1px, transparent 1px);
    background-size: 22px 22px;
    opacity: 0.28;
    pointer-events: none;
  }

  #confirm-weight-modal .confirm-weight-screen-value {
    min-width: 0;
    font-family: "Courier New", "Lucida Console", monospace;
    font-size: clamp(2.6rem, 2rem + 2vw, 4.8rem);
    line-height: 0.95;
    letter-spacing: 0.04em;
    font-weight: 700;
    color: var(--confirm-screen-text);
    text-shadow: 0 0 18px rgba(92, 225, 194, 0.18);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    position: relative;
    z-index: 1;
  }

  #confirm-weight-modal .confirm-weight-screen-unit {
    flex: 0 0 auto;
    font-size: clamp(1rem, 0.82rem + 0.5vw, 1.3rem);
    font-weight: 800;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--confirm-screen-unit-text);
    padding-bottom: 0.36rem;
    position: relative;
    z-index: 1;
  }

  #confirm-weight-modal .confirm-weight-screen-delete {
    width: 5.35rem;
    min-height: 8.3rem;
    border: 0;
    border-radius: 1rem;
    background: var(--confirm-delete-bg);
    color: #ffffff;
    box-shadow:
      0 14px 28px rgba(78, 12, 6, 0.36),
      inset 0 0 0 1px var(--confirm-delete-border);
    transition: transform 0.16s ease, opacity 0.16s ease;
  }

  #confirm-weight-modal .confirm-weight-screen-delete span {
    font-size: 2.2rem;
    line-height: 1;
    font-weight: 300;
  }

  #confirm-weight-modal .confirm-weight-screen-delete:hover,
  #confirm-weight-modal .confirm-weight-screen-delete:focus {
    opacity: 0.92;
    transform: translateY(-1px);
  }

  #confirm-weight-modal .confirm-weight-keypad {
    --confirm-keypad-gap: 0.8rem;
    --confirm-key-height: 5.2rem;
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: var(--confirm-keypad-gap);
  }

  #confirm-weight-modal .confirm-weight-key {
    min-height: var(--confirm-key-height);
    border-radius: 0.95rem;
    border: 1px solid var(--confirm-key-border);
    background: var(--confirm-key-bg);
    color: var(--confirm-key-text);
    font-size: clamp(1.4rem, 1rem + 0.9vw, 2rem);
    font-weight: 500;
    box-shadow: var(--confirm-key-shadow);
    transition: transform 0.15s ease, background-color 0.15s ease, filter 0.15s ease;
    touch-action: manipulation;
  }

  #confirm-weight-modal .confirm-weight-key:hover,
  #confirm-weight-modal .confirm-weight-key:focus {
    transform: translateY(-1px);
    background: var(--confirm-key-hover);
    border-color: rgba(92, 225, 194, 0.24);
  }

  #confirm-weight-modal .confirm-weight-key--digit {
    font-family: "Trebuchet MS", Arial, sans-serif;
    text-shadow: 0 0 14px rgba(92, 225, 194, 0.06);
  }

  #confirm-weight-modal .confirm-weight-key--clear {
    background: var(--confirm-clear-bg);
    border-color: var(--confirm-clear-border);
    color: #ffffff;
    font-size: 1.15rem;
    font-weight: 700;
    box-shadow:
      0 14px 28px rgba(78, 12, 6, 0.36),
      inset 0 0 0 1px rgba(255, 255, 255, 0.06);
  }

  #confirm-weight-modal .confirm-weight-key--clear:hover,
  #confirm-weight-modal .confirm-weight-key--clear:focus {
    background: var(--confirm-clear-bg);
    border-color: var(--confirm-clear-border);
    color: #ffffff;
    opacity: 0.92;
  }

  #confirm-weight-modal .confirm-weight-key--ok {
    grid-column: 4;
    grid-row: 2 / span 3;
    min-height: calc((var(--confirm-key-height) * 3) + (var(--confirm-keypad-gap) * 2));
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    background: var(--confirm-ok-bg);
    border: 1px solid var(--confirm-ok-border);
    color: var(--confirm-ok-text);
    box-shadow:
      0 20px 34px rgba(4, 52, 38, 0.3),
      inset 0 0 0 1px rgba(235, 255, 247, 0.08);
  }

  #confirm-weight-modal .confirm-weight-key--ok:hover,
  #confirm-weight-modal .confirm-weight-key--ok:focus {
    background: var(--confirm-ok-bg);
    border-color: var(--confirm-ok-border);
    color: var(--confirm-ok-text);
    opacity: 0.92;
  }

  #confirm-weight-modal .confirm-weight-ok-label {
    display: block;
    font-size: clamp(2rem, 1.4rem + 1.5vw, 3rem);
    line-height: 1;
    font-weight: 500;
    letter-spacing: 0.04em;
  }

  #confirm-weight-modal .confirm-weight-ok-subtitle {
    display: block;
    font-size: 0.9rem;
    font-weight: 800;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    opacity: 0.92;
  }

  #confirm-weight-modal .confirm-weight-key--ok .spinner-border {
    width: 1.4rem;
    height: 1.4rem;
    border-width: 0.16em;
  }

  @media (max-width: 991.98px) {
    #confirm-weight-modal .confirm-weight-layout {
      grid-template-columns: minmax(0, 1fr);
    }
  }

  @media (max-width: 767.98px) {
    #confirm-weight-modal .modal-dialog {
      max-width: calc(100vw - 1rem);
      margin: 0.5rem auto;
    }

    #confirm-weight-modal .modal-header,
    #confirm-weight-modal .modal-body {
      padding-left: 0.9rem;
      padding-right: 0.9rem;
    }

    #confirm-weight-modal .confirm-weight-screen-head {
      flex-direction: column;
      align-items: stretch;
    }

    #confirm-weight-modal .confirm-weight-unit-switch {
      justify-content: flex-start;
    }

    #confirm-weight-modal .confirm-weight-key {
      min-height: 4.7rem;
    }

    #confirm-weight-modal .confirm-weight-screen-delete {
      width: 4.4rem;
      min-height: 7.2rem;
    }

    #confirm-weight-modal .confirm-material-meta-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }

  @media (max-width: 575.98px) {
    #confirm-weight-modal .confirm-material-meta-grid {
      grid-template-columns: minmax(0, 1fr);
    }
  }

  @media (orientation: portrait) {
    #confirm-weight-modal {
      --confirm-meta-row-gap: clamp(0.4rem, 0.85vh, 0.82rem);
    }

    #confirm-weight-modal .modal-dialog {
      max-width: none;
      width: 100vw;
      height: 100vh;
      max-height: 100vh;
      margin: 0;
      min-height: 100vh;
      align-items: stretch;
    }

    #confirm-weight-modal .modal-content {
      min-height: 100%;
      max-height: 100%;
      border-radius: 0;
      border-left: 0;
      border-right: 0;
    }

    #confirm-weight-modal .modal-body {
      flex: 1 1 auto;
      display: flex;
      overflow-y: auto;
    }

    #confirm-weight-modal .confirm-weight-layout {
      width: 100%;
      display: flex;
      flex-direction: column;
      flex: 1 1 auto;
      min-height: 0;
    }

    #confirm-weight-modal .confirm-weight-info-panel {
      flex: 1 1 auto;
      min-height: 0;
    }

    #confirm-weight-modal .confirm-weight-console {
      flex: 0 0 auto;
    }

    #confirm-weight-modal .confirm-weight-key {
      min-height: 5.6rem;
    }

    #confirm-weight-modal .confirm-weight-keypad {
      --confirm-key-height: 5.6rem;
    }

    #confirm-weight-modal .confirm-weight-screen-readout {
      min-height: 9rem;
    }

    #confirm-weight-modal .confirm-weight-screen-delete {
      min-height: 9rem;
    }
  }

  @supports (height: 100dvh) {
    @media (orientation: portrait) {
      #confirm-weight-modal {
        --confirm-meta-row-gap: clamp(0.4rem, 0.85dvh, 0.82rem);
      }

      #confirm-weight-modal .modal-dialog {
        height: 100dvh;
        max-height: 100dvh;
        min-height: 100dvh;
      }
    }
  }

  @media (max-width: 575.98px) {
    #confirm-weight-modal .confirm-weight-close-btn {
      width: 3.6rem;
      height: 3.6rem;
    }

    #confirm-weight-modal .confirm-weight-close-btn span {
      font-size: 1.7rem;
    }

    #confirm-weight-modal .confirm-weight-keypad {
      --confirm-keypad-gap: 0.65rem;
    }

    #confirm-weight-modal .confirm-weight-key {
      min-height: 4.4rem;
      border-radius: 0.8rem;
    }

    #confirm-weight-modal .confirm-weight-keypad {
      --confirm-key-height: 4.4rem;
    }

    #confirm-weight-modal .confirm-weight-screen {
      grid-template-columns: minmax(0, 1fr) 4.2rem;
    }
  }
</style>
