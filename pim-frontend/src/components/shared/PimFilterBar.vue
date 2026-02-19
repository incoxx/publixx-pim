<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { Search, X, Filter, ChevronDown } from 'lucide-vue-next'

const props = defineProps({
  search: { type: String, default: '' },
  activeFilters: { type: Array, default: () => [] },
  presets: { type: Array, default: () => [] },
  placeholder: { type: String, default: 'Suchen…' },
})

const emit = defineEmits(['update:search', 'remove-filter', 'clear-all', 'preset'])

const searchRef = ref(null)

function focusSearch() {
  searchRef.value?.focus()
}

onMounted(() => {
  window.addEventListener('pim:focus-search', focusSearch)
})

onUnmounted(() => {
  window.removeEventListener('pim:focus-search', focusSearch)
})
</script>

<template>
  <div class="flex items-center gap-2 flex-wrap">
    <!-- Search -->
    <div class="relative flex-1 min-w-[200px] max-w-sm">
      <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
      <input
        ref="searchRef"
        type="text"
        :value="search"
        :placeholder="placeholder"
        class="pim-input pl-8 pr-3 text-[13px]"
        @input="$emit('update:search', $event.target.value)"
      />
    </div>

    <!-- Active filter chips -->
    <div
      v-for="filter in activeFilters"
      :key="filter.key"
      class="inline-flex items-center gap-1 px-2 py-1 bg-[color-mix(in_srgb,var(--color-accent)_10%,transparent)] text-[var(--color-accent)] rounded-md text-[12px]"
    >
      <span>{{ filter.label }}</span>
      <button
        class="p-0.5 rounded hover:bg-[color-mix(in_srgb,var(--color-accent)_20%,transparent)]"
        @click="$emit('remove-filter', filter.key)"
      >
        <X class="w-3 h-3" :stroke-width="2" />
      </button>
    </div>

    <!-- Clear all -->
    <button
      v-if="activeFilters.length > 0"
      class="text-[12px] text-[var(--color-text-tertiary)] hover:text-[var(--color-text-secondary)] underline"
      @click="$emit('clear-all')"
    >
      Alle löschen
    </button>

    <!-- Presets -->
    <div v-if="presets.length > 0" class="relative group ml-auto">
      <button class="pim-btn pim-btn-secondary text-xs gap-1">
        <Filter class="w-3 h-3" :stroke-width="1.75" />
        Filter
        <ChevronDown class="w-3 h-3" :stroke-width="2" />
      </button>
      <div class="absolute right-0 top-full mt-1 w-48 bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg shadow-lg py-1 hidden group-hover:block z-20">
        <button
          v-for="preset in presets"
          :key="preset.id"
          class="w-full px-3 py-1.5 text-left text-xs text-[var(--color-text-secondary)] hover:bg-[var(--color-bg)] transition-colors"
          @click="$emit('preset', preset)"
        >
          {{ preset.label }}
        </button>
      </div>
    </div>
  </div>
</template>
