@extends('layouts/contentLayoutMaster')
@section('title','Dobavljači i postavke nabavke')
@section('content')
@include('content.apps.procurement.common')
<div class="card"><div class="card-header"><h4>Povezivanje dobavljača</h4></div><div class="card-body"><p>Korisnika s ulogom „Dobavljač“ kreirajte kroz postojeće upravljanje korisnicima. Zatim ga povežite s tačnim Pantheon subjektom. Nepovezan korisnik nema pristup narudžbenicama.</p>
<form method="post" action="{{ route('app-procurement-bind') }}" class="row g-1">@csrf<div class="col-md-4"><label>Korisnik</label><select class="form-select" name="user_id" required>@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->name }} ({{ $account->username }})</option>@endforeach</select></div><div class="col-md-4"><label>Pantheon dobavljač</label><input class="form-control" name="supplier" list="supplier-options" required maxlength="255"><datalist id="supplier-options">@foreach($suppliers as $supplier)<option value="{{ $supplier }}">@endforeach</datalist></div><div class="col-md-4"><label>Email odgovorne osobe</label><input class="form-control" type="email" name="contact" required></div><div><button class="btn btn-primary">Sačuvaj vezu</button></div></form>
<table class="table mt-2"><thead><tr><th>Korisnik ID</th><th>Dobavljač</th><th>Kontakt</th></tr></thead><tbody>@foreach($bindings as $binding)@if(($binding['kind'] ?? '')==='account')<tr><td>{{ $binding['user_id'] }}</td><td>{{ $binding['supplier'] }}</td><td>{{ $binding['contact'] }}</td></tr>@endif @endforeach</tbody></table>
</div></div>
@endsection
