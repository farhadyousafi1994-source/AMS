<template>
  <q-page>
    <m-backgrounds>
      <div class="row my_radio_less q-pa-sm">
        <div class="col-12">
          <m-header icon="engineering" controlRoomButton="false" class="q-mt-xs">{{ $t('Subcontractors') }}</m-header>
        </div>

        <!-- Dashboard -->
        <div class="col-12 q-mt-md">
          <div class="row q-col-gutter-md">
            <div class="col-6 col-md-3"><stat-card icon="engineering" :label="$t('Subcontractors')" :value="summary.count" color="#175A8C" tint="#E0EDF7" :sub="$t('Registered')" sub-icon="how_to_reg" /></div>
            <div class="col-6 col-md-3"><stat-card icon="assignment" :label="$t('ContractTotal')" :value="fmt(summary.contract_total)" :suffix="summary.base" color="#0D9488" tint="#CCFBF1" :sub="$t('AcrossProjects')" sub-icon="domain" /></div>
            <div class="col-6 col-md-3"><stat-card icon="payments" :label="$t('Paid')" :value="fmt(summary.paid_total)" :suffix="summary.base" color="#16A34A" tint="#DCFCE7" :sub="$t('IncludingAdvance')" sub-icon="south_west" /></div>
            <div class="col-6 col-md-3"><stat-card icon="account_balance_wallet" :label="$t('Balance')" :value="fmt(summary.balance_total)" :suffix="summary.base" color="#DC2626" tint="#FEE2E2" :sub="$t('Remaining')" sub-icon="pending" /></div>
          </div>
        </div>

        <!-- Fingerprint quick scan -->
        <div class="col-12 q-mt-sm">
          <div class="fp-scan">
            <div class="fp-scan__icon"><q-icon name="fingerprint" size="26px" /></div>
            <div class="fp-scan__txt">
              <div class="fp-scan__title">{{ $t('FingerprintLookup') }}</div>
              <div class="fp-scan__sub">{{ $t('FingerprintHint') }} <span class="fp-scan__eg">{{ $t('FingerprintExample') }}</span></div>
            </div>
            <q-input outlined dense color="primary" v-model="fpQuery" :placeholder="$t('ScanOrTypeFp')" class="fp-scan__input" @keyup.enter="doFingerprint">
              <template #prepend><q-icon name="fingerprint" color="primary" /></template>
            </q-input>
            <q-btn unelevated color="primary" icon="search" :label="$t('Lookup')" :loading="fpLoading" @click="doFingerprint" />
          </div>
        </div>

        <div class="col-12 q-mt-sm">
          <div class="row q-col-gutter-sm items-center">
            <div class="col-6 col-sm-3"><q-input outlined dense color="primary" v-model="tableFilter" :placeholder="$t('Search')" clearable><template #prepend><q-icon name="search" color="primary" /></template></q-input></div>
          </div>
        </div>

        <action-bar :rows="rows" :columns="exportColumns" filename="subcontractors" create-perm="tradesman-create" @add="openCreate" @update:filtered="() => {}" />
        <div class="col-12">
          <n-table config-key="page.subcontractors" :loading="loading" :data="rows" :columns="columns" v-model:filter="tableFilter"
            :can_show="'tradesman-show'" :can_edit="'tradesman-edit'" :can_delete="'tradesman-delete'"
            info-icon="visibility" :noInfoDialog="true" @info="openDetail" @edit="openEdit" @del="remove">
            <template v-slot:body-cell-photo="props">
              <q-td :props="props">
                <q-avatar size="36px" color="blue-grey-2" text-color="blue-grey-9">
                  <img v-if="photos[props.row.id]" :src="photos[props.row.id]" />
                  <span v-else>{{ (props.row.name || '؟').slice(0, 1) }}</span>
                </q-avatar>
              </q-td>
            </template>
            <template v-slot:body-cell-name="props">
              <q-td :props="props">
                <a class="sc-link" @click.prevent="openDetail(props.row.id)">{{ props.row.name }}</a>
                <div v-if="props.row.father_name" class="text-caption text-grey-6">{{ $t('Son') }}: {{ props.row.father_name }}</div>
              </q-td>
            </template>
            <template v-slot:body-cell-projects_count="props">
              <q-td :props="props" class="text-center"><q-chip dense size="sm" color="blue-1" text-color="blue-9">{{ props.row.projects_count }}</q-chip></q-td>
            </template>
            <template v-slot:body-cell-contract_total="props"><q-td :props="props" class="text-right">{{ fmt(props.row.contract_total) }}</q-td></template>
            <template v-slot:body-cell-paid_total="props"><q-td :props="props" class="text-right text-positive">{{ fmt(props.row.paid_total) }}</q-td></template>
            <template v-slot:body-cell-balance="props">
              <q-td :props="props" class="text-right text-weight-bold" :class="Number(props.row.balance) > 0 ? 'text-negative' : 'text-grey-7'">{{ fmt(props.row.balance) }}</q-td>
            </template>
            <template v-slot:body-cell-rating_avg="props">
              <q-td :props="props" class="text-center">
                <span v-if="props.row.rating_avg" class="rate-pill"><q-icon name="star" size="14px" color="amber-7" /> {{ props.row.rating_avg }} <span class="text-grey-6">({{ props.row.rating_count }})</span></span>
                <span v-else class="text-grey-4">—</span>
              </q-td>
            </template>
            <template v-slot:body-cell-fingerprint_id="props">
              <q-td :props="props" class="text-center">
                <q-icon v-if="props.row.fingerprint_id" name="fingerprint" color="teal-7" size="18px"><q-tooltip>{{ props.row.fingerprint_id }}</q-tooltip></q-icon>
                <span v-else class="text-grey-4">—</span>
              </q-td>
            </template>
          </n-table>
        </div>
      </div>
    </m-backgrounds>

    <!-- Add / edit subcontractor -->
    <m-modal :showCM="dialog" @update:showCM="dialog = $event" card_style="width: 620px">
      <q-card class="bg-white">
        <n-header icon="engineering">{{ form.id ? $t('Edit') : $t('RegisterSubcontractor') }}</n-header>
        <q-separator />
        <q-form @submit="save">
          <q-card-section class="row q-col-gutter-sm">
            <div class="col-12 col-sm-6"><n-name :name="form.name" @update:name="form.name = $event" icon="person" :label="$t('Name')" /></div>
            <div class="col-12 col-sm-6"><n-name :name="form.father_name" @update:name="form.father_name = $event" icon="family_restroom" :label="$t('FatherName')" :rules="[]" /></div>
            <div class="col-12 col-sm-4"><n-name :name="form.phone" @update:name="form.phone = $event" icon="phone" :label="$t('Phone')" :rules="[]" /></div>
            <div class="col-12 col-sm-4"><n-name :name="form.trade" @update:name="form.trade = $event" icon="construction" :label="$t('Trade')" :rules="[]" /></div>
            <div class="col-12 col-sm-4"><n-name :name="form.cnic" @update:name="form.cnic = $event" icon="badge" :label="$t('IdNumber')" :rules="[]" /></div>
            <div class="col-12 col-sm-4"><q-input outlined dense color="primary" type="number" step="any" v-model.number="form.default_rate" :label="$t('DefaultRate')" /></div>
            <div class="col-12 col-sm-4"><q-select outlined dense color="primary" v-model="form.rate_unit" :options="['m2', 'm3', 'running-m', 'day', 'kg', 'lump']" :label="$t('RateUnit')" /></div>
            <div class="col-12 col-sm-4"><shamsi-date v-model="form.start_date" color="primary" :label="$t('StartDate')" /></div>
            <div class="col-12 col-sm-6"><n-name :name="form.fingerprint_id" @update:name="form.fingerprint_id = $event" icon="fingerprint" :label="$t('FingerprintId')" :rules="[]" /></div>
            <div class="col-12 col-sm-6" v-if="!form.id">
              <q-file outlined dense color="primary" v-model="photoFile" :label="$t('Photo')" accept="image/*" max-file-size="41943040" clearable>
                <template #prepend><q-icon name="photo_camera" color="primary" /></template>
              </q-file>
            </div>
            <div class="col-12"><q-input outlined dense color="primary" type="textarea" autogrow v-model="form.notes" :label="$t('Notes')" /></div>
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
import { useRouter } from 'vue-router'
import { Notify } from 'quasar'
import { api } from '@/boot/axios'
import { compressImage } from '@/utils/image'

