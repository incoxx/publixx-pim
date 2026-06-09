<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { Play, Copy, Check, ChevronDown, ChevronRight, Sparkles, Database, ShieldCheck, BarChart3, RefreshCw } from 'lucide-vue-next'
import apiTemplatesApi from '@/api/apiTemplates'
import client from '@/api/client'

const route = useRoute()

// ── State ─────────────────────────────────────────────────────────────────

const templates      = ref([])
const loading        = ref(false)
const running        = ref(false)
const error          = ref('')
const response       = ref(null)
const responseMs     = ref(null)
const copied         = ref(false)
const showUseCases   = ref(true)

const selectedTool   = ref('stream_products')
const selectedSlug   = ref('')
const paramLimit     = ref('10')
const paramOffset    = ref('')
const paramSince     = ref('')
const paramSort      = ref('')
const paramOrder     = ref('asc')
const paramFields    = ref('')
const paramLang      = ref('')
const graphqlQuery   = ref('{ total count groups { count products { sku name status } } }')
const graphqlVars    = ref('')

// search_products
const searchQuery      = ref('')
const searchStatus     = ref('')

// PIM-Struktur-Tools
const hierarchies      = ref([])
const paramSearch      = ref('')
const paramDataType    = ref('')
const paramHierarchyId = ref('')
const paramNodeId      = ref('')
const paramNodeType    = ref('both')
// update_product_attribute (schreibend)
const updProduct       = ref('')
const updAttribute     = ref('')
const updValue         = ref('')
const updUnit          = ref('')
const updLanguage      = ref('')

// ── Tools-Konfiguration ────────────────────────────────────────────────────

const tools = [
  { id: 'list_templates',  label: 'list_templates',  icon: '📋', desc: 'Alle aktiven API-Templates auflisten' },
  { id: 'search_products', label: 'search_products', icon: '🔎', desc: 'Volltextsuche über Produkte (FULLTEXT)' },
  { id: 'stream_products', label: 'stream_products', icon: '📦', desc: 'Produkte aus JSON-Template abrufen' },
  { id: 'graphql_query',   label: 'graphql_query',   icon: '🔍', desc: 'GraphQL-Query gegen Template ausführen' },
  { id: 'graphql_mutate',  label: 'graphql_mutate',  icon: '✏️',  desc: 'GraphQL-Mutation (Daten schreiben)' },
  { id: 'get_schema',      label: 'get_schema',      icon: '📐', desc: 'GraphQL-Schema (SDL) abrufen' },
  { id: 'list_attributes',       label: 'list_attributes',       icon: '🏷️', desc: 'Attribut-Definitionen inkl. UUID' },
  { id: 'list_hierarchies',      label: 'list_hierarchies',      icon: '🗂️', desc: 'Hierarchien (master/output)' },
  { id: 'list_hierarchy_nodes',  label: 'list_hierarchy_nodes',  icon: '🌳', desc: 'Knoten einer Hierarchie' },
  { id: 'list_node_attributes',  label: 'list_node_attributes',  icon: '🔖', desc: 'Attribute eines Knotens' },
  { id: 'list_node_products',    label: 'list_node_products',    icon: '📦', desc: 'Produkte eines Knotens (master+output)' },
  { id: 'update_product_attribute', label: 'update_product_attribute', icon: '✏️', desc: 'Attributwert setzen (schreibend)' },
]

// ── Vordefinierte Beispiel-Queries ─────────────────────────────────────────

