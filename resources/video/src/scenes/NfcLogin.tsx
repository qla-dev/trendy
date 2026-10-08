import React from 'react';
import {Ambient} from '../components/Kit';
import {AbsoluteFill, Easing, Img, interpolate, staticFile, useCurrentFrame, useVideoConfig} from 'remotion';

import {Title} from '../components/Stage';
import {Finger, Float, Ico, Lift, MobileBar, PH_CENTER, PH_DOWN, Phone, Snd, T, Type, TypeSnd, liftP, phoneToVideo, useHome, useOut, usePose} from '../components/UI';
import {C, FONT, clamp, ease, sp} from '../theme';

const TAP = 24;
const CARD_IN = 40;
const READ = 66;
const LINKED = 104;
const LIFT = 120;
const FLIP = 166;
const FLIP2 = 212;
const UID = 'C3 6E 1C 28';
const ACCENT = '#7367f0'; // --nfc-accent

// .nfc-card (design 380 wide, ratio 1.586)
const CW = 380;
const CH = CW / 1.586;
const face: React.CSSProperties = {
  position: 'absolute',
  inset: 0,
  borderRadius: '5.5% / 8.7%',
  backfaceVisibility: 'hidden',
  background: 'radial-gradient(140% 120% at 20% 0%, #ffffff 0%, #f4f4f2 55%, #e6e6e3 100%)',
  boxShadow: '0 20px 40px -18px rgba(0,0,0,.55), 0 0 0 1px rgba(0,0,0,.06)',
  overflow: 'hidden',
};

