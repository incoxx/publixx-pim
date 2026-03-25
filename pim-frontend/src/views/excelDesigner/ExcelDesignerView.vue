<script setup>
import { ref, onMounted, onBeforeUnmount, computed } from 'vue'
import { useRoute, useRouter, onBeforeRouteLeave } from 'vue-router'
import { Save, Download, Eye, ChevronLeft, Settings, FolderOpen, Copy, Trash2, ChevronDown, Search } from 'lucide-vue-next'
import { useExcelDesignerStore } from '@/stores/excelDesigner'
import searchProfilesApi from '@/api/searchProfiles'
import ExcelSheetEditor from '@/components/excelDesigner/ExcelSheetEditor.vue'
import ExcelFieldPicker from '@/components/excelDesigner/ExcelFieldPicker.vue'
import ExcelPreview from '@/components/excelDesigner/ExcelPreview.vue'
import ExcelSettingsModal from '@/components/excelDesigner/ExcelSettingsModal.vue'
import ExcelExportProgress from '@/components/excelDesigner/ExcelExportProgress.vue'

const route = useRoute()
const router = useRouter()
const store = useExcelDesignerStore()

const showFieldPicker = ref(false)
const showPreview = ref(false)
const showSettings = ref(false)
const showTemplateMenu = ref(false)
const saving = ref(false)
const downloading = ref(false)
const savingAs = ref(false)
const saveAsName = ref('')

// Suchprofil-Auswahl
const searchProfiles = ref([])
const searchProfilesLoaded = ref(false)

onMounted(async () => {
  const id = route.params.id
  if (id) {
    await store.loadTemplate(id)
    await Promise.all([store.loadFields(), loadSearchProfiles(), store.loadTemplates()])
  }
})

onBeforeUnmount(() => {
  // Polling stoppen wenn Export läuft
  if (store.exportPolling) {
    store.dismissExport()
  }
})

async function loadSearchProfiles() {
  if (searchProfilesLoaded.value) return
  try {
    const { data } = await searchProfilesApi.list()
    searchProfiles.value = data.data || data
    searchProfilesLoaded.value = true
  } catch {
    // Suchprofile optional
  }
}

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

async function saveAs() {
  const name = saveAsName.value.trim()
  if (!name) return
  savingAs.value = true
  try {
    const tmpl = await store.saveTemplateAs(name)
    saveAsName.value = ''
    showTemplateMenu.value = false
    router.replace(`/excel-designer/${tmpl.id}`)
  } finally {
    savingAs.value = false
  }
}

async function deleteCurrentTemplate() {
  if (!store.currentTemplate) return
  if (!confirm(`Template "${store.currentTemplate.name}" wirklich löschen?`)) return
  await store.deleteTemplate(store.currentTemplate.id)
  router.push('/excel-designer')
}

