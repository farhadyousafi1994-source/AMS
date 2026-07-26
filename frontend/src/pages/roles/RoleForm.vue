<template>
  <q-page>
    <m-backgrounds>
      <div class="row my_radio_less q-pa-sm">

        <div class="col-12">
          <div class="row items-center justify-between">
            <div class="col">
              <m-header icon="admin_panel_settings" controlRoomButton="false"
                :subtitle="editing ? $t('Edit') : $t('AddNew')" class="q-mt-xs">{{ $t('Role') }}</m-header>
            </div>
            <div class="col-auto">
              <q-btn flat dense icon="arrow_back" color="primary" :label="$t('Back')" @click="goBack" />
            </div>
          </div>
        </div>

        <div class="col-12">
          <q-form @submit="save">

            <!-- VIP header card -->
            <q-card flat class="role-hero q-mb-md">
              <div class="row items-center q-col-gutter-md">
                <div class="col-12 col-md-3">
                  <div class="role-hero__badge" :style="`--rc:${heroColor}`">
                    <q-icon name="admin_panel_settings" size="30px" />
                  </div>
                  <div class="role-hero__title">{{ current.name || $t('RoleName') }}</div>
                  <div class="role-hero__fa" v-if="current.name_fa">{{ current.name_fa }}</div>
                </div>
                <div class="col-12 col-md-5">
                  <div class="row q-col-gutter-sm">
                    <div class="col-6"><n-name :name="current.name" @update:name="current.name = $event" icon="badge" :label="$t('RoleName')" autofocus /></div>
                    <div class="col-6"><n-name :name="current.name_fa" @update:name="current.name_fa = $event" icon="translate" :label="$t('RoleNameFa')" :rules="[]" /></div>
                  </div>
                  <!-- Quick templates -->
                  <div class="row items-center q-gutter-xs q-mt-xs">
                    <span class="text-caption text-white" style="opacity:.85">{{ $t('QuickTemplate') }}:</span>
                    <q-btn v-for="t in templates" :key="t.key" size="sm" dense unelevated
                      color="white" text-color="primary" class="tpl-btn" :label="t.label" @click="applyTemplate(t)" />
                  </div>
                </div>
                <div class="col-12 col-md-4">
                  <div class="row q-col-gutter-sm">
                    <div class="col-4"><div class="hero-stat"><div class="hero-stat__v">{{ current.permissions.length }}</div><div class="hero-stat__l">{{ $t('Permissions') }}</div></div></div>
                    <div class="col-4"><div class="hero-stat"><div class="hero-stat__v">{{ activeModules }}</div><div class="hero-stat__l">{{ $t('Modules') }}</div></div></div>
                    <div class="col-4"><div class="hero-stat"><div class="hero-stat__v">{{ specialCount }}</div><div class="hero-stat__l">{{ $t('SpecialPowers') }}</div></div></div>
                  </div>
                  <div class="row items-center q-gutter-xs q-mt-sm justify-end">
                    <q-btn size="sm" outline color="white" icon="done_all" :label="$t('SelectAll')" @click="selectAll" />
                    <q-btn size="sm" outline color="white" icon="remove_done" :label="$t('Clear')" @click="clearAll" />
                  </div>
                </div>
              </div>
            </q-card>

            <!-- Search -->
            <q-input v-model="filter" :label="$t('FilterModules')" outlined dense clearable color="primary" class="q-mb-sm bg-white" style="border-radius:10px">
              <template #prepend><q-icon name="search" color="primary" /></template>
            </q-input>

            <!-- Module categories -->
            <q-card v-for="cat in filteredCategories" :key="cat.key" flat bordered class="cat-card q-mb-sm">
              <q-expansion-item :default-opened="!!filter || cat.defaultOpen" header-class="cat-head" expand-icon-class="text-grey-6">
                <template #header>
                  <q-item-section avatar style="min-width:44px">
                    <div class="cat-icon" :style="`--cc:${cat.color}`"><q-icon :name="cat.icon" size="20px" /></div>
                  </q-item-section>
                  <q-item-section>
                    <div class="cat-title">{{ $t(cat.label) }}</div>
                    <div class="cat-sub">{{ catSelected(cat) }} / {{ catTotal(cat) }} {{ $t('Permissions') }}</div>
                  </q-item-section>
                  <q-item-section side>
                    <q-toggle :model-value="catSelected(cat) > 0 && catSelected(cat) === catTotal(cat)"
                      :indeterminate-value="'mixed'"
                      :color="cat.color2 || 'primary'" dense
                      @update:model-value="toggleCategory(cat, $event)" @click.stop />
                  </q-item-section>
                </template>

                <div class="q-px-md q-pb-sm">
                  <div class="row items-center perm-grid-head text-caption text-weight-bold text-grey-7">
                    <div class="col-4">{{ $t('Module') }}</div>
                    <div v-for="a in actions" :key="a" class="col text-center">{{ $t(actionLabel[a]) }}</div>
                    <div class="col-2 text-center">{{ $t('All') }}</div>
                  </div>
                  <div v-for="group in cat.groups" :key="group.entity" class="row items-center perm-grid-row">
                    <div class="col-4 entity-label"><q-icon name="chevron_right" size="14px" class="text-grey-4" />{{ entityLabel(group.entity) }}</div>
                    <div v-for="a in actions" :key="a" class="col text-center">
                      <q-checkbox v-if="group.byAction[a]" :model-value="has(group.byAction[a])"
                        :color="cat.color2 || 'primary'" dense @update:model-value="toggle(group.byAction[a], $event)" />
                      <span v-else class="text-grey-3">·</span>
                    </div>
                    <div class="col-2 text-center">
                      <q-checkbox :model-value="groupAllSelected(group)" :indeterminate-value="'mixed'"
                        :color="cat.color2 || 'primary'" dense @update:model-value="toggleGroup(group, $event)" />
                    </div>
                  </div>
                </div>
              </q-expansion-item>
            </q-card>

            <!-- Special powers -->
            <q-card v-if="filteredSpecials.length" flat bordered class="cat-card special-card q-mb-sm">
              <div class="special-head">
                <div class="cat-icon" style="--cc:#B45309"><q-icon name="bolt" size="20px" /></div>
                <div>
                  <div class="cat-title">{{ $t('SpecialPowers') }}</div>
                  <div class="cat-sub">{{ $t('SpecialPowersHint') }}</div>
                </div>
              </div>
              <div class="row q-col-gutter-sm q-px-md q-pb-md">
                <div v-for="sp in filteredSpecials" :key="sp.name" class="col-12 col-sm-6 col-md-4">
                  <div class="special-item" :class="{ 'special-item--on': has(sp.name) }" @click="toggle(sp.name, !has(sp.name))">
                    <q-icon :name="sp.icon" size="20px" :color="has(sp.name) ? 'amber-8' : 'grey-5'" />
                    <div class="special-item__body">
                      <div class="special-item__t">{{ $t(sp.label) }}</div>
                      <div class="special-item__d">{{ $t(sp.desc) }}</div>
                    </div>
                    <q-toggle :model-value="has(sp.name)" color="amber-8" dense @update:model-value="toggle(sp.name, $event)" @click.stop />
                  </div>
                </div>
              </div>
            </q-card>

            <!-- Sticky save banner -->
            <div class="role-save-banner row items-center q-px-md q-py-sm">
              <div class="text-caption text-grey-7">
                <q-icon name="verified_user" size="15px" class="q-mr-xs" />{{ current.permissions.length }} {{ $t('Permissions') }}
              </div>
              <q-space />
              <q-btn flat :label="$t('Cancel')" color="grey-7" icon="close" @click="goBack" />
              <q-btn unelevated :label="$t('Save')" color="primary" icon="save" type="submit" :loading="saving" />
            </div>
          </q-form>
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

