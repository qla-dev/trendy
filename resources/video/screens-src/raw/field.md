## Static HTML recreations of six eNalog.app screens (A–F) for the vertical promo video

All six views were read; nothing was modified. The HTML below is condensed from the real Blade markup and the JS that builds rows. Class names, inline styles, labels, headers and icons are copied from the code. Sample data uses the formats the code produces. Values I made up are marked "plausible".

### Things that apply to every screen

- **Icons are mostly Font Awesome 4, not feather.** The work-order pages A, B and C use `<i class="fa fa-qrcode">`, `fa-barcode`, `fa-list`, `fa-cog` and so on. Only the materials page D, the documents page E and the NFC page F use `<i data-feather="...">`. You need FA 4 CSS and feather icons (`feather.replace()`), or inline SVGs.
- **Number and date formats:**
  - Work-order (RN) and order numbers have 13 digits, shown as `NN-NNNN-NNNNNNN`, e.g. `26-6000-0001687` and `25-0110-0003084`.
  - The header shows the order number with its position after a semicolon: `25-0110-0003084;1`.
  - Quantities use a decimal comma (`hr-HR`), e.g. `12,5`. Dates are `d.m.Y`, e.g. `07.10.2026`. Money is `1.234,56 KM`.
- **Status and priority labels:**
  - Statuses: `Planiran`, `Otvoren`, `Rezerviran`, `Raspisan`, `U radu`, `Djelomično zaključen`, `Zaključen`.
  - Priorities: `1 - Visoki prioritet` (danger), `5 - Uobičajeni prioritet` (warning), `7 - Materijal razdužen`, `10 - Niski prioritet` (info), `15 - Uzorci` (info).
- **Pinned bottom footer on B and other pages.** When a page defines `@section('footer-actions')`, `panels/footer.blade.php` renders `<footer class="footer footer-light screen-actions-footer"><div class="screen-footer-actions">…</div></footer>`. It is fixed to the bottom with background `rgba(255,255,255,.96)` and `backdrop-filter: blur(10px)`. At ≤480px it becomes a 2-column grid of full-width buttons.
- **Shared action-button classes.** `app-table-action-btn` and its `--primary`, `--warning`, `--success`, `--accent` and `--danger` variants come from the global compiled CSS, not from these views.

---

### A. Scan work order (`app-invoice-preview.blade.php` + `new-components/nalog-scan.blade.php`)

**How it looks at 390px.** All of this comes from the `@media (max-width:480px)` block.
- **Bottom bar:** `.wo-mobile-top-actions` is a fixed frosted card pinned to the bottom.
  - The top row shows `Prioritet RN:` with a coloured dot and `Zaštita:`.
  - Below it are two buttons side by side, 44px tall: green "Skeniraj radni nalog" and primary "Pripremi materijal".
- **Header:** 62px round logo plus "eNalog.app" at 1.62rem on the left, a 96px QR on the right. Below, the company address (0.73rem, 43% wide) sits left and the RN number with dates sits right-aligned.
- **Tabs:** the tabs row scrolls horizontally.
- **Mobile column labels:** `alt.`, `poz`, and `AKCIJA` as a collapsible sticky column.
- **Actions column:** it drops below the main card. Its primary button pair is hidden, so only an "Ostale opcije" collapse toggle is visible.

