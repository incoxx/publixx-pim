<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { useRoute, useRouter, onBeforeRouteLeave } from 'vue-router'
import { useReportDesignerStore } from '@/stores/reportDesigner'
import {
  ArrowLeft, Save, Eye, Download, FileText, FileSpreadsheet,
} from 'lucide-vue-next'
import reportTemplatesApi from '@/api/reportTemplates'
import searchProfilesApi from '@/api/searchProfiles'
import FieldPalette from '@/components/reports/FieldPalette.vue'
import GroupEditor from '@/components/reports/GroupEditor.vue'
import ElementProperties from '@/components/reports/ElementProperties.vue'

const route = useRoute()
const router = useRouter()
const store = useReportDesignerStore()

const searchProfiles = ref([])
const saving = ref(false)
const previewing = ref(false)
const exporting = ref(false)
const error = ref('')
const loadError = ref(false)

onMounted(async () => {
  const id = route.params.id
  try {
    await Promise.all([
      store.loadTemplate(id),
      store.loadFields(),
      loadSearchProfiles(),
    ])
  } catch (e) {
    loadError.value = true
    error.value = 'Template konnte nicht geladen werden.'
  }
})

// Unsaved changes guard
function beforeUnload(e) {
  if (store.isDirty) {
    e.preventDefault()
    e.returnValue = ''
  }
}
window.addEventListener('beforeunload', beforeUnload)
onBeforeUnmount(() => window.removeEventListener('beforeunload', beforeUnload))

onBeforeRouteLeave(() => {
  if (store.isDirty && !confirm('Es gibt ungespeicherte Änderungen. Seite wirklich verlassen?')) {
    return false
  }
})

async function loadSearchProfiles() {
  try {
    const { data } = await searchProfilesApi.list()
    searchProfiles.value = data.data || data
  } catch (e) { /* ignore */ }
}

async function save() {
  saving.value = true
  error.value = ''
  try {
    await store.saveTemplate()
  } catch (e) {
    error.value = 'Fehler beim Speichern'
  } finally {
    saving.value = false
  }
}

async function preview() {
  previewing.value = true
  error.value = ''
  try {
    // Save first if dirty
    if (store.isDirty) await store.saveTemplate()

    const format = store.currentTemplate.format || 'pdf'
    const response = await reportTemplatesApi.preview(store.currentTemplate.id, {
      format,
      limit: 5,
    })
    const mimeType = format === 'docx'
      ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
      : 'application/pdf'
    const blob = new Blob([response.data], { type: mimeType })
    const url = URL.createObjectURL(blob)
    window.open(url, '_blank')
    setTimeout(() => URL.revokeObjectURL(url), 30000)
  } catch (e) {
    error.value = 'Vorschau konnte nicht generiert werden'
  } finally {
    previewing.value = false
  }
}

async function exportReport(format) {
  exporting.value = true
  error.value = ''
  try {
    if (store.isDirty) await store.saveTemplate()

    const response = await reportTemplatesApi.execute(store.currentTemplate.id, { format })
    const ext = format === 'docx' ? 'docx' : 'pdf'
    const name = store.currentTemplate.name || 'report'
    const mimeType = format === 'docx'
      ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
      : 'application/pdf'
    const blob = new Blob([response.data], { type: mimeType })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `${name}.${ext}`
    a.click()
    setTimeout(() => URL.revokeObjectURL(url), 200)
  } catch (e) {
    error.value = 'Export fehlgeschlagen'
  } finally {
    exporting.value = false
  }
}

function onSearchProfileChange(id) {
  if (store.currentTemplate) {
    store.currentTemplate.search_profile_id = id || null
    store.isDirty = true
  }
}
</script>

