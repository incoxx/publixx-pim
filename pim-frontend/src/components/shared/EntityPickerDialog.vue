<script setup>
import { ref, watch } from 'vue'
import { useDebounceFn } from '@vueuse/core'
import { Search, X, Check, ChevronLeft, ChevronRight } from 'lucide-vue-next'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  title: { type: String, default: 'Auswählen' },
  // fetcher(query, page) => Promise<{ items: [], meta: {} }>
  fetcher: { type: Function, required: true },
  multiple: { type: Boolean, default: false },
  selected: { type: Array, default: () => [] }, // vorausgewählte IDs
  labelFn: { type: Function, default: (i) => i.name || i.label || i.id },
  sublabelFn: { type: Function, default: () => null },
})

const emit = defineEmits(['update:modelValue', 'confirm'])

const query = ref('')
const items = ref([])
const meta = ref({ current_page: 1, last_page: 1, total: 0 })
const loading = ref(false)
const chosen = ref(new Map()) // id -> item

async function load(page = 1) {
  loading.value = true
  try {
    const res = await props.fetcher(query.value, page)
    items.value = res.items || []
    meta.value = res.meta || { current_page: page, last_page: 1, total: items.value.length }
  } catch {
    items.value = []
  } finally {
    loading.value = false
  }
}

const debouncedSearch = useDebounceFn(() => load(1), 300)

function isChosen(item) {
  return chosen.value.has(item.id)
}

function toggle(item) {
  if (!props.multiple) {
    chosen.value = new Map([[item.id, item]])
    confirm()
    return
  }
  const next = new Map(chosen.value)
  next.has(item.id) ? next.delete(item.id) : next.set(item.id, item)
  chosen.value = next
}

function confirm() {
  emit('confirm', [...chosen.value.values()])
  close()
}

function close() {
  emit('update:modelValue', false)
}

watch(() => props.modelValue, (open) => {
  if (open) {
    query.value = ''
    chosen.value = new Map(props.selected.map((id) => [id, { id }]))
    load(1)
  }
})
</script>

<template>
  <Teleport to="body">
    <div v-if="modelValue" class="fixed inset-0 z-50 flex items-center justify-center">
      <div class="absolute inset-0 bg-black/40" @click="close" />
      <div class="relative bg-[var(--color-surface)] rounded-xl shadow-xl w-full max-w-lg max-h-[80vh] flex flex-col m-4">
        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-3 border-b border-[var(--color-border)]">
          <h3 class="text-sm font-semibold">{{ title }}</h3>
          <button class="p-1 rounded hover:bg-[var(--color-bg)]" @click="close"><X class="w-4 h-4" :stroke-width="2" /></button>
        </div>

        <!-- Suche -->
        <div class="p-3 border-b border-[var(--color-border)]">
          <div class="relative">
            <Search class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
            <input v-model="query" class="pim-input text-xs w-full pim-input-icon" placeholder="Suchen…" @input="debouncedSearch" />
          </div>
        </div>

        <!-- Liste -->
        <div class="flex-1 overflow-y-auto p-2">
          <div v-if="loading" class="space-y-1">
            <div v-for="i in 6" :key="i" class="pim-skeleton h-9 rounded" />
          </div>
          <template v-else>
            <button v-for="item in items" :key="item.id"
              class="flex items-center justify-between w-full px-3 py-2 rounded-lg text-left hover:bg-[var(--color-bg)]"
              :class="isChosen(item) ? 'bg-[var(--color-bg)]' : ''"
              @click="toggle(item)">
              <span class="min-w-0">
                <span class="block text-xs font-medium truncate">{{ labelFn(item) }}</span>
                <span v-if="sublabelFn(item)" class="block text-[10px] text-[var(--color-text-tertiary)] font-mono truncate">{{ sublabelFn(item) }}</span>
              </span>
              <Check v-if="isChosen(item)" class="w-4 h-4 text-[var(--color-accent)] shrink-0" :stroke-width="2.5" />
            </button>
            <p v-if="!items.length" class="text-xs text-[var(--color-text-tertiary)] text-center py-6">Keine Treffer.</p>
          </template>
        </div>

        <!-- Pagination + Footer -->
        <div class="flex items-center justify-between px-3 py-2 border-t border-[var(--color-border)]">
          <div class="flex items-center gap-1">
            <button class="pim-btn pim-btn-ghost p-1 disabled:opacity-30" :disabled="meta.current_page <= 1" @click="load(meta.current_page - 1)">
              <ChevronLeft class="w-4 h-4" :stroke-width="2" />
            </button>
            <span class="text-[11px] text-[var(--color-text-tertiary)]">{{ meta.current_page }} / {{ meta.last_page }}</span>
            <button class="pim-btn pim-btn-ghost p-1 disabled:opacity-30" :disabled="meta.current_page >= meta.last_page" @click="load(meta.current_page + 1)">
              <ChevronRight class="w-4 h-4" :stroke-width="2" />
            </button>
          </div>
          <div v-if="multiple" class="flex items-center gap-2">
            <span class="text-[11px] text-[var(--color-text-tertiary)]">{{ chosen.size }} gewählt</span>
            <button class="pim-btn pim-btn-primary text-xs" @click="confirm">Übernehmen</button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>
