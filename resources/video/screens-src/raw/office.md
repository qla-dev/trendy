I've written static HTML for all four screens (content area only, inside `.content-body`). Markup, classes, Bosnian labels and feather icon names are copied from the Blade views and from the JS that builds the rows. Sample rows follow the formatters in the code. The `<style>` blocks for the dashboard and production plan are copied in full. The AI scan has a 2,500-line style block, so I copied only the light-mode rules each mockup needs (still verbatim) and listed the line ranges for the rest.

**Two asset problems to fix first:**
- **Missing vendor CSS:** your compiled-CSS copy in `c:\Users\Public\Documents\trendy\resources\video\public\app\css\` has `core.css`, `base/*` and `base/pages/app-invoice-list.css` / `dashboard-ecommerce.css`, but no `vendors/` folder. There is no `public/css` at all. The vendor CSS lives in `c:\Users\Public\Documents\trendy\resources\vendors\css\...` (for example `tables/datatable/dataTables.bootstrap5.min.css`). Copy the files listed per screen from there.
- **Light theme selector:** many AI-scan light-theme rules require `html.light-layout:not(.dark-layout)...`. Put `class="light-layout"` on `<html>` or those styles won't apply.

**Formats taken from the code:**
- **Work order (RN) number:** 13 digits are shown as `2-4-7` digits, e.g. `25-6000-0001234`. Order (Narudžba) numbers use the same format.
- **Product name:** cut to 10 characters plus `..` in both the dashboard and the list.
- **List dates:** moment `DD MMM YYYY` (e.g. `03 Oct 2026`).
- **Production-plan dates:** `dd.mm.yyyy`. Quantities use the `bs-BA` locale. `GROB-WERKE` is shown as `GW`, and `TRENDY GERMANY GMBH 2` as `TG GmbH 2`.
- **Priorities (fallback list):** `1 - Visoki prioritet`, `5 - Uobičajeni prioritet`, `7 - Materijal razdužen`, `10 - Niski prioritet`, `15 - Uzorci`. In the list the trailing " prioritet" is stripped and the badges are `badge-light-danger text-danger`, `badge-light-warning text-warning`, `badge-light-info text-info` (10 and 15), or `badge-light-secondary`.
- **Status → badge class (list and dashboard):**
  - otvoren → `-otvoren` (green)
  - u radu / u toku → `-u-radu` (#b38600 on yellow)
  - rezerv → `-rezerviran` (orange)
  - djelimič → `-djelimicno-zakljucen` (#fd7e14)
  - zaklj / zavr / otkaz → `-zakljucen` (red)
  - planiran / novo → `-planiran` (cyan)
  - raspis / nacrt → `-raspisan` (#495B73; list only)

---

## A. Dashboard — `c:\Users\Public\Documents\trendy\resources\views\content\dashboard\dashboard-ecommerce.blade.php`

**CSS files:**
- Vendor: `vendors/css/charts/apexcharts.css`, `vendors/css/extensions/toastr.min.css`
- Page: `css/base/pages/dashboard-ecommerce.css`, `css/base/plugins/charts/chart-apex.css`, `css/base/plugins/extensions/ext-component-toastr.css`

**Charts** (`c:\Users\Public\Documents\trendy\resources\js\scripts\pages\dashboard-ecommerce.js`) are ApexCharts, so you need to draw them with SVG or real ApexCharts:
- `#revenue-report-chart`: stacked bar, height 230, columnWidth 17%. Colours: warning `#ff9f43` for the current year and `#dcdae3` for the comparison year. Months: Jan Feb Mar Apr Maj Jun Jul Aug Sep Okt Nov Dec.
- `#budget-chart`: sparkline line, height 80. Same two colours, and the second series is dashed.
- `#statistics-order-chart`: stacked bar sparkline, warning colour.
- `#statistics-profit-chart`: line, info colour.
- `#earnings-chart`: donut with labels Proizvodnja / Usluge / Mašine.
- The table shows the latest 6 orders (`limit(6)`).

```html
<style>
  .dark-layout a:hover { color: unset!important; }
  .dashboard-workorders-card .table { margin-bottom: 0; }
  .dashboard-workorders-scroll { overflow-x: auto; overflow-y: hidden; scrollbar-width: thin; scrollbar-color: var(--app-scroll-thumb-flat) var(--app-scroll-track); scrollbar-gutter: stable; }
  .dashboard-workorders-table thead th { background-color: #f8f8fc; border-bottom: 1px solid #ebe9f1; color: #6e6b7b; font-size: 1rem; font-weight: 700; letter-spacing: 0.02em; text-transform: uppercase; white-space: nowrap; }
  .dashboard-workorders-table > :not(caption) > * > * { box-shadow: none !important; }
  .dashboard-report-chart-shell { position: relative; min-height: 230px; }
  .dashboard-report-loader { position: absolute; inset: 0; z-index: 5; display: flex; align-items: center; justify-content: center; background: rgba(255, 255, 255, 0.78); backdrop-filter: blur(1px); transition: opacity 0.18s ease, visibility 0.18s ease; }
  .dashboard-report-loader.is-hidden { opacity: 0; visibility: hidden; pointer-events: none; }
  .dashboard-report-hover-box.is-hidden { opacity: 0; visibility: hidden; }
  .dashboard-workorders-table > :not(:first-child) { border-top: 0 !important; }
  .dashboard-workorders-table tbody td { border-top: 1px solid #ebe9f1; vertical-align: middle; color: #5e5873; font-size: 1.1rem; font-weight: 500; }
  .dashboard-workorders-table tbody tr:first-child td { border-top: 0; }
  .dashboard-workorders-table.table-hover tbody tr:hover > * { background-color: #f8f8fc; }
  .dashboard-workorders-table tbody tr.dashboard-workorder-row { cursor: pointer; }
  .dashboard-workorder-id { color: #42526e; font-weight: 700; white-space: nowrap; }
  .dashboard-client-wrap { display: flex; align-items: center; gap: 0.8rem; min-width: 0; }
  .dashboard-product-name { font-weight: 500; color: #5e5873; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 260px; }
  .dashboard-client-avatar { width: 2.1rem; height: 2.1rem; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1rem; flex: 0 0 auto; }
  .dashboard-client-name { font-weight: 500; color: #5e5873; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .dashboard-status-badge { font-weight: 500; }
  .dashboard-status-default { background-color: rgba(110, 107, 123, 0.12) !important; color: #6e6b7b !important; }
  .dashboard-status-planiran { background-color: rgba(0, 207, 232, 0.12) !important; color: #00cfe8 !important; }
  .dashboard-status-otvoren { background-color: rgba(40, 199, 111, 0.12) !important; color: #28c76f !important; }
  .dashboard-status-rezerviran { background-color: rgba(255, 159, 67, 0.12) !important; color: #ff9f43 !important; }
  .dashboard-status-u-radu { background-color: rgba(255, 193, 7, 0.16) !important; color: #b38600 !important; }
  .dashboard-status-djelimicno { background-color: rgba(253, 126, 20, 0.12) !important; color: #fd7e14 !important; }
  .dashboard-status-zakljucen { background-color: rgba(234, 84, 85, 0.12) !important; color: #ea5455 !important; }
</style>

<section id="dashboard-ecommerce">
  <div class="row match-height">
    <div class="col-lg-4 col-md-4 col-12">
      <div class="card card-developer-meetup">
        <div class="meetup-img-wrapper rounded-top text-center">
          <img src="images/illustration/email.svg" alt="CNC proizvodnja" height="170" />
        </div>
        <div class="card-body">
          <div class="meetup-header d-flex align-items-center">
            <div class="meetup-day"><h6 class="mb-0">ČET</h6><h3 class="mb-0">24</h3></div>
            <div class="my-auto">
              <h4 class="card-title mb-25">GROB-WERKE </h4>
              <p class="card-text mb-0">Sastanak o saradnji vezanoj za CNC obradu metala – kapaciteti i rokovi.</p>
            </div>
          </div>
          <div class="mt-0">
            <div class="avatar float-start bg-light-primary rounded me-1"><div class="avatar-content"><i data-feather="calendar" class="avatar-icon font-medium-3"></i></div></div>
            <div class="more-info"><h6 class="mb-0">Četvrtak, 24. decembar 2025.</h6><small>09:00 – 10:30</small></div>
          </div>
          <div class="mt-2">
            <div class="avatar float-start bg-light-primary rounded me-1"><div class="avatar-content"><i data-feather="map-pin" class="avatar-icon font-medium-3"></i></div></div>
            <div class="more-info"><h6 class="mb-0">Online sastanak (Teams / Zoom)</h6>
              <small>Predstavljanje mašina (CNC glodanje i tokarenje), tipičnih serija, tolerancija, površinske obrade.</small></div>
          </div>
          <div class="avatar-group mt-1">
            <div class="avatar pull-up" title="Direktor proizvodnje – Trendy d.o.o."><img src="images/portrait/small/avatar-s-9.jpg" alt="Avatar" width="33" height="33" /></div>
            <div class="avatar pull-up" title="Tehnički inženjer – Trendy d.o.o."><img src="images/portrait/small/avatar-s-6.jpg" alt="Avatar" width="33" height="33" /></div>
            <div class="avatar pull-up" title="Predstavnik nabavke vašeg preduzeća"><img src="images/portrait/small/avatar-s-8.jpg" alt="Avatar" width="33" height="33" /></div>
            <h6 class="align-self-center cursor-pointer ms-50 mb-0">+ još učesnika po potrebi</h6>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl-8 col-md-8 col-12">
      <div class="card card-statistics">
        <div class="card-body statistics-body">
          <div class="row">
            <div class="col-xl-4 col-sm-6 col-12 mb-2 mb-xl-0"><div class="d-flex flex-row">
              <div class="avatar bg-light-warning me-2"><div class="avatar-content"><i data-feather="clipboard" class="avatar-icon"></i></div></div>
              <div class="my-auto"><h4 class="fw-bolder mb-0">12.486</h4><p class="card-text font-small-3 mb-0">Radni nalozi</p></div>
            </div></div>
            <div class="col-xl-4 col-sm-6 col-12 mb-2 mb-xl-0"><div class="d-flex flex-row">
              <div class="avatar bg-light-info me-2"><div class="avatar-content"><i data-feather="user" class="avatar-icon"></i></div></div>
              <div class="my-auto"><h4 class="fw-bolder mb-0">214</h4><p class="card-text font-small-3 mb-0">Kupci</p></div>
            </div></div>
            <div class="col-xl-4 col-sm-6 col-12 mb-2 mb-sm-0"><div class="d-flex flex-row">
              <div class="avatar bg-light-danger me-2"><div class="avatar-content"><i data-feather="box" class="avatar-icon"></i></div></div>
              <div class="my-auto"><h4 class="fw-bolder mb-0">3.972</h4><p class="card-text font-small-3 mb-0">Proizvodi</p></div>
            </div></div>
          </div>
        </div>
      </div>

      <div class="card card-revenue-budget">
        <div class="row mx-0">
          <div class="col-md-8 col-12 revenue-report-wrapper">
            <div class="d-sm-flex justify-content-between align-items-center mb-3">
              <h4 class="card-title mb-50 mb-sm-0">Izvještaj o radnim nalozima</h4>
              <div class="d-flex align-items-center">
                <div class="d-flex align-items-center me-2"><span class="bullet bullet-warning font-small-3 me-50 cursor-pointer"></span><span id="revenue-current-label">2026</span></div>
                <div class="d-flex align-items-center ms-75"><span class="bullet bullet-secondary font-small-3 me-50 cursor-pointer"></span><span id="revenue-compare-label">2025</span></div>
              </div>
            </div>
            <div class="dashboard-report-chart-shell">
              <div id="dashboard-report-loader" class="dashboard-report-loader is-hidden"></div>
              <div id="revenue-report-chart"><!-- stacked bar chart, 230px --></div>
            </div>
          </div>
          <div class="col-md-4 col-12 budget-wrapper pb-2 pb-md-0">
            <div class="btn-group">
              <button type="button" id="dashboard-report-year-toggle" class="btn btn-outline-primary btn-sm dropdown-toggle budget-dropdown">2025</button>
            </div>
            <p class="text-center text mb-50" id="work-orders-total-subtitle">Tekuća godina: 2026</p>
            <h2 class="mb-25" id="work-orders-total-primary">1.284 naloga</h2>
            <div class="d-flex justify-content-center">
              <span class="fw-bolder me-25" id="work-orders-total-compare-label">Poređenje sa 2025:</span>
              <span id="work-orders-total-compare">1.102 naloga</span>
            </div>
            <div class="d-flex justify-content-center mb-1">
              <span class="badge rounded-pill badge-light-success" id="work-orders-delta">Razlika: +182 (+16,5%)</span>
            </div>
            <div id="budget-chart"><!-- sparkline line, 80px --></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row match-height">
    <div class="col-lg-4 col-12">
      <div class="row match-height">
        <div class="col-lg-6 col-md-3 col-6"><div class="card"><div class="card-body pb-50">
          <h6>Narudžbe</h6><h2 class="fw-bolder mb-1">2,76k</h2><div id="statistics-order-chart"></div>
        </div></div></div>
        <div class="col-lg-6 col-md-3 col-6"><div class="card card-tiny-line-stats"><div class="card-body pb-50">
          <h6>Dobit</h6><h2 class="fw-bolder mb-1">6,24k</h2><div id="statistics-profit-chart"></div>
        </div></div></div>
        <div class="col-lg-12 col-md-6 col-12"><div class="card earnings-card"><div class="card-body"><div class="row">
          <div class="col-6">
            <h4 class="card-title mb-1">Zarada</h4>
            <div class="font-small-2">Ovaj mjesec</div>
            <h5 class="mb-1">4055,56 KM</h5>
            <p class="card-text text-muted font-small-2"><span class="fw-bolder">68.2%</span><span> više zarade nego prošlog mjeseca.</span></p>
          </div>
          <div class="col-6"><div id="earnings-chart"><!-- donut --></div></div>
        </div></div></div></div>
      </div>
    </div>

    <div class="col-lg-8 col-12">
      <div class="card card-company-table dashboard-workorders-card">
        <div class="card-body p-0">
          <div class="table-responsive dashboard-workorders-scroll">
            <table class="table table-hover borderless mb-0 dashboard-workorders-table">
              <thead><tr><th>#</th><th>Narudžba</th><th>Naziv</th><th>Šifra</th><th>Klijent</th><th>Datum kreiranja</th><th>Status</th></tr></thead>
              <tbody>
                <tr class="dashboard-workorder-row">
                  <td class="text-nowrap"><span class="dashboard-workorder-id">26-6000-0001284</span></td>
                  <td class="text-nowrap"><span>26-0100-0000412</span></td>
                  <td><span class="dashboard-product-name" title="Prirubnica DN80 PN16">Prirubnica..</span></td>
                  <td class="text-nowrap"><span>PR-080-16</span></td>
                  <td><div class="dashboard-client-wrap"><span class="dashboard-client-avatar" style="background-color: hsla(212, 72%, 52%, 0.18); color: hsl(212, 82%, 30%); border: 1px solid hsla(212, 72%, 52%, 0.35);">GW</span><span class="dashboard-client-name">GROB-WERKE</span></div></td>
                  <td>06 Oct 2026</td>
                  <td><span class="badge rounded-pill dashboard-status-badge dashboard-status-u-radu">U radu</span></td>
                </tr>
                <tr class="dashboard-workorder-row">
                  <td class="text-nowrap"><span class="dashboard-workorder-id">26-6000-0001283</span></td>
                  <td class="text-nowrap"><span>26-0100-0000411</span></td>
                  <td><span class="dashboard-product-name" title="Osovina vratila Ø40">Osovina vr..</span></td>
                  <td class="text-nowrap"><span>OV-040-220</span></td>
                  <td><div class="dashboard-client-wrap"><span class="dashboard-client-avatar" style="background-color: hsla(28, 72%, 52%, 0.18); color: hsl(28, 82%, 30%); border: 1px solid hsla(28, 72%, 52%, 0.35);">TG</span><span class="dashboard-client-name">TRENDY GERMANY GMBH</span></div></td>
                  <td>06 Oct 2026</td>
                  <td><span class="badge rounded-pill dashboard-status-badge dashboard-status-otvoren">Otvoren</span></td>
                </tr>
                <tr class="dashboard-workorder-row">
                  <td class="text-nowrap"><span class="dashboard-workorder-id">26-6000-0001282</span></td>
                  <td class="text-nowrap"><span>26-0100-0000409</span></td>
                  <td><span class="dashboard-product-name" title="Nosač motora 3mm">Nosač moto..</span></td>
                  <td class="text-nowrap"><span>NM-300-A</span></td>
                  <td><div class="dashboard-client-wrap"><span class="dashboard-client-avatar" style="background-color: hsla(140, 72%, 52%, 0.18); color: hsl(140, 82%, 30%); border: 1px solid hsla(140, 72%, 52%, 0.35);">KH</span><span class="dashboard-client-name">KOVIS Hidraulika</span></div></td>
                  <td>05 Oct 2026</td>
                  <td><span class="badge rounded-pill dashboard-status-badge dashboard-status-planiran">Planiran</span></td>
                </tr>
                <tr class="dashboard-workorder-row">
                  <td class="text-nowrap"><span class="dashboard-workorder-id">26-6000-0001281</span></td>
                  <td class="text-nowrap"><span>26-0100-0000405</span></td>
                  <td><span class="dashboard-product-name" title="Kućište ležaja">Kućište le..</span></td>
                  <td class="text-nowrap"><span>KL-6205</span></td>
                  <td><div class="dashboard-client-wrap"><span class="dashboard-client-avatar" style="background-color: hsla(212, 72%, 52%, 0.18); color: hsl(212, 82%, 30%); border: 1px solid hsla(212, 72%, 52%, 0.35);">GW</span><span class="dashboard-client-name">GROB-WERKE</span></div></td>
                  <td>03 Oct 2026</td>
                  <td><span class="badge rounded-pill dashboard-status-badge dashboard-status-rezerviran">Rezerviran</span></td>
                </tr>
                <tr class="dashboard-workorder-row">
                  <td class="text-nowrap"><span class="dashboard-workorder-id">26-6000-0001279</span></td>
                  <td class="text-nowrap"><span>26-0100-0000398</span></td>
                  <td><span class="dashboard-product-name" title="Distantna čahura">Distantna ..</span></td>
                  <td class="text-nowrap"><span>DC-12-30</span></td>
                  <td><div class="dashboard-client-wrap"><span class="dashboard-client-avatar" style="background-color: hsla(28, 72%, 52%, 0.18); color: hsl(28, 82%, 30%); border: 1px solid hsla(28, 72%, 52%, 0.35);">TG</span><span class="dashboard-client-name">TRENDY GERMANY GMBH</span></div></td>
                  <td>02 Oct 2026</td>
                  <td><span class="badge rounded-pill dashboard-status-badge dashboard-status-djelimicno">Djelomično zaključen</span></td>
                </tr>
                <tr class="dashboard-workorder-row">
                  <td class="text-nowrap"><span class="dashboard-workorder-id">26-6000-0001275</span></td>
                  <td class="text-nowrap"><span>26-0100-0000390</span></td>
                  <td><span class="dashboard-product-name" title="Ploča adaptera">Ploča adap..</span></td>
                  <td class="text-nowrap"><span>PA-200-10</span></td>
                  <td><div class="dashboard-client-wrap"><span class="dashboard-client-avatar" style="background-color: hsla(300, 72%, 52%, 0.18); color: hsl(300, 82%, 30%); border: 1px solid hsla(300, 72%, 52%, 0.35);">MS</span><span class="dashboard-client-name">Metalac Sarajevo</span></div></td>
                  <td>30 Sep 2026</td>
                  <td><span class="badge rounded-pill dashboard-status-badge dashboard-status-zakljucen">Zaključen</span></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
```

On this screen, avatar initials are the first letter of the first word plus the first letter of the last word. The hue is `crc32(lowercase name) % 360`.

---

## B. Work orders list — `c:\Users\Public\Documents\trendy\resources\views\content\apps\invoice\app-invoice-list.blade.php` + `c:\Users\Public\Documents\trendy\resources\js\scripts\pages\app-invoice-list.js`

**CSS files:**
- Vendor: `vendors/css/tables/datatable/dataTables.bootstrap5.min.css`, `vendors/css/tables/datatable/extensions/dataTables.checkboxes.css`, `vendors/css/tables/datatable/responsive.bootstrap5.min.css`, `vendors/css/pickers/flatpickr/flatpickr.min.css`
- Page: `css/base/pages/app-invoice-list.css`, `css/base/plugins/forms/pickers/form-flat-pickr.css`

The DataTables layout comes from the JS `dom` setting:
- top row: "Prikaži [10]" on the left, search on the right
- the table itself
- bottom row: info text on the left, pagination on the right

The pagination arrows are `&nbsp;` (the theme draws the chevrons). Default sort is by Datum, descending. The Akcija column (`trash-2` button `.wo-delete-action`, title "Obriši RN") only appears when `$canDeleteWorkOrders` is true, so it is left out below.

```html
<style>
  /* first <style> (page-style) */
  .content-header { margin-top: -6px; margin-bottom: 4px; }
  .content-header-title { margin-top: 5px; }
  /* second <style> (status cards etc.) — verbatim subset */
  .status-cards-wrapper { display: flex; flex-wrap: nowrap; gap: 14px; width: 100%; align-items: stretch; margin: 0; padding: 0; }
  .status-card { flex: 1 1 0; min-width: 0; max-width: none; background: #fff; border: 1px solid #ebe9f1; border-radius: 5px; cursor: pointer; transition: all 0.2s ease; box-shadow: none; }
  .status-card:hover { background-color: #f8f8f8; }
  .status-card-active { border-color: #495B73; background-color: #fff; box-shadow: 0 4px 24px 0 rgba(34, 41, 47, 0.1); }
  .status-card[data-status="svi"] { border-color: #6e6b7b; }
  .status-card[data-status="planiran"] { border-color: #00cfe8; }
  .status-card[data-status="otvoren"] { border-color: #28c76f; }
  .status-card[data-status="rezerviran"] { border-color: #ff9f43; }
  .status-card[data-status="u_radu"] { border-color: #ffc107; }
  .status-card[data-status="djelimicno_zakljucen"] { border-color: #fd7e14; }
  .status-card[data-status="zakljucen"] { border-color: #ea5455; }
  .status-card-body { padding: 10px 15px; text-align: center; }
  .status-label { font-size: 13px; font-weight: 500; margin-bottom: 5px; color: #6e6b7b; white-space: nowrap; }
  .status-card[data-status="svi"] .status-label { color: #6e6b7b; }
  .status-card[data-status="planiran"] .status-label { color: #00cfe8; }
  .status-card[data-status="otvoren"] .status-label { color: #28c76f; }
  .status-card[data-status="rezerviran"] .status-label { color: #ff9f43; }
  .status-card[data-status="u_radu"] .status-label { color: #ffc107; }
  .status-card[data-status="djelimicno_zakljucen"] .status-label { color: #fd7e14; }
  .status-card[data-status="zakljucen"] .status-label { color: #ea5455; }
  .status-count { font-size: 1.5rem; font-weight: 700; color: #5e5873; line-height: 1; }
  .status-badge { font-weight: 500; }
  .status-badge-default { background-color: rgba(110, 107, 123, 0.12) !important; color: #6e6b7b !important; }
  .status-badge-planiran { background-color: rgba(0, 207, 232, 0.12) !important; color: #00cfe8 !important; }
  .status-badge-otvoren { background-color: rgba(40, 199, 111, 0.12) !important; color: #28c76f !important; }
  .status-badge-rezerviran { background-color: rgba(255, 159, 67, 0.12) !important; color: #ff9f43 !important; }
  .status-badge-raspisan { background-color: rgba(73, 91, 115, 0.12) !important; color: #495B73 !important; }
  .status-badge-u-radu { background-color: rgba(255, 193, 7, 0.16) !important; color: #b38600 !important; }
  .status-badge-djelimicno-zakljucen { background-color: rgba(253, 126, 20, 0.12) !important; color: #fd7e14 !important; }
  .status-badge-zakljucen { background-color: rgba(234, 84, 85, 0.12) !important; color: #ea5455 !important; }
  .filter-input { font-size: 14px; }
  .filter-header-actions { row-gap: 8px; }
  #btn-toggle-filters:not(:hover):not(:active), #btn-delete-filter:not(:hover):not(:active) { background-color: transparent; }
  .filter-input::placeholder { padding-left: 8px; }
  .input-group-text { background: #f8f8f8; border-right: none; }
  .content-header-right { display: flex; justify-content: flex-end; align-items: center; }
  .content-header-right .breadcrumb-right { width: 100%; display: flex; justify-content: flex-end; }
  .invoice-list-table tbody tr { cursor: pointer; transition: background-color 0.2s ease, color 0.2s ease; }
  .invoice-list-table.table tbody tr:hover > * { background-color: #f8f8fc; }
  .invoice-list-wrapper .invoice-list-table { min-width: 1100px; }
  .invoice-list-wrapper .card-datatable.table-responsive { overflow-x: visible; }
  .invoice-list-wrapper .card-datatable .dataTables_wrapper > .row:last-child { display: flex; flex-wrap: wrap; justify-content: flex-start; align-items: center; }
  .invoice-list-wrapper .card-datatable .dataTables_wrapper > .row:last-child > div:first-child { flex: 0 0 auto !important; width: auto !important; max-width: none !important; }
  .invoice-list-wrapper .card-datatable .dataTables_wrapper > .row:last-child > div:last-child { display: flex !important; flex: 0 0 auto !important; width: auto !important; max-width: none !important; margin-left: auto !important; justify-content: flex-end !important; }
  .invoice-list-wrapper .card-datatable .pagination { margin-bottom: 1rem !important; }
  .invoice-list-wrapper .card-datatable .dataTables_info { padding-top: 0 !important; }
  div.dataTables_wrapper div.dataTables_filter { text-align: right; float: right; }
  div.dataTables_wrapper div.dataTables_filter label { margin-top: 0; margin-bottom: 0; text-align: right; float: right; display: inline-flex; align-items: center; }
  .invoice-search-label-wrap { display: inline-flex; align-items: center; gap: 0.45rem; margin-right: 0.5rem; color: #6e6b7b; font-weight: 500; line-height: 1; white-space: nowrap; }
  .invoice-search-header-spinner { width: 0.9rem; height: 0.9rem; border-width: 0.15em; color: #495b73; display: none; }
  @media (min-width: 768px) {
    .invoice-list-wrapper .col-lg-6:first-child { flex: 0 0 auto; width: 25%; }
    .invoice-list-wrapper .col-lg-6:nth-child(2) { flex: 0 0 auto; width: 75%; }
  }
</style>

<section class="invoice-list-wrapper">
  <div class="content-header row">
    <div class="content-header-left col-md-8 col-12 mb-2">
      <div class="row breadcrumbs-top"><div class="col-12"><h2 class="content-header-title float-start mb-0">Radni nalozi</h2></div></div>
    </div>
    <div class="content-header-right text-md-end col-md-4 col-12 d-md-block d-none">
      <div class="mb-1 breadcrumb-right">
        <button type="button" class="btn btn-primary" id="btn-add"><i data-feather="plus" class="me-50"></i> Dodaj radni nalog</button>
      </div>
    </div>
  </div>

  <div class="row mb-2"><div class="col-12"><div class="d-flex status-cards-wrapper">
    <div class="status-card status-card-active" data-status="svi"><div class="status-card-body"><div class="status-label">Svi</div><div class="status-count">1248</div></div></div>
    <div class="status-card" data-status="planiran"><div class="status-card-body"><div class="status-label">Planiran</div><div class="status-count">86</div></div></div>
    <div class="status-card" data-status="otvoren"><div class="status-card-body"><div class="status-label">Otvoren</div><div class="status-count">142</div></div></div>
    <div class="status-card" data-status="rezerviran"><div class="status-card-body"><div class="status-label">Rezerviran</div><div class="status-count">37</div></div></div>
    <div class="status-card" data-status="u_radu"><div class="status-card-body"><div class="status-label">U radu</div><div class="status-count">64</div></div></div>
    <div class="status-card" data-status="djelimicno_zakljucen"><div class="status-card-body"><div class="status-label">Djel. zaključen</div><div class="status-count">21</div></div></div>
    <div class="status-card" data-status="zakljucen"><div class="status-card-body"><div class="status-label">Zaključen</div><div class="status-count">898</div></div></div>
  </div></div></div>

  <div class="card mb-2">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h4 class="mb-0">Filter radnih naloga</h4>
      <div class="d-flex align-items-center flex-wrap gap-2 filter-header-actions">
        <button type="button" class="btn btn-outline-primary btn-sm" id="btn-toggle-filters"><i data-feather="filter" class="me-50"></i> Sakrij filtere</button>
        <button type="button" class="btn btn-outline-danger btn-sm" id="btn-delete-filter"><i data-feather="trash-2" class="me-50"></i> Obriši filter</button>
      </div>
    </div>
    <div class="card-body" id="filters-body">
      <div class="row g-2 mb-2">
        <div class="col-md-3"><label class="form-label">Kupac</label><div class="input-group input-group-merge"><span class="input-group-text"><i data-feather="search"></i></span><input type="text" class="form-control filter-input" placeholder="Kupac"></div></div>
        <div class="col-md-3"><label class="form-label">Primatelj</label><div class="input-group input-group-merge"><span class="input-group-text"><i data-feather="search"></i></span><input type="text" class="form-control filter-input" placeholder="Primatelj"></div></div>
        <div class="col-md-3"><label class="form-label">Proizvod</label><div class="input-group input-group-merge"><span class="input-group-text"><i data-feather="search"></i></span><input type="text" class="form-control filter-input" placeholder="Proizvod"></div></div>
        <div class="col-md-3"><label class="form-label">Plan. početak od</label><div class="input-group input-group-merge"><span class="input-group-text"><i data-feather="calendar"></i></span><input type="text" class="form-control filter-input filter-date-input" placeholder="dd.mm.gggg"></div></div>
      </div>
      <div class="row g-2 mb-2">
        <div class="col-md-3"><label class="form-label">Plan. početak do</label><div class="input-group input-group-merge"><span class="input-group-text"><i data-feather="calendar"></i></span><input type="text" class="form-control filter-input filter-date-input" placeholder="dd.mm.gggg"></div></div>
        <div class="col-md-3"><label class="form-label">Plan. kraj od</label><div class="input-group input-group-merge"><span class="input-group-text"><i data-feather="calendar"></i></span><input type="text" class="form-control filter-input filter-date-input" placeholder="dd.mm.gggg"></div></div>
        <div class="col-md-3"><label class="form-label">Plan. kraj do</label><div class="input-group input-group-merge"><span class="input-group-text"><i data-feather="calendar"></i></span><input type="text" class="form-control filter-input filter-date-input" placeholder="dd.mm.gggg"></div></div>
        <div class="col-md-3"><label class="form-label">Datum od</label><div class="input-group input-group-merge"><span class="input-group-text"><i data-feather="calendar"></i></span><input type="text" class="form-control filter-input filter-date-input" placeholder="dd.mm.gggg"></div></div>
      </div>
      <div class="row g-2 mb-2">
        <div class="col-md-3"><label class="form-label">RN datum do</label><div class="input-group input-group-merge"><span class="input-group-text"><i data-feather="calendar"></i></span><input type="text" class="form-control filter-input filter-date-input" placeholder="dd.mm.gggg"></div></div>
        <div class="col-md-3"><label class="form-label">Vezni dok.</label><div class="input-group input-group-merge"><span class="input-group-text"><i data-feather="search"></i></span><input type="text" class="form-control filter-input" placeholder="Vezni dok."></div></div>
        <div class="col-md-3"><label class="form-label">Prioritet</label><select class="form-select filter-input"><option>Svi prioriteti</option><option>1 - Visoki prioritet</option><option>5 - Uobičajeni prioritet</option><option>7 - Materijal razdužen</option><option>10 - Niski prioritet</option><option>15 - Uzorci</option></select></div>
        <div class="col-md-3 d-flex align-items-end"><button type="button" class="btn btn-primary w-100" id="btn-filter"><i data-feather="filter" class="me-50"></i> Filter</button></div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-datatable table-responsive">
      <div class="dataTables_wrapper dt-bootstrap5 no-footer">
        <div class="row d-flex justify-content-between align-items-center m-1">
          <div class="col-lg-6 d-flex align-items-center">
            <div class="dataTables_length"><label>Prikaži <select class="form-select"><option>10</option><option>25</option><option>50</option></select></label></div>
            <div class="dt-action-buttons text-xl-end text-lg-start text-lg-end text-start "><div class="dt-buttons"></div></div>
          </div>
          <div class="col-lg-6 d-flex align-items-center justify-content-lg-end flex-lg-nowrap flex-wrap pe-lg-1 p-0">
            <div class="dataTables_filter"><label>
              <span class="invoice-search-label-wrap"><span class="spinner-border spinner-border-sm invoice-search-header-spinner"></span><span class="invoice-search-header-label">Brza pretraga po nazivu, šifri, klijentu itd..</span></span>
              <input type="search" class="form-control" placeholder="Pretraži...">
            </label></div>
          </div>
        </div>
        <table class="invoice-list-table table dataTable">
          <thead><tr><th>#</th><th>Narudžba</th><th>Naziv</th><th>Šifra</th><th>Qty</th><th>Klijent</th><th class="text-truncate">Datum</th><th>Status</th><th>Prioritet</th></tr></thead>
          <tbody>
            <tr>
              <td class="text-nowrap"><a class="fw-bold text-nowrap" href="#">26-6000-0001284</a></td>
              <td><span class="text-nowrap">26-0100-0000412</span></td>
              <td><span class="text-truncate d-inline-block w-100" title="Prirubnica DN80 PN16">Prirubnica..</span></td>
              <td><span class="text-nowrap">PR-080-16</span></td>
              <td class="align-middle"><span class="text-nowrap fw-semibold">120 <small class="text-muted">kom</small></span></td>
              <td><div class="d-flex justify-content-left align-items-center"><div class="avatar-wrapper"><div class="avatar me-50" style="background-color: hsla(212, 70%, 54%, 0.18); color: hsl(212, 78%, 34%); border: 1px solid hsla(212, 70%, 54%, 0.35);"><div class="avatar-content">GW</div></div></div><div class="d-flex flex-column"><h6 class="user-name text-truncate mb-0" title="GROB-WERKE">GROB-WERKE</h6><small class="text-truncate text-muted">Amir H.</small></div></div></td>
              <td>06 Oct 2026</td>
              <td class="text-center align-middle"><div class="d-flex justify-content-center"><span class="badge rounded-pill status-badge status-badge-u-radu"> U radu </span></div></td>
              <td><span class="badge rounded-pill badge-light-danger text-danger"> 1 - Visoki </span></td>
            </tr>
            <tr>
              <td class="text-nowrap"><a class="fw-bold text-nowrap" href="#">26-6000-0001283</a></td>
              <td><span class="text-nowrap">26-0100-0000411</span></td>
              <td><span class="text-truncate d-inline-block w-100" title="Osovina vratila Ø40">Osovina vr..</span></td>
              <td><span class="text-nowrap">OV-040-220</span></td>
              <td class="align-middle"><span class="text-nowrap fw-semibold">48 <small class="text-muted">kom</small></span></td>
              <td><div class="d-flex justify-content-left align-items-center"><div class="avatar-wrapper"><div class="avatar me-50" style="background-color: hsla(28, 70%, 54%, 0.18); color: hsl(28, 78%, 34%); border: 1px solid hsla(28, 70%, 54%, 0.35);"><div class="avatar-content">TG</div></div></div><div class="d-flex flex-column"><h6 class="user-name text-truncate mb-0" title="TRENDY GERMANY GMBH">TRENDY GER..</h6><small class="text-truncate text-muted">Lejla K.</small></div></div></td>
              <td>06 Oct 2026</td>
              <td class="text-center align-middle"><div class="d-flex justify-content-center"><span class="badge rounded-pill status-badge status-badge-otvoren"> Otvoren </span></div></td>
              <td><span class="badge rounded-pill badge-light-warning text-warning"> 5 - Uobičajeni </span></td>
            </tr>
            <tr>
              <td class="text-nowrap"><a class="fw-bold text-nowrap" href="#">26-6000-0001282</a></td>
              <td><span class="text-nowrap">26-0100-0000409</span></td>
              <td><span class="text-truncate d-inline-block w-100" title="Nosač motora 3mm">Nosač moto..</span></td>
              <td><span class="text-nowrap">NM-300-A</span></td>
              <td class="align-middle"><span class="text-nowrap fw-semibold">2,5 <small class="text-muted">kg</small></span></td>
              <td><div class="d-flex justify-content-left align-items-center"><div class="avatar-wrapper"><div class="avatar me-50" style="background-color: hsla(140, 70%, 54%, 0.18); color: hsl(140, 78%, 34%); border: 1px solid hsla(140, 70%, 54%, 0.35);"><div class="avatar-content">KH</div></div></div><div class="d-flex flex-column"><h6 class="user-name text-truncate mb-0" title="KOVIS Hidraulika">KOVIS Hidr..</h6><small class="text-truncate text-muted"></small></div></div></td>
              <td>05 Oct 2026</td>
              <td class="text-center align-middle"><div class="d-flex justify-content-center"><span class="badge rounded-pill status-badge status-badge-planiran"> Planiran </span></div></td>
              <td><span class="badge rounded-pill badge-light-info text-info"> 10 - Niski </span></td>
            </tr>
            <tr>
              <td class="text-nowrap"><a class="fw-bold text-nowrap" href="#">26-6000-0001281</a></td>
              <td><span class="text-muted">-</span></td>
              <td><span class="text-truncate d-inline-block w-100" title="Kućište ležaja">Kućište le..</span></td>
              <td><span class="text-nowrap">KL-6205</span></td>
              <td class="align-middle"><span class="text-nowrap fw-semibold">300 <small class="text-muted">kom</small></span></td>
              <td><div class="d-flex justify-content-left align-items-center"><div class="avatar-wrapper"><div class="avatar me-50" style="background-color: hsla(212, 70%, 54%, 0.18); color: hsl(212, 78%, 34%); border: 1px solid hsla(212, 70%, 54%, 0.35);"><div class="avatar-content">GW</div></div></div><div class="d-flex flex-column"><h6 class="user-name text-truncate mb-0" title="GROB-WERKE">GROB-WERKE</h6><small class="text-truncate text-muted">Amir H.</small></div></div></td>
              <td>03 Oct 2026</td>
              <td class="text-center align-middle"><div class="d-flex justify-content-center"><span class="badge rounded-pill status-badge status-badge-rezerviran"> Rezerviran </span></div></td>
              <td><span class="badge rounded-pill badge-light-secondary "> 7 - Materijal razdužen </span></td>
            </tr>
            <tr>
              <td class="text-nowrap"><a class="fw-bold text-nowrap" href="#">26-6000-0001279</a></td>
              <td><span class="text-nowrap">26-0100-0000398</span></td>
              <td><span class="text-truncate d-inline-block w-100" title="Distantna čahura">Distantna ..</span></td>
              <td><span class="text-nowrap">DC-12-30</span></td>
              <td class="align-middle"><span class="text-nowrap fw-semibold">1.000 <small class="text-muted">kom</small></span></td>
              <td><div class="d-flex justify-content-left align-items-center"><div class="avatar-wrapper"><div class="avatar me-50" style="background-color: hsla(28, 70%, 54%, 0.18); color: hsl(28, 78%, 34%); border: 1px solid hsla(28, 70%, 54%, 0.35);"><div class="avatar-content">TG</div></div></div><div class="d-flex flex-column"><h6 class="user-name text-truncate mb-0" title="TRENDY GERMANY GMBH">TRENDY GER..</h6><small class="text-truncate text-muted">Lejla K.</small></div></div></td>
              <td>02 Oct 2026</td>
              <td class="text-center align-middle"><div class="d-flex justify-content-center"><span class="badge rounded-pill status-badge status-badge-djelimicno-zakljucen"> Djelomično zaključen </span></div></td>
              <td><span class="badge rounded-pill badge-light-warning text-warning"> 5 - Uobičajeni </span></td>
            </tr>
            <tr>
              <td class="text-nowrap"><a class="fw-bold text-nowrap" href="#">26-6000-0001275</a></td>
              <td><span class="text-nowrap">26-0100-0000390</span></td>
              <td><span class="text-truncate d-inline-block w-100" title="Ploča adaptera">Ploča adap..</span></td>
              <td><span class="text-nowrap">PA-200-10</span></td>
              <td class="align-middle"><span class="text-nowrap fw-semibold">60 <small class="text-muted">kom</small></span></td>
              <td><div class="d-flex justify-content-left align-items-center"><div class="avatar-wrapper"><div class="avatar me-50" style="background-color: hsla(300, 70%, 54%, 0.18); color: hsl(300, 78%, 34%); border: 1px solid hsla(300, 70%, 54%, 0.35);"><div class="avatar-content">MS</div></div></div><div class="d-flex flex-column"><h6 class="user-name text-truncate mb-0" title="Metalac Sarajevo">Metalac Sa..</h6><small class="text-truncate text-muted"></small></div></div></td>
              <td>30 Sep 2026</td>
              <td class="text-center align-middle"><div class="d-flex justify-content-center"><span class="badge rounded-pill status-badge status-badge-zakljucen"> Zaključen </span></div></td>
              <td><span class="badge rounded-pill badge-light-info text-info"> 15 - Uzorci </span></td>
            </tr>
          </tbody>
        </table>
        <div class="d-flex justify-content-between mx-2 row">
          <div class="col-sm-12 col-md-6"><div class="dataTables_info">Prikazano 1 do 10 od 1248 naloga</div></div>
          <div class="col-sm-12 col-md-6"><div class="dataTables_paginate paging_simple_numbers"><ul class="pagination">
            <li class="paginate_button page-item previous disabled"><a class="page-link" href="#">&nbsp;</a></li>
            <li class="paginate_button page-item active"><a class="page-link" href="#">1</a></li>
            <li class="paginate_button page-item"><a class="page-link" href="#">2</a></li>
            <li class="paginate_button page-item"><a class="page-link" href="#">3</a></li>
            <li class="paginate_button page-item"><a class="page-link" href="#">4</a></li>
            <li class="paginate_button page-item"><a class="page-link" href="#">5</a></li>
            <li class="paginate_button page-item disabled"><a class="page-link" href="#">…</a></li>
            <li class="paginate_button page-item"><a class="page-link" href="#">125</a></li>
            <li class="paginate_button page-item next"><a class="page-link" href="#">&nbsp;</a></li>
          </ul></div></div>
        </div>
      </div>
    </div>
  </div>
</section>
```

Notes for this screen:
- The `<small>` under the client name is `dodeljen_korisnik` (assigned user) and is often empty.
- List-view initials come from the regex `\b\w`, so "GROB-WERKE" gives "GW".
- The priority label comes from the DB, so it may lack the code prefix.

---

## C. AI order scan — `c:\Users\Public\Documents\trendy\resources\views\content\apps\orders\app-order-ai-scan.blade.php`

**CSS files:**
- Vendor: `vendors/css/extensions/sweetalert2.min.css` only.
- Everything else is the inline `<style>` at lines 10–2512 plus a second block at 2513–2615.
- Lines 1590–1810 hold `html.light-layout:not(...)` overrides. Most re-state the same variables, but two change the look: table `thead th` becomes `color:#607385; background: rgba(238,243,247,.8)`, and `#order-ai-result-status` becomes purple (`rgba(115,103,240,.12)` / `#7367f0`).

**Hero:** the robot is a `<dotlottie-wc>` animation at `images/order-ai/hero-robot.lottie`. Use a still image or leave the box empty. `__('locale.Skeniraj narudzbu sa AI')` resolves to the locale string; check `resources/lang/*/locale.json` for the exact wording. I used "Skeniraj narudžbu sa AI".

**Live extraction steps (`EXTRACTION_STEPS`):**
- Priprema dokumenta
- Prepoznavanje sadržaja
- Klasifikacija stavki
- Ekstrakcija podataka
- Provjera rezultata

Phase pills use `is-tone-{0..4}` (red, orange, yellow, blue, green). Their state text is `✓` (done), `U toku` (active), `Čeka` (pending) or `Greška` (error). The meta line format is `Korak 4/5 - Ekstrakcija podataka - Stranica 2/3`.

**Result-status labels:** Uploadovan, AI radi, Spremno za pregled, Spremno za transfer, Transfer u toku, Sačuvano u bazi, Neuspjelo. The idle label is "Spremno".

**Stages:** `.order-ai-stage` gets `is-done` or `is-active`, and the `.order-ai-stage-fill` element gets `style="transform:scaleX(x)"`.

**Mock state shown below:** extraction is running at step 4/5, and the result card is shown with lines. In the real flow the result card appears when extraction finishes, so show only one of the two for the true state.

```html
<style>
  .order-ai-shell { --order-ai-ink:#16344d; --order-ai-subtle:#607385; --order-ai-accent:#0e7a6b; --order-ai-border:rgba(18,52,77,.12); --order-ai-brand-a:#18cbb7; --order-ai-brand-b:#347cf7; --order-ai-brand-c:#31c46d; --order-ai-card-surface:#fff; --order-ai-card-soft:#fbfcfd; --order-ai-card-muted:#f8fafc; --order-ai-card-strong:#eef3f7; --order-ai-chip-bg:rgba(22,52,77,.08); --order-ai-panel-shadow:0 16px 32px rgba(16,31,48,.06); }
  .order-ai-initial-loader { display:none; }
  .order-ai-hero { position:relative; overflow:hidden; border:0; border-radius:1.25rem; background: radial-gradient(circle at top right, rgba(255,207,107,.3), transparent 32%), linear-gradient(135deg,#fdf7eb 0%,#fffefe 42%,#eef8f7 100%); box-shadow:0 20px 42px rgba(16,31,48,.08); }
  .order-ai-hero::after { content:""; position:absolute; inset:auto -6rem -5rem auto; width:14rem; height:14rem; border-radius:999px; background:rgba(14,122,107,.08); filter:blur(12px); }
  .order-ai-hero-grid { display:grid; grid-template-columns:minmax(0,1.12fr) minmax(320px,.88fr); gap:1rem; align-items:stretch; }
  .order-ai-hero-story { position:relative; overflow:hidden; min-height:194px; padding:1.15rem 1.35rem; border-radius:1.15rem; border:1px solid rgba(18,52,77,.08); background: linear-gradient(135deg, rgba(255,255,255,.96), rgba(241,250,247,.88)), linear-gradient(135deg, rgba(24,203,183,.08), rgba(52,124,247,.05)); box-shadow: inset 0 1px 0 rgba(255,255,255,.7); }
  .order-ai-hero-story::after { content:""; position:absolute; inset:auto auto -2rem -2rem; width:14rem; height:14rem; border-radius:999px; background: radial-gradient(circle, rgba(24,203,183,.2), rgba(52,124,247,.04) 68%, transparent 72%); filter:blur(8px); pointer-events:none; }
  .order-ai-hero-story-inner { position:relative; z-index:1; display:flex; align-items:center; min-height:100%; }
  .order-ai-hero-story.has-hero-visual .order-ai-hero-story-inner { padding-right:11.75rem; }
  .order-ai-hero-visual { position:absolute; right:.85rem; bottom:.25rem; z-index:1; width:10.75rem; height:10.75rem; display:flex; align-items:flex-end; justify-content:center; pointer-events:none; }
  .order-ai-hero-copy { max-width:28rem; padding-left:.15rem; }
  .order-ai-hero-aside { display:grid; gap:.75rem; grid-template-rows:repeat(3,minmax(0,1fr)); min-height:100%; }
  .order-ai-stat { position:relative; overflow:hidden; display:flex; flex-direction:column; justify-content:center; padding:.85rem 1rem; border-radius:1rem; border:1px solid rgba(18,52,77,.08); background:rgba(255,255,255,.8); box-shadow:0 14px 30px rgba(16,31,48,.06); }
  .order-ai-stat::after { content:""; position:absolute; inset:0 auto 0 0; width:.35rem; background:linear-gradient(180deg,var(--order-ai-brand-a),var(--order-ai-brand-b)); opacity:.7; }
  .order-ai-stat--model { background:linear-gradient(135deg, rgba(24,203,183,.14), rgba(52,124,247,.08)); border-color:rgba(24,203,183,.18); }
  .order-ai-stat-label { display:block; margin-bottom:.2rem; color:var(--order-ai-subtle); font-size:.72rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase; }
  .order-ai-stat-value { display:block; color:var(--order-ai-ink); font-size:.96rem; font-weight:700; line-height:1.25; }
  .order-ai-chip { display:inline-flex; align-items:center; gap:.45rem; padding:.55rem .85rem; border-radius:999px; background:var(--order-ai-chip-bg); color:var(--order-ai-ink); font-size:.9rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase; }
  .order-ai-dropzone { position:relative; display:flex; flex-direction:column; align-items:center; justify-content:center; flex:1 1 auto; width:100%; height:100%; min-height:330px; padding:2rem; border:2px dashed rgba(14,122,107,.32); border-radius:1.25rem; background: linear-gradient(180deg, rgba(14,122,107,.06), rgba(14,122,107,.02)), var(--order-ai-card-surface); text-align:center; cursor:pointer; }
  .order-ai-dropzone.is-dragover { border-color:var(--order-ai-accent); transform:translateY(-2px); box-shadow:0 18px 32px rgba(14,122,107,.12); }
  .order-ai-upload-stage-row { align-items:stretch; }
  #order-ai-dropzone-shell, .order-ai-progress-shell { display:flex; }
  #order-ai-dropzone-shell > .card, .order-ai-progress-shell > .card { width:100%; height:100%; }
  #order-ai-dropzone-shell > .card > .card-body { display:flex; height:100%; }
  .order-ai-dropzone-icon { width:5rem; height:5rem; border-radius:1.4rem; display:inline-flex; align-items:center; justify-content:center; margin-bottom:1.15rem; background:linear-gradient(135deg, rgba(14,122,107,.16), rgba(255,207,107,.18)); color:var(--order-ai-accent); }
  .order-ai-dropzone-icon svg { width:2.25rem; height:2.25rem; }
  .order-ai-subtle { color:var(--order-ai-subtle); }
  .order-ai-title, .order-ai-shell h3, .order-ai-shell h4, .order-ai-shell h5 { color:var(--order-ai-ink); }
  .order-ai-progress-card, .order-ai-result-card { background:var(--order-ai-card-surface); border-radius:1.1rem; border:1px solid var(--order-ai-border); box-shadow:var(--order-ai-panel-shadow); }
  .order-ai-progress-head { display:flex; align-items:flex-start; gap:.9rem; flex-wrap:nowrap; }
  .order-ai-progress-copy { flex:1 1 auto; min-width:0; }
  .order-ai-progress-status { flex:0 0 auto; align-self:center; }
  .order-ai-progress-meta { flex:0 0 auto; min-width:5rem; text-align:right; display:flex; flex-direction:column; align-items:flex-end; gap:.3rem; }
  .order-ai-progress-runtime { display:inline-flex; align-items:center; justify-content:flex-end; gap:.45rem; margin-top:.2rem; white-space:nowrap; }
  .order-ai-progress-runtime-label { color:var(--order-ai-subtle); font-size:.72rem; font-weight:700; }
  .order-ai-progress-runtime-value { color:var(--order-ai-ink); font-size:.82rem; font-weight:700; line-height:1; }
  .order-ai-activity { display:inline-flex; align-items:center; gap:.55rem; padding:.55rem .8rem; border-radius:999px; background:rgba(14,122,107,.1); color:var(--order-ai-accent); font-size:.85rem; font-weight:700; }
  .order-ai-activity .spinner-border { width:.95rem; height:.95rem; border-width:.14em; }
  .order-ai-progress-track { height:.9rem; border-radius:999px; background:var(--order-ai-card-strong); overflow:hidden; }
  .order-ai-progress-bar { height:100%; width:100%; border-radius:999px; background:linear-gradient(90deg,#0e7a6b 0%,#1ca28f 100%); transform:scaleX(0); transform-origin:left center; }
  .order-ai-stage-list { display:grid; gap:.6rem; }
  .order-ai-stage { --order-ai-stage-accent:#0e7a6b; --order-ai-stage-accent-rgb:14,122,107; position:relative; overflow:hidden; padding:.78rem .95rem; border-radius:1rem; background:var(--order-ai-card-soft); border:1px solid rgba(22,52,77,.08); }
  .order-ai-stage-fill { position:absolute; top:0; left:0; bottom:0; width:100%; background:linear-gradient(90deg, rgba(var(--order-ai-stage-accent-rgb),.24), rgba(var(--order-ai-stage-accent-rgb),.08)); transform:scaleX(0); transform-origin:left center; pointer-events:none; }
  .order-ai-stage-content { position:relative; z-index:1; display:flex; align-items:flex-start; gap:.85rem; width:100%; }
  .order-ai-stage-main { flex:1 1 auto; min-width:0; }
  .order-ai-stage-side { flex:0 0 auto; align-self:center; margin-left:auto; display:flex; justify-content:flex-end; }
  .order-ai-stage-bullet { width:.8rem; height:.8rem; border-radius:999px; background:#c5d0da; flex:0 0 auto; }
  .order-ai-stage.is-active .order-ai-stage-bullet, .order-ai-stage.is-done .order-ai-stage-bullet { background:var(--order-ai-stage-accent); box-shadow:0 0 0 .25rem rgba(var(--order-ai-stage-accent-rgb),.14); }
  .order-ai-stage.is-active { background:rgba(var(--order-ai-stage-accent-rgb),.1); border-color:rgba(var(--order-ai-stage-accent-rgb),.24); }
  .order-ai-stage.is-done { background:rgba(var(--order-ai-stage-accent-rgb),.08); border-color:rgba(var(--order-ai-stage-accent-rgb),.2); }
  .order-ai-stage.is-active[data-stage="extract"] .order-ai-stage-fill { background:linear-gradient(90deg, rgba(var(--order-ai-stage-accent-rgb),.28), rgba(var(--order-ai-stage-accent-rgb),.18), rgba(var(--order-ai-stage-accent-rgb),.08)); background-size:200% 100%; animation:order-ai-flow 2s linear infinite; }
  @keyframes order-ai-flow { 0% { background-position:200% 0; } 100% { background-position:0 0; } }
  .order-ai-extract-live { --order-ai-phase-accent:#e0585d; --order-ai-phase-accent-rgb:224,88,93; display:grid; gap:.7rem; padding:0; }
  .order-ai-extract-live[data-phase-index="1"] { --order-ai-phase-accent:#ea8f1f; --order-ai-phase-accent-rgb:234,143,31; }
  .order-ai-extract-live[data-phase-index="2"] { --order-ai-phase-accent:#d6bb25; --order-ai-phase-accent-rgb:214,187,37; }
  .order-ai-extract-live[data-phase-index="3"] { --order-ai-phase-accent:#347cf7; --order-ai-phase-accent-rgb:52,124,247; }
  .order-ai-extract-live[data-phase-index="4"] { --order-ai-phase-accent:#18a957; --order-ai-phase-accent-rgb:24,169,87; }
  .order-ai-extract-live-header { display:flex; justify-content:flex-start; align-items:center; margin-bottom:.65rem; width:100%; gap:.65rem; flex-wrap:wrap; }
  .order-ai-extract-live-meta { color:var(--order-ai-subtle); font-size:.78rem; font-weight:600; }
  .order-ai-extract-global-progress { height:.55rem; border-radius:999px; background:rgba(18,52,77,.08); overflow:hidden; }
  .order-ai-extract-global-progress-bar { display:block; width:0; height:100%; border-radius:inherit; background:linear-gradient(90deg, rgba(var(--order-ai-phase-accent-rgb),.98), rgba(var(--order-ai-phase-accent-rgb),.72)); }
  .order-ai-extract-focus { display:grid; gap:.1rem; }
  .order-ai-extract-focus-label { color:var(--order-ai-subtle); font-size:.71rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase; }
  .order-ai-extract-focus-value { color:var(--order-ai-ink); font-size:.96rem; font-weight:700; line-height:1.2; }
  .order-ai-extract-live-grid { display:grid !important; grid-template-columns:minmax(0,1fr) !important; gap:.5rem; width:100%; }
  .order-ai-extract-live-row { --order-ai-row-columns:8; display:grid; grid-template-columns:repeat(var(--order-ai-row-columns), minmax(0,1fr)); gap:.5rem; width:100%; }
  .order-ai-extract-page-chip { --order-ai-phase-accent:#e0585d; --order-ai-phase-accent-rgb:224,88,93; width:100%; height:2.15rem; padding:0 .45rem; display:grid; grid-template-columns:.82rem 1fr; align-items:center; justify-items:center; gap:.2rem; box-sizing:border-box; border-radius:999px; border:1px solid rgba(18,52,77,.1); background:rgba(255,255,255,.88); color:var(--order-ai-subtle); font-size:.8rem; font-weight:700; line-height:1; white-space:nowrap; }
  .order-ai-extract-page-chip.is-tone-1 { --order-ai-phase-accent:#ea8f1f; --order-ai-phase-accent-rgb:234,143,31; }
  .order-ai-extract-page-chip.is-tone-2 { --order-ai-phase-accent:#d6bb25; --order-ai-phase-accent-rgb:214,187,37; }
  .order-ai-extract-page-chip.is-tone-3 { --order-ai-phase-accent:#347cf7; --order-ai-phase-accent-rgb:52,124,247; }
  .order-ai-extract-page-chip.is-tone-4 { --order-ai-phase-accent:#18a957; --order-ai-phase-accent-rgb:24,169,87; }
  .order-ai-extract-page-chip.is-pending { border-color:rgba(var(--order-ai-phase-accent-rgb),.18); background:rgba(var(--order-ai-phase-accent-rgb),.08); color:var(--order-ai-phase-accent); }
  .order-ai-extract-page-chip.is-done { border-color:rgba(var(--order-ai-phase-accent-rgb),.24); background:rgba(var(--order-ai-phase-accent-rgb),.12); color:var(--order-ai-phase-accent); }
  .order-ai-extract-page-chip.is-active { border-color:rgba(var(--order-ai-phase-accent-rgb),.28); background:linear-gradient(135deg, rgba(var(--order-ai-phase-accent-rgb),.18), rgba(255,255,255,.96)); color:var(--order-ai-phase-accent); box-shadow:0 10px 18px rgba(var(--order-ai-phase-accent-rgb),.16); }
  .order-ai-extract-page-chip-state { width:.82rem; min-width:.82rem; display:inline-flex; align-items:center; justify-content:center; font-size:.72rem; line-height:1; border-radius:999px; }
  .order-ai-extract-page-chip-state.is-empty { visibility:hidden; }
  .order-ai-extract-page-chip-number { min-width:0; text-align:center; font-variant-numeric:tabular-nums; }
  .order-ai-extract-phase-list { display:flex; flex-wrap:wrap; gap:.5rem; }
  .order-ai-extract-phase { --order-ai-phase-rgb:224,88,93; --order-ai-phase-color:#e0585d; display:inline-flex; align-items:center; gap:.45rem; padding:.42rem .68rem; border-radius:999px; border:1px solid rgba(var(--order-ai-phase-rgb),.18); background:rgba(var(--order-ai-phase-rgb),.08); color:var(--order-ai-phase-color); font-size:.72rem; font-weight:700; line-height:1.2; white-space:nowrap; }
  .order-ai-extract-phase.is-tone-1 { --order-ai-phase-rgb:234,143,31; --order-ai-phase-color:#ea8f1f; }
  .order-ai-extract-phase.is-tone-2 { --order-ai-phase-rgb:214,187,37; --order-ai-phase-color:#c89f17; }
  .order-ai-extract-phase.is-tone-3 { --order-ai-phase-rgb:52,124,247; --order-ai-phase-color:#347cf7; }
  .order-ai-extract-phase.is-tone-4 { --order-ai-phase-rgb:24,169,87; --order-ai-phase-color:#18a957; }
  .order-ai-extract-phase-name { font-weight:700; }
  .order-ai-extract-phase-state { display:inline-flex; align-items:center; justify-content:center; min-width:1.15rem; height:1.15rem; padding:0 .22rem; border-radius:999px; background:rgba(var(--order-ai-phase-rgb),.14); font-size:.7rem; white-space:nowrap; }
  .order-ai-extract-phase.is-done { border-color:rgba(var(--order-ai-phase-rgb),.28); background:rgba(var(--order-ai-phase-rgb),.14); box-shadow:0 8px 16px rgba(var(--order-ai-phase-rgb),.12); }
  .order-ai-extract-phase.is-active { border-color:rgba(var(--order-ai-phase-rgb),.32); background:linear-gradient(135deg, rgba(var(--order-ai-phase-rgb),.18), rgba(255,255,255,.96)); box-shadow:0 10px 18px rgba(var(--order-ai-phase-rgb),.14); transform:translateY(-1px); }
  .order-ai-extract-phase.is-pending { opacity:.78; }
  .order-ai-primary-action { min-width:170px; min-height:3.15rem; display:inline-flex; align-items:center; justify-content:center; padding:.8rem 1.2rem; border:1px solid rgba(14,122,107,.16); border-radius:.9rem; background:rgba(14,122,107,.08); color:#166458; font-weight:700; line-height:1.15; white-space:nowrap; box-shadow:0 12px 24px rgba(14,122,107,.08); }
  .order-ai-primary-action:disabled { background:rgba(133,148,163,.16); border-color:rgba(133,148,163,.18); color:#8da0b0; box-shadow:none; }
  .order-ai-transfer-cta { min-width:170px; min-height:3.15rem; display:inline-flex; align-items:center; justify-content:center; padding:.8rem 1.35rem; border-radius:.9rem; font-weight:700; line-height:1.15; white-space:nowrap; background:linear-gradient(180deg,#4aa075 0%,#397f62 100%); border-color:#397f62; box-shadow:0 14px 28px rgba(57,127,98,.18); }
  .order-ai-bottom-actions { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:-1rem; padding-top:.7rem; padding-bottom:.7rem; border-top:1px solid rgba(22,52,77,.08); }
  .order-ai-bottom-actions-secondary { display:flex; align-items:center; gap:.75rem; flex-wrap:wrap; }
  .order-ai-bottom-action-primary { margin-left:auto; flex:0 0 auto; }
  .order-ai-secondary-action { min-height:3.15rem; display:inline-flex; align-items:center; justify-content:center; padding:.8rem 1.35rem; border-radius:.9rem; border:1px solid rgba(74,160,117,.24); background:transparent; color:var(--order-ai-ink); font-weight:600; line-height:1.15; white-space:nowrap; }
  .order-ai-secondary-action--accent { background:linear-gradient(135deg, rgba(14,122,107,.16), rgba(28,162,143,.24)); border-color:rgba(14,122,107,.42); box-shadow:0 12px 24px rgba(14,122,107,.12); }
  #order-ai-lines-shell { margin-bottom:.5rem !important; }
  .order-ai-facts { display:grid; grid-template-columns:repeat(8,minmax(0,1fr)); gap:.85rem; }
  .order-ai-fact { padding:1rem; border-radius:1rem; background:linear-gradient(180deg,var(--order-ai-card-surface) 0%, var(--order-ai-card-muted) 100%); border:1px solid rgba(22,52,77,.08); }
  .order-ai-fact.is-match { border-color:rgba(22,163,74,.24); background:rgba(22,163,74,.08); color:#17683b; }
  .order-ai-lines-table td, .order-ai-lines-table th { white-space:nowrap; vertical-align:middle; }
  .order-ai-lines-table td.order-ai-wrap, .order-ai-lines-table th.order-ai-wrap { white-space:normal; }
  .order-ai-lines-table td.order-ai-weight-cell, .order-ai-lines-table th.order-ai-weight-cell { min-width:7rem; width:7rem; }
  .order-ai-weight-input { min-width:6.5rem; max-width:7rem; padding-inline:.55rem; text-align:right; }
  .order-ai-line-code-stack { display:flex; flex-direction:column; align-items:flex-start; gap:.35rem; }
  .order-ai-line-badge { display:inline-flex; align-items:center; gap:.35rem; padding:.2rem .55rem; border:1px solid transparent; border-radius:999px; font-size:.72rem; font-weight:700; line-height:1.15; letter-spacing:.02em; white-space:nowrap; }
  .order-ai-line-badge.is-missing { background:rgba(255,159,67,.14); border-color:rgba(245,158,11,.28); color:#b45309; }
  .order-ai-line-row.is-catalog-missing > td { background:rgba(255,159,67,.1); }
  .order-ai-line-name { font-weight:600; }
  .order-ai-line-edit-trigger { width:100%; padding:0; border:0; background:transparent; color:inherit; text-align:left; font:inherit; line-height:inherit; cursor:pointer; }
  .order-ai-line-edit-trigger--compact { white-space:nowrap; }
  .order-ai-line-total-trigger { width:100%; border:1px solid rgba(22,52,77,.12); border-radius:.9rem; background:var(--order-ai-card-muted); padding:.7rem .8rem; text-align:left; }
  .order-ai-line-total-trigger.is-match { border-color:rgba(22,163,74,.28); background:rgba(22,163,74,.08); color:#17683b; }
  .order-ai-line-total-trigger.is-mismatch { border-color:rgba(220,38,38,.25); background:rgba(220,38,38,.08); color:#8b1e1e; }
  .order-ai-line-total-meta { display:grid; gap:.15rem; }
  .order-ai-line-total-computed { font-size:.78rem; opacity:.8; }
  .order-ai-line-total-source { font-size:.94rem; font-weight:700; }
  .order-ai-line-total-diff { font-size:.76rem; font-weight:700; letter-spacing:.01em; }
  .order-ai-shell .table thead th { color:#607385; background:rgba(238,243,247,.8); }
  .order-ai-shell .text-muted { color:var(--order-ai-subtle) !important; }
  #order-ai-result-status { background:rgba(115,103,240,.12) !important; color:#7367f0 !important; border:1px solid rgba(115,103,240,.18); }
  .order-ai-hidden { display:none !important; }
</style>

<section class="order-ai-shell" id="order-ai-app">
  <div class="row">
    <div class="col-12">
      <div class="card order-ai-hero mb-2">
        <div class="card-body p-2 p-md-3">
          <div class="order-ai-hero-grid">
            <div class="order-ai-hero-story has-hero-visual">
              <div class="order-ai-hero-story-inner">
                <div class="order-ai-hero-copy">
                  <span class="order-ai-chip mb-1"><i class="fa fa-magic" aria-hidden="true"></i> Skeniraj narudžbu sa AI</span>
                  <h2 class="mb-75 order-ai-title">Skeniraj narudžbu sa AI</h2>
                  <p class="mb-0 order-ai-subtle" style="max-width:720px;">Ubaci PDF, sliku ili izvoz dokumenta. Dokument se zadržava na istoj stranici, AI obrada se izvršava, a upis u bazu se pokreće tek nakon ručne potvrde transfera.</p>
                </div>
                <div class="order-ai-hero-visual" aria-hidden="true"><!-- robot lottie / image --></div>
              </div>
            </div>
            <div class="order-ai-hero-aside">
              <div class="order-ai-stat"><span class="order-ai-stat-label">Provider</span><span class="order-ai-stat-value">qla.dev</span></div>
              <div class="order-ai-stat order-ai-stat--model"><span class="order-ai-stat-label">Model</span><span class="order-ai-stat-value">TrendyGPT 1.0</span></div>
              <div class="order-ai-stat"><span class="order-ai-stat-label">Status transfera</span><span class="order-ai-stat-value">Ručni transfer aktivan</span></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="row g-2 order-ai-upload-stage-row mb-2">
        <div class="col-lg-7 col-12" id="order-ai-dropzone-shell">
          <div class="card border-0 shadow-sm h-100"><div class="card-body p-2 p-md-3">
            <div class="order-ai-dropzone" id="order-ai-dropzone" role="button">
              <div class="order-ai-dropzone-icon"><i data-feather="upload-cloud"></i></div>
              <h3 class="mb-75">Prevuci dokument ovdje</h3>
              <p class="order-ai-subtle mb-1">ili se klikom odabire fajl za AI obradu narudžbe</p>
              <small class="text-muted">PDF i Word dokumenti (.doc, .docx) do 50 MB</small>
            </div>
          </div></div>
        </div>

        <div class="col-lg-5 col-12 order-ai-progress-shell" id="order-ai-progress-shell">
          <div class="card order-ai-progress-card"><div class="card-body p-2">
            <div class="order-ai-progress-head mb-1">
              <div class="order-ai-progress-copy">
                <h4 class="mb-25">Status obrade</h4>
                <p class="mb-0 order-ai-subtle" id="order-ai-progress-label">Ekstrakcija podataka</p>
              </div>
              <div class="order-ai-progress-status">
                <span class="order-ai-activity"><span class="spinner-border spinner-border-sm" role="status"></span><span>AI ekstrakcija radi...</span></span>
              </div>
              <div class="order-ai-progress-meta">
                <div class="fw-bolder">68%</div>
                <small class="text-muted">Bestellung_4500123456.pdf</small>
                <span class="order-ai-progress-runtime"><span class="order-ai-progress-runtime-label">Proteklo vrijeme:</span><span class="order-ai-progress-runtime-value">42s</span></span>
              </div>
            </div>
            <div class="order-ai-progress-track mb-2"><div class="order-ai-progress-bar" style="transform:scaleX(.68)"></div></div>
            <div class="order-ai-stage-list">
              <div class="order-ai-stage is-done" data-stage="upload">
                <div class="order-ai-stage-fill" style="transform:scaleX(1)"></div>
                <div class="order-ai-stage-content"><span class="order-ai-stage-bullet"></span>
                  <div class="order-ai-stage-main"><strong>Upload</strong><div class="small text-muted">Fajl se šalje u lokalni prihvat.</div></div></div>
              </div>
              <div class="order-ai-stage is-active" data-stage="extract">
                <div class="order-ai-stage-fill" style="transform:scaleX(.62)"></div>
                <div class="order-ai-stage-content"><span class="order-ai-stage-bullet"></span>
                  <div class="order-ai-stage-main"><strong>AI ekstrakcija</strong><div class="small text-muted">Dokument se čita i pretvara se u preglednu narudžbu za provjeru.</div></div></div>
              </div>
              <div class="order-ai-stage" data-stage="transfer">
                <div class="order-ai-stage-fill"></div>
                <div class="order-ai-stage-content"><span class="order-ai-stage-bullet"></span>
                  <div class="order-ai-stage-main"><strong>Transfer u bazu</strong><div class="small text-muted">Akcije su na dnu stranice. Nakon završetka obrade omogućava se upis u bazu.</div></div>
                  <div class="order-ai-stage-side"><button type="button" class="btn order-ai-primary-action" disabled>Poduzmi akciju</button></div></div>
              </div>
            </div>
          </div></div>
        </div>
      </div>

      <!-- live extraction panel (shown while status is extracting) -->
      <div class="row g-2 mb-2" id="order-ai-extract-live-shell">
        <div class="col-12"><div class="card order-ai-progress-card"><div class="card-body p-2 p-md-3">
          <div class="order-ai-extract-live" data-phase-index="3">
            <div class="order-ai-extract-live-header"><span class="order-ai-extract-live-meta">Korak 4/5 - Ekstrakcija podataka - Stranica 2/3</span></div>
            <div class="order-ai-extract-global-progress"><span class="order-ai-extract-global-progress-bar" style="width:46%"></span></div>
            <div class="order-ai-extract-focus"><span class="order-ai-extract-focus-label">Tok obrade</span><strong class="order-ai-extract-focus-value">Ekstrakcija podataka</strong></div>
            <div class="order-ai-extract-live-grid">
              <div class="order-ai-extract-live-row" style="--order-ai-row-columns:3;">
                <span class="order-ai-extract-page-chip is-tone-3 is-done" title="Stranica 1"><span class="order-ai-extract-page-chip-state">✓</span><span class="order-ai-extract-page-chip-number">1</span></span>
                <span class="order-ai-extract-page-chip is-tone-3 is-active" title="Stranica 2"><span class="order-ai-extract-page-chip-state">⟳</span><span class="order-ai-extract-page-chip-number">2</span></span>
                <span class="order-ai-extract-page-chip is-tone-2 is-pending" title="Stranica 3"><span class="order-ai-extract-page-chip-state is-empty">.</span><span class="order-ai-extract-page-chip-number">3</span></span>
              </div>
            </div>
            <div class="order-ai-extract-phase-list">
              <div class="order-ai-extract-phase is-tone-0 is-done"><span class="order-ai-extract-phase-name">Priprema dokumenta</span><span class="order-ai-extract-phase-state">✓</span></div>
              <div class="order-ai-extract-phase is-tone-1 is-done"><span class="order-ai-extract-phase-name">Prepoznavanje sadržaja</span><span class="order-ai-extract-phase-state">✓</span></div>
              <div class="order-ai-extract-phase is-tone-2 is-done"><span class="order-ai-extract-phase-name">Klasifikacija stavki</span><span class="order-ai-extract-phase-state">✓</span></div>
              <div class="order-ai-extract-phase is-tone-3 is-active"><span class="order-ai-extract-phase-name">Ekstrakcija podataka</span><span class="order-ai-extract-phase-state">U toku</span></div>
              <div class="order-ai-extract-phase is-tone-4 is-pending"><span class="order-ai-extract-phase-name">Provjera rezultata</span><span class="order-ai-extract-phase-state">Čeka</span></div>
            </div>
          </div>
        </div></div></div>
      </div>

      <!-- result card (shown after extraction) -->
      <div class="row g-2"><div class="col-12">
        <div class="card order-ai-result-card" id="order-ai-result-card"><div class="card-body p-2">
          <div class="d-flex flex-wrap align-items-start justify-content-between gap-1 mb-2">
            <div><h4 class="mb-25">Rezultat AI skena</h4><p class="mb-0 order-ai-subtle">Ekstrakcija završena. Provjerite stavke prije transfera.</p></div>
            <span class="badge rounded-pill bg-light-primary text-primary" id="order-ai-result-status">Spremno za transfer</span>
          </div>
          <div class="order-ai-facts mb-2">
            <div class="order-ai-fact"><div class="text-muted small mb-50">Kupac</div><div class="fw-bolder">GROB-WERKE</div></div>
            <div class="order-ai-fact"><div class="text-muted small mb-50">Naručilac</div><div class="fw-bolder">GROB-WERKE GmbH &amp; Co. KG</div></div>
            <div class="order-ai-fact"><div class="text-muted small mb-50">Referenca</div><div class="fw-bolder">4500123456</div></div>
            <div class="order-ai-fact"><div class="text-muted small mb-50">Vrsta dokumenta</div><div class="fw-bolder">Bestellung</div></div>
            <div class="order-ai-fact"><div class="text-muted small mb-50">Valuta</div><div class="fw-bolder">EUR</div></div>
            <div class="order-ai-fact is-match"><div class="text-muted small mb-50">Iznos</div><div class="fw-bolder">4820.00</div></div>
            <div class="order-ai-fact"><div class="text-muted small mb-50">AI tokeni</div><div class="fw-bolder">18342</div></div>
            <div class="order-ai-fact"><div class="text-muted small mb-50">Pantheon ključ</div><div class="fw-bolder">-</div></div>
          </div>
          <div class="table-responsive mb-2" id="order-ai-lines-shell">
            <table class="table table-sm order-ai-lines-table mb-0">
              <thead><tr><th>#</th><th>Šifra</th><th class="order-ai-wrap">Naziv</th><th>Količina</th><th>JM</th><th>Jed. cijena</th><th>Rok isporuke</th><th class="order-ai-wrap">Total provjera</th><th class="order-ai-weight-cell">Težina</th></tr></thead>
              <tbody>
                <tr>
                  <td>10</td>
                  <td><button type="button" class="order-ai-line-edit-trigger"><div class="order-ai-line-code-stack"><span>0001234567</span></div></button></td>
                  <td class="order-ai-wrap"><button type="button" class="order-ai-line-edit-trigger"><div class="order-ai-line-name">Flansch DN80 PN16</div></button></td>
                  <td><button type="button" class="order-ai-line-edit-trigger order-ai-line-edit-trigger--compact"><span>120.00</span></button></td>
                  <td><button type="button" class="order-ai-line-edit-trigger order-ai-line-edit-trigger--compact"><span>ST</span></button></td>
                  <td><button type="button" class="order-ai-line-edit-trigger order-ai-line-edit-trigger--compact"><span>18.50</span></button></td>
                  <td>14.11.2026</td>
                  <td class="order-ai-wrap"><button type="button" class="order-ai-line-total-trigger is-match"><span class="order-ai-line-total-meta"><span class="order-ai-line-total-source">Skenirani total: 2220.00 EUR</span><span class="order-ai-line-total-computed">18.50 x 120.00 = 2220.00 EUR</span><span class="order-ai-line-total-diff">Razlika: 0.00 EUR</span></span></button></td>
                  <td class="order-ai-weight-cell"><input type="text" class="form-control form-control-sm order-ai-weight-input" value="1,250"></td>
                </tr>
                <tr>
                  <td>20</td>
                  <td><button type="button" class="order-ai-line-edit-trigger"><div class="order-ai-line-code-stack"><span>0001234590</span></div></button></td>
                  <td class="order-ai-wrap"><button type="button" class="order-ai-line-edit-trigger"><div class="order-ai-line-name">Welle Ø40 x 220</div></button></td>
                  <td><button type="button" class="order-ai-line-edit-trigger order-ai-line-edit-trigger--compact"><span>48.00</span></button></td>
                  <td><button type="button" class="order-ai-line-edit-trigger order-ai-line-edit-trigger--compact"><span>ST</span></button></td>
                  <td><button type="button" class="order-ai-line-edit-trigger order-ai-line-edit-trigger--compact"><span>35.00</span></button></td>
                  <td>21.11.2026</td>
                  <td class="order-ai-wrap"><button type="button" class="order-ai-line-total-trigger is-match"><span class="order-ai-line-total-meta"><span class="order-ai-line-total-source">Skenirani total: 1680.00 EUR</span><span class="order-ai-line-total-computed">35.00 x 48.00 = 1680.00 EUR</span><span class="order-ai-line-total-diff">Razlika: 0.00 EUR</span></span></button></td>
                  <td class="order-ai-weight-cell"><input type="text" class="form-control form-control-sm order-ai-weight-input" value="2,700"></td>
                </tr>
                <tr class="order-ai-line-row is-catalog-missing">
                  <td>30</td>
                  <td><button type="button" class="order-ai-line-edit-trigger"><div class="order-ai-line-code-stack"><span>0001239011</span><span class="order-ai-line-badge is-missing">Nije u bazi</span></div></button></td>
                  <td class="order-ai-wrap"><button type="button" class="order-ai-line-edit-trigger"><div class="order-ai-line-name">Distanzhülse 12x30</div><div class="order-ai-line-note">Primarna klasifikacija: Tokarenje</div></button></td>
                  <td><button type="button" class="order-ai-line-edit-trigger order-ai-line-edit-trigger--compact"><span>400.00</span></button></td>
                  <td><button type="button" class="order-ai-line-edit-trigger order-ai-line-edit-trigger--compact"><span>ST</span></button></td>
                  <td><button type="button" class="order-ai-line-edit-trigger order-ai-line-edit-trigger--compact"><span>2.30</span></button></td>
                  <td>28.11.2026</td>
                  <td class="order-ai-wrap"><button type="button" class="order-ai-line-total-trigger is-match"><span class="order-ai-line-total-meta"><span class="order-ai-line-total-source">Skenirani total: 920.00 EUR</span><span class="order-ai-line-total-computed">2.30 x 400.00 = 920.00 EUR</span><span class="order-ai-line-total-diff">Razlika: 0.00 EUR</span></span></button></td>
                  <td class="order-ai-weight-cell"><input type="text" class="form-control form-control-sm order-ai-weight-input" value=""></td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="order-ai-bottom-actions" id="order-ai-actions">
            <div class="order-ai-bottom-actions-secondary">
              <a href="#" class="btn order-ai-secondary-action">Vidi PDF</a>
              <button type="button" class="btn order-ai-secondary-action order-ai-secondary-action--accent">Nova narudžba</button>
              <a href="#" class="btn order-ai-secondary-action">Historija AI skeniranja</a>
              <a href="#" class="btn order-ai-secondary-action">Moje narudžbe</a>
              <button type="button" class="btn order-ai-secondary-action">Ponovi scan</button>
            </div>
            <div class="order-ai-bottom-action-primary">
              <button type="button" class="btn btn-success order-ai-transfer-cta is-ready">Transfer u bazu</button>
            </div>
          </div>
        </div></div>
      </div></div>
    </div>
  </div>
</section>
```

Notes for this screen:
- **Idle state:** the extract-live shell and the result card both have `order-ai-hidden`. The progress label reads "Čekam upload...", the percentage is "0%", the elapsed time is "0s", and the activity pill is hidden.
- **Amounts:** formatted with `toFixed(2)` and a dot (e.g. `2220.00`). AI tokens are a plain integer.
- **Transfer button:** disabled it uses a grey gradient (`#dce3ea → #cfd7df`, text `#7f8d9b`). After transfer it reads "Prebačeno u bazu" (`is-complete`).
- **Mismatched amounts:** the Iznos fact gets `is-mismatch` and the meta line `Razlika: x`.
- **Facts row:** the 8-column grid is on purpose.

---

## D. Production plan — `c:\Users\Public\Documents\trendy\resources\views\content\apps\production\app-production-plan.blade.php` + `c:\Users\Public\Documents\trendy\resources\js\scripts\pages\app-production-plan.js`

**CSS files:**
- Vendor: `vendors/css/forms/select/select2.min.css`, `vendors/css/tables/datatable/dataTables.bootstrap5.min.css`, `vendors/css/pickers/flatpickr/flatpickr.min.css`, `vendors/css/extensions/sweetalert2.min.css`
- Plus the inline `<style>` below.

**Page behaviour:**
- The filter body (`#tijelo-filtera`) and column panel are `d-none` by default, so the screen opens with just the header card and the table.
- The loading overlay starts as `is-visible`; drop that class for the static mock.
- Hidden by default: `izr_kol` (Izr. kol.) and `status_rn` (Status RN). So 17 header cells are visible, the first one an empty expand column.
- DataTables uses the default Bootstrap 5 layout: `.row > col-sm-12 col-md-6` for length and search, then the table, then info and pagination. Labels: "Prikaži _MENU_ redova", "Pretraga:", "Prikaz _START_ do _END_ od _TOTAL_ radnih naloga", "Prethodna" / "Sljedeća". Page length is 25, sorted by Poč. termin descending.

**Row colour by priority (`priority_row_color`):**
- 1 Visoki → `--red`
- 5 Uobičajeni → `--yellow`
- 7 Materijal razdužen → `--teal`
- 10 Niski → `--green`
- 15 Uzorci → `--purple`
- anything else → `--grey`
- open orders left over from last week → `--red`

Editable cells are wrapped in `<span class="editable-cell">`. Progress is shown as `<b>NN%</b>`.

```html
<style>
  .production-plan-table .plan-expand-cell { width: 28px; padding: .25rem; }
  .plan-expand-button { display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border: 0; border-radius: .35rem; background: transparent; color: inherit; }
  .plan-expand-button:hover { background: rgba(94, 88, 115, .12); }
  .plan-expand-button i { transition: transform .15s ease; font-size: .7rem; }
  .plan-expand-button[aria-expanded='true'] i { transform: rotate(90deg); }
  .production-plan-table .plan-detail-row > td { padding: 0; background: #f8f8f8; border-bottom: 1px solid #d8d6de; }
  .plan-expanded-details { box-sizing: border-box; width: min(100%, var(--production-plan-visible-width, 80vw)); padding: .75rem 1rem; white-space: normal; }
  .plan-expanded-details h6 { font-size: .75rem; margin-bottom: .5rem; }
  .plan-detail-scroll { max-width: 100%; overflow-x: auto; }
  .plan-operations-flow { display: flex; flex-wrap: nowrap; list-style: none; padding: 0; margin: 0; width: max-content; }
  .plan-operations-flow li { display: flex; align-items: center; flex: 0 0 auto; }
  .plan-operations-flow li:not(:last-child)::after { content: ''; width: 2rem; height: 1px; margin: 0 .75rem; background: #d8d6de; }
  .plan-operation-circle { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 auto; width: 26px; height: 26px; border: 2px solid currentColor; border-radius: 50%; margin-right: .5rem; }
  .plan-operation-circle--finished { color: #28a745; background: rgba(40, 167, 69, .12); }
  .plan-operation-circle--unfinished { color: #e69a19; background: rgba(230, 154, 25, .12); }
  .plan-operations-flow strong { font-size: .8rem; }
  .plan-operations-flow small { display: block; font-size: .7rem; color: inherit; }
  .production-plan-table { width: max-content !important; min-width: 100%; }
  .production-plan-table > :not(caption) > * > * { padding: .42rem .5rem; font-size: .8rem; white-space: nowrap; }
  .production-plan-table thead th { max-width: 7rem; white-space: normal; line-height: 1.2; }
  table.production-plan-table.dataTable > thead > tr > th[class*="sorting"]::before { top: calc(50% - .5rem); right: .45em; bottom: auto; transform: translateY(-50%); }
  table.production-plan-table.dataTable > thead > tr > th[class*="sorting"]::after { top: calc(50% + .5rem); right: .45em; bottom: auto; transform: translateY(-50%); }
  .production-plan-table thead th:nth-child(9):not(.sorting_disabled),
  .production-plan-table thead th:nth-child(13):not(.sorting_disabled) { padding-right: 20px; }
  .production-plan-table .editable-cell { cursor: pointer; }
  .production-plan-table .production-plan-note-preview { display: inline-block; max-width: 16rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; vertical-align: bottom; }
  .production-plan-table tr.production-plan-row--red > td { background-color: #ffd6dc !important; color: #7a0014; }
  .production-plan-table tr.production-plan-row--yellow > td { background-color: #fff0a3 !important; color: #5f4500; }
  .production-plan-table tr.production-plan-row--orange > td { background-color: #ffd1aa !important; color: #792b00; }
  .production-plan-table tr.production-plan-row--purple > td { background-color: #e8d4ff !important; color: #4f167f; }
  .production-plan-table tr.production-plan-row--teal > td { background-color: #bcefe5 !important; color: #005c4c; }
  .production-plan-table tr.production-plan-row--green > td { background-color: #c5f1d2 !important; color: #075e2a; }
  .production-plan-table tr.production-plan-row--grey > td { background-color: #dde2e8 !important; color: #39424e; }
  .production-plan-table tr[class*='production-plan-row--'] > td:first-child { box-shadow: inset 4px 0 0 currentColor; }
  .production-plan-table-overlay-host { position: relative; isolation: isolate; }
  .production-plan-table-loading-overlay { position: absolute; inset: 0; display: none; align-items: center; justify-content: center; min-height: 220px; background: rgba(255, 255, 255, .74); backdrop-filter: blur(1px); z-index: 30; pointer-events: none; }
  .production-plan-table-loading-overlay.is-visible { display: flex; }
  #btn-izvoz-plana { color: #5e5873; border-color: currentColor; cursor: pointer; }
  #btn-izvoz-plana:hover, #btn-izvoz-plana:focus-visible { background-color: rgba(94, 88, 115, .12); }
  .production-plan-wrapper .card-datatable.table-responsive { overflow: visible; }
  .production-plan-wrapper .card-datatable .dataTables_wrapper > .row:first-child,
  .production-plan-wrapper .card-datatable .dataTables_wrapper > .row:last-child { margin-right: 0; margin-left: 0; padding: 1rem; }
  .production-plan-wrapper .card-datatable .dataTables_wrapper > .row:first-child > [class*='col-'],
  .production-plan-wrapper .card-datatable .dataTables_wrapper > .row:last-child > [class*='col-'] { padding-right: 0; padding-left: 0; }
  .production-plan-wrapper .card-datatable .dataTables_wrapper > .row:nth-child(2) { margin-right: 0; margin-left: 0; overflow-x: auto; }
  .production-plan-wrapper .card-datatable .dataTables_wrapper > .row:nth-child(2) > [class*='col-'] { min-width: max-content; padding-right: 0; padding-left: 0; }
  @media (min-width: 768px) {
    .production-plan-wrapper .card-datatable .dataTables_wrapper > .row:first-child { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
    .production-plan-wrapper .card-datatable .dataTables_wrapper > .row:first-child > [class*='col-']:first-child { flex: 1 1 auto; width: auto; max-width: none; }
    .production-plan-wrapper .card-datatable .dataTables_wrapper > .row:first-child > [class*='col-']:last-child { flex: 0 0 auto; width: auto; max-width: none; margin-left: auto; }
  }
  .production-plan-fullscreen-toolbar { display: none; }
</style>

<section id="rn-plan">
  <div class="content-header row"><div class="col-12 mb-2 d-flex justify-content-between align-items-center flex-wrap gap-1">
    <h2 class="mb-0">Plan proizvodnje — Radni nalozi</h2>
    <div class="d-flex flex-wrap gap-1">
      <button type="button" class="btn btn-outline-secondary" id="btn-fullscreen-plana"><i data-feather="maximize" class="me-50"></i>Prikaz preko cijelog ekrana</button>
      <button type="button" class="btn" id="btn-izvoz-plana"><i data-feather="download" class="me-50"></i>Izvoz u Excel</button>
    </div>
  </div></div>

  <div class="card mb-2">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h4 class="mb-0">Filter plana proizvodnje</h4>
      <div class="d-flex align-items-center flex-wrap gap-2">
        <button type="button" class="btn btn-outline-primary btn-sm" id="btn-kolone-plana"><i data-feather="columns" class="me-50"></i>Filter kolona</button>
        <button type="button" class="btn btn-outline-primary btn-sm" id="btn-prikazi-filtere"><i data-feather="filter" class="me-50"></i>Prikaži filtere</button>
        <button class="btn btn-outline-danger btn-sm" id="btn-obrisi-filter"><i data-feather="trash-2" class="me-50"></i>Obriši filter</button>
      </div>
    </div>
    <!-- #tijelo-filtera and #tijelo-kolona-plana are d-none by default -->
  </div>

  <div class="card production-plan-wrapper production-plan-table-overlay-host" id="production-plan-list">
    <div class="card-datatable table-responsive">
      <div class="dataTables_wrapper dt-bootstrap5 no-footer">
        <div class="row">
          <div class="col-sm-12 col-md-6"><div class="dataTables_length"><label>Prikaži <select class="form-select form-select-sm"><option>25</option></select> redova</label></div></div>
          <div class="col-sm-12 col-md-6"><div class="dataTables_filter"><label>Pretraga:<input type="search" class="form-control form-control-sm"></label></div></div>
        </div>
        <div class="row dt-row"><div class="col-sm-12">
          <table class="table production-plan-table dataTable" id="plan-proizvodnje-tabela">
            <thead><tr>
              <th aria-label="Detalji" class="plan-expand-cell sorting_disabled"></th><th class="sorting_disabled">%</th><th class="sorting">RN</th><th class="sorting">Naručitelj</th><th class="sorting">Prioritet</th><th class="sorting">Datum</th><th class="sorting">Narudžba</th><th class="sorting">Br. narudžbe <br>kupca</th><th class="sorting">Br. <br>poz.</th><th class="sorting sorting_desc">Poč. <br>termin</th><th class="sorting">Kraj <br>termin</th><th class="sorting">Datum <br>isporuke</th><th class="sorting">Proizvod</th><th class="sorting">Plan. <br>kol.</th><th class="sorting">Naziv</th><th class="sorting">Nositelj troška</th><th class="sorting">Napomena</th>
            </tr></thead>
            <tbody>
              <tr class="production-plan-row--red">
                <td class="plan-expand-cell"><button type="button" class="plan-expand-button" aria-expanded="false"><i class="fa fa-chevron-right"></i></button></td>
                <td><b>35%</b></td><td>26-6000-0001284</td><td><span class="editable-cell"><span title="GROB-WERKE">GW</span></span></td><td>Visoki prioritet</td><td><span class="editable-cell">01.10.2026</span></td><td><span class="editable-cell">26-0100-0000412</span></td><td>4500123456</td><td><span class="editable-cell">10</span></td><td><span class="editable-cell">06.10.2026</span></td><td><span class="editable-cell">09.10.2026</span></td><td>14.10.2026</td><td><span class="editable-cell">PR-080-16</span></td><td><span class="editable-cell">120</span></td><td><span class="editable-cell">Prirubnica DN80 PN16</span></td><td><span class="editable-cell">CNC-1</span></td><td><span class="editable-cell"><span class="production-plan-note-preview" title="Hitno – kupac čeka">Hitno – kupac čeka</span></span></td>
              </tr>
              <tr class="production-plan-row--yellow">
                <td class="plan-expand-cell"><button type="button" class="plan-expand-button" aria-expanded="false"><i class="fa fa-chevron-right"></i></button></td>
                <td><b>60%</b></td><td>26-6000-0001283</td><td><span class="editable-cell"><span title="TRENDY GERMANY GMBH 2">TG GmbH 2</span></span></td><td>Uobičajeni prioritet</td><td><span class="editable-cell">30.09.2026</span></td><td><span class="editable-cell">26-0100-0000411</span></td><td>PO-88213</td><td><span class="editable-cell">20</span></td><td><span class="editable-cell">05.10.2026</span></td><td><span class="editable-cell">08.10.2026</span></td><td>16.10.2026</td><td><span class="editable-cell">OV-040-220</span></td><td><span class="editable-cell">48</span></td><td><span class="editable-cell">Osovina vratila Ø40</span></td><td><span class="editable-cell">TOK-2</span></td><td><span class="editable-cell"><span class="production-plan-note-preview" title=""></span></span></td>
              </tr>
              <tr class="production-plan-row--teal">
                <td class="plan-expand-cell"><button type="button" class="plan-expand-button" aria-expanded="false"><i class="fa fa-chevron-right"></i></button></td>
                <td><b>80%</b></td><td>26-6000-0001281</td><td><span class="editable-cell"><span title="GROB-WERKE">GW</span></span></td><td>Materijal razdužen</td><td><span class="editable-cell">28.09.2026</span></td><td><span class="editable-cell">26-0100-0000405</span></td><td>4500122987</td><td><span class="editable-cell">30</span></td><td><span class="editable-cell">03.10.2026</span></td><td><span class="editable-cell">07.10.2026</span></td><td>12.10.2026</td><td><span class="editable-cell">KL-6205</span></td><td><span class="editable-cell">300</span></td><td><span class="editable-cell">Kućište ležaja</span></td><td><span class="editable-cell">CNC-3</span></td><td><span class="editable-cell"><span class="production-plan-note-preview" title="Materijal izdan">Materijal izdan</span></span></td>
              </tr>
              <tr class="production-plan-row--green">
                <td class="plan-expand-cell"><button type="button" class="plan-expand-button" aria-expanded="false"><i class="fa fa-chevron-right"></i></button></td>
                <td><b>10%</b></td><td>26-6000-0001282</td><td><span class="editable-cell"><span title="KOVIS Hidraulika">KOVIS Hidraulika</span></span></td><td>Niski prioritet</td><td><span class="editable-cell">29.09.2026</span></td><td><span class="editable-cell">26-0100-0000409</span></td><td>N-2026/118</td><td><span class="editable-cell">10</span></td><td><span class="editable-cell">02.10.2026</span></td><td><span class="editable-cell">10.10.2026</span></td><td>23.10.2026</td><td><span class="editable-cell">NM-300-A</span></td><td><span class="editable-cell">2,5</span></td><td><span class="editable-cell">Nosač motora 3mm</span></td><td><span class="editable-cell">LAS-1</span></td><td><span class="editable-cell"><span class="production-plan-note-preview" title=""></span></span></td>
              </tr>
              <tr class="production-plan-row--purple">
                <td class="plan-expand-cell"><button type="button" class="plan-expand-button" aria-expanded="false"><i class="fa fa-chevron-right"></i></button></td>
                <td><b>0%</b></td><td>26-6000-0001286</td><td><span class="editable-cell"><span title="TRENDY GERMANY GMBH 1">TG GmbH 1</span></span></td><td>Uzorci</td><td><span class="editable-cell">01.10.2026</span></td><td><span class="editable-cell">26-0100-0000415</span></td><td>PO-88240</td><td><span class="editable-cell">10</span></td><td><span class="editable-cell">01.10.2026</span></td><td><span class="editable-cell">03.10.2026</span></td><td>09.10.2026</td><td><span class="editable-cell">UZ-DC-12</span></td><td><span class="editable-cell">5</span></td><td><span class="editable-cell">Uzorak distantne čahure</span></td><td><span class="editable-cell">TOK-1</span></td><td><span class="editable-cell"><span class="production-plan-note-preview" title="Uzorak za odobrenje">Uzorak za odobrenje</span></span></td>
              </tr>
              <tr class="production-plan-row--yellow">
                <td class="plan-expand-cell"><button type="button" class="plan-expand-button" aria-expanded="false"><i class="fa fa-chevron-right"></i></button></td>
                <td><b>100%</b></td><td>26-6000-0001279</td><td><span class="editable-cell"><span title="TRENDY GERMANY GMBH 2">TG GmbH 2</span></span></td><td>Uobičajeni prioritet</td><td><span class="editable-cell">25.09.2026</span></td><td><span class="editable-cell">26-0100-0000398</span></td><td>PO-88177</td><td><span class="editable-cell">40</span></td><td><span class="editable-cell">29.09.2026</span></td><td><span class="editable-cell">02.10.2026</span></td><td>07.10.2026</td><td><span class="editable-cell">DC-12-30</span></td><td><span class="editable-cell">1.000</span></td><td><span class="editable-cell">Distantna čahura</span></td><td><span class="editable-cell">TOK-2</span></td><td><span class="editable-cell"><span class="production-plan-note-preview" title=""></span></span></td>
              </tr>
            </tbody>
          </table>
        </div></div>
        <div class="row">
          <div class="col-sm-12 col-md-5"><div class="dataTables_info">Prikaz 1 do 25 od 312 radnih naloga</div></div>
          <div class="col-sm-12 col-md-7"><div class="dataTables_paginate paging_simple_numbers"><ul class="pagination">
            <li class="paginate_button page-item previous disabled"><a class="page-link" href="#">Prethodna</a></li>
            <li class="paginate_button page-item active"><a class="page-link" href="#">1</a></li>
            <li class="paginate_button page-item"><a class="page-link" href="#">2</a></li>
            <li class="paginate_button page-item"><a class="page-link" href="#">3</a></li>
            <li class="paginate_button page-item disabled"><a class="page-link" href="#">…</a></li>
            <li class="paginate_button page-item"><a class="page-link" href="#">13</a></li>
            <li class="paginate_button page-item next"><a class="page-link" href="#">Sljedeća</a></li>
          </ul></div></div>
        </div>
      </div>
    </div>
  </div>
</section>
```

Optional expanded detail row (the chevron toggles it):

```html
<tr class="plan-detail-row"><td colspan="17"><div class="plan-expanded-details"><h6>Operacije</h6><div class="plan-detail-scroll"><ul class="plan-operations-flow">
  <li><span class="plan-operation-circle plan-operation-circle--finished">&#10003;</span><div><strong>Rezanje</strong><small>#10 · 010 · Završeno</small></div></li>
  <li><span class="plan-operation-circle plan-operation-circle--unfinished"></span><div><strong>CNC glodanje</strong><small>#10 · 020 · Nezavršeno</small></div></li>
</ul></div></div></td></tr>
```

The real RN numbers in this table come straight from Pantheon's `acKeyView`; the 2-4-7 format above is assumed from the list formatter. Columns can also be hidden by a saved user setting.
