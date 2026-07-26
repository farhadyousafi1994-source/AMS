/**
 * Offline-first boot — starts the sync service after the app mounts.
 * Also applies saved theme settings.
 */
import { defineBoot } from '#q-app'
import { Dark } from 'quasar'
import { syncService } from '@/services/syncService'
import * as localDb from '@/services/localDb'
import SyncStatusBar from '@/components/general/SyncStatusBar.vue'

export default defineBoot(({ app }) => {
  // Register sync status bar globally
  app.component('sync-status', SyncStatusBar)

  // Apply persisted theme on startup
  const savedDark  = localStorage.getItem('theme_dark') === '1'
  const savedColor = localStorage.getItem('theme_primary')
  const savedFont  = localStorage.getItem('theme_font') || 'normal'
  const savedRadius = localStorage.getItem('theme_radius') || '8px'
  const savedSidebar = localStorage.getItem('theme_sidebar')

  if (savedColor) document.documentElement.style.setProperty('--q-primary', savedColor)
  const fontMap = { small: '13px', normal: '14px', large: '16px' }
  document.documentElement.style.fontSize = fontMap[savedFont] || '14px'
  document.documentElement.style.setProperty('--my-radius', savedRadius)

  if (savedDark) Dark.set(true)
  if (savedSidebar === 'mini') document.body.classList.add('sidebar-mini')

  // Apply persisted topbar / sidebar accent CSS vars
  const savedTopbarFrom = localStorage.getItem('theme_topbar_from') || '#123A66'
  const savedTopbarTo   = localStorage.getItem('theme_topbar_to') || '#0B1626'
  const savedAccent     = localStorage.getItem('theme_sidebar_accent') || '#C8862D'
  const savedAccentBg   = localStorage.getItem('theme_sidebar_accent_bg') || '#FBF0DD'
  document.documentElement.style.setProperty('--topbar-from', savedTopbarFrom)
  document.documentElement.style.setProperty('--topbar-to', savedTopbarTo)
  document.documentElement.style.setProperty('--sidebar-accent', savedAccent)
  document.documentElement.style.setProperty('--sidebar-accent-bg', savedAccentBg)

  // Start sync service (it connects online/offline listeners)
  if (typeof window !== 'undefined') {
    syncService.start()
    window.__ariaSync = { service: syncService, db: localDb }
  }
})
