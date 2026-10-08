import React from 'react';
import {Ambient} from '../components/Kit';
import {AbsoluteFill, Img, staticFile, useCurrentFrame} from 'remotion';

import {Title} from '../components/Stage';
import {Charge, Finger, Float, Ico, In, Lift, LogoDot, MobileBar, PH_CENTER, PH_UP, Phone, Snd, T, Tick, Type, TypeSnd, phoneToVideo, useHome, useOut, usePose} from '../components/UI';
import {C, FONT, ease} from '../theme';

const SCAN = 54;
const WO = 72;
const OPS_AT = 160;
const TAPS = [186, 204, 222];
const SAVED = 240;
const OPS = [
  ['10', 'OP10', 'Lasersko rezanje', true],
  ['20', 'OP20', 'CNC savijanje', false],
  ['50', 'OP50', 'Bravarija', false],
  ['60', 'OP60', 'Kontrola', false],
] as const;
const LIFT = WO + 40;

// .wo-product-hero + progress, design 442 x 250
const Hero: React.FC = () => {
  const frame = useCurrentFrame();
  const pct = ease(frame, [WO + 30, WO + 75], [0, 45]);
  return (
    <div style={{width: 442, background: '#fff', borderRadius: 12, overflow: 'hidden', fontFamily: FONT, boxShadow: '0 4px 24px rgba(34,41,47,.08)'}}>
      <div style={{padding: '16px 18px', background: 'linear-gradient(180deg, rgba(115,103,240,.08), #fff)', display: 'flex', justifyContent: 'space-between', alignItems: 'flex-end'}}>
        <div>
          <div style={{fontSize: 10, fontWeight: 700, letterSpacing: 1.5, color: '#8a8798'}}>NAZIV PROIZVODA</div>
          <div style={{fontSize: 19, fontWeight: 800, color: T.head, marginTop: 3, minHeight: 24}}>
            <Type text="NOSAČ MOTORA 200x120" at={WO + 14} cps={40} />
          </div>
          <span style={{display: 'inline-flex', alignItems: 'center', gap: 6, marginTop: 6, padding: '4px 10px', borderRadius: 8, background: 'rgba(40,199,111,.14)', boxShadow: 'inset 0 0 0 1px rgba(40,199,111,.34)', fontSize: 10.5, fontWeight: 700, color: '#1f9d57'}}>
            <span style={{width: 7, height: 7, borderRadius: 4, background: C.green}} /> TR-NOS-200
          </span>
        </div>
        <div style={{textAlign: 'right'}}>
          <div style={{fontSize: 10, fontWeight: 700, letterSpacing: 1.5, color: '#8a8798'}}>KOLIČINA</div>
          <div style={{fontSize: 26, fontWeight: 800, color: T.head}}>
            120 <span style={{fontSize: 13}}>KOM</span>
          </div>
        </div>
      </div>
      <div style={{padding: '12px 18px 18px'}}>
        <div style={{display: 'flex', justifyContent: 'space-between', fontSize: 13, fontWeight: 600, color: T.head}}>
          <span>Realizacija po količini</span>
          <span>{Math.round(pct)} %</span>
        </div>
        <div style={{marginTop: 8}}>
          <Charge pct={pct} h={9} colors={['#00cfe8', '#28c76f']} />
        </div>
      </div>
    </div>
  );
};

const STOCK0 = 1252.9;
const PER_OP = 0.8;
const Consumption: React.FC = () => {
  const frame = useCurrentFrame();
  const used = TAPS.reduce((u, t) => u + ease(frame, [t, t + 18], [0, PER_OP]), 0);
  const stock = STOCK0 - used;
  const hit = TAPS.some((t) => frame >= t && frame < t + 18);
  return (
    <div style={{fontFamily: FONT}}>
      <div style={{display: 'flex', justifyContent: 'space-between', alignItems: 'center'}}>
        <span style={{fontSize: 12, fontWeight: 700, letterSpacing: 1, color: '#8a8798'}}>UTROŠAK MATERIJALA</span>
        <span style={{fontSize: 12, fontWeight: 700, color: C.navy}}>LIM-S235-5</span>
      </div>
      <div style={{display: 'flex', justifyContent: 'space-between', alignItems: 'baseline', marginTop: 8}}>
        <span style={{fontSize: 14, color: T.text}}>Zaliha</span>
        <span style={{fontSize: 30, fontWeight: 800, color: hit ? '#ea5455' : T.head, fontVariantNumeric: 'tabular-nums'}}>
          {stock.toLocaleString('de-DE', {minimumFractionDigits: 1, maximumFractionDigits: 1})} <span style={{fontSize: 14}}>KG</span>
        </span>
      </div>
      <div style={{display: 'flex', justifyContent: 'space-between', fontSize: 12, color: T.text, marginTop: 8}}>
        <span>Utrošeno</span>
        <b style={{color: T.head}}>
          {used.toLocaleString('de-DE', {minimumFractionDigits: 1, maximumFractionDigits: 1})} / 2,4 KG
        </b>
      </div>
      <div style={{marginTop: 6}}>
        <Charge pct={(used / 2.4) * 100} h={8} colors={['#7d8ea6', C.navy]} done={used >= 2.39} />
      </div>
    </div>
  );
};

