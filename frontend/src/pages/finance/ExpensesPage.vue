<template>
  <q-page>
    <m-backgrounds>
      <div class="row my_radio_less q-pa-sm">
        <div class="col-12">
          <m-header icon="receipt_long" controlRoomButton="false" class="q-mt-xs">
            {{ $t('Expenses') }}
          </m-header>
        </div>

        <action-bar
          :rows="rows"
          :columns="columns"
          filename="expenses"
          create-perm="expense-create"
          @add="openCreate"
          @update:filtered="filteredRows = $event"
        />
        <div class="col-12">
          <n-table config-key="page.expenses"
            :loading="loading"
            :data="rows"
            :columns="columns"
            v-model:filter="filter"
            :can_edit="'expense-edit'"
            :can_delete="'expense-delete'"
            :noInfo="true"
            @edit="openEdit"
            @del="remove"
          >
            <template v-slot:body-cell-amount="props">
              <q-td :props="props" class="text-right">{{ fmt(props.row.amount) }} {{ props.row.currency }}</q-td>
            </template>
            <template v-slot:body-cell-rate="props">
              <q-td :props="props" class="text-right text-grey-7">{{ Number(props.row.rate) === 1 ? '—' : fmt(props.row.rate) }}</q-td>
            </template>
            <template v-slot:body-cell-amount_base="props">
              <q-td :props="props" class="text-right text-weight-bold text-primary">{{ fmt(props.row.amount_base) }} {{ base }}</q-td>
            </template>
            <template v-slot:body-cell-project="props">
              <q-td :props="props">{{ props.row.project?.name || '—' }}</q-td>
            </template>
          </n-table>
        </div>
      </div>
    </m-backgrounds>

    <m-modal :showCM="dialog" @update:showCM="dialog = $event" card_style="width: 620px">
      <q-card class="bg-white">
        <n-header icon="receipt_long">{{ form.id ? $t('Edit') : $t('AddNew') }} — {{ $t('Expense') }}</n-header>
        <q-separator />
        <q-form @submit="save">
          <q-card-section class="row q-col-gutter-sm">
            <div class="col-6 col-sm-4">
              <shamsi-date v-model="form.expense_date" color="primary" :label="$t('ExpenseDate')" />
            </div>
            <div class="col-6 col-sm-4">
              <lookup-select v-model="form.category" group="expense_category" icon="receipt_long" allow-other :label="$t('Category')" />
            </div>
            <div class="col-12 col-sm-4">
              <q-select outlined dense color="primary" label-color="primary" v-model="form.project_id"
                :options="projectOptions" emit-value map-options clearable :label="$t('Project')" />
            </div>

            <div class="col-12 col-sm-6"><n-name :name="form.payee" @update:name="form.payee = $event" icon="person" :label="$t('Payee')" :rules="[]" /></div>
            <div class="col-12 col-sm-6"><n-name :name="form.description" @update:name="form.description = $event" icon="notes" :label="$t('Description')" :rules="[]" /></div>

            <div class="col-12 col-sm-9">
              <money-input v-model="form.amount" v-model:currency="form.currency" v-model:rate="form.rate" :label="$t('Amount')" />
            </div>
            <div class="col-6 col-sm-3">
              <div class="settle-chip">
                <div class="settle-chip__val text-primary">{{ fmt(baseAmount) }} {{ base }}</div>
                <div class="settle-chip__lbl">{{ $t('BaseAmount') }}</div>
              </div>
            </div>

            <div class="col-12">
              <q-file outlined dense color="primary" v-model="docFiles" multiple :label="$t('AttachBill')"
                accept=".jpg,.jpeg,.png,.webp,.pdf" max-file-size="41943040" clearable counter>
                <template #prepend><q-icon name="receipt_long" color="primary" /></template>
              </q-file>
            </div>
            <div v-if="form.id" class="col-12">
              <attach-box type="expense" :id="form.id" kind="receipt" :label="$t('Attachments')" icon="attach_file" />
            </div>
          </q-card-section>
          <q-separator />
          <n-submit :submitting="saving" :label="$t('Save')" />
        </q-form>
      </q-card>
    </m-modal>
  </q-page>
</template>

