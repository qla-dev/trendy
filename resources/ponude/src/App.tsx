import { BrowserRouter, Routes, Route, Navigate, useLocation } from 'react-router-dom'
import { AuthProvider } from './auth/AuthContext'
import RequireAuth from './components/RequireAuth'
import RequireRole from './components/RequireRole'
import RequirePermission from './components/RequirePermission'
import RequireQuoteEditor from './components/RequireQuoteEditor'
import MainLayout from './layouts/MainLayout'
import Dashboard from './pages/Dashboard'
import NewQuote from './pages/NewQuote'
import Quotes from './pages/Quotes'
import Positions from './pages/Positions'
import PositionHistory from './pages/PositionHistory'
import Customers from './pages/Customers'
import Materials from './pages/Materials'
import Machines from './pages/Machines'
import Settings from './pages/Settings'
import Treatments from './pages/Treatments'
import UserManagement from './pages/UserManagement'
import ActivityLog from './pages/ActivityLog'
import Login from './pages/Login'

// Give the quote form a new identity for each explicit new-quote request.
// Changes to other location state (for example, clearing a history prefill)
// must not discard the current form.
function createQuoteDraftToken() {
  if (typeof crypto !== "undefined" && typeof crypto.randomUUID === "function") {
    return crypto.randomUUID()
  }
  return `${Date.now()}-${Math.random().toString(36).slice(2, 10)}`
}

function NewQuoteRoute() {
  const location = useLocation()
  const params = new URLSearchParams(location.search)
  const explicitNewQuoteToken = params.get("new")

  // Svaka nova kalkulacija dobija stabilan ID u URL-u. Taj ID omogućava da
  // se ista radna ponuda pronađe nakon F5 ili ponovnog otvaranja preglednika.
  // Postojeći edit/copy parametri i history state ostaju sačuvani.
  if (!explicitNewQuoteToken) {
    params.set("new", createQuoteDraftToken())
    return (
      <Navigate
        to={{
          pathname: location.pathname,
          search: `?${params.toString()}`,
          hash: location.hash,
        }}
        replace
        state={location.state}
      />
    )
  }

  return (
    <RequireQuoteEditor>
      {/* Stabilan ID zadržava istu radnu ponudu na refresh-u. */}
      <NewQuote key={explicitNewQuoteToken} />
    </RequireQuoteEditor>
  )
}

function App() {
  return (
    <AuthProvider>
      <BrowserRouter basename="/ponude">
        <Routes>
          <Route path="/login" element={<Login />} />

          <Route
            element={
              <RequireAuth>
                <MainLayout />
              </RequireAuth>
            }
          >
            <Route path="/" element={<RequirePermission module="dashboard"><Dashboard /></RequirePermission>} />
            <Route path="/new-quote" element={<NewQuoteRoute />} />
            <Route path="/newquote" element={<NewQuoteRoute />} />
            <Route path="/quotes" element={<RequirePermission module="quotes"><Quotes view="offers" /></RequirePermission>} />
            <Route path="/analysis" element={<RequirePermission module="analysis"><Quotes view="analysis" /></RequirePermission>} />
            <Route path="/positions" element={<RequirePermission module="positions"><Positions /></RequirePermission>} />
            <Route path="/position-history" element={<RequirePermission module="history"><PositionHistory /></RequirePermission>} />

            <Route path="/customers" element={<RequirePermission module="customers"><Customers /></RequirePermission>} />
            <Route path="/materials" element={<RequirePermission module="materials"><Materials /></RequirePermission>} />
            <Route path="/machines" element={<RequirePermission module="machines"><Machines /></RequirePermission>} />
            <Route path="/treatments" element={<RequirePermission module="treatments"><Treatments /></RequirePermission>} />

            <Route path="/settings" element={<RequireRole allowedRoles={['admin']}><Settings /></RequireRole>} />
            <Route path="/users" element={<RequireRole allowedRoles={['admin']}><UserManagement /></RequireRole>} />
            <Route path="/activity-log" element={<RequireRole allowedRoles={['admin']}><ActivityLog /></RequireRole>} />
          </Route>

          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  )
}

export default App
