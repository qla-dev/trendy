import React from 'react';
import {AbsoluteFill, Audio, Img, Sequence, staticFile, useCurrentFrame, useVideoConfig} from 'remotion';
import {C, FONT, clamp, ease, rise, sp} from '../theme';
import {interpolate} from 'remotion';

export const Backdrop: React.FC = () => {
  const frame = useCurrentFrame();
  return (
    <AbsoluteFill style={{background: `linear-gradient(170deg, ${C.navy} 0%, ${C.navyDark} 48%, ${C.navyDeep} 100%)`, overflow: 'hidden'}}>
      <AbsoluteFill
        style={{
          backgroundImage: 'linear-gradient(rgba(255,255,255,.045) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.045) 1px, transparent 1px)',
          backgroundSize: '72px 72px',
          backgroundPosition: `0 ${frame * 0.6}px`,
        }}
      />
      <div
        style={{
          position: 'absolute',
          width: 1400,
          height: 1400,
          left: -160 + Math.sin(frame / 90) * 80,
          top: 380 + Math.cos(frame / 110) * 90,
          borderRadius: '50%',
          background: 'radial-gradient(circle, rgba(195,206,220,.22) 0%, transparent 62%)',
        }}
      />
    </AbsoluteFill>
  );
};

// The app logo (navy circle). The PNG has white margins, so it is cropped to its circle.
export const Logo: React.FC<{size: number; style?: React.CSSProperties}> = ({size, style}) => (
  <div style={{width: size, height: size, borderRadius: '50%', overflow: 'hidden', position: 'relative', boxShadow: '0 30px 80px rgba(0,0,0,.35)', ...style}}>
    <Img src={staticFile('trendy-logo.png')} style={{position: 'absolute', width: size / 0.9, left: '50%', top: '50%', transform: 'translate(-50%, -50%)'}} />
  </div>
);

export const Caption: React.FC<{kicker: string; title: string; text: string; top?: number}> = ({kicker, title, text, top = 150}) => {
  const frame = useCurrentFrame();
  const {fps} = useVideoConfig();
  const words = title.split(' ');
  return (
    <div style={{position: 'absolute', left: 80, right: 80, top, fontFamily: FONT, color: C.white}}>
      <div style={{display: 'flex', alignItems: 'center', gap: 18, ...rise(sp(frame, fps, 0), 20)}}>
        <span style={{height: 4, width: 70 * sp(frame, fps, 3), background: C.ice, borderRadius: 2}} />
        <span style={{fontSize: 30, fontWeight: 700, letterSpacing: 5, textTransform: 'uppercase', color: C.ice}}>{kicker}</span>
      </div>
      <div style={{fontSize: 78, lineHeight: 1.06, fontWeight: 800, margin: '22px 0 20px', letterSpacing: -1.5}}>
        {words.map((w, i) => (
          <span key={i} style={{display: 'inline-block', marginRight: 20, ...rise(sp(frame, fps, 5 + i * 3), 46)}}>
            {w}
          </span>
        ))}
      </div>
      <div style={{fontSize: 36, lineHeight: 1.4, fontWeight: 500, color: 'rgba(255,255,255,.82)', ...rise(sp(frame, fps, 16), 24)}}>{text}</div>
    </div>
  );
};

// Phone with real app screenshots; each later screen slides in at its `at` frame.
export const Phone: React.FC<{
  screens: {src: string; at: number; mode?: 'slide' | 'fade'}[];
  width?: number;
  style?: React.CSSProperties;
  children?: React.ReactNode;
}> = ({screens, width = 560, style, children}) => {
  const frame = useCurrentFrame();
  const inner = width - 28;
  const h = (inner * 2532) / 1170;
  return (
    <div style={{width, height: h + 28, borderRadius: 74, background: '#0b0d10', padding: 14, boxShadow: '0 60px 120px rgba(0,0,0,.5), inset 0 0 0 2px #2c3138', ...style}}>
      <div style={{width: inner, height: h, borderRadius: 62, overflow: 'hidden', position: 'relative', background: C.bg}}>
        {screens.map((s, i) => {
          const p = i === 0 ? 1 : ease(frame, [s.at, s.at + 16], [0, 1]);
          if (p <= 0) return null;
          const slide = (s.mode ?? 'slide') === 'slide';
          return (
            <Img
              key={s.src + i}
              src={staticFile(`screens/${s.src}.png`)}
              style={{position: 'absolute', inset: 0, width: inner, transform: slide ? `translateX(${(1 - p) * 100}%)` : undefined, opacity: slide ? 1 : p}}
            />
          );
        })}
        {children}
        <div style={{position: 'absolute', top: 14, left: '50%', marginLeft: -78, width: 156, height: 42, borderRadius: 21, background: '#0b0d10'}} />
      </div>
    </div>
  );
};

