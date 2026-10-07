import React from 'react';
import {Ambient} from '../components/Kit';
import {AbsoluteFill, useCurrentFrame} from 'remotion';
import {Title} from '../components/Stage';
import {Badge, Card, Charge, Cursor, DeskShell, In, LAP_CENTER, LAP_DOWN, Laptop, Lift, LogoDot, Num, Snd, T, Tick, Type, TypeSnd, lapToVideo, useHome, useOut, usePose} from '../components/UI';
import {C, FONT, ease} from '../theme';

const STATUS = [
  {l: 'Svi', n: 1248, c: '#6e6b7b'},
  {l: 'Planiran', n: 86, c: '#00cfe8'},
  {l: 'Otvoren', n: 142, c: '#28c76f'},
  {l: 'U radu', n: 64, c: '#b38600'},
  {l: 'Zaključen', n: 898, c: '#ea5455'},
];
const ROWS = [
  ['26-6000-0001284', 'Prirubnica DN80', 'GW', 'GROB-WERKE', '120 kom', 'Otvoren'],
  ['26-6000-0001283', 'Osovina vratila', 'TG', 'TRENDY GERMANY', '48 kom', 'U radu'],
  ['26-6000-0001282', 'Nosač motora', 'KH', 'KOVIS Hidraulika', '25 kom', 'Planiran'],
  ['26-6000-0001281', 'Kućište ležaja', 'GW', 'GROB-WERKE', '60 kom', 'U radu'],
  ['26-6000-0001279', 'Distantna čahura', 'TG', 'TRENDY GERMANY', '400 kom', 'Zaključen'],
  ['26-6000-0001275', 'Ploča adaptera', 'MS', 'Metalac Sarajevo', '10 kom', 'Otvoren'],
];
const BADGE: Record<string, [string, string]> = {
  Otvoren: ['#28c76f', 'rgba(40,199,111,.12)'],
  'U radu': ['#b38600', 'rgba(255,193,7,.16)'],
  Planiran: ['#00cfe8', 'rgba(0,207,232,.12)'],
  Zaključen: ['#ea5455', 'rgba(234,84,85,.12)'],
};
const SEARCH = 'Prirubnica';
const TYPE_AT = 50;
const FILTER = 84;
const CLICK = 100;
const PAGE = CLICK + 6; // list -> work order page
const LIFT = 150;
const STATUS_AT = 186;
const OPS = ['Rezanje', 'CNC glodanje', 'Bravarija', 'Kontrola'];
const COL_H = 440;

// Right column of the work order page: progress + operations (design 300 x 440)
const OpsCard: React.FC = () => {
  const frame = useCurrentFrame();
  const pct = ease(frame, [STATUS_AT, STATUS_AT + 50], [0, 45]);
  return (
    <Card style={{width: 300, height: COL_H, padding: 16, boxSizing: 'border-box', fontFamily: FONT, display: 'flex', flexDirection: 'column'}}>
      <div style={{fontSize: 14, fontWeight: 600, color: T.head}}>Realizacija</div>
      <div style={{display: 'flex', alignItems: 'baseline', justifyContent: 'space-between', marginTop: 8}}>
        <span style={{fontSize: 30, fontWeight: 800, color: T.head, fontVariantNumeric: 'tabular-nums'}}>{Math.round(pct)} %</span>
        <span style={{fontSize: 11, color: '#8a8798'}}>{Math.round((pct / 100) * 120)} / 120 kom</span>
      </div>
      <div style={{marginTop: 8}}>
        <Charge pct={pct} h={9} colors={['#00cfe8', '#28c76f']} done={frame > STATUS_AT + 50} />
      </div>
      <div style={{fontSize: 14, fontWeight: 600, color: T.head, marginTop: 18}}>Operacije</div>
      <div style={{flex: 1, display: 'flex', flexDirection: 'column', justifyContent: 'space-between', gap: 8, marginTop: 10}}>
        {OPS.map((o, i) => {
          const at = STATUS_AT + 14 + i * 14;
          const done = i < 2 && frame >= at;
          const run = i === 2 && frame >= STATUS_AT + 40;
          return (
            <div key={o} style={{flex: 1, display: 'flex', alignItems: 'center', gap: 10, fontSize: 12, padding: '0 10px', borderRadius: 8, background: done ? 'rgba(40,199,111,.08)' : run ? 'rgba(255,193,7,.12)' : T.soft, color: done || run ? '#3b4253' : '#9a97a5'}}>
              <Tick s={15} on={done} at={at} />
              <span style={{flex: 1}}>
                <span style={{display: 'block', fontSize: 9.5, fontWeight: 800, letterSpacing: 1}}>OP{(i + 1) * 10}</span>
                <span style={{display: 'block', fontWeight: 600}}>{o}</span>
              </span>
              {run && (
                <Badge c="#b38600" bg="rgba(255,193,7,.16)" s={9}>
                  U radu
                </Badge>
              )}
            </div>
          );
        })}
      </div>
    </Card>
  );
};

