<template>
  <q-page>
    <m-backgrounds>
      <div class="row my_radio_less q-pa-sm">
        <!-- Hero -->
        <div class="col-12">
          <div class="co-hero">
            <div class="co-hero__bar">
              <div class="co-hero__head">
                <div class="co-hero__title">
                  <div class="co-hero__icon"><q-icon name="published_with_changes" size="26px" /></div>
                  <div>
                    <div class="co-hero__name">{{ $t('ChangeOrders') }}</div>
                    <div class="co-hero__meta">
                      <span class="co-hero__pill"><q-icon name="tune" size="13px" /> {{ s.count }} {{ $t('Total') }}</span>
                      <span class="co-hero__pill" v-if="s.pending"><q-icon name="hourglass_top" size="13px" /> {{ s.pending }} {{ $t('Pending') }}</span>
                    </div>
                  </div>
                </div>
                <div class="q-gutter-xs row items-center">
                  <q-btn flat dense icon="print" color="white" :label="$t('Register')" @click="printRegister" />
                  <progress-btn color="white" text-color="primary" icon="add" v-if="$can('change-order-create')" @click="openCreate">{{ $t('AddNew') }}</progress-btn>
                </div>
              </div>
            </div>
            <div class="row q-col-gutter-sm co-hero__stats">
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
            <div class="col-6 col-sm-3"><q-select outlined dense color="primary" v-model="statusFilter" :options="statusOptions" emit-value map-options clearable :label="$t('Status')" @update:model-value="load" /></div>
            <div class="col-6 col-sm-3"><q-input outlined dense color="primary" v-model="tableFilter" :placeholder="$t('Search')" clearable><template #prepend><q-icon name="search" color="primary" /></template></q-input></div>
          </div>
        </div>

        <div class="col-12">
          <n-table config-key="page.changeOrders" :loading="loading" :data="rows" :columns="columns" v-model:filter="tableFilter"
            :can_show="'change-order-show'" info-icon="visibility" :noInfoDialog="true"
            :can_delete="'change-order-delete'" :noEdit="true" @info="openDetail" @del="remove">
            <template v-slot:body-cell-kind="props">
              <q-td :props="props" class="text-center"><q-chip dense size="sm" :color="kindColor(props.row.kind)" text-color="white">{{ $t(kindKey(props.row.kind)) }}</q-chip></q-td>
            </template>
            <template v-slot:body-cell-cost_impact_base="props">
              <q-td :props="props" class="text-right text-weight-medium" :class="props.row.kind === 'deduction' ? 'text-negative' : (props.row.kind === 'no_cost' ? 'text-grey-6' : 'text-positive')">
                {{ props.row.kind === 'deduction' ? '−' : (props.row.kind === 'addition' ? '+' : '') }}{{ fmt(props.row.cost_impact_base) }} {{ base }}
              </q-td>
            </template>
            <template v-slot:body-cell-status="props">
              <q-td :props="props" class="text-center"><q-chip dense size="sm" :color="statusColor(props.row.status)" text-color="white">{{ $t(statusKey(props.row.status)) }}</q-chip></q-td>
            </template>
          </n-table>
        </div>
      </div>
    </m-backgrounds>

    <!-- Create / edit -->
    <m-modal :showCM="dialog" @update:showCM="dialog = $event" card_style="width: 600px">
      <q-card class="bg-white">
        <n-header icon="published_with_changes">{{ form.id ? $t('Edit') : $t('NewChangeOrder') }}</n-header>
        <q-separator />
        <q-form @submit="save">
          <q-card-section class="row q-col-gutter-sm">
            <div class="col-12 col-sm-8"><n-name :name="form.title" @update:name="form.title = $event" icon="title" :label="$t('Title')" /></div>
            <div class="col-12 col-sm-4"><q-select outlined dense color="primary" v-model="form.project_id" :options="projectOptions" emit-value map-options :label="$t('Project')" :rules="[v => !!v || $t('FieldIsRequired')]" /></div>
            <div class="col-12 col-sm-4"><q-select outlined dense color="primary" v-model="form.kind" :options="kindOptions" emit-value map-options :label="$t('Kind')" /></div>
            <div class="col-12 col-sm-4"><q-select outlined dense color="primary" v-model="form.reason" :options="reasonOptions" emit-value map-options :label="$t('Reason')" /></div>
            <div class="col-6 col-sm-4"><shamsi-date v-model="form.co_date" color="primary" :label="$t('Date')" /></div>
            <div class="col-12 col-sm-8" v-if="form.kind !== 'no_cost'"><money-input v-model="form.cost_impact" v-model:currency="form.currency" v-model:rate="form.rate" :label="$t('CostImpact')" /></div>
            <div class="col-6 col-sm-4"><q-input outlined dense color="primary" type="number" v-model.number="form.time_impact_days" :label="$t('TimeImpactDays')" /></div>
            <div class="col-12 col-sm-8"><n-name :name="form.requested_by_name" @update:name="form.requested_by_name = $event" icon="person" :label="$t('RequestedBy')" :rules="[]" /></div>
            <div class="col-12"><q-input outlined dense color="primary" type="textarea" autogrow v-model="form.description" :label="$t('Description')" /></div>
            <div class="col-12"><q-file outlined dense color="primary" v-model="file" :label="$t('Attachment')" accept=".jpg,.jpeg,.png,.webp,.pdf" max-file-size="41943040" clearable><template #prepend><q-icon name="attach_file" color="primary" /></template></q-file></div>
            <div class="col-12"><q-select outlined dense color="primary" v-model="form.status" :options="[{ label: $t('Draft'), value: 'draft' }, { label: $t('Submitted'), value: 'submitted' }]" emit-value map-options :label="$t('Status')" /></div>
          </q-card-section>
          <q-separator />
          <n-submit :submitting="saving" :label="$t('Save')" />
        </q-form>
      </q-card>
    </m-modal>

    <!-- Detail + decision -->
    <m-modal :showCM="detailDialog" @update:showCM="detailDialog = $event" card_style="width: 620px">
      <q-card class="bg-white" v-if="active">
        <n-header icon="published_with_changes" :subtitle="active.code">{{ active.title }}</n-header>
        <q-separator />
        <q-card-section class="q-pb-none">
          <div class="row q-col-gutter-sm">
            <div class="col-6 col-sm-3"><stat-card dense icon="tune" :label="$t('Kind')" :value="$t(kindKey(active.kind))" :color="kindHex(active.kind)" :tint="kindTint(active.kind)" /></div>
            <div class="col-6 col-sm-3"><stat-card dense icon="payments" :label="$t('CostImpact')" :value="(active.kind === 'deduction' ? '−' : '+') + fmt(active.cost_impact_base)" :suffix="base" :color="kindHex(active.kind)" :tint="kindTint(active.kind)" /></div>
            <div class="col-6 col-sm-3"><stat-card dense icon="schedule" :label="$t('TimeImpactDays')" :value="active.time_impact_days" suffix="d" color="#7C3AED" tint="#EDE9FE" /></div>
            <div class="col-6 col-sm-3"><stat-card dense icon="flag" :label="$t('Status')" :value="$t(statusKey(active.status))" :color="statusHex(active.status)" :tint="statusTint(active.status)" /></div>
          </div>
        </q-card-section>
        <q-card-section>
          <q-markup-table flat bordered dense class="my_radio_less">
            <tbody>
              <tr><td class="text-grey-7">{{ $t('Project') }}</td><td class="text-weight-medium">{{ active.project?.name }}</td></tr>
              <tr><td class="text-grey-7">{{ $t('Reason') }}</td><td>{{ active.reason ? $t(reasonKey(active.reason)) : '—' }}</td></tr>
              <tr><td class="text-grey-7">{{ $t('RequestedBy') }}</td><td>{{ active.requested_by_name || active.requester?.name || '—' }}</td></tr>
              <tr v-if="active.decision_note"><td class="text-grey-7">{{ $t('DecisionNote') }}</td><td>{{ active.decision_note }}</td></tr>
              <tr v-if="active.description"><td class="text-grey-7">{{ $t('Description') }}</td><td>{{ active.description }}</td></tr>
            </tbody>
          </q-markup-table>
        </q-card-section>
        <q-separator />
        <q-card-actions align="right" class="q-pa-sm">
          <q-btn v-if="active.attachment_path" flat dense color="indigo-7" icon="attachment" :label="$t('Attachment')" @click="viewAttach(active)" />
          <q-btn v-if="active.status === 'draft' && $can('change-order-edit')" outline dense color="blue-8" icon="send" :label="$t('Submit')" @click="submit(active)" />
          <template v-if="['draft', 'submitted'].includes(active.status) && $can('change-order-approve')">
            <q-btn unelevated dense color="negative" icon="close" :label="$t('Reject')" @click="decide('rejected')" />
            <q-btn unelevated dense color="positive" icon="check" :label="$t('Approve')" @click="decide('approved')" />
          </template>
          <q-btn flat :label="$t('Close')" color="grey-7" @click="detailDialog = false" />
        </q-card-actions>
      </q-card>
    </m-modal>
  </q-page>
