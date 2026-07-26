<template>
  <q-page>
    <m-backgrounds>
      <div class="row my_radio_less q-pa-sm">
        <div class="col-12">
          <m-header icon="photo_library" controlRoomButton="false" class="q-mt-xs">
            {{ $t('InvoiceArchive') }}
          </m-header>
        </div>

        <!-- Summary -->
        <div class="col-12 q-mt-md">
          <div class="row q-col-gutter-md">
            <div class="col-6 col-md-4">
              <stat-card icon="receipt_long" :label="$t('Invoices')" :value="summary.count"
                color="#0D9488" tint="#CCFBF1" :sub="$t('InFilter')" sub-icon="filter_alt" />
            </div>
            <div class="col-6 col-md-4">
              <stat-card icon="payments" :label="$t('TotalValue')" :value="fmt(summary.total)" :suffix="summary.base"
                color="#175A8C" tint="#E0EDF7" :sub="$t('ActualSpent')" sub-icon="fact_check" />
            </div>
            <div class="col-12 col-md-4">
              <stat-card icon="download" :label="$t('Export')" value="CSV" color="#7C3AED" tint="#EDE9FE" :sub="$t('ForCloseout')" sub-icon="folder_zip" />
            </div>
          </div>
        </div>

        <!-- View: site receipts vs every financial document in the company -->
        <div class="col-12 q-mt-sm">
          <q-btn-toggle v-model="view" unelevated no-caps toggle-color="primary" color="grey-2" text-color="grey-8"
            :options="[
              { label: $t('SiteReceipts'), value: 'site', icon: 'photo_library' },
              { label: $t('AllFinancialDocs'), value: 'all', icon: 'folder_special' },
            ]" @update:model-value="v => v === 'all' && loadArchive()" />
        </div>

        <!-- Filters (site receipts) -->
        <div class="col-12 q-mt-sm" v-if="view === 'site'">
          <div class="row q-col-gutter-sm items-center">
            <div class="col-6 col-sm-3"><q-select outlined dense color="primary" v-model="filters.project_id" :options="projectOptions" emit-value map-options clearable :label="$t('Project')" @update:model-value="load" /></div>
            <div class="col-6 col-sm-3"><q-select outlined dense color="primary" v-model="filters.category_id" :options="categoryOptions" emit-value map-options clearable :label="$t('Category')" @update:model-value="load" /></div>
            <div class="col-6 col-sm-2"><shamsi-date v-model="filters.from" color="primary" :label="$t('From')" clearable @update:model-value="load" /></div>
            <div class="col-6 col-sm-2"><shamsi-date v-model="filters.to" color="primary" :label="$t('To')" clearable @update:model-value="load" /></div>
            <div class="col-12 col-sm-2 text-right"><q-btn outline dense color="primary" icon="download" :label="$t('Export')" @click="exportCsv" /></div>
          </div>
        </div>

        <!-- All financial documents (universal attachments archive) -->
        <div class="col-12 q-mt-md" v-if="view === 'all'">
          <div class="row q-col-gutter-sm items-center q-mb-sm">
            <div class="col-12 col-sm-4">
              <q-input outlined dense v-model="archSearch" :placeholder="$t('Search')" clearable>
                <template #prepend><q-icon name="search" color="primary" /></template>
              </q-input>
            </div>
            <div class="col-12 col-sm-4">
              <q-select outlined dense color="primary" v-model="archType" :options="archTypeOptions" emit-value map-options clearable :label="$t('Module')" />
            </div>
          </div>
          <div v-if="archLoading" class="text-center q-py-lg"><q-spinner color="primary" size="2.5em" /></div>
          <div v-else-if="filteredArchive.length === 0" class="text-center text-grey-5 q-py-lg">{{ $t('NoRecordFound') }}</div>
          <div v-else class="row q-col-gutter-md">
            <div class="col-6 col-sm-4 col-md-3" v-for="(d, i) in filteredArchive" :key="d.id || 'e' + d.source_id + i">
              <div class="inv-card" @click="viewDoc(d)">
                <div class="inv-card__thumb">
                  <img v-if="docThumbs[docKey(d)]" :src="docThumbs[docKey(d)]" :alt="d.original_name" />
                  <div v-else class="inv-card__ph"><q-icon :name="d.mime && d.mime.includes('pdf') ? 'picture_as_pdf' : 'receipt_long'" size="30px" color="teal-6" /></div>
                  <q-chip dense size="sm" class="inv-card__amt" color="indigo-7" text-color="white">{{ $t(srcKey(d.source_type)) }}</q-chip>
                </div>
                <div class="inv-card__meta">
                  <div class="inv-card__vendor">{{ d.source_label || d.original_name }}</div>
                  <div class="inv-card__sub">
                    <span class="text-caption text-grey-6">{{ (d.created_at || '').slice(0, 10) }}</span>
                    <span v-if="d.uploaded_by" class="text-caption text-grey-6">· {{ d.uploaded_by }}</span>
                  </div>
                  <q-btn dense flat no-caps size="sm" color="primary" icon="open_in_new" :label="$t('OpenRecord')" @click.stop="openSource(d)" />
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Grid of receipt thumbnails -->
        <div class="col-12 q-mt-md" v-if="view === 'site'">
          <div v-if="loading" class="text-center q-py-lg"><q-spinner color="primary" size="2.5em" /></div>
          <div v-else-if="invoices.length === 0" class="text-center text-grey-5 q-py-lg">{{ $t('NoRecordFound') }}</div>
          <div v-else class="row q-col-gutter-md">
            <div class="col-6 col-sm-4 col-md-3" v-for="inv in invoices" :key="inv.id">
              <div class="inv-card" @click="viewImage(inv)">
                <div class="inv-card__thumb">
                  <img v-if="thumbs[inv.id]" :src="thumbs[inv.id]" :alt="inv.vendor" />
                  <div v-else class="inv-card__ph"><q-icon name="receipt_long" size="30px" color="teal-6" /></div>
                  <q-chip dense size="sm" class="inv-card__amt" color="teal-8" text-color="white">{{ fmt(inv.actual_total) }} {{ inv.currency }}</q-chip>
                </div>
                <div class="inv-card__meta">
                  <div class="inv-card__vendor">{{ inv.vendor || $t('Vendor') }}</div>
                  <div class="inv-card__sub">
                    <q-chip v-if="inv.category" dense size="sm" color="blue-grey-2" text-color="blue-grey-9">{{ inv.category.name }}</q-chip>
                    <span class="text-caption text-grey-6">{{ (inv.invoice_date || '').slice(0, 10) }}</span>
                  </div>
                  <div class="text-caption text-grey-6">{{ inv.project?.name }} <span v-if="inv.request">· {{ inv.request.code }}</span></div>
                </div>
                <q-btn v-if="$can('site-invoice-delete')" size="sm" dense flat round icon="delete" color="negative" class="inv-card__del" @click.stop="remove(inv)" />
              </div>
            </div>
          </div>
        </div>
      </div>
    </m-backgrounds>

    <!-- Image preview -->
    <q-dialog v-model="imgDialog">
      <q-card class="bg-white" style="width:600px;max-width:95vw" v-if="imgInv">
        <n-header icon="image" :subtitle="imgInv.vendor">{{ imgInv.vendor || $t('Receipt') }} — {{ fmt(imgInv.actual_total) }} {{ imgInv.currency }}</n-header>
        <q-separator />
        <q-card-section class="text-center" style="max-height:66vh;overflow:auto">
          <img v-if="imgUrl" :src="imgUrl" style="max-width:100%;border-radius:8px" />
          <q-spinner v-else color="primary" size="2em" />
        </q-card-section>
        <q-card-actions align="right" class="q-pa-sm"><q-btn flat :label="$t('Close')" color="grey-7" @click="imgDialog = false" /></q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { Notify } from 'quasar'