const route = useRoute()
const router = useRouter()

const editing = computed(() => !!route.params.id)
const saving = ref(false)
const filter = ref('')
const permissions = ref([])
const current = reactive({ name: '', name_fa: '', permissions: [] })

const actions = ['list', 'create', 'edit', 'show', 'delete']
const actionLabel = { list: 'ActView', create: 'ActCreate', edit: 'ActEdit', show: 'ActShow', delete: 'ActDelete' }

// Module categories mirror the app's menu so the matrix is easy to reason about.
const CATEGORIES = [
  { key: 'projects', label: 'Projects', icon: 'domain', color: '#E0EDF7', color2: 'blue-8', defaultOpen: true,
    entities: ['project', 'site', 'task', 'lift', 'milestone', 'daily-log', 'document', 'change-order'] },
  { key: 'site', label: 'SiteManagement', icon: 'engineering', color: '#FFE8D9', color2: 'deep-orange-7',
    entities: ['purchase-request', 'cash-advance', 'site-invoice', 'purchase-category', 'worker', 'worker-attendance'] },
  { key: 'finance', label: 'FinanceAndAccounting', icon: 'account_balance_wallet', color: '#D7F5EC', color2: 'green-8',
    entities: ['treasury', 'payment-request', 'party', 'party-transaction', 'invoice', 'receipt', 'expense', 'office-expense', 'home-expense', 'expense-budget', 'currency', 'exchange-rate'] },
  { key: 'partners', label: 'PartnersAndContracts', icon: 'handshake', color: '#E5E7FB', color2: 'indigo-7',
    entities: ['investor', 'investment', 'partner', 'subcontractor', 'sub-payment', 'tradesman', 'work-measurement', 'contract', 'contract-milestone', 'contract-payment'] },
  { key: 'procurement', label: 'ProcurementAndAssets', icon: 'local_shipping', color: '#EFE7DF', color2: 'brown-6',
    entities: ['purchase-order', 'supplier', 'stock-item', 'stock-movement', 'asset'] },
  { key: 'hr', label: 'HRAndPayroll', icon: 'badge', color: '#D7F0F0', color2: 'teal-7',
    entities: ['employee', 'department', 'designation', 'attendance', 'payroll', 'leave'] },
  { key: 'safety', label: 'SafetyAndQuality', icon: 'health_and_safety', color: '#FBE0E0', color2: 'red-6',
    entities: ['incident'] },
  { key: 'reports', label: 'Reports', icon: 'assessment', color: '#E5E7FB', color2: 'indigo-7',
    entities: ['report'] },
  { key: 'admin', label: 'Administration', icon: 'shield', color: '#E2E8F0', color2: 'blue-grey-8',
    entities: ['company', 'user', 'role', 'branch', 'lookup', 'ui-setting'] },
  { key: 'system', label: 'System', icon: 'settings_suggest', color: '#E0EDF7', color2: 'blue-8',
    entities: ['dashboard', 'notification', 'backup', 'log', 'trash', 'theme', 'fingerprint', 'language', 'lang-en', 'lang-fa', 'lang-pa'] },
]

