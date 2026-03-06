<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import {
  Plus, Pencil, Trash2, Play, Download, FileText, FileSpreadsheet,
} from 'lucide-vue-next'
import reportTemplatesApi from '@/api/reportTemplates'

const router = useRouter()
const { t } = useI18n()

const templates = ref([])
const loading = ref(false)
const error = ref('')
const exporting = ref(null) // template id being exported
const showCreate = ref(false)
const newName = ref('')

onMounted(loadTemplates)

async function loadTemplates() {
  loading.value = true
  try {
    const { data } = await reportTemplatesApi.list()
    templates.value = data.data || data
  } catch (e) {
    error.value = 'Fehler beim Laden der Report-Templates'
  } finally {
    loading.value = false
  }
}

async function createTemplate() {
  if (!newName.value.trim()) return
  try {
    const { data } = await reportTemplatesApi.create({
      name: newName.value.trim(),
      template_json: {
        version: 1, title: newName.value.trim(),
        pageHeader: { elements: [] }, pageFooter: { elements: [] },
        groups: [], style: { font: 'Arial', size: 11, primaryColor: '#2563eb' },
      },
      format: 'pdf',
    })
    const tmpl = data.data || data
    router.push(`/reports/${tmpl.id}`)
  } catch (e) {
    error.value = 'Fehler beim Erstellen'
  }
}

async function deleteTemplate(id) {
  if (!confirm('Report-Template wirklich löschen?')) return
  try {
    await reportTemplatesApi.remove(id)
    templates.value = templates.value.filter(t => t.id !== id)
  } catch (e) {
    error.value = 'Fehler beim Löschen'
  }
}

async function exportTemplate(id, format) {
  exporting.value = id
  error.value = ''
  try {
    const response = await reportTemplatesApi.execute(id, { format })
    const ext = format === 'docx' ? 'docx' : 'pdf'
    const tmpl = templates.value.find(t => t.id === id)
    const name = tmpl?.name || 'report'
    triggerDownload(new Blob([response.data]), `${name}.${ext}`)
  } catch (e) {
    error.value = e.response?.data?.title || 'Export fehlgeschlagen'
  } finally {
    exporting.value = null
  }
}

function triggerDownload(blob, filename) {
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  a.click()
  setTimeout(() => URL.revokeObjectURL(url), 200)
}
</script>

<template>
  <div class="space-y-4 max-w-4xl mx-auto">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Report Designer</h2>
      <button class="pim-btn pim-btn-primary text-xs" @click="showCreate = !showCreate">
        <Plus class="w-3.5 h-3.5" :stroke-width="2" />
        Neues Template
      </button>
    </div>

    <!-- Create form -->
    <div v-if="showCreate" class="pim-card p-4 flex items-end gap-3">
      <div class="flex-1">
        <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Template-Name</label>
        <input
          v-model="newName"
          class="pim-input text-xs w-full"
          placeholder="z.B. Produktdatenblatt Q1"
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

    <!-- Error -->
    <div v-if="error" class="p-3 rounded-lg bg-[var(--color-error-light)] text-[var(--color-error)] text-xs">{{ error }}</div>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-8 text-[var(--color-text-tertiary)] text-sm">Lade Templates...</div>

    <!-- Empty state -->
    <div v-else-if="!templates.length" class="pim-card p-8 text-center">
      <FileText class="w-10 h-10 mx-auto mb-3 text-[var(--color-text-tertiary)]" :stroke-width="1.25" />
      <p class="text-sm text-[var(--color-text-secondary)]">Noch keine Report-Templates vorhanden.</p>
      <p class="text-xs text-[var(--color-text-tertiary)] mt-1">Erstelle ein neues Template, um strukturierte Produktberichte zu generieren.</p>
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
            <FileText class="w-4 h-4 text-[var(--color-accent)] shrink-0" :stroke-width="1.75" />
            <span class="text-sm font-medium text-[var(--color-text-primary)] truncate">{{ tmpl.name }}</span>
            <span class="text-[10px] px-1.5 py-0.5 rounded bg-[var(--color-bg)] text-[var(--color-text-tertiary)]">
              {{ tmpl.format?.toUpperCase() || 'PDF' }}
            </span>
          </div>
          <div class="text-[11px] text-[var(--color-text-tertiary)] mt-0.5 flex items-center gap-3">
            <span v-if="tmpl.description">{{ tmpl.description }}</span>
            <span v-if="tmpl.search_profile">Suchprofil: {{ tmpl.search_profile.name }}</span>
            <span>{{ tmpl.page_orientation === 'landscape' ? 'Querformat' : 'Hochformat' }}</span>
          </div>
        </div>

        <div class="flex items-center gap-1.5 shrink-0">
          <button
            class="pim-btn pim-btn-secondary text-[11px] px-2 py-1"
            @click="exportTemplate(tmpl.id, 'pdf')"
            :disabled="exporting === tmpl.id"
            title="PDF exportieren"
          >
            <Download class="w-3 h-3" :stroke-width="2" />
            PDF
          </button>
          <button
            class="pim-btn pim-btn-secondary text-[11px] px-2 py-1"
            @click="exportTemplate(tmpl.id, 'docx')"
            :disabled="exporting === tmpl.id"
            title="Word exportieren"
          >
            <FileSpreadsheet class="w-3 h-3" :stroke-width="2" />
            DOCX
          </button>
          <button
            class="pim-btn pim-btn-secondary text-[11px] px-2 py-1"
            @click="router.push(`/reports/${tmpl.id}`)"
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
