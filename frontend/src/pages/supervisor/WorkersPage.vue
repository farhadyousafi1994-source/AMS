<template>
  <q-page>
    <m-backgrounds>
      <div class="row my_radio_less q-pa-sm">
        <div class="col-12">
          <m-header icon="badge" controlRoomButton="false" class="q-mt-xs">{{ $t('Workers') }}</m-header>
        </div>

        <div class="col-12 q-mt-md">
          <div class="row q-col-gutter-md">
            <div class="col-6 col-md-3"><stat-card icon="groups" :label="$t('Workers')" :value="rows.length" color="#0D9488" tint="#CCFBF1" :sub="$t('Registered')" sub-icon="how_to_reg" /></div>
            <div class="col-6 col-md-3"><stat-card icon="engineering" :label="$t('Active')" :value="activeCount" color="#16A34A" tint="#DCFCE7" :sub="$t('OnTheCrew')" sub-icon="check" /></div>
          </div>
        </div>

        <action-bar :rows="rows" :columns="exportColumns" filename="workers" create-perm="worker-create" @add="openCreate" @update:filtered="() => {}">
          <template #filters>
            <div class="col-6 col-sm-3"><q-select outlined dense color="blue-grey-9" label-color="blue-grey-9" v-model="projectFilter" :options="projectOptions" emit-value map-options clearable :label="$t('Project')" @update:model-value="load" /></div>
          </template>
        </action-bar>
        <div class="col-12">
          <n-table config-key="page.workers" :loading="loading" :data="rows" :columns="columns" v-model:filter="tableFilter"
            :can_edit="'worker-edit'" :can_delete="'worker-delete'" :noInfo="true" @edit="openEdit" @del="remove">
            <template v-slot:body-cell-photo="props">
              <q-td :props="props">
                <q-avatar size="34px" color="teal-2" text-color="teal-9">
                  <img v-if="photos[props.row.id]" :src="photos[props.row.id]" />
                  <span v-else>{{ (props.row.name || '؟').slice(0, 1) }}</span>
                </q-avatar>
              </q-td>
            </template>
            <template v-slot:body-cell-active="props">
              <q-td :props="props" class="text-center">
                <q-chip dense size="sm" :color="props.row.active ? 'green-1' : 'grey-3'" :text-color="props.row.active ? 'green-9' : 'grey-8'">{{ props.row.active ? $t('Active') : $t('Inactive') }}</q-chip>
              </q-td>
            </template>
          </n-table>
        </div>
      </div>
    </m-backgrounds>

    <m-modal :showCM="dialog" @update:showCM="dialog = $event" card_style="width: 520px">
      <q-card class="bg-white">
        <n-header icon="badge">{{ form.id ? $t('Edit') : $t('RegisterWorker') }}</n-header>
        <q-separator />
        <q-form @submit="save">
          <q-card-section class="row q-col-gutter-sm">
            <div class="col-12 col-sm-6" v-if="!form.id"><q-select outlined dense color="primary" v-model="form.project_id" :options="projectOptions" emit-value map-options :label="$t('Project')" :rules="[v => !!v || $t('FieldIsRequired')]" /></div>
            <div class="col-12 col-sm-6"><n-name :name="form.name" @update:name="form.name = $event" icon="person" :label="$t('Name')" /></div>
            <div class="col-12 col-sm-6"><n-name :name="form.father_name" @update:name="form.father_name = $event" icon="family_restroom" :label="$t('FatherName')" :rules="[]" /></div>
            <div class="col-12 col-sm-6"><n-name :name="form.phone" @update:name="form.phone = $event" icon="phone" :label="$t('Phone')" :rules="[]" /></div>
            <div class="col-12 col-sm-6"><n-name :name="form.trade" @update:name="form.trade = $event" icon="construction" :label="$t('Trade')" :rules="[]" /></div>
            <div class="col-12 col-sm-6"><q-input outlined dense color="primary" type="number" step="any" v-model.number="form.default_wage" :label="$t('DefaultWage')" /></div>
            <div class="col-12" v-if="!form.id">
              <q-file outlined dense color="primary" v-model="photoFile" :label="$t('WorkerPhoto')" accept="image/*" max-file-size="41943040" clearable>
                <template #prepend><q-icon name="photo_camera" color="primary" /></template>
              </q-file>
            </div>
          </q-card-section>
          <q-separator />
          <n-submit :submitting="saving" :label="$t('Save')" />
        </q-form>
      </q-card>
    </m-modal>
  </q-page>
