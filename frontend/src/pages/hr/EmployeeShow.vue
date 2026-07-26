<template>
  <q-page>
    <m-backgrounds>
      <div class="row my_radio_less q-pa-sm" v-if="e">
        <!-- Hero -->
        <div class="col-12">
          <div class="emp-hero">
            <div class="emp-hero__bar">
              <div class="emp-hero__head">
                <div class="emp-hero__title">
                  <q-avatar size="56px" class="emp-hero__ava">
                    <img v-if="photo" :src="photo" />
                    <q-icon v-else name="person" size="30px" />
                    <q-btn v-if="$can('employee-edit')" round dense size="xs" color="white" text-color="primary" icon="photo_camera" class="emp-hero__cam" @click="photoInput.click()" />
                  </q-avatar>
                  <div>
                    <div class="emp-hero__name">{{ e.full_name }}</div>
                    <div class="emp-hero__meta">
                      <span v-if="e.code" class="emp-hero__code">{{ e.code }}</span>
                      <q-chip dense size="sm" :color="statusColor(e.status)" text-color="white">{{ $t(statusKey(e.status)) }}</q-chip>
                      <span class="emp-hero__pill" v-if="e.designation"><q-icon name="badge" size="13px" /> {{ e.designation.title }}</span>
                      <span class="emp-hero__pill" v-if="e.department"><q-icon name="apartment" size="13px" /> {{ e.department.name }}</span>
                      <span class="emp-hero__pill" v-if="e.phone"><q-icon name="phone" size="13px" /> {{ e.phone }}</span>
                    </div>
                  </div>
                </div>
                <div class="q-gutter-xs row items-center">
                  <q-btn flat dense icon="print" color="white" :label="$t('Print')" @click="printProfile" />
                  <q-btn flat dense icon="edit" color="white" :label="$t('Edit')" v-if="$can('employee-edit')" @click="router.push('/hr/employees')" />
                  <q-btn flat dense icon="arrow_back" color="white" @click="router.push('/hr/employees')" />
                </div>
              </div>
              <div class="emp-hero__progress">
                <div class="row items-center justify-between">
                  <div class="text-caption" style="opacity:.85">{{ $t('AttendanceRate') }}</div>
                  <div class="text-weight-bold">{{ attRate }}%</div>
                </div>
                <q-linear-progress rounded size="12px" :value="attRate / 100" color="amber-4" track-color="white" class="q-mt-xs" style="opacity:.95" />
              </div>
            </div>
            <div class="row q-col-gutter-sm emp-hero__stats">
              <div class="col-6 col-md-3" v-for="s in heroStats" :key="s.label">
                <div class="kpi-tile"><q-icon :name="s.icon" size="20px" class="kpi-tile__icon" /><div class="kpi-tile__val">{{ s.value }}</div><div class="kpi-tile__lbl">{{ $t(s.label) }}</div></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Pills -->
        <div class="col-12 q-mt-md">
          <div class="dash-nav">
            <button v-for="s in sections" :key="s.name" type="button" class="dash-pill" :class="{ 'dash-pill--active': tab === s.name }" @click="tab = s.name">
              <span class="dash-pill__orb"><q-icon :name="s.icon" size="14px" /></span>
              <span class="dash-pill__label">{{ $t(s.label) }}</span>
              <span v-if="s.count && s.count() > 0" class="dash-pill__count">{{ s.count() }}</span>
            </button>
          </div>
        </div>

        <div class="col-12 q-mt-sm">
          <q-card flat bordered class="my_radio_less dash-body">
            <div class="q-px-md q-pt-md">
              <tab-title :title="$t(activeSection.label)" :icon="activeSection.icon"
                :count="activeSection.count ? activeSection.count() : null" />
            </div>
            <q-tab-panels v-model="tab" animated>
              <!-- INFORMATION -->
              <q-tab-panel name="info">
                <div class="row q-col-gutter-md">
                  <div class="col-12 col-md-6">
                    <div class="text-subtitle2 q-mb-xs">{{ $t('PersonalInfo') }}</div>
                    <q-markup-table flat bordered dense class="my_radio_less">
                      <tbody>
                        <tr><td class="text-grey-7">{{ $t('FatherName') }}</td><td>{{ e.father_name || '—' }}</td></tr>
                        <tr><td class="text-grey-7">{{ $t('GrandfatherName') }}</td><td>{{ e.grandfather_name || '—' }}</td></tr>
                        <tr><td class="text-grey-7">{{ $t('IdNumber') }}</td><td>{{ e.tazkira || '—' }}</td></tr>
                        <tr><td class="text-grey-7">{{ $t('Gender') }}</td><td>{{ e.gender ? $t(e.gender.charAt(0).toUpperCase() + e.gender.slice(1)) : '—' }}</td></tr>
                        <tr><td class="text-grey-7">{{ $t('MaritalStatus') }}</td><td>{{ e.marital_status ? $t(e.marital_status.charAt(0).toUpperCase() + e.marital_status.slice(1)) : '—' }}</td></tr>
                        <tr><td class="text-grey-7">{{ $t('DateOfBirth') }}</td><td><dual-date v-if="e.dob" :value="e.dob" /><span v-else>—</span></td></tr>
                        <tr><td class="text-grey-7">{{ $t('Nationality') }}</td><td>{{ e.nationality || '—' }}</td></tr>
                        <tr><td class="text-grey-7">{{ $t('Phone') }}</td><td>{{ e.phone || '—' }}{{ e.phone2 ? ' · ' + e.phone2 : '' }}</td></tr>
                        <tr><td class="text-grey-7">{{ $t('Address') }}</td><td>{{ e.address || '—' }}</td></tr>
                        <tr><td class="text-grey-7">{{ $t('Emergency') }}</td><td>{{ e.emergency_name || '—' }} {{ e.emergency_phone ? '· ' + e.emergency_phone : '' }}</td></tr>
                      </tbody>
                    </q-markup-table>
                  </div>
                  <div class="col-12 col-md-6">
                    <div class="text-subtitle2 q-mb-xs">{{ $t('Employment') }}</div>
                    <q-markup-table flat bordered dense class="my_radio_less">
                      <tbody>
                        <tr><td class="text-grey-7">{{ $t('EmploymentType') }}</td><td>{{ e.employment_type }}</td></tr>
                        <tr><td class="text-grey-7">{{ $t('JoinDate') }}</td><td><dual-date v-if="e.join_date" :value="e.join_date" /><span v-else>—</span></td></tr>
                        <tr><td class="text-grey-7">{{ $t('License') }}</td><td>{{ e.license || '—' }}</td></tr>
                        <tr><td class="text-grey-7">{{ $t('Manager') }}</td><td>{{ e.manager?.full_name || '—' }}</td></tr>
                        <tr><td class="text-grey-7">{{ $t('BasicSalary') }}</td><td>{{ fmt(e.basic_salary) }} {{ e.salary_currency }}</td></tr>
                        <tr><td class="text-grey-7">{{ $t('PaymentMethod') }}</td><td>{{ e.payment_method || '—' }}</td></tr>
                        <tr><td class="text-grey-7">{{ $t('AssignedVehicle') }}</td><td>{{ e.assigned_vehicle?.name || '—' }}</td></tr>
                      </tbody>
                    </q-markup-table>
                  </div>

                  <!-- Specializations shown right here on the info page (not only in their own tab) -->
                  <div class="col-12">
                    <div class="row items-center q-mb-xs">
                      <div class="text-subtitle2">{{ $t('Specializations') }}</div>
                      <q-space />
                      <q-btn v-if="$can('employee-edit')" flat dense size="sm" color="primary" icon="edit" :label="$t('Edit')" @click="openSpec" />
                    </div>
                    <div v-if="(e.specializations || []).length" class="q-gutter-xs">
                      <q-chip v-for="(s, i) in e.specializations" :key="i" color="blue-1" text-color="blue-9" icon="star" size="md">{{ s }}</q-chip>
                    </div>
                    <div v-else class="text-grey-6 text-caption">{{ $t('NoRecordFound') }}</div>
                  </div>
                </div>
              </q-tab-panel>

              <!-- SALARY HISTORY -->
              <q-tab-panel name="salary">
                <div class="text-subtitle2 q-mb-sm">{{ $t('SalaryHistory') }} — {{ $t('TotalPaid') }}: <b>{{ fmt(e.paid_total) }} AFN</b></div>
                <q-markup-table flat bordered dense class="my_radio_less">
                  <thead class="bg-theme-soft"><tr><th class="text-left">{{ $t('Period') }}</th><th class="text-center">{{ $t('Status') }}</th><th class="text-right">{{ $t('BasicSalary') }}</th><th class="text-right">{{ $t('Allowances') }}</th><th class="text-right">{{ $t('Overtime') }}</th><th class="text-right">{{ $t('Deductions') }}</th><th class="text-right">{{ $t('Net') }}</th></tr></thead>
                  <tbody>
                    <tr v-if="!(e.salary_history || []).length"><td colspan="7" class="text-center text-grey-5 q-py-md">{{ $t('NoRecordFound') }}</td></tr>
                    <tr v-for="(s, i) in (e.salary_history || [])" :key="i">
                      <td class="text-weight-medium">{{ s.period }}</td>
                      <td class="text-center"><q-chip dense size="sm" :color="s.status === 'paid' ? 'green-1' : 'amber-1'" :text-color="s.status === 'paid' ? 'green-9' : 'amber-9'">{{ s.status }}</q-chip></td>
                      <td class="text-right">{{ fmt(s.basic) }}</td><td class="text-right">{{ fmt(s.allowances) }}</td>
                      <td class="text-right">{{ fmt(s.overtime) }}</td><td class="text-right text-negative">{{ fmt(s.deductions) }}</td>
                      <td class="text-right text-weight-bold">{{ fmt(s.net) }} {{ s.currency }}</td>
                    </tr>
                  </tbody>
                </q-markup-table>
              </q-tab-panel>

              <!-- ATTENDANCE -->
              <q-tab-panel name="attendance">
                <div class="row q-col-gutter-sm q-mb-md">
                  <div class="col-4"><stat-card dense icon="how_to_reg" :label="$t('Present')" :value="e.attendance?.present" color="#16A34A" tint="#DCFCE7" /></div>
                  <div class="col-4"><stat-card dense icon="person_off" :label="$t('Absent')" :value="e.attendance?.absent" color="#DC2626" tint="#FEE2E2" /></div>
                  <div class="col-4"><stat-card dense icon="beach_access" :label="$t('Leave')" :value="e.attendance?.leave" color="#D97706" tint="#FEF3C7" /></div>
                </div>
                <q-markup-table flat bordered dense class="my_radio_less" style="max-height:360px">
                  <thead class="bg-theme-soft"><tr><th class="text-left">{{ $t('Date') }}</th><th class="text-center">{{ $t('Status') }}</th><th class="text-left">{{ $t('Notes') }}</th></tr></thead>
                  <tbody>
                    <tr v-for="a in (e.attendance?.recent || [])" :key="a.id">
                      <td>{{ (a.att_date || '').slice(0, 10) }}</td>
                      <td class="text-center"><q-chip dense size="sm" :color="attColor(a.status)" text-color="white">{{ $t(attKey(a.status)) }}</q-chip></td>
                      <td class="text-caption">{{ a.note || '—' }}</td>
                    </tr>
                  </tbody>
                </q-markup-table>
              </q-tab-panel>

              <!-- STUDIES -->
              <q-tab-panel name="studies">
                <div class="row items-center justify-between q-mb-sm">
                  <div class="text-subtitle2">{{ $t('Studies') }} ({{ (e.educations || []).length }})</div>
                  <progress-btn color="teal" icon="add" v-if="$can('employee-edit')" @click="eduDialog = true">{{ $t('AddEducation') }}</progress-btn>
                </div>
                <q-markup-table flat bordered dense class="my_radio_less">
                  <thead class="bg-theme-soft"><tr><th class="text-left">{{ $t('Degree') }}</th><th class="text-left">{{ $t('Field') }}</th><th class="text-left">{{ $t('Institution') }}</th><th class="text-center">{{ $t('Years') }}</th><th class="text-left">{{ $t('Grade') }}</th><th></th></tr></thead>
                  <tbody>
                    <tr v-if="!(e.educations || []).length"><td colspan="6" class="text-center text-grey-5 q-py-md">{{ $t('NoRecordFound') }}</td></tr>
                    <tr v-for="ed in (e.educations || [])" :key="ed.id">
                      <td class="text-weight-medium">{{ ed.degree }}</td><td>{{ ed.field || '—' }}</td><td>{{ ed.institution || '—' }}</td>
                      <td class="text-center">{{ ed.year_from }}<span v-if="ed.year_to"> – {{ ed.year_to }}</span></td><td>{{ ed.grade || '—' }}</td>
                      <td class="text-right"><q-btn v-if="$can('employee-edit')" size="sm" dense flat round icon="delete" color="negative" @click="delEducation(ed)" /></td>
                    </tr>
                  </tbody>
                </q-markup-table>
              </q-tab-panel>

              <!-- SPECIALIZATIONS -->
              <q-tab-panel name="specializations">
                <div class="row items-center justify-between q-mb-sm">
                  <div class="text-subtitle2">{{ $t('Specializations') }}</div>
                  <progress-btn color="teal" icon="edit" v-if="$can('employee-edit')" @click="openSpec">{{ $t('Edit') }}</progress-btn>
                </div>
                <div v-if="(e.specializations || []).length" class="q-gutter-sm">
                  <q-chip v-for="(s, i) in e.specializations" :key="i" color="blue-1" text-color="blue-9" icon="star" size="md">{{ s }}</q-chip>
                </div>
                <div v-else class="text-grey-5">{{ $t('NoRecordFound') }}</div>
              </q-tab-panel>

              <!-- DOCUMENTS -->
              <q-tab-panel name="documents">
                <div class="row items-center justify-between q-mb-sm">
                  <div class="text-subtitle2">{{ $t('Documents') }} ({{ (e.documents || []).length }})</div>
                  <progress-btn color="teal" icon="upload_file" v-if="$can('employee-edit')" @click="docDialog = true">{{ $t('Upload') }}</progress-btn>
                </div>
                <div v-if="!(e.documents || []).length" class="text-center text-grey-5 q-py-md">{{ $t('NoRecordFound') }}</div>
                <div v-else class="row q-col-gutter-md">
                  <div class="col-6 col-sm-4 col-md-3" v-for="d in e.documents" :key="d.id">
                    <div class="doc-card">
                      <div class="doc-card__top" :style="`background:${docColor(d.doc_type)}22;color:${docColor(d.doc_type)}`">
                        <q-icon :name="docIcon(d.doc_type)" size="30px" />
                        <q-chip dense size="sm" :style="`background:${docColor(d.doc_type)};color:#fff`">{{ $t(docKey(d.doc_type)) }}</q-chip>
                      </div>
                      <div class="doc-card__body">
                        <div class="doc-card__title">{{ d.title }}</div>
                        <div class="text-caption text-grey-6" v-if="d.number">#{{ d.number }}</div>
                        <div class="text-caption text-grey-6" v-if="d.expiry_date">{{ $t('Expires') }}: {{ (d.expiry_date || '').slice(0, 10) }}</div>
                      </div>
                      <div class="doc-card__actions">
                        <q-btn v-if="d.file_path" size="sm" dense flat round icon="download" color="primary" @click="downloadDoc(d)" />
                        <q-btn v-if="$can('employee-edit')" size="sm" dense flat round icon="delete" color="negative" @click="delDocument(d)" />
                      </div>
                    </div>
                  </div>
                </div>
              </q-tab-panel>

              <!-- PROJECTS -->
              <q-tab-panel name="projects">
                <div class="text-subtitle2 q-mb-sm">{{ $t('ProjectsWorkedOn') }} ({{ (e.projects || []).length }})</div>
                <div v-if="!(e.projects || []).length" class="text-center text-grey-5 q-py-md">{{ $t('NoRecordFound') }}</div>
                <div v-else class="row q-col-gutter-md">
                  <div class="col-12 col-sm-6 col-md-4" v-for="p in e.projects" :key="p.id">
                    <q-card flat bordered class="my_radio_less cursor-pointer" @click="router.push('/projects/' + p.id)">
                      <q-card-section class="q-py-sm">
                        <div class="row items-center justify-between">
                          <div class="text-weight-bold">{{ p.name }}</div>
                          <q-chip dense size="sm" color="blue-grey-1" text-color="blue-grey-9">{{ p.code }}</q-chip>
                        </div>
                        <q-linear-progress rounded size="8px" :value="(p.progress || 0) / 100" color="primary" class="q-mt-sm" />
                        <div class="text-caption text-grey-6 q-mt-xs">{{ p.progress || 0 }}% · {{ p.status }}</div>
                      </q-card-section>
                    </q-card>
                  </div>
                </div>
              </q-tab-panel>
            </q-tab-panels>
          </q-card>
        </div>
      </div>
    </m-backgrounds>

    <input ref="photoInput" type="file" accept="image/*" style="display:none" @change="onPhoto" />

    <!-- Add education -->
    <m-modal :showCM="eduDialog" @update:showCM="eduDialog = $event" card_style="width: 480px">
      <q-card class="bg-white"><n-header icon="school">{{ $t('AddEducation') }}</n-header><q-separator />
        <q-form @submit="saveEducation">
          <q-card-section class="row q-col-gutter-sm">
            <div class="col-12 col-sm-6"><n-name :name="eduForm.degree" @update:name="eduForm.degree = $event" icon="school" :label="$t('Degree')" /></div>
            <div class="col-12 col-sm-6"><n-name :name="eduForm.field" @update:name="eduForm.field = $event" icon="menu_book" :label="$t('Field')" :rules="[]" /></div>
            <div class="col-12"><n-name :name="eduForm.institution" @update:name="eduForm.institution = $event" icon="account_balance" :label="$t('Institution')" :rules="[]" /></div>
            <div class="col-4"><q-input outlined dense color="primary" v-model="eduForm.year_from" :label="$t('From')" mask="####" /></div>
            <div class="col-4"><q-input outlined dense color="primary" v-model="eduForm.year_to" :label="$t('To')" mask="####" /></div>
            <div class="col-4"><n-name :name="eduForm.grade" @update:name="eduForm.grade = $event" icon="grade" :label="$t('Grade')" :rules="[]" /></div>
          </q-card-section>
          <q-separator /><n-submit :submitting="saving" :label="$t('Save')" />
        </q-form>
      </q-card>
    </m-modal>

    <!-- Upload document -->
    <m-modal :showCM="docDialog" @update:showCM="docDialog = $event" card_style="width: 480px">
      <q-card class="bg-white"><n-header icon="upload_file">{{ $t('UploadDocument') }}</n-header><q-separator />
        <q-form @submit="saveDocument">
          <q-card-section class="row q-col-gutter-sm">
            <div class="col-12 col-sm-6"><q-select outlined dense color="primary" v-model="docForm.doc_type" :options="docTypeOptions" emit-value map-options :label="$t('DocumentType')" /></div>
            <div class="col-12 col-sm-6"><n-name :name="docForm.title" @update:name="docForm.title = $event" icon="description" :label="$t('Title')" /></div>
            <div class="col-12 col-sm-6"><n-name :name="docForm.number" @update:name="docForm.number = $event" icon="tag" :label="$t('Number')" :rules="[]" /></div>
            <div class="col-12 col-sm-6"><shamsi-date v-model="docForm.expiry_date" color="primary" :label="$t('ExpiryDate')" /></div>
            <div class="col-12">
              <q-file outlined dense color="primary" v-model="docFile" :label="$t('File')" accept=".jpg,.jpeg,.png,.webp,.pdf" max-file-size="41943040" clearable>
                <template #prepend><q-icon name="attach_file" color="primary" /></template>
              </q-file>
            </div>
          </q-card-section>
          <q-separator /><n-submit :submitting="saving" :label="$t('Save')" />
        </q-form>
      </q-card>
    </m-modal>

    <!-- Edit specializations -->
    <m-modal :showCM="specDialog" @update:showCM="specDialog = $event" card_style="width: 460px">
      <q-card class="bg-white"><n-header icon="star">{{ $t('Specializations') }}</n-header><q-separator />
        <q-card-section>
          <q-select outlined dense color="primary" v-model="specList" use-input use-chips multiple hide-dropdown-icon new-value-mode="add-unique" :label="$t('AddSkillHint')" />
        </q-card-section>
        <q-separator /><q-card-actions align="right" class="q-pa-sm"><q-btn flat :label="$t('Close')" color="grey-7" @click="specDialog = false" /><q-btn unelevated color="primary" :label="$t('Save')" :loading="saving" @click="saveSpec" /></q-card-actions>
      </q-card>
    </m-modal>
  </q-page>
