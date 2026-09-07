@extends('layouts/contentLayoutMaster')
@section('title', 'Plan proizvodnje')
@section('vendor-style')
  <link rel="stylesheet" href="{{ asset('vendors/css/tables/datatable/dataTables.bootstrap5.min.css') }}">
  <link rel="stylesheet" href="{{ asset('vendors/css/pickers/flatpickr/flatpickr.min.css') }}">
  <link rel="stylesheet" href="{{ asset('vendors/css/extensions/sweetalert2.min.css') }}">
@endsection
@section('page-style')
  <style>.production-plan-table{min-width:1500px}.production-plan-table>:not(caption)>*>*{padding:.42rem .5rem;font-size:.8rem;white-space:nowrap}.production-plan-table .editable-cell{cursor:pointer}.plan-inline-editor{z-index:2000;min-width:240px;max-width:calc(100vw - 16px);padding:.65rem;background:#fff;border:1px solid #d8d6de;border-radius:.35rem;box-shadow:0 5px 18px rgba(34,41,47,.16)}.plan-inline-editor label{font-size:.75rem;margin-bottom:.35rem}.plan-inline-editor textarea{min-height:80px}</style>
@endsection
@section('content')
<section id="rn-plan">
  <div class="content-header row"><div class="col-12 mb-2"><h2 class="mb-0">Plan proizvodnje — Radni nalozi</h2></div></div>
  <div class="card mb-2"><div class="card-header d-flex justify-content-between"><h4 class="mb-0">Filter plana proizvodnje</h4><div><button class="btn btn-outline-primary btn-sm" id="btn-prikazi-filtere"><i data-feather="filter" class="me-50"></i>Prikaži filtere</button><button class="btn btn-outline-danger btn-sm" id="btn-obrisi-filter"><i data-feather="trash-2" class="me-50"></i>Obriši filter</button></div></div>
    <div class="card-body d-none" id="tijelo-filtera"><div class="row g-2">
      <div class="col-md-3"><x-filters.text label="RN" class="f" data-k="rn"/></div><div class="col-md-3"><x-filters.text label="Naručitelj" class="f" data-k="narucitelj"/></div><div class="col-md-3"><x-filters.text label="Proizvod" class="f" data-k="proizvod"/></div><div class="col-md-3"><label class="form-label">Status RN</label><input class="form-control f" data-k="status_rn"></div><div class="col-md-3"><x-filters.text label="Narudžba" class="f" data-k="narudzba"/></div><div class="col-md-3"><label class="form-label">Godina</label><input class="form-control f" data-k="year" value="{{ now()->year }}"></div><div class="col-md-3"><label class="form-label">Kalendarska sedmica</label><input class="form-control f" data-k="kw" placeholder="1–53"></div><div class="col-md-3"><x-filters.date label="Datum od" class="f" data-k="datum_od"/></div><div class="col-md-3"><x-filters.date label="Datum do" class="f" data-k="datum_do"/></div><div class="col-md-3 d-flex align-items-end"><button id="filter" class="btn btn-primary w-100"><i data-feather="filter" class="me-50"></i>Filter</button></div>
    </div></div>
  </div>
  <div class="card"><div class="card-datatable table-responsive"><table class="table production-plan-table" id="plan-proizvodnje-tabela"><thead><tr><th>%</th><th>RN</th><th>Naručitelj</th><th>Prioritet</th><th>Datum</th><th>Narudžba</th><th>Br. poz.</th><th>Poč. termin</th><th>Kraj termin</th><th>Proizvod</th><th>Plan. kol.</th><th>Izr. kol.</th><th>Naziv</th><th>Napomena</th></tr></thead></table></div></div>
</section>
@endsection
@section('vendor-script')
  <script src="{{ asset('vendors/js/tables/datatable/jquery.dataTables.min.js') }}"></script><script src="{{ asset('vendors/js/tables/datatable/dataTables.bootstrap5.min.js') }}"></script><script src="{{ asset('vendors/js/pickers/flatpickr/flatpickr.min.js') }}"></script><script src="{{ asset('vendors/js/extensions/sweetalert2.all.min.js') }}"></script>
@endsection
@section('page-script')
  <script>window.planProizvodnjeConfig=@json($planConfig);flatpickr('.shared-filter-date',{dateFormat:'Y-m-d',altInput:true,altFormat:'d.m.Y',allowInput:true,disableMobile:true});</script>
  <script src="{{ asset('js/scripts/pages/app-production-plan.js?v=106') }}"></script>
@endsection
