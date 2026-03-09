<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import client from '@/api/client'
import { Database, Search, ChevronLeft, ChevronRight, ArrowUpDown, ArrowUp, ArrowDown, Copy } from 'lucide-vue-next'

// Tables
const tables = ref([])
const loadingTables = ref(false)
const tableSearch = ref('')

// Selected table
const selectedTable = ref(null)
const columns = ref([])
const rows = ref([])
const loadingRows = ref(false)
const meta = ref({ total: 0, page: 1, per_page: 50, last_page: 1 })
const rowSearch = ref('')
const sortBy = ref('')
const sortDir = ref('asc')

const filteredTables = computed(() => {
  if (!tableSearch.value) return tables.value
  const q = tableSearch.value.toLowerCase()
  return tables.value.filter(t => t.name.toLowerCase().includes(q))
})

const totalRows = computed(() => tables.value.reduce((sum, t) => sum + t.rows, 0))

onMounted(fetchTables)

async function fetchTables() {
  loadingTables.value = true
  try {
    const { data } = await client.get('/admin/db/tables')
    tables.value = data.data || data
  } catch { /* ignore */ }
  finally { loadingTables.value = false }
}

async function selectTable(table) {
  selectedTable.value = table.name
  sortBy.value = ''
  sortDir.value = 'asc'
  rowSearch.value = ''
  meta.value.page = 1

  // Fetch columns
  try {
    const { data } = await client.get(`/admin/db/tables/${table.name}/columns`)
    columns.value = data.data || data
  } catch { columns.value = [] }

  await fetchRows()
}

async function fetchRows() {
  if (!selectedTable.value) return
  loadingRows.value = true
  try {
    const params = {
      page: meta.value.page,
      per_page: meta.value.per_page,
    }
    if (sortBy.value) {
      params.sort = sortBy.value
      params.order = sortDir.value
    }
    if (rowSearch.value) params.search = rowSearch.value

    const { data } = await client.get(`/admin/db/tables/${selectedTable.value}/rows`, { params })
    rows.value = data.data || []
    if (data.meta) meta.value = data.meta
  } catch { rows.value = [] }
  finally { loadingRows.value = false }
}

function toggleSort(col) {
  if (sortBy.value === col) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortBy.value = col
    sortDir.value = 'asc'
  }
  meta.value.page = 1
  fetchRows()
}

function goPage(p) {
  meta.value.page = p
  fetchRows()
}

let searchTimeout = null
function onRowSearch() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    meta.value.page = 1
    fetchRows()
  }, 400)
}

function formatValue(val) {
  if (val === null) return 'NULL'
  if (typeof val === 'boolean') return val ? 'true' : 'false'
  if (typeof val === 'object') return JSON.stringify(val)
  const s = String(val)
  return s.length > 120 ? s.substring(0, 120) + '…' : s
}

function isNull(val) {
  return val === null
}

function copyCell(val) {
  const text = val === null ? 'NULL' : typeof val === 'object' ? JSON.stringify(val) : String(val)
  navigator.clipboard.writeText(text)
}

function backToList() {
  selectedTable.value = null
  columns.value = []
  rows.value = []
}
</script>

