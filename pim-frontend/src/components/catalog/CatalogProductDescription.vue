<script setup>
import { computed } from 'vue'

const props = defineProps({
  description: { type: String, default: null },
  descriptionAttributes: { type: Array, default: () => [] },
})

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
      class="text-base-content/80 leading-relaxed"
      :class="getTypographyClass(attr.typography)"
    >
      {{ attr.value }}
    </div>
  </div>
  <div v-else-if="description">
    <p class="text-sm text-base-content/70 leading-relaxed">{{ description }}</p>
  </div>
  <p v-else class="text-base-content/40 italic text-sm">Keine Beschreibung vorhanden.</p>
</template>
