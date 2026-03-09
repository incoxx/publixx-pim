<script setup>
import { Radio, Copy, CheckCircle, ExternalLink } from 'lucide-vue-next'
import { useClipboard } from '@/composables/useClipboard'

const props = defineProps({
  url: { type: String, required: true },
  label: { type: String, default: 'JSON Stream API' },
  hint: { type: String, default: 'Daten direkt per API abrufen' },
})

const { copied, copy } = useClipboard()
</script>

<template>
  <div class="p-3 rounded-lg bg-[var(--color-bg)] border border-[var(--color-border)]">
    <div class="flex items-center gap-1.5 mb-2">
      <Radio class="w-3.5 h-3.5 text-green-500" :stroke-width="2" />
      <span class="text-[11px] font-semibold text-[var(--color-text-primary)]">{{ label }}</span>
      <span v-if="hint" class="text-[10px] text-[var(--color-text-tertiary)]">— {{ hint }}</span>
    </div>
    <div class="flex items-center gap-1.5">
      <code class="flex-1 text-[11px] font-mono text-[var(--color-text-secondary)] bg-[var(--color-surface)] px-2.5 py-2 rounded select-all overflow-x-auto whitespace-nowrap">{{ url }}</code>
      <button
        class="p-1.5 rounded hover:bg-[var(--color-surface)] text-[var(--color-text-tertiary)] hover:text-[var(--color-text-primary)] transition-colors shrink-0"
        @click="copy(url)"
        :title="copied ? 'Kopiert!' : 'URL kopieren'"
      >
        <CheckCircle v-if="copied" class="w-4 h-4 text-green-500" :stroke-width="2" />
        <Copy v-else class="w-4 h-4" :stroke-width="2" />
      </button>
      <a
        :href="url"
        target="_blank"
        class="p-1.5 rounded hover:bg-[var(--color-surface)] text-[var(--color-text-tertiary)] hover:text-blue-500 transition-colors shrink-0"
        title="Im Browser öffnen"
      >
        <ExternalLink class="w-4 h-4" :stroke-width="2" />
      </a>
    </div>
  </div>
</template>
