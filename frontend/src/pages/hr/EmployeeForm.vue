<template>
  <q-page>
    <m-backgrounds>
      <div class="row my_radio_less q-pa-sm">
        <div class="col-12">
          <m-header icon="person" back to="/hr/employees" controlRoomButton="false" class="q-mt-xs">
            {{ isEdit ? $t('Edit') : $t('AddNew') }} — {{ $t('Employee') }}
          </m-header>
        </div>

        <div class="col-12 q-mt-sm">
          <q-form @submit="save">
            <q-card flat bordered class="my_radio_less q-pa-md bg-white">
              <!-- Profile photo (available once the employee exists) -->
              <div v-if="isEdit" class="row items-center q-mb-md">
                <q-avatar size="72px" color="blue-grey-2" text-color="blue-grey-9" class="emp-photo">
                  <img v-if="photo" :src="photo" />
                  <q-icon v-else name="person" size="38px" />
                </q-avatar>
                <div class="q-ml-md">
                  <div class="text-caption text-grey-7 q-mb-xs">{{ $t('ProfilePhoto') }}</div>
                  <q-btn dense no-caps unelevated color="primary" icon="photo_camera" :label="$t('Upload')" size="sm" :loading="uploadingPhoto" @click="photoInput.click()" />
                  <input ref="photoInput" type="file" accept="image/*" class="hidden" @change="onPhoto" />
                </div>
              </div>

              <div class="sec-head">{{ $t('PersonalInfo') }}</div>
              <div class="row q-col-gutter-sm">
                <div class="col-12 col-sm-6"><n-name :name="form.full_name" @update:name="form.full_name = $event" icon="person" :label="$t('FullName')" autofocus /></div>
                <div class="col-6 col-sm-3"><n-name :name="form.father_name" @update:name="form.father_name = $event" icon="person" :label="$t('FatherName')" :rules="[]" /></div>
                <div class="col-6 col-sm-3"><n-name :name="form.grandfather_name" @update:name="form.grandfather_name = $event" icon="person" :label="$t('GrandfatherName')" :rules="[]" /></div>
                <div class="col-6 col-sm-3"><n-name :name="form.tazkira" @update:name="form.tazkira = $event" icon="badge" :label="$t('Tazkira')" :rules="[]" /></div>
                <div class="col-6 col-sm-3"><q-select outlined dense color="primary" v-model="form.gender" :options="genderOptions" emit-value map-options clearable :label="$t('Gender')" /></div>
                <div class="col-6 col-sm-3"><q-select outlined dense color="primary" v-model="form.marital_status" :options="maritalOptions" emit-value map-options clearable :label="$t('MaritalStatus')" /></div>
                <div class="col-6 col-sm-3">
                  <shamsi-date v-model="form.dob" color="primary" :label="$t('DateOfBirth')" clearable />
                </div>
                <div class="col-6 col-sm-3"><n-name :name="form.phone" @update:name="form.phone = $event" icon="phone" :label="$t('Phone')" /></div>
                <div class="col-6 col-sm-3"><n-name :name="form.phone2" @update:name="form.phone2 = $event" icon="phone" :label="$t('Phone2')" :rules="[]" /></div>
                <div class="col-6 col-sm-3"><n-name :name="form.emergency_name" @update:name="form.emergency_name = $event" icon="contact_emergency" :label="$t('EmergencyName')" :rules="[]" /></div>
                <div class="col-6 col-sm-3"><n-name :name="form.emergency_phone" @update:name="form.emergency_phone = $event" icon="phone" :label="$t('EmergencyPhone')" :rules="[]" /></div>
                <div class="col-12 col-sm-6"><n-name :name="form.address" @update:name="form.address = $event" icon="home" :label="$t('Address')" :rules="[]" /></div>
                <div class="col-6 col-sm-3"><n-name :name="form.nationality" @update:name="form.nationality = $event" icon="flag" :label="$t('Nationality')" :rules="[]" /></div>
              </div>

              <div class="sec-head q-mt-md">{{ $t('EmploymentInfo') }}</div>
              <div class="row q-col-gutter-sm">
                <div class="col-6 col-sm-4"><q-select outlined dense color="primary" v-model="form.department_id" :options="deptOptions" emit-value map-options clearable :label="$t('Department')" @update:model-value="form.designation_id = null" /></div>
                <div class="col-6 col-sm-4"><q-select outlined dense color="primary" v-model="form.designation_id" :options="filteredDesignations" emit-value map-options clearable :label="$t('Designation')" /></div>
                <div class="col-6 col-sm-4"><q-select outlined dense color="primary" v-model="form.employment_type" :options="typeOptions" emit-value map-options :label="$t('EmploymentType')" /></div>
                <div class="col-6 col-sm-4">
                  <shamsi-date v-model="form.join_date" color="primary" :label="$t('JoinDate')" clearable />
                </div>
                <div class="col-6 col-sm-4"><q-select outlined dense color="primary" v-model="form.status" :options="empStatusOptions" emit-value map-options :label="$t('Status')" /></div>
                <div class="col-6 col-sm-4"><q-select outlined dense color="primary" v-model="form.manager_id" :options="managerOptions" emit-value map-options clearable :label="$t('ReportingManager')" /></div>
                <div class="col-6 col-sm-4"><q-select outlined dense color="primary" v-model="form.assigned_vehicle_id" :options="vehicleOptions" emit-value map-options clearable :label="$t('AssignedVehicle')" /></div>
                <div class="col-12 col-sm-8"><q-select outlined dense color="primary" v-model="form.assigned_projects" :options="projectOptions" emit-value map-options multiple use-chips clearable :label="$t('AssignedProjects')" /></div>
                <div class="col-12 col-sm-4"><n-name :name="form.license" @update:name="form.license = $event" icon="workspace_premium" :label="$t('License')" :rules="[]" /></div>
                <div class="col-12">
                  <q-select outlined dense color="primary" v-model="form.specializations" :options="specOptions"
                    use-input use-chips multiple new-value-mode="add-unique" input-debounce="0"
                    @filter="filterSpecs" :label="$t('Specializations')" :hint="$t('AddSkillHint')">
                    <template #prepend><q-icon name="star" color="primary" /></template>
                  </q-select>
                </div>
              </div>

              <div class="sec-head q-mt-md">{{ $t('Payroll') }}</div>
              <div class="row q-col-gutter-sm">
                <div class="col-6 col-sm-3"><q-input outlined dense color="primary" type="number" step="0.01" v-model.number="form.basic_salary" :label="$t('BasicSalary')" /></div>
                <div class="col-6 col-sm-3"><q-select outlined dense color="primary" v-model="form.salary_currency" :options="['AFN','USD']" :label="$t('Currency')" /></div>
                <div class="col-6 col-sm-3"><q-select outlined dense color="primary" v-model="form.payment_method" :options="methodOptions" emit-value map-options clearable :label="$t('PaymentMethod')" /></div>
                <div class="col-6 col-sm-3"><n-name :name="form.bank_details" @update:name="form.bank_details = $event" icon="account_balance" :label="$t('BankDetails')" :rules="[]" /></div>
              </div>

              <q-separator class="q-my-md" />
              <div class="row justify-end q-gutter-sm">
                <q-btn flat :label="$t('Cancel')" color="grey-7" @click="router.push('/hr/employees')" />
                <q-btn unelevated color="primary" icon="save" :label="$t('Save')" type="submit" :loading="saving" />
              </div>
            </q-card>
          </q-form>
        </div>
      </div>
    </m-backgrounds>
  </q-page>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { Notify } from 'quasar'