export type Cam = {f: number; x: number; y: number; w: number};

// A desktop screenshot seen through a moving camera (crop rect in screenshot pixels).
export const Desk: React.FC<{
  src: string;
  nat: {w: number; h: number};
  cams: Cam[];
  width?: number;
  height?: number;
  url?: string;
  style?: React.CSSProperties;
  children?: (toView: (x: number, y: number) => {left: number; top: number}) => React.ReactNode;
}> = ({src, nat, cams, width = 960, height = 900, url = 'enalog.app', style, children}) => {
  const frame = useCurrentFrame();
  const fs = cams.map((c) => c.f);
  const pick = (k: 'x' | 'y' | 'w') => (cams.length === 1 ? cams[0][k] : ease(frame, fs, cams.map((c) => c[k])));
  const cx = pick('x');
  const cy = pick('y');
  const cw = pick('w');
  const bar = 54;
  const scale = width / cw;
  const toView = (x: number, y: number) => ({left: (x - cx) * scale, top: (y - cy) * scale + bar});
  return (
    <div style={{width, height, borderRadius: 30, overflow: 'hidden', background: C.bg, position: 'relative', boxShadow: '0 50px 110px rgba(0,0,0,.45)', ...style}}>
      <div style={{position: 'absolute', top: bar, left: 0, width: nat.w * scale, height: nat.h * scale, transform: `translate(${-cx * scale}px, ${-cy * scale}px)`}}>
        <Img src={staticFile(`screens/${src}.png`)} style={{width: '100%', height: '100%'}} />
      </div>
      <div style={{position: 'absolute', top: 0, left: 0, right: 0, height: bar, background: '#eceef2', display: 'flex', alignItems: 'center', gap: 10, padding: '0 22px'}}>
        {['#ff5f57', '#febc2e', '#28c840'].map((c) => (
          <span key={c} style={{width: 14, height: 14, borderRadius: 7, background: c}} />
        ))}
        <span style={{marginLeft: 18, flex: 1, height: 32, borderRadius: 16, background: C.white, fontFamily: FONT, fontSize: 19, color: '#6e6b7b', display: 'flex', alignItems: 'center', padding: '0 16px'}}>
          {url}
        </span>
      </div>
      {children?.(toView)}
    </div>
  );
};

// Finger-tap ripple.
export const Tap: React.FC<{at: number; left: number; top: number}> = ({at, left, top}) => {
  const frame = useCurrentFrame();
  const t = frame - at;
  if (t < -8 || t > 26) return null;
  const press = interpolate(t, [-8, 0, 6], [0, 1, 0.85], clamp);
  const ring = interpolate(t, [0, 24], [0, 1], clamp);
  return (
    <div style={{position: 'absolute', left, top, pointerEvents: 'none', zIndex: 40}}>
      <div style={{position: 'absolute', width: 70, height: 70, marginLeft: -35, marginTop: -35, borderRadius: '50%', background: 'rgba(73,91,115,.35)', border: '3px solid rgba(255,255,255,.9)', transform: `scale(${press})`, opacity: press}} />
      <div style={{position: 'absolute', width: 70, height: 70, marginLeft: -35, marginTop: -35, borderRadius: '50%', border: '4px solid rgba(255,255,255,.85)', transform: `scale(${1 + ring * 1.6})`, opacity: 1 - ring}} />
    </div>
  );
};

// Soft spotlight frame around a region.
export const Focus: React.FC<{from: number; to: number; left: number; top: number; width: number; height: number}> = ({from, to, left, top, width, height}) => {
  const frame = useCurrentFrame();
  const o = interpolate(frame, [from, from + 8, to - 8, to], [0, 1, 1, 0], clamp);
  return (
    <div
      style={{
        position: 'absolute',
        left: left - 10,
        top: top - 10,
        width: width + 20,
        height: height + 20,
        borderRadius: 18,
        border: `5px solid ${C.navy}`,
        boxShadow: '0 0 0 9999px rgba(20,28,38,.32)',
        opacity: o,
        zIndex: 30,
      }}
    />
  );
};

