/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React, { useState } from 'react';
import {
  AlarmClock,
  ArrowRight,
  BarChart3,
  BrainCircuit,
  CheckCircle2,
  Coins,
  Globe,
  LayoutDashboard,
  Plug,
  Settings,
  ShieldCheck,
  Smartphone,
  Sparkles,
  Target,
  TrendingUp,
  UserRound,
  Wrench,
} from 'lucide-react';
import { motion, AnimatePresence } from 'motion/react';
import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

type Tone = 'rose' | 'emerald' | 'violet' | 'amber' | 'sky';

const tones: Record<Tone, { chip: string; dot: string; bubble: string; label: string; solid: string; soft: string; bar: string }> = {
  rose: { chip: 'bg-rose-500/10 text-rose-600', dot: 'bg-rose-500', bubble: 'border-rose-200 bg-rose-50', label: 'text-rose-600', solid: 'bg-rose-600 border-rose-600 text-white', soft: 'bg-rose-500/10 text-rose-600', bar: 'bg-rose-500' },
  emerald: { chip: 'bg-emerald-500/10 text-emerald-600', dot: 'bg-emerald-500', bubble: 'border-emerald-200 bg-emerald-50', label: 'text-emerald-600', solid: 'bg-emerald-600 border-emerald-600 text-white', soft: 'bg-emerald-500/10 text-emerald-600', bar: 'bg-emerald-500' },
  violet: { chip: 'bg-violet-500/10 text-violet-600', dot: 'bg-violet-500', bubble: 'border-violet-200 bg-violet-50', label: 'text-violet-600', solid: 'bg-violet-600 border-violet-600 text-white', soft: 'bg-violet-500/10 text-violet-600', bar: 'bg-violet-500' },
  amber: { chip: 'bg-amber-500/10 text-amber-700', dot: 'bg-amber-500', bubble: 'border-amber-200 bg-amber-50', label: 'text-amber-700', solid: 'bg-amber-500 border-amber-500 text-white', soft: 'bg-amber-500/10 text-amber-700', bar: 'bg-amber-500' },
  sky: { chip: 'bg-sky-500/10 text-sky-600', dot: 'bg-sky-500', bubble: 'border-sky-200 bg-sky-50', label: 'text-sky-600', solid: 'bg-sky-600 border-sky-600 text-white', soft: 'bg-sky-500/10 text-sky-600', bar: 'bg-sky-500' },
};

const reveal = {
  hidden: { opacity: 0, y: 18, scale: 0.98 },
  visible: (index: number) => ({ opacity: 1, y: 0, scale: 1, transition: { duration: 0.5, delay: index * 0.14 } }),
};

const km = (value: number, decimals = 0) =>
  value.toLocaleString('de-DE', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }) + ' KM';

// Bosnian plural: 1 nalog, 2-4 naloga, 5+ naloga (11-14 always take the last form)
const plural = (n: number, one: string, few: string, many: string) => {
  const d = n % 10, h = n % 100;
  if (d === 1 && h !== 11) return `${n} ${one}`;
  if (d >= 2 && d <= 4 && (h < 12 || h > 14)) return `${n} ${few}`;
  return `${n} ${many}`;
};

/* ---------- Shared chat pieces ---------- */

const UserBubble = ({ children, index = 0 }: { children: React.ReactNode; index?: number }) => (
  <motion.div custom={index} variants={reveal} className="ml-auto w-fit max-w-[90%] rounded-2xl rounded-br-md bg-slate-900 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-slate-900/10">
    {children}
  </motion.div>
);

const SaraBubble = ({ tone, children, index = 1 }: { tone: Tone; children: React.ReactNode; index?: number }) => (
  <motion.div custom={index} variants={reveal} className="w-fit max-w-[92%] sm:max-w-[75%]">
    <span className={cn('mb-1.5 flex items-center gap-2 text-xs font-black', tones[tone].label)}>
      <BrainCircuit className="h-4 w-4" />SaraAI
    </span>
    <div className={cn('rounded-2xl rounded-bl-md border p-4 text-sm leading-6 text-slate-700', tones[tone].bubble)}>
      {children}
    </div>
  </motion.div>
);

function FilterChips<T extends string>({ tone, options, value, onChange }: { tone: Tone; options: { key: T; label: string }[]; value: T; onChange: (key: T) => void }) {
  return (
    <div className="flex flex-wrap gap-2">
      {options.map((option) => (
        <button
          key={option.key}
          type="button"
          onClick={() => onChange(option.key)}
          className={cn(
            'cursor-pointer rounded-full border px-3 py-1.5 text-xs font-bold transition-colors',
            value === option.key ? tones[tone].solid : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300',
          )}
        >
          {option.label}
        </button>
      ))}
    </div>
  );
}

const ResultCard = ({ index = 2, children }: { index?: number; children: React.ReactNode }) => (
  <motion.div custom={index} variants={reveal} className="rounded-3xl border border-slate-200 bg-slate-50 p-5 shadow-2xl shadow-slate-900/5">
    {children}
  </motion.div>
);

