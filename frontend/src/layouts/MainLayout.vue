<template>
  <q-layout view="hHh LpR fFf">
    <!-- ─── TOP BAR ─────────────────────────────────────────────── -->
    <q-header class="topbar-header">
      <q-toolbar class="q-px-md" style="min-height:48px">

        <!-- Hamburger -->
        <q-btn flat dense round icon="menu" color="white" aria-label="Menu"
          @click="leftDrawerOpen = !leftDrawerOpen" />

        <!-- Back — needed in fullscreen / PWA where the browser bar is hidden -->
        <q-btn flat dense round icon="arrow_back" color="white" size="sm" class="q-ml-xs" @click="$router.back()">
          <q-tooltip>{{ $t('Back') }}</q-tooltip>
        </q-btn>

        <!-- Company avatar + Brand text + Breadcrumb -->
        <div class="row items-center no-wrap q-ml-xs" style="gap:8px; overflow:hidden">
          <q-avatar color="white" text-color="primary" size="28px"
            style="font-size:13px; font-weight:700; flex-shrink:0">
            {{ (company.abbreviation || companyName || 'A').charAt(0).toUpperCase() }}
          </q-avatar>
          <div class="row items-center no-wrap" style="gap:4px; overflow:hidden">
            <span class="text-white text-weight-bold ellipsis mobile-hide" style="font-size:15px; letter-spacing:.3px">
              {{ companyName }}
            </span>
            <span v-if="routeLabel" class="text-white mobile-hide" style="opacity:.55; font-size:13px">/</span>
            <span v-if="routeLabel" class="text-white ellipsis mobile-hide" style="font-size:13px; opacity:.85; max-width:160px">
              {{ routeLabel }}
            </span>
          </div>
        </div>

        <q-space />

        <!-- Daily currency rate — view & set from any page -->
        <currency-rate-chip v-if="$can('exchange-rate-list')" class="mobile-hide" />

        <!-- Global search -->
        <q-btn flat dense round size="sm" color="white" icon="search" class="q-mx-xs" @click="openSearch">
          <q-tooltip>{{ $t('Search') }}</q-tooltip>
        </q-btn>

        <!-- Fullscreen (desktop only) -->
        <q-btn flat dense round size="sm" color="white" class="mobile-hide"
          :icon="$q.fullscreen.isActive ? 'fullscreen_exit' : 'fullscreen'"
          @click="$q.fullscreen.toggle()">
          <q-tooltip>Fullscreen</q-tooltip>
        </q-btn>

        <!-- Notifications -->
        <q-btn v-if="$can('notification-list')" flat round dense icon="notifications" color="white" class="q-mx-xs">
          <q-badge v-if="unreadCount > 0" color="red" :label="unreadCount" floating />
          <q-tooltip>{{ $t('Notification') }}</q-tooltip>
          <q-menu style="width:340px; max-height:500px">
            <q-card class="my_radio_less">
              <q-bar class="bg-cyan-7 text-white">
                <q-icon name="notifications" /><span class="q-ml-sm text-weight-bold">{{ $t('Notification') }}</span>
                <q-space />
                <q-btn flat dense size="sm" :label="$t('MarkAllRead')" @click="markAllRead" />
              </q-bar>
              <q-scroll-area style="height:400px">
                <q-list separator>
                  <q-item v-if="notifications.length === 0" class="q-py-md">
                    <q-item-section class="text-center text-grey-6">No notifications</q-item-section>
                  </q-item>
                  <q-item v-for="n in notifications" :key="n.id" clickable v-close-popup
                    @click="openNotif(n)"
                    :class="n.read_at ? '' : 'bg-cyan-1'">
                    <q-item-section avatar>
                      <q-icon :name="notifIcon(n.type)" :color="notifColor(n.type)" />
                    </q-item-section>
                    <q-item-section>
                      <q-item-label class="text-weight-medium">{{ n.title }}</q-item-label>
                      <q-item-label caption>{{ n.body }}</q-item-label>
                      <q-item-label caption class="text-grey-5">{{ n.created_at_human }}</q-item-label>
                    </q-item-section>
                    <q-item-section side>
                      <q-icon name="chevron_left" size="18px" color="grey-5" class="notif-go" />
                      <q-badge v-if="!n.read_at" color="cyan-6" label="New" />
                    </q-item-section>
                  </q-item>
                </q-list>
              </q-scroll-area>
            </q-card>
          </q-menu>
        </q-btn>


        <!-- Language switcher (calendar preference lives in Theme settings) -->
        <q-btn-dropdown v-if="$can('language-list')" flat dense icon="language" :label="currentLang" size="sm" color="white" class="q-mx-xs">
          <q-list dense>
            <q-item v-if="$can('lang-en-list')" clickable v-close-popup @click="setLang('en')"><q-item-section>English</q-item-section></q-item>
            <q-item v-if="$can('lang-fa-list')" clickable v-close-popup @click="setLang('fa')"><q-item-section>دری</q-item-section></q-item>
            <q-item v-if="$can('lang-pa-list')" clickable v-close-popup @click="setLang('pa')"><q-item-section>پښتو</q-item-section></q-item>
          </q-list>
        </q-btn-dropdown>

        <!-- User avatar — shows who is logged in -->
        <q-btn flat round color="white" class="q-ml-xs">
          <q-avatar size="30px" color="white" text-color="primary" style="font-weight:700">
            <img v-if="auth.user?.avatar" :src="auth.user.avatar" />
            <span v-else>{{ (auth.user?.name || 'U').charAt(0).toUpperCase() }}</span>
          </q-avatar>
          <q-menu>
            <q-card style="width:300px">
              <div class="bg-white my_card">
                <!-- Who am I -->
                <div class="user-card">
                  <q-avatar size="52px" color="primary" text-color="white" style="font-weight:700; font-size:20px">
                    <img v-if="auth.user?.avatar" :src="auth.user.avatar" />
                    <span v-else>{{ (auth.user?.name || 'U').charAt(0).toUpperCase() }}</span>
                  </q-avatar>
                  <div class="user-card__body">
                    <div class="user-card__name">{{ auth.user?.name || '—' }}</div>
                    <div class="user-card__mail">{{ auth.user?.email }}</div>
                    <div class="user-card__meta">
                      <q-badge v-for="r in auth.roles" :key="r" color="blue-1" text-color="blue-9" class="q-mr-xs" :label="r" />
                    </div>
                    <div v-if="auth.user?.phone" class="user-card__phone"><q-icon name="phone" size="12px" /> {{ auth.user.phone }}</div>
                  </div>
                </div>
                <div class="q-px-sm q-pb-xs">
                  <q-btn outline dense size="sm" color="primary" icon="manage_accounts" class="full-width"
                    :label="$t('MyProfile')" no-caps v-close-popup @click="openProfile" />
                </div>

                <!-- Branch switcher — lives only here, in the profile menu -->
                <q-separator />
                <div class="q-px-sm q-pt-sm">
                  <div class="text-caption text-grey-6 q-mb-xs"><q-icon name="store" size="14px" class="q-mr-xs" />{{ $t('Branch') }}</div>
                  <q-list dense class="branch-pick">
                    <q-item v-if="seesAllBranches" clickable v-ripple v-close-popup
                      class="rounded-borders" :active="!activeBranch" active-class="branch-option--active" @click="selectBranch('all')">
                      <q-item-section avatar style="min-width:28px"><q-icon name="public" size="16px" :color="!activeBranch ? 'cyan-7' : 'grey-5'" /></q-item-section>
                      <q-item-section>{{ $t('AllBranches') }}</q-item-section>
                    </q-item>
                    <q-item v-for="b in branches" :key="b.id" clickable v-ripple v-close-popup
                      class="rounded-borders" :active="activeBranch === b.id" active-class="branch-option--active" @click="selectBranch(b.id)">
                      <q-item-section avatar style="min-width:28px"><q-icon name="place" size="16px" :color="activeBranch === b.id ? 'cyan-7' : 'grey-5'" /></q-item-section>
                      <q-item-section>{{ b.name }}</q-item-section>
                    </q-item>
                  </q-list>
                </div>

                <!-- Language switcher — moved here from the top bar -->
                <template v-if="$can('language-list')">
                  <q-separator />
                  <div class="q-px-sm q-pt-sm">
                    <div class="text-caption text-grey-6 q-mb-xs"><q-icon name="language" size="14px" class="q-mr-xs" />{{ $t('Language') }}</div>
                    <q-list dense class="branch-pick">
                      <q-item v-if="$can('lang-en-list')" clickable v-ripple v-close-popup class="rounded-borders" :active="locale === 'en'" active-class="branch-option--active" @click="setLang('en')">
                        <q-item-section avatar style="min-width:28px"><q-icon name="translate" size="16px" :color="locale === 'en' ? 'cyan-7' : 'grey-5'" /></q-item-section>
                        <q-item-section>English</q-item-section>
                      </q-item>
                      <q-item v-if="$can('lang-fa-list')" clickable v-ripple v-close-popup class="rounded-borders" :active="locale === 'fa'" active-class="branch-option--active" @click="setLang('fa')">
                        <q-item-section avatar style="min-width:28px"><q-icon name="translate" size="16px" :color="locale === 'fa' ? 'cyan-7' : 'grey-5'" /></q-item-section>
                        <q-item-section>دری</q-item-section>
                      </q-item>
                      <q-item v-if="$can('lang-pa-list')" clickable v-ripple v-close-popup class="rounded-borders" :active="locale === 'pa'" active-class="branch-option--active" @click="setLang('pa')">
                        <q-item-section avatar style="min-width:28px"><q-icon name="translate" size="16px" :color="locale === 'pa' ? 'cyan-7' : 'grey-5'" /></q-item-section>
                        <q-item-section>پښتو</q-item-section>
                      </q-item>
                    </q-list>
                  </div>
                </template>

                <q-separator />
                <div class="q-pa-xs q-pb-sm">
                  <q-list dense>
                    <q-item clickable v-ripple class="rounded-borders text-negative" @click="logout">
                      <q-item-section avatar><q-icon name="mdi-logout" color="negative" /></q-item-section>
                      <q-item-section class="text-negative">{{ $t('LogOut') }}</q-item-section>
                    </q-item>
                  </q-list>
                </div>
              </div>
            </q-card>
          </q-menu>
        </q-btn>

        <!-- Clock -->
        <div class="text-caption text-white q-ml-sm mobile-hide" style="opacity:.75; min-width:72px; text-align:right">
          {{ clock }}
        </div>
      </q-toolbar>
    </q-header>

    <!-- ─── SIDE DRAWER ─────────────────────────────────────────── -->
    <q-drawer
      v-model="leftDrawerOpen"
      show-if-above
      :width="262"
      :breakpoint="600"
      class="sidebar-drawer"
    >
      <!-- Drawer header / brand -->
      <div class="sidebar-brand q-px-md q-pt-md q-pb-sm">
        <div class="row items-center no-wrap">
          <brand-mark size="30" class="q-mr-sm" />
          <div class="col ellipsis">
            <div class="text-weight-bold text-grey-9 ellipsis" style="font-size:14px; line-height:1.2">
              {{ companyName }}
            </div>
            <div class="sidebar-brand-subtitle" style="line-height:1.2; font-size:12px">Construction ERP</div>
          </div>
        </div>
      </div>

      <!-- Search -->
      <div class="q-px-sm q-pb-sm">
        <q-input
          outlined
          dense
          clearable
          debounce="200"
          :placeholder="$t('Search') + '...'"
          v-model="searchMenuItems"
          color="cyan-7"
          bg-color="grey-1"
          style="border-radius:8px"
        >
          <template v-slot:prepend><q-icon name="search" color="grey-5" size="18px" /></template>
        </q-input>
      </div>


      <q-separator class="q-mb-xs" />

      <!-- Menu List -->
      <q-scroll-area style="height: calc(100vh - 155px)">
        <q-list class="q-px-xs q-pb-md" style="font-family: poppins, sans-serif">
          <template v-for="m in showMenus" :key="m.name">

            <!-- ── Top-level single link ── -->
            <template v-if="m.url != null">
              <q-item
                v-if="((m.platform && auth.isPlatformOwner) || (!m.platform && $can(m.permission))) && m.name !== 'Logout'"
                :to="m.url"
                clickable v-ripple
                class="sidebar-item q-mb-xs"
                :class="[m.url === $route.path ? 'sidebar-item--active' : '', m.platform ? 'sidebar-item--vip' : '']"
              >
                <q-item-section avatar style="min-width:36px">
                  <q-icon :name="m.icon" size="20px"
                    :color="m.platform ? 'amber-6' : (m.url === $route.path ? 'var(--sidebar-accent, #C8862D)' : 'grey-6')" />
                </q-item-section>
                <q-item-section class="sidebar-label">{{ $t(m.name) }}</q-item-section>
              </q-item>

              <q-item
                v-else-if="m.name === 'Logout'"
                clickable v-ripple
                class="sidebar-item sidebar-item--danger q-mb-xs"
                @click="logout"
              >
                <q-item-section avatar style="min-width:36px">
                  <q-icon :name="m.icon" size="20px" color="negative" />
                </q-item-section>
                <q-item-section class="sidebar-label text-negative">{{ $t(m.name) }}</q-item-section>
              </q-item>
            </template>

            <!-- ── Group with submenu ── -->
            <template v-else>
              <q-expansion-item
                group="sidebarGroup"
                expand-separator
                :icon="m.icon"
                :label="$t(m.name)"
                header-class="sidebar-group-header q-mb-xs"
                expand-icon-class="sidebar-expand-icon"
              >
                <q-list class="q-pl-sm">
                  <template v-for="sub in m.is_sub" :key="m.name + '/' + sub.name">
                    <q-item
                      v-if="$can(sub.permission)"
                      clickable dense v-ripple
                      :to="sub.url"
                      class="sidebar-sub-item q-mb-xs"
                      :class="sub.url === $route.path ? 'sidebar-sub-item--active' : ''"
                    >
                      <q-item-section avatar style="min-width:30px">
                        <q-icon :name="sub.icon" size="16px"
                          :color="sub.url === $route.path ? 'var(--sidebar-accent, #C8862D)' : 'grey-5'" />
                      </q-item-section>
                      <q-item-section class="sidebar-sublabel">{{ $t(sub.name) }}</q-item-section>
                      <q-item-section side v-if="sub.add_url">
                        <q-btn icon="add"
                          :color="sub.url === $route.path ? 'var(--sidebar-accent, #C8862D)' : 'grey-5'"
                          size="xs" :to="sub.add_url" round flat dense>
                          <q-tooltip>Add new</q-tooltip>
                        </q-btn>
                      </q-item-section>
                    </q-item>
                  </template>
                </q-list>
              </q-expansion-item>
            </template>

          </template>
        </q-list>
      </q-scroll-area>
    </q-drawer>

    <!-- Global search palette -->
    <q-dialog v-model="searchOpen" position="top" @hide="searchQuery = ''">
      <q-card class="gsearch">
        <q-input ref="searchInput" v-model="searchQuery" borderless autofocus :placeholder="$t('SearchEverything')" @update:model-value="runSearch" class="gsearch__input">
          <template #prepend><q-icon name="search" color="primary" /></template>
          <template #append>
            <q-spinner v-if="searchLoading" color="primary" size="18px" />
            <q-icon v-else-if="searchQuery" name="close" class="cursor-pointer" @click="searchQuery = ''; searchGroups = []" />
          </template>
        </q-input>
        <q-separator />
        <q-card-section class="gsearch__body">
          <div v-if="searchQuery.length < 2" class="text-caption text-grey-6 text-center q-py-md">{{ $t('TypeToSearch') }}</div>
          <div v-else-if="!searchGroups.length && !searchLoading" class="text-caption text-grey-6 text-center q-py-md">{{ $t('NoRecordFound') }}</div>
          <div v-for="g in searchGroups" :key="g.type" class="q-mb-sm">
            <div class="gsearch__group"><q-icon :name="g.icon" size="14px" class="q-mr-xs" />{{ $t(g.type) }}</div>
            <q-list dense>
              <q-item v-for="(it, i) in g.items" :key="i" clickable v-ripple class="rounded-borders" @click="goSearch(it.to)">
                <q-item-section><q-item-label>{{ it.label }}</q-item-label><q-item-label caption>{{ it.sub }}</q-item-label></q-item-section>
                <q-item-section side><q-icon name="arrow_forward" size="15px" color="grey-5" /></q-item-section>
              </q-item>
            </q-list>
          </div>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- My Profile — self-service: photo, phone & password (name/email are admin-only) -->
    <q-dialog v-model="profileOpen">
      <q-card style="width:430px; max-width:94vw" class="my_radio_less">
        <div class="profile-head">
          <q-avatar size="64px" color="white" text-color="primary" style="font-weight:700; font-size:24px">
            <img v-if="profileForm.avatar" :src="profileForm.avatar" />
            <span v-else>{{ (auth.user?.name || 'U').charAt(0).toUpperCase() }}</span>
          </q-avatar>
          <div>
            <div class="text-weight-bold" style="font-size:16px">{{ auth.user?.name }}</div>
            <div style="font-size:12px; opacity:.8">{{ auth.user?.email }}</div>
          </div>
          <q-space />
          <q-btn flat round dense icon="close" color="white" v-close-popup />
        </div>
        <q-card-section class="q-pt-md">
          <div class="row q-col-gutter-sm">
            <div class="col-12">
              <q-file outlined dense color="primary" v-model="avatarFile" :label="$t('ProfilePhoto')" accept="image/*" clearable @update:model-value="onAvatarPick">
                <template #prepend><q-icon name="photo_camera" color="primary" /></template>
              </q-file>
            </div>
            <div class="col-12">
              <q-input outlined dense color="primary" v-model="profileForm.phone" :label="$t('Phone')">
                <template #prepend><q-icon name="phone" color="primary" /></template>
              </q-input>
            </div>
            <div class="col-12"><q-separator class="q-my-xs" /><div class="text-caption text-grey-6">{{ $t('ChangePassword') }}</div></div>
            <div class="col-12">
              <q-input outlined dense color="primary" type="password" v-model="profileForm.current_password" :label="$t('CurrentPassword')" autocomplete="current-password" />
            </div>
            <div class="col-6">
              <q-input outlined dense color="primary" type="password" v-model="profileForm.password" :label="$t('NewPassword')" autocomplete="new-password" />
            </div>
            <div class="col-6">
              <q-input outlined dense color="primary" type="password" v-model="profileForm.password_confirmation" :label="$t('ConfirmPassword')" autocomplete="new-password" />
            </div>
          </div>
        </q-card-section>
        <q-separator />
        <q-card-actions align="right" class="q-pa-md">
          <q-btn flat color="grey-7" :label="$t('Cancel')" v-close-popup no-caps />
          <q-btn unelevated color="primary" icon="save" :label="$t('Save')" :loading="profileSaving" no-caps @click="saveProfile" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <q-page-container>
      <router-view />
    </q-page-container>
  </q-layout>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount, getCurrentInstance } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { Notify } from 'quasar'
