@extends('layouts/contentLayoutMaster')
@section('title','QR prijem materijala')
@section('content')
@include('content.apps.procurement.common')
<div class="card"><div class="card-body"><h3>{{ $purchase['number'] }}</h3><p role="status">Evidentiranje dolaska skeniranog materijala…</p>
<form method="post" action="{{ route('app-procurement-scan-receive',$purchase['id']) }}" id="scan-receipt">@csrf
@if($line)<input type="hidden" name="line" value="{{ $line }}">@endif
<button class="btn btn-primary">Materijal stigao</button></form>
<a href="{{ route('app-procurement-show',$purchase['id']) }}">Detalji narudžbenice</a></div></div>
@endsection
@section('page-script')@if(!$errors->any())<script>document.getElementById('scan-receipt').requestSubmit();</script>@endif @endsection
