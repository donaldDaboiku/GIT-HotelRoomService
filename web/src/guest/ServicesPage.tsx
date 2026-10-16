import { useEffect, useState } from 'react'
import { Link, useParams, useSearchParams } from 'react-router-dom'
import { ApiError, api } from '../api/client'
import { Notice } from '../components/ui'
import { t } from '../i18n/en'
import { useGuest } from './GuestLayout'

type ServiceRow = {
  id: number
  name: string
  description: string | null
  kind: string
  category: string
  fee_display: string
  fee_minor: number
  target_minutes: number | null
}

export function ServicesPage() {
  const { hotelCode = '', roomCode = '' } = useParams()
  const [params] = useSearchParams()
  const kind = params.get('kind') ?? 'housekeeping'
  const { token } = useGuest()
  const [rows, setRows] = useState<ServiceRow[]>([])
  const [error, setError] = useState<unknown>(null)
  const [note, setNote] = useState('')
  const [quantity, setQuantity] = useState(1)
  const [photo, setPhoto] = useState<File | null>(null)
  const [busy, setBusy] = useState(false)
  const [done, setDone] = useState<string | null>(null)

  useEffect(() => {
    api<{ data: ServiceRow[] }>('/guest/services', { token, guest: true })
      .then((body) => setRows(body.data.filter((row) => kind === 'other' || row.kind === kind || (kind === 'housekeeping' && row.kind === 'other'))))
      .catch(setError)
  }, [kind, token])

  async function submit(serviceId: number) {
    setBusy(true)
    setError(null)
    const type = kind === 'maintenance' ? 'maintenance' : 'service'
    try {
      const body = photo
        ? (() => {
            const form = new FormData()
            form.set('type', type)
            form.set('service_id', String(serviceId))
            form.set('quantity', String(quantity))
            form.set('guest_note', note)
            form.set('photo', photo)
            return form
          })()
        : JSON.stringify({ type, service_id: serviceId, quantity, guest_note: note })
      const created = await api<{ data: { reference: string } }>('/guest/requests', {
        method: 'POST',
        token,
        guest: true,
        body,
      })
      setDone(created.data.reference)
    } catch (reason) {
      if (reason instanceof ApiError && reason.status === 409 && reason.payload?.reference) {
        setDone(reason.payload.reference)
      } else {
        setError(reason)
      }
    } finally {
      setBusy(false)
    }
  }

  const base = `/h/${hotelCode}/r/${roomCode}`
  return (
    <div className="stack">
      <Link to={base}>{t('back')}</Link>
      <h1>{kind === 'maintenance' ? t('maintenance') : kind === 'reception' ? t('contactReception') : t('housekeeping')}</h1>
      <Notice error={error} />
      {done ? <p className="notice">Request {done} was received. <Link to={`${base}/requests/${done}`}>Track it</Link></p> : null}
      <label className="field"><span>Note for the team</span><textarea value={note} onChange={(event) => setNote(event.target.value)} /></label>
      <label className="field"><span>Quantity</span><input type="number" min={1} max={20} value={quantity} onChange={(event) => setQuantity(Number(event.target.value))} /></label>
      {kind === 'maintenance' ? (
        <label className="field">
          <span>Photo, optional. JPEG, PNG, or WebP up to 5 MB.</span>
          <input type="file" accept="image/jpeg,image/png,image/webp" onChange={(event) => setPhoto(event.target.files?.[0] ?? null)} />
        </label>
      ) : null}
      {rows.length === 0 ? <p className="notice">{t('empty')}</p> : null}
      {rows.map((row) => (
        <article key={row.id} className="card">
          <h2>{row.name}</h2>
          <p>{row.description}</p>
          <p className="muted">{row.category}{row.fee_minor > 0 ? ` · ${row.fee_display}` : ''}{row.target_minutes ? ` · target ${row.target_minutes} min` : ''}</p>
          <button className="btn btn-primary" type="button" disabled={busy} onClick={() => void submit(row.id)}>{t('submitRequest')}</button>
        </article>
      ))}
    </div>
  )
}
