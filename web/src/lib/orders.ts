export type OrderLine = {
  menu_item_id: number
  quantity: number
  notes?: string
  price_minor?: number
}

export function orderPayload(
  items: OrderLine[],
  paymentMethod: string,
  guestNote: string,
) {
  return {
    payment_method: paymentMethod,
    guest_note: guestNote,
    items: items.map((item) => ({
      menu_item_id: item.menu_item_id,
      quantity: item.quantity,
      notes: item.notes ?? '',
    })),
  }
}
