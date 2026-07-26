<template>
  <q-page>
    <m-backgrounds>
      <div class="row my_radio_less q-pa-sm">
        <div class="col-12">
          <m-header icon="groups" controlRoomButton="false" class="q-mt-xs">
            {{ $t('Employees') }}
          </m-header>
        </div>

        <action-bar :rows="rows" :columns="exportColumns" filename="employees" create-perm="employee-create" @add="openCreate" @update:filtered="() => {}">
          <template #filters>
            <div class="col-6 col-sm-3"><q-select outlined dense color="blue-grey-9" label-color="blue-grey-9" v-model="filters.department_id" :options="deptOptions" emit-value map-options clearable :label="$t('Department')" @update:model-value="load" /></div>
            <div class="col-6 col-sm-3"><q-select outlined dense color="blue-grey-9" label-color="blue-grey-9" v-model="filters.employment_type" :options="typeOptions" emit-value map-options clearable :label="$t('EmploymentType')" @update:model-value="load" /></div>
            <div class="col-6 col-sm-3"><q-select outlined dense color="blue-grey-9" label-color="blue-grey-9" v-model="filters.status" :options="empStatusOptions" emit-value map-options clearable :label="$t('Status')" @update:model-value="load" /></div>
          </template>
        </action-bar>
        <div class="col-12">
          <n-table config-key="page.employees" :loading="loading" :data="rows" :columns="columns" v-model:filter="tableFilter"
            :can_edit="'employee-edit'" :can_delete="'employee-delete'" :can_show="'employee-list'"
            info-icon="badge" :noInfoDialog="true" @info="openShow" @edit="openEdit" @del="remove">
            <template v-slot:body-cell-full_name="props">
              <q-td :props="props">
                <div class="row items-center no-wrap">
                  <q-avatar size="34px" class="q-mr-sm" color="blue-grey-2" text-color="blue-grey-9">
                    <img v-if="photos[props.row.id]" :src="photos[props.row.id]" />
                    <span v-else>{{ (props.row.full_name || '?').slice(0, 1) }}</span>
                  </q-avatar>
                  <div style="min-width:0">
                    <a class="emp-link" @click.prevent="openShow(props.row.id)">{{ props.row.full_name }}</a>
                    <div v-if="props.row.phone" class="text-caption text-grey-6">{{ props.row.phone }}</div>
                  </div>
                </div>
              </q-td>
            </template>
            <template v-slot:body-cell-department="props"><q-td :props="props">{{ props.row.department?.name || '—' }}</q-td></template>
            <template v-slot:body-cell-designation="props"><q-td :props="props">{{ props.row.designation?.title || '—' }}</q-td></template>
            <template v-slot:body-cell-employment_type="props">
              <q-td :props="props"><q-chip dense size="sm" :color="typeColor(props.row.employment_type)" text-color="white">{{ $t(typeKey(props.row.employment_type)) }}</q-chip></q-td>
            </template>
            <template v-slot:body-cell-basic_salary="props">
              <q-td :props="props" class="text-right">{{ props.row.basic_salary ? fmt(props.row.basic_salary) + ' ' + props.row.salary_currency : '—' }}</q-td>
            </template>
            <template v-slot:body-cell-status="props">
              <q-td :props="props"><q-chip dense size="sm" :color="stColor(props.row.status)" text-color="white">{{ $t(stKey(props.row.status)) }}</q-chip></q-td>
            </template>
          </n-table>
        </div>
      </div>
    </m-backgrounds>

    <!-- Show modal -->
    <m-modal :showCM="showDialog" @update:showCM="showDialog = $event" card_style="width: 640px">
      <q-card class="bg-white" v-if="activeEmp">
        <n-header icon="person" :subtitle="activeEmp.code">{{ activeEmp.full_name }}</n-header>
        <q-separator />
        <q-card-section>
          <div class="row q-col-gutter-md">
            <div class="col-6 col-sm-4" v-for="f in showFacts" :key="f.label">
              <div class="text-caption text-grey-6">{{ $t(f.label) }}</div>
              <div class="text-subtitle2 text-weight-bold">{{ f.value }}</div>
            </div>
          </div>
        </q-card-section>
        <q-separator />
        <q-card-actions align="right" class="q-pa-sm"><q-btn flat :label="$t('Close')" color="grey-7" @click="showDialog = false" /></q-card-actions>
      </q-card>
    </m-modal>
  </q-page>
</template>