const { proxy } = getCurrentInstance()
const router = useRouter()

const rows = ref([])
const summary = ref({ count: 0, active: 0, contract_total: 0, paid_total: 0, balance_total: 0, base: 'AFN' })
const loading = ref(false)
const saving = ref(false)
const dialog = ref(false)
const tableFilter = ref('')
const photos = reactive({})
const photoFile = ref(null)

const columns = [
  { name: 'photo', label: '', field: 'photo', align: 'left' },
  { name: 'code', label: 'Code', field: 'code', align: 'left', sortable: true },
  { name: 'name', label: 'Name', field: 'name', align: 'left', sortable: true },
  { name: 'trade', label: 'Trade', field: 'trade', align: 'left' },
  { name: 'phone', label: 'Phone', field: 'phone', align: 'left' },
  { name: 'projects_count', label: 'Projects', field: 'projects_count', align: 'center', sortable: true },
  { name: 'contract_total', label: 'ContractTotal', field: 'contract_total', align: 'right', sortable: true },
  { name: 'paid_total', label: 'Paid', field: 'paid_total', align: 'right', sortable: true },
  { name: 'balance', label: 'Balance', field: 'balance', align: 'right', sortable: true },
  { name: 'rating_avg', label: 'Rating', field: 'rating_avg', align: 'center', sortable: true },
  { name: 'fingerprint_id', label: 'Fingerprint', field: 'fingerprint_id', align: 'center' },
  { name: 'actions', label: 'Actions', field: 'actions', align: 'right' },
]
const exportColumns = columns.filter(c => !['photo', 'actions'].includes(c.name))