</template>

<script setup>
import { ref, reactive, computed, getCurrentInstance, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Notify } from 'quasar'
import { api } from '@/boot/axios'
import { compressImage } from '@/utils/image'

const route = useRoute()
const router = useRouter()
const { proxy } = getCurrentInstance()
const id = route.params.id

const e = ref(null)
const photo = ref(null)
const tab = ref('info')
const saving = ref(false)
const photoInput = ref(null)

function fmt (v) { return Number(v || 0).toLocaleString('en-US') }
function statusColor (s) { return { active: 'green-7', on_leave: 'amber-8', inactive: 'blue-grey-6' }[s] ?? 'grey' }
function statusKey (s) { return { active: 'Active', on_leave: 'OnLeave', inactive: 'Inactive' }[s] ?? 'Active' }
function attColor (s) { return { present: 'green-7', absent: 'red-7', leave: 'amber-8', holiday: 'blue-grey-6' }[s] ?? 'grey' }
function attKey (s) { return { present: 'Present', absent: 'Absent', leave: 'Leave', holiday: 'Holiday' }[s] ?? 'Present' }
function docIcon (t) { return { degree: 'school', national_id: 'badge', passport: 'flight', license: 'directions_car', contract: 'gavel', certificate: 'workspace_premium', other: 'description' }[t] ?? 'description' }
function docColor (t) { return { degree: '#7C3AED', national_id: '#175A8C', passport: '#0D9488', license: '#D97706', contract: '#DC2626', certificate: '#2563EB', other: '#64748B' }[t] ?? '#64748B' }
function docKey (t) { return { degree: 'Degree', national_id: 'NationalId', passport: 'Passport', license: 'License', contract: 'Contract', certificate: 'Certificate', other: 'Other' }[t] ?? 'Other' }

