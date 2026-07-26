<template>
  <q-page>
    <m-backgrounds>
      <div class="row my_radio_less q-pa-sm">

        <div class="col-12">
          <m-header icon="tune" controlRoomButton="false" :subtitle="$t('ControlRoomSub')" class="q-mt-xs">
            {{ $t('ControlRoom') }}
          </m-header>
        </div>

        <!-- VIP hero (same language as the Roles editor) -->
        <div class="col-12 q-mt-sm">
          <q-card flat class="cr-hero">
            <div class="row items-center q-col-gutter-md">
              <div class="col-12 col-md-4">
                <div class="cr-hero__badge"><q-icon name="tune" size="30px" /></div>
                <div class="cr-hero__title">{{ $t('ControlRoom') }}</div>
                <div class="cr-hero__sub">{{ $t('ControlRoomHeroSub') }}</div>
              </div>
              <div class="col-12 col-md-5">
                <div class="row q-col-gutter-sm">
                  <div class="col-3"><div class="cr-tile"><div class="cr-tile__v">{{ groups.length }}</div><div class="cr-tile__l">{{ $t('Groups') }}</div></div></div>
                  <div class="col-3"><div class="cr-tile"><div class="cr-tile__v">{{ stats.pages }}</div><div class="cr-tile__l">{{ $t('Pages') }}</div></div></div>
                  <div class="col-3"><div class="cr-tile"><div class="cr-tile__v">{{ stats.elements }}</div><div class="cr-tile__l">{{ $t('Elements') }}</div></div></div>
                  <div class="col-3"><div class="cr-tile cr-tile--warn"><div class="cr-tile__v">{{ stats.hidden }}</div><div class="cr-tile__l">{{ $t('Hidden') }}</div></div></div>
                </div>
              </div>
              <div class="col-12 col-md-3">
                <div class="row items-center q-gutter-xs justify-end">
                  <q-btn size="sm" outline color="white" icon="visibility" :label="$t('ShowAll')" no-caps @click="showAll" />
                  <q-btn size="sm" outline color="white" icon="restart_alt" :label="$t('ResetToDefaults')" no-caps @click="confirmReset" />
                </div>
              </div>
            </div>
          </q-card>
        </div>

        <!-- Global search -->
        <div class="col-12 q-mt-sm">
          <q-input v-model="compSearch" :label="$t('SearchEverything')" outlined dense clearable color="primary" class="bg-white" style="border-radius:10px">
            <template #prepend><q-icon name="search" color="primary" /></template>
          </q-input>
        </div>

        <!-- Module category cards (one per sidebar group) -->
        <div class="col-12 q-mt-sm">
          <q-card v-for="g in filteredGroups" :key="g.key" flat bordered class="cr-cat q-mb-sm">
            <q-expansion-item :default-opened="searching" header-class="cr-cat__head" expand-icon-class="text-grey-6">
              <template #header>
                <q-item-section avatar style="min-width:46px">
                  <div class="cr-cat__icon" :style="`--cc:${g._color}`"><q-icon :name="g.icon" size="20px" /></div>
                </q-item-section>
                <q-item-section>
                  <div class="cr-cat__title">{{ $t(g.name) }}
                    <q-icon v-if="!visible(g.key)" name="visibility_off" size="14px" color="grey-5" class="q-ml-xs" />
                  </div>
                  <div class="cr-cat__sub">{{ g._pages.length }} {{ $t('Pages') }} · {{ g._elCount }} {{ $t('Elements') }}</div>
                </q-item-section>
                <q-item-section side @click.stop>
                  <q-checkbox :model-value="visible(g.key)" @update:model-value="setVisible(g.key, $event)" dense color="primary">
                    <q-tooltip>{{ $t('ShowInMenu') }}</q-tooltip>
                  </q-checkbox>
                </q-item-section>
              </template>

              <div class="q-px-md q-pb-md">
                <div v-for="(pageItem, pi) in g._pages" :key="pageItem.key" class="cr-page" :class="{ 'cr-page--off': !visible(pageItem.key) }">
                  <div class="cr-page__head">
                    <q-icon :name="pageItem.icon" size="17px" :color="visible(pageItem.key) ? 'primary' : 'grey-4'" />
                    <span class="cr-page__name">{{ $t(pageItem.name) }}</span>
                    <q-space />
                    <template v-if="!searching">
                      <q-btn flat dense round size="xs" icon="keyboard_arrow_up" :disable="pi === 0" color="grey-6" @click="movePage(g, pi, -1)" />
                      <q-btn flat dense round size="xs" icon="keyboard_arrow_down" :disable="pi === g._pages.length - 1" color="grey-6" @click="movePage(g, pi, 1)" />
                    </template>
                    <q-checkbox :model-value="visible(pageItem.key)" @update:model-value="setVisible(pageItem.key, $event)" dense color="primary">
                      <q-tooltip>{{ $t('Visible') }}</q-tooltip>
                    </q-checkbox>
                  </div>

                  <div v-if="pageItem.sections.length" class="cr-page__grid">
                    <div v-for="sec in pageItem.sections" :key="sec.type" class="cr-sec">
                      <div class="cr-sec__head" :style="`--cc:${g._color}`">
                        <q-icon :name="sec.meta.icon" size="13px" />{{ $t(sec.meta.label) }}
                        <span class="cr-sec__n">{{ sec.items.length }}</span>
                      </div>
                      <div v-for="(it, i) in sec.items" :key="it.key" class="cr-row" :class="{ 'cr-row--off': !visible(it.key) }">
                        <q-icon :name="it.icon" size="14px" :color="visible(it.key) ? 'primary' : 'grey-4'" class="cr-row__ic" />
                        <span class="cr-row__name">{{ label(it.key, $t(it.name)) }}</span>
                        <span class="cr-row__ctrls">
                          <template v-for="c in sec.controls" :key="c.flag">
                            <span v-if="c.flag === 'order'" class="cr-ord">
                              <q-btn flat dense round size="xs" icon="keyboard_arrow_up" :disable="i === 0" color="grey-6" @click="moveItem(sec, i, -1)" />
                              <q-btn flat dense round size="xs" icon="keyboard_arrow_down" :disable="i === sec.items.length - 1" color="grey-6" @click="moveItem(sec, i, 1)" />
                            </span>
                            <q-checkbox v-else :model-value="ctrlOn(it.key, c)" @update:model-value="setCtrl(it.key, c, $event)"
                              dense size="xs" :color="c.flag === 'visible' ? 'primary' : 'teal-7'" class="cr-chk">
                              <q-tooltip>{{ $t(c.label) }}</q-tooltip>
                            </q-checkbox>
                          </template>
                        </span>
                      </div>
                      <div class="cr-legend">
                        <span v-for="c in sec.controls.filter(x => x.flag !== 'order')" :key="c.flag" class="cr-legend__it">
                          <span class="cr-legend__dot" :class="c.flag === 'visible' ? 'cr-legend__dot--p' : 'cr-legend__dot--t'"></span>{{ $t(c.label) }}
                        </span>
                      </div>
                    </div>
                  </div>
                  <div v-else class="cr-page__hint"><q-icon name="visibility" size="12px" />{{ $t('PageVisibilityOnly') }}</div>
                </div>
              </div>
            </q-expansion-item>
          </q-card>
          <div v-if="!filteredGroups.length" class="text-center text-grey-5 q-py-lg">{{ $t('NoRecordFound') }}</div>
        </div>
      </div>
    </m-backgrounds>
  </q-page>
