import { useState } from 'react';
import { ArrowRight, ClipboardCheck, Database, FileScan, ListChecks, ScanLine, Upload } from 'lucide-react';
import OrderDashboard, { type InvoiceDemoRequest } from './OrderDashboard.tsx';
import { priceFor, tokenPrice, tokensFor } from './order-pricing.ts';
import './orderflow-details.css';

const steps = [
  {
    icon: Upload,
    title: 'Dodajte PDF fakture',
    description: 'Učitajte fotografiju ili PDF s mobitela ili klasičnog skenera. Izvorni dokument ostaje uz nacrt.',
  },
  {
    icon: FileScan,
    title: 'AI priprema nacrt',
    description: 'AI prepoznaje pošiljatelja, broj fakture i stavke, uključujući šifru, količinu, jedinicu mjere, cijenu, rabat i PDV kad su navedeni.',
  },
  {
    icon: ClipboardCheck,
    title: 'Provjerite stavke',
    description: 'Uporedite artikle, količine, cijene i iznose s originalom. Ispravite nejasne stavke i potvrdite prijenos.',
  },
  {
    icon: Database,
    title: 'Upis u Pantheon',
    description: 'U Pantheon se prenose podaci o ulaznoj fakturi i stavkama. Orderflow.ai evidentira broj upisanog dokumenta.',
  },
];

export const scenarios = [
  {
    title: 'Fakture koje čekaju prijenos',
    description: 'Lara izdvaja fakture koje još nisu prenesene u Pantheon.',
    bullets: ['Broj fakture i dobavljač', 'Status provjere i očekivani korak'],
    prompt: 'Koje ulazne fakture još nisu prenesene u Pantheon? Prikaži broj fakture, dobavljača i status.',
    answer: 'Faktura FA-031/26 dobavljača A je spremna za prijenos. Na fakturi FA-032/26 dobavljača B treba provjeriti jednu šifru artikla.',
    columns: ['Faktura', 'Dobavljač', 'Status'],
    rows: [['FA-031/26', 'Dobavljač A', 'Spremna za prijenos'], ['FA-032/26', 'Dobavljač B', 'Provjera šifre']],
  },
  {
    title: 'Nejasne stavke fakture',
    description: 'Prije prijenosa provjerite polja koja AI nije pouzdano očitao.',
    bullets: ['Šifra artikla, količina i cijena', 'Pozicija i stranica izvornog dokumenta'],
    prompt: 'Na fakturi FA-032/26 pronađi nejasne šifre, količine ili jedinične cijene. Navedi poziciju i stranicu.',
    answer: 'Na poziciji 04, str. 2, šifra artikla nije čitljiva. Na poziciji 07, str. 3, jedinična cijena traži provjeru.',
    columns: ['Pozicija', 'Stranica', 'Nedostaje'],
    rows: [['04', '2', 'Šifra artikla'], ['07', '3', 'Jedinična cijena']],
  },
  {
    title: 'Provjera količina i iznosa',
    description: 'Uporedite AI nacrt s originalom prije potvrde fakture.',
    bullets: ['Količine i jedinične cijene po pozicijama', 'Osnovica, rabat i PDV'],
    prompt: 'Uporedi početni AI nacrt fakture FA-031/26 s originalom. Pokaži šta se razlikuje.',
    answer: 'Na prvoj poziciji nacrt je očitao 2 vijka umjesto 20. Ispravkom količine osnovica iznosi 320,00 KM, a PDV 54,40 KM.',
    columns: ['Podatak', 'AI nacrt', 'Original'],
    rows: [['Poz. 01 · vijci', '2 kom.', '20 kom.'], ['Osnovica', '316,22 KM', '320,00 KM'], ['PDV', '53,76 KM', '54,40 KM']],
  },
  {
    title: 'Pregled nabavljenih artikala',
    description: 'Lara sažima količine po artiklima iz odabranih ulaznih faktura.',
    bullets: ['Vijci, PVC profili i okov', 'Brojevi faktura iz kojih dolaze količine'],
    prompt: 'Sažmi količine vijaka, PVC profila i okova iz prenesenih faktura FA-028/26 i FA-031/26. Navedi izvorne fakture.',
    answer: 'Ukupno je 50 vijaka s dvije fakture, 13 PVC profila s FA-031/26 i 4 komada okova s FA-031/26.',
    columns: ['Artikal', 'Komada', 'Fakture'],
    rows: [['Vijci', '50', 'FA-028/26, FA-031/26'], ['PVC profili', '13', 'FA-031/26'], ['Okov', '4', 'FA-031/26']],
  },
];

