<script setup>
import { ref, computed, watch } from 'vue'
import { X, Search, FolderOpen, Loader2, Check, Plus } from 'lucide-vue-next'
import { collections as collectionsApi, collectionTypes as collectionTypesApi, collectionItems as collectionItemsApi } from '@/api/collections'

const props = defineProps({
  open: { type: Boolean, default: false },
  productIds: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:open', 'added'])

const collectionsList = ref([])
const collectionTypesList = ref([])
const loading = ref(false)
const submitting = ref(false)
const error = ref('')
const success = ref('')
const searchQuery = ref('')
const selectedCollectionId = ref(null)

// "+ Neue Collection" -- Merkliste-Produkte in eine frisch angelegte Collection statt
// eine bestehende uebertragen, ohne den Dialog vorher verlassen zu muessen.
const creatingNew = ref(false)
const newName = ref('')
const newCollectionTypeId = ref(null)

const filteredCollections = computed(() => {
  if (!searchQuery.value) return collectionsList.value
  const q = searchQuery.value.toLowerCase()
  return collectionsList.value.filter(c => c.name.toLowerCase().includes(q))
})

watch(() => props.open, async (isOpen) => {
  if (isOpen) {
    error.value = ''
    success.value = ''
    selectedCollectionId.value = null
    searchQuery.value = ''
    creatingNew.value = false
    newName.value = ''
    newCollectionTypeId.value = null
    await loadData()
  }
})

async function loadData() {
  loading.value = true
  try {
    const [{ data: collData }, { data: typeData }] = await Promise.all([
      collectionsApi.list({ perPage: 100, sort: 'created_at', order: 'desc' }),
      collectionTypesApi.list({ perPage: 100 }),
    ])
    collectionsList.value = collData.data || collData
    collectionTypesList.value = typeData.data || typeData
    if (collectionTypesList.value.length === 1) {
      newCollectionTypeId.value = collectionTypesList.value[0].id
    }
  } catch {
    error.value = 'Collections konnten nicht geladen werden'
  } finally {
    loading.value = false
  }
}

async function submit() {
  if (props.productIds.length === 0) return
  submitting.value = true
  error.value = ''
  success.value = ''
  try {
    let targetId = selectedCollectionId.value

    if (creatingNew.value) {
      if (!newName.value.trim() || !newCollectionTypeId.value) return
      const { data } = await collectionsApi.create({
        collection_type_id: newCollectionTypeId.value,
        name: newName.value.trim(),
      })
      targetId = (data.data || data).id
    }

    if (!targetId) return

    const { data } = await collectionItemsApi.bulkAdd(targetId, props.productIds)
    success.value = data.message || `${props.productIds.length} Produkt(e) hinzugefügt.`
    emit('added')
    setTimeout(() => close(), 1500)
  } catch (e) {
    error.value = e.response?.data?.message || 'Übertragung fehlgeschlagen'
  } finally {
    submitting.value = false
  }
}

const canSubmit = computed(() => {
  if (creatingNew.value) return !!newName.value.trim() && !!newCollectionTypeId.value
  return !!selectedCollectionId.value
})

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
        <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" @click="close" />

        <div class="relative z-10 w-full max-w-[500px] bg-[var(--color-surface)] rounded-xl shadow-xl border border-[var(--color-border)] overflow-hidden mx-4">
          <div class="flex items-center justify-between px-5 py-3.5 border-b border-[var(--color-border)]">
            <div>
              <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">In Collection übertragen</h3>
              <p class="text-xs text-[var(--color-text-tertiary)] mt-0.5">{{ productIds.length }} Produkt{{ productIds.length !== 1 ? 'e' : '' }} ausgewählt</p>
            </div>
            <button class="p-1 rounded hover:bg-[var(--color-bg)] text-[var(--color-text-tertiary)] transition-colors" @click="close">
              <X class="w-4 h-4" :stroke-width="2" />
            </button>
          </div>

          <div class="px-5 py-4 max-h-[50vh] overflow-y-auto">
            <div v-if="error" class="text-xs text-[var(--color-error)] mb-3">{{ error }}</div>
            <div v-if="success" class="text-xs text-[var(--color-success)] mb-3 flex items-center gap-1.5">
              <Check class="w-3.5 h-3.5" :stroke-width="2" />
              {{ success }}
            </div>

            <div class="flex items-center gap-2 mb-3">
              <button
                class="pim-btn text-xs flex-1"
                :class="!creatingNew ? 'pim-btn-secondary bg-[var(--color-accent-light)] text-[var(--color-accent)]' : 'pim-btn-ghost'"
                @click="creatingNew = false"
              >
                Bestehende Collection
              </button>
              <button
                class="pim-btn text-xs flex-1"
                :class="creatingNew ? 'pim-btn-secondary bg-[var(--color-accent-light)] text-[var(--color-accent)]' : 'pim-btn-ghost'"
                @click="creatingNew = true"
              >
                <Plus class="w-3.5 h-3.5" :stroke-width="2" />
                Neue Collection
              </button>
            </div>

            <!-- Neue Collection anlegen -->
            <div v-if="creatingNew" class="space-y-3">
              <div>
                <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Name</label>
                <input v-model="newName" type="text" class="pim-input text-xs w-full" placeholder="z.B. Angebot Musterkunde" />
              </div>
              <div>
                <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Collection-Typ</label>
                <select v-model="newCollectionTypeId" class="pim-input text-xs w-full">
                  <option :value="null" disabled>Bitte wählen...</option>
                  <option v-for="t in collectionTypesList" :key="t.id" :value="t.id">{{ t.name_de }}</option>
                </select>
              </div>
            </div>

            <!-- Bestehende Collection auswaehlen -->
            <div v-else>
              <div class="relative mb-3">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
                <input v-model="searchQuery" type="text" class="pim-input text-xs w-full pim-input-icon" placeholder="Collection suchen..." />
              </div>

              <div v-if="loading" class="flex items-center justify-center py-8 text-[var(--color-text-tertiary)]">
                <Loader2 class="w-5 h-5 animate-spin" />
              </div>
              <div v-else-if="filteredCollections.length === 0" class="text-sm text-[var(--color-text-tertiary)] text-center py-8">
                Keine Collections gefunden.
              </div>
              <div v-else class="space-y-1">
                <button
                  v-for="c in filteredCollections"
                  :key="c.id"
                  class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-left transition-colors"
                  :class="selectedCollectionId === c.id
                    ? 'bg-[var(--color-accent-light)] border border-[var(--color-accent)]/30'
                    : 'hover:bg-[var(--color-bg)] border border-transparent'"
                  @click="selectedCollectionId = c.id"
                >
                  <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 bg-blue-50 text-blue-600">
                    <FolderOpen class="w-4 h-4" :stroke-width="1.75" />
                  </div>
                  <div class="flex-1 min-w-0">
                    <div class="text-sm font-medium text-[var(--color-text-primary)] truncate">{{ c.name }}</div>
                    <div class="text-[11px] text-[var(--color-text-tertiary)]">
                      {{ c.collection_type?.name_de }}
                      <span v-if="c.items_count != null"> &middot; {{ c.items_count }} Positionen</span>
                    </div>
                  </div>
                  <Check v-if="selectedCollectionId === c.id" class="w-4 h-4 text-[var(--color-accent)] shrink-0" :stroke-width="2" />
                </button>
              </div>
            </div>
          </div>

          <div class="flex items-center justify-end gap-2 px-5 py-3 border-t border-[var(--color-border)]">
            <button class="pim-btn pim-btn-secondary text-xs" @click="close">Abbrechen</button>
            <button
              class="pim-btn pim-btn-primary text-xs"
              data-testid="bulk-add-collection-submit"
              :disabled="!canSubmit || submitting"
              @click="submit"
            >
              <Loader2 v-if="submitting" class="w-3.5 h-3.5 animate-spin" />
              <FolderOpen v-else class="w-3.5 h-3.5" :stroke-width="1.75" />
              {{ submitting ? 'Übertrage...' : 'Übertragen' }}
            </button>
          </div>
        </div>
      </div>
    </transition>
  </Teleport>
</template>
