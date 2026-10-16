import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { api, staffToken, staffUser } from '../api/client'
import { Notice, StatusPill, paymentLabel } from '../components/ui'
import { formatMinor, formatWhen } from '../lib/money'

async function downloadAttachment(id: number, name: string) {
  const response = await fetch(`/api/v1/staff/attachments/${id}`, {
    headers: { Authorization: `Bearer ${staffToken() ?? ''}`, Accept: 'image/jpeg,image/png,image/webp' },
  })
  if (!response.ok) return
  const blob = await response.blob()
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = name
  link.click()
  URL.revokeObjectURL(url)
}

type Detail = {
  id: number
  reference: string
  type: string
  status: string
  status_label: string
  priority: string
  room: { number: string; floor: string | null; room_type: string | null }
  department: { id: number; name: string } | null
  guest_note: string | null
  items: { id: number; name: string; quantity: number; line_total_minor: number; notes: string | null; is_unavailable: boolean }[]
  totals: { total_display: string; subtotal_display: string; service_charge_display: string; tax_display: string; currency: string }
  payment_method: string | null
  payment_status: string
  submitted_at: string
  completed_at: string | null
  due_at: string | null
  overdue: boolean
  allowed_transitions: string[]
  can_assign: boolean
  can_set_priority: boolean
  can_record_payment: boolean
  history: { from: string | null; to: string; at: string; note: string | null; by: string | null; guest_visible: boolean }[]
  notes: { id: number; body: string; guest_visible: boolean; at: string; by: string | null }[]
  assignments: { department: string | null; assignee: string | null; by: string | null; at: string }[]
  attachments: { id: number; name: string }[]
  feedback: { rating: number; comment: string | null } | null
}