const NfcCard: React.FC<{flip: number; tilt?: number}> = ({flip, tilt = 0}) => {
  const frame = useCurrentFrame();
  const sheen = ((frame * 6) % 900) - 300;
  return (
    <div style={{width: CW, height: CH, perspective: 1200}}>
      <div style={{position: 'relative', width: '100%', height: '100%', transformStyle: 'preserve-3d', transform: `rotateY(${flip}deg) rotateX(${tilt}deg)`}}>
        <div style={{...face, display: 'grid', placeItems: 'center'}}>
          <Img src={staticFile('app/images/pwa/trendy-gear-logo.png')} style={{width: '58%'}} />
          <span style={{position: 'absolute', right: '6%', bottom: '7%', fontFamily: 'Consolas, monospace', fontSize: 11, letterSpacing: 2, color: '#a3a3a3'}}>{UID}</span>
          <div style={{position: 'absolute', inset: 0, background: 'linear-gradient(115deg, transparent 40%, rgba(255,255,255,.9) 50%, transparent 60%)', transform: `translateX(${sheen}px)`}} />
        </div>
        <div style={{...face, transform: 'rotateY(180deg)', display: 'flex', alignItems: 'center', gap: '6%', padding: '7%', fontFamily: FONT}}>
          <Img src={staticFile('app/images/pwa/trendy-gear-logo.png')} style={{width: '32%'}} />
          <div style={{display: 'grid', gap: 7}}>
            {[
              ['Korisnik', 'Admin', '#1b1b1b'],
              ['UID kartice', UID, '#e30613'],
              ['Povezana', '07.10.2026. 08:14', '#1b1b1b'],
            ].map(([l, v, c]) => (
              <div key={l}>
                <span style={{display: 'block', fontSize: 8, textTransform: 'uppercase', letterSpacing: 1.6, color: '#8a8a8a'}}>{l}</span>
                <span style={{display: 'block', fontSize: 14, fontWeight: 700, color: c, fontFamily: l === 'UID kartice' ? 'Consolas, monospace' : FONT, letterSpacing: l === 'UID kartice' ? 2 : 0}}>
                  {l === 'Povezana' ? <Type text={v} at={FLIP + 20} cps={30} caret={false} /> : v}
                </span>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
};

export const NfcLogin: React.FC = () => {
  const frame = useCurrentFrame();
  const {fps} = useVideoConfig();
  const linked = frame >= LINKED;
  const cardIn = sp(frame, fps, CARD_IN, 16);
  const cardAway = ease(frame, [READ + 6, READ + 20], [0, 1]);
  const bez = {...clamp, easing: Easing.bezier(0.34, 1.36, 0.5, 1)};
  const lp = liftP(frame, fps, LIFT);
  const flip = interpolate(frame, [FLIP, FLIP + 26], [0, 180], bez) + interpolate(frame, [FLIP2, FLIP2 + 26], [0, 180], bez);
  const spin = (1 - lp) * -360; // nfc-enter spin while it leaves the phone
  const float = Math.sin((frame - LIFT) / 16) * lp;
  const ok = frame >= READ;
  const pose = usePose([
    {f: 0, y: PH_CENTER, s: 1},
    {f: TAP, y: PH_CENTER - 50, s: 1.08},
    {f: LINKED, y: PH_CENTER - 50, s: 1.08},
    {f: LIFT - 4, y: PH_CENTER, s: 1},
    {f: LIFT + 14, y: PH_DOWN, s: 0.8},
  ]);
  const out = useOut(LIFT);
  const home = useHome(LIFT);
  const cardSrc = phoneToVideo(47, 300, pose);

  return (
    <AbsoluteFill style={{fontFamily: FONT}}>
      <Ambient kind="nfc" />
      <Title kicker="06 · Radnici" title="Prijava jednim dodirom" sub="Lična NFC kartica radnika." />
      <Phone pose={pose} dim={ease(frame, [LIFT, LIFT + 14], [0, 0.8])}>
        <MobileBar title="Moja NFC kartica" />
        {!linked ? (
          <div style={{position: 'absolute', inset: 0}}>
            {/* .nfc-scanner */}
            <div style={{position: 'absolute', left: 237 - 150, top: 230, width: 300, height: 300, display: 'grid', placeItems: 'center'}}>
              {frame > TAP &&
                [0, 1, 2].map((k) => {
                  const t = ((frame - TAP + k * 10) % 30) / 30;
                  return <div key={k} style={{position: 'absolute', inset: 0, borderRadius: '50%', border: `3px solid ${ok ? C.green : ACCENT}`, transform: `scale(${0.45 + t * 0.6})`, opacity: (1 - t) * 0.8}} />;
                })}
              <div style={{width: 150, height: 150, borderRadius: '50%', display: 'grid', placeItems: 'center', background: ok ? `linear-gradient(145deg, ${C.green}, #48da89)` : `linear-gradient(145deg, ${ACCENT}, #9e95f5)`, boxShadow: `0 18px 40px -12px ${ok ? 'rgba(40,199,111,.65)' : 'rgba(115,103,240,.65)'}`}}>
                <Ico n={ok ? 'check' : 'wifi'} s={64} c="#fff" w={2.6} style={{transform: ok ? undefined : 'rotate(90deg)'}} />
              </div>
            </div>
            <div style={{position: 'absolute', top: 560, left: 20, right: 20, textAlign: 'center'}}>
              <div style={{fontSize: 26, fontWeight: 700, color: T.head}}>{frame < TAP ? 'Povežite svoju NFC karticu' : ok ? 'Kartica očitana' : 'Prislonite karticu'}</div>
              <div style={{fontSize: 15, color: T.text, marginTop: 8, minHeight: 40}}>
                {frame < TAP ? 'Dodirnite krug da pokrenete skener.' : <Type text="Skener je aktivan. Prislonite karticu na poleđinu uređaja." at={TAP + 4} cps={50} caret={false} />}
              </div>
              {/* .nfc-uid-live */}
              <div style={{marginTop: 18, fontFamily: 'Consolas, monospace', fontSize: 32, fontWeight: 700, letterSpacing: 6, color: C.green, minHeight: 40}}>{ok && <Type text={UID} at={READ} cps={20} />}</div>
            </div>
            {/* the physical card touching the phone */}
            {frame >= CARD_IN && cardAway < 1 && (
              <div style={{position: 'absolute', left: 47, top: 760 + (1 - cardIn) * 400 + cardAway * 400, opacity: 1 - cardAway, transform: `rotate(${(1 - cardIn) * -14}deg)`}}>
                <NfcCard flip={0} />
              </div>
            )}
          </div>
        ) : (
          <div style={{position: 'absolute', inset: 0, textAlign: 'center'}}>
            <div style={{position: 'absolute', top: 230, left: 0, right: 0}}>
              <span style={{display: 'inline-flex', alignItems: 'center', gap: 8, padding: '7px 16px', borderRadius: 99, background: 'rgba(40,199,111,.12)', color: C.green, fontWeight: 600, fontSize: 16}}>
                <span style={{width: 9, height: 9, borderRadius: 5, background: C.green}} /> Kartica je aktivna
              </span>
            </div>
            {home && (
              <div style={{position: 'absolute', left: 47, top: 300}}>
                <NfcCard flip={0} />
              </div>
            )}
            {/* hint + actions slide up when the card leaves */}
            <div style={{position: 'absolute', left: 30, right: 30, top: 580 - 270 * out}}>
              <div style={{fontSize: 14, color: T.muted}}>Dodirnite karticu da je okrenete</div>
              <div style={{display: 'flex', gap: 12, justifyContent: 'center', marginTop: 18}}>
                <span style={{padding: '12px 18px', borderRadius: 8, border: `1px solid ${C.navy}`, color: C.navy, fontSize: 15, fontWeight: 600}}>Zamijeni karticu</span>
                <span style={{padding: '12px 18px', borderRadius: 8, border: '1px solid #ea5455', color: '#ea5455', fontSize: 15, fontWeight: 600}}>Ukloni</span>
              </div>
              <div style={{marginTop: 26, padding: '16px 18px', borderRadius: 12, background: '#fff', boxShadow: '0 4px 24px rgba(34,41,47,.08)', textAlign: 'left', fontSize: 14, color: T.text}}>
                <div style={{fontWeight: 700, color: T.head}}>Prijava na mašinu</div>
                <div style={{marginTop: 4}}>Prislonite karticu na terminal i nalog se otvara automatski.</div>
              </div>
            </div>
          </div>
        )}
        <Finger at={TAP} x={237} y={380} />
      </Phone>

      {linked && (
        <Lift at={LIFT} w={CW} s0={pose.s} shadow={false} src={cardSrc} dst={{x: 110, y: 420 + float * 10, w: 860}}>
          <NfcCard flip={flip + spin + float * 14} tilt={float * 6 + (1 - lp) * 25} />
        </Lift>
      )}

      <Float at={TAP + 10} until={LIFT - 6} x={20} y={760} icon="wifi" label="Skener" value="Web NFC" color="#7367f0" />
      <Float at={READ + 4} until={LIFT - 6} x={826} y={980} icon="card" label="UID" value={UID} color={C.green} />
      <Float at={LINKED} until={LIFT - 6} x={30} y={1240} icon="user" label="Korisnik" value="Admin" color="#00a8c2" />
      <Snd at={TAP + 10} s="pop" v={0.35} />
      <Snd at={READ + 4} s="pop" v={0.35} />
      <Snd at={TAP} s="click" v={0.5} />
      <TypeSnd text="Skener je aktivan." at={TAP + 4} cps={50} />
      <Snd at={CARD_IN} s="whoosh" v={0.4} />
      <Snd at={READ} s="beep" v={0.6} />
      <TypeSnd text={UID} at={READ} cps={20} />
      <Snd at={LINKED} s="success" v={0.55} />
      <Snd at={LIFT} s="whoosh" v={0.45} />
      <Snd at={FLIP} s="whoosh" v={0.5} />
      <Snd at={FLIP2} s="whoosh" v={0.5} />
      <Snd at={4} s="whoosh" v={0.4} />
    </AbsoluteFill>
  );
};