import { menus } from './menus.js'
import { useAuthStore } from '@/stores/auth'
import { useUiConfig } from '@/composables/useUiConfig'
import { i18n } from '@/boot/i18n'
import { api } from '@/boot/axios'
import { shamsiDate, fmtDateGregorian } from '@/utils/date'
import BrandMark from '@/components/general/BrandMark.vue'
import CurrencyRateChip from '@/components/general/CurrencyRateChip.vue'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()
const { proxy } = getCurrentInstance()
const { loadUiConfig, hidden: uiHidden, orderOf: uiOrder } = useUiConfig()

const searchMenuItems = ref('')
const leftDrawerOpen = ref(false)
const clock = ref(null)
const company = ref({ abbreviation: null, name_en: null })

const companyName = computed(() =>
  auth.currentCompany?.name_en || company.value.name_en || 'Aria Herat ERP'
)

const routeLabel = computed(() =>
  route.meta?.title ||
  route.name?.toString().replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) ||
  ''
)

const showMenus = computed(() => {
  let allm = JSON.parse(JSON.stringify(menus))
  const can = (p) => proxy.$can(p)

  allm.forEach((e) => {
    // Control Room: an admin can hide a whole group or individual sub-menus.
    if (uiHidden('menu.' + e.room)) e.status = false

    if (e.platform) {
      // VIP Control Center — visible only to the immutable Platform Owner,
      // never through roles/permissions.
      e.status = e.status && auth.isPlatformOwner
    } else if (e.is_sub.length > 0) {
      e.is_sub.forEach((e2) => {
        if (!can(e2.permission) || uiHidden('sub.' + e2.room)) e2.status = false
      })
      if (e.is_sub.filter((v) => v.status).length === 0) e.status = false
    } else if (!can(e.permission)) {
      e.status = false
    }
  })

  allm = allm.filter((v) => v.status)

  // Apply the Control Room ordering (groups, then sub-menus within a group).
  allm.forEach((e, idx) => { e._ord = uiOrder('menu.' + e.room, idx) })
  allm.sort((a, b) => a._ord - b._ord)
  allm.forEach((e) => {
    e.is_sub = (e.is_sub || [])
      .map((s, i) => ({ ...s, _ord: uiOrder('sub.' + s.room, i) }))
      .sort((a, b) => a._ord - b._ord)
  })

  const needle = searchMenuItems.value
  if (needle) {
    const up = needle.toUpperCase()
    allm = allm.filter((m) => {
      if (m.is_sub.length > 0) {
        m.is_sub = m.is_sub.filter((sub) => sub.name.toUpperCase().includes(up))
      }
      return m.is_sub.length > 0 || m.name.toUpperCase().includes(up)
    })
  }
  return allm
})

