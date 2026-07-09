<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useCollectionsStore } from '@/stores/collections'
import PimForm from '@/components/shared/PimForm.vue'

const props = defineProps({
  collection: { type: Object, default: null },
  onSaved: { type: Function, default: null },
})

const authStore = useAuthStore()
const store = useCollectionsStore()
const loading = ref(false)
const errors = ref({})

const isEdit = computed(() => !!props.collection)

const formData = ref(
  props.collection
    ? {
        collection_type_id: props.collection.collection_type_id || '',
        name: props.collection.name || '',
        reference: props.collection.reference || '',
        status: props.collection.status || 'draft',
        language: props.collection.language || 'de',
        currency: props.collection.currency || '',
        valid_from: props.collection.valid_from || '',
        valid_until: props.collection.valid_until || '',
      }
    : {
        collection_type_id: '',
        name: '',
        reference: '',
        status: 'draft',
        language: 'de',
        currency: '',
        valid_from: '',
        valid_until: '',
      }
)

onMounted(() => {
  if (!store.types.length) store.fetchTypes()
})

const collectionTypeOptions = computed(() =>
  store.types.map((t) => ({ value: t.id, label: t.name_de || t.technical_name }))
)

const statusOptions = [
  { value: 'draft', label: 'Entwurf' },
  { value: 'open', label: 'Offen' },
  { value: 'sent', label: 'Versendet' },
  { value: 'archived', label: 'Archiviert' },
]

const fields = computed(() => [
  { key: 'collection_type_id', label: 'Typ', type: 'select', required: true, disabled: isEdit.value, options: collectionTypeOptions.value },
  { key: 'name', label: 'Name', type: 'text', required: true },
  { key: 'reference', label: 'Referenz', type: 'text' },
  { key: 'status', label: 'Status', type: 'select', options: statusOptions },
  { key: 'language', label: 'Sprache', type: 'text' },
  { key: 'currency', label: 'Währung', type: 'text' },
  { key: 'valid_from', label: 'Gültig ab', type: 'date' },
  { key: 'valid_until', label: 'Gültig bis', type: 'date' },
])

async function handleSubmit(data) {
  loading.value = true
  errors.value = {}
  try {
    if (isEdit.value) {
      await store.updateCollection(props.collection.id, data)
    } else {
      await store.createCollection(data)
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
      {{ isEdit ? 'Collection bearbeiten' : 'Neue Collection' }}
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
