<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { X, Search, Plus, Trash2 } from 'lucide-vue-next'
import { roles } from '@/api/users'

const props = defineProps({
  open: { type: Boolean, default: false },
  selectedRoleIds: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'updated'])

const saving = ref(false)
const error = ref(null)
const search = ref('')
const availablePermissions = ref({})
const loading = ref(false)

// Track add/remove state per permission: null = untouched, 'add', 'remove'
const permissionActions = ref({})

// Labels dynamisch vom Backend (via /permissions Endpoint)
const entityLabels = ref({})

const actionLabels = {
  'view': 'Lesen',
  'create': 'Erstellen',
  'edit': 'Bearbeiten',
  'delete': 'Löschen',
  'execute': 'Ausführen',
  'move': 'Verschieben',
  'mappings.edit': 'Mappings',
}

// Flat list of all permissions for the search view
const allPermissions = computed(() => {
  const list = []
  for (const [entity, actions] of Object.entries(availablePermissions.value)) {
    for (const action of actions) {
      const permName = `${entity}.${action}`
      const label = `${entityLabels.value[entity] || entity} — ${actionLabels[action] || action}`
      list.push({ name: permName, label, entity, action })
    }
  }
  return list.sort((a, b) => a.label.localeCompare(b.label, 'de'))
})

const filteredPermissions = computed(() => {
  if (!search.value.trim()) return allPermissions.value
  const q = search.value.toLowerCase()
  return allPermissions.value.filter(p =>
    p.label.toLowerCase().includes(q) || p.name.toLowerCase().includes(q)
  )
})

const hasChanges = computed(() =>
  Object.values(permissionActions.value).some(v => v !== null)
)

function togglePermission(permName) {
  const current = permissionActions.value[permName]
  if (current === 'add') {
    permissionActions.value[permName] = 'remove'
  } else if (current === 'remove') {
    permissionActions.value[permName] = null
  } else {
    permissionActions.value[permName] = 'add'
  }
}

function resetAll() {
  for (const key in permissionActions.value) {
    permissionActions.value[key] = null
  }
  search.value = ''
  error.value = null
}

async function apply() {
  saving.value = true
  error.value = null

  const addPerms = []
  const removePerms = []
  for (const [perm, action] of Object.entries(permissionActions.value)) {
    if (action === 'add') addPerms.push(perm)
    else if (action === 'remove') removePerms.push(perm)
  }

  try {
    await roles.bulkSyncPermissions({
      role_ids: props.selectedRoleIds,
      add_permissions: addPerms,
      remove_permissions: removePerms,
    })
    resetAll()
    emit('updated')
  } catch (e) {
    error.value = e.response?.data?.message || 'Fehler beim Speichern'
  } finally {
    saving.value = false
  }
}

function close() {
  resetAll()
  emit('close')
}

watch(() => props.open, async (isOpen) => {
  if (isOpen && Object.keys(availablePermissions.value).length === 0) {
    loading.value = true
    try {
      const { data } = await roles.listPermissions()
      availablePermissions.value = data.data || data
      if (data.labels) entityLabels.value = data.labels
      // Initialize action tracking
      for (const [entity, actions] of Object.entries(availablePermissions.value)) {
        for (const action of actions) {
          permissionActions.value[`${entity}.${action}`] = null
        }
      }
    } catch { /* ignore */ }
    finally { loading.value = false }
  }
})
</script>

<template>
  <teleport to="body">
    <transition name="fade">
      <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/40" @click="close" />
        <div class="relative bg-[var(--color-surface)] rounded-xl shadow-2xl border border-[var(--color-border)] w-full max-w-xl mx-4 max-h-[80vh] flex flex-col">
          <!-- Header -->
          <div class="flex items-center justify-between px-5 py-4 border-b border-[var(--color-border)] shrink-0">
            <div>
              <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Berechtigungen bearbeiten</h3>
              <p class="text-xs text-[var(--color-text-tertiary)] mt-0.5">{{ selectedRoleIds.length }} Rollen ausgewählt</p>
            </div>
            <button class="p-1 rounded hover:bg-[var(--color-bg)] transition-colors cursor-pointer" @click="close">
              <X class="w-4 h-4 text-[var(--color-text-tertiary)]" />
            </button>
          </div>

          <!-- Search -->
          <div class="px-5 py-3 border-b border-[var(--color-border)] shrink-0">
            <div class="relative">
              <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-[var(--color-text-tertiary)]" />
              <input
                v-model="search"
                type="text"
                class="pim-input w-full text-xs pim-input-icon"
                placeholder="Berechtigung suchen..."
              />
            </div>
            <p class="text-[11px] text-[var(--color-text-tertiary)] mt-2">
              Klicken: <span class="text-[var(--color-success)]">Hinzufügen</span> →
              <span class="text-[var(--color-error)]">Entfernen</span> →
              Zurücksetzen
            </p>
          </div>

          <!-- Permission list -->
          <div class="flex-1 overflow-y-auto px-5 py-3">
            <div v-if="loading" class="text-xs text-[var(--color-text-tertiary)] text-center py-8">
              Berechtigungen werden geladen...
            </div>
            <div v-else class="space-y-0.5">
              <button
                v-for="perm in filteredPermissions"
                :key="perm.name"
                class="w-full flex items-center gap-2 px-2 py-1.5 rounded text-xs transition-colors cursor-pointer"
                :class="{
                  'bg-[var(--color-success)]/10 text-[var(--color-success)]': permissionActions[perm.name] === 'add',
                  'bg-[var(--color-error)]/10 text-[var(--color-error)]': permissionActions[perm.name] === 'remove',
                  'text-[var(--color-text-secondary)] hover:bg-[var(--color-bg)]': !permissionActions[perm.name],
                }"
                @click="togglePermission(perm.name)"
              >
                <Plus v-if="permissionActions[perm.name] === 'add'" class="w-3.5 h-3.5 shrink-0" :stroke-width="2.5" />
                <Trash2 v-else-if="permissionActions[perm.name] === 'remove'" class="w-3.5 h-3.5 shrink-0" :stroke-width="2" />
                <span v-else class="w-3.5 h-3.5 shrink-0" />
                <span class="truncate">{{ perm.label }}</span>
                <span class="ml-auto text-[10px] text-[var(--color-text-tertiary)] font-mono">{{ perm.name }}</span>
              </button>
            </div>
          </div>

          <!-- Error -->
          <p v-if="error" class="px-5 text-xs text-[var(--color-error)]">{{ error }}</p>

          <!-- Footer -->
          <div class="flex items-center justify-end gap-2 px-5 py-3 border-t border-[var(--color-border)] shrink-0">
            <button class="pim-btn pim-btn-secondary text-xs" @click="close">Abbrechen</button>
            <button
              class="pim-btn pim-btn-primary text-xs"
              :disabled="!hasChanges || saving"
              @click="apply"
            >
              {{ saving ? 'Wird gespeichert...' : 'Anwenden' }}
            </button>
          </div>
        </div>
      </div>
    </transition>
  </teleport>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
