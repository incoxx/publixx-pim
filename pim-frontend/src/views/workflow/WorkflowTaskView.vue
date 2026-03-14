<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { Plus, Trash2, ChevronDown } from 'lucide-vue-next'
import { useWorkflowTasksStore } from '@/stores/workflowTasks'
import { useAuthStore } from '@/stores/auth'
import usersApi from '@/api/users'
import PimTable from '@/components/shared/PimTable.vue'
import PimDeleteConfirmDialog from '@/components/shared/PimDeleteConfirmDialog.vue'

const store = useWorkflowTasksStore()
const authStore = useAuthStore()
const router = useRouter()

const usersList = ref([])
const showDeleteDialog = ref(false)
const deleteTarget = ref(null)
const deleting = ref(false)

// New task form
const showNewTask = ref(false)
const newTask = ref({ product_id: '', title: '', assigned_to: '', note: '' })

const columns = [
  { key: 'created_at', label: 'Datum', sortable: true },
  { key: 'product_sku', label: 'SKU' },
  { key: 'product_name', label: 'Produkt' },
  { key: 'title', label: 'Aufgabe' },
  { key: 'status', label: 'Status', sortable: true },
  { key: 'assignee_name', label: 'Zugewiesen an' },
  { key: 'creator_name', label: 'Erstellt von' },
]

// Filters
const filterStatus = ref('')
const filterUserId = ref('')
const filterDateFrom = ref('')
const filterDateTo = ref('')
const searchQuery = ref('')

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
  if (filterStatus.value) f.status = filterStatus.value
  if (filterUserId.value) f.assigned_to = filterUserId.value
  if (filterDateFrom.value) f.date_from = filterDateFrom.value
  if (filterDateTo.value) f.date_to = filterDateTo.value
  store.setFilters(f)
  store.setPage(1)
  store.fetchList()
}

watch([filterStatus, filterUserId, filterDateFrom, filterDateTo], applyFilters)

const statusLabels = {
  open: 'Offen',
  in_progress: 'In Bearbeitung',
  closed: 'Geschlossen',
}

const statusColors = {
  open: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
  in_progress: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
  closed: 'bg-gray-100 text-gray-500 dark:bg-gray-800/40 dark:text-gray-400',
}

function handleSort({ field, order }) {
  store.setSort(field, order)
  store.fetchList()
}

function goPage(page) {
  store.setPage(page)
  store.fetchList()
}

function goToProduct(id) {
  if (id) router.push(`/products/${id}`)
}

async function changeStatus(task, newStatus) {
  await store.updateTask(task.id, { status: newStatus })
}

function confirmDelete(task) {
  deleteTarget.value = task
  showDeleteDialog.value = true
}

async function doDelete() {
  if (!deleteTarget.value) return
  deleting.value = true
  try {
    await store.deleteTask(deleteTarget.value.id)
    showDeleteDialog.value = false
    deleteTarget.value = null
  } finally {
    deleting.value = false
  }
}

// Map flat row data for PimTable
function mapRows(items) {
  return items.map(item => ({
    ...item,
    product_sku: item.product?.sku || '—',
    product_name: item.product?.name || '—',
    product_id: item.product?.id,
    assignee_name: item.assignee?.name || '—',
    creator_name: item.creator?.name || '—',
  }))
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
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Workflow-Aufgaben</h2>
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
        <label class="text-[11px] font-medium text-[var(--color-text-tertiary)] uppercase tracking-wider">Status</label>
        <select v-model="filterStatus" class="pim-input w-44">
          <option value="">Alle</option>
          <option value="open">Offen</option>
          <option value="in_progress">In Bearbeitung</option>
          <option value="closed">Geschlossen</option>
        </select>
      </div>
      <div class="flex flex-col gap-1">
        <label class="text-[11px] font-medium text-[var(--color-text-tertiary)] uppercase tracking-wider">Zugewiesen an</label>
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
      :rows="mapRows(store.items)"
      :loading="store.loading"
      :sortField="store.sort.field"
      :sortOrder="store.sort.order"
      :showActions="true"
      emptyText="Keine Workflow-Aufgaben vorhanden"
      @sort="handleSort"
    >
      <template #cell-created_at="{ value }">
        <span class="text-xs text-[var(--color-text-tertiary)]">
          {{ value ? new Date(value).toLocaleString('de-DE', { dateStyle: 'medium', timeStyle: 'short' }) : '' }}
        </span>
      </template>

      <template #cell-product_sku="{ row }">
        <button
          class="text-sm font-mono text-[var(--color-accent)] hover:underline cursor-pointer"
          @click.stop="goToProduct(row.product_id)"
        >
          {{ row.product_sku }}
        </button>
      </template>

      <template #cell-product_name="{ row }">
        <span class="text-sm">{{ row.product_name }}</span>
      </template>

      <template #cell-title="{ value }">
        <span class="text-sm font-medium">{{ value }}</span>
      </template>

      <template #cell-status="{ value }">
        <span
          class="pim-badge text-[11px] px-2 py-0.5 rounded-full font-medium"
          :class="statusColors[value] || 'bg-gray-100 text-gray-600'"
        >
          {{ statusLabels[value] || value }}
        </span>
      </template>

      <template #cell-assignee_name="{ row }">
        <span class="text-sm">{{ row.assignee_name }}</span>
      </template>

      <template #cell-creator_name="{ row }">
        <span class="text-sm">{{ row.creator_name }}</span>
      </template>

      <!-- Row actions -->
      <template #actions="{ row }">
        <div class="flex items-center gap-1">
          <!-- Status dropdown -->
          <div class="relative group">
            <button class="pim-btn pim-btn-ghost text-xs px-2 py-1">
              <ChevronDown class="w-3.5 h-3.5" :stroke-width="2" />
            </button>
            <div class="absolute right-0 top-full mt-1 w-40 py-1 bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all z-20">
              <button
                v-if="row.status !== 'open'"
                class="w-full text-left px-3 py-1.5 text-xs hover:bg-[var(--color-bg)] transition-colors"
                @click.stop="changeStatus(row, 'open')"
              >Offen setzen</button>
              <button
                v-if="row.status !== 'in_progress'"
                class="w-full text-left px-3 py-1.5 text-xs hover:bg-[var(--color-bg)] transition-colors"
                @click.stop="changeStatus(row, 'in_progress')"
              >In Bearbeitung</button>
              <button
                v-if="row.status !== 'closed'"
                class="w-full text-left px-3 py-1.5 text-xs hover:bg-[var(--color-bg)] transition-colors"
                @click.stop="changeStatus(row, 'closed')"
              >Schließen</button>
            </div>
          </div>
          <button
            class="pim-btn pim-btn-ghost text-xs px-2 py-1 text-red-500 hover:text-red-700"
            @click.stop="confirmDelete(row)"
          >
            <Trash2 class="w-3.5 h-3.5" :stroke-width="2" />
          </button>
        </div>
      </template>

      <!-- Pagination -->
      <template #pagination>
        <div class="flex items-center justify-between px-4 py-3 border-t border-[var(--color-border)]">
          <span class="text-xs text-[var(--color-text-tertiary)]">
            {{ store.meta.total }} Aufgaben
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
      title="Aufgabe löschen?"
      message="Die Workflow-Aufgabe wird unwiderruflich gelöscht."
      entityType="workflow-task"
      :loading="deleting"
      @confirm="doDelete"
      @cancel="showDeleteDialog = false; deleteTarget = null"
    />
  </div>
</template>
