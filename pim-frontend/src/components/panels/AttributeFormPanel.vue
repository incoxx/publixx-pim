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
    ? {
        ...props.attribute,
        child_attribute_ids: (props.attribute.children || []).map(c => c.id),
      }
    : {
        technical_name: '',
        name_de: '',
        name_en: '',
        data_type: '',
        attribute_type_id: '',
        value_list_id: '',
        child_attribute_ids: [],
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
      options: ['String', 'Number', 'Float', 'Date', 'Flag', 'Selection', 'Dictionary', 'Composite', 'RichText']
        .map(t => ({ value: t, label: t })),
    },
    {
      key: 'attribute_type_id', label: 'Attributgruppe', type: 'select',
      options: [{ value: '', label: '— Keine —' }, ...store.types.map(t => ({ value: t.id, label: t.name_de || t.technical_name }))],
    },
  ]

  if (formData.value.data_type === 'Selection' || formData.value.data_type === 'Dictionary') {
    base.push({
      key: 'value_list_id', label: formData.value.data_type === 'Dictionary' ? 'Werteliste (Wörterbuch)' : 'Werteliste', type: 'select',
      options: [{ value: '', label: '— Keine —' }, ...store.lists.map(l => ({ value: l.id, label: l.name_de || l.technical_name }))],
      hint: formData.value.data_type === 'Dictionary' ? 'Wörterbucheinträge können unter Menü → Wörterbuch verwaltet werden.' : undefined,
    })
  }

  // Show composite format field and child attribute selector for Composite attributes
  if (formData.value.data_type === 'Composite') {
    const eligibleChildren = store.items.filter(a => {
      if (a.data_type === 'Composite') return false
      if (a.id === props.attribute?.id) return false
      // Available if unassigned or already assigned to this composite
      if (!a.parent_attribute_id) return true
      if (a.parent_attribute_id === props.attribute?.id) return true
      return false
    })
    if (eligibleChildren.length > 0) {
      base.push({
        key: 'child_attribute_ids', label: 'Kind-Attribute', type: 'multicombobox',
        options: eligibleChildren.map(a => ({ value: a.id, label: a.name_de || a.technical_name })),
        hint: 'Attribute die zu diesem Composite gehören.',
      })
    }
    base.push({
      key: 'composite_format', label: 'Anzeigeformat', type: 'text',
      hint: 'Platzhalter {0}, {1}, {2}… für Kind-Attribute in Reihenfolge. Beispiel: {0} x {1} x {2} mm',
    })
  }

  // Show parent attribute selector only for types that can be children of a composite
  const compositeChildTypes = ['String', 'Number', 'Float', 'Date', 'Flag']
  if (compositeChildTypes.includes(formData.value.data_type)) {
    const composites = store.items.filter(a => a.data_type === 'Composite' && a.id !== props.attribute?.id)
    if (composites.length > 0) {
      base.push({
        key: 'parent_attribute_id', label: 'Übergeordnetes Composite-Attribut', type: 'select',
        options: [{ value: '', label: '— Kein übergeordnetes Attribut —' }, ...composites.map(c => ({ value: c.id, label: c.name_de || c.technical_name }))],
      })
    }
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
    const { child_attribute_ids, ...attrData } = data
    let savedId

    if (isEdit.value) {
      await store.updateAttribute(props.attribute.id, attrData)
      savedId = props.attribute.id
    } else {
      const created = await store.createAttribute(attrData)
      savedId = created.id
    }

    // Update child attribute relationships for Composite attributes
    if (data.data_type === 'Composite' && savedId) {
      const newChildIds = child_attribute_ids || []
      const oldChildIds = (props.attribute?.children || []).map(c => c.id)

      // Assign new children (set parent_attribute_id)
      for (const childId of newChildIds) {
        if (!oldChildIds.includes(childId)) {
          await store.updateAttribute(childId, { parent_attribute_id: savedId })
        }
      }
      // Unassign removed children (clear parent_attribute_id)
      for (const childId of oldChildIds) {
        if (!newChildIds.includes(childId)) {
          await store.updateAttribute(childId, { parent_attribute_id: null })
        }
      }
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
