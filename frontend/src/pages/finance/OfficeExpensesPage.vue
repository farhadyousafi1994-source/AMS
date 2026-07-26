<template>
  <q-page>
    <m-backgrounds>
      <div class="row my_radio_less q-pa-sm">
        <!-- Hero -->
        <div class="col-12">
          <div class="ox-hero">
            <div class="ox-hero__bar">
              <div class="ox-hero__head">
                <div class="ox-hero__title">
                  <div class="ox-hero__icon"><q-icon name="business_center" size="26px" /></div>
                  <div>
                    <div class="ox-hero__name">{{ $t('OfficeExpenses') }}</div>
                    <div class="ox-hero__meta">
                      <span class="ox-hero__pill"><q-icon name="groups" size="13px" /> {{ partners.rows.length }} {{ $t('Partners') }}</span>
                      <span class="ox-hero__pill" v-if="d.summary.pending"><q-icon name="hourglass_top" size="13px" /> {{ d.summary.pending }} {{ $t('Pending') }}</span>
                    </div>
                  </div>
                </div>
                <div class="q-gutter-xs row items-center">
                  <q-btn flat dense icon="print" color="white" :label="$t('Print')" @click="printReport" />
                  <progress-btn color="white" text-color="primary" icon="add" v-if="$can('office-expense-create')" @click="openCreate">{{ $t('AddNew') }}</progress-btn>
                </div>
              </div>
            </div>
            <div class="row q-col-gutter-sm ox-hero__stats">
              <div class="col-6 col-md-3" v-for="s in heroStats" :key="s.label">
                <div class="kpi-tile"><q-icon :name="s.icon" size="20px" class="kpi-tile__icon" /><div class="kpi-tile__val">{{ s.value }}</div><div class="kpi-tile__lbl">{{ $t(s.label) }}</div></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Pills -->
        <div class="col-12 q-mt-md">
          <div class="dash-nav">
            <button v-for="s in sections" :key="s.name" type="button" class="dash-pill" :class="{ 'dash-pill--active': tab === s.name }" @click="tab = s.name">
              <span class="dash-pill__orb"><q-icon :name="s.icon" size="14px" /></span>
              <span class="dash-pill__label">{{ $t(s.label) }}</span>
            </button>
          </div>
        </div>

        <div class="col-12 q-mt-sm">
          <q-card flat bordered class="my_radio_less dash-body">
            <div class="q-px-md q-pt-md">
              <tab-title :title="$t(activeSection.label)" :icon="activeSection.icon" />
            </div>
            <q-tab-panels v-model="tab" animated>
              <!-- OVERVIEW -->
              <q-tab-panel name="overview">
                <div class="row q-col-gutter-md">
                  <div class="col-12 col-md-7">
                    <div class="text-subtitle2 q-mb-xs">{{ $t('MonthlyTrend') }}</div>
                    <div class="bars">
                      <div v-for="m in d.monthly" :key="m.period" class="bars__col">
                        <div class="bars__bar" :style="`height:${barH(m.total)}%`"><q-tooltip>{{ fmt(m.total) }} {{ base }}</q-tooltip></div>
                        <div class="bars__lbl">{{ m.period.slice(2) }}</div>
                      </div>
                    </div>
                  </div>
                  <div class="col-12 col-md-5">
                    <div class="text-subtitle2 q-mb-xs">{{ $t('RecentExpenses') }}</div>
                    <q-markup-table flat bordered dense class="my_radio_less">
                      <thead class="bg-theme-soft"><tr><th class="text-left">{{ $t('Date') }}</th><th class="text-left">{{ $t('Category') }}</th><th class="text-right">{{ $t('Amount') }}</th></tr></thead>
                      <tbody>
                        <tr v-for="r in d.recent" :key="r.id"><td>{{ (r.expense_date || '').slice(0, 10) }}</td><td>{{ r.category }}</td><td class="text-right">{{ fmt(r.amount_base) }}</td></tr>
                      </tbody>
                    </q-markup-table>
                  </div>
                </div>
              </q-tab-panel>

              <!-- FULL TABLE -->
              <q-tab-panel name="all">
                <div class="row items-center justify-between q-mb-sm">
                  <div class="text-subtitle2">{{ $t('AllExpenses') }} ({{ rows.length }})</div>
                  <q-input outlined dense color="primary" v-model="tableFilter" :placeholder="$t('Search')" clearable style="max-width:240px"><template #prepend><q-icon name="search" color="primary" /></template></q-input>
                </div>
                <n-table config-key="page.officeExpenses" :loading="loading" :data="rows" :columns="columns" v-model:filter="tableFilter"
                  :can_edit="'office-expense-edit'" :can_delete="'office-expense-delete'" :noInfo="true" @edit="openEdit" @del="remove">
                  <template v-slot:body-cell-approval_status="props">
                    <q-td :props="props" class="text-center">
                      <q-chip v-if="props.row.approval_status === 'pending'" dense size="sm" color="amber-8" text-color="white">{{ $t('Pending') }}</q-chip>
                      <q-btn v-if="props.row.approval_status === 'pending' && $can('expense-approve')" size="sm" dense flat round icon="task_alt" color="positive" @click="approve(props.row)"><q-tooltip>{{ $t('Approve') }}</q-tooltip></q-btn>
                      <q-icon v-else-if="props.row.approval_status === 'approved'" name="check_circle" color="positive" size="16px" />
                      <q-icon v-else name="cancel" color="negative" size="16px" />
                    </q-td>
                  </template>
                  <template v-slot:body-cell-amount_base="props"><q-td :props="props" class="text-right text-weight-medium">{{ fmt(props.row.amount_base) }} {{ base }}</q-td></template>
                  <template v-slot:body-cell-attach="props">
                    <q-td :props="props" class="text-center"><q-btn v-if="props.row.attachment_path" size="sm" dense flat round icon="attachment" color="indigo-7" @click="viewAttach(props.row)" /><span v-else class="text-grey-4">—</span></q-td>
                  </template>
                </n-table>
              </q-tab-panel>

              <!-- BREAKDOWNS -->
              <q-tab-panel name="category"><breakdown :rows="d.by_category" :base="base" :label="$t('Category')" /></q-tab-panel>
              <q-tab-panel name="vendor"><breakdown :rows="d.by_vendor" :base="base" :label="$t('Vendor')" /></q-tab-panel>
              <q-tab-panel name="method"><breakdown :rows="d.by_method" :base="base" :label="$t('PaymentMethod')" mapMethod /></q-tab-panel>

              <!-- PARTNER SHARES -->
              <q-tab-panel name="partners">
                <div class="ps-note q-mb-md"><q-icon name="info" size="15px" class="q-mr-xs" />{{ $t('PartnerShareNote') }}</div>
                <div class="text-subtitle2 q-mb-sm">{{ $t('PartnerShare') }} — {{ $t('ThisYear') }}: <b>{{ fmt(partners.year_total) }} {{ base }}</b></div>
                <q-markup-table flat bordered dense class="my_radio_less">
                  <thead class="bg-theme-soft"><tr><th class="text-left">{{ $t('Partner') }}</th><th class="text-center">{{ $t('Share') }}</th><th class="text-right">{{ $t('ShareAmount') }}</th></tr></thead>
                  <tbody>
                    <tr v-for="(p, i) in partners.rows" :key="i">
                      <td class="text-weight-medium"><q-icon name="engineering" size="15px" color="blue-grey-6" class="q-mr-xs" />{{ p.name }}</td>
                      <td class="text-center"><q-chip dense size="sm" color="blue-1" text-color="blue-9">{{ p.share_percent }}%</q-chip></td>
                      <td class="text-right text-weight-bold">{{ fmt(p.share_amount) }} {{ base }}</td>
                    </tr>
                  </tbody>
                </q-markup-table>
              </q-tab-panel>

              <!-- YEARLY -->
              <q-tab-panel name="yearly"><breakdown :rows="d.yearly.map(y => ({ name: y.year, total: y.total }))" :base="base" :label="$t('Year')" /></q-tab-panel>
            </q-tab-panels>
          </q-card>
        </div>
      </div>
    </m-backgrounds>

    <!-- Add / edit -->
    <m-modal :showCM="dialog" @update:showCM="dialog = $event" card_style="width: 560px">
      <q-card class="bg-white">
        <n-header icon="business_center">{{ form.id ? $t('Edit') : $t('AddNew') }} — {{ $t('OfficeExpenses') }}</n-header>
        <q-separator />
        <q-form @submit="save">
          <q-card-section class="row q-col-gutter-sm">
            <div class="col-12 col-sm-6"><lookup-select v-model="form.category" group="expense_category" icon="receipt_long" allow-other :label="$t('Category')" /></div>
            <div class="col-6 col-sm-3"><shamsi-date v-model="form.expense_date" color="primary" :label="$t('Date')" /></div>
            <div class="col-6 col-sm-3"><q-select outlined dense color="primary" v-model="form.payment_method" :options="methodOptions" emit-value map-options :label="$t('PaymentMethod')" /></div>
            <div class="col-6 col-sm-3" v-if="form.payment_method === 'other'"><q-input outlined dense color="primary" v-model="otherMethod" :label="$t('DescribeOther')" :rules="[v => !!v || $t('FieldIsRequired')]" /></div>
            <div class="col-12 col-sm-6"><n-name :name="form.vendor" @update:name="form.vendor = $event" icon="storefront" :label="$t('Vendor')" :rules="[]" /></div>
            <div class="col-12 col-sm-6"><money-input v-model="form.amount" v-model:currency="form.currency" v-model:rate="form.rate" :label="$t('Amount')" /></div>
            <div class="col-12"><q-select outlined dense color="primary" v-model="form.approval_status" :options="[{ label: $t('Approved'), value: 'approved' }, { label: $t('Pending'), value: 'pending' }]" emit-value map-options :label="$t('Status')" /></div>
            <div class="col-12"><q-file outlined dense color="primary" v-model="file" :label="$t('AttachReceipt')" accept=".jpg,.jpeg,.png,.webp,.pdf" max-file-size="41943040" clearable><template #prepend><q-icon name="attach_file" color="primary" /></template></q-file></div>
            <div class="col-12"><q-input outlined dense color="primary" type="textarea" autogrow v-model="form.description" :label="$t('Notes')" /></div>
          </q-card-section>
          <q-separator />
          <n-submit :submitting="saving" :label="$t('Save')" />
        </q-form>
      </q-card>
    </m-modal>
  </q-page>
