<template>
  <q-page>
    <m-backgrounds>
      <div class="row my_radio_less q-pa-sm">
        <div class="col-12">
          <m-header icon="event_available" controlRoomButton="false" class="q-mt-xs">{{ $t('FieldAttendance') }}</m-header>
        </div>

        <!-- Connection + sync banner -->
        <div class="col-12 q-mt-sm">
          <div class="att-bar" :class="online ? 'att-bar--on' : 'att-bar--off'">
            <q-icon :name="online ? 'wifi' : 'wifi_off'" size="18px" />
            <span class="text-weight-medium">{{ online ? $t('Online') : $t('OfflineCaptured') }}</span>
            <q-space />
            <q-chip v-if="pending > 0" dense color="amber-8" text-color="white" icon="cloud_upload">{{ pending }} {{ $t('QueuedToSync') }}</q-chip>
            <q-btn v-if="pending > 0 && online" dense unelevated color="white" text-color="teal-9" :loading="syncing" icon="sync" :label="$t('SyncNow')" @click="doFlush" />
          </div>
        </div>

        <!-- Controls -->
        <div class="col-12 q-mt-sm">
          <div class="row q-col-gutter-sm items-center">
            <div class="col-6 col-sm-3"><q-select outlined dense color="primary" v-model="projectId" :options="projectOptions" emit-value map-options :label="$t('Project')" @update:model-value="load" /></div>
            <div class="col-6 col-sm-3"><shamsi-date v-model="workDate" color="primary" :label="$t('Date')" @update:model-value="load" /></div>
            <div class="col-8 col-sm-4"><q-input outlined dense color="primary" v-model="task" :label="$t('TaskToday')"><template #prepend><q-icon name="assignment" color="primary" /></template></q-input></div>
            <div class="col-4 col-sm-2">
              <q-btn dense outline :color="gps ? 'positive' : 'primary'" :icon="gps ? 'my_location' : 'location_searching'" :label="gps ? $t('Located') : $t('GetGPS')" @click="captureGps" :loading="locating" class="full-width" />
            </div>
          </div>
        </div>

        <!-- Summary -->
        <div class="col-12 q-mt-md">
          <div class="row q-col-gutter-md">
            <div class="col-4"><stat-card dense icon="how_to_reg" :label="$t('Present')" :value="summary.present" color="#16A34A" tint="#DCFCE7" /></div>
            <div class="col-4"><stat-card dense icon="person_off" :label="$t('Absent')" :value="summary.absent" color="#DC2626" tint="#FEE2E2" /></div>
            <div class="col-4"><stat-card dense icon="payments" :label="$t('WageTotal')" :value="fmt(summary.wage_total)" :suffix="summary.base" color="#175A8C" tint="#E0EDF7" /></div>
          </div>
        </div>

        <!-- General attendance sheet attachment (one hard-copy per project/day) -->
        <div class="col-12 q-mt-md" v-if="projectId">
          <q-card flat bordered class="my_radio_less q-pa-sm">
            <attach-box type="project" :id="projectId" :kind="'attendance-' + workDate"
              :label="$t('AttendanceSheet') + ' — ' + workDate" icon="assignment_turned_in"
              accept="image/*,application/pdf" />
          </q-card>
        </div>

        <!-- Worker roster -->
        <div class="col-12 q-mt-md">
          <div v-if="loading" class="text-center q-py-lg"><q-spinner color="primary" size="2.5em" /></div>
          <div v-else-if="workers.length === 0" class="text-center text-grey-5 q-py-lg">{{ $t('NoWorkersHint') }}</div>
          <div v-else class="row q-col-gutter-sm">
            <div class="col-12 col-md-6" v-for="w in workers" :key="w.id">
              <div class="wk-row" :class="'wk-row--' + (markOf(w.id)?.status || 'none')">
                <q-avatar size="40px" color="teal-2" text-color="teal-9">
                  <img v-if="photos[w.id]" :src="photos[w.id]" />
                  <span v-else>{{ (w.name || '؟').slice(0, 1) }}</span>
                </q-avatar>
                <div class="wk-row__info">
                  <div class="wk-row__name">{{ w.name }} <span class="text-caption text-grey-6">{{ w.code }}</span></div>
                  <div class="text-caption text-grey-7">{{ w.trade || '—' }} · {{ fmt(rateOf(w)) }} {{ summary.base }}/{{ $t('Day') }}</div>
                </div>
                <div class="wk-row__actions">
                  <q-btn dense round :flat="markOf(w.id)?.status !== 'present'" :unelevated="markOf(w.id)?.status === 'present'" size="sm" color="positive" icon="check" @click="mark(w, 'present')"><q-tooltip>{{ $t('Present') }}</q-tooltip></q-btn>
                  <q-btn dense round :flat="markOf(w.id)?.status !== 'half'" :unelevated="markOf(w.id)?.status === 'half'" size="sm" color="amber-8" icon="hourglass_bottom" @click="mark(w, 'half')"><q-tooltip>{{ $t('HalfDay') }}</q-tooltip></q-btn>
                  <q-btn dense round :flat="markOf(w.id)?.status !== 'absent'" :unelevated="markOf(w.id)?.status === 'absent'" size="sm" color="negative" icon="close" @click="mark(w, 'absent')"><q-tooltip>{{ $t('Absent') }}</q-tooltip></q-btn>
                  <q-btn dense round flat size="sm" color="indigo-7" icon="photo_camera" @click="pickPhoto(w)"><q-tooltip>{{ $t('AttachPhoto') }}</q-tooltip></q-btn>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </m-backgrounds>

    <input ref="fileInput" type="file" accept="image/*" capture="environment" style="display:none" @change="onPhotoPicked" />
  </q-page>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { Notify } from 'quasar'
