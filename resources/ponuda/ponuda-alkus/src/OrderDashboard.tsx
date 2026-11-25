import { useEffect, useRef, useState, type ReactNode } from 'react';
import {
  ArrowDownToLine, ArrowLeft, ArrowRight, Check, ChevronRight, Database,
  FileScan, FileText, History, List, MousePointer2, Pause, PencilLine, Play, RotateCcw,
  Search, UploadCloud,
} from 'lucide-react';
import { tokensFor } from './order-pricing.ts';
import './order-dashboard.css';

const tabs = [
  { id: 'invoices', label: 'Ulazne fakture', icon: List },
  { id: 'scan', label: 'AI obrada', icon: FileScan },
  { id: 'history', label: 'Historija obrade', icon: History },
] as const;

const scenes = [
  { id: 'invoices', view: 'invoices', duration: 2900, note: 'Otvaramo AI obradu iz pregleda ulaznih faktura.' },
  { id: 'scan', view: 'scan', duration: 2500, note: 'Biramo ogledni PDF fakture FA-031/26.' },
  { id: 'upload', view: 'scan', duration: 1700, note: 'Učitavanje šest stranica izvornog PDF-a.' },
  { id: 'extract', view: 'scan', duration: 2300, note: 'AI prepoznaje pošiljatelja, broj fakture, stavke, cijene i PDV.' },
  { id: 'review', view: 'scan', duration: 3400, note: 'Faktura je spremna za pregled. Provjeravamo količinu na prvoj poziciji.' },
  { id: 'edit', view: 'scan', duration: 3200, note: 'Prema originalu ispravljamo očitanu količinu s 2 na 20 vijaka.' },
  { id: 'ready', view: 'scan', duration: 2700, note: 'Izmjena je sačuvana. Dokument je spreman za transfer.' },
  { id: 'pending', view: 'history', duration: 3400, note: 'U historiji faktura čeka na transfer. Potrošnja je još 0 tokena.' },
  { id: 'transfer', view: 'history', duration: 2100, note: 'Nakon potvrde korisnika provjeravaju se šifre i prenose stavke u Pantheon.' },
  { id: 'charged', view: 'history', duration: 3600, note: 'Pantheon je vratio broj dokumenta. Za 6 stranica evidentirano je 11 tokena.' },
  { id: 'saved', view: 'invoices', duration: 3200, note: 'U pregledu ulaznih faktura nalazimo dokument upisan u Pantheon.' },
  { id: 'positions', view: 'invoices', duration: 3300, note: 'Otvaramo spremljene stavke i provjeravamo količinu, cijene i rabat.' },
] as const;

type SceneId = (typeof scenes)[number]['id'];
type View = (typeof tabs)[number]['id'];
type GoToScene = (id: SceneId, play?: boolean) => void;
export type InvoiceDemoRequest = { scene: 'pending' | 'transfer' };

const invoiceReference = 'FA-031/26';
const filename = 'Faktura_FA-031-26.pdf';
const savedDocument = 'UF-000031';
const date = '25.11.2026.';
const scanPages = 6;
const scanTokens = tokensFor(scanPages);
const previousScanPages = 4;
const correctedQuantity = 20;
const lineHeadings = ['#', 'Šifra artikla', 'Naziv', 'Količina', 'JM', 'Jed. cijena', 'Rabat', 'Iznos bez PDV-a'];
const items = [
  { code: 'VIJ-419', name: 'Montažni vijak 4,2 × 19 mm', quantity: correctedQuantity, price: 21, discount: 0 },
  { code: 'PVC-7016', name: 'PVC profil RAL 7016', quantity: 13, price: 2400, discount: 10 },
  { code: 'OK-012', name: 'Okov za prozor', quantity: 4, price: 875, discount: 0 },
];
const formatAmount = (feninga: number) => (feninga / 100).toFixed(2).replace('.', ',') + ' KM';
const netLine = (item: (typeof items)[number], quantity: number) => Math.round(quantity * item.price * (100 - item.discount) / 100);
const netInvoice = (firstQuantity: number) => items.reduce((sum, item, index) => sum + netLine(item, index ? item.quantity : firstQuantity), 0);

function Cue() {
  return <MousePointer2 className="od-cursor" size={21} aria-hidden="true" />;
}

function Action({ children, onClick, active = false, primary = false, disabled = false }: {
  children: ReactNode;
  onClick?: () => void;
  active?: boolean;
  primary?: boolean;
  disabled?: boolean;
}) {
  return (
    <button type="button" className={`od-button${primary ? ' od-primary' : ''}${active ? ' od-click' : ''}`}
      onClick={onClick} disabled={disabled}>
      {children}
      {active && <Cue />}
    </button>
  );
}

