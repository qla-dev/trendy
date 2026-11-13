import { useEffect, useMemo, useState, type FormEvent } from "react"
import {
  ACTION_LABELS,
  createDefaultPermissions,
  PERMISSION_GROUPS,
  validatePermissionDependencies,
  type PermissionAction,
  type PermissionModule,
  type UserPermissions,
} from "../auth/permissions"
import {
  createManagedUser,
  getManagedUsers,
  sendManagedUserPasswordReset,
  updateManagedUser,
  type ManagedUser,
} from "../services/users"
import "./UserManagement.css"

type UserFormState = {
  imePrezime: string
  email: string
  password: string
  aktivan: boolean
  permissions: UserPermissions
}

function createEmptyForm(): UserFormState {
  return {
    imePrezime: "",
    email: "",
    password: "",
    aktivan: true,
    permissions: createDefaultPermissions(),
  }
}

function getEnabledPermissionCount(permissions: UserPermissions): number {
  return PERMISSION_GROUPS.reduce((total, group) => {
    return total + group.actions.filter((action) => permissions[group.key][action] === true).length
  }, 0)
}

function UserManagement() {
  const [users, setUsers] = useState<ManagedUser[]>([])
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [sendingResetUid, setSendingResetUid] = useState<string | null>(null)
  const [modalOpen, setModalOpen] = useState(false)
  const [editingUid, setEditingUid] = useState<string | null>(null)
  const [form, setForm] = useState<UserFormState>(createEmptyForm)
  const [error, setError] = useState("")
  const [message, setMessage] = useState("")
  const [search, setSearch] = useState("")

  async function loadUsers() {
    try {
      setLoading(true)
      setError("")
      setUsers(await getManagedUsers())
    } catch (loadError) {
      console.error("Greška pri učitavanju korisnika:", loadError)
      setError("Korisnike nije moguće učitati. Provjeri administratorski pristup i Firestore pravila.")
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void loadUsers()
  }, [])

  const filteredUsers = useMemo(() => {
    const normalized = search.trim().toLocaleLowerCase()
    if (!normalized) return users
    return users.filter((user) =>
      `${user.imePrezime} ${user.email}`.toLocaleLowerCase().includes(normalized),
    )
  }, [users, search])

  function openCreateForm() {
    setEditingUid(null)
    setForm(createEmptyForm())
    setError("")
    setMessage("")
    setModalOpen(true)
  }

  function openEditForm(user: ManagedUser) {
    if (user.role === "admin") return
    setEditingUid(user.uid)
    setForm({
      imePrezime: user.imePrezime,
      email: user.email,
      password: "",
      aktivan: user.aktivan,
      permissions: user.permissions,
    })
    setError("")
    setMessage("")
    setModalOpen(true)
  }

  function closeForm() {
    if (saving) return
    setModalOpen(false)
    setEditingUid(null)
    setForm(createEmptyForm())
    setError("")
  }

  function setPermission(module: PermissionModule, action: PermissionAction, checked: boolean) {
    setForm((current) => {
      const permissions = {
        ...current.permissions,
        [module]: { ...current.permissions[module] },
      }

      // Pregled ponuda je obavezan; pristup Dashboardu ostaje po izboru administratora.
      if (module === "quotes" && action === "view") return current

      permissions[module][action] = checked

      if (action === "view" && !checked) {
        permissions[module].create = false
        permissions[module].update = false
        permissions[module].delete = false
      }
      if (action !== "view" && checked) {
        permissions[module].view = true
      }

      return { ...current, permissions }
    })
  }

  async function handleSave(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError("")
    setMessage("")

    const name = form.imePrezime.trim()
    const email = form.email.trim().toLowerCase()
    if (name.length < 2) {
      setError("Unesi ime i prezime korisnika.")
      return
    }
    if (!editingUid && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      setError("Unesi ispravnu e-mail adresu.")
      return
    }
    if (!editingUid && form.password.length < 8) {
      setError("Početna lozinka mora imati najmanje 8 znakova.")
      return
    }

    const dependencyError = validatePermissionDependencies(form.permissions)
    if (dependencyError) {
      setError(dependencyError)
      return
    }

    try {
      setSaving(true)
      if (editingUid) {
        await updateManagedUser({
          uid: editingUid,
          imePrezime: name,
          aktivan: form.aktivan,
          permissions: form.permissions,
        })
        setMessage("Korisnički podaci i privilegije su sačuvani.")
      } else {
        await createManagedUser({
          imePrezime: name,
          email,
          password: form.password,
          permissions: form.permissions,
        })
        setMessage("Korisnički račun je uspješno kreiran.")
      }

      setModalOpen(false)
      setEditingUid(null)
      setForm(createEmptyForm())
      await loadUsers()
    } catch (saveError) {
      console.error("Greška pri spremanju korisnika:", saveError)
      const code =
        typeof saveError === "object" && saveError !== null && "code" in saveError
          ? String((saveError as { code?: unknown }).code ?? "")
          : ""
      const detail = saveError instanceof Error ? saveError.message : "Nepoznata greška."
      if (code === "auth/email-already-in-use" || code.includes("already-exists") || detail.includes("Korisnik s ovom e-mail adresom već postoji")) {
        setError("Korisnik s ovom e-mail adresom već postoji.")
      } else if (code === "permission-denied") {
        setError("Kreiranje profila je odbijeno. Provjeri da li su objavljena nova Firestore pravila i da li koristiš administratorski račun.")
      } else if (detail.startsWith("Unesite ") || detail.startsWith("Početna lozinka") || detail.startsWith("Pregled ponuda")) {
        setError(detail)
      } else {
        setError(`Korisnika nije moguće sačuvati. ${detail}`)
      }
    } finally {
      setSaving(false)
    }
  }

  async function handlePasswordReset(user: ManagedUser) {
    const confirmed = window.confirm(
      `Poslati e-mail za promjenu lozinke na adresu ${user.email}?`,
    )
    if (!confirmed) return

    try {
      setSendingResetUid(user.uid)
      await sendManagedUserPasswordReset(user.email)
      setMessage(`E-mail za promjenu lozinke poslan je na ${user.email}.`)
      setError("")
    } catch (resetError) {
      console.error("Greška pri slanju e-maila za promjenu lozinke:", resetError)
      setError("E-mail za promjenu lozinke nije moguće poslati. Provjeri Firebase Authentication postavke.")
    } finally {
      setSendingResetUid(null)
    }
  }

  return (
    <div className="user-management-page">
      <div className="user-management-header">
        <div>
          <p className="user-management-eyebrow">ADMINISTRACIJA SISTEMA</p>
          <h1>Korisnici i privilegije</h1>
          <p>Dodavanje korisničkih računa, dodjela pristupa i deaktiviranje naloga.</p>
        </div>
        <button type="button" className="user-management-primary" onClick={openCreateForm}>
          ＋ Novi korisnik
        </button>
      </div>

      {message && <div className="user-management-alert is-success" role="status">{message}</div>}
      {error && !modalOpen && <div className="user-management-alert is-error" role="alert">{error}</div>}

      <section className="user-management-card">
        <div className="user-management-toolbar">
          <div>
            <h2>Korisnički računi</h2>
            <p>{users.length} korisnika ukupno · Administratorski računi imaju sva prava.</p>
          </div>
          <input
            type="search"
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            placeholder="Pretraži ime ili e-mail..."
            aria-label="Pretraži korisnike"
          />
        </div>

        {loading ? (
          <div className="user-management-empty">Učitavanje korisnika...</div>
        ) : filteredUsers.length === 0 ? (
          <div className="user-management-empty">Nema korisnika koji odgovaraju pretrazi.</div>
        ) : (
          <div className="user-management-table-wrap">
            <table className="user-management-table">
              <thead>
                <tr>
                  <th>Korisnik</th>
                  <th>E-mail</th>
                  <th>Uloga</th>
                  <th>Status</th>
                  <th>Dodijeljena prava</th>
                  <th>Akcije</th>
                </tr>
              </thead>
              <tbody>
                {filteredUsers.map((user) => (
                  <tr key={user.uid}>
                    <td><strong>{user.imePrezime || "Bez imena"}</strong></td>
                    <td>{user.email}</td>
                    <td>{user.role === "admin" ? "Administrator" : "Kalkulant"}</td>
                    <td>
                      <span className={`user-status ${user.aktivan ? "is-active" : "is-inactive"}`}>
                        {user.aktivan ? "Aktivan" : "Neaktivan"}
                      </span>
                    </td>
                    <td>{user.role === "admin" ? "Sva prava" : `${getEnabledPermissionCount(user.permissions)} privilegija`}</td>
                    <td>
                      <div className="user-table-actions">
                        {user.role !== "admin" && (
                          <>
                            <button type="button" className="user-action-button" onClick={() => openEditForm(user)}>Uredi</button>
                            <button
                              type="button"
                              className="user-action-button"
                              onClick={() => void handlePasswordReset(user)}
                              disabled={sendingResetUid === user.uid}
                            >
                              {sendingResetUid === user.uid ? "Slanje..." : "Reset lozinke"}
                            </button>
                          </>
                        )}
                        {user.role === "admin" && <span className="user-admin-lock">Zaštićen račun</span>}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>

      <div className="user-management-note">
        <strong>Napomena o finansijskim podacima:</strong> korisnik s pravom pregleda ponuda može vidjeti prodajne cijene, interne troškove i maržu pojedinačne ponude. Pristup stranici Analiza dodjeljuje se zasebno.
      </div>

      {modalOpen && (
        <div className="user-modal-backdrop" role="presentation" onMouseDown={(event) => {
          if (event.target === event.currentTarget) closeForm()
        }}>
          <section className="user-modal" role="dialog" aria-modal="true" aria-labelledby="user-modal-title">
            <div className="user-modal-header">
              <div>
                <p className="user-management-eyebrow">KORISNIČKI RAČUN</p>
                <h2 id="user-modal-title">{editingUid ? "Uredi korisnika" : "Novi korisnik"}</h2>
                <p>{editingUid ? "Promijeni podatke, status i privilegije." : "Kreiraj kalkulanta i odredi mu početne privilegije."}</p>
              </div>
              <button type="button" className="user-modal-close" onClick={closeForm} aria-label="Zatvori">×</button>
            </div>

            <form onSubmit={handleSave}>
              <div className="user-form-grid">
                <label>
                  Ime i prezime
                  <input
                    type="text"
                    maxLength={100}
                    value={form.imePrezime}
                    onChange={(event) => setForm((current) => ({ ...current, imePrezime: event.target.value }))}
                    required
                  />
                </label>
                <label>
                  E-mail adresa
                  <input
                    type="email"
                    value={form.email}
                    onChange={(event) => setForm((current) => ({ ...current, email: event.target.value }))}
                    disabled={Boolean(editingUid)}
                    required
                  />
                </label>
                {!editingUid && (
                  <label>
                    Početna lozinka
                    <input
                      type="password"
                      minLength={8}
                      maxLength={128}
                      autoComplete="new-password"
                      value={form.password}
                      onChange={(event) => setForm((current) => ({ ...current, password: event.target.value }))}
                      required
                    />
                    <small>Najmanje 8 znakova. Korisnik kasnije može zatražiti novu lozinku putem e-maila.</small>
                  </label>
                )}
                {editingUid && (
                  <label className="user-active-toggle">
                    <input
                      type="checkbox"
                      checked={form.aktivan}
                      onChange={(event) => setForm((current) => ({ ...current, aktivan: event.target.checked }))}
                    />
                    Korisnički račun je aktivan
                  </label>
                )}
              </div>

              <div className="user-permissions-heading">
                <div>
                  <h3>Privilegije</h3>
                  <p>Označi šta korisnik smije pregledati, kreirati, mijenjati ili brisati.</p>
                </div>
                <span>Kalkulant</span>
              </div>

              <div className="user-permission-list">
                {PERMISSION_GROUPS.map((group) => (
                  <div className="user-permission-row" key={group.key}>
                    <div className="user-permission-module">
                      <strong>{group.label}</strong>
                      {group.key === "quotes" && <small>Pregled uključuje troškove i maržu ponude.</small>}
                    </div>
                    <div className="user-permission-actions">
                      {group.actions.map((action) => {
                        const fixed = group.key === "quotes" && action === "view"
                        return (
                          <label key={action} className={fixed ? "is-fixed" : ""}>
                            <input
                              type="checkbox"
                              checked={form.permissions[group.key][action] === true}
                              disabled={fixed}
                              onChange={(event) => setPermission(group.key, action, event.target.checked)}
                            />
                            {ACTION_LABELS[action]}
                          </label>
                        )
                      })}
                    </div>
                  </div>
                ))}
              </div>

              {error && <div className="user-management-alert is-error" role="alert">{error}</div>}

              <div className="user-modal-footer">
                <button type="button" className="user-management-secondary" onClick={closeForm} disabled={saving}>Odustani</button>
                <button type="submit" className="user-management-primary" disabled={saving}>
                  {saving ? "Spremanje..." : editingUid ? "Sačuvaj izmjene" : "Kreiraj korisnika"}
                </button>
              </div>
            </form>
          </section>
        </div>
      )}
    </div>
  )
}

export default UserManagement
