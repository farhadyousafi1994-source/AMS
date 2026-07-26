<template>
  <q-page>
    <m-backgrounds>
      <div class="row my_radio_less q-pa-sm">
        <div class="col-12">
          <m-header icon="payments" controlRoomButton="false" class="q-mt-xs">
            {{ $t('Receipts') }}
          </m-header>
        </div>

        <action-bar
          :rows="rows"
          :columns="columns"
          filename="receipts"
          @update:filtered="filteredRows = $event"
        />
        <div class="col-12">
          <n-table config-key="page.receipts"
            :loading="loading"
            :data="rows"
            :columns="columns"
            v-model:filter="filter"
            :can_delete="'receipt-delete'"
            :noInfo="true"
            :noEdit="true"
            @del="remove"
          >
            <template v-slot:body-cell-invoice="props">
              <q-td :props="props">{{ props.row.invoice?.invoice_no || '—' }}</q-td>
            </template>
            <template v-slot:body-cell-amount="props">
              <q-td :props="props" class="text-right">{{ fmt(props.row.amount) }} {{ props.row.currency }}</q-td>
            </template>
            <template v-slot:body-cell-amount_base="props">
              <q-td :props="props" class="text-right text-weight-bold text-primary">{{ fmt(props.row.amount_base) }}</q-td>
            </template>
            <template v-slot:body-cell-method="props">
              <q-td :props="props">
                <q-chip v-if="props.row.method" dense size="sm" :color="methodColor(props.row.method)" text-color="white">{{ $t(methodKey(props.row.method)) }}</q-chip>
                <span v-else>—</span>
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
import { api } from '@/boot/axios'

const { proxy } = getCurrentInstance()
const rows = ref([])
const filteredRows = ref([])
const loading = ref(false)
const filter = ref('')

const columns = [
  { name: 'created_at', label: '#', field: 'id', align: 'left' },
  { name: 'receipt_no', label: 'ReceiptNo', field: 'receipt_no', align: 'left', sortable: true },
  { name: 'receipt_date', label: 'PaymentDate', field: 'receipt_date', align: 'left', sortable: true },
  { name: 'payer', label: 'Payer', field: 'payer', align: 'left' },
  { name: 'invoice', label: 'InvoiceNo', field: 'invoice', align: 'left' },
  { name: 'amount', label: 'Amount', field: 'amount', align: 'right', sortable: true },
  { name: 'amount_base', label: 'BaseAmount', field: 'amount_base', align: 'right' },
  { name: 'method', label: 'Method', field: 'method', align: 'left' },
  { name: 'actions', label: 'Actions', field: 'actions', align: 'right' }
]

function fmt (v) { return Number(v || 0).toLocaleString('en-US', { maximumFractionDigits: 2 }) }
function methodColor (m) { return { cash: 'green-7', bank: 'blue-7', other: 'blue-grey-6' }[m] ?? 'grey' }
function methodKey (m) { return { cash: 'Cash', bank: 'Bank', other: 'Other' }[m] ?? 'Other' }

async function load () {
  loading.value = true
  try {
    const { data } = await api.get('/receipts')
    rows.value = data
  } finally { loading.value = false }
}

function remove (id) { proxy.$delete('receipts/' + id, load) }

onMounted(load)
</script>
