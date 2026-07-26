<template>
  <q-page>
    <m-backgrounds>
      <div class="row my_radio_less q-pa-sm">
        <div class="col-12">
          <m-header icon="tune" controlRoomButton="false" :subtitle="$t('OptionsRegistrySub')" class="q-mt-xs">
            {{ $t('OptionsRegistry') }}
          </m-header>
        </div>

        <!-- Module tabs — every select in the system, organised by department -->
        <div class="col-12 q-mt-sm">
          <q-card flat bordered class="my_radio_less bg-white">
            <q-tabs v-model="tab" dense no-caps inline-label align="left" active-color="primary" indicator-color="primary" class="reg-tabs">
              <q-tab v-for="t in moduleTabs" :key="t.key" :name="t.key" :icon="t.icon" :label="$t(t.label)">
                <q-badge v-if="t.key !== 'all'" floating transparent color="grey-6">{{ groupsForTab(t.key).length }}</q-badge>
              </q-tab>
            </q-tabs>
          </q-card>
        </div>

        <div class="col-12 q-mt-sm">
          <div class="row q-col-gutter-md">
            <!-- Groups sidebar -->
            <div class="col-12 col-md-4 col-lg-3">
              <q-card flat bordered class="my_radio_less bg-white reg-side">
                <div class="reg-side__head">
                  <q-icon name="category" size="18px" class="q-mr-xs" />{{ $t('Groups') }}
                  <q-space />
                  <q-btn v-if="$can('lookup-create')" flat dense round icon="add" color="primary" @click="openNewGroup">
                    <q-tooltip>{{ $t('NewGroup') }}</q-tooltip>
                  </q-btn>
                </div>
                <q-input dense outlined class="q-ma-sm" v-model="groupSearch" :placeholder="$t('Search')" clearable>
                  <template #prepend><q-icon name="search" /></template>
                </q-input>
                <q-list separator class="reg-grouplist">
                  <q-item v-for="g in filteredGroups" :key="g" clickable v-ripple
                    :active="g === active" active-class="reg-active" @click="active = g">
                    <q-item-section avatar><q-icon :name="groupIcon(g)" :color="g === active ? 'primary' : 'grey-7'" /></q-item-section>
                    <q-item-section>
                      <q-item-label class="text-weight-medium">{{ $t(groupLabelKey(g)) }}</q-item-label>
                      <q-item-label caption>{{ g }}</q-item-label>
                    </q-item-section>
                    <q-item-section side><q-badge color="grey-4" text-color="grey-8">{{ countFor(g) }}</q-badge></q-item-section>
                  </q-item>
                  <q-item v-if="filteredGroups.length === 0"><q-item-section class="text-grey-5 text-center q-py-md">{{ $t('NoRecordFound') }}</q-item-section></q-item>
                </q-list>
              </q-card>
            </div>

            <!-- Options editor -->
            <div class="col-12 col-md-8 col-lg-9">
              <q-card flat bordered class="my_radio_less bg-white">
                <div class="reg-main__head">
                  <q-icon :name="groupIcon(active)" size="20px" class="q-mr-sm" color="primary" />
                  <div>
                    <div class="text-subtitle1 text-weight-bold">{{ active ? $t(groupLabelKey(active)) : '—' }}</div>
                    <div class="text-caption text-grey-6">{{ $t('OptionsRegistryHint') }}</div>
                  </div>
                  <q-space />
                  <q-toggle v-model="showInactive" :label="$t('ShowInactive')" dense size="sm" color="primary" />
                  <progress-btn v-if="$can('lookup-create') && active" color="teal" icon="add" class="q-ml-sm" @click="openCreate">{{ $t('AddNew') }}</progress-btn>
                </div>
                <q-separator />

                <q-markup-table flat dense class="my_radio_less">
                  <thead class="bg-theme-soft">
                    <tr>
                      <th class="text-left" style="width:44px">#</th>
                      <th class="text-left">{{ $t('English') }}</th>
                      <th class="text-right">{{ $t('Dari') }}</th>
                      <th class="text-left">{{ $t('Code') }}</th>
                      <th class="text-center">{{ $t('Status') }}</th>
                      <th class="text-right">{{ $t('Actions') }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="loading"><td colspan="6" class="text-center q-py-md"><q-spinner color="primary" size="2em" /></td></tr>
                    <tr v-else-if="visibleRows.length === 0"><td colspan="6" class="text-center text-grey-5 q-py-md">{{ $t('NoRecordFound') }}</td></tr>
                    <tr v-for="(r, i) in visibleRows" :key="r.id" :class="{ 'reg-off': !r.active }">
                      <td class="text-grey-6">{{ i + 1 }}</td>
                      <td class="text-weight-medium">{{ r.label_en }}</td>
                      <td class="text-right reg-fa">{{ r.label_fa || '—' }}</td>
                      <td><code class="reg-code">{{ r.code }}</code>
                        <q-icon v-if="r.is_system" name="lock" size="13px" color="grey-5" class="q-ml-xs"><q-tooltip>{{ $t('SystemOption') }}</q-tooltip></q-icon>
                      </td>
                      <td class="text-center">
                        <q-chip dense size="sm" :color="r.active ? 'positive' : 'grey'" text-color="white">{{ r.active ? $t('Active') : $t('Inactive') }}</q-chip>
                      </td>
                      <td class="text-right" style="white-space:nowrap">
                        <q-btn size="sm" dense flat round icon="edit" color="blue-8" v-if="$can('lookup-edit')" @click="openEdit(r)" />
                        <q-btn size="sm" dense flat round :icon="r.active ? 'toggle_on' : 'toggle_off'" :color="r.active ? 'positive' : 'grey'"
                          v-if="$can('lookup-edit')" @click="toggle(r)"><q-tooltip>{{ r.active ? $t('Disable') : $t('Enable') }}</q-tooltip></q-btn>
                        <q-btn size="sm" dense flat round icon="delete" color="negative" v-if="$can('lookup-delete') && !r.is_system" @click="remove(r)" />
                      </td>
                    </tr>
                  </tbody>
                </q-markup-table>
              </q-card>
            </div>
          </div>
        </div>
      </div>
    </m-backgrounds>

    <!-- Add / edit option -->
    <m-modal :showCM="dialog" @update:showCM="dialog = $event" card_style="width: 460px">
      <q-card class="bg-white">
        <n-header icon="tune">{{ form.id ? $t('Edit') : $t('AddNew') }} — {{ active ? $t(groupLabelKey(active)) : '' }}</n-header>
        <q-separator />
        <q-form @submit="save">
          <q-card-section class="row q-col-gutter-sm">
            <div class="col-12"><n-name :name="form.label_en" @update:name="form.label_en = $event" icon="translate" :label="$t('English')" autofocus /></div>
            <div class="col-12"><n-name :name="form.label_fa" @update:name="form.label_fa = $event" icon="translate" :label="$t('Dari')" :rules="[]" input-class="reg-fa-input" /></div>
            <div class="col-6"><q-input outlined dense color="primary" type="number" v-model.number="form.sort_order" :label="$t('SortOrder')" /></div>
            <div class="col-6 flex items-center"><q-toggle v-model="form.active" :label="$t('Active')" color="primary" /></div>
          </q-card-section>
          <q-separator />
          <n-submit :submitting="saving" :label="$t('Save')" />
        </q-form>
      </q-card>
    </m-modal>

    <!-- New group -->
    <m-modal :showCM="groupDialog" @update:showCM="groupDialog = $event" card_style="width: 420px">
      <q-card class="bg-white">
        <n-header icon="category">{{ $t('NewGroup') }}</n-header>
        <q-separator />
        <q-form @submit="createGroup">
          <q-card-section class="row q-col-gutter-sm">
            <div class="col-12"><n-name :name="newGroup.name" @update:name="newGroup.name = $event" icon="tag" :label="$t('GroupKey')" autofocus />
              <div class="text-caption text-grey-6 q-mt-xs">{{ $t('GroupKeyHint') }}</div></div>
            <div class="col-12"><n-name :name="newGroup.label_en" @update:name="newGroup.label_en = $event" icon="translate" :label="$t('FirstOptionEn')" /></div>
            <div class="col-12"><n-name :name="newGroup.label_fa" @update:name="newGroup.label_fa = $event" icon="translate" :label="$t('FirstOptionFa')" :rules="[]" /></div>
          </q-card-section>
          <q-separator />
          <n-submit :submitting="saving" :label="$t('Save')" />
        </q-form>
      </q-card>
    </m-modal>
  </q-page>
</template>

<script setup>
import { ref, reactive, computed, watch, getCurrentInstance, onMounted } from 'vue'
import { Notify } from 'quasar'
import { api } from '@/boot/axios'
import { useLookups } from '@/composables/useLookups'

const { proxy } = getCurrentInstance()
const { loadLookups } = useLookups()

const all = ref({})          // { group: [rows] } including inactive
const groupList = ref([])
const active = ref('')
const loading = ref(false)
const saving = ref(false)
const dialog = ref(false)
const groupDialog = ref(false)
const showInactive = ref(true)
const groupSearch = ref('')

const blank = () => ({ id: null, label_en: '', label_fa: '', sort_order: 0, active: true })
const form = reactive(blank())
const newGroup = reactive({ name: '', label_en: '', label_fa: '' })

// Friendly labels + icons for the known groups; unknown groups fall back gracefully.
const GROUP_META = {
  project_type: ['Type', 'category'], project_status: ['Status', 'flag'], task_phase: ['Phase', 'timeline'],
  priority: ['Priority', 'priority_high'], unit: ['Unit', 'straighten'], drawing_category: ['Category', 'folder'],
  payment_method: ['PaymentMethod', 'payments'], party_type: ['PartyType', 'groups'], worker_trade: ['Trade', 'engineering'],
  fuel_type: ['FuelType', 'local_gas_station'], leave_type: ['LeaveType', 'event_busy'], incident_type: ['IncidentType', 'report_problem'],
  incident_severity: ['Severity', 'warning'], expense_category: ['ExpenseCategory', 'receipt_long'], province: ['Province', 'map'],
  asset_category: ['AssetCategory', 'construction'], asset_condition: ['AssetCondition', 'build_circle'],
  weather: ['Weather', 'wb_sunny'], change_order_reason: ['ChangeOrderReason', 'published_with_changes'],
  gender: ['Gender', 'wc'], marital_status: ['MaritalStatus', 'family_restroom'], employment_type: ['EmploymentType', 'badge'],
}
function groupLabelKey (g) { return GROUP_META[g]?.[0] || humanize(g) }
function groupIcon (g) { return GROUP_META[g]?.[1] || 'label' }
function humanize (g) { return String(g || '').replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()) }

