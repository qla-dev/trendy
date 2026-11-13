import { useState, type FormEvent } from "react"
import { Navigate } from "react-router-dom"
import { useAuth } from "../auth/AuthContext"
import "./Login.css"

function Login() {
  const { profile, loading, error, login, clearError } = useAuth()
  const [email, setEmail] = useState("")
  const [password, setPassword] = useState("")
  const [submitting, setSubmitting] = useState(false)
  const [localError, setLocalError] = useState("")

  if (!loading && profile) {
    return <Navigate to="/" replace />
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setLocalError("")
    clearError()
    setSubmitting(true)

    try {
      await login(email, password)
    } catch (submitError) {
      setLocalError(
        submitError instanceof Error
          ? submitError.message
          : "Prijava nije uspjela. Pokušajte ponovo.",
      )
    } finally {
      setSubmitting(false)
    }
  }

  const visibleError = localError || error

  return (
    <div className="login-screen">
      <section className="login-card">
        <div className="login-logo">CNC</div>
        <p className="login-eyebrow">CNC QUOTING SYSTEM</p>
        <h1>Prijava u sistem</h1>
        <p className="login-description">
          Unesite svoje korisničke podatke za nastavak.
        </p>

        <form onSubmit={handleSubmit} className="login-form">
          <label htmlFor="login-email">E-mail adresa</label>
          <input
            id="login-email"
            type="email"
            autoComplete="username"
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            placeholder="ime@firma.ba"
            required
          />

          <label htmlFor="login-password">Lozinka</label>
          <input
            id="login-password"
            type="password"
            autoComplete="current-password"
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            placeholder="Unesite lozinku"
            required
          />

          {visibleError && (
            <div className="login-error" role="alert">
              {visibleError}
            </div>
          )}

          <button type="submit" disabled={submitting || loading}>
            {submitting ? "Prijava u toku..." : "Prijavi se"}
          </button>
        </form>

        <div className="login-footer">Pristup je dozvoljen samo registrovanim korisnicima.</div>
      </section>
    </div>
  )
}

export default Login
