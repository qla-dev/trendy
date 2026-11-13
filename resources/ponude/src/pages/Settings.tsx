import { useEffect, useState } from "react"

import {
  addSurfaceProtection,
  getSurfaceProtections,
  updateSurfaceProtection,
  deleteSurfaceProtection,
  addHeatTreatment,
  getHeatTreatments,
  updateHeatTreatment,
  deleteHeatTreatment,
  addOtherOperation,
  getOtherOperations,
  updateOtherOperation,
  deleteOtherOperation,
  type SurfaceProtection,
  type HeatTreatment,
  type OtherOperation,
  type PriceType,
} from "../services/treatments"

import {
  addCustomer,
  getCustomers,
  updateCustomer,
  deleteCustomer,
  type Customer,
} from "../services/customers"

import {
  getGeneralSettings,
  updateGeneralSettings,
  type GeneralSettings,
} from "../services/settings"

import "./Settings.css"

// ==========================================
// PRAZNA POVRŠINSKA ZAŠTITA
// ==========================================

const emptySurfaceProtection: SurfaceProtection = {
  naziv: "",
  cijena: 0,
  tipCijene: "komad",
  aktivno: true,
  napomena: "",
}

// ==========================================
// PRAZNA TERMIČKA OBRADA
// ==========================================

const emptyHeatTreatment: HeatTreatment = {
  naziv: "",
  cijena: 0,
  tipCijene: "komad",
  aktivno: true,
  napomena: "",
}


// ==========================================
// PRAZAN OSTALI ZAHVAT
// ==========================================

const emptyOtherOperation: OtherOperation = {
  naziv: "",
  cijena: 0,
  tipCijene: "komad",
  aktivno: true,
  napomena: "",
}

// ==========================================
// PRAZAN KUPAC
// ==========================================

const emptyCustomer: Customer = {
  naziv: "",
  adresa: "",
  postanskiBroj: "",
  grad: "",
  drzava: "",
  aktivan: true,
  napomena: "",
}

// ==========================================
// KOMPONENTA
// ==========================================

