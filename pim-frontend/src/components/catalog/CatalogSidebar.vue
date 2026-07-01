<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCatalogStore } from '@/stores/catalog'
import CatalogCategoryTree from './CatalogCategoryTree.vue'
import { FolderTree } from 'lucide-vue-next'

const { t } = useI18n()
const store = useCatalogStore()

// Aktiver Menüpunkt: explizite Farben, sonst 10%-Tint der Sidebar-Farbe als Fallback
const activeBg = computed(() =>
  store.themeSettings.color_sidebar_active_bg
  || `color-mix(in srgb, ${store.themeSettings.color_sidebar || '#1B3A5C'} 10%, transparent)`,
)
const activeText = computed(() =>
  store.themeSettings.color_sidebar_active_text || store.themeSettings.color_sidebar || '#1B3A5C',
)

function selectAll() {
  store.clearCategory()
  store.fetchProducts()
}
</script>

<template>
  <aside
    class="bg-base-100 w-full min-h-full flex flex-col"
    :style="{
      backgroundColor: store.themeSettings.color_mobile_menu_bg || undefined,
      color: store.themeSettings.color_mobile_menu_text || undefined,
    }"
  >
    <!-- Header -->
    <div class="p-4 border-b border-base-300">
      <h2 class="font-semibold text-sm text-base-content/80 flex items-center gap-2">
        <FolderTree class="w-4 h-4" />
        {{ t('catalog.categories') }}
      </h2>
    </div>

    <!-- All categories -->
    <div class="px-2 pt-2">
      <button
        class="w-full text-left px-3 py-2 rounded-lg text-sm transition-colors"
        :class="!store.selectedCategoryId ? 'font-medium' : 'hover:bg-base-200 text-base-content'"
        :style="!store.selectedCategoryId ? { backgroundColor: activeBg, color: activeText } : {}"
        @click="selectAll"
      >
        {{ t('catalog.allCategories') }}
        <span class="badge badge-sm badge-ghost ml-1">{{ store.meta.total }}</span>
      </button>
    </div>

    <!-- Category tree -->
    <div
      class="flex-1 overflow-y-auto px-2 py-2 transition-opacity duration-200"
      :class="{ 'opacity-40 pointer-events-none': store.searchActive }"
    >
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