import { api } from '@/boot/axios'
import { compressImage } from '@/utils/image'
import { useOfflineQueue } from '@/composables/useOfflineQueue'

const { pending, online, syncing, enqueue, flush, bind, refresh } = useOfflineQueue()

const projectOptions = ref([])
const projectId = ref(null)
const workDate = ref(new Date().toISOString().slice(0, 10))
const task = ref('')
const workers = ref([])
const records = ref([])
const summary = ref({ present: 0, absent: 0, wage_total: 0, base: 'AFN' })
const loading = ref(false)
const photos = reactive({})
const gps = ref(null)
const locating = ref(false)

function fmt (v) { return Number(v || 0).toLocaleString('en-US') }
function markOf (workerId) { return records.value.find(r => r.worker_id === workerId) }
function rateOf (w) { const m = markOf(w.id); return m ? m.day_rate : (w.default_wage || 0) }

async function loadProjects () {
  try { const { data } = await api.get('/projects'); projectOptions.value = (data || []).map(p => ({ label: p.name, value: p.id })); if (!projectId.value && projectOptions.value.length) { projectId.value = projectOptions.value[0].value; load() } } catch (_) {}
}
async function load () {
  if (!projectId.value) return
  loading.value = true
  try {
    const [w, a] = await Promise.all([
      api.get('/workers', { params: { project_id: projectId.value, active_only: true } }),
      api.get('/worker-attendances', { params: { project_id: projectId.value, date: workDate.value } }),
    ])
    workers.value = Array.isArray(w.data) ? w.data : []
    records.value = a.data.records || []
    summary.value = a.data.summary || summary.value
    workers.value.forEach(loadPhoto)
  } finally { loading.value = false }
}
async function loadPhoto (w) {
  if (photos[w.id] || !w.photo_mime?.startsWith('image/')) return
  try { const res = await api.get('/workers/' + w.id + '/photo', { responseType: 'blob' }); photos[w.id] = URL.createObjectURL(new Blob([res.data], { type: w.photo_mime })) } catch (_) {}
}

