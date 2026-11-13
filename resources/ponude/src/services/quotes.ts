import { api } from "../api"

export type QuoteStatus = "draft" | "sent" | "accepted" | "rejected"

export interface QuotePosition {
  positionNumber: string
  code: string
  name: string
  quantity: number
  shape: string
  length: number
  width: number
  height: number
  diameter: number
  material: string
  programmingMinutes: number
  setupMinutes: number
  machiningMinutes: number
  machine: string
  surfaceProtectionId?: string
  surfaceProtectionName?: string
  surfaceProtection?: { id?: string; naziv: string; cijena: number; tipCijene: string; napomena?: string } | null
  heatTreatmentId?: string
  heatTreatmentName?: string
  heatTreatment?: { id?: string; naziv: string; cijena: number; tipCijene: string; napomena?: string } | null
  otherOperationId?: string
  otherOperationName?: string
  otherOperation?: { id?: string; naziv: string; cijena: number; tipCijene: string; napomena?: string } | null
  otherCost1Description?: string
  otherCost1Amount?: number
  otherCost2Description?: string
  otherCost2Amount?: number
  ensatCount?: number
  ensatPricePerPiece?: number
  weightKg: number
  outputWeightKg?: number
  outputWeightUsedKg?: number
  materialCost: number
  programmingCost: number
  setupCost: number
  machiningCost: number
  surfaceProtectionCost: number
  heatTreatmentCost: number
  otherOperationCost?: number
  otherCostsTotal?: number
  ensatCostPerPiece?: number
  totalCostPerPiece: number
  totalCost: number
  machineRate: number
  marginPercent: number
  marginAmount: number
  marginValuePerPiece: number
  sellingPricePerPiece: number
  totalSellingPrice: number
  staffelpreise?: QuoteTierPrice[]
  staffelKolicine?: number[]
}

export interface QuoteTierPrice {
  quantity: number
  totalCostPerPiece: number
  totalCost: number
  sellingPricePerPiece: number
  totalSellingPrice: number
  marginValuePerPiece: number
}

export interface Quote {
  id?: string
  brojPonude: string
  brojUpitaKupca?: string
  datum: Date | any
  status: QuoteStatus
  kupacId: string
  kupacNaziv: string
  marginPercent: number
  transportIncluded?: boolean
  positions: QuotePosition[]
  staffelpreiseAktivno?: boolean
  staffelNacin?: "shared" | "per-position"
  staffelKolicine?: number[]
  totalCost: number
  totalSellingPrice: number
  createdBy: string
  createdAt?: Date | any
  updatedAt?: Date | any
  vrijemeIzradePonudePocetak?: Date | any
  vrijemeIzradePonudeKraj?: Date | any
  vrijemeIzradePonudeMs?: number
}

function coerceTreatmentId(t: any): any {
  if (!t) return t
  return { ...t, id: t.id != null ? String(t.id) : undefined }
}

function positionToApi(p: QuotePosition): Record<string, unknown> {
  return {
    position_number: p.positionNumber,
    code: p.code,
    name: p.name,
    quantity: p.quantity,
    shape: p.shape,
    length: p.length,
    width: p.width,
    height: p.height,
    diameter: p.diameter,
    material: p.material,
    programming_minutes: p.programmingMinutes,
    setup_minutes: p.setupMinutes,
    machining_minutes: p.machiningMinutes,
    machine: p.machine,
    surface_protection_id: p.surfaceProtectionId,
    surface_protection_name: p.surfaceProtectionName,
    surface_protection: p.surfaceProtection,
    heat_treatment_id: p.heatTreatmentId,
    heat_treatment_name: p.heatTreatmentName,
    heat_treatment: p.heatTreatment,
    other_operation_id: p.otherOperationId,
    other_operation_name: p.otherOperationName,
    other_operation: p.otherOperation,
    other_cost1_description: p.otherCost1Description,
    other_cost1_amount: p.otherCost1Amount,
    other_cost2_description: p.otherCost2Description,
    other_cost2_amount: p.otherCost2Amount,
    ensat_count: p.ensatCount,
    ensat_price_per_piece: p.ensatPricePerPiece,
    weight_kg: p.weightKg,
    output_weight_kg: p.outputWeightKg,
    output_weight_used_kg: p.outputWeightUsedKg,
    material_cost: p.materialCost,
    programming_cost: p.programmingCost,
    setup_cost: p.setupCost,
    machining_cost: p.machiningCost,
    surface_protection_cost: p.surfaceProtectionCost,
    heat_treatment_cost: p.heatTreatmentCost,
    other_operation_cost: p.otherOperationCost,
    other_costs_total: p.otherCostsTotal,
    ensat_cost_per_piece: p.ensatCostPerPiece,
    total_cost_per_piece: p.totalCostPerPiece,
    total_cost: p.totalCost,
    machine_rate: p.machineRate,
    margin_percent: p.marginPercent,
    margin_amount: p.marginAmount,
    margin_value_per_piece: p.marginValuePerPiece,
    selling_price_per_piece: p.sellingPricePerPiece,
    total_selling_price: p.totalSellingPrice,
    staffelpreise: p.staffelpreise,
    staffel_kolicine: p.staffelKolicine,
  }
}