</template>

<script setup>
import { ref, reactive, computed, getCurrentInstance, onMounted } from 'vue'
import { Notify } from 'quasar'
import { api } from '@/boot/axios'
import { useCurrency } from '@/composables/useCurrency'
import { compressImage } from '@/utils/image'
import { useLookups } from '@/composables/useLookups'

const { proxy } = getCurrentInstance()
const { base, loadRates, rateFor } = useCurrency()

const rows = ref([])
const s = ref({ count: 0, pending: 0, approved: 0, additions: 0, deductions: 0, net_impact: 0, time_impact: 0 })
const loading = ref(false)
const saving = ref(false)
const dialog = ref(false)
const detailDialog = ref(false)
const active = ref(null)
const file = ref(null)
const tableFilter = ref('')
const projectFilter = ref(null)
const statusFilter = ref(null)
const projectOptions = ref([])

const statusOptions = [{ label: 'Draft', value: 'draft' }, { label: 'Submitted', value: 'submitted' }, { label: 'Approved', value: 'approved' }, { label: 'Rejected', value: 'rejected' }]
const kindOptions = [{ label: 'Addition', value: 'addition' }, { label: 'Deduction', value: 'deduction' }, { label: 'No cost', value: 'no_cost' }]
// Reasons come from the Options Registry (fallback until loaded).
const { loadLookups, options: lookupOptions } = useLookups()
const reasonOptions = computed(() => lookupOptions('change_order_reason').length
  ? lookupOptions('change_order_reason')
  : [{ label: 'Owner request', value: 'owner request' }, { label: 'Design change', value: 'design change' }, { label: 'Site condition', value: 'site condition' }, { label: 'Error/omission', value: 'error' }])

