// Synthesises every sound used in the video (no samples needed):
// UI sounds mirror the Web Audio cues on the NFC card page, plus a short music bed.
import {mkdirSync, writeFileSync} from 'node:fs';

const SR = 44100;
const OUT = new URL('../public/audio/', import.meta.url);
mkdirSync(OUT, {recursive: true});

let seed = 1337;
const rnd = () => ((seed = (seed * 1664525 + 1013904223) >>> 0) / 4294967296) * 2 - 1;
const TAU = Math.PI * 2;
const osc = {
  sine: (p) => Math.sin(p),
  square: (p) => (Math.sin(p) >= 0 ? 1 : -1),
  triangle: (p) => (2 / Math.PI) * Math.asin(Math.sin(p)),
  saw: (p) => 2 * ((p / TAU) % 1) - 1,
};

const buffer = (sec) => new Float32Array(Math.ceil(sec * SR));

function wav(name, data, peakTarget = 0.9) {
  let peak = 0;
  for (const v of data) peak = Math.max(peak, Math.abs(v));
  const g = peak > 0 ? peakTarget / peak : 1;
  const b = Buffer.alloc(44 + data.length * 2);
  b.write('RIFF', 0);
  b.writeUInt32LE(36 + data.length * 2, 4);
  b.write('WAVEfmt ', 8);
  b.writeUInt32LE(16, 16);
  b.writeUInt16LE(1, 20);
  b.writeUInt16LE(1, 22);
  b.writeUInt32LE(SR, 24);
  b.writeUInt32LE(SR * 2, 28);
  b.writeUInt16LE(2, 32);
  b.writeUInt16LE(16, 34);
  b.write('data', 36);
  b.writeUInt32LE(data.length * 2, 40);
  for (let i = 0; i < data.length; i++) {
    b.writeInt16LE(Math.round(Math.max(-1, Math.min(1, data[i] * g)) * 32767), 44 + i * 2);
  }
  writeFileSync(new URL(name, OUT), b);
  console.log('wrote', name, (data.length / SR).toFixed(2) + 's');
}

// Adds a note with a short attack and exponential decay.
function tone(buf, start, dur, freq, type = 'sine', gain = 0.3, decay = 6, attack = 0.004) {
  const s0 = Math.floor(start * SR);
  const n = Math.floor(dur * SR);
  for (let i = 0; i < n && s0 + i < buf.length; i++) {
    const t = i / SR;
    const env = Math.min(1, t / attack) * Math.exp(-t * decay) * Math.min(1, (dur - t) / 0.01);
    buf[s0 + i] += osc[type](TAU * freq * t) * env * gain;
  }
}

function bandpass(input, freqAt, q = 1.2) {
  const out = new Float32Array(input.length);
  let x1 = 0, x2 = 0, y1 = 0, y2 = 0;
  for (let i = 0; i < input.length; i++) {
    const w0 = (TAU * freqAt(i / SR)) / SR;
    const alpha = Math.sin(w0) / (2 * q);
    const a0 = 1 + alpha;
    const y = (alpha * input[i] - alpha * x2 + 2 * Math.cos(w0) * y1 - (1 - alpha) * y2) / a0;
    x2 = x1; x1 = input[i]; y2 = y1; y1 = y;
    out[i] = y;
  }
  return out;
}

function lowpass(input, fc) {
  const a = 1 - Math.exp((-TAU * fc) / SR);
  let y = 0;
  return input.map((x) => (y += a * (x - y)));
}

