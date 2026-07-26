<template>
  <q-page>
    <m-backgrounds>
      <div class="row my_radio_less q-pa-sm pay-wrap">

        <!-- ── Hero ── -->
        <div class="col-12">
          <div class="pay-hero">
            <div class="pay-hero__glow"></div>
            <div class="pay-hero__left">
              <div class="pay-hero__eyebrow"><q-icon name="payments" size="16px" /> {{ $t('Payroll') }}</div>
              <div class="pay-hero__title">{{ monthLabel(period) }}</div>
              <div class="pay-hero__sub">{{ $t('PayrollHeroSub') }}</div>
            </div>
            <div class="pay-hero__right">
              <div class="pay-month">
                <q-icon name="event" size="18px" class="q-mr-xs" />
                <span>{{ monthLabel(period) }}</span>
                <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                  <q-date v-model="period" mask="YYYY-MM" default-view="Months" minimal color="indigo-8" />
                </q-popup-proxy>
              </div>
              <q-btn class="pay-run-btn" no-caps unelevated :loading="generating" v-if="$can('payroll-create')" @click="generate">
                <q-icon name="bolt" size="20px" class="q-mr-xs" />{{ $t('RunPayroll') }}
              </q-btn>
            </div>
          </div>
        </div>

        <!-- ── KPI row (latest run) ── -->
        <div class="col-12 q-mt-md" v-if="latest">
          <div class="row q-col-gutter-md">
            <div class="col-6 col-md-3"><div class="kpi kpi--net"><div class="kpi__icon"><q-icon name="account_balance_wallet" size="22px" /></div><div><div class="kpi__val">{{ fmt(latest.net_total) }} <small>{{ latest.currency }}</small></div><div class="kpi__lbl">{{ $t('NetPayroll') }} · {{ monthLabel(latest.period) }}</div></div></div></div>
            <div class="col-6 col-md-3"><div class="kpi kpi--emp"><div class="kpi__icon"><q-icon name="groups" size="22px" /></div><div><div class="kpi__val">{{ latest.items_count }}</div><div class="kpi__lbl">{{ $t('Employees') }}</div></div></div></div>
            <div class="col-6 col-md-3"><div class="kpi kpi--runs"><div class="kpi__icon"><q-icon name="event_repeat" size="22px" /></div><div><div class="kpi__val">{{ runs.length }}</div><div class="kpi__lbl">{{ $t('PayrollRuns') }}</div></div></div></div>
            <div class="col-6 col-md-3"><div class="kpi kpi--paid"><div class="kpi__icon"><q-icon name="verified" size="22px" /></div><div><div class="kpi__val">{{ paidCount }}</div><div class="kpi__lbl">{{ $t('Paid') }}</div></div></div></div>
          </div>
        </div>

        <!-- ── Runs as cards ── -->
        <div class="col-12 q-mt-md">
          <div class="text-subtitle1 text-weight-bold q-mb-sm pay-section-t"><q-icon name="history" size="18px" class="q-mr-xs" />{{ $t('PayrollRuns') }}</div>
          <div v-if="loading" class="text-center q-py-lg"><q-spinner color="indigo-7" size="2.4em" /></div>
          <div v-else-if="!runs.length" class="pay-empty">
            <q-icon name="payments" size="46px" class="q-mb-sm" />
            <div class="text-subtitle2">{{ $t('NoPayrollYet') }}</div>
            <div class="text-caption text-grey-6">{{ $t('NoPayrollHint') }}</div>
          </div>
          <div v-else class="row q-col-gutter-md">
            <div v-for="r in runs" :key="r.id" class="col-12 col-sm-6 col-lg-4">
              <div class="run-card" :class="{ 'run-card--paid': r.status === 'paid' }" @click="open(r.id)">
                <div class="run-card__top">
                  <div>
                    <div class="run-card__period">{{ monthLabel(r.period) }}</div>
                    <div class="run-card__meta"><q-icon name="groups" size="13px" /> {{ r.items_count }} {{ $t('Employees') }}</div>
                  </div>
                  <q-chip dense size="sm" :color="r.status === 'paid' ? 'positive' : 'amber-8'" text-color="white" class="run-card__chip">
                    <q-icon :name="r.status === 'paid' ? 'check_circle' : 'schedule'" size="13px" class="q-mr-xs" />{{ $t(r.status === 'paid' ? 'Paid' : 'Draft') }}
                  </q-chip>
                </div>
                <div class="run-card__amount">{{ fmt(r.net_total) }} <span>{{ r.currency }}</span></div>
                <div class="run-card__lbl">{{ $t('NetPayroll') }}</div>
                <div class="run-card__actions" @click.stop>
                  <q-btn flat dense no-caps color="indigo-8" icon="visibility" :label="$t('View')" @click="open(r.id)" />
                  <q-space />
                  <q-btn v-if="r.status !== 'paid' && $can('payroll-edit')" flat dense round color="positive" icon="task_alt" @click="pay(r)"><q-tooltip>{{ $t('MarkPaid') }}</q-tooltip></q-btn>
                  <q-btn v-if="r.status !== 'paid' && $can('payroll-delete')" flat dense round color="negative" icon="delete" @click="removeRun(r)" />
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </m-backgrounds>

    <!-- ── Run detail (VIP payslip list) ── -->
    <m-modal :showCM="dialog" @update:showCM="dialog = $event" card_style="width: 900px; max-width: 96vw">
      <q-card class="bg-grey-1" v-if="active">
        <div class="det-head">
          <div>
            <div class="det-head__title">{{ monthLabel(active.period) }}</div>
            <div class="det-head__sub">{{ active.items?.length || 0 }} {{ $t('Employees') }} · <span :class="active.status === 'paid' ? 'text-positive' : 'text-amber-8'">{{ $t(active.status === 'paid' ? 'Paid' : 'Draft') }}</span></div>
          </div>
          <q-space />
          <q-btn v-if="active.status !== 'paid' && $can('payroll-edit')" unelevated color="positive" no-caps icon="task_alt" :label="$t('MarkPaid')" @click="pay(active, true)" />
          <q-btn flat round dense icon="close" color="grey-7" class="q-ml-sm" @click="dialog = false" />
        </div>

        <!-- totals bar -->
        <div class="det-totals">
          <div class="det-tot"><span>{{ $t('Gross') }}</span><b>{{ fmt(sumField('gross')) }} {{ active.currency }}</b></div>
          <div class="det-tot det-tot--ded"><span>{{ $t('Deductions') }}</span><b>− {{ fmt(sumDeductions()) }}</b></div>
          <div class="det-tot det-tot--net"><span>{{ $t('NetPayroll') }}</span><b>{{ fmt(sumField('net')) }} {{ active.currency }}</b></div>
        </div>

        <div class="q-px-md q-pt-sm">
          <q-input outlined dense color="indigo-8" v-model="empSearch" :placeholder="$t('SearchEmployee')" clearable class="det-search">
            <template #prepend><q-icon name="search" /></template>
          </q-input>
        </div>

        <q-card-section class="q-pt-sm" style="max-height:60vh;overflow:auto">
          <div v-for="it in filteredItems" :key="it.id" class="slip">
            <div class="slip__main">
              <q-avatar size="42px" class="slip__avatar" :style="avatarStyle(it.employee?.full_name)">{{ initials(it.employee?.full_name) }}</q-avatar>
              <div class="slip__who">
                <div class="slip__name">{{ it.employee?.full_name }}</div>
                <div class="slip__meta">{{ it.employee?.code }}<span v-if="it.employee?.designation"> · {{ it.employee.designation.title }}</span></div>
                <div class="slip__att">
                  <span class="att att--p"><q-icon name="check" size="11px" />{{ it.present_days }}</span>
                  <span class="att att--a"><q-icon name="close" size="11px" />{{ it.absent_days }}</span>
                  <span class="att att--l"><q-icon name="beach_access" size="11px" />{{ it.leave_days }}</span>
                </div>
              </div>
              <div class="slip__net">
                <div class="slip__net-val">{{ fmt(it.net) }} <small>{{ active.currency }}</small></div>
                <div class="slip__net-lbl">{{ $t('NetSalary') }}</div>
                <div class="slip__gd">{{ fmt(it.gross) }} − <span class="text-negative">{{ fmt(totalDeductions(it)) }}</span></div>
              </div>
              <div class="slip__btns">
                <q-btn v-if="active.status !== 'paid'" round dense flat color="indigo-8" icon="edit" @click="toggleEdit(it)"><q-tooltip>{{ $t('EditPayslip') }}</q-tooltip></q-btn>
                <q-btn round dense flat color="teal-8" icon="print" @click="printSlip(it)"><q-tooltip>{{ $t('SalarySlip') }}</q-tooltip></q-btn>
              </div>
            </div>

            <!-- inline editor -->
            <q-slide-transition>
              <div v-show="editId === it.id" class="slip__edit">
                <div class="slip__edit-col">
                  <div class="slip__edit-h text-positive">{{ $t('Earnings') }}</div>
                  <div class="row q-col-gutter-sm">
                    <div class="col-6 col-sm-4" v-for="f in earningFields" :key="f.key">
                      <q-input outlined dense color="positive" type="number" step="any" v-model.number="draft[f.key]" :label="$t(f.label)" hide-bottom-space @update:model-value="noop" />
                    </div>
                  </div>
                </div>
                <div class="slip__edit-col">
                  <div class="slip__edit-h text-negative">{{ $t('Deductions') }}</div>
                  <div class="row q-col-gutter-sm">
                    <div class="col-6 col-sm-4" v-for="f in deductionFields" :key="f.key">
                      <q-input outlined dense color="negative" type="number" step="any" v-model.number="draft[f.key]" :label="$t(f.label)" hide-bottom-space @update:model-value="noop" />
                    </div>
                  </div>
                </div>
                <div class="slip__edit-foot">
                  <div class="slip__edit-live">
                    <span>{{ $t('Gross') }} <b>{{ fmt(liveGross) }}</b></span>
                    <span class="text-negative">− {{ fmt(liveDeductions) }}</span>
                    <span class="slip__edit-net">= {{ fmt(liveGross - liveDeductions) }} {{ active.currency }}</span>
                  </div>
                  <q-space />
                  <q-btn flat dense no-caps color="grey-7" :label="$t('Cancel')" @click="editId = null" />
                  <q-btn unelevated dense no-caps color="indigo-8" icon="save" :label="$t('Save')" :loading="savingSlip" @click="saveSlip(it)" />
                </div>
              </div>
            </q-slide-transition>
          </div>
        </q-card-section>
      </q-card>
    </m-modal>
  </q-page>
