import { api } from "../api"

export type ActivityAction = "create" | "update" | "delete" | "activate" | "deactivate" | "reset_password"

export type ActivityEntity =
  | "quote" | "position" | "customer" | "material" | "machine"
  | "surfaceProtection" | "heatTreatment" | "otherOperation" | "user" | "settings"

export interface ActivityLogEntry {
  id: string
  actorUid: string
  actorName: string
  actorEmail: string
  action: ActivityAction
  entityType: ActivityEntity
  entityId: string
  entityLabel: string
  description: string
  createdAt: unknown
}

interface LogActivityInput {
  action: ActivityAction
  entityType: ActivityEntity
  entityId: string
  entityLabel: string
  description: string
}

export async function logActivity(_input: LogActivityInput): Promise<void> {
  // Activity logging is handled server-side by the API.
  // This function is kept for interface compatibility.
}

export async function getRecentActivityLogs(maxItems = 200): Promise<ActivityLogEntry[]> {
  const data = await api.get<any[]>(`/activity-logs?limit=${maxItems}`)
  return data.map((d) => ({
    id: String(d.id ?? ""),
    actorUid: String(d.actor_id ?? ""),
    actorName: d.actor_name ?? "",
    actorEmail: d.actor_email ?? "",
    action: d.action,
    entityType: d.entity_type,
    entityId: d.entity_id ?? "",
    entityLabel: d.entity_label ?? "",
    description: d.description ?? "",
    createdAt: d.created_at,
  }))
}
