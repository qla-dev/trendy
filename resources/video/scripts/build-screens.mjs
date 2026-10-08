// Wraps each content snippet in screens-src/ into the real eNalog.app page shell
// (navbar, sidebar, breadcrumbs, compiled Vuexy CSS) -> public/app/screens/*.html
import {readFileSync, readdirSync, writeFileSync, mkdirSync} from 'node:fs';

const SRC = new URL('../screens-src/', import.meta.url);
const OUT = new URL('../public/app/screens/', import.meta.url);
mkdirSync(OUT, {recursive: true});

const MENU = [
  ['h', 'Radni nalozi'],
  ['nalozi', 'file-text', 'Upravljanje nalozima'],
  ['skeniraj', 'camera', 'Skeniraj nalog'],
  ['h', 'Narudžbe'],
  ['narudzbe', 'briefcase', 'Upravljanje narudžbama'],
  ['ai', 'fa', 'AI narudžba'],
  ['inbox', 'inbox', 'AI Inbox'],
  ['h', 'Proizvodnja'],
  ['plan', 'clipboard', 'Plan proizvodnje'],
  ['h', 'AI asistent'],
  ['historija', 'activity', 'Historija AI skeniranja'],
  ['posiljaoci', 'shield', 'Dozvoljeni pošiljaoci'],
  ['h', 'Dokumenti'],
  ['razduzeni', 'archive', 'Razduženi materijali'],
  ['wip', 'box', 'Razduživanje WIP'],
  ['operacije', 'tool', 'Razdužene operacije'],
  ['prijem', 'package', 'Prijem VP skladište'],
  ['skart', 'package', 'Prijem škarta'],
  ['h', 'Administracija'],
  ['korisnici', 'user', 'Korisnici'],
  ['nfc', 'credit-card', 'Moja NFC kartica'],
  ['h', 'Skladišni alati'],
  ['zalihe', 'layers', 'Pregled zaliha'],
  ['zastite', 'shield', 'Površinske zaštite'],
  ['rezerve', 'shield', 'Kreiranje rezervi'],
  ['mapa', 'map', 'Mapa skladišta'],
];

const sidebar = (active) => `
<div class="main-menu menu-fixed menu-light menu-accordion menu-shadow">
  <div class="navbar-header">
    <ul class="nav navbar-nav flex-row">
      <li class="nav-item me-auto">
        <a class="navbar-brand d-flex align-items-center" href="#">
          <span class="brand-logo"><img src="../images/logo/TrendyCNC.png" alt="eNalog.app" width="36"></span>
          <h2 class="brand-text mb-0 ms-50">eNalog.app</h2>
        </a>
      </li>
      <li class="nav-item nav-toggle"><a class="nav-link modern-nav-toggle pe-0"><i class="d-none d-xl-block collapse-toggle-icon font-medium-4 text-primary" data-feather="disc"></i></a></li>
    </ul>
  </div>
  <div class="shadow-bottom"></div>
  <div class="main-menu-content">
    <ul class="navigation navigation-main">
${MENU.map(([k, icon, label]) =>
  k === 'h'
    ? `      <li class="navigation-header"><span>${icon}</span><i data-feather="more-horizontal"></i></li>`
    : `      <li class="nav-item${k === active ? ' active' : ''}"><a href="#" class="d-flex align-items-center">${
        icon === 'fa' ? '<i class="fa fa-magic fa-fw"></i>' : `<i data-feather="${icon}"></i>`
      }<span class="menu-title text-truncate">${label}</span></a></li>`,
).join('\n')}
    </ul>
  </div>
</div>`;

