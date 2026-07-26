<template>
  <div class="login-box">

    <!-- Header -->
    <div class="login-brand">
      <brand-mark size="36" />
      <div>
        <div class="login-brand__name">Aria Herat ERP</div>
        <div class="login-brand__ver">Construction ERP · v1.0</div>
      </div>
    </div>

    <div class="login-title">Welcome back</div>
    <div class="login-sub">Sign in to access your workspace</div>

    <!-- Form -->
    <q-form @submit="onSubmit" class="login-form">

      <div class="login-field-label">Email address</div>
      <q-input v-model="form.email" type="email" outlined dense autofocus
        placeholder="you@ariaherat.af"
        :rules="[v => !!v || 'Required']"
        class="login-input">
        <template #prepend>
          <q-icon name="alternate_email" size="18px" color="grey-5" />
        </template>
      </q-input>

      <div class="login-field-label q-mt-sm">Password</div>
      <q-input v-model="form.password" :type="showPwd ? 'text' : 'password'" outlined dense
        placeholder="••••••••"
        :rules="[v => !!v || 'Required']"
        class="login-input">
        <template #prepend>
          <q-icon name="lock_outline" size="18px" color="grey-5" />
        </template>
        <template #append>
          <q-icon :name="showPwd ? 'visibility_off' : 'visibility'"
            size="18px" color="grey-5" class="cursor-pointer" @click="showPwd = !showPwd" />
        </template>
      </q-input>

      <div class="row items-center q-mt-xs q-mb-md">
        <q-toggle v-model="form.remember" label="Remember me" dense size="sm" color="cyan-7" />
      </div>

      <q-btn type="submit" unelevated class="full-width login-btn"
        :loading="loading" :disable="loading">
        <span>Sign In</span>
        <q-icon name="arrow_forward" size="18px" class="q-ml-xs" />
      </q-btn>
    </q-form>

    <!-- Demo account -->
    <div class="login-demo">
      <div class="login-demo__title">Quick demo login</div>
      <div class="row q-gutter-xs q-mt-xs">
        <q-btn flat dense size="sm" outline color="grey-6" class="login-demo__btn"
          @click="fillDemo">
          Administrator
        </q-btn>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { useAuthStore } from '@/stores/auth'
import { syncState } from '@/services/syncService'
import BrandMark from '@/components/general/BrandMark.vue'

const router = useRouter()
const $q = useQuasar()
const auth = useAuthStore()

const showPwd = ref(false)
const loading = ref(false)
// Restore the remembered email so returning users only type their password.
const rememberedEmail = (typeof localStorage !== 'undefined') ? localStorage.getItem('remember_email') || '' : ''
const form = reactive({ email: rememberedEmail, password: '', remember: !!rememberedEmail })
const isOffline = computed(() => !syncState.isOnline)

function fillDemo() {
  form.email = 'admin@ariaherat.af'
  form.password = 'password'
}

async function onSubmit() {
  loading.value = true
  try {
    await auth.login(form)
    // Persist (or clear) the remembered email per the toggle.
    if (form.remember) localStorage.setItem('remember_email', form.email)
    else localStorage.removeItem('remember_email')
    router.push({ name: 'dashboard' })
    $q.notify({ type: 'positive', position: 'bottom', icon: 'waving_hand', message: 'Welcome back!' })
  } catch (e) {
    $q.notify({ type: 'negative', message: e?.response?.data?.message || 'Login failed' })
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.login-box {
  width: 100%;
}

.login-brand {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 36px;
}
.login-brand__name { font-size: 16px; font-weight: 700; color: #0F172A; }
.login-brand__ver  { font-size: 11px; color: #94A3B8; }

.login-title {
  font-size: 26px;
  font-weight: 800;
  color: #0F172A;
  letter-spacing: -0.5px;
  margin-bottom: 6px;
}
.login-sub {
  font-size: 14px;
  color: #64748B;
  margin-bottom: 28px;
}

.login-form { display: flex; flex-direction: column; }

.login-field-label {
  font-size: 12px;
  font-weight: 600;
  color: #475569;
  margin-bottom: 4px;
}
.login-input :deep(.q-field__control) {
  border-radius: 10px !important;
}
.login-input { margin-bottom: 4px; }

.login-btn {
  background: linear-gradient(135deg, #123A66, #175A8C) !important;
  color: #fff !important;
  height: 44px;
  border-radius: 10px !important;
  font-size: 15px;
  font-weight: 600;
}

/* Demo section */
.login-demo {
  margin-top: 28px;
  padding-top: 20px;
  border-top: 1px solid #F1F5F9;
}
.login-demo__title {
  font-size: 11px;
  color: #94A3B8;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  font-weight: 600;
  margin-bottom: 6px;
}
.login-demo__btn {
  font-size: 11px;
  border-radius: 6px !important;
}

/* Offline bar */
.login-offline {
  margin-top: 16px;
  padding: 8px 12px;
  background: #FEF3C7;
  color: #92400E;
  border-radius: 8px;
  font-size: 12px;
  display: flex;
  align-items: center;
}
</style>
