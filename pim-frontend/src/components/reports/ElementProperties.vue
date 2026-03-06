<script setup>
import { computed } from 'vue'
import { useReportDesignerStore } from '@/stores/reportDesigner'
import { Settings } from 'lucide-vue-next'

const store = useReportDesignerStore()

const sel = computed(() => store.selectedElement)
const group = computed(() => store.selectedGroup)

function updateElement(key, value) {
  if (!sel.value) return
  store.updateElement(sel.value.groupId, sel.value.section, sel.value.element.id, { [key]: value })
}

function updateStyle(key, value) {
  if (!sel.value) return
  const style = { ...(sel.value.element.style || {}), [key]: value }
  store.updateElement(sel.value.groupId, sel.value.section, sel.value.element.id, { style })
}

function updateGroup(key, value) {
  if (!store.selectedGroupId) return
  store.updateGroup(store.selectedGroupId, { [key]: value })
}

const groupFields = computed(() => store.availableFields?.group_fields || [])
</script>

<template>
  <div class="p-3 space-y-4">
    <div class="text-xs font-semibold text-[var(--color-text-primary)]">Eigenschaften</div>

    <!-- No selection -->
    <div v-if="!sel && !group" class="text-center py-8">
      <Settings class="w-8 h-8 mx-auto mb-2 text-[var(--color-text-tertiary)]" :stroke-width="1.25" />
      <p class="text-[11px] text-[var(--color-text-tertiary)]">
        Wähle ein Element oder eine Gruppe, um die Eigenschaften zu bearbeiten.
      </p>
    </div>

    <!-- Group Properties -->
    <div v-if="group && !sel" class="space-y-3">
      <div class="text-[11px] font-semibold text-[var(--color-accent)]">Gruppen-Einstellungen</div>

      <div>
        <label class="block text-[10px] font-medium text-[var(--color-text-tertiary)] mb-0.5">Label</label>
        <input
          :value="group.label"
          class="pim-input text-xs w-full"
          @input="updateGroup('label', $event.target.value)"
        />
      </div>

      <div>
        <label class="block text-[10px] font-medium text-[var(--color-text-tertiary)] mb-0.5">Gruppierungsfeld</label>
        <select
          :value="group.field"
          class="pim-input text-xs w-full"
          @change="updateGroup('field', $event.target.value)"
        >
          <option v-for="gf in groupFields" :key="gf.field" :value="gf.field">{{ gf.label_de }}</option>
        </select>
      </div>

      <div>
        <label class="block text-[10px] font-medium text-[var(--color-text-tertiary)] mb-0.5">Sortierung</label>
        <select
          :value="group.sortOrder || 'asc'"
          class="pim-input text-xs w-full"
          @change="updateGroup('sortOrder', $event.target.value)"
        >
          <option value="asc">Aufsteigend (A-Z)</option>
          <option value="desc">Absteigend (Z-A)</option>
        </select>
      </div>

      <label class="flex items-center gap-2 text-[11px] cursor-pointer text-[var(--color-text-secondary)]">
        <input
          type="checkbox"
          :checked="group.pageBreak"
          class="rounded"
          @change="updateGroup('pageBreak', $event.target.checked)"
        />
        Seitenumbruch nach Gruppe
      </label>
    </div>

    <!-- Element Properties -->
    <div v-if="sel" class="space-y-3">
      <div class="text-[11px] font-semibold text-[var(--color-accent)]">
        {{ { text: 'Text', field: 'Feld', attribute: 'Attribut', image: 'Bild', counter: 'Zähler', separator: 'Trennlinie', pageBreak: 'Seitenumbruch' }[sel.element.type] || sel.element.type }}
      </div>

      <!-- TEXT -->
      <template v-if="sel.element.type === 'text'">
        <div>
          <label class="block text-[10px] font-medium text-[var(--color-text-tertiary)] mb-0.5">Inhalt</label>
          <textarea
            :value="sel.element.content"
            class="pim-input text-xs w-full"
            rows="3"
            placeholder="Text eingeben... Platzhalter: {date}, {group.value}, {count}"
            @input="updateElement('content', $event.target.value)"
          />
        </div>
      </template>

      <!-- FIELD -->
      <template v-if="sel.element.type === 'field'">
        <div>
          <label class="block text-[10px] font-medium text-[var(--color-text-tertiary)] mb-0.5">Feld</label>
          <select
            :value="sel.element.field"
            class="pim-input text-xs w-full"
            @change="updateElement('field', $event.target.value)"
          >
            <option v-for="f in (store.availableFields?.base_fields || [])" :key="f.field" :value="f.field">{{ f.label_de }}</option>
          </select>
        </div>
        <div>
          <label class="block text-[10px] font-medium text-[var(--color-text-tertiary)] mb-0.5">Label-Überschreibung</label>
          <input
            :value="sel.element.label"
            class="pim-input text-xs w-full"
            @input="updateElement('label', $event.target.value)"
          />
        </div>
        <label class="flex items-center gap-2 text-[11px] cursor-pointer text-[var(--color-text-secondary)]">
          <input type="checkbox" :checked="sel.element.showLabel" class="rounded" @change="updateElement('showLabel', $event.target.checked)" />
          Label anzeigen
        </label>
      </template>

      <!-- ATTRIBUTE -->
      <template v-if="sel.element.type === 'attribute'">
        <div>
          <label class="block text-[10px] font-medium text-[var(--color-text-tertiary)] mb-0.5">Attribut</label>
          <select
            :value="sel.element.attributeId"
            class="pim-input text-xs w-full"
            @change="updateElement('attributeId', $event.target.value)"
          >
            <option v-for="a in (store.availableFields?.attributes || [])" :key="a.attributeId" :value="a.attributeId">
              {{ a.label_de }} ({{ a.technical_name }})
            </option>
          </select>
        </div>
        <div>
          <label class="block text-[10px] font-medium text-[var(--color-text-tertiary)] mb-0.5">Label-Überschreibung</label>
          <input
            :value="sel.element.label"
            class="pim-input text-xs w-full"
            @input="updateElement('label', $event.target.value)"
          />
        </div>
        <div class="space-y-1">
          <label class="flex items-center gap-2 text-[11px] cursor-pointer text-[var(--color-text-secondary)]">
            <input type="checkbox" :checked="sel.element.showLabel" class="rounded" @change="updateElement('showLabel', $event.target.checked)" />
            Label anzeigen
          </label>
          <label class="flex items-center gap-2 text-[11px] cursor-pointer text-[var(--color-text-secondary)]">
            <input type="checkbox" :checked="sel.element.showValue !== false" class="rounded" @change="updateElement('showValue', $event.target.checked)" />
            Wert anzeigen
          </label>
          <label class="flex items-center gap-2 text-[11px] cursor-pointer text-[var(--color-text-secondary)]">
            <input type="checkbox" :checked="sel.element.showUnit" class="rounded" @change="updateElement('showUnit', $event.target.checked)" />
            Einheit anzeigen
          </label>
        </div>
      </template>

      <!-- IMAGE -->
      <template v-if="sel.element.type === 'image'">
        <div>
          <label class="block text-[10px] font-medium text-[var(--color-text-tertiary)] mb-0.5">Quelle</label>
          <select :value="sel.element.source" class="pim-input text-xs w-full" @change="updateElement('source', $event.target.value)">
            <option value="primary">Hauptbild</option>
            <option value="all">Alle Bilder</option>
          </select>
        </div>
        <div class="grid grid-cols-2 gap-2">
          <div>
            <label class="block text-[10px] font-medium text-[var(--color-text-tertiary)] mb-0.5">Breite (px)</label>
            <input type="number" :value="sel.element.width || 80" class="pim-input text-xs w-full" @input="updateElement('width', parseInt($event.target.value) || 80)" />
          </div>
          <div>
            <label class="block text-[10px] font-medium text-[var(--color-text-tertiary)] mb-0.5">Höhe (px)</label>
            <input type="number" :value="sel.element.height || 80" class="pim-input text-xs w-full" @input="updateElement('height', parseInt($event.target.value) || 80)" />
          </div>
        </div>
      </template>

      <!-- COUNTER -->
      <template v-if="sel.element.type === 'counter'">
        <div>
          <label class="block text-[10px] font-medium text-[var(--color-text-tertiary)] mb-0.5">Label</label>
          <input :value="sel.element.label" class="pim-input text-xs w-full" @input="updateElement('label', $event.target.value)" />
        </div>
        <div>
          <label class="block text-[10px] font-medium text-[var(--color-text-tertiary)] mb-0.5">Format</label>
          <input :value="sel.element.format || '{count}'" class="pim-input text-xs w-full" placeholder="{count} Produkte" @input="updateElement('format', $event.target.value)" />
          <p class="text-[9px] text-[var(--color-text-tertiary)] mt-0.5">Platzhalter: {count}</p>
        </div>
      </template>

      <!-- Style (for text, field, attribute) -->
      <template v-if="['text', 'field', 'attribute'].includes(sel.element.type)">
        <div class="border-t border-[var(--color-border)] pt-3 mt-3">
          <div class="text-[10px] font-semibold text-[var(--color-text-tertiary)] mb-2">Stil</div>
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="block text-[9px] text-[var(--color-text-tertiary)] mb-0.5">Schriftgröße</label>
              <input type="number" :value="sel.element.style?.fontSize || 10" class="pim-input text-xs w-full" min="6" max="36" @input="updateStyle('fontSize', parseInt($event.target.value) || 10)" />
            </div>
            <div>
              <label class="block text-[9px] text-[var(--color-text-tertiary)] mb-0.5">Ausrichtung</label>
              <select :value="sel.element.style?.align || 'left'" class="pim-input text-xs w-full" @change="updateStyle('align', $event.target.value)">
                <option value="left">Links</option>
                <option value="center">Mitte</option>
                <option value="right">Rechts</option>
              </select>
            </div>
          </div>
          <div class="flex items-center gap-3 mt-2">
            <label class="flex items-center gap-1.5 text-[11px] cursor-pointer text-[var(--color-text-secondary)]">
              <input type="checkbox" :checked="sel.element.style?.bold" class="rounded" @change="updateStyle('bold', $event.target.checked)" />
              Fett
            </label>
            <label class="flex items-center gap-1.5 text-[11px] cursor-pointer text-[var(--color-text-secondary)]">
              <input type="checkbox" :checked="sel.element.style?.italic" class="rounded" @change="updateStyle('italic', $event.target.checked)" />
              Kursiv
            </label>
          </div>
          <div class="mt-2">
            <label class="block text-[9px] text-[var(--color-text-tertiary)] mb-0.5">Farbe</label>
            <input type="color" :value="sel.element.style?.color || '#000000'" class="w-8 h-6 rounded border border-[var(--color-border)]" @input="updateStyle('color', $event.target.value)" />
          </div>
        </div>
      </template>
    </div>
  </div>
</template>
