<script setup>
import CatalogProductCard from './CatalogProductCard.vue'

defineProps({
  products: { type: Array, required: true },
})

const emit = defineEmits(['view-detail'])
</script>

<template>
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
    <CatalogProductCard
      v-for="(product, index) in products"
      :key="product.id"
      :product="product"
      :index="index"
      @view-detail="emit('view-detail', $event)"
    />
  </div>
</template>
