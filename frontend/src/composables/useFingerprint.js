import { ref } from 'vue'
import { api } from '@/boot/axios'

// Client side of the biometric subsystem: device list, policy, and the
// capture / enrol / verify calls. Templates never touch the UI as raw images —
// capture returns an opaque template the server stores encrypted.
const settings = ref({ enabled: false, enforcement: 'optional', allow_override: true, allow_pin_fallback: false, fallback_when_unavailable: true, min_quality: 40 })
const brands = ref([])
const devices = ref([])
let loaded = false

export function useFingerprint () {
  async function loadSettings (force = false) {
    if (loaded && !force) return
    try {
      const { data } = await api.get('/fingerprint/settings')
      settings.value = data.settings || settings.value
      brands.value = data.brands || []
      loaded = true
    } catch (_) { /* leave defaults */ }
  }

  async function loadDevices () {
    try { const { data } = await api.get('/fingerprint/devices'); devices.value = data || [] } catch (_) {}
  }

  function defaultDevice () {
    return devices.value.find(d => d.is_default && d.active) || devices.value.find(d => d.active) || devices.value[0] || null
  }

  /** Live scan on a device (Simulator works out of the box). */
  async function capture (deviceId) {
    const id = deviceId || defaultDevice()?.id
    const { data } = await api.post('/fingerprint/capture', { device_id: id })
    return { ...data, device_id: id }
  }

  async function enroll (payload) {
    const { data } = await api.post('/fingerprint/enroll', payload)
    return data
  }

  async function enrollments (enrollableType, enrollableId) {
    const { data } = await api.get('/fingerprint/enrollments', { params: { enrollable_type: enrollableType, enrollable_id: enrollableId } })
    return data || []
  }

  async function removeEnrollment (id) { await api.delete('/fingerprint/enrollments/' + id) }

  async function verify (enrollableType, enrollableId, template) {
    const { data } = await api.post('/fingerprint/verify', { enrollable_type: enrollableType, enrollable_id: enrollableId, template })
    return data
  }

  async function verifyPayment (paymentId, payload) {
    const { data } = await api.post('/fingerprint/payments/' + paymentId + '/verify', payload)
    return data
  }

  return { settings, brands, devices, loadSettings, loadDevices, defaultDevice, capture, enroll, enrollments, removeEnrollment, verify, verifyPayment }
}
