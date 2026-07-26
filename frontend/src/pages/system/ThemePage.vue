<template>
  <q-page>
    <m-backgrounds>
      <div class="row q-pa-sm q-col-gutter-md">
        <div class="col-12">
          <m-header icon="palette" controlRoomButton="false" class="q-mt-xs">{{ $t('ThemeAppearance') }}</m-header>
        </div>

        <!-- Colour scheme gallery -->
        <div class="col-12">
          <div class="th-sec-title">
            <div><q-icon name="format_paint" size="20px" class="q-mr-xs" />{{ $t('ColorScheme') }}</div>
            <div class="th-sec-sub">{{ $t('ColorSchemeHint') }}</div>
          </div>

          <div class="row q-col-gutter-md q-mt-none">
            <div v-for="s in schemes" :key="s.name" class="col-12 col-sm-6 col-md-4">
              <div class="th-card" :class="{ 'th-card--active': active === s.name }" @click="applyScheme(s)">
                <div class="th-card__top">
                  <span class="th-name">{{ $t(s.key) }}<small>{{ s.nameFa }}</small></span>
                  <q-icon v-if="active === s.name" name="check_circle" color="primary" size="20px" />
                </div>

                <!-- Mini website preview, rendered in the scheme's colours -->
                <div class="sp" :style="spStyle(s)">
                  <div class="sp-bar">
                    <span class="sp-logo">LOGO</span>
                    <span class="sp-nav"><i></i><i></i><i></i></span>
                    <span class="sp-menu">Menu</span>
                  </div>
                  <div class="sp-body">
                    <div class="sp-left">
                      <div class="sp-h"></div>
                      <div class="sp-h sp-h--2"></div>
                      <div class="sp-p"></div>
                      <div class="sp-p sp-p--2"></div>
                      <div class="sp-cta">Get&nbsp;Started</div>
                    </div>
                    <div class="sp-img"></div>
                  </div>
                </div>

                <!-- Palette dots -->
                <div class="th-dots">
                  <span :style="`background:${s.topFrom}`"></span>
                  <span :style="`background:${s.primary}`"></span>
                  <span :style="`background:${s.accent}`"></span>
                  <span :style="`background:${s.bg}`" class="th-dots--ring"></span>
                  <q-space />
                  <q-btn v-if="active === s.name" flat dense size="sm" color="primary" :label="$t('Applied')" icon="check" no-caps disable />
                  <q-btn v-else flat dense size="sm" color="primary" :label="$t('Apply')" icon="brush" no-caps />
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Customize the active scheme's colours, one by one -->
        <div class="col-12 q-mt-sm">
          <div class="th-sec-title">
            <div><q-icon name="colorize" size="20px" class="q-mr-xs" />{{ $t('CustomizeColors') }}</div>
            <div class="th-sec-sub">{{ $t('CustomizeColorsHint') }}</div>
          </div>
          <q-card flat bordered class="th-tune">
            <div class="row q-col-gutter-md">
              <div v-for="c in colorFields" :key="c.key" class="col-6 col-sm-4 col-md-2 th-pick">
                <label class="th-pick__swatch" :style="{ background: custom[c.key] }">
                  <input type="color" :value="custom[c.key]" @input="setCustom(c.key, $event.target.value)" />
                </label>
                <div class="th-pick__label">{{ $t(c.label) }}</div>
                <div class="th-pick__hex">{{ custom[c.key] }}</div>
              </div>
              <div class="col-12 col-md-2 flex items-center">
                <q-btn outline dense no-caps color="grey-7" icon="undo" :label="$t('RevertToScheme')" @click="revertCustom" />
              </div>
            </div>
          </q-card>
        </div>

        <!-- Fine-tune -->
        <div class="col-12 q-mt-sm">
          <div class="th-sec-title"><div><q-icon name="tune" size="20px" class="q-mr-xs" />{{ $t('FineTune') }}</div></div>
        </div>

        <div class="col-12 col-sm-6 col-md-3">
          <q-card flat bordered class="th-tune">
            <div class="th-tune__t"><q-icon name="brightness_6" size="16px" class="q-mr-xs" />{{ $t('DisplayMode') }}</div>
            <q-btn-toggle v-model="darkMode" spread unelevated toggle-color="primary" color="grey-2" text-color="grey-9"
              :options="[{label:$t('LightMode'),value:false,icon:'light_mode'},{label:$t('DarkMode'),value:true,icon:'dark_mode'}]" @update:model-value="applyDark" />
          </q-card>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
          <q-card flat bordered class="th-tune">
            <div class="th-tune__t"><q-icon name="text_fields" size="16px" class="q-mr-xs" />{{ $t('FontSize') }}</div>
            <q-btn-toggle v-model="fontSize" spread unelevated toggle-color="primary" color="grey-2" text-color="grey-9"
              :options="[{label:$t('Small'),value:'small'},{label:$t('Normal'),value:'normal'},{label:$t('Large'),value:'large'}]" @update:model-value="applyFont" />
          </q-card>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
          <q-card flat bordered class="th-tune">
            <div class="th-tune__t"><q-icon name="rounded_corner" size="16px" class="q-mr-xs" />{{ $t('BorderRadius') }}</div>
            <q-btn-toggle v-model="radius" spread unelevated toggle-color="primary" color="grey-2" text-color="grey-9"
              :options="[{label:$t('Sharp'),value:'2px'},{label:$t('Normal'),value:'8px'},{label:$t('Round'),value:'16px'}]" @update:model-value="applyRadius" />
          </q-card>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
          <q-card flat bordered class="th-tune">
            <div class="th-tune__t"><q-icon name="view_sidebar" size="16px" class="q-mr-xs" />{{ $t('SidebarStyle') }}</div>
            <q-btn-toggle v-model="sidebarStyle" spread unelevated toggle-color="primary" color="grey-2" text-color="grey-9"
              :options="[{label:'Mini',value:'mini'},{label:$t('Normal'),value:'normal'},{label:'Wide',value:'wide'}]" @update:model-value="applySidebar" />
          </q-card>
        </div>

        <div class="col-12 col-sm-6 col-md-3">
          <q-card flat bordered class="th-tune">
            <div class="th-tune__t"><q-icon name="event" size="16px" class="q-mr-xs" />{{ $t('CalendarLead') }}</div>
            <q-btn-toggle v-model="calendarType" spread unelevated toggle-color="primary" color="grey-2" text-color="grey-9"
              :options="[{label:$t('Gregorian'),value:'en'},{label:$t('SolarHijri'),value:'fa'}]" @update:model-value="applyCalendar" />
          </q-card>
        </div>

        <div class="col-12">
          <q-btn outline color="negative" icon="restart_alt" :label="$t('ResetToDefault')" no-caps @click="resetAll" />
        </div>
      </div>
    </m-backgrounds>
  </q-page>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useQuasar, Notify } from 'quasar'

