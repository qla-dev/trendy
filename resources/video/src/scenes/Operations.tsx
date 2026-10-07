import React from 'react';
import {Ambient} from '../components/Kit';
import {AbsoluteFill, interpolate, useCurrentFrame, useVideoConfig} from 'remotion';

import {Title} from '../components/Stage';
import {Card, Charge, DeskShell, LAP_CENTER, LAP_UP, Laptop, Lift, Num, Snd, T, Tick, lapToVideo, useHome, useOut, usePose} from '../components/UI';
import {C, FONT, clamp, ease, sp} from '../theme';

const BARS = [86, 94, 112, 108, 121, 117, 126, 98, 132, 140];
const MONTHS = ['J', 'F', 'M', 'A', 'M', 'J', 'J', 'A', 'S', 'O'];
const LIFT = 70;
// Operations in progress: [RN, operation, start%, end%, machine]
const RUNNING: [string, string, number, number, string][] = [
  ['26-6000-0001284', 'CNC glodanje', 35, 100, 'CNC 1'],
  ['26-6000-0001283', 'Bravarija', 52, 100, 'Bravarija'],
  ['26-6000-0001282', 'Lasersko rezanje', 10, 78, 'Laser'],
  ['26-6000-0001281', 'Kontrola', 0, 64, 'Kontrola'],
];

// "Operacije u toku" panel (design 300 x 330)
const Running: React.FC = () => {
  const frame = useCurrentFrame();
  return (
    <Card style={{width: 300, height: 406, padding: 16, boxSizing: 'border-box', fontFamily: FONT, display: 'flex', flexDirection: 'column'}}>
      <div style={{display: 'flex', justifyContent: 'space-between', alignItems: 'center'}}>
        <span style={{fontSize: 14, fontWeight: 600, color: T.head}}>Operacije u toku</span>
        <span style={{fontSize: 10, fontWeight: 700, color: '#1f9d57'}}>● uživo</span>
      </div>
      <div style={{flex: 1, display: 'flex', flexDirection: 'column', justifyContent: 'space-around', gap: 12, marginTop: 14}}>
        {RUNNING.map(([rn, op, a, b, m], i) => {
          const pct = interpolate(frame, [LIFT + 10 + i * 6, LIFT + 110 + i * 10], [a, b], clamp);
          const done = pct >= 100;
          return (
            <div key={rn}>
              <div style={{display: 'flex', alignItems: 'center', gap: 7, fontSize: 10.5}}>
                <Tick s={13} on={done} at={LIFT + 110 + i * 10} />
                <b style={{color: T.head}}>{op}</b>
                <span style={{color: '#8a8798', flex: 1, textAlign: 'right'}}>{Math.round(pct)}%</span>
              </div>
              <div style={{fontSize: 9, color: '#8a8798', margin: '2px 0 5px 20px'}}>
                {rn} · {m}
              </div>
              <div style={{marginLeft: 20}}>
                <Charge pct={pct} h={7} done={done} colors={['#7d8ea6', C.navy]} />
              </div>
            </div>
          );
        })}
      </div>
    </Card>
  );
};

export const Operations: React.FC = () => {
  const frame = useCurrentFrame();
  const {fps} = useVideoConfig();
  const dim = ease(frame, [LIFT, LIFT + 14], [0, 0.8]);
  const pose = usePose([
    {f: 0, y: LAP_CENTER - 40, s: 1},
    {f: 40, y: LAP_CENTER - 70, s: 1.06},
    {f: LIFT - 8, y: LAP_CENTER - 70, s: 1.06},
    {f: LIFT + 12, y: LAP_UP, s: 1},
  ]);
  const out = useOut(LIFT);
  const home = useHome(LIFT);

  return (
    <AbsoluteFill style={{fontFamily: FONT}}>
      <Ambient kind="oper" />
      <Title kicker="05 · Operacije" title="Puna kontrola nad operacijama" />
      <Laptop pose={pose} dim={dim}>
        <DeskShell active={0}>
          <div style={{fontSize: 19, fontWeight: 500, color: T.head}}>Kontrolna ploča</div>
          <Card style={{position: 'absolute', left: 0, top: 40, width: 424 + 312 * out, height: 84, display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', alignItems: 'center'}}>
            {[
              ['Radni nalozi', 12486],
              ['Kupci', 214],
              ['Proizvodi', 3972],
            ].map(([l, n], i) => (
              <div key={l} style={{textAlign: 'center', borderLeft: i ? `1px solid ${T.border}` : 'none'}}>
                <div style={{fontSize: 19, fontWeight: 700, color: T.head}}>
                  <Num to={n as number} at={4} dur={40} />
                </div>
                <div style={{fontSize: 10}}>{l}</div>
              </div>
            ))}
          </Card>
          <Card style={{position: 'absolute', left: 0, top: 136, width: 424 + 312 * out, height: 310, padding: 16, boxSizing: 'border-box'}}>
            <div style={{fontSize: 13, fontWeight: 600, color: T.head}}>Izvještaj o radnim nalozima</div>
            <div style={{display: 'flex', alignItems: 'flex-end', gap: 12, height: 220, marginTop: 18}}>
              {BARS.map((b, i) => {
                const p = sp(frame, fps, 10 + i * 3, 18);
                return (
                  <div key={i} style={{flex: 1, display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 5, height: '100%', justifyContent: 'flex-end'}}>
                    <div style={{width: '70%', height: `${(b / 150) * 100 * p}%`, borderRadius: 4, background: i === BARS.length - 1 ? C.navy : '#9aa8bb'}} />
                    <span style={{fontSize: 8.5, color: T.muted}}>{MONTHS[i]}</span>
                  </div>
                );
              })}
            </div>
          </Card>
          {home && (
            <div style={{position: 'absolute', left: 436, top: 40}}>
              <Running />
            </div>
          )}
        </DeskShell>
      </Laptop>

      <Lift at={LIFT} w={300} s0={pose.s} src={lapToVideo(176 + 436, 74 + 40, pose)} dst={{x: 240, y: 990, w: 600}}>
        <Running />
      </Lift>

      <Snd at={4} s="whoosh" v={0.4} />
      <Snd at={LIFT} s="whoosh" v={0.45} />
      {RUNNING.slice(0, 2).map((_, i) => (
        <Snd key={i} at={LIFT + 110 + i * 10} s="click" v={0.45} />
      ))}
    </AbsoluteFill>
  );
};
