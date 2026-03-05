<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { attributeTypes } from '@/api/attributes'
import attributesApi from '@/api/attributes'
import PimForm from '@/components/shared/PimForm.vue'

const props = defineProps({
  attributeType: { type: Object, default: null },
  onSaved: { type: Function, default: null },
})

const authStore = useAuthStore()
const loading = ref(false)
const errors = ref({})
const assignedAttributes = ref([])
const loadingAttributes = ref(false)

const isEdit = computed(() => !!props.attributeType)

const formData = ref(
  props.attributeType
    ? {
        technical_name: props.attributeType.technical_name || '',
        name_de: props.attributeType.name_de || '',
        name_en: props.attributeType.name_en || '',
        description: props.attributeType.description || '',
        sort_order: props.attributeType.sort_order ?? 0,
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

onMounted(async () => {
  if (isEdit.value && props.attributeType?.id) {
    loadingAttributes.value = true
    try {
      const { data } = await attributesApi.list({
        filters: { attribute_type_id: props.attributeType.id },
        per_page: 200,
      })
      assignedAttributes.value = data.data || data
    } catch (e) { /* ignore */ }
    finally { loadingAttributes.value = false }
  }
})

async function handleSubmit(data) {
  loading.value = true
  errors.value = {}
  try {
    if (isEdit.value) {
      await attributeTypes.update(props.attributeType.id, data)
    } else {
      await attributeTypes.create(data)
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
      {{ isEdit ? 'Attributgruppe bearbeiten' : 'Neue Attributgruppe' }}
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
      <h4 class="text-xs font-semibold text-[var(--color-text-secondary)] mb-2">
        Zugeordnete Attribute
        <span v-if="!loadingAttributes" class="text-[var(--color-text-tertiary)] font-normal">({{ assignedAttributes.length }})</span>
      </h4>
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
          <span class="pim-badge bg-[var(--color-bg)] text-[var(--color-text-tertiary)] shrink-0">{{ attr.data_type }}</span>
        </div>
      </div>
    </div>
  </div>
</template>
