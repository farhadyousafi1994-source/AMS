/**
 * Local IndexedDB database via Dexie.js — the device's offline replica of the
 * central PostgreSQL data. Sync-eligible tables are keyed by the same `uuid`
 * the server uses, so a record created offline keeps one identity all the way
 * up to Postgres. An outbox (syncQueue) holds offline mutations; a conflicts
 * store holds edits the server flagged for review.
 */
import Dexie from 'dexie'
import { SYNC_TABLES } from './syncTables'

export const db = new Dexie('AriaHeratERP')

const stores = {
  branches: '++localId, id, company_id, name, _synced, _dirty',
  syncQueue: '++id, table, uuid, op, createdAt, attempts',
  syncMeta: 'table',
  conflicts: '++id, table, uuid, at',
}
for (const t of SYNC_TABLES) stores[t] = 'uuid, id, company_id, project_id, updated_at'

db.version(2).stores(stores)

/** RFC-4122 v4 id, used for records created offline. */
export function newUuid() {
  if (globalThis.crypto?.randomUUID) return globalThis.crypto.randomUUID()
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0
    return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16)
  })
}

/** Seed/refresh a local table from a server array (legacy path, keyed rows). */
export async function seedTable(tableName, rows) {
  const tbl = db[tableName]
  if (!tbl || !Array.isArray(rows)) return
  const stamped = rows.map((r) => ({ ...r, _synced: true, _dirty: false }))
  await tbl.bulkPut(stamped)
  await db.syncMeta.put({ table: tableName, lastSync: Date.now() })
}

/**
 * Apply a pulled batch: live rows are upserted by uuid; tombstoned rows
 * (deleted_at set) are removed locally.
 */
export async function upsertLocal(tableName, rows) {
  const tbl = db[tableName]
  if (!tbl || !Array.isArray(rows) || !rows.length) return
  const dead = rows.filter((r) => r.deleted_at).map((r) => r.uuid).filter(Boolean)
  const live = rows.filter((r) => !r.deleted_at && r.uuid)
  if (live.length) await tbl.bulkPut(live)
  if (dead.length) await tbl.bulkDelete(dead)
}

export async function getLocal(tableName, companyId) {
  const tbl = db[tableName]
  if (!tbl) return []
  if (companyId) return tbl.where('company_id').equals(companyId).toArray()
  return tbl.toArray()
}

/** Queue an offline mutation for later replay. */
export async function enqueueSync(table, op, payload, uuid, baseRevision = null) {
  await db.syncQueue.add({ table, op, payload, uuid, baseRevision, createdAt: Date.now(), attempts: 0 })
}

export async function getPendingQueue() {
  return db.syncQueue.orderBy('createdAt').toArray()
}

export async function dequeueSync(id) {
  await db.syncQueue.delete(id)
}

export async function pendingCount() {
  return db.syncQueue.count()
}

/** The global high-water mark for delta pulls. */
export async function getSince() {
  return (await db.syncMeta.get('__global__'))?.lastSync ?? null
}

export async function setSince(iso) {
  await db.syncMeta.put({ table: '__global__', lastSync: iso })
}

export async function addConflict(entry) {
  await db.conflicts.add({ ...entry, at: Date.now() })
}

export async function conflictCount() {
  return db.conflicts.count()
}

export async function listConflicts() {
  return db.conflicts.orderBy('at').reverse().toArray()
}

export async function deleteConflict(id) {
  await db.conflicts.delete(id)
}

/**
 * Resolve a flagged conflict. keep='mine' re-queues the local edit against the
 * server's current revision (so it applies on next push); keep='server' accepts
 * the server row locally and drops the local edit.
 */
export async function resolveConflict(id, keep) {
  const c = await db.conflicts.get(id)
  if (!c) return
  if (keep === 'mine') {
    const baseRevision = c.server_row?.revision ?? null
    await enqueueSync(c.table, 'update', c.local_payload, c.uuid, baseRevision)
  } else if (c.server_row) {
    await upsertLocal(c.table, [c.server_row])
  }
  await deleteConflict(id)
}

export default db