function captureGps () {
  if (!navigator.geolocation) { Notify.create({ type: 'warning', message: 'GPS not available' }); return }
  locating.value = true
  navigator.geolocation.getCurrentPosition(
    (pos) => { gps.value = { lat: pos.coords.latitude, lng: pos.coords.longitude }; locating.value = false; Notify.create({ type: 'positive', position: 'bottom', icon: 'my_location', message: 'Location captured' }) },
    () => { locating.value = false; Notify.create({ type: 'negative', message: 'Could not get location' }) },
    { enableHighAccuracy: true, timeout: 8000 }
  )
}

function baseRecord (w, status) {
  return {
    worker_id: w.id, project_id: projectId.value, work_date: workDate.value,
    status, task: task.value || null, day_rate: rateOf(w),
    gps_lat: gps.value?.lat ?? null, gps_lng: gps.value?.lng ?? null,
  }
}

async function mark (w, status) {
  const rec = baseRecord(w, status)
  if (!navigator.onLine) {
    enqueue(rec)
    // optimistic local update so the roster reflects the mark immediately
    upsertLocal(rec)
    Notify.create({ type: 'info', position: 'bottom', icon: 'cloud_off', message: 'Saved offline' })
    return
  }
  try {
    await api.post('/worker-attendances', rec)
    load()
  } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Failed' }) }
}

function upsertLocal (rec) {
  const i = records.value.findIndex(r => r.worker_id === rec.worker_id)
  if (i >= 0) records.value[i] = { ...records.value[i], ...rec }
  else records.value.push({ ...rec, id: 'q-' + rec.worker_id })
}

// Per-worker photo: mark present with the photo attached (online only).
const fileInput = ref(null)
let photoTarget = null
function pickPhoto (w) {
  if (!navigator.onLine) { Notify.create({ type: 'warning', message: 'Photos need a connection' }); return }
  photoTarget = w; fileInput.value?.click()
}
async function onPhotoPicked (e) {
  const file = e.target.files?.[0]; e.target.value = ''
  if (!file || !photoTarget) return
  const w = photoTarget; photoTarget = null
  try {
    const fd = new FormData()
    const rec = baseRecord(w, markOf(w.id)?.status || 'present')
    Object.entries(rec).forEach(([k, v]) => { if (v !== null && v !== '') fd.append(k, v) })
    fd.append('photo', await compressImage(file))
    await api.post('/worker-attendances', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    Notify.create({ type: 'positive', position: 'bottom', icon: 'photo_camera', message: 'Photo saved' })
    load()
  } catch (err) { Notify.create({ type: 'negative', message: err?.response?.data?.message || 'Failed' }) }
}

async function doFlush () {
  try { const r = await flush(); if (r.synced) { Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: r.synced + ' synced' }); load() } } catch (_) { Notify.create({ type: 'negative', message: 'Sync failed' }) }
}

onMounted(() => { bind(); refresh(); loadProjects(); if (navigator.onLine) flush() })
</script>

<style scoped>
.att-bar { display: flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 10px; font-size: 13px; color: #fff; }
.att-bar--on { background: linear-gradient(90deg, #0D9488, #14B8A6); }
.att-bar--off { background: linear-gradient(90deg, #B45309, #D97706); }
.wk-row { display: flex; align-items: center; gap: 10px; background: #fff; border: 1px solid #E7ECF3; border-inline-start: 4px solid #CBD5E1; border-radius: 12px; padding: 8px 12px; }
.wk-row--present { border-inline-start-color: #16A34A; }
.wk-row--half { border-inline-start-color: #D97706; }
.wk-row--absent { border-inline-start-color: #DC2626; }
.wk-row__info { flex: 1; min-width: 0; }
.wk-row__name { font-weight: 600; font-size: 13.5px; color: #0F172A; }
.wk-row__actions { display: flex; gap: 3px; }
@media (prefers-color-scheme: dark) {
  .wk-row { background: #1E293B; border-color: #334155; }
  .wk-row__name { color: #F1F5F9; }
}
</style>
