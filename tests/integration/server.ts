// Serves the REAL backend route handlers over HTTP for the PHP plugin to call.
import http from 'http'
import { NextRequest } from 'next/server'
import { db, resetDb } from './fake-supabase'
import { sentMessages, setFailNext } from './messaging-stub'

const ROOT = '/home/claude/jkusseoni/cartrenew/app/api/woocommerce'
const routes: Record<string, () => Promise<any>> = {
  '/api/woocommerce/register-store': () => import(`${ROOT}/register-store/route`),
  '/api/woocommerce/abandoned-cart': () => import(`${ROOT}/abandoned-cart/route`),
  '/api/woocommerce/feedback': () => import(`${ROOT}/feedback/route`),
}

const server = http.createServer(async (req, res) => {
  const url = new URL(req.url || '/', 'http://localhost')
  let body = ''
  for await (const c of req) body += c

  // test control plane
  if (url.pathname === '/__state') {
    res.setHeader('content-type', 'application/json')
    return res.end(JSON.stringify({ db, sent: sentMessages }))
  }
  if (url.pathname === '/__reset') { resetDb(); sentMessages.length = 0; setFailNext(false); return res.end('ok') }
  if (url.pathname === '/__seed') { const { table, row } = JSON.parse(body); db[table].push(row); return res.end('ok') }
  if (url.pathname === '/__patch') { const { table, id, patch } = JSON.parse(body); Object.assign(db[table].find((r: any) => r.id === id), patch); return res.end('ok') }
  if (url.pathname === '/__fail_next') { setFailNext(true); return res.end('ok') }

  const load = routes[url.pathname]
  if (!load) { res.statusCode = 404; return res.end('{}') }
  const mod = await load()
  const headers: Record<string, string> = {}
  for (const [k, v] of Object.entries(req.headers)) if (typeof v === 'string') headers[k] = v
  const r = await mod.POST(new NextRequest(`http://localhost${req.url}`, { method: 'POST', headers, body: body || undefined }))
  res.statusCode = r.status
  res.setHeader('content-type', 'application/json')
  res.end(await r.text())
})
server.listen(Number(process.env.PORT || 4010), '127.0.0.1', () => console.log('READY'))
