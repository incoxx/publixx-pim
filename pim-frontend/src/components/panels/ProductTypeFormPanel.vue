<script setup>
import { ref, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { productTypes } from '@/api/attributes'
import PimForm from '@/components/shared/PimForm.vue'

const props = defineProps({
  productType: { type: Object, default: null },
  onSaved: { type: Function, default: null },
})

const authStore = useAuthStore()
const loading = ref(false)
const errors = ref({})

const isEdit = computed(() => !!props.productType)

const formData = ref(
  props.productType
    ? {
        technical_name: props.productType.technical_name || '',
        name_de: props.productType.name_de || '',
        name_en: props.productType.name_en || '',
        description: props.productType.description || '',
        has_variants: props.productType.has_variants ?? false,
        has_ean: props.productType.has_ean ?? true,
        has_prices: props.productType.has_prices ?? true,
        has_media: props.productType.has_media ?? true,
      }
    : {
        technical_name: '',
        name_de: '',
        name_en: '',
        description: '',
        has_variants: false,
        has_ean: true,
        has_prices: true,
        has_media: true,
      }
)

const fields = computed(() => [
  { key: 'technical_name', label: 'Technischer Name', type: 'text', required: true, disabled: isEdit.value },
  { key: 'name_de', label: 'Name (DE)', type: 'text', required: true },
  { key: 'name_en', label: 'Name (EN)', type: 'text' },
  { key: 'description', label: 'Beschreibung', type: 'textarea' },
  { key: 'has_variants', label: 'Varianten', type: 'boolean' },
  { key: 'has_ean', label: 'EAN', type: 'boolean' },
  { key: 'has_prices', label: 'Preise', type: 'boolean' },
  { key: 'has_media', label: 'Medien', type: 'boolean' },
])

async function handleSubmit(data) {
  loading.value = true
  errors.value = {}
  try {
    if (isEdit.value) {
      await productTypes.update(props.productType.id, data)
    } else {
      await productTypes.create(data)
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
