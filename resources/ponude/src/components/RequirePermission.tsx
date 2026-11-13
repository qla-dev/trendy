import type { ReactNode } from "react"
import { Navigate } from "react-router-dom"
import { useAuth } from "../auth/AuthContext"
import { hasPermission, type PermissionAction, type PermissionModule } from "../auth/permissions"

export default function RequirePermission({
  module,
  action = "view",
  children,
}: {
  module: PermissionModule
  action?: PermissionAction
  children: ReactNode
}) {
  const { profile, loading } = useAuth()

  if (loading) {
    return <div className="auth-check-screen" role="status">Provjera dozvola...</div>
  }

  if (!hasPermission(profile, module, action)) {
    // quotes.view je obavezna privilegija za svakog kalkulanta i sigurna je
    // početna stranica čak i kada administrator isključi Dashboard.
    return <Navigate to="/quotes" replace />
  }

  return <>{children}</>
}