// Calendar preference — which calendar leads in every date display. Both the
// Gregorian (میلادی) and Afghan solar (خورشیدی) dates are always shown together.
const calendarType = ref(localStorage.getItem('calendar_type') === 'fa' ? 'fa' : 'en')
function toggleCalendar() {
  calendarType.value = calendarType.value === 'fa' ? 'en' : 'fa'
  localStorage.setItem('calendar_type', calendarType.value)
  // Reload so every rendered date picks up the new lead calendar.
  window.location.reload()
}

const locale = computed(() => i18n.locale)
const currentLang = computed(() => ({ en: 'EN', fa: 'فا', pa: 'پښ' })[i18n.locale] || 'EN')
function setLang(lang) {
  i18n.locale = lang
  localStorage.setItem('locale', lang)
  const dir = ['fa', 'pa'].includes(lang) ? 'rtl' : 'ltr'
  document.documentElement.dir = dir
  document.body.dir = dir
  document.documentElement.lang = lang
}

// ── My Profile (self-service) ─────────────────────────────────────────────
const profileOpen = ref(false)
const profileSaving = ref(false)
const avatarFile = ref(null)
const profileForm = reactive({ phone: '', avatar: '', current_password: '', password: '', password_confirmation: '' })

function openProfile () {
  Object.assign(profileForm, {
    phone: auth.user?.phone || '', avatar: auth.user?.avatar || '',
    current_password: '', password: '', password_confirmation: '',
  })
  avatarFile.value = null
  profileOpen.value = true
}

