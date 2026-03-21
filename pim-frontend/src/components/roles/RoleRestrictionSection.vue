<script setup>
import { ref, computed, onMounted } from 'vue'
import { ChevronDown, ChevronRight, X, Plus, Lock } from 'lucide-vue-next'

const props = defineProps({
  label: { type: String, required: true },
  items: { type: Array, default: () => [] },
  selectedRestrictions: { type: Array, default: () => [] },
  accessLevels: { type: Array, default: () => ['read', 'write', 'delete'] },
  nameField: { type: String, default: 'name_de' },
})

const emit = defineEmits(['update:restrictions'])

const expanded = ref(false)
const showPicker = ref(false)

const accessLevelLabels = {
  read: 'Lesen',
  write: 'Schreiben',
  delete: 'Löschen',
}

const isRestricted = computed(() => props.selectedRestrictions.length > 0)

const availableItems = computed(() => {
  const selectedIds = new Set(props.selectedRestrictions.map(r => r.id))
  return props.items.filter(item => !selectedIds.has(item.id))
})

function addRestriction(item) {
  const newRestrictions = [
    ...props.selectedRestrictions,
    { id: item.id, access_level: 'read', name: item[props.nameField] || item.name_de || item.id },
  ]
  emit('update:restrictions', newRestrictions)
  showPicker.value = false
}

function removeRestriction(id) {
  emit('update:restrictions', props.selectedRestrictions.filter(r => r.id !== id))
}

function updateAccessLevel(id, level) {
  const updated = props.selectedRestrictions.map(r =>
    r.id === id ? { ...r, access_level: level } : r
  )
  emit('update:restrictions', updated)
}

function clearAll() {
  emit('update:restrictions', [])
}
</script>

<template>
  <div class="border border-[var(--color-border)] rounded-lg overflow-hidden">
    <!-- Header -->
    <button
      class="w-full flex items-center gap-2 px-3 py-2 text-left cursor-pointer hover:bg-[var(--color-bg-hover)] transition-colors"
      @click="expanded = !expanded"
    >
      <component :is="expanded ? ChevronDown : ChevronRight" class="w-3.5 h-3.5 text-[var(--color-text-tertiary)]" />
      <Lock v-if="isRestricted" class="w-3 h-3 text-[var(--color-warning)]" />
      <span class="text-xs font-medium text-[var(--color-text-primary)]">{{ label }}</span>
      <span v-if="isRestricted" class="text-[10px] text-[var(--color-warning)] ml-auto">
        {{ selectedRestrictions.length }} Einschränkung{{ selectedRestrictions.length !== 1 ? 'en' : '' }}
      </span>
      <span v-else class="text-[10px] text-[var(--color-text-tertiary)] ml-auto">Vollzugriff</span>
    </button>

    <!-- Content -->
    <div v-if="expanded" class="border-t border-[var(--color-border)] px-3 py-2 space-y-2">
      <!-- Restriction list -->
      <div v-if="selectedRestrictions.length > 0" class="space-y-1">
        <div
          v-for="restriction in selectedRestrictions"
          :key="restriction.id"
          class="flex items-center gap-2 py-1 px-2 rounded bg-[var(--color-bg-hover)]"
        >
          <span class="text-xs text-[var(--color-text-primary)] flex-1 truncate">
            {{ restriction.name }}
          </span>
          <select
            :value="restriction.access_level"
            class="pim-input text-[11px] py-0.5 px-1.5 min-w-0"
            @change="updateAccessLevel(restriction.id, $event.target.value)"
          >
            <option v-for="level in accessLevels" :key="level" :value="level">
              {{ accessLevelLabels[level] }}
            </option>
          </select>
          <button
            class="p-0.5 rounded hover:bg-[var(--color-bg)] text-[var(--color-text-tertiary)] hover:text-[var(--color-error)] cursor-pointer"
            @click="removeRestriction(restriction.id)"
          >
            <X class="w-3 h-3" />
          </button>
        </div>
      </div>

      <!-- Add button / Picker -->
      <div v-if="!showPicker" class="flex items-center gap-2">
        <button
          v-if="availableItems.length > 0"
          class="inline-flex items-center gap-1 text-[11px] text-[var(--color-accent)] hover:underline cursor-pointer"
          @click="showPicker = true"
        >
          <Plus class="w-3 h-3" />
          Hinzufügen
        </button>
        <button
          v-if="selectedRestrictions.length > 0"
          class="inline-flex items-center gap-1 text-[11px] text-[var(--color-text-tertiary)] hover:text-[var(--color-error)] cursor-pointer ml-auto"
          @click="clearAll"
        >
          Alle entfernen
        </button>
      </div>

      <!-- Item picker dropdown -->
      <div v-if="showPicker" class="border border-[var(--color-border)] rounded bg-[var(--color-bg)] max-h-40 overflow-y-auto">
        <button
          v-for="item in availableItems"
          :key="item.id"
          class="w-full text-left px-2 py-1.5 text-xs hover:bg-[var(--color-bg-hover)] cursor-pointer border-b border-[var(--color-border)] last:border-b-0"
          @click="addRestriction(item)"
        >
          {{ item[nameField] || item.name_de || item.id }}
        </button>
        <div v-if="availableItems.length === 0" class="px-2 py-1.5 text-[11px] text-[var(--color-text-tertiary)]">
          Alle Einträge bereits hinzugefügt
        </div>
        <button
          class="w-full text-center px-2 py-1 text-[11px] text-[var(--color-text-tertiary)] hover:text-[var(--color-text-primary)] cursor-pointer border-t border-[var(--color-border)]"
          @click="showPicker = false"
        >
          Abbrechen
        </button>
      </div>

      <p v-if="selectedRestrictions.length === 0" class="text-[10px] text-[var(--color-text-tertiary)]">
        Keine Einschränkungen — Rolle hat Vollzugriff auf alle Einträge.
      </p>
    </div>
  </div>
</template>
