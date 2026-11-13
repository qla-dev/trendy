import { api } from "../api"

export interface Position {
  id?: string
  sifra: string
  naziv: string
  kolicina?: number
  tipKomada?: string
  duzina?: number
  sirina?: number
  visina?: number
  precnik?: number
  rawWeightKg?: number
  outputWeightKg?: number
  materijal?: string
  masina?: string
  programiranje?: number
  priprema?: number
  obrada?: number
  kontrola?: number
  pakovanje?: number
  rezanje?: number
  oznacavanje?: number
  farbanje?: number
  transport?: number
  povrsinskaZastitaId?: string
  povrsinskaZastita?: string
  povrsinskaZastitaCijena?: number
  termickaObradaId?: string
  termickaObrada?: string
  termickaObradaCijena?: number
  otherOperationId?: string
  otherOperationName?: string
  otherOperationCost?: number
  otherCost1Description?: string
  otherCost1Amount?: number
  otherCost2Description?: string
  otherCost2Amount?: number
  ensatCount?: number
  ensatCostPerPiece?: number
  napomena?: string
  createdAt?: Date
  updatedAt?: Date
}

function normalize(p: any): Position {
  return {
    ...p,
    id: String(p.id ?? ""),
    tipKomada: p.tip_komada ?? p.tipKomada,
    rawWeightKg: p.raw_weight_kg ?? p.rawWeightKg,
    outputWeightKg: p.output_weight_kg ?? p.outputWeightKg,
    povrsinskaZastitaId: p.povrsinska_zastita_id ?? p.povrsinskaZastitaId,
    povrsinskaZastita: p.povrsinska_zastita ?? p.povrsinskaZastita,
    povrsinskaZastitaCijena: p.povrsinska_zastita_cijena ?? p.povrsinskaZastitaCijena,
    termickaObradaId: p.termicka_obrada_id ?? p.termickaObradaId,
    termickaObrada: p.termicka_obrada ?? p.termickaObrada,
    termickaObradaCijena: p.termicka_obrada_cijena ?? p.termickaObradaCijena,
    otherOperationId: p.other_operation_id ?? p.otherOperationId,
    otherOperationName: p.other_operation_name ?? p.otherOperationName,
    otherOperationCost: p.other_operation_cost ?? p.otherOperationCost,
    otherCost1Description: p.other_cost1_description ?? p.otherCost1Description,
    otherCost1Amount: p.other_cost1_amount ?? p.otherCost1Amount,
    otherCost2Description: p.other_cost2_description ?? p.otherCost2Description,
    otherCost2Amount: p.other_cost2_amount ?? p.otherCost2Amount,
    ensatCount: p.ensat_count ?? p.ensatCount,
    ensatCostPerPiece: p.ensat_cost_per_piece ?? p.ensatCostPerPiece,
    createdAt: p.created_at,
    updatedAt: p.updated_at,
  }
}

function toApi(p: Partial<Position>): Record<string, unknown> {
  return {
    sifra: p.sifra, naziv: p.naziv, kolicina: p.kolicina,
    tip_komada: p.tipKomada, duzina: p.duzina, sirina: p.sirina,
    visina: p.visina, precnik: p.precnik,
    raw_weight_kg: p.rawWeightKg, output_weight_kg: p.outputWeightKg,
    materijal: p.materijal, masina: p.masina,
    programiranje: p.programiranje, priprema: p.priprema, obrada: p.obrada,
    povrsinska_zastita_id: p.povrsinskaZastitaId,
    povrsinska_zastita: p.povrsinskaZastita,
    povrsinska_zastita_cijena: p.povrsinskaZastitaCijena,
    termicka_obrada_id: p.termickaObradaId,
    termicka_obrada: p.termickaObrada,
    termicka_obrada_cijena: p.termickaObradaCijena,
    other_operation_id: p.otherOperationId,
    other_operation_name: p.otherOperationName,
    other_operation_cost: p.otherOperationCost,
    other_cost1_description: p.otherCost1Description,
    other_cost1_amount: p.otherCost1Amount,
    other_cost2_description: p.otherCost2Description,
    other_cost2_amount: p.otherCost2Amount,
    ensat_count: p.ensatCount,
    ensat_cost_per_piece: p.ensatCostPerPiece,
    napomena: p.napomena,
  }
}

export async function addPosition(position: Position) {
  const result = await api.post('/positions', toApi(position))
  return String(result.id)
}

export async function getPositionByCode(sifra: string): Promise<Position | null> {
  const result = await api.get<any>(`/positions/by-code?sifra=${encodeURIComponent(sifra)}`)
  return result ? normalize(result) : null
}

export async function getPositionById(id: string | number): Promise<Position | null> {
  try {
    const result = await api.get<any>(`/positions/${id}`)
    return result ? normalize(result) : null
  } catch {
    return null
  }
}

export async function getAllPositions(): Promise<Position[]> {
  const data = await api.get<any[]>('/positions')
  return data.map(normalize)
}

export async function updatePosition(id: string | number, position: Partial<Position>) {
  await api.put(`/positions/${id}`, toApi(position))
}

export async function deletePosition(id: string | number) {
  await api.delete(`/positions/${id}`)
}