</template>

<script setup>
import { ref, computed, onMounted, getCurrentInstance } from 'vue'
import { Dialog, Notify } from 'quasar'
import { useUiConfig } from '@/composables/useUiConfig'
import { menuCatalog, pageByRoute, SECTION_CONTROLS, sectionMeta } from '@/config/controlRoom.js'

const { proxy } = getCurrentInstance()
const { loadUiConfig, visible, label, orderOf, prop, setHidden, setOrder, setProp, resetAll } = useUiConfig()

const compSearch = ref('')
const searching = computed(() => !!(compSearch.value || '').trim())

// Same accent palette the Roles editor uses for its module categories.
const PALETTE = ['#E0EDF7', '#FFE8D9', '#D7F5EC', '#E5E7FB', '#EFE7DF', '#D7F0F0', '#FBE0E0', '#E2E8F0', '#FDF0E1', '#EDE9FE', '#E0EDF7']
const groups = computed(() => menuCatalog().map((g, i) => ({ ...g, _color: PALETTE[i % PALETTE.length] })))

function sectionsFor (catalog, keepAll = false) {
  if (!catalog) return []
  const q = (compSearch.value || '').trim().toLowerCase()
  return Object.entries(catalog.sections || {}).map(([type, items]) => {
    let list = items.map((it, idx) => ({ ...it, _idx: idx }))
      .sort((a, b) => orderOf(a.key, a._idx) - orderOf(b.key, b._idx) || a._idx - b._idx)
    if (q && !keepAll) list = list.filter((it) => proxy.$t(it.name).toLowerCase().includes(q) || it.key.toLowerCase().includes(q))
    return { type, meta: sectionMeta(type), controls: SECTION_CONTROLS[type] || SECTION_CONTROLS.fields, items: list }
  }).filter((s) => s.items.length)
}

