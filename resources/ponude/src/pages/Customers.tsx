import { useEffect, useState } from "react"
import { useAuth } from "../auth/AuthContext"
import { hasPermission } from "../auth/permissions"

import {
  addCustomer,
  deleteCustomer,
  getCustomers,
  updateCustomer,
  type Customer,
} from "../services/customers"

function Customers() {
  const { profile } = useAuth()
  const canCreateCustomer = hasPermission(profile, "customers", "create")
  const canUpdateCustomer = hasPermission(profile, "customers", "update")
  const canDeleteCustomer = hasPermission(profile, "customers", "delete")
  const [customers, setCustomers] = useState<Customer[]>([])

  const [naziv, setNaziv] = useState("")
  const [adresa, setAdresa] = useState("")
  const [postanskiBroj, setPostanskiBroj] = useState("")
  const [grad, setGrad] = useState("")
  const [drzava, setDrzava] = useState("")
  const [napomena, setNapomena] = useState("")
  const [aktivan, setAktivan] = useState(true)

  const [editingId, setEditingId] = useState<string | null>(null)

  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)

  // ==========================================
  // UČITAVANJE KUPACA
  // ==========================================

  async function loadCustomers() {
    try {
      setLoading(true)

      const data = await getCustomers()

      setCustomers(data)
    } catch (error) {
      console.error("Greška pri učitavanju kupaca:", error)

      alert("Greška pri učitavanju kupaca.")
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    loadCustomers()
  }, [])

  // ==========================================
  // RESET FORME
  // ==========================================

  function resetForm() {
    setNaziv("")
    setAdresa("")
    setPostanskiBroj("")
    setGrad("")
    setDrzava("")
    setNapomena("")
    setAktivan(true)
    setEditingId(null)
  }

  // ==========================================
  // DODAVANJE / IZMJENA
  // ==========================================

  async function handleSubmit() {
    const cleanName = naziv.trim()

    if (!cleanName) {
      alert("Molimo unesite naziv kupca.")
      return
    }

    try {
      setSaving(true)

      if (editingId) {
        await updateCustomer(editingId, {
          naziv: cleanName,
          adresa: adresa.trim(),
          postanskiBroj: postanskiBroj.trim(),
          grad: grad.trim(),
          drzava: drzava.trim(),
          aktivan,
          napomena: napomena.trim(),
        })

        alert("Kupac je uspješno izmijenjen.")
      } else {
        await addCustomer({
          naziv: cleanName,
          adresa: adresa.trim(),
          postanskiBroj: postanskiBroj.trim(),
          grad: grad.trim(),
          drzava: drzava.trim(),
          aktivan,
          napomena: napomena.trim(),
        })

        alert("Kupac je uspješno dodan.")
      }

      resetForm()

      await loadCustomers()
    } catch (error) {
      console.error("Greška pri spremanju kupca:", error)

      alert("Greška pri spremanju kupca.")
    } finally {
      setSaving(false)
    }
  }

  // ==========================================
  // UREĐIVANJE
  // ==========================================

  function handleEdit(customer: Customer) {
    setEditingId(customer.id ?? null)
    setNaziv(customer.naziv)
    setAdresa(customer.adresa ?? "")
    setPostanskiBroj(customer.postanskiBroj ?? "")
    setGrad(customer.grad ?? "")
    setDrzava(customer.drzava ?? "")
    setNapomena(customer.napomena ?? "")
    setAktivan(customer.aktivan !== false)

    window.scrollTo({
      top: 0,
      behavior: "smooth",
    })
  }

  // ==========================================
  // BRISANJE
  // ==========================================

  async function handleDelete(customer: Customer) {
    if (!customer.id) {
      return
    }

    const confirmed = window.confirm(
      `Da li ste sigurni da želite obrisati kupca "${customer.naziv}"?`,
    )

    if (!confirmed) {
      return
    }

    try {
      await deleteCustomer(customer.id)

      if (editingId === customer.id) {
        resetForm()
      }

      await loadCustomers()
    } catch (error) {
      console.error("Greška pri brisanju kupca:", error)

      alert("Greška pri brisanju kupca.")
    }
  }

  // ==========================================
  // RENDER
  // ==========================================

  return (
    <div
      style={{
        maxWidth: "1500px",
        margin: "0 auto",
        padding: "40px 35px 70px",
      }}
    >
      {/* ====================================== */}
      {/* NASLOV */}
      {/* ====================================== */}

      <div
        style={{
          textAlign: "center",
          marginBottom: "35px",
        }}
      >
        <h1
          style={{
            fontSize: "42px",
            margin: "0 0 8px",
            color: "#111827",
          }}
        >
          Kupci
        </h1>

        <p
          style={{
            margin: 0,
            fontSize: "17px",
            color: "#64748b",
          }}
        >
          Upravljanje kupcima.
        </p>
      </div>

      {/* ====================================== */}
      {/* KARTICA */}
      {/* ====================================== */}

      <section
        style={{
          background: "#ffffff",
          border: "1px solid #dfe4ec",
          borderRadius: "14px",
          padding: "28px 24px 24px",
          boxShadow: "0 1px 3px rgba(15, 23, 42, 0.04)",
        }}
      >
        {/* ==================================== */}
        {/* HEADER */}
        {/* ==================================== */}

        <div
          style={{
            display: "flex",
            justifyContent: "space-between",
            alignItems: "flex-start",
            marginBottom: "24px",
          }}
        >
          <div>
            <h2
              style={{
                margin: "0 0 8px",
                fontSize: "24px",
                color: "#111827",
              }}
            >
              {editingId ? "Uredi kupca" : "Kupci"}
            </h2>

            <p
              style={{
                margin: 0,
                color: "#64748b",
                fontSize: "16px",
              }}
            >
              Kupci koji će biti dostupni prilikom izrade ponude.
            </p>
          </div>

          <div
            style={{
              background: "#eef2ff",
              color: "#4338ca",
              borderRadius: "22px",
              padding: "10px 18px",
              fontWeight: 600,
              whiteSpace: "nowrap",
            }}
          >
            {customers.length}{" "}
            {customers.length === 1
              ? "kupac"
              : customers.length >= 2 && customers.length <= 4
                ? "kupca"
                : "kupaca"}
          </div>
        </div>

        {/* ==================================== */}
        {/* FORMA */}
        {/* ==================================== */}

        {(canCreateCustomer || canUpdateCustomer) && <div
          style={{
            background: "#f8fafc",
            border: "1px solid #dfe4ec",
            borderRadius: "12px",
            padding: "20px 18px 18px",
            marginBottom: "24px",
          }}
        >
          <div
            style={{
              display: "grid",
              gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))",
              gap: "18px",
              alignItems: "end",
            }}
          >
            {/* NAZIV */}
            <div>
              <label
                style={{
                  display: "block",
                  textAlign: "center",
                  fontWeight: 600,
                  fontSize: "14px",
                  color: "#334155",
                  marginBottom: "9px",
                }}
              >
                Naziv kupca
              </label>

              <input
                type="text"
                value={naziv}
                onChange={(event) =>
                  setNaziv(event.target.value)
                }
                placeholder="npr. Trendy Germany"
                disabled={saving}
                style={{
                  width: "100%",
                  boxSizing: "border-box",
                  height: "40px",
                  padding: "0 13px",
                  border: "1px solid #cbd5e1",
                  borderRadius: "7px",
                  background: "#ffffff",
                  fontSize: "14px",
                  outline: "none",
                }}
              />
            </div>

            {/* ADRESA */}
            <div>
              <label style={{ display: "block", fontWeight: 600, fontSize: "14px", color: "#334155", marginBottom: "9px" }}>
                Ulica i broj
              </label>
              <input
                type="text"
                value={adresa}
                onChange={(event) => setAdresa(event.target.value)}
                placeholder="npr. Kaiserstrasse 150"
                disabled={saving}
                style={{ width: "100%", boxSizing: "border-box", height: "40px", padding: "0 13px", border: "1px solid #cbd5e1", borderRadius: "7px", background: "#ffffff", fontSize: "14px", outline: "none" }}
              />
            </div>

            {/* POŠTANSKI BROJ */}
            <div>
              <label style={{ display: "block", fontWeight: 600, fontSize: "14px", color: "#334155", marginBottom: "9px" }}>
                Poštanski broj
              </label>
              <input
                type="text"
                value={postanskiBroj}
                onChange={(event) => setPostanskiBroj(event.target.value)}
                placeholder="npr. 51643"
                disabled={saving}
                style={{ width: "100%", boxSizing: "border-box", height: "40px", padding: "0 13px", border: "1px solid #cbd5e1", borderRadius: "7px", background: "#ffffff", fontSize: "14px", outline: "none" }}
              />
            </div>

            {/* GRAD */}
            <div>
              <label style={{ display: "block", fontWeight: 600, fontSize: "14px", color: "#334155", marginBottom: "9px" }}>
                Grad
              </label>
              <input
                type="text"
                value={grad}
                onChange={(event) => setGrad(event.target.value)}
                placeholder="npr. Gummersbach"
                disabled={saving}
                style={{ width: "100%", boxSizing: "border-box", height: "40px", padding: "0 13px", border: "1px solid #cbd5e1", borderRadius: "7px", background: "#ffffff", fontSize: "14px", outline: "none" }}
              />
            </div>

            {/* DRŽAVA */}
            <div>
              <label style={{ display: "block", fontWeight: 600, fontSize: "14px", color: "#334155", marginBottom: "9px" }}>
                Država
              </label>
              <input
                type="text"
                value={drzava}
                onChange={(event) => setDrzava(event.target.value)}
                placeholder="npr. Njemačka"
                disabled={saving}
                style={{ width: "100%", boxSizing: "border-box", height: "40px", padding: "0 13px", border: "1px solid #cbd5e1", borderRadius: "7px", background: "#ffffff", fontSize: "14px", outline: "none" }}
              />
            </div>

            {/* AKTIVAN */}
            <label
              style={{
                display: "flex",
                alignItems: "center",
                gap: "8px",
                height: "40px",
                paddingBottom: "1px",
                fontSize: "14px",
                fontWeight: 600,
                color: "#334155",
                cursor: "pointer",
                whiteSpace: "nowrap",
              }}
            >
              <input
                type="checkbox"
                checked={aktivan}
                onChange={(event) =>
                  setAktivan(event.target.checked)
                }
                disabled={saving}
                style={{
                  width: "16px",
                  height: "16px",
                }}
              />

              Aktivno
            </label>
          </div>

          {/* NAPOMENA */}

          <div
            style={{
              marginTop: "18px",
            }}
          >
            <label
              style={{
                display: "block",
                textAlign: "center",
                fontWeight: 600,
                fontSize: "14px",
                color: "#334155",
                marginBottom: "9px",
              }}
            >
              Napomena
            </label>

            <input
              type="text"
              value={napomena}
              onChange={(event) =>
                setNapomena(event.target.value)
              }
              placeholder="Opcionalna napomena"
              disabled={saving}
              style={{
                width: "100%",
                boxSizing: "border-box",
                height: "40px",
                padding: "0 13px",
                border: "1px solid #cbd5e1",
                borderRadius: "7px",
                background: "#ffffff",
                fontSize: "14px",
              }}
            />
          </div>

          {/* DUGMAD */}

          <div
            style={{
              display: "flex",
              gap: "10px",
              marginTop: "18px",
            }}
          >
            {(editingId ? canUpdateCustomer : canCreateCustomer) && <button
              type="button"
              onClick={handleSubmit}
              disabled={saving}
              style={{
                border: "none",
                borderRadius: "7px",
                padding: "9px 16px",
                background: "#4f46e5",
                color: "#ffffff",
                fontWeight: 600,
                cursor: saving ? "not-allowed" : "pointer",
                opacity: saving ? 0.7 : 1,
              }}
            >
              {saving
                ? "Spremanje..."
                : editingId
                  ? "Sačuvaj izmjene"
                  : "Dodaj kupca"}
            </button>}

            {editingId && canUpdateCustomer && (
              <button
                type="button"
                onClick={resetForm}
                disabled={saving}
                style={{
                  border: "1px solid #cbd5e1",
                  borderRadius: "7px",
                  padding: "9px 16px",
                  background: "#ffffff",
                  color: "#475569",
                  fontWeight: 600,
                  cursor: "pointer",
                }}
              >
                Otkaži
              </button>
            )}
          </div>
        </div>}

        {/* ==================================== */}
        {/* TABELA */}
        {/* ==================================== */}

        <div
          style={{
            overflowX: "auto",
          }}
        >
          <table
            style={{
              width: "100%",
              borderCollapse: "collapse",
              fontSize: "15px",
            }}
          >
            <thead>
              <tr
                style={{
                  background: "#f8fafc",
                  borderBottom: "1px solid #dfe4ec",
                }}
              >
                <th
                  style={{
                    textAlign: "left",
                    padding: "14px 12px",
                    color: "#334155",
                    fontWeight: 600,
                  }}
                >
                  Naziv
                </th>

                <th
                  style={{
                    textAlign: "left",
                    padding: "14px 12px",
                    color: "#334155",
                    fontWeight: 600,
                  }}
                >
                  Adresa
                </th>

                <th
                  style={{
                    textAlign: "left",
                    padding: "14px 12px",
                    color: "#334155",
                    fontWeight: 600,
                  }}
                >
                  Status
                </th>

                <th
                  style={{
                    textAlign: "left",
                    padding: "14px 12px",
                    color: "#334155",
                    fontWeight: 600,
                  }}
                >
                  Napomena
                </th>

                <th
                  style={{
                    textAlign: "right",
                    padding: "14px 12px",
                    color: "#334155",
                    fontWeight: 600,
                  }}
                >
                  Akcije
                </th>
              </tr>
            </thead>

            <tbody>
              {loading ? (
                <tr>
                  <td
                    colSpan={5}
                    style={{
                      textAlign: "center",
                      padding: "35px 12px",
                      color: "#64748b",
                    }}
                  >
                    Učitavanje kupaca...
                  </td>
                </tr>
              ) : customers.length === 0 ? (
                <tr>
                  <td
                    colSpan={5}
                    style={{
                      textAlign: "center",
                      padding: "35px 12px",
                      color: "#64748b",
                    }}
                  >
                    Nema unesenih kupaca.
                  </td>
                </tr>
              ) : (
                customers.map((customer) => (
                  <tr
                    key={customer.id}
                    style={{
                      borderBottom:
                        "1px solid #e2e8f0",
                    }}
                  >
                    <td
                      style={{
                        padding: "15px 12px",
                        fontWeight: 600,
                        color: "#475569",
                      }}
                    >
                      {customer.naziv}
                    </td>

                    <td
                      style={{
                        padding: "15px 12px",
                        color: "#64748b",
                        lineHeight: 1.45,
                      }}
                    >
                      {[
                        customer.adresa,
                        [customer.postanskiBroj, customer.grad].filter(Boolean).join(" "),
                        customer.drzava,
                      ].filter(Boolean).map((line, index) => (
                        <div key={index}>{line}</div>
                      ))}
                      {!customer.adresa && !customer.postanskiBroj && !customer.grad && !customer.drzava ? "-" : null}
                    </td>

                    <td
                      style={{
                        padding: "15px 12px",
                      }}
                    >
                      <span
                        style={{
                          display: "inline-block",
                          padding: "5px 12px",
                          borderRadius: "16px",
                          fontSize: "12px",
                          fontWeight: 600,
                          background:
                            customer.aktivan !== false
                              ? "#dcfce7"
                              : "#e2e8f0",
                          color:
                            customer.aktivan !== false
                              ? "#15803d"
                              : "#64748b",
                        }}
                      >
                        {customer.aktivan !== false
                          ? "Aktivan"
                          : "Neaktivan"}
                      </span>
                    </td>

                    <td
                      style={{
                        padding: "15px 12px",
                        color: "#64748b",
                      }}
                    >
                      {customer.napomena || "-"}
                    </td>

                    <td
                      style={{
                        padding: "15px 12px",
                        textAlign: "right",
                      }}
                    >
                      <div
                        style={{
                          display: "flex",
                          justifyContent: "flex-end",
                          gap: "8px",
                        }}
                      >
                        {canUpdateCustomer && <button
                          type="button"
                          onClick={() =>
                            handleEdit(customer)
                          }
                          style={{
                            border: "none",
                            borderRadius: "7px",
                            padding: "8px 14px",
                            background: "#eef2ff",
                            color: "#4338ca",
                            fontWeight: 600,
                            cursor: "pointer",
                          }}
                        >
                          Uredi
                        </button>}

                        {canDeleteCustomer && <button
                          type="button"
                          onClick={() =>
                            handleDelete(customer)
                          }
                          style={{
                            border: "none",
                            borderRadius: "7px",
                            padding: "8px 14px",
                            background: "#fee2e2",
                            color: "#dc2626",
                            fontWeight: 600,
                            cursor: "pointer",
                          }}
                        >
                          Obriši
                        </button>}
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </section>
    </div>
  )
}

export default Customers