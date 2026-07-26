<template>
  <q-page class="db-page q-pa-md">
    <!-- Greeting -->
    <div class="row items-center q-mb-md">
      <div>
        <div class="db-hello">{{ companyName }}</div>
        <div class="db-date"><span class="db-live"></span>{{ today }} <span class="db-date-shamsi">· {{ todayShamsi }}</span></div>
      </div>
      <q-space />
      <q-btn outline color="blue-grey-7" icon="tune" :label="$t('Customize')" no-caps class="q-mr-sm" @click="customize = true" />
      <q-btn v-if="$can('report-list')" outline color="primary" icon="assessment" :label="$t('Reports')" to="/reports" no-caps />
    </div>

    <!-- Dashboard customizer — each user picks the widgets they want to see -->
    <q-dialog v-model="customize" position="right">
      <q-card class="db-customize">
        <div class="db-customize__head">
          <q-icon name="tune" size="20px" class="q-mr-sm" />
          <div>
            <div class="text-weight-bold">{{ $t('CustomizeDashboard') }}</div>
            <div class="db-customize__sub">{{ $t('CustomizeDashboardHint') }}</div>
          </div>
          <q-space />
          <q-btn flat round dense icon="close" v-close-popup />
        </div>
        <q-scroll-area style="height: calc(100vh - 150px)">
          <!-- Show / hide -->
          <div class="db-cust-sec">{{ $t('ShowHide') }}</div>
          <q-list padding class="q-py-none">
            <q-item v-for="w in widgetDefs" :key="w.key" tag="label" class="db-cust-item">
              <q-item-section avatar>
                <div class="db-cust-ic" :style="`--wc:${w.color}`"><q-icon :name="w.icon" size="20px" /></div>
              </q-item-section>
              <q-item-section>
                <q-item-label class="text-weight-medium">{{ $t(w.label) }}</q-item-label>
                <q-item-label caption>{{ $t(w.desc) }}</q-item-label>
              </q-item-section>
              <q-item-section side>
                <q-toggle :model-value="widgetOn(w.key)" color="primary" @update:model-value="toggleWidget(w.key)" />
              </q-item-section>
            </q-item>
          </q-list>

          <!-- Order -->
          <div class="db-cust-sec">{{ $t('SortSections') }}</div>
          <div class="q-px-md">
            <div v-for="(b, i) in orderedLayoutBlocks" :key="b.key" class="db-order-row">
              <q-icon :name="b.icon" size="18px" class="text-blue-grey-6 q-mr-sm" />
              <span class="col">{{ $t(b.label) }}</span>
              <q-btn flat dense round size="sm" icon="keyboard_arrow_up" :disable="i === 0" @click="moveBlock(b.key, -1)" />
              <q-btn flat dense round size="sm" icon="keyboard_arrow_down" :disable="i === orderedLayoutBlocks.length - 1" @click="moveBlock(b.key, 1)" />
            </div>
          </div>

          <!-- Current cards -->
          <div class="db-cust-sec">{{ $t('MyCards') }} <span v-if="myCards.length" class="text-grey-5">· {{ myCards.length }}</span></div>
          <div class="q-px-md">
            <div v-if="!myCards.length" class="text-caption text-grey-5 q-pb-sm">{{ $t('NoCardsYet') }}</div>
            <div v-for="(c, i) in myCards" :key="c.id" class="db-order-row">
              <q-icon :name="c.icon" size="18px" class="q-mr-sm" :style="`color:${c.color}`" />
              <span class="col ellipsis">{{ $t(c.label) }}<q-badge v-if="c.kind === 'metric'" color="green-1" text-color="green-9" class="q-ml-xs" :label="$t('Metric')" /></span>
              <q-btn flat dense round size="sm" icon="keyboard_arrow_up" :disable="i === 0" @click="moveCard(c.id, -1)" />
              <q-btn flat dense round size="sm" icon="keyboard_arrow_down" :disable="i === myCards.length - 1" @click="moveCard(c.id, 1)" />
              <q-btn flat dense round size="sm" icon="close" color="negative" @click="removeCard(c.id)" />
            </div>
          </div>

          <!-- Add a card from the whole system -->
          <div class="db-cust-sec">{{ $t('AddCard') }}</div>
          <div class="q-px-md q-pb-md">
            <q-input v-model="cardSearch" dense outlined :placeholder="$t('SearchEverything')" clearable class="q-mb-sm">
              <template #prepend><q-icon name="search" /></template>
            </q-input>
            <q-scroll-area style="height: 220px">
              <div v-for="def in filteredCatalog" :key="def.id" class="db-add-row" @click="addCard(def)">
                <span class="db-add-ic" :style="`--qa:${def.color}`"><q-icon :name="def.icon" size="17px" /></span>
                <span class="col">{{ $t(def.label) }}</span>
                <q-badge v-if="def.kind === 'metric'" color="green-1" text-color="green-9" :label="$t('Metric')" />
                <q-badge v-else color="blue-1" text-color="blue-9" :label="$t('Link')" />
                <q-icon name="add_circle" color="primary" size="20px" class="q-ml-sm" />
              </div>
              <div v-if="!filteredCatalog.length" class="text-caption text-grey-5 q-pa-md text-center">{{ $t('NoRecordFound') }}</div>
            </q-scroll-area>
          </div>
        </q-scroll-area>
        <div class="db-customize__foot">
          <q-btn flat color="grey-7" icon="restart_alt" :label="$t('ResetAll')" no-caps @click="resetWidgets" />
          <q-space />
          <q-btn unelevated color="primary" icon="done" :label="$t('Done')" no-caps v-close-popup />
        </div>
      </q-card>
    </q-dialog>

    <div class="db-blocks">

    <!-- MY CARDS — user-added cards from anywhere in the system -->
    <div class="db-mycards q-mb-md" :style="orderStyle('cards')" v-if="widgetOn('cards') && myCards.length">
      <div class="db-card__title q-mb-sm"><q-icon name="dashboard_customize" size="18px" class="q-mr-xs" />{{ $t('MyCards') }}</div>
      <div class="row q-col-gutter-md">
        <div v-for="c in myCards" :key="c.id" class="col-6 col-sm-4 col-md-3">
          <router-link v-if="c.kind === 'link'" :to="c.to" class="db-mycard" :style="`--qa:${c.color}`">
            <span class="db-mycard__ic"><q-icon :name="c.icon" size="20px" /></span>
            <div class="db-mycard__body"><div class="db-mycard__l">{{ $t(c.label) }}</div><div class="db-mycard__sub">{{ $t('Open') }}</div></div>
            <q-icon name="arrow_forward" size="15px" class="db-mycard__go" />
          </router-link>
          <div v-else class="db-mycard" :style="`--qa:${c.color}`">
            <span class="db-mycard__ic"><q-icon :name="c.icon" size="20px" /></span>
            <div class="db-mycard__body"><div class="db-mycard__v">{{ metricValue(c) }}</div><div class="db-mycard__l">{{ $t(c.label) }}</div></div>
          </div>
        </div>
      </div>
    </div>

    <!-- SITE MAP — compact square, click to expand full -->
    <div class="row q-col-gutter-md q-mb-md" :style="orderStyle('map')" v-if="$can('project-list') && widgetOn('map')">
      <div class="col-12 col-sm-5 col-md-3">
        <div class="db-map" @click="openFullMap">
          <project-map :projects="mappable" :interactive="false" height="100%" class="db-map__frame" />
          <div class="db-map__grad"></div>
          <div class="db-map__top"><q-icon name="map" size="15px" class="q-mr-xs" />{{ $t('SiteMap') }}</div>
          <div class="db-map__foot">
            <span><q-icon name="place" size="13px" /> {{ mappable.length }} {{ $t('Locations') }}</span>
            <span class="db-map__expand"><q-icon name="open_in_full" size="13px" /> {{ $t('TapToExpand') }}</span>
          </div>
        </div>
      </div>
      <div class="col-12 col-sm-7 col-md-9">
        <div class="db-loc-panel">
          <div class="db-loc-panel__head"><q-icon name="pin_drop" size="16px" class="q-mr-xs" />{{ $t('ProjectLocations') }}</div>
          <div v-if="mappable.length === 0" class="text-caption text-grey-5 q-pa-md">{{ $t('NoLocationSet') }}</div>
          <div v-else class="db-loc-strip">
            <button v-for="p in mappable" :key="p.id" type="button" class="db-loc"
              @click="$router.push('/projects/' + p.id)">
              <q-icon name="location_on" size="15px" class="db-loc__pin" />
              <span class="db-loc__name">{{ p.name }}</span>
              <span class="db-loc__addr">{{ p.location }}</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Fullscreen map -->
    <q-dialog v-model="mapFull" maximized transition-show="slide-up" transition-hide="slide-down" @show="onFullShown">
      <q-card class="db-mapfull">
        <div class="db-mapfull__bar">
          <div class="text-subtitle1 text-weight-bold"><q-icon name="map" size="20px" class="q-mr-xs" />{{ $t('SiteMap') }} · {{ mappable.length }}</div>
          <q-space />
          <q-btn flat round dense icon="close" v-close-popup />
        </div>
        <div class="db-mapfull__body">
          <div class="db-mapfull__list">
            <button v-for="p in mappable" :key="p.id" type="button" class="db-loc db-loc--full"
              @click="$router.push('/projects/' + p.id)">
              <q-icon name="location_on" size="16px" class="db-loc__pin" />
              <span class="db-loc__name">{{ p.name }}</span>
              <span class="db-loc__addr">{{ p.location }}</span>
            </button>
          </div>
          <project-map ref="fullMap" :projects="mappable" height="100%" class="db-mapfull__frame" @select="id => $router.push('/projects/' + id)" />
        </div>
      </q-card>
    </q-dialog>

    <!-- QUICK ACTIONS — on top -->
    <div class="db-qa-row" :style="orderStyle('quick')" v-if="widgetOn('quick')">
      <router-link v-for="(a, i) in visibleQuickActions" :key="a.to" :to="a.to"
        class="db-qa" :style="`--qa:${a.color};--qa-tint:${a.tint};animation-delay:${i * 70}ms`">
        <span class="db-qa__icon"><q-icon :name="a.icon" size="22px" /></span>
        <span class="db-qa__txt">
          <b>{{ $t(a.label) }}</b>
          <small>{{ $t(a.sub) }}</small>
        </span>
        <q-icon name="arrow_forward" size="15px" class="db-qa__go" />
      </router-link>
    </div>

    <!-- KPIs with count-up — each card shows only if the role can see it -->
    <div class="row q-col-gutter-md q-mb-md" :style="orderStyle('kpis')" v-if="widgetOn('kpis')">
      <div class="col-6 col-md-3" v-if="$can('treasury-list')"><stat-card icon="savings" :label="$t('GeneralBudget')" :value="fmt(anim.budget)" :suffix="stats.treasury?.base || 'AFN'" color="#16A34A" tint="#DCFCE7" :sub="$t('Available')" sub-icon="check_circle" /></div>
      <div class="col-6 col-md-3" v-if="$can('project-list')"><stat-card icon="domain" :label="$t('ActiveProjects')" :value="Math.round(anim.projects)" color="#175A8C" tint="#E0EDF7" :sub="fmt(anim.contract) + ' ' + $t('ContractValue')" sub-icon="payments" /></div>
      <div class="col-6 col-md-3" v-if="$can('receipt-list')"><stat-card icon="call_received" :label="$t('CollectedThisMonth')" :value="fmt(anim.collected)" color="#7C3AED" tint="#EDE9FE" :sub="$t('Receipts')" sub-icon="receipt_long" /></div>
      <div class="col-6 col-md-3" v-if="$can('invoice-list')"><stat-card icon="schedule" :label="$t('Outstanding')" :value="fmt(anim.outstanding)" color="#D97706" tint="#FEF3C7" :sub="$t('Invoices')" sub-icon="request_quote" /></div>
    </div>

    <div class="row q-col-gutter-md" :style="orderStyle('insights')" v-if="widgetOn('skyline') || widgetOn('activity') || widgetOn('entities')">
      <!-- COMPANY SKYLINE — each project is a building rising with its progress -->
      <div :class="widgetOn('skyline') ? 'col-12 col-lg-8' : 'col-12'" v-if="widgetOn('skyline')">
        <q-card flat bordered class="db-card">
          <q-card-section class="q-pb-none row items-center">
            <div class="db-card__title">
              <q-icon name="location_city" size="18px" class="q-mr-xs" />{{ $t('CompanySkyline') }}
              <q-chip v-if="cityProjects.length" dense size="sm" color="blue-grey-1" text-color="blue-grey-8" class="q-ml-sm">{{ cityProjects.length }}</q-chip>
            </div>
            <q-space />
            <div class="text-caption text-grey-6">{{ $t('SkylineHint') }}</div>
          </q-card-section>
          <q-card-section>
            <div class="sky">
              <div class="sky__scan"></div>
              <div class="sky__row">
                <div v-for="p in cityProjects" :key="p.id" class="twr" @click="$router.push('/projects/' + p.id)">
                  <div class="twr__pct">{{ p.progress }}<small>%</small></div>
                  <div class="twr__frame" :style="`height:${p.h}px`">
                    <div class="twr__built" :class="{ 'twr__built--done': p.progress >= 100 }" :style="`height:${p.progress}%`">
                      <div class="twr__glass"></div>
                    </div>
                    <div class="twr__level" v-if="p.progress > 0 && p.progress < 100" :style="`bottom:${p.progress}%`"></div>
                  </div>
                  <div class="twr__name">{{ p.name }}</div>
                  <div class="twr__val">{{ p.value }}</div>
                  <q-tooltip class="bg-grey-10">{{ p.name }} — {{ p.progress }}% · {{ p.status }}</q-tooltip>
                </div>
                <div v-if="cityProjects.length === 0" class="text-grey-5 q-pa-lg">{{ $t('NoRecordFound') }}</div>
              </div>
              <div class="sky__base"></div>
            </div>
          </q-card-section>
        </q-card>
      </div>

      <!-- LIVE ACTIVITY + entity chips -->
      <div v-if="widgetOn('activity') || widgetOn('entities')" :class="widgetOn('skyline') ? 'col-12 col-lg-4' : 'col-12'">
        <q-card v-if="widgetOn('activity')" flat bordered class="db-card q-mb-md">
          <q-card-section class="q-pb-xs row items-center">
            <div class="db-card__title"><q-icon name="bolt" size="18px" class="q-mr-xs" />{{ $t('LiveActivity') }}</div>
            <q-space /><span class="db-live"></span>
          </q-card-section>
          <q-card-section class="q-pt-none db-feed">
            <div v-if="(stats.recent_activity || []).length === 0" class="text-caption text-grey-5 q-py-md text-center">{{ $t('NoRecordFound') }}</div>
            <div v-for="a in stats.recent_activity" :key="a.id" class="db-feed__item">
              <span class="db-feed__dot" :class="'db-feed__dot--' + a.action"></span>
              <div>
                <div class="db-feed__txt">{{ a.description }}</div>
                <div class="db-feed__meta">{{ a.user?.name || '—' }} · {{ a.created_at_human }}</div>
              </div>
            </div>
          </q-card-section>
        </q-card>

        <div class="row q-col-gutter-sm" v-if="widgetOn('entities')">
          <div class="col-4" v-for="e in entities" :key="e.label">
            <router-link :to="e.to" class="db-ent" :style="`--qa:${e.color}`">
              <q-icon :name="e.icon" size="19px" />
              <div class="db-ent__num">{{ e.val }}</div>
              <div class="db-ent__lbl">{{ $t(e.label) }}</div>
            </router-link>
          </div>
        </div>
      </div>
    </div>

    </div><!-- /db-blocks -->
  </q-page>
