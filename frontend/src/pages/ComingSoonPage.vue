<template>
  <q-page>
    <m-backgrounds>
      <div class="row q-pa-sm">
        <div class="col-12">
          <m-header :icon="icon" controlRoomButton="false" class="q-mt-xs">
            {{ title }}
          </m-header>
        </div>
        <div class="col-12 q-mt-xl flex flex-center">
          <q-card class="my_radio_less three_d_plan q-pa-xl text-center" style="max-width:440px">
            <q-icon :name="icon" size="56px" color="grey-5" class="q-mb-md" />
            <div class="text-h6 text-grey-8 q-mb-xs">{{ title }}</div>
            <div class="text-body2 text-grey-6">
              This module ships in the next phase, per the Nova Technologies proposal.
              The platform shell — auth, users &amp; roles, multi-branch, offline sync — is ready for it.
            </div>
          </q-card>
        </div>
      </div>
    </m-backgrounds>
  </q-page>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()

const labels = {
  projects:    { title: 'Projects & Sites',        icon: 'domain' },
  finance:     { title: 'Finance & Accounting',     icon: 'account_balance_wallet' },
  procurement: { title: 'Procurement & Assets',     icon: 'local_shipping' },
  hr:          { title: 'HR & Payroll',             icon: 'badge' },
}

const key = computed(() => route.params.module)
const title = computed(() => labels[key.value]?.title ?? 'Coming Soon')
const icon = computed(() => labels[key.value]?.icon ?? 'hourglass_empty')
</script>
