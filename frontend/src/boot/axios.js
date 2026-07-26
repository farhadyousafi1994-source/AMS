import { defineBoot } from '#q-app'
import axios from 'axios'

// When running inside Electron, main process injects __ELECTRON_API_URL__
// before the page loads. Fall back to VITE_API_URL env var, then localhost.
const API_URL = (
  (typeof window !== 'undefined' && window.__ELECTRON_API_URL__) ||
  import.meta.env.VITE_API_URL ||
  'http://localhost:8000'
).replace(/\/$/, '')

const api = axios.create({
  baseURL: `${API_URL}/api`,
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: 'application/json' }
})

// Token-based auth (works under Electron file:// where cookies are unreliable).
// Restore a saved token on startup. "Remember me" decides where the token lives:
//   • localStorage   → survives browser/app restarts (remembered)
//   • sessionStorage → cleared when the tab/window closes (not remembered)
const savedToken = (typeof localStorage !== 'undefined')
  ? (localStorage.getItem('auth_token') || sessionStorage.getItem('auth_token'))
  : null
if (savedToken) {
  api.defaults.headers.common.Authorization = `Bearer ${savedToken}`
}

// Active branch (multi-branch). Sent on every request so the API scopes data
// to the branch chosen in the header switcher. 'all' = cross-branch view.
const savedBranch = (typeof localStorage !== 'undefined') ? localStorage.getItem('active_branch') : null
if (savedBranch) {
  api.defaults.headers.common['X-Branch-Id'] = savedBranch
}

api.setBranch = (id) => {
  const val = (id === null || id === undefined || id === 'all') ? 'all' : String(id)
  localStorage.setItem('active_branch', val)
  api.defaults.headers.common['X-Branch-Id'] = val
}

// remember === true  → persist in localStorage (survives restart)
// remember === false → sessionStorage only (dropped when the window closes)
// remember omitted   → keep it wherever it already lives (default true)
api.setToken = (token, remember = true) => {
  if (token) {
    const primary = remember ? localStorage : sessionStorage
    const secondary = remember ? sessionStorage : localStorage
    primary.setItem('auth_token', token)
    secondary.removeItem('auth_token')
    api.defaults.headers.common.Authorization = `Bearer ${token}`
  } else {
    localStorage.removeItem('auth_token')
    sessionStorage.removeItem('auth_token')
    delete api.defaults.headers.common.Authorization
  }
}

// Some shared hosts (LiteSpeed / Apache mod_security) block the DELETE, PUT and
// PATCH verbs outright and answer 403. Tunnel those over POST with a standard
// method-override header — Laravel/Symfony transparently resolves the request
// back to the real verb, so all delete/edit actions keep working everywhere.
api.interceptors.request.use((config) => {
  const method = (config.method || 'get').toLowerCase()
  if (method === 'delete' || method === 'put' || method === 'patch') {
    config.headers = config.headers || {}
    config.headers['X-HTTP-Method-Override'] = method.toUpperCase()
    config.method = 'post'
  }
  return config
})

// No-op CSRF for desktop token auth; kept so existing callers don't break.
api.getCsrf = () => Promise.resolve()

export default defineBoot(({ app }) => {
  app.config.globalProperties.$api = api
})

export { api }