// Non entity-action permissions (approvals & scope bypasses) — shown as VIP toggles.
const SPECIALS = [
  { name: 'purchase-approve', label: 'PermPurchaseApprove', desc: 'PermPurchaseApproveD', icon: 'fact_check' },
  { name: 'cash-release', label: 'PermCashRelease', desc: 'PermCashReleaseD', icon: 'payments' },
  { name: 'expense-approve', label: 'PermExpenseApprove', desc: 'PermExpenseApproveD', icon: 'price_check' },
  { name: 'change-order-approve', label: 'PermChangeOrderApprove', desc: 'PermChangeOrderApproveD', icon: 'published_with_changes' },
  { name: 'incident-close', label: 'PermIncidentClose', desc: 'PermIncidentCloseD', icon: 'task_alt' },
  { name: 'payment-approve', label: 'PermPaymentApprove', desc: 'PermPaymentApproveD', icon: 'approval' },
  { name: 'payment-process', label: 'PermPaymentProcess', desc: 'PermPaymentProcessD', icon: 'account_balance' },
  { name: 'all-projects', label: 'PermAllProjects', desc: 'PermAllProjectsD', icon: 'workspaces' },
  { name: 'all-branches', label: 'PermAllBranches', desc: 'PermAllBranchesD', icon: 'store' },
]
const specialNames = SPECIALS.map(s => s.name)

const ENTITY_LABELS = {
  'daily-log': 'Daily Log', 'change-order': 'Change Order', 'purchase-request': 'Purchase Request',
  'cash-advance': 'Cash Advance', 'site-invoice': 'Site Invoice', 'purchase-category': 'Purchase Category',
  'worker-attendance': 'Worker Attendance', 'exchange-rate': 'Exchange Rate', 'office-expense': 'Office Expense',
  'home-expense': 'Home Expense', 'expense-budget': 'Expense Budget', 'party-transaction': 'Party Account Tx',
  'sub-payment': 'Sub Payment', 'work-measurement': 'Work Measurement', 'contract-milestone': 'Contract Milestone',
  'contract-payment': 'Contract Payment', 'purchase-order': 'Purchase Order', 'stock-item': 'Stock Item',
  'stock-movement': 'Stock Movement', 'ui-setting': 'Control Room',
  'lang-en': 'Language · English', 'lang-fa': 'Language · Dari', 'lang-pa': 'Language · Pashto',
  'payment-request': 'Payment Request', 'party': 'Party Account', 'stock': 'Warehouse',
}
function entityLabel (e) { return ENTITY_LABELS[e] || e.replace(/-/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) }

