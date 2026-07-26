<template>
  <m-modal :showCM="show" @update:showCM="close" card_style="width: 420px">
    <q-card class="bg-white">
      <n-header icon="fingerprint">{{ $t('VerifyIdentity') }}</n-header>
      <q-separator />
      <q-card-section class="text-center q-pb-sm">
        <div class="text-caption text-grey-6 q-mb-sm">{{ $t('VerifyBeforePayment') }}</div>
        <div v-if="person" class="text-subtitle1 text-weight-bold q-mb-md">{{ person }}</div>

        <!-- Scanner -->
        <div class="fp-scanner" :class="stateClass" @click="scan">
          <q-spinner-puff v-if="scanning" color="teal" size="56px" />
          <q-icon v-else :name="stateIcon" size="56px" />
          <div class="fp-scanner__ring"></div>
        </div>
        <div class="fp-msg" :class="stateClass">{{ message }}</div>
        <div v-if="deviceName" class="text-caption text-grey-5 q-mt-xs"><q-icon name="usb" size="12px" /> {{ deviceName }}</div>

        <div class="q-mt-md">
          <q-btn v-if="state !== 'ok'" unelevated color="teal-7" icon="fingerprint" :label="scanning ? $t('Scanning') : $t('Scan')" :loading="scanning" @click="scan" />
        </div>

        <!-- Policy-driven fallbacks -->
        <div v-if="state === 'fail' || state === 'nodevice'" class="q-mt-md fp-fallback">
          <div class="text-caption text-grey-6 q-mb-xs">{{ $t('AlternativeVerification') }}</div>
          <div class="row q-gutter-sm justify-center">
            <q-btn v-if="settings.allow_override" outline dense color="deep-orange-7" icon="admin_panel_settings" :label="$t('ManagerOverride')" @click="fallback('override')" />
            <q-btn v-if="settings.allow_pin_fallback" outline dense color="blue-8" icon="pin" :label="$t('PinFallback')" @click="fallback('pin')" />
          </div>
        </div>
      </q-card-section>
      <q-separator />
      <q-card-actions align="right" class="q-pa-sm">
        <q-btn flat :label="$t('Cancel')" color="grey-7" @click="close" />
      </q-card-actions>
    </q-card>
  </m-modal>
</template>

<script setup>
import { ref, computed, watch, getCurrentInstance } from 'vue'
import { Dialog, Notify } from 'quasar'
import { useFingerprint } from '@/composables/useFingerprint'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  paymentId: { type: [Number, String], default: null },
  person: { type: String, default: '' },
})
const emit = defineEmits(['update:modelValue', 'verified'])

const { settings, loadSettings, loadDevices, defaultDevice, capture, verifyPayment } = useFingerprint()
const { proxy } = getCurrentInstance()

const show = computed(() => props.modelValue)
const scanning = ref(false)
const state = ref('idle')   // idle | scanning | ok | fail | nodevice
const message = ref('')
const deviceName = ref('')

const stateClass = computed(() => `fp--${state.value}`)
const stateIcon = computed(() => ({ idle: 'fingerprint', ok: 'check_circle', fail: 'error', nodevice: 'usb_off' }[state.value] || 'fingerprint'))

watch(() => props.modelValue, async (v) => {
  if (!v) return
  state.value = 'idle'; message.value = proxy.$t('PlaceFinger'); scanning.value = false
  await loadSettings(); await loadDevices()
  const d = defaultDevice()
  deviceName.value = d?.name || ''
  if (!d) { state.value = 'nodevice'; message.value = proxy.$t('NoActiveDevice') }
})

function close () { emit('update:modelValue', false) }

async function scan () {
  if (scanning.value) return
  const d = defaultDevice()
  if (!d) { state.value = 'nodevice'; message.value = proxy.$t('NoActiveDevice'); return }
  scanning.value = true; state.value = 'scanning'; message.value = proxy.$t('Scanning')
  try {
    const cap = await capture(d.id)
    await verifyPayment(props.paymentId, { template: cap.template, method: 'template', device_id: cap.device_id })
    state.value = 'ok'; message.value = proxy.$t('IdentityVerified')
    Notify.create({ type: 'positive', position: 'bottom', icon: 'verified', message: proxy.$t('PaymentVerified') })
    emit('verified', { method: 'template' })
    setTimeout(close, 800)
  } catch (e) {
    state.value = 'fail'; message.value = e?.response?.data?.message || proxy.$t('VerificationFailed')
  } finally { scanning.value = false }
}

function fallback (method) {
  Dialog.create({
    title: method === 'override' ? proxy.$t('ManagerOverride') : proxy.$t('PinFallback'),
    message: proxy.$t('FallbackReason'),
    prompt: { model: '', type: method === 'pin' ? 'password' : 'text' },
    cancel: true, persistent: true,
  }).onOk(async (note) => {
    try {
      await verifyPayment(props.paymentId, { method, note })
      state.value = 'ok'; message.value = proxy.$t('IdentityVerified')
      Notify.create({ type: 'positive', position: 'bottom', icon: 'verified', message: proxy.$t('PaymentVerified') })
      emit('verified', { method })
      setTimeout(close, 700)
    } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Failed' }) }
  })
}
</script>

<style scoped>
.fp-scanner { position: relative; width: 110px; height: 110px; margin: 6px auto 10px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; background: #F1F5F9; color: #94A3B8; transition: all .3s; }
.fp-scanner__ring { position: absolute; inset: -4px; border-radius: 50%; border: 2px dashed #CBD5E1; }
.fp--scanning { background: #CCFBF1; color: #0D9488; }
.fp--scanning .fp-scanner__ring { border-color: #14B8A6; animation: fpspin 1.4s linear infinite; }
.fp--ok { background: #DCFCE7; color: #16A34A; }
.fp--fail { background: #FEE2E2; color: #DC2626; }
.fp--nodevice { background: #FEF3C7; color: #D97706; }
.fp-msg { font-size: 13px; font-weight: 700; }
.fp-msg.fp--ok { color: #16A34A; } .fp-msg.fp--fail { color: #DC2626; } .fp-msg.fp--nodevice { color: #D97706; } .fp-msg.fp--scanning { color: #0D9488; }
.fp-fallback { border-top: 1px dashed #E2E8F0; padding-top: 10px; }
@keyframes fpspin { to { transform: rotate(360deg); } }
</style>