**Main work-order detail (content of `.content-body`):**
```html
<section class="invoice-preview-wrapper">
  <!-- fixed bottom bar on phones (<=480px) -->
  <div class="wo-mobile-top-actions">
    <div class="card"><div class="card-body">
      <div class="wo-mobile-meta-row">
        <div id="wo-mobile-priority" class="wo-mobile-priority wo-priority-warning">
          <span class="wo-meta-chip-label">Prioritet RN:</span>
          <span class="wo-mobile-priority-current wo-meta-chip-value"><span class="wo-mobile-priority-dot" aria-hidden="true"></span><strong id="wo-mobile-priority-value">5 - Uobičajeni prioritet</strong></span>
        </div>
        <div class="wo-mobile-protection">
          <span class="wo-meta-chip-label">Zaštita:</span>
          <strong class="wo-mobile-protection-value wo-meta-chip-value">PLASTIFIKACIJA RAL 7016</strong><!-- plausible -->
        </div>
      </div>
      <button class="btn btn-success w-100 mb-75 d-flex justify-content-center align-items-center"><i class="fa fa-qrcode me-50" style="font-size: 20px;"></i> Skeniraj radni nalog</button>
      <button class="btn btn-primary w-100 d-flex justify-content-center align-items-center"><i class="fa fa-barcode me-50" style="font-size: 20px;"></i> Pripremi materijal</button>
    </div></div>
  </div>

  <div class="row invoice-preview">
    <div class="col-xl-9 col-md-8 col-12 wo-preview-main-col">
      <div class="card invoice-preview-card">
        <div class="card-body invoice-padding pb-0">
          <div class="wo-header-shell invoice-spacing mt-0">
            <div class="wo-header-brand-row">
              <div class="logo-wrapper">
                <img src="/images/logo/TrendyCNC.png" alt="Trendy d.o.o." width="50" height="auto" class="wo-brand-logo">
                <h3 class="text-primary invoice-logo">eNalog.app</h3>
              </div>
              <div class="wo-header-qr-block">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=0&data=250110000308%3B1%3BTR-NOS-200" alt="QR Code" class="wo-preview-qr-image">
              </div>
            </div>
            <div class="wo-header-details-row">
              <div class="wo-header-company-block">
                <p class="card-text mb-25">Trendy d.o.o.</p>
                <p class="card-text mb-25">Bratstvo 11, 72290</p>
                <p class="card-text mb-25">Novi Travnik, BiH</p>
                <p class="card-text mb-0">+387 30 525 252</p>
                <p class="card-text mb-0">info@trendy.ba</p>
              </div>
              <div class="wo-header-right-column"><div class="wo-header-main-row"><div class="wo-header-meta">
                <h4 class="invoice-title"><span class="invoice-title-stack">
                  <span><span class="invoice-key">RN</span><span class="invoice-number">26-6000-0001687</span></span>
                  <span class="invoice-order-number">Narudžba:<span class="invoice-number">25-0110-0003084;1</span></span>
                </span></h4>
                <div class="invoice-date-wrapper"><p class="invoice-date-title">Datum izdavanja:</p><p class="invoice-date">07.10.2026</p></div>
                <div class="invoice-date-wrapper"><p class="invoice-date-title">Planirani start:</p><p class="invoice-date">09.10.2026</p></div>
              </div></div></div>
            </div>
          </div>
        </div>
        <hr class="invoice-spacing" />
        <div class="card-body invoice-padding pt-0 pb-0">
          <div class="row invoice-spacing wo-contact-row">
            <div class="col-xl-4 col-md-6 p-0 wo-contact-col"><h6 class="mb-2">Pošiljatelj:</h6><h6 class="mb-25">Trendy d.o.o.</h6></div>
            <div class="col-xl-4 d-none d-xl-block"></div>
            <div class="col-xl-4 col-md-6 p-0 wo-contact-col"><h6 class="mb-2">Primatelj:</h6><h6 class="mb-25">Hidraulika Flex d.o.o.</h6></div><!-- plausible -->
          </div>
        </div>
        <hr class="invoice-spacing" />
        <div class="card-body invoice-padding pt-2 pb-0">
          <div class="wo-product-hero"><div class="wo-product-hero-row">
            <div class="wo-product-hero-main">
              <span class="wo-product-kicker">Naziv proizvoda</span>
              <span class="wo-product-title">NOSAČ MOTORA 200x120</span><!-- plausible -->
              <span class="wo-product-code-accent" aria-label="Šifra proizvoda">
                <span class="wo-product-code-label">Šifra proizvoda</span>
                <span class="wo-product-code-value">TR-NOS-200</span>
              </span>
            </div>
            <div class="wo-product-qty" aria-label="Količina proizvoda">
              <span class="wo-product-qty-label">Količina</span>
              <span class="wo-product-qty-metric"><span class="wo-product-qty-value">120</span><span class="wo-product-qty-unit">KOM</span></span>
            </div>
          </div></div>
        </div>
        <div class="card-body invoice-padding pt-0 pb-0">
          <div class="wo-progress-shell">
            <div class="wo-progress-head"><span>Realizacija po količini</span><span>45 %</span></div>
            <div class="wo-progress wo-progress-live"><div class="wo-progress-bar" style="width: 45%;"></div></div>
          </div>
        </div>
        <div class="card-body invoice-padding pt-0 pb-0">
          <div class="wo-chip-shell"><div class="wo-meta-chip-row mb-0">
            <div class="wo-meta-chip wo-chip-slate"><span class="wo-meta-chip-label">Tip dokumento</span><span class="wo-meta-chip-value">6000</span></div>
            <div class="wo-meta-chip wo-chip-slate"><span class="wo-meta-chip-label">Varijanta</span><span class="wo-meta-chip-value">1</span></div>
            <div class="wo-meta-chip wo-chip-slate"><span class="wo-meta-chip-label">Lokacija</span><span class="wo-meta-chip-value">CNC</span></div>
            <span class="wo-flag-pill wo-flag-danger"><span class="wo-flag-dot"></span><span>Povrat: <strong>Ne</strong></span></span>
            <span class="wo-flag-pill wo-flag-danger"><span class="wo-flag-dot"></span><span>Prijem završen: <strong>Ne</strong></span></span>
            <span class="wo-flag-pill wo-flag-secondary"><span class="wo-flag-dot"></span><span>SN transfer: <strong>-</strong></span></span>
          </div></div>
        </div>
        <hr class="invoice-spacing mb-0" />
        <div class="nav-align-top">
          <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item"><button type="button" class="nav-link active"><i class="fa fa-list me-50"></i> Sastavnica</button></li>
            <li class="nav-item"><button type="button" class="nav-link"><i class="fa fa-shield me-50"></i> Zaštita</button></li>
            <li class="nav-item"><button type="button" class="nav-link"><i class="fa fa-cube me-50"></i> Materijali</button></li>
            <li class="nav-item"><button type="button" class="nav-link"><i class="fa fa-cog me-50"></i> Operacija</button></li>
            <li class="nav-item"><button type="button" class="nav-link"><i class="fa fa-sticky-note-o me-50"></i> Napomena</button></li>
            <li class="nav-item"><button type="button" class="nav-link"><i class="fa fa-line-chart me-50"></i> KPI</button></li>
            <li class="nav-item"><button type="button" class="nav-link"><i class="fa fa-clock-o me-50"></i> Timeline</button></li>
            <li class="nav-item"><button type="button" class="nav-link"><i class="fa fa-link me-50"></i> Poveznice</button></li>
          </ul>
          <div class="tab-content">
            <div class="tab-pane fade show active" id="tab-sastavnica">
              <div class="table-responsive wo-sastavnica-table-wrap">
                <table class="table" id="sastavnica-table">
                  <thead><tr>
                    <th class="py-1 text-center"><span class="wo-desktop-column-label">Alternat...</span><span class="wo-mobile-column-label">alt.</span></th>
                    <th class="py-1 text-center"><span class="wo-desktop-column-label">Pozicija</span><span class="wo-mobile-column-label">poz</span></th>
                    <th class="py-1 text-center">Artikal</th><th class="py-1 text-center">Opis</th><th class="py-1 text-center">Slika</th>
                    <th class="py-1 text-center">Napo...</th><th class="py-1 text-center">Količina</th><th class="py-1 text-center">MJ</th>
                    <th class="py-1 text-center">Serija</th><th class="py-1 text-center">nor.os.</th><th class="py-1 text-center">Aktivno</th>
                    <th class="py-1 text-center">Završ...</th><th class="py-1 text-center">VA</th><th class="py-1 text-center">Prim.klas</th><th class="py-1 text-center">Sek.klas</th>
                    <th class="py-1 text-center wo-sastavnica-action-col"><span class="wo-desktop-column-label">Akcija</span><button type="button" class="wo-mobile-column-label wo-sastavnica-action-toggle" aria-expanded="true"><span class="wo-action-expanded-label">AKCIJA</span><span class="wo-action-collapsed-label"><i class="fa fa-angle-left"></i></span></button></th>
                  </tr></thead>
                  <tbody>
                    <tr><td class="py-1">1</td><td class="py-1">10</td><td class="py-1">LIM-S235-5</td><td class="py-1">Lim S235 5mm</td><td class="py-1 text-center"><span class="text-muted">-</span></td><td class="py-1" title="">-</td><td class="py-1">2,4</td><td class="py-1">KG</td><td class="py-1">1</td><td class="py-1">-</td><td class="py-1">D</td><td class="py-1">N</td><td class="py-1">M</td><td class="py-1">-</td><td class="py-1">-</td>
                      <td class="py-1 text-center wo-sastavnica-action-col"><div class="d-inline-flex align-items-center gap-50"><button type="button" class="btn btn-sm btn-outline-primary wo-edit-sastavnica-btn" title="Uredi stavku"><i class="fa fa-pencil"></i></button><button type="button" class="btn btn-sm btn-outline-danger wo-remove-sastavnica-btn" title="Ukloni iz radnog naloga"><i class="fa fa-trash"></i></button></div></td></tr>
                  </tbody>
                </table>
              </div>
            </div>
            <!-- Operacija tab (role kontrola/bravarija or admin gets the ✓ column) -->
            <div class="tab-pane fade" id="tab-operacija">
              <div class="table-responsive wo-sastavnica-table-wrap">
                <table class="table" id="operacija-table">
                  <thead><tr>
                    <th class="py-1 text-center"><span class="wo-desktop-column-label">Alternativa</span><span class="wo-mobile-column-label">Alt.</span></th>
                    <th class="py-1 text-center"><span class="wo-desktop-column-label">Pozicija</span><span class="wo-mobile-column-label">Pos.</span></th>
                    <th class="py-1 text-center">Operacija</th><th class="py-1 text-center">Naziv</th><th class="py-1 text-center">Napo...</th><th class="py-1 text-center">MJ</th><th class="py-1 text-center">MJ/vrij.</th><th class="py-1 text-center">nor.os.</th><th class="py-1 text-center">VA</th><th class="py-1 text-center">Prim.klas.</th><th class="py-1 text-center">Sek.klas.</th>
                    <th class="py-1 text-center wo-operation-action-col">&#10003;</th>
                  </tr></thead>
                  <tbody>
                    <tr class="wo-operation-finished-row"><td class="py-1">1</td><td class="py-1">20</td><td class="py-1">OP10</td><td class="py-1">Laserko rezanje</td><td class="py-1">-</td><td class="py-1">MIN</td><td class="py-1">4</td><td class="py-1">-</td><td class="py-1">O</td><td class="py-1">-</td><td class="py-1">-</td><td class="py-1 text-center wo-operation-action-col"><button type="button" class="btn btn-sm btn-flat-success wo-operation-complete-btn" data-finished="1"></button></td></tr>
                    <tr><td class="py-1">1</td><td class="py-1">30</td><td class="py-1">OP20</td><td class="py-1">CNC savijanje</td><td class="py-1">-</td><td class="py-1">MIN</td><td class="py-1">6</td><td class="py-1">-</td><td class="py-1">O</td><td class="py-1">-</td><td class="py-1">-</td><td class="py-1 text-center wo-operation-action-col"><button type="button" class="btn btn-sm btn-flat-secondary wo-operation-complete-btn" data-finished="0"></button></td></tr>
                  </tbody>
                </table>
              </div>
            </div>
            <!-- Materijali tab headers: Pozicija | Materijal | Naziv | Količina (input.form-control.form-control-sm.wo-material-quantity-input + btn btn-outline-primary btn-sm wo-material-save-quantity-btn <i class="fa fa-check">) | Napomena -->
          </div>
        </div>
      </div>
    </div>

    <!-- action column (below main card on phones; primary pair hidden, collapse shown) -->
    <div class="col-xl-3 col-md-4 col-12 invoice-actions mt-md-0 mt-2 wo-preview-actions-col">
      <div class="card"><div class="card-body">
        <div class="wo-other-options-shell">
          <button class="btn btn-outline-secondary w-100 mb-75 d-flex justify-content-center align-items-center wo-other-options-toggle collapsed" type="button" aria-expanded="false"><i class="fa fa-chevron-down me-50 wo-other-options-chevron" style="font-size: 12px;"></i> Ostale opcije</button>
          <div class="collapse wo-other-options-collapse" id="wo-other-options-collapse">
            <button id="wo-close-order-btn" class="btn btn-outline-success w-100 mb-75 d-flex justify-content-center align-items-center"><i class="fa fa-check-circle me-50"></i><span class="wo-close-order-label">Zatvori nalog</span></button>
            <button id="wo-status-trigger-btn" class="btn w-100 mb-75 d-flex justify-content-center align-items-center wo-side-meta-btn wo-side-meta-btn-success"><i class="fa fa-circle-notch me-50"></i> Status: <span id="wo-status-label" class="ms-25">Otvoren</span></button>
            <button id="wo-priority-trigger-btn" class="btn w-100 mb-75 d-flex justify-content-center align-items-center wo-side-meta-btn wo-side-meta-btn-warning"><span id="wo-priority-label">5 - Uobičajeni prioritet</span></button>
            <button id="wo-protection-trigger-btn" class="btn w-100 mb-75 d-flex justify-content-center align-items-center wo-side-meta-btn wo-side-meta-btn-secondary"><i class="fa fa-shield me-50" style="margin-top: 1px;"></i> Zaštita: <span class="ms-25">PLASTIFIKACIJA RAL 7016</span></button>
            <button class="btn btn-outline-secondary w-100 mb-75 d-flex justify-content-center align-items-center"><i class="fa fa-building me-50"></i> Dodaj odjel</button>
            <button class="btn btn-outline-primary w-100 mb-75 d-flex justify-content-center align-items-center"><i class="fa fa-cube me-50"></i> Dodaj materijal</button>
            <button class="btn btn-outline-secondary w-100 mb-75 d-flex justify-content-center align-items-center"><i class="fa fa-cog me-50"></i> Dodaj operaciju</button>
            <button class="btn btn-outline-secondary w-100 mb-75 d-flex justify-content-center align-items-center"><i class="fa fa-paper-plane me-50" style="margin-top: 2px; font-size: 12px;"></i> Pošalji</button>
            <a class="btn btn-outline-secondary w-100 d-flex justify-content-center align-items-center"><i class="fa fa-print me-50"></i> Isprintaj</a>
          </div>
        </div>
      </div></div>
    </div>
  </div>
</section>
```