const examples = [
  {
    category: 'Datenschema',
    icon: Database,
    color: 'text-blue-500',
    items: [
      {
        label: 'Schema anzeigen',
        tool: 'get_schema',
        apply() { selectedTool.value = 'get_schema' },
      },
      {
        label: 'Alle Templates',
        tool: 'list_templates',
        apply() { selectedTool.value = 'list_templates' },
      },
    ],
  },
  {
    category: 'Datenqualität',
    icon: ShieldCheck,
    color: 'text-amber-500',
    items: [
      {
        label: 'Produkte ohne Beschreibung',
        tool: 'graphql_query',
        apply() {
          selectedTool.value = 'graphql_query'
          graphqlQuery.value = '{ total count groups { products { sku name status } } }'
        },
      },
      {
        label: 'Zuletzt geänderte Produkte',
        tool: 'stream_products',
        apply() {
          selectedTool.value = 'stream_products'
          paramSort.value = 'updated_at'
          paramOrder.value = 'desc'
          paramLimit.value = '20'
          paramSince.value = ''
        },
      },
      {
        label: 'Delta-Sync (letzte 7 Tage)',
        tool: 'stream_products',
        apply() {
          const d = new Date()
          d.setDate(d.getDate() - 7)
          selectedTool.value = 'stream_products'
          paramSince.value = d.toISOString().slice(0, 16)
          paramLimit.value = '50'
        },
      },
      {
        label: 'Neueste Produkte',
        tool: 'stream_products',
        apply() {
          selectedTool.value = 'stream_products'
          paramSort.value = 'created_at'
          paramOrder.value = 'desc'
          paramLimit.value = '10'
          paramSince.value = ''
          paramFields.value = ''
        },
      },
    ],
  },
  {
    category: 'Auswertungen',
    icon: BarChart3,
    color: 'text-green-500',
    items: [
      {
        label: 'Produkte nach Status',
        tool: 'graphql_query',
        apply() {
          selectedTool.value = 'graphql_query'
          graphqlQuery.value = '{ total count groups { field value count } }'
        },
      },
      {
        label: 'SKU + Name (kompakt)',
        tool: 'stream_products',
        apply() {
          selectedTool.value = 'stream_products'
          paramFields.value = 'sku,name,status'
          paramLimit.value = '50'
        },
      },
      {
        label: 'Sprachvergleich EN',
        tool: 'stream_products',
        apply() {
          selectedTool.value = 'stream_products'
          paramLang.value = 'en'
          paramLimit.value = '10'
        },
      },
      {
        label: 'Pagination (Seite 2)',
        tool: 'stream_products',
        apply() {
          selectedTool.value = 'stream_products'
          paramLimit.value = '10'
          paramOffset.value = '10'
          paramSort.value = 'sku'
          paramOrder.value = 'asc'
          paramFields.value = 'sku,name'
        },
      },
    ],
  },
  {
    category: 'PIM-Struktur',
    icon: Database,
    color: 'text-purple-500',
    items: [
      {
        label: 'Attribute „gewicht" (mit IDs)',
        tool: 'list_attributes',
        apply() {
          selectedTool.value = 'list_attributes'
          paramSearch.value = 'gewicht'
          paramDataType.value = ''
        },
      },
      {
        label: 'Alle Hierarchien',
        tool: 'list_hierarchies',
        apply() {
          selectedTool.value = 'list_hierarchies'
          paramNodeType.value = 'both'
        },
      },
      {
        label: 'Knoten der ersten Hierarchie',
        tool: 'list_hierarchy_nodes',
        apply() {
          selectedTool.value = 'list_hierarchy_nodes'
          paramHierarchyId.value = hierarchies.value[0]?.id || ''
          paramSearch.value = ''
        },
      },
    ],
  },
]

// ── Computed ───────────────────────────────────────────────────────────────

const jsonTemplates = computed(() => templates.value.filter(t => t.output_format === 'json'))
const graphqlTemplates = computed(() => templates.value.filter(t => t.output_format === 'graphql'))

const needsSlug = computed(() => ['stream_products', 'search_products', 'graphql_query', 'graphql_mutate', 'get_schema'].includes(selectedTool.value))
const isGraphql  = computed(() => ['graphql_query', 'graphql_mutate'].includes(selectedTool.value))
const isStream   = computed(() => selectedTool.value === 'stream_products')
const isSearch   = computed(() => selectedTool.value === 'search_products')

