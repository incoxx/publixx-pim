<script setup>
import { ref, watch, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAssetCatalogStore } from '@/stores/assetCatalog'
import { Search, Grid, List, SlidersHorizontal } from 'lucide-vue-next'

const { t } = useI18n()
const store = useAssetCatalogStore()
const searchInput = ref(store.search)

let debounceTimer = null
watch(searchInput, (val) => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    store.setSearch(val)
    store.fetchAssets()
  }, 300)
})
onUnmounted(() => clearTimeout(debounceTimer))

function setSort(field, order) {
  store.setSort(field, order)
  store.fetchAssets()
}

function setUsagePurpose(val) {
  store.setUsagePurpose(val || null)
  store.fetchAssets()
}

function setMediaType(val) {
  store.setMediaType(val || null)
  store.fetchAssets()
}
</script>

<template>
  <div class="flex flex-wrap items-center gap-3 mb-4">
    <!-- Search -->
    <div class="relative flex-1 min-w-[200px]">
      <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-base-content/40" />
      <input
        v-model="searchInput"
        type="text"
        :placeholder="t('assetCatalog.search')"
        class="input input-bordered input-sm w-full pl-9"
      />
    </div>

    <!-- Usage filter -->
    <select
      class="select select-bordered select-sm"
      :value="store.usagePurposeFilter || ''"
      @change="setUsagePurpose($event.target.value)"
    >
      <option value="">{{ t('assetCatalog.allUsage') }}</option>
      <option value="print">{{ t('assetCatalog.usagePrint') }}</option>
      <option value="web">{{ t('assetCatalog.usageWeb') }}</option>
    </select>

    <!-- Media type filter -->
    <select
      class="select select-bordered select-sm"
      :value="store.mediaTypeFilter || ''"
      @change="setMediaType($event.target.value)"
    >
      <option value="">{{ t('assetCatalog.allTypes') }}</option>
      <option value="image">{{ t('assetCatalog.images') }}</option>
      <option value="document">{{ t('assetCatalog.documents') }}</option>
    </select>

    <!-- Sort -->
    <select
      class="select select-bordered select-sm"
      @change="
        const [f, o] = $event.target.value.split(':');
        setSort(f, o)
      "
    >
      <option value="created_at:desc">{{ t('assetCatalog.sortDate') }} ↓</option>
      <option value="created_at:asc">{{ t('assetCatalog.sortDate') }} ↑</option>
      <option value="name:asc">{{ t('assetCatalog.sortName') }} A-Z</option>
      <option value="name:desc">{{ t('assetCatalog.sortName') }} Z-A</option>
      <option value="file_size:desc">{{ t('assetCatalog.sortSize') }} ↓</option>
      <option value="file_size:asc">{{ t('assetCatalog.sortSize') }} ↑</option>
    </select>

    <!-- View toggle -->
    <div class="flex gap-1">
      <button
        class="btn btn-sm btn-ghost"
        :class="store.viewMode === 'grid' ? 'btn-active' : ''"
        @click="store.setViewMode('grid')"
        :title="t('assetCatalog.gridView')"
      >
        <Grid class="w-4 h-4" />
      </button>
      <button
        class="btn btn-sm btn-ghost"
        :class="store.viewMode === 'list' ? 'btn-active' : ''"
        @click="store.setViewMode('list')"
        :title="t('assetCatalog.listView')"
      >
        <List class="w-4 h-4" />
      </button>
    </div>
  </div>
</template>