export const Sfx: React.FC<{at: number; src: string; volume?: number}> = ({at, src, volume = 0.6}) => (
  <Sequence from={at} layout="none">
    <Audio src={staticFile(`audio/${src}.wav`)} volume={volume} />
  </Sequence>
);

export const Enter: React.FC<{delay?: number; children: React.ReactNode; style?: React.CSSProperties}> = ({delay = 6, children, style}) => {
  const frame = useCurrentFrame();
  const {fps} = useVideoConfig();
  const p = sp(frame, fps, delay, 20);
  return <div style={{...style, opacity: Math.min(1, p * 1.5), transform: `translateY(${(1 - p) * 260}px) scale(${0.92 + p * 0.08})`}}>{children}</div>;
};

export const PoweredBy: React.FC<{at?: number; bottom?: number}> = ({at = 0, bottom = 90}) => {
  const frame = useCurrentFrame();
  const {fps} = useVideoConfig();
  return (
    <div style={{position: 'absolute', bottom, left: 0, right: 0, display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 16, fontFamily: FONT, ...rise(sp(frame, fps, at), 20)}}>
      <span style={{fontSize: 24, fontWeight: 600, letterSpacing: 3, color: 'rgba(255,255,255,.6)', textTransform: 'uppercase'}}>powered by</span>
      <Img src={staticFile('qla-logo-dark.png')} style={{height: 46}} />
    </div>
  );
};

/* ---------- Themed drifting background objects (like the paper orders in the hook) ---------- */
export type AmbientKind = 'ai' | 'plan' | 'teren' | 'skl' | 'oper' | 'nfc';
const SPOTS = [
  {x: -40, y: 400, r: -14},
  {x: 930, y: 360, r: 12},
  {x: -60, y: 930, r: 8},
  {x: 960, y: 860, r: -10},
  {x: -30, y: 1470, r: 16},
  {x: 950, y: 1390, r: -6},
  {x: 120, y: 1790, r: -8},
  {x: 800, y: 1780, r: 10},
];

const gearPath = (() => {
  const pts: string[] = [];
  for (let i = 0; i < 10; i++) {
    const a = (i / 10) * Math.PI * 2;
    const st = (Math.PI * 2) / 10;
    const p = (ang: number, r: number) => `${(50 + Math.cos(ang) * r).toFixed(1)} ${(50 + Math.sin(ang) * r).toFixed(1)}`;
    pts.push(p(a - st * 0.3, 38), p(a - st * 0.16, 48), p(a + st * 0.16, 48), p(a + st * 0.3, 38));
  }
  return `M ${pts.join(' L ')} Z M 66 50 a 16 16 0 1 0 -32 0 a 16 16 0 1 0 32 0 Z`;
})();

