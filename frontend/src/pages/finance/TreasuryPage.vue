<template>
  <q-page>
    <m-backgrounds>
      <div class="row my_radio_less q-pa-sm">
        <div class="col-12">
          <m-header icon="savings" controlRoomButton="false" class="q-mt-xs">
            {{ $t('GeneralBudget') }}
          </m-header>
        </div>

        <!-- Summary cards (currency-explicit) — order & visibility from Control Room -->
        <div class="col-12 q-mt-md" v-if="visibleCards.length">
          <div class="row q-col-gutter-md">
            <div v-for="c in visibleCards" :key="c.key" class="col-6 col-md-3">
              <stat-card :icon="c.icon" :label="$t(c.label)" :value="c.card.value" :suffix="c.card.suffix"
                :color="c.color" :tint="c.tint" :sub="c.sub" :sub-icon="c.subIcon" />
            </div>
          </div>
        </div>

        <!-- Ledger -->
        <div class="col-12 q-mt-md">
          <q-card flat bordered class="my_radio_less bg-white">
            <q-card-section class="row items-center justify-between q-py-sm">
              <div class="text-subtitle2 text-weight-bold text-primary">
                <q-icon name="receipt_long" size="18px" class="q-mr-xs" />{{ $t('Ledger') }}
              </div>
              <progress-btn color="teal" icon="add" v-if="$can('treasury-create') && uiVisible('page.treasury.action.add')" @click="openTx">
                {{ $t('AddTransaction') }}
              </progress-btn>
            </q-card-section>
            <q-separator />
            <q-card-section>
              <q-markup-table flat bordered dense class="my_radio_less" style="max-height:56vh">
                <thead class="bg-theme-soft">
                  <tr>
                    <th v-if="colOn('date')" class="text-left">{{ $t('Date') }}</th>
                    <th v-if="colOn('kind')" class="text-left">{{ $t('Kind') }}</th>
                    <th v-if="colOn('project')" class="text-left">{{ $t('Project') }}</th>
                    <th v-if="colOn('money_in')" class="text-right">{{ $t('MoneyIn') }}</th>
                    <th v-if="colOn('money_out')" class="text-right">{{ $t('MoneyOut') }}</th>
                    <th v-if="colOn('status')" class="text-center">{{ $t('Status') }}</th>
                    <th v-if="colOn('notes')" class="text-left">{{ $t('Notes') }}</th>
                    <th class="text-right"></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="loading"><td :colspan="colCount" class="text-center q-py-md"><q-spinner color="primary" size="2em" /></td></tr>
                  <tr v-else-if="rows.length === 0"><td :colspan="colCount" class="text-center text-grey-5 q-py-md">{{ $t('NoRecordFound') }}</td></tr>
                  <tr v-for="t in rows" :key="t.id">
                    <td v-if="colOn('date')" style="white-space:nowrap">{{ t.tx_date ? t.tx_date.slice(0, 10) : '—' }}</td>
                    <td v-if="colOn('kind')">
                      <q-chip dense size="sm" :color="kindMeta(t.kind).color" text-color="white">
                        <q-icon :name="kindMeta(t.kind).icon" size="13px" class="q-mr-xs" />{{ $t(kindMeta(t.kind).key) }}
                      </q-chip>
                    </td>
                    <td v-if="colOn('project')" class="text-caption">{{ t.project?.name || '—' }}</td>
                    <td v-if="colOn('money_in')" class="text-right text-positive text-weight-medium">
                      <template v-if="t.direction === 'in'">{{ fmt(t.amount) }} {{ t.currency }}
                        <div v-if="t.currency !== base" class="text-caption text-grey-6">≈ {{ fmt(t.amount_base) }} {{ base }}</div>
                      </template>
                    </td>
                    <td v-if="colOn('money_out')" class="text-right text-negative text-weight-medium">
                      <template v-if="t.direction === 'out'">{{ fmt(t.amount) }} {{ t.currency }}
                        <div v-if="t.currency !== base" class="text-caption text-grey-6">≈ {{ fmt(t.amount_base) }} {{ base }}</div>
                      </template>
                    </td>
                    <td v-if="colOn('status')" class="text-center">
                      <q-chip v-if="t.status === 'reserved'" dense size="sm" color="amber-8" text-color="white">{{ $t('Reserved') }}</q-chip>
                      <q-icon v-else name="check_circle" color="positive" size="16px" />
                    </td>
                    <td v-if="colOn('notes')" class="text-caption" style="max-width:220px">{{ t.note || '—' }}</td>
                    <td class="text-right">
                      <q-chip v-if="t.investment_id || t.party_transaction_id" dense size="sm" color="blue-grey-2" text-color="blue-grey-8">
                        <q-icon name="sync" size="12px" class="q-mr-xs" />{{ $t('Auto') }}
                      </q-chip>
                      <q-btn v-else-if="$can('treasury-delete')" size="sm" dense flat round icon="delete" color="negative" @click="removeTx(t)" />
                    </td>
                  </tr>
                </tbody>
              </q-markup-table>
            </q-card-section>
          </q-card>
        </div>
      </div>
    </m-backgrounds>

    <!-- Add manual transaction -->
    <m-modal :showCM="txDialog" @update:showCM="txDialog = $event" card_style="width: 520px">
      <q-card class="bg-white">
        <n-header icon="savings">{{ $t('AddTransaction') }} — {{ $t('GeneralBudget') }}</n-header>
        <q-separator />
        <q-form @submit="saveTx">
          <q-card-section class="row q-col-gutter-sm">
            <div class="col-12" v-if="uiVisible('page.treasury.field.kind')">
              <q-btn-toggle v-model="txForm.kind" spread unelevated toggle-color="primary" color="grey-2" text-color="grey-8"
                :disable="uiReadonly('page.treasury.field.kind')"
                :options="[
                  { label: $t('Deposit'), value: 'deposit', icon: 'south_west' },
                  { label: $t('Withdrawal'), value: 'withdrawal', icon: 'north_east' },
                  { label: $t('ProjectReceipt'), value: 'project_receipt', icon: 'lock_clock' },
                ]" />
              <div class="text-caption text-grey-6 q-mt-xs" v-if="txForm.kind === 'project_receipt'">
                <q-icon name="info" size="13px" /> {{ $t('ReceiptReservedHint') }}
              </div>
            </div>
            <div class="col-12 col-sm-8" v-if="uiVisible('page.treasury.field.amount')">
              <money-input v-model="txForm.amount" v-model:currency="txForm.currency" v-model:rate="txForm.rate"
                v-model:save-rate="txForm.save_rate" :label="$t('Amount') + (uiRequired('page.treasury.field.amount', true) ? ' *' : '')" />
            </div>
            <div class="col-12 col-sm-4" v-if="uiVisible('page.treasury.field.date')">
              <shamsi-date v-model="txForm.tx_date" color="primary" :label="$t('Date')" />
            </div>
            <div class="col-12" v-if="uiVisible('page.treasury.field.project')">
              <q-select outlined dense color="primary" v-model="txForm.project_id" :options="projectOptions" emit-value map-options
                :readonly="uiReadonly('page.treasury.field.project')"
                :clearable="txForm.kind !== 'project_receipt'" :label="$t('Project')"
                :rules="(txForm.kind === 'project_receipt' || uiRequired('page.treasury.field.project')) ? [v => !!v || $t('FieldIsRequired')] : []" hide-bottom-space>
                <template v-slot:prepend><q-icon name="domain" color="primary" /></template>
              </q-select>
            </div>
            <div class="col-12" v-if="uiVisible('page.treasury.field.note')"><q-input outlined dense color="primary" v-model="txForm.note" :readonly="uiReadonly('page.treasury.field.note')" :label="$t('Notes')" /></div>
            <div class="col-12">
              <q-file outlined dense color="primary" v-model="docFiles" multiple :label="$t('AttachBill')"
                accept=".jpg,.jpeg,.png,.webp,.pdf" max-file-size="41943040" clearable counter>
                <template #prepend><q-icon name="receipt_long" color="primary" /></template>
              </q-file>
            </div>
          </q-card-section>
          <q-separator />
          <n-submit :submitting="savingTx" :label="$t('Save')" />
        </q-form>
      </q-card>
    </m-modal>
  </q-page>
