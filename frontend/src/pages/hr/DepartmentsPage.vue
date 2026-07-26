<template>
  <q-page>
    <m-backgrounds>
      <div class="row my_radio_less q-pa-sm">
        <div class="col-12">
          <m-header icon="apartment" controlRoomButton="false" class="q-mt-xs">
            {{ $t('Departments') }}
          </m-header>
        </div>

        <div class="col-12 q-mt-xs" v-if="$can('department-create')">
          <progress-btn color="teal" icon="add" @click="openCreate">{{ $t('AddNew') }}</progress-btn>
        </div>

        <div class="col-12 q-mt-sm">
          <q-markup-table flat bordered dense class="my_radio_less">
            <thead class="bg-theme-soft">
              <tr>
                <th class="text-left">{{ $t('DepartmentName') }}</th>
                <th class="text-left">{{ $t('Manager') }}</th>
                <th class="text-center">{{ $t('Designations') }}</th>
                <th class="text-center">{{ $t('Status') }}</th>
                <th class="text-right">{{ $t('Actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading"><td colspan="5" class="text-center q-py-md"><q-spinner color="primary" size="2em" /></td></tr>
              <tr v-else-if="rows.length === 0"><td colspan="5" class="text-center text-grey-5 q-py-md">{{ $t('NoRecordFound') }}</td></tr>
              <tr v-for="d in rows" :key="d.id">
                <td class="text-weight-medium">{{ d.name }}</td>
                <td>{{ d.manager || '—' }}</td>
                <td class="text-center"><q-badge color="blue-grey-2" text-color="blue-grey-9">{{ d.designations_count ?? 0 }}</q-badge></td>
                <td class="text-center">
                  <q-chip dense size="sm" :color="d.active ? 'positive' : 'grey'" text-color="white">{{ d.active ? $t('Active') : $t('Inactive') }}</q-chip>
                </td>
                <td class="text-right" style="white-space:nowrap">
                  <q-btn size="sm" dense flat round icon="edit" color="blue-8" v-if="$can('department-edit')" @click="openEdit(d)" />
                  <q-btn size="sm" dense flat round icon="delete" color="negative" v-if="$can('department-delete')" @click="remove(d)" />
                </td>
              </tr>
            </tbody>
          </q-markup-table>
        </div>
      </div>
    </m-backgrounds>

    <m-modal :showCM="dialog" @update:showCM="dialog = $event" card_style="width: 440px">
      <q-card class="bg-white">
        <n-header icon="apartment">{{ form.id ? $t('Edit') : $t('AddNew') }} — {{ $t('Department') }}</n-header>
        <q-separator />
        <q-form @submit="save">
          <q-card-section class="row q-col-gutter-sm">
            <div class="col-12"><n-name :name="form.name" @update:name="form.name = $event" icon="apartment" :label="$t('DepartmentName')" autofocus /></div>
            <div class="col-12"><n-name :name="form.manager" @update:name="form.manager = $event" icon="person" :label="$t('Manager')" :rules="[]" /></div>
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
import { ref, reactive, getCurrentInstance, onMounted } from 'vue'
import { Notify } from 'quasar'
import { api } from '@/boot/axios'

const { proxy } = getCurrentInstance()
const rows = ref([])
const loading = ref(false)
const saving = ref(false)
const dialog = ref(false)
const blank = () => ({ id: null, name: '', manager: '', description: '', active: true })
const form = reactive(blank())

async function load () {
  loading.value = true
  try { const { data } = await api.get('/departments'); rows.value = data } finally { loading.value = false }
}
function openCreate () { Object.assign(form, blank()); dialog.value = true }
function openEdit (d) { Object.assign(form, { id: d.id, name: d.name, manager: d.manager || '', description: d.description || '', active: !!d.active }); dialog.value = true }
async function save () {
  saving.value = true
  try {
    const payload = { name: form.name, manager: form.manager, description: form.description, active: form.active }
    if (form.id) await api.put('/departments/' + form.id, payload)
    else await api.post('/departments', payload)
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: 'Saved' })
    dialog.value = false; load()
  } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Save failed' }) } finally { saving.value = false }
}
function remove (d) { proxy.$delete('departments/' + d.id, load) }
onMounted(load)
</script>
