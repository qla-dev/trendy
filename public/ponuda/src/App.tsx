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
  Truck
} from 'lucide-react';
import { motion, AnimatePresence } from 'motion/react';
import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

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
  duration: string; 
  price: string; 
  items: string[];
  delay?: number;
}) => (
  <motion.div 
    initial={{ opacity: 0, y: 20 }}
    whileInView={{ opacity: 1, y: 0 }}
    viewport={{ once: true }}
    transition={{ delay, duration: 0.5 }}
    className="bg-white rounded-3xl p-8 border border-slate-200 shadow-sm hover:shadow-md transition-all group"
  >
    <div className="flex justify-between items-start mb-6">
      <div>
        <span className="text-xs font-bold tracking-widest text-slate-500 uppercase mb-2 block">{number}</span>
        <h3 className="text-2xl font-semibold text-slate-900">{title}</h3>
      </div>
      <div className="bg-slate-50 px-4 py-2 rounded-2xl border border-slate-100">
        <div className="flex items-center gap-2 text-slate-500 text-sm font-medium whitespace-nowrap">
          <Clock className="w-4 h-4" />
          {duration}
        </div>
      </div>
    </div>
    
    <ul className="space-y-4 mb-8">
      {items.map((item, idx) => (
        <li key={idx} className="flex items-start gap-3 text-slate-600">
          <CheckCircle2 className="w-5 h-5 text-slate-900 mt-0.5 shrink-0" />
          <span className="text-sm leading-relaxed">{item}</span>
        </li>
      ))}
    </ul>
    
    <div className="pt-6 border-t border-slate-100 flex justify-between items-center">
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

export default function App() {
  const [activeStep, setActiveStep] = useState(0);

  const steps = [
    { icon: Database, label: "Skladište" },
    { icon: Factory, label: "Proizvodnja" },
    { icon: QrCode, label: "QR Skeniranje" },
    { icon: CheckCircle2, label: "Zatvaranje" }
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
            <div className="h-6 w-px bg-slate-200 hidden sm:block" />
            <span className="text-lg font-bold tracking-tight hidden sm:block">eNalog<span className="text-slate-500">.app</span></span>
          </div>
          <div className="hidden md:flex items-center gap-8">
            <a href="#faze" className="text-sm font-medium text-slate-500 hover:text-slate-900 transition-colors">Faze Projekta</a>
            <a href="#proces" className="text-sm font-medium text-slate-500 hover:text-slate-900 transition-colors">Proces</a>
            <a href="#mobilna" className="text-sm font-medium text-slate-500 hover:text-slate-900 transition-colors">Mobilna App</a>
            <div className="h-6 w-px bg-slate-200" />
            <span className="text-sm font-bold text-slate-900">Ukupno: 9.800 KM</span>
            <button 
              onClick={() => {
                // Ensure print is called on the main window
                setTimeout(() => {
                  window.print();
                }, 100);
              }}
              className="px-4 py-2 bg-slate-900 text-white text-xs font-bold rounded-xl hover:bg-slate-800 transition-all flex items-center gap-2 print:hidden cursor-pointer relative z-[100]"
            >
              <FileText className="w-3.5 h-3.5" />
              Preuzmi PDF
            </button>
          </div>
        </div>
      </nav>

      {/* Hero Section */}
      <section className="pt-20 pb-32 px-6 overflow-hidden">
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
            <h1 className="text-6xl lg:text-7xl font-bold tracking-tight text-slate-900 mb-8 leading-[1.1]">
              Digitalna transformacija <span className="text-slate-400">proizvodnje.</span>
            </h1>
            <p className="text-xl text-slate-500 leading-relaxed mb-10 max-w-xl">
              Nadogradnja trenutne web aplikacije na nivo potpune automatizacije skladišta i operativnog praćenja radnih naloga u realnom vremenu.
            </p>
            <div className="flex flex-wrap gap-4">
              <a href="#faze" className="px-8 py-4 bg-slate-900 text-white rounded-2xl font-bold hover:bg-slate-800 transition-all flex items-center gap-2 group">
                Pogledaj Faze
                <ArrowRight className="w-4 h-4 group-hover:translate-x-1 transition-transform" />
              </a>
              <a href="#roi" className="px-8 py-4 bg-white border border-slate-200 text-slate-900 rounded-2xl font-bold flex items-center gap-3 hover:border-slate-400 transition-all">
                <BarChart3 className="w-5 h-5 text-slate-500" />
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
            <div className="relative bg-white rounded-[2.5rem] border border-slate-200 shadow-2xl p-10 overflow-hidden">
              <div className="flex items-center justify-between mb-12">
                <h4 className="text-lg font-bold text-slate-900">Status Sistema</h4>
                <div className="flex gap-1.5">
                  {[1, 2, 3].map(i => <div key={i} className="w-2 h-2 rounded-full bg-slate-100" />)}
                </div>
              </div>
              
              <div className="flex justify-between items-center relative">
                <div className="absolute top-8 left-0 right-0 h-0.5 bg-slate-100 -z-10" />
                {steps.map((step, idx) => (
                  <div key={idx} className="flex flex-col items-center gap-3">
                    <div className={cn(
                      "w-16 h-16 rounded-2xl flex items-center justify-center transition-all duration-500",
                      idx === activeStep ? "bg-slate-900 text-white shadow-lg shadow-slate-200" : "bg-slate-100 text-slate-400"
                    )}>
                      <step.icon className="w-8 h-8" />
                    </div>
                    <span className={cn(
                      "text-[10px] font-bold uppercase tracking-wider text-center",
                      idx === activeStep ? "text-slate-900" : "text-slate-400"
                    )}>{step.label}</span>
                  </div>
                ))}
              </div>

              <div className="mt-16 p-6 bg-slate-50 rounded-3xl border border-slate-100">
                <div className="flex items-center gap-4 mb-4">
                  <div className="w-12 h-12 bg-white rounded-2xl flex items-center justify-center shadow-sm">
                    <QrCode className="text-slate-900 w-6 h-6" />
                  </div>
                  <div>
                    <p className="text-xs font-bold text-slate-400 uppercase tracking-widest">Skeniranje</p>
                    <p className="text-sm font-bold text-slate-900">Automatsko Zatvaranje RN</p>
                  </div>
                </div>
                <div className="w-full h-2 bg-slate-200 rounded-full overflow-hidden">
                  <motion.div 
                    className="h-full bg-slate-900"
                    animate={{ width: `${(activeStep + 1) * 25}%` }}
                    transition={{ duration: 0.5 }}
                  />
                </div>
              </div>
            </div>
            <motion.div 
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.4 }}
              className="mt-6 p-4 bg-emerald-50 border border-emerald-100 rounded-2xl flex items-start gap-3"
            >
              <Zap className="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" />
              <p className="text-xs font-medium text-emerald-800 leading-relaxed">
                Pripremljeno za potrebe: <span className="font-bold">Javni konkurs za odabir korisnika grant sredstava tekućih transfera za 2026. godinu - Jačanje konkurentnosti malih i srednjih preduzeća</span>
              </p>
            </motion.div>
          </motion.div>
        </div>
      </section>

      {/* Process Section (Redesigned for Maximum Clarity) */}
      <section id="proces" className="py-32 bg-white overflow-hidden">
        <div className="max-w-7xl mx-auto px-6">
          <div className="text-center mb-24">
            <motion.div
              initial={{ opacity: 0, y: 10 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true }}
            >
              <span className="text-xs font-bold tracking-[0.3em] text-slate-400 uppercase mb-4 block">Operativni Model</span>
              <h2 className="text-4xl lg:text-5xl font-bold mb-6 tracking-tight text-slate-900">Digitalni Tok Proizvodnje</h2>
              <p className="text-slate-500 max-w-2xl mx-auto text-lg">
                Precizno definisan put od ulaza sirovine do finalnog proizvoda, eliminišući manuelne greške.
              </p>
            </motion.div>
          </div>

          <div className="relative">
            {/* Connection Line (Desktop) */}
            <div className="absolute top-1/2 left-0 w-full h-px bg-slate-100 -translate-y-1/2 hidden lg:block" />

            <div className="grid lg:grid-cols-5 gap-8 relative">
              {[
                {
                  step: "01",
                  title: "Skladište",
                  desc: "Definisanje šifarnika i lokacija sirovina.",
                  icon: Database,
                  tags: ["Šifre", "Lokacije"]
                },
                {
                  step: "02",
                  title: "Prenosnica",
                  desc: "Digitalni prenos materijala u proizvodnju.",
                  icon: ArrowRightLeft,
                  isDoc: true
                },
                {
                  step: "03",
                  title: "Proizvodnja",
                  desc: "QR skeniranje: Početak, Pauza, Kraj.",
                  icon: QrCode,
                  active: true
                },
                {
                  step: "04",
                  title: "Izdavanje",
                  desc: "Automatski dokument izdavanja materijala.",
                  icon: FileText,
                  isDoc: true
                },
                {
                  step: "05",
                  title: "Zatvaranje",
                  desc: "Automatsko zatvaranje RN nakon pakovanja.",
                  icon: CheckCircle2,
                  success: true
                }
              ].map((item, idx) => (
                <motion.div
                  key={idx}
                  initial={{ opacity: 0, y: 20 }}
                  whileInView={{ opacity: 1, y: 0 }}
                  viewport={{ once: true }}
                  transition={{ delay: idx * 0.1 }}
                  className="relative z-10"
                >
                  <div className={cn(
                    "p-8 rounded-[2rem] border transition-all duration-500 h-full flex flex-col items-center text-center",
                    item.active ? "bg-slate-900 text-white border-slate-900 shadow-xl scale-105" : 
                    item.success ? "bg-emerald-50 border-emerald-100 text-slate-900" :
                    "bg-white border-slate-100 text-slate-900 hover:border-slate-300"
                  )}>
                    <div className={cn(
                      "w-14 h-14 rounded-2xl flex items-center justify-center mb-6 shadow-sm",
                      item.active ? "bg-white text-slate-900" : "bg-slate-50 text-slate-400"
                    )}>
                      <item.icon className="w-7 h-7" />
                    </div>
                    
                    <span className={cn(
                      "text-[10px] font-black uppercase tracking-[0.2em] mb-2",
                      item.active ? "text-slate-400" : "text-slate-300"
                    )}>Korak {item.step}</span>
                    
                    <h3 className="text-xl font-bold mb-3">{item.title}</h3>
                    <p className={cn(
                      "text-sm leading-relaxed",
                      item.active ? "text-slate-400" : "text-slate-500"
                    )}>{item.desc}</p>

                    {item.tags && (
                      <div className="flex gap-2 mt-4">
                        {item.tags.map(t => (
                          <span key={t} className="px-2 py-1 bg-slate-50 text-[9px] font-bold uppercase rounded-md border border-slate-100 text-slate-400">{t}</span>
                        ))}
                      </div>
                    )}

                    {item.isDoc && (
                      <div className="mt-4 inline-flex items-center gap-1.5 px-3 py-1 bg-slate-50 rounded-full border border-slate-100 text-[9px] font-bold uppercase text-slate-400">
                        <FileText className="w-3 h-3" />
                        Dokument
                      </div>
                    )}
                  </div>
                </motion.div>
              ))}
            </div>
          </div>
        </div>
      </section>

      {/* Phases Section */}
      <section id="faze" className="py-32 bg-slate-50">
        <div className="max-w-7xl mx-auto px-6">
          <div className="text-center mb-20">
            <h2 className="text-4xl font-bold text-slate-900 mb-4">Struktura Nadogradnje</h2>
            <p className="text-slate-500 max-w-2xl mx-auto">
              Projekat je podijeljen u dvije ključne faze koje osiguravaju stabilnu implementaciju i postepeno usvajanje novih procesa.
            </p>
          </div>

          <div className="grid md:grid-cols-2 gap-8">
            <PhaseCard 
              number="Faza I"
              title="Digitalna kontrola toka materijala i radnih operacija"
              duration="1.5 mj."
              price="4.900"
              items={[
                "Integracija skladišta sirovina sa proizvodnjom",
                "Definisanje šifarnika sirovina i lokacija",
                "Automatsko generisanje dokumenata izdavanja",
                "QR mehanizam za praćenje početka/pauze/kraja rada",
                "Povezivanje potrošnje sa konkretnim RN",
                "Precizno mjerenje učinka po radniku"
              ]}
              delay={0.1}
            />
            <PhaseCard 
              number="Faza II"
              title="Automatizacija procesa i zatvaranje ciklusa"
              duration="1.5 mj."
              price="4.900"
              items={[
                "Standardizacija operacija (Kontrola, Bravarija, Pakovanje)",
                "Implementacija plana proizvodnje kroz jednostavnu formu",
                "Automatsko zatvaranje RN nakon pakovanja",
                "Završna kontrola i usklađivanje potrošnje materijala",
                "Napredno izvještavanje o efikasnosti procesa",
                "Eliminacija administrativnih kašnjenja"
              ]}
              delay={0.2}
            />
          </div>

          <motion.div 
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            className="mt-12 p-10 bg-slate-900 rounded-[2.5rem] text-white flex flex-col md:flex-row justify-between items-center gap-8"
          >
            <div>
              <h3 className="text-3xl font-bold mb-2">Ukupna Investicija</h3>
              <p className="text-slate-400">Kompletna nadogradnja web platforme (Faza I + II)</p>
            </div>
            <div className="text-right">
              <div className="text-5xl font-bold mb-1">9.800 KM</div>
              <p className="text-slate-400 text-sm font-medium uppercase tracking-widest">Fiksna cijena projekta</p>
            </div>
          </motion.div>
        </div>
      </section>

      {/* Mobile App Section */}
      <section id="mobilna" className="py-32 px-6 bg-white">
        <div className="max-w-7xl mx-auto">
          <div className="bg-slate-50 rounded-[3rem] border border-slate-200 overflow-hidden relative shadow-sm">
            <div className="absolute top-0 right-0 w-1/2 h-full bg-slate-500/5 blur-[120px]" />
            
            <div className="grid lg:grid-cols-2 gap-16 p-12 lg:p-24 items-center">
              <div>
                <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-100 border border-slate-200 text-slate-700 text-xs font-bold uppercase tracking-wider mb-8">
                  <SmartphoneIcon className="w-3.5 h-3.5" />
                  Premium Add-on
                </div>
                <h2 className="text-4xl lg:text-5xl font-bold text-slate-900 mb-8 leading-tight">
                  Native Mobilna & Tablet Aplikacija
                </h2>
                <p className="text-slate-500 text-lg mb-10 leading-relaxed">
                  Za maksimalnu efikasnost na terenu, nudimo razvoj nativne aplikacije optimizovane za tablete i mobilne uređaje.
                </p>
                
                <div className="space-y-6 mb-12">
                  <div className="flex gap-4">
                    <div className="w-12 h-12 bg-slate-50 rounded-2xl flex items-center justify-center shrink-0 border border-slate-100">
                      <QrCode className="text-slate-900 w-6 h-6" />
                    </div>
                    <div>
                      <h4 className="text-slate-900 font-bold mb-1">Besplatna Migracija</h4>
                      <p className="text-slate-500 text-sm">Postojeći moduli za QR skeniranje RN i sirovina biće besplatno prebačeni na mobilnu aplikaciju.</p>
                    </div>
                  </div>
                  <div className="flex gap-4">
                    <div className="w-12 h-12 bg-slate-50 rounded-2xl flex items-center justify-center shrink-0 border border-slate-100">
                      <Zap className="text-slate-900 w-6 h-6" />
                    </div>
                    <div>
                      <h4 className="text-slate-900 font-bold mb-1">Brzina i Offline Rad</h4>
                      <p className="text-slate-500 text-sm">Native performanse omogućavaju brže skeniranje i rad u uslovima slabije konekcije.</p>
                    </div>
                  </div>
                </div>

                <div className="flex items-center gap-6">
                  <div className="text-3xl font-bold text-slate-900">4.900 KM</div>
                  <div className="h-8 w-px bg-slate-200" />
                  <div className="text-slate-500 font-bold uppercase tracking-widest text-sm">Jednokratno</div>
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
