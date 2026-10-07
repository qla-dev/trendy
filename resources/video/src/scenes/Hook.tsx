import React from 'react';
import {AbsoluteFill, random, useCurrentFrame, useVideoConfig} from 'remotion';
import {Sfx} from '../components/Kit';
import {C, FONT, ease, sp} from '../theme';

const OLD = ['Papirni nalozi.', 'Ručni unos.', 'Izgubljeni podaci.'];
const OUT = 70;

// Paper work orders drifting around: the "before" picture.
const SHEETS = Array.from({length: 9}).map((_, i) => ({
  x: [60, 700, 120, 760, 40, 640, 380, 820, 300][i],
  y: [120, 160, 1300, 1260, 1560, 1600, 1720, 420, 80][i],
  r: (random(`r${i}`) - 0.5) * 40,
  s: 0.75 + random(`s${i}`) * 0.4,
  d: i * 3,
}));

const Sheet: React.FC<{i: number}> = ({i}) => (
  <div style={{position: 'relative', width: 200, height: 270, background: '#f4f1ea', borderRadius: 4, padding: 18, boxSizing: 'border-box', boxShadow: '0 20px 40px rgba(0,0,0,.35)', fontFamily: FONT}}>
    <div style={{fontSize: 13, fontWeight: 800, color: '#3b3b3b', letterSpacing: 1}}>RADNI NALOG</div>
    <div style={{fontSize: 11, color: '#777', marginTop: 4, fontFamily: 'Comic Sans MS, cursive'}}>br. {1680 + i} / {['?', '24.10.', 'hitno', '??'][i % 4]}</div>
    {Array.from({length: 7}).map((_, k) => (
      <div key={k} style={{height: 2, background: '#cfc9bb', marginTop: 18, width: `${60 + random(`l${i}${k}`) * 40}%`}} />
    ))}
    {i % 3 === 0 && <div style={{position: 'absolute', right: 26, bottom: 40, width: 60, height: 60, borderRadius: '50%', border: '6px solid rgba(140,90,40,.25)'}} />}
    {i % 2 === 1 && <div style={{position: 'absolute', left: 30, bottom: 30, fontSize: 30, color: '#c0392b', fontWeight: 800, transform: 'rotate(-12deg)', fontFamily: 'Comic Sans MS, cursive'}}>?!</div>}
  </div>
);

export const Hook: React.FC = () => {
  const frame = useCurrentFrame();
  const {fps} = useVideoConfig();
  const away = ease(frame, [OUT, OUT + 16], [0, 1]);
  const fin = sp(frame, fps, OUT + 10, 14);

  return (
    <AbsoluteFill style={{fontFamily: FONT}}>
      {SHEETS.map((s, i) => {
        const p = sp(frame, fps, s.d, 18);
        const drift = Math.sin((frame + i * 20) / 24) * 10;
        return (
          <div
            key={i}
            style={{
              position: 'absolute',
              left: s.x,
              top: s.y + (1 - p) * 300 + drift + away * (i % 2 ? 1 : -1) * 900,
              opacity: Math.min(1, p) * 0.55 * (1 - away),
              transform: `rotate(${s.r + frame * 0.08 * (i % 2 ? 1 : -1) + away * 90}deg) scale(${s.s})`,
            }}
          >
            <Sheet i={i} />
          </div>
        );
      })}
      <AbsoluteFill style={{justifyContent: 'center', padding: '0 90px', gap: 26}}>
        {OLD.map((w, i) => {
          const p = sp(frame, fps, i * 10, 16);
          const strike = ease(frame, [34 + i * 7, 46 + i * 7], [0, 1]);
          return (
            <div key={w} style={{position: 'relative', alignSelf: 'flex-start', opacity: Math.min(1, p) * (1 - away) * (1 - strike * 0.45), transform: `translateX(${(1 - p) * -120}px) translateY(${away * -80}px)`, filter: `blur(${away * 8}px)`}}>
              <span style={{fontSize: 80, fontWeight: 800, color: C.white, letterSpacing: -2, whiteSpace: 'nowrap'}}>{w}</span>
              {strike > 0 && <span style={{position: 'absolute', left: -8, top: '54%', height: 10, width: `calc((100% + 16px) * ${strike})`, background: C.ice, borderRadius: 5}} />}
            </div>
          );
        })}
      </AbsoluteFill>
      <AbsoluteFill style={{alignItems: 'center', justifyContent: 'center', padding: '0 80px'}}>
        <div style={{textAlign: 'center', opacity: Math.min(1, fin), transform: `scale(${0.8 + fin * 0.2})`}}>
          <div style={{fontSize: 44, fontWeight: 700, color: C.ice, letterSpacing: 6, textTransform: 'uppercase'}}>Sada</div>
          <div style={{fontSize: 122, fontWeight: 800, color: C.white, lineHeight: 1.04, letterSpacing: -3, marginTop: 18}}>
            Digitalni tok
            <br />
            proizvodnje.
          </div>
          <div style={{height: 10, width: 560 * ease(frame, [OUT + 22, OUT + 40], [0, 1]), background: C.white, margin: '34px auto 0', borderRadius: 5}} />
        </div>
      </AbsoluteFill>
      {OLD.map((_, i) => (
        <Sfx key={i} at={i * 10} src="pop" volume={0.45} />
      ))}
      {OLD.map((_, i) => (
        <Sfx key={`s${i}`} at={34 + i * 7} src="click" volume={0.3} />
      ))}
      <Sfx at={OUT} src="whoosh" volume={0.5} />
      <Sfx at={OUT + 10} src="impact" volume={0.6} />
    </AbsoluteFill>
  );
};
