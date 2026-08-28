@extends('layouts/contentLayoutMaster')

@section('title', 'Plan proizvodnje')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('vendors/css/tables/datatable/dataTables.bootstrap5.min.css') }}">
<link rel="stylesheet" href="{{ asset('vendors/css/pickers/flatpickr/flatpickr.min.css') }}">
@endsection

@section('page-style')
<link rel="stylesheet" href="{{ asset('css/base/plugins/forms/pickers/form-flat-pickr.css') }}">
<style>
  .production-plan-table th { white-space: nowrap; font-size: .78rem; }
  .production-plan-table td { vertical-align: middle; }
  .production-plan-table tr.production-plan-row--red > td { background-color: #fff6f7 !important; color: #8f1d2c; }
  .production-plan-table tr.production-plan-row--blue > td { background-color: #e0f1ff !important; color: #145486; }
  .production-plan-table tr.production-plan-row--yellow > td { background-color: #fff9e3 !important; color: #735c00; }
  .production-plan-table tr.production-plan-row--orange > td { background-color: #fff5ed !important; color: #9a4210; }
  .production-plan-table tr.production-plan-row--purple > td { background-color: #f0e6ff !important; color: #6540a0; }
  .production-plan-table tr.production-plan-row--teal > td { background-color: #eef9f6 !important; color: #0e6b5b; }
  .production-plan-table tr.production-plan-row--green > td { background-color: #eef8f0 !important; color: #1d6e3b; }
  .production-plan-table tr.production-plan-row--grey > td { background-color: #f5f6f8 !important; color: #69707a; }
  .production-plan-table tr.production-plan-row--red > td:first-child { box-shadow: inset 4px 0 0 #d96274; }
  .production-plan-table tr.production-plan-row--blue > td:first-child { box-shadow: inset 4px 0 0 #2b81c5; }
  .production-plan-table tr.production-plan-row--yellow > td:first-child { box-shadow: inset 4px 0 0 #d9ad27; }
  .production-plan-table tr.production-plan-row--orange > td:first-child { box-shadow: inset 4px 0 0 #df8a4e; }
  .production-plan-table tr.production-plan-row--purple > td:first-child { box-shadow: inset 4px 0 0 #8b5bc8; }
  .production-plan-table tr.production-plan-row--teal > td:first-child { box-shadow: inset 4px 0 0 #4faaa0; }
  .production-plan-table tr.production-plan-row--green > td:first-child { box-shadow: inset 4px 0 0 #65aa7a; }
  .production-plan-table tr.production-plan-row--grey > td:first-child { box-shadow: inset 4px 0 0 #9aa2ad; }
  .production-plan-table .number-cell { text-align: end; }
  .production-plan-table .document-cell { min-width: 145px; }
  .plan-filter-label { font-size: .78rem; font-weight: 600; }
  #plan-filteri > [class*="col-md-2"] { flex: 0 0 auto; width: 25%; }
  #plan-filteri .form-control, #plan-filteri .form-select, #plan-filteri .input-group-text { min-height: 36px; }
  #plan-proizvodnje-tabela_wrapper > .row:first-child {
    margin-right: 0;
    margin-left: 0;
    padding: .875rem 1.25rem;
  }
  #plan-proizvodnje-tabela_wrapper > .row:first-child > div:first-child { padding-left: 0; }
  #plan-proizvodnje-tabela_wrapper > .row:first-child > div:last-child { padding-right: 0; }
  .production-plan-table-overlay-host { position: relative; }
  .production-plan-table-loading-overlay {
    position: fixed;
    inset: 0;
    display: none;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.74);
    backdrop-filter: blur(1px);
    z-index: 1055;
    pointer-events: none;
  }
  .production-plan-table-loading-overlay.is-visible { display: flex; }
  .production-plan-table-loading-overlay-content {
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.65rem;
    text-align: center;
  }
  .production-plan-table-loading-spinner {
    width: 2rem;
    height: 2rem;
    border-width: 0.2em;
    color: #495b73;
  }
  .production-plan-table-loading-message {
    font-size: 0.95rem;
    font-weight: 600;
    color: #6e6b7b;
  }
  .dark-layout .production-plan-table-loading-overlay,
  .semi-dark-layout .production-plan-table-loading-overlay { background: rgba(20, 28, 48, 0.68); }
  .dark-layout .production-plan-table-loading-spinner,
  .semi-dark-layout .production-plan-table-loading-spinner { color: #d6dcec; }
  .dark-layout .production-plan-table-loading-message,
  .semi-dark-layout .production-plan-table-loading-message { color: #f4f5fb; }
  @media (max-width: 767.98px) { #plan-filteri > [class*="col-md-2"] { width: 100%; } }
</style>
@endsection

@section('content')
<section class="production-plan-wrapper">
  <div class="content-header row mb-2">
    <div class="content-header-left col-md-8 col-12"><h2 class="content-header-title float-start mb-0">Plan proizvodnje</h2></div>
    <div class="content-header-right col-md-4 col-12 text-md-end mt-1 mt-md-0">
      <button class="btn btn-primary" type="button" id="btn-izvoz"><i data-feather="download" class="me-50"></i> Izvezi u Excel</button>
    </div>
  </div>

  <div class="card mb-2">
    <div class="card-header d-flex justify-content-between align-items-center"><h4 class="mb-0">Filter plana proizvodnje</h4><div class="d-flex align-items-center flex-wrap gap-2 filter-header-actions"><button class="btn btn-outline-primary btn-sm" type="button" id="btn-prikazi-filtere" aria-expanded="false"><i data-feather="filter" class="me-50"></i> Prikaži filtere</button><button class="btn btn-outline-danger btn-sm" type="button" id="btn-obrisi-filter"><i data-feather="trash-2" class="me-50"></i> Obriši filter</button></div></div>
    <div class="card-body d-none" id="tijelo-filtera">
      <div class="row g-2 mb-2" id="plan-filteri">
        <div class="col-md-3"><label class="plan-filter-label">Pretraga</label><div class="input-group input-group-merge"><span class="input-group-text"><i data-feather="search"></i></span><input class="form-control" data-filter="pretraga" placeholder="Pretraži sve kolone"></div></div>
        <div class="col-md-3"><label class="plan-filter-label" for="plan-boja-redova">Boja redova</label><select class="form-select" id="plan-boja-redova"><option value="none">Bez boje</option><option value="basic">Osnovne</option><option value="all" selected>Sve</option></select></div>
        <div class="col-md-3"><label class="plan-filter-label">Broj narudžbenice</label><input class="form-control" data-filter="broj_narudzbenice" value="0110"></div>
        <div class="col-md-3"><label class="plan-filter-label">Kupac</label><input class="form-control" data-filter="kupac"></div>
        <div class="col-md-3"><label class="plan-filter-label">Narudžbenica kupca</label><input class="form-control" data-filter="narudzbenica_kupca"></div>
        <div class="col-md-2"><label class="plan-filter-label">Br. poz.</label><input class="form-control" data-filter="broj_pozicije"></div>
        <div class="col-md-2"><label class="plan-filter-label">Šifra artikla</label><input class="form-control" data-filter="sifra_artikla"></div>
        <div class="col-md-3"><label class="plan-filter-label">Naziv</label><input class="form-control" data-filter="naziv"></div>
        <div class="col-md-2"><label class="plan-filter-label">Naručeno</label><input class="form-control" data-filter="naruceno"></div>
        <div class="col-md-2"><label class="plan-filter-label">Razlika</label><input class="form-control" data-filter="izradeno"></div>
        <div class="col-md-3"><label class="plan-filter-label">Dobavljač</label><input class="form-control" data-filter="dobavljac"></div>
        <div class="col-md-3"><label class="plan-filter-label">Status dobavljača</label><input class="form-control" data-filter="status_dobavljaca" placeholder="Ulazni dokument"></div>
        <div class="col-md-2"><label class="plan-filter-label">Status RN</label><input class="form-control" data-filter="status_rn"></div>
        <div class="col-md-2"><label class="plan-filter-label">Faza izrade</label><input class="form-control" data-filter="faza_izrade"></div>
        <div class="col-md-3"><label class="plan-filter-label">Datum isporuke od</label><input class="form-control plan-date" data-filter="datum_isporuke_od" value="2026-07-01" placeholder="dd.mm.gggg" autocomplete="off"></div>
        <div class="col-md-3"><label class="plan-filter-label">Datum isporuke do</label><input class="form-control plan-date" data-filter="datum_isporuke_do" placeholder="dd.mm.gggg" autocomplete="off"></div>
        <div class="col-md-3"><label class="plan-filter-label">Datum narudžbe od</label><input class="form-control plan-date" data-filter="datum_narudzbe_od" placeholder="dd.mm.gggg" autocomplete="off"></div>
        <div class="col-md-3"><label class="plan-filter-label">Datum narudžbe do</label><input class="form-control plan-date" data-filter="datum_narudzbe_do" placeholder="dd.mm.gggg" autocomplete="off"></div>
        <div class="col-md-3 d-flex align-items-end"><button type="button" class="btn btn-primary w-100" id="btn-filter"><i data-feather="filter" class="me-50"></i> Filter</button></div>
      </div>
    </div>
  </div>

  <div class="card"><div class="card-datatable table-responsive"><table class="table production-plan-table" id="plan-proizvodnje-tabela"><thead><tr>
    <th>Broj narudžbenice</th><th>Kupac</th><th>Narudžbenica kupca</th><th>Br. poz.</th><th>Šifra artikla</th><th>Naziv</th><th>Naručeno</th><th>Razlika</th><th>Datum isporuke</th><th>Datum narudžbe</th><th>Dobavljač</th><th>Status dobavljača</th><th>Status RN</th><th>Faza izrade</th>
  </tr></thead></table></div></div>
</section>

<div class="modal fade" id="modal-izvoz" tabindex="-1" aria-labelledby="naslov-modal-izvoza" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="naslov-modal-izvoza">Izvoz plana proizvodnje</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zatvori"></button></div><div class="modal-body">
  <label class="form-label">Opseg izvoza</label>
  <div class="form-check mb-50"><input class="form-check-input" type="radio" name="opseg-izvoza" value="cijela_lista" checked id="opseg-cijela"><label class="form-check-label" for="opseg-cijela">Cijela lista</label></div>
  <div class="form-check"><input class="form-check-input" type="radio" name="opseg-izvoza" value="redovi" id="opseg-redovi"><label class="form-check-label" for="opseg-redovi">Raspon redova</label></div>
  <div class="row g-1 mt-1 d-none" id="raspon-redova"><div class="col-6"><label class="form-label">Početni red</label><input class="form-control" type="number" id="pocetni-red" min="1" value="1"></div><div class="col-6"><label class="form-label">Završni red</label><input class="form-control" type="number" id="zavrsni-red" min="1" value="100"></div></div>
  <hr><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="primijeni-trenutni-filter" checked><label class="form-check-label" for="primijeni-trenutni-filter">Primijeni trenutne filtere</label><div class="form-text">Može se primijeniti i kada izvozite raspon redova.</div></div>
</div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Odustani</button><button type="button" class="btn btn-primary" id="potvrdi-izvoz">Izvezi</button></div></div></div></div>
<script>window.planProizvodnjeConfig = @json($planConfig);</script>
@endsection

@section('vendor-script')
<script src="{{ asset('vendors/js/tables/datatable/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('vendors/js/tables/datatable/dataTables.bootstrap5.min.js') }}"></script>
<script src="{{ asset('vendors/js/pickers/flatpickr/flatpickr.min.js') }}"></script>
@endsection
@section('page-script')<script src="{{ asset('js/scripts/pages/app-production-plan.js?v=7') }}"></script>@endsection
