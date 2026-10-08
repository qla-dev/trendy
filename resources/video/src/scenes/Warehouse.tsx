import React from 'react';
import {Ambient} from '../components/Kit';
import {AbsoluteFill, useCurrentFrame} from 'remotion';

import {Title} from '../components/Stage';
import {Card, DeskShell, Ico, In, LAP_CENTER, LAP_DOWN, Laptop, Lift, Num, PH_CENTER, Phone, Snd, T, Type, TypeSnd, lapToVideo, useHome, useOut, usePose} from '../components/UI';
import {C, FONT, ease} from '../theme';

const SCAN = 52;
const SWAP = 96;
const TYPE_AT = SWAP + 18;
const LIFT = SWAP + 56;
const BOOK = LIFT + 34;
const ROWS: [string, string, number, string][] = [
  ['LIM-S235-5', 'Lim S235 5mm', 1252.9, 'KG'],
  ['SIP-S355-40', 'Šipka S355 Ø40', 386.2, 'M'],
  ['AL-6082-20', 'Aluminij 6082', 742, 'KG'],
  ['INOX-304-2', 'Inox lim 2mm', 518.75, 'KG'],
  ['VIJ-M10-35', 'Vijak M10x35', 6400, 'KOM'],
  ['PRA-RAL7016', 'Prah RAL 7016', 96.5, 'KG'],
];