const blank = () => ({ id: null, name: '', father_name: '', phone: '', trade: '', cnic: '', default_rate: 0, rate_unit: 'm2', start_date: '', fingerprint_id: '', notes: '' })
const form = reactive(blank())

function fmt (v) { return Number(v || 0).toLocaleString('en-US') }

async function load () {
  loading.value = true
  try {
    const { data } = await api.get('/tradesmen')
    rows.value = data.tradesmen || []
    summary.value = data.summary || summary.value
    rows.value.forEach(loadPhoto)
  } finally { loading.value = false }
}
async function loadPhoto (t) {
  if (photos[t.id] || !t.photo_mime?.startsWith('image/')) return
  try { const res = await api.get('/tradesmen/' + t.id + '/photo', { responseType: 'blob' }); photos[t.id] = URL.createObjectURL(new Blob([res.data], { type: t.photo_mime })) } catch (_) {}
}

function openDetail (id) { router.push('/subcontractors/' + id) }
function openCreate () { Object.assign(form, blank()); photoFile.value = null; dialog.value = true }
function openEdit (id) {
  const r = rows.value.find(x => x.id === id); if (!r) return
  Object.assign(form, { id: r.id, name: r.name, father_name: r.father_name || '', phone: r.phone || '', trade: r.trade || '', cnic: r.cnic || '', default_rate: Number(r.default_rate || 0), rate_unit: r.rate_unit || 'm2', start_date: r.start_date || '', fingerprint_id: r.fingerprint_id || '', notes: r.notes || '', active: r.active })
  dialog.value = true
}
async function save () {
  saving.value = true
  try {
    if (form.id) {
      await api.put('/tradesmen/' + form.id, { ...form })
    } else {
      const fd = new FormData()
      Object.entries(form).forEach(([k, v]) => { if (v !== null && v !== '' && k !== 'id') fd.append(k, v) })
      if (photoFile.value) fd.append('photo', await compressImage(photoFile.value))
      await api.post('/tradesmen', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    }
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: 'Saved' })
    dialog.value = false; load()
  } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Save failed' }) } finally { saving.value = false }
}
function remove (id) { proxy.$delete('tradesmen/' + id, load) }

// Fingerprint lookup → open that subcontractor's page.
const fpQuery = ref('')
const fpLoading = ref(false)
async function doFingerprint () {
  if (!fpQuery.value) return
  fpLoading.value = true
  try {
    const { data } = await api.get('/tradesmen/fingerprint/' + encodeURIComponent(fpQuery.value.trim()))
    router.push('/subcontractors/' + data.id)
  } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'No match for this fingerprint' }) } finally { fpLoading.value = false }
}

onMounted(load)
</script>

<style scoped>
.sc-link { color: var(--q-primary); font-weight: 600; cursor: pointer; text-decoration: none; }
.sc-link:hover { text-decoration: underline; }
.fp-scan {
  display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
  background: linear-gradient(135deg, #0D3B66, #145DA0); color: #fff;
  border-radius: 14px; padding: 12px 16px;
}
.fp-scan__icon { width: 46px; height: 46px; border-radius: 12px; background: rgba(255, 255, 255, 0.15); display: flex; align-items: center; justify-content: center; }
.fp-scan__txt { flex: 1; min-width: 160px; }
.fp-scan__title { font-weight: 800; font-size: 15px; }
.fp-scan__sub { font-size: 12px; opacity: 0.85; }
.fp-scan__input { min-width: 220px; background: #fff; border-radius: 6px; }
.fp-scan__eg { display: block; opacity: 0.75; font-size: 11px; margin-top: 2px; }
.rate-pill { display: inline-flex; align-items: center; gap: 2px; font-weight: 700; }
</style>
