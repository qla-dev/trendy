import { NavLink, Outlet, useNavigate } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'
import { hasPermission } from '../auth/permissions'
import './MainLayout.css'
import './AuthHeader.css'

function MainLayout() {
  const { profile, logout } = useAuth()
  const navigate = useNavigate()
  const isAdmin = profile?.role === 'admin'
  const can = (module: Parameters<typeof hasPermission>[1], action: Parameters<typeof hasPermission>[2] = 'view') =>
    hasPermission(profile, module, action)
  const displayName = profile?.imePrezime || profile?.email || 'Korisnik'
  const roleLabel = isAdmin ? 'Administrator' : 'Kalkulant'
  const avatar = displayName
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0])
    .join('')
    .toUpperCase() || 'K'

  async function handleLogout() {
    try {
      await logout()
      navigate('/login', { replace: true })
    } catch (error) {
      console.error('Greška pri odjavi:', error)
      window.alert('Odjava nije uspjela. Pokušajte ponovo.')
    }
  }

  const hasDatabaseLinks = ['customers', 'materials', 'machines', 'treatments'].some(
    (module) => can(module as 'customers' | 'materials' | 'machines' | 'treatments', 'view'),
  )

  return (
    <div className="app-layout">
      <aside className="sidebar">
        <div className="sidebar-logo">
          <div className="logo-box">CNC</div>
          <div>
            <div className="logo-title">CNC Quoting</div>
            <div className="logo-subtitle">System</div>
          </div>
        </div>

        <nav className="sidebar-nav">
          <div className="nav-section-title">GLAVNO</div>

          {can('dashboard', 'view') && (
            <NavLink to="/" end className={({ isActive }) => `nav-item ${isActive ? 'active' : ''}`}>
              <span className="nav-icon">⌂</span><span>Dashboard</span>
            </NavLink>
          )}

          {can('quotes', 'create') && (
            <NavLink
              to="/new-quote"
              onClick={(event) => {
                // "Nova ponuda" uvijek znači potpuno novu, praznu kalkulaciju,
                // čak i kada je korisnik već na ovoj ruti ili uređuje spremljenu ponudu.
                const isModifiedClick =
                  event.button !== 0 || event.metaKey || event.altKey || event.ctrlKey || event.shiftKey
                if (!isModifiedClick) {
                  event.preventDefault()
                  navigate(`/new-quote?new=${typeof crypto !== "undefined" && crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`}`, { state: null })
                }
              }}
              className={({ isActive }) => `nav-item ${isActive ? 'active' : ''}`}
            >
              <span className="nav-icon">＋</span><span>Nova ponuda</span>
            </NavLink>
          )}

          {can('quotes', 'view') && (
            <NavLink to="/quotes" className={({ isActive }) => `nav-item ${isActive ? 'active' : ''}`}>
              <span className="nav-icon">▤</span><span>Pretraži ponude</span>
            </NavLink>
          )}

          {can('positions', 'view') && (
            <NavLink to="/positions" className={({ isActive }) => `nav-item ${isActive ? 'active' : ''}`}>
              <span className="nav-icon">⌕</span><span>Pretraži poziciju</span>
            </NavLink>
          )}

          {can('history', 'view') && (
            <NavLink to="/position-history" className={({ isActive }) => `nav-item ${isActive ? 'active' : ''}`}>
              <span className="nav-icon">↺</span><span>Historija kalkulacija</span>
            </NavLink>
          )}

          {can('analysis', 'view') && (
            <NavLink to="/analysis" className={({ isActive }) => `nav-item ${isActive ? 'active' : ''}`}>
              <span className="nav-icon">▥</span><span>Analiza</span>
            </NavLink>
          )}

          {(hasDatabaseLinks || isAdmin) && (
            <>
              <div className="nav-divider" />
              <div className="nav-section-title">BAZA PODATAKA</div>

              {can('customers', 'view') && (
                <NavLink to="/customers" className={({ isActive }) => `nav-item ${isActive ? 'active' : ''}`}>
                  <span className="nav-icon">♙</span><span>Kupci</span>
                </NavLink>
              )}

              {can('materials', 'view') && (
                <NavLink to="/materials" className={({ isActive }) => `nav-item ${isActive ? 'active' : ''}`}>
                  <span className="nav-icon">◆</span><span>Materijali</span>
                </NavLink>
              )}

              {can('machines', 'view') && (
                <NavLink to="/machines" className={({ isActive }) => `nav-item ${isActive ? 'active' : ''}`}>
                  <span className="nav-icon">⚙</span><span>Mašine i satnice</span>
                </NavLink>
              )}

              {can('treatments', 'view') && (
                <NavLink to="/treatments" className={({ isActive }) => `nav-item ${isActive ? 'active' : ''}`}>
                  <span className="nav-icon">◈</span><span>Obrade</span>
                </NavLink>
              )}
            </>
          )}

          {isAdmin && (
            <>
              <div className="nav-divider" />
              <div className="nav-section-title">POSTAVKE</div>
              <NavLink to="/users" className={({ isActive }) => `nav-item ${isActive ? 'active' : ''}`}>
                <span className="nav-icon">♙</span><span>Korisnici i privilegije</span>
              </NavLink>
              <NavLink to="/activity-log" className={({ isActive }) => `nav-item ${isActive ? 'active' : ''}`}>
                <span className="nav-icon">☷</span><span>Evidencija aktivnosti</span>
              </NavLink>
              <NavLink to="/settings" className={({ isActive }) => `nav-item ${isActive ? 'active' : ''}`}>
                <span className="nav-icon">⚙</span><span>Postavke</span>
              </NavLink>
            </>
          )}
        </nav>

        <div className="sidebar-footer">
          <div className="footer-status">
            <span className="status-dot" /><span>Sistem aktivan</span>
          </div>
          <div className="version">CNC Quoting System v1.0</div>
        </div>
      </aside>

      <div className="main-area">
        <header className="top-header">
          <div className="breadcrumb">CNC Quoting System</div>
          <div className="header-user">
            <div className="header-user-info">
              <strong>{displayName}</strong>
              <span>{roleLabel}</span>
            </div>
            <div className="header-avatar" aria-label={`Korisnik ${displayName}`}>
              {avatar}
            </div>
            <button type="button" className="header-logout" onClick={handleLogout}>
              Odjava
            </button>
          </div>
        </header>

        <main className="page-content"><Outlet /></main>
      </div>
    </div>
  )
}

export default MainLayout
