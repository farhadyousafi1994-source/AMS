<template>
  <q-page>
    <m-backgrounds>
      <div class="row my_radio_less q-pa-sm">
        <div class="col-12">
          <m-header icon="account_balance_wallet" controlRoomButton="false" class="q-mt-xs">
            {{ $t('PartyAccounts') }}
          </m-header>
        </div>

        <!-- Summary cards (main-dashboard animation language) -->
        <div class="col-12 q-mt-md">
          <div class="row q-col-gutter-md">
            <div class="col-6 col-md-3">
              <stat-card icon="call_received" :label="$t('WeOwe')" :value="weOweCard.value" :suffix="weOweCard.suffix"
                color="#DC2626" tint="#FEE2E2" :sub="weOweCard.sub" sub-icon="south_west" />
            </div>
            <div class="col-6 col-md-3">
              <stat-card icon="call_made" :label="$t('TheyOwe')" :value="theyOweCard.value" :suffix="theyOweCard.suffix"
                color="#16A34A" tint="#DCFCE7" :sub="theyOweCard.sub" sub-icon="north_east" />
            </div>
            <div class="col-6 col-md-3">
              <stat-card icon="hourglass_top" :label="$t('PendingPromises')" :value="pendingCard.value" :suffix="pendingCard.suffix"
                color="#D97706" tint="#FEF3C7" :sub="pendingCard.sub" sub-icon="schedule" />
            </div>
            <div class="col-6 col-md-3">
              <stat-card icon="group" :label="$t('Parties')" :value="summary.parties"
                color="#175A8C" tint="#E0EDF7" :sub="$t('Transactions')" sub-icon="swap_horiz" />
            </div>
          </div>
        </div>

        <action-bar :rows="rows" :columns="exportColumns" filename="party-accounts" create-perm="party-create" @add="openCreate" @update:filtered="() => {}">
          <template #filters>
            <div class="col-6 col-sm-3"><q-select outlined dense color="blue-grey-9" label-color="blue-grey-9" v-model="typeFilter" :options="typeOptions" emit-value map-options clearable :label="$t('PartyType')" @update:model-value="load" /></div>
          </template>
        </action-bar>
        <div class="col-12 acc-anim" style="animation-delay:340ms">
          <n-table config-key="page.accounts" :loading="loading" :data="rows" :columns="columns" v-model:filter="tableFilter"
            :can_edit="'party-edit'" :can_delete="'party-delete'" :can_show="'party-list'"
            info-icon="payments" :noInfoDialog="true" @info="openStatement" @edit="openEdit" @del="remove">
            <template v-slot:body-cell-name="props">
              <q-td :props="props"><a class="acc-link" @click.prevent="openStatement(props.row.id)">{{ props.row.name }}</a>
                <div v-if="props.row.relation" class="text-caption text-grey-6">{{ props.row.relation }}</div>
              </q-td>
            </template>
            <template v-slot:body-cell-type="props">
              <q-td :props="props"><q-chip dense size="sm" :color="typeColor(props.row.type)" text-color="white">{{ $t(typeKey(props.row.type)) }}</q-chip></q-td>
            </template>
            <template v-slot:body-cell-in_total="props">
              <q-td :props="props" class="text-right">
                <span v-if="Number(props.row.in_total) > 0" class="text-green-8 text-weight-medium">
                  <q-icon name="south_west" size="12px" class="q-mr-xs" />{{ fmt(props.row.in_total) }} <span class="acc-cur">{{ base }}</span>
                </span>
                <span v-else class="text-grey-5">—</span>
              </q-td>
            </template>
            <template v-slot:body-cell-out_total="props">
              <q-td :props="props" class="text-right">
                <span v-if="Number(props.row.out_total) > 0" class="text-deep-orange-8 text-weight-medium">
                  <q-icon name="north_east" size="12px" class="q-mr-xs" />{{ fmt(props.row.out_total) }} <span class="acc-cur">{{ base }}</span>
                </span>
                <span v-else class="text-grey-5">—</span>
              </q-td>
            </template>
            <template v-slot:body-cell-balance="props">
              <q-td :props="props" class="text-right">
                <q-chip dense size="sm" :color="props.row.balance > 0 ? 'red-1' : (props.row.balance < 0 ? 'green-1' : 'grey-2')"
                  :text-color="props.row.balance > 0 ? 'red-9' : (props.row.balance < 0 ? 'green-9' : 'grey-8')" class="text-weight-bold">
                  {{ fmt(Math.abs(props.row.balance)) }} {{ props.row.balance > 0 ? $t('Credit') : (props.row.balance < 0 ? $t('Debit') : '') }}
                </q-chip>
              </q-td>
            </template>
            <template v-slot:body-cell-pending_total="props">
              <q-td :props="props" class="text-right" :class="Number(props.row.pending_total) > 0 ? 'text-orange-8 text-weight-medium' : 'text-grey-5'">
                {{ fmt(props.row.pending_total) }}
              </q-td>
            </template>
          </n-table>
        </div>
      </div>
    </m-backgrounds>

    <!-- Add / Edit party -->
    <m-modal :showCM="dialog" @update:showCM="dialog = $event" card_style="width: 480px">
      <q-card class="bg-white">
        <n-header icon="account_balance_wallet">{{ form.id ? $t('Edit') : $t('AddNew') }} — {{ $t('Party') }}</n-header>
        <q-separator />
        <q-form @submit="save">
          <q-card-section class="row q-col-gutter-sm">
            <div class="col-12 col-sm-8"><n-name :name="form.name" @update:name="form.name = $event" icon="person" :label="$t('Name')" autofocus /></div>
            <div class="col-12 col-sm-4"><q-select outlined dense color="primary" v-model="form.type" :options="typeOptions" emit-value map-options :label="$t('PartyType')" /></div>
            <div class="col-12 col-sm-6"><n-name :name="form.phone" @update:name="form.phone = $event" icon="phone" :label="$t('Phone')" :rules="[]" /></div>
            <div class="col-12 col-sm-6"><n-name :name="form.relation" @update:name="form.relation = $event" icon="link" :label="$t('Relation')" :rules="[]" /></div>
            <div class="col-12"><n-name :name="form.address" @update:name="form.address = $event" icon="home" :label="$t('Address')" :rules="[]" /></div>
            <div class="col-12"><q-input outlined dense color="primary" type="textarea" autogrow v-model="form.notes" :label="$t('Description')" /></div>
          </q-card-section>
          <q-separator />
          <n-submit :submitting="saving" :label="$t('Save')" />
        </q-form>
      </q-card>
    </m-modal>

    <!-- Statement modal -->
    <m-modal :showCM="statementDialog" @update:showCM="statementDialog = $event" card_style="width: 860px">
      <q-card class="bg-white" v-if="active">
        <n-header icon="receipt_long" :subtitle="active.code">{{ active.name }} — {{ $t('Statement') }}</n-header>
        <q-separator />

        <q-card-section class="q-pb-none">
          <div class="row q-col-gutter-xs">
            <div class="col-12 col-sm-4">
              <stat-card dense icon="account_balance_wallet" :label="$t('TotalBalance')"
                :value="stCards.bal.value" :suffix="stCards.bal.suffix" :sub="stCards.bal.sub"
                :color="stCards.netBase > 0 ? '#DC2626' : (stCards.netBase < 0 ? '#16A34A' : '#94A3B8')"
                :tint="stCards.netBase > 0 ? '#FEE2E2' : (stCards.netBase < 0 ? '#DCFCE7' : '#F1F5F9')" />
            </div>
            <div class="col-6 col-sm-4">
              <stat-card dense icon="south_west" :label="$t('TotalMoneyIn')"
                :value="stCards.tin.value" :suffix="stCards.tin.suffix" :sub="stCards.tin.sub"
                color="#16A34A" tint="#DCFCE7" />
            </div>
            <div class="col-6 col-sm-4">
              <stat-card dense icon="north_east" :label="$t('TotalMoneyOut')"
                :value="stCards.tout.value" :suffix="stCards.tout.suffix" :sub="stCards.tout.sub"
                color="#DC2626" tint="#FEE2E2" />
            </div>
            <div class="col-12 text-right q-pt-sm">
              <q-btn outline dense color="primary" icon="print" :label="$t('PrintStatement')" @click="printStatement" class="q-mr-xs" />
              <q-btn unelevated dense color="teal-7" icon="add" :label="$t('AddTransaction')" v-if="$can('party-transaction-create')" @click="txDialog = true" />
            </div>
          </div>
        </q-card-section>

        <q-card-section>
          <q-markup-table flat bordered dense class="my_radio_less" style="max-height:380px">
            <thead class="bg-theme-soft">
              <tr>
                <th class="text-left">{{ $t('Date') }}</th>
                <th class="text-center">{{ $t('Direction') }}</th>
                <th class="text-right">{{ $t('Amount') }}</th>
                <th class="text-left">{{ $t('Basis') }}</th>
                <th class="text-left">{{ $t('Project') }}</th>
                <th class="text-right">{{ $t('RunningBalance') }} ({{ base }})</th>
                <th class="text-center">{{ $t('Status') }}</th>
                <th class="text-right"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!active.statement || active.statement.length === 0"><td colspan="8" class="text-center text-grey-5 q-py-md">{{ $t('NoRecordFound') }}</td></tr>
              <tr v-for="t in active.statement" :key="t.id">
                <td style="white-space:nowrap">{{ t.tx_date ? t.tx_date.slice(0, 10) : '—' }}</td>
                <td class="text-center">
                  <q-chip dense size="sm" :color="t.direction === 'in' ? 'green-7' : 'deep-orange-6'" text-color="white">
                    <q-icon :name="t.direction === 'in' ? 'south_west' : 'north_east'" size="13px" class="q-mr-xs" />
                    {{ $t(t.direction === 'in' ? 'MoneyIn' : 'MoneyOut') }}
                  </q-chip>
                </td>
                <td class="text-right text-weight-medium">{{ fmt(t.amount) }} {{ t.currency }}</td>
                <td class="text-caption" style="max-width:180px">{{ t.basis || '—' }}</td>
                <td class="text-caption">{{ t.project?.name || '—' }}</td>
                <td class="text-right text-weight-bold" :class="t.running_balance > 0 ? 'text-negative' : 'text-positive'">{{ fmt(t.running_balance) }}</td>
                <td class="text-center">
                  <q-chip v-if="t.status === 'pending'" dense size="sm" color="amber-8" text-color="white">{{ $t('Pending') }}</q-chip>
                  <q-icon v-else name="check_circle" color="positive" size="16px" />
                </td>
                <td class="text-right" style="white-space:nowrap">
                  <q-btn v-if="t.attachment_path" size="sm" dense flat round icon="image" color="indigo-7" @click="viewAttachment(t)"><q-tooltip>{{ $t('Attachment') }}</q-tooltip></q-btn>
                  <q-btn v-if="t.status === 'pending' && $can('party-transaction-edit')" size="sm" dense flat round icon="task_alt" color="positive" @click="confirmTx(t)"><q-tooltip>{{ $t('Confirm') }}</q-tooltip></q-btn>
                  <q-btn size="sm" dense flat round icon="delete" color="negative" v-if="$can('party-transaction-delete')" @click="removeTx(t)" />
                </td>
              </tr>
            </tbody>
          </q-markup-table>
        </q-card-section>

        <q-separator />
        <q-card-actions align="right" class="q-pa-sm"><q-btn flat :label="$t('Close')" color="grey-7" @click="statementDialog = false" /></q-card-actions>
      </q-card>
    </m-modal>

    <!-- Add transaction -->
    <m-modal :showCM="txDialog" @update:showCM="txDialog = $event" card_style="width: 560px">
      <q-card class="bg-white" v-if="active">
        <n-header icon="payments" :subtitle="active.name">{{ $t('AddTransaction') }}</n-header>
        <q-separator />
        <q-form @submit="saveTx">
          <q-card-section class="row q-col-gutter-sm">
            <div class="col-12">
              <q-btn-toggle v-model="txForm.direction" spread unelevated toggle-color="primary" color="grey-2" text-color="grey-8"
                :options="[{ label: $t('WeReceived'), value: 'in', icon: 'south_west' }, { label: $t('WePaid'), value: 'out', icon: 'north_east' }]" />
            </div>
            <div class="col-12 col-sm-8">
              <money-input v-model="txForm.amount" v-model:currency="txForm.currency" v-model:rate="txForm.rate" :label="$t('Amount')" />
            </div>
            <div class="col-6 col-sm-4">
              <shamsi-date v-model="txForm.tx_date" color="primary" :label="$t('Date')" />
            </div>
            <div class="col-6 col-sm-4"><q-select outlined dense color="primary" v-model="txForm.method" :options="methodOptions" emit-value map-options :label="$t('Method')" /></div>
            <div class="col-6 col-sm-4"><q-select outlined dense color="primary" v-model="txForm.status" :options="[{ label: $t('Confirmed'), value: 'confirmed' }, { label: $t('Pending'), value: 'pending' }]" emit-value map-options :label="$t('Status')" /></div>
            <div class="col-12 col-sm-6">
              <q-select outlined dense color="primary" v-model="txForm.project_id" :options="projectOptions" emit-value map-options clearable :label="$t('Project')">
                <template v-slot:prepend><q-icon name="domain" color="primary" /></template>
              </q-select>
            </div>
            <div class="col-12 col-sm-6"><n-name :name="txForm.handled_by" @update:name="txForm.handled_by = $event" icon="person" :label="$t('HandledBy')" :rules="[]" /></div>
            <div class="col-12"><n-name :name="txForm.basis" @update:name="txForm.basis = $event" icon="gavel" :label="$t('Basis')" :rules="[]" /></div>
            <div class="col-12">
              <q-file outlined dense color="primary" v-model="txFile" :label="$t('AttachReceipt')" accept=".jpg,.jpeg,.png,.pdf" max-file-size="41943040" clearable>
                <template #prepend><q-icon name="attach_file" color="primary" /></template>
              </q-file>
            </div>
            <div class="col-12"><q-input outlined dense color="primary" type="textarea" autogrow v-model="txForm.note" :label="$t('Notes')" /></div>
          </q-card-section>
          <q-separator />
          <n-submit :submitting="savingTx" :label="$t('Save')" />
        </q-form>
      </q-card>
    </m-modal>

    <!-- Attachment preview -->
    <q-dialog v-model="attachDialog">
      <q-card class="bg-white" style="width:560px;max-width:95vw" v-if="attachTx">
        <n-header icon="image" :subtitle="attachTx.attachment_name">{{ $t('Attachment') }}</n-header>
        <q-separator />
        <q-card-section class="text-center" style="max-height:60vh;overflow:auto">
          <img v-if="attachUrl" :src="attachUrl" style="max-width:100%;border-radius:8px" />
          <q-spinner v-else color="primary" size="2em" />
        </q-card-section>
        <q-separator />
        <q-card-actions align="right" class="q-pa-sm">
          <q-btn flat :label="$t('Close')" color="grey-7" @click="attachDialog = false" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { ref, reactive, computed, getCurrentInstance, onMounted } from 'vue'
