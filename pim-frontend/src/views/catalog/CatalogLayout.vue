<script setup>
import { onMounted, ref, provide, computed } from 'vue'
import { useCatalogStore } from '@/stores/catalog'
import { useThemeApplicator } from '@/composables/useThemeApplicator'
import CatalogHeader from '@/components/catalog/CatalogHeader.vue'
import CatalogSidebar from '@/components/catalog/CatalogSidebar.vue'
import CatalogFacets from '@/components/catalog/CatalogFacets.vue'
import CatalogWishlistDrawer from '@/components/catalog/CatalogWishlistDrawer.vue'
import CatalogCompareModal from '@/components/catalog/CatalogCompareModal.vue'
import CatalogFooter from '@/components/catalog/CatalogFooter.vue'
import CatalogShareGate from '@/components/catalog/CatalogShareGate.vue'
import { SlidersHorizontal } from 'lucide-vue-next'

const store = useCatalogStore()
const sidebarOpen = ref(false)
const wishlistOpen = ref(false)

// Freigabelink-Zugang (?share=<token>): Gate blendet sich über den Katalog, bis
// Token/Passwort geprüft sind. Erst danach werden die (ggf. login-gesicherten) Daten geladen.
const shareToken = ref(store.getShareTokenFromUrl())
const shareGateActive = ref(!!shareToken.value)
const showCompare = ref(false)
const compareProductIds = ref([])
const themeRoot = ref(null)

// Resizable sidebar
const sidebarWidth = ref(288) // 18rem = 288px default
const isResizingSidebar = ref(false)

function startSidebarResize(e) {
  isResizingSidebar.value = true
  const startX = e.clientX
  const startWidth = sidebarWidth.value

  function onMouseMove(e) {
    const delta = e.clientX - startX
    sidebarWidth.value = Math.max(200, Math.min(500, startWidth + delta))
  }
  function onMouseUp() {
    isResizingSidebar.value = false
    document.removeEventListener('mousemove', onMouseMove)
    document.removeEventListener('mouseup', onMouseUp)
    document.body.style.cursor = ''
    document.body.style.userSelect = ''
  }
  document.body.style.cursor = 'col-resize'
  document.body.style.userSelect = 'none'
  document.addEventListener('mousemove', onMouseMove)
  document.addEventListener('mouseup', onMouseUp)
}

// Facet panel (right side)
const facetPanelOpen = ref(true)
const facetPanelWidth = ref(300)
const isResizingFacets = ref(false)

function toggleFacetPanel() {
  facetPanelOpen.value = !facetPanelOpen.value
}

function startFacetResize(e) {
  isResizingFacets.value = true
  const startX = e.clientX
  const startWidth = facetPanelWidth.value

  function onMouseMove(e) {
    // Dragging left increases width
    const delta = startX - e.clientX
    facetPanelWidth.value = Math.max(220, Math.min(500, startWidth + delta))
  }
  function onMouseUp() {
    isResizingFacets.value = false
    document.removeEventListener('mousemove', onMouseMove)
    document.removeEventListener('mouseup', onMouseUp)
    document.body.style.cursor = ''
    document.body.style.userSelect = ''
  }
  document.body.style.cursor = 'col-resize'
  document.body.style.userSelect = 'none'
  document.addEventListener('mousemove', onMouseMove)
  document.addEventListener('mouseup', onMouseUp)
}

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

function openWishlistIfCollectionLoaded() {
  if (store.collectionInfo && store.collectionInfo.count > 0) {
    wishlistOpen.value = true
  }
}

async function onShareUnlocked() {
  // Nach erfolgreicher Freischaltung: Gate entfernen, Daten laden, Merkliste zeigen.
  // Produkte neu laden — der erste Versuch von CatalogView lief noch vor dem Entsperren
  // (im login-gesicherten Modus mit 401).
  shareGateActive.value = false
  store.clearShareParamFromUrl()
  store.fetchProducts()
  store.fetchCategories()
  store.fetchFacets()
  openWishlistIfCollectionLoaded()
}

onMounted(async () => {
  // Theme zuerst — enthält access_mode und hierarchy_id (von fetchCategories benötigt).
  await store.fetchThemeSettings()

  // Freigabelink: Gate übernimmt das Laden nach dem Entsperren (siehe onShareUnlocked).
  if (shareGateActive.value) return

  // Collection-Deeplink (?collection=<id>) ersetzt die Merkliste durch die
  // kuratierte Produktliste; ?wishlist=<ids> ergänzt sie wie bisher.
  await store.importCollectionFromUrl()
  store.importWishlistFromUrl()
  openWishlistIfCollectionLoaded()
  store.fetchCategories()
  store.fetchFacets()
})
</script>

