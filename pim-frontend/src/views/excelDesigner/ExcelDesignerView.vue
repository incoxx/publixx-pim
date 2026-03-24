<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'
import { useRoute, useRouter, onBeforeRouteLeave } from 'vue-router'
import { Save, Download, Eye, ChevronLeft, Settings } from 'lucide-vue-next'
import { useExcelDesignerStore } from '@/stores/excelDesigner'
import ExcelSheetEditor from '@/components/excelDesigner/ExcelSheetEditor.vue'
import ExcelFieldPicker from '@/components/excelDesigner/ExcelFieldPicker.vue'
import ExcelPreview from '@/components/excelDesigner/ExcelPreview.vue'
import ExcelSettingsModal from '@/components/excelDesigner/ExcelSettingsModal.vue'

const route = useRoute()
const router = useRouter()
const store = useExcelDesignerStore()

const showFieldPicker = ref(false)
const showPreview = ref(false)
const showSettings = ref(false)
const saving = ref(false)
const downloading = ref(false)

onMounted(async () => {
  const id = route.params.id
  if (id) {
    await store.loadTemplate(id)
    await store.loadFields()
  }
})

onBeforeUnmount(() => {
  // Cleanup
})

// Unsaved changes guard
onBeforeRouteLeave((to, from) => {
  if (store.isDirty) {
    return confirm('Es gibt ungespeicherte Änderungen. Seite wirklich verlassen?')
  }
})

function onBeforeUnload(e) {
  if (store.isDirty) {
    e.preventDefault()
    e.returnValue = ''
  }
}
if (typeof window !== 'undefined') {
  window.addEventListener('beforeunload', onBeforeUnload)
  onBeforeUnmount(() => window.removeEventListener('beforeunload', onBeforeUnload))
}

async function save() {
  saving.value = true
  try {
    await store.saveTemplate()
  } finally {
    saving.value = false
  }
}

async function download() {
  downloading.value = true
  try {
    await store.downloadExcel()
  } finally {
    downloading.value = false
  }
}

async function preview() {
  showPreview.value = true
  await store.loadPreview()
}
</script>

<template>
  <div class="h-full flex flex-col" v-if="store.currentTemplate">
    <!-- Toolbar -->
    <div class="pim-card rounded-none border-x-0 border-t-0 px-4 py-2 flex items-center gap-3 shrink-0">
      <button class="pim-btn pim-btn-secondary text-xs px-2 py-1" @click="router.push('/excel-designer')" title="Zurück">
        <ChevronLeft class="w-3.5 h-3.5" :stroke-width="2" />
      </button>

      <input
        v-model="store.currentTemplate.name"
        class="pim-input text-sm font-medium flex-1 max-w-xs"
        placeholder="Template-Name"
        @input="store.isDirty = true"
      />

      <select
        v-model="store.currentTemplate.language"
        class="pim-input text-xs w-16"
        @change="store.isDirty = true"
      >
        <option value="de">DE</option>
        <option value="en">EN</option>
      </select>

      <div class="flex-1" />

      <button class="pim-btn pim-btn-secondary text-xs" @click="showSettings = true" title="Excel-Einstellungen">
        <Settings class="w-3.5 h-3.5" :stroke-width="2" />
      </button>

      <button class="pim-btn pim-btn-secondary text-xs" @click="showFieldPicker = !showFieldPicker">
        Felder
      </button>

      <button class="pim-btn pim-btn-secondary text-xs" @click="preview" :disabled="store.previewLoading">
        <Eye class="w-3.5 h-3.5" :stroke-width="2" />
        Vorschau
      </button>

      <button
        class="pim-btn pim-btn-secondary text-xs"
        @click="download"
        :disabled="downloading"
      >
        <Download class="w-3.5 h-3.5" :stroke-width="2" />
        {{ downloading ? 'Wird erstellt...' : 'Download .xlsx' }}
      </button>

      <button
        class="pim-btn pim-btn-primary text-xs"
        @click="save"
        :disabled="saving || !store.isDirty"
      >
        <Save class="w-3.5 h-3.5" :stroke-width="2" />
        {{ saving ? 'Speichern...' : 'Speichern' }}
      </button>
    </div>

    <!-- Main content -->
    <div class="flex-1 flex overflow-hidden">
      <!-- Sheet Editor (left) -->
      <div class="flex-1 overflow-auto p-4">
        <ExcelSheetEditor />
      </div>

      <!-- Field Picker (right panel) -->
      <div
        v-if="showFieldPicker"
        class="w-80 border-l border-[var(--color-border)] overflow-auto bg-[var(--color-surface)]"
      >
        <ExcelFieldPicker @close="showFieldPicker = false" />
      </div>
    </div>

    <!-- Preview Modal -->
    <ExcelPreview v-if="showPreview" @close="showPreview = false" />

    <!-- Settings Modal -->
    <ExcelSettingsModal v-if="showSettings" @close="showSettings = false" />
  </div>

  <!-- Loading state -->
  <div v-else class="flex items-center justify-center h-full text-[var(--color-text-tertiary)]">
    Lade Template...
  </div>
</template>
