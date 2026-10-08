import React from 'react';
import {Img, staticFile, useCurrentFrame, useVideoConfig} from 'remotion';
import {C, FONT, clamp, ease, sp} from '../theme';
import {interpolate} from 'remotion';

// Vuexy card look, scaled up for a 1080px-wide video.
export const AppCard: React.FC<{children: React.ReactNode; style?: React.CSSProperties}> = ({children, style}) => (
  <div style={{background: C.white, borderRadius: 22, boxShadow: '0 40px 90px rgba(10,16,24,.38), 0 4px 24px rgba(34,41,47,.1)', padding: 36, fontFamily: FONT, color: '#6e6b7b', ...style}}>
    {children}
  </div>
);

// An element that rises out of the screen behind it.
export const Pop: React.FC<{at: number; from?: {x?: number; y?: number; s?: number}; style?: React.CSSProperties; children: React.ReactNode}> = ({at, from = {}, style, children}) => {
  const frame = useCurrentFrame();
  const {fps} = useVideoConfig();
  const p = sp(frame, fps, at, 16);
  if (frame < at) return null;
  const {x = 0, y = 160, s = 0.82} = from;
  return (
    <div
      style={{
        position: 'absolute',
        ...style,
        opacity: Math.min(1, p * 1.6),
        transform: `translate(${(1 - p) * x}px, ${(1 - p) * y}px) scale(${s + (1 - s) * p})`,
      }}
    >
      {children}
    </div>
  );
};

// Laptop showing a real app screenshot (2880x1800).
export const Laptop: React.FC<{src: string; dim?: number; style?: React.CSSProperties; objectY?: number}> = ({src, dim = 0, style, objectY = 0}) => {
  const W = 960;
  const sw = W - 36;
  const sh = (sw * 1800) / 2880;
  return (
    <div style={{width: W, ...style}}>
      <div style={{background: '#0d1014', borderRadius: '28px 28px 8px 8px', padding: '18px 18px 22px', boxShadow: '0 50px 100px rgba(0,0,0,.45), inset 0 0 0 2px #2c3138'}}>
        <div style={{width: sw, height: sh, borderRadius: 6, overflow: 'hidden', position: 'relative', background: C.bg}}>
          <Img src={staticFile(`screens/${src}.png`)} style={{width: '100%', position: 'absolute', top: -objectY * (sw / 2880)}} />
          <div style={{position: 'absolute', inset: 0, background: `rgba(24,32,44,${dim * 0.45})`}} />
        </div>
      </div>
      <div style={{height: 26, margin: '0 -34px', borderRadius: '0 0 26px 26px', background: 'linear-gradient(180deg, #d5dae1, #9aa3ae)'}} />
    </div>
  );
};

// Phone with a real status bar so the island never covers app content.
export const Phone: React.FC<{
  screens: {src: string; at: number}[];
  width?: number;
  dark?: boolean;
  dim?: number;
  style?: React.CSSProperties;
  children?: React.ReactNode;
}> = ({screens, width = 500, dark, dim = 0, style, children}) => {
  const frame = useCurrentFrame();
  const inner = width - 26;
  const bar = 50;
  const h = (inner * 2532) / 1170 * 0.93 + bar;
  return (
    <div style={{width, height: h + 26, borderRadius: 66, background: '#0b0d10', padding: 13, boxShadow: '0 60px 120px rgba(0,0,0,.5), inset 0 0 0 2px #2c3138', ...style}}>
      <div style={{width: inner, height: h, borderRadius: 54, overflow: 'hidden', position: 'relative', background: dark ? '#05070a' : C.bg}}>
        <div style={{position: 'absolute', top: bar, left: 0, right: 0, bottom: 0, overflow: 'hidden'}}>
          {screens.map((s, i) => {
            const p = i === 0 ? 1 : ease(frame, [s.at, s.at + 14], [0, 1]);
            if (p <= 0) return null;
            return <Img key={s.src + i} src={staticFile(`screens/${s.src}.png`)} style={{position: 'absolute', top: 0, left: 0, width: inner, opacity: p}} />;
          })}
          <div style={{position: 'absolute', inset: 0, background: `rgba(24,32,44,${dim * 0.45})`}} />
        </div>
        <div style={{position: 'absolute', top: 0, left: 0, right: 0, height: bar, display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '4px 40px 0', fontFamily: FONT, fontWeight: 700, fontSize: 20, color: dark ? C.white : '#1b1b1b'}}>
          <span>9:41</span>
          <span style={{display: 'flex', gap: 6, alignItems: 'center'}}>
            <span style={{width: 26, height: 13, border: `2px solid ${dark ? '#fff' : '#1b1b1b'}`, borderRadius: 4, padding: 1}}>
              <span style={{display: 'block', width: '75%', height: '100%', background: dark ? '#fff' : '#1b1b1b', borderRadius: 1}} />
            </span>
          </span>
        </div>
        <div style={{position: 'absolute', top: 10, left: '50%', marginLeft: -62, width: 124, height: 34, borderRadius: 17, background: '#0b0d10'}} />
        {children}
      </div>
    </div>
  );
};