// Resize the picked photo to a small square data-URI (fits the TEXT column).
function onAvatarPick (file) {
  if (!file) { profileForm.avatar = ''; return }
  const reader = new FileReader()
  reader.onload = () => {
    const img = new Image()
    img.onload = () => {
      const size = 160
      const canvas = document.createElement('canvas')
      canvas.width = size; canvas.height = size
      const ctx = canvas.getContext('2d')
      const s = Math.min(img.width, img.height)
      ctx.drawImage(img, (img.width - s) / 2, (img.height - s) / 2, s, s, 0, 0, size, size)
      profileForm.avatar = canvas.toDataURL('image/jpeg', 0.82)
    }
    img.src = reader.result
  }
  reader.readAsDataURL(file)
}

async function saveProfile () {
  if (profileForm.password && profileForm.password !== profileForm.password_confirmation) {
    Notify.create({ type: 'negative', message: proxy.$t('PasswordsDontMatch') }); return
  }
  profileSaving.value = true
  try {
    const payload = { phone: profileForm.phone, avatar: profileForm.avatar }
    if (profileForm.password) {
      payload.current_password = profileForm.current_password
      payload.password = profileForm.password
      payload.password_confirmation = profileForm.password_confirmation
    }
    const { data } = await api.put('/me/profile', payload)
    auth.setSession(data)
    profileOpen.value = false
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: proxy.$t('Saved') })
  } catch (e) {
    Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Failed' })
  } finally { profileSaving.value = false }
}

