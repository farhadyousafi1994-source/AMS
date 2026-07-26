<template>
  <q-page>
    <m-backgrounds>
      <div class="row my_radio_less q-pa-sm">
        <div class="col-12">
          <m-header icon="construction" controlRoomButton="false" class="q-mt-xs">
            {{ $t('Assets') }}
          </m-header>
        </div>

        <!-- Cross-branch transfer requests -->
        <div class="col-12 q-mt-sm" v-if="transfers.length">
          <q-card flat bordered class="my_radio_less">
            <q-expansion-item default-opened dense-toggle icon="swap_horiz" :label="$t('AssetTransfers')" :caption="pendingIncoming + ' ' + $t('PendingApproval')">
              <q-separator />
              <q-list separator>
                <q-item v-for="t in transfers" :key="t.id">
                  <q-item-section avatar><q-icon :name="t.direction === 'incoming' ? 'call_received' : 'call_made'" :color="t.direction === 'incoming' ? 'teal-7' : 'blue-7'" /></q-item-section>
                  <q-item-section>
                    <q-item-label><b>{{ t.quantity }}×</b> {{ t.asset?.name }}</q-item-label>
                    <q-item-label caption>{{ t.from_branch?.name }} → {{ t.to_branch?.name }} <span v-if="t.reason">· {{ t.reason }}</span></q-item-label>
                  </q-item-section>
                  <q-item-section side>
                    <div class="row items-center q-gutter-xs">
                      <template v-if="t.status === 'pending' && t.direction === 'incoming' && $can('asset-edit')">
                        <q-btn size="sm" dense unelevated color="positive" icon="check" :label="$t('Approve')" @click="decideTransfer(t, 'approved')" />
                        <q-btn size="sm" dense flat color="negative" icon="close" @click="decideTransfer(t, 'rejected')" />
                      </template>
                      <q-chip v-else dense size="sm" :color="tStatusColor(t.status)" text-color="white">{{ $t(tStatusKey(t.status)) }}</q-chip>
                    </div>
                  </q-item-section>
                </q-item>
              </q-list>
            </q-expansion-item>
          </q-card>
        </div>

        <action-bar
          :rows="rows"
          :columns="exportColumns"
          filename="assets"
          create-perm="asset-create"
          @add="openCreate"
          @update:filtered="() => {}"
        >
          <template #filters>
            <div class="col-6 col-sm-3"><q-select outlined dense color="blue-grey-9" label-color="blue-grey-9" v-model="filters.category" :options="categoryOptions" emit-value map-options clearable :label="$t('Category')" @update:model-value="load" /></div>
            <div class="col-6 col-sm-3"><q-select outlined dense color="blue-grey-9" label-color="blue-grey-9" v-model="filters.status" :options="statusOptions" emit-value map-options clearable :label="$t('Status')" @update:model-value="load" /></div>
            <div class="col-6 col-sm-3"><q-select outlined dense color="blue-grey-9" label-color="blue-grey-9" v-model="filters.tracking" :options="trackingOptions" emit-value map-options clearable :label="$t('TrackingMode')" @update:model-value="load" /></div>
          </template>
        </action-bar>
        <div class="col-12">
          <n-table config-key="page.assets"
            :loading="loading"
            :data="rows"
            :columns="columns"
            v-model:filter="tableFilter"
            :can_edit="'asset-edit'"
            :can_delete="'asset-delete'"
            :noInfo="true"
            @edit="openEdit"
            @del="remove"
          >
            <template v-slot:body-cell-name="props">
              <q-td :props="props">
                <q-icon v-if="props.row.locked" name="lock" size="14px" color="grey-6" class="q-mr-xs"><q-tooltip>{{ $t('LockedOtherBranch') }}</q-tooltip></q-icon>
                <a class="asset-link" :class="{ 'text-grey-6': props.row.locked }" @click.prevent="openShow(props.row.id)">{{ props.row.name }}</a>
                <div v-if="props.row.serial" class="text-caption text-grey-6">{{ props.row.serial }}</div>
              </q-td>
            </template>
            <template v-slot:body-cell-branch="props">
              <q-td :props="props">
                <q-chip dense size="sm" :color="props.row.locked ? 'blue-grey-2' : 'teal-2'" :text-color="props.row.locked ? 'blue-grey-8' : 'teal-9'">
                  <q-icon :name="props.row.locked ? 'lock' : 'store'" size="12px" class="q-mr-xs" />{{ props.row.branch_name || '—' }}
                </q-chip>
              </q-td>
            </template>
            <template v-slot:body-cell-actions="props">
              <q-td :props="props" class="text-right" style="white-space:nowrap">
                <q-btn v-if="props.row.locked" size="sm" dense unelevated color="primary" icon="swap_horiz" :label="$t('Request')" @click="openTransfer(props.row)" />
                <template v-else>
                  <q-btn v-if="$can('asset-edit')" size="sm" dense flat round icon="edit" color="primary" @click="openEdit(props.row.id)" />
                  <q-btn v-if="$can('asset-delete')" size="sm" dense flat round icon="delete" color="negative" @click="remove(props.row.id)" />
                </template>
              </q-td>
            </template>
            <template v-slot:body-cell-category="props">
              <q-td :props="props">
                <q-chip dense size="sm" :color="catColor(props.row.category)" text-color="white" :icon="catIcon(props.row.category)">
                  {{ $t(catKey(props.row.category)) }}
                </q-chip>
              </q-td>
            </template>
            <template v-slot:body-cell-tracking="props">
              <q-td :props="props">{{ props.row.tracking === 'unit' ? $t('PerUnit') : $t('ByCount') }}</q-td>
            </template>
            <template v-slot:body-cell-available="props">
              <q-td :props="props" class="text-center">
                <span class="text-weight-bold" :class="props.row.available > 0 ? 'text-positive' : 'text-negative'">{{ props.row.available }}</span>
                <span class="text-grey-6"> / {{ props.row.quantity_total }}</span>
              </q-td>
            </template>
            <template v-slot:body-cell-allocated="props">
              <q-td :props="props" class="text-center text-orange-8">{{ props.row.allocated }}</q-td>
            </template>
            <template v-slot:body-cell-status="props">
              <q-td :props="props">
                <q-chip dense size="sm" :color="statusColor(props.row.status)" text-color="white">{{ $t(statusKey(props.row.status)) }}</q-chip>
              </q-td>
            </template>
          </n-table>
        </div>
      </div>
    </m-backgrounds>

    <!-- Add / Edit modal -->
    <m-modal :showCM="dialog" @update:showCM="dialog = $event" card_style="width: 620px">
      <q-card class="bg-white">
        <n-header icon="construction">{{ form.id ? $t('Edit') : $t('AddNew') }} — {{ $t('Asset') }}</n-header>
        <q-separator />
        <q-form @submit="save">
          <q-card-section class="row q-col-gutter-sm">
            <div class="col-12 col-sm-6"><n-name :name="form.name" @update:name="form.name = $event" icon="construction" :label="$t('AssetName')" autofocus /></div>
            <div class="col-12 col-sm-6">
              <q-select outlined dense color="primary" v-model="form.category" :options="categoryOptions" emit-value map-options :label="$t('Category')">
                <template #prepend><q-icon name="category" color="primary" /></template>
              </q-select>
            </div>

            <div class="col-12">
              <div class="text-caption text-grey-7 q-mb-xs">{{ $t('TrackingMode') }}</div>
              <q-btn-toggle v-model="form.tracking" spread no-caps unelevated
                toggle-color="primary" color="grey-3" text-color="grey-9"
                :options="[{label:$t('PerUnit'),value:'unit'},{label:$t('ByCount'),value:'count'}]" />
            </div>

            <!-- by-count fields -->
            <template v-if="form.tracking === 'count'">
              <div class="col-6 col-sm-4">
                <q-input outlined dense color="primary" type="number" min="1" v-model.number="form.quantity_total" :label="$t('TotalQty')" :rules="[v => v >= 1 || $t('FieldIsRequired')]" hide-bottom-space />
              </div>
              <div class="col-6 col-sm-4">
                <q-input outlined dense color="primary" type="number" min="0" v-model.number="form.allocated" :label="$t('Allocated')" />
              </div>
              <div class="col-6 col-sm-4">
                <q-select outlined dense color="primary" v-model="form.unit" :options="unitOptions" emit-value map-options clearable :label="$t('Unit')" />
              </div>
            </template>

            <!-- per-unit fields -->
            <template v-else>
              <div class="col-12 col-sm-6"><n-name :name="form.serial" @update:name="form.serial = $event" icon="tag" :label="$t('Serial')" :rules="[]" /></div>
              <div class="col-12 col-sm-6">
                <q-select outlined dense color="primary" v-model="form.condition" :options="conditionOptions" emit-value map-options clearable :label="$t('Condition')" />
              </div>
              <div class="col-6 col-sm-4">
                <q-input outlined dense color="primary" type="number" min="0" v-model.number="form.allocated" :label="$t('Allocated')" />
              </div>
            </template>

            <div class="col-6 col-sm-4">
              <q-select outlined dense color="primary" v-model="form.status" :options="statusOptions" emit-value map-options :label="$t('Status')" />
            </div>
            <div class="col-12 col-sm-4"><n-name :name="form.location" @update:name="form.location = $event" icon="place" :label="$t('Location')" :rules="[]" /></div>
            <div class="col-6 col-sm-4">
              <shamsi-date v-model="form.purchase_date" color="primary" :label="$t('PurchaseDate')" clearable />
            </div>
            <div class="col-12 col-sm-8">
              <money-input v-model="form.purchase_value" v-model:currency="form.currency" v-model:rate="form.rate" :allow-save-rate="false" :label="$t('PurchaseValue')" />
            </div>
            <div class="col-12"><q-input outlined dense color="primary" type="textarea" autogrow v-model="form.notes" :label="$t('Description')" /></div>
          </q-card-section>
          <q-separator />
          <n-submit :submitting="saving" :label="$t('Save')" />
        </q-form>
      </q-card>
    </m-modal>

    <!-- Show modal (with maintenance for per-unit) -->
    <m-modal :showCM="showDialog" @update:showCM="showDialog = $event" card_style="width: 640px">
      <q-card class="bg-white" v-if="activeAsset">
        <n-header icon="visibility" :subtitle="activeAsset.code">{{ activeAsset.name }}</n-header>
        <q-separator />
        <q-card-section>
          <div class="row q-col-gutter-md">
            <div class="col-6 col-sm-3" v-for="f in showFacts" :key="f.label">
              <div class="text-caption text-grey-6">{{ $t(f.label) }}</div>
              <div class="text-subtitle2 text-weight-bold">{{ f.value }}</div>
            </div>
          </div>
          <div v-if="activeAsset.notes" class="q-mt-sm text-body2 text-grey-8">{{ activeAsset.notes }}</div>
        </q-card-section>

        <template v-if="activeAsset.tracking === 'unit'">
          <q-separator />
          <q-card-section>
            <div class="row items-center justify-between q-mb-xs">
              <div class="text-subtitle2">{{ $t('MaintenanceLog') }}</div>
              <q-btn v-if="$can('asset-edit')" size="sm" flat dense color="teal-7" icon="add" :label="$t('AddNew')" @click="showMaintForm = !showMaintForm" />
            </div>
            <q-form v-if="showMaintForm && $can('asset-edit')" @submit="addMaint" class="row q-col-gutter-sm items-end q-mb-sm">
              <div class="col-6 col-sm-3">
                <shamsi-date v-model="maintForm.log_date" color="primary" :label="$t('LogDate')" />
              </div>
              <div class="col-6 col-sm-3"><n-name :name="maintForm.work_type" @update:name="maintForm.work_type = $event" icon="build" :label="$t('WorkType')" :rules="[]" /></div>
              <div class="col-6 col-sm-3"><q-input outlined dense color="primary" type="number" step="0.01" v-model.number="maintForm.cost" :label="$t('Cost')" /></div>
              <div class="col-6 col-sm-2"><q-input outlined dense color="primary" v-model="maintForm.description" :label="$t('Description')" /></div>
              <div class="col-12 col-sm-1"><q-btn unelevated color="teal-7" icon="add" type="submit" :loading="savingMaint" round dense /></div>
            </q-form>
            <q-markup-table flat bordered dense class="my_radio_less">
              <thead class="bg-theme-soft">
                <tr><th class="text-left">{{ $t('LogDate') }}</th><th class="text-left">{{ $t('WorkType') }}</th><th class="text-right">{{ $t('Cost') }}</th><th class="text-left">{{ $t('Description') }}</th><th></th></tr>
              </thead>
              <tbody>
                <tr v-if="!activeAsset.maintenance_logs || activeAsset.maintenance_logs.length === 0"><td colspan="5" class="text-center text-grey-5 q-py-md">{{ $t('NoRecordFound') }}</td></tr>
                <tr v-for="m in activeAsset.maintenance_logs" :key="m.id">
                  <td style="white-space:nowrap">{{ m.log_date ? m.log_date.slice(0,10) : '—' }}</td>
                  <td>{{ m.work_type || '—' }}</td>
                  <td class="text-right">{{ fmt(m.cost) }} {{ m.currency }}</td>
                  <td class="text-caption">{{ m.description || '—' }}</td>
                  <td class="text-right"><q-btn v-if="$can('asset-edit')" size="sm" dense flat round icon="delete" color="negative" @click="delMaint(m)" /></td>
                </tr>
              </tbody>
            </q-markup-table>
          </q-card-section>
        </template>

        <q-separator />
        <q-card-actions align="right" class="q-pa-sm"><q-btn flat :label="$t('Close')" color="grey-7" @click="showDialog = false" /></q-card-actions>
      </q-card>
    </m-modal>

    <!-- Request transfer from another branch -->
    <m-modal :showCM="transferDialog" @update:showCM="transferDialog = $event" card_style="width: 440px">
      <q-card class="bg-white" v-if="transferAsset">
        <n-header icon="swap_horiz" :subtitle="transferAsset.branch_name">{{ $t('RequestTransfer') }}</n-header>
        <q-separator />
        <q-form @submit="submitTransfer">
          <q-card-section>
            <div class="transfer-info q-mb-sm">
              <div class="text-weight-bold">{{ transferAsset.name }}</div>
              <div class="text-caption text-grey-7">{{ $t('Available') }}: {{ transferAsset.available }} / {{ transferAsset.quantity_total }} @ {{ transferAsset.branch_name }}</div>
            </div>
            <q-input outlined dense color="primary" type="number" min="1" :max="transferAsset.available" v-model.number="transferForm.quantity" :label="$t('Quantity')" :rules="[v => (v >= 1 && v <= transferAsset.available) || $t('FieldIsRequired')]" hide-bottom-space />
            <q-input outlined dense color="primary" class="q-mt-sm" type="textarea" autogrow v-model="transferForm.reason" :label="$t('Reason')" />
          </q-card-section>
          <q-separator />
          <n-submit :submitting="submittingTransfer" :label="$t('SendRequest')" />
        </q-form>
      </q-card>
    </m-modal>
  </q-page>