</template>

<script setup>
import { ref, reactive, computed, getCurrentInstance, onMounted, onUnmounted } from 'vue'
import { api } from '@/boot/axios'
import { shamsiDate } from '@/utils/date'
import { menus } from '@/layouts/menus.js'

const { proxy } = getCurrentInstance()
const can = (p) => (proxy?.$can ? proxy.$can(p) : true)

// ── Per-user dashboard customization ──────────────────────────────────────
// Each person builds their own dashboard: show/hide widgets, sort the sections,
// and add cards drawn from anywhere in the system. Remembered on this device.
const widgetDefs = [
  { key: 'map', label: 'WdgSiteMap', desc: 'WdgSiteMapD', icon: 'map', color: '#0D9488' },
  { key: 'quick', label: 'WdgQuickActions', desc: 'WdgQuickActionsD', icon: 'bolt', color: '#175A8C' },
  { key: 'kpis', label: 'WdgKpis', desc: 'WdgKpisD', icon: 'insights', color: '#16A34A' },
  { key: 'skyline', label: 'WdgSkyline', desc: 'WdgSkylineD', icon: 'location_city', color: '#7C3AED' },
  { key: 'activity', label: 'WdgActivity', desc: 'WdgActivityD', icon: 'history', color: '#D97706' },
  { key: 'entities', label: 'WdgDirectory', desc: 'WdgDirectoryD', icon: 'grid_view', color: '#DC2626' },
  { key: 'cards', label: 'WdgMyCards', desc: 'WdgMyCardsD', icon: 'dashboard_customize', color: '#0284C7' },
]
const customize = ref(false)

