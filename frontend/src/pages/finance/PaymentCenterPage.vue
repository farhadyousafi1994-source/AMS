<template>
  <q-page>
    <m-backgrounds>
      <div class="row my_radio_less q-pa-sm pc-wrap">
        <!-- Hero -->
        <div class="col-12">
          <div class="pc-hero">
            <div class="pc-hero__glow"></div>
            <div>
              <div class="pc-hero__eyebrow"><q-icon name="account_balance" size="16px" /> {{ $t('Finance') }}</div>
              <div class="pc-hero__title">{{ $t('PaymentCenter') }}</div>
              <div class="pc-hero__sub">{{ $t('PaymentCenterSub') }}</div>
            </div>
            <q-space />
            <q-btn class="pc-new" no-caps unelevated v-if="$can('payment-request-create')" @click="openNew">
              <q-icon name="add" size="20px" class="q-mr-xs" />{{ $t('NewPaymentRequest') }}
            </q-btn>
          </div>
        </div>

        <!-- KPI -->
        <div class="col-12 q-mt-md">
          <div class="row q-col-gutter-md">
            <div class="col-6 col-md" v-for="k in kpis" :key="k.key">
              <div class="pc-kpi" :class="'pc-kpi--' + k.key" :style="filter === k.filter ? 'outline:2px solid ' + k.color : ''" @click="k.filter && (filter = k.filter)">
                <div class="pc-kpi__icon" :style="`background:${k.color}`"><q-icon :name="k.icon" size="20px" /></div>
                <div>
                  <div class="pc-kpi__val">{{ k.count }}</div>
                  <div class="pc-kpi__lbl">{{ $t(k.label) }}</div>
                  <div v-if="k.amount != null" class="pc-kpi__amt">{{ fmt(k.amount) }} {{ base }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Filters -->
        <div class="col-12 q-mt-md">
          <div class="pc-bar">
            <div class="pc-tabs">
              <button v-for="t in tabs" :key="t.v" class="pc-tab" :class="{ 'pc-tab--on': filter === t.v }" @click="filter = t.v">
                {{ $t(t.label) }}<span v-if="counts[t.v] != null" class="pc-tab__n">{{ counts[t.v] }}</span>
              </button>
            </div>
            <q-space />
            <q-select outlined dense color="indigo-8" v-model="typeFilter" :options="typeOptions" emit-value map-options clearable :label="$t('Type')" style="min-width:170px" @update:model-value="load" />
            <q-input outlined dense color="indigo-8" v-model="search" :placeholder="$t('SmartSearch')" clearable debounce="300" style="min-width:220px" @update:model-value="load">
              <template #prepend><q-icon name="search" /></template>
            </q-input>
          </div>
        </div>

        <!-- List -->
        <div class="col-12 q-mt-sm">
          <div v-if="loading" class="text-center q-py-lg"><q-spinner color="indigo-7" size="2.4em" /></div>
          <div v-else-if="!rows.length" class="pc-empty"><q-icon name="receipt_long" size="44px" class="q-mb-sm" /><div>{{ $t('NoPaymentsFound') }}</div></div>
          <div v-else class="pc-list">
            <div v-for="r in rows" :key="r.id" class="pc-row" @click="open(r)">
              <div class="pc-row__type" :style="`background:${typeColor(r.type)}1a;color:${typeColor(r.type)}`"><q-icon :name="typeIcon(r.type)" size="20px" /></div>
              <div class="pc-row__main">
                <div class="pc-row__payee">{{ r.payee_name }} <span class="pc-row__no">{{ r.request_no }}</span></div>
                <div class="pc-row__meta">
                  <span>{{ $t(typeLabel(r.type)) }}</span>
                  <span v-if="r.project"> · {{ r.project.name }}</span>
                  <span v-if="r.source_module"> · {{ r.source_module }}</span>
                </div>
              </div>
              <div class="pc-row__prio" v-if="r.priority !== 'normal'"><q-chip dense size="sm" :color="prioColor(r.priority)" text-color="white">{{ $t(cap(r.priority)) }}</q-chip></div>
              <div class="pc-row__flow">
                <div class="pc-flow">
                  <span v-for="(a, i) in r.approvals" :key="a.id" class="pc-flow__dot"
                    :class="'pc-flow__dot--' + a.status" :title="a.role">{{ i + 1 }}</span>
                  <span v-if="!r.approvals?.length" class="pc-flow__auto">{{ $t('AutoApproved') }}</span>
                </div>
              </div>
              <div class="pc-row__amt">
                <div class="pc-row__amt-v">{{ fmt(r.requested_amount) }} <small>{{ r.currency }}</small></div>
                <q-chip dense size="sm" :color="statusColor(r.status)" text-color="white" class="pc-row__status"><q-icon :name="statusIcon(r.status)" size="13px" class="q-mr-xs" />{{ $t(cap(r.status)) }}</q-chip>
              </div>
            </div>
          </div>
        </div>
      </div>
    </m-backgrounds>

    <!-- Detail -->
    <m-modal :showCM="dialog" @update:showCM="dialog = $event" card_style="width: 720px; max-width: 96vw">
      <q-card class="bg-grey-1" v-if="active">
        <div class="pcd-head" :style="`background:${typeColor(active.type)}`">
          <q-icon :name="typeIcon(active.type)" size="26px" class="q-mr-sm" />
          <div>
            <div class="pcd-head__payee">{{ active.payee_name }}</div>
            <div class="pcd-head__sub">{{ active.request_no }} · {{ $t(typeLabel(active.type)) }}<span v-if="active.project"> · {{ active.project.name }}</span></div>
          </div>
          <q-space />
          <div class="pcd-head__amt">{{ fmt(active.requested_amount) }} {{ active.currency }}</div>
          <q-btn flat round dense icon="close" color="white" class="q-ml-sm" @click="dialog = false" />
        </div>

        <q-card-section>
          <div class="row q-col-gutter-sm q-mb-sm">
            <div class="col-6 col-sm-3"><div class="pcd-fact"><span>{{ $t('Status') }}</span><b :style="`color:${statusColor(active.status)}`">{{ $t(cap(active.status)) }}</b></div></div>
            <div class="col-6 col-sm-3"><div class="pcd-fact"><span>{{ $t('Priority') }}</span><b>{{ $t(cap(active.priority)) }}</b></div></div>
            <div class="col-6 col-sm-3"><div class="pcd-fact"><span>{{ $t('RequestedBy') }}</span><b>{{ active.requester?.name || '—' }}</b></div></div>
            <div class="col-6 col-sm-3"><div class="pcd-fact"><span>{{ $t('NeededBy') }}</span><b>{{ (active.needed_by || '').slice(0, 10) || '—' }}</b></div></div>
          </div>
          <div v-if="active.notes" class="pcd-notes"><q-icon name="sticky_note_2" size="15px" class="q-mr-xs" />{{ active.notes }}</div>

          <!-- Approval timeline -->
          <div class="pcd-sec">{{ $t('ApprovalWorkflow') }}</div>
          <div class="pcd-timeline">
            <div v-for="a in active.approvals" :key="a.id" class="pcd-step" :class="'pcd-step--' + a.status">
              <div class="pcd-step__dot"><q-icon :name="a.status === 'approved' ? 'check' : a.status === 'rejected' ? 'close' : 'schedule'" size="14px" /></div>
              <div>
                <div class="pcd-step__role">{{ $t('Level') }} {{ a.level }} — {{ a.role }}</div>
                <div class="pcd-step__meta">
                  <span :style="`color:${statusColor(a.status)}`">{{ $t(cap(a.status)) }}</span>
                  <span v-if="a.approver"> · {{ a.approver.name }}</span>
                  <span v-if="a.decided_at"> · {{ (a.decided_at || '').slice(0, 10) }}</span>
                  <span v-if="a.note"> · “{{ a.note }}”</span>
                </div>
              </div>
            </div>
            <div v-if="!active.approvals?.length" class="text-caption text-grey-6 q-pa-sm">{{ $t('NoApprovalNeeded') }}</div>
          </div>

          <!-- Actions -->
          <div v-if="active.status === 'pending' && $can('payment-approve')" class="pcd-actions">
            <q-btn outline color="negative" no-caps icon="thumb_down" :label="$t('Reject')" @click="decide('reject')" />
            <q-space />
            <q-btn unelevated color="positive" no-caps icon="thumb_up" :label="`${$t('Approve')} — ${$t('Level')} ${active.current_level}`" :loading="acting" @click="decide('approve')" />
          </div>

          <!-- Process payment -->
          <div v-else-if="active.status === 'approved' && $can('payment-process')" class="pcd-pay">
            <div class="pcd-sec">{{ $t('ProcessPayment') }}</div>
            <div class="row q-col-gutter-sm">
              <div class="col-6 col-sm-4"><q-select outlined dense color="indigo-8" v-model="payForm.payment_method" :options="methodOptions" emit-value map-options :label="$t('PaymentMethod')" /></div>
              <div class="col-6 col-sm-4"><q-input outlined dense color="indigo-8" type="number" v-model.number="payForm.approved_amount" :label="$t('PayableAmount')" /></div>
              <div class="col-12 col-sm-4"><n-name :name="payForm.reference" @update:name="payForm.reference = $event" icon="tag" :label="$t('Reference')" :rules="[]" /></div>
              <div class="col-12">
                <q-toggle v-model="payForm.fingerprint_verified" :label="$t('FingerprintVerified')" color="teal-7" />
              </div>
            </div>
            <div class="pcd-actions">
              <q-space />
              <q-btn unelevated color="indigo-8" no-caps icon="paid" :label="$t('MarkPaidReceipt')" :loading="acting" @click="processPay" />
            </div>
          </div>

          <div v-else-if="active.status === 'paid'" class="pcd-paid">
            <q-icon name="verified" size="22px" color="positive" class="q-mr-xs" />
            {{ $t('PaidVia') }} <b class="q-mx-xs">{{ $t(cap(active.payment_method || 'cash')) }}</b>
            <span v-if="active.reference">· {{ active.reference }}</span>
            <q-space />
            <q-btn flat dense no-caps color="indigo-8" icon="print" :label="$t('Receipt')" @click="printReceipt(active)" />
          </div>
        </q-card-section>
      </q-card>
    </m-modal>

    <!-- New request -->
    <m-modal :showCM="newDialog" @update:showCM="newDialog = $event" card_style="width: 520px">
      <q-card class="bg-white">
        <n-header icon="add_card">{{ $t('NewPaymentRequest') }}</n-header>
        <q-separator />
        <q-form @submit="submitNew">
          <q-card-section class="row q-col-gutter-sm">
            <div class="col-12 col-sm-6"><q-select outlined dense color="indigo-8" v-model="form.type" :options="typeOptions" emit-value map-options :label="$t('Type')" /></div>
            <div class="col-12 col-sm-6"><q-select outlined dense color="indigo-8" v-model="form.priority" :options="prioOptions" emit-value map-options :label="$t('Priority')" /></div>
            <!-- Payee — a dynamic list driven by the chosen Type (employees, subcontractors,
                 investors, suppliers…). Free text for expenses/other. -->
            <div class="col-12" v-if="payeeIsList">
              <q-select outlined dense color="indigo-8" use-input input-debounce="0" clearable
                :model-value="payeeSel" :options="filteredPayeeOptions" option-label="label"
                :label="payeeLabel" @filter="filterPayee" @update:model-value="onPayeePick"
                :rules="[() => !!form.payee_name || $t('FieldIsRequired')]" hide-bottom-space="false">
                <template #prepend><q-icon name="person" color="indigo-8" /></template>
                <template #option="scope">
                  <q-item v-bind="scope.itemProps">
                    <q-item-section avatar><q-icon :name="ptypeIcon(scope.opt.ptype)" :color="ptypeColor(scope.opt.ptype)" /></q-item-section>
                    <q-item-section>
                      <q-item-label>{{ scope.opt.label }}</q-item-label>
                      <q-item-label caption>{{ $t(ptypeLabel(scope.opt.ptype)) }}<span v-if="scope.opt.sub"> · {{ scope.opt.sub }}</span></q-item-label>
                    </q-item-section>
                  </q-item>
                </template>
                <template #no-option><q-item><q-item-section class="text-grey-6">{{ $t('NoRecordFound') }}</q-item-section></q-item></template>
              </q-select>
            </div>
            <div class="col-12" v-else><n-name :name="form.payee_name" @update:name="form.payee_name = $event" icon="person" :label="$t('Payee')" /></div>
            <div class="col-12 col-sm-6"><q-select outlined dense color="indigo-8" v-model="form.project_id" :options="projectOptions" emit-value map-options clearable :label="$t('Project')" /></div>
            <div class="col-12 col-sm-6"><money-input v-model="form.requested_amount" v-model:currency="form.currency" v-model:rate="form.rate" :allow-save-rate="false" :label="$t('Amount')" /></div>
            <div class="col-12"><q-input outlined dense color="indigo-8" type="textarea" autogrow v-model="form.notes" :label="$t('Notes')" /></div>
            <div class="col-12">
              <q-file outlined dense color="indigo-8" v-model="docFiles" multiple :label="$t('AttachBill')"
                accept=".jpg,.jpeg,.png,.webp,.pdf" max-file-size="41943040" clearable counter>
                <template #prepend><q-icon name="receipt_long" color="indigo-8" /></template>
              </q-file>
            </div>
          </q-card-section>
          <q-separator />
          <n-submit :submitting="saving" :label="$t('Submit')" />
        </q-form>
      </q-card>
    </m-modal>
  </q-page>
</template>

<script setup>
import { ref, reactive, computed, watch, getCurrentInstance, onMounted } from 'vue'
import { Dialog, Notify } from 'quasar'
import { api } from '@/boot/axios'
import { useCurrency } from '@/composables/useCurrency'
import { uploadDocs } from '@/composables/useAttachments'

const { proxy } = getCurrentInstance()
const { base, loadRates } = useCurrency()

const rows = ref([])
const stats = ref({})
const loading = ref(false)
const filter = ref('pending')
const typeFilter = ref(null)
const search = ref('')
const dialog = ref(false)
const active = ref(null)
const acting = ref(false)
const newDialog = ref(false)
const saving = ref(false)
const docFiles = ref(null)
const projectOptions = ref([])

const TYPES = [
  ['salary', 'Salary', 'badge', '#4338CA'], ['subcontractor', 'Subcontractor', 'engineering', '#0D9488'],
  ['supplier', 'Supplier', 'local_shipping', '#B45309'], ['procurement', 'Procurement', 'shopping_cart', '#7C3AED'],
  ['material', 'Material', 'grain', '#0284C7'], ['asset', 'Asset', 'precision_manufacturing', '#DC2626'],
  ['advance', 'Advance', 'north_east', '#EA580C'], ['sub_advance', 'SubcontractorAdvance', 'north_east', '#EA580C'],
  ['investor_withdrawal', 'InvestorWithdrawal', 'diamond', '#DB2777'], ['expense', 'Expense', 'receipt_long', '#059669'],
  ['office_expense', 'OfficeExpense', 'business_center', '#0891B2'], ['petty_cash', 'PettyCash', 'savings', '#65A30D'],
  ['other', 'Other', 'more_horiz', '#64748B'],
]
const typeOptions = TYPES.map(t => ({ label: proxy.$t(t[1]), value: t[0] }))
function typeLabel (t) { return TYPES.find(x => x[0] === t)?.[1] || 'Other' }
function typeIcon (t) { return TYPES.find(x => x[0] === t)?.[2] || 'payments' }
function typeColor (t) { return TYPES.find(x => x[0] === t)?.[3] || '#64748B' }

const methodOptions = [
  { label: proxy.$t('Cash'), value: 'cash' }, { label: proxy.$t('Bank'), value: 'bank' },
  { label: proxy.$t('Cheque'), value: 'cheque' }, { label: proxy.$t('Hawala'), value: 'hawala' },
]
const prioOptions = ['low', 'normal', 'high', 'urgent'].map(p => ({ label: proxy.$t(cap(p)), value: p }))

const tabs = [
  { v: 'pending', label: 'Pending' }, { v: 'approved', label: 'Approved' },
  { v: 'paid', label: 'Paid' }, { v: 'rejected', label: 'Rejected' }, { v: 'all', label: 'All' },
]
const counts = computed(() => ({
  pending: stats.value.pending?.count, approved: stats.value.approved?.count,
  paid: stats.value.paid?.count, rejected: stats.value.rejected?.count, all: null,
}))
const kpis = computed(() => [
  { key: 'pending', filter: 'pending', label: 'Pending', icon: 'schedule', color: '#D97706', count: stats.value.pending?.count ?? 0, amount: stats.value.pending?.amount ?? 0 },
  { key: 'approved', filter: 'approved', label: 'Approved', icon: 'thumb_up', color: '#2563EB', count: stats.value.approved?.count ?? 0, amount: stats.value.approved?.amount ?? 0 },
  { key: 'paid', filter: 'paid', label: 'Paid', icon: 'verified', color: '#16A34A', count: stats.value.paid?.count ?? 0, amount: stats.value.paid?.amount ?? 0 },
  { key: 'overdue', filter: null, label: 'Overdue', icon: 'warning', color: '#DC2626', count: stats.value.overdue ?? 0, amount: null },
])

const payForm = reactive({ payment_method: 'cash', approved_amount: null, reference: '', fingerprint_verified: false })
const form = reactive({ type: 'subcontractor', priority: 'normal', payee_name: '', payee_type: null, payee_id: null, project_id: null, currency: 'AFN', rate: 1, requested_amount: null, notes: '' })

// ── Dynamic Payee, driven by the chosen Type ──────────────────────────────
// Each payee type is a real record so the payment links back to that person /
// company (their profile, the project, and every report).
const payeeLists = reactive({ employee: [], tradesman: [], investor: [], supplier: [] })
const payeeSel = ref(null)
const filteredPayeeOptions = ref([])

// Which entity list(s) each payment type draws its payee from.
const TYPE_PAYEE = {
  salary: ['employee'], advance: ['employee'],
  subcontractor: ['tradesman'], sub_advance: ['tradesman'],
  investor_withdrawal: ['investor'],
  supplier: ['supplier'], asset: ['supplier'],
  material: ['supplier', 'employee'], procurement: ['supplier', 'employee'],
  // expense / office_expense / petty_cash / other → free text
}
const PTYPE = {
  employee: { label: 'Employee', icon: 'badge', color: '#175A8C' },
  tradesman: { label: 'Subcontractor', icon: 'engineering', color: '#0D9488' },
  investor: { label: 'Investor', icon: 'diamond', color: '#DB2777' },
  supplier: { label: 'Supplier', icon: 'local_shipping', color: '#B45309' },
}
function ptypeLabel (t) { return PTYPE[t]?.label || 'Other' }
function ptypeIcon (t) { return PTYPE[t]?.icon || 'person' }
function ptypeColor (t) { return PTYPE[t]?.color || '#64748B' }

const payeeSources = computed(() => TYPE_PAYEE[form.type] || null)
const payeeIsList = computed(() => !!payeeSources.value)
const payeeLabel = computed(() => proxy.$t('Payee') + (payeeSources.value ? ' — ' + payeeSources.value.map(s => proxy.$t(ptypeLabel(s))).join(' / ') : ''))
const basePayeeOptions = computed(() => {
  if (!payeeSources.value) return []
  return payeeSources.value.flatMap(src => (payeeLists[src] || []).map(o => ({ ...o, ptype: src })))
})

function filterPayee (val, update) {
  const needle = (val || '').toLowerCase()
  update(() => {
    filteredPayeeOptions.value = needle
      ? basePayeeOptions.value.filter(o => o.label.toLowerCase().includes(needle) || (o.sub || '').toLowerCase().includes(needle))
      : basePayeeOptions.value
  })
}
function onPayeePick (opt) {
  payeeSel.value = opt
  form.payee_name = opt?.label || ''
  form.payee_type = opt?.ptype || null
  form.payee_id = opt?.id || null
}
function resetPayee () { payeeSel.value = null; form.payee_name = ''; form.payee_type = null; form.payee_id = null }

async function loadPayeeLists () {
  const grab = async (url, mapper) => { try { const { data } = await api.get(url); return mapper(Array.isArray(data) ? data : (data.data ?? data.tradesmen ?? [])) } catch (_) { return [] } }
  payeeLists.employee = await grab('/employees', a => a.map(e => ({ id: e.id, label: e.full_name || e.name, sub: e.designation?.title || e.department?.name || '' })))
  payeeLists.tradesman = await grab('/tradesmen', a => a.map(t => ({ id: t.id, label: t.name, sub: t.trade || t.skill || '' })))
  payeeLists.investor = await grab('/investors', a => a.map(i => ({ id: i.id, label: i.name, sub: i.type || '' })))
  payeeLists.supplier = await grab('/suppliers', a => a.map(s => ({ id: s.id, label: s.name, sub: s.category || s.phone || '' })))
}

// Switching the Type clears the chosen payee and refreshes the option list.
watch(() => form.type, () => { resetPayee(); filteredPayeeOptions.value = basePayeeOptions.value })

function cap (s) { return String(s || '').charAt(0).toUpperCase() + String(s || '').slice(1) }
function fmt (v) { return Number(v || 0).toLocaleString('en-US', { maximumFractionDigits: 2 }) }
const STATUS = { pending: '#D97706', approved: '#2563EB', paid: '#16A34A', rejected: '#DC2626', on_hold: '#64748B' }
function statusColor (s) { return STATUS[s] || '#64748B' }
function statusIcon (s) { return { pending: 'schedule', approved: 'thumb_up', paid: 'verified', rejected: 'close', on_hold: 'pause' }[s] || 'help' }
const PRIO = { low: 'blue-grey-5', normal: 'blue-grey-6', high: 'orange-7', urgent: 'red-7' }
function prioColor (p) { return PRIO[p] || 'blue-grey-6' }

async function load () {
  loading.value = true
  try {
    const params = { status: filter.value, type: typeFilter.value || undefined, q: search.value || undefined }
    const { data } = await api.get('/payment-center', { params })
    rows.value = data.data || []
    stats.value = data.stats || {}
  } catch (_) {} finally { loading.value = false }
}
async function loadProjects () { try { const { data } = await api.get('/projects'); projectOptions.value = (data || []).map(p => ({ label: p.name, value: p.id })) } catch (_) {} }

async function open (r) {
  try { const { data } = await api.get('/payment-center/' + r.id); active.value = data; payForm.approved_amount = data.approved_amount || data.requested_amount; payForm.fingerprint_verified = false; payForm.reference = ''; payForm.payment_method = 'cash'; dialog.value = true } catch (_) {}
}
async function decide (kind) {
  const doIt = async (note) => {
    acting.value = true
    try {
      const { data } = await api.put('/payment-center/' + active.value.id + '/' + kind, { note })
      active.value = data
      Notify.create({ type: 'positive', position: 'bottom', icon: kind === 'approve' ? 'thumb_up' : 'thumb_down', message: proxy.$t(kind === 'approve' ? 'Approved' : 'Rejected') })
      load()
    } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Failed' }) } finally { acting.value = false }
  }
  if (kind === 'reject') {
    Dialog.create({ title: proxy.$t('Reject'), message: proxy.$t('RejectReason'), prompt: { model: '', type: 'text' }, cancel: true }).onOk(doIt)
  } else { doIt('') }
}
async function processPay () {
  acting.value = true
  try {
    const { data } = await api.put('/payment-center/' + active.value.id + '/process', { ...payForm })
    active.value = data
    Notify.create({ type: 'positive', position: 'bottom', icon: 'paid', message: proxy.$t('PaymentProcessed') })
    load()
  } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Failed' }) } finally { acting.value = false }
}

function openNew () {
  Object.assign(form, { type: 'subcontractor', priority: 'normal', payee_name: '', payee_type: null, payee_id: null, project_id: null, currency: 'AFN', rate: 1, requested_amount: null, notes: '' })
  resetPayee()
  filteredPayeeOptions.value = basePayeeOptions.value
  newDialog.value = true
}
async function submitNew () {
  saving.value = true
  try {
    const { data } = await api.post('/payment-center', { ...form })
    if (data?.id && docFiles.value) { try { await uploadDocs('payment-request', data.id, docFiles.value) } catch (_) {} }
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: proxy.$t('RequestSubmitted') })
    newDialog.value = false; docFiles.value = null; load()
  } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Failed' }) } finally { saving.value = false }
}

