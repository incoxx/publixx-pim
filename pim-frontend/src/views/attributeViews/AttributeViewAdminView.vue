<script setup>
import { ref, onMounted, markRaw } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { attributeViews } from '@/api/attributes'
import { useI18n } from 'vue-i18n'
import { Plus } from 'lucide-vue-next'
import PimTable from '@/components/shared/PimTable.vue'
import PimFilterBar from '@/components/shared/PimFilterBar.vue'
import PimConfirmDialog from '@/components/shared/PimConfirmDialog.vue'
import AttributeViewFormPanel from '@/components/panels/AttributeViewFormPanel.vue'

const { t } = useI18n()
const authStore = useAuthStore()
const items = ref([])
const loading = ref(false)
const search = ref('')
const deleteTarget = ref(null)
const deleting = ref(false)

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
    const { data } = await attributeViews.list({ include: 'attributes' })
    const raw = data.data || data
    items.value = raw.map(v => ({
      ...v,
      attributes_count: v.attributes?.length ?? 0,
    }))
  } finally {
    loading.value = false
  }
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

async function confirmDelete() {
  deleting.value = true
  try {
    await attributeViews.delete(deleteTarget.value.id)
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
      @update:search="v => { search = v; fetchViews() }"
    />

    <PimTable
      :columns="columns"
      :rows="items"
      :loading="loading"
      :showActions="authStore.hasPermission('attribute-views.delete')"
      emptyText="Keine Attribut-Sichten gefunden"
      @row-click="openEditPanel"
      @row-action="handleRowAction"
    >
      <template #cell-attributes_count="{ value }">
        <span class="pim-badge bg-[var(--color-bg)] text-[var(--color-text-secondary)]">{{ value ?? 0 }}</span>
      </template>
    </PimTable>

    <PimConfirmDialog
      :open="!!deleteTarget"
      title="Attribut-Sicht löschen?"
      :message="`Die Attribut-Sicht '${deleteTarget?.name_de || deleteTarget?.technical_name || ''}' wird unwiderruflich gelöscht.`"
      :loading="deleting"
      @confirm="confirmDelete"
      @cancel="deleteTarget = null"
    />
  </div>
</template>