<template>
  <div class="h-full flex flex-col" v-if="store.currentTemplate">
    <!-- Toolbar -->
    <div class="shrink-0 border-b border-[var(--color-border)] bg-[var(--color-surface)] px-4 py-2.5 flex items-center gap-3">
      <button
        class="pim-btn pim-btn-secondary text-xs px-2 py-1"
        @click="router.push('/reports')"
        title="Zurück"
      >
        <ArrowLeft class="w-3.5 h-3.5" :stroke-width="2" />
      </button>

      <input
        v-model="store.currentTemplate.name"
        class="pim-input text-sm font-medium w-56"
        placeholder="Template-Name"
        @input="store.isDirty = true"
      />

      <!-- Format -->
      <select
        v-model="store.currentTemplate.format"
        class="pim-input text-xs w-20"
        @change="store.isDirty = true"
      >
        <option value="pdf">PDF</option>
        <option value="docx">DOCX</option>
      </select>

      <!-- Orientation -->
      <select
        v-model="store.currentTemplate.page_orientation"
        class="pim-input text-xs w-28"
        @change="store.isDirty = true"
      >
        <option value="portrait">Hochformat</option>
        <option value="landscape">Querformat</option>
      </select>

      <!-- Language -->
      <select
        v-model="store.currentTemplate.language"
        class="pim-input text-xs w-16"
        @change="store.isDirty = true"
      >
        <option value="de">DE</option>
        <option value="en">EN</option>
      </select>

      <div class="flex-1"></div>

      <!-- Search Profile -->
      <div class="w-48">
        <select
          class="pim-input text-xs w-full"
          :value="store.currentTemplate.search_profile_id || ''"
          @change="onSearchProfileChange($event.target.value)"
        >
          <option value="">Alle Produkte</option>
          <option v-for="sp in searchProfiles" :key="sp.id" :value="sp.id">{{ sp.name }}</option>
        </select>
      </div>

      <!-- Actions -->
      <button
        class="pim-btn pim-btn-primary text-xs"
        @click="save"
        :disabled="saving || !store.isDirty"
      >
        <Save class="w-3.5 h-3.5" :stroke-width="2" />
        {{ saving ? 'Speichern...' : 'Speichern' }}
      </button>

      <button
        class="pim-btn pim-btn-secondary text-xs"
        @click="preview"
        :disabled="previewing"
      >
        <Eye class="w-3.5 h-3.5" :stroke-width="2" />
        {{ previewing ? 'Laden...' : 'Vorschau' }}
      </button>

      <button
        class="pim-btn pim-btn-secondary text-xs"
        @click="exportReport(store.currentTemplate.format || 'pdf')"
        :disabled="exporting"
      >
        <Download class="w-3.5 h-3.5" :stroke-width="2" />
        Export
      </button>
    </div>

    <!-- Error -->
    <div v-if="error" class="px-4 py-2 bg-[var(--color-error-light)] text-[var(--color-error)] text-xs">{{ error }}</div>

    <!-- 3-Column Layout -->
    <div class="flex-1 flex overflow-hidden">
      <!-- Left: Field Palette -->
      <div class="w-[250px] shrink-0 border-r border-[var(--color-border)] overflow-y-auto bg-[var(--color-surface)]">
        <FieldPalette />
      </div>

      <!-- Center: Group Editor -->
      <div class="flex-1 overflow-y-auto p-4 bg-[var(--color-bg)]">
        <GroupEditor />
      </div>

      <!-- Right: Properties -->
      <div class="w-[300px] shrink-0 border-l border-[var(--color-border)] overflow-y-auto bg-[var(--color-surface)]">
        <ElementProperties />
      </div>
    </div>
  </div>

  <!-- Loading / Error state -->
  <div v-else class="flex flex-col items-center justify-center h-64 text-[var(--color-text-tertiary)]">
    <template v-if="loadError">
      <p class="text-[var(--color-error)] text-sm mb-2">{{ error }}</p>
      <button class="pim-btn pim-btn-secondary text-xs" @click="router.push('/reports')">Zurück zur Übersicht</button>
    </template>
    <template v-else>Lade Template...</template>
  </div>
</template>
