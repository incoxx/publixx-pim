<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'
import { useRoute, useRouter, onBeforeRouteLeave } from 'vue-router'
import { usePdfTemplateDesignerStore } from '@/stores/pdfTemplateDesigner'
import {
  ArrowLeft, Save, Eye, Pencil, Grid3x3, Magnet, Users, Search, FileDown, ChevronDown,
} from 'lucide-vue-next'
import { fontFamilies } from '@/components/pdf-templates/fontList'
import pdfTemplatesApi from '@/api/pdfTemplates'
import productsApi from '@/api/products'
import PdfFieldPalette from '@/components/pdf-templates/PdfFieldPalette.vue'
import PdfCanvas from '@/components/pdf-templates/PdfCanvas.vue'
import PdfElementProperties from '@/components/pdf-templates/PdfElementProperties.vue'

const route = useRoute()
const router = useRouter()
const store = usePdfTemplateDesignerStore()

const saving = ref(false)
const previewing = ref(false)
const exporting = ref(false)
const showExportMenu = ref(false)
const error = ref('')
const loadError = ref(false)

// Product search for reference product
const productSearch = ref('')
const productResults = ref([])
const showProductDropdown = ref(false)
const searchingProducts = ref(false)
let searchTimeout = null

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
onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', beforeUnload)
  document.removeEventListener('click', closeExportMenu)
})

function closeExportMenu(e) {
  if (showExportMenu.value && !e.target.closest('.relative')) {
    showExportMenu.value = false
  }
}
document.addEventListener('click', closeExportMenu)

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
    const params = {}
    if (store.referenceProductId) params.product_id = store.referenceProductId
    const response = await pdfTemplatesApi.preview(store.currentTemplate.id, params)
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

function onDefaultFontChange(e) {
  store.templateJson.style = store.templateJson.style || {}
  store.templateJson.style.defaultFontFamily = e.target.value
  store.isDirty = true
}

// Product search with debounce
watch(productSearch, (val) => {
  clearTimeout(searchTimeout)
  if (!val || val.length < 2) {
    productResults.value = []
    showProductDropdown.value = false
    return
  }
  searchTimeout = setTimeout(async () => {
    searchingProducts.value = true
    try {
      const { data } = await productsApi.list({ search: val, per_page: 8 })
      productResults.value = data.data || data
      showProductDropdown.value = productResults.value.length > 0
    } catch (e) {
      productResults.value = []
    } finally {
      searchingProducts.value = false
    }
  }, 300)
})

function selectProduct(product) {
  const label = `${product.sku || ''} – ${product.name || ''}`
  productSearch.value = label
  store.setReferenceProduct(product.id, label)
  showProductDropdown.value = false
}

function clearProduct() {
  productSearch.value = ''
  store.setReferenceProduct(null, '')
  store.previewMode = false
  store.resolvedElements = []
}

async function togglePreview() {
  if (!store.referenceProductId) return
  error.value = ''
  try {
    await store.togglePreviewMode()
  } catch (e) {
    error.value = 'Vorschau konnte nicht geladen werden'
  }
}

