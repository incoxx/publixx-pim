<script setup>
import { ref, onMounted } from 'vue'
import { useCatalogStore } from '@/stores/catalog'
import { Lock, Loader2, AlertTriangle } from 'lucide-vue-next'

const props = defineProps({
  token: { type: String, required: true },
})
const emit = defineEmits(['unlocked'])

const store = useCatalogStore()

// checking | password | unlocking | expired | notfound | error
const status = ref('checking')
const collectionName = ref(null)
const password = ref('')
const passwordError = ref(false)

onMounted(async () => {
  try {
    const info = await store.fetchShareInfo(props.token)
    collectionName.value = info.collection_name || null
    if (info.expired) {
      status.value = 'expired'
    } else if (info.requires_password) {
      status.value = 'password'
    } else {
      // Passwortloser Link — direkt entsperren.
      await doUnlock('')
    }
  } catch (e) {
    const code = e.response?.status
    status.value = code === 410 ? 'expired' : code === 404 ? 'notfound' : 'error'
  }
})

async function doUnlock(pw) {
  status.value = 'unlocking'
  passwordError.value = false
  try {
    await store.unlockShare(props.token, pw)
    emit('unlocked')
  } catch (e) {
    const code = e.response?.status
    if (code === 422) {
      passwordError.value = true
      status.value = 'password'
    } else if (code === 410) {
      status.value = 'expired'
    } else if (code === 404) {
      status.value = 'notfound'
    } else {
      status.value = 'error'
    }
  }
}

function submitPassword() {
  if (!password.value.trim()) return
  doUnlock(password.value)
}
</script>

<template>
  <div class="fixed inset-0 z-[100] flex items-center justify-center bg-base-200 p-4">
    <div class="w-full max-w-sm rounded-2xl bg-base-100 shadow-xl border border-base-300 p-6 text-center">
      <!-- Prüfen / Entsperren -->
      <template v-if="status === 'checking' || status === 'unlocking'">
        <Loader2 class="w-8 h-8 mx-auto text-primary animate-spin" :stroke-width="1.75" />
        <p class="mt-4 text-sm text-base-content/70">
          {{ status === 'unlocking' ? 'Katalog wird geöffnet…' : 'Freigabe wird geprüft…' }}
        </p>
      </template>

      <!-- Passwort-Eingabe -->
      <template v-else-if="status === 'password'">
        <div class="w-12 h-12 mx-auto rounded-full bg-primary/10 flex items-center justify-center">
          <Lock class="w-6 h-6 text-primary" :stroke-width="1.75" />
        </div>
        <h2 class="mt-4 text-lg font-semibold text-base-content">Geschützter Katalog</h2>
        <p v-if="collectionName" class="mt-1 text-sm text-base-content/60">{{ collectionName }}</p>
        <p class="mt-1 text-xs text-base-content/50">Bitte gib das Passwort ein, das du erhalten hast.</p>

        <form class="mt-4 space-y-3" @submit.prevent="submitPassword">
          <input
            v-model="password"
            type="password"
            autofocus
            class="input input-bordered w-full"
            placeholder="Passwort"
            data-testid="catalog-share-password"
          />
          <p v-if="passwordError" class="text-xs text-error text-left">
            Falsches Passwort. Bitte erneut versuchen.
          </p>
          <button
            type="submit"
            class="btn btn-primary w-full"
            :disabled="!password.trim()"
            data-testid="catalog-share-submit"
          >
            Katalog öffnen
          </button>
        </form>
      </template>

      <!-- Abgelaufen -->
      <template v-else-if="status === 'expired'">
        <div class="w-12 h-12 mx-auto rounded-full bg-warning/10 flex items-center justify-center">
          <AlertTriangle class="w-6 h-6 text-warning" :stroke-width="1.75" />
        </div>
        <h2 class="mt-4 text-lg font-semibold text-base-content">Link abgelaufen</h2>
        <p class="mt-1 text-sm text-base-content/60">
          Dieser Freigabelink ist nicht mehr gültig. Bitte fordere einen neuen an.
        </p>
      </template>

      <!-- Nicht gefunden -->
      <template v-else-if="status === 'notfound'">
        <div class="w-12 h-12 mx-auto rounded-full bg-error/10 flex items-center justify-center">
          <AlertTriangle class="w-6 h-6 text-error" :stroke-width="1.75" />
        </div>
        <h2 class="mt-4 text-lg font-semibold text-base-content">Link ungültig</h2>
        <p class="mt-1 text-sm text-base-content/60">
          Der Freigabelink wurde nicht gefunden oder wurde widerrufen.
        </p>
      </template>

      <!-- Fehler -->
      <template v-else>
        <div class="w-12 h-12 mx-auto rounded-full bg-error/10 flex items-center justify-center">
          <AlertTriangle class="w-6 h-6 text-error" :stroke-width="1.75" />
        </div>
        <h2 class="mt-4 text-lg font-semibold text-base-content">Etwas ist schiefgelaufen</h2>
        <p class="mt-1 text-sm text-base-content/60">
          Der Katalog konnte nicht geöffnet werden. Bitte versuche es später erneut.
        </p>
      </template>
    </div>
  </div>
</template>