const $q = useQuasar()
const darkMode = ref(false)
const fontSize = ref('normal')
const radius = ref('8px')
const sidebarStyle = ref('normal')
const active = ref('Steel Blue')

// Each scheme carries both the preview colours and the app CSS-var mapping.
const schemes = [
  { name: 'Steel Blue', key: 'SchemeSteelBlue', nameFa: 'آبی فولادی',
    bg: '#F4F7FB', card: '#FFFFFF', text: '#0F172A', sub: '#64748B', primary: '#175A8C', primaryText: '#FFFFFF', accent: '#C8862D', img: '#E7EEF6',
    topFrom: '#123A66', topTo: '#0B1626', appAccent: '#C8862D', appAccentBg: '#FBF0DD', appPrimary: '#175A8C', dark: false },
  { name: 'Minimal', key: 'SchemeMinimal', nameFa: 'مینیمال',
    bg: '#FFFFFF', card: '#FFFFFF', text: '#111111', sub: '#8A8A8A', primary: '#2E5BFF', primaryText: '#FFFFFF', accent: '#2E5BFF', img: '#EEF1F6',
    topFrom: '#1A1A1A', topTo: '#000000', appAccent: '#2E5BFF', appAccentBg: '#EAEFFF', appPrimary: '#2E5BFF', dark: false },
  { name: 'Pastel', key: 'SchemePastel', nameFa: 'پاستل',
    bg: '#FBEEE6', card: '#F6D9C6', text: '#3A2A20', sub: '#9A7B66', primary: '#2F6E4E', primaryText: '#FFFFFF', accent: '#E29A6E', img: '#F0C4A8',
    topFrom: '#2F6E4E', topTo: '#1F4A34', appAccent: '#E29A6E', appAccentBg: '#FBEEE6', appPrimary: '#2F6E4E', dark: false },
  { name: 'Bold', key: 'SchemeBold', nameFa: 'پررنگ',
    bg: '#2A5BFF', card: '#2A5BFF', text: '#FFFFFF', sub: '#C7D2FE', primary: '#FFFFFF', primaryText: '#2A5BFF', accent: '#C7D2FE', img: '#5B7BFF',
    topFrom: '#2A5BFF', topTo: '#1E3FCC', appAccent: '#2A5BFF', appAccentBg: '#E4EAFF', appPrimary: '#2A5BFF', dark: false },
  { name: 'Dark Mode', key: 'SchemeDark', nameFa: 'حالت تاریک',
    bg: '#111214', card: '#1E1F22', text: '#F5F5F5', sub: '#9CA3AF', primary: '#8AE0C2', primaryText: '#0B2A20', accent: '#8AE0C2', img: '#2A2C30',
    topFrom: '#1A1B1E', topTo: '#050506', appAccent: '#8AE0C2', appAccentBg: '#12332A', appPrimary: '#2E7D64', dark: true },
  { name: 'Gradient', key: 'SchemeGradient', nameFa: 'گرادیانت',
    bg: 'linear-gradient(135deg,#34D399,#10B981)', card: 'transparent', text: '#FFFFFF', sub: '#DFF7EE', primary: '#FCD34D', primaryText: '#3A2E00', accent: '#FCD34D', img: 'rgba(255,255,255,.18)',
    topFrom: '#10B981', topTo: '#0E9488', appAccent: '#FCD34D', appAccentBg: '#ECFDF5', appPrimary: '#10B981', dark: false },
  { name: 'Neutral', key: 'SchemeNeutral', nameFa: 'خنثی',
    bg: '#F2E9DE', card: '#E7DAC8', text: '#5A4632', sub: '#9C8A76', primary: '#7A5C3A', primaryText: '#FFFFFF', accent: '#A98C63', img: '#DDCDBB',
    topFrom: '#5A4632', topTo: '#3A2C1E', appAccent: '#A98C63', appAccentBg: '#F2E9DE', appPrimary: '#7A5C3A', dark: false },
  { name: 'Forest Green', key: 'SchemeForest', nameFa: 'سبز جنگلی',
    bg: '#EAF3EC', card: '#FFFFFF', text: '#14321A', sub: '#5E7A64', primary: '#2E7D32', primaryText: '#FFFFFF', accent: '#66A56B', img: '#D6EAD9',
    topFrom: '#1E4620', topTo: '#0D2A0E', appAccent: '#66A56B', appAccentBg: '#E8F5E9', appPrimary: '#2E7D32', dark: false },
  { name: 'Royal Purple', key: 'SchemePurple', nameFa: 'بنفش سلطنتی',
    bg: '#F3EEFB', card: '#FFFFFF', text: '#2A1B45', sub: '#7A6A96', primary: '#7C3AED', primaryText: '#FFFFFF', accent: '#A78BFA', img: '#E4DAF7',
    topFrom: '#4C1D95', topTo: '#2A1065', appAccent: '#A78BFA', appAccentBg: '#EDE9FE', appPrimary: '#7C3AED', dark: false },
  { name: 'Sunset Amber', key: 'SchemeSunset', nameFa: 'کهربای غروب',
    bg: '#FFF4E6', card: '#FFFFFF', text: '#4A2A10', sub: '#A9835E', primary: '#E07A1F', primaryText: '#FFFFFF', accent: '#F0A855', img: '#FCE3C6',
    topFrom: '#8A4A0E', topTo: '#5C3009', appAccent: '#F0A855', appAccentBg: '#FDF0E1', appPrimary: '#E07A1F', dark: false },
]

