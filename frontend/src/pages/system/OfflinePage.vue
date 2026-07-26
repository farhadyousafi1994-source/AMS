<template>
  <q-page>
    <m-backgrounds>
      <div class="row my_radio_less q-pa-sm">
        <div class="col-12">
          <m-header icon="wifi_off" controlRoomButton="false" class="q-mt-xs">
            Offline & Sync Settings
          </m-header>
        </div>

        <!-- Status Card -->
        <div class="col-12 col-md-6 q-mt-sm">
          <q-card class="my_radio_less three_d">
            <q-card-section>
              <div class="row items-center q-mb-md">
                <q-icon
                  :name="status.isOnline ? 'cloud_done' : 'wifi_off'"
                  :color="status.isOnline ? 'positive' : 'negative'"
                  size="28px" class="q-mr-sm"
                />
                <div>
                  <div class="text-weight-bold text-body1">
                    {{ status.isOnline ? 'Online' : 'Offline' }}
                  </div>
                  <div class="text-caption text-grey-6">
                    {{ status.isOnline ? 'Connected to server' : 'Working locally — changes will sync when connected' }}
                  </div>
                </div>
              </div>

              <div class="row q-col-gutter-sm">
                <div class="col-6">
                  <div class="stat-chip">
                    <div class="stat-chip__val text-orange">{{ status.pendingCount }}</div>
                    <div class="stat-chip__lbl">Pending Changes</div>
                  </div>
                </div>
                <div class="col-6">
                  <div class="stat-chip">
                    <div class="stat-chip__val text-teal-7">{{ lastSyncLabel }}</div>
                    <div class="stat-chip__lbl">Last Synced</div>
                  </div>
                </div>
              </div>

              <div v-if="status.lastError" class="q-mt-sm">
                <q-banner rounded class="bg-red-1 text-red-8 text-caption">
                  <template #avatar><q-icon name="error" color="red" /></template>
                  Last error: {{ status.lastError }}
                </q-banner>
              </div>

              <div class="q-mt-md row q-gutter-sm">
                <q-btn unelevated color="teal-7" icon="sync" label="Sync Now"
                  :loading="status.isSyncing" :disable="!status.isOnline || status.isSyncing"
                  @click="syncNow" class="my_radio_less" />
                <q-btn outline color="red-6" icon="delete_sweep" label="Clear Pending"
                  :disable="status.pendingCount === 0"
                  @click="confirmClear = true" class="my_radio_less" />
              </div>
            </q-card-section>
          </q-card>
        </div>

        <!-- Configuration Card -->
        <div class="col-12 col-md-6 q-mt-sm">
          <q-card class="my_radio_less three_d">
            <q-card-section>
              <div class="text-subtitle2 text-teal-8 q-mb-md">Sync Configuration</div>

              <div class="q-mb-md">
                <div class="cfg-label">Auto-sync interval</div>
                <q-select v-model="config.interval" :options="intervalOptions"
                  emit-value map-options outlined dense color="teal-7"
                  @update:model-value="saveConfig" />
              </div>

              <div class="q-mb-md">
                <div class="cfg-label">Data to cache offline</div>
                <q-list dense>
                  <q-item v-for="t in syncTables" :key="t.key" tag="label" class="q-px-none">
                    <q-item-section avatar>
                      <q-checkbox v-model="config.tables" :val="t.key" color="teal-7"
                        @update:model-value="saveConfig" />
                    </q-item-section>
                    <q-item-section>
                      <q-item-label>{{ t.label }}</q-item-label>
                      <q-item-label caption>{{ t.desc }}</q-item-label>
                    </q-item-section>
                  </q-item>
                </q-list>
              </div>

              <div>
                <div class="cfg-label">Conflict resolution</div>
                <q-select v-model="config.conflict" :options="conflictOptions"
                  emit-value map-options outlined dense color="teal-7"
                  @update:model-value="saveConfig" />
              </div>
            </q-card-section>
          </q-card>
        </div>

        <!-- Local Database Info -->
        <div class="col-12 q-mt-sm">
          <q-card class="my_radio_less three_d">
            <q-card-section>
              <div class="text-subtitle2 text-teal-8 q-mb-md">Local Database (IndexedDB)</div>
              <div class="row q-col-gutter-sm">
                <div class="col-6 col-sm-3" v-for="t in dbStats" :key="t.label">
                  <div class="stat-chip">
                    <div class="stat-chip__val text-teal-7">{{ t.count }}</div>
                    <div class="stat-chip__lbl">{{ t.label }}</div>
                  </div>
                </div>
              </div>
              <div class="q-mt-md">
                <q-btn flat dense color="red-6" icon="delete_forever" label="Clear Local Cache"
                  @click="confirmClearCache = true" size="sm" />
              </div>
            </q-card-section>
          </q-card>
        </div>

        <!-- How It Works -->
        <div class="col-12 q-mt-sm">
          <q-card class="my_radio_less bg-teal-1">
            <q-card-section>
              <div class="text-subtitle2 text-teal-8 q-mb-sm">How Offline Mode Works</div>
              <div class="row q-col-gutter-md">
                <div class="col-12 col-sm-4" v-for="step in howItWorks" :key="step.title">
                  <div class="how-step">
                    <q-icon :name="step.icon" color="teal-7" size="24px" class="q-mb-xs" />
                    <div class="text-weight-bold text-caption text-teal-9">{{ step.title }}</div>
                    <div class="text-caption text-grey-7 q-mt-xs">{{ step.desc }}</div>
                  </div>
                </div>
              </div>
            </q-card-section>
          </q-card>
        </div>

      </div>
    </m-backgrounds>

    <!-- Clear pending confirm -->
    <q-dialog v-model="confirmClear">
      <q-card class="my_radio_less" style="min-width: 300px">
        <q-card-section class="text-h6">Clear Pending Changes?</q-card-section>
        <q-card-section class="text-body2 text-grey-7">
          This will discard {{ status.pendingCount }} unsaved changes that haven't been synced yet. This cannot be undone.
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancel" v-close-popup />
          <q-btn unelevated color="red-6" label="Clear" @click="clearPending" v-close-popup />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Clear cache confirm -->
    <q-dialog v-model="confirmClearCache">
      <q-card class="my_radio_less" style="min-width: 300px">
        <q-card-section class="text-h6">Clear Local Cache?</q-card-section>
        <q-card-section class="text-body2 text-grey-7">
          All locally cached data will be removed. The app will re-download data from the server on next sync.
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancel" v-close-popup />
          <q-btn unelevated color="red-6" label="Clear Cache" @click="clearCache" v-close-popup />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Notify } from 'quasar'
