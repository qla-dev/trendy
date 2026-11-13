export type UserRole = "admin" | "kalkulant"

export type PermissionAction = "view" | "create" | "update" | "delete"

export type PermissionModule =
  | "dashboard"
  | "quotes"
  | "positions"
  | "history"
  | "analysis"
  | "customers"
  | "materials"
  | "machines"
  | "treatments"

export type UserPermissions = Record<
  PermissionModule,
  Partial<Record<PermissionAction, boolean>>
>

export const PERMISSION_GROUPS: Array<{
  key: PermissionModule
  label: string
  actions: PermissionAction[]
}> = [
  { key: "dashboard", label: "Dashboard", actions: ["view"] },
  { key: "quotes", label: "Ponude", actions: ["view", "create", "update", "delete"] },
  { key: "positions", label: "Pretraživanje pozicija", actions: ["view", "create", "update", "delete"] },
  { key: "history", label: "Historija kalkulacija", actions: ["view"] },
  { key: "analysis", label: "Analiza i profitabilnost", actions: ["view"] },
  { key: "customers", label: "Kupci", actions: ["view", "create", "update", "delete"] },
  { key: "materials", label: "Materijali", actions: ["view", "create", "update", "delete"] },
  { key: "machines", label: "Mašine i satnice", actions: ["view", "create", "update", "delete"] },
  { key: "treatments", label: "Površinske i termičke obrade", actions: ["view", "create", "update", "delete"] },
]

export const ACTION_LABELS: Record<PermissionAction, string> = {
  view: "Pregled",
  create: "Kreiranje",
  update: "Izmjena",
  delete: "Brisanje",
}

/**
 * Historijski pristup koji postojeći kalkulanti zadržavaju ako njihov Firestore
 * profil još nema polje `permissions`. Nemoj koristiti ove vrijednosti kao
 * početna prava za nove račune.
 */
export const DEFAULT_KALKULANT_PERMISSIONS: UserPermissions = {
  dashboard: { view: true },
  quotes: { view: true, create: true, update: true, delete: true },
  positions: { view: true, create: true, update: true, delete: true },
  history: { view: true },
  analysis: { view: true },
  customers: { view: false, create: false, update: false, delete: false },
  materials: { view: false, create: false, update: false, delete: false },
  machines: { view: false, create: false, update: false, delete: false },
  treatments: { view: false, create: false, update: false, delete: false },
}

/** Least-privilege rights for a newly created kalkulant. Quote viewing remains
 * enabled because the current app uses quotes for its required landing page
 * and because the owner confirmed that quote viewers may see costs and margin.
 * All write and optional-module privileges must be explicitly granted.
 */
const NEW_KALKULANT_PERMISSIONS: UserPermissions = {
  dashboard: { view: false },
  quotes: { view: true, create: false, update: false, delete: false },
  positions: { view: false, create: false, update: false, delete: false },
  history: { view: false },
  analysis: { view: false },
  customers: { view: false, create: false, update: false, delete: false },
  materials: { view: false, create: false, update: false, delete: false },
  machines: { view: false, create: false, update: false, delete: false },
  treatments: { view: false, create: false, update: false, delete: false },
}

function clonePermissions(source: UserPermissions): UserPermissions {
  return Object.fromEntries(
    PERMISSION_GROUPS.map(({ key }) => [key, { ...source[key] }]),
  ) as UserPermissions
}

/** Defaults shown in the form when the administrator creates a new account. */
export function createDefaultPermissions(): UserPermissions {
  return clonePermissions(NEW_KALKULANT_PERMISSIONS)
}

/**
 * Legacy user documents do not contain a permissions map. Preserve the access
 * those users had before this feature. For explicit permission maps, missing
 * or unknown actions are denied by default.
 */
export function normalizePermissions(value: unknown): UserPermissions {
  if (value === null || typeof value !== "object" || Array.isArray(value)) {
    return clonePermissions(DEFAULT_KALKULANT_PERMISSIONS)
  }

  const source = value as Record<string, unknown>
  const normalized = {} as UserPermissions

  for (const { key, actions } of PERMISSION_GROUPS) {
    const moduleValue = source[key]
    const modulePermissions =
      moduleValue !== null && typeof moduleValue === "object" && !Array.isArray(moduleValue)
        ? (moduleValue as Record<string, unknown>)
        : {}

    normalized[key] = {}
    for (const action of actions) {
      normalized[key][action] = modulePermissions[action] === true
    }
  }

  return normalized
}

export type PermissionProfile = {
  role: UserRole
  permissions?: UserPermissions
} | null | undefined

export function hasPermission(
  profile: PermissionProfile,
  module: PermissionModule,
  action: PermissionAction,
): boolean {
  if (!profile) return false
  if (profile.role === "admin") return true
  return profile.permissions?.[module]?.[action] === true
}

/** Ensures modules that read the full quotes collection cannot be granted alone. */
export function validatePermissionDependencies(permissions: UserPermissions): string | null {
  const quoteDependentModules: PermissionModule[] = ["dashboard", "history", "analysis"]
  if (
    quoteDependentModules.some((module) => permissions[module].view === true) &&
    permissions.quotes.view !== true
  ) {
    return "Za Dashboard, historiju i analizu potrebno je omogućiti i pregled ponuda."
  }
  return null
}