function printReceipt (r) {
  const w = window.open('', '_blank'); if (!w) return
  w.document.write(`<!doctype html><html dir="auto"><head><meta charset="utf-8"><title>Receipt ${r.request_no}</title><style>
    body{font-family:'Segoe UI',Tahoma,Arial,sans-serif;margin:0;color:#1E293B}.w{max-width:520px;margin:26px auto;padding:0 26px}
    .bar{height:8px;background:linear-gradient(90deg,#312E81,#4338CA,#0EA5A4);margin:0 -26px 18px}
    h1{font-size:22px;color:#312E81;margin:0}.s{color:#64748B;font-size:12px}
    table{border-collapse:collapse;width:100%;font-size:13px;margin-top:14px}td{padding:8px 12px;border-bottom:1px solid #EDF2F7}
    .n td{background:#EEF2FF;font-weight:800;font-size:15px}.num{text-align:end;font-weight:700}
    .sign{display:flex;justify-content:space-between;margin-top:44px;gap:40px}.sign div{flex:1;text-align:center;font-size:11px;color:#64748B;border-top:1.5px solid #CBD5E1;padding-top:6px}
  </style></head><body><div class="w"><div class="bar"></div>
    <h1>Aria Herat Mohandes Zada</h1><div class="s">Payment Receipt · ${r.request_no}</div>
    <table>
      <tr><td>Payee</td><td class="num">${r.payee_name}</td></tr>
      <tr><td>Type</td><td class="num">${r.type}</td></tr>
      <tr><td>Method</td><td class="num">${r.payment_method || 'cash'}</td></tr>
      ${r.reference ? `<tr><td>Reference</td><td class="num">${r.reference}</td></tr>` : ''}
      <tr><td>Date</td><td class="num">${(r.paid_at || '').slice(0, 10)}</td></tr>
      <tr class="n"><td>Amount Paid</td><td class="num">${fmt(r.paid_amount || r.requested_amount)} ${r.currency}</td></tr>
    </table>
    <div class="sign"><div>Received By</div><div>Finance Officer</div></div>
  </div><script>window.onload=function(){setTimeout(function(){window.print()},120)}<\/script></body></html>`)
  w.document.close()
}

