<script setup>
import { computed } from 'vue'
import { useExcelDesignerStore } from '@/stores/excelDesigner'

const store = useExcelDesignerStore()

const col = computed(() => store.selectedColumn)

function update(field, value) {
  if (!col.value || !store.selectedSheetId) return
  store.updateColumn(store.selectedSheetId, col.value.id, { [field]: value })
}

const dataTypeOptions = [
  { value: 'string', label: 'Text' },
  { value: 'number', label: 'Zahl' },
  { value: 'integer', label: 'Ganzzahl' },
  { value: 'boolean', label: 'Boolean' },
]

const arrayModeOptions = [
  { value: 'single', label: 'Nur erster Wert' },
  { value: 'join', label: 'Komma-getrennt' },
  { value: 'expand', label: 'Separate Spalten' },
]

const renderModeOptions = [
  { value: 'embedded', label: 'Eingebettetes Bild' },
  { value: 'url', label: 'Nur Bild-URL' },
]

const displayFieldOptions = [
  { value: 'sku', label: 'SKU' },
  { value: 'name', label: 'Name' },
  { value: 'ean', label: 'EAN' },
]
</script>

<template>
  <div v-if="col" class="pim-card p-4 space-y-3">
    <h3 class="text-xs font-semibold text-[var(--color-text-secondary)] uppercase tracking-wide">Spalten-Konfiguration</h3>

    <!-- Label -->
    <div>
      <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Spaltenüberschrift</label>
      <input :value="col.label" @input="update('label', $event.target.value)" class="pim-input text-xs w-full" />
    </div>

    <!-- Typ-spezifische Einstellungen -->

    <!-- Field: Feld-Auswahl -->
    <div v-if="col.type === 'field'">
      <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Feld</label>
      <select :value="col.field" @change="update('field', $event.target.value)" class="pim-input text-xs w-full">
        <option v-for="f in (store.availableFields?.base_fields || [])" :key="f.field" :value="f.field">{{ f.label_de }}</option>
      </select>
    </div>

    <!-- Attribute: Attribut-Auswahl -->
    <div v-if="col.type === 'attribute'">
      <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Attribut</label>
      <select :value="col.attributeId" @change="update('attributeId', $event.target.value)" class="pim-input text-xs w-full">
        <option value="">— Attribut wählen —</option>
        <option v-for="attr in (store.availableFields?.attributes || [])" :key="attr.attributeId" :value="attr.attributeId">
          {{ attr.label_de }} ({{ attr.technical_name }})
        </option>
      </select>
      <label class="flex items-center gap-2 mt-2 text-xs text-[var(--color-text-secondary)]">
        <input type="checkbox" :checked="col.includeUnit" @change="update('includeUnit', $event.target.checked)" class="pim-checkbox" />
        Einheit anzeigen
      </label>
    </div>

    <!-- Price: Preistyp-Auswahl -->
    <div v-if="col.type === 'price'">
      <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Preistyp</label>
      <select :value="col.priceType" @change="update('priceType', $event.target.value)" class="pim-input text-xs w-full">
        <option value="">— Preistyp wählen —</option>
        <option v-for="pt in (store.availableFields?.price_types || [])" :key="pt.priceType" :value="pt.priceType">
          {{ pt.label_de }}
        </option>
      </select>
    </div>

    <!-- Image: Rendering-Modus -->
    <div v-if="col.type === 'image'">
      <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Medien-Verwendungstyp</label>
      <select :value="col.mediaUsageType" @change="update('mediaUsageType', $event.target.value)" class="pim-input text-xs w-full">
        <option value="">— Erstes Bild —</option>
        <option v-for="mut in (store.availableFields?.media_usage_types || [])" :key="mut.mediaUsageType" :value="mut.mediaUsageType">
          {{ mut.label_de }}
        </option>
      </select>

      <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1 mt-2">Darstellung</label>
      <select :value="col.renderMode || 'embedded'" @change="update('renderMode', $event.target.value)" class="pim-input text-xs w-full">
        <option v-for="opt in renderModeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
      </select>

      <div v-if="(col.renderMode || 'embedded') === 'embedded'" class="mt-2">
        <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Thumbnail-Höhe (px)</label>
        <input type="number" :value="col.thumbnailHeight || 50" @input="update('thumbnailHeight', parseInt($event.target.value) || 50)" class="pim-input text-xs w-24" min="20" max="200" />
      </div>
    </div>

    <!-- Media: Array-Modus -->
    <div v-if="col.type === 'media'">
      <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Medien-Verwendungstyp</label>
      <select :value="col.mediaUsageType" @change="update('mediaUsageType', $event.target.value)" class="pim-input text-xs w-full">
        <option value="">— Alle Medien —</option>
        <option v-for="mut in (store.availableFields?.media_usage_types || [])" :key="mut.mediaUsageType" :value="mut.mediaUsageType">
          {{ mut.label_de }}
        </option>
      </select>

      <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1 mt-2">Array-Darstellung</label>
      <select :value="col.arrayMode || 'single'" @change="update('arrayMode', $event.target.value)" class="pim-input text-xs w-full">
        <option v-for="opt in arrayModeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
      </select>

      <div v-if="col.arrayMode === 'expand'" class="mt-2">
        <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Max. Spalten</label>
        <input type="number" :value="col.maxColumns || 5" @input="update('maxColumns', parseInt($event.target.value) || 5)" class="pim-input text-xs w-24" min="1" max="20" />
      </div>
    </div>

    <!-- Relation: Relationstyp + Anzeige -->
    <div v-if="col.type === 'relation'">
      <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Relationstyp</label>
      <select :value="col.relationType" @change="update('relationType', $event.target.value)" class="pim-input text-xs w-full">
        <option value="">— Alle Relationen —</option>
        <option v-for="rt in (store.availableFields?.relation_types || [])" :key="rt.relationType" :value="rt.relationType">
          {{ rt.label_de }}
        </option>
      </select>

      <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1 mt-2">Anzeige-Feld</label>
      <select :value="col.displayField || 'sku'" @change="update('displayField', $event.target.value)" class="pim-input text-xs w-full">
        <option v-for="opt in displayFieldOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
      </select>

      <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1 mt-2">Array-Darstellung</label>
      <select :value="col.arrayMode || 'join'" @change="update('arrayMode', $event.target.value)" class="pim-input text-xs w-full">
        <option v-for="opt in arrayModeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
      </select>
    </div>

    <!-- Variant -->
    <div v-if="col.type === 'variant'">
      <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Anzeige-Feld</label>
      <select :value="col.displayField || 'sku'" @change="update('displayField', $event.target.value)" class="pim-input text-xs w-full">
        <option v-for="opt in displayFieldOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
      </select>

      <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1 mt-2">Array-Darstellung</label>
      <select :value="col.arrayMode || 'join'" @change="update('arrayMode', $event.target.value)" class="pim-input text-xs w-full">
        <option v-for="opt in arrayModeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
      </select>
    </div>

    <!-- Static: Default-Wert -->
    <div v-if="col.type === 'static'">
      <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Standard-Wert</label>
      <input :value="col.defaultValue" @input="update('defaultValue', $event.target.value)" class="pim-input text-xs w-full" placeholder="Leer = freie Spalte" />
      <p class="text-[10px] text-[var(--color-text-tertiary)] mt-1">
        Leer lassen für eine freie Spalte (zum manuellen Ausfüllen).
      </p>
    </div>

    <!-- Text: Template -->
    <div v-if="col.type === 'text'">
      <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Textvorlage</label>
      <input :value="col.content" @input="update('content', $event.target.value)" class="pim-input text-xs w-full" placeholder="z.B. {sku}-{ean}" />
      <p class="text-[10px] text-[var(--color-text-tertiary)] mt-1">
        Platzhalter: {sku}, {name}, {ean}, {status}, {date}
      </p>
    </div>

    <!-- Gemeinsame Einstellungen -->
    <div v-if="['field', 'attribute', 'price'].includes(col.type)" class="pt-2 border-t border-[var(--color-border)]">
      <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Datentyp</label>
      <select :value="col.dataType || 'string'" @change="update('dataType', $event.target.value)" class="pim-input text-xs w-full">
        <option v-for="opt in dataTypeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
      </select>
    </div>

    <!-- Spaltenbreite -->
    <div class="pt-2 border-t border-[var(--color-border)]">
      <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Spaltenbreite</label>
      <input type="number" :value="col.width" @input="update('width', $event.target.value ? parseInt($event.target.value) : null)" class="pim-input text-xs w-24" placeholder="Auto" min="5" max="100" />
      <p class="text-[10px] text-[var(--color-text-tertiary)] mt-1">Leer = automatische Breite</p>
    </div>
  </div>
</template>
