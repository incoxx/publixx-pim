<script setup>
import { ref, computed } from 'vue'
import { Search } from 'lucide-vue-next'
import OutputHierarchyTreeRow from './OutputHierarchyTreeRow.vue'

const props = defineProps({
  nodes: { type: Array, default: () => [] },
  // Bereits zugeordnete Knoten-IDs — werden angezeigt, aber nicht abwählbar
  disabledIds: { type: Array, default: () => [] },
  modelValue: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue'])

const selectedSet = computed(() => new Set(props.modelValue))
const disabledSet = computed(() => new Set(props.disabledIds))

const filterText = ref('')
const expandedIds = ref(new Set())

function collectIds(node, acc = []) {
  acc.push(node.id)
  for (const child of node.children || []) collectIds(child, acc)
  return acc
}

// Ein Knoten gilt als "abgedeckt", wenn er neu ausgewählt ODER bereits zugeordnet ist —
// so zeigt z.B. "Europa" ein volles Häkchen, sobald jedes Land entweder frisch
// markiert oder schon länger zugeordnet ist.
function nodeState(node) {
  const ids = collectIds(node)
  const covered = ids.filter(id => selectedSet.value.has(id) || disabledSet.value.has(id)).length
  if (covered === 0) return 'unchecked'
  if (covered === ids.length) return 'checked'
  return 'indeterminate'
}

function toggleNode(node) {
  const ids = collectIds(node)
  const next = new Set(props.modelValue)
  const state = nodeState(node)

  if (state === 'checked') {
    // Abwählen: nur frisch ausgewählte IDs entfernen, bereits zugeordnete bleiben unberührt
    // (die werden über die Tabelle entfernt, nicht über diesen Dialog).
    for (const id of ids) next.delete(id)
  } else {
    // Auswählen: alle noch nicht zugeordneten IDs im Teilbaum hinzufügen.
    for (const id of ids) {
      if (!disabledSet.value.has(id)) next.add(id)
    }
  }
  emit('update:modelValue', Array.from(next))
}

function toggleExpand(nodeId) {
  const next = new Set(expandedIds.value)
  if (next.has(nodeId)) next.delete(nodeId)
  else next.add(nodeId)
  expandedIds.value = next
}

function nodeLabel(node) {
  return node.name_de || node.name_en || node.technical_name || node.id
}

// Bei aktivem Filter: nur Knoten zeigen, deren Name matcht — oder die einen
// passenden Nachfahren haben (damit der Pfad zum Treffer sichtbar bleibt).
function matchesFilter(node) {
  const q = filterText.value.trim().toLowerCase()
  if (!q) return true
  if (nodeLabel(node).toLowerCase().includes(q)) return true
  return (node.children || []).some(matchesFilter)
}

function isForceExpanded(node) {
  return filterText.value.trim() !== '' && (node.children || []).some(matchesFilter)
}
</script>

<template>
  <div class="border border-[var(--color-border)] rounded-lg overflow-hidden">
    <div class="flex items-center gap-1.5 px-2 py-1.5 border-b border-[var(--color-border)] bg-[var(--color-bg)]">
      <Search class="w-3.5 h-3.5 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
      <input
        v-model="filterText"
        type="text"
        placeholder="Knoten suchen…"
        class="flex-1 bg-transparent text-xs outline-none text-[var(--color-text-primary)]"
      >
    </div>
    <div class="max-h-72 overflow-y-auto py-1">
      <OutputHierarchyTreeRow
        v-for="node in nodes.filter(matchesFilter)"
        :key="node.id"
        :node="node"
        :depth="0"
        :node-state="nodeState"
        :disabled-set="disabledSet"
        :expanded-ids="expandedIds"
        :matches-filter="matchesFilter"
        :force-expanded="isForceExpanded"
        :node-label="nodeLabel"
        @toggle-node="toggleNode"
        @toggle-expand="toggleExpand"
      />
      <p v-if="nodes.length === 0" class="text-xs text-[var(--color-text-tertiary)] text-center py-4">
        Keine Knoten in dieser Hierarchie.
      </p>
    </div>
  </div>
</template>
