import { Link } from 'react-router-dom'

export function LandingPage() {
  return (
    <main className="guest-wrap stack">
      <p className="eyebrow">GreenPasture Integrated Tech System</p>
      <h1>GIT Hotel Room Service</h1>
      <p>Scan the card in your room. You can request service, order food, and follow the request without creating an account.</p>
      <section className="card">
        <h2>Guests</h2>
        <p>Use the QR code in your room. The link opens this hotel’s service page for that room only.</p>
      </section>
      <Link className="btn btn-navy" to="/staff/login">Staff sign in</Link>
    </main>
  )
}