function splitPermission (name) { const i = name.lastIndexOf('-'); return { entity: name.slice(0, i), action: name.slice(i + 1) } }

// entity -> { entity, byAction }
const groupMap = computed(() => {
  const map = new Map()
  for (const p of permissions.value) {
    if (specialNames.includes(p.name)) continue
    const { entity, action } = splitPermission(p.name)
    if (!actions.includes(action)) continue
    if (!map.has(entity)) map.set(entity, { entity, byAction: {} })
    map.get(entity).byAction[action] = p.name
  }
  return map
})

const categories = computed(() => {
  const used = new Set()
  const cats = CATEGORIES.map(cat => {
    const groups = cat.entities.map(e => groupMap.value.get(e)).filter(Boolean)
    groups.forEach(g => used.add(g.entity))
    return { ...cat, groups }
  }).filter(c => c.groups.length)
  // Any entity not mapped to a category → "Other"
  const others = [...groupMap.value.values()].filter(g => !used.has(g.entity))
  if (others.length) cats.push({ key: 'other', label: 'Other', icon: 'category', color: '#E2E8F0', color2: 'grey-8', groups: others })
  return cats
})

const filteredCategories = computed(() => {
  if (!filter.value) return categories.value
  const n = filter.value.toLowerCase()
  return categories.value
    .map(c => ({ ...c, groups: c.groups.filter(g => entityLabel(g.entity).toLowerCase().includes(n) || g.entity.includes(n)) }))
    .filter(c => c.groups.length)
})
const filteredSpecials = computed(() => {
  const avail = SPECIALS.filter(s => permissions.value.some(p => p.name === s.name))
  if (!filter.value) return avail
  const n = filter.value.toLowerCase()
  return avail.filter(s => s.name.includes(n))
})

const activeModules = computed(() => categories.value.filter(c => c.groups.some(g => Object.values(g.byAction).some(p => current.permissions.includes(p)))).length)
const specialCount = computed(() => current.permissions.filter(p => specialNames.includes(p)).length)
const heroColor = computed(() => '#175A8C')

function has (name) { return current.permissions.includes(name) }
function groupPermNames (g) { return actions.map(a => g.byAction[a]).filter(Boolean) }
function groupAllSelected (g) {
  const names = groupPermNames(g); const sel = names.filter(n => has(n)).length
  return sel === 0 ? false : (sel === names.length ? true : 'mixed')
}
function catNames (cat) { return cat.groups.flatMap(groupPermNames) }
function catTotal (cat) { return catNames(cat).length }
function catSelected (cat) { return catNames(cat).filter(n => has(n)).length }

function toggle (name, val) {
  const i = current.permissions.indexOf(name)
  if (val && i === -1) current.permissions.push(name)
  else if (!val && i !== -1) current.permissions.splice(i, 1)
}
function toggleGroup (g, val) {
  const names = groupPermNames(g)
  if (val) { const s = new Set(current.permissions); names.forEach(n => s.add(n)); current.permissions = [...s] }
  else current.permissions = current.permissions.filter(n => !names.includes(n))
}
function toggleCategory (cat, val) {
  const names = catNames(cat)
  if (val) { const s = new Set(current.permissions); names.forEach(n => s.add(n)); current.permissions = [...s] }
  else current.permissions = current.permissions.filter(n => !names.includes(n))
}
function selectAll () { current.permissions = permissions.value.map(p => p.name) }
function clearAll () { current.permissions = [] }

