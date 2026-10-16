import { describe, expect, it } from 'vitest'
import { orderPayload } from './orders'

describe('orderPayload', () => {
  it('omits browser prices so the server owns the total', () => {
    const payload = orderPayload(
      [{ menu_item_id: 4, quantity: 2, notes: 'No pepper', price_minor: 1 }],
      'cash_delivery',
      '',
    )
    expect(payload).toEqual({
      payment_method: 'cash_delivery',
      guest_note: '',
      items: [{ menu_item_id: 4, quantity: 2, notes: 'No pepper' }],
    })
    expect(JSON.stringify(payload)).not.toContain('price')
  })
})