function normalizePosition(p: any): QuotePosition {
  return {
    positionNumber: p.position_number ?? p.positionNumber ?? "",
    code: p.code ?? "",
    name: p.name ?? "",
    quantity: Number(p.quantity) || 1,
    shape: p.shape ?? "",
    length: Number(p.length) || 0,
    width: Number(p.width) || 0,
    height: Number(p.height) || 0,
    diameter: Number(p.diameter) || 0,
    material: p.material ?? "",
    programmingMinutes: Number(p.programming_minutes ?? p.programmingMinutes) || 0,
    setupMinutes: Number(p.setup_minutes ?? p.setupMinutes) || 0,
    machiningMinutes: Number(p.machining_minutes ?? p.machiningMinutes) || 0,
    machine: p.machine ?? "",
    surfaceProtectionId: p.surface_protection_id != null ? String(p.surface_protection_id) : p.surfaceProtectionId,
    surfaceProtectionName: p.surface_protection_name ?? p.surfaceProtectionName,
    surfaceProtection: coerceTreatmentId(p.surface_protection ?? p.surfaceProtection),
    heatTreatmentId: p.heat_treatment_id != null ? String(p.heat_treatment_id) : p.heatTreatmentId,
    heatTreatmentName: p.heat_treatment_name ?? p.heatTreatmentName,
    heatTreatment: coerceTreatmentId(p.heat_treatment ?? p.heatTreatment),
    otherOperationId: p.other_operation_id != null ? String(p.other_operation_id) : p.otherOperationId,
    otherOperationName: p.other_operation_name ?? p.otherOperationName,
    otherOperation: coerceTreatmentId(p.other_operation ?? p.otherOperation),
    otherCost1Description: p.other_cost1_description ?? p.otherCost1Description,
    otherCost1Amount: p.other_cost1_amount ?? p.otherCost1Amount,
    otherCost2Description: p.other_cost2_description ?? p.otherCost2Description,
    otherCost2Amount: p.other_cost2_amount ?? p.otherCost2Amount,
    ensatCount: p.ensat_count ?? p.ensatCount,
    ensatPricePerPiece: p.ensat_price_per_piece ?? p.ensatPricePerPiece,
    weightKg: Number(p.weight_kg ?? p.weightKg) || 0,
    outputWeightKg: p.output_weight_kg ?? p.outputWeightKg,
    outputWeightUsedKg: p.output_weight_used_kg ?? p.outputWeightUsedKg,
    materialCost: Number(p.material_cost ?? p.materialCost) || 0,
    programmingCost: Number(p.programming_cost ?? p.programmingCost) || 0,
    setupCost: Number(p.setup_cost ?? p.setupCost) || 0,
    machiningCost: Number(p.machining_cost ?? p.machiningCost) || 0,
    surfaceProtectionCost: Number(p.surface_protection_cost ?? p.surfaceProtectionCost) || 0,
    heatTreatmentCost: Number(p.heat_treatment_cost ?? p.heatTreatmentCost) || 0,
    otherOperationCost: p.other_operation_cost ?? p.otherOperationCost,
    otherCostsTotal: p.other_costs_total ?? p.otherCostsTotal,
    ensatCostPerPiece: p.ensat_cost_per_piece ?? p.ensatCostPerPiece,
    totalCostPerPiece: Number(p.total_cost_per_piece ?? p.totalCostPerPiece) || 0,
    totalCost: Number(p.total_cost ?? p.totalCost) || 0,
    machineRate: Number(p.machine_rate ?? p.machineRate) || 0,
    marginPercent: Number(p.margin_percent ?? p.marginPercent) || 0,
    marginAmount: Number(p.margin_amount ?? p.marginAmount) || 0,
    marginValuePerPiece: Number(p.margin_value_per_piece ?? p.marginValuePerPiece) || 0,
    sellingPricePerPiece: Number(p.selling_price_per_piece ?? p.sellingPricePerPiece) || 0,
    totalSellingPrice: Number(p.total_selling_price ?? p.totalSellingPrice) || 0,
    staffelpreise: p.staffelpreise,
    staffelKolicine: p.staffel_kolicine ?? p.staffelKolicine,
  }
}