import { Notify } from 'quasar'
import { api } from '@/boot/axios'
import { compressImage } from '@/utils/image'
import { useCurrency } from '@/composables/useCurrency'

const { proxy } = getCurrentInstance()
const { base, rates, loadRates, rateFor, smartMoney, ledgerTotals, netMoney } = useCurrency()

const currencyOptions = computed(() => {
  const list = Object.keys(rates.value || {})
  return list.length ? list : ['AFN', 'USD']
})
const weOweCard = computed(() => smartMoney(summary.value.we_owe, summary.value.currencies?.we_owe, proxy?.$t ? proxy.$t('Credit') : 'Credit'))
const theyOweCard = computed(() => smartMoney(summary.value.they_owe, summary.value.currencies?.they_owe, proxy?.$t ? proxy.$t('Debit') : 'Debit'))
const pendingCard = computed(() => smartMoney(summary.value.pending, summary.value.currencies?.pending, proxy?.$t ? proxy.$t('Pending') : 'Pending'))
const rows = ref([])
const summary = ref({ we_owe: 0, they_owe: 0, pending: 0, parties: 0 })
const loading = ref(false)
const saving = ref(false)
const dialog = ref(false)
const tableFilter = ref('')
const typeFilter = ref(null)
const projectOptions = ref([])