// Today's date in the header — the lead calendar first, the other beside it.
const headerDate = computed(() => {
  const g = fmtDateGregorian(new Date())
  const s = shamsiDate(new Date())
  return calendarType.value === 'fa' ? `${s} · ${g}` : `${g} · ${s}`
})


const notifications = ref([])
const unreadCount = computed(() => notifications.value.filter(n => !n.read_at).length)

function notifIcon(type) {
  const m = { system: 'settings', info: 'info', approval: 'fact_check', safety: 'health_and_safety', finance: 'payments', hr: 'badge' }
  return m[type] || 'notifications'
}
function notifColor(type) {
  const m = { system: 'grey-7', info: 'cyan-7', approval: 'orange-7', safety: 'red-6', finance: 'green-7', hr: 'teal-7' }
  return m[type] || 'grey-7'
}
// Where each notification takes the user when clicked. An explicit link on the
// notification (data.link) always wins; otherwise we route by its type so every
// notification is actionable even without a stored link.
function notifLink(n) {
  if (n.link) return n.link
  if (n.data && n.data.link) return n.data.link
  const byType = {
    approval: '/site/purchases', safety: '/safety/incidents', finance: '/finance/receipts',
    hr: '/hr/leaves', system: '/notification', info: '/'
  }
  return byType[n.type] || '/notification'
}
async function openNotif(n) {
  // Mark this one read locally, then take the user to the relevant page.
  if (!n.read_at) {
    n.read_at = new Date().toISOString()
    try { await api.post('/notifications/mark-read', { id: n.id }) } catch (_) {}
  }
  const to = notifLink(n)
  if (to && to !== route.path) router.push(to)
}
async function loadNotifications() {
  try {
    const { data } = await api.get('/notifications')
    notifications.value = data
  } catch (_) {}
}
async function markAllRead() {
  try {
    await api.post('/notifications/mark-read')
    notifications.value.forEach(n => n.read_at = new Date().toISOString())
  } catch (_) {}
}

