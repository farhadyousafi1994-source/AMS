<template>
  <q-page>
    <m-backgrounds>
      <div class="row my_radio_less q-pa-sm">
        <div class="col-12">
          <m-header icon="assessment" controlRoomButton="false" class="q-mt-xs">
            {{ $t('Reports') }}
          </m-header>
        </div>

        <!-- ── Report catalogue (grouped, Odoo-style) ── -->
        <div class="col-12 q-mt-sm">
          <div v-for="g in groups" :key="g.key" class="rep-group">
            <div class="rep-group__head">
              <q-icon :name="g.icon" size="16px" :style="{ color: g.color }" />
              <span>{{ $t(g.label) }}</span>
            </div>
            <div class="row q-col-gutter-sm">
              <div class="col-6 col-sm-4 col-md-3" v-for="r in g.items" :key="r.value">
                <div
                  class="report-tile"
                  :class="{ 'report-tile--active': selected === r.value }"
                  :style="selected === r.value ? { borderColor: g.color, background: tint(g.color) } : {}"
                  @click="selectReport(r.value)"
                >
                  <div class="report-tile__ico" :style="{ background: tint(g.color), color: g.color }">
                    <q-icon :name="r.icon" size="20px" />
                  </div>
                  <div class="report-tile__label">{{ $t(r.label) }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ── Shared filters ── -->
        <div class="col-12 q-mt-md">
          <q-card class="my_radio_less bg-white rep-filters">
            <q-card-section class="row q-col-gutter-sm items-end q-pa-sm">
              <div class="col-6 col-sm-3">
                <shamsi-date v-model="filters.date_from" color="primary" :label="$t('DateFrom')" clearable />
              </div>
              <div class="col-6 col-sm-3">
                <shamsi-date v-model="filters.date_to" color="primary" :label="$t('DateTo')" clearable />
              </div>
              <div class="col-12 col-sm-3">
                <q-select outlined dense color="primary" label-color="primary" v-model="filters.project_id"
                  :options="projectOptions" emit-value map-options clearable :label="$t('Project')">
                  <template v-slot:prepend><q-icon name="domain" color="primary" /></template>
                </q-select>
              </div>
              <div class="col-12 col-sm-3">
                <q-btn unelevated color="primary" icon="play_arrow" :label="$t('RunReport')" :loading="loading" @click="run" class="full-width" />
              </div>
            </q-card-section>
          </q-card>
        </div>

        <!-- ── Result ── -->
        <div class="col-12 q-mt-md" v-if="report">
          <q-card class="my_radio_less bg-white rep-result">
            <!-- Branded result header -->
            <div class="rep-result__bar">
              <div class="rep-result__title">
                <div class="rep-result__ico"><q-icon :name="activeIcon" size="22px" /></div>
                <div>
                  <div class="rep-result__name">{{ report.title }}</div>
                  <div class="rep-result__meta">{{ $t('GeneratedOn') }} {{ generatedAt }} · {{ report.rows.length }} {{ $t('Records') }}</div>
                </div>
              </div>
              <div class="rep-result__actions">
                <q-btn dense unelevated color="red-7" icon="picture_as_pdf" :label="$t('PDF')" size="sm" :loading="busy === 'pdf'" @click="doExport('pdf')" />
                <q-btn dense unelevated color="green-8" icon="grid_on" :label="$t('Excel')" size="sm" @click="doExport('excel')" />
                <q-btn dense unelevated color="blue-8" icon="description" :label="$t('Word')" size="sm" @click="doExport('word')" />
              </div>
            </div>

            <!-- KPI summary cards -->
            <div class="q-px-sm q-pt-md" v-if="report.summary && report.summary.length">
              <div class="row q-col-gutter-sm">
                <div class="col-6 col-sm-4 col-md-3" v-for="(s, i) in report.summary" :key="s.label">
                  <div class="kpi" :style="{ '--kpi': kpiColor(i) }">
                    <div class="kpi__ico"><q-icon :name="kpiIcon(s.label, i)" size="20px" /></div>
                    <div class="kpi__body">
                      <div class="kpi__val">{{ s.value }}</div>
                      <div class="kpi__lbl">{{ s.label }}</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- In-table search -->
            <div class="q-px-sm q-pt-md" v-if="report.rows.length">
              <q-input outlined dense v-model="search" :placeholder="$t('SearchInReport')" clearable class="rep-search">
                <template #prepend><q-icon name="search" color="primary" /></template>
              </q-input>
            </div>

            <!-- Data table -->
            <q-card-section class="q-pt-sm">
              <div class="rep-table-wrap">
                <table class="rep-table">
                  <thead>
                    <tr>
                      <th class="rep-table__idx">#</th>
                      <th v-for="c in report.columns" :key="c.name" :class="'text-' + (c.align || 'left')">{{ c.label }}</th>
                      <th v-if="hasLinks" class="rep-table__go"></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="filteredRows.length === 0">
                      <td :colspan="report.columns.length + (hasLinks ? 2 : 1)" class="text-center text-grey-5 q-py-lg">
                        <q-icon name="search_off" size="28px" class="q-mb-xs block" />
                        {{ $t('NoRecordFound') }}
                      </td>
                    </tr>
                    <tr v-for="(row, i) in filteredRows" :key="i"
                      :class="[rowClass(row), row._link ? 'rep-row--link' : '']"
                      @click="openRow(row)">
                      <td class="rep-table__idx">{{ i + 1 }}</td>
                      <td v-for="c in report.columns" :key="c.name" :class="'text-' + (c.align || 'left')">
                        <span :class="{ 'rep-num': c.align === 'right' }">{{ row[c.name] }}</span>
                      </td>
                      <td v-if="hasLinks" class="rep-table__go">
                        <q-icon v-if="row._link" name="open_in_new" size="14px" />
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div class="text-caption text-grey-6 q-mt-sm">
                {{ filteredRows.length }}<span v-if="search"> / {{ report.rows.length }}</span> {{ $t('Rows') }}
              </div>
            </q-card-section>
          </q-card>
        </div>

        <!-- Empty state -->
        <div class="col-12 q-mt-md" v-else-if="!loading">
          <div class="text-center text-grey-5 q-py-xl">
            <q-icon name="assessment" size="48px" class="q-mb-sm" />
            <div>{{ $t('PickReportHint') }}</div>
          </div>
        </div>
      </div>
    </m-backgrounds>
  </q-page>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { Notify } from 'quasar'
import { api } from '@/boot/axios'
import { fmtDate } from '@/utils/date'
import { exportPdf, exportExcel, exportWord } from '@/composables/useExport'

// Report catalogue, grouped like Odoo's reporting menu.
const groups = [
  {
    key: 'overview', label: 'Overview', icon: 'insights', color: '#2563eb',
    items: [
      { value: 'executive', label: 'ExecutiveOverview', icon: 'insights' },
      { value: 'project', label: 'ProjectReport', icon: 'domain' },
    ],
  },
  {
    key: 'finance', label: 'Finance', icon: 'account_balance', color: '#0d9488',
    items: [
      { value: 'pnl', label: 'ProfitLoss', icon: 'account_balance' },
      { value: 'treasury', label: 'GeneralBudget', icon: 'savings' },
      { value: 'accounts', label: 'PartyAccounts', icon: 'account_balance_wallet' },
      { value: 'captable', label: 'CapTableStatement', icon: 'diversity_3' },
    ],
  },
  {
    key: 'operations', label: 'Operations', icon: 'inventory_2', color: '#d97706',
    items: [
      { value: 'stock', label: 'StockAssets', icon: 'inventory_2' },
      { value: 'payroll', label: 'PayrollSheet', icon: 'payments' },
      { value: 'approval-log', label: 'ApprovalLog', icon: 'fact_check' },
    ],
  },
]

const allReports = groups.flatMap(g => g.items)
const kpiPalette = ['#2563eb', '#0d9488', '#d97706', '#7c3aed', '#dc2626', '#0891b2']

const selected = ref('executive')
const loading = ref(false)
const busy = ref(null)
const report = ref(null)
const projectOptions = ref([])
const search = ref('')
const generatedAt = ref('')
const filters = reactive({ date_from: '', date_to: '', project_id: null, currency: null })

const activeIcon = computed(() => allReports.find(r => r.value === selected.value)?.icon || 'assessment')
const hasLinks = computed(() => !!report.value && report.value.rows.some(r => r._link))

// Click-through: a report row opens the record (or module page) it came from.
const router = useRouter()
function openRow (row) { if (row._link) router.push(row._link) }

const filteredRows = computed(() => {
  if (!report.value) return []
  const q = search.value.trim().toLowerCase()
  if (!q) return report.value.rows
  return report.value.rows.filter(row =>
    report.value.columns.some(c => String(row[c.name] ?? '').toLowerCase().includes(q))
  )
})

function tint (hex) { return `color-mix(in srgb, ${hex} 10%, #fff)` }
function kpiColor (i) { return kpiPalette[i % kpiPalette.length] }

function kpiIcon (label, i) {
  const l = String(label || '').toLowerCase()
  if (l.includes('profit') || l.includes('net')) return 'trending_up'
  if (l.includes('income') || l.includes('receiv')) return 'south_west'
  if (l.includes('expense') || l.includes('paid') || l.includes('owe')) return 'north_east'
  if (l.includes('capital') || l.includes('available') || l.includes('budget')) return 'savings'
  if (l.includes('head') || l.includes('part') || l.includes('employ')) return 'groups'
  if (l.includes('progress')) return 'donut_large'
  return ['analytics', 'payments', 'inventory_2', 'insights'][i % 4]
}

// Semantic row highlighting for statement-style reports (P&L etc.)
function rowClass (row) {
  const k = String(row.kind || '').toLowerCase()
  if (k === 'net' || k === 'total') return 'rep-row--total'
  if (k === 'income') return 'rep-row--income'
  if (k === 'expense') return 'rep-row--expense'
  return ''
}

function selectReport (v) { selected.value = v; search.value = ''; run() }

async function loadProjects () {
  try { const { data } = await api.get('/projects'); projectOptions.value = (data || []).map(p => ({ label: p.name, value: p.id })) } catch (_) {}
}

async function run () {
  loading.value = true
  try {
    const params = {}
    if (filters.date_from) params.date_from = filters.date_from
    if (filters.date_to) params.date_to = filters.date_to
    if (filters.project_id) params.project_id = filters.project_id
    const { data } = await api.get('/reports/' + selected.value, { params })
    report.value = data
    generatedAt.value = fmtDate(new Date().toISOString())
  } catch (e) {
    Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Report failed' })
  } finally { loading.value = false }
}

async function doExport (kind) {
  if (!report.value) return
  const cols = report.value.columns.map(c => ({ name: c.name, label: c.label, field: c.name }))
  const fname = (report.value.title || 'report').replace(/[^\w]+/g, '-').toLowerCase()
  busy.value = kind
  try {
    if (kind === 'pdf') await exportPdf(report.value.rows, cols, fname, report.value.title)
    else if (kind === 'excel') exportExcel(report.value.rows, cols, fname)
    else exportWord(report.value.rows, cols, fname, report.value.title)
  } finally { busy.value = null }
}

onMounted(() => { loadProjects(); run() })
</script>

<style scoped>
/* ── Catalogue ── */
.rep-group { margin-bottom: 14px; }
.rep-group__head {
  display: flex; align-items: center; gap: 6px;
  font-size: 12px; font-weight: 800; letter-spacing: .4px; text-transform: uppercase;
  color: #64748b; margin: 4px 2px 8px;
}
.report-tile {
  display: flex; align-items: center; gap: 10px;
  border: 1.5px solid #E2E8F0; border-radius: 12px;
  padding: 10px 12px; cursor: pointer; background: #fff;
  transition: all .15s ease; height: 100%;
}
.report-tile:hover { border-color: #cbd5e1; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(15,23,42,.06); }
.report-tile--active { box-shadow: 0 4px 14px rgba(15,23,42,.08); }
.report-tile__ico {
  width: 38px; height: 38px; border-radius: 10px; flex: 0 0 auto;
  display: flex; align-items: center; justify-content: center;
}
.report-tile__label { font-size: 12.5px; font-weight: 700; color: #334155; line-height: 1.2; }

.rep-filters { border: 1px solid #eef2f7; }

/* ── Result header ── */
.rep-result { overflow: hidden; border: 1px solid #eef2f7; }
.rep-result__bar {
  display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;
  padding: 14px 16px;
  background: linear-gradient(135deg, #123A66 0%, #1c5a9e 100%); color: #fff;
}
.rep-result__title { display: flex; align-items: center; gap: 12px; }
.rep-result__ico {
  width: 42px; height: 42px; border-radius: 11px;
  background: rgba(255,255,255,.16); display: flex; align-items: center; justify-content: center;
}
.rep-result__name { font-size: 16px; font-weight: 800; }
.rep-result__meta { font-size: 11.5px; opacity: .82; margin-top: 2px; }
.rep-result__actions { display: flex; gap: 6px; flex-wrap: wrap; }

/* ── KPI cards ── */
.kpi {
  display: flex; align-items: center; gap: 12px;
  border: 1px solid #eef2f7; border-inline-start: 4px solid var(--kpi);
  border-radius: 12px; padding: 12px 14px; background: #fff; height: 100%;
}
.kpi__ico {
  width: 40px; height: 40px; border-radius: 10px; flex: 0 0 auto;
  display: flex; align-items: center; justify-content: center;
  background: color-mix(in srgb, var(--kpi) 12%, #fff); color: var(--kpi);
}
.kpi__val { font-size: 18px; font-weight: 800; letter-spacing: -0.3px; color: #0f172a; line-height: 1.1; }
.kpi__lbl { font-size: 11px; color: #94a3b8; margin-top: 3px; }

.rep-search :deep(.q-field__control) { border-radius: 10px; }

/* ── Table ── */
.rep-table-wrap { overflow-x: auto; border: 1px solid #eef2f7; border-radius: 12px; max-height: 62vh; overflow-y: auto; }
.rep-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.rep-table thead th {
  position: sticky; top: 0; z-index: 2;
  background: #123A66; color: #fff; font-weight: 700; white-space: nowrap;
  padding: 10px 12px; text-align: start;
}
.rep-table thead th.text-right { text-align: end; }
.rep-table thead th.text-center { text-align: center; }
.rep-table tbody td { padding: 9px 12px; border-bottom: 1px solid #eef2f7; color: #334155; }
.rep-table tbody tr:nth-child(even) { background: #f8fafc; }
.rep-table tbody tr:hover { background: #eef5ff; }
.rep-table__idx { color: #94a3b8; font-size: 11px; width: 38px; text-align: center; }
.rep-num { font-variant-numeric: tabular-nums; font-weight: 600; }

.rep-table__go { width: 30px; text-align: center; color: #94a3b8; }
.rep-row--link { cursor: pointer; }
.rep-row--link:hover .rep-table__go { color: #2563eb; }

.rep-row--income td { color: #0f766e; }
.rep-row--expense td { color: #b91c1c; }
.rep-row--total td { background: #fff7ed !important; font-weight: 800; color: #0f172a; border-top: 2px solid #fdba74; }

.block { display: block; margin-left: auto; margin-right: auto; }

@media (max-width: 600px) {
  .rep-result__actions .q-btn { padding: 2px 8px; }
}
</style>
