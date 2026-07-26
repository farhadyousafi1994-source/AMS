<template>
  <!--
    Money field with a currency selector. When a non-base currency is chosen it
    reveals an inline "Afghani rate" input (beautifully, right under the amount)
    so the user can confirm/adjust the AFN-per-unit rate at the moment of entry
    — and, with one tick, push that as today's official daily rate.

      <money-input v-model="form.contract_value" v-model:currency="form.currency"
                   v-model:rate="form.rate" :label="$t('ProjectBudget')" />

    v-model         → amount (number)
    v-model:currency→ currency code
    v-model:rate    → locked AFN-per-unit rate at entry (auto-prefilled)
  -->
  <div class="money-input">
    <div class="row q-col-gutter-sm items-start">
      <div :class="currencyCol">
        <q-input outlined dense :color="color" type="number" :model-value="modelValue"
          @update:model-value="$emit('update:modelValue', $event === '' ? null : Number($event))"
          :label="label" :rules="rules" :hide-bottom-space="hideBottomSpace">
          <template #prepend><q-icon name="payments" :color="color" /></template>
        </q-input>
      </div>
      <div class="col-auto" style="min-width:104px">
        <q-select outlined dense :color="color" :model-value="currency"
          @update:model-value="onCurrency" :options="currencyOptions" emit-value map-options
          :label="$t('Currency')" />
      </div>
    </div>

    <!-- Inline live Afghani rate (only when currency ≠ base) -->
    <transition name="rate-reveal">
      <div v-if="!isBase" class="rate-strip q-mt-xs">
        <div class="rate-strip__icon"><q-icon name="currency_exchange" size="17px" /></div>
        <div class="rate-strip__body">
          <div class="rate-strip__row">
            <span class="rate-strip__lead">1 {{ currency }} =</span>
            <q-input dense borderless class="rate-strip__field" type="number"
              :model-value="rate" @update:model-value="onRate"
              :placeholder="String(prefill || '')">
            </q-input>
            <span class="rate-strip__unit">{{ base }}</span>
          </div>
          <div class="rate-strip__hint">
            <template v-if="Number(modelValue)">≈ {{ fmt(baseValue) }} {{ base }}</template>
            <template v-else>{{ $t('TodaysRate') }}</template>
          </div>
        </div>
        <template v-if="allowSaveRate">
          <q-toggle :model-value="saveRate" @update:model-value="$emit('update:saveRate', $event)"
            dense size="sm" :color="color" class="rate-strip__save">
            <q-tooltip>{{ $t('UpdateDailyRateHint') }}</q-tooltip>
          </q-toggle>
          <div class="rate-strip__savelabel">{{ $t('SetAsTodaysRate') }}</div>
        </template>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { api } from '@/boot/axios'
import { useCurrency } from '@/composables/useCurrency'

const props = defineProps({
  modelValue: { type: [Number, String], default: null },
  currency: { type: String, default: 'AFN' },
  rate: { type: [Number, String], default: null },
  saveRate: { type: Boolean, default: false },
  allowSaveRate: { type: Boolean, default: true },  // show the "set as today's rate" toggle
  label: { type: String, default: '' },
  color: { type: String, default: 'primary' },
  rules: { type: Array, default: () => [] },
  hideBottomSpace: { type: Boolean, default: true },
})
const emit = defineEmits(['update:modelValue', 'update:currency', 'update:rate', 'update:saveRate'])

const { base, loadRates, rateFor } = useCurrency()
const currencyOptions = ref([{ label: 'AFN', value: 'AFN' }])

const isBase = computed(() => !props.currency || props.currency === base.value)
const prefill = computed(() => rateFor(props.currency))
const currencyCol = computed(() => 'col')

const baseValue = computed(() => Number(props.modelValue || 0) * Number(props.rate || prefill.value || 1))

function fmt (v) { return Number(v || 0).toLocaleString('en-US') }

function onCurrency (val) {
  emit('update:currency', val)
  // When switching to a non-base currency, prefill the locked rate from today's.
  if (val && val !== base.value) {
    emit('update:rate', rateFor(val))
  } else {
    emit('update:rate', 1)
    emit('update:saveRate', false)
  }
}
function onRate (val) {
  emit('update:rate', val === '' ? null : Number(val))
}

async function loadCurrencies () {
  try {
    const { data } = await api.get('/currencies')
    const list = (Array.isArray(data) ? data : (data.data ?? [])).filter(c => c.active !== false)
    if (list.length) currencyOptions.value = list.map(c => ({ label: c.code, value: c.code }))
  } catch (_) {
    currencyOptions.value = [{ label: 'AFN', value: 'AFN' }, { label: 'USD', value: 'USD' }]
  }
}

onMounted(async () => {
  await loadRates()
  await loadCurrencies()
  // Prefill the rate if a non-base currency is already selected and no rate set.
  if (!isBase.value && (props.rate == null || Number(props.rate) === 0 || Number(props.rate) === 1)) {
    emit('update:rate', rateFor(props.currency))
  }
})

// Keep the rate sensible if the currency prop changes externally.
watch(() => props.currency, (c) => {
  if (c && c !== base.value && (props.rate == null || Number(props.rate) <= 1)) {
    emit('update:rate', rateFor(c))
  }
})
</script>

<style scoped>
.rate-strip {
  display: flex; align-items: center; gap: 10px;
  background: linear-gradient(135deg, color-mix(in srgb, var(--q-primary) 8%, #fff), color-mix(in srgb, var(--q-secondary, #0EA5A4) 8%, #fff));
  border: 1px solid color-mix(in srgb, var(--q-primary) 22%, #fff);
  border-radius: 12px; padding: 7px 12px;
}
.rate-strip__icon {
  width: 30px; height: 30px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  background: var(--q-primary); color: #fff; flex: 0 0 auto;
}
.rate-strip__body { flex: 1 1 auto; min-width: 0; }
.rate-strip__row { display: flex; align-items: baseline; gap: 6px; }
.rate-strip__lead { font-size: 12.5px; font-weight: 700; color: #475569; white-space: nowrap; }
.rate-strip__field { max-width: 110px; font-weight: 800; }
.rate-strip__field :deep(input) { font-size: 16px; font-weight: 800; color: var(--q-primary); }
.rate-strip__unit { font-size: 12.5px; font-weight: 800; color: var(--q-primary); }
.rate-strip__hint { font-size: 11px; color: #64748B; margin-top: 1px; }
.rate-strip__save { flex: 0 0 auto; }
.rate-strip__savelabel { font-size: 10.5px; font-weight: 700; color: #475569; max-width: 60px; line-height: 1.05; }

.rate-reveal-enter-active, .rate-reveal-leave-active { transition: all 0.22s ease; }
.rate-reveal-enter-from, .rate-reveal-leave-to { opacity: 0; transform: translateY(-4px); }

body.body--rtl .rate-strip__field :deep(input) { text-align: left; }
</style>
