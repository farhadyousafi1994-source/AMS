<template>
  <!--
    Searchable picker over the cross-project subcontractor registry (استادکاران).
    Pick an existing subcontractor by name, or press ＋ to register a brand-new
    one on the spot — only the name is required — so it immediately becomes a
    payable party finance can use.

      <subcontractor-select v-model="form.tradesman_id" @selected="onPick" :label="$t('Name')" />
  -->
  <div>
    <q-select
      outlined dense
      :color="color" :label-color="color"
      :model-value="modelValue"
      @update:model-value="onPick"
      :options="filtered"
      :label="label"
      :loading="loading"
      emit-value map-options clearable
      use-input input-debounce="0" @filter="onFilter"
      :rules="rules"
    >
      <template #prepend><q-icon name="engineering" :color="color" /></template>

      <template #option="scope">
        <q-item v-bind="scope.itemProps">
          <q-item-section avatar>
            <q-avatar size="28px" color="teal-1" text-color="teal-8">{{ (scope.opt.label || '؟').slice(0, 1) }}</q-avatar>
          </q-item-section>
          <q-item-section>
            <q-item-label>{{ scope.opt.label }}</q-item-label>
            <q-item-label caption>
              <span v-if="scope.opt.trade">{{ scope.opt.trade }}</span>
              <span v-if="scope.opt.phone"> · {{ scope.opt.phone }}</span>
              <span v-if="scope.opt.code" class="text-grey-5"> · {{ scope.opt.code }}</span>
            </q-item-label>
          </q-item-section>
        </q-item>
      </template>

      <template #no-option>
        <q-item>
          <q-item-section class="text-grey">{{ $t('NoRecordFound') }}</q-item-section>
          <q-item-section side>
            <q-btn dense flat color="primary" icon="person_add" :label="$t('RegisterSubcontractor')" @click="openRegister(needle)" />
          </q-item-section>
        </q-item>
      </template>

      <template #after>
        <q-btn round dense flat :color="color" icon="person_add" type="button" @click.stop="openRegister('')">
          <q-tooltip>{{ $t('RegisterSubcontractor') }}</q-tooltip>
        </q-btn>
      </template>
    </q-select>

    <!-- The exact "register a new subcontractor" modal — only the name is required -->
    <m-modal :showCM="dialog" @update:showCM="dialog = $event" card_style="width: 420px">
      <q-card class="bg-white">
        <n-header icon="engineering">{{ $t('RegisterSubcontractor') }}</n-header>
        <q-separator />
        <q-form @submit="register">
          <q-card-section class="row q-col-gutter-sm">
            <div class="col-12"><n-name :name="draft.name" @update:name="draft.name = $event" icon="person" :label="$t('Name')" autofocus /></div>
            <div class="col-12 col-sm-6"><n-name :name="draft.phone" @update:name="draft.phone = $event" icon="phone" :label="$t('Phone')" :rules="[]" /></div>
            <div class="col-12 col-sm-6"><n-name :name="draft.trade" @update:name="draft.trade = $event" icon="construction" :label="$t('Trade')" :rules="[]" /></div>
            <div class="col-12 text-caption text-grey-6"><q-icon name="info" size="14px" class="q-mr-xs" />{{ $t('OnlyNameRequiredHint') }}</div>
          </q-card-section>
          <q-separator />
          <n-submit :submitting="saving" :label="$t('Save')" />
        </q-form>
      </q-card>
    </m-modal>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { api } from '@/boot/axios'
import { Notify } from 'quasar'

const props = defineProps({
  modelValue: { type: [Number, String], default: null },
  label: { type: String, default: '' },
  color: { type: String, default: 'primary' },
  rules: { type: Array, default: () => [] },
})
const emit = defineEmits(['update:modelValue', 'selected', 'created'])

const raw = ref([])
const loading = ref(false)
const needle = ref('')
const dialog = ref(false)
const saving = ref(false)
const draft = ref({ name: '', phone: '', trade: '' })

const options = computed(() => raw.value.map((t) => ({
  label: t.name, value: t.id, trade: t.trade, phone: t.phone, code: t.code,
})))
const filtered = computed(() => {
  if (!needle.value) return options.value
  const q = needle.value.toLowerCase()
  return options.value.filter((o) =>
    String(o.label || '').toLowerCase().includes(q) ||
    String(o.trade || '').toLowerCase().includes(q) ||
    String(o.phone || '').includes(needle.value))
})

async function load () {
  loading.value = true
  try {
    const { data } = await api.get('/tradesmen')
    raw.value = Array.isArray(data) ? data : (data.tradesmen ?? data.data ?? [])
  } finally { loading.value = false }
}

function onFilter (val, update) { update(() => { needle.value = val }) }

function onPick (id) {
  emit('update:modelValue', id)
  const t = raw.value.find((x) => x.id === id)
  emit('selected', t || null)
}

function openRegister (prefillName) {
  draft.value = { name: prefillName || '', phone: '', trade: '' }
  dialog.value = true
}

async function register () {
  if (!draft.value.name) return
  saving.value = true
  try {
    const { data } = await api.post('/tradesmen', { name: draft.value.name, phone: draft.value.phone || null, trade: draft.value.trade || null })
    await load()
    emit('update:modelValue', data.id)
    emit('selected', data)
    emit('created', data)
    dialog.value = false
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: 'Registered' })
  } catch (e) {
    Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Save failed' })
  } finally { saving.value = false }
}

onMounted(load)
</script>
