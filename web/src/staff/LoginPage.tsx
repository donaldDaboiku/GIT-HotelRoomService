import { useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { api, setStaffToken, setStaffUser, type StaffUser } from '../api/client'
import { Notice } from '../components/ui'
import { t } from '../i18n/en'

export function LoginPage() {
  const navigate = useNavigate()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState<unknown>(null)
  const [busy, setBusy] = useState(false)

  async function submit(event: FormEvent) {
    event.preventDefault()
    setBusy(true)
    setError(null)
    try {
      const body = await api<{ data: { token: string; user: StaffUser } }>('/staff/login', {
        method: 'POST',
        body: JSON.stringify({ email, password }),
      })
      setStaffToken(body.data.token)
      setStaffUser(body.data.user)
      navigate(body.data.user.role === 'platform_admin' ? '/platform' : '/staff')
    } catch (reason) {
      setError(reason)
    } finally {
      setBusy(false)
    }
  }

  return (
    <main className="guest-wrap stack">
      <p className="eyebrow">GIT System</p>
      <h1>{t('staffSignIn')}</h1>
      <form className="card" onSubmit={(event) => void submit(event)}>
        <Notice error={error} />
        <label className="field"><span>Email</span><input type="email" autoComplete="username" value={email} onChange={(event) => setEmail(event.target.value)} required /></label>
        <label className="field"><span>Password</span><input type="password" autoComplete="current-password" value={password} onChange={(event) => setPassword(event.target.value)} required /></label>
        <button className="btn btn-primary" type="submit" disabled={busy}>Sign in</button>
      </form>
    </main>
  )
}