function spStyle (s) {
  return {
    '--sp-bg': s.bg, '--sp-card': s.card, '--sp-text': s.text, '--sp-sub': s.sub,
    '--sp-primary': s.primary, '--sp-primary-text': s.primaryText, '--sp-accent': s.accent, '--sp-img': s.img,
  }
}

function set (k, v) { document.documentElement.style.setProperty(k, v) }

function applyScheme (s) {
  active.value = s.name
  set('--topbar-from', s.topFrom); set('--topbar-to', s.topTo)
  set('--sidebar-accent', s.appAccent); set('--sidebar-accent-bg', s.appAccentBg)
  set('--q-primary', s.appPrimary)
  localStorage.setItem('theme_scheme', s.name)
  localStorage.setItem('theme_topbar_from', s.topFrom)
  localStorage.setItem('theme_topbar_to', s.topTo)
  localStorage.setItem('theme_sidebar_accent', s.appAccent)
  localStorage.setItem('theme_sidebar_accent_bg', s.appAccentBg)
  localStorage.setItem('theme_primary', s.appPrimary)
  darkMode.value = !!s.dark
  $q.dark.set(!!s.dark)
  localStorage.setItem('theme_dark', s.dark ? '1' : '0')
  syncCustomFrom(s)
  Notify.create({ type: 'positive', position: 'bottom', icon: 'palette', message: s.name })
}