const SkillCopy = ({ number, tone, title, description, bullets, badge }: { number: string; tone: Tone; title: string; description: string; bullets: string[]; badge?: string }) => (
  <motion.div initial={{ opacity: 0, x: -24 }} whileInView={{ opacity: 1, x: 0 }} viewport={{ once: true, amount: 0.3 }} transition={{ duration: 0.6 }} className="self-center">
    <div className="flex flex-wrap items-center gap-2">
      <span className={cn('inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-[10px] font-black uppercase tracking-[0.18em]', tones[tone].chip)}>
        <Sparkles className="h-3.5 w-3.5" />Vještina {number}
      </span>
      {badge && <span className="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">{badge}</span>}
    </div>
    <h3 className="mt-5 text-3xl font-bold leading-tight tracking-tight text-slate-900 sm:text-4xl">{title}</h3>
    <p className="mt-5 text-base leading-7 text-slate-500 sm:text-lg">{description}</p>
    <div className="mt-7 space-y-3">
      {bullets.map((item) => (
        <div key={item} className="flex items-start gap-3 text-sm font-semibold text-slate-700">
          <span className={cn('mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-white', tones[tone].dot)}>
            <CheckCircle2 className="h-3.5 w-3.5" />
          </span>
          {item}
        </div>
      ))}
    </div>
  </motion.div>
);

const Visual = ({ children }: { children: React.ReactNode }) => (
  <motion.div initial="hidden" whileInView="visible" viewport={{ once: true, amount: 0.3 }} className="relative space-y-5">
    {children}
  </motion.div>
);

const SkillSection = ({ id, tone, flip, copy, visual }: { id: string; tone: Tone; flip?: boolean; copy: React.ReactNode; visual: React.ReactNode }) => (
  <section id={id} className="relative py-20 sm:py-24 scroll-mt-20">
    <div className={cn('pointer-events-none absolute top-1/3 h-72 w-72 rounded-full blur-3xl opacity-60', flip ? 'left-0' : 'right-0', tones[tone].soft)} />
    <div className={cn('relative grid gap-12 lg:items-center', flip ? 'lg:grid-cols-[1.15fr_0.85fr]' : 'lg:grid-cols-[0.85fr_1.15fr]')}>
      <div className={cn(flip && 'lg:order-2')}>{copy}</div>
      <div className={cn(flip && 'lg:order-1')}>{visual}</div>
    </div>
  </section>
);

/* ---------- Skill 1: delayed work orders ---------- */

type Period3 = 'danas' | 'mjesec' | 'ukupno';
const periodLabels: Record<Period3, string> = { danas: 'Danas', mjesec: 'Ovaj mjesec', ukupno: 'Ukupno' };

const delayed: Record<Period3, { total: number; critical: number; rows: { rn: string; op: string; days: number }[] }> = {
  danas: { total: 3, critical: 1, rows: [
    { rn: 'RN 26-0418', op: 'Glodanje · Kupac #1042', days: 6 },
    { rn: 'RN 26-0431', op: 'Bravarija · Kupac #0877', days: 2 },
    { rn: 'RN 26-0436', op: 'Kontrola · Kupac #1105', days: 1 },
  ] },
  mjesec: { total: 7, critical: 3, rows: [
    { rn: 'RN 26-0397', op: 'Savijanje · Kupac #0761', days: 9 },
    { rn: 'RN 26-0418', op: 'Glodanje · Kupac #1042', days: 6 },
    { rn: 'RN 26-0402', op: 'Farbanje · Kupac #0915', days: 5 },
  ] },
  ukupno: { total: 12, critical: 4, rows: [
    { rn: 'RN 26-0351', op: 'Bravarija · Kupac #0688', days: 14 },
    { rn: 'RN 26-0397', op: 'Savijanje · Kupac #0761', days: 9 },
    { rn: 'RN 26-0418', op: 'Glodanje · Kupac #1042', days: 6 },
  ] },
};