const docTypeOptions = [
  { label: 'Degree', value: 'degree' }, { label: 'National ID', value: 'national_id' }, { label: 'Passport', value: 'passport' },
  { label: 'License', value: 'license' }, { label: 'Contract', value: 'contract' }, { label: 'Certificate', value: 'certificate' }, { label: 'Other', value: 'other' },
]

const sections = [
  { name: 'info', label: 'Information', icon: 'person' },
  { name: 'salary', label: 'SalaryHistory', icon: 'payments', count: () => (e.value?.salary_history || []).length },
  { name: 'attendance', label: 'Attendance', icon: 'event_available', count: () => e.value?.attendance?.total || 0 },
  { name: 'studies', label: 'Studies', icon: 'school', count: () => (e.value?.educations || []).length },
  { name: 'specializations', label: 'Specializations', icon: 'star', count: () => (e.value?.specializations || []).length },
  { name: 'documents', label: 'Documents', icon: 'folder', count: () => (e.value?.documents || []).length },
  { name: 'projects', label: 'ProjectsWorkedOn', icon: 'domain', count: () => (e.value?.projects || []).length },
]
const activeSection = computed(() => sections.find(s => s.name === tab.value) || sections[0])

const attRate = computed(() => {
  const a = e.value?.attendance; if (!a || !a.total) return 0
  return Math.round(((a.present + (a.leave || 0)) / a.total) * 100)
})
const heroStats = computed(() => [
  { label: 'BasicSalary', value: fmt(e.value?.basic_salary) + ' ' + (e.value?.salary_currency || ''), icon: 'account_balance_wallet' },
  { label: 'TotalPaid', value: fmt(e.value?.paid_total), icon: 'payments' },
  { label: 'AttendanceRate', value: attRate.value + '%', icon: 'event_available' },
  { label: 'Projects', value: (e.value?.projects || []).length, icon: 'domain' },
])

