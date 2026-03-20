<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import {
  Zap, Plus, Trash2, RefreshCw, CheckCircle, Settings, FileText,
  Search, Sparkles, Save, Copy, ChevronDown, X, Type, Globe,
  BarChart3, Megaphone, List, PenTool,
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
const attrSearchTerm = ref('')

// Generation config
const task = ref('description')
const tonality = ref('')
const customPrompt = ref('')
const language = ref('de')
const showAdvanced = ref(false)

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
  { value: 'description', label: 'Produktbeschreibung', desc: 'Informative Beschreibung für Online-Shop', icon: 'Type' },
  { value: 'seo', label: 'SEO-Text', desc: 'Meta-Title, Description & SEO-Text', icon: 'BarChart3' },
  { value: 'features', label: 'Merkmale', desc: 'Strukturierte Bullet-Point-Liste', icon: 'List' },
  { value: 'marketing', label: 'Marketing-Text', desc: 'Emotionaler, überzeugender Text', icon: 'Megaphone' },
]

const taskIcons = { Type, BarChart3, List, Megaphone }

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

const filteredAttributes = computed(() => {
  const attrs = allAttributes.value.filter(a => !a.is_internal)
  if (!attrSearchTerm.value) return attrs
  const term = attrSearchTerm.value.toLowerCase()
  return attrs.filter(a =>
    (a.name_de || '').toLowerCase().includes(term) ||
    (a.technical_name || '').toLowerCase().includes(term),
  )
})

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
  <div class="space-y-5 max-w-4xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center shadow-sm">
          <Sparkles class="w-5 h-5 text-white" />
        </div>
        <div>
          <h1 class="text-lg font-bold">Claude AI Textgenerator</h1>
          <p class="text-xs text-base-content/50">KI-generierte Produkttexte, SEO und Marketing-Inhalte</p>
        </div>
      </div>
      <button class="btn btn-sm btn-ghost btn-square" @click="loadData" title="Neu laden">
        <RefreshCw class="w-4 h-4" />
      </button>
    </div>

    <div v-if="error" class="alert alert-error text-sm">{{ error }}</div>

    <div v-if="loading" class="flex justify-center py-12">
      <span class="loading loading-spinner loading-lg text-primary"></span>
    </div>

    <template v-if="!loading">
      <!-- ═══════ Connections (kompakt) ═══════ -->
      <div class="flex items-center gap-2 flex-wrap">
        <span class="text-xs font-medium text-base-content/40 uppercase tracking-wider mr-1">Verbindung:</span>
        <button
          v-for="conn in connections" :key="conn.id"
          class="btn btn-xs gap-1"
          :class="activeConnectionId === conn.id ? 'btn-primary' : 'btn-ghost border border-base-300'"
          @click="activeConnectionId = conn.id"
        >
          <CheckCircle v-if="activeConnectionId === conn.id" class="w-3 h-3" />
          {{ conn.name }}
          <span class="hover:text-error cursor-pointer" @click.stop="deleteConnection(conn.id)">
            <X class="w-3 h-3" />
          </span>
        </button>
        <button class="btn btn-xs btn-ghost border border-dashed border-base-300" @click="showConnectForm = true">
          <Plus class="w-3 h-3" />
          Hinzufügen
        </button>
      </div>

      <!-- Connect Form (inline) -->
      <div v-if="showConnectForm" class="card bg-base-200/40 border border-base-300">
        <div class="card-body p-4">
          <div class="flex items-end gap-3">
            <div class="form-control flex-1">
              <label class="label py-0.5"><span class="label-text text-xs">Name</span></label>
              <input v-model="formData.name" type="text" class="input input-bordered input-sm" />
            </div>
            <div class="form-control flex-1">
              <label class="label py-0.5"><span class="label-text text-xs">Anthropic API-Key</span></label>
              <input v-model="formData.api_key" type="password" class="input input-bordered input-sm" placeholder="sk-ant-..." />
            </div>
            <button class="btn btn-primary btn-sm" :disabled="connecting || !formData.api_key.trim()" @click="connectClaudeAI">
              <span v-if="connecting" class="loading loading-spinner loading-xs"></span>
              Verbinden
            </button>
            <button class="btn btn-ghost btn-sm" @click="showConnectForm = false">
              <X class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>

      <!-- ═══════ Text-Generator ═══════ -->
      <template v-if="activeConnection">
        <div class="card bg-base-100 shadow-sm border border-base-200">
          <div class="card-body p-5 space-y-5">

            <!-- ── Produkt suchen ── -->
            <div>
              <label class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mb-1.5 block">Produkt</label>

              <div v-if="selectedProduct" class="flex items-center gap-3 px-4 py-3 bg-primary/5 border border-primary/20 rounded-xl">
                <div class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center flex-shrink-0">
                  <FileText class="w-4 h-4 text-primary" />
                </div>
                <div class="flex-1 min-w-0">
                  <div class="font-semibold text-sm truncate">{{ selectedProduct.name }}</div>
                  <div class="text-xs text-base-content/40">SKU: {{ selectedProduct.sku }}<span v-if="selectedProduct.ean"> &middot; EAN: {{ selectedProduct.ean }}</span></div>
                </div>
                <button class="btn btn-ghost btn-xs btn-square" @click="clearProduct">
                  <X class="w-4 h-4" />
                </button>
              </div>

              <div v-else class="relative">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-base-content/30" />
                <input
                  v-model="productSearch"
                  type="text"
                  class="input input-bordered w-full pl-10"
                  placeholder="Produkt suchen (Name, SKU, EAN)..."
                />
                <span v-if="searching" class="absolute right-3 top-1/2 -translate-y-1/2 loading loading-spinner loading-xs text-primary"></span>

                <div v-if="productResults.length > 0" class="absolute z-20 mt-1 w-full bg-base-100 border border-base-300 rounded-xl shadow-xl max-h-64 overflow-y-auto">
                  <button
                    v-for="p in productResults" :key="p.id"
                    class="w-full text-left px-4 py-2.5 hover:bg-primary/5 flex justify-between items-center border-b border-base-200 last:border-0 transition-colors"
                    @click="selectProduct(p)"
                  >
                    <div>
                      <div class="font-medium text-sm">{{ p.name }}</div>
                      <div class="text-xs text-base-content/40">{{ p.sku }}</div>
                    </div>
                  </button>
                </div>
              </div>
            </div>

            <!-- ── Aufgabe + Sprache (Grid) ── -->
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
              <div class="lg:col-span-3">
                <label class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mb-1.5 block">Aufgabe</label>
                <div class="grid grid-cols-4 gap-2">
                  <button
                    v-for="t in tasks" :key="t.value"
                    class="flex flex-col items-center gap-1 p-3 rounded-xl border-2 transition-all text-center cursor-pointer"
                    :class="task === t.value
                      ? 'border-primary bg-primary/5 text-primary shadow-sm'
                      : 'border-base-200 hover:border-base-300 hover:bg-base-200/50'"
                    @click="task = t.value"
                  >
                    <component :is="taskIcons[t.icon]" class="w-5 h-5" :class="task === t.value ? 'text-primary' : 'text-base-content/40'" />
                    <span class="text-xs font-semibold leading-tight">{{ t.label }}</span>
                    <span class="text-[10px] leading-tight opacity-50 hidden sm:block">{{ t.desc }}</span>
                  </button>
                </div>
              </div>

              <div>
                <label class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mb-1.5 block">Sprache</label>
                <select v-model="language" class="select select-bordered w-full">
                  <option value="de">Deutsch</option>
                  <option value="en">English</option>
                  <option value="fr">Français</option>
                  <option value="es">Español</option>
                  <option value="it">Italiano</option>
                </select>
              </div>
            </div>

            <!-- ── Tonalität ── -->
            <div>
              <label class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mb-1.5 block">Tonalität / Stil</label>
              <input
                v-model="tonality"
                type="text"
                class="input input-bordered w-full"
                placeholder="z.B. Professionell & sachlich, Premium & exklusiv..."
              />
              <div class="flex gap-1.5 mt-2 flex-wrap">
                <button
                  v-for="preset in tonalityPresets" :key="preset"
                  class="px-2.5 py-1 rounded-full text-xs font-medium transition-all cursor-pointer"
                  :class="tonality === preset
                    ? 'bg-primary text-primary-content shadow-sm'
                    : 'bg-base-200 text-base-content/60 hover:bg-base-300'"
                  @click="tonality = tonality === preset ? '' : preset"
                >
                  {{ preset }}
                </button>
              </div>
            </div>

            <!-- ── Erweiterte Optionen (collapsible) ── -->
            <div class="border border-base-200 rounded-xl overflow-hidden">
              <button
                class="w-full flex items-center justify-between px-4 py-2.5 text-sm font-medium text-base-content/60 hover:bg-base-200/50 transition-colors"
                @click="showAdvanced = !showAdvanced"
              >
                <span class="flex items-center gap-2">
                  <Settings class="w-4 h-4" />
                  Erweiterte Optionen
                </span>
                <ChevronDown class="w-4 h-4 transition-transform" :class="showAdvanced ? 'rotate-180' : ''" />
              </button>

              <div v-if="showAdvanced" class="px-4 pb-4 space-y-4 border-t border-base-200">
                <!-- Attribute selection -->
                <div class="pt-3">
                  <div class="flex items-center justify-between mb-1.5">
                    <label class="text-xs font-semibold text-base-content/50 uppercase tracking-wider">Attribute als Kontext</label>
                    <span class="text-xs text-base-content/40">{{ selectedAttributes.length ? selectedAttributes.length + ' gewählt' : 'Alle Attribute' }}</span>
                  </div>

                  <div class="relative">
                    <button
                      class="w-full flex items-center justify-between px-3 py-2 border border-base-300 rounded-lg text-sm hover:bg-base-200/50 transition-colors"
                      @click="showAttrDropdown = !showAttrDropdown"
                    >
                      <span class="truncate text-base-content/70">
                        {{ selectedAttributes.length
                          ? selectedAttributes.map(a => a.name_de || a.technical_name).join(', ')
                          : 'Alle Attribute werden als Kontext verwendet'
                        }}
                      </span>
                      <ChevronDown class="w-4 h-4 flex-shrink-0 text-base-content/30" />
                    </button>

                    <div v-if="showAttrDropdown" class="absolute z-20 mt-1 w-full bg-base-100 border border-base-300 rounded-xl shadow-xl max-h-56 overflow-hidden flex flex-col">
                      <div class="px-3 py-2 border-b border-base-200">
                        <input
                          v-model="attrSearchTerm"
                          type="text"
                          class="input input-bordered input-xs w-full"
                          placeholder="Attribut suchen..."
                        />
                      </div>
                      <div class="overflow-y-auto flex-1">
                        <button
                          v-for="attr in filteredAttributes" :key="attr.id"
                          class="w-full text-left px-3 py-1.5 hover:bg-base-200/70 flex items-center gap-2 text-xs transition-colors"
                          @click="toggleAttribute(attr)"
                        >
                          <input type="checkbox" class="checkbox checkbox-xs checkbox-primary" :checked="isAttrSelected(attr)" @click.prevent />
                          <span class="flex-1 truncate">{{ attr.name_de || attr.technical_name }}</span>
                          <span class="text-base-content/20 text-[10px]">{{ attr.data_type }}</span>
                        </button>
                        <div v-if="filteredAttributes.length === 0" class="px-3 py-3 text-xs text-base-content/30 text-center">Keine Attribute gefunden</div>
                      </div>
                    </div>
                  </div>

                  <div v-if="selectedAttributes.length > 0" class="flex gap-1 mt-2 flex-wrap">
                    <span
                      v-for="attr in selectedAttributes" :key="attr.id"
                      class="inline-flex items-center gap-1 px-2 py-0.5 bg-primary/10 text-primary rounded-full text-xs font-medium"
                    >
                      {{ attr.name_de || attr.technical_name }}
                      <button class="hover:text-error transition-colors" @click="toggleAttribute(attr)"><X class="w-3 h-3" /></button>
                    </span>
                    <button class="text-xs text-base-content/40 hover:text-base-content/60 underline" @click="selectedAttributes = []">Zurücksetzen</button>
                  </div>
                </div>

                <!-- Custom Prompt -->
                <div>
                  <div class="flex items-center justify-between mb-1.5">
                    <label class="text-xs font-semibold text-base-content/50 uppercase tracking-wider">Eigener Prompt</label>
                    <span class="text-[10px] text-base-content/30">Ersetzt den Standard-Prompt der Aufgabe</span>
                  </div>
                  <textarea
                    v-model="customPrompt"
                    class="textarea textarea-bordered w-full text-sm"
                    rows="3"
                    placeholder="z.B. Erstelle eine Produktbeschreibung für eine junge Zielgruppe, max. 150 Wörter, mit Fokus auf Nachhaltigkeit..."
                  ></textarea>
                </div>

                <!-- Target attribute + save toggle -->
                <div>
                  <label class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mb-1.5 block">Ziel-Attribut</label>
                  <div class="flex items-center gap-3">
                    <select v-model="targetAttribute" class="select select-bordered flex-1">
                      <option value="">Kein Ziel-Attribut</option>
                      <option
                        v-for="attr in stringAttributes" :key="attr.id"
                        :value="attr.technical_name"
                      >
                        {{ attr.name_de || attr.technical_name }}
                      </option>
                    </select>
                    <label v-if="targetAttribute" class="flex items-center gap-2 cursor-pointer whitespace-nowrap">
                      <input
                        v-model="saveToAttribute"
                        type="checkbox"
                        class="toggle toggle-sm toggle-primary"
                      />
                      <span class="text-xs text-base-content/60">Direkt speichern</span>
                    </label>
                  </div>
                  <p v-if="targetAttribute" class="text-[10px] text-base-content/30 mt-1">
                    Das Ergebnis wird in das Attribut <strong>{{ targetAttribute }}</strong> in der Sprache <strong>{{ language.toUpperCase() }}</strong> geschrieben.
                  </p>
                </div>
              </div>
            </div>

            <!-- ── Generate Button ── -->
            <button
              class="btn btn-primary btn-lg w-full shadow-md"
              :disabled="generating || !selectedProduct"
              @click="generate"
            >
              <span v-if="generating" class="loading loading-spinner loading-md"></span>
              <Sparkles v-else class="w-5 h-5" />
              {{ generating ? 'Text wird generiert...' : 'Text generieren' }}
            </button>
          </div>
        </div>

        <!-- ═══════ Result ═══════ -->
        <div v-if="resultError" class="alert alert-error text-sm shadow-sm">{{ resultError }}</div>

        <div v-if="result" class="card bg-base-100 shadow-md border-2 border-primary/15">
          <div class="card-body p-5 space-y-4">
            <div class="flex items-center justify-between">
              <h3 class="font-bold flex items-center gap-2 text-primary">
                <FileText class="w-5 h-5" />
                Generierter Text
              </h3>
              <div class="flex items-center gap-3 text-xs text-base-content/30">
                <span class="font-mono">{{ result.model }}</span>
                <span v-if="result.usage" class="px-2 py-0.5 bg-base-200 rounded-full">
                  {{ result.usage.input_tokens + result.usage.output_tokens }} Tokens
                </span>
              </div>
            </div>

            <div class="bg-base-200/40 border border-base-200 p-5 rounded-xl whitespace-pre-wrap text-sm leading-relaxed">{{ result.text }}</div>

            <div class="flex items-center gap-2 pt-1">
              <button class="btn btn-sm btn-ghost gap-1" @click="copyResult">
                <Copy class="w-3.5 h-3.5" />
                {{ copied ? 'Kopiert!' : 'Kopieren' }}
              </button>
              <button
                v-if="targetAttribute && !saveToAttribute"
                class="btn btn-sm btn-primary gap-1"
                @click="saveResult"
              >
                <Save class="w-3.5 h-3.5" />
                {{ saved ? 'Gespeichert!' : 'In Attribut speichern' }}
              </button>
              <span v-if="saveToAttribute && targetAttribute" class="text-xs text-success flex items-center gap-1 px-2">
                <CheckCircle class="w-3.5 h-3.5" />
                Gespeichert in <strong>{{ targetAttribute }}</strong>
              </span>
              <div class="ml-auto">
                <button class="btn btn-sm btn-ghost gap-1" @click="generate">
                  <RefreshCw class="w-3.5 h-3.5" />
                  Neu generieren
                </button>
              </div>
            </div>
          </div>
        </div>
      </template>

      <!-- No connection hint -->
      <div v-if="connections.length === 0 && !loading && !showConnectForm" class="card bg-base-200/30 border border-dashed border-base-300">
        <div class="card-body text-center py-10">
          <div class="w-14 h-14 rounded-2xl bg-base-200 flex items-center justify-center mx-auto mb-3">
            <Zap class="w-7 h-7 text-base-content/20" />
          </div>
          <p class="text-sm text-base-content/40 max-w-sm mx-auto">
            Verbinden Sie Ihren Anthropic API-Key, um KI-generierte Produkttexte zu erstellen.
          </p>
          <button class="btn btn-primary btn-sm mx-auto mt-3" @click="showConnectForm = true">
            <Plus class="w-4 h-4" />
            Verbindung erstellen
          </button>
        </div>
      </div>
    </template>
  </div>
</template>
