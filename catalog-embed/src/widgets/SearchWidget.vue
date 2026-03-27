<script setup>
import { ref } from 'vue'
import { useStore } from '../store.js'
import { icons } from '../icons.js'

const { state, actions } = useStore()
const inputValue = ref(state.search)
let debounceTimer = null

function onInput(e) {
  inputValue.value = e.target.value
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    actions.setSearch(inputValue.value)
    actions.fetchProducts()
  }, 300)
}

function onClear() {
  inputValue.value = ''
  actions.setSearch('')
  actions.fetchProducts()
}

function onSubmit(e) {
  e.preventDefault()
  clearTimeout(debounceTimer)
  actions.setSearch(inputValue.value)
  actions.fetchProducts()
}
</script>

<template>
  <form class="pxc-search" @submit="onSubmit">
    <div class="pxc-search__wrapper">
      <span class="pxc-search__icon" v-html="icons.search"></span>
      <input
        type="text"
        class="pxc-search__input"
        :value="inputValue"
        placeholder="Produkte suchen (Name, Artikelnr., EAN)..."
        @input="onInput"
      />
      <button
        v-if="inputValue"
        type="button"
        class="pxc-search__clear"
        @click="onClear"
        v-html="icons.x"
      ></button>
      <span v-if="state.loading" class="pxc-search__loader" v-html="icons.loader"></span>
    </div>
  </form>
</template>
