import { ref } from 'vue'
import { api } from '@/boot/axios'

// Control Room state: the per-company map of interface overrides. Every part
// of the app (sidebar, page tabs, inputs, table features) reads visibility and
// order from here, and the Control Room page writes to it. Changes apply live
// and persist immediately, so the interface reshapes the moment a toggle flips.
//   key examples: 'menu.ProjectsGroup', 'sub.ChangeOrders',
//                 'page.projects.tab.financing', 'page.projects.table.advanced_search'
const config = ref({})   // { key: { hidden, sort_order, label_override } }
let loaded = false
let inflight = null

export function useUiConfig () {
  async function loadUiConfig (force = false) {
    if (loaded && !force) return
    if (inflight) return inflight
    inflight = (async () => {
      try {
        const { data } = await api.get('/ui-settings')
        config.value = data || {}
        loaded = true
      } catch (_) { /* keep whatever we have; everything defaults to visible */ } finally {
        inflight = null
      }
    })()
    return inflight
  }

  function entry (key) { return config.value[key] || {} }
  function hidden (key) { return !!entry(key).hidden }
  function visible (key) { return !hidden(key) }
  function labelOf (key) { return entry(key).label_override || '' }
  function orderOf (key, fallback = 0) {
    const v = entry(key).sort_order
    return v == null ? fallback : v
  }

  // ── Rich per-element props (enabled/required/readonly/expanded/…) ──
  function prop (key, name, fallback = undefined) {
    const p = entry(key).props || {}
    return p[name] === undefined ? fallback : p[name]
  }
  function enabled (key) { return prop(key, 'disabled', false) !== true }
  function required (key, fallback = false) { return !!prop(key, 'required', fallback) }
  function readonly (key) { return !!prop(key, 'readonly', false) }
  function expandedDefault (key, fallback = false) { return !!prop(key, 'expanded', fallback) }
  /** A label to show, honouring an override, else the given default. */
  function label (key, fallback) { return labelOf(key) || fallback }

  /** Merge a set of override patches into the live map (no persistence). */
  function apply (patches) {
    const next = { ...config.value }
    patches.forEach((p) => {
      next[p.key] = { ...(next[p.key] || {}), ...p }
    })
    config.value = next
  }

  /** Persist a batch of overrides and apply them live. */
  async function save (patches) {
    apply(patches)
    const settings = patches.map((p) => ({
      key: p.key,
      hidden: config.value[p.key]?.hidden ?? false,
      sort_order: config.value[p.key]?.sort_order ?? null,
      label_override: config.value[p.key]?.label_override ?? null,
      props: config.value[p.key]?.props ?? null,
    }))
    try {
      const { data } = await api.post('/ui-settings/bulk', { settings })
      if (data) config.value = data
    } catch (_) { /* live change stays; will re-sync on next load */ }
  }

  function setHidden (key, val) { return save([{ key, hidden: val }]) }
  function setOrder (patches) { return save(patches) }
  /** Set one flag inside an element's props (disabled, required, readonly, expanded, …). */
  function setProp (key, name, val) {
    const props = { ...(entry(key).props || {}), [name]: val }
    return save([{ key, props }])
  }

  async function resetAll () {
    try { await api.delete('/ui-settings') } catch (_) {}
    config.value = {}
  }

  return {
    config, loadUiConfig, hidden, visible, labelOf, label, orderOf,
    prop, enabled, required, readonly, expandedDefault,
    apply, save, setHidden, setOrder, setProp, resetAll,
  }
}
