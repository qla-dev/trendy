import React from 'react';
import {Ambient} from '../components/Kit';
import {AbsoluteFill, interpolate, useCurrentFrame} from 'remotion';

import {Title} from '../components/Stage';
import {Badge, Card, Charge, Cursor, DeskShell, Ico, In, LAP_CENTER, LAP_UP, Laptop, Lift, Snd, Spin, T, Tick, Type, TypeSnd, lapToVideo, useHome, useOut, usePose} from '../components/UI';
import {C, FONT, clamp, ease} from '../theme';

const PHASES = ['Priprema dokumenta', 'Prepoznavanje sadržaja', 'Klasifikacija stavki', 'Ekstrakcija podataka', 'Provjera rezultata'];
const INK = '#16344d';
const TEAL = '#0e7a6b';
const DROP = 42;
const START = 54;
const STEP = 28;
const DONE = START + PHASES.length * STEP; // 194
const LIFT = 72;
const BACK = DONE + 14;
const FILE = 'Bestellung_4500123456.pdf';
const ROWS = [
  ['10', '0001234567', 'Flansch DN80 PN16', '120'],
  ['20', '0001234590', 'Welle Ø40 x 220', '48'],
  ['30', '0001239011', 'Distanzhülse 12x30', '400'],
];
const ROW0 = BACK + 22;

const pctAt = (f: number) => interpolate(f, [START, START + 24, START + 56, START + 84, START + 112, DONE], [0, 19, 41, 63, 84, 100], clamp);

// Status card exactly as drawn inside the screen (design size 360 x 250)
const StatusCard: React.FC<{h?: number}> = ({h = 250}) => {
  const frame = useCurrentFrame();
  const pct = pctAt(frame);
  const done = frame >= DONE;
  const active = Math.floor((frame - START) / STEP);
  return (
    <Card style={{width: 360, height: h, padding: 16, boxSizing: 'border-box', fontFamily: FONT, display: 'flex', flexDirection: 'column'}}>
      <div style={{display: 'flex', justifyContent: 'space-between', alignItems: 'center'}}>
        <div>
          <div style={{fontSize: 14, fontWeight: 600, color: INK}}>Status obrade</div>
          <div style={{fontSize: 10, color: '#607385', marginTop: 2}}>{frame < START ? 'Čekam upload…' : done ? 'Spremno za transfer' : PHASES[active]}</div>
        </div>
        <div style={{display: 'flex', alignItems: 'center', gap: 8}}>
          {frame >= START && !done && <Spin s={13} c={TEAL} />}
          <span style={{fontSize: 22, fontWeight: 800, color: INK, fontVariantNumeric: 'tabular-nums'}}>{Math.round(pct)}%</span>
        </div>
      </div>
      <div style={{marginTop: 10}}>
        <Charge pct={pct} h={9} done={done} />
      </div>
      <div style={{marginTop: 10, flex: 1, display: 'flex', flexDirection: 'column', justifyContent: 'space-between', gap: 5}}>
        {PHASES.map((p, i) => {
          const s = START + i * STEP;
          const ok = frame >= s + STEP;
          const on = frame >= s && !ok;
          return (
            <div key={p} style={{flex: 1, display: 'flex', alignItems: 'center', gap: 8, padding: '0 8px', borderRadius: 6, fontSize: 10.5, background: ok ? 'rgba(14,122,107,.08)' : on ? 'rgba(52,124,247,.08)' : '#f8fafc', color: frame >= s ? INK : '#9aa6b2'}}>
              {ok ? <Tick s={13} on at={s + STEP} c={TEAL} /> : on ? <Spin s={13} c="#347cf7" /> : <Tick s={13} on={false} />}
              <span style={{flex: 1, fontWeight: 600}}>{p}</span>
              <span style={{fontSize: 9, fontWeight: 700, color: ok ? TEAL : on ? '#347cf7' : '#9aa6b2'}}>{ok ? '✓' : on ? 'U toku' : 'Čeka'}</span>
            </div>
          );
        })}
      </div>
    </Card>
  );
};

const ResultCard: React.FC = () => {
  const frame = useCurrentFrame();
  return (
    <Card style={{width: 736, height: 182, padding: '14px 16px', boxSizing: 'border-box', fontFamily: FONT}}>
      <div style={{display: 'flex', justifyContent: 'space-between', alignItems: 'center'}}>
        <span style={{fontSize: 14, fontWeight: 600, color: INK}}>Rezultat AI skena</span>
        {frame >= ROW0 && <Badge c="#7367f0" bg="rgba(115,103,240,.12)">Spremno za transfer</Badge>}
      </div>
      <div style={{display: 'grid', gridTemplateColumns: '40px 120px 1fr 70px', fontSize: 9.5, fontWeight: 700, color: '#607385', background: 'rgba(238,243,247,.8)', padding: '6px 8px', borderRadius: 5, marginTop: 8, textTransform: 'uppercase'}}>
        <span>#</span>
        <span>Šifra</span>
        <span>Naziv</span>
        <span style={{textAlign: 'right'}}>Količina</span>
      </div>
      {ROWS.map((r, i) => {
        const at = ROW0 + i * 12;
        return (
          <In key={r[0]} at={at} style={{display: 'grid', gridTemplateColumns: '40px 120px 1fr 70px', fontSize: 11.5, padding: '7px 8px', borderBottom: `1px solid ${T.border}`, color: INK}}>
            <span>{r[0]}</span>
            <span style={{fontWeight: 600}}>
              <Type text={r[1]} at={at} cps={45} caret={false} />
            </span>
            <span style={{fontWeight: 600}}>
              <Type text={r[2]} at={at + 3} cps={45} />
            </span>
            <span style={{textAlign: 'right'}}>{frame >= at + 8 ? r[3] : ''}</span>
          </In>
        );
      })}
    </Card>
  );
};

