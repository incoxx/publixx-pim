<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useStore } from '../store.js'
import { icons } from '../icons.js'

const { state, actions, getters } = useStore()
const drawerOpen = ref(false)
const exporting = ref(null)
const linkCopied = ref(false)

// Listen for external open-wishlist events (from WishlistButtonWidget)
function onOpenWishlist() { drawerOpen.value = true }
onMounted(() => window.addEventListener('pxc:open-wishlist', onOpenWishlist))
onUnmounted(() => window.removeEventListener('pxc:open-wishlist', onOpenWishlist))

const wishlistProducts = computed(() => {
  return state.products.filter(p => getters.isInWishlist(p.id))
})

const unloadedCount = computed(() => {
  const loadedIds = new Set(state.products.map(p => p.id))
  return state.wishlistIds.filter(id => !loadedIds.has(id)).length
})

const canCompare = computed(() =>
  state.settings.catalog_compare_enabled &&
  getters.wishlistCount.value >= 2 &&
  getters.wishlistCount.value <= (state.settings.catalog_compare_max_products || 3)
)

function toggle() { drawerOpen.value = !drawerOpen.value }

function formatPrice(price) {
  if (!price) return null
  return new Intl.NumberFormat(state.locale === 'de' ? 'de-DE' : 'en-US', {
    style: 'currency', currency: 'EUR',
  }).format(price)
}

async function downloadPdf() {
  if (exporting.value) return
  exporting.value = 'pdf'
  try { await actions.downloadWishlistPdf() }
  catch (e) { console.error('PDF export failed:', e) }
  finally { exporting.value = null }
}

async function downloadExcel() {
  if (exporting.value) return
  exporting.value = 'excel'
  try { await actions.downloadWishlistExcel() }
  catch (e) { console.error('Excel export failed:', e) }
  finally { exporting.value = null }
}

function openCompare() {
  actions.openCompare([...state.wishlistIds])
}

async function shareWishlist() {
  const base = window.location.href.split('?')[0]
  const url = `${base}?wishlist=${state.wishlistIds.join(',')}`
  try {
    await navigator.clipboard.writeText(url)
    linkCopied.value = true
    setTimeout(() => { linkCopied.value = false }, 2000)
  } catch {}
}
</script>

<template>
  <div class="pxc-wishlist">
    <!-- Toggle button -->
    <button class="pxc-wishlist__toggle" @click="toggle">
      <span v-html="icons.heart"></span>
      <span v-if="getters.wishlistCount.value > 0" class="pxc-wishlist__badge">
        {{ getters.wishlistCount.value }}
      </span>
    </button>

    <!-- Drawer -->
    <div v-if="drawerOpen" class="pxc-wishlist__overlay" @click="toggle"></div>
    <div class="pxc-wishlist__drawer" :class="{ 'pxc-wishlist__drawer--open': drawerOpen }">
      <div class="pxc-wishlist__drawer-header">
        <span v-html="icons.heart" class="pxc-text-accent"></span>
        <span>Merkliste</span>
        <span v-if="getters.wishlistCount.value" class="pxc-wishlist__badge">{{ getters.wishlistCount.value }}</span>
        <button class="pxc-wishlist__close" @click="toggle" v-html="icons.x"></button>
      </div>

      <!-- Empty -->
      <div v-if="getters.wishlistCount.value === 0" class="pxc-wishlist__empty">
        <span v-html="icons.heart" style="width:40px;height:40px;opacity:0.15"></span>
        <p>Merkliste ist leer</p>
        <p class="pxc-text-muted">Klicken Sie auf das Herz-Symbol bei einem Produkt</p>
      </div>

      <!-- Items -->
      <div v-else class="pxc-wishlist__items">
        <div v-for="product in wishlistProducts" :key="product.id" class="pxc-wishlist__item">
          <div class="pxc-wishlist__item-image">
            <img v-if="product.image_url" :src="product.image_url" :alt="product.name" />
            <span v-else v-html="icons.package"></span>
          </div>
          <div class="pxc-wishlist__item-info">
            <p class="pxc-wishlist__item-name">{{ product.name }}</p>
            <p class="pxc-wishlist__item-sku">{{ product.sku }}</p>
            <p v-if="product.price" class="pxc-wishlist__item-price">{{ formatPrice(product.price) }}</p>
          </div>
          <button class="pxc-wishlist__item-remove" @click="actions.toggleWishlist(product.id)">
            <span v-html="icons.trash"></span>
          </button>
        </div>
        <div v-if="unloadedCount > 0" class="pxc-text-muted" style="text-align:center;padding:8px">
          + {{ unloadedCount }} weitere Produkte
        </div>
      </div>

      <!-- Footer actions -->
      <div v-if="getters.wishlistCount.value > 0" class="pxc-wishlist__footer">
        <button v-if="state.settings.catalog_pdf_enabled" class="pxc-btn pxc-btn--primary" @click="downloadPdf" :disabled="!!exporting">
          <span v-html="icons.fileDown"></span>
          {{ exporting === 'pdf' ? 'Exportiere...' : 'Als PDF' }}
        </button>
        <button v-if="state.settings.catalog_excel_export_enabled" class="pxc-btn pxc-btn--outline" @click="downloadExcel" :disabled="!!exporting">
          <span v-html="icons.sheet"></span>
          {{ exporting === 'excel' ? 'Exportiere...' : 'Excel-Export' }}
        </button>
        <button v-if="canCompare" class="pxc-btn pxc-btn--outline" @click="openCompare">
          <span v-html="icons.compare"></span>
          Vergleichen ({{ getters.wishlistCount.value }})
        </button>
        <button v-if="state.settings.catalog_share_wishlist_enabled" class="pxc-btn pxc-btn--ghost" @click="shareWishlist">
          <span v-html="linkCopied ? icons.check : icons.share"></span>
          {{ linkCopied ? 'Link kopiert!' : 'Teilen' }}
        </button>
        <button class="pxc-btn pxc-btn--danger" @click="actions.clearWishlist()">
          <span v-html="icons.trash"></span>
          Leeren
        </button>
      </div>
    </div>
  </div>
</template>
