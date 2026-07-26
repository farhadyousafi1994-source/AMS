<template>
  <q-page>
    <m-backgrounds>
      <div class="row my_radio_less q-pa-sm">
        <!-- Hero -->
        <div class="col-12">
          <div class="hse-hero">
            <div class="hse-hero__bar">
              <div class="hse-hero__head">
                <div class="hse-hero__title">
                  <div class="hse-hero__icon"><q-icon name="health_and_safety" size="26px" /></div>
                  <div>
                    <div class="hse-hero__name">{{ $t('SafetyIncidents') }}</div>
                    <div class="hse-hero__meta">
                      <span class="hse-hero__pill"><q-icon name="summarize" size="13px" /> {{ s.count }} {{ $t('Total') }}</span>
                      <span class="hse-hero__pill" v-if="s.open"><q-icon name="error_outline" size="13px" /> {{ s.open }} {{ $t('Open') }}</span>
                      <span class="hse-hero__pill hse-hero__pill--danger" v-if="s.critical_open"><q-icon name="priority_high" size="13px" /> {{ s.critical_open }} {{ $t('Critical') }}</span>
                    </div>
                  </div>
                </div>
                <div class="q-gutter-xs row items-center">
                  <q-btn flat dense icon="print" color="white" :label="$t('Register')" @click="printRegister" />
                  <progress-btn color="white" text-color="red-8" icon="add" v-if="$can('incident-create')" @click="openCreate">{{ $t('AddNew') }}</progress-btn>
                </div>
              </div>
            </div>
            <div class="row q-col-gutter-sm hse-hero__stats">
              <div class="col-6 col-md-3" v-for="k in heroStats" :key="k.label">
                <div class="kpi-tile"><q-icon :name="k.icon" size="20px" class="kpi-tile__icon" :style="`color:${k.color}`" /><div class="kpi-tile__val">{{ k.value }}</div><div class="kpi-tile__lbl">{{ $t(k.label) }}</div></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Filters -->
        <div class="col-12 q-mt-md">
          <div class="row q-col-gutter-sm items-center">
            <div class="col-6 col-sm-3"><q-select outlined dense color="primary" v-model="projectFilter" :options="projectOptions" emit-value map-options clearable :label="$t('Project')" @update:model-value="load" /></div>
            <div class="col-6 col-sm-3"><q-select outlined dense color="primary" v-model="typeFilter" :options="typeOptions" emit-value map-options clearable :label="$t('Type')" @update:model-value="load" /></div>
            <div class="col-6 col-sm-3"><q-select outlined dense color="primary" v-model="statusFilter" :options="statusOptions" emit-value map-options clearable :label="$t('Status')" @update:model-value="load" /></div>
            <div class="col-6 col-sm-3"><q-input outlined dense color="primary" v-model="tableFilter" :placeholder="$t('Search')" clearable><template #prepend><q-icon name="search" color="primary" /></template></q-input></div>
          </div>
        </div>

        <div class="col-12">
          <n-table config-key="page.incidents" :loading="loading" :data="rows" :columns="columns" v-model:filter="tableFilter"
            :can_show="'incident-show'" info-icon="visibility" :noInfoDialog="true"
            :can_delete="'incident-delete'" :noEdit="true" @info="openDetail" @del="remove">
            <template v-slot:body-cell-type="props">
              <q-td :props="props" class="text-center"><q-chip dense size="sm" :color="typeColor(props.row.type)" text-color="white">{{ $t(typeKey(props.row.type)) }}</q-chip></q-td>
            </template>
            <template v-slot:body-cell-severity="props">
              <q-td :props="props" class="text-center"><q-chip dense size="sm" :color="sevColor(props.row.severity)" text-color="white">{{ $t(sevKey(props.row.severity)) }}</q-chip></q-td>
            </template>
            <template v-slot:body-cell-status="props">
              <q-td :props="props" class="text-center"><q-chip dense size="sm" :color="statusColor(props.row.status)" text-color="white">{{ $t(statusKey(props.row.status)) }}</q-chip></q-td>
            </template>
          </n-table>
        </div>
      </div>
    </m-backgrounds>

    <!-- Create / edit -->
    <m-modal :showCM="dialog" @update:showCM="dialog = $event" card_style="width: 640px">
      <q-card class="bg-white">
        <n-header icon="health_and_safety">{{ form.id ? $t('Edit') : $t('LogIncident') }}</n-header>
        <q-separator />
        <q-form @submit="save">
          <q-card-section class="row q-col-gutter-sm">
            <div class="col-12 col-sm-8"><n-name :name="form.title" @update:name="form.title = $event" icon="title" :label="$t('Title')" /></div>
            <div class="col-12 col-sm-4"><q-select outlined dense color="primary" v-model="form.project_id" :options="projectOptions" emit-value map-options clearable :label="$t('Project')" /></div>
            <div class="col-6 col-sm-4"><q-select outlined dense color="primary" v-model="form.type" :options="typeOptions" emit-value map-options :label="$t('Type')" /></div>
            <div class="col-6 col-sm-4"><q-select outlined dense color="primary" v-model="form.severity" :options="sevOptions" emit-value map-options :label="$t('Severity')" /></div>
            <div class="col-6 col-sm-4"><q-select outlined dense color="primary" v-model="form.status" :options="statusOptions" emit-value map-options :label="$t('Status')" /></div>
            <div class="col-6 col-sm-4"><shamsi-date v-model="form.incident_date" color="primary" :label="$t('Date')" /></div>
            <div class="col-6 col-sm-4"><q-input outlined dense color="primary" v-model="form.incident_time" :label="$t('Time')" mask="##:##" placeholder="14:30" /></div>
            <div class="col-12 col-sm-4"><n-name :name="form.location" @update:name="form.location = $event" icon="place" :label="$t('Location')" :rules="[]" /></div>
            <div class="col-6 col-sm-4"><q-input outlined dense color="primary" type="number" v-model.number="form.injured_count" :label="$t('InjuredCount')" min="0" /></div>
            <div class="col-6 col-sm-4"><q-input outlined dense color="primary" type="number" v-model.number="form.lost_time_days" :label="$t('LostTimeDays')" min="0" /></div>
            <div class="col-12 col-sm-4"><n-name :name="form.people_involved" @update:name="form.people_involved = $event" icon="group" :label="$t('PeopleInvolved')" :rules="[]" /></div>
            <div class="col-12"><q-input outlined dense color="primary" type="textarea" autogrow v-model="form.description" :label="$t('Description')" /></div>
            <div class="col-12"><q-input outlined dense color="primary" type="textarea" autogrow v-model="form.immediate_action" :label="$t('ImmediateAction')" /></div>
          </q-card-section>
          <q-separator />
          <n-submit :submitting="saving" :label="$t('Save')" />
        </q-form>
      </q-card>
    </m-modal>

    <!-- Detail + close -->
    <m-modal :showCM="detailDialog" @update:showCM="detailDialog = $event" card_style="width: 660px">
      <q-card class="bg-white" v-if="active">
        <n-header icon="health_and_safety" :subtitle="active.code">{{ active.title }}</n-header>
        <q-separator />
        <q-card-section class="q-pb-none">
          <div class="row q-col-gutter-sm">
            <div class="col-6 col-sm-3"><stat-card dense icon="category" :label="$t('Type')" :value="$t(typeKey(active.type))" :color="typeHex(active.type)" :tint="typeTint(active.type)" /></div>
            <div class="col-6 col-sm-3"><stat-card dense icon="warning" :label="$t('Severity')" :value="$t(sevKey(active.severity))" :color="sevHex(active.severity)" :tint="sevTint(active.severity)" /></div>
            <div class="col-6 col-sm-3"><stat-card dense icon="healing" :label="$t('InjuredCount')" :value="active.injured_count" color="#DC2626" tint="#FEE2E2" /></div>
            <div class="col-6 col-sm-3"><stat-card dense icon="flag" :label="$t('Status')" :value="$t(statusKey(active.status))" :color="statusHex(active.status)" :tint="statusTint(active.status)" /></div>
          </div>
        </q-card-section>
        <q-card-section>
          <q-markup-table flat bordered dense class="my_radio_less">
            <tbody>
              <tr><td class="text-grey-7">{{ $t('Project') }}</td><td class="text-weight-medium">{{ active.project?.name || $t('CompanyWide') }}</td></tr>
              <tr><td class="text-grey-7">{{ $t('Date') }}</td><td>{{ active.incident_date }}<span v-if="active.incident_time"> · {{ active.incident_time }}</span></td></tr>
              <tr v-if="active.location"><td class="text-grey-7">{{ $t('Location') }}</td><td>{{ active.location }}</td></tr>
              <tr v-if="active.people_involved"><td class="text-grey-7">{{ $t('PeopleInvolved') }}</td><td>{{ active.people_involved }}</td></tr>
              <tr v-if="active.lost_time_days"><td class="text-grey-7">{{ $t('LostTimeDays') }}</td><td>{{ active.lost_time_days }}</td></tr>
              <tr><td class="text-grey-7">{{ $t('ReportedBy') }}</td><td>{{ active.reported_by_name || active.reporter?.name || '—' }}</td></tr>
              <tr v-if="active.description"><td class="text-grey-7">{{ $t('Description') }}</td><td>{{ active.description }}</td></tr>
              <tr v-if="active.immediate_action"><td class="text-grey-7">{{ $t('ImmediateAction') }}</td><td>{{ active.immediate_action }}</td></tr>
              <tr v-if="active.corrective_action"><td class="text-grey-7">{{ $t('CorrectiveAction') }}</td><td>{{ active.corrective_action }}</td></tr>
              <tr v-if="active.closure_note"><td class="text-grey-7">{{ $t('ClosureNote') }}</td><td>{{ active.closure_note }}</td></tr>
            </tbody>
          </q-markup-table>
        </q-card-section>
        <q-card-section class="q-pt-none">
          <attach-box type="safety-incident" :id="active.id" kind="document" :label="$t('Attachments')" icon="attach_file" accept="image/*,application/pdf" />
        </q-card-section>
        <q-separator />
        <q-card-actions align="right" class="q-pa-sm">
          <q-btn v-if="active.status !== 'closed' && $can('incident-close')" unelevated dense color="positive" icon="task_alt" :label="$t('CloseIncident')" @click="openClose" />
          <q-btn v-if="active.status === 'closed' && $can('incident-close')" outline dense color="orange-8" icon="restart_alt" :label="$t('Reopen')" @click="reopen" />
          <q-btn flat :label="$t('Close')" color="grey-7" @click="detailDialog = false" />
        </q-card-actions>
      </q-card>
    </m-modal>

    <!-- Close-out -->
    <m-modal :showCM="closeDialog" @update:showCM="closeDialog = $event" card_style="width: 520px">
      <q-card class="bg-white" v-if="active">
        <n-header icon="task_alt" :subtitle="active.code">{{ $t('CloseIncident') }}</n-header>
        <q-separator />
        <q-form @submit="doClose">
          <q-card-section class="row q-col-gutter-sm">
            <div class="col-12"><q-input outlined dense color="primary" type="textarea" autogrow v-model="closeForm.corrective_action" :label="$t('CorrectiveAction')" /></div>
            <div class="col-6"><q-input outlined dense color="primary" type="number" v-model.number="closeForm.lost_time_days" :label="$t('LostTimeDays')" min="0" /></div>
            <div class="col-12"><q-input outlined dense color="primary" v-model="closeForm.closure_note" :label="$t('ClosureNote')" /></div>
          </q-card-section>
          <q-separator />
          <n-submit :submitting="saving" :label="$t('CloseIncident')" />
        </q-form>
      </q-card>
    </m-modal>
  </q-page>
