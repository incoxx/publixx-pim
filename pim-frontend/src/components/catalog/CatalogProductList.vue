<script setup>
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCatalogStore } from '@/stores/catalog'
import { Heart, Package, ArrowUp, ArrowDown, ArrowUpDown, Search } from 'lucide-vue-next'

const props = defineProps({
  products: { type: Array, required: true },
})

const emit = defineEmits(['view-detail'])

const { t } = useI18n()
const store = useCatalogStore()

const showPrice = computed(() => store.themeSettings.card_show_price !== false)
const showSku = computed(() => store.themeSettings.card_show_sku === true)

// Build dynamic attribute columns from the first product's card_attributes
// (all products share the same configured attributes)
const attrColumns = computed(() => {
  for (const p of props.products) {
    if (p.card_attributes?.length) {
      return p.card_attributes.map(a => ({
        attribute_id: a.attribute_id,
        technical_name: a.technical_name,
        label: a.label,
      }))
    }
  }
  return []
})

// Quick lookup filters
const showQuickLookup = ref(false)
const quickFilters = ref({})
let debounceTimer = null

function onFilterInput(key, value) {
  quickFilters.value = { ...quickFilters.value, [key]: value }
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => { /* reactivity handles it */ }, 0)
}

// Client-side filtering
const filteredProducts = computed(() => {
  if (!showQuickLookup.value) return props.products
  const filters = Object.entries(quickFilters.value).filter(([, v]) => v && v.trim())
  if (filters.length === 0) return props.products

  return props.products.filter(product => {
    return filters.every(([key, filterVal]) => {
      const lowerFilter = filterVal.toLowerCase()
      if (key === 'name') {
        return (product.name || '').toLowerCase().includes(lowerFilter)
      }
      if (key === 'sku') {
        return (product.sku || '').toLowerCase().includes(lowerFilter)
      }
      if (key === 'price') {
        return String(product.price || '').includes(filterVal)
      }
      // Attribute column
      const attr = (product.card_attributes || []).find(a => a.attribute_id === key || a.technical_name === key)
      return attr && (attr.value || '').toLowerCase().includes(lowerFilter)
    })
  })
})

// Sort handling
function handleSort(field) {
  const currentField = store.sort.field
  const currentOrder = store.sort.order
  const newOrder = currentField === field && currentOrder === 'asc' ? 'desc' : 'asc'
  store.setSort(field, newOrder)
  store.fetchProducts()
}

function formatPrice(product) {
  if (!product.price) return null
  return new Intl.NumberFormat(store.locale === 'de' ? 'de-DE' : 'en-US', {
    style: 'currency',
    currency: product.currency || 'EUR',
  }).format(product.price)
}

function getAttrValue(product, attrId) {
  const attr = (product.card_attributes || []).find(a => a.attribute_id === attrId || a.technical_name === attrId)
  return attr?.value || '—'
}

function isInWishlist(productId) {
  return store.isInWishlist(productId)
}

function toggleWishlist(e, productId) {
  e.stopPropagation()
  store.toggleWishlist(productId)
}
</script>

