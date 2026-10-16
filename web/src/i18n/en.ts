const catalog = {
  welcomeEyebrow: 'Room service',
  scanTitle: 'Scan for Room Service',
  viewRequests: 'View my requests',
  contactReception: 'Contact reception',
  food: 'Food and drinks',
  housekeeping: 'Housekeeping',
  maintenance: 'Report a fault',
  hours: 'Service hours',
  hoursNote: 'A response target is not a guaranteed arrival time.',
  closed: 'Outside published service hours',
  open: 'Within published service hours',
  cart: 'Your order',
  placeOrder: 'Place order request',
  submitRequest: 'Submit request',
  reference: 'Reference',
  back: 'Back',
  loading: 'Loading',
  empty: 'Nothing here yet',
  staffSignIn: 'Staff sign in',
} as const

export type MessageKey = keyof typeof catalog

export function t(key: MessageKey): string {
  return catalog[key]
}
