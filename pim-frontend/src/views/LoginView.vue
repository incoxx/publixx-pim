<script setup>
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const email = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)

async function handleLogin() {
  error.value = ''
  loading.value = true
  try {
    await authStore.login({ email: email.value, password: password.value })
    router.push(route.query.redirect || '/products')
  } catch (e) {
    error.value = e.response?.data?.title || 'Anmeldung fehlgeschlagen'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-[var(--color-bg)]">
    <div class="w-full max-w-sm">
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-[var(--color-primary)] mb-4">
          <span class="text-white font-bold text-xl">P</span>
        </div>
        <h1 class="text-lg font-semibold text-[var(--color-text-primary)]">Publixx PIM</h1>
        <p class="text-sm text-[var(--color-text-secondary)] mt-1">Product Information Management</p>
      </div>

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
    </div>
  </div>
</template>
