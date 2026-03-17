<script setup>
import { ref, onMounted, watch } from 'vue'
import { useStore } from '../store.js'
import { icons } from '../icons.js'

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

    <!-- Tree -->
    <template v-else>
      <div
        v-for="node in state.categories"
        :key="node.id"
        class="pxc-categories__node"
      >
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

        <!-- Children (level 1) -->
        <template v-if="isExpanded(node.id) && node.children">
          <div
            v-for="child in node.children"
            :key="child.id"
            class="pxc-categories__node pxc-categories__node--l1"
          >
            <div class="pxc-categories__row">
              <button
                v-if="child.children && child.children.length"
                class="pxc-categories__toggle"
                @click="toggleNode(child.id)"
                v-html="isExpanded(child.id) ? icons.chevronDown : icons.chevronRight"
              ></button>
              <span v-else class="pxc-categories__toggle-space"></span>
              <button
                class="pxc-categories__item"
                :class="{ 'pxc-categories__item--active': state.selectedCategoryId === child.id }"
                @click="selectCategory(child)"
              >
                {{ child.name }}
                <span v-if="child.product_count" class="pxc-categories__count">{{ child.product_count }}</span>
              </button>
            </div>

            <!-- Children (level 2) -->
            <template v-if="isExpanded(child.id) && child.children">
              <div
                v-for="grandchild in child.children"
                :key="grandchild.id"
                class="pxc-categories__node pxc-categories__node--l2"
              >
                <div class="pxc-categories__row">
                  <span class="pxc-categories__toggle-space"></span>
                  <button
                    class="pxc-categories__item"
                    :class="{ 'pxc-categories__item--active': state.selectedCategoryId === grandchild.id }"
                    @click="selectCategory(grandchild)"
                  >
                    {{ grandchild.name }}
                    <span v-if="grandchild.product_count" class="pxc-categories__count">{{ grandchild.product_count }}</span>
                  </button>
                </div>
              </div>
            </template>
          </div>
        </template>
      </div>
    </template>
  </div>
</template>
