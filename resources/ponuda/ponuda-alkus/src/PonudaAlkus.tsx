/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { useState, type ReactNode } from 'react';
import {
  ArrowRight, BrainCircuit, CheckCircle2, Coins, Database, FileScan, Printer, ShieldCheck, Sparkles, Users,
} from 'lucide-react';
import { motion } from 'motion/react';
import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';
import { OrderflowDetails, TokenDetails, scenarios } from './OrderflowDetails.tsx';
import alkusLogo from './alkus-logo.png';

function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

const gold = {
  chip: 'bg-alkus-500/10 text-alkus-700',
  dot: 'bg-alkus-600',
  bubble: 'border-alkus-200 bg-alkus-50',
  label: 'text-alkus-700',
  solid: 'bg-alkus-700 border-alkus-700 text-white',
  soft: 'bg-alkus-500/10',
};

const reveal = {
  hidden: { opacity: 0, y: 18, scale: 0.98 },
  visible: (index: number) => ({ opacity: 1, y: 0, scale: 1, transition: { duration: 0.5, delay: index * 0.14 } }),
};

/* ---------- Shared chat pieces ---------- */

const UserBubble = ({ children, index = 0 }: { children: ReactNode; index?: number }) => (
  <motion.div custom={index} variants={reveal} className="ml-auto w-fit max-w-[90%] rounded-2xl rounded-br-md bg-slate-900 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-slate-900/10">
    {children}
  </motion.div>
);

const LenaBubble = ({ children, index = 1 }: { children: ReactNode; index?: number }) => (
  <motion.div custom={index} variants={reveal} className="w-fit max-w-[92%] sm:max-w-[75%]">
    <span className={cn('mb-1.5 flex items-center gap-2 text-xs font-black', gold.label)}>
      <BrainCircuit className="h-4 w-4" />Lena AI
    </span>
    <div className={cn('rounded-2xl rounded-bl-md border p-4 text-sm leading-6 text-slate-700', gold.bubble)}>
      {children}
    </div>
  </motion.div>
);

function FilterChips<T extends string>({ options, value, onChange }: { options: { key: T; label: string }[]; value: T; onChange: (key: T) => void }) {
  return (
    <div className="flex flex-wrap gap-2">
      {options.map((option) => (
        <button
          key={option.key}
          type="button"
          aria-pressed={value === option.key}
          onClick={() => onChange(option.key)}
          className={cn(
            'cursor-pointer rounded-full border px-3 py-1.5 text-xs font-bold transition-colors',
            value === option.key ? gold.solid : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300',
          )}
        >
          {option.label}
        </button>
      ))}
    </div>
  );
}

const ResultCard = ({ index = 2, children }: { index?: number; children: ReactNode }) => (
  <motion.div custom={index} variants={reveal} className="rounded-3xl border border-slate-200 bg-slate-50 p-5 shadow-2xl shadow-slate-900/5">
    {children}
  </motion.div>
);

const SkillCopy = ({ number, title, description, bullets }: { number: string; title: string; description: string; bullets: string[] }) => (
  <motion.div initial={{ opacity: 0, x: -24 }} whileInView={{ opacity: 1, x: 0 }} viewport={{ once: true, amount: 0.3 }} transition={{ duration: 0.6 }} className="self-center">
    <div className="flex flex-wrap items-center gap-2">
      <span className={cn('inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-[10px] font-black uppercase tracking-[0.18em]', gold.chip)}>
        <Sparkles className="h-3.5 w-3.5" />Vještina {number}
      </span>
    </div>
    <h3 className="mt-5 text-3xl font-bold leading-tight tracking-tight text-slate-900 sm:text-4xl">{title}</h3>
    <p className="mt-5 text-base leading-7 text-slate-500 sm:text-lg">{description}</p>
    <div className="mt-7 space-y-3">
      {bullets.map((item) => (
        <div key={item} className="flex items-start gap-3 text-sm font-semibold text-slate-700">
          <span className={cn('mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-white', gold.dot)}>
            <CheckCircle2 className="h-3.5 w-3.5" />
          </span>
          {item}
        </div>
      ))}
    </div>
  </motion.div>
);

