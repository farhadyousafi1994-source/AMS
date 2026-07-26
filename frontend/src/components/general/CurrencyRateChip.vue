<template>
  <!--
    Global daily-rate chip for the top bar. Shows today's rate for each foreign
    currency and lets anyone with permission set/adjust it in one click — so the
    daily rate is reachable from every page, not only the Exchange Rates screen.
  -->
  <q-btn flat dense no-caps color="white" class="rate-chip q-mx-xs">
    <q-icon name="currency_exchange" size="16px" class="q-mr-xs" />
    <span class="rate-chip__text">
      <template v-if="primary">1 {{ primary.code }} = {{ fmt(primary.rate) }} {{ base }}</template>
      <template v-else>{{ base }}</template>
    </span>
    <q-badge v-if="staleToday" color="amber-8" rounded floating class="rate-chip__dot" />
    <q-tooltip>{{ staleToday ? $t('RateNotSet') : $t('DailyRate') }}</q-tooltip>

    <q-menu anchor="bottom right" self="top right" class="rate-menu">
      <q-card style="width:320px" class="rate-card">
        <div class="rate-card__head">
          <q-icon name="currency_exchange" size="18px" class="q-mr-sm" />
          <div class="text-weight-bold">{{ $t('SetDailyRate') }}</div>
          <q-space />
          <div class="rate-card__date">{{ todayLabel }}</div>
        </div>

        <q-list class="q-py-xs">
          <q-item v-for="c in foreign" :key="c.code" class="rate-row">
            <q-item-section avatar style="min-width:44px">
              <div class="rate-row__code">{{ c.code }}</div>
            </q-item-section>
            <q-item-section>
              <div class="row items-center no-wrap" style="gap:6px">
                <span class="rate-row__lead">1 {{ c.code }} =</span>
                <q-input dense outlined type="number" v-model.number="c.draft" class="rate-row__field"
                  :placeholder="String(c.rate || '')" @keyup.enter="save(c)" />
                <span class="rate-row__unit">{{ base }}</span>
              </div>
              <div class="rate-row__hint" :class="{ 'rate-row__hint--stale': c.stale }">
                <q-icon :name="c.stale ? 'warning' : 'event_available'" size="12px" class="q-mr-xs" />
                <span v-if="c.stale">{{ $t('RateNotSet') }}</span>
                <span v-else>{{ $t('TodaysRate') }}: {{ fmt(c.rate) }}</span>
              </div>
            </q-item-section>
            <q-item-section side>
              <q-btn round dense unelevated color="primary" icon="check" size="sm"
                :loading="c.saving" :disable="!c.draft || Number(c.draft) <= 0" @click="save(c)">
                <q-tooltip>{{ $t('SetAsTodaysRate') }}</q-tooltip>
              </q-btn>
            </q-item-section>
          </q-item>
          <q-item v-if="!foreign.length" class="text-grey-6 text-caption justify-center">
            {{ base }} — {{ $t('DailyRate') }}
          </q-item>
        </q-list>
      </q-card>
    </q-menu>
  </q-btn>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Notify } from 'quasar'
import { api } from '@/boot/axios'

const base = ref('AFN')
const foreign = ref([]) // [{ code, rate, date, draft, stale, saving }]
const today = new Date().toISOString().slice(0, 10)
const todayLabel = computed(() => today)

const primary = computed(() => foreign.value.find(c => c.code === 'USD') || foreign.value[0] || null)
const staleToday = computed(() => foreign.value.some(c => c.stale))

function fmt (v) { return v == null ? '—' : Number(v).toLocaleString('en-US', { maximumFractionDigits: 4 }) }

async function load () {
  try {
    const { data } = await api.get('/exchange-rates')
    base.value = data.base || 'AFN'
    foreign.value = (data.current || []).map(c => ({
      code: c.currency_code,
      rate: c.rate_to_base,
      date: c.rate_date,
      draft: c.rate_to_base,
      stale: c.rate_date !== today,
      saving: false,
    }))
  } catch (_) {}
}

async function save (c) {
  if (!c.draft || Number(c.draft) <= 0) return
  c.saving = true
  try {
    await api.post('/exchange-rates', { currency_code: c.code, rate_to_base: Number(c.draft), rate_date: today })
    c.rate = Number(c.draft)
    c.date = today
    c.stale = false
    Notify.create({ type: 'positive', position: 'bottom', icon: 'currency_exchange', message: `${c.code}: ${fmt(c.rate)} ${base.value}` })
  } catch (e) {
    Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Failed' })
  } finally { c.saving = false }
}

onMounted(load)
</script>

<style scoped>
.rate-chip { border: 1px solid rgba(255,255,255,.25); border-radius: 18px; padding: 2px 10px; height: 30px; }
.rate-chip__text { font-size: 12px; font-weight: 600; letter-spacing: .2px; }
.rate-chip__dot { top: 2px; right: 2px; }
.rate-card__head { display: flex; align-items: center; padding: 10px 14px; background: linear-gradient(135deg, #123A66, #175A8C); color: #fff; }
.rate-card__date { font-size: 11px; opacity: .8; }
.rate-row { padding: 8px 12px; }
.rate-row__code { font-weight: 800; color: #123A66; font-size: 13px; }
.rate-row__lead { font-size: 12px; color: #64748b; white-space: nowrap; }
.rate-row__field { max-width: 96px; }
.rate-row__field :deep(input) { font-weight: 800; color: #123A66; }
.rate-row__unit { font-size: 12px; font-weight: 700; color: #123A66; }
.rate-row__hint { font-size: 10.5px; color: #64748b; margin-top: 3px; }
.rate-row__hint--stale { color: #b45309; }
</style>