</template>

<script setup>
import { ref, reactive, computed, getCurrentInstance, onMounted, h } from 'vue'
import { Notify } from 'quasar'
import { api } from '@/boot/axios'
import { useCurrency } from '@/composables/useCurrency'
import { compressImage } from '@/utils/image'

const { proxy } = getCurrentInstance()
const { base, loadRates, rateFor } = useCurrency()

// Tiny inline breakdown component (bar + amount per row).
const breakdown = {
  props: ['rows', 'base', 'label', 'mapMethod'],
  setup (props) {
    const max = computed(() => Math.max(1, ...(props.rows || []).map(r => Number(r.total || 0))))
    const fmtN = (v) => Number(v || 0).toLocaleString('en-US')
    const mm = (m) => ({ cash: 'Cash', bank: 'Bank', hawala: 'Hawala', card: 'Card', other: 'Other' }[m] || m || '—')
    return () => h('div', { class: 'bd' }, (props.rows || []).length
      ? props.rows.map(r => h('div', { class: 'bd__row' }, [
        h('div', { class: 'bd__name' }, props.mapMethod ? mm(r.name) : (r.name ?? '—')),
        h('div', { class: 'bd__track' }, [h('div', { class: 'bd__fill', style: `width:${(Number(r.total) / max.value) * 100}%` })]),
        h('div', { class: 'bd__val' }, `${fmtN(r.total)} ${props.base}`),
      ]))
      : h('div', { class: 'text-center text-grey-5 q-py-md' }, '—'))
  },
}

