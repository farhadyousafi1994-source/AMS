import { ref } from 'vue'
import { api } from '@/boot/axios'
import { i18n } from '@/boot/i18n'

// Shared, cached view of the Options Registry (the `lookups` table). Every
// dropdown in the system — units, project types, statuses, phases, provinces,
// payment methods … — is served from here, bilingual (EN + Dari) and fully
// editable from the admin "Options Registry" page. Load once, use everywhere.
const groups = ref({})   // { unit: [ {code,label_en,label_fa,…}, … ], … }
let loaded = false
let inflight = null

export function useLookups () {
  async function loadLookups (force = false) {
    if (loaded && !force) return
    if (inflight) return inflight
    inflight = (async () => {
      try {
        const { data } = await api.get('/lookups')
        groups.value = data || {}
        loaded = true
      } catch (_) {
        // leave whatever we had; callers fall back to their local defaults
      } finally {
        inflight = null
      }
    })()
    return inflight
  }

  /** Raw rows for a group (empty array if not loaded / unknown group). */
  function rows (group) {
    return groups.value[group] || []
  }

  /** Localised display label for a row, honouring the current locale. */
  function labelOf (row) {
    if (!row) return ''
    const fa = i18n.locale === 'fa' || i18n.locale === 'pa'
    return (fa && row.label_fa) ? row.label_fa : (row.label_en || row.label_fa || row.code)
  }

  /**
   * q-select ready options for a group: [{ label, value, label_en, label_fa }].
   * `value` is the stable machine code so stored data never breaks on relabel.
   */
  function options (group) {
    return rows(group).map((r) => ({
      label: labelOf(r),
      value: r.code,
      label_en: r.label_en,
      label_fa: r.label_fa,
    }))
  }

  /** Resolve a stored code back to its localised label (for tables/show pages). */
  function label (group, code) {
    if (code == null || code === '') return ''
    const row = rows(group).find((r) => r.code === code)
    return row ? labelOf(row) : String(code)
  }

  return { groups, loadLookups, options, label, rows, labelOf }
}