const columns = [
  { name: 'code', label: 'Code', field: 'code', align: 'left', sortable: true },
  { name: 'title', label: 'Title', field: 'title', align: 'left' },
  { name: 'project', label: 'Project', field: r => r.project?.name, align: 'left' },
  { name: 'kind', label: 'Kind', field: 'kind', align: 'center' },
  { name: 'cost_impact_base', label: 'CostImpact', field: 'cost_impact_base', align: 'right', sortable: true },
  { name: 'time_impact_days', label: 'Days', field: 'time_impact_days', align: 'center' },
  { name: 'status', label: 'Status', field: 'status', align: 'center', sortable: true },
  { name: 'actions', label: 'Actions', field: 'actions', align: 'right' },
]

function fmt (v) { return Number(v || 0).toLocaleString('en-US') }
function kindKey (k) { return { addition: 'Addition', deduction: 'Deduction', no_cost: 'NoCost' }[k] ?? 'Addition' }
function kindColor (k) { return { addition: 'green-7', deduction: 'red-7', no_cost: 'blue-grey-5' }[k] ?? 'grey' }
function kindHex (k) { return { addition: '#16A34A', deduction: '#DC2626', no_cost: '#64748B' }[k] ?? '#16A34A' }
function kindTint (k) { return { addition: '#DCFCE7', deduction: '#FEE2E2', no_cost: '#F1F5F9' }[k] ?? '#DCFCE7' }
function statusKey (s) { return { draft: 'Draft', submitted: 'Submitted', approved: 'Approved', rejected: 'Rejected' }[s] ?? 'Draft' }
function statusColor (s) { return { draft: 'blue-grey-5', submitted: 'amber-8', approved: 'green-7', rejected: 'red-7' }[s] ?? 'grey' }
function statusHex (s) { return { draft: '#64748B', submitted: '#D97706', approved: '#16A34A', rejected: '#DC2626' }[s] ?? '#64748B' }
function statusTint (s) { return { draft: '#F1F5F9', submitted: '#FEF3C7', approved: '#DCFCE7', rejected: '#FEE2E2' }[s] ?? '#F1F5F9' }
function reasonKey (r) { return { 'owner request': 'OwnerRequest', 'design change': 'DesignChange', 'site condition': 'SiteCondition', error: 'ErrorOmission' }[r] ?? 'OwnerRequest' }

