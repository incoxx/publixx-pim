<script setup>
import { ref, onMounted, markRaw } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { attributeViews } from '@/api/attributes'
import { useI18n } from 'vue-i18n'
import { Plus } from 'lucide-vue-next'
import PimTable from '@/components/shared/PimTable.vue'
import PimFilterBar from '@/components/shared/PimFilterBar.vue'
import PimDeleteConfirmDialog from '@/components/shared/PimDeleteConfirmDialog.vue'
import AttributeViewFormPanel from '@/components/panels/AttributeViewFormPanel.vue'

const { t } = useI18n()
const authStore = useAuthStore()
const items = ref([])
const loading = ref(false)
const search = ref('')
const deleteTarget = ref(null)
const deleting = ref(false)
const meta = ref({ current_page: 1, last_page: 1, total: 0, per_page: 25 })
const sortField = ref('sort_order')
const sortOrder = ref('asc')

const columns = [
  { key: 'technical_name', label: 'Code', sortable: true, mono: true },
  { key: 'name_de', label: 'Name DE', sortable: true },
  { key: 'name_en', label: 'Name EN', sortable: true },
  { key: 'attributes_count', label: 'Attribute' },
  { key: 'sort_order', label: 'Sortierung', sortable: true },
]

async function fetchViews() {
  loading.value = true
  try {
    const { data } = await attributeViews.list({
      include: 'attributes',
      search: search.value || undefined,
      sort: sortField.value,
      order: sortOrder.value,
      perPage: meta.value.per_page,
      page: meta.value.current_page,
    })
    const raw = data.data || data
    items.value = raw.map(v => ({
      ...v,
      attributes_count: v.attributes?.length ?? 0,
    }))
    if (data.meta) meta.value = data.meta
  } finally {
    loading.value = false
  }
}

function handleSort(field, order) {
  sortField.value = field
  sortOrder.value = order
  meta.value.current_page = 1
  fetchViews()
}

function handlePageChange(page) {
  if (page < 1 || page > meta.value.last_page) return
  meta.value.current_page = page
  fetchViews()
}

function openCreatePanel() {
  authStore.openPanel(markRaw(AttributeViewFormPanel), {
    attributeView: null,
    onSaved: () => fetchViews(),
  })
}

function openEditPanel(row) {
  if (!authStore.hasPermission('attribute-views.edit')) return
  authStore.openPanel(markRaw(AttributeViewFormPanel), {
    attributeView: row,
    onSaved: () => fetchViews(),
  })
}

function handleRowAction(row) {
  deleteTarget.value = row
}

async function confirmDelete({ force }) {
  deleting.value = true
  try {
    await attributeViews.delete(deleteTarget.value.id, { force })
    deleteTarget.value = null
    await fetchViews()
  } finally {
    deleting.value = false
  }
}

onMounted(() => fetchViews())
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">{{ t('attributeView.title') }}</h2>
      <button v-if="authStore.hasPermission('attribute-views.create')" class="pim-btn pim-btn-primary" @click="openCreatePanel">
        <Plus class="w-4 h-4" :stroke-width="2" />
        {{ t('attributeView.newView') }}
      </button>
    </div>

    <PimFilterBar
      :search="search"
      placeholder="Attribut-Sichten durchsuchen…"
      @update:search="v => { search = v; meta.current_page = 1; fetchViews() }"
    />

    <PimTable
      :columns="columns"
      :rows="items"
      :loading="loading"
      :sortField="sortField"
      :sortOrder="sortOrder"
      :showActions="authStore.hasPermission('attribute-views.delete')"
      emptyText="Keine Attribut-Sichten gefunden"
      @sort="handleSort"
      @row-click="openEditPanel"
      @row-action="handleRowAction"
    >
      <template #cell-attributes_count="{ value }">
        <span class="pim-badge bg-[var(--color-bg)] text-[var(--color-text-secondary)]">{{ value ?? 0 }}</span>
      </template>

      <!-- Pagination -->
      <template #pagination>
        <div class="flex items-center justify-between px-4 py-3 border-t border-[var(--color-border)]">
          <span class="text-xs text-[var(--color-text-tertiary)]">{{ meta.total }} Attribut-Sichten</span>
          <div class="flex items-center gap-1">
            <button class="pim-btn pim-btn-ghost text-xs" :disabled="meta.current_page <= 1" @click="handlePageChange(meta.current_page - 1)">Zurück</button>
            <span class="text-xs text-[var(--color-text-secondary)] px-2">{{ meta.current_page }} / {{ meta.last_page }}</span>
            <button class="pim-btn pim-btn-ghost text-xs" :disabled="meta.current_page >= meta.last_page" @click="handlePageChange(meta.current_page + 1)">Weiter</button>
          </div>
        </div>
      </template>
    </PimTable>

    <PimDeleteConfirmDialog
      :open="!!deleteTarget"
      title="Attribut-Sicht löschen?"
      :message="`Die Attribut-Sicht '${deleteTarget?.name_de || deleteTarget?.technical_name || ''}' wird unwiderruflich gelöscht.`"
      entityType="attribute-views"
      :entityId="deleteTarget?.id"
      :loading="deleting"
      @confirm="confirmDelete"
      @cancel="deleteTarget = null"
    />
  </div>
</template>
