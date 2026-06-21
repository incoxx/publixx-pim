<script setup>
import { ref, onMounted } from 'vue'
import watchlistApi from '@/api/watchlist'
import DataQualityWidget from './DataQualityWidget.vue'

/**
 * Datenqualität über die eigene Merkliste (Arbeitsvorrat).
 * Lädt das Aggregat selbst und nutzt die Darstellung des DataQualityWidget.
 */
const quality = ref(null)

onMounted(async () => {
  try {
    const { data } = await watchlistApi.dataQuality()
    quality.value = data.data || data
  } catch { /* ignore */ }
})
</script>

<template>
  <DataQualityWidget
    :quality="quality"
    title="Datenqualität (Merkliste)"
    empty-text="Lege Produkte auf die Merkliste, um deine Datenqualität zu sehen."
  />
</template>
