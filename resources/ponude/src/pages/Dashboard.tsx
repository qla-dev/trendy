import { useCallback, useEffect, useMemo, useState } from "react"
import { useNavigate } from "react-router-dom"
import { useAuth } from "../auth/AuthContext"
import { hasPermission } from "../auth/permissions"
import { getAllQuotes, type Quote } from "../services/quotes"
import "./Dashboard.css"

type DashboardQuote = Quote & {
  nextContactDate?: unknown
  followUpDate?: unknown
  datumNarednogKontakta?: unknown
  datumSljedecegKontakta?: unknown
}

type Period = "month" | "previousMonth" | "year" | "all" | "custom"

type PeriodRange = {
  from: Date | null
  to: Date | null
  label: string
}

const currency = new Intl.NumberFormat("bs-BA", {
  style: "currency",
  currency: "EUR",
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
})

const dateFormatter = new Intl.DateTimeFormat("bs-BA", {
  day: "2-digit",
  month: "2-digit",
  year: "numeric",
})

function asDate(value: unknown): Date | null {
  if (!value) return null

  try {
    const dateLike = value as { toDate?: () => Date }
    const date = typeof dateLike.toDate === "function"
      ? dateLike.toDate()
      : value instanceof Date
        ? value
        : new Date(value as string | number)

    return Number.isFinite(date.getTime()) ? date : null
  } catch {
    return null
  }
}

function quoteDate(quote: DashboardQuote): Date | null {
  return asDate(quote.datum) ?? asDate(quote.createdAt) ?? asDate(quote.updatedAt)
}

function quoteCreatedDate(quote: DashboardQuote): Date | null {
  return asDate(quote.createdAt) ?? quoteDate(quote)
}

function quoteActivityDate(quote: DashboardQuote): Date | null {
  return asDate(quote.updatedAt) ?? asDate(quote.createdAt) ?? quoteDate(quote)
}

function quoteStatus(quote: DashboardQuote): string {
  return String(quote.status || "draft").toLowerCase()
}

function statusLabel(status: string): string {
  switch (status) {
    case "draft": return "U pripremi"
    case "sent": return "Poslana"
    case "accepted": return "Prihvaćena"
    case "rejected": return "Odbijena"
    default: return status || "Nepoznat status"
  }
}

function statusClass(status: string): string {
  switch (status) {
    case "sent": return "dashboard-status dashboard-status-sent"
    case "accepted": return "dashboard-status dashboard-status-accepted"
    case "rejected": return "dashboard-status dashboard-status-rejected"
    default: return "dashboard-status dashboard-status-draft"
  }
}

function quoteValue(quote: DashboardQuote): number {
  const savedTotal = Number(quote.totalSellingPrice)
  if (Number.isFinite(savedTotal) && savedTotal !== 0) return savedTotal

  return (quote.positions || []).reduce((sum, position) => {
    const lineTotal = Number(position.totalSellingPrice)
    if (Number.isFinite(lineTotal) && lineTotal !== 0) return sum + lineTotal
    return sum + (Number(position.sellingPricePerPiece) || 0) * (Number(position.quantity) || 0)
  }, 0)
}

function formatDate(value: unknown): string {
  const date = asDate(value)
  return date ? dateFormatter.format(date) : "—"
}

function dateToInputValue(date: Date): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, "0")
  const day = String(date.getDate()).padStart(2, "0")
  return `${year}-${month}-${day}`
}

function parseDateInput(value: string, endOfDay = false): Date | null {
  if (!value) return null
  const [year, month, day] = value.split("-").map(Number)
  if (!year || !month || !day) return null
  return new Date(year, month - 1, day, endOfDay ? 23 : 0, endOfDay ? 59 : 0, endOfDay ? 59 : 0, endOfDay ? 999 : 0)
}