// ── Module tabs: every select in the system, grouped the way the menu is ──
const tab = ref('all')
const moduleTabs = [
  { key: 'all', label: 'All', icon: 'apps' },
  { key: 'projects', label: 'Projects', icon: 'domain' },
  { key: 'finance', label: 'FinanceAndAccounting', icon: 'account_balance_wallet' },
  { key: 'hr', label: 'HRAndPayroll', icon: 'badge' },
  { key: 'procurement', label: 'ProcurementAndAssets', icon: 'local_shipping' },
  { key: 'safety', label: 'SafetyAndQuality', icon: 'health_and_safety' },
  { key: 'general', label: 'Other', icon: 'category' },
]
const TAB_GROUPS = {
  projects: ['project_type', 'project_status', 'task_phase', 'priority', 'unit', 'drawing_category', 'weather', 'change_order_reason'],
  finance: ['payment_method', 'expense_category', 'party_type'],
  hr: ['leave_type', 'worker_trade', 'gender', 'marital_status', 'employment_type'],
  procurement: ['asset_category', 'asset_condition', 'fuel_type'],
  safety: ['incident_type', 'incident_severity'],
}
function groupsForTab (t) {
  if (t === 'all') return groupList.value
  if (t === 'general') {
    const known = Object.values(TAB_GROUPS).flat()
    return groupList.value.filter((g) => !known.includes(g))
  }
  return groupList.value.filter((g) => (TAB_GROUPS[t] || []).includes(g))
}

