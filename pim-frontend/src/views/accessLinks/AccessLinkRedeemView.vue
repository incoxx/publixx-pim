<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import accessLinksApi from '@/api/accessLinks'
import AnyPimLogo from '@/components/shared/AnyPimLogo.vue'
import { Copy, Check, AlertCircle } from 'lucide-vue-next'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const loading = ref(true)
const error = ref('')
const credentials = ref(null)
const copied = ref({ email: false, password: false })

async function copyText(text, field) {
  try {
    await navigator.clipboard.writeText(text)
    copied.value[field] = true
    setTimeout(() => { copied.value[field] = false }, 2000)
  } catch { /* ignore */ }
}

function proceed() {
  router.push('/products')
}

onMounted(async () => {
  const token = route.params.token
  if (!token) {
    error.value = 'Kein Zugangstoken angegeben.'
    loading.value = false
    return
  }

  try {
    const { data } = await accessLinksApi.redeem(token)
    const payload = data.data || data

    // Auto-login: store token and user
    authStore.token = payload.token
    authStore.user = payload.user
    localStorage.setItem('pim_token', payload.token)

    credentials.value = payload.credentials
  } catch (e) {
    const detail = e.response?.data?.detail
    error.value = detail || 'Dieser Zugangslink ist ungültig oder abgelaufen.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-[var(--color-bg)]">
    <div class="w-full max-w-md">
      <div class="text-center mb-8">
        <div class="inline-flex mb-4">
          <AnyPimLogo size="lg" />
        </div>
        <p class="text-sm text-[var(--color-text-secondary)] mt-1">Product Information Management</p>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="pim-card p-6 text-center">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-[var(--color-primary)] mx-auto mb-3"></div>
        <p class="text-sm text-[var(--color-text-secondary)]">Zugang wird eingerichtet…</p>
      </div>

      <!-- Error -->
      <div v-else-if="error" class="pim-card p-6">
        <div class="flex items-start gap-3 p-3 rounded-md bg-[var(--color-error-light)] text-[var(--color-error)] text-sm mb-4">
          <AlertCircle class="w-5 h-5 shrink-0 mt-0.5" :stroke-width="2" />
          <span>{{ error }}</span>
        </div>
        <div class="text-center">
          <button class="pim-btn" @click="router.push('/login')">Zum Login</button>
        </div>
      </div>

      <!-- Success: Show credentials -->
      <div v-else-if="credentials" class="pim-card p-6 space-y-4">
        <div class="p-3 rounded-md bg-[var(--color-success-light)] text-[var(--color-success)] text-sm text-center">
          Willkommen! Ihr Zugang wurde erfolgreich eingerichtet.
        </div>

        <p class="text-sm text-[var(--color-text-secondary)] text-center">
          Bitte notieren Sie sich Ihre Zugangsdaten für zukünftige Anmeldungen:
        </p>

        <div class="space-y-3">
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">E-Mail</label>
            <div class="flex items-center gap-2">
              <input type="text" readonly :value="credentials.email" class="pim-input flex-1 font-mono text-sm" @click="$event.target.select()" />
              <button class="pim-btn shrink-0" @click="copyText(credentials.email, 'email')">
                <Check v-if="copied.email" class="w-4 h-4 text-[var(--color-success)]" :stroke-width="2" />
                <Copy v-else class="w-4 h-4" :stroke-width="2" />
              </button>
            </div>
          </div>

          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Passwort</label>
            <div class="flex items-center gap-2">
              <input type="text" readonly :value="credentials.password" class="pim-input flex-1 font-mono text-sm" @click="$event.target.select()" />
              <button class="pim-btn shrink-0" @click="copyText(credentials.password, 'password')">
                <Check v-if="copied.password" class="w-4 h-4 text-[var(--color-success)]" :stroke-width="2" />
                <Copy v-else class="w-4 h-4" :stroke-width="2" />
              </button>
            </div>
          </div>
        </div>

        <button class="pim-btn pim-btn-primary w-full py-2.5 mt-2" @click="proceed">
          Weiter zur Anwendung
        </button>
      </div>
    </div>
  </div>
</template>
