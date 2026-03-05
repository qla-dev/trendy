<!-- BEGIN: Footer-->
@php
  $copyrightYear = date('Y');
@endphp
<footer
  class="footer footer-light {{ $configData['footerType'] === 'footer-hidden' ? 'd-none' : '' }} {{ $configData['footerType'] }}">
  <p class="clearfix mb-0">
    <span class="float-md-end d-block mt-25 text-end">COPYRIGHT &copy; {{ $copyrightYear }} <a href="https://qla.dev/" target="_blank" rel="noopener noreferrer" class="text-info footer-qla-link"><img src="https://deklarant.ai/build/images/logo-qla.png" alt="qla.dev" class="footer-qla-logo footer-qla-logo-light"><img src="https://deklarant.ai/build/images/logo-qla-dark.png" alt="qla.dev" class="footer-qla-logo footer-qla-logo-dark"></a> | All rights Reserved</span>
  </p>
</footer>
<style>
  .footer-qla-link .footer-qla-logo {
    height: 18px;
    width: auto;
    vertical-align: middle;
  }

  .footer-qla-link .footer-qla-logo-dark {
    display: none;
  }

  body.dark-layout .footer-qla-link .footer-qla-logo-light,
  body.semi-dark-layout .footer-qla-link .footer-qla-logo-light,
  .dark-layout .footer-qla-link .footer-qla-logo-light,
  .semi-dark-layout .footer-qla-link .footer-qla-logo-light {
    display: none;
  }

  body.dark-layout .footer-qla-link .footer-qla-logo-dark,
  body.semi-dark-layout .footer-qla-link .footer-qla-logo-dark,
  .dark-layout .footer-qla-link .footer-qla-logo-dark,
  .semi-dark-layout .footer-qla-link .footer-qla-logo-dark {
    display: inline-block;
  }
</style>
<button class="btn btn-primary btn-icon scroll-top" type="button"><i data-feather="arrow-up"></i></button>
<!-- END: Footer-->
