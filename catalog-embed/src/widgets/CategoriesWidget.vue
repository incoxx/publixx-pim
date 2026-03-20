<script setup>
import { ref, onMounted, watch } from 'vue'
import { useStore } from '../store.js'
import { icons } from '../icons.js'
import CategorySubtree from './CategorySubtree.vue'

const { state, actions, getters } = useStore()
const expandedNodes = ref({})

onMounted(() => {
  if (state.categories.length === 0) actions.fetchCategories()
})

watch(() => state.locale, () => actions.fetchCategories())

function selectCategory(node) {
  actions.setCategory(node.id, node.name)
  actions.fetchProducts()
}

function selectAll() {
  actions.clearCategory()
  actions.fetchProducts()
}

function toggleNode(nodeId) {
  expandedNodes.value[nodeId] = !expandedNodes.value[nodeId]
}

function isExpanded(nodeId) {
  return !!expandedNodes.value[nodeId]
}
</script>

<template>
  <div class="pxc-categories">
    <div class="pxc-categories__header">
      <span v-html="icons.folder"></span>
      <span>Kategorien</span>
    </div>

    <!-- All categories button -->
    <button
      class="pxc-categories__item"
      :class="{ 'pxc-categories__item--active': !state.selectedCategoryId }"
      @click="selectAll"
    >
      Alle Kategorien
      <span class="pxc-categories__count">{{ state.meta.total }}</span>
    </button>

    <!-- Loading -->
    <div v-if="state.categoriesLoading" class="pxc-categories__loading">
      <div v-for="i in 5" :key="i" class="pxc-skeleton" style="height:24px;margin-bottom:4px"></div>
    </div>

    <!-- Tree (recursive) -->
    <template v-else>
      <template v-for="node in state.categories" :key="node.id">
        <div class="pxc-categories__node" :style="{ paddingLeft: '0px' }">
          <div class="pxc-categories__row">
            <button
              v-if="node.children && node.children.length"
              class="pxc-categories__toggle"
              @click="toggleNode(node.id)"
              v-html="isExpanded(node.id) ? icons.chevronDown : icons.chevronRight"
            ></button>
            <span v-else class="pxc-categories__toggle-space"></span>
            <button
              class="pxc-categories__item"
              :class="{ 'pxc-categories__item--active': state.selectedCategoryId === node.id }"
              @click="selectCategory(node)"
            >
              {{ node.name }}
              <span v-if="node.product_count" class="pxc-categories__count">{{ node.product_count }}</span>
            </button>
          </div>
        </div>
        <template v-if="isExpanded(node.id) && node.children">
          <category-subtree
            :nodes="node.children"
            :level="1"
            :expanded-nodes="expandedNodes"
            :selected-category-id="state.selectedCategoryId"
            @toggle="toggleNode"
            @select="selectCategory"
          />
        </template>
      </template>
    </template>
  </div>
</template>
