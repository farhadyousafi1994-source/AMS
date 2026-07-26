<template>
  <div>
    <q-input v-bind="$attrs" outlined dense :color="color" :label-color="color"
      :model-value="displayValue" @update:model-value="onInput" :label="label" readonly
      :hint="secondaryHint">
      <template v-slot:prepend>
        <q-icon name="event" :color="color" class="cursor-pointer">
          <!-- Afghan solar (خورشیدی) calendar -->
          <q-popup-proxy v-if="shamsiMode" cover transition-show="scale" transition-hide="scale">
            <div class="shamsi-picker bg-white shadow-5 q-pa-md" style="min-width:280px">
              <div class="row items-center justify-between q-mb-sm">
                <q-btn flat dense round icon="chevron_left" @click="prevMonth" />
                <div class="text-subtitle2 text-center">{{ persianMonthName }} {{ shamsiYear }}</div>
                <q-btn flat dense round icon="chevron_right" @click="nextMonth" />
              </div>
              <div class="row text-caption text-grey-6 q-mb-xs">
                <div v-for="d in dayNames" :key="d" class="col text-center">{{ d }}</div>
              </div>
              <div class="row q-col-gutter-xs">
                <div v-for="(day, i) in calendarDays" :key="i" class="col" style="min-width:14.28%">
                  <q-btn v-if="day" flat dense size="sm" :class="dayClass(day)" @click="selectDay(day)">
                    {{ day }}
                  </q-btn>
                </div>
              </div>
              <q-separator class="q-my-sm" />
              <div class="text-caption text-grey-6 text-center">میلادی: {{ gregorianEquiv || (modelValue || '—') }}</div>
            </div>
          </q-popup-proxy>
          <!-- Gregorian (میلادی) calendar -->
          <q-popup-proxy v-else cover transition-show="scale" transition-hide="scale">
            <q-date :model-value="modelValue" mask="YYYY-MM-DD" :color="color" today-btn
              @update:model-value="onGregPick">
              <div class="row items-center justify-between">
                <div class="text-caption text-grey-7">{{ shamsiPreview }}</div>
                <q-btn v-close-popup label="OK" color="primary" flat dense />
              </div>
            </q-date>
          </q-popup-proxy>
        </q-icon>
      </template>
      <template v-slot:append>
        <q-btn flat dense round size="xs" icon="swap_horiz" :color="color" @click="toggleMode">
          <q-tooltip>{{ shamsiMode ? 'میلادی / Gregorian' : 'خورشیدی / Solar' }}</q-tooltip>
        </q-btn>
      </template>
    </q-input>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { AFGHAN_MONTHS, shamsiDate, fmtDateGregorian, calendarPref, toShamsi, toGregorian, shamsiMonthLength } from '@/utils/date'

const shamsiToGregorian = (jy, jm, jd) => toGregorian(jy, jm, jd)

const props = defineProps({
  modelValue: { type: String, default: '' },
  label: { type: String, default: 'Date' },
  color: { type: String, default: 'primary' }
})
const emit = defineEmits(['update:modelValue'])

// Default the picker to whichever calendar the user set as primary.
const shamsiMode = ref(calendarPref() === 'fa')
const today = new Date()

// Afghan (خورشیدی) month names — حمل، ثور، جوزا… (not the Iranian فروردین…).
const shamsiMonths = AFGHAN_MONTHS
const dayNames = ['ش','ی','د','س','چ','پ','ج']

const todayShamsi = computed(() => toShamsi(today))
const shamsiYear = ref(todayShamsi.value.y)
const shamsiMonth = ref(todayShamsi.value.m)
const selectedDay = ref(null)

const persianMonthName = computed(() => shamsiMonths[shamsiMonth.value - 1])

const calendarDays = computed(() => {
  const monthDays = shamsiMonthLength(shamsiYear.value, shamsiMonth.value)
  const firstGreg = shamsiToGregorian(shamsiYear.value, shamsiMonth.value, 1)
  const firstDate = new Date(firstGreg)
  const startDay = (firstDate.getDay() + 1) % 7
  const days = new Array(startDay).fill(null)
  for (let d = 1; d <= monthDays; d++) days.push(d)
  return days
})

const gregorianEquiv = computed(() => {
  if (!selectedDay.value) return ''
  return shamsiToGregorian(shamsiYear.value, shamsiMonth.value, selectedDay.value)
})

const displayValue = computed(() => {
  if (!props.modelValue) return ''
  if (!shamsiMode.value) return props.modelValue
  try {
    const d = new Date(props.modelValue)
    const s = toShamsi(d)
    return `${s.y}/${String(s.m).padStart(2,'0')}/${String(s.d).padStart(2,'0')}`
  } catch { return props.modelValue }
})

// The "other" calendar shown as a hint under the field, so both are always visible.
const secondaryHint = computed(() => {
  if (!props.modelValue) return ''
  return shamsiMode.value ? ('میلادی: ' + fmtDateGregorian(props.modelValue)) : ('خورشیدی: ' + shamsiDate(props.modelValue))
})
// Shamsi preview inside the Gregorian popup.
const shamsiPreview = computed(() => props.modelValue ? shamsiDate(props.modelValue) : '')

function onGregPick(val) {
  if (val) emit('update:modelValue', String(val).slice(0, 10))
}

function toggleMode() { shamsiMode.value = !shamsiMode.value }
function prevMonth() { if (shamsiMonth.value === 1) { shamsiMonth.value = 12; shamsiYear.value-- } else shamsiMonth.value-- }
function nextMonth() { if (shamsiMonth.value === 12) { shamsiMonth.value = 1; shamsiYear.value++ } else shamsiMonth.value++ }

function selectDay(d) {
  selectedDay.value = d
  const greg = shamsiToGregorian(shamsiYear.value, shamsiMonth.value, d)
  emit('update:modelValue', greg)
}

function dayClass(d) {
  const classes = ['full-width']
  if (d === selectedDay.value) classes.push('bg-primary text-white')
  else if (d === todayShamsi.value.d && shamsiMonth.value === todayShamsi.value.m && shamsiYear.value === todayShamsi.value.y) {
    classes.push('text-primary text-weight-bold')
  }
  return classes.join(' ')
}

function onInput(val) {
  emit('update:modelValue', val)
}

watch(() => props.modelValue, (val) => {
  if (val && shamsiMode.value) {
    try {
      const d = new Date(val)
      const s = toShamsi(d)
      shamsiYear.value = s.y
      shamsiMonth.value = s.m
      selectedDay.value = s.d
    } catch {}
  }
}, { immediate: true })
</script>

<style scoped>
.shamsi-picker { border-radius: 12px; }
</style>
