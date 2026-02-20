<script setup>
import { ref, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { valueLists } from '@/api/attributes'
import PimForm from '@/components/shared/PimForm.vue'

const props = defineProps({
  valueList: { type: Object, default: null },
  onSaved: { type: Function, default: null },
})

const authStore = useAuthStore()
const loading = ref(false)
const errors = ref({})

const isEdit = computed(() => !!props.valueList)

const formData = ref(
  props.valueList
    ? {
        technical_name: props.valueList.technical_name || '',
        name_de: props.valueList.name_de || '',
        name_en: props.valueList.name_en || '',
        description: props.valueList.description || '',
        value_data_type: props.valueList.value_data_type || 'String',
      }
    : {
        technical_name: '',
        name_de: '',
        name_en: '',
        description: '',
        value_data_type: 'String',
      }
)

const fields = computed(() => [
  { key: 'technical_name', label: 'Technischer Name', type: 'text', required: true, disabled: isEdit.value },
  { key: 'name_de', label: 'Name (DE)', type: 'text', required: true },
  { key: 'name_en', label: 'Name (EN)', type: 'text' },
  { key: 'value_data_type', label: 'Datentyp', type: 'select', options: [{ value: 'String', label: 'String' }, { value: 'Number', label: 'Number' }] },
  { key: 'description', label: 'Beschreibung', type: 'textarea' },
])

async function handleSubmit(data) {
  loading.value = true
  errors.value = {}
  try {
    if (isEdit.value) {
      await valueLists.update(props.valueList.id, data)
    } else {
      await valueLists.create(data)
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
      {{ isEdit ? 'Werteliste bearbeiten' : 'Neue Werteliste' }}
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
