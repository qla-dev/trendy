import React from 'react';
import {Audio, Img, interpolate, Sequence, staticFile, useCurrentFrame, useVideoConfig} from 'remotion';
import {C, FONT, clamp, ease, sp} from '../theme';

/* ------------------------------------------------------------------ */
/* Simplified eNalog.app UI, drawn live so everything on screen moves. */
/* ------------------------------------------------------------------ */

export const T = {text: '#6e6b7b', head: '#5e5873', muted: '#b9b9c3', border: '#ebe9f1', soft: '#f3f2f7'};

const PATHS: Record<string, string[]> = {
  home: ['M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z'],
  file: ['M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z', 'M14 2v6h6', 'M16 13H8', 'M16 17H8'],
  zap: ['M13 2L3 14h9l-1 8 10-12h-9l1-8z'],
  calendar: ['M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z', 'M16 2v4', 'M8 2v4', 'M3 10h18'],
  box: ['M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z', 'M3.3 7L12 12l8.7-5', 'M12 22V12'],
  layers: ['M12 2L2 7l10 5 10-5-10-5z', 'M2 17l10 5 10-5', 'M2 12l10 5 10-5'],
  card: ['M3 4h18a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z', 'M1 10h22'],
  search: ['M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16z', 'M21 21l-4.35-4.35'],
  upload: ['M16 16l-4-4-4 4', 'M12 12v9', 'M20.4 18.4A5 5 0 0 0 18 9h-1.3A8 8 0 1 0 3 16.3'],
  menu: ['M3 12h18', 'M3 6h18', 'M3 18h18'],
  check: ['M20 6L9 17l-5-5'],
  camera: ['M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z', 'M12 17a4 4 0 1 0 0-8 4 4 0 0 0 0 8z'],
  download: ['M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4', 'M7 10l5 5 5-5', 'M12 15V3'],
  user: ['M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2', 'M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z'],
  clock: ['M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20z', 'M12 6v6l4 2'],
  cpu: ['M6 4h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z', 'M9 9h6v6H9z'],
  wifi: ['M5 12.55a11 11 0 0 1 14.08 0', 'M1.42 9a16 16 0 0 1 21.16 0', 'M8.53 16.11a6 6 0 0 1 6.95 0', 'M12 20h.01'],
};

export const Ico: React.FC<{n: string; s?: number; c?: string; w?: number; style?: React.CSSProperties}> = ({n, s = 18, c = 'currentColor', w = 2, style}) => (
  <svg width={s} height={s} viewBox="0 0 24 24" fill="none" stroke={c} strokeWidth={w} strokeLinecap="round" strokeLinejoin="round" style={{flexShrink: 0, ...style}}>
    {PATHS[n].map((d, i) => (
      <path key={i} d={d} />
    ))}
  </svg>
);

export const LogoDot: React.FC<{s: number}> = ({s}) => (
  <div style={{width: s, height: s, borderRadius: '50%', overflow: 'hidden', position: 'relative', flexShrink: 0}}>
    <Img src={staticFile('trendy-logo.png')} style={{position: 'absolute', width: s / 0.9, left: '50%', top: '50%', transform: 'translate(-50%,-50%)'}} />
  </div>
);

/* Typing text with caret. cps = chars per second */
export const Type: React.FC<{text: string; at: number; cps?: number; caret?: boolean; style?: React.CSSProperties}> = ({text, at, cps = 22, caret = true, style}) => {
  const frame = useCurrentFrame();
  const {fps} = useVideoConfig();
  const n = Math.max(0, Math.min(text.length, Math.floor(((frame - at) * cps) / fps)));
  const typing = frame >= at && n < text.length;
  return (
    <span style={style}>
      {text.slice(0, n)}
      {caret && (typing || (frame >= at && frame < at + (text.length * fps) / cps + 18)) && (
        <span style={{display: 'inline-block', width: '0.08em', minWidth: 2, height: '1em', marginLeft: 2, verticalAlign: '-0.12em', background: 'currentColor', opacity: typing || frame % 16 < 8 ? 1 : 0}} />
      )}
    </span>
  );
};
export const typeEnd = (text: string, at: number, cps = 22, fps = 30) => at + Math.ceil((text.length * fps) / cps);

