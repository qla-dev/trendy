@extends('layouts/contentLayoutMaster')

@section('title', 'Moja NFC kartica')

@section('page-style')
  <style>
    .nfc-page {
      --nfc-navy: #4a5c75;
      --nfc-navy-deep: #22303f;
      --nfc-accent: #7367f0;
      --nfc-ok: #28c76f;
      --nfc-err: #ea5455;
      min-height: calc(100vh - 13rem);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 1.5rem 0 2.5rem;
    }

    .nfc-stage { width: 100%; max-width: 560px; }
    .nfc-view { display: none; flex-direction: column; align-items: center; animation: nfc-fade-in .45s ease both; }
    .nfc-page[data-state="idle"] .nfc-view--scan,
    .nfc-page[data-state="scanning"] .nfc-view--scan,
    .nfc-page[data-state="reading"] .nfc-view--scan,
    .nfc-page[data-state="error"] .nfc-view--scan,
    .nfc-page[data-state="linked"] .nfc-view--card { display: flex; }

    @keyframes nfc-fade-in { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: none; } }

    /* ---------- Scanner ---------- */
    .nfc-scanner {
      position: relative;
      width: min(78vw, 320px);
      aspect-ratio: 1;
      margin: 0 auto 2rem;
      display: grid;
      place-items: center;
    }

    .nfc-scanner__ring {
      position: absolute;
      inset: 0;
      border-radius: 50%;
      border: 2px solid rgba(115, 103, 240, .35);
      opacity: 0;
    }

    .nfc-page[data-state="scanning"] .nfc-scanner__ring { animation: nfc-ripple 2.4s cubic-bezier(.2, .6, .35, 1) infinite; }
    .nfc-scanner__ring:nth-child(2) { animation-delay: .8s !important; }
    .nfc-scanner__ring:nth-child(3) { animation-delay: 1.6s !important; }

    @keyframes nfc-ripple {
      0% { transform: scale(.35); opacity: .9; }
      100% { transform: scale(1.05); opacity: 0; }
    }

    .nfc-scanner__radar {
      position: absolute;
      inset: 12%;
      border-radius: 50%;
      background:
        radial-gradient(circle, transparent 0 58%, rgba(115, 103, 240, .08) 58% 59%, transparent 59% 78%, rgba(115, 103, 240, .08) 78% 79%, transparent 79%),
        conic-gradient(from 0deg, rgba(115, 103, 240, 0) 0deg, rgba(115, 103, 240, 0) 290deg, rgba(115, 103, 240, .45) 360deg);
      opacity: 0;
      transition: opacity .4s;
    }

    .nfc-page[data-state="scanning"] .nfc-scanner__radar { opacity: 1; animation: nfc-spin 2.2s linear infinite; }
    @keyframes nfc-spin { to { transform: rotate(360deg); } }

    .nfc-scanner__core {
      position: relative;
      width: 46%;
      aspect-ratio: 1;
      border-radius: 50%;
      display: grid;
      place-items: center;
      color: #fff;
      background: linear-gradient(145deg, var(--nfc-accent), #9e95f5);
      box-shadow: 0 18px 40px -12px rgba(115, 103, 240, .65), inset 0 2px 0 rgba(255, 255, 255, .25);
      transition: transform .35s, background .35s, box-shadow .35s;
      cursor: pointer;
      border: 0;
    }

    .nfc-scanner__core svg { width: 46%; height: 46%; }
    .nfc-page[data-state="idle"] .nfc-scanner__core { animation: nfc-breathe 2.6s ease-in-out infinite; }
    .nfc-page[data-state="scanning"] .nfc-scanner__core { cursor: default; }
    @keyframes nfc-breathe { 50% { transform: scale(1.06); } }

    .nfc-page[data-state="reading"] .nfc-scanner__core {
      background: linear-gradient(145deg, var(--nfc-ok), #48da89);
      box-shadow: 0 18px 40px -12px rgba(40, 199, 111, .7);
      transform: scale(1.1);
    }

    .nfc-page[data-state="error"] .nfc-scanner__core {
      background: linear-gradient(145deg, var(--nfc-err), #f08182);
      box-shadow: 0 18px 40px -12px rgba(234, 84, 85, .6);
      animation: nfc-shake .45s;
    }

    @keyframes nfc-shake { 20%, 60% { transform: translateX(-8px); } 40%, 80% { transform: translateX(8px); } }

    /* little card that floats towards the reader while scanning */
    .nfc-scanner__ghost {
      position: absolute;
      width: 38%;
      aspect-ratio: 1.586;
      border-radius: 9px;
      background: linear-gradient(135deg, var(--nfc-navy), var(--nfc-navy-deep));
      box-shadow: 0 10px 24px -8px rgba(0, 0, 0, .45);
      right: -4%;
      top: 4%;
      opacity: 0;
      pointer-events: none;
    }

    .nfc-scanner__ghost::after {
      content: '';
      position: absolute;
      left: 12%;
      top: 30%;
      width: 18%;
      height: 26%;
      border-radius: 3px;
      background: linear-gradient(135deg, #e7c77d, #b8913a);
    }

    .nfc-page[data-state="scanning"] .nfc-scanner__ghost { animation: nfc-tap 3.2s ease-in-out infinite; }

    @keyframes nfc-tap {
      0% { opacity: 0; transform: translate(30%, -20%) rotate(18deg); }
      25% { opacity: 1; }
      50% { opacity: 1; transform: translate(-62%, 62%) rotate(-6deg); }
      70% { opacity: 1; transform: translate(-62%, 62%) rotate(-6deg); }
      100% { opacity: 0; transform: translate(30%, -20%) rotate(18deg); }
    }

    .nfc-title { font-size: 1.65rem; font-weight: 700; margin-bottom: .4rem; }
    .nfc-subtitle { color: #6e6b7b; max-width: 400px; margin: 0 auto 1.4rem; min-height: 3em; }

    .nfc-uid-live {
      font-family: "SFMono-Regular", Consolas, "Liberation Mono", monospace;
      font-size: 1.6rem;
      letter-spacing: .18em;
      font-weight: 700;
      color: var(--nfc-ok);
      min-height: 2.2rem;
      margin-bottom: 1rem;
    }

    .nfc-uid-live span { display: inline-block; animation: nfc-digit .3s ease both; }
    @keyframes nfc-digit { from { opacity: 0; transform: translateY(-10px) scale(1.4); filter: blur(3px); } to { opacity: 1; transform: none; filter: none; } }

    .nfc-actions { display: flex; flex-wrap: wrap; gap: .6rem; justify-content: center; }

    .nfc-manual { margin-top: 1.25rem; width: 100%; max-width: 360px; display: none; }
    .nfc-manual.is-open { display: block; animation: nfc-fade-in .3s ease both; }

    .nfc-support-note { margin-top: 1.25rem; font-size: .85rem; color: #b9b9c3; }

    /* hidden input that catches keyboard-wedge readers */
    .nfc-wedge { position: absolute; left: -9999px; opacity: 0; width: 1px; height: 1px; }

    /* ---------- 3D card ---------- */
    .nfc-card-scene {
      perspective: 1400px;
      width: min(88vw, 420px);
      aspect-ratio: 1.586;
      margin: .5rem auto 2.25rem;
      cursor: pointer;
    }

    .nfc-card {
      position: relative;
      width: 100%;
      height: 100%;
      transform-style: preserve-3d;
      animation: nfc-float 7s ease-in-out infinite;
    }

    .nfc-card.is-entering { animation: nfc-enter 1.5s cubic-bezier(.2, .8, .2, 1) both, nfc-float 7s ease-in-out 1.5s infinite; }
    .nfc-card-scene.is-flipped .nfc-card { animation: none; transform: rotateY(180deg); transition: transform .9s cubic-bezier(.3, 1.3, .5, 1); }
    .nfc-card-scene:not(.is-flipped) .nfc-card { transition: transform .9s cubic-bezier(.3, 1.3, .5, 1); }

    @keyframes nfc-float {
      0%, 100% { transform: rotateY(-16deg) rotateX(8deg) translateY(0); }
      50% { transform: rotateY(16deg) rotateX(-4deg) translateY(-10px); }
    }

    @keyframes nfc-enter {
      0% { transform: translateY(60px) rotateY(-540deg) rotateX(30deg) scale(.4); opacity: 0; }
      60% { opacity: 1; }
      100% { transform: rotateY(-16deg) rotateX(8deg) scale(1); opacity: 1; }
    }

    .nfc-card__face {
      position: absolute;
      inset: 0;
      border-radius: 18px;
      overflow: hidden;
      backface-visibility: hidden;
      -webkit-backface-visibility: hidden;
      color: #fff;
      text-align: left;
      box-shadow: 0 30px 60px -20px rgba(34, 48, 63, .75), inset 0 1px 0 rgba(255, 255, 255, .2);
      background:
        radial-gradient(120% 90% at 100% 0%, rgba(158, 149, 245, .55), transparent 55%),
        radial-gradient(90% 80% at 0% 100%, rgba(115, 103, 240, .35), transparent 60%),
        linear-gradient(135deg, var(--nfc-navy) 0%, var(--nfc-navy-deep) 100%);
    }

    /* moving sheen */
    .nfc-card__face::before {
      content: '';
      position: absolute;
      inset: -50%;
      background: linear-gradient(115deg, transparent 40%, rgba(255, 255, 255, .22) 50%, transparent 60%);
      animation: nfc-sheen 5s ease-in-out infinite;
      pointer-events: none;
    }

    @keyframes nfc-sheen { 0%, 30% { transform: translateX(-60%); } 70%, 100% { transform: translateX(60%); } }

    /* fine guilloche lines */
    .nfc-card__face::after {
      content: '';
      position: absolute;
      inset: 0;
      background: repeating-radial-gradient(circle at 85% 120%, rgba(255, 255, 255, .05) 0 1px, transparent 1px 9px);
      pointer-events: none;
    }

    .nfc-card__front { padding: 6% 7%; }
    .nfc-card__back { transform: rotateY(180deg); padding: 7%; display: flex; flex-direction: column; justify-content: center; }

    .nfc-card__mark {
      position: absolute;
      right: 5%;
      top: 7%;
      width: 30%;
      opacity: .95;
      filter: drop-shadow(0 4px 10px rgba(0, 0, 0, .25));
    }

    .nfc-card__brand { font-weight: 800; letter-spacing: .28em; font-size: clamp(.75rem, 3.2vw, 1rem); opacity: .9; }
    .nfc-card__brand small { display: block; letter-spacing: .2em; font-weight: 500; opacity: .7; font-size: .7em; margin-top: 2px; }

    .nfc-card__chip {
      position: absolute;
      left: 7%;
      top: 38%;
      width: 15%;
      aspect-ratio: 1.3;
      border-radius: 7px;
      background:
        linear-gradient(90deg, transparent 32%, rgba(0, 0, 0, .22) 32% 35%, transparent 35% 65%, rgba(0, 0, 0, .22) 65% 68%, transparent 68%),
        linear-gradient(0deg, transparent 45%, rgba(0, 0, 0, .22) 45% 55%, transparent 55%),
        linear-gradient(135deg, #f3dc9b, #c9a24a 55%, #9c7a2c);
      box-shadow: inset 0 0 0 1px rgba(0, 0, 0, .15);
    }

    .nfc-card__waves { position: absolute; left: 25%; top: 40%; width: 8%; opacity: .85; }

    .nfc-card__uid {
      position: absolute;
      left: 7%;
      bottom: 24%;
      font-family: "SFMono-Regular", Consolas, "Liberation Mono", monospace;
      font-size: clamp(1rem, 4.6vw, 1.45rem);
      letter-spacing: .16em;
      text-shadow: 0 2px 4px rgba(0, 0, 0, .35);
    }

    .nfc-card__holder { position: absolute; left: 7%; bottom: 8%; right: 7%; display: flex; justify-content: space-between; align-items: flex-end; }
    .nfc-card__label { display: block; font-size: .6rem; text-transform: uppercase; letter-spacing: .14em; opacity: .65; }
    .nfc-card__value { font-weight: 600; font-size: clamp(.8rem, 3.4vw, 1rem); text-transform: uppercase; letter-spacing: .06em; }

    .nfc-card__stripe { position: absolute; left: 0; right: 0; top: 12%; height: 18%; background: rgba(0, 0, 0, .55); }
    .nfc-card__back-row { margin-top: 18%; display: grid; gap: .8rem; }

    .nfc-card-status {
      display: inline-flex;
      align-items: center;
      gap: .45rem;
      padding: .35rem .9rem;
      border-radius: 999px;
      background: rgba(40, 199, 111, .12);
      color: var(--nfc-ok);
      font-weight: 600;
      margin-bottom: 1rem;
    }

    .nfc-card-status::before { content: ''; width: 8px; height: 8px; border-radius: 50%; background: currentColor; box-shadow: 0 0 0 0 currentColor; animation: nfc-dot 1.8s infinite; }
    @keyframes nfc-dot { 0% { box-shadow: 0 0 0 0 rgba(40, 199, 111, .6); } 100% { box-shadow: 0 0 0 10px rgba(40, 199, 111, 0); } }

    .nfc-hint { color: #b9b9c3; font-size: .85rem; margin-top: -1.25rem; margin-bottom: 1.5rem; }

    /* success burst */
    .nfc-burst { position: fixed; inset: 0; pointer-events: none; z-index: 2000; overflow: hidden; }
    .nfc-burst i {
      position: absolute;
      width: 9px;
      height: 14px;
      border-radius: 2px;
      animation: nfc-confetti 1.6s cubic-bezier(.2, .7, .4, 1) forwards;
    }

    @keyframes nfc-confetti {
      0% { transform: translate(0, 0) rotate(0); opacity: 1; }
      100% { transform: translate(var(--dx), var(--dy)) rotate(var(--rot)); opacity: 0; }
    }

    .dark-layout .nfc-subtitle { color: #b4b7bd; }

    @media (prefers-reduced-motion: reduce) {
      .nfc-page *, .nfc-page *::before, .nfc-page *::after { animation-duration: .01ms !important; animation-iteration-count: 1 !important; }
    }
  </style>
@endsection

@section('content')
  @php
    $holderName = trim((string) ($nfcUser->name ?? $nfcUser->username ?? ''));
  @endphp

  <section
    class="nfc-page"
    id="nfc-page"
    data-state="{{ $nfcCard ? 'linked' : 'idle' }}"
    data-store-url="{{ route('app-nfc-card-store') }}"
    data-destroy-url="{{ route('app-nfc-card-destroy') }}">

    <div class="nfc-stage">
      {{-- ================= Scanner ================= --}}
      <div class="nfc-view nfc-view--scan">
        <div class="nfc-scanner">
          <span class="nfc-scanner__ring"></span>
          <span class="nfc-scanner__ring"></span>
          <span class="nfc-scanner__ring"></span>
          <span class="nfc-scanner__radar"></span>
          <span class="nfc-scanner__ghost"></span>
          <button type="button" class="nfc-scanner__core" id="nfc-core" aria-label="Pokreni skeniranje">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M6 8.32a7.43 7.43 0 0 1 0 7.36"/>
              <path d="M9.46 6.21a11.76 11.76 0 0 1 0 11.58"/>
              <path d="M12.91 4.1a15.91 15.91 0 0 1 .01 15.8"/>
              <path d="M16.37 2a20.16 20.16 0 0 1 0 20"/>
            </svg>
          </button>
        </div>

        <h2 class="nfc-title" id="nfc-title">Povežite svoju NFC karticu</h2>
        <p class="nfc-subtitle" id="nfc-subtitle">Dodirnite krug da pokrenete skener, zatim prislonite karticu na poleđinu uređaja.</p>
        <div class="nfc-uid-live" id="nfc-uid-live" aria-live="polite"></div>

        <div class="nfc-actions">
          <button type="button" class="btn btn-primary" id="nfc-start">
            <i data-feather="radio" class="me-50"></i>Pokreni skener
          </button>
          <button type="button" class="btn btn-outline-secondary" id="nfc-manual-toggle">
            <i data-feather="edit-3" class="me-50"></i>Unesi ručno
          </button>
          <button type="button" class="btn btn-flat-secondary d-none" id="nfc-cancel-replace">Odustani</button>
        </div>

        <form class="nfc-manual" id="nfc-manual" autocomplete="off">
          <div class="input-group">
            <input type="text" class="form-control text-uppercase" id="nfc-manual-input" placeholder="npr. C36E1C28" maxlength="64" spellcheck="false">
            <button class="btn btn-primary" type="submit">Spasi</button>
          </div>
        </form>

        <div class="nfc-support-note" id="nfc-support-note"></div>
        <input type="text" class="nfc-wedge" id="nfc-wedge" aria-hidden="true" tabindex="-1" autocomplete="off">
      </div>

      {{-- ================= Linked card ================= --}}
      <div class="nfc-view nfc-view--card">
        <div class="nfc-card-status">Kartica je aktivna</div>

        <div class="nfc-card-scene" id="nfc-card-scene" title="Dodirnite za okretanje">
          <div class="nfc-card" id="nfc-card">
            <div class="nfc-card__face nfc-card__front">
              <div class="nfc-card__brand">TRENDY<small>CNC · eNalog</small></div>
              <img class="nfc-card__mark" src="{{ asset('images/pwa/trendy-mark-white.png') }}" alt="">
              <span class="nfc-card__chip"></span>
              <svg class="nfc-card__waves" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                <path d="M6 8.32a7.43 7.43 0 0 1 0 7.36"/><path d="M9.46 6.21a11.76 11.76 0 0 1 0 11.58"/><path d="M12.91 4.1a15.91 15.91 0 0 1 .01 15.8"/>
              </svg>
              <div class="nfc-card__uid" data-card-uid>{{ $nfcCard['uid_display'] ?? '' }}</div>
              <div class="nfc-card__holder">
                <div>
                  <span class="nfc-card__label">Korisnik</span>
                  <span class="nfc-card__value">{{ $holderName }}</span>
                </div>
                <div class="text-end">
                  <span class="nfc-card__label">Uloga</span>
                  <span class="nfc-card__value">{{ $nfcUser->role }}</span>
                </div>
              </div>
            </div>
            <div class="nfc-card__face nfc-card__back">
              <span class="nfc-card__stripe"></span>
              <div class="nfc-card__back-row">
                <div>
                  <span class="nfc-card__label">UID kartice</span>
                  <span class="nfc-card__value" style="font-family: Consolas, monospace; letter-spacing: .14em;" data-card-uid>{{ $nfcCard['uid_display'] ?? '' }}</span>
                </div>
                <div>
                  <span class="nfc-card__label">Povezana</span>
                  <span class="nfc-card__value" data-card-linked>{{ $nfcCard['linked_at'] ?? '' }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="nfc-hint">Dodirnite karticu da je okrenete</div>

        <div class="nfc-actions">
          <button type="button" class="btn btn-outline-primary" id="nfc-replace">
            <i data-feather="refresh-cw" class="me-50"></i>Zamijeni karticu
          </button>
          <button type="button" class="btn btn-outline-danger" id="nfc-remove">
            <i data-feather="trash-2" class="me-50"></i>Ukloni
          </button>
        </div>
      </div>
    </div>
  </section>
@endsection

@section('page-script')
  <script>
    (function () {
      'use strict';

      var page = document.getElementById('nfc-page');
      if (!page) return;

      var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
      var title = document.getElementById('nfc-title');
      var subtitle = document.getElementById('nfc-subtitle');
      var uidLive = document.getElementById('nfc-uid-live');
      var startBtn = document.getElementById('nfc-start');
      var core = document.getElementById('nfc-core');
      var supportNote = document.getElementById('nfc-support-note');
      var manualForm = document.getElementById('nfc-manual');
      var manualInput = document.getElementById('nfc-manual-input');
      var wedge = document.getElementById('nfc-wedge');
      var cardScene = document.getElementById('nfc-card-scene');
      var card = document.getElementById('nfc-card');
      var cancelReplace = document.getElementById('nfc-cancel-replace');

      var hasWebNfc = 'NDEFReader' in window;
      var abortCtrl = null;
      var saving = false;
      var hadCardBeforeReplace = page.dataset.state === 'linked';

      function setState(state, heading, text) {
        page.dataset.state = state;
        if (heading) title.textContent = heading;
        if (text !== undefined) subtitle.textContent = text;
        startBtn.classList.toggle('d-none', state === 'scanning' || state === 'reading');
      }

      function normalizeUid(raw) {
        return String(raw || '').replace(/[^0-9a-f]/gi, '').toLowerCase();
      }

      function formatUid(uid) {
        return (uid.toUpperCase().match(/.{1,2}/g) || []).join(' ');
      }

      function typeUid(uid) {
        uidLive.innerHTML = '';
        formatUid(uid).split('').forEach(function (ch, i) {
          var s = document.createElement('span');
          s.textContent = ch === ' ' ? ' ' : ch;
          s.style.animationDelay = (i * 45) + 'ms';
          uidLive.appendChild(s);
        });
      }

      function vibrate(pattern) {
        try { navigator.vibrate && navigator.vibrate(pattern); } catch (e) {}
      }

      function burst() {
        var layer = document.createElement('div');
        layer.className = 'nfc-burst';
        var colors = ['#7367f0', '#28c76f', '#ff9f43', '#00cfe8', '#4a5c75', '#e7c77d'];
        var rect = cardScene.getBoundingClientRect();
        var cx = rect.left + rect.width / 2;
        var cy = rect.top + rect.height / 2;
        for (var i = 0; i < 70; i++) {
          var p = document.createElement('i');
          var angle = Math.random() * Math.PI * 2;
          var dist = 140 + Math.random() * 260;
          p.style.left = cx + 'px';
          p.style.top = cy + 'px';
          p.style.background = colors[i % colors.length];
          p.style.setProperty('--dx', Math.cos(angle) * dist + 'px');
          p.style.setProperty('--dy', Math.sin(angle) * dist + 120 + 'px');
          p.style.setProperty('--rot', (Math.random() * 900 - 450) + 'deg');
          p.style.animationDelay = (Math.random() * 120) + 'ms';
          layer.appendChild(p);
        }
        document.body.appendChild(layer);
        setTimeout(function () { layer.remove(); }, 2000);
      }

      function stopScan() {
        if (abortCtrl) {
          try { abortCtrl.abort(); } catch (e) {}
          abortCtrl = null;
        }
      }

      function showCard(cardData, animate) {
        document.querySelectorAll('[data-card-uid]').forEach(function (el) { el.textContent = cardData.uid_display; });
        document.querySelectorAll('[data-card-linked]').forEach(function (el) { el.textContent = cardData.linked_at || ''; });
        cardScene.classList.remove('is-flipped');
        setState('linked');
        hadCardBeforeReplace = true;
        cancelReplace.classList.add('d-none');
        if (animate) {
          card.classList.remove('is-entering');
          void card.offsetWidth;
          card.classList.add('is-entering');
          setTimeout(burst, 650);
        }
      }

      function request(method, url, body) {
        return fetch(url, {
          method: method,
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrf,
            'X-Requested-With': 'XMLHttpRequest'
          },
          credentials: 'same-origin',
          body: body ? JSON.stringify(body) : undefined
        }).then(function (res) {
          return res.json().catch(function () { return {}; }).then(function (data) {
            if (!res.ok) {
              var err = new Error(data.message || 'Greška pri spašavanju (' + res.status + ').');
              throw err;
            }
            return data;
          });
        });
      }

      function saveUid(raw) {
        var uid = normalizeUid(raw);
        if (saving) return;
        if (uid.length < 8 || uid.length % 2 !== 0) {
          showError('Očitani broj nije ispravan UID kartice.');
          return;
        }

        saving = true;
        stopScan();
        vibrate([60, 40, 60]);
        typeUid(uid);
        setState('reading', 'Kartica očitana', 'Spašavam karticu na vaš profil…');

        request('POST', page.dataset.storeUrl, { uid: uid })
          .then(function (data) {
            setTimeout(function () { showCard(data.card, true); }, 700);
          })
          .catch(function (err) {
            vibrate([200]);
            showError(err.message);
          })
          .finally(function () { saving = false; });
      }

      function showError(message) {
        setState('error', 'Nije uspjelo', message);
        setTimeout(function () {
          if (page.dataset.state === 'error') startScan();
        }, 2600);
      }

      function startScan() {
        uidLive.innerHTML = '';
        focusWedge();

        if (!hasWebNfc) {
          setState('scanning', 'Prislonite karticu', 'Čekam karticu sa čitača… (ili unesite UID ručno)');
          return;
        }

        stopScan();
        abortCtrl = new AbortController();
        var reader = new NDEFReader();

        reader.scan({ signal: abortCtrl.signal }).then(function () {
          setState('scanning', 'Prislonite karticu', 'Skener je aktivan. Prislonite karticu na poleđinu uređaja.');

          reader.onreading = function (event) {
            if (event.serialNumber) {
              saveUid(event.serialNumber);
            } else {
              showError('Kartica je očitana, ali nije vratila UID. Unesite ga ručno.');
            }
          };

          reader.onreadingerror = function () {
            showError('Kartica nije podržana u browseru. Pokušajte ponovo ili unesite UID ručno.');
          };
        }).catch(function (err) {
          abortCtrl = null;
          if (err && err.name === 'AbortError') return;
          var msg = err && err.name === 'NotAllowedError'
            ? 'Pristup NFC-u je odbijen. Dozvolite NFC za ovu stranicu u postavkama browsera.'
            : 'NFC nije dostupan. Provjerite da je NFC uključen na uređaju.';
          setState('error', 'NFC nije pokrenut', msg);
        });
      }

      // Keyboard-wedge readers "type" the UID and press Enter.
      function focusWedge() {
        if (document.activeElement === manualInput) return;
        try { wedge.focus({ preventScroll: true }); } catch (e) { wedge.focus(); }
      }

      wedge.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          var value = wedge.value;
          wedge.value = '';
          if (normalizeUid(value).length >= 8) saveUid(value);
        }
      });

      document.addEventListener('click', function (e) {
        var scanStates = ['scanning', 'error', 'idle'];
        if (scanStates.indexOf(page.dataset.state) !== -1 && !e.target.closest('input, button, a, form')) focusWedge();
      });

      startBtn.addEventListener('click', startScan);
      core.addEventListener('click', function () {
        if (page.dataset.state !== 'scanning' && page.dataset.state !== 'reading') startScan();
      });

      document.getElementById('nfc-manual-toggle').addEventListener('click', function () {
        manualForm.classList.toggle('is-open');
        if (manualForm.classList.contains('is-open')) manualInput.focus();
      });

      manualForm.addEventListener('submit', function (e) {
        e.preventDefault();
        saveUid(manualInput.value);
        manualInput.value = '';
        manualForm.classList.remove('is-open');
      });

      cardScene.addEventListener('click', function () {
        card.classList.remove('is-entering');
        cardScene.classList.toggle('is-flipped');
      });

      document.getElementById('nfc-replace').addEventListener('click', function () {
        cancelReplace.classList.remove('d-none');
        setState('idle', 'Prislonite novu karticu', 'Nova kartica će zamijeniti postojeću.');
        startScan();
      });

      cancelReplace.addEventListener('click', function () {
        stopScan();
        if (hadCardBeforeReplace) {
          cancelReplace.classList.add('d-none');
          setState('linked');
        }
      });

      document.getElementById('nfc-remove').addEventListener('click', function () {
        if (!window.confirm('Ukloniti NFC karticu sa vašeg profila?')) return;
        request('DELETE', page.dataset.destroyUrl).then(function () {
          hadCardBeforeReplace = false;
          setState('idle', 'Povežite svoju NFC karticu', 'Kartica je uklonjena. Dodirnite krug da povežete novu.');
        }).catch(function (err) {
          window.alert(err.message);
        });
      });

      document.addEventListener('visibilitychange', function () {
        if (document.hidden) stopScan();
        else if (page.dataset.state === 'scanning') startScan();
      });

      supportNote.textContent = hasWebNfc
        ? ''
        : 'Ovaj browser nema Web NFC (radi u Chrome-u na Androidu preko HTTPS-a). Čitač koji "kuca" UID ili ručni unos i dalje rade.';

      // Auto-start when the user opens the page without a linked card.
      if (page.dataset.state === 'idle') {
        if (!hasWebNfc) {
          startScan();
        } else if (navigator.permissions && navigator.permissions.query) {
          navigator.permissions.query({ name: 'nfc' }).then(function (status) {
            if (status.state === 'granted') startScan();
          }).catch(function () {});
        }
      }
    })();
  </script>
@endsection