const typeOptions = [
  { label: 'Person', value: 'person' },
  { label: 'Company', value: 'company' },
  { label: 'Bank', value: 'bank' },
  { label: 'Exchange (صرافی)', value: 'exchange' },
  { label: 'Relative', value: 'relative' },
  { label: 'Other', value: 'other' },
]
const methodOptions = [
  { label: 'Cash', value: 'cash' },
  { label: 'Bank', value: 'bank' },
  { label: 'Hawala', value: 'hawala' },
  { label: 'Other', value: 'other' },
]

const today = () => new Date().toISOString().slice(0, 10)
const blank = () => ({ id: null, name: '', type: 'person', phone: '', relation: '', address: '', notes: '' })
const form = reactive(blank())

const columns = [
  { name: 'created_at', label: '#', field: 'id', align: 'left' },
  { name: 'code', label: 'Code', field: 'code', align: 'left', sortable: true },
  { name: 'name', label: 'Name', field: 'name', align: 'left', sortable: true },
  { name: 'type', label: 'PartyType', field: 'type', align: 'left' },
  { name: 'phone', label: 'Phone', field: 'phone', align: 'left' },
  { name: 'transactions_count', label: 'Transactions', field: 'transactions_count', align: 'center', sortable: true },
  { name: 'in_total', label: 'MoneyIn', field: 'in_total', align: 'right', sortable: true },
  { name: 'out_total', label: 'MoneyOut', field: 'out_total', align: 'right', sortable: true },
  { name: 'balance', label: 'Balance', field: 'balance', align: 'right', sortable: true },
  { name: 'pending_total', label: 'Pending', field: 'pending_total', align: 'right' },
  { name: 'actions', label: 'Actions', field: 'actions', align: 'right' }
]
const exportColumns = columns.filter(c => c.name !== 'actions')

