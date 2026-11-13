import type { ReactNode } from "react"
import { Navigate } from "react-router-dom"
import { useAuth } from "../auth/AuthContext"

export default function RequireAuth({ children }: { children: ReactNode }) {
  const { profile, loading } = useAuth()

  if (loading) {
    return (
      <div className="auth-check-screen" role="status">
        Provjera prijave...
      </div>
    )
  }

  if (!profile) {
    return <Navigate to="/login" replace />
  }

  return <>{children}</>
}