// ── Global search ──
const searchOpen = ref(false)
const searchQuery = ref('')
const searchGroups = ref([])
const searchLoading = ref(false)
let searchTimer = null
function openSearch () { searchOpen.value = true }
function runSearch (q) {
  clearTimeout(searchTimer)
  if (!q || q.length < 2) { searchGroups.value = []; searchLoading.value = false; return }
  searchLoading.value = true
  searchTimer = setTimeout(async () => {
    try { const { data } = await api.get('/search', { params: { q } }); searchGroups.value = data.groups || [] }
    catch (_) { searchGroups.value = [] } finally { searchLoading.value = false }
  }, 250)
}
function goSearch (to) { searchOpen.value = false; searchQuery.value = ''; searchGroups.value = []; router.push(to) }

const branches = ref([])
const seesAllBranches = ref(false)
const activeBranch = ref(null) // numeric branch id, or null = all branches

async function loadBranches() {
  // Reuse the session already loaded by the auth store — no second /user call.
  if (!auth.branches.length && !auth.ready) {
    try { await auth.fetchUser() } catch {}
  }
  branches.value = auth.branches || []
  seesAllBranches.value = !!auth.seesAllBranches
  const saved = localStorage.getItem('active_branch')
  if (saved === 'all') activeBranch.value = null
  else if (saved) activeBranch.value = Number(saved)
  else activeBranch.value = auth.currentBranch || null
  // Keep the axios header in sync with the resolved branch.
  api.setBranch(activeBranch.value === null ? 'all' : activeBranch.value)
}