<template>
  <div class="space-y-4">
    <h2 class="text-lg font-semibold text-[var(--color-text-primary)] flex items-center gap-2">
      <Database class="w-5 h-5" />
      Datenbank
    </h2>

    <!-- Table list -->
    <div v-if="!selectedTable" class="space-y-3">
      <div class="flex items-center gap-3">
        <div class="relative flex-1 max-w-md">
          <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[var(--color-text-tertiary)]" />
          <input
            v-model="tableSearch"
            type="text"
            class="pim-input text-xs w-full pl-9"
            placeholder="Tabelle suchen..."
          />
        </div>
        <span class="text-xs text-[var(--color-text-tertiary)]">
          {{ tables.length }} Tabellen, {{ totalRows.toLocaleString() }} Zeilen gesamt
        </span>
      </div>

      <div v-if="loadingTables" class="space-y-2">
        <div v-for="i in 8" :key="i" class="pim-skeleton h-8 rounded" />
      </div>

      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-2">
        <button
          v-for="t in filteredTables"
          :key="t.name"
          class="flex items-center justify-between px-3 py-2.5 rounded-lg border border-[var(--color-border)] hover:border-[var(--color-accent)] hover:bg-[color-mix(in_srgb,var(--color-accent)_5%,transparent)] transition-colors text-left cursor-pointer group"
          @click="selectTable(t)"
        >
          <span class="font-mono text-xs text-[var(--color-text-primary)] truncate group-hover:text-[var(--color-accent)]">
            {{ t.name }}
          </span>
          <span class="text-[11px] text-[var(--color-text-tertiary)] tabular-nums shrink-0 ml-2">
            {{ t.rows.toLocaleString() }}
          </span>
        </button>
      </div>
    </div>

    <!-- Table detail -->
    <div v-else class="space-y-3">
      <!-- Header -->
      <div class="flex items-center gap-3">
        <button
          class="pim-btn pim-btn-secondary text-xs px-2 py-1"
          @click="backToList"
        >
          <ChevronLeft class="w-3.5 h-3.5" /> Zurück
        </button>
        <h3 class="font-mono text-sm font-semibold text-[var(--color-accent)]">{{ selectedTable }}</h3>
        <span class="text-xs text-[var(--color-text-tertiary)]">
          {{ meta.total.toLocaleString() }} Zeilen, {{ columns.length }} Spalten
        </span>
      </div>

      <!-- Search -->
      <div class="relative max-w-md">
        <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[var(--color-text-tertiary)]" />
        <input
          v-model="rowSearch"
          type="text"
          class="pim-input text-xs w-full pl-9"
          placeholder="In Textfeldern suchen..."
          @input="onRowSearch"
        />
      </div>

      <!-- Data table -->
      <div class="border border-[var(--color-border)] rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-xs">
            <thead>
              <tr class="bg-[var(--color-bg)]">
                <th
                  v-for="col in columns"
                  :key="col.name"
                  class="px-3 py-2 text-left font-semibold text-[var(--color-text-secondary)] whitespace-nowrap cursor-pointer hover:text-[var(--color-accent)] select-none border-b border-[var(--color-border)]"
                  @click="toggleSort(col.name)"
                >
                  <div class="flex items-center gap-1">
                    <span>{{ col.name }}</span>
                    <span class="text-[10px] text-[var(--color-text-tertiary)] font-normal">{{ col.type_name }}</span>
                    <ArrowUp v-if="sortBy === col.name && sortDir === 'asc'" class="w-3 h-3 text-[var(--color-accent)]" />
                    <ArrowDown v-else-if="sortBy === col.name && sortDir === 'desc'" class="w-3 h-3 text-[var(--color-accent)]" />
                    <ArrowUpDown v-else class="w-3 h-3 opacity-20" />
                  </div>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loadingRows">
                <td :colspan="columns.length" class="px-3 py-8 text-center text-[var(--color-text-tertiary)]">
                  Laden...
                </td>
              </tr>
              <tr v-else-if="!rows.length">
                <td :colspan="columns.length" class="px-3 py-8 text-center text-[var(--color-text-tertiary)] italic">
                  Keine Daten
                </td>
              </tr>
              <tr
                v-else
                v-for="(row, ri) in rows"
                :key="ri"
                class="border-b border-[var(--color-border)] hover:bg-[var(--color-bg)] transition-colors"
              >
                <td
                  v-for="col in columns"
                  :key="col.name"
                  class="px-3 py-1.5 font-mono text-[11px] max-w-[300px] truncate group relative"
                  :class="isNull(row[col.name]) ? 'text-[var(--color-text-tertiary)] italic' : 'text-[var(--color-text-primary)]'"
                  :title="String(row[col.name] ?? 'NULL')"
                  @dblclick="copyCell(row[col.name])"
                >
                  {{ formatValue(row[col.name]) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="meta.last_page > 1" class="flex items-center justify-between">
        <span class="text-xs text-[var(--color-text-tertiary)]">
          Seite {{ meta.page }} von {{ meta.last_page }}
          ({{ meta.total.toLocaleString() }} Zeilen)
        </span>
        <div class="flex gap-1">
          <button
            class="pim-btn pim-btn-secondary text-xs px-2 py-1"
            :disabled="meta.page <= 1"
            @click="goPage(meta.page - 1)"
          >
            <ChevronLeft class="w-3.5 h-3.5" />
          </button>
          <button
            v-for="p in Math.min(meta.last_page, 7)"
            :key="p"
            :class="[
              'pim-btn text-xs px-2.5 py-1 min-w-[32px]',
              p === meta.page ? 'pim-btn-primary' : 'pim-btn-secondary'
            ]"
            @click="goPage(p)"
          >
            {{ p }}
          </button>
          <template v-if="meta.last_page > 7">
            <span class="text-xs text-[var(--color-text-tertiary)] px-1 self-center">...</span>
            <button
              class="pim-btn pim-btn-secondary text-xs px-2.5 py-1"
              @click="goPage(meta.last_page)"
            >
              {{ meta.last_page }}
            </button>
          </template>
          <button
            class="pim-btn pim-btn-secondary text-xs px-2 py-1"
            :disabled="meta.page >= meta.last_page"
            @click="goPage(meta.page + 1)"
          >
            <ChevronRight class="w-3.5 h-3.5" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
