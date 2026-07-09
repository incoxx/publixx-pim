<script setup>
import { ref, onMounted } from 'vue'
import { collectionShareLinks as shareLinksApi } from '@/api/collections'
import { useToastStore } from '@/stores/toast'
import { Link2, Copy, Trash2, Loader2, Plus, X } from 'lucide-vue-next'

const props = defineProps({
  collectionId: { type: String, required: true },
})

const toastStore = useToastStore()

const links = ref([])
const loading = ref(false)
const showCreate = ref(false)
const creating = ref(false)
const newPassword = ref('')
const newExpiresAt = ref('')

async function load() {
  loading.value = true
  try {
    const { data } = await shareLinksApi.list(props.collectionId)
    links.value = data.data
  } finally {
    loading.value = false
  }
}

async function createLink() {
  if (!newPassword.value.trim()) return
  creating.value = true
  try {
    await shareLinksApi.create(props.collectionId, {
      password: newPassword.value.trim(),
      expiresAt: newExpiresAt.value || null,
    })
    newPassword.value = ''
    newExpiresAt.value = ''
    showCreate.value = false
    await load()
    toastStore.showToast('Freigabe-Link erstellt — Passwort dem Kunden separat mitteilen', 'success')
  } catch (e) {
    toastStore.showToast(e.response?.data?.message || 'Link konnte nicht erstellt werden', 'error')
  } finally {
    creating.value = false
  }
}

async function revokeLink(link) {
  await shareLinksApi.delete(props.collectionId, link.id)
  links.value = links.value.filter((l) => l.id !== link.id)
  toastStore.showToast('Link widerrufen', 'success')
}

async function copyUrl(link) {
  await navigator.clipboard.writeText(link.url)
  toastStore.showToast('Link kopiert', 'success')
}

defineExpose({ load })

onMounted(load)
</script>

<template>
  <div class="pim-card p-4 space-y-3">
    <div class="flex items-center justify-between">
      <h4 class="text-xs font-semibold text-[var(--color-text-secondary)] uppercase tracking-wide">
        <Link2 class="inline w-3.5 h-3.5 -mt-0.5 mr-1" :stroke-width="1.75" />
        Freigabe-Link
      </h4>
      <button class="pim-btn pim-btn-ghost text-xs" @click="showCreate = !showCreate">
        <Plus class="w-3.5 h-3.5" :stroke-width="2" />
        Neuer Link
      </button>
    </div>

    <div v-if="showCreate" class="border border-[var(--color-border)] rounded-lg p-3 space-y-2">
      <div>
        <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Passwort</label>
        <input
          v-model="newPassword"
          type="text"
          class="pim-input text-xs w-full"
          placeholder="z.B. Musterkunde2026"
          data-testid="share-link-password"
        />
        <p class="text-[10px] text-[var(--color-text-tertiary)] mt-1">
          Wird nicht automatisch versendet — bitte dem Kunden separat mitteilen (Telefon/E-Mail).
        </p>
      </div>
      <div>
        <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Gültig bis (optional)</label>
        <input v-model="newExpiresAt" type="date" class="pim-input text-xs w-full" />
      </div>
      <div class="flex items-center gap-2">
        <button
          class="pim-btn pim-btn-primary text-xs"
          data-testid="share-link-submit"
          :disabled="creating || !newPassword.trim()"
          @click="createLink"
        >
          <Loader2 v-if="creating" class="w-3.5 h-3.5 animate-spin" />
          {{ creating ? 'Erstelle...' : 'Link erstellen' }}
        </button>
        <button class="pim-btn pim-btn-ghost text-xs" @click="showCreate = false">
          <X class="w-3.5 h-3.5" :stroke-width="2" />
        </button>
      </div>
    </div>

    <div v-if="loading" class="text-xs text-[var(--color-text-tertiary)]">Lade...</div>
    <p v-else-if="!links.length" class="text-xs text-[var(--color-text-tertiary)]">
      Noch kein Freigabe-Link erstellt.
    </p>
    <div v-else class="space-y-2">
      <div
        v-for="link in links"
        :key="link.id"
        class="flex items-center justify-between gap-2 px-3 py-2 rounded-lg border border-[var(--color-border)] text-xs"
      >
        <div class="min-w-0 flex-1">
          <p class="font-mono text-[11px] truncate">{{ link.url }}</p>
          <p class="text-[10px] text-[var(--color-text-tertiary)]">
            <span v-if="link.is_expired" class="text-[var(--color-error)]">Abgelaufen</span>
            <span v-else-if="link.expires_at">Gültig bis {{ new Date(link.expires_at).toLocaleDateString('de-DE') }}</span>
            <span v-else>Kein Ablaufdatum</span>
            · {{ link.view_count }} Aufruf{{ link.view_count !== 1 ? 'e' : '' }}
          </p>
        </div>
        <button class="pim-btn pim-btn-ghost !p-1.5" title="Link kopieren" @click="copyUrl(link)">
          <Copy class="w-3.5 h-3.5" :stroke-width="1.75" />
        </button>
        <button class="pim-btn pim-btn-ghost !p-1.5 hover:text-[var(--color-error)]" title="Widerrufen" @click="revokeLink(link)">
          <Trash2 class="w-3.5 h-3.5" :stroke-width="1.75" />
        </button>
      </div>
    </div>
  </div>
</template>