// Hidden widgets (only hidden ones stored; everything is on by default).
const hiddenWidgets = ref(new Set())
try { hiddenWidgets.value = new Set(JSON.parse(localStorage.getItem('dash_hidden') || '[]')) } catch (_) {}
function persistWidgets () { localStorage.setItem('dash_hidden', JSON.stringify([...hiddenWidgets.value])) }
function widgetOn (key) { return !hiddenWidgets.value.has(key) }
function toggleWidget (key) {
  const s = new Set(hiddenWidgets.value)
  if (s.has(key)) s.delete(key); else s.add(key)
  hiddenWidgets.value = s
  persistWidgets()
}

// Section order (CSS flex order) — sort the blocks up/down.
const DEFAULT_LAYOUT = ['cards', 'map', 'quick', 'kpis', 'insights']
const layout = ref([...DEFAULT_LAYOUT])
try {
  const saved = JSON.parse(localStorage.getItem('dash_layout') || 'null')
  if (Array.isArray(saved) && saved.length) {
    layout.value = [...saved, ...DEFAULT_LAYOUT.filter(k => !saved.includes(k))]
  }
} catch (_) {}
function persistLayout () { localStorage.setItem('dash_layout', JSON.stringify(layout.value)) }
function orderStyle (key) { const i = layout.value.indexOf(key); return `order:${i === -1 ? 99 : i}` }
const layoutBlocks = [
  { key: 'cards', label: 'WdgMyCards', icon: 'dashboard_customize' },
  { key: 'map', label: 'WdgSiteMap', icon: 'map' },
  { key: 'quick', label: 'WdgQuickActions', icon: 'bolt' },
  { key: 'kpis', label: 'WdgKpis', icon: 'insights' },
  { key: 'insights', label: 'WdgInsights', icon: 'location_city' },
]
const orderedLayoutBlocks = computed(() => layout.value.map(k => layoutBlocks.find(b => b.key === k)).filter(Boolean))
function moveBlock (key, dir) {
  const i = layout.value.indexOf(key); const j = i + dir
  if (i === -1 || j < 0 || j >= layout.value.length) return
  const arr = [...layout.value];[arr[i], arr[j]] = [arr[j], arr[i]]; layout.value = arr; persistLayout()
}