// PIM-Struktur-Tools
const isAttributes     = computed(() => selectedTool.value === 'list_attributes')
const isHierarchies    = computed(() => selectedTool.value === 'list_hierarchies')
const isHierarchyNodes = computed(() => selectedTool.value === 'list_hierarchy_nodes')
const isNodeAttributes = computed(() => selectedTool.value === 'list_node_attributes')
const isNodeProducts   = computed(() => selectedTool.value === 'list_node_products')
const isUpdate         = computed(() => selectedTool.value === 'update_product_attribute')
const needsHierarchy   = computed(() => isHierarchyNodes.value)
const needsNode        = computed(() => isNodeAttributes.value || isNodeProducts.value)

const responseJson = computed(() => {
  if (!response.value) return ''
  return JSON.stringify(response.value, null, 2)
})

const responseStats = computed(() => {
  if (!response.value || typeof response.value !== 'object') return null
  const d = response.value
  return {
    total: d.total ?? null,
    count: d.count ?? (Array.isArray(d.data) ? d.data.length : null),
    ms: responseMs.value,
  }
})

const mcpConfigJson = computed(() => JSON.stringify({
  mcpServers: {
    pim: {
      command: 'node',
      args: ['./mcp-server/dist/index.js'],
      env: {
        PIM_BASE_URL: window.location.origin,
        PIM_API_KEY: 'API_KEY_HIER_EINTRAGEN',
      },
    },
  },
}, null, 2))

// ── Lifecycle ──────────────────────────────────────────────────────────────

onMounted(async () => {
  loading.value = true
  try {
    const { data } = await apiTemplatesApi.list()
    templates.value = (data.data || data).filter(t => t.is_active)
    if (templates.value.length > 0) {
      selectedSlug.value = templates.value[0].slug
    }
  } catch (e) {
    error.value = 'Templates konnten nicht geladen werden.'
  } finally {
    loading.value = false
  }

  // Hierarchien für die Dropdown-Auswahl der Struktur-Tools laden (best effort)
  try {
    const { data } = await client.get('/hierarchies', { params: { per_page: 200 } })
    hierarchies.value = data.data || data || []
  } catch (e) {
    // nicht kritisch — Struktur-Tools funktionieren auch mit manueller ID-Eingabe
  }

  // Deep-Link aus dem Copilot-Debug: Tool + Parameter vorbefüllen (+ Auto-Run)
  if (route.query.tool) {
    applyQueryParams(route.query)
    if (route.query.run === '1') run()
  }
})

/** Befüllt Tool + Parameter aus den Query-Parametern (Copilot-Deep-Link). */
function applyQueryParams(q) {
  if (!q.tool) return
  selectedTool.value = q.tool
  if (q.slug)         selectedSlug.value     = q.slug
  if (q.limit)        paramLimit.value       = String(q.limit)
  if (q.status)       searchStatus.value     = q.status
  if (q.search)       paramSearch.value      = q.search
  if (q.data_type)    paramDataType.value    = q.data_type
  if (q.type)         paramNodeType.value    = q.type
  if (q.hierarchy_id) paramHierarchyId.value = q.hierarchy_id
  if (q.node_id)      paramNodeId.value      = q.node_id
  if (q.product)      updProduct.value       = q.product
  if (q.attribute)    updAttribute.value     = q.attribute
  if (q.value !== undefined)  updValue.value = String(q.value)
  if (q.unit)         updUnit.value          = q.unit
  if (q.language)     updLanguage.value      = q.language
  if (q.query !== undefined) {
    if (q.tool === 'search_products') searchQuery.value = q.query
    else graphqlQuery.value = q.query
  }
}

// ── Run ────────────────────────────────────────────────────────────────────

