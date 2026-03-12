<script setup>
import { computed } from 'vue'
import { usePdfTemplateDesignerStore } from '@/stores/pdfTemplateDesigner'
import { Settings, Trash2, Copy, ArrowUpToLine, ArrowDownToLine, RulerDimensionLine } from 'lucide-vue-next'
import { fontFamilies } from './fontList'

const store = usePdfTemplateDesignerStore()

const sel = computed(() => store.selectedElement)

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

// fontFamilies imported from fontList.js

const canAutofit = computed(() => {
  if (!sel.value || sel.value.type === 'shape' || sel.value.type === 'image') return false
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

const typeLabels = {
  text: 'Statischer Text',
  field: 'Datenfeld',
  attribute: 'Attribut',
  image: 'Produktbild',
  shape: 'Form / Rahmen',
}
</script>

<template>
  <div class="p-3 space-y-4">
    <div class="text-xs font-semibold text-[var(--color-text-primary)]">Eigenschaften</div>

    <!-- No selection -->
    <div v-if="!sel" class="text-center py-8">
      <Settings class="w-8 h-8 mx-auto mb-2 text-[var(--color-text-tertiary)]" :stroke-width="1.25" />
      <p class="text-[11px] text-[var(--color-text-tertiary)]">
        Wähle ein Element auf dem Canvas, um die Eigenschaften zu bearbeiten.
      </p>
    </div>

    <!-- Element Properties -->
    <div v-if="sel" class="space-y-3">
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
