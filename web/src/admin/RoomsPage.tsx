import { useEffect, useState, type FormEvent } from 'react'
import { Link } from 'react-router-dom'
import { api, staffToken } from '../api/client'
import { Notice } from '../components/ui'

type RoomRow = {
  id: number
  number: string
  floor: string | null
  room_type: string | null
  is_active: boolean
  qr: { public_code: string; url: string } | null
}

export function RoomsPage() {
  const [rooms, setRooms] = useState<RoomRow[]>([])
  const [error, setError] = useState<unknown>(null)

  function load() {
    api<{ data: RoomRow[] }>('/admin/rooms', { token: staffToken() }).then((body) => setRooms(body.data)).catch(setError)
  }

  useEffect(() => { load() }, [])

  async function create(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = new FormData(event.currentTarget)
    try {
      await api('/admin/rooms', {
        method: 'POST',
        token: staffToken(),
        body: JSON.stringify({
          number: form.get('number'),
          floor: form.get('floor'),
          room_type: form.get('room_type'),
          is_active: true,
        }),
      })
      event.currentTarget.reset()
      load()
    } catch (reason) {
      setError(reason)
    }
  }

  async function qr(id: number, action: 'revoke' | 'reissue') {
    try {
      await api(`/admin/rooms/${id}/qr-code`, { method: 'POST', token: staffToken(), body: JSON.stringify({ action }) })
      load()
    } catch (reason) {
      setError(reason)
    }
  }

  async function download(id: number, number: string) {
    const response = await fetch(`/api/v1/admin/rooms/${id}/qr-code.png`, {
      headers: { Authorization: `Bearer ${staffToken() ?? ''}` },
    })
    if (!response.ok) {
      setError(new Error('This room has no active QR code.'))
      return
    }
    const blob = await response.blob()
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `room-${number}.png`
    link.click()
    URL.revokeObjectURL(url)
  }

  return (
    <div className="stack">
      <form className="card" onSubmit={(event) => void create(event)}>
        <h2>Add a room</h2>
        <Notice error={error} />
        <label className="field"><span>Room number</span><input name="number" required /></label>
        <label className="field"><span>Floor</span><input name="floor" /></label>
        <label className="field"><span>Type</span><input name="room_type" /></label>
        <button className="btn btn-primary" type="submit">Create room and QR code</button>
      </form>
      {rooms.map((room) => (
        <article key={room.id} className="card">
          <h2>Room {room.number}</h2>
          <p className="muted">{room.floor ? `Floor ${room.floor}` : 'No floor'} · {room.room_type || 'Room'} · {room.is_active ? 'Active' : 'Inactive'}</p>
          {room.qr ? <p>{room.qr.url}</p> : <p className="pill pill-bad">QR revoked</p>}
          <div className="actions">
            <Link className="btn" to={`/admin/rooms/${room.id}/print`}>Print card</Link>
            <button className="btn" type="button" onClick={() => void download(room.id, room.number)}>Download PNG</button>
            <button className="btn" type="button" onClick={() => void qr(room.id, 'reissue')}>Reissue</button>
            <button className="btn btn-ghost" type="button" onClick={() => void qr(room.id, 'revoke')}>Revoke</button>
          </div>
        </article>
      ))}
    </div>
  )
}
