<script setup>
import { ref, computed, onMounted } from 'vue'
import { Plus } from 'lucide-vue-next'
import { mediaUsageTypes } from '@/api/mediaUsageTypes'
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
const formData = ref({ technical_name: '', name_de: '', name_en: '', sort_order: 0, allowed_extensions: null })
const formErrors = ref({})
const formSaving = ref(false)
const allExtensionsAllowed = ref(true)

const extensionGroups = [
  { label: 'Bilder', exts: ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'tiff'] },
  { label: 'Dokumente', exts: ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv'] },
  { label: 'Design', exts: ['eps', 'ai'] },
]

const columns = [
  { key: 'technical_name', label: 'Technischer Name', mono: true },
  { key: 'name_de', label: 'Name (DE)' },
  { key: 'name_en', label: 'Name (EN)' },
  { key: 'allowed_extensions_display', label: 'Dateitypen' },
  { key: 'sort_order', label: 'Sortierung', width: '100px' },
]

const tableRows = computed(() =>
  items.value.map(item => ({
    ...item,
    allowed_extensions_display: item.allowed_extensions
      ? item.allowed_extensions.join(', ')
      : 'Alle',
  }))
)

async function fetchItems() {
  loading.value = true
  try {
    const { data } = await mediaUsageTypes.list()
    items.value = data.data || data
  } finally { loading.value = false }
}

function openForm(item = null) {
  if (item && !authStore.hasPermission('media-usage-types.edit')) return
  if (!item && !authStore.hasPermission('media-usage-types.create')) return
  if (item) {
    editId.value = item.id
    formData.value = {
      technical_name: item.technical_name || '',
      name_de: item.name_de || '',
      name_en: item.name_en || '',
      sort_order: item.sort_order ?? 0,
      allowed_extensions: item.allowed_extensions ? [...item.allowed_extensions] : null,
    }
    allExtensionsAllowed.value = item.allowed_extensions === null
  } else {
    editId.value = null
    formData.value = { technical_name: '', name_de: '', name_en: '', sort_order: 0, allowed_extensions: null }
    allExtensionsAllowed.value = true
  }
  formErrors.value = {}
  showForm.value = true
}

function toggleAllExtensions() {
  allExtensionsAllowed.value = !allExtensionsAllowed.value
  if (allExtensionsAllowed.value) {
    formData.value.allowed_extensions = null
  } else {
    formData.value.allowed_extensions = []
  }
}

function toggleExt(ext) {
  if (allExtensionsAllowed.value) return
  const list = formData.value.allowed_extensions || []
  const idx = list.indexOf(ext)
  if (idx >= 0) {
    list.splice(idx, 1)
  } else {
    list.push(ext)
  }
  formData.value.allowed_extensions = [...list]
}

function isExtSelected(ext) {
  if (allExtensionsAllowed.value) return false
  return (formData.value.allowed_extensions || []).includes(ext)
}

async function saveForm() {
  formSaving.value = true
  formErrors.value = {}
  try {
    const payload = { ...formData.value }
    // Convert empty array to null (= all allowed)
    if (Array.isArray(payload.allowed_extensions) && payload.allowed_extensions.length === 0) {
      payload.allowed_extensions = null
    }
    if (editId.value) {
      await mediaUsageTypes.update(editId.value, payload)
    } else {
      await mediaUsageTypes.create(payload)
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
    await mediaUsageTypes.delete(deleteTarget.value.id, { force })
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
      <button v-if="authStore.hasPermission('media-usage-types.create')" class="pim-btn pim-btn-primary" @click="openForm()">
        <Plus class="w-4 h-4" :stroke-width="2" /> Neuer Bildtyp
      </button>
    </div>

    <!-- Inline form -->
    <div v-if="showForm" class="pim-card p-4 space-y-3">
      <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">
        {{ editId ? 'Bildtyp bearbeiten' : 'Neuer Bildtyp' }}
      </h3>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
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

      <!-- Allowed extensions -->
      <div>
        <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-2">Erlaubte Dateitypen</label>
        <label class="inline-flex items-center gap-2 cursor-pointer mb-2">
          <input type="checkbox" :checked="allExtensionsAllowed" class="w-3.5 h-3.5 accent-[var(--color-primary)]" @change="toggleAllExtensions" />
          <span class="text-xs text-[var(--color-text-primary)]">Alle Dateitypen erlaubt</span>
        </label>
        <div v-if="!allExtensionsAllowed" class="space-y-2">
          <div v-for="group in extensionGroups" :key="group.label">
            <span class="text-[11px] text-[var(--color-text-tertiary)] font-medium">{{ group.label }}</span>
            <div class="flex flex-wrap gap-1.5 mt-1">
              <button
                v-for="ext in group.exts"
                :key="ext"
                type="button"
                class="px-2 py-0.5 rounded text-[11px] border transition-colors"
                :class="isExtSelected(ext)
                  ? 'bg-[var(--color-accent)] text-white border-[var(--color-accent)]'
                  : 'bg-[var(--color-surface)] text-[var(--color-text-secondary)] border-[var(--color-border)] hover:border-[var(--color-accent)]'"
                @click="toggleExt(ext)"
              >
                .{{ ext }}
              </button>
            </div>
          </div>
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
      :showActions="authStore.hasPermission('media-usage-types.delete')"
      emptyText="Keine Bildtypen vorhanden"
      @row-click="openForm"
      @row-action="(row) => deleteTarget = row"
    />

    <PimDeleteConfirmDialog
      :open="!!deleteTarget"
      title="Bildtyp löschen?"
      :message="`Der Bildtyp '${deleteTarget?.name_de || deleteTarget?.technical_name || ''}' wird unwiderruflich gelöscht.`"
      entityType="media-usage-types"
      :entityId="deleteTarget?.id"
      :loading="deleting"
      @confirm="confirmDelete"
      @cancel="deleteTarget = null"
    />
  </div>
</template>