import { api } from '@/boot/axios'
import { compressImage } from '@/utils/image'

const router = useRouter()
const route = useRoute()
const isEdit = computed(() => !!route.params.id)

const saving = ref(false)
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

const blank = () => ({ id: null, full_name: '', father_name: '', grandfather_name: '', tazkira: '', gender: 'male', dob: '', marital_status: null, nationality: 'افغان', phone: '', phone2: '', emergency_name: '', emergency_phone: '', address: '', department_id: null, designation_id: null, employment_type: 'permanent', join_date: '', status: 'active', manager_id: null, assigned_vehicle_id: null, assigned_projects: [], specializations: [], license: '', basic_salary: null, salary_currency: 'AFN', payment_method: 'cash', bank_details: '' })
const form = reactive(blank())

const filteredDesignations = computed(() => designations.value.filter(d => !form.department_id || d.department_id === form.department_id).map(d => ({ label: d.title, value: d.id })))

async function loadMeta () {
  try { const { data } = await api.get('/departments'); deptOptions.value = (data || []).map(d => ({ label: d.name, value: d.id })) } catch (_) {}
  try { const { data } = await api.get('/designations'); designations.value = data || [] } catch (_) {}
  try { const { data } = await api.get('/assets', { params: { category: 'vehicle' } }); vehicleOptions.value = (data || []).map(a => ({ label: a.name + (a.serial ? ' — ' + a.serial : ''), value: a.id })) } catch (_) {}
  try { const { data } = await api.get('/projects'); projectOptions.value = (data || []).map(p => ({ label: p.name, value: p.id })) } catch (_) {}
  try {
    const { data } = await api.get('/employees')
    managerOptions.value = (data || []).map(e => ({ label: e.full_name, value: e.id }))
    // Skill suggestions come from what colleagues already have on file.
    allSpecs.value = [...new Set((data || []).flatMap(e => e.specializations || []))]
  } catch (_) {}
}

