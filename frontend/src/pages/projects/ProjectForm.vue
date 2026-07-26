<template>
  <q-page>
    <m-backgrounds>
      <div class="row my_radio_less q-pa-sm">
        <div class="col-12">
          <div class="row items-center justify-between">
            <div class="col">
              <m-header icon="domain" controlRoomButton="false"
                :subtitle="editing ? (form.name || $t('Edit')) : $t('NewProjectWizard')" class="q-mt-xs">
                {{ $t('Project') }}
              </m-header>
            </div>
            <div class="col-auto">
              <q-btn flat dense icon="close" color="grey-7" :label="$t('Cancel')" @click="goBack" />
            </div>
          </div>
        </div>

        <!-- Floating dreamy stepper -->
        <div class="col-12 q-mt-sm">
          <div class="wiz-nav">
            <button v-for="(s, i) in steps" :key="s.name" type="button" v-show="stepVisible(i + 1)"
              class="wiz-step" :class="{ 'wiz-step--active': step === i + 1, 'wiz-step--done': step > i + 1 }"
              :disabled="!projectId && i > 0" @click="jump(i + 1)">
              <span class="wiz-step__orb">
                <q-icon :name="step > i + 1 ? 'check' : s.icon" size="17px" />
              </span>
              <span class="wiz-step__label">{{ $t(s.label) }}</span>
            </button>
          </div>
        </div>

        <div class="col-12 q-mt-sm">
          <q-card flat bordered class="my_radio_less bg-white wizard-card">
            <q-card-section>

              <!-- STEP 1 — Essentials -->
              <div v-show="step === 1">
                <div class="wiz-title"><q-icon name="auto_awesome" size="18px" class="q-mr-xs" />{{ $t('Essentials') }}</div>
                <div class="row q-col-gutter-md">
                  <div class="col-12 col-sm-5"><n-name :name="form.name" @update:name="form.name = $event" icon="domain" :label="$t('ProjectName')" autofocus /></div>
                  <div v-if="uiVisible('page.projects.input.name_fa')" class="col-12 col-sm-4"><n-name :name="form.name_fa" @update:name="form.name_fa = $event" icon="translate" :label="$t('ProjectNameFa')" :rules="[]" /></div>
                  <div v-if="uiVisible('page.projects.input.code')" class="col-12 col-sm-3"><n-name :name="form.code" @update:name="form.code = $event" icon="tag" :label="$t('Code')" :rules="[]" /></div>
                  <div class="col-12 col-sm-8">
                    <money-input v-model="form.contract_value" v-model:currency="form.currency" v-model:rate="form.rate"
                      v-model:save-rate="form.save_rate" :label="$t('ProjectBudget')" />
                  </div>
                  <div class="col-12 col-sm-4"><n-name :name="form.location" @update:name="form.location = $event" icon="place" :label="$t('Location')" :rules="[]" /></div>

                  <!-- Map location picker -->
                  <div v-if="uiVisible('page.projects.input.map')" class="col-12">
                    <div class="row items-center q-mb-xs">
                      <div class="text-caption text-grey-7"><q-icon name="pin_drop" size="14px" /> {{ $t('PinOnMap') }}</div>
                      <q-space />
                      <span v-if="mapPoint" class="text-caption text-grey-6">{{ mapPoint.lat }}, {{ mapPoint.lng }}</span>
                      <q-btn v-if="mapPoint" flat dense size="sm" color="grey-7" icon="clear" :label="$t('Clear')" @click="clearPin" />
                    </div>
                    <project-map pickable height="240px" v-model="mapPoint" />
                  </div>
                </div>

                <q-expansion-item class="q-mt-md wiz-more" icon="tune" :label="$t('MoreDetails')" dense header-class="text-primary text-weight-medium">
                  <div class="row q-col-gutter-md q-pt-md q-px-sm">
                    <div v-if="uiVisible('page.projects.input.client_name')" class="col-12 col-sm-6"><n-name :name="form.client_name" @update:name="form.client_name = $event" icon="person" :label="$t('Client')" :rules="[]" /></div>
                    <div v-if="uiVisible('page.projects.input.type')" class="col-6 col-sm-3"><lookup-select v-model="form.type" group="project_type" icon="category" :label="$t('Type')" /></div>
                    <div v-if="uiVisible('page.projects.input.branch')" class="col-6 col-sm-3">
                      <q-select outlined dense color="primary" v-model="form.branch_id" :options="branchOptions" emit-value map-options clearable :label="$t('Branch')" />
                    </div>
                    <div class="col-6 col-sm-3">
                      <shamsi-date v-model="form.start_date" color="primary" :label="$t('StartDate')" />
                    </div>
                    <div class="col-6 col-sm-3">
                      <shamsi-date v-model="form.end_date" color="primary" :label="$t('EndDate')" />
                    </div>
                    <div v-if="uiVisible('page.projects.input.status')" class="col-12 col-sm-6">
                      <lookup-select v-model="form.status" group="project_status" icon="flag" :label="$t('Status')" />
                    </div>
                    <div v-if="uiVisible('page.projects.input.description')" class="col-12"><q-input outlined dense color="primary" type="textarea" autogrow v-model="form.description" :label="$t('Description')" /></div>
                  </div>
                </q-expansion-item>
                <div class="auto-note q-mt-md"><q-icon name="auto_awesome" size="16px" class="q-mr-xs" />{{ $t('ProgressAutoNote') }}</div>
              </div>

              <!-- STEP 2 — Financing -->
              <div v-show="step === 2">
                <div class="wiz-title"><q-icon name="account_balance" size="18px" class="q-mr-xs" />{{ $t('Financing') }}</div>
                <q-form @submit="addCap" class="row q-col-gutter-sm items-end q-mb-md wizard-add">
                  <div class="col-12"><q-toggle v-model="capForm.is_company" :label="$t('CompanyItselfParticipant')" color="secondary" /></div>
                  <div class="col-12 col-sm-4" v-if="!capForm.is_company"><q-select outlined dense color="primary" v-model="capForm.investor_id" :options="investorOptions" emit-value map-options :label="$t('Investor')" /></div>
                  <div class="col-8 col-sm-4"><money-input v-model="capForm.capital" v-model:currency="capForm.currency" v-model:rate="capForm.rate" :allow-save-rate="false" :label="$t('Capital')" /></div>
                  <div class="col-4 col-sm-2"><q-input outlined dense color="primary" type="number" v-model.number="capForm.profit_percent" :label="$t('ProfitPercent')" /></div>
                  <div class="col-12 col-sm-2"><q-btn unelevated color="teal-7" icon="add" type="submit" :label="$t('Add')" class="full-width" /></div>
                </q-form>
                <q-markup-table flat bordered dense class="my_radio_less">
                  <thead class="bg-theme-soft"><tr><th class="text-left">{{ $t('Participant') }}</th><th class="text-right">{{ $t('Capital') }}</th><th class="text-center">{{ $t('ProfitPercent') }}</th><th class="text-right"></th></tr></thead>
                  <tbody>
                    <tr v-if="capRows.length === 0"><td colspan="4" class="text-center text-grey-5 q-py-md">{{ $t('NoRecordFound') }}</td></tr>
                    <tr v-for="r in capRows" :key="r.id"><td>{{ r.participant_name }}</td><td class="text-right">{{ fmt(r.capital) }} {{ r.currency }}</td><td class="text-center">{{ Number(r.profit_percent) }}%</td><td class="text-right"><q-btn size="sm" dense flat round icon="delete" color="negative" @click="delRow('investments/' + r.id, loadCap)" /></td></tr>
                  </tbody>
                </q-markup-table>
              </div>

              <!-- STEP 3 — Work Breakdown -->
              <div v-show="step === 3">
                <div class="wiz-title"><q-icon name="checklist" size="18px" class="q-mr-xs" />{{ $t('WorkBreakdown') }}</div>
                <q-form ref="taskFormRef" @submit="addTask" class="row q-col-gutter-sm items-end q-mb-md wizard-add">
                  <div class="col-12 col-sm-5"><n-name :name="taskForm.title" @update:name="taskForm.title = $event" icon="checklist" :label="$t('Task')" /></div>
                  <div class="col-6 col-sm-3"><lookup-select v-model="taskForm.phase" group="task_phase" icon="timeline" allow-other :label="$t('Phase')" /></div>
                  <div class="col-6 col-sm-2"><n-name :name="taskForm.assignee" @update:name="taskForm.assignee = $event" icon="group" :label="$t('Assignee')" :rules="[]" /></div>
                  <div class="col-12 col-sm-2"><q-btn unelevated color="teal-7" icon="add" type="submit" :label="$t('Add')" class="full-width" /></div>
                </q-form>
                <q-markup-table flat bordered dense class="my_radio_less">
                  <thead class="bg-theme-soft"><tr><th class="text-left">{{ $t('Task') }}</th><th class="text-left">{{ $t('Phase') }}</th><th class="text-left">{{ $t('Assignee') }}</th><th class="text-right"></th></tr></thead>
                  <tbody>
                    <tr v-if="taskRows.length === 0"><td colspan="4" class="text-center text-grey-5 q-py-md">{{ $t('NoRecordFound') }}</td></tr>
                    <tr v-for="t in taskRows" :key="t.id"><td class="text-weight-medium">{{ t.title }}</td><td>{{ lookupLabel('task_phase', t.phase) || t.phase }}</td><td>{{ t.assignee || '—' }}</td><td class="text-right"><q-btn size="sm" dense flat round icon="delete" color="negative" @click="delRow('tasks/' + t.id, loadTasks)" /></td></tr>
                  </tbody>
                </q-markup-table>
              </div>

              <!-- STEP 4 — Site Operations -->
              <div v-show="step === 4">
                <div class="wiz-title"><q-icon name="place" size="18px" class="q-mr-xs" />{{ $t('SiteOperations') }}</div>
                <q-form ref="siteFormRef" @submit="addSite" class="row q-col-gutter-sm items-end q-mb-md wizard-add">
                  <div class="col-12 col-sm-4"><n-name :name="siteForm.name" @update:name="siteForm.name = $event" icon="place" :label="$t('Name')" /></div>
                  <div class="col-6 col-sm-3"><n-name :name="siteForm.location" @update:name="siteForm.location = $event" icon="map" :label="$t('Location')" :rules="[]" /></div>
                  <div class="col-6 col-sm-3">
                    <q-select outlined dense color="primary" v-model="siteForm.in_charge" :options="inchargeOptions"
                      use-input new-value-mode="add-unique" input-debounce="0" @filter="filterIncharge" clearable :label="$t('InCharge')" :hint="$t('InChargeHint')">
                      <template #prepend><q-icon name="engineering" color="primary" /></template>
                      <template #no-option><q-item><q-item-section class="text-grey">{{ $t('TypeAName') }}</q-item-section></q-item></template>
                    </q-select>
                  </div>
                  <div class="col-12 col-sm-2"><q-btn unelevated color="teal-7" icon="add" type="submit" :label="$t('Add')" class="full-width" /></div>
                </q-form>
                <q-markup-table flat bordered dense class="my_radio_less">
                  <thead class="bg-theme-soft"><tr><th class="text-left">{{ $t('Name') }}</th><th class="text-left">{{ $t('Location') }}</th><th class="text-left">{{ $t('InCharge') }}</th><th class="text-right"></th></tr></thead>
                  <tbody>
                    <tr v-if="siteRows.length === 0"><td colspan="4" class="text-center text-grey-5 q-py-md">{{ $t('NoRecordFound') }}</td></tr>
                    <tr v-for="s in siteRows" :key="s.id"><td class="text-weight-medium">{{ s.name }}</td><td>{{ s.location || '—' }}</td><td>{{ s.in_charge || '—' }}</td><td class="text-right"><q-btn size="sm" dense flat round icon="delete" color="negative" @click="delRow('sites/' + s.id, loadSites)" /></td></tr>
                  </tbody>
                </q-markup-table>
              </div>

              <!-- STEP 5 — Plant & Materials -->
              <div v-show="step === 5">
                <div class="wiz-title"><q-icon name="construction" size="18px" class="q-mr-xs" />{{ $t('PlantMaterials') }}</div>
                <div class="text-caption text-weight-bold text-grey-7 q-mb-xs">{{ $t('EquipmentVehicles') }}</div>
                <q-form @submit="addAsset" class="row q-col-gutter-sm items-end q-mb-sm wizard-add">
                  <div class="col-12 col-sm-7">
                    <q-select outlined dense color="primary" v-model="assetForm.asset_id" :options="assetCatalog" emit-value map-options use-input @filter="filterAssets" :label="$t('SelectAsset')">
                      <template v-slot:option="scope">
                        <q-item v-bind="scope.itemProps"><q-item-section><q-item-label>{{ scope.opt.label }}</q-item-label><q-item-label caption>{{ $t('Available') }}: {{ scope.opt.available }} · {{ scope.opt.category }}</q-item-label></q-item-section></q-item>
                      </template>
                    </q-select>
                  </div>
                  <div class="col-6 col-sm-3"><q-input outlined dense color="primary" type="number" min="1" v-model.number="assetForm.quantity" :label="$t('Quantity')" /></div>
                  <div class="col-6 col-sm-2"><q-btn unelevated color="teal-7" icon="add" type="submit" :label="$t('Add')" class="full-width" /></div>
                </q-form>
                <q-markup-table flat bordered dense class="my_radio_less q-mb-md">
                  <thead class="bg-theme-soft"><tr><th class="text-left">{{ $t('Asset') }}</th><th class="text-right">{{ $t('Quantity') }}</th><th class="text-right"></th></tr></thead>
                  <tbody>
                    <tr v-if="assetRows.length === 0"><td colspan="3" class="text-center text-grey-5 q-py-md">{{ $t('NoRecordFound') }}</td></tr>
                    <tr v-for="a in assetRows" :key="a.id"><td class="text-weight-medium">{{ a.asset?.name }}</td><td class="text-right">{{ a.quantity }}</td><td class="text-right"><q-btn size="sm" dense flat round icon="delete" color="negative" @click="delRow('project-assets/' + a.id, loadResources)" /></td></tr>
                  </tbody>
                </q-markup-table>

                <div class="text-caption text-weight-bold text-grey-7 q-mb-xs">{{ $t('Materials') }}</div>
                <q-form ref="materialFormRef" @submit="addMaterial" class="row q-col-gutter-sm items-end q-mb-sm wizard-add">
                  <div class="col-12 col-sm-6"><n-name :name="materialForm.name" @update:name="materialForm.name = $event" icon="grain" :label="$t('Material')" :placeholder="$t('MaterialEg')" /></div>
                  <div class="col-5 col-sm-2"><q-input outlined dense color="primary" type="number" v-model.number="materialForm.quantity" :label="$t('Quantity')" /></div>
                  <div class="col-4 col-sm-2"><lookup-select v-model="materialForm.unit" group="unit" icon="straighten" allow-other :label="$t('Unit')" /></div>
                  <div class="col-3 col-sm-2"><q-btn unelevated color="teal-7" icon="add" type="submit" :label="$t('Add')" class="full-width" /></div>
                </q-form>
                <q-markup-table flat bordered dense class="my_radio_less">
                  <thead class="bg-theme-soft"><tr><th class="text-left">{{ $t('Material') }}</th><th class="text-right">{{ $t('Quantity') }}</th><th class="text-left">{{ $t('Unit') }}</th><th class="text-right"></th></tr></thead>
                  <tbody>
                    <tr v-if="materialRows.length === 0"><td colspan="4" class="text-center text-grey-5 q-py-md">{{ $t('NoRecordFound') }}</td></tr>
                    <tr v-for="m in materialRows" :key="m.id"><td class="text-weight-medium">{{ m.name }}</td><td class="text-right">{{ fmt(m.quantity) }}</td><td>{{ lookupLabel('unit', m.unit) || m.unit || '—' }}</td><td class="text-right"><q-btn size="sm" dense flat round icon="delete" color="negative" @click="delRow('project-materials/' + m.id, loadResources)" /></td></tr>
                  </tbody>
                </q-markup-table>
              </div>

              <!-- STEP 6 — Drawings & Documents -->
              <div v-show="step === 6">
                <div class="wiz-title"><q-icon name="folder" size="18px" class="q-mr-xs" />{{ $t('DrawingsDocs') }}</div>
                <q-form @submit="uploadDocs" class="row q-col-gutter-sm items-end q-mb-md wizard-add">
                  <div class="col-12 col-sm-4">
                    <lookup-select v-model="docForm.category" group="drawing_category" icon="folder" :label="$t('Category')" />
                  </div>
                  <div class="col-12 col-sm-6">
                    <q-file outlined dense color="primary" v-model="docFiles" multiple :label="$t('SelectFilesMulti')"
                      accept=".pdf,.jpg,.jpeg,.png,.dwg,.doc,.docx,.xls,.xlsx,.zip" max-file-size="41943040" @rejected="onFileRejected" use-chips>
                      <template #prepend><q-icon name="attach_file" color="primary" /></template>
                    </q-file>
                  </div>
                  <div class="col-12 col-sm-2">
                    <q-btn unelevated color="teal-7" icon="upload_file" type="submit" :label="$t('Upload')" :loading="uploading" :disable="!docFiles || docFiles.length === 0" class="full-width" />
                  </div>
                </q-form>
                <q-markup-table flat bordered dense class="my_radio_less">
                  <thead class="bg-theme-soft"><tr><th class="text-left">{{ $t('Title') }}</th><th class="text-left">{{ $t('Category') }}</th><th class="text-right">{{ $t('Size') }}</th><th class="text-right"></th></tr></thead>
                  <tbody>
                    <tr v-if="docRows.length === 0"><td colspan="4" class="text-center text-grey-5 q-py-md">{{ $t('NoRecordFound') }}</td></tr>
                    <tr v-for="d in docRows" :key="d.id"><td class="text-weight-medium">{{ d.title }}</td><td>{{ lookupLabel('drawing_category', d.category) || d.category }}</td><td class="text-right">{{ fmtSize(d.size) }}</td><td class="text-right"><q-btn size="sm" dense flat round icon="delete" color="negative" @click="delRow('documents/' + d.id, loadDocs)" /></td></tr>
                  </tbody>
                </q-markup-table>
              </div>

            </q-card-section>
            <q-separator />
            <q-card-actions class="row items-center justify-between q-pa-md">
              <q-btn flat color="grey-7" :label="$t('Back')" icon="chevron_left" @click="prev" :disable="step === 1" />
              <div>
                <q-btn v-if="step !== lastVisibleStep" unelevated color="primary" :label="step === 1 && !projectId ? $t('CreateAndContinue') : $t('Next')" icon-right="chevron_right" :loading="savingCore" @click="next" />
                <q-btn v-else unelevated color="positive" :label="$t('Finish')" icon-right="check" @click="finish" />
              </div>
            </q-card-actions>
          </q-card>
        </div>
      </div>
    </m-backgrounds>
  </q-page>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Notify } from 'quasar'
