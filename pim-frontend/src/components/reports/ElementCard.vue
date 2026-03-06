<script setup>
import { X, GripVertical, Type, Hash, Tag, Image, Minus, SeparatorHorizontal, BarChart3 } from 'lucide-vue-next'

const props = defineProps({
  element: { type: Object, required: true },
  selected: { type: Boolean, default: false },
})

const emit = defineEmits(['click', 'remove'])

const typeIcons = { text: Type, field: Hash, attribute: Tag, image: Image, separator: Minus, pageBreak: SeparatorHorizontal, counter: BarChart3 }
const typeLabels = { text: 'Text', field: 'Feld', attribute: 'Attribut', image: 'Bild', separator: 'Linie', pageBreak: 'Seitenumbruch', counter: 'Zähler' }

function getLabel() {
  const el = props.element
  if (el.type === 'field') return el.label || el.field || 'Feld'
  if (el.type === 'attribute') return el.label || 'Attribut'
  if (el.type === 'text') return el.content?.slice(0, 30) || 'Text'
  if (el.type === 'counter') return el.label || 'Zähler'
  return typeLabels[el.type] || el.type
}
</script>

<template>
  <div
    class="flex items-center gap-1.5 px-2 py-1 rounded text-[11px] cursor-pointer transition-colors"
    :class="selected
      ? 'bg-[color-mix(in_srgb,var(--color-accent)_10%,transparent)] ring-1 ring-[var(--color-accent)]'
      : 'hover:bg-[var(--color-bg)]'"
    @click="emit('click')"
  >
    <GripVertical class="w-3 h-3 shrink-0 text-[var(--color-text-tertiary)] cursor-grab" :stroke-width="2" />
    <component
      :is="typeIcons[element.type] || Type"
      class="w-3 h-3 shrink-0"
      :class="element.type === 'attribute' ? 'text-emerald-500' : 'text-[var(--color-accent)]'"
      :stroke-width="2"
    />
    <span class="flex-1 truncate text-[var(--color-text-secondary)]">{{ getLabel() }}</span>
    <span class="text-[9px] text-[var(--color-text-tertiary)]">{{ typeLabels[element.type] }}</span>
    <button
      class="shrink-0 text-[var(--color-text-tertiary)] hover:text-[var(--color-error)]"
      @click.stop="emit('remove')"
    >
      <X class="w-3 h-3" :stroke-width="2" />
    </button>
  </div>
</template>