const d = ref({ summary: { this_month: 0, this_year: 0, all_time: 0, pending: 0, count: 0 }, by_category: [], by_vendor: [], by_method: [], monthly: [], yearly: [], recent: [] })
const partners = ref({ year_total: 0, rows: [] })
const rows = ref([])
const loading = ref(false)
const saving = ref(false)
const dialog = ref(false)
const file = ref(null)
const tab = ref('overview')
const tableFilter = ref('')

const methodOptions = [{ label: 'Cash', value: 'cash' }, { label: 'Bank', value: 'bank' }, { label: 'Hawala', value: 'hawala' }, { label: 'Card', value: 'card' }, { label: 'Other', value: 'other' }]
const officeCategories = ['Office Rent', 'Electricity', 'Internet', 'Office Equipment', 'Furniture', 'Refreshments', 'Maintenance', 'Administrative', 'Utilities', 'General']

const sections = [
  { name: 'overview', label: 'Overview', icon: 'dashboard' },
  { name: 'all', label: 'AllExpenses', icon: 'receipt_long' },
  { name: 'category', label: 'ByCategory', icon: 'category' },
  { name: 'vendor', label: 'ByVendor', icon: 'storefront' },
  { name: 'method', label: 'ByMethod', icon: 'payments' },
  { name: 'partners', label: 'PartnerShare', icon: 'groups' },
  { name: 'yearly', label: 'Yearly', icon: 'calendar_month' },
]
const activeSection = computed(() => sections.find(s => s.name === tab.value) || sections[0])