**Other tab contents:**
- **KPI:** the `wo-kpi-item` labels are `Planirana količina`, `Izrađena količina`, `Serija`, `Popravka`, `Plan otpad`, `Otpad`, `Plan škart`, `Škart`, `Vrijeme rada` (h), `Vrijeme protoka` (h), `Stavke`, `Materijali`, `Operacije`.
- **Timeline:** `Datum naloga`, `Planirani start`, `Planirani kraj`, `Završetak WO`, `Datum veze`, `Vrijeme unosa`, `Vrijeme izmjene`.
- **Poveznice:** `wo-link-card wo-link-tone-*` cards labelled `RN ključ`, `Broj narudžbe`, `Vezni dokument`, `QID` and others.

**Close work order modal** (`#close-work-order-modal`, `modal-xl modal-dialog-scrollable modal-dialog-centered`):
- **Header:** title `Zatvori nalog 26-6000-0001687`, with the small muted line `Vrijeme se unosi u minutama za jednu proizvedenu jedinicu.`
- **Tabs:** `Operacije` | `Materijali` | `Prijem`.
- **Operations table** (`table align-middle wo-close-table`):
  - Columns: `Pozicija`, `Operacija` (input placeholder `npr. OP30`), `Naziv`, `Radnik` (placeholder `Upišite ime radnika`), `Početak izrade` and `Kraj izrade` (HH : MM inputs), `Trajanje (min)` (`Ukupno min.`), `Trajanje (min/jed)` (`Min./jed.`), `Zastoj (min)` (`Opcionalno`), `Akcije`.
  - `Akcije` is a sticky column holding three buttons: `fa-copy`, `fa-eraser` and `fa-trash`.
  - The footer row shows `Ukupno` with 0 / 0 / 0 totals and a 2px #7367f0 top border.
  - OP50, OP60 and any row whose name contains "bravar" or "kontrol" are left out of this table.
- **Materials table:** `Pozicija | Materijal | Naziv | Količina | MJ | Skladište ("Skladište sirovina") | Zaliha | Akcije` (`fa-plus`, `fa-eraser`, `fa-trash`).
- **Prijem tab:**
  - Text: `Rasporedite proizvedenu količinu između veleprodajnog skladišta i skladišta škarta. Ukupan prijem mora biti jednak količini radnog naloga.`
  - Table columns `Odredište | Artikal | Naziv | Količina | MJ`, with one row `Veleprodajno skladište`.
  - Button `+ Dodaj prijem škarta`.
- **Footer:** `Odustani` (btn-outline-secondary) and `Zatvori nalog` (btn-success, `fa-check-circle`).

**QR scanner modal** (`#qr-scanner-modal`, from `nalog-scan.blade.php`; the backdrop is near-black `rgba(0,0,0,.992)`):
```html
<div class="modal fade" id="qr-scanner-modal"><div class="modal-dialog modal-dialog-centered"><div class="modal-content" style="background: transparent; border: none;">
 <div class="modal-body p-0 text-center wo-qr-modal-body">
  <div class="wo-qr-modal-copy">
   <h4 class="text-white mb-2 wo-qr-modal-title">Skeniraj QR code radnog naloga</h4>
   <div class="qr-scanner-container position-relative" style="max-width: 400px; margin: 0 auto;">
    <div id="qr-scanner-frame" class="qr-scanner-frame position-relative" style="width: 100%; padding-top: 100%; background: rgba(255, 255, 255, 0.1); border: 2px solid var(--bs-success, #28c76f); border-radius: 12px; overflow: hidden;">
     <div id="qr-scanner-region" class="position-absolute" style="inset: 0;"></div>
     <div class="qr-corner qr-corner-top-left" style="position: absolute; top: 0; left: 0; width: 40px; height: 40px; border-top: 3px solid var(--bs-success, #28c76f); border-left: 3px solid var(--bs-success, #28c76f);"></div>
     <div class="qr-corner qr-corner-top-right" style="position: absolute; top: 0; right: 0; width: 40px; height: 40px; border-top: 3px solid var(--bs-success, #28c76f); border-right: 3px solid var(--bs-success, #28c76f);"></div>
     <div class="qr-corner qr-corner-bottom-left" style="position: absolute; bottom: 0; left: 0; width: 40px; height: 40px; border-bottom: 3px solid var(--bs-success, #28c76f); border-left: 3px solid var(--bs-success, #28c76f);"></div>
     <div class="qr-corner qr-corner-bottom-right" style="position: absolute; bottom: 0; right: 0; width: 40px; height: 40px; border-bottom: 3px solid var(--bs-success, #28c76f); border-right: 3px solid var(--bs-success, #28c76f);"></div>
     <div class="qr-scan-line" style="position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, transparent, var(--bs-success, #28c76f), transparent); animation: scanLineNalog 2s linear infinite;"></div>
     <div class="qr-grid" style="position: absolute; inset: 0; background-image: linear-gradient(rgba(40, 199, 111, 0.1) 1px, transparent 1px), linear-gradient(90deg, rgba(40, 199, 111, 0.1) 1px, transparent 1px); background-size: 20px 20px; opacity: 0.3;"></div>
    </div>
   </div>
   <div class="wo-qr-controls-wrap" style="max-width: 400px; margin: 0 auto;">
    <div class="wo-qr-controls-panel mt-2 mb-1">
     <div class="wo-qr-controls-row wo-qr-display-row">
      <div class="wo-qr-control-block"><span class="wo-qr-control-kicker">Prikaz</span>
       <div class="form-check form-switch mb-0 d-flex align-items-center"><input class="form-check-input me-50" type="checkbox" id="qr-mirror-toggle"><label class="form-check-label mb-0" for="qr-mirror-toggle">Mirror</label></div></div>
      <button type="button" class="btn btn-sm wo-qr-btn wo-qr-btn-subtle"><i class="fa fa-refresh me-50"></i> Ponovo pokreni</button>
     </div>
     <div class="wo-qr-controls-row wo-qr-camera-controls-row">
      <span class="wo-qr-camera-icon"><i class="fa fa-camera"></i></span>
      <div class="wo-qr-camera-row"><select class="form-select form-select-sm"><option>Automatski odabir</option></select>
       <button type="button" class="btn btn-sm wo-qr-btn wo-qr-btn-primary">Primijeni</button></div>
     </div>
    </div>
    <div class="wo-qr-feedback-wrap"><div id="qr-scanner-status" class="small wo-qr-status">Usmjeri kameru prema QR kodu radnog naloga.</div></div>
   </div>
  </div>
  <button type="button" class="btn btn-secondary wo-scanner-close-fab"><i class="fa fa-times me-50"></i> Zatvori</button>
 </div>
</div></div></div>
```

