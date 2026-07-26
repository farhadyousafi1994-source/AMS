<template>
  <q-page>
    <m-backgrounds>
      <div class="row my_radio_less q-pa-sm">
        <div class="col-12">
          <m-header icon="shopping_basket" controlRoomButton="false" class="q-mt-xs">
            {{ $t('PurchaseRequests') }}
          </m-header>
        </div>

        <!-- Status summary cards (dashboard animation comes from stat-card) -->
        <div class="col-12 q-mt-md">
          <div class="row q-col-gutter-md">
            <div class="col-6 col-md-3">
              <stat-card icon="hourglass_top" :label="$t('Pending')" :value="counts.pending"
                color="#D97706" tint="#FEF3C7" :sub="$t('AwaitingApproval')" sub-icon="schedule" />
            </div>
            <div class="col-6 col-md-3">
              <stat-card icon="task_alt" :label="$t('Approved')" :value="counts.approved"
                color="#2563EB" tint="#DBEAFE" :sub="$t('ReadyToBuy')" sub-icon="check" />
            </div>
            <div class="col-6 col-md-3">
              <stat-card icon="local_shipping" :label="$t('Purchased')" :value="counts.purchased"
                color="#0D9488" tint="#CCFBF1" :sub="$t('ReceiptUploaded')" sub-icon="receipt" />
            </div>
            <div class="col-6 col-md-3">
              <stat-card icon="verified" :label="$t('Closed')" :value="counts.closed"
                color="#16A34A" tint="#DCFCE7" :sub="$t('Reconciled')" sub-icon="done_all" />
            </div>
          </div>
        </div>

        <action-bar :rows="rows" :columns="exportColumns" filename="purchase-requests" create-perm="purchase-request-create" @add="openCreate" @update:filtered="() => {}">
          <template #filters>
            <div class="col-6 col-sm-3"><q-select outlined dense color="blue-grey-9" label-color="blue-grey-9" v-model="projectFilter" :options="projectOptions" emit-value map-options clearable :label="$t('Project')" @update:model-value="load" /></div>
            <div class="col-6 col-sm-3"><q-select outlined dense color="blue-grey-9" label-color="blue-grey-9" v-model="statusFilter" :options="statusOptions" emit-value map-options clearable :label="$t('Status')" @update:model-value="load" /></div>
          </template>
        </action-bar>
        <div class="col-12">
          <n-table config-key="page.purchaseRequests" :loading="loading" :data="rows" :columns="columns" v-model:filter="tableFilter"
            :can_show="'purchase-request-show'" :can_delete="'purchase-request-delete'" info-icon="visibility"
            :noInfoDialog="true" :noEdit="true" @info="openDetail" @del="remove">
            <template v-slot:body-cell-status="props">
              <q-td :props="props" class="text-center">
                <q-chip dense size="sm" :color="statusColor(props.row.status)" text-color="white">{{ $t(statusKey(props.row.status)) }}</q-chip>
              </q-td>
            </template>
            <template v-slot:body-cell-estimated_total="props">
              <q-td :props="props" class="text-right text-weight-medium">{{ fmt(props.row.estimated_total) }} {{ props.row.currency }}</q-td>
            </template>
            <template v-slot:body-cell-reconcile="props">
              <q-td :props="props" class="text-right">
                <span v-if="Number(props.row.spent_total) > 0" :class="Math.abs(Number(props.row.reconcile_diff)) > 0.009 ? 'text-orange-8 text-weight-medium' : 'text-positive'">
                  {{ fmt(props.row.spent_total) }} / {{ fmt(props.row.advanced_total) }}
                </span>
                <span v-else class="text-grey-5">—</span>
              </q-td>
            </template>
          </n-table>
        </div>
      </div>
    </m-backgrounds>

    <!-- Create request -->
    <m-modal :showCM="dialog" @update:showCM="dialog = $event" card_style="width: 640px">
      <q-card class="bg-white">
        <n-header icon="shopping_basket">{{ $t('NewPurchaseRequest') }}</n-header>
        <q-separator />
        <q-form @submit="save">
          <q-card-section class="row q-col-gutter-sm">
            <div class="col-12 col-sm-6"><q-select outlined dense color="primary" v-model="form.project_id" :options="projectOptions" emit-value map-options :label="$t('Project')" :rules="[v => !!v || $t('FieldIsRequired')]" /></div>
            <div class="col-12 col-sm-6"><q-select outlined dense color="primary" v-model="form.category_id" :options="categoryOptions" emit-value map-options clearable :label="$t('Category')" /></div>
            <div class="col-12"><n-name :name="form.title" @update:name="form.title = $event" icon="title" :label="$t('Title')" :rules="[]" /></div>

            <div class="col-12">
              <div class="text-caption text-grey-7 q-mb-xs">{{ $t('Items') }}</div>
              <div v-for="(it, i) in form.items" :key="i" class="row q-col-gutter-xs items-center q-mb-xs">
                <div class="col-5"><q-input outlined dense color="primary" v-model="it.name" :placeholder="$t('ItemName')" /></div>
                <div class="col-2"><q-input outlined dense color="primary" type="number" step="any" v-model.number="it.qty" :placeholder="$t('Qty')" @update:model-value="recalc" /></div>
                <div class="col-2"><q-input outlined dense color="primary" v-model="it.unit" :placeholder="$t('Unit')" /></div>
                <div class="col-2"><q-input outlined dense color="primary" type="number" step="any" v-model.number="it.est_price" :placeholder="$t('Price')" @update:model-value="recalc" /></div>
                <div class="col-1 text-center"><q-btn size="sm" dense flat round icon="close" color="negative" @click="form.items.splice(i, 1); recalc()" /></div>
              </div>
              <q-btn dense flat color="primary" icon="add" :label="$t('AddItem')" @click="form.items.push({ name: '', qty: 1, unit: '', est_price: 0 })" />
            </div>

            <div class="col-6 col-sm-4"><q-input outlined dense color="primary" type="number" step="any" v-model.number="form.estimated_total" :label="$t('EstimatedTotal')" /></div>
            <div class="col-6 col-sm-2"><q-select outlined dense color="primary" v-model="form.currency" :options="['AFN', 'USD']" :label="$t('Currency')" /></div>
            <div class="col-12"><q-input outlined dense color="primary" type="textarea" autogrow v-model="form.note" :label="$t('Notes')" /></div>
            <div class="col-12">
              <q-file outlined dense color="primary" v-model="billFiles" multiple :label="$t('AttachBill')"
                accept=".jpg,.jpeg,.png,.webp,.pdf" max-file-size="41943040" clearable counter>
                <template #prepend><q-icon name="receipt_long" color="primary" /></template>
              </q-file>
              <div class="text-caption text-grey-6 q-mt-xs">{{ $t('AttachBillHint') }}</div>
            </div>
          </q-card-section>
          <q-separator />
          <n-submit :submitting="saving" :label="$t('Save')" />
        </q-form>
      </q-card>
    </m-modal>

    <!-- Detail + workflow -->
    <m-modal :showCM="detailDialog" @update:showCM="detailDialog = $event" card_style="width: 720px">
      <q-card class="bg-white" v-if="active">
        <n-header icon="receipt_long" :subtitle="active.code">{{ active.title || $t('PurchaseRequest') }}</n-header>
        <q-separator />
        <q-card-section class="q-pb-none">
          <div class="row q-col-gutter-sm">
            <div class="col-12 col-sm-4"><stat-card dense icon="domain" :label="$t('Project')" :value="active.project?.name || '—'" color="#175A8C" tint="#E0EDF7" /></div>
            <div class="col-6 col-sm-4"><stat-card dense icon="payments" :label="$t('CashAdvanced')" :value="fmt(active.advanced_total)" :suffix="active.currency" color="#2563EB" tint="#DBEAFE" /></div>
            <div class="col-6 col-sm-4">
              <stat-card dense icon="fact_check" :label="$t('ActualSpent')" :value="fmt(active.spent_total)" :suffix="active.currency"
                :color="Math.abs(Number(active.reconcile_diff)) > 0.009 ? '#D97706' : '#16A34A'"
                :tint="Math.abs(Number(active.reconcile_diff)) > 0.009 ? '#FEF3C7' : '#DCFCE7'" />
            </div>
          </div>
          <div v-if="Number(active.spent_total) > 0 && Math.abs(Number(active.reconcile_diff)) > 0.009" class="recon-flag q-mt-sm">
            <q-icon name="warning" size="15px" class="q-mr-xs" />
            {{ $t('ReconcileDiff') }}: {{ fmt(active.reconcile_diff) }} {{ active.currency }}
            <span class="text-caption">({{ Number(active.reconcile_diff) > 0 ? $t('CashLeftover') : $t('Overspent') }})</span>
          </div>
        </q-card-section>

        <q-card-section>
          <div class="text-caption text-grey-7">{{ $t('Items') }}</div>
          <q-markup-table flat bordered dense class="my_radio_less q-mb-sm">
            <thead class="bg-theme-soft"><tr><th class="text-left">{{ $t('ItemName') }}</th><th class="text-right">{{ $t('Qty') }}</th><th class="text-left">{{ $t('Unit') }}</th><th class="text-right">{{ $t('Price') }}</th></tr></thead>
            <tbody>
              <tr v-if="!active.items || active.items.length === 0"><td colspan="4" class="text-center text-grey-5">—</td></tr>
              <tr v-for="(it, i) in (active.items || [])" :key="i"><td>{{ it.name }}</td><td class="text-right">{{ it.qty }}</td><td>{{ it.unit }}</td><td class="text-right">{{ fmt(it.est_price) }}</td></tr>
            </tbody>
          </q-markup-table>

          <div v-if="active.invoices?.length" class="text-caption text-grey-7">{{ $t('Receipts') }}</div>
          <div v-for="inv in (active.invoices || [])" :key="inv.id" class="row items-center q-gutter-x-sm q-py-xs">
            <q-btn size="sm" dense flat round icon="image" color="indigo-7" @click="viewImage(inv)" />
            <span class="text-weight-medium">{{ fmt(inv.actual_total) }} {{ inv.currency }}</span>
            <span class="text-caption text-grey-7">{{ inv.vendor || '—' }} · {{ (inv.invoice_date || '').slice(0, 10) }}</span>
          </div>

          <q-separator class="q-my-sm" />
          <attach-box type="purchase-request" :id="active.id" kind="receipt"
            :label="$t('ReceiptBill')" icon="receipt_long" accept="image/*,application/pdf" />
        </q-card-section>

        <q-separator />
        <q-card-actions align="right" class="q-pa-sm">
          <template v-if="active.status === 'pending' && $can('purchase-approve')">
            <q-btn unelevated dense color="negative" icon="close" :label="$t('Reject')" @click="decide('rejected')" />
            <q-btn unelevated dense color="positive" icon="check" :label="$t('Approve')" @click="decide('approved')" />
          </template>
          <q-btn v-if="['approved', 'purchased'].includes(active.status) && $can('cash-release')" outline dense color="blue-8" icon="payments" :label="$t('ReleaseAdvance')" @click="advanceDialog = true" />
          <q-btn v-if="['approved', 'purchased'].includes(active.status) && $can('purchase-request-create')" unelevated dense color="teal-7" icon="upload" :label="$t('UploadReceipt')" @click="receiptDialog = true" />
          <q-btn v-if="active.status === 'purchased' && active.invoices?.length && $can('purchase-request-show')" unelevated dense color="green-8" icon="verified" :label="$t('CloseRequest')" @click="closeRequest" />
          <q-btn flat :label="$t('Close')" color="grey-7" @click="detailDialog = false" />
        </q-card-actions>
      </q-card>
    </m-modal>

    <!-- Release advance -->
    <m-modal :showCM="advanceDialog" @update:showCM="advanceDialog = $event" card_style="width: 420px">
      <q-card class="bg-white" v-if="active">
        <n-header icon="payments" :subtitle="active.code">{{ $t('ReleaseAdvance') }}</n-header>
        <q-separator />
        <q-form @submit="saveAdvance">
          <q-card-section class="row q-col-gutter-sm">
            <div class="col-12"><q-input outlined dense color="primary" type="number" step="any" v-model.number="advanceForm.amount_given" :label="$t('AmountGiven') + ' (' + active.currency + ')'" :rules="[v => v > 0 || $t('FieldIsRequired')]" /></div>
            <div class="col-12"><q-input outlined dense color="primary" v-model="advanceForm.note" :label="$t('Notes')" /></div>
          </q-card-section>
          <q-separator />
          <n-submit :submitting="savingAdvance" :label="$t('Save')" />
        </q-form>
      </q-card>
    </m-modal>

    <!-- Upload receipt -->
    <m-modal :showCM="receiptDialog" @update:showCM="receiptDialog = $event" card_style="width: 480px">
      <q-card class="bg-white" v-if="active">
        <n-header icon="upload" :subtitle="active.code">{{ $t('UploadReceipt') }}</n-header>
        <q-separator />
        <q-form @submit="saveReceipt">
          <q-card-section class="row q-col-gutter-sm">
            <div class="col-12 col-sm-7"><n-name :name="receiptForm.vendor" @update:name="receiptForm.vendor = $event" icon="storefront" :label="$t('Vendor')" :rules="[]" /></div>
            <div class="col-12 col-sm-5"><q-input outlined dense color="primary" type="number" step="any" v-model.number="receiptForm.actual_total" :label="$t('ActualTotal')" :rules="[v => v >= 0 || $t('FieldIsRequired')]" /></div>
            <div class="col-12 col-sm-7"><q-select outlined dense color="primary" v-model="receiptForm.category_id" :options="categoryOptions" emit-value map-options clearable :label="$t('Category')" /></div>
            <div class="col-12 col-sm-5"><shamsi-date v-model="receiptForm.invoice_date" color="primary" :label="$t('Date')" /></div>
            <div class="col-12">
              <q-file outlined dense color="primary" v-model="receiptFile" :label="$t('ReceiptPhoto')" accept=".jpg,.jpeg,.png,.webp,.pdf" max-file-size="41943040" clearable>
                <template #prepend><q-icon name="photo_camera" color="primary" /></template>
              </q-file>
              <div class="text-caption text-grey-6 q-mt-xs">{{ $t('PhotoCompressedHint') }}</div>
            </div>
          </q-card-section>
          <q-separator />
          <n-submit :submitting="savingReceipt" :label="$t('Save')" />
        </q-form>
      </q-card>
    </m-modal>

    <!-- Image preview -->
    <q-dialog v-model="imgDialog">
      <q-card class="bg-white" style="width:560px;max-width:95vw" v-if="imgInv">
        <n-header icon="image" :subtitle="imgInv.vendor">{{ $t('Receipt') }}</n-header>
        <q-separator />
        <q-card-section class="text-center" style="max-height:65vh;overflow:auto">
          <img v-if="imgUrl" :src="imgUrl" style="max-width:100%;border-radius:8px" />
          <q-spinner v-else color="primary" size="2em" />
        </q-card-section>
        <q-card-actions align="right" class="q-pa-sm"><q-btn flat :label="$t('Close')" color="grey-7" @click="imgDialog = false" /></q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { ref, reactive, computed, getCurrentInstance, onMounted } from 'vue'