async function load () {
  const { data } = await api.get('/employees/' + id + '/profile')
  e.value = data
  if (data.photo) { try { const res = await api.get('/employees/' + id + '/photo', { responseType: 'blob' }); photo.value = URL.createObjectURL(new Blob([res.data])) } catch (_) {} }
}

// photo
async function onPhoto (ev) {
  const file = ev.target.files?.[0]; ev.target.value = ''
  if (!file) return
  const fd = new FormData(); fd.append('photo', await compressImage(file))
  try { await api.post('/employees/' + id + '/photo', fd, { headers: { 'Content-Type': 'multipart/form-data' } }); load(); Notify.create({ type: 'positive', position: 'bottom', message: 'Saved' }) } catch (_) { Notify.create({ type: 'negative', message: 'Failed' }) }
}

// education
const eduDialog = ref(false)
const eduForm = reactive({ degree: '', field: '', institution: '', year_from: '', year_to: '', grade: '' })
async function saveEducation () {
  saving.value = true
  try { await api.post('/employees/' + id + '/educations', { ...eduForm }); eduDialog.value = false; Object.assign(eduForm, { degree: '', field: '', institution: '', year_from: '', year_to: '', grade: '' }); load(); Notify.create({ type: 'positive', position: 'bottom', message: 'Saved' }) }
  catch (err) { Notify.create({ type: 'negative', message: err?.response?.data?.message || 'Failed' }) } finally { saving.value = false }
}
function delEducation (ed) { proxy.$delete('employee-educations/' + ed.id, load) }

