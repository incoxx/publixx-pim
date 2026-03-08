<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useDebounceFn } from '@vueuse/core'
import { useAuthStore } from '@/stores/auth'
import hierarchiesApi from '@/api/hierarchies'
import attributesApi from '@/api/attributes'
import PimForm from '@/components/shared/PimForm.vue'
import { Plus, Trash2, Search, ChevronUp, ChevronDown } from 'lucide-vue-next'

const props = defineProps({
  hierarchy: { type: Object, default: null },
  onSaved: { type: Function, default: null },
})

const authStore = useAuthStore()
const loading = ref(false)
const errors = ref({})

const isEdit = computed(() => !!props.hierarchy)

const formData = ref(
  props.hierarchy
    ? { ...props.hierarchy }
    : {
        technical_name: '',
        name_de: '',
        name_en: '',
        hierarchy_type: 'master',
        description: '',
      }
)

const fields = computed(() => [
  { key: 'technical_name', label: 'Technischer Name', type: 'text', required: true, disabled: isEdit.value },
  { key: 'name_de', label: 'Name (DE)', type: 'text', required: true },
  { key: 'name_en', label: 'Name (EN)', type: 'text' },
  {
    key: 'hierarchy_type', label: 'Typ', type: 'select', required: true,
    disabled: isEdit.value,
    options: [
      { value: 'master', label: 'Master' },
      { value: 'output', label: 'Output' },
      { value: 'asset', label: 'Asset' },
    ],
  },
  { key: 'description', label: 'Beschreibung', type: 'textarea' },
])

async function handleSubmit(data) {
  loading.value = true
  errors.value = {}
  try {
    let result
    if (isEdit.value) {
      const { data: resp } = await hierarchiesApi.update(props.hierarchy.id, data)
      result = resp.data || resp
    } else {
      const { data: resp } = await hierarchiesApi.create(data)
      result = resp.data || resp
    }
    authStore.closePanel()
    if (props.onSaved) props.onSaved(result)
  } catch (e) {
    if (e.response?.status === 422) {
      const serverErrors = e.response.data.errors || {}
      for (const [key, val] of Object.entries(serverErrors)) {
        errors.value[key] = Array.isArray(val) ? val[0] : val
      }
      if (e.response.data.title && !Object.keys(serverErrors).length) {
        errors.value._general = e.response.data.title
      }
    } else if (e.response?.status === 403) {
      errors.value._general = 'Keine Berechtigung für diese Aktion.'
    } else {
      errors.value._general = e.response?.data?.title || 'Ein Fehler ist aufgetreten.'
    }
  } finally {
    loading.value = false
  }
}

// ─── Hierarchy-level attribute assignments (edit mode only) ─────
const assignedAttrs = ref([])
const assignedLoading = ref(false)
const showPicker = ref(false)
const pickerSearch = ref('')
const pickerItems = ref([])
const pickerLoading = ref(false)
const pickerMeta = ref({ current_page: 1, last_page: 1, total: 0, per_page: 20 })

async function loadAssignedAttributes() {
  if (!props.hierarchy?.id) return
  assignedLoading.value = true
  try {
    const { data } = await hierarchiesApi.getHierarchyAttributes(props.hierarchy.id)
    assignedAttrs.value = data.data || data
  } catch {
    assignedAttrs.value = []
  } finally {
    assignedLoading.value = false
  }
}

async function fetchPickerAttributes(page = 1) {
  pickerLoading.value = true
  try {
    const assignedIds = new Set(assignedAttrs.value.map(a => a.attribute?.id || a.attribute_id))
    const { data } = await attributesApi.list({
      search: pickerSearch.value || undefined,
      page,
      perPage: 20,
      sort: 'name_de',
      order: 'asc',
    })
    const items = data.data || data
    pickerItems.value = items.filter(a => !assignedIds.has(a.id) && !a.parent_attribute_id)
    pickerMeta.value = data.meta || { current_page: page, last_page: 1, total: items.length, per_page: 20 }
  } catch {
    pickerItems.value = []
  } finally {
    pickerLoading.value = false
  }
}

const debouncedPickerSearch = useDebounceFn(() => fetchPickerAttributes(1), 300)

async function assignAttribute(attr) {
  try {
    await hierarchiesApi.assignHierarchyAttribute(props.hierarchy.id, { attribute_id: attr.id })
    await loadAssignedAttributes()
    await fetchPickerAttributes(pickerMeta.value.current_page)
  } catch { /* ignore */ }
}

async function removeAttribute(assignment) {
  try {
    await hierarchiesApi.removeHierarchyAttribute(assignment.id)
    await loadAssignedAttributes()
  } catch { /* ignore */ }
}

watch(showPicker, (open) => {
  if (open) {
    pickerSearch.value = ''
    fetchPickerAttributes(1)
  }
})

onMounted(() => {
  if (isEdit.value) loadAssignedAttributes()
})
</script>

