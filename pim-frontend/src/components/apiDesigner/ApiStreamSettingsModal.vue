<script setup>
import { ref, computed } from 'vue'
import { useApiDesignerStore } from '@/stores/apiDesigner'
import { X, Copy, Check, RefreshCw } from 'lucide-vue-next'
import apiTemplatesApi from '@/api/apiTemplates'

const emit = defineEmits(['close'])
const store = useApiDesignerStore()
const copied = ref(false)
const copiedFull = ref(false)
const regenerating = ref(false)
const newApiKey = ref('')

// Laufzeit-Parameter (werden nicht gespeichert, nur für URL-Vorschau)
const paramLimit  = ref('')
const paramOffset = ref('')
const paramSince  = ref('')
const paramSort   = ref('')
const paramOrder  = ref('asc')
const paramFields = ref('')

const apiBase = (import.meta.env.VITE_API_BASE_URL || '/api/v1').replace(/\/+$/, '')

const baseStreamUrl = computed(() =>
  `${window.location.origin}${apiBase}/api-streams/${store.currentTemplate?.slug || '...'}`
)

// URL mit allen gesetzten Query-Parametern
const streamUrlWithParams = computed(() => {
  const params = new URLSearchParams()
  if (paramLimit.value)  params.set('limit',  paramLimit.value)
  if (paramOffset.value) params.set('offset', paramOffset.value)
  if (paramSince.value)  params.set('since',  paramSince.value)
  if (paramSort.value)   params.set('sort',   paramSort.value)
  if (paramSort.value && paramOrder.value !== 'asc') params.set('order', paramOrder.value)
  if (paramFields.value) params.set('fields', paramFields.value)
  const qs = params.toString()
  return qs ? `${baseStreamUrl.value}?${qs}` : baseStreamUrl.value
})

const hasParams = computed(() =>
  !!(paramLimit.value || paramOffset.value || paramSince.value || paramSort.value || paramFields.value)
)

function copyBaseUrl() {
  navigator.clipboard.writeText(baseStreamUrl.value)
  copied.value = true
  setTimeout(() => { copied.value = false }, 2000)
}

