export function formatMinor(minor: number, currency = 'NGN'): string {
  const sign = minor < 0 ? '-' : ''
  const absolute = Math.abs(Math.trunc(minor))
  const major = Math.floor(absolute / 100)
  const cents = (absolute % 100).toString().padStart(2, '0')
  const grouped = major.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',')
  const symbol = currency === 'NGN' ? '₦' : `${currency} `
  return `${symbol}${sign}${grouped}.${cents}`
}

export function formatWhen(iso: string | null | undefined, timeZone = 'Africa/Lagos'): string {
  if (!iso) return '—'
  return new Intl.DateTimeFormat('en-NG', {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone,
  }).format(new Date(iso))
}
