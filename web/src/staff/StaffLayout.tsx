import { useEffect, useState } from 'react'
import { Link, NavLink, Outlet, useNavigate } from 'react-router-dom'
import { api, setStaffToken, setStaffUser, staffToken, staffUser, type StaffUser } from '../api/client'

export function StaffLayout() {
  const navigate = useNavigate()
  const user = staffUser()
  const [notes, setNotes] = useState(0)

  useEffect(() => {
    if (!staffToken() || !user) {
      navigate('/staff/login')
      return
    }
    if (user.role === 'platform_admin') {
      navigate('/platform')
      return
    }
    api<{ data: { read_at: string | null }[] }>('/staff/notifications', { token: staffToken() })
      .then((body) => setNotes(body.data.filter((row) => !row.read_at).length))
      .catch(() => setNotes(0))
  }, [navigate, user?.id, user?.role])

  if (!user || user.role === 'platform_admin') return null

  async function logout() {
    await api('/staff/logout', { method: 'POST', token: staffToken() }).catch(() => undefined)
    setStaffToken(null)
    setStaffUser(null)
    navigate('/staff/login')
  }

  return (
    <div className="app">
      <a className="skip" href="#main">Skip to content</a>
      <header className="wrap">
        <div className="topbar">
          <Link className="brand" to="/staff">
            <span className="mark">G</span>
            <span>
              <strong>{user.hotel?.name ?? 'GIT Hotel'}</strong>
              <span>{user.role_label} · {user.name}</span>
            </span>
          </Link>
          <button className="btn btn-ghost" type="button" onClick={() => void logout()}>Sign out</button>
        </div>
        <nav className="desk-nav" aria-label="Staff">
          <NavLink to="/staff" end>Dashboard</NavLink>
          <NavLink to="/staff/requests">Requests {notes > 0 ? `(${notes})` : ''}</NavLink>
          {user.role === 'hotel_admin' ? <NavLink to="/admin">Hotel setup</NavLink> : null}
        </nav>
      </header>
      <main id="main" className="wrap stack">
        <Outlet context={user satisfies StaffUser} />
      </main>
    </div>
  )
}