function copyFullUrl() {
  navigator.clipboard.writeText(streamUrlWithParams.value)
  copiedFull.value = true
  setTimeout(() => { copiedFull.value = false }, 2000)
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

// Curl-Beispiel dynamisch aus URL und Auth-Typ
const curlExample = computed(() => {
  const url = streamUrlWithParams.value
  const isGraphql = store.currentTemplate?.output_format === 'graphql'
  const isMutation = ['import', 'bidirectional'].includes(store.currentTemplate?.direction)
  const auth = store.currentTemplate?.auth_type

  const authFlag = auth === 'api_key' || !auth
    ? '  -H "X-Api-Key: YOUR_KEY" \\\n'
    : auth === 'bearer' ? '  -H "Authorization: Bearer YOUR_TOKEN" \\\n' : ''

  if (isGraphql && isMutation) {
    return `curl -X POST \\\n${authFlag}  -H "Content-Type: application/json" \\\n  -d '{"query": "mutation { createProduct(input: { sku: \\"P-001\\", name: \\"Test\\", product_type_id: \\"...\\" }) { success message product { sku } } }"}' \\\n  ${url}`
  }
  if (isGraphql) {
    return `curl -X POST \\\n${authFlag}  -H "Content-Type: application/json" \\\n  -d '{"query": "{ total groups { products { sku name } } }"}' \\\n  ${url}`
  }
  return `curl \\\n${authFlag}  ${url}`
})
</script>

<template>
  <!-- Modal Overlay -->
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="emit('close')">
    <div class="bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg shadow-xl w-[520px] max-h-[85vh] overflow-y-auto">

      <!-- Header -->
      <div class="flex items-center justify-between px-4 py-3 border-b border-[var(--color-border)]">
        <span class="text-sm font-semibold text-[var(--color-text-primary)]">Stream-Einstellungen</span>
        <button @click="emit('close')" class="text-[var(--color-text-tertiary)] hover:text-[var(--color-text-primary)]">
          <X class="w-4 h-4" :stroke-width="2" />
        </button>
      </div>

      <div class="p-4 space-y-5">

        <!-- ── Konfiguration ──────────────────────────────────────── -->
        <section class="space-y-4">
          <p class="text-[11px] font-semibold text-[var(--color-text-tertiary)] uppercase tracking-wide">Konfiguration</p>

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
        </section>

        <hr class="border-[var(--color-border)]" />

        <!-- ── Query-Parameter ────────────────────────────────────── -->
        <section class="space-y-3">
          <p class="text-[11px] font-semibold text-[var(--color-text-tertiary)] uppercase tracking-wide">Query-Parameter <span class="normal-case font-normal">(Laufzeit · werden nicht gespeichert)</span></p>

          <!-- Paginierung -->
          <div>
            <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Paginierung</label>
            <div class="flex items-center gap-2">
              <div class="flex items-center gap-1.5">
                <span class="text-[10px] text-[var(--color-text-tertiary)] whitespace-nowrap">Limit</span>
                <input
                  v-model="paramLimit"
                  type="number"
                  min="1"
                  placeholder="alle"
                  class="pim-input text-xs w-20"
                />
              </div>
              <div class="flex items-center gap-1.5">
                <span class="text-[10px] text-[var(--color-text-tertiary)] whitespace-nowrap">Offset</span>
                <input
                  v-model="paramOffset"
                  type="number"
                  min="0"
                  placeholder="0"
                  class="pim-input text-xs w-20"
                />
              </div>
            </div>
            <p class="text-[9px] text-[var(--color-text-tertiary)] mt-0.5">?limit=50&amp;offset=100 — Max. Datensätze &amp; Start-Record</p>
          </div>

          <!-- Delta-Sync -->
          <div>
            <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Delta-Sync — nur Änderungen seit</label>
            <input
              v-model="paramSince"
              type="datetime-local"
              class="pim-input text-xs w-full"
            />
            <p class="text-[9px] text-[var(--color-text-tertiary)] mt-0.5">?since= — Nur Produkte die ab diesem Zeitpunkt geändert wurden</p>
          </div>

          <!-- Sortierung -->
          <div>
            <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Sortierung</label>
            <div class="flex items-center gap-2">
              <select v-model="paramSort" class="pim-input text-xs flex-1">
                <option value="">— Standard (Suchprofil) —</option>
                <option value="sku">SKU</option>
                <option value="name">Name</option>
                <option value="status">Status</option>
                <option value="created_at">Erstellt am</option>
                <option value="updated_at">Geändert am</option>
              </select>
              <select v-model="paramOrder" class="pim-input text-xs w-24" :disabled="!paramSort">
                <option value="asc">Aufsteig.</option>
                <option value="desc">Absteig.</option>
              </select>
            </div>
            <p class="text-[9px] text-[var(--color-text-tertiary)] mt-0.5">?sort=sku&amp;order=asc — Überschreibt Suchprofil-Sortierung</p>
          </div>

          <!-- Sparse Fields -->
          <div>
            <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Felder (Sparse Fieldset)</label>
            <input
              v-model="paramFields"
              type="text"
              placeholder="sku, name, price"
              class="pim-input text-xs w-full font-mono"
            />
            <p class="text-[9px] text-[var(--color-text-tertiary)] mt-0.5">?fields=sku,name — Nur diese JSON-Keys pro Produkt (leer = alle)</p>
          </div>
        </section>

        <hr class="border-[var(--color-border)]" />

        <!-- ── URL-Vorschau ───────────────────────────────────────── -->
        <section class="space-y-3">
          <p class="text-[11px] font-semibold text-[var(--color-text-tertiary)] uppercase tracking-wide">Stream-URL</p>

          <!-- Basis-URL -->
          <div>
            <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Basis-URL</label>
            <div class="flex items-center gap-2">
              <code class="flex-1 text-[11px] font-mono bg-[var(--color-bg)] px-2 py-1.5 rounded text-[var(--color-text-secondary)] truncate">
                {{ baseStreamUrl }}
              </code>
              <button class="shrink-0" @click="copyBaseUrl" :title="copied ? 'Kopiert!' : 'URL kopieren'">
                <Check v-if="copied" class="w-3.5 h-3.5 text-green-500" :stroke-width="2" />
                <Copy v-else class="w-3.5 h-3.5 text-[var(--color-text-tertiary)]" :stroke-width="2" />
              </button>
            </div>
          </div>

          <!-- URL mit Parametern (nur wenn welche gesetzt) -->
          <div v-if="hasParams">
            <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">URL mit Parametern</label>
            <div class="flex items-start gap-2">
              <code class="flex-1 text-[11px] font-mono bg-[var(--color-accent)]/5 border border-[var(--color-accent)]/20 px-2 py-1.5 rounded text-[var(--color-text-secondary)] break-all">
                {{ streamUrlWithParams }}
              </code>
              <button class="shrink-0 mt-0.5" @click="copyFullUrl" :title="copiedFull ? 'Kopiert!' : 'URL kopieren'">
                <Check v-if="copiedFull" class="w-3.5 h-3.5 text-green-500" :stroke-width="2" />
                <Copy v-else class="w-3.5 h-3.5 text-[var(--color-text-tertiary)]" :stroke-width="2" />
              </button>
            </div>
          </div>

          <!-- cURL -->
          <div>
            <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">cURL-Beispiel</label>
            <pre class="text-[10px] font-mono bg-[var(--color-bg)] p-2 rounded text-[var(--color-text-secondary)] whitespace-pre-wrap break-all">{{ curlExample }}</pre>
          </div>
        </section>

      </div>

      <!-- Footer -->
      <div class="px-4 py-3 border-t border-[var(--color-border)] flex justify-end">
        <button class="pim-btn pim-btn-secondary text-xs" @click="emit('close')">Schließen</button>
      </div>
    </div>
  </div>
</template>
