<script setup>
import { onMounted, ref, reactive, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCatalogStore } from '@/stores/catalog'
import { resolveMediaUrl } from '@/api/catalog'
import { X, Image, Download, FileText } from 'lucide-vue-next'
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
      const selectedIds = String(value).split(',').map(v => decodeURIComponent(v))
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
    const current = String(store.activeFilters[chip.attrId] || '').split(',').filter(Boolean).map(v => decodeURIComponent(v))
    const idx = current.indexOf(String(chip.valueId))
    if (idx !== -1) current.splice(idx, 1)
    if (current.length === 0) {
      store.clearFilter(chip.attrId)
    } else {
      store.setFilter(chip.attrId, current.map(v => encodeURIComponent(v)).join(','))
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

function isPdf(asset) {
  const mime = (asset.mime_type || '').toLowerCase()
  const name = (asset.file_name || '').toLowerCase()
  return mime.includes('pdf') || name.endsWith('.pdf')
}

const brokenAssets = reactive({})

// Kategorie-Assets laden wenn Kategorie gewechselt wird
watch(() => store.selectedCategoryId, (id) => {
  store.fetchCategoryAssets(id)
})

onMounted(() => {
  store.fetchProducts()
  if (store.selectedCategoryId) {
    store.fetchCategoryAssets(store.selectedCategoryId)
  }
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

    <!-- Category Assets -->
    <div
      v-if="store.categoryAssets.length > 0 && !store.searchActive"
      class="px-4 py-3"
    >
      <h3 class="text-sm font-semibold text-base-content/70 mb-2 flex items-center gap-1.5">
        <Image class="w-4 h-4" />
        {{ store.selectedCategoryName ? store.selectedCategoryName + ' — ' : '' }}Medien
      </h3>
      <div class="flex gap-3 overflow-x-auto pb-2">
        <a
          v-for="asset in store.categoryAssets"
          :key="asset.id"
          :href="resolveMediaUrl(asset.original_url)"
          target="_blank"
          class="shrink-0 group relative rounded-lg overflow-hidden border border-base-300 hover:shadow-lg transition-all duration-300"
          :title="asset.title || asset.file_name"
        >
          <div class="w-28 h-28 bg-base-200 flex items-center justify-center">
            <!-- PDF: Icon statt Thumbnail -->
            <template v-if="isPdf(asset)">
              <div class="flex flex-col items-center gap-1">
                <FileText class="w-10 h-10 text-error/60" />
                <span class="text-[9px] text-base-content/50 font-medium uppercase">PDF</span>
              </div>
            </template>
            <template v-else>
              <img
                v-if="asset.thumb_url && !brokenAssets[asset.id]"
                :src="resolveMediaUrl(asset.thumb_url)"
                :alt="asset.alt_text || asset.title || asset.file_name"
                class="w-full h-full object-contain p-1 group-hover:scale-105 transition-transform duration-300"
                loading="lazy"
                @error="brokenAssets[asset.id] = true"
              />
              <Image v-else class="w-8 h-8 text-base-content/15" />
            </template>
          </div>
          <div class="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-colors flex items-end justify-center opacity-0 group-hover:opacity-100 pb-1">
            <span class="text-[10px] text-white bg-black/50 rounded px-1.5 py-0.5 truncate max-w-[100px]">
              {{ asset.title || asset.file_name }}
            </span>
          </div>
        </a>
      </div>
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
