<script setup>
import { ref, computed, watch } from 'vue'
import { useAttributeStore } from '@/stores/attributes'
import { useAuthStore } from '@/stores/auth'
import PimForm from '@/components/shared/PimForm.vue'

const props = defineProps({
  attribute: { type: Object, default: null },
})

const store = useAttributeStore()
const authStore = useAuthStore()
const loading = ref(false)
const errors = ref({})

const isEdit = computed(() => !!props.attribute)

const formData = ref(
  props.attribute
    ? { ...props.attribute }
    : {
        technical_name: '',
        name_de: '',
        name_en: '',
        data_type: '',
        attribute_type_id: '',
        value_list_id: '',
        is_translatable: false,
        is_multipliable: false,
        is_searchable: false,
        is_mandatory: false,
        is_unique: false,
        is_inheritable: false,
        is_variant_attribute: false,
        is_internal: false,
        description_de: '',
        status: 'active',
      }
)

const fields = computed(() => {
  const base = [
    { key: 'technical_name', label: 'Technischer Name', type: 'text', required: true, disabled: isEdit.value },
    { key: 'name_de', label: 'Name (DE)', type: 'text', required: true },
    { key: 'name_en', label: 'Name (EN)', type: 'text' },
    {
      key: 'data_type', label: 'Datentyp', type: 'select', required: true,
      options: ['String', 'Number', 'Float', 'Date', 'Flag', 'Selection', 'Dictionary', 'Collection']
        .map(t => ({ value: t, label: t })),
    },
    {
      key: 'attribute_type_id', label: 'Attributgruppe', type: 'select',
      options: [{ value: '', label: '— Keine —' }, ...store.types.map(t => ({ value: t.id, label: t.name_de || t.technical_name }))],
    },
  ]

  if (formData.value.data_type === 'Selection') {
    base.push({
      key: 'value_list_id', label: 'Werteliste', type: 'select',
      options: [{ value: '', label: '— Keine —' }, ...store.lists.map(l => ({ value: l.id, label: l.name_de || l.technical_name }))],
    })
  }

  base.push(
    {
      key: 'status', label: 'Status', type: 'select',
      options: [{ value: 'active', label: 'Aktiv' }, { value: 'inactive', label: 'Inaktiv' }],
    },
    { key: 'is_translatable', label: 'Übersetzbar', type: 'boolean' },
    { key: 'is_multipliable', label: 'Multiplizierbar', type: 'boolean' },
    { key: 'is_searchable', label: 'Suchbar', type: 'boolean' },
    { key: 'is_mandatory', label: 'Pflichtfeld', type: 'boolean' },
    { key: 'is_unique', label: 'Eindeutig', type: 'boolean' },
    { key: 'is_inheritable', label: 'Vererbbar', type: 'boolean' },
    { key: 'is_variant_attribute', label: 'Varianten-Attribut', type: 'boolean' },
    { key: 'is_internal', label: 'Intern', type: 'boolean' },
    { key: 'description_de', label: 'Beschreibung', type: 'textarea' },
  )

  return base
})

async function handleSubmit(data) {
  loading.value = true
  errors.value = {}
  try {
    if (isEdit.value) {
      await store.updateAttribute(props.attribute.id, data)
    } else {
      await store.createAttribute(data)
    }
    await store.fetchAttributes()
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
      {{ isEdit ? 'Attribut bearbeiten' : 'Neues Attribut' }}
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
