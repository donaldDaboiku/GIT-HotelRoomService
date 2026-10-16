import { useEffect, useState, type FormEvent } from 'react'
import { api, staffToken, type StaffUser } from '../api/client'
import { Notice } from '../components/ui'
import { formatMinor } from '../lib/money'

export function StaffAdminPage() {
  const [people, setPeople] = useState<StaffUser[]>([])
  const [departments, setDepartments] = useState<{ id: number; name: string }[]>([])
  const [error, setError] = useState<unknown>(null)

  function load() {
    Promise.all([
      api<{ data: StaffUser[] }>('/admin/staff', { token: staffToken() }),
      api<{ data: { id: number; name: string }[] }>('/staff/departments', { token: staffToken() }),
    ]).then(([staff, deps]) => {
      setPeople(staff.data)
      setDepartments(deps.data)
    }).catch(setError)
  }

  useEffect(() => { load() }, [])

  async function create(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = new FormData(event.currentTarget)
    const department = form.get('department_id')
    try {
      await api('/admin/staff', {
        method: 'POST',
        token: staffToken(),
        body: JSON.stringify({
          name: form.get('name'),
          email: form.get('email'),
          password: form.get('password'),
          role: form.get('role'),
          department_id: department ? Number(department) : null,
        }),
      })
      event.currentTarget.reset()
      load()
    } catch (reason) {
      setError(reason)
    }
  }

  async function status(id: number, next: string) {
    await api(`/admin/staff/${id}/status`, {
      method: 'POST',
      token: staffToken(),
      body: JSON.stringify({ status: next }),
    }).then(load).catch(setError)
  }

  return (
    <div className="stack">
      <Notice error={error} />
      <form className="card" onSubmit={(event) => void create(event)}>
        <h2>New staff account</h2>
        <label className="field"><span>Name</span><input name="name" required /></label>
        <label className="field"><span>Email</span><input name="email" type="email" required /></label>
        <label className="field"><span>Temporary password</span><input name="password" type="password" minLength={10} required /></label>
        <label className="field"><span>Role</span>
          <select name="role" defaultValue="service_staff">
            <option value="hotel_admin">Hotel administrator</option>
            <option value="receptionist">Receptionist</option>
            <option value="service_staff">Service staff</option>
            <option value="kitchen_staff">Kitchen staff</option>
          </select>
        </label>
        <label className="field"><span>Department</span>
          <select name="department_id">
            <option value="">None</option>
            {departments.map((row) => <option key={row.id} value={row.id}>{row.name}</option>)}
          </select>
        </label>
        <button className="btn btn-primary" type="submit">Create account</button>
      </form>
      {people.map((person) => (
        <article key={person.id} className="card">
          <strong>{person.name}</strong>
          <p className="muted">{person.email} · {person.role_label} · {person.department?.name ?? 'No department'} · {person.status}</p>
          {person.status === 'active'
            ? <button className="btn" type="button" onClick={() => void status(person.id, 'suspended')}>Suspend</button>
            : <button className="btn" type="button" onClick={() => void status(person.id, 'active')}>Activate</button>}
        </article>
      ))}
    </div>
  )
}

type Report = {
  request_volume: { service: number; order: number; maintenance: number; total: number }
  average_acknowledgement_seconds: number | null
  average_completion_seconds: number | null
  overdue_rate: number
  overdue_count: number
  most_requested_services: { name: string; times: number }[]
  food_order_value_minor: number
  food_payments_received_minor: number
  food_refunds_minor: number
  food_unpaid_minor: number
  service_fee_value_minor: number
  service_fees_received_minor: number
  guest_satisfaction: { average_rating: number | null; responses: number }
  currency: string
  note: string
}

function minutes(seconds: number | null): string {
  if (seconds === null) return '—'
  return `${Math.round(seconds / 60)} min`
}

export function ReportsPage() {
  const [report, setReport] = useState<Report | null>(null)
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const [error, setError] = useState<unknown>(null)

  function load(start = from, end = to) {
    const query = new URLSearchParams()
    if (start) query.set('from', start)
    if (end) query.set('to', end)
    const suffix = query.toString() ? `?${query}` : ''
    api<{ data: Report }>(`/admin/reports/requests${suffix}`, { token: staffToken() })
      .then((body) => setReport(body.data))
      .catch(setError)
  }

  useEffect(() => { load() }, [])

  async function csv() {
    const query = new URLSearchParams()
    if (from) query.set('from', from)
    if (to) query.set('to', to)
    const suffix = query.toString() ? `?${query}` : ''
    const response = await fetch(`/api/v1/admin/reports/requests.csv${suffix}`, {
      headers: { Authorization: `Bearer ${staffToken() ?? ''}` },
    })
    const blob = await response.blob()
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = 'guest-requests.csv'
    link.click()
    URL.revokeObjectURL(url)
  }

  return (
    <div className="stack">
      <form className="card row" onSubmit={(event) => { event.preventDefault(); load() }}>
        <label className="field"><span>From</span><input type="date" value={from} onChange={(event) => setFrom(event.target.value)} /></label>
        <label className="field"><span>To</span><input type="date" value={to} onChange={(event) => setTo(event.target.value)} /></label>
        <button className="btn btn-navy" type="submit">Filter</button>
        <button className="btn" type="button" onClick={() => void csv()}>Export CSV</button>
      </form>
      <Notice error={error} />
      {report ? (
        <section className="card">
          <p>{report.note}</p>
          <p>Requests {report.request_volume.total} · services {report.request_volume.service} · orders {report.request_volume.order} · maintenance {report.request_volume.maintenance}</p>
          <p>Average acknowledgement {minutes(report.average_acknowledgement_seconds)} · average completion {minutes(report.average_completion_seconds)}</p>
          <p>Overdue {report.overdue_count} ({Math.round(report.overdue_rate * 100)}%)</p>
          <p>Food order value {formatMinor(report.food_order_value_minor, report.currency)}</p>
          <p>Payments received {formatMinor(report.food_payments_received_minor, report.currency)}</p>
          <p>Refunds {formatMinor(report.food_refunds_minor, report.currency)}</p>
          <p>Unpaid, not revenue {formatMinor(report.food_unpaid_minor, report.currency)}</p>
          <p>Service fees recorded {formatMinor(report.service_fee_value_minor, report.currency)} · received {formatMinor(report.service_fees_received_minor, report.currency)}</p>
          <p>Satisfaction {report.guest_satisfaction.average_rating ?? '—'} from {report.guest_satisfaction.responses} responses</p>
          <h2>Most requested services</h2>
          {report.most_requested_services.map((row) => <p key={row.name}>{row.name} · {row.times}</p>)}
        </section>
      ) : null}
    </div>
  )
}

export function AuditPage() {
  const [rows, setRows] = useState<{ id: number; action: string; by: string | null; created_at: string }[]>([])
  const [error, setError] = useState<unknown>(null)

  useEffect(() => {
    api<{ data: { id: number; action: string; by: string | null; created_at: string }[] }>('/admin/audit-logs', { token: staffToken() })
      .then((body) => setRows(body.data))
      .catch(setError)
  }, [])

  return (
    <div className="stack">
      <Notice error={error} />
      {rows.map((row) => <p key={row.id} className="card">{row.action} · {row.by ?? 'System'} · {row.created_at}</p>)}
    </div>
  )
}
