<script setup>
import { ref } from 'vue'
import { Plus, Trash2, Copy, GripVertical, ChevronDown, ChevronRight } from 'lucide-vue-next'
import { useExcelDesignerStore } from '@/stores/excelDesigner'
import ExcelColumnConfig from './ExcelColumnConfig.vue'

const store = useExcelDesignerStore()
const dragColumnIndex = ref(null)
const expandedSheets = ref(new Set())

function toggleSheet(sheetId) {
  if (expandedSheets.value.has(sheetId)) {
    expandedSheets.value.delete(sheetId)
  } else {
    expandedSheets.value.add(sheetId)
  }
}

// Spalten Drag & Drop
function onDragStart(e, sheetId, colIndex) {
  dragColumnIndex.value = colIndex
  e.dataTransfer.effectAllowed = 'move'
  e.dataTransfer.setData('text/plain', colIndex.toString())
}

function onDragOver(e) {
  e.preventDefault()
  e.dataTransfer.dropEffect = 'move'
}

function onDrop(e, sheetId, toIndex) {
  e.preventDefault()
  const fromIndex = dragColumnIndex.value
  if (fromIndex !== null && fromIndex !== toIndex) {
    store.moveColumn(sheetId, fromIndex, toIndex)
  }
  dragColumnIndex.value = null
}

function onDropFromPicker(e, sheetId) {
  e.preventDefault()
  try {
    const data = JSON.parse(e.dataTransfer.getData('application/json'))
    if (data) {
      store.addColumn(sheetId, data)
    }
  } catch {
    // Kein gültiges Spalten-Objekt
  }
}

const columnTypeLabels = {
  field: 'Feld',
  attribute: 'Attribut',
  price: 'Preis',
  image: 'Bild',
  media: 'Medien',
  relation: 'Relation',
  variant: 'Variante',
  collection: 'Collection',
  static: 'Statisch',
  text: 'Text',
}

const columnTypeColors = {
  field: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
  attribute: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
  price: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
  image: 'bg-pink-100 text-pink-700 dark:bg-pink-900/30 dark:text-pink-300',
  media: 'bg-pink-100 text-pink-700 dark:bg-pink-900/30 dark:text-pink-300',
  relation: 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-300',
  variant: 'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-300',
  collection: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300',
  static: 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
  text: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300',
}

const groupFieldLabels = {
  none: 'Kein Grouping (ein Sheet)',
  product_type: 'Nach Produkttyp',
  master_hierarchy_node: 'Nach Kategorie',
  status: 'Nach Status',
}
</script>

