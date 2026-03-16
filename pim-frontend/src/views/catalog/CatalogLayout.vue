<script setup>
import { onMounted, ref, provide, computed } from 'vue'
import { useCatalogStore } from '@/stores/catalog'
import { useThemeApplicator } from '@/composables/useThemeApplicator'
import CatalogHeader from '@/components/catalog/CatalogHeader.vue'
import CatalogSidebar from '@/components/catalog/CatalogSidebar.vue'
import CatalogWishlistDrawer from '@/components/catalog/CatalogWishlistDrawer.vue'
import CatalogCompareModal from '@/components/catalog/CatalogCompareModal.vue'
import CatalogFooter from '@/components/catalog/CatalogFooter.vue'

const store = useCatalogStore()
const sidebarOpen = ref(false)
const wishlistOpen = ref(false)
const showCompare = ref(false)
const compareProductIds = ref([])
const themeRoot = ref(null)

function handleOpenCompare(ids) {
  compareProductIds.value = ids
  showCompare.value = true
}

// Provide wishlist state to child components (Header, WishlistDrawer)
provide('wishlistOpen', wishlistOpen)
provide('sidebarOpen', sidebarOpen)

// Apply theme via composable
const themeSettingsRef = computed(() => store.themeSettings)
useThemeApplicator(themeRoot, themeSettingsRef)

onMounted(async () => {
  store.importWishlistFromUrl()
  await store.fetchThemeSettings()
  store.fetchCategories()
})
</script>

<template>
  <div ref="themeRoot" data-theme="pim-catalog" class="min-h-screen bg-base-200 flex flex-col" :style="{ fontSize: store.themeSettings.font_body_size || '0.875rem' }">
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
            <CatalogWishlistDrawer @open-compare="handleOpenCompare" />
          </div>
        </Transition>
      </div>
    </Transition>

    <!-- Compare modal -->
    <CatalogCompareModal
      :open="showCompare"
      :product-ids="compareProductIds"
      @update:open="showCompare = $event"
    />
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