// Material card in the right column (design 300 x 330)
const MaterialCard: React.FC = () => {
  const frame = useCurrentFrame();
  const booked = frame >= BOOK;
  const qty = booked ? ease(frame, [BOOK, BOOK + 24], [1252.9, 1250.5]) : 1252.9;
  const moves = [
    {at: BOOK, doc: '26-6400-0000412', what: 'Razduženo na RN 26-6000-0001687', q: '−2,4 KG', c: C.navy},
    {at: LIFT + 8, doc: '26-6100-0000188', what: 'Prijem na skladište sirovina', q: '+500 KG', c: '#1f9d57'},
    {at: 0, doc: '26-6400-0000398', what: 'Razduženo na RN 26-6000-0001676', q: '−4,5 KG', c: C.navy},
    {at: 0, doc: '26-2005-0000071', what: 'Razduživanje WIP', q: '−12 KG', c: '#7d8ea6'},
    {at: 0, doc: '26-7100-0000019', what: 'Prijem škarta', q: '+0,6 KG', c: '#ea5455'},
  ];
  return (
    <Card style={{width: 300, height: 406, padding: 16, boxSizing: 'border-box', fontFamily: FONT, display: 'flex', flexDirection: 'column'}}>
      <div style={{fontSize: 10, fontWeight: 700, letterSpacing: 1, color: '#8a8798'}}>KARTICA MATERIJALA</div>
      <div style={{fontSize: 17, fontWeight: 700, color: C.navy, marginTop: 5}}>LIM-S235-5</div>
      <div style={{fontSize: 12, color: T.head}}>Lim S235 5mm 1500x3000</div>
      <div style={{marginTop: 12, padding: '10px 12px', borderRadius: 8, background: T.soft, display: 'flex', justifyContent: 'space-between', alignItems: 'baseline'}}>
        <span style={{fontSize: 11, fontWeight: 600}}>Zaliha</span>
        <span style={{fontSize: 22, fontWeight: 800, color: T.head, fontVariantNumeric: 'tabular-nums'}}>
          {qty.toLocaleString('de-DE', {minimumFractionDigits: 1, maximumFractionDigits: 1})} <span style={{fontSize: 11}}>KG</span>
        </span>
      </div>
      <div style={{fontSize: 10, fontWeight: 700, letterSpacing: 1, color: '#8a8798', marginTop: 14}}>KRETANJA</div>
      <div style={{flex: 1, display: 'flex', flexDirection: 'column', gap: 7, marginTop: 7}}>
        {moves.map((m) => (
          <In key={m.doc} at={m.at} style={{display: 'flex', alignItems: 'center', gap: 8, padding: '7px 9px', borderRadius: 7, border: `1px solid ${T.border}`, background: '#fff'}}>
            <span style={{width: 24, height: 24, borderRadius: 6, display: 'grid', placeItems: 'center', background: `${m.c}18`}}>
              <Ico n="file" s={13} c={m.c} />
            </span>
            <span style={{flex: 1, minWidth: 0}}>
              <span style={{display: 'block', fontSize: 10.5, fontWeight: 700, color: T.head}}>
                <Type text={m.doc} at={m.at} cps={45} caret={false} />
              </span>
              <span style={{display: 'block', fontSize: 9, color: '#8a8798', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis'}}>{m.what}</span>
            </span>
            <span style={{fontSize: 11, fontWeight: 800, color: m.c}}>{m.q}</span>
          </In>
        ))}
      </div>
    </Card>
  );
};

export const Warehouse: React.FC = () => {
  const frame = useCurrentFrame();
  const flash = ease(frame, [SCAN, SCAN + 4, SCAN + 16], [0, 1, 0]);
  const phoneOut = ease(frame, [SWAP - 10, SWAP + 10], [0, 1]);
  const dim = ease(frame, [LIFT, LIFT + 14], [0, 0.8]);
  const phPose0 = usePose([
    {f: 0, y: PH_CENTER, s: 1},
    {f: SCAN - 10, y: PH_CENTER - 40, s: 1.08},
  ]);
  const phPose = {...phPose0, y: phPose0.y - phoneOut * 1800};
  const pose = usePose(
    [
      {f: SWAP, y: LAP_CENTER - 70, s: 1.06},
      {f: LIFT - 8, y: LAP_CENTER - 70, s: 1.06},
      {f: LIFT + 12, y: LAP_DOWN, s: 0.95},
    ],
    SWAP - 4,
  );
  const out = useOut(LIFT);
  const home = useHome(LIFT);
  const scanY = (Math.sin(frame / 7) * 0.5 + 0.5) * 260;

  return (
    <AbsoluteFill style={{fontFamily: FONT}}>
      <Ambient kind="skl" />
      <Title kicker="04 · Skladište" title="Puna kontrola nad skladištem" />

      {phoneOut < 1 && (
        <div style={{position: 'absolute', inset: 0}}>
          <Phone dark pose={phPose}>
            {/* sirovina-scan: barcode / QR window */}
            <div style={{position: 'absolute', inset: 0, color: '#fff', textAlign: 'center'}}>
              <div style={{position: 'absolute', top: 120, left: 20, right: 20, fontSize: 20, fontWeight: 600}}>Skenirajte BARCODE ili QR kod artikla</div>
              <div style={{position: 'absolute', left: 47, top: 180, width: 380, height: 320, borderRadius: 18, border: '3px solid rgba(92,225,194,.95)', background: 'rgba(255,255,255,.04)', overflow: 'hidden'}}>
                <div style={{position: 'absolute', left: 60, right: 60, top: 100, height: 110, background: '#fff', borderRadius: 6, display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 3, padding: '0 14px', transform: 'rotate(-3deg)'}}>
                  {Array.from({length: 38}).map((_, i) => (
                    <span key={i} style={{width: [2, 4, 1, 3, 2, 5][i % 6], height: 78, background: '#111'}} />
                  ))}
                </div>
                {frame < SCAN && <div style={{position: 'absolute', left: 0, right: 0, top: 30 + scanY, height: 3, background: 'linear-gradient(90deg, transparent, #5ce1c2, transparent)', boxShadow: '0 0 16px 4px rgba(92,225,194,.6)'}} />}
                <div style={{position: 'absolute', inset: 0, background: C.green, opacity: flash * 0.5}} />
              </div>
              {frame >= SCAN && (
                <In at={SCAN} style={{position: 'absolute', left: 30, right: 30, top: 540, background: 'rgba(23,30,48,.96)', border: '1px solid rgba(92,225,194,.4)', borderRadius: 14, padding: 18, textAlign: 'left'}}>
                  <div style={{fontSize: 12, fontWeight: 700, letterSpacing: 1, color: '#bad0ff'}}>PRIVREMENA SASTAVNICA</div>
                  <div style={{fontSize: 20, fontWeight: 700, marginTop: 8}}>
                    <Type text="LIM-S235-5" at={SCAN + 2} cps={35} />
                  </div>
                  <div style={{fontSize: 15, color: '#aab4c8', marginTop: 2}}>Lim S235 5mm · Planirano 2,4 KG</div>
                </In>
              )}
            </div>
          </Phone>
        </div>
      )}

      {frame >= SWAP && (
        <div style={{position: 'absolute', inset: 0}}>
          <Laptop pose={pose} dim={dim}>
            <DeskShell active={4} search={frame >= TYPE_AT ? <Type text="LIM-S235" at={TYPE_AT} cps={16} /> : undefined}>
              <div style={{fontSize: 19, fontWeight: 500, color: T.head}}>Pregled zaliha</div>
              <Card style={{position: 'absolute', left: 0, top: 40, width: 424 + 312 * out, height: 406, overflow: 'hidden'}}>
                <div style={{display: 'grid', gridTemplateColumns: '120px 1fr 110px', fontSize: 9, fontWeight: 700, background: '#f3f2f7', padding: '8px 12px', textTransform: 'uppercase'}}>
                  <span>Šifra</span>
                  <span>Naziv</span>
                  <span style={{textAlign: 'right'}}>Zaliha</span>
                </div>
                {ROWS.map(([code, name, q, u], i) => {
                  const hit = i === 0 && frame >= TYPE_AT + 14;
                  return (
                    <In key={code} at={SWAP + 4 + i * 4} style={{display: 'grid', gridTemplateColumns: '120px 1fr 110px', alignItems: 'center', height: 52, padding: '0 12px', borderTop: `1px solid ${T.border}`, fontSize: 10.5, background: hit ? 'rgba(73,91,115,.08)' : '#fff', boxShadow: hit ? `inset 3px 0 0 ${C.navy}` : 'none', opacity: frame >= TYPE_AT + 14 && i > 0 ? 0.35 : 1}}>
                      <span style={{fontWeight: 700, color: T.head}}>{code}</span>
                      <span>{name}</span>
                      <span style={{textAlign: 'right', fontWeight: 700, color: T.head, fontVariantNumeric: 'tabular-nums'}}>
                        <Num to={q} at={SWAP + 6 + i * 4} dur={30} dec={q % 1 ? 1 : 0} /> <span style={{fontSize: 8.5, color: '#8a8798'}}>{u}</span>
                      </span>
                    </In>
                  );
                })}
              </Card>
              {home && (
                <div style={{position: 'absolute', left: 436, top: 40}}>
                  <MaterialCard />
                </div>
              )}
            </DeskShell>
          </Laptop>
          <Lift at={LIFT} w={300} s0={pose.s} src={lapToVideo(176 + 436, 74 + 40, pose)} dst={{x: 270, y: 430, w: 540}}>
            <MaterialCard />
          </Lift>
        </div>
      )}

      <Snd at={4} s="whoosh" v={0.4} />
      <Snd at={SCAN} s="beep" v={0.55} />
      <TypeSnd text="LIM-S235-5" at={SCAN + 2} cps={35} />
      <Snd at={SWAP} s="whoosh" v={0.4} />
      <TypeSnd text="LIM-S235" at={TYPE_AT} cps={16} />
      <Snd at={LIFT} s="whoosh" v={0.45} />
      <Snd at={LIFT + 8} s="pop" v={0.4} />
      <Snd at={BOOK} s="success" v={0.5} />
    </AbsoluteFill>
  );
};