import { useSyncStatus, syncService } from '@/services/syncService'
import { db, getPendingQueue, dequeueSync } from '@/services/localDb'

const status = useSyncStatus() // reactive object (not a ref)
const confirmClear = ref(false)
const confirmClearCache = ref(false)
const dbStats = ref([])

const intervalOptions = [
  { label: '30 seconds', value: 30 },
  { label: '1 minute', value: 60 },
  { label: '5 minutes', value: 300 },
  { label: '15 minutes', value: 900 },
  { label: 'Manual only', value: 0 },
]

const conflictOptions = [
  { label: 'Server wins (recommended)', value: 'server' },
  { label: 'Local wins', value: 'local' },
  { label: 'Newer timestamp wins', value: 'newest' },
]

const syncTables = [
  { key: 'branches', label: 'Branches / Sites', desc: 'Cache branch list for offline use' },
]

const howItWorks = [
  { icon: 'save', title: 'Work Offline', desc: 'The platform keeps working even without internet on-site. All changes are saved locally in IndexedDB.' },
  { icon: 'queue', title: 'Queue Changes', desc: 'Every create/update/delete is queued locally. Nothing is lost — even if you close the app.' },
  { icon: 'sync', title: 'Auto Sync', desc: 'When internet is restored, all queued changes are automatically replayed to the server in order.' },
]

const config = ref({
  interval: parseInt(localStorage.getItem('sync_interval') || '30'),
  tables: JSON.parse(localStorage.getItem('sync_tables') || '["branches"]'),
  conflict: localStorage.getItem('sync_conflict') || 'server',
})

const lastSyncLabel = computed(() => {
  if (!status.lastSyncAt) return 'Never'
  const diff = Math.floor((Date.now() - new Date(status.lastSyncAt)) / 1000)
  if (diff < 60) return `${diff}s ago`
  if (diff < 3600) return `${Math.floor(diff / 60)}m ago`
  return `${Math.floor(diff / 3600)}h ago`
})

function saveConfig() {
  localStorage.setItem('sync_interval', config.value.interval)
  localStorage.setItem('sync_tables', JSON.stringify(config.value.tables))
  localStorage.setItem('sync_conflict', config.value.conflict)
  Notify.create({ type: 'positive', position: 'bottom', message: 'Settings saved', timeout: 1500 })
}

function syncNow() {
  syncService.sync()
}

async function clearPending() {
  const queue = await getPendingQueue()
  for (const item of queue) {
    await dequeueSync(item.id)
  }
  Notify.create({ type: 'warning', message: 'Pending changes cleared' })
}

async function clearCache() {
  await db.branches.clear()
  Notify.create({ type: 'positive', message: 'Local cache cleared — will re-sync on next connection' })
  loadDbStats()
}

async function loadDbStats() {
  dbStats.value = [
    { label: 'Branches', count: await db.branches.count() },
  ]
}

onMounted(loadDbStats)
</script>

<style scoped>
.stat-chip {
  background: #F8FAFC;
  border: 1px solid #E2E8F0;
  border-radius: 10px;
  padding: 12px 16px;
  text-align: center;
}
.stat-chip__val { font-size: 22px; font-weight: 800; }
.stat-chip__lbl { font-size: 11px; color: #94A3B8; margin-top: 2px; }

.cfg-label { font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 6px; }

.how-step {
  padding: 16px;
  background: var(--surface-card);
  border-radius: 10px;
  border: 1px solid #CCFBF1;
}
</style>