function Grid({ title, headings, children }: { title: string; headings: string[]; children: ReactNode }) {
  return (
    <div className="od-table-frame" role="region" aria-label={title} tabIndex={0}>
      <table className="od-table" aria-label={title}>
        <thead><tr>{headings.map((heading) => <th scope="col" key={heading}>{heading}</th>)}</tr></thead>
        <tbody>{children}</tbody>
      </table>
    </div>
  );
}

function ItemRows({ quantity = correctedQuantity, onEdit, cue = false }: { quantity?: number; onEdit?: () => void; cue?: boolean }) {
  return items.map((item, index) => {
    const lineQuantity = index ? item.quantity : quantity;
    return (
      <tr key={item.code} className={index === 0 && onEdit ? 'od-new-row' : ''}>
        <td>{index + 1}</td><th scope="row">{item.code}</th><td>{item.name}</td>
        <td>{index === 0 && onEdit
          ? <Action onClick={onEdit} active={cue}>{lineQuantity}<PencilLine size={13} aria-label="Uredi količinu" /></Action>
          : lineQuantity}</td>
        <td>kom</td><td>{formatAmount(item.price)}</td><td>{item.discount}%</td><td>{formatAmount(netLine(item, lineQuantity))}</td>
      </tr>
    );
  });
}

function InvoiceReview({ scene, onEdit, onSave, actions, cue = false, hero = false }: {
  scene: 'review' | 'edit' | 'ready';
  onEdit: () => void;
  onSave: () => void;
  actions: ReactNode;
  cue?: boolean;
  hero?: boolean;
}) {
  const ready = scene === 'ready';
  const quantity = ready ? correctedQuantity : 2;
  const net = netInvoice(quantity);
  const vat = Math.round(net * 17 / 100);
  const Heading = hero ? 'h2' : 'h5';

  return (
    <div className="od-result">
      <div className="od-screen-heading">
        <div><Heading className="od-invoice-title">Pregled fakture</Heading><p>{filename} · {scanPages} stranica</p></div>
        <span className="od-badge od-ready">{ready ? 'Spremno za transfer' : 'Količina za provjeru'}</span>
      </div>
      <dl className="od-facts">
        <div><dt>Broj fakture</dt><dd>{invoiceReference}</dd></div>
        <div><dt>Pošiljatelj · AI</dt><dd>Dobavljač A</dd></div>
        <div><dt>Datum fakture</dt><dd>{date}</dd></div>
      </dl>
      <Grid title="Stavke fakture" headings={lineHeadings}>
        <ItemRows quantity={quantity} cue={scene === 'review' && cue} onEdit={ready ? undefined : onEdit} />
      </Grid>
      <div className="od-feedback" aria-live={hero ? 'polite' : undefined}>
        <span>Osnovica <strong>{formatAmount(net)}</strong></span>
        <span>PDV <strong>{formatAmount(vat)}</strong></span>
        <span>Ukupno <strong>{formatAmount(net + vat)}</strong></span>
      </div>
      {scene === 'edit' && (
        <div className="od-edit">
          <div><strong>Uredi polje · Količina</strong><p>{items[0].code} · 2 → {correctedQuantity} kom. prema originalu</p></div>
          <Action primary active={cue} onClick={onSave}>Sačuvaj</Action>
        </div>
      )}
      <div className="od-actions">
        <span><FileText size={15} aria-hidden="true" /> Original · {invoiceReference}</span>
        {actions}
      </div>
    </div>
  );
}

export function HeroInvoicePreview({ onDemoRequest }: { onDemoRequest: (request: InvoiceDemoRequest) => void }) {
  const [scene, setScene] = useState<'review' | 'edit' | 'ready'>('review');
  const ready = scene === 'ready';
  const goToDemo = (request: InvoiceDemoRequest) => {
    onDemoRequest(request);
    document.getElementById('demo-narudzbe')?.scrollIntoView();
  };

  return (
    <div className="hero-invoice">
      <InvoiceReview hero scene={scene} onEdit={() => setScene('edit')} onSave={() => setScene('ready')}
        actions={<>
          <Action disabled={!ready} onClick={() => goToDemo({ scene: 'pending' })}>Historija obrade <ChevronRight size={14} /></Action>
          <Action primary disabled={!ready} onClick={() => goToDemo({ scene: 'transfer' })}><Database size={15} /> Transfer u bazu</Action>
        </>} />
    </div>
  );
}

