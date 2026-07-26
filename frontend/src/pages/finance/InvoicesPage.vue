<template>
  <q-page>
    <m-backgrounds>
      <div class="row my_radio_less q-pa-sm">
        <div class="col-12">
          <m-header icon="request_quote" controlRoomButton="false" class="q-mt-xs">
            {{ $t('Invoices') }}
          </m-header>
        </div>

        <action-bar
          :rows="rows"
          :columns="columns"
          filename="invoices"
          create-perm="invoice-create"
          @add="openCreate"
          @update:filtered="filteredRows = $event"
        />
        <div class="col-12">
          <n-table config-key="page.invoices"
            :loading="loading"
            :data="rows"
            :columns="columns"
            v-model:filter="filter"
            :can_edit="'invoice-edit'"
            :can_delete="'invoice-delete'"
            :can_show="'invoice-list'"
            :noInfoDialog="true"
            @info="openDetail"
            @edit="openEdit"
            @del="remove"
          >
            <template v-slot:body-cell-project="props">
              <q-td :props="props">{{ props.row.project?.name || '—' }}</q-td>
            </template>
            <template v-slot:body-cell-total="props">
              <q-td :props="props" class="text-right">{{ fmt(props.row.total) }} {{ props.row.currency }}</q-td>
            </template>
            <template v-slot:body-cell-paid_base="props">
              <q-td :props="props" class="text-right text-positive">{{ fmt(props.row.paid_base) }} {{ base }}</q-td>
            </template>
            <template v-slot:body-cell-balance_base="props">
              <q-td :props="props" class="text-right text-weight-bold" :class="Number(props.row.balance_base) > 0 ? 'text-negative' : 'text-grey-7'">
                {{ fmt(props.row.balance_base) }} {{ base }}
              </q-td>
            </template>
            <template v-slot:body-cell-status="props">
              <q-td :props="props">
                <q-chip dense size="sm" :color="statusColor(props.row.status)" text-color="white">{{ $t(statusKey(props.row.status)) }}</q-chip>
              </q-td>
            </template>
            <template v-slot:body-cell-collect="props">
              <q-td :props="props" class="text-right">
                <q-btn size="sm" dense flat round icon="payments" color="teal-8" v-if="$can('receipt-list') || $can('receipt-create')" @click="openReceipts(props.row)">
                  <q-tooltip>{{ $t('Receipts') }}</q-tooltip>
                </q-btn>
              </q-td>
            </template>
          </n-table>
        </div>
      </div>
    </m-backgrounds>

    <!-- Invoice form -->
    <m-modal :showCM="dialog" @update:showCM="dialog = $event" card_style="width: 760px">
      <q-card class="bg-white">
        <n-header icon="request_quote">{{ form.id ? $t('Edit') : $t('AddNew') }} — {{ $t('Invoice') }}</n-header>
        <q-separator />
        <q-form @submit="save">
          <q-card-section class="row q-col-gutter-sm">
            <div class="col-12 col-sm-5"><n-name :name="form.client_name" @update:name="form.client_name = $event" icon="person" :label="$t('Client')" autofocus /></div>
            <div class="col-12 col-sm-4">
              <q-select outlined dense color="primary" label-color="primary" v-model="form.project_id"
                :options="projectOptions" emit-value map-options clearable :label="$t('Project')" />
            </div>
            <div class="col-12 col-sm-3">
              <q-select outlined dense color="primary" v-model="form.status" :options="statusOptions" emit-value map-options :label="$t('Status')" />
            </div>

            <div class="col-6 col-sm-3">
              <shamsi-date v-model="form.invoice_date" color="primary" :label="$t('InvoiceDate')" />
            </div>
            <div class="col-6 col-sm-3">
              <shamsi-date v-model="form.due_date" color="primary" :label="$t('DueDate')" clearable />
            </div>
            <div class="col-6 col-sm-3">
              <q-select outlined dense color="primary" v-model="form.currency" :options="currencyOptions" @update:model-value="onCurrency" :label="$t('Currency')" />
            </div>
            <div class="col-6 col-sm-3" v-if="!isBase">
              <q-input outlined dense color="primary" type="number" step="0.0001" v-model.number="form.rate"
                :label="`${$t('Rate')} (1 ${form.currency} = ? ${base})`" hide-bottom-space>
                <template #append><q-icon name="lock" size="16px" color="grey-6" /></template>
              </q-input>
            </div>

            <!-- Line items -->
            <div class="col-12 q-mt-xs">
              <div class="row items-center justify-between q-mb-xs">
                <div class="text-caption text-weight-bold text-grey-7">{{ $t('Items') }}</div>
                <q-btn size="sm" flat dense color="teal-7" icon="add" :label="$t('AddRow')" @click="addItem" />
              </div>
              <q-markup-table flat bordered dense class="my_radio_less">
                <thead class="bg-theme-soft">
                  <tr>
                    <th class="text-left">{{ $t('Description') }}</th>
                    <th class="text-right" style="width:90px">{{ $t('Qty') }}</th>
                    <th class="text-right" style="width:130px">{{ $t('UnitPrice') }}</th>
                    <th class="text-right" style="width:130px">{{ $t('Amount') }}</th>
                    <th style="width:36px"></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(it, i) in form.items" :key="i">
                    <td><q-input dense borderless v-model="it.description" :placeholder="$t('Description')" /></td>
                    <td><q-input dense borderless type="number" step="0.01" input-class="text-right" v-model.number="it.qty" /></td>
                    <td><q-input dense borderless type="number" step="0.01" input-class="text-right" v-model.number="it.unit_price" /></td>
                    <td class="text-right text-weight-medium">{{ fmt((it.qty || 0) * (it.unit_price || 0)) }}</td>
                    <td class="text-center"><q-btn size="sm" dense flat round icon="close" color="negative" @click="form.items.splice(i, 1)" /></td>
                  </tr>
                  <tr v-if="form.items.length === 0"><td colspan="5" class="text-center text-grey-5 q-py-sm">{{ $t('NoRecordFound') }}</td></tr>
                </tbody>
              </q-markup-table>
            </div>

            <!-- Totals -->
            <div class="col-12 col-sm-6"><q-input outlined dense color="primary" type="textarea" autogrow v-model="form.notes" :label="$t('Notes')" /></div>
            <div class="col-12 col-sm-6">
              <div class="row q-col-gutter-xs">
                <div class="col-6"><q-input outlined dense color="primary" type="number" step="0.01" v-model.number="form.discount" :label="$t('Discount')" /></div>
                <div class="col-6"><q-input outlined dense color="primary" type="number" step="0.01" v-model.number="form.tax" :label="$t('Tax')" /></div>
              </div>
              <div class="totals-box q-mt-xs">
                <div class="row justify-between"><span>{{ $t('Subtotal') }}</span><b>{{ fmt(subtotal) }} {{ form.currency }}</b></div>
                <div class="row justify-between"><span>{{ $t('Total') }}</span><b class="text-primary">{{ fmt(total) }} {{ form.currency }}</b></div>
                <div v-if="!isBase" class="row justify-between text-caption text-grey-6"><span>{{ $t('BaseAmount') }}</span><span>{{ fmt(totalBase) }} {{ base }}</span></div>
              </div>
            </div>
          </q-card-section>
          <q-separator />
          <n-submit :submitting="saving" :label="$t('Save')" />
        </q-form>
      </q-card>
    </m-modal>

    <!-- Receipts modal -->
    <m-modal :showCM="receiptsDialog" @update:showCM="receiptsDialog = $event" card_style="width: 640px">
      <q-card class="bg-white" v-if="activeInv">
        <n-header icon="payments" :subtitle="activeInv.client_name">{{ activeInv.invoice_no }} — {{ $t('Receipts') }}</n-header>
        <q-separator />
        <q-card-section class="q-pb-none">
          <div class="row q-col-gutter-sm">
            <div class="col-4" v-for="m in receiptCards" :key="m.label">
              <div class="settle-chip" :style="`border-color:${m.color}`">
                <div class="settle-chip__val" :style="`color:${m.color}`">{{ fmt(m.value) }} {{ base }}</div>
                <div class="settle-chip__lbl">{{ $t(m.label) }}</div>
              </div>
            </div>
          </div>
        </q-card-section>

        <q-card-section>
          <q-markup-table flat bordered dense class="my_radio_less" style="max-height:200px">
            <thead class="bg-theme-soft">
              <tr><th class="text-left">{{ $t('ReceiptNo') }}</th><th class="text-left">{{ $t('PaymentDate') }}</th><th class="text-right">{{ $t('Amount') }}</th><th class="text-left">{{ $t('Method') }}</th><th></th></tr>
            </thead>
            <tbody>
              <tr v-if="!activeInv.receipts || activeInv.receipts.length === 0"><td colspan="5" class="text-center text-grey-5 q-py-md">{{ $t('NoRecordFound') }}</td></tr>
              <tr v-for="r in activeInv.receipts" :key="r.id">
                <td>{{ r.receipt_no }}</td>
                <td style="white-space:nowrap">{{ r.receipt_date ? r.receipt_date.slice(0,10) : '—' }}</td>
                <td class="text-right text-weight-medium">{{ fmt(r.amount) }} {{ r.currency }}</td>
                <td>{{ r.method || '—' }}</td>
                <td class="text-right"><q-btn size="sm" dense flat round icon="delete" color="negative" v-if="$can('receipt-delete')" @click="removeReceipt(r)" /></td>
              </tr>
            </tbody>
          </q-markup-table>
        </q-card-section>

        <q-card-section class="q-pt-none" v-if="$can('receipt-create')">
          <q-separator class="q-mb-sm" />
          <div class="text-caption text-weight-bold text-grey-7 q-mb-xs">{{ $t('AddReceipt') }}</div>
          <q-form @submit="saveReceipt" class="row q-col-gutter-sm items-end">
            <div class="col-6 col-sm-3">
              <shamsi-date v-model="receiptForm.receipt_date" color="primary" :label="$t('PaymentDate')" />
            </div>
            <div class="col-12 col-sm-5"><money-input v-model="receiptForm.amount" v-model:currency="receiptForm.currency" v-model:rate="receiptForm.rate" :allow-save-rate="false" :label="$t('Amount')" /></div>
            <div class="col-6 col-sm-3"><lookup-select v-model="receiptForm.method" group="payment_method" icon="payments" :label="$t('Method')" /></div>
            <div class="col-12 col-sm-1"><q-btn unelevated color="teal-7" icon="add" type="submit" :loading="savingReceipt" round dense /></div>
          </q-form>
        </q-card-section>

        <q-separator />
        <q-card-actions align="right" class="q-pa-sm"><q-btn flat :label="$t('Close')" color="grey-7" @click="receiptsDialog = false" /></q-card-actions>
      </q-card>
    </m-modal>

    <!-- Invoice detail (read-only view) -->
    <m-modal :showCM="detailDialog" @update:showCM="detailDialog = $event" card_style="width: 760px">
      <q-card class="bg-white" v-if="detail">
        <n-header icon="request_quote" :subtitle="detail.invoice_no">{{ detail.client_name }}</n-header>
        <q-separator />
        <q-card-section class="q-pb-none">
          <div class="row q-col-gutter-sm">
            <div class="col-6 col-sm-3"><stat-card dense icon="event" :label="$t('InvoiceDate')" :value="(detail.invoice_date || '').slice(0, 10)" color="#175A8C" tint="#E0EDF7" /></div>
            <div class="col-6 col-sm-3"><stat-card dense icon="domain" :label="$t('Project')" :value="detail.project?.name || '—'" color="#0D9488" tint="#CCFBF1" /></div>
            <div class="col-6 col-sm-3"><stat-card dense icon="request_quote" :label="$t('Total')" :value="fmt(detail.total)" :suffix="detail.currency" color="#2563EB" tint="#DBEAFE" /></div>
            <div class="col-6 col-sm-3">
              <stat-card dense icon="account_balance_wallet" :label="$t('Balance')" :value="fmt(detail.balance_base)" :suffix="base"
                :color="Number(detail.balance_base) > 0 ? '#DC2626' : '#16A34A'" :tint="Number(detail.balance_base) > 0 ? '#FEE2E2' : '#DCFCE7'" />
            </div>
          </div>
          <div class="row items-center q-gutter-x-sm q-mt-sm">
            <q-chip dense size="sm" :color="statusColor(detail.status)" text-color="white">{{ $t(statusKey(detail.status)) }}</q-chip>
            <span class="text-caption text-grey-7" v-if="detail.due_date">{{ $t('DueDate') }}: {{ (detail.due_date || '').slice(0, 10) }}</span>
          </div>
        </q-card-section>

        <q-card-section>
          <div class="text-caption text-weight-bold text-grey-7 q-mb-xs">{{ $t('Items') }}</div>
          <q-markup-table flat bordered dense class="my_radio_less">
            <thead class="bg-theme-soft">
              <tr><th class="text-left">{{ $t('Description') }}</th><th class="text-right">{{ $t('Qty') }}</th><th class="text-right">{{ $t('UnitPrice') }}</th><th class="text-right">{{ $t('Amount') }}</th></tr>
            </thead>
            <tbody>
              <tr v-if="!detail.items || detail.items.length === 0"><td colspan="4" class="text-center text-grey-5">—</td></tr>
              <tr v-for="it in (detail.items || [])" :key="it.id">
                <td>{{ it.description }}</td>
                <td class="text-right">{{ fmt(it.qty) }}</td>
                <td class="text-right">{{ fmt(it.unit_price) }}</td>
                <td class="text-right text-weight-medium">{{ fmt(Number(it.qty) * Number(it.unit_price)) }}</td>
              </tr>
            </tbody>
          </q-markup-table>
          <div class="totals-box q-mt-sm">
            <div class="row justify-between"><span>{{ $t('Subtotal') }}</span><b>{{ fmt(detail.subtotal) }} {{ detail.currency }}</b></div>
            <div v-if="Number(detail.discount)" class="row justify-between"><span>{{ $t('Discount') }}</span><b>-{{ fmt(detail.discount) }}</b></div>
            <div v-if="Number(detail.tax)" class="row justify-between"><span>{{ $t('Tax') }}</span><b>{{ fmt(detail.tax) }}</b></div>
            <div class="row justify-between"><span>{{ $t('Total') }}</span><b class="text-primary">{{ fmt(detail.total) }} {{ detail.currency }}</b></div>
            <div class="row justify-between text-positive"><span>{{ $t('Paid') }}</span><b>{{ fmt(detail.paid_base) }} {{ base }}</b></div>
          </div>

          <div v-if="detail.receipts?.length" class="q-mt-md">
            <div class="text-caption text-weight-bold text-grey-7 q-mb-xs">{{ $t('Receipts') }}</div>
            <div v-for="r in detail.receipts" :key="r.id" class="row items-center q-gutter-x-sm q-py-xs">
              <q-icon name="payments" size="16px" color="teal-7" />
              <span class="text-weight-medium">{{ fmt(r.amount) }} {{ r.currency }}</span>
              <span class="text-caption text-grey-7">{{ r.receipt_no }} · {{ (r.receipt_date || '').slice(0, 10) }} · {{ r.method || '—' }}</span>
            </div>
          </div>

          <q-separator class="q-my-sm" />
          <attach-box type="invoice" :id="detail.id" kind="document"
            :label="$t('Attachments')" icon="attach_file" accept="image/*,application/pdf" />
        </q-card-section>

        <q-separator />
        <q-card-actions align="right" class="q-pa-sm">
          <q-btn flat color="blue-grey-8" icon="print" :label="$t('PrintPdf')" @click="openPrint(detail)" />
          <q-btn v-if="$can('receipt-list') || $can('receipt-create')" flat color="teal-8" icon="payments" :label="$t('Receipts')" @click="detailDialog = false; openReceipts(detail)" />
          <q-btn v-if="$can('invoice-edit')" flat color="primary" icon="edit" :label="$t('Edit')" @click="detailDialog = false; openEdit(detail.id)" />
          <q-btn flat :label="$t('Close')" color="grey-7" @click="detailDialog = false" />
        </q-card-actions>
      </q-card>
    </m-modal>

    <!-- Professional printable invoice -->
    <m-modal :showCM="printDialog" @update:showCM="printDialog = $event" card_style="width: 860px; max-width: 96vw">
      <q-card class="bg-grey-2">
        <div class="row items-center q-pa-sm bg-white">
          <q-icon name="description" color="primary" size="20px" class="q-mr-sm" />
          <div class="text-subtitle2 text-weight-bold">{{ $t('Invoice') }} — {{ printInv?.invoice_no }}</div>
          <q-space />
          <q-btn dense flat color="grey-7" icon="print" :label="$t('Print')" :loading="printing" @click="doPrint" />
          <q-btn dense unelevated color="primary" icon="picture_as_pdf" :label="$t('DownloadPdf')" :loading="downloading" class="q-ml-sm" @click="doPdf" />
          <q-btn dense flat round icon="close" color="grey-7" class="q-ml-sm" @click="printDialog = false" />
        </div>
        <q-separator />
        <div class="q-pa-md" style="max-height:74vh;overflow:auto">
          <div ref="printArea" class="inv-print-holder">
            <invoice-document v-if="printInv" :invoice="printInv" :company="company" :base="base" />
          </div>
        </div>
      </q-card>
    </m-modal>
  </q-page>
