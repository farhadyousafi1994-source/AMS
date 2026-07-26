<template>
  <q-page>
    <m-backgrounds>
      <div class="row my_radio_less q-pa-sm q-col-gutter-md">
        <div class="col-12">
          <m-header icon="sync" controlRoomButton="false" class="q-mt-xs">{{ $t('SyncCenter') }}</m-header>
        </div>

        <!-- Status -->
        <div class="col-12">
          <q-card class="my_radio_less bg-white">
            <q-card-section class="row items-center q-col-gutter-md">
              <div class="col-6 col-sm-3"><div class="sync-kpi"><q-icon :name="state.isOnline ? 'wifi' : 'wifi_off'" size="22px" :color="state.isOnline ? 'green-7' : 'red-7'" /><div class="sync-kpi__val">{{ state.isOnline ? $t('Online') : $t('Offline') }}</div><div class="sync-kpi__lbl">{{ $t('Connection') }}</div></div></div>
              <div class="col-6 col-sm-3"><div class="sync-kpi"><q-icon name="cloud_upload" size="22px" color="amber-8" /><div class="sync-kpi__val">{{ state.pendingCount }}</div><div class="sync-kpi__lbl">{{ $t('PendingChanges') }}</div></div></div>
              <div class="col-6 col-sm-3"><div class="sync-kpi"><q-icon name="error_outline" size="22px" color="deep-orange-8" /><div class="sync-kpi__val">{{ state.conflictCount }}</div><div class="sync-kpi__lbl">{{ $t('Conflicts') }}</div></div></div>
              <div class="col-6 col-sm-3"><div class="sync-kpi"><q-icon name="schedule" size="22px" color="blue-7" /><div class="sync-kpi__val">{{ lastSync }}</div><div class="sync-kpi__lbl">{{ $t('LastSync') }}</div></div></div>
            </q-card-section>
            <q-separator />
            <q-card-actions align="right" class="q-pa-sm">
              <span class="text-caption text-negative q-mr-sm" v-if="state.lastError">{{ state.lastError }}</span>
              <progress-btn color="primary" icon="sync" :loading="state.isSyncing" :disable="!state.isOnline" @click="syncNow">{{ $t('SyncNow') }}</progress-btn>
            </q-card-actions>
          </q-card>
        </div>

        <!-- Conflicts -->
        <div class="col-12">
          <q-card class="my_radio_less bg-white">
            <q-card-section>
              <div class="text-subtitle1 text-weight-bold q-mb-sm">{{ $t('ConflictsToReview') }}</div>
              <div v-if="!conflicts.length" class="text-center text-grey-5 q-py-lg"><q-icon name="task_alt" size="28px" color="green-6" class="q-mb-xs block" />{{ $t('NoConflicts') }}</div>
              <div v-for="c in conflicts" :key="c.id" class="conflict">
                <div class="conflict__head">
                  <q-chip dense size="sm" color="blue-grey-7" text-color="white">{{ c.table }}</q-chip>
                  <span class="text-caption text-grey-6">{{ c.uuid }}</span>
                </div>
                <div class="row q-col-gutter-sm q-mt-xs">
                  <div class="col-12 col-sm-6"><div class="conflict__box"><div class="conflict__lbl">{{ $t('YourEdit') }}</div><pre class="conflict__pre">{{ pretty(c.local_payload) }}</pre></div></div>
                  <div class="col-12 col-sm-6"><div class="conflict__box conflict__box--srv"><div class="conflict__lbl">{{ $t('ServerVersion') }}</div><pre class="conflict__pre">{{ pretty(c.server_row) }}</pre></div></div>
                </div>
                <div class="row justify-end q-gutter-xs q-mt-xs">
                  <q-btn outline dense size="sm" color="blue-grey-7" icon="cloud_done" :label="$t('KeepServer')" @click="resolve(c.id, 'server')" />
                  <q-btn unelevated dense size="sm" color="primary" icon="upload" :label="$t('KeepMine')" @click="resolve(c.id, 'mine')" />
                </div>
              </div>
            </q-card-section>
          </q-card>
        </div>
      </div>
    </m-backgrounds>
  </q-page>
</template>

<script setup>
import { ref, computed, onMounted, getCurrentInstance } from 'vue'
import { Notify } from 'quasar'
import { syncService, syncState } from '@/services/syncService'
import { listConflicts, resolveConflict, conflictCount } from '@/services/localDb'

const { proxy } = getCurrentInstance()
const $t = (k) => (proxy?.$t ? proxy.$t(k) : k)

const state = syncState
const conflicts = ref([])

const lastSync = computed(() => state.lastSyncAt ? new Date(state.lastSyncAt).toLocaleTimeString() : '—')

function pretty (o) { try { return JSON.stringify(o ?? {}, null, 1) } catch { return String(o) } }

async function refresh () { conflicts.value = await listConflicts(); state.conflictCount = await conflictCount() }
async function syncNow () { await syncService.sync(); await refresh() }
async function resolve (id, keep) {
  await resolveConflict(id, keep)
  Notify.create({ type: 'positive', position: 'bottom', message: keep === 'mine' ? $t('KeptYourEdit') : $t('KeptServerVersion') })
  await refresh()
  if (keep === 'mine' && state.isOnline) await syncNow()
}

onMounted(refresh)
</script>

<style scoped>
.sync-kpi { border: 1.5px solid #E2E8F0; border-radius: 12px; padding: 12px 14px; text-align: center; background: #fff; height: 100%; }
.sync-kpi__val { font-size: 17px; font-weight: 800; margin-top: 4px; color: #1E293B; }
.sync-kpi__lbl { font-size: 11px; color: #94A3B8; margin-top: 1px; }
.conflict { border: 1px solid #FED7AA; border-radius: 12px; padding: 12px; margin-bottom: 10px; background: #FFFBF5; }
.conflict__head { display: flex; align-items: center; gap: 8px; }
.conflict__box { border: 1px solid #E2E8F0; border-radius: 8px; padding: 8px; background: #fff; }
.conflict__box--srv { border-color: #BFDBFE; background: #F8FBFF; }
.conflict__lbl { font-size: 11px; font-weight: 700; color: #64748B; margin-bottom: 4px; }
.conflict__pre { font-size: 11px; margin: 0; white-space: pre-wrap; word-break: break-word; max-height: 160px; overflow: auto; color: #334155; }
.block { display: block; margin-left: auto; margin-right: auto; }
@media (prefers-color-scheme: dark) { .sync-kpi, .conflict__box { background: #1E293B; border-color: #334155; } .sync-kpi__val { color: #F1F5F9; } }
</style>