function ScanScreen({ scene, go, cue }: { scene: SceneId; go: GoToScene; cue: boolean }) {
  const reviewing = scene === 'review' || scene === 'edit' || scene === 'ready';
  const ready = scene === 'ready';
  const progress = reviewing ? 100 : scene === 'extract' ? 66 : scene === 'upload' ? 24 : 0;
  const status = reviewing ? 'Ekstrakcija završena' : scene === 'extract' ? 'AI ekstrakcija u toku' : scene === 'upload' ? 'Učitavanje dokumenta' : 'Čekam upload';
  return (
    <>
      <div className="od-screen-heading">
        <div><h4>AI obrada ulazne fakture</h4><p>Učitajte fotografiju ili PDF fakture dobavljača i provjerite rezultat prije upisa.</p></div>
        <span className="od-badge">Prijenos uz potvrdu</span>
      </div>
      {!reviewing && (
        <div className="od-scan-grid">
          <div className="od-drop" onDragOver={(event) => event.preventDefault()} onDrop={(event) => {
            event.preventDefault();
            if (!progress && event.dataTransfer.getData('text/plain') === filename) go('upload', true);
          }}>
            <UploadCloud size={29} aria-hidden="true" />
            <strong>{progress ? filename : 'Prevuci dokument ovdje'}</strong>
            <p>{progress ? `PDF · ${scanPages} stranica` : 'Povuci ili klikni ogledni PDF fakture.'}</p>
            {!progress && <div className="od-file-sample" draggable onDragStart={(event) => event.dataTransfer.setData('text/plain', filename)}>
              <Action onClick={() => go('upload', true)} active={cue} primary><FileText size={15} /> Ogledni PDF</Action>
            </div>}
          </div>
          <div className="od-progress-card">
            <div className="od-progress-title"><strong>Status obrade</strong><span>{progress}%</span></div>
            <p>{status}</p>
            <progress value={progress} max={100} aria-label="Status AI obrade" />
            <ol>
              {['Upload', 'AI ekstrakcija', 'Transfer u bazu'].map((label, index) => (
                <li key={label} className={(scene === 'upload' && index === 0) || (scene === 'extract' && index === 1) ? 'od-current-stage' : ''}>
                  {scene === 'extract' && index === 0 ? <Check size={14} /> : <span className="od-stage-dot" />}{label}
                </li>
              ))}
            </ol>
          </div>
        </div>
      )}
      {reviewing && (
        <InvoiceReview scene={scene} cue={cue} onEdit={() => go('edit')} onSave={() => go('ready')}
          actions={<>
            <Action onClick={() => go('pending')} disabled={!ready} active={ready && cue}>Historija obrade <ChevronRight size={14} /></Action>
            <Action onClick={() => go('transfer', true)} disabled={!ready} primary><Database size={15} /> Transfer u bazu</Action>
          </>} />
      )}
    </>
  );
}

function HistoryScreen({ scene, go, cue }: { scene: SceneId; go: GoToScene; cue: boolean }) {
  const completed = scene === 'charged';
  const busy = scene === 'transfer';
  const status = completed ? 'Uspješan transfer' : busy ? 'Transfer u bazu' : 'Čeka na transfer u bazu';
  return (
    <>
      <div className="od-screen-heading">
        <div><h4>Historija obrade</h4><p>Filter historije · Novembar 2026.</p></div>
        {completed ? <Action onClick={() => go('saved')} active={cue}>Ulazne fakture <ArrowRight size={15} /></Action>
          : <Action primary onClick={() => go('transfer', true)} active={!busy && cue} disabled={busy}><ArrowDownToLine size={14} /> {busy ? 'Prijenos…' : 'Transfer u bazu'}</Action>}
      </div>
      <div className="od-stats">
        {[
          ['Obrađeni dokumenti', '3'], ['Tokeni', String(tokensFor(previousScanPages) + (completed ? scanTokens : 0))],
          ['Uspješna AI obrada', '2'], ['Neuspješna AI obrada', '1'],
        ].map(([label, value]) => <div key={label}><span>{label}</span><strong>{value}</strong></div>)}
      </div>
      <Grid title="Historija AI obrade" headings={['Datum i vrijeme', 'Modul', 'Aktivnost', 'Status', 'Fajl', 'Broj stranica', 'Tokeni', 'Akcija']}>
        <tr className="od-new-row">
          <td>{date} 09:42</td><td>Ulazne fakture</td><td>AI obrada</td>
          <td><span className={`od-badge ${completed ? 'od-success' : 'od-ready'}`}>{status}</span></td>
          <th scope="row">{filename}</th>
          <td>{scanPages}</td><td><strong>{completed ? scanTokens : 0}</strong></td>
          <td>{completed ? <Action onClick={() => go('saved')}>Vidi fakturu</Action>
            : <Action onClick={() => go('transfer', true)} disabled={busy}>{busy ? 'Prijenos…' : 'Transfer u bazu'}</Action>}</td>
        </tr>
        <tr>
          <td>{date} 08:18</td><td>Ulazne fakture</td><td>AI obrada</td><td><span className="od-badge od-success">Uspješan transfer</span></td>
          <th scope="row">Faktura_FA-028-26.pdf</th><td>{previousScanPages}</td><td>{tokensFor(previousScanPages)}</td><td>—</td>
        </tr>
        <tr>
          <td>24.11.2026. 16:11</td><td>Ulazne fakture</td><td>AI obrada</td><td><span className="od-badge od-error">Neuspješna AI obrada</span></td>
          <th scope="row">Nečitljiva_faktura.pdf</th><td>2</td><td>0</td><td>—</td>
        </tr>
      </Grid>
      {busy && <div className="od-feedback"><Database size={18} /> Upis zaglavlja i {items.length} stavke fakture u Pantheon…</div>}
      {completed && <div className="od-feedback od-confirmed"><Check size={18} /> Ogledni Pantheon dokument <strong>{savedDocument}</strong> · {scanTokens} tokena za {scanPages} stranica</div>}
    </>
  );
}

