<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useLicenseStore } from '@/stores/license'
import { productTypes } from '@/api/attributes'
import workflowsApi from '@/api/workflows'
import PimForm from '@/components/shared/PimForm.vue'

const props = defineProps({
  productType: { type: Object, default: null },
  onSaved: { type: Function, default: null },
})

const authStore = useAuthStore()
const licenseStore = useLicenseStore()
const loading = ref(false)
const errors = ref({})
const workflows = ref([])

const isEdit = computed(() => !!props.productType)

const formData = ref(
  props.productType
    ? {
        technical_name: props.productType.technical_name || '',
        name_de: props.productType.name_de || '',
        name_en: props.productType.name_en || '',
        description: props.productType.description || '',
        icon: props.productType.icon || '',
        color: props.productType.color || '',
        sort_order: props.productType.sort_order ?? 0,
        is_active: props.productType.is_active ?? true,
        has_variants: props.productType.has_variants ?? false,
        has_ean: props.productType.has_ean ?? true,
        has_prices: props.productType.has_prices ?? true,
        has_media: props.productType.has_media ?? true,
        has_stock: props.productType.has_stock ?? false,
        has_physical_dimensions: props.productType.has_physical_dimensions ?? false,
        workflow_id: props.productType.workflow_id || '',
      }
    : {
        technical_name: '',
        name_de: '',
        name_en: '',
        description: '',
        icon: '',
        color: '',
        sort_order: 0,
        is_active: true,
        has_variants: false,
        has_ean: true,
        has_prices: true,
        has_media: true,
        has_stock: false,
        has_physical_dimensions: false,
        workflow_id: '',
      }
)

const workflowModuleActive = computed(() => licenseStore.isModuleActive('workflow'))

const fields = computed(() => {
  const base = [
    // Grunddaten
    { key: 'technical_name', label: 'Technischer Name', type: 'text', required: true, disabled: isEdit.value },
    { key: 'name_de', label: 'Name (DE)', type: 'text', required: true },
    { key: 'name_en', label: 'Name (EN)', type: 'text' },
    { key: 'description', label: 'Beschreibung', type: 'textarea' },
    // Darstellung
    { key: 'icon', label: 'Icon (Lucide Name)', type: 'text' },
    { key: 'color', label: 'Farbe (Hex)', type: 'text', placeholder: '#FF5733' },
    { key: 'sort_order', label: 'Sortierung', type: 'number' },
    { key: 'is_active', label: 'Aktiv', type: 'boolean' },
    // Feature-Flags
    { key: 'has_variants', label: 'Varianten', type: 'boolean' },
    { key: 'has_ean', label: 'EAN', type: 'boolean' },
    { key: 'has_prices', label: 'Preise', type: 'boolean' },
    { key: 'has_media', label: 'Medien', type: 'boolean' },
    { key: 'has_stock', label: 'Lagerbestand', type: 'boolean' },
    { key: 'has_physical_dimensions', label: 'Physische Maße', type: 'boolean' },
  ]

  // Workflow dropdown only visible with Enterprise license
  if (workflowModuleActive.value) {
    base.push({
      key: 'workflow_id',
      label: 'Workflow',
      type: 'select',
      options: [
        { value: '', label: 'Kein Workflow' },
        ...workflows.value.map(w => ({ value: w.id, label: w.name })),
      ],
    })
  }

  return base
})

async function loadWorkflows() {
  if (!workflowModuleActive.value) return
  try {
    const { data } = await workflowsApi.list({ perPage: 200 })
    workflows.value = (data.data || data).filter(w => w.is_active !== false)
  } catch { /* ignore */ }
}

onMounted(() => {
  loadWorkflows()
})

async function handleSubmit(data) {
  loading.value = true
  errors.value = {}
  const payload = { ...data, workflow_id: data.workflow_id || null }
  try {
    if (isEdit.value) {
      await productTypes.update(props.productType.id, payload)
    } else {
      await productTypes.create(payload)
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
  <div class="p-4">
    <h3 class="text-sm font-semibold text-[var(--color-text-primary)] mb-4">
      {{ isEdit ? 'Produkttyp bearbeiten' : 'Neuer Produkttyp' }}
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
  </div>
</template>