import { api } from '@/boot/axios'

const router = useRouter()

const invoices = ref([])
const summary = ref({ count: 0, total: 0, base: 'AFN' })
const loading = ref(false)
const projectOptions = ref([])
const categoryOptions = ref([])
const filters = reactive({ project_id: null, category_id: null, from: null, to: null })
const thumbs = reactive({})

function fmt (v) { return Number(v || 0).toLocaleString('en-US') }

async function load () {
  loading.value = true
  try {
    const params = {}
    Object.entries(filters).forEach(([k, v]) => { if (v) params[k] = v })
    const { data } = await api.get('/site-invoices', { params })
    invoices.value = data.invoices || []
    summary.value = data.summary || summary.value
    invoices.value.forEach(loadThumb)
  } finally { loading.value = false }
}
async function loadThumb (inv) {
  if (thumbs[inv.id] || inv.image_mime === 'application/pdf' || !inv.image_mime?.startsWith('image/')) return
  try { const res = await api.get('/site-invoices/' + inv.id + '/image', { responseType: 'blob' }); thumbs[inv.id] = URL.createObjectURL(new Blob([res.data], { type: inv.image_mime })) } catch (_) {}
}
async function loadProjects () { try { const { data } = await api.get('/projects'); projectOptions.value = (data || []).map(p => ({ label: p.name, value: p.id })) } catch (_) {} }
async function loadCategories () { try { const { data } = await api.get('/purchase-categories'); categoryOptions.value = (data || []).map(c => ({ label: c.name, value: c.id })) } catch (_) {} }

