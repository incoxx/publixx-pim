<script setup>
import { ref, computed } from 'vue'
import { useHierarchyStore } from '@/stores/hierarchies'
import { useAuthStore } from '@/stores/auth'
import PimForm from '@/components/shared/PimForm.vue'

const props = defineProps({
  node: { type: Object, default: null },
  hierarchyId: { type: String, required: true },
  parentNodeId: { type: String, default: null },
  onSaved: { type: Function, default: null },
})

const store = useHierarchyStore()
const authStore = useAuthStore()
const loading = ref(false)
const errors = ref({})

const isEdit = computed(() => !!props.node)

const formData = ref(
  props.node
    ? {
        name_de: props.node.name_de || '',
        name_en: props.node.name_en || '',
        sort_order: props.node.sort_order || 0,
        is_active: props.node.is_active ?? true,
      }
    : {
        name_de: '',
        name_en: '',
        sort_order: 0,
        is_active: true,
      }
)

const fields = computed(() => [
  { key: 'name_de', label: 'Name (DE)', type: 'text', required: true },
  { key: 'name_en', label: 'Name (EN)', type: 'text' },
  { key: 'sort_order', label: 'Reihenfolge', type: 'number' },
  { key: 'is_active', label: 'Aktiv', type: 'boolean' },
])

async function handleSubmit(data) {
  loading.value = true
  errors.value = {}
  try {
    if (isEdit.value) {
      await store.updateNode(props.node.id, data)
    } else {
      const payload = { ...data }
      if (props.parentNodeId) {
        payload.parent_node_id = props.parentNodeId
      }
      await store.createNode(props.hierarchyId, payload)
    }
    await store.fetchTree(props.hierarchyId)
    authStore.closePanel()
    if (props.onSaved) props.onSaved()
  } catch (e) {
    if (e.response?.status === 422) {
      const serverErrors = e.response.data.errors || {}
      for (const [key, val] of Object.entries(serverErrors)) {
        errors.value[key] = Array.isArray(val) ? val[0] : val
      }
      if (e.response.data.title && !Object.keys(serverErrors).length) {
        errors.value._general = e.response.data.title
      }
    } else if (e.response?.status === 403) {
      errors.value._general = 'Keine Berechtigung für diese Aktion.'
    } else {
      errors.value._general = e.response?.data?.title || 'Ein Fehler ist aufgetreten.'
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="p-4">
    <h3 class="text-sm font-semibold text-[var(--color-text-primary)] mb-4">
      {{ isEdit ? 'Knoten bearbeiten' : 'Neuer Knoten' }}
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
