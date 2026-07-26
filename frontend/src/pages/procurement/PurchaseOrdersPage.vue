<template>
  <q-page>
    <m-backgrounds>
      <div class="row my_radio_less q-pa-sm">
        <div class="col-12">
          <m-header icon="shopping_cart" controlRoomButton="false" class="q-mt-xs">{{ $t('PurchaseOrders') }}</m-header>
        </div>

        <action-bar :rows="rows" :columns="exportColumns" filename="purchase-orders" create-perm="purchase-order-create" @add="openCreate" @update:filtered="() => {}" />
        <div class="col-12">
          <n-table config-key="page.purchaseOrders" :loading="loading" :data="rows" :columns="columns" v-model:filter="tableFilter"
            :can_edit="'purchase-order-edit'" :can_delete="'purchase-order-delete'" :noInfo="true" @edit="openEdit" @del="remove">
            <template v-slot:body-cell-code="props">
              <q-td :props="props"><a class="po-link" @click.prevent="openShow(props.row.id)">{{ props.row.code }}</a></q-td>
            </template>
            <template v-slot:body-cell-status="props">
              <q-td :props="props"><q-chip dense size="sm" :color="statusColor(props.row.status)" text-color="white">{{ $t(statusKey(props.row.status)) }}</q-chip></q-td>
            </template>
            <template v-slot:body-cell-total="props">
              <q-td :props="props" class="text-right text-weight-medium">{{ fmt(props.row.total) }} {{ props.row.currency }}</q-td>
            </template>
          </n-table>
        </div>
      </div>
    </m-backgrounds>

    <!-- Create / Edit PO with line items -->
    <m-modal :showCM="dialog" @update:showCM="dialog = $event" card_style="width: 720px">
      <q-card class="bg-white">
        <n-header icon="shopping_cart">{{ form.id ? $t('Edit') : $t('AddNew') }} — {{ $t('PurchaseOrder') }}</n-header>
        <q-separator />
        <q-form @submit="save">
          <q-card-section class="row q-col-gutter-sm">
            <div class="col-12 col-sm-5"><q-select outlined dense color="primary" v-model="form.supplier_id" :options="supplierOptions" emit-value map-options :label="$t('Supplier')" :rules="[v => !!v || $t('FieldIsRequired')]" hide-bottom-space /></div>
            <div class="col-12 col-sm-4"><q-select outlined dense color="primary" v-model="form.project_id" :options="projectOptions" emit-value map-options clearable :label="$t('Project')" /></div>
            <div class="col-6 col-sm-3"><q-select outlined dense color="primary" v-model="form.status" :options="statusOptions" emit-value map-options :label="$t('Status')" /></div>
            <div class="col-6 col-sm-3">
              <shamsi-date v-model="form.order_date" color="primary" :label="$t('Date')" />
            </div>
            <div class="col-6 col-sm-3"><q-select outlined dense color="primary" v-model="form.currency" :options="currencyOptions" :label="$t('Currency')" @update:model-value="form.rate = rateFor(form.currency)" /></div>
            <div class="col-6 col-sm-3"><q-input outlined dense color="primary" type="number" step="any" v-model.number="form.rate" :label="$t('Rate') + ' → ' + base" :disable="form.currency === base" /></div>

            <!-- Line items -->
            <div class="col-12">
              <div class="text-caption text-weight-bold text-grey-7 q-mb-xs">{{ $t('OrderLines') }}</div>
              <div v-for="(line, i) in form.items" :key="i" class="row q-col-gutter-xs items-center q-mb-xs po-line">
                <div class="col-12 col-sm-4"><q-select outlined dense color="primary" v-model="line.stock_item_id" :options="stockOptions" emit-value map-options clearable :label="$t('StockItem')" @update:model-value="fillLine(line)" /></div>
                <div class="col-6 col-sm-3"><q-input outlined dense color="primary" v-model="line.name" :label="$t('Name')" /></div>
                <div class="col-4 col-sm-2"><q-input outlined dense color="primary" type="number" step="any" v-model.number="line.quantity" :label="$t('Quantity')" /></div>
                <div class="col-4 col-sm-2"><q-input outlined dense color="primary" type="number" step="any" v-model.number="line.unit_price" :label="$t('UnitPrice')" /></div>
                <div class="col-4 col-sm-1 text-right"><q-btn dense flat round icon="close" color="negative" size="sm" @click="form.items.splice(i, 1)" :disable="form.items.length === 1" /></div>
              </div>
              <q-btn flat dense color="primary" icon="add" :label="$t('AddLine')" size="sm" @click="form.items.push(blankLine())" />
              <div class="text-right text-subtitle2 text-weight-bold q-mt-sm">{{ $t('Total') }}: {{ fmt(orderTotal) }} {{ form.currency }}</div>
            </div>
            <div class="col-12"><q-input outlined dense color="primary" v-model="form.notes" :label="$t('Notes')" /></div>
          </q-card-section>
          <q-separator />
          <n-submit :submitting="saving" :label="$t('Save')" />
        </q-form>
      </q-card>
    </m-modal>

    <!-- Show + Receive -->
    <m-modal :showCM="showDialog" @update:showCM="showDialog = $event" card_style="width: 640px">
      <q-card class="bg-white" v-if="active">
        <n-header icon="shopping_cart" :subtitle="active.supplier?.name">{{ active.code }}</n-header>
        <q-separator />
        <q-card-section>
          <div class="row items-center q-mb-sm">
            <q-chip dense size="sm" :color="statusColor(active.status)" text-color="white">{{ $t(statusKey(active.status)) }}</q-chip>
            <span class="text-caption text-grey-6 q-ml-sm">{{ active.order_date ? active.order_date.slice(0, 10) : '' }} · {{ active.project?.name || '—' }}</span>
            <q-space />
            <q-btn v-if="active.status !== 'received' && active.status !== 'cancelled' && $can('purchase-order-edit')"
              unelevated dense color="positive" icon="download_done" :label="$t('ReceiveIntoStock')" :loading="receiving" @click="receivePo" />
          </div>
          <q-markup-table flat bordered dense class="my_radio_less">
            <thead class="bg-theme-soft"><tr><th class="text-left">{{ $t('Name') }}</th><th class="text-right">{{ $t('Quantity') }}</th><th class="text-right">{{ $t('UnitPrice') }}</th><th class="text-right">{{ $t('Total') }}</th></tr></thead>
            <tbody>
              <tr v-for="it in active.items" :key="it.id">
                <td class="text-weight-medium">{{ it.name }}<q-icon v-if="it.stock_item_id" name="inventory" size="13px" color="teal-7" class="q-ml-xs"><q-tooltip>{{ $t('LinkedToStock') }}</q-tooltip></q-icon></td>
                <td class="text-right">{{ fmt(it.quantity) }} {{ it.unit }}</td>
                <td class="text-right">{{ fmt(it.unit_price) }}</td>
                <td class="text-right text-weight-bold">{{ fmt(it.line_total) }}</td>
              </tr>
            </tbody>
          </q-markup-table>
          <div class="text-caption text-grey-6 q-mt-xs" v-if="active.status !== 'received'">{{ $t('ReceiveHint') }}</div>

          <q-separator class="q-my-sm" />
          <attach-box type="purchase-order" :id="active.id" kind="document"
            :label="$t('Attachments')" icon="attach_file" accept="image/*,application/pdf" />
        </q-card-section>
        <q-separator />
        <q-card-actions align="right" class="q-pa-sm"><q-btn flat :label="$t('Close')" color="grey-7" @click="showDialog = false" /></q-card-actions>
      </q-card>
    </m-modal>
  </q-page>
