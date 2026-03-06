<script setup>
import { ref, watch } from 'vue'
import { X, FileText, FileSpreadsheet, Download, Loader2 } from 'lucide-vue-next'
import reportTemplatesApi from '@/api/reportTemplates'

const props = defineProps({
  open: { type: Boolean, default: false },
  productIds: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:open'])

const templates = ref([])
const loading = ref(false)
const generating = ref(null)
const error = ref('')

watch(() => props.open, async (isOpen) => {
  if (isOpen) {
    error.value = ''
    generating.value = null
    await loadTemplates()
  }
})

async function loadTemplates() {
  loading.value = true
  try {
    const { data } = await reportTemplatesApi.list()
    templates.value = data.data || data
  } catch (e) {
    error.value = 'Report-Templates konnten nicht geladen werden'
  } finally {
    loading.value = false
  }
}

async function generate(template) {
  generating.value = template.id
  error.value = ''
  try {
    const format = template.format || 'pdf'
    const response = await reportTemplatesApi.execute(template.id, {
      product_ids: props.productIds,
      format,
    })
    const ext = format === 'docx' ? 'docx' : 'pdf'
    const mimeType = format === 'docx'
      ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
      : 'application/pdf'
    const blob = new Blob([response.data], { type: mimeType })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `${template.name || 'report'}.${ext}`
    a.click()
    setTimeout(() => URL.revokeObjectURL(url), 200)
    close()
  } catch (e) {
    error.value = 'Report-Generierung fehlgeschlagen'
  } finally {
    generating.value = null
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
        <div class="relative w-full max-w-[500px] bg-[var(--color-surface)] rounded-xl shadow-xl border border-[var(--color-border)] overflow-hidden mx-4">
          <!-- Header -->
          <div class="flex items-center justify-between px-5 py-3.5 border-b border-[var(--color-border)]">
            <div>
              <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Report generieren</h3>
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

            <div v-if="loading" class="flex items-center justify-center py-8 text-[var(--color-text-tertiary)]">
              <Loader2 class="w-5 h-5 animate-spin" />
            </div>

            <div v-else-if="templates.length === 0" class="text-sm text-[var(--color-text-tertiary)] text-center py-8">
              Keine Report-Templates vorhanden.
            </div>

            <div v-else class="space-y-1.5">
              <button
                v-for="t in templates"
                :key="t.id"
                class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-left hover:bg-[var(--color-bg)] transition-colors group"
                :disabled="generating !== null"
                @click="generate(t)"
              >
                <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0"
                  :class="t.format === 'docx' ? 'bg-blue-50 text-blue-600' : 'bg-red-50 text-red-600'"
                >
                  <FileSpreadsheet v-if="t.format === 'docx'" class="w-4 h-4" :stroke-width="1.75" />
                  <FileText v-else class="w-4 h-4" :stroke-width="1.75" />
                </div>
                <div class="flex-1 min-w-0">
                  <div class="text-sm font-medium text-[var(--color-text-primary)] truncate">{{ t.name }}</div>
                  <div class="text-[11px] text-[var(--color-text-tertiary)] uppercase">{{ t.format || 'pdf' }}</div>
                </div>
                <Loader2 v-if="generating === t.id" class="w-4 h-4 animate-spin text-[var(--color-accent)]" />
                <Download v-else class="w-4 h-4 text-[var(--color-text-tertiary)] opacity-0 group-hover:opacity-100 transition-opacity" :stroke-width="1.75" />
              </button>
            </div>
          </div>

          <!-- Footer -->
          <div class="flex items-center justify-end px-5 py-3 border-t border-[var(--color-border)]">
            <button class="pim-btn pim-btn-secondary text-xs" @click="close">Abbrechen</button>
          </div>
        </div>
      </div>
    </transition>
  </Teleport>
</template>
