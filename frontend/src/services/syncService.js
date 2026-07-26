/**
 * SyncService — replays queued offline mutations up to the server in one batch
 * (/sync/push) and pulls the delta of everything that changed since the last
 * sync (/sync/pull) down into IndexedDB. Applied changes clear the outbox;
 * server-flagged conflicts are moved to the conflicts store for review.
 *
 *   import { syncService } from '@/services/syncService'
 *   syncService.start()   // once, on app mount
 *   syncService.sync()    // manual trigger
 */
import { api } from '@/boot/axios'
import { SYNC_TABLES } from './syncTables'
import {
  db, getPendingQueue, dequeueSync, pendingCount,
  upsertLocal, getSince, setSince, addConflict, conflictCount,
} from './localDb'
import { reactive } from 'vue'

export const syncState = reactive({
  isOnline: navigator.onLine,
  isSyncing: false,
  pendingCount: 0,
  conflictCount: 0,
  lastSyncAt: null,
  lastError: null,
})

let _timer = null

export const syncService = {
  start() {
    window.addEventListener('online', () => { syncState.isOnline = true; this.sync() })
    window.addEventListener('offline', () => { syncState.isOnline = false })
    syncState.isOnline = navigator.onLine

    _timer = setInterval(() => { if (syncState.isOnline) this.sync() }, 30_000)
    if (syncState.isOnline) setTimeout(() => this.sync(), 2000)
  },

  stop() {
    if (_timer) clearInterval(_timer)
  },

  async sync() {
    if (syncState.isSyncing || !syncState.isOnline) return
    syncState.isSyncing = true
    syncState.lastError = null
    try {
      await this._pushQueue()
      await this._pullDelta()
      syncState.lastSyncAt = new Date()
      syncState.pendingCount = await pendingCount()
      syncState.conflictCount = await conflictCount()
    } catch (err) {
      syncState.lastError = err?.response?.data?.message || err?.message || 'Sync failed'
      console.warn('[SyncService] error:', err)
    } finally {
      syncState.isSyncing = false
    }
  },

  /** Send the whole outbox in one batch and resolve each result. */
  async _pushQueue() {
    const queue = await getPendingQueue()
    if (!queue.length) return

    const changes = queue.map((e) => ({
      table: e.table,
      op: e.op,
      uuid: e.uuid,
      base_revision: e.baseRevision ?? null,
      payload: e.payload ?? {},
    }))

    const { data } = await api.post('/sync/push', { changes })
    const byUuid = new Map((data?.results ?? []).map((r) => [r.uuid + ':' + r.op, r]))

    for (const entry of queue) {
      const res = byUuid.get(entry.uuid + ':' + entry.op)
      if (!res) continue
      if (res.status === 'applied') {
        if (res.server_row) await upsertLocal(entry.table, [res.server_row])
        await dequeueSync(entry.id)
      } else if (res.status === 'conflict') {
        await addConflict({ table: entry.table, uuid: entry.uuid, local_payload: entry.payload, server_row: res.server_row })
        await dequeueSync(entry.id)
      } else {
        const attempts = (entry.attempts || 0) + 1
        await db.syncQueue.update(entry.id, { attempts })
        if (attempts >= 5) await dequeueSync(entry.id)
      }
    }
  },

  /** Pull everything changed since the last sync into local tables. */
  async _pullDelta() {
    const since = await getSince()
    const { data } = await api.post('/sync/pull', { tables: SYNC_TABLES, since })
    const tables = data?.data ?? {}
    for (const [table, rows] of Object.entries(tables)) {
      await upsertLocal(table, rows)
    }
    if (data?.server_time) await setSince(data.server_time)
  },
}

export function useSyncStatus() {
  return syncState
}