const columns = [
  { name: 'expense_date', label: 'Date', field: 'expense_date', align: 'left', sortable: true, format: v => (v || '').slice(0, 10) },
  { name: 'category', label: 'Category', field: 'category', align: 'left', sortable: true },
  { name: 'vendor', label: 'Vendor', field: 'vendor', align: 'left' },
  { name: 'payment_method', label: 'PaymentMethod', field: 'payment_method', align: 'left' },
  { name: 'amount_base', label: 'Amount', field: 'amount_base', align: 'right', sortable: true },
  { name: 'attach', label: 'Receipt', field: 'attach', align: 'center' },
  { name: 'approval_status', label: 'Status', field: 'approval_status', align: 'center' },
  { name: 'actions', label: 'Actions', field: 'actions', align: 'right' },
]

function fmt (v) { return Number(v || 0).toLocaleString('en-US') }
function barH (v) { const max = Math.max(1, ...d.value.monthly.map(m => Number(m.total || 0))); return Math.max(3, (Number(v) / max) * 100) }

const heroStats = computed(() => [
  { label: 'ThisMonth', value: fmt(d.value.summary.this_month) + ' ' + base.value, icon: 'calendar_today' },
  { label: 'ThisYear', value: fmt(d.value.summary.this_year) + ' ' + base.value, icon: 'calendar_month' },
  { label: 'AllTime', value: fmt(d.value.summary.all_time) + ' ' + base.value, icon: 'savings' },
  { label: 'PerPartner', value: fmt(partners.value.rows[0]?.share_amount) + ' ' + base.value, icon: 'groups' },
])

const blank = () => ({ id: null, type: 'office', expense_date: new Date().toISOString().slice(0, 10), category: 'Office Rent', vendor: '', payment_method: 'cash', amount: null, currency: 'AFN', rate: 1, approval_status: 'approved', description: '' })
const form = reactive(blank())
const otherMethod = ref('')
const knownMethods = ['cash', 'bank', 'hawala', 'card', 'other']

async function loadDash () { try { const { data } = await api.get('/office-expenses/dashboard'); d.value = data; partners.value = data.partners || partners.value } catch (_) {} }
async function loadRows () { loading.value = true; try { const { data } = await api.get('/expenses', { params: { type: 'office' } }); rows.value = Array.isArray(data) ? data : [] } finally { loading.value = false } }
async function reload () { await Promise.all([loadDash(), loadRows()]) }