async function exportAs(format) {
  showExportMenu.value = false
  if (!store.referenceProductId) {
    error.value = 'Bitte zuerst ein Referenz-Produkt auswählen.'
    return
  }
  exporting.value = true
  error.value = ''
  try {
    if (store.isDirty) await store.saveTemplate()
    const response = await pdfTemplatesApi.execute(
      store.currentTemplate.id,
      { product_ids: [store.referenceProductId], language: 'de' },
      format,
    )
    const mimeTypes = {
      pdf: 'application/pdf',
      docx: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      indesign: 'application/zip',
    }
    const extensions = { pdf: '.pdf', docx: '.docx', indesign: '_indesign.zip' }
    const blob = new Blob([response.data], { type: mimeTypes[format] || 'application/octet-stream' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = (store.currentTemplate.name || 'export') + (extensions[format] || '')
    link.click()
    URL.revokeObjectURL(url)
  } catch (e) {
    error.value = 'Export fehlgeschlagen'
  } finally {
    exporting.value = false
  }
}
</script>

<template>
  <div class="h-full flex flex-col" v-if="store.currentTemplate">
    <!-- Toolbar Row 1 -->
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
        class="pim-input text-sm font-medium w-48"
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

      <!-- Default Font -->
      <select
        :value="store.templateJson.style?.defaultFontFamily || 'DejaVu Sans'"
        class="pim-input text-xs w-36"
        @change="onDefaultFontChange"
        title="Standard-Schriftart für neue Elemente"
      >
        <option v-for="f in fontFamilies" :key="f.value" :value="f.value">{{ f.label }}</option>
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

      <!-- Export Dropdown -->
      <div class="relative">
        <button
          class="pim-btn pim-btn-secondary text-xs"
          @click="showExportMenu = !showExportMenu"
          :disabled="exporting"
        >
          <FileDown class="w-3.5 h-3.5" :stroke-width="2" />
          {{ exporting ? 'Exportiere...' : 'Export' }}
          <ChevronDown class="w-3 h-3 ml-0.5" :stroke-width="2" />
        </button>
        <div
          v-if="showExportMenu"
          class="absolute right-0 top-full mt-1 bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg shadow-lg z-50 min-w-[160px] py-1"
        >
          <button
            class="w-full text-left px-3 py-1.5 text-[11px] hover:bg-[var(--color-bg)] text-[var(--color-text-primary)]"
            @click="exportAs('pdf')"
          >PDF exportieren</button>
          <button
            class="w-full text-left px-3 py-1.5 text-[11px] hover:bg-[var(--color-bg)] text-[var(--color-text-primary)]"
            @click="exportAs('docx')"
          >Word exportieren</button>
          <button
            class="w-full text-left px-3 py-1.5 text-[11px] hover:bg-[var(--color-bg)] text-[var(--color-text-primary)]"
            @click="exportAs('indesign')"
          >InDesign exportieren</button>
        </div>
      </div>
    </div>

    <!-- Toolbar Row 2: Reference Product -->
    <div class="shrink-0 border-b border-[var(--color-border)] bg-[var(--color-surface)] px-4 py-1.5 flex items-center gap-3">
      <span class="text-[11px] text-[var(--color-text-tertiary)] whitespace-nowrap">Referenz-Produkt:</span>

      <!-- Product search -->
      <div class="relative w-64">
        <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3 h-3 text-[var(--color-text-tertiary)]" :stroke-width="2" />
        <input
          v-model="productSearch"
          class="pim-input text-[11px] w-full pim-input-icon pr-6"
          placeholder="Produkt suchen (SKU/Name)..."
          @focus="showProductDropdown = productResults.length > 0"
          @blur="setTimeout(() => showProductDropdown = false, 200)"
        />
        <button
          v-if="store.referenceProductId"
          class="absolute right-1.5 top-1/2 -translate-y-1/2 text-[var(--color-text-tertiary)] hover:text-[var(--color-text-secondary)] text-sm leading-none"
          @click="clearProduct"
          title="Referenz-Produkt entfernen"
        >×</button>

        <!-- Dropdown -->
        <div
          v-if="showProductDropdown"
          class="absolute z-50 top-full left-0 right-0 mt-1 bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg shadow-lg max-h-48 overflow-y-auto"
        >
          <button
            v-for="p in productResults"
            :key="p.id"
            class="w-full text-left px-3 py-1.5 text-[11px] hover:bg-[var(--color-bg)] flex items-center gap-2"
            @mousedown.prevent="selectProduct(p)"
          >
            <span class="font-mono text-[var(--color-accent)]">{{ p.sku || '–' }}</span>
            <span class="truncate text-[var(--color-text-secondary)]">{{ p.name }}</span>
          </button>
        </div>
      </div>

      <!-- Preview/Edit toggle -->
      <button
        class="pim-btn text-xs px-2 py-1"
        :class="store.previewMode ? 'pim-btn-primary' : 'pim-btn-secondary'"
        :disabled="!store.referenceProductId || store.previewLoading"
        @click="togglePreview"
        :title="store.previewMode ? 'Bearbeiten' : 'Vorschau mit Referenz-Produkt'"
      >
        <component :is="store.previewMode ? Pencil : Eye" class="w-3.5 h-3.5" :stroke-width="2" />
        {{ store.previewLoading ? 'Laden...' : (store.previewMode ? 'Bearbeiten' : 'Datenvorschau') }}
      </button>

      <span v-if="store.referenceProductId && store.referenceProductLabel" class="text-[10px] text-[var(--color-text-tertiary)] truncate max-w-48">
        {{ store.referenceProductLabel }}
      </span>
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