const DelayedVisual = () => {
  const [period, setPeriod] = useState<Period3>('mjesec');
  const data = delayed[period];
  return (
    <Visual>
      <UserBubble>Koji radni nalozi kasne?</UserBubble>
      <SaraBubble tone="rose">
        {period === 'danas' ? 'Danas' : period === 'mjesec' ? 'Ovog mjeseca' : 'Ukupno'} kasni <b>{plural(data.total, 'radni nalog', 'radna naloga', 'radnih naloga')}</b>, od toga {data.critical} više od 5 dana. Najviše kasni {data.rows[0].rn}.
      </SaraBubble>
      <motion.div custom={2} variants={reveal}>
        <FilterChips tone="rose" value={period} onChange={setPeriod} options={(Object.keys(periodLabels) as Period3[]).map((key) => ({ key, label: periodLabels[key] }))} />
      </motion.div>
      <ResultCard index={3}>
        <div className="flex items-center justify-between gap-3">
          <div className="flex items-center gap-2 text-xs font-black text-slate-900"><AlarmClock className="h-4 w-4 text-rose-500" />Nalozi u kašnjenju</div>
          <span className="rounded-full bg-rose-500/10 px-2.5 py-1 text-[10px] font-black text-rose-600">{data.total} ukupno · {data.critical} kritično</span>
        </div>
        <div className="mt-4 space-y-2">
          <AnimatePresence mode="popLayout">
            {data.rows.map((row) => (
              <motion.div key={period + row.rn} layout initial={{ opacity: 0, y: 6 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0 }} className="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-3">
                <span className="shrink-0 rounded-md bg-slate-100 px-2 py-1 font-mono text-[11px] font-black text-slate-700">{row.rn}</span>
                <span className="min-w-0 flex-1 truncate text-xs font-semibold text-slate-600">{row.op}</span>
                <span className={cn('shrink-0 text-xs font-black', row.days > 5 ? 'text-rose-600' : 'text-amber-600')}>+{row.days} d</span>
              </motion.div>
            ))}
          </AnimatePresence>
        </div>
      </ResultCard>
    </Visual>
  );
};

/* ---------- Skill 2: turnover ---------- */

type TurnoverPeriod = 'danas' | 'mjesec' | 'godina';
const turnover: Record<TurnoverPeriod, { label: string; when: string; value: number; delta: number; compare: string; bars: number[] }> = {
  danas: { label: 'Danas', when: 'Danas', value: 18450, delta: 6, compare: 'u odnosu na prosjek radnog dana', bars: [52, 61, 48, 70, 66, 58, 74] },
  mjesec: { label: 'Ovaj mjesec', when: 'Ovog mjeseca', value: 312800, delta: 8, compare: 'u odnosu na prošli mjesec', bars: [58, 63, 55, 71, 68, 76, 82] },
  godina: { label: 'Ova godina', when: 'Ove godine', value: 2940000, delta: 12, compare: 'u odnosu na isti period prošle godine', bars: [44, 52, 57, 61, 66, 73, 80] },
};

const TurnoverVisual = () => {
  const [period, setPeriod] = useState<TurnoverPeriod>('mjesec');
  const data = turnover[period];
  return (
    <Visual>
      <UserBubble>Koliki je ukupni promet?</UserBubble>
      <SaraBubble tone="emerald">
        {data.when} je ukupni promet <b>{km(data.value)}</b>, što je {data.delta}% više {data.compare}.
      </SaraBubble>
      <motion.div custom={2} variants={reveal}>
        <FilterChips tone="emerald" value={period} onChange={setPeriod} options={[{ key: 'danas', label: 'Danas' }, { key: 'mjesec', label: 'Ovaj mjesec' }, { key: 'godina', label: 'Ova godina' }]} />
      </motion.div>
      <ResultCard index={3}>
        <div className="flex items-center gap-2 text-xs font-black text-slate-900"><Coins className="h-4 w-4 text-emerald-600" />Ukupni promet · {data.label}</div>
        <div className="mt-4 flex flex-wrap items-end justify-between gap-4">
          <AnimatePresence mode="wait">
            <motion.p key={period} initial={{ opacity: 0, y: 6 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0 }} className="text-4xl font-bold tracking-tight text-slate-900">{km(data.value)}</motion.p>
          </AnimatePresence>
          <span className="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2.5 py-1 text-xs font-black text-emerald-600"><TrendingUp className="h-3.5 w-3.5" />+{data.delta}%</span>
        </div>
        <div className="mt-5 flex h-24 items-end gap-2">
          {data.bars.map((h, i) => (
            <motion.div key={i} className={cn('flex-1 rounded-t-lg', i === data.bars.length - 1 ? 'bg-emerald-500' : 'bg-emerald-200')} initial={false} animate={{ height: `${h}%` }} transition={{ duration: 0.5, delay: i * 0.03 }} />
          ))}
        </div>
      </ResultCard>
    </Visual>
  );
};

/* ---------- Skill 3: goals ---------- */

const goals = [
  { title: 'Promet za oktobar', current: '252.000 KM', target: '350.000 KM', pct: 72 },
  { title: 'Nalozi završeni na vrijeme', current: '88%', target: '95%', pct: 93 },
  { title: 'Iskorištenost mašina', current: '71%', target: '80%', pct: 89 },
];

const GoalsVisual = () => (
  <Visual>
    <UserBubble>Kako stojimo s ciljevima ovaj mjesec?</UserBubble>
    <SaraBubble tone="violet">
      Promet je na <b>72% cilja</b>, a do kraja mjeseca je ostalo 9 radnih dana. Za cilj je potrebno oko <b>10.900 KM dnevno</b>. Nalozi na vrijeme i iskorištenost mašina su blizu cilja.
    </SaraBubble>
    <ResultCard index={2}>
      <div className="flex items-center gap-2 text-xs font-black text-slate-900"><Target className="h-4 w-4 text-violet-600" />Stanje ciljeva · Oktobar</div>
      <div className="mt-4 space-y-4">
        {goals.map((goal, i) => (
          <div key={goal.title} className="rounded-xl border border-slate-200 bg-white p-3">
            <div className="flex flex-wrap items-baseline justify-between gap-2">
              <p className="text-sm font-bold text-slate-900">{goal.title}</p>
              <p className="text-xs font-semibold text-slate-500">{goal.current} od {goal.target}</p>
            </div>
            <div className="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
              <motion.div className="h-full rounded-full bg-violet-500" initial={{ width: 0 }} whileInView={{ width: `${goal.pct}%` }} viewport={{ once: true }} transition={{ duration: 0.9, delay: 0.3 + i * 0.15 }} />
            </div>
          </div>
        ))}
      </div>
    </ResultCard>
  </Visual>
);