const Visual = ({ children }: { children: ReactNode }) => (
  <motion.div initial="hidden" whileInView="visible" viewport={{ once: true, amount: 0.3 }} className="relative space-y-5">
    {children}
  </motion.div>
);

const SkillSection = ({ id, flip, copy, visual }: { id: string; flip?: boolean; copy: ReactNode; visual: ReactNode }) => (
  <section id={id} className="relative py-20 sm:py-24 scroll-mt-20">
    <div className={cn('pointer-events-none absolute top-1/3 h-72 w-72 rounded-full blur-3xl opacity-60', flip ? 'left-0' : 'right-0', gold.soft)} />
    <div className={cn('relative grid gap-12 lg:items-center', flip ? 'lg:grid-cols-[1.15fr_0.85fr]' : 'lg:grid-cols-[0.85fr_1.15fr]')}>
      <div className={cn(flip && 'lg:order-2')}>{copy}</div>
      <div className={cn(flip && 'lg:order-1')}>{visual}</div>
    </div>
  </section>
);

const skillLinks = scenarios.map(({ title }, index) => ({ id: 'vjestina-' + (index + 1), label: title }));

const included = [
  { icon: FileScan, title: 'Modul Narudžbe', desc: 'Povlačenje PDF-a, AI priprema nacrta i ručni unos narudžbi kupaca.' },
  { icon: Database, title: 'Pantheon integracija', desc: 'Provjera šifrarnika, upis narudžbe i povrat broja dokumenta.' },
  { icon: Users, title: 'Do 3 korisnika', desc: 'Zaseban korisnički račun i prijava za svakog člana tima.' },
  { icon: Sparkles, title: 'Lena AI bez doplate', desc: 'Uključena integracija i 20 besplatnih poruka dnevno. Svaka naredna poruka troši 1 token.' },
];

function ScenarioVisual({ scenario }: { scenario: (typeof scenarios)[number] }) {
  const [filter, setFilter] = useState('all');
  const options = [{ key: 'all', label: 'Sve' }, ...scenario.rows.map(([label]) => ({ key: label, label }))];
  const rows = scenario.rows.filter(([label]) => filter === 'all' || filter === label);
  const answer = filter === 'all' ? scenario.answer : rows.map((row) =>
    row.map((value, index) => scenario.columns[index] + ' – ' + value).join(', ') + '.').join(' ');

  return (
    <Visual>
      <UserBubble>{scenario.prompt}{filter !== 'all' && ' Fokusiraj se na ' + filter + '.'}</UserBubble>
      <LenaBubble>{answer}</LenaBubble>
      <ResultCard>
        <div className="print:hidden">
          <FilterChips options={options} value={filter} onChange={setFilter} />
        </div>
        <table className="mt-4 w-full table-fixed text-left text-xs leading-relaxed sm:text-sm" aria-label={scenario.title}>
          <thead><tr>{scenario.columns.map((heading) => <th className="break-words px-1 pb-2 text-slate-500" key={heading} scope="col">{heading}</th>)}</tr></thead>
          <tbody aria-live="polite">
            {rows.map(([label, ...values]) => (
              <tr className="border-t border-slate-200" key={label}>
                <th className="break-words px-1 py-2" scope="row">{label}</th>
                {values.map((value, index) => <td className="break-words px-1 py-2 text-slate-600" key={index}>{value}</td>)}
              </tr>
            ))}
          </tbody>
        </table>
      </ResultCard>
    </Visual>
  );
}

