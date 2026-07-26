<template>
  <q-page>
    <m-backgrounds>
      <div class="row my_radio_less q-pa-sm">
        <div class="col-12">
          <m-header icon="inventory" controlRoomButton="false" class="q-mt-xs">{{ $t('Warehouse') }}</m-header>
        </div>

        <div class="col-12 q-mt-md">
          <div class="row q-col-gutter-md">
            <div class="col-6 col-md-3"><stat-card icon="inventory_2" :label="$t('StockItems')" :value="rows.length" color="#175A8C" tint="#E0EDF7" /></div>
            <div class="col-6 col-md-3"><stat-card icon="warning" :label="$t('LowStock')" :value="lowCount" color="#DC2626" tint="#FEE2E2" :sub="$t('BelowMinimum')" sub-icon="south" /></div>
            <div class="col-6 col-md-3"><stat-card icon="swap_vert" :label="$t('Movements')" :value="movementCount" color="#7C3AED" tint="#EDE9FE" /></div>
            <div class="col-6 col-md-3"><stat-card icon="domain" :label="$t('ConsumableHint')" value="گدام" color="#0D9488" tint="#CCFBF1" :sub="$t('SeparateFromAssets')" sub-icon="construction" /></div>
          </div>
        </div>

        <action-bar :rows="rows" :columns="exportColumns" filename="stock" create-perm="stock-item-create" @add="openCreate" @update:filtered="() => {}" />
        <div class="col-12">
          <n-table config-key="page.stock" :loading="loading" :data="rows" :columns="columns" v-model:filter="tableFilter"
            :can_edit="'stock-item-edit'" :can_delete="'stock-item-delete'" :noInfo="true" @edit="openEdit" @del="remove">
            <template v-slot:body-cell-name="props">
              <q-td :props="props"><a class="stk-link" @click.prevent="openShow(props.row.id)">{{ props.row.name }}</a></q-td>
            </template>
            <template v-slot:body-cell-quantity="props">
              <q-td :props="props" class="text-right">
                <q-chip dense size="sm" :color="props.row.low ? 'red-1' : 'green-1'" :text-color="props.row.low ? 'red-9' : 'green-9'" class="text-weight-bold">
                  {{ fmt(props.row.quantity) }} {{ props.row.unit }}
                </q-chip>
                <q-icon v-if="props.row.low" name="warning" color="negative" size="15px" class="q-ml-xs"><q-tooltip>{{ $t('LowStock') }}</q-tooltip></q-icon>
              </q-td>
            </template>
          </n-table>
        </div>
      </div>
    </m-backgrounds>

    <!-- Add / Edit item -->
    <m-modal :showCM="dialog" @update:showCM="dialog = $event" card_style="width: 440px">
      <q-card class="bg-white">
        <n-header icon="inventory">{{ form.id ? $t('Edit') : $t('AddNew') }} — {{ $t('StockItem') }}</n-header>
        <q-separator />
        <q-form @submit="save">
          <q-card-section class="row q-col-gutter-sm">
            <div class="col-12"><n-name :name="form.name" @update:name="form.name = $event" icon="inventory" :label="$t('Name')" autofocus /></div>
            <div class="col-6"><q-select outlined dense color="primary" v-model="form.unit" :options="unitOptions" use-input new-value-mode="add-unique" :label="$t('Unit')" /></div>
            <div class="col-6"><q-input outlined dense color="primary" type="number" step="any" v-model.number="form.min_quantity" :label="$t('MinQuantity')" /></div>
            <div class="col-12"><q-input outlined dense color="primary" type="textarea" autogrow v-model="form.notes" :label="$t('Description')" /></div>
          </q-card-section>
          <q-separator />
          <n-submit :submitting="saving" :label="$t('Save')" />
        </q-form>
      </q-card>
    </m-modal>

    <!-- Movements -->
    <m-modal :showCM="showDialog" @update:showCM="showDialog = $event" card_style="width: 720px">
      <q-card class="bg-white" v-if="active">
        <n-header icon="swap_vert" :subtitle="active.code">{{ active.name }} — {{ fmt(active.quantity) }} {{ active.unit }}</n-header>
        <q-separator />
        <q-card-section v-if="$can('stock-movement-create')">
          <q-form @submit="saveMove" class="row q-col-gutter-sm items-end stk-add">
            <div class="col-6 col-sm-3">
              <q-btn-toggle v-model="moveForm.direction" spread unelevated dense toggle-color="primary" color="grey-2" text-color="grey-8"
                :options="[{ label: $t('MoneyIn').split(' ')[0] || 'In', value: 'in', icon: 'south_west' }, { label: 'Out', value: 'out', icon: 'north_east' }]" />
            </div>
            <div class="col-6 col-sm-2"><q-input outlined dense color="primary" type="number" step="any" v-model.number="moveForm.quantity" :label="$t('Quantity')" :rules="[v => v > 0 || $t('FieldIsRequired')]" hide-bottom-space /></div>
            <div class="col-6 col-sm-3"><q-select outlined dense color="primary" v-model="moveForm.kind" :options="kindOptions" emit-value map-options :label="$t('Kind')" /></div>
            <div class="col-6 col-sm-3"><q-select outlined dense color="primary" v-model="moveForm.project_id" :options="projectOptions" emit-value map-options clearable :label="$t('Project')" /></div>
            <div class="col-12 col-sm-1"><q-btn unelevated color="teal-7" icon="add" type="submit" :loading="savingMove" round dense /></div>
            <div class="col-12"><q-input outlined dense color="primary" v-model="moveForm.note" :label="$t('Notes')" /></div>
          </q-form>
        </q-card-section>
        <q-card-section class="q-pt-none">
          <q-markup-table flat bordered dense class="my_radio_less" style="max-height:320px">
            <thead class="bg-theme-soft">
              <tr><th class="text-left">{{ $t('Date') }}</th><th class="text-center">{{ $t('Kind') }}</th><th class="text-right">{{ $t('Quantity') }}</th><th class="text-left">{{ $t('Project') }}</th><th class="text-left">{{ $t('Notes') }}</th><th class="text-right"></th></tr>
            </thead>
            <tbody>
              <tr v-if="!active.movements || active.movements.length === 0"><td colspan="6" class="text-center text-grey-5 q-py-md">{{ $t('NoRecordFound') }}</td></tr>
              <tr v-for="m in active.movements" :key="m.id">
                <td style="white-space:nowrap">{{ m.movement_date ? m.movement_date.slice(0, 10) : '—' }}</td>
                <td class="text-center"><q-chip dense size="sm" :color="kindColor(m.kind)" text-color="white">{{ $t(kindKey(m.kind)) }}</q-chip></td>
                <td class="text-right text-weight-bold" :class="m.direction === 'in' ? 'text-positive' : 'text-negative'">{{ m.direction === 'in' ? '+' : '−' }}{{ fmt(m.quantity) }}</td>
                <td class="text-caption">{{ m.project?.name || '—' }}</td>
                <td class="text-caption">{{ m.note || '—' }}</td>
                <td class="text-right"><q-btn size="sm" dense flat round icon="delete" color="negative" v-if="$can('stock-movement-delete')" @click="removeMove(m)" /></td>
              </tr>
            </tbody>
          </q-markup-table>
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
import { useLookups } from '@/composables/useLookups'