</template>

<script setup>
import { ref, reactive, computed, getCurrentInstance, onMounted } from 'vue'
import { Notify } from 'quasar'
import { api } from '@/boot/axios'
import { useCurrency } from '@/composables/useCurrency'
import { useUiConfig } from '@/composables/useUiConfig'
import { uploadDocs } from '@/composables/useAttachments'

const { proxy } = getCurrentInstance()
const { base, rates, loadRates, rateFor, smartMoney } = useCurrency()
// Control Room: cards, columns, toolbar actions and form fields on this page
// are all driven by the central configuration.
const { loadUiConfig, visible: uiVisible, orderOf: uiOrder, required: uiRequired, readonly: uiReadonly } = useUiConfig()
function colOn (c) { return uiVisible('page.treasury.col.' + c) }
const rows = ref([])
const summary = ref({ available: 0, reserved: 0, total: 0, currencies: { available: {}, reserved: {} } })
const loading = ref(false)
const projectOptions = ref([])

const currencyOptions = computed(() => {
  const list = Object.keys(rates.value || {})
  return list.length ? list : ['AFN', 'USD']
})

// Currency-explicit cards: one currency shows itself; mixed shows base + split.
const availableCard = computed(() => smartMoney(summary.value.available, summary.value.currencies?.available, $tSafe('SpendableNow')))
const reservedCard = computed(() => smartMoney(summary.value.reserved, summary.value.currencies?.reserved, $tSafe('ReleasesOnCompletion')))
const totalCard = computed(() => {
  const merged = {}
  for (const m of [summary.value.currencies?.available, summary.value.currencies?.reserved]) {
    for (const [c, v] of Object.entries(m || {})) merged[c] = (merged[c] || 0) + Number(v)
  }
  return smartMoney(summary.value.total, merged, $tSafe('AvailablePlusReserved'))
})
const allocatedCard = computed(() => {
  const map = {}
  rows.value.filter(t => t.kind === 'allocation').forEach(t => { map[t.currency] = (map[t.currency] || 0) + Number(t.amount) })
  const totalBase = rows.value.filter(t => t.kind === 'allocation').reduce((s, t) => s + Number(t.amount_base || 0), 0)
  return smartMoney(totalBase, map, '')
})
function $tSafe (k) { return proxy?.$t ? proxy.$t(k) : k }

