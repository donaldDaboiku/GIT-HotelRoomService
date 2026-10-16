import { NavLink, Outlet } from 'react-router-dom'

export function AdminLayout() {
  return (
    <div className="stack">
      <h1>Hotel setup</h1>
      <nav className="desk-nav" aria-label="Administration">
        <NavLink to="/admin" end>Profile</NavLink>
        <NavLink to="/admin/rooms">Rooms</NavLink>
        <NavLink to="/admin/services">Services</NavLink>
        <NavLink to="/admin/menu">Menu</NavLink>
        <NavLink to="/admin/staff">Staff</NavLink>
        <NavLink to="/admin/reports">Reports</NavLink>
        <NavLink to="/admin/audit">Audit</NavLink>
      </nav>
      <Outlet />
    </div>
  )
}
