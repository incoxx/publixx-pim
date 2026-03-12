<script setup>
import { ref, watch } from 'vue'
import { X, FileText, Download } from 'lucide-vue-next'
import pdfTemplatesApi from '@/api/pdfTemplates'
import watchlistApi from '@/api/watchlist'
import { triggerDownload } from '@/utils/download'

const props = defineProps({
  open: { type: Boolean, default: false },
  productIds: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:open'])

const templates = ref([])
const loading = ref(false)
const selectedTemplateId = ref(null)
const mode = ref('combined')
const format = ref('pdf')
const exporting = ref(false)
const error = ref('')

watch(() => props.open, async (val) => {
  if (val && templates.value.length === 0) {
    await loadTemplates()
  }
})

async function loadTemplates() {
  loading.value = true
  try {
    const { data } = await pdfTemplatesApi.list()
    templates.value = data.data || data
    if (templates.value.length > 0 && !selectedTemplateId.value) {
      selectedTemplateId.value = templates.value[0].id
    }
  } catch (e) {
    error.value = 'Fehler beim Laden der Vorlagen'
  } finally {
    loading.value = false
  }
}

async function doExport() {
  if (!selectedTemplateId.value) return
  exporting.value = true
  error.value = ''
  try {
    let response
    if (props.productIds?.length > 0) {
      response = await pdfTemplatesApi.execute(selectedTemplateId.value, {
        product_ids: props.productIds,
        mode: mode.value,
      }, format.value)
    } else {
      response = await watchlistApi.exportPdfTemplate(selectedTemplateId.value, mode.value)
    }
    const ext = mode.value === 'zip' ? 'zip' : format.value
    const tmpl = templates.value.find(t => t.id === selectedTemplateId.value)
    triggerDownload(response.data, `${tmpl?.name || 'export'}.${ext}`)
    close()
  } catch (e) {
    error.value = 'Export fehlgeschlagen'
  } finally {
    exporting.value = false
  }
}

function close() {
  emit('update:open', false)
}
</script>

<template>
  <Teleport to="body">
    <transition name="fade">
      <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" @click="close" />
        <div class="relative w-full max-w-lg bg-[var(--color-surface)] border border-[var(--color-border)] rounded-xl shadow-xl mx-4 overflow-hidden">
          <!-- Header -->
          <div class="flex items-center justify-between px-5 py-3 border-b border-[var(--color-border)]">
            <div class="flex items-center gap-2">
              <FileText class="w-4 h-4 text-[var(--color-accent)]" :stroke-width="1.75" />
              <span class="text-sm font-semibold text-[var(--color-text-primary)]">PDF-Vorlage wählen</span>
            </div>
            <button class="p-1.5 rounded hover:bg-[var(--color-bg)]" @click="close">
              <X class="w-4 h-4" :stroke-width="2" />
            </button>
          </div>

          <!-- Content -->
          <div class="p-5 space-y-4">
            <div v-if="error" class="p-2 rounded bg-[var(--color-error-light)] text-[var(--color-error)] text-xs">{{ error }}</div>

            <div v-if="loading" class="text-center py-4 text-sm text-[var(--color-text-tertiary)]">Lade Vorlagen...</div>

            <div v-else-if="templates.length === 0" class="text-center py-4">
              <p class="text-sm text-[var(--color-text-tertiary)]">Keine PDF-Vorlagen vorhanden.</p>
              <p class="text-xs text-[var(--color-text-tertiary)] mt-1">Erstelle zuerst eine Vorlage unter PDF-Vorlagen.</p>
            </div>

            <template v-else>
              <!-- Template selection -->
              <div>
                <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Vorlage</label>
                <select v-model="selectedTemplateId" class="pim-input text-xs w-full">
                  <option v-for="t in templates" :key="t.id" :value="t.id">
                    {{ t.name }}
                    <template v-if="t.description"> — {{ t.description }}</template>
                  </option>
                </select>
              </div>

              <!-- Format selection -->
              <div>
                <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Format</label>
                <div class="flex gap-2">
                  <button
                    class="flex-1 px-3 py-2 rounded text-xs font-medium border transition-colors"
                    :class="format === 'pdf'
                      ? 'bg-[var(--color-accent)] text-white border-[var(--color-accent)]'
                      : 'bg-[var(--color-surface)] text-[var(--color-text-secondary)] border-[var(--color-border)] hover:border-[var(--color-accent)]'"
                    @click="format = 'pdf'"
                  >
                    PDF
                  </button>
                  <button
                    class="flex-1 px-3 py-2 rounded text-xs font-medium border transition-colors"
                    :class="format === 'docx'
                      ? 'bg-[var(--color-accent)] text-white border-[var(--color-accent)]'
                      : 'bg-[var(--color-surface)] text-[var(--color-text-secondary)] border-[var(--color-border)] hover:border-[var(--color-accent)]'"
                    @click="format = 'docx'"
                  >
                    DOCX (Word)
                  </button>
                </div>
              </div>

              <!-- Mode selection -->
              <div>
                <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Ausgabemodus</label>
                <div class="flex gap-2">
                  <button
                    class="flex-1 px-3 py-2 rounded text-xs font-medium border transition-colors"
                    :class="mode === 'combined'
                      ? 'bg-[var(--color-accent)] text-white border-[var(--color-accent)]'
                      : 'bg-[var(--color-surface)] text-[var(--color-text-secondary)] border-[var(--color-border)] hover:border-[var(--color-accent)]'"
                    @click="mode = 'combined'"
                  >
                    {{ format === 'pdf' ? 'Ein PDF (alle Seiten)' : 'Ein Dokument (alle Seiten)' }}
                  </button>
                  <button
                    class="flex-1 px-3 py-2 rounded text-xs font-medium border transition-colors"
                    :class="mode === 'zip'
                      ? 'bg-[var(--color-accent)] text-white border-[var(--color-accent)]'
                      : 'bg-[var(--color-surface)] text-[var(--color-text-secondary)] border-[var(--color-border)] hover:border-[var(--color-accent)]'"
                    @click="mode = 'zip'"
                  >
                    Einzelne Dateien (ZIP)
                  </button>
                </div>
              </div>
            </template>
          </div>

          <!-- Footer -->
          <div v-if="templates.length > 0" class="flex items-center justify-end gap-2 px-5 py-3 border-t border-[var(--color-border)]">
            <button class="pim-btn pim-btn-secondary text-xs" @click="close">Abbrechen</button>
            <button
              class="pim-btn pim-btn-primary text-xs"
              :disabled="!selectedTemplateId || exporting"
              @click="doExport"
            >
              <Download class="w-3.5 h-3.5" :stroke-width="2" />
              {{ exporting ? 'Exportiere...' : 'Exportieren' }}
            </button>
          </div>
        </div>
      </div>
    </transition>
  </Teleport>
</template>
