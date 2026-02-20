<script setup>
import { useCatalogStore } from '@/stores/catalog'
import { useI18n } from 'vue-i18n'

const store = useCatalogStore()
const { locale: i18nLocale } = useI18n()

function switchLocale(loc) {
  store.setLocale(loc)
  i18nLocale.value = loc
  store.fetchProducts()
  store.fetchCategories()
}
</script>

<template>
  <div class="join">
    <button
      class="join-item btn btn-xs"
      :class="store.locale === 'de' ? 'btn-primary' : 'btn-ghost'"
      @click="switchLocale('de')"
    >
      DE
    </button>
    <button
      class="join-item btn btn-xs"
      :class="store.locale === 'en' ? 'btn-primary' : 'btn-ghost'"
      @click="switchLocale('en')"
    >
      EN
    </button>
  </div>
</template>
