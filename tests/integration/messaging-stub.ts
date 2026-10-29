// Replaces lib/services/messaging: records the WhatsApp "send" instead of calling Meta.
import { db } from './fake-supabase'

export const sentMessages: any[] = []
export let failNext = false
export function setFailNext(v: boolean) { failNext = v }

export async function triggerWhatsAppRecoveryForCart(args: any) {
  if (failNext) return { sent: false, error: 'meta_rejected' }
  sentMessages.push(args)
  db.messages.push({ id: `m${sentMessages.length}`, store_id: args.storeId, cart_id: args.cartId, status: 'sent' })
  const cart = db.abandoned_carts.find((c) => c.id === args.cartId)
  if (cart) cart.status = 'messaged'
  return { sent: true }
}