</template>

<script setup>
import { ref, reactive, computed, getCurrentInstance, onMounted } from 'vue'
import { Notify } from 'quasar'
import { api } from '@/boot/axios'
import { useLookups } from '@/composables/useLookups'

const { proxy } = getCurrentInstance()
const { loadLookups, options: lookupOptions } = useLookups()

const rows = ref([])
const loading = ref(false)
const saving = ref(false)
const dialog = ref(false)
const tableFilter = ref('')
const filters = reactive({ category: null, status: null, tracking: null })

// Selects are served from the Options Registry; hardcoded lists remain only
// as a fallback until the lookups have loaded.
const FALLBACK_CATEGORIES = [
  { label: 'Heavy Equipment', value: 'heavy_equipment' },
  { label: 'Vehicles', value: 'vehicle' },
  { label: 'Tools', value: 'tool' },
  { label: 'Equipment', value: 'equipment' },
]
const categoryOptions = computed(() => lookupOptions('asset_category').length ? lookupOptions('asset_category') : FALLBACK_CATEGORIES)
const statusOptions = [
  { label: 'Available', value: 'available' },
  { label: 'In Use', value: 'in_use' },
  { label: 'Maintenance', value: 'maintenance' },
  { label: 'Retired', value: 'retired' },
]
const trackingOptions = [
  { label: 'Per Unit', value: 'unit' },
  { label: 'By Count', value: 'count' },
]
const unitOptions = computed(() => lookupOptions('unit').length ? lookupOptions('unit') : [{ label: 'Piece', value: 'piece' }, { label: 'Set', value: 'set' }])
const conditionOptions = computed(() => lookupOptions('asset_condition').length ? lookupOptions('asset_condition') : [
  { label: 'New', value: 'new' },
  { label: 'Good', value: 'good' },
  { label: 'Fair', value: 'fair' },
  { label: 'Needs Repair', value: 'needs_repair' },
])

