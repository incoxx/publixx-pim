<script setup>
import { ref, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { attributeTypes } from '@/api/attributes'
import PimForm from '@/components/shared/PimForm.vue'

const props = defineProps({
  attributeType: { type: Object, default: null },
  onSaved: { type: Function, default: null },
})

const authStore = useAuthStore()
const loading = ref(false)
const errors = ref({})

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
  <div class="p-4">
    <h3 class="text-sm font-semibold text-[var(--color-text-primary)] mb-4">
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
  </div>
</template>
