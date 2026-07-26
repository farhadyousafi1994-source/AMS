<template>
  <q-page>
    <m-backgrounds>
      <div class="row my_radio_less q-pa-sm">
        <div class="col-12">
          <m-header icon="fingerprint" controlRoomButton="false" :subtitle="$t('FingerprintSettingsSub')" class="q-mt-xs">
            {{ $t('FingerprintSettings') }}
          </m-header>
        </div>

        <!-- Policy card -->
        <div class="col-12 q-mt-sm">
          <q-card flat bordered class="my_radio_less bg-white">
            <q-card-section class="row items-center q-col-gutter-md">
              <div class="col-12 col-md-4">
                <div class="fp-policy-head">
                  <q-icon name="policy" size="20px" color="primary" class="q-mr-sm" />{{ $t('Policy') }}
                </div>
                <q-toggle v-model="policy.enabled" :label="$t('EnableFingerprintAuth')" color="teal-7" @update:model-value="saveSettings" />
              </div>
              <div class="col-6 col-md-3">
                <q-select outlined dense color="primary" v-model="policy.enforcement" :options="enforcementOptions" emit-value map-options :label="$t('Enforcement')" @update:model-value="saveSettings" />
              </div>
              <div class="col-6 col-md-2">
                <q-input outlined dense color="primary" type="number" min="0" max="100" v-model.number="policy.min_quality" :label="$t('MinQuality')" @blur="saveSettings" />
              </div>
              <div class="col-12 col-md-3 column q-gutter-xs">
                <q-toggle dense size="sm" v-model="policy.allow_override" :label="$t('AllowManagerOverride')" color="primary" @update:model-value="saveSettings" />
                <q-toggle dense size="sm" v-model="policy.allow_pin_fallback" :label="$t('AllowPinFallback')" color="primary" @update:model-value="saveSettings" />
                <q-toggle dense size="sm" v-model="policy.fallback_when_unavailable" :label="$t('FallbackWhenUnavailable')" color="primary" @update:model-value="saveSettings" />
              </div>
            </q-card-section>
          </q-card>
        </div>

        <!-- Devices -->
        <div class="col-12 q-mt-md">
          <div class="row items-center q-mb-sm">
            <div class="text-subtitle1 text-weight-bold"><q-icon name="usb" size="18px" class="q-mr-xs" />{{ $t('Devices') }}</div>
            <q-space />
            <q-btn flat dense color="blue-grey-8" icon="travel_explore" :label="$t('DetectDevices')" :loading="detecting" @click="detect" />
            <progress-btn color="teal" icon="add" class="q-ml-sm" @click="openCreate">{{ $t('AddDevice') }}</progress-btn>
          </div>

          <div class="row q-col-gutter-md">
            <div v-for="d in devices" :key="d.id" class="col-12 col-md-6 col-lg-4">
              <q-card flat bordered class="my_radio_less bg-white fp-dev">
                <q-card-section class="q-pb-xs">
                  <div class="row items-center no-wrap">
                    <q-avatar size="40px" :color="d.status === 'online' ? 'teal-1' : 'grey-3'" :text-color="d.status === 'online' ? 'teal-8' : 'grey-7'">
                      <q-icon name="fingerprint" size="22px" />
                    </q-avatar>
                    <div class="q-ml-sm">
                      <div class="text-weight-bold">{{ d.name }}
                        <q-badge v-if="d.is_default" color="amber-7" text-color="white" class="q-ml-xs">{{ $t('Default') }}</q-badge>
                      </div>
                      <div class="text-caption text-grey-6">{{ brandLabel(d.brand) }} · {{ d.connection }}<span v-if="d.model"> · {{ d.model }}</span></div>
                    </div>
                    <q-space />
                    <q-chip dense size="sm" :color="statusColor(d.status)" text-color="white">{{ $t(statusKey(d.status)) }}</q-chip>
                  </div>
                  <div v-if="d.host" class="text-caption text-grey-6 q-mt-xs"><q-icon name="lan" size="13px" /> {{ d.host }}<span v-if="d.port">:{{ d.port }}</span></div>
                  <div v-if="d.last_seen_at" class="text-caption text-grey-5">{{ $t('LastSeen') }}: {{ $fmtDateTime(d.last_seen_at) }}</div>
                </q-card-section>
                <q-separator />
                <q-card-actions align="right" class="q-py-xs">
                  <q-btn flat dense size="sm" color="blue-grey-8" icon="wifi_tethering" :label="$t('Test')" :loading="testingId === d.id" @click="test(d)" />
                  <q-btn flat dense size="sm" color="blue-8" icon="edit" @click="openEdit(d)" />
                  <q-btn flat dense size="sm" color="negative" icon="delete" @click="remove(d)" />
                </q-card-actions>
              </q-card>
            </div>
            <div v-if="!devices.length" class="col-12 text-center text-grey-5 q-py-lg">{{ $t('NoDevicesYet') }}</div>
          </div>
        </div>
      </div>
    </m-backgrounds>

    <!-- Add / edit device -->
    <m-modal :showCM="dialog" @update:showCM="dialog = $event" card_style="width: 480px">
      <q-card class="bg-white">
        <n-header icon="fingerprint">{{ form.id ? $t('Edit') : $t('AddDevice') }}</n-header>
        <q-separator />
        <q-form @submit="save">
          <q-card-section class="row q-col-gutter-sm">
            <div class="col-12"><n-name :name="form.name" @update:name="form.name = $event" icon="badge" :label="$t('Name')" autofocus /></div>
            <div class="col-6"><q-select outlined dense color="primary" v-model="form.brand" :options="brandOptions" emit-value map-options :label="$t('Brand')" /></div>
            <div class="col-6"><q-select outlined dense color="primary" v-model="form.connection" :options="connectionOptions" :label="$t('Connection')" /></div>
            <div class="col-12 col-sm-6"><n-name :name="form.model" @update:name="form.model = $event" icon="memory" :label="$t('Model')" :rules="[]" /></div>
            <div class="col-12 col-sm-6"><n-name :name="form.serial" @update:name="form.serial = $event" icon="tag" :label="$t('Serial')" :rules="[]" /></div>
            <template v-if="['network', 'bridge'].includes(form.connection)">
              <div class="col-8"><n-name :name="form.host" @update:name="form.host = $event" icon="lan" :label="$t('Host')" :rules="[]" /></div>
              <div class="col-4"><q-input outlined dense color="primary" type="number" v-model.number="form.port" :label="$t('Port')" /></div>
            </template>
            <div class="col-6 flex items-center"><q-toggle v-model="form.active" :label="$t('Active')" color="primary" /></div>
            <div class="col-6 flex items-center"><q-toggle v-model="form.is_default" :label="$t('Default')" color="amber-7" /></div>
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
import { useFingerprint } from '@/composables/useFingerprint'