// Specializations: pick from known skills or type a new one (multi-select).
const allSpecs = ref([])
const specOptions = ref([])
function filterSpecs (val, update) {
  update(() => {
    const q = (val || '').toLowerCase()
    specOptions.value = q ? allSpecs.value.filter(s => s.toLowerCase().includes(q)) : allSpecs.value
  })
}

const photo = ref(null)
const photoInput = ref(null)
const uploadingPhoto = ref(false)

async function loadEmployee () {
  if (!isEdit.value) return
  try {
    const { data } = await api.get('/employees/' + route.params.id)
    Object.assign(form, { ...blank(), ...data, dob: data.dob ? data.dob.slice(0, 10) : '', join_date: data.join_date ? data.join_date.slice(0, 10) : '', assigned_projects: data.assigned_projects || [], specializations: data.specializations || [] })
    form.id = data.id
    if (data.photo) { try { const res = await api.get('/employees/' + data.id + '/photo', { responseType: 'blob' }); photo.value = URL.createObjectURL(res.data) } catch (_) {} }
  } catch (_) { Notify.create({ type: 'negative', message: 'Load failed' }) }
}

async function onPhoto (e) {
  const file = e.target.files?.[0]; e.target.value = ''
  if (!file || !form.id) return
  uploadingPhoto.value = true
  try {
    const fd = new FormData(); fd.append('photo', await compressImage(file, { maxDim: 640, quality: 0.72 }))
    await api.post('/employees/' + form.id + '/photo', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    const res = await api.get('/employees/' + form.id + '/photo', { responseType: 'blob' })
    photo.value = URL.createObjectURL(res.data)
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: 'Photo updated' })
  } catch (_) { Notify.create({ type: 'negative', message: 'Upload failed' }) } finally { uploadingPhoto.value = false }
}

async function save () {
  saving.value = true
  try {
    const payload = { ...form }
    if (!payload.dob) payload.dob = null
    if (!payload.join_date) payload.join_date = null
    if (form.id) await api.put('/employees/' + form.id, payload)
    else await api.post('/employees', payload)
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: 'Saved' })
    router.push('/hr/employees')
  } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Save failed' }) } finally { saving.value = false }
}

onMounted(() => { loadMeta(); loadEmployee() })
</script>

<style scoped>
.sec-head { font-size: 12px; font-weight: 700; color: var(--q-primary); text-transform: uppercase; letter-spacing: .5px; padding-bottom: 4px; border-bottom: 2px solid color-mix(in srgb, var(--q-primary) 25%, #fff); margin-bottom: 8px; }
.emp-photo { border: 3px solid #fff; box-shadow: 0 4px 14px -6px rgba(15, 23, 42, 0.4); }
.hidden { display: none; }
</style>