function InvoicesScreen({ scene, go, cue }: {
  scene: SceneId; go: GoToScene; cue: boolean;
}) {
  const transferred = scene === 'saved' || scene === 'positions';
  const [search, setSearch] = useState('');
  const rows = [
    ...(transferred ? [{ number: savedDocument, reference: invoiceReference, supplier: 'Dobavljač A', netFeninga: netInvoice(correctedQuantity), positions: items.length }] : []),
    { number: 'UF-000028', reference: 'FA-028/26', supplier: 'Dobavljač B', netFeninga: 8500, positions: 4 },
    { number: 'UF-000024', reference: 'FA-024/26', supplier: 'Dobavljač C', netFeninga: 10650, positions: 3 },
  ].filter((row) => [row.number, row.reference, row.supplier].join(' ').toLowerCase().includes(search.toLowerCase().trim()));
  return (
    <>
      <div className="od-screen-heading">
        <div><h4>Ulazne fakture dobavljača</h4><p>Fakture prenesene u Pantheon i njihove stavke.</p></div>
        <div className="od-actions">
          <Action onClick={() => go('scan')} active={scene === 'invoices' && cue} primary><FileScan size={16} /> AI obrada</Action>
        </div>
      </div>
      <label className="od-search">
        <Search size={16} aria-hidden="true" />
        <input aria-label="Brza pretraga faktura" value={search} onChange={(event) => setSearch(event.target.value)}
          placeholder="Broj fakture, dobavljač ili Pantheon broj" />
      </label>
      <Grid title="Ulazne fakture dobavljača" headings={['Broj fakture', 'Dobavljač', 'Datum', 'Osnovica', 'Broj stavki', 'Pantheon dokument', 'Status', 'Akcija']}>
        {rows.map((row) => (
          <tr key={row.number} className={row.number === savedDocument ? 'od-new-row' : ''}>
            <th scope="row">{row.reference}</th>
            <td>{row.supplier}</td><td>{date}</td><td>{formatAmount(row.netFeninga)}</td><td>{row.positions}</td><td>{row.number}</td>
            <td><span className="od-badge od-success">Prenesena</span></td>
            <td>{row.number === savedDocument ? <Action onClick={() => go('positions')} active={scene === 'saved' && cue}>Stavke</Action> : '—'}</td>
          </tr>
        ))}
        {!rows.length && <tr><td colSpan={8}>Nema faktura za unesenu pretragu.</td></tr>}
      </Grid>
      {scene === 'positions' && (
        <div className="od-result od-positions">
          <div className="od-screen-heading">
            <div><h5>Stavke ulazne fakture</h5><p>{savedDocument} · {invoiceReference}</p></div>
            <Action onClick={() => go('saved')}>Zatvori stavke</Action>
          </div>
          <Grid title="Spremljene stavke fakture" headings={lineHeadings}>
            <ItemRows />
          </Grid>
        </div>
      )}
    </>
  );
}