<script setup>
import { ref, reactive, computed, getCurrentInstance, onMounted } from 'vue'
import { Notify } from 'quasar'
import { api } from '@/boot/axios'
import { uploadDocs } from '@/composables/useAttachments'

const { proxy } = getCurrentInstance()

const rows = ref([])
const filteredRows = ref([])
const loading = ref(false)
const saving = ref(false)
const dialog = ref(false)
const docFiles = ref(null)
const filter = ref('')

const base = ref('AFN')
const rateMap = ref({ AFN: 1 })
const currencyOptions = ref(['AFN'])
const projectOptions = ref([])
const categoryOptions = ['Materials', 'Labour', 'Transport', 'Fuel', 'Equipment Rent', 'Office', 'Utilities', 'General']

const blank = () => ({ id: null, expense_date: new Date().toISOString().slice(0, 10), category: 'General', project_id: null, payee: '', description: '', currency: 'AFN', amount: null, rate: 1 })
const form = reactive(blank())

const isBase = computed(() => form.currency === base.value)
const baseAmount = computed(() => {
  const amt = Number(form.amount || 0)
  return isBase.value ? amt : amt * Number(form.rate || 0)
})

const columns = [
  { name: 'created_at', label: '#', field: 'id', align: 'left' },
  { name: 'expense_date', label: 'ExpenseDate', field: 'expense_date', align: 'left', sortable: true },
  { name: 'category', label: 'Category', field: 'category', align: 'left', sortable: true },
  { name: 'project', label: 'Project', field: 'project', align: 'left' },
  { name: 'payee', label: 'Payee', field: 'payee', align: 'left' },
  { name: 'amount', label: 'Amount', field: 'amount', align: 'right', sortable: true },
  { name: 'rate', label: 'Rate', field: 'rate', align: 'right' },
  { name: 'amount_base', label: 'BaseAmount', field: 'amount_base', align: 'right', sortable: true },
  { name: 'actions', label: 'Actions', field: 'actions', align: 'right' }
]

function fmt (v) { return Number(v || 0).toLocaleString('en-US', { maximumFractionDigits: 2 }) }

function onCurrency (code) {
  form.rate = code === base.value ? 1 : (rateMap.value[code] || 1)
}

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
    const { data } = await api.get('/expenses')
    rows.value = data
  } finally { loading.value = false }
}

function openCreate () {
  Object.assign(form, blank())
  form.currency = base.value
  form.rate = 1
  dialog.value = true
}
function openEdit (id) {
  const r = rows.value.find(x => x.id === id)
  if (!r) return
  Object.assign(form, {
    id: r.id, expense_date: r.expense_date ? r.expense_date.slice(0, 10) : '', category: r.category,
    project_id: r.project_id, payee: r.payee || '', description: r.description || '',
    currency: r.currency, amount: Number(r.amount), rate: Number(r.rate),
  })
  dialog.value = true
}

async function save () {
  saving.value = true
  try {
    const payload = {
      expense_date: form.expense_date, category: form.category, project_id: form.project_id,
      payee: form.payee, description: form.description, currency: form.currency,
      amount: form.amount, rate: isBase.value ? 1 : form.rate,
    }
    let savedId = form.id
    if (form.id) await api.put('/expenses/' + form.id, payload)
    else { const { data } = await api.post('/expenses', payload); savedId = data?.id }
    if (savedId && docFiles.value) { try { await uploadDocs('expense', savedId, docFiles.value) } catch (_) {} }
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: 'Saved' })
    dialog.value = false
    docFiles.value = null
    load()
  } catch (e) {
    Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Save failed' })
  } finally { saving.value = false }
}

function remove (id) { proxy.$delete('expenses/' + id, load) }

onMounted(() => { loadMeta(); load() })
</script>

<style scoped>
.settle-chip {
  border: 1.5px solid #E2E8F0;
  border-radius: 8px;
  padding: 6px 8px;
  text-align: center;
  background: #F8FAFC;
  height: 40px;
  display: flex;
  flex-direction: column;
  justify-content: center;
}
.settle-chip__val { font-size: 14px; font-weight: 800; letter-spacing: -0.3px; }
.settle-chip__lbl { font-size: 9px; color: #94A3B8; }
</style>
