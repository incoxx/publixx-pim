<script setup>
import { ref, onMounted } from 'vue'
import { Plus } from 'lucide-vue-next'
import { mediaUsageTypes } from '@/api/mediaUsageTypes'
import PimTable from '@/components/shared/PimTable.vue'
import PimConfirmDialog from '@/components/shared/PimConfirmDialog.vue'

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
  { key: 'technical_name', label: 'Technischer Name', mono: true },
  { key: 'name_de', label: 'Name (DE)' },
  { key: 'name_en', label: 'Name (EN)' },
  { key: 'sort_order', label: 'Sortierung', width: '100px' },
]

async function fetchItems() {
  loading.value = true
  try {
    const { data } = await mediaUsageTypes.list()
    items.value = data.data || data
  } finally { loading.value = false }
}

function openForm(item = null) {
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
      await mediaUsageTypes.update(editId.value, formData.value)
    } else {
      await mediaUsageTypes.create(formData.value)
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

async function confirmDelete() {
  deleting.value = true
  try {
    await mediaUsageTypes.delete(deleteTarget.value.id)
    deleteTarget.value = null
    await fetchItems()
  } finally { deleting.value = false }
}

onMounted(() => fetchItems())
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Bildtypen</h2>
      <button class="pim-btn pim-btn-primary" @click="openForm()">
        <Plus class="w-4 h-4" :stroke-width="2" /> Neuer Bildtyp
      </button>
    </div>

    <!-- Inline form -->
    <div v-if="showForm" class="pim-card p-4 space-y-3">
      <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">
        {{ editId ? 'Bildtyp bearbeiten' : 'Neuer Bildtyp' }}
      </h3>
      <div class="grid grid-cols-4 gap-3">
        <div>
          <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Technischer Name <span class="text-[var(--color-error)]">*</span></label>
          <input class="pim-input" v-model="formData.technical_name" :disabled="!!editId" />
          <p v-if="formErrors.technical_name" class="text-[11px] text-[var(--color-error)] mt-0.5">{{ formErrors.technical_name }}</p>
        </div>
        <div>
          <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Name (DE) <span class="text-[var(--color-error)]">*</span></label>
          <input class="pim-input" v-model="formData.name_de" />
          <p v-if="formErrors.name_de" class="text-[11px] text-[var(--color-error)] mt-0.5">{{ formErrors.name_de }}</p>
        </div>
        <div>
          <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Name (EN)</label>
          <input class="pim-input" v-model="formData.name_en" />
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
      :rows="items"
      :loading="loading"
      emptyText="Keine Bildtypen vorhanden"
      @row-click="openForm"
      @row-action="(row) => deleteTarget = row"
    />

    <PimConfirmDialog
      :open="!!deleteTarget"
      title="Bildtyp löschen?"
      :message="`Der Bildtyp '${deleteTarget?.name_de || deleteTarget?.technical_name || ''}' wird unwiderruflich gelöscht.`"
      :loading="deleting"
      @confirm="confirmDelete"
      @cancel="deleteTarget = null"
    />
  </div>
</template>
