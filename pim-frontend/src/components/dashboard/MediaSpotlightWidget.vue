<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { Image as ImageIcon, ExternalLink, AlertTriangle } from 'lucide-vue-next'
import DashboardWidgetWrapper from './DashboardWidgetWrapper.vue'
import mediaApi from '@/api/media'
import watchlistApi from '@/api/watchlist'

const props = defineProps({
  // 'all' = neueste Medien insgesamt, 'watchlist' = Medien der Merklisten-Produkte
  scope: { type: String, default: 'all' },
})

const router = useRouter()
const items = ref([])
const loading = ref(false)
const totalCount = ref(0)

const isWatchlist = computed(() => props.scope === 'watchlist')
const title = computed(() => isWatchlist.value ? 'Medien meiner Merkliste' : 'Medien-Spotlight')
const emptyText = computed(() => isWatchlist.value
  ? 'Produkte auf der Merkliste haben noch keine Medien.'
  : 'Noch keine Medien vorhanden')

// Assets ohne Alt-Text (innerhalb der geladenen Auswahl) — SEO-/A11y-Hinweis
const missingAltCount = computed(() =>
  items.value.filter(m => m.media_type === 'image' && !m.alt_text_de && !m.alt_text_en).length
)

function altMissing(m) {
  return m.media_type === 'image' && !m.alt_text_de && !m.alt_text_en
}

onMounted(async () => {
  loading.value = true
  try {
    const { data } = isWatchlist.value
      ? await watchlistApi.media()
      : await mediaApi.list({ per_page: 8 })
    const body = data || {}
    let list = body.data ?? body
    if (!Array.isArray(list)) list = list.data || []
    items.value = list
    totalCount.value = body.meta?.total ?? body.total ?? list.length
  } catch { /* ignore */ }
  loading.value = false
})
</script>

<template>
  <DashboardWidgetWrapper :title="title" :icon="ImageIcon">
    <div class="p-4">
      <!-- Loading -->
      <div v-if="loading" class="flex items-center justify-center py-6">
        <div class="w-4 h-4 border-2 border-[var(--color-accent)] border-t-transparent rounded-full animate-spin" />
      </div>

      <!-- Leer -->
      <div v-else-if="items.length === 0" class="text-center text-xs text-[var(--color-text-tertiary)] py-6">
        {{ emptyText }}
      </div>

      <template v-else>
        <!-- Thumbnail-Raster -->
        <div class="grid grid-cols-4 gap-2">
          <button
            v-for="m in items"
            :key="m.id"
            class="relative aspect-square rounded-lg overflow-hidden border border-[var(--color-border)] bg-[var(--color-bg)] hover:border-[var(--color-accent)] transition group"
            :title="m.title_de || m.file_name"
            @click="router.push({ name: 'media' })"
          >
            <img
              v-if="m.media_type === 'image'"
              :src="m.thumb_url"
              :alt="m.alt_text_de || m.title_de || m.file_name"
              class="w-full h-full object-cover"
              loading="lazy"
            />
            <div v-else class="w-full h-full flex items-center justify-center">
              <ImageIcon class="w-5 h-5 text-[var(--color-text-tertiary)]" :stroke-width="1.5" />
            </div>
            <!-- Alt-Text-fehlt-Hinweis -->
            <span
              v-if="altMissing(m)"
              class="absolute top-1 right-1 p-0.5 rounded bg-[var(--color-warning)]/90"
              title="Alt-Text fehlt"
            >
              <AlertTriangle class="w-3 h-3 text-white" :stroke-width="2" />
            </span>
          </button>
        </div>

        <!-- Footer: Hinweis + Link -->
        <div class="flex items-center justify-between mt-3">
          <span v-if="missingAltCount > 0" class="text-[10px] text-[var(--color-warning)] flex items-center gap-1">
            <AlertTriangle class="w-3 h-3" :stroke-width="2" />
            {{ missingAltCount }} ohne Alt-Text
          </span>
          <span v-else class="text-[10px] text-[var(--color-text-tertiary)]">{{ totalCount }} Medien gesamt</span>
          <button
            class="text-xs text-[var(--color-accent)] hover:underline flex items-center gap-1"
            @click="router.push({ name: 'media' })"
          >
            Zur Mediathek
            <ExternalLink class="w-3 h-3" :stroke-width="2" />
          </button>
        </div>
      </template>
    </div>
  </DashboardWidgetWrapper>
</template>
