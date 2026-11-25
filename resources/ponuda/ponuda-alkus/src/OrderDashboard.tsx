import { useEffect, useRef, useState, type ReactNode } from 'react';
import {
  ArrowDownToLine, ArrowLeft, ArrowRight, Check, ChevronRight, CircleAlert, Database,
  FileScan, FileText, History, List, MousePointer2, Pause, PencilLine, Play, RotateCcw,
  Search, UploadCloud,
} from 'lucide-react';
import { tokensFor } from './order-pricing.ts';
import './order-dashboard.css';

const tabs = [
  { id: 'orders', label: 'Narudžbe', icon: List },
  { id: 'scan', label: 'AI Scan', icon: FileScan },
  { id: 'history', label: 'Historija skeniranja', icon: History },
] as const;

const scenes = [
  { id: 'orders', view: 'orders', duration: 2900, note: 'Otvaramo AI Scan iz pregleda narudžbi.' },
  { id: 'scan', view: 'scan', duration: 2500, note: 'Biramo ogledni PDF narudžbe ALK-031.' },
  { id: 'upload', view: 'scan', duration: 1700, note: 'Učitavanje šest stranica izvornog PDF-a.' },
  { id: 'extract', view: 'scan', duration: 2300, note: 'AI čita dokument i izdvaja zaglavlje i pozicije.' },
  { id: 'review', view: 'scan', duration: 3400, note: 'Rezultat skena je spreman. Provjeravamo količinu na prvoj poziciji.' },
  { id: 'edit', view: 'scan', duration: 3200, note: 'Referent prema originalu ispravlja količinu sa 2 na 3 komada.' },
  { id: 'ready', view: 'scan', duration: 2700, note: 'Izmjena je sačuvana. Dokument je spreman za transfer.' },
  { id: 'pending', view: 'history', duration: 3400, note: 'U historiji sken čeka na transfer. Potrošnja je još 0 tokena.' },
  { id: 'transfer', view: 'history', duration: 2100, note: 'Nakon potvrde korisnika provjeravaju se šifre i prenose stavke u Pantheon.' },
  { id: 'charged', view: 'history', duration: 3600, note: 'Pantheon je vratio broj narudžbe. Za 6 stranica evidentirano je 11 tokena.' },
  { id: 'saved', view: 'orders', duration: 3200, note: 'U tabeli Narudžbe nalazimo novi Pantheon dokument.' },
  { id: 'positions', view: 'orders', duration: 3300, note: 'U pozicijama provjeravamo artikle i sačuvanu količinu.' },
] as const;

type SceneId = (typeof scenes)[number]['id'];
type View = (typeof tabs)[number]['id'];
type GoToScene = (id: SceneId, play?: boolean) => void;
type ManualOrder = { buyer: string; reference: string; item: string; quantity: string };

const filename = 'Narudzba_ALK-031.pdf';
const orderNumber = '26-0110-000031';
const manualNumber = '26-0110-000032';
const date = '25.11.2026.';
const deadline = '30.11.2026.';
const scanPages = 6;
const scanTokens = tokensFor(scanPages);
const previousScanPages = 4;
const items = [
  { code: 'PVC-1200', name: 'PVC prozor 1200 × 1400 mm', quantity: 3, price: '245,00 KM' },
  { code: 'ALU-0950', name: 'ALU vrata 950 × 2100 mm', quantity: 1, price: '680,00 KM' },
  { code: 'ROL-1200', name: 'Roletna RAL 7016', quantity: 2, price: '95,00 KM' },
];

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

function ItemRows({ quantity = 3, onEdit, cue = false }: { quantity?: number; onEdit?: () => void; cue?: boolean }) {
  return items.map((item, index) => (
    <tr key={item.code} className={index === 0 && cue ? 'od-new-row' : ''}>
      <td>{index + 1}</td><th scope="row">{item.code}</th><td>{item.name}</td>
      <td>{index === 0 && onEdit
        ? <Action onClick={onEdit} active={cue}>{quantity}<PencilLine size={13} aria-label="Uredi količinu" /></Action>
        : item.quantity}</td>
      <td>kom</td><td>{item.price}</td><td>{deadline}</td>
    </tr>
  ));
}

