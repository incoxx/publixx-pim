<script setup>
import { ref, computed, onMounted } from 'vue'
import client from '@/api/client'
import { Send, Copy, ChevronDown, ChevronRight, Trash2 } from 'lucide-vue-next'

const routes = ref([])
const loadingRoutes = ref(false)
const search = ref('')

// Request state
const method = ref('GET')
const url = ref('/api/v1/')
const requestBody = ref('')
const requestHeaders = ref([])

// Response state
const response = ref(null)
const responseTime = ref(0)
const sending = ref(false)
const showRoutes = ref(true)

// History
const history = ref([])

const methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']

const methodColors = {
  GET: 'bg-green-100 text-green-700',
  POST: 'bg-blue-100 text-blue-700',
  PUT: 'bg-amber-100 text-amber-700',
  PATCH: 'bg-orange-100 text-orange-700',
  DELETE: 'bg-red-100 text-red-700',
}

const filteredRoutes = computed(() => {
  if (!search.value) return routes.value
  const q = search.value.toLowerCase()
  return routes.value.filter(r =>
    r.uri.toLowerCase().includes(q) || r.methods.some(m => m.toLowerCase().includes(q))
  )
})

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
  url.value = route.uri
  if (['POST', 'PUT', 'PATCH'].includes(m) && !requestBody.value) {
    requestBody.value = '{}'
  }
}

async function sendRequest() {
  sending.value = true
  response.value = null
  const start = performance.now()

  try {
    const config = {
      method: method.value.toLowerCase(),
      url: url.value,
      baseURL: '', // use absolute URL
      validateStatus: () => true, // don't throw on 4xx/5xx
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
      url: url.value,
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
  url.value = item.url
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
            <input
              v-model="search"
              type="text"
              class="pim-input text-xs w-full mb-2"
              placeholder="Route suchen..."
            />
            <div class="max-h-[400px] overflow-y-auto space-y-0.5">
              <div v-if="loadingRoutes" class="text-xs text-[var(--color-text-tertiary)]">Laden...</div>
              <div
                v-for="(route, i) in filteredRoutes"
                :key="i"
                class="text-[11px] leading-tight"
              >
                <div class="flex flex-wrap items-center gap-1 py-1 px-1 rounded hover:bg-[var(--color-bg)] cursor-pointer group">
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
        <!-- Request bar -->
        <div class="pim-card">
          <div class="flex gap-2">
            <select v-model="method" class="pim-input text-xs font-mono font-bold w-[100px]">
              <option v-for="m in methods" :key="m" :value="m">{{ m }}</option>
            </select>
            <input
              v-model="url"
              type="text"
              class="pim-input text-xs font-mono flex-1"
              placeholder="/api/v1/..."
              @keydown.enter="sendRequest"
            />
            <button
              class="pim-btn pim-btn-primary text-xs px-4 flex items-center gap-1.5"
              :disabled="sending"
              @click="sendRequest"
            >
              <Send class="w-3.5 h-3.5" :stroke-width="2" />
              {{ sending ? 'Sende...' : 'Senden' }}
            </button>
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
            <button class="text-xs text-[var(--color-accent)] hover:underline" @click="addHeader">
              + Header
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
      </div>
    </div>
  </div>
</template>