export default function OrderDashboard({ demoRequest }: { demoRequest: InvoiceDemoRequest | null }) {
  const dashboard = useRef<HTMLDivElement>(null);
  const [index, setIndex] = useState(0);
  const [playing, setPlaying] = useState(() => !window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  const [visible, setVisible] = useState(false);
  const [pageVisible, setPageVisible] = useState(!document.hidden);
  const scene = scenes[index];
  const running = playing && visible && pageVisible;

  useEffect(() => {
    const target = dashboard.current;
    if (!target) return;
    const observer = new IntersectionObserver(([entry]) => setVisible(entry.isIntersecting), { threshold: 0.15 });
    observer.observe(target);
    const handleVisibility = () => setPageVisible(!document.hidden);
    document.addEventListener('visibilitychange', handleVisibility);
    return () => {
      observer.disconnect();
      document.removeEventListener('visibilitychange', handleVisibility);
    };
  }, []);

  useEffect(() => {
    if (!running) return;
    const timer = window.setTimeout(() => setIndex((current) => (current + 1) % scenes.length), scene.duration);
    return () => window.clearTimeout(timer);
  }, [running, scene]);

  useEffect(() => {
    if (!demoRequest) return;
    setPlaying(demoRequest.scene === 'transfer');
    setIndex(scenes.findIndex((step) => step.id === demoRequest.scene));
  }, [demoRequest]);

  function go(id: SceneId, play = false) {
    setPlaying(play);
    setIndex(scenes.findIndex((step) => step.id === id));
  }

  function selectView(view: View) {
    if (view === scene.view) return;
    const transferred = scene.id === 'charged' || scene.id === 'saved' || scene.id === 'positions';
    const history = transferred || scene.id === 'transfer' ? 'charged' : 'pending';
    go(view === 'invoices' ? (transferred ? 'saved' : 'invoices') : view === 'scan' ? 'scan' : history);
  }

  function replay() {
    setIndex(0);
    setPlaying(true);
  }

  return (
    <div id="demo-narudzbe" className="order-dashboard print:hidden" ref={dashboard} role="region" aria-labelledby="order-demo-title">
      <div className="od-top">
        <div>
          <div className="od-kicker"><span /> Orderflow.ai · Simulacija</div>
          <h3 id="order-demo-title">Mini dashboard za ulazne fakture</h3>
          <p>Ogledni podaci i simulirani upis u Pantheon.</p>
        </div>
        <div className="od-playback" role="group" aria-label="Upravljanje demonstracijom">
          <Action onClick={() => setPlaying(!playing)}>
            {playing ? <Pause size={16} /> : <Play size={16} />}{playing ? 'Pauziraj' : 'Pokreni'}
          </Action>
          <button type="button" title="Od početka" aria-label="Od početka" className="od-button od-icon-button" onClick={replay}><RotateCcw size={16} /></button>
        </div>
      </div>
      <div className="od-tabs" role="group" aria-label="Ekrani demonstracije">
        {tabs.map(({ id, label, icon: Icon }) => (
          <button type="button" key={id} aria-pressed={scene.view === id} onClick={() => selectView(id)}>
            <Icon size={16} aria-hidden="true" />{label}
          </button>
        ))}
      </div>
      <div className="od-screen" onFocus={(event) => {
        if (event.target instanceof HTMLInputElement) setPlaying(false);
      }}>
        {scene.view === 'invoices' && <InvoicesScreen scene={scene.id} cue={running} go={go} />}
        {scene.view === 'scan' && <ScanScreen scene={scene.id} cue={running} go={go} />}
        {scene.view === 'history' && <HistoryScreen scene={scene.id} cue={running} go={go} />}
      </div>
      <div className="od-footer">
        <div className="od-step-text" aria-live={playing ? 'off' : 'polite'}>
          <span>{index + 1} / {scenes.length}</span>
          <p>{scene.note}</p>
        </div>
        <div className="od-step-buttons">
          <button type="button" className="od-button od-icon-button" aria-label="Prethodni korak" disabled={index === 0}
            onClick={() => go(scenes[index - 1].id)}><ArrowLeft size={16} /></button>
          <button type="button" className="od-button od-icon-button" aria-label="Sljedeći korak" disabled={index === scenes.length - 1}
            onClick={() => go(scenes[index + 1].id)}><ArrowRight size={16} /></button>
        </div>
      </div>
      <div className="od-timeline" aria-hidden="true">
        {scenes.map(({ id }, step) => <span key={id} className={step <= index ? 'od-timeline-active' : ''} />)}
      </div>
    </div>
  );
}