</template>

<script setup>
import { ref, reactive, computed, getCurrentInstance, onMounted } from 'vue'
import { Notify } from 'quasar'
import { api } from '@/boot/axios'
import { compressImage } from '@/utils/image'

const { proxy } = getCurrentInstance()

const rows = ref([])
const loading = ref(false)
const saving = ref(false)
const dialog = ref(false)
const tableFilter = ref('')
const projectFilter = ref(null)
const projectOptions = ref([])
const photos = reactive({})
const photoFile = ref(null)

const activeCount = computed(() => rows.value.filter(r => r.active).length)

const columns = [
  { name: 'photo', label: '', field: 'photo', align: 'left' },
  { name: 'code', label: 'Code', field: 'code', align: 'left', sortable: true },
  { name: 'name', label: 'Name', field: 'name', align: 'left', sortable: true },
  { name: 'father_name', label: 'FatherName', field: 'father_name', align: 'left' },
  { name: 'trade', label: 'Trade', field: 'trade', align: 'left' },
  { name: 'project', label: 'Project', field: r => r.project?.name, align: 'left' },
  { name: 'attendances_count', label: 'Days', field: 'attendances_count', align: 'center' },
  { name: 'active', label: 'Status', field: 'active', align: 'center' },
  { name: 'actions', label: 'Actions', field: 'actions', align: 'right' },
]
const exportColumns = columns.filter(c => !['photo', 'actions'].includes(c.name))

const blank = () => ({ id: null, project_id: null, name: '', father_name: '', phone: '', trade: '', default_wage: 0 })
const form = reactive(blank())

async function load () {
  loading.value = true
  try {
    const params = projectFilter.value ? { project_id: projectFilter.value } : {}
    const { data } = await api.get('/workers', { params })
    rows.value = Array.isArray(data) ? data : []
    rows.value.forEach(loadPhoto)
  } finally { loading.value = false }
}
async function loadPhoto (w) {
  if (photos[w.id] || !w.photo_mime?.startsWith('image/')) return
  try { const res = await api.get('/workers/' + w.id + '/photo', { responseType: 'blob' }); photos[w.id] = URL.createObjectURL(new Blob([res.data], { type: w.photo_mime })) } catch (_) {}
}
async function loadProjects () { try { const { data } = await api.get('/projects'); projectOptions.value = (data || []).map(p => ({ label: p.name, value: p.id })) } catch (_) {} }

function openCreate () { Object.assign(form, blank()); photoFile.value = null; dialog.value = true }
function openEdit (id) {
  const r = rows.value.find(x => x.id === id); if (!r) return
  Object.assign(form, { id: r.id, name: r.name, father_name: r.father_name || '', phone: r.phone || '', trade: r.trade || '', default_wage: Number(r.default_wage || 0), active: r.active })
  dialog.value = true
}
async function save () {
  saving.value = true
  try {
    if (form.id) {
      await api.put('/workers/' + form.id, { name: form.name, father_name: form.father_name, phone: form.phone, trade: form.trade, default_wage: form.default_wage, active: form.active ?? true })
    } else {
      const fd = new FormData()
      Object.entries(form).forEach(([k, v]) => { if (v !== null && v !== '' && k !== 'id') fd.append(k, v) })
      if (photoFile.value) fd.append('photo', await compressImage(photoFile.value))
      await api.post('/workers', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    }
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: 'Saved' })
    dialog.value = false; load()
  } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Save failed' }) } finally { saving.value = false }
}
function remove (id) { proxy.$delete('workers/' + id, load) }

onMounted(() => { load(); loadProjects() })
</script>