function Settings() {
  const [
    surfaceProtections,
    setSurfaceProtections,
  ] = useState<SurfaceProtection[]>([])

  const [
    heatTreatments,
    setHeatTreatments,
  ] = useState<HeatTreatment[]>([])

  const [
    otherOperations,
    setOtherOperations,
  ] = useState<OtherOperation[]>([])

  const [
    customers,
    setCustomers,
  ] = useState<Customer[]>([])

  const [
    loading,
    setLoading,
  ] = useState(true)

  const [
    generalSettings,
    setGeneralSettings,
  ] = useState<GeneralSettings>({
    transportPricePerKg: 0,
    ensatPricePerPiece: 0,
  })

  const [
    settingsSaving,
    setSettingsSaving,
  ] = useState(false)

  const [
    surfaceForm,
    setSurfaceForm,
  ] = useState<SurfaceProtection>(
    emptySurfaceProtection,
  )

  const [
    heatForm,
    setHeatForm,
  ] = useState<HeatTreatment>(
    emptyHeatTreatment,
  )

  const [
    otherOperationForm,
    setOtherOperationForm,
  ] = useState<OtherOperation>(
    emptyOtherOperation,
  )

  const [
    customerForm,
    setCustomerForm,
  ] = useState<Customer>(
    emptyCustomer,
  )

  const [
    editingSurfaceId,
    setEditingSurfaceId,
  ] = useState<string | null>(null)

  const [
    editingHeatId,
    setEditingHeatId,
  ] = useState<string | null>(null)

  const [
    editingOtherOperationId,
    setEditingOtherOperationId,
  ] = useState<string | null>(null)

  const [
    editingCustomerId,
    setEditingCustomerId,
  ] = useState<string | null>(null)

  // ==========================================
  // UČITAVANJE
  // ==========================================

  async function loadData() {
    try {
      setLoading(true)

      const [
        surfaceProtectionData,
        heatTreatmentData,
        otherOperationData,
        customerData,
        generalSettingsData,
      ] = await Promise.all([
        getSurfaceProtections(),
        getHeatTreatments(),
        getOtherOperations(),
        getCustomers(),
        getGeneralSettings(),
      ])

      setSurfaceProtections(
        surfaceProtectionData,
      )

      setHeatTreatments(
        heatTreatmentData,
      )

      setOtherOperations(
        otherOperationData,
      )

      setCustomers(
        customerData,
      )

      setGeneralSettings(
        generalSettingsData,
      )
    } catch (error) {
      console.error(
        "Greška pri učitavanju postavki:",
        error,
      )

      alert(
        "Greška pri učitavanju postavki iz Firebase-a.",
      )
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    loadData()
  }, [])

  // ==========================================
  // OPĆE KALKULACIJSKE POSTAVKE
  // ==========================================

  async function saveGeneralSettings() {
    try {
      setSettingsSaving(true)

      const saved = await updateGeneralSettings({
        transportPricePerKg:
          Number(
            generalSettings.transportPricePerKg,
          ) || 0,
        ensatPricePerPiece:
          Number(
            generalSettings.ensatPricePerPiece,
          ) || 0,
      })

      setGeneralSettings(saved)

      alert(
        "Postavke kalkulacije su uspješno sačuvane.",
      )
    } catch (error) {
      console.error(
        "Greška pri spremanju općih postavki:",
        error,
      )

      alert(
        "Greška pri spremanju postavki kalkulacije.",
      )
    } finally {
      setSettingsSaving(false)
    }
  }

  // ==========================================
  // POVRŠINSKE ZAŠTITE
  // ==========================================

  function handleSurfaceChange(
    field: keyof SurfaceProtection,
    value: string | number | boolean,
  ) {
    setSurfaceForm(
      (previous) => ({
        ...previous,
        [field]: value,
      }),
    )
  }

  async function saveSurfaceProtection() {
    if (!surfaceForm.naziv.trim()) {
      alert(
        "Unesi naziv površinske zaštite.",
      )

      return
    }

    try {
      if (editingSurfaceId) {
        await updateSurfaceProtection(
          editingSurfaceId,
          {
            naziv:
              surfaceForm.naziv.trim(),

            cijena:
              Number(
                surfaceForm.cijena,
              ) || 0,

            tipCijene:
              surfaceForm.tipCijene,

            aktivno:
              surfaceForm.aktivno,

            napomena:
              surfaceForm.napomena ||
              "",
          },
        )

        alert(
          "Površinska zaštita je uspješno izmijenjena.",
        )
      } else {
        await addSurfaceProtection({
          naziv:
            surfaceForm.naziv.trim(),

          cijena:
            Number(
              surfaceForm.cijena,
            ) || 0,

          tipCijene:
            surfaceForm.tipCijene,

          aktivno:
            surfaceForm.aktivno,

          napomena:
            surfaceForm.napomena ||
            "",
        })

        alert(
          "Površinska zaštita je uspješno dodana.",
        )
      }

      setSurfaceForm(
        emptySurfaceProtection,
      )

      setEditingSurfaceId(
        null,
      )

      await loadData()
    } catch (error) {
      console.error(
        "Greška pri spremanju površinske zaštite:",
        error,
      )

      alert(
        "Greška pri spremanju površinske zaštite.",
      )
    }
  }

  function editSurfaceProtection(
    protection: SurfaceProtection,
  ) {
    setSurfaceForm({
      naziv:
        protection.naziv,

      cijena:
        protection.cijena,

      tipCijene:
        protection.tipCijene,

      aktivno:
        protection.aktivno ??
        true,

      napomena:
        protection.napomena ??
        "",
    })

    setEditingSurfaceId(
      protection.id ??
        null,
    )
  }

  function cancelSurfaceEdit() {
    setSurfaceForm(
      emptySurfaceProtection,
    )

    setEditingSurfaceId(
      null,
    )
  }

  async function removeSurfaceProtection(
    protection: SurfaceProtection,
  ) {
    if (!protection.id) {
      return
    }

    const confirmed =
      window.confirm(
        `Da li sigurno želiš obrisati "${protection.naziv}"?`,
      )

    if (!confirmed) {
      return
    }

    try {
      await deleteSurfaceProtection(
        protection.id,
      )

      if (
        editingSurfaceId ===
        protection.id
      ) {
        cancelSurfaceEdit()
      }

      await loadData()
    } catch (error) {
      console.error(
        "Greška pri brisanju površinske zaštite:",
        error,
      )

      alert(
        "Greška pri brisanju površinske zaštite.",
      )
    }
  }

  async function toggleSurfaceProtection(
    protection: SurfaceProtection,
  ) {
    if (!protection.id) {
      return
    }

    try {
      await updateSurfaceProtection(
        protection.id,
        {
          aktivno:
            !(
              protection.aktivno ??
              true
            ),
        },
      )

      await loadData()
    } catch (error) {
      console.error(
        "Greška pri promjeni statusa:",
        error,
      )

      alert(
        "Greška pri promjeni statusa.",
      )
    }
  }

  // ==========================================
  // TERMIČKE OBRADE
  // ==========================================

  function handleHeatChange(
    field: keyof HeatTreatment,
    value: string | number | boolean,
  ) {
    setHeatForm(
      (previous) => ({
        ...previous,
        [field]: value,
      }),
    )
  }

  async function saveHeatTreatment() {
    if (!heatForm.naziv.trim()) {
      alert(
        "Unesi naziv termičke obrade.",
      )

      return
    }

    try {
      if (editingHeatId) {
        await updateHeatTreatment(
          editingHeatId,
          {
            naziv:
              heatForm.naziv.trim(),

            cijena:
              Number(
                heatForm.cijena,
              ) || 0,

            tipCijene:
              heatForm.tipCijene,

            aktivno:
              heatForm.aktivno,

            napomena:
              heatForm.napomena ||
              "",
          },
        )

        alert(
          "Termička obrada je uspješno izmijenjena.",
        )
      } else {
        await addHeatTreatment({
          naziv:
            heatForm.naziv.trim(),

          cijena:
            Number(
              heatForm.cijena,
            ) || 0,

          tipCijene:
            heatForm.tipCijene,

          aktivno:
            heatForm.aktivno,

          napomena:
            heatForm.napomena ||
            "",
        })

        alert(
          "Termička obrada je uspješno dodana.",
        )
      }

      setHeatForm(
        emptyHeatTreatment,
      )

      setEditingHeatId(
        null,
      )

      await loadData()
    } catch (error) {
      console.error(
        "Greška pri spremanju termičke obrade:",
        error,
      )

      alert(
        "Greška pri spremanju termičke obrade.",
      )
    }
  }

  function editHeatTreatment(
    treatment: HeatTreatment,
  ) {
    setHeatForm({
      naziv:
        treatment.naziv,

      cijena:
        treatment.cijena,

      tipCijene:
        treatment.tipCijene,

      aktivno:
        treatment.aktivno ??
        true,

      napomena:
        treatment.napomena ??
        "",
    })

    setEditingHeatId(
      treatment.id ??
        null,
    )
  }

  function cancelHeatEdit() {
    setHeatForm(
      emptyHeatTreatment,
    )

    setEditingHeatId(
      null,
    )
  }

  async function removeHeatTreatment(
    treatment: HeatTreatment,
  ) {
    if (!treatment.id) {
      return
    }

    const confirmed =
      window.confirm(
        `Da li sigurno želiš obrisati "${treatment.naziv}"?`,
      )

    if (!confirmed) {
      return
    }

    try {
      await deleteHeatTreatment(
        treatment.id,
      )

      if (
        editingHeatId ===
        treatment.id
      ) {
        cancelHeatEdit()
      }

      await loadData()
    } catch (error) {
      console.error(
        "Greška pri brisanju termičke obrade:",
        error,
      )

      alert(
        "Greška pri brisanju termičke obrade.",
      )
    }
  }

  async function toggleHeatTreatment(
    treatment: HeatTreatment,
  ) {
    if (!treatment.id) {
      return
    }

    try {
      await updateHeatTreatment(
        treatment.id,
        {
          aktivno:
            !(
              treatment.aktivno ??
              true
            ),
        },
      )

      await loadData()
    } catch (error) {
      console.error(
        "Greška pri promjeni statusa:",
        error,
      )

      alert(
        "Greška pri promjeni statusa.",
      )
    }
  }

  // ==========================================
  // OSTALI ZAHVATI
  // ==========================================

  function handleOtherOperationChange(
    field: keyof OtherOperation,
    value: string | number | boolean,
  ) {
    setOtherOperationForm(
      (previous) => ({
        ...previous,
        [field]: value,
      }),
    )
  }

  async function saveOtherOperation() {
    if (!otherOperationForm.naziv.trim()) {
      alert(
        "Unesi naziv ostalog zahvata.",
      )

      return
    }

    try {
      if (editingOtherOperationId) {
        await updateOtherOperation(
          editingOtherOperationId,
          {
            naziv:
              otherOperationForm.naziv.trim(),

            cijena:
              Number(
                otherOperationForm.cijena,
              ) || 0,

            tipCijene:
              otherOperationForm.tipCijene,

            aktivno:
              otherOperationForm.aktivno,

            napomena:
              otherOperationForm.napomena ||
              "",
          },
        )

        alert(
          "Ostali zahvat je uspješno izmijenjen.",
        )
      } else {
        await addOtherOperation({
          naziv:
            otherOperationForm.naziv.trim(),

          cijena:
            Number(
              otherOperationForm.cijena,
            ) || 0,

          tipCijene:
            otherOperationForm.tipCijene,

          aktivno:
            otherOperationForm.aktivno,

          napomena:
            otherOperationForm.napomena ||
            "",
        })

        alert(
          "Ostali zahvat je uspješno dodan.",
        )
      }

      setOtherOperationForm(
        emptyOtherOperation,
      )

      setEditingOtherOperationId(
        null,
      )

      await loadData()
    } catch (error) {
      console.error(
        "Greška pri spremanju ostalih zahvata:",
        error,
      )

      alert(
        "Greška pri spremanju ostalih zahvata.",
      )
    }
  }

  function editOtherOperation(
    operation: OtherOperation,
  ) {
    setOtherOperationForm({
      naziv:
        operation.naziv,

      cijena:
        operation.cijena,

      tipCijene:
        operation.tipCijene,

      aktivno:
        operation.aktivno ??
        true,

      napomena:
        operation.napomena ??
        "",
    })

    setEditingOtherOperationId(
      operation.id ??
        null,
    )
  }

  function cancelOtherOperationEdit() {
    setOtherOperationForm(
      emptyOtherOperation,
    )

    setEditingOtherOperationId(
      null,
    )
  }

  async function removeOtherOperation(
    operation: OtherOperation,
  ) {
    if (!operation.id) {
      return
    }

    const confirmed =
      window.confirm(
        `Da li sigurno želiš obrisati "${operation.naziv}"?`,
      )

    if (!confirmed) {
      return
    }

    try {
      await deleteOtherOperation(
        operation.id,
      )

      if (
        editingOtherOperationId ===
        operation.id
      ) {
        cancelOtherOperationEdit()
      }

      await loadData()
    } catch (error) {
      console.error(
        "Greška pri brisanju ostalih zahvata:",
        error,
      )

      alert(
        "Greška pri brisanju ostalih zahvata.",
      )
    }
  }

  async function toggleOtherOperation(
    operation: OtherOperation,
  ) {
    if (!operation.id) {
      return
    }

    try {
      await updateOtherOperation(
        operation.id,
        {
          aktivno:
            !(
              operation.aktivno ??
              true
            ),
        },
      )

      await loadData()
    } catch (error) {
      console.error(
        "Greška pri promjeni statusa:",
        error,
      )

      alert(
        "Greška pri promjeni statusa.",
      )
    }
  }

  // ==========================================
  // KUPCI
  // ==========================================

  function handleCustomerChange(
    field: keyof Customer,
    value: string | boolean,
  ) {
    setCustomerForm(
      (previous) => ({
        ...previous,
        [field]: value,
      }),
    )
  }

  async function saveCustomer() {
    if (!customerForm.naziv.trim()) {
      alert(
        "Unesi naziv kupca.",
      )

      return
    }

    try {
      if (editingCustomerId) {
        await updateCustomer(
          editingCustomerId,
          {
            naziv:
              customerForm.naziv.trim(),

            adresa:
              customerForm.adresa?.trim() || "",

            postanskiBroj:
              customerForm.postanskiBroj?.trim() || "",

            grad:
              customerForm.grad?.trim() || "",

            drzava:
              customerForm.drzava?.trim() || "",

            aktivan:
              customerForm.aktivan,

            napomena:
              customerForm.napomena ||
              "",
          },
        )

        alert(
          "Kupac je uspješno izmijenjen.",
        )
      } else {
        await addCustomer({
          naziv:
            customerForm.naziv.trim(),

          adresa:
            customerForm.adresa?.trim() || "",

          postanskiBroj:
            customerForm.postanskiBroj?.trim() || "",

          grad:
            customerForm.grad?.trim() || "",

          drzava:
            customerForm.drzava?.trim() || "",

          aktivan:
            customerForm.aktivan,

          napomena:
            customerForm.napomena ||
            "",
        })

        alert(
          "Kupac je uspješno dodan.",
        )
      }

      setCustomerForm(
        emptyCustomer,
      )

      setEditingCustomerId(
        null,
      )

      await loadData()
    } catch (error) {
      console.error(
        "Greška pri spremanju kupca:",
        error,
      )

      alert(
        "Greška pri spremanju kupca.",
      )
    }
  }

  function editCustomer(
    customer: Customer,
  ) {
    setCustomerForm({
      naziv:
        customer.naziv,

      adresa:
        customer.adresa ?? "",

      postanskiBroj:
        customer.postanskiBroj ?? "",

      grad:
        customer.grad ?? "",

      drzava:
        customer.drzava ?? "",

      aktivan:
        customer.aktivan ??
        true,

      napomena:
        customer.napomena ??
        "",
    })

    setEditingCustomerId(
      customer.id ??
        null,
    )
  }

  function cancelCustomerEdit() {
    setCustomerForm(
      emptyCustomer,
    )

    setEditingCustomerId(
      null,
    )
  }

  async function removeCustomer(
    customer: Customer,
  ) {
    if (!customer.id) {
      return
    }

    const confirmed =
      window.confirm(
        `Da li sigurno želiš obrisati "${customer.naziv}"?`,
      )

    if (!confirmed) {
      return
    }

    try {
      await deleteCustomer(
        customer.id,
      )

      if (
        editingCustomerId ===
        customer.id
      ) {
        cancelCustomerEdit()
      }

      await loadData()
    } catch (error) {
      console.error(
        "Greška pri brisanju kupca:",
        error,
      )

      alert(
        "Greška pri brisanju kupca.",
      )
    }
  }

  async function toggleCustomer(
    customer: Customer,
  ) {
    if (!customer.id) {
      return
    }

    try {
      await updateCustomer(
        customer.id,
        {
          aktivan:
            !(
              customer.aktivan ??
              true
            ),
        },
      )

      await loadData()
    } catch (error) {
      console.error(
        "Greška pri promjeni statusa kupca:",
        error,
      )

      alert(
        "Greška pri promjeni statusa kupca.",
      )
    }
  }

  // ==========================================
  // PRIKAZ
  // ==========================================

  if (loading) {
    return (
      <div className="settings-page">
        <div className="settings-header">
          <div>
            <h1>
              Postavke
            </h1>

            <p>
              Upravljanje površinskim zaštitama,
              termičkim obradama i kupcima.
            </p>
          </div>
        </div>

        <div className="settings-loading">
          Učitavanje...
        </div>
      </div>
    )
  }

  return (
    <div className="settings-page">

      {/* ====================================== */}
      {/* HEADER */}
      {/* ====================================== */}

      <div className="settings-header">
        <div>
          <h1>
            Postavke
          </h1>

          <p>
            Upravljanje površinskim zaštitama,
            termičkim obradama i kupcima.
          </p>
        </div>
      </div>

      {/* ====================================== */}
      {/* OPĆE KALKULACIJSKE POSTAVKE */}
      {/* ====================================== */}

      <section className="settings-section">

        <div className="settings-section-header">

          <div>
            <h2>
              Kalkulacija
            </h2>

            <p>
              Opće postavke koje se koriste u CNC kalkulaciji.
            </p>
          </div>

        </div>

        <div className="settings-form">

          <div className="settings-form-grid">

            <label>
              Transport
              <input
                type="number"
                min="0"
                step="0.01"
                value={
                  generalSettings.transportPricePerKg
                }
                onChange={(event) =>
                  setGeneralSettings((previous) => ({
                    ...previous,
                    transportPricePerKg:
                      Number(event.target.value) || 0,
                  }))
                }
              />
              <span
                style={{
                  display: "block",
                  marginTop: "4px",
                  fontSize: "12px",
                  color: "#64748b",
                }}
              >
                €/kg
              </span>
            </label>

            <label>
              Cijena Ensata
              <input
                type="number"
                min="0"
                step="0.01"
                value={generalSettings.ensatPricePerPiece}
                onChange={(event) =>
                  setGeneralSettings((previous) => ({
                    ...previous,
                    ensatPricePerPiece: Number(event.target.value) || 0,
                  }))
                }
              />
              <span
                style={{
                  display: "block",
                  marginTop: "4px",
                  fontSize: "12px",
                  color: "#64748b",
                }}
              >
                €/komad
              </span>
            </label>

          </div>

          <div className="settings-form-actions">
            <button
              className="primary-button"
              onClick={saveGeneralSettings}
              disabled={settingsSaving}
            >
              {settingsSaving
                ? "Spremanje..."
                : "Sačuvaj postavke"}
            </button>
          </div>

        </div>

      </section>

      {/* ====================================== */}
      {/* KUPCI */}
      {/* ====================================== */}

      <section className="settings-section">

        <div className="settings-section-header">

          <div>
            <h2>
              Kupci
            </h2>

            <p>
              Kupci koji će biti dostupni
              u novoj ponudi.
            </p>
          </div>

          <span className="settings-count">
            {customers.length} kupaca
          </span>

        </div>

        {/* FORMA */}

        <div className="settings-form">

          <div className="settings-form-grid settings-customer-form-grid">

            <label>
              Naziv kupca

              <input
                type="text"
                value={
                  customerForm.naziv
                }
                placeholder="npr. Trendy Germany"
                onChange={(event) =>
                  handleCustomerChange(
                    "naziv",
                    event.target.value,
                  )
                }
              />
            </label>

            <label>
              Ulica i broj
              <input
                type="text"
                value={customerForm.adresa ?? ""}
                placeholder="npr. Kaiserstrasse 150"
                onChange={(event) => handleCustomerChange("adresa", event.target.value)}
              />
            </label>

            <label>
              Poštanski broj
              <input
                type="text"
                value={customerForm.postanskiBroj ?? ""}
                placeholder="npr. 51643"
                onChange={(event) => handleCustomerChange("postanskiBroj", event.target.value)}
              />
            </label>

            <label>
              Grad
              <input
                type="text"
                value={customerForm.grad ?? ""}
                placeholder="npr. Gummersbach"
                onChange={(event) => handleCustomerChange("grad", event.target.value)}
              />
            </label>

            <label>
              Država
              <input
                type="text"
                value={customerForm.drzava ?? ""}
                placeholder="npr. Njemačka"
                onChange={(event) => handleCustomerChange("drzava", event.target.value)}
              />
            </label>

            <label className="settings-checkbox">

              <input
                type="checkbox"
                checked={
                  customerForm.aktivan ??
                  true
                }
                onChange={(event) =>
                  handleCustomerChange(
                    "aktivan",
                    event.target.checked,
                  )
                }
              />

              Aktivno

            </label>

          </div>

          <label className="settings-note">

            Napomena

            <input
              type="text"
              value={
                customerForm.napomena ??
                ""
              }
              placeholder="Opcionalna napomena"
              onChange={(event) =>
                handleCustomerChange(
                  "napomena",
                  event.target.value,
                )
              }
            />

          </label>

          <div className="settings-form-actions">

            <button
              className="primary-button"
              onClick={
                saveCustomer
              }
            >
              {editingCustomerId
                ? "Sačuvaj izmjene"
                : "Dodaj kupca"}
            </button>

            {editingCustomerId && (
              <button
                className="secondary-button"
                onClick={
                  cancelCustomerEdit
                }
              >
                Odustani
              </button>
            )}

          </div>

        </div>

        {/* TABELA */}

        <div className="settings-table-wrapper">

          <table className="settings-table">

            <thead>
              <tr>
                <th>
                  Naziv
                </th>

                <th>
                  Adresa
                </th>

                <th>
                  Status
                </th>

                <th>
                  Napomena
                </th>

                <th>
                  Akcije
                </th>
              </tr>
            </thead>

            <tbody>

              {customers.length ===
              0 ? (
                <tr>
                  <td
                    colSpan={5}
                    className="empty-row"
                  >
                    Nema unesenih kupaca.
                  </td>
                </tr>
              ) : (
                customers.map(
                  (customer) => (
                    <tr
                      key={
                        customer.id
                      }
                    >

                      <td>
                        <strong>
                          {
                            customer.naziv
                          }
                        </strong>
                      </td>

                      <td>
                        <div>{customer.adresa || ""}</div>
                        <div>{[customer.postanskiBroj, customer.grad].filter(Boolean).join(" ")}</div>
                        <div>{customer.drzava || ""}</div>
                        {!customer.adresa && !customer.postanskiBroj && !customer.grad && !customer.drzava ? "-" : null}
                      </td>

                      <td>
                        <button
                          className={
                            customer.aktivan
                              ? "status-active"
                              : "status-inactive"
                          }
                          onClick={() =>
                            toggleCustomer(
                              customer,
                            )
                          }
                        >
                          {customer.aktivan
                            ? "Aktivno"
                            : "Neaktivno"}
                        </button>
                      </td>

                      <td>
                        {
                          customer.napomena ||
                          "-"
                        }
                      </td>

                      <td className="actions-cell">

                        <button
                          className="secondary-button"
                          onClick={() =>
                            editCustomer(
                              customer,
                            )
                          }
                        >
                          Uredi
                        </button>

                        <button
                          className="danger-button"
                          onClick={() =>
                            removeCustomer(
                              customer,
                            )
                          }
                        >
                          Obriši
                        </button>

                      </td>

                    </tr>
                  ),
                )
              )}

            </tbody>

          </table>

        </div>

      </section>

      {/* ====================================== */}
      {/* POVRŠINSKE ZAŠTITE */}
      {/* ====================================== */}

      <section className="settings-section">

        <div className="settings-section-header">

          <div>
            <h2>
              Površinske zaštite
            </h2>

            <p>
              Zaštite koje će biti dostupne
              u novoj ponudi.
            </p>
          </div>

          <span className="settings-count">
            {surfaceProtections.length} stavki
          </span>

        </div>

        <div className="settings-form">

          <div className="settings-form-grid">

            <label>
              Naziv

              <input
                type="text"
                value={
                  surfaceForm.naziv
                }
                placeholder="npr. Bruniranje"
                onChange={(event) =>
                  handleSurfaceChange(
                    "naziv",
                    event.target.value,
                  )
                }
              />
            </label>

            <label>
              Cijena

              <input
                type="number"
                min="0"
                step="0.01"
                value={
                  surfaceForm.cijena
                }
                onChange={(event) =>
                  handleSurfaceChange(
                    "cijena",
                    Number(
                      event.target.value,
                    ),
                  )
                }
              />
            </label>

            <label>
              Tip cijene

              <select
                value={
                  surfaceForm.tipCijene
                }
                onChange={(event) =>
                  handleSurfaceChange(
                    "tipCijene",
                    event.target.value as PriceType,
                  )
                }
              >
                <option value="komad">
                  €/komad
                </option>

                <option value="kg">
                  €/kg
                </option>

                <option value="fiksno">
                  Fiksna cijena
                </option>
              </select>
            </label>

            <label className="settings-checkbox">

              <input
                type="checkbox"
                checked={
                  surfaceForm.aktivno ??
                  true
                }
                onChange={(event) =>
                  handleSurfaceChange(
                    "aktivno",
                    event.target.checked,
                  )
                }
              />

              Aktivno

            </label>

          </div>

          <label className="settings-note">

            Napomena

            <input
              type="text"
              value={
                surfaceForm.napomena ??
                ""
              }
              placeholder="Opcionalna napomena"
              onChange={(event) =>
                handleSurfaceChange(
                  "napomena",
                  event.target.value,
                )
              }
            />

          </label>

          <div className="settings-form-actions">

            <button
              className="primary-button"
              onClick={
                saveSurfaceProtection
              }
            >
              {editingSurfaceId
                ? "Sačuvaj izmjene"
                : "Dodaj zaštitu"}
            </button>

            {editingSurfaceId && (
              <button
                className="secondary-button"
                onClick={
                  cancelSurfaceEdit
                }
              >
                Odustani
              </button>
            )}

          </div>

        </div>

        <div className="settings-table-wrapper">

          <table className="settings-table">

            <thead>
              <tr>
                <th>Naziv</th>
                <th>Cijena</th>
                <th>Tip cijene</th>
                <th>Status</th>
                <th>Napomena</th>
                <th>Akcije</th>
              </tr>
            </thead>

            <tbody>

              {surfaceProtections.length ===
              0 ? (
                <tr>
                  <td
                    colSpan={6}
                    className="empty-row"
                  >
                    Nema unesenih površinskih
                    zaštita.
                  </td>
                </tr>
              ) : (
                surfaceProtections.map(
                  (protection) => (
                    <tr
                      key={
                        protection.id
                      }
                    >

                      <td>
                        <strong>
                          {
                            protection.naziv
                          }
                        </strong>
                      </td>

                      <td>
                        {Number(
                          protection.cijena,
                        ).toFixed(2)}{" "}
                        €
                      </td>

                      <td>

                        {protection.tipCijene ===
                          "komad" &&
                          "€/komad"}

                        {protection.tipCijene ===
                          "kg" &&
                          "€/kg"}

                        {protection.tipCijene ===
                          "fiksno" &&
                          "Fiksno"}

                      </td>

                      <td>

                        <button
                          className={
                            protection.aktivno
                              ? "status-active"
                              : "status-inactive"
                          }
                          onClick={() =>
                            toggleSurfaceProtection(
                              protection,
                            )
                          }
                        >
                          {protection.aktivno
                            ? "Aktivno"
                            : "Neaktivno"}
                        </button>

                      </td>

                      <td>
                        {
                          protection.napomena ||
                          "-"
                        }
                      </td>

                      <td className="actions-cell">

                        <button
                          className="secondary-button"
                          onClick={() =>
                            editSurfaceProtection(
                              protection,
                            )
                          }
                        >
                          Uredi
                        </button>

                        <button
                          className="danger-button"
                          onClick={() =>
                            removeSurfaceProtection(
                              protection,
                            )
                          }
                        >
                          Obriši
                        </button>

                      </td>

                    </tr>
                  ),
                )
              )}

            </tbody>

          </table>

        </div>

      </section>

      {/* ====================================== */}
      {/* TERMIČKE OBRADE */}
      {/* ====================================== */}

      <section className="settings-section">

        <div className="settings-section-header">

          <div>
            <h2>
              Termičke obrade
            </h2>

            <p>
              Termičke obrade koje će biti
              dostupne u novoj ponudi.
            </p>
          </div>

          <span className="settings-count">
            {heatTreatments.length} stavki
          </span>

        </div>

        <div className="settings-form">

          <div className="settings-form-grid">

            <label>
              Naziv

              <input
                type="text"
                value={
                  heatForm.naziv
                }
                placeholder="npr. Kaljenje"
                onChange={(event) =>
                  handleHeatChange(
                    "naziv",
                    event.target.value,
                  )
                }
              />
            </label>

            <label>
              Cijena

              <input
                type="number"
                min="0"
                step="0.01"
                value={
                  heatForm.cijena
                }
                onChange={(event) =>
                  handleHeatChange(
                    "cijena",
                    Number(
                      event.target.value,
                    ),
                  )
                }
              />
            </label>

            <label>
              Tip cijene

              <select
                value={
                  heatForm.tipCijene
                }
                onChange={(event) =>
                  handleHeatChange(
                    "tipCijene",
                    event.target.value as PriceType,
                  )
                }
              >
                <option value="komad">
                  €/komad
                </option>

                <option value="kg">
                  €/kg
                </option>

                <option value="fiksno">
                  Fiksna cijena
                </option>
              </select>
            </label>

            <label className="settings-checkbox">

              <input
                type="checkbox"
                checked={
                  heatForm.aktivno ??
                  true
                }
                onChange={(event) =>
                  handleHeatChange(
                    "aktivno",
                    event.target.checked,
                  )
                }
              />

              Aktivno

            </label>

          </div>

          <label className="settings-note">

            Napomena

            <input
              type="text"
              value={
                heatForm.napomena ??
                ""
              }
              placeholder="Opcionalna napomena"
              onChange={(event) =>
                handleHeatChange(
                  "napomena",
                  event.target.value,
                )
              }
            />

          </label>

          <div className="settings-form-actions">

            <button
              className="primary-button"
              onClick={
                saveHeatTreatment
              }
            >
              {editingHeatId
                ? "Sačuvaj izmjene"
                : "Dodaj obradu"}
            </button>

            {editingHeatId && (
              <button
                className="secondary-button"
                onClick={
                  cancelHeatEdit
                }
              >
                Odustani
              </button>
            )}

          </div>

        </div>

        <div className="settings-table-wrapper">

          <table className="settings-table">

            <thead>
              <tr>
                <th>Naziv</th>
                <th>Cijena</th>
                <th>Tip cijene</th>
                <th>Status</th>
                <th>Napomena</th>
                <th>Akcije</th>
              </tr>
            </thead>

            <tbody>

              {heatTreatments.length ===
              0 ? (
                <tr>
                  <td
                    colSpan={6}
                    className="empty-row"
                  >
                    Nema unesenih termičkih obrada.
                  </td>
                </tr>
              ) : (
                heatTreatments.map(
                  (treatment) => (
                    <tr
                      key={
                        treatment.id
                      }
                    >

                      <td>
                        <strong>
                          {
                            treatment.naziv
                          }
                        </strong>
                      </td>

                      <td>
                        {Number(
                          treatment.cijena,
                        ).toFixed(2)}{" "}
                        €
                      </td>

                      <td>

                        {treatment.tipCijene ===
                          "komad" &&
                          "€/komad"}

                        {treatment.tipCijene ===
                          "kg" &&
                          "€/kg"}

                        {treatment.tipCijene ===
                          "fiksno" &&
                          "Fiksno"}

                      </td>

                      <td>

                        <button
                          className={
                            treatment.aktivno
                              ? "status-active"
                              : "status-inactive"
                          }
                          onClick={() =>
                            toggleHeatTreatment(
                              treatment,
                            )
                          }
                        >
                          {treatment.aktivno
                            ? "Aktivno"
                            : "Neaktivno"}
                        </button>

                      </td>

                      <td>
                        {
                          treatment.napomena ||
                          "-"
                        }
                      </td>

                      <td className="actions-cell">

                        <button
                          className="secondary-button"
                          onClick={() =>
                            editHeatTreatment(
                              treatment,
                            )
                          }
                        >
                          Uredi
                        </button>

                        <button
                          className="danger-button"
                          onClick={() =>
                            removeHeatTreatment(
                              treatment,
                            )
                          }
                        >
                          Obriši
                        </button>

                      </td>

                    </tr>
                  ),
                )
              )}

            </tbody>

          </table>

        </div>

      </section>

      {/* ====================================== */}
      {/* OSTALI ZAHVATI */}
      {/* ====================================== */}

      <section className="settings-section">

        <div className="settings-section-header">

          <div>
            <h2>
              Ostali zahvati
            </h2>

            <p>
              Ostali zahvati koji će biti
              dostupni u novoj ponudi.
            </p>
          </div>

          <span className="settings-count">
            {otherOperations.length} stavki
          </span>

        </div>

        <div className="settings-form">

          <div className="settings-form-grid">

            <label>
              Naziv

              <input
                type="text"
                value={
                  otherOperationForm.naziv
                }
                placeholder="npr. Peglanje, pjeskarenje, staklarenje"
                onChange={(event) =>
                  handleOtherOperationChange(
                    "naziv",
                    event.target.value,
                  )
                }
              />
            </label>

            <label>
              Cijena

              <input
                type="number"
                min="0"
                step="0.01"
                value={
                  otherOperationForm.cijena
                }
                onChange={(event) =>
                  handleOtherOperationChange(
                    "cijena",
                    Number(
                      event.target.value,
                    ),
                  )
                }
              />
            </label>

            <label>
              Tip cijene

              <select
                value={
                  otherOperationForm.tipCijene
                }
                onChange={(event) =>
                  handleOtherOperationChange(
                    "tipCijene",
                    event.target.value as PriceType,
                  )
                }
              >
                <option value="komad">
                  €/komad
                </option>

                <option value="kg">
                  €/kg
                </option>

                <option value="fiksno">
                  Fiksna cijena
                </option>
              </select>
            </label>

            <label className="settings-checkbox">

              <input
                type="checkbox"
                checked={
                  otherOperationForm.aktivno ??
                  true
                }
                onChange={(event) =>
                  handleOtherOperationChange(
                    "aktivno",
                    event.target.checked,
                  )
                }
              />

              Aktivno

            </label>

          </div>

          <label className="settings-note">

            Napomena

            <input
              type="text"
              value={
                otherOperationForm.napomena ??
                ""
              }
              placeholder="Opcionalna napomena"
              onChange={(event) =>
                handleOtherOperationChange(
                  "napomena",
                  event.target.value,
                )
              }
            />

          </label>

          <div className="settings-form-actions">

            <button
              className="primary-button"
              onClick={
                saveOtherOperation
              }
            >
              {editingOtherOperationId
                ? "Sačuvaj izmjene"
                : "Dodaj zahvat"}
            </button>

            {editingOtherOperationId && (
              <button
                className="secondary-button"
                onClick={
                  cancelOtherOperationEdit
                }
              >
                Odustani
              </button>
            )}

          </div>

        </div>

        <div className="settings-table-wrapper">

          <table className="settings-table">

            <thead>
              <tr>
                <th>Naziv</th>
                <th>Cijena</th>
                <th>Tip cijene</th>
                <th>Status</th>
                <th>Napomena</th>
                <th>Akcije</th>
              </tr>
            </thead>

            <tbody>

              {otherOperations.length ===
              0 ? (
                <tr>
                  <td
                    colSpan={6}
                    className="empty-row"
                  >
                    Nema unesenih ostalih zahvata.
                  </td>
                </tr>
              ) : (
                otherOperations.map(
                  (operation) => (
                    <tr
                      key={
                        operation.id
                      }
                    >

                      <td>
                        <strong>
                          {
                            operation.naziv
                          }
                        </strong>
                      </td>

                      <td>
                        {Number(
                          operation.cijena,
                        ).toFixed(2)}{" "}
                        €
                      </td>

                      <td>

                        {operation.tipCijene ===
                          "komad" &&
                          "€/komad"}

                        {operation.tipCijene ===
                          "kg" &&
                          "€/kg"}

                        {operation.tipCijene ===
                          "fiksno" &&
                          "Fiksno"}

                      </td>

                      <td>

                        <button
                          className={
                            operation.aktivno
                              ? "status-active"
                              : "status-inactive"
                          }
                          onClick={() =>
                            toggleOtherOperation(
                              operation,
                            )
                          }
                        >
                          {operation.aktivno
                            ? "Aktivno"
                            : "Neaktivno"}
                        </button>

                      </td>

                      <td>
                        {
                          operation.napomena ||
                          "-"
                        }
                      </td>

                      <td className="actions-cell">

                        <button
                          className="secondary-button"
                          onClick={() =>
                            editOtherOperation(
                              operation,
                            )
                          }
                        >
                          Uredi
                        </button>

                        <button
                          className="danger-button"
                          onClick={() =>
                            removeOtherOperation(
                              operation,
                            )
                          }
                        >
                          Obriši
                        </button>

                      </td>

                    </tr>
                  ),
                )
              )}

            </tbody>

          </table>

        </div>

      </section>

    </div>
  )
}

export default Settings