const Thing: React.FC<{kind: AmbientKind; i: number}> = ({kind, i}) => {
  const paper: React.CSSProperties = {background: '#f4f6f9', borderRadius: 8, boxShadow: '0 20px 40px rgba(0,0,0,.35)', fontFamily: FONT, position: 'relative'};
  const lines = (n: number) =>
    Array.from({length: n}).map((_, k) => <div key={k} style={{height: 3, borderRadius: 2, background: '#d5dbe3', marginTop: 12, width: `${55 + ((k * 37 + i * 13) % 40)}%`}} />);
  if (kind === 'ai')
    return i % 2 ? (
      <div style={{...paper, width: 170, height: 220, padding: 18, boxSizing: 'border-box'}}>
        <span style={{position: 'absolute', left: -10, top: 24, background: C.navy, color: '#fff', fontSize: 15, fontWeight: 800, padding: '3px 10px', borderRadius: 5}}>PDF</span>
        <div style={{height: 34}} />
        {lines(6)}
      </div>
    ) : (
      <div style={{...paper, width: 200, height: 136, overflow: 'hidden'}}>
        <div style={{position: 'absolute', inset: 0, background: `linear-gradient(155deg, transparent 49%, #d5dbe3 50%, transparent 52%), linear-gradient(25deg, transparent 49%, #d5dbe3 50%, transparent 52%)`}} />
        <span style={{position: 'absolute', right: 12, bottom: 10, fontSize: 30, fontWeight: 800, color: C.navy}}>@</span>
      </div>
    );
  if (kind === 'plan')
    return (
      <div style={{...paper, width: 150, height: 160, overflow: 'hidden', textAlign: 'center'}}>
        <div style={{background: C.navy, color: '#fff', fontSize: 16, fontWeight: 700, padding: '8px 0', letterSpacing: 2}}>{['OKT', 'NOV', 'DEC', 'OKT'][i % 4]}</div>
        <div style={{fontSize: 58, fontWeight: 800, color: C.heading, marginTop: 8}}>{[24, 9, 31, 14, 7, 21, 3, 18][i]}</div>
      </div>
    );
  if (kind === 'teren')
    return (
      <div style={{...paper, width: 160, padding: 14, boxSizing: 'border-box'}}>
        <Img src={staticFile('app/images/qr-label.svg')} style={{width: '100%', display: 'block'}} />
        <div style={{fontSize: 11, fontWeight: 700, color: C.heading, marginTop: 8, textAlign: 'center'}}>RN 26-6000-00016{80 + i}</div>
      </div>
    );
  if (kind === 'skl')
    return i % 2 ? (
      <div style={{...paper, width: 210, padding: '14px 16px', boxSizing: 'border-box'}}>
        <div style={{display: 'flex', gap: 3, height: 60}}>
          {Array.from({length: 30}).map((_, k) => (
            <span key={k} style={{width: [2, 4, 1, 3, 2, 5][(k + i) % 6], background: '#1b1b1b'}} />
          ))}
        </div>
        <div style={{fontSize: 12, fontWeight: 700, color: C.heading, marginTop: 6, letterSpacing: 2}}>{['LIM-S235-5', 'AL-6082-20', 'SIP-S355-40', 'VIJ-M10-35'][i % 4]}</div>
      </div>
    ) : (
      <div style={{width: 170, height: 150, borderRadius: 10, background: 'linear-gradient(160deg, #c9a878, #a8875a)', boxShadow: '0 20px 40px rgba(0,0,0,.35)', position: 'relative'}}>
        <div style={{position: 'absolute', left: '42%', top: 0, bottom: 0, width: '16%', background: 'rgba(255,255,255,.25)'}} />
        <div style={{position: 'absolute', right: 14, bottom: 14, width: 70, height: 40, background: '#fff', borderRadius: 4}} />
      </div>
    );
  if (kind === 'oper')
    return (
      <svg width={i % 2 ? 190 : 140} height={i % 2 ? 190 : 140} viewBox="0 0 100 100">
        <path d={gearPath} fill={i % 3 === 0 ? '#c3cedc' : '#e9edf2'} fillRule="evenodd" />
      </svg>
    );
  return (
    <div style={{width: 210, height: 132, borderRadius: 14, background: 'radial-gradient(140% 120% at 20% 0%, #fff 0%, #f4f4f2 55%, #e6e6e3 100%)', boxShadow: '0 20px 40px rgba(0,0,0,.35)', display: 'grid', placeItems: 'center'}}>
      <Img src={staticFile('app/images/pwa/trendy-gear-logo.png')} style={{width: '55%'}} />
    </div>
  );
};

export const Ambient: React.FC<{kind: AmbientKind}> = ({kind}) => {
  const frame = useCurrentFrame();
  const {fps} = useVideoConfig();
  return (
    <AbsoluteFill style={{pointerEvents: 'none'}}>
      {SPOTS.map((s, i) => {
        const p = sp(frame, fps, i * 3, 20);
        const spin = kind === 'oper' ? frame * (i % 2 ? 0.6 : -0.8) : Math.sin((frame + i * 30) / 50) * 6;
        return (
          <div
            key={i}
            style={{
              position: 'absolute',
              left: s.x,
              top: s.y + Math.sin((frame + i * 25) / 30) * 14 + (1 - p) * 200,
              opacity: Math.min(1, p) * 0.32,
              transform: `rotate(${s.r + spin}deg) scale(${0.8 + (i % 3) * 0.12})`,
              filter: i % 3 === 1 ? 'blur(2px)' : undefined,
            }}
          >
            <Thing kind={kind} i={i} />
          </div>
        );
      })}
    </AbsoluteFill>
  );
};