</template>

<script setup>
import { ref, computed, getCurrentInstance, onMounted } from 'vue'
import { Notify } from 'quasar'
import { api } from '@/boot/axios'

const { proxy } = getCurrentInstance()
const runs = ref([])
const period = ref(new Date().toISOString().slice(0, 7))
const generating = ref(false)
const loading = ref(false)
const dialog = ref(false)
const active = ref(null)
const empSearch = ref('')

const earningFields = [
  { key: 'basic', label: 'BasicSalary' }, { key: 'allowances', label: 'Allowances' },
  { key: 'housing', label: 'HousingAllowance' }, { key: 'transport', label: 'TransportAllowance' },
  { key: 'overtime', label: 'Overtime' }, { key: 'bonus', label: 'Bonus' },
]
const deductionFields = [
  { key: 'deductions', label: 'AbsenceDeduction' }, { key: 'tax', label: 'Tax' },
  { key: 'loan', label: 'Loan' }, { key: 'advance', label: 'Advance' },
]

const editId = ref(null)
const draft = ref({})
const savingSlip = ref(false)
const liveGross = computed(() => earningFields.reduce((s, f) => s + Number(draft.value?.[f.key] || 0), 0))
const liveDeductions = computed(() => deductionFields.reduce((s, f) => s + Number(draft.value?.[f.key] || 0), 0))
function noop () {}