function remove (inv) {
  api.delete('/site-invoices/' + inv.id).then(() => { Notify.create({ type: 'positive', position: 'bottom', message: 'Deleted' }); load() })
    .catch(e => Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Failed' }))
}

const imgDialog = ref(false)
const imgInv = ref(null)
const imgUrl = ref(null)
async function viewImage (inv) {
  imgInv.value = inv; imgUrl.value = thumbs[inv.id] || null; imgDialog.value = true
  if (!imgUrl.value) { try { const res = await api.get('/site-invoices/' + inv.id + '/image', { responseType: 'blob' }); imgUrl.value = URL.createObjectURL(new Blob([res.data], { type: inv.image_mime })) } catch (_) {} }
}

// Export the currently-filtered set for project closeout.
function exportCsv () {
  const headers = ['Date', 'Vendor', 'Category', 'Project', 'Request', 'Amount', 'Currency']
  const lines = invoices.value.map(i => [
    (i.invoice_date || '').slice(0, 10), i.vendor || '', i.category?.name || '',
    i.project?.name || '', i.request?.code || '', i.actual_total, i.currency,
  ])
  const csv = [headers, ...lines].map(r => r.map(v => `"${String(v ?? '').replace(/"/g, '""')}"`).join(',')).join('\n')
  const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8;' }))
  const a = document.createElement('a'); a.href = url; a.download = 'invoice-archive.csv'; a.click()
  URL.revokeObjectURL(url)
}

// ── All-financial-documents archive ──
const view = ref('site')
const archive = ref([])
const archLoading = ref(false)
const archSearch = ref('')
const archType = ref(null)
const docThumbs = reactive({})

const SRC_KEYS = {
  expense: 'Expense', invoice: 'Invoice', receipt: 'Receipt', 'site-invoice': 'SiteInvoice',
  'purchase-request': 'PurchaseRequest', 'purchase-order': 'PurchaseOrder',
  'contract-payment': 'ContractPayment', 'subcontractor-payment': 'SubPayment',
  'payment-request': 'PaymentRequest', treasury: 'GeneralBudget',
}
const SRC_ROUTES = {
  expense: '/finance/expenses', invoice: '/finance/invoices', receipt: '/finance/receipts',
  'site-invoice': '/site/invoices', 'purchase-request': '/site/purchases',
  'purchase-order': '/procurement/purchase-orders', 'contract-payment': '/contracts',
  'subcontractor-payment': '/subcontractors', 'payment-request': '/finance/payment-center',
  treasury: '/finance/treasury',
}
function srcKey (t) { return SRC_KEYS[t] || t }
const archTypeOptions = computed(() => [...new Set(archive.value.map(d => d.source_type))].map(t => ({ label: srcKey(t), value: t })))

const filteredArchive = computed(() => {
  const q = (archSearch.value || '').toLowerCase()
  return archive.value.filter(d =>
    (!archType.value || d.source_type === archType.value) &&
    (!q || [d.source_label, d.original_name, d.uploaded_by, d.caption].some(v => String(v || '').toLowerCase().includes(q)))
  )
})

function docKey (d) { return d.id ? 'a' + d.id : 'e' + d.source_id }

async function loadArchive () {
  archLoading.value = true
  try {
    const { data } = await api.get('/attachments/archive')
    archive.value = data || []
    archive.value.filter(d => d.is_image).forEach(loadDocThumb)
  } finally { archLoading.value = false }
}
async function loadDocThumb (d) {
  const key = docKey(d)
  if (docThumbs[key]) return
  try {
    const url = d.id ? '/attachments/' + d.id + '/view' : '/expenses/' + d.source_id + '/attachment'
    const res = await api.get(url, { responseType: 'blob' })
    docThumbs[key] = URL.createObjectURL(res.data)
  } catch (_) {}
}
async function viewDoc (d) {
  await loadDocThumb(d)
  imgInv.value = { vendor: d.source_label, actual_total: null, currency: '' }
  imgUrl.value = docThumbs[docKey(d)] || null
  imgDialog.value = true
}
function openSource (d) { router.push(SRC_ROUTES[d.source_type] || '/site/invoices') }

onMounted(() => { load(); loadProjects(); loadCategories() })
</script>

<style scoped>
.inv-card {
  position: relative; cursor: pointer; background: #fff;
  border: 1px solid #E7ECF3; border-radius: 14px; overflow: hidden;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.inv-card:hover { transform: translateY(-3px); box-shadow: 0 14px 26px -18px rgba(13, 148, 136, 0.6); }
.inv-card__thumb { position: relative; height: 150px; background: #F1F5F9; display: flex; align-items: center; justify-content: center; }
.inv-card__thumb img { width: 100%; height: 100%; object-fit: cover; }
.inv-card__ph { display: flex; align-items: center; justify-content: center; }
.inv-card__amt { position: absolute; bottom: 6px; inset-inline-start: 6px; font-weight: 700; }
.inv-card__meta { padding: 8px 10px; }
.inv-card__vendor { font-weight: 600; font-size: 13px; color: #0F172A; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.inv-card__sub { display: flex; align-items: center; gap: 6px; margin: 2px 0; }
.inv-card__del { position: absolute; top: 4px; inset-inline-end: 4px; background: rgba(255, 255, 255, 0.85); }
@media (prefers-color-scheme: dark) {
  .inv-card { background: #1E293B; border-color: #334155; }
  .inv-card__vendor { color: #F1F5F9; }
}
</style>