// ── Cards drawn from the whole system (metrics + quick-links to any page) ──
const myCards = ref([])
try { myCards.value = JSON.parse(localStorage.getItem('dash_cards') || '[]') } catch (_) {}
function persistCards () { localStorage.setItem('dash_cards', JSON.stringify(myCards.value)) }

const METRIC_CARDS = [
  { id: 'm_budget', kind: 'metric', metric: 'budget', icon: 'savings', label: 'GeneralBudget', color: '#16A34A', perm: 'treasury-list' },
  { id: 'm_projects', kind: 'metric', metric: 'projects', icon: 'domain', label: 'ActiveProjects', color: '#175A8C', perm: 'project-list' },
  { id: 'm_collected', kind: 'metric', metric: 'collected', icon: 'call_received', label: 'CollectedThisMonth', color: '#7C3AED', perm: 'receipt-list' },
  { id: 'm_outstanding', kind: 'metric', metric: 'outstanding', icon: 'schedule', label: 'Outstanding', color: '#D97706', perm: 'invoice-list' },
  { id: 'm_employees', kind: 'metric', metric: 'employees', icon: 'groups', label: 'Employees', color: '#175A8C', perm: 'employee-list' },
  { id: 'm_equipment', kind: 'metric', metric: 'equipment', icon: 'construction', label: 'Equipment', color: '#0D9488', perm: 'asset-list' },
  { id: 'm_suppliers', kind: 'metric', metric: 'suppliers', icon: 'local_shipping', label: 'Suppliers', color: '#D97706', perm: 'supplier-list' },
]
function metricValue (c) {
  const m = {
    budget: fmt(anim.budget), projects: Math.round(anim.projects),
    collected: fmt(anim.collected), outstanding: fmt(anim.outstanding),
    employees: stats.value.total_employees ?? 0, equipment: stats.value.total_equipment ?? 0,
    suppliers: stats.value.total_suppliers ?? 0,
  }
  return m[c.metric] ?? 0
}
// Quick-link cards for every page in the menu the user may see.
const linkCards = computed(() => {
  const out = []
  for (const m of menus) {
    const subs = m.is_sub && m.is_sub.length ? m.is_sub : [m]
    for (const s of subs) {
      if (!s.url || s.url === '/' || s.url === '') continue
      if (s.permission && !can(s.permission)) continue
      out.push({ id: 'l_' + s.url, kind: 'link', to: s.url, icon: s.icon || 'chevron_right', label: s.name, color: '#0284C7' })
    }
  }
  return out
})
const cardCatalog = computed(() => {
  const added = new Set(myCards.value.map(c => c.id))
  const metrics = METRIC_CARDS.filter(c => (!c.perm || can(c.perm)) && !added.has(c.id))
  const links = linkCards.value.filter(c => !added.has(c.id))
  return [...metrics, ...links]
})
const cardSearch = ref('')
const filteredCatalog = computed(() => {
  const q = (cardSearch.value || '').toLowerCase()
  const t = (k) => (proxy?.$t ? proxy.$t(k) : k)
  let list = cardCatalog.value
  if (q) list = list.filter(c => (t(c.label) || '').toLowerCase().includes(q) || c.label.toLowerCase().includes(q))
  return list.slice(0, 50)
})
function addCard (def) {
  if (myCards.value.some(c => c.id === def.id)) return
  myCards.value = [...myCards.value, { ...def }]; persistCards()
  if (hiddenWidgets.value.has('cards')) toggleWidget('cards') // reveal the block
}
function removeCard (id) { myCards.value = myCards.value.filter(c => c.id !== id); persistCards() }
function moveCard (id, dir) {
  const i = myCards.value.indexOf(myCards.value.find(c => c.id === id)); const j = i + dir
  if (i === -1 || j < 0 || j >= myCards.value.length) return
  const arr = [...myCards.value];[arr[i], arr[j]] = [arr[j], arr[i]]; myCards.value = arr; persistCards()
}