// ── Per-colour customization on top of the chosen scheme ──
const colorFields = [
  { key: 'topFrom', label: 'TopBarStart', cssVar: '--topbar-from', store: 'theme_topbar_from' },
  { key: 'topTo', label: 'TopBarEnd', cssVar: '--topbar-to', store: 'theme_topbar_to' },
  { key: 'primary', label: 'PrimaryColor', cssVar: '--q-primary', store: 'theme_primary' },
  { key: 'accent', label: 'AccentColor', cssVar: '--sidebar-accent', store: 'theme_sidebar_accent' },
  { key: 'accentBg', label: 'AccentSoftBg', cssVar: '--sidebar-accent-bg', store: 'theme_sidebar_accent_bg' },
]
const custom = reactive({ topFrom: '#123A66', topTo: '#0B1626', primary: '#175A8C', accent: '#C8862D', accentBg: '#FBF0DD' })

function syncCustomFrom (s) {
  Object.assign(custom, { topFrom: s.topFrom, topTo: s.topTo, primary: s.appPrimary, accent: s.appAccent, accentBg: s.appAccentBg })
}

function setCustom (key, val) {
  custom[key] = val
  const f = colorFields.find(x => x.key === key)
  set(f.cssVar, val)
  localStorage.setItem(f.store, val)
}

function revertCustom () {
  const s = schemes.find(x => x.name === active.value) || schemes[0]
  applyScheme(s)
}

// Which calendar leads in every date display (میلادی or خورشیدی).
const calendarType = ref(localStorage.getItem('calendar_type') === 'fa' ? 'fa' : 'en')
function applyCalendar (v) { localStorage.setItem('calendar_type', v); window.location.reload() }

function applyDark (val) { $q.dark.set(val); localStorage.setItem('theme_dark', val ? '1' : '0') }
function applyFont (size) { const m = { small: '13px', normal: '14px', large: '16px' }; document.documentElement.style.fontSize = m[size]; localStorage.setItem('theme_font', size) }
function applyRadius (r) { set('--my-radius', r); localStorage.setItem('theme_radius', r) }
function applySidebar (s) { localStorage.setItem('theme_sidebar', s) }

function resetAll () {
  ['theme_dark', 'theme_primary', 'theme_font', 'theme_radius', 'theme_sidebar', 'theme_topbar_from', 'theme_topbar_to', 'theme_sidebar_accent', 'theme_sidebar_accent_bg', 'theme_scheme'].forEach(k => localStorage.removeItem(k))
  darkMode.value = false; fontSize.value = 'normal'; radius.value = '8px'; sidebarStyle.value = 'normal'
  $q.dark.set(false)
  document.documentElement.style.fontSize = '14px'
  document.documentElement.style.removeProperty('--my-radius')
  applyScheme(schemes[0])
  localStorage.removeItem('theme_scheme')
  Notify.create({ type: 'positive', position: 'bottom', message: 'Reset' })
}

onMounted(() => {
  darkMode.value = localStorage.getItem('theme_dark') === '1'
  fontSize.value = localStorage.getItem('theme_font') || 'normal'
  radius.value = localStorage.getItem('theme_radius') || '8px'
  sidebarStyle.value = localStorage.getItem('theme_sidebar') || 'normal'
  active.value = localStorage.getItem('theme_scheme') || 'Steel Blue'
  // Start the colour editor from what is actually stored (scheme or custom).
  const s = schemes.find(x => x.name === active.value) || schemes[0]
  syncCustomFrom(s)
  colorFields.forEach(f => {
    const v = localStorage.getItem(f.store)
    if (v) custom[f.key] = v
  })
})
</script>