- **Status texts in order:** `Dozvoli pristup kameri da skeniranje zapocne.` → `Pokrećem kameru...` → `Usmjeri kameru prema QR kodu radnog naloga.` → `QR prepoznat. Provjeravam radni nalog i narudžbu...` → `Radni nalog je spreman. Otvaram...` or `Otvaram operacije...`.
- **SweetAlert titles:** `RN pronađen`, `RN nije pronađen`, `Skeniranje nije uspjelo`.

**CSS to copy verbatim.** The full style block is lines 11–2021 of `app-invoice-preview.blade.php`; everything from about line 1163 onward is mostly dark-layout overrides you can skip. Copy these line ranges as-is:
- **Lines 15–147:** header, title, meta buttons and the "other options" toggle.
- **Lines 323–1162:** chips, header shell, QR image, `wo-mobile-priority`, progress, product hero, chip shell, KPI, links, flags, and the operation checkbox `::after` rules at lines 603–657.
- **Lines 1589–2020:** the borderless-wrapper rule, all media queries, and the **@media (max-width:480px)** block at lines 1692–2004, which drives the phone look.
- **nalog-scan modal:** its CSS is lines 76–410 plus the `@media (max-width:480px)` block at lines 966–1051 of `nalog-scan.blade.php`.

The rules that matter most for the look are reproduced here verbatim:
```css
.wo-product-hero{border:1px solid #ebe9f1;border-radius:10px;background:linear-gradient(180deg,rgba(115,103,240,0.08) 0%,rgba(255,255,255,1) 100%);padding:0.85rem 1rem;margin-bottom:0.85rem;}
.wo-product-code-accent{margin-top:0.55rem;display:inline-flex;align-items:center;gap:0.45rem;border-radius:9px;padding:0.36rem 0.72rem 0.36rem 0.56rem;background:linear-gradient(90deg,rgba(40,199,111,0.2) 0%,rgba(40,199,111,0.06) 68%,rgba(40,199,111,0) 100%);box-shadow:inset 0 0 0 1px rgba(40,199,111,0.34);}
.wo-product-code-accent::before{content:'';width:0.45rem;height:0.45rem;border-radius:999px;background:#28c76f;box-shadow:0 0 0 4px rgba(40,199,111,0.2);flex:0 0 auto;}
.wo-progress{height:6px;width:100%;border-radius:999px;background-color:#f1f1f5;overflow:hidden;position:relative;}
.wo-progress-shell .wo-progress{height:9px;background-color:#e9edf3;}
.wo-progress-bar{height:100%;width:0;border-radius:999px;background:linear-gradient(90deg,#00cfe8 0%,#28c76f 100%);transition:width 1.25s cubic-bezier(0.22,1,0.36,1),filter 0.2s ease;position:relative;}
.invoice-preview-wrapper .wo-operation-complete-btn::after{content:'\2713';width:22px;height:22px;display:inline-grid;place-items:center;border:2px solid #b9b7c1;border-radius:.25rem;color:transparent;font-size:.9rem;font-weight:800;line-height:1;}
.invoice-preview-wrapper .wo-operation-complete-btn[data-finished="1"]::after{color:#fff;background:#28c76f;border-color:#28c76f;}
.invoice-preview-wrapper #operacija-table tbody tr.wo-operation-finished-row > td{background-color:rgba(40,199,111,.08);color:#6e6b7b;}
@media (max-width:480px){
 .wo-mobile-top-actions{display:block;position:fixed;left:max(0.75rem,env(safe-area-inset-left));right:max(0.75rem,env(safe-area-inset-right));bottom:max(0.75rem,env(safe-area-inset-bottom));z-index:1035;margin-bottom:0;}
 .wo-mobile-top-actions .card{margin:0;border:1px solid rgba(113,130,163,0.18);background:rgba(255,255,255,0.94);box-shadow:0 12px 28px rgba(34,41,47,0.16);backdrop-filter:blur(10px);}
 .wo-mobile-top-actions .card-body{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0.55rem;padding:0.55rem;}
 .wo-mobile-top-actions .btn{width:100%;height:44px;margin-bottom:0 !important;padding:0.5rem 0.3rem;font-size:0.92rem;font-weight:600;line-height:1.12;white-space:nowrap;}
 .invoice-preview-wrapper .logo-wrapper .wo-brand-logo{width:62px;height:62px;...}
 .invoice-preview-wrapper .logo-wrapper .invoice-logo{margin-left:0.82rem;font-size:1.62rem;line-height:1.02;white-space:nowrap;}
 .wo-preview-qr-image{width:96px;height:96px;padding:6px;}
 /* ...rest in lines 1692–2004 */
}
```

---

### B. Scan operations (`app-invoice-scan-operations.blade.php`, title "Operacije radnog naloga")

**At 390px:** a single full-width card with a centred RN number in the header, then large tappable rows. Each row has a 42px position square, the operation code in small uppercase, the name in bold 1.05rem, and a checkbox square on the right.
- **Enabled rows** are green-tinted and their position square is green (`#65dba0`).
- **Finished rows** are faded with a green checked box.
- **Rows for other roles** are grey and disabled.

The fixed footer has two buttons: "Skeniraj radni nalog" (success) and "Detalji RN" (outline-primary).

```html
<style>
  .scan-operations-page { min-height: calc(100vh - 14rem); min-height: calc(100svh - 14rem); padding: .25rem 0 calc(4rem + env(safe-area-inset-bottom, 0px)); }
  .scan-operations-shell { width: 100%; max-width: 900px; }
  .scan-operations-card { overflow: hidden; border-radius: .428rem; }
  .scan-operations-card-header { display: flex; justify-content: center; align-items: center; padding: 1rem 1.2rem; border-bottom: 1px solid rgba(34,41,47,.08); }
  .scan-operations-card-header strong { font-size: 1.05rem; text-align: center; }
  .scan-operations-list { padding: .75rem; display: grid; gap: .65rem; }
  .scan-operation-row { width: 100%; min-height: 78px; display: grid; grid-template-columns: 48px minmax(0,1fr) 28px; align-items: center; gap: 1rem; padding: .85rem 1rem; border-radius: 10px; text-align: left; border: 1px solid transparent; transition: .18s ease; font: inherit; -webkit-appearance: none; appearance: none; -webkit-tap-highlight-color: transparent; touch-action: manipulation; }
  .scan-operation-position { width: 42px; height: 42px; display: grid; place-items: center; border-radius: 9px; font-weight: 800; }
  .scan-operation-copy { min-width: 0; display: flex; flex-direction: column; gap: .25rem; }
  .scan-operation-code { font-size: .76rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
  .scan-operation-name { font-size: 1.05rem; font-weight: 700; }
  .scan-operation-finished-check { width: 22px; height: 22px; display: inline-grid; place-items: center; border: 2px solid #b9b7c1; border-radius: .25rem; color: transparent; }
  .scan-operation-finished-check::after { content: '✓'; font-size: .9rem; font-weight: 800; line-height: 1; }
  .scan-operation-finished-check.is-checked { color: #fff; background: #28c76f; border-color: #28c76f; }
  .scan-operation-row.is-disabled { color: #9a97a5; background: #f8f8f8; border-color: #ebe9f1; cursor: not-allowed; opacity: .64; }
  .scan-operation-row.is-disabled .scan-operation-position { background: #ebe9f1; }
  .scan-operation-row.is-enabled { color: #3b4253; background: rgba(40,199,111,.08); border-color: rgba(40,199,111,.42); cursor: pointer; box-shadow: 0 4px 15px rgba(40,199,111,.06); }
  .scan-operation-row.is-enabled .scan-operation-position { color: #111c17; background: #65dba0; }
  .scan-operation-row.is-enabled .scan-operation-code { color: #65dba0; }
  .scan-operation-row.is-enabled:hover { transform: translateY(-2px); border-color: #65dba0; box-shadow: 0 12px 30px rgba(40,199,111,.15); }
  .scan-operation-row.is-finished { color: #6e6b7b; background: rgba(40,199,111,.08); border-color: rgba(40,199,111,.2); opacity: .72; cursor: default; }
  .scan-operation-row.is-finished .scan-operation-position { color: #16824a; background: rgba(40,199,111,.18); }
  .scan-operation-row.is-saving { opacity: .7; cursor: wait; }
  .scan-operation-row.is-complete { background: rgba(40,199,111,.27); }
  .scan-operations-empty { padding: 3rem 1rem; text-align: center; color: #8790a6; }
  .scan-operations-empty i { font-size: 2rem; margin-bottom: .75rem; }
  @media (max-width: 575.98px) {
    .scan-operations-page { padding: .75rem 0 calc(4.5rem + env(safe-area-inset-bottom, 0px)); }
    .scan-operation-row { grid-template-columns: 42px minmax(0,1fr) 26px; gap: .7rem; }
  }
</style>
<div class="scan-operations-page"><div class="scan-operations-shell">
  <div class="card scan-operations-card mb-0">
    <div class="scan-operations-card-header"><strong>26-6000-0001687</strong></div>
    <div class="scan-operations-list" role="list">
      <button type="button" class="scan-operation-row is-finished" disabled role="listitem"><span class="scan-operation-position">10</span><span class="scan-operation-copy"><span class="scan-operation-code">OP10</span><span class="scan-operation-name">Lasersko rezanje</span></span><span class="scan-operation-finished-check is-checked" aria-label="Operacija završena"></span></button>
      <button type="button" class="scan-operation-row is-disabled" disabled role="listitem"><span class="scan-operation-position">20</span><span class="scan-operation-copy"><span class="scan-operation-code">OP20</span><span class="scan-operation-name">CNC savijanje</span></span><span class="scan-operation-finished-check" aria-label="Operacija nije završena"></span></button>
      <button type="button" class="scan-operation-row is-enabled" role="listitem"><span class="scan-operation-position">50</span><span class="scan-operation-copy"><span class="scan-operation-code">OP50</span><span class="scan-operation-name">Bravarija</span></span><span class="scan-operation-finished-check" aria-label="Operacija nije završena"></span></button>
      <button type="button" class="scan-operation-row is-disabled" disabled role="listitem"><span class="scan-operation-position">60</span><span class="scan-operation-copy"><span class="scan-operation-code">OP60</span><span class="scan-operation-name">Kontrola</span></span><span class="scan-operation-finished-check" aria-label="Operacija nije završena"></span></button>
    </div>
  </div>
</div></div>
<!-- fixed footer -->
<footer class="footer footer-light screen-actions-footer"><div class="screen-footer-actions">
  <button class="btn btn-success d-flex justify-content-center align-items-center"><i class="fa fa-qrcode me-50"></i> Skeniraj radni nalog</button>
  <a class="btn btn-outline-primary">Detalji RN</a>
</div></footer>
```

