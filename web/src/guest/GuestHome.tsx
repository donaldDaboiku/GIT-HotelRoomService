import { Link, useParams } from 'react-router-dom'
import { Hours } from '../components/ui'
import { t } from '../i18n/en'
import { useGuest } from './GuestLayout'

export function GuestHome() {
  const { hotelCode = '', roomCode = '' } = useParams()
  const { place } = useGuest()
  const base = `/h/${hotelCode}/r/${roomCode}`
  const tiles = [
    { to: `${base}/menu`, title: t('food'), text: 'Order from the restaurant menu.' },
    { to: `${base}/services?kind=housekeeping`, title: t('housekeeping'), text: 'Towels, cleaning, linen, and laundry.' },
    { to: `${base}/services?kind=maintenance`, title: t('maintenance'), text: 'Tell us what needs repair.' },
    { to: `${base}/services?kind=reception`, title: t('contactReception'), text: 'Send a note to the front desk.' },
  ]

  return (
    <div className="stack">
      <section className="hero">
        <p className="eyebrow">{t('welcomeEyebrow')}</p>
        <h1>{place.hotel.welcome_message || `Welcome to ${place.hotel.name}`}</h1>
        <p>{place.hotel.guest_info}</p>
        <span className="room-chip">Room {place.room.number}{place.room.room_type ? ` · ${place.room.room_type}` : ''}</span>
      </section>
      <p className={place.hotel.service_open ? 'pill pill-ok' : 'pill pill-warn'}>
        {place.hotel.service_open ? t('open') : t('closed')}
      </p>
      <div className="grid cards cards-2">
        {tiles.map((tile) => (
          <Link key={tile.to} className="tile" to={tile.to}>
            <strong>{tile.title}</strong>
            <span className="muted">{tile.text}</span>
          </Link>
        ))}
      </div>
      <Link className="btn btn-navy" to={`${base}/requests`}>{t('viewRequests')}</Link>
      <section className="card">
        <h2>{t('hours')}</h2>
        <p className="muted">{t('hoursNote')}</p>
        <Hours hours={place.hotel.service_hours} />
        {place.hotel.contact_phone ? <p>Reception {place.hotel.contact_phone}</p> : null}
      </section>
    </div>
  )
}
