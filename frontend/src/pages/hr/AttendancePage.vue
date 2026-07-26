<template>
  <q-page>
    <m-backgrounds>
      <div class="row my_radio_less q-pa-sm">

        <!-- Page title + date (reference: "Take Attendance" / date top-right) -->
        <div class="col-12">
          <div class="ta-titlebar">
            <div class="ta-titlebar__t">{{ $t('TakeAttendance') }}</div>
            <dual-date :value="date" />
          </div>
        </div>

        <!-- Attendance Management bar with badges -->
        <div class="col-12 q-mt-sm">
          <div class="ta-mgmt">
            <span class="ta-mgmt__accent"></span>
            <q-icon name="event_available" size="20px" class="q-mr-sm text-primary" />
            <div class="ta-mgmt__title">{{ $t('AttendanceManagement') }}</div>
            <q-space />
            <span class="ta-badge ta-badge--dark"><q-icon name="today" size="13px" /> {{ $t('Day') }} {{ dayOfMonth }}/{{ daysInMonth }}</span>
            <span class="ta-badge ta-badge--soft"><q-icon name="how_to_reg" size="13px" /> {{ markedCount }}/{{ rows.length }} {{ $t('Marked') }}</span>
            <span class="ta-badge ta-badge--soft"><q-icon name="groups" size="13px" /> {{ rows.length }} {{ $t('Employees') }}</span>
          </div>
        </div>

        <!-- 4 info cards (Faculty/Class/Subject/Date in the reference) -->
        <div class="col-12 q-mt-sm">
          <div class="row q-col-gutter-sm">
            <div class="col-6 col-md-3">
              <div class="ta-info">
                <div class="ta-info__ic" style="--c:#175A8C"><q-icon name="store" size="19px" /></div>
                <div class="ta-info__body">
                  <div class="ta-info__cap">{{ $t('Branch') }}</div>
                  <div class="ta-info__val">{{ branchName }}</div>
                  <div class="ta-info__sub"><q-icon name="apartment" size="11px" /> {{ companyName }}</div>
                </div>
              </div>
            </div>
            <div class="col-6 col-md-3">
              <div class="ta-info">
                <div class="ta-info__ic" style="--c:#DC2626"><q-icon name="apartment" size="19px" /></div>
                <div class="ta-info__body">
                  <div class="ta-info__cap">{{ $t('Department') }}</div>
                  <q-select borderless dense v-model="departmentId" :options="departmentOptions" emit-value map-options clearable
                    :label="departmentId ? undefined : $t('AllDepartments')" class="ta-info__select" @update:model-value="load" />
                </div>
              </div>
            </div>
            <div class="col-6 col-md-3">
              <div class="ta-info">
                <div class="ta-info__ic" style="--c:#0D9488"><q-icon name="fingerprint" size="19px" /></div>
                <div class="ta-info__body">
                  <div class="ta-info__cap">{{ $t('Device') }}</div>
                  <div class="ta-info__val">{{ device ? device.name : $t('NoDevice') }}</div>
                  <div class="ta-info__sub">
                    <span v-if="device" class="ta-dot" :class="device.online ? 'ta-dot--on' : 'ta-dot--off'"></span>
                    {{ device ? (device.online ? $t('Online') : $t('Offline')) : $t('AddDeviceHint') }}
                  </div>
                </div>
              </div>
            </div>
            <div class="col-6 col-md-3">
              <div class="ta-info">
                <div class="ta-info__ic" style="--c:#D97706"><q-icon name="event" size="19px" /></div>
                <div class="ta-info__body">
                  <div class="ta-info__cap">{{ $t('Date') }}</div>
                  <shamsi-date v-model="date" color="primary" class="ta-info__date" @update:model-value="load" />
                  <div class="ta-info__sub"><q-icon name="groups" size="11px" /> {{ rows.length }} {{ $t('Employees') }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 3 progress cards -->
        <div class="col-12 q-mt-sm">
          <div class="row q-col-gutter-sm">
            <div class="col-12 col-md-4">
              <div class="ta-prog">
                <div class="ta-prog__cap"><q-icon name="insights" size="14px" /> {{ $t('MonthProgress') }}</div>
                <div class="ta-prog__val">{{ dayOfMonth }}/{{ daysInMonth }}</div>
                <div class="ta-prog__sub">{{ daysInMonth - dayOfMonth }} {{ $t('DaysRemaining') }}</div>
                <q-linear-progress rounded size="7px" :value="dayOfMonth / daysInMonth" color="primary" track-color="grey-3" class="q-mt-xs" />
              </div>
            </div>
            <div class="col-12 col-md-4">
              <div class="ta-prog">
                <div class="ta-prog__cap"><q-icon name="fact_check" size="14px" /> {{ $t('TodaysMarking') }}</div>
                <div class="ta-prog__val">{{ markedCount }}/{{ rows.length }}</div>
                <div class="ta-prog__sub">{{ unmarkedCount }} {{ $t('NotMarkedYet') }}</div>
                <q-linear-progress rounded size="7px" :value="rows.length ? markedCount / rows.length : 0" color="teal-7" track-color="grey-3" class="q-mt-xs" />
              </div>
            </div>
            <div class="col-12 col-md-4">
              <div class="ta-prog">
                <div class="ta-prog__cap"><q-icon name="wb_sunny" size="14px" /> {{ $t('CurrentDay') }}</div>
                <div class="ta-prog__val">{{ weekdayName }}</div>
                <div class="ta-prog__sub"><q-icon name="event" size="11px" /> <dual-date :value="date" /></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Device strip — smooth one-click device attendance -->
        <div class="col-12 q-mt-sm" v-if="$can('attendance-create')">
          <div class="ta-devices">
            <div class="ta-devices__head"><q-icon name="fingerprint" size="16px" class="q-mr-xs" />{{ $t('AttendanceDevices') }}</div>
            <div class="ta-devices__row">
              <div v-for="d in devices" :key="d.id" class="ta-dev" :class="{ 'ta-dev--current': device && device.id === d.id }" @click="device = d">
                <q-icon name="fingerprint" size="18px" :color="d.online ? 'teal-7' : 'grey-5'" />
                <div class="ta-dev__body">
                  <div class="ta-dev__name">{{ d.name }} <q-badge v-if="d.is_default" color="amber-7" text-color="white" :label="$t('Default')" /></div>
                  <div class="ta-dev__sub"><span class="ta-dot" :class="d.online ? 'ta-dot--on' : 'ta-dot--off'"></span>{{ d.online ? $t('Online') : $t('Offline') }}<span v-if="d.last_seen"> · {{ d.last_seen }}</span></div>
                </div>
              </div>
              <div v-if="!devices.length" class="text-caption text-grey-5 q-pa-sm">{{ $t('NoDevice') }} — <router-link to="/fingerprint" class="text-primary">{{ $t('FingerprintSettings') }}</router-link></div>
              <q-space />
              <q-btn unelevated color="teal-7" icon="sync" :label="$t('SyncFromDevice')" :loading="syncing" no-caps @click="syncDevice" :disable="!devices.length" />
            </div>
          </div>
        </div>

        <!-- Not-marked-yet banner (reference's yellow banner) -->
        <div class="col-12 q-mt-sm" v-if="unmarkedCount > 0">
          <div class="ta-banner"><q-icon name="error_outline" size="16px" class="q-mr-xs" />
            <span>{{ $t('YouHave') }} <b>{{ unmarkedCount }}</b> {{ $t('EmployeesNotMarked') }}</span>
          </div>
        </div>

        <!-- 5 mini stat cards -->
        <div class="col-12 q-mt-sm">
          <div class="row q-col-gutter-sm">
            <div class="col-6 col-sm-4 col-md" v-for="m in miniStats" :key="m.label">
              <div class="ta-mini">
                <q-icon :name="m.icon" size="17px" :style="`color:${m.color}`" />
                <div class="ta-mini__val" :style="`color:${m.color}`">{{ m.value }}</div>
                <div class="ta-mini__lbl">{{ $t(m.label) }}</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Attendance record table (exact reference layout) -->
        <div class="col-12 q-mt-sm">
          <q-card flat bordered class="ta-table-card">
            <div class="ta-table-head">
              <div class="ta-table-head__t">{{ $t('EmployeeAttendanceRecord') }} <q-badge color="red-1" text-color="red-8" :label="rows.length + ' ' + $t('Employees')" /></div>
              <q-space />
              <q-input dense borderless v-model="q" :placeholder="$t('Search')" debounce="300" class="ta-search" @update:model-value="load" clearable>
                <template #prepend><q-icon name="search" size="16px" /></template>
              </q-input>
              <span class="ta-count ta-count--p"><q-icon name="check_circle" size="13px" /> {{ summary.present }} {{ $t('Present') }}</span>
              <span class="ta-count ta-count--a"><q-icon name="cancel" size="13px" /> {{ summary.absent }} {{ $t('Absent') }}</span>
              <span class="ta-count ta-count--r"><q-icon name="timelapse" size="13px" /> {{ rate }}%</span>
            </div>

            <div v-if="loading" class="text-center q-py-xl"><q-spinner color="primary" size="2.5em" /></div>
            <div v-else-if="rows.length === 0" class="text-center text-grey-5 q-py-xl">{{ $t('NoRecordFound') }}</div>
            <template v-else>
              <div class="ta-grid ta-grid--head">
                <div>#</div><div>{{ $t('Code') }}</div><div>{{ $t('EmployeeName') }}</div>
                <div>{{ $t('FatherName') }}</div><div class="text-center">{{ $t('Attendance') }}</div><div>{{ $t('Statistics') }}</div>
              </div>
              <div v-for="(r, i) in rows" :key="r.employee_id" class="ta-grid ta-grid--row">
                <div class="ta-idx">{{ String(i + 1).padStart(2, '0') }}</div>
                <div class="ta-code">{{ r.code }}</div>
                <div class="ta-name">
                  <div class="ta-name__n">{{ r.name }}</div>
                  <div class="ta-name__d"><q-icon name="apartment" size="11px" /> {{ r.department || '—' }}
                    <span v-if="r.source === 'device'" class="ta-chip ta-chip--dev"><q-icon name="fingerprint" size="10px" /> {{ $t('Device') }}</span>
                    <span v-if="r.attachments_count" class="ta-chip ta-chip--att"><q-icon name="attach_file" size="10px" /> {{ r.attachments_count }}</span>
                  </div>
                </div>
                <div class="ta-father">{{ r.father_name || '—' }}</div>
                <div class="ta-att">
                  <q-toggle :model-value="r.status === 'present'" color="teal-6" dense
                    @update:model-value="v => r.status = v ? 'present' : 'absent'" />
                  <span class="ta-pill" :class="'ta-pill--' + r.status">
                    <q-icon :name="pillIcon(r.status)" size="12px" /> {{ $t(pillLabel(r.status)) }}
                  </span>
                  <q-btn dense flat round size="sm" icon="more_vert" color="grey-6">
                    <q-menu auto-close>
                      <q-list dense style="min-width:150px">
                        <q-item clickable @click="r.status = 'leave'"><q-item-section avatar style="min-width:30px"><q-icon name="beach_access" color="amber-8" size="16px" /></q-item-section><q-item-section>{{ $t('OnLeave') }}</q-item-section></q-item>
                        <q-item clickable @click="r.status = 'holiday'"><q-item-section avatar style="min-width:30px"><q-icon name="star" color="deep-purple-6" size="16px" /></q-item-section><q-item-section>{{ $t('Holiday') }}</q-item-section></q-item>
                        <q-separator />
                        <q-item clickable @click="openDetail(r)"><q-item-section avatar style="min-width:30px"><q-icon name="note_add" color="teal-8" size="16px" /></q-item-section><q-item-section>{{ $t('ReasonAttachment') }}</q-item-section></q-item>
                      </q-list>
                    </q-menu>
                  </q-btn>
                </div>
                <div class="ta-stats">
                  <div class="ta-stats__r"><span>{{ $t('Present') }}</span><b class="text-green-8">{{ r.mtd_present }}</b></div>
                  <div class="ta-stats__r"><span>{{ $t('Absent') }}</span><b class="text-red-7">{{ r.mtd_absent }}</b></div>
                  <div class="ta-stats__r"><span>%</span><q-badge color="teal-1" text-color="teal-9" :label="mtdRate(r) + '%'" /></div>
                </div>
              </div>
            </template>

            <!-- Save (reference's big red button, in our brand) -->
            <div class="ta-save" v-if="rows.length && $can('attendance-create')">
              <q-btn unelevated color="primary" icon="save" :loading="saving" no-caps size="md"
                :label="$t('SaveAttendance') + ' — ' + date" icon-right="arrow_forward" @click="save" />
            </div>
          </q-card>
        </div>
      </div>
    </m-backgrounds>

    <!-- Reason + attachment (absence justification, sick note, leave form…) -->
    <m-modal :showCM="detailDialog" @update:showCM="detailDialog = $event" card_style="width: 480px">
      <q-card class="bg-white" v-if="detail">
        <n-header icon="event_available" :subtitle="detail.code">{{ detail.name }}</n-header>
        <q-separator />
        <q-card-section class="row q-col-gutter-sm">
          <div class="col-12">
            <q-btn-toggle v-model="detail.status" spread dense unelevated no-caps toggle-color="primary" color="grey-2" text-color="grey-8"
              :options="statusOptionsLabelled" />
          </div>
          <div class="col-6"><q-input outlined dense color="primary" v-model="detail.check_in" mask="##:##" :label="$t('CheckIn')" placeholder="08:00"><template #prepend><q-icon name="login" /></template></q-input></div>
          <div class="col-6"><q-input outlined dense color="primary" v-model="detail.check_out" mask="##:##" :label="$t('CheckOut')" placeholder="17:00"><template #prepend><q-icon name="logout" /></template></q-input></div>
          <div class="col-12"><q-input outlined dense color="primary" type="textarea" autogrow v-model="detail.note" :label="$t('Reason')" :placeholder="$t('ReasonPlaceholder')" /></div>
          <div class="col-12">
            <div class="text-caption text-weight-bold text-grey-7 q-mb-xs">{{ $t('Attachment') }}</div>
            <div v-if="detail.record_id">
              <attach-box type="attendance-record" :id="detail.record_id" kind="file" :label="$t('AttachDocument')" @changed="refreshDetailCount" />
            </div>
            <div v-else class="att-attach-hint"><q-icon name="info" size="15px" class="q-mr-xs" />{{ $t('SaveToAttach') }}</div>
          </div>
        </q-card-section>
        <q-separator />
        <q-card-actions align="right" class="q-pa-sm">
          <q-btn flat :label="$t('Close')" color="grey-7" @click="detailDialog = false" />
          <q-btn unelevated color="primary" icon="save" :label="detail.record_id ? $t('Save') : $t('SaveToAttach')" :loading="savingDetail" @click="saveDetail" />
        </q-card-actions>
      </q-card>
    </m-modal>
  </q-page>
</template>

<script setup>
import { ref, computed, watch, getCurrentInstance, onMounted } from 'vue'
import { Notify } from 'quasar'
import { api } from '@/boot/axios'
import { useAuthStore } from '@/stores/auth'
import { shamsiDate } from '@/utils/date'

const { proxy } = getCurrentInstance()
const auth = useAuthStore()

const today = new Date().toISOString().slice(0, 10)
const date = ref(today)
// Past days are fine (catching up a missed sheet); future days are not.
watch(date, (v) => {
  if (v && v > today) {
    date.value = today
    Notify.create({ type: 'warning', position: 'bottom', icon: 'event_busy', message: proxy.$t('NoFutureAttendance') })
  }
})
const departmentId = ref(null)
const q = ref('')
const rows = ref([])
const summary = ref({ total: 0, present: 0, absent: 0, leave: 0, holiday: 0 })
const departmentOptions = ref([])
const loading = ref(false)
const saving = ref(false)
const syncing = ref(false)
const devices = ref([])
const device = ref(null)

const detailDialog = ref(false)
const detail = ref(null)
const savingDetail = ref(false)
const statusOptionsLabelled = computed(() => [
  { label: proxy.$t('Present'), value: 'present' },
  { label: proxy.$t('Absent'), value: 'absent' },
  { label: proxy.$t('OnLeave'), value: 'leave' },
  { label: proxy.$t('Holiday'), value: 'holiday' },
])

const companyName = computed(() => auth.currentCompany?.name_en || 'Aria Herat')
const branchName = computed(() => {
  const saved = localStorage.getItem('active_branch')
  if (!saved || saved === 'all') return proxy.$t('AllBranches')
  return (auth.branches || []).find(b => String(b.id) === saved)?.name || proxy.$t('Branch')
})

const dayOfMonth = computed(() => Number(date.value.slice(8, 10)))
const daysInMonth = computed(() => {
  const [y, m] = date.value.split('-').map(Number)
  return new Date(y, m, 0).getDate()
})
const weekdayName = computed(() => new Date(date.value).toLocaleDateString(undefined, { weekday: 'long' }))
const markedCount = computed(() => rows.value.filter(r => r.record_id).length)
const unmarkedCount = computed(() => rows.value.length - markedCount.value)

const rate = computed(() => {
  const t = summary.value.total || 0
  if (!t) return 0
  return Math.round(((summary.value.present + summary.value.leave) / t) * 100)
})
const miniStats = computed(() => [
  { label: 'Present', icon: 'how_to_reg', value: summary.value.present, color: '#16A34A' },
  { label: 'Absent', icon: 'person_off', value: summary.value.absent, color: '#DC2626' },
  { label: 'OnLeave', icon: 'beach_access', value: summary.value.leave, color: '#D97706' },
  { label: 'Holiday', icon: 'star', value: summary.value.holiday, color: '#7C3AED' },
  { label: 'AttendanceRate', icon: 'timelapse', value: rate.value + '%', color: '#175A8C' },
])

function pillIcon (s) { return { present: 'check_circle', absent: 'cancel', leave: 'beach_access', holiday: 'star' }[s] || 'help' }
function pillLabel (s) { return { present: 'Present', absent: 'Absent', leave: 'OnLeave', holiday: 'Holiday' }[s] || s }
function mtdRate (r) {
  const t = r.mtd_present + r.mtd_absent + r.mtd_leave
  return t ? Math.round((r.mtd_present / t) * 100) : 0
}

async function loadDepartments () {
  try { const { data } = await api.get('/departments'); departmentOptions.value = (data || []).map(d => ({ label: d.name, value: d.id })) } catch (_) {}
}
async function loadDevices () {
  try {
    const { data } = await api.get('/fingerprint/devices')
    devices.value = (Array.isArray(data) ? data : (data.data ?? [])).map(d => ({
      id: d.id, name: d.name, brand: d.brand, is_default: !!d.is_default,
      online: (d.status || d.state || 'online') !== 'offline', last_seen: d.last_seen_human || d.last_seen || '',
    }))
    device.value = devices.value.find(d => d.is_default) || devices.value[0] || null
  } catch (_) {}
}
async function load () {
  loading.value = true
  try {
    const { data } = await api.get('/attendance', { params: { date: date.value, department_id: departmentId.value || undefined, q: q.value || undefined } })
    rows.value = data.rows || []
    summary.value = data.summary || summary.value
  } finally { loading.value = false }
}
async function save () {
  saving.value = true
  try {
    await api.post('/attendance', { date: date.value, rows: rows.value.map(r => ({ employee_id: r.employee_id, status: r.status, note: r.note || '' })) })
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: proxy.$t('Saved') })
    load()
  } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Save failed' }) } finally { saving.value = false }
}
async function syncDevice () {
  syncing.value = true
  try {
    const { data } = await api.post('/attendance/sync-device', { date: date.value, device_id: device.value?.id })
    Notify.create({ type: 'positive', position: 'bottom', icon: 'fingerprint', message: `${data.device}: ${data.present} ${proxy.$t('Present')} · ${data.absent} ${proxy.$t('Absent')}` })
    load()
  } catch (e) { Notify.create({ type: e?.response?.status === 422 ? 'warning' : 'negative', message: e?.response?.data?.message || 'Sync failed' }) } finally { syncing.value = false }
}

function openDetail (r) { detail.value = { ...r }; detailDialog.value = true }
async function saveDetail () {
  savingDetail.value = true
  try {
    const { data } = await api.post('/attendance/record', {
      employee_id: detail.value.employee_id, date: date.value, status: detail.value.status,
      note: detail.value.note || '', check_in: detail.value.check_in || '', check_out: detail.value.check_out || '',
    })
    detail.value.record_id = data.id
    const row = rows.value.find(x => x.employee_id === detail.value.employee_id)
    if (row) Object.assign(row, { record_id: data.id, status: detail.value.status, note: detail.value.note, check_in: detail.value.check_in, check_out: detail.value.check_out })
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: proxy.$t('Saved') })
  } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Save failed' }) } finally { savingDetail.value = false }
}
function refreshDetailCount (n) {
  const c = Array.isArray(n) ? n.length : (typeof n === 'number' ? n : (detail.value.attachments_count || 0) + 1)
  detail.value.attachments_count = c
  const row = rows.value.find(x => x.employee_id === detail.value.employee_id)
  if (row) row.attachments_count = c
}

