<template>
  <q-page>
    <m-backgrounds>
      <div class="row my_radio_less q-pa-sm">
        <!-- Hero -->
        <div class="col-12">
          <div class="hx-hero">
            <div class="hx-hero__bar">
              <div class="hx-hero__head">
                <div class="hx-hero__title">
                  <div class="hx-hero__icon"><q-icon name="home" size="26px" /></div>
                  <div>
                    <div class="hx-hero__name">{{ $t('HomeExpenses') }}</div>
                    <div class="hx-hero__meta"><span class="hx-hero__pill"><q-icon name="savings" size="13px" /> {{ $t('BudgetTracking') }}</span></div>
                  </div>
                </div>
                <div class="q-gutter-xs row items-center">
                  <q-btn flat dense icon="tune" color="white" :label="$t('SetBudget')" v-if="$can('expense-budget-create')" @click="budgetDialog = true" />
                  <progress-btn color="white" text-color="primary" icon="add" v-if="$can('home-expense-create')" @click="openCreate">{{ $t('AddNew') }}</progress-btn>
                </div>
              </div>
              <!-- budget vs actual progress for current month -->
              <div class="hx-hero__progress">
                <div class="row items-center justify-between">
                  <div class="text-caption" style="opacity:.85">{{ $t('BudgetUsedThisMonth') }}</div>
                  <div class="text-weight-bold">{{ budgetPct }}%</div>
                </div>
                <q-linear-progress rounded size="12px" :value="budgetPct / 100" :color="budgetPct > 100 ? 'red-4' : 'amber-4'" track-color="white" class="q-mt-xs" style="opacity:.95" />
              </div>
            </div>
            <div class="row q-col-gutter-sm hx-hero__stats">
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
                <div class="text-subtitle2 q-mb-xs">{{ $t('MonthlyTrend') }}</div>
                <div class="bars q-mb-md">
                  <div v-for="m in d.monthly" :key="m.period" class="bars__col">
                    <div class="bars__bar" :style="`height:${barH(m.total)}%`"><q-tooltip>{{ fmt(m.total) }} {{ base }}</q-tooltip></div>
                    <div class="bars__lbl">{{ m.period.slice(2) }}</div>
                  </div>
                </div>
                <div class="text-subtitle2 q-mb-xs">{{ $t('RecentExpenses') }}</div>
                <q-markup-table flat bordered dense class="my_radio_less">
                  <thead class="bg-theme-soft"><tr><th class="text-left">{{ $t('Date') }}</th><th class="text-left">{{ $t('Category') }}</th><th class="text-left">{{ $t('PaymentMethod') }}</th><th class="text-right">{{ $t('Amount') }}</th></tr></thead>
                  <tbody><tr v-for="r in d.recent" :key="r.id"><td>{{ (r.expense_date || '').slice(0, 10) }}</td><td>{{ r.category }}</td><td>{{ r.payment_method }}</td><td class="text-right">{{ fmt(r.amount_base) }}</td></tr></tbody>
                </q-markup-table>
              </q-tab-panel>

              <!-- ALL -->
              <q-tab-panel name="all">
                <div class="row items-center justify-between q-mb-sm">
                  <div class="text-subtitle2">{{ $t('AllExpenses') }} ({{ rows.length }})</div>
                  <q-input outlined dense color="primary" v-model="tableFilter" :placeholder="$t('Search')" clearable style="max-width:240px"><template #prepend><q-icon name="search" color="primary" /></template></q-input>
                </div>
                <n-table config-key="page.homeExpenses" :loading="loading" :data="rows" :columns="columns" v-model:filter="tableFilter"
                  :can_edit="'home-expense-edit'" :can_delete="'home-expense-delete'" :noInfo="true" @edit="openEdit" @del="remove">
                  <template v-slot:body-cell-amount_base="props"><q-td :props="props" class="text-right text-weight-medium">{{ fmt(props.row.amount_base) }} {{ base }}</q-td></template>
                  <template v-slot:body-cell-attach="props"><q-td :props="props" class="text-center"><q-btn v-if="props.row.attachment_path" size="sm" dense flat round icon="attachment" color="indigo-7" @click="viewAttach(props.row)" /><span v-else class="text-grey-4">—</span></q-td></template>
                </n-table>
              </q-tab-panel>

              <!-- BY CATEGORY -->
              <q-tab-panel name="category"><breakdown :rows="d.by_category" :base="base" /></q-tab-panel>

              <!-- BUDGET VS ACTUAL -->
              <q-tab-panel name="budget">
                <div class="text-subtitle2 q-mb-sm">{{ $t('BudgetVsActual') }}</div>
                <q-markup-table flat bordered dense class="my_radio_less">
                  <thead class="bg-theme-soft"><tr><th class="text-left">{{ $t('Month') }}</th><th class="text-right">{{ $t('Budget') }}</th><th class="text-right">{{ $t('Actual') }}</th><th class="text-right">{{ $t('Variance') }}</th><th style="width:160px">{{ $t('Usage') }}</th></tr></thead>
                  <tbody>
                    <tr v-for="b in d.budget.series" :key="b.period">
                      <td class="text-weight-medium">{{ b.period }}</td>
                      <td class="text-right">{{ fmt(b.budget) }}</td>
                      <td class="text-right">{{ fmt(b.actual) }}</td>
                      <td class="text-right text-weight-bold" :class="b.variance < 0 ? 'text-negative' : 'text-positive'">{{ fmt(b.variance) }}</td>
                      <td><q-linear-progress rounded size="12px" :value="b.budget ? Math.min(1, b.actual / b.budget) : 0" :color="b.budget && b.actual > b.budget ? 'negative' : 'primary'" track-color="grey-3" /></td>
                    </tr>
                  </tbody>
                </q-markup-table>
              </q-tab-panel>

              <!-- YEARLY -->
              <q-tab-panel name="yearly"><breakdown :rows="d.yearly.map(y => ({ name: y.year, total: y.total }))" :base="base" /></q-tab-panel>
            </q-tab-panels>
          </q-card>
        </div>
      </div>
    </m-backgrounds>

    <!-- Add / edit -->
    <m-modal :showCM="dialog" @update:showCM="dialog = $event" card_style="width: 540px">
      <q-card class="bg-white">
        <n-header icon="home">{{ form.id ? $t('Edit') : $t('AddNew') }} — {{ $t('HomeExpenses') }}</n-header>
        <q-separator />
        <q-form @submit="save">
          <q-card-section class="row q-col-gutter-sm">
            <div class="col-12 col-sm-6"><lookup-select v-model="form.category" group="expense_category" icon="receipt_long" allow-other :label="$t('Category')" /></div>
            <div class="col-6 col-sm-3"><shamsi-date v-model="form.expense_date" color="primary" :label="$t('Date')" /></div>
            <div class="col-6 col-sm-3"><q-select outlined dense color="primary" v-model="form.payment_method" :options="methodOptions" emit-value map-options :label="$t('PaymentMethod')" /></div>
            <div class="col-6 col-sm-3" v-if="form.payment_method === 'other'"><q-input outlined dense color="primary" v-model="otherMethod" :label="$t('DescribeOther')" :rules="[v => !!v || $t('FieldIsRequired')]" /></div>
            <div class="col-12 col-sm-8"><money-input v-model="form.amount" v-model:currency="form.currency" v-model:rate="form.rate" :label="$t('Amount')" /></div>
            <div class="col-12"><q-file outlined dense color="primary" v-model="file" :label="$t('AttachReceipt')" accept=".jpg,.jpeg,.png,.webp,.pdf" max-file-size="41943040" clearable><template #prepend><q-icon name="attach_file" color="primary" /></template></q-file></div>
            <div class="col-12"><q-input outlined dense color="primary" type="textarea" autogrow v-model="form.description" :label="$t('Notes')" /></div>
          </q-card-section>
          <q-separator />
          <n-submit :submitting="saving" :label="$t('Save')" />
        </q-form>
      </q-card>
    </m-modal>

    <!-- Set budget -->
    <m-modal :showCM="budgetDialog" @update:showCM="budgetDialog = $event" card_style="width: 400px">
      <q-card class="bg-white">
        <n-header icon="tune">{{ $t('SetBudget') }}</n-header><q-separator />
        <q-form @submit="saveBudget">
          <q-card-section class="row q-col-gutter-sm">
            <div class="col-7"><q-input outlined dense color="primary" v-model="budgetForm.period" :label="$t('Month') + ' (YYYY-MM)'" mask="####-##" /></div>
            <div class="col-5"><q-input outlined dense color="primary" type="number" step="any" v-model.number="budgetForm.amount" :label="$t('Budget')" /></div>
          </q-card-section>
          <q-separator /><n-submit :submitting="savingBudget" :label="$t('Save')" />
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

