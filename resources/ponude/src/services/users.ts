import { api } from "../api"
import { normalizePermissions, type UserPermissions, type UserRole } from "../auth/permissions"

export interface ManagedUser {
  uid: string
  imePrezime: string
  email: string
  role: UserRole
  aktivan: boolean
  permissions: UserPermissions
  createdAt?: unknown
  updatedAt?: unknown
}

interface CreateManagedUserInput {
  imePrezime: string
  email: string
  password: string
  permissions: UserPermissions
}

interface UpdateManagedUserInput {
  uid: string
  imePrezime: string
  aktivan: boolean
  permissions: UserPermissions
}

export async function getManagedUsers(): Promise<ManagedUser[]> {
  const data = await api.get<any[]>('/users')
  return data.map((u) => ({
    uid: String(u.id),
    imePrezime: u.imePrezime ?? u.ime_prezime ?? "",
    email: u.email ?? "",
    role: u.role === "admin" ? "admin" : "kalkulant" as UserRole,
    aktivan: u.aktivan === true,
    permissions: normalizePermissions(u.permissions),
    createdAt: u.created_at,
    updatedAt: u.updated_at,
  })).sort((a, b) => {
    if (a.role !== b.role) return a.role === "admin" ? -1 : 1
    if (a.aktivan !== b.aktivan) return a.aktivan ? -1 : 1
    return a.imePrezime.localeCompare(b.imePrezime, "bs")
  })
}

export async function createManagedUser(input: CreateManagedUserInput): Promise<string> {
  const result = await api.post<any>('/users', {
    ime_prezime: input.imePrezime.trim(),
    email: input.email.trim().toLowerCase(),
    password: input.password,
    permissions: input.permissions,
  })
  return String(result.id)
}

export async function updateManagedUser(input: UpdateManagedUserInput): Promise<void> {
  await api.put(`/users/${input.uid}`, {
    ime_prezime: input.imePrezime.trim(),
    aktivan: input.aktivan,
    permissions: input.permissions,
  })
}

export async function sendManagedUserPasswordReset(email: string): Promise<void> {
  const users = await getManagedUsers()
  const user = users.find((u) => u.email.toLowerCase() === email.toLowerCase())
  if (!user) throw new Error("Korisnik s ovom e-mail adresom nije pronađen.")
  // Generate a temporary password and reset it
  const tempPassword = Math.random().toString(36).slice(2) + Math.random().toString(36).slice(2)
  await api.post(`/users/${user.uid}/reset-password`, { password: tempPassword })
  alert(`Nova privremena lozinka za ${email}: ${tempPassword}\n\nKorisnik mora promijeniti lozinku nakon prijave.`)
}
