<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { Plus, X, Save, Check, AlertCircle } from 'lucide-vue-next'
import { mediaUsageTypes } from '@/api/mediaUsageTypes'
import { useAuthStore } from '@/stores/auth'
import { useAttributeStore } from '@/stores/attributes'
import PimTable from '@/components/shared/PimTable.vue'
import PimDeleteConfirmDialog from '@/components/shared/PimDeleteConfirmDialog.vue'

const authStore = useAuthStore()
const attributeStore = useAttributeStore()

const items = ref([])
const loading = ref(false)
const deleteTarget = ref(null)
const deleting = ref(false)
const showForm = ref(false)
const editId = ref(null)
const formData = ref({ technical_name: '', name_de: '', name_en: '', sort_order: 0, allowed_extensions: null, restricted_display_mode: 'locked' })
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
      restricted_display_mode: item.restricted_display_mode || 'locked',
    }
    allExtensionsAllowed.value = item.allowed_extensions === null
  } else {
    editId.value = null
    formData.value = { technical_name: '', name_de: '', name_en: '', sort_order: 0, allowed_extensions: null, restricted_display_mode: 'locked' }
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

// ─── Default Attributes ─────────────────────────
const defaultAttributes = ref([])
const newAttrId = ref('')
const savingAttributes = ref(false)
const saveAttrSuccess = ref(false)
const saveAttrError = ref('')

const allAttributes = computed(() => attributeStore.attributes || [])

const availableAttributes = computed(() => {
  const usedIds = new Set(defaultAttributes.value.map(a => a.id))
  return allAttributes.value.filter(a => !usedIds.has(a.id))
})

watch(editId, (newId) => {
  if (newId) {
    const item = items.value.find(i => i.id === newId)
    defaultAttributes.value = (item?.default_attributes || []).map(a => ({ ...a }))
  } else {
    defaultAttributes.value = []
  }
  saveAttrSuccess.value = false
  saveAttrError.value = ''
})

function addDefaultAttribute() {
  if (!newAttrId.value) return
  const attr = allAttributes.value.find(a => a.id === newAttrId.value)
  if (!attr || defaultAttributes.value.some(d => d.id === attr.id)) return
  defaultAttributes.value.push({
    id: attr.id,
    technical_name: attr.technical_name,
    name_de: attr.name_de,
    name_en: attr.name_en,
    data_type: attr.data_type,
  })
  newAttrId.value = ''
}

function removeDefaultAttribute(index) {
  defaultAttributes.value.splice(index, 1)
}

async function saveDefaultAttributes() {
  if (!editId.value) return
  savingAttributes.value = true
  saveAttrSuccess.value = false
  saveAttrError.value = ''
  try {
    const payload = defaultAttributes.value.map((a, idx) => ({
      attribute_id: a.id,
      sort_order: idx,
    }))
    const { data } = await mediaUsageTypes.updateDefaultAttributes(editId.value, payload)
    const updated = data.data || data
    defaultAttributes.value = (updated.default_attributes || []).map(a => ({ ...a }))
    // Update the item in the list too
    const itemIdx = items.value.findIndex(i => i.id === editId.value)
    if (itemIdx >= 0) {
      items.value[itemIdx].default_attributes = updated.default_attributes || []
    }
    saveAttrSuccess.value = true
    setTimeout(() => { saveAttrSuccess.value = false }, 3000)
  } catch (e) {
    saveAttrError.value = e.response?.data?.message || e.message || 'Fehler beim Speichern'
  } finally {
    savingAttributes.value = false
  }
}

onMounted(() => {
  fetchItems()
  attributeStore.fetchAttributes()
})
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

      <!-- Rechteverwaltung: Verhalten bei fehlendem Rollen-Zugriff -->
      <div>
        <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">
          Verhalten bei fehlendem Zugriff (Rollen-Einschränkung)
        </label>
        <select v-model="formData.restricted_display_mode" class="pim-select text-xs w-64">
          <option value="locked">Sichtbar, Download gesperrt</option>
          <option value="hidden">Vollständig ausblenden</option>
        </select>
        <p class="text-[11px] text-[var(--color-text-tertiary)] mt-1">
          Greift nur, wenn eine Rolle über die Rollenverwaltung auf diesen Bildtyp eingeschränkt wurde.
          Ohne Einschränkung haben alle Rollen Zugriff.
        </p>
      </div>

      <div class="flex gap-2">
        <button class="pim-btn pim-btn-primary text-xs" :disabled="formSaving" @click="saveForm">
          {{ formSaving ? 'Speichern…' : 'Speichern' }}
        </button>
        <button class="pim-btn pim-btn-secondary text-xs" @click="showForm = false">Abbrechen</button>
      </div>

      <!-- Default Attributes (nur im Edit-Modus) -->
      <div v-if="editId" class="border-t border-[var(--color-border)] pt-4 mt-4 space-y-3">
        <h4 class="text-sm font-semibold text-[var(--color-text-primary)]">
          Standard-Attribute für diesen Bildtyp
        </h4>
        <p class="text-[11px] text-[var(--color-text-tertiary)]">
          Diese Attribute können bei Medien gepflegt werden, die diesem Bildtyp zugeordnet sind (z.B. Fotograf, Lizenzlaufzeit).
        </p>

        <!-- Zugeordnete Attribute -->
        <div v-if="defaultAttributes.length > 0" class="space-y-1.5">
          <div
            v-for="(attr, index) in defaultAttributes"
            :key="attr.id"
            class="flex items-center gap-3 px-3 py-2 rounded-lg bg-[var(--color-bg)] border border-[var(--color-border)]/50"
          >
            <span class="text-xs font-mono text-[var(--color-text-tertiary)]">{{ attr.technical_name }}</span>
            <span class="text-xs text-[var(--color-text-primary)] flex-1">{{ attr.name_de || attr.name_en || '—' }}</span>
            <span class="pim-badge text-[9px] bg-[var(--color-surface)] text-[var(--color-text-tertiary)]">{{ attr.data_type }}</span>
            <button
              class="p-1 rounded hover:bg-[var(--color-error)]/10 text-[var(--color-text-tertiary)] hover:text-[var(--color-error)] transition-colors"
              @click="removeDefaultAttribute(index)"
              title="Entfernen"
            >
              <X class="w-3.5 h-3.5" :stroke-width="2" />
            </button>
          </div>
        </div>
        <p v-else class="text-[11px] text-[var(--color-text-tertiary)] italic">Keine Attribute zugeordnet</p>

        <!-- Attribut hinzufügen -->
        <div class="flex items-center gap-2">
          <select v-model="newAttrId" class="pim-select text-xs flex-1">
            <option value="">— Attribut auswählen —</option>
            <option v-for="attr in availableAttributes" :key="attr.id" :value="attr.id">
              {{ attr.name_de || attr.technical_name }} ({{ attr.data_type }})
            </option>
          </select>
          <button class="pim-btn pim-btn-ghost pim-btn-sm" :disabled="!newAttrId" @click="addDefaultAttribute">
            <Plus class="w-3.5 h-3.5" :stroke-width="2" /> Hinzufügen
          </button>
        </div>

        <!-- Speichern -->
        <div class="flex items-center gap-3">
          <button class="pim-btn pim-btn-accent text-xs" :disabled="savingAttributes" @click="saveDefaultAttributes">
            <Save v-if="!savingAttributes" class="w-3.5 h-3.5" :stroke-width="2" />
            {{ savingAttributes ? 'Speichern…' : 'Attribute speichern' }}
          </button>
          <span v-if="saveAttrSuccess" class="flex items-center gap-1 text-xs text-[var(--color-success)]">
            <Check class="w-3.5 h-3.5" :stroke-width="2" /> Gespeichert
          </span>
          <span v-if="saveAttrError" class="flex items-center gap-1 text-xs text-[var(--color-error)]">
            <AlertCircle class="w-3.5 h-3.5" :stroke-width="2" /> {{ saveAttrError }}
          </span>
        </div>
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
