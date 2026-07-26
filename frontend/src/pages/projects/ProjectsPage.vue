<template>
  <q-page>
    <m-backgrounds>
      <div class="row my_radio_less q-pa-sm">
        <div class="col-12">
          <m-header icon="domain" controlRoomButton="false" class="q-mt-xs">
            {{ $t('ProjectsAndSites') }}
          </m-header>
        </div>

        <action-bar
          :rows="rows"
          :columns="columns"
          filename="projects"
          create-perm="project-create"
          search-key="page.projects.table.advanced_search"
          @add="openCreate"
          @update:filtered="filteredRows = $event"
        />
        <div class="col-12">
          <n-table config-key="page.projects"
            :loading="loading"
            :data="rows"
            :columns="columns"
            v-model:filter="filter"
            :can_edit="'project-edit'"
            :can_delete="'project-delete'"
            :noInfo="true"
            @edit="openEdit"
            @del="remove"
          >
            <template v-slot:body-cell-name="props">
              <q-td :props="props">
                <q-btn size="sm" dense flat round icon="visibility" color="primary" class="q-mr-xs" @click="openShow(props.row.id)">
                  <q-tooltip>{{ $t('ViewDashboard') }}</q-tooltip>
                </q-btn>
                <a class="project-link" @click.prevent="openShow(props.row.id)">{{ props.row.name }}</a>
                <q-badge v-if="props.row.branch" color="teal-6" class="q-ml-xs branch-stripe">
                  <q-icon name="store" size="11px" class="q-mr-xs" />{{ props.row.branch.name }}
                </q-badge>
                <div v-if="props.row.sites_count != null" class="text-caption text-grey-6">
                  {{ props.row.sites_count }} {{ $t('Sites') }} · {{ props.row.milestones_count }} {{ $t('Milestones') }}
                </div>
              </q-td>
            </template>
            <template v-slot:body-cell-type="props">
              <q-td :props="props">
                <q-chip dense size="sm"
                  :color="props.row.type === 'road' ? 'orange-7' : 'blue-grey-7'"
                  text-color="white"
                  :icon="props.row.type === 'road' ? 'route' : 'domain'">
                  {{ props.row.type === 'road' ? $t('RoadBuilding') : $t('Building') }}
                </q-chip>
              </q-td>
            </template>
            <template v-slot:body-cell-contract_value="props">
              <q-td :props="props" class="text-right">
                {{ fmtMoney(props.row.contract_value) }} {{ props.row.currency }}
              </q-td>
            </template>
            <template v-slot:body-cell-progress="props">
              <q-td :props="props" style="min-width:120px">
                <q-linear-progress rounded size="14px" :value="(props.row.progress || 0) / 100"
                  :color="progressColor(props.row.progress)" track-color="grey-3">
                  <div class="absolute-full flex flex-center">
                    <span style="font-size:9px;font-weight:700;color:#fff">{{ props.row.progress || 0 }}%</span>
                  </div>
                </q-linear-progress>
              </q-td>
            </template>
            <template v-slot:body-cell-status="props">
              <q-td :props="props">
                <q-chip dense size="sm" :color="statusColor(props.row.status)" text-color="white">
                  {{ $t(statusKey(props.row.status)) }}
                </q-chip>
              </q-td>
            </template>
          </n-table>
        </div>
      </div>
    </m-backgrounds>
  </q-page>
</template>

<script setup>
import { ref, getCurrentInstance, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '@/boot/axios'

const { proxy } = getCurrentInstance()
const router = useRouter()

const rows = ref([])
const filteredRows = ref([])
const loading = ref(false)
const filter = ref('')

const columns = [
  { name: 'created_at', label: '#', field: 'id', align: 'left' },
  { name: 'code', label: 'Code', field: 'code', align: 'left', sortable: true },
  { name: 'name', label: 'ProjectName', field: 'name', align: 'left', sortable: true },
  { name: 'client_name', label: 'Client', field: 'client_name', align: 'left' },
  { name: 'type', label: 'Type', field: 'type', align: 'left' },
  { name: 'contract_value', label: 'ContractValue', field: 'contract_value', align: 'right', sortable: true },
  { name: 'progress', label: 'Progress', field: 'progress', align: 'center', sortable: true },
  { name: 'status', label: 'Status', field: 'status', align: 'center' },
  { name: 'actions', label: 'Actions', field: 'actions', align: 'right' }
]

function fmtMoney (v) {
  return Number(v || 0).toLocaleString('en-US')
}
function progressColor (p) {
  if (p >= 100) return 'positive'
  if (p >= 50) return 'primary'
  if (p >= 20) return 'amber-8'
  return 'orange-7'
}
function statusColor (s) {
  return { planning: 'blue-grey-6', active: 'positive', on_hold: 'amber-8', completed: 'grey-7' }[s] ?? 'grey'
}
function statusKey (s) {
  return { planning: 'Planning', active: 'StatusActive', on_hold: 'OnHold', completed: 'Completed' }[s] ?? 'Planning'
}

async function load () {
  loading.value = true
  try {
    const { data } = await api.get('/projects')
    rows.value = data
  } finally {
    loading.value = false
  }
}

function openCreate () { router.push('/projects/create') }
function openEdit (id) { router.push('/projects/edit/' + id) }
function openShow (id) { router.push('/projects/' + id) }
function remove (id) { proxy.$delete('projects/' + id, load) }

onMounted(load)
</script>

<style scoped>
.project-link {
  color: var(--q-primary);
  font-weight: 600;
  cursor: pointer;
  text-decoration: none;
}
.project-link:hover { text-decoration: underline; }
</style>
