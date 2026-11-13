import { useCallback, useEffect, useMemo, useState } from "react"
import {
  getRecentActivityLogs,
  type ActivityAction,
  type ActivityEntity,
  type ActivityLogEntry,
} from "../services/activityLog"
import "./ActivityLog.css"

const ACTION_LABELS: Record<ActivityAction, string> = {
  create: "Kreiranje",
  update: "Izmjena",
  delete: "Brisanje",
  activate: "Aktiviranje",
  deactivate: "Deaktiviranje",
  reset_password: "Reset lozinke",
}

const ENTITY_LABELS: Record<ActivityEntity, string> = {
  quote: "Ponuda",
  position: "Pozicija",
  customer: "Kupac",
  material: "Materijal",
  machine: "Mašina / satnica",
  surfaceProtection: "Površinska zaštita",
  heatTreatment: "Termička obrada",
  otherOperation: "Ostali zahvat",
  user: "Korisnik",
  settings: "Postavke",
}

function formatActivityDate(value: unknown): string {
  if (!value) return "Datum nije dostupan"
  try {
    const date = typeof (value as { toDate?: unknown }).toDate === "function"
      ? (value as { toDate: () => Date }).toDate()
      : value instanceof Date
        ? value
        : new Date(value as string | number)
    if (!Number.isFinite(date.getTime())) return "Datum nije dostupan"
    return new Intl.DateTimeFormat("bs-BA", {
      day: "2-digit",
      month: "2-digit",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit",
      second: "2-digit",
    }).format(date)
  } catch {
    return "Datum nije dostupan"
  }
}

function ActivityLog() {
  const [entries, setEntries] = useState<ActivityLogEntry[]>([])
  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)
  const [error, setError] = useState("")
  const [search, setSearch] = useState("")
  const [actionFilter, setActionFilter] = useState("all")
  const [entityFilter, setEntityFilter] = useState("all")

  const loadEntries = useCallback(async (showRefresh = false) => {
    if (showRefresh) setRefreshing(true)
    else setLoading(true)
    setError("")
    try {
      setEntries(await getRecentActivityLogs(300))
    } catch (loadError) {
      console.error("Greška pri učitavanju evidencije aktivnosti:", loadError)
      setError("Evidenciju aktivnosti nije moguće učitati. Provjeri administratorski pristup i Firestore pravila.")
    } finally {
      setLoading(false)
      setRefreshing(false)
    }
  }, [])

  useEffect(() => {
    void loadEntries()
  }, [loadEntries])

  const filteredEntries = useMemo(() => {
    const term = search.trim().toLocaleLowerCase()
    return entries.filter((entry) => {
      const matchesSearch = !term || [
        entry.actorName,
        entry.actorEmail,
        entry.description,
        entry.entityLabel,
        entry.entityId,
      ].some((value) => String(value || "").toLocaleLowerCase().includes(term))
      const matchesAction = actionFilter === "all" || entry.action === actionFilter
      const matchesEntity = entityFilter === "all" || entry.entityType === entityFilter
      return matchesSearch && matchesAction && matchesEntity
    })
  }, [entries, search, actionFilter, entityFilter])

  return (
    <div className="activity-log-page">
      <header className="activity-log-header">
        <div>
          <p className="activity-log-eyebrow">ADMINISTRACIJA</p>
          <h1>Evidencija aktivnosti</h1>
          <p>Pregled ko je i kada kreirao, mijenjao ili brisao podatke u sistemu.</p>
        </div>
        <button
          type="button"
          className="activity-refresh-button"
          onClick={() => void loadEntries(true)}
          disabled={loading || refreshing}
        >
          {refreshing ? "Osvježavanje..." : "↻ Osvježi"}
        </button>
      </header>

      <section className="activity-log-card">
        <div className="activity-log-toolbar">
          <div>
            <h2>Posljednje aktivnosti</h2>
            <p>Prikazano je najviše 300 posljednjih zapisa.</p>
          </div>
          <div className="activity-filters">
            <input
              type="search"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Pretraži korisnika ili aktivnost..."
              aria-label="Pretraži evidenciju aktivnosti"
            />
            <select value={actionFilter} onChange={(event) => setActionFilter(event.target.value)} aria-label="Filtriraj po radnji">
              <option value="all">Sve radnje</option>
              {Object.entries(ACTION_LABELS).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
            </select>
            <select value={entityFilter} onChange={(event) => setEntityFilter(event.target.value)} aria-label="Filtriraj po modulu">
              <option value="all">Svi moduli</option>
              {Object.entries(ENTITY_LABELS).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
            </select>
          </div>
        </div>

        {error && <div className="activity-alert is-error" role="alert">{error}</div>}
        {loading ? (
          <div className="activity-empty">Učitavanje evidencije...</div>
        ) : filteredEntries.length === 0 ? (
          <div className="activity-empty">{entries.length === 0 ? "Još nema evidentiranih aktivnosti." : "Nema aktivnosti koje odgovaraju filterima."}</div>
        ) : (
          <div className="activity-table-wrap">
            <table className="activity-table">
              <thead>
                <tr>
                  <th>Datum i vrijeme</th>
                  <th>Korisnik</th>
                  <th>Radnja</th>
                  <th>Modul</th>
                  <th>Predmet</th>
                  <th>Detalji</th>
                </tr>
              </thead>
              <tbody>
                {filteredEntries.map((entry) => (
                  <tr key={entry.id}>
                    <td className="activity-date">{formatActivityDate(entry.createdAt)}</td>
                    <td>
                      <strong>{entry.actorName || "Korisnik"}</strong>
                      <small>{entry.actorEmail || ""}</small>
                    </td>
                    <td><span className={`activity-action action-${entry.action}`}>{ACTION_LABELS[entry.action] || entry.action}</span></td>
                    <td>{ENTITY_LABELS[entry.entityType] || entry.entityType}</td>
                    <td><strong>{entry.entityLabel || "-"}</strong></td>
                    <td className="activity-description">{entry.description || "-"}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>

      <p className="activity-log-footnote">
        Evidencija je namijenjena operativnom praćenju rada. Zapisi se dodaju kao novi dokumenti; aplikacija i Firestore pravila ne dozvoljavaju njihovu izmjenu ili brisanje. Na Spark planu evidencija se bilježi iz klijentske aplikacije, pa nije zamjena za serverski, forenzički audit.
      </p>
    </div>
  )
}

export default ActivityLog