const filteredGroups = computed(() => {
  let list = groupsForTab(tab.value)
  const q = (groupSearch.value || '').toLowerCase()
  if (q) list = list.filter((g) => g.toLowerCase().includes(q) || proxy.$t(groupLabelKey(g)).toLowerCase().includes(q))
  return list
})

// Keep the selected group inside the active tab.
watch(tab, () => {
  const list = groupsForTab(tab.value)
  if (list.length && !list.includes(active.value)) active.value = list[0]
})
const rowsForActive = computed(() => all.value[active.value] || [])
const visibleRows = computed(() => showInactive.value ? rowsForActive.value : rowsForActive.value.filter((r) => r.active))
function countFor (g) { return (all.value[g] || []).length }

async function load () {
  loading.value = true
  try {
    const { data } = await api.get('/lookups', { params: { all: 1 } })
    all.value = data || {}
    groupList.value = Object.keys(all.value).sort()
    if (!active.value || !groupList.value.includes(active.value)) active.value = groupList.value[0] || ''
  } finally { loading.value = false }
}

function openCreate () {
  const rows = rowsForActive.value
  Object.assign(form, blank(), { sort_order: rows.length })
  dialog.value = true
}
function openEdit (r) {
  Object.assign(form, { id: r.id, label_en: r.label_en, label_fa: r.label_fa || '', sort_order: r.sort_order || 0, active: !!r.active })
  dialog.value = true
}

