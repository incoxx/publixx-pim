<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import {
  Zap, Plus, Trash2, RefreshCw, CheckCircle, Settings, FileText,
  Search, Sparkles, Save, Copy, ChevronDown, X,
} from 'lucide-vue-next'
import connectorsApi from '@/api/connectors'
import searchApi from '@/api/search'
import productsApi from '@/api/products'
import attributesApi from '@/api/attributes'

const router = useRouter()

// ── Connection management ──
const connections = ref([])
const connectorInfo = ref(null)
const loading = ref(false)
const error = ref('')
const showConnectForm = ref(false)
const formData = ref({ name: 'Claude AI-Verbindung', api_key: '' })
const connecting = ref(false)

// ── Text generation ──
const activeConnectionId = ref(null)
const activeConnection = computed(() => connections.value.find(c => c.id === activeConnectionId.value))

// Product search
const productSearch = ref('')
const productResults = ref([])
const searching = ref(false)
const selectedProduct = ref(null)
let searchTimeout = null

// Attributes
const allAttributes = ref([])
const selectedAttributes = ref([])
const showAttrDropdown = ref(false)

// Generation config
const task = ref('description')
const tonality = ref('')
const customPrompt = ref('')
const language = ref('de')

// Target attribute
const targetAttribute = ref('')
const saveToAttribute = ref(false)

// Result
const generating = ref(false)
const result = ref(null)
const resultError = ref('')
const copied = ref(false)
const saved = ref(false)

const tasks = [
  { value: 'description', label: 'Produktbeschreibung', desc: 'Informative Beschreibung für Online-Shop' },
  { value: 'seo', label: 'SEO-Text', desc: 'Meta-Title, Description & SEO-Text' },
  { value: 'features', label: 'Merkmale', desc: 'Strukturierte Bullet-Point-Liste' },
  { value: 'marketing', label: 'Marketing-Text', desc: 'Emotionaler, überzeugender Text' },
]

const tonalityPresets = [
  'Professionell & sachlich',
  'Freundlich & einladend',
  'Premium & exklusiv',
  'Jung & modern',
  'Technisch & detailliert',
]

const stringAttributes = computed(() =>
  allAttributes.value.filter(a => a.data_type === 'String' && a.is_translatable),
)

onMounted(loadData)

async function loadData() {
  loading.value = true
  error.value = ''
  try {
    const [listRes, connRes, attrRes] = await Promise.all([
      connectorsApi.list(),
      connectorsApi.connections(),
      attributesApi.list({ perPage: 500 }),
    ])
    const allConnectors = listRes.data.data || listRes.data
    connectorInfo.value = allConnectors.find(c => c.type === 'claude_ai')
    const allConns = connRes.data.data || connRes.data
    connections.value = allConns.filter(c => c.connector_type === 'claude_ai')
    allAttributes.value = (attrRes.data.data || attrRes.data) ?? []

    // Auto-select first connection
    if (connections.value.length > 0 && !activeConnectionId.value) {
      activeConnectionId.value = connections.value[0].id
    }
  } catch (e) {
    error.value = 'Fehler beim Laden'
  } finally {
    loading.value = false
  }
}

// ── Product search with debounce ──
watch(productSearch, (val) => {
  clearTimeout(searchTimeout)
  if (!val || val.length < 2) {
    productResults.value = []
    return
  }
  searchTimeout = setTimeout(() => searchProducts(val), 300)
})

async function searchProducts(term) {
  searching.value = true
  try {
    const { data } = await searchApi.search({
      search: term,
      per_page: 10,
      language: language.value,
    })
    productResults.value = data.data || data
  } catch {
    productResults.value = []
  } finally {
    searching.value = false
  }
}

function selectProduct(product) {
  selectedProduct.value = product
  productSearch.value = ''
  productResults.value = []
}

function clearProduct() {
  selectedProduct.value = null
  result.value = null
}

// ── Attribute selection ──
function toggleAttribute(attr) {
  const idx = selectedAttributes.value.findIndex(a => a.id === attr.id)
  if (idx >= 0) {
    selectedAttributes.value.splice(idx, 1)
  } else {
    selectedAttributes.value.push(attr)
  }
}