function fmt (v) { return Number(v || 0).toLocaleString('en-US') }
function typeColor (t) { return { person: 'blue-7', company: 'deep-purple-6', bank: 'teal-7', exchange: 'orange-8', relative: 'pink-6', other: 'blue-grey-6' }[t] ?? 'grey' }
function typeKey (t) { return { person: 'Person', company: 'Company', bank: 'Bank', exchange: 'Exchange', relative: 'Relative', other: 'Other' }[t] ?? 'Person' }

async function load () {
  loading.value = true
  try {
    const params = typeFilter.value ? { type: typeFilter.value } : {}
    const { data } = await api.get('/parties', { params })
    rows.value = data.parties || []
    summary.value = data.summary || summary.value
  } finally { loading.value = false }
}
async function loadProjects () { try { const { data } = await api.get('/projects'); projectOptions.value = (data || []).map(p => ({ label: p.name, value: p.id })) } catch (_) {} }

function openCreate () { Object.assign(form, blank()); dialog.value = true }
function openEdit (id) {
  const r = rows.value.find(x => x.id === id)
  if (!r) return
  Object.assign(form, { id: r.id, name: r.name, type: r.type, phone: r.phone || '', relation: r.relation || '', address: r.address || '', notes: r.notes || '' })
  dialog.value = true
}
async function save () {
  saving.value = true
  try {
    const payload = { name: form.name, type: form.type, phone: form.phone, relation: form.relation, address: form.address, notes: form.notes }
    if (form.id) await api.put('/parties/' + form.id, payload)
    else await api.post('/parties', payload)
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: 'Saved' })
    dialog.value = false; load()
  } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Save failed' }) } finally { saving.value = false }
}
function remove (id) { proxy.$delete('parties/' + id, load) }