function openCreate () { Object.assign(form, blank()); otherMethod.value = ''; file.value = null; dialog.value = true }
function openEdit (id) {
  const r = rows.value.find(x => x.id === id); if (!r) return
  const method = r.payment_method || 'cash'
  const isCustom = method && !knownMethods.includes(method)
  otherMethod.value = isCustom ? method : ''
  Object.assign(form, { id: r.id, type: 'office', expense_date: (r.expense_date || '').slice(0, 10), category: r.category, vendor: r.vendor || '', payment_method: isCustom ? 'other' : method, amount: Number(r.amount), currency: r.currency, rate: Number(r.rate || 1), approval_status: r.approval_status, description: r.description || '' })
  file.value = null; dialog.value = true
}
async function save () {
  saving.value = true
  try {
    const fd = new FormData()
    Object.entries(form).forEach(([k, v]) => { if (v !== null && v !== '' && k !== 'id') fd.append(k, v) })
    // "Other" payment method → store the described value in its place
    if (form.payment_method === 'other' && otherMethod.value) fd.set('payment_method', otherMethod.value)
    if (file.value) fd.append('attachment', file.value.type?.startsWith('image/') ? await compressImage(file.value) : file.value)
    if (form.id) { fd.append('_method', 'PUT'); await api.post('/expenses/' + form.id, fd, { headers: { 'Content-Type': 'multipart/form-data' } }) }
    else await api.post('/expenses', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: 'Saved' })
    dialog.value = false; reload()
  } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Save failed' }) } finally { saving.value = false }
}
function remove (id) { proxy.$delete('expenses/' + id, reload) }
async function approve (r) { try { await api.put('/expenses/' + r.id + '/approve', { decision: 'approved' }); Notify.create({ type: 'positive', position: 'bottom', message: 'Approved' }); reload() } catch (e) { Notify.create({ type: 'negative', message: 'Failed' }) } }
async function viewAttach (r) { try { const res = await api.get('/expenses/' + r.id + '/attachment', { responseType: 'blob' }); window.open(URL.createObjectURL(new Blob([res.data], { type: r.attachment_mime })), '_blank') } catch (_) {} }

function printReport () {
  const esc = s => String(s ?? '—').replace(/</g, '&lt;')
  const rowsHtml = d.value.by_category.map(c => `<tr><td>${esc(c.name)}</td><td style="text-align:end">${fmt(c.total)} ${base.value}</td></tr>`).join('')
  const psHtml = partners.value.rows.map(p => `<tr><td>${esc(p.name)}</td><td>${p.share_percent}%</td><td style="text-align:end">${fmt(p.share_amount)} ${base.value}</td></tr>`).join('')
  const html = `<!DOCTYPE html><html dir="rtl"><head><meta charset="utf-8"><title>Office Expenses</title><style>body{font-family:Arial;margin:24px;font-size:12px;color:#1E293B}h1{color:#123A66;font-size:19px;margin:0}h3{color:#175A8C;margin:14px 0 4px}table{border-collapse:collapse;width:100%;font-size:11.5px}th{background:#EEF4FB;text-align:start;padding:5px 7px;border:1px solid #CBD5E1}td{padding:5px 7px;border:1px solid #E2E8F0}</style></head><body>
    <h1>راپور مصارف دفتر</h1><div>${new Date().toLocaleDateString()} · امسال: ${fmt(d.value.summary.this_year)} ${base.value}</div>
    <h3>بر اساس نوعیت</h3><table><thead><tr><th>نوعیت</th><th>مبلغ</th></tr></thead><tbody>${rowsHtml}</tbody></table>
    <h3>سهم شرکا</h3><table><thead><tr><th>شریک</th><th>سهم</th><th>مبلغ</th></tr></thead><tbody>${psHtml}</tbody></table>
    <script>window.onload=()=>window.print()<\/script></body></html>`
  const w = window.open('', '_blank'); if (!w) return; w.document.write(html); w.document.close()
}

onMounted(() => { loadRates(); reload() })
</script>

