<template>
  <q-page>
    <m-backgrounds>
      <div class="row my_radio_less q-pa-sm">
        <div class="col-12">
          <m-header icon="diversity_3" controlRoomButton="false" class="q-mt-xs">
            {{ $t('Investors') }}
          </m-header>
        </div>

        <action-bar :rows="filteredRows" :columns="exportColumns" filename="investors" create-perm="investor-create" @add="openCreate" @update:filtered="() => {}">
          <template #filters>
            <div class="col-6 col-sm-3"><q-select outlined dense color="blue-grey-9" label-color="blue-grey-9" v-model="typeFilter" :options="typeOptions" emit-value map-options clearable :label="$t('InvestorType')" /></div>
          </template>
        </action-bar>
        <div class="col-12">
          <n-table config-key="page.investors" :loading="loading" :data="filteredRows" :columns="columns" v-model:filter="tableFilter"
            :can_edit="'investor-edit'" :can_delete="'investor-delete'" :noInfo="true" @edit="openEdit" @del="remove">
            <template v-slot:body-cell-name="props">
              <q-td :props="props"><a class="inv-link" @click.prevent="openShow(props.row.id)">{{ props.row.name }}</a></q-td>
            </template>
            <template v-slot:body-cell-type="props">
              <q-td :props="props"><q-chip dense size="sm" :color="typeColor(props.row.type)" text-color="white">{{ $t(typeKey(props.row.type)) }}</q-chip></q-td>
            </template>
            <template v-slot:body-cell-total_capital="props">
              <q-td :props="props" class="text-right text-weight-medium">{{ fmt(props.row.total_capital) }}</q-td>
            </template>
            <template v-slot:body-cell-total_profit="props">
              <q-td :props="props" class="text-right text-positive">{{ fmt(props.row.total_profit) }}</q-td>
            </template>
          </n-table>
        </div>
      </div>
    </m-backgrounds>

    <!-- Add / Edit modal -->
    <m-modal :showCM="dialog" @update:showCM="dialog = $event" card_style="width: 480px">
      <q-card class="bg-white">
        <n-header icon="diversity_3">{{ form.id ? $t('Edit') : $t('AddNew') }} — {{ $t('Investor') }}</n-header>
        <q-separator />
        <q-form @submit="save">
          <q-card-section class="row q-col-gutter-sm">
            <div class="col-12 col-sm-8"><n-name :name="form.name" @update:name="form.name = $event" icon="person" :label="$t('Name')" autofocus /></div>
            <div class="col-12 col-sm-4"><q-select outlined dense color="primary" v-model="form.type" :options="typeOptions" emit-value map-options :label="$t('InvestorType')" /></div>
            <div class="col-12 col-sm-6"><n-name :name="form.phone" @update:name="form.phone = $event" icon="phone" :label="$t('Phone')" :rules="[]" /></div>
            <div class="col-12 col-sm-6"><n-name :name="form.email" @update:name="form.email = $event" icon="email" :label="$t('Email')" :rules="[]" /></div>
            <div class="col-12 col-sm-6"><n-name :name="form.register_no" @update:name="form.register_no = $event" icon="badge" :label="$t('RegisterNo')" :rules="[]" /></div>
            <div class="col-12 col-sm-6"><n-name :name="form.address" @update:name="form.address = $event" icon="home" :label="$t('Address')" :rules="[]" /></div>
            <div class="col-12"><q-input outlined dense color="primary" type="textarea" autogrow v-model="form.notes" :label="$t('Description')" /></div>
          </q-card-section>
          <q-separator />
          <n-submit :submitting="saving" :label="$t('Save')" />
        </q-form>
      </q-card>
    </m-modal>

    <!-- Show modal — cross-project history -->
    <m-modal :showCM="showDialog" @update:showCM="showDialog = $event" card_style="width: 720px">
      <q-card class="bg-white" v-if="activeInv">
        <n-header icon="diversity_3" :subtitle="activeInv.code">{{ activeInv.name }}</n-header>
        <q-separator />
        <q-card-section class="q-pb-none">
          <div class="row q-col-gutter-sm">
            <div class="col-4"><div class="settle-chip"><div class="settle-chip__val text-primary">{{ (activeInv.investments || []).length }}</div><div class="settle-chip__lbl">{{ $t('ProjectsCount') }}</div></div></div>
            <div class="col-4"><div class="settle-chip"><div class="settle-chip__val" style="color:#175A8C">{{ fmt(activeInv.total_capital) }}</div><div class="settle-chip__lbl">{{ $t('TotalCapital') }}</div></div></div>
            <div class="col-4"><div class="settle-chip"><div class="settle-chip__val text-positive">{{ fmt(activeInv.total_profit) }}</div><div class="settle-chip__lbl">{{ $t('ProfitReceived') }}</div></div></div>
          </div>
        </q-card-section>
        <q-card-section>
          <div class="text-subtitle2 q-mb-xs">{{ $t('InvestmentHistory') }}</div>
          <q-markup-table flat bordered dense class="my_radio_less">
            <thead class="bg-theme-soft">
              <tr>
                <th class="text-left">{{ $t('ProjectName') }}</th>
                <th class="text-right">{{ $t('Capital') }}</th>
                <th class="text-center">{{ $t('ProfitPercent') }}</th>
                <th class="text-right">{{ $t('ProfitReceived') }}</th>
                <th class="text-center">{{ $t('Status') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!activeInv.investments || activeInv.investments.length === 0"><td colspan="5" class="text-center text-grey-5 q-py-md">{{ $t('NoRecordFound') }}</td></tr>
              <tr v-for="iv in activeInv.investments" :key="iv.id">
                <td class="text-weight-medium">{{ iv.project?.name || '—' }}</td>
                <td class="text-right">{{ fmt(iv.capital) }} {{ iv.currency }}</td>
                <td class="text-center">{{ Number(iv.profit_percent) }}%</td>
                <td class="text-right text-positive">{{ fmt(iv.profit_received) }}</td>
                <td class="text-center"><q-chip dense size="sm" color="blue-grey-2" text-color="blue-grey-9">{{ iv.project?.status || '—' }}</q-chip></td>
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

const { proxy } = getCurrentInstance()
const rows = ref([])
const loading = ref(false)
const saving = ref(false)
const dialog = ref(false)
const tableFilter = ref('')
const typeFilter = ref(null)

const typeOptions = [
  { label: 'Individual', value: 'individual' },
  { label: 'Company', value: 'company' },
  { label: 'Government', value: 'government' },
]

const blank = () => ({ id: null, name: '', type: 'individual', phone: '', email: '', register_no: '', address: '', notes: '' })
const form = reactive(blank())

const filteredRows = computed(() => typeFilter.value ? rows.value.filter(r => r.type === typeFilter.value) : rows.value)

const columns = [
  { name: 'created_at', label: '#', field: 'id', align: 'left' },
  { name: 'code', label: 'Code', field: 'code', align: 'left', sortable: true },
  { name: 'name', label: 'Name', field: 'name', align: 'left', sortable: true },
  { name: 'type', label: 'InvestorType', field: 'type', align: 'left' },
  { name: 'phone', label: 'Phone', field: 'phone', align: 'left' },
  { name: 'investments_count', label: 'ProjectsCount', field: 'investments_count', align: 'center', sortable: true },
  { name: 'total_capital', label: 'TotalCapital', field: 'total_capital', align: 'right', sortable: true },
  { name: 'total_profit', label: 'ProfitReceived', field: 'total_profit', align: 'right' },
  { name: 'actions', label: 'Actions', field: 'actions', align: 'right' }
]
const exportColumns = columns.filter(c => c.name !== 'actions')

function fmt (v) { return Number(v || 0).toLocaleString('en-US') }
function typeColor (t) { return { individual: 'blue-7', company: 'deep-purple-6', government: 'teal-7' }[t] ?? 'grey' }
function typeKey (t) { return { individual: 'Individual', company: 'Company', government: 'Government' }[t] ?? 'Individual' }

async function load () {
  loading.value = true
  try { const { data } = await api.get('/investors'); rows.value = data } finally { loading.value = false }
}
function openCreate () { Object.assign(form, blank()); dialog.value = true }
function openEdit (id) {
  const r = rows.value.find(x => x.id === id)
  if (!r) return
  Object.assign(form, { id: r.id, name: r.name, type: r.type, phone: r.phone || '', email: r.email || '', register_no: r.register_no || '', address: r.address || '', notes: r.notes || '' })
  dialog.value = true
}
async function save () {
  saving.value = true
  try {
    const payload = { name: form.name, type: form.type, phone: form.phone, email: form.email, register_no: form.register_no, address: form.address, notes: form.notes }
    if (form.id) await api.put('/investors/' + form.id, payload)
    else await api.post('/investors', payload)
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: 'Saved' })
    dialog.value = false; load()
  } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Save failed' }) } finally { saving.value = false }
}
function remove (id) { proxy.$delete('investors/' + id, load) }

const showDialog = ref(false)
const activeInv = ref(null)
async function openShow (id) {
  try { const { data } = await api.get('/investors/' + id); activeInv.value = data; showDialog.value = true } catch (_) {}
}

onMounted(load)
</script>

<style scoped>
.inv-link { color: var(--q-primary); font-weight: 600; cursor: pointer; text-decoration: none; }
.inv-link:hover { text-decoration: underline; }
.settle-chip { border: 1.5px solid #E2E8F0; border-radius: 10px; padding: 8px 10px; text-align: center; background: #F8FAFC; }
.settle-chip__val { font-size: 16px; font-weight: 800; letter-spacing: -0.3px; }
.settle-chip__lbl { font-size: 10px; color: #94A3B8; margin-top: 2px; }
</style>