import { api } from '@/boot/axios'
import { compressImage } from '@/utils/image'
import { useCurrency } from '@/composables/useCurrency'
import { useLookups } from '@/composables/useLookups'
import { useUiConfig } from '@/composables/useUiConfig'

const route = useRoute()
const { loadUiConfig, visible: uiVisible } = useUiConfig()
const { base, loadRates, rateFor } = useCurrency()
const { loadLookups, label: lookupLabel } = useLookups()
const router = useRouter()

const editing = computed(() => !!route.params.id)
const step = ref(1)
// Inline "add row" forms — reset their validation after a successful add so
// the emptied required fields don't flash a "field is required" error.
const taskFormRef = ref(null)
const siteFormRef = ref(null)
const materialFormRef = ref(null)
const projectId = ref(route.params.id || null)
const savingCore = ref(false)
const branchOptions = ref([])
const investorOptions = ref([])

const steps = [
  { name: 'essentials', label: 'Essentials', icon: 'auto_awesome' },
  { name: 'financing', label: 'Financing', icon: 'account_balance' },
  { name: 'wbs', label: 'WorkBreakdown', icon: 'checklist' },
  { name: 'sites', label: 'SiteOperations', icon: 'place' },
  { name: 'plant', label: 'PlantMaterials', icon: 'construction' },
  { name: 'docs', label: 'DrawingsDocs', icon: 'folder' },
]

