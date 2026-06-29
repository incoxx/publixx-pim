<script setup>
import { ref, computed } from 'vue'
import contentApi from '@/api/content'
import { ArrowRightLeft, Download, Upload, FileJson, CheckCircle2, AlertTriangle } from 'lucide-vue-next'

// Auswählbare Teilmengen der Konfiguration
const SECTIONS = [
  { key: 'content_types', label: 'Seitentypen' },
  { key: 'section_types', label: 'Sektionstypen' },
  { key: 'product_widgets', label: 'Produkt-Widgets' },
  { key: 'navigations', label: 'Navigation & Theme' },
]

// ─── Export ───────────────────────────────────────────────────────
const exportSel = ref(SECTIONS.map((s) => s.key)) // default: alles
const exporting = ref(false)
const exportError = ref('')

function toggle(list, key) {
  const i = list.value.indexOf(key)
  if (i >= 0) list.value.splice(i, 1)
  else list.value.push(key)
}

function triggerDownload(blob, filename) {
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  a.click()
  setTimeout(() => URL.revokeObjectURL(url), 200)
}

async function runExport() {
  exportError.value = ''
  exporting.value = true
  try {
    // leere Auswahl = alles
    const sel = exportSel.value.length === SECTIONS.length ? [] : exportSel.value
    const { data } = await contentApi.exportConfig(sel)
    const date = new Date().toISOString().slice(0, 10)
    triggerDownload(data, `anypim-content-config-${date}.json`)
  } catch (e) {
    exportError.value = e.response?.data?.message || 'Export fehlgeschlagen.'
  } finally {
    exporting.value = false
  }
}

// ─── Import ───────────────────────────────────────────────────────
const importText = ref('')
const importSel = ref(SECTIONS.map((s) => s.key))
const importing = ref(false)
const importError = ref('')
const importResult = ref(null)
const fileName = ref('')

const parsedBundle = computed(() => {
  if (!importText.value.trim()) return null
  try { return JSON.parse(importText.value) } catch { return undefined } // undefined = Parse-Fehler
})
const parseInvalid = computed(() => parsedBundle.value === undefined)

function onFile(e) {
  const file = e.target.files?.[0]
  if (!file) return
  fileName.value = file.name
  const reader = new FileReader()
  reader.onload = () => { importText.value = String(reader.result || '') }
  reader.readAsText(file)
}

async function runImport() {
  importError.value = ''
  importResult.value = null
  const bundle = parsedBundle.value
  if (!bundle || typeof bundle !== 'object') {
    importError.value = 'Bitte gültiges JSON einfügen oder eine Datei wählen.'
    return
  }
  importing.value = true
  try {
    const sel = importSel.value.length === SECTIONS.length ? [] : importSel.value
    const { data } = await contentApi.importConfig(bundle, sel)
    importResult.value = data.summary || {}
  } catch (e) {
    importError.value = e.response?.data?.message || 'Import fehlgeschlagen.'
  } finally {
    importing.value = false
  }
}

const summaryRows = computed(() => {
  if (!importResult.value) return []
  return Object.entries(importResult.value).map(([key, counts]) => ({
    label: SECTIONS.find((s) => s.key === key)?.label || key,
    counts,
  }))
})
</script>

