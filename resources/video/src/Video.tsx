import React from 'react';
import {AbsoluteFill, Audio, interpolate, Sequence, Series, staticFile, useCurrentFrame} from 'remotion';
import {Backdrop} from './components/Kit';
import {AiOrders} from './scenes/AiOrders';
import {Hook} from './scenes/Hook';
import {Intro} from './scenes/Intro';
import {NfcLogin} from './scenes/NfcLogin';
import {Operations} from './scenes/Operations';
import {Outro} from './scenes/Outro';
import {Planning} from './scenes/Planning';
import {ShopFloor} from './scenes/ShopFloor';
import {Warehouse} from './scenes/Warehouse';

export const SCENES = [
  {C: Intro, d: 100},
  {C: Hook, d: 135},
  {C: AiOrders, d: 300},
  {C: Planning, d: 240},
  {C: ShopFloor, d: 280},
  {C: Warehouse, d: 260},
  {C: Operations, d: 225},
  {C: NfcLogin, d: 260},
  {C: Outro, d: 140},
];

export const TOTAL = SCENES.reduce((s, x) => s + x.d, 0);
const starts = SCENES.map((_, i) => SCENES.slice(0, i).reduce((s, x) => s + x.d, 0));

const OUT = 14;
/* Each scene leaves on its own (flies up + fades) before the next one flies in: no overlap. */
export const Exit: React.FC<{d: number; children: React.ReactNode}> = ({d, children}) => {
  const frame = useCurrentFrame();
  const e = interpolate(frame, [d - OUT, d], [0, 1], {extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: (t) => t * t});
  return <AbsoluteFill style={{opacity: 1 - e, transform: `translateY(${-260 * e}px) scale(${1 - 0.06 * e})`, filter: e > 0 ? `blur(${e * 6}px)` : undefined}}>{children}</AbsoluteFill>;
};

export const Framed: React.FC<{i: number}> = ({i}) => {
  const S = SCENES[i];
  return (
    <AbsoluteFill>
      <Backdrop />
      <Exit d={S.d}>
        <S.C />
      </Exit>
    </AbsoluteFill>
  );
};

export const Video: React.FC = () => (
  <AbsoluteFill style={{background: '#222c39'}}>
    <Backdrop />
    <Series>
      {SCENES.map((s, i) => (
        <Series.Sequence key={i} durationInFrames={s.d}>
          <Exit d={s.d}>
            <s.C />
          </Exit>
        </Series.Sequence>
      ))}
    </Series>
    <Audio
      src={staticFile('audio/music.wav')}
      volume={(f) => interpolate(f, [0, 15, TOTAL - 60, TOTAL], [0, 0.4, 0.4, 0], {extrapolateLeft: 'clamp', extrapolateRight: 'clamp'})}
    />
    {starts.slice(1).map((st) => (
      <Sequence key={st} from={st - OUT} layout="none">
        <Audio src={staticFile('audio/whoosh.wav')} volume={0.3} />
      </Sequence>
    ))}
  </AbsoluteFill>
);
