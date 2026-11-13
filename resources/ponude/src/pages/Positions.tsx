import { useCallback, useState } from "react"
import { useNavigate } from "react-router-dom"
import { useAuth } from "../auth/AuthContext"
import { hasPermission } from "../auth/permissions"
import {
  addPosition,
  getAllPositions,
  getPositionByCode,
  updatePosition,
  type Position as DatabasePosition,
} from "../services/positions"
import {
  getAllQuotes,
  type Quote,
  type QuotePosition,
} from "../services/quotes"
import "./Positions.css"

type PositionForm = {
  code: string
  name: string
  shape: string
  length: number
  width: number
  height: number
  diameter: number
  thickness: number
  material: string
  machine: string
  programmingMinutes: number
  setupMinutes: number
  machiningMinutes: number
  rawWeightKg: number
  outputWeightKg: number
  surfaceProtectionName: string
  heatTreatmentName: string
  otherOperationName: string
  note: string
}

type HistoryRow = {
  quote: Quote
  position: QuotePosition
}

const emptyForm: PositionForm = {
  code: "", name: "", shape: "rectangular",
  length: 0, width: 0, height: 0, diameter: 0, thickness: 0,
  material: "", machine: "", programmingMinutes: 0, setupMinutes: 0,
  machiningMinutes: 0, rawWeightKg: 0, outputWeightKg: 0,
  surfaceProtectionName: "", heatTreatmentName: "", otherOperationName: "", note: "",
}

const asNumber = (value: unknown) => {
  const number = Number(value)
  return Number.isFinite(number) ? number : 0
}
const text = (value: unknown) => String(value ?? "").trim()

function dateValue(value: unknown): number {
  if (!value) return 0
  try {
    const candidate = value as { toDate?: () => Date }
    const date = typeof candidate.toDate === "function"
      ? candidate.toDate()
      : value instanceof Date ? value : new Date(value as string | number)
    return Number.isFinite(date.getTime()) ? date.getTime() : 0
  } catch {
    return 0
  }
}
function formatDate(value: unknown) {
  const millis = dateValue(value)
  return millis ? new Date(millis).toLocaleDateString("bs-BA") : "—"
}
function money(value: unknown) {
  return `${asNumber(value).toLocaleString("bs-BA", { minimumFractionDigits: 2, maximumFractionDigits: 2 })} €`
}
function shapeLabel(shape: string) {
  const labels: Record<string, string> = {
    rectangular: "Pravougaoni komad", cylinder: "Valjak", hexagon: "Šestkant",
    octagon: "Osmokant", "l-profile": "L-profil", "i-profile": "I-profil",
    "h-profile": "H-profil", "u-profile": "U-profil", "t-profile": "T-profil",
    "square-tube": "Kvadratna cijev", "rectangular-tube": "Pravougaona cijev",
    "round-tube": "Okrugla cijev", "custom-profile": "Poseban profil",
  }
  return labels[shape] || shape || "—"
}

function fromDatabase(position: DatabasePosition): PositionForm {
  const raw = position as DatabasePosition & Record<string, any>
  return {
    code: text(raw.sifra ?? raw.code),
    name: text(raw.naziv ?? raw.name),
    shape: text(raw.shape ?? raw.tipKomada) || "rectangular",
    length: asNumber(raw.length ?? raw.duzina),
    width: asNumber(raw.width ?? raw.sirina),
    height: asNumber(raw.height ?? raw.visina),
    diameter: asNumber(raw.diameter ?? raw.precnik),
    thickness: asNumber(raw.thickness ?? raw.debljina),
    material: text(raw.material ?? raw.materijal),
    machine: text(raw.machine ?? raw.masina),
    programmingMinutes: asNumber(raw.programmingMinutes ?? raw.programiranje),
    setupMinutes: asNumber(raw.setupMinutes ?? raw.priprema),
    machiningMinutes: asNumber(raw.machiningMinutes ?? raw.obrada),
    rawWeightKg: asNumber(raw.rawWeightKg),
    outputWeightKg: asNumber(raw.outputWeightKg),
    surfaceProtectionName: text(raw.surfaceProtectionName ?? raw.povrsinskaZastita),
    heatTreatmentName: text(raw.heatTreatmentName ?? raw.termickaObrada),
    otherOperationName: text(raw.otherOperationName ?? raw.otherOperation?.naziv),
    note: text(raw.napomena),
  }
}
function fromQuotePosition(position: QuotePosition): PositionForm {
  const raw = position as QuotePosition & Record<string, any>
  return {
    ...emptyForm,
    code: text(raw.code), name: text(raw.name),
    shape: text(raw.shape) || "rectangular",
    length: asNumber(raw.length), width: asNumber(raw.width),
    height: asNumber(raw.height), diameter: asNumber(raw.diameter),
    thickness: asNumber(raw.thickness),
    material: text(raw.material), machine: text(raw.machine),
    programmingMinutes: asNumber(raw.programmingMinutes),
    setupMinutes: asNumber(raw.setupMinutes),
    machiningMinutes: asNumber(raw.machiningMinutes),
    rawWeightKg: asNumber(raw.rawWeightKg ?? raw.weightKg),
    outputWeightKg: asNumber(raw.outputWeightKg),
    surfaceProtectionName: text(raw.surfaceProtectionName ?? raw.surfaceProtection?.naziv),
    heatTreatmentName: text(raw.heatTreatmentName ?? raw.heatTreatment?.naziv),
    otherOperationName: text(raw.otherOperationName ?? raw.otherOperation?.naziv),
  }
}