import { Notify } from 'quasar'
import { offlineApi as api } from '@/services/offlineApi'
import { compressImage } from '@/utils/image'

const { proxy } = getCurrentInstance()

const rows = ref([])
const loading = ref(false)
const saving = ref(false)
const dialog = ref(false)
const tableFilter = ref('')
const projectFilter = ref(null)
const statusFilter = ref(null)
const projectOptions = ref([])
const categoryOptions = ref([])

const statusOptions = [
  { label: 'Pending', value: 'pending' }, { label: 'Approved', value: 'approved' },
  { label: 'Rejected', value: 'rejected' }, { label: 'Purchased', value: 'purchased' }, { label: 'Closed', value: 'closed' },
]

const columns = [
  { name: 'created_at', label: '#', field: 'id', align: 'left' },
  { name: 'code', label: 'Code', field: 'code', align: 'left', sortable: true },
  { name: 'title', label: 'Title', field: 'title', align: 'left' },
  { name: 'project', label: 'Project', field: r => r.project?.name, align: 'left' },
  { name: 'status', label: 'Status', field: 'status', align: 'center', sortable: true },
  { name: 'estimated_total', label: 'EstimatedTotal', field: 'estimated_total', align: 'right', sortable: true },
  { name: 'reconcile', label: 'SpentVsAdvanced', field: 'spent_total', align: 'right' },
  { name: 'actions', label: 'Actions', field: 'actions', align: 'right' },
]
const exportColumns = columns.filter(c => c.name !== 'actions' && c.name !== 'reconcile')