const { proxy } = getCurrentInstance()
const { settings, brands, devices, loadSettings, loadDevices } = useFingerprint()

const policy = reactive({ enabled: false, enforcement: 'optional', allow_override: true, allow_pin_fallback: false, fallback_when_unavailable: true, min_quality: 40 })
const dialog = ref(false)
const saving = ref(false)
const detecting = ref(false)
const testingId = ref(null)

const blank = () => ({ id: null, name: '', brand: 'simulator', connection: 'simulator', model: '', serial: '', host: '', port: null, active: true, is_default: false })
const form = reactive(blank())

const enforcementOptions = computed(() => [
  { label: proxy.$t('EnforceOff'), value: 'off' },
  { label: proxy.$t('EnforceOptional'), value: 'optional' },
  { label: proxy.$t('EnforceRequired'), value: 'required' },
])
const connectionOptions = ['simulator', 'usb', 'network', 'bridge', 'webauthn']
const brandOptions = computed(() => brands.value.map(b => ({ label: b.label, value: b.key })))
function brandLabel (key) { return brands.value.find(b => b.key === key)?.label || key }

const STATUS = { online: ['Online', 'positive'], offline: ['Offline', 'negative'], unknown: ['Unknown', 'grey-6'] }
function statusKey (s) { return STATUS[s]?.[0] || 'Unknown' }
function statusColor (s) { return STATUS[s]?.[1] || 'grey-6' }

async function boot () {
  await loadSettings(true)
  Object.assign(policy, settings.value)
  await loadDevices()
}
async function saveSettings () {
  try { await api.put('/fingerprint/settings', { ...policy }); Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: proxy.$t('Saved') }) } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Failed' }) }
}

function openCreate () { Object.assign(form, blank()); dialog.value = true }
function openEdit (d) { Object.assign(form, { id: d.id, name: d.name, brand: d.brand, connection: d.connection, model: d.model || '', serial: d.serial || '', host: d.host || '', port: d.port, active: !!d.active, is_default: !!d.is_default }); dialog.value = true }
async function save () {
  saving.value = true
  try {
    const payload = { name: form.name, brand: form.brand, connection: form.connection, model: form.model, serial: form.serial, host: form.host, port: form.port, active: form.active, is_default: form.is_default }
    if (form.id) await api.put('/fingerprint/devices/' + form.id, payload)
    else await api.post('/fingerprint/devices', payload)
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: proxy.$t('Saved') })
    dialog.value = false
    loadDevices()
  } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Save failed' }) } finally { saving.value = false }
}
function remove (d) { proxy.$delete('fingerprint/devices/' + d.id, loadDevices) }

async function test (d) {
  testingId.value = d.id
  try {
    const { data } = await api.post('/fingerprint/devices/' + d.id + '/test')
    Notify.create({ type: data.online ? 'positive' : 'negative', message: `${d.name}: ${data.message}` + (data.latency_ms != null ? ` (${data.latency_ms} ms)` : '') })
    loadDevices()
  } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Test failed' }) } finally { testingId.value = null }
}

async function detect () {
  detecting.value = true
  try {
    const { data } = await api.get('/fingerprint/devices/detect')
    if (!data.length) { Notify.create({ type: 'info', message: proxy.$t('NoDevicesDetected') }); return }
    Notify.create({ type: 'positive', message: `${data.length} ${proxy.$t('DevicesDetected')}` })
    // Pre-fill the add form with the first detected device.
    const d = data[0]
    Object.assign(form, blank(), { name: d.name, brand: d.brand, model: d.model, connection: d.connection, serial: d.serial })
    dialog.value = true
  } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Detect failed' }) } finally { detecting.value = false }
}

onMounted(boot)
</script>

<style scoped>
.fp-policy-head { font-weight: 800; color: var(--q-primary); display: flex; align-items: center; margin-bottom: 6px; }
.fp-dev { border-radius: 12px; transition: box-shadow .2s; }
.fp-dev:hover { box-shadow: 0 8px 22px -14px rgba(18,58,102,.5); }
</style>