async function run() {
  running.value = true
  error.value = ''
  response.value = null
  responseMs.value = null
  const t0 = performance.now()

  try {
    let result

    if (selectedTool.value === 'list_templates') {
      const { data } = await apiTemplatesApi.list()
      result = data

    } else if (selectedTool.value === 'get_schema') {
      const tpl = templates.value.find(t => t.slug === selectedSlug.value)
      if (!tpl) throw new Error('Template nicht gefunden')
      const { data } = await apiTemplatesApi.schemaPreview(tpl.id)
      result = data

    } else if (selectedTool.value === 'stream_products') {
      const params = {}
      if (paramLimit.value) {
        const limit = Number(paramLimit.value)
        if (!Number.isInteger(limit) || limit < 1)
          throw new Error('Limit muss eine positive ganze Zahl sein')
        params.limit = limit
      }
      if (paramOffset.value) {
        const offset = Number(paramOffset.value)
        if (!Number.isInteger(offset) || offset < 0)
          throw new Error('Offset muss ≥ 0 sein')
        params.offset = offset
      }
      if (paramSince.value) {
        const date = new Date(paramSince.value)
        if (isNaN(date.getTime()))
          throw new Error('Ungültiges Datum für "since"')
        params.since = date.toISOString()
      }
      if (paramSort.value)   params.sort   = paramSort.value
      if (paramOrder.value !== 'asc') params.order = paramOrder.value
      if (paramFields.value) params.fields = paramFields.value
      if (paramLang.value)   params.lang   = paramLang.value
      const { data } = await client.get(`/api-streams/${selectedSlug.value}`, { params })
      result = data

    } else if (selectedTool.value === 'search_products') {
      const args = { slug: selectedSlug.value }
      if (searchQuery.value)  args.query  = searchQuery.value
      if (paramLimit.value)   args.limit  = Number(paramLimit.value)
      if (searchStatus.value) args.status = searchStatus.value
      const { data } = await client.post('/mcp-test-call', { tool: 'search_products', arguments: args })
      if (data?.is_error) throw new Error(data.error || 'Suche fehlgeschlagen')
      result = data.result ?? data

    } else if (selectedTool.value === 'graphql_query') {
      let vars = undefined
      if (graphqlVars.value.trim()) {
        try { vars = JSON.parse(graphqlVars.value) }
        catch { throw new Error('Variables: Ungültiges JSON') }
      }
      const { data } = await client.post(`/api-streams/${selectedSlug.value}`, {
        query: graphqlQuery.value,
        variables: vars,
      })
      result = data

    } else if (selectedTool.value === 'graphql_mutate') {
      let vars = undefined
      if (graphqlVars.value.trim()) {
        try { vars = JSON.parse(graphqlVars.value) }
        catch { throw new Error('Variables: Ungültiges JSON') }
      }
      const { data } = await client.post(`/api-streams/${selectedSlug.value}/import`, {
        query: graphqlQuery.value,
        variables: vars,
      })
      result = data

    } else if (selectedTool.value === 'list_attributes') {
      const params = { per_page: paramLimit.value ? Number(paramLimit.value) : 100 }
      if (paramSearch.value)   params.search = paramSearch.value
      if (paramDataType.value) params['filter[data_type]'] = paramDataType.value
      const { data } = await client.get('/attributes', { params })
      result = data

    } else if (selectedTool.value === 'list_hierarchies') {
      const params = {}
      if (paramNodeType.value && paramNodeType.value !== 'both') params['filter[hierarchy_type]'] = paramNodeType.value
      const { data } = await client.get('/hierarchies', { params })
      result = data

    } else if (selectedTool.value === 'list_hierarchy_nodes') {
      if (!paramHierarchyId.value) throw new Error('Bitte eine Hierarchie wählen')
      const params = { per_page: paramLimit.value ? Number(paramLimit.value) : 200 }
      if (paramSearch.value) params.search = paramSearch.value
      const { data } = await client.get(`/hierarchies/${paramHierarchyId.value}/nodes`, { params })
      result = data

    } else if (selectedTool.value === 'list_node_attributes') {
      if (!paramNodeId.value) throw new Error('Bitte eine node_id (Knoten-UUID) angeben')
      const { data } = await client.get(`/hierarchy-nodes/${paramNodeId.value}/attributes`)
      result = data

    } else if (selectedTool.value === 'list_node_products') {
      if (!paramNodeId.value) throw new Error('Bitte eine node_id (Knoten-UUID) angeben')
      const params = { type: paramNodeType.value || 'both' }
      if (paramLimit.value) params.per_page = Number(paramLimit.value)
      const { data } = await client.get(`/hierarchy-nodes/${paramNodeId.value}/products`, { params })
      result = data

    } else if (selectedTool.value === 'update_product_attribute') {
      if (!updProduct.value || !updAttribute.value) throw new Error('product und attribute sind erforderlich')
      const input = {
        product: updProduct.value,
        attribute: updAttribute.value,
        value: updValue.value,
      }
      if (updUnit.value)     input.unit = updUnit.value
      if (updLanguage.value) input.language = updLanguage.value
      const { data } = await client.post('/copilot/execute-tool', {
        name: 'update_product_attribute',
        input,
      })
      if (data?.is_error) throw new Error(data.content || 'Aktion fehlgeschlagen')
      result = typeof data?.content === 'string'
        ? (() => { try { return JSON.parse(data.content) } catch { return data.content } })()
        : data
    }

    responseMs.value = Math.round(performance.now() - t0)
    response.value = result
  } catch (e) {
    error.value = e.response?.data?.error || e.response?.data?.message || e.response?.data?.errors?.[0]?.message || e.message
  } finally {
    running.value = false
  }
}