- **Confirm dialog** (Swal, `icon: 'question'`): title `Potvrdi kontrolnu tačku`, text `Želite li označiti operaciju "Bravarija"?`, buttons `Da, označi` and `Odustani`.
- **Success:** `Evidentirano` / `Kontrolna tačka je sačuvana.` / `U redu`.
- **Empty state:** `<i class="fa fa-list-alt">` with `Nema operacija`.

---

### C. Raw material scan (`new-components/sirovina-scan.blade.php`, a full-screen dark modal `#sirovina-scanner-modal`)

This is a modal over a black backdrop (`rgba(0,0,0,.95)`) with a transparent modal-content.

**At ≤480px:**
- **Top buttons:** two fixed buttons in a 2-column grid: "Zatvori" (dark glass) and "Nastavite" (green, disabled until something is selected).
- **Title:** centred, 1.15rem.
- **Viewfinder:** square (aspect-ratio 1/1, max 320px), with a teal inner window `rgba(92,225,194,.95)` and a pill label.
- **Controls panel:** Mirror switch, restart, camera select with Apply, and a zoom slider with a light button.
- **Status text:** hidden on phones.
- **Basic `user` role:** `data-compact-layout="1"` hides the two table columns and the "Nastavite" button, so workers see only the scanner. Admins on phones see three stacked columns.
- **Mode switcher:** the bottom pill switcher is hidden below 992px.

```html
<div class="modal fade" id="sirovina-scanner-modal" data-compact-layout="0"><div class="modal-dialog modal-dialog-centered modal-xl mt-0"><div class="modal-content wo-bom-modal-content">
 <div class="modal-header wo-bom-modal-header wo-bom-content-header"><div class="w-100 text-center">
  <h4 class="mb-0 text-white">Planiraj novu potrošnju za RN <span id="sirovina-rn-number">26-6000-0001687</span></h4>
  <p class="mb-0 wo-bom-modal-subtitle">Izaberite materijal i operacije za privremenu sastavnicu</p>
 </div></div>
 <div class="modal-body p-0"><div class="wo-bom-modal-shell"><div class="row g-4 align-items-stretch">
  <div class="col-12 col-lg-4 wo-bom-scanner-col"><div class="wo-bom-card wo-bom-dummy-qr-card">
   <h5 class="text-white mb-1">Skenirajte BARCODE ili QR kod artikla</h5>
   <div class="qr-scanner-container position-relative wo-bom-dummy-qr-wrap">
    <div id="sirovina-qr-scanner-frame" class="qr-scanner-frame position-relative">
     <div id="sirovina-qr-scanner-region" class="position-absolute" style="inset: 0;"></div>
     <div class="qr-corner qr-corner-top-left"></div><div class="qr-corner qr-corner-top-right"></div>
     <div class="qr-corner qr-corner-bottom-left"></div><div class="qr-corner qr-corner-bottom-right"></div>
     <div class="qr-barcode-window"></div>
     <div class="qr-barcode-window-label">Barcode ili QR kod može biti bilo gdje u okviru</div>
     <div class="qr-scan-line"></div><div class="qr-grid"></div>
    </div>
   </div>
   <div class="wo-qr-controls-wrap wo-bom-dummy-qr-controls">
    <div class="wo-qr-controls-panel mt-2 mb-1">
     <div class="wo-qr-controls-row wo-qr-display-row">
      <div class="wo-qr-control-block"><span class="wo-qr-control-kicker">Prikaz</span><div class="form-check form-switch mb-0 d-flex align-items-center"><input class="form-check-input me-50" type="checkbox"><label class="form-check-label mb-0">Mirror</label></div></div>
      <button type="button" class="btn btn-sm wo-qr-btn wo-qr-btn-subtle"><i class="fa fa-refresh me-50"></i> Ponovo pokrenite</button>
     </div>
     <div class="wo-qr-controls-row wo-qr-camera-controls-row">
      <span class="wo-qr-camera-icon"><i class="fa fa-camera"></i></span>
      <div class="wo-qr-camera-row"><select class="form-select form-select-sm"><option>Automatski odabir</option></select><button type="button" class="btn btn-sm wo-qr-btn wo-qr-btn-primary">Primijenite</button></div>
     </div>
     <div class="wo-qr-controls-row wo-qr-enhance-row">
      <div class="wo-qr-zoom-control"><label class="wo-qr-control-kicker mb-0">Zoom <span>1.0x</span></label><input type="range" class="form-range wo-qr-range" min="1" max="1" step="0.1" value="1" disabled></div>
      <button type="button" class="btn btn-sm wo-qr-btn wo-qr-btn-subtle" disabled><i class="fa fa-lightbulb-o me-50"></i> Uključite svjetlo</button>
     </div>
    </div>
    <div class="wo-qr-feedback-wrap"><div class="small wo-qr-status">Dozvoli pristup kameri za barcode / QR skeniranje.</div></div>
   </div>
  </div></div>

  <div class="col-12 col-lg-4 d-flex wo-bom-ipad-hidden-col"><div class="wo-bom-card wo-bom-quick-card h-100 w-100 d-flex flex-column">
   <div class="wo-bom-field wo-bom-quick-last wo-bom-quick-persistent d-flex flex-column flex-grow-1">
    <div class="wo-bom-quick-head"><label class="form-label wo-bom-section-title mb-50">Privremena sastavnica</label><span class="wo-bom-quick-selected">Odabrano: <strong>2</strong></span></div>
    <div class="table-responsive wo-bom-quick-table-wrap"><table class="table table-sm mb-0 wo-bom-table wo-bom-quick-table">
     <thead><tr><th style="width: 80px;">Poz</th><th style="width: 200px;">Komponenta</th><th>Opis</th><th style="width: 120px;" class="text-end">Zaliha</th><th style="width: 120px;" class="text-center">Tip</th></tr></thead>
     <tbody>
      <tr><td>10</td><td class="fw-semibold">LIM-S235-5</td><td class="wo-opis-cell"><span class="wo-opis-two-line is-single"><span>Lim S235 5mm</span></span></td><td class="text-end">1.250</td><td class="text-center">M</td></tr>
      <tr class="wo-bom-quick-operation-row"><td>20</td><td class="fw-semibold">OP10</td><td class="wo-opis-cell"><span class="wo-opis-two-line is-single"><span>Lasersko rezanje</span></span></td><td class="text-end">-</td><td class="text-center">O</td></tr>
     </tbody>
    </table></div>
   </div>
  </div></div>

  <div class="col-12 col-lg-4 wo-bom-right-col d-flex"><div class="wo-bom-card wo-bom-main-card h-100 w-100 d-flex flex-column">
   <div class="wo-bom-mode-panel wo-panel-active">
    <div class="wo-bom-head-block"><div class="wo-bom-grid"><div class="wo-bom-field"><label class="form-label wo-bom-section-title mb-50">Proizvod</label><select class="form-select form-select-sm"><option>Izaberite proizvod</option></select></div></div>
     <p class="small mb-0 text-white-50">Sastavnica se automatski učitava nakon odabira proizvoda.</p></div>
    <div class="wo-bom-table-section">
     <div class="wo-bom-table-head mt-1"><h6 class="wo-bom-section-title mb-0">Sastavnice proizvoda</h6><button type="button" class="btn btn-sm wo-bom-inline-action-btn wo-bom-inline-action-btn-danger" disabled>Resetuj</button><span class="wo-bom-table-found">Pronađeno: <strong>4</strong></span></div>
     <div class="table-responsive wo-bom-table-wrap"><table class="table table-sm mb-0 wo-bom-table">
      <thead><tr><th class="text-center" style="width: 46px;">#</th><th style="width: 80px;">Poz</th><th style="width: 200px;">Komponenta</th><th>Opis</th><th style="width: 130px;" class="text-end">Planirano</th><th style="width: 120px;" class="text-center">Tip</th></tr></thead>
      <tbody><tr><td class="text-center"><input type="checkbox" class="form-check-input bom-component-checkbox" checked></td><td>10</td><td class="fw-semibold">LIM-S235-5</td><td class="wo-opis-cell"><span class="wo-opis-two-line is-single"><span>Lim S235 5mm</span></span></td><td class="text-end">2.4</td><td class="text-center">M</td></tr></tbody>
     </table></div>
    </div>
   </div>
  </div>
  <div class="wo-bom-bottom-mode-switch"><button type="button" class="wo-bom-bottom-mode-btn is-active">Pretraga po proizvodu</button><button type="button" class="wo-bom-bottom-mode-btn">Svi materijali</button><button type="button" class="wo-bom-bottom-mode-btn">Sve operacije</button></div>
  </div>
 </div>
 <div class="wo-bom-modal-footer">
  <button type="button" class="btn btn-secondary wo-scanner-close-fab"><i class="fa fa-times me-50"></i> Zatvori</button>
  <button type="button" class="btn btn-success wo-scanner-open-fab"><i class="fa fa-check me-50"></i> Nastavite</button>
 </div>
</div></div></div></div></div>
```

