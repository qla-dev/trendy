// Splits one long voice recording (out/voice-src/amila.wav) into the parts in src/voice.json,
// by aligning silence gaps with the text length of each part.
const fs = require('fs');
const path = require('path');
const ROOT = path.join(__dirname, '..');
const SRC = process.argv[2] || path.join(ROOT, 'out/voice-src/amila.wav');
const f = fs.readFileSync(SRC);
const di = f.indexOf('data');
const pcm = f.subarray(di + 8);
const R = 24000;
const W = 240;
const n = pcm.length / 2;
const env = [];
for (let i = 0; i + 1 < n; i += W) {
  let m = 0;
  for (let k = i; k < Math.min(n, i + W); k++) m = Math.max(m, Math.abs(pcm.readInt16LE(k * 2)));
  env.push(m);
}
const TH = 700;
const gaps = [];
let st = null;
env.forEach((m, i) => {
  if (m < TH) {
    if (st === null) st = i;
  } else if (st !== null) {
    const d = ((i - st) * W) / R;
    if (d >= 0.15 && st > 0) gaps.push({a: (st * W) / R, b: (i * W) / R, d});
    st = null;
  }
});
const first = (env.findIndex((m) => m >= TH) * W) / R;
let lastIdx = env.length - 1;
while (env[lastIdx] < TH) lastIdx--;
const last = ((lastIdx + 1) * W) / R;

const LINES = JSON.parse(fs.readFileSync(path.join(ROOT, 'src/voice.json'), 'utf8'));
const chars = LINES.map((l) => l.text.length);
const total = chars.reduce((a, b) => a + b, 0);
const speech = last - first - gaps.reduce((s, g) => s + g.d, 0) * 0.6;
let cum = 0;
const expect = chars.slice(0, -1).map((c) => (cum += c) / total);
// DP: choose K = parts-1 gaps (in order) minimising distance to expected boundaries, preferring long gaps.
const K = expect.length;
const G = gaps.length;
const pos = (g) => (g.a + g.b) / 2;
const cost = (k, j) => Math.abs((pos(gaps[j]) - first) / (last - first) - expect[k]) * 10 - gaps[j].d * 2.2;
const dp = Array.from({length: K}, () => new Array(G).fill(Infinity));
const from = Array.from({length: K}, () => new Array(G).fill(-1));
for (let j = 0; j < G; j++) dp[0][j] = cost(0, j);
for (let k = 1; k < K; k++)
  for (let j = k; j < G; j++)
    for (let p = k - 1; p < j; p++)
      if (dp[k - 1][p] + cost(k, j) < dp[k][j]) {
        dp[k][j] = dp[k - 1][p] + cost(k, j);
        from[k][j] = p;
      }
let j = dp[K - 1].indexOf(Math.min(...dp[K - 1]));
const pick = [];
for (let k = K - 1; k >= 0; k--) {
  pick.unshift(j);
  j = from[k][j];
}
const cuts = [first, ...pick.flatMap((g) => [gaps[g].a, gaps[g].b]), last];
// Manual corrections where gap detection alone is ambiguous (checked against finer pause analysis).
const OVERRIDE = {
  'teren-1': [28.56, 30.41],
  'teren-2': [30.56, 31.62],
  'teren-3': [32.05, 34.77],
  'skladiste-1': [35.4, 37.13],
  'skladiste-2': [37.5, 39.29],
  'skladiste-3': [39.7, 43.86],
};
LINES.forEach((l, i) => {
  if (OVERRIDE[l.key]) [cuts[i * 2], cuts[i * 2 + 1]] = OVERRIDE[l.key];
});
const out = path.join(ROOT, 'public/voice');
fs.mkdirSync(out, {recursive: true});
for (const x of fs.readdirSync(out)) fs.unlinkSync(path.join(out, x));
const dur = {};
const pad = 0.06;
LINES.forEach((l, i) => {
  const a = Math.max(0, cuts[i * 2] - pad);
  const b = Math.min(n / R, cuts[i * 2 + 1] + pad);
  const seg = pcm.subarray(Math.floor(a * R) * 2, Math.floor(b * R) * 2);
  const h = Buffer.alloc(44);
  h.write('RIFF', 0);
  h.writeUInt32LE(36 + seg.length, 4);
  h.write('WAVEfmt ', 8);
  h.writeUInt32LE(16, 16);
  h.writeUInt16LE(1, 20);
  h.writeUInt16LE(1, 22);
  h.writeUInt32LE(R, 24);
  h.writeUInt32LE(R * 2, 28);
  h.writeUInt16LE(2, 32);
  h.writeUInt16LE(16, 34);
  h.write('data', 36);
  h.writeUInt32LE(seg.length, 40);
  fs.writeFileSync(path.join(out, `${l.key}.wav`), Buffer.concat([h, seg]));
  dur[l.key] = +(seg.length / 2 / R).toFixed(2);
  console.log(`${l.key.padEnd(12)} ${a.toFixed(2).padStart(6)}–${b.toFixed(2).padEnd(6)} ${dur[l.key].toFixed(2)}s  ${(l.text.length / dur[l.key]).toFixed(1)} zn/s  ${l.text}`);
});
fs.writeFileSync(path.join(ROOT, 'src/voice-durations.json'), JSON.stringify(dur, null, 2) + '\n');
