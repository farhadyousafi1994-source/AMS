<!--
  SyncStatusBar — shows in MainLayout toolbar.
  - Green wifi = online + synced
  - Amber clock = online + syncing
  - Red wifi-off = offline (with pending count badge)
-->
<template>
  <q-btn flat dense round size="sm" @click="manualSync" :loading="status.isSyncing">
    <!-- Offline -->
    <q-icon v-if="!status.isOnline" name="wifi_off" color="red-4" size="18px">
      <q-badge v-if="status.pendingCount > 0" color="red" floating :label="status.pendingCount" />
    </q-icon>
    <!-- Online syncing -->
    <q-icon v-else-if="status.isSyncing" name="sync" color="amber-4" size="18px" class="sync-spin" />
    <!-- Online synced -->
    <q-icon v-else name="cloud_done" color="green-4" size="18px">
      <q-badge v-if="status.pendingCount > 0" color="orange" floating :label="status.pendingCount" />
    </q-icon>

    <q-tooltip>
      <div v-if="!status.isOnline">
        <b>Offline</b><br>
        {{ status.pendingCount }} change(s) pending sync<br>
        <small>Will sync automatically when online</small>
      </div>
      <div v-else-if="status.isSyncing">Syncing...</div>
      <div v-else>
        Online — {{ status.pendingCount }} pending<br>
        <small v-if="status.lastSyncAt">Last sync: {{ timeAgo(status.lastSyncAt) }}</small>
        <small v-if="status.lastError" class="text-red-3">Error: {{ status.lastError }}</small><br>
        <small>Click to sync now</small>
      </div>
    </q-tooltip>
  </q-btn>
</template>

<script setup>
import { useSyncStatus, syncService } from '@/services/syncService'

const status = useSyncStatus()

function manualSync() {
  if (status.isOnline) syncService.sync()
}

function timeAgo(date) {
  const diff = Math.floor((Date.now() - new Date(date)) / 1000)
  if (diff < 60) return `${diff}s ago`
  if (diff < 3600) return `${Math.floor(diff/60)}m ago`
  return `${Math.floor(diff/3600)}h ago`
}
</script>

<style scoped>
.sync-spin { animation: spin 1.2s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
