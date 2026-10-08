import React from 'react';
import {AbsoluteFill, useCurrentFrame, useVideoConfig} from 'remotion';
import {Logo, PoweredBy, Sfx} from '../components/Kit';
import {C, FONT, ease, rise, sp} from '../theme';

export const Intro: React.FC = () => {
  const frame = useCurrentFrame();
  const {fps} = useVideoConfig();
  const logo = sp(frame, fps, 4, 12);
  const name = 'eNalog.app';
  const typed = Math.round(ease(frame, [26, 50], [0, name.length]));
  const ring = ease(frame, [4, 40], [0, 1]);

  return (
    <AbsoluteFill style={{fontFamily: FONT}}>
      <AbsoluteFill style={{alignItems: 'center', justifyContent: 'center', flexDirection: 'column'}}>
        <div style={{position: 'relative', display: 'grid', placeItems: 'center'}}>
          <div style={{position: 'absolute', width: 440, height: 440, borderRadius: '50%', border: `3px solid rgba(195,206,220,${0.5 * (1 - ring)})`, transform: `scale(${0.8 + ring * 0.6})`}} />
          <Logo size={360} style={{transform: `scale(${logo})`, opacity: Math.min(1, logo * 2)}} />
        </div>
        <div style={{fontSize: 128, fontWeight: 800, color: C.white, marginTop: 70, letterSpacing: -3, minHeight: 156}}>
          {name.slice(0, typed)}
          <span style={{opacity: typed < name.length && frame % 14 < 7 ? 1 : 0, color: C.ice}}>|</span>
        </div>
        <div style={{fontSize: 42, fontWeight: 600, color: C.ice, marginTop: 10, ...rise(sp(frame, fps, 54), 26)}}>Trendy CNC ide digitalno.</div>
      </AbsoluteFill>
      <PoweredBy at={60} />
      <Sfx at={4} src="impact" volume={0.8} />
      {Array.from({length: name.length}).map((_, i) => (
        <Sfx key={i} at={26 + Math.round((i * 24) / name.length)} src="click" volume={0.22} />
      ))}
    </AbsoluteFill>
  );
};
