<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import workflowsApi from '@/api/workflows'
import workflowStatusesApi from '@/api/workflowStatuses'
import PimForm from '@/components/shared/PimForm.vue'
import { Plus, Trash2, ArrowRight } from 'lucide-vue-next'

const props = defineProps({
  workflow: { type: Object, default: null },
  onSaved: { type: Function, default: null },
})

const authStore = useAuthStore()
const loading = ref(false)
const errors = ref({})
const availableStatuses = ref([])
const workflowDetail = ref(null)

const isEdit = computed(() => !!props.workflow)

const formData = ref({
  name: props.workflow?.name || '',
  description: props.workflow?.description || '',
  start_status_id: props.workflow?.start_status_id || '',
  end_status_id: props.workflow?.end_status_id || '',
  is_active: props.workflow?.is_active ?? true,
})

const selectedStatusIds = ref([])
const transitions = ref([])

const fields = computed(() => [
  { key: 'name', label: 'Name', type: 'text', required: true },
  { key: 'description', label: 'Beschreibung', type: 'textarea' },
  {
    key: 'start_status_id', label: 'Start-Status', type: 'select',
    options: selectedStatusIds.value.map(id => {
      const s = availableStatuses.value.find(st => st.id === id)
      return { value: id, label: s?.name || id }
    }),
  },
  {
    key: 'end_status_id', label: 'End-Status', type: 'select',
    options: selectedStatusIds.value.map(id => {
      const s = availableStatuses.value.find(st => st.id === id)
      return { value: id, label: s?.name || id }
    }),
  },
  { key: 'is_active', label: 'Aktiv', type: 'checkbox' },
])

function statusName(id) {
  return availableStatuses.value.find(s => s.id === id)?.name || '?'
}

function statusColor(id) {
  return availableStatuses.value.find(s => s.id === id)?.color || '#6b7280'
}

function toggleStatus(id) {
  const idx = selectedStatusIds.value.indexOf(id)
  if (idx >= 0) {
    selectedStatusIds.value.splice(idx, 1)
    // Remove transitions referencing this status
    transitions.value = transitions.value.filter(t => t.from_status_id !== id && t.to_status_id !== id)
    // Clear start/end if needed
    if (formData.value.start_status_id === id) formData.value.start_status_id = ''
    if (formData.value.end_status_id === id) formData.value.end_status_id = ''
  } else {
    selectedStatusIds.value.push(id)
  }
}

function addTransition() {
  transitions.value.push({ from_status_id: '', to_status_id: '', duration_hours: null, name: '' })
}

function removeTransition(index) {
  transitions.value.splice(index, 1)
}

async function loadData() {
  try {
    const { data } = await workflowStatusesApi.list({ per_page: 200 })
    availableStatuses.value = data.data || data

    if (isEdit.value) {
      const { data: detail } = await workflowsApi.get(props.workflow.id)
      const wf = detail.data || detail
      workflowDetail.value = wf
      selectedStatusIds.value = (wf.statuses || []).map(s => s.id)
      transitions.value = (wf.transitions || []).map(t => ({
        from_status_id: t.from_status_id,
        to_status_id: t.to_status_id,
        duration_hours: t.duration_hours,
        name: t.name || '',
      }))
    }
  } catch (e) {
    console.error('Failed to load workflow data', e)
  }
}

async function handleSubmit(data) {
  loading.value = true
  errors.value = {}
  const payload = {
    ...data,
    status_ids: selectedStatusIds.value,
    transitions: transitions.value.filter(t => t.from_status_id && t.to_status_id),
  }
  try {
    if (isEdit.value) {
      await workflowsApi.update(props.workflow.id, payload)
    } else {
      await workflowsApi.create(payload)
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

onMounted(() => loadData())
</script>

<template>
  <div class="p-4 space-y-6">
    <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">
      {{ isEdit ? 'Workflow bearbeiten' : 'Neuer Workflow' }}
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

    <!-- Status-Auswahl -->
    <div class="border-t border-[var(--color-border)] pt-4">
      <h4 class="text-xs font-semibold text-[var(--color-text-secondary)] mb-2">Status im Workflow</h4>
      <div class="flex flex-wrap gap-2">
        <button
          v-for="status in availableStatuses"
          :key="status.id"
          :class="[
            'px-3 py-1.5 rounded-full text-xs font-medium border transition-colors cursor-pointer',
            selectedStatusIds.includes(status.id)
              ? 'border-[var(--color-accent)] bg-[color-mix(in_srgb,var(--color-accent)_10%,transparent)] text-[var(--color-accent)]'
              : 'border-[var(--color-border)] text-[var(--color-text-secondary)] hover:border-[var(--color-text-tertiary)]'
          ]"
          @click="toggleStatus(status.id)"
        >
          <span class="inline-block w-2.5 h-2.5 rounded-full mr-1.5" :style="{ backgroundColor: status.color }"></span>
          {{ status.name }}
        </button>
      </div>
      <p v-if="availableStatuses.length === 0" class="text-xs text-[var(--color-text-tertiary)]">Keine Workflow-Status vorhanden. Erstellen Sie zuerst Status.</p>
    </div>

    <!-- Übergänge -->
    <div class="border-t border-[var(--color-border)] pt-4">
      <div class="flex items-center justify-between mb-2">
        <h4 class="text-xs font-semibold text-[var(--color-text-secondary)]">Übergänge</h4>
        <button class="pim-btn pim-btn-sm pim-btn-secondary" @click="addTransition">
          <Plus class="w-3.5 h-3.5" :stroke-width="2" />
          Übergang
        </button>
      </div>
      <div v-for="(t, idx) in transitions" :key="idx" class="flex items-center gap-2 mb-2">
        <select v-model="t.from_status_id" class="pim-input text-xs flex-1">
          <option value="">Von…</option>
          <option v-for="id in selectedStatusIds" :key="id" :value="id">{{ statusName(id) }}</option>
        </select>
        <ArrowRight class="w-4 h-4 text-[var(--color-text-tertiary)] shrink-0" :stroke-width="2" />
        <select v-model="t.to_status_id" class="pim-input text-xs flex-1">
          <option value="">Nach…</option>
          <option v-for="id in selectedStatusIds" :key="id" :value="id">{{ statusName(id) }}</option>
        </select>
        <input v-model.number="t.duration_hours" type="number" min="0" placeholder="Std." class="pim-input text-xs w-16" />
        <button class="text-[var(--color-error)] hover:bg-red-50 rounded p-1" @click="removeTransition(idx)">
          <Trash2 class="w-3.5 h-3.5" :stroke-width="2" />
        </button>
      </div>
      <p v-if="transitions.length === 0" class="text-xs text-[var(--color-text-tertiary)]">Keine Übergänge definiert.</p>
    </div>
  </div>
</template>
