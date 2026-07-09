<script setup>
import { ref, onMounted } from 'vue'
import { collectionItems as collectionItemsApi } from '@/api/collections'
import productsApi from '@/api/products'
import { Check, Search } from 'lucide-vue-next'
import EntityPickerDialog from '@/components/shared/EntityPickerDialog.vue'

const props = defineProps({
  collectionId: { type: String, required: true },
})

const emit = defineEmits(['resolved'])

const items = ref([])
const loading = ref(false)
const manualSearchItemId = ref(null)

async function load() {
  loading.value = true
  try {
    const { data } = await collectionItemsApi.needsReview(props.collectionId)
    items.value = data.data
  } finally {
    loading.value = false
  }
}

async function confirm(item, productId) {
  await collectionItemsApi.confirmMatch(props.collectionId, item.id, productId)
  items.value = items.value.filter((i) => i.id !== item.id)
  manualSearchItemId.value = null
  emit('resolved', item.id)
}

function productFetcher(query, page) {
  return productsApi.list({ search: query || undefined, page, perPage: 20 }).then(({ data }) => ({
    items: data.data,
    meta: data.meta,
  }))
}

function onManualPick(picked, item) {
  const product = picked[0]
  if (product) confirm(item, product.id)
}

defineExpose({ load })

onMounted(load)
</script>

<template>
  <div v-if="items.length" class="pim-card p-4 space-y-3 border-l-4 border-l-[var(--color-warning,#f59e0b)]">
    <h4 class="text-xs font-semibold text-[var(--color-text-secondary)] uppercase tracking-wide">
      Zu bestätigende Positionen ({{ items.length }})
    </h4>
    <div v-for="item in items" :key="item.id" class="border border-[var(--color-border)] rounded-lg p-3 space-y-2">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-xs font-medium text-[var(--color-text-primary)]">
            {{ item.snapshot?.sku_candidate || item.snapshot?.name || 'Unbekannte Position' }}
          </p>
          <p class="text-[10px] text-[var(--color-text-tertiary)]">
            {{ item.import_match_status === 'unresolved' ? 'Kein Treffer gefunden' : 'Unsichere Zuordnung' }}
            · Menge {{ item.quantity }}
          </p>
        </div>
        <button class="pim-btn pim-btn-ghost text-xs" @click="manualSearchItemId = item.id">
          <Search class="w-3.5 h-3.5" :stroke-width="2" /> Manuell suchen
        </button>
      </div>

      <div v-if="item.import_fuzzy_candidates?.length" class="space-y-1">
        <button
          v-for="candidate in item.import_fuzzy_candidates"
          :key="candidate.product_id"
          class="flex items-center justify-between w-full px-3 py-1.5 rounded-md text-left hover:bg-[var(--color-bg)] text-xs"
          @click="confirm(item, candidate.product_id)"
        >
          <span>{{ candidate.name }} <span class="text-[var(--color-text-tertiary)] font-mono">({{ candidate.sku }})</span></span>
          <span class="flex items-center gap-1 text-[var(--color-accent)]">
            <Check class="w-3.5 h-3.5" :stroke-width="2.5" /> {{ Math.round(candidate.similarity * 100) }}%
          </span>
        </button>
      </div>
    </div>

    <EntityPickerDialog
      :modelValue="!!manualSearchItemId"
      title="Produkt manuell zuordnen"
      :fetcher="productFetcher"
      :labelFn="p => p.name"
      :sublabelFn="p => p.sku"
      @update:modelValue="v => { if (!v) manualSearchItemId = null }"
      @confirm="picked => onManualPick(picked, items.find(i => i.id === manualSearchItemId))"
    />
  </div>
</template>
