<script setup>
import { ref, onMounted, markRaw } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { formattingRules } from '@/api/attributes'
import { Plus } from 'lucide-vue-next'
import PimTable from '@/components/shared/PimTable.vue'
import PimFilterBar from '@/components/shared/PimFilterBar.vue'
import PimDeleteConfirmDialog from '@/components/shared/PimDeleteConfirmDialog.vue'
import FormattingRuleFormPanel from '@/components/panels/FormattingRuleFormPanel.vue'

const authStore = useAuthStore()
const items = ref([])
const loading = ref(false)
const search = ref('')
const deleteTarget = ref(null)
const deleting = ref(false)
const meta = ref({ current_page: 1, last_page: 1, total: 0, per_page: 25 })
const sortField = ref('name')
const sortOrder = ref('asc')

const RULE_TYPE_LABELS = {
  uppercase: 'Großbuchstaben',
  lowercase: 'Kleinbuchstaben',
  capitalize: 'Erster Buchstabe groß',
  regex: 'Regex Suchen/Ersetzen',
  number_format: 'Zahlenformatierung',
}

const columns = [
  { key: 'name', label: 'Name', sortable: true },
  { key: 'rule_type', label: 'Regeltyp' },
  { key: 'attributes_count', label: 'Attribute', align: 'right' },
]

async function fetchRules() {
  loading.value = true
  try {
    const { data } = await formattingRules.list({
      include: 'attributes',
      search: search.value || undefined,
      sort: sortField.value,
      order: sortOrder.value,
      perPage: meta.value.per_page,
      page: meta.value.current_page,
    })
    items.value = (data.data || data).map(item => ({
      ...item,
      attributes_count: item.attributes?.length ?? 0,
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
  fetchRules()
}

function handlePageChange(page) {
  if (page < 1 || page > meta.value.last_page) return
  meta.value.current_page = page
  fetchRules()
}

function openCreatePanel() {
  authStore.openPanel(markRaw(FormattingRuleFormPanel), {
    formattingRule: null,
    onSaved: () => fetchRules(),
  })
}

function openEditPanel(row) {
  if (!authStore.hasPermission('attribute-formatting-rules.edit')) return
  authStore.openPanel(markRaw(FormattingRuleFormPanel), {
    formattingRule: row,
    onSaved: () => fetchRules(),
  })
}

function handleRowAction(row) {
  deleteTarget.value = row
}

async function confirmDelete({ force } = {}) {
  deleting.value = true
  try {
    await formattingRules.delete(deleteTarget.value.id, { force })
    deleteTarget.value = null
    await fetchRules()
  } finally {
    deleting.value = false
  }
}

onMounted(() => fetchRules())
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Formatierungsregeln</h2>
      <button v-if="authStore.hasPermission('attribute-formatting-rules.create')" class="pim-btn pim-btn-primary" @click="openCreatePanel">
        <Plus class="w-4 h-4" :stroke-width="2" />
        Neue Formatierungsregel
      </button>
    </div>

    <PimFilterBar
      :search="search"
      placeholder="Formatierungsregeln durchsuchen…"
      @update:search="v => { search = v; meta.current_page = 1; fetchRules() }"
    />

    <PimTable
      :columns="columns"
      :rows="items"
      :loading="loading"
      :sortField="sortField"
      :sortOrder="sortOrder"
      :showActions="authStore.hasPermission('attribute-formatting-rules.delete')"
      emptyText="Keine Formatierungsregeln gefunden"
      @sort="handleSort"
      @row-click="openEditPanel"
      @row-action="handleRowAction"
    >
      <template #cell-rule_type="{ value }">
        <span class="pim-badge bg-[var(--color-bg)] text-[var(--color-text-secondary)]">{{ RULE_TYPE_LABELS[value] || value }}</span>
      </template>
      <template #cell-attributes_count="{ value }">
        <span class="pim-badge bg-[var(--color-bg)] text-[var(--color-text-secondary)]">{{ value ?? 0 }}</span>
      </template>

      <!-- Pagination -->
      <template #pagination>
        <div class="flex items-center justify-between px-4 py-3 border-t border-[var(--color-border)]">
          <span class="text-xs text-[var(--color-text-tertiary)]">{{ meta.total }} Formatierungsregeln</span>
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
      title="Formatierungsregel löschen?"
      :message="`Die Formatierungsregel '${deleteTarget?.name || ''}' wird unwiderruflich gelöscht.`"
      entityType="attribute-formatting-rules"
      :entityId="deleteTarget?.id"
      :loading="deleting"
      @confirm="confirmDelete"
      @cancel="deleteTarget = null"
    />
  </div>
</template>
