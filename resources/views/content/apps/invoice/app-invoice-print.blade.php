@extends('layouts/fullLayoutMaster')

@php
  $invoiceNumber = $invoiceNumber ?? '';
  $issueDate = $issueDate ?? '';
  $dueDate = $dueDate ?? '';
  $sender = $sender ?? ['name' => '', 'address' => '', 'phone' => '', 'email' => ''];
  $recipient = $recipient ?? ['name' => '', 'address' => '', 'phone' => '', 'email' => ''];
  $compositions = $workOrder->compositions ?? [];
  $materials = $workOrder->materials ?? [];
  $operations = $workOrder->operations ?? [];
@endphp

@section('title', 'Radni nalog ' . ($invoiceNumber ?: ''))

@section('page-style')
<link rel="stylesheet" href="{{asset(mix('css/base/pages/app-invoice-print.css'))}}">
@endsection

@section('content')
<div class="invoice-print p-4">
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
    <div>
      <img src="{{ asset('/images/logo/TrendyCNC.png') }}" alt="Trendy logo" width="60">
      <h2 class="mb-0">eNalog.app</h2>
      <p class="mb-0 text-muted">Trendy d.o.o.</p>
      <p class="mb-0 text-muted">Bratstvo 11, 72290, Novi Travnik, BiH</p>
      <p class="mb-0 text-muted">+387 30 525 252</p>
      <p class="mb-0 text-muted">info@trendy.ba</p>
    </div>
    <div class="text-end">
      <h3 class="fw-bold">RN {{ $invoiceNumber ?: 'N/A' }}</h3>
      <p class="mb-1">Datum izdavanja: {{ $issueDate ?: '-' }}</p>
      <p class="mb-1">Datum dospijeća: {{ $dueDate ?: '-' }}</p>
    </div>
  </div>

  <div class="row mb-3">
    <div class="col-md-6">
      <h6>Pošiljatelj</h6>
      <p class="mb-0">{{ $sender['name'] }}</p>
      <p class="mb-0">{{ $sender['address'] }}</p>
      <p class="mb-0">{{ $sender['phone'] }}</p>
      <p class="mb-0">{{ $sender['email'] }}</p>
    </div>
    <div class="col-md-6">
      <h6>Primatelj</h6>
      <p class="mb-0">{{ $recipient['name'] }}</p>
      <p class="mb-0">{{ $recipient['address'] }}</p>
      <p class="mb-0">{{ $recipient['phone'] }}</p>
      <p class="mb-0">{{ $recipient['email'] }}</p>
    </div>
  </div>

  <div class="table-responsive mb-3">
    <table class="table table-striped">
      <thead>
        <tr>
          <th>Alternativa</th>
          <th>Pozicija</th>
          <th>Artikal</th>
          <th>Opis</th>
          <th>Količina</th>
        </tr>
      </thead>
      <tbody>
        @forelse($compositions as $item)
          <tr>
            <td>{{ $item['alternative'] ? 'Da' : 'Ne' }}</td>
            <td>{{ $item['position'] }}</td>
            <td>{{ $item['article_code'] }}</td>
            <td>{{ $item['description'] }}</td>
            <td>{{ $item['quantity'] }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="5" class="text-center text-muted">Nema sastavnica</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if($materials || $operations)
    <div class="row">
      <div class="col-md-6 mb-3">
        <div class="card">
          <div class="card-header">
            <h6 class="mb-0">Materijali</h6>
          </div>
          <div class="card-body p-0">
            <ul class="list-group list-group-flush">
              @forelse($materials as $material)
                <li class="list-group-item d-flex justify-content-between">
                  <div>
                    <strong>{{ $material['material_code'] }}</strong>
                    <div class="text-muted">{{ $material['name'] }}</div>
                  </div>
                  <span>{{ $material['quantity'] }} {{ $material['unit'] }}</span>
                </li>
              @empty
                <li class="list-group-item text-muted">Nema materijala</li>
              @endforelse
            </ul>
          </div>
        </div>
      </div>
      <div class="col-md-6 mb-3">
        <div class="card">
          <div class="card-header">
            <h6 class="mb-0">Operacije</h6>
          </div>
          <div class="card-body p-0">
            <ul class="list-group list-group-flush">
              @forelse($operations as $operation)
                <li class="list-group-item d-flex justify-content-between">
                  <div>
                    <strong>{{ $operation['operation_code'] }}</strong>
                    <div class="text-muted">{{ $operation['name'] }}</div>
                  </div>
                  <span>{{ $operation['unit_value'] }} {{ $operation['unit'] }}</span>
                </li>
              @empty
                <li class="list-group-item text-muted">Nema operacija</li>
              @endforelse
            </ul>
          </div>
        </div>
      </div>
    </div>
  @endif
</div>
@endsection

@section('page-script')
<script src="{{asset('js/scripts/pages/app-invoice-print.js')}}"></script>
@endsection
