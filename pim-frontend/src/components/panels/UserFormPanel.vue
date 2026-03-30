<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import usersApi, { roles } from '@/api/users'
import pimSyncApi from '@/api/pimSync'
import PimForm from '@/components/shared/PimForm.vue'
import {
  Key, Plus, Trash2, Copy, Eye, EyeOff, Shield, Clock,
} from 'lucide-vue-next'

const props = defineProps({
  user: { type: Object, default: null },
  rolesList: { type: Array, default: () => [] },
})

const authStore = useAuthStore()
const loading = ref(false)
const errors = ref({})

const isEdit = computed(() => !!props.user)
const isAdmin = computed(() => authStore.user?.roles?.some(r => r.name === 'Admin'))

const formData = ref(
  props.user
    ? {
        name: props.user.name,
        email: props.user.email,
        password: '',
        role_ids: props.user.roles?.map(r => r.id) || [],
        language: props.user.language || 'de',
        is_active: props.user.is_active ?? true,
      }
    : {
        name: '',
        email: '',
        password: '',
        role_ids: [],
        language: 'de',
        is_active: true,
      }
)

const fields = computed(() => [
  { key: 'name', label: 'Name', type: 'text', required: true },
  { key: 'email', label: 'E-Mail', type: 'email', required: true },
  {
    key: 'password', label: 'Passwort', type: 'text', required: !isEdit.value,
    hint: isEdit.value ? 'Leer lassen um Passwort beizubehalten' : 'Mindestens 8 Zeichen',
  },
  {
    key: 'role_ids', label: 'Rolle', type: 'select',
    options: props.rolesList.map(r => ({ value: r.id, label: r.name })),
  },
  {
    key: 'language', label: 'Sprache', type: 'select',
    options: [
      { value: 'de', label: 'Deutsch' },
      { value: 'en', label: 'English' },
      { value: 'fr', label: 'Français' },
    ],
  },
  { key: 'is_active', label: 'Aktiv', type: 'boolean' },
])

async function handleSubmit(data) {
  loading.value = true
  errors.value = {}

  const payload = { ...data }
  if (isEdit.value && !payload.password) {
    delete payload.password
  }
  // Convert single role_ids value to array if needed
  if (payload.role_ids && !Array.isArray(payload.role_ids)) {
    payload.role_ids = [payload.role_ids]
  }

  try {
    if (isEdit.value) {
      await usersApi.update(props.user.id, payload)
    } else {
      await usersApi.create(payload)
    }
    authStore.closePanel()
    if (props.onSaved) props.onSaved()
  } catch (e) {
    if (e.response?.status === 422) {
      const serverErrors = e.response.data.errors || {}
      for (const [key, val] of Object.entries(serverErrors)) {
        errors.value[key] = Array.isArray(val) ? val[0] : val
      }
    }
  } finally {
    loading.value = false
  }
}

// =====================================================================
// API-Keys Sektion (nur im Edit-Modus, nur für Admins)
// =====================================================================

const apiKeys = ref([])
const apiKeysLoading = ref(false)
const showCreateKeyForm = ref(false)
const keyFormData = ref({ name: '', description: '' })
const creatingKey = ref(false)
const newKey = ref(null)
const showToken = ref(false)
const keyError = ref('')

async function loadApiKeys() {
  if (!isEdit.value || !isAdmin.value || !props.user?.id) return
  apiKeysLoading.value = true
  try {
    const res = await pimSyncApi.adminUserApiKeys(props.user.id)
    apiKeys.value = res.data.data || []
  } catch {
    // Silently fail — API-Keys Sektion ist optional
  } finally {
    apiKeysLoading.value = false
  }
}

async function createApiKey() {
  creatingKey.value = true
  keyError.value = ''
  try {
    const res = await pimSyncApi.adminCreateApiKey(props.user.id, {
      name: keyFormData.value.name,
      description: keyFormData.value.description || undefined,
    })
    newKey.value = res.data.data
    showCreateKeyForm.value = false
    keyFormData.value = { name: '', description: '' }
    await loadApiKeys()
  } catch (e) {
    keyError.value = e.response?.data?.message || 'Fehler beim Erstellen'
  } finally {
    creatingKey.value = false
  }
}

async function deleteApiKey(tokenId) {
  if (!confirm('API-Key wirklich widerrufen?')) return
  try {
    await pimSyncApi.adminDeleteApiKey(tokenId)
    apiKeys.value = apiKeys.value.filter(k => k.id !== tokenId)
  } catch {
    keyError.value = 'Fehler beim Löschen'
  }
}

async function copyToClipboard(text) {
  try { await navigator.clipboard.writeText(text) } catch { /* fallback */ }
}

onMounted(() => {
  if (isEdit.value && isAdmin.value) loadApiKeys()
})
</script>

