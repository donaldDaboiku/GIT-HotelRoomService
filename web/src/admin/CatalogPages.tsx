import { useEffect, useState, type FormEvent } from 'react'
import { api, staffToken } from '../api/client'
import { Notice } from '../components/ui'
import { formatMinor } from '../lib/money'

type Named = { id: number; name: string }
type ServiceRow = {
  id: number
  name: string
  description: string | null
  is_available: boolean
  fee_minor: number
  category: Named
  department: Named
}
type MenuRow = {
  id: number
  name: string
  description: string | null
  price_minor: number
  is_available: boolean
  is_sold_out: boolean
  dietary_info: string | null
  category: Named
}

export function ServicesPage() {
  const [rows, setRows] = useState<ServiceRow[]>([])
  const [categories, setCategories] = useState<{ id: number; name: string; kind: string }[]>([])
  const [departments, setDepartments] = useState<Named[]>([])
  const [error, setError] = useState<unknown>(null)

  function load() {
    Promise.all([
      api<{ data: ServiceRow[] }>('/admin/services', { token: staffToken() }),
      api<{ data: { id: number; name: string; kind: string }[] }>('/admin/service-categories', { token: staffToken() }),
      api<{ data: Named[] }>('/staff/departments', { token: staffToken() }),
    ]).then(([services, cats, deps]) => {
      setRows(services.data)
      setCategories(cats.data)
      setDepartments(deps.data)
    }).catch(setError)
  }

  useEffect(() => { load() }, [])

  async function createCategory(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = new FormData(event.currentTarget)
    try {
      await api('/admin/service-categories', {
        method: 'POST',
        token: staffToken(),
        body: JSON.stringify({ name: form.get('name'), kind: form.get('kind'), sort_order: 0 }),
      })
      event.currentTarget.reset()
      load()
    } catch (reason) {
      setError(reason)
    }
  }

  async function createService(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = new FormData(event.currentTarget)
    const naira = Number(form.get('fee_naira') || 0)
    try {
      await api('/admin/services', {
        method: 'POST',
        token: staffToken(),
        body: JSON.stringify({
          service_category_id: Number(form.get('service_category_id')),
          department_id: Number(form.get('department_id')),
          name: form.get('name'),
          description: form.get('description'),
          is_available: true,
          target_minutes: Number(form.get('target_minutes') || 30),
          fee_minor: Math.round(naira * 100),
          sort_order: 0,
        }),
      })
      event.currentTarget.reset()
      load()
    } catch (reason) {
      setError(reason)
    }
  }

  async function toggle(row: ServiceRow) {
    await api(`/admin/services/${row.id}`, {
      method: 'PATCH',
      token: staffToken(),
      body: JSON.stringify({
        service_category_id: row.category.id,
        department_id: row.department.id,
        name: row.name,
        description: row.description,
        is_available: !row.is_available,
        fee_minor: row.fee_minor,
        sort_order: 0,
      }),
    }).then(load).catch(setError)
  }

  return (
    <div className="stack">
      <Notice error={error} />
      <form className="card" onSubmit={(event) => void createCategory(event)}>
        <h2>Service category</h2>
        <label className="field"><span>Name</span><input name="name" required /></label>
        <label className="field"><span>Kind</span>
          <select name="kind" defaultValue="housekeeping">
            <option value="housekeeping">Housekeeping</option>
            <option value="maintenance">Maintenance</option>
            <option value="reception">Reception</option>
            <option value="other">Other</option>
          </select>
        </label>
        <button className="btn" type="submit">Add category</button>
      </form>
      <form className="card" onSubmit={(event) => void createService(event)}>
        <h2>Service</h2>
        <label className="field"><span>Name</span><input name="name" required /></label>
        <label className="field"><span>Description</span><input name="description" /></label>
        <label className="field"><span>Category</span>
          <select name="service_category_id" required>{categories.map((row) => <option key={row.id} value={row.id}>{row.name}</option>)}</select>
        </label>
        <label className="field"><span>Department</span>
          <select name="department_id" required>{departments.map((row) => <option key={row.id} value={row.id}>{row.name}</option>)}</select>
        </label>
        <label className="field"><span>Fee in naira</span><input name="fee_naira" type="number" min={0} step="0.01" defaultValue={0} /></label>
        <label className="field"><span>Target minutes</span><input name="target_minutes" type="number" min={1} defaultValue={30} /></label>
        <button className="btn btn-primary" type="submit">Add service</button>
      </form>
      {rows.map((row) => (
        <article key={row.id} className="card">
          <strong>{row.name}</strong>
          <p className="muted">{row.category.name} · {row.department.name} · {formatMinor(row.fee_minor)} · {row.is_available ? 'Available' : 'Hidden'}</p>
          <button className="btn" type="button" onClick={() => void toggle(row)}>{row.is_available ? 'Hide' : 'Show'}</button>
        </article>
      ))}
    </div>
  )
}

