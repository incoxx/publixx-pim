<script setup>
import { onMounted, ref, provide } from 'vue'
import { useAssetCatalogStore } from '@/stores/assetCatalog'
import AssetCatalogHeader from '@/components/assetCatalog/AssetCatalogHeader.vue'
import AssetCatalogSidebar from '@/components/assetCatalog/AssetCatalogSidebar.vue'
import AssetCatalogWishlistDrawer from '@/components/assetCatalog/AssetCatalogWishlistDrawer.vue'
import AssetCatalogFooter from '@/components/assetCatalog/AssetCatalogFooter.vue'

const store = useAssetCatalogStore()
const sidebarOpen = ref(false)
const wishlistOpen = ref(false)

provide('wishlistOpen', wishlistOpen)
provide('sidebarOpen', sidebarOpen)

onMounted(() => {
  store.fetchFolders()
})
</script>

<template>
  <div data-theme="pim-catalog" class="min-h-screen bg-base-200 flex flex-col">
    <AssetCatalogHeader />

    <div class="flex flex-1">
      <aside class="hidden lg:flex flex-col w-72 flex-none bg-base-100 border-r border-base-300">
        <AssetCatalogSidebar />
      </aside>

      <main class="flex-1 min-w-0 p-4 lg:p-6">
        <router-view v-slot="{ Component }">
          <transition name="catalog-fade" mode="out-in">
            <component :is="Component" />
          </transition>
        </router-view>
      </main>
    </div>

    <AssetCatalogFooter />

    <!-- Mobile sidebar overlay -->
    <Transition name="sidebar-fade">
      <div v-if="sidebarOpen" class="fixed inset-0 z-50 lg:hidden">
        <div class="absolute inset-0 bg-black/40" @click="sidebarOpen = false"></div>
        <Transition name="sidebar-slide" appear>
          <div class="absolute inset-y-0 left-0 w-72 shadow-2xl">
            <AssetCatalogSidebar />
          </div>
        </Transition>
      </div>
    </Transition>

    <!-- Wishlist overlay -->
    <Transition name="wishlist-fade">
      <div v-if="wishlistOpen" class="fixed inset-0 z-50">
        <div class="absolute inset-0 bg-black/40" @click="wishlistOpen = false"></div>
        <Transition name="wishlist-slide" appear>
          <div class="absolute inset-y-0 right-0 shadow-2xl">
            <AssetCatalogWishlistDrawer />
          </div>
        </Transition>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.catalog-fade-enter-active,
.catalog-fade-leave-active { transition: opacity 0.2s ease; }
.catalog-fade-enter-from,
.catalog-fade-leave-to { opacity: 0; }

.sidebar-fade-enter-active,
.sidebar-fade-leave-active { transition: opacity 0.3s ease; }
.sidebar-fade-enter-from,
.sidebar-fade-leave-to { opacity: 0; }
.sidebar-slide-enter-active,
.sidebar-slide-leave-active { transition: transform 0.3s ease; }
.sidebar-slide-enter-from,
.sidebar-slide-leave-to { transform: translateX(-100%); }

.wishlist-fade-enter-active,
.wishlist-fade-leave-active { transition: opacity 0.3s ease; }
.wishlist-fade-enter-from,
.wishlist-fade-leave-to { opacity: 0; }
.wishlist-slide-enter-active,
.wishlist-slide-leave-active { transition: transform 0.3s ease; }
.wishlist-slide-enter-from,
.wishlist-slide-leave-to { transform: translateX(100%); }
</style>
