<template>
  <q-page>
    <m-backgrounds>
      <div class="row my_radio_less q-pa-sm">
        <div class="col-12">
          <m-header icon="assignment_turned_in" controlRoomButton="false" class="q-mt-xs">
            {{ $t('Contracts') }}
          </m-header>
        </div>

        <action-bar :rows="filteredRows" :columns="exportColumns" filename="contracts" create-perm="contract-create" @add="openCreate" @update:filtered="() => {}">
          <template #filters>
            <div class="col-6 col-sm-3"><q-select outlined dense color="blue-grey-9" label-color="blue-grey-9" v-model="directionFilter" :options="directionOptions" emit-value map-options clearable :label="$t('Direction')" /></div>
            <div class="col-6 col-sm-3"><q-select outlined dense color="blue-grey-9" label-color="blue-grey-9" v-model="typeFilter" :options="partyTypeOptions" emit-value map-options clearable :label="$t('PartyType')" /></div>
            <div class="col-6 col-sm-3"><q-select outlined dense color="blue-grey-9" label-color="blue-grey-9" v-model="statusFilter" :options="statusOptions" emit-value map-options clearable :label="$t('Status')" /></div>
          </template>
        </action-bar>
        <div class="col-12">
          <n-table config-key="page.contracts" :loading="loading" :data="filteredRows" :columns="columns" v-model:filter="tableFilter"
            :can_edit="'contract-edit'" :can_delete="'contract-delete'" :noInfo="true" @edit="openEdit" @del="remove">
            <template v-slot:body-cell-title="props">
              <q-td :props="props"><a class="con-link" @click.prevent="openShow(props.row.id)">{{ props.row.title }}</a></q-td>
            </template>
            <template v-slot:body-cell-direction="props">
              <q-td :props="props">
                <q-chip dense size="sm" :color="props.row.direction === 'client' ? 'green-7' : 'deep-orange-6'" text-color="white">
                  <q-icon :name="props.row.direction === 'client' ? 'south_west' : 'north_east'" size="14px" class="q-mr-xs" />
                  {{ $t(props.row.direction === 'client' ? 'MoneyIn' : 'MoneyOut') }}
                </q-chip>
              </q-td>
            </template>
            <template v-slot:body-cell-party_type="props">
              <q-td :props="props"><q-chip dense size="sm" :color="typeColor(props.row.party_type)" text-color="white">{{ $t(typeKey(props.row.party_type)) }}</q-chip></q-td>
            </template>
            <template v-slot:body-cell-amount="props">
              <q-td :props="props" class="text-right text-weight-medium">{{ fmt(props.row.amount) }} {{ props.row.currency }}</q-td>
            </template>
            <template v-slot:body-cell-balance="props">
              <q-td :props="props" class="text-right text-weight-bold" :class="Number(props.row.balance) > 0 ? 'text-negative' : 'text-grey-7'">{{ fmt(props.row.balance) }}</q-td>
            </template>
            <template v-slot:body-cell-status="props">
              <q-td :props="props"><q-chip dense size="sm" :color="statusColor(props.row.status)" text-color="white">{{ $t(statusKey(props.row.status)) }}</q-chip></q-td>
            </template>
          </n-table>
        </div>
      </div>
    </m-backgrounds>

    <!-- Add / Edit modal -->
    <m-modal :showCM="dialog" @update:showCM="dialog = $event" card_style="width: 620px">
      <q-card class="bg-white">
        <n-header icon="assignment_turned_in">{{ form.id ? $t('Edit') : $t('AddNew') }} — {{ $t('Contract') }}</n-header>
        <q-separator />
        <q-form @submit="save">
          <q-card-section class="row q-col-gutter-sm">
            <div class="col-12 col-sm-8"><n-name :name="form.title" @update:name="form.title = $event" icon="title" :label="$t('Title')" autofocus /></div>
            <div class="col-12 col-sm-4">
              <q-select outlined dense color="primary" v-model="form.direction" :options="directionOptions" emit-value map-options :label="$t('Direction')" />
            </div>
            <div class="col-12 col-sm-6"><n-name :name="form.party_name" @update:name="form.party_name = $event" icon="badge" :label="$t('PartyName')" /></div>
            <div class="col-6 col-sm-3">
              <q-select outlined dense color="primary" v-model="form.party_type" :options="partyTypeOptions" emit-value map-options :label="$t('PartyType')" />
            </div>
            <div class="col-6 col-sm-3"><n-name :name="form.party_phone" @update:name="form.party_phone = $event" icon="phone" :label="$t('Phone')" :rules="[]" /></div>
            <div class="col-12 col-sm-6">
              <q-select outlined dense color="primary" label-color="primary" v-model="form.project_id"
                :options="projectOptions" emit-value map-options clearable :label="$t('Project')">
                <template v-slot:prepend><q-icon name="domain" color="primary" /></template>
              </q-select>
            </div>
            <div class="col-6 col-sm-3">
              <q-select outlined dense color="primary" v-model="form.status" :options="statusOptions" emit-value map-options :label="$t('Status')" />
            </div>
            <div class="col-6 col-sm-3"></div>
            <div class="col-12 col-sm-8">
              <money-input v-model="form.amount" v-model:currency="form.currency" v-model:rate="form.rate" :label="$t('ContractAmount')" />
            </div>
            <div class="col-6 col-sm-2">
              <shamsi-date v-model="form.start_date" color="primary" :label="$t('StartDate')" />
            </div>
            <div class="col-6 col-sm-2">
              <shamsi-date v-model="form.end_date" color="primary" :label="$t('EndDate')" />
            </div>
            <div class="col-12"><q-input outlined dense color="primary" type="textarea" autogrow v-model="form.scope" :label="$t('Scope')" /></div>
          </q-card-section>
          <q-separator />
          <n-submit :submitting="saving" :label="$t('Save')" />
        </q-form>
      </q-card>
    </m-modal>

    <!-- Show modal — settlement + milestones + payments + party history -->
    <m-modal :showCM="showDialog" @update:showCM="showDialog = $event" card_style="width: 820px">
      <q-card class="bg-white" v-if="active">
        <n-header icon="assignment_turned_in" :subtitle="active.code">
          {{ active.title }} — {{ active.party_name }}
        </n-header>
        <q-separator />

        <!-- Settlement summary -->
        <q-card-section class="q-pb-none">
          <div class="row q-col-gutter-sm">
            <div class="col-3" v-for="m in settlementCards" :key="m.label">
              <div class="settle-chip" :style="`border-color:${m.color}`">
                <div class="settle-chip__val" :style="`color:${m.color}`">{{ fmt(m.value) }}</div>
                <div class="settle-chip__lbl">{{ $t(m.label) }}</div>
              </div>
            </div>
          </div>
        </q-card-section>

        <q-card-section>
          <q-tabs v-model="showTab" dense align="left" active-color="primary" indicator-color="primary" class="text-grey-7" no-caps>
            <q-tab name="milestones" icon="flag" :label="$t('Milestones')" />
            <q-tab name="payments" icon="payments" :label="$t('Payments')" />
            <q-tab name="history" icon="history" :label="$t('CrossProjectHistory')" />
          </q-tabs>
          <q-separator />
          <tab-title class="q-mt-md" :title="$t(activeTab.label)" :icon="activeTab.icon" />
          <q-tab-panels v-model="showTab" animated>

            <!-- Milestones -->
            <q-tab-panel name="milestones" class="q-px-none">
              <div class="row items-center justify-between q-mb-sm">
                <div class="text-subtitle2">{{ $t('Milestones') }} ({{ active.milestones?.length || 0 }})</div>
                <progress-btn color="teal" icon="add" v-if="$can('contract-milestone-create')" @click="openMilestone()">{{ $t('AddNew') }}</progress-btn>
              </div>
              <q-markup-table flat bordered dense class="my_radio_less">
                <thead class="bg-theme-soft">
                  <tr>
                    <th class="text-left">{{ $t('Title') }}</th>
                    <th class="text-right">{{ $t('Amount') }}</th>
                    <th class="text-left">{{ $t('DueDate') }}</th>
                    <th class="text-center">{{ $t('Status') }}</th>
                    <th class="text-right"></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="!active.milestones || active.milestones.length === 0"><td colspan="5" class="text-center text-grey-5 q-py-md">{{ $t('NoRecordFound') }}</td></tr>
                  <tr v-for="ms in active.milestones" :key="ms.id">
                    <td class="text-weight-medium">{{ ms.title }}</td>
                    <td class="text-right">{{ fmt(ms.amount) }}</td>
                    <td>{{ ms.due_date ? ms.due_date.slice(0,10) : '—' }}</td>
                    <td class="text-center"><q-chip dense size="sm" :color="mStatusColor(ms.status)" text-color="white">{{ $t(mStatusKey(ms.status)) }}</q-chip></td>
                    <td class="text-right" style="white-space:nowrap">
                      <q-btn size="sm" dense flat round icon="edit" color="blue-8" v-if="$can('contract-milestone-edit')" @click="openMilestone(ms)" />
                      <q-btn size="sm" dense flat round icon="delete" color="negative" v-if="$can('contract-milestone-delete')" @click="removeMilestone(ms)" />
                    </td>
                  </tr>
                </tbody>
              </q-markup-table>
            </q-tab-panel>

            <!-- Payments -->
            <q-tab-panel name="payments" class="q-px-none">
              <q-markup-table flat bordered dense class="my_radio_less" style="max-height:230px">
                <thead class="bg-theme-soft">
                  <tr>
                    <th class="text-left">{{ $t('PaymentDate') }}</th>
                    <th class="text-left">{{ $t('Kind') }}</th>
                    <th class="text-right">{{ $t('Amount') }}</th>
                    <th class="text-left">{{ $t('Notes') }}</th>
                    <th class="text-right"></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="!active.payments || active.payments.length === 0"><td colspan="5" class="text-center text-grey-5 q-py-md">{{ $t('NoRecordFound') }}</td></tr>
                  <tr v-for="p in active.payments" :key="p.id">
                    <td style="white-space:nowrap">{{ p.payment_date ? p.payment_date.slice(0,10) : '—' }}</td>
                    <td><q-chip dense size="sm" :color="p.kind === 'advance' ? 'orange-7' : 'positive'" text-color="white">{{ p.kind === 'advance' ? $t('Advance') : $t('Payment') }}</q-chip></td>
                    <td class="text-right text-weight-medium">{{ fmt(p.amount) }} {{ p.currency }}</td>
                    <td class="text-caption">{{ p.note || '—' }}</td>
                    <td class="text-right"><q-btn size="sm" dense flat round icon="delete" color="negative" v-if="$can('contract-payment-delete')" @click="removePayment(p)" /></td>
                  </tr>
                </tbody>
              </q-markup-table>
              <div class="q-mt-sm" v-if="$can('contract-payment-create')">
                <div class="text-caption text-weight-bold text-grey-7 q-mb-xs">{{ $t('AddPayment') }}</div>
                <q-form @submit="savePayment" class="row q-col-gutter-sm items-end">
                  <div class="col-6 col-sm-3">
                    <shamsi-date v-model="paymentForm.payment_date" color="primary" :label="$t('PaymentDate')" />
                  </div>
                  <div class="col-6 col-sm-3"><q-select outlined dense color="primary" v-model="paymentForm.kind" :options="kindOptions" emit-value map-options :label="$t('Kind')" /></div>
                  <div class="col-10 col-sm-5"><money-input v-model="paymentForm.amount" v-model:currency="paymentForm.currency" v-model:rate="paymentForm.rate" :allow-save-rate="false" :label="$t('Amount')" /></div>
                  <div class="col-2 col-sm-1"><q-btn unelevated color="teal-7" icon="add" type="submit" :loading="savingPayment" round dense /></div>
                  <div class="col-12"><q-input outlined dense color="primary" v-model="paymentForm.note" :label="$t('Notes')" /></div>
                </q-form>
              </div>
            </q-tab-panel>

            <!-- Cross-project history -->
            <q-tab-panel name="history" class="q-px-none">
              <q-markup-table flat bordered dense class="my_radio_less">
                <thead class="bg-theme-soft">
                  <tr>
                    <th class="text-left">{{ $t('Code') }}</th>
                    <th class="text-left">{{ $t('Title') }}</th>
                    <th class="text-left">{{ $t('Project') }}</th>
                    <th class="text-right">{{ $t('ContractAmount') }}</th>
                    <th class="text-right">{{ $t('Balance') }}</th>
                    <th class="text-center">{{ $t('Status') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="!active.party_history || active.party_history.length === 0"><td colspan="6" class="text-center text-grey-5 q-py-md">{{ $t('NoOtherContracts') }}</td></tr>
                  <tr v-for="h in active.party_history" :key="h.id">
                    <td class="text-grey-7">{{ h.code }}</td>
                    <td class="text-weight-medium">{{ h.title }}</td>
                    <td>{{ h.project?.name || '—' }}</td>
                    <td class="text-right">{{ fmt(h.amount) }} {{ h.currency }}</td>
                    <td class="text-right" :class="Number(h.balance) > 0 ? 'text-negative' : 'text-grey-7'">{{ fmt(h.balance) }}</td>
                    <td class="text-center"><q-chip dense size="sm" :color="statusColor(h.status)" text-color="white">{{ $t(statusKey(h.status)) }}</q-chip></td>
                  </tr>
                </tbody>
              </q-markup-table>
            </q-tab-panel>
          </q-tab-panels>
        </q-card-section>

        <q-separator />
        <q-card-actions align="right" class="q-pa-sm"><q-btn flat :label="$t('Close')" color="grey-7" @click="showDialog = false" /></q-card-actions>
      </q-card>
    </m-modal>

    <!-- Milestone modal -->
    <m-modal :showCM="milestoneDialog" @update:showCM="milestoneDialog = $event" card_style="width: 460px">
      <q-card class="bg-white">
        <n-header icon="flag">{{ milestoneForm.id ? $t('Edit') : $t('AddNew') }} — {{ $t('Milestone') }}</n-header>
        <q-separator />
        <q-form @submit="saveMilestone">
          <q-card-section class="row q-col-gutter-sm">
            <div class="col-12"><n-name :name="milestoneForm.title" @update:name="milestoneForm.title = $event" icon="flag" :label="$t('Title')" autofocus /></div>
            <div class="col-6"><q-input outlined dense color="primary" type="number" step="any" v-model.number="milestoneForm.amount" :label="$t('Amount')" /></div>
            <div class="col-6">
              <shamsi-date v-model="milestoneForm.due_date" color="primary" :label="$t('DueDate')" />
            </div>
            <div class="col-12"><q-select outlined dense color="primary" v-model="milestoneForm.status" :options="mStatusOptions" emit-value map-options :label="$t('Status')" /></div>
            <div class="col-12"><q-input outlined dense color="primary" type="textarea" autogrow v-model="milestoneForm.notes" :label="$t('Notes')" /></div>
          </q-card-section>
          <q-separator />
          <n-submit :submitting="savingMilestone" :label="$t('Save')" />
        </q-form>
      </q-card>
    </m-modal>
  </q-page>
</template>

<script setup>
import { ref, reactive, computed, getCurrentInstance, onMounted } from 'vue'
import { Notify } from 'quasar'
import { api } from '@/boot/axios'

const { proxy } = getCurrentInstance()
const rows = ref([])
const loading = ref(false)
const saving = ref(false)
const dialog = ref(false)
const tableFilter = ref('')
const directionFilter = ref(null)
const typeFilter = ref(null)
const statusFilter = ref(null)
const projectOptions = ref([])

const directionOptions = [
  { label: 'Client (money in)', value: 'client' },
  { label: 'Subcontractor (money out)', value: 'subcontractor' },
]
const partyTypeOptions = [
  { label: 'Individual', value: 'individual' },
  { label: 'Company', value: 'company' },
  { label: 'Government', value: 'government' },
]
const statusOptions = [
  { label: 'Draft', value: 'draft' },
  { label: 'Active', value: 'active' },
  { label: 'Completed', value: 'completed' },
  { label: 'Cancelled', value: 'cancelled' },
]

const today = () => new Date().toISOString().slice(0, 10)
const blank = () => ({ id: null, title: '', party_name: '', party_type: 'individual', party_phone: '', direction: 'subcontractor', project_id: null, amount: null, currency: 'AFN', rate: 1, status: 'active', start_date: '', end_date: '', scope: '' })
const form = reactive(blank())

const filteredRows = computed(() => rows.value.filter(r =>
  (!directionFilter.value || r.direction === directionFilter.value) &&
  (!typeFilter.value || r.party_type === typeFilter.value) &&
  (!statusFilter.value || r.status === statusFilter.value)
))

const columns = [
  { name: 'created_at', label: '#', field: 'id', align: 'left' },
  { name: 'code', label: 'Code', field: 'code', align: 'left', sortable: true },
  { name: 'title', label: 'Title', field: 'title', align: 'left', sortable: true },
  { name: 'party_name', label: 'PartyName', field: 'party_name', align: 'left', sortable: true },
  { name: 'direction', label: 'Direction', field: 'direction', align: 'left' },
  { name: 'party_type', label: 'PartyType', field: 'party_type', align: 'left' },
  { name: 'amount', label: 'ContractAmount', field: 'amount', align: 'right', sortable: true },
  { name: 'balance', label: 'Balance', field: 'balance', align: 'right', sortable: true },
  { name: 'status', label: 'Status', field: 'status', align: 'center' },
  { name: 'actions', label: 'Actions', field: 'actions', align: 'right' }
]
const exportColumns = columns.filter(c => c.name !== 'actions')

function fmt (v) { return Number(v || 0).toLocaleString('en-US') }
function typeColor (t) { return { individual: 'blue-7', company: 'deep-purple-6', government: 'teal-7' }[t] ?? 'grey' }
function typeKey (t) { return { individual: 'Individual', company: 'Company', government: 'Government' }[t] ?? 'Individual' }
function statusColor (s) { return { draft: 'blue-grey-5', active: 'primary', completed: 'positive', cancelled: 'negative' }[s] ?? 'grey' }
function statusKey (s) { return { draft: 'Draft', active: 'Active', completed: 'Completed', cancelled: 'Cancelled' }[s] ?? 'Draft' }
function mStatusColor (s) { return { pending: 'blue-grey-6', in_progress: 'amber-8', done: 'positive' }[s] ?? 'grey' }
function mStatusKey (s) { return { pending: 'Pending', in_progress: 'InProgress', done: 'Done' }[s] ?? 'Pending' }

async function load () {
  loading.value = true
  try { const { data } = await api.get('/contracts'); rows.value = data } finally { loading.value = false }
}
async function loadProjects () {
  try { const { data } = await api.get('/projects'); projectOptions.value = (data || []).map(p => ({ label: p.name, value: p.id })) } catch (_) {}
}
function syncRate () { if (form.currency === 'AFN') form.rate = 1 }
function openCreate () { Object.assign(form, blank()); dialog.value = true }
function openEdit (id) {
  const r = rows.value.find(x => x.id === id)
  if (!r) return
  Object.assign(form, { id: r.id, title: r.title, party_name: r.party_name, party_type: r.party_type, party_phone: r.party_phone || '', direction: r.direction, project_id: r.project_id, amount: Number(r.amount), currency: r.currency || 'AFN', rate: Number(r.rate || 1), status: r.status, start_date: r.start_date ? r.start_date.slice(0,10) : '', end_date: r.end_date ? r.end_date.slice(0,10) : '', scope: r.scope || '' })
  dialog.value = true
}
async function save () {
  saving.value = true
  try {
    const payload = { ...form, start_date: form.start_date || null, end_date: form.end_date || null }
    if (form.id) await api.put('/contracts/' + form.id, payload)
    else await api.post('/contracts', payload)
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: 'Saved' })
    dialog.value = false; load()
  } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Save failed' }) } finally { saving.value = false }
}
function remove (id) { proxy.$delete('contracts/' + id, load) }

