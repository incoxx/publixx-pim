<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import client from '@/api/client'
import { Send, Copy, ChevronDown, ChevronRight, Trash2, Plus, AlertCircle, Filter } from 'lucide-vue-next'

const routes = ref([])
const loadingRoutes = ref(false)
const search = ref('')

// Group/category filter
const activeGroup = ref(null)      // null = alle, sonst Gruppenname
const activeCategory = ref(null)   // null = alle, 'read', 'write', 'delete'
const collapsedGroups = ref(new Set())

// Request state
const method = ref('GET')
const urlTemplate = ref('')       // e.g. /api/v1/products/{product}
const urlParams = ref([])         // [{name: 'product', value: '1'}]
const queryParams = ref([])       // [{key: 'page', value: '1'}]
const requestBody = ref('')
const requestHeaders = ref([])
const selectedRouteUri = ref('')  // highlight in sidebar

// Response state
const response = ref(null)
const responseTime = ref(0)
const sending = ref(false)
const showRoutes = ref(true)

// History
const history = ref([])

const methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']

const methodColors = {
  GET: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',
  POST: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
  PUT: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
  PATCH: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300',
  DELETE: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
}

const categoryColors = {
  read: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',
  write: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
  delete: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
}

const categoryLabels = { read: 'Read', write: 'Write', delete: 'Delete' }

// Alle vorhandenen Gruppen (dynamisch aus den Routes)
const availableGroups = computed(() => {
  const groups = new Map()
  for (const r of routes.value) {
    const g = r.group || 'Sonstiges'
    groups.set(g, (groups.get(g) || 0) + 1)
  }
  return [...groups.entries()].sort((a, b) => a[0].localeCompare(b[0]))
})

// Kategorie-Counts
const categoryCounts = computed(() => {
  const counts = { read: 0, write: 0, delete: 0 }
  for (const r of filteredByGroupRoutes.value) {
    counts[r.category || 'read']++
  }
  return counts
})

// Erst nach Gruppe filtern
const filteredByGroupRoutes = computed(() => {
  let result = routes.value
  if (activeGroup.value) {
    result = result.filter(r => (r.group || 'Sonstiges') === activeGroup.value)
  }
  return result
})

// Dann nach Kategorie + Suche filtern
const filteredRoutes = computed(() => {
  let result = filteredByGroupRoutes.value
  if (activeCategory.value) {
    result = result.filter(r => r.category === activeCategory.value)
  }
  if (search.value) {
    const q = search.value.toLowerCase()
    result = result.filter(r =>
      r.uri.toLowerCase().includes(q) || r.methods.some(m => m.toLowerCase().includes(q))
    )
  }
  return result
})

// Routes nach Gruppe gruppiert
const groupedRoutes = computed(() => {
  const groups = new Map()
  for (const r of filteredRoutes.value) {
    const g = r.group || 'Sonstiges'
    if (!groups.has(g)) groups.set(g, [])
    groups.get(g).push(r)
  }
  return [...groups.entries()].sort((a, b) => a[0].localeCompare(b[0]))
})

function toggleGroup(groupName) {
  if (collapsedGroups.value.has(groupName)) {
    collapsedGroups.value.delete(groupName)
  } else {
    collapsedGroups.value.add(groupName)
  }
}

function setGroupFilter(group) {
  activeGroup.value = activeGroup.value === group ? null : group
}

function setCategoryFilter(cat) {
  activeCategory.value = activeCategory.value === cat ? null : cat
}

// The API client baseURL (e.g. '/web/api/v1' or '/api/v1')
const apiBase = (import.meta.env.VITE_API_BASE_URL || '/api/v1').replace(/\/+$/, '')

// Strip the /api/v1 prefix that the backend returns, so we can use the client's baseURL
function stripApiPrefix(uri) {
  // Routes come as "api/v1/..." or "/api/v1/..." from backend
  return uri.replace(/^\/?api\/v1\/?/, '/')
}

