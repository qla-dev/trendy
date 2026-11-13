import { api } from "../api"

export interface QuoteDraftRecord {
  draftId: string
  ownerUid: string
  label: string
  positionCount: number
  createdAtMs: number
  updatedAtMs: number
  state: Record<string, unknown>
}

function normalize(d: any): QuoteDraftRecord {
  return {
    draftId: d.draft_id ?? d.draftId ?? "",
    ownerUid: String(d.owner_id ?? d.ownerUid ?? ""),
    label: d.label ?? "Nedovršena ponuda",
    positionCount: Number(d.position_count ?? d.positionCount) || 0,
    createdAtMs: Number(d.created_at_ms ?? d.createdAtMs) || 0,
    updatedAtMs: Number(d.updated_at_ms ?? d.updatedAtMs) || 0,
    state: (d.state && typeof d.state === "object" ? d.state : {}) as Record<string, unknown>,
  }
}

export async function getQuoteDraft(
  _ownerUid: string,
  draftId: string,
): Promise<QuoteDraftRecord | null> {
  if (!draftId) return null
  const result = await api.get<any>(`/drafts/${encodeURIComponent(draftId)}`)
  return result ? normalize(result) : null
}

export async function listQuoteDrafts(_ownerUid: string): Promise<QuoteDraftRecord[]> {
  const data = await api.get<any[]>('/drafts')
  return data.map(normalize).filter((d) => d.draftId && d.createdAtMs > 0).sort((a, b) => b.updatedAtMs - a.updatedAtMs)
}

export async function saveQuoteDraft(
  _ownerUid: string,
  draftId: string,
  input: Omit<QuoteDraftRecord, "ownerUid" | "draftId">,
): Promise<void> {
  if (!draftId) throw new Error("Nedostaje ID radne ponude.")

  await api.post('/drafts', {
    draft_id: draftId,
    label: String(input.label || "Nedovršena ponuda").slice(0, 160),
    position_count: Math.max(0, Math.min(500, Math.floor(Number(input.positionCount) || 0))),
    created_at_ms: Math.max(1, Math.floor(Number(input.createdAtMs) || Date.now())),
    updated_at_ms: Math.max(1, Math.floor(Number(input.updatedAtMs) || Date.now())),
    state: input.state,
  })
}

export async function deleteQuoteDraft(_ownerUid: string, draftId: string): Promise<void> {
  if (!draftId) return
  await api.delete(`/drafts/${encodeURIComponent(draftId)}`)
}
