<template>
  <q-page>
    <m-backgrounds>
      <div class="row my_radio_less q-pa-sm">
        <div class="col-12"><m-header icon="beach_access" back to="/hr/employees" controlRoomButton="false" class="q-mt-xs">{{ $t('LeaveManagement') }}</m-header></div>

        <!-- Filters + add -->
        <div class="col-12 q-mt-sm">
          <q-card flat bordered class="my_radio_less q-pa-sm">
            <div class="row q-col-gutter-sm items-end">
              <div class="col-6 col-sm-3">
                <q-select outlined dense color="primary" v-model="fStatus" :options="statusFilterOptions" emit-value map-options clearable :label="$t('Status')" @update:model-value="load" />
              </div>
              <div class="col-6 col-sm-3">
                <q-select outlined dense color="primary" v-model="fType" :options="typeOptions" emit-value map-options clearable :label="$t('Type')" @update:model-value="load" />
              </div>
              <div class="col-6 col-sm-3">
                <q-input outlined dense color="primary" v-model="fMonth" mask="####-##" :label="$t('Period') + ' (YYYY-MM)'" clearable @update:model-value="load" />
              </div>
              <div class="col-6 col-sm-3">
                <q-btn unelevated color="primary" icon="add" :label="$t('Add') + ' ' + $t('Leave')" class="full-width" @click="openCreate" v-if="$can('leave-create')" />
              </div>
            </div>
          </q-card>
        </div>

        <div class="col-12 q-mt-sm">
          <q-markup-table flat bordered dense class="my_radio_less bg-white">
            <thead class="bg-theme-soft"><tr>
              <th class="text-left">{{ $t('Employee') }}</th><th class="text-left">{{ $t('Type') }}</th>
              <th class="text-left">{{ $t('StartDate') }}</th><th class="text-left">{{ $t('EndDate') }}</th>
              <th class="text-center">{{ $t('Days') }}</th><th class="text-center">{{ $t('Status') }}</th>
              <th class="text-right">{{ $t('Actions') }}</th>
            </tr></thead>
            <tbody>
              <tr v-if="rows.length === 0"><td colspan="7" class="text-center text-grey-5 q-py-md">{{ $t('NoRecordFound') }}</td></tr>
              <tr v-for="r in rows" :key="r.id">
                <td class="text-weight-medium">{{ r.employee?.full_name }}<div class="text-caption text-grey-6">{{ r.employee?.code }}</div></td>
                <td><q-chip dense size="sm" color="blue-grey-1" text-color="blue-grey-8">{{ $t(typeKey(r.type)) }}</q-chip></td>
                <td>{{ (r.start_date || '').slice(0, 10) }}</td>
                <td>{{ (r.end_date || '').slice(0, 10) }}</td>
                <td class="text-center text-weight-bold">{{ r.days }}</td>
                <td class="text-center"><q-chip dense size="sm" :color="statusColor(r.status)" text-color="white">{{ $t(statusKey(r.status)) }}</q-chip></td>
                <td class="text-right" style="white-space:nowrap">
                  <template v-if="r.status === 'pending' && $can('leave-edit')">
                    <q-btn size="sm" dense flat round icon="check" color="positive" @click="decide(r, 'approved')"><q-tooltip>{{ $t('Approve') }}</q-tooltip></q-btn>
                    <q-btn size="sm" dense flat round icon="close" color="negative" @click="decide(r, 'rejected')"><q-tooltip>{{ $t('Reject') }}</q-tooltip></q-btn>
                  </template>
                  <q-btn v-if="$can('leave-delete')" size="sm" dense flat round icon="delete" color="grey-7" @click="remove(r)" />
                </td>
              </tr>
            </tbody>
          </q-markup-table>
        </div>
      </div>
    </m-backgrounds>

    <!-- Add leave -->
    <m-modal :showCM="dialog" @update:showCM="dialog = $event" card_style="width: 520px">
      <q-card class="bg-white">
        <n-header icon="beach_access">{{ $t('AddNew') }} — {{ $t('Leave') }}</n-header>
        <q-separator />
        <q-form @submit="save">
          <q-card-section class="row q-col-gutter-sm">
            <div class="col-12">
              <q-select outlined dense color="primary" v-model="form.employee_id" :options="employeeOptions" emit-value map-options use-input @filter="filterEmp" :label="$t('Employee')" :rules="[v => !!v || $t('FieldIsRequired')]">
                <template #prepend><q-icon name="person" color="primary" /></template>
              </q-select>
            </div>
            <div class="col-12 col-sm-6">
              <q-select outlined dense color="primary" v-model="form.type" :options="typeOptions" emit-value map-options :label="$t('Type')" />
            </div>
            <div class="col-12 col-sm-6 flex items-center">
              <q-toggle v-model="form.paid" :label="$t('Paid')" color="primary" />
            </div>
            <div class="col-6">
              <shamsi-date v-model="form.start_date" color="primary" :label="$t('StartDate')" />
            </div>
            <div class="col-6">
              <shamsi-date v-model="form.end_date" color="primary" :label="$t('EndDate')" />
            </div>
            <div class="col-12"><q-input outlined dense color="primary" type="textarea" autogrow v-model="form.reason" :label="$t('Reason')" /></div>
          </q-card-section>
          <q-separator />
          <n-submit :submitting="saving" :label="$t('Save')" />
        </q-form>
      </q-card>
    </m-modal>
  </q-page>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { Notify } from 'quasar'
