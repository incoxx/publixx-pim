<script setup>
import { ref } from 'vue'
import { Upload, FileSpreadsheet, Download } from 'lucide-vue-next'
import importsApi from '@/api/imports'

const file = ref(null)
const importJob = ref(null)
const uploading = ref(false)
const error = ref('')

async function handleUpload(e) {
  const f = e.target.files?.[0]
  if (!f) return
  file.value = f
  uploading.value = true
  error.value = ''
  try {
    const { data } = await importsApi.upload(f)
    importJob.value = data.data || data
  } catch (err) {
    error.value = err.response?.data?.title || 'Upload fehlgeschlagen'
  } finally { uploading.value = false }
}

async function executeImport() {
  if (!importJob.value) return
  try { await importsApi.execute(importJob.value.id); importJob.value.status = 'executing' }
  catch (err) { error.value = err.response?.data?.title || 'Import fehlgeschlagen' }
}

async function downloadTemplate(type) {
  try {
    const { data } = await importsApi.downloadTemplate(type)
    const url = URL.createObjectURL(data)
    const a = document.createElement('a')
    a.href = url
    a.download = `import-template-${type}.xlsx`
    a.click()
    URL.revokeObjectURL(url)
  } catch (err) {
    error.value = err.response?.data?.title || 'Download fehlgeschlagen'
  }
}
</script>

<template>
  <div class="space-y-6">
    <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Import</h2>
    <div class="pim-card p-8">
      <div class="border-2 border-dashed border-[var(--color-border)] rounded-xl p-12 text-center hover:border-[var(--color-accent)] transition-colors">
        <Upload class="w-8 h-8 mx-auto mb-3 text-[var(--color-text-tertiary)]" :stroke-width="1.5" />
        <p class="text-sm text-[var(--color-text-secondary)] mb-2">Excel-Datei hier ablegen oder klicken</p>
        <input type="file" accept=".xlsx,.xls,.csv" class="hidden" id="import-file" @change="handleUpload" />
        <label for="import-file" class="pim-btn pim-btn-secondary text-xs cursor-pointer">{{ uploading ? 'Hochladen...' : 'Datei auswaehlen' }}</label>
      </div>
    </div>
    <div v-if="error" class="p-3 rounded-lg bg-[var(--color-error-light)] text-[var(--color-error)] text-sm">{{ error }}</div>
    <div v-if="importJob" class="pim-card p-6 space-y-4">
      <div class="flex items-center gap-3">
        <FileSpreadsheet class="w-5 h-5 text-[var(--color-accent)]" :stroke-width="1.75" />
        <div><p class="text-sm font-medium">{{ file?.name }}</p><p class="text-xs text-[var(--color-text-tertiary)]">Status: {{ importJob.status }}</p></div>
      </div>
      <button v-if="importJob.status === 'validated'" class="pim-btn pim-btn-primary" @click="executeImport">Import ausfuehren</button>
    </div>
    <div class="pim-card p-6">
      <h3 class="text-sm font-semibold text-[var(--color-text-primary)] mb-3">Vorlagen herunterladen</h3>
      <div class="flex gap-2">
        <button class="pim-btn pim-btn-secondary text-xs" @click="downloadTemplate('products')"><Download class="w-3.5 h-3.5" :stroke-width="1.75" /> Produkte</button>
        <button class="pim-btn pim-btn-secondary text-xs" @click="downloadTemplate('attributes')"><Download class="w-3.5 h-3.5" :stroke-width="1.75" /> Attribute</button>
      </div>
    </div>
  </div>
</template>