onMounted(() => { loadDepartments(); loadDevices(); load() })
</script>

<style scoped>
/* ── Title ── */
.ta-titlebar { display: flex; align-items: baseline; justify-content: space-between; background: #fff; border: 1px solid #E7ECF3; border-radius: 12px; padding: 12px 16px; }
.ta-titlebar__t { font-size: 18px; font-weight: 800; color: #0F172A; }

/* ── Management bar ── */
.ta-mgmt { display: flex; align-items: center; background: #fff; border: 1px solid #E7ECF3; border-radius: 12px; padding: 10px 14px; position: relative; overflow: hidden; gap: 6px; }
.ta-mgmt__accent { position: absolute; inset-inline-start: 0; top: 0; bottom: 0; width: 4px; background: var(--q-primary); }
.ta-mgmt__title { font-size: 14.5px; font-weight: 800; color: #0F172A; }
.ta-badge { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 700; border-radius: 999px; padding: 3px 10px; }
.ta-badge--dark { background: var(--q-primary); color: #fff; }
.ta-badge--soft { background: #F1F5F9; color: #334155; }

/* ── Info cards ── */
.ta-info { display: flex; gap: 10px; background: #fff; border: 1px solid #E7ECF3; border-radius: 12px; padding: 12px; height: 100%; }
.ta-info__ic { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; background: color-mix(in srgb, var(--c) 12%, #fff); color: var(--c); flex: 0 0 auto; }
.ta-info__body { min-width: 0; flex: 1; }
.ta-info__cap { font-size: 9.5px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: #94A3B8; }
.ta-info__val { font-weight: 800; font-size: 14px; color: #0F172A; line-height: 1.25; }
.ta-info__sub { font-size: 10.5px; color: #64748B; margin-top: 2px; display: flex; align-items: center; gap: 3px; }
.ta-info__select :deep(.q-field__control) { min-height: 28px; }
.ta-info__select :deep(.q-field__native) { font-weight: 800; font-size: 14px; color: #0F172A; padding: 0; }
.ta-info__date :deep(.q-field__control) { min-height: 28px; padding: 0; }
.ta-info__date :deep(.q-field__native) { font-weight: 800; font-size: 14px; color: #0F172A; }
.ta-info__date :deep(.q-field__bottom) { display: none; }
.ta-info__date :deep(.q-field--outlined .q-field__control:before) { border: none; }

/* ── Progress cards ── */
.ta-prog { background: #fff; border: 1px solid #E7ECF3; border-radius: 12px; padding: 12px 14px; height: 100%; }
.ta-prog__cap { font-size: 11px; font-weight: 700; color: #475569; display: flex; align-items: center; gap: 4px; }
.ta-prog__val { font-size: 20px; font-weight: 800; color: #0F172A; margin-top: 2px; }
.ta-prog__sub { font-size: 10.5px; color: #94A3B8; }

/* ── Devices strip ── */
.ta-devices { background: #fff; border: 1px solid #E7ECF3; border-radius: 12px; padding: 10px 14px; }
.ta-devices__head { font-size: 12px; font-weight: 800; color: #0F172A; display: flex; align-items: center; margin-bottom: 6px; }
.ta-devices__row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.ta-dev { display: flex; align-items: center; gap: 8px; border: 1px solid #E2E8F0; border-radius: 10px; padding: 6px 10px; cursor: pointer; transition: all .15s; }
.ta-dev:hover { border-color: #99F6E4; background: #F0FDFA; }
.ta-dev--current { border-color: #0D9488; background: #F0FDFA; }
.ta-dev__name { font-size: 12px; font-weight: 700; color: #0F172A; }
.ta-dev__sub { font-size: 10px; color: #64748B; display: flex; align-items: center; gap: 4px; }
.ta-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; }
.ta-dot--on { background: #16A34A; box-shadow: 0 0 0 3px #DCFCE7; }
.ta-dot--off { background: #94A3B8; }

/* ── Banner ── */
.ta-banner { display: flex; align-items: center; background: #FFFBEB; border: 1px solid #FCD34D; color: #92400E; border-radius: 10px; padding: 8px 12px; font-size: 12px; }

/* ── Mini stats ── */
.ta-mini { background: #fff; border: 1px solid #E7ECF3; border-radius: 12px; padding: 10px; text-align: center; height: 100%; }
.ta-mini__val { font-size: 18px; font-weight: 800; line-height: 1.1; }
.ta-mini__lbl { font-size: 9.5px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #94A3B8; margin-top: 1px; }

/* ── Table ── */
.ta-table-card { border-radius: 12px; overflow: hidden; }
.ta-table-head { display: flex; align-items: center; gap: 8px; padding: 10px 14px; border-bottom: 1px solid #EEF2F7; flex-wrap: wrap; }
.ta-table-head__t { font-weight: 800; font-size: 13.5px; color: #0F172A; display: flex; align-items: center; gap: 6px; }
.ta-search { max-width: 180px; background: #F8FAFC; border-radius: 8px; padding: 0 8px; }
.ta-count { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 700; border-radius: 999px; padding: 3px 10px; }
.ta-count--p { background: #DCFCE7; color: #15803D; }
.ta-count--a { background: #FEE2E2; color: #B91C1C; }
.ta-count--r { background: #E0EDF7; color: #175A8C; }

.ta-grid { display: grid; grid-template-columns: 44px 110px 1.4fr 1fr 220px 150px; gap: 8px; align-items: center; padding: 8px 14px; }
.ta-grid--head { background: #F8FAFC; font-size: 10px; font-weight: 800; letter-spacing: .07em; text-transform: uppercase; color: #64748B; border-bottom: 1px solid #EEF2F7; }
.ta-grid--row { border-bottom: 1px solid #F1F5F9; }
.ta-grid--row:hover { background: #F8FAFC; }
.ta-idx { font-size: 12px; color: #64748B; font-weight: 600; }
.ta-code { font-size: 12px; font-weight: 800; color: var(--q-primary); }
.ta-name__n { font-weight: 700; font-size: 13.5px; color: #0F172A; }
.ta-name__d { font-size: 11px; color: #64748B; display: flex; align-items: center; gap: 4px; flex-wrap: wrap; }
.ta-father { font-size: 12.5px; color: #334155; }
.ta-att { display: flex; align-items: center; justify-content: center; gap: 4px; }
.ta-pill { display: inline-flex; align-items: center; gap: 3px; font-size: 10.5px; font-weight: 800; border-radius: 999px; padding: 3px 9px; min-width: 76px; justify-content: center; }
.ta-pill--present { background: #CCFBF1; color: #0F766E; }
.ta-pill--absent { background: #FEE2E2; color: #B91C1C; }
.ta-pill--leave { background: #FEF3C7; color: #B45309; }
.ta-pill--holiday { background: #EDE9FE; color: #6D28D9; }
.ta-chip { display: inline-flex; align-items: center; gap: 2px; font-size: 9.5px; font-weight: 700; padding: 1px 6px; border-radius: 999px; background: #F1F5F9; color: #475569; }
.ta-chip--dev { background: #CCFBF1; color: #0D9488; }
.ta-chip--att { background: #E0E7FF; color: #4338CA; }
.ta-stats { background: #F8FAFC; border: 1px solid #EEF2F7; border-radius: 9px; padding: 5px 9px; }
.ta-stats__r { display: flex; align-items: center; justify-content: space-between; font-size: 10.5px; color: #64748B; line-height: 1.5; }
.ta-stats__r b { font-size: 12px; }
.ta-save { display: flex; justify-content: flex-end; padding: 14px; border-top: 1px solid #EEF2F7; }
.att-attach-hint { display: flex; align-items: center; font-size: 12px; color: #94A3B8; background: #F8FAFC; border: 1px dashed #CBD5E1; border-radius: 10px; padding: 10px 12px; }

@media (max-width: 900px) {
  .ta-grid { grid-template-columns: 30px 1.5fr 170px; }
  .ta-grid--head div:nth-child(2), .ta-grid--head div:nth-child(4), .ta-grid--head div:nth-child(6),
  .ta-grid--row .ta-code, .ta-grid--row .ta-father, .ta-grid--row .ta-stats { display: none; }
}
</style>
