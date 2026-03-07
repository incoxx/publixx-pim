<script setup>
import { onMounted, onUnmounted, ref, provide, watchEffect } from 'vue'
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

// Convert hex color (#RRGGBB) to oklch() string for DaisyUI v5
function hexToOklch(hex) {
  if (!hex || !/^#[0-9A-Fa-f]{6}$/.test(hex)) return null
  // sRGB 0-1
  let r = parseInt(hex.slice(1, 3), 16) / 255
  let g = parseInt(hex.slice(3, 5), 16) / 255
  let b = parseInt(hex.slice(5, 7), 16) / 255
  // linearize sRGB
  r = r <= 0.04045 ? r / 12.92 : Math.pow((r + 0.055) / 1.055, 2.4)
  g = g <= 0.04045 ? g / 12.92 : Math.pow((g + 0.055) / 1.055, 2.4)
  b = b <= 0.04045 ? b / 12.92 : Math.pow((b + 0.055) / 1.055, 2.4)
  // sRGB → XYZ (D65)
  const x = 0.4124564 * r + 0.3575761 * g + 0.1804375 * b
  const y = 0.2126729 * r + 0.7151522 * g + 0.0721750 * b
  const z = 0.0193339 * r + 0.1191920 * g + 0.9503041 * b
  // XYZ → LMS
  const l_ = 0.8189330101 * x + 0.3618667424 * y - 0.1288597137 * z
  const m_ = 0.0329845436 * x + 0.9293118715 * y + 0.0361456387 * z
  const s_ = 0.0482003018 * x + 0.2643662691 * y + 0.6338517070 * z
  // cube root
  const lc = Math.cbrt(l_), mc = Math.cbrt(m_), sc = Math.cbrt(s_)
  // LMS → OKLab
  const L = 0.2104542553 * lc + 0.7936177850 * mc - 0.0040720468 * sc
  const A = 1.9779984951 * lc - 2.4285922050 * mc + 0.4505937099 * sc
  const B = 0.0259040371 * lc + 0.7827717662 * mc - 0.8086757660 * sc
  // OKLab → OKLCh
  const C = Math.sqrt(A * A + B * B)
  let H = Math.atan2(B, A) * (180 / Math.PI)
  if (H < 0) H += 360
  // DaisyUI v5 expects: oklch(L% C H)
  return `oklch(${(L * 100).toFixed(2)}% ${C.toFixed(4)} ${H.toFixed(2)})`
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

  // Apply DaisyUI v5 color overrides (oklch CSS custom properties)
  const colorMap = {
    color_primary: '--color-primary',
    color_accent: '--color-accent',
    color_body_text: '--color-base-content',
    color_table_bg: '--color-base-200',
    color_button: '--color-secondary',
  }
  for (const [key, cssVar] of Object.entries(colorMap)) {
    const oklch = hexToOklch(t[key])
    if (oklch) el.style.setProperty(cssVar, oklch)
  }

  // Custom CSS vars for sidebar and table stripes (consumed by components)
  if (t.color_sidebar) el.style.setProperty('--catalog-sidebar-color', t.color_sidebar)
  if (t.color_table_stripe) el.style.setProperty('--catalog-stripe-color', t.color_table_stripe)

  // Font sizes as CSS vars for components to pick up
  el.style.setProperty('--catalog-heading-size', t.font_heading_size || '1.75rem')
  el.style.setProperty('--catalog-body-size', t.font_body_size || '0.875rem')

  // Header & mobile menu colors
  if (t.color_header_bg) el.style.setProperty('--catalog-header-bg', t.color_header_bg)
  if (t.color_header_text) el.style.setProperty('--catalog-header-text', t.color_header_text)
  if (t.color_mobile_menu_bg) el.style.setProperty('--catalog-mobile-menu-bg', t.color_mobile_menu_bg)
  if (t.color_mobile_menu_text) el.style.setProperty('--catalog-mobile-menu-text', t.color_mobile_menu_text)

  // SEO: update document title and meta description
  const seoTitle = t.seo_title || t.catalog_title || 'Produktkatalog'
  document.title = seoTitle
  let metaDesc = document.querySelector('meta[name="description"]')
  if (t.seo_description) {
    if (!metaDesc) {
      metaDesc = document.createElement('meta')
      metaDesc.setAttribute('name', 'description')
      document.head.appendChild(metaDesc)
    }
    metaDesc.setAttribute('content', t.seo_description)
  }
})

onMounted(async () => {
  await store.fetchThemeSettings()
  store.fetchCategories()
})

onUnmounted(() => {
  if (fontLinkEl) {
    fontLinkEl.remove()
    fontLinkEl = null
  }
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
