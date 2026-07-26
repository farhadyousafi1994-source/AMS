<template>
  <!--
    Bilingual dropdown backed by the Options Registry (`lookups`). Bind it to a
    group and it self-loads, shows EN or Dari by the active locale, and stores
    the stable machine code. Admins can add a missing option inline (EN + Dari)
    without leaving the page.

      <lookup-select v-model="form.unit" group="unit" :label="$t('Unit')" icon="straighten" />

    Set :allow-add="false" to hide the inline add, or :allow-other="true" to let
    the user free-type a value the registry doesn't have.
  -->
  <q-select
    outlined dense
    :color="color" :label-color="color"
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    :options="filtered"
    :label="label"
    :loading="loading"
    emit-value map-options
    :clearable="clearable"
    :use-input="useInput"
    :new-value-mode="allowOther ? 'add-unique' : undefined"
    input-debounce="0" @filter="onFilter"
    :rules="rules"
    :hint="hint"
  >
    <template v-if="icon" #prepend><q-icon :name="icon" :color="color" /></template>

    <!-- show the Dari label as a subtitle in the dropdown so both are visible -->
    <template #option="scope">
      <q-item v-bind="scope.itemProps">
        <q-item-section>
          <q-item-label>{{ scope.opt.label }}</q-item-label>
          <q-item-label v-if="scope.opt.label_fa && scope.opt.label_fa !== scope.opt.label"
            caption class="lookup-alt">{{ altLabel(scope.opt) }}</q-item-label>
        </q-item-section>
      </q-item>
    </template>

    <template #no-option>
      <q-item><q-item-section class="text-grey">{{ $t('NoRecordFound') }}</q-item-section></q-item>
    </template>

    <!-- inline quick-add (EN + Dari) -->
    <template v-if="allowAdd && $can('lookup-create')" #after>
      <q-btn round dense flat :color="color" icon="add" type="button" @click.stop="adding = !adding">
        <q-tooltip>{{ $t('AddNew') }}</q-tooltip>
        <q-menu v-model="adding" no-parent-event anchor="bottom right" self="top right" @hide="draft = { label_en: '', label_fa: '' }">
          <div class="q-pa-md column" style="min-width:280px" @click.stop>
            <div class="row items-center q-mb-sm">
              <q-icon :name="icon || 'list'" :color="color" size="20px" class="q-mr-sm" />
              <div class="text-subtitle2 text-weight-bold">{{ $t('AddNew') }}</div>
              <q-space />
              <div class="text-caption text-grey-6 ellipsis" style="max-width:120px">{{ label }}</div>
            </div>
            <q-input outlined dense :color="color" :label-color="color" v-model="draft.label_en"
              :label="$t('English')" autofocus class="q-mb-sm" @keyup.enter="create">
              <template #prepend><q-icon name="translate" :color="color" size="xs" /></template>
            </q-input>
            <q-input outlined dense :color="color" :label-color="color" v-model="draft.label_fa"
              :label="$t('Dari')" class="q-mb-sm lookup-rtl" @keyup.enter="create">
              <template #prepend><q-icon name="translate" :color="color" size="xs" /></template>
            </q-input>
            <div class="row justify-end q-gutter-sm q-mt-xs">
              <q-btn flat dense :label="$t('Cancel')" color="grey-7" type="button" v-close-popup />
              <q-btn unelevated dense :color="color" icon="save" :label="$t('Save')" type="button" @click="create" :loading="saving" />
            </div>
          </div>
        </q-menu>
      </q-btn>
    </template>
  </q-select>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { api } from '@/boot/axios'
import { Notify } from 'quasar'
import { useLookups } from '@/composables/useLookups'
import { i18n } from '@/boot/i18n'

const props = defineProps({
  modelValue: { type: [Number, String], default: null },
  group: { type: String, required: true },
  label: { type: String, default: '' },
  icon: { type: String, default: 'list' },
  color: { type: String, default: 'primary' },
  clearable: { type: Boolean, default: true },
  allowAdd: { type: Boolean, default: true },     // inline "＋ add option"
  allowOther: { type: Boolean, default: false },  // free-type a value not in the registry
  rules: { type: Array, default: () => [] },
  hint: { type: String, default: '' },
})
const emit = defineEmits(['update:modelValue', 'created'])

const { options: groupOptions, loadLookups } = useLookups()
const loading = ref(false)
const adding = ref(false)
const saving = ref(false)
const draft = ref({ label_en: '', label_fa: '' })
const needle = ref('')

const useInput = computed(() => props.allowOther || true)

const options = computed(() => groupOptions(props.group))
const filtered = computed(() => {
  if (!needle.value) return options.value
  const q = needle.value.toLowerCase()
  return options.value.filter((o) =>
    String(o.label).toLowerCase().includes(q) ||
    String(o.label_fa || '').includes(needle.value) ||
    String(o.label_en || '').toLowerCase().includes(q))
})

function altLabel (opt) {
  // Whichever label isn't the one currently shown as the title.
  const fa = i18n.locale === 'fa' || i18n.locale === 'pa'
  return fa ? opt.label_en : opt.label_fa
}

function onFilter (val, update) {
  update(() => { needle.value = val })
}

async function create () {
  if (!draft.value.label_en && !draft.value.label_fa) return
  saving.value = true
  try {
    const { data } = await api.post('/lookups', {
      group: props.group,
      label_en: draft.value.label_en || draft.value.label_fa,
      label_fa: draft.value.label_fa || null,
    })
    await loadLookups(true)
    if (data?.code) emit('update:modelValue', data.code)
    emit('created', data)
    adding.value = false
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: 'Added' })
  } catch (e) {
    Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Save failed' })
  } finally {
    saving.value = false
  }
}

onMounted(loadLookups)
</script>

<style scoped>
.lookup-rtl :deep(input) { direction: rtl; text-align: right; }
.lookup-alt { opacity: .7; }
</style>