/* ---------- Skill 4: purchase vs sales price ---------- */

const products = [
  { key: 'dn100', name: 'Prirubnica DN100', buy: 42.8, sell: 68.5 },
  { key: 'nl40', name: 'Nosač ležaja NL-40', buy: 18.2, sell: 31.9 },
  { key: 'os30', name: 'Osovina Ø30×250', buy: 27.4, sell: 39.0 },
] as const;
type ProductKey = (typeof products)[number]['key'];

const PriceVisual = () => {
  const [key, setKey] = useState<ProductKey>('dn100');
  const product = products.find((p) => p.key === key)!;
  const margin = product.sell - product.buy;
  const pct = Math.round((margin / product.sell) * 1000) / 10;
  return (
    <Visual>
      <UserBubble>Uporedi nabavnu i prodajnu cijenu za {product.name}.</UserBubble>
      <SaraBubble tone="amber">
        Za {product.name} nabavna cijena je <b>{km(product.buy, 2)}</b>, a prodajna <b>{km(product.sell, 2)}</b>. Razlika je {km(margin, 2)}, odnosno marža od <b>{pct.toLocaleString('de-DE')}%</b>.
      </SaraBubble>
      <motion.div custom={2} variants={reveal}>
        <FilterChips tone="amber" value={key} onChange={setKey} options={products.map((p) => ({ key: p.key, label: p.name }))} />
      </motion.div>
      <ResultCard index={3}>
        <div className="flex items-center gap-2 text-xs font-black text-slate-900"><BarChart3 className="h-4 w-4 text-amber-600" />Nabavna i prodajna cijena</div>
        <div className="mt-4 space-y-3">
          {[
            { label: 'Nabavna cijena', value: product.buy, cls: 'bg-slate-400' },
            { label: 'Prodajna cijena', value: product.sell, cls: 'bg-amber-500' },
          ].map((row) => (
            <div key={row.label}>
              <div className="mb-1 flex justify-between text-xs font-semibold text-slate-600"><span>{row.label}</span><span className="font-black text-slate-900">{km(row.value, 2)}</span></div>
              <div className="h-3 overflow-hidden rounded-full bg-slate-100">
                <motion.div className={cn('h-full rounded-full', row.cls)} initial={false} animate={{ width: `${(row.value / (product.sell * 1.05)) * 100}%` }} transition={{ duration: 0.5 }} />
              </div>
            </div>
          ))}
        </div>
        <div className="mt-4 grid grid-cols-2 gap-2">
          <div className="rounded-xl border border-slate-200 bg-white p-3"><p className="text-[10px] font-black uppercase tracking-wider text-slate-400">Razlika</p><p className="mt-1 text-lg font-bold text-slate-900">{km(margin, 2)}</p></div>
          <div className="rounded-xl border border-amber-200 bg-amber-50 p-3"><p className="text-[10px] font-black uppercase tracking-wider text-amber-700">Marža</p><p className="mt-1 text-lg font-bold text-amber-800">{pct.toLocaleString('de-DE')}%</p></div>
        </div>
      </ResultCard>
    </Visual>
  );
};

/* ---------- Skill 5: worker statistics ---------- */

type WorkerKey = 'haris' | 'amela' | 'dino';
type WorkerPeriod = 'danas' | 'mjesec';
const workers: Record<WorkerKey, { name: string; role: string; female: boolean; stats: Record<WorkerPeriod, { ops: number; hours: number; eff: number; down: number }> }> = {
  haris: { name: 'Haris M.', role: 'CNC glodanje', female: false, stats: { danas: { ops: 14, hours: 7.5, eff: 96, down: 12 }, mjesec: { ops: 238, hours: 151, eff: 93, down: 184 } } },
  amela: { name: 'Amela K.', role: 'Kontrola kvaliteta', female: true, stats: { danas: { ops: 22, hours: 8, eff: 101, down: 0 }, mjesec: { ops: 401, hours: 158, eff: 98, down: 35 } } },
  dino: { name: 'Dino S.', role: 'Bravarija', female: false, stats: { danas: { ops: 9, hours: 6.5, eff: 84, down: 41 }, mjesec: { ops: 176, hours: 139, eff: 88, down: 312 } } },
};

