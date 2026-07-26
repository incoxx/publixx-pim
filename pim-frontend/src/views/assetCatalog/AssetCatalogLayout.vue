<script setup>
import { onMounted, ref, provide, computed } from 'vue'
import { useAssetCatalogStore } from '@/stores/assetCatalog'
import { useThemeApplicator } from '@/composables/useThemeApplicator'
import AssetCatalogHeader from '@/components/assetCatalog/AssetCatalogHeader.vue'
import AssetCatalogSidebar from '@/components/assetCatalog/AssetCatalogSidebar.vue'
import AssetCatalogWishlistDrawer from '@/components/assetCatalog/AssetCatalogWishlistDrawer.vue'
import AssetCatalogDetailModal from '@/components/assetCatalog/AssetCatalogDetailModal.vue'
import CatalogFooter from '@/components/catalog/CatalogFooter.vue'

const store = useAssetCatalogStore()
const sidebarOpen = ref(false)
const wishlistOpen = ref(false)
const detailAssetId = ref(null)
const showDetail = ref(false)
const themeRoot = ref(null)

// Asset-Detail aus der Merkliste: Merkliste schließen und Detail-Modal öffnen.
// (Der Asset-Katalog hat keine eigene Detailseite — daher Modal statt Navigation.)
function handleOpenDetail(assetId) {
  wishlistOpen.value = false
  detailAssetId.value = assetId
  showDetail.value = true
}

function closeDetail() {
  showDetail.value = false
  detailAssetId.value = null
}

provide('wishlistOpen', wishlistOpen)
provide('sidebarOpen', sidebarOpen)

// Apply theme via composable
const themeSettingsRef = computed(() => store.themeSettings)
useThemeApplicator(themeRoot, themeSettingsRef)

onMounted(async () => {
  await store.fetchThemeSettings()
  store.fetchFolders()
  store.fetchUsageTypes()
})
</script>

<template>
  <div ref="themeRoot" data-theme="pim-catalog" class="min-h-screen bg-base-200 flex flex-col" :style="{ fontSize: store.themeSettings.font_body_size || '0.875rem' }">
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

    <CatalogFooter :theme-settings="store.themeSettings" base-path="/assetpreview" />

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
            <AssetCatalogWishlistDrawer @open-detail="handleOpenDetail" />
          </div>
        </Transition>
      </div>
    </Transition>

    <!-- Asset-Detail aus der Merkliste -->
    <AssetCatalogDetailModal
      :asset-id="detailAssetId"
      :open="showDetail"
      @close="closeDetail"
    />
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
