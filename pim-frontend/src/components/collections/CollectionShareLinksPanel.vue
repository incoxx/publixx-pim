<script setup>
import { ref, onMounted } from 'vue'
import { collectionShareLinks as shareLinksApi } from '@/api/collections'
import { useToastStore } from '@/stores/toast'
import { Link2, Copy, Trash2, Loader2, Plus, X, LayoutGrid, KeyRound, Check } from 'lucide-vue-next'

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

// Einmalige Anzeige des Passworts direkt nach dem Erstellen (danach nicht mehr
// rekonstruierbar, da bcrypt-gehasht). { catalog_url, password }.
const justCreated = ref(null)

// Inline-Passwortänderung für bestehende Links.
const editingId = ref(null)
const editPassword = ref('')
const savingPassword = ref(false)

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
  creating.value = true
  try {
    // Passwort optional: leer lassen = Zugang nur über den (nicht erratbaren) Link.
    const password = newPassword.value.trim() || null
    const { data } = await shareLinksApi.create(props.collectionId, {
      password,
      expiresAt: newExpiresAt.value || null,
    })
    const link = data.data
    // Passwort nur jetzt anzeigbar — Nutzer soll es notieren/kopieren.
    justCreated.value = password ? { catalog_url: link.catalog_url, password } : null
    newPassword.value = ''
    newExpiresAt.value = ''
    showCreate.value = false
    await load()
    toastStore.showToast(
      password ? 'Freigabe-Link erstellt — Passwort jetzt notieren' : 'Freigabe-Link erstellt (ohne Passwort)',
      'success',
    )
  } catch (e) {
    toastStore.showToast(e.response?.data?.message || 'Link konnte nicht erstellt werden', 'error')
  } finally {
    creating.value = false
  }
}

function startEditPassword(link) {
  editingId.value = link.id
  editPassword.value = ''
}

function cancelEditPassword() {
  editingId.value = null
  editPassword.value = ''
}

async function savePassword(link) {
  savingPassword.value = true
  try {
    const password = editPassword.value.trim() || null
    await shareLinksApi.updatePassword(props.collectionId, link.id, password)
    // Neu gesetztes Passwort einmalig anzeigen.
    justCreated.value = password ? { catalog_url: link.catalog_url, password } : null
    cancelEditPassword()
    await load()
    toastStore.showToast(password ? 'Passwort geändert — jetzt notieren' : 'Passwort entfernt', 'success')
  } catch (e) {
    toastStore.showToast(e.response?.data?.message || 'Passwort konnte nicht geändert werden', 'error')
  } finally {
    savingPassword.value = false
  }
}

async function revokeLink(link) {
  await shareLinksApi.delete(props.collectionId, link.id)
  links.value = links.value.filter((l) => l.id !== link.id)
  toastStore.showToast('Link widerrufen', 'success')
}

async function copyText(text, label) {
  await navigator.clipboard.writeText(text)
  toastStore.showToast(`${label} kopiert`, 'success')
}

defineExpose({ load })

onMounted(load)
</script>