const latest = computed(() => runs.value[0] || null)
const paidCount = computed(() => runs.value.filter(r => r.status === 'paid').length)
const filteredItems = computed(() => {
  const q = (empSearch.value || '').toLowerCase()
  const items = active.value?.items || []
  if (!q) return items
  return items.filter(it => (it.employee?.full_name || '').toLowerCase().includes(q) || (it.employee?.code || '').toLowerCase().includes(q))
})

function fmt (v) { return Number(v || 0).toLocaleString('en-US', { maximumFractionDigits: 2 }) }
function totalDeductions (it) { return deductionFields.reduce((s, f) => s + Number(it[f.key] || 0), 0) }
function sumField (key) { return (active.value?.items || []).reduce((s, it) => s + Number(it[key] || 0), 0) }
function sumDeductions () { return (active.value?.items || []).reduce((s, it) => s + totalDeductions(it), 0) }

function monthLabel (p) {
  if (!p) return ''
  const [y, m] = p.split('-')
  const d = new Date(Number(y), Number(m) - 1, 1)
  return d.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })
}
function initials (n) { return String(n || '؟').trim().split(/\s+/).slice(0, 2).map(w => w[0]).join('').toUpperCase() }
function avatarStyle (n) {
  const colors = ['#6366F1', '#0EA5A4', '#F59E0B', '#EC4899', '#3B82F6', '#10B981', '#8B5CF6']
  let h = 0; for (const c of String(n || '')) h = (h * 31 + c.charCodeAt(0)) % colors.length
  return `background:${colors[h]}22;color:${colors[h]};font-weight:800`
}

