<template>
  <q-page>
    <m-backgrounds>
      <div class="row my_radio_less q-pa-sm">
        <div class="col-12">
          <m-header icon="local_shipping" controlRoomButton="false" class="q-mt-xs">{{ $t('Suppliers') }}</m-header>
        </div>

        <action-bar :rows="rows" :columns="exportColumns" filename="suppliers" create-perm="supplier-create" @add="openCreate" @update:filtered="() => {}" />
        <div class="col-12">
          <n-table config-key="page.suppliers" :loading="loading" :data="rows" :columns="columns" v-model:filter="tableFilter"
            :can_edit="'supplier-edit'" :can_delete="'supplier-delete'" :noInfo="true" @edit="openEdit" @del="remove">
            <template v-slot:body-cell-name="props">
              <q-td :props="props"><a class="sup-link" @click.prevent="openShow(props.row.id)">{{ props.row.name }}</a></q-td>
            </template>
            <template v-slot:body-cell-category="props">
              <q-td :props="props"><q-chip dense size="sm" :color="catColor(props.row.category)" text-color="white">{{ $t(catKey(props.row.category)) }}</q-chip></q-td>
            </template>
          </n-table>
        </div>
      </div>
    </m-backgrounds>

    <!-- Add / Edit -->
    <m-modal :showCM="dialog" @update:showCM="dialog = $event" card_style="width: 440px">
      <q-card class="bg-white">
        <n-header icon="local_shipping">{{ form.id ? $t('Edit') : $t('AddNew') }} — {{ $t('Supplier') }}</n-header>
        <q-separator />
        <q-form @submit="save">
          <q-card-section class="row q-col-gutter-sm">
            <div class="col-12 col-sm-8"><n-name :name="form.name" @update:name="form.name = $event" icon="local_shipping" :label="$t('Name')" autofocus /></div>
            <div class="col-12 col-sm-4"><q-select outlined dense color="primary" v-model="form.category" :options="catOptions" emit-value map-options :label="$t('Category')" /></div>
            <div class="col-6"><n-name :name="form.phone" @update:name="form.phone = $event" icon="phone" :label="$t('Phone')" :rules="[]" /></div>
            <div class="col-6"><n-name :name="form.address" @update:name="form.address = $event" icon="home" :label="$t('Address')" :rules="[]" /></div>
            <div class="col-12"><q-input outlined dense color="primary" type="textarea" autogrow v-model="form.notes" :label="$t('Description')" /></div>
          </q-card-section>
          <q-separator />
          <n-submit :submitting="saving" :label="$t('Save')" />
        </q-form>
      </q-card>
    </m-modal>

    <!-- PO history -->
    <m-modal :showCM="showDialog" @update:showCM="showDialog = $event" card_style="width: 640px">
      <q-card class="bg-white" v-if="active">
        <n-header icon="local_shipping" :subtitle="active.code">{{ active.name }} — {{ $t('PurchaseOrders') }}</n-header>
        <q-separator />
        <q-card-section>
          <q-markup-table flat bordered dense class="my_radio_less" style="max-height:340px">
            <thead class="bg-theme-soft"><tr><th class="text-left">{{ $t('Code') }}</th><th class="text-left">{{ $t('Date') }}</th><th class="text-left">{{ $t('Project') }}</th><th class="text-right">{{ $t('Total') }}</th><th class="text-center">{{ $t('Status') }}</th></tr></thead>
            <tbody>
              <tr v-if="!active.purchase_orders || active.purchase_orders.length === 0"><td colspan="5" class="text-center text-grey-5 q-py-md">{{ $t('NoRecordFound') }}</td></tr>
              <tr v-for="po in active.purchase_orders" :key="po.id">
                <td class="text-weight-medium">{{ po.code }}</td>
                <td>{{ po.order_date ? po.order_date.slice(0, 10) : '—' }}</td>
                <td class="text-caption">{{ po.project?.name || '—' }}</td>
                <td class="text-right">{{ fmt((po.items || []).reduce((s, i) => s + Number(i.line_total || 0), 0)) }} {{ po.currency }}</td>
                <td class="text-center"><q-chip dense size="sm" :color="poColor(po.status)" text-color="white">{{ po.status }}</q-chip></td>
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
import { ref, reactive, getCurrentInstance, onMounted } from 'vue'
import { Notify } from 'quasar'
import { api } from '@/boot/axios'

const { proxy } = getCurrentInstance()
const rows = ref([])
const loading = ref(false)
const saving = ref(false)
const dialog = ref(false)
const tableFilter = ref('')

const catOptions = [
  { label: 'Materials', value: 'materials' }, { label: 'Equipment', value: 'equipment' },
  { label: 'Fuel', value: 'fuel' }, { label: 'Services', value: 'services' }, { label: 'Other', value: 'other' },
]
const blank = () => ({ id: null, name: '', category: 'materials', phone: '', address: '', notes: '' })
const form = reactive(blank())

const columns = [
  { name: 'created_at', label: '#', field: 'id', align: 'left' },
  { name: 'code', label: 'Code', field: 'code', align: 'left', sortable: true },
  { name: 'name', label: 'Name', field: 'name', align: 'left', sortable: true },
  { name: 'category', label: 'Category', field: 'category', align: 'left' },
  { name: 'phone', label: 'Phone', field: 'phone', align: 'left' },
  { name: 'purchase_orders_count', label: 'PurchaseOrders', field: 'purchase_orders_count', align: 'center', sortable: true },
  { name: 'actions', label: 'Actions', field: 'actions', align: 'right' }
]
const exportColumns = columns.filter(c => c.name !== 'actions')

function fmt (v) { return Number(v || 0).toLocaleString('en-US') }
function catColor (c) { return { materials: 'blue-7', equipment: 'deep-purple-6', fuel: 'orange-8', services: 'teal-7', other: 'blue-grey-6' }[c] ?? 'grey' }
function catKey (c) { return { materials: 'Materials', equipment: 'EquipmentCat', fuel: 'Fuel', services: 'Services', other: 'Other' }[c] ?? 'Other' }
function poColor (s) { return { draft: 'blue-grey-5', ordered: 'amber-8', received: 'positive', cancelled: 'negative' }[s] ?? 'grey' }

async function load () { loading.value = true; try { const { data } = await api.get('/suppliers'); rows.value = data } finally { loading.value = false } }
function openCreate () { Object.assign(form, blank()); dialog.value = true }
function openEdit (id) {
  const r = rows.value.find(x => x.id === id)
  if (!r) return
  Object.assign(form, { id: r.id, name: r.name, category: r.category, phone: r.phone || '', address: r.address || '', notes: r.notes || '' })
  dialog.value = true
}
async function save () {
  saving.value = true
  try {
    const payload = { name: form.name, category: form.category, phone: form.phone, address: form.address, notes: form.notes }
    if (form.id) await api.put('/suppliers/' + form.id, payload)
    else await api.post('/suppliers', payload)
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: 'Saved' })
    dialog.value = false; load()
  } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Save failed' }) } finally { saving.value = false }
}
function remove (id) { proxy.$delete('suppliers/' + id, load) }

const showDialog = ref(false)
const active = ref(null)
async function openShow (id) { try { const { data } = await api.get('/suppliers/' + id); active.value = data; showDialog.value = true } catch (_) {} }

onMounted(load)
</script>

<style scoped>
.sup-link { color: var(--q-primary); font-weight: 600; cursor: pointer; text-decoration: none; }
.sup-link:hover { text-decoration: underline; }
</style>
