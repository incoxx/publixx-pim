import { ref, watch, onUnmounted } from 'vue'
import mediaApi from '@/api/media'

const POLL_INTERVAL_MS = 3000
// ProcessAudioVideoMedia hat tries=3 mit backoff=[10, 30, 120] — nach einem 'error'-Status
// noch einige Male weiterpollen, falls ein Retry noch erfolgreich abschließt.
const ERROR_GRACE_POLLS = 20
const MAX_CONSECUTIVE_FETCH_FAILURES = 5

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
  let consecutiveFailures = 0
  let errorGracePollsLeft = 0

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
      consecutiveFailures = 0
    } catch (e) {
      // Status bewusst nicht anfassen — ein einzelner Netzwerk-/Server-Fehler soll die
      // Anzeige nicht auf "unbekannt" zurücksetzen. Weiter unten wird trotzdem erneut
      // gepollt (begrenzt durch MAX_CONSECUTIVE_FETCH_FAILURES).
      consecutiveFailures++
      console.warn('useMediaProcessingPoll: Failed to fetch media status:', e.message)
    } finally {
      loading.value = false
    }
  }

  async function pollUntilDone(id) {
    await fetchOnce(id)

    if (status.value === 'pending' || status.value === 'processing') {
      errorGracePollsLeft = ERROR_GRACE_POLLS
      timer = setTimeout(() => pollUntilDone(id), POLL_INTERVAL_MS)
      return
    }

    // Transienter Job-Fehler: der Job kann per Retry/Backoff noch erfolgreich abschließen,
    // also nicht sofort aufgeben, sondern noch eine Weile weiterpollen.
    if (status.value === 'error' && errorGracePollsLeft > 0) {
      errorGracePollsLeft--
      timer = setTimeout(() => pollUntilDone(id), POLL_INTERVAL_MS)
      return
    }

    // Fetch selbst ist fehlgeschlagen (Status unverändert) — mit Obergrenze erneut versuchen,
    // statt bei einem einzelnen Netzwerk-Hänger dauerhaft stehen zu bleiben.
    if (consecutiveFailures > 0 && consecutiveFailures < MAX_CONSECUTIVE_FETCH_FAILURES) {
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