/* Charging progress bar */
export const Charge: React.FC<{pct: number; h?: number; done?: boolean; colors?: [string, string]}> = ({pct, h = 12, done, colors = ['#18cbb7', '#347cf7']}) => {
  const frame = useCurrentFrame();
  return (
    <div style={{height: h, borderRadius: h / 2, background: '#eef1f5', overflow: 'hidden', position: 'relative'}}>
      <div style={{position: 'absolute', left: 0, top: 0, bottom: 0, width: `${pct}%`, borderRadius: h / 2, background: done ? `linear-gradient(90deg, ${colors[0]}, ${C.green})` : `linear-gradient(90deg, ${colors[0]}, ${colors[1]})`, boxShadow: `0 0 ${h}px ${colors[0]}88`}}>
        {!done && (
          <>
            <div style={{position: 'absolute', inset: 0, backgroundImage: 'repeating-linear-gradient(-45deg, rgba(255,255,255,.3) 0 8px, transparent 8px 16px)', backgroundSize: '200% 100%', backgroundPosition: `${frame * 2}px 0`}} />
            <div style={{position: 'absolute', right: 0, top: 0, bottom: 0, width: h * 3, background: 'linear-gradient(90deg, transparent, rgba(255,255,255,.9))'}} />
          </>
        )}
      </div>
    </div>
  );
};

export const Spin: React.FC<{s?: number; c?: string}> = ({s = 16, c = C.navy}) => {
  const frame = useCurrentFrame();
  return <span style={{display: 'inline-block', width: s, height: s, borderRadius: '50%', border: `${Math.max(2, s / 7)}px solid ${c}33`, borderTopColor: c, transform: `rotate(${frame * 16}deg)`, flexShrink: 0}} />;
};

export const Tick: React.FC<{s?: number; on: boolean; at?: number; c?: string}> = ({s = 18, on, at = 0, c = C.green}) => {
  const frame = useCurrentFrame();
  const {fps} = useVideoConfig();
  const p = on ? sp(frame, fps, at, 10) : 0;
  return (
    <span style={{width: s, height: s, borderRadius: s / 3.5, display: 'inline-grid', placeItems: 'center', background: on ? c : 'transparent', border: on ? 'none' : `2px solid #c9c7d1`, transform: `scale(${on ? 0.5 + p * 0.5 : 1})`, flexShrink: 0}}>
      {on && <Ico n="check" s={s * 0.66} c="#fff" w={3.5} />}
    </span>
  );
};

export const Num: React.FC<{to: number; at: number; dur?: number; dec?: number; from?: number}> = ({to, at, dur = 36, dec = 0, from = 0}) => {
  const frame = useCurrentFrame();
  const v = interpolate(frame, [at, at + dur], [from, to], {...clamp, easing: (t) => 1 - Math.pow(1 - t, 3)});
  return <>{v.toLocaleString('de-DE', {minimumFractionDigits: dec, maximumFractionDigits: dec})}</>;
};

export const Badge: React.FC<{c: string; bg: string; children: React.ReactNode; s?: number}> = ({c, bg, children, s = 11}) => (
  <span style={{display: 'inline-flex', alignItems: 'center', gap: 5, padding: `${s * 0.35}px ${s * 0.9}px`, borderRadius: 99, background: bg, color: c, fontSize: s, fontWeight: 600, whiteSpace: 'nowrap'}}>{children}</span>
);

export const Card: React.FC<{children: React.ReactNode; style?: React.CSSProperties}> = ({children, style}) => (
  <div style={{background: '#fff', borderRadius: 8, boxShadow: '0 4px 24px rgba(34,41,47,.08)', ...style}}>{children}</div>
);

/* Entrance for rows / items inside a screen */
export const In: React.FC<{at: number; children: React.ReactNode; style?: React.CSSProperties; dy?: number}> = ({at, children, style, dy = 14}) => {
  const frame = useCurrentFrame();
  const {fps} = useVideoConfig();
  const p = sp(frame, fps, at, 18);
  if (frame < at) return <div style={{...style, opacity: 0}}>{children}</div>;
  return <div style={{...style, opacity: Math.min(1, p * 1.4), transform: `translateY(${(1 - p) * dy}px)`}}>{children}</div>;
};

