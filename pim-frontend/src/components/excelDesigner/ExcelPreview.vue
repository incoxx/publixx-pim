<script setup>
import { ref, computed } from 'vue'
import { X, Download } from 'lucide-vue-next'
import { useExcelDesignerStore } from '@/stores/excelDesigner'

const emit = defineEmits(['close'])
const store = useExcelDesignerStore()
const activeTab = ref(0)

const sheets = computed(() => store.previewData?.sheets || [])
const downloading = ref(false)

async function download() {
  downloading.value = true
  try {
    await store.downloadExcel()
  } finally {
    downloading.value = false
  }
}
</script>

<template>
  <div class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-6" @click.self="emit('close')">
    <div class="bg-[var(--color-surface)] rounded-xl shadow-2xl w-full max-w-5xl max-h-[85vh] flex flex-col">
      <!-- Header -->
      <div class="flex items-center justify-between px-5 py-3 border-b border-[var(--color-border)]">
        <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Excel-Vorschau</h3>
        <div class="flex items-center gap-2">
          <button class="pim-btn pim-btn-primary text-xs" @click="download" :disabled="downloading">
            <Download class="w-3.5 h-3.5" :stroke-width="2" />
            {{ downloading ? 'Erstelle...' : 'Download .xlsx' }}
          </button>
          <button class="p-1.5 text-[var(--color-text-tertiary)] hover:text-[var(--color-text-primary)]" @click="emit('close')">
            <X class="w-4 h-4" :stroke-width="2" />
          </button>
        </div>
      </div>

      <!-- Content -->
      <div class="flex-1 overflow-hidden flex flex-col">
        <div v-if="store.previewLoading" class="flex-1 flex items-center justify-center text-sm text-[var(--color-text-tertiary)]">
          Lade Vorschau...
        </div>

        <template v-else-if="sheets.length">
          <!-- Sheet Tabs -->
          <div class="flex border-b border-[var(--color-border)] px-4 pt-2 bg-[var(--color-bg)]">
            <button
              v-for="(sheet, idx) in sheets"
              :key="idx"
              class="px-3 py-1.5 text-xs rounded-t border border-b-0 -mb-px transition-colors"
              :class="activeTab === idx
                ? 'bg-[var(--color-surface)] border-[var(--color-border)] text-[var(--color-text-primary)] font-medium'
                : 'bg-transparent border-transparent text-[var(--color-text-tertiary)] hover:text-[var(--color-text-secondary)]'"
              @click="activeTab = idx"
            >
              {{ sheet.label }} ({{ sheet.count }})
            </button>
          </div>

          <!-- Table -->
          <div class="flex-1 overflow-auto p-4">
            <div v-if="sheets[activeTab]" class="border border-[var(--color-border)] rounded-lg overflow-hidden">
              <table class="w-full text-xs">
                <thead>
                  <tr class="bg-[#4472C4] text-white">
                    <th
                      v-for="col in sheets[activeTab].columns"
                      :key="col.id"
                      class="px-3 py-2 text-left font-medium whitespace-nowrap"
                    >
                      {{ col.label }}
                      <span class="text-blue-200 text-[10px] ml-1">({{ col.type }})</span>
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="(row, rowIdx) in sheets[activeTab].rows"
                    :key="rowIdx"
                    class="border-t border-[var(--color-border)]"
                    :class="rowIdx % 2 === 0 ? 'bg-[var(--color-surface)]' : 'bg-[var(--color-bg)]'"
                  >
                    <td
                      v-for="(cell, cellIdx) in row"
                      :key="cellIdx"
                      class="px-3 py-1.5 whitespace-nowrap"
                    >
                      <span v-if="cell === '[Thumbnail]'" class="text-[var(--color-text-tertiary)] italic">Bild</span>
                      <span v-else>{{ cell }}</span>
                    </td>
                  </tr>
                  <tr v-if="sheets[activeTab].rows.length === 0">
                    <td :colspan="sheets[activeTab].columns.length" class="px-3 py-4 text-center text-[var(--color-text-tertiary)]">
                      Keine Produkte gefunden
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <p class="text-[11px] text-[var(--color-text-tertiary)] mt-2">
              Vorschau zeigt max. 5 Produkte. Gesamtanzahl: {{ store.previewData?.total || 0 }}.
            </p>
          </div>
        </template>

        <div v-else class="flex-1 flex items-center justify-center text-sm text-[var(--color-text-tertiary)]">
          Keine Vorschaudaten verfügbar. Bitte zuerst Spalten hinzufügen.
        </div>
      </div>
    </div>
  </div>
</template>
