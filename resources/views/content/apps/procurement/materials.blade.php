@extends('layouts/contentLayoutMaster')
@section('title', 'Materijali za nabavku')
@section('content')
@include('content.apps.procurement.common')
@php($fmt = \App\Services\Procurement\ProcurementFormat::class)
@php($canSelect = app(\App\Services\Procurement\ProcurementAccess::class)->can(auth()->user(),'edit'))
<div class="card"><div class="card-header gap-1 flex-wrap">
 <div class="flex-grow-1" style="max-width:520px"><label class="form-label" for="filter-search">Pretraga</label><div class="input-group input-group-merge"><span class="input-group-text"><i data-feather="search"></i></span><input id="filter-search" class="form-control filter-input" name="q" form="procurement-material-filters" value="{{ request('q') }}" placeholder="Narudžba, pozicija, RN, materijal, dimenzije"></div></div>
 <div class="d-flex gap-1 flex-wrap"><button type="button" class="btn btn-outline-primary btn-sm" data-filter-toggle="procurement-material-filter-panel" aria-controls="procurement-material-filter-panel" aria-expanded="{{ request()->boolean('filters_open') ? 'true':'false' }}"><i data-feather="filter" class="me-50"></i><span data-filter-toggle-label>{{ request()->boolean('filters_open') ? 'Sakrij filtere':'Pokaži filtere' }}</span></button><a class="btn btn-outline-danger btn-sm" href="{{ route('app-procurement-materials') }}">Obriši filter</a></div>