</template>

<script setup>
import { ref, reactive, computed, getCurrentInstance, onMounted } from 'vue'
import { Notify } from 'quasar'
import { api } from '@/boot/axios'
import InvoiceDocument from '@/components/documents/InvoiceDocument.vue'
import { printElementToPdf, openPrintWindow } from '@/composables/useExport'

const { proxy } = getCurrentInstance()

const rows = ref([])
const filteredRows = ref([])
const loading = ref(false)
const saving = ref(false)
const dialog = ref(false)
const filter = ref('')

const base = ref('AFN')
const rateMap = ref({ AFN: 1 })
const currencyOptions = ref(['AFN'])
const projectOptions = ref([])

const statusOptions = [
  { label: 'Draft', value: 'draft' },
  { label: 'Sent', value: 'sent' },
  { label: 'Partial', value: 'partial' },
  { label: 'Paid', value: 'paid' },
  { label: 'Cancelled', value: 'cancelled' },
]
const methodOptions = [
  { label: 'Cash', value: 'cash' },
  { label: 'Bank', value: 'bank' },
  { label: 'Other', value: 'other' },
]

const today = () => new Date().toISOString().slice(0, 10)
const blankItem = () => ({ description: '', qty: 1, unit_price: 0 })
const blank = () => ({ id: null, client_name: '', project_id: null, status: 'draft', invoice_date: today(), due_date: '', currency: 'AFN', rate: 1, discount: 0, tax: 0, notes: '', items: [blankItem()] })
const form = reactive(blank())

