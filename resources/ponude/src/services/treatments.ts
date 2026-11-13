import { api } from "../api"

export type PriceType = "komad" | "kg" | "fiksno"

export interface SurfaceProtection {
  id?: string
  naziv: string
  cijena: number
  tipCijene: PriceType
  aktivno?: boolean
  napomena?: string
  createdAt?: Date
  updatedAt?: Date
}

export interface HeatTreatment {
  id?: string
  naziv: string
  cijena: number
  tipCijene: PriceType
  aktivno?: boolean
  napomena?: string
  createdAt?: Date
  updatedAt?: Date
}

export interface OtherOperation {
  id?: string
  naziv: string
  cijena: number
  tipCijene: PriceType
  aktivno?: boolean
  napomena?: string
  createdAt?: Date
  updatedAt?: Date
}

function normalizeTreatment(t: any): any {
  return {
    ...t,
    id: String(t.id ?? ""),
    cijena: Number(t.cijena) || 0,
    tipCijene: t.tip_cijene ?? t.tipCijene ?? "komad",
    aktivno: t.aktivno !== false,
    createdAt: t.created_at,
    updatedAt: t.updated_at,
  }
}

function toApi(t: any): Record<string, unknown> {
  return {
    naziv: t.naziv,
    cijena: Number(t.cijena),
    tip_cijene: t.tipCijene ?? t.tip_cijene,
    aktivno: t.aktivno,
    napomena: t.napomena,
  }
}

// Surface Protections
export async function addSurfaceProtection(protection: SurfaceProtection) {
  const result = await api.post('/surface-protections', toApi(protection))
  return String(result.id)
}

export async function getSurfaceProtections(): Promise<SurfaceProtection[]> {
  const data = await api.get<any[]>('/surface-protections')
  return data.map(normalizeTreatment)
}

export async function updateSurfaceProtection(id: string | number, protection: Partial<SurfaceProtection>) {
  await api.put(`/surface-protections/${id}`, toApi(protection))
}

export async function deleteSurfaceProtection(id: string | number) {
  await api.delete(`/surface-protections/${id}`)
}

// Heat Treatments
export async function addHeatTreatment(treatment: HeatTreatment) {
  const result = await api.post('/heat-treatments', toApi(treatment))
  return String(result.id)
}

export async function getHeatTreatments(): Promise<HeatTreatment[]> {
  const data = await api.get<any[]>('/heat-treatments')
  return data.map(normalizeTreatment)
}

export async function updateHeatTreatment(id: string | number, treatment: Partial<HeatTreatment>) {
  await api.put(`/heat-treatments/${id}`, toApi(treatment))
}

export async function deleteHeatTreatment(id: string | number) {
  await api.delete(`/heat-treatments/${id}`)
}

// Other Operations
export async function addOtherOperation(operation: OtherOperation) {
  const result = await api.post('/other-operations', toApi(operation))
  return String(result.id)
}

export async function getOtherOperations(): Promise<OtherOperation[]> {
  const data = await api.get<any[]>('/other-operations')
  return data.map(normalizeTreatment)
}

export async function updateOtherOperation(id: string | number, operation: Partial<OtherOperation>) {
  await api.put(`/other-operations/${id}`, toApi(operation))
}

export async function deleteOtherOperation(id: string | number) {
  await api.delete(`/other-operations/${id}`)
}