<template>
  <div class="pim-card p-4 space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <h4 class="text-xs font-semibold text-[var(--color-text-secondary)] uppercase tracking-wide">
        <Link2 class="inline w-3.5 h-3.5 -mt-0.5 mr-1" :stroke-width="1.75" />
        Freigabe-Link
      </h4>
      <button class="pim-btn pim-btn-ghost text-xs" @click="showCreate = !showCreate">
        <Plus class="w-3.5 h-3.5" :stroke-width="2" />
        Neuer Link
      </button>
    </div>

    <!-- Einmalige Passwort-Anzeige nach Erstellen/Ändern -->
    <div
      v-if="justCreated"
      class="rounded-lg border border-[var(--color-success)] bg-[var(--color-success)]/10 p-3 space-y-2"
      data-testid="share-link-password-reveal"
    >
      <div class="flex items-start justify-between gap-2">
        <p class="text-[11px] font-medium text-[var(--color-text-primary)]">
          Passwort (nur jetzt sichtbar — bitte notieren):
        </p>
        <button class="pim-btn pim-btn-ghost !p-1" title="Ausblenden" @click="justCreated = null">
          <X class="w-3.5 h-3.5" :stroke-width="2" />
        </button>
      </div>
      <div class="flex items-center gap-2">
        <code class="flex-1 min-w-0 truncate rounded bg-[var(--color-surface)] px-2 py-1 text-xs font-mono">{{ justCreated.password }}</code>
        <button class="pim-btn pim-btn-ghost !p-1.5" title="Passwort kopieren" @click="copyText(justCreated.password, 'Passwort')">
          <Copy class="w-3.5 h-3.5" :stroke-width="1.75" />
        </button>
      </div>
      <button class="pim-btn pim-btn-secondary text-xs w-full" @click="copyText(justCreated.catalog_url, 'Katalog-Link')">
        <LayoutGrid class="w-3.5 h-3.5" :stroke-width="1.75" /> Katalog-Link kopieren
      </button>
    </div>

    <div v-if="showCreate" class="border border-[var(--color-border)] rounded-lg p-3 space-y-2">
      <div>
        <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Passwort (optional)</label>
        <input
          v-model="newPassword"
          type="text"
          class="pim-input text-xs w-full"
          placeholder="Leer lassen = kein Passwort"
          data-testid="share-link-password"
        />
        <p class="text-[10px] text-[var(--color-text-tertiary)] mt-1">
          Wenn gesetzt (min. 4 Zeichen): nicht automatisch versendet — bitte dem Kunden separat
          mitteilen (Telefon/E-Mail). Ohne Passwort genügt der Link selbst.
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
          :disabled="creating"
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
        class="rounded-lg border border-[var(--color-border)] text-xs"
      >
        <div class="flex flex-wrap items-center gap-2 px-3 py-2">
          <div class="min-w-0 flex-1">
            <p class="font-mono text-[11px] truncate">{{ link.catalog_url }}</p>
            <p class="text-[10px] text-[var(--color-text-tertiary)]">
              <span v-if="link.is_expired" class="text-[var(--color-error)]">Abgelaufen</span>
              <span v-else-if="link.expires_at">Gültig bis {{ new Date(link.expires_at).toLocaleDateString('de-DE') }}</span>
              <span v-else>Kein Ablaufdatum</span>
              · {{ link.view_count }} Aufruf{{ link.view_count !== 1 ? 'e' : '' }}
              · <span v-if="link.has_password">🔒 Passwort</span><span v-else>🌐 ohne Passwort</span>
            </p>
          </div>
          <div class="flex items-center gap-1 shrink-0">
            <button class="pim-btn pim-btn-ghost !p-1.5" title="Katalog-Link kopieren (interaktiver Online-Katalog)" @click="copyText(link.catalog_url, 'Katalog-Link')">
              <LayoutGrid class="w-3.5 h-3.5" :stroke-width="1.75" />
            </button>
            <button class="pim-btn pim-btn-ghost !p-1.5" title="Dokument-Link kopieren (statische PDF/HTML-Ansicht)" @click="copyText(link.url, 'Dokument-Link')">
              <Copy class="w-3.5 h-3.5" :stroke-width="1.75" />
            </button>
            <button class="pim-btn pim-btn-ghost !p-1.5" title="Passwort ändern" @click="startEditPassword(link)">
              <KeyRound class="w-3.5 h-3.5" :stroke-width="1.75" />
            </button>
            <button class="pim-btn pim-btn-ghost !p-1.5 hover:text-[var(--color-error)]" title="Widerrufen" @click="revokeLink(link)">
              <Trash2 class="w-3.5 h-3.5" :stroke-width="1.75" />
            </button>
          </div>
        </div>

        <!-- Inline: Passwort ändern -->
        <div v-if="editingId === link.id" class="border-t border-[var(--color-border)] px-3 py-2 flex flex-wrap items-center gap-2">
          <input
            v-model="editPassword"
            type="text"
            class="pim-input text-xs flex-1 min-w-[8rem]"
            placeholder="Neues Passwort (leer = kein Passwort)"
            data-testid="share-link-edit-password"
            @keyup.enter="savePassword(link)"
          />
          <button class="pim-btn pim-btn-primary !p-1.5" :disabled="savingPassword" title="Speichern" @click="savePassword(link)">
            <Loader2 v-if="savingPassword" class="w-3.5 h-3.5 animate-spin" />
            <Check v-else class="w-3.5 h-3.5" :stroke-width="2" />
          </button>
          <button class="pim-btn pim-btn-ghost !p-1.5" title="Abbrechen" @click="cancelEditPassword">
            <X class="w-3.5 h-3.5" :stroke-width="2" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
