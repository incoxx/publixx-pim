<script setup>
import { ref, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import hierarchiesApi from '@/api/hierarchies'
import PimForm from '@/components/shared/PimForm.vue'

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
      if (e.response.data.message && !Object.keys(serverErrors).length) {
        errors.value._general = e.response.data.message
      }
    } else if (e.response?.status === 403) {
      errors.value._general = 'Keine Berechtigung für diese Aktion.'
    } else {
      errors.value._general = e.response?.data?.message || 'Ein Fehler ist aufgetreten.'
    }
  } finally {
    loading.value = false
  }
}
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
  </div>
</template>
