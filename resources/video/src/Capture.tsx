import React from 'react';
import {AbsoluteFill, IFrame, staticFile} from 'remotion';

export type CaptureProps = {src: string; w: number; h: number};

// Renders one static app screen so `remotion still` can snapshot it at high DPI.
export const Capture: React.FC<CaptureProps> = ({src}) => (
  <AbsoluteFill style={{background: '#f8f8f8'}}>
    <IFrame src={staticFile(src)} style={{width: '100%', height: '100%', border: 0}} />
  </AbsoluteFill>
);
