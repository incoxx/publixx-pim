<script setup>
import { onMounted, ref } from 'vue'
import { Loader2, CheckCircle, XCircle } from 'lucide-vue-next'

const status = ref('processing') // 'processing' | 'success' | 'error'
const errorMsg = ref('')

onMounted(() => {
  const params = new URLSearchParams(window.location.search)
  const code = params.get('code')
  const error = params.get('error')

  if (error) {
    status.value = 'error'
    errorMsg.value = params.get('error_description') || error
    return
  }

  if (!code) {
    status.value = 'error'
    errorMsg.value = 'Kein Autorisierungscode erhalten.'
    return
  }

  // Code per postMessage ans Hauptfenster senden
  if (window.opener) {
    window.opener.postMessage({ code, state: params.get('state') }, window.location.origin)
    status.value = 'success'
    setTimeout(() => window.close(), 1500)
  } else {
    status.value = 'error'
    errorMsg.value = 'Kein Hauptfenster gefunden. Bitte schließe dieses Fenster und versuche es erneut.'
  }
})
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-[var(--color-bg)]">
    <div class="text-center space-y-4 p-8">
      <div v-if="status === 'processing'" class="space-y-3">
        <Loader2 class="w-8 h-8 animate-spin text-[var(--color-accent)] mx-auto" />
        <p class="text-sm text-[var(--color-text-secondary)]">Autorisierung wird verarbeitet...</p>
      </div>

      <div v-if="status === 'success'" class="space-y-3">
        <CheckCircle class="w-8 h-8 text-[var(--color-success)] mx-auto" />
        <p class="text-sm text-[var(--color-text-primary)] font-medium">Verbindung hergestellt!</p>
        <p class="text-xs text-[var(--color-text-tertiary)]">Dieses Fenster schließt sich automatisch...</p>
      </div>

      <div v-if="status === 'error'" class="space-y-3">
        <XCircle class="w-8 h-8 text-[var(--color-error)] mx-auto" />
        <p class="text-sm text-[var(--color-text-primary)] font-medium">Autorisierung fehlgeschlagen</p>
        <p class="text-xs text-[var(--color-text-tertiary)]">{{ errorMsg }}</p>
        <button class="pim-btn pim-btn-secondary text-xs mt-2" @click="window.close()">Fenster schließen</button>
      </div>
    </div>
  </div>
</template>
