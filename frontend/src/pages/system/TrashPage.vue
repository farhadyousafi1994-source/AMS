<template>
  <q-page>
    <m-backgrounds>
      <div class="row my_radio_less q-pa-sm">
        <div class="col-12">
          <m-header icon="auto_delete" controlRoomButton="false" class="q-mt-xs">
            {{ $t('Trashes') }}
          </m-header>
        </div>
        <div class="col-12 q-mt-sm">
          <div class="row q-col-gutter-sm">
            <div v-for="section in sections" :key="section.label" class="col-12 col-sm-6 col-md-4">
              <q-card class="my_radio_less">
                <q-card-section class="q-pa-sm">
                  <div class="row items-center">
                    <q-icon :name="section.icon" size="sm" color="negative" class="q-mr-sm" />
                    <div>
                      <div class="text-caption text-grey-7">{{ section.label }}</div>
                      <div class="text-weight-bold">
                        <q-skeleton v-if="loading" type="text" width="40px" />
                        <span v-else>{{ section.count }} deleted</span>
                      </div>
                    </div>
                  </div>
                </q-card-section>
              </q-card>
            </div>
          </div>
        </div>
      </div>
    </m-backgrounds>
  </q-page>
</template>
<script setup>
import { reactive, onMounted, ref } from 'vue'
import { api } from '@/boot/axios'
const loading = ref(false)
const sections = reactive([
  { label: 'Companies', icon: 'business', count: 0, endpoint: 'companies' },
  { label: 'Branches', icon: 'store', count: 0, endpoint: 'branches' },
  { label: 'Users', icon: 'manage_accounts', count: 0, endpoint: 'users' },
])
async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/trash-counts')
    if (data) {
      sections.forEach(s => { s.count = data[s.endpoint] ?? 0 })
    }
  } catch (_) {} finally { loading.value = false }
}
onMounted(load)
</script>