const counts = computed(() => ({
  pending: rows.value.filter(r => r.status === 'pending').length,
  approved: rows.value.filter(r => r.status === 'approved').length,
  purchased: rows.value.filter(r => r.status === 'purchased').length,
  closed: rows.value.filter(r => r.status === 'closed').length,
}))

function fmt (v) { return Number(v || 0).toLocaleString('en-US') }
function statusColor (s) { return { pending: 'amber-8', approved: 'blue-7', rejected: 'red-7', purchased: 'teal-7', closed: 'green-8' }[s] ?? 'grey' }
function statusKey (s) { return { pending: 'Pending', approved: 'Approved', rejected: 'Rejected', purchased: 'Purchased', closed: 'Closed' }[s] ?? 'Pending' }

const blank = () => ({ project_id: null, category_id: null, title: '', items: [{ name: '', qty: 1, unit: '', est_price: 0 }], estimated_total: 0, currency: 'AFN', note: '' })
const form = reactive(blank())
function recalc () { form.estimated_total = form.items.reduce((s, it) => s + Number(it.qty || 0) * Number(it.est_price || 0), 0) }

async function load () {
  loading.value = true
  try {
    const params = {}
    if (projectFilter.value) params.project_id = projectFilter.value
    if (statusFilter.value) params.status = statusFilter.value
    const { data } = await api.get('/purchase-requests', { params })
    rows.value = Array.isArray(data) ? data : []
  } finally { loading.value = false }
}
async function loadProjects () { try { const { data } = await api.get('/projects'); projectOptions.value = (data || []).map(p => ({ label: p.name, value: p.id })) } catch (_) {} }
async function loadCategories () { try { const { data } = await api.get('/purchase-categories'); categoryOptions.value = (data || []).map(c => ({ label: c.name, value: c.id })) } catch (_) {} }

