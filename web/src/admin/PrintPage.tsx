import { useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import { api, staffToken } from '../api/client'
import { Notice } from '../components/ui'

export function PrintPage() {
  const { id = '' } = useParams()
  const [svgUrl, setSvgUrl] = useState('')
  const [hotel, setHotel] = useState('')
  const [error, setError] = useState<unknown>(null)

  useEffect(() => {
    let objectUrl = ''
    api<{ data: { name: string } }>('/admin/hotel', { token: staffToken() })
      .then((body) => setHotel(body.data.name))
      .catch(setError)
    fetch(`/api/v1/admin/rooms/${id}/qr-code.svg`, {
      headers: { Authorization: `Bearer ${staffToken() ?? ''}`, Accept: 'image/svg+xml' },
    }).then(async (response) => {
      if (!response.ok) throw new Error('This room has no active QR code.')
      const svg = await response.text()
      if (!svg.includes('<svg')) throw new Error('The QR image was not valid.')
      objectUrl = URL.createObjectURL(new Blob([svg], { type: 'image/svg+xml' }))
      setSvgUrl(objectUrl)
    }).catch(setError)

    return () => {
      if (objectUrl) URL.revokeObjectURL(objectUrl)
    }
  }, [id])

  return (
    <div>
      <p className="no-print"><button className="btn btn-primary" type="button" onClick={() => window.print()}>Print</button></p>
      <Notice error={error} />
      <article className="print-sheet">
        <p className="eyebrow">GIT Hotel Room Service</p>
        <h1>{hotel}</h1>
        {svgUrl ? <img src={svgUrl} alt="Room QR code. Scan for Room Service." width={240} height={240} /> : null}
        <h2>Scan for Room Service</h2>
        <p className="muted">Point your camera at this code. No app and no account are required.</p>
      </article>
    </div>
  )
}
