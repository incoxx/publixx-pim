<script setup>
import { onMounted, ref, provide, watchEffect } from 'vue'
import { useCatalogStore } from '@/stores/catalog'
import CatalogHeader from '@/components/catalog/CatalogHeader.vue'
import CatalogSidebar from '@/components/catalog/CatalogSidebar.vue'
import CatalogWishlistDrawer from '@/components/catalog/CatalogWishlistDrawer.vue'
import CatalogFooter from '@/components/catalog/CatalogFooter.vue'

const store = useCatalogStore()
const sidebarOpen = ref(false)
const wishlistOpen = ref(false)
const themeRoot = ref(null)

// Provide wishlist state to child components (Header, WishlistDrawer)
provide('wishlistOpen', wishlistOpen)
provide('sidebarOpen', sidebarOpen)

// Convert hex color to oklch-compatible HSL for DaisyUI
function hexToHSL(hex) {
  const r = parseInt(hex.slice(1, 3), 16) / 255
  const g = parseInt(hex.slice(3, 5), 16) / 255
  const b = parseInt(hex.slice(5, 7), 16) / 255
  const max = Math.max(r, g, b)
  const min = Math.min(r, g, b)
  let h = 0
  let s = 0
  const l = (max + min) / 2
  if (max !== min) {
    const d = max - min
    s = l > 0.5 ? d / (2 - max - min) : d / (max + min)
    switch (max) {
      case r: h = ((g - b) / d + (g < b ? 6 : 0)) / 6; break
      case g: h = ((b - r) / d + 2) / 6; break
      case b: h = ((r - g) / d + 4) / 6; break
    }
  }
  return `${Math.round(h * 360)} ${Math.round(s * 100)}% ${Math.round(l * 100)}%`
}

let fontLinkEl = null

watchEffect(() => {
  const el = themeRoot.value
  const t = store.themeSettings
  if (!el) return

  // Apply font family
  el.style.fontFamily = `"${t.font_family}", sans-serif`

  // Inject Google Font link
  const isSystemFont = t.font_family?.startsWith('System') || !t.font_family
  if (!isSystemFont) {
    const href = `https://fonts.googleapis.com/css2?family=${encodeURIComponent(t.font_family)}:wght@300;400;500;600;700&display=swap`
    if (!fontLinkEl) {
      fontLinkEl = document.createElement('link')
      fontLinkEl.rel = 'stylesheet'
      document.head.appendChild(fontLinkEl)
    }
    fontLinkEl.href = href
  } else if (fontLinkEl) {
    fontLinkEl.remove()
    fontLinkEl = null
  }

  // Apply DaisyUI color overrides via CSS custom properties
  if (t.color_primary) el.style.setProperty('--p', hexToHSL(t.color_primary))
  if (t.color_accent) el.style.setProperty('--a', hexToHSL(t.color_accent))
  if (t.color_body_text) el.style.setProperty('--bc', hexToHSL(t.color_body_text))
  if (t.color_table_bg) el.style.setProperty('--b2', hexToHSL(t.color_table_bg))

  // Font sizes as CSS vars for components to pick up
  el.style.setProperty('--catalog-heading-size', t.font_heading_size || '1.75rem')
  el.style.setProperty('--catalog-body-size', t.font_body_size || '0.875rem')
})

onMounted(() => {
  store.fetchCategories()
  store.fetchThemeSettings()
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
