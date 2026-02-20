<script setup>
import { onMounted, ref } from 'vue'
import { useCatalogStore } from '@/stores/catalog'
import CatalogToolbar from '@/components/catalog/CatalogToolbar.vue'
import CatalogProductGrid from '@/components/catalog/CatalogProductGrid.vue'
import CatalogProductList from '@/components/catalog/CatalogProductList.vue'
import CatalogPagination from '@/components/catalog/CatalogPagination.vue'
import CatalogEmptyState from '@/components/catalog/CatalogEmptyState.vue'
import CatalogSkeleton from '@/components/catalog/CatalogSkeleton.vue'
import CatalogProductModal from '@/components/catalog/CatalogProductModal.vue'

const store = useCatalogStore()

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