export function RequestDetailPage() {
  const { id = '' } = useParams()
  const [row, setRow] = useState<Detail | null>(null)
  const [error, setError] = useState<unknown>(null)
  const [note, setNote] = useState('')
  const [guestVisible, setGuestVisible] = useState(false)
  const [departments, setDepartments] = useState<{ id: number; name: string }[]>([])
  const [assignees, setAssignees] = useState<{ id: number; name: string }[]>([])
  const [departmentId, setDepartmentId] = useState('')
  const [userId, setUserId] = useState('')
  const [priority, setPriority] = useState('normal')
  const [payment, setPayment] = useState('unpaid')

  function load() {
    api<{ data: Detail }>(`/staff/requests/${id}`, { token: staffToken() })
      .then((body) => {
        setRow(body.data)
        setPriority(body.data.priority)
        setPayment(body.data.payment_status)
        if (body.data.department) setDepartmentId(String(body.data.department.id))
      })
      .catch(setError)
  }

  useEffect(() => { load() }, [id])

  useEffect(() => {
    if (!row?.can_assign) return
    api<{ data: { id: number; name: string }[] }>('/staff/departments', { token: staffToken() })
      .then((body) => setDepartments(body.data))
      .catch(setError)
  }, [row?.can_assign])

  useEffect(() => {
    if (!departmentId) return
    api<{ data: { id: number; name: string }[] }>(`/staff/assignees?department_id=${departmentId}`, { token: staffToken() })
      .then((body) => setAssignees(body.data))
      .catch(() => setAssignees([]))
  }, [departmentId])

  async function act(path: string, body?: unknown) {
    setError(null)
    try {
      const response = await api<{ data: Detail }>(path, {
        method: path.includes('/status') || path.includes('/priority') ? 'PATCH' : 'POST',
        token: staffToken(),
        body: body === undefined ? undefined : JSON.stringify(body),
      })
      setRow(response.data)
      setNote('')
    } catch (reason) {
      setError(reason)
    }
  }

  if (!row) return error ? <Notice error={error} /> : <p>Loading…</p>

  return (
    <div className="stack">
      <Link to="/staff/requests">Back to requests</Link>
      <p className="eyebrow">Room {row.room.number}</p>
      <h1>{row.reference}</h1>
      <div className="actions">
        <StatusPill status={row.status} label={row.status_label} />
        {row.overdue ? <span className="pill pill-bad">Overdue</span> : null}
        <span className="pill">{row.priority}</span>
      </div>
      <p>Submitted {formatWhen(row.submitted_at)}{row.due_at ? ` · target ${formatWhen(row.due_at)}` : ''}</p>
      {row.guest_note ? <p>{row.guest_note}</p> : null}
      <Notice error={error} />
      <section className="card">
        {row.items.map((item) => (
          <div key={item.id} className="row">
            <span className="grow">{item.quantity} × {item.name}{item.notes ? ` · ${item.notes}` : ''}{item.is_unavailable ? ' · unavailable' : ''}</span>
            <span>{formatMinor(item.line_total_minor, row.totals.currency)}</span>
            {row.type === 'order' && !item.is_unavailable ? (
              <button className="btn" type="button" onClick={() => void act(`/staff/requests/${row.id}/items/${item.id}/unavailable`, { note: 'Unavailable' })}>Unavailable</button>
            ) : null}
          </div>
        ))}
        <p>Subtotal {row.totals.subtotal_display}</p>
        <p>Service charge {row.totals.service_charge_display}</p>
        <p>Tax {row.totals.tax_display}</p>
        <p><strong>Order value {row.totals.total_display}</strong></p>
        <p>{paymentLabel(row.payment_method)} · recorded as {row.payment_status}. Unpaid is not collected revenue.</p>
      </section>
      <section className="card">
        <h2>Update</h2>
        <div className="actions">
          {row.allowed_transitions.map((status) => (
            <button key={status} className="btn btn-primary" type="button" onClick={() => void act(`/staff/requests/${row.id}/status`, { status, note, guest_visible: guestVisible })}>
              {status.replaceAll('_', ' ')}
            </button>
          ))}
        </div>
        {(row.allowed_transitions.includes('rejected') || row.allowed_transitions.includes('cancelled')) ? (
          <p className="muted">Reject and cancel need a note of at least 3 characters.</p>
        ) : null}
        <label className="field"><span>Note for this change</span><textarea value={note} onChange={(event) => setNote(event.target.value)} /></label>
        <label className="row"><input type="checkbox" checked={guestVisible} onChange={(event) => setGuestVisible(event.target.checked)} /> Guest can see this note</label>
        <button className="btn" type="button" onClick={() => void act(`/staff/requests/${row.id}/notes`, { body: note, guest_visible: guestVisible })}>Save note</button>
      </section>
      {row.can_assign ? (
        <section className="card">
          <h2>Assign</h2>
          <label className="field"><span>Department</span>
            <select value={departmentId} onChange={(event) => setDepartmentId(event.target.value)}>
              <option value="">Choose</option>
              {departments.map((department) => <option key={department.id} value={department.id}>{department.name}</option>)}
            </select>
          </label>
          <label className="field"><span>Staff member</span>
            <select value={userId} onChange={(event) => setUserId(event.target.value)}>
              <option value="">Department queue</option>
              {assignees.map((person) => <option key={person.id} value={person.id}>{person.name}</option>)}
            </select>
          </label>
          <button className="btn btn-navy" type="button" disabled={!departmentId} onClick={() => void act(`/staff/requests/${row.id}/assign`, { department_id: Number(departmentId), user_id: userId ? Number(userId) : null })}>Assign</button>
          {staffUser()?.role === 'receptionist' || staffUser()?.role === 'hotel_admin' ? (
            <button className="btn" type="button" onClick={() => void act(`/staff/requests/${row.id}/escalate`, { note: note || 'Escalated from the desk.' })}>Escalate</button>
          ) : null}
        </section>
      ) : null}
      {row.can_set_priority ? (
        <section className="card">
          <h2>Priority</h2>
          <div className="row">
            <select value={priority} onChange={(event) => setPriority(event.target.value)}>
              <option value="normal">Normal</option>
              <option value="high">High</option>
              <option value="urgent">Urgent</option>
            </select>
            <button className="btn" type="button" onClick={() => void act(`/staff/requests/${row.id}/priority`, { priority })}>Save priority</button>
          </div>
        </section>
      ) : null}
      {row.can_record_payment ? (
        <section className="card">
          <h2>Payment record</h2>
          <div className="row">
            <select value={payment} onChange={(event) => setPayment(event.target.value)}>
              <option value="unpaid">Unpaid</option>
              <option value="paid">Paid</option>
              <option value="refunded">Refunded</option>
            </select>
            <button className="btn" type="button" onClick={() => void act(`/staff/requests/${row.id}/payment`, { payment_status: payment })}>Record</button>
          </div>
        </section>
      ) : null}
      <section className="card">
        <h2>History</h2>
        {row.history.map((entry, index) => (
          <p key={`${entry.at}-${index}`}>{entry.to}{entry.by ? ` · ${entry.by}` : ''} · {formatWhen(entry.at)}{entry.note ? ` · ${entry.note}` : ''}</p>
        ))}
        {row.notes.map((entry) => <p key={entry.id}>{entry.guest_visible ? 'Guest note' : 'Internal'} · {entry.by}: {entry.body}</p>)}
        {row.assignments.map((entry, index) => <p key={index}>{entry.department}{entry.assignee ? ` · ${entry.assignee}` : ''} · {entry.by}</p>)}
        {row.feedback ? <p>Guest rating {row.feedback.rating}/5 {row.feedback.comment}</p> : null}
        {row.attachments.map((file) => (
          <p key={file.id}>
            <button className="btn" type="button" onClick={() => void downloadAttachment(file.id, file.name)}>{file.name}</button>
          </p>
        ))}
      </section>
    </div>
  )
}
