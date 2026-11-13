import { api } from "../api"

export interface MaterialRecord {
  id?: string
  oznaka: string
  naziv: string
  grupa: string
  standard: string
  gustina: number
  cijenaPoKg: number
  aktivno: boolean
  napomena: string
  createdAt?: unknown
  updatedAt?: unknown
}

export const INITIAL_MATERIALS: Omit<MaterialRecord, "id" | "createdAt" | "updatedAt">[] = [
  { oznaka: "S235", naziv: "S235", grupa: "Čelik", standard: "", gustina: 7850, cijenaPoKg: 1.5, aktivno: true, napomena: "" },
  { oznaka: "C45", naziv: "C45", grupa: "Čelik", standard: "", gustina: 7850, cijenaPoKg: 2.1, aktivno: true, napomena: "" },
  { oznaka: "42CrMo4", naziv: "42CrMo4", grupa: "Legirani čelik", standard: "", gustina: 7850, cijenaPoKg: 3.2, aktivno: true, napomena: "" },
  { oznaka: "AlMg3", naziv: "AlMg3", grupa: "Aluminij", standard: "", gustina: 2670, cijenaPoKg: 4.5, aktivno: true, napomena: "" },
  { oznaka: "AlMg4_5", naziv: "AlMg4.5", grupa: "Aluminij", standard: "", gustina: 2660, cijenaPoKg: 5.2, aktivno: true, napomena: "" },
]

function normalize(m: any): MaterialRecord {
  return {
    id: String(m.id ?? ""),
    oznaka: String(m.oznaka ?? "").trim(),
    naziv: String(m.naziv ?? m.oznaka ?? "").trim(),
    grupa: String(m.grupa ?? "").trim(),
    standard: String(m.standard ?? "").trim(),
    gustina: Number(m.gustina) || 0,
    cijenaPoKg: Number(m.cijena_po_kg ?? m.cijenaPoKg) || 0,
    aktivno: m.aktivno !== false,
    napomena: String(m.napomena ?? ""),
    createdAt: m.created_at,
    updatedAt: m.updated_at,
  }
}

function toApi(m: Partial<MaterialRecord>): Record<string, unknown> {
  return {
    oznaka: m.oznaka?.trim(),
    naziv: m.naziv?.trim(),
    grupa: m.grupa?.trim(),
    standard: m.standard?.trim(),
    gustina: Number(m.gustina),
    cijena_po_kg: Number(m.cijenaPoKg),
    aktivno: m.aktivno,
    napomena: m.napomena?.trim(),
  }
}

export async function getMaterials(): Promise<MaterialRecord[]> {
  const data = await api.get<any[]>('/materials')
  return data.map(normalize).filter((m) => m.oznaka).sort((a, b) => a.oznaka.localeCompare(b.oznaka))
}

export async function addMaterial(material: Omit<MaterialRecord, "id" | "createdAt" | "updatedAt">): Promise<string> {
  const result = await api.post('/materials', toApi(material))
  return String(result.id)
}

export async function updateMaterial(id: string | number, changes: Partial<Omit<MaterialRecord, "id" | "createdAt">>): Promise<void> {
  await api.put(`/materials/${id}`, toApi(changes as MaterialRecord))
}

export async function seedMissingInitialMaterials(existingMaterials: MaterialRecord[]): Promise<number> {
  const existingCodes = new Set(existingMaterials.map((m) => m.oznaka.trim().toLocaleUpperCase()))
  const missing = INITIAL_MATERIALS.filter((m) => !existingCodes.has(m.oznaka.trim().toLocaleUpperCase()))
  for (const material of missing) await addMaterial(material)
  return missing.length
}
