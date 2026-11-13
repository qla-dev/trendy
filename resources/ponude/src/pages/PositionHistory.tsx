import { useEffect, useMemo, useState, type FormEvent } from "react"
import { useNavigate } from "react-router-dom"
import { getAllQuotes, type Quote, type QuotePosition } from "../services/quotes"
import "./PositionHistory.css"

type AnyPosition = QuotePosition & Record<string, any>
type HistoryRecord = {
  key: string
  quote: Quote
  position: AnyPosition
  date: Date | null
  quantity: number
  costPerPiece: number | null
  materialCostPerPiece: number | null
  sellingPricePerPiece: number | null
  totalSellingPrice: number | null
  marginPercent: number | null
  machineName: string
  materialName: string
}

function safeNumber(value: unknown): number | null {
  if (value === null || value === undefined || value === "") return null
  const number = Number(value)
  return Number.isFinite(number) ? number : null
}

function readDate(value: unknown): Date | null {
  if (!value) return null
  try {
    const candidate = value as { toDate?: () => Date }
    const parsed = typeof candidate.toDate === "function"
      ? candidate.toDate()
      : value instanceof Date
        ? value
        : new Date(value as string | number)
    return Number.isNaN(parsed.getTime()) ? null : parsed
  } catch {
    return null
  }
}

function formatDate(value: Date | null): string {
  return value
    ? value.toLocaleDateString("bs-BA", { day: "2-digit", month: "2-digit", year: "numeric" })
    : "Datum nije evidentiran"
}

function formatMoney(value: number | null | undefined): string {
  if (value === null || value === undefined || !Number.isFinite(Number(value))) return "—"
  return `${Number(value).toLocaleString("bs-BA", { minimumFractionDigits: 2, maximumFractionDigits: 2 })} €`
}

function formatPercent(value: number | null | undefined): string {
  if (value === null || value === undefined || !Number.isFinite(Number(value))) return "—"
  return `${Number(value).toLocaleString("bs-BA", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}%`
}

function formatOperationRate(operation: Record<string, any> | null | undefined): string {
  if (!operation) return "—"
  const price = safeNumber(operation.cijena)
  if (price === null) return "—"
  const type = String(operation.tipCijene || "komad").toLocaleLowerCase()
  if (type === "kg") return `${formatMoney(price)} / kg`
  if (type === "fiksno") return `${formatMoney(price)} / serija`
  return `${formatMoney(price)} / kom.`
}

function normalizedCode(value: unknown): string {
  return String(value ?? "").trim().toLocaleUpperCase()
}

function statusLabel(status: unknown): string {
  switch (status) {
    case "draft": return "Nacrt"
    case "sent": return "Poslana"
    case "accepted": return "Prihvaćena"
    case "rejected": return "Odbijena"
    default: return String(status || "—")
  }
}

function statusClass(status: unknown): string {
  switch (status) {
    case "sent": return "position-history-status is-sent"
    case "accepted": return "position-history-status is-accepted"
    case "rejected": return "position-history-status is-rejected"
    default: return "position-history-status is-draft"
  }
}

function quotePositionCost(position: AnyPosition, quantity: number): number | null {
  const direct = safeNumber(position.totalCostPerPiece)
  if (direct !== null) return direct
  const total = safeNumber(position.totalCost)
  return total !== null && quantity > 0 ? total / quantity : null
}

function quotePositionSalesPerPiece(position: AnyPosition, quantity: number): number | null {
  const direct = safeNumber(position.sellingPricePerPiece)
  if (direct !== null) return direct
  const total = safeNumber(position.totalSellingPrice)
  return total !== null && quantity > 0 ? total / quantity : null
}