const isBase = computed(() => form.currency === base.value)
const subtotal = computed(() => form.items.reduce((s, it) => s + (Number(it.qty || 0) * Number(it.unit_price || 0)), 0))
const total = computed(() => Math.max(0, subtotal.value - Number(form.discount || 0) + Number(form.tax || 0)))
const totalBase = computed(() => total.value * Number(isBase.value ? 1 : (form.rate || 0)))

const columns = [
  { name: 'created_at', label: '#', field: 'id', align: 'left' },
  { name: 'invoice_no', label: 'InvoiceNo', field: 'invoice_no', align: 'left', sortable: true },
  { name: 'invoice_date', label: 'InvoiceDate', field: 'invoice_date', align: 'left', sortable: true },
  { name: 'client_name', label: 'Client', field: 'client_name', align: 'left', sortable: true },
  { name: 'project', label: 'Project', field: 'project', align: 'left' },
  { name: 'total', label: 'Total', field: 'total', align: 'right', sortable: true },
  { name: 'paid_base', label: 'Paid', field: 'paid_base', align: 'right' },
  { name: 'balance_base', label: 'Balance', field: 'balance_base', align: 'right' },
  { name: 'status', label: 'Status', field: 'status', align: 'center' },
  { name: 'collect', label: 'Receipts', field: 'collect', align: 'right' },
  { name: 'actions', label: 'Actions', field: 'actions', align: 'right' }
]