// ── Show / detail ──
const showDialog = ref(false)
const showTab = ref('milestones')
const contractTabs = [
  { name: 'milestones', label: 'Milestones', icon: 'flag' },
  { name: 'payments', label: 'Payments', icon: 'payments' },
  { name: 'history', label: 'CrossProjectHistory', icon: 'history' }
]
const activeTab = computed(() => contractTabs.find(t => t.name === showTab.value) || contractTabs[0])
const active = ref(null)

const settlementCards = computed(() => {
  const c = active.value || {}
  return [
    { label: 'ContractAmount', value: c.amount, color: '#175A8C' },
    { label: 'Paid', value: c.paid_total, color: '#059669' },
    { label: 'Advance', value: c.advance_total, color: '#C2410C' },
    { label: 'Balance', value: c.balance, color: Number(c.balance) > 0 ? '#DC2626' : '#64748B' },
  ]
})

async function openShow (id) {
  try {
    const { data } = await api.get('/contracts/' + id)
    active.value = data; showTab.value = 'milestones'; showDialog.value = true
  } catch (_) {}
}
async function refreshActive () {
  if (!active.value) return
  try { const { data } = await api.get('/contracts/' + active.value.id); active.value = data } catch (_) {}
}

// ── Milestones ──
const milestoneDialog = ref(false)
const savingMilestone = ref(false)
const mStatusOptions = [
  { label: 'Pending', value: 'pending' },
  { label: 'In Progress', value: 'in_progress' },
  { label: 'Done', value: 'done' },
]
const milestoneForm = reactive({ id: null, title: '', amount: null, due_date: '', status: 'pending', notes: '' })
function openMilestone (ms = null) {
  if (ms) Object.assign(milestoneForm, { id: ms.id, title: ms.title, amount: Number(ms.amount), due_date: ms.due_date ? ms.due_date.slice(0,10) : '', status: ms.status || 'pending', notes: ms.notes || '' })
  else Object.assign(milestoneForm, { id: null, title: '', amount: null, due_date: '', status: 'pending', notes: '' })
  milestoneDialog.value = true
}
async function saveMilestone () {
  savingMilestone.value = true
  try {
    const payload = { title: milestoneForm.title, amount: milestoneForm.amount || 0, due_date: milestoneForm.due_date || null, status: milestoneForm.status, notes: milestoneForm.notes }
    if (milestoneForm.id) await api.put('/contract-milestones/' + milestoneForm.id, payload)
    else await api.post('/contracts/' + active.value.id + '/milestones', payload)
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: 'Saved' })
    milestoneDialog.value = false; refreshActive()
  } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Save failed' }) } finally { savingMilestone.value = false }
}
function removeMilestone (ms) { proxy.$delete('contract-milestones/' + ms.id, refreshActive) }

