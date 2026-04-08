<script setup>
import { computed } from 'vue'
import { X, Loader2, CheckCircle2, XCircle, Ban } from 'lucide-vue-next'

const props = defineProps({
  progress: {
    type: Object,
    required: true,
  },
  title: {
    type: String,
    default: 'Export',
  },
})

const emit = defineEmits(['cancel', 'dismiss'])

const status = computed(() => props.progress?.status || 'queued')
const percent = computed(() => props.progress?.percent || 0)
const processed = computed(() => props.progress?.processed || 0)
const total = computed(() => props.progress?.total || 0)

const statusLabel = computed(() => {
  switch (status.value) {
    case 'queued':     return 'In Warteschlange...'
    case 'collecting': return total.value > 0 ? `Daten laden... (${processed.value} / ${total.value})` : 'Daten laden...'
    case 'writing':    return 'Datei schreiben...'
    case 'completed':  return 'Export abgeschlossen!'
    case 'cancelled':  return 'Export abgebrochen.'
    case 'cancelling': return 'Wird abgebrochen...'
    case 'failed':     return 'Export fehlgeschlagen.'
    default:           return 'Exportiere...'
  }
})

const isActive = computed(() => ['queued', 'collecting', 'writing', 'cancelling'].includes(status.value))
const isDone = computed(() => ['completed', 'cancelled', 'failed'].includes(status.value))
const canCancel = computed(() => ['queued', 'collecting', 'writing'].includes(status.value))
</script>

<template>
  <div class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-6" @click.self="isDone && emit('dismiss')">
    <div class="bg-[var(--color-surface)] rounded-xl shadow-2xl w-full max-w-sm">
      <!-- Header -->
      <div class="flex items-center justify-between px-5 py-3 border-b border-[var(--color-border)]">
        <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">{{ title }}</h3>
        <button
          v-if="isDone"
          class="p-1.5 text-[var(--color-text-tertiary)] hover:text-[var(--color-text-primary)]"
          @click="emit('dismiss')"
        >
          <X class="w-4 h-4" :stroke-width="2" />
        </button>
      </div>

      <!-- Content -->
      <div class="p-5 space-y-4">
        <!-- Status Icon + Text -->
        <div class="flex items-center gap-3">
          <Loader2 v-if="isActive" class="w-5 h-5 text-[var(--color-accent)] animate-spin shrink-0" :stroke-width="2" />
          <CheckCircle2 v-else-if="status === 'completed'" class="w-5 h-5 text-emerald-500 shrink-0" :stroke-width="2" />
          <XCircle v-else-if="status === 'failed'" class="w-5 h-5 text-[var(--color-error)] shrink-0" :stroke-width="2" />
          <Ban v-else class="w-5 h-5 text-amber-500 shrink-0" :stroke-width="2" />

          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-[var(--color-text-primary)]">{{ statusLabel }}</p>
            <p v-if="progress?.error" class="text-xs text-[var(--color-error)] mt-0.5 break-words">{{ progress.error }}</p>
          </div>
        </div>

        <!-- Progress Bar (nur wenn total bekannt) -->
        <div v-if="total > 0" class="space-y-1">
          <div class="w-full h-2 bg-[var(--color-bg)] rounded-full overflow-hidden">
            <div
              class="h-full rounded-full transition-all duration-300"
              :class="status === 'failed' ? 'bg-[var(--color-error)]' : status === 'cancelled' ? 'bg-amber-500' : 'bg-[var(--color-accent)]'"
              :style="{ width: percent + '%' }"
            />
          </div>
          <div class="flex justify-between text-[11px] text-[var(--color-text-tertiary)]">
            <span>{{ processed.toLocaleString('de-DE') }} / {{ total.toLocaleString('de-DE') }}</span>
            <span>{{ percent }}%</span>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="px-5 py-3 border-t border-[var(--color-border)] flex justify-end gap-2">
        <button
          v-if="canCancel"
          class="pim-btn pim-btn-secondary text-xs text-[var(--color-error)]"
          @click="emit('cancel')"
        >
          Abbrechen
        </button>
        <button
          v-if="isDone"
          class="pim-btn pim-btn-primary text-xs"
          @click="emit('dismiss')"
        >
          Schließen
        </button>
      </div>
    </div>
  </div>
</template>
