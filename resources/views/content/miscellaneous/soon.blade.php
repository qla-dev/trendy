@php
$configData = Helper::applClasses();
@endphp
@extends('layouts/fullLayoutMaster')

@section('title', $soonTitle ?? 'Skladišni alati')

@section('page-style')
  <link rel="stylesheet" href="{{ asset(mix('css/base/pages/page-misc.css')) }}">
@endsection

@section('content')
<div class="misc-wrapper">
  <a class="brand-logo d-flex align-items-center" href="#" style="top: 0; left: 0;">
    <img src="{{ asset('/images/logo/TrendyCNC.png') }}" alt="eNalog.app" height="auto" width="100">
    <h2 class="brand-text text-primary ms-1 mb-0">eNalog.app</h2>
  </a>

  <div class="misc-inner p-2 p-sm-3 text-center">
    @if($configData['theme'] === 'dark')
      <img class="img-fluid mb-3" src="{{ asset('images/pages/coming-soon-dark.svg') }}" alt="Soon page" />
    @else
      <img class="img-fluid mb-3" src="{{ asset('images/pages/coming-soon.svg') }}" alt="Soon page" />
    @endif

    <h2 class="mb-1">{{ $soonTitle ?? 'Skladišni alati stižu' }}</h2>
    <p class="mb-2">{{ $soonText ?? 'Trenutno radimo na alatu koji će objediniti sve vaše zalihe, lokacije i pakete.' }}</p>
    <a class="btn btn-primary mb-2 btn-sm-block" href="{{ url('/') }}">Natrag na početnu</a>
  </div>
</div>
<!-- / Soon page -->
@endsection