const WorkerVisual = () => {
  const [worker, setWorker] = useState<WorkerKey>('haris');
  const [period, setPeriod] = useState<WorkerPeriod>('mjesec');
  const w = workers[worker];
  const s = w.stats[period];
  const when = period === 'danas' ? 'danas' : 'ovog mjeseca';
  const hours = Number.isInteger(s.hours) ? plural(s.hours, 'sat', 'sata', 'sati') : `${s.hours.toLocaleString('de-DE')} sati`;
  return (
    <Visual>
      <UserBubble>Pokaži statistiku: {w.name}, {period === 'danas' ? 'danas' : 'ovaj mjesec'}.</UserBubble>
      <SaraBubble tone="sky">
        {w.name} je {when} {w.female ? 'završila' : 'završio'} <b>{plural(s.ops, 'operaciju', 'operacije', 'operacija')}</b> za {hours} rada, uz učinak od <b>{s.eff}%</b> norme {s.down === 0 ? 'i bez zastoja' : `i ${s.down} min zastoja`}.
      </SaraBubble>
      <motion.div custom={2} variants={reveal} className="space-y-2">
        <FilterChips tone="sky" value={worker} onChange={setWorker} options={(Object.keys(workers) as WorkerKey[]).map((key) => ({ key, label: workers[key].name }))} />
        <FilterChips tone="sky" value={period} onChange={setPeriod} options={[{ key: 'danas', label: 'Danas' }, { key: 'mjesec', label: 'Ovaj mjesec' }]} />
      </motion.div>
      <ResultCard index={3}>
        <div className="flex items-center gap-3">
          <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-sky-500/10 text-sky-600"><UserRound className="h-5 w-5" /></span>
          <div className="min-w-0"><p className="truncate font-bold text-slate-900">{w.name}</p><p className="truncate text-xs text-slate-500">{w.role} · {period === 'danas' ? 'Danas' : 'Ovaj mjesec'}</p></div>
        </div>
        <div className="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
          {[
            { label: 'Operacije', value: String(s.ops) },
            { label: 'Sati rada', value: s.hours.toLocaleString('de-DE') + ' h' },
            { label: 'Učinak', value: s.eff + '%' },
            { label: 'Zastoji', value: s.down + ' min' },
          ].map((item) => (
            <div key={item.label} className="rounded-xl border border-slate-200 bg-white p-3">
              <p className="text-[10px] font-black uppercase tracking-wider text-slate-400">{item.label}</p>
              <AnimatePresence mode="wait">
                <motion.p key={worker + period + item.value} initial={{ opacity: 0, y: 4 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0 }} className="mt-1 text-lg font-bold text-slate-900">{item.value}</motion.p>
              </AnimatePresence>
            </div>
          ))}
        </div>
      </ResultCard>
    </Visual>
  );
};

/* ---------- Page ---------- */

const skillLinks = [
  { id: 'vjestina-1', label: 'Nalozi u kašnjenju', tone: 'rose' as Tone },
  { id: 'vjestina-2', label: 'Ukupni promet', tone: 'emerald' as Tone },
  { id: 'vjestina-3', label: 'Stanje ciljeva', tone: 'violet' as Tone },
  { id: 'vjestina-4', label: 'Nabavna i prodajna cijena', tone: 'amber' as Tone },
  { id: 'vjestina-5', label: 'Statistika radnika', tone: 'sky' as Tone },
];

const included = [
  { icon: Wrench, title: 'Setup i konfiguracija', desc: 'Povezivanje SaraAI s podacima iz eNalog.app i Pantheona, uz prava pristupa po ulogama.' },
  { icon: Globe, title: 'Integracija u eNalog.app web', desc: 'SaraAI chat direktno unutar web aplikacije, dostupan sa svakog ekrana.' },
  { icon: Smartphone, title: 'Integracija u native aplikaciju', desc: 'Isti asistent u novoj mobilnoj i tablet aplikaciji, za rad u pogonu i na terenu.' },
  { icon: Sparkles, title: '5 početnih vještina', desc: 'Nalozi u kašnjenju, promet, ciljevi, cijene i statistika radnika, spremni od prvog dana.' },
];

