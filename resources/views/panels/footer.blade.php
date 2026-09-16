<!-- BEGIN: Footer-->
@php
  $copyrightYear = date('Y');
@endphp
<footer
  class="footer footer-light {{ $configData['footerType'] === 'footer-hidden' ? 'd-none' : '' }} {{ $configData['footerType'] }} {{ View::hasSection('footer-actions') ? 'screen-actions-footer' : '' }}">
  @hasSection('footer-actions')
    <div class="screen-footer-actions">
      @yield('footer-actions')
    </div>
  @else
  <p class="clearfix mb-0">
    <span class="float-md-start d-block text-start footer-app-version">eNalog.app v1.0</span>
    <span class="float-md-end d-block mt-25 text-end footer-copyright">
      <span>COPYRIGHT &copy; {{ $copyrightYear }}</span>
      <a href="https://qla.dev/" target="_blank" rel="noopener noreferrer" class="text-info footer-qla-link" aria-label="qla.dev">
        <img src="https://deklarant.ai/build/images/logo-qla.png" alt="qla.dev" class="footer-qla-logo footer-qla-logo-light">
        <img src="https://deklarant.ai/build/images/logo-qla-dark.png" alt="qla.dev" class="footer-qla-logo footer-qla-logo-dark">
      </a>
      <span>| All rights Reserved</span>
    </span>
  </p>
  @endif
</footer>
<style>
  .screen-footer-actions {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0.75rem;
  }

  footer.screen-actions-footer {
    position: fixed !important;
    right: 0;
    bottom: 0;
    left: 0;
    z-index: 1030;
    display: block !important;
    background: rgba(255, 255, 255, 0.96);
    border-top: 1px solid rgba(34, 41, 47, 0.08);
    box-shadow: 0 -4px 18px rgba(34, 41, 47, 0.08);
    -webkit-backdrop-filter: blur(10px);
    backdrop-filter: blur(10px);
  }

  body.dark-layout footer.screen-actions-footer,
  body.semi-dark-layout footer.screen-actions-footer {
    background: rgba(40, 48, 70, 0.96);
    border-top-color: rgba(184, 190, 220, 0.15);
  }

  .screen-footer-actions > .btn,
  .screen-footer-actions > form,
  .screen-footer-actions > form .btn {
    min-width: 12rem;
  }

  .footer-app-version {
    font-weight: 500;
    letter-spacing: 0.01em;
  }

  .footer-copyright {
    display: inline-flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.34rem;
    flex-wrap: wrap;
    line-height: 1.2;
  }

  .footer-qla-link .footer-qla-logo {
    height: 18px;
    width: auto;
    display: block;
    margin-bottom: 0;
  }

  .footer-qla-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
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

  @media (max-width: 480px) {
    .screen-footer-actions {
      display: grid;
      grid-template-columns: 1fr 1fr;
      padding-bottom: env(safe-area-inset-bottom, 0px);
    }

    .screen-footer-actions > .btn,
    .screen-footer-actions > form,
    .screen-footer-actions > form .btn {
      width: 100%;
      min-width: 0;
    }

    footer .clearfix {
      display: flex;
      align-items: center;
      justify-content: space-between;
      width: 100%;
      gap: 0.2rem;
      flex-wrap: nowrap;
      margin-bottom: 0;
      padding-left: 0;
      padding-right: 0;
      padding-bottom: calc(4.9rem + env(safe-area-inset-bottom, 0px));
    }

    footer .clearfix > span {
      float: none !important;
      display: flex !important;
      align-items: center;
      min-width: 0;
      margin-top: 0 !important;
    }

    .footer-app-version {
      flex: 0 0 auto;
      font-size: 1.12rem;
      line-height: 1.02;
    }

    footer .clearfix > span.text-end {
      flex: 1 1 auto;
      font-size: 0.72rem;
      line-height: 1.02;
      letter-spacing: -0.02em;
      justify-content: flex-end;
      text-align: right !important;
      white-space: nowrap;
    }

    .footer-copyright {
      gap: 0.24rem;
      flex-wrap: nowrap;
    }

    .footer-qla-link .footer-qla-logo {
      height: 14px;
    }
  }
</style>
<button class="btn btn-primary btn-icon scroll-top" type="button"><i data-feather="arrow-up"></i></button>
<!-- END: Footer-->