async function switchTemplate(id) {
  if (store.isDirty && !confirm('Ungespeicherte Änderungen verwerfen?')) return
  showTemplateMenu.value = false
  router.push(`/excel-designer/${id}`)
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

function onSearchProfileChange(e) {
  const val = e.target.value || null
  store.currentTemplate.search_profile_id = val
  store.isDirty = true
}

const selectedProfileName = computed(() => {
  if (!store.currentTemplate?.search_profile_id) return null
  const sp = searchProfiles.value.find(s => s.id === store.currentTemplate.search_profile_id)
  return sp?.name || null
})

// Click-outside für Template-Menü
function onClickOutside(e) {
  if (!e.target.closest('.template-menu-container')) {
    showTemplateMenu.value = false
  }
}
if (typeof window !== 'undefined') {
  window.addEventListener('click', onClickOutside, true)
  onBeforeUnmount(() => window.removeEventListener('click', onClickOutside, true))
}
</script>

<template>
  <div class="h-full flex flex-col" v-if="store.currentTemplate">
    <!-- Toolbar -->
    <div class="pim-card rounded-none border-x-0 border-t-0 px-4 py-2 flex items-center gap-3 shrink-0">
      <button class="pim-btn pim-btn-secondary text-xs px-2 py-1" @click="router.push('/excel-designer')" title="Zurück zur Liste">
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

      <!-- Suchprofil-Auswahl -->
      <div class="flex items-center gap-1.5">
        <Search class="w-3 h-3 text-[var(--color-text-tertiary)] shrink-0" :stroke-width="2" />
        <select
          :value="store.currentTemplate.search_profile_id || ''"
          @change="onSearchProfileChange"
          class="pim-input text-xs w-44"
          title="Suchprofil für Export"
        >
          <option value="">Alle aktiven Produkte</option>
          <option v-for="sp in searchProfiles" :key="sp.id" :value="sp.id">{{ sp.name }}</option>
        </select>
      </div>

      <div class="flex-1" />

      <!-- Template-Menü (Laden/Speichern unter/Löschen) -->
      <div class="relative template-menu-container">
        <button
          class="pim-btn pim-btn-secondary text-xs"
          @click.stop="showTemplateMenu = !showTemplateMenu"
          title="Template-Verwaltung"
        >
          <FolderOpen class="w-3.5 h-3.5" :stroke-width="2" />
          <ChevronDown class="w-3 h-3" :stroke-width="2" />
        </button>

        <div
          v-if="showTemplateMenu"
          class="absolute right-0 top-full mt-1 w-72 bg-[var(--color-surface)] rounded-lg shadow-xl border border-[var(--color-border)] z-50"
        >
          <!-- Speichern unter -->
          <div class="p-3 border-b border-[var(--color-border)]">
            <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Speichern unter</label>
            <div class="flex gap-1.5">
              <input
                v-model="saveAsName"
                class="pim-input text-xs flex-1"
                :placeholder="`${store.currentTemplate.name} (Kopie)`"
                @keyup.enter="saveAs"
              />
              <button class="pim-btn pim-btn-primary text-[11px] px-2" @click="saveAs" :disabled="savingAs">
                <Copy class="w-3 h-3" :stroke-width="2" />
              </button>
            </div>
          </div>

          <!-- Andere Templates laden -->
          <div class="max-h-48 overflow-auto">
            <div class="px-3 py-1.5 text-[11px] font-medium text-[var(--color-text-tertiary)] uppercase">Templates laden</div>
            <button
              v-for="tmpl in store.templates"
              :key="tmpl.id"
              class="w-full text-left px-3 py-1.5 text-xs hover:bg-[var(--color-bg)] flex items-center gap-2"
              :class="{ 'text-[var(--color-accent)] font-medium': tmpl.id === store.currentTemplate.id }"
              @click="switchTemplate(tmpl.id)"
            >
              <span class="flex-1 truncate">{{ tmpl.name }}</span>
              <span v-if="tmpl.id === store.currentTemplate.id" class="text-[10px] text-[var(--color-text-tertiary)]">aktiv</span>
            </button>
            <div v-if="!store.templates.length" class="px-3 py-2 text-xs text-[var(--color-text-tertiary)]">Keine Templates</div>
          </div>

          <!-- Löschen -->
          <div class="p-2 border-t border-[var(--color-border)]">
            <button
              class="w-full pim-btn pim-btn-secondary text-[11px] text-[var(--color-error)] justify-center"
              @click="deleteCurrentTemplate"
            >
              <Trash2 class="w-3 h-3" :stroke-width="2" />
              Template löschen
            </button>
          </div>
        </div>
      </div>

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
        :disabled="downloading || store.exportPolling"
      >
        <Download class="w-3.5 h-3.5" :stroke-width="2" />
        {{ downloading || store.exportPolling ? 'Exportiere...' : 'Download .xlsx' }}
      </button>

      <button
        class="pim-btn pim-btn-primary text-xs"
        @click="save"
        :disabled="saving"
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

    <!-- Export Progress Modal -->
    <ExcelExportProgress v-if="store.exportProgress" />
  </div>

  <!-- Loading state -->
  <div v-else class="flex items-center justify-center h-full text-[var(--color-text-tertiary)]">
    Lade Template...
  </div>
</template>