<style scoped>
.ox-hero__bar { background: linear-gradient(135deg, #123A66 0%, #175A8C 55%, #1E6BA8 100%); border-radius: 14px; padding: 16px 18px; color: #fff; box-shadow: 0 10px 26px -12px rgba(18, 58, 102, 0.6); }
.ox-hero__head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
.ox-hero__title { display: flex; align-items: center; gap: 12px; }
.ox-hero__icon { width: 46px; height: 46px; border-radius: 12px; background: rgba(255, 255, 255, 0.14); display: flex; align-items: center; justify-content: center; border: 1px solid rgba(255, 255, 255, 0.25); }
.ox-hero__name { font-size: 20px; font-weight: 800; }
.ox-hero__meta { display: flex; gap: 6px; margin-top: 6px; }
.ox-hero__pill { display: inline-flex; align-items: center; gap: 3px; font-size: 11.5px; padding: 2px 8px; border-radius: 20px; background: rgba(255, 255, 255, 0.14); border: 1px solid rgba(255, 255, 255, 0.2); }
.ox-hero__stats { margin-top: 10px; }
.kpi-tile { border: 1.5px solid #E2E8F0; border-radius: 12px; padding: 12px 14px; background: #fff; height: 100%; }
.kpi-tile__icon { color: var(--q-primary); opacity: 0.85; }
.kpi-tile__val { font-size: 16px; font-weight: 800; margin-top: 4px; color: #1E293B; }
.kpi-tile__lbl { font-size: 11px; color: #94A3B8; margin-top: 1px; }
.dash-nav { display: flex; gap: 4px; background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(10px); border: 1px solid #E2E8F0; border-radius: 999px; padding: 5px 8px; box-shadow: 0 10px 30px -14px rgba(18, 58, 102, 0.35); width: fit-content; max-width: 100%; margin: 0 auto; overflow-x: auto; position: sticky; top: 8px; z-index: 10; }
.dash-pill { display: flex; align-items: center; gap: 6px; border: none; background: transparent; cursor: pointer; padding: 5px 11px; border-radius: 999px; color: #64748B; font-size: 12px; font-weight: 700; transition: all 0.25s ease; white-space: nowrap; }
.dash-pill__orb { width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: #F1F5F9; }
.dash-pill--active { background: linear-gradient(135deg, #123A66, #1E6BA8); color: #fff; box-shadow: 0 6px 18px -6px rgba(18, 58, 102, 0.55); }
.dash-pill--active .dash-pill__orb { background: rgba(255, 255, 255, 0.18); color: #fff; }
.dash-body { border-radius: 14px; }
@media (max-width: 900px) { .dash-pill__label { display: none; } }
.bars { display: flex; align-items: flex-end; gap: 6px; height: 180px; padding: 8px; border: 1px solid #E2E8F0; border-radius: 10px; }
.bars__col { flex: 1; display: flex; flex-direction: column; align-items: center; height: 100%; justify-content: flex-end; }
.bars__bar { width: 70%; background: linear-gradient(180deg, #2E6DA4, #123A66); border-radius: 4px 4px 0 0; min-height: 3px; }
.bars__lbl { font-size: 9px; color: #94A3B8; margin-top: 3px; }
.bd__row { display: flex; align-items: center; gap: 10px; padding: 5px 0; }
.bd__name { width: 130px; font-size: 12.5px; font-weight: 600; color: #334155; }
.bd__track { flex: 1; height: 12px; background: #F1F5F9; border-radius: 6px; overflow: hidden; }
.bd__fill { height: 100%; background: linear-gradient(90deg, #2E6DA4, #1E6BA8); border-radius: 6px; }
.bd__val { width: 130px; text-align: end; font-size: 12.5px; font-weight: 700; color: #123A66; }
.ps-note { display: flex; align-items: center; font-size: 12.5px; color: #1D4ED8; background: #DBEAFE; border: 1px dashed #3B82F6; border-radius: 8px; padding: 7px 10px; }
@media (prefers-color-scheme: dark) { .kpi-tile { background: #1E293B; border-color: #334155; } .kpi-tile__val { color: #F1F5F9; } }
</style>
