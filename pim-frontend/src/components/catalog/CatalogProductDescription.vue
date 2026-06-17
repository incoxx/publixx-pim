<script setup>
import { computed } from 'vue'
import { Pencil } from 'lucide-vue-next'

const props = defineProps({
  description: { type: String, default: null },
  descriptionAttributes: { type: Array, default: () => [] },
  // Wenn true, wird hinter live-edit-fähigen Feldern ein Stift-Symbol angezeigt
  // (nur im eingeloggten Admin-Vorschaukatalog, nicht im öffentlichen Embed).
  editable: { type: Boolean, default: false },
})

const emit = defineEmits(['edit'])

const hasConfiguredAttributes = computed(() => props.descriptionAttributes?.length > 0)

const typographyClasses = {
  'xs': 'text-xs',
  'sm': 'text-sm',
  'base': 'text-base',
  'lg': 'text-lg',
  'xl': 'text-xl font-semibold',
  '2xl': 'text-2xl font-bold',
  '3xl': 'text-3xl font-bold',
}

function getTypographyClass(typography) {
  return typographyClasses[typography] || 'text-base'
}
</script>

<template>
  <div v-if="hasConfiguredAttributes" class="space-y-1.5">
    <div
      v-for="attr in descriptionAttributes"
      :key="attr.attribute_id"
      class="group/desc flex items-start gap-1.5 text-base-content/80 leading-relaxed"
      :class="getTypographyClass(attr.typography)"
    >
      <span class="flex-1">{{ attr.value }}</span>
      <button
        v-if="editable && attr.live_edit && attr.attribute_id"
        type="button"
        class="btn btn-ghost btn-xs px-1 opacity-0 group-hover/desc:opacity-100 focus:opacity-100 transition-opacity shrink-0 text-base-content/50 hover:text-primary"
        title="Wert bearbeiten"
        @click="emit('edit', attr)"
      >
        <Pencil class="w-3.5 h-3.5" />
      </button>
    </div>
  </div>
  <div v-else-if="description">
    <p class="text-sm text-base-content/70 leading-relaxed">{{ description }}</p>
  </div>
  <p v-else class="text-base-content/40 italic text-sm">Keine Beschreibung vorhanden.</p>
</template>
