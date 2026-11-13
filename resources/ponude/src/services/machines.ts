import { api } from "../api"

export interface MachineRecord {
  id?: string
  oznaka: string
  naziv: string
  tip: string
  satnicaProgramiranja: number
  satnicaPripreme: number
  satnicaObrade: number
  satnica?: number
  aktivna: boolean
  napomena: string
  createdAt?: unknown
  updatedAt?: unknown
}

export const INITIAL_MACHINES: Omit<MachineRecord, "id" | "createdAt" | "updatedAt">[] = [
  { oznaka: "Haas_UMC500", naziv: "Haas UMC500", tip: "CNC glodalica, 5 osa", satnicaProgramiranja: 35, satnicaPripreme: 32, satnicaObrade: 32, aktivna: true, napomena: "" },
  { oznaka: "Haas_ST30Y", naziv: "Haas ST30Y", tip: "CNC strug s Y-osom", satnicaProgramiranja: 35, satnicaPripreme: 32, satnicaObrade: 32, aktivna: true, napomena: "" },
  { oznaka: "Haas_VM9", naziv: "Haas VM9", tip: "CNC glodalica", satnicaProgramiranja: 35, satnicaPripreme: 35, satnicaObrade: 35, aktivna: true, napomena: "" },
]

function normalize(m: any): MachineRecord {
  return {
    id: String(m.id ?? ""),
    oznaka: String(m.oznaka ?? "").trim(),
    naziv: String(m.naziv ?? m.oznaka ?? "").trim(),
    tip: String(m.tip ?? "").trim(),
    satnicaProgramiranja: Number(m.satnica_programiranja ?? m.satnicaProgramiranja) || 35,
    satnicaPripreme: Number(m.satnica_pripreme ?? m.satnicaPripreme ?? m.satnica) || 0,
    satnicaObrade: Number(m.satnica_obrade ?? m.satnicaObrade ?? m.satnica) || 0,
    aktivna: m.aktivna !== false,
    napomena: String(m.napomena ?? ""),
    createdAt: m.created_at,
    updatedAt: m.updated_at,
  }
}

function toApi(m: Partial<MachineRecord>): Record<string, unknown> {
  return {
    oznaka: m.oznaka?.trim(),
    naziv: m.naziv?.trim(),
    tip: m.tip?.trim(),
    satnica_programiranja: Number(m.satnicaProgramiranja),
    satnica_pripreme: Number(m.satnicaPripreme),
    satnica_obrade: Number(m.satnicaObrade),
    aktivna: m.aktivna,
    napomena: m.napomena?.trim(),
  }
}

export async function getMachines(): Promise<MachineRecord[]> {
  const data = await api.get<any[]>('/machines')
  return data.map(normalize).filter((m) => m.oznaka).sort((a, b) => a.naziv.localeCompare(b.naziv))
}

export async function addMachine(machine: Omit<MachineRecord, "id" | "createdAt" | "updatedAt">): Promise<string> {
  const result = await api.post('/machines', toApi(machine))
  return String(result.id)
}

export async function updateMachine(id: string | number, changes: Partial<Omit<MachineRecord, "id" | "createdAt">>): Promise<void> {
  await api.put(`/machines/${id}`, toApi(changes as MachineRecord))
}

export async function seedMissingInitialMachines(existingMachines: MachineRecord[]): Promise<number> {
  const existingCodes = new Set(existingMachines.map((m) => m.oznaka.trim().toLocaleUpperCase()))
  const missing = INITIAL_MACHINES.filter((m) => !existingCodes.has(m.oznaka.trim().toLocaleUpperCase()))
  for (const machine of missing) await addMachine(machine)
  return missing.length
}
