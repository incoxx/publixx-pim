<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { attributeViews } from '@/api/attributes'
import attributesApi from '@/api/attributes'
import { Plus, X } from 'lucide-vue-next'
import PimForm from '@/components/shared/PimForm.vue'

const props = defineProps({
  attributeView: { type: Object, default: null },
  onSaved: { type: Function, default: null },
})

const authStore = useAuthStore()
const loading = ref(false)
const errors = ref({})
const assignedAttributes = ref([])
const loadingAttributes = ref(false)

// For attribute assignment
const allAttributes = ref([])
const loadingAllAttributes = ref(false)
const showAddAttribute = ref(false)
const selectedAttributeId = ref('')
const addingAttribute = ref(false)

const isEdit = computed(() => !!props.attributeView)

const formData = ref(
  props.attributeView
    ? {
        technical_name: props.attributeView.technical_name || '',
        name_de: props.attributeView.name_de || '',
        name_en: props.attributeView.name_en || '',
        description: props.attributeView.description || '',
        sort_order: props.attributeView.sort_order ?? 0,
      }
    : {
        technical_name: '',
        name_de: '',
        name_en: '',
        description: '',
        sort_order: 0,
      }
)

const fields = computed(() => [
  { key: 'technical_name', label: 'Technischer Name', type: 'text', required: true, disabled: isEdit.value },
  { key: 'name_de', label: 'Name (DE)', type: 'text', required: true },
  { key: 'name_en', label: 'Name (EN)', type: 'text' },
  { key: 'description', label: 'Beschreibung', type: 'textarea' },
  { key: 'sort_order', label: 'Sortierung', type: 'number' },
])

const assignedIds = computed(() => new Set(assignedAttributes.value.map(a => a.id)))

const availableAttributes = computed(() =>
  allAttributes.value.filter(a => !assignedIds.value.has(a.id))
)

onMounted(async () => {
  if (isEdit.value && props.attributeView?.id) {
    loadingAttributes.value = true
    try {
      // Load view with its attributes
      const { data } = await attributeViews.list({ include: 'attributes' })
      const views = data.data || data
      const thisView = views.find(v => v.id === props.attributeView.id)
      assignedAttributes.value = thisView?.attributes || []
    } catch (e) { /* ignore */ }
    finally { loadingAttributes.value = false }
  }
})

async function loadAllAttributes() {
  if (allAttributes.value.length > 0) return
  loadingAllAttributes.value = true
  try {
    const { data } = await attributesApi.list({ perPage: 500 })
    allAttributes.value = data.data || data
  } catch (e) { /* ignore */ }
  finally { loadingAllAttributes.value = false }
}

async function addAttribute() {
  if (!selectedAttributeId.value) return
  addingAttribute.value = true
  try {
    await attributeViews.addAttribute(props.attributeView.id, {
      attribute_id: selectedAttributeId.value,
    })
    // Add to local list
    const attr = allAttributes.value.find(a => a.id === selectedAttributeId.value)
    if (attr) assignedAttributes.value.push(attr)
    selectedAttributeId.value = ''
  } catch (e) { /* ignore */ }
  finally { addingAttribute.value = false }
}

async function removeAttribute(attrId) {
  try {
    await attributeViews.removeAttribute(props.attributeView.id, attrId)
    assignedAttributes.value = assignedAttributes.value.filter(a => a.id !== attrId)
  } catch (e) { /* ignore */ }
}

async function handleSubmit(data) {
  loading.value = true
  errors.value = {}
  try {
    if (isEdit.value) {
      await attributeViews.update(props.attributeView.id, data)
    } else {
      await attributeViews.create(data)
    }
    authStore.closePanel()
    if (props.onSaved) props.onSaved()
  } catch (e) {
    if (e.response?.status === 422) {
      const serverErrors = e.response.data.errors || {}
      for (const [key, val] of Object.entries(serverErrors)) {
        errors.value[key] = Array.isArray(val) ? val[0] : val
      }
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="p-4 space-y-6">
    <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">
      {{ isEdit ? 'Attribut-Sicht bearbeiten' : 'Neue Attribut-Sicht' }}
    </h3>
    <PimForm
      :fields="fields"
      :modelValue="formData"
      :errors="errors"
      :loading="loading"
      @update:modelValue="formData = $event"
      @submit="handleSubmit"
      @cancel="authStore.closePanel()"
    />

    <!-- Zugeordnete Attribute -->
    <div v-if="isEdit" class="border-t border-[var(--color-border)] pt-4">
      <div class="flex items-center justify-between mb-2">
        <h4 class="text-xs font-semibold text-[var(--color-text-secondary)]">
          Zugeordnete Attribute
          <span v-if="!loadingAttributes" class="text-[var(--color-text-tertiary)] font-normal">({{ assignedAttributes.length }})</span>
        </h4>
        <button
          class="pim-btn pim-btn-ghost pim-btn-xs"
          @click="showAddAttribute = !showAddAttribute; if (showAddAttribute) loadAllAttributes()"
        >
          <Plus class="w-3 h-3" :stroke-width="2" />
          Zuordnen
        </button>
      </div>

      <!-- Add attribute selector -->
      <div v-if="showAddAttribute" class="flex gap-1 mb-3">
        <select
          v-model="selectedAttributeId"
          class="pim-input text-xs flex-1"
          :disabled="loadingAllAttributes"
        >
          <option value="">{{ loadingAllAttributes ? 'Laden…' : '— Attribut wählen —' }}</option>
          <option v-for="attr in availableAttributes" :key="attr.id" :value="attr.id">
            {{ attr.technical_name }} — {{ attr.name_de || '' }}
          </option>
        </select>
        <button
          class="pim-btn pim-btn-primary pim-btn-xs"
          :disabled="!selectedAttributeId || addingAttribute"
          @click="addAttribute"
        >
          OK
        </button>
      </div>

      <div v-if="loadingAttributes" class="space-y-2">
        <div v-for="i in 3" :key="i" class="pim-skeleton h-6 rounded" />
      </div>
      <div v-else-if="assignedAttributes.length === 0" class="text-xs text-[var(--color-text-tertiary)] italic">
        Keine Attribute zugeordnet.
      </div>
      <div v-else class="space-y-1 max-h-[300px] overflow-y-auto">
        <div
          v-for="attr in assignedAttributes"
          :key="attr.id"
          class="flex items-center justify-between p-2 rounded-lg bg-[var(--color-bg)] text-xs"
        >
          <div class="flex items-center gap-2 min-w-0">
            <span class="font-mono text-[var(--color-accent)] truncate">{{ attr.technical_name }}</span>
            <span class="text-[var(--color-text-secondary)] truncate">{{ attr.name_de }}</span>
          </div>
          <div class="flex items-center gap-1.5 shrink-0">
            <span class="pim-badge bg-[var(--color-bg)] text-[var(--color-text-tertiary)]">{{ attr.data_type }}</span>
            <button
              class="p-0.5 rounded hover:bg-[var(--color-danger-light)] text-[var(--color-text-tertiary)] hover:text-[var(--color-danger)] transition-colors"
              title="Zuordnung entfernen"
              @click.stop="removeAttribute(attr.id)"
            >
              <X class="w-3 h-3" :stroke-width="2" />
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