</div>
<div class="card-body {{ request()->boolean('filters_open') ? '':'d-none' }}" id="procurement-material-filter-panel"><form method="get" id="procurement-material-filters" data-auto-filter class="row g-2">
 <input type="hidden" name="filters_open" value="{{ request()->boolean('filters_open') ? '1':'0' }}">
 <div class="col-md-2"><label class="form-label" for="filter-status">Status</label><select class="form-select" id="filter-status" name="status"><option value="">Svi</option>@foreach(['not_ordered'=>'Nije naručeno','part_ordered'=>'Djelimično naručeno','ordered'=>'Naručeno','partial'=>'Djelimično isporučeno','received'=>'Materijal stigao','late'=>'Kasni','soon'=>'Rok u narednih 7 dana','zero'=>'Bez potrebne količine'] as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select></div>
 <div class="col-md-2"><label class="form-label" for="filter-unit">Jedinica mjere</label><input id="filter-unit" class="form-control filter-input" name="unit" value="{{ request('unit') }}"></div>
 <div class="col-md-2"><label class="form-label" for="filter-wo">Radni nalog</label><input id="filter-wo" class="form-control filter-input" name="work_order" value="{{ request('work_order') }}"></div>
 <div class="col-md-2"><label class="form-label" for="filter-supplier">Dobavljač</label><input id="filter-supplier" class="form-control filter-input" name="supplier" value="{{ request('supplier') }}"></div>
 <div class="col-md-2"><label class="form-label" for="filter-from">Potreban prijem od</label><input id="filter-from" class="form-control" type="date" name="from" value="{{ request('from') }}"></div>
 <div class="col-md-2"><label class="form-label" for="filter-to">Potreban prijem do</label><input id="filter-to" class="form-control" type="date" name="to" value="{{ request('to') }}"></div>
 <div class="col-md-3 d-flex align-items-end"><label class="form-check"><input type="checkbox" class="form-check-input" name="selected_only" value="1" @checked(request()->boolean('selected_only'))> Samo odabrane stavke</label></div>
 <input type="hidden" name="sort" value="{{ request('sort', 'work_order') }}"><input type="hidden" name="direction" value="{{ request('direction', 'desc') }}"><noscript><button class="btn btn-primary">Filtriraj</button></noscript>
</form></div></div>
<div class="card" id="requirement-selection" data-url="{{ route('app-procurement-selection') }}" data-csrf="{{ csrf_token() }}"><div class="card-header d-flex gap-1 flex-wrap"><h4 class="mb-0 me-auto">Materijali za nabavku</h4>
<span role="status" id="selection-count">Odabrano: {{ count($selected) }}</span><button type="button" id="clear-selection" class="btn btn-outline-secondary btn-sm" @disabled(!$selected || !$canSelect)>Poništi odabir</button>
<form method="post" action="{{ route('app-procurement-create') }}" id="create-from-selection">@csrf<input type="hidden" name="from_session" value="1"><button class="btn btn-primary btn-sm" @disabled(!$selected || !$canSelect)>Kreiraj nabavku</button></form></div>
<div class="table-responsive"><table class="table table-striped procurement-compact"><thead><tr>
 <th aria-label="Odabir"></th>
 @foreach(['order'=>'Narudžba','position'=>'Poz.','work_order'=>'Radni nalog','material'=>'Materijal'] as $key=>$label)<th><a href="{{ request()->fullUrlWithQuery(['sort'=>$key,'direction'=>request('sort')===$key && request('direction')==='asc' ? 'desc':'asc','page'=>1]) }}">{{ $label }}</a></th>@endforeach
 <th>Dimenzije / Napomena</th><th class="text-end">Potrebna količina</th><th class="text-end">Naručeno</th><th class="text-end">Primljeno</th><th class="text-end">Preostalo za nabavku</th><th><a href="{{ request()->fullUrlWithQuery(['sort'=>'required_date','direction'=>'asc','page'=>1]) }}">Potreban prijem</a></th><th>Status</th><th>Akcije</th>
</tr></thead><tbody>
@forelse($rows as $row)
@php($orders = implode(', ', array_unique(array_column($row['links'],'order_number'))))
@php($positions = implode(', ', array_column($row['links'],'position')))
<tr class="{{ $row['late'] ? 'table-danger' : '' }}">
<td><input type="checkbox" class="requirement-select" value="{{ $row['id'] }}" aria-label="Odaberi {{ $row['material'] }} za {{ $row['work_order'] }}" @checked(in_array($row['id'],$selected,true)) @disabled(!$canSelect || $row['state']==='zero' || bccomp($row['remaining'],'0',6)<=0)></td>
<td title="{{ $orders }}">{{ $orders ?: '—' }}</td><td title="{{ $positions }}">{{ $positions ?: '—' }}</td><td title="{{ $row['work_order'] }}"><a href="{{ route('app-invoice-preview', $row['work_order_key']) }}">{{ $row['work_order'] }}</a></td>
<td class="material-description" title="{{ $row['material'] }} — {{ $row['name'] }}">{{ $row['material'] }} — {{ $row['name'] }}</td><td class="material-description" title="{{ $row['dimensions'] }}">{{ $row['dimensions'] ?: '—' }}</td>
@foreach(['required','ordered','received','remaining'] as $field)<td class="text-end" title="{{ $row[$field] }} {{ $row['unit'] }}">{{ $fmt::quantity($row[$field],$row['unit']) }} {{ $row['unit'] }}</td>@endforeach
<td>{{ $row['required_date'] ? \Carbon\Carbon::parse($row['required_date'])->format('d.m.Y.') : 'Nije planirano' }}</td><td><span class="badge bg-light-{{ $row['late'] ? 'danger':'primary' }}">{{ ['zero'=>'Bez potrebe','not_ordered'=>'Nije naručeno','part_ordered'=>'Djelimično naručeno','ordered'=>'Naručeno','partial'=>'Djelimično isporučeno','received'=>'Materijal stigao'][$row['state']] }}{{ $row['late'] ? ' · Kasni':'' }}</span></td>
<td>@foreach($row['purchases'] as $id=>$number)<a class="btn btn-sm btn-outline-primary" href="{{ route('app-procurement-show',$id) }}">{{ $number }}</a> @endforeach</td></tr>
@empty<tr><td colspan="13" class="text-center py-2">Nema materijala za odabrane filtere.</td></tr>@endforelse
</tbody></table></div><div class="card-body d-flex justify-content-between"><span>Ukupno: {{ $rows->total() }}</span>{{ $rows->links('pagination::bootstrap-4') }}</div></div>
@endsection
@section('page-script')<script src="{{ asset('js/scripts/pages/procurement.js') }}?v=2"></script>@endsection