onMounted(() => { load(); loadProjects(); loadRates(); loadPayeeLists() })
</script>

<style scoped>
.pc-wrap { max-width: 1180px; margin: 0 auto; }
.pc-hero { position: relative; overflow: hidden; display: flex; align-items: center; gap: 16px; border-radius: 20px; padding: 24px 30px;
  background: linear-gradient(120deg, #0F172A 0%, #1E293B 40%, #334155 100%); color: #fff; box-shadow: 0 20px 44px -24px rgba(15,23,42,.8); }
.pc-hero__glow { position: absolute; right: -50px; top: -70px; width: 240px; height: 240px; border-radius: 50%; background: radial-gradient(circle, rgba(99,102,241,.5), transparent 68%); }
.pc-hero__eyebrow { font-size: 12px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; opacity: .75; display: inline-flex; align-items: center; gap: 6px; }
.pc-hero__title { font-size: 28px; font-weight: 800; margin-top: 2px; }
.pc-hero__sub { font-size: 13px; opacity: .8; margin-top: 3px; max-width: 460px; }
.pc-new { z-index: 1; background: linear-gradient(135deg, #6366F1, #4338CA); color: #fff; font-weight: 800; border-radius: 12px; padding: 10px 20px; box-shadow: 0 10px 24px -10px rgba(67,56,202,.9); }

.pc-kpi { display: flex; align-items: center; gap: 12px; background: #fff; border: 1px solid #EAEFF6; border-radius: 16px; padding: 14px 16px; cursor: pointer; transition: transform .15s; }
.pc-kpi:hover { transform: translateY(-2px); }
.pc-kpi__icon { width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #fff; flex: 0 0 auto; }
.pc-kpi__val { font-size: 20px; font-weight: 800; color: #0F172A; line-height: 1; }
.pc-kpi__lbl { font-size: 11.5px; color: #64748B; font-weight: 700; }
.pc-kpi__amt { font-size: 11px; color: #94A3B8; margin-top: 1px; }

.pc-bar { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.pc-tabs { display: flex; gap: 4px; background: #fff; border: 1px solid #E2E8F0; border-radius: 999px; padding: 4px; }
.pc-tab { border: none; background: transparent; cursor: pointer; padding: 6px 14px; border-radius: 999px; font-size: 12.5px; font-weight: 700; color: #64748B; display: flex; align-items: center; gap: 6px; }
.pc-tab--on { background: linear-gradient(135deg, #4338CA, #6366F1); color: #fff; }
.pc-tab__n { background: rgba(255,255,255,.25); border-radius: 999px; padding: 0 6px; font-size: 11px; }
.pc-tab:not(.pc-tab--on) .pc-tab__n { background: #EEF2FF; color: #4338CA; }

.pc-empty { text-align: center; padding: 44px 0; color: #94A3B8; background: #fff; border: 1px dashed #CBD5E1; border-radius: 16px; }
.pc-list { display: flex; flex-direction: column; gap: 8px; }
.pc-row { display: flex; align-items: center; gap: 14px; background: #fff; border: 1px solid #EEF2F7; border-radius: 14px; padding: 12px 16px; cursor: pointer; transition: box-shadow .15s, transform .15s; }
.pc-row:hover { box-shadow: 0 12px 26px -18px rgba(15,23,42,.5); transform: translateY(-1px); }
.pc-row__type { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; }
.pc-row__main { flex: 1; min-width: 0; }
.pc-row__payee { font-weight: 800; color: #1E293B; }
.pc-row__no { font-size: 11px; color: #94A3B8; font-weight: 700; margin-inline-start: 6px; }
.pc-row__meta { font-size: 12px; color: #64748B; }
.pc-flow { display: flex; align-items: center; gap: 4px; }
.pc-flow__dot { width: 20px; height: 20px; border-radius: 50%; font-size: 11px; font-weight: 800; display: flex; align-items: center; justify-content: center; background: #F1F5F9; color: #94A3B8; }
.pc-flow__dot--approved { background: #DCFCE7; color: #16A34A; }
.pc-flow__dot--rejected { background: #FEE2E2; color: #DC2626; }
.pc-flow__auto { font-size: 11px; color: #94A3B8; font-style: italic; }
.pc-row__amt { text-align: right; min-width: 130px; }
.pc-row__amt-v { font-size: 16px; font-weight: 800; color: #0F172A; }
.pc-row__amt-v small { font-size: 10px; color: #94A3B8; }
.pc-row__status { margin-top: 3px; }

/* detail */
.pcd-head { display: flex; align-items: center; padding: 16px 20px; color: #fff; }
.pcd-head__payee { font-size: 18px; font-weight: 800; }
.pcd-head__sub { font-size: 12px; opacity: .9; }
.pcd-head__amt { font-size: 20px; font-weight: 800; }
.pcd-fact { background: #fff; border: 1px solid #EEF2F7; border-radius: 10px; padding: 8px 12px; }
.pcd-fact span { display: block; font-size: 10.5px; color: #94A3B8; text-transform: uppercase; letter-spacing: .5px; font-weight: 700; }
.pcd-fact b { font-size: 14px; color: #0F172A; }
.pcd-notes { background: #FFFBEB; border: 1px solid #FDE68A; color: #92400E; border-radius: 10px; padding: 8px 12px; font-size: 12.5px; margin-bottom: 8px; }
.pcd-sec { font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: .6px; color: #4338CA; margin: 12px 0 8px; }
.pcd-timeline { display: flex; flex-direction: column; gap: 6px; }
.pcd-step { display: flex; align-items: center; gap: 10px; background: #fff; border: 1px solid #EEF2F7; border-radius: 10px; padding: 8px 12px; }
.pcd-step__dot { width: 26px; height: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: #F1F5F9; color: #94A3B8; flex: 0 0 auto; }
.pcd-step--approved .pcd-step__dot { background: #DCFCE7; color: #16A34A; }
.pcd-step--rejected .pcd-step__dot { background: #FEE2E2; color: #DC2626; }
.pcd-step__role { font-weight: 700; font-size: 13px; color: #1E293B; }
.pcd-step__meta { font-size: 11.5px; color: #64748B; }
.pcd-actions { display: flex; align-items: center; margin-top: 14px; gap: 8px; }
.pcd-pay { background: #F8FAFC; border-radius: 12px; padding: 12px 14px; margin-top: 10px; }
.pcd-paid { display: flex; align-items: center; background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 12px; padding: 12px 14px; margin-top: 10px; font-size: 13px; color: #166534; }
</style>