// ── Statement ──
const statementDialog = ref(false)
const active = ref(null)
// Statement cards: single-currency ledgers show their own currency; mixed
// ledgers show the base total (locked rates) with a per-currency split.
const stCards = computed(() => {
  const { inBase, outBase, netBase, maps } = ledgerTotals(active.value?.statement)
  return {
    netBase,
    tin: smartMoney(inBase, maps.in),
    tout: smartMoney(outBase, maps.out),
    bal: netMoney(netBase, maps.net, proxy?.$t ? proxy.$t('Credit') : 'Credit', proxy?.$t ? proxy.$t('Debit') : 'Debit'),
  }
})
async function openStatement (id) {
  try { const { data } = await api.get('/parties/' + id); active.value = data; statementDialog.value = true } catch (_) {}
}
async function refreshStatement () { if (active.value) { try { const { data } = await api.get('/parties/' + active.value.id); active.value = data } catch (_) {} load() } }

// ── Transactions ──
const txDialog = ref(false)
const savingTx = ref(false)
const txFile = ref(null)
const txForm = reactive({ direction: 'in', amount: null, currency: 'AFN', rate: 1, tx_date: today(), method: 'cash', status: 'confirmed', project_id: null, handled_by: '', basis: '', note: '' })
async function saveTx () {
  savingTx.value = true
  try {
    const fd = new FormData()
    Object.entries(txForm).forEach(([k, v]) => { if (v !== null && v !== '') fd.append(k, v) })
    if (txFile.value) fd.append('attachment', await compressImage(txFile.value))
    await api.post('/parties/' + active.value.id + '/transactions', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: 'Saved' })
    txDialog.value = false; txFile.value = null
    Object.assign(txForm, { amount: null, basis: '', note: '', tx_date: today(), status: 'confirmed' })
    refreshStatement()
  } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Save failed' }) } finally { savingTx.value = false }
}
async function confirmTx (t) {
  try { await api.put('/party-transactions/' + t.id + '/confirm'); Notify.create({ type: 'positive', position: 'bottom', message: 'Confirmed' }); refreshStatement() } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Failed' }) }
}
function removeTx (t) { proxy.$delete('party-transactions/' + t.id, refreshStatement) }