function pagesOf (g) {
  const subs = (g.subs && g.subs.length) ? g.subs : [{ key: g.key, name: g.name, icon: g.icon, url: g.url }]
  const q = (compSearch.value || '').trim().toLowerCase()
  const out = []
  subs.forEach((s, idx) => {
    if (q) {
      const nameHit = proxy.$t(s.name).toLowerCase().includes(q) || s.key.toLowerCase().includes(q)
      const sections = sectionsFor(pageByRoute(s.url), nameHit)
      if (!nameHit && !sections.length) return
      out.push({ ...s, _i: out.length, sections })
    } else {
      out.push({ ...s, _i: idx, sections: sectionsFor(pageByRoute(s.url)) })
    }
  })
  if (q) return out
  return out
    .sort((a, b) => orderOf(a.key, a._i) - orderOf(b.key, b._i) || a._i - b._i)
    .map((s, i) => ({ ...s, _i: i }))
}

// Groups with their (search-filtered, ordered) pages; empty groups drop out while searching.
const filteredGroups = computed(() => {
  const q = (compSearch.value || '').trim().toLowerCase()
  return groups.value
    .map((g) => {
      const pages = pagesOf(g)
      const elCount = pages.reduce((n, p) => n + p.sections.reduce((m, s) => m + s.items.length, 0), 0)
      return { ...g, _pages: pages, _elCount: elCount }
    })
    .filter((g) => !q || g._pages.length || proxy.$t(g.name).toLowerCase().includes(q))
})

// Hero tallies over the full catalogue (unfiltered).
const stats = computed(() => {
  let pages = 0; let elements = 0; let hidden = 0
  for (const g of groups.value) {
    if (!visible(g.key)) hidden++
    const subs = (g.subs && g.subs.length) ? g.subs : [{ key: g.key, url: g.url }]
    for (const s of subs) {
      pages++
      if (!visible(s.key)) hidden++
      const cat = pageByRoute(s.url)
      for (const items of Object.values(cat?.sections || {})) {
        for (const it of items) { elements++; if (!visible(it.key)) hidden++ }
      }
    }
  }
  return { pages, elements, hidden }
})

function setVisible (key, on) { setHidden(key, !on) }

function ctrlOn (key, c) {
  if (c.flag === 'visible') return visible(key)
  if (c.invert) return prop(key, c.flag, false) !== true
  return !!prop(key, c.flag, c.on || false)
}
function setCtrl (key, c, on) {
  if (c.flag === 'visible') return setHidden(key, !on)
  if (c.invert) return setProp(key, c.flag, !on)
  return setProp(key, c.flag, on)
}

function movePage (g, index, dir) {
  const list = g._pages
  const t = index + dir
  if (t < 0 || t >= list.length) return
  const a = list[index]; const b = list[t]
  setOrder([{ key: a.key, sort_order: orderOf(b.key, b._i) }, { key: b.key, sort_order: orderOf(a.key, a._i) }])
}
function moveItem (sec, index, dir) {
  const t = index + dir
  if (t < 0 || t >= sec.items.length) return
  const a = sec.items[index]; const b = sec.items[t]
  setOrder([{ key: a.key, sort_order: orderOf(b.key, b._idx) }, { key: b.key, sort_order: orderOf(a.key, a._idx) }])
}

// Un-hide every group, page and element in one click.
function showAll () {
  for (const g of groups.value) {
    if (!visible(g.key)) setHidden(g.key, false)
    const subs = (g.subs && g.subs.length) ? g.subs : [{ key: g.key, url: g.url }]
    for (const s of subs) {
      if (!visible(s.key)) setHidden(s.key, false)
      const cat = pageByRoute(s.url)
      for (const items of Object.values(cat?.sections || {})) {
        for (const it of items) if (!visible(it.key)) setHidden(it.key, false)
      }
    }
  }
  Notify.create({ type: 'positive', position: 'bottom', icon: 'visibility', message: proxy.$t('Done') })
}

