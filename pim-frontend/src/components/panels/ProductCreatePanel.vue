<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useProductStore } from '@/stores/products'
import { useAuthStore } from '@/stores/auth'
import PimForm from '@/components/shared/PimForm.vue'

const props = defineProps({
  productTypes: { type: Array, default: () => [] },
})

const router = useRouter()
const store = useProductStore()
const authStore = useAuthStore()
const loading = ref(false)
const errors = ref({})

const formData = ref({
  sku: '',
  name: '',
  product_type_id: '',
  ean: '',
  status: 'draft',
})

const fields = computed(() => [
  { key: 'sku', label: 'SKU / Artikelnummer', type: 'text', required: true },
  { key: 'name', label: 'Name', type: 'text', required: true },
  {
    key: 'product_type_id', label: 'Produkttyp', type: 'select', required: true,
    options: props.productTypes.map(t => ({ value: t.id, label: t.name_de || t.technical_name })),
  },
  { key: 'ean', label: 'EAN', type: 'text' },
  {
    key: 'status', label: 'Status', type: 'select',
    options: [
      { value: 'draft', label: 'Entwurf' },
      { value: 'active', label: 'Aktiv' },
      { value: 'inactive', label: 'Inaktiv' },
      { value: 'discontinued', label: 'Auslaufend' },
    ],
  },
])

async function handleSubmit(data) {
  loading.value = true
  errors.value = {}
  try {
    const result = await store.create(data)
    authStore.closePanel()
    if (result?.id) {
      router.push(`/products/${result.id}`)
    }
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
    <h3 class="text-sm font-semibold text-[var(--color-text-primary)] mb-4">Neues Produkt</h3>
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