// ── Attachment preview ──
const attachDialog = ref(false)
const attachTx = ref(null)
const attachUrl = ref(null)
async function viewAttachment (t) {
  attachTx.value = t; attachUrl.value = null; attachDialog.value = true
  try {
    const res = await api.get('/party-transactions/' + t.id + '/attachment', { responseType: 'blob' })
    attachUrl.value = URL.createObjectURL(new Blob([res.data], { type: t.attachment_mime }))
  } catch (_) {}
}

// ── Printable statement ──
function printStatement () {
  const p = active.value
  if (!p) return
  const esc = (s) => String(s ?? '—').replace(/</g, '&lt;')
  const rowsHtml = (p.statement || []).map(t =>
    `<tr><td>${(t.tx_date || '').slice(0, 10)}</td><td>${t.direction === 'in' ? 'IN' : 'OUT'}</td>` +
    `<td style="text-align:end">${Number(t.amount).toLocaleString()} ${t.currency}</td>` +
    `<td>${esc(t.basis)}</td><td>${esc(t.project?.name)}</td>` +
    `<td style="text-align:end;font-weight:bold">${Number(t.running_balance).toLocaleString()}</td>` +
    `<td>${t.status}</td></tr>`).join('')
  const html = `<!DOCTYPE html><html dir="auto"><head><meta charset="utf-8"><title>${esc(p.name)}</title><style>
    body{font-family:Arial;margin:26px;color:#1E293B;font-size:12px}
    h1{color:#123A66;font-size:20px;margin:0} .sub{color:#64748B;margin-bottom:12px}
    table{border-collapse:collapse;width:100%;font-size:11.5px;margin-top:10px}
    th{background:#EEF4FB;text-align:start;padding:5px 7px;border:1px solid #CBD5E1}
    td{padding:5px 7px;border:1px solid #E2E8F0}
    .bal{font-size:15px;font-weight:bold;margin-top:8px}
  </style></head><body>
    <h1>${esc(p.name)} — Account Statement</h1>
    <div class="sub">${esc(p.code)} · Aria Herat Mohandes Zada · ${new Date().toLocaleDateString()}</div>
    <div class="bal">Balance: ${Number(Math.abs(p.balance)).toLocaleString()} ${p.balance > 0 ? '(Credit — we owe)' : (p.balance < 0 ? '(Debit — owes us)' : '')}</div>
    <table><thead><tr><th>Date</th><th>Dir</th><th>Amount</th><th>Basis</th><th>Project</th><th>Running</th><th>Status</th></tr></thead>
    <tbody>${rowsHtml}</tbody></table>
    <script>window.onload = () => window.print()<\/script></body></html>`
  const w = window.open('', '_blank')
  if (!w) return
  w.document.write(html); w.document.close()
}

onMounted(() => { load(); loadProjects(); loadRates() })
</script>

<style scoped>
.acc-link { color: var(--q-primary); font-weight: 600; cursor: pointer; text-decoration: none; }
.acc-link:hover { text-decoration: underline; }
.acc-cur { font-size: 10px; color: #94A3B8; font-weight: 600; }

/* Filters and table follow the cards in (card animation lives in StatCard) */
.acc-anim { animation: accin 0.5s backwards; }
@keyframes accin { from { opacity: 0; transform: translateY(14px); } }
.cur-equiv {
  display: flex; align-items: center;
  font-size: 12.5px; color: var(--q-primary);
  background: color-mix(in srgb, var(--q-primary) 7%, #fff);
  border: 1px dashed color-mix(in srgb, var(--q-primary) 30%, #fff);
  border-radius: 8px; padding: 7px 10px;
}
</style>