function fmt (v) { return Number(v || 0).toLocaleString('en-US', { maximumFractionDigits: 2 }) }
function statusColor (s) { return { draft: 'grey-6', sent: 'blue-7', partial: 'amber-8', paid: 'positive', cancelled: 'blue-grey-4' }[s] ?? 'grey' }
function statusKey (s) { return { draft: 'Draft', sent: 'Sent', partial: 'Partial', paid: 'Paid', cancelled: 'Cancelled' }[s] ?? 'Draft' }
function addItem () { form.items.push(blankItem()) }
function onCurrency (code) { form.rate = code === base.value ? 1 : (rateMap.value[code] || 1) }

async function loadMeta () {
  try {
    const { data } = await api.get('/exchange-rates/current')
    base.value = data.base || 'AFN'
    rateMap.value = data.rates || { AFN: 1 }
    currencyOptions.value = Object.keys(rateMap.value)
  } catch (_) {}
  try {
    const { data } = await api.get('/projects')
    projectOptions.value = (data || []).map(p => ({ label: p.name, value: p.id }))
  } catch (_) {}
}

async function load () {
  loading.value = true
  try {
    const { data } = await api.get('/invoices')
    rows.value = data
  } finally { loading.value = false }
}

function openCreate () {
  Object.assign(form, blank())
  form.currency = base.value
  dialog.value = true
}
async function openEdit (id) {
  try {
    const { data } = await api.get('/invoices/' + id)
    Object.assign(form, {
      id: data.id, client_name: data.client_name, project_id: data.project_id, status: data.status,
      invoice_date: data.invoice_date ? data.invoice_date.slice(0, 10) : today(),
      due_date: data.due_date ? data.due_date.slice(0, 10) : '', currency: data.currency, rate: Number(data.rate),
      discount: Number(data.discount), tax: Number(data.tax), notes: data.notes || '',
      items: (data.items || []).map(it => ({ description: it.description, qty: Number(it.qty), unit_price: Number(it.unit_price) })),
    })
    if (form.items.length === 0) form.items.push(blankItem())
    dialog.value = true
  } catch (e) {
    Notify.create({ type: 'negative', message: 'Load failed' })
  }
}

