import type { ReactNode } from "react"
import { Navigate, useLocation } from "react-router-dom"
import { useAuth } from "../auth/AuthContext"
import { hasPermission } from "../auth/permissions"

export default function RequireQuoteEditor({ children }: { children: ReactNode }) {
  const { profile, loading } = useAuth()
  const location = useLocation()
  const params = new URLSearchParams(location.search)
  const editId = params.get("edit")
  const copyId = params.get("copy")

  const allowed = editId
    ? hasPermission(profile, "quotes", "update")
    : hasPermission(profile, "quotes", "create") &&
      (!copyId || hasPermission(profile, "quotes", "view"))

  if (loading) {
    return <div className="auth-check-screen" role="status">Provjera dozvola...</div>
  }

  if (!allowed) {
    return <Navigate to="/quotes" replace />
  }

  return <>{children}</>
}
