// Minimal in-memory stand-in for the supabase-js query builder (only what the
// WooCommerce routes use). Tables are plain arrays, exposed for assertions.
import { randomUUID } from 'crypto'

export const db: Record<string, any[]> = { stores: [], abandoned_carts: [], messages: [], woocommerce_plugin_feedback: [] }
export function resetDb() { for (const k of Object.keys(db)) db[k] = [] }

class Query {
  private filters: Array<(r: any) => boolean> = []
  private op: 'select' | 'insert' | 'update' = 'select'
  private payload: any
  private head = false
  private wantCount = false
  private lim: number | null = null
  constructor(private table: string) {}

  select(_cols?: string, opts?: { count?: string; head?: boolean }) {
    if (this.op === 'select') {
      this.head = !!opts?.head
      this.wantCount = !!opts?.count
    }
    return this
  }
  insert(row: any) { this.op = 'insert'; this.payload = row; return this }
  update(patch: any) { this.op = 'update'; this.payload = patch; return this }
  eq(col: string, val: any) { this.filters.push((r) => r[col] === val); return this }
  in(col: string, vals: any[]) { this.filters.push((r) => vals.includes(r[col])); return this }
  limit(n: number) { this.lim = n; return this }

  private run(): { data: any; error: any; count?: number } {
    const rows = (db[this.table] ||= [])
    if (this.op === 'insert') {
      // unique site_url on stores
      if (this.table === 'stores' && rows.some((r) => r.site_url && r.site_url === this.payload.site_url)) {
        return { data: null, error: { code: '23505', message: 'duplicate' } }
      }
      const row = { id: randomUUID(), ...this.payload }
      rows.push(row)
      return { data: [row], error: null }
    }
    const matched = rows.filter((r) => this.filters.every((f) => f(r)))
    if (this.op === 'update') {
      matched.forEach((r) => Object.assign(r, this.payload))
      return { data: matched, error: null }
    }
    const limited = this.lim ? matched.slice(0, this.lim) : matched
    return { data: this.head ? null : limited, error: null, count: this.wantCount ? matched.length : undefined }
  }
  maybeSingle() { const r = this.run(); return Promise.resolve({ ...r, data: Array.isArray(r.data) ? (r.data[0] ?? null) : r.data }) }
  single() { return this.maybeSingle() }
  then(res: any, rej: any) { return Promise.resolve(this.run()).then(res, rej) }
}

export const supabaseAdmin = { from: (t: string) => new Query(t) }
