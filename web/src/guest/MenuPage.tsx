import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { ApiError, api } from '../api/client'
import { Notice, paymentLabel } from '../components/ui'
import { t } from '../i18n/en'
import { formatMinor } from '../lib/money'
import { orderPayload } from '../lib/orders'
import { useGuest } from './GuestLayout'

type MenuItem = {
  id: number
  name: string
  description: string | null
  price_minor: number
  price_display: string
  image_url: string | null
  is_sold_out: boolean
  dietary_info: string | null
}
type Category = { id: number; name: string; items: MenuItem[] }
type Quote = {
  totals: { subtotal_minor: number; service_charge_minor: number; tax_minor: number; total_minor: number }
  service_charge_label: string
  tax_label: string
  currency: string
}

export function MenuPage() {
  const { hotelCode = '', roomCode = '' } = useParams()
  const { token, place } = useGuest()
  const [menu, setMenu] = useState<Category[]>([])
  const [filter, setFilter] = useState<number | 'all'>('all')
  const [cart, setCart] = useState<Record<number, { quantity: number; notes: string }>>({})
  const [quote, setQuote] = useState<Quote | null>(null)
  const [payment, setPayment] = useState(place.hotel.payment_methods[0] ?? 'cash_delivery')
  const [note, setNote] = useState('')
  const [error, setError] = useState<unknown>(null)
  const [done, setDone] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const base = `/h/${hotelCode}/r/${roomCode}`

  useEffect(() => {
    api<{ data: Category[] }>('/guest/menu', { token, guest: true }).then((body) => setMenu(body.data)).catch(setError)
  }, [token])

  const lines = Object.entries(cart)
    .filter(([, line]) => line.quantity > 0)
    .map(([id, line]) => ({ menu_item_id: Number(id), quantity: line.quantity, notes: line.notes }))

  useEffect(() => {
    if (lines.length === 0) {
      setQuote(null)
      return
    }
    const timer = window.setTimeout(() => {
      api<{ data: Quote }>('/guest/orders/quote', {
        method: 'POST',
        token,
        guest: true,
        body: JSON.stringify(orderPayload(lines, payment, note)),
      }).then((body) => setQuote(body.data)).catch(setError)
    }, 250)
    return () => window.clearTimeout(timer)
  }, [cart, payment, note, token])

  function setQty(id: number, quantity: number) {
    setCart((current) => ({ ...current, [id]: { quantity: Math.max(0, quantity), notes: current[id]?.notes ?? '' } }))
  }

  async function placeOrder() {
    setBusy(true)
    setError(null)
    try {
      const created = await api<{ data: { reference: string } }>('/guest/orders', {
        method: 'POST',
        token,
        guest: true,
        body: JSON.stringify(orderPayload(lines, payment, note)),
      })
      setDone(created.data.reference)
      setCart({})
    } catch (reason) {
      if (reason instanceof ApiError && reason.status === 409 && reason.payload?.reference) setDone(reason.payload.reference)
      else setError(reason)
    } finally {
      setBusy(false)
    }
  }

  const visible = filter === 'all' ? menu : menu.filter((category) => category.id === filter)

  return (
    <div className="stack">
      <Link to={base}>{t('back')}</Link>
      <h1>{t('food')}</h1>
      <p className="muted">This is an order request. It is not a confirmed or paid order until the hotel accepts it and records payment.</p>
      <Notice error={error} />
      {done ? <p className="notice">Order request {done} was received. <Link to={`${base}/requests/${done}`}>Track it</Link></p> : null}
      <div className="filters" role="toolbar" aria-label="Menu categories">
        <button type="button" className="btn" aria-pressed={filter === 'all'} onClick={() => setFilter('all')}>All</button>
        {menu.map((category) => (
          <button key={category.id} type="button" className="btn" aria-pressed={filter === category.id} onClick={() => setFilter(category.id)}>{category.name}</button>
        ))}
      </div>
      {visible.map((category) => (
        <section key={category.id} className="stack">
          <h2>{category.name}</h2>
          {category.items.map((item) => (
            <article key={item.id} className="card menu-item">
              {item.image_url ? <img className="thumb" src={item.image_url} alt="" /> : <div className="thumb" />}
              <div>
                <strong>{item.name}</strong>
                <p>{item.description}</p>
                <p>{item.price_display}{item.dietary_info ? ` · ${item.dietary_info}` : ''}</p>
                {item.is_sold_out ? <span className="pill pill-bad">Sold out</span> : (
                  <div className="qty">
                    <button type="button" aria-label={`Fewer ${item.name}`} onClick={() => setQty(item.id, (cart[item.id]?.quantity ?? 0) - 1)}>-</button>
                    <span>{cart[item.id]?.quantity ?? 0}</span>
                    <button type="button" aria-label={`More ${item.name}`} onClick={() => setQty(item.id, (cart[item.id]?.quantity ?? 0) + 1)}>+</button>
                  </div>
                )}
                {(cart[item.id]?.quantity ?? 0) > 0 ? (
                  <label className="field">
                    <span>Note</span>
                    <input value={cart[item.id]?.notes ?? ''} onChange={(event) => setCart((current) => ({ ...current, [item.id]: { quantity: current[item.id]?.quantity ?? 1, notes: event.target.value } }))} />
                  </label>
                ) : null}
              </div>
            </article>
          ))}
        </section>
      ))}
      <aside className="card">
        <h2>{t('cart')}</h2>
        {quote ? (
          <>
            <p>Subtotal {formatMinor(quote.totals.subtotal_minor, quote.currency)}</p>
            <p>Service charge {quote.service_charge_label} {formatMinor(quote.totals.service_charge_minor, quote.currency)}</p>
            <p>Tax {quote.tax_label} {formatMinor(quote.totals.tax_minor, quote.currency)}</p>
            <p><strong>Total {formatMinor(quote.totals.total_minor, quote.currency)}</strong></p>
          </>
        ) : <p className="muted">Add an item to see the server total.</p>}
        <label className="field">
          <span>Payment</span>
          <select value={payment} onChange={(event) => setPayment(event.target.value)}>
            {place.hotel.payment_methods.map((method) => <option key={method} value={method}>{paymentLabel(method)}</option>)}
          </select>
        </label>
        <label className="field"><span>Order note</span><textarea value={note} onChange={(event) => setNote(event.target.value)} /></label>
        <button className="btn btn-primary" type="button" disabled={busy || lines.length === 0} onClick={() => void placeOrder()}>{t('placeOrder')}</button>
      </aside>
    </div>
  )
}
