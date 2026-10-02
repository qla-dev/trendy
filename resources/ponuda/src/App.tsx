/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React, { useState } from 'react';
import { 
  LayoutDashboard, 
  Package, 
  QrCode, 
  Settings, 
  CheckCircle2, 
  Clock, 
  Smartphone, 
  ArrowRight, 
  ChevronRight,
  Zap,
  ShieldCheck,
  BarChart3,
  Factory,
  Database,
  SmartphoneIcon,
  ArrowRightLeft,
  FileText,
  Truck,
  ClipboardCheck,
  RotateCcw,
  BellRing,
  Gauge,
  Users,
  TrendingUp,
  Wrench,
  Target,
  Globe,
  Lightbulb,
  Nfc,
  IdCardLanyard,
  KeyRound,
  LogIn,
  Mail
} from 'lucide-react';
import { motion, AnimatePresence } from 'motion/react';
import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';
const trendyCardsUrl = new URL('../assets/trendy-cards.png', import.meta.url).href;

function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

const PhaseCard = ({ 
  number, 
  title, 
  duration, 
  price, 
  items, 
  delay = 0
}: { 
  number: string; 
  title: string; 
  duration?: string;
  price: string; 
  items: string[];
  delay?: number;
}) => (
  <motion.div 
    initial={{ opacity: 0, y: 20 }}
    whileInView={{ opacity: 1, y: 0 }}
    viewport={{ once: true }}
    transition={{ delay, duration: 0.5 }}
    className="bg-white rounded-3xl p-6 xl:p-8 border border-slate-200 shadow-sm hover:shadow-md transition-all group h-full min-w-0 flex flex-col"
  >
    <div className="flex flex-col items-start gap-4 mb-6">
      <div>
        <span className="text-xs font-bold tracking-widest text-slate-500 uppercase mb-2 block">{number}</span>
        <h3 className="text-xl xl:text-2xl font-semibold text-slate-900">{title}</h3>
      </div>
      {duration && (
        <div className="bg-slate-50 px-4 py-2 rounded-2xl border border-slate-100 self-start">
          <div className="flex items-center gap-2 text-slate-500 text-sm font-medium whitespace-nowrap">
            <Clock className="w-4 h-4" />
            {duration}
          </div>
        </div>
      )}
    </div>
    
    <ul className="space-y-3 mb-8 flex-1">
      {items.map((item, idx) => (
        <li key={idx} className="flex items-start gap-3 text-slate-600">
          <CheckCircle2 className="w-5 h-5 text-slate-900 mt-0.5 shrink-0" />
          <span className="text-sm leading-relaxed">
            {item}
          </span>
        </li>
      ))}
    </ul>
    
    <div className="pt-6 border-t border-slate-100 flex flex-wrap justify-between items-center gap-3">
      <span className="text-slate-400 text-sm font-medium uppercase tracking-wider">Investicija</span>
      <span className="text-2xl font-bold text-slate-900">{price} <span className="text-sm font-medium text-slate-400">KM</span></span>
    </div>
  </motion.div>
);

const ProcessStep = ({ icon: Icon, title, subtitle, description, isLast }: { icon: any, title: string, subtitle?: string, description: string, isLast?: boolean }) => (
  <div className="relative flex flex-col items-center text-center group">
    {!isLast && (
      <div className="hidden lg:block absolute top-12 left-[60%] w-[80%] h-px border-t-2 border-dashed border-slate-200 -z-10" />
    )}
    <div className="w-24 h-24 bg-white rounded-[2rem] border border-slate-200 shadow-sm flex items-center justify-center mb-6 group-hover:border-slate-900 group-hover:shadow-lg transition-all duration-500">
      <Icon className="w-10 h-10 text-slate-900" />
    </div>
    <h4 className="text-lg font-bold text-slate-900 mb-1">{title}</h4>
    {subtitle && <p className="text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">{subtitle}</p>}
    <p className="text-sm text-slate-500 max-w-[200px]">{description}</p>
  </div>
);

type FlowStep = {
  title: string;
  desc: string;
  icon: any;
  tags?: string[];
  badge?: { icon: any; label: string };
  active?: boolean;
  success?: boolean;
};

const noteTones = {
  amber: { box: "bg-amber-50 border-amber-100 text-amber-900", icon: "text-amber-600", text: "text-amber-700" },
  emerald: { box: "bg-emerald-50 border-emerald-100 text-emerald-900", icon: "text-emerald-600", text: "text-emerald-700" },
  slate: { box: "bg-slate-50 border-slate-200 text-slate-900", icon: "text-slate-600", text: "text-slate-600" }
};

