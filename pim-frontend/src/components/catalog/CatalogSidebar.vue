<script setup>
import { useI18n } from 'vue-i18n'
import { useCatalogStore } from '@/stores/catalog'
import CatalogCategoryTree from './CatalogCategoryTree.vue'
import { FolderTree } from 'lucide-vue-next'

const { t } = useI18n()
const store = useCatalogStore()

function selectAll() {
  store.clearCategory()
  store.fetchProducts()
}
</script>

<template>
  <aside class="bg-base-100 w-72 min-h-full border-r border-base-300 flex flex-col">
    <!-- Header -->
    <div class="p-4 border-b border-base-300">
      <h2 class="font-semibold text-sm text-base-content/80 flex items-center gap-2">
        <FolderTree class="w-4 h-4" />
        {{ store.hierarchyInfo?.hierarchy_name || t('catalog.categories') }}
      </h2>
    </div>

    <!-- All categories -->
    <div class="px-2 pt-2">
      <button
        class="w-full text-left px-3 py-2 rounded-lg text-sm transition-colors"
        :class="
          !store.selectedCategoryId
            ? 'bg-primary/10 text-primary font-medium'
            : 'hover:bg-base-200 text-base-content'
        "
        @click="selectAll"
      >
        {{ t('catalog.allCategories') }}
        <span class="badge badge-sm badge-ghost ml-1">{{ store.meta.total }}</span>
      </button>
    </div>

    <!-- Category tree -->
    <div class="flex-1 overflow-y-auto px-2 py-2">
      <div v-if="store.categoriesLoading" class="space-y-2 px-3">
        <div v-for="i in 6" :key="i" class="skeleton h-6 w-full rounded"></div>
      </div>
      <CatalogCategoryTree
        v-else
        :nodes="store.categories"
        :level="0"
      />
    </div>
  </aside>
</template>