function TokenCalculator() {
  const [pageInput, setPageInput] = useState('7');
  const pages = Number(pageInput);
  const valid = /^[0-9]+$/.test(pageInput) && pages >= 1
    && Number.isSafeInteger(tokensFor(pages) * tokenPrice);

  return (
    <div className="calculator">
      <label htmlFor="pages">Broj stranica dokumenta</label>
      <div className="calculator-input">
        <input id="pages" type="text" inputMode="numeric" value={pageInput}
          onChange={(event) => setPageInput(event.target.value)} aria-invalid={!valid} />
        <span>str.</span>
      </div>
      <div className="presets">
        {[5, 6, 7, 10, 20].map((preset) => (
          <button key={preset} type="button" aria-pressed={pageInput === String(preset)}
            onClick={() => setPageInput(String(preset))}>{preset}</button>
        ))}
      </div>
      <div className="calculator-result" aria-live="polite">
        {valid ? <><span>{tokensFor(pages)} tokena</span><strong>{priceFor(pages)}</strong></>
          : <span>Unesite cijeli broj stranica veći od nule.</span>}
      </div>
    </div>
  );
}

export function OrderflowDetails({ demoRequest }: { demoRequest: InvoiceDemoRequest | null }) {
  return (
    <div className="orderflow-details bg-white">
        <section className="section wrap offer-section" id="modul" aria-labelledby="module-title">
          <div className="section-heading offer-section-heading">
            <p className="eyebrow">Modul Ulazne fakture</p>
            <h2 id="module-title">Od skeniranog PDF-a do Pantheona</h2>
            <p>Učitajte fotografiju ili PDF ulazne fakture. AI prepoznaje pošiljatelja i stavke, a vi pregledate rezultat i potvrđujete prijenos. Svaki od najviše tri korisnika prijavljuje se svojim računom.</p>
          </div>
          <ol className="workflow" aria-label="Dijagram unosa skenirane ulazne fakture">
            {steps.map(({ icon: Icon, title, description }, index) => (
              <li key={title}>
                <Icon size={23} aria-hidden="true" />
                <h3>{title}</h3>
                <p>{description}</p>
                {index < steps.length - 1 && <ArrowRight className="flow-arrow" size={20} aria-hidden="true" />}
              </li>
            ))}
          </ol>
          <div className="sender-flow" role="group" aria-label="Prepoznavanje pošiljatelja i prijenos fakture">
            <span><ScanLine size={18} aria-hidden="true" /> AI prepoznaje pošiljatelja</span>
            <ArrowRight size={18} className="sender-arrow" aria-hidden="true" />
            <span><ClipboardCheck size={18} aria-hidden="true" /> Provjerite i potvrdite</span>
            <ArrowRight size={18} className="sender-arrow" aria-hidden="true" />
            <span><Database size={18} aria-hidden="true" /> Upišite u Pantheon</span>
          </div>
          <OrderDashboard demoRequest={demoRequest} />
        </section>

        <section className="section wrap pantheon-section offer-section" id="pantheon" aria-labelledby="pantheon-title">
          <div className="section-heading offer-section-heading">
            <p className="eyebrow">Pantheon integracija</p>
            <h2 id="pantheon-title">Upis ulazne fakture dobavljača</h2>
            <p>Orderflow.ai povezuje prepoznatog pošiljatelja i artikle s Pantheon šifrarnicima. Nakon potvrde korisnika prenosi zaglavlje i stavke fakture te prikazuje broj dokumenta koji je Pantheon vratio.</p>
          </div>
          <ol className="erp-diagram" aria-label="Dijagram razmjene podataka sa Pantheonom">
            <li><ListChecks size={22} aria-hidden="true" /><h3>Provjera šifrarnika</h3><p>Pantheon → Orderflow.ai</p><small>Dobavljač, šifre artikala i jedinice mjere</small><ArrowRight className="flow-arrow" size={20} aria-hidden="true" /></li>
            <li><ClipboardCheck size={22} aria-hidden="true" /><h3>Upis dokumenta</h3><p>Orderflow.ai → Pantheon</p><small>Broj i datum fakture, stavke, količine, cijene, rabat i PDV</small><ArrowRight className="flow-arrow" size={20} aria-hidden="true" /></li>
            <li><Database size={22} aria-hidden="true" /><h3>Potvrda upisa</h3><p>Pantheon → Orderflow.ai</p><small>Broj dokumenta i status prijenosa u evidenciji faktura</small></li>
          </ol>
          <p className="erp-note">Vrstu dokumenta i mapiranje polja potvrđujemo na Alkusovoj Pantheon konfiguraciji. Ako prijenos ne uspije, nacrt i razlog greške ostaju dostupni za ispravku i ponovni pokušaj.</p>
        </section>

    </div>
  );
}

