import React from 'react';
import {Composition} from 'remotion';
import {Capture, CaptureProps} from './Capture';
import {Framed, SCENES, TOTAL, Video} from './Video';

export const RemotionRoot: React.FC = () => (
  <>
    <Composition id="eNalogPromo" component={Video} durationInFrames={TOTAL} fps={30} width={1080} height={1920} />
    {SCENES.map((s, i) => (
      <Composition key={i} id={`Scene${i}`} component={Framed} defaultProps={{i}} durationInFrames={s.d} fps={30} width={1080} height={1920} />
    ))}
    <Composition
      id="Screen"
      component={Capture}
      durationInFrames={1}
      fps={30}
      width={1440}
      height={900}
      defaultProps={{src: 'app/screens/d-plan.html', w: 1440, h: 900} as CaptureProps}
      calculateMetadata={({props}) => ({width: props.w, height: props.h})}
    />
  </>
);