export default function PonudaSaraAi() {
  return (
    <div className="min-h-screen bg-[#F8FAFC] font-sans text-slate-900 selection:bg-violet-100 selection:text-slate-900">
      {/* Navigation */}
      <nav className="sticky top-0 z-50 bg-white/80 backdrop-blur-md border-b border-slate-200">
        <div className="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
          <div className="flex items-center gap-4">
            <img src="https://enalog.app/images/logo/TrendyCNC.png" alt="Trendy CNC Logo" className="h-10 object-contain" referrerPolicy="no-referrer" />
            <div className="h-6 w-px bg-slate-200 hidden md:block" />
            <span className="text-lg font-bold tracking-tight hidden md:block">eNalog<span className="text-slate-500">.app</span> <span className="text-violet-600">· SaraAI</span></span>
          </div>
          <span className="md:hidden text-lg font-bold tracking-tight">eNalog<span className="text-slate-500">.app</span> <span className="text-violet-600">· SaraAI</span></span>
          <div className="hidden md:flex items-center gap-8">
            <a href="#ukljuceno" className="text-sm font-medium text-slate-500 hover:text-slate-900 transition-colors">Šta je uključeno</a>
            <a href="#vjestine" className="text-sm font-medium text-slate-500 hover:text-slate-900 transition-colors">Vještine</a>
            <a href="#cijena" className="text-sm font-medium text-slate-500 hover:text-slate-900 transition-colors">Cijena</a>
            <div className="h-6 w-px bg-slate-200" />
            <span className="text-sm font-bold text-slate-900">Ukupno: 4.900 KM</span>
          </div>
        </div>
      </nav>

      {/* Hero */}
      <section className="pt-8 sm:pt-20 pb-20 sm:pb-28 px-6 overflow-hidden">
        <div className="max-w-7xl mx-auto grid lg:grid-cols-2 gap-16 items-center">
          <motion.div initial={{ opacity: 0, x: -20 }} animate={{ opacity: 1, x: 0 }} transition={{ duration: 0.6 }}>
            <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-violet-50 border border-violet-100 text-violet-700 text-xs font-bold uppercase tracking-wider mb-6">
              <BrainCircuit className="w-3.5 h-3.5" />
              AI asistent za eNalog.app
            </div>
            <h1 className="text-5xl sm:text-6xl lg:text-7xl font-bold tracking-tight text-slate-900 mb-6 sm:mb-8 leading-[1.1]">
              Pitajte proizvodnju. <span className="text-violet-500">Sara odgovara.</span>
            </h1>
            <p className="text-lg sm:text-xl text-slate-500 leading-relaxed mb-8 sm:mb-10 max-w-xl">
              SaraAI je ugrađena u eNalog.app web i native aplikaciju. Pitajte običnim jezikom i dobijte odgovor iz vaših podataka za nekoliko sekundi.
            </p>
            <div className="grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)] gap-3 sm:flex sm:flex-wrap sm:gap-4">
              <a href="#vjestine" className="px-2 min-[380px]:px-4 sm:px-8 py-3.5 sm:py-4 bg-slate-900 text-white rounded-2xl font-bold text-[13px] min-[380px]:text-sm sm:text-base whitespace-nowrap hover:bg-slate-800 transition-all flex items-center justify-center gap-2 group">
                Pogledaj vještine
                <ArrowRight className="w-4 h-4 group-hover:translate-x-1 transition-transform" />
              </a>
              <a href="#cijena" className="px-2 min-[380px]:px-4 sm:px-8 py-3.5 sm:py-4 bg-white border border-slate-200 text-slate-900 rounded-2xl font-bold text-[13px] min-[380px]:text-sm sm:text-base whitespace-nowrap flex items-center justify-center gap-2 sm:gap-3 hover:border-slate-400 transition-all">
                <Coins className="w-5 h-5 text-slate-500" />
                4.900 KM
              </a>
            </div>
          </motion.div>

          <motion.div initial={{ opacity: 0, scale: 0.95 }} animate={{ opacity: 1, scale: 1 }} transition={{ duration: 0.8, delay: 0.2 }} className="relative">
            <div className="absolute -inset-4 bg-violet-500/10 blur-3xl rounded-full" />
            <div className="relative bg-white rounded-[2.5rem] border border-slate-200 shadow-2xl overflow-hidden">
              <div className="flex items-center justify-between px-6 sm:px-8 py-5 border-b border-slate-100">
                <div className="flex items-center gap-3">
                  <span className="flex h-10 w-10 items-center justify-center rounded-2xl bg-violet-600 text-white"><BrainCircuit className="h-5 w-5" /></span>
                  <div>
                    <p className="text-sm font-bold text-slate-900">SaraAI</p>
                    <p className="text-[11px] font-semibold text-slate-400">eNalog.app asistent</p>
                  </div>
                </div>
                <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-100 text-emerald-700 text-[10px] font-bold uppercase tracking-wider">
                  <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse" />
                  Online
                </div>
              </div>
              <motion.div initial="hidden" animate="visible" className="space-y-4 p-6 sm:p-8">
                <UserBubble index={1}>Koliko naloga kasni ovaj mjesec i koliki je promet?</UserBubble>
                <SaraBubble tone="violet" index={2}>
                  Ovog mjeseca kasni <b>7 radnih naloga</b>, od toga 3 više od 5 dana. Ukupni promet je <b>312.800 KM</b>, 8% više nego prošli mjesec.
                </SaraBubble>
                <motion.div custom={3} variants={reveal} className="grid grid-cols-2 gap-2">
                  <div className="rounded-2xl border border-rose-100 bg-rose-50 p-3"><p className="text-[10px] font-black uppercase tracking-wider text-rose-600">Kasni</p><p className="mt-1 text-xl font-bold text-slate-900">7 RN</p></div>
                  <div className="rounded-2xl border border-emerald-100 bg-emerald-50 p-3"><p className="text-[10px] font-black uppercase tracking-wider text-emerald-600">Promet</p><p className="mt-1 text-xl font-bold text-slate-900">312.800 KM</p></div>
                </motion.div>
              </motion.div>
              <div className="border-t border-slate-100 bg-slate-50 px-6 sm:px-8 py-4">
                <p className="mb-2 text-[10px] font-black uppercase tracking-wider text-slate-400">Pitajte Saru</p>
                <div className="flex flex-wrap gap-2">
                  {skillLinks.map((s) => (
                    <a key={s.id} href={`#${s.id}`} className={cn('rounded-full px-3 py-1.5 text-xs font-bold transition-opacity hover:opacity-80', tones[s.tone].chip)}>{s.label}</a>
                  ))}
                </div>
              </div>
            </div>
          </motion.div>
        </div>
      </section>

      {/* Included */}
      <section id="ukljuceno" className="py-28 bg-white scroll-mt-20">
        <div className="max-w-7xl mx-auto px-6">
          <div className="text-center mb-16">
            <span className="text-xs font-bold tracking-[0.3em] text-slate-400 uppercase mb-4 block">Šta je uključeno</span>
            <h2 className="text-4xl lg:text-5xl font-bold mb-6 tracking-tight text-slate-900">Jedan asistent, svuda u eNalog.app</h2>
            <p className="text-slate-500 max-w-2xl mx-auto text-lg">
              Postavljanje, integracija u web i native aplikaciju i pet vještina koje odmah odgovaraju na najčešća pitanja iz proizvodnje.
            </p>
          </div>
          <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
            {included.map((item, i) => (
              <motion.div key={item.title} initial={{ opacity: 0, y: 20 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true }} transition={{ delay: i * 0.08 }} className="p-6 rounded-[1.75rem] border border-slate-200 bg-white hover:border-slate-300 transition-colors">
                <div className="w-12 h-12 rounded-2xl bg-violet-50 text-violet-600 flex items-center justify-center mb-5"><item.icon className="w-6 h-6" /></div>
                <h3 className="text-lg font-bold text-slate-900 mb-2">{item.title}</h3>
                <p className="text-sm text-slate-500 leading-relaxed">{item.desc}</p>
              </motion.div>
            ))}
          </div>
          <div className="mt-6 flex justify-center">
            <div className="inline-flex flex-wrap items-center justify-center gap-x-3 gap-y-1 px-5 py-3 rounded-2xl border bg-slate-50 border-slate-200 text-sm text-slate-900">
              <ShieldCheck className="w-4 h-4 shrink-0 text-slate-600" />
              <span className="font-bold">Sigurnost</span>
              <span className="text-slate-600">Sara vidi samo podatke koje prijavljeni korisnik smije vidjeti u eNalog.app.</span>
            </div>
          </div>
        </div>
      </section>

      {/* Skills */}
      <section id="vjestine" className="pt-28 bg-[#F8FAFC] scroll-mt-20 overflow-hidden">
        <div className="max-w-7xl mx-auto px-6">
          <div className="text-center mb-8">
            <span className="text-xs font-bold tracking-[0.3em] text-slate-400 uppercase mb-4 block">5 početnih vještina</span>
            <h2 className="text-4xl lg:text-5xl font-bold mb-6 tracking-tight text-slate-900">Kako izgleda razgovor sa Sarom</h2>
            <p className="text-slate-500 max-w-2xl mx-auto text-lg">
              Primjeri razgovora sa ilustrativnim podacima. Kliknite filtere da vidite kako Sara mijenja odgovor.
            </p>
          </div>

          <div className="divide-y divide-slate-200">
            <SkillSection
              id="vjestina-1"
              tone="rose"
              copy={<SkillCopy number="01" tone="rose" title="Nalozi u kašnjenju" description="Sara odmah izdvaja radne naloge koji kasne, koliko kasne i na kojoj su operaciji zapeli." bullets={['Filter: danas, ovaj mjesec ili ukupno', 'Broj naloga i koliko je kritičnih', 'Najveća kašnjenja s operacijom i kupcem']} />}
              visual={<DelayedVisual />}
            />
            <SkillSection
              id="vjestina-2"
              tone="emerald"
              flip
              copy={<SkillCopy number="02" tone="emerald" title="Ukupni promet" description="Jedno pitanje umjesto izvještaja. Sara sabira promet za traženi period i odmah ga upoređuje s prethodnim." bullets={['Filter: danas, ovaj mjesec ili ova godina', 'Poređenje s prethodnim periodom', 'Kretanje prometa kroz vrijeme']} />}
              visual={<TurnoverVisual />}
            />
            <SkillSection
              id="vjestina-3"
              tone="violet"
              copy={<SkillCopy number="03" tone="violet" badge="Uskoro u eNalog.app" title="Stanje ciljeva" description="eNalog.app uskoro dobija postavljanje ciljeva. Sara tada za svaki cilj kaže gdje ste sada i šta treba do kraja perioda." bullets={['Trenutno stanje svakog cilja u procentima', 'Koliko je ostalo do kraja perioda', 'Šta je potrebno dnevno da se cilj dostigne']} />}
              visual={<GoalsVisual />}
            />
            <SkillSection
              id="vjestina-4"
              tone="amber"
              flip
              copy={<SkillCopy number="04" tone="amber" title="Nabavna i prodajna cijena" description="Pitajte za bilo koji proizvod i Sara uporedi nabavnu i prodajnu cijenu, razliku i maržu." bullets={['Pretraga proizvoda po nazivu ili šifri', 'Nabavna i prodajna cijena jedna pored druge', 'Razlika u KM i marža u procentima']} />}
              visual={<PriceVisual />}
            />
            <SkillSection
              id="vjestina-5"
              tone="sky"
              copy={<SkillCopy number="05" tone="sky" title="Statistika po radnicima" description="Odaberite radnika i period, a Sara prikaže njegove operacije, sate rada, učinak i zastoje." bullets={['Odabir radnika po imenu', 'Filter: danas ili ovaj mjesec', 'Operacije, sati rada, učinak i zastoji']} />}
              visual={<WorkerVisual />}
            />
          </div>
        </div>
      </section>

      {/* Pricing */}
      <section id="cijena" className="py-28 bg-white scroll-mt-20">
        <div className="max-w-5xl mx-auto px-6">
          <div className="text-center mb-14">
            <span className="text-xs font-bold tracking-[0.3em] text-slate-400 uppercase mb-4 block">Investicija</span>
            <h2 className="text-4xl lg:text-5xl font-bold tracking-tight text-slate-900">SaraAI za eNalog.app</h2>
          </div>
          <motion.div initial={{ opacity: 0, y: 20 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true }} className="grid md:grid-cols-[1.2fr_0.8fr] rounded-[2.5rem] overflow-hidden border border-slate-200 shadow-sm">
            <div className="bg-white p-8 lg:p-10">
              <h3 className="text-xl font-bold text-slate-900 mb-6">Uključeno u cijenu</h3>
              <ul className="space-y-3">
                {[
                  'Setup i konfiguracija SaraAI asistenta',
                  'Povezivanje s podacima iz eNalog.app i Pantheona',
                  'Prava pristupa po ulogama korisnika',
                  'Integracija u eNalog.app web aplikaciju',
                  'Integracija u novu native mobilnu i tablet aplikaciju',
                  '5 početnih vještina: nalozi u kašnjenju, promet, ciljevi, cijene i statistika radnika',
                ].map((item) => (
                  <li key={item} className="flex items-start gap-3 text-slate-600">
                    <CheckCircle2 className="w-5 h-5 text-slate-900 mt-0.5 shrink-0" />
                    <span className="text-sm leading-relaxed">{item}</span>
                  </li>
                ))}
              </ul>
            </div>
            <div className="bg-slate-900 text-white p-8 lg:p-10 flex flex-col justify-between gap-8">
              <div>
                <span className="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-violet-200"><Plug className="h-3.5 w-3.5" />Setup + integracija + 5 vještina</span>
                <p className="mt-6 text-5xl font-bold">4.900 KM</p>
                <p className="mt-2 text-sm font-medium uppercase tracking-widest text-slate-400">Fiksna cijena</p>
              </div>
              <p className="text-sm text-slate-400 leading-relaxed">Nove vještine se mogu dodavati kasnije, kako se pojave nova pitanja iz proizvodnje.</p>
            </div>
          </motion.div>
        </div>
      </section>

      {/* Footer */}
      <footer className="py-20 bg-white border-t border-slate-100">
        <div className="max-w-7xl mx-auto px-6 flex flex-col md:flex-row justify-between items-center gap-8">
          <div className="flex items-center gap-4">
            <img src="https://enalog.app/images/logo/TrendyCNC.png" alt="Trendy CNC Logo" className="h-8 object-contain opacity-50 grayscale hover:grayscale-0 transition-all" referrerPolicy="no-referrer" />
            <div className="h-4 w-px bg-slate-200" />
            <span className="text-sm font-bold tracking-tight text-slate-400">eNalog<span className="text-slate-300">.app</span></span>
          </div>
          <div className="flex flex-col md:flex-row items-center gap-4">
            <p className="text-slate-400 text-sm">© 2026 Sva prava zadržana. Ponuda napravljena od strane</p>
            <a href="https://qla.dev/" target="_blank" rel="noopener noreferrer" className="hover:opacity-100 transition-opacity opacity-80">
              <img src="https://deklarant.ai/build/images/logo-qla.png" alt="QLA Logo" className="h-6 object-contain" referrerPolicy="no-referrer" />
            </a>
          </div>
          <div className="flex gap-6">
            <a href="#" className="text-slate-400 hover:text-slate-900 transition-colors"><LayoutDashboard className="w-5 h-5" /></a>
            <a href="#" className="text-slate-400 hover:text-slate-900 transition-colors"><Settings className="w-5 h-5" /></a>
          </div>
        </div>
      </footer>
    </div>
  );
}
