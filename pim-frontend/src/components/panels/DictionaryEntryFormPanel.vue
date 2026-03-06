<script setup>
import { ref, computed } from 'vue'
import { useDictionaryStore } from '@/stores/dictionary'
import { useAuthStore } from '@/stores/auth'
import PimForm from '@/components/shared/PimForm.vue'

const props = defineProps({
  entry: { type: Object, default: null },
})

const store = useDictionaryStore()
const authStore = useAuthStore()
const loading = ref(false)
const errors = ref({})

const isEdit = computed(() => !!props.entry)

const formData = ref(
  props.entry
    ? { ...props.entry }
    : {
        category: '',
        short_text_de: '',
        short_text_en: '',
        long_text_de: '',
        long_text_en: '',
        status: 'active',
      }
)

const fields = computed(() => [
  { key: 'category', label: 'Kategorie / Gruppe', type: 'text', hint: 'z.B. Verpackung, Material, Farbe' },
  { key: 'short_text_de', label: 'Kurztext (DE)', type: 'text', required: true },
  { key: 'short_text_en', label: 'Kurztext (EN)', type: 'text' },
  { key: 'long_text_de', label: 'Langtext (DE)', type: 'textarea', required: true },
  { key: 'long_text_en', label: 'Langtext (EN)', type: 'textarea' },
  {
    key: 'status', label: 'Status', type: 'select',
    options: [{ value: 'active', label: 'Aktiv' }, { value: 'inactive', label: 'Inaktiv' }],
  },
])

async function handleSubmit(data) {
  loading.value = true
  errors.value = {}
  try {
    if (isEdit.value) {
      await store.updateEntry(props.entry.id, data)
    } else {
      await store.createEntry(data)
    }
    await store.fetchEntries()
    authStore.closePanel()
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
      {{ isEdit ? 'Wörterbucheintrag bearbeiten' : 'Neuer Wörterbucheintrag' }}
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
