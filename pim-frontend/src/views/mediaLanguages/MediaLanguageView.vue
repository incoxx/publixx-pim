<script setup>
import { ref, computed, onMounted } from 'vue'
import { Plus } from 'lucide-vue-next'
import { mediaLanguages } from '@/api/mediaLanguages'
import { useAuthStore } from '@/stores/auth'
import PimTable from '@/components/shared/PimTable.vue'
import PimDeleteConfirmDialog from '@/components/shared/PimDeleteConfirmDialog.vue'

const authStore = useAuthStore()

const items = ref([])
const loading = ref(false)
const deleteTarget = ref(null)
const deleting = ref(false)
const showForm = ref(false)
const editId = ref(null)
const formData = ref({ technical_name: '', name_de: '', name_en: '', sort_order: 0 })
const formErrors = ref({})
const formSaving = ref(false)

const columns = [
  { key: 'technical_name', label: 'Technischer Name', mono: true, sortable: true },
  { key: 'name_de', label: 'Name (DE)', sortable: true },
  { key: 'name_en', label: 'Name (EN)', sortable: true },
  { key: 'sort_order', label: 'Sortierung', width: '100px', sortable: true },
]

const tableRows = computed(() => items.value)

const meta = ref({ current_page: 1, last_page: 1, total: 0, per_page: 25 })
const sortField = ref('sort_order')
const sortOrder = ref('asc')

async function fetchItems() {
  loading.value = true
  try {
    const { data } = await mediaLanguages.list({
      sort: sortField.value,
      order: sortOrder.value,
      perPage: meta.value.per_page,
      page: meta.value.current_page,
    })
    items.value = data.data || data
    if (data.meta) meta.value = data.meta
  } finally { loading.value = false }
}

function handleSort(field, order) {
  sortField.value = field
  sortOrder.value = order
  meta.value.current_page = 1
  fetchItems()
}

function handlePageChange(page) {
  if (page < 1 || page > meta.value.last_page) return
  meta.value.current_page = page
  fetchItems()
}

function openForm(item = null) {
  if (item && !authStore.hasPermission('media-languages.edit')) return
  if (!item && !authStore.hasPermission('media-languages.create')) return
  if (item) {
    editId.value = item.id
    formData.value = {
      technical_name: item.technical_name || '',
      name_de: item.name_de || '',
      name_en: item.name_en || '',
      sort_order: item.sort_order ?? 0,
    }
  } else {
    editId.value = null
    formData.value = { technical_name: '', name_de: '', name_en: '', sort_order: 0 }
  }
  formErrors.value = {}
  showForm.value = true
}

async function saveForm() {
  formSaving.value = true
  formErrors.value = {}
  try {
    if (editId.value) {
      await mediaLanguages.update(editId.value, formData.value)
    } else {
      await mediaLanguages.create(formData.value)
    }
    showForm.value = false
    await fetchItems()
  } catch (e) {
    if (e.response?.status === 422) {
      const errs = e.response.data.errors || {}
      for (const [key, val] of Object.entries(errs)) {
        formErrors.value[key] = Array.isArray(val) ? val[0] : val
      }
    }
  } finally { formSaving.value = false }
}

async function confirmDelete({ force }) {
  deleting.value = true
  try {
    await mediaLanguages.delete(deleteTarget.value.id, { force })
    deleteTarget.value = null
    await fetchItems()
  } finally { deleting.value = false }
}

onMounted(() => {
  fetchItems()
})
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Medien-Sprachen</h2>
        <p class="text-xs text-[var(--color-text-tertiary)]">Sprachen, die Medien (insbesondere PDF-Dokumenten) zugeordnet werden können</p>
      </div>
      <button v-if="authStore.hasPermission('media-languages.create')" class="pim-btn pim-btn-primary" @click="openForm()">
        <Plus class="w-4 h-4" :stroke-width="2" /> Neue Sprache
      </button>
    </div>

    <!-- Inline form -->
    <div v-if="showForm" class="pim-card p-4 space-y-3">
      <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">
        {{ editId ? 'Sprache bearbeiten' : 'Neue Sprache' }}
      </h3>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <div>
          <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Technischer Name <span class="text-[var(--color-error)]">*</span></label>
          <input class="pim-input" v-model="formData.technical_name" placeholder="z.B. de" :disabled="!!editId" />
          <p v-if="formErrors.technical_name" class="text-[11px] text-[var(--color-error)] mt-0.5">{{ formErrors.technical_name }}</p>
        </div>
        <div>
          <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Name (DE) <span class="text-[var(--color-error)]">*</span></label>
          <input class="pim-input" v-model="formData.name_de" placeholder="z.B. Deutsch" />
          <p v-if="formErrors.name_de" class="text-[11px] text-[var(--color-error)] mt-0.5">{{ formErrors.name_de }}</p>
        </div>
        <div>
          <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Name (EN)</label>
          <input class="pim-input" v-model="formData.name_en" placeholder="z.B. German" />
        </div>
        <div>
          <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Sortierung</label>
          <input class="pim-input" type="number" v-model.number="formData.sort_order" />
        </div>
      </div>

      <div class="flex gap-2">
        <button class="pim-btn pim-btn-primary text-xs" :disabled="formSaving" @click="saveForm">
          {{ formSaving ? 'Speichern…' : 'Speichern' }}
        </button>
        <button class="pim-btn pim-btn-secondary text-xs" @click="showForm = false">Abbrechen</button>
      </div>
    </div>

    <PimTable
      :columns="columns"
      :rows="tableRows"
      :loading="loading"
      :sortField="sortField"
      :sortOrder="sortOrder"
      :showActions="authStore.hasPermission('media-languages.delete')"
      emptyText="Keine Sprachen vorhanden"
      @sort="handleSort"
      @row-click="openForm"
      @row-action="(row) => deleteTarget = row"
    >
      <template #pagination>
        <div class="flex items-center justify-between px-4 py-3 border-t border-[var(--color-border)]">
          <span class="text-xs text-[var(--color-text-tertiary)]">{{ meta.total }} Sprachen</span>
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
      title="Sprache löschen?"
      :message="`Die Sprache '${deleteTarget?.name_de || deleteTarget?.technical_name || ''}' wird unwiderruflich gelöscht.`"
      entityType="media-languages"
      :entityId="deleteTarget?.id"
      :loading="deleting"
      @confirm="confirmDelete"
      @cancel="deleteTarget = null"
    />
  </div>
</template>
