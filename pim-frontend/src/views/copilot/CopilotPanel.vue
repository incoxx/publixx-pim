<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { Sparkles, X, Send, RotateCcw, AlertTriangle, Loader2 } from 'lucide-vue-next'
import { useCopilotStore } from '@/stores/copilot'
import { useProductStore } from '@/stores/products'
import { useLocaleStore } from '@/stores/locale'

const copilot = useCopilotStore()
const route = useRoute()
const products = useProductStore()
const localeStore = useLocaleStore()

const draft = ref('')
const scrollEl = ref(null)

// Aktueller UI-Kontext für den kontextbewussten Co-Piloten
const context = computed(() => {
  const ctx = {
    route: route.meta?.title || route.path,
    locale: localeStore.currentLocale,
  }
  const cur = products.current
  if (cur && route.path.startsWith('/products/') && cur.sku) {
    ctx.productSku = cur.sku
    if (cur.name) ctx.productName = cur.name
  }
  return ctx
})

const canSend = computed(() => draft.value.trim() !== '' && !copilot.busy && !copilot.pendingMutation)

async function submit() {
  if (!canSend.value) return
  const text = draft.value
  draft.value = ''
  await copilot.send(text, context.value)
}

function onKeydown(e) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault()
    submit()
  }
}

// Lesbare Vorschau der geplanten Mutation
const mutationPreview = computed(() => {
  const input = copilot.pendingMutation?.input || {}
  return {
    slug: input.slug || '—',
    mutation: input.mutation || '',
    variables: input.variables ? JSON.stringify(input.variables, null, 2) : null,
  }
})

// Auto-Scroll bei neuem Inhalt
watch(
  () => [copilot.transcript.length, copilot.streamingText, copilot.toolStatus, copilot.pendingMutation],
  async () => {
    await nextTick()
    if (scrollEl.value) scrollEl.value.scrollTop = scrollEl.value.scrollHeight
  },
)
</script>

