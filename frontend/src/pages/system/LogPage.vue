<template>
  <q-page>
    <m-backgrounds>
      <div class="row my_radio_less q-pa-sm">
        <div class="col-12">
          <m-header icon="manage_search" controlRoomButton="false" class="q-mt-xs">
            {{ $t('Log') }}
          </m-header>
        </div>

        <div class="col-12 q-mt-sm">
          <div class="row q-col-gutter-sm items-end">
            <div class="col-auto">
              <q-select outlined dense color="primary" label-color="primary"
                v-model="filterAction" :options="actionOptions" emit-value map-options
                :label="$t('Action')" clearable style="min-width:140px">
                <template #prepend><q-icon name="filter_list" color="primary" /></template>
              </q-select>
            </div>
            <div class="col-auto">
              <q-select outlined dense color="primary" label-color="primary"
                v-model="filterModule" :options="moduleOptions" emit-value map-options
                :label="$t('Module')" clearable style="min-width:140px">
                <template #prepend><q-icon name="category" color="primary" /></template>
              </q-select>
            </div>
            <div class="col-auto">
              <q-input outlined dense color="primary" v-model="filterText"
                placeholder="Search description…" clearable style="min-width:200px">
                <template #prepend><q-icon name="search" color="primary" /></template>
              </q-input>
            </div>
            <div class="col-auto">
              <progress-btn color="primary" icon="refresh" @click="load">{{ $t('Load') }}</progress-btn>
            </div>
          </div>
        </div>

        <div class="col-12 q-mt-sm">
          <q-markup-table flat bordered dense class="my_radio_less">
            <thead class="bg-theme-soft">
              <tr>
                <th class="text-left" style="width:40px">#</th>
                <th class="text-left">{{ $t('Action') }}</th>
                <th class="text-left">Module</th>
                <th class="text-left">Description</th>
                <th class="text-left">User</th>
                <th class="text-left">Time</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading">
                <td colspan="6" class="text-center q-py-md"><q-spinner color="primary" size="2em" /></td>
              </tr>
              <tr v-else-if="filtered.length === 0">
                <td colspan="6" class="text-center text-grey-5 q-py-md">{{ $t('NoRecordFound') }}</td>
              </tr>
              <tr v-else v-for="(r, i) in filtered" :key="r.id">
                <td class="text-grey-5 text-caption">{{ i + 1 }}</td>
                <td>
                  <q-chip dense size="sm" :color="actionColor(r.action)" text-color="white" class="q-ma-none">
                    {{ r.action }}
                  </q-chip>
                </td>
                <td>
                  <q-chip dense size="sm" color="grey-3" text-color="grey-8" class="q-ma-none">
                    <q-icon :name="moduleIcon(r.module)" size="12px" class="q-mr-xs" />
                    {{ r.module }}
                  </q-chip>
                </td>
                <td class="text-caption">{{ r.description }}</td>
                <td class="text-caption text-blue-grey-7">{{ r.user?.name ?? '—' }}</td>
                <td class="text-caption text-grey-6" style="white-space:nowrap">{{ r.created_at_human ?? r.created_at?.slice(0,16) }}</td>
              </tr>
            </tbody>
          </q-markup-table>
        </div>

        <div class="col-12 q-mt-xs text-caption text-grey-6">
          Showing {{ filtered.length }} of {{ rows.length }} entries
        </div>
      </div>
    </m-backgrounds>
  </q-page>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { api } from '@/boot/axios'

const rows = ref([])
const loading = ref(false)
const filterAction = ref(null)
const filterModule = ref(null)
const filterText = ref('')

const actionOptions = [
  { label: 'Created', value: 'created' },
  { label: 'Updated', value: 'updated' },
  { label: 'Deleted', value: 'deleted' },
]

const moduleOptions = computed(() => {
  const mods = [...new Set(rows.value.map(r => r.module).filter(Boolean))]
  return mods.map(m => ({ label: m, value: m }))
})

const filtered = computed(() => {
  let list = rows.value
  if (filterAction.value) list = list.filter(r => r.action === filterAction.value)
  if (filterModule.value) list = list.filter(r => r.module === filterModule.value)
  if (filterText.value) {
    const q = filterText.value.toLowerCase()
    list = list.filter(r => r.description?.toLowerCase().includes(q))
  }
  return list
})

function actionColor(action) {
  return { created: 'positive', updated: 'warning', deleted: 'negative' }[action] ?? 'grey'
}

function moduleIcon(module) {
  const map = {
    User: 'manage_accounts', Role: 'rule', Branch: 'store',
    Company: 'business', Settings: 'settings',
    Project: 'domain', Site: 'place', Task: 'checklist', Milestone: 'flag',
    DailyLog: 'assignment', Subcontractor: 'engineering', SubPayment: 'payments',
    Document: 'folder',
    Currency: 'payments', ExchangeRate: 'currency_exchange', Expense: 'receipt_long',
    Invoice: 'request_quote', Receipt: 'payments',
    Asset: 'construction', Department: 'apartment', Designation: 'badge', Employee: 'groups',
    Investor: 'diversity_3', Investment: 'account_balance', Contract: 'assignment_turned_in',
    Treasury: 'savings', Party: 'account_balance_wallet',
    Supplier: 'local_shipping', Stock: 'inventory', PurchaseOrder: 'shopping_cart',
  }
  return map[module] ?? 'circle'
}

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/activity-logs')
    rows.value = Array.isArray(data) ? data : []
  } catch (_) {}
  finally { loading.value = false }
}

onMounted(load)
</script>
