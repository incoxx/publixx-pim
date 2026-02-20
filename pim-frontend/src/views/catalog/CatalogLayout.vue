<script setup>
import { onMounted, ref, provide } from 'vue'
import { useCatalogStore } from '@/stores/catalog'
import CatalogHeader from '@/components/catalog/CatalogHeader.vue'
import CatalogSidebar from '@/components/catalog/CatalogSidebar.vue'
import CatalogWishlistDrawer from '@/components/catalog/CatalogWishlistDrawer.vue'
import CatalogFooter from '@/components/catalog/CatalogFooter.vue'

const store = useCatalogStore()
const sidebarOpen = ref(false)
const wishlistOpen = ref(false)

// Provide wishlist state to child components (Header, WishlistDrawer)
provide('wishlistOpen', wishlistOpen)
provide('sidebarOpen', sidebarOpen)

onMounted(() => {
  store.fetchCategories()
})
</script>

<template>
  <div data-theme="pim-catalog" class="min-h-screen bg-base-200 flex flex-col">
    <!-- Header -->
    <CatalogHeader />

    <!-- Body: Sidebar + Main -->
    <div class="flex flex-1">
      <!-- Desktop sidebar (always visible on lg+) -->
      <aside class="hidden lg:flex flex-col w-72 flex-none bg-base-100 border-r border-base-300">
        <CatalogSidebar />
      </aside>

      <!-- Main content -->
      <main class="flex-1 min-w-0 p-4 lg:p-6">
        <router-view v-slot="{ Component }">
          <transition name="catalog-fade" mode="out-in">
            <component :is="Component" />
          </transition>
        </router-view>
      </main>
    </div>

    <!-- Footer -->
    <CatalogFooter />

    <!-- Mobile sidebar overlay -->
    <Transition name="sidebar-fade">
      <div
        v-if="sidebarOpen"
        class="fixed inset-0 z-50 lg:hidden"
      >
        <div class="absolute inset-0 bg-black/40" @click="sidebarOpen = false"></div>
        <Transition name="sidebar-slide" appear>
          <div class="absolute inset-y-0 left-0 w-72 shadow-2xl">
            <CatalogSidebar />
          </div>
        </Transition>
      </div>
    </Transition>

    <!-- Wishlist overlay -->
    <Transition name="wishlist-fade">
      <div
        v-if="wishlistOpen"
        class="fixed inset-0 z-50"
      >
        <div class="absolute inset-0 bg-black/40" @click="wishlistOpen = false"></div>
        <Transition name="wishlist-slide" appear>
          <div class="absolute inset-y-0 right-0 shadow-2xl">
            <CatalogWishlistDrawer />
          </div>
        </Transition>
      </div>
    </Transition>
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

.sidebar-fade-enter-active,
.sidebar-fade-leave-active {
  transition: opacity 0.3s ease;
}
.sidebar-fade-enter-from,
.sidebar-fade-leave-to {
  opacity: 0;
}
.sidebar-slide-enter-active,
.sidebar-slide-leave-active {
  transition: transform 0.3s ease;
}
.sidebar-slide-enter-from,
.sidebar-slide-leave-to {
  transform: translateX(-100%);
}

.wishlist-fade-enter-active,
.wishlist-fade-leave-active {
  transition: opacity 0.3s ease;
}
.wishlist-fade-enter-from,
.wishlist-fade-leave-to {
  opacity: 0;
}
.wishlist-slide-enter-active,
.wishlist-slide-leave-active {
  transition: transform 0.3s ease;
}
.wishlist-slide-enter-from,
.wishlist-slide-leave-to {
  transform: translateX(100%);
}
</style>