async function selectBranch(id) {
  const branchId = id === 'all' ? null : id
  if (branchId === activeBranch.value) return
  api.setBranch(branchId === null ? 'all' : branchId)
  try { await api.post('/me/branch', { branch_id: branchId }) } catch {}
  // Reload so every view refetches under the newly selected branch.
  window.location.reload()
}

let clockTimer = null
let notifTimer = null
onMounted(async () => {
  clockTimer = setInterval(() => { clock.value = new Date().toLocaleTimeString() }, 1000)
  const savedLang = localStorage.getItem('locale') || 'en'
  const dir = ['fa', 'pa'].includes(savedLang) ? 'rtl' : 'ltr'
  document.documentElement.dir = dir
  document.body.dir = dir
  document.documentElement.lang = savedLang
  // One session fetch feeds both $can and the branch switcher (no duplicate call).
  if (!auth.user) { try { await auth.fetchUser() } catch {} }
  loadUiConfig()
  loadBranches()
  loadNotifications()
  notifTimer = setInterval(loadNotifications, 60000)
})
onBeforeUnmount(() => {
  if (clockTimer) clearInterval(clockTimer)
  if (notifTimer) clearInterval(notifTimer)
})

async function logout() {
  await auth.logout()
  router.push('/login')
}
</script>

