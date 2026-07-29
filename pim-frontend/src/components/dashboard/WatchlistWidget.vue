<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { Star, ExternalLink, Download, FileSpreadsheet, FileText, FileArchive } from 'lucide-vue-next'
import DashboardWidgetWrapper from './DashboardWidgetWrapper.vue'
import watchlistApi from '@/api/watchlist'
import { triggerDownload } from '@/utils/download'

const { t } = useI18n()
const router = useRouter()
const items = ref([])
const loading = ref(false)
const totalCount = ref(0)

// Export (wie auf der Merkliste-Seite) — direkt im Widget verfügbar
const showExport = ref(false)
const exporting = ref(false)
async function doExport(kind) {
  if (exporting.value) return
  exporting.value = true
  try {
    const today = new Date().toISOString().slice(0, 10)
    if (kind === 'excel') {
      const r = await watchlistApi.exportExcel()
      triggerDownload(r.data, `merkliste-${today}.xlsx`)
    } else if (kind === 'pdf') {
      const r = await watchlistApi.exportPdf()
      triggerDownload(r.data, `merkliste-${today}.pdf`)
    } else if (kind === 'zip') {
      const r = await watchlistApi.exportPdfZip()
      triggerDownload(r.data, `merkliste-${today}.zip`)
    }
  } catch { /* ignore */ } finally {
    exporting.value = false
    showExport.value = false
  }
}

onMounted(async () => {
  loading.value = true
  try {
    const { data } = await watchlistApi.list({ per_page: 8 })
    items.value = data.data || data
    totalCount.value = data.meta?.total ?? items.value.length
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
  if (diff < 60) return t('gerade')
  if (diff < 3600) return t('vor {count} Min.', { count: Math.floor(diff / 60) })
  if (diff < 86400) return t('vor {count} Std.', { count: Math.floor(diff / 3600) })
  if (diff < 172800) return t('gestern')
  return t('vor {count} T.', { count: Math.floor(diff / 86400) })
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
              {{ item.product?.name || item.product_name || t('Produkt') }}
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

      <!-- Footer: Export + Alle anzeigen -->
      <div v-if="items.length > 0" class="mt-3 flex items-center justify-between">
        <!-- Export-Dropdown -->
        <div class="relative">
          <button
            class="text-xs text-[var(--color-text-secondary)] hover:text-[var(--color-accent)] flex items-center gap-1 disabled:opacity-50"
            :disabled="exporting"
            @click="showExport = !showExport"
          >
            <Download class="w-3.5 h-3.5" :stroke-width="2" />
            Export
          </button>
          <div
            v-if="showExport"
            class="absolute left-0 bottom-full mb-1 w-44 py-1 bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg shadow-lg z-30"
          >
            <button class="w-full px-3 py-1.5 text-left text-xs flex items-center gap-2 hover:bg-[var(--color-bg)]" @click="doExport('excel')">
              <FileSpreadsheet class="w-3.5 h-3.5 text-emerald-600" :stroke-width="2" /> Excel (.xlsx)
            </button>
            <button class="w-full px-3 py-1.5 text-left text-xs flex items-center gap-2 hover:bg-[var(--color-bg)]" @click="doExport('pdf')">
              <FileText class="w-3.5 h-3.5 text-red-500" :stroke-width="2" /> PDF
            </button>
            <button class="w-full px-3 py-1.5 text-left text-xs flex items-center gap-2 hover:bg-[var(--color-bg)]" @click="doExport('zip')">
              <FileArchive class="w-3.5 h-3.5 text-[var(--color-text-tertiary)]" :stroke-width="2" /> PDF je Produkt (ZIP)
            </button>
            <button class="w-full px-3 py-1.5 text-left text-xs flex items-center gap-2 hover:bg-[var(--color-bg)] border-t border-[var(--color-border)] text-[var(--color-text-tertiary)]" @click="router.push({ name: 'watchlist' }); showExport = false">
              <ExternalLink class="w-3.5 h-3.5" :stroke-width="2" /> Weitere Exporte …
            </button>
          </div>
        </div>

        <!-- Alle anzeigen -->
        <button
          v-if="totalCount > items.length"
          class="text-xs text-[var(--color-accent)] hover:underline flex items-center gap-1"
          @click="router.push({ name: 'watchlist' })"
        >
          {{ t('Alle {count} anzeigen', { count: totalCount }) }}
          <ExternalLink class="w-3 h-3" :stroke-width="2" />
        </button>
      </div>

      <!-- Klick außerhalb schließt das Export-Menü -->
      <div v-if="showExport" class="fixed inset-0 z-20" @click="showExport = false" />
    </div>
  </DashboardWidgetWrapper>
</template>