function isAttrSelected(attr) {
  return selectedAttributes.value.some(a => a.id === attr.id)
}

// ── Generate text ──
async function generate() {
  if (!selectedProduct.value || !activeConnectionId.value) return
  generating.value = true
  resultError.value = ''
  result.value = null
  saved.value = false
  try {
    const options = {
      language: language.value,
      task: task.value,
    }
    if (tonality.value) options.tonality = tonality.value
    if (customPrompt.value) options.custom_prompt = customPrompt.value
    if (selectedAttributes.value.length > 0) {
      options.attributes = selectedAttributes.value.map(a => a.technical_name)
    }
    if (saveToAttribute.value && targetAttribute.value) {
      options.save_as_attribute = true
      options.target_attribute = targetAttribute.value
    }

    const { data } = await connectorsApi.syncProduct(
      activeConnectionId.value,
      selectedProduct.value.id,
      options,
    )
    result.value = data.data || data
  } catch (e) {
    resultError.value = e.response?.data?.message || e.message
  } finally {
    generating.value = false
  }
}

// ── Save to attribute (after preview) ──
async function saveResult() {
  if (!result.value?.text || !targetAttribute.value || !selectedProduct.value) return
  saved.value = false
  try {
    const attr = allAttributes.value.find(a => a.technical_name === targetAttribute.value)
    if (!attr) return
    await productsApi.saveAttributeValues(selectedProduct.value.id, [{
      attribute_id: attr.id,
      value: result.value.text,
      language: language.value,
    }])
    saved.value = true
    setTimeout(() => { saved.value = false }, 3000)
  } catch (e) {
    resultError.value = 'Fehler beim Speichern: ' + (e.response?.data?.message || e.message)
  }
}

async function copyResult() {
  if (!result.value?.text) return
  await navigator.clipboard.writeText(result.value.text)
  copied.value = true
  setTimeout(() => { copied.value = false }, 2000)
}

// ── Connection management ──
async function connectClaudeAI() {
  if (!formData.value.api_key.trim()) return
  connecting.value = true
  error.value = ''
  try {
    await connectorsApi.callback('claude_ai', {
      code: formData.value.api_key.trim(),
      name: formData.value.name,
    })
    showConnectForm.value = false
    formData.value = { name: 'Claude AI-Verbindung', api_key: '' }
    await loadData()
  } catch (e) {
    error.value = `Verbindungsfehler: ${e.response?.data?.message || e.message}`
  } finally {
    connecting.value = false
  }
}

async function deleteConnection(id) {
  if (!confirm('Verbindung wirklich trennen?')) return
  try {
    await connectorsApi.deleteConnection(id)
    connections.value = connections.value.filter(c => c.id !== id)
    if (activeConnectionId.value === id) {
      activeConnectionId.value = connections.value[0]?.id || null
    }
  } catch (e) {
    error.value = 'Fehler beim Löschen'
  }
}
</script>

