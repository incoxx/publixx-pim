<script setup>
import { useI18n } from 'vue-i18n'
import { useCatalogStore } from '@/stores/catalog'
import { LayoutGrid, List, X } from 'lucide-vue-next'

const { t } = useI18n()
const store = useCatalogStore()

function changeSort(e) {
  const val = e.target.value
  const [field, order] = val.split(':')
  store.setSort(field, order)
  store.fetchProducts()
}

function clearCategory() {
  store.clearCategory()
  store.fetchProducts()
}
</script>

<template>
  <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <!-- Left: Active filter / breadcrumb -->
    <div class="flex items-center gap-2 min-w-0">
      <div v-if="store.selectedCategoryName" class="badge badge-primary badge-outline gap-1">
        {{ store.selectedCategoryName }}
        <button @click="clearCategory" class="hover:text-error">
          <X class="w-3 h-3" />
        </button>
      </div>
      <div v-if="store.search" class="badge badge-accent badge-outline gap-1">
        "{{ store.search }}"
        <button @click="store.setSearch(''); store.fetchProducts()" class="hover:text-error">
          <X class="w-3 h-3" />
        </button>
      </div>
      <span class="text-sm text-base-content/50">
        {{ store.meta.total }} {{ t('catalog.results') }}
      </span>
    </div>

    <!-- Right: Sort + View mode -->
    <div class="flex items-center gap-2">
      <select
        class="select select-bordered select-xs"
        :value="`${store.sort.field}:${store.sort.order}`"
        @change="changeSort"
      >
        <option value="name:asc">{{ t('catalog.sortName') }} A-Z</option>
        <option value="name:desc">{{ t('catalog.sortName') }} Z-A</option>
        <option value="price:asc">{{ t('catalog.sortPrice') }} ↑</option>
        <option value="price:desc">{{ t('catalog.sortPrice') }} ↓</option>
        <option value="sku:asc">{{ t('catalog.sortSku') }} A-Z</option>
        <option value="updated_at:desc">{{ t('catalog.sortNewest') }}</option>
      </select>

      <div class="join">
        <button
          class="join-item btn btn-xs"
          :class="store.viewMode === 'grid' ? 'btn-primary' : 'btn-ghost'"
          :title="t('catalog.gridView')"
          @click="store.setViewMode('grid')"
        >
          <LayoutGrid class="w-3.5 h-3.5" />
        </button>
        <button
          class="join-item btn btn-xs"
          :class="store.viewMode === 'list' ? 'btn-primary' : 'btn-ghost'"
          :title="t('catalog.listView')"
          @click="store.setViewMode('list')"
        >
          <List class="w-3.5 h-3.5" />
        </button>
      </div>
    </div>
  </div>
</template>
