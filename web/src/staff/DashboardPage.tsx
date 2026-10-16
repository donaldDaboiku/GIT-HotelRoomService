import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api, staffToken } from '../api/client'
import { Notice } from '../components/ui'

type Counts = {
  new_requests: number
  awaiting_assignment: number
  active_tasks: number
  overdue: number
  completed_today: number
  new_orders: number
  open_maintenance: number
}

const cards: { key: keyof Counts; label: string; to: string }[] = [
  { key: 'new_requests', label: 'New requests', to: '/staff/requests?status=submitted' },
  { key: 'awaiting_assignment', label: 'Awaiting assignment', to: '/staff/requests?status=acknowledged' },
  { key: 'active_tasks', label: 'Active tasks', to: '/staff/requests?status=in_progress' },
  { key: 'overdue', label: 'Overdue', to: '/staff/requests?sort=overdue' },
  { key: 'completed_today', label: 'Completed today', to: '/staff/requests?status=completed' },
  { key: 'new_orders', label: 'New food orders', to: '/staff/requests?type=order&status=submitted' },
  { key: 'open_maintenance', label: 'Open maintenance', to: '/staff/requests?type=maintenance' },
]

export function DashboardPage() {
  const [counts, setCounts] = useState<Counts | null>(null)
  const [error, setError] = useState<unknown>(null)

  useEffect(() => {
    api<{ data: Counts }>('/staff/dashboard', { token: staffToken() }).then((body) => setCounts(body.data)).catch(setError)
  }, [])

  return (
    <div className="stack">
      <h1>Today</h1>
      <p className="muted">Overdue means a configured response target has passed. It is not a promised arrival time.</p>
      <Notice error={error} />
      <div className="grid stats">
        {cards.map((card) => (
          <Link key={card.key} className="card stat" to={card.to}>
            <span className="muted">{card.label}</span>
            <strong>{counts ? counts[card.key] : '—'}</strong>
          </Link>
        ))}
      </div>
      <nav className="actions" aria-label="Department queues">
        <Link className="btn" to="/staff/requests?type=service">Reception and services</Link>
        <Link className="btn" to="/staff/requests?type=order">Kitchen</Link>
        <Link className="btn" to="/staff/requests?type=maintenance">Maintenance</Link>
      </nav>
    </div>
  )
}