<template>
  <div class="space-y-4 max-w-4xl" data-testid="content-config">
    <div class="flex items-center gap-2">
      <ArrowRightLeft class="w-5 h-5 text-[var(--color-text-tertiary)]" :stroke-width="2" />
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Content-Konfiguration · Export / Import</h2>
    </div>
    <p class="text-[12px] text-[var(--color-text-tertiary)] -mt-2 leading-relaxed">
      Die komplette Content-Konfiguration (Seitentypen, Sektionstypen, Produkt-Widgets, Navigation inkl. Theme)
      als JSON sichern oder einspielen. Der Import ist idempotent: Einträge werden anhand des technischen Namens
      aktualisiert, neue angelegt — nichts dupliziert. Ideal, um Layouts per KI/Claude entwerfen zu lassen und
      einfach zurückzuspielen.
    </p>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <!-- Export -->
      <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] p-4 space-y-3">
        <div class="flex items-center gap-2">
          <Download class="w-4 h-4 text-[var(--color-text-tertiary)]" :stroke-width="2" />
          <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Exportieren</h3>
        </div>
        <p class="text-[11px] text-[var(--color-text-tertiary)]">Wähle, welche Teile exportiert werden.</p>
        <div class="space-y-1.5">
          <label v-for="s in SECTIONS" :key="s.key" class="flex items-center gap-2 text-[12px] text-[var(--color-text-secondary)]">
            <input type="checkbox" class="pim-checkbox" :checked="exportSel.includes(s.key)" @change="toggle(exportSel, s.key)" />
            {{ s.label }}
          </label>
        </div>
        <p v-if="exportError" class="text-[12px] text-[var(--color-error)] bg-[var(--color-error-light)] px-3 py-2 rounded-lg">{{ exportError }}</p>
        <button class="pim-btn pim-btn-primary text-xs" :disabled="exporting || !exportSel.length" @click="runExport">
          <Download class="w-3.5 h-3.5" :stroke-width="2" /> {{ exporting ? 'Exportiere…' : 'JSON herunterladen' }}
        </button>
      </div>

      <!-- Import -->
      <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] p-4 space-y-3">
        <div class="flex items-center gap-2">
          <Upload class="w-4 h-4 text-[var(--color-text-tertiary)]" :stroke-width="2" />
          <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Importieren</h3>
        </div>

        <label class="pim-btn pim-btn-secondary text-xs cursor-pointer w-fit">
          <FileJson class="w-3.5 h-3.5" :stroke-width="2" /> Datei wählen…
          <input type="file" accept="application/json,.json" class="hidden" @change="onFile" />
        </label>
        <span v-if="fileName" class="text-[11px] text-[var(--color-text-tertiary)] ml-2">{{ fileName }}</span>

        <textarea
          v-model="importText"
          rows="6"
          class="pim-input text-[11px] font-mono w-full"
          placeholder='…oder JSON hier einfügen: { "anypim_content_config": "1.0", … }'
        />
        <p v-if="parseInvalid" class="flex items-center gap-1 text-[11px] text-[var(--color-error)]">
          <AlertTriangle class="w-3 h-3" :stroke-width="2" /> JSON ist nicht gültig.
        </p>

        <div>
          <p class="text-[11px] text-[var(--color-text-tertiary)] mb-1">Nur diese Teile importieren:</p>
          <div class="flex flex-wrap gap-x-4 gap-y-1.5">
            <label v-for="s in SECTIONS" :key="s.key" class="flex items-center gap-2 text-[12px] text-[var(--color-text-secondary)]">
              <input type="checkbox" class="pim-checkbox" :checked="importSel.includes(s.key)" @change="toggle(importSel, s.key)" />
              {{ s.label }}
            </label>
          </div>
        </div>

        <p v-if="importError" class="text-[12px] text-[var(--color-error)] bg-[var(--color-error-light)] px-3 py-2 rounded-lg">{{ importError }}</p>

        <button class="pim-btn pim-btn-primary text-xs" :disabled="importing || parseInvalid || !importText.trim() || !importSel.length" @click="runImport">
          <Upload class="w-3.5 h-3.5" :stroke-width="2" /> {{ importing ? 'Importiere…' : 'Importieren' }}
        </button>

        <!-- Ergebnis -->
        <div v-if="summaryRows.length" class="rounded-lg border border-emerald-200 bg-emerald-50 dark:bg-emerald-950/20 dark:border-emerald-900 p-3 space-y-1">
          <p class="flex items-center gap-1 text-[12px] font-medium text-emerald-700 dark:text-emerald-400">
            <CheckCircle2 class="w-3.5 h-3.5" :stroke-width="2" /> Import abgeschlossen
          </p>
          <ul class="text-[11px] text-[var(--color-text-secondary)] space-y-0.5">
            <li v-for="row in summaryRows" :key="row.label">
              {{ row.label }}:
              <span class="font-medium">{{ row.counts.created || 0 }} neu</span>,
              <span class="font-medium">{{ row.counts.updated || 0 }} aktualisiert</span>
              <template v-if="row.counts.nodes != null">, {{ row.counts.nodes }} Knoten</template>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>
