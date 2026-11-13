import { api } from "../api"

export interface QuoteContactHistoryEntry {
  id: string
  datum: Date
  kanal: "email" | "telefon" | "sastanak" | "drugo"
  kontaktOsoba: string
  ishod: string
  napomena: string
  evidentirao: string
  evidentiranoU: Date
}

export async function addQuoteContactHistory(
  quoteId: string | number,
  entry: QuoteContactHistoryEntry,
): Promise<void> {
  if (!quoteId) throw new Error("Nedostaje ID ponude.")

  await api.post(`/quotes/${quoteId}/contact-history`, {
    datum: entry.datum instanceof Date ? entry.datum.toISOString().slice(0, 10) : entry.datum,
    kanal: entry.kanal,
    kontakt_osoba: entry.kontaktOsoba,
    ishod: entry.ishod,
    napomena: entry.napomena,
    evidentirao: entry.evidentirao,
  })
}