</template>

<script setup>
import { ref, reactive, computed, onMounted, getCurrentInstance } from 'vue'
import { Notify } from 'quasar'
import { offlineApi as api } from '@/services/offlineApi'

const { proxy } = getCurrentInstance()

const rows = ref([])
const s = ref({ count: 0, open: 0, critical_open: 0, closed: 0, this_month: 0, lost_time_days: 0, injured: 0 })
const loading = ref(false)
const saving = ref(false)
const dialog = ref(false)
const detailDialog = ref(false)
const closeDialog = ref(false)
const active = ref(null)
const tableFilter = ref('')
const projectFilter = ref(null)
const typeFilter = ref(null)
const statusFilter = ref(null)
const projectOptions = ref([])

const typeOptions = [{ label: 'Hazard', value: 'hazard' }, { label: 'Near miss', value: 'near_miss' }, { label: 'Incident', value: 'incident' }, { label: 'Accident', value: 'accident' }]
const sevOptions = [{ label: 'Low', value: 'low' }, { label: 'Medium', value: 'medium' }, { label: 'High', value: 'high' }, { label: 'Critical', value: 'critical' }]
const statusOptions = [{ label: 'Open', value: 'open' }, { label: 'Investigating', value: 'investigating' }, { label: 'Action pending', value: 'action_pending' }, { label: 'Closed', value: 'closed' }]