async function save () {
  if (subtotal.value <= 0) return Notify.create({ type: 'warning', message: 'Add at least one line item with an amount' })
  saving.value = true
  try {
    const payload = {
      client_name: form.client_name, project_id: form.project_id, status: form.status,
      invoice_date: form.invoice_date, due_date: form.due_date || null,
      currency: form.currency, rate: isBase.value ? 1 : form.rate, discount: form.discount || 0, tax: form.tax || 0,
      notes: form.notes, items: form.items.filter(it => it.description),
    }
    if (form.id) await api.put('/invoices/' + form.id, payload)
    else await api.post('/invoices', payload)
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: 'Saved' })
    dialog.value = false
    load()
  } catch (e) {
    Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Save failed' })
  } finally { saving.value = false }
}

function remove (id) { proxy.$delete('invoices/' + id, load) }

// ── Detail (read-only) ──
const detailDialog = ref(false)
const detail = ref(null)
async function openDetail (id) {
  detail.value = null
  detailDialog.value = true
  try { const { data } = await api.get('/invoices/' + id); detail.value = data } catch (_) { Notify.create({ type: 'negative', message: 'Load failed' }) }
}

// ── Printable invoice ──
const printDialog = ref(false)
const printInv = ref(null)
const printArea = ref(null)
const printing = ref(false)
const downloading = ref(false)
const company = ref({})
async function loadCompany () {
  try {
    const { data } = await api.get('/company')
    const c = Array.isArray(data) ? data[0] : (data.data ?? data)
    company.value = c || {}
  } catch (_) { company.value = {} }
}
function openPrint (inv) { printInv.value = inv; detailDialog.value = false; printDialog.value = true }
async function doPdf () {
  downloading.value = true
  try { await printElementToPdf(printArea.value?.firstElementChild || printArea.value, 'Invoice-' + (printInv.value?.invoice_no || '')) } finally { downloading.value = false }
}
async function doPrint () {
  printing.value = true
  try { await openPrintWindow(printArea.value?.firstElementChild || printArea.value, 'Invoice ' + (printInv.value?.invoice_no || '')) } finally { printing.value = false }
}