/* ---------------------------- Devices ---------------------------- */

// Device pose: top edge (video px) and scale, device is always horizontally centred.
export type Pose = {y: number; s: number; r: number};
export type PoseKey = {f: number; y: number; s?: number};

/* Flies the device in from below, then follows the keyframes. */
export const usePose = (keys: PoseKey[], enterAt = 0): Pose => {
  const frame = useCurrentFrame();
  const {fps} = useVideoConfig();
  const fs = keys.map((k) => k.f);
  const y = keys.length > 1 ? ease(frame, fs, keys.map((k) => k.y)) : keys[0].y;
  const sc = keys.length > 1 ? ease(frame, fs, keys.map((k) => k.s ?? 1)) : keys[0].s ?? 1;
  const e = sp(frame, fps, enterAt, 15);
  return {y: 2100 + (y - 2100) * e, s: sc * (0.85 + 0.15 * e), r: (1 - e) * 10};
};
const poseStyle = (pose: Pose, w: number): React.CSSProperties => ({
  position: 'absolute',
  left: (1080 - w) / 2,
  top: pose.y,
  transformOrigin: '50% 0',
  transform: `perspective(2400px) rotateX(${pose.r}deg) scale(${pose.s})`,
});


export const LAP = {w: 924, h: 578, bezel: 18, outer: 960, height: 640};
export const LAP_CENTER = (1920 - LAP.height) / 2 + 60;
export const LAP_UP = 400;
export const LAP_DOWN = 1250; // device drops, lifted element goes up
export const lapToVideo = (x: number, y: number, pose: Pose) => ({
  x: 540 + (60 + LAP.bezel + x - 540) * pose.s,
  y: pose.y + (LAP.bezel + y) * pose.s,
});

export const Laptop: React.FC<{children: React.ReactNode; pose: Pose; dim?: number}> = ({children, pose, dim = 0}) => (
  <div style={{...poseStyle(pose, LAP.outer), width: LAP.outer}}>
    <div style={{background: '#0d1014', borderRadius: '28px 28px 8px 8px', padding: `${LAP.bezel}px ${LAP.bezel}px 22px`, boxShadow: '0 50px 100px rgba(0,0,0,.45), inset 0 0 0 2px #2c3138'}}>
      <div style={{width: LAP.w, height: LAP.h, borderRadius: 6, overflow: 'hidden', position: 'relative', background: C.bg, fontFamily: FONT, color: T.text}}>
        {children}
        <div style={{position: 'absolute', inset: 0, background: `rgba(24,32,44,${dim * 0.5})`, pointerEvents: 'none'}} />
      </div>
    </div>
    <div style={{height: 26, margin: '0 -34px', borderRadius: '0 0 26px 26px', background: 'linear-gradient(180deg, #d5dae1, #9aa3ae)'}} />
  </div>
);

const MENU = [
  ['home', 'Kontrolna ploča'],
  ['file', 'Radni nalozi'],
  ['zap', 'AI narudžba'],
  ['calendar', 'Plan proizvodnje'],
  ['layers', 'Pregled zaliha'],
  ['card', 'NFC kartica'],
];