async function load () {
  loading.value = true
  try { const { data } = await api.get('/payroll-runs'); runs.value = data } catch (_) {} finally { loading.value = false }
}
async function generate () {
  generating.value = true
  try {
    const { data } = await api.post('/payroll-runs', { period: period.value })
    Notify.create({ type: 'positive', position: 'bottom', icon: 'bolt', message: proxy.$t('PayrollGenerated') })
    await load(); active.value = data; dialog.value = true
  } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Failed' }) } finally { generating.value = false }
}
async function open (id) { try { const { data } = await api.get('/payroll-runs/' + id); active.value = data; empSearch.value = ''; editId.value = null; dialog.value = true } catch (_) {} }

function toggleEdit (it) {
  if (editId.value === it.id) { editId.value = null; return }
  draft.value = { ...it }
  editId.value = it.id
}
async function saveSlip (it) {
  savingSlip.value = true
  try {
    const payload = {}
    for (const f of [...earningFields, ...deductionFields]) payload[f.key] = Number(draft.value[f.key] || 0)
    const { data } = await api.put('/payroll-items/' + it.id, payload)
    const idx = active.value.items.findIndex(x => x.id === it.id)
    if (idx >= 0) active.value.items[idx] = { ...active.value.items[idx], ...data }
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: proxy.$t('Saved') })
    editId.value = null; load()
  } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Failed' }) } finally { savingSlip.value = false }
}
async function pay (r, closeAfter = false) {
  try {
    await api.put('/payroll-runs/' + r.id + '/pay')
    Notify.create({ type: 'positive', position: 'bottom', icon: 'verified', message: proxy.$t('PayrollPaid') })
    if (active.value?.id === r.id) active.value.status = 'paid'
    if (closeAfter) dialog.value = false
    load()
  } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Failed' }) }
}
function removeRun (r) { proxy.$delete('payroll-runs/' + r.id, load) }