export default function PonudaAlkus() {
  return (
    <div className="alkus-offer min-h-screen bg-[#f8f5ee] font-sans text-slate-900 selection:bg-alkus-100 selection:text-slate-900">
      {/* Navigation */}
      <nav className="sticky top-0 z-50 bg-white/80 backdrop-blur-md border-b border-slate-200">
        <div className="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
          <div className="flex items-center gap-4">
            <img src={alkusLogo} alt="Alkus" className="h-10 object-contain" referrerPolicy="no-referrer" />
            <div className="h-6 w-px bg-slate-200 hidden md:block" />
            <span className="text-lg font-bold tracking-tight hidden md:block">Orderflow<span className="text-alkus-700">.ai</span> <span className="text-alkus-600">· Lena AI</span></span>
          </div>
          <span className="md:hidden text-lg font-bold tracking-tight">Orderflow<span className="text-alkus-700">.ai</span> <span className="text-alkus-600">· Lena AI</span></span>
          <div className="hidden md:flex items-center gap-8">
            <a href="#ukljuceno" className="text-sm font-medium text-slate-500 hover:text-slate-900 transition-colors">Šta je uključeno</a>
            <a href="#vjestine" className="text-sm font-medium text-slate-500 hover:text-slate-900 transition-colors">Lena AI</a>
            <a href="#modul" className="text-sm font-medium text-slate-500 hover:text-slate-900 transition-colors">Narudžbe</a>
            <a href="#pantheon" className="text-sm font-medium text-slate-500 hover:text-slate-900 transition-colors">Pantheon</a>
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
            <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-alkus-50 border border-alkus-100 text-alkus-700 text-xs font-bold uppercase tracking-wider mb-6">
              <BrainCircuit className="w-3.5 h-3.5" />
              Ponuda za ALKUS d.o.o. · Kreševo
            </div>
            <h1 className="text-5xl sm:text-6xl lg:text-7xl font-bold tracking-tight text-slate-900 mb-6 sm:mb-8 leading-[1.1]">
              Narudžbe kupaca. <span className="text-alkus-700">Od PDF-a do Pantheona.</span>
            </h1>
            <p className="text-lg sm:text-xl text-slate-500 leading-relaxed mb-8 sm:mb-10 max-w-xl">
              Uvezite ili ručno unesite narudžbu za ALU i PVC stolariju. Pregledajte stavke, prenesite narudžbu u Pantheon i pitajte Lenu za rokove i specifikacije.
            </p>
            <div className="grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)] gap-3 sm:flex sm:flex-wrap sm:gap-4">
              <a href="#demo-narudzbe" className="px-2 min-[380px]:px-4 sm:px-8 py-3.5 sm:py-4 bg-slate-900 text-white rounded-2xl font-bold text-[13px] min-[380px]:text-sm sm:text-base whitespace-nowrap hover:bg-slate-800 transition-all flex items-center justify-center gap-2 group">
                Isprobaj unos
                <ArrowRight className="w-4 h-4 group-hover:translate-x-1 transition-transform" />
              </a>
              <a href="#cijena" className="px-2 min-[380px]:px-4 sm:px-8 py-3.5 sm:py-4 bg-white border border-slate-200 text-slate-900 rounded-2xl font-bold text-[13px] min-[380px]:text-sm sm:text-base whitespace-nowrap flex items-center justify-center gap-2 sm:gap-3 hover:border-slate-400 transition-all">
                <Coins className="w-5 h-5 text-slate-500" />
                4.900 KM
              </a>
            </div>
          </motion.div>

          <motion.div initial={{ opacity: 0, scale: 0.95 }} animate={{ opacity: 1, scale: 1 }} transition={{ duration: 0.8, delay: 0.2 }} className="relative">
            <div className="absolute -inset-4 bg-alkus-500/10 blur-3xl rounded-full" />
            <div className="relative bg-white rounded-[2.5rem] border border-slate-200 shadow-2xl overflow-hidden">
              <div className="flex items-center justify-between px-6 sm:px-8 py-5 border-b border-slate-100">
                <div className="flex items-center gap-3">
                  <span className="flex h-10 w-10 items-center justify-center rounded-2xl bg-alkus-600 text-white"><BrainCircuit className="h-5 w-5" /></span>
                  <div>
                    <p className="text-sm font-bold text-slate-900">Lena AI</p>
                    <p className="text-[11px] font-semibold text-slate-400">Orderflow.ai asistent</p>
                  </div>
                </div>
                <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-alkus-50 border border-alkus-100 text-alkus-700 text-[10px] font-bold uppercase tracking-wider">
                  <span className="w-1.5 h-1.5 rounded-full bg-alkus-500" />
                  Primjer
                </div>
              </div>
              <motion.div initial="hidden" animate="visible" className="space-y-4 p-6 sm:p-8">
                <UserBubble index={1}>Koje otvorene narudžbe kasne i koje dospijevaju u narednih sedam dana?</UserBubble>
                <LenaBubble index={2}>
                  <b>ALK-024</b> kasni od 23.11. <b>ALK-031</b> je otvorena, s rokom isporuke 30.11.
                </LenaBubble>
                <motion.div custom={3} variants={reveal} className="grid grid-cols-2 gap-2">
                  <div className="rounded-2xl border border-alkus-100 bg-alkus-50 p-3"><p className="text-[10px] font-black uppercase tracking-wider text-alkus-700">Kasni</p><p className="mt-1 text-xl font-bold text-slate-900">ALK-024</p></div>
                  <div className="rounded-2xl border border-alkus-100 bg-alkus-50 p-3"><p className="text-[10px] font-black uppercase tracking-wider text-alkus-700">Sljedeća isporuka</p><p className="mt-1 text-xl font-bold text-slate-900">ALK-031</p></div>
                </motion.div>
              </motion.div>
              <div className="border-t border-slate-100 bg-slate-50 px-6 sm:px-8 py-4">
                <p className="mb-2 text-[10px] font-black uppercase tracking-wider text-slate-400">Primjeri pitanja</p>
                <div className="flex flex-wrap gap-2">
                  {skillLinks.map((s) => (
                    <a key={s.id} href={`#${s.id}`} className={cn('rounded-full px-3 py-1.5 text-xs font-bold transition-opacity hover:opacity-80', gold.chip)}>{s.label}</a>
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
            <h2 className="text-4xl lg:text-5xl font-bold mb-6 tracking-tight text-slate-900">Orderflow.ai za Alkus</h2>
            <p className="text-slate-500 max-w-2xl mx-auto text-lg">
              Modul za narudžbe kupaca i prijenos u Pantheon, po uzoru na eNalogov tok rada.
            </p>
          </div>
          <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
            {included.map((item, i) => (
              <motion.div key={item.title} initial={{ opacity: 0, y: 20 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true }} transition={{ delay: i * 0.08 }} className="p-6 rounded-[1.75rem] border border-slate-200 bg-white hover:border-slate-300 transition-colors">
                <div className="w-12 h-12 rounded-2xl bg-alkus-50 text-alkus-600 flex items-center justify-center mb-5"><item.icon className="w-6 h-6" /></div>
                <h3 className="text-lg font-bold text-slate-900 mb-2">{item.title}</h3>
                <p className="text-sm text-slate-500 leading-relaxed">{item.desc}</p>
              </motion.div>
            ))}
          </div>
          <div className="mt-6 flex justify-center">
            <div className="inline-flex flex-wrap items-center justify-center gap-x-3 gap-y-1 px-5 py-3 rounded-2xl border bg-slate-50 border-slate-200 text-sm text-slate-900">
              <ShieldCheck className="w-4 h-4 shrink-0 text-slate-600" />
              <span className="font-bold">Sigurnost</span>
              <span className="text-slate-600">Lena prikazuje narudžbe kojima prijavljeni korisnik ima pristup.</span>
            </div>
          </div>
        </div>
      </section>

      <OrderflowDetails />

      {/* Skills */}
      <section id="vjestine" className="pt-28 bg-[#f8f5ee] scroll-mt-20 overflow-hidden">
        <span id="lara" className="block scroll-mt-24" />
        <div className="max-w-7xl mx-auto px-6">
          <div className="text-center mb-8">
            <span className="text-xs font-bold tracking-[0.3em] text-slate-400 uppercase mb-4 block">Četiri primjera upita</span>
            <h2 className="text-4xl lg:text-5xl font-bold mb-6 tracking-tight text-slate-900">Kako će izgledati razgovor s Lenom</h2>
            <p className="text-slate-500 max-w-2xl mx-auto text-lg">
              Isprobajte četiri razgovora s oglednim podacima. Odaberite stavku u rezultatu da suzite odgovor.
            </p>
            <a href="#tokeni" className="mt-5 inline-flex flex-wrap items-center justify-center gap-x-2 rounded-2xl border border-alkus-200 bg-alkus-50 px-4 py-2.5 text-sm text-alkus-700">
              <Sparkles className="h-4 w-4" aria-hidden="true" />
              <strong>20 besplatnih poruka dnevno</strong>
              <span>Od 21. poruke po 1 token (0,35 KM)</span>
            </a>
          </div>

          <div className="divide-y divide-slate-200">
            {scenarios.map((scenario, index) => (
              <div key={scenario.title}>
                <SkillSection
                  id={skillLinks[index].id}
                  flip={index % 2 === 1}
                  copy={<SkillCopy number={String(index + 1).padStart(2, '0')} title={scenario.title}
                    description={scenario.description} bullets={scenario.bullets} />}
                  visual={<ScenarioVisual scenario={scenario} />}
                />
              </div>
            ))}
          </div>
        </div>
      </section>

      <TokenDetails />

      {/* Pricing */}
      <section id="cijena" className="py-28 bg-white scroll-mt-20">
        <div className="max-w-5xl mx-auto px-6">
          <div className="text-center mb-14">
            <span className="text-xs font-bold tracking-[0.3em] text-slate-400 uppercase mb-4 block">Investicija</span>
            <h2 className="text-4xl lg:text-5xl font-bold tracking-tight text-slate-900">Orderflow.ai za ALKUS d.o.o.</h2>
          </div>
          <motion.div initial={{ opacity: 0, y: 20 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true }} className="grid md:grid-cols-[1.2fr_0.8fr] rounded-[2.5rem] overflow-hidden border border-slate-200 shadow-sm">
            <div className="bg-white p-8 lg:p-10">
              <h3 className="text-xl font-bold text-slate-900 mb-6">Uključeno u cijenu</h3>
              <ul className="space-y-3">
                {[
                  'Modul Narudžbe s PDF i ručnim unosom',
                  'Provjera i prijenos narudžbi u Pantheon',
                  'Prijava za do tri korisnika',
                  'Integracija Lena AI bez doplate i 20 besplatnih poruka dnevno',
                  'Četiri početna scenarija za narudžbe',
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
                <span className="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-alkus-200"><Database className="h-3.5 w-3.5" />Narudžbe + Pantheon + Lena AI</span>
                <p className="mt-6 text-5xl font-bold">4.900 KM</p>
                <p className="mt-2 text-sm font-medium uppercase tracking-widest text-slate-400">Fiksna cijena</p>
              </div>
              <p className="text-sm leading-relaxed text-alkus-200">Token košta 0,35 KM. Obrada dokumenata se obračunava po stranicama, a Lena AI od 21. poruke u danu troši 1 token po poruci.</p>
            </div>
          </motion.div>
        </div>
      </section>

      {/* Footer */}
      <footer className="py-20 bg-white border-t border-slate-100">
        <div className="max-w-7xl mx-auto px-6 flex flex-col md:flex-row justify-between items-center gap-8">
          <div className="flex items-center gap-4">
            <img src={alkusLogo} alt="Alkus" className="h-8 object-contain opacity-50 grayscale hover:grayscale-0 transition-all" referrerPolicy="no-referrer" />
            <div className="h-4 w-px bg-slate-200" />
            <span className="text-sm font-bold tracking-tight text-slate-400">Orderflow<span className="text-alkus-700">.ai</span></span>
          </div>
          <div className="flex flex-col md:flex-row items-center gap-4">
            <p className="text-slate-400 text-sm">© 2026 Sva prava zadržana. Ponuda napravljena od strane</p>
            <a href="https://qla.dev/" target="_blank" rel="noopener noreferrer" className="hover:opacity-100 transition-opacity opacity-80">
              <img src="https://deklarant.ai/build/images/logo-qla.png" alt="QLA Logo" className="h-6 object-contain" referrerPolicy="no-referrer" />
            </a>
          </div>
          <button type="button" onClick={() => window.print()} className="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold">
            <Printer className="h-4 w-4" /> Ispis / PDF
          </button>
        </div>
      </footer>
    </div>
  );
}