function confirmReset () {
  Dialog.create({
    title: proxy.$t('ResetToDefaults'), message: proxy.$t('ResetInterfaceConfirm'),
    cancel: true, persistent: true,
    ok: { label: proxy.$t('ResetToDefaults'), color: 'negative', unelevated: true },
  }).onOk(async () => {
    await resetAll()
    Notify.create({ type: 'positive', position: 'bottom', icon: 'restart_alt', message: proxy.$t('Done') })
  })
}

onMounted(async () => { await loadUiConfig(true) })
</script>

<style scoped>
/* Hero — mirrors the Roles editor */
.cr-hero { background: linear-gradient(135deg, var(--topbar-from, #123A66) 0%, var(--topbar-to, #0B1626) 100%); color: #fff; border-radius: 14px; padding: 18px 20px; }
.cr-hero__badge { width: 56px; height: 56px; border-radius: 14px; display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,.14); margin-bottom: 8px; }
.cr-hero__title { font-size: 20px; font-weight: 800; letter-spacing: -.3px; }
.cr-hero__sub { font-size: 12px; opacity: .8; max-width: 380px; }
.cr-tile { background: rgba(255,255,255,.1); border-radius: 10px; padding: 8px 6px; text-align: center; }
.cr-tile--warn { background: rgba(245,158,11,.22); }
.cr-tile__v { font-size: 20px; font-weight: 800; }
.cr-tile__l { font-size: 10px; opacity: .8; }

/* Category cards — same as Roles editor */
.cr-cat { border-radius: 12px; overflow: hidden; }
:deep(.cr-cat__head) { padding: 8px 12px; }
.cr-cat__icon { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; background: var(--cc); color: #334155; }
.cr-cat__title { font-weight: 700; font-size: 14px; color: #0F172A; display: flex; align-items: center; }
.cr-cat__sub { font-size: 11px; color: #64748B; }

/* Page blocks inside a category */
.cr-page { border: 1px solid #EEF2F7; border-radius: 10px; margin-top: 8px; overflow: hidden; }
.cr-page--off { opacity: .6; }
.cr-page__head { display: flex; align-items: center; gap: 7px; padding: 7px 10px; background: #F8FAFC; border-bottom: 1px solid #EEF2F7; }
.cr-page__name { font-weight: 800; font-size: 12.5px; color: #1E293B; }
.cr-page__hint { display: flex; align-items: center; gap: 5px; padding: 6px 10px; font-size: 10.5px; color: #94A3B8; }
.cr-page__grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(270px, 1fr)); gap: 4px 18px; padding: 6px 10px 8px; }

/* Element rows (dense, one line) */
.cr-sec { margin-top: 4px; min-width: 0; }
.cr-sec__head { display: flex; align-items: center; gap: 4px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .5px; color: #123A66; margin-bottom: 2px; }
.cr-sec__n { background: var(--cc, #EEF2F7); color: #475569; border-radius: 8px; padding: 0 6px; font-size: 9.5px; }
.cr-row { display: flex; align-items: center; gap: 6px; padding: 1px 4px; border-radius: 6px; }
.cr-row:hover { background: #F8FAFC; }
.cr-row--off .cr-row__name { color: #94A3B8; text-decoration: line-through; }
.cr-row__ic { flex: 0 0 auto; }
.cr-row__name { font-size: 12px; font-weight: 600; color: #1E293B; flex: 1 1 auto; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cr-row__ctrls { display: flex; align-items: center; gap: 2px; flex: 0 0 auto; }
.cr-chk { margin: 0; }
.cr-ord { display: flex; align-items: center; }
.cr-legend { display: flex; gap: 10px; padding: 2px 4px 4px; }
.cr-legend__it { display: flex; align-items: center; gap: 3px; font-size: 9px; font-weight: 700; color: #94A3B8; text-transform: uppercase; letter-spacing: .4px; }
.cr-legend__dot { width: 7px; height: 7px; border-radius: 2px; }
.cr-legend__dot--p { background: var(--q-primary); }
.cr-legend__dot--t { background: #0F766E; }

@media (prefers-color-scheme: dark) {
  .cr-cat { background: #1E293B; border-color: #334155; }
  .cr-cat__title, .cr-row__name, .cr-page__name { color: #E2E8F0; }
  .cr-page__head { background: #263449; border-color: #334155; }
}
</style>
