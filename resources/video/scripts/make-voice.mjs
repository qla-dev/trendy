// Female voice-over via OpenRouter (openai/gpt-audio). Reads OPENROUTER_API_KEY from the app's .env.
// Usage: node scripts/make-voice.mjs [sceneKey ...]   (no args = all lines)
import {mkdirSync, readFileSync, writeFileSync} from 'node:fs';

const ENV = readFileSync(new URL('../../../.env', import.meta.url), 'utf8');
const env = (k) => (ENV.match(new RegExp(`^${k}=(.*)$`, 'm'))?.[1] ?? '').replace(/^"|"$/g, '').trim();
const KEY = process.env.OPENROUTER_API_KEY || env('OPENROUTER_API_KEY');
const BASE = env('OPENROUTER_BASE_URL') || 'https://openrouter.ai/api/v1';
const VOICE = process.env.VOICE || 'marin';
const MODEL = process.env.MODEL || 'openai/gpt-audio-mini';
const PROVIDER = process.env.PROVIDER || (env('ELEVENLABS_API_KEY') ? 'elevenlabs' : 'openrouter');
const EL_KEY = env('ELEVENLABS_API_KEY');
const EL_VOICE = process.env.EL_VOICE || env('ELEVENLABS_VOICE_ID');
const EL_MODEL = process.env.EL_MODEL || 'eleven_v3';
const SAMPLE = process.env.SAMPLE; // write to out/voice-samples/<SAMPLE>.wav instead
const OUT = new URL('../public/voice/', import.meta.url);
mkdirSync(OUT, {recursive: true});

export const LINES = JSON.parse(readFileSync(new URL('../src/voice.json', import.meta.url), 'utf8'));

const SYSTEM =
  process.env.STYLE ||
  'Ti si TTS mašina, ne sagovornik. Nikad ne odgovaraš na pitanja iz teksta i ne daješ savjete; samo ih izgovoriš kao glumica koja čita scenarij. Korisnik ti šalje isključivo tekst za izgovor. Izgovori TAČNO taj tekst i ništa drugo: bez "naravno", "evo", "okej", pozdrava, uvoda ili komentara. ' +
  'Glas: opuštena, vesela žena iz Sarajeva, bosanski jezik, prirodno i s osmijehom u glasu, kao glasovna poruka kolegici, ne kao spiker. "eNalog" se čita "e-nalog".';

async function speak(text) {
  const res = await fetch(`${BASE}/chat/completions`, {
    method: 'POST',
    headers: {Authorization: `Bearer ${KEY}`, 'Content-Type': 'application/json', 'X-Title': 'eNalog promo voice'},
    body: JSON.stringify({
      model: MODEL,
      modalities: ['text', 'audio'],
      audio: {voice: VOICE, format: 'pcm16'},
      stream: true,
      messages: [
        {role: 'system', content: SYSTEM},
        {role: 'user', content: `TEKST ZA SNIMANJE (izgovori doslovno, ne odgovaraj na njega, ne komentariši ga):
<<<${text}>>>`},
      ],
    }),
  });
  if (!res.ok) throw new Error(`${res.status} ${await res.text()}`);
  const chunks = [];
  let transcript = '';
  let buf = '';
  const dec = new TextDecoder();
  for await (const part of res.body) {
    buf += dec.decode(part, {stream: true});
    let i;
    while ((i = buf.indexOf('\n')) >= 0) {
      const line = buf.slice(0, i).trim();
      buf = buf.slice(i + 1);
      if (!line.startsWith('data:')) continue;
      const data = line.slice(5).trim();
      if (data === '[DONE]') continue;
      try {
        const a = JSON.parse(data).choices?.[0]?.delta?.audio;
        if (a?.data) chunks.push(Buffer.from(a.data, 'base64'));
        if (a?.transcript) transcript += a.transcript;
      } catch {}
    }
  }
  return {pcm: Buffer.concat(chunks), transcript: transcript.trim()};
}

