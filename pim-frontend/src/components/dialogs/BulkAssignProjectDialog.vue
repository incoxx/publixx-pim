<script setup>
import { ref, computed, watch } from 'vue'
import { X, Search, FolderTree, Loader2, Check } from 'lucide-vue-next'
import projectsApi from '@/api/projects'

const props = defineProps({
  open: { type: Boolean, default: false },
  productIds: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:open', 'assigned'])

const projects = ref([])
const loading = ref(false)
const assigning = ref(false)
const error = ref('')
const success = ref('')
const searchQuery = ref('')
const selectedProjectId = ref(null)

const filteredProjects = computed(() => {
  if (!searchQuery.value) return projects.value
  const q = searchQuery.value.toLowerCase()
  return projects.value.filter(p => p.name.toLowerCase().includes(q))
})

watch(() => props.open, async (isOpen) => {
  if (isOpen) {
    error.value = ''
    success.value = ''
    selectedProjectId.value = null
    searchQuery.value = ''
    await loadProjects()
  }
})

async function loadProjects() {
  loading.value = true
  try {
    const { data } = await projectsApi.list({ filter: { status: 'active' }, perPage: 100 })
    const active = data.data || data
    const { data: data2 } = await projectsApi.list({ filter: { status: 'planning' }, perPage: 100 })
    const planning = data2.data || data2
    projects.value = [...active, ...planning]
  } catch {
    error.value = 'Projekte konnten nicht geladen werden'
  } finally {
    loading.value = false
  }
}

async function assign() {
  if (!selectedProjectId.value || props.productIds.length === 0) return
  assigning.value = true
  error.value = ''
  success.value = ''
  try {
    const { data } = await projectsApi.bulkAddProducts(selectedProjectId.value, props.productIds)
    success.value = data.message || `${props.productIds.length} Produkt(e) zugeordnet.`
    emit('assigned')
    setTimeout(() => close(), 1500)
  } catch {
    error.value = 'Zuordnung fehlgeschlagen'
  } finally {
    assigning.value = false
  }
}

function close() {
  emit('update:open', false)
}
</script>

<template>
  <Teleport to="body">
    <transition name="fade">
      <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-start justify-center pt-[15vh]"
        @keydown.escape="close"
      >
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" @click="close" />

        <!-- Panel -->
        <div class="relative z-10 w-full max-w-[500px] bg-[var(--color-surface)] rounded-xl shadow-xl border border-[var(--color-border)] overflow-hidden mx-4">
          <!-- Header -->
          <div class="flex items-center justify-between px-5 py-3.5 border-b border-[var(--color-border)]">
            <div>
              <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Projekt zuordnen</h3>
              <p class="text-xs text-[var(--color-text-tertiary)] mt-0.5">{{ productIds.length }} Produkt{{ productIds.length !== 1 ? 'e' : '' }} ausgewählt</p>
            </div>
            <button
              class="p-1 rounded hover:bg-[var(--color-bg)] text-[var(--color-text-tertiary)] transition-colors"
              @click="close"
            >
              <X class="w-4 h-4" :stroke-width="2" />
            </button>
          </div>

          <!-- Body -->
          <div class="px-5 py-4 max-h-[50vh] overflow-y-auto">
            <div v-if="error" class="text-xs text-[var(--color-error)] mb-3">{{ error }}</div>
            <div v-if="success" class="text-xs text-[var(--color-success)] mb-3 flex items-center gap-1.5">
              <Check class="w-3.5 h-3.5" :stroke-width="2" />
              {{ success }}
            </div>

            <!-- Search -->
            <div class="relative mb-3">
              <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
              <input
                v-model="searchQuery"
                type="text"
                class="pim-input text-xs w-full pl-8"
                placeholder="Projekt suchen..."
              />
            </div>

            <div v-if="loading" class="flex items-center justify-center py-8 text-[var(--color-text-tertiary)]">
              <Loader2 class="w-5 h-5 animate-spin" />
            </div>

            <div v-else-if="filteredProjects.length === 0" class="text-sm text-[var(--color-text-tertiary)] text-center py-8">
              Keine Projekte gefunden.
            </div>

            <div v-else class="space-y-1">
              <button
                v-for="p in filteredProjects"
                :key="p.id"
                class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-left transition-colors"
                :class="selectedProjectId === p.id
                  ? 'bg-[var(--color-accent-light)] border border-[var(--color-accent)]/30'
                  : 'hover:bg-[var(--color-bg)] border border-transparent'"
                @click="selectedProjectId = p.id"
              >
                <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0"
                  :class="p.status === 'active' ? 'bg-emerald-50 text-emerald-600' : 'bg-blue-50 text-blue-600'"
                >
                  <FolderTree class="w-4 h-4" :stroke-width="1.75" />
                </div>
                <div class="flex-1 min-w-0">
                  <div class="text-sm font-medium text-[var(--color-text-primary)] truncate">{{ p.name }}</div>
                  <div class="text-[11px] text-[var(--color-text-tertiary)]">
                    {{ p.status === 'active' ? 'Aktiv' : 'Planung' }}
                    <span v-if="p.products_count != null"> &middot; {{ p.products_count }} Produkte</span>
                  </div>
                </div>
                <Check v-if="selectedProjectId === p.id" class="w-4 h-4 text-[var(--color-accent)] shrink-0" :stroke-width="2" />
              </button>
            </div>
          </div>

          <!-- Footer -->
          <div class="flex items-center justify-end gap-2 px-5 py-3 border-t border-[var(--color-border)]">
            <button class="pim-btn pim-btn-secondary text-xs" @click="close">Abbrechen</button>
            <button
              class="pim-btn pim-btn-primary text-xs"
              :disabled="!selectedProjectId || assigning"
              @click="assign"
            >
              <Loader2 v-if="assigning" class="w-3.5 h-3.5 animate-spin" />
              <FolderTree v-else class="w-3.5 h-3.5" :stroke-width="1.75" />
              {{ assigning ? 'Zuordnen...' : 'Zuordnen' }}
            </button>
          </div>
        </div>
      </div>
    </transition>
  </Teleport>
</template>