function buildPeriodRange(period: Period, customFrom: string, customTo: string, now = new Date()): PeriodRange {
  const todayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate())
  if (period === "all") return { from: null, to: null, label: "sve vrijeme" }
  if (period === "year") return { from: new Date(now.getFullYear(), 0, 1), to: new Date(now.getFullYear(), 11, 31, 23, 59, 59, 999), label: `godina ${now.getFullYear()}` }
  if (period === "previousMonth") {
    const first = new Date(now.getFullYear(), now.getMonth() - 1, 1)
    return { from: first, to: new Date(now.getFullYear(), now.getMonth(), 0, 23, 59, 59, 999), label: first.toLocaleDateString("bs-BA", { month: "long", year: "numeric" }) }
  }
  if (period === "custom") {
    return {
      from: parseDateInput(customFrom),
      to: parseDateInput(customTo, true),
      label: customFrom && customTo ? `${formatDate(parseDateInput(customFrom))} – ${formatDate(parseDateInput(customTo, true))}` : "odabrani period",
    }
  }
  return { from: new Date(now.getFullYear(), now.getMonth(), 1), to: new Date(now.getFullYear(), now.getMonth() + 1, 0, 23, 59, 59, 999), label: todayStart.toLocaleDateString("bs-BA", { month: "long", year: "numeric" }) }
}

function daysSince(value: unknown): number | null {
  const date = asDate(value)
  if (!date) return null
  return Math.max(0, Math.floor((Date.now() - date.getTime()) / 86_400_000))
}

function getFollowUpDate(quote: DashboardQuote): Date | null {
  return asDate(quote.nextContactDate)
    ?? asDate(quote.followUpDate)
    ?? asDate(quote.datumNarednogKontakta)
    ?? asDate(quote.datumSljedecegKontakta)
}

