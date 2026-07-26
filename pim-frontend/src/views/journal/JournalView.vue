<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue'
import { Download, Trash2, ChevronDown, ChevronRight, History } from 'lucide-vue-next'
import { useJournalStore } from '@/stores/journal'
import { useAuthStore } from '@/stores/auth'
import usersApi from '@/api/users'
import PimTable from '@/components/shared/PimTable.vue'
import PimDeleteConfirmDialog from '@/components/shared/PimDeleteConfirmDialog.vue'

const store = useJournalStore()
const authStore = useAuthStore()

const usersList = ref([])
const showDeleteDialog = ref(false)
const deleting = ref(false)
const expandedRows = ref(new Set())

const columns = [
  { key: 'created_at', label: 'Datum', sortable: true },
  { key: 'user_name', label: 'Benutzer' },
  { key: 'action', label: 'Aktion', sortable: true },
  { key: 'auditable_type', label: 'Typ' },
  { key: 'auditable_id', label: 'Objekt-ID', mono: true },
  { key: 'details', label: 'Details' },
]

// ─── Filters ─────────────────────────────────────────
const filterAction = ref('')
const filterType = ref('')
const filterUserId = ref('')
const filterDateFrom = ref('')
const filterDateTo = ref('')
const searchQuery = ref('')

// Objekttypen (auditable_type) für den Typ-Filter. Die Werte müssen den in
// audit_logs gespeicherten Typen entsprechen (Kurzname für PIM-Objekte,
// FQCN für Benutzer).
const typeLabels = {
  Product: 'Produkt',
  HierarchyNode: 'Hierarchie',
  Attribute: 'Attribut',
  'App\\Models\\User': 'Benutzer',
}

let searchDebounce = null
function onSearchInput(val) {
  clearTimeout(searchDebounce)
  searchDebounce = setTimeout(() => {
    store.setSearch(val)
    store.setPage(1)
    store.fetchList()
  }, 400)
}
onUnmounted(() => clearTimeout(searchDebounce))

function applyFilters() {
  const f = {}
  if (filterAction.value) f.action = filterAction.value
  if (filterType.value) f.auditable_type = filterType.value
  if (filterUserId.value) f.user_id = filterUserId.value
  if (filterDateFrom.value) f.date_from = filterDateFrom.value
  if (filterDateTo.value) f.date_to = filterDateTo.value
  store.setFilters(f)
  store.setPage(1)
  store.fetchList()
}

watch([filterAction, filterType, filterUserId, filterDateFrom, filterDateTo], applyFilters)

// ─── Actions ─────────────────────────────────────────
const actionLabels = {
  created: 'Erstellt',
  updated: 'Aktualisiert',
  deleted: 'Gelöscht',
  attributes_changed: 'Attribute geändert',
  relation_added: 'Beziehung hinzugefügt',
  relation_removed: 'Beziehung entfernt',
  relation_attributes_updated: 'Beziehungs-Attribute geändert',
  relation_attribute_removed: 'Beziehungs-Attribut entfernt',
  media_assigned: 'Medium zugeordnet',
  media_removed: 'Medium entfernt',
  price_added: 'Preis hinzugefügt',
  price_updated: 'Preis aktualisiert',
  price_removed: 'Preis entfernt',
}

const actionColors = {
  created: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
  updated: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
  deleted: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
  attributes_changed: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
  relation_added: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
  relation_removed: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
  relation_attributes_updated: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
  relation_attribute_removed: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
  media_assigned: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
  media_removed: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
  price_added: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
  price_updated: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
  price_removed: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
}

function toggleExpand(id) {
  if (expandedRows.value.has(id)) {
    expandedRows.value.delete(id)
  } else {
    expandedRows.value.add(id)
  }
}

function formatValues(values) {
  if (!values) return '—'
  return Object.entries(values)
    .map(([k, v]) => `${k}: ${v ?? '—'}`)
    .join(', ')
}

function truncate(str, max = 60) {
  if (!str || str.length <= max) return str
  return str.slice(0, max) + '...'
}

