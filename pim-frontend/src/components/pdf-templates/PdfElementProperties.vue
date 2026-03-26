<script setup>
import { computed, ref, onMounted } from 'vue'
import { usePdfTemplateDesignerStore } from '@/stores/pdfTemplateDesigner'
import { Settings, Trash2, Copy, ArrowUpToLine, ArrowDownToLine } from 'lucide-vue-next'
import { fontFamilies } from './fontList'
import SmartTableEditor from './SmartTableEditor.vue'
import client from '@/api/client'

const store = usePdfTemplateDesignerStore()

const relationTypes = ref([])
const attributeTypes = ref([])
const allAttributes = ref([])
const attrSearchQuery = ref('')
const attrTableSearchQuery = ref('')
const smartTableEditorOpen = ref(false)

onMounted(async () => {
  try {
    const [rtRes, attrRes, atRes] = await Promise.allSettled([
      client.get('/relation-types', { params: { per_page: 100 } }),
      client.get('/attributes', { params: { per_page: 500 } }),
      client.get('/attribute-types', { params: { per_page: 100 } }),
    ])
    if (rtRes.status === 'fulfilled') relationTypes.value = rtRes.value.data.data || rtRes.value.data || []
    if (attrRes.status === 'fulfilled') {
      const attrs = attrRes.value.data.data || attrRes.value.data || []
      allAttributes.value = attrs.filter(a => a.data_type !== 'Composite')
    }
    if (atRes.status === 'fulfilled') attributeTypes.value = atRes.value.data.data || atRes.value.data || []
  } catch (e) {
    console.warn('Failed to load data:', e.message)
  }
})

const sel = computed(() => store.selectedElement)

const filteredProductAttributes = computed(() => {
  const q = attrSearchQuery.value.toLowerCase()
  if (!q) return allAttributes.value
  return allAttributes.value.filter(a =>
    (a.name_de || '').toLowerCase().includes(q) ||
    (a.technical_name || '').toLowerCase().includes(q)
  )
})

const filteredAttrTableAttributes = computed(() => {
  const q = attrTableSearchQuery.value.toLowerCase()
  if (!q) return allAttributes.value
  return allAttributes.value.filter(a =>
    (a.name_de || '').toLowerCase().includes(q) ||
    (a.technical_name || '').toLowerCase().includes(q)
  )
})

// Compute current table headers for column width UI
const currentTableHeaders = computed(() => {
  if (!sel.value) return []
  const cols = sel.value.columns || []
  const headers = []
  for (const col of cols) {
    if (col === 'relation_type') headers.push('Beziehungsart')
    else if (col === 'sku') headers.push('SKU')
    else if (col === 'name') headers.push('Name')
    else if (col === 'ean') headers.push('EAN')
    else if (col === 'variant_attributes') headers.push('Var.-Attr.')
    else if (col === 'relation_attributes') headers.push('Bez.-Attr.')
    else if (col === 'product_attributes') {
      const ids = sel.value.productAttributeIds || []
      for (const id of ids) {
        const attr = allAttributes.value.find(a => a.id === id)
        headers.push(attr?.name_de || attr?.technical_name || 'Attribut')
      }
    }
  }
  return headers
})

function updateElement(key, value) {
  if (!sel.value) return
  store.updateElement(sel.value.id, { [key]: value })
}

function updateStyle(key, value) {
  if (!sel.value) return
  const style = { ...(sel.value.style || {}), [key]: value }
  store.updateElement(sel.value.id, { style })
}

function updateNumber(key, value) {
  updateElement(key, parseFloat(value) || 0)
}

function updateTableStyle(key, value) {
  if (!sel.value) return
  const tableStyle = { ...(sel.value.tableStyle || {}), [key]: value }
  store.updateElement(sel.value.id, { tableStyle })
}

function toggleColumn(col) {
  if (!sel.value) return
  const cols = [...(sel.value.columns || [])]
  const idx = cols.indexOf(col)
  if (idx >= 0) cols.splice(idx, 1)
  else cols.push(col)
  store.updateElement(sel.value.id, { columns: cols })
}

function toggleProductAttribute(attrId) {
  if (!sel.value) return
  const ids = [...(sel.value.productAttributeIds || [])]
  const idx = ids.indexOf(attrId)
  if (idx >= 0) ids.splice(idx, 1)
  else ids.push(attrId)
  store.updateElement(sel.value.id, { productAttributeIds: ids })
}

function toggleAttrTableAttribute(attrId) {
  if (!sel.value) return
  const ids = [...(sel.value.attributeIds || [])]
  const idx = ids.indexOf(attrId)
  if (idx >= 0) ids.splice(idx, 1)
  else ids.push(attrId)
  store.updateElement(sel.value.id, { attributeIds: ids })
}