// --- UI sounds -------------------------------------------------------------
{
  const b = buffer(0.2);
  tone(b, 0, 0.07, 1320, 'square', 0.25, 10);
  tone(b, 0.08, 0.09, 1760, 'square', 0.25, 10);
  wav('beep.wav', b, 0.5);
}
{
  const b = buffer(0.95);
  [1046.5, 1318.5, 1568].forEach((f, i) => tone(b, i * 0.09, 0.22, f, 'triangle', 0.35, 9));
  tone(b, 0.3, 0.6, 2093, 'sine', 0.3, 5);
  wav('success.wav', b, 0.6);
}
{
  const b = buffer(0.06);
  tone(b, 0, 0.05, 2400, 'sine', 0.4, 70);
  for (let i = 0; i < 0.01 * SR; i++) b[i] += rnd() * 0.3 * (1 - i / (0.01 * SR));
  wav('click.wav', b, 0.45);
}
{
  const b = buffer(0.14);
  for (let i = 0, ph = 0; i < b.length; i++) {
    const t = i / SR;
    ph += (TAU * (900 * Math.exp(-t * 18) + 220)) / SR;
    b[i] = Math.sin(ph) * Math.exp(-t * 28);
  }
  wav('pop.wav', b, 0.5);
}
{
  const dur = 0.5;
  const noise = buffer(dur).map(() => rnd());
  const sweep = (t) => {
    const k = t / dur;
    return k < 0.45 ? 600 + (3200 - 600) * (k / 0.45) : 3200 - (3200 - 900) * ((k - 0.45) / 0.55);
  };
  const b = bandpass(noise, sweep, 1.4).map((v, i) => v * Math.sin((Math.PI * i) / (dur * SR)) ** 1.5);
  wav('whoosh.wav', b, 0.55);
}
{
  const b = buffer(0.12);
  tone(b, 0, 0.12, 220, 'saw', 0.25, 12);
  tone(b, 0.06, 0.06, 165, 'saw', 0.25, 12);
  wav('error.wav', b, 0.4);
}
{
  // Low impact for the logo reveal.
  const b = buffer(1.6);
  for (let i = 0, ph = 0; i < b.length; i++) {
    const t = i / SR;
    ph += (TAU * (90 * Math.exp(-t * 6) + 38)) / SR;
    b[i] = Math.sin(ph) * Math.exp(-t * 3.2) * 0.9 + rnd() * Math.exp(-t * 30) * 0.25;
  }
  wav('impact.wav', b, 0.8);
}

// --- Music bed: 120 BPM, Am – F – C – G -----------------------------------
{
  const BPM = 120;
  const beat = 60 / BPM;
  const bar = beat * 4;
  const LEN = 50;
  const chords = [
    {root: 110, pad: [220, 261.63, 329.63]},
    {root: 87.31, pad: [174.61, 220, 261.63]},
    {root: 130.81, pad: [196, 261.63, 329.63]},
    {root: 98, pad: [196, 246.94, 293.66]},
  ];
  const pad = buffer(LEN);
  const drums = buffer(LEN);
  const bass = buffer(LEN);
  const bars = Math.floor(LEN / bar);

  for (let k = 0; k < bars; k++) {
    const c = chords[k % 4];
    const t0 = k * bar;
    for (const f of c.pad) {
      for (const det of [0.996, 1.004]) {
        const s0 = Math.floor(t0 * SR);
        for (let i = 0; i < bar * SR; i++) {
          const t = i / SR;
          const env = Math.min(1, t / 0.35) * Math.min(1, (bar - t) / 0.3);
          pad[s0 + i] += osc.saw(TAU * f * det * t) * env * 0.05;
        }
      }
    }
    const full = k >= 2 && k < bars - 1; // first two bars are an intro, last bar rings out
    for (let b8 = 0; b8 < 8; b8++) {
      const tb = t0 + b8 * (beat / 2);
      if (k >= 1) tone(bass, tb, beat / 2, c.root, 'sine', 0.32, 7);
      if (k >= 1) tone(bass, tb, beat / 2, c.root * 2, 'triangle', 0.06, 9);
      if (!full) continue;
      if (b8 % 2 === 0) {
        const s0 = Math.floor(tb * SR);
        for (let i = 0, ph = 0; i < 0.35 * SR; i++) {
          const t = i / SR;
          ph += (TAU * (120 * Math.exp(-t * 25) + 45)) / SR;
          drums[s0 + i] += Math.sin(ph) * Math.exp(-t * 8) * 0.8;
        }
      } else {
        const s0 = Math.floor(tb * SR);
        let lp = 0;
        for (let i = 0; i < 0.05 * SR; i++) {
          const n = rnd();
          lp += 0.3 * (n - lp);
          drums[s0 + i] += (n - lp) * Math.exp((-i / SR) * 60) * 0.14;
        }
      }
      if (b8 === 2 || b8 === 6) {
        const s0 = Math.floor(tb * SR);
        const noise = buffer(0.2).map(() => rnd());
        const clap = bandpass(noise, () => 1500, 0.8);
        for (let i = 0; i < clap.length; i++) drums[s0 + i] += clap[i] * Math.exp((-i / SR) * 18) * 0.5;
      }
    }
  }
  const padLp = lowpass(pad, 1400);
  const mix = new Float32Array(LEN * SR);
  for (let i = 0; i < mix.length; i++) mix[i] = padLp[i] + drums[i] + bass[i];
  wav('music.wav', mix, 0.85);
}

// Soft key tick for typing effects.
{
  const b = buffer(0.035);
  for (let i = 0; i < b.length; i++) {
    const t = i / SR;
    b[i] = rnd() * Math.exp(-t * 260) * 0.6 + Math.sin(TAU * 3200 * t) * Math.exp(-t * 180) * 0.4;
  }
  wav('type.wav', b, 0.35);
}
