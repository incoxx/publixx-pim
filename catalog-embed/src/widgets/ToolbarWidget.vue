<script setup>
import { useStore } from '../store.js'
import { icons } from '../icons.js'

const { state, actions } = useStore()

function toggleSort() {
  const order = state.sort.order === 'asc' ? 'desc' : 'asc'
  actions.setSort(state.sort.field, order)
  actions.fetchProducts()
}

function setSortField(e) {
  actions.setSort(e.target.value, state.sort.order)
  actions.fetchProducts()
}

function setViewMode(mode) {
  actions.setViewMode(mode)
}
</script>

<template>
  <div class="pxc-toolbar">
    <!-- Result count -->
    <span class="pxc-toolbar__count">
      {{ state.meta.total }} Produkte
      <template v-if="state.selectedCategoryName"> in <strong>{{ state.selectedCategoryName }}</strong></template>
    </span>

    <div class="pxc-toolbar__actions">
      <!-- Sort -->
      <div class="pxc-toolbar__sort">
        <select :value="state.sort.field" @change="setSortField">
          <option value="name">Name</option>
          <option value="sku">Artikelnummer</option>
          <option value="created_at">Neu</option>
          <option value="updated_at">Aktualisiert</option>
        </select>
        <button @click="toggleSort" :title="state.sort.order === 'asc' ? 'Aufsteigend' : 'Absteigend'">
          <span v-html="state.sort.order === 'asc' ? icons.sortAsc : icons.sortDesc"></span>
        </button>
      </div>

      <!-- View mode -->
      <div class="pxc-toolbar__view">
        <button
          :class="{ 'pxc-toolbar__view--active': state.viewMode === 'grid' }"
          @click="setViewMode('grid')"
          v-html="icons.grid"
        ></button>
        <button
          :class="{ 'pxc-toolbar__view--active': state.viewMode === 'list' }"
          @click="setViewMode('list')"
          v-html="icons.list"
        ></button>
      </div>
    </div>
  </div>
</template>
