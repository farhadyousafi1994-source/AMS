/**
 * offlineApi — wraps the axios `api` instance.
 * When online: pass through to the server normally.
 * When offline: serve GET from IndexedDB, and queue POST/PUT/DELETE mutations
 * into the outbox keyed by the record's `uuid` (generated locally for creates),
 * so they replay cleanly into PostgreSQL when the connection returns.
 *
 * Usage: in a component that needs offline support, replace
 *        `import { api } from '@/boot/axios'`
 *   with `import { offlineApi as api } from '@/services/offlineApi'`
 */
import { api } from '@/boot/axios'
import { syncState } from './syncService'
import { getLocal, enqueueSync, db, newUuid } from './localDb'

const ENDPOINT_TABLE_MAP = {
  '/branches': 'branches',
  '/projects': 'projects',
  '/safety-incidents': 'safety_incidents',
  '/purchase-requests': 'purchase_requests',
  '/site-invoices': 'site_invoices',
  '/expenses': 'expenses',
  '/receipts': 'receipts',
  '/invoices': 'invoices',
  '/workers': 'workers',
  '/assets': 'assets',
  '/suppliers': 'suppliers',
  '/purchase-orders': 'purchase_orders',
  '/parties': 'parties',
  '/contracts': 'contracts',
}

function tableForUrl(url = '') {
  const clean = url.replace(/^\/api/, '').split('?')[0]
  for (const [prefix, table] of Object.entries(ENDPOINT_TABLE_MAP)) {
    if (clean.startsWith(prefix)) return table
  }
  return null
}

function getCompanyId() {
  try {
    const user = JSON.parse(localStorage.getItem('user') || '{}')
    return user.current_company || null
  } catch { return null }
}

async function localByUrlId(table, url) {
  const idMatch = url.match(/\/(\d+)$/)
  if (!idMatch) return null
  return db[table]?.where('id').equals(Number(idMatch[1])).first().catch(() => null)
}

const offlineApi = {
  async get(url, config = {}) {
    if (syncState.isOnline) return api.get(url, config)
    const table = tableForUrl(url)
    if (table) {
      const rows = await getLocal(table, getCompanyId())
      return { data: rows, status: 200, offline: true }
    }
    return api.get(url, config)
  },

  async post(url, data = {}, config = {}) {
    if (syncState.isOnline) return api.post(url, data, config)
    const table = tableForUrl(url)
    if (table) {
      const uuid = newUuid()
      const record = { ...data, uuid, id: null, revision: 1, _synced: false, _dirty: true, created_at: new Date().toISOString(), updated_at: new Date().toISOString() }
      await db[table]?.put(record).catch(() => {})
      await enqueueSync(table, 'create', data, uuid)
      return { data: record, status: 201, offline: true }
    }
    return api.post(url, data, config)
  },

  async put(url, data = {}, config = {}) {
    if (syncState.isOnline) return api.put(url, data, config)
    const table = tableForUrl(url)
    const existing = table ? await localByUrlId(table, url) : null
    if (table && existing) {
      await db[table].put({ ...existing, ...data, _dirty: true, _synced: false, updated_at: new Date().toISOString() }).catch(() => {})
      await enqueueSync(table, 'update', data, existing.uuid, existing.revision ?? null)
      return { data: { ...existing, ...data }, status: 200, offline: true }
    }
    return api.put(url, data, config)
  },

  async delete(url, config = {}) {
    if (syncState.isOnline) return api.delete(url, config)
    const table = tableForUrl(url)
    const existing = table ? await localByUrlId(table, url) : null
    if (table && existing) {
      await db[table].delete(existing.uuid).catch(() => {})
      await enqueueSync(table, 'delete', {}, existing.uuid, existing.revision ?? null)
      return { data: { message: 'Queued for deletion' }, status: 200, offline: true }
    }
    return api.delete(url, config)
  },
}

export { offlineApi }
export default offlineApi
