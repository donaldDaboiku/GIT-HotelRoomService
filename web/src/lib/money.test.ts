import { describe, expect, it } from 'vitest'
import { formatMinor } from './money'

describe('formatMinor', () => {
  it('formats naira from integer kobo', () => {
    expect(formatMinor(450000)).toBe('₦4,500.00')
    expect(formatMinor(80)).toBe('₦0.80')
    expect(formatMinor(0)).toBe('₦0.00')
  })
})
