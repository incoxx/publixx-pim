<script setup>
import { ref } from 'vue'
import { ChevronDown, ChevronRight } from 'lucide-vue-next'

const props = defineProps({
  title: { type: String, required: true },
  icon: { type: Object, default: null },
  collapsible: { type: Boolean, default: true },
})

const collapsed = ref(false)
</script>

<template>
  <div class="pim-card overflow-hidden">
    <!-- Header -->
    <div
      class="flex items-center gap-2 px-4 py-3 border-b border-[var(--color-border)]"
      :class="collapsible ? 'cursor-pointer select-none hover:bg-[var(--color-bg)] transition-colors' : ''"
      @click="collapsible && (collapsed = !collapsed)"
    >
      <component
        v-if="icon"
        :is="icon"
        class="w-4 h-4 text-[var(--color-accent)] shrink-0"
        :stroke-width="1.75"
      />
      <h3 class="text-sm font-semibold text-[var(--color-text-primary)] flex-1">{{ title }}</h3>
      <button
        v-if="collapsible"
        class="p-0.5 rounded hover:bg-[var(--color-bg)] transition-colors"
        @click.stop="collapsed = !collapsed"
      >
        <ChevronDown v-if="!collapsed" class="w-4 h-4 text-[var(--color-text-tertiary)]" :stroke-width="2" />
        <ChevronRight v-else class="w-4 h-4 text-[var(--color-text-tertiary)]" :stroke-width="2" />
      </button>
    </div>
    <!-- Content -->
    <div v-show="!collapsed">
      <slot />
    </div>
  </div>
</template>
