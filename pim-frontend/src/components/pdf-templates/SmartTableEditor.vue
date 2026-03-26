<script setup>
import { ref, computed, watch } from 'vue'
import {
  X, Plus, Trash2, GripVertical, ChevronDown, ChevronRight,
  ArrowUpDown, Columns3, Rows3, Table2,
} from 'lucide-vue-next'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  ptl: { type: Object, default: () => ({}) },
  bind: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue', 'save'])

// Lokale Kopie der PTL-Konfiguration
const localPtl = ref(createDefaultPtl())

watch(() => props.modelValue, (open) => {
  if (open) {
    localPtl.value = JSON.parse(JSON.stringify(props.ptl && Object.keys(props.ptl).length > 0 ? props.ptl : createDefaultPtl()))
  }
})

function createDefaultPtl() {
  return {
    mode: 'normal',
    columns: [],
    pivot: {
      rowField: '',
      colField: '',
      valueField: '',
      aggregation: 'first',
    },
    headerStyle: {
      backgroundColor: '#f3f4f6',
      color: '#374151',
      fontWeight: 700,
      fontSize: 9,
    },
    rowStyle: {
      fontSize: 8,
      padding: 4,
    },
    zebraStripes: true,
    zebraColor: '#f9fafb',
    borderColor: '#e5e7eb',
  }
}

// Verfügbare Felder basierend auf dem Bind
const availableFields = computed(() => {
  if (props.bind === 'variants') {
    return [
      { field: 'sku', label: 'SKU' },
      { field: 'name', label: 'Name' },
      { field: 'ean', label: 'EAN' },
      { field: 'status', label: 'Status' },
    ]
  }
  if (props.bind === 'relations') {
    return [
      { field: 'relation_type', label: 'Beziehungsart' },
      { field: 'sku', label: 'SKU' },
      { field: 'name', label: 'Name' },
      { field: 'ean', label: 'EAN' },
    ]
  }
  if (props.bind === 'attributes') {
    return [
      { field: 'name', label: 'Attributname' },
      { field: 'value', label: 'Wert' },
      { field: 'unit', label: 'Einheit' },
      { field: 'group', label: 'Gruppe' },
      { field: 'technical_name', label: 'Technischer Name' },
    ]
  }
  return []
})

const formatOptions = [
  { value: 'text', label: 'Text' },
  { value: 'number', label: 'Zahl' },
  { value: 'currency', label: 'Währung (€)' },
  { value: 'percent', label: 'Prozent (%)' },
]

const alignOptions = [
  { value: 'left', label: 'Links' },
  { value: 'center', label: 'Mitte' },
  { value: 'right', label: 'Rechts' },
]

const aggregationOptions = [
  { value: 'first', label: 'Erster Wert' },
  { value: 'sum', label: 'Summe' },
  { value: 'avg', label: 'Durchschnitt' },
  { value: 'count', label: 'Anzahl' },
  { value: 'min', label: 'Minimum' },
  { value: 'max', label: 'Maximum' },
]

// Spalte hinzufügen
function addColumn() {
  localPtl.value.columns.push({
    field: availableFields.value[0]?.field || '',
    label: availableFields.value[0]?.label || 'Spalte',
    width: '',
    align: 'left',
    format: 'text',
  })
}

// Gruppen-Spalte hinzufügen
function addGroupColumn() {
  localPtl.value.columns.push({
    label: 'Gruppe',
    children: [],
  })
}

// Spalte zu Gruppe hinzufügen
function addChildColumn(group) {
  if (!group.children) group.children = []
  group.children.push({
    field: availableFields.value[0]?.field || '',
    label: availableFields.value[0]?.label || 'Spalte',
    width: '',
    align: 'left',
    format: 'text',
  })
}

// Spalte entfernen
function removeColumn(index) {
  localPtl.value.columns.splice(index, 1)
}

// Kind-Spalte entfernen
function removeChildColumn(group, index) {
  group.children.splice(index, 1)
}

// Spalte nach oben/unten bewegen
function moveColumn(index, direction) {
  const cols = localPtl.value.columns
  const newIndex = index + direction
  if (newIndex < 0 || newIndex >= cols.length) return
  const temp = cols[index]
  cols[index] = cols[newIndex]
  cols[newIndex] = temp
}

function moveChildColumn(group, index, direction) {
  const cols = group.children
  const newIndex = index + direction
  if (newIndex < 0 || newIndex >= cols.length) return
  const temp = cols[index]
  cols[index] = cols[newIndex]
  cols[newIndex] = temp
}