const breakdown = {
  props: ['rows', 'base'],
  setup (props) {
    const max = computed(() => Math.max(1, ...(props.rows || []).map(r => Number(r.total || 0))))
    const fmtN = (v) => Number(v || 0).toLocaleString('en-US')
    return () => h('div', { class: 'bd' }, (props.rows || []).length
      ? props.rows.map(r => h('div', { class: 'bd__row' }, [
        h('div', { class: 'bd__name' }, r.name ?? '—'),
        h('div', { class: 'bd__track' }, [h('div', { class: 'bd__fill', style: `width:${(Number(r.total) / max.value) * 100}%` })]),
        h('div', { class: 'bd__val' }, `${fmtN(r.total)} ${props.base}`),
      ]))
      : h('div', { class: 'text-center text-grey-5 q-py-md' }, '—'))
  },
}

const d = ref({ summary: { this_month: 0, this_year: 0, all_time: 0 }, by_category: [], monthly: [], yearly: [], recent: [], budget: { series: [], current: { budget: 0, actual: 0, variance: 0 } } })
const rows = ref([])
const loading = ref(false)
const saving = ref(false)
const dialog = ref(false)
const file = ref(null)
const tab = ref('overview')
const tableFilter = ref('')

const methodOptions = [{ label: 'Cash', value: 'cash' }, { label: 'Bank', value: 'bank' }, { label: 'Hawala', value: 'hawala' }, { label: 'Card', value: 'card' }, { label: 'Other', value: 'other' }]
const homeCategories = ['Groceries', 'Household Purchases', 'Home Maintenance', 'Utility Bills', 'Other']