// Build the final URL from template + params + query (relative to client baseURL)
const resolvedUrl = computed(() => {
  let u = stripApiPrefix(urlTemplate.value)
  // Replace {param} placeholders
  for (const p of urlParams.value) {
    u = u.replace(`{${p.name}}`, encodeURIComponent(p.value || p.name))
  }
  // Also handle {param?} optional params
  u = u.replace(/\{[^}]+\?\}/g, '')
  // Append query params
  const qParts = queryParams.value
    .filter(q => q.key.trim())
    .map(q => `${encodeURIComponent(q.key)}=${encodeURIComponent(q.value)}`)
  if (qParts.length) u += '?' + qParts.join('&')
  return u
})

// Full URL for display purposes
const displayUrl = computed(() => apiBase + resolvedUrl.value)

// Check if there are unfilled URL params
const hasUnfilledParams = computed(() =>
  urlParams.value.some(p => !p.value.trim())
)

const responseStatusClass = computed(() => {
  if (!response.value) return ''
  const s = response.value.status
  if (s < 300) return 'text-green-600'
  if (s < 400) return 'text-amber-600'
  return 'text-red-600'
})

const formattedResponse = computed(() => {
  if (!response.value) return ''
  try {
    return JSON.stringify(JSON.parse(response.value.body), null, 2)
  } catch {
    return response.value.body
  }
})

// Extract {param} names from a URI
function extractParams(uri) {
  const matches = uri.matchAll(/\{(\w+)\??}/g)
  return [...matches].map(m => ({ name: m[1], value: '' }))
}

onMounted(async () => {
  loadingRoutes.value = true
  try {
    const { data } = await client.get('/admin/api-routes')
    routes.value = data.data || data
  } catch { /* ignore */ }
  finally { loadingRoutes.value = false }
})

function selectRoute(route, m) {
  method.value = m
  urlTemplate.value = route.uri
  selectedRouteUri.value = route.uri
  urlParams.value = extractParams(route.uri)
  queryParams.value = []
  response.value = null

  if (['POST', 'PUT', 'PATCH'].includes(m)) {
    requestBody.value = '{}'
  } else {
    requestBody.value = ''
  }

  // Auto-focus first param input if any
  if (urlParams.value.length) {
    nextTick(() => {
      const el = document.querySelector('[data-param-input]')
      if (el) el.focus()
    })
  }
}

async function sendRequest() {
  sending.value = true
  response.value = null
  const start = performance.now()
  const finalUrl = resolvedUrl.value

  try {
    const config = {
      method: method.value.toLowerCase(),
      url: finalUrl,
      validateStatus: () => true,
    }

    // Add body for non-GET requests
    if (['POST', 'PUT', 'PATCH'].includes(method.value) && requestBody.value.trim()) {
      try {
        config.data = JSON.parse(requestBody.value)
      } catch {
        config.data = requestBody.value
      }
    }

    // Add custom headers
    const customHeaders = {}
    for (const h of requestHeaders.value) {
      if (h.key.trim()) customHeaders[h.key] = h.value
    }
    if (Object.keys(customHeaders).length) {
      config.headers = { ...config.headers, ...customHeaders }
    }

    const res = await client(config)
    responseTime.value = Math.round(performance.now() - start)

    response.value = {
      status: res.status,
      statusText: res.statusText,
      headers: res.headers,
      body: typeof res.data === 'string' ? res.data : JSON.stringify(res.data),
    }

    // Add to history
    history.value.unshift({
      method: method.value,
      url: displayUrl.value,
      template: urlTemplate.value,
      status: res.status,
      time: responseTime.value,
      ts: new Date().toLocaleTimeString(),
    })
    if (history.value.length > 20) history.value.pop()
  } catch (e) {
    responseTime.value = Math.round(performance.now() - start)
    response.value = {
      status: 0,
      statusText: 'Network Error',
      headers: {},
      body: e.message,
    }
  } finally {
    sending.value = false
  }
}

function addQueryParam() {
  queryParams.value.push({ key: '', value: '' })
}

function removeQueryParam(idx) {
  queryParams.value.splice(idx, 1)
}

function addHeader() {
  requestHeaders.value.push({ key: '', value: '' })
}

function removeHeader(idx) {
  requestHeaders.value.splice(idx, 1)
}

function copyResponse() {
  navigator.clipboard.writeText(formattedResponse.value)
}

function loadFromHistory(item) {
  method.value = item.method
  urlTemplate.value = item.template || item.url
  urlParams.value = extractParams(urlTemplate.value)
  selectedRouteUri.value = item.template || item.url
  queryParams.value = []
  response.value = null
}
</script>

