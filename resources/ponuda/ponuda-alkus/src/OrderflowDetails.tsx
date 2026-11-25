import { useState } from 'react';
import { ArrowRight, ClipboardCheck, Database, FileScan, ListChecks, PencilLine, Upload } from 'lucide-react';
import OrderDashboard from './OrderDashboard.tsx';
import { priceFor, tokenPrice, tokensFor } from './order-pricing.ts';
import './orderflow-details.css';

const steps = [
  {
    icon: Upload,
    title: 'Povucite PDF',
    description: 'Ispustite narudžbu kupca u zonu za učitavanje ili odaberite datoteku. Original ostaje uz nacrt.',
  },
  {
    icon: FileScan,
    title: 'AI priprema nacrt',
    description: 'Sistem prepoznaje broj narudžbe, kupca, rok i stavke. Za stolariju nacrt može obuhvatiti količine, dimenzije, profil, boju i staklo kada su navedeni u dokumentu.',
  },
  {
    icon: ClipboardCheck,
    title: 'Provjerite stavke',
    description: 'Provjerite kupca, rok i stavke uz original. Ispravite podatke i pokrenite prijenos.',
  },
  {
    icon: Database,
    title: 'Upis u Pantheon',
    description: 'Pantheon dobija zaglavlje i pozicije. Orderflow.ai evidentira broj kreiranog dokumenta.',
  },
];

