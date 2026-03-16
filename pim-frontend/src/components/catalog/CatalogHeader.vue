<script setup>
import { useCatalogStore } from '@/stores/catalog'
import { useI18n } from 'vue-i18n'
import CatalogSharedHeader from '@/components/shared/CatalogSharedHeader.vue'

const store = useCatalogStore()
const { locale: i18nLocale } = useI18n()

function onSearch(value) {
  store.setSearch(value)
  store.fetchProducts()
}

function onClearSearch() {
  store.setSearch('')
  store.fetchProducts()
}

function onSwitchLocale(loc) {
  store.setLocale(loc)
  i18nLocale.value = loc
  store.fetchProducts()
  store.fetchCategories()
}
</script>

<template>
  <CatalogSharedHeader
    :theme-settings="store.themeSettings"
    :search="store.search"
    :loading="store.loading"
    :wishlist-count="store.wishlistCount"
    :locale="store.locale"
    home-url="/preview"
    @search="onSearch"
    @clear-search="onClearSearch"
    @switch-locale="onSwitchLocale"
  />
</template>
