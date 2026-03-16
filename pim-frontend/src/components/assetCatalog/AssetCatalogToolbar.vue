<script setup>
import { useI18n } from 'vue-i18n'
import { useAssetCatalogStore } from '@/stores/assetCatalog'
import { LayoutGrid, List, X } from 'lucide-vue-next'

const { t } = useI18n()
const store = useAssetCatalogStore()

function changeSort(e) {
  const val = e.target.value
  const [field, order] = val.split(':')
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

function clearFolder() {
  store.clearFolder()
  store.fetchAssets()
}
</script>

<template>
  <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <!-- Left: Active filter / breadcrumb -->
    <div class="flex items-center gap-2 min-w-0">
      <div v-if="store.selectedFolderName" class="badge badge-primary badge-outline gap-1">
        {{ store.selectedFolderName }}
        <button @click="clearFolder" class="hover:text-error">
          <X class="w-3 h-3" />
        </button>
      </div>
      <div v-if="store.search" class="badge badge-accent badge-outline gap-1">
        "{{ store.search }}"
        <button @click="store.setSearch(''); store.fetchAssets()" class="hover:text-error">
          <X class="w-3 h-3" />
        </button>
      </div>
      <span class="text-sm text-base-content/50">
        {{ store.meta.total }} {{ t('catalog.results') }}
      </span>
    </div>

    <!-- Center: Search mode toggle (slot) -->
    <slot name="search-toggle" />

    <!-- Right: Filters + Sort + View mode -->
    <div class="flex items-center gap-2">
      <!-- Usage filter -->
      <select
        class="select select-bordered select-xs"
        :value="store.usagePurposeFilter || ''"
        @change="setUsagePurpose($event.target.value)"
      >
        <option value="">{{ t('assetCatalog.allUsage') }}</option>
        <option value="print">{{ t('assetCatalog.usagePrint') }}</option>
        <option value="web">{{ t('assetCatalog.usageWeb') }}</option>
      </select>

      <!-- Media type filter -->
      <select
        class="select select-bordered select-xs"
        :value="store.mediaTypeFilter || ''"
        @change="setMediaType($event.target.value)"
      >
        <option value="">{{ t('assetCatalog.allTypes') }}</option>
        <option value="image">{{ t('assetCatalog.images') }}</option>
        <option value="document">{{ t('assetCatalog.documents') }}</option>
      </select>

      <select
        class="select select-bordered select-xs"
        :value="`${store.sort.field}:${store.sort.order}`"
        @change="changeSort"
      >
        <option value="created_at:desc">{{ t('assetCatalog.sortDate') }} ↓</option>
        <option value="created_at:asc">{{ t('assetCatalog.sortDate') }} ↑</option>
        <option value="name:asc">{{ t('assetCatalog.sortName') }} A-Z</option>
        <option value="name:desc">{{ t('assetCatalog.sortName') }} Z-A</option>
        <option value="file_size:desc">{{ t('assetCatalog.sortSize') }} ↓</option>
        <option value="file_size:asc">{{ t('assetCatalog.sortSize') }} ↑</option>
      </select>

      <div class="join">
        <button
          class="join-item btn btn-xs"
          :class="store.viewMode === 'grid' ? 'btn-primary' : 'btn-ghost'"
          :title="t('assetCatalog.gridView')"
          @click="store.setViewMode('grid')"
        >
          <LayoutGrid class="w-3.5 h-3.5" />
        </button>
        <button
          class="join-item btn btn-xs"
          :class="store.viewMode === 'list' ? 'btn-primary' : 'btn-ghost'"
          :title="t('assetCatalog.listView')"
          @click="store.setViewMode('list')"
        >
          <List class="w-3.5 h-3.5" />
        </button>
      </div>
    </div>
  </div>
</template>
