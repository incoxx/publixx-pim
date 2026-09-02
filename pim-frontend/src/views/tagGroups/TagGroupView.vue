<script setup>
/**
 * Tag-Gruppen — Pflege wie bei den Attributgruppen.
 *
 * Gruppen sind eine Sortierhilfe: im Katalog wird je Gruppe eine eigene
 * Filtergruppe angezeigt. Eine Gruppe zu löschen entfernt ihre Tags nicht,
 * sie sind danach nur ungruppiert.
 */
import { ref, computed, onMounted } from 'vue'
import { Plus } from 'lucide-vue-next'
import { tagGroups as tagGroupsApi } from '@/api/tags'
import { useAuthStore } from '@/stores/auth'
import { useLocaleStore } from '@/stores/locale'
import PimTable from '@/components/shared/PimTable.vue'
import PimDeleteConfirmDialog from '@/components/shared/PimDeleteConfirmDialog.vue'

const authStore = useAuthStore()
const localeStore = useLocaleStore()

const items = ref([])
const loading = ref(false)
const deleteTarget = ref(null)
const deleting = ref(false)
const showForm = ref(false)
const editId = ref(null)
const formData = ref(emptyForm())
const formErrors = ref({})
const formSaving = ref(false)
const meta = ref({ current_page: 1, last_page: 1, total: 0, per_page: 25 })
const sortField = ref('sort_order')
const sortOrder = ref('asc')

function emptyForm() {
  return { technical_name: '', name_de: '', name_en: '', name_json: {}, sort_order: 0 }
}

const columns = [
  { key: 'technical_name', label: 'Technischer Name', mono: true, sortable: true },
  { key: 'name_de', label: 'Name (DE)', sortable: true },
  { key: 'name_en', label: 'Name (EN)', sortable: true },
  { key: 'tags_count', label: 'Tags', width: '90px' },
  { key: 'sort_order', label: 'Sortierung', width: '100px', sortable: true },
]

const extraLocales = computed(() =>
  localeStore.availableLocales.filter(l => l.code !== 'de' && l.code !== 'en'),
)

async function fetchItems() {
  loading.value = true
  try {
    const { data } = await tagGroupsApi.list({
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
  if (item && !authStore.hasPermission('tags.edit')) return
  if (!item && !authStore.hasPermission('tags.create')) return
  if (item) {
    editId.value = item.id
    formData.value = {
      technical_name: item.technical_name || '',
      name_de: item.name_de || '',
      name_en: item.name_en || '',
      name_json: { ...(item.name_json || {}) },
      sort_order: item.sort_order ?? 0,
    }
  } else {
    editId.value = null
    formData.value = emptyForm()
  }
  formErrors.value = {}
  showForm.value = true
}

async function saveForm() {
  formSaving.value = true
  formErrors.value = {}
  try {
    const payload = { ...formData.value }
    const translations = Object.fromEntries(
      Object.entries(payload.name_json || {}).filter(([, value]) => (value || '').trim() !== ''),
    )
    payload.name_json = Object.keys(translations).length ? translations : null
    if (!editId.value && !payload.technical_name.trim()) delete payload.technical_name

    if (editId.value) await tagGroupsApi.update(editId.value, payload)
    else await tagGroupsApi.create(payload)

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
    await tagGroupsApi.delete(deleteTarget.value.id, { force })
    deleteTarget.value = null
    await fetchItems()
  } finally { deleting.value = false }
}

onMounted(() => {
  fetchItems()
  localeStore.fetchLanguages()
})
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Tag-Gruppen</h2>
      <button v-if="authStore.hasPermission('tags.create')" class="pim-btn pim-btn-primary" @click="openForm()">
        <Plus class="w-4 h-4" :stroke-width="2" /> Neue Tag-Gruppe
      </button>
    </div>

    <p class="text-[11px] text-[var(--color-text-tertiary)]">
      Gruppen bündeln Tags — im Katalog erscheint je Gruppe eine eigene Filtergruppe. Tags ohne Gruppe bleiben erlaubt.
    </p>

    <div v-if="showForm" class="pim-card p-4 space-y-3">
      <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">
        {{ editId ? 'Tag-Gruppe bearbeiten' : 'Neue Tag-Gruppe' }}
      </h3>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
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
          <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Technischer Name</label>
          <input class="pim-input font-mono text-xs" v-model="formData.technical_name" :disabled="!!editId" placeholder="wird aus Name (DE) abgeleitet" />
          <p v-if="formErrors.technical_name" class="text-[11px] text-[var(--color-error)] mt-0.5">{{ formErrors.technical_name }}</p>
          <p v-else-if="editId" class="text-[11px] text-[var(--color-text-tertiary)] mt-0.5">Nicht änderbar — stabiler Schlüssel für Import/Export.</p>
        </div>
        <div>
          <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Sortierung</label>
          <input class="pim-input" type="number" v-model.number="formData.sort_order" />
        </div>
      </div>

      <div v-if="extraLocales.length">
        <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-2">Weitere Sprachen</label>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
          <div v-for="locale in extraLocales" :key="locale.code">
            <label class="block text-[11px] text-[var(--color-text-tertiary)] mb-1">{{ locale.label }} ({{ locale.code }})</label>
            <input
              class="pim-input"
              :value="formData.name_json[locale.code] || ''"
              @input="formData.name_json = { ...formData.name_json, [locale.code]: $event.target.value }"
            />
          </div>
        </div>
      </div>

      <div class="flex items-center gap-2 pt-1">
        <button class="pim-btn pim-btn-primary text-xs" :disabled="formSaving" @click="saveForm">
          {{ formSaving ? 'Speichern…' : 'Speichern' }}
        </button>
        <button class="pim-btn pim-btn-ghost text-xs" @click="showForm = false">Abbrechen</button>
      </div>
    </div>

    <PimTable
      :columns="columns"
      :rows="items"
      :loading="loading"
      :sortField="sortField"
      :sortOrder="sortOrder"
      :showActions="authStore.hasPermission('tags.delete')"
      emptyText="Keine Tag-Gruppen vorhanden"
      @sort="handleSort"
      @row-click="openForm"
      @row-action="(row) => deleteTarget = row"
    >
      <template #pagination>
        <div class="flex items-center justify-between px-4 py-3 border-t border-[var(--color-border)]">
          <span class="text-xs text-[var(--color-text-tertiary)]">{{ meta.total }} Tag-Gruppen</span>
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
      title="Tag-Gruppe löschen?"
      :message="`Die Gruppe '${deleteTarget?.name_de || ''}' wird gelöscht. Ihre ${deleteTarget?.tags_count ?? 0} Tags bleiben erhalten und sind danach ungruppiert.`"
      entityType="tag-groups"
      :entityId="deleteTarget?.id"
      :loading="deleting"
      @confirm="confirmDelete"
      @cancel="deleteTarget = null"
    />
  </div>
</template>