function buildRecord(quote: Quote, position: AnyPosition, index: number): HistoryRecord {
  const quantity = Math.max(0, safeNumber(position.quantity) ?? 0)
  const date = readDate(quote.datum) ?? readDate(quote.createdAt)
  const sellingPricePerPiece = quotePositionSalesPerPiece(position, quantity)
  const storedTotalSales = safeNumber(position.totalSellingPrice)

  return {
    key: `${quote.id || quote.brojPonude || "quote"}-${index}-${String(position.code || "")}`,
    quote,
    position,
    date,
    quantity,
    costPerPiece: quotePositionCost(position, quantity),
    materialCostPerPiece: safeNumber(position.materialCost),
    sellingPricePerPiece,
    totalSellingPrice: storedTotalSales ?? (sellingPricePerPiece !== null ? sellingPricePerPiece * quantity : null),
    marginPercent: safeNumber(position.marginPercent),
    machineName: String(position.machineName || position.machine || "—"),
    materialName: String(position.material || "—"),
  }
}

function deltaLabel(current: number | null, previous: number | null): { text: string; direction: "up" | "down" | "flat" | "none" } {
  if (current === null || previous === null) return { text: "Nema dovoljno podataka za poređenje", direction: "none" }
  const difference = current - previous
  const formatted = `${difference > 0 ? "+" : difference < 0 ? "−" : ""}${formatMoney(Math.abs(difference))}`
  const pct = previous !== 0 ? ` (${difference > 0 ? "+" : ""}${((difference / previous) * 100).toLocaleString("bs-BA", { maximumFractionDigits: 1 })}%)` : ""
  return { text: `${formatted}${pct}`, direction: difference > 0 ? "up" : difference < 0 ? "down" : "flat" }
}

