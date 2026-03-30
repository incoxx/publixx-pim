<script setup>
import { ref, computed } from 'vue'
import { useApiDesignerStore } from '@/stores/apiDesigner'
import { X, Copy, Check, RefreshCw, ExternalLink } from 'lucide-vue-next'
import apiTemplatesApi from '@/api/apiTemplates'

const emit = defineEmits(['close'])
const store = useApiDesignerStore()
const copied = ref(false)
const regenerating = ref(false)
const newApiKey = ref('')

const apiBase = (import.meta.env.VITE_API_BASE_URL || '/api/v1').replace(/\/+$/, '')
const streamUrl = computed(() =>
  `${window.location.origin}${apiBase}/api-streams/${store.currentTemplate?.slug || '...'}`
)

function copyUrl() {
  navigator.clipboard.writeText(streamUrl)
  copied.value = true
  setTimeout(() => { copied.value = false }, 2000)
}

async function regenerateKey() {
  if (!confirm('Neuen API-Key generieren? Der alte Key wird sofort ungültig.')) return
  regenerating.value = true
  try {
    const { data } = await apiTemplatesApi.regenerateKey(store.currentTemplate.id)
    newApiKey.value = data.api_key_plain
    store.currentTemplate = data.data || store.currentTemplate
  } finally {
    regenerating.value = false
  }
}

function copyKey() {
  navigator.clipboard.writeText(newApiKey.value)
}

function updateSlug(value) {
  if (store.currentTemplate) {
    store.currentTemplate.slug = value
    store.isDirty = true
  }
}

function updateAuthType(value) {
  if (store.currentTemplate) {
    store.currentTemplate.auth_type = value
    store.isDirty = true
  }
}

function updateDirection(value) {
  if (store.currentTemplate) {
    store.currentTemplate.direction = value
    store.isDirty = true
  }
}

function updateRateLimit(value) {
  if (store.currentTemplate) {
    store.currentTemplate.rate_limit = parseInt(value) || 60
    store.isDirty = true
  }
}
</script>