// Options (type, status, phase, unit, drawing category) all come from the
// Options Registry now via <lookup-select> — nothing hard-coded here.

// Site incharge: pick from employees if any exist, else free-type a name.
const employeeOptions = ref([])
const inchargeOptions = ref([])

const form = reactive({
  code: '', name: '', name_fa: '', client_name: '', location: '', type: 'building',
  contract_value: null, currency: 'AFN', rate: 1, save_rate: false, branch_id: null, lat: null, lng: null,
  start_date: '', end_date: '', status: 'planning', description: '',
})

// Two-way bridge between the map picker and the lat/lng form fields.
const mapPoint = computed({
  get: () => (form.lat != null && form.lng != null ? { lat: form.lat, lng: form.lng } : null),
  set: (v) => { form.lat = v?.lat ?? null; form.lng = v?.lng ?? null }
})
function clearPin () { form.lat = null; form.lng = null }

function fmt (v) { return Number(v || 0).toLocaleString('en-US') }
function fmtSize (b) { b = Number(b || 0); if (b < 1024) return b + ' B'; if (b < 1048576) return (b / 1024).toFixed(1) + ' KB'; return (b / 1048576).toFixed(1) + ' MB' }
function notifyOk () { Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: 'Saved' }) }
function notifyErr (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Failed' }) }
async function delRow (url, cb) { try { await api.delete('/' + url); cb() } catch (e) { notifyErr(e) } }

// When the user ticked "set as today's rate" on the money field, push it as the
// official daily exchange rate for that currency before saving the project.
async function maybeSaveDailyRate () {
  if (!form.save_rate || !form.currency || form.currency === base.value) return
  const r = Number(form.rate || 0)
  if (r <= 0) return
  try {
    await api.post('/exchange-rates', { currency_code: form.currency, rate_to_base: r })
    form.save_rate = false
  } catch (_) { /* non-blocking */ }
}

async function ensureProject () {
  savingCore.value = true
  try {
    await maybeSaveDailyRate()
    const payload = { ...form, start_date: form.start_date || null, end_date: form.end_date || null }
    if (projectId.value) {
      await api.put('/projects/' + projectId.value, payload)
    } else {
      const { data } = await api.post('/projects', payload)
      projectId.value = data.id
      await loadAll()
    }
    return true
  } catch (e) { notifyErr(e); return false } finally { savingCore.value = false }
}

// Control Room can hide wizard tabs; navigation skips the hidden ones.
const tabKeyByNum = { 2: 'page.projects.tab.financing', 3: 'page.projects.tab.wbs', 4: 'page.projects.tab.sites', 5: 'page.projects.tab.plant', 6: 'page.projects.tab.docs' }
function stepVisible (n) { const k = tabKeyByNum[n]; return !k || uiVisible(k) }
const lastVisibleStep = computed(() => { for (let n = 6; n >= 1; n--) if (stepVisible(n)) return n; return 1 })
function nextVisible (from) { for (let n = from + 1; n <= 6; n++) if (stepVisible(n)) return n; return null }
function prevVisible (from) { for (let n = from - 1; n >= 1; n--) if (stepVisible(n)) return n; return null }

async function next () {
  if (step.value === 1) {
    if (!form.name) return Notify.create({ type: 'warning', message: 'Project name is required' })
    if (!(await ensureProject())) return
  }
  const n = nextVisible(step.value)
  if (n) step.value = n
}
function prev () { const p = prevVisible(step.value); if (p) step.value = p }
function jump (n) { if ((projectId.value || n === 1) && stepVisible(n)) step.value = n }
function finish () { router.push('/projects/' + projectId.value) }
function goBack () { router.push('/projects') }

// ── Financing (cap table) ──
const capRows = ref([])
const capForm = reactive({ is_company: false, investor_id: null, capital: null, currency: 'AFN', rate: 1, profit_percent: null })
async function loadCap () { if (!projectId.value) return; try { const { data } = await api.get('/projects/' + projectId.value + '/investments'); capRows.value = data } catch (_) {} }
async function addCap () {
  try {
    // Advisory only — an underfunded General Budget never blocks the client.
    if (capForm.is_company) {
      try {
        const { data: t } = await api.get('/treasury/summary')
        const share = Number(capForm.capital || 0) * (capForm.rate || rateFor(capForm.currency))
        if (share > Number(t.available || 0)) {
          Notify.create({ type: 'warning', timeout: 6000, position: 'top',
            message: 'Company share exceeds the General Budget available balance (' + Number(t.available).toLocaleString() + ' ' + (t.base || '') + ') — recorded anyway.' })
        }
      } catch (_) {}
    }
    await api.post('/projects/' + projectId.value + '/investments', {
      is_company: capForm.is_company, investor_id: capForm.is_company ? null : capForm.investor_id,
      capital: capForm.capital || 0, currency: capForm.currency, rate: capForm.rate || rateFor(capForm.currency), profit_percent: capForm.profit_percent || 0,
    })
    notifyOk(); Object.assign(capForm, { investor_id: null, capital: null, profit_percent: null }); loadCap()
  } catch (e) { notifyErr(e) }
}

// ── Work Breakdown ──
const taskRows = ref([])
const taskForm = reactive({ title: '', phase: 'general', assignee: '' })
async function loadTasks () { if (!projectId.value) return; try { const { data } = await api.get('/projects/' + projectId.value + '/tasks'); taskRows.value = data } catch (_) {} }
async function addTask () {
  try {
    await api.post('/projects/' + projectId.value + '/tasks', { title: taskForm.title, phase: taskForm.phase, assignee: taskForm.assignee, priority: 'medium', status: 'todo', progress: 0 })
    notifyOk(); Object.assign(taskForm, { title: '', assignee: '' }); taskFormRef.value?.resetValidation(); loadTasks()
  } catch (e) { notifyErr(e) }
}

// ── Site Operations ──
const siteRows = ref([])
const siteForm = reactive({ name: '', location: '', in_charge: '' })
async function loadSites () { if (!projectId.value) return; try { const { data } = await api.get('/projects/' + projectId.value); siteRows.value = data.sites || [] } catch (_) {} }
async function addSite () {
  try {
    await api.post('/projects/' + projectId.value + '/sites', { name: siteForm.name, location: siteForm.location, in_charge: siteForm.in_charge, active: true })
    notifyOk(); Object.assign(siteForm, { name: '', location: '', in_charge: '' }); siteFormRef.value?.resetValidation(); loadSites()
  } catch (e) { notifyErr(e) }
}

// ── Plant & Materials ──
const assetRows = ref([])
const materialRows = ref([])
const assetCatalog = ref([])
const assetCatalogAll = ref([])
const assetForm = reactive({ asset_id: null, quantity: 1 })
const materialForm = reactive({ name: '', quantity: null, unit: 'bag' })
async function loadAssetCatalog () {
  try {
    const { data } = await api.get('/assets')
    assetCatalogAll.value = (data || []).map(a => ({ label: a.name + ' (' + a.code + ')', value: a.id, available: a.available, category: a.category }))
    assetCatalog.value = assetCatalogAll.value
  } catch (_) {}
}
function filterAssets (val, update) {
  update(() => {
    const n = (val || '').toLowerCase()
    assetCatalog.value = n ? assetCatalogAll.value.filter(o => o.label.toLowerCase().includes(n)) : assetCatalogAll.value
  })
}
async function loadResources () {
  if (!projectId.value) return
  try { const { data } = await api.get('/projects/' + projectId.value + '/resources'); assetRows.value = data.assets || []; materialRows.value = data.materials || [] } catch (_) {}
}
async function addAsset () {
  if (!assetForm.asset_id) return Notify.create({ type: 'warning', message: 'Pick an asset' })
  try { await api.post('/projects/' + projectId.value + '/assets', { asset_id: assetForm.asset_id, quantity: assetForm.quantity || 1 }); notifyOk(); Object.assign(assetForm, { asset_id: null, quantity: 1 }); loadResources(); loadAssetCatalog() } catch (e) { notifyErr(e) }
}
async function addMaterial () {
  try { await api.post('/projects/' + projectId.value + '/materials', { name: materialForm.name, quantity: materialForm.quantity || 0, unit: materialForm.unit }); notifyOk(); Object.assign(materialForm, { name: '', quantity: null }); materialFormRef.value?.resetValidation(); loadResources() } catch (e) { notifyErr(e) }
}

// ── Drawings & Documents ──
const docRows = ref([])
const docFiles = ref(null)
const docForm = reactive({ category: 'drawing' })
const uploading = ref(false)
async function loadDocs () { if (!projectId.value) return; try { const { data } = await api.get('/projects/' + projectId.value + '/documents'); docRows.value = data } catch (_) {} }
function onFileRejected () { Notify.create({ type: 'negative', message: 'A file was too large (max 20 MB) or of a disallowed type.' }) }
async function uploadDocs () {
  if (!docFiles.value || docFiles.value.length === 0) return
  uploading.value = true
  try {
    for (const file of docFiles.value) {
      const fd = new FormData()
      fd.append('file', await compressImage(file))
      fd.append('title', file.name.replace(/\.[^.]+$/, ''))
      fd.append('category', docForm.category)
      fd.append('version', 1)
      await api.post('/projects/' + projectId.value + '/documents', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    }
    notifyOk(); docFiles.value = null; loadDocs()
  } catch (e) { notifyErr(e) } finally { uploading.value = false }
}

async function loadAll () { await Promise.all([loadCap(), loadTasks(), loadSites(), loadResources(), loadDocs()]) }

async function loadBranches () { try { const { data } = await api.get('/branches'); branchOptions.value = (data || []).map(b => ({ label: b.name, value: b.id })) } catch (_) {} }
async function loadEmployees () {
  try {
    const { data } = await api.get('/employees')
    const list = Array.isArray(data) ? data : (data.data ?? [])
    employeeOptions.value = list.map(e => e.full_name || e.name).filter(Boolean)
    inchargeOptions.value = employeeOptions.value
  } catch (_) {}
}
// Incharge: filter employee names, but keep free-typing possible (add-unique).
function filterIncharge (val, update) {
  update(() => {
    const n = (val || '').toLowerCase()
    inchargeOptions.value = n ? employeeOptions.value.filter(o => String(o).toLowerCase().includes(n)) : employeeOptions.value
  })
}
async function loadInvestors () { try { const { data } = await api.get('/investors'); investorOptions.value = (data || []).map(iv => ({ label: iv.name + ' (' + iv.code + ')', value: iv.id })) } catch (_) {} }
async function prefillCode () {
  try { const { data } = await api.get('/projects-next-code'); if (!form.code) form.code = data.code } catch (_) {}
}

async function loadProjectCore () {
  try {
    const { data } = await api.get('/projects/' + projectId.value)
    Object.assign(form, {
      code: data.code || '', name: data.name, name_fa: data.name_fa || '', client_name: data.client_name || '', location: data.location || '',
      type: data.type || 'building', contract_value: data.contract_value, currency: data.currency || 'AFN', rate: data.rate || 1, branch_id: data.branch_id,
      lat: data.lat ?? null, lng: data.lng ?? null,
      start_date: data.start_date ? data.start_date.slice(0, 10) : '', end_date: data.end_date ? data.end_date.slice(0, 10) : '',
      status: data.status || 'planning', description: data.description || '',
    })
  } catch (e) { notifyErr(e); router.push('/projects') }
}

onMounted(async () => {
  loadBranches(); loadInvestors(); loadAssetCatalog(); loadRates(); loadLookups(); loadEmployees(); loadUiConfig()
  if (editing.value) { await loadProjectCore(); await loadAll() } else { prefillCode() }
})
</script>

<style scoped>
.wizard-card { border-radius: 14px; }
.wiz-title { display: flex; align-items: center; font-size: 15px; font-weight: 800; color: var(--q-primary); margin-bottom: 14px; letter-spacing: -0.2px; }
.wizard-add { background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px; padding: 10px; }
.wiz-more { border: 1px dashed #CBD5E1; border-radius: 10px; }
.auto-note { display: flex; align-items: center; font-size: 12px; color: var(--q-primary); background: color-mix(in srgb, var(--q-primary) 8%, #fff); border-radius: 8px; padding: 8px 10px; }

/* ── Floating dreamy stepper ── */
.wiz-nav {
  display: flex; align-items: center; justify-content: center; gap: 4px;
  background: rgba(255, 255, 255, 0.85);
  backdrop-filter: blur(10px);
  border: 1px solid #E2E8F0;
  border-radius: 999px;
  padding: 6px 10px;
  box-shadow: 0 10px 30px -14px rgba(18, 58, 102, 0.35);
  width: fit-content; max-width: 100%;
  margin: 0 auto;
  overflow-x: auto;
}
.wiz-step {
  display: flex; align-items: center; gap: 7px;
  border: none; background: transparent; cursor: pointer;
  padding: 6px 12px; border-radius: 999px;
  color: #64748B; font-size: 12.5px; font-weight: 700;
  transition: all 0.25s ease;
  white-space: nowrap;
}
.wiz-step:disabled { opacity: 0.45; cursor: not-allowed; }
.wiz-step__orb {
  position: relative; width: 30px; height: 30px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  background: #F1F5F9; color: #64748B;
  transition: all 0.25s ease;
}
.wiz-step--done .wiz-step__orb { background: #DCFCE7; color: #16A34A; }
.wiz-step--active {
  background: linear-gradient(135deg, #123A66, #1E6BA8);
  color: #fff;
  box-shadow: 0 6px 18px -6px rgba(18, 58, 102, 0.55);
}
.wiz-step--active .wiz-step__orb { background: rgba(255, 255, 255, 0.18); color: #fff; }
.wiz-step--active .wiz-step__orb::before,
.wiz-step--active .wiz-step__orb::after {
  content: ''; position: absolute; inset: 0; border-radius: 50%;
  border: 2px solid rgba(255, 255, 255, 0.55);
  animation: wizwave 2.2s ease-out infinite;
}
.wiz-step--active .wiz-step__orb::after { animation-delay: 1.1s; }
@keyframes wizwave {
  0% { transform: scale(1); opacity: 0.9; }
  100% { transform: scale(2); opacity: 0; }
}
@media (max-width: 700px) {
  .wiz-step__label { display: none; }
}
</style>