function printSlip (it) {
  const w = window.open('', '_blank')
  if (!w) return
  const cur = active.value.currency
  const row = (l, v, neg) => `<tr><td>${l}</td><td class="num${neg ? ' neg' : ''}">${neg ? '− ' : ''}${fmt(v)} ${cur}</td></tr>`
  w.document.write(`<!doctype html><html dir="auto"><head><meta charset="utf-8"><title>Salary Slip</title><style>
    *{box-sizing:border-box} body{font-family:'Segoe UI',Tahoma,Arial,sans-serif;margin:0;color:#1E293B;background:#fff}
    .wrap{max-width:620px;margin:24px auto;padding:0 26px 26px}
    .bar{height:8px;background:linear-gradient(90deg,#312E81,#4338CA,#0EA5A4);margin:0 -26px 20px}
    .head{display:flex;justify-content:space-between;align-items:flex-start}
    .co{font-size:20px;font-weight:800;color:#312E81}.co-sub{font-size:12px;color:#64748B}
    .ttl{font-size:24px;font-weight:800;letter-spacing:1px;color:#0F172A}.per{font-size:12px;color:#64748B;text-align:right}
    .emp{margin:18px 0;padding:12px 16px;background:#EEF2FF;border-radius:12px}
    .emp b{font-size:16px}.emp .m{font-size:12px;color:#475569}
    .att{margin-top:6px;font-size:12px;color:#334155}
    table{border-collapse:collapse;width:100%;font-size:13px;margin-top:6px}
    td{padding:8px 12px;border-bottom:1px solid #EDF2F7}.num{text-align:end;font-weight:600}.neg{color:#DC2626}
    .sec td{background:#F8FAFC;font-weight:800;font-size:10.5px;text-transform:uppercase;letter-spacing:.6px;color:#475569;border:none}
    .net td{background:#312E81;color:#fff;font-size:16px;font-weight:800;border:none}
    .sign{display:flex;justify-content:space-between;margin-top:44px;gap:40px}
    .sign div{flex:1;text-align:center;font-size:11px;color:#64748B;border-top:1.5px solid #CBD5E1;padding-top:6px}
    .foot{margin-top:22px;text-align:center;font-size:10.5px;color:#94A3B8;border-top:1px solid #EDF2F7;padding-top:10px}
  </style></head><body><div class="wrap">
    <div class="bar"></div>
    <div class="head">
      <div><div class="co">Aria Herat Mohandes Zada</div><div class="co-sub">Construction &amp; Road Building</div></div>
      <div><div class="ttl">PAYSLIP</div><div class="per">${monthLabel(active.value.period)}</div></div>
    </div>
    <div class="emp"><b>${it.employee?.full_name || ''}</b> <span class="m">${it.employee?.code || ''}${it.employee?.designation ? ' · ' + it.employee.designation.title : ''}</span>
      <div class="att">Attendance — Present <b>${it.present_days}</b> · Absent <b>${it.absent_days}</b> · Leave <b>${it.leave_days}</b></div>
    </div>
    <table>
      <tr class="sec"><td>Earnings</td><td class="num">Amount</td></tr>
      ${row('Basic Salary', it.basic)}${row('Allowances', it.allowances)}${row('Housing', it.housing)}${row('Transport', it.transport)}${row('Overtime', it.overtime)}${row('Bonus', it.bonus)}
      <tr><td><b>Gross</b></td><td class="num"><b>${fmt(it.gross)} ${cur}</b></td></tr>
      <tr class="sec"><td>Deductions</td><td class="num">Amount</td></tr>
      ${row('Absence (' + it.absent_days + ' days)', it.deductions, true)}${row('Tax', it.tax, true)}${row('Loan', it.loan, true)}${row('Advance', it.advance, true)}
      <tr class="net"><td>Net Salary</td><td class="num">${fmt(it.net)} ${cur}</td></tr>
    </table>
    <div class="sign"><div>Employee Signature</div><div>Authorized Signature</div></div>
    <div class="foot">Aria Herat Mohandes Zada · Payslip ${active.value.period} · ${it.employee?.full_name || ''}</div>
  </div><script>window.onload=function(){setTimeout(function(){window.print()},120)}<\/script></body></html>`)
  w.document.close()
}

