import { useEffect, useState, type FormEvent } from 'react'
import { api, staffToken } from '../api/client'
import { Hours, Notice } from '../components/ui'

type DayHours = { open?: string; close?: string; closed?: boolean }
type HotelProfile = {
  name: string
  public_code: string
  logo_url: string | null
  contact_phone: string | null
  contact_email: string | null
  address: string | null
  currency: string
  timezone: string
  welcome_message: string
  guest_info: string | null
  service_charge_bps: number
  tax_bps: number
  allow_cash: boolean
  allow_reception_payment: boolean
  feedback_revisions: boolean
  guest_messages_enabled: boolean
  block_outside_hours: boolean
  service_hours: Record<string, DayHours>
  notification_preferences: { in_app: boolean; email: boolean; sms: boolean; whatsapp: boolean }
  notification_note: string
  online_payment_note: string
  targets: { request_type: string; target_minutes: number }[]
}

const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']

export function HotelPage() {
  const [hotel, setHotel] = useState<HotelProfile | null>(null)
  const [error, setError] = useState<unknown>(null)
  const [saved, setSaved] = useState('')

  useEffect(() => {
    api<{ data: HotelProfile }>('/admin/hotel', { token: staffToken() }).then((body) => setHotel(body.data)).catch(setError)
  }, [])

  async function saveProfile(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!hotel) return
    const form = new FormData(event.currentTarget)
    try {
      const body = await api<{ data: HotelProfile }>('/admin/hotel', {
        method: 'PATCH',
        token: staffToken(),
        body: JSON.stringify({
          name: form.get('name'),
          contact_phone: form.get('contact_phone'),
          contact_email: form.get('contact_email'),
          address: form.get('address'),
          currency: form.get('currency'),
          timezone: form.get('timezone'),
        }),
      })
      setHotel(body.data)
      setSaved('Hotel profile saved.')
    } catch (reason) {
      setError(reason)
    }
  }

  async function saveSettings(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!hotel) return
    const form = new FormData(event.currentTarget)
    const service_hours: Record<string, DayHours> = {}
    for (const day of days) {
      const closed = form.get(`${day}_closed`) === 'on'
      service_hours[day] = closed
        ? { closed: true }
        : { closed: false, open: String(form.get(`${day}_open`) || '07:00'), close: String(form.get(`${day}_close`) || '22:00') }
    }
    try {
      const body = await api<{ data: HotelProfile }>('/admin/settings', {
        method: 'PATCH',
        token: staffToken(),
        body: JSON.stringify({
          welcome_message: form.get('welcome_message'),
          guest_info: form.get('guest_info'),
          service_charge_bps: Number(form.get('service_charge_bps')),
          tax_bps: Number(form.get('tax_bps')),
          allow_cash: form.get('allow_cash') === 'on',
          allow_reception_payment: form.get('allow_reception_payment') === 'on',
          allow_online_payment: false,
          feedback_revisions: form.get('feedback_revisions') === 'on',
          guest_messages_enabled: form.get('guest_messages_enabled') === 'on',
          block_outside_hours: form.get('block_outside_hours') === 'on',
          service_hours,
          notification_preferences: {
            in_app: true,
            email: form.get('email') === 'on',
            sms: form.get('sms') === 'on',
            whatsapp: form.get('whatsapp') === 'on',
          },
          targets: ['service', 'order', 'maintenance'].map((request_type) => ({
            request_type,
            target_minutes: Number(form.get(`target_${request_type}`)),
          })),
        }),
      })
      setHotel(body.data)
      setSaved('Guest settings saved. In-app notices are the only channel that is stored.')
    } catch (reason) {
      setError(reason)
    }
  }

  async function uploadLogo(file: File) {
    const form = new FormData()
    form.set('logo', file)
    try {
      await api('/admin/hotel/logo', { method: 'POST', token: staffToken(), body: form })
      const body = await api<{ data: HotelProfile }>('/admin/hotel', { token: staffToken() })
      setHotel(body.data)
    } catch (reason) {
      setError(reason)
    }
  }

  if (!hotel) return <Notice error={error} />
  const target = (type: string) => hotel.targets.find((row) => row.request_type === type)?.target_minutes ?? 30

  return (
    <div className="stack">
      <p className="muted">Public code {hotel.public_code}. Guests open /h/{hotel.public_code}/r/…</p>
      {saved ? <p className="notice">{saved}</p> : null}
      <Notice error={error} />
      <form className="card" onSubmit={(event) => void saveProfile(event)}>
        <h2>Profile</h2>
        {hotel.logo_url ? <img src={hotel.logo_url} alt="" width={72} height={72} /> : null}
        <label className="field"><span>Logo</span><input type="file" accept="image/jpeg,image/png,image/webp" onChange={(event) => { const file = event.target.files?.[0]; if (file) void uploadLogo(file) }} /></label>
        <label className="field"><span>Name</span><input name="name" defaultValue={hotel.name} required /></label>
        <label className="field"><span>Phone</span><input name="contact_phone" defaultValue={hotel.contact_phone ?? ''} /></label>
        <label className="field"><span>Email</span><input name="contact_email" defaultValue={hotel.contact_email ?? ''} /></label>
        <label className="field"><span>Address</span><input name="address" defaultValue={hotel.address ?? ''} /></label>
        <label className="field"><span>Currency</span><input name="currency" defaultValue={hotel.currency} maxLength={3} required /></label>
        <label className="field"><span>Time zone</span><input name="timezone" defaultValue={hotel.timezone} required /></label>
        <button className="btn btn-primary" type="submit">Save profile</button>
      </form>
      <form className="card" onSubmit={(event) => void saveSettings(event)}>
        <h2>Guest-facing settings</h2>
        <label className="field"><span>Welcome</span><input name="welcome_message" defaultValue={hotel.welcome_message} required /></label>
        <label className="field"><span>Guest information</span><textarea name="guest_info" defaultValue={hotel.guest_info ?? ''} /></label>
        <label className="field"><span>Service charge, basis points (1000 = 10%)</span><input name="service_charge_bps" type="number" min={0} max={10000} defaultValue={hotel.service_charge_bps} required /></label>
        <label className="field"><span>Tax, basis points</span><input name="tax_bps" type="number" min={0} max={10000} defaultValue={hotel.tax_bps} required /></label>
        <label className="row"><input name="allow_cash" type="checkbox" defaultChecked={hotel.allow_cash} /> Cash at delivery</label>
        <label className="row"><input name="allow_reception_payment" type="checkbox" defaultChecked={hotel.allow_reception_payment} /> Pay at reception</label>
        <p className="muted">{hotel.online_payment_note}</p>
        <label className="row"><input name="feedback_revisions" type="checkbox" defaultChecked={hotel.feedback_revisions} /> Allow feedback revisions</label>
        <label className="row"><input name="guest_messages_enabled" type="checkbox" defaultChecked={hotel.guest_messages_enabled} /> Guest-visible staff messages</label>
        <label className="row"><input name="block_outside_hours" type="checkbox" defaultChecked={hotel.block_outside_hours} /> Block requests outside service hours</label>
        <h3>Service hours</h3>
        {days.map((day) => {
          const row = hotel.service_hours[day] ?? { closed: false, open: '07:00', close: '22:00' }
          return (
            <div className="row" key={day}>
              <span className="grow">{day}</span>
              <input name={`${day}_open`} defaultValue={row.open ?? '07:00'} aria-label={`${day} opens`} />
              <input name={`${day}_close`} defaultValue={row.close ?? '22:00'} aria-label={`${day} closes`} />
              <label><input name={`${day}_closed`} type="checkbox" defaultChecked={Boolean(row.closed)} /> Closed</label>
            </div>
          )
        })}
        <h3>Response targets, minutes</h3>
        <label className="field"><span>Housekeeping and services</span><input name="target_service" type="number" min={1} defaultValue={target('service')} required /></label>
        <label className="field"><span>Food orders</span><input name="target_order" type="number" min={1} defaultValue={target('order')} required /></label>
        <label className="field"><span>Maintenance</span><input name="target_maintenance" type="number" min={1} defaultValue={target('maintenance')} required /></label>
        <h3>Notification preferences</h3>
        <p className="muted">{hotel.notification_note}</p>
        <label className="row"><input name="email" type="checkbox" defaultChecked={hotel.notification_preferences.email} /> Email preference</label>
        <label className="row"><input name="sms" type="checkbox" defaultChecked={hotel.notification_preferences.sms} /> SMS preference</label>
        <label className="row"><input name="whatsapp" type="checkbox" defaultChecked={hotel.notification_preferences.whatsapp} /> WhatsApp preference</label>
        <button className="btn btn-primary" type="submit">Save settings</button>
      </form>
      <section className="card">
        <h2>Published hours</h2>
        <Hours hours={hotel.service_hours} />
      </section>
    </div>
  )
}