// ── Payments ──
const savingPayment = ref(false)
const kindOptions = [
  { label: 'Payment', value: 'payment' },
  { label: 'Advance', value: 'advance' },
]
const paymentForm = reactive({ payment_date: today(), kind: 'payment', amount: null, currency: 'AFN', rate: 1, note: '' })
async function savePayment () {
  if (!active.value) return
  savingPayment.value = true
  try {
    await api.post('/contracts/' + active.value.id + '/payments', { ...paymentForm })
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: 'Saved' })
    Object.assign(paymentForm, { amount: null, note: '' })
    await refreshActive(); load()
  } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Save failed' }) } finally { savingPayment.value = false }
}
function removePayment (p) { proxy.$delete('contract-payments/' + p.id, async () => { await refreshActive(); load() }) }

onMounted(() => { load(); loadProjects() })
</script>

<style scoped>
.con-link { color: var(--q-primary); font-weight: 600; cursor: pointer; text-decoration: none; }
.con-link:hover { text-decoration: underline; }
.settle-chip { border: 1.5px solid #E2E8F0; border-radius: 10px; padding: 8px 10px; text-align: center; background: #F8FAFC; }
.settle-chip__val { font-size: 15px; font-weight: 800; letter-spacing: -0.3px; }
.settle-chip__lbl { font-size: 10px; color: #94A3B8; margin-top: 2px; }
</style>
