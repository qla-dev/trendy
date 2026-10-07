import {loadFont} from '@remotion/google-fonts/Montserrat';
import {Easing, interpolate, spring} from 'remotion';

// eNalog.app palette: primary #495B73 (bootstrap-extended/_variables.scss) on white / #f8f8f8.
export const C = {
  navy: '#495B73',
  navyDark: '#344255',
  navyDeep: '#222c39',
  ice: '#c3cedc',
  mist: '#e9edf2',
  bg: '#f8f8f8',
  white: '#ffffff',
  green: '#28c76f',
  heading: '#5e5873',
};

export const {fontFamily: FONT} = loadFont('normal', {
  weights: ['400', '500', '600', '700', '800'],
  subsets: ['latin', 'latin-ext'],
});

export const clamp = {extrapolateLeft: 'clamp', extrapolateRight: 'clamp'} as const;

export const ease = (frame: number, input: number[], output: number[], easing = Easing.bezier(0.45, 0, 0.2, 1)) =>
  interpolate(frame, input, output, {...clamp, easing});

export const sp = (frame: number, fps: number, delay = 0, damping = 200) => spring({frame: frame - delay, fps, config: {damping}});

export const rise = (p: number, dist = 40) => ({
  opacity: Math.min(1, p),
  transform: `translateY(${(1 - p) * dist}px)`,
});

// Captured screenshot sizes (scripts/capture.sh)
export const DESK = {w: 2880, h: 1800};
export const AI = {w: 2880, h: 3450};
export const PHONE = {w: 1170, h: 2532};
