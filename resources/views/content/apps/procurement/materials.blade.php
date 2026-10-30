@extends('layouts/contentLayoutMaster')
@section('title', 'Materijali za nabavku')
@php($canSelect = app(\App\Services\Procurement\ProcurementAccess::class)->can(auth()->user(),'edit'))
@section('content_header_right')
<div class="d-flex justify-content-end" id="material-page-actions"><button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#order-material-modal" @disabled(!$canSelect)><i data-feather="plus" class="me-50"></i>Naruči materijal</button></div>
@endsection
@section('content')
@include('content.apps.procurement.common')
@php($fmt = \App\Services\Procurement\ProcurementFormat::class)
<div class="card"><div class="card-header d-flex justify-content-between align-items-center gap-1 flex-wrap"><h4 class="mb-0">Filter materijala</h4><div class="d-flex gap-1 flex-wrap"><button type="button" class="btn btn-outline-primary btn-sm" data-filter-toggle="procurement-material-filter-panel" aria-controls="procurement-material-filter-panel" aria-expanded="{{ request()->boolean('filters_open') ? 'true':'false' }}"><i data-feather="filter" class="me-50"></i><span data-filter-toggle-label>{{ request()->boolean('filters_open') ? 'Sakrij filtere':'Pokaži filtere' }}</span></button><a class="btn btn-outline-danger btn-sm" href="{{ route('app-procurement-materials') }}"><i data-feather="trash-2" class="me-50"></i>Obriši filter</a></div></div>
<div class="card-body {{ request()->boolean('filters_open') ? '':'d-none' }}" id="procurement-material-filter-panel"><form method="get" id="procurement-material-filters" data-manual-filter class="row g-2">
<input type="hidden" name="filters_open" value="{{ request()->boolean('filters_open') ? '1':'0' }}">
<input type="hidden" name="q" value="{{ request('q') }}"><input type="hidden" name="per_page" value="{{ request('per_page',10) }}">
 <div class="col-md-3"><label class="form-label" for="filter-status">Status</label><select class="form-select" id="filter-status" name="status"><option value="">Svi</option>@foreach(['not_ordered'=>'Nije naručeno','part_ordered'=>'Djelimično naručeno','ordered'=>'Naručeno','partial'=>'Djelimično isporučeno','received'=>'Materijal stigao','late'=>'Kasni','soon'=>'Rok u narednih 7 dana','zero'=>'Bez potrebne količine'] as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select></div>
 <div class="col-md-3"><label class="form-label" for="filter-unit">Jedinica mjere</label><input id="filter-unit" class="form-control filter-input" name="unit" value="{{ request('unit') }}"></div>
 <div class="col-md-3"><label class="form-label" for="filter-wo">Radni nalog</label><input id="filter-wo" class="form-control filter-input" name="work_order" value="{{ request('work_order') }}"></div>
 <div class="col-md-3"><label class="form-label" for="filter-supplier">Dobavljač</label><input id="filter-supplier" class="form-control filter-input" name="supplier" value="{{ request('supplier') }}"></div><div class="col-md-3"><label class="form-label" for="date-created_from">Kreiranje RN od</label><input id="date-created_from" class="form-control" type="text" data-procurement-date placeholder="dd.mm.gggg" name="created_from" value="{{ request('created_from') }}"></div>
<div class="col-md-3"><label class="form-label" for="date-created_to">Kreiranje RN do</label><input id="date-created_to" class="form-control" type="text" data-procurement-date placeholder="dd.mm.gggg" name="created_to" value="{{ request('created_to') }}"></div>
<div class="col-md-3"><label class="form-label" for="date-from">Potreban prijem od</label><input id="date-from" class="form-control" type="text" data-procurement-date placeholder="dd.mm.gggg" name="from" value="{{ request('from') }}"></div>
<div class="col-md-3"><label class="form-label" for="date-to">Potreban prijem do</label><input id="date-to" class="form-control" type="text" data-procurement-date placeholder="dd.mm.gggg" name="to" value="{{ request('to') }}"></div>
<input type="hidden" name="sort" value="{{ request('sort', 'work_order') }}"><input type="hidden" name="direction" value="{{ request('direction', 'desc') }}">
<div class="col-md-3 d-flex align-items-end"><button type="submit" class="btn btn-primary w-100"><i data-feather="filter" class="me-50"></i>Primijeni filtere</button></div>
</form></div></div>
<div class="card" id="procurement-material-list"><div class="card-header">
<form method="get" id="material-table-controls" class="d-flex justify-content-between align-items-center flex-wrap gap-1 w-100">
@foreach(request()->only(['status','unit','work_order','supplier','created_from','created_to','from','to','sort','direction','filters_open']) as $field=>$value)@if(is_scalar($value))<input type="hidden" name="{{ $field }}" value="{{ $value }}">@endif @endforeach
<div class="d-flex align-items-center gap-50"><label for="material-page-length" class="mb-0">Prikaži</label><select id="material-page-length" class="form-select" name="per_page" aria-label="Broj redova">@foreach([10,25,50,100] as $length)<option value="{{ $length }}" @selected((int)request('per_page',10)===$length)>{{ $length }}</option>@endforeach</select><span>zapisa</span></div>
<div class="d-flex align-items-center gap-50 flex-wrap"><label class="mb-0" for="filter-search">Brza pretraga:</label><input id="filter-search" class="form-control" style="width:260px;max-width:100%" type="search" name="q" value="{{ request('q') }}" placeholder="RN, materijal, dimenzije"><noscript><button class="btn btn-primary">Pretraži</button></noscript></div>
</form></div>
<div class="table-responsive"><table class="table table-striped procurement-compact"><thead><tr>
 @foreach(['order'=>'Narudžba','position'=>'Poz.','work_order'=>'Radni nalog','material'=>'Materijal'] as $key=>$label)<th><a href="{{ request()->fullUrlWithQuery(['sort'=>$key,'direction'=>request('sort')===$key && request('direction')==='asc' ? 'desc':'asc','page'=>1]) }}">{{ $label }}</a></th>@endforeach
 <th>Dimenzije / Napomena</th><th class="text-end">Potrebna količina</th><th class="text-end">Naručeno</th><th class="text-end">Primljeno</th><th class="text-end">Preostalo za nabavku</th><th><a href="{{ request()->fullUrlWithQuery(['sort'=>'required_date','direction'=>'asc','page'=>1]) }}">Potreban prijem</a></th><th>Status</th><th class="text-end">Skladište sirovina</th>
</tr></thead><tbody>
@forelse($rows as $row)
@php($orders = implode(', ', array_unique(array_column($row['links'],'order_number'))))
@php($positions = implode(', ', array_column($row['links'],'position')))
<tr class="{{ $row['late'] ? 'table-danger' : '' }}">

<td title="{{ $orders }}">{{ $orders ?: '—' }}</td><td title="{{ $positions }}">{{ $positions ?: '—' }}</td><td title="{{ $row['work_order'] }}"><a href="{{ route('app-invoice-preview', $row['work_order_key']) }}">{{ $row['work_order'] }}</a></td>
<td class="material-description" title="{{ $row['material'] }} — {{ $row['name'] }}">{{ $row['material'] }} — {{ $row['name'] }}</td><td class="material-description" title="{{ $row['dimensions'] }}">{{ $row['dimensions'] ?: '—' }}</td>
@foreach(['required','ordered','received','remaining'] as $field)<td class="text-end" title="{{ $row[$field] }} {{ $row['unit'] }}">{{ $fmt::quantity($row[$field],$row['unit']) }} {{ $row['unit'] }}</td>@endforeach
<td>{{ $row['required_date'] ? \Carbon\Carbon::parse($row['required_date'])->format('d.m.Y.') : 'Nije planirano' }}</td><td><span class="badge bg-light-{{ $row['late'] ? 'danger':'primary' }}">{{ ['zero'=>'Bez potrebe','not_ordered'=>'Nije naručeno','part_ordered'=>'Djelimično naručeno','ordered'=>'Naručeno','partial'=>'Djelimično isporučeno','received'=>'Materijal stigao'][$row['state']] }}{{ $row['late'] ? ' · Kasni':'' }}</span></td>
<td class="text-end">{{ $fmt::quantity($stock[$row['material']] ?? '0',$row['unit']) }} {{ $row['unit'] }}</td></tr>
@empty<tr><td colspan="12" class="text-center py-2">Nema materijala za odabrane filtere.</td></tr>@endforelse
</tbody></table></div><div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-1"><span>Prikaz {{ $rows->firstItem() ?? 0 }} do {{ $rows->lastItem() ?? 0 }} od {{ $rows->total() }} zapisa</span>{{ $rows->links('pagination::bootstrap-4') }}</div></div>
@if($canSelect)@include('content.apps.procurement.order-modal')@endif
@endsection
@section('vendor-style')<link rel="stylesheet" href="{{ asset('vendors/css/forms/select/select2.min.css') }}">@endsection
@section('page-style')<link rel="stylesheet" href="{{ asset('css/base/plugins/forms/form-select2.css') }}"><style>
.content-header:has(#material-page-actions){display:grid!important;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:1rem;margin:0 0 1.5rem!important}
.content-header:has(#material-page-actions) .content-header-left,.content-header:has(#material-page-actions) .content-header-right{display:block!important;min-width:0;width:auto!important;max-width:none;margin:0!important;padding:0!important}
.content-header:has(#material-page-actions) .breadcrumbs-top{margin:0!important}
.content-header:has(#material-page-actions) .breadcrumbs-top>.col-12{padding:0!important}
.content-header:has(#material-page-actions) .content-header-title{display:block!important;float:none!important;margin:0!important;text-align:left!important}
.content-header:has(#material-page-actions) .breadcrumb-right{margin:0!important}
@media(max-width:575px){.content-header:has(#material-page-actions) .content-header-title{font-size:1.1rem;line-height:1.3}#material-page-actions .btn{font-size:.9rem;padding:.65rem .8rem;white-space:nowrap}}
</style>@endsection
@section('vendor-script')<script src="{{ asset('vendors/js/forms/select/select2.full.min.js') }}"></script>
<script>
(function(){
 const form=document.getElementById('material-table-controls'),search=document.getElementById('filter-search');let timer;
 jQuery('#material-page-length').select2({minimumResultsForSearch:Infinity,width:'80px'}).on('change',()=>{clearTimeout(timer);form.requestSubmit();});
 search.addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(()=>form.requestSubmit(),500);});
 form.addEventListener('submit',()=>{clearTimeout(timer);if(document.activeElement===search){try{sessionStorage.setItem('procurement-material-search-focus',JSON.stringify({q:search.value,position:search.selectionStart,at:Date.now()}));}catch(e){}}});
 try{const saved=JSON.parse(sessionStorage.getItem('procurement-material-search-focus') || 'null');sessionStorage.removeItem('procurement-material-search-focus');if(saved && saved.q===search.value && Date.now()-saved.at<30000){search.focus();search.setSelectionRange(saved.position,saved.position);}}catch(e){}
})();
</script>@endsection
@section('page-script')<script src="{{ asset('js/scripts/pages/procurement.js') }}?v=4"></script><script src="{{ asset('js/scripts/pages/procurement-ordering.js') }}?v=3"></script>@endsection
