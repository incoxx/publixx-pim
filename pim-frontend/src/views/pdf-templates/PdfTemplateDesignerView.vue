<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { useRoute, useRouter, onBeforeRouteLeave } from 'vue-router'
import { usePdfTemplateDesignerStore } from '@/stores/pdfTemplateDesigner'
import {
  ArrowLeft, Save, Eye, Grid3x3, Magnet, Users,
} from 'lucide-vue-next'
import pdfTemplatesApi from '@/api/pdfTemplates'
import PdfFieldPalette from '@/components/pdf-templates/PdfFieldPalette.vue'
import PdfCanvas from '@/components/pdf-templates/PdfCanvas.vue'
import PdfElementProperties from '@/components/pdf-templates/PdfElementProperties.vue'

const route = useRoute()
const router = useRouter()
const store = usePdfTemplateDesignerStore()

const saving = ref(false)
const previewing = ref(false)
const error = ref('')
const loadError = ref(false)

onMounted(async () => {
  const id = route.params.id
  try {
    await Promise.all([
      store.loadTemplate(id),
      store.loadFields(),
    ])
  } catch (e) {
    loadError.value = true
    error.value = 'Vorlage konnte nicht geladen werden.'
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
    if (store.isDirty) await store.saveTemplate()
    const response = await pdfTemplatesApi.preview(store.currentTemplate.id, {})
    const blob = new Blob([response.data], { type: 'application/pdf' })
    const url = URL.createObjectURL(blob)
    window.open(url, '_blank')
    setTimeout(() => URL.revokeObjectURL(url), 30000)
  } catch (e) {
    error.value = 'Vorschau konnte nicht generiert werden'
  } finally {
    previewing.value = false
  }
}
</script>

<template>
  <div class="h-full flex flex-col" v-if="store.currentTemplate">
    <!-- Toolbar -->
    <div class="shrink-0 border-b border-[var(--color-border)] bg-[var(--color-surface)] px-4 py-2.5 flex items-center gap-3">
      <button
        class="pim-btn pim-btn-secondary text-xs px-2 py-1"
        @click="router.push('/pdf-templates')"
        title="Zurück"
      >
        <ArrowLeft class="w-3.5 h-3.5" :stroke-width="2" />
      </button>

      <input
        v-model="store.currentTemplate.name"
        class="pim-input text-sm font-medium w-56"
        placeholder="Vorlagen-Name"
        @input="store.isDirty = true"
      />

      <!-- Orientation -->
      <select
        v-model="store.currentTemplate.page_orientation"
        class="pim-input text-xs w-28"
        @change="store.isDirty = true"
      >
        <option value="portrait">Hochformat</option>
        <option value="landscape">Querformat</option>
      </select>

      <!-- Grid / Snap -->
      <div class="flex items-center gap-1 border-l border-[var(--color-border)] pl-3">
        <button
          class="pim-btn text-xs px-2 py-1"
          :class="store.showGrid ? 'pim-btn-primary' : 'pim-btn-secondary'"
          @click="store.showGrid = !store.showGrid"
          title="Raster anzeigen"
        >
          <Grid3x3 class="w-3.5 h-3.5" :stroke-width="2" />
        </button>
        <button
          class="pim-btn text-xs px-2 py-1"
          :class="store.snapToGrid ? 'pim-btn-primary' : 'pim-btn-secondary'"
          @click="store.snapToGrid = !store.snapToGrid"
          title="Am Raster einrasten"
        >
          <Magnet class="w-3.5 h-3.5" :stroke-width="2" />
        </button>
        <select
          v-model.number="store.gridSize"
          class="pim-input text-xs w-16"
          title="Rastergröße (mm)"
        >
          <option :value="2">2mm</option>
          <option :value="5">5mm</option>
          <option :value="10">10mm</option>
        </select>
      </div>

      <!-- Shared -->
      <label class="flex items-center gap-1.5 text-[11px] cursor-pointer text-[var(--color-text-secondary)] border-l border-[var(--color-border)] pl-3">
        <input
          type="checkbox"
          v-model="store.currentTemplate.is_shared"
          class="rounded"
          @change="store.isDirty = true"
        />
        <Users class="w-3 h-3" :stroke-width="2" />
        Teilen
      </label>

      <div class="flex-1"></div>

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
    </div>

    <!-- Error -->
    <div v-if="error" class="px-4 py-2 bg-[var(--color-error-light)] text-[var(--color-error)] text-xs">{{ error }}</div>

    <!-- 3-Column Layout -->
    <div class="flex-1 flex overflow-hidden">
      <!-- Left: Field Palette -->
      <div class="w-[250px] shrink-0 border-r border-[var(--color-border)] overflow-y-auto bg-[var(--color-surface)]">
        <PdfFieldPalette />
      </div>

      <!-- Center: Canvas -->
      <PdfCanvas />

      <!-- Right: Properties -->
      <div class="w-[300px] shrink-0 border-l border-[var(--color-border)] overflow-y-auto bg-[var(--color-surface)]">
        <PdfElementProperties />
      </div>
    </div>
  </div>

  <!-- Loading / Error state -->
  <div v-else class="flex flex-col items-center justify-center h-64 text-[var(--color-text-tertiary)]">
    <template v-if="loadError">
      <p class="text-[var(--color-error)] text-sm mb-2">{{ error }}</p>
      <button class="pim-btn pim-btn-secondary text-xs" @click="router.push('/pdf-templates')">Zurück zur Übersicht</button>
    </template>
    <template v-else>Lade Vorlage...</template>
  </div>
</template>