function Positions() {
  const navigate = useNavigate()
  const { profile } = useAuth()
  const canUpdate = hasPermission(profile, "positions", "update")
  const canCreateQuote = hasPermission(profile, "quotes", "create")
  const [searchCode, setSearchCode] = useState("")
  const [position, setPosition] = useState<PositionForm | null>(null)
  const [positionId, setPositionId] = useState("")
  const [history, setHistory] = useState<HistoryRow[]>([])
  const [message, setMessage] = useState("")
  const [messageType, setMessageType] = useState<"error" | "success" | "info">("info")
  const [loading, setLoading] = useState(false)
  const [saving, setSaving] = useState(false)
  const [source, setSource] = useState<"database" | "quote" | "none">("none")

  const searchPosition = useCallback(async () => {
    const code = searchCode.trim()
    if (!code) {
      setPosition(null); setHistory([]); setPositionId("")
      setMessage("Unesite šifru pozicije koju želite pronaći.")
      setMessageType("error")
      return
    }

    setLoading(true)
    setMessage("")
    setPosition(null)
    setHistory([])
    setPositionId("")
    try {
      const [savedPosition, allPositions, allQuotes] = await Promise.all([
        getPositionByCode(code),
        getAllPositions(),
        getAllQuotes(),
      ])
      const normalized = code.toLocaleUpperCase()
      const databaseMatch = savedPosition || allPositions.find((item) =>
        text(item.sifra).toLocaleUpperCase() === normalized
      )

      const matchingHistory: HistoryRow[] = []
      for (const quote of allQuotes) {
        const match = (quote.positions || []).find((item) =>
          text(item.code).toLocaleUpperCase() === normalized
        )
        if (match) matchingHistory.push({ quote, position: match })
      }
      matchingHistory.sort((a, b) => dateValue(b.quote.datum ?? b.quote.createdAt) - dateValue(a.quote.datum ?? a.quote.createdAt))

      if (databaseMatch) {
        setPosition(fromDatabase(databaseMatch))
        setPositionId(databaseMatch.id || "")
        setSource("database")
      } else if (matchingHistory.length) {
        setPosition(fromQuotePosition(matchingHistory[0].position))
        setSource("quote")
      } else {
        setSource("none")
      }
      setHistory(matchingHistory)
      if (databaseMatch) {
        setMessage(`Pozicija ${code} je pronađena u bazi pozicija.`)
        setMessageType("success")
      } else if (matchingHistory.length) {
        setMessage(`Pozicija ${code} nije zasebno spremljena u bazi pozicija, ali je pronađena u ${matchingHistory.length} ponuda.`)
        setMessageType("info")
      } else {
        setMessage(`Pozicija ${code} nije pronađena ni u bazi pozicija ni u ranijim ponudama.`)
        setMessageType("error")
      }
    } catch (error) {
      console.error("Greška pri pretrazi pozicije:", error)
      setMessage("Došlo je do greške pri učitavanju podataka. Provjerite vezu i Firestore pristup.")
      setMessageType("error")
    } finally {
      setLoading(false)
    }
  }, [searchCode])

  const updateField = (key: keyof PositionForm, value: string | number) => {
    setPosition((current) => current ? { ...current, [key]: value } : current)
  }

  const saveChanges = async () => {
    if (!position) return
    if (!position.code.trim()) {
      setMessage("Šifra pozicije ne može biti prazna.")
      setMessageType("error")
      return
    }
    setSaving(true)
    try {
      const databaseData: DatabasePosition & Record<string, any> = {
        sifra: position.code.trim(),
        naziv: position.name.trim(),
        tipKomada: position.shape,
        duzina: position.length,
        sirina: position.width,
        visina: position.height,
        precnik: position.diameter,
        debljina: position.thickness,
        materijal: position.material,
        masina: position.machine,
        programiranje: position.programmingMinutes,
        priprema: position.setupMinutes,
        obrada: position.machiningMinutes,
        rawWeightKg: position.rawWeightKg,
        outputWeightKg: position.outputWeightKg,
        povrsinskaZastita: position.surfaceProtectionName,
        termickaObrada: position.heatTreatmentName,
        otherOperationName: position.otherOperationName,
        napomena: position.note,
      }
      if (positionId) {
        await updatePosition(positionId, databaseData)
      } else {
        const existing = await getPositionByCode(position.code.trim())
        if (existing?.id) {
          await updatePosition(existing.id, databaseData)
          setPositionId(existing.id)
        } else {
          const id = await addPosition(databaseData)
          setPositionId(id)
        }
      }
      setSource("database")
      setMessage(`Pozicija ${position.code} je uspješno sačuvana u zajedničkoj bazi.`)
      setMessageType("success")
    } catch (error) {
      console.error("Greška pri spremanju pozicije:", error)
      setMessage("Poziciju nije moguće sačuvati. Provjerite Firestore dozvole.")
      setMessageType("error")
    } finally {
      setSaving(false)
    }
  }

  const startNewQuote = (row?: HistoryRow) => {
    if (!position || !canCreateQuote) return
    const historyPosition = row ? row.position : {
      positionNumber: "001",
      code: position.code,
      name: position.name,
      quantity: 1,
      shape: position.shape,
      length: position.length,
      width: position.width,
      height: position.height,
      diameter: position.diameter,
      material: position.material,
      machine: position.machine,
      programmingMinutes: position.programmingMinutes,
      setupMinutes: position.setupMinutes,
      machiningMinutes: position.machiningMinutes,
      surfaceProtectionName: position.surfaceProtectionName,
      heatTreatmentName: position.heatTreatmentName,
    }
    navigate("/new-quote", {
      state: {
        historyPosition,
        historySource: row ? {
          quoteId: row.quote.id,
          quoteNumber: row.quote.brojPonude,
          customerName: row.quote.kupacNaziv,
          date: formatDate(row.quote.datum ?? row.quote.createdAt),
        } : undefined,
      },
    })
  }

  return (
    <main className="position-page">
      <header className="position-page-header">
        <div>
          <span className="position-eyebrow">BAZA PODATAKA / CNC</span>
          <h1>Pretraži poziciju</h1>
          <p>Pronađite tehničke podatke i historiju kalkulacija prema šifri artikla.</p>
        </div>
        <div className="position-header-badge"><span className="position-badge-icon">⌕</span><span>Pretraga<br /><strong>pozicija</strong></span></div>
      </header>

      <section className="position-search-panel">
        <div className="position-search-copy">
          <h2>Koju poziciju tražite?</h2>
          <p>Pretraga obuhvata spremljene pozicije i sve ranije ponude.</p>
        </div>
        <form className="position-search-form" onSubmit={(event) => { event.preventDefault(); void searchPosition() }}>
          <div className="position-search-input-wrap">
            <span aria-hidden="true">⌕</span>
            <input
              value={searchCode}
              onChange={(event) => setSearchCode(event.target.value)}
              placeholder="Unesite šifru pozicije, npr. 65009439"
              aria-label="Šifra pozicije"
            />
            {searchCode && <button type="button" className="position-clear" onClick={() => { setSearchCode(""); setPosition(null); setHistory([]); setMessage("") }} aria-label="Obriši pretragu">×</button>}
          </div>
          <button className="position-search-button" type="submit" disabled={loading}>
            {loading ? <><span className="position-spinner" /> Pretražujem...</> : "Pretraži poziciju"}
          </button>
        </form>
        {message && <div className={`position-alert ${messageType}`} role="status">{message}</div>}
      </section>

      {position && (
        <div className="position-results">
          <section className="position-summary-card">
            <div className="position-card-heading">
              <div>
                <span className="position-eyebrow">PRONAĐENA POZICIJA</span>
                <h2>{position.name || "Bez naziva"}</h2>
                <div className="position-code-line">Šifra: <strong>{position.code}</strong><span className={`position-source-tag ${source}`}>{source === "database" ? "Baza pozicija" : "Preuzeto iz ponude"}</span></div>
              </div>
              {canUpdate && <button className="position-primary-button" onClick={() => void saveChanges()} disabled={saving}>{saving ? "Spremam..." : "Sačuvaj izmjene"}</button>}
            </div>
            <div className="position-stat-grid">
              <div><span>Tip komada</span><strong>{shapeLabel(position.shape)}</strong></div>
              <div><span>Materijal</span><strong>{position.material || "—"}</strong></div>
              <div><span>Mašina</span><strong>{position.machine || "—"}</strong></div>
              <div><span>Dimenzije</span><strong>{position.shape === "cylinder" || position.shape === "round-tube" ? `Ø ${position.diameter || "—"} × ${position.length || "—"} mm` : `${position.length || "—"} × ${position.width || "—"} × ${position.height || "—"} mm`}</strong></div>
            </div>
          </section>

          <section className="position-detail-grid">
            <div className="position-detail-card">
              <div className="position-section-title"><span className="position-section-icon">▦</span><div><h3>Tehnički podaci</h3><p>Geometrija i dimenzije komada</p></div></div>
              <div className="position-fields">
                <label>Šifra pozicije<input value={position.code} onChange={(e) => updateField("code", e.target.value)} /></label>
                <label>Naziv pozicije<input value={position.name} onChange={(e) => updateField("name", e.target.value)} /></label>
                <label>Oblik komada<select value={position.shape} onChange={(e) => updateField("shape", e.target.value)}><option value="rectangular">Pravougaoni</option><option value="cylinder">Valjak</option><option value="hexagon">Šestkant</option><option value="octagon">Osmokant</option><option value="l-profile">L-profil</option><option value="i-profile">I-profil</option><option value="h-profile">H-profil</option><option value="u-profile">U-profil</option><option value="t-profile">T-profil</option><option value="square-tube">Kvadratna cijev</option><option value="rectangular-tube">Pravougaona cijev</option><option value="round-tube">Okrugla cijev</option><option value="custom-profile">Poseban profil</option></select></label>
                {position.shape === "cylinder" || position.shape === "round-tube" ? <><label>Prečnik [mm]<input type="number" min="0" value={position.diameter || ""} onChange={(e) => updateField("diameter", asNumber(e.target.value))} /></label><label>Dužina [mm]<input type="number" min="0" value={position.length || ""} onChange={(e) => updateField("length", asNumber(e.target.value))} /></label></> : <><label>Dužina [mm]<input type="number" min="0" value={position.length || ""} onChange={(e) => updateField("length", asNumber(e.target.value))} /></label><label>Širina [mm]<input type="number" min="0" value={position.width || ""} onChange={(e) => updateField("width", asNumber(e.target.value))} /></label><label>Visina [mm]<input type="number" min="0" value={position.height || ""} onChange={(e) => updateField("height", asNumber(e.target.value))} /></label></>}
                <label>Debljina [mm]<input type="number" min="0" value={position.thickness || ""} onChange={(e) => updateField("thickness", asNumber(e.target.value))} /></label>
                <label>Ulazna težina [kg]<input type="number" min="0" step="0.001" value={position.rawWeightKg || ""} onChange={(e) => updateField("rawWeightKg", asNumber(e.target.value))} /></label>
                <label>Izlazna težina [kg]<input type="number" min="0" step="0.001" value={position.outputWeightKg || ""} onChange={(e) => updateField("outputWeightKg", asNumber(e.target.value))} /></label>
              </div>
            </div>

            <div className="position-detail-card">
              <div className="position-section-title"><span className="position-section-icon">⚙</span><div><h3>Proizvodnja</h3><p>Materijal, mašina i vremena obrade</p></div></div>
              <div className="position-fields">
                <label>Materijal<input value={position.material} onChange={(e) => updateField("material", e.target.value)} /></label>
                <label>Mašina<input value={position.machine} onChange={(e) => updateField("machine", e.target.value)} /></label>
                <label>Programiranje [min]<input type="number" min="0" step="0.1" value={position.programmingMinutes || ""} onChange={(e) => updateField("programmingMinutes", asNumber(e.target.value))} /></label>
                <label>Priprema [min]<input type="number" min="0" step="0.1" value={position.setupMinutes || ""} onChange={(e) => updateField("setupMinutes", asNumber(e.target.value))} /></label>
                <label>Izrada [min]<input type="number" min="0" step="0.1" value={position.machiningMinutes || ""} onChange={(e) => updateField("machiningMinutes", asNumber(e.target.value))} /></label>
              </div>
              <div className="position-time-total"><span>Ukupno vrijeme po stavkama</span><strong>{(position.programmingMinutes + position.setupMinutes + position.machiningMinutes).toLocaleString("bs-BA")} min</strong></div>
            </div>

            <div className="position-detail-card position-treatment-card">
              <div className="position-section-title"><span className="position-section-icon">◇</span><div><h3>Dodatne obrade</h3><p>Zaštita i dodatni proizvodni zahvati</p></div></div>
              <div className="position-fields">
                <label>Površinska zaštita<input value={position.surfaceProtectionName} onChange={(e) => updateField("surfaceProtectionName", e.target.value)} /></label>
                <label>Termička obrada<input value={position.heatTreatmentName} onChange={(e) => updateField("heatTreatmentName", e.target.value)} /></label>
                <label>Ostali zahvati<input value={position.otherOperationName} onChange={(e) => updateField("otherOperationName", e.target.value)} /></label>
                <label className="position-note-field">Napomena<textarea rows={3} value={position.note} onChange={(e) => updateField("note", e.target.value)} /></label>
              </div>
            </div>
          </section>

          <section className="position-history-card">
            <div className="position-history-heading">
              <div><span className="position-eyebrow">PREGLED KALKULACIJA</span><h3>Historija ponuda</h3><p>Pozicija je pronađena u {history.length} {history.length === 1 ? "ponudi" : "ponuda"}.</p></div>
              <span className="position-history-count">{history.length}</span>
            </div>
            {history.length ? (
              <div className="position-table-wrap"><table className="position-history-table"><thead><tr><th>Datum</th><th>Broj ponude</th><th>Kupac</th><th>Količina</th><th>Cijena / kom.</th><th>Ukupno</th><th>Status</th><th></th></tr></thead><tbody>
                {history.map((row, index) => <tr key={`${row.quote.id || row.quote.brojPonude}-${index}`}>
                  <td>{formatDate(row.quote.datum ?? row.quote.createdAt)}</td>
                  <td className="position-quote-number">{row.quote.brojPonude || "—"}</td>
                  <td>{row.quote.kupacNaziv || "—"}</td>
                  <td>{asNumber(row.position.quantity).toLocaleString("bs-BA")}</td>
                  <td>{money(row.position.sellingPricePerPiece)}</td>
                  <td className="position-price-total">{money(row.position.totalSellingPrice)}</td>
                  <td><span className={`position-status ${row.quote.status}`}>{({ draft: "U pripremi", sent: "Poslana", accepted: "Prihvaćena", rejected: "Odbijena" } as Record<string, string>)[row.quote.status] || row.quote.status}</span></td>
                  <td><div className="position-row-actions">{row.quote.id && <button className="position-text-button" onClick={() => navigate(`/new-quote?edit=${encodeURIComponent(row.quote.id!)}`)}>Otvori</button>}{canCreateQuote && <button className="position-text-button" onClick={() => startNewQuote(row)}>Nova ponuda</button>}</div></td>
                </tr>)}
              </tbody></table></div>
            ) : <div className="position-empty-history"><span>▤</span><strong>Nema ranijih ponuda</strong><p>Za ovu poziciju još nije pronađena nijedna spremljena ponuda.</p></div>}
          </section>
          {canCreateQuote && <div className="position-bottom-action"><span>Želite ponovo ponuditi ovu poziciju?</span><button className="position-primary-button" onClick={() => startNewQuote()}>Kreiraj novu ponudu <span>→</span></button></div>}
        </div>
      )}
    </main>
  )
}

export default Positions