<template>
  <!-- Modal Overlay -->
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="emit('close')">
    <div class="bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg shadow-xl w-[480px] max-h-[80vh] overflow-y-auto">
      <!-- Header -->
      <div class="flex items-center justify-between px-4 py-3 border-b border-[var(--color-border)]">
        <span class="text-sm font-semibold text-[var(--color-text-primary)]">Stream-Einstellungen</span>
        <button @click="emit('close')" class="text-[var(--color-text-tertiary)] hover:text-[var(--color-text-primary)]">
          <X class="w-4 h-4" :stroke-width="2" />
        </button>
      </div>

      <!-- Content -->
      <div class="p-4 space-y-4">
        <!-- Slug -->
        <div>
          <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">URL-Slug</label>
          <input
            :value="store.currentTemplate?.slug || ''"
            class="pim-input text-xs w-full font-mono"
            placeholder="product-catalog"
            @input="updateSlug($event.target.value)"
          />
          <p class="text-[9px] text-[var(--color-text-tertiary)] mt-0.5">Wird Teil der Stream-URL</p>
        </div>

        <!-- Stream URL -->
        <div>
          <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Stream-URL</label>
          <div class="flex items-center gap-2">
            <code class="flex-1 text-[11px] font-mono bg-[var(--color-bg)] px-2 py-1.5 rounded text-[var(--color-text-secondary)] truncate">
              {{ streamUrl }}
            </code>
            <button class="shrink-0" @click="copyUrl" :title="copied ? 'Kopiert!' : 'URL kopieren'">
              <Check v-if="copied" class="w-3.5 h-3.5 text-green-500" :stroke-width="2" />
              <Copy v-else class="w-3.5 h-3.5 text-[var(--color-text-tertiary)]" :stroke-width="2" />
            </button>
          </div>
        </div>

        <!-- Direction -->
        <div>
          <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Datenrichtung</label>
          <select
            :value="store.currentTemplate?.direction || 'export'"
            class="pim-input text-xs w-full"
            @change="updateDirection($event.target.value)"
          >
            <option value="export">Export (nur Lesen)</option>
            <option value="import">Import (nur Schreiben)</option>
            <option value="bidirectional">Bidirektional (Lesen + Schreiben)</option>
          </select>
          <p class="text-[9px] text-[var(--color-text-tertiary)] mt-0.5">Bei "Import" oder "Bidirektional" werden GraphQL-Mutationen freigeschaltet</p>
        </div>

        <!-- Auth Type -->
        <div>
          <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Authentifizierung</label>
          <select
            :value="store.currentTemplate?.auth_type || 'api_key'"
            class="pim-input text-xs w-full"
            @change="updateAuthType($event.target.value)"
          >
            <option value="api_key">API-Key (X-Api-Key Header)</option>
            <option value="bearer">Bearer Token (Sanctum)</option>
            <option value="none">Keine (Öffentlich)</option>
          </select>
        </div>

        <!-- API Key -->
        <div v-if="store.currentTemplate?.auth_type === 'api_key' || !store.currentTemplate?.auth_type">
          <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">API-Key</label>
          <div v-if="newApiKey" class="space-y-1">
            <div class="flex items-center gap-2 bg-green-50 dark:bg-green-900/20 p-2 rounded">
              <code class="flex-1 text-[11px] font-mono text-green-700 dark:text-green-300 break-all">{{ newApiKey }}</code>
              <button @click="copyKey" class="shrink-0">
                <Copy class="w-3.5 h-3.5 text-green-600" :stroke-width="2" />
              </button>
            </div>
            <p class="text-[9px] text-amber-600">Diesen Key jetzt kopieren! Er wird nicht erneut angezeigt.</p>
          </div>
          <div v-else class="flex items-center gap-2">
            <span class="text-[11px] text-[var(--color-text-tertiary)]">Key ist gesetzt (nicht einsehbar)</span>
            <button
              class="pim-btn pim-btn-secondary text-[11px] px-2 py-1"
              @click="regenerateKey"
              :disabled="regenerating"
            >
              <RefreshCw class="w-3 h-3" :stroke-width="2" :class="regenerating ? 'animate-spin' : ''" />
              Neuen Key
            </button>
          </div>
        </div>

        <!-- Rate Limit -->
        <div>
          <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Rate-Limit (Requests/Minute)</label>
          <input
            type="number"
            :value="store.currentTemplate?.rate_limit || 60"
            class="pim-input text-xs w-24"
            min="1"
            max="10000"
            @input="updateRateLimit($event.target.value)"
          />
        </div>

        <!-- cURL Example -->
        <div>
          <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Beispiel-Aufruf</label>
          <!-- Mutation-Beispiel bei Import/Bidirektional + GraphQL -->
          <pre v-if="store.currentTemplate?.output_format === 'graphql' && ['import', 'bidirectional'].includes(store.currentTemplate?.direction)" class="text-[10px] font-mono bg-[var(--color-bg)] p-2 rounded text-[var(--color-text-secondary)] whitespace-pre-wrap">curl -X POST \
  -H "X-Api-Key: YOUR_KEY" \
  -H "Content-Type: application/json" \
  -d '{"query": "mutation { createProduct(input: { sku: \"P-001\", name: \"Test\", product_type_id: \"...\" }) { success message product { sku } } }"}' \
  {{ streamUrl }}</pre>
          <pre v-else-if="store.currentTemplate?.output_format === 'graphql'" class="text-[10px] font-mono bg-[var(--color-bg)] p-2 rounded text-[var(--color-text-secondary)] whitespace-pre-wrap">curl -X POST \
  -H "X-Api-Key: YOUR_KEY" \
  -H "Content-Type: application/json" \
  -d '{"query": "{ total groups { products { sku name } } }"}' \
  {{ streamUrl }}</pre>
          <pre v-else class="text-[10px] font-mono bg-[var(--color-bg)] p-2 rounded text-[var(--color-text-secondary)] whitespace-pre-wrap">curl -H "X-Api-Key: YOUR_KEY" \
  {{ streamUrl }}</pre>
        </div>
      </div>

      <!-- Footer -->
      <div class="px-4 py-3 border-t border-[var(--color-border)] flex justify-end">
        <button class="pim-btn pim-btn-secondary text-xs" @click="emit('close')">Schließen</button>
      </div>
    </div>
  </div>
</template>