<style scoped>
.th-sec-title { display: flex; align-items: baseline; gap: 12px; font-size: 16px; font-weight: 700; color: var(--on-surface, #0F172A); margin-bottom: 4px; }
.th-sec-sub { font-size: 12px; font-weight: 400; color: #94A3B8; }

.th-card { background: var(--surface-card, #fff); border: 1px solid var(--border-soft, #E7ECF3); border-radius: 16px; padding: 12px; cursor: pointer; transition: box-shadow .18s, transform .18s, border-color .18s; }
.th-card:hover { box-shadow: 0 10px 26px rgba(15,23,42,.12); transform: translateY(-3px); }
.th-card--active { border-color: var(--q-primary); box-shadow: 0 0 0 2px color-mix(in srgb, var(--q-primary) 40%, transparent); }
.th-card__top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; padding: 0 2px; }
.th-name { font-weight: 700; font-size: 14px; color: var(--on-surface, #0F172A); display: flex; flex-direction: column; line-height: 1.15; }
.th-name small { font-size: 11px; font-weight: 500; color: #94A3B8; }

/* Mini website preview */
.sp { background: var(--sp-bg); border-radius: 12px; padding: 12px; height: 168px; display: flex; flex-direction: column; gap: 10px; overflow: hidden; border: 1px solid rgba(0,0,0,.05); }
.sp-bar { display: flex; align-items: center; gap: 8px; }
.sp-logo { font-size: 10px; font-weight: 800; color: var(--sp-text); letter-spacing: .5px; }
.sp-nav { display: flex; gap: 6px; margin-left: auto; }
.sp-nav i { width: 16px; height: 4px; border-radius: 2px; background: var(--sp-sub); opacity: .5; }
.sp-menu { font-size: 8px; font-weight: 700; color: var(--sp-primary-text); background: var(--sp-primary); border-radius: 6px; padding: 3px 7px; }
.sp-body { display: flex; gap: 10px; flex: 1; min-height: 0; }
.sp-left { flex: 1.1; display: flex; flex-direction: column; gap: 6px; justify-content: center; }
.sp-h { height: 11px; width: 78%; border-radius: 3px; background: var(--sp-text); }
.sp-h--2 { width: 55%; }
.sp-p { height: 4px; width: 90%; border-radius: 2px; background: var(--sp-sub); opacity: .55; }
.sp-p--2 { width: 70%; }
.sp-cta { margin-top: 6px; align-self: flex-start; font-size: 8px; font-weight: 700; color: var(--sp-primary-text); background: var(--sp-primary); border-radius: 6px; padding: 4px 9px; }
.sp-img { flex: 1; border-radius: 10px; background: var(--sp-img); }

.th-dots { display: flex; align-items: center; gap: 6px; margin-top: 10px; padding: 0 2px; }
.th-dots span { width: 15px; height: 15px; border-radius: 50%; box-shadow: inset 0 0 0 1px rgba(0,0,0,.08); }
.th-dots--ring { box-shadow: inset 0 0 0 1px rgba(0,0,0,.15); }

.th-tune { border-radius: 12px; padding: 12px; }
.th-pick { text-align: center; }
.th-pick__swatch {
  display: block; width: 100%; height: 52px; border-radius: 12px; cursor: pointer;
  border: 1px solid rgba(0,0,0,.12); box-shadow: inset 0 0 0 3px rgba(255,255,255,.55);
  transition: transform .15s ease;
}
.th-pick__swatch:hover { transform: translateY(-2px); }
.th-pick__swatch input { opacity: 0; width: 100%; height: 100%; cursor: pointer; }
.th-pick__label { font-size: 11.5px; font-weight: 700; color: #475569; margin-top: 6px; }
.th-pick__hex { font-size: 10.5px; color: #94A3B8; text-transform: uppercase; }
.th-tune__t { font-size: 12.5px; font-weight: 700; color: var(--on-surface, #0F172A); margin-bottom: 8px; }

@media (prefers-color-scheme: dark) {
  .th-card { background: #1E293B; border-color: #334155; }
  .th-name, .th-tune__t, .th-sec-title { color: #E2E8F0; }
}
</style>
