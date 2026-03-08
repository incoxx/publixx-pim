<script setup>
import { ref, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { relationTypes } from '@/api/prices'
import PimForm from '@/components/shared/PimForm.vue'

const props = defineProps({
  relationType: { type: Object, default: null },
  onSaved: { type: Function, default: null },
})

const authStore = useAuthStore()
const loading = ref(false)
const errors = ref({})

const isEdit = computed(() => !!props.relationType)

const formData = ref(
  props.relationType
    ? {
        technical_name: props.relationType.technical_name || '',
        name_de: props.relationType.name_de || '',
        name_en: props.relationType.name_en || '',
        is_bidirectional: props.relationType.is_bidirectional ?? false,
      }
    : {
        technical_name: '',
        name_de: '',
        name_en: '',
        is_bidirectional: false,
      }
)

const fields = computed(() => [
  { key: 'technical_name', label: 'Technischer Name', type: 'text', required: true, disabled: isEdit.value },
  { key: 'name_de', label: 'Name (DE)', type: 'text', required: true },
  { key: 'name_en', label: 'Name (EN)', type: 'text' },
  { key: 'is_bidirectional', label: 'Bidirektional', type: 'boolean' },
])

async function handleSubmit(data) {
  loading.value = true
  errors.value = {}
  try {
    if (isEdit.value) {
      await relationTypes.update(props.relationType.id, data)
    } else {
      await relationTypes.create(data)
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
      {{ isEdit ? 'Beziehungstyp bearbeiten' : 'Neuer Beziehungstyp' }}
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