<style lang="scss">
.gsearch { width: 640px; max-width: 96vw; border-radius: 14px; overflow: hidden; margin-top: 8vh; }
.gsearch__input { padding: 6px 14px; font-size: 16px; }
.gsearch__body { max-height: 60vh; overflow-y: auto; }
.gsearch__group { font-size: 11px; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase; color: #94A3B8; padding: 6px 6px 2px; }

/* Logged-in user card in the profile menu */
.user-card { display: flex; gap: 12px; align-items: center; padding: 14px 14px 8px; }
.user-card__name { font-weight: 800; font-size: 15px; color: #0F172A; }
.user-card__mail { font-size: 11.5px; color: #64748B; }
.user-card__meta { margin-top: 4px; }
.user-card__phone { font-size: 11px; color: #64748B; margin-top: 2px; }

/* My Profile dialog header */
.profile-head { display: flex; align-items: center; gap: 14px; padding: 16px; background: linear-gradient(135deg, var(--topbar-from, #123A66), var(--topbar-to, #0B1626)); color: #fff; }

/* Header date chip (replaces the language switcher) */
.header-date { border: 1px solid rgba(255,255,255,.22); border-radius: 18px; padding: 2px 10px; height: 30px; }
.header-date__txt { font-size: 12px; font-weight: 600; letter-spacing: .2px; white-space: nowrap; }
@media (max-width: 720px) { .header-date { display: none; } }

/* ── Top bar ─────────────────────────────────────── */
.topbar-header {
  background: linear-gradient(135deg, var(--topbar-from, #123A66) 0%, var(--topbar-to, #0B1626) 100%);
  box-shadow: 0 2px 12px rgba(0,0,0,.2);
  border-bottom: 1px solid rgba(255,255,255,.15);
}

/* ── Drawer ──────────────────────────────────────── */
.sidebar-drawer {
  background: #ffffff;
  border-right: 1px solid #e8eaf0;
}

.sidebar-brand {
  background: linear-gradient(135deg, var(--sidebar-accent-bg, #FBF0DD) 0%, #f5f5f5 100%);
  border-bottom: 1px solid #e8eaf0;
}

.sidebar-brand-subtitle {
  color: var(--sidebar-accent, #C8862D);
}

/* ── Branch switcher ─────────────────────────────── */
.branch-switcher {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 5px 10px;
  border-radius: 20px;
  background: #f5f5f5;
  border: 1px solid #e0e0e0;
  cursor: pointer;
  transition: background .15s;
  user-select: none;

  &:hover {
    background: var(--sidebar-accent-bg, #FBF0DD);
    border-color: var(--sidebar-accent, #C8862D);
  }

  .branch-switcher-icon {
    color: var(--sidebar-accent, #C8862D);
    flex-shrink: 0;
  }

  .branch-name {
    flex: 1;
    font-size: 12.5px;
    font-weight: 500;
    color: #546e7a;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
}

.branch-option--active {
  background: var(--sidebar-accent-bg, #FBF0DD) !important;
  color: var(--sidebar-accent, #C8862D) !important;
  font-weight: 600;
}

/* ── Menu items ──────────────────────────────────── */
.sidebar-item {
  border-radius: 8px !important;
  min-height: 40px;
  padding: 0 10px;
  transition: background .15s, color .15s;
  color: #546e7a;

  &:hover {
    background: var(--sidebar-accent-bg, #FBF0DD) !important;
    color: var(--sidebar-accent, #C8862D);
  }

  &--active {
    background: var(--sidebar-accent-bg, #FBF0DD) !important;
    color: var(--sidebar-accent, #C8862D) !important;
    font-weight: 600;
    border-left: 3px solid var(--sidebar-accent, #C8862D);
    padding-left: 7px;
  }

  &--danger:hover {
    background: #ffebee !important;
  }
}

.sidebar-label {
  font-size: 13px;
  font-weight: 500;
}

/* ── Group headers ───────────────────────────────── */
.sidebar-group-header {
  border-radius: 8px !important;
  min-height: 40px;
  padding: 0 10px;
  color: #37474f !important;
  font-size: 13px !important;
  font-weight: 600 !important;
  transition: background .15s;

  .q-icon { color: #607d8b !important; }

  &:hover {
    background: #eceff1 !important;
  }

  &.q-expansion-item--expanded {
    background: #eceff1 !important;
    color: var(--sidebar-accent, #C8862D) !important;
    .q-icon { color: var(--sidebar-accent, #C8862D) !important; }
  }
}

/* ── Sub items ───────────────────────────────────── */
.sidebar-sub-item {
  border-radius: 6px !important;
  min-height: 34px;
  padding: 0 8px;
  color: #607d8b;
  transition: background .15s, color .15s;

  &:hover {
    background: var(--sidebar-accent-bg, #FBF0DD) !important;
    color: var(--sidebar-accent, #C8862D);
  }

  &--active {
    background: var(--sidebar-accent-bg, #FBF0DD) !important;
    color: var(--sidebar-accent, #C8862D) !important;
    font-weight: 600;
    border-left: 3px solid var(--sidebar-accent, #C8862D);
    padding-left: 5px;
  }
}

.sidebar-sublabel {
  font-size: 12.5px;
}

.sidebar-expand-icon {
  color: #90a4ae !important;
  font-size: 18px;
}

/* ── Expansion open accent ───────────────────────── */
.q-expansion-item--expanded > .q-expansion-item__container > .sidebar-group-header {
  background: var(--sidebar-accent-bg, #FBF0DD) !important;
  color: var(--sidebar-accent, #C8862D) !important;
  .q-icon { color: var(--sidebar-accent, #C8862D) !important; }
}

/* ── Responsive ──────────────────────────────────── */
@media (max-width: 600px) {
  .mobile-hide { display: none !important; }
}
</style>