function resetWidgets () {
  hiddenWidgets.value = new Set(); persistWidgets()
  layout.value = [...DEFAULT_LAYOUT]; persistLayout()
}

const stats = ref({})
const projects = ref([])
const companyName = ref('Aria Herat Mohandes Zada')

// ── Site map (all projects with coordinates, plotted together) ──
const mapFull = ref(false)
const fullMap = ref(null)
const mappable = computed(() => projects.value.filter(p => p.lat != null && p.lng != null && p.status !== 'cancelled'))
function openFullMap () { mapFull.value = true }
function onFullShown () { setTimeout(() => fullMap.value?.invalidate(), 200) }
const today = new Date().toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })
// Home date stays Gregorian (میلادی) primary, with the Afghan solar date beside it.
const todayShamsi = shamsiDate(new Date())

// Each quick action / entity carries the permission that reveals it.
const quickActions = [
  { to: '/projects/create', icon: 'add_business', label: 'NewProject', sub: 'Projects', color: '#175A8C', tint: '#E0EDF7', perm: 'project-create' },
  { to: '/projects', icon: 'assignment', label: 'SiteDiary', sub: 'DailyLogs', color: '#0D9488', tint: '#CCFBF1', perm: 'project-list' },
  { to: '/finance/treasury', icon: 'savings', label: 'GeneralBudget', sub: 'Ledger', color: '#16A34A', tint: '#DCFCE7', perm: 'treasury-list' },
  { to: '/accounts', icon: 'account_balance_wallet', label: 'PartyAccounts', sub: 'Statement', color: '#D97706', tint: '#FEF3C7', perm: 'party-list' },
  { to: '/hr/attendance', icon: 'event_available', label: 'Attendance', sub: 'Employees', color: '#7C3AED', tint: '#EDE9FE', perm: 'attendance-list' },
  { to: '/procurement/stock', icon: 'inventory', label: 'Warehouse', sub: 'Movements', color: '#DC2626', tint: '#FEE2E2', perm: 'stock-item-list' },
]
const visibleQuickActions = computed(() => quickActions.filter(a => can(a.perm)))

const entities = computed(() => [
  { label: 'Employees', val: stats.value.total_employees ?? 0, icon: 'groups', color: '#175A8C', to: '/hr/employees', perm: 'employee-list' },
  { label: 'Equipment', val: stats.value.total_equipment ?? 0, icon: 'construction', color: '#0D9488', to: '/assets', perm: 'asset-list' },
  { label: 'Suppliers', val: stats.value.total_suppliers ?? 0, icon: 'local_shipping', color: '#D97706', to: '/procurement/suppliers', perm: 'supplier-list' },
].filter(e => can(e.perm)))

