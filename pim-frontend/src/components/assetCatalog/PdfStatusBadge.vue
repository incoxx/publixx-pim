<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { Loader2, CheckCircle, AlertCircle, Clock } from 'lucide-vue-next'
import pdfApi from '@/api/pdf'

const props = defineProps({
  mediaId: { type: String, required: true },
})

const { t } = useI18n()

const status = ref(null)
const pageCount = ref(null)
const loading = ref(true)
let pollInterval = null

async function fetchStatus() {
  try {
    const { data } = await pdfApi.getByMedia(props.mediaId)
    status.value = data.status
    pageCount.value = data.page_count
    loading.value = false

    if (data.status === 'pending' || data.status === 'processing') {
      startPolling()
    } else {
      stopPolling()
    }
  } catch (e) {
    if (e.response?.status === 404) {
      status.value = null
      loading.value = false
    }
  }
}

function startPolling() {
  if (pollInterval) return
  pollInterval = setInterval(fetchStatus, 3000)
}

function stopPolling() {
  if (pollInterval) {
    clearInterval(pollInterval)
    pollInterval = null
  }
}

onMounted(() => {
  fetchStatus()
})

onUnmounted(() => {
  stopPolling()
})

watch(() => props.mediaId, () => {
  stopPolling()
  loading.value = true
  status.value = null
  fetchStatus()
})
</script>

<template>
  <span v-if="loading" class="badge badge-xs badge-ghost gap-1">
    <Loader2 class="w-3 h-3 animate-spin" />
  </span>

  <span
    v-else-if="status === 'pending' || status === 'processing'"
    class="badge badge-xs badge-warning gap-1"
  >
    <Loader2 class="w-3 h-3 animate-spin" />
    {{ status === 'pending' ? t('pdf.pending', 'Wartend') : t('pdf.processing', 'Wird verarbeitet…') }}
  </span>

  <span
    v-else-if="status === 'ready'"
    class="badge badge-xs badge-success gap-1"
  >
    <CheckCircle class="w-3 h-3" />
    {{ pageCount }} {{ t('pdf.pages', 'Seiten') }}
  </span>

  <span
    v-else-if="status === 'error'"
    class="badge badge-xs badge-error gap-1"
  >
    <AlertCircle class="w-3 h-3" />
    {{ t('pdf.error', 'Fehler') }}
  </span>
</template>
