@isset($pageConfigs)
  {!! Helper::updatePageConfig($pageConfigs) !!}
@endisset

<!DOCTYPE html>
@php $configData = Helper::applClasses(); @endphp

<script>
  // Apply theme immediately before HTML renders
  (function() {
    // Clean up old localStorage keys - keep only the most recent one
    var possibleKeys = ['light-layout-current-skin', 'dark-layout-current-skin', 'bordered-layout-current-skin', 'semi-dark-layout-current-skin'];
    var currentLocalStorageLayout = null;
    var dataLayout = 'light-layout'; // default
    var foundKey = null;
    
    // Find which key has a value
    for (var i = 0; i < possibleKeys.length; i++) {
      var value = localStorage.getItem(possibleKeys[i]);
      if (value) {
        currentLocalStorageLayout = value;
        dataLayout = possibleKeys[i].replace('-current-skin', '');
        foundKey = possibleKeys[i];
        break;
      }
    }
    
    // If we found a theme, ensure it's saved to the standard key
    if (foundKey && foundKey !== 'light-layout-current-skin') {
      localStorage.setItem('light-layout-current-skin', currentLocalStorageLayout);
      localStorage.removeItem(foundKey);
      foundKey = 'light-layout-current-skin';
    }
    
    // Clean up other keys to avoid conflicts, but keep the found key
    for (var j = 0; j < possibleKeys.length; j++) {
      if (possibleKeys[j] !== foundKey) {
        localStorage.removeItem(possibleKeys[j]);
      }
    }
    // Also clean up prev-skin keys
    for (var k = 0; k < possibleKeys.length; k++) {
      var prevKey = possibleKeys[k].replace('-current-skin', '-prev-skin');
      localStorage.removeItem(prevKey);
    }
    
    // Apply theme if there's a stored theme
    if (currentLocalStorageLayout) {
      // Create a style element to apply theme immediately
      var style = document.createElement('style');
      style.textContent = 'html { display: none !important; }';
      document.head.appendChild(style);
      
      // Apply theme class to document element
      document.documentElement.className = 'loading ' + currentLocalStorageLayout;
      document.documentElement.setAttribute('data-layout', currentLocalStorageLayout);
      
      // Remove the hiding style after a short delay
      setTimeout(function() {
        style.remove();
      }, 50);
    }
  })();
</script>

<html class="loading {{ $configData['theme'] === 'light' ? '' : $configData['layoutTheme'] }}"
  lang="@if (session()->has('locale')){{ session()->get('locale') }}@else{{ $configData['defaultLanguage'] }}@endif" data-textdirection="{{ env('MIX_CONTENT_DIRECTION') === 'rtl' ? 'rtl' : 'ltr' }}"
  @if ($configData['theme'] === 'dark') data-layout="dark-layout" @endif>

<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width,initial-scale=1.0,user-scalable=0,minimal-ui">
  <meta name="description"
    content="Vuexy admin is super flexible, powerful, clean &amp; modern responsive bootstrap 4 admin template with unlimited possibilities.">
  <meta name="keywords"
    content="admin template, Vuexy admin template, dashboard template, flat admin template, responsive admin template, web app">
  <meta name="author" content="PIXINVENT">
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>@yield('title') - Vuexy - Bootstrap HTML & Laravel admin template</title>
  <link rel="apple-touch-icon" href="{{ asset('images/ico/favicon-32x32.png') }}">
  <link rel="shortcut icon" type="image/x-icon" href="{{ asset('images/logo/favicon.ico') }}" />
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,300;0,400;0,500;0,600;1,400;1,500;1,600"
    rel="stylesheet">


  {{-- Include core + vendor Styles --}}
  @include('panels/styles')
</head>

@isset($configData['mainLayoutType'])
  @extends((( $configData["mainLayoutType"] === 'horizontal') ? 'layouts.horizontalDetachedLayoutMaster' :
  'layouts.verticalDetachedLayoutMaster' ))
@endisset
