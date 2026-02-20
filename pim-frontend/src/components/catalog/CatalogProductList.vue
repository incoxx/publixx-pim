<script setup>
import CatalogProductListItem from './CatalogProductListItem.vue'

defineProps({
  products: { type: Array, required: true },
})

const emit = defineEmits(['view-detail'])
</script>

<template>
  <div class="flex flex-col gap-3">
    <CatalogProductListItem
      v-for="(product, index) in products"
      :key="product.id"
      :product="product"
      :index="index"
      @view-detail="emit('view-detail', $event)"
    />
  </div>
</template>
