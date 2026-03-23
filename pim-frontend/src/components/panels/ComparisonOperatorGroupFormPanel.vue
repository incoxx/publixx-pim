<script setup>
import { ref, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { comparisonOperatorGroups } from '@/api/comparisonOperators'
import PimForm from '@/components/shared/PimForm.vue'

const props = defineProps({
  group: { type: Object, default: null },
  onSaved: { type: Function, default: null },
})

const authStore = useAuthStore()
const loading = ref(false)
const errors = ref({})

const isEdit = computed(() => !!props.group)

const formData = ref(
  props.group
    ? {
        technical_name: props.group.technical_name || '',
        name_de: props.group.name_de || '',
        name_en: props.group.name_en || '',
      }
    : {
        technical_name: '',
        name_de: '',
        name_en: '',
      }
)

const fields = computed(() => [
  { key: 'technical_name', label: 'Technischer Name', type: 'text', required: true, disabled: isEdit.value },
  { key: 'name_de', label: 'Name (DE)', type: 'text', required: true },
  { key: 'name_en', label: 'Name (EN)', type: 'text' },
])

async function handleSubmit(data) {
  loading.value = true
  errors.value = {}
  try {
    if (isEdit.value) {
      await comparisonOperatorGroups.update(props.group.id, data)
    } else {
      await comparisonOperatorGroups.create(data)
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
      {{ isEdit ? 'Vergleichsoperator-Gruppe bearbeiten' : 'Neue Vergleichsoperator-Gruppe' }}
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
