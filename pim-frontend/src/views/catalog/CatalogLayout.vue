<script setup>
import { onMounted } from 'vue'
import { useCatalogStore } from '@/stores/catalog'
import CatalogHeader from '@/components/catalog/CatalogHeader.vue'
import CatalogSidebar from '@/components/catalog/CatalogSidebar.vue'
import CatalogWishlistDrawer from '@/components/catalog/CatalogWishlistDrawer.vue'
import CatalogFooter from '@/components/catalog/CatalogFooter.vue'

const store = useCatalogStore()

onMounted(() => {
  store.fetchCategories()
})
</script>

<template>
  <div data-theme="pim-catalog" class="min-h-screen bg-base-200 flex flex-col">
    <!-- Outer drawer for wishlist (right side) -->
    <div class="drawer drawer-end flex-1 flex flex-col">
      <input id="catalog-wishlist-drawer" type="checkbox" class="drawer-toggle" />
      <div class="drawer-content flex flex-col flex-1">
        <!-- Inner drawer for sidebar (left side, responsive) -->
        <div class="drawer lg:drawer-open flex-1 flex flex-col">
          <input id="catalog-sidebar-drawer" type="checkbox" class="drawer-toggle" />
          <div class="drawer-content flex flex-col min-h-screen">
            <!-- Header -->
            <CatalogHeader />

            <!-- Main content -->
            <main class="flex-1 p-4 lg:p-6">
              <router-view v-slot="{ Component }">
                <transition name="catalog-fade" mode="out-in">
                  <component :is="Component" />
                </transition>
              </router-view>
            </main>

            <!-- Footer -->
            <CatalogFooter />
          </div>

          <!-- Left sidebar (categories) -->
          <div class="drawer-side z-40">
            <label for="catalog-sidebar-drawer" aria-label="close sidebar" class="drawer-overlay"></label>
            <CatalogSidebar />
          </div>
        </div>
      </div>

      <!-- Right drawer (wishlist) -->
      <div class="drawer-side z-50">
        <label for="catalog-wishlist-drawer" aria-label="close wishlist" class="drawer-overlay"></label>
        <CatalogWishlistDrawer />
      </div>
    </div>
  </div>
</template>

<style scoped>
.catalog-fade-enter-active,
.catalog-fade-leave-active {
  transition: opacity 0.2s ease;
}
.catalog-fade-enter-from,
.catalog-fade-leave-to {
  opacity: 0;
}
</style>