<template>
  <div class="space-y-4">
    <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">API Tester</h2>

    <div class="grid grid-cols-1 lg:grid-cols-[320px_1fr] gap-4">
      <!-- Left: Route List -->
      <div class="space-y-3">
        <!-- Route browser -->
        <div class="pim-card">
          <button
            class="w-full flex items-center justify-between text-xs font-semibold text-[var(--color-text-secondary)] mb-2"
            @click="showRoutes = !showRoutes"
          >
            <span>API Routes ({{ filteredRoutes.length }})</span>
            <component :is="showRoutes ? ChevronDown : ChevronRight" class="w-4 h-4" />
          </button>

          <template v-if="showRoutes">
            <!-- Suche -->
            <input
              v-model="search"
              type="text"
              class="pim-input text-xs w-full mb-2"
              placeholder="Route suchen..."
            />

            <!-- Kategorie-Filter (Read / Write / Delete) -->
            <div class="flex items-center gap-1 mb-2">
              <span class="text-[10px] text-[var(--color-text-tertiary)] mr-1">
                <Filter class="w-3 h-3 inline" :stroke-width="2" />
              </span>
              <button
                v-for="cat in ['read', 'write', 'delete']"
                :key="cat"
                class="px-2 py-0.5 rounded text-[10px] font-medium transition-colors"
                :class="activeCategory === cat
                  ? categoryColors[cat] + ' ring-1 ring-current'
                  : 'bg-[var(--color-bg)] text-[var(--color-text-tertiary)] hover:text-[var(--color-text-secondary)]'"
                @click="setCategoryFilter(cat)"
              >
                {{ categoryLabels[cat] }} ({{ categoryCounts[cat] }})
              </button>
              <button
                v-if="activeCategory"
                class="text-[10px] text-[var(--color-text-tertiary)] hover:text-[var(--color-text-secondary)] ml-1"
                @click="activeCategory = null"
                title="Filter zurücksetzen"
              >
                &times;
              </button>
            </div>

            <!-- Gruppen-Chips -->
            <div class="flex flex-wrap gap-1 mb-2">
              <button
                v-for="[group, count] in availableGroups"
                :key="group"
                class="px-2 py-0.5 rounded text-[10px] transition-colors"
                :class="activeGroup === group
                  ? 'bg-[var(--color-accent)] text-white'
                  : 'bg-[var(--color-bg)] text-[var(--color-text-tertiary)] hover:text-[var(--color-text-secondary)]'"
                @click="setGroupFilter(group)"
              >
                {{ group }} <span class="opacity-60">{{ count }}</span>
              </button>
            </div>

            <!-- Gruppierte Route-Liste -->
            <div class="max-h-[500px] overflow-y-auto space-y-1">
              <div v-if="loadingRoutes" class="text-xs text-[var(--color-text-tertiary)]">Laden...</div>

              <div v-for="[groupName, groupRoutes] in groupedRoutes" :key="groupName">
                <!-- Gruppen-Header -->
                <button
                  class="w-full flex items-center gap-1.5 py-1.5 px-1 text-[11px] font-semibold text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)] transition-colors"
                  @click="toggleGroup(groupName)"
                >
                  <component
                    :is="collapsedGroups.has(groupName) ? ChevronRight : ChevronDown"
                    class="w-3 h-3 shrink-0"
                    :stroke-width="2"
                  />
                  <span>{{ groupName }}</span>
                  <span class="text-[10px] text-[var(--color-text-tertiary)] font-normal">({{ groupRoutes.length }})</span>
                </button>

                <!-- Routes in Gruppe -->
                <div v-if="!collapsedGroups.has(groupName)" class="space-y-0.5 ml-3 mb-1">
                  <div
                    v-for="(route, i) in groupRoutes"
                    :key="i"
                    class="text-[11px] leading-tight"
                  >
                    <div
                      :class="[
                        'flex flex-wrap items-center gap-1 py-1 px-1 rounded cursor-pointer group transition-colors',
                        selectedRouteUri === route.uri
                          ? 'bg-[color-mix(in_srgb,var(--color-accent)_10%,transparent)] border border-[var(--color-accent)]/30'
                          : 'hover:bg-[var(--color-bg)]'
                      ]"
                    >
                      <button
                        v-for="m in route.methods"
                        :key="m"
                        :class="['px-1.5 py-0.5 rounded font-mono font-bold text-[10px]', methodColors[m] || 'bg-gray-100 text-gray-600']"
                        @click="selectRoute(route, m)"
                        :title="`${m} ${route.uri}`"
                      >
                        {{ m }}
                      </button>
                      <span
                        class="font-mono text-[var(--color-text-secondary)] truncate cursor-pointer"
                        @click="selectRoute(route, route.methods[0])"
                        :title="route.uri"
                      >
                        {{ route.uri }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>

              <div v-if="!groupedRoutes.length && !loadingRoutes" class="text-xs text-[var(--color-text-tertiary)] py-2 text-center">
                Keine Routes gefunden
              </div>
            </div>
          </template>
        </div>

        <!-- History -->
        <div v-if="history.length" class="pim-card">
          <div class="text-xs font-semibold text-[var(--color-text-secondary)] mb-2">Verlauf</div>
          <div class="space-y-1 max-h-[200px] overflow-y-auto">
            <div
              v-for="(h, i) in history"
              :key="i"
              class="flex items-center gap-2 text-[11px] py-1 px-1 rounded hover:bg-[var(--color-bg)] cursor-pointer"
              @click="loadFromHistory(h)"
            >
              <span :class="['px-1 py-0.5 rounded font-mono font-bold text-[10px]', methodColors[h.method]]">
                {{ h.method }}
              </span>
              <span class="font-mono truncate flex-1 text-[var(--color-text-secondary)]">{{ h.url }}</span>
              <span :class="h.status < 300 ? 'text-green-600' : h.status < 400 ? 'text-amber-600' : 'text-red-600'">
                {{ h.status }}
              </span>
              <span class="text-[var(--color-text-tertiary)]">{{ h.time }}ms</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Right: Request/Response -->
      <div class="space-y-3">
        <!-- Empty state -->
        <div v-if="!urlTemplate" class="pim-card flex flex-col items-center justify-center py-16 text-center">
          <Send class="w-8 h-8 text-[var(--color-text-tertiary)] mb-3" />
          <p class="text-sm text-[var(--color-text-secondary)] mb-1">Wähle eine Route aus der Liste</p>
          <p class="text-xs text-[var(--color-text-tertiary)]">Klicke auf eine API Route links, um sie zu testen</p>
        </div>

        <template v-else>
          <!-- Request bar -->
          <div class="pim-card space-y-3">
            <div class="flex gap-2">
              <select v-model="method" class="pim-input text-xs font-mono font-bold w-[100px]">
                <option v-for="m in methods" :key="m" :value="m">{{ m }}</option>
              </select>
              <div class="flex-1 relative">
                <input
                  :value="displayUrl"
                  type="text"
                  class="pim-input text-xs font-mono w-full pr-2"
                  placeholder="/api/v1/..."
                  readonly
                  @keydown.enter="sendRequest"
                />
              </div>
              <button
                class="pim-btn pim-btn-primary text-xs px-4 flex items-center gap-1.5 shrink-0"
                :disabled="sending || hasUnfilledParams"
                @click="sendRequest"
              >
                <Send class="w-3.5 h-3.5" :stroke-width="2" />
                {{ sending ? 'Sende...' : 'Senden' }}
              </button>
            </div>

            <!-- Route template info -->
            <div class="text-[11px] font-mono text-[var(--color-text-tertiary)]">
              {{ urlTemplate }}
            </div>
          </div>

          <!-- URL Parameters -->
          <div v-if="urlParams.length" class="pim-card">
            <div class="text-xs font-semibold text-[var(--color-text-secondary)] mb-2">
              URL Parameter
            </div>
            <div class="space-y-2">
              <div v-for="p in urlParams" :key="p.name" class="flex items-center gap-2">
                <span class="text-xs font-mono text-[var(--color-accent)] bg-[color-mix(in_srgb,var(--color-accent)_10%,transparent)] px-2 py-1 rounded min-w-[80px]">
                  {{ '{' + p.name + '}' }}
                </span>
                <input
                  v-model="p.value"
                  data-param-input
                  type="text"
                  class="pim-input text-xs font-mono flex-1"
                  :placeholder="`Wert für ${p.name} eingeben...`"
                  @keydown.enter="sendRequest"
                />
                <AlertCircle v-if="!p.value.trim()" class="w-4 h-4 text-amber-500 shrink-0" />
              </div>
            </div>
          </div>

          <!-- Query Parameters -->
          <div class="pim-card">
            <div class="flex items-center justify-between mb-2">
              <span class="text-xs font-semibold text-[var(--color-text-secondary)]">
                Query Parameter ({{ queryParams.length }})
              </span>
              <button class="text-xs text-[var(--color-accent)] hover:underline flex items-center gap-1" @click="addQueryParam">
                <Plus class="w-3 h-3" /> Parameter
              </button>
            </div>
            <div v-for="(q, i) in queryParams" :key="i" class="flex gap-2 mb-1.5">
              <input v-model="q.key" class="pim-input text-xs font-mono flex-1" placeholder="key" @keydown.enter="sendRequest" />
              <span class="text-[var(--color-text-tertiary)] self-center">=</span>
              <input v-model="q.value" class="pim-input text-xs font-mono flex-1" placeholder="value" @keydown.enter="sendRequest" />
              <button class="text-[var(--color-text-tertiary)] hover:text-[var(--color-error)]" @click="removeQueryParam(i)">
                <Trash2 class="w-3.5 h-3.5" />
              </button>
            </div>
            <div v-if="!queryParams.length" class="text-[11px] text-[var(--color-text-tertiary)] italic">
              z.B. page, per_page, search, filter...
            </div>
          </div>

          <!-- Request Body -->
          <div v-if="['POST', 'PUT', 'PATCH'].includes(method)" class="pim-card">
            <div class="text-xs font-semibold text-[var(--color-text-secondary)] mb-2">Request Body (JSON)</div>
            <textarea
              v-model="requestBody"
              class="pim-input text-xs font-mono w-full"
              rows="6"
              placeholder='{ "key": "value" }'
            />
          </div>

          <!-- Custom Headers -->
          <div class="pim-card">
            <div class="flex items-center justify-between mb-2">
              <span class="text-xs font-semibold text-[var(--color-text-secondary)]">
                Zusätzliche Headers ({{ requestHeaders.length }})
              </span>
              <button class="text-xs text-[var(--color-accent)] hover:underline flex items-center gap-1" @click="addHeader">
                <Plus class="w-3 h-3" /> Header
              </button>
            </div>
            <div v-for="(h, i) in requestHeaders" :key="i" class="flex gap-2 mb-1">
              <input v-model="h.key" class="pim-input text-xs font-mono flex-1" placeholder="Header-Name" />
              <input v-model="h.value" class="pim-input text-xs font-mono flex-1" placeholder="Wert" />
              <button class="text-[var(--color-text-tertiary)] hover:text-[var(--color-error)]" @click="removeHeader(i)">
                <Trash2 class="w-3.5 h-3.5" />
              </button>
            </div>
            <div v-if="!requestHeaders.length" class="text-[11px] text-[var(--color-text-tertiary)] italic">
              Authorization und Content-Type werden automatisch gesetzt.
            </div>
          </div>

          <!-- Response -->
          <div v-if="response" class="pim-card">
            <div class="flex items-center justify-between mb-2">
              <div class="flex items-center gap-3">
                <span class="text-xs font-semibold text-[var(--color-text-secondary)]">Response</span>
                <span :class="['text-xs font-bold font-mono', responseStatusClass]">
                  {{ response.status }} {{ response.statusText }}
                </span>
                <span class="text-[11px] text-[var(--color-text-tertiary)]">{{ responseTime }}ms</span>
              </div>
              <button
                class="text-xs text-[var(--color-accent)] hover:underline flex items-center gap-1"
                @click="copyResponse"
                title="Response kopieren"
              >
                <Copy class="w-3 h-3" /> Kopieren
              </button>
            </div>
            <pre class="text-[11px] font-mono bg-[var(--color-bg)] rounded-lg p-3 overflow-auto max-h-[500px] whitespace-pre-wrap break-all text-[var(--color-text-primary)]">{{ formattedResponse }}</pre>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>