function updateColumnWidth(index, value) {
  if (!sel.value) return
  const widths = [...(sel.value.columnWidths || [])]
  // Ensure array is long enough
  while (widths.length < currentTableHeaders.value.length) widths.push(0)
  widths[index] = parseInt(value) || 0
  store.updateElement(sel.value.id, { columnWidths: widths })
}

// fontFamilies imported from fontList.js

const canAutofit = computed(() => {
  if (!sel.value || sel.value.type === 'shape' || sel.value.type === 'image' || sel.value.type === 'variant_table' || sel.value.type === 'relation_table' || sel.value.type === 'attribute_table' || sel.value.type === 'smart_table') return false
  return !!store.referenceProductId && store.resolvedElements.length > 0
})

function autofitHeight() {
  if (!sel.value || !canAutofit.value) return
  const resolved = store.getResolvedElement(sel.value.id)
  if (!resolved || !resolved.displayValue) return

  const heightMm = store.measureElementHeight(sel.value, resolved.displayValue)
  if (heightMm !== null) {
    updateNumber('height', heightMm)
  }
}

function onSaveSmartTablePtl(ptl) {
  if (!sel.value) return
  store.updateElement(sel.value.id, { ptl })
}

const typeLabels = {
  text: 'Statischer Text',
  field: 'Datenfeld',
  attribute: 'Attribut',
  image: 'Produktbild',
  shape: 'Form / Rahmen',
  variant_table: 'Variantentabelle',
  relation_table: 'Beziehungstabelle',
  attribute_table: 'Attribut-Tabelle',
  smart_table: 'Smart Table',
}
</script>