</template>

<script setup>
import { ref, reactive, computed, getCurrentInstance, onMounted } from 'vue'
import { Notify } from 'quasar'
import { api } from '@/boot/axios'
import { useCurrency } from '@/composables/useCurrency'

const { proxy } = getCurrentInstance()
const { base, rates, loadRates, rateFor } = useCurrency()
const rows = ref([])
const loading = ref(false)
const saving = ref(false)
const dialog = ref(false)
const tableFilter = ref('')
const supplierOptions = ref([])
const projectOptions = ref([])
const stockOptions = ref([])
const stockMap = ref({})

const currencyOptions = computed(() => { const l = Object.keys(rates.value || {}); return l.length ? l : ['AFN', 'USD'] })
const statusOptions = [
  { label: 'Draft', value: 'draft' }, { label: 'Ordered', value: 'ordered' }, { label: 'Cancelled', value: 'cancelled' },
]
const today = () => new Date().toISOString().slice(0, 10)
const blankLine = () => ({ stock_item_id: null, name: '', quantity: null, unit: '', unit_price: null })
const blank = () => ({ id: null, supplier_id: null, project_id: null, status: 'draft', order_date: today(), currency: 'AFN', rate: 1, notes: '', items: [blankLine()] })
const form = reactive(blank())
const orderTotal = computed(() => form.items.reduce((s, l) => s + Number(l.quantity || 0) * Number(l.unit_price || 0), 0))

