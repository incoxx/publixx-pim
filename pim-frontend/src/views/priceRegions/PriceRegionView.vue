<script setup>
import { ref, onMounted } from 'vue'
import { Plus } from 'lucide-vue-next'
import { priceRegions } from '@/api/priceRegions'
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
const formData = ref({ code: '', name: '', type: 'country', metadata: {} })
const formErrors = ref({})
const formSaving = ref(false)
const metadataKey = ref('')
const metadataValue = ref('')

const columns = [
  { key: 'code', label: 'Code', mono: true },
  { key: 'name', label: 'Name' },
  { key: 'type', label: 'Typ' },
]

const typeOptions = [
  { value: 'country', label: 'Land' },
  { value: 'vkorg', label: 'Vertriebsorganisation' },
]

async function fetchItems() {
  loading.value = true
  try {
    const { data } = await priceRegions.list()
    items.value = data.data || data
  } finally { loading.value = false }
}

function openForm(item = null) {
  if (item && !authStore.hasPermission('price-regions.edit')) return
  if (!item && !authStore.hasPermission('price-regions.create')) return
  if (item) {
    editId.value = item.id
    formData.value = { code: item.code || '', name: item.name || '', type: item.type || 'country', metadata: item.metadata || {} }
  } else {
    editId.value = null
    formData.value = { code: '', name: '', type: 'country', metadata: {} }
  }
  formErrors.value = {}
  metadataKey.value = ''
  metadataValue.value = ''
  showForm.value = true
}

async function saveForm() {
  formSaving.value = true
  formErrors.value = {}
  try {
    const payload = { ...formData.value }
    if (!payload.metadata || Object.keys(payload.metadata).length === 0) {
      payload.metadata = null
    }
    if (editId.value) {
      await priceRegions.update(editId.value, payload)
    } else {
      await priceRegions.create(payload)
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

function addMetadata() {
  if (!metadataKey.value.trim()) return
  formData.value.metadata = { ...formData.value.metadata, [metadataKey.value.trim()]: metadataValue.value }
  metadataKey.value = ''
  metadataValue.value = ''
}

function removeMetadata(key) {
  const meta = { ...formData.value.metadata }
  delete meta[key]
  formData.value.metadata = meta
}

async function confirmDelete({ force }) {
  deleting.value = true
  try {
    await priceRegions.delete(deleteTarget.value.id, { force })
    deleteTarget.value = null
    await fetchItems()
  } finally { deleting.value = false }
}

onMounted(() => fetchItems())
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Preisregionen</h2>
      <button v-if="authStore.hasPermission('price-regions.create')" class="pim-btn pim-btn-primary" @click="openForm()">
        <Plus class="w-4 h-4" :stroke-width="2" /> Neue Preisregion
      </button>
    </div>

    <!-- Inline form -->
    <div v-if="showForm" class="pim-card p-4 space-y-3">
      <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">
        {{ editId ? 'Preisregion bearbeiten' : 'Neue Preisregion' }}
      </h3>
      <div class="grid grid-cols-3 gap-3">
        <div>
          <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Code <span class="text-[var(--color-error)]">*</span></label>
          <input class="pim-input" v-model="formData.code" maxlength="10" placeholder="z.B. DE, VK01" />
          <p v-if="formErrors.code" class="text-[11px] text-[var(--color-error)] mt-0.5">{{ formErrors.code }}</p>
        </div>
        <div>
          <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Name <span class="text-[var(--color-error)]">*</span></label>
          <input class="pim-input" v-model="formData.name" placeholder="z.B. Deutschland, Großhändler" />
          <p v-if="formErrors.name" class="text-[11px] text-[var(--color-error)] mt-0.5">{{ formErrors.name }}</p>
        </div>
        <div>
          <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Typ <span class="text-[var(--color-error)]">*</span></label>
          <select class="pim-input" v-model="formData.type">
            <option v-for="opt in typeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>
          <p v-if="formErrors.type" class="text-[11px] text-[var(--color-error)] mt-0.5">{{ formErrors.type }}</p>
        </div>
      </div>

      <!-- Metadata section -->
      <div>
        <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Metadaten</label>
        <div v-if="formData.metadata && Object.keys(formData.metadata).length > 0" class="space-y-1 mb-2">
          <div v-for="(val, key) in formData.metadata" :key="key" class="flex items-center gap-2 text-sm">
            <span class="font-mono text-[var(--color-text-secondary)]">{{ key }}:</span>
            <span>{{ val }}</span>
            <button class="text-[var(--color-error)] text-xs hover:underline" @click="removeMetadata(key)">Entfernen</button>
          </div>
        </div>
        <div class="flex gap-2">
          <input class="pim-input w-40" v-model="metadataKey" placeholder="Schlüssel" @keyup.enter="addMetadata" />
          <input class="pim-input flex-1" v-model="metadataValue" placeholder="Wert" @keyup.enter="addMetadata" />
          <button class="pim-btn pim-btn-secondary text-xs" @click="addMetadata" :disabled="!metadataKey.trim()">Hinzufügen</button>
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
      :showActions="authStore.hasPermission('price-regions.delete')"
      emptyText="Keine Preisregionen vorhanden"
      @row-click="openForm"
      @row-action="(row) => deleteTarget = row"
    />

    <PimDeleteConfirmDialog
      :open="!!deleteTarget"
      title="Preisregion löschen?"
      :message="`Die Preisregion '${deleteTarget?.name || deleteTarget?.code || ''}' wird unwiderruflich gelöscht.`"
      entityType="price-regions"
      :entityId="deleteTarget?.id"
      :loading="deleting"
      @confirm="confirmDelete"
      @cancel="deleteTarget = null"
    />
  </div>
</template>