const columns = [
  { name: 'code', label: 'Code', field: 'code', align: 'left', sortable: true },
  { name: 'title', label: 'Title', field: 'title', align: 'left' },
  { name: 'project', label: 'Project', field: r => r.project?.name, align: 'left' },
  { name: 'type', label: 'Type', field: 'type', align: 'center' },
  { name: 'severity', label: 'Severity', field: 'severity', align: 'center', sortable: true },
  { name: 'incident_date', label: 'Date', field: 'incident_date', align: 'left', sortable: true },
  { name: 'status', label: 'Status', field: 'status', align: 'center', sortable: true },
  { name: 'actions', label: 'Actions', field: 'actions', align: 'right' },
]

function typeKey (t) { return { hazard: 'Hazard', near_miss: 'NearMiss', incident: 'Incident', accident: 'Accident' }[t] ?? 'Incident' }
function typeColor (t) { return { hazard: 'amber-8', near_miss: 'blue-7', incident: 'orange-8', accident: 'red-8' }[t] ?? 'grey' }
function typeHex (t) { return { hazard: '#D97706', near_miss: '#2563EB', incident: '#EA580C', accident: '#B91C1C' }[t] ?? '#64748B' }
function typeTint (t) { return { hazard: '#FEF3C7', near_miss: '#DBEAFE', incident: '#FFEDD5', accident: '#FEE2E2' }[t] ?? '#F1F5F9' }
function sevKey (v) { return { low: 'Low', medium: 'Medium', high: 'High', critical: 'Critical' }[v] ?? 'Low' }
function sevColor (v) { return { low: 'blue-grey-5', medium: 'amber-8', high: 'deep-orange-8', critical: 'red-9' }[v] ?? 'grey' }
function sevHex (v) { return { low: '#64748B', medium: '#D97706', high: '#EA580C', critical: '#B91C1C' }[v] ?? '#64748B' }
function sevTint (v) { return { low: '#F1F5F9', medium: '#FEF3C7', high: '#FFEDD5', critical: '#FEE2E2' }[v] ?? '#F1F5F9' }
function statusKey (v) { return { open: 'Open', investigating: 'Investigating', action_pending: 'ActionPending', closed: 'Closed' }[v] ?? 'Open' }
function statusColor (v) { return { open: 'red-7', investigating: 'amber-8', action_pending: 'blue-7', closed: 'green-7' }[v] ?? 'grey' }
function statusHex (v) { return { open: '#DC2626', investigating: '#D97706', action_pending: '#2563EB', closed: '#16A34A' }[v] ?? '#64748B' }
function statusTint (v) { return { open: '#FEE2E2', investigating: '#FEF3C7', action_pending: '#DBEAFE', closed: '#DCFCE7' }[v] ?? '#F1F5F9' }