function Dashboard() {
  const { profile } = useAuth()
  const canCreateQuote = hasPermission(profile, "quotes", "create")
  const navigate = useNavigate()
  const [quotes, setQuotes] = useState<DashboardQuote[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState("")
  const [period, setPeriod] = useState<Period>("month")
  const today = new Date()
  const [customFrom, setCustomFrom] = useState(dateToInputValue(new Date(today.getFullYear(), today.getMonth(), 1)))
  const [customTo, setCustomTo] = useState(dateToInputValue(today))

  const loadQuotes = useCallback(async () => {
    setLoading(true)
    setError("")
    try {
      const data = await getAllQuotes()
      setQuotes(data as DashboardQuote[])
    } catch (loadError) {
      console.error("Greška pri učitavanju Dashboard podataka:", loadError)
      setError("Ponude trenutno nije moguće učitati. Provjeri vezu i pokušaj ponovo.")
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void loadQuotes()
  }, [loadQuotes])

  const periodRange = useMemo(
    () => buildPeriodRange(period, customFrom, customTo),
    [period, customFrom, customTo],
  )

  const validPeriodRange = !periodRange.from || !periodRange.to || periodRange.from <= periodRange.to

  const sortedQuotes = useMemo(() => [...quotes].sort((a, b) => {
    const dateA = quoteActivityDate(a)?.getTime() ?? 0
    const dateB = quoteActivityDate(b)?.getTime() ?? 0
    return dateB - dateA
  }), [quotes])

  const periodQuotes = useMemo(() => {
    if (!validPeriodRange) return []
    return sortedQuotes.filter((quote) => {
      const date = quoteCreatedDate(quote)
      if (!date) return period === "all"
      if (periodRange.from && date < periodRange.from) return false
      if (periodRange.to && date > periodRange.to) return false
      return true
    })
  }, [sortedQuotes, validPeriodRange, period, periodRange.from, periodRange.to])

  const metrics = useMemo(() => {
    const currentValue = periodQuotes.reduce((sum, quote) => sum + quoteValue(quote), 0)
    const open = sortedQuotes.filter((quote) => ["draft", "sent"].includes(quoteStatus(quote))).length
    const sent = sortedQuotes.filter((quote) => quoteStatus(quote) === "sent").length
    const prep = sortedQuotes.filter((quote) => quoteStatus(quote) === "draft").length
    return { count: periodQuotes.length, currentValue, open, sent, prep }
  }, [periodQuotes, sortedQuotes])

  const followUpQuotes = useMemo(() => {
    const now = new Date()
    return sortedQuotes
      .filter((quote) => {
        if (quoteStatus(quote) !== "sent") return false
        const followUpDate = getFollowUpDate(quote)
        if (followUpDate) return followUpDate <= now
        return (daysSince(quoteDate(quote)) ?? 0) >= 7
      })
      .sort((a, b) => (quoteDate(a)?.getTime() ?? 0) - (quoteDate(b)?.getTime() ?? 0))
  }, [sortedQuotes])

  const statusCounts = useMemo(() => {
    const keys = ["draft", "sent", "accepted", "rejected"]
    return keys.map((status) => ({
      status,
      label: statusLabel(status),
      count: sortedQuotes.filter((quote) => quoteStatus(quote) === status).length,
      css: `dashboard-status-bar-${status}`,
    }))
  }, [sortedQuotes])
  const statusMax = Math.max(1, ...statusCounts.map((item) => item.count))

  const openQuote = (quote: DashboardQuote) => {
    if (!quote.id) {
      navigate("/quotes")
      return
    }
    navigate(`/new-quote?edit=${encodeURIComponent(quote.id)}`)
  }

  return (
    <div className="dashboard-page">
      <section className="dashboard-welcome">
        <div>
          <span className="dashboard-eyebrow">CNC QUOTING SYSTEM</span>
          <h1>Pregled poslovanja</h1>
          <p>Pregled ponuda, prodajnih aktivnosti i zadataka koji traže pažnju.</p>
        </div>
        {canCreateQuote && <button className="dashboard-primary-action" type="button" onClick={() => navigate("/new-quote")}>
          <span aria-hidden="true">＋</span> Nova ponuda
        </button>}
      </section>

      <section className="dashboard-toolbar" aria-label="Filter perioda">
        <div>
          <h2>Rezime ponuda</h2>
          <p>Finansijski pokazatelji se računaju iz spremljenih ponuda.</p>
        </div>
        <label className="dashboard-period-control">
          <span>Period izvještaja</span>
          <select value={period} onChange={(event) => setPeriod(event.target.value as Period)}>
            <option value="month">Ovaj mjesec</option>
            <option value="previousMonth">Prethodni mjesec</option>
            <option value="year">Ova godina</option>
            <option value="all">Sve vrijeme</option>
            <option value="custom">Prilagođeni period</option>
          </select>
        </label>
        {period === "custom" && (
          <div className="dashboard-custom-period">
            <label>Od <input type="date" value={customFrom} onChange={(event) => setCustomFrom(event.target.value)} /></label>
            <label>Do <input type="date" value={customTo} onChange={(event) => setCustomTo(event.target.value)} /></label>
          </div>
        )}
      </section>

      {!validPeriodRange && period === "custom" && (
        <div className="dashboard-alert" role="alert">Datum početka ne može biti poslije datuma završetka.</div>
      )}

      {error && (
        <div className="dashboard-error" role="alert">
          <span>{error}</span>
          <button type="button" onClick={() => void loadQuotes()}>Pokušaj ponovo</button>
        </div>
      )}

      <section className="dashboard-metrics" aria-label="Ključni pokazatelji">
        <article className="dashboard-metric-card metric-primary">
          <div className="dashboard-metric-icon" aria-hidden="true">▤</div>
          <div className="dashboard-metric-content">
            <span>Nove ponude</span>
            <strong>{loading ? "—" : metrics.count}</strong>
            <small>{periodRange.label}</small>
          </div>
        </article>
        <article className="dashboard-metric-card">
          <div className="dashboard-metric-icon icon-open" aria-hidden="true">◷</div>
          <div className="dashboard-metric-content">
            <span>Otvorene ponude</span>
            <strong>{loading ? "—" : metrics.open}</strong>
            <small>{loading ? "Učitavanje…" : `${metrics.prep} u pripremi · ${metrics.sent} poslanih`}</small>
          </div>
        </article>
        <article className="dashboard-metric-card metric-value">
          <div className="dashboard-metric-icon icon-value" aria-hidden="true">€</div>
          <div className="dashboard-metric-content">
            <span>Vrijednost ponuda</span>
            <strong>{loading ? "—" : currency.format(metrics.currentValue)}</strong>
            <small>{periodRange.label}</small>
          </div>
        </article>
        <article className="dashboard-metric-card">
          <div className="dashboard-metric-icon icon-follow" aria-hidden="true">↗</div>
          <div className="dashboard-metric-content">
            <span>Za praćenje</span>
            <strong>{loading ? "—" : followUpQuotes.length}</strong>
            <small>Poslane ponude starije od 7 dana ili s dospjelim kontaktom</small>
          </div>
        </article>
      </section>

      <section className="dashboard-quick-actions" aria-label="Brze akcije">
        {canCreateQuote && <button type="button" onClick={() => navigate("/new-quote")} className="dashboard-quick-action quick-action-highlight">
          <span className="quick-action-icon" aria-hidden="true">＋</span>
          <span><strong>Nova ponuda</strong><small>Započni novu kalkulaciju</small></span>
          <span className="quick-action-arrow" aria-hidden="true">→</span>
        </button>}
        {canCreateQuote && <button type="button" onClick={() => navigate("/new-quote")} className="dashboard-quick-action">
          <span className="quick-action-icon" aria-hidden="true">⇧</span>
          <span><strong>Uvoz upita kupca</strong><small>Excel ili tekstualni PDF</small></span>
          <span className="quick-action-arrow" aria-hidden="true">→</span>
        </button>}
        <button type="button" onClick={() => navigate("/quotes")} className="dashboard-quick-action">
          <span className="quick-action-icon" aria-hidden="true">⌕</span>
          <span><strong>Pretraži ponude</strong><small>Pregled i upravljanje ponudama</small></span>
          <span className="quick-action-arrow" aria-hidden="true">→</span>
        </button>
        <button type="button" onClick={() => navigate("/positions")} className="dashboard-quick-action">
          <span className="quick-action-icon" aria-hidden="true">▦</span>
          <span><strong>Pretraži poziciju</strong><small>Pronađi raniju kalkulaciju</small></span>
          <span className="quick-action-arrow" aria-hidden="true">→</span>
        </button>
      </section>

      <section className="dashboard-content-grid">
        <article className="dashboard-panel dashboard-recent-panel">
          <div className="dashboard-panel-heading">
            <div>
              <h2>Nedavne ponude</h2>
              <p>Ponude kreirane ili ažurirane u posljednje vrijeme.</p>
            </div>
            <button type="button" className="dashboard-text-link" onClick={() => navigate("/quotes")}>Sve ponude <span aria-hidden="true">→</span></button>
          </div>

          {loading ? (
            <div className="dashboard-empty-state">Učitavanje ponuda…</div>
          ) : sortedQuotes.length === 0 ? (
            <div className="dashboard-empty-state">
              <span className="dashboard-empty-icon" aria-hidden="true">▤</span>
              <strong>Još nema spremljenih ponuda</strong>
              <span>Kada napraviš prvu ponudu, ovdje će se prikazati pregled.</span>
              {canCreateQuote && <button type="button" onClick={() => navigate("/new-quote")}>Kreiraj prvu ponudu</button>}
            </div>
          ) : (
            <div className="dashboard-table-wrap">
              <table className="dashboard-table">
                <thead>
                  <tr><th>Broj ponude</th><th>Kupac</th><th>Datum</th><th>Status</th><th className="dashboard-align-right">Prodajna vrijednost</th><th aria-label="Akcije" /></tr>
                </thead>
                <tbody>
                  {sortedQuotes.slice(0, 8).map((quote) => (
                    <tr key={quote.id ?? quote.brojPonude}>
                      <td><button type="button" className="dashboard-quote-number" onClick={() => openQuote(quote)}>{quote.brojPonude || "Bez broja"}</button></td>
                      <td className="dashboard-customer-cell">{quote.kupacNaziv || "Nepoznat kupac"}</td>
                      <td>{formatDate(quoteDate(quote))}</td>
                      <td><span className={statusClass(quoteStatus(quote))}>{statusLabel(quoteStatus(quote))}</span></td>
                      <td className="dashboard-align-right dashboard-value-cell">{currency.format(quoteValue(quote))}</td>
                      <td><button type="button" className="dashboard-row-open" onClick={() => openQuote(quote)} aria-label={`Otvori ponudu ${quote.brojPonude || ""}`}>Otvori</button></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </article>

        <aside className="dashboard-side-panels">
          <article className="dashboard-panel dashboard-follow-panel">
            <div className="dashboard-panel-heading">
              <div>
                <h2>Ponude za praćenje</h2>
                <p>Zahtijevaju provjeru odgovora kupca.</p>
              </div>
              <span className="dashboard-panel-count">{loading ? "—" : followUpQuotes.length}</span>
            </div>
            {loading ? (
              <div className="dashboard-empty-state compact">Učitavanje…</div>
            ) : followUpQuotes.length === 0 ? (
              <div className="dashboard-follow-empty"><span aria-hidden="true">✓</span><div><strong>Nema ponuda za praćenje</strong><p>Nema poslanih ponuda sa dospjelim kontaktom ili starijih od 7 dana.</p></div></div>
            ) : (
              <div className="dashboard-follow-list">
                {followUpQuotes.slice(0, 5).map((quote) => {
                  const followUpDate = getFollowUpDate(quote)
                  const age = daysSince(quoteDate(quote))
                  const detail = followUpDate
                    ? `Kontakt do ${formatDate(followUpDate)}`
                    : age === null ? "Čeka odgovor" : `Poslana prije ${age} ${age === 1 ? "dan" : "dana"}`
                  return (
                    <div className="dashboard-follow-item" key={quote.id ?? quote.brojPonude}>
                      <span className="dashboard-follow-dot" aria-hidden="true" />
                      <div className="dashboard-follow-details">
                        <strong>{quote.kupacNaziv || "Nepoznat kupac"}</strong>
                        <span>{quote.brojPonude || "Bez broja"}</span>
                        <small>{detail}</small>
                      </div>
                      <button type="button" onClick={() => openQuote(quote)} aria-label={`Otvori ${quote.brojPonude || "ponudu"}`}>→</button>
                    </div>
                  )
                })}
                {followUpQuotes.length > 5 && <button type="button" className="dashboard-view-all-follow" onClick={() => navigate("/quotes")}>Prikaži sve ponude za praćenje</button>}
              </div>
            )}
          </article>

          <article className="dashboard-panel dashboard-status-panel">
            <div className="dashboard-panel-heading">
              <div><h2>Statusi ponuda</h2><p>Raspored svih spremljenih ponuda.</p></div>
              <span className="dashboard-total-pill">{loading ? "—" : sortedQuotes.length} ukupno</span>
            </div>
            {statusCounts.map((item) => (
              <div className="dashboard-status-row" key={item.status}>
                <div className="dashboard-status-row-label"><span>{item.label}</span><strong>{loading ? "—" : item.count}</strong></div>
                <div className="dashboard-status-track"><span className={item.css} style={{ width: `${loading ? 0 : (item.count / statusMax) * 100}%` }} /></div>
              </div>
            ))}
          </article>
        </aside>
      </section>

      <footer className="dashboard-footnote">
        <span className="dashboard-footnote-dot" aria-hidden="true" />
        <span>Prikaz se zasniva na podacima iz spremljenih ponuda. Vrijednosti ne uključuju ponude koje još nisu sačuvane.</span>
        <button type="button" onClick={() => void loadQuotes()} disabled={loading}>{loading ? "Osvježavanje…" : "↻ Osvježi podatke"}</button>
      </footer>
    </div>
  )
}

export default Dashboard
