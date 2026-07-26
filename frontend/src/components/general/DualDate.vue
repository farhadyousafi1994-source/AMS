<template>
  <!--
    Shows a Gregorian (میلادی) and Afghan solar (خورشیدی) date together, nicely.
    The user's calendar preference decides which one leads; the other sits beside
    it in a lighter tone. Use anywhere a single date should be human-readable in
    both calendars:  <dual-date :value="row.start_date" />
  -->
  <span class="dual-date" :class="{ block }">
    <span class="dual-date__primary">{{ primary }}</span>
    <span v-if="secondary" class="dual-date__sep">·</span>
    <span v-if="secondary" class="dual-date__secondary">{{ secondary }}</span>
  </span>
</template>

<script setup>
import { computed } from 'vue'
import { shamsiDate, fmtDateGregorian, calendarPref } from '@/utils/date'

const props = defineProps({
  value: { type: [String, Number, Date], default: null },
  block: { type: Boolean, default: false }, // stack the two lines instead of inline
})

const greg = computed(() => fmtDateGregorian(props.value))
const shamsi = computed(() => shamsiDate(props.value))
const faLead = computed(() => calendarPref() === 'fa')

const primary = computed(() => (faLead.value ? (shamsi.value || greg.value) : greg.value))
const secondary = computed(() => {
  if (!shamsi.value || greg.value === '—') return ''
  return faLead.value ? greg.value : shamsi.value
})
</script>

<style scoped>
.dual-date { display: inline-flex; align-items: baseline; gap: 5px; flex-wrap: wrap; }
.dual-date.block { flex-direction: column; gap: 0; align-items: flex-start; }
.dual-date.block .dual-date__sep { display: none; }
.dual-date__primary { font-weight: 600; }
.dual-date__sep { color: #cbd5e1; }
.dual-date__secondary { color: #64748b; font-size: 0.86em; }
</style>
