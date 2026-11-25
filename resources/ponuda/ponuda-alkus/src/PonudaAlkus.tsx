/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { useState, type ReactNode } from 'react';
import {
  ArrowRight, BrainCircuit, CheckCircle2, Coins, Database, Download, FileScan, ShieldCheck, Sparkles, Users,
} from 'lucide-react';
import { motion } from 'motion/react';
import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';
import { OrderflowDetails, TokenDetails, scenarios } from './OrderflowDetails.tsx';
import { HeroInvoicePreview, type InvoiceDemoRequest } from './OrderDashboard.tsx';
import alkusLogo from './alkus-logo.png';
import offer from '../offer.json';

const proformaUrl = new URL('../predracun-alkus.pdf', import.meta.url).href;
const formatOfferAmount = (amount: number) => amount.toLocaleString('de-DE') + ' KM';
const offerTotal = formatOfferAmount(offer.items.reduce((sum, item) => sum + item.quantity * item.unitPrice, 0));

function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

function ProformaLink({ className }: { className: string }) {
  return (
    <a href={proformaUrl} download="Predracun-Orderflow-ALKUS.pdf" aria-label="Preuzmi predračun"
      className={cn('inline-flex shrink-0 items-center justify-center gap-2 whitespace-nowrap rounded-xl px-3 py-2 text-sm font-semibold print:hidden', className)}>
      <Download className="h-4 w-4" aria-hidden="true" />
      <span><span className="hidden sm:inline">Preuzmi </span>predračun</span>
    </a>
  );
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

const LaraBubble = ({ children, index = 1 }: { children: ReactNode; index?: number }) => (
  <motion.div custom={index} variants={reveal} className="w-fit max-w-[92%] sm:max-w-[75%]">
    <span className={cn('mb-1.5 flex items-center gap-2 text-xs font-black', gold.label)}>
      <BrainCircuit className="h-4 w-4" />Lara AI
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

const included = [
  { icon: FileScan, title: 'Modul Ulazne fakture', desc: 'Učitavanje skeniranog PDF-a ili fotografije, AI priprema nacrta i ručni unos faktura dobavljača.' },
  { icon: Database, title: 'Pantheon integracija', desc: 'Provjera dobavljača i artikala, upis fakture i povrat broja dokumenta.' },
  { icon: Users, title: 'Do 3 korisnika', desc: 'Zaseban korisnički račun i prijava za svakog člana tima.' },
  { icon: Sparkles, title: 'Lara AI bez doplate', desc: 'Uključena integracija i 20 besplatnih poruka dnevno. Svaka naredna poruka troši 1 token.' },
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
      <LaraBubble>{answer}</LaraBubble>
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
  const [demoRequest, setDemoRequest] = useState<InvoiceDemoRequest | null>(null);

  return (
    <div className="alkus-offer min-h-screen bg-[#f8f5ee] font-sans text-slate-900 selection:bg-alkus-100 selection:text-slate-900">
      {/* Navigation */}
      <nav className="sticky top-0 z-50 bg-white/80 backdrop-blur-md border-b border-slate-200">
        <div className="mx-auto flex h-20 max-w-7xl items-center gap-3 px-4 sm:gap-5 sm:px-6">
          <div className="flex shrink-0 items-center gap-2 sm:gap-4">
            <img src={alkusLogo} alt="Alkus" className="h-10 object-contain" referrerPolicy="no-referrer" />
            <div className="hidden h-6 w-px bg-slate-200 sm:block" />
            <span className="text-base font-bold tracking-tight sm:text-lg">Orderflow<span className="text-alkus-700">.ai</span> <span className="hidden text-alkus-600 xl:inline">· Lara AI</span></span>
          </div>
          <div className="ml-auto hidden items-center gap-5 lg:flex">
            <a href="#ukljuceno" className="text-sm font-medium text-slate-500 hover:text-slate-900 transition-colors">Šta je uključeno</a>
            <a href="#vjestine" className="text-sm font-medium text-slate-500 hover:text-slate-900 transition-colors">Lara AI</a>
            <a href="#modul" className="text-sm font-medium text-slate-500 hover:text-slate-900 transition-colors">Ulazne fakture</a>
            <a href="#pantheon" className="text-sm font-medium text-slate-500 hover:text-slate-900 transition-colors">Pantheon</a>
            <a href="#cijena" className="text-sm font-medium text-slate-500 hover:text-slate-900 transition-colors">Cijena</a>
            <div className="hidden h-6 w-px bg-slate-200 xl:block" />
            <span className="hidden whitespace-nowrap text-sm font-bold text-slate-900 xl:inline">Ukupno {offerTotal}</span>
          </div>
          <ProformaLink className="ml-auto bg-slate-900 text-white hover:bg-slate-800 lg:ml-0" />
        </div>
      </nav>

      {/* Hero */}
      <section className="pt-8 sm:pt-20 pb-20 sm:pb-28 px-6 overflow-hidden">
        <div className="mx-auto grid max-w-7xl items-center gap-10 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)] lg:gap-8">
          <motion.div initial={{ opacity: 0, x: -20 }} animate={{ opacity: 1, x: 0 }} transition={{ duration: 0.6 }}>
            <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-alkus-50 border border-alkus-100 text-alkus-700 text-xs font-bold uppercase tracking-wider mb-6">
              <BrainCircuit className="w-3.5 h-3.5" />
              Ponuda za ALKUS d.o.o. · Kreševo
            </div>
            <h1 className="mb-6 text-4xl font-bold leading-[1.1] tracking-tight text-slate-900 sm:mb-8 sm:text-6xl lg:text-5xl xl:text-[3.5rem]">
              Ulazne fakture. <span className="block text-balance text-alkus-700">Od skeniranog PDF-a do Pantheona.</span>
            </h1>
            <p className="text-lg sm:text-xl text-slate-500 leading-relaxed mb-8 sm:mb-10 max-w-xl">
              Učitajte skenirani PDF ili fotografiju papirne fakture dobavljača. AI izdvaja artikle, količine i cijene, a vaš tim provjerava podatke i potvrđuje upis u Pantheon.
            </p>
            <div className="grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)] gap-3 sm:flex sm:flex-wrap sm:gap-4">
              <a href="#demo-narudzbe" className="px-2 min-[380px]:px-4 sm:px-8 py-3.5 sm:py-4 bg-slate-900 text-white rounded-2xl font-bold text-[13px] min-[380px]:text-sm sm:text-base whitespace-nowrap hover:bg-slate-800 transition-all flex items-center justify-center gap-2 group">
                Isprobaj unos
                <ArrowRight className="w-4 h-4 group-hover:translate-x-1 transition-transform" />
              </a>
              <a href="#cijena" className="px-2 min-[380px]:px-4 sm:px-8 py-3.5 sm:py-4 bg-white border border-slate-200 text-slate-900 rounded-2xl font-bold text-[13px] min-[380px]:text-sm sm:text-base whitespace-nowrap flex items-center justify-center gap-2 sm:gap-3 hover:border-slate-400 transition-all">
                <Coins className="w-5 h-5 text-slate-500" />
                {offerTotal}
              </a>
            </div>
          </motion.div>

          <motion.div initial={{ opacity: 0, scale: 0.97 }} animate={{ opacity: 1, scale: 1 }}
            transition={{ duration: 0.65, delay: 0.12 }} className="relative min-w-0">
            <div className="pointer-events-none absolute -inset-4 rounded-full bg-alkus-500/10 blur-3xl" />
            <div className="relative"><HeroInvoicePreview onDemoRequest={setDemoRequest} /></div>
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
              Modul za unos ulaznih faktura dobavljača i prijenos u Pantheon. Uključena je optimizacija za pet dobavljača na osnovu uzoraka njihovih faktura.
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
              <span className="text-slate-600">Lara prikazuje fakture kojima prijavljeni korisnik ima pristup.</span>
            </div>
          </div>
        </div>
      </section>

      <OrderflowDetails demoRequest={demoRequest} />

      {/* Skills */}
      <section id="vjestine" className="pt-28 bg-[#f8f5ee] scroll-mt-20 overflow-hidden">
        <span id="lara" className="block scroll-mt-24" />
        <div className="max-w-7xl mx-auto px-6">
          <div className="text-center mb-8">
            <span className="text-xs font-bold tracking-[0.3em] text-slate-400 uppercase mb-4 block">Četiri primjera upita</span>
            <h2 className="text-4xl lg:text-5xl font-bold mb-6 tracking-tight text-slate-900">Kako će izgledati razgovor s Larom</h2>
            <p className="text-slate-500 max-w-2xl mx-auto text-lg">
              Isprobajte četiri razgovora s oglednim podacima. Odaberite stavku u rezultatu da suzite odgovor.
            </p>
            <a href="#tokeni" className="mt-5 inline-flex flex-wrap items-center justify-center gap-x-2 rounded-2xl border border-alkus-200 bg-alkus-50 px-4 py-2.5 text-sm text-alkus-700">
              <Sparkles className="h-4 w-4" aria-hidden="true" />
              <strong>20 besplatnih poruka dnevno</strong>
              <span>Od 21. poruke po 1 token</span>
            </a>
          </div>

          <div className="divide-y divide-slate-200">
            {scenarios.map((scenario, index) => (
              <div key={scenario.title}>
                <SkillSection
                  id={'vjestina-' + (index + 1)}
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
              <h3 className="text-xl font-bold text-slate-900 mb-3">Stavke ponude</h3>
              <p className="mb-5 text-sm leading-relaxed text-slate-600">Ulazne fakture iz PDF-a ili ručnog unosa, prijenos u Pantheon i prijava za do tri korisnika.</p>
              <ul className="space-y-3">
                {offer.items.map(({ description, unitPrice }) => (
                  <li key={description} className="flex items-start gap-2 text-sm text-slate-600 sm:gap-3">
                    <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-alkus-700" aria-hidden="true" />
                    <span className="flex-1 leading-relaxed">{description}</span>
                    <span className="shrink-0 whitespace-nowrap font-semibold text-slate-900">
                      {unitPrice === 0 ? '0 KM' : formatOfferAmount(unitPrice)}
                    </span>
                  </li>
                ))}
              </ul>
            </div>
            <div className="bg-slate-900 text-white p-8 lg:p-10 flex flex-col justify-between gap-8">
              <div>
                <span className="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-alkus-200"><Database className="h-3.5 w-3.5" />Ulazne fakture + Pantheon + Lara AI</span>
                <p className="mt-6 text-5xl font-bold">{offerTotal}</p>
                <p className="mt-2 text-sm font-medium uppercase tracking-widest text-slate-400">Fiksna cijena</p>
              </div>
              <div className="space-y-3 text-sm leading-relaxed text-alkus-200">
                <p><strong className="text-white">Rok isporuke</strong> · {offer.delivery} od potvrde ponude</p>
                <p>{offer.paymentConditions}</p>
                <a className="underline" href="#tokeni">Cjenovnik tokena</a>
              </div>
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
          <ProformaLink className="border border-slate-200 hover:bg-slate-50" />
        </div>
      </footer>
    </div>
  );
}