onMounted(load)
</script>

<style scoped>
.pay-wrap { max-width: 1180px; margin: 0 auto; }

/* Hero */
.pay-hero { position: relative; overflow: hidden; border-radius: 20px; padding: 26px 30px;
  background: linear-gradient(120deg, #1E1B4B 0%, #312E81 45%, #3730A3 100%); color: #fff;
  display: flex; align-items: center; justify-content: space-between; gap: 20px; flex-wrap: wrap;
  box-shadow: 0 20px 44px -22px rgba(49,46,129,.7); }
.pay-hero__glow { position: absolute; right: -60px; top: -80px; width: 260px; height: 260px; border-radius: 50%;
  background: radial-gradient(circle, rgba(14,165,164,.55), transparent 68%); }
.pay-hero__eyebrow { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; opacity: .8; }
.pay-hero__title { font-size: 30px; font-weight: 800; letter-spacing: -.4px; margin-top: 2px; }
.pay-hero__sub { font-size: 13px; opacity: .82; margin-top: 3px; max-width: 420px; }
.pay-hero__right { display: flex; align-items: center; gap: 12px; z-index: 1; flex-wrap: wrap; }
.pay-month { display: inline-flex; align-items: center; cursor: pointer; background: rgba(255,255,255,.14);
  border: 1px solid rgba(255,255,255,.22); border-radius: 12px; padding: 10px 16px; font-weight: 700; font-size: 14px; backdrop-filter: blur(4px); transition: background .2s; }
.pay-month:hover { background: rgba(255,255,255,.24); }
.pay-run-btn { background: linear-gradient(135deg, #0EA5A4, #14B8A6); color: #fff; font-weight: 800; font-size: 14px;
  border-radius: 12px; padding: 10px 22px; box-shadow: 0 10px 24px -10px rgba(14,165,164,.8); }

/* KPI */
.kpi { display: flex; align-items: center; gap: 14px; background: #fff; border: 1px solid #EAEFF6; border-radius: 16px; padding: 16px 18px; box-shadow: 0 10px 26px -20px rgba(30,27,75,.5); }
.kpi__icon { width: 46px; height: 46px; border-radius: 14px; display: flex; align-items: center; justify-content: center; color: #fff; flex: 0 0 auto; }
.kpi--net .kpi__icon { background: linear-gradient(135deg,#312E81,#4338CA); }
.kpi--emp .kpi__icon { background: linear-gradient(135deg,#0EA5A4,#14B8A6); }
.kpi--runs .kpi__icon { background: linear-gradient(135deg,#2563EB,#3B82F6); }
.kpi--paid .kpi__icon { background: linear-gradient(135deg,#16A34A,#22C55E); }
.kpi__val { font-size: 21px; font-weight: 800; color: #0F172A; line-height: 1.1; }
.kpi__val small { font-size: 12px; color: #94A3B8; font-weight: 700; }
.kpi__lbl { font-size: 11.5px; color: #64748B; font-weight: 600; margin-top: 1px; }

.pay-section-t { color: #312E81; }

/* Run cards */
.run-card { background: #fff; border: 1px solid #EAEFF6; border-radius: 18px; padding: 18px 20px; cursor: pointer;
  transition: transform .18s, box-shadow .18s; position: relative; overflow: hidden; }
.run-card:hover { transform: translateY(-3px); box-shadow: 0 18px 36px -22px rgba(49,46,129,.55); }
.run-card::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 5px; background: linear-gradient(#4338CA,#0EA5A4); }
.run-card--paid::before { background: linear-gradient(#16A34A,#22C55E); }
.run-card__top { display: flex; justify-content: space-between; align-items: flex-start; }
.run-card__period { font-size: 16px; font-weight: 800; color: #1E293B; }
.run-card__meta { font-size: 12px; color: #64748B; margin-top: 2px; }
.run-card__chip { font-weight: 700; }
.run-card__amount { font-size: 26px; font-weight: 800; color: #312E81; margin-top: 12px; }
.run-card__amount span { font-size: 13px; color: #94A3B8; font-weight: 700; }
.run-card__lbl { font-size: 11px; color: #94A3B8; text-transform: uppercase; letter-spacing: .6px; font-weight: 700; }
.run-card__actions { display: flex; align-items: center; margin-top: 12px; border-top: 1px solid #F1F5F9; padding-top: 8px; }

.pay-empty { text-align: center; padding: 46px 0; color: #94A3B8; background: #fff; border: 1px dashed #CBD5E1; border-radius: 18px; }

/* Detail */
.det-head { display: flex; align-items: center; padding: 16px 20px; background: linear-gradient(120deg,#1E1B4B,#312E81); color: #fff; }
.det-head__title { font-size: 20px; font-weight: 800; }
.det-head__sub { font-size: 12.5px; opacity: .85; }
.det-head__sub .text-positive { color: #86EFAC !important; } .det-head__sub .text-amber-8 { color: #FCD34D !important; }
.det-totals { display: flex; gap: 1px; background: #E2E8F0; }
.det-tot { flex: 1; background: #fff; padding: 12px 16px; text-align: center; }
.det-tot span { display: block; font-size: 11px; color: #64748B; text-transform: uppercase; letter-spacing: .5px; font-weight: 700; }
.det-tot b { font-size: 17px; color: #0F172A; }
.det-tot--ded b { color: #DC2626; } .det-tot--net { background: #EEF2FF; } .det-tot--net b { color: #312E81; }
.det-search :deep(.q-field__control) { border-radius: 12px; }

/* Slip cards */
.slip { background: #fff; border: 1px solid #EEF2F7; border-radius: 14px; margin-bottom: 10px; overflow: hidden; }
.slip__main { display: flex; align-items: center; gap: 14px; padding: 12px 16px; }
.slip__avatar { font-size: 14px; }
.slip__who { flex: 1; min-width: 0; }
.slip__name { font-weight: 800; color: #1E293B; }
.slip__meta { font-size: 11.5px; color: #64748B; }
.slip__att { display: flex; gap: 8px; margin-top: 4px; }
.att { display: inline-flex; align-items: center; gap: 2px; font-size: 11px; font-weight: 700; padding: 1px 7px; border-radius: 999px; }
.att--p { background: #DCFCE7; color: #15803D; } .att--a { background: #FEE2E2; color: #B91C1C; } .att--l { background: #FEF3C7; color: #B45309; }
.slip__net { text-align: right; }
.slip__net-val { font-size: 19px; font-weight: 800; color: #312E81; }
.slip__net-val small { font-size: 11px; color: #94A3B8; }
.slip__net-lbl { font-size: 10px; color: #94A3B8; text-transform: uppercase; letter-spacing: .5px; font-weight: 700; }
.slip__gd { font-size: 11px; color: #64748B; }
.slip__btns { display: flex; }
.slip__edit { padding: 6px 16px 14px; background: #F8FAFC; border-top: 1px dashed #E2E8F0; }
.slip__edit-col { margin-top: 10px; }
.slip__edit-h { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .6px; margin-bottom: 6px; }
.slip__edit-foot { display: flex; align-items: center; gap: 8px; margin-top: 12px; }
.slip__edit-live { font-size: 12.5px; color: #475569; display: flex; gap: 10px; align-items: center; }
.slip__edit-live b { color: #0F172A; }
.slip__edit-net { font-weight: 800; color: #312E81; }

body.body--rtl .slip__net, body.body--rtl .pay-hero__right { text-align: left; }
</style>
