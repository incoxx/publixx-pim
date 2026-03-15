<script setup>
import { ref, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import workflowStatusesApi from '@/api/workflowStatuses'
import PimForm from '@/components/shared/PimForm.vue'

const props = defineProps({
  workflowStatus: { type: Object, default: null },
  onSaved: { type: Function, default: null },
})

const authStore = useAuthStore()
const loading = ref(false)
const errors = ref({})

const isEdit = computed(() => !!props.workflowStatus)

const formData = ref(
  props.workflowStatus
    ? {
        name: props.workflowStatus.name || '',
        description: props.workflowStatus.description || '',
        color: props.workflowStatus.color || '#6b7280',
        sort_order: props.workflowStatus.sort_order ?? 0,
      }
    : {
        name: '',
        description: '',
        color: '#6b7280',
        sort_order: 0,
      }
)

const fields = computed(() => [
  { key: 'name', label: 'Name', type: 'text', required: true },
  { key: 'description', label: 'Beschreibung', type: 'textarea' },
  { key: 'color', label: 'Farbe', type: 'color' },
  { key: 'sort_order', label: 'Reihenfolge', type: 'number' },
])

async function handleSubmit(data) {
  loading.value = true
  errors.value = {}
  try {
    if (isEdit.value) {
      await workflowStatusesApi.update(props.workflowStatus.id, data)
    } else {
      await workflowStatusesApi.create(data)
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
      {{ isEdit ? 'Workflow-Status bearbeiten' : 'Neuer Workflow-Status' }}
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

    <!-- Farbvorschau -->
    <div class="border-t border-[var(--color-border)] pt-4">
      <h4 class="text-xs font-semibold text-[var(--color-text-secondary)] mb-2">Vorschau</h4>
      <div class="flex items-center gap-3">
        <span class="inline-block w-8 h-8 rounded-full border border-[var(--color-border)]" :style="{ backgroundColor: formData.color }"></span>
        <span class="text-sm text-[var(--color-text-primary)]">{{ formData.name || 'Status-Name' }}</span>
      </div>
    </div>
  </div>
</template>
