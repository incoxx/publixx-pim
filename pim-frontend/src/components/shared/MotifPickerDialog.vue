<script setup>
import { ref, watch } from 'vue'
import { X, Search, Images } from 'lucide-vue-next'
import mediaMotifs from '@/api/mediaMotifs'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'select'])

const searchQuery = ref('')
const loading = ref(false)
const items = ref([])
let searchTimer = null

async function loadMotifs() {
  loading.value = true
  try {
    const { data } = await mediaMotifs.list({ search: searchQuery.value || undefined, perPage: 24 })
    items.value = data.data || []
  } finally {
    loading.value = false
  }
}

watch(() => props.modelValue, (open) => {
  if (open) {
    searchQuery.value = ''
    loadMotifs()
  }
})

watch(searchQuery, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(loadMotifs, 300)
})

function close() {
  emit('update:modelValue', false)
}

function select(motif) {
  emit('select', motif)
  close()
}
</script>

<template>
  <Teleport to="body">
    <div v-if="modelValue" class="fixed inset-0 z-50 flex items-center justify-center">
      <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" @click="close" />
      <div class="relative z-10 w-full max-w-[760px] max-h-[80vh] flex flex-col bg-[var(--color-surface)] border border-[var(--color-border)] rounded-xl shadow-xl mx-4">
        <div class="flex items-center justify-between px-4 py-3 border-b border-[var(--color-border)]">
          <span class="text-sm font-semibold text-[var(--color-text-primary)]">Motiv zuordnen</span>
          <button class="p-1 rounded hover:bg-[var(--color-bg)] transition-colors" @click="close">
            <X class="w-4 h-4" :stroke-width="2" />
          </button>
        </div>
        <div class="px-4 py-2 border-b border-[var(--color-border)]">
          <div class="relative">
            <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-[var(--color-text-tertiary)]" :stroke-width="2" />
            <input v-model="searchQuery" type="text" class="pim-input text-xs pl-8 w-full" placeholder="Motive nach Titel durchsuchen…" />
          </div>
        </div>
        <div class="flex-1 overflow-y-auto p-4">
          <div v-if="loading" class="grid grid-cols-3 sm:grid-cols-4 gap-3">
            <div v-for="i in 8" :key="i" class="pim-skeleton aspect-square rounded-lg" />
          </div>
          <div v-else-if="items.length === 0" class="flex flex-col items-center justify-center py-12 text-center">
            <Images class="w-10 h-10 mb-3 text-[var(--color-text-tertiary)]" :stroke-width="1.5" />
            <p class="text-sm text-[var(--color-text-tertiary)]">Keine Motive gefunden</p>
          </div>
          <div v-else class="grid grid-cols-3 sm:grid-cols-4 gap-3">
            <button
              v-for="motif in items"
              :key="motif.id"
              type="button"
              class="group relative aspect-square bg-[var(--color-bg)] rounded-lg overflow-hidden border border-[var(--color-border)] hover:ring-2 hover:ring-[var(--color-accent)] hover:border-[var(--color-accent)] transition-all"
              @click="select(motif)"
            >
              <img v-if="motif.master_rendition?.thumb_url" :src="motif.master_rendition.thumb_url" class="w-full h-full object-cover" loading="lazy" alt="" />
              <div v-else class="w-full h-full flex items-center justify-center">
                <Images class="w-8 h-8 text-[var(--color-text-tertiary)]" :stroke-width="1.25" />
              </div>
              <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent p-1.5">
                <span class="text-[10px] text-white truncate block">{{ motif.title_de || '(ohne Titel)' }}</span>
                <span class="text-[9px] text-white/70">{{ motif.rendition_count ?? 0 }} Renditions</span>
              </div>
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>
