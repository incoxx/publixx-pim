<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useLicenseStore } from '@/stores/license'
import { useConnectorsStore } from '@/stores/connectors'
import authApi from '@/api/auth'
import AnyPimLogo from '@/components/shared/AnyPimLogo.vue'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const licenseStore = useLicenseStore()
const connectorsStore = useConnectorsStore()

const email = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)
const ssoEnabled = ref(false)
const ssoLabel = ref('Mit Microsoft anmelden')
const ssoLoading = ref(false)

async function handleLogin() {
  error.value = ''
  loading.value = true
  try {
    await authStore.login({ email: email.value, password: password.value })
    licenseStore.fetchLicense()
    connectorsStore.loadConfiguredPlugins()
    router.push(route.query.redirect || '/products')
  } catch (e) {
    error.value = e.response?.data?.detail || e.response?.data?.title || 'Anmeldung fehlgeschlagen'
  } finally {
    loading.value = false
  }
}

function handleSsoLogin() {
  const apiBase = import.meta.env.VITE_API_BASE_URL || '/api/v1'
  const redirectPath = route.query.redirect || '/products'
  window.location.href = `${apiBase}/auth/sso/redirect?redirect=${encodeURIComponent(redirectPath)}`
}

async function handleSsoCallback() {
  const params = new URLSearchParams(window.location.search)
  const ssoToken = params.get('sso_token')
  const ssoError = params.get('sso_error')

  if (ssoError) {
    error.value = ssoError
    // Clean URL
    window.history.replaceState({}, '', window.location.pathname)
    return
  }

  if (!ssoToken) return

  ssoLoading.value = true

  try {
    // Store token
    authStore.token = ssoToken
    localStorage.setItem('pim_token', ssoToken)

    // Fetch user info
    await authStore.checkAuth()

    if (!authStore.user) {
      throw new Error('User info could not be loaded')
    }

    // Load license & connectors after SSO login
    licenseStore.fetchLicense()
    connectorsStore.loadConfiguredPlugins()

    // Clean URL and redirect
    const redirectPath = params.get('redirect') || '/products'
    window.history.replaceState({}, '', window.location.pathname)
    router.push(redirectPath)
  } catch (e) {
    error.value = 'SSO-Anmeldung fehlgeschlagen. Bitte versuchen Sie es erneut.'
    authStore.token = null
    localStorage.removeItem('pim_token')
    window.history.replaceState({}, '', window.location.pathname)
  } finally {
    ssoLoading.value = false
  }
}

async function loadSsoConfig() {
  try {
    const { data } = await authApi.ssoConfig()
    const cfg = data.data || data
    ssoEnabled.value = cfg.enabled
    if (cfg.label) ssoLabel.value = cfg.label
  } catch {
    // SSO not available — keep button hidden
  }
}

onMounted(async () => {
  // Handle SSO callback first (has sso_token in URL)
  await handleSsoCallback()

  // Then load SSO config
  await loadSsoConfig()
})
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-[var(--color-bg)]">
    <div class="w-full max-w-sm">
      <div class="text-center mb-8">
        <div class="inline-flex mb-4">
          <AnyPimLogo size="lg" />
        </div>
        <p class="text-sm text-[var(--color-text-secondary)] mt-1">Product Information Management</p>
      </div>

      <!-- SSO Loading -->
      <div v-if="ssoLoading" class="pim-card p-6 text-center">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-[var(--color-primary)] mx-auto mb-3"></div>
        <p class="text-sm text-[var(--color-text-secondary)]">Anmeldung wird verarbeitet…</p>
      </div>

      <!-- Login Form -->
      <template v-else>
        <form @submit.prevent="handleLogin" class="pim-card p-6 space-y-4">
          <div v-if="error" class="p-3 rounded-md bg-[var(--color-error-light)] text-[var(--color-error)] text-sm">
            {{ error }}
          </div>

          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">E-Mail</label>
            <input v-model="email" type="email" required class="pim-input" placeholder="admin@example.com" autofocus />
          </div>

          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Passwort</label>
            <input v-model="password" type="password" required class="pim-input" placeholder="••••••••" />
          </div>

          <button type="submit" class="pim-btn pim-btn-primary w-full py-2.5" :disabled="loading">
            {{ loading ? 'Anmelden…' : 'Anmelden' }}
          </button>
        </form>

        <!-- SSO Button -->
        <div v-if="ssoEnabled" class="mt-4">
          <div class="relative flex items-center justify-center my-3">
            <div class="absolute inset-0 flex items-center">
              <div class="w-full border-t border-[var(--color-border)]"></div>
            </div>
            <span class="relative bg-[var(--color-bg)] px-3 text-xs text-[var(--color-text-tertiary)]">oder</span>
          </div>

          <button
            @click="handleSsoLogin"
            class="pim-btn w-full py-2.5 border border-[var(--color-border)] bg-[var(--color-surface)] hover:bg-[var(--color-surface-hover)] text-[var(--color-text)] flex items-center justify-center gap-2"
          >
            <svg class="w-5 h-5" viewBox="0 0 21 21" xmlns="http://www.w3.org/2000/svg">
              <rect x="1" y="1" width="9" height="9" fill="#f25022"/>
              <rect x="11" y="1" width="9" height="9" fill="#7fba00"/>
              <rect x="1" y="11" width="9" height="9" fill="#00a4ef"/>
              <rect x="11" y="11" width="9" height="9" fill="#ffb900"/>
            </svg>
            {{ ssoLabel }}
          </button>
        </div>
      </template>
    </div>
  </div>
</template>