function applyExample(ex) {
  ex.apply()
  // Slug auf passendes Template setzen
  const isGql = ['graphql_query', 'graphql_mutate', 'get_schema'].includes(selectedTool.value)
  const pool = isGql ? graphqlTemplates.value : jsonTemplates.value
  if (pool.length > 0 && !pool.find(t => t.slug === selectedSlug.value)) {
    selectedSlug.value = pool[0].slug
  }
}

function copyResponse() {
  navigator.clipboard.writeText(responseJson.value)
  copied.value = true
  setTimeout(() => { copied.value = false }, 2000)
}
</script>

<template>
  <div class="space-y-4 max-w-5xl">

    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-base font-semibold text-[var(--color-text-primary)]">MCP Playground</h1>
        <p class="text-xs text-[var(--color-text-tertiary)] mt-0.5">
          Teste die MCP-Tools direkt im Browser — dieselben Endpoints die Claude über den MCP-Server aufruft.
        </p>
      </div>
      <div class="flex items-center gap-1.5 text-[10px] text-[var(--color-text-tertiary)]">
        <RefreshCw v-if="loading" class="w-3 h-3 animate-spin" :stroke-width="2" />
        <template v-else>
          <div class="w-1.5 h-1.5 rounded-full bg-green-500"></div>
          {{ templates.length }} aktive Templates
        </template>
      </div>
    </div>

    <!-- Use-Case Banner -->
    <div class="border border-[var(--color-border)] rounded-lg overflow-hidden">
      <button
        class="w-full flex items-center justify-between px-4 py-3 bg-[var(--color-surface)] hover:bg-[var(--color-bg)] text-left transition-colors"
        @click="showUseCases = !showUseCases"
      >
        <div class="flex items-center gap-2">
          <Sparkles class="w-4 h-4 text-[var(--color-accent)]" :stroke-width="2" />
          <span class="text-xs font-semibold text-[var(--color-text-primary)]">Was kann Claude mit MCP-Zugriff auf das PIM?</span>
        </div>
        <ChevronDown v-if="showUseCases" class="w-3.5 h-3.5 text-[var(--color-text-tertiary)]" />
        <ChevronRight v-else class="w-3.5 h-3.5 text-[var(--color-text-tertiary)]" />
      </button>
      <div v-if="showUseCases" class="px-4 pb-4 pt-1 bg-[var(--color-surface)] grid grid-cols-3 gap-4">
        <div v-for="uc in examples" :key="uc.category">
          <div class="flex items-center gap-1.5 mb-2">
            <component :is="uc.icon" class="w-3.5 h-3.5" :class="uc.color" :stroke-width="2" />
            <span class="text-[11px] font-semibold text-[var(--color-text-secondary)]">{{ uc.category }}</span>
          </div>
          <div class="space-y-1">
            <button
              v-for="item in uc.items"
              :key="item.label"
              class="w-full text-left text-[11px] px-2 py-1 rounded text-[var(--color-text-secondary)] hover:bg-[var(--color-accent)]/10 hover:text-[var(--color-accent)] transition-colors"
              @click="applyExample(item)"
            >
              {{ item.label }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4">

      <!-- Left: Input Panel -->
      <div class="space-y-3">

        <!-- Tool auswählen -->
        <div>
          <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Tool</label>
          <div class="grid grid-cols-1 gap-1">
            <button
              v-for="tool in tools"
              :key="tool.id"
              class="flex items-center gap-2 px-3 py-2 rounded-md text-left text-xs border transition-colors"
              :class="selectedTool === tool.id
                ? 'bg-[var(--color-accent)]/10 border-[var(--color-accent)]/30 text-[var(--color-accent)]'
                : 'bg-[var(--color-surface)] border-[var(--color-border)] text-[var(--color-text-secondary)] hover:border-[var(--color-border-strong)]'"
              @click="selectedTool = tool.id"
            >
              <span class="text-base leading-none">{{ tool.icon }}</span>
              <div>
                <div class="font-mono font-medium">{{ tool.label }}</div>
                <div class="text-[10px] opacity-70 mt-0.5">{{ tool.desc }}</div>
              </div>
            </button>
          </div>
        </div>

        <!-- Template / Slug -->
        <div v-if="needsSlug">
          <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Template</label>
          <select v-model="selectedSlug" class="pim-input text-xs w-full">
            <optgroup v-if="jsonTemplates.length" label="JSON">
              <option v-for="t in jsonTemplates" :key="t.id" :value="t.slug">{{ t.name }} ({{ t.slug }})</option>
            </optgroup>
            <optgroup v-if="graphqlTemplates.length" label="GraphQL">
              <option v-for="t in graphqlTemplates" :key="t.id" :value="t.slug">{{ t.name }} ({{ t.slug }})</option>
            </optgroup>
          </select>
        </div>

        <!-- search_products Parameter -->
        <template v-if="isSearch">
          <div>
            <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-1">Suchbegriff (query)</label>
            <input v-model="searchQuery" type="text" placeholder="z.B. cryo" class="pim-input text-xs w-full" />
          </div>
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-1">Limit</label>
              <input v-model="paramLimit" type="number" min="1" max="200" placeholder="20" class="pim-input text-xs w-full" />
            </div>
            <div>
              <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-1">Status</label>
              <select v-model="searchStatus" class="pim-input text-xs w-full">
                <option value="">active (Standard)</option>
                <option value="active">active</option>
                <option value="draft">draft</option>
                <option value="inactive">inactive</option>
                <option value="discontinued">discontinued</option>
              </select>
            </div>
          </div>
        </template>

        <!-- stream_products Parameter -->
        <template v-if="isStream">
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-1">Limit</label>
              <input v-model="paramLimit" type="number" min="1" placeholder="alle" class="pim-input text-xs w-full" />
            </div>
            <div>
              <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-1">Offset</label>
              <input v-model="paramOffset" type="number" min="0" placeholder="0" class="pim-input text-xs w-full" />
            </div>
          </div>
          <div>
            <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-1">Delta-Sync (since)</label>
            <input v-model="paramSince" type="datetime-local" class="pim-input text-xs w-full" />
          </div>
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-1">Sort</label>
              <select v-model="paramSort" class="pim-input text-xs w-full">
                <option value="">Standard</option>
                <option value="sku">SKU</option>
                <option value="name">Name</option>
                <option value="updated_at">Geändert am</option>
                <option value="created_at">Erstellt am</option>
              </select>
            </div>
            <div>
              <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-1">Sprache</label>
              <select v-model="paramLang" class="pim-input text-xs w-full">
                <option value="">Template-Standard</option>
                <option value="de">DE</option>
                <option value="en">EN</option>
                <option value="fr">FR</option>
              </select>
            </div>
          </div>
          <div>
            <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-1">Felder (sparse)</label>
            <input v-model="paramFields" type="text" placeholder="sku,name,status" class="pim-input text-xs w-full font-mono" />
          </div>
        </template>

        <!-- GraphQL Parameter -->
        <template v-if="isGraphql">
          <div>
            <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-1">Query / Mutation</label>
            <textarea
              v-model="graphqlQuery"
              rows="5"
              class="pim-input text-xs w-full font-mono resize-none"
              spellcheck="false"
            />
          </div>
          <div>
            <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-1">Variables (JSON, optional)</label>
            <textarea
              v-model="graphqlVars"
              rows="2"
              placeholder="{}"
              class="pim-input text-xs w-full font-mono resize-none"
              spellcheck="false"
            />
          </div>
        </template>

        <!-- list_attributes -->
        <template v-if="isAttributes">
          <div>
            <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-1">Suche (technical_name / Name)</label>
            <input v-model="paramSearch" type="text" placeholder="z.B. gewicht" class="pim-input text-xs w-full" />
          </div>
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-1">Datentyp</label>
              <select v-model="paramDataType" class="pim-input text-xs w-full">
                <option value="">Alle</option>
                <option value="String">String</option>
                <option value="Number">Number</option>
                <option value="Float">Float</option>
                <option value="Date">Date</option>
                <option value="Flag">Flag</option>
                <option value="Selection">Selection</option>
              </select>
            </div>
            <div>
              <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-1">Limit</label>
              <input v-model="paramLimit" type="number" min="1" placeholder="100" class="pim-input text-xs w-full" />
            </div>
          </div>
        </template>

        <!-- list_hierarchies -->
        <template v-if="isHierarchies">
          <div>
            <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-1">Typ</label>
            <select v-model="paramNodeType" class="pim-input text-xs w-full">
              <option value="both">Alle</option>
              <option value="master">master</option>
              <option value="output">output</option>
            </select>
          </div>
        </template>

        <!-- list_hierarchy_nodes -->
        <template v-if="isHierarchyNodes">
          <div>
            <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Hierarchie</label>
            <select v-model="paramHierarchyId" class="pim-input text-xs w-full">
              <option value="">— wählen —</option>
              <option v-for="h in hierarchies" :key="h.id" :value="h.id">
                {{ h.name_de || h.technical_name }} ({{ h.hierarchy_type }})
              </option>
            </select>
          </div>
          <div>
            <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-1">Suche (optional)</label>
            <input v-model="paramSearch" type="text" placeholder="Knotenname" class="pim-input text-xs w-full" />
          </div>
        </template>

        <!-- list_node_attributes / list_node_products -->
        <template v-if="needsNode">
          <div>
            <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Knoten-UUID (node_id)</label>
            <input v-model="paramNodeId" type="text" placeholder="UUID aus list_hierarchy_nodes" class="pim-input text-xs w-full font-mono" />
          </div>
          <div v-if="isNodeProducts">
            <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-1">Zuordnung</label>
            <select v-model="paramNodeType" class="pim-input text-xs w-full">
              <option value="both">master + output</option>
              <option value="master">master</option>
              <option value="output">output</option>
            </select>
          </div>
        </template>

        <!-- update_product_attribute (schreibend) -->
        <template v-if="isUpdate">
          <div class="rounded-md border border-amber-300 bg-amber-50 dark:bg-amber-900/20 p-2 text-[10px] text-amber-700 dark:text-amber-400">
            Schreibende Aktion — ändert echte Produktdaten und wird ins Audit-Journal geschrieben.
          </div>
          <div>
            <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-1">Produkt (UUID oder SKU)</label>
            <input v-model="updProduct" type="text" placeholder="z.B. 10001" class="pim-input text-xs w-full" />
          </div>
          <div>
            <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-1">Attribut (UUID oder technical_name)</label>
            <input v-model="updAttribute" type="text" placeholder="z.B. gewicht-netto" class="pim-input text-xs w-full" />
          </div>
          <div class="grid grid-cols-3 gap-2">
            <div class="col-span-2">
              <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-1">Wert</label>
              <input v-model="updValue" type="text" placeholder="500" class="pim-input text-xs w-full" />
            </div>
            <div>
              <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-1">Einheit</label>
              <input v-model="updUnit" type="text" placeholder="g" class="pim-input text-xs w-full" />
            </div>
          </div>
          <div>
            <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-1">Sprache (bei übersetzbar)</label>
            <input v-model="updLanguage" type="text" placeholder="de" class="pim-input text-xs w-full" />
          </div>
        </template>

        <!-- Run Button -->
        <button
          class="pim-btn pim-btn-primary w-full text-xs"
          :disabled="running || (needsSlug && !selectedSlug) || (needsHierarchy && !paramHierarchyId) || (needsNode && !paramNodeId) || (isUpdate && (!updProduct || !updAttribute))"
          @click="run"
        >
          <RefreshCw v-if="running" class="w-3.5 h-3.5 animate-spin" :stroke-width="2" />
          <Play v-else class="w-3.5 h-3.5" :stroke-width="2" />
          {{ running ? 'Wird ausgeführt...' : 'Tool ausführen' }}
        </button>
      </div>

      <!-- Right: Response Panel -->
      <div class="flex flex-col gap-2">
        <div class="flex items-center justify-between">
          <label class="text-[11px] font-medium text-[var(--color-text-secondary)]">Response</label>
          <div class="flex items-center gap-2">
            <span v-if="responseStats" class="text-[10px] text-[var(--color-text-tertiary)]">
              <template v-if="responseStats.total !== null">{{ responseStats.total }} total</template>
              <template v-if="responseStats.count !== null"> · {{ responseStats.count }} zurück</template>
              · {{ responseStats.ms }}ms
            </span>
            <button v-if="response" @click="copyResponse" class="text-[var(--color-text-tertiary)] hover:text-[var(--color-text-primary)]">
              <Check v-if="copied" class="w-3.5 h-3.5 text-green-500" :stroke-width="2" />
              <Copy v-else class="w-3.5 h-3.5" :stroke-width="2" />
            </button>
          </div>
        </div>

        <!-- Error -->
        <div v-if="error" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md p-3">
          <p class="text-xs text-red-600 dark:text-red-400 font-mono">{{ error }}</p>
        </div>

        <!-- Empty state -->
        <div
          v-else-if="!response"
          class="flex-1 flex flex-col items-center justify-center bg-[var(--color-bg)] rounded-lg border border-dashed border-[var(--color-border)] min-h-[300px] text-center p-6"
        >
          <Play class="w-8 h-8 text-[var(--color-text-tertiary)] mb-3" :stroke-width="1.5" />
          <p class="text-xs text-[var(--color-text-tertiary)]">Tool auswählen und ausführen</p>
          <p class="text-[10px] text-[var(--color-text-tertiary)] mt-1 opacity-60">
            Oder ein Beispiel oben auswählen
          </p>
        </div>

        <!-- JSON Response -->
        <pre
          v-else
          class="flex-1 text-[10px] font-mono bg-[var(--color-bg)] border border-[var(--color-border)] rounded-lg p-3 overflow-auto min-h-[300px] max-h-[600px] text-[var(--color-text-secondary)] leading-relaxed whitespace-pre-wrap break-all"
        >{{ responseJson }}</pre>
      </div>
    </div>

    <!-- MCP Config Hint -->
    <div class="border border-[var(--color-border)] rounded-lg p-4 bg-[var(--color-surface)]">
      <p class="text-[11px] font-semibold text-[var(--color-text-secondary)] mb-2">MCP-Konfiguration für Claude Code / Claude Desktop</p>
      <pre class="text-[10px] font-mono bg-[var(--color-bg)] p-3 rounded text-[var(--color-text-secondary)] overflow-x-auto">{{ mcpConfigJson }}</pre>
      <p class="text-[10px] text-[var(--color-text-tertiary)] mt-2">
        Nach dem Eintragen in <code class="font-mono">.mcp.json</code> kann Claude direkt auf alle aktiven Templates zugreifen —
        Datenschema, Datenqualität und Auswertungen ohne manuellen Export.
      </p>
    </div>

  </div>
</template>
