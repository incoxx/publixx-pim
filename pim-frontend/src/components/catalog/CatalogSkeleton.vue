<script setup>
defineProps({
  mode: { type: String, default: 'grid' },
})
</script>

<template>
  <!-- Grid skeleton -->
  <div v-if="mode === 'grid'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
    <div v-for="i in 8" :key="i" class="card bg-base-100 border border-base-300">
      <figure class="aspect-[4/3] bg-base-200">
        <div class="skeleton w-full h-full"></div>
      </figure>
      <div class="card-body p-4 gap-2">
        <div class="skeleton h-3 w-1/3"></div>
        <div class="skeleton h-4 w-full"></div>
        <div class="skeleton h-3 w-1/4"></div>
        <div class="skeleton h-6 w-1/3 mt-2"></div>
      </div>
    </div>
  </div>

  <!-- List skeleton -->
  <div v-else class="flex flex-col gap-3">
    <div v-for="i in 6" :key="i" class="card card-side bg-base-100 border border-base-300 h-24">
      <figure class="w-24 sm:w-32 flex-none bg-base-200">
        <div class="skeleton w-full h-full"></div>
      </figure>
      <div class="card-body p-3 gap-2">
        <div class="skeleton h-3 w-1/4"></div>
        <div class="skeleton h-4 w-2/3"></div>
        <div class="skeleton h-3 w-1/6"></div>
      </div>
    </div>
  </div>
</template>