const blank = () => ({ id: null, name: '', category: 'heavy_equipment', tracking: 'count', quantity_total: 1, allocated: 0, unit: 'piece', status: 'available', location: '', serial: '', condition: 'good', purchase_date: '', purchase_value: null, currency: 'AFN', rate: 1, notes: '' })
const form = reactive(blank())

const columns = [
  { name: 'created_at', label: '#', field: 'id', align: 'left' },
  { name: 'code', label: 'Code', field: 'code', align: 'left', sortable: true },
  { name: 'name', label: 'AssetName', field: 'name', align: 'left', sortable: true },
  { name: 'category', label: 'Category', field: 'category', align: 'left' },
  { name: 'tracking', label: 'TrackingMode', field: 'tracking', align: 'left' },
  { name: 'available', label: 'Available', field: 'available', align: 'center', sortable: true },
  { name: 'allocated', label: 'Allocated', field: 'allocated', align: 'center' },
  { name: 'branch', label: 'Branch', field: 'branch_name', align: 'left' },
  { name: 'status', label: 'Status', field: 'status', align: 'center' },
  { name: 'location', label: 'Location', field: 'location', align: 'left' },
  { name: 'actions', label: 'Actions', field: 'actions', align: 'right' }
]
const exportColumns = columns.filter(c => c.name !== 'actions')