const navbar = (mobile) => `
<nav class="header-navbar navbar navbar-expand-lg align-items-center floating-nav navbar-light navbar-shadow">
  <div class="navbar-container d-flex content">
    <div class="bookmark-wrapper d-flex align-items-center">
      <ul class="nav navbar-nav d-xl-none align-items-center">
        <li class="nav-item"><a class="nav-link menu-toggle" href="#"><i class="ficon" data-feather="menu"></i></a></li>
        <li class="nav-item d-md-none"><a class="nav-link" href="#"><i class="ficon" data-feather="camera"></i></a></li>
      </ul>
      <ul class="nav navbar-nav bookmark-icons">
        <li class="nav-item d-none d-md-block"><a class="nav-link" href="#"><i class="ficon" data-feather="home"></i></a></li>
        <li class="nav-item d-none d-md-block"><a class="nav-link" href="#"><i class="ficon" data-feather="calendar"></i></a></li>
        <li class="nav-item d-none d-md-block"><a class="nav-link" href="#"><i class="ficon" data-feather="camera"></i></a></li>
      </ul>
    </div>
    <ul class="nav navbar-nav align-items-center ms-auto">
      <li class="nav-item">
        <a class="nav-link navbar-ai-token-link" href="#">
          <span class="navbar-ai-token-pill">
            <span class="navbar-ai-token-pill__label">Tokeni</span>
            <span class="navbar-ai-token-pill__icon"><span class="navbar-ai-token-coin navbar-ai-token-coin--top"></span><span class="navbar-ai-token-coin navbar-ai-token-coin--mid"></span><span class="navbar-ai-token-coin navbar-ai-token-coin--bottom"></span></span>
            <span class="navbar-ai-token-pill__divider">|</span>
            <span class="navbar-ai-token-pill__value">12.480</span>
          </span>
        </a>
      </li>
      ${mobile ? '' : '<li class="nav-item d-none d-md-block"><a class="nav-link nav-link-style"><i class="ficon" data-feather="moon"></i></a></li>'}
      <li class="nav-item nav-search"><a class="nav-link nav-link-search"><i class="ficon" data-feather="search"></i></a></li>
      <li class="nav-item dropdown dropdown-user">
        <a class="nav-link dropdown-toggle dropdown-user-link" href="#">
          <div class="user-nav d-sm-flex d-none"><span class="user-name fw-bolder">Admin</span><span class="user-status">ADMIN</span></div>
          <span class="avatar"><img class="round" src="../images/portrait/avatar-s-22.jpg" alt="avatar" height="40" width="40"><span class="avatar-status-online"></span></span>
        </a>
      </li>
    </ul>
  </div>
</nav>`;