// ── Receipts ──
const receiptsDialog = ref(false)
const activeInv = ref(null)
const savingReceipt = ref(false)
const receiptForm = reactive({ receipt_date: today(), amount: null, currency: 'AFN', rate: 1, method: 'cash' })

const receiptCards = computed(() => {
  const inv = activeInv.value || {}
  return [
    { label: 'Total', value: inv.total_base, color: '#175A8C' },
    { label: 'Paid', value: inv.paid_base, color: '#059669' },
    { label: 'Balance', value: inv.balance_base, color: Number(inv.balance_base) > 0 ? '#DC2626' : '#64748B' },
  ]
})

function onReceiptCurrency (code) { receiptForm.rate = code === base.value ? 1 : (rateMap.value[code] || 1) }

async function openReceipts (inv) {
  activeInv.value = { ...inv, receipts: [] }
  Object.assign(receiptForm, { receipt_date: today(), amount: null, currency: base.value, rate: 1, method: 'cash' })
  receiptsDialog.value = true
  await refreshInv(inv.id)
}
async function refreshInv (invId) {
  try { const { data } = await api.get('/invoices/' + invId); activeInv.value = data } catch (_) {}
}
async function saveReceipt () {
  if (!activeInv.value) return
  savingReceipt.value = true
  try {
    await api.post('/receipts', {
      invoice_id: activeInv.value.id, receipt_date: receiptForm.receipt_date, amount: receiptForm.amount,
      currency: receiptForm.currency, rate: receiptForm.currency === base.value ? 1 : receiptForm.rate, method: receiptForm.method,
    })
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: 'Saved' })
    receiptForm.amount = null
    await refreshInv(activeInv.value.id)
    load()
  } catch (e) {
    Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Save failed' })
  } finally { savingReceipt.value = false }
}
function removeReceipt (r) {
  proxy.$delete('receipts/' + r.id, async () => { await refreshInv(activeInv.value.id); load() })
}

onMounted(() => { loadMeta(); load(); loadCompany() })
</script>

<style scoped>
.totals-box { background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 8px 12px; font-size: 13px; }
.totals-box .row { padding: 2px 0; }
.settle-chip { border: 1.5px solid #E2E8F0; border-radius: 8px; padding: 6px 8px; text-align: center; background: #F8FAFC; }
.settle-chip__val { font-size: 13px; font-weight: 800; letter-spacing: -0.3px; }
.settle-chip__lbl { font-size: 9px; color: #94A3B8; margin-top: 2px; }
</style>