// Erweiterte Spalten-Konfiguration toggle
const expandedColumns = ref(new Set())
function toggleExpand(index) {
  if (expandedColumns.value.has(index)) {
    expandedColumns.value.delete(index)
  } else {
    expandedColumns.value.add(index)
  }
}

// Speichern
function onSave() {
  emit('save', JSON.parse(JSON.stringify(localPtl.value)))
  emit('update:modelValue', false)
}

function onCancel() {
  emit('update:modelValue', false)
}

// Vorschau-Daten für den Editor
const previewHeaders = computed(() => {
  if (localPtl.value.mode === 'pivot') {
    const p = localPtl.value.pivot
    return [p.rowField || 'Zeile', 'Wert 1', 'Wert 2', 'Wert 3']
  }
  const headers = []
  collectPreviewHeaders(localPtl.value.columns, headers)
  return headers.length ? headers : ['Spalte 1', 'Spalte 2']
})

function collectPreviewHeaders(columns, headers) {
  for (const col of columns) {
    if (col.children?.length) {
      collectPreviewHeaders(col.children, headers)
    } else {
      headers.push(col.label || col.field || '?')
    }
  }
}

// Gruppierte Header-Zeilen für Vorschau
const previewGroupHeaders = computed(() => {
  if (localPtl.value.mode === 'pivot') return []
  const groups = []
  for (const col of localPtl.value.columns) {
    if (col.children?.length) {
      groups.push({ label: col.label, span: col.children.length })
    } else {
      groups.push({ label: null, span: 1 })
    }
  }
  return groups.some(g => g.label) ? groups : []
})
</script>