const { proxy } = getCurrentInstance()
const rows = ref([])
const loading = ref(false)
const saving = ref(false)
const dialog = ref(false)
const tableFilter = ref('')
const projectOptions = ref([])

// Units come from the Options Registry (fallback until loaded).
const { loadLookups, options: lookupOptions } = useLookups()
const unitOptions = computed(() => {
  const o = lookupOptions('unit')
  return o.length ? o.map(x => x.value) : ['bag', 'ton', 'm³', 'm²', 'm', 'piece', 'litre', 'kg', 'roll']
})
const kindOptions = [
  { label: 'Purchase', value: 'purchase' }, { label: 'Consumption', value: 'consumption' },
  { label: 'Adjustment', value: 'adjustment' }, { label: 'Return', value: 'return' },
]
const blank = () => ({ id: null, name: '', unit: 'bag', min_quantity: null, notes: '' })
const form = reactive(blank())

const columns = [
  { name: 'created_at', label: '#', field: 'id', align: 'left' },
  { name: 'code', label: 'Code', field: 'code', align: 'left', sortable: true },
  { name: 'name', label: 'Name', field: 'name', align: 'left', sortable: true },
  { name: 'quantity', label: 'OnHand', field: 'quantity', align: 'right', sortable: true },
  { name: 'min_quantity', label: 'MinQuantity', field: 'min_quantity', align: 'right' },
  { name: 'movements_count', label: 'Movements', field: 'movements_count', align: 'center', sortable: true },
  { name: 'actions', label: 'Actions', field: 'actions', align: 'right' }
]
const exportColumns = columns.filter(c => c.name !== 'actions')
const lowCount = computed(() => rows.value.filter(r => r.low).length)
const movementCount = computed(() => rows.value.reduce((s, r) => s + Number(r.movements_count || 0), 0))

