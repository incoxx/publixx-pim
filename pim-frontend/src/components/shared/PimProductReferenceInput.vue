<script setup>
import { ref, watch, onBeforeUnmount } from 'vue'
import { Package, X } from 'lucide-vue-next'
import productsApi from '@/api/products'

// Attributwert-Picker für Querverweise auf ein anderes Produkt.
// Der Wert (modelValue) ist die Produkt-UUID (value_string im Backend).
const props = defineProps({
  modelValue: { default: null },
  disabled: { type: Boolean, default: false },
})
const emit = defineEmits(['update:modelValue'])

const label = ref('')
const searchOpen = ref(false)
const search = ref('')
const results = ref([])
const searching = ref(false)
let searchTimeout = null

function formatProduct(p) {
  return `${p.sku || ''}${p.name ? ' — ' + p.name : ''}`.trim() || p.id
}

async function resolveLabel(id) {
  if (!id) {
    label.value = ''
    return
  }
  try {
    const { data } = await productsApi.get(id)
    const p = data.data || data
    label.value = formatProduct(p)
  } catch {
    // Referenz evtl. gelöscht: rohe UUID anzeigen statt Fehler
    label.value = id
  }
}

watch(() => props.modelValue, (v) => resolveLabel(v), { immediate: true })

function onSearch() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(async () => {
    if (!search.value.trim()) {
      results.value = []
      return
    }
    searching.value = true
    try {
      const { data } = await productsApi.list({ search: search.value, perPage: 10 })
      results.value = data.data || data
    } catch {
      results.value = []
    } finally {
      searching.value = false
    }
  }, 300)
}

function pick(p) {
  emit('update:modelValue', p.id)
  label.value = formatProduct(p)
  search.value = ''
  results.value = []
  searchOpen.value = false
}

function clear() {
  emit('update:modelValue', null)
  label.value = ''
}

onBeforeUnmount(() => clearTimeout(searchTimeout))
</script>

<template>
  <div class="relative">
    <div class="flex items-center gap-1.5">
      <div
        class="flex-1 min-w-0 flex items-center gap-1.5 px-2 py-1.5 rounded-lg border border-[var(--color-border)] text-sm"
        :class="{ 'opacity-60': disabled }"
      >
        <Package class="w-3.5 h-3.5 shrink-0 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
        <span v-if="label" class="truncate">{{ label }}</span>
        <span v-else class="text-[var(--color-text-tertiary)]">Kein Produkt gewählt</span>
      </div>
      <button
        type="button"
        class="shrink-0 text-xs px-2 py-1.5 rounded-lg border border-[var(--color-border)] hover:bg-[var(--color-bg-hover)] disabled:opacity-50"
        :disabled="disabled"
        @click="searchOpen = !searchOpen"
      >
        {{ modelValue ? 'Ändern' : 'Auswählen' }}
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
    </div>

    <div v-if="searchOpen" class="mt-1.5">
      <input
        v-model="search"
        type="text"
        placeholder="SKU oder Name suchen…"
        class="w-full px-2 py-1.5 rounded-lg border border-[var(--color-border)] bg-transparent text-sm focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]"
        @input="onSearch"
      />
      <div
        v-if="results.length || searching"
        class="mt-1 max-h-56 overflow-auto rounded-lg border border-[var(--color-border)] bg-[var(--color-bg)] shadow-sm"
      >
        <div v-if="searching" class="px-2 py-1.5 text-xs text-[var(--color-text-tertiary)]">Suche…</div>
        <button
          v-for="p in results"
          :key="p.id"
          type="button"
          class="w-full text-left px-2 py-1.5 text-sm hover:bg-[var(--color-bg-hover)] flex items-center gap-1.5"
          @click="pick(p)"
        >
          <span class="font-mono text-xs text-[var(--color-text-tertiary)]">{{ p.sku }}</span>
          <span class="truncate">{{ p.name }}</span>
        </button>
      </div>
    </div>
  </div>
</template>