export const Planning: React.FC = () => {
  const frame = useCurrentFrame();
  const pose = usePose([
    {f: 0, y: LAP_CENTER - 40, s: 1},
    {f: 40, y: LAP_CENTER - 70, s: 1.06},
    {f: LIFT - 8, y: LAP_CENTER - 70, s: 1.06},
    {f: LIFT + 12, y: LAP_DOWN, s: 0.95},
  ]);
  const dim = ease(frame, [LIFT, LIFT + 14], [0, 0.8]);
  const filt = ease(frame, [FILTER, FILTER + 12], [0, 1]);
  const page = ease(frame, [PAGE, PAGE + 14], [0, 1]);
  const out = useOut(LIFT);
  const home = useHome(LIFT);
  const working = frame >= STATUS_AT;

  return (
    <AbsoluteFill style={{fontFamily: FONT}}>
      <Ambient kind="plan" />
      <Title kicker="02 · Planiranje" title="Svaki nalog uživo" sub="Pronađi, otvori i prati nalog." />
      <Laptop pose={pose} dim={dim}>
        <DeskShell active={1} search={frame >= TYPE_AT && frame < PAGE ? <Type text={SEARCH} at={TYPE_AT} cps={14} /> : undefined}>
          {/* ---- list view (full width) ---- */}
          {page < 1 && (
            <div style={{position: 'absolute', inset: 0, opacity: 1 - page, transform: `translateX(${-60 * page}px)`}}>
              <div style={{fontSize: 19, fontWeight: 500, color: T.head}}>Radni nalozi</div>
              <div style={{position: 'absolute', top: 34, left: 0, right: 0, display: 'grid', gridTemplateColumns: 'repeat(5, 1fr)', gap: 10}}>
                {STATUS.map((s, i) => (
                  <In key={s.l} at={6 + i * 4}>
                    <div style={{background: '#fff', border: `1px solid ${i === 0 ? C.navy : s.c}`, borderRadius: 5, padding: '7px 0', textAlign: 'center'}}>
                      <div style={{fontSize: 10, fontWeight: 500, color: s.c}}>{s.l}</div>
                      <div style={{fontSize: 16, fontWeight: 700, color: T.head}}>
                        <Num to={s.n} at={8 + i * 4} />
                      </div>
                    </div>
                  </In>
                ))}
              </div>
              <Card style={{position: 'absolute', left: 0, right: 0, top: 104, height: 388, padding: '8px 0', overflow: 'hidden'}}>
                <div style={{display: 'grid', gridTemplateColumns: '140px 1.2fr 1fr 70px 90px', fontSize: 9, fontWeight: 700, color: T.text, background: '#f3f2f7', padding: '8px 16px', textTransform: 'uppercase'}}>
                  <span>#</span>
                  <span>Naziv</span>
                  <span>Klijent</span>
                  <span>Qty</span>
                  <span>Status</span>
                </div>
                {ROWS.map((r, i) => {
                  const match = i === 0;
                  const gone = !match ? filt : 0;
                  const sel = frame >= CLICK && match;
                  return (
                    <In key={r[0]} at={14 + i * 5} style={{height: 56 * (1 - gone), opacity: 1 - gone, overflow: 'hidden'}}>
                      <div style={{display: 'grid', gridTemplateColumns: '140px 1.2fr 1fr 70px 90px', alignItems: 'center', padding: '0 16px', height: 56, borderTop: `1px solid ${T.border}`, fontSize: 11, background: sel ? 'rgba(73,91,115,.08)' : '#fff', boxShadow: sel ? `inset 3px 0 0 ${C.navy}` : 'none'}}>
                        <span style={{fontWeight: 700, color: C.navy}}>{r[0]}</span>
                        <span style={{fontWeight: 600, color: T.head}}>{r[1]}</span>
                        <span style={{display: 'flex', alignItems: 'center', gap: 7}}>
                          <span style={{width: 22, height: 22, borderRadius: 11, display: 'grid', placeItems: 'center', fontSize: 8, fontWeight: 700, background: 'rgba(73,91,115,.12)', color: C.navy}}>{r[2]}</span>
                          {r[3]}
                        </span>
                        <span>{r[4]}</span>
                        <span>
                          <Badge c={BADGE[r[5]][0]} bg={BADGE[r[5]][1]} s={9}>
                            {r[5]}
                          </Badge>
                        </span>
                      </div>
                    </In>
                  );
                })}
              </Card>
              <Cursor path={[{f: 64, x: 620, y: 420}, {f: CLICK - 4, x: 120, y: 165}, {f: CLICK + 10, x: 130, y: 172}]} clicks={[CLICK]} from={60} to={PAGE + 4} />
            </div>
          )}

          {/* ---- work order page ---- */}
          {page > 0 && (
            <div style={{position: 'absolute', inset: 0, opacity: page, transform: `translateX(${60 * (1 - page)}px)`}}>
              <div style={{fontSize: 19, fontWeight: 500, color: T.head}}>Radni nalog</div>
              <Card style={{position: 'absolute', left: 0, top: 40, width: 424 + 312 * out, height: COL_H, padding: 20, boxSizing: 'border-box', display: 'flex', flexDirection: 'column'}}>
                <div style={{display: 'flex', alignItems: 'center', justifyContent: 'space-between'}}>
                  <span style={{display: 'flex', alignItems: 'center', gap: 10}}>
                    <LogoDot s={34} />
                    <span style={{fontSize: 18, color: C.navy}}>eNalog.app</span>
                  </span>
                  {working ? (
                    <Badge c="#b38600" bg="rgba(255,193,7,.16)" s={11}>
                      U radu
                    </Badge>
                  ) : (
                    <Badge c="#28c76f" bg="rgba(40,199,111,.12)" s={11}>
                      Otvoren
                    </Badge>
                  )}
                </div>
                <div style={{fontSize: 22, color: T.head, marginTop: 18}}>
                  <b>RN </b>
                  <Type text="26-6000-0001284" at={PAGE + 4} cps={45} />
                </div>
                <div style={{fontSize: 12, color: T.text, marginTop: 4}}>Narudžba: 26-0100-0000412;1</div>
                <div style={{marginTop: 18, padding: '14px 16px', borderRadius: 10, border: `1px solid ${T.border}`, background: 'linear-gradient(180deg, rgba(115,103,240,.07), #fff)'}}>
                  <div style={{fontSize: 9.5, fontWeight: 700, letterSpacing: 1.5, color: '#8a8798'}}>NAZIV PROIZVODA</div>
                  <div style={{display: 'flex', justifyContent: 'space-between', alignItems: 'baseline'}}>
                    <span style={{fontSize: 18, fontWeight: 800, color: T.head}}>
                      <Type text="PRIRUBNICA DN80 PN16" at={PAGE + 10} cps={45} caret={false} />
                    </span>
                    <span style={{fontSize: 20, fontWeight: 800, color: T.head}}>
                      120 <span style={{fontSize: 11}}>KOM</span>
                    </span>
                  </div>
                </div>
                <div style={{flex: 1, display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 10, marginTop: 14}}>
                  {[
                    ['Klijent', 'GROB-WERKE'],
                    ['Prioritet', '1 - Visoki'],
                    ['Rok', '24.10.2026'],
                    ['Lokacija', 'CNC'],
                    ['Zaštita', 'RAL 7016'],
                    ['Plan. start', '09.10.2026'],
                  ].map(([k, v], i) => (
                    <In key={k} at={PAGE + 12 + i * 3} style={{borderRadius: 8, background: T.soft, padding: '10px 12px', display: 'flex', flexDirection: 'column', justifyContent: 'center'}}>
                      <span style={{fontSize: 9, fontWeight: 700, textTransform: 'uppercase', color: '#8a8798'}}>{k}</span>
                      <span style={{fontSize: 13, fontWeight: 700, color: k === 'Prioritet' ? '#ea5455' : T.head, marginTop: 2}}>{v}</span>
                    </In>
                  ))}
                </div>
              </Card>
              {home && (
                <In at={PAGE + 6} style={{position: 'absolute', left: 436, top: 40}}>
                  <OpsCard />
                </In>
              )}
            </div>
          )}
        </DeskShell>
      </Laptop>

      <Lift at={LIFT} w={300} s0={pose.s} src={lapToVideo(176 + 436, 74 + 40, pose)} dst={{x: 280, y: 380, w: 520}}>
        <OpsCard />
      </Lift>

      <Snd at={4} s="whoosh" v={0.4} />
      <TypeSnd text={SEARCH} at={TYPE_AT} cps={14} />
      <Snd at={FILTER} s="whoosh" v={0.3} />
      <Snd at={CLICK} s="click" v={0.5} />
      <TypeSnd text="26-6000-0001284" at={PAGE + 4} cps={45} />
      <Snd at={LIFT} s="whoosh" v={0.45} />
      <Snd at={STATUS_AT} s="pop" v={0.5} />
      <Snd at={STATUS_AT + 14} s="click" v={0.4} />
      <Snd at={STATUS_AT + 28} s="click" v={0.4} />
    </AbsoluteFill>
  );
};
