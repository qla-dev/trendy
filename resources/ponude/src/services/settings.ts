import { api } from "../api"

export interface GeneralSettings {
  transportPricePerKg: number
  ensatPricePerPiece: number
}

const defaultSettings: GeneralSettings = {
  transportPricePerKg: 0,
  ensatPricePerPiece: 0,
}

export async function getGeneralSettings(): Promise<GeneralSettings> {
  try {
    const data = await api.get<any>('/settings')
    return {
      transportPricePerKg: Number(data.transportPricePerKg) || 0,
      ensatPricePerPiece: Number(data.ensatPricePerPiece) || 0,
    }
  } catch {
    return defaultSettings
  }
}

export async function updateGeneralSettings(settings: Partial<GeneralSettings>): Promise<GeneralSettings> {
  const result = await api.put<any>('/settings', settings)
  return {
    transportPricePerKg: Number(result.transportPricePerKg) || 0,
    ensatPricePerPiece: Number(result.ensatPricePerPiece) || 0,
  }
}