const page = ({title, crumbs, active, mobile, bare, noHeader, css = [], style = '', body}) => `<!DOCTYPE html>
<html class="loaded light-layout" lang="bs" data-textdirection="ltr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>${title}</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=block" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
<link rel="stylesheet" href="../vendors/css/vendors.min.css">
<link rel="stylesheet" href="../css/core.css">
<link rel="stylesheet" href="../css/base/core/menu/menu-types/vertical-menu.css">
${css.map((c) => `<link rel="stylesheet" href="../${c}">`).join('\n')}
<link rel="stylesheet" href="../css/overrides.css">
<style>html .content .content-wrapper .content-header-title{border-right:0!important;padding-right:0!important;margin-right:0!important}</style>
<link rel="stylesheet" href="../css/style.css">
<style>
.navbar-ai-token-link{padding-left:.2rem!important;padding-right:.2rem!important}
.navbar-ai-token-pill{position:relative;display:inline-flex;align-items:center;gap:.45rem;min-height:2.7rem;padding:.38rem .8rem;border-radius:1rem;background:rgba(255,255,255,.95);box-shadow:0 10px 24px rgba(34,41,47,.08)}
.navbar-ai-token-pill__icon{position:relative;display:inline-grid;width:1.3rem;height:1.35rem;place-items:center;flex:0 0 auto}
.navbar-ai-token-pill__label,.navbar-ai-token-pill__divider{display:inline-flex;align-items:center;line-height:1;color:#6e7f91;font-size:.92rem;font-weight:600}
.navbar-ai-token-pill__divider{opacity:.7}
.navbar-ai-token-pill__value{display:inline-flex;align-items:center;justify-content:center;min-width:2.2rem;font-size:1rem;font-weight:700;line-height:1;color:#4b5d78}
.navbar-ai-token-coin{position:absolute;width:.95rem;height:.42rem;border-radius:999px;background:linear-gradient(180deg,#ffd86f 0%,#f5b301 100%);box-shadow:inset 0 1px 0 rgba(255,255,255,.5),0 2px 4px rgba(196,140,0,.2)}
.navbar-ai-token-coin--top{transform:translateY(-.34rem)}
.navbar-ai-token-coin--mid{width:1.05rem}
.navbar-ai-token-coin--bottom{transform:translateY(.34rem)}
html,body{overflow:hidden}
.pace,.pace-progress{display:none!important}
*,*::before,*::after{animation-duration:.001s!important;animation-delay:0s!important;animation-iteration-count:1!important;transition:none!important}
${style}
</style>
</head>
<body class="vertical-layout ${mobile ? 'vertical-overlay-menu menu-hide' : 'vertical-menu-modern menu-expanded'} navbar-floating footer-static default">
${bare ? body : `${navbar(mobile)}
${mobile ? '' : sidebar(active)}
<div class="app-content content">
  <div class="content-overlay"></div>
  <div class="header-navbar-shadow"></div>
  <div class="content-wrapper container-xxxl p-0">
    ${noHeader ? '' : `<div class="content-header row">
      <div class="content-header-left col-md-9 col-12 mb-2">
        <div class="row breadcrumbs-top"><div class="col-12">
          <h2 class="content-header-title float-start mb-0">${title}</h2>
          <div class="breadcrumb-wrapper"><ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="#">Početna</a></li>
            ${(crumbs || []).map((c) => `<li class="breadcrumb-item">${c}</li>`).join('')}
          </ol></div>
        </div></div>
      </div>
    </div>`}
    <div class="content-body">
${body}
    </div>
  </div>
</div>`}
<script src="../vendors/js/vendors.min.js"></script>
<script>feather.replace({width: 14, height: 14});</script>
</body>
</html>`;

const VIEWS = new URL('../../views/content/', import.meta.url);
// Blade control lines only — CSS at-rules (@media, @keyframes) must survive.
const BLADE_DIRECTIVE = /^\s*@(?:if|else|elseif|endif|php|endphp|foreach|endforeach|isset|endisset|can|endcan|auth|endauth|unless|endunless)\b.*$/gm;
// Pulls every <style> block straight out of the real Blade view, resolving the few Blade echoes.
const bladeStyles = (rel) => {
  const src = readFileSync(new URL(rel, VIEWS), 'utf8');
  let css = '';
  for (const m of src.matchAll(/<style[^>]*>([\s\S]*?)<\/style>/g)) css += m[1] + '\n';
  return css
    .replace(/\{\{\s*asset\(['"]([^'"]+)['"]\)\s*\}\}/g, '../$1')
    .replace(/\{\{[^}]*?'([^']*)'[^}]*\}\}/g, '$1')
    .replace(BLADE_DIRECTIVE, '');
};

// Each source file starts with a JSON header comment: <!--{"title":"...","active":"...","mobile":true}-->
for (const f of readdirSync(SRC).filter((x) => x.endsWith('.html'))) {
  const raw = readFileSync(new URL(f, SRC), 'utf8');
  const m = raw.match(/^<!--(\{[\s\S]*?\})-->\s*/);
  const meta = m ? JSON.parse(m[1]) : {};
  let rest = m ? raw.slice(m[0].length) : raw;
  let style = '';
  for (const b of meta.blade || []) style += bladeStyles(b);
  rest = rest.replace(/<style>([\s\S]*?)<\/style>/g, (_, css) => {
    style += css + '\n';
    return '';
  });
  rest = rest.replace(/(src|href)="\/?images\//g, '$1="../images/');
  writeFileSync(new URL(f, OUT), page({...meta, style, body: rest}));
  console.log('built', f);
}
