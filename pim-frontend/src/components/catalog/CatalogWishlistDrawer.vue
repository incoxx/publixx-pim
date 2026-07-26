<script setup>
import { computed, inject, ref, watch, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useCatalogStore } from '@/stores/catalog'
import { Heart, Trash2, X, Package, FileDown, Sheet, GitCompareArrows, Share2, Check } from 'lucide-vue-next'
import catalogApi from '@/api/catalog'
import { triggerDownload } from '@/utils/download'

const emit = defineEmits(['open-compare'])

const { t } = useI18n()
const router = useRouter()
const store = useCatalogStore()
const wishlistOpen = inject('wishlistOpen')

const exporting = ref(null) // 'pdf' | 'excel' | null
const linkCopied = ref(false)

// Produktdetail öffnen: Merkliste schließen und zur Detailseite navigieren.
function openDetail(product) {
  wishlistOpen.value = false
  router.push({ name: 'catalog-product', params: { id: product.id } })
}

// Alle Merklisten-Produkte (auch solche, die nicht auf der aktuellen Grid-Seite
// liegen) — der Store lädt fehlende serverseitig nach.
const wishlistProducts = computed(() => store.wishlistProducts)

// Restliche IDs ohne Produktdaten (inaktiv/gelöscht oder jenseits des Limits)
const unloadedCount = computed(() => store.wishlistUnresolvedCount)

// Fehlende Merklisten-Produkte nachladen, sobald sich die Liste ändert.
onMounted(() => store.fetchWishlistProducts())
watch(
  () => store.wishlistIds.slice(),
  () => store.fetchWishlistProducts(),
  { deep: true },
)

const canCompare = computed(() =>
  store.themeSettings.catalog_compare_enabled &&
  store.wishlistCount >= 2 &&
  store.wishlistCount <= (store.themeSettings.catalog_compare_max_products || 3)
)

function closeDrawer() {
  wishlistOpen.value = false
}

function formatPrice(price) {
  if (!price) return null
  return new Intl.NumberFormat(store.locale === 'de' ? 'de-DE' : 'en-US', {
    style: 'currency',
    currency: 'EUR',
  }).format(price)
}

async function downloadWishlistPdf() {
  if (exporting.value) return
  exporting.value = 'pdf'
  try {
    const resp = await catalogApi.downloadWishlistPdf(store.wishlistIds, store.locale)
    triggerDownload(resp.data, `merkliste-${new Date().toISOString().slice(0, 10)}.pdf`)
  } catch (e) {
    console.error('Wishlist PDF export failed:', e)
  } finally {
    exporting.value = null
  }
}

async function downloadWishlistExcel() {
  if (exporting.value) return
  exporting.value = 'excel'
  try {
    const resp = await catalogApi.downloadWishlistExcel(store.wishlistIds)
    triggerDownload(resp.data, `merkliste-${new Date().toISOString().slice(0, 10)}.xlsx`)
  } catch (e) {
    console.error('Wishlist Excel export failed:', e)
  } finally {
    exporting.value = null
  }
}

function openCompare() {
  emit('open-compare', [...store.wishlistIds])
}

async function shareWishlist() {
  const url = `${window.location.origin}${window.location.pathname}?wishlist=${store.wishlistIds.join(',')}`
  try {
    await navigator.clipboard.writeText(url)
    linkCopied.value = true
    setTimeout(() => { linkCopied.value = false }, 2000)
  } catch (e) {
    console.error('Failed to copy to clipboard:', e)
  }
}
</script>