const columns = [
  { name: 'created_at', label: '#', field: 'id', align: 'left' },
  { name: 'code', label: 'Code', field: 'code', align: 'left', sortable: true },
  { name: 'supplier', label: 'Supplier', field: r => r.supplier?.name, align: 'left', sortable: true },
  { name: 'project', label: 'Project', field: r => r.project?.name || '—', align: 'left' },
  { name: 'order_date', label: 'Date', field: 'order_date', align: 'left', sortable: true },
  { name: 'total', label: 'Total', field: 'total', align: 'right', sortable: true },
  { name: 'status', label: 'Status', field: 'status', align: 'center' },
  { name: 'actions', label: 'Actions', field: 'actions', align: 'right' }
]
const exportColumns = columns.filter(c => c.name !== 'actions')

function fmt (v) { return Number(v || 0).toLocaleString('en-US') }
function statusColor (s) { return { draft: 'blue-grey-5', ordered: 'amber-8', received: 'positive', cancelled: 'negative' }[s] ?? 'grey' }
function statusKey (s) { return { draft: 'Draft', ordered: 'Ordered', received: 'Received', cancelled: 'Cancelled' }[s] ?? 'Draft' }
function fillLine (line) {
  const it = stockMap.value[line.stock_item_id]
  if (it) { line.name = it.name; line.unit = it.unit }
}

async function load () { loading.value = true; try { const { data } = await api.get('/purchase-orders'); rows.value = data } finally { loading.value = false } }
async function loadOptions () {
  try { const { data } = await api.get('/suppliers'); supplierOptions.value = (data || []).map(s => ({ label: s.name, value: s.id })) } catch (_) {}
  try { const { data } = await api.get('/projects'); projectOptions.value = (data || []).map(p => ({ label: p.name, value: p.id })) } catch (_) {}
  try {
    const { data } = await api.get('/stock-items')
    stockOptions.value = (data || []).map(i => ({ label: i.name + ' (' + fmt(i.quantity) + ' ' + i.unit + ')', value: i.id }))
    stockMap.value = Object.fromEntries((data || []).map(i => [i.id, i]))
  } catch (_) {}
}

function openCreate () { Object.assign(form, blank()); form.items = [blankLine()]; dialog.value = true }
async function openEdit (id) {
  try {
    const { data } = await api.get('/purchase-orders/' + id)
    if (data.status === 'received') return Notify.create({ type: 'warning', message: 'A received order can no longer be edited.' })
    Object.assign(form, {
      id: data.id, supplier_id: data.supplier_id, project_id: data.project_id, status: data.status,
      order_date: data.order_date ? data.order_date.slice(0, 10) : today(), currency: data.currency || 'AFN',
      rate: Number(data.rate || 1), notes: data.notes || '',
      items: (data.items || []).map(it => ({ stock_item_id: it.stock_item_id, name: it.name, quantity: Number(it.quantity), unit: it.unit, unit_price: Number(it.unit_price) })),
    })
    if (!form.items.length) form.items = [blankLine()]
    dialog.value = true
  } catch (_) {}
}
async function save () {
  saving.value = true
  try {
    const payload = { ...form, items: form.items.filter(l => (l.name || l.stock_item_id) && l.quantity > 0) }
    if (!payload.items.length) { saving.value = false; return Notify.create({ type: 'warning', message: 'Add at least one order line' }) }
    if (form.id) await api.put('/purchase-orders/' + form.id, payload)
    else await api.post('/purchase-orders', payload)
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: 'Saved' })
    dialog.value = false; load()
  } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Save failed' }) } finally { saving.value = false }
}
function remove (id) { proxy.$delete('purchase-orders/' + id, load) }

const showDialog = ref(false)
const active = ref(null)
const receiving = ref(false)
async function openShow (id) { try { const { data } = await api.get('/purchase-orders/' + id); active.value = data; showDialog.value = true } catch (_) {} }
async function receivePo () {
  receiving.value = true
  try {
    const { data } = await api.put('/purchase-orders/' + active.value.id + '/receive')
    active.value = { ...active.value, ...data }
    Notify.create({ type: 'positive', position: 'bottom', icon: 'inventory', message: 'Received into stock' })
    load(); loadOptions()
  } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Failed' }) } finally { receiving.value = false }
}

onMounted(() => { load(); loadOptions(); loadRates() })
</script>

<style scoped>
.po-link { color: var(--q-primary); font-weight: 600; cursor: pointer; text-decoration: none; }
.po-link:hover { text-decoration: underline; }
.po-line { background: #F8FAFC; border: 1px solid #EEF2F7; border-radius: 8px; margin: 0 0 4px; padding: 4px 6px; }
</style>
