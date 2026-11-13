import { useEffect, useState } from "react"
import { useAuth } from "../auth/AuthContext"
import { hasPermission } from "../auth/permissions"
import { LocalizedNumberInput } from "../components/LocalizedNumberInput"
import { formatLocalizedNumber, parseLocalizedNumber } from "../utils/numericInput"
import "./Treatments.css"

import {
  addSurfaceProtection,
  getSurfaceProtections,
  updateSurfaceProtection,
  deleteSurfaceProtection,
  addHeatTreatment,
  getHeatTreatments,
  updateHeatTreatment,
  deleteHeatTreatment,
  type SurfaceProtection,
  type HeatTreatment,
  type PriceType,
} from "../services/treatments"

function Treatments() {
  const { profile } = useAuth()
  const canCreateTreatment = hasPermission(profile, "treatments", "create")
  const canUpdateTreatment = hasPermission(profile, "treatments", "update")
  const canDeleteTreatment = hasPermission(profile, "treatments", "delete")
  const [surfaceProtections, setSurfaceProtections] = useState<
    SurfaceProtection[]
  >([])

  const [heatTreatments, setHeatTreatments] = useState<
    HeatTreatment[]
  >([])

  const [loading, setLoading] = useState(true)

  const [newSurfaceName, setNewSurfaceName] = useState("")
  const [newSurfacePrice, setNewSurfacePrice] = useState("")
  const [newSurfacePriceType, setNewSurfacePriceType] =
    useState<PriceType>("komad")

  const [newHeatName, setNewHeatName] = useState("")
  const [newHeatPrice, setNewHeatPrice] = useState("")
  const [newHeatPriceType, setNewHeatPriceType] =
    useState<PriceType>("kg")

  useEffect(() => {
    loadData()
  }, [])

  async function loadData() {
    try {
      setLoading(true)

      const [
        surfaceProtectionData,
        heatTreatmentData,
      ] = await Promise.all([
        getSurfaceProtections(),
        getHeatTreatments(),
      ])

      setSurfaceProtections(surfaceProtectionData)
      setHeatTreatments(heatTreatmentData)
    } catch (error) {
      console.error("Greška pri učitavanju:", error)
      alert("Greška pri učitavanju podataka.")
    } finally {
      setLoading(false)
    }
  }

  async function handleAddSurfaceProtection() {
    if (!newSurfaceName.trim()) {
      alert("Unesi naziv površinske zaštite.")
      return
    }

    const price = parseLocalizedNumber(newSurfacePrice)

    if (price === null || price < 0) {
      alert("Unesi ispravnu cijenu.")
      return
    }

    try {
      await addSurfaceProtection({
        naziv: newSurfaceName.trim(),
        cijena: price,
        tipCijene: newSurfacePriceType,
        aktivno: true,
      })

      setNewSurfaceName("")
      setNewSurfacePrice("")

      await loadData()
    } catch (error) {
      console.error(error)
      alert("Greška pri dodavanju površinske zaštite.")
    }
  }

  async function handleAddHeatTreatment() {
    if (!newHeatName.trim()) {
      alert("Unesi naziv termičke obrade.")
      return
    }

    const price = parseLocalizedNumber(newHeatPrice)

    if (price === null || price < 0) {
      alert("Unesi ispravnu cijenu.")
      return
    }

    try {
      await addHeatTreatment({
        naziv: newHeatName.trim(),
        cijena: price,
        tipCijene: newHeatPriceType,
        aktivno: true,
      })

      setNewHeatName("")
      setNewHeatPrice("")

      await loadData()
    } catch (error) {
      console.error(error)
      alert("Greška pri dodavanju termičke obrade.")
    }
  }

  async function handleEditSurface(
    item: SurfaceProtection
  ) {
    if (!item.id) return

    const naziv = window.prompt(
      "Naziv površinske zaštite:",
      item.naziv
    )

    if (naziv === null) return

    const cijenaText = window.prompt(
      "Cijena:",
      String(item.cijena)
    )

    if (cijenaText === null) return

    const cijena = parseLocalizedNumber(cijenaText)

    if (cijena === null || cijena < 0) {
      alert("Neispravna cijena.")
      return
    }

    try {
      await updateSurfaceProtection(item.id, {
        naziv: naziv.trim(),
        cijena,
      })

      await loadData()
    } catch (error) {
      console.error(error)
      alert("Greška pri izmjeni.")
    }
  }

  async function handleDeleteSurface(
    item: SurfaceProtection
  ) {
    if (!item.id) return

    const confirmed = window.confirm(
      `Da li želiš obrisati "${item.naziv}"?`
    )

    if (!confirmed) return

    try {
      await deleteSurfaceProtection(item.id)
      await loadData()
    } catch (error) {
      console.error(error)
      alert("Greška pri brisanju.")
    }
  }

  async function handleEditHeat(
    item: HeatTreatment
  ) {
    if (!item.id) return

    const naziv = window.prompt(
      "Naziv termičke obrade:",
      item.naziv
    )

    if (naziv === null) return

    const cijenaText = window.prompt(
      "Cijena:",
      String(item.cijena)
    )

    if (cijenaText === null) return

    const cijena = parseLocalizedNumber(cijenaText)

    if (cijena === null || cijena < 0) {
      alert("Neispravna cijena.")
      return
    }

    try {
      await updateHeatTreatment(item.id, {
        naziv: naziv.trim(),
        cijena,
      })

      await loadData()
    } catch (error) {
      console.error(error)
      alert("Greška pri izmjeni.")
    }
  }

  async function handleDeleteHeat(
    item: HeatTreatment
  ) {
    if (!item.id) return

    const confirmed = window.confirm(
      `Da li želiš obrisati "${item.naziv}"?`
    )

    if (!confirmed) return

    try {
      await deleteHeatTreatment(item.id)
      await loadData()
    } catch (error) {
      console.error(error)
      alert("Greška pri brisanju.")
    }
  }

  function priceTypeLabel(type: PriceType) {
    if (type === "komad") return "€/kom"
    if (type === "kg") return "€/kg"
    return "fiksno"
  }

  if (loading) {
    return (
      <div className="treatments-page">
        <h1>Obrade i zaštite</h1>
        <p>Učitavanje...</p>
      </div>
    )
  }

  return (
    <div className="treatments-page">

      <div className="treatments-header">
        <div>
          <h1>Obrade i zaštite</h1>
          <p>
            Upravljanje površinskim zaštitama i termičkim
            obradama.
          </p>
        </div>
      </div>

      {/* ========================================= */}
      {/* POVRŠINSKE ZAŠTITE */}
      {/* ========================================= */}

      <section className="treatment-section">

        <div className="treatment-section-header">
          <h2>Površinske zaštite</h2>
        </div>

        {canCreateTreatment && <div className="add-treatment-form">

          <input
            type="text"
            placeholder="Naziv zaštite"
            value={newSurfaceName}
            onChange={(e) =>
              setNewSurfaceName(e.target.value)
            }
          />

          <LocalizedNumberInput
            min="0"
            step="0.01"
            placeholder="Cijena"
            value={newSurfacePrice}
            onValueChange={(value) => setNewSurfacePrice(formatLocalizedNumber(value))}
          />

          <select
            value={newSurfacePriceType}
            onChange={(e) =>
              setNewSurfacePriceType(
                e.target.value as PriceType
              )
            }
          >
            <option value="komad">€/kom</option>
            <option value="kg">€/kg</option>
            <option value="fiksno">Fiksna cijena</option>
          </select>

          <button
            className="primary-button"
            onClick={handleAddSurfaceProtection}
          >
            + Dodaj
          </button>

        </div>}

        <div className="treatment-table-wrapper">

          <table className="treatment-table">

            <thead>
              <tr>
                <th>Naziv</th>
                <th>Cijena</th>
                <th>Obračun</th>
                <th>Status</th>
                <th>Akcije</th>
              </tr>
            </thead>

            <tbody>

              {surfaceProtections.map((item) => (

                <tr key={item.id}>

                  <td>{item.naziv}</td>

                  <td>
                    {item.cijena.toFixed(2)} €
                  </td>

                  <td>
                    {priceTypeLabel(item.tipCijene)}
                  </td>

                  <td>
                    {item.aktivno !== false
                      ? "Aktivno"
                      : "Neaktivno"}
                  </td>

                  <td>

                    {canUpdateTreatment && <button
                      className="small-button"
                      onClick={() => handleEditSurface(item)}
                    >
                      Uredi
                    </button>}

                    {canDeleteTreatment && <button
                      className="small-danger-button"
                      onClick={() => handleDeleteSurface(item)}
                    >
                      Obriši
                    </button>}

                  </td>

                </tr>

              ))}

              {surfaceProtections.length === 0 && (
                <tr>
                  <td
                    colSpan={5}
                    className="empty-row"
                  >
                    Nema unesenih površinskih zaštita.
                  </td>
                </tr>
              )}

            </tbody>

          </table>

        </div>

      </section>

      {/* ========================================= */}
      {/* TERMIČKE OBRADE */}
      {/* ========================================= */}

      <section className="treatment-section">

        <div className="treatment-section-header">
          <h2>Termičke obrade</h2>
        </div>

        {canCreateTreatment && <div className="add-treatment-form">

          <input
            type="text"
            placeholder="Naziv termičke obrade"
            value={newHeatName}
            onChange={(e) =>
              setNewHeatName(e.target.value)
            }
          />

          <LocalizedNumberInput
            min="0"
            step="0.01"
            placeholder="Cijena"
            value={newHeatPrice}
            onValueChange={(value) => setNewHeatPrice(formatLocalizedNumber(value))}
          />

          <select
            value={newHeatPriceType}
            onChange={(e) =>
              setNewHeatPriceType(
                e.target.value as PriceType
              )
            }
          >
            <option value="komad">€/kom</option>
            <option value="kg">€/kg</option>
            <option value="fiksno">Fiksna cijena</option>
          </select>

          <button
            className="primary-button"
            onClick={handleAddHeatTreatment}
          >
            + Dodaj
          </button>

        </div>}

        <div className="treatment-table-wrapper">

          <table className="treatment-table">

            <thead>
              <tr>
                <th>Naziv</th>
                <th>Cijena</th>
                <th>Obračun</th>
                <th>Status</th>
                <th>Akcije</th>
              </tr>
            </thead>

            <tbody>

              {heatTreatments.map((item) => (

                <tr key={item.id}>

                  <td>{item.naziv}</td>

                  <td>
                    {item.cijena.toFixed(2)} €
                  </td>

                  <td>
                    {priceTypeLabel(item.tipCijene)}
                  </td>

                  <td>
                    {item.aktivno !== false
                      ? "Aktivno"
                      : "Neaktivno"}
                  </td>

                  <td>

                    {canUpdateTreatment && <button
                      className="small-button"
                      onClick={() => handleEditHeat(item)}
                    >
                      Uredi
                    </button>}

                    {canDeleteTreatment && <button
                      className="small-danger-button"
                      onClick={() => handleDeleteHeat(item)}
                    >
                      Obriši
                    </button>}

                  </td>

                </tr>

              ))}

              {heatTreatments.length === 0 && (
                <tr>
                  <td
                    colSpan={5}
                    className="empty-row"
                  >
                    Nema unesenih termičkih obrada.
                  </td>
                </tr>
              )}

            </tbody>

          </table>

        </div>

      </section>

    </div>
  )
}

export default Treatments