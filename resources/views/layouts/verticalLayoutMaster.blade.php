<body class="vertical-layout vertical-menu-modern {{ $configData['verticalMenuNavbarType'] }} {{ $configData['blankPageClass'] }} {{ $configData['bodyClass'] }} {{ $configData['sidebarClass']}} {{ $configData['footerType'] }} {{$configData['contentLayout']}}"
data-open="click"
data-menu="vertical-menu-modern"
data-col="{{$configData['showMenu'] ? $configData['contentLayout'] : '1-column' }}"
data-framework="laravel"
data-asset-path="{{ asset('/')}}">
  <!-- BEGIN: Header-->
  @include('panels.navbar')
  <!-- END: Header-->

  <!-- BEGIN: Main Menu-->
  @if((isset($configData['showMenu']) && $configData['showMenu'] === true))
  @include('panels.sidebar')
  @endif
  <!-- END: Main Menu-->

  <!-- BEGIN: Content-->
  <div class="app-content content {{ $configData['pageClass'] }}">
    <!-- BEGIN: Header-->
    <div class="content-overlay"></div>
    <div class="header-navbar-shadow"></div>

    @if(($configData['contentLayout']!=='default') && isset($configData['contentLayout']))
    <div class="content-area-wrapper {{ $configData['layoutWidth'] === 'boxed' ? 'container-xxl p-0' : '' }}">
      <div class="{{ $configData['sidebarPositionClass'] }}">
        <div class="sidebar">
          {{-- Include Sidebar Content --}}
          @yield('content-sidebar')
        </div>
      </div>
      <div class="{{ $configData['contentsidebarClass'] }}">
        <div class="content-wrapper">
          <div class="content-body">
            {{-- Include Page Content --}}
            @yield('content')
          </div>
        </div>
      </div>
    </div>
    @else
    <div class="content-wrapper {{ $configData['layoutWidth'] === 'boxed' ? 'container-xxl p-0' : '' }}">
      {{-- Include Breadcrumb --}}
      @if($configData['pageHeader'] === true && isset($configData['pageHeader']))
      @include('panels.breadcrumb')
      @endif

      <div class="content-body">
        {{-- Include Page Content --}}
        @yield('content')
      </div>
    </div>
    @endif

  </div>
  <!-- End: Content-->

  <div class="sidenav-overlay"></div>
  <div class="drag-target"></div>

  <!-- Success and Error Messages -->
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
      <i data-feather="alert-circle" class="me-25 ms-25"></i>
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
      <button type="button" class="btn-close text-white" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  {{-- include footer --}}
  @include('panels/footer')

   {{-- include default scripts --}}
   @include('panels/scripts')
   
   <script type="text/javascript">
     $(window).on('load', function() {
       if (feather) {
         feather.replace({
           width: 14, height: 14
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


</body>
</html>
