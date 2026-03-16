<script setup>
import { ref, computed } from 'vue'
import { useApiDesignerStore } from '@/stores/apiDesigner'
import { Copy, Check, Code, Database } from 'lucide-vue-next'

const store = useApiDesignerStore()
const copied = ref(false)
const showMode = ref('structure') // 'structure' | 'data'

const displayJson = computed(() => {
  if (showMode.value === 'data' && store.previewData) {
    return JSON.stringify(store.previewData, null, 2)
  }
  return store.jsonStructurePreview
})

function copyToClipboard() {
  navigator.clipboard.writeText(displayJson.value)
  copied.value = true
  setTimeout(() => { copied.value = false }, 2000)
}
</script>

<template>
  <div class="h-full flex flex-col">
    <!-- Header -->
    <div class="flex items-center justify-between px-3 py-2 border-b border-[var(--color-border)]">
      <span class="text-xs font-semibold text-[var(--color-text-primary)]">JSON-Vorschau</span>
      <div class="flex items-center gap-1.5">
        <!-- Toggle: Structure / Data -->
        <div class="flex rounded overflow-hidden border border-[var(--color-border)]">
          <button
            class="px-2 py-0.5 text-[10px] transition-colors"
            :class="showMode === 'structure' ? 'bg-[var(--color-accent)] text-white' : 'bg-[var(--color-surface)] text-[var(--color-text-secondary)] hover:bg-[var(--color-bg)]'"
            @click="showMode = 'structure'"
            title="Struktur-Vorschau"
          >
            <Code class="w-3 h-3 inline" :stroke-width="2" />
          </button>
          <button
            class="px-2 py-0.5 text-[10px] transition-colors"
            :class="showMode === 'data' ? 'bg-[var(--color-accent)] text-white' : 'bg-[var(--color-surface)] text-[var(--color-text-secondary)] hover:bg-[var(--color-bg)]'"
            @click="showMode = 'data'"
            title="Beispieldaten"
          >
            <Database class="w-3 h-3 inline" :stroke-width="2" />
          </button>
        </div>

        <button
          class="text-[var(--color-text-tertiary)] hover:text-[var(--color-text-primary)]"
          @click="copyToClipboard"
          :title="copied ? 'Kopiert!' : 'JSON kopieren'"
        >
          <Check v-if="copied" class="w-3.5 h-3.5 text-green-500" :stroke-width="2" />
          <Copy v-else class="w-3.5 h-3.5" :stroke-width="2" />
        </button>
      </div>
    </div>

    <!-- Info if data mode but no data -->
    <div v-if="showMode === 'data' && !store.previewData" class="px-3 py-4 text-center text-[11px] text-[var(--color-text-tertiary)]">
      Klicke "Vorschau" in der Toolbar, um Beispieldaten mit echten Produkten zu laden.
    </div>

    <!-- JSON Content -->
    <pre
      v-else
      class="flex-1 overflow-auto p-3 text-[11px] font-mono text-[var(--color-text-secondary)] leading-relaxed whitespace-pre"
    >{{ displayJson }}</pre>
  </div>
</template>