const cityProjects = computed(() => {
  // Show every active project; the row scrolls horizontally when they overflow.
  const list = projects.value.filter(p => p.status !== 'cancelled')
    .sort((a, b) => Number(b.progress || 0) - Number(a.progress || 0))
  const maxV = Math.max(...list.map(p => Number(p.contract_value || 0)), 1)
  const compact = new Intl.NumberFormat('en-US', { notation: 'compact', maximumFractionDigits: 1 })
  return list.map(p => ({
    id: p.id, name: p.name, progress: Number(p.progress || 0), status: p.status,
    // tower height scales with contract value (96px floor, 185px cap)
    h: Math.round(96 + 89 * Math.sqrt(Number(p.contract_value || 0) / maxV)),
    value: compact.format(Number(p.contract_value || 0)) + ' ' + (p.currency || ''),
  }))
})

function fmt (v) { return Number(v || 0).toLocaleString('en-US', { maximumFractionDigits: 0 }) }

// Count-up animation (game feel): numbers roll up to the live values.
const anim = reactive({ budget: 0, projects: 0, contract: 0, collected: 0, outstanding: 0 })
let raf = null
function countUp (targets, ms = 1200) {
  const start = performance.now()
  const from = { ...anim }
  const step = (now) => {
    const t = Math.min(1, (now - start) / ms)
    const e = 1 - Math.pow(1 - t, 3)
    for (const k of Object.keys(targets)) anim[k] = from[k] + (targets[k] - from[k]) * e
    if (t < 1) raf = requestAnimationFrame(step)
  }
  raf = requestAnimationFrame(step)
}

async function load () {
  try {
    const { data } = await api.get('/dashboard_data')
    stats.value = data
    countUp({
      budget: Number(data.treasury?.available || 0),
      projects: Number(data.active_projects || 0),
      contract: Number(data.contract_value_total || 0),
      collected: Number(data.collected_month || 0),
      outstanding: Number(data.outstanding_balance || 0),
    })
  } catch (_) {}
  try { const { data } = await api.get('/projects'); projects.value = data || [] } catch (_) {}
  try { const { data } = await api.get('/user'); companyName.value = data?.company?.name_en || companyName.value } catch (_) {}
}

onMounted(load)
onUnmounted(() => { if (raf) cancelAnimationFrame(raf) })
</script>

<style scoped>
.db-page { background: #F0F4F8; }
.db-hello { font-size: 20px; font-weight: 800; letter-spacing: -0.4px; color: #0F172A; }
.db-date { font-size: 12px; color: #64748B; display: flex; align-items: center; gap: 6px; }
.db-live { width: 8px; height: 8px; border-radius: 50%; background: #22C55E; animation: dblive 2s infinite; display: inline-block; }
@keyframes dblive { 0% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.5); } 70% { box-shadow: 0 0 0 7px rgba(34, 197, 94, 0); } 100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); } }