function normalizeQuote(q: any): Quote {
  return {
    id: String(q.id ?? ""),
    brojPonude: q.broj_ponude ?? q.brojPonude ?? "",
    brojUpitaKupca: q.broj_upita_kupca ?? q.brojUpitaKupca,
    datum: q.datum ?? q.created_at ?? null,
    status: q.status ?? "draft",
    kupacId: String(q.kupac_id ?? q.kupacId ?? ""),
    kupacNaziv: q.kupac_naziv ?? q.kupacNaziv ?? "",
    marginPercent: Number(q.margin_percent ?? q.marginPercent) || 0,
    transportIncluded: q.transport_included ?? q.transportIncluded,
    positions: (q.positions || []).map(normalizePosition),
    staffelpreiseAktivno: q.staffelpreise_aktivno ?? q.staffelpreiseAktivno,
    staffelNacin: q.staffel_nacin ?? q.staffelNacin,
    staffelKolicine: q.staffel_kolicine ?? q.staffelKolicine,
    totalCost: Number(q.total_cost ?? q.totalCost) || 0,
    totalSellingPrice: Number(q.total_selling_price ?? q.totalSellingPrice) || 0,
    createdBy: q.created_by ?? q.createdBy ?? "",
    createdAt: q.created_at ?? q.createdAt,
    updatedAt: q.updated_at ?? q.updatedAt,
    vrijemeIzradePonudePocetak: q.vrijeme_izrade_pocetak ?? q.vrijemeIzradePonudePocetak,
    vrijemeIzradePonudeKraj: q.vrijeme_izrade_kraj ?? q.vrijemeIzradePonudeKraj,
    vrijemeIzradePonudeMs: q.vrijeme_izrade_ms ?? q.vrijemeIzradePonudeMs,
  }
}

function quoteToApi(q: Partial<Quote>): Record<string, unknown> {
  return {
    broj_ponude: q.brojPonude,
    broj_upita_kupca: q.brojUpitaKupca,
    datum: q.datum instanceof Date ? q.datum.toISOString().slice(0, 10) : q.datum,
    status: q.status,
    kupac_id: q.kupacId || null,
    kupac_naziv: q.kupacNaziv,
    margin_percent: q.marginPercent,
    transport_included: q.transportIncluded,
    staffelpreise_aktivno: q.staffelpreiseAktivno,
    staffel_nacin: q.staffelNacin,
    staffel_kolicine: q.staffelKolicine,
    total_cost: q.totalCost,
    total_selling_price: q.totalSellingPrice,
    created_by: q.createdBy,
    vrijeme_izrade_pocetak: q.vrijemeIzradePonudePocetak,
    vrijeme_izrade_kraj: q.vrijemeIzradePonudeKraj,
    vrijeme_izrade_ms: q.vrijemeIzradePonudeMs,
    positions: q.positions?.map(positionToApi),
  }
}

export async function addQuote(quote: Omit<Quote, "id">) {
  const result = await api.post<any>('/quotes', quoteToApi(quote))
  return normalizeQuote(result)
}

export async function updateQuote(id: string | number, quote: Partial<Omit<Quote, "id">>) {
  const result = await api.put<any>(`/quotes/${id}`, quoteToApi(quote))
  return normalizeQuote(result)
}

export async function deleteQuote(id: string | number): Promise<void> {
  await api.delete(`/quotes/${id}`)
}

export async function getAllQuotes(): Promise<Quote[]> {
  const data = await api.get<any[]>('/quotes')
  return data.map(normalizeQuote)
}

export async function getQuoteById(id: string | number): Promise<Quote | null> {
  try {
    const result = await api.get<any>(`/quotes/${id}`)
    return result ? normalizeQuote(result) : null
  } catch {
    return null
  }
}