function PositionHistory() {
  const navigate = useNavigate()
  const [quotes, setQuotes] = useState<Quote[]>([])
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState("")
  const [searchInput, setSearchInput] = useState("")
  const [searchedCode, setSearchedCode] = useState("")
  const [customerFilter, setCustomerFilter] = useState("all")
  const [expandedRows, setExpandedRows] = useState<Record<string, boolean>>({})

  useEffect(() => {
    let cancelled = false
    async function loadHistorySource() {
      try {
        setLoading(true)
        setLoadError("")
        const data = await getAllQuotes()
        if (!cancelled) setQuotes(data as Quote[])
      } catch (error) {
        console.error("Greška pri učitavanju historije kalkulacija:", error)
        if (!cancelled) setLoadError("Nije moguće učitati historiju iz baze ponuda. Provjeri internet vezu i korisnička prava, pa pokušaj ponovo.")
      } finally {
        if (!cancelled) setLoading(false)
      }
    }
    void loadHistorySource()
    return () => { cancelled = true }
  }, [])

  const allRecords = useMemo(() => {
    if (!searchedCode) return []
    const target = normalizedCode(searchedCode)
    const records: HistoryRecord[] = []

    for (const quote of quotes) {
      const positions = Array.isArray(quote.positions) ? quote.positions : []
      positions.forEach((rawPosition, index) => {
        const position = rawPosition as AnyPosition
        if (normalizedCode(position.code) !== target) return
        records.push(buildRecord(quote, position, index))
      })
    }

    return records.sort((a, b) => (b.date?.getTime() ?? 0) - (a.date?.getTime() ?? 0))
  }, [quotes, searchedCode])

  const customerOptions = useMemo(() => {
    const distinct = new Set(allRecords.map((record) => String(record.quote.kupacNaziv || "").trim()).filter(Boolean))
    return [...distinct].sort((a, b) => a.localeCompare(b, "bs"))
  }, [allRecords])

  const records = useMemo(() => {
    const result = customerFilter === "all"
      ? allRecords
      : allRecords.filter((record) => String(record.quote.kupacNaziv || "").trim() === customerFilter)
    return result
  }, [allRecords, customerFilter])

  const latest = records[0]
  const previous = records[1]
  const priceDelta = deltaLabel(latest?.sellingPricePerPiece ?? null, previous?.sellingPricePerPiece ?? null)
  const costDelta = deltaLabel(latest?.costPerPiece ?? null, previous?.costPerPiece ?? null)

  function submitSearch(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const code = searchInput.trim()
    if (!code) {
      setSearchedCode("")
      setCustomerFilter("all")
      setExpandedRows({})
      return
    }
    setSearchedCode(code)
    setCustomerFilter("all")
    setExpandedRows({})
  }

  function toggleRow(key: string) {
    setExpandedRows((current) => ({ ...current, [key]: !current[key] }))
  }

  function reuseHistoricalCalculation(record: HistoryRecord) {
    const p = record.position

    // Namjerno prenosimo samo tehničke ulazne podatke. Stari troškovi,
    // satnice i prodajna cijena se ne prenose; Nova ponuda ih računa ponovo.
    const historyPosition = {
      positionNumber: String(p.positionNumber || "001"),
      code: String(p.code || ""),
      name: String(p.name || ""),
      quantity: record.quantity > 0 ? record.quantity : 1,
      shape: String(p.shape || "rectangular"),
      length: safeNumber(p.length) ?? 0,
      width: safeNumber(p.width) ?? 0,
      height: safeNumber(p.height) ?? 0,
      diameter: safeNumber(p.diameter) ?? 0,
      thickness: safeNumber(p.thickness ?? p.debljina) ?? 0,
      flangeThickness: safeNumber(p.flangeThickness ?? p.debljinaPrirubnice) ?? 0,
      webThickness: safeNumber(p.webThickness ?? p.debljinaRebra) ?? 0,
      customCrossSectionArea: safeNumber(p.customCrossSectionArea ?? p.povrsinaPresjekaMm2) ?? 0,
      material: String(p.material || "S235"),
      programmingMinutes: safeNumber(p.programmingMinutes) ?? 0,
      setupMinutes: safeNumber(p.setupMinutes) ?? 0,
      machiningMinutes: safeNumber(p.machiningMinutes) ?? 0,
      machine: String(p.machine || "Haas_UMC500"),
      surfaceProtectionId: String(p.surfaceProtectionId || p.surfaceProtection?.id || ""),
      surfaceProtectionName: String(p.surfaceProtectionName || p.surfaceProtection?.naziv || ""),
      heatTreatmentId: String(p.heatTreatmentId || p.heatTreatment?.id || ""),
      heatTreatmentName: String(p.heatTreatmentName || p.heatTreatment?.naziv || ""),
      otherOperationId: String(p.otherOperationId || p.otherOperation?.id || ""),
      otherOperationName: String(p.otherOperationName || p.otherOperation?.naziv || ""),
      otherCost1Description: String(p.otherCost1Description || ""),
      otherCost1Amount: safeNumber(p.otherCost1Amount) ?? 0,
      otherCost2Description: String(p.otherCost2Description || ""),
      otherCost2Amount: safeNumber(p.otherCost2Amount) ?? 0,
      ensatCount: safeNumber(p.ensatCount) ?? 0,
      inspectionCostSeries: safeNumber(p.inspectionCostSeries) ?? 0,
    }

    navigate("/new-quote", {
      state: {
        historyPosition,
        historySource: {
          quoteId: record.quote.id || "",
          quoteNumber: String(record.quote.brojPonude || ""),
          customerName: String(record.quote.kupacNaziv || ""),
          date: record.date?.toISOString() || "",
        },
      },
    })
  }

  return (
    <div className="position-history-page">
      <header className="position-history-page-header">
        <div>
          <span className="position-history-eyebrow">PREGLED CIJENA</span>
          <h1>Historija kalkulacija</h1>
          <p>Pregled ranijih ponuda za istu poziciju, sa sačuvanim troškovima i prodajnim cijenama.</p>
        </div>
        <button type="button" className="position-history-secondary-button" onClick={() => navigate("/positions")}>← Pretraži poziciju</button>
      </header>

      <section className="position-history-search-card">
        <form onSubmit={submitSearch} className="position-history-search-form">
          <label htmlFor="position-history-code">Šifra pozicije</label>
          <div className="position-history-search-controls">
            <input
              id="position-history-code"
              value={searchInput}
              onChange={(event) => setSearchInput(event.target.value)}
              placeholder="Unesi šifru artikla, npr. 2185028310"
              autoComplete="off"
            />
            <button type="submit" className="position-history-primary-button" disabled={loading}>Prikaži historiju</button>
          </div>
          <p>Historija se čita iz spremljenih ponuda. Stare cijene se ne preračunavaju prema današnjim cijenama materijala ili satnicama.</p>
        </form>
      </section>

      {loading && <div className="position-history-state">Učitavanje historije ponuda…</div>}
      {!loading && loadError && <div className="position-history-state is-error" role="alert">{loadError}</div>}

      {!loading && !loadError && !searchedCode && (
        <div className="position-history-state">Unesi šifru pozicije da prikažemo ranije kalkulacije, cijene i kupce kojima je pozicija nuđena.</div>
      )}

      {!loading && !loadError && searchedCode && allRecords.length === 0 && (
        <div className="position-history-state is-empty">
          Nije pronađena nijedna spremljena ponuda za šifru <strong>{searchedCode}</strong>. Provjeri šifru ili da li je pozicija ranije bila sačuvana u ponudi.
        </div>
      )}

      {!loading && !loadError && allRecords.length > 0 && (
        <>
          <section className="position-history-results-heading">
            <div>
              <h2>Rezultati za {searchedCode}</h2>
              <p>Pronađeno kalkulacija: <strong>{records.length}</strong>{customerFilter !== "all" ? ` za kupca ${customerFilter}` : " ukupno za sve kupce"}.</p>
            </div>
            <label className="position-history-customer-filter">
              Kupac
              <select value={customerFilter} onChange={(event) => setCustomerFilter(event.target.value)}>
                <option value="all">Svi kupci</option>
                {customerOptions.map((customer) => <option key={customer} value={customer}>{customer}</option>)}
              </select>
            </label>
          </section>

          {records.length >= 2 && (
            <section className="position-history-comparison">
              <div className="position-history-comparison-heading">
                <div>
                  <h2>Poređenje posljednje dvije kalkulacije</h2>
                  <p>{formatDate(previous.date)} → {formatDate(latest.date)} · {previous.quote.kupacNaziv || "Nepoznat kupac"} → {latest.quote.kupacNaziv || "Nepoznat kupac"}</p>
                </div>
                <span className="position-history-compare-hint">Poređenje sačuvanih vrijednosti</span>
              </div>
              <div className="position-history-comparison-grid">
                <div className="position-history-comparison-card">
                  <span>Prodajna cijena / kom.</span>
                  <strong>{formatMoney(latest.sellingPricePerPiece)}</strong>
                  <small className={`position-history-delta is-${priceDelta.direction}`}>{priceDelta.text}</small>
                  <small>Prethodno: {formatMoney(previous.sellingPricePerPiece)}</small>
                </div>
                <div className="position-history-comparison-card">
                  <span>Trošak / kom.</span>
                  <strong>{formatMoney(latest.costPerPiece)}</strong>
                  <small className={`position-history-delta is-${costDelta.direction}`}>{costDelta.text}</small>
                  <small>Prethodno: {formatMoney(previous.costPerPiece)}</small>
                </div>
                <div className="position-history-comparison-card">
                  <span>Trošak materijala / kom.</span>
                  <strong>{formatMoney(latest.materialCostPerPiece)}</strong>
                  <small>Prethodno: {formatMoney(previous.materialCostPerPiece)}</small>
                </div>
                <div className="position-history-comparison-card">
                  <span>Marža</span>
                  <strong>{formatPercent(latest.marginPercent)}</strong>
                  <small>Prethodno: {formatPercent(previous.marginPercent)}</small>
                </div>
              </div>
              <p className="position-history-note">Ako nisu izabrani pojedinačni kupci, poređenje može obuhvatiti dvije ponude za različite kupce. Izaberite kupca u filteru za pregled historije samo tog kupca.</p>
            </section>
          )}

          <section className="position-history-table-card">
            <div className="position-history-table-heading">
              <div>
                <h2>Ranije kalkulacije</h2>
                <p>Najnovija ponuda je na vrhu. Detalji i sačuvane Staffelpreise dostupni su po svakom redu.</p>
              </div>
              <button type="button" className="position-history-secondary-button" onClick={() => navigate("/new-quote")}>＋ Nova kalkulacija</button>
            </div>
            <div className="position-history-table-scroll">
              <table className="position-history-table">
                <thead>
                  <tr>
                    <th>Datum</th><th>Broj ponude</th><th>Kupac</th><th>Status</th><th>Količina</th><th>Materijal</th><th>Mašina</th><th>Trošak / kom.</th><th>Prodajna cijena / kom.</th><th>Ukupno</th><th>Marža</th><th>Akcije</th>
                  </tr>
                </thead>
                <tbody>
                  {records.map((record, index) => {
                    const position = record.position
                    const tiers = Array.isArray(position.staffelpreise) ? position.staffelpreise : []
                    const expanded = Boolean(expandedRows[record.key])
                    return (
                      <FragmentRow
                        key={record.key}
                        record={record}
                        index={index}
                        expanded={expanded}
                        tiers={tiers}
                        onToggle={() => toggleRow(record.key)}
                        onReuse={() => reuseHistoricalCalculation(record)}
                      />
                    )
                  })}
                </tbody>
              </table>
            </div>
          </section>
        </>
      )}
    </div>
  )
}

