<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { Plus, Pencil, Trash2, FileSpreadsheet, Upload, Copy, Download } from 'lucide-vue-next'
import excelTemplatesApi from '@/api/excelTemplates'
import { createEmptyTemplate } from '@/stores/excelDesigner'

const router = useRouter()

const templates = ref([])
const loading = ref(false)
const error = ref('')
const showCreate = ref(false)
const newName = ref('')
const showImport = ref(false)
const importFile = ref(null)
const importName = ref('')

onMounted(loadTemplates)

async function loadTemplates() {
  loading.value = true
  try {
    const { data } = await excelTemplatesApi.list()
    templates.value = data.data || data
  } catch (e) {
    error.value = 'Fehler beim Laden der Excel-Templates'
  } finally {
    loading.value = false
  }
}

async function createTemplate() {
  if (!newName.value.trim()) return
  try {
    const { data } = await excelTemplatesApi.create({
      name: newName.value.trim(),
      template_json: createEmptyTemplate(),
    })
    const tmpl = data.data || data
    router.push(`/excel-designer/${tmpl.id}`)
  } catch (e) {
    error.value = 'Fehler beim Erstellen'
  }
}

async function importExcel() {
  if (!importFile.value) return
  try {
    const { data } = await excelTemplatesApi.import(importFile.value, importName.value.trim() || null)
    const result = data.data || data
    if (result.id) {
      router.push(`/excel-designer/${result.id}`)
    } else {
      // Importierte Struktur ohne Speicherung — neues Template erstellen
      const { data: created } = await excelTemplatesApi.create({
        name: importName.value.trim() || importFile.value.name.replace(/\.[^.]+$/, ''),
        template_json: result,
      })
      const tmpl = created.data || created
      router.push(`/excel-designer/${tmpl.id}`)
    }
  } catch (e) {
    error.value = 'Fehler beim Importieren der Excel-Vorlage'
  }
}

function onFileSelect(e) {
  importFile.value = e.target.files[0] || null
  if (importFile.value && !importName.value) {
    importName.value = importFile.value.name.replace(/\.[^.]+$/, '')
  }
}

async function deleteTemplate(id) {
  if (!confirm('Excel-Template wirklich löschen?')) return
  try {
    await excelTemplatesApi.remove(id)
    templates.value = templates.value.filter(t => t.id !== id)
  } catch (e) {
    error.value = 'Fehler beim Löschen'
  }
}
</script>

<template>
  <div class="space-y-4 max-w-4xl mx-auto">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Sheet Designer</h2>
      <div class="flex gap-2">
        <button class="pim-btn pim-btn-secondary text-xs" @click="showImport = !showImport; showCreate = false">
          <Upload class="w-3.5 h-3.5" :stroke-width="2" />
          Excel-Vorlage importieren
        </button>
        <button class="pim-btn pim-btn-primary text-xs" @click="showCreate = !showCreate; showImport = false">
          <Plus class="w-3.5 h-3.5" :stroke-width="2" />
          Neues Template
        </button>
      </div>
    </div>

    <!-- Create form -->
    <div v-if="showCreate" class="pim-card p-4 flex items-end gap-3">
      <div class="flex-1">
        <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Template-Name</label>
        <input
          v-model="newName"
          class="pim-input text-xs w-full"
          placeholder="z.B. FABDIS Produktliste"
          @keyup.enter="createTemplate"
          autofocus
        />
      </div>
      <button class="pim-btn pim-btn-primary text-xs" @click="createTemplate" :disabled="!newName.trim()">
        Erstellen & Bearbeiten
      </button>
      <button class="pim-btn pim-btn-secondary text-xs" @click="showCreate = false; newName = ''">
        Abbrechen
      </button>
    </div>

    <!-- Import form -->
    <div v-if="showImport" class="pim-card p-4 space-y-3">
      <p class="text-xs text-[var(--color-text-secondary)]">
        Lade eine .xlsx-Datei hoch. Die Spaltenköpfe werden automatisch als Spalten übernommen.
        Du kannst sie anschließend auf PIM-Felder mappen.
      </p>
      <div class="flex items-end gap-3">
        <div class="flex-1">
          <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Excel-Datei</label>
          <input type="file" accept=".xlsx,.xls" class="pim-input text-xs w-full" @change="onFileSelect" />
        </div>
        <div class="flex-1">
          <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Template-Name</label>
          <input v-model="importName" class="pim-input text-xs w-full" placeholder="Optional" />
        </div>
        <button class="pim-btn pim-btn-primary text-xs" @click="importExcel" :disabled="!importFile">
          Importieren & Bearbeiten
        </button>
        <button class="pim-btn pim-btn-secondary text-xs" @click="showImport = false; importFile = null; importName = ''">
          Abbrechen
        </button>
      </div>
    </div>

    <!-- Error -->
    <div v-if="error" class="p-3 rounded-lg bg-[var(--color-error-light)] text-[var(--color-error)] text-xs">{{ error }}</div>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-8 text-[var(--color-text-tertiary)] text-sm">Lade Templates...</div>

    <!-- Empty state -->
    <div v-else-if="!templates.length" class="pim-card p-8 text-center">
      <FileSpreadsheet class="w-10 h-10 mx-auto mb-3 text-[var(--color-text-tertiary)]" :stroke-width="1.25" />
      <p class="text-sm text-[var(--color-text-secondary)]">Noch keine Excel-Templates vorhanden.</p>
      <p class="text-xs text-[var(--color-text-tertiary)] mt-1">Erstelle ein neues Template oder importiere eine Excel-Vorlage.</p>
    </div>

    <!-- Template list -->
    <div v-else class="space-y-2">
      <div
        v-for="tmpl in templates"
        :key="tmpl.id"
        class="pim-card p-4 flex items-center gap-4 hover:bg-[var(--color-bg)] transition-colors"
      >
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2">
            <FileSpreadsheet class="w-4 h-4 text-emerald-500 shrink-0" :stroke-width="1.75" />
            <span class="text-sm font-medium text-[var(--color-text-primary)] truncate">{{ tmpl.name }}</span>
            <span
              v-if="tmpl.is_shared"
              class="text-[10px] px-1.5 py-0.5 rounded bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300"
            >
              Geteilt
            </span>
          </div>
          <div v-if="tmpl.description" class="text-[11px] text-[var(--color-text-tertiary)] mt-0.5">
            {{ tmpl.description }}
          </div>
        </div>

        <div class="flex items-center gap-1.5 shrink-0">
          <button
            class="pim-btn pim-btn-secondary text-[11px] px-2 py-1"
            @click="router.push(`/excel-designer/${tmpl.id}`)"
            title="Bearbeiten"
          >
            <Pencil class="w-3 h-3" :stroke-width="2" />
          </button>
          <button
            class="pim-btn pim-btn-secondary text-[11px] px-2 py-1 text-[var(--color-error)]"
            @click="deleteTemplate(tmpl.id)"
            title="Löschen"
          >
            <Trash2 class="w-3 h-3" :stroke-width="2" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