function fmt (v) { return Number(v || 0).toLocaleString('en-US') }
function catColor (c) { return { heavy_equipment: 'blue-grey-8', vehicle: 'blue-8', tool: 'teal-7', equipment: 'deep-purple-6' }[c] ?? 'grey' }
function catIcon (c) { return { heavy_equipment: 'agriculture', vehicle: 'local_shipping', tool: 'handyman', equipment: 'construction' }[c] ?? 'inventory_2' }
function catKey (c) { return { heavy_equipment: 'HeavyEquipment', vehicle: 'Vehicles', tool: 'Tools', equipment: 'Equipment' }[c] ?? 'Other' }
function statusColor (s) { return { available: 'positive', in_use: 'blue-7', maintenance: 'amber-8', retired: 'blue-grey-4' }[s] ?? 'grey' }
function statusKey (s) { return { available: 'AssetAvailable', in_use: 'InUse', maintenance: 'UnderMaintenance', retired: 'Retired' }[s] ?? 'AssetAvailable' }

async function load () {
  loading.value = true
  try {
    const params = {}
    if (filters.category) params.category = filters.category
    if (filters.status) params.status = filters.status
    if (filters.tracking) params.tracking = filters.tracking
    const { data } = await api.get('/assets', { params })
    rows.value = data
  } finally { loading.value = false }
  loadTransfers()
}

