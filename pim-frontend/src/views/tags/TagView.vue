<script setup>
import { ref, computed, onMounted } from 'vue'
import { Plus, Search } from 'lucide-vue-next'
import { tags as tagsApi } from '@/api/tags'
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
const searchTerm = ref('')
const meta = ref({ current_page: 1, last_page: 1, total: 0, per_page: 25 })
const sortField = ref('name_de')
const sortOrder = ref('asc')

function emptyForm() {
  return { technical_name: '', name_de: '', name_en: '', name_json: {}, sort_order: 0, is_active: true }
}

const columns = [
  { key: 'technical_name', label: 'Technischer Name', mono: true, sortable: true },
  { key: 'name_de', label: 'Name (DE)', sortable: true },
  { key: 'name_en', label: 'Name (EN)', sortable: true },
  { key: 'further_languages', label: 'Weitere Sprachen' },
  { key: 'usage_display', label: 'Verwendung', width: '160px' },
  { key: 'active_display', label: 'Aktiv', width: '80px' },
  { key: 'sort_order', label: 'Sortierung', width: '100px', sortable: true },
]

// Sprachen jenseits von DE/EN kommen aus der Sprachverwaltung und landen in name_json
// (docs/architecture/05-i18n.md, Level 1: feste Spalten + JSON für den Rest).
const extraLocales = computed(() =>
  localeStore.availableLocales.filter(l => l.code !== 'de' && l.code !== 'en'),
)

const tableRows = computed(() =>
  items.value.map(item => ({
    ...item,
    further_languages: Object.entries(item.name_json || {})
      .filter(([, value]) => value)
      .map(([code, value]) => `${code.toUpperCase()}: ${value}`)
      .join(', ') || '—',
    usage_display: `${item.products_count ?? 0} Produkte · ${item.media_count ?? 0} Medien`,
    active_display: item.is_active ? 'Ja' : 'Nein',
  })),
)

async function fetchItems() {
  loading.value = true
  try {
    const { data } = await tagsApi.list({
      sort: sortField.value,
      order: sortOrder.value,
      perPage: meta.value.per_page,
      page: meta.value.current_page,
      search: searchTerm.value.trim() || undefined,
    })
    items.value = data.data || data
    if (data.meta) meta.value = data.meta
  } finally { loading.value = false }
}

let searchDebounce = null
function onSearchInput() {
  clearTimeout(searchDebounce)
  searchDebounce = setTimeout(() => {
    meta.value.current_page = 1
    fetchItems()
  }, 300)
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
      is_active: item.is_active !== false,
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
    // Leere Übersetzungen nicht mitschicken; ganz ohne Einträge NULL statt {}
    const translations = Object.fromEntries(
      Object.entries(payload.name_json || {}).filter(([, value]) => (value || '').trim() !== ''),
    )
    payload.name_json = Object.keys(translations).length ? translations : null
    // Technischer Name wird beim Anlegen serverseitig aus name_de abgeleitet
    if (!editId.value && !payload.technical_name.trim()) {
      delete payload.technical_name
    }
    if (editId.value) {
      await tagsApi.update(editId.value, payload)
    } else {
      await tagsApi.create(payload)
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
    await tagsApi.delete(deleteTarget.value.id, { force })
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
    <div class="flex items-center justify-between gap-3">
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Tags</h2>
      <div class="flex items-center gap-2">
        <div class="relative">
          <Search class="w-3.5 h-3.5 absolute left-2 top-1/2 -translate-y-1/2 text-[var(--color-text-tertiary)]" />
          <input
            v-model="searchTerm"
            class="pim-input text-xs pl-7 w-48"
            placeholder="Tags durchsuchen…"
            @input="onSearchInput"
          />
        </div>
        <button v-if="authStore.hasPermission('tags.create')" class="pim-btn pim-btn-primary" @click="openForm()">
          <Plus class="w-4 h-4" :stroke-width="2" /> Neuer Tag
        </button>
      </div>
    </div>

    <!-- Inline form -->
    <div v-if="showForm" class="pim-card p-4 space-y-3">
      <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">
        {{ editId ? 'Tag bearbeiten' : 'Neuer Tag' }}
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

      <!-- Weitere Sprachen (name_json) -->
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

      <label class="inline-flex items-center gap-2 cursor-pointer">
        <input type="checkbox" v-model="formData.is_active" class="w-3.5 h-3.5 accent-[var(--color-primary)]" />
        <span class="text-xs text-[var(--color-text-primary)]">Aktiv (inaktive Tags werden nicht mehr zur Vergabe angeboten)</span>
      </label>

      <div class="flex items-center gap-2 pt-1">
        <button class="pim-btn pim-btn-primary text-xs" :disabled="formSaving" @click="saveForm">
          {{ formSaving ? 'Speichern…' : 'Speichern' }}
        </button>
        <button class="pim-btn pim-btn-ghost text-xs" @click="showForm = false">Abbrechen</button>
      </div>
    </div>

    <PimTable
      :columns="columns"
      :rows="tableRows"
      :loading="loading"
      :sortField="sortField"
      :sortOrder="sortOrder"
      :showActions="authStore.hasPermission('tags.delete')"
      emptyText="Keine Tags vorhanden"
      @sort="handleSort"
      @row-click="openForm"
      @row-action="(row) => deleteTarget = row"
    >
      <template #pagination>
        <div class="flex items-center justify-between px-4 py-3 border-t border-[var(--color-border)]">
          <span class="text-xs text-[var(--color-text-tertiary)]">{{ meta.total }} Tags</span>
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
      title="Tag löschen?"
      :message="`Der Tag '${deleteTarget?.name_de || deleteTarget?.technical_name || ''}' wird unwiderruflich gelöscht.`"
      entityType="tags"
      :entityId="deleteTarget?.id"
      :loading="deleting"
      @confirm="confirmDelete"
      @cancel="deleteTarget = null"
    />
  </div>
</template>
