export class ApiError extends Error {
  status: number
  errors?: Record<string, string[]>
  payload?: { reference?: string }

  constructor(status: number, message: string, errors?: Record<string, string[]>, payload?: { reference?: string }) {
    super(message)
    this.status = status
    this.errors = errors
    this.payload = payload
  }
}

const base = import.meta.env.VITE_API_URL ?? ''

type Options = RequestInit & { token?: string | null; guest?: boolean }

export async function api<T>(path: string, options: Options = {}): Promise<T> {
  const headers = new Headers(options.headers)
  headers.set('Accept', 'application/json')
  if (options.body && !(options.body instanceof FormData)) {
    headers.set('Content-Type', 'application/json')
  }
  if (options.token) {
    if (options.guest) headers.set('X-Guest-Token', options.token)
    else headers.set('Authorization', `Bearer ${options.token}`)
  }

  const response = await fetch(`${base}/api/v1${path}`, { ...options, headers })
  const contentType = response.headers.get('content-type') ?? ''
  if (!contentType.includes('application/json')) {
    if (!response.ok) throw new ApiError(response.status, 'The server could not complete that request.')
    return undefined as T
  }
  const body = await response.json() as { message?: string; errors?: Record<string, string[]>; data?: { reference?: string } }
  if (!response.ok) {
    throw new ApiError(response.status, body.message ?? 'Request failed', body.errors, body.data)
  }
  return body as T
}

export function staffToken(): string | null {
  return localStorage.getItem('git.staff.token')
}

export function setStaffToken(token: string | null): void {
  if (token) localStorage.setItem('git.staff.token', token)
  else localStorage.removeItem('git.staff.token')
}

export type StaffUser = {
  id: number
  name: string
  email: string
  role: string
  role_label: string
  status: string
  department: { id: number; name: string; code: string } | null
  hotel: { name: string; public_code: string; currency: string; timezone: string } | null
}

export function staffUser(): StaffUser | null {
  const raw = localStorage.getItem('git.staff.user')
  if (!raw) return null
  try {
    return JSON.parse(raw) as StaffUser
  } catch {
    return null
  }
}

export function setStaffUser(user: StaffUser | null): void {
  if (user) localStorage.setItem('git.staff.user', JSON.stringify(user))
  else localStorage.removeItem('git.staff.user')
}
