<script setup>
import { computed } from 'vue'
import { Video, Loader, AlertCircle } from 'lucide-vue-next'
import mediaApi from '@/api/media'
import { useMediaProcessingPoll } from '@/composables/useMediaProcessingPoll'

const props = defineProps({
  url: { type: String, required: true },
  mediaId: { type: String, default: null },
  title: { type: String, default: '' },
  maxHeight: { type: String, default: '24rem' },
})

const { status, durationSeconds, loading } = useMediaProcessingPoll(computed(() => props.mediaId))

const posterUrl = computed(() =>
  props.mediaId && status.value === 'ready' ? mediaApi.thumbUrl(props.mediaId, 640, 640) : undefined
)

const formattedDuration = computed(() => {
  if (!durationSeconds.value) return null
  const m = Math.floor(durationSeconds.value / 60)
  const s = durationSeconds.value % 60
  return `${m}:${String(s).padStart(2, '0')}`
})
</script>

<template>
  <div class="video-preview">
    <p v-if="title" class="mb-2 flex items-center gap-1.5 text-sm font-medium">
      <Video class="w-4 h-4 shrink-0" />
      {{ title }}
    </p>

    <div class="overflow-hidden rounded-lg border border-[var(--color-border,theme(colors.base-300))] bg-black"
         :style="{ maxHeight }">
      <video :src="url" :poster="posterUrl" controls preload="metadata" class="block w-full h-auto max-h-full" />
    </div>

    <div class="mt-1 flex items-center gap-2 text-[11px] text-[var(--color-text-tertiary,theme(colors.base-content/50))]">
      <span v-if="!loading && status === 'processing'"><Loader class="w-3 h-3 animate-spin inline mr-1" />Thumbnail wird erstellt...</span>
      <span v-else-if="!loading && status === 'pending'">Verarbeitung wird vorbereitet...</span>
      <span v-else-if="!loading && status === 'error'"><AlertCircle class="w-3 h-3 inline mr-0.5 text-[var(--color-error,red)]" />Verarbeitung fehlgeschlagen</span>
      <span v-if="formattedDuration">{{ formattedDuration }}</span>
    </div>
  </div>
</template>