- **"Tip" column:** it shows `acOperationType` exactly as stored. The "M"/"O" values above are plausible, not confirmed.
- **Planned-consumption step:** "Nastavite" opens `#fine-adjust-bom-modal` (`new-components/fine-adjust-bom.blade.php`, `modal-fullscreen`, dark gradient `#0a1020 → #091421`).
  - Header: `Ručno prilagođavanje sastavnice` with the subtitle `Ovaj prikaz dopušta administratoru ručno prilagođavanje svih stavki unutar nove privremene sastavnice prije dodavanje iste na radni nalog`.
  - Badge `Stavki: 0` (bg-primary).
  - Columns: `Alternativno | Pozicija | Artikal | Opis | Slika | Visina | Širina | Debljina | Napomena | Planirano | Zaliha | MJ | Akcija`.
  - Footer: `Odustani` (btn-danger, fa-times) and `Potvrdi i dodaj na RN` (btn-success, fa-check).

**CSS to copy verbatim:**
- Lines 300–1590 of `sirovina-scan.blade.php`. The key rules are at 316–600 (shell, section title colour `#bad0ff`, table head `rgba(23,30,48,.96)`), 616–745 (window, corners, scan line, grid), 948–1155 (tables, footer fab, bottom switch), and the ≤480px block at 1426–1589.
- Lines 59–260 of `fine-adjust-bom.blade.php` for the next step.

---

### D. Stock overview (`apps/materials/app-material.blade.php`, title "Pregled zaliha")

This is a server-side DataTable built by `resources/js/scripts/pages/app-material.js`, with no custom `dom` (so the default Bootstrap 5 layout).

**At 390px:** an H2 title, then a full-width warehouse select. Admins also get a "Dodaj novi materijal" button there. Then comes the card:
- The DataTables length control is centred.
- The two bulk buttons drop into a toolbar row, stacked at full width: blue barcode and amber QR.
- Next is `Pretraga:`, then a horizontally scrolling table (min-width 760/860/1140px) with a sticky white "Akcija" column on the right.

```html
<section class="material-barcode-generator-wrapper">
  <div class="content-header row">
    <div class="content-header-left col-md-3 col-lg-6 col-12 mb-2"><div class="row breadcrumbs-top"><div class="col-12"><h2 class="content-header-title float-start mb-0">Pregled zaliha</h2></div></div></div>
    <div class="content-header-right text-md-end col-md-9 col-lg-6 col-12"><div class="mb-1 breadcrumb-right"><div class="material-header-actions">
      <div class="material-warehouse-filter"><select class="form-select" id="material-warehouse-filter"><option value="">Sva skladišta</option><option>Skladište sirovina</option></select></div>
      <button type="button" class="btn btn-primary" id="material-create-open-btn"><i data-feather="plus" class="me-50"></i> Dodaj novi materijal</button>
    </div></div></div>
  </div>
  <div class="card">
    <div class="card-datatable table-responsive">
      <div class="dataTables_wrapper dt-bootstrap5">
        <div class="row">
          <div class="col-sm-12 col-md-6"><div class="material-table-inline-controls"><div class="dataTables_length"><label>Prikaži <select class="form-select form-select-sm"><option>25</option></select> materijala</label></div></div></div>
          <div class="col-sm-12 col-md-6"><div class="dataTables_filter"><label>Pretraga:<input type="search" class="form-control form-control-sm"></label></div></div>
        </div>
        <div class="row material-bulk-download-toolbar-row"><div class="col-12"><div class="material-bulk-download-toolbar">
          <button type="button" class="btn material-action-btn material-barcode-preview-btn material-bulk-download-btn"><i class="fa fa-download me-50"></i> Preuzmi sve etikete</button>
          <button type="button" class="btn material-action-btn material-qr-preview-btn material-bulk-download-btn"><i class="fa fa-qrcode me-50"></i> Preuzmi sve QR etikete</button>
        </div></div></div>
        <div class="row"><div class="col-sm-12">
          <table class="table material-barcode-table" id="material-barcode-table">
            <thead><tr><th>Šifra</th><th>Naziv</th><th>MJ</th><th>Skladište</th><th>Zaliha</th><th class="material-actions-cell">Akcija</th></tr></thead>
            <tbody>
              <tr><td class="material-code-cell">LIM-S235-5</td><td>Lim S235 5mm 1500x3000</td><td class="material-unit-cell">KG</td><td class="material-warehouse-cell">Skladište sirovina</td><td class="text-end material-stock-cell">1.250,5</td>
                <td class="text-end material-actions-cell"><div class="material-actions-group">
                  <button type="button" class="btn btn-sm app-table-action-btn app-table-action-btn--primary material-action-btn material-barcode-preview-btn" title="Barcode"><i class="fa fa-barcode"></i></button>
                  <button type="button" class="btn btn-sm app-table-action-btn app-table-action-btn--warning material-action-btn material-qr-preview-btn" title="QR code"><i class="fa fa-qrcode"></i></button>
                  <button type="button" class="btn btn-sm app-table-action-btn app-table-action-btn--success material-action-btn material-stock-adjust-btn" title="Korekcija zalihe"><i class="fa fa-database"></i></button>
                  <button type="button" class="btn btn-sm app-table-action-btn app-table-action-btn--accent material-action-btn material-copy-btn" title="Kopiraj materijal"><i class="fa fa-copy"></i></button>
                  <button type="button" class="btn btn-sm app-table-action-btn app-table-action-btn--danger material-action-btn material-delete-btn" title="Izbriši materijal"><i class="fa fa-trash"></i></button>
                </div></td></tr>
            </tbody>
          </table>
        </div></div>
        <div class="row"><div class="col-sm-12 col-md-5"><div class="dataTables_info">Prikaz 1 do 25 od 1.482 materijala</div></div><div class="col-sm-12 col-md-7"><div class="dataTables_paginate paging_simple_numbers"><ul class="pagination"><li class="paginate_button page-item previous disabled"><a class="page-link">Prethodna</a></li><li class="paginate_button page-item active"><a class="page-link">1</a></li><li class="paginate_button page-item"><a class="page-link">2</a></li><li class="paginate_button page-item next"><a class="page-link">Sljedeća</a></li></ul></div></div></div>
      </div>
    </div>
  </div>
</section>
```