<template>
  <div
    ref="themeRoot"
    data-theme="pim-catalog"
    class="min-h-screen bg-base-200 flex flex-col"
    :style="{
      fontSize: store.themeSettings.font_body_size || '0.875rem',
      backgroundColor: store.themeSettings.color_grid_bg || undefined,
    }"
  >
    <!-- Header -->
    <CatalogHeader />

    <!-- Body: Sidebar + Main -->
    <div class="flex flex-1">
      <!-- Desktop sidebar (always visible on lg+, resizable) -->
      <aside
        class="hidden lg:flex flex-col flex-none bg-base-100 border-r border-base-300 relative"
        :style="{ width: sidebarWidth + 'px' }"
      >
        <CatalogSidebar />
        <!-- Resize handle -->
        <div
          class="absolute top-0 right-0 w-1.5 h-full cursor-col-resize hover:bg-primary/20 active:bg-primary/30 transition-colors z-10 group"
          @mousedown.prevent="startSidebarResize"
        >
          <div class="absolute right-0 top-1/2 -translate-y-1/2 w-1 h-8 rounded-full bg-base-content/10 group-hover:bg-primary/40 transition-colors"></div>
        </div>
      </aside>

      <!-- Main content -->
      <main class="flex-1 min-w-0 p-4 lg:p-6">
        <router-view v-slot="{ Component }">
          <transition name="catalog-fade" mode="out-in">
            <component :is="Component" />
          </transition>
        </router-view>
      </main>

      <!-- Facet filter toggle button (visible when panel is closed) -->
      <button
        v-if="!facetPanelOpen && store.facets.length > 0 && !store.searchActive"
        class="hidden lg:flex items-center gap-1.5 px-3 py-2 bg-primary text-primary-content rounded-l-lg fixed right-0 top-1/3 z-30 shadow-lg hover:bg-primary/90 transition-colors cursor-pointer"
        style="writing-mode: vertical-rl; text-orientation: mixed;"
        @click="toggleFacetPanel"
      >
        <SlidersHorizontal class="w-4 h-4 rotate-90" />
        <span class="text-xs font-medium">Filter</span>
        <span v-if="store.activeFilterCount > 0" class="badge badge-xs badge-accent">{{ store.activeFilterCount }}</span>
      </button>

      <!-- Facet panel (right side, collapsible + resizable) -->
      <aside
        v-if="facetPanelOpen && !store.searchActive"
        class="hidden lg:flex flex-col flex-none bg-base-100 border-l border-base-300 relative"
        :style="{
          width: facetPanelWidth + 'px',
          backgroundColor: store.themeSettings.color_facets_bg || undefined,
          color: store.themeSettings.color_facets_text || undefined,
        }"
      >
        <!-- Resize handle (left edge) -->
        <div
          class="absolute top-0 left-0 w-1.5 h-full cursor-col-resize hover:bg-primary/20 active:bg-primary/30 transition-colors z-10 group"
          @mousedown.prevent="startFacetResize"
        >
          <div class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-8 rounded-full bg-base-content/10 group-hover:bg-primary/40 transition-colors"></div>
        </div>

        <!-- Panel header -->
        <div class="p-3 border-b border-base-300 flex items-center justify-between">
          <h3 class="font-semibold text-sm text-base-content/80 flex items-center gap-2">
            <SlidersHorizontal class="w-4 h-4" />
            Filter
          </h3>
          <button
            class="btn btn-ghost btn-xs btn-circle"
            @click="facetPanelOpen = false"
            title="Filter schließen"
          >✕</button>
        </div>

        <!-- Facets content (scrollable) -->
        <div class="flex-1 overflow-y-auto">
          <CatalogFacets />
        </div>
      </aside>
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
          <div class="absolute inset-y-0 left-0 w-72 shadow-2xl overflow-y-auto bg-base-100">
            <CatalogSidebar />
            <CatalogFacets v-if="!store.searchActive" />
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

    <!-- Freigabelink-Gate (Token/Passwort) — deckt den Katalog bis zur Freischaltung ab -->
    <CatalogShareGate
      v-if="shareGateActive"
      :token="shareToken"
      @unlocked="onShareUnlocked"
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
