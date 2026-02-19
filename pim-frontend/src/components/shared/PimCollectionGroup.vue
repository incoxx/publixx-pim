<script setup>
import { ref, computed } from 'vue'
import { ChevronDown, ChevronRight, GripVertical } from 'lucide-vue-next'

const props = defineProps({
  title: { type: String, required: true },
  attributes: { type: Array, default: () => [] },
  filledCount: { type: Number, default: 0 },
  totalCount: { type: Number, default: 0 },
  defaultOpen: { type: Boolean, default: true },
})

const open = ref(props.defaultOpen)

const progress = computed(() => {
  if (props.totalCount === 0) return 0
  return Math.round((props.filledCount / props.totalCount) * 100)
})

const progressColor = computed(() => {
  if (progress.value >= 100) return 'var(--color-success)'
  if (progress.value >= 50) return 'var(--color-accent)'
  return 'var(--color-warning)'
})
</script>

<template>
  <div class="pim-card overflow-hidden">
    <!-- Header -->
    <button
      class="w-full flex items-center gap-3 px-4 py-3 hover:bg-[var(--color-bg)] transition-colors cursor-pointer"
      @click="open = !open"
    >
      <ChevronDown v-if="open" class="w-4 h-4 text-[var(--color-text-tertiary)] shrink-0" :stroke-width="2" />
      <ChevronRight v-else class="w-4 h-4 text-[var(--color-text-tertiary)] shrink-0" :stroke-width="2" />

      <span class="flex-1 text-left text-sm font-medium text-[var(--color-text-primary)]">
        {{ title }}
      </span>

      <!-- Progress -->
      <div class="flex items-center gap-2">
        <span class="text-[11px] text-[var(--color-text-tertiary)]">
          {{ filledCount }}/{{ totalCount }}
        </span>
        <div class="w-16 h-1.5 bg-[var(--color-border)] rounded-full overflow-hidden">
          <div
            class="h-full rounded-full transition-all duration-300"
            :style="{ width: progress + '%', backgroundColor: progressColor }"
          />
        </div>
      </div>
    </button>

    <!-- Content -->
    <transition name="fade">
      <div v-if="open" class="px-4 pb-4 space-y-3 border-t border-[var(--color-border)]">
        <slot />
      </div>
    </transition>
  </div>
</template>