async function handleExport() {
  await store.exportLogs()
}

async function confirmDelete() {
  deleting.value = true
  try {
    await store.deleteAll()
    showDeleteDialog.value = false
  } finally {
    deleting.value = false
  }
}

function handleSort({ field, order }) {
  store.setSort(field, order)
  store.fetchList()
}

function goPage(page) {
  store.setPage(page)
  store.fetchList()
}

onMounted(async () => {
  store.fetchList()
  try {
    const { data } = await usersApi.list()
    usersList.value = data.data || data
  } catch { /* might not have permission */ }
})
</script>

<template>
  <div class="space-y-4">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Änderungsprotokoll</h2>
      <div class="flex items-center gap-2">
        <button class="pim-btn pim-btn-ghost" @click="handleExport">
          <Download class="w-4 h-4" :stroke-width="2" /> Export
        </button>
        <button class="pim-btn pim-btn-danger" @click="showDeleteDialog = true">
          <Trash2 class="w-4 h-4" :stroke-width="2" /> Alle löschen
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-end gap-3 p-3 rounded-lg bg-[var(--color-surface)] border border-[var(--color-border)]">
      <div class="flex flex-col gap-1">
        <label class="text-[11px] font-medium text-[var(--color-text-tertiary)] uppercase tracking-wider">Suche (SKU/Name)</label>
        <input
          type="text"
          class="pim-input w-48"
          placeholder="SKU oder Name..."
          :value="searchQuery"
          @input="searchQuery = $event.target.value; onSearchInput($event.target.value)"
        />
      </div>
      <div class="flex flex-col gap-1">
        <label class="text-[11px] font-medium text-[var(--color-text-tertiary)] uppercase tracking-wider">Aktion</label>
        <select v-model="filterAction" class="pim-input w-44">
          <option value="">Alle</option>
          <option v-for="(label, key) in actionLabels" :key="key" :value="key">{{ label }}</option>
        </select>
      </div>
      <div class="flex flex-col gap-1">
        <label class="text-[11px] font-medium text-[var(--color-text-tertiary)] uppercase tracking-wider">Typ</label>
        <select v-model="filterType" class="pim-input w-40">
          <option value="">Alle</option>
          <option v-for="(label, key) in typeLabels" :key="key" :value="key">{{ label }}</option>
        </select>
      </div>
      <div class="flex flex-col gap-1">
        <label class="text-[11px] font-medium text-[var(--color-text-tertiary)] uppercase tracking-wider">Benutzer</label>
        <select v-model="filterUserId" class="pim-input w-40">
          <option value="">Alle</option>
          <option v-for="u in usersList" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>
      </div>
      <div class="flex flex-col gap-1">
        <label class="text-[11px] font-medium text-[var(--color-text-tertiary)] uppercase tracking-wider">Von</label>
        <input type="date" v-model="filterDateFrom" class="pim-input w-36" />
      </div>
      <div class="flex flex-col gap-1">
        <label class="text-[11px] font-medium text-[var(--color-text-tertiary)] uppercase tracking-wider">Bis</label>
        <input type="date" v-model="filterDateTo" class="pim-input w-36" />
      </div>
    </div>

    <!-- Table -->
    <PimTable
      :columns="columns"
      :rows="store.items"
      :loading="store.loading"
      :sortField="store.sort.field"
      :sortOrder="store.sort.order"
      :showActions="false"
      emptyText="Keine Einträge im Änderungsprotokoll"
      @sort="handleSort"
    >
      <template #cell-created_at="{ value }">
        <span class="text-xs text-[var(--color-text-tertiary)]">
          {{ value ? new Date(value).toLocaleString('de-DE', { dateStyle: 'medium', timeStyle: 'short' }) : '' }}
        </span>
      </template>

      <template #cell-user_name="{ row }">
        <span class="text-sm">{{ row.user?.name || '—' }}</span>
      </template>

      <template #cell-action="{ value }">
        <span
          class="pim-badge text-[11px] px-2 py-0.5 rounded-full font-medium"
          :class="actionColors[value] || 'bg-gray-100 text-gray-600'"
        >
          {{ actionLabels[value] || value }}
        </span>
      </template>

      <template #cell-auditable_id="{ value }">
        <span class="text-xs font-mono">{{ value ? value.slice(0, 8) + '...' : '' }}</span>
      </template>

      <template #cell-details="{ row }">
        <div class="flex items-start gap-1">
          <button
            v-if="row.old_values || row.new_values"
            class="shrink-0 p-0.5 rounded hover:bg-[var(--color-bg)] transition-colors"
            @click.stop="toggleExpand(row.id)"
          >
            <ChevronDown v-if="expandedRows.has(row.id)" class="w-3.5 h-3.5 text-[var(--color-text-tertiary)]" :stroke-width="2" />
            <ChevronRight v-else class="w-3.5 h-3.5 text-[var(--color-text-tertiary)]" :stroke-width="2" />
          </button>
          <div class="text-xs text-[var(--color-text-secondary)] min-w-0">
            <!-- Referenz auf die Produkt-Version (Snapshot inkl. Attributwerte) -->
            <router-link
              v-if="row.product_version"
              :to="{ name: 'product-detail', params: { id: row.product_version.product_id }, query: { tab: 'versions' } }"
              class="inline-flex items-center gap-1 mb-0.5 text-[11px] font-medium text-[var(--color-accent)] hover:underline"
              @click.stop
            >
              <History class="w-3 h-3" :stroke-width="2" />
              Version {{ row.product_version.version_number }} ansehen
            </router-link>
            <template v-if="!expandedRows.has(row.id)">
              {{ truncate(formatValues(row.new_values || row.old_values)) }}
            </template>
            <template v-else>
              <div v-if="row.old_values" class="mb-1">
                <span class="font-medium text-red-500">Alt:</span>
                <pre class="whitespace-pre-wrap text-[11px] mt-0.5 p-1.5 rounded bg-red-50 dark:bg-red-900/10 text-[var(--color-text-secondary)]">{{ JSON.stringify(row.old_values, null, 2) }}</pre>
              </div>
              <div v-if="row.new_values">
                <span class="font-medium text-emerald-600">Neu:</span>
                <pre class="whitespace-pre-wrap text-[11px] mt-0.5 p-1.5 rounded bg-emerald-50 dark:bg-emerald-900/10 text-[var(--color-text-secondary)]">{{ JSON.stringify(row.new_values, null, 2) }}</pre>
              </div>
            </template>
          </div>
        </div>
      </template>

      <!-- Pagination -->
      <template #pagination>
        <div class="flex items-center justify-between px-4 py-3 border-t border-[var(--color-border)]">
          <span class="text-xs text-[var(--color-text-tertiary)]">
            {{ store.meta.total }} Einträge
          </span>
          <div class="flex items-center gap-1">
            <button
              class="pim-btn pim-btn-ghost text-xs"
              :disabled="store.meta.current_page <= 1"
              @click="goPage(store.meta.current_page - 1)"
            >Zurück</button>
            <span class="text-xs text-[var(--color-text-secondary)] px-2">
              {{ store.meta.current_page }} / {{ store.meta.last_page }}
            </span>
            <button
              class="pim-btn pim-btn-ghost text-xs"
              :disabled="store.meta.current_page >= store.meta.last_page"
              @click="goPage(store.meta.current_page + 1)"
            >Weiter</button>
          </div>
        </div>
      </template>
    </PimTable>

    <!-- Delete confirmation -->
    <PimDeleteConfirmDialog
      :open="showDeleteDialog"
      title="Änderungsprotokoll löschen?"
      message="Alle Einträge im Änderungsprotokoll werden unwiderruflich gelöscht."
      entityType="audit-logs"
      :loading="deleting"
      @confirm="confirmDelete"
      @cancel="showDeleteDialog = false"
    />
  </div>
</template>
