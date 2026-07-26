import { defineStore } from 'pinia'
import { api } from '@/boot/axios'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    permissions: [],
    roles: [],
    isPlatformOwner: false,
    branches: [],
    seesAllBranches: false,
    currentBranch: null,
    ready: false
  }),

  getters: {
    isAuthenticated: (state) => !!state.user,
    currentCompany: (state) => state.user?.company || null,
    can: (state) => (perm) => state.permissions.includes(perm) || state.roles.includes('Super Admin')
  },

  actions: {
    async login (credentials) {
      const { data } = await api.post('/login', credentials)
      if (data.token) api.setToken(data.token, credentials.remember !== false)
      this.setSession(data)
      return data
    },

    async fetchUser () {
      try {
        const { data } = await api.get('/user')
        this.setSession(data)
      } catch {
        this.clear()
      } finally {
        this.ready = true
      }
    },

    setSession (data) {
      this.user = data.user
      this.permissions = data.permissions || []
      this.roles = data.roles || []
      this.isPlatformOwner = !!data.is_platform_owner
      // Branch info travels on the same payload — cache it so the layout does
      // not need a second /user round-trip (keeps first paint fast).
      if ('branches' in data) this.branches = data.branches || []
      if ('sees_all_branches' in data) this.seesAllBranches = !!data.sees_all_branches
      if ('current_branch' in data) this.currentBranch = data.current_branch ?? null
    },

    async logout () {
      try { await api.post('/logout') } catch { /* ignore */ }
      api.setToken(null)
      this.clear()
    },

    clear () {
      this.user = null
      this.permissions = []
      this.roles = []
      this.isPlatformOwner = false
      api.setToken(null)
    }
  }
})
