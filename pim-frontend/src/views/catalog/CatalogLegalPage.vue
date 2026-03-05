<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useCatalogStore } from '@/stores/catalog'
import { ArrowLeft } from 'lucide-vue-next'

const route = useRoute()
const router = useRouter()
const store = useCatalogStore()

const pageType = computed(() => route.path.endsWith('/kontakt') ? 'kontakt' : 'impressum')

const title = computed(() => pageType.value === 'kontakt' ? 'Kontakt' : 'Impressum')

const content = computed(() =>
  pageType.value === 'kontakt'
    ? store.themeSettings.kontakt_text
    : store.themeSettings.impressum_text,
)
</script>

<template>
  <div>
    <button class="btn btn-ghost btn-sm gap-1 mb-4" @click="router.push({ name: 'catalog' })">
      <ArrowLeft class="w-4 h-4" />
      Zurück zum Katalog
    </button>

    <div class="card bg-base-100 shadow-sm border border-base-300">
      <div class="card-body">
        <h1 class="text-2xl font-bold text-base-content mb-4" :style="{ fontSize: 'var(--catalog-heading-size, 1.75rem)' }">
          {{ title }}
        </h1>
        <div v-if="content" class="prose prose-sm max-w-none text-base-content/80 whitespace-pre-wrap">{{ content }}</div>
        <p v-else class="text-base-content/40 text-sm">Kein Inhalt hinterlegt.</p>
      </div>
    </div>
  </div>
</template>
