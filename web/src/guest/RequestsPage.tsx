import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { api } from '../api/client'
import { Notice, StatusPill, paymentLabel } from '../components/ui'
import { formatWhen } from '../lib/money'
import { t } from '../i18n/en'
import { useGuest } from './GuestLayout'

type GuestRequest = {
  reference: string
  type: string
  status: string
  status_label: string
  room_number: string
  guest_note: string | null
  items: { name: string; quantity: number; line_total_display: string; notes: string | null; is_unavailable: boolean }[]
  totals: { total_display: string; subtotal_display: string; service_charge_display: string; tax_display: string; service_charge_label: string; tax_label: string }
  payment_method: string | null
  payment_status: string
  submitted_at: string
  completed_at: string | null
  history: { to: string; at: string; message: string | null }[]
  messages: { body: string; at: string }[]
  feedback: { can_submit: boolean; existing: { rating: number; comment: string | null; category: string | null } | null }
  can_cancel: boolean
}

export function RequestsPage() {
  const { hotelCode = '', roomCode = '' } = useParams()
  const { token, place } = useGuest()
  const [rows, setRows] = useState<GuestRequest[]>([])
  const [error, setError] = useState<unknown>(null)

  useEffect(() => {
    api<{ data: GuestRequest[] }>('/guest/requests', { token, guest: true }).then((body) => setRows(body.data)).catch(setError)
  }, [token])

  return (
    <div className="stack">
      <Link to={`/h/${hotelCode}/r/${roomCode}`}>{t('back')}</Link>
      <h1>{t('viewRequests')}</h1>
      <Notice error={error} />
      {rows.length === 0 ? <p className="notice">{t('empty')}</p> : null}
      {rows.map((row) => (
        <Link key={row.reference} className="card" to={`/h/${hotelCode}/r/${roomCode}/requests/${row.reference}`}>
          <strong>{row.reference}</strong>
          <p>{row.type} · Room {row.room_number}</p>
          <StatusPill status={row.status} label={row.status_label} />
          <p className="muted">{formatWhen(row.submitted_at, place.hotel.timezone)}</p>
        </Link>
      ))}
    </div>
  )
}

export function RequestDetailPage() {
  const { hotelCode = '', roomCode = '', reference = '' } = useParams()
  const { token, place } = useGuest()
  const [row, setRow] = useState<GuestRequest | null>(null)
  const [error, setError] = useState<unknown>(null)
  const [rating, setRating] = useState(5)
  const [comment, setComment] = useState('')
  const base = `/h/${hotelCode}/r/${roomCode}`

  function load() {
    api<{ data: GuestRequest }>(`/guest/requests/${reference}`, { token, guest: true })
      .then((body) => setRow(body.data))
      .catch(setError)
  }

  useEffect(() => { load() }, [reference, token])

  async function cancel() {
    await api(`/guest/requests/${reference}/cancel`, { method: 'POST', token, guest: true }).then(load).catch(setError)
  }

  async function feedback() {
    await api(`/guest/requests/${reference}/feedback`, {
      method: 'POST',
      token,
      guest: true,
      body: JSON.stringify({ rating, comment, category: row?.type }),
    }).then(load).catch(setError)
  }

  if (!row) return error ? <Notice error={error} /> : <p>Loading…</p>

  return (
    <div className="stack">
      <Link to={`${base}/requests`}>{t('back')}</Link>
      <p className="eyebrow">{t('reference')}</p>
      <h1>{row.reference}</h1>
      <StatusPill status={row.status} label={row.status_label} />
      <p>Submitted {formatWhen(row.submitted_at, place.hotel.timezone)}</p>
      {row.completed_at ? <p>Completed {formatWhen(row.completed_at, place.hotel.timezone)}</p> : null}
      {row.guest_note ? <p>{row.guest_note}</p> : null}
      <Notice error={error} />
      <section className="card">
        {row.items.map((item) => (
          <p key={item.name}>{item.quantity} × {item.name} · {item.line_total_display}{item.is_unavailable ? ' · unavailable' : ''}{item.notes ? ` · ${item.notes}` : ''}</p>
        ))}
        <p>Subtotal {row.totals.subtotal_display}</p>
        <p>Service charge {row.totals.service_charge_label} {row.totals.service_charge_display}</p>
        <p>Tax {row.totals.tax_label} {row.totals.tax_display}</p>
        <p><strong>Total {row.totals.total_display}</strong></p>
        <p>{paymentLabel(row.payment_method)} · {row.payment_status}</p>
      </section>
      <section className="card">
        <h2>Status history</h2>
        {row.history.map((entry, index) => (
          <p key={`${entry.at}-${index}`}>{entry.to} · {formatWhen(entry.at, place.hotel.timezone)}{entry.message ? ` · ${entry.message}` : ''}</p>
        ))}
      </section>
      {row.messages.length > 0 ? (
        <section className="card">
          <h2>Messages</h2>
          {row.messages.map((message) => <p key={message.at}>{message.body}</p>)}
        </section>
      ) : null}
      {row.can_cancel ? <button className="btn btn-ghost" type="button" onClick={() => void cancel()}>Cancel request</button> : null}
      {row.feedback.existing ? <p>Your rating: {row.feedback.existing.rating} / 5</p> : null}
      {row.feedback.can_submit ? (
        <form className="card" onSubmit={(event) => { event.preventDefault(); void feedback() }}>
          <h2>How was this request?</h2>
          <label className="field"><span>Rating</span>
            <select value={rating} onChange={(event) => setRating(Number(event.target.value))}>
              {[5, 4, 3, 2, 1].map((value) => <option key={value} value={value}>{value}</option>)}
            </select>
          </label>
          <label className="field"><span>Comment</span><textarea value={comment} onChange={(event) => setComment(event.target.value)} /></label>
          <button className="btn btn-primary" type="submit">Send feedback</button>
        </form>
      ) : null}
    </div>
  )
}