import { api } from '@/boot/axios'

const rows = ref([])
const dialog = ref(false)
const saving = ref(false)
const fStatus = ref(null)
const fType = ref(null)
const fMonth = ref('')
const allEmployees = ref([])
const employeeOptions = ref([])

const typeOptions = [
  { label: 'Annual', value: 'annual' }, { label: 'Sick', value: 'sick' },
  { label: 'Unpaid', value: 'unpaid' }, { label: 'Maternity', value: 'maternity' }, { label: 'Other', value: 'other' }
]
const statusFilterOptions = [
  { label: 'Pending', value: 'pending' }, { label: 'Approved', value: 'approved' }, { label: 'Rejected', value: 'rejected' }
]

const blank = () => ({ employee_id: null, type: 'annual', paid: true, start_date: new Date().toISOString().slice(0, 10), end_date: new Date().toISOString().slice(0, 10), reason: '' })
const form = reactive(blank())

function typeKey (t) { return ({ annual: 'Annual', sick: 'Sick', unpaid: 'Unpaid', maternity: 'Maternity', other: 'Other' })[t] || 'Other' }
function statusKey (s) { return ({ pending: 'Pending', approved: 'Approved', rejected: 'Rejected' })[s] || 'Pending' }
function statusColor (s) { return ({ pending: 'amber-8', approved: 'positive', rejected: 'negative' })[s] || 'grey' }

function filterEmp (val, update) {
  update(() => {
    const n = (val || '').toLowerCase()
    employeeOptions.value = allEmployees.value.filter(e => e.label.toLowerCase().includes(n))
  })
}

async function loadEmployees () {
  try { const { data } = await api.get('/employees'); allEmployees.value = (data || []).map(e => ({ label: `${e.full_name} (${e.code})`, value: e.id })); employeeOptions.value = allEmployees.value } catch (_) {}
}
async function load () {
  try {
    const { data } = await api.get('/leaves', { params: { status: fStatus.value || undefined, type: fType.value || undefined, month: fMonth.value || undefined } })
    rows.value = data
  } catch (_) {}
}
function openCreate () { Object.assign(form, blank()); dialog.value = true }
async function save () {
  saving.value = true
  try {
    await api.post('/leaves', { ...form })
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: 'Saved' })
    dialog.value = false; load()
  } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Save failed' }) } finally { saving.value = false }
}
async function decide (r, status) {
  try { await api.put(`/leaves/${r.id}/decide`, { status }); Notify.create({ type: 'positive', position: 'bottom', message: status }); load() } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Failed' }) }
}
function remove (r) {
  api.delete('/leaves/' + r.id).then(() => { rows.value = rows.value.filter(x => x.id !== r.id); Notify.create({ type: 'positive', position: 'bottom', icon: 'delete', message: 'Removed' }) }).catch(() => Notify.create({ type: 'negative', message: 'Delete failed' }))
}

onMounted(() => { loadEmployees(); load() })
</script>