const tr = (k) => (proxy?.$t ? proxy.$t(k) : k)
const heroStats = computed(() => [
  { label: 'NetImpact', value: (s.value.net_impact >= 0 ? '+' : '') + fmt(s.value.net_impact) + ' ' + base.value, icon: 'trending_up', color: s.value.net_impact >= 0 ? '#16A34A' : '#DC2626' },
  { label: 'Additions', value: '+' + fmt(s.value.additions) + ' ' + base.value, icon: 'add_circle', color: '#16A34A' },
  { label: 'Deductions', value: '−' + fmt(s.value.deductions) + ' ' + base.value, icon: 'remove_circle', color: '#DC2626' },
  { label: 'TimeImpact', value: s.value.time_impact + ' ' + tr('Days'), icon: 'schedule', color: '#7C3AED' },
])

const blank = () => ({ id: null, project_id: null, title: '', kind: 'addition', reason: 'owner request', co_date: new Date().toISOString().slice(0, 10), cost_impact: null, currency: 'AFN', rate: 1, time_impact_days: 0, requested_by_name: '', description: '', status: 'draft' })
const form = reactive(blank())

async function load () {
  loading.value = true
  try {
    const params = {}
    if (projectFilter.value) params.project_id = projectFilter.value
    if (statusFilter.value) params.status = statusFilter.value
    const { data } = await api.get('/change-orders', { params })
    rows.value = data.change_orders || []
    s.value = data.summary || s.value
  } finally { loading.value = false }
}
async function loadProjects () { try { const { data } = await api.get('/projects'); projectOptions.value = (data || []).map(x => ({ label: x.name, value: x.id })) } catch (_) {} }

function openCreate () { Object.assign(form, blank()); file.value = null; dialog.value = true }
async function save () {
  saving.value = true
  try {
    const fd = new FormData()
    Object.entries(form).forEach(([k, v]) => { if (v !== null && v !== '' && k !== 'id') fd.append(k, v) })
    if (file.value) fd.append('attachment', file.value.type?.startsWith('image/') ? await compressImage(file.value) : file.value)
    await api.post('/change-orders', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: 'Saved' })
    dialog.value = false; load()
  } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Save failed' }) } finally { saving.value = false }
}
function remove (id) { proxy.$delete('change-orders/' + id, load) }