const sections = [
  { name: 'overview', label: 'Overview', icon: 'dashboard' },
  { name: 'all', label: 'AllExpenses', icon: 'receipt_long' },
  { name: 'category', label: 'ByCategory', icon: 'category' },
  { name: 'budget', label: 'BudgetVsActual', icon: 'savings' },
  { name: 'yearly', label: 'Yearly', icon: 'calendar_month' },
]
const activeSection = computed(() => sections.find(s => s.name === tab.value) || sections[0])

const columns = [
  { name: 'expense_date', label: 'Date', field: 'expense_date', align: 'left', sortable: true, format: v => (v || '').slice(0, 10) },
  { name: 'category', label: 'Category', field: 'category', align: 'left', sortable: true },
  { name: 'payment_method', label: 'PaymentMethod', field: 'payment_method', align: 'left' },
  { name: 'amount_base', label: 'Amount', field: 'amount_base', align: 'right', sortable: true },
  { name: 'attach', label: 'Receipt', field: 'attach', align: 'center' },
  { name: 'actions', label: 'Actions', field: 'actions', align: 'right' },
]

function fmt (v) { return Number(v || 0).toLocaleString('en-US') }
function barH (v) { const max = Math.max(1, ...d.value.monthly.map(m => Number(m.total || 0))); return Math.max(3, (Number(v) / max) * 100) }
const budgetPct = computed(() => { const c = d.value.budget.current; return c.budget ? Math.round((c.actual / c.budget) * 100) : 0 })

const heroStats = computed(() => [
  { label: 'ThisMonth', value: fmt(d.value.summary.this_month) + ' ' + base.value, icon: 'calendar_today' },
  { label: 'ThisYear', value: fmt(d.value.summary.this_year) + ' ' + base.value, icon: 'calendar_month' },
  { label: 'MonthBudget', value: fmt(d.value.budget.current.budget) + ' ' + base.value, icon: 'savings' },
  { label: 'BudgetLeft', value: fmt(d.value.budget.current.variance) + ' ' + base.value, icon: 'account_balance_wallet' },
])

const blank = () => ({ id: null, type: 'home', expense_date: new Date().toISOString().slice(0, 10), category: 'Groceries', payment_method: 'cash', amount: null, currency: 'AFN', rate: 1, approval_status: 'approved', description: '' })
const form = reactive(blank())
const otherMethod = ref('')
const knownMethods = ['cash', 'bank', 'hawala', 'card', 'other']

async function loadDash () { try { const { data } = await api.get('/home-expenses/dashboard'); d.value = data } catch (_) {} }
async function loadRows () { loading.value = true; try { const { data } = await api.get('/expenses', { params: { type: 'home' } }); rows.value = Array.isArray(data) ? data : [] } finally { loading.value = false } }
async function reload () { await Promise.all([loadDash(), loadRows()]) }