function ScanScreen({ scene, go, cue }: { scene: SceneId; go: GoToScene; cue: boolean }) {
  const reviewing = ['review', 'edit', 'ready'].includes(scene);
  const progress = reviewing ? 100 : scene === 'extract' ? 66 : scene === 'upload' ? 24 : 0;
  const status = reviewing ? 'Ekstrakcija završena' : scene === 'extract' ? 'AI ekstrakcija u toku' : scene === 'upload' ? 'Učitavanje dokumenta' : 'Čekam upload';
  return (
    <>
      <div className="od-screen-heading">
        <div><h4>AI skeniranje narudžbe</h4><p>Učitajte dokument kupca i provjerite rezultat prije upisa u bazu.</p></div>
        <span className="od-badge">Ručni transfer aktivan</span>
      </div>
      {!reviewing && (
        <div className="od-scan-grid">
          <div className="od-drop" onDragOver={(event) => event.preventDefault()} onDrop={(event) => {
            event.preventDefault();
            if (!progress && event.dataTransfer.getData('text/plain') === filename) go('upload', true);
          }}>
            <UploadCloud size={29} aria-hidden="true" />
            <strong>{progress ? filename : 'Prevuci dokument ovdje'}</strong>
            <p>{progress ? `PDF · ${scanPages} stranica` : 'Povuci ili klikni oznaku oglednog PDF-a.'}</p>
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
        <div className="od-result">
          <div className="od-screen-heading">
            <div><h5>Rezultat AI skena</h5><p>{filename} · {scanPages} stranica</p></div>
            <span className="od-badge od-ready">Spremno za transfer</span>
          </div>
          <dl className="od-facts">
            <div><dt>Broj narudžbe kupca</dt><dd>ALK-031</dd></div>
            <div><dt>Naručitelj</dt><dd>Kupac B</dd></div>
            <div><dt>Rok isporuke</dt><dd>{deadline}</dd></div>
          </dl>
          <Grid title="Stavke iz AI skena" headings={['#', 'Šifra', 'Naziv', 'Količina', 'JM', 'Jed. cijena', 'Rok isporuke']}>
            <ItemRows quantity={scene === 'ready' ? 3 : 2} cue={scene === 'review' && cue}
              onEdit={scene === 'ready' ? undefined : () => go('edit')} />
          </Grid>
          {scene === 'edit' && (
            <div className="od-edit">
              <div><strong>Uredi polje · Količina</strong><p>PVC-1200 · 2 → 3 kom. prema originalu</p></div>
              <Action primary active={cue} onClick={() => go('ready')}>Sačuvaj</Action>
            </div>
          )}
          <div className="od-actions">
            <span><FileText size={15} /> Original · ALK-031</span>
            <Action onClick={() => go('pending')} disabled={scene !== 'ready'} active={scene === 'ready' && cue}>Historija skeniranja <ChevronRight size={14} /></Action>
            <Action onClick={() => go('transfer', true)} disabled={scene !== 'ready'} primary><Database size={15} /> Transfer u bazu</Action>
          </div>
        </div>
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
        <div><h4>Historija skeniranja</h4><p>Filter historije · Novembar 2026.</p></div>
        {completed ? <Action onClick={() => go('saved')} active={cue}>Moje narudžbe <ArrowRight size={15} /></Action>
          : <Action primary onClick={() => go('transfer', true)} active={!busy && cue} disabled={busy}><ArrowDownToLine size={14} /> {busy ? 'Prijenos…' : 'Transfer u bazu'}</Action>}
      </div>
      <div className="od-stats">
        {[
          ['Obrađeni dokumenti', '3'], ['Tokeni', String(tokensFor(previousScanPages) + (completed ? scanTokens : 0))],
          ['Uspješan AI scan', '2'], ['Neuspješan AI scan', '1'],
        ].map(([label, value]) => <div key={label}><span>{label}</span><strong>{value}</strong></div>)}
      </div>
      <Grid title="Historija AI skeniranja" headings={['Datum i vrijeme', 'Modul', 'Aktivnost', 'Status', 'Fajl', 'Broj stranica', 'Tokeni', 'Akcija']}>
        <tr className="od-new-row">
          <td>{date} 09:42</td><td>AI narudžba</td><td>AI scan</td>
          <td><span className={`od-badge ${completed ? 'od-success' : 'od-ready'}`}>{status}</span></td>
          <th scope="row">{filename}</th>
          <td>{scanPages}</td><td><strong>{completed ? scanTokens : 0}</strong></td>
          <td>{completed ? <Action onClick={() => go('saved')}>Vidi narudžbu</Action>
            : <Action onClick={() => go('transfer', true)} disabled={busy}>{busy ? 'Prijenos…' : 'Transfer u bazu'}</Action>}</td>
        </tr>
        <tr>
          <td>{date} 08:18</td><td>AI narudžba</td><td>AI scan</td><td><span className="od-badge od-success">Uspješan transfer</span></td>
          <th scope="row">Narudzba_ALK-028.pdf</th><td>{previousScanPages}</td><td>{tokensFor(previousScanPages)}</td><td>—</td>
        </tr>
        <tr>
          <td>24.11.2026. 16:11</td><td>AI narudžba</td><td>AI scan</td><td><span className="od-badge od-error">Neuspješan AI scan</span></td>
          <th scope="row">Nečitljiv_sken.pdf</th><td>2</td><td>0</td><td>—</td>
        </tr>
      </Grid>
      {busy && <div className="od-feedback"><Database size={18} /> Prenos zaglavlja i 3 pozicije u Pantheon…</div>}
      {completed && <div className="od-feedback od-confirmed"><Check size={18} /> Pantheon dokument <strong>{orderNumber}</strong> · {scanTokens} tokena za {scanPages} stranica</div>}
    </>
  );
}

function OrdersScreen({ scene, go, cue, manualOrder, openManual }: {
  scene: SceneId; go: (id: SceneId) => void; cue: boolean; manualOrder: ManualOrder | null; openManual: () => void;
}) {
  const transferred = scene === 'saved' || scene === 'positions';
  const [search, setSearch] = useState('');
  const rows = [
    ...(transferred ? [{ number: orderNumber, reference: 'ALK-031', buyer: 'Kupac B', carrier: 'Preuzimanje', quantity: '6 kom', positions: 3, workOrders: 0 }] : []),
    ...(manualOrder ? [{ number: manualNumber, reference: manualOrder.reference, buyer: manualOrder.buyer, carrier: '—', quantity: manualOrder.quantity + ' kom', positions: 1, workOrders: 0 }] : []),
    { number: '26-0110-000028', reference: 'ALK-028', buyer: 'Kupac A', carrier: 'Vlastiti prevoz', quantity: '12 kom', positions: 4, workOrders: 2 },
    { number: '26-0110-000024', reference: 'ALK-024', buyer: 'Kupac C', carrier: 'Preuzimanje', quantity: '8 kom', positions: 3, workOrders: 0 },
  ].filter((row) => !search || [row.number, row.reference, row.buyer, row.carrier].join(' ').toLowerCase().includes(search.toLowerCase().trim()));
  return (
    <>
      <div className="od-screen-heading">
        <div><h4>Narudžbe kupaca</h4><p>Pantheon dokumenti i pozicije narudžbi.</p></div>
        <div className="od-actions">
          <Action onClick={openManual}><PencilLine size={15} /> Ručni unos</Action>
          <Action onClick={() => go('scan')} active={scene === 'orders' && cue} primary><FileScan size={16} /> AI Scan</Action>
        </div>
      </div>
      <label className="od-search">
        <Search size={16} aria-hidden="true" />
        <input aria-label="Brza pretraga narudžbi" value={search} onChange={(event) => setSearch(event.target.value)}
          placeholder="Broj narudžbe, naručitelj ili prijevoznik" />
      </label>
      <Grid title="Narudžbe kupaca" headings={['Narudžba', 'Naručitelj', 'Prijevoznik', 'Datum', 'Količina', 'Broj pozicija', 'Broj RN', 'Akcija']}>
        {rows.map((row) => (
          <tr key={row.number} className={row.number === orderNumber || row.number === manualNumber ? 'od-new-row' : ''}>
            <th scope="row"><strong>{row.number}</strong><small>{row.reference} · {row.workOrders ? 'Povezano' : 'Bez RN'}</small></th>
            <td>{row.buyer}</td><td>{row.carrier}</td><td>{date}</td><td>{row.quantity}</td><td>{row.positions}</td><td>{row.workOrders}</td>
            <td>{row.number === orderNumber ? <Action onClick={() => go('positions')} active={scene === 'saved' && cue}>Pozicije</Action> : '—'}</td>
          </tr>
        ))}
        {!rows.length && <tr><td colSpan={8}>Nema narudžbi za unesenu pretragu.</td></tr>}
      </Grid>
      {scene === 'positions' && (
        <div className="od-result od-positions">
          <div className="od-screen-heading">
            <div><h5>Pozicije narudžbe</h5><p>{orderNumber} · ALK-031</p></div>
            <Action onClick={() => go('saved')}>Zatvori pozicije</Action>
          </div>
          <Grid title="Spremljene pozicije narudžbe" headings={['#', 'Šifra', 'Naziv', 'Količina', 'JM', 'Jed. cijena', 'Rok isporuke']}>
            <ItemRows />
          </Grid>
        </div>
      )}
    </>
  );
}

export default function OrderDashboard() {
  const dashboard = useRef<HTMLDivElement>(null);
  const [index, setIndex] = useState(0);
  const [playing, setPlaying] = useState(() => !window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  const [visible, setVisible] = useState(false);
  const [pageVisible, setPageVisible] = useState(!document.hidden);
  const [manual, setManual] = useState(false);
  const [manualOrder, setManualOrder] = useState<ManualOrder | null>(null);
  const scene = scenes[index];
  const running = playing && visible && pageVisible && !manual;

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

  function go(id: SceneId, play = false) {
    setPlaying(play);
    setManual(false);
    setIndex(scenes.findIndex((step) => step.id === id));
  }

  function selectView(view: View) {
    if (view === scene.view && !manual) return;
    const transferred = scene.id === 'charged' || scene.id === 'saved' || scene.id === 'positions';
    const history = transferred || scene.id === 'transfer' ? 'charged' : 'pending';
    go(view === 'orders' ? (transferred ? 'saved' : 'orders') : view === 'scan' ? 'scan' : history);
  }

  function replay() {
    setManual(false);
    setManualOrder(null);
    setIndex(0);
    setPlaying(true);
  }

  return (
    <div id="demo-narudzbe" className="order-dashboard print:hidden" ref={dashboard} role="region" aria-labelledby="order-demo-title">
      <div className="od-top">
        <div>
          <div className="od-kicker"><span /> Orderflow.ai · Simulacija</div>
          <h3 id="order-demo-title">Mini dashboard za narudžbe</h3>
          <p>Ogledni podaci i simulirani upis u Pantheon.</p>
        </div>
        <div className="od-playback" role="group" aria-label="Upravljanje demonstracijom">
          <Action onClick={() => { setManual(false); setPlaying(!playing); }}>
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
        {scene.view === 'orders' && !manual && (
          <OrdersScreen scene={scene.id} cue={running} go={go} manualOrder={manualOrder}
            openManual={() => { setPlaying(false); setManual(true); }} />
        )}
        {scene.view === 'scan' && <ScanScreen scene={scene.id} cue={running} go={go} />}
        {scene.view === 'history' && <HistoryScreen scene={scene.id} cue={running} go={go} />}
        {manual && (
          <form className="od-manual" onSubmit={(event) => {
            event.preventDefault();
            const data = new FormData(event.currentTarget);
            const buyer = String(data.get('buyer') ?? '').trim();
            const reference = String(data.get('reference') ?? '').trim();
            const item = String(data.get('item') ?? '').trim();
            if (!buyer || !reference || !item) return;
            setManualOrder({ buyer, reference, item, quantity: String(data.get('quantity')) });
            setManual(false);
          }}>
            <div className="od-screen-heading">
              <div><h4>Ručni unos narudžbe</h4><p>Ručni unos troši 0 tokena.</p></div>
              <Action onClick={() => setManual(false)}>Odustani</Action>
            </div>
            <div className="od-manual-fields">
              <label>Naručitelj<input name="buyer" required placeholder="Kupac C" /></label>
              <label>Broj narudžbe kupca<input name="reference" required placeholder="ALK-032" /></label>
              <label>Šifra artikla<input name="item" required placeholder="PVC-1200" /></label>
              <label>Količina<input name="quantity" type="number" defaultValue="1" min="1" required /></label>
            </div>
            <button type="submit" className="od-button od-primary"><Database size={15} /> Potvrdi i prenesi u Pantheon</button>
          </form>
        )}
        {manualOrder && !manual && scene.view === 'orders' && (
          <div className="od-feedback od-confirmed" role="status"><Check size={17} /> Ručni upis · {manualNumber} · {manualOrder.item} · 0 tokena</div>
        )}
      </div>
      <div className="od-footer">
        <div className="od-step-text" aria-live={playing ? 'off' : 'polite'}>
          <span>{index + 1} / {scenes.length}</span>
          <p>{manual ? 'Unesite podatke i potvrdite ogledni prijenos.' : scene.note}</p>
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