/* Desktop app shell. Content area origin: (176, 74), size 736 x 492 */
export const DESK_CONTENT = {x: 176, y: 74, w: 736, h: 492};
export const DeskShell: React.FC<{active: number; search?: React.ReactNode; children: React.ReactNode}> = ({active, search, children}) => (
  <div style={{position: 'absolute', inset: 0}}>
    <div style={{position: 'absolute', left: 0, top: 0, bottom: 0, width: 164, background: '#fff', boxShadow: '0 0 15px rgba(34,41,47,.05)', padding: '14px 10px'}}>
      <div style={{display: 'flex', alignItems: 'center', gap: 8, padding: '0 6px 16px'}}>
        <LogoDot s={26} />
        <span style={{fontWeight: 600, fontSize: 15, color: C.navy}}>eNalog.app</span>
      </div>
      {MENU.map(([ic, label], i) => (
        <div key={label} style={{display: 'flex', alignItems: 'center', gap: 9, padding: '8px 10px', margin: '2px 0', borderRadius: 5, fontSize: 11.5, color: i === active ? '#fff' : T.text, background: i === active ? `linear-gradient(118deg, ${C.navy}, rgba(73,91,115,.7))` : 'transparent', boxShadow: i === active ? '0 0 8px 1px rgba(73,91,115,.55)' : 'none'}}>
          <Ico n={ic} s={14} />
          {label}
        </div>
      ))}
    </div>
    <div style={{position: 'absolute', left: 176, right: 12, top: 12, height: 48, background: '#fff', borderRadius: 7, boxShadow: '0 4px 24px rgba(34,41,47,.08)', display: 'flex', alignItems: 'center', padding: '0 14px', gap: 12}}>
      <Ico n="search" s={15} c={T.text} />
      <span style={{flex: 1, fontSize: 12.5, color: T.head}}>{search ?? <span style={{color: T.muted}}>Pretraga…</span>}</span>
      <span style={{display: 'inline-flex', alignItems: 'center', gap: 6, padding: '5px 10px', borderRadius: 12, background: '#fff', boxShadow: '0 6px 14px rgba(34,41,47,.08)', fontSize: 11, fontWeight: 600, color: '#6e7f91'}}>
        Tokeni <span style={{width: 12, height: 9, borderRadius: 5, background: 'linear-gradient(180deg,#ffd86f,#f5b301)'}} /> <b style={{color: '#4b5d78'}}>12.480</b>
      </span>
      <span style={{width: 30, height: 30, borderRadius: 15, background: C.navy, color: '#fff', display: 'grid', placeItems: 'center', fontSize: 11, fontWeight: 700}}>AT</span>
    </div>
    <div style={{position: 'absolute', left: DESK_CONTENT.x, top: DESK_CONTENT.y, width: DESK_CONTENT.w, height: DESK_CONTENT.h}}>{children}</div>
  </div>
);

export const PH = {w: 500, pad: 13, bar: 50, height: 1076};
export const PH_INNER = PH.w - PH.pad * 2; // 474
export const PH_H = 1000;
export const PH_CENTER = (1920 - PH.height) / 2 + 70;
export const PH_UP = 380;
export const PH_DOWN = 1030;
export const phoneToVideo = (x: number, y: number, pose: Pose) => ({
  x: 540 + (290 + PH.pad + x - 540) * pose.s,
  y: pose.y + (PH.pad + PH.bar + y) * pose.s,
});

export const Phone: React.FC<{children: React.ReactNode; pose: Pose; dark?: boolean; dim?: number}> = ({children, pose, dark, dim = 0}) => (
  <div style={{...poseStyle(pose, PH.w), width: PH.w, borderRadius: 66, background: '#0b0d10', padding: PH.pad, boxShadow: '0 60px 120px rgba(0,0,0,.5), inset 0 0 0 2px #2c3138'}}>
    <div style={{width: PH_INNER, height: PH_H + PH.bar, borderRadius: 54, overflow: 'hidden', position: 'relative', background: dark ? '#05070a' : C.bg, fontFamily: FONT, color: T.text}}>
      <div style={{position: 'absolute', top: PH.bar, left: 0, width: PH_INNER, height: PH_H}}>{children}</div>
      <div style={{position: 'absolute', top: 0, left: 0, right: 0, height: PH.bar, display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '4px 40px 0', fontWeight: 700, fontSize: 19, color: dark ? '#fff' : '#1b1b1b'}}>
        <span>9:41</span>
        <span style={{width: 26, height: 13, border: `2px solid ${dark ? '#fff' : '#1b1b1b'}`, borderRadius: 4, padding: 1}}>
          <span style={{display: 'block', width: '75%', height: '100%', background: dark ? '#fff' : '#1b1b1b', borderRadius: 1}} />
        </span>
      </div>
      <div style={{position: 'absolute', top: 10, left: '50%', marginLeft: -62, width: 124, height: 34, borderRadius: 17, background: '#0b0d10'}} />
      <div style={{position: 'absolute', inset: 0, background: `rgba(24,32,44,${dim * 0.5})`, pointerEvents: 'none'}} />
    </div>
  </div>
);