export function TokenDetails() {
  return (
    <div className="orderflow-details bg-white">
        <section className="section wrap offer-section" id="tokeni" aria-labelledby="tokens-title">
          <div className="section-heading offer-section-heading">
            <p className="eyebrow">Cjenovnik tokena</p>
            <h2 id="tokens-title">Jedan token košta 0,35 KM</h2>
            <p>AI obrada dokumenta od 1 do 5 stranica troši 10 tokena. Od šeste stranice svaka dodatna stranica troši još 1 token.</p>
          </div>
          <div className="billing-grid">
            <div className="billing-examples">
              <table>
                <caption>Primjeri obračuna za jedan dokument</caption>
                <thead><tr><th scope="col">Stranice</th><th scope="col">Tokeni</th><th scope="col">Iznos</th></tr></thead>
                <tbody>
                  {[5, 6, 7, 8, 10, 20].map((pages) => (
                    <tr key={pages}><th scope="row">{pages === 5 ? '1–5' : pages}</th><td>{tokensFor(pages)}</td><td>{priceFor(pages)}</td></tr>
                  ))}
                </tbody>
              </table>
            </div>
            <div className="billing-details">
              <TokenCalculator />
              <ul>
                <li><strong>Formula</strong> 10 + max(0, broj stranica − 5).</li>
                <li><strong>Svaki dokument ima svoj obračun.</strong> Dva zasebna PDF-a od po 3 stranice troše ukupno 20 tokena. Jedan PDF od 6 stranica troši 11 tokena.</li>
                <li><strong>Naplata slijedi nakon uspješnog prijenosa AI obrađene fakture u Pantheon.</strong> Neuspjela obrada i neuspjeli prijenos troše 0 tokena. Pregled i uređivanje nacrta ne stvaraju novi obračun.</li>
                <li><strong>Jednokratna implementacija iznosi 4.900 KM.</strong> Potrošnja tokena za AI obradu dokumenata obračunava se zasebno.</li>
              </ul>
            </div>
          </div>
          <div className="offer-token-note mt-6 rounded-[1.25rem] border border-alkus-200 bg-alkus-50 p-5">
            <h3>Lara AI · 20 besplatnih poruka dnevno</h3>
            <p className="mt-1">Prvih 20 poruka u danu troši 0 tokena. Od 21. poruke svaka naredna poruka istoga dana troši <strong>1 token</strong>.</p>
          </div>
        </section>
    </div>
  );
}
