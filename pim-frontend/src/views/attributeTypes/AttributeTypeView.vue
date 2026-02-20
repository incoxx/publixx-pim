<script setup>
import { ref, onMounted, markRaw } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { attributeTypes } from '@/api/attributes'
import { useI18n } from 'vue-i18n'
import { Plus } from 'lucide-vue-next'
import PimTable from '@/components/shared/PimTable.vue'
import PimFilterBar from '@/components/shared/PimFilterBar.vue'
import PimConfirmDialog from '@/components/shared/PimConfirmDialog.vue'
import AttributeTypeFormPanel from '@/components/panels/AttributeTypeFormPanel.vue'

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
  { key: 'sort_order', label: 'Sortierung', sortable: true },
]

async function fetchTypes() {
  loading.value = true
  try {
    const { data } = await attributeTypes.list()
    items.value = data.data || data
  } finally {
    loading.value = false
  }
}

function openCreatePanel() {
  authStore.openPanel(markRaw(AttributeTypeFormPanel), {
    attributeType: null,
    onSaved: () => fetchTypes(),
  })
}

function openEditPanel(row) {
  authStore.openPanel(markRaw(AttributeTypeFormPanel), {
    attributeType: row,
    onSaved: () => fetchTypes(),
  })
}

function handleRowAction(row) {
  deleteTarget.value = row
}

async function confirmDelete() {
  deleting.value = true
  try {
    await attributeTypes.delete(deleteTarget.value.id)
    deleteTarget.value = null
    await fetchTypes()
  } finally {
    deleting.value = false
  }
}

onMounted(() => fetchTypes())
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">{{ t('attributeType.title') }}</h2>
      <button class="pim-btn pim-btn-primary" @click="openCreatePanel">
        <Plus class="w-4 h-4" :stroke-width="2" />
        {{ t('attributeType.newType') }}
      </button>
    </div>

    <PimFilterBar
      :search="search"
      placeholder="Attributgruppen durchsuchen…"
      @update:search="v => { search = v; fetchTypes() }"
    />

    <PimTable
      :columns="columns"
      :rows="items"
      :loading="loading"
      emptyText="Keine Attributgruppen gefunden"
      @row-click="openEditPanel"
      @row-action="handleRowAction"
    />

    <PimConfirmDialog
      :open="!!deleteTarget"
      title="Attributgruppe löschen?"
      :message="`Die Attributgruppe '${deleteTarget?.name_de || deleteTarget?.technical_name || ''}' wird unwiderruflich gelöscht.`"
      :loading="deleting"
      @confirm="confirmDelete"
      @cancel="deleteTarget = null"
    />
  </div>
</template>
