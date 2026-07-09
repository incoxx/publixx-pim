<script setup>
import { ref, computed, watch } from 'vue'
import { X, Star, Loader2, Check } from 'lucide-vue-next'
import watchlistApi from '@/api/watchlist'
import { collectionItems as collectionItemsApi } from '@/api/collections'

const props = defineProps({
  open: { type: Boolean, default: false },
  collectionId: { type: String, default: null },
  existingProductIds: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:open', 'added'])

const items = ref([])
const loading = ref(false)
const submitting = ref(false)
const error = ref('')
const selected = ref(new Set())

// Bereits in der Collection enthaltene Produkte lassen sich nicht nochmal auswaehlen --
// CollectionItemController::bulkStore() ueberspringt sie ohnehin serverseitig, hier aber
// schon vorab sichtbar machen statt eine stille No-Op-Aktion anzubieten.
const selectableItems = computed(() => items.value.filter(i => i.product && !props.existingProductIds.includes(i.product.id)))
const alreadyAddedCount = computed(() => items.value.length - selectableItems.value.length)

watch(() => props.open, async (isOpen) => {
  if (isOpen) {
    error.value = ''
    selected.value = new Set()
    await loadWatchlist()
  }
})

async function loadWatchlist() {
  loading.value = true
  try {
    const { data } = await watchlistApi.list()
    items.value = data.data || data
  } catch {
    error.value = 'Merkliste konnte nicht geladen werden'
  } finally {
    loading.value = false
  }
}

function toggle(productId) {
  const next = new Set(selected.value)
  if (next.has(productId)) next.delete(productId)
  else next.add(productId)
  selected.value = next
}

function toggleAll() {
  if (selected.value.size === selectableItems.value.length) {
    selected.value = new Set()
  } else {
    selected.value = new Set(selectableItems.value.map(i => i.product.id))
  }
}

async function submit() {
  if (selected.value.size === 0 || !props.collectionId) return
  submitting.value = true
  error.value = ''
  try {
    const { data } = await collectionItemsApi.bulkAdd(props.collectionId, [...selected.value])
    emit('added', data)
    close()
  } catch (e) {
    error.value = e.response?.data?.message || 'Übertragung fehlgeschlagen'
  } finally {
    submitting.value = false
  }
}

function close() {
  emit('update:open', false)
}
</script>

<template>
  <Teleport to="body">
    <transition name="fade">
      <div v-if="open" class="fixed inset-0 z-50 flex items-start justify-center pt-[15vh]" @keydown.escape="close">
        <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" @click="close" />

        <div class="relative z-10 w-full max-w-[480px] bg-[var(--color-surface)] rounded-xl shadow-xl border border-[var(--color-border)] overflow-hidden mx-4">
          <div class="flex items-center justify-between px-5 py-3.5 border-b border-[var(--color-border)]">
            <div>
              <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Aus Merkliste übernehmen</h3>
              <p class="text-xs text-[var(--color-text-tertiary)] mt-0.5">{{ selected.size }} von {{ selectableItems.length }} ausgewählt</p>
            </div>
            <button class="p-1 rounded hover:bg-[var(--color-bg)] text-[var(--color-text-tertiary)] transition-colors" @click="close">
              <X class="w-4 h-4" :stroke-width="2" />
            </button>
          </div>

          <div class="px-5 py-4 max-h-[50vh] overflow-y-auto">
            <div v-if="error" class="text-xs text-[var(--color-error)] mb-3">{{ error }}</div>

            <div v-if="loading" class="flex items-center justify-center py-8 text-[var(--color-text-tertiary)]">
              <Loader2 class="w-5 h-5 animate-spin" />
            </div>
            <div v-else-if="!items.length" class="text-sm text-[var(--color-text-tertiary)] text-center py-8">
              Die Merkliste ist leer.
            </div>
            <template v-else>
              <button class="pim-btn pim-btn-ghost text-xs mb-2" @click="toggleAll" :disabled="!selectableItems.length">
                {{ selected.size === selectableItems.length && selectableItems.length ? 'Auswahl aufheben' : 'Alle auswählen' }}
              </button>
              <p v-if="alreadyAddedCount" class="text-[11px] text-[var(--color-text-tertiary)] mb-2">
                {{ alreadyAddedCount }} Produkt{{ alreadyAddedCount !== 1 ? 'e' : '' }} bereits in dieser Collection — nicht wählbar.
              </p>
              <div class="space-y-1">
                <button
                  v-for="wi in items"
                  :key="wi.id"
                  class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-left transition-colors"
                  :class="[
                    !wi.product || existingProductIds.includes(wi.product?.id) ? 'opacity-40 cursor-not-allowed' : 'hover:bg-[var(--color-bg)]',
                    wi.product && selected.has(wi.product.id) ? 'bg-[var(--color-accent-light)] border border-[var(--color-accent)]/30' : 'border border-transparent',
                  ]"
                  :disabled="!wi.product || existingProductIds.includes(wi.product?.id)"
                  @click="wi.product && toggle(wi.product.id)"
                >
                  <Star class="w-3.5 h-3.5 text-[var(--color-warning)] shrink-0" :stroke-width="1.75" fill="currentColor" />
                  <div class="flex-1 min-w-0">
                    <div class="text-xs font-medium text-[var(--color-text-primary)] truncate">{{ wi.product?.name || '—' }}</div>
                    <div class="text-[10px] text-[var(--color-text-tertiary)] font-mono">{{ wi.product?.sku }}</div>
                  </div>
                  <Check v-if="wi.product && selected.has(wi.product.id)" class="w-4 h-4 text-[var(--color-accent)] shrink-0" :stroke-width="2" />
                </button>
              </div>
            </template>
          </div>

          <div class="flex items-center justify-end gap-2 px-5 py-3 border-t border-[var(--color-border)]">
            <button class="pim-btn pim-btn-secondary text-xs" @click="close">Abbrechen</button>
            <button
              class="pim-btn pim-btn-primary text-xs"
              data-testid="add-from-watchlist-submit"
              :disabled="selected.size === 0 || submitting"
              @click="submit"
            >
              <Loader2 v-if="submitting" class="w-3.5 h-3.5 animate-spin" />
              <Star v-else class="w-3.5 h-3.5" :stroke-width="1.75" />
              {{ submitting ? 'Übernehme...' : 'Übernehmen' }}
            </button>
          </div>
        </div>
      </div>
    </transition>
  </Teleport>
</template>