const tr = (k) => (proxy?.$t ? proxy.$t(k) : k)
const heroStats = computed(() => [
  { label: 'OpenCases', value: s.value.open, icon: 'error_outline', color: '#DC2626' },
  { label: 'CriticalOpen', value: s.value.critical_open, icon: 'priority_high', color: '#B91C1C' },
  { label: 'ThisMonth', value: s.value.this_month, icon: 'calendar_month', color: '#2563EB' },
  { label: 'LostTimeDays', value: s.value.lost_time_days + ' ' + tr('Days'), icon: 'healing', color: '#D97706' },
])

const blank = () => ({ id: null, project_id: null, title: '', type: 'incident', severity: 'medium', status: 'open', incident_date: new Date().toISOString().slice(0, 10), incident_time: '', location: '', injured_count: 0, lost_time_days: 0, people_involved: '', description: '', immediate_action: '' })
const form = reactive(blank())
const closeForm = reactive({ corrective_action: '', lost_time_days: 0, closure_note: '' })

async function load () {
  loading.value = true
  try {
    const params = {}
    if (projectFilter.value) params.project_id = projectFilter.value
    if (typeFilter.value) params.type = typeFilter.value
    if (statusFilter.value) params.status = statusFilter.value
    const { data } = await api.get('/safety-incidents', { params })
    rows.value = data.incidents || (Array.isArray(data) ? data : [])
    s.value = data.summary || s.value
  } finally { loading.value = false }
}
async function loadProjects () { try { const { data } = await api.get('/projects'); projectOptions.value = (data || []).map(x => ({ label: x.name, value: x.id })) } catch (_) {} }

function openCreate () { Object.assign(form, blank()); dialog.value = true }
async function save () {
  saving.value = true
  try {
    await api.post('/safety-incidents', form)
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: tr('Saved') })
    dialog.value = false; load()
  } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Save failed' }) } finally { saving.value = false }
}
function remove (id) { proxy.$delete('safety-incidents/' + id, load) }

