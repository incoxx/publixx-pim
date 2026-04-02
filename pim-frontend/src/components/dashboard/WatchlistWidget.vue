<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { Star, ExternalLink } from 'lucide-vue-next'
import DashboardWidgetWrapper from './DashboardWidgetWrapper.vue'
import watchlistApi from '@/api/watchlist'

const router = useRouter()
const items = ref([])
const loading = ref(false)
const totalCount = ref(0)

onMounted(async () => {
  loading.value = true
  try {
    const { data } = await watchlistApi.list({ per_page: 8 })
    const payload = data.data || data
    items.value = Array.isArray(payload) ? payload : (payload.data || [])
    totalCount.value = payload.total || payload.meta?.total || items.value.length
  } catch { /* ignore */ }
  loading.value = false
})

function goToProduct(item) {
  const productId = item.product_id || item.product?.id
  if (productId) {
    router.push({ name: 'product-detail', params: { id: productId } })
  }
}

function relativeTime(timestamp) {
  if (!timestamp) return ''
  const now = new Date()
  const date = new Date(timestamp)
  const diff = Math.floor((now - date) / 1000)
  if (diff < 60) return 'gerade'
  if (diff < 3600) return `vor ${Math.floor(diff / 60)} Min.`
  if (diff < 86400) return `vor ${Math.floor(diff / 3600)} Std.`
  if (diff < 172800) return 'gestern'
  return `vor ${Math.floor(diff / 86400)} T.`
}
</script>

<template>
  <DashboardWidgetWrapper title="Merkliste" :icon="Star">
    <div class="p-4">
      <!-- Loading -->
      <div v-if="loading" class="flex items-center justify-center py-4">
        <div class="w-4 h-4 border-2 border-[var(--color-accent)] border-t-transparent rounded-full animate-spin" />
      </div>

      <!-- Leer -->
      <div v-else-if="items.length === 0" class="text-center text-xs text-[var(--color-text-tertiary)] py-4">
        Keine Produkte auf der Merkliste
      </div>

      <!-- Liste -->
      <div v-else class="space-y-0">
        <div
          v-for="item in items"
          :key="item.id"
          class="flex items-center gap-2 py-2 border-b border-[var(--color-border)] last:border-0 cursor-pointer hover:bg-[var(--color-bg)] -mx-2 px-2 rounded transition-colors"
          @click="goToProduct(item)"
        >
          <Star class="w-3.5 h-3.5 text-amber-500 shrink-0 fill-amber-500" :stroke-width="1.5" />
          <div class="flex-1 min-w-0">
            <p class="text-xs text-[var(--color-text-primary)] truncate">
              {{ item.product?.name || item.product_name || 'Produkt' }}
            </p>
            <p class="text-[10px] text-[var(--color-text-tertiary)]">
              {{ item.product?.sku || item.product_sku || '' }}
              <span v-if="item.note" class="ml-1">&middot; {{ item.note }}</span>
            </p>
          </div>
          <span class="text-[10px] text-[var(--color-text-tertiary)] shrink-0">
            {{ relativeTime(item.created_at) }}
          </span>
        </div>
      </div>

      <!-- Alle anzeigen -->
      <div v-if="totalCount > items.length" class="mt-3 text-center">
        <button
          class="text-xs text-[var(--color-accent)] hover:underline flex items-center gap-1 mx-auto"
          @click="router.push({ name: 'watchlist' })"
        >
          Alle {{ totalCount }} anzeigen
          <ExternalLink class="w-3 h-3" :stroke-width="2" />
        </button>
      </div>
    </div>
  </DashboardWidgetWrapper>
</template>
