<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useContentStore } from '@/stores/content'
import PimForm from '@/components/shared/PimForm.vue'

const props = defineProps({
  page: { type: Object, default: null },
  onSaved: { type: Function, default: null },
})

const authStore = useAuthStore()
const contentStore = useContentStore()
const loading = ref(false)
const errors = ref({})

const isEdit = computed(() => !!props.page)
const contentTypes = ref([])

const formData = ref(
  props.page
    ? { ...props.page }
    : {
        content_type_id: '',
        title: '',
        slug: '',
        status: 'draft',
        valid_from: '',
        valid_to: '',
      }
)

const fields = computed(() => [
  {
    key: 'content_type_id', label: 'Seitentyp', type: 'select', required: true,
    disabled: isEdit.value,
    options: contentTypes.value.map(t => ({ value: t.id, label: t.name_de || t.technical_name })),
  },
  { key: 'title', label: 'Titel', type: 'text', required: true },
  { key: 'slug', label: 'URL-Segment (Slug)', type: 'text', required: true, hint: 'z. B. "smart-home"' },
  {
    key: 'status', label: 'Status', type: 'select', required: true,
    options: [
      { value: 'draft', label: 'Entwurf' },
      { value: 'active', label: 'Aktiv' },
      { value: 'inactive', label: 'Inaktiv' },
      { value: 'archived', label: 'Archiviert' },
    ],
  },
  { key: 'valid_from', label: 'Gültig ab', type: 'date', hint: 'Leer = sofort sichtbar' },
  { key: 'valid_to', label: 'Gültig bis', type: 'date', hint: 'Leer = unbegrenzt' },
])

async function handleSubmit(data) {
  loading.value = true
  errors.value = {}
  try {
    // Leere Datumsfelder als null senden
    const payload = { ...data }
    if (!payload.valid_from) payload.valid_from = null
    if (!payload.valid_to) payload.valid_to = null

    const result = isEdit.value
      ? await contentStore.update(props.page.id, payload)
      : await contentStore.create(payload)

    authStore.closePanel()
    if (props.onSaved) props.onSaved(result)
  } catch (e) {
    if (e.response?.status === 422) {
      const serverErrors = e.response.data.errors || {}
      for (const [key, val] of Object.entries(serverErrors)) {
        errors.value[key] = Array.isArray(val) ? val[0] : val
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

onMounted(async () => {
  // force = true: neu angelegte Seitentypen sollen sofort wählbar sein (Cache umgehen)
  contentTypes.value = await contentStore.loadContentTypes(true)
})
</script>

<template>
  <div class="p-4">
    <h3 class="text-sm font-semibold text-[var(--color-text-primary)] mb-4">
      {{ isEdit ? 'Seite bearbeiten' : 'Neue Content-Seite' }}
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