export function MenuAdminPage() {
  const [items, setItems] = useState<MenuRow[]>([])
  const [categories, setCategories] = useState<Named[]>([])
  const [error, setError] = useState<unknown>(null)

  function load() {
    Promise.all([
      api<{ data: MenuRow[] }>('/admin/menu-items', { token: staffToken() }),
      api<{ data: Named[] }>('/admin/menu-categories', { token: staffToken() }),
    ]).then(([menu, cats]) => {
      setItems(menu.data)
      setCategories(cats.data)
    }).catch(setError)
  }

  useEffect(() => { load() }, [])

  async function createCategory(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = new FormData(event.currentTarget)
    try {
      await api('/admin/menu-categories', {
        method: 'POST',
        token: staffToken(),
        body: JSON.stringify({ name: form.get('name'), is_available: true, sort_order: 0 }),
      })
      event.currentTarget.reset()
      load()
    } catch (reason) {
      setError(reason)
    }
  }

  async function createItem(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = new FormData(event.currentTarget)
    try {
      await api('/admin/menu-items', {
        method: 'POST',
        token: staffToken(),
        body: JSON.stringify({
          menu_category_id: Number(form.get('menu_category_id')),
          name: form.get('name'),
          description: form.get('description'),
          price_minor: Math.round(Number(form.get('price_naira')) * 100),
          is_available: true,
          is_sold_out: false,
          dietary_info: form.get('dietary_info'),
          sort_order: 0,
        }),
      })
      event.currentTarget.reset()
      load()
    } catch (reason) {
      setError(reason)
    }
  }

  async function soldOut(row: MenuRow) {
    await api(`/admin/menu-items/${row.id}`, {
      method: 'PATCH',
      token: staffToken(),
      body: JSON.stringify({
        menu_category_id: row.category.id,
        name: row.name,
        description: row.description,
        price_minor: row.price_minor,
        is_available: row.is_available,
        is_sold_out: !row.is_sold_out,
        dietary_info: row.dietary_info,
        sort_order: 0,
      }),
    }).then(load).catch(setError)
  }

  return (
    <div className="stack">
      <Notice error={error} />
      <form className="card" onSubmit={(event) => void createCategory(event)}>
        <h2>Menu category</h2>
        <label className="field"><span>Name</span><input name="name" required /></label>
        <button className="btn" type="submit">Add category</button>
      </form>
      <form className="card" onSubmit={(event) => void createItem(event)}>
        <h2>Menu item</h2>
        <label className="field"><span>Name</span><input name="name" required /></label>
        <label className="field"><span>Description</span><input name="description" /></label>
        <label className="field"><span>Category</span>
          <select name="menu_category_id" required>{categories.map((row) => <option key={row.id} value={row.id}>{row.name}</option>)}</select>
        </label>
        <label className="field"><span>Price in naira</span><input name="price_naira" type="number" min={0} step="0.01" required /></label>
        <label className="field"><span>Dietary note</span><input name="dietary_info" /></label>
        <button className="btn btn-primary" type="submit">Add item</button>
      </form>
      {items.map((item) => (
        <article key={item.id} className="card">
          <strong>{item.name}</strong>
          <p>{item.category.name} · {formatMinor(item.price_minor)}{item.dietary_info ? ` · ${item.dietary_info}` : ''}</p>
          <button className="btn" type="button" onClick={() => void soldOut(item)}>{item.is_sold_out ? 'Mark available' : 'Mark sold out'}</button>
        </article>
      ))}
    </div>
  )
}
