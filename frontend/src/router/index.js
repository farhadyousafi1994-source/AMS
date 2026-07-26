import { defineRouter } from '#q-app'
import { createRouter, createWebHistory, createWebHashHistory, createMemoryHistory } from 'vue-router'
import routes from './routes.js'
import { useAuthStore } from '@/stores/auth'

export default defineRouter(() => {
  const createHistory = import.meta.env.QUASAR_SERVER
    ? createMemoryHistory
    : import.meta.env.QUASAR_VUE_ROUTER_MODE === 'history'
      ? createWebHistory
      : createWebHashHistory

  const Router = createRouter({
    scrollBehavior: () => ({ left: 0, top: 0 }),
    routes,
    history: createHistory(import.meta.env.QUASAR_VUE_ROUTER_BASE)
  })

  Router.beforeEach(async (to) => {
    const auth = useAuthStore()
    if (!auth.ready) await auth.fetchUser()

    if (to.meta.requiresAuth && !auth.isAuthenticated) {
      return { name: 'login' }
    }
    if (to.meta.guest && auth.isAuthenticated) {
      return { name: 'dashboard' }
    }
    if (to.meta.permission && !auth.can(to.meta.permission)) {
      return { name: 'dashboard' }
    }
    // Platform Owner only routes — never reachable by tenant roles.
    if (to.meta.platform && !auth.isPlatformOwner) {
      return { name: 'dashboard' }
    }
  })

  return Router
})