// documents
const docDialog = ref(false)
const docFile = ref(null)
const docForm = reactive({ doc_type: 'national_id', title: '', number: '', expiry_date: '' })
async function saveDocument () {
  if (!docFile.value) { Notify.create({ type: 'warning', message: 'File required' }); return }
  saving.value = true
  try {
    const fd = new FormData()
    Object.entries(docForm).forEach(([k, v]) => { if (v) fd.append(k, v) })
    const f = docFile.value.type?.startsWith('image/') ? await compressImage(docFile.value) : docFile.value
    fd.append('file', f)
    await api.post('/employees/' + id + '/documents', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    docDialog.value = false; docFile.value = null; Object.assign(docForm, { doc_type: 'national_id', title: '', number: '', expiry_date: '' }); load()
    Notify.create({ type: 'positive', position: 'bottom', message: 'Saved' })
  } catch (err) { Notify.create({ type: 'negative', message: err?.response?.data?.message || 'Failed' }) } finally { saving.value = false }
}
function delDocument (d) { proxy.$delete('employee-documents/' + d.id, load) }
async function downloadDoc (d) {
  try { const res = await api.get('/employee-documents/' + d.id + '/download', { responseType: 'blob' }); const url = URL.createObjectURL(new Blob([res.data], { type: d.file_mime })); const a = document.createElement('a'); a.href = url; a.download = d.file_name || 'document'; a.click(); URL.revokeObjectURL(url) } catch (_) {}
}

// specializations
const specDialog = ref(false)
const specList = ref([])
function openSpec () { specList.value = [...(e.value?.specializations || [])]; specDialog.value = true }
async function saveSpec () {
  saving.value = true
  try { await api.put('/employees/' + id + '/specializations', { specializations: specList.value }); specDialog.value = false; load(); Notify.create({ type: 'positive', position: 'bottom', message: 'Saved' }) }
  catch (_) { Notify.create({ type: 'negative', message: 'Failed' }) } finally { saving.value = false }
}

function printProfile () {
  const p = e.value; if (!p) return
  const esc = (s) => String(s ?? '—').replace(/</g, '&lt;')
  const eduRows = (p.educations || []).map(x => `<tr><td>${esc(x.degree)}</td><td>${esc(x.field)}</td><td>${esc(x.institution)}</td><td>${esc(x.year_from)}–${esc(x.year_to)}</td></tr>`).join('')
  const salRows = (p.salary_history || []).map(x => `<tr><td>${esc(x.period)}</td><td style="text-align:end">${Number(x.net).toLocaleString()} ${esc(x.currency)}</td></tr>`).join('')
  const html = `<!DOCTYPE html><html dir="rtl"><head><meta charset="utf-8"><title>${esc(p.full_name)}</title><style>body{font-family:Arial;margin:24px;color:#1E293B;font-size:12px}h1{color:#123A66;margin:0}.sub{color:#64748B;margin-bottom:10px}h3{color:#175A8C;margin:14px 0 4px}table{border-collapse:collapse;width:100%;font-size:11.5px}th{background:#EEF4FB;text-align:start;padding:4px 6px;border:1px solid #CBD5E1}td{padding:4px 6px;border:1px solid #E2E8F0}</style></head><body>
    <h1>${esc(p.full_name)}</h1><div class="sub">${esc(p.code)} · ${esc(p.designation?.title)} · ${esc(p.department?.name)}</div>
    <h3>مطالعات</h3><table><thead><tr><th>سند</th><th>رشته</th><th>موسسه</th><th>سال</th></tr></thead><tbody>${eduRows}</tbody></table>
    <h3>سابقهٔ معاش</h3><table><thead><tr><th>دوره</th><th>خالص</th></tr></thead><tbody>${salRows}</tbody></table>
    <script>window.onload=()=>window.print()<\/script></body></html>`
  const w = window.open('', '_blank'); if (!w) return; w.document.write(html); w.document.close()
}

onMounted(load)
</script>

<style scoped>
.emp-hero__bar { background: linear-gradient(135deg, #123A66 0%, #175A8C 55%, #1E6BA8 100%); border-radius: 14px; padding: 16px 18px; color: #fff; box-shadow: 0 10px 26px -12px rgba(18, 58, 102, 0.6); }
.emp-hero__head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
.emp-hero__title { display: flex; align-items: center; gap: 12px; }
.emp-hero__ava { background: rgba(255, 255, 255, 0.14); border: 1px solid rgba(255, 255, 255, 0.25); position: relative; }
.emp-hero__cam { position: absolute; bottom: -4px; inset-inline-end: -4px; }
.emp-hero__name { font-size: 20px; font-weight: 800; letter-spacing: -0.3px; }
.emp-hero__meta { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; margin-top: 6px; }
.emp-hero__code { font-size: 12px; font-family: monospace; opacity: 0.85; }
.emp-hero__pill { display: inline-flex; align-items: center; gap: 3px; font-size: 11.5px; padding: 2px 8px; border-radius: 20px; background: rgba(255, 255, 255, 0.14); border: 1px solid rgba(255, 255, 255, 0.2); }
.emp-hero__progress { margin-top: 14px; }
.emp-hero__stats { margin-top: 10px; }
.kpi-tile { border: 1.5px solid #E2E8F0; border-radius: 12px; padding: 12px 14px; background: #fff; height: 100%; }
.kpi-tile__icon { color: var(--q-primary); opacity: 0.85; }
.kpi-tile__val { font-size: 16px; font-weight: 800; margin-top: 4px; color: #1E293B; }
.kpi-tile__lbl { font-size: 11px; color: #94A3B8; margin-top: 1px; }
.dash-nav { display: flex; align-items: center; gap: 4px; background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(10px); border: 1px solid #E2E8F0; border-radius: 999px; padding: 5px 8px; box-shadow: 0 10px 30px -14px rgba(18, 58, 102, 0.35); width: fit-content; max-width: 100%; margin: 0 auto; overflow-x: auto; position: sticky; top: 8px; z-index: 10; }
.dash-pill { display: flex; align-items: center; gap: 6px; border: none; background: transparent; cursor: pointer; padding: 5px 11px; border-radius: 999px; color: #64748B; font-size: 12px; font-weight: 700; transition: all 0.25s ease; white-space: nowrap; }
.dash-pill__orb { width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: #F1F5F9; }
.dash-pill__count { font-size: 10px; background: #E2E8F0; color: #475569; border-radius: 10px; padding: 1px 6px; font-weight: 800; }
.dash-pill--active { background: linear-gradient(135deg, #123A66, #1E6BA8); color: #fff; box-shadow: 0 6px 18px -6px rgba(18, 58, 102, 0.55); }
.dash-pill--active .dash-pill__orb { background: rgba(255, 255, 255, 0.18); color: #fff; }
.dash-pill--active .dash-pill__count { background: rgba(255, 255, 255, 0.2); color: #fff; }
.dash-body { border-radius: 14px; }
@media (max-width: 900px) { .dash-pill__label { display: none; } }
.doc-card { border: 1px solid #E7ECF3; border-radius: 12px; overflow: hidden; background: #fff; height: 100%; }
.doc-card__top { display: flex; align-items: center; justify-content: space-between; padding: 12px; }
.doc-card__body { padding: 8px 12px; }
.doc-card__title { font-weight: 600; font-size: 13px; color: #0F172A; }
.doc-card__actions { display: flex; justify-content: flex-end; padding: 4px 8px; border-top: 1px solid #F1F5F9; }
@media (prefers-color-scheme: dark) { .kpi-tile, .doc-card { background: #1E293B; border-color: #334155; } .kpi-tile__val, .doc-card__title { color: #F1F5F9; } }
</style>
