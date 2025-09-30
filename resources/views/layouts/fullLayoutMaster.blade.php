@isset($pageConfigs)
  {!! Helper::updatePageConfig($pageConfigs) !!}
@endisset

<!DOCTYPE html>
@php $configData = Helper::applClasses(); @endphp

<html class="loading {{ $configData['theme'] === 'light' ? '' : $configData['layoutTheme'] }}"
  lang="@if (session()->has('locale')){{ session()->get('locale') }}@else{{ $configData['defaultLanguage'] }}@endif" data-textdirection="{{ env('MIX_CONTENT_DIRECTION') === 'rtl' ? 'rtl' : 'ltr' }}"
  @if ($configData['theme'] === 'dark') data-layout="dark-layout"@endif>

<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width,initial-scale=1.0,user-scalable=0,minimal-ui">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="description"
    content="Vuexy admin is super flexible, powerful, clean &amp; modern responsive bootstrap 4 admin template with unlimited possibilities.">
  <meta name="keywords"
    content="admin template, Vuexy admin template, dashboard template, flat admin template, responsive admin template, web app">
  <meta name="author" content="PIXINVENT">
  <title>@yield('title') - TRENDY.app | Platoforma za upravljanje skladištem</title>
  <link rel="apple-touch-icon" href="{{ asset('images/ico/favicon-32x32.png') }}">
  <link rel="shortcut icon" type="image/x-icon" href="{{ asset('images/logo/favicon.ico') }}">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,300;0,400;0,500;0,600;1,400;1,500;1,600"
    rel="stylesheet">

  {{-- Include core + vendor Styles --}}
  @include('panels/styles')

  {{-- Include core + vendor Styles --}}
  @include('panels/styles')
</head>



<body
  class="vertical-layout vertical-menu-modern {{ $configData['bodyClass'] }} {{ $configData['theme'] === 'dark' ? 'dark-layout' : '' }} {{ $configData['blankPageClass'] }} blank-page"
  data-menu="vertical-menu-modern" data-col="blank-page" data-framework="laravel"
  data-asset-path="{{ asset('/') }}">

  <!-- BEGIN: Content-->
  <div class="app-content content {{ $configData['pageClass'] }}">
    <div class="content-overlay"></div>
    <div class="header-navbar-shadow"></div>

    <div class="content-wrapper">
      <div class="content-body">
        {{-- Include Startkit Content --}}
        @yield('content')

      </div>
    </div>
  </div>
  <!-- End: Content-->

  <!-- Success Messages -->
   @if(session('success'))
     <div class="alert alert-success alert-dismissible fade show text-white" role="alert" style="width: 100%; margin: 1rem 1rem; position: fixed; bottom: 0; left: 0; z-index: 9999; height: 30px; opacity: 1; display: flex; align-items: center;">
       <i data-feather="check-circle" class="ms-2 me-2"></i>
       {{ session('success') }}
       <button type="button" class="btn-close text-white" data-bs-dismiss="alert" aria-label="Close"></button>
     </div>
   @endif

  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show text-white" role="alert" style="width: 100%; margin: 1rem 1rem; position: fixed; bottom: 0; left: 0; z-index: 9999; height: 30px; opacity: 1; display: flex; align-items: center;">
      <i data-feather="alert-circle" class="ms-2 me-2"></i>
      {{ session('error') }}
      <button type="button" class="btn-close text-white" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  @if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show text-white" role="alert" style="width: 100%; margin: 1rem 1rem; position: fixed; bottom: 0; left: 0; z-index: 9999; height: 30px; opacity: 1; display: flex; align-items: center;">
      <i data-feather="alert-circle" class="me-25 ms-2"></i>
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
      <button type="button" class="btn-close text-white" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

   {{-- include default scripts --}}
   @include('panels/scripts')
   
   {{-- Custom Alert Styles --}}
   <style>
     .alert-danger {
       background-color: #dc3545 !important;
       color: white !important;
       opacity: 1 !important;
       border: none !important;
       margin-left: calc(1.2rem) !important;
       margin-right: calc(1.2rem) !important;
       width: calc(100% - 2.4rem + 0vw) !important;
     }
     
     .alert-success {
       background-color: #198754 !important;
       color: white !important;
       opacity: 1 !important;
       border: none !important;
       margin-left: calc(1.2rem) !important;
       margin-right: calc(1.2rem) !important;
       width: calc(100% - 2.4rem + 0vw) !important;
     }
     
     .alert .btn-close {
       padding: 0.7rem !important;
       color: white !important;
       background-color: transparent !important;
       opacity: 1 !important;
       filter: none !important;
     }
     
     .alert .btn-close::before {
       color: white !important;
       opacity: 1 !important;
       filter: none !important;
     }
     
     .alert * {
       color: white !important;
     }
   </style>

  <script type="text/javascript">
    $(window).on('load', function() {
      if (feather) {
        feather.replace({
          width: 14,
          height: 14
        });
      }
    })

    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
      const alerts = document.querySelectorAll('.alert');
      alerts.forEach(function(alert) {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
      });
    }, 5000);
  </script>

</body>

</html>