const FlowBlock = ({
  phase,
  eyebrow,
  title,
  subtitle,
  steps,
  note,
  live
}: {
  phase: string;
  eyebrow: string;
  title: string;
  subtitle: string;
  steps: FlowStep[];
  note: { tone: keyof typeof noteTones; icon: any; label: string; text: string };
  live: { eyebrow: string; title: string; items: { icon: any; title: string; desc: string }[] };
}) => {
  const tone = noteTones[note.tone];
  return (
    <div>
      <div className="text-center mb-20">
        <motion.div
          initial={{ opacity: 0, y: 10 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true }}
        >
          <div className="flex items-center justify-center gap-3 mb-4">
            <span className="px-3 py-1 rounded-full bg-slate-900 text-white text-xs font-bold uppercase tracking-wider">{phase}</span>
            <span className="text-xs font-bold tracking-[0.3em] text-slate-400 uppercase">{eyebrow}</span>
          </div>
          <h2 className="text-4xl lg:text-5xl font-bold mb-6 tracking-tight text-slate-900">{title}</h2>
          <p className="text-slate-500 max-w-2xl mx-auto text-lg">{subtitle}</p>
        </motion.div>
      </div>

      <div className="grid sm:grid-cols-2 lg:grid-cols-6 gap-4 xl:gap-5">
        {steps.map((item, idx) => (
          <motion.div
            key={idx}
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ delay: idx * 0.08 }}
            className="relative"
          >
            <div className={cn(
              "p-6 rounded-[1.75rem] border transition-all duration-500 h-full flex flex-col items-center text-center",
              item.active ? "bg-slate-900 text-white border-slate-900 shadow-xl" :
              item.success ? "bg-emerald-50 border-emerald-100 text-slate-900" :
              "bg-white border-slate-200 text-slate-900 hover:border-slate-300"
            )}>
              <div className={cn(
                "w-12 h-12 rounded-2xl flex items-center justify-center mb-5",
                item.active ? "bg-white text-slate-900" :
                item.success ? "bg-white text-emerald-600" :
                "bg-slate-50 text-slate-500"
              )}>
                <item.icon className="w-6 h-6" />
              </div>

              <span className={cn(
                "text-[10px] font-black uppercase tracking-[0.2em] mb-2",
                item.active ? "text-slate-500" : "text-slate-400"
              )}>Korak {String(idx + 1).padStart(2, "0")}</span>

              <h3 className="text-lg font-bold mb-2 leading-tight">{item.title}</h3>
              <p className={cn(
                "text-sm leading-relaxed flex-1",
                item.active ? "text-slate-300" : "text-slate-500"
              )}>{item.desc}</p>

              {item.tags && (
                <div className="flex gap-2 mt-4">
                  {item.tags.map(t => (
                    <span key={t} className="px-2 py-1 bg-slate-50 text-[9px] font-bold uppercase rounded-md border border-slate-100 text-slate-500">{t}</span>
                  ))}
                </div>
              )}

              {item.badge && (
                <div className={cn(
                  "mt-4 inline-flex items-center gap-1.5 px-3 py-1 rounded-full border text-[9px] font-bold uppercase",
                  item.active ? "bg-white/10 border-white/10 text-slate-300" :
                  item.success ? "bg-white border-emerald-100 text-emerald-700" :
                  "bg-slate-50 border-slate-100 text-slate-500"
                )}>
                  <item.badge.icon className="w-3 h-3" />
                  {item.badge.label}
                </div>
              )}
            </div>

            {idx < steps.length - 1 && (
              <div className="hidden lg:flex absolute top-1/2 -right-[0.85rem] xl:-right-[1rem] -translate-y-1/2 z-10 w-6 h-6 rounded-full bg-white border border-slate-200 items-center justify-center text-slate-400">
                <ChevronRight className="w-3.5 h-3.5" />
              </div>
            )}
          </motion.div>
        ))}
      </div>

      <div className="mt-6 flex justify-center">
        <div className={cn("inline-flex flex-wrap items-center justify-center gap-x-3 gap-y-1 px-5 py-3 rounded-2xl border text-sm", tone.box)}>
          <note.icon className={cn("w-4 h-4 shrink-0", tone.icon)} />
          <span className="font-bold">{note.label}</span>
          <span className={tone.text}>{note.text}</span>
        </div>
      </div>

      <motion.div
        initial={{ opacity: 0, y: 20 }}
        whileInView={{ opacity: 1, y: 0 }}
        viewport={{ once: true }}
        className="mt-12 p-8 lg:p-10 bg-slate-50 rounded-[2.5rem] border border-slate-200"
      >
        <div className="flex flex-col lg:flex-row lg:items-center gap-8">
          <div className="lg:w-1/4 shrink-0">
            <span className="text-xs font-bold tracking-[0.3em] text-slate-400 uppercase mb-2 block">{live.eyebrow}</span>
            <h3 className="text-2xl font-bold text-slate-900 leading-tight">{live.title}</h3>
          </div>
          <div className="grid sm:grid-cols-3 gap-4 flex-1">
            {live.items.map((f) => (
              <div key={f.title} className="p-5 bg-white rounded-2xl border border-slate-100 flex gap-4">
                <div className="w-10 h-10 bg-slate-900 text-white rounded-xl flex items-center justify-center shrink-0">
                  <f.icon className="w-5 h-5" />
                </div>
                <div>
                  <h4 className="text-sm font-bold text-slate-900 mb-1">{f.title}</h4>
                  <p className="text-xs text-slate-500 leading-relaxed">{f.desc}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </motion.div>
    </div>
  );
};

export default function App() {
  const [activeStep, setActiveStep] = useState(0);
  const ponudaPdfUrl = new URL('../assets/ponuda-qla-dev_trendy.pdf', import.meta.url).href;
  const predracunPdfUrl = new URL('../assets/predracun-qla-dev_trendy.pdf', import.meta.url).href;

  const steps = [
    { icon: ShieldCheck, label: "Ulazna kontrola", status: "Ulazna kontrola", detail: "Provjera materijala prije početka operacija" },
    { icon: Wrench, label: "Dorada", status: "Dorada po potrebi", detail: "Otklanjanje nedostataka utvrđenih ulaznom kontrolom" },
    { icon: QrCode, label: "Operacije", status: "Skeniranje RN-a", detail: "Početak, Zastoj, Kraj operacije" },
    { icon: CheckCircle2, label: "Zatvaranje", status: "Završen ciklus", detail: "Automatsko zatvaranje RN-a" }
  ];

  React.useEffect(() => {
    const interval = setInterval(() => {
      setActiveStep((prev) => (prev + 1) % steps.length);
    }, 3000);
    return () => clearInterval(interval);
  }, []);

  return (
    <div className="min-h-screen bg-[#F8FAFC] font-sans text-slate-900 selection:bg-slate-200 selection:text-slate-900">
      {/* Print Header (Only visible in PDF/Print) */}
      <div className="hidden print:block mb-12 border-b border-slate-200 pb-8">
        <div className="flex items-center justify-between">
          <img 
            src="https://enalog.app/images/logo/TrendyCNC.png" 
            alt="Trendy CNC Logo" 
            className="h-12 object-contain"
            referrerPolicy="no-referrer"
          />
          <div className="text-right">
            <h2 className="text-xl font-bold">Ponuda za Nadogradnju</h2>
            <p className="text-sm text-slate-500">enalog.app ekosistem</p>
          </div>
        </div>
      </div>

      {/* Navigation */}
      <nav className="sticky top-0 z-50 bg-white/80 backdrop-blur-md border-b border-slate-200">
        <div className="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
          <div className="flex items-center gap-4">
            <img 
              src="https://enalog.app/images/logo/TrendyCNC.png" 
              alt="Trendy CNC Logo" 
              className="h-10 object-contain"
              referrerPolicy="no-referrer"
            />
            <div className="h-6 w-px bg-slate-200 hidden md:block" />
            <span className="text-lg font-bold tracking-tight hidden md:block">eNalog<span className="text-slate-500">.app</span></span>
          </div>
          <span className="md:hidden text-lg font-bold tracking-tight">eNalog<span className="text-slate-500">.app</span></span>
          <div className="hidden md:flex items-center gap-8">
            <a href="#faze" className="text-sm font-medium text-slate-500 hover:text-slate-900 transition-colors">Faze Projekta</a>
            <a href="#proces" className="text-sm font-medium text-slate-500 hover:text-slate-900 transition-colors">Proces</a>
            <a href="#mobilna" className="text-sm font-medium text-slate-500 hover:text-slate-900 transition-colors">Mobilna App</a>
            <div className="h-6 w-px bg-slate-200" />
            <span className="text-sm font-bold text-slate-900">Ukupno: 14.700 KM</span>
            <div className="flex items-center gap-3 print:hidden">
              <a
                href={ponudaPdfUrl}
                target="_blank"
                rel="noopener noreferrer"
                download="ponuda-qla-dev_trendy.pdf"
                className="px-4 py-2 bg-slate-900 text-white text-xs font-bold rounded-xl hover:bg-slate-800 transition-all flex items-center gap-2 cursor-pointer relative z-[100]"
              >
                <FileText className="w-3.5 h-3.5" />
                Preuzmi ponudu
              </a>
              <a
                href={predracunPdfUrl}
                target="_blank"
                rel="noopener noreferrer"
                download="predracun-qla-dev_trendy.pdf"
                className="px-4 py-2 bg-white border border-slate-200 text-slate-900 text-xs font-bold rounded-xl hover:border-slate-400 transition-all flex items-center gap-2 cursor-pointer relative z-[100]"
              >
                <FileText className="w-3.5 h-3.5" />
                Preuzmi predračun
              </a>
            </div>
          </div>
        </div>
      </nav>

      {/* Hero Section */}
      <section className="pt-8 sm:pt-20 pb-20 sm:pb-32 px-6 overflow-hidden">
        <div className="max-w-7xl mx-auto grid lg:grid-cols-2 gap-16 items-center">
          <motion.div
            initial={{ opacity: 0, x: -20 }}
            animate={{ opacity: 1, x: 0 }}
            transition={{ duration: 0.6 }}
          >
            <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-100 border border-slate-200 text-slate-700 text-xs font-bold uppercase tracking-wider mb-6">
              <ShieldCheck className="w-3.5 h-3.5" />
              Upgrade Ponuda 2026
            </div>
            <h1 className="text-[2.75rem] min-[375px]:text-5xl sm:text-6xl lg:text-7xl font-bold tracking-tight text-slate-900 mb-6 sm:mb-8 leading-[1.1]">
              Digitalna transformacija <span className="text-slate-400">proizvodnje.</span>
            </h1>
            <p className="text-lg sm:text-xl text-slate-500 leading-relaxed mb-8 sm:mb-10 max-w-xl">
              Nadogradnja web aplikacije za digitalni tok proizvodnje, automatizaciju procesa i inteligentnu analizu poslovanja.
            </p>
            <div className="grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)] gap-3 sm:flex sm:flex-wrap sm:gap-4">
              <a href="#faze" className="px-2 min-[380px]:px-4 sm:px-8 py-3.5 sm:py-4 bg-slate-900 text-white rounded-2xl font-bold text-[13px] min-[380px]:text-sm sm:text-base whitespace-nowrap hover:bg-slate-800 transition-all flex items-center justify-center gap-2 group">
                Pogledaj Faze
                <ArrowRight className="w-4 h-4 group-hover:translate-x-1 transition-transform" />
              </a>
              <a href="#roi" className="px-2 min-[380px]:px-4 sm:px-8 py-3.5 sm:py-4 bg-white border border-slate-200 text-slate-900 rounded-2xl font-bold text-[13px] min-[380px]:text-sm sm:text-base whitespace-nowrap flex items-center justify-center gap-2 sm:gap-3 hover:border-slate-400 transition-all">
                <BarChart3 className="hidden sm:block w-5 h-5 text-slate-500" />
                ROI Fokusiran Dizajn
              </a>
            </div>
          </motion.div>

          <motion.div
            initial={{ opacity: 0, scale: 0.95 }}
            animate={{ opacity: 1, scale: 1 }}
            transition={{ duration: 0.8, delay: 0.2 }}
            className="relative"
          >
            <div className="absolute -inset-4 bg-slate-500/5 blur-3xl rounded-full" />
            <div className="relative bg-white rounded-[2.5rem] border border-slate-200 shadow-2xl p-6 sm:p-8 lg:p-10 overflow-hidden">
              <div className="flex items-center justify-between mb-10">
                <div>
                  <p className="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Faza II</p>
                  <h4 className="text-lg font-bold text-slate-900">Tok radnog naloga</h4>
                </div>
                <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-100 text-emerald-700 text-[10px] font-bold uppercase tracking-wider">
                  <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse" />
                  Uživo
                </div>
              </div>

              <div className="relative flex justify-between items-start [--flow-inset:3rem] sm:[--flow-inset:4rem]">
                <div className="absolute top-6 sm:top-8 left-6 sm:left-8 right-6 sm:right-8 h-0.5 bg-slate-100" />
                <motion.div
                  className="absolute top-6 sm:top-8 left-6 sm:left-8 h-0.5 bg-slate-900"
                  animate={{ width: `calc((100% - var(--flow-inset)) * ${activeStep / (steps.length - 1)})` }}
                  transition={{ duration: 0.5 }}
                />
                {steps.map((step, idx) => (
                  <div key={idx} className="relative flex flex-col items-center gap-3 w-12 sm:w-16">
                    <div className={cn(
                      "w-12 h-12 sm:w-16 sm:h-16 rounded-2xl flex items-center justify-center transition-all duration-500",
                      idx === activeStep ? "bg-slate-900 text-white shadow-lg shadow-slate-200" :
                      idx < activeStep ? "bg-white border border-slate-900 text-slate-900" :
                      "bg-slate-100 text-slate-400"
                    )}>
                      <step.icon className="w-5 h-5 sm:w-7 sm:h-7" />
                    </div>
                    <span className={cn(
                      "text-[8px] sm:text-[10px] font-bold uppercase tracking-wider text-center whitespace-nowrap",
                      idx <= activeStep ? "text-slate-900" : "text-slate-400"
                    )}>{step.label}</span>
                  </div>
                ))}
              </div>

              <div className="mt-12 p-6 bg-slate-50 rounded-3xl border border-slate-100">
                <div className="flex items-center gap-4 mb-4">
                  <div className="w-12 h-12 bg-white rounded-2xl flex items-center justify-center shadow-sm shrink-0">
                    {React.createElement(steps[activeStep].icon, { className: "text-slate-900 w-6 h-6" })}
                  </div>
                  <AnimatePresence mode="wait">
                    <motion.div
                      key={activeStep}
                      initial={{ opacity: 0, y: 6 }}
                      animate={{ opacity: 1, y: 0 }}
                      exit={{ opacity: 0, y: -6 }}
                      transition={{ duration: 0.25 }}
                    >
                      <p className="text-xs font-bold text-slate-400 uppercase tracking-widest">{steps[activeStep].status}</p>
                      <p className="text-sm font-bold text-slate-900">{steps[activeStep].detail}</p>
                    </motion.div>
                  </AnimatePresence>
                </div>
                <div className="w-full h-2 bg-slate-200 rounded-full overflow-hidden">
                  <motion.div
                    className="h-full bg-slate-900"
                    animate={{ width: `${((activeStep + 1) / steps.length) * 100}%` }}
                    transition={{ duration: 0.5 }}
                  />
                </div>
              </div>
            </div>
            <motion.div
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.4 }}
              className="mt-6 grid grid-cols-2 sm:grid-cols-4 gap-3"
            >
              {[
                { label: "Faza II", value: "Automatizacija" },
                { label: "Faza III", value: "Analitika" },
                { label: "Faza IV", value: "RFID prijava" },
                { label: "Native app", value: "Besplatno", highlight: true }
              ].map((item) => (
                <div
                  key={item.label}
                  className={cn(
                    "p-3 sm:p-4 rounded-2xl border",
                    item.highlight ? "bg-emerald-50 border-emerald-100" : "bg-white border-slate-200"
                  )}
                >
                  <p className={cn(
                    "text-[9px] sm:text-[10px] font-bold uppercase tracking-widest mb-1",
                    item.highlight ? "text-emerald-600" : "text-slate-400"
                  )}>{item.label}</p>
                  <p className={cn(
                    "text-xs sm:text-sm font-bold",
                    item.highlight ? "text-emerald-800" : "text-slate-900"
                  )}>{item.value}</p>
                </div>
              ))}
            </motion.div>
          </motion.div>
        </div>
      </section>

      {/* Process Section: one flow per phase */}
      <section id="proces" className="py-32 bg-white overflow-hidden">
        <div className="max-w-7xl mx-auto px-6">
          <FlowBlock
            phase="Faza II"
            eyebrow="Operativni Model"
            title="Digitalni Tok Proizvodnje"
            subtitle="Od prenosa materijala do zatvaranja radnog naloga, svaki korak se skenira, kontroliše i vidi u realnom vremenu."
            steps={[
              {
                title: "Ulazna kontrola",
                desc: "Odluka „dozvoljava / ne dozvoljava“ prije početka operacije.",
                icon: ShieldCheck,
                badge: { icon: ClipboardCheck, label: "Kontrola" }
              },
              {
                title: "Procesna kontrola",
                desc: "Neispravni komadi se vraćaju ili idu na doradu.",
                icon: ClipboardCheck,
                badge: { icon: RotateCcw, label: "Dorada" }
              },
              {
                title: "Operacije",
                desc: "Skeniranjem RN-a: Početak, Zastoj, Kraj i Kraj operacije.",
                icon: QrCode,
                active: true,
                badge: { icon: QrCode, label: "QR" }
              },
              {
                title: "Evidencija radnika",
                desc: "Pregled radnika i evidencija njihovog rada u proizvodnji.",
                icon: Users,
                tags: ["Evidencija"]
              },
              {
                title: "Detekcija kašnjenja",
                desc: "Automatsko praćenje kašnjenja radnih naloga.",
                icon: ArrowRightLeft,
                badge: { icon: FileText, label: "RN" }
              },
              {
                title: "Zatvaranje",
                desc: "Izdavanje materijala i automatsko zatvaranje RN-a.",
                icon: CheckCircle2,
                success: true,
                badge: { icon: FileText, label: "Dokument" }
              }
            ]}
            note={{
              tone: "amber",
              icon: RotateCcw,
              label: "Ne dozvoljava",
              text: "komad se vraća na operaciju ili ide na doradu, uz evidenciju naplate i prateću dokumentaciju."
            }}
            live={{
              eyebrow: "Kroz cijeli tok",
              title: "Uživo za menadžment",
              items: [
                { icon: Users, title: "Panel resursa", desc: "Radnici, mašine, kapaciteti i efikasnost." },
                { icon: BellRing, title: "Kašnjenja RN-a", desc: "Automatsko praćenje i operativne notifikacije." },
                { icon: Gauge, title: "Live Manager Analytics", desc: "Stanje proizvodnje u realnom vremenu." }
              ]
            }}
          />

          <div className="my-32 h-px bg-slate-100" />

          <FlowBlock
            phase="Faza III"
            eyebrow="Analitički Model"
            title="Od Podataka do Odluke"
            subtitle="Podaci iz proizvodnje, skladišta i rada se pretvaraju u analize, prognoze i jasne poslovne odluke."
            steps={[
              {
                title: "Inventura",
                desc: "QR evidencija i usklađivanje stvarnih količina.",
                icon: Package,
                badge: { icon: QrCode, label: "QR" }
              },
              {
                title: "Prijava radnika",
                desc: "Stvarno vrijeme rada i učinak po radniku.",
                icon: Users,
                badge: { icon: Clock, label: "Evidencija" }
              },
              {
                title: "Uska grla",
                desc: "Čekanja, zastoji i uzroci gubitaka u proizvodnji.",
                icon: Gauge,
                badge: { icon: BarChart3, label: "Analiza" }
              },
              {
                title: "Profitabilnost",
                desc: "Po proizvodu, kupcu, RN-u i proizvodnom satu.",
                icon: BarChart3,
                active: true,
                badge: { icon: TrendingUp, label: "Miks" }
              },
              {
                title: "Mašine i ulaganja",
                desc: "KPI, kvarovi, održavanje, remont i povrat investicije.",
                icon: Wrench,
                badge: { icon: BarChart3, label: "ROI" }
              },
              {
                title: "Planiranje",
                desc: "Prediktivne potrebe za materijalom i buduća potražnja.",
                icon: Target,
                success: true,
                badge: { icon: TrendingUp, label: "Prognoza" }
              }
            ]}
            note={{
              tone: "emerald",
              icon: RotateCcw,
              label: "Povratna veza",
              text: "zaključci analize se vraćaju u plan materijala, raspored mašina i radnika iz Faze II."
            }}
            live={{
              eyebrow: "Šira slika",
              title: "Kontekst za odluke",
              items: [
                { icon: Target, title: "Industrijski benchmark", desc: "Ključni pokazatelji naspram referentnih vrijednosti." },
                { icon: Lightbulb, title: "Podrška odlučivanju", desc: "Preporuke na osnovu stvarnih podataka iz pogona." },
                { icon: BarChart3, title: "Detaljna analiza", desc: "Uska grla, profitabilnost i isplativost ulaganja." }
              ]
            }}
          />
          <div className="my-32 h-px bg-slate-100" />

          <div className="mb-12 grid md:grid-cols-2 items-center gap-8 rounded-[2rem] border border-slate-200 bg-slate-50 p-6 sm:p-10">
            <div>
              <span className="inline-flex items-center gap-2 rounded-full border border-emerald-100 bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">
                <Nfc className="h-4 w-4" /> Faza IV · Postojeće Trendy kartice
              </span>
              <h3 className="mt-5 text-2xl sm:text-3xl font-bold text-slate-900">Vaša kartica, nova namjena</h3>
              <p className="mt-4 text-slate-500 leading-relaxed">
                Koristimo Trendy kartice koje radnici već imaju. Povezujemo ih s evidencijom radnika iz Pantheona za prijavu, evidenciju dolaska i odlaska te potvrdu rada na operacijama.
              </p>
              <p className="mt-4 flex items-center gap-2 text-sm font-semibold text-emerald-700">
                <CheckCircle2 className="h-4 w-4 shrink-0" /> Bez izdavanja novih kartica
              </p>
            </div>
            <img src={trendyCardsUrl} alt="Postojeće Trendy CNC kartice za identifikaciju radnika u Fazi IV" className="mx-auto w-full max-w-md object-contain" loading="lazy" width="1536" height="1024" />
          </div>

          <FlowBlock
            phase="Faza IV"
            eyebrow="RFID Model"
            title="Prijava Jednim Dodirom"
            subtitle="Radnik prisloni postojeću RFID karticu i sistem zna ko radi, bez korisničkog imena i lozinke, na svakom uređaju u pogonu."
            steps={[
              {
                title: "Postojeće kartice",
                desc: "Postojeće Trendy kartice povezuju se s radnikom iz Pantheona.",
                icon: IdCardLanyard,
                badge: { icon: Users, label: "Radnik" }
              },
              {
                title: "Čitač na mjestu rada",
                desc: "RFID čitač na tabletu ili računaru, NFC u native aplikaciji.",
                icon: Nfc,
                badge: { icon: Smartphone, label: "USB / NFC" }
              },
              {
                title: "Dolazak i odlazak",
                desc: "Dolazak, pauza i odlazak bilježe se jednim prislanjanjem.",
                icon: LogIn,
                badge: { icon: Clock, label: "Evidencija" }
              },
              {
                title: "Operacije",
                desc: "Kartica i RN: Početak, Zastoj i Kraj na ime radnika.",
                icon: QrCode,
                active: true,
                badge: { icon: Nfc, label: "RFID" }
              },
              {
                title: "Potvrde",
                desc: "Izdavanje materijala, kontrola i dorada potvrđeni karticom.",
                icon: ClipboardCheck,
                badge: { icon: FileText, label: "Potpis" }
              },
              {
                title: "Industrijski newsletter",
                desc: "Praćenje trendova u mašinskoj industriji kroz sedmični pregled tehnologija i poslovnih prilika.",
                icon: Mail,
                success: true,
                badge: { icon: Globe, label: "Trendovi" }
              }
            ]}
            note={{
              tone: "slate",
              icon: KeyRound,
              label: "Sigurnost",
              text: "ako prijavljen uređaj ostane neaktivan, zaključava se i traži 4-cifreni PIN, pa niko ne može raditi na tuđe ime."
            }}
            live={{
              eyebrow: "Bez lozinki",
              title: "Gdje kartica mijenja prijavu",
              items: [
                { icon: Smartphone, title: "Dijeljeni tableti", desc: "Više radnika na jednom uređaju, bez odjave i ponovne prijave." },
                { icon: Package, title: "Inventura", desc: "Svako brojanje vezano je za radnika koji ga je uradio." },
                { icon: BarChart3, title: "Tačan učinak", desc: "Vrijeme i rad po osobi, a ne po odjeljenju." }
              ]
            }}
          />
        </div>
      </section>

      {/* Phases Section */}
      <section id="faze" className="py-32 bg-slate-50">
        <div className="max-w-7xl mx-auto px-6">
          <div className="text-center mb-20">
            <h2 className="text-4xl font-bold text-slate-900 mb-4">Struktura Nadogradnje</h2>
            <p className="text-slate-500 max-w-2xl mx-auto">
              Faze II, III i IV povezuju automatizaciju proizvodnog toka, analitiku za poslovno odlučivanje i prijavu radnika RFID karticama.
            </p>
          </div>

          <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6 xl:gap-8">
            <PhaseCard 
              number="Faza II"
              title="Automatizacija proizvodnog toka i kontrola operacija"
              duration="1.5 mj."
              price="4.900"
              items={[
                "Digitalni tok radnih naloga kroz proizvodne operacije",
                "Ulazna i procesna kontrola sa opcijama „dozvoljava / ne dozvoljava“ i vraćanjem neispravnih komada",
                "Digitalno upravljanje radom operatera kroz „Početak“, „Zastoj“, „Kraj“ i „Kraj operacije“, uz preuzimanje operacije skeniranjem RN-a",
                "Panel resursa sa pregledom radnika i mašina",
                "Postavljanje finansijskih ciljeva",
                "Proces dorade sa kontrolom, evidencijom naplate i pratećom dokumentacijom",
                "Automatsko praćenje kašnjenja radnih naloga i operativne notifikacije",
                "Automatsko zatvaranje radnog naloga nakon završetka proizvodnog ciklusa",
                "Live Manager Analytics Dashboard"
              ]}
              delay={0.2}
            />
            <PhaseCard
              number="Faza III"
              title="Analitika, optimizacija i podrška poslovnom odlučivanju"
              duration="1.5 mj."
              price="4.900"
              items={[
                "Implementacija inventure sa QR evidencijom i usklađivanjem količina",
                "Evidencija prijave radnika i praćenje stvarnog vremena rada i učinka",
                "Prediktivno planiranje potreba za materijalom i buduće potražnje",
                "Analiza uskih grla, čekanja i uzroka gubitaka u proizvodnji",
                "Analiza profitabilnosti i proizvodnog miksa po proizvodu, kupcu, RN-u i proizvodnom satu",
                "Analiza stanja mašina i isplativosti ulaganja — KPI pokazatelji, kvarovi, zastoji, troškovi održavanja, remont, zamjena i povrat investicije",
                "Poređenje ključnih proizvodnih pokazatelja sa industrijskim referentnim vrijednostima",
                "Detaljan prikaz radnika sa učinkovitosti, efikasnosti, radnim vremenom i urađenim operacijama"
              ]}
              delay={0.3}
            />
            <PhaseCard
              number="Faza IV"
              title="RFID identifikacija radnika i prijava bez lozinke"
              duration="1.5 mj."
              price="4.900"
              items={[
                "Integracija postojećih Trendy RFID kartica i povezivanje s radnicima iz Pantheona",
                "Prijava radnika prislanjanjem kartice, bez korisničkog imena i lozinke",
                "Podrška za RFID čitače na tabletima i računarima te NFC u native aplikaciji",
                "Evidencija dolaska, pauze i odlaska karticom",
                "Početak, Zastoj i Kraj operacije bilježe se na ime radnika koji je prislonio karticu",
                "Potvrda izdavanja materijala, kontrole i dorade karticom",
                "Automatsko popunjavanje radnika i vremena pri zatvaranju radnog naloga",
                "Automatsko zaključavanje neaktivnog uređaja i brzo otključavanje 4-cifrenim PIN-om, kao zaštita od rada na tuđe ime",
                "Blokada izgubljenih kartica i dodatni PIN za osjetljive radnje",
                "Sedmični pregled industrijskih trendova, tehnologija, materijala i poslovnih prilika"
              ]}
              delay={0.4}
            />
          </div>

          <motion.div 
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            className="mt-12 p-10 bg-slate-900 rounded-[2.5rem] text-white flex flex-col md:flex-row justify-between items-center gap-8"
          >
            <div>
              <h3 className="text-3xl font-bold mb-2">Preostala investicija</h3>
              <p className="text-slate-400">Faza II + III + IV · Faza I završena</p>
            </div>
            <div className="text-right">
              <div className="text-5xl font-bold mb-1">14.700 KM</div>
              <p className="text-slate-400 text-sm font-medium uppercase tracking-widest">Fiksna cijena projekta</p>
            </div>
          </motion.div>
        </div>
      </section>

      {/* Mobile App Section */}
      <section id="mobilna" className="py-16 sm:py-32 px-4 sm:px-6 bg-white">
        <div className="max-w-7xl mx-auto">
          <div className="bg-slate-50 rounded-3xl sm:rounded-[3rem] border border-slate-200 overflow-hidden relative shadow-sm">
            <div className="absolute top-0 right-0 w-1/2 h-full bg-slate-500/5 blur-[120px]" />
            
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-16 p-5 sm:p-12 lg:p-24 items-center">
              <div className="min-w-0">
                <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-100 border border-slate-200 text-slate-700 text-xs font-bold uppercase tracking-wider mb-8">
                  <SmartphoneIcon className="w-3.5 h-3.5" />
                  Uključeno u ponudu
                </div>
                <h2 className="text-3xl sm:text-4xl lg:text-5xl font-bold text-slate-900 mb-6 sm:mb-8 leading-tight break-words">
                  Native Mobilna & Tablet Aplikacija
                </h2>
                <p className="text-slate-500 text-base sm:text-lg mb-8 sm:mb-10 leading-relaxed">
                  Za maksimalnu efikasnost na terenu, u sklopu ponude razvijamo nativnu aplikaciju optimizovanu za tablete i mobilne uređaje, bez dodatnih troškova.
                </p>
                
                <div className="space-y-6 mb-12">
                  <div className="flex gap-4">
                    <div className="w-12 h-12 bg-slate-50 rounded-2xl flex items-center justify-center shrink-0 border border-slate-100">
                      <QrCode className="text-slate-900 w-6 h-6" />
                    </div>
                    <div className="min-w-0">
                      <h4 className="text-slate-900 font-bold mb-1">Besplatna Migracija</h4>
                      <p className="text-slate-500 text-sm">Postojeći moduli za QR skeniranje RN i sirovina biće besplatno prebačeni na mobilnu aplikaciju.</p>
                    </div>
                  </div>
                  <div className="flex gap-4">
                    <div className="w-12 h-12 bg-slate-50 rounded-2xl flex items-center justify-center shrink-0 border border-slate-100">
                      <Zap className="text-slate-900 w-6 h-6" />
                    </div>
                    <div className="min-w-0">
                      <h4 className="text-slate-900 font-bold mb-1">Brzina i Offline Rad</h4>
                      <p className="text-slate-500 text-sm">Native performanse omogućavaju brže skeniranje i rad u uslovima slabije konekcije.</p>
                    </div>
                  </div>
                </div>

                <div className="flex flex-wrap items-center gap-x-4 gap-y-2 sm:gap-x-6">
                  <div className="text-3xl font-bold text-emerald-600">Besplatno</div>
                  <div className="hidden sm:block h-8 w-px bg-slate-200" />
                  <div className="w-full sm:w-auto text-slate-500 font-bold uppercase tracking-widest text-xs sm:text-sm">Uključeno u ponudu</div>
                </div>
              </div>

              <div className="relative flex justify-center">
                <div className="w-64 h-[500px] bg-slate-900 rounded-[3rem] border-8 border-slate-800 shadow-2xl relative overflow-hidden">
                  <div className="absolute top-0 left-1/2 -translate-x-1/2 w-32 h-6 bg-slate-800 rounded-b-2xl" />
                  <div className="p-6 pt-12">
                    <div className="w-full h-32 bg-white/5 rounded-2xl border border-white/10 mb-6 flex items-center justify-center">
                      <QrCode className="text-white w-12 h-12" />
                    </div>
                    <div className="space-y-4">
                      <div className="h-4 w-3/4 bg-white/10 rounded-full" />
                      <div className="h-4 w-full bg-white/10 rounded-full" />
                      <div className="h-4 w-1/2 bg-white/10 rounded-full" />
                    </div>
                    <div className="mt-12 grid grid-cols-2 gap-4">
                      <div className="h-20 bg-white/5 rounded-2xl" />
                      <div className="h-20 bg-white/5 rounded-2xl" />
                    </div>
                  </div>
                </div>
                {/* Decorative elements */}
                <div className="absolute -bottom-10 -right-10 w-40 h-40 bg-slate-500/10 blur-3xl rounded-full" />
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* ROI Section */}
      <section id="roi" className="py-32 bg-slate-50 overflow-hidden">
        <div className="max-w-7xl mx-auto px-6">
          <div className="grid lg:grid-cols-2 gap-16 items-center">
            <div>
              <span className="text-xs font-bold tracking-[0.3em] text-slate-400 uppercase mb-4 block">Povrat Investicije</span>
              <h2 className="text-4xl lg:text-5xl font-bold mb-8 tracking-tight text-slate-900 leading-tight">
                Dizajnirano za <span className="text-slate-400">maksimalnu efikasnost.</span>
              </h2>
              <p className="text-slate-500 text-lg mb-10 leading-relaxed">
                Naš fokus nije samo na kodu, već na mjerljivim rezultatima. eNalog.app transformiše vašu proizvodnju u visokoproduktivan digitalni pogon.
              </p>
              
              <div className="grid sm:grid-cols-2 gap-6">
                {/* ROI Stat Cards */}
                {[
                  { label: "Manuelne Greške", value: "-90%", icon: ShieldCheck },
                  { label: "Protok Informacija", value: "+300%", icon: Zap },
                  { label: "Admin. Vrijeme", value: "-70%", icon: Clock },
                  { label: "Uvid u Zalihe", value: "100%", icon: Database }
                ].map((stat, i) => (
                  <div key={i} className="p-6 bg-white rounded-2xl border border-slate-100 shadow-sm">
                    <stat.icon className="w-5 h-5 text-slate-400 mb-4" />
                    <div className="text-3xl font-bold text-slate-900 mb-1">{stat.value}</div>
                    <div className="text-xs font-bold text-slate-400 uppercase tracking-widest">{stat.label}</div>
                  </div>
                ))}
              </div>
            </div>

            <div className="relative">
              <div className="absolute -inset-4 bg-emerald-500/5 blur-3xl rounded-full" />
              <div className="relative bg-slate-900 rounded-[3rem] p-12 text-white overflow-hidden">
                <div className="absolute top-0 right-0 p-8 opacity-10">
                  <BarChart3 className="w-32 h-32" />
                </div>
                <h3 className="text-2xl font-bold mb-8">Projekcija Isplativosti</h3>
                <div className="space-y-8">
                  {[
                    { label: "Optimizacija Skladišta", progress: 85 },
                    { label: "Efikasnost Radnika", progress: 92 },
                    { label: "Preciznost Naloga", progress: 98 }
                  ].map((item, i) => (
                    <div key={i}>
                      <div className="flex justify-between text-sm font-bold mb-3 uppercase tracking-widest text-slate-400">
                        <span>{item.label}</span>
                        <span className="text-white">{item.progress}%</span>
                      </div>
                      <div className="h-2 bg-white/10 rounded-full overflow-hidden">
                        <motion.div 
                          initial={{ width: 0 }}
                          whileInView={{ width: `${item.progress}%` }}
                          viewport={{ once: true }}
                          transition={{ duration: 1, delay: i * 0.2 }}
                          className="h-full bg-emerald-400"
                        />
                      </div>
                    </div>
                  ))}
                </div>
                <div className="mt-12 pt-8 border-t border-white/10">
                  <p className="text-sm text-slate-400 leading-relaxed italic">
                    "Digitalizacija procesa direktno smanjuje 'skrivene troškove' zastoja i pogrešnih isporuka."
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Footer */}
      <footer className="py-20 bg-white">
        <div className="max-w-7xl mx-auto px-6 flex flex-col md:flex-row justify-between items-center gap-8">
          <div className="flex items-center gap-4">
            <img 
              src="https://enalog.app/images/logo/TrendyCNC.png" 
              alt="Trendy CNC Logo" 
              className="h-8 object-contain opacity-50 grayscale hover:grayscale-0 transition-all"
              referrerPolicy="no-referrer"
            />
            <div className="h-4 w-px bg-slate-200" />
            <span className="text-sm font-bold tracking-tight text-slate-400">eNalog<span className="text-slate-300">.app</span></span>
          </div>
          <div className="flex flex-col md:flex-row items-center gap-4">
            <p className="text-slate-400 text-sm">© 2026 Sva prava zadržana. Ponuda napravljena od strane</p>
            <a href="https://qla.dev/" target="_blank" rel="noopener noreferrer" className="hover:opacity-100 transition-opacity opacity-80">
              <img 
                src="https://deklarant.ai/build/images/logo-qla.png" 
                alt="QLA Logo" 
                className="h-6 object-contain"
                referrerPolicy="no-referrer"
              />
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
