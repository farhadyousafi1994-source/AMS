<template>
  <q-page>
    <m-backgrounds>
      <div class="row my_radio_less q-pa-sm">
        <div class="col-12">
          <m-header icon="badge" controlRoomButton="false" class="q-mt-xs">
            {{ $t('Designations') }}
          </m-header>
        </div>

        <div class="col-12 q-mt-xs row items-center q-gutter-sm">
          <progress-btn v-if="$can('designation-create')" color="teal" icon="add" @click="openCreate">{{ $t('AddNew') }}</progress-btn>
          <q-select outlined dense color="primary" v-model="deptFilter" :options="deptOptions" emit-value map-options clearable :label="$t('Department')" style="min-width:200px" />
        </div>

        <div class="col-12 q-mt-sm">
          <q-markup-table flat bordered dense class="my_radio_less">
            <thead class="bg-theme-soft">
              <tr>
                <th class="text-left">{{ $t('DesignationTitle') }}</th>
                <th class="text-left">{{ $t('Department') }}</th>
                <th class="text-center">{{ $t('Status') }}</th>
                <th class="text-right">{{ $t('Actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading"><td colspan="4" class="text-center q-py-md"><q-spinner color="primary" size="2em" /></td></tr>
              <tr v-else-if="filtered.length === 0"><td colspan="4" class="text-center text-grey-5 q-py-md">{{ $t('NoRecordFound') }}</td></tr>
              <tr v-for="d in filtered" :key="d.id">
                <td class="text-weight-medium">{{ d.title }}</td>
                <td>{{ d.department?.name || '—' }}</td>
                <td class="text-center">
                  <q-chip dense size="sm" :color="d.active ? 'positive' : 'grey'" text-color="white">{{ d.active ? $t('Active') : $t('Inactive') }}</q-chip>
                </td>
                <td class="text-right" style="white-space:nowrap">
                  <q-btn size="sm" dense flat round icon="edit" color="blue-8" v-if="$can('designation-edit')" @click="openEdit(d)" />
                  <q-btn size="sm" dense flat round icon="delete" color="negative" v-if="$can('designation-delete')" @click="remove(d)" />
                </td>
              </tr>
            </tbody>
          </q-markup-table>
        </div>
      </div>
    </m-backgrounds>

    <m-modal :showCM="dialog" @update:showCM="dialog = $event" card_style="width: 440px">
      <q-card class="bg-white">
        <n-header icon="badge">{{ form.id ? $t('Edit') : $t('AddNew') }} — {{ $t('Designation') }}</n-header>
        <q-separator />
        <q-form @submit="save">
          <q-card-section class="row q-col-gutter-sm">
            <div class="col-12"><n-name :name="form.title" @update:name="form.title = $event" icon="badge" :label="$t('DesignationTitle')" autofocus /></div>
            <div class="col-12">
              <q-select outlined dense color="primary" label-color="primary" v-model="form.department_id"
                :options="deptOptions" emit-value map-options :label="$t('Department')"
                :rules="[v => !!v || $t('FieldIsRequired')]" hide-bottom-space>
                <template #prepend><q-icon name="apartment" color="primary" /></template>
              </q-select>
            </div>
            <div class="col-12"><q-input outlined dense color="primary" type="textarea" autogrow v-model="form.description" :label="$t('Description')" /></div>
            <div class="col-12"><q-toggle v-model="form.active" :label="$t('Active')" color="primary" /></div>
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

const { proxy } = getCurrentInstance()
const rows = ref([])
const deptOptions = ref([])
const deptFilter = ref(null)
const loading = ref(false)
const saving = ref(false)
const dialog = ref(false)
const blank = () => ({ id: null, title: '', department_id: null, description: '', active: true })
const form = reactive(blank())

const filtered = computed(() => deptFilter.value ? rows.value.filter(r => r.department_id === deptFilter.value) : rows.value)

async function loadDepts () {
  try { const { data } = await api.get('/departments'); deptOptions.value = (data || []).map(d => ({ label: d.name, value: d.id })) } catch (_) {}
}
async function load () {
  loading.value = true
  try { const { data } = await api.get('/designations'); rows.value = data } finally { loading.value = false }
}
function openCreate () { Object.assign(form, blank()); dialog.value = true }
function openEdit (d) { Object.assign(form, { id: d.id, title: d.title, department_id: d.department_id, description: d.description || '', active: !!d.active }); dialog.value = true }
async function save () {
  saving.value = true
  try {
    const payload = { title: form.title, department_id: form.department_id, description: form.description, active: form.active }
    if (form.id) await api.put('/designations/' + form.id, payload)
    else await api.post('/designations', payload)
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: 'Saved' })
    dialog.value = false; load()
  } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Save failed' }) } finally { saving.value = false }
}
function remove (d) { proxy.$delete('designations/' + d.id, load) }
onMounted(() => { loadDepts(); load() })
</script>