const billFiles = ref(null)
function openCreate () { Object.assign(form, blank()); billFiles.value = null; dialog.value = true }
async function save () {
  saving.value = true
  try {
    const { data } = await api.post('/purchase-requests', { ...form })
    // Bill photos picked in the form are filed on the new request (kind
    // "receipt" so they show alongside uploaded receipts in the detail view).
    const files = Array.isArray(billFiles.value) ? billFiles.value : (billFiles.value ? [billFiles.value] : [])
    for (const raw of files) {
      try {
        const fd = new FormData()
        fd.append('type', 'purchase-request')
        fd.append('id', data.id)
        fd.append('kind', 'receipt')
        fd.append('file', await compressImage(raw))
        await api.post('/attachments', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
      } catch (_) { Notify.create({ type: 'warning', message: 'Bill upload failed: ' + (raw.name || '') }) }
    }
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: 'Saved' })
    dialog.value = false; billFiles.value = null; load()
  } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Save failed' }) } finally { saving.value = false }
}
function remove (id) { proxy.$delete('purchase-requests/' + id, load) }

// ── Detail + workflow ──
const detailDialog = ref(false)
const active = ref(null)
async function openDetail (id) { try { const { data } = await api.get('/purchase-requests/' + id); active.value = data; detailDialog.value = true } catch (_) {} }
async function refresh () { if (active.value) { try { const { data } = await api.get('/purchase-requests/' + active.value.id); active.value = data } catch (_) {} } load() }