export const MobileBar: React.FC<{title?: string}> = ({title}) => (
  <>
    <div style={{position: 'absolute', left: 16, right: 16, top: 12, height: 56, background: '#fff', borderRadius: 10, boxShadow: '0 4px 24px rgba(34,41,47,.08)', display: 'flex', alignItems: 'center', padding: '0 16px', gap: 14}}>
      <Ico n="menu" s={22} c={T.head} />
      <Ico n="camera" s={20} c={T.head} />
      <span style={{flex: 1}} />
      <span style={{display: 'inline-flex', alignItems: 'center', gap: 7, fontSize: 15, fontWeight: 600, color: '#6e7f91'}}>
        Tokeni <span style={{width: 14, height: 10, borderRadius: 5, background: 'linear-gradient(180deg,#ffd86f,#f5b301)'}} /> <b style={{color: '#4b5d78'}}>12.480</b>
      </span>
      <span style={{width: 36, height: 36, borderRadius: 18, background: C.navy, color: '#fff', display: 'grid', placeItems: 'center', fontSize: 13, fontWeight: 700}}>AT</span>
    </div>
    {title && <div style={{position: 'absolute', left: 20, top: 88, fontSize: 24, color: T.head, fontWeight: 500}}>{title}</div>}
  </>
);

/* Mouse cursor following keyframes, with click squeeze. */
export const Cursor: React.FC<{path: {f: number; x: number; y: number}[]; clicks?: number[]; from?: number; to?: number}> = ({path, clicks = [], from = 0, to = 99999}) => {
  const frame = useCurrentFrame();
  if (frame < from || frame > to) return null;
  const fs = path.map((p) => p.f);
  const x = path.length > 1 ? ease(frame, fs, path.map((p) => p.x)) : path[0].x;
  const y = path.length > 1 ? ease(frame, fs, path.map((p) => p.y)) : path[0].y;
  const down = clicks.some((c) => frame >= c - 2 && frame < c + 4);
  const ring = clicks.map((c) => frame - c).find((t) => t >= 0 && t < 16);
  return (
    <div style={{position: 'absolute', left: x, top: y, zIndex: 50, pointerEvents: 'none'}}>
      {ring !== undefined && <div style={{position: 'absolute', left: -14, top: -14, width: 28, height: 28, borderRadius: '50%', border: '2px solid rgba(73,91,115,.7)', transform: `scale(${1 + ring / 8})`, opacity: 1 - ring / 16}} />}
      <svg width={22} height={22} viewBox="0 0 24 24" style={{transform: `scale(${down ? 0.82 : 1})`, filter: 'drop-shadow(0 3px 5px rgba(0,0,0,.35))'}}>
        <path d="M4 2l16 9.5-7 1.6-3.6 6.4z" fill="#1b1b1b" stroke="#fff" strokeWidth={1.6} strokeLinejoin="round" />
      </svg>
    </div>
  );
};

/* Finger tap for phones */
export const Finger: React.FC<{at: number; x: number; y: number}> = ({at, x, y}) => {
  const frame = useCurrentFrame();
  const t = frame - at;
  if (t < -10 || t > 22) return null;
  const press = interpolate(t, [-10, 0, 8], [0, 1, 0], clamp);
  const ring = interpolate(t, [0, 20], [0, 1], clamp);
  return (
    <div style={{position: 'absolute', left: x, top: y, zIndex: 50, pointerEvents: 'none'}}>
      <div style={{position: 'absolute', width: 56, height: 56, marginLeft: -28, marginTop: -28, borderRadius: '50%', background: 'rgba(73,91,115,.35)', border: '3px solid rgba(255,255,255,.95)', transform: `scale(${0.6 + press * 0.4})`, opacity: press}} />
      <div style={{position: 'absolute', width: 56, height: 56, marginLeft: -28, marginTop: -28, borderRadius: '50%', border: '3px solid rgba(73,91,115,.6)', transform: `scale(${1 + ring * 1.5})`, opacity: 1 - ring}} />
    </div>
  );
};