async function save () {
  saving.value = true
  try {
    const payload = { group: active.value, label_en: form.label_en, label_fa: form.label_fa || null, sort_order: form.sort_order || 0, active: form.active }
    if (form.id) await api.put('/lookups/' + form.id, payload)
    else await api.post('/lookups', payload)
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: 'Saved' })
    dialog.value = false
    await refresh()
  } catch (e) {
    Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Save failed' })
  } finally { saving.value = false }
}

async function toggle (r) {
  try {
    await api.put('/lookups/' + r.id, { group: active.value, label_en: r.label_en, label_fa: r.label_fa, active: !r.active, sort_order: r.sort_order })
    await refresh()
  } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Failed' }) }
}

function remove (r) { proxy.$delete('lookups/' + r.id, refresh) }

function openNewGroup () { Object.assign(newGroup, { name: '', label_en: '', label_fa: '' }); groupDialog.value = true }
async function createGroup () {
  saving.value = true
  try {
    const key = String(newGroup.name || '').trim().toLowerCase().replace(/\s+/g, '_').replace(/[^a-z0-9_]/g, '')
    if (!key) { Notify.create({ type: 'warning', message: 'A group key is required' }); return }
    await api.post('/lookups', { group: key, label_en: newGroup.label_en, label_fa: newGroup.label_fa || null, sort_order: 0 })
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: 'Added' })
    groupDialog.value = false
    await refresh()
    active.value = key
  } catch (e) {
    Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Save failed' })
  } finally { saving.value = false }
}

async function refresh () { await load(); await loadLookups(true) }

onMounted(load)
</script>

<style scoped>
.reg-tabs :deep(.q-tab) { min-height: 44px; }
.reg-side { overflow: hidden; }
.reg-side__head { display: flex; align-items: center; font-weight: 800; color: var(--q-primary); padding: 12px 14px; border-bottom: 1px solid #EEF2F7; }
.reg-grouplist { max-height: 62vh; overflow-y: auto; }
.reg-active { background: color-mix(in srgb, var(--q-primary) 10%, #fff); color: var(--q-primary); }
.reg-main__head { display: flex; align-items: center; padding: 12px 14px; gap: 4px; flex-wrap: wrap; }
.reg-code { background: #F1F5F9; border-radius: 5px; padding: 1px 6px; font-size: 11.5px; color: #475569; }
.reg-fa { font-family: 'Vazirmatn', 'IRANSans', Tahoma, sans-serif; direction: rtl; }
.reg-off { opacity: 0.55; }
:deep(.reg-fa-input) { direction: rtl; text-align: right; }
</style>