export const AiOrders: React.FC = () => {
  const frame = useCurrentFrame();
  const pose = usePose([
    {f: 0, y: LAP_CENTER - 40, s: 1},
    {f: 18, y: LAP_CENTER - 70, s: 1.06},
    {f: LIFT - 8, y: LAP_CENTER - 70, s: 1.06},
    {f: LIFT + 12, y: LAP_UP, s: 1},
  ]);
  const dim = ease(frame, [LIFT, LIFT + 14], [0, 0.8]);
  const dropped = frame >= DROP;
  const fx = ease(frame, [10, DROP], [760, 160]);
  const fy = ease(frame, [10, DROP], [470, 150]);
  const statusOut = useOut(LIFT, BACK);
  const statusHome = useHome(LIFT, BACK);
  const resOut = useOut(ROW0 - 12);
  const resHome = useHome(ROW0 - 12);
  const topH = 250 + 198 * resOut;

  return (
    <AbsoluteFill style={{fontFamily: FONT}}>
      <Ambient kind="ai" />
      <Title kicker="01 · Narudžbe" title="AI čita narudžbe" sub="PDF ili e-mail, direktno u Pantheon." />
      <Laptop pose={pose} dim={dim}>
        <DeskShell active={2}>
          <div style={{fontSize: 19, fontWeight: 500, color: T.head}}>Skeniraj narudžbu sa AI</div>
          {/* dropzone: stretches into the space the lifted cards leave */}
          <Card style={{position: 'absolute', left: 0, top: 44, width: 360 + 376 * statusOut, height: topH, padding: 14, boxSizing: 'border-box'}}>
            <div style={{height: '100%', borderRadius: 10, border: `2px dashed ${dropped ? TEAL : '#a7c9c3'}`, background: dropped ? 'rgba(14,122,107,.06)' : 'rgba(14,122,107,.02)', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', gap: 8}}>
              <span style={{width: 44, height: 44, borderRadius: 12, display: 'grid', placeItems: 'center', background: 'rgba(14,122,107,.1)'}}>
                <Ico n={dropped ? 'file' : 'upload'} s={22} c={TEAL} />
              </span>
              <div style={{fontSize: 14, fontWeight: 600, color: INK, minHeight: 18}}>{dropped ? <Type text={FILE} at={DROP + 2} cps={40} /> : 'Prevuci dokument ovdje'}</div>
              <div style={{fontSize: 9.5, color: '#607385'}}>PDF i Word dokumenti do 50 MB</div>
            </div>
          </Card>
          {statusHome && (
            <div style={{position: 'absolute', left: 376, top: 44}}>
              <StatusCard h={topH} />
            </div>
          )}
          {resHome && (
            <div style={{position: 'absolute', left: 0, top: 310}}>
              <ResultCard />
            </div>
          )}
          {frame < DROP + 2 && (
            <div style={{position: 'absolute', left: fx, top: fy, display: 'flex', alignItems: 'center', gap: 8, padding: '8px 12px', borderRadius: 8, background: '#fff', boxShadow: '0 10px 24px rgba(34,41,47,.25)', fontSize: 11, fontWeight: 600, color: INK, transform: `rotate(${(1 - ease(frame, [10, DROP], [0, 1])) * -6}deg)`}}>
              <span style={{width: 24, height: 30, borderRadius: 4, background: C.navy, color: '#fff', fontSize: 8, fontWeight: 800, display: 'grid', placeItems: 'end center', paddingBottom: 3}}>PDF</span>
              {FILE}
            </div>
          )}
          <Cursor path={[{f: 10, x: 900, y: 500}, {f: DROP, x: 300, y: 170}, {f: DROP + 20, x: 320, y: 200}]} clicks={[DROP]} from={6} to={DROP + 24} />
        </DeskShell>
      </Laptop>

      <Lift at={LIFT} back={BACK} w={360} s0={pose.s} src={lapToVideo(176 + 376, 74 + 44, pose)} dst={{x: 90, y: 1110, w: 900}}>
        <StatusCard />
      </Lift>

      <Lift at={ROW0 - 12} w={736} s0={pose.s} src={lapToVideo(176, 74 + 310, pose)} dst={{x: 40, y: 1110, w: 1000}}>
        <ResultCard />
      </Lift>

      {frame >= ROW0 + 30 && (
        <In at={ROW0 + 30} style={{position: 'absolute', left: 0, right: 0, top: 1420, display: 'flex', justifyContent: 'center'}} dy={40}>
          <div style={{display: 'inline-flex', alignItems: 'center', gap: 16, padding: '22px 34px', borderRadius: 20, background: C.green, color: '#fff', fontSize: 34, fontWeight: 700, boxShadow: '0 30px 70px rgba(10,16,24,.4)'}}>
            <Ico n="check" s={38} c="#fff" w={3.5} /> Sačuvano u Pantheon bazi
          </div>
        </In>
      )}

      <Snd at={4} s="whoosh" v={0.4} />
      <Snd at={DROP} s="pop" v={0.55} />
      <TypeSnd text={FILE} at={DROP + 2} cps={40} />
      <Snd at={LIFT} s="whoosh" v={0.45} />
      {PHASES.map((_, i) => (
        <Snd key={i} at={START + (i + 1) * STEP} s="click" v={0.4} />
      ))}
      <Snd at={BACK} s="whoosh" v={0.35} />
      <Snd at={ROW0 - 12} s="whoosh" v={0.4} />
      {ROWS.map((r, i) => (
        <TypeSnd key={i} text={r[2]} at={ROW0 + i * 12 + 3} cps={45} />
      ))}
      <Snd at={ROW0 + 30} s="success" v={0.6} />
    </AbsoluteFill>
  );
};