type FragmentRowProps = {
  record: HistoryRecord
  index: number
  expanded: boolean
  tiers: any[]
  onToggle: () => void
  onReuse: () => void
}

function FragmentRow({ record, index, expanded, tiers, onToggle, onReuse }: FragmentRowProps) {
  const p = record.position
  const quote = record.quote
  const total = record.totalSellingPrice
  return (
    <>
      <tr className={index === 0 ? "position-history-row is-latest" : "position-history-row"}>
        <td>{formatDate(record.date)}</td>
        <td><strong>{quote.brojPonude || "—"}</strong>{index === 0 && <span className="position-history-latest-label">Najnovija</span>}</td>
        <td>{quote.kupacNaziv || "Kupac nije naveden"}</td>
        <td><span className={statusClass(quote.status)}>{statusLabel(quote.status)}</span></td>
        <td>{record.quantity || "—"}</td>
        <td>{record.materialName}</td>
        <td>{record.machineName}</td>
        <td><strong>{formatMoney(record.costPerPiece)}</strong></td>
        <td><strong className="position-history-price">{formatMoney(record.sellingPricePerPiece)}</strong></td>
        <td>{formatMoney(total)}</td>
        <td>{formatPercent(record.marginPercent)}</td>
        <td>
          <div className="position-history-row-actions">
            <button type="button" className="position-history-details-button" onClick={onToggle}>{expanded ? "Sakrij" : "Detalji"}</button>
            <button type="button" className="position-history-reuse-button" onClick={onReuse} title="Preuzmi tehničke podatke i ponovo izračunaj cijenu">Nova ponuda</button>
          </div>
        </td>
      </tr>
      {expanded && (
        <tr className="position-history-detail-row">
          <td colSpan={12}>
            <div className="position-history-detail-content">
              <section>
                <h3>Tehnički podaci sačuvani uz ponudu</h3>
                <div className="position-history-detail-grid">
                  <Detail label="Šifra pozicije" value={String(p.code || "—")} />
                  <Detail label="Naziv" value={String(p.name || "—")} />
                  <Detail label="Tip komada" value={String(p.shape || "—")} />
                  <Detail label="Dimenzije" value={formatDimensions(p)} />
                  <Detail label="Programiranje" value={p.programmingMinutes == null ? "—" : `${p.programmingMinutes} min`} />
                  <Detail label="Priprema" value={p.setupMinutes == null ? "—" : `${p.setupMinutes} min`} />
                  <Detail label="Obrada / kom." value={p.machiningMinutes == null ? "—" : `${p.machiningMinutes} min`} />
                  <Detail label="Težina / kom." value={p.weightKg == null ? "—" : `${Number(p.weightKg).toLocaleString("bs-BA", { maximumFractionDigits: 3 })} kg`} />
                </div>
              </section>
              <section>
                <h3>Troškovi sačuvani uz ponudu (po komadu, gdje je primjenjivo)</h3>
                <div className="position-history-detail-grid">
                  <Detail label="Materijal" value={formatMoney(safeNumber(p.materialCost))} />
                  <Detail label="Programiranje" value={formatMoney(safeNumber(p.programmingCost))} />
                  <Detail label="Priprema" value={formatMoney(safeNumber(p.setupCost))} />
                  <Detail label="Obrada" value={formatMoney(safeNumber(p.machiningCost))} />
                  <Detail label="Transport" value={formatMoney(safeNumber(p.transportCost))} />
                  <Detail label="Površinska zaštita" value={formatMoney(safeNumber(p.surfaceProtectionCost))} />
                  <Detail label="Termička obrada" value={formatMoney(safeNumber(p.heatTreatmentCost))} />
                  <Detail label="Ostali zahvati" value={formatMoney(safeNumber(p.otherOperationCost))} />
                  <Detail label="Ostali troškovi" value={formatMoney(safeNumber(p.otherCostsTotal))} />
                  <Detail label="Ensat" value={formatMoney(safeNumber(p.ensatCostPerPiece))} />
                  <Detail label="Satnica obrade mašine" value={formatMoney(safeNumber(p.machineMachiningRate ?? p.machineRate)) + ((p.machineMachiningRate ?? p.machineRate) == null ? "" : " / h")} />
                  <Detail label="Ukupan trošak / kom." value={formatMoney(record.costPerPiece)} />
                  <Detail label="Marža / kom." value={formatMoney(safeNumber(p.marginValuePerPiece))} />
                </div>
              </section>
              {(p.materialSnapshot || p.pricingSnapshot) && (
                <section>
                  <h3>Historijski ulazni podaci kalkulacije</h3>
                  <div className="position-history-detail-grid">
                    <Detail label="Mašina" value={String(p.machineName || p.machine || "—")} />
                    <Detail label="Materijal — naziv" value={String(p.materialSnapshot?.name || p.materialSnapshot?.code || p.material || "—")} />
                    <Detail label="Cijena materijala" value={p.materialSnapshot?.pricePerKg == null ? "—" : `${formatMoney(safeNumber(p.materialSnapshot.pricePerKg))} / kg`} />
                    <Detail label="Gustina materijala" value={p.materialSnapshot?.densityKgPerM3 == null ? "—" : `${Number(p.materialSnapshot.densityKgPerM3).toLocaleString("bs-BA")} kg/m³`} />
                    <Detail label="Satnica programiranja" value={formatMoney(safeNumber(p.machineProgrammingRate ?? p.programmingHourlyRate ?? p.pricingSnapshot?.programmingHourlyRate)) + ((p.machineProgrammingRate ?? p.programmingHourlyRate ?? p.pricingSnapshot?.programmingHourlyRate) == null ? "" : " / h")} />
                    <Detail label="Satnica pripreme" value={formatMoney(safeNumber(p.machineSetupRate ?? p.pricingSnapshot?.setupHourlyRate)) + ((p.machineSetupRate ?? p.pricingSnapshot?.setupHourlyRate) == null ? "" : " / h")} />
                    <Detail label="Satnica obrade" value={formatMoney(safeNumber(p.machineMachiningRate ?? p.machineRate ?? p.pricingSnapshot?.machiningHourlyRate)) + ((p.machineMachiningRate ?? p.machineRate ?? p.pricingSnapshot?.machiningHourlyRate) == null ? "" : " / h")} />
                    <Detail label="Transportna stopa" value={p.pricingSnapshot?.transportPricePerKg == null ? "—" : `${formatMoney(safeNumber(p.pricingSnapshot.transportPricePerKg))} / kg`} />
                    <Detail label="Trošak kontrole za seriju" value={formatMoney(safeNumber(p.inspectionCostSeries))} />
                    <Detail label="Ensat — broj / kom." value={p.ensatCount == null ? "—" : String(p.ensatCount)} />
                    <Detail label="Ensat cijena / kom." value={p.pricingSnapshot?.ensatPricePerPiece == null ? (p.ensatPricePerPiece == null ? "—" : formatMoney(safeNumber(p.ensatPricePerPiece))) : formatMoney(safeNumber(p.pricingSnapshot.ensatPricePerPiece))} />
                    <Detail label="Površinska zaštita — ulazna cijena" value={formatOperationRate(p.surfaceProtection)} />
                    <Detail label="Termička obrada — ulazna cijena" value={formatOperationRate(p.heatTreatment)} />
                    <Detail label="Ostali zahvat — ulazna cijena" value={formatOperationRate(p.otherOperation)} />
                    <Detail label="Dodatni trošak 1" value={p.otherCost1Description ? `${p.otherCost1Description}: ${formatMoney(safeNumber(p.otherCost1Amount))}` : "—"} />
                    <Detail label="Dodatni trošak 2" value={p.otherCost2Description ? `${p.otherCost2Description}: ${formatMoney(safeNumber(p.otherCost2Amount))}` : "—"} />
                    <Detail label="Marža korištena" value={formatPercent(safeNumber(p.pricingSnapshot?.marginPercent ?? p.marginPercent))} />
                    <Detail label="Verzija kalkulacije" value={p.pricingSnapshot?.version == null ? "Stariji zapis" : `v${p.pricingSnapshot.version}`} />
                    <Detail label="Snapshot zabilježen" value={formatDate(readDate(p.pricingSnapshot?.capturedAt))} />
                  </div>
                </section>
              )}
              {tiers.length > 0 && (
                <section className="position-history-staffel-section">
                  <h3>Količinske cijene (Staffelpreise)</h3>
                  <div className="position-history-tier-table-scroll">
                    <table className="position-history-tier-table">
                      <thead><tr><th>Količina</th><th>Trošak / kom.</th><th>Prodajna cijena / kom.</th><th>Ukupan trošak</th><th>Ukupna prodajna vrijednost</th></tr></thead>
                      <tbody>{tiers.map((tier, tierIndex) => (
                        <tr key={`${record.key}-tier-${tierIndex}`}>
                          <td>{safeNumber(tier.quantity) ?? "—"}</td>
                          <td>{formatMoney(safeNumber(tier.totalCostPerPiece))}</td>
                          <td>{formatMoney(safeNumber(tier.sellingPricePerPiece))}</td>
                          <td>{formatMoney(safeNumber(tier.totalCost))}</td>
                          <td>{formatMoney(safeNumber(tier.totalSellingPrice))}</td>
                        </tr>
                      ))}</tbody>
                    </table>
                  </div>
                </section>
              )}
              <p className="position-history-snapshot-note">Ovo su vrijednosti sačuvane u originalnoj ponudi. Ako su neka polja prazna, starija ponuda ih možda nije sadržavala.</p>
            </div>
          </td>
        </tr>
      )}
    </>
  )
}

function Detail({ label, value }: { label: string; value: string }) {
  return <div className="position-history-detail-item"><span>{label}</span><strong>{value}</strong></div>
}

function formatDimensions(position: AnyPosition): string {
  const dims = [
    ["L", safeNumber(position.length)],
    ["W", safeNumber(position.width)],
    ["H", safeNumber(position.height)],
    ["Ø", safeNumber(position.diameter)],
  ].filter((item) => item[1] !== null && item[1] !== 0)
  return dims.length ? `${dims.map(([label, value]) => `${label}: ${value} mm`).join(" · ")}` : "—"
}

export default PositionHistory