<script setup>
import { ref, reactive, computed, getCurrentInstance, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { Notify } from 'quasar'
import { api } from '@/boot/axios'

const router = useRouter()

const { proxy } = getCurrentInstance()
const rows = ref([])
const loading = ref(false)
const saving = ref(false)
const dialog = ref(false)
const tableFilter = ref('')
const filters = reactive({ department_id: null, employment_type: null, status: null })

const deptOptions = ref([])
const designations = ref([])
const managerOptions = ref([])
const vehicleOptions = ref([])
const projectOptions = ref([])

const genderOptions = [{ label: 'Male', value: 'male' }, { label: 'Female', value: 'female' }]
const maritalOptions = [{ label: 'Single', value: 'single' }, { label: 'Married', value: 'married' }]
const typeOptions = [{ label: 'Permanent', value: 'permanent' }, { label: 'Contract', value: 'contract' }, { label: 'Daily-wage', value: 'daily_wage' }]
const empStatusOptions = [{ label: 'Active', value: 'active' }, { label: 'On Leave', value: 'on_leave' }, { label: 'Inactive', value: 'inactive' }]
const methodOptions = [{ label: 'Cash', value: 'cash' }, { label: 'Bank', value: 'bank' }, { label: 'Hawala', value: 'hawala' }]

const blank = () => ({ id: null, full_name: '', father_name: '', grandfather_name: '', tazkira: '', gender: 'male', dob: '', marital_status: null, nationality: 'افغان', phone: '', phone2: '', emergency_name: '', emergency_phone: '', address: '', department_id: null, designation_id: null, employment_type: 'permanent', join_date: '', status: 'active', manager_id: null, assigned_vehicle_id: null, assigned_projects: [], license: '', basic_salary: null, salary_currency: 'AFN', payment_method: 'cash', bank_details: '' })
const form = reactive(blank())

const filteredDesignations = computed(() => designations.value.filter(d => !form.department_id || d.department_id === form.department_id).map(d => ({ label: d.title, value: d.id })))

const columns = [
  { name: 'created_at', label: '#', field: 'id', align: 'left' },
  { name: 'code', label: 'Code', field: 'code', align: 'left', sortable: true },
  { name: 'full_name', label: 'FullName', field: 'full_name', align: 'left', sortable: true },
  { name: 'department', label: 'Department', field: 'department', align: 'left' },
  { name: 'designation', label: 'Designation', field: 'designation', align: 'left' },
  { name: 'employment_type', label: 'EmploymentType', field: 'employment_type', align: 'left' },
  { name: 'basic_salary', label: 'Salary', field: 'basic_salary', align: 'right' },
  { name: 'status', label: 'Status', field: 'status', align: 'center' },
  { name: 'actions', label: 'Actions', field: 'actions', align: 'right' }
]
const exportColumns = columns.filter(c => c.name !== 'actions')

function fmt (v) { return Number(v || 0).toLocaleString('en-US') }
function typeColor (t) { return { permanent: 'positive', contract: 'blue-7', daily_wage: 'amber-8' }[t] ?? 'grey' }
function typeKey (t) { return { permanent: 'Permanent', contract: 'Contract', daily_wage: 'DailyWage' }[t] ?? 'Permanent' }
function stColor (s) { return { active: 'positive', on_leave: 'amber-8', inactive: 'blue-grey-4' }[s] ?? 'grey' }
function stKey (s) { return { active: 'Active', on_leave: 'OnLeave', inactive: 'Inactive' }[s] ?? 'Active' }

async function loadMeta () {
  try { const { data } = await api.get('/departments'); deptOptions.value = (data || []).map(d => ({ label: d.name, value: d.id })) } catch (_) {}
  try { const { data } = await api.get('/designations'); designations.value = data || [] } catch (_) {}
  try { const { data } = await api.get('/assets', { params: { category: 'vehicle' } }); vehicleOptions.value = (data || []).map(a => ({ label: a.name + (a.serial ? ' — ' + a.serial : ''), value: a.id })) } catch (_) {}
  try { const { data } = await api.get('/projects'); projectOptions.value = (data || []).map(p => ({ label: p.name, value: p.id })) } catch (_) {}
}
async function load () {
  loading.value = true
  try {
    const params = {}
    if (filters.department_id) params.department_id = filters.department_id
    if (filters.employment_type) params.employment_type = filters.employment_type
    if (filters.status) params.status = filters.status
    const { data } = await api.get('/employees', { params })
    rows.value = data
    managerOptions.value = data.map(e => ({ label: e.full_name, value: e.id }))
    for (const e of data) if (e.photo) loadPhoto(e.id)
  } finally { loading.value = false }
}

const photos = reactive({})
async function loadPhoto (id) {
  if (photos[id]) return
  try { const res = await api.get('/employees/' + id + '/photo', { responseType: 'blob' }); photos[id] = URL.createObjectURL(res.data) } catch (_) {}
}

function openCreate () { router.push('/hr/employees/create') }
function openEdit (id) { router.push('/hr/employees/edit/' + id) }
function remove (id) { proxy.$delete('employees/' + id, load) }

// Show
const showDialog = ref(false)
const activeEmp = ref(null)
const showFacts = computed(() => {
  const e = activeEmp.value || {}
  return [
    { label: 'Department', value: e.department?.name || '—' },
    { label: 'Designation', value: e.designation?.title || '—' },
    { label: 'EmploymentType', value: proxy.$t(typeKey(e.employment_type)) },
    { label: 'Status', value: proxy.$t(stKey(e.status)) },
    { label: 'Phone', value: e.phone || '—' },
    { label: 'Tazkira', value: e.tazkira || '—' },
    { label: 'JoinDate', value: e.join_date ? e.join_date.slice(0,10) : '—' },
    { label: 'BasicSalary', value: e.basic_salary ? fmt(e.basic_salary) + ' ' + e.salary_currency : '—' },
    { label: 'ReportingManager', value: e.manager?.full_name || '—' },
    { label: 'AssignedVehicle', value: e.assigned_vehicle?.name || '—' },
    { label: 'License', value: e.license || '—' },
    { label: 'Address', value: e.address || '—' },
  ]
})
function openShow (id) { router.push('/hr/employees/' + id) }

onMounted(() => { loadMeta(); load() })
</script>

<style scoped>
.emp-link { color: var(--q-primary); font-weight: 600; cursor: pointer; text-decoration: none; }
.emp-link:hover { text-decoration: underline; }
.sec-head { font-size: 12px; font-weight: 700; color: var(--q-primary); text-transform: uppercase; letter-spacing: .5px; padding-bottom: 4px; border-bottom: 2px solid color-mix(in srgb, var(--q-primary) 25%, #fff); margin-bottom: 8px; }
</style>