// ── Cross-branch transfers ──
const transfers = ref([])
const transferDialog = ref(false)
const transferAsset = ref(null)
const submittingTransfer = ref(false)
const transferForm = reactive({ quantity: 1, reason: '' })
const pendingIncoming = computed(() => transfers.value.filter(t => t.status === 'pending' && t.direction === 'incoming').length)
function tStatusColor (s) { return { pending: 'amber-8', approved: 'positive', rejected: 'negative' }[s] || 'grey' }
function tStatusKey (s) { return { pending: 'Pending', approved: 'Approved', rejected: 'Rejected' }[s] || 'Pending' }

async function loadTransfers () { try { const { data } = await api.get('/asset-transfers'); transfers.value = data } catch (_) {} }
function openTransfer (asset) { transferAsset.value = asset; Object.assign(transferForm, { quantity: 1, reason: '' }); transferDialog.value = true }
async function submitTransfer () {
  submittingTransfer.value = true
  try {
    await api.post('/asset-transfers', { asset_id: transferAsset.value.id, quantity: transferForm.quantity, reason: transferForm.reason })
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: 'Request sent' })
    transferDialog.value = false; loadTransfers()
  } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Failed' }) } finally { submittingTransfer.value = false }
}
async function decideTransfer (t, status) {
  try { await api.put(`/asset-transfers/${t.id}/decide`, { status }); Notify.create({ type: 'positive', position: 'bottom', message: status }); load() } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Failed' }) }
}

