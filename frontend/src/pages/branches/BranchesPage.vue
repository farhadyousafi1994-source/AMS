<template>
  <q-page>
    <m-backgrounds>
      <div class="row my_radio_less q-pa-sm">
        <div class="col-12">
          <m-header icon="store" controlRoomButton="false" class="q-mt-xs">
            {{ $t('Branches') }}
          </m-header>
        </div>

        <action-bar
          :rows="rows"
          :columns="columns"
          filename="branches"
          create-perm="branch-create"
          @add="openCreate"
          @update:filtered="filteredRows = $event"
        />
        <div class="col-12">
          <n-table config-key="page.branches"
            :loading="loading"
            :data="filteredRows"
            :columns="columns"
            v-model:filter="filter"
            :can_edit="'branch-edit'"
            :can_delete="'branch-delete'"
            @edit="openEdit"
            @del="remove"
          >
            <template v-slot:body-cell-active="props">
              <q-td :props="props">
                <q-chip dense :color="props.row.active ? 'positive' : 'grey'" text-color="white"
                  :label="props.row.active ? $t('Active') : $t('Inactive')" />
              </q-td>
            </template>
          </n-table>
        </div>
      </div>
    </m-backgrounds>

    <m-modal :showCM="dialog" @update:showCM="dialog = $event" card_style="width: 480px">
      <q-card class="bg-white">
        <n-header icon="store">{{ editing ? $t('Edit') : $t('AddNew') }} — {{ $t('Branch') }}</n-header>
        <q-separator />
        <q-form @submit="save" @reset="resetForm">
          <q-card-section class="row q-col-gutter-sm">
            <div class="col-12 col-sm-6">
              <n-name :name="form.name" @update:name="form.name = $event" icon="store" :label="$t('Name')" autofocus />
            </div>
            <div class="col-12 col-sm-6">
              <n-name :name="form.phone" @update:name="form.phone = $event" icon="phone" :label="$t('Phone')" :rules="[]" />
            </div>
            <div class="col-12 col-sm-6 flex items-center">
              <q-toggle v-model="form.active" :label="$t('Active')" color="primary" />
            </div>
            <div class="col-12">
              <n-name :name="form.address" @update:name="form.address = $event" icon="home" :label="$t('Address')" :rules="[]" />
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
import { ref, reactive, getCurrentInstance, onMounted } from 'vue'
import { Notify } from 'quasar'
import { api } from '@/boot/axios'

const { proxy } = getCurrentInstance()

const rows = ref([])
const loading = ref(false)
const saving = ref(false)
const dialog = ref(false)
const editing = ref(null)
const filter = ref('')
const filteredRows = ref([])

const blank = () => ({ name: '', address: '', phone: '', active: true })
const form = reactive(blank())

const columns = [
  { name: 'created_at', label: '#', field: 'id', align: 'left' },
  { name: 'name', label: 'Name', field: 'name', align: 'left', sortable: true },
  { name: 'address', label: 'Address', field: 'address', align: 'left' },
  { name: 'phone', label: 'Phone', field: 'phone', align: 'left' },
  { name: 'active', label: 'Status', field: 'active', align: 'center' },
  { name: 'actions', label: 'Actions', field: 'actions', align: 'right' }
]

function resetForm () { Object.assign(form, blank()) }

async function load () {
  loading.value = true
  try {
    const { data } = await api.get('/branches')
    rows.value = data
  } finally {
    loading.value = false
  }
}

function openCreate () {
  editing.value = null
  resetForm()
  dialog.value = true
}

function openEdit (id) {
  const row = rows.value.find(r => r.id === id)
  if (!row) return
  editing.value = id
  Object.assign(form, { name: row.name, address: row.address, phone: row.phone, active: !!row.active })
  dialog.value = true
}

async function save () {
  saving.value = true
  try {
    if (editing.value) await api.put(`/branches/${editing.value}`, form)
    else await api.post('/branches', form)
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: 'Saved successfully' })
    dialog.value = false
    load()
  } catch (e) {
    Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Save failed' })
  } finally {
    saving.value = false
  }
}

function remove (id) {
  proxy.$delete(`branches/${id}`, load)
}

onMounted(load)
</script>
