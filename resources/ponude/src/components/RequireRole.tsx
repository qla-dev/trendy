import type { ReactNode } from "react"
import { Navigate } from "react-router-dom"
import { useAuth, type UserRole } from "../auth/AuthContext"

export default function RequireRole({
  allowedRoles,
  children,
}: {
  allowedRoles: UserRole[]
  children: ReactNode
}) {
  const { profile, loading } = useAuth()

  if (loading) {
    return (
      <div className="auth-check-screen" role="status">
        Provjera dozvola...
      </div>
    )
  }

  if (!profile || !allowedRoles.includes(profile.role)) {
    return <Navigate to="/" replace />
  }

  return <>{children}</>
}
