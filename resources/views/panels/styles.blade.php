<!-- BEGIN: Vendor CSS-->
<!-- Font Awesome CDN -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />

@if ($configData['direction'] === 'rtl' && isset($configData['direction']))
  <link rel="stylesheet" href="{{ asset(mix('vendors/css/vendors-rtl.min.css')) }}" />
@else
  <link rel="stylesheet" href="{{ asset(mix('vendors/css/vendors.min.css')) }}" />
@endif

@yield('vendor-style')
<!-- END: Vendor CSS-->

<!-- BEGIN: Theme CSS-->
<link rel="stylesheet" href="{{ asset(mix('css/core.css')) }}" />
<link rel="stylesheet" href="{{ asset(mix('css/base/themes/dark-layout.css')) }}" />
<link rel="stylesheet" href="{{ asset(mix('css/base/themes/bordered-layout.css')) }}" />
<link rel="stylesheet" href="{{ asset(mix('css/base/themes/semi-dark-layout.css')) }}" />

@php $configData = Helper::applClasses(); @endphp

<!-- BEGIN: Page CSS-->
@if ($configData['mainLayoutType'] === 'horizontal')
  <link rel="stylesheet" href="{{ asset(mix('css/base/core/menu/menu-types/horizontal-menu.css')) }}" />
@else
  <link rel="stylesheet" href="{{ asset(mix('css/base/core/menu/menu-types/vertical-menu.css')) }}" />
@endif

{{-- Page Styles --}}
@yield('page-style')

<!-- laravel style -->
<link rel="stylesheet" href="{{ asset(mix('css/overrides.css')) }}" />
<style>
  html .content .content-wrapper .content-header-title {
    border-right: 0 !important;
    padding-right: 0 !important;
    margin-right: 0 !important;
  }
</style>

<!-- BEGIN: Custom CSS-->

@if ($configData['direction'] === 'rtl' && isset($configData['direction']))
  <link rel="stylesheet" href="{{ asset(mix('css-rtl/custom-rtl.css')) }}" />
  <link rel="stylesheet" href="{{ asset(mix('css-rtl/style-rtl.css')) }}" />

@else
  {{-- user custom styles --}}
  <link rel="stylesheet" href="{{ asset(mix('css/style.css')) }}" />
@endif

<style>
  :root {
    --app-scroll-track: rgba(233, 234, 238, 0.96);
    --app-scroll-thumb-flat: rgba(172, 173, 179, 0.88);
    --app-scroll-thumb-flat-hover: rgba(154, 156, 163, 0.94);
    --app-scroll-thumb-flat-active: rgba(138, 141, 149, 0.98);
    --app-scroll-thumb-border: rgba(248, 248, 250, 0.98);
    --app-scroll-thumb-start: rgba(188, 190, 196, 0.96);
    --app-scroll-thumb-end: rgba(157, 160, 168, 0.96);
    --app-scroll-thumb-hover-start: rgba(174, 177, 184, 0.98);
    --app-scroll-thumb-hover-end: rgba(145, 149, 157, 0.98);
    --app-scroll-thumb-active-start: rgba(160, 164, 172, 1);
    --app-scroll-thumb-active-end: rgba(133, 138, 147, 1);
    --app-scroll-ps-rail-hover: rgba(222, 223, 228, 0.9);
  }

  html.dark-layout,
  body.dark-layout,
  html.semi-dark-layout,
  body.semi-dark-layout {
    --app-scroll-track: rgba(28, 31, 38, 0.96);
    --app-scroll-thumb-flat: rgba(128, 132, 141, 0.9);
    --app-scroll-thumb-flat-hover: rgba(149, 154, 164, 0.95);
    --app-scroll-thumb-flat-active: rgba(168, 173, 184, 0.98);
    --app-scroll-thumb-border: rgba(18, 21, 27, 0.96);
    --app-scroll-thumb-start: rgba(140, 145, 155, 0.96);
    --app-scroll-thumb-end: rgba(113, 118, 128, 0.96);
    --app-scroll-thumb-hover-start: rgba(160, 165, 176, 0.98);
    --app-scroll-thumb-hover-end: rgba(131, 137, 148, 0.98);
    --app-scroll-thumb-active-start: rgba(178, 184, 195, 1);
    --app-scroll-thumb-active-end: rgba(147, 153, 164, 1);
    --app-scroll-ps-rail-hover: rgba(52, 57, 68, 0.95);
  }

  * {
    scrollbar-width: thin;
    scrollbar-color: var(--app-scroll-thumb-flat) var(--app-scroll-track);
  }

  *::-webkit-scrollbar {
    width: 8px;
    height: 8px;
  }

  *::-webkit-scrollbar-track {
    background: var(--app-scroll-track);
    border-radius: 999px;
  }

  *::-webkit-scrollbar-thumb {
    background: linear-gradient(180deg, var(--app-scroll-thumb-start) 0%, var(--app-scroll-thumb-end) 100%);
    border-radius: 999px;
    border: 1px solid var(--app-scroll-thumb-border);
  }

  *::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(180deg, var(--app-scroll-thumb-hover-start) 0%, var(--app-scroll-thumb-hover-end) 100%);
  }

  *::-webkit-scrollbar-thumb:active {
    background: linear-gradient(180deg, var(--app-scroll-thumb-active-start) 0%, var(--app-scroll-thumb-active-end) 100%);
  }

  *::-webkit-scrollbar-corner {
    background: var(--app-scroll-track);
  }

  .ps .ps__rail-x.ps--clicking,
  .ps .ps__rail-x:focus,
  .ps .ps__rail-x:hover,
  .ps .ps__rail-y.ps--clicking,
  .ps .ps__rail-y:focus,
  .ps .ps__rail-y:hover {
    background-color: var(--app-scroll-ps-rail-hover) !important;
    opacity: 0.92;
  }

  .ps__thumb-x,
  .ps__thumb-y {
    background: linear-gradient(180deg, var(--app-scroll-thumb-start) 0%, var(--app-scroll-thumb-end) 100%) !important;
    border-radius: 999px;
    box-shadow: inset 0 0 0 1px var(--app-scroll-thumb-border);
  }

  .ps__thumb-x {
    height: 7px;
    bottom: 3px;
  }

  .ps__thumb-y {
    width: 7px;
    right: 3px;
  }

  .ps__rail-x.ps--clicking > .ps__thumb-x,
  .ps__rail-x:focus > .ps__thumb-x,
  .ps__rail-x:hover > .ps__thumb-x,
  .ps__rail-y.ps--clicking > .ps__thumb-y,
  .ps__rail-y:focus > .ps__thumb-y,
  .ps__rail-y:hover > .ps__thumb-y {
    background: linear-gradient(180deg, var(--app-scroll-thumb-hover-start) 0%, var(--app-scroll-thumb-hover-end) 100%) !important;
  }

  .ps__rail-x.ps--clicking > .ps__thumb-x,
  .ps__rail-x:focus > .ps__thumb-x,
  .ps__rail-x:hover > .ps__thumb-x {
    height: 9px;
  }

  .ps__rail-y.ps--clicking > .ps__thumb-y,
  .ps__rail-y:focus > .ps__thumb-y,
  .ps__rail-y:hover > .ps__thumb-y {
    width: 9px;
  }
</style>