function openDetail (id, row) { active.value = row || rows.value.find(x => x.id === id); detailDialog.value = true }
async function submit (co) { try { await api.put('/change-orders/' + co.id + '/submit'); Notify.create({ type: 'positive', position: 'bottom', message: 'Submitted' }); detailDialog.value = false; load() } catch (e) { Notify.create({ type: 'negative', message: 'Failed' }) } }
async function decide (decision) {
  try { await api.put('/change-orders/' + active.value.id + '/decide', { decision }); Notify.create({ type: 'positive', position: 'bottom', message: decision === 'approved' ? 'Approved — contract revised' : 'Rejected' }); detailDialog.value = false; load() }
  catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Failed' }) }
}
async function viewAttach (co) { try { const res = await api.get('/change-orders/' + co.id + '/attachment', { responseType: 'blob' }); window.open(URL.createObjectURL(new Blob([res.data], { type: co.attachment_mime })), '_blank') } catch (_) {} }

function printRegister () {
  const esc = x => String(x ?? '—').replace(/</g, '&lt;')
  const body = rows.value.map(r => `<tr><td>${esc(r.code)}</td><td>${esc(r.title)}</td><td>${esc(r.project?.name)}</td><td>${r.kind}</td><td style="text-align:end">${(r.kind === 'deduction' ? '−' : '+')}${fmt(r.cost_impact_base)}</td><td>${r.status}</td></tr>`).join('')
  const html = `<!DOCTYPE html><html dir="rtl"><head><meta charset="utf-8"><title>Change Orders</title><style>body{font-family:Arial;margin:24px;font-size:12px;color:#1E293B}h1{color:#123A66;font-size:19px;margin:0}table{border-collapse:collapse;width:100%;font-size:11.5px;margin-top:10px}th{background:#EEF4FB;text-align:start;padding:5px 7px;border:1px solid #CBD5E1}td{padding:5px 7px;border:1px solid #E2E8F0}</style></head><body>
    <h1>ثبت تغییرات قرارداد (Change Order Register)</h1><div>${new Date().toLocaleDateString()} · تأثیر خالص: ${(s.value.net_impact >= 0 ? '+' : '')}${fmt(s.value.net_impact)} ${base.value}</div>
    <table><thead><tr><th>کود</th><th>عنوان</th><th>پروژه</th><th>نوع</th><th>مبلغ</th><th>وضعیت</th></tr></thead><tbody>${body}</tbody></table>
    <script>window.onload=()=>window.print()<\/script></body></html>`
  const w = window.open('', '_blank'); if (!w) return; w.document.write(html); w.document.close()
}

onMounted(() => { loadLookups(); loadRates(); load(); loadProjects() })
</script>

<style scoped>
.co-hero__bar { background: linear-gradient(135deg, #4C1D95 0%, #6D28D9 55%, #7C3AED 100%); border-radius: 14px; padding: 16px 18px; color: #fff; box-shadow: 0 10px 26px -12px rgba(76, 29, 149, 0.6); }
.co-hero__head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
.co-hero__title { display: flex; align-items: center; gap: 12px; }
.co-hero__icon { width: 46px; height: 46px; border-radius: 12px; background: rgba(255, 255, 255, 0.14); display: flex; align-items: center; justify-content: center; border: 1px solid rgba(255, 255, 255, 0.25); }
.co-hero__name { font-size: 20px; font-weight: 800; }
.co-hero__meta { display: flex; gap: 6px; margin-top: 6px; }
.co-hero__pill { display: inline-flex; align-items: center; gap: 3px; font-size: 11.5px; padding: 2px 8px; border-radius: 20px; background: rgba(255, 255, 255, 0.14); border: 1px solid rgba(255, 255, 255, 0.2); }
.co-hero__stats { margin-top: 10px; }
.kpi-tile { border: 1.5px solid #E2E8F0; border-radius: 12px; padding: 12px 14px; background: #fff; height: 100%; }
.kpi-tile__val { font-size: 15.5px; font-weight: 800; margin-top: 4px; color: #1E293B; }
.kpi-tile__lbl { font-size: 11px; color: #94A3B8; margin-top: 1px; }
@media (prefers-color-scheme: dark) { .kpi-tile { background: #1E293B; border-color: #334155; } .kpi-tile__val { color: #F1F5F9; } }
</style>
