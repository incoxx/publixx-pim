<script setup>
import { computed } from 'vue'
import { Music, Loader, AlertCircle } from 'lucide-vue-next'
import { useMediaProcessingPoll } from '@/composables/useMediaProcessingPoll'

const props = defineProps({
  url: { type: String, required: true },
  mediaId: { type: String, default: null },
  title: { type: String, default: '' },
})

const { status, durationSeconds, loading } = useMediaProcessingPoll(computed(() => props.mediaId))

const formattedDuration = computed(() => {
  if (!durationSeconds.value) return null
  const m = Math.floor(durationSeconds.value / 60)
  const s = durationSeconds.value % 60
  return `${m}:${String(s).padStart(2, '0')}`
})
</script>

<template>
  <div class="audio-player rounded-lg border border-[var(--color-border,theme(colors.base-300))] bg-[var(--color-bg,theme(colors.base-200))] p-4">
    <p v-if="title" class="mb-3 flex items-center gap-1.5 text-sm font-medium">
      <Music class="w-4 h-4 shrink-0" />
      {{ title }}
    </p>

    <audio :src="url" controls preload="metadata" class="w-full" />

    <div class="mt-2 flex items-center gap-2 text-[11px] text-[var(--color-text-tertiary,theme(colors.base-content/50))]">
      <span v-if="!loading && status === 'processing'"><Loader class="w-3 h-3 animate-spin inline mr-1" />Wird verarbeitet...</span>
      <span v-else-if="!loading && status === 'pending'">Verarbeitung wird vorbereitet...</span>
      <span v-else-if="!loading && status === 'error'"><AlertCircle class="w-3 h-3 inline mr-0.5 text-[var(--color-error,red)]" />Verarbeitung fehlgeschlagen</span>
      <span v-if="formattedDuration">{{ formattedDuration }}</span>
    </div>
  </div>
</template>
