import { createContext, useContext, useEffect, useState } from 'react'
import { Link, Outlet, useParams } from 'react-router-dom'
import { ApiError, api } from '../api/client'
import { t } from '../i18n/en'
import { Notice } from '../components/ui'

export type Place = {
  hotel: {
    name: string
    logo_url: string | null
    welcome_message: string | null
    guest_info: string | null
    contact_phone: string | null
    currency: string
    timezone: string
    service_hours: Record<string, { open?: string; close?: string; closed?: boolean }>
    service_open: boolean
    service_charge_label: string
    tax_label: string
    payment_methods: string[]
  }
  room: { number: string; floor: string | null; room_type: string | null }
}

type GuestState = { token: string; place: Place }

const GuestContext = createContext<GuestState | null>(null)

export function useGuest(): GuestState {
  const value = useContext(GuestContext)
  if (!value) throw new Error('Guest session is missing.')
  return value
}

function storageKey(hotel: string, room: string): string {
  return `git.guest.${hotel}.${room}`
}

export function GuestLayout() {
  const { hotelCode = '', roomCode = '' } = useParams()
  const [state, setState] = useState<GuestState | null>(null)
  const [error, setError] = useState<unknown>(null)

  useEffect(() => {
    let cancelled = false
    const key = storageKey(hotelCode, roomCode)
    async function open() {
      const stored = localStorage.getItem(key)
      if (stored) {
        try {
          const context = await api<{ data: Place }>('/guest/context', { token: stored, guest: true })
          if (!cancelled) setState({ token: stored, place: context.data })
          return
        } catch (reason) {
          if (!(reason instanceof ApiError) || reason.status !== 401) {
            if (!cancelled) setError(reason)
            return
          }
          localStorage.removeItem(key)
        }
      }
      try {
        const created = await api<{ data: Place & { token: string } }>('/guest/sessions', {
          method: 'POST',
          body: JSON.stringify({ hotel_code: hotelCode, room_code: roomCode }),
        })
        localStorage.setItem(key, created.data.token)
        if (!cancelled) setState({ token: created.data.token, place: created.data })
      } catch (reason) {
        if (!cancelled) setError(reason)
      }
    }
    void open()
    return () => { cancelled = true }
  }, [hotelCode, roomCode])

  return (
    <div className="app">
      <a className="skip" href="#main">Skip to content</a>
      <header className="guest-wrap topbar">
        <Link className="brand" to={`/h/${hotelCode}/r/${roomCode}`}>
          {state?.place.hotel.logo_url
            ? <img src={state.place.hotel.logo_url} alt="" />
            : <span className="mark">G</span>}
          <span>
            <strong>{state?.place.hotel.name ?? 'GIT Hotel Room Service'}</strong>
            <span>{state ? `Room ${state.place.room.number}` : t('scanTitle')}</span>
          </span>
        </Link>
      </header>
      <main id="main" className="guest-wrap stack">
        {error ? <Notice error={error} /> : null}
        {!state && !error ? <p>{t('loading')}…</p> : null}
        {state ? <GuestContext.Provider value={state}><Outlet /></GuestContext.Provider> : null}
      </main>
    </div>
  )
}
