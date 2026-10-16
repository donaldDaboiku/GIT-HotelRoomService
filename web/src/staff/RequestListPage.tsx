import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { api, staffToken } from '../api/client'
import { Notice, StatusPill } from '../components/ui'
import { formatMinor, formatWhen } from '../lib/money'

type Row = {
  id: number
  reference: string
  type: string
  status: string
  status_label: string
  priority: string
  room_number: string
  department: string | null
  submitted_at: string
  overdue: boolean
  total_minor: number
  payment_status: string
}

export function RequestListPage() {
  const [params, setParams] = useSearchParams()
  const [rows, setRows] = useState<Row[]>([])
  const [error, setError] = useState<unknown>(null)
  const [search, setSearch] = useState(params.get('search') ?? '')

  useEffect(() => {
    const query = params.toString()
    api<{ data: Row[] }>(`/staff/requests${query ? `?${query}` : ''}`, { token: staffToken() })
      .then((body) => setRows(body.data))
      .catch(setError)
  }, [params])

  function update(key: string, value: string) {
    const next = new URLSearchParams(params)
    if (value) next.set(key, value)
    else next.delete(key)
    setParams(next)
  }

  return (
    <div className="stack">
      <h1>Requests</h1>
      <form className="card" onSubmit={(event) => { event.preventDefault(); update('search', search) }}>
        <div className="row">
          <label className="field grow"><span>Room or reference</span><input value={search} onChange={(event) => setSearch(event.target.value)} /></label>
          <button className="btn btn-navy" type="submit">Search</button>
        </div>
        <div className="grid cards-2">
          <label className="field"><span>Status</span>
            <select value={params.get('status') ?? ''} onChange={(event) => update('status', event.target.value)}>
              <option value="">Any</option>
              {['submitted', 'acknowledged', 'assigned', 'accepted', 'in_progress', 'preparing', 'ready_for_delivery', 'delivered', 'completed', 'cancelled', 'rejected'].map((status) => (
                <option key={status} value={status}>{status.replaceAll('_', ' ')}</option>
              ))}
            </select>
          </label>
          <label className="field"><span>Type</span>
            <select value={params.get('type') ?? ''} onChange={(event) => update('type', event.target.value)}>
              <option value="">Any</option>
              <option value="service">Service</option>
              <option value="order">Food order</option>
              <option value="maintenance">Maintenance</option>
            </select>
          </label>
          <label className="field"><span>Priority</span>
            <select value={params.get('priority') ?? ''} onChange={(event) => update('priority', event.target.value)}>
              <option value="">Any</option>
              <option value="normal">Normal</option>
              <option value="high">High</option>
              <option value="urgent">Urgent</option>
            </select>
          </label>
          <label className="field"><span>Sort</span>
            <select value={params.get('sort') ?? 'newest'} onChange={(event) => update('sort', event.target.value)}>
              <option value="newest">Newest</option>
              <option value="oldest">Oldest</option>
              <option value="priority">Priority</option>
              <option value="overdue">Overdue</option>
            </select>
          </label>
          <label className="field"><span>From</span><input type="date" value={params.get('from') ?? ''} onChange={(event) => update('from', event.target.value)} /></label>
          <label className="field"><span>To</span><input type="date" value={params.get('to') ?? ''} onChange={(event) => update('to', event.target.value)} /></label>
        </div>
      </form>
      <Notice error={error} />
      {rows.length === 0 ? <p className="notice">No requests match these filters.</p> : null}
      <div className="table-wrap card">
        <table>
          <thead><tr><th>Reference</th><th>Room</th><th>Status</th><th>When</th></tr></thead>
          <tbody>
            {rows.map((row) => (
              <tr key={row.id}>
                <td><Link to={`/staff/requests/${row.id}`}>{row.reference}</Link><div className="muted">{row.type}{row.department ? ` · ${row.department}` : ''}</div></td>
                <td>{row.room_number}{row.overdue ? <div className="pill pill-bad">Overdue</div> : null}</td>
                <td><StatusPill status={row.status} label={row.status_label} /><div className="muted">{row.priority}</div></td>
                <td>{formatWhen(row.submitted_at)}<div className="muted">{row.total_minor > 0 ? `${formatMinor(row.total_minor)} · ${row.payment_status}` : row.payment_status}</div></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}