// One-click starting points
const templates = [
  { key: 'viewer', label: 'Viewer', build: () => permissions.value.filter(p => /-list$|-show$/.test(p.name)).map(p => p.name) },
  { key: 'operator', label: 'Operator', build: () => permissions.value.filter(p => /-list$|-show$|-create$|-edit$/.test(p.name)).map(p => p.name) },
  { key: 'manager', label: 'Manager', build: () => permissions.value.map(p => p.name).filter(n => !['user-delete', 'role-delete', 'company-delete', 'backup-list'].includes(n)) },
  { key: 'full', label: 'Full', build: () => permissions.value.map(p => p.name) },
]
function applyTemplate (t) { current.permissions = t.build(); Notify.create({ type: 'info', position: 'bottom', message: t.label }) }

async function loadPermissions () { const { data } = await api.get('/permissions'); permissions.value = data }
async function loadRole () {
  const { data } = await api.get('/roles')
  const row = data.find(r => String(r.id) === String(route.params.id))
  if (!row) { Notify.create({ type: 'negative', message: 'Role not found' }); return router.push('/roles') }
  Object.assign(current, { name: row.name, name_fa: row.name_fa || '', permissions: (row.permissions || []).map(p => p.name) })
}
async function save () {
  if (!current.name) return Notify.create({ type: 'warning', message: 'Name is required' })
  saving.value = true
  try {
    const payload = { name: current.name, name_fa: current.name_fa || null, permissions: current.permissions }
    if (editing.value) await api.put(`/roles/${route.params.id}`, payload)
    else await api.post('/roles', payload)
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: 'Saved successfully' })
    router.push('/roles')
  } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Save failed' }) }
  finally { saving.value = false }
}
function goBack () { router.push('/roles') }

onMounted(async () => { await loadPermissions(); if (editing.value) await loadRole() })
</script>

<style scoped>
.role-hero {
  background: linear-gradient(135deg, #123A66 0%, #0B1626 100%);
  color: #fff; border-radius: 14px; padding: 18px 20px;
}
.role-hero__badge { width: 56px; height: 56px; border-radius: 14px; display: flex; align-items: center; justify-content: center;
  background: rgba(255,255,255,.14); color: #fff; margin-bottom: 8px; }
.role-hero__title { font-size: 20px; font-weight: 800; letter-spacing: -.3px; }
.role-hero__fa { font-size: 13px; opacity: .8; }
.tpl-btn { font-size: 11px; font-weight: 700; border-radius: 6px; }
.hero-stat { background: rgba(255,255,255,.1); border-radius: 10px; padding: 8px 6px; text-align: center; }
.hero-stat__v { font-size: 20px; font-weight: 800; }
.hero-stat__l { font-size: 10px; opacity: .8; }
.cat-card { border-radius: 12px; overflow: hidden; }
:deep(.cat-head) { padding: 8px 12px; }
.cat-icon { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center;
  background: var(--cc); color: #334155; }
.cat-title { font-weight: 700; font-size: 14px; color: #0F172A; }
.cat-sub { font-size: 11px; color: #64748B; }
.perm-grid-head { padding: 6px 4px; border-bottom: 1px solid #E2E8F0; }
.perm-grid-row { padding: 2px 4px; border-bottom: 1px solid #F1F5F9; }
.perm-grid-row:hover { background: #F8FAFC; }
.entity-label { font-size: 12.5px; color: #334155; display: flex; align-items: center; gap: 2px; }
.special-card { border-color: #FCD9A5; }
.special-head { display: flex; align-items: center; gap: 12px; padding: 12px 16px; background: #FFFBEB; }
.special-item { display: flex; align-items: center; gap: 10px; border: 1px solid #E2E8F0; border-radius: 10px; padding: 8px 10px; cursor: pointer; transition: all .15s; height: 100%; }
.special-item:hover { border-color: #FCD9A5; background: #FFFDF6; }
.special-item--on { border-color: #F59E0B; background: #FFFBEB; }
.special-item__body { flex: 1 1 auto; min-width: 0; }
.special-item__t { font-size: 12.5px; font-weight: 700; color: #0F172A; }
.special-item__d { font-size: 10.5px; color: #64748B; line-height: 1.2; }
.role-save-banner { position: sticky; bottom: 0; background: #fff; border: 1px solid #E2E8F0; border-radius: 10px; margin-top: 8px; z-index: 5; gap: 6px; box-shadow: 0 -2px 8px rgba(0,0,0,.04); }
@media (prefers-color-scheme: dark) {
  .cat-title { color: #E2E8F0; } .entity-label { color: #CBD5E1; }
  .role-save-banner { background: #1E293B; border-color: #334155; }
}
</style>