<template>
  <div class="bg-base-100 w-[85vw] max-w-80 min-h-full border-l border-base-300 flex flex-col">
    <!-- Header -->
    <div class="p-4 border-b border-base-300 flex items-center justify-between">
      <h2 class="font-semibold text-sm flex items-center gap-2">
        <Heart class="w-4 h-4 text-error" />
        {{ t('catalog.wishlist') }}
        <span v-if="store.wishlistCount > 0" class="badge badge-sm badge-ghost">
          {{ store.wishlistCount }}
        </span>
      </h2>
      <button class="btn btn-ghost btn-sm btn-circle" @click="closeDrawer">
        <X class="w-4 h-4" />
      </button>
    </div>

    <!-- Items -->
    <div class="flex-1 overflow-y-auto">
      <!-- Empty state -->
      <div
        v-if="store.wishlistCount === 0"
        class="flex flex-col items-center justify-center py-16 px-6 text-center"
      >
        <Heart class="w-10 h-10 text-base-content/15 mb-4" />
        <p class="text-sm font-medium text-base-content/50 mb-1">
          {{ t('catalog.wishlistEmpty') }}
        </p>
        <p class="text-xs text-base-content/30">
          {{ t('catalog.wishlistEmptyHint') }}
        </p>
      </div>

      <!-- Products -->
      <div v-else class="p-2 space-y-2">
        <div
          v-for="product in wishlistProducts"
          :key="product.id"
          class="flex items-center gap-3 p-2 rounded-lg hover:bg-base-200 transition-colors cursor-pointer"
          role="button"
          tabindex="0"
          @click="openDetail(product)"
          @keydown.enter="openDetail(product)"
        >
          <div class="w-12 h-12 flex-none rounded-lg bg-base-200 overflow-hidden">
            <img
              v-if="product.image_url"
              :src="product.image_url"
              :alt="product.name"
              class="object-contain w-full h-full p-1"
            />
            <div v-else class="flex items-center justify-center w-full h-full">
              <Package class="w-5 h-5 text-base-content/15" />
            </div>
          </div>
          <div class="min-w-0 flex-1">
            <p class="text-xs font-medium line-clamp-1">{{ product.name }}</p>
            <p class="text-[10px] text-base-content/50 font-mono">{{ product.sku }}</p>
            <p v-if="product.price" class="text-xs font-bold text-primary">
              {{ formatPrice(product.price) }}
            </p>
          </div>
          <button
            class="btn btn-ghost btn-xs btn-circle flex-none text-base-content/30 hover:text-error"
            @click.stop="store.toggleWishlist(product.id)"
          >
            <Trash2 class="w-3.5 h-3.5" />
          </button>
        </div>

        <!-- Restliche IDs ohne Produktdaten (inaktiv/gelöscht) -->
        <div
          v-if="unloadedCount > 0"
          class="text-center text-xs text-base-content/40 py-2"
        >
          + {{ unloadedCount }} weitere Produkte
        </div>
      </div>
    </div>

    <!-- Footer -->
    <div v-if="store.wishlistCount > 0" class="p-3 border-t border-base-300 space-y-1.5">
      <!-- PDF Export -->
      <button
        v-if="store.themeSettings.catalog_pdf_enabled"
        class="btn btn-primary btn-sm w-full gap-1"
        :disabled="exporting !== null"
        @click="downloadWishlistPdf"
      >
        <FileDown class="w-3.5 h-3.5" />
        {{ exporting === 'pdf' ? 'Exportiere...' : 'Merkliste als PDF' }}
      </button>

      <!-- Excel Export -->
      <button
        v-if="store.themeSettings.catalog_excel_export_enabled"
        class="btn btn-outline btn-sm w-full gap-1"
        :disabled="exporting !== null"
        @click="downloadWishlistExcel"
      >
        <Sheet class="w-3.5 h-3.5" />
        {{ exporting === 'excel' ? 'Exportiere...' : 'Excel-Export' }}
      </button>

      <!-- Compare -->
      <button
        v-if="canCompare"
        class="btn btn-outline btn-sm w-full gap-1"
        @click="openCompare"
      >
        <GitCompareArrows class="w-3.5 h-3.5" />
        Produkte vergleichen ({{ store.wishlistCount }})
      </button>

      <!-- Share -->
      <button
        v-if="store.themeSettings.catalog_share_wishlist_enabled"
        class="btn btn-ghost btn-sm w-full gap-1"
        @click="shareWishlist"
      >
        <template v-if="linkCopied">
          <Check class="w-3.5 h-3.5 text-success" />
          Link kopiert!
        </template>
        <template v-else>
          <Share2 class="w-3.5 h-3.5" />
          Merkliste teilen
        </template>
      </button>

      <!-- Clear -->
      <button
        class="btn btn-outline btn-error btn-sm w-full gap-1"
        @click="store.clearWishlist()"
      >
        <Trash2 class="w-3.5 h-3.5" />
        {{ t('catalog.clearWishlist') }}
      </button>
    </div>
  </div>
</template>
