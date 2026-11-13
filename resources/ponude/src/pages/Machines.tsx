import { useEffect, useMemo, useState, type FormEvent } from "react"
import { useAuth } from "../auth/AuthContext"
import { hasPermission } from "../auth/permissions"
import { LocalizedNumberInput } from "../components/LocalizedNumberInput"

import {
  addMachine,
  getMachines,
  INITIAL_MACHINES,
  seedMissingInitialMachines,
  updateMachine,
  type MachineRecord,
} from "../services/machines"

import "./Machines.css"

type MachineForm = Omit<MachineRecord, "id" | "createdAt" | "updatedAt">

const emptyForm: MachineForm = {
  oznaka: "",
  naziv: "",
  tip: "",
  satnicaProgramiranja: 35,
  satnicaPripreme: 32,
  satnicaObrade: 32,
  aktivna: true,
  napomena: "",
}

const money = (value: number) => Number(value || 0).toLocaleString("bs-BA", {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
})

function Machines() {
  const { profile } = useAuth()
  const canCreateMachine = hasPermission(profile, "machines", "create")
  const canUpdateMachine = hasPermission(profile, "machines", "update")
  const [machines, setMachines] = useState<MachineRecord[]>([])
  const [form, setForm] = useState<MachineForm>(emptyForm)
  const [editingId, setEditingId] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [seeding, setSeeding] = useState(false)
  const [search, setSearch] = useState("")
  const [statusFilter, setStatusFilter] = useState<"all" | "active" | "inactive">("active")
  const [error, setError] = useState("")
  const [message, setMessage] = useState("")

  async function loadMachines() {
    setLoading(true)
    setError("")
    try {
      setMachines(await getMachines())
    } catch (loadError) {
      console.error("Greška pri učitavanju mašina:", loadError)
      setError("Mašine nije moguće učitati iz Firebasea. Provjeri vezu i Firestore pravila.")
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => { void loadMachines() }, [])

  const missingInitialCount = useMemo(() => {
    const codes = new Set(machines.map((item) => item.oznaka.trim().toLocaleUpperCase()))
    return INITIAL_MACHINES.filter((item) => !codes.has(item.oznaka.toLocaleUpperCase())).length
  }, [machines])

  const filteredMachines = useMemo(() => {
    const query = search.trim().toLocaleLowerCase()
    return machines.filter((item) => {
      const matchesStatus = statusFilter === "all"
        || (statusFilter === "active" && item.aktivna !== false)
        || (statusFilter === "inactive" && item.aktivna === false)
      const haystack = [item.oznaka, item.naziv, item.tip, item.napomena].join(" ").toLocaleLowerCase()
      return matchesStatus && (!query || haystack.includes(query))
    })
  }, [machines, search, statusFilter])

  function resetForm() {
    setForm(emptyForm)
    setEditingId(null)
    setError("")
  }

  function editMachine(machine: MachineRecord) {
    if (!machine.id) return
    setEditingId(machine.id)
    setForm({
      oznaka: machine.oznaka,
      naziv: machine.naziv,
      tip: machine.tip,
      satnicaProgramiranja: machine.satnicaProgramiranja,
      satnicaPripreme: machine.satnicaPripreme,
      satnicaObrade: machine.satnicaObrade,
      aktivna: machine.aktivna !== false,
      napomena: machine.napomena ?? "",
    })
    setMessage("")
    setError("")
    window.scrollTo({ top: 0, behavior: "smooth" })
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError("")
    setMessage("")

    const code = form.oznaka.trim()
    const name = form.naziv.trim()
    const programmingRate = Number(form.satnicaProgramiranja)
    const prepRate = Number(form.satnicaPripreme)
    const machiningRate = Number(form.satnicaObrade)

    if (!code || !name) {
      setError("Unesi oznaku i naziv mašine.")
      return
    }
    if (
      !Number.isFinite(programmingRate) || programmingRate < 0 ||
      !Number.isFinite(prepRate) || prepRate < 0 ||
      !Number.isFinite(machiningRate) || machiningRate < 0
    ) {
      setError("Sve satnice moraju biti nenegativni brojevi.")
      return
    }
    const duplicate = machines.find((item) =>
      item.oznaka.trim().toLocaleUpperCase() === code.toLocaleUpperCase() && item.id !== editingId,
    )
    if (duplicate) {
      setError(`Mašina s oznakom "${duplicate.oznaka}" već postoji.`)
      return
    }

    const payload: MachineForm = {
      ...form,
      oznaka: code,
      naziv: name,
      tip: form.tip.trim(),
      satnicaProgramiranja: programmingRate,
      satnicaPripreme: prepRate,
      satnicaObrade: machiningRate,
      aktivna: form.aktivna,
      napomena: form.napomena.trim(),
    }

    try {
      setSaving(true)
      if (editingId) {
        await updateMachine(editingId, payload)
        setMessage(`Mašina "${name}" je ažurirana.`)
      } else {
        await addMachine(payload)
        setMessage(`Mašina "${name}" je dodana.`)
      }
      resetForm()
      await loadMachines()
    } catch (saveError) {
      console.error("Greška pri spremanju mašine:", saveError)
      setError("Mašina nije spremljena. Provjeri da li si prijavljen kao administrator i da li su Firestore pravila objavljena.")
    } finally {
      setSaving(false)
    }
  }

  async function toggleActive(machine: MachineRecord) {
    if (!machine.id) return
    const activate = machine.aktivna === false
    const confirmed = window.confirm(
      activate
        ? `Aktivirati mašinu "${machine.naziv}"?`
        : `Deaktivirati mašinu "${machine.naziv}"? Mašina će ostati u historijskim ponudama, ali se više neće nuditi za nove kalkulacije.`,
    )
    if (!confirmed) return
    setError("")
    setMessage("")
    try {
      await updateMachine(machine.id, { aktivna: activate })
      setMessage(`Mašina "${machine.naziv}" je ${activate ? "aktivirana" : "deaktivirana"}.`)
      await loadMachines()
    } catch (toggleError) {
      console.error("Greška pri promjeni statusa mašine:", toggleError)
      setError("Status mašine nije promijenjen. Provjeri administratorska prava.")
    }
  }

  async function handleSeedInitialMachines() {
    const confirmed = window.confirm(
      `Dodati ${missingInitialCount} početnih mašina koje nedostaju? Satnice su prenesene iz starog kalkulatora. Provjeri ih prije upotrebe u stvarnim ponudama.`,
    )
    if (!confirmed) return
    setError("")
    setMessage("")
    try {
      setSeeding(true)
      const count = await seedMissingInitialMachines(machines)
      setMessage(count
        ? `Dodano početnih mašina: ${count}. Potvrdi satnice prije izrade stvarnih ponuda.`
        : "Sve početne mašine već postoje u bazi.")
      await loadMachines()
    } catch (seedError) {
      console.error("Greška pri dodavanju početnih mašina:", seedError)
      setError("Početne mašine nisu u potpunosti dodane. Osvježi stranicu i provjeri Firestore pravila prije ponovnog pokušaja.")
      await loadMachines()
    } finally {
      setSeeding(false)
    }
  }

  return (
    <div className="machines-page">
      <header className="machines-header">
        <div>
          <h1>Mašine i satnice</h1>
          <p>Baza CNC mašina sa zasebnim satnicama programiranja, pripreme i obrade.</p>
        </div>
        <div className="machines-header-actions">
          <button type="button" className="machines-button machines-button-secondary" onClick={() => void loadMachines()} disabled={loading}>Osvježi</button>
          {canCreateMachine && missingInitialCount > 0 && (
            <button type="button" className="machines-button machines-button-primary" onClick={() => void handleSeedInitialMachines()} disabled={seeding || saving}>
              {seeding ? "Dodavanje..." : `Dodaj početne mašine (${missingInitialCount})`}
            </button>
          )}
        </div>
      </header>

      <div className="machines-info">
        <strong>Napomena:</strong> Satnice programiranja, pripreme i obrade podešavaju se zasebno za svaku mašinu. Starije mašine bez unesene satnice programiranja zadržavaju dosadašnju vrijednost od 35 €/h. Mašine deaktiviramo umjesto da ih brišemo, kako bismo sačuvali historijske ponude.
      </div>

      {error && <div className="machines-alert machines-alert-error" role="alert">{error}</div>}
      {message && <div className="machines-alert machines-alert-success" role="status">{message}</div>}

      {(canCreateMachine || canUpdateMachine) && <section className="machines-card">
        <div className="machines-section-heading">
          <div>
            <h2>{editingId ? "Uredi mašinu" : "Nova mašina"}</h2>
            <p>{editingId ? "Izmijeni naziv, vrstu ili satnice." : "Dodaj CNC mašinu u bazu kalkulacije."}</p>
          </div>
          {editingId && <button type="button" className="machines-button machines-button-secondary" onClick={resetForm}>Odustani od uređivanja</button>}
        </div>

        <form onSubmit={handleSubmit}>
          <div className="machines-form-grid">
            <label>
              Oznaka mašine *
              <input required value={form.oznaka} disabled={Boolean(editingId)} onChange={(event) => setForm((previous) => ({ ...previous, oznaka: event.target.value }))} placeholder="npr. Haas_UMC500" />
              {editingId && <small>Oznaka se ne mijenja jer se koristi u postojećim i novim pozicijama.</small>}
            </label>
            <label>
              Naziv mašine *
              <input required value={form.naziv} onChange={(event) => setForm((previous) => ({ ...previous, naziv: event.target.value }))} placeholder="npr. Haas UMC500" />
            </label>
            <label>
              Vrsta mašine
              <input value={form.tip} onChange={(event) => setForm((previous) => ({ ...previous, tip: event.target.value }))} placeholder="npr. CNC glodalica, 5 osa" />
            </label>
            <label>
              Satnica programiranja (€/h) *
              <LocalizedNumberInput required min="0" step="0.01" value={form.satnicaProgramiranja} onValueChange={(value) => setForm((previous) => ({ ...previous, satnicaProgramiranja: value }))} />
            </label>
            <label>
              Satnica pripreme (€/h) *
              <LocalizedNumberInput required min="0" step="0.01" value={form.satnicaPripreme} onValueChange={(value) => setForm((previous) => ({ ...previous, satnicaPripreme: value }))} />
            </label>
            <label>
              Satnica obrade (€/h) *
              <LocalizedNumberInput required min="0" step="0.01" value={form.satnicaObrade} onValueChange={(value) => setForm((previous) => ({ ...previous, satnicaObrade: value }))} />
            </label>
            <label className="machines-checkbox-label">
              <input type="checkbox" checked={form.aktivna} onChange={(event) => setForm((previous) => ({ ...previous, aktivna: event.target.checked }))} />
              Aktivna mašina
            </label>
            <label className="machines-full-width">
              Napomena
              <textarea rows={3} value={form.napomena} onChange={(event) => setForm((previous) => ({ ...previous, napomena: event.target.value }))} placeholder="Dodatne informacije..." />
            </label>
          </div>
          <div className="machines-form-actions">
            <button type="submit" className="machines-button machines-button-primary" disabled={saving}>{saving ? "Spremanje..." : editingId ? "Sačuvaj izmjene" : "Dodaj mašinu"}</button>
            {!editingId && <button type="button" className="machines-button machines-button-secondary" onClick={resetForm} disabled={saving}>Očisti polja</button>}
          </div>
        </form>
      </section>}

      <section className="machines-card">
        <div className="machines-section-heading">
          <div>
            <h2>Pregled mašina</h2>
            <p>Ukupno evidentirano: {machines.length}. Aktivne: {machines.filter((item) => item.aktivna !== false).length}.</p>
          </div>
          <div className="machines-filters">
            <input aria-label="Pretraga mašina" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Traži mašinu..." />
            <select aria-label="Filter statusa mašina" value={statusFilter} onChange={(event) => setStatusFilter(event.target.value as "all" | "active" | "inactive")}>
              <option value="active">Samo aktivne</option>
              <option value="inactive">Samo neaktivne</option>
              <option value="all">Sve mašine</option>
            </select>
          </div>
        </div>

        {loading ? <div className="machines-empty">Učitavanje mašina...</div> : filteredMachines.length === 0 ? <div className="machines-empty">Nema mašina za odabrani filter.</div> : (
          <div className="machines-table-wrapper">
            <table className="machines-table">
              <thead><tr><th>Oznaka</th><th>Naziv</th><th>Vrsta</th><th>Programiranje (€/h)</th><th>Priprema (€/h)</th><th>Obrada (€/h)</th><th>Status</th><th>Akcije</th></tr></thead>
              <tbody>{filteredMachines.map((machine) => (
                <tr key={machine.id || machine.oznaka}>
                  <td><strong>{machine.oznaka}</strong></td>
                  <td>{machine.naziv}</td>
                  <td>{machine.tip || "—"}</td>
                  <td className="machines-number">{money(machine.satnicaProgramiranja)}</td>
                  <td className="machines-number">{money(machine.satnicaPripreme)}</td>
                  <td className="machines-number">{money(machine.satnicaObrade)}</td>
                  <td><span className={machine.aktivna !== false ? "machines-status-active" : "machines-status-inactive"}>{machine.aktivna !== false ? "Aktivna" : "Neaktivna"}</span></td>
                  <td><div className="machines-row-actions">
                    {canUpdateMachine && <>
                      <button type="button" className="machines-button machines-button-secondary" onClick={() => editMachine(machine)}>Uredi</button>
                      <button type="button" className="machines-button machines-button-secondary" onClick={() => void toggleActive(machine)}>{machine.aktivna !== false ? "Deaktiviraj" : "Aktiviraj"}</button>
                    </>}
                  </div></td>
                </tr>
              ))}</tbody>
            </table>
          </div>
        )}
      </section>
    </div>
  )
}

export default Machines
