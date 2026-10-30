@extends('layouts/contentLayoutMaster')
@section('title', $purchase ? 'Izmjena nabavke' : 'Nova nabavka sirovina')
@section('content')
@include('content.apps.procurement.common')
@php($dates = array_filter(array_column($lines,'deadline')))
@php($header = $purchase['header'] ?? [])
<div class="card"><div class="card-header"><h4 class="card-title">{{ $purchase ? 'Izmjena '.$purchase['number'] : 'Nova nabavka sirovina' }}</h4></div><div class="card-body">
<form method="post" action="{{ $purchase ? route('app-procurement-update',$purchase['id']) : route('app-procurement-store') }}" id="procurement-form">@csrf
<input type="hidden" name="submission" value="{{ old('submission',$submission) }}">
<div class="row g-1 mb-2">
<div class="col-md-4"><label class="form-label" for="supplier">Dobavljač</label><input id="supplier" class="form-control" name="supplier" list="suppliers" required maxlength="255" value="{{ old('supplier',$purchase['supplier'] ?? $lines[0]['source']['supplier']) }}"><datalist id="suppliers">@foreach($suppliers as $supplier)<option value="{{ $supplier }}">@endforeach</datalist></div>
<div class="col-md-2"><label class="form-label" for="deadline">Rok isporuke</label><input id="deadline" class="form-control" type="date" name="deadline" required value="{{ old('deadline',$purchase['deadline'] ?? ($dates ? min($dates):'')) }}"></div>
@foreach(['document_date'=>'Datum dokumenta','valid_until'=>'Važi do'] as $field=>$label)<div class="col-md-2"><label class="form-label" for="{{ $field }}">{{ $label }}</label><input type="date" class="form-control" id="{{ $field }}" name="{{ $field }}" value="{{ old($field,$header[$field] ?? ($field==='document_date' ? now()->format('Y-m-d'):'')) }}"></div>@endforeach
<div class="col-md-2"><label class="form-label">Valuta</label><div class="form-control">{{ $purchase['currency'] ?? config('workorders.catalog_product_defaults.purchase_currency','KM') }}</div></div>
@foreach(['carrier'=>'Prijevoznik','department'=>'Odjel','procurement_type'=>'Vrsta nabavke','responsible_person'=>'Odgovorna osoba','reference_person'=>'Referent','exchange_rate'=>'Kurs'] as $field=>$label)<div class="col-md-2"><label class="form-label" for="{{ $field }}">{{ $label }}</label><input class="form-control" id="{{ $field }}" name="{{ $field }}" maxlength="255" value="{{ old($field,$header[$field] ?? ($field==='exchange_rate' ? '1':'')) }}"></div>@endforeach
</div>
<div class="table-responsive"><table class="table"><thead><tr><th>Materijal</th><th>JM</th><th>Količina</th><th>Težina kg (opcionalno)</th><th>Cijena/JM</th><th>Rabat %</th><th>Bez PDV-a</th><th>PDV %</th><th>S PDV-om</th><th>Rok stavke</th></tr></thead><tbody>
@foreach($lines as $index=>$line)
@php($allocations = $line['allocations'] ?? [['source'=>$line['source'],'quantity'=>$line['quantity']]])
<tr data-calculation-row><td style="min-width:260px">{{ $line['source']['material'] }} — {{ $line['source']['name'] }}<small class="d-block">{{ $line['source']['dimensions'] }}</small>
@if(count($allocations)>1)<details><summary>Raspodjela: {{ count($allocations) }} potreba</summary>@foreach($allocations as $a=>$allocation)<div class="my-1"><label>{{ $allocation['source']['work_order'] }} · {{ $allocation['source']['dimensions'] }}</label><input type="hidden" name="items[{{ $index }}][allocations][{{ $a }}][source_id]" value="{{ $allocation['source']['id'] }}"><input class="form-control" data-allocation name="items[{{ $index }}][allocations][{{ $a }}][quantity]" inputmode="decimal" required value="{{ old('items.'.$index.'.allocations.'.$a.'.quantity',$allocation['quantity']) }}"></div>@endforeach</details>@else
<input type="hidden" name="items[{{ $index }}][allocations][0][source_id]" value="{{ $allocations[0]['source']['id'] }}">
<input type="hidden" data-single-allocation name="items[{{ $index }}][allocations][0][quantity]" value="{{ old('items.'.$index.'.quantity',$line['quantity']) }}">
@endif</td>
<td>{{ $line['unit'] ?? $line['source']['unit'] }}</td><td><input aria-label="Količina stavke {{ $index+1 }}" class="form-control procurement-input" data-field="quantity" @readonly(count($allocations)>1) name="items[{{ $index }}][quantity]" value="{{ old('items.'.$index.'.quantity',$line['quantity']) }}"></td>
<td><input aria-label="Težina stavke {{ $index+1 }}" class="form-control procurement-input" name="items[{{ $index }}][weight]" inputmode="decimal" value="{{ old('items.'.$index.'.weight',$line['weight'] ?? '') }}"></td>
@foreach(['price'=>'price','discountRate'=>'discount_rate','vatRate'=>'vat_rate'] as $field=>$input)
@if($field==='vatRate')<td data-value="net">—</td>@endif
<td><input aria-label="{{ ['price'=>'Cijena','discountRate'=>'Rabat','vatRate'=>'PDV'][$field] }} stavke {{ $index+1 }}" class="form-control procurement-input" data-field="{{ $field }}" name="items[{{ $index }}][{{ $input }}]" inputmode="decimal" required value="{{ old('items.'.$index.'.'.$input,$line[$field] ?? '0') }}"></td>
@endforeach
<td data-value="gross">—</td><td><input aria-label="Rok stavke {{ $index+1 }}" class="form-control" style="min-width:150px" type="date" name="items[{{ $index }}][deadline]" value="{{ old('items.'.$index.'.deadline',$line['deadline'] ?? '') }}"></td></tr>
@endforeach
</tbody></table></div>
<div class="alert alert-light p-2 mt-2" aria-live="polite">Bez PDV-a: <strong data-total="net">—</strong> · Rabat: <strong data-total="discount">—</strong> · PDV: <strong data-total="vat">—</strong><div class="fs-4 mt-1">UKUPNO S PDV-OM: <strong data-total="gross">—</strong> {{ $purchase['currency'] ?? config('workorders.catalog_product_defaults.purchase_currency','KM') }}</div></div>
<label class="form-label" for="notes">Napomene</label><textarea id="notes" class="form-control mb-1" name="notes" maxlength="4000">{{ old('notes',$header['notes'] ?? '') }}</textarea>
@if(app(\App\Services\Procurement\ProcurementAccess::class)->can(auth()->user(),'manage'))<div class="alert alert-warning p-1"><label><input type="checkbox" name="overorder" value="1" @checked(old('overorder'))> Odobravam prekoračenje preostale potrebe</label><input aria-label="Obrazloženje prekoračenja" class="form-control mt-1" name="override_reason" maxlength="1000" placeholder="Obavezno obrazloženje za prekoračenje" value="{{ old('override_reason') }}"></div>@endif
<button class="btn btn-primary" id="save-procurement">Sačuvaj nabavku</button>
</form></div></div>
@endsection
@section('page-script')<script src="{{ asset('js/scripts/pages/procurement.js') }}?v=3"></script>@endsection