<template>
  <div class="space-y-6 max-w-5xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <Zap class="w-6 h-6 text-amber-500" />
        <div>
          <h1 class="text-xl font-bold">Claude AI</h1>
          <p class="text-sm text-base-content/60">KI-generierte Produkttexte und SEO-Inhalte</p>
        </div>
      </div>
      <button class="btn btn-sm btn-ghost" @click="loadData">
        <RefreshCw class="w-4 h-4" />
      </button>
    </div>

    <div v-if="error" class="alert alert-error">{{ error }}</div>

    <div v-if="loading" class="flex justify-center py-8">
      <span class="loading loading-spinner loading-lg"></span>
    </div>

    <template v-if="!loading">
      <!-- ═══════ Connections ═══════ -->
      <div class="card bg-base-100 shadow-sm border border-base-200">
        <div class="card-body p-4">
          <div class="flex items-center justify-between mb-2">
            <h2 class="text-sm font-semibold text-base-content/60 uppercase tracking-wider">Verbindungen</h2>
            <button class="btn btn-xs btn-primary" @click="showConnectForm = true">
              <Plus class="w-3 h-3" />
              Neue Verbindung
            </button>
          </div>

          <!-- Connect Form -->
          <div v-if="showConnectForm" class="p-3 bg-base-200/50 rounded-lg mb-3">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <div class="form-control">
                <label class="label py-1"><span class="label-text text-xs">Name</span></label>
                <input v-model="formData.name" type="text" class="input input-bordered input-sm" />
              </div>
              <div class="form-control">
                <label class="label py-1"><span class="label-text text-xs">Anthropic API-Key</span></label>
                <input v-model="formData.api_key" type="password" class="input input-bordered input-sm" placeholder="sk-ant-..." />
              </div>
            </div>
            <div class="flex gap-2 mt-2 justify-end">
              <button class="btn btn-ghost btn-xs" @click="showConnectForm = false">Abbrechen</button>
              <button class="btn btn-primary btn-xs" :disabled="connecting || !formData.api_key.trim()" @click="connectClaudeAI">
                <span v-if="connecting" class="loading loading-spinner loading-xs"></span>
                Verbinden
              </button>
            </div>
          </div>

          <div v-if="connections.length === 0" class="text-center py-4 text-base-content/40 text-sm">
            Noch keine Verbindung hergestellt.
          </div>

          <div class="flex flex-wrap gap-2">
            <button
              v-for="conn in connections" :key="conn.id"
              class="btn btn-sm"
              :class="activeConnectionId === conn.id ? 'btn-primary' : 'btn-ghost border border-base-300'"
              @click="activeConnectionId = conn.id"
            >
              <CheckCircle class="w-3 h-3" />
              {{ conn.name }}
              <button class="ml-1 hover:text-error" @click.stop="deleteConnection(conn.id)">
                <Trash2 class="w-3 h-3" />
              </button>
            </button>
          </div>
        </div>
      </div>

      <!-- ═══════ Text-Generator ═══════ -->
      <template v-if="activeConnection">
        <div class="card bg-base-100 shadow-sm border border-base-200">
          <div class="card-body space-y-4">
            <h2 class="font-semibold flex items-center gap-2">
              <Sparkles class="w-5 h-5 text-amber-500" />
              Text generieren
            </h2>

            <!-- Row 1: Product search -->
            <div class="form-control">
              <label class="label py-1"><span class="label-text font-medium">Produkt</span></label>

              <div v-if="selectedProduct" class="flex items-center gap-3 p-3 bg-base-200/50 rounded-lg">
                <div class="flex-1">
                  <div class="font-semibold text-sm">{{ selectedProduct.name }}</div>
                  <div class="text-xs text-base-content/50">SKU: {{ selectedProduct.sku }}</div>
                </div>
                <button class="btn btn-ghost btn-xs" @click="clearProduct">
                  <X class="w-4 h-4" />
                </button>
              </div>

              <div v-else class="relative">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-base-content/40" />
                <input
                  v-model="productSearch"
                  type="text"
                  class="input input-bordered input-sm w-full pl-9"
                  placeholder="Produkt suchen (Name, SKU, EAN)..."
                />
                <span v-if="searching" class="absolute right-3 top-1/2 -translate-y-1/2 loading loading-spinner loading-xs"></span>

                <!-- Dropdown -->
                <div v-if="productResults.length > 0" class="absolute z-20 mt-1 w-full bg-base-100 border border-base-300 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                  <button
                    v-for="p in productResults" :key="p.id"
                    class="w-full text-left px-3 py-2 hover:bg-base-200 flex justify-between items-center text-sm"
                    @click="selectProduct(p)"
                  >
                    <span class="font-medium">{{ p.name }}</span>
                    <span class="text-xs text-base-content/40">{{ p.sku }}</span>
                  </button>
                </div>
              </div>
            </div>

            <!-- Row 2: Task + Language -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div class="form-control md:col-span-2">
                <label class="label py-1"><span class="label-text font-medium">Aufgabe</span></label>
                <div class="grid grid-cols-2 gap-2">
                  <button
                    v-for="t in tasks" :key="t.value"
                    class="btn btn-sm justify-start"
                    :class="task === t.value ? 'btn-primary' : 'btn-ghost border border-base-300'"
                    @click="task = t.value"
                  >
                    <span class="text-left">
                      <span class="block text-xs font-semibold">{{ t.label }}</span>
                      <span class="block text-[10px] opacity-60">{{ t.desc }}</span>
                    </span>
                  </button>
                </div>
              </div>

              <div class="form-control">
                <label class="label py-1"><span class="label-text font-medium">Sprache</span></label>
                <select v-model="language" class="select select-bordered select-sm w-full">
                  <option value="de">Deutsch</option>
                  <option value="en">Englisch</option>
                  <option value="fr">Französisch</option>
                  <option value="es">Spanisch</option>
                  <option value="it">Italienisch</option>
                </select>
              </div>
            </div>

            <!-- Row 3: Tonality -->
            <div class="form-control">
              <label class="label py-1"><span class="label-text font-medium">Tonalität / Stil</span></label>
              <input
                v-model="tonality"
                type="text"
                class="input input-bordered input-sm"
                placeholder="z.B. Professionell & sachlich, Premium & exklusiv..."
              />
              <div class="flex gap-1 mt-1 flex-wrap">
                <button
                  v-for="preset in tonalityPresets" :key="preset"
                  class="badge badge-sm cursor-pointer hover:badge-primary"
                  :class="tonality === preset ? 'badge-primary' : 'badge-ghost'"
                  @click="tonality = tonality === preset ? '' : preset"
                >
                  {{ preset }}
                </button>
              </div>
            </div>

            <!-- Row 4: Attribute selection -->
            <div class="form-control">
              <label class="label py-1">
                <span class="label-text font-medium">Attribute als Kontext</span>
                <span class="label-text-alt text-xs">{{ selectedAttributes.length ? selectedAttributes.length + ' gewählt' : 'alle' }}</span>
              </label>

              <div class="relative">
                <button
                  class="btn btn-sm btn-ghost border border-base-300 w-full justify-between"
                  @click="showAttrDropdown = !showAttrDropdown"
                >
                  <span class="text-xs truncate">
                    {{ selectedAttributes.length
                      ? selectedAttributes.map(a => a.name_de || a.technical_name).join(', ')
                      : 'Alle Attribute (klicken zum Filtern)'
                    }}
                  </span>
                  <ChevronDown class="w-4 h-4 flex-shrink-0" />
                </button>

                <div v-if="showAttrDropdown" class="absolute z-20 mt-1 w-full bg-base-100 border border-base-300 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                  <button
                    v-for="attr in allAttributes.filter(a => !a.is_internal)" :key="attr.id"
                    class="w-full text-left px-3 py-1.5 hover:bg-base-200 flex items-center gap-2 text-xs"
                    @click="toggleAttribute(attr)"
                  >
                    <input type="checkbox" class="checkbox checkbox-xs" :checked="isAttrSelected(attr)" @click.prevent />
                    <span>{{ attr.name_de || attr.technical_name }}</span>
                    <span class="text-base-content/30 ml-auto">{{ attr.data_type }}</span>
                  </button>
                  <div v-if="allAttributes.length === 0" class="px-3 py-2 text-xs text-base-content/40">Keine Attribute gefunden</div>
                </div>
              </div>

              <!-- Selected chips -->
              <div v-if="selectedAttributes.length > 0" class="flex gap-1 mt-1 flex-wrap">
                <span
                  v-for="attr in selectedAttributes" :key="attr.id"
                  class="badge badge-sm badge-primary gap-1"
                >
                  {{ attr.name_de || attr.technical_name }}
                  <button @click="toggleAttribute(attr)"><X class="w-3 h-3" /></button>
                </span>
                <button class="badge badge-sm badge-ghost" @click="selectedAttributes = []">Alle zurücksetzen</button>
              </div>
            </div>

            <!-- Row 5: Custom Prompt -->
            <div class="form-control">
              <label class="label py-1">
                <span class="label-text font-medium">Eigener Prompt</span>
                <span class="label-text-alt text-xs">Optional — ersetzt den Standard-Prompt</span>
              </label>
              <textarea
                v-model="customPrompt"
                class="textarea textarea-bordered textarea-sm"
                rows="2"
                placeholder="z.B. Erstelle eine Produktbeschreibung für eine junge Zielgruppe, max. 150 Wörter..."
              ></textarea>
            </div>

            <!-- Row 6: Target attribute + Save toggle -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="form-control">
                <label class="label py-1"><span class="label-text font-medium">Ziel-Attribut</span></label>
                <select v-model="targetAttribute" class="select select-bordered select-sm w-full">
                  <option value="">– Kein Ziel-Attribut –</option>
                  <option
                    v-for="attr in stringAttributes" :key="attr.id"
                    :value="attr.technical_name"
                  >
                    {{ attr.name_de || attr.technical_name }}
                  </option>
                </select>
              </div>
              <div class="form-control justify-end">
                <label class="label cursor-pointer justify-start gap-2">
                  <input
                    v-model="saveToAttribute"
                    type="checkbox"
                    class="checkbox checkbox-sm checkbox-primary"
                    :disabled="!targetAttribute"
                  />
                  <span class="label-text text-sm">Ergebnis direkt ins Attribut übernehmen</span>
                </label>
              </div>
            </div>

            <!-- Generate Button -->
            <button
              class="btn btn-primary w-full"
              :disabled="generating || !selectedProduct"
              @click="generate"
            >
              <span v-if="generating" class="loading loading-spinner loading-sm"></span>
              <Sparkles v-else class="w-4 h-4" />
              {{ generating ? 'Generiert...' : 'Text generieren' }}
            </button>
          </div>
        </div>

        <!-- ═══════ Result ═══════ -->
        <div v-if="resultError" class="alert alert-error text-sm">{{ resultError }}</div>

        <div v-if="result" class="card bg-base-100 shadow-sm border border-primary/20">
          <div class="card-body space-y-3">
            <div class="flex items-center justify-between">
              <h3 class="font-semibold flex items-center gap-2">
                <FileText class="w-4 h-4 text-primary" />
                Ergebnis
              </h3>
              <div class="flex items-center gap-2 text-xs text-base-content/40">
                <span>{{ result.model }}</span>
                <span v-if="result.usage">{{ result.usage.input_tokens + result.usage.output_tokens }} Tokens</span>
              </div>
            </div>

            <div class="prose prose-sm max-w-none bg-base-200/30 p-4 rounded-lg whitespace-pre-wrap text-sm">{{ result.text }}</div>

            <div class="flex gap-2">
              <button class="btn btn-sm btn-ghost" @click="copyResult">
                <Copy class="w-4 h-4" />
                {{ copied ? 'Kopiert!' : 'Kopieren' }}
              </button>
              <button
                v-if="targetAttribute && !saveToAttribute"
                class="btn btn-sm btn-primary"
                @click="saveResult"
              >
                <Save class="w-4 h-4" />
                {{ saved ? 'Gespeichert!' : 'In Attribut übernehmen' }}
              </button>
              <span v-if="saveToAttribute && targetAttribute" class="text-xs text-success flex items-center gap-1">
                <CheckCircle class="w-3 h-3" />
                Automatisch gespeichert in {{ targetAttribute }}
              </span>
              <button class="btn btn-sm btn-ghost ml-auto" @click="generate">
                <RefreshCw class="w-4 h-4" />
                Neu generieren
              </button>
            </div>
          </div>
        </div>
      </template>

      <!-- No connection hint -->
      <div v-if="connections.length === 0 && !loading" class="card bg-base-200/50 border border-base-300">
        <div class="card-body text-center py-8">
          <Settings class="w-8 h-8 mx-auto text-base-content/30 mb-2" />
          <p class="text-sm text-base-content/50">
            Erstellen Sie eine Verbindung mit Ihrem Anthropic API-Key, um Texte zu generieren.
          </p>
          <button class="btn btn-primary btn-sm mx-auto mt-2" @click="showConnectForm = true">
            <Plus class="w-4 h-4" />
            Verbindung erstellen
          </button>
        </div>
      </div>
    </template>
  </div>
</template>