<template>
  <div class="p-4">
    <h3 class="text-sm font-semibold text-[var(--color-text-primary)] mb-4">
      {{ isEdit ? 'Benutzer bearbeiten' : 'Neuer Benutzer' }}
    </h3>
    <PimForm
      :fields="fields"
      :modelValue="formData"
      :errors="errors"
      :loading="loading"
      @update:modelValue="formData = $event"
      @submit="handleSubmit"
      @cancel="authStore.closePanel()"
    />

    <!-- API-Keys Sektion (nur Edit-Modus, nur Admin) -->
    <template v-if="isEdit && isAdmin">
      <div class="mt-6 pt-4 border-t border-[var(--color-border)]">
        <div class="flex items-center justify-between mb-3">
          <div class="flex items-center gap-2">
            <Key class="w-4 h-4 text-[var(--color-accent)]" />
            <h4 class="text-xs font-semibold text-[var(--color-text-primary)]">API-Keys</h4>
            <span class="text-[10px] text-[var(--color-text-tertiary)]">({{ apiKeys.length }})</span>
          </div>
          <button
            class="pim-btn pim-btn-ghost text-[10px]"
            @click="showCreateKeyForm = !showCreateKeyForm"
          >
            <Plus class="w-3 h-3 mr-0.5" />Key erstellen
          </button>
        </div>

        <!-- Neuer Key erstellt (einmalig sichtbar) -->
        <div v-if="newKey" class="mb-3 p-3 rounded-lg border-2 border-[var(--color-warning,theme(colors.amber.500))] bg-[var(--color-bg)]">
          <div class="flex items-center gap-1 text-[var(--color-warning,theme(colors.amber.500))] mb-2">
            <Shield class="w-3.5 h-3.5" />
            <span class="text-[11px] font-bold">Nur jetzt sichtbar!</span>
          </div>
          <div class="flex items-center gap-1 font-mono text-[10px] bg-[var(--color-bg-elevated)] p-2 rounded">
            <span class="truncate">{{ showToken ? newKey.token : '••••••••••••••••' }}</span>
            <button class="p-0.5 shrink-0" @click="showToken = !showToken">
              <Eye v-if="!showToken" class="w-3 h-3" /><EyeOff v-else class="w-3 h-3" />
            </button>
            <button class="p-0.5 shrink-0" @click="copyToClipboard(newKey.token)">
              <Copy class="w-3 h-3" />
            </button>
          </div>
          <button class="text-[10px] text-[var(--color-accent)] mt-1" @click="newKey = null; showToken = false">Schließen</button>
        </div>

        <!-- Create Form -->
        <div v-if="showCreateKeyForm" class="mb-3 p-3 rounded-lg border border-[var(--color-accent)]/20 bg-[var(--color-bg)] space-y-2">
          <input v-model="keyFormData.name" type="text" class="pim-input text-xs w-full" placeholder="Key-Name (z.B. ERP Integration)" />
          <input v-model="keyFormData.description" type="text" class="pim-input text-xs w-full" placeholder="Beschreibung (optional)" />
          <div v-if="keyError" class="text-[10px] text-[var(--color-error)]">{{ keyError }}</div>
          <div class="flex gap-1 justify-end">
            <button class="pim-btn pim-btn-ghost text-[10px]" @click="showCreateKeyForm = false">Abbrechen</button>
            <button class="pim-btn pim-btn-primary text-[10px]" :disabled="creatingKey || !keyFormData.name" @click="createApiKey">Erstellen</button>
          </div>
        </div>

        <!-- Key List -->
        <div v-if="apiKeysLoading" class="text-center py-2">
          <div class="w-4 h-4 border-2 border-[var(--color-accent)] border-t-transparent rounded-full animate-spin inline-block"></div>
        </div>

        <div v-else-if="apiKeys.length === 0 && !showCreateKeyForm" class="text-[11px] text-[var(--color-text-tertiary)] py-2">
          Keine API-Keys für diesen Benutzer.
        </div>

        <div v-else class="space-y-1.5">
          <div
            v-for="key in apiKeys"
            :key="key.id"
            class="flex items-center justify-between p-2 rounded-lg bg-[var(--color-bg-elevated)] text-[11px]"
          >
            <div class="min-w-0">
              <span class="font-medium text-[var(--color-text-primary)]">{{ key.name }}</span>
              <div class="flex gap-3 text-[10px] text-[var(--color-text-tertiary)] mt-0.5">
                <span v-if="key.last_used_at" class="flex items-center gap-0.5">
                  <Clock class="w-2.5 h-2.5" />{{ new Date(key.last_used_at).toLocaleDateString('de') }}
                </span>
                <span>Erstellt: {{ new Date(key.created_at).toLocaleDateString('de') }}</span>
              </div>
            </div>
            <button class="p-1 rounded hover:bg-[var(--color-bg-hover)] text-[var(--color-error)] shrink-0" @click="deleteApiKey(key.id)">
              <Trash2 class="w-3 h-3" />
            </button>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