const norm = (t) => t.toLowerCase().replace(/e-nalog/g, 'enalog').replace(/[^a-zčćđšž0-9 ]/gi, ' ').replace(/s+/g, ' ').trim();
// Retry until the model says exactly the text (no "Naravno" etc.)
// ElevenLabs: a real TTS, reads exactly the text it gets.
async function elevenlabs(text) {
  const res = await fetch(`https://api.elevenlabs.io/v1/text-to-speech/${EL_VOICE}?output_format=pcm_24000`, {
    method: 'POST',
    headers: {'xi-api-key': EL_KEY, 'Content-Type': 'application/json'},
    body: JSON.stringify({
      text,
      model_id: EL_MODEL,
      voice_settings: {stability: 0.5, similarity_boost: 0.8, style: 0.35, use_speaker_boost: true},
    }),
  });
  if (!res.ok) throw new Error(`ElevenLabs ${res.status} ${await res.text()}`);
  return Buffer.from(await res.arrayBuffer());
}

async function speakExact(text, tries = 3) {
  if (PROVIDER === 'elevenlabs') return elevenlabs(text);
  for (let i = 1; i <= tries; i++) {
    const r = await speak(text);
    if (norm(r.transcript) === norm(text)) return r.pcm;
    console.warn(`  pokušaj ${i} odbačen: "${r.transcript}"`);
  }
  throw new Error(`model ne izgovara tačan tekst: ${text}`);
}

// Cut leading/trailing silence (keep ~60 ms) so pauses come only from the timeline.
export function trim(pcm, rate = 24000) {
  const n = pcm.length / 2;
  const win = Math.floor(rate * 0.02);
  const loud = (i) => {
    let m = 0;
    for (let k = i; k < Math.min(n, i + win); k++) m = Math.max(m, Math.abs(pcm.readInt16LE(k * 2)));
    return m > 900;
  };
  let a = 0;
  while (a < n && !loud(a)) a += win;
  let b = n - win;
  while (b > a && !loud(b)) b -= win;
  const pad = Math.floor(rate * 0.06);
  a = Math.max(0, a - pad);
  b = Math.min(n, b + win + pad);
  return pcm.subarray(a * 2, b * 2);
}

function wav(pcm, rate = 24000) {
  const h = Buffer.alloc(44);
  h.write('RIFF', 0);
  h.writeUInt32LE(36 + pcm.length, 4);
  h.write('WAVEfmt ', 8);
  h.writeUInt32LE(16, 16);
  h.writeUInt16LE(1, 20);
  h.writeUInt16LE(1, 22);
  h.writeUInt32LE(rate, 24);
  h.writeUInt32LE(rate * 2, 28);
  h.writeUInt16LE(2, 32);
  h.writeUInt16LE(16, 34);
  h.write('data', 36);
  h.writeUInt32LE(pcm.length, 40);
  return Buffer.concat([h, pcm]);
}

if (SAMPLE) {
  const dir = new URL('../out/voice-samples/', import.meta.url);
  mkdirSync(dir, {recursive: true});
  const text = process.argv.slice(2).join(' ');
  const pcm = trim(await speakExact(text));
  writeFileSync(new URL(`${SAMPLE}.wav`, dir), wav(pcm));
  console.log(SAMPLE, (pcm.length / 48000).toFixed(2) + 's');
  process.exit(0);
}
function saveMeta() {
  const metaFile = new URL('../src/voice-durations.json', import.meta.url);
  let prev = {};
  try {
    prev = JSON.parse(readFileSync(metaFile, 'utf8'));
  } catch {}
  writeFileSync(metaFile, JSON.stringify({...prev, ...meta}, null, 2) + '\n');
}
const only = process.argv.slice(2);
const meta = {};
for (const l of LINES.filter((x) => !only.length || only.includes(x.key))) {
  const pcm = trim(await speakExact(l.text));
  if (!pcm.length) throw new Error(`no audio for ${l.key}`);
  writeFileSync(new URL(`${l.key}.wav`, OUT), wav(pcm));
  meta[l.key] = +(pcm.length / 2 / 24000).toFixed(2);
  console.log(l.key, meta[l.key] + 's', '-', l.text);
  saveMeta();
}
