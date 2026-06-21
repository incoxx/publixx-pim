<script setup>
import { ref, onMounted } from 'vue'
import watchlistApi from '@/api/watchlist'
import CompletenessWidget from './CompletenessWidget.vue'

/**
 * Produktfüllstand über die eigene Merkliste (Arbeitsvorrat).
 * Lädt das Aggregat selbst und nutzt die Darstellung des CompletenessWidget.
 */
const summary = ref(null)

onMounted(async () => {
  try {
    const { data } = await watchlistApi.completeness()
    summary.value = data.data || data
  } catch { /* ignore */ }
})
</script>

<template>
  <CompletenessWidget
    :summary="summary"
    title="Mein Füllstand (Merkliste)"
    empty-text="Lege Produkte auf die Merkliste, um deinen Arbeitsfortschritt zu sehen."
  />
</template>