/* Quick actions — top, staggered entrance, shine sweep on hover */
.db-qa-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 16px; }
.db-qa {
  position: relative; display: flex; align-items: center; gap: 10px;
  background: #fff; border: 1.5px solid #E7ECF3; border-radius: 14px;
  padding: 12px 14px; text-decoration: none; overflow: hidden;
  animation: qain 0.5s both; transition: all 0.22s ease;
}
@keyframes qain { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }
.db-qa::after {
  content: ''; position: absolute; top: 0; bottom: 0; width: 40px;
  background: linear-gradient(100deg, transparent, rgba(255, 255, 255, 0.85), transparent);
  inset-inline-start: -60px; transform: skewX(-20deg);
}
.db-qa:hover { transform: translateY(-3px); border-color: var(--qa); box-shadow: 0 14px 26px -18px var(--qa); }
.db-qa:hover::after { animation: qashine 0.7s ease; }
@keyframes qashine { to { inset-inline-start: 120%; } }
.db-qa__icon {
  width: 42px; height: 42px; border-radius: 12px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  background: var(--qa-tint); color: var(--qa); transition: transform 0.2s ease;
}
.db-qa:hover .db-qa__icon { transform: scale(1.12) rotate(-6deg); }
.db-qa__txt b { display: block; font-size: 13px; color: #0F172A; }
.db-qa__txt small { font-size: 10.5px; color: #94A3B8; }
.db-qa__go { margin-inline-start: auto; color: var(--qa); opacity: 0; transition: all 0.2s ease; }
.db-qa:hover .db-qa__go { opacity: 1; transform: translateX(3px); }

/* Cards */
.db-card { border-radius: 16px; }
.db-card__title { font-size: 14px; font-weight: 800; color: #175A8C; display: flex; align-items: center; }

/* Site map — compact square that expands full-screen */
.db-map {
  position: relative; aspect-ratio: 1 / 1; border-radius: 16px; overflow: hidden;
  border: 1px solid #E2E8F0; cursor: pointer; background: #EDF4FB;
  box-shadow: 0 10px 24px -16px rgba(18, 58, 102, 0.5);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.db-map:hover { transform: translateY(-2px); box-shadow: 0 16px 30px -18px rgba(18, 58, 102, 0.6); }
.db-map__frame { width: 100%; height: 100%; border: 0; pointer-events: none; filter: saturate(1.05); }
.db-map__grad { position: absolute; inset: 0; pointer-events: none; background: linear-gradient(180deg, rgba(18,58,102,0.35) 0%, transparent 30%, transparent 60%, rgba(18,58,102,0.5) 100%); }
.db-map__top { position: absolute; top: 10px; left: 12px; color: #fff; font-weight: 800; font-size: 13px; display: flex; align-items: center; text-shadow: 0 1px 3px rgba(0,0,0,0.4); }
.db-map__foot { position: absolute; bottom: 9px; left: 12px; right: 12px; display: flex; justify-content: space-between; align-items: center; color: #fff; font-size: 11px; font-weight: 700; text-shadow: 0 1px 3px rgba(0,0,0,0.4); }
.db-map__expand { background: rgba(255,255,255,0.22); backdrop-filter: blur(4px); border-radius: 20px; padding: 2px 8px; }
.db-loc-panel { height: 100%; border: 1px solid #E7ECF3; border-radius: 16px; background: #fff; padding: 12px 14px; display: flex; flex-direction: column; }
.db-loc-panel__head { font-size: 13px; font-weight: 800; color: #1E293B; display: flex; align-items: center; margin-bottom: 8px; }
.db-loc-strip { display: flex; flex-wrap: wrap; gap: 8px; overflow-y: auto; align-content: flex-start; }
.db-loc {
  display: flex; flex-direction: column; align-items: flex-start; gap: 1px;
  border: 1px solid #E7ECF3; border-radius: 12px; background: #F8FAFC; cursor: pointer;
  padding: 8px 12px; min-width: 150px; max-width: 220px; text-align: start; transition: all 0.18s ease; position: relative;
}
.db-loc:hover { border-color: var(--q-primary); transform: translateY(-2px); }
.db-loc--on { border-color: var(--q-primary); background: color-mix(in srgb, var(--q-primary) 8%, #fff); box-shadow: 0 8px 16px -12px rgba(18,58,102,0.5); }
.db-loc__pin { color: var(--q-primary); position: absolute; top: 8px; right: 8px; }
.db-loc__name { font-size: 12.5px; font-weight: 800; color: #1E293B; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 170px; }
.db-loc__addr { font-size: 11px; color: #64748B; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 190px; }
.db-mapfull { display: flex; flex-direction: column; height: 100%; }
.db-mapfull__bar { display: flex; align-items: center; padding: 10px 16px; border-bottom: 1px solid #E7ECF3; }
.db-mapfull__body { flex: 1; display: flex; min-height: 0; }
.db-mapfull__list { width: 280px; overflow-y: auto; padding: 12px; display: flex; flex-direction: column; gap: 8px; border-right: 1px solid #E7ECF3; }
.db-loc--full { min-width: 0; max-width: none; width: 100%; }
.db-mapfull__frame { flex: 1; border: 0; }
@media (max-width: 599px) {
  .db-mapfull__body { flex-direction: column; }
  .db-mapfull__list { width: 100%; max-height: 40vh; border-right: none; border-bottom: 1px solid #E7ECF3; flex-direction: row; flex-wrap: nowrap; overflow-x: auto; }
  .db-loc--full { width: auto; min-width: 160px; }
}

/* Company skyline — executive portfolio panel */
.sky {
  position: relative; border-radius: 14px; padding: 26px 18px 0; overflow: hidden;
  background:
    repeating-linear-gradient(0deg, rgba(23, 90, 140, 0.045) 0 1px, transparent 1px 34px),
    repeating-linear-gradient(90deg, rgba(23, 90, 140, 0.045) 0 1px, transparent 1px 34px),
    linear-gradient(180deg, #FBFDFF 0%, #EDF4FB 100%);
  border: 1px solid #E2E8F0;
}
.sky__scan {
  position: absolute; top: 0; bottom: 0; width: 120px; pointer-events: none;
  background: linear-gradient(100deg, transparent, rgba(200, 134, 45, 0.07), transparent);
  animation: skyscan 9s linear infinite;
}
@keyframes skyscan { from { inset-inline-start: -140px; } to { inset-inline-start: 110%; } }
.sky__row {
  display: flex; align-items: flex-end; gap: 18px; min-height: 235px; padding: 0 10px 10px;
  /* scroll horizontally instead of squeezing when there are many projects */
  overflow-x: auto; overflow-y: hidden; scroll-snap-type: x proximity;
  scrollbar-width: thin;
}
.sky__row::-webkit-scrollbar { height: 7px; }
.sky__row::-webkit-scrollbar-thumb { background: rgba(23, 90, 140, 0.3); border-radius: 6px; }
.twr { text-align: center; cursor: pointer; flex: 0 0 auto; width: 84px; position: relative; transition: transform 0.25s ease; scroll-snap-align: start; }
.twr:hover { transform: translateY(-3px); }
.twr__pct { font-size: 15px; font-weight: 800; color: #175A8C; letter-spacing: -0.3px; font-variant-numeric: tabular-nums; margin-bottom: 5px; }
.twr__pct small { font-size: 10px; font-weight: 700; opacity: 0.7; }
.twr__frame {
  width: 58px; margin: 0 auto; position: relative;
  border: 1px dashed rgba(100, 116, 139, 0.45); border-bottom: none;
  background: rgba(23, 90, 140, 0.03);
}
.twr__built {
  position: absolute; bottom: 0; inset-inline: -1px; overflow: hidden;
  background: linear-gradient(180deg, #7FB3DF 0%, #2E6DA4 60%, #1C4F80 100%);
  border: 1px solid rgba(255, 255, 255, 0.55); border-bottom: none;
  box-shadow: 0 14px 24px -14px rgba(18, 58, 102, 0.5);
  animation: twrise 1.4s cubic-bezier(0.25, 0.9, 0.3, 1) both;
  transform-origin: bottom; transition: filter 0.25s ease;
}
@keyframes twrise { from { transform: scaleY(0); } }
.twr:hover .twr__built { filter: brightness(1.2) saturate(1.1); }
.twr__built--done { background: linear-gradient(180deg, #EDC15E 0%, #B07A24 100%); }
.twr__glass {
  position: absolute; inset: 5px 7px;
  background-image:
    repeating-linear-gradient(0deg, rgba(255, 255, 255, 0.38) 0 2px, transparent 2px 11px),
    repeating-linear-gradient(90deg, rgba(255, 255, 255, 0.22) 0 2px, transparent 2px 13px);
}
.twr__level {
  position: absolute; inset-inline: -7px; height: 2px;
  background: #C8862D; box-shadow: 0 0 8px rgba(200, 134, 45, 0.75);
  animation: lvl 3s ease-in-out infinite;
}
@keyframes lvl { 50% { opacity: 0.45; } }
.twr__name { font-size: 10.5px; font-weight: 600; color: #334155; margin-top: 8px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.twr__val { font-size: 10px; color: #A16207; letter-spacing: 0.08em; font-variant-numeric: tabular-nums; }
.sky__base { position: relative; height: 14px; margin: 0 -18px; border-top: 1.5px solid rgba(200, 134, 45, 0.5); background: linear-gradient(180deg, rgba(200, 134, 45, 0.07), transparent); }

/* Feed + entity chips */
.db-feed { max-height: 300px; overflow-y: auto; }
.db-feed__item { display: flex; gap: 8px; padding: 6px 0; border-bottom: 1px dashed #F1F5F9; }
.db-feed__dot { width: 8px; height: 8px; border-radius: 50%; margin-top: 5px; flex-shrink: 0; background: #CBD5E1; }
.db-feed__dot--created { background: #22C55E; }
.db-feed__dot--updated { background: #3B82F6; }
.db-feed__dot--deleted { background: #EF4444; }
.db-feed__txt { font-size: 12px; color: #334155; line-height: 1.3; }
.db-feed__meta { font-size: 10.5px; color: #94A3B8; }
.db-ent {
  display: block; text-align: center; background: #fff; border: 1.5px solid #E7ECF3;
  border-radius: 14px; padding: 12px 6px; text-decoration: none; color: var(--qa);
  transition: all 0.2s ease;
}
.db-ent:hover { transform: translateY(-3px); border-color: var(--qa); box-shadow: 0 12px 22px -16px var(--qa); }
.db-ent__num { font-size: 19px; font-weight: 800; color: #0F172A; }
.db-ent__lbl { font-size: 10.5px; color: #94A3B8; }
/* Ordered blocks (CSS flex order = user's section sort) */
.db-blocks { display: flex; flex-direction: column; }

/* My Cards grid */
.db-mycard { display: flex; align-items: center; gap: 10px; background: var(--surface-card, #fff); border: 1px solid var(--border-soft, #E7ECF3); border-radius: 12px; padding: 12px; text-decoration: none; color: inherit; transition: box-shadow .15s, transform .15s; height: 100%; }
.db-mycard:hover { box-shadow: 0 6px 16px rgba(15,23,42,.08); transform: translateY(-2px); }
.db-mycard__ic { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; background: color-mix(in srgb, var(--qa) 14%, #fff); color: var(--qa); flex: 0 0 auto; }
.db-mycard__body { min-width: 0; flex: 1 1 auto; }
.db-mycard__v { font-size: 20px; font-weight: 800; color: #0F172A; letter-spacing: -.3px; }
.db-mycard__l { font-size: 12.5px; font-weight: 600; color: #334155; }
.db-mycard__sub { font-size: 10.5px; color: #94A3B8; }
.db-mycard__go { color: #CBD5E1; }

/* Customizer inner sections */
.db-cust-sec { font-size: 10.5px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: #94A3B8; padding: 12px 16px 4px; }
.db-order-row { display: flex; align-items: center; padding: 5px 4px; border-bottom: 1px solid #F1F5F9; font-size: 13px; }
.db-add-row { display: flex; align-items: center; gap: 8px; padding: 7px 6px; border-radius: 8px; cursor: pointer; font-size: 13px; }
.db-add-row:hover { background: #F1F5F9; }
.db-add-ic { width: 30px; height: 30px; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: color-mix(in srgb, var(--qa) 14%, #fff); color: var(--qa); flex: 0 0 auto; }

/* Dashboard customizer panel */
.db-customize { width: 380px; max-width: 94vw; display: flex; flex-direction: column; height: 100vh; }
.db-customize__head { display: flex; align-items: center; gap: 6px; padding: 14px 16px; background: linear-gradient(135deg, #123A66, #0B1626); color: #fff; }
.db-customize__sub { font-size: 11px; opacity: .8; }
.db-customize__foot { display: flex; align-items: center; padding: 10px 14px; border-top: 1px solid #E2E8F0; }
.db-cust-item { border-radius: 10px; margin: 2px 8px; }
.db-cust-item:hover { background: #F1F5F9; }
.db-cust-ic { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; background: color-mix(in srgb, var(--wc) 14%, #fff); color: var(--wc); }
@media (prefers-color-scheme: dark) {
  .db-cust-item:hover { background: #334155; }
  .db-customize__foot { border-color: #334155; }
}
</style>
