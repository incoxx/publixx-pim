<script setup>
/**
 * Massenzuordnung von Tags — genutzt aus Profisuche und Merkliste.
 *
 * Der Modus ist bewusst eine sichtbare Entscheidung und nicht implizit:
 * bei einer Massenoperation über hunderte Produkte ist der Unterschied
 * zwischen "ergänzen" und "ersetzen" teuer, wenn man ihn erst hinterher merkt.
 */
import { ref, computed, watch } from 'vue'
import { X, Loader2, Check, Tag as TagIcon, AlertTriangle } from 'lucide-vue-next'
import { tags as tagsApi } from '@/api/tags'
import PimTagInput from '@/components/shared/PimTagInput.vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  productIds: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:open', 'assigned'])

const selectedTags = ref([])
const mode = ref('add')
const saving = ref(false)
const error = ref('')
const success = ref('')

const modes = [
  { value: 'add', label: 'Ergänzen', hint: 'Tags hinzufügen, bestehende bleiben erhalten' },
  { value: 'remove', label: 'Entfernen', hint: 'Nur die gewählten Tags entfernen' },
  { value: 'replace', label: 'Ersetzen', hint: 'Genau diese Tags setzen — alle anderen werden entfernt' },
]

const activeMode = computed(() => modes.find(m => m.value === mode.value))
const canSave = computed(() => selectedTags.value.length > 0 && props.productIds.length > 0 && !saving.value)

watch(() => props.open, (isOpen) => {
  if (!isOpen) return
  selectedTags.value = []
  mode.value = 'add'
  error.value = ''
  success.value = ''
})

async function save() {
  if (!canSave.value) return
  saving.value = true
  error.value = ''
  success.value = ''
  try {
    const { data } = await tagsApi.bulkAssignProducts(
      props.productIds,
      selectedTags.value.map(t => t.id),
      mode.value,
    )
    success.value = data.message || `${props.productIds.length} Produkt(e) aktualisiert.`
    emit('assigned')
    setTimeout(close, 1500)
  } catch (e) {
    error.value = e.response?.data?.message || 'Zuordnung fehlgeschlagen'
  } finally {
    saving.value = false
  }
}

function close() {
  emit('update:open', false)
}

defineExpose({ selectedTags, mode, save, canSave })
</script>

<template>
  <Teleport to="body">
    <transition name="fade">
      <div v-if="open" class="fixed inset-0 z-50 flex items-start justify-center pt-[15vh]" @keydown.escape="close">
        <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" @click="close" />

        <div class="relative z-10 w-full max-w-[500px] bg-[var(--color-surface)] rounded-xl shadow-xl border border-[var(--color-border)] overflow-hidden mx-4">
          <div class="flex items-center justify-between px-5 py-3.5 border-b border-[var(--color-border)]">
            <div>
              <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Tags zuordnen</h3>
              <p class="text-xs text-[var(--color-text-tertiary)] mt-0.5">
                {{ productIds.length }} Produkt{{ productIds.length !== 1 ? 'e' : '' }} ausgewählt
              </p>
            </div>
            <button class="p-1 rounded hover:bg-[var(--color-bg)] text-[var(--color-text-tertiary)] transition-colors" @click="close">
              <X class="w-4 h-4" :stroke-width="2" />
            </button>
          </div>

          <div class="px-5 py-4 space-y-4 max-h-[60vh] overflow-y-auto">
            <div v-if="error" class="text-xs text-[var(--color-error)]">{{ error }}</div>
            <div v-if="success" class="text-xs text-[var(--color-success)] flex items-center gap-1.5">
              <Check class="w-3.5 h-3.5" :stroke-width="2" /> {{ success }}
            </div>

            <div>
              <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1.5">Tags</label>
              <PimTagInput v-model="selectedTags" placeholder="Tag auswählen oder anlegen…" />
            </div>

            <div>
              <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1.5">Vorgehen</label>
              <div class="flex gap-1.5">
                <button
                  v-for="m in modes"
                  :key="m.value"
                  type="button"
                  class="flex-1 px-2 py-1.5 rounded-lg border text-xs transition-colors"
                  :class="mode === m.value
                    ? 'border-[var(--color-accent)] bg-[var(--color-accent)]/10 text-[var(--color-accent)] font-medium'
                    : 'border-[var(--color-border)] text-[var(--color-text-secondary)] hover:bg-[var(--color-bg)]'"
                  @click="mode = m.value"
                >
                  {{ m.label }}
                </button>
              </div>
              <p class="text-[11px] text-[var(--color-text-tertiary)] mt-1.5">{{ activeMode.hint }}</p>
            </div>

            <div
              v-if="mode === 'replace'"
              class="flex items-start gap-2 rounded-lg border border-[var(--color-warning,#f59e0b)]/40 bg-[var(--color-warning,#f59e0b)]/10 px-3 py-2"
            >
              <AlertTriangle class="w-3.5 h-3.5 shrink-0 mt-0.5 text-[var(--color-warning,#f59e0b)]" :stroke-width="2" />
              <p class="text-[11px] text-[var(--color-text-secondary)]">
                Alle bisher vergebenen Tags dieser {{ productIds.length }} Produkte werden entfernt und durch die Auswahl ersetzt.
              </p>
            </div>
          </div>

          <div class="flex items-center justify-end gap-2 px-5 py-3 border-t border-[var(--color-border)]">
            <button class="pim-btn pim-btn-secondary text-xs" @click="close">Abbrechen</button>
            <button class="pim-btn pim-btn-primary text-xs" :disabled="!canSave" @click="save">
              <Loader2 v-if="saving" class="w-3.5 h-3.5 animate-spin" />
              <TagIcon v-else class="w-3.5 h-3.5" :stroke-width="1.75" />
              {{ saving ? 'Speichern…' : 'Zuordnen' }}
            </button>
          </div>
        </div>
      </div>
    </transition>
  </Teleport>
</template>
