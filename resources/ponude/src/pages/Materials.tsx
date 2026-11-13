import { useEffect, useMemo, useState, type FormEvent } from "react"
import { useAuth } from "../auth/AuthContext"
import { hasPermission } from "../auth/permissions"
import { LocalizedNumberInput } from "../components/LocalizedNumberInput"

import {
  addMaterial,
  getMaterials,
  INITIAL_MATERIALS,
  seedMissingInitialMaterials,
  updateMaterial,
  type MaterialRecord,
} from "../services/materials"

import "./Materials.css"

type MaterialForm = Omit<MaterialRecord, "id" | "createdAt" | "updatedAt">

const emptyForm: MaterialForm = {
  oznaka: "",
  naziv: "",
  grupa: "Čelik",
  standard: "",
  gustina: 7850,
  cijenaPoKg: 0,
  aktivno: true,
  napomena: "",
}

const formatNumber = (value: number) =>
  Number(value || 0).toLocaleString("bs-BA", {
    maximumFractionDigits: 3,
  })

function Materials() {
  const { profile } = useAuth()
  const canCreateMaterial = hasPermission(profile, "materials", "create")
  const canUpdateMaterial = hasPermission(profile, "materials", "update")
  const [materials, setMaterials] = useState<MaterialRecord[]>([])
  const [form, setForm] = useState<MaterialForm>(emptyForm)
  const [editingId, setEditingId] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [seeding, setSeeding] = useState(false)
  const [search, setSearch] = useState("")
  const [statusFilter, setStatusFilter] = useState<"all" | "active" | "inactive">("active")
  const [error, setError] = useState("")
  const [message, setMessage] = useState("")

  async function loadMaterials() {
    setLoading(true)
    setError("")
    try {
      setMaterials(await getMaterials())
    } catch (loadError) {
      console.error("Greška pri učitavanju materijala:", loadError)
      setError("Materijale nije moguće učitati iz Firebasea. Provjeri vezu i Firestore pravila.")
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void loadMaterials()
  }, [])

  const missingInitialCount = useMemo(() => {
    const codes = new Set(materials.map((item) => item.oznaka.trim().toLocaleUpperCase()))
    return INITIAL_MATERIALS.filter((item) => !codes.has(item.oznaka.toLocaleUpperCase())).length
  }, [materials])

  const filteredMaterials = useMemo(() => {
    const query = search.trim().toLocaleLowerCase()
    return materials.filter((item) => {
      const matchesStatus = statusFilter === "all"
        || (statusFilter === "active" && item.aktivno !== false)
        || (statusFilter === "inactive" && item.aktivno === false)
      const haystack = [item.oznaka, item.naziv, item.grupa, item.standard, item.napomena]
        .join(" ")
        .toLocaleLowerCase()
      return matchesStatus && (!query || haystack.includes(query))
    })
  }, [materials, search, statusFilter])

  function resetForm() {
    setForm(emptyForm)
    setEditingId(null)
    setError("")
  }

  function editMaterial(material: MaterialRecord) {
    if (!material.id) return
    setEditingId(material.id)
    setForm({
      oznaka: material.oznaka,
      naziv: material.naziv,
      grupa: material.grupa,
      standard: material.standard,
      gustina: material.gustina,
      cijenaPoKg: material.cijenaPoKg,
      aktivno: material.aktivno !== false,
      napomena: material.napomena ?? "",
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
    const density = Number(form.gustina)
    const price = Number(form.cijenaPoKg)

    if (!code || !name) {
      setError("Unesi oznaku i naziv materijala.")
      return
    }
    if (!Number.isFinite(density) || density <= 0) {
      setError("Gustina mora biti broj veći od nule.")
      return
    }
    if (!Number.isFinite(price) || price < 0) {
      setError("Nabavna cijena ne može biti negativna.")
      return
    }

    const duplicate = materials.find((item) =>
      item.oznaka.trim().toLocaleUpperCase() === code.toLocaleUpperCase()
      && item.id !== editingId,
    )
    if (duplicate) {
      setError(`Materijal s oznakom "${duplicate.oznaka}" već postoji.`)
      return
    }

    const payload: MaterialForm = {
      ...form,
      oznaka: code,
      naziv: name,
      grupa: form.grupa.trim(),
      standard: form.standard.trim(),
      gustina: density,
      cijenaPoKg: price,
      aktivno: form.aktivno,
      napomena: form.napomena.trim(),
    }

    try {
      setSaving(true)
      if (editingId) {
        await updateMaterial(editingId, payload)
        setMessage(`Materijal "${code}" je ažuriran.`)
      } else {
        await addMaterial(payload)
        setMessage(`Materijal "${code}" je dodan.`)
      }
      resetForm()
      await loadMaterials()
    } catch (saveError) {
      console.error("Greška pri spremanju materijala:", saveError)
      setError("Materijal nije spremljen. Provjeri da li si prijavljen kao administrator i da li su Firestore pravila objavljena.")
    } finally {
      setSaving(false)
    }
  }

  async function toggleActive(material: MaterialRecord) {
    if (!material.id) return
    const activate = material.aktivno === false
    const confirmed = window.confirm(
      activate
        ? `Aktivirati materijal "${material.oznaka}"?`
        : `Deaktivirati materijal "${material.oznaka}"? Materijal ostaje u historijskim ponudama, ali se više neće nuditi za nove kalkulacije.`,
    )
    if (!confirmed) return

    setError("")
    setMessage("")
    try {
      await updateMaterial(material.id, { aktivno: activate })
      setMessage(`Materijal "${material.oznaka}" je ${activate ? "aktiviran" : "deaktiviran"}.`)
      await loadMaterials()
    } catch (toggleError) {
      console.error("Greška pri promjeni statusa materijala:", toggleError)
      setError("Status materijala nije promijenjen. Provjeri administratorska prava.")
    }
  }

  async function handleSeedInitialMaterials() {
    const confirmed = window.confirm(
      `Dodati ${missingInitialCount} početnih materijala koji nedostaju? Cijene su preuzete iz starog kalkulatora i treba ih provjeriti prema aktuelnim nabavnim cijenama.`,
    )
    if (!confirmed) return

    setError("")
    setMessage("")
    try {
      setSeeding(true)
      const count = await seedMissingInitialMaterials(materials)
      setMessage(count ? `Dodano početnih materijala: ${count}. Provjeri njihove nabavne cijene prije upotrebe u ponudama.` : "Svi početni materijali već postoje u bazi.")
      await loadMaterials()
    } catch (seedError) {
      console.error("Greška pri dodavanju početnih materijala:", seedError)
      setError("Početni materijali nisu u potpunosti dodani. Provjeri Firestore pravila i osvježi stranicu prije ponovnog pokušaja.")
      await loadMaterials()
    } finally {
      setSeeding(false)
    }
  }

  return (
    <div className="materials-page">
      <header className="materials-header">
        <div>
          <h1>Materijali</h1>
          <p>Baza materijala i nabavnih cijena za CNC kalkulaciju.</p>
        </div>
        <div className="materials-header-actions">
          <button type="button" className="materials-button materials-button-secondary" onClick={() => void loadMaterials()} disabled={loading}>
            Osvježi
          </button>
          {canCreateMaterial && missingInitialCount > 0 && (
            <button type="button" className="materials-button materials-button-primary" onClick={() => void handleSeedInitialMaterials()} disabled={seeding || saving}>
              {seeding ? "Dodavanje..." : `Dodaj početne materijale (${missingInitialCount})`}
            </button>
          )}
        </div>
      </header>

      <div className="materials-info">
        <strong>Napomena:</strong> Početne cijene prenesene su iz dosadašnjeg kalkulatora. Prije upotrebe u stvarnim ponudama potvrdi ih prema aktuelnim cijenama dobavljača. Deaktivacija materijala čuva ga u bazi i ne briše historijske ponude.
      </div>

      {error && <div className="materials-alert materials-alert-error" role="alert">{error}</div>}
      {message && <div className="materials-alert materials-alert-success" role="status">{message}</div>}

      {(canCreateMaterial || canUpdateMaterial) && <section className="materials-card">
        <div className="materials-section-heading">
          <div>
            <h2>{editingId ? "Uredi materijal" : "Novi materijal"}</h2>
            <p>{editingId ? "Izmijeni tehničke podatke ili nabavnu cijenu." : "Dodaj materijal koji će se koristiti u kalkulaciji."}</p>
          </div>
          {editingId && <button type="button" className="materials-button materials-button-secondary" onClick={resetForm}>Odustani od uređivanja</button>}
        </div>

        <form onSubmit={handleSubmit}>
          <div className="materials-form-grid">
            <label>
              Oznaka materijala *
              <input required value={form.oznaka} disabled={Boolean(editingId)} onChange={(event) => setForm((previous) => ({ ...previous, oznaka: event.target.value }))} placeholder="npr. S235" />
              {editingId && <small>Oznaka se ne mijenja nakon kreiranja jer se koristi kao ključ u postojećim pozicijama.</small>}
            </label>
            <label>
              Naziv *
              <input required value={form.naziv} onChange={(event) => setForm((previous) => ({ ...previous, naziv: event.target.value }))} placeholder="npr. Konstrukcijski čelik" />
            </label>
            <label>
              Grupa
              <input value={form.grupa} onChange={(event) => setForm((previous) => ({ ...previous, grupa: event.target.value }))} placeholder="Čelik, aluminij..." />
            </label>
            <label>
              Standard
              <input value={form.standard} onChange={(event) => setForm((previous) => ({ ...previous, standard: event.target.value }))} placeholder="npr. EN ..." />
            </label>
            <label>
              Gustina (kg/m³) *
              <LocalizedNumberInput required min="0.001" step="any" value={form.gustina} onValueChange={(value) => setForm((previous) => ({ ...previous, gustina: value }))} />
            </label>
            <label>
              Nabavna cijena (€/kg) *
              <LocalizedNumberInput required min="0" step="0.0001" value={form.cijenaPoKg} onValueChange={(value) => setForm((previous) => ({ ...previous, cijenaPoKg: value }))} />
            </label>
            <label className="materials-active-toggle">
              <input type="checkbox" checked={form.aktivno} onChange={(event) => setForm((previous) => ({ ...previous, aktivno: event.target.checked }))} />
              Aktivan materijal
            </label>
            <label className="materials-wide-field">
              Napomena
              <textarea rows={2} value={form.napomena} onChange={(event) => setForm((previous) => ({ ...previous, napomena: event.target.value }))} placeholder="Dobavljač, komentar ili druge informacije" />
            </label>
          </div>

          <div className="materials-form-actions">
            {(editingId ? canUpdateMaterial : canCreateMaterial) && <button type="submit" className="materials-button materials-button-primary" disabled={saving || loading}>
              {saving ? "Spremanje..." : editingId ? "Sačuvaj izmjene" : "Dodaj materijal"}
            </button>}
            {!editingId && canCreateMaterial && <button type="button" className="materials-button materials-button-secondary" onClick={() => setForm(emptyForm)}>Očisti polja</button>}
          </div>
        </form>
      </section>}

      <section className="materials-card">
        <div className="materials-section-heading">
          <div>
            <h2>Pregled materijala</h2>
            <p>Ukupno u bazi: {materials.length} · Aktivni: {materials.filter((item) => item.aktivno !== false).length}</p>
          </div>
          <div className="materials-filters">
            <input aria-label="Pretraži materijale" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Pretraži oznaku, naziv, grupu..." />
            <select aria-label="Filter statusa materijala" value={statusFilter} onChange={(event) => setStatusFilter(event.target.value as typeof statusFilter)}>
              <option value="active">Aktivni</option>
              <option value="inactive">Neaktivni</option>
              <option value="all">Svi</option>
            </select>
          </div>
        </div>

        {loading ? (
          <div className="materials-empty-state">Učitavanje materijala...</div>
        ) : filteredMaterials.length === 0 ? (
          <div className="materials-empty-state">Nema materijala za prikaz u odabranom filteru.</div>
        ) : (
          <div className="materials-table-wrap">
            <table className="materials-table">
              <thead>
                <tr>
                  <th>Oznaka</th><th>Naziv</th><th>Grupa</th><th>Standard</th><th>Gustina (kg/m³)</th><th>Nabavna cijena (€/kg)</th><th>Status</th><th>Akcije</th>
                </tr>
              </thead>
              <tbody>
                {filteredMaterials.map((material) => (
                  <tr key={material.id ?? material.oznaka}>
                    <td><strong>{material.oznaka}</strong></td>
                    <td>{material.naziv}</td>
                    <td>{material.grupa || "—"}</td>
                    <td>{material.standard || "—"}</td>
                    <td className="materials-number-cell">{formatNumber(material.gustina)}</td>
                    <td className="materials-number-cell">{formatNumber(material.cijenaPoKg)} €</td>
                    <td><span className={material.aktivno === false ? "materials-status materials-status-inactive" : "materials-status materials-status-active"}>{material.aktivno === false ? "Neaktivan" : "Aktivan"}</span></td>
                    <td>
                      <div className="materials-row-actions">
                        {canUpdateMaterial && <>
                          <button type="button" className="materials-button materials-button-small materials-button-secondary" onClick={() => editMaterial(material)}>Uredi</button>
                          <button type="button" className="materials-button materials-button-small materials-button-secondary" onClick={() => void toggleActive(material)}>{material.aktivno === false ? "Aktiviraj" : "Deaktiviraj"}</button>
                        </>}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>
    </div>
  )
}

export default Materials