async function decide (decision) {
  try {
    await api.put('/purchase-requests/' + active.value.id + '/decide', { decision })
    Notify.create({ type: 'positive', position: 'bottom', message: decision === 'approved' ? 'Approved' : 'Rejected' })
    refresh()
  } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Failed' }) }
}

const advanceDialog = ref(false)
const savingAdvance = ref(false)
const advanceForm = reactive({ amount_given: null, note: '' })
async function saveAdvance () {
  savingAdvance.value = true
  try {
    await api.post('/purchase-requests/' + active.value.id + '/advance', { ...advanceForm })
    Notify.create({ type: 'positive', position: 'bottom', message: 'Saved' })
    advanceDialog.value = false; advanceForm.amount_given = null; advanceForm.note = ''; refresh()
  } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Failed' }) } finally { savingAdvance.value = false }
}

const receiptDialog = ref(false)
const savingReceipt = ref(false)
const receiptFile = ref(null)
const receiptForm = reactive({ vendor: '', actual_total: null, category_id: null, invoice_date: new Date().toISOString().slice(0, 10) })
async function saveReceipt () {
  if (!receiptFile.value) { Notify.create({ type: 'warning', message: 'A receipt photo is required' }); return }
  savingReceipt.value = true
  try {
    const fd = new FormData()
    Object.entries(receiptForm).forEach(([k, v]) => { if (v !== null && v !== '') fd.append(k, v) })
    fd.append('image', await compressImage(receiptFile.value))
    await api.post('/purchase-requests/' + active.value.id + '/receipt', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: 'Saved' })
    receiptDialog.value = false; receiptFile.value = null
    Object.assign(receiptForm, { vendor: '', actual_total: null, category_id: null, invoice_date: new Date().toISOString().slice(0, 10) })
    refresh()
  } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Upload failed' }) } finally { savingReceipt.value = false }
}

async function closeRequest () {
  try {
    await api.put('/purchase-requests/' + active.value.id + '/close')
    Notify.create({ type: 'positive', position: 'bottom', icon: 'verified', message: 'Closed' })
    refresh()
  } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Failed' }) }
}

// ── Image preview ──
const imgDialog = ref(false)
const imgInv = ref(null)
const imgUrl = ref(null)
async function viewImage (inv) {
  imgInv.value = inv; imgUrl.value = null; imgDialog.value = true
  try { const res = await api.get('/site-invoices/' + inv.id + '/image', { responseType: 'blob' }); imgUrl.value = URL.createObjectURL(new Blob([res.data], { type: inv.image_mime })) } catch (_) {}
}

onMounted(() => { load(); loadProjects(); loadCategories() })
</script>

<style scoped>
.recon-flag {
  display: flex; align-items: center; gap: 4px;
  font-size: 12.5px; font-weight: 600; color: #B45309;
  background: #FEF3C7; border: 1px dashed #F59E0B; border-radius: 8px; padding: 7px 10px;
}
</style>