/* An on-screen element that lifts straight out of the screen to the front. */
export const Lift: React.FC<{
  at: number;
  back?: number;
  w: number; // design width of children (px, as drawn on screen)
  src: {x: number; y: number}; // top-left on screen, video coords
  dst: {x: number; y: number; w: number}; // front position, video coords
  s0?: number; // scale of the screen it sits in
  shadow?: boolean;
  children: React.ReactNode;
}> = ({at, back, w, src, dst, s0 = 1, shadow = true, children}) => {
  const frame = useCurrentFrame();
  const {fps} = useVideoConfig();
  const p = liftP(frame, fps, at, back);
  if (p < 0.002) return null;
  const s = s0 + (dst.w / w - s0) * p;
  const x = src.x + (dst.x - src.x) * p;
  const y = src.y + (dst.y - src.y) * p;
  // zoom (not transform: scale) so text and 3D layers re-rasterise crisp at the final size
  return (
    <div style={{position: 'absolute', left: x, top: y, zIndex: 20}}>
      <div style={{width: w, zoom: s, borderRadius: 14, boxShadow: shadow ? `0 ${p * 30}px ${p * 70}px rgba(8,14,22,${p * 0.45})` : 'none'}}>{children}</div>
    </div>
  );
};
export const liftP = (frame: number, fps: number, at: number, back?: number) => Math.max(0, sp(frame, fps, at, 18) - (back ? sp(frame, fps, back, 20) : 0));
/* 1 while the element is out of the screen (use to reflow what stays behind) */
export const useOut = (at: number, back?: number) => {
  const frame = useCurrentFrame();
  return ease(frame, [at, at + 14], [0, 1]) - (back ? ease(frame, [back, back + 12], [0, 1]) : 0);
};
export const useHome = (at: number, back?: number) => {
  const frame = useCurrentFrame();
  const {fps} = useVideoConfig();
  return liftP(frame, fps, at, back) < 0.002;
};

export const Snd: React.FC<{at: number; s: string; v?: number}> = ({at, s, v = 0.5}) => (
  <Sequence from={at} layout="none">
    <Audio src={staticFile(`audio/${s}.wav`)} volume={v} />
  </Sequence>
);

/* Typing sounds: one tick every 2 characters */
export const TypeSnd: React.FC<{text: string; at: number; cps?: number}> = ({text, at, cps = 22}) => (
  <>
    {Array.from({length: Math.ceil(text.length / 2)}).map((_, i) => (
      <Snd key={i} at={at + Math.round((i * 2 * 30) / cps)} s="type" v={0.35} />
    ))}
  </>
);

/* Floating info chip beside a device, pops in, bobs, pops out. */
export const Float: React.FC<{at: number; until?: number; x: number; y: number; icon: string; label: string; value: string; color?: string}> = ({at, until = 99999, x, y, icon, label, value, color = C.navy}) => {
  const frame = useCurrentFrame();
  const {fps} = useVideoConfig();
  const p = sp(frame, fps, at, 12) - sp(frame, fps, until, 20);
  if (frame < at || p <= 0.01) return null;
  const bob = Math.sin((frame + x) / 20) * 8;
  return (
    <div style={{position: 'absolute', left: x, top: y + bob, zIndex: 30, opacity: Math.min(1, p * 1.5), transform: `scale(${0.6 + 0.4 * p})`, display: 'flex', alignItems: 'center', gap: 12, padding: '10px 18px 10px 10px', borderRadius: 18, background: '#fff', borderLeft: `5px solid ${color}`, boxShadow: `0 20px 50px rgba(8,14,22,.4), 0 0 0 1px ${color}22`, fontFamily: FONT}}>
      <span style={{width: 44, height: 44, borderRadius: 12, display: 'grid', placeItems: 'center', background: color}}>
        <Ico n={icon} s={22} c="#fff" w={2.4} />
      </span>
      <span>
        <span style={{display: 'block', fontSize: 13, fontWeight: 700, letterSpacing: 1.5, textTransform: 'uppercase', color}}>{label}</span>
        <span style={{display: 'block', fontSize: 21, fontWeight: 700, color: T.head, whiteSpace: 'nowrap'}}>{value}</span>
      </span>
    </div>
  );
};