// Summary cards, ordered & filtered by the Control Room config.
const cardDefs = computed(() => [
  { key: 'available', icon: 'account_balance', label: 'Available', card: availableCard.value, color: '#175A8C', tint: '#E0EDF7', subIcon: 'check_circle', sub: availableCard.value.sub },
  { key: 'reserved', icon: 'lock_clock', label: 'Reserved', card: reservedCard.value, color: '#D97706', tint: '#FEF3C7', subIcon: 'schedule', sub: reservedCard.value.sub },
  { key: 'total', icon: 'savings', label: 'TotalBalance', card: totalCard.value, color: '#16A34A', tint: '#DCFCE7', subIcon: 'functions', sub: totalCard.value.sub },
  { key: 'allocated', icon: 'domain', label: 'AllocatedToProjects', card: allocatedCard.value, color: '#7C3AED', tint: '#EDE9FE', subIcon: 'account_balance_wallet', sub: $tSafe('CompanyShare') },
])
const visibleCards = computed(() =>
  cardDefs.value
    .map((c, idx) => ({ ...c, _idx: idx }))
    .filter((c) => uiVisible('page.treasury.card.' + c.key))
    .sort((a, b) => uiOrder('page.treasury.card.' + a.key, a._idx) - uiOrder('page.treasury.card.' + b.key, b._idx) || a._idx - b._idx)
)