- **Who sees what:** the `Skladište` column and the stock correction button are admin-only. A non-admin sees `Šifra | Naziv | MJ | Zaliha`, plus a reduced Akcija column with barcode and QR buttons only if they have create rights.
- **Stock format:** `toLocaleString('hr-HR')` with at most 3 decimals.
- **Row click** opens the "Etiketa materijala" modal with buttons `Zatvori` and `Preuzmi SVG`.
- **CSS:** copy lines 39–724 verbatim. The key ones are 132–143, 317–362, and the mobile block at 671–724.

---

### E. Documents (`apps/documents/released-materials.blade.php`)

**There are no tabs.** The same view is rendered by five controllers as five separate pages, each with its own sidebar entry under the "Dokumenti" navheader:

| Menu (feather icon) | Route | `documentType` | H2 title | Subtitle |
|---|---|---|---|---|
| archive | `app/documents/released-materials` | 6400 | Razduženi materijali | RN Rasknjiženje materijala |
| box | `app/documents/wip-releases` | 2005 | Razduživanje WIP | Prenos materijala u skladište proizvodnje u toku |
| tool | `app/documents/released-operations` | 6600 | Razdužene operacije | RN Rasknjiženje operacija |
| package | `app/documents/finished-goods-receipts` | 6100 | Prijem VP skladište | Prijem gotovih proizvoda iz radnog naloga |
| package | `app/documents/scrap-receipts` | 7100 | Prijem škarta | Prijem škarta iz radnog naloga |

The item label used in the filter placeholders changes per type: `materijala`, `operacije` (6600), `gotovog proizvoda` (6100), `škarta` (7100).

**At 390px:** the H2 title, then a "Filter dokumenata" card whose two small buttons wrap below the heading. The filter body is hidden by default. Below is the DataTable card: a min-width 1500/1620px table scrolling horizontally inside `.released-doc-table-body-scroll`, with a sticky delete column for admins.

```html
<style>
  /* copy lines 28–382 verbatim; key parts: */
  .released-doc-filter-actions { row-gap: 8px; }
  .released-doc-active-filter-chip { display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border:1px solid #d8d6de; border-radius:999px; background-color:#f8f8f8; font-size:12px; line-height:1; }
  .released-doc-active-filter-label { color:#6e6b7b; font-weight:500; }
  .released-doc-active-filter-value { color:#5e5873; font-weight:600; }
  .released-doc-document-number { display:inline-flex; align-items:center; gap:0.45rem; font-weight:700; letter-spacing:0.01em; }
  .released-doc-note-cell, .released-doc-name-cell { min-width:220px; }
  .released-doc-action-btn { display:inline-flex; align-items:center; justify-content:center; width:38px; min-width:38px; height:38px; padding:0.45rem; white-space:nowrap; }
  .released-doc-wrapper .dataTables_wrapper > .row:first-child, .released-doc-wrapper .dataTables_wrapper > .row:last-child { margin-left:0; margin-right:0; padding:1rem 1rem 0.95rem; }
  @media (max-width: 767.98px) { .released-doc-filter-actions { align-items: flex-start !important; } }
</style>
<section class="released-material-documents-wrapper">
  <div class="content-header row"><div class="content-header-left col-12 mb-2"><div class="row breadcrumbs-top"><div class="col-12"><h2 class="content-header-title float-start mb-0">Razduženi materijali</h2></div></div></div></div>
  <div class="card mb-2">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div><h4 class="mb-0">Filter dokumenata</h4><small class="text-muted">Dokumenti tipa 6400 - RN Rasknjiženje materijala</small></div>
      <div class="d-flex align-items-center flex-wrap gap-2 released-doc-filter-actions">
        <div id="released-doc-active-filters" class="released-doc-active-filters d-none"></div>
        <button type="button" class="btn btn-outline-primary btn-sm"><i data-feather="filter" class="me-50"></i> Prikaži filtere</button>
        <button type="button" class="btn btn-outline-danger btn-sm"><i data-feather="trash-2" class="me-50"></i> Obriši filter</button>
      </div>
    </div>
    <!-- hidden filter body: Dokument (file-text, placeholder "26-6400-..."), RN (search, "26-6000-0001687"), Narudžba (briefcase, "25-0110-0003084"), Šifra (hash, "Šifra materijala"), Naziv (search), Datum od / Datum do (calendar, "dd.mm.gggg"), Napomena (message-square), button "Filter" -->
  </div>
  <div class="card released-doc-wrapper">
    <div class="card-datatable table-responsive"><div class="dataTables_wrapper dt-bootstrap5">
      <div class="row"><div class="col-sm-12 col-md-6"><div class="dataTables_length"><label>Prikaži <select class="form-select form-select-sm"><option>10</option></select> zapisa</label></div></div>
        <div class="col-sm-12 col-md-6"><div class="dataTables_filter"><label>Brza pretraga:<input type="search" class="form-control form-control-sm" placeholder="Dokument, RN, narudžba, šifra ili naziv"></label></div></div></div>
      <div class="row"><div class="col-sm-12 released-doc-table-body-cell"><div class="released-doc-table-body-scroll">
        <table class="table released-doc-table" id="released-doc-table">
          <thead><tr><th>Dokument</th><th>Datum</th><th>RN</th><th>Narudžba</th><th>Pozicija</th><th>Šifra</th><th>Naziv</th><th>Količina</th><th>JM</th><th>Cijena RN</th><th>Napomena</th><th class="released-doc-action-cell">Akcija</th></tr></thead>
          <tbody>
            <tr><td class="released-doc-document-cell"><span class="released-doc-document-number">26-6400-0000412</span></td><td class="released-doc-date-cell">07.10.2026</td><td class="released-doc-work-order-cell">26-6000-0001687</td><td class="released-doc-order-cell">25-0110-0003084</td><td class="released-doc-position-cell text-end">10</td><td class="released-doc-code-cell">LIM-S235-5</td><td class="released-doc-name-cell">Lim S235 5mm 1500x3000</td><td class="released-doc-quantity-cell text-end">2,4</td><td class="released-doc-unit-cell">KG</td><td class="released-doc-price-cell text-end">12,48 KM</td><td class="released-doc-note-cell"><span class="text-muted">-</span></td>
              <td class="released-doc-action-cell text-end"><div class="released-doc-actions-group"><button type="button" class="btn btn-sm app-table-action-btn app-table-action-btn--danger released-doc-action-btn released-doc-delete-btn" title="Izbriši dokument"><i data-feather="trash-2"></i></button></div></td></tr>
          </tbody>
        </table>
      </div></div></div>
      <div class="row"><div class="col-sm-12 col-md-5"><div class="dataTables_info">Prikaz 1 do 10 od 214 zapisa</div></div><div class="col-sm-12 col-md-7"><!-- pagination Prethodna / Sljedeća --></div></div>
    </div></div>
  </div>
</section>
```

- **Formats:** `document_date_display` is `d.m.Y`. Price is `x.xxx,xx KM`. Quantity uses `hr-HR` with up to 3 decimals. Empty cells render `<span class="text-muted">-</span>`.
- **Loading overlay text:** `Učitavanje podataka`.