export const ShopFloor: React.FC = () => {
  const frame = useCurrentFrame();
  const flash = ease(frame, [SCAN, SCAN + 4, SCAN + 16], [0, 1, 0]);
  const scanY = (Math.sin(frame / 7) * 0.5 + 0.5) * 300;
  const heroBack = OPS_AT - 16;
  const pose = usePose([
    {f: 0, y: PH_CENTER, s: 1},
    {f: 30, y: PH_CENTER - 60, s: 1.1},
    {f: WO, y: PH_CENTER - 60, s: 1.1},
    {f: LIFT - 6, y: PH_CENTER, s: 1},
    {f: LIFT + 12, y: PH_UP, s: 0.8},
    {f: heroBack, y: PH_UP, s: 0.8},
    {f: heroBack + 18, y: PH_CENTER - 40, s: 1.04},
  ]);
  const out = useOut(LIFT, heroBack);
  const home = useHome(LIFT, heroBack);
  const done = (i: number) => OPS[i][3] || (i > 0 && frame >= TAPS[i - 1]);

  return (
    <AbsoluteFill style={{fontFamily: FONT}}>
      <Ambient kind="teren" />
      <Title kicker="03 · Proizvodnja" title="Teren bez papira" sub="Skeniraj nalog. Evidentiraj operaciju." />
      <Phone pose={pose} dark={frame < WO} dim={ease(frame, [LIFT, LIFT + 12], [0, 0.8]) - ease(frame, [heroBack, heroBack + 12], [0, 0.8])}>
        {frame < WO ? (
          /* nalog-scan: QR scanner */
          <div style={{position: 'absolute', inset: 0, color: '#fff', textAlign: 'center'}}>
            <div style={{position: 'absolute', top: 140, left: 0, right: 0, fontSize: 21, fontWeight: 600}}>Skeniraj QR code radnog naloga</div>
            <div style={{position: 'absolute', left: 57, top: 200, width: 360, height: 360, borderRadius: 12, background: 'rgba(255,255,255,.06)', border: '2px solid #28c76f', overflow: 'hidden'}}>
              <Img src={staticFile('app/images/qr-label.svg')} style={{position: 'absolute', left: 50, top: 50, width: 260, transform: `rotate(-4deg) scale(${1 + Math.sin(frame / 20) * 0.02})`, borderRadius: 6}} />
              {frame < SCAN && <div style={{position: 'absolute', left: 0, right: 0, top: 30 + scanY, height: 3, background: 'linear-gradient(90deg, transparent, #28c76f, transparent)', boxShadow: '0 0 16px 4px rgba(40,199,111,.6)'}} />}
              <div style={{position: 'absolute', inset: 0, background: C.green, opacity: flash * 0.5}} />
            </div>
            <div style={{position: 'absolute', top: 600, left: 30, right: 30, fontSize: 16, color: frame >= SCAN ? '#7ee2a8' : '#aab4c8'}}>
              {frame >= SCAN ? <Type text="QR prepoznat. Otvaram radni nalog…" at={SCAN} cps={45} /> : <Type text="Usmjeri kameru prema QR kodu radnog naloga." at={6} cps={40} />}
            </div>
          </div>
        ) : frame < OPS_AT ? (
          /* app-invoice-preview: work order */
          <div style={{position: 'absolute', inset: 0}}>
            <MobileBar />
            <In at={WO} style={{position: 'absolute', left: 16, right: 16, top: 84, background: '#fff', borderRadius: 12, padding: 18, boxShadow: '0 4px 24px rgba(34,41,47,.08)'}}>
              <div style={{display: 'flex', alignItems: 'center', gap: 12}}>
                <LogoDot s={48} />
                <span style={{fontSize: 24, color: C.navy}}>eNalog.app</span>
              </div>
              <div style={{marginTop: 16, fontSize: 22, color: T.head}}>
                <b>RN </b>
                <Type text="26-6000-0001687" at={WO + 4} cps={40} />
              </div>
              <div style={{fontSize: 14, color: T.text, marginTop: 4}}>Narudžba: 25-0110-0003084;1</div>
            </In>
            {home && frame >= WO + 10 && (
              <In at={WO + 10} style={{position: 'absolute', left: 16, top: 260}}>
                <Hero />
              </In>
            )}
            {/* chips + tabs slide up into the space the lifted hero leaves */}
            <In at={WO + 20} style={{position: 'absolute', left: 16, right: 16, top: 270 + 190 * (1 - out)}}>
              <div style={{display: 'flex', flexWrap: 'wrap', gap: 8}}>
                {[['Tip dokumenta', '6000'], ['Varijanta', '1'], ['Lokacija', 'CNC']].map(([l, v]) => (
                  <span key={l} style={{padding: '6px 12px', borderRadius: 99, border: '1px solid #d8d6de', background: '#fff', fontSize: 13, color: T.text}}>
                    {l} <b style={{color: T.head}}>{v}</b>
                  </span>
                ))}
              </div>
              <div style={{display: 'flex', gap: 18, marginTop: 16, fontSize: 14, fontWeight: 600, borderBottom: '1px solid #ebe9f1'}}>
                {['Sastavnica', 'Zaštita', 'Materijali', 'Operacija'].map((t, i) => (
                  <span key={t} style={{paddingBottom: 8, color: i === 0 ? C.navy : T.text, borderBottom: i === 0 ? `2px solid ${C.navy}` : 'none'}}>{t}</span>
                ))}
              </div>
              {[['10', 'LIM-S235-5', 'Lim S235 5mm', '2,4 KG'], ['20', 'OP10', 'Lasersko rezanje', '4 MIN'], ['30', 'OP20', 'CNC savijanje', '6 MIN']].map((r, i) => (
                <In key={r[0]} at={WO + 26 + i * 5} style={{display: 'grid', gridTemplateColumns: '34px 110px 1fr 70px', padding: '11px 4px', borderBottom: '1px solid #ebe9f1', fontSize: 13, color: T.text, background: '#fff'}}>
                  <span>{r[0]}</span>
                  <b style={{color: T.head}}>{r[1]}</b>
                  <span>{r[2]}</span>
                  <span style={{textAlign: 'right'}}>{r[3]}</span>
                </In>
              ))}
            </In>
            <div style={{position: 'absolute', left: 16, right: 16, bottom: 18, display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10, padding: 10, background: 'rgba(255,255,255,.95)', borderRadius: 12, boxShadow: '0 12px 28px rgba(34,41,47,.16)'}}>
              <span style={{height: 50, borderRadius: 8, background: C.green, color: '#fff', fontSize: 15, fontWeight: 600, display: 'grid', placeItems: 'center'}}>Skeniraj radni nalog</span>
              <span style={{height: 50, borderRadius: 8, background: C.navy, color: '#fff', fontSize: 15, fontWeight: 600, display: 'grid', placeItems: 'center'}}>Pripremi materijal</span>
            </div>
          </div>
        ) : (
          /* app-invoice-scan-operations */
          <div style={{position: 'absolute', inset: 0}}>
            <MobileBar title="Operacije radnog naloga" />
            <In at={OPS_AT} style={{position: 'absolute', left: 16, right: 16, top: 140, background: '#fff', borderRadius: 12, boxShadow: '0 4px 24px rgba(34,41,47,.08)', overflow: 'hidden'}}>
              <div style={{textAlign: 'center', padding: 16, fontSize: 17, fontWeight: 700, color: T.head, borderBottom: `1px solid ${T.border}`}}>26-6000-0001687</div>
              <div style={{padding: 12, display: 'flex', flexDirection: 'column', gap: 10}}>
                {OPS.map(([pos, code, name], i) => {
                  const ok = done(i);
                  const next = !ok && (i === 0 || done(i - 1));
                  return (
                    <div key={code} style={{display: 'flex', alignItems: 'center', gap: 14, padding: '14px 16px', borderRadius: 10, border: `1px solid ${ok ? 'rgba(40,199,111,.42)' : next ? '#65dba0' : T.border}`, background: ok ? 'rgba(40,199,111,.1)' : next ? 'rgba(40,199,111,.05)' : '#f8f8f8'}}>
                      <span style={{width: 42, height: 42, borderRadius: 9, display: 'grid', placeItems: 'center', fontWeight: 800, fontSize: 16, background: ok || next ? '#65dba0' : '#ebe9f1', color: ok || next ? '#111c17' : '#9a97a5'}}>{pos}</span>
                      <span style={{flex: 1}}>
                        <span style={{display: 'block', fontSize: 11, fontWeight: 800, letterSpacing: 1, color: ok || next ? '#1f9d57' : '#9a97a5'}}>{code}</span>
                        <span style={{display: 'block', fontSize: 17, fontWeight: 700, color: ok || next ? '#3b4253' : '#9a97a5'}}>{name}</span>
                      </span>
                      <Tick s={24} on={ok} at={i > 0 ? TAPS[i - 1] : 0} />
                    </div>
                  );
                })}
              </div>
            </In>
            {/* material consumption: stock weight drops with every recorded operation */}
            <In at={OPS_AT + 10} style={{position: 'absolute', left: 16, right: 16, top: 560, background: '#fff', borderRadius: 12, padding: '16px 18px', boxShadow: '0 4px 24px rgba(34,41,47,.08)'}}>
              <Consumption />
            </In>
            {frame >= SAVED && (
              <In at={SAVED} style={{position: 'absolute', left: 50, right: 50, top: 760, background: '#fff', borderRadius: 14, padding: '24px 20px', textAlign: 'center', boxShadow: '0 20px 50px rgba(34,41,47,.3)'}} dy={30}>
                <span style={{width: 64, height: 64, borderRadius: 32, border: `4px solid ${C.green}`, display: 'inline-grid', placeItems: 'center'}}>
                  <Ico n="check" s={34} c={C.green} w={3} />
                </span>
                <div style={{fontSize: 22, fontWeight: 700, color: T.head, marginTop: 10}}>Evidentirano</div>
                <div style={{fontSize: 14, color: T.text, marginTop: 4}}>Kontrolna tačka je sačuvana.</div>
              </In>
            )}
            {TAPS.map((t, i) => (
              <Finger key={t} at={t} x={418} y={240 + (i + 1) * 82} />
            ))}
          </div>
        )}
      </Phone>

      {frame >= WO + 10 && frame < OPS_AT && (
        <Lift at={LIFT} back={heroBack} w={442} s0={pose.s} src={phoneToVideo(16, 260, pose)} dst={{x: 70, y: 1270, w: 940}}>
          <Hero />
        </Lift>
      )}

      <Float at={OPS_AT + 10} x={24} y={720} icon="user" label="Radnik" value="Amir H." color="#00a8c2" />
      <Float at={OPS_AT + 18} x={826} y={900} icon="cpu" label="Mašina" value="CNC 1" color="#7367f0" />
      <Float at={OPS_AT + 26} x={24} y={1180} icon="clock" label="Trajanje" value="12 min" color={C.navy} />
      <Float at={TAPS[1] + 4} x={826} y={1380} icon="check" label="OP50" value="08:14" color={C.green} />
      {TAPS.map((t, i) => (
        <Float key={`w${t}`} at={t + 2} until={t + 15} x={826} y={1180} icon="box" label="Lim S235" value="−0,8 KG" color="#ea5455" />
      ))}
      <Snd at={OPS_AT + 10} s="pop" v={0.35} />
      <Snd at={OPS_AT + 18} s="pop" v={0.35} />
      <Snd at={OPS_AT + 26} s="pop" v={0.35} />
      <Snd at={4} s="whoosh" v={0.4} />
      <TypeSnd text="Usmjeri kameru" at={6} cps={40} />
      <Snd at={SCAN} s="beep" v={0.55} />
      <TypeSnd text="26-6000-0001687" at={WO + 4} cps={40} />
      <Snd at={LIFT} s="whoosh" v={0.45} />
      <TypeSnd text="NOSAČ MOTORA 200x120" at={WO + 14} cps={40} />
      <Snd at={heroBack} s="whoosh" v={0.35} />
      {TAPS.map((t) => (
        <Snd key={t} at={t} s="click" v={0.5} />
      ))}
      <Snd at={SAVED} s="success" v={0.6} />
    </AbsoluteFill>
  );
};
