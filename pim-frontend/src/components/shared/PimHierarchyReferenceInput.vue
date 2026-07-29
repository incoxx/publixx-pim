<script setup>
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { FolderTree, X } from 'lucide-vue-next'
import hierarchiesApi from '@/api/hierarchies'
import MasterHierarchyNodePickerDialog from '@/components/products/MasterHierarchyNodePickerDialog.vue'
import { useLocalizedName } from '@/composables/useLocalizedName'

const { t } = useI18n()
const { localizedName } = useLocalizedName()

// Attributwert-Picker für Querverweise auf einen Hierarchie-Knoten.
// Der Wert (modelValue) ist die Knoten-UUID (value_string im Backend).
const props = defineProps({
  modelValue: { default: null },
  disabled: { type: Boolean, default: false },
})
const emit = defineEmits(['update:modelValue'])

const showPicker = ref(false)
const nodeLabel = ref('')
const nodeHierarchyId = ref(null)

async function resolveLabel(id) {
  if (!id) {
    nodeLabel.value = ''
    nodeHierarchyId.value = null
    return
  }
  try {
    const { data } = await hierarchiesApi.getNode(id)
    const n = data.data || data
    nodeLabel.value = localizedName(n) || n.path || id
    nodeHierarchyId.value = n.hierarchy_id || n.hierarchy?.id || null
  } catch {
    // Referenz evtl. gelöscht: rohe UUID anzeigen statt Fehler
    nodeLabel.value = id
  }
}

watch(() => props.modelValue, (v) => resolveLabel(v), { immediate: true })

function onSelect(node) {
  emit('update:modelValue', node.id)
  nodeLabel.value = localizedName(node) || node.id
  nodeHierarchyId.value = node.hierarchy_id || node.hierarchy?.id || null
  showPicker.value = false
}

function clear() {
  emit('update:modelValue', null)
  nodeLabel.value = ''
  nodeHierarchyId.value = null
}
</script>

<template>
  <div class="flex items-center gap-1.5">
    <div
      class="flex-1 min-w-0 flex items-center gap-1.5 px-2 py-1.5 rounded-lg border border-[var(--color-border)] text-sm"
      :class="{ 'opacity-60': disabled }"
    >
      <FolderTree class="w-3.5 h-3.5 shrink-0 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
      <span v-if="nodeLabel" class="truncate">{{ nodeLabel }}</span>
      <span v-else class="text-[var(--color-text-tertiary)]">Kein Knoten gewählt</span>
    </div>
    <button
      type="button"
      class="shrink-0 text-xs px-2 py-1.5 rounded-lg border border-[var(--color-border)] hover:bg-[var(--color-bg-hover)] disabled:opacity-50"
      :disabled="disabled"
      @click="showPicker = true"
    >
      {{ modelValue ? t('Ändern') : t('Auswählen') }}
    </button>
    <button
      v-if="modelValue"
      type="button"
      class="shrink-0 p-1.5 rounded-lg border border-[var(--color-border)] hover:bg-[var(--color-bg-hover)] disabled:opacity-50"
      :disabled="disabled"
      title="Referenz entfernen"
      @click="clear"
    >
      <X class="w-3.5 h-3.5" />
    </button>
    <MasterHierarchyNodePickerDialog
      v-model:open="showPicker"
      :currentNodeId="modelValue"
      :currentHierarchyId="nodeHierarchyId"
      @select="onSelect"
    />
  </div>
</template>