<template>
  <transition name="slide-right">
    <div
      v-if="copilot.open"
      class="fixed top-0 right-0 h-screen w-full sm:w-[420px] max-w-[100vw] bg-[var(--color-surface)] border-l border-[var(--color-border)] shadow-lg z-40 flex flex-col"
    >
      <!-- Header -->
      <div class="shrink-0 flex items-center justify-between px-4 py-3 border-b border-[var(--color-border)]">
        <div class="flex items-center gap-2">
          <Sparkles class="w-4 h-4 text-[var(--color-accent)]" :stroke-width="1.75" />
          <span class="text-sm font-semibold text-[var(--color-text-primary)]">anyPIM Copilot</span>
        </div>
        <div class="flex items-center gap-1">
          <button
            class="pim-btn-ghost p-1.5 rounded"
            title="Verlauf zurücksetzen"
            :disabled="copilot.busy"
            @click="copilot.reset()"
          >
            <RotateCcw class="w-4 h-4" :stroke-width="1.75" />
          </button>
          <button class="pim-btn-ghost p-1.5 rounded" title="Schließen" @click="copilot.closePanel()">
            <X class="w-4 h-4" :stroke-width="1.75" />
          </button>
        </div>
      </div>

      <!-- Verlauf -->
      <div ref="scrollEl" class="flex-1 min-h-0 overflow-y-auto px-4 py-4 space-y-3">
        <!-- Leerzustand -->
        <div v-if="copilot.transcript.length === 0 && !copilot.streamingText" class="text-center mt-8 px-2">
          <Sparkles class="w-8 h-8 mx-auto text-[var(--color-accent)] opacity-60" :stroke-width="1.5" />
          <p class="mt-3 text-sm text-[var(--color-text-secondary)]">
            Frag mich zu deinen Produktdaten — z.&nbsp;B.<br />
            <span class="italic">„Welche Produkte haben Schutzart IP55?“</span>
          </p>
        </div>

        <!-- Nachrichten -->
        <div
          v-for="(msg, i) in copilot.transcript"
          :key="i"
          class="flex"
          :class="msg.role === 'user' ? 'justify-end' : 'justify-start'"
        >
          <div
            class="max-w-[85%] rounded-2xl px-3.5 py-2 text-sm whitespace-pre-wrap break-words"
            :class="msg.role === 'user'
              ? 'bg-[var(--color-accent)] text-white rounded-br-sm'
              : 'bg-[var(--color-bg)] text-[var(--color-text-primary)] rounded-bl-sm'"
          >{{ msg.text }}</div>
        </div>

        <!-- Streaming-Antwort -->
        <div v-if="copilot.streamingText" class="flex justify-start">
          <div class="max-w-[85%] rounded-2xl rounded-bl-sm px-3.5 py-2 text-sm whitespace-pre-wrap break-words bg-[var(--color-bg)] text-[var(--color-text-primary)]">{{ copilot.streamingText }}</div>
        </div>

        <!-- Tool-Status -->
        <div v-if="copilot.toolStatus" class="flex justify-start">
          <div class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs bg-[var(--color-bg)] text-[var(--color-text-secondary)] border border-[var(--color-border)]">
            <Loader2 class="w-3.5 h-3.5 animate-spin" :stroke-width="2" />
            {{ copilot.toolStatus }}
          </div>
        </div>

        <!-- Denkindikator -->
        <div v-else-if="copilot.busy && !copilot.streamingText && !copilot.pendingMutation" class="flex justify-start">
          <div class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs bg-[var(--color-bg)] text-[var(--color-text-secondary)] border border-[var(--color-border)]">
            <Loader2 class="w-3.5 h-3.5 animate-spin" :stroke-width="2" />
            Denkt nach…
          </div>
        </div>

        <!-- Mutations-Bestätigung -->
        <div
          v-if="copilot.pendingMutation"
          class="rounded-lg border border-[var(--color-warning,#d97706)] bg-[var(--color-warning-light,#fffbeb)] p-3"
        >
          <div class="flex items-center gap-2 text-sm font-medium text-[var(--color-text-primary)]">
            <AlertTriangle class="w-4 h-4 text-[var(--color-warning,#d97706)]" :stroke-width="1.75" />
            Änderung bestätigen
          </div>
          <p class="mt-1 text-xs text-[var(--color-text-secondary)]">
            Der Copilot möchte Daten in Template <strong>{{ mutationPreview.slug }}</strong> schreiben.
          </p>
          <pre class="mt-2 max-h-40 overflow-auto rounded bg-[var(--color-bg)] p-2 text-[11px] text-[var(--color-text-primary)] whitespace-pre-wrap break-words">{{ mutationPreview.mutation }}</pre>
          <pre v-if="mutationPreview.variables" class="mt-1 max-h-32 overflow-auto rounded bg-[var(--color-bg)] p-2 text-[11px] text-[var(--color-text-secondary)] whitespace-pre-wrap break-words">{{ mutationPreview.variables }}</pre>
          <div class="mt-3 flex gap-2">
            <button class="pim-btn-primary px-3 py-1.5 text-xs rounded" :disabled="copilot.busy" @click="copilot.confirmMutation()">
              Ausführen
            </button>
            <button class="pim-btn-ghost px-3 py-1.5 text-xs rounded border border-[var(--color-border)]" :disabled="copilot.busy" @click="copilot.denyMutation()">
              Ablehnen
            </button>
          </div>
        </div>

        <!-- Fehler -->
        <div v-if="copilot.error" class="rounded-lg border border-[var(--color-error)] bg-[var(--color-error-light)] p-3 text-xs text-[var(--color-error)]">
          {{ copilot.error }}
        </div>
      </div>

      <!-- Eingabe -->
      <div class="shrink-0 border-t border-[var(--color-border)] p-3">
        <div class="flex items-end gap-2">
          <textarea
            v-model="draft"
            rows="1"
            placeholder="Nachricht an den Copilot…"
            class="flex-1 resize-none max-h-32 rounded-lg border border-[var(--color-border)] bg-[var(--color-bg)] px-3 py-2 text-sm text-[var(--color-text-primary)] focus:outline-none focus:border-[var(--color-accent)]"
            :disabled="copilot.busy"
            @keydown="onKeydown"
          />
          <button
            class="pim-btn-primary p-2.5 rounded-lg shrink-0"
            :disabled="!canSend"
            title="Senden (Enter)"
            @click="submit()"
          >
            <Send class="w-4 h-4" :stroke-width="1.75" />
          </button>
        </div>
        <p class="mt-1.5 text-[10px] text-[var(--color-text-tertiary)]">
          Enter senden · Shift+Enter neue Zeile · Verlauf nur in dieser Sitzung
        </p>
      </div>
    </div>
  </transition>
</template>
