<script setup>
import { ref } from 'vue'
import { Download } from 'lucide-vue-next'
import exportsApi from '@/api/exports'

const pqlInput = ref('')
const exportResult = ref(null)
const exporting = ref(false)
const error = ref('')

async function runExport() {
  exporting.value = true; error.value = ''
  try {
    const { data } = await exportsApi.exportWithPql({ query: pqlInput.value || '*' })
    exportResult.value = data
  } catch (err) { error.value = err.response?.data?.title || 'Export fehlgeschlagen' }
  finally { exporting.value = false }
}
</script>

<template>
  <div class="space-y-6">
    <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Export</h2>
    <div class="pim-card p-6 space-y-4">
      <div>
        <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">PQL-Query (optional)</label>
        <textarea v-model="pqlInput" class="pim-input font-mono text-xs min-h-[80px]" placeholder='WHERE status = "active"' />
      </div>
      <button class="pim-btn pim-btn-primary" :disabled="exporting" @click="runExport">
        <Download class="w-4 h-4" :stroke-width="2" />
        {{ exporting ? 'Exportieren...' : 'Export starten' }}
      </button>
    </div>
    <div v-if="error" class="p-3 rounded-lg bg-[var(--color-error-light)] text-[var(--color-error)] text-sm">{{ error }}</div>
    <div v-if="exportResult" class="pim-card p-6">
      <h3 class="text-sm font-semibold mb-3">Ergebnis</h3>
      <pre class="font-mono text-xs bg-[var(--color-bg)] p-4 rounded-lg overflow-auto max-h-[400px]">{{ JSON.stringify(exportResult, null, 2) }}</pre>
    </div>
  </div>
</template>