<template>
  <Teleport to="body">
    <div v-if="modelValue" class="fixed inset-0 z-50 flex items-center justify-center">
      <!-- Backdrop -->
      <div class="absolute inset-0 bg-black/50" @click="onCancel" />

      <!-- Dialog -->
      <div class="relative bg-[var(--color-bg-surface)] rounded-lg shadow-xl w-[800px] max-h-[90vh] flex flex-col">
        <!-- Header -->
        <div class="flex items-center justify-between px-5 py-3 border-b border-[var(--color-border)]">
          <div class="flex items-center gap-2">
            <Table2 class="w-4 h-4 text-[var(--color-accent)]" :stroke-width="2" />
            <span class="font-semibold text-sm text-[var(--color-text-primary)]">Smart Table konfigurieren</span>
          </div>
          <button class="p-1 rounded hover:bg-[var(--color-bg-hover)]" @click="onCancel">
            <X class="w-4 h-4" />
          </button>
        </div>

        <!-- Body -->
        <div class="flex-1 overflow-y-auto p-5 space-y-5">

          <!-- Modus -->
          <div>
            <div class="text-xs font-semibold text-[var(--color-text-secondary)] mb-2">Modus</div>
            <div class="flex gap-2">
              <button
                class="px-3 py-1.5 rounded text-xs font-medium border transition-colors"
                :class="localPtl.mode === 'normal'
                  ? 'bg-[var(--color-accent)] text-white border-[var(--color-accent)]'
                  : 'border-[var(--color-border)] text-[var(--color-text-secondary)] hover:bg-[var(--color-bg-hover)]'"
                @click="localPtl.mode = 'normal'"
              >
                <Rows3 class="w-3 h-3 inline mr-1" /> Normale Tabelle
              </button>
              <button
                class="px-3 py-1.5 rounded text-xs font-medium border transition-colors"
                :class="localPtl.mode === 'pivot'
                  ? 'bg-[var(--color-accent)] text-white border-[var(--color-accent)]'
                  : 'border-[var(--color-border)] text-[var(--color-text-secondary)] hover:bg-[var(--color-bg-hover)]'"
                @click="localPtl.mode = 'pivot'"
              >
                <ArrowUpDown class="w-3 h-3 inline mr-1" /> Pivot-Tabelle
              </button>
            </div>
          </div>

          <!-- NORMAL MODE: Spalten-Definitionen -->
          <template v-if="localPtl.mode === 'normal'">
            <div>
              <div class="flex items-center justify-between mb-2">
                <div class="text-xs font-semibold text-[var(--color-text-secondary)]">Spalten</div>
                <div class="flex gap-1">
                  <button class="pim-btn pim-btn-secondary text-[10px] px-2 py-1" @click="addColumn">
                    <Plus class="w-3 h-3" /> Spalte
                  </button>
                  <button class="pim-btn pim-btn-secondary text-[10px] px-2 py-1" @click="addGroupColumn">
                    <Columns3 class="w-3 h-3" /> Gruppe
                  </button>
                </div>
              </div>

              <div v-if="localPtl.columns.length === 0" class="text-center py-6 text-[var(--color-text-tertiary)] text-xs border border-dashed border-[var(--color-border)] rounded">
                Noch keine Spalten definiert. Klicke auf "Spalte" oder "Gruppe" um zu beginnen.
              </div>

              <div class="space-y-2">
                <div v-for="(col, ci) in localPtl.columns" :key="ci" class="border border-[var(--color-border)] rounded">
                  <!-- Gruppen-Spalte -->
                  <template v-if="col.children !== undefined">
                    <div class="flex items-center gap-2 px-3 py-2 bg-[var(--color-bg)]">
                      <button class="cursor-grab text-[var(--color-text-tertiary)]">
                        <GripVertical class="w-3 h-3" />
                      </button>
                      <Columns3 class="w-3 h-3 text-[var(--color-accent)]" />
                      <input
                        v-model="col.label"
                        class="pim-input text-xs flex-1"
                        placeholder="Gruppenname..."
                      />
                      <div class="flex gap-0.5">
                        <button class="p-1 rounded hover:bg-[var(--color-bg-hover)] text-[var(--color-text-tertiary)]" @click="moveColumn(ci, -1)" :disabled="ci === 0">
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                        </button>
                        <button class="p-1 rounded hover:bg-[var(--color-bg-hover)] text-[var(--color-text-tertiary)]" @click="moveColumn(ci, 1)" :disabled="ci === localPtl.columns.length - 1">
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                      </div>
                      <button class="p-1 rounded hover:bg-red-50 text-red-400 hover:text-red-600" @click="removeColumn(ci)">
                        <Trash2 class="w-3 h-3" />
                      </button>
                    </div>
                    <!-- Kinder der Gruppe -->
                    <div class="px-5 py-2 space-y-1.5 border-t border-[var(--color-border)]">
                      <div v-for="(child, chi) in col.children" :key="chi" class="flex items-center gap-2">
                        <GripVertical class="w-3 h-3 text-[var(--color-text-tertiary)] cursor-grab" />
                        <select v-model="child.field" class="pim-input text-[10px] w-28">
                          <option v-for="f in availableFields" :key="f.field" :value="f.field">{{ f.label }}</option>
                        </select>
                        <input v-model="child.label" class="pim-input text-[10px] flex-1" placeholder="Label..." />
                        <select v-model="child.align" class="pim-input text-[10px] w-16">
                          <option v-for="a in alignOptions" :key="a.value" :value="a.value">{{ a.label }}</option>
                        </select>
                        <select v-model="child.format" class="pim-input text-[10px] w-20">
                          <option v-for="f in formatOptions" :key="f.value" :value="f.value">{{ f.label }}</option>
                        </select>
                        <input v-model="child.width" class="pim-input text-[10px] w-14" placeholder="auto" title="Breite (z.B. 25% oder 80px)" />
                        <div class="flex gap-0.5">
                          <button class="p-0.5 rounded hover:bg-[var(--color-bg-hover)] text-[var(--color-text-tertiary)]" @click="moveChildColumn(col, chi, -1)" :disabled="chi === 0">
                            <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                          </button>
                          <button class="p-0.5 rounded hover:bg-[var(--color-bg-hover)] text-[var(--color-text-tertiary)]" @click="moveChildColumn(col, chi, 1)" :disabled="chi === col.children.length - 1">
                            <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                          </button>
                        </div>
                        <button class="p-0.5 rounded hover:bg-red-50 text-red-400 hover:text-red-600" @click="removeChildColumn(col, chi)">
                          <Trash2 class="w-3 h-3" />
                        </button>
                      </div>
                      <button class="text-[10px] text-[var(--color-accent)] hover:underline" @click="addChildColumn(col)">
                        + Unter-Spalte hinzufügen
                      </button>
                    </div>
                  </template>

                  <!-- Einfache Spalte -->
                  <template v-else>
                    <div class="flex items-center gap-2 px-3 py-2">
                      <button class="cursor-grab text-[var(--color-text-tertiary)]">
                        <GripVertical class="w-3 h-3" />
                      </button>
                      <select v-model="col.field" class="pim-input text-[10px] w-28">
                        <option v-for="f in availableFields" :key="f.field" :value="f.field">{{ f.label }}</option>
                      </select>
                      <input v-model="col.label" class="pim-input text-[10px] flex-1" placeholder="Label..." />
                      <select v-model="col.align" class="pim-input text-[10px] w-16">
                        <option v-for="a in alignOptions" :key="a.value" :value="a.value">{{ a.label }}</option>
                      </select>
                      <select v-model="col.format" class="pim-input text-[10px] w-20">
                        <option v-for="f in formatOptions" :key="f.value" :value="f.value">{{ f.label }}</option>
                      </select>
                      <input v-model="col.width" class="pim-input text-[10px] w-14" placeholder="auto" title="Breite (z.B. 25% oder 80px)" />
                      <div class="flex gap-0.5">
                        <button class="p-1 rounded hover:bg-[var(--color-bg-hover)] text-[var(--color-text-tertiary)]" @click="moveColumn(ci, -1)" :disabled="ci === 0">
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                        </button>
                        <button class="p-1 rounded hover:bg-[var(--color-bg-hover)] text-[var(--color-text-tertiary)]" @click="moveColumn(ci, 1)" :disabled="ci === localPtl.columns.length - 1">
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                      </div>
                      <button class="p-1 rounded hover:bg-red-50 text-red-400 hover:text-red-600" @click="removeColumn(ci)">
                        <Trash2 class="w-3 h-3" />
                      </button>
                    </div>
                  </template>
                </div>
              </div>
            </div>
          </template>

          <!-- PIVOT MODE -->
          <template v-if="localPtl.mode === 'pivot'">
            <div class="space-y-3">
              <div class="text-xs font-semibold text-[var(--color-text-secondary)]">Pivot-Konfiguration</div>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-0.5">Zeilen-Feld (Rows)</label>
                  <select v-model="localPtl.pivot.rowField" class="pim-input text-xs w-full">
                    <option value="">-- Feld wählen --</option>
                    <option v-for="f in availableFields" :key="f.field" :value="f.field">{{ f.label }}</option>
                  </select>
                </div>
                <div>
                  <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-0.5">Spalten-Feld (Columns)</label>
                  <select v-model="localPtl.pivot.colField" class="pim-input text-xs w-full">
                    <option value="">-- Feld wählen --</option>
                    <option v-for="f in availableFields" :key="f.field" :value="f.field">{{ f.label }}</option>
                  </select>
                </div>
                <div>
                  <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-0.5">Werte-Feld (Values)</label>
                  <select v-model="localPtl.pivot.valueField" class="pim-input text-xs w-full">
                    <option value="">-- Feld wählen --</option>
                    <option v-for="f in availableFields" :key="f.field" :value="f.field">{{ f.label }}</option>
                  </select>
                </div>
                <div>
                  <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-0.5">Aggregation</label>
                  <select v-model="localPtl.pivot.aggregation" class="pim-input text-xs w-full">
                    <option v-for="a in aggregationOptions" :key="a.value" :value="a.value">{{ a.label }}</option>
                  </select>
                </div>
              </div>
            </div>
          </template>

          <!-- Styling -->
          <div class="border-t border-[var(--color-border)] pt-4">
            <div class="text-xs font-semibold text-[var(--color-text-secondary)] mb-3">Styling</div>
            <div class="grid grid-cols-2 gap-x-6 gap-y-3">
              <div class="flex items-center justify-between">
                <span class="text-[11px] text-[var(--color-text-secondary)]">Header-Hintergrund</span>
                <input type="color" v-model="localPtl.headerStyle.backgroundColor" class="w-7 h-5 rounded border border-[var(--color-border)] cursor-pointer" />
              </div>
              <div class="flex items-center justify-between">
                <span class="text-[11px] text-[var(--color-text-secondary)]">Header-Textfarbe</span>
                <input type="color" v-model="localPtl.headerStyle.color" class="w-7 h-5 rounded border border-[var(--color-border)] cursor-pointer" />
              </div>
              <div class="flex items-center justify-between">
                <span class="text-[11px] text-[var(--color-text-secondary)]">Rahmenfarbe</span>
                <input type="color" v-model="localPtl.borderColor" class="w-7 h-5 rounded border border-[var(--color-border)] cursor-pointer" />
              </div>
              <div class="flex items-center justify-between">
                <span class="text-[11px] text-[var(--color-text-secondary)]">Zebra-Streifen Farbe</span>
                <input type="color" v-model="localPtl.zebraColor" class="w-7 h-5 rounded border border-[var(--color-border)] cursor-pointer" />
              </div>
              <div>
                <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-0.5">Header-Schriftgröße (pt)</label>
                <input type="number" v-model.number="localPtl.headerStyle.fontSize" class="pim-input text-xs w-full" min="6" max="16" />
              </div>
              <div>
                <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-0.5">Zeilen-Schriftgröße (pt)</label>
                <input type="number" v-model.number="localPtl.rowStyle.fontSize" class="pim-input text-xs w-full" min="6" max="16" />
              </div>
              <label class="flex items-center gap-2 text-[11px] cursor-pointer text-[var(--color-text-secondary)]">
                <input type="checkbox" v-model="localPtl.zebraStripes" class="rounded" />
                Zebra-Streifen
              </label>
            </div>
          </div>

          <!-- Live-Vorschau -->
          <div class="border-t border-[var(--color-border)] pt-4">
            <div class="text-xs font-semibold text-[var(--color-text-secondary)] mb-2">Vorschau</div>
            <div class="border border-[var(--color-border)] rounded overflow-hidden">
              <table style="width: 100%; border-collapse: collapse;" :style="{ fontSize: localPtl.rowStyle.fontSize + 'pt' }">
                <thead>
                  <!-- Gruppen-Header -->
                  <tr v-if="previewGroupHeaders.length > 0"
                    :style="{ background: localPtl.headerStyle.backgroundColor, color: localPtl.headerStyle.color }"
                  >
                    <template v-for="(g, gi) in previewGroupHeaders" :key="gi">
                      <th
                        v-if="g.label"
                        :colspan="g.span"
                        :style="{
                          border: '1px solid ' + localPtl.borderColor,
                          padding: '3px 6px',
                          textAlign: 'center',
                          fontWeight: localPtl.headerStyle.fontWeight,
                          fontSize: localPtl.headerStyle.fontSize + 'pt',
                        }"
                      >{{ g.label }}</th>
                      <th
                        v-else
                        :rowspan="2"
                        :style="{
                          border: '1px solid ' + localPtl.borderColor,
                          padding: '3px 6px',
                          textAlign: 'left',
                          fontWeight: localPtl.headerStyle.fontWeight,
                          fontSize: localPtl.headerStyle.fontSize + 'pt',
                        }"
                      >{{ previewHeaders[gi] || '' }}</th>
                    </template>
                  </tr>
                  <!-- Spalten-Header -->
                  <tr :style="{ background: localPtl.headerStyle.backgroundColor, color: localPtl.headerStyle.color }">
                    <template v-if="previewGroupHeaders.length > 0">
                      <template v-for="(col, ci) in localPtl.columns" :key="ci">
                        <template v-if="col.children?.length">
                          <th
                            v-for="(child, chi) in col.children"
                            :key="'c' + ci + '-' + chi"
                            :style="{
                              border: '1px solid ' + localPtl.borderColor,
                              padding: '3px 6px',
                              textAlign: child.align || 'left',
                              fontWeight: localPtl.headerStyle.fontWeight,
                              fontSize: localPtl.headerStyle.fontSize + 'pt',
                            }"
                          >{{ child.label || child.field }}</th>
                        </template>
                      </template>
                    </template>
                    <template v-else>
                      <th
                        v-for="(h, hi) in previewHeaders"
                        :key="hi"
                        :style="{
                          border: '1px solid ' + localPtl.borderColor,
                          padding: '3px 6px',
                          textAlign: 'left',
                          fontWeight: localPtl.headerStyle.fontWeight,
                          fontSize: localPtl.headerStyle.fontSize + 'pt',
                        }"
                      >{{ h }}</th>
                    </template>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="ri in 3"
                    :key="ri"
                    :style="{ background: localPtl.zebraStripes && ri % 2 === 0 ? localPtl.zebraColor : 'transparent' }"
                  >
                    <td
                      v-for="(h, hi) in previewHeaders"
                      :key="hi"
                      :style="{
                        border: '1px solid ' + localPtl.borderColor,
                        padding: localPtl.rowStyle.padding + 'px',
                      }"
                    >Beispiel</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="flex items-center justify-end gap-2 px-5 py-3 border-t border-[var(--color-border)]">
          <button class="pim-btn pim-btn-secondary text-xs px-4 py-1.5" @click="onCancel">
            Abbrechen
          </button>
          <button class="pim-btn pim-btn-primary text-xs px-4 py-1.5" @click="onSave">
            Übernehmen
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