const kindMap = {
  deposit: { key: 'Deposit', icon: 'south_west', color: 'green-7' },
  withdrawal: { key: 'Withdrawal', icon: 'north_east', color: 'deep-orange-6' },
  allocation: { key: 'ProjectAllocation', icon: 'domain', color: 'indigo-6' },
  project_receipt: { key: 'ProjectReceipt', icon: 'lock_clock', color: 'amber-8' },
  loan_in: { key: 'LoanIn', icon: 'call_received', color: 'teal-7' },
  loan_out: { key: 'LoanOut', icon: 'call_made', color: 'pink-6' },
  adjustment: { key: 'Adjustment', icon: 'tune', color: 'blue-grey-6' },
}
function kindMeta (k) { return kindMap[k] ?? kindMap.adjustment }
const colCount = computed(() => ['date', 'kind', 'project', 'money_in', 'money_out', 'status', 'notes'].filter(colOn).length + 1)
function fmt (v) { return Number(v || 0).toLocaleString('en-US') }

async function load () {
  loading.value = true
  try {
    const { data } = await api.get('/treasury')
    rows.value = data.transactions || []
    summary.value = data.summary || summary.value
  } finally { loading.value = false }
}
async function loadProjects () { try { const { data } = await api.get('/projects'); projectOptions.value = (data || []).map(p => ({ label: p.name, value: p.id })) } catch (_) {} }

// ── Manual transactions (deposit / withdrawal / project receipt) ──
const txDialog = ref(false)
const savingTx = ref(false)
const docFiles = ref(null)
const today = () => new Date().toISOString().slice(0, 10)
const txForm = reactive({ kind: 'deposit', amount: null, currency: 'AFN', rate: 1, save_rate: false, tx_date: today(), project_id: null, note: '' })

function openTx () {
  Object.assign(txForm, { kind: 'deposit', amount: null, currency: 'AFN', rate: 1, save_rate: false, tx_date: today(), project_id: null, note: '' })
  txDialog.value = true
}
async function saveTx () {
  savingTx.value = true
  try {
    // Optionally push the entered rate as today's official daily rate.
    if (txForm.save_rate && txForm.currency !== base.value && Number(txForm.rate) > 0) {
      try { await api.post('/exchange-rates', { currency_code: txForm.currency, rate_to_base: txForm.rate }) } catch (_) {}
    }
    const { data } = await api.post('/treasury', {
      kind: txForm.kind,
      direction: txForm.kind === 'withdrawal' ? 'out' : 'in',
      amount: txForm.amount, currency: txForm.currency, rate: txForm.rate || 1,
      tx_date: txForm.tx_date, project_id: txForm.project_id, note: txForm.note,
    })
    if (data?.id && docFiles.value) { try { await uploadDocs('treasury', data.id, docFiles.value) } catch (_) {} }
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: 'Saved' })
    txDialog.value = false; docFiles.value = null; load()
  } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Save failed' }) } finally { savingTx.value = false }
}
function removeTx (t) { proxy.$delete('treasury/' + t.id, load) }

onMounted(() => { load(); loadProjects(); loadRates(); loadUiConfig() })
</script>

<style scoped>
.cur-equiv {
  display: flex; align-items: center;
  font-size: 12.5px; color: var(--q-primary);
  background: color-mix(in srgb, var(--q-primary) 7%, #fff);
  border: 1px dashed color-mix(in srgb, var(--q-primary) 30%, #fff);
  border-radius: 8px; padding: 7px 10px;
}
</style>
