import { defineBoot } from '#q-app'
import { Notify, Dialog } from 'quasar'
import { api } from '@/boot/axios'
import { useAuthStore } from '@/stores/auth'
import { fmtDate, fmtDateTime, shamsiDate, dualDate } from '@/utils/date'

// Reusable components ported from the legacy app, registered globally under the
// same short aliases the old pages used so templates port over with minimal edits.
import AppHeader from '@/components/Headers/AppHeader.vue'
import ProgressButton from '@/components/Buttons/ProgressButton.vue'
import PageBackground from '@/components/general/PageBackground.vue'
import MainModal from '@/components/general/MainModal.vue'
import ModalHeader from '@/components/general/ModalHeader.vue'
import DataTable from '@/components/tables/DataTable.vue'
import NameField from '@/components/fields/NameField.vue'
import NameSimple from '@/components/fields/NameSimple.vue'
import SubmitButtons from '@/components/fields/SubmitButtons.vue'
import SelectAdd from '@/components/fields/SelectAdd.vue'
import LookupSelect from '@/components/fields/LookupSelect.vue'
import MoneyInput from '@/components/fields/MoneyInput.vue'
import SubcontractorSelect from '@/components/fields/SubcontractorSelect.vue'
import FingerprintVerify from '@/components/fingerprint/FingerprintVerify.vue'
import ExportBtn from '@/components/general/ExportBtn.vue'
import ActionBar from '@/components/general/ActionBar.vue'
import StatCard from '@/components/general/StatCard.vue'
import ShamsiDatePicker from '@/components/general/ShamsiDatePicker.vue'
import DualDate from '@/components/general/DualDate.vue'
import ShortcutsPanel from '@/components/general/ShortcutsPanel.vue'
import TabTitle from '@/components/TabTitle.vue'
import AttachmentBox from '@/components/AttachmentBox.vue'
import AvatarUpload from '@/components/AvatarUpload.vue'
import ProjectMap from '@/components/ProjectMap.vue'

export default defineBoot(({ app }) => {
  const auth = useAuthStore()

  // Toasts appear bottom-centre with a soft rounded look (not the old corner).
  Notify.setDefaults({
    position: 'bottom',
    timeout: 2600,
    progress: true,
    classes: 'app-toast',
    actions: [{ icon: 'close', color: 'white', round: true, dense: true }]
  })

  // Date formatting helpers available in all templates as $fmtDate / $fmtDateTime.
  // $fmtDate now renders both calendars (Gregorian · Afghan solar); $shamsi and
  // $dual are available for finer control.
  app.config.globalProperties.$fmtDate = fmtDate
  app.config.globalProperties.$fmtDateTime = fmtDateTime
  app.config.globalProperties.$shamsi = shamsiDate
  app.config.globalProperties.$dual = dualDate

  // Legacy global helpers
  app.config.globalProperties.$axios = api
  app.config.globalProperties.$api = api

  // Permission check (Super Admin bypasses, matching the store getter)
  app.config.globalProperties.$can = (permission) => {
    if (!permission) return true
    return auth.can(permission)
  }

  // Confirm + delete helper used as `this.$delete('resource/id')`
  app.config.globalProperties.$delete = (url, onDone) => {
    Dialog.create({
      title: 'Delete',
      message: 'Are you sure you want to delete this record?',
      cancel: true,
      persistent: true,
      ok: { label: 'Delete', color: 'negative', unelevated: true }
    }).onOk(async () => {
      try {
        await api.delete(`/${String(url).replace(/^\//, '')}`)
        Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: 'Deleted successfully' })
        if (typeof onDone === 'function') onDone()
      } catch (e) {
        Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Delete failed' })
      }
    })
  }

  // Global component aliases (match legacy local names)
  app.component('m-header', AppHeader)
  app.component('progress-btn', ProgressButton)
  app.component('m-backgrounds', PageBackground)
  app.component('m-modal', MainModal)
  app.component('n-header', ModalHeader)
  app.component('n-table', DataTable)
  app.component('n-name', NameField)
  app.component('n-simple', NameSimple)
  app.component('n-submit', SubmitButtons)
  app.component('n-select-add', SelectAdd)
  app.component('lookup-select', LookupSelect)
  app.component('money-input', MoneyInput)
  app.component('subcontractor-select', SubcontractorSelect)
  app.component('fp-verify', FingerprintVerify)
  app.component('export-btn', ExportBtn)
  app.component('action-bar', ActionBar)
  app.component('stat-card', StatCard)
  app.component('shamsi-date', ShamsiDatePicker)
  app.component('dual-date', DualDate)
  app.component('shortcuts-panel', ShortcutsPanel)
  app.component('tab-title', TabTitle)
  app.component('attach-box', AttachmentBox)
  app.component('avatar-box', AvatarUpload)
  app.component('project-map', ProjectMap)
})
