import { useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import { api, staffToken } from '../api/client'
import { Notice } from '../components/ui'

export function PrintPage() {
  const { id = '' } = useParams()
  const [svg, setSvg] = useState('')
  const [hotel, setHotel] = useState('')
  const [error, setError] = useState<unknown>(null)

  useEffect(() => {
    api<{ data: { name: string } }>('/admin/hotel', { token: staffToken() })
      .then((body) => setHotel(body.data.name))
      .catch(setError)
    fetch(`/api/v1/admin/rooms/${id}/qr-code.svg`, {
      headers: { Authorization: `Bearer ${staffToken() ?? ''}`, Accept: 'image/svg+xml' },
    }).then(async (response) => {
      if (!response.ok) throw new Error('This room has no active QR code.')
      setSvg(await response.text())
    }).catch(setError)
  }, [id])

  return (
    <div>
      <p className="no-print"><button className="btn btn-primary" type="button" onClick={() => window.print()}>Print</button></p>
      <Notice error={error} />
      <article className="print-sheet">
        <p className="eyebrow">GIT Hotel Room Service</p>
        <h1>{hotel}</h1>
        {svg.startsWith('<svg') || svg.startsWith('<?xml') ? <div dangerouslySetInnerHTML={{ __html: svg }} /> : null}
        <h2>Scan for Room Service</h2>
        <p className="muted">Point your camera at this code. No app and no account are required.</p>
      </article>
    </div>
  )
}