<template>
  <div class="p-4">
    <h3 class="text-sm font-semibold text-[var(--color-text-primary)] mb-4">
      {{ isEdit ? 'Hierarchie bearbeiten' : 'Neue Hierarchie' }}
    </h3>
    <p v-if="errors._general" class="mb-3 text-[12px] text-[var(--color-error)] bg-[var(--color-error-light)] px-3 py-2 rounded-lg">
      {{ errors._general }}
    </p>
    <PimForm
      :fields="fields"
      :modelValue="formData"
      :errors="errors"
      :loading="loading"
      @update:modelValue="formData = $event"
      @submit="handleSubmit"
      @cancel="authStore.closePanel()"
    />

    <!-- Attribute assignments (edit mode only) -->
    <template v-if="isEdit">
      <div class="mt-6 pt-4 border-t border-[var(--color-border)]">
        <div class="flex items-center justify-between mb-3">
          <h4 class="text-sm font-medium text-[var(--color-text-secondary)]">Zugeordnete Attribute</h4>
          <button class="pim-btn pim-btn-secondary text-xs" @click="showPicker = !showPicker">
            <Plus class="w-3 h-3" :stroke-width="2" /> Attribut zuordnen
          </button>
        </div>

        <!-- Attribute picker -->
        <div v-if="showPicker" class="mb-3 p-3 bg-[var(--color-bg)] rounded-lg space-y-2">
          <div class="relative">
            <Search class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
            <input
              v-model="pickerSearch"
              class="pim-input text-xs w-full pl-8"
              placeholder="Attribut suchen…"
              @input="debouncedPickerSearch"
              @keyup.escape="showPicker = false"
            />
          </div>
          <div v-if="pickerLoading" class="space-y-1">
            <div v-for="i in 4" :key="i" class="pim-skeleton h-7 rounded" />
          </div>
          <div v-else-if="pickerItems.length > 0" class="max-h-64 overflow-y-auto space-y-1">
            <div
              v-for="attr in pickerItems"
              :key="attr.id"
              class="flex items-center justify-between px-2 py-1.5 rounded hover:bg-[var(--color-surface)] cursor-pointer"
              @click="assignAttribute(attr)"
            >
              <div class="flex items-center gap-2 min-w-0">
                <span class="text-xs font-medium truncate">{{ attr.name_de || attr.technical_name }}</span>
                <span v-if="attr.name_de && attr.technical_name" class="text-[10px] text-[var(--color-text-tertiary)] font-mono truncate">{{ attr.technical_name }}</span>
              </div>
              <span class="text-[10px] text-[var(--color-text-tertiary)] shrink-0 ml-2">{{ attr.data_type }}</span>
            </div>
          </div>
          <p v-else class="text-xs text-[var(--color-text-tertiary)]">Keine Attribute gefunden</p>
          <!-- Pagination -->
          <div v-if="pickerMeta.last_page > 1" class="flex items-center justify-between pt-1 border-t border-[var(--color-border)]">
            <span class="text-[11px] text-[var(--color-text-tertiary)]">
              Seite {{ pickerMeta.current_page }} von {{ pickerMeta.last_page }}
              ({{ pickerMeta.total }} gesamt)
            </span>
            <div class="flex items-center gap-1">
              <button
                class="pim-btn pim-btn-ghost p-1 text-xs disabled:opacity-30"
                :disabled="pickerMeta.current_page <= 1"
                @click="fetchPickerAttributes(pickerMeta.current_page - 1)"
              >
                <ChevronUp class="w-3.5 h-3.5 -rotate-90" :stroke-width="2" />
              </button>
              <button
                class="pim-btn pim-btn-ghost p-1 text-xs disabled:opacity-30"
                :disabled="pickerMeta.current_page >= pickerMeta.last_page"
                @click="fetchPickerAttributes(pickerMeta.current_page + 1)"
              >
                <ChevronDown class="w-3.5 h-3.5 -rotate-90" :stroke-width="2" />
              </button>
            </div>
          </div>
        </div>

        <!-- Assigned list -->
        <div v-if="assignedLoading" class="space-y-2">
          <div v-for="i in 3" :key="i" class="pim-skeleton h-8 rounded" />
        </div>
        <template v-else-if="assignedAttrs.length > 0">
          <div class="space-y-1">
            <div
              v-for="assignment in assignedAttrs"
              :key="assignment.id"
              class="flex items-center justify-between px-3 py-2 rounded-lg bg-[var(--color-bg)] group"
            >
              <div class="flex items-center gap-2">
                <span class="text-xs font-medium">{{ assignment.attribute?.name_de || assignment.attribute?.technical_name || '—' }}</span>
                <span class="text-[10px] text-[var(--color-text-tertiary)]">{{ assignment.attribute?.data_type }}</span>
              </div>
              <button
                class="opacity-0 group-hover:opacity-100 p-0.5 rounded hover:bg-[var(--color-error-light)] text-[var(--color-text-tertiary)] hover:text-[var(--color-error)] transition-all"
                @click="removeAttribute(assignment)"
                title="Entfernen"
              >
                <Trash2 class="w-3.5 h-3.5" :stroke-width="2" />
              </button>
            </div>
          </div>
          <p class="text-[11px] text-[var(--color-text-tertiary)] mt-1">{{ assignedAttrs.length }} Attribute zugeordnet</p>
        </template>
        <p v-else class="text-xs text-[var(--color-text-tertiary)]">Keine Attribute zugeordnet.</p>
      </div>
    </template>
  </div>
</template>
