import { useEffect, useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { api, setStaffToken, setStaffUser, staffToken, staffUser } from '../api/client'
import { Notice } from '../components/ui'

type HotelRow = {
  id: number
  name: string
  public_code: string
  status: string
  plan: string | null
  staff_count: number
  room_count: number
  currency: string
}

type Health = {
  hotels: number
  active_hotels: number
  suspended_hotels: number
  requests_today: number
}

export function PlatformPage() {
  const navigate = useNavigate()
  const [hotels, setHotels] = useState<HotelRow[]>([])
  const [health, setHealth] = useState<Health | null>(null)
  const [error, setError] = useState<unknown>(null)

  useEffect(() => {
    const user = staffUser()
    if (!staffToken() || user?.role !== 'platform_admin') {
      navigate('/staff/login')
      return
    }
    Promise.all([
      api<{ data: HotelRow[] }>('/platform/hotels', { token: staffToken() }),
      api<{ data: Health }>('/platform/health', { token: staffToken() }),
    ]).then(([list, pulse]) => {
      setHotels(list.data)
      setHealth(pulse.data)
    }).catch(setError)
  }, [navigate])

  async function setStatus(id: number, status: string) {
    await api(`/platform/hotels/${id}/status`, {
      method: 'PATCH',
      token: staffToken(),
      body: JSON.stringify({ status }),
    }).then(() => api<{ data: HotelRow[] }>('/platform/hotels', { token: staffToken() }).then((body) => setHotels(body.data))).catch(setError)
  }

  async function create(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = new FormData(event.currentTarget)
    try {
      await api('/platform/hotels', {
        method: 'POST',
        token: staffToken(),
        body: JSON.stringify({
          name: form.get('name'),
          public_code: form.get('public_code'),
          timezone: form.get('timezone') || 'Africa/Lagos',
          currency: form.get('currency') || 'NGN',
          plan: 'standard',
          admin_name: form.get('admin_name'),
          admin_email: form.get('admin_email'),
          admin_password: form.get('admin_password'),
        }),
      })
      event.currentTarget.reset()
      const body = await api<{ data: HotelRow[] }>('/platform/hotels', { token: staffToken() })
      setHotels(body.data)
    } catch (reason) {
      setError(reason)
    }
  }

  function logout() {
    api('/staff/logout', { method: 'POST', token: staffToken() }).catch(() => undefined)
    setStaffToken(null)
    setStaffUser(null)
    navigate('/staff/login')
  }

  return (
    <main className="wrap stack">
      <div className="topbar">
        <div>
          <p className="eyebrow">GIT System</p>
          <h1>Platform</h1>
        </div>
        <button className="btn btn-ghost" type="button" onClick={logout}>Sign out</button>
      </div>
      <Notice error={error} />
      {health ? (
        <p className="card">{health.active_hotels} active hotels · {health.suspended_hotels} suspended · {health.requests_today} requests since local midnight · {health.hotels} registered</p>
      ) : null}
      <form className="card" onSubmit={(event) => void create(event)}>
        <h2>Register a hotel</h2>
        <label className="field"><span>Hotel name</span><input name="name" required /></label>
        <label className="field"><span>Public code</span><input name="public_code" required pattern="[a-z0-9]+(?:-[a-z0-9]+)*" /></label>
        <label className="field"><span>Currency</span><input name="currency" defaultValue="NGN" maxLength={3} /></label>
        <label className="field"><span>Time zone</span><input name="timezone" defaultValue="Africa/Lagos" /></label>
        <label className="field"><span>Administrator name</span><input name="admin_name" required /></label>
        <label className="field"><span>Administrator email</span><input name="admin_email" type="email" required /></label>
        <label className="field"><span>Administrator password</span><input name="admin_password" type="password" minLength={10} required /></label>
        <button className="btn btn-primary" type="submit">Create hotel</button>
      </form>
      {hotels.map((hotel) => (
        <article key={hotel.id} className="card">
          <strong>{hotel.name}</strong>
          <p className="muted">{hotel.public_code} · {hotel.status} · {hotel.plan} · {hotel.room_count} rooms · {hotel.staff_count} staff · {hotel.currency}</p>
          <div className="actions">
            <button className="btn" type="button" onClick={() => void setStatus(hotel.id, 'active')}>Activate</button>
            <button className="btn" type="button" onClick={() => void setStatus(hotel.id, 'suspended')}>Suspend</button>
            <button className="btn btn-ghost" type="button" onClick={() => void setStatus(hotel.id, 'deactivated')}>Deactivate</button>
          </div>
        </article>
      ))}
    </main>
  )
}