function fmt (v) { return Number(v || 0).toLocaleString('en-US') }
function kindColor (k) { return { purchase: 'green-7', consumption: 'deep-orange-6', adjustment: 'blue-grey-6', return: 'teal-7' }[k] ?? 'grey' }
function kindKey (k) { return { purchase: 'Purchase', consumption: 'Consumption', adjustment: 'Adjustment', return: 'ReturnKind' }[k] ?? 'Adjustment' }

async function load () { loading.value = true; try { const { data } = await api.get('/stock-items'); rows.value = data } finally { loading.value = false } }
async function loadProjects () { try { const { data } = await api.get('/projects'); projectOptions.value = (data || []).map(p => ({ label: p.name, value: p.id })) } catch (_) {} }

function openCreate () { Object.assign(form, blank()); dialog.value = true }
function openEdit (id) {
  const r = rows.value.find(x => x.id === id)
  if (!r) return
  Object.assign(form, { id: r.id, name: r.name, unit: r.unit, min_quantity: Number(r.min_quantity), notes: r.notes || '' })
  dialog.value = true
}
async function save () {
  saving.value = true
  try {
    const payload = { name: form.name, unit: form.unit, min_quantity: form.min_quantity || 0, notes: form.notes }
    if (form.id) await api.put('/stock-items/' + form.id, payload)
    else await api.post('/stock-items', payload)
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: 'Saved' })
    dialog.value = false; load()
  } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Save failed' }) } finally { saving.value = false }
}
function remove (id) { proxy.$delete('stock-items/' + id, load) }

// movements
const showDialog = ref(false)
const active = ref(null)
const savingMove = ref(false)
const today = () => new Date().toISOString().slice(0, 10)
const moveForm = reactive({ direction: 'out', kind: 'consumption', quantity: null, project_id: null, note: '' })
async function openShow (id) { try { const { data } = await api.get('/stock-items/' + id); active.value = data; showDialog.value = true } catch (_) {} }
async function refresh () { if (active.value) { try { const { data } = await api.get('/stock-items/' + active.value.id); active.value = data } catch (_) {} load() } }
async function saveMove () {
  savingMove.value = true
  try {
    await api.post('/stock-items/' + active.value.id + '/movements', { ...moveForm, movement_date: today() })
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: 'Saved' })
    Object.assign(moveForm, { quantity: null, note: '' }); refresh()
  } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || e?.response?.data?.errors?.quantity?.[0] || 'Save failed' }) } finally { savingMove.value = false }
}
function removeMove (m) { proxy.$delete('stock-movements/' + m.id, refresh) }

onMounted(() => { loadLookups(); load(); loadProjects() })
</script>

<style scoped>
.stk-link { color: var(--q-primary); font-weight: 600; cursor: pointer; text-decoration: none; }
.stk-link:hover { text-decoration: underline; }
.stk-add { background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px; padding: 10px; }
</style>