<template>
  <div class="p-3 space-y-4">
    <div class="text-xs font-semibold text-[var(--color-text-primary)]">Eigenschaften</div>

    <!-- No selection -->
    <div v-if="!sel && store.selectedElementIds.size === 0" class="text-center py-8">
      <Settings class="w-8 h-8 mx-auto mb-2 text-[var(--color-text-tertiary)]" :stroke-width="1.25" />
      <p class="text-[11px] text-[var(--color-text-tertiary)]">
        Wähle ein Element auf dem Canvas, um die Eigenschaften zu bearbeiten.
      </p>
    </div>

    <!-- Multi-selection info -->
    <div v-else-if="store.selectedElementIds.size > 1" class="space-y-3">
      <div class="text-[11px] font-semibold text-[var(--color-accent)]">
        {{ store.selectedElementIds.size }} Elemente ausgewählt
      </div>
      <p class="text-[10px] text-[var(--color-text-tertiary)]">
        Ziehe zum Verschieben, oder nutze Pfeiltasten. Strg+C zum Kopieren, Strg+V zum Einfügen.
      </p>
      <div class="flex flex-wrap gap-1">
        <button class="pim-btn pim-btn-secondary text-[10px] px-2 py-1" @click="store.copySelectedElements()">
          <Copy class="w-3 h-3" :stroke-width="2" /> Kopieren
        </button>
        <button
          class="pim-btn text-[10px] px-2 py-1 bg-[var(--color-error-light)] text-[var(--color-error)]"
          @click="store.removeSelectedElements()"
        >
          <Trash2 class="w-3 h-3" :stroke-width="2" /> Löschen
        </button>
      </div>
    </div>

    <!-- Element Properties -->
    <div v-if="sel && store.selectedElementIds.size <= 1" class="space-y-3">
      <div class="text-[11px] font-semibold text-[var(--color-accent)]">
        {{ typeLabels[sel.type] || sel.type }}
      </div>

      <!-- Position & Size -->
      <div class="border-b border-[var(--color-border)] pb-3">
        <div class="flex items-center justify-between mb-2">
          <div class="text-[10px] font-semibold text-[var(--color-text-tertiary)]">Position & Größe (mm)</div>
          <button
            v-if="canAutofit"
            class="pim-btn pim-btn-secondary text-[9px] px-1.5 py-0.5"
            @click="autofitHeight"
            title="Höhe automatisch an Inhalt anpassen"
          >
            Autofit Höhe
          </button>
        </div>
        <div class="grid grid-cols-2 gap-2">
          <div>
            <label class="block text-[9px] text-[var(--color-text-tertiary)] mb-0.5">X</label>
            <input type="number" :value="sel.x" class="pim-input text-xs w-full" step="1" min="0" @input="updateNumber('x', $event.target.value)" />
          </div>
          <div>
            <label class="block text-[9px] text-[var(--color-text-tertiary)] mb-0.5">Y</label>
            <input type="number" :value="sel.y" class="pim-input text-xs w-full" step="1" min="0" @input="updateNumber('y', $event.target.value)" />
          </div>
          <div>
            <label class="block text-[9px] text-[var(--color-text-tertiary)] mb-0.5">Breite</label>
            <input type="number" :value="sel.width" class="pim-input text-xs w-full" step="1" min="5" @input="updateNumber('width', $event.target.value)" />
          </div>
          <div>
            <label class="block text-[9px] text-[var(--color-text-tertiary)] mb-0.5">Höhe</label>
            <input type="number" :value="sel.height" class="pim-input text-xs w-full" step="1" min="5" @input="updateNumber('height', $event.target.value)" />
          </div>
        </div>
      </div>

      <!-- TEXT content -->
      <template v-if="sel.type === 'text'">
        <div>
          <label class="block text-[10px] font-medium text-[var(--color-text-tertiary)] mb-0.5">Inhalt</label>
          <textarea
            :value="sel.content"
            class="pim-input text-xs w-full"
            rows="3"
            placeholder="Text eingeben..."
            @input="updateElement('content', $event.target.value)"
          />
        </div>
      </template>

      <!-- FIELD -->
      <template v-if="sel.type === 'field'">
        <div>
          <label class="block text-[10px] font-medium text-[var(--color-text-tertiary)] mb-0.5">Feld</label>
          <select
            :value="sel.field"
            class="pim-input text-xs w-full"
            @change="updateElement('field', $event.target.value)"
          >
            <option v-for="f in (store.availableFields?.base_fields || [])" :key="f.field" :value="f.field">{{ f.label_de }}</option>
          </select>
        </div>
        <div>
          <label class="block text-[10px] font-medium text-[var(--color-text-tertiary)] mb-0.5">Label</label>
          <input
            :value="sel.label"
            class="pim-input text-xs w-full"
            @input="updateElement('label', $event.target.value)"
          />
        </div>
        <label class="flex items-center gap-2 text-[11px] cursor-pointer text-[var(--color-text-secondary)]">
          <input type="checkbox" :checked="sel.showLabel" class="rounded" @change="updateElement('showLabel', $event.target.checked)" />
          Label anzeigen
        </label>
      </template>

      <!-- ATTRIBUTE -->
      <template v-if="sel.type === 'attribute'">
        <div>
          <label class="block text-[10px] font-medium text-[var(--color-text-tertiary)] mb-0.5">Attribut</label>
          <select
            :value="sel.attributeId"
            class="pim-input text-xs w-full"
            @change="updateElement('attributeId', $event.target.value)"
          >
            <option v-for="a in (store.availableFields?.attributes || [])" :key="a.attributeId" :value="a.attributeId">
              {{ a.label_de }} ({{ a.technical_name }})
            </option>
          </select>
        </div>
        <div>
          <label class="block text-[10px] font-medium text-[var(--color-text-tertiary)] mb-0.5">Label</label>
          <input
            :value="sel.label"
            class="pim-input text-xs w-full"
            @input="updateElement('label', $event.target.value)"
          />
        </div>
        <div class="space-y-1">
          <label class="flex items-center gap-2 text-[11px] cursor-pointer text-[var(--color-text-secondary)]">
            <input type="checkbox" :checked="sel.showLabel" class="rounded" @change="updateElement('showLabel', $event.target.checked)" />
            Label anzeigen
          </label>
          <label class="flex items-center gap-2 text-[11px] cursor-pointer text-[var(--color-text-secondary)]">
            <input type="checkbox" :checked="sel.showValue !== false" class="rounded" @change="updateElement('showValue', $event.target.checked)" />
            Wert anzeigen
          </label>
          <label class="flex items-center gap-2 text-[11px] cursor-pointer text-[var(--color-text-secondary)]">
            <input type="checkbox" :checked="sel.showUnit" class="rounded" @change="updateElement('showUnit', $event.target.checked)" />
            Einheit anzeigen
          </label>
        </div>
      </template>

      <!-- IMAGE -->
      <template v-if="sel.type === 'image'">
        <div>
          <label class="block text-[10px] font-medium text-[var(--color-text-tertiary)] mb-0.5">Quelle</label>
          <select :value="sel.source" class="pim-input text-xs w-full" @change="updateElement('source', $event.target.value)">
            <option value="primary">Hauptbild</option>
            <option value="all">Alle Bilder</option>
          </select>
        </div>
      </template>

      <!-- VARIANT TABLE -->
      <template v-if="sel.type === 'variant_table'">
        <div>
          <div class="text-[10px] font-semibold text-[var(--color-text-tertiary)] mb-2">Spalten</div>
          <div class="space-y-1">
            <label class="flex items-center gap-2 text-[11px] cursor-pointer text-[var(--color-text-secondary)]">
              <input type="checkbox" :checked="(sel.columns || []).includes('sku')" class="rounded" @change="toggleColumn('sku')" />
              SKU
            </label>
            <label class="flex items-center gap-2 text-[11px] cursor-pointer text-[var(--color-text-secondary)]">
              <input type="checkbox" :checked="(sel.columns || []).includes('name')" class="rounded" @change="toggleColumn('name')" />
              Name
            </label>
            <label class="flex items-center gap-2 text-[11px] cursor-pointer text-[var(--color-text-secondary)]">
              <input type="checkbox" :checked="(sel.columns || []).includes('variant_attributes')" class="rounded" @change="toggleColumn('variant_attributes')" />
              Variantenattribute
            </label>
          </div>
        </div>
        <!-- Column Widths -->
        <div v-if="currentTableHeaders.length > 0" class="border-t border-[var(--color-border)] pt-3">
          <div class="text-[10px] font-semibold text-[var(--color-text-tertiary)] mb-2">Spaltenbreiten (%)</div>
          <div class="space-y-1">
            <div v-for="(header, hi) in currentTableHeaders" :key="hi" class="flex items-center gap-2">
              <span class="text-[10px] text-[var(--color-text-secondary)] w-20 truncate" :title="header">{{ header }}</span>
              <input type="number" :value="(sel.columnWidths || [])[hi] || ''" class="pim-input text-xs w-16" min="0" max="100" placeholder="auto" @input="updateColumnWidth(hi, $event.target.value)" />
              <span class="text-[9px] text-[var(--color-text-tertiary)]">%</span>
            </div>
          </div>
        </div>
        <div class="border-t border-[var(--color-border)] pt-3">
          <div class="text-[10px] font-semibold text-[var(--color-text-tertiary)] mb-2">Tabellen-Styling</div>
          <div class="space-y-2">
            <div class="flex items-center justify-between">
              <span class="text-[11px] text-[var(--color-text-secondary)]">Header-Hintergrund</span>
              <input type="color" :value="sel.tableStyle?.headerBg || '#f3f4f6'" class="w-6 h-5 rounded border border-[var(--color-border)] cursor-pointer" @input="updateTableStyle('headerBg', $event.target.value)" />
            </div>
            <div class="flex items-center justify-between">
              <span class="text-[11px] text-[var(--color-text-secondary)]">Header-Textfarbe</span>
              <input type="color" :value="sel.tableStyle?.headerColor || '#374151'" class="w-6 h-5 rounded border border-[var(--color-border)] cursor-pointer" @input="updateTableStyle('headerColor', $event.target.value)" />
            </div>
            <div class="flex items-center justify-between">
              <span class="text-[11px] text-[var(--color-text-secondary)]">Rahmenfarbe</span>
              <input type="color" :value="sel.tableStyle?.borderColor || '#e5e7eb'" class="w-6 h-5 rounded border border-[var(--color-border)] cursor-pointer" @input="updateTableStyle('borderColor', $event.target.value)" />
            </div>
            <div class="flex items-center justify-between">
              <span class="text-[11px] text-[var(--color-text-secondary)]">Alternierende Zeilen</span>
              <input type="color" :value="sel.tableStyle?.alternateRowBg || '#f9fafb'" class="w-6 h-5 rounded border border-[var(--color-border)] cursor-pointer" @input="updateTableStyle('alternateRowBg', $event.target.value)" />
            </div>
            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="block text-[9px] text-[var(--color-text-tertiary)] mb-0.5">Schriftgröße (pt)</label>
                <input type="number" :value="sel.tableStyle?.fontSize || 8" class="pim-input text-xs w-full" min="6" max="14" @input="updateTableStyle('fontSize', parseInt($event.target.value) || 8)" />
              </div>
              <div>
                <label class="block text-[9px] text-[var(--color-text-tertiary)] mb-0.5">Header-Größe (pt)</label>
                <input type="number" :value="sel.tableStyle?.headerFontSize || 8" class="pim-input text-xs w-full" min="6" max="14" @input="updateTableStyle('headerFontSize', parseInt($event.target.value) || 8)" />
              </div>
            </div>
          </div>
        </div>
      </template>

      <!-- RELATION TABLE -->
      <template v-if="sel.type === 'relation_table'">
        <div>
          <label class="block text-[10px] font-medium text-[var(--color-text-tertiary)] mb-0.5">Beziehungstyp</label>
          <select :value="sel.relationTypeId || ''" class="pim-input text-xs w-full" @change="updateElement('relationTypeId', $event.target.value || null)">
            <option value="">Alle Beziehungen</option>
            <option v-for="rt in relationTypes" :key="rt.id" :value="rt.id">{{ rt.name_de || rt.technical_name }}</option>
          </select>
        </div>
        <div>
          <div class="text-[10px] font-semibold text-[var(--color-text-tertiary)] mb-2">Spalten</div>
          <div class="space-y-1">
            <label class="flex items-center gap-2 text-[11px] cursor-pointer text-[var(--color-text-secondary)]">
              <input type="checkbox" :checked="(sel.columns || []).includes('sku')" class="rounded" @change="toggleColumn('sku')" />
              SKU
            </label>
            <label class="flex items-center gap-2 text-[11px] cursor-pointer text-[var(--color-text-secondary)]">
              <input type="checkbox" :checked="(sel.columns || []).includes('name')" class="rounded" @change="toggleColumn('name')" />
              Name
            </label>
            <label class="flex items-center gap-2 text-[11px] cursor-pointer text-[var(--color-text-secondary)]">
              <input type="checkbox" :checked="(sel.columns || []).includes('ean')" class="rounded" @change="toggleColumn('ean')" />
              EAN
            </label>
            <label class="flex items-center gap-2 text-[11px] cursor-pointer text-[var(--color-text-secondary)]">
              <input type="checkbox" :checked="(sel.columns || []).includes('relation_type')" class="rounded" @change="toggleColumn('relation_type')" />
              Beziehungsart
            </label>
            <label class="flex items-center gap-2 text-[11px] cursor-pointer text-[var(--color-text-secondary)]">
              <input type="checkbox" :checked="(sel.columns || []).includes('relation_attributes')" class="rounded" @change="toggleColumn('relation_attributes')" />
              Beziehungsattribute
            </label>
            <label class="flex items-center gap-2 text-[11px] cursor-pointer text-[var(--color-text-secondary)]">
              <input type="checkbox" :checked="(sel.columns || []).includes('product_attributes')" class="rounded" @change="toggleColumn('product_attributes')" />
              Produktattribute (Zielprodukt)
            </label>
          </div>
        </div>
        <!-- Product Attribute Selection -->
        <div v-if="(sel.columns || []).includes('product_attributes')">
          <div class="text-[10px] font-semibold text-[var(--color-text-tertiary)] mb-1">Produktattribute auswählen</div>
          <input v-model="attrSearchQuery" class="pim-input text-[10px] w-full mb-1.5" placeholder="Attribute suchen..." />
          <div class="max-h-32 overflow-y-auto space-y-0.5 border border-[var(--color-border)] rounded p-1.5">
            <label
              v-for="attr in filteredProductAttributes"
              :key="attr.id"
              class="flex items-center gap-1.5 text-[10px] cursor-pointer text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)]"
            >
              <input type="checkbox" :checked="(sel.productAttributeIds || []).includes(attr.id)" class="rounded" style="width: 12px; height: 12px;" @change="toggleProductAttribute(attr.id)" />
              <span class="truncate">{{ attr.name_de || attr.technical_name }}</span>
            </label>
          </div>
          <div v-if="(sel.productAttributeIds || []).length > 0" class="mt-1">
            <div class="text-[9px] text-[var(--color-text-tertiary)]">{{ (sel.productAttributeIds || []).length }} Attribute ausgewählt</div>
          </div>
        </div>
        <!-- Sorting -->
        <div class="border-t border-[var(--color-border)] pt-3">
          <div class="text-[10px] font-semibold text-[var(--color-text-tertiary)] mb-2">Sortierung</div>
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="block text-[9px] text-[var(--color-text-tertiary)] mb-0.5">Sortieren nach</label>
              <select :value="sel.sortBy || ''" class="pim-input text-xs w-full" @change="updateElement('sortBy', $event.target.value || null)">
                <option value="">Standard (Reihenfolge)</option>
                <option value="relation_type">Beziehungsart</option>
                <option value="name">Name</option>
                <option value="sku">SKU</option>
              </select>
            </div>
            <div>
              <label class="block text-[9px] text-[var(--color-text-tertiary)] mb-0.5">Richtung</label>
              <select :value="sel.sortDirection || 'asc'" class="pim-input text-xs w-full" @change="updateElement('sortDirection', $event.target.value)">
                <option value="asc">Aufsteigend</option>
                <option value="desc">Absteigend</option>
              </select>
            </div>
          </div>
        </div>
        <!-- Column Widths -->
        <div v-if="currentTableHeaders.length > 0" class="border-t border-[var(--color-border)] pt-3">
          <div class="text-[10px] font-semibold text-[var(--color-text-tertiary)] mb-2">Spaltenbreiten (%)</div>
          <div class="space-y-1">
            <div v-for="(header, hi) in currentTableHeaders" :key="hi" class="flex items-center gap-2">
              <span class="text-[10px] text-[var(--color-text-secondary)] w-20 truncate" :title="header">{{ header }}</span>
              <input type="number" :value="(sel.columnWidths || [])[hi] || ''" class="pim-input text-xs w-16" min="0" max="100" placeholder="auto" @input="updateColumnWidth(hi, $event.target.value)" />
              <span class="text-[9px] text-[var(--color-text-tertiary)]">%</span>
            </div>
          </div>
        </div>
        <div class="border-t border-[var(--color-border)] pt-3">
          <div class="text-[10px] font-semibold text-[var(--color-text-tertiary)] mb-2">Tabellen-Styling</div>
          <div class="space-y-2">
            <div class="flex items-center justify-between">
              <span class="text-[11px] text-[var(--color-text-secondary)]">Header-Hintergrund</span>
              <input type="color" :value="sel.tableStyle?.headerBg || '#f3f4f6'" class="w-6 h-5 rounded border border-[var(--color-border)] cursor-pointer" @input="updateTableStyle('headerBg', $event.target.value)" />
            </div>
            <div class="flex items-center justify-between">
              <span class="text-[11px] text-[var(--color-text-secondary)]">Header-Textfarbe</span>
              <input type="color" :value="sel.tableStyle?.headerColor || '#374151'" class="w-6 h-5 rounded border border-[var(--color-border)] cursor-pointer" @input="updateTableStyle('headerColor', $event.target.value)" />
            </div>
            <div class="flex items-center justify-between">
              <span class="text-[11px] text-[var(--color-text-secondary)]">Rahmenfarbe</span>
              <input type="color" :value="sel.tableStyle?.borderColor || '#e5e7eb'" class="w-6 h-5 rounded border border-[var(--color-border)] cursor-pointer" @input="updateTableStyle('borderColor', $event.target.value)" />
            </div>
            <div class="flex items-center justify-between">
              <span class="text-[11px] text-[var(--color-text-secondary)]">Alternierende Zeilen</span>
              <input type="color" :value="sel.tableStyle?.alternateRowBg || '#f9fafb'" class="w-6 h-5 rounded border border-[var(--color-border)] cursor-pointer" @input="updateTableStyle('alternateRowBg', $event.target.value)" />
            </div>
            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="block text-[9px] text-[var(--color-text-tertiary)] mb-0.5">Schriftgröße (pt)</label>
                <input type="number" :value="sel.tableStyle?.fontSize || 8" class="pim-input text-xs w-full" min="6" max="14" @input="updateTableStyle('fontSize', parseInt($event.target.value) || 8)" />
              </div>
              <div>
                <label class="block text-[9px] text-[var(--color-text-tertiary)] mb-0.5">Header-Größe (pt)</label>
                <input type="number" :value="sel.tableStyle?.headerFontSize || 8" class="pim-input text-xs w-full" min="6" max="14" @input="updateTableStyle('headerFontSize', parseInt($event.target.value) || 8)" />
              </div>
            </div>
          </div>
        </div>
      </template>

      <!-- ATTRIBUTE TABLE -->
      <template v-if="sel.type === 'attribute_table'">
        <div>
          <label class="block text-[10px] font-medium text-[var(--color-text-tertiary)] mb-0.5">Quelle</label>
          <select :value="sel.sourceMode || 'group'" class="pim-input text-xs w-full" @change="updateElement('sourceMode', $event.target.value)">
            <option value="group">Attributgruppe</option>
            <option value="individual">Einzelne Attribute</option>
          </select>
        </div>
        <!-- Group mode -->
        <div v-if="(sel.sourceMode || 'group') === 'group'">
          <label class="block text-[10px] font-medium text-[var(--color-text-tertiary)] mb-0.5">Attributgruppe</label>
          <select :value="sel.attributeGroupId || ''" class="pim-input text-xs w-full" @change="updateElement('attributeGroupId', $event.target.value || null)">
            <option value="">-- Bitte wählen --</option>
            <option v-for="at in attributeTypes" :key="at.id" :value="at.id">{{ at.name_de || at.technical_name }}</option>
          </select>
        </div>
        <!-- Individual mode -->
        <div v-if="sel.sourceMode === 'individual'">
          <div class="text-[10px] font-semibold text-[var(--color-text-tertiary)] mb-1">Attribute auswählen</div>
          <input v-model="attrTableSearchQuery" class="pim-input text-[10px] w-full mb-1.5" placeholder="Attribute suchen..." />
          <div class="max-h-40 overflow-y-auto space-y-0.5 border border-[var(--color-border)] rounded p-1.5">
            <label
              v-for="attr in filteredAttrTableAttributes"
              :key="attr.id"
              class="flex items-center gap-1.5 text-[10px] cursor-pointer text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)]"
            >
              <input type="checkbox" :checked="(sel.attributeIds || []).includes(attr.id)" class="rounded" style="width: 12px; height: 12px;" @change="toggleAttrTableAttribute(attr.id)" />
              <span class="truncate">{{ attr.name_de || attr.technical_name }}</span>
            </label>
          </div>
          <div v-if="(sel.attributeIds || []).length > 0" class="mt-1">
            <div class="text-[9px] text-[var(--color-text-tertiary)]">{{ (sel.attributeIds || []).length }} Attribute ausgewählt</div>
          </div>
        </div>
        <!-- Table Styling -->
        <div class="border-t border-[var(--color-border)] pt-3">
          <div class="text-[10px] font-semibold text-[var(--color-text-tertiary)] mb-2">Tabellen-Styling</div>
          <div class="space-y-2">
            <div class="flex items-center justify-between">
              <span class="text-[11px] text-[var(--color-text-secondary)]">Header-Hintergrund</span>
              <input type="color" :value="sel.tableStyle?.headerBg || '#f3f4f6'" class="w-6 h-5 rounded border border-[var(--color-border)] cursor-pointer" @input="updateTableStyle('headerBg', $event.target.value)" />
            </div>
            <div class="flex items-center justify-between">
              <span class="text-[11px] text-[var(--color-text-secondary)]">Header-Textfarbe</span>
              <input type="color" :value="sel.tableStyle?.headerColor || '#374151'" class="w-6 h-5 rounded border border-[var(--color-border)] cursor-pointer" @input="updateTableStyle('headerColor', $event.target.value)" />
            </div>
            <div class="flex items-center justify-between">
              <span class="text-[11px] text-[var(--color-text-secondary)]">Rahmenfarbe</span>
              <input type="color" :value="sel.tableStyle?.borderColor || '#e5e7eb'" class="w-6 h-5 rounded border border-[var(--color-border)] cursor-pointer" @input="updateTableStyle('borderColor', $event.target.value)" />
            </div>
            <div class="flex items-center justify-between">
              <span class="text-[11px] text-[var(--color-text-secondary)]">Alternierende Zeilen</span>
              <input type="color" :value="sel.tableStyle?.alternateRowBg || '#f9fafb'" class="w-6 h-5 rounded border border-[var(--color-border)] cursor-pointer" @input="updateTableStyle('alternateRowBg', $event.target.value)" />
            </div>
            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="block text-[9px] text-[var(--color-text-tertiary)] mb-0.5">Schriftgröße (pt)</label>
                <input type="number" :value="sel.tableStyle?.fontSize || 8" class="pim-input text-xs w-full" min="6" max="14" @input="updateTableStyle('fontSize', parseInt($event.target.value) || 8)" />
              </div>
              <div>
                <label class="block text-[9px] text-[var(--color-text-tertiary)] mb-0.5">Header-Größe (pt)</label>
                <input type="number" :value="sel.tableStyle?.headerFontSize || 8" class="pim-input text-xs w-full" min="6" max="14" @input="updateTableStyle('headerFontSize', parseInt($event.target.value) || 8)" />
              </div>
            </div>
          </div>
        </div>
      </template>

      <!-- SMART TABLE -->
      <template v-if="sel.type === 'smart_table'">
        <div>
          <label class="block text-[10px] font-medium text-[var(--color-text-tertiary)] mb-0.5">Datenquelle</label>
          <select :value="sel.bind || 'variants'" class="pim-input text-xs w-full" @change="updateElement('bind', $event.target.value)">
            <option value="variants">Varianten</option>
            <option value="relations">Beziehungen</option>
            <option value="attributes">Attribute</option>
          </select>
        </div>
        <div>
          <div class="text-[10px] text-[var(--color-text-tertiary)] mb-1">
            Modus: <span class="font-semibold text-[var(--color-text-secondary)]">{{ (sel.ptl?.mode || 'normal') === 'pivot' ? 'Pivot-Tabelle' : 'Normale Tabelle' }}</span>
          </div>
          <div class="text-[10px] text-[var(--color-text-tertiary)] mb-2">
            Spalten: <span class="font-semibold text-[var(--color-text-secondary)]">{{ (sel.ptl?.columns || []).length || '–' }}</span>
          </div>
          <button
            class="pim-btn pim-btn-primary text-[11px] w-full py-1.5"
            @click="smartTableEditorOpen = true"
          >
            Tabelle konfigurieren...
          </button>
        </div>
      </template>

      <SmartTableEditor
        v-model="smartTableEditorOpen"
        :ptl="sel?.ptl || {}"
        :bind="sel?.bind || 'variants'"
        @save="onSaveSmartTablePtl"
      />

      <!-- Typography (for text, field, attribute) -->
      <template v-if="['text', 'field', 'attribute'].includes(sel.type)">
        <div class="border-t border-[var(--color-border)] pt-3">
          <div class="text-[10px] font-semibold text-[var(--color-text-tertiary)] mb-2">Typografie</div>

          <div class="space-y-2">
            <div>
              <label class="block text-[9px] text-[var(--color-text-tertiary)] mb-0.5">Schriftart</label>
              <select :value="sel.style?.fontFamily || 'DejaVu Sans'" class="pim-input text-xs w-full" @change="updateStyle('fontFamily', $event.target.value)">
                <option v-for="f in fontFamilies" :key="f.value" :value="f.value">{{ f.label }}</option>
              </select>
            </div>

            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="block text-[9px] text-[var(--color-text-tertiary)] mb-0.5">Schriftgröße (pt)</label>
                <input type="number" :value="sel.style?.fontSize || 10" class="pim-input text-xs w-full" min="4" max="72" @input="updateStyle('fontSize', parseInt($event.target.value) || 10)" />
              </div>
              <div>
                <label class="block text-[9px] text-[var(--color-text-tertiary)] mb-0.5">Ausrichtung</label>
                <select :value="sel.style?.textAlign || 'left'" class="pim-input text-xs w-full" @change="updateStyle('textAlign', $event.target.value)">
                  <option value="left">Links</option>
                  <option value="center">Mitte</option>
                  <option value="right">Rechts</option>
                </select>
              </div>
            </div>

            <div class="flex items-center gap-3">
              <label class="flex items-center gap-1.5 text-[11px] cursor-pointer text-[var(--color-text-secondary)]">
                <input type="checkbox" :checked="sel.style?.fontWeight === 'bold'" class="rounded" @change="updateStyle('fontWeight', $event.target.checked ? 'bold' : 'normal')" />
                Fett
              </label>
              <label class="flex items-center gap-1.5 text-[11px] cursor-pointer text-[var(--color-text-secondary)]">
                <input type="checkbox" :checked="sel.style?.fontStyle === 'italic'" class="rounded" @change="updateStyle('fontStyle', $event.target.checked ? 'italic' : 'normal')" />
                Kursiv
              </label>
            </div>

            <div>
              <label class="block text-[9px] text-[var(--color-text-tertiary)] mb-0.5">Textfarbe</label>
              <input type="color" :value="sel.style?.color || '#000000'" class="w-8 h-6 rounded border border-[var(--color-border)]" @input="updateStyle('color', $event.target.value)" />
            </div>

            <div>
              <label class="block text-[9px] text-[var(--color-text-tertiary)] mb-0.5">Zeilenhöhe</label>
              <input type="number" :value="sel.style?.lineHeight || ''" class="pim-input text-xs w-full" min="0.5" max="4" step="0.1" placeholder="normal" @input="updateStyle('lineHeight', $event.target.value ? parseFloat($event.target.value) : null)" />
            </div>
          </div>
        </div>
      </template>

      <!-- Appearance (for all) -->
      <div class="border-t border-[var(--color-border)] pt-3">
        <div class="text-[10px] font-semibold text-[var(--color-text-tertiary)] mb-2">Darstellung</div>
        <div class="space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-[11px] text-[var(--color-text-secondary)]">Hintergrund</span>
            <div class="flex items-center gap-1.5">
              <input type="color" :value="sel.style?.backgroundColor || '#ffffff'" class="w-6 h-5 rounded border border-[var(--color-border)] cursor-pointer" @input="updateStyle('backgroundColor', $event.target.value)" />
              <button
                v-if="sel.style?.backgroundColor"
                class="text-[9px] text-[var(--color-text-tertiary)] hover:text-[var(--color-text-secondary)]"
                @click="updateStyle('backgroundColor', null)"
              >×</button>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="block text-[9px] text-[var(--color-text-tertiary)] mb-0.5">Rahmenstärke</label>
              <input type="number" :value="sel.style?.borderWidth || 0" class="pim-input text-xs w-full" min="0" max="10" @input="updateStyle('borderWidth', parseInt($event.target.value) || 0)" />
            </div>
            <div>
              <label class="block text-[9px] text-[var(--color-text-tertiary)] mb-0.5">Rahmenfarbe</label>
              <input type="color" :value="sel.style?.borderColor || '#000000'" class="w-8 h-6 rounded border border-[var(--color-border)]" @input="updateStyle('borderColor', $event.target.value)" />
            </div>
          </div>
          <div>
            <label class="block text-[9px] text-[var(--color-text-tertiary)] mb-0.5">Innenabstand (mm)</label>
            <input type="number" :value="sel.style?.padding || 0" class="pim-input text-xs w-full" min="0" max="20" @input="updateStyle('padding', parseInt($event.target.value) || 0)" />
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="border-t border-[var(--color-border)] pt-3 flex flex-wrap gap-1.5">
        <button class="pim-btn pim-btn-secondary text-[11px] px-2 py-1" @click="store.duplicateElement(sel.id)" title="Duplizieren">
          <Copy class="w-3 h-3" :stroke-width="2" />
          Duplizieren
        </button>
        <button class="pim-btn pim-btn-secondary text-[11px] px-2 py-1" @click="store.bringToFront(sel.id)" title="Nach vorne">
          <ArrowUpToLine class="w-3 h-3" :stroke-width="2" />
        </button>
        <button class="pim-btn pim-btn-secondary text-[11px] px-2 py-1" @click="store.sendToBack(sel.id)" title="Nach hinten">
          <ArrowDownToLine class="w-3 h-3" :stroke-width="2" />
        </button>
        <button class="pim-btn pim-btn-secondary text-[11px] px-2 py-1 text-[var(--color-error)]" @click="store.removeElement(sel.id)" title="Löschen">
          <Trash2 class="w-3 h-3" :stroke-width="2" />
          Löschen
        </button>
      </div>
    </div>
  </div>
</template>
