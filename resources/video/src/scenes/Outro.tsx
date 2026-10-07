import React from 'react';
import {AbsoluteFill, useCurrentFrame, useVideoConfig} from 'remotion';
import {Logo, PoweredBy, Sfx} from '../components/Kit';
import {C, FONT, rise, sp} from '../theme';

const PILLS = ['AI narudžbe', 'Radni nalozi', 'Plan proizvodnje', 'Skladište', 'Operacije', 'NFC prijava'];

export const Outro: React.FC = () => {
  const frame = useCurrentFrame();
  const {fps} = useVideoConfig();
  const logo = sp(frame, fps, 4, 12);

  return (
    <AbsoluteFill style={{fontFamily: FONT}}>
      <AbsoluteFill style={{alignItems: 'center', justifyContent: 'center', flexDirection: 'column', padding: '0 70px'}}>
        <Logo size={300} style={{transform: `scale(${logo})`}} />
        <div style={{fontSize: 140, fontWeight: 800, color: C.white, letterSpacing: -4, marginTop: 60, ...rise(sp(frame, fps, 12), 40)}}>eNalog.app</div>
        <div style={{fontSize: 54, fontWeight: 700, color: C.white, textAlign: 'center', lineHeight: 1.25, marginTop: 20, ...rise(sp(frame, fps, 20), 30)}}>
          Digitalna proizvodnja.
          <br />
          <span style={{color: C.ice}}>Puna kontrola.</span>
        </div>
        <div style={{display: 'flex', flexWrap: 'wrap', justifyContent: 'center', gap: 16, marginTop: 64, maxWidth: 900}}>
          {PILLS.map((p, i) => (
            <span key={p} style={{padding: '14px 26px', borderRadius: 40, background: 'rgba(255,255,255,.1)', border: '1.5px solid rgba(255,255,255,.25)', color: C.white, fontSize: 30, fontWeight: 600, ...rise(sp(frame, fps, 34 + i * 4), 24)}}>
              {p}
            </span>
          ))}
        </div>
      </AbsoluteFill>
      <PoweredBy at={56} bottom={110} />
      <Sfx at={4} src="impact" volume={0.7} />
    </AbsoluteFill>
  );
};