function openCreate () { Object.assign(form, blank()); dialog.value = true }
function openEdit (id) {
  const r = rows.value.find(x => x.id === id)
  if (!r) return
  Object.assign(form, {
    id: r.id, name: r.name, category: r.category, tracking: r.tracking,
    quantity_total: r.quantity_total, allocated: r.allocated, unit: r.unit || 'piece',
    status: r.status, location: r.location || '', serial: r.serial || '', condition: r.condition || 'good',
    purchase_date: r.purchase_date ? r.purchase_date.slice(0,10) : '', purchase_value: r.purchase_value, currency: r.currency || 'AFN', notes: r.notes || '',
  })
  dialog.value = true
}

async function save () {
  saving.value = true
  try {
    const payload = { ...form }
    if (payload.tracking === 'unit') { payload.quantity_total = 1; payload.unit = null }
    else { payload.serial = null; payload.condition = null }
    if (form.id) await api.put('/assets/' + form.id, payload)
    else await api.post('/assets', payload)
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: 'Saved' })
    dialog.value = false
    load()
  } catch (e) {
    Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Save failed' })
  } finally { saving.value = false }
}

function remove (id) { proxy.$delete('assets/' + id, load) }

// ── Show + maintenance ──
const showDialog = ref(false)
const activeAsset = ref(null)
const showMaintForm = ref(false)
const savingMaint = ref(false)
const maintForm = reactive({ log_date: new Date().toISOString().slice(0,10), work_type: '', cost: null, description: '' })

const showFacts = computed(() => {
  const a = activeAsset.value || {}
  return [
    { label: 'Category', value: proxy.$t(catKey(a.category)) },
    { label: 'TrackingMode', value: a.tracking === 'unit' ? proxy.$t('PerUnit') : proxy.$t('ByCount') },
    { label: 'Available', value: (a.available ?? 0) + ' / ' + (a.quantity_total ?? 0) },
    { label: 'Allocated', value: a.allocated ?? 0 },
    { label: 'Status', value: proxy.$t(statusKey(a.status)) },
    { label: 'Location', value: a.location || '—' },
    { label: 'Serial', value: a.serial || '—' },
    { label: 'PurchaseValue', value: a.purchase_value ? fmt(a.purchase_value) + ' ' + a.currency : '—' },
  ]
})

async function openShow (id) {
  showMaintForm.value = false
  try {
    const { data } = await api.get('/assets/' + id)
    activeAsset.value = data
    showDialog.value = true
  } catch (_) {}
}
async function refreshActive () {
  if (!activeAsset.value) return
  const { data } = await api.get('/assets/' + activeAsset.value.id)
  activeAsset.value = data
}
async function addMaint () {
  savingMaint.value = true
  try {
    await api.post('/assets/' + activeAsset.value.id + '/maintenance', { log_date: maintForm.log_date, work_type: maintForm.work_type, cost: maintForm.cost || 0, description: maintForm.description })
    Object.assign(maintForm, { work_type: '', cost: null, description: '' })
    showMaintForm.value = false
    await refreshActive()
  } catch (e) {
    Notify.create({ type: 'negative', message: 'Save failed' })
  } finally { savingMaint.value = false }
}
function delMaint (m) {
  proxy.$delete('asset-maintenance/' + m.id, refreshActive)
}

onMounted(() => { loadLookups(); load() })
</script>

<style scoped>
.asset-link { color: var(--q-primary); font-weight: 600; cursor: pointer; text-decoration: none; }
.asset-link:hover { text-decoration: underline; }
</style>
