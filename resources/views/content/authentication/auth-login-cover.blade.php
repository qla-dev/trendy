@php
$configData = Helper::applClasses();
@endphp
@extends('layouts/fullLayoutMaster')

@section('title', 'Prijava')

@section('page-style')
  {{-- Page Css files --}}
  <link rel="stylesheet" href="{{ asset(mix('css/base/plugins/forms/form-validation.css')) }}">
  <link rel="stylesheet" href="{{ asset(mix('css/base/pages/authentication.css')) }}">
@endsection

@section('content')
<div class="auth-wrapper auth-cover">
  <div class="auth-inner row m-0">
    <!-- Brand logo-->
    <a class="brand-logo d-flex align-items-center" href="#" style="top: 0; left: 0;">
      <img src="{{ asset('/images/logo/TrendyCNC.png') }}" alt="eNalog.app" height="auto" width="100">
      <h2 class="brand-text text-primary ms-1 mb-0">eNalog.app</h2>
    </a>
    <!-- /Brand logo-->

    <!-- Left Text-->
    <div class="d-none d-lg-flex col-lg-8 align-items-center p-5">
      <div class="w-100 d-lg-flex align-items-center justify-content-center px-5">
        @if($configData['theme'] === 'dark')
          <img class="img-fluid" src="{{asset('images/pages/login-v2-dark.svg')}}" alt="Login V2" />
          @else
          <img class="img-fluid" src="{{asset('images/pages/login-v2-dark.svg')}}" alt="Login V2" />
          @endif
      </div>
    </div>
    <!-- /Left Text-->

    <!-- Login-->
    <div class="d-flex col-lg-4 align-items-center auth-bg px-2 p-lg-5">
      <div class="col-12 col-sm-8 col-md-6 col-lg-12 px-xl-2 mx-auto">
        <h2 class="card-title fw-bold mb-1">Dobrodošli u eNalog.app! 👋</h2>
        <p class="card-text mb-2">Prijavite se da biste pristupili eNalog.app sistemu za upravljanje radnim nalozima i skladištem</p>
        <form class="auth-login-form mt-2" action="{{ route('auth.login') }}" method="POST">
          @csrf
          <div class="mb-1">
            <label class="form-label" for="email">Email ili username</label>
            <input class="form-control @error('email') is-invalid @enderror" id="email" type="text" name="email" value="{{ old('email') }}" placeholder="korisnik@primjer.com ili username" aria-describedby="email" autofocus="" tabindex="1" required />
            @error('email')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>
          <div class="mb-1">
            <div class="d-flex justify-content-between">
            <label class="form-label" for="password">Lozinka</label>
            <a href="{{url("auth/forgot-password")}}">
              <small>Zaboravili ste lozinku?</small>
            </a>
            </div>
            <div class="input-group input-group-merge form-password-toggle">
              <input class="form-control form-control-merge @error('password') is-invalid @enderror" id="password" type="password" name="password" placeholder="············" aria-describedby="password" tabindex="2" required />
              <span class="input-group-text cursor-pointer"><i data-feather="eye"></i></span>
            </div>
            @error('password')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>
          <div class="mb-1">
            <div class="form-check">
              <input class="form-check-input" id="remember" type="checkbox" name="remember" tabindex="3" />
              <label class="form-check-label" for="remember"> Zapamti me</label>
            </div>
          </div>
          <button class="btn btn-primary w-100" type="submit" tabindex="4">Prijavi se</button>
        </form>
      </div>
    </div>
    <!-- /Login-->
  </div>
</div>
@endsection

@section('vendor-script')
<script src="{{asset(mix('vendors/js/forms/validation/jquery.validate.min.js'))}}"></script>
@endsection

@section('page-script')
<script src="{{asset(mix('js/scripts/pages/auth-login.js'))}}"></script>
@endsection