---

### F. NFC card page (`apps/nfc-card/app-nfc-card.blade.php`, title "Moja NFC kartica")

**Structure:** a centred `section.nfc-page[data-state="idle|scanning|reading|error|linked"]` contains `.nfc-stage` (max 560px) with two views.

1. **`.nfc-view--scan`** (shown in idle, scanning, reading and error):
   - **Scanner:** `.nfc-scanner`, a circle `min(78vw,320px)` holding 3 ripple rings, a conic radar, a "ghost" mini card that taps in, and a purple gradient core button with an NFC-wave SVG that swaps to a check SVG.
   - **Text:** H2 `.nfc-title` "Povežite svoju NFC karticu" and the subtitle "Dodirnite krug da pokrenete skener, zatim prislonite karticu na poleđinu uređaja."
   - **Live UID:** `.nfc-uid-live`, green mono with each digit animating in.
   - **Buttons:** `btn-primary` "Pokreni skener" (feather `radio`) and `btn-outline-secondary` "Unesi ručno" (feather `edit-3`).
   - **Manual entry:** a hidden input group, placeholder `npr. C36E1C28`, button `Spasi`.
   - **Scanning state text:** title "Prislonite karticu", subtitle "Skener je aktivan. Prislonite karticu na poleđinu uređaja."
2. **`.nfc-view--card`** (shown when linked):
   - Pill `.nfc-card-status` "Kartica je aktivna", with a pulsing green dot.
   - 3D card scene `min(88vw,420px)`, aspect ratio 1.586.
     - **Front:** the logo `images/pwa/trendy-gear-logo.png` at 58% width, with the UID in the bottom-right corner, e.g. `C3 6E 1C 28` (pairs of hex digits separated by spaces).
     - **Back:** a small logo plus `Korisnik`, `UID kartice` (red mono) and `Povezana` (`d.m.Y. H:i`, e.g. `07.10.2026. 08:14`).
   - Hint "Dodirnite karticu da je okrenete".
   - Buttons: `btn-outline-primary` "Zamijeni karticu" (`refresh-cw`) and `btn-outline-danger` "Ukloni" (`trash-2`).

**Key card CSS (verbatim):**
```css
.nfc-card-scene { perspective: 1400px; width: min(88vw, 420px); aspect-ratio: 1.586; margin: .5rem auto 2.25rem; cursor: pointer; }
.nfc-card-float { position: relative; width: 100%; height: 100%; transform-style: preserve-3d; animation: nfc-float 7s ease-in-out infinite; }
.nfc-card-float.is-entering { animation: nfc-enter 1.5s cubic-bezier(.2, .8, .2, 1) both, nfc-float 7s ease-in-out 1.5s infinite; }
.nfc-card { position: relative; width: 100%; height: 100%; transform-style: preserve-3d; transition: transform .85s cubic-bezier(.34, 1.36, .5, 1); }
.nfc-card-scene.is-flipped .nfc-card { transform: rotateY(180deg); }
@keyframes nfc-float { 0%, 100% { transform: rotateY(-16deg) rotateX(8deg) translateY(0); } 50% { transform: rotateY(16deg) rotateX(-4deg) translateY(-10px); } }
@keyframes nfc-enter { 0% { transform: translateY(60px) rotateY(-540deg) rotateX(30deg) scale(.4); opacity: 0; } 60% { opacity: 1; } 100% { transform: rotateY(-16deg) rotateX(8deg) scale(1); opacity: 1; } }
.nfc-card__face {
  position: absolute; inset: 0; border-radius: 5.5% / 8.7%;
  backface-visibility: hidden; -webkit-backface-visibility: hidden; color: #1b1b1b; text-align: left;
  background:
    linear-gradient(115deg, rgba(255, 255, 255, 0) 40%, rgba(255, 255, 255, .95) 50%, rgba(255, 255, 255, 0) 60%) no-repeat,
    radial-gradient(140% 120% at 20% 0%, #ffffff 0%, #f4f4f2 55%, #e6e6e3 100%);
  background-size: 260% 100%, 100% 100%;
  background-position: 160% 0, 0 0;
  animation: nfc-sheen 5s ease-in-out infinite;
  box-shadow: 0 30px 60px -22px rgba(0, 0, 0, .55), 0 0 0 1px rgba(0, 0, 0, .06), inset 0 1px 0 rgba(255, 255, 255, .9), inset 0 -2px 6px rgba(0, 0, 0, .06);
}
@keyframes nfc-sheen { 0%, 30% { background-position: 160% 0, 0 0; } 70%, 100% { background-position: -60% 0, 0 0; } }
.nfc-card__front { display: grid; place-items: center; }
.nfc-card__logo { width: 58%; height: auto; position: relative; z-index: 1; user-select: none; -webkit-user-drag: none; }
.nfc-card__corner-uid { position: absolute; right: 6%; bottom: 7%; z-index: 1; font-family: "SFMono-Regular", Consolas, "Liberation Mono", monospace; font-size: clamp(.55rem, 2.4vw, .72rem); letter-spacing: .12em; color: #a3a3a3; }
.nfc-card__back { transform: rotateY(180deg); display: flex; align-items: center; gap: 6%; padding: 7%; }
.nfc-card__logo--small { width: 32%; flex: 0 0 auto; opacity: .95; }
.nfc-card__info { display: grid; gap: .55rem; min-width: 0; position: relative; z-index: 1; }
.nfc-card__label { display: block; font-size: .6rem; text-transform: uppercase; letter-spacing: .14em; color: #8a8a8a; }
.nfc-card__value { display: block; font-weight: 700; font-size: clamp(.8rem, 3.4vw, 1rem); color: #1b1b1b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.nfc-card__value--mono { font-family: "SFMono-Regular", Consolas, "Liberation Mono", monospace; letter-spacing: .12em; color: #e30613; }
.nfc-card-status { display: inline-flex; align-items: center; gap: .45rem; padding: .35rem .9rem; border-radius: 999px; background: rgba(40, 199, 111, .12); color: var(--nfc-ok); font-weight: 600; margin-bottom: 1rem; }
.nfc-card-status::before { content: ''; width: 8px; height: 8px; border-radius: 50%; background: currentColor; box-shadow: 0 0 0 0 currentColor; animation: nfc-dot 1.8s infinite; }
.nfc-scanner__core { position: relative; width: 46%; aspect-ratio: 1; border-radius: 50%; display: grid; place-items: center; color: #fff; background: linear-gradient(145deg, var(--nfc-accent), #9e95f5); box-shadow: 0 18px 40px -12px rgba(115, 103, 240, .65), inset 0 2px 0 rgba(255, 255, 255, .25); border: 0; }
```
The page variables are `--nfc-accent:#7367f0`, `--nfc-ok:#28c76f` and `--nfc-err:#ea5455`. The full stylesheet is lines 6–305.

---

### Source files
- c:\Users\Public\Documents\trendy\resources\views\content\apps\invoice\app-invoice-preview.blade.php
- c:\Users\Public\Documents\trendy\resources\views\content\new-components\nalog-scan.blade.php
- c:\Users\Public\Documents\trendy\resources\views\content\new-components\sirovina-scan.blade.php
- c:\Users\Public\Documents\trendy\resources\views\content\new-components\fine-adjust-bom.blade.php
- c:\Users\Public\Documents\trendy\resources\views\content\apps\invoice\app-invoice-scan-operations.blade.php
- c:\Users\Public\Documents\trendy\resources\views\panels\footer.blade.php
- c:\Users\Public\Documents\trendy\resources\views\content\apps\materials\app-material.blade.php
- c:\Users\Public\Documents\trendy\resources\js\scripts\pages\app-material.js
- c:\Users\Public\Documents\trendy\resources\views\content\apps\documents\released-materials.blade.php
- c:\Users\Public\Documents\trendy\resources\js\scripts\pages\app-released-material-documents.js
- c:\Users\Public\Documents\trendy\resources\views\content\apps\nfc-card\app-nfc-card.blade.php
- c:\Users\Public\Documents\trendy\app\Http\Controllers\WorkOrderController.php (meta chips, KPIs, timeline: lines 9744–9817)
- c:\Users\Public\Documents\trendy\app\Services\WorkOrder\DeliveryPriorityOptions.php
- c:\Users\Public\Documents\trendy\resources\data\menu-data\mainMenu.json