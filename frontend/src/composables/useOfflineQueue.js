import { ref } from 'vue'
import { api } from '@/boot/axios'

// Capture-then-sync for field attendance on patchy connections. Marks are
// queued in localStorage and flushed to POST /worker-attendances/sync when the
// device is online. Photos aren't queued offline (kept to the online path);
// each queued mark carries a client_uuid so a retry never double-marks.
const STORE_KEY = 'ahmz_attendance_queue'

function readQueue () {
  try { return JSON.parse(localStorage.getItem(STORE_KEY) || '[]') } catch (_) { return [] }
}
function writeQueue (q) { localStorage.setItem(STORE_KEY, JSON.stringify(q)) }

export function useOfflineQueue () {
  const pending = ref(readQueue().length)
  const online = ref(navigator.onLine)
  const syncing = ref(false)

  function refresh () { pending.value = readQueue().length }

  function enqueue (record) {
    const q = readQueue()
    q.push({ ...record, client_uuid: record.client_uuid || (crypto?.randomUUID?.() || String(Date.now() + Math.random())) })
    writeQueue(q)
    refresh()
  }

  async function flush () {
    if (syncing.value) return { synced: 0 }
    const q = readQueue()
    if (q.length === 0 || !navigator.onLine) return { synced: 0 }
    syncing.value = true
    try {
      const { data } = await api.post('/worker-attendances/sync', { records: q })
      writeQueue([]) // the batch is idempotent on (worker, date), so clear on success
      refresh()
      return data
    } finally { syncing.value = false }
  }

  function bind () {
    window.addEventListener('online', () => { online.value = true; flush() })
    window.addEventListener('offline', () => { online.value = false })
  }

  return { pending, online, syncing, enqueue, flush, bind, refresh }
}