export const scenarios = [
  {
    title: 'Rokovi isporuke',
    description: 'Lara izdvaja otvorene narudžbe koje kasne i one koje uskoro dospijevaju.',
    bullets: ['Broj narudžbe i kupac', 'Dogovoreni rok i trenutni status'],
    prompt: 'Koje otvorene narudžbe kasne i koje dospijevaju u narednih sedam dana? Prikaži broj, kupca, rok i status.',
    answer: 'Kasni ALK-024 za kupca A, s rokom 23.11. Naredna je ALK-031 za kupca B, s rokom 30.11. Obje su otvorene.',
    columns: ['Narudžba', 'Kupac', 'Rok', 'Status'],
    rows: [['ALK-024', 'Kupac A', '23.11.', 'Kasni'], ['ALK-031', 'Kupac B', '30.11.', 'Otvorena']],
  },
  {
    title: 'Provjera specifikacije',
    description: 'Prije prijenosa provjerite koje pozicije treba dopuniti.',
    bullets: ['Dimenzije, RAL boja i tip stakla', 'Pozicija i stranica izvornog PDF-a'],
    prompt: 'U narudžbi ALK-031 pronađi pozicije kojima nedostaju dimenzije, RAL boja ili tip stakla. Navedi stranicu izvornog PDF-a.',
    answer: 'Pozicija 04, str. 2, nema navedenu RAL boju. Za poziciju 07, str. 3, nedostaje tip stakla.',
    columns: ['Pozicija', 'Stranica', 'Nedostaje'],
    rows: [['04', '2', 'RAL boja'], ['07', '3', 'Tip stakla']],
  },
  {
    title: 'Poređenje revizija',
    description: 'Uporedite dvije verzije narudžbe prije nego što potvrdite novu.',
    bullets: ['Promjene količina i dimenzija po pozicijama', 'Prethodni i novi rok isporuke'],
    prompt: 'Uporedi original i novu reviziju ALK-031. Prikaži promjene količina, dimenzija i roka isporuke po pozicijama.',
    answer: 'Na poziciji 04 količina je promijenjena s 2 na 3 komada. Širina pozicije 07 promijenjena je s 900 na 950 mm. Rok je pomjeren s 27.11. na 30.11.',
    columns: ['Podatak', 'Original', 'Revizija'],
    rows: [['Poz. 04 · količina', '2 kom.', '3 kom.'], ['Poz. 07 · širina', '900 mm', '950 mm'], ['Rok isporuke', '27.11.', '30.11.']],
  },
  {
    title: 'Sažetak po proizvodima',
    description: 'Pogledajte količine potvrđenih narudžbi koje dospijevaju naredne sedmice.',
    bullets: ['PVC prozori, ALU vrata i roletne', 'Brojevi narudžbi iz kojih dolaze količine'],
    prompt: 'Za potvrđene narudžbe s isporukom naredne sedmice sažmi količine po grupama: PVC prozori, ALU vrata i roletne. Dodaj brojeve narudžbi.',
    answer: 'PVC prozori, 14 komada (ALK-034 i ALK-038). ALU vrata, 3 komada (ALK-036). Roletne, 8 komada (ALK-038).',
    columns: ['Grupa proizvoda', 'Komada', 'Narudžbe'],
    rows: [['PVC prozori', '14', 'ALK-034, ALK-038'], ['ALU vrata', '3', 'ALK-036'], ['Roletne', '8', 'ALK-038']],
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

export function OrderflowDetails() {
  return (
    <div className="orderflow-details bg-white">
        <section className="section wrap" id="modul" aria-labelledby="module-title">
          <div className="section-heading">
            <p className="eyebrow">Modul Narudžbe</p>
            <h2 id="module-title">Od unosa narudžbe do Pantheona</h2>
            <p>Uvezite PDF za AI obradu ili unesite narudžbu ručno. Oba načina koriste isti pregled i prijenos. Svaki od najviše tri korisnika prijavljuje se svojim računom.</p>
          </div>
          <ol className="workflow" aria-label="Dijagram unosa narudžbe iz PDF-a">
            {steps.map(({ icon: Icon, title, description }, index) => (
              <li key={title}>
                <Icon size={23} aria-hidden="true" />
                <h3>{title}</h3>
                <p>{description}</p>
                {index < steps.length - 1 && <ArrowRight className="flow-arrow" size={20} aria-hidden="true" />}
              </li>
            ))}
          </ol>
          <div className="manual-flow" aria-label="Dijagram ručnog unosa narudžbe">
            <span><PencilLine size={18} aria-hidden="true" /> Ručno unesite kupca i stavke</span>
            <ArrowRight size={18} className="manual-arrow" aria-hidden="true" />
            <span><ClipboardCheck size={18} aria-hidden="true" /> Provjerite i potvrdite</span>
            <ArrowRight size={18} className="manual-arrow" aria-hidden="true" />
            <span><Database size={18} aria-hidden="true" /> Upišite u Pantheon</span>
          </div>
          <OrderDashboard />
        </section>

        <section className="section wrap pantheon-section" id="pantheon" aria-labelledby="pantheon-title">
          <div className="section-heading">
            <p className="eyebrow">Pantheon integracija</p>
            <h2 id="pantheon-title">Direktan upis narudžbe kupca</h2>
            <p>Orderflow.ai provjerava kupca i artikle prema Pantheon šifrarnicima. Nakon potvrde korisnika prenosi zaglavlje i sve stavke te prikazuje broj dokumenta koji je Pantheon kreirao.</p>
          </div>
          <ol className="erp-diagram" aria-label="Dijagram razmjene podataka sa Pantheonom">
            <li><ListChecks size={22} aria-hidden="true" /><h3>Provjera šifrarnika</h3><p>Pantheon → Orderflow.ai</p><small>Kupac, šifre artikala i jedinice mjere</small><ArrowRight className="flow-arrow" size={20} aria-hidden="true" /></li>
            <li><ClipboardCheck size={22} aria-hidden="true" /><h3>Upis dokumenta</h3><p>Orderflow.ai → Pantheon</p><small>Broj narudžbe kupca, rok, stavke, količine i cijene gdje su navedene</small><ArrowRight className="flow-arrow" size={20} aria-hidden="true" /></li>
            <li><Database size={22} aria-hidden="true" /><h3>Potvrda upisa</h3><p>Pantheon → Orderflow.ai</p><small>Broj dokumenta i status prijenosa u evidenciji narudžbi</small></li>
          </ol>
          <p className="erp-note">Vrstu dokumenta i mapiranje polja potvrđujemo na Alkusovoj Pantheon konfiguraciji. Ako prijenos ne uspije, nacrt i razlog greške ostaju dostupni za ispravku i ponovni pokušaj.</p>
        </section>

    </div>
  );
}

export function TokenDetails() {
  return (
    <div className="orderflow-details bg-white">
        <section className="section wrap" id="tokeni" aria-labelledby="tokens-title">
          <div className="section-heading">
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
                <li><strong>Naplata slijedi nakon uspješnog prijenosa AI obrađene narudžbe u Pantheon.</strong> Neuspjela obrada, neuspjeli prijenos i ručni unos troše 0 tokena. Pregled i uređivanje nacrta ne stvaraju novi obračun.</li>
                <li><strong>Jednokratna implementacija iznosi 4.900 KM.</strong> Potrošnja tokena za AI obradu dokumenata obračunava se zasebno.</li>
              </ul>
            </div>
          </div>
          <div className="mt-6 rounded-[1.25rem] border border-alkus-200 bg-alkus-50 p-5">
            <h3>Lara AI · 20 besplatnih poruka dnevno</h3>
            <p className="mt-1">Prvih 20 poruka u danu troši 0 tokena. Od 21. poruke svaka naredna poruka istoga dana troši <strong>1 token</strong>.</p>
          </div>
        </section>
    </div>
  );
}
