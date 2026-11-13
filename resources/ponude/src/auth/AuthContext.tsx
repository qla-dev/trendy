import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react"
import { api, setToken, clearToken, hasToken } from "../api"
import { normalizePermissions, type UserPermissions, type UserRole } from "./permissions"

export type { UserRole, UserPermissions } from "./permissions"

export type UserProfile = {
  uid: string
  imePrezime: string
  email: string
  role: UserRole
  aktivan: boolean
  permissions: UserPermissions
}

type AuthContextValue = {
  profile: UserProfile | null
  loading: boolean
  error: string
  login: (email: string, password: string) => Promise<void>
  logout: () => Promise<void>
  clearError: () => void
}

const AuthContext = createContext<AuthContextValue | undefined>(undefined)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [profile, setProfile] = useState<UserProfile | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState("")

  useEffect(() => {
    if (!hasToken()) {
      setLoading(false)
      return
    }

    api.get('/auth/me')
      .then((data) => {
        const role = data.role
        if (role !== "admin" && role !== "kalkulant") {
          setError("Korisnički nalog nema važeću ulogu.")
          clearToken()
          setLoading(false)
          return
        }

        setProfile({
          uid: String(data.id),
          imePrezime: data.imePrezime || data.email || "Korisnik",
          email: data.email || "",
          role,
          aktivan: data.aktivan !== false,
          permissions: normalizePermissions(data.permissions),
        })
        setLoading(false)
      })
      .catch(() => {
        clearToken()
        setLoading(false)
      })
  }, [])

  const login = useCallback(async (email: string, password: string) => {
    setError("")
    try {
      const data = await api.post('/auth/login', {
        email: email.trim(),
        password,
      })

      setToken(data.token)

      const user = data.user
      setProfile({
        uid: String(user.id),
        imePrezime: user.imePrezime || user.email || "Korisnik",
        email: user.email || "",
        role: user.role,
        aktivan: user.aktivan !== false,
        permissions: normalizePermissions(user.permissions),
      })
    } catch (loginError) {
      const message = loginError instanceof Error ? loginError.message : String(loginError)
      setError(`Greška prijave: ${message}`)
      throw new Error(message)
    }
  }, [])

  const logout = useCallback(async () => {
    setError("")
    try {
      await api.post('/auth/logout')
    } catch {
      // Ignore logout errors
    }
    clearToken()
    setProfile(null)
  }, [])

  const clearErrorFn = useCallback(() => setError(""), [])

  const value = useMemo(
    () => ({ profile, loading, error, login, logout, clearError: clearErrorFn }),
    [profile, loading, error, login, logout, clearErrorFn],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth() {
  const context = useContext(AuthContext)
  if (!context) {
    throw new Error("useAuth se mora koristiti unutar AuthProvider komponente.")
  }
  return context
}
