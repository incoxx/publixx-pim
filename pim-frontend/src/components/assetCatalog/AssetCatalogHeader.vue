<script setup>
import { useAssetCatalogStore } from '@/stores/assetCatalog'
import { useI18n } from 'vue-i18n'
import CatalogSharedHeader from '@/components/shared/CatalogSharedHeader.vue'

const store = useAssetCatalogStore()
const { t, locale: i18nLocale } = useI18n()

function onSearch(value) {
  store.setSearch(value)
  store.fetchAssets()
}

function onClearSearch() {
  store.setSearch('')
  store.fetchAssets()
}

function onSwitchLocale(loc) {
  store.setLocale(loc)
  i18nLocale.value = loc
  store.fetchAssets()
  store.fetchFolders()
}
</script>

<template>
  <CatalogSharedHeader
    :theme-settings="store.themeSettings"
    :search="store.search"
    :loading="store.loading"
    :wishlist-count="store.wishlistCount"
    :locale="store.locale"
    home-url="/assetpreview"
    :search-placeholder="t('assetCatalog.search')"
    @search="onSearch"
    @clear-search="onClearSearch"
    @switch-locale="onSwitchLocale"
  />
</template>