<template>
  <div class="bg-base-100 rounded-xl shadow-sm border border-base-300 overflow-hidden">
    <!-- Quick Lookup Toggle -->
    <div class="flex justify-end px-3 py-1.5 border-b border-base-200">
      <button
        class="btn btn-xs gap-1"
        :class="showQuickLookup ? 'btn-primary' : 'btn-ghost'"
        @click="showQuickLookup = !showQuickLookup; quickFilters = {}"
      >
        <Search class="w-3 h-3" />
        Quick Lookup
      </button>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full text-[13px]">
        <thead>
          <!-- Header row -->
          <tr class="bg-base-200/50 border-b border-base-300">
            <!-- Image -->
            <th class="w-16 px-2 py-2.5"></th>
            <!-- Name -->
            <th
              class="px-3 py-2.5 text-left font-medium text-[11px] uppercase tracking-wider text-base-content/50 cursor-pointer select-none hover:text-base-content/70"
              @click="handleSort('name')"
            >
              <div class="flex items-center gap-1">
                <span>{{ t('catalog.sortName') }}</span>
                <ArrowUp v-if="store.sort.field === 'name' && store.sort.order === 'asc'" class="w-3 h-3 text-primary" />
                <ArrowDown v-else-if="store.sort.field === 'name' && store.sort.order === 'desc'" class="w-3 h-3 text-primary" />
                <ArrowUpDown v-else class="w-3 h-3 opacity-30" />
              </div>
            </th>
            <!-- SKU -->
            <th
              v-if="showSku"
              class="px-3 py-2.5 text-left font-medium text-[11px] uppercase tracking-wider text-base-content/50 cursor-pointer select-none hover:text-base-content/70"
              @click="handleSort('sku')"
            >
              <div class="flex items-center gap-1">
                <span>SKU</span>
                <ArrowUp v-if="store.sort.field === 'sku' && store.sort.order === 'asc'" class="w-3 h-3 text-primary" />
                <ArrowDown v-else-if="store.sort.field === 'sku' && store.sort.order === 'desc'" class="w-3 h-3 text-primary" />
                <ArrowUpDown v-else class="w-3 h-3 opacity-30" />
              </div>
            </th>
            <!-- Dynamic attribute columns -->
            <th
              v-for="col in attrColumns"
              :key="col.attribute_id"
              class="px-3 py-2.5 text-left font-medium text-[11px] uppercase tracking-wider text-base-content/50"
            >
              {{ col.label }}
            </th>
            <!-- Price -->
            <th
              v-if="showPrice"
              class="px-3 py-2.5 text-right font-medium text-[11px] uppercase tracking-wider text-base-content/50 cursor-pointer select-none hover:text-base-content/70"
              @click="handleSort('price')"
            >
              <div class="flex items-center gap-1 justify-end">
                <span>{{ t('catalog.sortPrice') || 'Preis' }}</span>
                <ArrowUp v-if="store.sort.field === 'price' && store.sort.order === 'asc'" class="w-3 h-3 text-primary" />
                <ArrowDown v-else-if="store.sort.field === 'price' && store.sort.order === 'desc'" class="w-3 h-3 text-primary" />
                <ArrowUpDown v-else class="w-3 h-3 opacity-30" />
              </div>
            </th>
            <!-- Wishlist -->
            <th class="w-10"></th>
          </tr>

          <!-- Quick Lookup row -->
          <tr v-if="showQuickLookup" class="bg-base-200/30 border-b border-base-200">
            <th class="w-16"></th>
            <th class="px-2 py-1.5">
              <input
                type="text"
                :value="quickFilters.name || ''"
                @input="onFilterInput('name', $event.target.value)"
                placeholder="Name..."
                class="input input-xs input-bordered w-full"
              />
            </th>
            <th v-if="showSku" class="px-2 py-1.5">
              <input
                type="text"
                :value="quickFilters.sku || ''"
                @input="onFilterInput('sku', $event.target.value)"
                placeholder="SKU..."
                class="input input-xs input-bordered w-full"
              />
            </th>
            <th v-for="col in attrColumns" :key="'ql-' + col.attribute_id" class="px-2 py-1.5">
              <input
                type="text"
                :value="quickFilters[col.attribute_id] || ''"
                @input="onFilterInput(col.attribute_id, $event.target.value)"
                :placeholder="col.label + '...'"
                class="input input-xs input-bordered w-full"
              />
            </th>
            <th v-if="showPrice" class="px-2 py-1.5">
              <input
                type="text"
                :value="quickFilters.price || ''"
                @input="onFilterInput('price', $event.target.value)"
                placeholder="Preis..."
                class="input input-xs input-bordered w-full text-right"
              />
            </th>
            <th class="w-10"></th>
          </tr>
        </thead>

        <tbody>
          <tr v-if="filteredProducts.length === 0">
            <td :colspan="3 + attrColumns.length + (showSku ? 1 : 0) + (showPrice ? 1 : 0)" class="py-12 text-center text-sm text-base-content/40">
              Keine Ergebnisse
            </td>
          </tr>
          <tr
            v-for="(product, index) in filteredProducts"
            :key="product.id"
            class="border-b border-base-200 hover:bg-base-200/30 transition-colors cursor-pointer group catalog-list-enter"
            :style="{ animationDelay: `${Math.min(index * 20, 200)}ms` }"
            @click="emit('view-detail', product)"
          >
            <!-- Image -->
            <td class="px-2 py-2">
              <div class="w-12 h-12 rounded bg-base-200 overflow-hidden flex items-center justify-center flex-none">
                <img
                  v-if="product.image_url"
                  :src="product.image_url"
                  :alt="product.name"
                  class="object-contain w-full h-full p-1"
                  loading="lazy"
                />
                <Package v-else class="w-5 h-5 text-base-content/15" />
              </div>
            </td>
            <!-- Name -->
            <td class="px-3 py-2">
              <div class="min-w-0">
                <p v-if="product.category_path" class="text-[10px] text-base-content/35 truncate max-w-[200px]">
                  {{ product.category_path }}
                </p>
                <p class="text-sm font-medium line-clamp-1">{{ product.name || '—' }}</p>
              </div>
            </td>
            <!-- SKU -->
            <td v-if="showSku" class="px-3 py-2 font-mono text-xs text-base-content/60 whitespace-nowrap">
              {{ product.sku }}
            </td>
            <!-- Dynamic attribute cells -->
            <td
              v-for="col in attrColumns"
              :key="col.attribute_id"
              class="px-3 py-2 text-xs text-base-content/70 max-w-[180px] truncate"
            >
              {{ getAttrValue(product, col.attribute_id) }}
            </td>
            <!-- Price -->
            <td v-if="showPrice" class="px-3 py-2 text-right whitespace-nowrap">
              <span v-if="formatPrice(product)" class="font-semibold text-primary">{{ formatPrice(product) }}</span>
              <span v-else class="text-base-content/25">—</span>
            </td>
            <!-- Wishlist -->
            <td class="px-2 py-2" @click.stop>
              <button
                class="btn btn-ghost btn-xs btn-circle"
                @click="toggleWishlist($event, product.id)"
              >
                <Heart
                  class="w-3.5 h-3.5 transition-all"
                  :class="isInWishlist(product.id) ? 'fill-error text-error' : 'text-base-content/25'"
                />
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
@keyframes catalogListEnter {
  from {
    opacity: 0;
    transform: translateX(-4px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

.catalog-list-enter {
  animation: catalogListEnter 0.25s cubic-bezier(0.16, 1, 0.3, 1) both;
}
</style>
