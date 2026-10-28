@extends('layouts/contentLayoutMaster')
@section('title','Nacrt poruke dobavljaču')
@section('content')
@include('content.apps.procurement.common')
<div class="card"><div class="card-header"><h4>Nacrt poruke · {{ $purchase['number'] }}</h4></div><div class="card-body">
<form method="get" data-auto-filter><label>Predložak</label><select name="template" class="form-select mb-2">@foreach($templates as $key=>$label)<option value="{{ $key }}" @selected($kind===$key)>{{ $label }}</option>@endforeach</select></form>
<label>Primaoci</label><input id="draft-to" class="form-control mb-1" value="{{ implode(', ',array_unique(array_column($contacts,'contact'))) }}"><label>Predmet</label><input id="draft-subject" class="form-control mb-1" value="{{ $subject }}"><label>Sadržaj</label><textarea id="draft-body" class="form-control mb-1" rows="12">{{ $body }}</textarea><button type="button" class="btn btn-primary" id="download-draft">Preuzmi nacrt (.eml)</button><p class="text-muted mt-1">Nacrt možete pregledati i izmijeniti. Ovaj ekran ne šalje emailove. Poruku otvorite u svom email programu i sami potvrdite slanje.</p>
</div></div>
@endsection
@section('page-script')<script src="{{ asset('js/scripts/pages/procurement.js') }}?v=2"></script>@endsection