<template>
  <div class="space-y-3">
    <!-- Sheet Tabs -->
    <div class="flex items-center gap-2 border-b border-[var(--color-border)] pb-2">
      <button
        v-for="sheet in store.templateJson.sheets"
        :key="sheet.id"
        class="px-3 py-1.5 text-xs rounded-t-lg border border-b-0 transition-colors"
        :class="store.selectedSheetId === sheet.id
          ? 'bg-[var(--color-surface)] border-[var(--color-border)] text-[var(--color-text-primary)] font-medium'
          : 'bg-transparent border-transparent text-[var(--color-text-tertiary)] hover:text-[var(--color-text-secondary)]'"
        @click="store.selectSheet(sheet.id)"
      >
        {{ sheet.label || 'Blatt' }}
      </button>
      <button class="px-2 py-1 text-xs text-[var(--color-accent)] hover:bg-[var(--color-accent)]/10 rounded" @click="store.addSheet">
        <Plus class="w-3.5 h-3.5 inline" :stroke-width="2" /> Blatt
      </button>
    </div>

    <!-- Aktives Sheet -->
    <template v-if="store.selectedSheet">
      <div class="pim-card p-4 space-y-4">
        <!-- Sheet-Header -->
        <div class="flex items-center gap-3">
          <input
            v-model="store.selectedSheet.label"
            class="pim-input text-sm font-medium flex-1"
            placeholder="Sheet-Name (z.B. Produkte, Preisliste...)"
            @input="store.isDirty = true"
          />

          <select
            v-model="store.selectedSheet.field"
            class="pim-input text-xs w-52"
            @change="store.isDirty = true"
          >
            <option v-for="(label, field) in groupFieldLabels" :key="field" :value="field">{{ label }}</option>
            <option
              v-for="attr in (store.availableFields?.attributes || [])"
              :key="'attr:' + attr.attributeId"
              :value="'attribute:' + attr.attributeId"
            >
              Attribut: {{ attr.label_de }}
            </option>
          </select>

          <button
            class="pim-btn pim-btn-secondary text-[11px] px-2 py-1"
            @click="store.duplicateSheet(store.selectedSheet.id)"
            title="Sheet duplizieren"
          >
            <Copy class="w-3 h-3" :stroke-width="2" />
          </button>
          <button
            v-if="store.templateJson.sheets.length > 1"
            class="pim-btn pim-btn-secondary text-[11px] px-2 py-1 text-[var(--color-error)]"
            @click="store.removeSheet(store.selectedSheet.id)"
            title="Sheet löschen"
          >
            <Trash2 class="w-3 h-3" :stroke-width="2" />
          </button>
        </div>

        <p v-if="store.selectedSheet.field !== 'none'" class="text-[11px] text-[var(--color-text-tertiary)]">
          Gruppierung aktiv: Pro Wert wird ein eigenes Excel-Sheet erzeugt.
        </p>

        <!-- Spalten -->
        <div class="space-y-1">
          <div class="flex items-center justify-between mb-2">
            <h3 class="text-xs font-semibold text-[var(--color-text-secondary)] uppercase tracking-wide">Spalten</h3>
            <span class="text-[11px] text-[var(--color-text-tertiary)]">{{ store.selectedSheet.columns.length }} Spalten</span>
          </div>

          <!-- Drop zone -->
          <div
            class="space-y-1 min-h-[60px] rounded-lg border-2 border-dashed border-[var(--color-border)] p-2 transition-colors"
            @dragover.prevent="onDragOver"
            @drop="onDropFromPicker($event, store.selectedSheet.id)"
          >
            <div
              v-if="store.selectedSheet.columns.length === 0"
              class="text-center py-6 text-[var(--color-text-tertiary)] text-xs"
            >
              Felder aus der Palette hierher ziehen oder doppelklicken
            </div>

            <div
              v-for="(col, idx) in store.selectedSheet.columns"
              :key="col.id"
              class="flex items-center gap-2 px-2 py-1.5 rounded-md border border-[var(--color-border)] bg-[var(--color-surface)] hover:bg-[var(--color-bg)] transition-colors cursor-pointer"
              :class="{ 'ring-2 ring-[var(--color-accent)]': store.selectedColumnId === col.id }"
              draggable="true"
              @dragstart="onDragStart($event, store.selectedSheet.id, idx)"
              @dragover.prevent="onDragOver"
              @drop.stop="onDrop($event, store.selectedSheet.id, idx)"
              @click="store.selectColumn(col.id)"
            >
              <GripVertical class="w-3 h-3 text-[var(--color-text-tertiary)] cursor-grab shrink-0" :stroke-width="2" />

              <span class="text-[10px] px-1.5 py-0.5 rounded font-medium shrink-0" :class="columnTypeColors[col.type] || columnTypeColors.static">
                {{ columnTypeLabels[col.type] || col.type }}
              </span>

              <input
                v-model="col.label"
                class="pim-input text-xs flex-1 min-w-0"
                placeholder="Spaltenüberschrift"
                @input="store.isDirty = true"
                @click.stop
              />

              <span v-if="col.type === 'static' && col.defaultValue" class="text-[10px] text-[var(--color-text-tertiary)] truncate max-w-[100px]">
                = {{ col.defaultValue }}
              </span>
              <span v-else-if="col.type === 'static' && !col.defaultValue" class="text-[10px] text-[var(--color-text-tertiary)] italic">
                leer
              </span>

              <button
                class="p-0.5 text-[var(--color-text-tertiary)] hover:text-[var(--color-error)] shrink-0"
                @click.stop="store.removeColumn(store.selectedSheet.id, col.id)"
                title="Spalte entfernen"
              >
                <Trash2 class="w-3 h-3" :stroke-width="2" />
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Spalten-Konfiguration (wenn Spalte ausgewählt) -->
      <ExcelColumnConfig v-if="store.selectedColumn" />
    </template>
  </div>
</template>
