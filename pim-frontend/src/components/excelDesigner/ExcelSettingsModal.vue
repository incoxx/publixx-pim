<script setup>
import { X } from 'lucide-vue-next'
import { useExcelDesignerStore } from '@/stores/excelDesigner'

const emit = defineEmits(['close'])
const store = useExcelDesignerStore()
const settings = store.excelSettings

// Sicherstellen dass headerStyle existiert (alte Templates können null haben)
if (!settings.headerStyle) {
  settings.headerStyle = { bgColor: '4472C4', fontColor: 'FFFFFF', bold: true }
}

function updateHeaderStyle(field, value) {
  settings.headerStyle[field] = value
  store.isDirty = true
}
</script>

<template>
  <div class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-6" @click.self="emit('close')">
    <div class="bg-[var(--color-surface)] rounded-xl shadow-2xl w-full max-w-md">
      <!-- Header -->
      <div class="flex items-center justify-between px-5 py-3 border-b border-[var(--color-border)]">
        <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Excel-Einstellungen</h3>
        <button class="p-1.5 text-[var(--color-text-tertiary)] hover:text-[var(--color-text-primary)]" @click="emit('close')">
          <X class="w-4 h-4" :stroke-width="2" />
        </button>
      </div>

      <!-- Content -->
      <div class="p-5 space-y-4 text-xs">
        <!-- Header-Styling -->
        <div>
          <h4 class="font-semibold text-[var(--color-text-secondary)] mb-2">Spaltenköpfe</h4>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-[11px] text-[var(--color-text-tertiary)] mb-1">Hintergrundfarbe</label>
              <div class="flex items-center gap-2">
                <input
                  type="color"
                  :value="'#' + (settings.headerStyle?.bgColor || '4472C4')"
                  @input="updateHeaderStyle('bgColor', $event.target.value.replace('#', ''))"
                  class="w-8 h-8 rounded cursor-pointer"
                />
                <span class="text-[var(--color-text-tertiary)] font-mono">#{{ settings.headerStyle?.bgColor || '4472C4' }}</span>
              </div>
            </div>
            <div>
              <label class="block text-[11px] text-[var(--color-text-tertiary)] mb-1">Schriftfarbe</label>
              <div class="flex items-center gap-2">
                <input
                  type="color"
                  :value="'#' + (settings.headerStyle?.fontColor || 'FFFFFF')"
                  @input="updateHeaderStyle('fontColor', $event.target.value.replace('#', ''))"
                  class="w-8 h-8 rounded cursor-pointer"
                />
                <span class="text-[var(--color-text-tertiary)] font-mono">#{{ settings.headerStyle?.fontColor || 'FFFFFF' }}</span>
              </div>
            </div>
          </div>

          <label class="flex items-center gap-2 mt-2">
            <input type="checkbox" v-model="settings.headerStyle.bold" class="pim-checkbox" @change="store.isDirty = true" />
            <span class="text-[var(--color-text-secondary)]">Fettschrift</span>
          </label>
        </div>

        <!-- Optionen -->
        <div>
          <h4 class="font-semibold text-[var(--color-text-secondary)] mb-2">Optionen</h4>

          <label class="flex items-center gap-2 mb-2">
            <input type="checkbox" v-model="settings.freezeHeader" class="pim-checkbox" @change="store.isDirty = true" />
            <span class="text-[var(--color-text-secondary)]">Kopfzeile einfrieren (Freeze Panes)</span>
          </label>

          <label class="flex items-center gap-2 mb-2">
            <input type="checkbox" v-model="settings.autoFilter" class="pim-checkbox" @change="store.isDirty = true" />
            <span class="text-[var(--color-text-secondary)]">Auto-Filter aktivieren</span>
          </label>
        </div>

        <!-- Dateiname -->
        <div>
          <h4 class="font-semibold text-[var(--color-text-secondary)] mb-2">Export-Dateiname</h4>
          <label class="block text-[11px] text-[var(--color-text-tertiary)] mb-1">Dateiname (ohne Endung)</label>
          <input
            v-model="settings.fileName"
            class="pim-input text-xs w-full"
            :placeholder="store.currentTemplate?.name || 'excel-export'"
            @input="store.isDirty = true"
          />
          <p class="text-[10px] text-[var(--color-text-tertiary)] mt-1">
            Leer = Template-Name. Das Datum wird automatisch angehängt.
          </p>
        </div>

        <!-- Thumbnail-Standardgröße -->
        <div>
          <h4 class="font-semibold text-[var(--color-text-secondary)] mb-2">Thumbnails</h4>
          <label class="block text-[11px] text-[var(--color-text-tertiary)] mb-1">Standard-Höhe (px)</label>
          <input
            type="number"
            v-model.number="settings.defaultThumbnailHeight"
            class="pim-input text-xs w-24"
            min="20" max="200"
            @change="store.isDirty = true"
          />
        </div>
      </div>

      <!-- Footer -->
      <div class="px-5 py-3 border-t border-[var(--color-border)] flex justify-end">
        <button class="pim-btn pim-btn-primary text-xs" @click="emit('close')">Schließen</button>
      </div>
    </div>
  </div>
</template>
