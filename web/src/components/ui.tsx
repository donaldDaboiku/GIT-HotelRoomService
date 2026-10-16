import type { ReactNode } from 'react'
import { ApiError } from '../api/client'

export function Notice({ error }: { error: unknown }) {
  if (!error) return null
  const message = error instanceof ApiError
    ? Object.values(error.errors ?? {}).flat()[0] ?? error.message
    : error instanceof Error
      ? error.message
      : 'Something went wrong.'
  return <p className="notice notice-bad" role="alert">{message}</p>
}

export function StatusPill({ status, label }: { status: string; label: string }) {
  const tone = ['completed', 'delivered'].includes(status)
    ? 'pill-ok'
    : ['rejected', 'cancelled'].includes(status)
      ? 'pill-bad'
      : ['submitted', 'urgent'].includes(status)
        ? 'pill-warn'
        : 'pill-navy'
  return <span className={`pill ${tone}`}>{label}</span>
}

export function Empty({ children }: { children: ReactNode }) {
  return <p className="notice">{children}</p>
}

const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as const

export function Hours({ hours }: { hours: Record<string, { open?: string; close?: string; closed?: boolean }> }) {
  return (
    <div className="hours">
      {days.map((day) => {
        const row = hours[day]
        const label = row?.closed ? 'Closed' : `${row?.open ?? '—'} – ${row?.close ?? '—'}`
        return <div key={day}><span>{day}</span><strong>{label}</strong></div>
      })}
    </div>
  )
}

export function paymentLabel(method: string | null | undefined): string {
  if (method === 'cash_delivery') return 'Cash at delivery'
  if (method === 'pay_reception') return 'Pay at reception'
  if (method === 'online') return 'Online'
  return 'No payment'
}
