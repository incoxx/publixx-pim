<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { Loader2, CheckCircle, AlertCircle, FileText, X } from 'lucide-vue-next'
import mediaApi from '@/api/media'

const status = ref(null)
const visible = ref(false)
const dismissed = ref(false)
let pollTimeout = null

async function fetchStatus() {
  try {
    const { data } = await mediaApi.processingStatus()
    status.value = data.data || data

    // Sichtbar wenn Aktivität vorhanden
    if (status.value?.has_activity && !dismissed.value) {
      visible.value = true
    }

    // Weiter pollen wenn Jobs aktiv
    if (status.value?.pdf?.active) {
      scheduleNextPoll(3000)
    } else if (status.value?.has_activity) {
      scheduleNextPoll(10000)
    } else {
      visible.value = false
    }
  } catch {
    // Nicht-kritisch — Status-Leiste bleibt einfach versteckt
    scheduleNextPoll(15000)
  }
}

function scheduleNextPoll(delay) {
  if (pollTimeout) clearTimeout(pollTimeout)
  pollTimeout = setTimeout(fetchStatus, delay)
}

function dismiss() {
  dismissed.value = true
  visible.value = false
}

// Beim Mounten sofort prüfen
onMounted(() => {
  fetchStatus()
})

onUnmounted(() => {
  if (pollTimeout) clearTimeout(pollTimeout)
})

// Extern aufrufbar: nach Upload sofort Status prüfen
function refresh() {
  dismissed.value = false
  fetchStatus()
}

defineExpose({ refresh })
</script>

<template>
  <Transition name="slide-down">
    <div
      v-if="visible && status"
      class="flex items-center gap-3 px-4 py-2 bg-[var(--color-bg)] border border-[var(--color-border)] rounded-lg text-xs"
    >
      <!-- PDF-Verarbeitung aktiv -->
      <template v-if="status.pdf?.active">
        <Loader2 class="w-4 h-4 animate-spin text-[var(--color-primary)]" :stroke-width="2" />
        <div class="flex items-center gap-2 flex-1">
          <FileText class="w-3.5 h-3.5 text-[var(--color-text-tertiary)]" :stroke-width="2" />
          <span class="text-[var(--color-text-primary)]">
            PDF-Verarbeitung:
            <strong v-if="status.pdf.processing > 0">{{ status.pdf.processing }} aktiv</strong>
            <template v-if="status.pdf.pending > 0">
              <span v-if="status.pdf.processing > 0">, </span>
              {{ status.pdf.pending }} wartend
            </template>
          </span>
          <span v-if="status.pdf.recently_ready > 0" class="text-[var(--color-success)]">
            <CheckCircle class="w-3 h-3 inline" /> {{ status.pdf.recently_ready }} fertig
          </span>
          <span v-if="status.pdf.recent_errors > 0" class="text-[var(--color-error)]">
            <AlertCircle class="w-3 h-3 inline" /> {{ status.pdf.recent_errors }} Fehler
          </span>
        </div>
      </template>

      <!-- Nur kürzlich fertige PDFs (kein aktiver Job) -->
      <template v-else-if="status.pdf?.recently_ready > 0 || status.pdf?.recent_errors > 0">
        <CheckCircle class="w-4 h-4 text-[var(--color-success)]" :stroke-width="2" />
        <span class="text-[var(--color-text-primary)] flex-1">
          {{ status.pdf.recently_ready }} PDF{{ status.pdf.recently_ready !== 1 ? 's' : '' }} verarbeitet
          <template v-if="status.pdf.recent_errors > 0">
            , {{ status.pdf.recent_errors }} Fehler
          </template>
        </span>
      </template>

      <button
        class="p-0.5 rounded hover:bg-[var(--color-surface)] text-[var(--color-text-tertiary)] shrink-0"
        @click="dismiss"
      >
        <X class="w-3.5 h-3.5" />
      </button>
    </div>
  </Transition>
</template>

<style scoped>
.slide-down-enter-active,
.slide-down-leave-active { transition: all 0.3s ease; }
.slide-down-enter-from,
.slide-down-leave-to { opacity: 0; transform: translateY(-8px); }
</style>