export const Spinner: React.FC<{size?: number; color?: string; track?: string}> = ({size = 30, color = '#0e7a6b', track = 'rgba(14,122,107,.18)'}) => {
  const frame = useCurrentFrame();
  return <span style={{display: 'inline-block', width: size, height: size, borderRadius: '50%', border: `${Math.max(3, size / 8)}px solid ${track}`, borderTopColor: color, transform: `rotate(${frame * 16}deg)`}} />;
};

export const Check: React.FC<{size?: number; on: number; color?: string}> = ({size = 34, on, color = C.green}) => (
  <span style={{width: size, height: size, borderRadius: size / 3.2, display: 'inline-grid', placeItems: 'center', background: on > 0 ? color : 'transparent', border: on > 0 ? 'none' : '3px solid #c9c7d1', transform: `scale(${on > 0 ? 0.6 + Math.min(on, 1) * 0.4 : 1})`, flexShrink: 0}}>
    {on > 0 && (
      <svg width={size * 0.6} height={size * 0.6} viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth={3.6} strokeLinecap="round" strokeLinejoin="round">
        <path d="M20 6L9 17l-5-5" />
      </svg>
    )}
  </span>
);

export const Chip: React.FC<{children: React.ReactNode; style?: React.CSSProperties}> = ({children, style}) => (
  <div style={{display: 'inline-flex', alignItems: 'center', gap: 14, background: C.white, borderRadius: 18, padding: '18px 26px', fontFamily: FONT, fontWeight: 700, fontSize: 28, color: C.heading, boxShadow: '0 30px 70px rgba(10,16,24,.4)', ...style}}>
    {children}
  </div>
);

export const Count: React.FC<{from: number; to: number; at: number; dur?: number; decimals?: number}> = ({from, to, at, dur = 40, decimals = 0}) => {
  const frame = useCurrentFrame();
  const v = interpolate(frame, [at, at + dur], [0, to], clamp);
  return <>{v.toLocaleString('de-DE', {minimumFractionDigits: decimals, maximumFractionDigits: decimals})}</>;
};

// Simple caption for vertical scenes.
export const Title: React.FC<{kicker: string; title: string; sub?: string}> = ({kicker, title, sub}) => {
  const frame = useCurrentFrame();
  const {fps} = useVideoConfig();
  const r = (d: number, dist = 30) => {
    const p = sp(frame, fps, d);
    return {opacity: Math.min(1, p), transform: `translateY(${(1 - p) * dist}px)`};
  };
  return (
    <div style={{position: 'absolute', top: 130, left: 80, right: 80, fontFamily: FONT, color: C.white}}>
      <div style={{fontSize: 28, fontWeight: 700, letterSpacing: 6, color: C.ice, textTransform: 'uppercase', ...r(0, 16)}}>{kicker}</div>
      <div style={{fontSize: 76, fontWeight: 800, lineHeight: 1.05, letterSpacing: -1.5, marginTop: 16, ...r(5, 40)}}>{title}</div>
      {sub && <div style={{fontSize: 34, fontWeight: 500, color: 'rgba(255,255,255,.78)', marginTop: 18, ...r(12, 20)}}>{sub}</div>}
    </div>
  );
};
