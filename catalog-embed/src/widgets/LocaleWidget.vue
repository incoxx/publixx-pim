<script setup>
import { useStore } from '../store.js'
import { icons } from '../icons.js'

const { state, actions } = useStore()

function switchLocale(loc) {
  actions.setLocale(loc)
  actions.fetchProducts()
  actions.fetchCategories()
}
</script>

<template>
  <div class="pxc-locale">
    <span v-html="icons.globe"></span>
    <button
      class="pxc-locale__btn"
      :class="{ 'pxc-locale__btn--active': state.locale === 'de' }"
      @click="switchLocale('de')"
    >DE</button>
    <button
      class="pxc-locale__btn"
      :class="{ 'pxc-locale__btn--active': state.locale === 'en' }"
      @click="switchLocale('en')"
    >EN</button>
  </div>
</template>
