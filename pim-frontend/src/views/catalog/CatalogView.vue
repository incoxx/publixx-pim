<script setup>
import { onMounted, ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCatalogStore } from '@/stores/catalog'
import { X } from 'lucide-vue-next'
import CatalogToolbar from '@/components/catalog/CatalogToolbar.vue'
import CatalogProductGrid from '@/components/catalog/CatalogProductGrid.vue'
import CatalogProductList from '@/components/catalog/CatalogProductList.vue'
import CatalogPagination from '@/components/catalog/CatalogPagination.vue'
import CatalogEmptyState from '@/components/catalog/CatalogEmptyState.vue'
import CatalogSkeleton from '@/components/catalog/CatalogSkeleton.vue'
import CatalogProductModal from '@/components/catalog/CatalogProductModal.vue'

const { t } = useI18n()
const store = useCatalogStore()

// Build human-readable active filter chips
const filterChips = computed(() => {
  const chips = []
  for (const [attrId, value] of Object.entries(store.activeFilters)) {
    const facet = store.facets.find(f => f.attribute_id === attrId)
    if (!facet) continue
    const label = facet.label
    if (facet.data_type === 'ValueList' || facet.data_type === 'Text') {
      const selectedIds = String(value).split(',')
      for (const id of selectedIds) {
        const entry = (facet.values || []).find(v => String(v.value_id || v.value) === id)
        chips.push({ attrId, valueId: id, label, value: entry?.value || id })
      }
    } else if (facet.data_type === 'Boolean') {
      chips.push({ attrId, valueId: null, label, value: value === '1' ? 'Ja' : 'Nein' })
    } else {
      const parts = String(value).split(':')
      const rangeText = `${parts[0] || '…'} – ${parts[1] || '…'}${facet.unit ? ' ' + facet.unit : ''}`
      chips.push({ attrId, valueId: null, label, value: rangeText })
    }
  }
  return chips
})

function removeChip(chip) {
  if (chip.valueId && (chip.valueId !== null)) {
    // Remove single value from multi-select
    const current = String(store.activeFilters[chip.attrId] || '').split(',').filter(Boolean)
    const idx = current.indexOf(String(chip.valueId))
    if (idx !== -1) current.splice(idx, 1)
    if (current.length === 0) {
      store.clearFilter(chip.attrId)
    } else {
      store.setFilter(chip.attrId, current.join(','))
    }
  } else {
    store.clearFilter(chip.attrId)
  }
  store.fetchProducts()
}

const modalOpen = ref(false)
const modalProductId = ref(null)

function openDetail(product) {
  modalProductId.value = product.id
  modalOpen.value = true
}

function closeDetail() {
  modalOpen.value = false
  modalProductId.value = null
}

onMounted(() => {
  store.fetchProducts()
})
</script>

<template>
  <div>
    <!-- Toolbar -->
    <CatalogToolbar />

    <!-- Active filter chips -->
    <div
      v-if="filterChips.length > 0"
      class="flex items-center gap-2 px-4 py-2 overflow-x-auto flex-nowrap"
    >
      <span class="text-xs text-base-content/50 shrink-0">{{ t('catalog.activeFilters') }}:</span>
      <button
        v-for="(chip, idx) in filterChips"
        :key="idx"
        class="badge badge-sm badge-outline gap-1 shrink-0 hover:badge-error transition-colors"
        @click="removeChip(chip)"
      >
        <span class="max-w-[120px] truncate">{{ chip.label }}: {{ chip.value }}</span>
        <X class="w-3 h-3" />
      </button>
      <button
        class="text-xs text-primary hover:underline shrink-0"
        @click="store.clearAllFilters(); store.fetchProducts()"
      >
        {{ t('catalog.clearAllFilters') }}
      </button>
    </div>

    <!-- Loading skeleton -->
    <CatalogSkeleton v-if="store.loading" :mode="store.viewMode" />

    <!-- Empty state -->
    <CatalogEmptyState v-else-if="store.isEmpty" />

    <!-- Products -->
    <template v-else>
      <CatalogProductGrid
        v-if="store.viewMode === 'grid'"
        :products="store.products"
        @view-detail="openDetail"
      />
      <CatalogProductList
        v-else
        :products="store.products"
        @view-detail="openDetail"
      />
    </template>

    <!-- Pagination -->
    <CatalogPagination />

    <!-- Product detail modal -->
    <CatalogProductModal
      :product-id="modalProductId"
      :open="modalOpen"
      @close="closeDetail"
    />
  </div>
</template>