function openDetail (id, row) { active.value = row || rows.value.find(x => x.id === id); detailDialog.value = true }

function openClose () { closeForm.corrective_action = active.value.corrective_action || ''; closeForm.lost_time_days = active.value.lost_time_days || 0; closeForm.closure_note = ''; closeDialog.value = true }
async function doClose () {
  saving.value = true
  try {
    await api.put('/safety-incidents/' + active.value.id + '/close', closeForm)
    Notify.create({ type: 'positive', position: 'bottom', message: tr('IncidentClosed') })
    closeDialog.value = false; detailDialog.value = false; load()
  } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Failed' }) } finally { saving.value = false }
}
async function reopen () {
  try { await api.put('/safety-incidents/' + active.value.id + '/reopen'); Notify.create({ type: 'positive', position: 'bottom', message: tr('Reopened') }); detailDialog.value = false; load() }
  catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Failed' }) }
}

function printRegister () {
  const esc = x => String(x ?? '—').replace(/</g, '&lt;')
  const body = rows.value.map(r => `<tr><td>${esc(r.code)}</td><td>${esc(r.title)}</td><td>${esc(r.project?.name)}</td><td>${r.type}</td><td>${r.severity}</td><td>${esc(r.incident_date)}</td><td>${r.status}</td></tr>`).join('')
  const html = `<!DOCTYPE html><html dir="rtl"><head><meta charset="utf-8"><title>Safety Incidents</title><style>body{font-family:Arial;margin:24px;font-size:12px;color:#1E293B}h1{color:#B91C1C;font-size:19px;margin:0}table{border-collapse:collapse;width:100%;font-size:11.5px;margin-top:10px}th{background:#FEE2E2;text-align:start;padding:5px 7px;border:1px solid #FCA5A5}td{padding:5px 7px;border:1px solid #E2E8F0}</style></head><body>
    <h1>ثبت حوادث ایمنی (Safety Incident Register)</h1><div>${new Date().toLocaleDateString()} · ${tr('Open')}: ${s.value.open} · ${tr('LostTimeDays')}: ${s.value.lost_time_days}</div>
    <table><thead><tr><th>کود</th><th>عنوان</th><th>پروژه</th><th>نوع</th><th>شدت</th><th>تاریخ</th><th>وضعیت</th></tr></thead><tbody>${body}</tbody></table>
    <script>window.onload=()=>window.print()<\/script></body></html>`
  const w = window.open('', '_blank'); if (!w) return; w.document.write(html); w.document.close()
}

onMounted(() => { load(); loadProjects() })
</script>

<style scoped>
.hse-hero__bar { background: linear-gradient(135deg, #7F1D1D 0%, #B91C1C 55%, #DC2626 100%); border-radius: 14px; padding: 16px 18px; color: #fff; box-shadow: 0 10px 26px -12px rgba(185, 28, 28, 0.6); }
.hse-hero__head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
.hse-hero__title { display: flex; align-items: center; gap: 12px; }
.hse-hero__icon { width: 46px; height: 46px; border-radius: 12px; background: rgba(255, 255, 255, 0.14); display: flex; align-items: center; justify-content: center; border: 1px solid rgba(255, 255, 255, 0.25); }
.hse-hero__name { font-size: 20px; font-weight: 800; }
.hse-hero__meta { display: flex; gap: 6px; margin-top: 6px; flex-wrap: wrap; }
.hse-hero__pill { display: inline-flex; align-items: center; gap: 3px; font-size: 11.5px; padding: 2px 8px; border-radius: 20px; background: rgba(255, 255, 255, 0.14); border: 1px solid rgba(255, 255, 255, 0.2); }
.hse-hero__pill--danger { background: rgba(0, 0, 0, 0.22); }
.hse-hero__stats { margin-top: 10px; }
.kpi-tile { border: 1.5px solid #E2E8F0; border-radius: 12px; padding: 12px 14px; background: #fff; height: 100%; }
.kpi-tile__val { font-size: 15.5px; font-weight: 800; margin-top: 4px; color: #1E293B; }
.kpi-tile__lbl { font-size: 11px; color: #94A3B8; margin-top: 1px; }
@media (prefers-color-scheme: dark) { .kpi-tile { background: #1E293B; border-color: #334155; } .kpi-tile__val { color: #F1F5F9; } }
</style>