function openCreate () { Object.assign(form, blank()); otherMethod.value = ''; file.value = null; dialog.value = true }
function openEdit (id) {
  const r = rows.value.find(x => x.id === id); if (!r) return
  const method = r.payment_method || 'cash'
  const isCustom = method && !knownMethods.includes(method)
  otherMethod.value = isCustom ? method : ''
  Object.assign(form, { id: r.id, type: 'home', expense_date: (r.expense_date || '').slice(0, 10), category: r.category, payment_method: isCustom ? 'other' : method, amount: Number(r.amount), currency: r.currency, rate: Number(r.rate || 1), approval_status: r.approval_status, description: r.description || '' })
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
async function viewAttach (r) { try { const res = await api.get('/expenses/' + r.id + '/attachment', { responseType: 'blob' }); window.open(URL.createObjectURL(new Blob([res.data], { type: r.attachment_mime })), '_blank') } catch (_) {} }

const budgetDialog = ref(false)
const savingBudget = ref(false)
const budgetForm = reactive({ period: new Date().toISOString().slice(0, 7), amount: null })
async function saveBudget () {
  savingBudget.value = true
  try { await api.post('/expense-budgets', { type: 'home', period: budgetForm.period, amount: budgetForm.amount }); budgetDialog.value = false; loadDash(); Notify.create({ type: 'positive', position: 'bottom', message: 'Saved' }) }
  catch (e) { Notify.create({ type: 'negative', message: 'Failed' }) } finally { savingBudget.value = false }
}

onMounted(() => { loadRates(); reload() })
</script>

<style scoped>
.hx-hero__bar { background: linear-gradient(135deg, #0D5C4A 0%, #0D9488 55%, #14B8A6 100%); border-radius: 14px; padding: 16px 18px; color: #fff; box-shadow: 0 10px 26px -12px rgba(13, 92, 74, 0.6); }
.hx-hero__head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
.hx-hero__title { display: flex; align-items: center; gap: 12px; }
.hx-hero__icon { width: 46px; height: 46px; border-radius: 12px; background: rgba(255, 255, 255, 0.14); display: flex; align-items: center; justify-content: center; border: 1px solid rgba(255, 255, 255, 0.25); }
.hx-hero__name { font-size: 20px; font-weight: 800; }
.hx-hero__meta { display: flex; gap: 6px; margin-top: 6px; }
.hx-hero__pill { display: inline-flex; align-items: center; gap: 3px; font-size: 11.5px; padding: 2px 8px; border-radius: 20px; background: rgba(255, 255, 255, 0.14); border: 1px solid rgba(255, 255, 255, 0.2); }
.hx-hero__progress { margin-top: 14px; }
.hx-hero__stats { margin-top: 10px; }
.kpi-tile { border: 1.5px solid #E2E8F0; border-radius: 12px; padding: 12px 14px; background: #fff; height: 100%; }
.kpi-tile__icon { color: #0D9488; opacity: 0.9; }
.kpi-tile__val { font-size: 16px; font-weight: 800; margin-top: 4px; color: #1E293B; }
.kpi-tile__lbl { font-size: 11px; color: #94A3B8; margin-top: 1px; }
.dash-nav { display: flex; gap: 4px; background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(10px); border: 1px solid #E2E8F0; border-radius: 999px; padding: 5px 8px; box-shadow: 0 10px 30px -14px rgba(13, 92, 74, 0.35); width: fit-content; max-width: 100%; margin: 0 auto; overflow-x: auto; position: sticky; top: 8px; z-index: 10; }
.dash-pill { display: flex; align-items: center; gap: 6px; border: none; background: transparent; cursor: pointer; padding: 5px 11px; border-radius: 999px; color: #64748B; font-size: 12px; font-weight: 700; transition: all 0.25s ease; white-space: nowrap; }
.dash-pill__orb { width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: #F1F5F9; }
.dash-pill--active { background: linear-gradient(135deg, #0D5C4A, #14B8A6); color: #fff; box-shadow: 0 6px 18px -6px rgba(13, 92, 74, 0.55); }
.dash-pill--active .dash-pill__orb { background: rgba(255, 255, 255, 0.18); color: #fff; }
.dash-body { border-radius: 14px; }
@media (max-width: 900px) { .dash-pill__label { display: none; } }
.bars { display: flex; align-items: flex-end; gap: 6px; height: 170px; padding: 8px; border: 1px solid #E2E8F0; border-radius: 10px; }
.bars__col { flex: 1; display: flex; flex-direction: column; align-items: center; height: 100%; justify-content: flex-end; }
.bars__bar { width: 70%; background: linear-gradient(180deg, #14B8A6, #0D5C4A); border-radius: 4px 4px 0 0; min-height: 3px; }
.bars__lbl { font-size: 9px; color: #94A3B8; margin-top: 3px; }
.bd__row { display: flex; align-items: center; gap: 10px; padding: 5px 0; }
.bd__name { width: 130px; font-size: 12.5px; font-weight: 600; color: #334155; }
.bd__track { flex: 1; height: 12px; background: #F1F5F9; border-radius: 6px; overflow: hidden; }
.bd__fill { height: 100%; background: linear-gradient(90deg, #14B8A6, #0D9488); border-radius: 6px; }
.bd__val { width: 130px; text-align: end; font-size: 12.5px; font-weight: 700; color: #0D5C4A; }
@media (prefers-color-scheme: dark) { .kpi-tile { background: #1E293B; border-color: #334155; } .kpi-tile__val { color: #F1F5F9; } }
</style>
