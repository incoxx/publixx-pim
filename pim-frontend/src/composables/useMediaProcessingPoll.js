import { ref, watch, onUnmounted } from 'vue'
import mediaApi from '@/api/media'

const POLL_INTERVAL_MS = 3000

/**
 * Pollt den Verarbeitungsstatus eines Audio-/Video-Media-Assets (ffmpeg/ffprobe-Job),
 * solange er noch nicht abgeschlossen ist ('pending'/'processing'). Wird von
 * VideoPreview.vue und AudioPlayer.vue genutzt, um Dauer und Video-Thumbnail
 * anzuzeigen, sobald ProcessAudioVideoMedia fertig ist.
 */
export function useMediaProcessingPoll(mediaId) {
  const status = ref(null)
  const durationSeconds = ref(null)
  const errorMessage = ref(null)
  const loading = ref(true)

  let timer = null

  function stopPolling() {
    if (timer) {
      clearTimeout(timer)
      timer = null
    }
  }

  async function fetchOnce(id) {
    try {
      const { data } = await mediaApi.get(id)
      const media = data.data || data
      status.value = media.av_processing_status
      durationSeconds.value = media.duration_seconds
      errorMessage.value = media.av_error_message
    } catch (e) {
      console.warn('useMediaProcessingPoll: Failed to fetch media status:', e.message)
    } finally {
      loading.value = false
    }
  }

  async function pollUntilDone(id) {
    await fetchOnce(id)
    if (status.value === 'pending' || status.value === 'processing') {
      timer = setTimeout(() => pollUntilDone(id), POLL_INTERVAL_MS)
    }
  }

  function start(id) {
    stopPolling()
    if (!id) return
    loading.value = true
    pollUntilDone(id)
  }

  if (typeof mediaId === 'object' && 'value' in mediaId) {
    watch(mediaId, (id) => start(id), { immediate: true })
  } else {
    start(mediaId)
  }

  onUnmounted(stopPolling)

  return { status, durationSeconds, errorMessage, loading }
}
